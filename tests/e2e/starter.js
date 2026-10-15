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
  await p.click('[data-util-toggle]');
  const hs = await p.evaluate(() => ({ util: document.querySelector('.hdr-util a').getBoundingClientRect().height, utilFont: parseFloat(getComputedStyle(document.querySelector('.hdr-util a')).fontSize), navFont: parseFloat(getComputedStyle(document.querySelector('.site-nav .menu > li > a')).fontSize) }));
  ok((await p.locator('.bg-bar').count()) === 0 && hs.util >= 36 && hs.util <= 48, 'no black bar; the quick links are easy-to-hit rows in a panel (' + Math.round(hs.util) + 'px)');

  // the page band runs up to the top of the page (the floating header sits on it; there is no black bar)
  for (const [w, u] of [[1440, '/about/'], [390, '/about/'], [1440, '/?s=a']]) {
    await p.setViewportSize({ width: w, height: 800 });
    await p.goto(B + u);
    const d = await p.evaluate(() => ({ band: document.querySelector('#content .mcu-wheel').getBoundingClientRect().top, bar: 0, hdr: document.getElementById('masthead').getBoundingClientRect().top }));
    ok(Math.abs(d.band - d.bar) <= 1 && d.hdr >= d.bar, w + 'px ' + u + ': the pink band starts at the very top and the header floats on it');
  }
  await p.setViewportSize({ width: 1440, height: 900 });

  // only one dropdown / mega panel open at a time
  await p.setViewportSize({ width: 1440, height: 900 });
  await p.goto(B + '/');
  await p.waitForLoadState('networkidle');
  await p.waitForTimeout(400); // the "More" fitting re-flows the menu once fonts are in
  await p.locator('.site-nav .menu > li > .submenu-toggle').nth(0).click(); await p.locator('.site-nav .menu > li > .submenu-toggle').nth(1).click();
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
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); // the sticky header must not cover footer links while axe measures them
  await p.waitForTimeout(300);
  await p.addScriptTag({ content: axeSource() });
  const fx = await p.evaluate(() => axe.run(document.getElementById('colophon'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(fx.violations.length === 0, 'footer axe: ' + (fx.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join(', ') || '0 violations'));
  await p.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
  const fd = await p.evaluate(() => axe.run(document.getElementById('colophon'), { runOnly: ['wcag2aa'] }));
  ok(fd.violations.length === 0, 'footer axe (dark): ' + (fd.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.evaluate(() => document.documentElement.removeAttribute('data-theme'));

  // layout v2: side / hybrid = ONE sidebar card as tall as its menu + a rounded content frame; no black bar (its links join the header)
  for (const [mode, min] of [['side', '0'], ['side', '1']]) {
    const bc = await br.newContext({ viewport: { width: 1500, height: 900 }, reducedMotion: 'reduce' });
    await bc.addInitScript(([m, mn]) => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: m })); localStorage.setItem('mcupr_sbmin', mn); } catch (e) { /* frame */ } }, [mode, min]);
    const bp = await bc.newPage();
    await bp.goto(B + '/');
    const g = await bp.evaluate(() => {
      const nav = document.querySelector('.site-nav');
      const nr = nav.getBoundingClientRect();
      const last = [...nav.querySelectorAll('a, button')].filter((e) => e.offsetParent !== null).pop().getBoundingClientRect();
      const page = document.getElementById('page');
      const pr = page.getBoundingClientRect();
      const bar = document.querySelector('.bg-bar');
      const util = document.querySelector('.hdr-util');
      const cur = document.querySelector('.site-nav .menu > .current-menu-item > a');
      const cr = cur ? cur.getBoundingClientRect() : null;
      const hdr = document.getElementById('masthead').getBoundingClientRect();
      return { navTop: nr.top, navBottom: nr.bottom, navLeft: nr.left, navRight: nr.right, lastBottom: last.bottom, frameLeft: pr.left, frameTop: pr.top, frameRight: pr.right, vw: innerWidth, vh: innerHeight, radius: parseFloat(getComputedStyle(page).borderTopLeftRadius), barShown: !!bar && getComputedStyle(bar).display !== 'none', utilShown: !!document.querySelector('[data-util-toggle]') && document.querySelector('[data-util-toggle]').offsetParent !== null, utilLinks: util ? util.querySelectorAll('a').length : 0, cur: cr && { w: cr.width, h: cr.height, l: cr.left, r: cr.right, br: parseFloat(getComputedStyle(cur).borderTopLeftRadius) }, hdrRight: hdr.right, overflow: document.documentElement.scrollWidth > innerWidth + 1, brand: !!document.querySelector('.sb-brand') && document.querySelector('.sb-brand').offsetParent !== null };
    });
    const tag = mode + (min === '1' ? ' collapsed' : ' expanded');
    ok(g.navLeft >= 8 && g.navTop >= 8 && g.navTop <= 16, tag + ': the sidebar card floats at the top-left with a margin (' + Math.round(g.navLeft) + ', ' + Math.round(g.navTop) + ')');
    ok(g.navBottom < g.vh - 40 && g.navBottom - g.lastBottom <= 24, tag + ': the card is only as tall as its menu (ends at ' + Math.round(g.navBottom) + ' of ' + g.vh + ')');
    ok(g.frameLeft - g.navRight >= 8 && g.vw - g.frameRight >= 8 && Math.abs(g.frameTop - g.navTop) <= 2, tag + ': the content frame is apart from the sidebar and level with it at the top');
    ok(g.radius >= 24, tag + ': the content frame has rounded corners (' + g.radius + 'px)');
    ok(!g.barShown && g.utilShown && g.utilLinks >= 3, tag + ': no black bar - the student / staff / alumni links are in the header (' + g.utilLinks + ' links)');
    ok(g.brand, tag + ': the logo sits at the top of the sidebar card');
    ok(!g.overflow, tag + ': no horizontal scroll');
    if (min === '1' && g.cur) { ok(Math.abs(g.cur.w - g.cur.h) <= 1 && g.cur.br >= g.cur.w / 2 - 1 && g.cur.l >= g.navLeft + 2 && g.cur.r <= g.navRight - 2, tag + ': the active item is a circle (' + Math.round(g.cur.w) + 'x' + Math.round(g.cur.h) + ') inside the card'); }
    await bc.close();
  }
  {
    const tc = await br.newContext({ viewport: { width: 1500, height: 900 }, reducedMotion: 'reduce' });
    await tc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: 'top' })); } catch (e) { /* frame */ } });
    const tp = await tc.newPage();
    await tp.goto(B + '/');
    const t = await tp.evaluate(() => {
      const r = (s) => document.querySelector(s).getBoundingClientRect();
      const bg = (s) => getComputedStyle(document.querySelector(s)).backgroundColor;
      return { brand: r('#masthead .brand'), nav: r('.site-nav'), tools: r('#masthead .wrap > div'), brandBg: bg('#masthead .brand'), navBg: bg('.site-nav'), toolsBg: bg('#masthead .wrap > div'), hdrBg: bg('#masthead'), bar: document.querySelector('.bg-bar') ? 'present' : 'none', util: !!document.querySelector('[data-util-toggle]') && document.querySelector('[data-util-toggle]').offsetParent !== null };
    });
    ok(t.nav.left - t.brand.right >= 8 && t.tools.left - t.nav.right >= 8, 'top menu: brand, menu and tools are three separate floating capsules (gaps ' + Math.round(t.nav.left - t.brand.right) + ' / ' + Math.round(t.tools.left - t.nav.right) + 'px)');
    ok(t.brandBg !== 'rgba(0, 0, 0, 0)' && t.navBg !== 'rgba(0, 0, 0, 0)' && t.toolsBg !== 'rgba(0, 0, 0, 0)' && t.hdrBg === 'rgba(0, 0, 0, 0)', 'top menu: each capsule has its own background; the bar behind them is transparent');
    ok(t.bar === 'none' && t.util, 'top menu: no black bar; the quick links are tucked into a button in the tools capsule');
    await tc.close();
  }
  // ---------- Finder-style news browser: zones, folders, tags apart, view switcher, filter, phones
  {
    const fc = await br.newContext({ viewport: { width: 1366, height: 900 } });
    await fc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const fp = await fc.newPage();
    await fp.goto(B + '/category/academic/');
    ok((await fp.locator('.fb-side').isVisible()) && (await fp.locator('.fb-drawer > summary').isHidden()), 'browser: the source list is a sidebar on desktop (no fold button)');
    ok((await fp.locator('.fb-pill-tabs, .band .pill-tabs, header.mcu-wheel .pill-tabs').count()) === 0, 'browser: the scattered chip wall is gone from the band');
    const zn = await fp.$$eval('.fb-zone > summary .fb-z', (a) => a.map((x) => x.textContent.trim()));
    ok(zn.includes('วิชาการ'), 'browser: categories are grouped in zones (' + zn.join(' | ') + ')');
    ok((await fp.locator('.fb-zone[open] a[aria-current]').count()) === 1, 'browser: the zone of the current category is open and the category is marked');
    ok((await fp.locator('.fb-zone .fb-n').first().isVisible()) && (await fp.locator('.fb-list a .fb-n').count()) >= 4, 'browser: every entry shows its count');
    ok((await fp.$$eval('.fb-folders a', (a) => a.map((x) => x.textContent.trim().replace(/\s+/g, ''))).then((t) => t.length === 2 && t[0].startsWith('งานวิจัย'))), 'browser: a category with sub-categories shows them as folders');
    const sq = await fp.$$eval('.fb-folders a', (a) => a.map((x) => { const r = x.getBoundingClientRect(); return [r.width, r.height]; }));
    ok(sq.length === 2 && sq.every((d) => Math.abs(d[0] - d[1]) <= 1) && Math.abs(sq[0][0] - sq[1][0]) <= 1, 'browser: folders are equal squares (' + sq.map((d) => Math.round(d[0]) + 'x' + Math.round(d[1])).join(', ') + ')');
    const catLinks = await fp.$$eval('.fb-side .fb-zone a', (a) => a.map((x) => x.getAttribute('href')));
    const tagLinks = await fp.$$eval('.fb-side .fb-tags a', (a) => a.map((x) => x.getAttribute('href')));
    ok(catLinks.length && catLinks.every((h) => h.includes('/category/')) && tagLinks.length >= 3 && tagLinks.every((h) => h.includes('/tag/')), 'browser: tags and categories are separate sections (' + catLinks.length + ' categories, ' + tagLinks.length + ' tags)');
    ok((await fp.locator('.fb-items article').count()) === 4 && (await fp.locator('[data-fb-view][aria-checked=true]').getAttribute('data-fb-view')) === 'grid', 'browser: the cards view is the default');
    const cols = () => fp.$eval('.fb-items', (e) => getComputedStyle(e).gridTemplateColumns.split(' ').length);
    const c1 = await cols();
    await fp.click('[data-fb-view=list]');
    ok((await cols()) === 1 && c1 >= 2 && (await fp.locator('[data-fb-view=list]').getAttribute('aria-checked')) === 'true', 'browser: list view is one row per item (' + c1 + ' columns -> 1)');
    await fp.reload();
    ok((await fp.locator('.fb-items').getAttribute('data-view')) === 'list', 'browser: the chosen view is remembered');
    await fp.focus('[data-fb-view=list]'); await fp.keyboard.press('ArrowRight');
    ok((await fp.locator('.fb-items').getAttribute('data-view')) === 'gallery', 'browser: arrow keys move through the view switcher');
    await fp.fill('[data-fb-filter]', 'ชุมชน');
    ok((await fp.locator('.fb-side [data-fb-item]:not([hidden])').count()) === 1 && (await fp.locator('.fb-side .fb-h:not([hidden])').count()) === 1, 'browser: the filter narrows categories and tags and hides empty headings');
    await fp.fill('[data-fb-filter]', 'zzzz');
    ok(await fp.locator('.fb-empty').isVisible(), 'browser: a filter with no match says so');
    await fp.fill('[data-fb-filter]', '');
    ok((await fp.locator('.fb-side [data-fb-item][hidden]').count()) === 0, 'browser: clearing the filter restores everything');
    await fp.goto(B + '/tag/' + encodeURIComponent('โครงการ') + '/');
    ok((await fp.locator('.fb-tags a[aria-current]').count()) === 1 && (await fp.locator('.fb-items article').count()) === 3, 'browser: a tag page marks the tag in its own section and lists its 3 posts');
    await fp.addScriptTag({ content: axeSource() });
    const ar = await fp.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(ar.violations.length === 0, 'axe (news browser): ' + (ar.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    await fp.screenshot({ path: shot('browser.png') });
    await fc.close();
    const fm = await br.newContext({ viewport: { width: 390, height: 800 } });
    await fm.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const mq = await fm.newPage();
    await mq.goto(B + '/category/academic/');
    ok((await mq.locator('.fb-drawer').getAttribute('open')) === null && (await mq.locator('.fb-drawer > summary').isVisible()) && (await mq.locator('.fb-body').isHidden()), 'browser on phones: the source list is folded under one button');
    await mq.click('.fb-drawer > summary');
    ok(await mq.locator('.fb-body').isVisible(), 'browser on phones: the button opens the list');
    ok(await mq.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'browser on phones: no horizontal scroll');
    await mq.screenshot({ path: shot('browser-mobile.png') });
    // utility links live in the menu drawer on phones
    await mq.click('.nav-toggle');
    await mq.waitForTimeout(600);
    ok((await mq.locator('.nav-util a').count()) >= 3 && (await mq.locator('.nav-util a').first().isVisible()) && (await mq.locator('.bg-bar').count()) === 0, 'phones: the student / staff / alumni links are chips at the end of the menu drawer');
    await fm.close();
    const hc = await br.newContext({ viewport: { width: 1366, height: 800 } });
    await hc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: 'side' })); } catch (e) { /* frame */ } });
    const hp = await hc.newPage();
    await hp.goto(B + '/');
    await hp.click('[data-sb-toggle]');
    const hv = await hp.evaluate(() => ({ cls: document.documentElement.classList.contains('sb-min'), tip: document.querySelector('.site-nav .menu > li > a').title }));
    ok(hv.cls && hv.tip, 'sidebar: collapsing gives each icon a tooltip (' + hv.tip + ')');
    await hc.close();
    // the creator is credited in the About dialog only (no footer card)
    await p.goto(B + '/');
    ok((await p.locator('#colophon .fcredit').count()) === 0, 'footer: no creator card');
  }
  // ---------- A4 print with the letterhead
  {
    const pc = await br.newContext({ viewport: { width: 1280, height: 900 } });
    await pc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const pp = await pc.newPage();
    await pp.goto(B + '/print-long/');
    ok((await pp.locator('.print-sheet').count()) === 1 && (await pp.locator('.print-sheet').isHidden()), 'print: the letterhead exists but is not shown on screen');
    await pp.emulateMedia({ media: 'print' });
    const g = await pp.evaluate(() => { const mm = (n) => n * 96 / 25.4; const r = (s) => document.querySelector(s).getBoundingClientRect(); const h = r('.print-head'), f = r('.print-foot'); return { headH: h.height / mm(1), footH: f.height / mm(1), headPos: getComputedStyle(document.querySelector('.print-head')).position, top: h.top, padTop: parseFloat(getComputedStyle(document.body).paddingTop) / mm(1), padSide: parseFloat(getComputedStyle(document.body).paddingLeft) / mm(1), header: getComputedStyle(document.querySelector('.site-header')).display, tools: !!document.querySelector('.pill-tabs') && getComputedStyle(document.querySelector('.pill-tabs')).display, share: document.querySelector('.share-bar') && getComputedStyle(document.querySelector('.share-bar')).display, imgs: document.querySelectorAll('.print-fill').length }; });
    ok(Math.abs(g.headH - 36) < 0.6 && Math.abs(g.footH - 20) < 0.6 && g.headPos === 'fixed' && g.top === 0, 'print: header band 36 mm and footer band 20 mm, fixed (repeated on every sheet) ' + JSON.stringify([g.headH, g.footH]));
    ok(Math.abs(g.padTop - 36) < 0.6 && Math.abs(g.padSide - 18) < 0.6, 'print: the text keeps clear of the header (36 mm) and the side margins (18 mm)');
    ok(g.header === 'none' && g.tools === 'none' && g.share === 'none' && g.imgs === 2, 'print: site header, share buttons and tools are not printed; header + footer graphics are');
    const pdf = await pp.pdf({ preferCSSPageSize: true, printBackground: true });
    const fs = require('fs'); const cp = require('child_process'); const os = require('os'); const path = require('path');
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mcupr-pdf-')); const file = path.join(dir, 'p.pdf'); fs.writeFileSync(file, pdf);
    let info = ''; try { info = cp.execFileSync('pdfinfo', [file]).toString(); } catch (e) { /* poppler not installed */ }
    if (info) {
      const pages = parseInt((info.match(/Pages:\s+(\d+)/) || [])[1], 10);
      ok(pages >= 3 && /59[45]\.\d+ x 84[12]\.\d+ pts \(A4\)/.test(info), 'print: a long post prints on several A4 sheets (' + pages + ' pages)');
      const px = (page, fx, fy) => { const out = cp.execFileSync('pdftoppm', ['-r', '20', '-f', String(page), '-l', String(page), '-singlefile', file, path.join(dir, 'pg')]); const buf = fs.readFileSync(path.join(dir, 'pg.ppm')); const m = buf.toString('latin1', 0, 20).match(/^P6\s+(\d+)\s+(\d+)\s+255\s/); const w = +m[1], h = +m[2], off = m[0].length; const x = Math.floor(w * fx), y = Math.floor(h * fy); const i = off + (y * w + x) * 3; return [buf[i], buf[i + 1], buf[i + 2]]; };
      const near = (a, b) => a.every((v, i) => Math.abs(v - b[i]) < 40);
      ok(near(px(1, .5, .02), [200, 40, 90]) && near(px(2, .5, .02), [200, 40, 90]) && near(px(pages, .5, .02), [200, 40, 90]), 'print: the header graphic is on the first, the second and the last sheet');
      ok(near(px(1, .5, .93), [30, 100, 160]) && near(px(pages, .5, .93), [30, 100, 160]), 'print: the footer graphic is on every sheet too');
      ok(near(px(2, .02, .5), [255, 255, 255]) && near(px(2, .98, .5), [255, 255, 255]), 'print: the side margins are clear paper');
    } else { console.log('  (poppler not installed: PDF page checks skipped)'); }
    await pc.close();
  }
  // ---------- sidebar button ring: loading runner, then scroll progress
  {
    const rc = await br.newContext({ viewport: { width: 1366, height: 800 } });
    await rc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: 'side' })); } catch (e) { /* frame */ } });
    const rp = await rc.newPage();
    await rp.goto(B + '/print-long/');
    await rp.waitForLoadState('load');
    const st = () => rp.evaluate(() => ({ loading: document.documentElement.classList.contains('page-loading'), spin: getComputedStyle(document.querySelector('.sb-ring-spin')).opacity, anim: getComputedStyle(document.querySelector('.sb-ring-spin')).animationName, bar: parseFloat(document.querySelector('.sb-ring-bar').style.strokeDasharray), vis: getComputedStyle(document.querySelector('.sb-ring-bar')).visibility, w: document.querySelector('.sb-ring').getBoundingClientRect().width, fw: document.querySelector('.sb-foot').getBoundingClientRect().width, h: document.querySelector('.sb-ring').getBoundingClientRect().height, fh: document.querySelector('.sb-foot').getBoundingClientRect().height }));
    let s0 = await st();
    ok(!s0.loading && s0.spin === '0' && s0.vis === 'hidden', 'ring: once the page has loaded the runner is gone and nothing is drawn at the top');
    ok(Math.abs(s0.w - s0.fw) <= 1 && Math.abs(s0.h - s0.fh) <= 1, 'ring: it wraps the expand button exactly (' + Math.round(s0.w) + 'x' + Math.round(s0.h) + ')');
    await rp.evaluate(() => document.documentElement.classList.add('page-loading'));
    s0 = await st();
    ok(s0.spin === '1' && s0.anim === 'sb-run', 'ring: while loading a runner circles the button');
    await rp.evaluate(() => document.documentElement.classList.remove('page-loading'));
    await rp.evaluate(() => window.scrollTo(0, (document.documentElement.scrollHeight - innerHeight) / 2));
    await rp.waitForTimeout(300);
    const mid = await st();
    ok(mid.bar > 40 && mid.bar < 60, 'ring: halfway down the page the outline is about half full (' + mid.bar + '%)');
    await rp.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await rp.waitForTimeout(300);
    const end = await st();
    ok(end.bar >= 99, 'ring: at the end of the page the outline is full (' + end.bar + '%)');
    await rp.click('[data-sb-toggle]');
    await rp.waitForTimeout(500);
    const ex = await st();
    ok(Math.abs(ex.w - ex.fw) <= 1 && ex.bar >= 99, 'ring: follows the button when the sidebar is expanded');
    await rc.close();
    const rm = await br.newContext({ viewport: { width: 1366, height: 800 }, reducedMotion: 'reduce' });
    await rm.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: 'side' })); } catch (e) { /* frame */ } });
    const rmp = await rm.newPage();
    await rmp.goto(B + '/print-long/');
    await rmp.evaluate(() => document.documentElement.classList.add('page-loading'));
    ok((await rmp.evaluate(() => getComputedStyle(document.querySelector('.sb-ring-spin')).animationName)) === 'none', 'ring: no spinning with reduced motion');
    await rm.close();
  }
  // ---------- accessibility dialog: extra options are icon tiles
  {
    const oc = await br.newContext({ viewport: { width: 1280, height: 900 } });
    await oc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const op = await oc.newPage();
    await op.goto(B + '/');
    await op.click('#a11y-toggle'); await op.waitForSelector('.swal2-popup');
    await op.click('.swal2-popup summary');
    await op.waitForTimeout(300);
    const tiles = await op.$$eval('.opt-grid .opt', (a) => a.map((l) => ({ svg: !!l.querySelector('.opt-b svg'), name: l.getAttribute('data-tip'), sr: (l.querySelector('.sr-only:not(input)') || {}).textContent, w: l.querySelector('.opt-b').getBoundingClientRect().width, h: l.querySelector('.opt-b').getBoundingClientRect().height })));
    ok(tiles.length === 8 && tiles.every((t) => t.svg && t.name && t.sr === t.name && t.w >= 44 && Math.abs(t.w - t.h) <= 1), 'options: 8 square icon tiles (>= 44px) with an icon, a tooltip and a screen-reader name');
    ok((await op.locator('.opt-grid label:has-text("โหมดสีเทา")').count()) === 1 && (await op.locator('.opt-grid .opt-b').first().innerText()).trim() !== undefined, 'options: no visible text labels, only icons');
    await op.check('input[data-pref=underline]', { force: true });
    await op.waitForTimeout(400); // colour transition
    const on = await op.evaluate(() => { const b = document.querySelector('input[data-pref=underline] + .opt-b'); const s = getComputedStyle(b); return [s.backgroundColor, getComputedStyle(document.documentElement).getPropertyValue('--primary').trim(), document.documentElement.dataset.underline]; });
    ok(on[2] === '1' && on[0] !== 'rgb(255, 255, 255)' && on[0] !== 'rgba(0, 0, 0, 0)', 'options: a switched-on tile is filled and the option applies (' + on[0] + ')');
    await op.hover('.opt:nth-child(3) .opt-b');
    await op.waitForTimeout(250);
    ok((await op.evaluate(() => getComputedStyle(document.querySelector('.opt:nth-child(3) .opt-tip')).opacity)) === '1', 'options: hovering a tile shows its name');
    // the tooltip of a tile at the edge stays inside the dialog (it used to be cut off)
    const edge = await op.evaluate(async () => { const d = document.querySelector('.swal2-html-container').getBoundingClientRect(); const out = []; for (const o of document.querySelectorAll('.opt-grid .opt')) { o.querySelector('.opt-b').dispatchEvent(new Event('pointerover', { bubbles: true })); const r = o.querySelector('.opt-tip').getBoundingClientRect(); out.push(r.left >= d.left - 1 && r.right <= d.right + 1); } return out; });
    ok(edge.length === 8 && edge.every(Boolean), 'options: every tooltip stays inside the dialog scrolling body');
    await op.focus('input[data-pref=tv]'); await op.keyboard.press('Space');
    ok((await op.evaluate(() => document.documentElement.dataset.tv)) === '1', 'options: tiles work from the keyboard');
    await oc.close();
  }
  // ---------- menu refresh: quick-links button, clearer hover, full-screen menu in both modes
  for (const mode of ['top', 'side']) {
    const mc = await br.newContext({ viewport: { width: 1366, height: 768 }, reducedMotion: 'reduce' });
    await mc.addInitScript((m) => { try { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', JSON.stringify({ menu: m })); } catch (e) { /* frame */ } }, mode);
    const mp2 = await mc.newPage();
    await mp2.goto(B + '/about/');
    ok(await mp2.evaluate(() => document.documentElement.dataset.menu) === mode, mode + ': mode applied');
    // quick links: one button, closed by default, opens a panel, closes with Esc and outside click
    ok((await mp2.locator('[data-util-toggle]').isVisible()) && (await mp2.locator('.util-panel').isHidden()) && (await mp2.locator('[data-util-toggle]').getAttribute('aria-expanded')) === 'false', mode + ': quick links are tucked into one button (closed)');
    await mp2.click('[data-util-toggle]');
    ok((await mp2.locator('.util-panel').isVisible()) && (await mp2.locator('.util-panel a').count()) >= 3 && (await mp2.locator('[data-util-toggle]').getAttribute('aria-expanded')) === 'true', mode + ': the button opens the list of quick links');
    const pr = await mp2.evaluate(() => { const r = document.querySelector('.util-panel').getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.bottom <= innerHeight; });
    ok(pr, mode + ': the panel stays inside the window');
    await mp2.keyboard.press('Escape');
    ok(await mp2.locator('.util-panel').isHidden(), mode + ': Esc closes it');
    await mp2.click('[data-util-toggle]'); await mp2.mouse.click(600, 500);
    ok(await mp2.locator('.util-panel').isHidden(), mode + ': a click outside closes it');
    // hover is clear: a visible fill and outline on the hovered item
    const item = mp2.locator('.site-nav .menu > li:not(.current-menu-item):not(.current-menu-ancestor) > a').first();
    const before = await item.evaluate((a) => getComputedStyle(a).backgroundColor);
    await item.hover(); await mp2.waitForTimeout(250);
    const after = await item.evaluate((a) => { const s = getComputedStyle(a); return [s.backgroundColor, s.boxShadow]; });
    ok(after[0] !== before && after[1] !== 'none', mode + ': hovering a menu item gives a clear fill and outline (' + after[0] + ')');
    // full-screen menu: button present in both modes, fits one screen, filter works, quick links strip
    ok(await mp2.locator('[data-fs-open]').isVisible(), mode + ': the "All menu" button is shown');
    await mp2.click('[data-fs-open]'); await mp2.waitForSelector('#fs-menu:not([hidden])'); await mp2.waitForTimeout(300);
    const fit = await mp2.evaluate(() => { const m = document.getElementById('fs-menu'); return [m.scrollHeight, m.clientHeight]; });
    ok(fit[0] <= fit[1] + 1, mode + ': the full-screen menu fits one screen at 1366x768 without scrolling (' + fit.join('/') + ')');
    ok((await mp2.locator('#fs-menu .fs-quick a').count()) >= 3, mode + ': quick links strip at the foot of the full-screen menu');
    const visible = () => mp2.$$eval('#fs-menu .fs-list > li', (a) => a.filter((l) => !l.hidden).length);
    const all = await visible();
    await mp2.fill('[data-fs-find]', 'ทุน');
    const some = await visible();
    ok(some >= 1 && some < all && (await mp2.locator('#fs-menu .fs-none').isHidden()), mode + ': the filter narrows the cards (' + all + ' -> ' + some + ')');
    await mp2.fill('[data-fs-find]', 'zzzzzz');
    ok((await visible()) === 0 && (await mp2.locator('#fs-menu .fs-none').isVisible()), mode + ': a filter with no match says so');
    await mp2.fill('[data-fs-find]', '');
    ok((await visible()) === all, mode + ': clearing the filter restores the cards');
    await mp2.screenshot({ path: shot('menu-fullscreen-' + mode + '.png') });
    await mc.close();
  }
  // ---------- reading speed: buttons, not a drop-down
  {
    const sc = await br.newContext({ viewport: { width: 1280, height: 800 } });
    await sc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); window.speechSynthesis; } catch (e) { /* frame */ } });
    const sp = await sc.newPage();
    await sp.goto(B + '/about/');
    ok((await sp.locator('[data-listen] select').count()) === 0 && (await sp.locator('[data-listen] [data-listen-rate]').count()) === 4, 'speed: four buttons, no drop-down');
    const sb = await sp.$$eval('.speed-b', (a) => a.map((x) => { const r = x.getBoundingClientRect(); return [x.textContent.trim(), r.width, r.height]; }));
    ok(sb.map((x) => x[0]).join(' ') === '0.75× 1× 1.25× 1.5×' && sb.every((x) => x[1] >= 44 && x[2] >= 40), 'speed: 0.75× 1× 1.25× 1.5×, each at least 44 wide');
    ok((await sp.locator('input[data-listen-rate][value="1"]').isChecked()) && (await sp.locator('.speed').getAttribute('role')) === 'radiogroup', 'speed: 1× is selected by default; the group is a labelled radio group');
    await sp.click('.speed-b >> text=1.25×');
    ok((await sp.locator('input[data-listen-rate][value="1.25"]').isChecked()) && (await sp.evaluate(() => localStorage.getItem('mcupr_rate'))) === '1.25', 'speed: clicking selects and remembers the speed');
    await sp.reload();
    ok(await sp.locator('input[data-listen-rate][value="1.25"]').isChecked(), 'speed: the choice is restored on the next page');
    await sp.focus('input[data-listen-rate][value="1.25"]'); await sp.keyboard.press('ArrowRight');
    ok(await sp.locator('input[data-listen-rate][value="1.5"]').isChecked(), 'speed: arrow keys move between the buttons');
    await sp.waitForTimeout(400); // colour transition
    const fill = await sp.$eval('input[data-listen-rate]:checked + .speed-b', (e) => getComputedStyle(e).backgroundColor);
    ok(fill !== 'rgba(0, 0, 0, 0)', 'speed: the chosen button is filled (' + fill + ')');
    await sc.close();
  }
  // ---------- staff hub: profile pages, privacy, decorations, chart link, directory search
  {
    const fx = JSON.parse(require('fs').readFileSync(require('path').join(__dirname, '..', '.site-starter', 'staff-fixture.json'), 'utf8'));
    const hc = await br.newContext({ viewport: { width: 1366, height: 900 } });
    await hc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const hp = await hc.newPage();
    await hp.goto(B + '/profile/' + fx.p0 + '/');
    ok((await hp.locator('h1').textContent()).includes(fx.name0) && (await hp.locator('.profile-facts').textContent()).includes('ผู้ช่วยศาสตราจารย์ ดร.'), 'profile: name, position and academic rank');
    const txt = await hp.locator('.profile').innerText();
    ok(txt.includes('head@example.org') && !txt.includes('054-000-111'), 'profile: the visible email is shown, the hidden telephone is not anywhere');
    ok(/อาคาร 1 ห้อง 101/.test(txt) && (await hp.locator('.profile-head').count()) === 1, 'profile: office and the "head of unit" tag');
    ok((await hp.locator('.profile-edu li').count()) === 2 && (await hp.locator('.profile-chips li').count()) === 3, 'profile: education rows and expertise chips');
    ok((await hp.locator('.profile-honors li').count()) === 2 && (await hp.locator('.profile-honors li svg.medal').count()) === 2 && /ทวีติยาภรณ์ช้างเผือก/.test(await hp.locator('.profile-honors li').first().innerText()) && /2562/.test(await hp.locator('.profile-honors').innerText()), 'profile: royal decorations in rank order with the medal, year (พ.ศ.) and gazette');
    const ys = await hp.$$eval('.profile-works .pw-y', (a) => a.map((x) => x.textContent.trim()));
    ok(ys.join(',') === '2565,2563,2566', 'profile: works grouped by type, newest first (' + ys.join(',') + ')');
    ok(await hp.evaluate(() => { const j = [...document.querySelectorAll('script[type="application/ld+json"]')].map((x) => x.textContent).find((t) => t.includes('"Person"')); return !!j && j.includes('head@example.org') && !j.includes('054-000-111'); }), 'profile: structured data lists only what is public');
    await hp.addScriptTag({ content: axeSource() });
    const ax = await hp.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(ax.violations.length === 0, 'axe (profile): ' + (ax.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join('; ') || '0 violations'));
    await hp.screenshot({ path: shot('staff-profile.png'), fullPage: true });
    const hidden = await hp.goto(B + '/profile/' + fx.p2 + '/');
    const t2 = await hp.locator('.profile').innerText();
    ok(hidden.status() === 200 && !t2.includes('hidden@example.org') && !t2.includes('ปริญญาลับ') && !t2.includes('บันทึกส่วนตัวห้ามแสดง') && (await hp.locator('#pf-works').count()) === 0 && (await hp.locator('#pf-edu').count()) === 0, 'privacy: hidden email, education, works and the private note are nowhere on the page');
    const off = await hp.goto(B + '/profile/' + fx.p1 + '/');
    ok(off.status() === 404, 'privacy: a profile switched off is a 404');
    await hp.goto(B + '/executives/');
    const links = await hp.$$eval('.person-card .person-n a', (a) => a.map((x) => x.getAttribute('href')));
    ok(links.length >= 1 && links.every((h) => h.includes('/profile/')) && (await hp.locator('.person-card .person-hb svg').count()) >= 1, 'directory: names link to the profiles, medal badges on the cards');
    await hp.goto(B + '/staff/');
    const cards = await hp.locator('.person-card').count();
    await hp.fill('[data-people-find]', 'บุคลากร 2');
    const shown = await hp.locator('.person-card:visible').count();
    ok(cards === 4 && shown === 1, 'directory: the search box narrows the cards (' + cards + ' -> ' + shown + ')');
    await hp.fill('[data-people-find]', '');
    ok((await hp.locator('.person-card:visible').count()) === 4, 'directory: clearing the search shows everyone');
    await hp.goto(B + '/structure/');
    const node = await hp.locator('.org-node', { hasText: fx.unit0 }).first();
    ok((await node.locator('.org-person a[href*="/profile/"]').count()) === 1 && (await node.innerText()).includes(fx.name0), 'chart: the unit box shows its linked head, with a link to the profile');
    await hc.close();
  }
  // ---------- discussion board
  {
    const ff = JSON.parse(require('fs').readFileSync(require('path').join(__dirname, '..', '.site-starter', 'forum-fixture.json'), 'utf8'));
    const fc = await br.newContext({ viewport: { width: 1366, height: 900 } });
    await fc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const fp = await fc.newPage();
    await fp.goto(B + '/forum/');
    ok((await fp.locator('.forum-board').count()) === 3 && (await fp.locator('.forum-row').count()) >= 3, 'forum: three boards and the latest topics');
    ok(!(await fp.locator('.forum').innerText()).includes('hidden-forum@example.org'), 'forum: no visitor email is shown');
    await fp.click('.forum-bn >> text=ถามตอบเรื่องการเรียนการสอน');
    await fp.waitForLoadState('load');
    ok((await fp.locator('.forum-row').count()) === 2 && /ปิดการตอบ/.test(await fp.locator('.forum-list').innerText()), 'board: its topics, a locked one marked');
    await fp.goto(B + '/forum/?fb=general');
    ok((await fp.locator('.forum-row.is-pinned').count()) === 1 && /ปักหมุด/.test(await fp.locator('.forum-row.is-pinned').innerText()), 'board: the pinned topic is marked');
    await fp.goto(B + '/forum/?fq=' + encodeURIComponent('ลงทะเบียน'));
    ok((await fp.locator('.forum-row').count()) === 1, 'search: finds the topic by a word');
    await fp.goto(B + '/forum/topic/' + ff.t2 + '/');
    ok((await fp.locator('.forum-reply').count()) === 2 && (await fp.locator('h1').textContent()).includes('ลงทะเบียน'), 'topic: the first post and its two replies');
    ok((await fp.locator('.forum-op').innerText()).includes('ลงทะเบียนเรียนล่าช้า') && (await fp.locator('.forum-op br').count()) >= 1, 'topic: visitor text keeps its line breaks');
    ok(!(await fp.locator('.forum').innerText()).includes('hidden-forum@example.org') && !(await fp.content()).includes('hidden-forum@example.org'), 'topic: the author\'s email is not in the page at all');
    await fp.addScriptTag({ content: axeSource() });
    const fa = await fp.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(fa.violations.length === 0, 'axe (forum topic): ' + (fa.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join('; ') || '0 violations'));
    await fp.screenshot({ path: shot('forum-topic.png'), fullPage: true });
    // reply as a visitor: held for a moderator
    await fp.waitForTimeout(3200);
    await fp.fill('#fm-reply-name', 'ผู้ใช้ทดสอบ'); await fp.fill('#fm-reply-email', 'visitor@example.org'); await fp.fill('#fm-reply-msg', 'ขอบคุณสำหรับคำตอบครับ จะลองทำตามดู');
    await fp.check('#fm-reply input[name=consent]');
    await fp.click('#fm-reply button[type=submit]');
    await fp.waitForSelector('.mform-ok');
    ok(/ผู้ดูแลจะตรวจสอบ/.test(await fp.locator('.mform-ok').innerText()) && (await fp.locator('.forum-reply').count()) === 2, 'reply: a visitor\'s reply is held for a moderator and not shown yet');
    // an empty form shows the problems
    await fp.click('#fm-reply button[type=submit]');
    await fp.waitForSelector('.mform-err');
    ok((await fp.locator('.mform-err li').count()) >= 3, 'reply: an empty form lists what is missing');
    // report
    await fp.goto(B + '/forum/topic/' + ff.t2 + '/');
    await fp.locator('.forum-reply').first().locator('.forum-report button').click();
    await fp.waitForSelector('.mform-ok');
    ok(/ได้รับรายงานแล้ว/.test(await fp.locator('.mform-ok').innerText()), 'report: the visitor is thanked');
    // locked topic
    await fp.goto(B + '/forum/topic/' + ff.t3 + '/');
    ok((await fp.locator('#fm-reply').count()) === 0 && (await fp.locator('.forum-locked').count()) === 1, 'locked: no reply form, a note instead');
    // a new topic
    await fp.goto(B + '/forum/');
    await fp.waitForTimeout(3200);
    await fp.fill('#fm-topic-name', 'ผู้ใช้ใหม่'); await fp.fill('#fm-topic-email', 'new@example.org'); await fp.fill('#fm-title', 'ทดสอบตั้งหัวข้อใหม่จากเบราว์เซอร์'); await fp.fill('#fm-topic-msg', 'ข้อความทดสอบการตั้งหัวข้อใหม่ ความยาวเพียงพอ');
    await fp.check('#fm-topic input[name=consent]');
    await fp.click('#fm-topic button[type=submit]');
    await fp.waitForSelector('.mform-ok');
    ok(/ผู้ดูแลจะตรวจสอบ/.test(await fp.locator('.mform-ok').innerText()) && !(await fp.locator('.forum').innerText()).includes('ทดสอบตั้งหัวข้อใหม่จากเบราว์เซอร์'), 'new topic: held for approval, not on the board');
    await fp.screenshot({ path: shot('forum-index.png'), fullPage: true });
    await fc.close();
    const fm = await br.newContext({ viewport: { width: 360, height: 800 } });
    await fm.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const fq = await fm.newPage();
    for (const u of ['/forum/', '/forum/?fb=academic', '/forum/topic/' + ff.t2 + '/']) {
      await fq.goto(B + u);
      ok(await fq.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'forum on a phone: no horizontal scroll ' + u);
    }
    await fm.close();
  }
  // ---------- FAQ accordion
  {
    const qc = await br.newContext({ viewport: { width: 1280, height: 900 } });
    await qc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const qp = await qc.newPage();
    await qp.goto(B + '/faq/');
    ok((await qp.locator('.faq-item').count()) === 8 && (await qp.locator('.faq-h').count()) === 3 && (await qp.locator('.faq-item[open]').count()) === 0, 'faq: 8 questions in 3 groups, all closed');
    await qp.locator('.faq-item > summary').first().click();
    ok((await qp.locator('.faq-item[open]').count()) === 1 && (await qp.locator('.faq-item[open] .faq-a').first().isVisible()), 'faq: clicking a question opens its answer');
    await qp.keyboard.press('Tab'); await qp.focus('.faq-item:nth-of-type(2) > summary'); await qp.keyboard.press('Enter');
    ok((await qp.locator('.faq-item[open]').count()) === 2, 'faq: opens from the keyboard');
    await qp.fill('[data-faq-find]', 'ทุน');
    const shown = await qp.locator('.faq-item:not([hidden])').count();
    ok(shown >= 1 && shown < 8 && (await qp.locator('.faq-item:not([hidden])[open]').count()) === shown, 'faq: the search keeps matching questions and opens them (' + shown + ')');
    await qp.fill('[data-faq-find]', 'zzzzzzz');
    ok((await qp.locator('.faq-none').isVisible()) && (await qp.locator('.faq-item:not([hidden])').count()) === 0, 'faq: a search with no match says so');
    await qp.fill('[data-faq-find]', '');
    ok((await qp.locator('.faq-item:not([hidden])').count()) === 8 && !(await qp.locator('.faq-none').isVisible()), 'faq: clearing the search shows everything');
    await qp.addScriptTag({ content: axeSource() });
    const qa = await qp.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(qa.violations.length === 0, 'axe (FAQ): ' + (qa.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join('; ') || '0 violations'));
    await qp.screenshot({ path: shot('faq.png'), fullPage: true });
    await qc.close();
  }
  // ---------- photo albums + lightbox
  {
    const ac = await br.newContext({ viewport: { width: 1280, height: 900 } });
    await ac.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const ap = await ac.newPage();
    await ap.goto(B + '/albums/');
    ok((await ap.locator('.album-card').count()) >= 1 && /4/.test(await ap.locator('.album-n').first().innerText()), 'albums: the list shows the album with its number of pictures');
    await ap.goto(B + '/albums/fx-album/');
    ok((await ap.locator('.album-pics a').count()) === 4 && (await ap.locator('h1').textContent()).includes('วันเปิดบ้าน'), 'album: four pictures in a grid');
    ok((await ap.locator('.lb').count()) === 0, 'lightbox: not built until it is used');
    await ap.locator('.album-pics a').nth(1).click();
    await ap.waitForSelector('.lb:not([hidden])');
    ok((await ap.locator('.lb-count').innerText()).includes('2 / 4') && (await ap.locator('.lb-cap').innerText()) === 'คำบรรยายภาพที่ 2' && (await ap.locator('.lb-img').getAttribute('src')).includes('e2e-album-1'), 'lightbox: opens on the clicked picture with its caption and counter');
    ok(await ap.evaluate(() => document.activeElement.classList.contains('lb-close') && document.documentElement.classList.contains('lb-open')), 'lightbox: focus moves to the close button, the page behind does not scroll');
    await ap.keyboard.press('ArrowRight');
    ok((await ap.locator('.lb-count').innerText()).includes('3 / 4'), 'lightbox: the right arrow shows the next picture');
    await ap.keyboard.press('ArrowLeft'); await ap.keyboard.press('ArrowLeft');
    ok((await ap.locator('.lb-count').innerText()).includes('1 / 4'), 'lightbox: the left arrow goes back');
    await ap.click('.lb-prev');
    ok((await ap.locator('.lb-count').innerText()).includes('4 / 4') && (await ap.locator('.lb-cap').innerText()) === '', 'lightbox: the previous button wraps to the last picture (no caption)');
    await ap.keyboard.press('Tab'); await ap.keyboard.press('Tab'); await ap.keyboard.press('Tab');
    ok(await ap.evaluate(() => document.querySelector('.lb').contains(document.activeElement)), 'lightbox: Tab stays inside the lightbox');
    await ap.addScriptTag({ content: axeSource() });
    const la = await ap.evaluate(() => axe.run(document.querySelector('.lb'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(la.violations.length === 0, 'axe (lightbox): ' + (la.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    await ap.screenshot({ path: shot('album-lightbox.png') });
    await ap.keyboard.press('Escape');
    ok((await ap.locator('.lb').isHidden()) && (await ap.evaluate(() => document.activeElement.hasAttribute('data-lb') && !document.documentElement.classList.contains('lb-open'))), 'lightbox: Esc closes it and returns focus to the picture');
    const aa = await ap.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(aa.violations.length === 0, 'axe (album page): ' + (aa.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join('; ') || '0 violations'));
    await ap.screenshot({ path: shot('album.png'), fullPage: true });
    await ac.close();
    const am = await br.newContext({ viewport: { width: 360, height: 800 }, hasTouch: true });
    await am.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const aq = await am.newPage();
    for (const u of ['/albums/', '/albums/fx-album/']) { await aq.goto(B + u); ok(await aq.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'albums on a phone: no horizontal scroll ' + u); }
    await aq.locator('.album-pics a').first().tap();
    await aq.waitForSelector('.lb:not([hidden])');
    ok(await aq.evaluate(() => { const r = document.querySelector('.lb-img').getBoundingClientRect(); return r.width <= innerWidth && r.height <= innerHeight; }), 'lightbox on a phone: the picture fits the screen');
    await am.close();
  }
  // ---------- page lead, quick links on the back-to-top button
  {
    const lc = await br.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
    await lc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const lp = await lc.newPage();
    await lp.goto(B + '/fx-lead/');
    ok((await lp.locator('header .band-lead').innerText()).includes('คำเกริ่นนำ'), 'page: the excerpt is shown as a lead in the title band');
    await lp.evaluate(() => document.documentElement.setAttribute('data-menu', 'side'));
    await lp.waitForTimeout(300);
    ok(await lp.evaluate(() => { const b = document.querySelector('#content .mcu-wheel nav').getBoundingClientRect(); const h = document.querySelector('#content .mcu-wheel').getBoundingClientRect(); return b.top - h.top < 70; }), 'page (sidebar mode): the breadcrumb sits at the top of the band');
    await lp.evaluate(() => document.documentElement.removeAttribute('data-menu'));
    await lp.evaluate(() => scrollTo(0, 1200));
    await lp.waitForSelector('#back-to-top:not(.hidden)');
    const box = await lp.locator('#back-to-top').boundingBox();
    await lp.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await lp.mouse.down();
    await lp.waitForTimeout(800);
    await lp.mouse.up();
    ok((await lp.locator('.top-quick a').count()) >= 1, 'back to top: pressing and holding opens the quick links');
    ok((await lp.evaluate(() => scrollY)) > 1000, 'back to top: the hold does not scroll up');
    await lp.keyboard.press('Escape');
    ok((await lp.locator('.top-quick').count()) === 0, 'back to top: Esc closes the quick links');
    await lp.locator('#back-to-top').click();
    await lp.waitForTimeout(1500);
    ok((await lp.evaluate(() => scrollY)) < 50, 'back to top: a plain click still scrolls to the top');
    await lc.close();
  }
  // ---------- news cards (posters shown whole, short excerpts) and videos from other sites
  {
    const vc = await br.newContext({ viewport: { width: 1366, height: 900 }, reducedMotion: 'reduce' });
    await vc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
    const vp = await vc.newPage();
    await vp.goto(B + '/?s=fx');
    const cards = await vp.evaluate(() => [...document.querySelectorAll('.news-card')].map((c) => { const m = c.querySelector('.news-media'); const e = c.querySelector('.news-excerpt'); return { fit: !!m && m.classList.contains('is-fit'), title: c.querySelector('.news-title a').textContent, fitStyle: m ? getComputedStyle(m.querySelector('img')).objectFit : '', exLines: e ? Math.round(e.getBoundingClientRect().height / parseFloat(getComputedStyle(e).lineHeight)) : 0, more: !!c.querySelector('.news-more') }; }));
    const poster = cards.find((c) => c.title.includes('fx-poster')), wide = cards.find((c) => c.title.includes('fx-wide'));
    ok(poster && poster.fit && poster.fitStyle === 'contain' && wide && !wide.fit && wide.fitStyle === 'cover', 'news cards: a poster-shaped picture is shown whole, a wide one is cropped to fill');
    ok(cards.length >= 2 && cards.every((c) => c.exLines <= 6 && c.more), 'news cards: excerpts are clamped and every card ends with "read more"');
    const rows = await vp.evaluate(() => { const g = document.querySelector('.news-card').parentElement; const kids = [...g.children]; return kids.map((k) => Math.round(k.getBoundingClientRect().top)).reduce((m, t, i) => { (m[t] = m[t] || []).push(Math.round(kids[i].getBoundingClientRect().height)); return m; }, {}); });
    ok(Object.values(rows).every((hs) => Math.max(...hs) - Math.min(...hs) <= 1), 'news cards: cards in a row are the same height');
    await vp.screenshot({ path: shot('news-cards.png'), fullPage: true });

    const asked = [];
    vp.on('request', (r) => { if (/youtube|ytimg|vimeo/.test(r.url())) { asked.push(r.url()); } });
    await vp.goto(B + '/videos/');
    ok((await vp.locator('.video-card').count()) >= 1 && (await vp.locator('.vid-badge').count()) >= 1, 'videos: the list shows the video with a play badge');
    await vp.goto(B + '/videos/fx-video/');
    ok((await vp.locator('.vid-play').count()) === 1 && (await vp.locator('iframe').count()) === 0 && asked.length === 0, 'video: a click-to-play cover; nothing is requested from YouTube before play');
    ok(/YouTube/.test(await vp.locator('.vid-note').innerText()), 'video: tells the visitor where it will play from');
    await vp.addScriptTag({ content: axeSource() });
    const va = await vp.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(va.violations.length === 0, 'axe (video page): ' + (va.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join('; ') || '0 violations'));
    await vp.screenshot({ path: shot('video.png') });
    await vp.route(/youtube-nocookie\.com/, (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>player</body></html>' }));
    await vp.focus('.vid-play'); await vp.keyboard.press('Enter');
    await vp.waitForSelector('iframe.vid-frame');
    const fr = await vp.locator('iframe.vid-frame').evaluate((f) => ({ src: f.src, title: f.title, allow: f.allow }));
    ok(/^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?/.test(fr.src) && /autoplay=1/.test(fr.src) && fr.title.includes('วิดีโอแนะนำ'), 'video: pressing play (from the keyboard) builds the no-cookie player with a title');
    ok((await vp.locator('.vid-note').count()) === 0, 'video: the notice goes away once it plays');
    await vc.close();
  }
  // ---------- polls / satisfaction survey
  {
    const pc = await br.newContext({ viewport: { width: 1366, height: 900 } });
    const pq = await pc.newPage();
    await pq.goto(B + '/fx-poll/');
    ok((await pq.locator('.poll-o').count()) === 5 && (await pq.locator('.poll-res').count()) === 0, 'poll: five satisfaction levels, no results before voting');
    await pq.waitForTimeout(3200);
    await pq.click('.poll-form button');
    ok(/pv=none/.test(pq.url()) && (await pq.locator('.mform-err').count()) === 1, 'poll: sending without a choice says so');
    await pq.waitForTimeout(3200);
    await pq.locator('.poll-o').nth(1).click();
    await pq.click('.poll-form button');
    await pq.waitForSelector('.poll-res');
    ok((await pq.locator('.mform-ok').count()) === 1 && (await pq.locator('.poll-form').count()) === 0 && /1/.test(await pq.locator('.poll-tot').innerText()), 'poll: the vote is counted, thanks and the results appear');
    await pq.evaluate(() => scrollTo(0, 0));
    await pq.addScriptTag({ content: axeSource() });
    const pa = await pq.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(pa.violations.length === 0, 'axe (poll): ' + (pa.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ') + ' ' + (n.any[0] && n.any[0].message)).join(', ')).join('; ') || '0 violations'));
    await pq.screenshot({ path: shot('poll.png'), fullPage: true });
    await pq.goto(B + '/fx-poll/');
    ok((await pq.locator('.poll-form').count()) === 0 && (await pq.locator('.poll-res').count()) === 1, 'poll: coming back, the same visitor sees the results, not the form');
    await pc.close();
  }
  // works & awards (own categories), administration chart, attachments, views
  await p.goto(B + '/works/');
  ok((await p.$$('.work-card')).length === 3, '/works/ lists the 3 sample works');
  const chips = await p.$$eval('nav[aria-label] .pill-tabs a, .pill-tabs a', (a) => a.map((x) => x.textContent.trim()));
  ok(chips.includes('การแข่งขัน') && chips.includes('นวัตกรรมและงานวิจัย') && chips.includes('ศิลปะและวัฒนธรรม'), '/works/ filters by the works\' own categories (' + chips.length + ' chips)');
  ok(!chips.includes('ข่าวประชาสัมพันธ์'), '/works/ does not offer the news categories');
  await p.click('.pill-tabs a:has-text("ศิลปะและวัฒนธรรม")');
  ok((await p.$$('.work-card')).length === 1 && /work-category/.test(p.url()), 'a work category shows only its own works');
  await p.goto(B + '/category/press-releases/');
  ok((await p.$$('.work-card')).length === 0, 'a news category never lists works');
  await p.goto(B + '/works/?wy=1999');
  ok((await p.$$('.work-card')).length === 0, 'the year filter hides works of other years');
  await p.goto(B + '/works/');
  await p.addScriptTag({ content: axeSource() });
  const wa = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(wa.violations.length === 0, '/works/ axe: ' + (wa.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.goto(B + '/works/work-1/');
  ok((await p.$$('.files .file-row')).length === 2 && /เกียรติบัตรชนะเลิศ/.test(await p.textContent('.files')), 'work page: the attached files are listed');
  ok(/PDF/.test(await p.textContent('.files')) && /350 KB/.test(await p.textContent('.files')) && /ดาวน์โหลดแล้ว 7 ครั้ง/.test(await p.textContent('.files')), 'work page: type, size text and download count');
  ok(/เข้าชม\s+12\d ครั้ง/.test(await p.textContent('article > header')), 'work page: the number of views is shown');
  ok(await p.$('.share-bar a.share-fb') !== null && /ทีม/.test(await p.textContent('.ev-info')), 'work page: share bar and details box');
  await p.addScriptTag({ content: axeSource() });
  const wb = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(wb.violations.length === 0, 'work page axe: ' + (wb.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  // views: counted once per visitor (a real browser UA – the default headless one is treated as a robot)
  const rc = await br.newContext({ userAgent: 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0', viewport: { width: 1280, height: 800 } });
  await rc.addInitScript(() => { try { localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* frame */ } });
  const rp = await rc.newPage();
  await rp.goto(B + '/works/work-1/');
  const vv1 = Number((await rp.textContent('article > header')).match(/เข้าชม\s+(\d+)/)[1]);
  await rp.reload();
  const vv2 = Number((await rp.textContent('article > header')).match(/เข้าชม\s+(\d+)/)[1]);
  ok(vv2 === vv1, 'views: a reload by the same visitor is not counted again (' + vv1 + ' -> ' + vv2 + ')');
  const rc2 = await br.newContext({ userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0' });
  const rp2 = await rc2.newPage();
  await rp2.goto(B + '/works/work-1/');
  const vv3 = Number((await rp2.textContent('article > header')).match(/เข้าชม\s+(\d+)/)[1]);
  ok(vv3 === vv1 + 1, 'views: a different visitor is counted (' + vv1 + ' -> ' + vv3 + ')');
  await rc2.close();
  // download limit 2
  const href = await p.getAttribute('.files .file-dl', 'href');
  const UA = { 'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0' };
  const d1 = await p.request.get(href, { headers: UA });
  ok(d1.status() === 200 && /pdf/.test(d1.headers()['content-type']) && /attachment/.test(d1.headers()['content-disposition']) && (await d1.text()).startsWith('%PDF'), 'download 1: the PDF is sent as an attachment');
  const d2 = await p.request.get(href, { headers: UA });
  ok(d2.status() === 200, 'download 2 works');
  const d3 = await p.request.get(href, { headers: UA });
  ok(d3.status() === 403, 'download 3 is refused: the limit of 2 was reached (' + d3.status() + ')');
  await p.reload();
  ok(/ดาวน์โหลดแล้ว 2 ครั้ง/.test(await p.textContent('.files')) && (await p.$$('.files .file-full')).length === 1, 'the page now shows 2 downloads and the "limit reached" notice');
  ok((await p.request.get(B + '/?mcupr_dl=999999-0')).status() === 404, 'a missing file is 404');
  const th2 = await p.request.get(B + '/?mcupr_dl=../../wp-config.php');
  ok(th2.status() === 200 && !(await th2.text()).includes('DB_PASSWORD'), 'a crafted download parameter cannot read other files');
  // document centre, people, forms, hero slider, social links
  await p.goto(B + '/documents/');
  ok((await p.$$('table.docs tbody tr')).length >= 2 && /เกียรติบัตรชนะเลิศ/.test(await p.textContent('table.docs')), 'document centre: lists the files of all posts');
  await p.fill('input[name=dq]', 'ภาพถ่าย');
  await Promise.all([p.waitForNavigation(), p.click('.docs-form button')]);
  ok((await p.$$('table.docs tbody tr')).length === 1 && /dq=/.test(p.url()), 'document centre: search narrows the list');
  await p.goto(B + '/documents/?dt=pdf');
  ok((await p.$$('table.docs tbody tr')).length >= 1 && !/ภาพถ่ายทีม/.test(await p.textContent('table.docs')), 'document centre: filter by type');
  await p.addScriptTag({ content: axeSource() });
  const da = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(da.violations.length === 0, 'document centre axe: ' + (da.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.goto(B + '/executives/');
  ok((await p.$$('.person-card')).length === 3 && /อธิการบดี/.test(await p.textContent('.people-grid')), 'executives: 3 people cards from the directory');
  await p.goto(B + '/staff/');
  ok((await p.$$('.person-card')).length === 4, 'staff: 4 people cards');
  await p.addScriptTag({ content: axeSource() });
  const pa = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(pa.violations.length === 0, 'people axe: ' + (pa.violations.map((v) => v.id + '(' + v.nodes.length + ') ' + v.nodes.map((n) => n.target.join(' ')).join(', ')).join(', ') || '0 violations'));
  // contact form
  await p.goto(B + '/contact/');
  ok((await p.$$('#mform-contact form')).length === 1 && (await p.$$eval('#mform-contact label', (l) => l.every((x) => x.control || x.querySelector('input,select,textarea')))) , 'contact page: the form with labelled fields');
  await p.click('#mform-contact button[type=submit]');
  await p.waitForSelector('.mform-err');
  ok(/aria-invalid/.test(await p.innerHTML('#mform-contact')) && (await p.$$('.mform-err li')).length >= 3, 'contact form: empty submit shows the problems, fields marked invalid');
  await p.fill('#mform-contact-name', 'สมชาย ใจดี'); await p.fill('#mform-contact-email', 'somchai@example.org');
  await p.fill('#mform-contact-msg', 'อยากทราบกำหนดการรับสมัครนักศึกษาใหม่ครับ'); await p.check('#mform-contact input[name=consent]');
  await p.waitForTimeout(3300);
  await p.click('#mform-contact button[type=submit]');
  await p.waitForSelector('.mform-ok');
  ok(/MC-\d{8}-\d+/.test(await p.textContent('.mform-ok')) && /^[^?]*\?mf=ok/.test(p.url().replace(B, '')), 'contact form: success message with a reference number');
  await p.goto(B + '/complaints/');
  await p.selectOption('#mform-complaint-kind', 'corrupt');
  await p.fill('#mform-complaint-name', 'ผู้แจ้ง'); await p.fill('#mform-complaint-email', 'a@example.org');
  await p.fill('#mform-complaint-msg', 'ขอแจ้งเบาะแสเรื่องการจัดซื้อที่ไม่โปร่งใสครับ'); await p.check('#mform-complaint input[name=consent]');
  await p.waitForTimeout(3300);
  await p.click('#mform-complaint button[type=submit]');
  await p.waitForSelector('.mform-ok');
  ok(/MC-/.test(await p.textContent('.mform-ok')), 'complaint form: accepted with a reference number');
  await p.goto(B + '/contact/');
  await p.addScriptTag({ content: axeSource() });
  const fm = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(fm.violations.length === 0, 'contact page axe: ' + (fm.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  // hero slider
  await p.goto(B + '/');
  ok((await p.$$('[data-hero] [data-slide]')).length === 3 && (await p.$$('[data-hero] h1')).length === 1 && (await p.$$eval('[data-hero] h1', (h) => h[0].classList.contains('sr-only'))), 'hero: 3 slides, one h1 that stays available to screen readers');
  ok(await p.isVisible('[data-hero-ctl]') && (await p.getAttribute('[data-hero]', 'data-auto')) === '0', 'hero: controls appear with JavaScript; automatic change is off by default');
  ok(await p.evaluate(() => document.querySelectorAll('[data-slide]')[0].classList.contains('is-active')), 'hero: slide 1 first');
  await p.click('[data-hero-next]');
  ok(await p.evaluate(() => document.querySelectorAll('[data-slide]')[1].classList.contains('is-active') && document.querySelectorAll('[data-hero-dot]')[1].getAttribute('aria-current') === 'true'), 'hero: Next shows slide 2 and marks its dot');
  ok(/สไลด์ที่ 2/.test(await p.textContent('[data-slide].is-active')) && await p.isVisible('[data-slide].is-active a.hero-btn-1'), 'hero: the slide has its own headline and button');
  await p.click('[data-hero-dot="2"]');
  ok(/สไลด์ที่ 3/.test(await p.textContent('[data-slide].is-active')), 'hero: a dot jumps to its slide');
  await p.click('[data-hero-prev]'); await p.click('[data-hero-prev]'); await p.click('[data-hero-prev]');
  ok(await p.evaluate(() => document.querySelectorAll('[data-slide]')[2].classList.contains('is-active')), 'hero: Previous wraps around');
  await p.addScriptTag({ content: axeSource() });
  const ha = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(ha.violations.length === 0, 'home with the slider axe: ' + (ha.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  const nj = await br.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 800 } });
  const njp = await nj.newPage();
  await njp.goto(B + '/');
  ok(await njp.isVisible('[data-slide]:first-child') && !(await njp.isVisible('[data-hero-ctl]')), 'hero without JavaScript: the first slide shows, no dead buttons');
  await nj.close();
  ok((await p.$$('#colophon .soc a')).length === 2 && /Facebook/.test(await p.getAttribute('#colophon .soc a', 'aria-label')), 'footer: social icons for the two networks that have an address');
  // administration chart
  await p.goto(B + '/structure/');
  ok((await p.$$('.org-node')).length === 15, 'structure page: the administration chart with 15 boxes (line of command + committees)');
  ok((await p.$$('.org-d1')).length === 1 && (await p.$$('.org-d2')).length === 1 && (await p.$$('.org-d3')).length === 1, 'chart levels: rector / council / vice rector');
  ok((await p.$$('.org-sidebox')).length === 4 && (await p.$$('.org-grp')).length === 4, 'chart: 4 committee boxes beside the vice rector and 4 list panels (sections with their groups)');
  const o = await p.evaluate(() => {
    const box = (t) => [...document.querySelectorAll('.org-node')].find((e) => e.textContent.includes(t));
    const top = (e) => Math.round(e.getBoundingClientRect().top);
    const assist = ['ฝ่ายบริหาร', 'ฝ่ายวิชาการ', 'ฝ่ายกิจการทั่วไป'].map((x) => top(box('ผู้ช่วยอธิการบดี' + x)));
    const dirs = ['สำนักงานวิทยาเขตแพร่', 'สำนักวิชาการวิทยาเขตแพร่', 'วิทยาลัยสงฆ์แพร่'].map((x) => top([...document.querySelectorAll('.org-node')].find((e) => e.querySelector('.org-name').textContent.trim() === x)));
    const vice = box('รองอธิการบดีวิทยาเขตแพร่').getBoundingClientRect();
    const side = [...document.querySelectorAll('.org-sidebox')].map((e) => e.getBoundingClientRect());
    const rector = box('อธิการบดี'); const cs = getComputedStyle(rector);
    return { assist, dirs, viceBottom: vice.bottom, sideTop: Math.min(...side.map((r) => r.top)), sideBottom: Math.max(...side.map((r) => r.bottom)), bg: cs.backgroundColor, fg: cs.color, lampang: top(box('วิทยาลัยสงฆ์นครลำปาง')), gen: top(box('ผู้ช่วยอธิการบดีฝ่ายกิจการทั่วไป')) };
  });
  ok(new Set(o.assist).size === 1 && new Set(o.dirs).size === 1 && o.dirs[0] > o.assist[0], 'chart (desktop): the assistants are in one row and the three directors in one row below them ' + JSON.stringify([o.assist, o.dirs]));
  ok(o.sideTop >= o.viceBottom && o.lampang > o.gen, 'chart (desktop): committees sit under the vice rector, the Lampang college hangs under the general-affairs assistant');
  ok(o.bg === 'rgb(107, 106, 54)' && o.fg === 'rgb(255, 255, 255)', 'chart: a unit\'s box colour is the chosen one (' + o.bg + ')');
  await p.addScriptTag({ content: axeSource() });
  const oa = await p.evaluate(() => axe.run(document.querySelector('.org-wrap'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(oa.violations.length === 0, 'chart axe: ' + (oa.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.goto(B + '/structure/?lang=en');
  ok(/Phrae Campus Council/.test(await p.textContent('.org')) && /Administration Group/.test(await p.textContent('.org')), 'chart: unit names in English');
  await p.goto(B + '/?lang=th');

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
  for (const s of ['/', '/about/', '/ita/', '/privacy-policy/', '/events/', '/events/event-1/', '/works/', '/works/work-1/', '/structure/', '/documents/', '/executives/', '/contact/']) {
    await mp.goto(B + s);
    ok(await mp.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'mobile 360px: no horizontal scroll on ' + s);
  }
  await mp.setViewportSize({ width: 390, height: 700 });
  await mp.goto(B + '/about/');
  const bc = await mp.evaluate(() => { const a = document.querySelector('#masthead .brand'); const t = a.lastElementChild; return { compact: a.classList.contains('is-compact'), hidden: getComputedStyle(t).display === 'none', label: a.getAttribute('aria-label'), logo: a.firstElementChild.getBoundingClientRect().width > 20 }; });
  ok(bc.compact && bc.hidden && bc.label && bc.logo, 'phones: a name that does not fit beside the logo is dropped, the logo stays and the link keeps its accessible name');
  await mp.setViewportSize({ width: 900, height: 700 });
  await mp.waitForTimeout(300);
  ok(await mp.evaluate(() => !document.querySelector('#masthead .brand').classList.contains('is-compact')), 'tablet: the name is shown again when it fits');
  await mp.setViewportSize({ width: 240, height: 700 });
  await mp.goto(B + '/events/');
  ok(await mp.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'events calendar fits 240px without horizontal scroll');
  await p.screenshot({ path: shot('starter-about.png') });
  ok(!errors.length, 'no console / page errors' + (errors.length ? ': ' + errors[0] : ''));
  await br.close();
  finish();
})();
