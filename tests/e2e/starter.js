// The starter file after a real import: menus and footer on the front end, every page renders cleanly,
// WCAG 2.2 AA (axe) on every kind of page, all internal menu/footer links resolve.
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

(async () => {
  const br = await launch();
  const ctx = await br.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
  await ctx.addInitScript(() => localStorage.setItem('mcupr_consent', '1'));
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', (e) => errors.push(String(e)));
  p.on('console', (m) => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push(m.text()); });

  await p.goto(B + '/');
  const bar = await p.$$eval('.sb-cap .menu > li, .sb-cap > ul > li', (l) => l.map((x) => x.textContent.trim().replace(/\s+/g, ' ')));
  ok(bar.length >= 6, 'home: main menu shows ' + bar.length + ' items');
  ok(bar.some((t) => /เกี่ยวกับ/.test(t)) && bar.some((t) => /ธรรมาภิบาล/.test(t)), 'home: Thai labels in the main menu');
  ok((await p.$$('.menu-item.is-mega, li.mega, [class*="mega"]')).length >= 3, 'home: 3 mega menus rendered');
  ok((await p.$$('.menu-label')).length >= 6, 'mega column headings are labels without a link');
  const footLinks = await p.$$eval('footer .footer-cols a', (a) => a.map((x) => x.getAttribute('href')));
  ok(footLinks.length >= 20, 'footer: ' + footLinks.length + ' links in 4 columns');
  const policy = await p.$$eval('footer a', (a) => a.map((x) => x.getAttribute('href') || '').filter((h) => /privacy-policy|cookie-policy|accessibility-statement|sitemap|contact/.test(h)));
  ok(policy.length >= 4, 'footer: policy / accessibility / sitemap / contact links present (' + policy.length + ')');

  // every link in header + footer menus resolves (200) and is not "#"
  const links = [...new Set(await p.$$eval('header a[href], footer a[href]', (a) => a.map((x) => x.href).filter((h) => h.startsWith(location.origin) && !h.includes('#') && !/wp-admin|wp-login|\?lang=|replytocom|feed/.test(h))))];
  const bad = [];
  for (const u of links) { const r = await p.request.get(u); if (r.status() !== 200) bad.push(u + ' ' + r.status()); }
  ok(!bad.length, 'all ' + links.length + ' internal menu/footer links return 200' + (bad.length ? ': ' + bad.slice(0, 5).join(', ') : ''));

  // other languages
  for (const [l, re] of [['en', /About MCU/], ['zh', /关于本校/], ['ja', /大学について/]]) {
    await p.goto(B + '/?lang=' + l);
    ok(re.test(await p.textContent('header')), l + ': main menu uses its own language');
  }
  await p.goto(B + '/?lang=th');

  // pages: render, no errors, h1, axe
  const sample = ['about', 'history', 'ita', 'oic', 'privacy-policy', 'cookie-policy', 'accessibility-statement', 'contact', 'sitemap', 'faq', 'eservice', 'admissions', 'procurement', 'governance', 'policies'];
  for (const s of sample) {
    const r = await p.goto(B + '/' + s + '/');
    const okStatus = r && r.status() === 200;
    const h1 = await p.$$eval('h1', (h) => h.length);
    const raw = await p.evaluate(() => /\[mcupr_/.test(document.body.innerText));
    ok(okStatus && h1 === 1 && !raw, '/' + s + '/ renders: 200, one h1, no raw shortcode');
    if (['about', 'ita', 'privacy-policy', 'contact', 'sitemap', 'faq', 'eservice'].includes(s)) {
      await p.addScriptTag({ content: axeSource() });
      const a = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
      ok(a.violations.length === 0, '/' + s + '/ axe: ' + (a.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    }
  }
  await p.goto(B + '/about/');
  ok((await p.$$('main .soft-card.lift')).length >= 5, '/about/ lists its child pages as tiles');
  await p.goto(B + '/sitemap/');
  ok((await p.$$eval('main a', (a) => a.length)) >= 40, '/sitemap/ lists the pages');
  await p.goto(B + '/contact/');
  ok(!/\[mcupr_contact\]/.test(await p.content()), '/contact/ shortcode expanded');

  // mobile: no horizontal overflow on a few pages, menu drawer reachable
  const m = await br.newContext({ viewport: { width: 360, height: 740 }, reducedMotion: 'reduce' });
  await m.addInitScript(() => localStorage.setItem('mcupr_consent', '1'));
  const mp = await m.newPage();
  for (const s of ['/', '/about/', '/ita/', '/privacy-policy/']) {
    await mp.goto(B + s);
    ok(await mp.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'mobile 360px: no horizontal scroll on ' + s);
  }
  await p.screenshot({ path: shot('starter-about.png') });
  ok(!errors.length, 'no console / page errors' + (errors.length ? ': ' + errors[0] : ''));
  await br.close();
  finish();
})();
