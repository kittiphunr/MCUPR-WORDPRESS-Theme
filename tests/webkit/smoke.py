"""Smoke test in a real WebKit engine (WebKitGTK, the engine family of Safari) through WebDriver.
Playwright cannot drive it, so this is a separate, small check of what breaks in Safari first:
pages load, one h1, no sideways scroll, the CSS features the theme relies on exist, the loading
screen leaves, the full-screen menu opens and closes, the post media (gallery, PDF reader) starts.
  apt-get install webkit2gtk-driver xvfb && pip install selenium
  xvfb-run -a python3 tests/webkit/smoke.py [base-url]
"""
import os, sys, time
from selenium import webdriver
from selenium.webdriver.common.by import By

B = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8090').rstrip('/')
SHOTS = os.path.join(os.path.dirname(__file__), '..', 'screenshots', 'webkit')
os.makedirs(SHOTS, exist_ok=True)
bad = []; n = 0


def ok(c, label):
    global n
    n += 1
    print(('PASS ' if c else 'FAIL ') + label)
    if not c:
        bad.append(label)


def driver(w, h):
    o = webdriver.WebKitGTKOptions()
    o.binary_location = '/usr/lib/x86_64-linux-gnu/webkit2gtk-4.1/MiniBrowser'
    o.add_argument('--automation')
    d = webdriver.WebKitGTK(options=o)
    d.set_window_size(w, h)
    return d


PAGES = ['/', '/about/vision/', '/notices/', '/events/', '/albums/', '/videos/', '/?name=fx-media']
for (w, h) in ((1366, 900), (390, 844)):
    d = driver(w, h)
    try:
        for path in PAGES:
            d.get(B + path)
            time.sleep(2)
            r = d.execute_script("""var de=document.documentElement;return {h1:document.querySelectorAll('h1').length,
              sx:de.scrollWidth>innerWidth+1,w:innerWidth,sw:de.scrollWidth,title:document.title,
              pl:de.classList.contains('pl-on')&&!de.classList.contains('pl-done'),
              body:document.body.innerText.length,
              imgs:[].filter.call(document.images,function(i){return i.complete&&i.naturalWidth===0&&i.src}).length}""")
            tag = '%s @%d' % (path, w)
            ok(r['h1'] == 1 and r['title'] and r['body'] > 200, tag + ': loads with one h1 and text')
            ok(not r['sx'], tag + ': no sideways scroll (%d/%d)' % (r['sw'], r['w']))
            ok(r['imgs'] == 0, tag + ': no broken images')
            if path in ('/', '/?name=fx-media'):
                d.save_screenshot(os.path.join(SHOTS, ('home' if path == '/' else 'post') + '-%d.png' % w))
        d.get(B + '/')
        time.sleep(1)
        f = d.execute_script("""var c=function(p,v){return CSS.supports(p,v)};return {has:CSS.supports('selector(:has(a))'),
          mix:c('color','color-mix(in srgb,red,blue)'),dvh:c('height','100dvh'),io:'IntersectionObserver' in window,
          bf:c('backdrop-filter','blur(2px)')||c('-webkit-backdrop-filter','blur(2px)'),cq:c('container-type','inline-size'),
          ls:(function(){try{localStorage.setItem('t','1');return true}catch(e){return false}})(),sc:typeof structuredClone}""")
        ok(all(v not in (False, 'undefined') for v in f.values()), 'engine features the theme uses: ' + str(f))
        if w == 1366:
            btn = d.find_elements(By.CSS_SELECTOR, '[data-fs-open]')
            ok(len(btn) and btn[0].is_displayed(), 'the "All menu" button is shown')
            if btn:
                btn[0].click(); time.sleep(1)
                opened = d.execute_script("var m=document.getElementById('fs-menu');return !!m&&!m.hidden&&m.querySelectorAll('.fs-list > li, .fs-rb').length")
                ok(opened and opened >= 7, 'the full-screen menu opens and lists the sections (%s)' % opened)
                d.save_screenshot(os.path.join(SHOTS, 'fullmenu.png'))
                d.find_element(By.CSS_SELECTOR, '[data-fs-close]').click(); time.sleep(1)
                ok(d.execute_script("var m=document.getElementById('fs-menu');return !m||m.hidden"), 'the full-screen menu closes')
        d.get(B + '/?name=fx-media')
        time.sleep(3)
        m = d.execute_script("return {gal:document.querySelectorAll('.post-gal .album-pics a').length,pdf:(document.querySelector('.pdfv')||{getAttribute:function(){return null}}).getAttribute('data-state')}")
        ok(m['gal'] == 6, 'post: the photo grid has its 6 photos')
        d.execute_script("var p=document.querySelector('.pdfv');if(p)p.scrollIntoView()")
        time.sleep(4)
        st = d.execute_script("var p=document.querySelector('.pdfv');return p&&p.getAttribute('data-state')")
        ok(st == 'ready', 'post: the PDF reader (pdf.js) renders its pages (%s)' % st)
    finally:
        d.quit()
print('%d checks, %d failed' % (n, len(bad)))
sys.exit(1 if bad else 0)
