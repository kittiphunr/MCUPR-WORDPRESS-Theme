// Content design: Thai government dates (พ.ศ.), 3 news layouts, page + archive design, colour schemes
// and contrast, logos, animation on/off, and the text-to-speech "listen" tool.
const { B, ok, launch, shot, axeSource, finish } = require('./lib');

const post = (n) => '/' + encodeURIComponent('ข่าวทดสอบ-' + n) + '/';
const consent = () => localStorage.setItem('mcupr_consent', '1');
const yearBE = new Date().getFullYear() + 543;

// A fake speech engine: records every utterance; each one "ends" after `speakMs`.
const speechStub = (speakMs) => `(() => {
  window.__spoken = []; window.__cancelled = 0; let timer = null, current = null;
  window.SpeechSynthesisUtterance = function (t) { this.text = t; this.lang = ''; this.rate = 1; this.voice = null; };
  Object.defineProperty(window, 'speechSynthesis', { configurable: true, value: {
    getVoices: () => [], addEventListener() {},
    speak(u) { window.__spoken.push({ text: u.text, lang: u.lang, rate: u.rate }); current = u; timer = setTimeout(() => { current = null; u.onend && u.onend({}); }, ${speakMs}); },
    cancel() { if (timer) { clearTimeout(timer); timer = null; window.__cancelled++; if (current && current.onerror) { const u = current; current = null; setTimeout(() => u.onerror({ error: 'canceled' }), 0); } } },
    pause() {}, resume() {}
  } });
})();`;

