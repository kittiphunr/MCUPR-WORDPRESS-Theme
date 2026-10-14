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

  // layout v2: side / hybrid = ONE sidebar card as tall as its menu + a rounded content frame; no black bar (its links join the header)
  for (const [mode, min] of [['side', '0'], ['side', '1'], ['hybrid', '1']]) {
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
      return { navTop: nr.top, navBottom: nr.bottom, navLeft: nr.left, navRight: nr.right, lastBottom: last.bottom, frameLeft: pr.left, frameTop: pr.top, frameRight: pr.right, vw: innerWidth, vh: innerHeight, radius: parseFloat(getComputedStyle(page).borderTopLeftRadius), barShown: !!bar && getComputedStyle(bar).display !== 'none', utilShown: !!util && util.offsetParent !== null, utilLinks: util ? util.querySelectorAll('a').length : 0, cur: cr && { w: cr.width, h: cr.height, l: cr.left, r: cr.right, br: parseFloat(getComputedStyle(cur).borderTopLeftRadius) }, hdrRight: hdr.right, overflow: document.documentElement.scrollWidth > innerWidth + 1, brand: !!document.querySelector('.sb-brand') && document.querySelector('.sb-brand').offsetParent !== null };
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
      return { brand: r('#masthead .brand'), nav: r('.site-nav'), tools: r('#masthead .wrap > div'), brandBg: bg('#masthead .brand'), navBg: bg('.site-nav'), toolsBg: bg('#masthead .wrap > div'), hdrBg: bg('#masthead'), bar: getComputedStyle(document.querySelector('.bg-bar')).display, util: document.querySelector('.hdr-util') && document.querySelector('.hdr-util').offsetParent !== null };
    });
    ok(t.nav.left - t.brand.right >= 8 && t.tools.left - t.nav.right >= 8, 'top menu: brand, menu and tools are three separate floating capsules (gaps ' + Math.round(t.nav.left - t.brand.right) + ' / ' + Math.round(t.tools.left - t.nav.right) + 'px)');
    ok(t.brandBg !== 'rgba(0, 0, 0, 0)' && t.navBg !== 'rgba(0, 0, 0, 0)' && t.toolsBg !== 'rgba(0, 0, 0, 0)' && t.hdrBg === 'rgba(0, 0, 0, 0)', 'top menu: each capsule has its own background; the bar behind them is transparent');
    ok(t.bar !== 'none' && !t.util, 'top menu keeps the black utility bar (and not a second copy in the header)');
    await tc.close();
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
  // administration chart
  await p.goto(B + '/structure/');
  ok((await p.$$('.org-node')).length === 13, 'structure page: the administration chart with 13 boxes');
  ok((await p.$$('.org-d1')).length === 1 && (await p.$$('.org-d2')).length === 1 && (await p.$$('.org-d3')).length === 4 && (await p.$$('.org-d4')).length >= 5, 'chart levels: 1 / 1 / 4 / 5+ boxes');
  const o = await p.evaluate(() => { const r = (s) => document.querySelector(s).getBoundingClientRect(); const kids = [...document.querySelectorAll('.org-d3')].map((e) => e.getBoundingClientRect()); return { rowY: kids.map((k) => Math.round(k.top)), parent: r('.org-d2 > .org-node') }; });
  ok(o.rowY.length === 4 && new Set(o.rowY).size === 1 && o.rowY[0] > o.parent.bottom, 'chart (desktop): the executives are in one row below the president');
  await p.addScriptTag({ content: axeSource() });
  const oa = await p.evaluate(() => axe.run(document.querySelector('.org-wrap'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
  ok(oa.violations.length === 0, 'chart axe: ' + (oa.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
  await p.goto(B + '/structure/?lang=en');
  ok(/Office of the President/.test(await p.textContent('.org')), 'chart: unit names in English');
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
  for (const s of ['/', '/about/', '/ita/', '/privacy-policy/', '/events/', '/events/event-1/', '/works/', '/works/work-1/', '/structure/']) {
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
