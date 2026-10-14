// Certify Hub style interface + visitor customisation: menu position (top bar / sidebar),
// colour scheme picker (presets + any custom colour with automatic contrast), settings dialog,
// About dialog, credit, Sarabun font, canvas/card look.
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

const post = (n) => '/' + encodeURIComponent('ข่าวทดสอบ-' + n) + '/';
const consent = () => localStorage.setItem('mcupr_consent', '1');

(async () => {
  const br = await launch();
  const newCtx = async (opts = {}, prefs = null, mods = null) => {
    const c = await br.newContext({ viewport: { width: 1366, height: 860 }, reducedMotion: 'reduce', ...opts });
    await c.addInitScript(consent);
    if (prefs) await c.addInitScript((p) => { if (!sessionStorage.getItem('seeded')) { localStorage.setItem('mcupr_prefs', p); sessionStorage.setItem('seeded', '1'); } }, prefs);
    if (mods) await c.addCookies([{ name: 'mod_stub', value: JSON.stringify(mods), url: B }]);
    return c;
  };
  const attr = (p) => p.evaluate(() => document.documentElement.dataset.menu);
  const css = (p, sel, prop) => p.evaluate(([s, pr]) => getComputedStyle(document.querySelector(s))[pr], [sel, prop]);
  const ratio = (p, a, b) => p.evaluate(([x, y]) => {
    const lum = (c) => { const m = c.match(/\d+(\.\d+)?/g).map(Number).slice(0, 3).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]; };
    const [l1, l2] = [lum(x), lum(y)].sort((u, v) => v - u); return (l1 + 0.05) / (l2 + 0.05);
  }, [a, b]);
  const colours = (p) => p.evaluate(() => {
    const b = document.createElement('a'); b.href = '#'; b.className = 'btn'; document.body.appendChild(b);
    const l = document.createElement('a'); l.href = '#'; document.body.appendChild(l);
    const o = { btnBg: getComputedStyle(b).backgroundColor, btnFg: getComputedStyle(b).color, link: getComputedStyle(l).color, canvas: getComputedStyle(document.body).backgroundColor, primary: getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() };
    b.remove(); l.remove(); return o;
  });
  const openSettings = async (p) => { await p.click('#a11y-toggle'); await p.waitForSelector('.swal2-popup'); };

  // ================= look: canvas, cards, font
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await css(p, 'body', 'backgroundColor')) === 'rgb(241, 245, 249)', 'slate canvas behind white cards');
    ok((await css(p, '#masthead', 'position')) === 'sticky', 'sticky header');
    ok((await p.locator('#masthead .brand img[src$="assets/img/mcu-logo.webp"]').count()) === 1, 'default logo: the university emblem (no logo uploaded)');
    ok((await css(p, 'body', 'fontFamily')).startsWith('Sarabun'), 'Sarabun is the UI font');
    ok(await p.evaluate(() => document.fonts.check('16px Sarabun')), 'Sarabun font file loads (self-hosted)');
    await p.goto(B + post(1));
    ok((await css(p, '.panel-card', 'backgroundColor')) === 'rgb(255, 255, 255)', 'article body sits on a white card');
    await p.screenshot({ path: shot('layout-top.png') });
    await c.close();
  }

  // ================= menu position: top vs side
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await attr(p)) === 'top', 'default menu position = top');
    ok(await p.isVisible('#site-nav .menu') && (await css(p, '#site-nav', 'position')) !== 'fixed', 'top: pill menu inside the header');
    ok(!(await p.locator('#site-nav .sb-brand').isVisible()), 'top: sidebar brand hidden');
    ok((await p.evaluate(() => getComputedStyle(document.body).paddingLeft)) === '0px', 'top: no sidebar offset');
    // switch in the settings dialog
    await openSettings(p);
    ok(await p.locator('input[data-pref=menu][value=top]').isChecked(), 'dialog: "top" selected');
    await p.check('input[data-pref=menu][value=side]', { force: true });
    ok((await attr(p)) === 'side', 'switch to sidebar applies at once');
    await p.keyboard.press('Escape');
    await p.waitForSelector('.swal2-popup', { state: 'detached' });
    ok((await css(p, '#site-nav', 'position')) === 'fixed' && (await css(p, '#site-nav', 'left')) === '12px', 'side: fixed sidebar card on the left, 12px from the edge');
    const nav = await p.evaluate(() => { const r = document.getElementById('site-nav').getBoundingClientRect(); return [Math.round(r.x), Math.round(r.width), Math.round(r.height)]; });
    ok(nav[0] === 12 && nav[1] === 248 && nav[2] >= 150 && nav[2] <= 760, 'side: card 17rem minus margins wide, only as tall as its menu ' + nav);
    ok((await css(p, 'body', 'paddingLeft')) === '272px', 'side: page content offset by the sidebar width');
    ok(await p.locator('#site-nav .sb-brand').isVisible() && !(await p.locator('#masthead .brand').isVisible()), 'side: brand moves into the rail');
    ok((await p.locator('#site-nav .menu .ic').first().isVisible()), 'side: menu items show icons / initials');
    ok((await css(p, '#site-nav', 'borderTopLeftRadius')) !== '0px', 'side: the sidebar is a rounded floating card');
    ok(await p.isVisible('#search-toggle') && await p.isVisible('#a11y-toggle'), 'side: tools stay in the top bar');
    // current page highlighted as a filled pill
    await p.goto(B + '/about/');
    await p.screenshot({ path: shot('layout-side.png') });
    // persists
    await p.reload();
    ok((await attr(p)) === 'side', 'choice persisted after reload (applied before paint)');
    // collapse to icon rail
    await p.click('[data-sb-toggle]');
    await p.mouse.move(900, 600);
    await p.waitForTimeout(300);
    ok(await p.evaluate(() => document.documentElement.classList.contains('sb-min')), 'collapse button -> icon rail');
    ok((await css(p, 'body', 'paddingLeft')) === '92px', 'rail collapsed to 5.75rem (92px)');
    ok((await p.locator('#site-nav .menu .t').first().evaluate((el) => getComputedStyle(el).opacity)) === '0', 'labels hidden in the icon rail');
    await p.hover('#site-nav .menu li a');
    await p.waitForTimeout(300);
    ok((await p.locator('#site-nav .menu .t').first().evaluate((el) => getComputedStyle(el).opacity)) === '1', 'hover expands the rail');
    await p.mouse.move(900, 600);
    await p.reload();
    ok(await p.evaluate(() => document.documentElement.classList.contains('sb-min')), 'collapsed state persisted');
    await p.click('[data-sb-toggle]');
    ok(!(await p.evaluate(() => document.documentElement.classList.contains('sb-min'))), 'expand again');
    // dropdown behaviour in the side rail: sub-menu opens inline
    await p.locator('.submenu-toggle').first().click();
    ok(await p.isVisible('.sub-menu') && (await css(p, '.sub-menu', 'position')) === 'static', 'sub-menu opens inline inside the rail');
    // back to top bar
    await openSettings(p);
    ok(await p.locator('input[data-pref=menu][value=side]').isChecked(), 'dialog reflects the sidebar choice');
    await p.check('input[data-pref=menu][value=top]', { force: true });
    ok((await attr(p)) === 'top' && (await css(p, 'body', 'paddingLeft')) === '0px', 'back to the top bar');
    await c.close();
  }

  // ================= sidebar keyboard + a11y
  {
    const c = await newCtx({}, '{"menu":"side"}');
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.keyboard.press('Tab'); await p.keyboard.press('Tab'); await p.keyboard.press('Tab'); // skip links
    await p.keyboard.press('Tab');
    ok(await p.evaluate(() => !!document.activeElement.closest('#site-nav, #masthead, nav')), 'keyboard reaches the rail');
    for (const [name, u] of [['home', '/'], ['news', post(3)], ['page', '/about/about-1/']]) {
      await p.goto(B + u);
      await p.addScriptTag({ content: axeSource() });
      const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
      ok(r.violations.length === 0, `axe (sidebar mode) ${name}: ${r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'}`);
      r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    }
    await c.close();
  }

  // ================= site default from the Customizer vs visitor choice
  {
    const c = await newCtx({}, null, { mcupr_menu_layout: 'side' });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await attr(p)) === 'side', 'Customizer default "side" applies to new visitors');
    await openSettings(p);
    ok(await p.locator('input[data-pref=menu][value=side]').isChecked(), 'dialog shows the site default');
    await p.check('input[data-pref=menu][value=top]', { force: true });
    ok((await attr(p)) === 'top', 'visitor overrides the site default');
    await p.keyboard.press('Escape');
    await p.reload();
    ok((await attr(p)) === 'top', 'visitor choice persists over the site default');
    await openSettings(p);
    await p.click('[data-reset-prefs]');
    ok((await attr(p)) === 'side', 'reset returns to the site default');
    await c.close();
  }

  // ================= mobile: drawer + floating button
  {
    const c = await newCtx({ viewport: { width: 390, height: 820 } }, '{"menu":"side"}');
    const p = await c.newPage();
    await p.goto(B + '/');
    ok(await p.isVisible('#nav-fab') && !(await p.isVisible('.nav-toggle')), 'sidebar mode on phones: floating menu button, no header hamburger');
    ok((await css(p, 'body', 'paddingLeft')) === '0px', 'no rail offset on phones');
    await p.click('#nav-fab');
    await p.waitForTimeout(450);
    ok(await p.isVisible('#site-nav') && (await p.getAttribute('#nav-fab', 'aria-expanded')) === 'true', 'floating button opens the drawer');
    await p.screenshot({ path: shot('layout-mobile-drawer.png') });
    await p.keyboard.press('Escape');
    await p.waitForTimeout(450);
    ok(!(await p.isVisible('#site-nav')), 'Esc closes it');
    await c.close();
    const c2 = await newCtx({ viewport: { width: 390, height: 820 } });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok(await p2.isVisible('.nav-toggle') && !(await p2.isVisible('#nav-fab')), 'top mode on phones: header hamburger');
    await c2.close();
  }

  // ================= visitor colour scheme
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    const base = await colours(p);
    await openSettings(p);
    ok((await p.locator('[data-color-preset]').count()) === 6, 'six colour presets offered');
    ok((await p.getAttribute('[data-color-preset="rose"]', 'aria-checked')) === 'true', 'site default preset marked');
    await p.click('[data-color-preset="indigo"]');
    let o = await colours(p);
    ok(o.primary === '#4f46e5' && o.btnBg === 'rgb(79, 70, 229)', 'Indigo (Certify Hub) preset applied live: ' + o.primary);
    ok((await p.getAttribute('[data-color-preset="indigo"]', 'aria-checked')) === 'true', 'selected swatch marked');
    await p.keyboard.press('Escape');
    await p.reload();
    o = await colours(p);
    ok(o.primary === '#4f46e5', 'colour persists after reload');
    ok((await p.evaluate(() => { const s = document.getElementById('mcupr-user-colors'); const l = document.querySelector('link[href*="tailwind.css"]'); return !!s && !!l && s.compareDocumentPosition(l) & Node.DOCUMENT_POSITION_FOLLOWING; })), 'colours applied from <head> before the stylesheet loads (no flash)');
    await p.screenshot({ path: shot('layout-indigo.png') });
    // custom colours, including worst cases: contrast is derived automatically
    for (const hex of ['#ffe14d', '#808080', '#0f766e', '#000000', '#ffffff', '#ff0000']) {
      await openSettings(p);
      await p.fill('[data-color-custom]', hex);
      await p.keyboard.press('Escape');
      await p.waitForSelector('.swal2-popup', { state: 'detached' });
      for (const theme of ['light', 'dark']) {
        await p.evaluate((t) => { document.documentElement.dataset.theme = t; }, theme);
        const k = await colours(p);
        const bg = theme === 'dark' ? 'rgb(15, 23, 42)' : k.canvas;
        const rb = await ratio(p, k.btnBg, k.btnFg);
        const rl = await ratio(p, k.link, bg);
        ok(rb >= 4.5 && rl >= 4.5, `custom ${hex}/${theme}: button ${rb.toFixed(1)}:1, link ${rl.toFixed(1)}:1`);
      }
      await p.evaluate(() => { delete document.documentElement.dataset.theme; });
    }
    // contrast modes still win over a visitor colour
    await p.evaluate(() => { document.documentElement.dataset.contrast = 'yb'; });
    ok((await css(p, 'body', 'backgroundColor')) === 'rgb(0, 0, 0)' && (await p.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary').trim())) === '#000', 'high-contrast mode overrides the visitor colour');
    await p.evaluate(() => { delete document.documentElement.dataset.contrast; });
    // back to site default via the swatch, and via reset
    await openSettings(p);
    await p.click('[data-color-preset="rose"]');
    o = await colours(p);
    ok(o.primary === base.primary && (await p.locator('#mcupr-user-colors').count()) === 0, 'choosing the site default removes the override');
    await p.click('[data-color-preset="navy"]');
    await p.click('[data-reset-prefs]');
    o = await colours(p);
    ok(o.primary === base.primary, 'reset restores the site colours');
    await c.close();
    // site default from the Customizer (Indigo preset) + visitor still free to change
    const c2 = await newCtx({}, null, { mcupr_palette: 'indigo' });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await colours(p2)).primary === '#4f46e5', 'Customizer: Indigo preset as the site colour');
    await openSettings(p2);
    ok((await p2.getAttribute('[data-color-preset="indigo"]', 'aria-checked')) === 'true', 'dialog marks the site preset');
    await p2.click('[data-color-preset="teal"]');
    ok((await colours(p2)).primary === '#0f766e', 'visitor picks another scheme');
    await c2.close();
  }

  // ================= ashoka flower background pattern
  {
    const pseudo = (p, which) => p.evaluate((w) => { const cs = getComputedStyle(document.body, w); return { display: cs.display, op: cs.opacity, mask: cs.webkitMaskImage || cs.maskImage, pos: cs.position, z: cs.zIndex, pe: cs.pointerEvents }; }, which);
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    let a = await pseudo(p, '::before');
    let b = await pseudo(p, '::after');
    ok(a.display === 'block' && a.mask.includes('data:image/svg+xml') && a.pos === 'fixed' && a.pe === 'none', 'ashoka flowers pattern layer (fixed, non-interactive, SVG mask)');
    ok((a.mask.match(/data:image\/svg\+xml/g) || []).length === 2, 'flowers and leaves are two masks of ONE layer (one colour, one opacity: a single shade)');
    ok(b.display === 'block' && (b.mask === 'none' || b.mask === '') && /gradient/.test(await p.evaluate(() => getComputedStyle(document.body, '::after').backgroundImage)), 'second layer is only a soft veil of the page colour (no other colour in the pattern)');
    ok(parseFloat(a.op) > 0 && parseFloat(a.op) <= 0.2, 'pattern is faint (opacity ' + a.op + ')');
    ok((await p.evaluate(() => getComputedStyle(document.body, '::before').backgroundColor)) === 'rgb(194, 24, 91)', 'flowers take the main (pink) colour');
    await p.screenshot({ path: shot('layout-pattern.png') });
    // visitor switch
    await openSettings(p);
    await p.click('summary');
    ok(!(await p.locator('input[data-pref=pattern]').isChecked()), 'dialog: "hide pattern" unchecked by default');
    await p.check('input[data-pref=pattern]', { force: true });
    ok((await p.evaluate(() => document.documentElement.dataset.pattern)) === 'off' && (await pseudo(p, '::before')).display === 'none', 'hiding the pattern works');
    await p.keyboard.press('Escape');
    await p.reload();
    ok((await pseudo(p, '::before')).display === 'none', 'choice persisted');
    await openSettings(p);
    await p.click('[data-reset-prefs]');
    ok((await pseudo(p, '::before')).display === 'block', 'reset shows it again');
    await c.close();
    // follows the colour scheme
    const c2 = await newCtx({}, null, { mcupr_palette: 'navy' });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.evaluate(() => getComputedStyle(document.body, '::before').backgroundColor)) === 'rgb(31, 58, 110)', 'pattern follows the colour scheme (navy)');
    await c2.close();
    // contrast mode hides it
    const c3 = await newCtx({}, '{"contrast":"wb"}');
    const p3 = await c3.newPage();
    await p3.goto(B + '/');
    ok((await pseudo(p3, '::before')).display === 'none' && (await pseudo(p3, '::after')).display === 'none', 'hidden in high-contrast mode');
    await c3.close();
    // Customizer default off, visitor can turn it on
    const c4 = await newCtx({}, null, { mcupr_pattern: false });
    const p4 = await c4.newPage();
    await p4.goto(B + '/');
    ok((await pseudo(p4, '::before')).display === 'none', 'Customizer default off hides it for new visitors');
    await openSettings(p4);
    await p4.click('summary');
    ok(await p4.locator('input[data-pref=pattern]').isChecked(), 'dialog reflects the site default (hidden)');
    await p4.uncheck('input[data-pref=pattern]', { force: true });
    ok((await pseudo(p4, '::before')).display === 'block', 'visitor can turn it on');
    await p4.keyboard.press('Escape');
    await p4.reload();
    ok((await pseudo(p4, '::before')).display === 'block', 'visitor choice beats the site default');
    await c4.close();
  }

  // ================= About dialog + credit
  {
    const c = await newCtx({}, null, { mcupr_credit_name: 'ทีมงานเว็บไซต์', mcupr_credit_url: 'https://example.org/', mcupr_about_th: 'เว็บไซต์ทดสอบสำหรับธีม MCUPR' });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await text(p, '#colophon')).includes('จัดทำโดย ทีมงานเว็บไซต์') && (await p.locator('#colophon a[href="https://example.org/"]').count()) === 1, 'footer credit with link');
    await p.click('#about-toggle');
    await p.waitForSelector('.swal2-popup');
    const t = await text(p, '.swal2-html-container');
    ok(t.includes('มหาวิทยาลัยทดสอบ') && t.includes('เว็บไซต์ทดสอบสำหรับธีม MCUPR') && t.includes('ทีมงานเว็บไซต์') && t.includes('ธีม MCUPR'), 'About dialog: name, about text, credit, theme version');
    ok((await p.locator('.swal2-html-container a').count()) >= 1, 'About dialog lists policy links');
    await p.keyboard.press('Escape');
    await p.waitForSelector('.swal2-popup', { state: 'detached' });
    await p.click('[data-open-about]');
    await p.waitForSelector('.swal2-popup');
    ok(true, 'footer "About" link opens it too');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document.querySelector('.swal2-container'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe About dialog: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    await p.keyboard.press('Escape');
    // settings dialog axe with the new sections (+ details open)
    await p.waitForSelector('.swal2-popup', { state: 'detached' });
    await openSettings(p);
    await p.click('summary');
    const r2 = await p.evaluate(() => axe.run(document.querySelector('.swal2-container'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r2.violations.length === 0, 'axe settings dialog: ' + (r2.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r2.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await c.close();
  }

  // ================= hybrid: floating pill bar on top + always-collapsed icon rail
  {
    const c = await newCtx({}, '{"menu":"hybrid"}');
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await attr(p)) === 'hybrid', 'hybrid: applied before paint from the saved choice');
    ok(!(await p.locator('#masthead .brand').isVisible()) && (await p.locator('#site-nav .sb-brand').isVisible()), 'hybrid: the logo sits at the top of the sidebar card, not in the top bar');
    ok((await css(p, '#masthead', 'borderTopLeftRadius')) !== '0px' && (await css(p, '#masthead', 'boxShadow')) !== 'none', 'hybrid: header floats as a rounded pill with a shadow');
    const nav = await p.evaluate(() => { const r = document.querySelector('#site-nav').getBoundingClientRect(); return [Math.round(r.left), Math.round(r.width)]; });
    ok((await css(p, '#site-nav', 'position')) === 'fixed' && nav[0] === 12 && nav[1] <= 90, 'hybrid: slim icon card on the left ' + nav);
    ok((await css(p, 'body', 'paddingLeft')) === '92px', 'hybrid: content offset by the slim rail');
    ok(!(await p.locator('#site-nav .sb-foot').isVisible()), 'hybrid: no collapse button (rail is always slim)');
    await p.hover('#site-nav .menu > li > a');
    await p.waitForTimeout(350);
    ok((await p.evaluate(() => document.querySelector('#site-nav').getBoundingClientRect().width)) > 200, 'hybrid: rail expands on hover');
    await p.mouse.move(900, 500);
    await p.screenshot({ path: shot('layout-hybrid.png') });
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe (hybrid mode): ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    await p.click('#a11y-toggle'); await p.waitForSelector('.swal2-popup');
    ok(await p.locator('input[data-pref=menu][value=hybrid]').isChecked(), 'hybrid: settings dialog offers and reflects the hybrid choice');
    await p.check('input[data-pref=menu][value=top]', { force: true });
    ok((await attr(p)) === 'top' && (await p.evaluate(() => !document.documentElement.classList.contains('sb-min'))), 'hybrid → top: switches at once, slim-rail flag cleared');
    await c.close();
    const c2 = await newCtx({ viewport: { width: 390, height: 820 } }, '{"menu":"hybrid"}');
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok(await p2.isVisible('.nav-toggle') && (await p2.evaluate(() => document.documentElement.scrollWidth <= 390)), 'hybrid on phones: header hamburger + drawer, no horizontal scroll');
    await c2.close();
  }

  // ================= top-bar menu: one row, overflow goes into "More"
  {
    const rowTops = (p) => p.locator('#site-nav .menu > li').evaluateAll((els) => els.map((e) => Math.round(e.getBoundingClientRect().top)));
    const c = await newCtx({ viewport: { width: 1280, height: 800 } });
    const p = await c.newPage();
    await p.goto(B + '/?lang=en');
    await p.waitForSelector('.menu-more');
    const tops = await rowTops(p);
    ok(new Set(tops).size === 1, 'top menu: every item on ONE row ' + tops.join(','));
    const hb = await p.locator('#masthead').evaluate((el) => Math.round(el.getBoundingClientRect().height));
    ok(hb <= 90, 'header stays slim (' + hb + 'px)');
    const hidden = await p.locator('.menu-more > .sub-menu > li').count();
    ok(hidden >= 1, 'overflowing items moved into "More" (' + hidden + ')');
    ok((await p.locator('#site-nav .menu li:not(.menu-more) a').allTextContents()).concat(await p.locator('.menu-more .sub-menu a').allTextContents()).some((t) => /Contact us/.test(t)), 'no item is lost: "Contact us" is reachable');
    const moreBtn = p.locator('.more-btn');
    ok((await moreBtn.getAttribute('aria-expanded')) === 'false' && /More/.test(await moreBtn.textContent()), 'More button is a real button with aria-expanded');
    await moreBtn.click();
    ok((await moreBtn.getAttribute('aria-expanded')) === 'true' && await p.locator('.menu-more > .sub-menu').isVisible(), 'More opens the dropdown');
    const box = await p.locator('.menu-more > .sub-menu').boundingBox();
    ok(box.x >= 0 && box.x + box.width <= 1280, 'dropdown stays inside the screen (' + Math.round(box.x) + '..' + Math.round(box.x + box.width) + ')');
    await p.screenshot({ path: shot('layout-menu-more.png') });
    await p.keyboard.press('Escape');
    ok((await moreBtn.getAttribute('aria-expanded')) === 'false' && !(await p.locator('.menu-more > .sub-menu').isVisible()), 'Esc closes it and returns focus to the button');
    await moreBtn.click();
    await p.mouse.click(300, 600);
    ok(!(await p.locator('.menu-more > .sub-menu').isVisible()), 'a click outside closes it');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with the overflow menu: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await p.setViewportSize({ width: 1920, height: 800 });
    await p.waitForTimeout(400);
    const hidden2 = await p.locator('.menu-more > .sub-menu > li').count();
    ok(hidden2 < hidden, 'wider screen: fewer items in More (' + hidden + ' -> ' + hidden2 + ')');
    ok(new Set(await rowTops(p)).size === 1, 'still one row at 1920');
    await p.setViewportSize({ width: 800, height: 800 });
    await p.waitForTimeout(400);
    ok((await p.locator('.menu-more').count()) === 0, 'below 1100px: the drawer shows the full menu (no More)');
    await p.setViewportSize({ width: 1280, height: 800 });
    await p.waitForTimeout(400);
    ok((await p.locator('.menu-more').count()) === 1, 'back to desktop: More returns');
    await p.evaluate(() => { document.documentElement.setAttribute('data-menu', 'side'); });
    await p.waitForTimeout(400);
    ok((await p.locator('.menu-more').count()) === 0 && (await p.locator('#site-nav .menu > li').count()) === 8, 'sidebar mode: all 8 items in the rail (no More)');
    await c.close();
  }

  // ================= menu icons + mega menu
  {
    const c = await newCtx({ viewport: { width: 1280, height: 800 } });
    const p = await c.newPage();
    await p.goto(B + '/?lang=zh');
    const items = p.locator('#site-nav .menu > li');
    ok((await items.locator(':scope > a > .ic.has-ic svg').count()) === 3, 'menu icons: 3 top-level items show the icon chosen in the menu editor');
    const first = await items.first().locator('a').evaluate((a) => { const ic = a.querySelector('.ic'); const t = a.querySelector('.t'); return ic.getBoundingClientRect().right <= t.getBoundingClientRect().left + 1 && getComputedStyle(ic).display !== 'none'; });
    ok(first, 'menu icon sits in front of the text and is visible on the top bar');
    ok((await items.last().locator('.ic').count()) === 1 && !(await items.last().locator('.ic').isVisible()), 'an item without an icon shows no icon on the top bar (initial badge only in drawer / sidebar)');
    const mega = p.locator('#site-nav .menu > li.mcupr-mega');
    ok((await mega.count()) === 1 && /mega-cols-3/.test(await mega.getAttribute('class')), 'mega item carries its column count');
    ok(!(await mega.locator('> .sub-menu').isVisible()), 'mega panel starts closed');
    await mega.locator('> .submenu-toggle').click();
    const panel = mega.locator('> .sub-menu');
    const g = await panel.evaluate((el) => { const cs = getComputedStyle(el); const r = el.getBoundingClientRect(); return { display: cs.display, cols: cs.gridTemplateColumns.split(' ').length, left: Math.round(r.left), right: Math.round(r.right), top: Math.round(r.top) }; });
    ok(g.display === 'grid' && g.cols === 3, 'mega panel: a 3-column grid');
    ok(g.left >= 0 && g.right <= 1280 && g.right - g.left > 900, 'mega panel spans the header and stays on screen (' + g.left + '..' + g.right + ')');
    ok((await panel.locator('> li.menu-item-has-children > a').allTextContents()).map((x) => x.trim()).join('|') === '本科|研究生', 'mega panel: second-level items with children are the column headings');
    ok((await panel.locator('> li > .sub-menu').first().isVisible()) && (await panel.locator('> li > .sub-menu a').count()) === 5, 'mega panel: the links under each heading are always visible');
    ok(/面向在职人员的短期课程/.test(await text(p, '#site-nav .menu .d')), 'mega panel: description printed under its link');
    ok(!(await panel.locator('.submenu-toggle').first().isVisible()), 'mega panel: no toggle buttons inside it');
    await p.screenshot({ path: shot('layout-mega.png') });
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with the mega menu open: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await p.keyboard.press('Escape');
    ok(!(await panel.isVisible()), 'Esc closes the mega panel');
    await mega.locator('> .submenu-toggle').click();
    await p.mouse.click(40, 700);
    ok(!(await panel.isVisible()), 'a click outside closes it');
    await p.evaluate(() => { document.documentElement.setAttribute('data-menu', 'side'); });
    await p.waitForTimeout(300);
    const sideMega = p.locator('#site-nav .menu > li.mcupr-mega');
    await sideMega.locator('> .submenu-toggle').click();
    ok((await sideMega.locator('> .sub-menu').evaluate((el) => getComputedStyle(el).display)) !== 'grid', 'sidebar: the mega item is a normal nested list (no grid)');
    ok((await sideMega.locator('.ic.has-ic').count()) >= 2, 'sidebar: icons still shown');
    await p.evaluate(() => { document.documentElement.setAttribute('data-menu', 'top'); });
    await p.setViewportSize({ width: 800, height: 800 });
    await p.waitForTimeout(300);
    await p.click('.nav-toggle');
    await p.locator('#site-nav .mcupr-mega > .submenu-toggle').click();
    ok((await p.locator('#site-nav .mcupr-mega > .sub-menu').evaluate((el) => getComputedStyle(el).display)) !== 'grid', 'drawer: accordion, not a grid');
    await c.close();
  }

  // ================= About dialog in the Precision QC layout
  {
    const mods = { mcupr_credit_name: 'ผู้พัฒนาทดสอบ', mcupr_credit_role: 'นักวิชาการ', mcupr_credit_tag: 'Full-Stack', mcupr_credit_email: 'dev@example.org', mcupr_credit_tel: '089-000-0000', mcupr_credit_url: 'https://example.org/', mcupr_org_email: 'info@example.org', mcupr_org_tel: '054-000-000' };
    const c = await newCtx({}, null, mods);
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.click('#about-toggle');
    await p.waitForSelector('.swal2-popup');
    const d = p.locator('.swal2-html-container');
    ok((await d.locator('.about-head img').count()) === 1 && /เกี่ยวกับ มหาวิทยาลัยทดสอบ/.test(await text(p, '.about-head')), 'About: header with the logo and "About {organisation}"');
    ok((await d.locator('.about-sec').count()) === 5, 'About: developer, website, technology, rights, contact sections');
    ok((await d.locator('.about-sec > h3').allTextContents()).join('|') === 'ผู้พัฒนา|เกี่ยวกับเว็บไซต์|เทคโนโลยีและทรัพยากรที่นำมาใช้ (อ้างอิง)|สิทธิ์และการอ้างอิง|หน่วยงานและติดต่อ', 'About: section headings (Thai)');
    ok(/ผู้พัฒนาทดสอบ/.test(await text(p, '.dev-name')) && /นักวิชาการ/.test(await text(p, '.dev-role')) && /Full-Stack/.test(await text(p, '.dev-tag')), 'About: developer name, role, tagline');
    ok((await d.locator('.dev-photo-initial').count()) === 1 && (await d.locator('.dev-photo').evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius))) > 30, 'About: round developer badge (initial when no photo)');
    ok((await d.locator('a[href="mailto:dev@example.org"]').count()) === 1 && (await d.locator('a[href="tel:0890000000"]').count()) === 1 && (await d.locator('a[href="https://example.org/"][rel~=noopener]').count()) === 1, 'About: developer contacts are real links (mailto / tel / web)');
    ok((await d.locator('a[href="mailto:info@example.org"]').count()) === 1 && (await d.locator('a[href="tel:054000000"]').count()) === 1 && /123 ถนนทดสอบ/.test(await text(p, '.swal2-html-container')), 'About: organisation address, email, tel');
    ok((await d.locator('.about-list li').count()) >= 12 && (await d.locator('.chip-gray').count()) === 5, 'About: feature list, stack chips and credits');
    ok((await d.locator('a[href*="tailwindcss.com"][target=_blank][rel~=noopener]').count()) === 1, 'About: credits link out safely');
    const themeVer = (require('fs').readFileSync(require('path').join(__dirname, '../../mcupr/style.css'), 'utf8').match(/^Version:\s*(\S+)/m) || [])[1];
    ok((await text(p, '.swal2-html-container')).includes('MCUPR ' + themeVer), 'About: theme version ' + themeVer);
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document.querySelector('.swal2-container'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe About (new layout): ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await p.keyboard.press('Escape');
    await p.waitForSelector('.swal2-popup', { state: 'detached' });
    await p.goto(B + '/?lang=en');
    await p.click('#about-toggle');
    await p.waitForSelector('.swal2-popup');
    ok(/About Test University/.test(await text(p, '.about-head')) && /Developer/.test(await text(p, '#ab-dev')) && /Rights and attributions/.test(await text(p, '#ab-rights')), 'About: English');
    await c.close();
    const c2 = await newCtx();
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    await p2.click('#about-toggle');
    await p2.waitForSelector('.swal2-popup');
    ok((await p2.locator('.swal2-html-container #ab-dev').count()) === 0 && (await p2.locator('.swal2-html-container .about-sec').count()) === 4, 'About: no developer section until a credit is set (no personal data by default)');
    await c2.close();
  }

  await br.close();
  finish();
  async function text(p, sel) { return p.evaluate((s) => (document.querySelector(s) || {}).textContent || '', sel); }
})();
