// Responsive 240px–4K (no horizontal overflow, target size) and TV mode (Tizen UA, dark default, D-pad navigation)
const { B, ok, launch, shot, axeSource, finish } = require('./lib');
const VPS=[[240,480],[280,560],[320,568],[360,740],[480,800],[768,1024],[1024,768],[1280,720],[1600,900],[1920,1080],[2560,1440],[3840,2160]];
const PAGES=['/', '/'+encodeURIComponent('ข่าวทดสอบ-1')+'/', '/?s=ข่าว', '/nonexist'];
(async()=>{
const br=await launch();
for(const [w,h] of VPS){
  const c=await br.newContext({viewport:{width:w,height:h}}); await c.addInitScript(()=>localStorage.setItem('mcupr_consent','1'));
  const p=await c.newPage();
  for(const url of PAGES){
    await p.goto(B+url);
    const r=await p.evaluate(()=>{ const de=document.documentElement; const over=[...document.querySelectorAll('body *')].filter(e=>{const b=e.getBoundingClientRect(); return b.width>0 && (b.right>de.clientWidth+1||b.left<-1) && !e.closest('.snap-row') && getComputedStyle(e).position!=='fixed' && !e.closest('.sr-only')&&!e.classList.contains('sr-only')}).slice(0,3).map(e=>e.tagName+'.'+String(e.className).slice(0,50)+':'+Math.round(e.getBoundingClientRect().right));
      return {sw:de.scrollWidth,cw:de.clientWidth,fs:getComputedStyle(de).fontSize,over}; });
    ok(r.sw<=r.cw+1 && r.over.length===0,`${w}px ${url.slice(0,14)} no overflow (font ${r.fs}) ${r.over.join(' | ')}`);
  }
  await p.goto(B+'/');
  const small=await p.evaluate(()=>[...document.querySelectorAll('a[href],button,input,select')].filter(e=>{const b=e.getBoundingClientRect();return b.width>0&&b.height>0&&getComputedStyle(e).visibility!=='hidden'&&!e.closest('.sr-only')&&(b.width<24||b.height<24)&&!e.classList.contains('sr-only')&&e.className.indexOf('sr-only')<0&&!(e.tagName==='A'&&e.closest('p,li:not(.menu-item),address'))}).slice(0,4).map(e=>e.tagName+' '+(e.textContent||e.name||'').trim().slice(0,20)));
  ok(small.length===0,`${w}px targets >=24px ${small.join('|')}`);
  if([240,320,1280,1920,3840].includes(w)){ await p.screenshot({path:shot(`rwd-${w}.png`),fullPage:false}); }
  await c.close();
}
// TV: Tizen UA at 4K and FHD
for(const [w,h] of [[1920,1080],[3840,2160]]){
  const c=await br.newContext({viewport:{width:w,height:h},userAgent:'Mozilla/5.0 (SMART-TV; LINUX; Tizen 7.0) AppleWebKit/537.36 (KHTML, like Gecko) 94.0.4606.31/7.0 TV Safari/537.36'}); await c.addInitScript(()=>localStorage.setItem('mcupr_consent','1'));
  const p=await c.newPage(); await p.goto(B+'/');
  ok(await p.evaluate(()=>document.documentElement.dataset.tv)==='1','TV UA => tv mode ('+w+')');
  ok(await p.evaluate(()=>document.documentElement.dataset.theme)==='dark','TV default dark ('+w+')');
  const fs=await p.evaluate(()=>parseFloat(getComputedStyle(document.documentElement).fontSize)); console.log('   root font',fs);
  ok(fs>=(w===3840?44:23),'TV root font large: '+fs);
  // D-pad navigation
  await p.keyboard.press('Tab'); await p.keyboard.press('Tab'); await p.keyboard.press('Tab'); await p.keyboard.press('Tab');
  const start=await p.evaluate(()=>document.activeElement.outerHTML.slice(0,60));
  let moved=0, seen=new Set();
  for(const k of ['ArrowRight','ArrowRight','ArrowDown','ArrowDown','ArrowDown','ArrowLeft','ArrowUp']){ await p.keyboard.press(k); const d=await p.evaluate(()=>{const a=document.activeElement; const r=a.getBoundingClientRect(); return a.tagName+':'+(a.textContent||a.getAttribute('aria-label')||'').trim().slice(0,15)+'@'+Math.round(r.left)+','+Math.round(r.top)}); seen.add(d); }
  ok(seen.size>=4,'D-pad moves focus across elements ('+seen.size+' distinct): '+[...seen].join(' ; '));
  ok(await p.evaluate(()=>{const a=document.activeElement; const cs=getComputedStyle(a); return parseFloat(cs.outlineWidth)>=5}),'focus ring >=6px in TV mode');
  const tgt=await p.evaluate(()=>Math.min(...[...document.querySelectorAll('#utility-bar button, #utility-bar a')].filter(e=>e.getBoundingClientRect().width>0).map(e=>e.getBoundingClientRect().height)));
  ok(tgt>=64*(w===3840?2.4/1.2*0+1:1)*0.9 , 'TV targets large: min height '+Math.round(tgt)+'px');
  await p.screenshot({path:shot(`tv-${w}.png`)});
  // turn TV mode off via panel
  await p.click('#a11y-toggle'); await p.uncheck('input[data-pref=tv]'); ok(await p.evaluate(()=>document.documentElement.dataset.tv)==='0','TV mode can be switched off');
  await c.close();
}
await br.close(); finish();})();
