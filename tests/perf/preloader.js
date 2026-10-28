// What the loading screen (the logo that covers a page while it loads) costs in Core Web Vitals and in the time a visitor
// really waits to see the page. Compares three settings of Customizer → Display "Loading screen with the logo":
//   off | session (first page of a visit only) | every (every page and between pages)
// on a phone with a slow connection and a slow processor (Lighthouse-like), and without throttling.
//   BASE_URL=http://localhost:8090 CHROMIUM_PATH=... node perf/preloader.js [runs]
//
// Why not just read LCP: Chrome counts a picture as painted even when the screen covers it, so LCP alone says the screen costs nothing.
// What the visitor sees is the page when the screen has gone, so "visible" below = the later of LCP and the moment the screen left.
const { chromium } = require('playwright');

const B = process.env.BASE_URL || 'http://localhost:8080';
const RUNS = parseInt(process.argv[2] || '5', 10);
const PAGES = [['home', '/'], ['news post', '/?name=fx-media'], ['notices', '/notices/']];
const MODES = ['off', 'session', 'every'];
const PROFILES = {
  'slow phone (4x CPU, 1.6 Mbit/s, 150 ms)': { cpu: 4, net: { latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (0.75 * 1024 * 1024) / 8 } },
  'no throttling': { cpu: 1, net: null },
};
const median = (a) => { const s = a.slice().sort((x, y) => x - y); return s.length ? s[Math.floor(s.length / 2)] : NaN; };
const f = (n) => (isFinite(n) ? Math.round(n) : '–');

const probe = () => {
  window.__m = { lcp: 0, lcpEl: '', cls: 0, fcp: 0, tbt: 0, plDone: 0, inp: 0 };
  try { localStorage.setItem('mcupr_preload_test', '1'); } catch (e) { /* */ }
  new PerformanceObserver((l) => { for (const e of l.getEntries()) { window.__m.lcp = e.startTime; window.__m.lcpEl = (e.element && (e.element.tagName + (e.element.className ? '.' + String(e.element.className).split(' ')[0] : ''))) || ''; } }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) { if (!e.hadRecentInput) { window.__m.cls += e.value; } } }).observe({ type: 'layout-shift', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) { if (e.name === 'first-contentful-paint') { window.__m.fcp = e.startTime; } } }).observe({ type: 'paint', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) { if (e.startTime > 0) { window.__m.tbt += Math.max(0, e.duration - 50); } } }).observe({ type: 'longtask', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) { if (e.interactionId) { window.__m.inp = Math.max(window.__m.inp, e.duration); } } }).observe({ type: 'event', durationThreshold: 16, buffered: true });
  const note = () => {
    const d = document.documentElement;
    if (!d) { return; }
    if (d.classList.contains('pl-on') || d.classList.contains('pl-out')) { window.__m.shown = true; }
    if (d.classList.contains('pl-done') && !window.__m.plDone) { window.__m.plDone = performance.now(); }
  };
  new MutationObserver(note).observe(document, { attributes: true, subtree: true, attributeFilter: ['class'] });
  note();
};

(async () => {
  const br = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
  const rows = [];
  { const w = await br.newPage(); for (const [, path] of PAGES) { await w.goto(B + path, { waitUntil: 'load' }).catch(() => {}); } await w.close(); } // the server's own first request is slow: not what is measured
  for (const [pname, prof] of Object.entries(PROFILES)) {
    console.log('\n=== ' + pname + ' – median of ' + RUNS + ' cold loads ===');
    console.log('page'.padEnd(11) + 'setting'.padEnd(9) + 'FCP'.padStart(6) + 'LCP'.padStart(7) + 'visible'.padStart(9) + 'CLS'.padStart(7) + 'TBT'.padStart(6) + 'INP'.padStart(6) + '   LCP element');
    for (const [name, path] of PAGES) {
      for (const mode of MODES) {
        const runs = [];
        for (let i = 0; i < RUNS; i++) {
          const c = await br.newContext({ viewport: { width: 412, height: 823 }, deviceScaleFactor: 1.75, isMobile: true });
          await c.addCookies([{ name: 'mod_stub', value: encodeURIComponent(JSON.stringify({ mcupr_preload: mode })), url: B }]);
          const p = await c.newPage();
          await p.addInitScript(probe);
          const cdp = await c.newCDPSession(p);
          await cdp.send('Network.enable');
          await cdp.send('Network.emulateNetworkConditions', prof.net ? Object.assign({ offline: false }, prof.net) : { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
          await cdp.send('Emulation.setCPUThrottlingRate', { rate: prof.cpu });
          try {
            if (mode === 'session') { await p.goto(B + '/notices/', { waitUntil: 'load' }); await p.waitForTimeout(500); } // a visit that already began: the screen is not shown again
            await p.goto(B + path, { waitUntil: 'load', timeout: 90000 });
            await p.waitForFunction(() => !window.__m.shown ? document.readyState === 'complete' : window.__m.plDone > 0, null, { timeout: 15000 }).catch(() => {});
            await p.waitForTimeout(1200);
            await p.mouse.click(206, 700).catch(() => {}); // one tap, for the input delay
            await p.waitForTimeout(400);
            const m = await p.evaluate(() => window.__m);
            m.visible = Math.max(m.lcp, m.shown ? m.plDone : 0); // when the screen was shown, the page is seen when it has gone
            runs.push(m);
          } catch (e) { /* a failed load is left out */ }
          await c.close();
        }
        const g = (k) => median(runs.map((r) => r[k]));
        const el = runs.map((r) => r.lcpEl).sort((a, b) => runs.filter((r) => r.lcpEl === b).length - runs.filter((r) => r.lcpEl === a).length)[0] || '';
        const row = { profile: pname, page: name, mode, fcp: g('fcp'), lcp: g('lcp'), visible: g('visible'), cls: g('cls'), tbt: g('tbt'), inp: g('inp'), el, n: runs.length };
        rows.push(row);
        console.log(name.padEnd(11) + mode.padEnd(9) + String(f(row.fcp)).padStart(6) + String(f(row.lcp)).padStart(7) + String(f(row.visible)).padStart(9) + row.cls.toFixed(3).padStart(7) + String(f(row.tbt)).padStart(6) + String(f(row.inp)).padStart(6) + '   ' + el + (runs.length < RUNS ? '  (' + runs.length + ' runs)' : ''));
      }
    }
  }
  require('fs').writeFileSync(process.env.OUT || '/dev/null', JSON.stringify(rows, null, 1));
  await br.close();
})();
