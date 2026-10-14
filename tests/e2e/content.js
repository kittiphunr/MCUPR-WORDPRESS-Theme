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
    const o = { primary: cs.getPropertyValue('--primary').trim(), btnBg: getComputedStyle(s).backgroundColor, btnFg: getComputedStyle(s).color, link: getComputedStyle(l).color, bg: getComputedStyle(document.body).backgroundColor, bar: getComputedStyle(document.querySelector('#colophon')).backgroundColor, barFg: getComputedStyle(document.querySelector('#colophon')).color };
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
      const rl = await ratio(p, o.link, o.bg);
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
    ok((await p.locator('#masthead img.logo-light').count()) === 1 && (await p.locator('#masthead img.logo-dark').count()) === 1, 'header: light + dark logo rendered');
    ok(await p.locator('#masthead img.logo-light').isVisible() && !(await p.locator('#masthead img.logo-dark').isVisible()), 'light mode shows the default logo');
    const h = await p.evaluate(() => document.querySelector('#masthead img.logo-light').getBoundingClientRect().height);
    ok(Math.round(h) === 80, 'logo height from Customizer (80px): ' + Math.round(h));
    ok((await p.locator('#colophon img').count()) === 1, 'footer logo shown');
    await p.evaluate(() => localStorage.setItem('mcupr_prefs', '{"theme":"dark"}'));
    await p.reload();
    ok(!(await p.locator('#masthead img.logo-light').isVisible()) && (await p.locator('#masthead img.logo-dark').isVisible()), 'dark mode swaps to the dark logo');
    await c.close();
    const c2 = await newCtx({}, { custom_logo: 'ATT' });
    const p2 = await c2.newPage();
    await p2.goto(B + '/');
    ok((await p2.locator('#masthead img.logo-dark').count()) === 0, 'no dark logo -> single logo only');
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
    await c5.addInitScript(speechStub(400));
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

  await br.close();
  finish();
})();
