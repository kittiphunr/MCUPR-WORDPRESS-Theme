// The starter file after a real import: menus and footer on the front end, every page renders cleanly,
// WCAG 2.2 AA (axe) on every kind of page, all internal menu/footer links resolve.
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

(async () => {
  const br = await launch();
  const ctx = await br.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
  await ctx.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* cross-origin frame (the map) */ } });
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

  // events calendar
  for (const u of ['/events/', '/events-calendar/']) {
    await p.goto(B + u);
    ok((await p.$$('table.cal')).length === 1 && (await p.$$eval('table.cal th[scope=col]', (t) => t.length)) === 7, u + ' shows the month grid with 7 column headers');
    ok(/[๐-๙0-9]/.test(await p.textContent('.cal-title')) && (await p.textContent('.cal-title')).includes(String(new Date().getFullYear() + 543)), u + ': month title uses the Buddhist year');
    ok((await p.$$('.cal-d.is-today')).length === 1, u + ': today is marked');
    ok(!(await p.evaluate(() => /\[mcupr_/.test(document.body.innerText))), u + ': no raw shortcode');
    await p.addScriptTag({ content: axeSource() });
    const ax = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(ax.violations.length === 0, u + ' axe: ' + (ax.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  }
  await p.goto(B + '/events/');
  ok((await p.$$('.ev-row')).length >= 1 && (await p.$$('.cal-evs a')).length >= 1, '/events/ lists the sample events in the grid and in the list');
  const before = await p.textContent('.cal-title');
  await p.click('a[rel=next]');
  ok((await p.textContent('.cal-title')) !== before && /ev_m=\d{4}-\d{2}/.test(p.url()), 'next month navigates (plain link, no JavaScript needed)');
  await p.click('.cal-today');
  ok((await p.textContent('.cal-title')) === before, '"current month" returns');
  await p.goto(B + '/events/?ev_m=2031-03');
  ok((await p.textContent('.cal-title')).includes('2574'), 'a given month renders (มีนาคม 2574)');
  await p.goto(B + '/events/event-1/');
  ok((await p.$eval('h1', (h) => h.textContent)).includes('พิธีเปิด'), 'event page: title');
  ok((await p.$$('.ev-info dt')).length >= 2 && (await p.textContent('.ev-info')).includes('น.'), 'event page: date, time (น.) and place box');
  ok((await p.getAttribute('a[href*="calendar.google.com"]', 'href')).includes('TEMPLATE'), 'event page: Google Calendar link');
  ok(((await p.textContent('h1')) || '').length > 0 && (await p.$$('script[type="application/ld+json"]')).length >= 1, 'event page: structured data');
  await p.addScriptTag({ content: axeSource() });
  const ea = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(ea.violations.length === 0, 'event page axe: ' + (ea.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  const one = await p.request.get(B + '/?mcupr_ics=all');
  const body = await one.text();
  ok((one.headers()['content-type'] || '').includes('text/calendar') && body.startsWith('BEGIN:VCALENDAR') && (body.match(/BEGIN:VEVENT/g) || []).length >= 3, '.ics feed of all events');
  ok((await p.request.get(B + '/?mcupr_ics=999999')).status() === 404, '.ics of a missing event is 404');
  await p.goto(B + '/events/?lang=en');
  ok(/Upcoming events/.test(await p.textContent('main')) && /Sun/.test(await p.textContent('table.cal thead')), 'English: calendar labels');
  await p.goto(B + '/events/?lang=zh');
  ok(/年/.test(await p.textContent('.cal-title')), 'Chinese: 2026年10月-style title');
  await p.goto(B + '/events/?lang=th');

  // footer: contact details + click-to-load map
  await p.goto(B + '/');
  const ft = await p.textContent('#colophon');
  ok(ft.includes('111 หมู่ที่ 5') && ft.includes('054 646 273') && ft.includes('เวลาทำการ'), 'footer shows address, telephone and opening hours');
  ok((await p.getAttribute('#colophon a[href^="tel:"]', 'href')) === 'tel:054646273', 'footer: the telephone is a tap-to-call link');
  ok((await p.$$('#colophon iframe')).length === 0, 'footer: no Google iframe until the visitor asks (no third-party request, fast page)');
  const ext = [];
  p.on('request', (r) => { if (/google\.com\/maps|maps\.google/.test(r.url())) ext.push(r.url()); });
  await p.goto(B + '/');
  ok(ext.length === 0, 'footer: no request to Google Maps on page load');
  await p.click('#colophon [data-map-load]');
  await p.waitForSelector('#colophon iframe.fmap-frame');
  ok((await p.getAttribute('#colophon iframe', 'src')).includes('output=embed') && !!(await p.getAttribute('#colophon iframe', 'title')), 'footer: pressing "Show map" loads the titled Google map');
  ok((await p.$$eval('#colophon a[href*="google.com/maps"]', (a) => a.length)) === 2, 'footer: "Open in Google Maps" and "Directions" links');
  await p.addScriptTag({ content: axeSource() });
  const fx = await p.evaluate(() => axe.run(document.getElementById('colophon'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(fx.violations.length === 0, 'footer axe: ' + (fx.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
  const fd = await p.evaluate(() => axe.run(document.getElementById('colophon'), { runOnly: ['wcag2aa'] }));
  ok(fd.violations.length === 0, 'footer axe (dark): ' + (fd.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.evaluate(() => document.documentElement.removeAttribute('data-theme'));

  // side menu: the left rail's menu card runs the full height of the screen (down to the collapse button)
  for (const [mode, min, h] of [['side', '0', 900], ['side', '1', 900], ['side', '0', 560], ['hybrid', '1', 900]]) {
    const sc = await br.newContext({ viewport: { width: 1600, height: h }, reducedMotion: 'reduce' });
    await sc.addInitScript(([m, mn]) => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: m })); localStorage.setItem('mcupr_sbmin', mn); } catch (e) { /* frame */ } }, [mode, min]);
    const sp = await sc.newPage();
    await sp.goto(B + '/sitemap/');
    const g = await sp.evaluate(() => { const cap = document.querySelector('.site-nav > .sb-cap:not(.sb-foot)').getBoundingClientRect(); const f = document.querySelector('.sb-foot').getBoundingClientRect(); const n = document.querySelector('.site-nav').getBoundingClientRect(); return { capBottom: cap.bottom, footTop: f.top, footH: f.height, navBottom: n.bottom, vh: innerHeight }; });
    const okRail = mode === 'hybrid' ? g.capBottom >= g.vh - 40 : (g.footTop - g.capBottom <= 24 && g.vh - (g.footTop + g.footH) <= 40);
    ok(okRail, mode + (min === '1' ? ' (collapsed)' : '') + ' menu, ' + h + 'px high: the rail card fills the screen height (card ends at ' + Math.round(g.capBottom) + ' of ' + g.vh + ')');
    await sc.close();
  }

  // bento: sidebar card | rounded content frame; collapsed active item is a circle inside the card
  for (const [mode, min] of [['side', '0'], ['side', '1'], ['hybrid', '1']]) {
    const bc = await br.newContext({ viewport: { width: 1500, height: 860 }, reducedMotion: 'reduce' });
    await bc.addInitScript(([m, mn]) => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: m })); localStorage.setItem('mcupr_sbmin', mn); } catch (e) { /* frame */ } }, [mode, min]);
    const bp = await bc.newPage();
    await bp.goto(B + '/');
    const g = await bp.evaluate(() => {
      const nav = document.querySelector('.site-nav').getBoundingClientRect();
      const card = document.querySelector('.site-nav > .sb-cap:not(.sb-foot)').getBoundingClientRect();
      const page = document.getElementById('page');
      const pr = page.getBoundingClientRect();
      const cur = document.querySelector('.site-nav .menu > .current-menu-item > a');
      const cr = cur ? cur.getBoundingClientRect() : null;
      return { navRight: nav.right, cardLeft: card.left, cardRight: card.right, frameLeft: pr.left, frameRight: pr.right, vw: innerWidth, radius: parseFloat(getComputedStyle(page).borderTopLeftCornerRadius || getComputedStyle(page).borderTopLeftRadius), cur: cr && { w: cr.width, h: cr.height, l: cr.left, r: cr.right, br: parseFloat(getComputedStyle(cur).borderTopLeftRadius) }, overflow: document.documentElement.scrollWidth > innerWidth + 1 };
    });
    const tag = mode + (min === '1' ? ' collapsed' : ' expanded');
    ok(g.cardLeft >= 8 && g.navRight - g.cardRight >= 8, tag + ': the menu card keeps a margin to the sidebar edge (left ' + Math.round(g.cardLeft) + 'px, right ' + Math.round(g.navRight - g.cardRight) + 'px)');
    ok(g.frameLeft - g.cardRight >= 8 && g.vw - g.frameRight >= 8, tag + ': the content frame is separate from the sidebar and from the screen edge');
    ok(g.radius >= 24, tag + ': the content frame has rounded corners (' + g.radius + 'px)');
    ok(!g.overflow, tag + ': no horizontal scroll');
    if (min === '1' && g.cur) { ok(Math.abs(g.cur.w - g.cur.h) <= 1 && g.cur.br >= g.cur.w / 2 - 1 && g.cur.l >= g.cardLeft + 2 && g.cur.r <= g.cardRight - 2, tag + ': the active item is a circle (' + Math.round(g.cur.w) + '×' + Math.round(g.cur.h) + ') inside the card'); }
    await bc.close();
  }

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
  for (const s of ['/', '/about/', '/ita/', '/privacy-policy/', '/events/', '/events/event-1/']) {
    await mp.goto(B + s);
    ok(await mp.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'mobile 360px: no horizontal scroll on ' + s);
  }
  await mp.setViewportSize({ width: 240, height: 700 });
  await mp.goto(B + '/events/');
  ok(await mp.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'events calendar fits 240px without horizontal scroll');
  await p.screenshot({ path: shot('starter-about.png') });
  ok(!errors.length, 'no console / page errors' + (errors.length ? ': ' + errors[0] : ''));
  await br.close();
  finish();
})();
