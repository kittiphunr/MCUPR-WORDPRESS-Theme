// Polylang integration (uses fixtures/mu-plugins/polylang-stub.php)
const { B, ok, launch, finish } = require('./lib');
(async()=>{ const br=await launch();
for (const slug of ['en','fr']) {
  const c=await br.newContext({viewport:{width:1280,height:900}}); await c.addInitScript(()=>localStorage.setItem('mcupr_consent','1')); await c.addCookies([{name:'pll_stub',value:slug,url:B}]);
  const p=await c.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto(B+'/?lang=th'); // ?lang must be ignored when Polylang is active
  ok((await p.textContent('#a11y-toggle')).trim()==='Accessibility',`[${slug}] UI strings in English (stub language ${slug}, ?lang ignored)`);
  const links=await p.$$eval('nav[aria-label=Language] a',a=>a.map(x=>x.textContent.trim()+'>'+x.getAttribute('href')));
  ok(links.join(',')===`TH-name>${B}/th/,EN-name>${B}/en/,FR-name>${B}/fr/`,`[${slug}] switcher uses Polylang languages/URLs: ${links.join(', ')}`);
  ok((await p.$$eval('nav[aria-label=Language] a[aria-current]',a=>a.map(x=>x.textContent.trim()))).join()===slug.toUpperCase()+'-name',`[${slug}] current language marked`);
  ok(await p.locator('link[rel=alternate][hreflang]').count()===0,`[${slug}] theme prints no hreflang (Polylang does)`);
  ok(await p.getAttribute('a[rel=home]','href')===`${B}/${slug}/`,`[${slug}] brand links to language home`);
  ok((await p.getAttribute('form[role=search]','action'))===`${B}/${slug}/`,`[${slug}] search form posts to language home`);
  ok((await c.cookies()).every(x=>x.name!=='mcupr_lang'),`[${slug}] no theme language cookie`);
  ok(errs.length===0,'no JS errors');
  await c.close();
}
await br.close(); finish();})();
