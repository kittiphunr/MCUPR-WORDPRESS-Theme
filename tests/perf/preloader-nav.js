// Moving from page to page (a click on a link of the site): how long from the click until the next page can be seen, with the
// loading screen on "every page" and off.   BASE_URL=... CHROMIUM_PATH=... node perf/preloader-nav.js [runs]
const { chromium } = require('playwright');
const B = process.env.BASE_URL || 'http://localhost:8080';
const RUNS = parseInt(process.argv[2] || '7', 10);
const median = (a) => { const s = a.slice().sort((x, y) => x - y); return s[Math.floor(s.length / 2)]; };
(async () => {
  const br = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  for (const [pname, cpu] of [['no throttling', 1], ['slow phone CPU (4x)', 4]]) {
    for (const mode of ['off', 'every']) {
      const t = [];
      for (let i = 0; i < RUNS; i++) {
        const c = await br.newContext({ viewport: { width: 412, height: 823 }, isMobile: true });
        await c.addCookies([{ name: 'mod_stub', value: encodeURIComponent(JSON.stringify({ mcupr_preload: mode })), url: B }]);
        await c.addInitScript(() => { try { localStorage.setItem('mcupr_preload_test', '1'); localStorage.setItem('mcupr_consent', '1'); } catch (e) { /* */ } });
        const p = await c.newPage();
        const cdp = await c.newCDPSession(p);
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: cpu });
        await p.goto(B + '/', { waitUntil: 'load' });
        await p.waitForTimeout(2200);
        const href = await p.evaluate(() => { const a = [...document.querySelectorAll('a[href]')].find((x) => x.origin === location.origin && x.pathname !== '/' && !x.hash && !/wp-admin|wp-login|\.(pdf|zip)/.test(x.href) && x.getBoundingClientRect().width > 0); return a ? a.href : ''; });
        if (!href) { await c.close(); continue; }
        const t0 = Date.now();
        await p.evaluate((h) => { const a = [...document.querySelectorAll('a[href]')].find((x) => x.href === h); a.scrollIntoView(); a.click(); }, href);
        await p.waitForURL((u) => u.href !== B + '/', { timeout: 20000, waitUntil: 'commit' }).catch(() => {});
        await p.waitForFunction(() => { const d = document.documentElement; return document.readyState !== 'loading' && !d.classList.contains('pl-on') && !d.classList.contains('pl-out') && !d.classList.contains('pl-leave'); }, null, { timeout: 20000, polling: 20 }).catch(() => {});
        t.push(Date.now() - t0);
        await c.close();
      }
      console.log(pname.padEnd(22) + mode.padEnd(7) + 'click → page visible (median of ' + t.length + '): ' + median(t) + ' ms');
    }
  }
  await br.close();
})();
