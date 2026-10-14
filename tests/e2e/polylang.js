// Polylang integration (uses fixtures/mu-plugins/polylang-stub.php)
const { B, ok, launch, finish } = require('./lib');
(async()=>{ const br=await launch();
for (const slug of ['en','fr']) {
  const c=await br.newContext({viewport:{width:1280,height:900},reducedMotion:'reduce'}); await c.addInitScript(()=>localStorage.setItem('mcupr_consent','1')); await c.addCookies([{name:'pll_stub',value:slug,url:B}]);
  const p=await c.newPage(); const errs=[]; p.on('pageerror',e=>errs.push(e.message));
  await p.goto(B+'/?lang=th'); // ?lang must be ignored when Polylang is active
  ok((await p.getAttribute('#a11y-toggle','aria-label'))==='Accessibility helper',`[${slug}] UI strings in English (stub language ${slug}, ?lang ignored)`);
  await p.click('#lang-toggle'); await p.waitForSelector('.swal2-popup');
  const links=await p.$$eval('.swal2-html-container a',a=>a.map(x=>x.textContent.trim()+'>'+x.getAttribute('href')));
  ok(links.join(',')===`TH-name>${B}/th/,EN-name>${B}/en/,FR-name>${B}/fr/`,`[${slug}] switcher uses Polylang languages/URLs: ${links.join(', ')}`);
  ok((await p.$$eval('.swal2-html-container a[aria-current]',a=>a.map(x=>x.textContent.trim()))).join()===slug.toUpperCase()+'-name',`[${slug}] current language marked`);
  ok((await p.textContent('#lang-toggle')).trim()===slug.toUpperCase(),`[${slug}] language badge from Polylang`);
  ok(await p.locator('link[rel=alternate][hreflang]').count()===0,`[${slug}] theme prints no hreflang (Polylang does)`);
  ok(await p.getAttribute('a[rel=home]','href')===`${B}/${slug}/`,`[${slug}] brand links to language home`);
  await p.keyboard.press('Escape'); await p.click('#search-toggle'); await p.waitForSelector('.swal2-popup');
  ok((await p.getAttribute('[data-search-form]','action'))===`${B}/${slug}/`,`[${slug}] search dialog posts to language home`);
  ok((await c.cookies()).every(x=>x.name!=='mcupr_lang'),`[${slug}] no theme language cookie`);
  ok(errs.length===0,'no JS errors');
  await c.close();
}
await br.close(); finish();})();
