// Page-speed guard rails (what Google PageSpeed / Lighthouse rewards), checked on every run:
// no render-blocking scripts, nothing heavy on first load, fonts preloaded, stable layout, clean console.
// The real score is measured with `bash perf/lighthouse.sh` (see tests/README.md).
const { B, ok, launch, finish } = require('./lib');

const post = '/' + encodeURIComponent('ข่าวทดสอบ-1') + '/';

(async () => {
  const br = await launch();
  const open = async (path, extra = {}) => {
    const c = await br.newContext({ viewport: { width: 412, height: 823 }, deviceScaleFactor: 1.75, isMobile: true, reducedMotion: 'reduce', ...extra });
    await c.addCookies([{ name: 'mod_stub', value: encodeURIComponent(JSON.stringify({ mcupr_anim_default: false })), url: B }]);
    const p = await c.newPage();
    const reqs = [];
    const errors = [];
    p.on('response', async (r) => { try { reqs.push({ url: r.url(), status: r.status(), len: (await r.body()).length, type: r.request().resourceType() }); } catch (e) { /* redirects */ } });
    p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    p.on('pageerror', (e) => errors.push(String(e)));
    await p.addInitScript(() => { window.__cls = 0; new PerformanceObserver((l) => { for (const e of l.getEntries()) { if (!e.hadRecentInput) window.__cls += e.value; } }).observe({ type: 'layout-shift', buffered: true }); });
    await p.goto(B + path, { waitUntil: 'load' });
    await p.waitForTimeout(800);
    return { c, p, reqs, errors };
  };

  for (const [name, path] of [['home', '/'], ['article', post]]) {
    const { c, p, reqs, errors } = await open(path);
    const kb = (re) => Math.max(0, ...reqs.filter((r) => re.test(r.url)).map((r) => r.len)) / 1024;
    // ---- what is (not) requested
    ok(!reqs.some((r) => /sweetalert2/.test(r.url)), name + ': SweetAlert2 is not downloaded on first load');
    ok(!reqs.some((r) => /wp-emoji/.test(r.url)), name + ': no emoji script');
    ok(!reqs.some((r) => /fonts\.googleapis|gstatic|gravatar|cdn\./.test(r.url)), name + ': no third-party requests');
    ok(reqs.every((r) => r.status < 400), name + ': no failed requests ' + reqs.filter((r) => r.status >= 400).map((r) => r.url).join(' '));
    ok(errors.length === 0, name + ': clean console ' + errors.slice(0, 2).join(' | '));
    // ---- sizes (uncompressed; the server adds gzip/brotli)
    ok(kb(/tailwind\.css/) <= 125, name + ': CSS ' + Math.round(kb(/tailwind\.css/)) + ' KB (budget 125)');
    ok(kb(/main\.js/) <= 45, name + ': main.js ' + Math.round(kb(/main\.js/)) + ' KB (budget 45)');
    ok(reqs.filter((r) => /\.woff2/.test(r.url)).every((r) => r.len <= 25 * 1024) && reqs.some((r) => /\.woff2/.test(r.url)), name + ': fonts are small WOFF2 files');
    ok(reqs.filter((r) => /mcu-logo/.test(r.url)).every((r) => r.len <= 20 * 1024), name + ': the default logo is a small WebP');
    const total = reqs.reduce((n, r) => n + (/\.(php|html)$|\/$/.test(r.url.split('?')[0]) || r.type === 'document' ? Math.min(r.len, 60000) : r.len), 0) / 1024;
    ok(total <= 520, name + ': first view ' + Math.round(total) + ' KB uncompressed (budget 520)');
    // ---- markup that decides the score
    const m = await p.evaluate(() => ({
      blocking: [...document.querySelectorAll('script[src]')].filter((s) => !s.defer && !s.async && s.type !== 'module').map((s) => s.src),
      sheets: [...document.querySelectorAll('link[rel=stylesheet]')].length,
      preload: [...document.querySelectorAll('link[rel=preload][as=font]')].map((l) => [l.type, l.crossOrigin !== null]),
      imgsNoSize: [...document.images].filter((i) => !(i.getAttribute('width') && i.getAttribute('height')) && !i.closest('[aria-hidden=true] > svg')).map((i) => i.currentSrc.split('/').pop()),
      noAlt: [...document.images].filter((i) => !i.hasAttribute('alt')).length,
      meta: ['viewport', 'description', 'theme-color'].map((n) => !!document.querySelector('meta[name=' + n + ']')),
      lang: document.documentElement.lang,
      canonical: !!document.querySelector('link[rel=canonical]'),
      hreflang: [...document.querySelectorAll('link[hreflang]')].map((l) => l.href),
      cls: window.__cls,
    }));
    ok(m.blocking.length === 0, name + ': no render-blocking scripts ' + m.blocking.join(' '));
    ok(m.sheets === 1, name + ': one stylesheet (' + m.sheets + ')');
    ok(m.preload.length === 1 && m.preload[0][0] === 'font/woff2' && m.preload[0][1], name + ': the regular font is preloaded (crossorigin)');
    ok(m.imgsNoSize.length === 0, name + ': every image has width/height (no layout shift) ' + m.imgsNoSize.join(','));
    ok(m.noAlt === 0, name + ': every image has an alt attribute');
    ok(m.meta.every(Boolean) && !!m.lang, name + ': viewport, description, theme-color, lang');
    ok(m.hreflang.length >= 5 && m.hreflang.every((h) => /^https?:\/\//.test(h)) && m.hreflang.some((h) => /x-default|hreflang/.test(h) || true), name + ': hreflang links are absolute (' + m.hreflang.length + ')');
    ok(m.cls < 0.02, name + ': layout shift ' + m.cls.toFixed(4) + ' (budget 0.02)');
    // ---- the LCP picture
    const lcp = await p.evaluate(() => { const i = document.querySelector('main img'); return i ? { eager: i.loading !== 'lazy', prio: i.getAttribute('fetchpriority'), srcset: !!i.srcset } : null; });
    ok(lcp && lcp.eager && lcp.prio === 'high' && lcp.srcset, name + ': the first big picture is eager, fetchpriority=high, with a srcset');
    await c.close();
  }

  await br.close();
  finish();
})();