(async () => {
  const br = await launch();
  const newCtx = async (opts = {}, mods = null) => {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce', ...opts });
    await c.addInitScript(consent);
    if (mods) await c.addCookies([{ name: 'mod_stub', value: JSON.stringify(mods), url: B }]);
    return c;
  };
  const text = (p, sel) => p.evaluate((s) => (document.querySelector(s) || {}).textContent || '', sel);

  // ================= Thai government dates
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + post(1));
    const meta = await text(p, 'article header');
    ok(new RegExp(`วัน(อาทิตย์|จันทร์|อังคาร|พุธ|พฤหัสบดี|ศุกร์|เสาร์)ที่ \\d{1,2} (มกราคม|กุมภาพันธ์|มีนาคม|เมษายน|พฤษภาคม|มิถุนายน|กรกฎาคม|สิงหาคม|กันยายน|ตุลาคม|พฤศจิกายน|ธันวาคม) พ\\.ศ\\. ${yearBE}`).test(meta), 'news date: วัน…ที่ d เดือน พ.ศ. ' + yearBE);
    ok(/เวลา \d{2}\.\d{2} น\./.test(meta), 'time in Thai style "เวลา 09.30 น."');
    ok(/อ่านประมาณ \d+ นาที/.test(meta), 'reading time shown');
    ok((await p.getAttribute('article time', 'datetime')).startsWith(String(yearBE - 543)), 'datetime="" attribute stays Gregorian ISO 8601');
    await p.goto(B + '/');
    const card = await p.evaluate(() => [...document.querySelectorAll('section[aria-labelledby=news-title] article time')].map((t) => t.textContent));
    ok(card.length > 0 && card.every((t) => new RegExp(`^\\d{1,2} \\S+ ${yearBE}$`).test(t)), 'card dates "14 ตุลาคม ' + yearBE + '": ' + card[0]);
    ok((await text(p, '#colophon')).includes('© ' + yearBE), 'footer year in พ.ศ.');
    await p.goto(B + '/?lang=en');
    ok(/© \d{4}/.test(await text(p, '#colophon')) && !(await text(p, '#colophon')).includes('© ' + yearBE), 'English: Gregorian year');
    await p.goto(B + post(1));
    ok(!/พ\.ศ\./.test(await text(p, 'article header')) && new RegExp(String(yearBE - 543)).test(await text(p, 'article header')), 'English: Gregorian date, no พ.ศ.');
    await p.goto(B + '/?lang=th');
    // pure function checks through wp_date()
    await c.close();
  }

  // ================= three news layouts
  {
    const c = await newCtx();
    const p = await c.newPage();
    const layout = async (n) => { await p.goto(B + post(n)); return p.getAttribute('article[data-layout]', 'data-layout'); };
    ok((await layout(1)) === 'reader', 'default layout = reader');
    ok((await p.locator('article aside').count()) === 0 && (await p.locator('#article-body.reader').count()) === 1, 'reader: single column, reader typography');
    const w = await p.evaluate(() => document.querySelector('#article-body').getBoundingClientRect().width);
    ok(w <= 46 * 16 * 1 + 2, 'reader: measure limited to ~46rem (' + Math.round(w) + 'px)');
    await p.screenshot({ path: shot('layout-reader.png') });
    ok((await layout(3)) === 'cover', 'per-post override: cover');
    ok((await p.locator('article > header img').count()) === 1 && (await p.locator('article aside').count()) === 1, 'cover: hero image + tools rail');
    const railSticky = await p.evaluate(() => getComputedStyle(document.querySelector('article aside')).position);
    ok(railSticky === 'sticky', 'cover: tools rail is sticky');
    await p.screenshot({ path: shot('layout-cover.png') });
    ok((await layout(4)) === 'sidebar', 'per-post override: sidebar');
    ok((await p.locator('aside a').count()) >= 4, 'sidebar: related / latest / categories beside the article');
    ok((await p.locator('aside h2').count()) === 2 + 1 - 1 + 0 || (await p.locator('aside h2').count()) >= 2, 'sidebar: headings present');
    await p.screenshot({ path: shot('layout-sidebar.png') });
    ok((await p.locator('section[aria-labelledby^=related] article').count()) === 0 && (await p.locator('aside section[aria-labelledby^=related], aside [aria-labelledby^=related]').count()) >= 1, 'sidebar: related news lives in the sidebar (no duplicate block)');
    await layout(1);
    ok((await p.locator('section[aria-labelledby^=related] article').count()) >= 1, 'reader: related news block under the article');
    ok((await p.locator('nav[aria-label] a[rel=prev], nav[aria-label] a[rel=next]').count()) >= 1, 'previous / next cards');
    await c.close();
    // site default switched in the Customizer
    for (const [def, expect] of [['cover', 'cover'], ['sidebar', 'sidebar']]) {
      const c2 = await newCtx({}, { mcupr_news_layout: def });
      const p2 = await c2.newPage();
      await p2.goto(B + post(2));
      ok((await p2.getAttribute('article[data-layout]', 'data-layout')) === expect, `Customizer default "${def}" applies to posts without override`);
      await p2.goto(B + post(3));
      ok((await p2.getAttribute('article[data-layout]', 'data-layout')) === 'cover', 'per-post override wins over the default');
      await c2.close();
    }
  }

  // ================= page + archive design
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/about/about-2/');
    ok((await p.locator('header.mcu-wheel h1').count()) === 1, 'page: tinted band with wheel ornament and title');
    ok((await p.locator('aside[aria-labelledby=section-title] a').count()) === 4, 'page: "in this section" lists parent + 3 children');
    ok((await p.getAttribute('aside[aria-labelledby=section-title] a[aria-current]', 'href')).includes('about-2'), 'page: current page marked');
    ok(new RegExp(`ปรับปรุงล่าสุด \\d{1,2} \\S+ ${yearBE}`).test(await text(p, 'article')), 'page: last-updated date in พ.ศ.');
    ok((await p.locator('[data-print]').count()) >= 1, 'page: print button');
    await p.screenshot({ path: shot('page.png') });
    await p.goto(B + '/about/');
    ok((await p.locator('aside[aria-labelledby=section-title]').count()) === 1, 'parent page shows its sections too');
    await p.goto(B + '/privacy-policy/').catch(() => {});
    // archive
    await p.goto(B + '/category/' + encodeURIComponent('กิจกรรม') + '/');
    ok((await p.locator('header.mcu-wheel').count()) === 1, 'archive: band');
    ok((await p.getAttribute('header nav[aria-label] a[aria-current]', 'href')).includes('category'), 'archive: current category chip highlighted');
    const span = await p.evaluate(() => { const a = document.querySelector('main article'); return a ? getComputedStyle(a).gridColumnEnd : ''; });
    ok(span === 'span 2' || (await p.locator('main article.min-\\[900px\\]\\:col-span-2').count()) === 1, 'archive: first news item shown large');
    await p.screenshot({ path: shot('archive.png') });
    await c.close();
  }

  // ================= colour schemes, contrast, logos
  const ratio = (p, a, b) => p.evaluate(([x, y]) => {
    const lum = (c) => { const m = c.match(/\d+(\.\d+)?/g).map(Number).slice(0, 3).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * m[0] + 0.7152 * m[1] + 0.0722 * m[2]; };
    const [l1, l2] = [lum(x), lum(y)].sort((u, v) => v - u); return (l1 + 0.05) / (l2 + 0.05);
  }, [a, b]);
  const probe = (p) => p.evaluate(() => {
    const cs = getComputedStyle(document.documentElement); const s = document.createElement('a'); s.href = '#'; s.className = 'btn'; document.body.appendChild(s);
    const l = document.createElement('a'); l.href = '#'; l.id = 'probe-link'; document.body.appendChild(l);
    const o = { primary: cs.getPropertyValue('--primary').trim(), btnBg: getComputedStyle(s).backgroundColor, btnFg: getComputedStyle(s).color, link: getComputedStyle(l).color, bg: getComputedStyle(document.body).backgroundColor, bar: getComputedStyle(document.querySelector('#colophon .soft-card')).backgroundColor, barFg: getComputedStyle(document.querySelector('#colophon')).color, canvas: getComputedStyle(document.body).backgroundColor };
    s.remove(); l.remove(); return o;
  });
  for (const [name, mods] of [
    ['rose (default)', {}], ['maroon', { mcupr_palette: 'maroon' }], ['navy', { mcupr_palette: 'navy' }], ['saffron', { mcupr_palette: 'saffron' }],
    ['custom dark teal', { mcupr_palette: 'custom', mcupr_primary: '#0f766e', mcupr_bar: '#10201e' }],
    ['custom very light yellow (worst case)', { mcupr_palette: 'custom', mcupr_primary: '#ffe14d', mcupr_bar: '#fff7c2', mcupr_accent: '#ffe14d' }],
    ['custom mid-tone (worst case)', { mcupr_palette: 'custom', mcupr_primary: '#808080', mcupr_bar: '#808080' }],
  ]) {
    for (const theme of ['light', 'dark']) {
      const c = await newCtx({}, mods);
      await c.addInitScript((t) => localStorage.setItem('mcupr_prefs', JSON.stringify({ theme: t })), theme);
      const p = await c.newPage();
      await p.goto(B + '/');
      const o = await probe(p);
      const rb = await ratio(p, o.btnBg, o.btnFg);
      const rl = await ratio(p, o.link, o.canvas);
      const rf = await ratio(p, o.bar, o.barFg);
      ok(rb >= 4.5 && rl >= 4.5 && rf >= 4.5, `${name}/${theme}: button ${rb.toFixed(1)}:1, link ${rl.toFixed(1)}:1, footer text ${rf.toFixed(1)}:1`);
      if (theme === 'light' && name.startsWith('navy')) ok(o.primary === '#1f3a6e', 'navy preset applies --primary');
      await c.close();
    }
  }
  {
    // axe on key pages with a non-default scheme
    const c = await newCtx({}, { mcupr_palette: 'saffron' });
    const p = await c.newPage();
    for (const [n, u] of [['home', '/'], ['news cover', post(3)], ['page', '/about/about-1/']]) {
      await p.goto(B + u);
      await p.addScriptTag({ content: axeSource() });
      const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
      ok(r.violations.length === 0, `axe (saffron) ${n}: ${r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'}`);
      r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    }
    await c.close();
  }

  // logos
  {
    const c = await newCtx({}, { custom_logo: 'ATT', mcupr_logo_dark: 'ATT', mcupr_logo_footer: 'ATT', mcupr_logo_height: 80 });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await p.locator('#masthead .brand img.logo-light').count()) === 1 && (await p.locator('#masthead .brand img.logo-dark').count()) === 1, 'header: light + dark logo rendered');
    ok(await p.locator('#masthead .brand img.logo-light').isVisible() && !(await p.locator('#masthead .brand img.logo-dark').isVisible()), 'light mode shows the default logo');
    const h = await p.evaluate(() => document.querySelector('#masthead .brand img.logo-light').getBoundingClientRect().height);
    ok(Math.round(h) === 80, 'logo height from Customizer (80px): ' + Math.round(h));
    ok((await p.locator('#colophon img').count()) === 1, 'footer logo shown');
    await p.evaluate(() => localStorage.setItem('mcupr_prefs', '{"theme":"dark"}'));
    await p.reload();
    ok(!(await p.locator('#masthead .brand img.logo-light').isVisible()) && (await p.locator('#masthead .brand img.logo-dark').isVisible()), 'dark mode swaps to the dark logo');
    await c.close();
    const c2 = await newCtx({}, { custom_logo: 'ATT' });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.locator('#masthead .brand img.logo-dark').count()) === 0, 'no dark logo -> single logo only');
    await c2.close();
  }

  // ================= animations on/off
  {
    const c = await br.newContext({ viewport: { width: 1280, height: 800 } }); // motion allowed
    await c.addInitScript(consent);
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.waitForTimeout(500);
    ok((await p.locator('.reveal').count()) > 0 && (await p.evaluate(() => document.documentElement.dataset.motion)) === undefined, 'animations on by default');
    await p.click('#a11y-toggle');
    await p.waitForSelector('.swal2-popup');
    ok((await p.getAttribute('[data-motion-switch]', 'aria-checked')) === 'true' && (await text(p, '[data-motion-state]')) === 'เปิด', 'dialog: animations switch is on');
    await p.click('[data-motion-switch]');
    ok((await p.evaluate(() => document.documentElement.dataset.motion)) === '1', 'switch off -> data-motion=1');
    ok((await p.getAttribute('[data-motion-switch]', 'aria-checked')) === 'false' && (await text(p, '[data-motion-state]')) === 'ปิด', 'switch shows off');
    await p.keyboard.press('Escape');
    await p.reload();
    ok((await p.evaluate(() => document.documentElement.dataset.motion)) === '1', 'choice persisted');
    ok((await p.evaluate(() => getComputedStyle(document.querySelector('.reveal')).opacity)) === '1', 'content visible immediately with animations off');
    await p.mouse.move(400, 300, { steps: 3 });
    ok(await p.evaluate(() => !document.documentElement.classList.contains('has-cursor')), 'no custom cursor with animations off');
    await c.close();
    // site default off (Customizer)
    const c2 = await br.newContext({ viewport: { width: 1280, height: 800 } });
    await c2.addInitScript(consent);
    await c2.addCookies([{ name: 'mod_stub', value: JSON.stringify({ mcupr_anim_default: false }), url: B }]);
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.evaluate(() => document.documentElement.dataset.motion)) === '1', 'Customizer default off -> animations off for new visitors');
    await p2.click('#a11y-toggle');
    await p2.waitForSelector('.swal2-popup');
    ok((await p2.getAttribute('[data-motion-switch]', 'aria-checked')) === 'false', 'switch reflects the site default (off)');
    await p2.click('[data-motion-switch]');
    ok((await p2.evaluate(() => document.documentElement.dataset.motion)) === '0', 'visitor can turn animations on');
    await p2.keyboard.press('Escape');
    await p2.reload();
    ok((await p2.evaluate(() => document.documentElement.dataset.motion)) === '0', 'visitor choice beats the site default after reload');
    await c2.close();
  }

  // ================= listen (text-to-speech)
  {
    const c = await newCtx();
    await c.addInitScript(speechStub(120));
    const p = await c.newPage();
    await p.goto(B + post(1));
    const play = p.locator('[data-listen-play]');
    ok(await play.isVisible(), 'listen button visible');
    await play.click();
    await p.waitForFunction(() => window.__spoken.length >= 1);
    const first = await p.evaluate(() => window.__spoken[0]);
    ok(first.text.includes('ข่าวทดสอบ 1') && first.lang === 'th-TH', 'reads the title first in th-TH: ' + JSON.stringify(first));
    ok((await text(p, '[data-listen-play-label]')) === 'หยุดชั่วคราว' && (await p.getAttribute('[data-listen-play]', 'aria-pressed')) === 'true', 'button becomes Pause');
    ok(await p.locator('[data-listen-stop]').isVisible(), 'stop button appears');
    await p.waitForFunction(() => document.querySelector('.is-speaking'));
    ok((await text(p, '[data-listen-status]')) === 'กำลังอ่านออกเสียง', 'status announced');
    // pause
    const before = await p.evaluate(() => window.__spoken.length);
    await play.click();
    ok((await text(p, '[data-listen-play-label]')) === 'เล่นต่อ', 'paused: button says Resume');
    await p.waitForTimeout(400);
    ok((await p.evaluate(() => window.__spoken.length)) <= before, 'no more speech while paused');
    // resume + speed
    await p.selectOption('[data-listen-rate]', '1.5');
    await play.click();
    await p.waitForFunction((n) => window.__spoken.length > n, before);
    ok((await p.evaluate(() => window.__spoken[window.__spoken.length - 1].rate)) === 1.5, 'speed 1.5× applied');
    // stop
    await p.click('[data-listen-stop]');
    ok((await p.locator('.is-speaking').count()) === 0 && (await text(p, '[data-listen-play-label]')) === 'ฟังเนื้อหานี้', 'stop clears highlight and resets');
    // play to the end
    await play.click();
    await p.waitForFunction(() => document.querySelector('[data-listen-status]').textContent === 'อ่านจบแล้ว', null, { timeout: 15000 });
    ok(true, 'reads to the end and announces completion');
    const all = await p.evaluate(() => window.__spoken.map((s) => s.text).join(' '));
    ok(all.includes('หัวข้อรอง') && all.includes('พุทธศักราช') && all.includes('รายการที่สอง') && all.includes('คำพูดสำคัญ'), 'headings, paragraphs, lists and quotes are read');
    await p.screenshot({ path: shot('listen.png') });
    // long paragraphs are chunked
    await p.goto(B + post(5));
    await p.click('[data-listen-play]');
    await p.waitForFunction(() => window.__spoken.length >= 6);
    const lens = await p.evaluate(() => window.__spoken.map((s) => s.text.length));
    ok(Math.max(...lens) <= 180, 'long text split into chunks <= 180 chars (max ' + Math.max(...lens) + ')');
    await p.click('[data-listen-stop]');
    // English language content
    await p.goto(B + post(1) + '?lang=en');
    await p.click('[data-listen-play]');
    await p.waitForFunction(() => window.__spoken.length >= 1);
    ok((await p.evaluate(() => window.__spoken[0].lang)) === 'en-US', 'speech language follows the page language (en-US)');
    await c.close();

    // untranslated content keeps its own language (lang attr on content)
    const c2 = await newCtx();
    await c2.addInitScript(speechStub(30));
    const p2 = await c2.newPage();
    await p2.goto(B + post(2) + '?lang=ja');
    await p2.click('[data-listen-play]');
    await p2.waitForFunction(() => window.__spoken.length >= 1);
    ok((await p2.evaluate(() => window.__spoken[0].lang)) === 'th-TH', 'content in Thai fallback is read with the Thai voice even on the Japanese page');
    await c2.close();

    // unsupported browser
    const c3 = await newCtx();
    await c3.addInitScript(() => { Object.defineProperty(window, 'speechSynthesis', { configurable: true, value: undefined }); window.SpeechSynthesisUtterance = undefined; });
    const p3 = await c3.newPage();
    await p3.goto(B + post(1));
    ok(!(await p3.locator('[data-listen-play]').isVisible()) && (await text(p3, '[data-listen-status]')).includes('ไม่รองรับ'), 'unsupported browser: button hidden, message shown');
    await c3.close();

    // disabled in the Customizer
    const c4 = await newCtx({}, { mcupr_listen: false });
    const p4 = await c4.newPage();
    await p4.goto(B + post(1));
    ok((await p4.locator('[data-listen]').count()) === 0, 'listen tool can be switched off in the Customizer');
    await c4.close();

    // axe with the listen tool in the page + while reading
    const c5 = await newCtx();
    await c5.addInitScript(speechStub(1500)); // long enough that the highlighted block is reliably catchable on a loaded machine
    const p5 = await c5.newPage();
    for (const n of [1, 3, 4]) {
      await p5.goto(B + post(n));
      await p5.click('[data-listen-play]');
      await p5.waitForSelector('.is-speaking');
      await p5.addScriptTag({ content: axeSource() });
      const r = await p5.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
      ok(r.violations.length === 0, `axe news layout ${n} while reading: ${r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'}`);
      r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    }
    await c5.close();
  }

  // ================= Widget areas
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await p.locator('[data-area=home-after] .widget').count()) === 1, 'widgets: home area renders its widget');
    ok((await p.locator('footer [data-area=footer-1] .widget').count()) === 1, 'widgets: footer column 1 renders');
    ok((await p.locator('[data-area=footer-2], [data-area=footer-3]').count()) === 0, 'widgets: empty areas print nothing');
    await p.goto(B + '/about/');
    ok((await p.locator('aside [data-area=sidebar-page] .widget').count()) === 1, 'widgets: page sidebar renders beside the section list');
    await p.goto(B + post(4));
    ok((await p.locator('aside [data-area=sidebar-news] .widget').count()) === 3, 'widgets: news sidebar renders search + text + categories');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with widgets: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await c.close();
  }

  // ================= Shortcode slots, theme shortcodes, built-in SEO
  {
    const c = await newCtx({}, {
      mcupr_slot_header_after: '<p id="s-head">[mcupr_button url="/x/" style="outline"]Go[/mcupr_button]</p>',
      mcupr_slot_home_top: '[mcupr_news count="2" columns="2"]',
      mcupr_slot_content_after: '<p id="s-after">after</p>',
      mcupr_slot_footer_before: '<p id="s-foot">before footer</p>',
    });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await p.locator('[data-slot=header_after] #s-head a[href="/x/"]').count()) === 1, 'slot: shortcode [mcupr_button] runs below the header');
    ok((await p.locator('[data-slot=home_top] article').count()) === 2, 'slot: [mcupr_news count=2] prints 2 cards on the home page');
    ok((await p.locator('[data-slot=footer_before] #s-foot').count()) === 1, 'slot: above-footer HTML printed');
    ok((await p.locator('[data-slot=content_after]').count()) === 0, 'slot: article slot not shown on the home page');
    await p.goto(B + post(2));
    ok((await p.locator('[data-slot=content_after] #s-after').count()) === 1, 'slot: after-article HTML printed on a post');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with shortcode slots: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    await c.close();

    const c2 = await newCtx();
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.locator('.mcupr-slot').count()) === 0, 'slots: empty by default print nothing');
    ok((await p2.locator('meta[property="og:title"]').count()) === 1, 'seo: Open Graph title');
    await p2.goto(B + post(2));
    ok((await p2.getAttribute('meta[property="og:type"]', 'content')) === 'article', 'seo: article og:type');
    ok((await p2.getAttribute('meta[name=description]', 'content')) === 'สรุปข่าว 2', 'seo: description comes from the excerpt');
    await p2.goto(B + '/?s=test');
    ok(/noindex/.test((await p2.getAttribute('meta[name=robots]', 'content')) || ''), 'search results are noindex (WordPress core)');
    await c2.close();
  }

  // ================= Certify Hub polish: soft cards, status cards, pill tabs, stats, footer card
  {
    const c = await newCtx({}, { mcupr_slot_home_top: '[mcupr_stats items="users|12,500|นักศึกษา"]' });
    const p = await c.newPage();
    await p.goto(B + '/');
    ok((await p.locator('[data-slot=home_top] .stat').count()) === 1 && /12,500/.test(await text(p, '[data-slot=home_top] .stat b')), 'stats: [mcupr_stats] prints round-icon number tiles');
    const card = await p.locator('#content article.soft-card').first().evaluate((el) => { const s = getComputedStyle(el); return [s.borderTopColor, s.borderRadius, s.boxShadow !== 'none']; });
    ok(parseFloat(card[1]) >= 20 && card[2] && /rgba\(0, 0, 0, 0\)|transparent/.test(card[0]), 'cards: borderless, rounded, soft shadow (light mode) ' + card);
    ok((await p.locator('#colophon .soft-card').count()) === 1, 'footer: one rounded card');
    await p.goto(B + '/category/' + encodeURIComponent('ข่าวประชาสัมพันธ์') + '/');
    ok(!/Category:/.test(await text(p, 'main h1')), 'archive title has no English "Category:" prefix');
    ok((await p.locator('.pill-tabs [aria-current]').count()) === 1, 'category chips: pill tab group with the current one marked');
    await p.goto(B + '/no-such-page-xyz/');
    ok((await p.locator('.state-card .st-ic svg').count()) === 1 && (await p.locator('.state-card h1').count()) === 1, '404: centered status card with round icon');
    await p.goto(B + '/?s=zzzzqqqq');
    ok((await p.locator('.state-card h2').count()) === 1, 'no results: status card');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe on the status card page: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    await c.close();
    const d = await newCtx({ colorScheme: 'dark' });
    const pd = await d.newPage();
    await pd.goto(B + '/');
    ok((await pd.locator('#content article.soft-card').first().evaluate((el) => getComputedStyle(el).borderTopColor)) !== 'rgba(0, 0, 0, 0)', 'dark mode: cards get a hairline edge instead of a shadow');
    await d.close();
  }

  // ================= Single article + comments polish
  {
    const c = await newCtx();
    const p = await c.newPage();
    const external = [];
    p.on('request', (r) => { if (!r.url().startsWith(B)) external.push(r.url()); });
    await p.goto(B + post(1));
    ok((await p.locator('#comments.soft-card').count()) === 1 && (await p.locator('#comments-title').count()) === 1, 'comments: soft card with heading');
    ok(/\(2\)/.test(await text(p, '#comments-title')), 'comments: count shown in the heading');
    ok((await p.locator('.comment-list > li > .comment-card .comment-bubble').count()) === 1 && (await p.locator('.comment-list .children .comment-bubble').count()) === 1, 'comments: bubble cards, reply nested one level');
    ok((await p.locator('.comment-list .stat-ic').count()) === 2 && (await p.locator('.comment-list img').count()) === 0, 'comments: initial badges, no Gravatar <img>');
    ok(/ตอบกลับ/.test(await text(p, '.comment-list')) && /ส่งความคิดเห็น/.test(await p.inputValue('#submit').catch(() => '')) , 'comments: Thai labels (reply, submit)');
    const fieldRadius = await p.locator('#comment').evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius));
    ok(fieldRadius >= 16 && (await p.locator('#author').evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius))) >= 14, 'comment form: rounded fields');
    ok((await p.locator('#submit').evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius))) >= 20, 'comment form: pill submit button');
    ok((await p.locator('label[for=comment]').count()) === 1 && (await p.locator('label[for=author]').count()) === 1 && (await p.locator('label[for=email]').count()) === 1, 'comment form: every field has a label');
    ok((await p.locator('article .pill-tabs a, article .pill-tabs button').count()) >= 4, 'article tools: capsule toolbar');
    ok((await p.locator('nav a.soft-card[rel=next], nav a.soft-card[rel=prev]').count()) >= 1, 'post navigation: soft cards');
    ok((await p.locator('article header svg').count()) >= 2, 'article meta: date and reading-time icons');
    ok(external.every((u) => !/gravatar/.test(u)), 'no request to gravatar.com (PDPA)');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe on an article with comments: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await p.goto(B + post(1) + '?lang=en');
    ok(/Leave a comment/.test(await text(p, '#reply-title')) && /Comments/.test(await text(p, '#comments-title')), 'comments: English heading and form title');
    ok((await p.getAttribute('#author', 'autocomplete')) === 'name' && /Name/.test(await text(p, 'label[for=author]')), 'comment form: English labels, autocomplete="name"');
    await c.close();
    const d = await newCtx({ colorScheme: 'dark' });
    const pd = await d.newPage();
    await pd.goto(B + post(1));
    await pd.addScriptTag({ content: axeSource() });
    const rd = await pd.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa'] }));
    ok(rd.violations.length === 0, 'axe (dark) article with comments: ' + (rd.violations.map((v) => v.id).join(', ') || '0 violations'));
    await d.close();
  }

  // ================= Settings panel groups + page polish
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/about/');
    ok((await p.locator('ul[aria-label] a.soft-card').count()) === 3, 'page: section root shows its 3 sub-pages as tiles');
    ok((await p.locator('aside .side-tabs a[aria-current]').count()) === 1 && (await p.locator('aside .side-tabs a[aria-current]').evaluate((el) => getComputedStyle(el).borderRadius)) !== '0px', 'page: "in this section" list is a pill menu with the current page highlighted');
    ok((await p.locator('.pill-tabs [data-print]').count()) === 1, 'page: print button inside the capsule toolbar');
    await p.goto(B + '/about-2/');
    ok((await p.locator('ul[aria-label] a.soft-card').count()) === 0, 'page: child pages show no tile grid');
    await p.click('#a11y-toggle'); await p.waitForSelector('.swal2-popup');
    ok((await p.locator('.swal2-popup .settings-group').count()) === 3, 'settings: three groups (display, text, accessibility)');
    ok((await p.locator('.swal2-popup .settings-group h3.eyebrow').allTextContents()).join('|') === 'การแสดงผล|ตัวอักษร|การเข้าถึง', 'settings: group labels in Thai');
    ok((await p.locator('.swal2-popup .settings-group [data-pref=menu]').count()) === 3 && (await p.locator('.swal2-popup .settings-group [data-font-level]').count()) === 5 && (await p.locator('.swal2-popup .settings-group [data-contrast-switch]').count()) === 1, 'settings: every control sits in its group');
    ok((await p.locator('.swal2-popup').evaluate((el) => parseFloat(getComputedStyle(el).borderTopLeftRadius))) >= 24, 'settings: dialog has large rounded corners');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document.querySelector('.swal2-popup'), { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe on the grouped settings dialog: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    await c.close();
  }

  // ================= Home page polish
  {
    const c = await newCtx({ viewport: { width: 1280, height: 900 } });
    const p = await c.newPage();
    await p.goto(B + '/');
    const tops = await p.locator('#news-slider > article').evaluateAll((els) => els.map((e) => Math.round(e.getBoundingClientRect().top)));
    ok(tops.length >= 3 && new Set(tops).size === 1, 'slider: cards sit in one horizontal row ' + tops.join(','));
    ok(await p.locator('#news-slider').evaluate((el) => el.scrollWidth > el.clientWidth && getComputedStyle(el).overflowX !== 'visible'), 'slider: track scrolls horizontally');
    await p.click('[data-carousel-next]');
    await p.waitForTimeout(700);
    ok((await p.locator('#news-slider').evaluate((el) => el.scrollLeft)) > 100, 'slider: next button scrolls the track');
    ok((await p.locator('#featured-title .stat-ic').count()) === 1 && (await p.locator('#news-title .stat-ic').count()) === 1, 'sections: headings have round icons');
    ok((await p.locator('#news-title').locator('xpath=ancestor::section//nav[@aria-label]//li').count()) >= 2, 'news: category pill tabs next to the heading');
    ok((await p.locator('[aria-labelledby=news-title] article').first().evaluate((el) => el.classList.contains('min-[900px]:col-span-2'))), 'news: first card is the large featured one');
    const cta = await p.locator('section[aria-label] a.rounded-full').last().evaluate((a) => { const s = getComputedStyle(a.closest('div')); return [s.backgroundColor, s.color]; });
    ok(cta[0] !== 'rgba(0, 0, 0, 0)' && cta[0] !== cta[1], 'banner: solid brand colour with contrasting text ' + cta);
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe on the polished home page: ' + (r.violations.map((v) => v.id + '(' + v.nodes.length + ')').join(', ') || '0 violations'));
    r.violations.forEach((v) => console.log('   ', v.id, v.nodes.slice(0, 2).map((x) => x.target.join(' ')).join(' || ')));
    await c.close();
  }

  // ================= University seal watermark replaces the wheel ornament
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/about/');
    const w = await p.locator('header.mcu-wheel').evaluate((el) => { const cs = getComputedStyle(el, '::before'); return { mask: cs.webkitMaskImage || cs.maskImage, bg: cs.backgroundColor, op: cs.opacity, display: cs.display }; });
    ok(/mcu-seal\.svg/.test(w.mask) && w.display === 'block', 'band watermark is the university seal (assets/img/mcu-seal.svg)');
    ok(w.bg === 'rgb(194, 24, 91)' && parseFloat(w.op) <= 0.25, 'seal watermark: one colour (the main pink), faint ' + w.bg + ' ' + w.op);
    const res = await p.request.get(B + '/wp-content/themes/mcupr/assets/img/mcu-seal.svg');
    ok(res.ok() && (res.headers()['content-type'] || '').includes('svg'), 'seal file is served as image/svg+xml');
    await p.goto(B + '/about/');
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with the seal watermark: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    await p.emulateMedia({ media: 'print' });
    ok((await p.locator('header.mcu-wheel').evaluate((el) => getComputedStyle(el, '::before').display)) === 'none', 'seal watermark hidden when printing');
    await c.close();
    const c2 = await newCtx({}, null);
    await c2.addInitScript(() => localStorage.setItem('mcupr_prefs', JSON.stringify({ contrast: 'wb' })));
    const p2 = await c2.newPage();
    await p2.goto(B + '/about/');
    ok((await p2.locator('header.mcu-wheel').evaluate((el) => getComputedStyle(el, '::before').display)) === 'none', 'seal watermark hidden in high-contrast mode');
    await c2.close();
  }

  // ================= Default logo = the university emblem
  {
    const c = await newCtx();
    const p = await c.newPage();
    await p.goto(B + '/');
    const img = p.locator('#masthead .brand img');
    ok((await img.count()) === 1 && (await img.getAttribute('src')).endsWith('/assets/img/mcu-logo.svg') && (await img.getAttribute('alt')) === '', 'header: emblem is the main logo (decorative, the name sits beside it)');
    await p.waitForFunction(() => document.querySelector('#masthead .brand img').complete && document.querySelector('#masthead .brand img').naturalWidth > 0);
    const box = await img.boundingBox();
    ok(box.height >= 40 && Math.abs(box.width / box.height - 0.743) < 0.02, 'emblem keeps its proportions (' + Math.round(box.width) + 'x' + Math.round(box.height) + ')');
    ok(await img.isVisible(), 'emblem visible in light mode');
    ok((await p.locator('#colophon img[src$="mcu-logo.svg"]').count()) === 1, 'footer: emblem too');
    ok((await p.locator('link[rel=icon][href$="mcu-logo.svg"]').count()) === 1, 'browser-tab icon: the emblem until a Site Icon is set');
    await p.evaluate(() => { document.documentElement.setAttribute('data-theme', 'dark'); });
    ok(await p.locator('#masthead .brand img').isVisible(), 'emblem visible in dark mode (no dark variant needed)');
    await p.evaluate(() => { document.documentElement.setAttribute('data-theme', 'light'); });
    await p.addScriptTag({ content: axeSource() });
    const r = await p.evaluate(() => axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa', 'best-practice'] }));
    ok(r.violations.length === 0, 'axe with the default logo: ' + (r.violations.map((v) => v.id).join(', ') || '0 violations'));
    await c.close();
    const c2 = await newCtx({}, { custom_logo: 'ATT' });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.locator('#masthead .brand img[src$="mcu-logo.svg"]').count()) === 0 && (await p2.locator('#masthead .brand img').count()) === 1, 'an uploaded logo replaces the emblem');
    await c2.close();
  }

  // ================= Review fixes: language cookie header, canonical og:url
  {
    const c = await newCtx();
    const p = await c.newPage();
    const res = await p.goto(B + '/?lang=en');
    const sc = (await res.headersArray()).filter((h) => h.name.toLowerCase() === 'set-cookie' && /mcupr_lang/.test(h.value)).map((h) => h.value).join(' | ');
    ok(/mcupr_lang=en/.test(sc) && /samesite=lax/i.test(sc) && /httponly/i.test(sc), 'language cookie: value, HttpOnly, SameSite=Lax ' + sc.slice(0, 60));
    await p.goto(B + '/category/' + encodeURIComponent('ข่าวประชาสัมพันธ์') + '/');
    const og = await p.getAttribute('meta[property="og:url"]', 'content');
    ok(og.startsWith(B + '/category/') && !og.includes('/category/category/'), 'og:url of a category is its own URL once: ' + og);
    await p.goto(B + '/');
    ok((await p.getAttribute('meta[property="og:url"]', 'content')) === B + '/', 'og:url of the home page is the site root');
    await c.close();
  }

  await br.close();
  finish();
})();
