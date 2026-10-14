// Elementor Pro Theme Builder integration (uses fixtures/mu-plugins/elementor-stub.php) + Elementor-built page
const { B, ok, launch, shot, axeSource, finish } = require('./lib');
(async()=>{
const br=await launch(); 
async function ctxWith(el){const c=await br.newContext({viewport:{width:1280,height:900},reducedMotion:'reduce'}); await c.addInitScript(()=>localStorage.setItem('mcupr_consent','1')); if(el) await c.addCookies([{name:'el_stub',value:el,url:B}]); return c;}
// 1. header + footer replaced
let c=await ctxWith('header,footer'); let p=await c.newPage(); await p.goto(B+'/');
ok(await p.locator('#el-header').count()===1,'Elementor header rendered');
ok(await p.locator('#masthead').count()===0,'default header skipped');
ok(await p.locator('#el-footer').count()===1 && await p.locator('#colophon').count()===0,'Elementor footer replaces default');
ok(await p.isVisible('#a11y-toggle') && await p.isVisible('#lang-toggle') && await p.isVisible('#search-toggle'),'fallback bar keeps the Aa / language / search buttons');
ok(await p.locator('[data-skip-nav]').count()===0,'skip-to-menu removed when no #site-nav');
await p.click('#a11y-toggle'); await p.waitForSelector('.swal2-popup'); await p.click('[data-font-level="2"]'); ok(await p.evaluate(()=>getComputedStyle(document.documentElement).fontSize)==='20px','text size works with Elementor header');
await p.check('input[data-pref=theme][value=dark]',{force:true}); ok(await p.evaluate(()=>document.documentElement.dataset.theme)==='dark','dark mode works with Elementor header');
await c.close();
// 2. single location
c=await ctxWith('single'); p=await c.newPage();
await p.goto(B+'/'+encodeURIComponent('ข่าวทดสอบ-3')+'/'); ok(await p.locator('#el-single').count()===1 && await p.locator('article').count()===0,'Elementor single template replaces post');
await p.goto(B+'/nonexist'); ok(await p.locator('#el-single').count()===1,'404 uses single location');
await c.close();
c=await ctxWith('archive'); p=await c.newPage(); await p.goto(B+'/?s=ข่าว'); ok(await p.locator('#el-archive').count()===1,'search uses archive location'); await c.close();
// 3. Elementor-built page: full width content, no theme title
c=await ctxWith(''); p=await c.newPage(); await p.goto(B+'/el-page/');
ok(await p.locator('#el-content').count()===1 && await p.locator('h1').count()===0,'Elementor-built page: content only'); 
// 4. no Elementor: defaults
await p.goto(B+'/'); ok(await p.locator('#masthead').count()===1 && await p.locator('#colophon').count()===1,'defaults without Elementor templates');
ok(await p.locator('#site-nav').count()===1,'default nav present');
await p.screenshot({path:shot('shot-chula-home.png'),fullPage:true});
await p.goto(B+'/'+encodeURIComponent('ข่าวทดสอบ-3')+'/'); await p.screenshot({path:shot('shot-chula-single.png')});
const m=await br.newContext({viewport:{width:390,height:844},reducedMotion:'reduce'}); await m.addInitScript(()=>localStorage.setItem('mcupr_consent','1')); const mp=await m.newPage(); await mp.goto(B+'/');
ok(await mp.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal scroll mobile'); await mp.screenshot({path:shot('shot-chula-mobile.png')});
await mp.evaluate(()=>scrollTo(0,1200)); await mp.waitForTimeout(200); ok(await mp.isVisible('#back-to-top'),'back-to-top appears'); await mp.click('#back-to-top'); await mp.waitForTimeout(600); ok(await mp.evaluate(()=>scrollY)<5,'back-to-top scrolls up');
await br.close(); finish();})();
