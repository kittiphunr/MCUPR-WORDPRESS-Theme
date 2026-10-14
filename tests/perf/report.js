// Detail view of a Lighthouse JSON: node perf/report.js <file.json>
const r = require(require('path').resolve(process.argv[2])); const a = r.audits;
console.log('==', decodeURIComponent(r.finalDisplayedUrl));
const show = (id, n = 6) => { const x = a[id]; if (!x || x.score === 1) return; console.log('--', id, 'score', x.score, x.displayValue || ''); ((x.details && x.details.items) || []).slice(0, n).forEach((i) => console.log('   ', JSON.stringify(i).slice(0, 230))); };
['lcp-breakdown-insight', 'render-blocking-insight', 'image-delivery-insight', 'font-display-insight', 'unused-javascript', 'unused-css-rules', 'forced-reflow-insight', 'long-tasks', 'errors-in-console', 'meta-description', 'hreflang', 'cache-insight', 'cls-culprits-insight', 'third-parties-insight', 'bootup-time', 'mainthread-work-breakdown', 'duplicated-javascript-insight', 'legacy-javascript-insight', 'document-latency-insight'].forEach((id) => show(id));
const nr = a['network-requests'].details.items;
console.log('requests:', nr.length, 'total KB', Math.round(nr.reduce((s, i) => s + i.transferSize, 0) / 1024));
nr.forEach((i) => console.log(' ', String(Math.round(i.transferSize / 1024)).padStart(5), 'KB', String(i.resourceType).padEnd(10), decodeURIComponent(i.url).replace(/^http:\/\/localhost:\d+/, '').slice(0, 90)));
