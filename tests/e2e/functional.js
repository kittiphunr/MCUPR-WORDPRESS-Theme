// Functional + accessibility: languages, text size, dark mode, contrast modes, panel, SweetAlert2, menus, axe-core on 4 pages x light/dark
const { B, ok, launch, shot, axeSource, finish } = require('./lib');
(async()=>{
const br=await launch();
const init=()=>localStorage.setItem('mcupr_consent','1');
// cookie / PDPA dialog (fresh visitor)
{const c0=await br.newContext({viewport:{width:1280,height:900}}); const p0=await c0.newPage(); await p0.goto(B+'/');
 await p0.waitForSelector('.swal2-popup');
 ok(await p0.isVisible('.swal2-popup'),'SweetAlert cookie dialog shown');
 ok((await p0.textContent('.swal2-title')).includes('คุกกี้'),'cookie dialog text in Thai');
 ok(await p0.evaluate(()=>document.activeElement.classList.contains('swal2-confirm')),'focus on confirm button');
 await p0.keyboard.press('Escape'); ok(await p0.isVisible('.swal2-popup'),'Esc does not dismiss cookie dialog');
 await p0.click('.swal2-confirm'); await p0.waitForSelector('.swal2-popup',{state:'detached'});
 await p0.reload(); await p0.waitForTimeout(400); ok(!(await p0.locator('.swal2-popup').count()),'cookie dialog not shown again after accept');
 await p0.screenshot({path:shot('shot-home-consented.png')}); await c0.close();}
{const c1=await br.newContext({viewport:{width:1280,height:900}}); const p1=await c1.newPage(); await p1.goto(B+'/?lang=ja'); await p1.waitForSelector('.swal2-popup');
 ok((await p1.textContent('.swal2-title')).includes('Cookie'),'cookie dialog in Japanese'); await p1.screenshot({path:shot('shot-cookie.png')}); await c1.close();}
const ctx=await br.newContext({viewport:{width:1280,height:900}}); await ctx.addInitScript(init);
const page=await ctx.newPage(); const errs=[];
page.on('pageerror',e=>errs.push(e.message)); page.on('console',m=>{if(m.type()==='error')errs.push(m.text()+' '+(m.location().url||''))});
await page.goto(B+'/');
ok(await page.getAttribute('html','lang')==='th','html lang=th default');
ok((await page.textContent('#masthead a[rel=home] span')).includes('มหาวิทยาลัยทดสอบ'),'org name th');
// language
await page.click('nav[aria-label] a[hreflang="en"]');
ok(await page.getAttribute('html','lang')==='en','switch to en');
ok((await page.textContent('#masthead a[rel=home] span')).includes('Test University'),'org name en');
await page.goto(B+'/'); ok(await page.getAttribute('html','lang')==='en','language persisted via cookie');
await page.goto(B+'/?lang=zh');
await page.goto(B+'/'+encodeURIComponent('ข่าวทดสอบ-1')+'/');
ok((await page.textContent('h1')).includes('测试新闻 1'),'zh translated title');
ok((await page.content()).includes('新闻1的中文内容'),'zh translated content');
await page.goto(B+'/'+encodeURIComponent('ข่าวทดสอบ-2')+'/?lang=ja');
ok(await page.isVisible('[role=note]'),'missing translation notice (ja)');
ok(await page.locator('.entry-content[lang="th"]').count()===1,'content lang=th on fallback');
await page.goto(B+'/?lang=th');
// font size
const fs=()=>page.evaluate(()=>getComputedStyle(document.documentElement).fontSize);
ok(await fs()==='16px','font 16px');
await page.click('[data-font-action=inc]'); ok(await fs()==='20px','A+ => 20px'); await page.waitForSelector('.swal2-toast'); ok((await page.textContent('.swal2-toast')).includes('125%'),'SweetAlert toast announces 125%'); ok(await page.getAttribute('.swal2-container','role')===null && await page.evaluate(()=>!!document.querySelector('.swal2-toast[role="status"], .swal2-toast [role="status"], .swal2-toast[aria-live]')),'toast is a live region');
await page.click('[data-font-action=inc]'); await page.click('[data-font-action=inc]'); ok(await fs()==='32px','max 200%');
ok(await page.getAttribute('[data-font-action=inc]','aria-disabled')==='true','inc disabled at max');
await page.reload(); ok(await fs()==='32px','font persisted');
await page.screenshot({path:shot('shot-200.png')});
await page.click('[data-font-action=reset]'); ok(await fs()==='16px','reset');
await page.click('[data-font-action=dec]'); ok(await fs()==='14px','A- => 14px'); await page.click('[data-font-action=reset]');
// dark mode
const bg=()=>page.evaluate(()=>getComputedStyle(document.body).backgroundColor);
const l=await bg(); await page.click('#theme-toggle'); const d=await bg();
ok(l!==d && d==='rgb(18, 18, 20)','dark mode toggles ('+l+' -> '+d+')');
await page.reload(); ok(await bg()===d,'dark persisted, no reload flash');
await page.screenshot({path:shot('shot-dark.png')});
await page.click('#theme-toggle');
// panel + contrast
await page.click('#a11y-toggle'); ok(await page.isVisible('#a11y-panel'),'panel opens');
ok(await page.evaluate(()=>document.activeElement.closest('#a11y-panel')!==null),'focus moved into panel');
await page.check('input[name=mcupr-contrast][value=yb]');
ok(await bg()==='rgb(0, 0, 0)' && await page.evaluate(()=>getComputedStyle(document.body).color)==='rgb(255, 234, 0)','yellow-on-black contrast');
await page.screenshot({path:shot('shot-yb.png')});
await page.check('input[data-pref=gray]'); ok(await page.evaluate(()=>getComputedStyle(document.documentElement).filter)==='grayscale(1)','grayscale');
await page.check('input[data-pref=spacing]'); ok(await page.evaluate(()=>getComputedStyle(document.body).letterSpacing!=='normal'),'text spacing');
await page.check('input[data-pref=images]');
await page.keyboard.press('Escape'); ok(!(await page.isVisible('#a11y-panel')),'Esc closes panel');
ok(await page.evaluate(()=>document.activeElement.id)==='a11y-toggle','focus returns to button');
await page.reload(); ok(await page.evaluate(()=>document.documentElement.dataset.contrast)==='yb' ,'contrast persisted');
await page.click('#a11y-toggle'); await page.click('[data-reset-prefs]');
await page.waitForSelector('.swal2-popup'); ok((await page.textContent('.swal2-title')).includes('คืนค่า'),'reset confirmation dialog');
await page.click('.swal2-cancel'); await page.waitForTimeout(300);
ok(await page.evaluate(()=>document.documentElement.dataset.contrast==='yb'),'cancel keeps settings');
await page.click('[data-reset-prefs]'); await page.waitForSelector('.swal2-popup'); await page.click('.swal2-confirm'); await page.waitForTimeout(300);
ok(await page.evaluate(()=>!document.documentElement.dataset.contrast && !document.documentElement.dataset.gray),'reset all after confirm');
await page.waitForSelector('.swal2-toast',{state:'detached'}).catch(()=>{}); await page.click('[data-close-panel]');
// empty search
await page.fill('#utility-bar input[type=search]',''); await page.click('#utility-bar .search-form button'); await page.waitForSelector('.swal2-popup'); ok((await page.textContent('.swal2-html-container')).includes('ค้นหา'),'empty search warning'); await page.click('.swal2-confirm'); await page.waitForTimeout(200); ok(await page.evaluate(()=>document.activeElement.type==='search'),'focus returns to search input');
// nav submenu keyboard
const tog=page.locator('.submenu-toggle').first();
ok(await tog.count()===1,'submenu toggle injected');
await tog.focus(); await page.keyboard.press('Enter');
ok(await tog.getAttribute('aria-expanded')==='true' && await page.isVisible('.sub-menu'),'submenu opens by keyboard');
await page.keyboard.press('Escape'); ok(!(await page.isVisible('.sub-menu')),'Esc closes submenu');
// skip link
await page.goto(B+'/'); await page.keyboard.press('Tab');
ok(await page.evaluate(()=>document.activeElement.textContent.trim())==='ข้ามไปยังเนื้อหาหลัก','first Tab = skip link');
// OS dark pref
const c2=await br.newContext({colorScheme:'dark'}); await c2.addInitScript(init); const p2=await c2.newPage(); await p2.goto(B+'/');
ok(await p2.evaluate(()=>getComputedStyle(document.body).backgroundColor)==='rgb(18, 18, 20)','auto follows OS dark');
await p2.evaluate(()=>localStorage.setItem('mcupr_prefs','{"theme":"light"}')); await p2.reload();
ok(await p2.evaluate(()=>getComputedStyle(document.body).backgroundColor)==='rgb(255, 255, 255)','explicit light overrides OS dark');
// mobile
const m=await br.newContext({viewport:{width:375,height:800}}); await m.addInitScript(init); const mp=await m.newPage(); await mp.goto(B+'/');
ok(await mp.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'no horizontal scroll @375px');
ok(!(await mp.isVisible('#site-nav')),'nav collapsed on mobile'); await mp.click('.nav-toggle'); ok(await mp.isVisible('#site-nav'),'mobile nav opens');
await mp.screenshot({path:shot('shot-mobile.png'),fullPage:true});
// 200% mobile reflow
await mp.click('[data-font-action=inc]').catch(()=>{});
// axe
for (const [name,url] of [['home','/'],['single','/'+encodeURIComponent('ข่าวทดสอบ-1')+'/'],['404','/nonexist'],['search','/?s=ข่าว']]) {
  for (const theme of ['light','dark']) {
    const pg=await ctx.newPage(); await pg.goto(B+url); await pg.evaluate(t=>localStorage.setItem('mcupr_prefs',JSON.stringify({theme:t})),theme); await pg.reload();
    await pg.addScriptTag({content:axeSource()});
    const r=await pg.evaluate(()=>axe.run(document,{runOnly:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa','best-practice']}));
    ok(r.violations.length===0,`axe ${name}/${theme}: ${r.violations.map(v=>v.id+'('+v.nodes.length+')').join(', ')||'0 violations'}`);
    r.violations.forEach(v=>console.log('   ',v.id,v.nodes.slice(0,2).map(n=>n.target.join(' ')+' | '+(n.any[0]||n.all[0]||{}).message).join(' || ')));
    await pg.close();
  }
}
ok(errs.length===0,'no JS/console errors '+JSON.stringify(errs));
await br.close(); finish();
})();
