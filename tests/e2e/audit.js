// Page-by-page audit: every public address of the starter site at six screen sizes (and in both menu layouts):
// answers 200, no JS errors, no sideways scroll, nothing sticking out of the screen, no broken pictures, one <h1>,
// and WCAG 2.2 AA (axe) at a phone and a desktop size.
const fs = require('fs');
const path = require('path');
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

(async () => {
  const fx = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '.site-starter', 'urls-fixture.json'), 'utf8'));
  const urls = fx.urls.map((u) => u.replace(/^https?:\/\/[^/]+/, B));
  const br = await launch();
  const sizes = [[1920, 1080, 'top'], [1366, 768, 'top'], [1366, 768, 'side'], [1024, 768, 'top'], [768, 1024, 'top'], [390, 844, 'top'], [320, 568, 'top']];
  const axeAt = new Set(['1366top', '390top', '1366side']);
  const problems = {};
  const note = (k, m) => { (problems[k] = problems[k] || []).push(m); };
  let loads = 0;
  for (const [w, h, menu] of sizes) {
    const ctx = await br.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' });
    await ctx.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const p = await ctx.newPage();
    let errs = [];
    p.on('pageerror', (e) => errs.push(String(e).slice(0, 120)));
    p.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource|youtube|ytimg|gravatar|google/.test(m.text())) { errs.push(m.text().slice(0, 120)); } });
    await p.route(/youtube|ytimg|gravatar|google\.com|googleapis|gstatic/, (r) => r.abort());
    for (const url of urls) {
      loads++;
      errs = [];
      let res;
      try { res = await p.goto(url, { waitUntil: 'load', timeout: 30000 }); } catch (e) { note('load', w + menu + ' ' + url + ' ' + String(e).slice(0, 60)); continue; }
      await p.evaluate((m) => document.documentElement.setAttribute('data-menu', m), menu);
      await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 700) { scrollTo(0, y); await new Promise((r) => setTimeout(r, 40)); } scrollTo(0, 0); });
      await p.waitForTimeout(150);
      const where = w + 'x' + h + ' ' + menu + ' ' + url.replace(B, '');
      if (res.status() >= 400) { note('status', where + ' → ' + res.status()); }
      const r = await p.evaluate(() => {
        const vw = innerWidth;
        const out = [];
        document.querySelectorAll('body *').forEach((e) => {
          const cs = getComputedStyle(e);
          if (cs.display === 'none' || cs.visibility === 'hidden' || cs.position === 'fixed' || e.closest('[hidden], .sr-only, .swal2-container, #back-to-top, .maint-bar, svg *')) { return; }
          const b = e.getBoundingClientRect();
          if (b.width === 0 || b.height === 0 || b.right <= vw + 1) { return; }
          for (let a = e.parentElement; a && a !== document.body; a = a.parentElement) {
            const o = getComputedStyle(a).overflowX;
            if (/(auto|scroll|hidden|clip)/.test(o)) { return; } // clipped or scrollable by an ancestor
          }
          if (/^(HTML|BODY)$/.test(e.tagName)) { return; }
          out.push(e.tagName.toLowerCase() + '.' + String(e.className).split(' ')[0] + ' →' + Math.round(b.right));
        });
        const broken = [...document.querySelectorAll('img')].filter((i) => i.complete && i.naturalWidth === 0 && i.getAttribute('src') && getComputedStyle(i).display !== 'none' && i.getBoundingClientRect().width > 0).map((i) => (i.getAttribute('src') || '').slice(-40));
        return { hs: document.documentElement.scrollWidth > vw + 1, sw: document.documentElement.scrollWidth, out: out.slice(0, 3), broken, h1: document.querySelectorAll('h1').length };
      });
      if (r.hs) { note('hscroll', where + ' (page ' + r.sw + ' > ' + w + ')'); }
      if (r.out.length) { note('overflow', where + ': ' + r.out.join(', ')); }
      if (r.broken.length) { note('images', where + ': ' + r.broken.join(', ')); }
      if (r.h1 !== 1 && !/\/page\/2\/|search|zzzz/.test(url)) { note('h1', where + ' has ' + r.h1 + ' h1'); }
      if (errs.length) { note('js', where + ': ' + errs[0]); }
      if (axeAt.has(w + menu)) {
        await p.addScriptTag({ content: axeSource() });
        const ax = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
        if (ax.violations.length) { note('axe', where + ': ' + ax.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes[0].target.join(' ')).join('; ').slice(0, 200)); }
      }
    }
    await ctx.close();
  }
  for (const k of ['load', 'status', 'js', 'hscroll', 'overflow', 'images', 'h1', 'axe']) {
    const list = problems[k] || [];
    ok(list.length === 0, 'audit ' + k + ': ' + (list.length ? list.length + ' problem(s) — ' + list.slice(0, 8).join(' | ') : 'none in ' + loads + ' page loads (' + urls.length + ' pages × ' + sizes.length + ' sizes/layouts)'));
  }
  await br.close();
  finish();
})();
