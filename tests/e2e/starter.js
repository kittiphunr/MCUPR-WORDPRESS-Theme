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
  const bar = await p.$$eval('.sb-cap .menu > li:not(.menu-more), .sb-cap .menu-more > .sub-menu > li', (l) => l.map((x) => x.textContent.trim().replace(/\s+/g, ' ')));
  ok(bar.length >= 6, 'home: main menu shows ' + bar.length + ' items');
  ok(bar.some((t) => /เกี่ยวกับ/.test(t)) && bar.some((t) => /ธรรมาภิบาล/.test(t)), 'home: Thai labels in the main menu');
  ok((await p.$$('.menu-item.is-mega, li.mega, [class*="mega"]')).length >= 3, 'home: 3 mega menus rendered');
  ok((await p.$$('.menu-label')).length >= 6, 'mega column headings are labels without a link');
  const footLinks = await p.$$eval('footer .footer-cols a', (a) => a.map((x) => x.getAttribute('href')));
  ok(footLinks.length >= 20, 'footer: ' + footLinks.length + ' links in 4 columns');
  const policy = await p.$$eval('footer a', (a) => a.map((x) => x.getAttribute('href') || '').filter((h) => /privacy-policy|cookie-policy|accessibility-statement|sitemap|contact/.test(h)));
  ok(policy.length >= 4, 'footer: policy / accessibility / sitemap / contact links present (' + policy.length + ')');

  // narrow desktop: the overflow "More" menu opens its mega item inline (no tall flyout over the page)
  await p.setViewportSize({ width: 1280, height: 720 });
  await p.goto(B + '/');
  await p.waitForTimeout(600);
  if (await p.$('.more-btn')) {
    await p.click('.more-btn');
    const tog = await p.$('.menu-more .mcupr-mega > .submenu-toggle');
    if (tog) { await tog.click(); }
    const box = await p.evaluate(() => {
      const more = document.querySelector('.menu-more > .sub-menu').getBoundingClientRect();
      const inner = document.querySelector('.menu-more .mcupr-mega > .sub-menu');
      const r = inner ? inner.getBoundingClientRect() : null;
      return { more: more.toJSON(), inner: r && r.toJSON(), pos: inner && getComputedStyle(inner).position, vh: innerHeight, vw: innerWidth };
    });
    ok(box.more.bottom <= box.vh && box.more.left >= 0 && box.more.right <= box.vw, 'More panel stays inside the viewport');
    ok(!box.inner || (box.pos === 'static' && box.inner.left >= box.more.left - 1), 'a mega item inside More opens inline, not as a flyout');
  }
  await p.setViewportSize({ width: 1366, height: 900 });

  // top bar proportions + the icon of the active item stays visible (>= 3:1 against its pill) in every colour mode
  await p.setViewportSize({ width: 1440, height: 900 });
  const lum = (c) => { const v = c.match(/[\d.]+/g).slice(0, 3).map((x) => { x = x / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  for (const mode of ['light', 'dark', 'contrast']) {
    await p.goto(B + '/about/');
    await p.evaluate((m) => { const r = document.documentElement; if (m === 'dark') { r.setAttribute('data-theme', 'dark'); } if (m === 'contrast') { r.setAttribute('data-contrast', 'high'); } }, mode);
    const r = await p.evaluate(() => {
      const a = document.querySelector('.site-nav .menu > .current-menu-ancestor > a, .site-nav .menu > .current-menu-item > a');
      const ic = a.querySelector('.ic');
      return { icon: getComputedStyle(ic).color, pill: getComputedStyle(a).backgroundColor, text: getComputedStyle(a).color, shown: ic.getBoundingClientRect().width > 8 };
    });
    ok(r.shown && ratio(r.icon, r.pill) >= 3, mode + ': icon of the active menu item is visible on its pill (' + ratio(r.icon, r.pill).toFixed(1) + ':1)');
  }
  await p.goto(B + '/about/');
  const hs = await p.evaluate(() => ({ util: document.querySelector('.utility-menu').closest('div.bg-bar').getBoundingClientRect().height, utilFont: parseFloat(getComputedStyle(document.querySelector('.utility-menu a')).fontSize), navFont: parseFloat(getComputedStyle(document.querySelector('.site-nav .menu > li > a')).fontSize) }));
  ok(hs.util <= 42, 'utility bar is compact (' + Math.round(hs.util) + 'px)');
  ok(hs.utilFont < hs.navFont, 'utility links are smaller than the main menu (' + hs.utilFont + 'px < ' + hs.navFont + 'px)');

  // the page band runs up to the black top bar (the floating header sits on it)
  for (const [w, u] of [[1440, '/about/'], [390, '/about/'], [1440, '/?s=a']]) {
    await p.setViewportSize({ width: w, height: 800 });
    await p.goto(B + u);
    const d = await p.evaluate(() => ({ band: document.querySelector('#content .mcu-wheel').getBoundingClientRect().top, bar: document.querySelector('.bg-bar').getBoundingClientRect().bottom, hdr: document.getElementById('masthead').getBoundingClientRect().top }));
    ok(Math.abs(d.band - d.bar) <= 1 && d.hdr >= d.bar, w + 'px ' + u + ': the pink band starts at the top bar and the header floats on it');
  }
  await p.setViewportSize({ width: 1440, height: 900 });

  // only one dropdown / mega panel open at a time
  await p.setViewportSize({ width: 1440, height: 900 });
  await p.goto(B + '/');
  const tg = await p.$$('.site-nav .menu > li > .submenu-toggle');
  await tg[0].click(); await tg[1].click();
  ok((await p.$$('.site-nav li.is-open')).length === 1, 'opening a second menu item closes the first (one panel at a time)');
  await p.keyboard.press('Escape');

  // full-screen menu
  ok(await p.isVisible('[data-fs-open]'), 'desktop: the "All menu" button is shown');
  ok((await p.$('#fs-menu')) === null, 'full-screen menu is not in the page until it is opened');
  await p.click('[data-fs-open]');
  await p.waitForSelector('#fs-menu:not([hidden])');
  ok(await p.evaluate(() => document.activeElement && document.activeElement.hasAttribute('data-fs-close')), 'full-screen menu: focus moves to the close button');
  ok((await p.$$eval('#fs-menu .fs-list > li', (l) => l.length)) >= 7, 'full-screen menu lists every top-level item (incl. those in "More")');
  ok((await p.$$eval('#fs-menu a[href]', (a) => a.length)) >= 40, 'full-screen menu lists the sub-pages too');
  ok(await p.evaluate(() => !document.querySelector('#fs-menu .submenu-toggle, #fs-menu [id]')), 'full-screen menu has no duplicate ids / leftover toggles');
  await p.addScriptTag({ content: axeSource() });
  const fa = await p.evaluate(() => axe.run(document.getElementById('fs-menu'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(fa.violations.length === 0, 'full-screen menu axe: ' + (fa.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.keyboard.press('Tab'); await p.keyboard.press('Shift+Tab');
  ok(await p.evaluate(() => document.getElementById('fs-menu').contains(document.activeElement)), 'full-screen menu traps focus');
  await p.screenshot({ path: shot('starter-fullscreen.png') });
  await p.keyboard.press('Escape');
  ok(await p.evaluate(() => document.getElementById('fs-menu').hidden && document.activeElement && document.activeElement.hasAttribute('data-fs-open')), 'Escape closes it and returns focus to the button');
  await p.click('[data-fs-open]');
  await p.click('#fs-menu .fs-list a[href*="/ita/"]');
  await p.waitForURL('**/ita/');
  ok((await p.$eval('h1', (h) => h.textContent)).includes('ITA'), 'a link in the full-screen menu navigates');
  await p.setViewportSize({ width: 1366, height: 900 });

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
