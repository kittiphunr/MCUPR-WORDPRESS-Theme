// Functional + accessibility: cookie dialog, languages, header dialogs (accessibility helper,
// language, search), text size, dark mode, contrast modes, menus/drawer, axe-core on 4 pages x light/dark.
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

const consent = () => localStorage.setItem('mcupr_consent', '1');
const post1 = '/' + encodeURIComponent('ข่าวทดสอบ-1') + '/';
const post2 = '/' + encodeURIComponent('ข่าวทดสอบ-2') + '/';

(async () => {
  const br = await launch();
  // Reduced motion keeps scroll-reveal / animations from hiding content during checks (effects.js covers motion).
  const newCtx = async (opts = {}) => {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce', ...opts });
    await c.addInitScript(consent);
    return c;
  };

  // ---- cookie / PDPA notice (fresh visitor, no stored consent): plain HTML, no SweetAlert2 download
  {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    const p = await c.newPage();
    const loaded = [];
    p.on('request', (r) => loaded.push(r.url()));
    await p.goto(B + '/');
    await p.waitForSelector('#cookie-notice', { state: 'visible' });
    ok((await p.textContent('#cookie-title')).includes('คุกกี้') && (await p.locator('#cookie-notice [data-cc-accept], #cookie-notice [data-cc-reject], #cookie-notice [data-cc-open]').count()) === 3, 'cookie notice shown in Thai, with three equal choices: accept all, necessary only, settings');
    ok(await p.evaluate(() => document.documentElement.classList.contains('needs-consent')), 'shown by CSS from <html class="needs-consent"> (set before first paint)');
    ok((await p.getAttribute('#cookie-notice', 'role')) === 'region' && !!(await p.getAttribute('#cookie-notice', 'aria-labelledby')), 'notice is a labelled region');
    ok(!(await p.evaluate(() => document.activeElement.closest && document.activeElement.closest('#cookie-notice'))), 'the notice does not steal focus');
    ok(!loaded.some((u) => /sweetalert2/.test(u)) && !(await p.evaluate(() => !!window.Swal)), 'SweetAlert2 is NOT downloaded on first load (speed)');
    await p.keyboard.press('Escape');
    ok(await p.isVisible('#cookie-notice'), 'Esc does not dismiss the notice (consent needs a click)');
    const r = await p.evaluate(() => document.querySelector('#cookie-notice').getBoundingClientRect());
    ok(r.bottom <= 900 && r.right <= 1280 && r.width <= 400, 'notice is a compact card inside the screen');
    await p.click('#cookie-notice [data-cc-accept]');
    ok(!(await p.isVisible('#cookie-notice')) && (await p.evaluate(() => /mcupr_cc=/.test(document.cookie))), 'accept hides it and remembers the choice (cookie mcupr_cc)');
    await p.reload();
    await p.waitForTimeout(300);
    ok(!(await p.isVisible('#cookie-notice')), 'not shown again after accepting');
    await p.click('#a11y-toggle');
    await p.waitForSelector('.swal2-popup');
    ok(loaded.some((u) => /sweetalert2/.test(u)) && (await p.locator('.swal2-popup').count()) === 1, 'SweetAlert2 loads on first use (opening a dialog) and the dialog opens');
    await c.close();
  }
  {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
    const p = await c.newPage();
    await p.goto(B + '/?lang=ja');
    await p.waitForSelector('#cookie-notice', { state: 'visible' });
    ok((await p.textContent('#cookie-title')).includes('Cookie'), 'cookie notice in Japanese');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with the notice showing: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    await c.close();
  }

  const ctx = await newCtx();
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', (e) => errs.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errs.push(m.text() + ' ' + (m.location().url || '')); });

  const openDialog = async (btn) => { await page.click(btn); await page.waitForSelector('.swal2-popup'); };
  const closeDialog = async () => { await page.keyboard.press('Escape'); await page.waitForSelector('.swal2-popup', { state: 'detached' }); };
  const fontSize = () => page.evaluate(() => getComputedStyle(document.documentElement).fontSize);
  const bodyBg = () => page.evaluate(() => getComputedStyle(document.body).backgroundColor);

  // ---- languages (language dialog)
  await page.goto(B + '/');
  ok((await page.getAttribute('html', 'lang')) === 'th', 'html lang=th by default');
  ok((await page.textContent('#masthead a[rel=home]')).includes('มหาวิทยาลัยทดสอบ'), 'organisation name in Thai');
  ok((await page.textContent('#lang-toggle')).trim() === 'TH', 'language badge shows TH');
  await openDialog('#lang-toggle');
  ok((await page.locator('.swal2-html-container a').count()) === 4, 'language dialog lists 4 languages');
  ok(await page.evaluate(() => document.activeElement.getAttribute('aria-current') === 'true'), 'focus starts on the current language');
  await page.click('.swal2-html-container a[hreflang="en"]');
  await page.waitForLoadState();
  ok((await page.getAttribute('html', 'lang')) === 'en', 'switched to English');
  ok((await page.textContent('#masthead a[rel=home]')).includes('Test University'), 'organisation name in English');
  await page.goto(B + '/');
  ok((await page.getAttribute('html', 'lang')) === 'en', 'language persisted via cookie');
  await page.goto(B + '/?lang=zh');
  await page.goto(B + post1);
  ok((await page.textContent('h1')).includes('测试新闻 1'), 'Chinese translated title');
  ok((await page.content()).includes('新闻1的中文内容'), 'Chinese translated content');
  await page.goto(B + post2 + '?lang=ja');
  ok(await page.isVisible('[role=note]'), 'missing-translation notice (ja)');
  ok((await page.locator('#article-body[lang="th"]').count()) === 1, 'untranslated content marked lang=th');
  await page.goto(B + '/?lang=th');

  // ---- accessibility helper: text size
  ok((await fontSize()) === '16px', 'default text size 16px');
  await openDialog('#a11y-toggle');
  ok((await page.textContent('.swal2-title')).includes('ตั้งค่าการแสดงผล'), 'settings dialog title');
  ok(await page.evaluate(() => document.activeElement.getAttribute('aria-checked') === 'true'), 'focus on the selected text size');
  const level = (n) => page.click(`[data-font-level="${n}"]`);
  await level(2);
  ok((await fontSize()) === '20px', 'level 125% => 20px');
  ok((await page.getAttribute('[data-font-level="2"]', 'aria-checked')) === 'true', 'selected level marked aria-checked');
  await page.waitForFunction(() => document.querySelector('[data-a11y-status]').textContent.includes('125%'), null, { timeout: 3000 }).catch(() => {});
  ok((await page.textContent('[data-a11y-status]')).includes('125%'), 'status announced inside the dialog');
  await level(4);
  ok((await fontSize()) === '32px', 'max 200% => 32px');
  await level(0);
  ok((await fontSize()) === '14px', 'smallest 87.5% => 14px');
  await level(4);
  await closeDialog();
  await page.reload();
  ok((await fontSize()) === '32px', 'text size persisted');
  await page.screenshot({ path: shot('func-200pct.png') });
  await openDialog('#a11y-toggle');
  await level(1);
  ok((await fontSize()) === '16px', 'back to default 100%');

  // ---- dark mode / display mode
  const light = await bodyBg();
  await page.check('input[data-pref=theme][value=dark]', { force: true });
  const dark = await bodyBg();
  ok(light !== dark && dark === 'rgb(15, 23, 42)', `dark mode (${light} -> ${dark})`);
  await closeDialog();
  await page.reload();
  ok((await bodyBg()) === dark, 'dark mode persisted without a flash on reload');
  await page.screenshot({ path: shot('func-dark.png') });
  await openDialog('#a11y-toggle');
  await page.check('input[data-pref=theme][value=light]', { force: true });

  // ---- high contrast switch + variants + extras
  ok(await page.locator('[data-contrast-variants]').isHidden(), 'contrast variants hidden while off');
  await page.click('[data-contrast-switch]');
  ok((await page.getAttribute('[data-contrast-switch]', 'aria-checked')) === 'true', 'high-contrast switch on');
  ok(await page.locator('[data-contrast-variants]').isVisible(), 'contrast variants shown');
  ok((await bodyBg()) === 'rgb(0, 0, 0)', 'white-on-black applied');
  await page.check('input[data-pref=contrast][value=yb]', { force: true });
  ok((await bodyBg()) === 'rgb(0, 0, 0)' && (await page.evaluate(() => getComputedStyle(document.body).color)) === 'rgb(255, 234, 0)', 'yellow-on-black applied');
  await page.screenshot({ path: shot('func-contrast.png') });
  await page.click('summary');
  await page.check('input[data-pref=gray]', { force: true });
  ok((await page.evaluate(() => getComputedStyle(document.documentElement).filter)) === 'grayscale(1)', 'grayscale');
  await page.check('input[data-pref=spacing]', { force: true });
  ok(await page.evaluate(() => getComputedStyle(document.body).letterSpacing !== 'normal'), 'text spacing');
  await closeDialog();
  await page.waitForFunction(() => document.activeElement && document.activeElement.id === 'a11y-toggle', null, { timeout: 3000 }).catch(() => {});
  ok((await page.evaluate(() => document.activeElement.id)) === 'a11y-toggle', 'focus returns to the Aa button');
  await page.reload();
  ok((await page.evaluate(() => document.documentElement.dataset.contrast)) === 'yb', 'contrast persisted');
  await openDialog('#a11y-toggle');
  await page.click('[data-reset-prefs]');
  ok(await page.evaluate(() => !document.documentElement.dataset.contrast && !document.documentElement.dataset.gray && !document.documentElement.dataset.theme), 'reset all');
  await page.waitForFunction(() => document.querySelector('[data-a11y-status]').textContent.length > 0, null, { timeout: 3000 }).catch(() => {});
  ok((await page.textContent('[data-a11y-status]')).length > 0, 'reset confirmed in the live region');
  await closeDialog();

  // ---- search dialog
  await page.goto(B + '/');
  await openDialog('#search-toggle');
  ok(await page.evaluate(() => document.activeElement.id === 'overlay-search'), 'search input focused');
  ok((await page.locator('.swal2-html-container a', { hasText: 'ค่าเทอม' }).count()) === 1, 'popular searches listed');
  await page.click('[data-search-form] button[type=submit]');
  ok((await page.textContent('[data-search-msg]')).includes('ค้นหา'), 'empty search shows a message');
  await page.fill('#overlay-search', 'ข่าว');
  await page.press('#overlay-search', 'Enter');
  await page.waitForURL(/s=/);
  ok(decodeURIComponent(page.url()).includes('s=ข่าว'), 'search submits to the results page');
  await openDialog('#search-toggle');
  ok((await page.locator('[data-recent-list] a').count()) === 1, 'recent search remembered');
  await page.click('[data-clear-recent]');
  ok(await page.locator('[data-recent]').isHidden(), 'recent searches cleared');
  ok(await page.evaluate(() => document.activeElement.id === 'overlay-search'), 'focus moves to the search input after Clear');
  await closeDialog();

  // ---- menus (desktop dropdown, keyboard)
  await page.goto(B + '/');
  const tog = page.locator('.submenu-toggle').first();
  ok((await tog.count()) === 1, 'dropdown toggle injected');
  await tog.focus();
  await page.keyboard.press('Enter');
  ok((await tog.getAttribute('aria-expanded')) === 'true' && (await page.isVisible('.sub-menu')), 'dropdown opens with the keyboard');
  await page.keyboard.press('Escape');
  ok(!(await page.isVisible('.sub-menu')), 'Esc closes the dropdown');
  await page.goto(B + '/');
  await page.keyboard.press('Tab');
  ok((await page.evaluate(() => document.activeElement.textContent.trim())) === 'ข้ามไปยังเนื้อหาหลัก', 'first Tab = skip link');

  // ---- OS dark preference vs explicit choice
  {
    const c = await newCtx({ colorScheme: 'dark' });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await p.evaluate(() => getComputedStyle(document.body).backgroundColor)) === 'rgb(15, 23, 42)', 'auto theme follows OS dark');
    await p.evaluate(() => localStorage.setItem('mcupr_prefs', '{"theme":"light"}'));
    await p.reload();
    ok((await p.evaluate(() => getComputedStyle(document.body).backgroundColor)) === 'rgb(241, 245, 249)', 'explicit light overrides OS dark');
    await c.close();
  }

  // ---- mobile drawer
  {
    const c = await newCtx({ viewport: { width: 375, height: 800 } });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'no horizontal scroll at 375px');
    ok(!(await p.isVisible('#site-nav')), 'menu drawer closed on mobile');
    await p.click('.nav-toggle');
    await p.waitForTimeout(400);
    ok(await p.isVisible('#site-nav'), 'menu drawer opens');
    ok((await p.getAttribute('.nav-toggle', 'aria-expanded')) === 'true', 'toggle aria-expanded=true');
    ok(await p.isVisible('#nav-overlay'), 'overlay shown');
    await p.screenshot({ path: shot('func-drawer.png') });
    await p.keyboard.press('Escape');
    await p.waitForTimeout(400);
    ok(!(await p.isVisible('#site-nav')), 'Esc closes the drawer');
    ok((await p.evaluate(() => document.activeElement.classList.contains('nav-toggle'))), 'focus returns to the menu button');
    await p.click('.nav-toggle');
    await p.waitForTimeout(400);
    await p.click('#nav-overlay', { position: { x: 10, y: 300 } });
    await p.waitForTimeout(400);
    ok(!(await p.isVisible('#site-nav')), 'clicking the overlay closes the drawer');
    await c.close();
  }

  // ---- axe-core (WCAG 2.2 AA + best practice)
  for (const [name, url] of [['home', '/'], ['single', post1], ['404', '/nonexist'], ['search', '/?s=ข่าว']]) {
    for (const theme of ['light', 'dark']) {
      const pg = await ctx.newPage();
      await pg.goto(B + url);
      await pg.evaluate((t) => localStorage.setItem('mcupr_prefs', JSON.stringify({ theme: t })), theme);
      await pg.reload();
      await pg.addScriptTag({ content: axeSource() });
      const r = await pg.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
      ok(r.violations.length === 0, `axe ${name}/${theme}: ${r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'}`);
      r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((n) => n.target.join(' ') + ' | ' + (n.any[0] || n.all[0] || {}).message).join(' || ')));
      await pg.close();
    }
  }
  // axe on each dialog while open
  for (const [name, btn] of [['a11y-dialog', '#a11y-toggle'], ['language-dialog', '#lang-toggle'], ['search-dialog', '#search-toggle']]) {
    const pg = await ctx.newPage();
    await pg.goto(B + '/');
    await pg.addScriptTag({ content: axeSource() });
    await pg.click(btn);
    await pg.waitForSelector('.swal2-popup');
    await pg.waitForTimeout(300);
    const r = await pg.evaluate(() => axe.run(document.querySelector('.swal2-container'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, `axe ${name}: ${r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'}`);
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((n) => n.target.join(' ') + ' | ' + (n.any[0] || n.all[0] || {}).message).join(' || ')));
    await pg.close();
  }

  ok(errs.length === 0, 'no JS/console errors ' + JSON.stringify(errs));
  await br.close();
  finish();
})();
