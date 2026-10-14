// Chrome's own main-thread counters for a page (style recalc / layout / script), no throttling.
//   node perf/metrics.js <url> [cookie]
const { chromium } = require('playwright');
(async () => {
  const url = process.argv[2];
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
  const c = await b.newContext({ viewport: { width: 412, height: 823 }, deviceScaleFactor: 1.75, isMobile: true });
  if (process.argv[3]) await c.addCookies([{ name: 'mod_stub', value: encodeURIComponent(process.argv[3]), url }]);
  const p = await c.newPage();
  const cdp = await c.newCDPSession(p);
  await cdp.send('Performance.enable');
  await p.goto(url, { waitUntil: 'load' });
  await p.waitForTimeout(800);
  const m = Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((x) => [x.name, x.value]));
  const pick = ['RecalcStyleCount', 'RecalcStyleDuration', 'LayoutCount', 'LayoutDuration', 'ScriptDuration', 'TaskDuration', 'Nodes'];
  console.log(pick.map((k) => k + '=' + (/Duration/.test(k) ? Math.round(m[k] * 1000) + 'ms' : m[k])).join('  '));
  await b.close();
})();
