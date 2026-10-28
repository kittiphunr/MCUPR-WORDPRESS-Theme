// Motion effects: scroll reveal, card tilt, interactive cursor, eased scrolling, scroll progress
// (and that all of them switch off with reduced motion / contrast mode / TV mode).
const { B, ok, launch, shot, finish } = require('./lib');

const consent = () => localStorage.setItem('mcupr_consent', '1');

(async () => {
  const br = await launch();
  const newCtx = async (opts = {}) => {
    const c = await br.newContext({ viewport: { width: 1280, height: 800 }, ...opts });
    await c.addInitScript(consent);
    return c;
  };

  // ---- motion on
  {
    const c = await newCtx();
    const p = await c.newPage();
    const errs = [];
    p.on('pageerror', (e) => errs.push(e.message));
    await p.goto(B + '/');
    await p.waitForTimeout(900);
    const cards = p.locator('article.tilt.reveal');
    ok((await cards.count()) >= 4, 'cards get the reveal + tilt classes');
    const below = p.locator('section[aria-labelledby=news-title] article.tilt').last();
    ok(!(await below.evaluate((el) => el.classList.contains('in'))), 'a card below the fold is not revealed yet');
    ok((await below.evaluate((el) => getComputedStyle(el).opacity)) === '0', 'and is transparent');
    await below.scrollIntoViewIfNeeded();
    await p.waitForTimeout(1500);
    ok(await below.evaluate((el) => el.classList.contains('in')), 'revealed after scrolling into view');
    ok((await below.evaluate((el) => getComputedStyle(el).opacity)) === '1', 'now fully visible');

    // scroll progress bar
    await p.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await p.waitForTimeout(200);
    const prog = await p.evaluate(() => document.getElementById('progress').style.transform);
    ok(/scaleX\(0\.9|scaleX\(1\)/.test(prog), 'scroll progress bar reaches the end: ' + prog);
    ok(!(await p.locator('#back-to-top').evaluate((el) => el.classList.contains('hidden'))), 'back-to-top visible after scrolling');
    const bt = await p.locator('#back-to-top').evaluate((el) => { const r = el.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), parseFloat(getComputedStyle(el).borderTopLeftRadius)]; });
    ok(bt[0] === bt[1] && bt[0] >= 48 && bt[2] >= bt[0] / 2, 'back-to-top is a true circle (' + bt.join(' x ') + ')');

    // eased scroll-to-top
    await p.click('#back-to-top');
    await p.waitForTimeout(300);
    const mid = await p.evaluate(() => window.scrollY);
    await p.waitForTimeout(2200);
    const end = await p.evaluate(() => window.scrollY);
    ok(end === 0 && mid > 0, `eased scroll to top (mid ${Math.round(mid)} -> ${end})`);

    // tilt: move the mouse over a card
    const card = p.locator('section[aria-labelledby=news-title] article.tilt').first();
    await card.scrollIntoViewIfNeeded();
    await p.waitForTimeout(1200);
    // over the card's image link (the first card on the home page is the wide "featured" one, text on the right)
    const box = await card.locator('a').first().boundingBox();
    await p.mouse.move(box.x + box.width * 0.8, box.y + box.height * 0.2, { steps: 4 });
    await p.waitForTimeout(200);
    ok(await card.evaluate((el) => el.classList.contains('tilting')), 'card tilts under the mouse');
    ok((await card.evaluate((el) => el.style.getPropertyValue('--ry'))) !== '', 'tilt angle set');

    // interactive cursor
    ok((await p.locator('.cursor-dot').count()) === 1 && (await p.locator('.cursor-ring').count()) === 1, 'cursor dot + ring created');
    ok(await p.evaluate(() => document.documentElement.classList.contains('cursor-visible')), 'custom cursor visible after mouse movement');
    ok(await p.locator('.cursor-ring.is-view').count() === 1, 'ring becomes a "view" bubble over a clickable card');
    ok(await p.evaluate(() => { const v = document.querySelector('.cursor-ring span svg').getBoundingClientRect(); const r = document.querySelector('.cursor-ring').getBoundingClientRect(); return v.width >= 24 && Math.abs(v.left + v.width / 2 - r.left) < 3 && Math.abs(v.top + v.height / 2 - r.top) < 3; }), 'the eye icon is visible and centred in the bubble');
    ok((await p.locator('.cursor-ring span svg circle').count()) === 1 && (await p.textContent('.cursor-ring span')) === '', 'bubble shows an eye icon instead of the word "view"');
    await p.mouse.move(5, 5);
    await p.hover('#lang-toggle');
    await p.waitForTimeout(150);
    ok((await p.locator('.cursor-ring.is-link').count()) === 1 && (await p.locator('.cursor-ring.is-view').count()) === 0, 'ring enlarges over a button');
    await p.screenshot({ path: shot('effects-cursor.png') });
    ok(errs.length === 0, 'no JS errors ' + JSON.stringify(errs));
    await c.close();
  }

  // ---- everything off with reduced motion
  {
    const c = await newCtx({ reducedMotion: 'reduce' });
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.waitForTimeout(300);
    const below = p.locator('section[aria-labelledby=news-title] article.tilt').last();
    ok((await below.evaluate((el) => getComputedStyle(el).opacity)) === '1', 'reduced motion: content visible at once (no reveal)');
    await p.mouse.move(300, 300, { steps: 3 });
    ok(await p.evaluate(() => !document.documentElement.classList.contains('has-cursor')), 'reduced motion: no custom cursor');
    const card = p.locator('section[aria-labelledby=news-title] article.tilt').first();
    await card.scrollIntoViewIfNeeded();
    const box = await card.boundingBox();
    await p.mouse.move(box.x + 40, box.y + 40, { steps: 3 });
    ok(!(await card.evaluate((el) => el.classList.contains('tilting'))), 'reduced motion: cards do not tilt');
    await c.close();
  }

  // ---- off in high-contrast mode, with the large-cursor option, and in TV mode
  for (const [name, prefs] of [['high contrast', '{"contrast":"wb"}'], ['large cursor option', '{"cursor":"1"}'], ['TV mode', '{"tv":"1"}'], ['"animations off" option', '{"motion":"1"}']]) {
    const c = await newCtx();
    await c.addInitScript((v) => { localStorage.setItem('mcupr_consent', '1'); localStorage.setItem('mcupr_prefs', v); }, prefs);
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.mouse.move(400, 400, { steps: 3 });
    await p.waitForTimeout(200);
    ok(await p.evaluate(() => !document.documentElement.classList.contains('has-cursor')), `${name}: no custom cursor`);
    await c.close();
  }

  // ---- touch device: no cursor, no tilt
  {
    const c = await newCtx({ hasTouch: true, isMobile: true, viewport: { width: 390, height: 800 } });
    const p = await c.newPage();
    await p.goto(B + '/');
    await p.waitForTimeout(300);
    ok((await p.locator('.cursor-dot').count()) === 0, 'touch device: no cursor elements');
    await c.close();
  }

  await br.close();
  finish();
})();
