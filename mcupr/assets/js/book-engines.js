/* Page-turning engines for the e-book reader that need no library:
     css3d  – leaves of paper turned with CSS 3D (the lightest: no canvas, no script per frame except one leaf)
     webgl  – one canvas; the page is held by a corner and rolled over a slanting fold (the back of the page shows), with light, a
              glossy sheen and a shadow on the page underneath; the corner lifts as the mouse nears it and follows the pointer; the paper swings
              and bounces back (a spring, not a timed slide); the thickness of the book (the stack of pages at the edges) and stiff covers
   Both offer the small interface of StPageFlip that the reader uses (flipNext, flipPrev, turnToPage, getCurrentPageIndex,
   getOrientation, on('flip' | 'changeState'), loadFromHTML), plus pageDrawn(i) (a page got its picture). They share one
   "core": the state of the book, the turn animation, dragging a corner and tapping the sides. */
(function () {
	'use strict';

	var E = window.mcuprBookEngines = {};
	var RAD = Math.PI;

	function emitter() {
		var h = {};
		return {
			on: function (n, f) { (h[n] = h[n] || []).push(f); },
			emit: function (n, d) { (h[n] || []).forEach(function (f) { f({ data: d }); }); }
		};
	}
	function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
	function ease(p) { return p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2; }

	/* ------------------------------------------------------------------ the core */
	/* Model given to the renderer: { mode, L (leaves), t (leaves turned at rest), leaf (the leaf turning, -1 if none), th (0..1 of a half turn), cx (centre of the view, in page widths from the spine) }.
	   Landscape: leaf j has page 2j in front and 2j+1 behind. Portrait: leaf j has page j in front only (a single page is seen). */
	function Core(host, o, R) {
		var ev = emitter(), pages = [], total = 0, mode = 'landscape', c = 0, t = 0, anim = null, drag = null, api, ro = null;
		var m = { mode: 'landscape', L: 0, t: 0, leaf: -1, th: 0, cx: 0.5, v: 0, px: 1, py: 0, cy: 0, cover: false, peel: false };
		var H = o.ratio || 1.4; // the height of a page in page widths: the corners of the page are at y = ±H / 2
		var peel = { amt: 0, tgt: 0, dir: 0, cy: 0, id: 0 };
		var SPRING = 64, DAMP = 10.5, REST = 0.3, LIFT = 0.16; // stiffness, damping, how much of its speed the paper keeps when it lands, how far a button turn lifts the corner

		function modeNow() { return host.clientWidth >= 620 ? 'landscape' : 'portrait'; }
		function leaves() { return mode === 'landscape' ? total / 2 : total; }
		function tOf(cc) { return mode === 'landscape' ? (cc + 1) >> 1 : cc; }
		function cOf(tt) { return mode === 'landscape' ? (tt === 0 ? 0 : 2 * tt - 1) : tt; }
		function cxAt(tf) {
			var L = leaves();
			if (mode === 'portrait') { return 0.5; }
			if (tf < 1) { return 0.5 * (1 - tf); }
			if (tf > L - 1) { return -0.5 * (tf - (L - 1)); }
			return 0;
		}
		function rest() { m.mode = mode; m.L = leaves(); m.t = t; m.leaf = -1; m.th = 0; m.cx = cxAt(t); m.v = 0; m.px = 1; m.py = 0; m.cy = 0; m.cover = false; m.peel = false; }
		function coverOf(leaf) { return leaf === 0 || leaf === leaves() - 1; }
		function draw() { R.draw(m); }
		function canTurn(dir) { return dir > 0 ? t < (mode === 'landscape' ? leaves() : total - 1) : t > 0; }

		/* The turning page is held by one corner, which is at m.cy (±H / 2: the bottom or the top) and is taken to the point (m.px, m.py) (page widths from the spine, up is +).
		   The fold is the line half way between where the corner was and where it is now; the part of the page behind the fold is rolled over it.
		   The paper does not stretch: the corner cannot be further from the spine than the page is long. */
		function limit(px, py, cy) {
			var s = cy > 0 ? -1 : 1, u = (py - cy) * s; // u: how far the corner is lifted towards the middle of the page
			px = clamp(px, -1, 1);
			u = clamp(u, Math.max(-0.05, H - Math.sqrt(1 + H * H - px * px)), Math.sqrt(Math.max(0, 1 - px * px)));
			return [px, cy + s * u];
		}
		function setP(px, py, cy) {
			var q = limit(px, py, cy);
			m.px = q[0]; m.py = q[1]; m.cy = cy;
			m.th = Math.acos(q[0]) / RAD;
			m.cx = cxAt(m.leaf + m.th);
			draw();
		}
		function begin(dir, speed, cy) {
			stopPeel();
			m.leaf = dir > 0 ? t : t - 1;
			m.cy = cy === undefined ? -H / 2 : cy;
			m.px = dir > 0 ? 1 : -1; m.py = m.cy; m.th = dir > 0 ? 0 : 1;
			m.cx = cxAt(m.leaf + m.th);
			m.cover = coverOf(m.leaf); m.v = 0;
			ev.emit('turnmeta', { dir: dir, cover: m.cover, speed: speed });
			ev.emit('changeState', 'flipping');
			draw();
		}
		function finish(dir, committed) {
			if (committed) { t += dir > 0 ? 1 : -1; c = cOf(t); }
			rest();
			draw();
			ev.emit('changeState', 'read');
			if (committed) { ev.emit('flip', c); }
		}
		/* the paper is on a spring: it is pulled to where it ends (committed) or back to where it started, with the speed it was let go with;
		   it starts slowly (the finger or the hand has to lift it), and when it lands it bounces back a little. The corner comes back to the
		   edge of the page on the way (a turn from a button lifts it a little, so that the fold is a slant). */
		function run(dir, committed, v0) {
			var target = committed ? (dir > 0 ? 1 : 0) : (dir > 0 ? 0 : 1), th = m.th, v = v0 || 0, last = null, born = null, landed = false, cy = m.cy;
			var sgn = cy > 0 ? -1 : 1, off0 = (m.py - cy) * sgn - LIFT * H * Math.sin(th * RAD); // how far the hand has taken the corner from the way a button would
			if (anim) { cancelAnimationFrame(anim.id); }
			anim = { id: 0 };
			if (o.reduced) { anim = null; finish(dir, committed); return; }
			function step(now) {
				if (last === null) { last = now; born = now; }
				var age = (now - born) / 1000, dt = Math.min((now - last) / 1000, 0.12), ramp = v0 ? 1 : clamp(age / 0.2, 0, 1), n = Math.max(2, Math.ceil(dt * 120)), k; // steps of at most 1/120 s, so that a slow frame rate makes it choppy, not slow
				ramp = ramp * ramp * (3 - 2 * ramp);
				last = now;
				for (k = 0; k < n; k++) {
					v += (SPRING * ramp * (target - th) - DAMP * v) * dt / n;
					th += v * dt / n;
					if (th > 1 || th < 0) {
						var edge = th > 1 ? 1 : 0;
						if (!landed && edge === target) { landed = true; ev.emit('land', { speed: Math.min(Math.abs(v) / 4, 1.5), cover: m.cover, committed: committed }); }
						th = edge + (edge - th) * REST; v = -v * REST;
					}
				}
				m.v = v;
				var u = LIFT * H * Math.sin(th * RAD) + off0 * Math.exp(-6 * age);
				setP(Math.cos(th * RAD), cy + sgn * u, cy);
				if ((Math.abs(target - th) < 0.002 && Math.abs(v) < 0.05) || age > 2.5) { anim = null; if (!landed) { ev.emit('land', { speed: 0.2, cover: m.cover, committed: committed }); } finish(dir, committed); } else { anim.id = requestAnimationFrame(step); }
			}
			anim.id = requestAnimationFrame(step);
		}
		function turn(dir) {
			if (anim || drag && drag.started || !canTurn(dir)) { return; }
			begin(dir, 1);
			run(dir, true, 0);
		}

		/* ---- a corner lifts when the mouse comes near it (a hint that the page can be turned) */
		function stopPeel() {
			if (peel.id) { cancelAnimationFrame(peel.id); peel.id = 0; }
			peel.amt = peel.tgt = 0; peel.dir = 0;
			if (m.peel) { rest(); draw(); }
			host.removeAttribute('data-peel');
		}
		function peelStep() {
			peel.id = 0;
			var d = peel.tgt - peel.amt;
			peel.amt = Math.abs(d) < 0.001 ? peel.tgt : peel.amt + d * 0.22;
			if (peel.amt < 0.002 && peel.tgt === 0) { peel.amt = 0; if (m.peel) { rest(); draw(); } peel.dir = 0; host.removeAttribute('data-peel'); return; }
			var cy = peel.cy > 0 ? H / 2 : -H / 2, sg = cy > 0 ? -1 : 1, dir = peel.dir;
			m.peel = true; m.leaf = dir > 0 ? t : t - 1; m.cover = coverOf(m.leaf); m.v = 0;
			var q = limit(dir * (1 - 0.24 * peel.amt), cy + sg * 0.17 * H * peel.amt, cy);
			m.px = q[0]; m.py = q[1]; m.cy = cy; m.th = Math.acos(q[0]) / RAD; m.cx = cxAt(m.leaf + m.th);
			host.setAttribute('data-peel', '1');
			draw();
			if (peel.amt !== peel.tgt) { peel.id = requestAnimationFrame(peelStep); }
		}
		function onHover(e) {
			if (!R.peel || drag || anim || o.reduced || (e.pointerType && e.pointerType !== 'mouse') || (host.closest && host.closest('.bk-reader.is-zoomed'))) { return; }
			var r = host.getBoundingClientRect(), xr = (e.clientX - r.left) / r.width, yr = (e.clientY - r.top) / r.height;
			var dir = xr >= 0.5 ? 1 : -1, pw = mode === 'landscape' ? 0.5 : 1, edge = dir > 0 ? 1 - xr : xr, zone = 0.17 * pw, vy = Math.abs(yr - 0.5), tgt = 0;
			if (edge >= 0 && edge < zone && vy > 0.26 && canTurn(dir)) { tgt = 0.3 + 0.7 * clamp(1 - edge / zone, 0, 1) * clamp((vy - 0.26) / 0.2, 0, 1); }
			if (peel.amt > 0 && peel.dir !== dir && tgt > 0) { peel.amt = 0; if (m.peel) { rest(); } }
			if (tgt === peel.tgt && Math.abs(peel.cy - (yr - 0.5)) < 0.01) { return; }
			peel.tgt = tgt; peel.dir = tgt > 0 ? dir : peel.dir; peel.cy = yr < 0.5 ? 1 : -1;
			host.style.cursor = tgt > 0 ? 'grab' : '';
			if (!peel.id) { peel.id = requestAnimationFrame(peelStep); }
		}
		function onLeave() { if (peel.tgt || peel.amt) { peel.tgt = 0; host.style.cursor = ''; if (!peel.id) { peel.id = requestAnimationFrame(peelStep); } } }
		host.addEventListener('pointermove', onHover);
		host.addEventListener('pointerleave', onLeave);

		/* ---- dragging a corner and tapping the sides */
		function onDown(e) {
			if ((e.button && e.button > 0) || anim || (host.closest && host.closest('.bk-reader.is-zoomed'))) { return; }
			stopPeel();
			var r = host.getBoundingClientRect(), yr = (e.clientY - r.top) / r.height;
			drag = { x0: e.clientX, y0: e.clientY, xr: (e.clientX - r.left) / r.width, w: r.width, started: false, dir: 0, cy: yr < 0.5 ? H / 2 : -H / 2, hist: [] };
			drag.dir = drag.xr >= 0.5 ? 1 : -1;
			document.addEventListener('pointermove', onMove);
			document.addEventListener('pointerup', onUp);
			document.addEventListener('pointercancel', onUp);
		}
		function onMove(e) {
			if (!drag) { return; }
			var dx = e.clientX - drag.x0, pw = drag.w / (mode === 'landscape' ? 2 : 1);
			if (!drag.started) {
				if (Math.abs(dx) < 8 || (drag.dir > 0 ? dx > 0 : dx < 0) || !canTurn(drag.dir)) { return; }
				drag.started = true;
				begin(drag.dir, 0.6, drag.cy);
			}
			var px = clamp(drag.dir + dx / pw, -1, 1), py = drag.cy - (e.clientY - drag.y0) / pw, now = e.timeStamp || performance.now(), h = drag.hist, th = Math.acos(px) / RAD; // when the pointer moved, not when this code got to run
			h.push({ t: now, th: th });
			while (h.length > 2 && now - h[0].t > 90) { h.shift(); }
			if (h.length > 1 && now - h[0].t >= 30) { m.v = (th - h[0].th) / ((now - h[0].t) / 1000); } // a speed only from a stretch long enough to mean something
			setP(px, py, drag.cy);
			if (e.cancelable) { e.preventDefault(); }
		}
		function onUp(e) {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			document.removeEventListener('pointercancel', onUp);
			var d = drag; drag = null;
			if (!d) { return; }
			if (d.started) {
				var v0 = clamp(m.v, -8, 8), vf = d.dir > 0 ? v0 : -v0; // vf > 0: the paper is moving on towards the other side
				var commit = vf > 1.2 ? true : (vf < -1.2 ? false : (d.dir > 0 ? m.th > 0.5 : m.th < 0.5));
				run(d.dir, commit, v0);
			} else if (e.type === 'pointerup' && Math.abs(e.clientX - d.x0) < 6 && Math.abs(e.clientY - d.y0) < 6) {
				if (d.xr > 0.65) { turn(1); } else if (d.xr < 0.35) { turn(-1); }
			}
		}
		host.addEventListener('pointerdown', onDown);

		/* ---- size and orientation */
		function layout() {
			mode = modeNow();
			if (mode === 'landscape' && c > 0 && c % 2 === 0) { c -= 1; }
			t = tOf(c);
			c = cOf(t);
			rest();
			R.layout(m, pages, o);
			draw();
		}
		function resize() {
			if (!pages.length) { return; }
			if (anim || drag) { return; }
			if (modeNow() !== mode) { layout(); ev.emit('flip', c); } else { R.resize(m); draw(); }
		}

		api = {
			on: ev.on,
			loadFromHTML: function (list) {
				pages = Array.prototype.slice.call(list);
				total = pages.length;
				host.classList.add('bk-fx');
				layout();
				if (window.ResizeObserver) { ro = new ResizeObserver(function () { resize(); }); ro.observe(host); } else { window.addEventListener('resize', resize); }
			},
			flipNext: function () { turn(1); },
			flipPrev: function () { turn(-1); },
			turnToPage: function (i) {
				i = clamp(Math.round(i), 0, total - 1);
				if (mode === 'landscape' && i > 0 && i % 2 === 0) { i -= 1; }
				var nt = tOf(i);
				if (nt === t || anim) { return; }
				t = nt; c = cOf(t);
				rest(); draw();
				ev.emit('flip', c);
			},
			getCurrentPageIndex: function () { return c; },
			getOrientation: function () { return mode; },
			pageDrawn: function (i) { if (R.pageDrawn) { R.pageDrawn(i, m); } },
			destroy: function () { if (ro) { ro.disconnect(); } stopPeel(); host.removeEventListener('pointerdown', onDown); host.removeEventListener('pointermove', onHover); host.removeEventListener('pointerleave', onLeave); if (R.destroy) { R.destroy(); } }
		};
		this.api = api;
		this.getPages = function () { return pages; };
	}

	/* ------------------------------------------------------------------ CSS 3D */
	function CssRenderer(host) {
		var root = null, book = null, cast = null, leafEls = [], last = [], L = 0, pagesRef = [];
		var Z = 0.6;

		function build(m, pages, o) {
			var frag = document.createDocumentFragment(), i, j;
			pages.forEach(function (p) { if (p.parentNode) { p.parentNode.removeChild(p); } p.removeAttribute('style'); });
			host.innerHTML = '';
			pagesRef = pages;
			root = document.createElement('div'); root.className = 'bkc'; root.setAttribute('data-mode', m.mode);
			book = document.createElement('div'); book.className = 'bkc-book';
			L = m.L; leafEls = []; last = [];
			for (j = 0; j < L; j++) {
				var leaf = document.createElement('div'), f = document.createElement('div'), b = document.createElement('div');
				leaf.className = 'bkc-leaf'; f.className = 'bkc-face bkc-front'; b.className = 'bkc-face bkc-back';
				i = m.mode === 'landscape' ? 2 * j : j;
				f.appendChild(pages[i]);
				if (m.mode === 'landscape' && pages[2 * j + 1]) { b.appendChild(pages[2 * j + 1]); }
				leaf.appendChild(f); leaf.appendChild(b);
				book.appendChild(leaf);
				leafEls.push(leaf);
			}
			cast = document.createElement('div'); cast.className = 'bkc-cast';
			book.appendChild(cast);
			root.appendChild(book);
			frag.appendChild(root);
			host.appendChild(frag);
			host.classList.toggle('bk-single', m.mode === 'portrait');
			host.style.setProperty('--bk-arc', String((m.mode === 'portrait' ? 1 : 2) / o.ratio));
		}
		this.layout = build;
		this.resize = function () {};
		this.draw = function (m) {
			if (!book) { return; }
			var j, a, z, vis, act, key, el, turning = m.leaf;
			for (j = 0; j < L; j++) {
				el = leafEls[j];
				if (j === turning) { a = -m.th * 180; z = 3; } else if (j < m.t) { a = -180; z = -(L - 1 - j) * Z; } else { a = 0; z = -j * Z; }
				vis = Math.abs(j - m.t) <= 2 || j === turning;
				act = j === turning || j === m.t || (m.mode === 'landscape' && j === m.t - 1); // only the two open pages take clicks
				key = a + '|' + z + '|' + vis + '|' + act;
				if (last[j] === key) { continue; }
				last[j] = key;
				el.style.pointerEvents = act ? '' : 'none';
				el.style.transform = 'translateZ(' + z + 'px) rotateY(' + a + 'deg)';
				el.style.visibility = vis ? 'visible' : 'hidden';
				el.style.setProperty('--sh', j === turning ? String(Math.sin(m.th * RAD)) : '0');
				el.classList.toggle('is-turning', j === turning);
			}
			if (turning >= 0) {
				cast.style.opacity = String(0.5 * Math.sin(m.th * RAD));
				cast.classList.toggle('on-left', m.mode === 'landscape' && m.th > 0.5);
				cast.style.display = 'block';
			} else { cast.style.display = 'none'; }
			if (m.mode === 'landscape') { book.style.transform = 'translateX(' + (-m.cx * 50) + '%)'; } else { book.style.transform = ''; }
		};
	}

	/* ------------------------------------------------------------------ WebGL */
	var PAD = 0.955, OVER = 1.036; // the pages are a little smaller than the picture, so that the hard covers can stick out around them
	var VS = [
		'attribute vec2 aPos;',
		'uniform vec2 uM, uN;', // the fold: a point on it, and the unit normal that points to the corner that is held
		'uniform float uOn, uR, uH, uViewW, uCx, uD, uZoff, uSc, uPad;',
		'varying vec2 vUv; varying vec3 vN; varying float vX, vY;',
		'void main() {',
		'  vec2 q = vec2(aPos.x, aPos.y * uH);',
		'  float d = uOn > 0.5 ? dot(q - uM, uN) : -1.0;',
		'  vec3 p = vec3(q, 0.0);',
		'  vec3 nrm = vec3(0.0, 0.0, 1.0);',
		'  if (d > 0.0) {', // behind the fold: rolled over a cylinder of radius uR, then flat again, face down, on top
		'    vec2 F = q - d * uN;',
		'    float pr = 3.14159265 * uR;',
		'    if (d < pr) { float ph = d / uR; p = vec3(F + uN * (uR * sin(ph)), uR * (1.0 - cos(ph))); nrm = vec3(-uN * sin(ph), cos(ph)); }',
		'    else { p = vec3(F - uN * (d - pr), 2.0 * uR); nrm = vec3(0.0, 0.0, -1.0); }',
		'  }',
		'  p.xy *= uSc;',
		'  p.z += uZoff;',
		'  vX = p.x; vY = p.y; vN = nrm;',
		'  vUv = vec2(aPos.x, 0.5 - aPos.y);',
		'  float dz = uD - p.z;',
		'  gl_Position = vec4((p.x - uCx) * uD / dz * 2.0 / uViewW * uPad, p.y * uD / dz * 2.0 / uH * uPad, -p.z * 0.2, 1.0);',
		'}'
	].join('\n');
	var FS = [
		'precision mediump float;',
		'uniform sampler2D uFront, uBack;',
		'uniform vec2 uShM, uShN;',
		'uniform float uHasBack, uShade, uShI, uPaper, uCovF, uCovB, uInF, uInB, uOver;',
		'uniform vec3 uPaperCol;',
		'varying vec2 vUv; varying vec3 vN; varying float vX, vY;',
		'void main() {',
		'  bool front = gl_FrontFacing;',
		'  vec3 col = uPaperCol;',
		'  float m = 0.5 * (1.0 - 1.0 / uOver);',
		'  vec2 iv = vec2(vUv.x * uOver, (vUv.y - m) / (1.0 - 2.0 * m));', // the page of the inside of a cover: smaller than the cover, which is the board around it
		'  bool outside = iv.x > 1.0 || iv.y < 0.0 || iv.y > 1.0;',
		'  if (front) { col = (uInF > 0.5) ? (outside ? vec3(0.17, 0.17, 0.2) : texture2D(uFront, iv).rgb) : texture2D(uFront, vUv).rgb; }',
		'  else if (uHasBack > 0.5) { col = (uInB > 0.5) ? (outside ? vec3(0.17, 0.17, 0.2) : texture2D(uBack, vec2(1.0 - iv.x, iv.y)).rgb) : texture2D(uBack, vec2(1.0 - vUv.x, vUv.y)).rgb; }',
		'  vec3 n = normalize(vN);',
		'  if (!front) { n = -n; }',
		'  vec3 l = normalize(vec3(-0.30, 0.35, 0.89));',
		'  float dif = max(dot(n, l), 0.0) / l.z;',
		'  float lit = 0.5 + 0.5 * dif;',
		'  vec3 h = normalize(l + vec3(0.0, 0.0, 1.0));',
		'  float spec = pow(max(dot(n, h), 0.0), 38.0) * 0.10 * (1.0 - uShade);',
		'  col = col * min(lit, 1.06) + spec;',
		'  col *= 1.0 - 0.17 * exp(-abs(vX) * 7.0);',
		'  if ((front ? uCovF : uCovB) > 0.5) {', // a hard cover: a darker rim, a thin edge line and the groove of the hinge
		'    float e = min(min(1.0 - vUv.x, vUv.y), 1.0 - vUv.y);',
		'    col *= 1.0 - 0.26 * smoothstep(0.035, 0.0, e) - 0.34 * smoothstep(0.007, 0.0, e);',
		'    col *= 1.0 - 0.24 * exp(-pow((vUv.x - 0.03) / 0.011, 2.0));',
		'  }',
		'  if (uShI > 0.0) {', // the shadow of the turning page on the page under it: along the fold
		'    float e = dot(vec2(vX, vY) - uShM, uShN);',
		'    col *= 1.0 - uShI * exp(-e * e / 0.02);',
		'  }',
		'  gl_FragColor = vec4(col, 1.0);',
		'}'
	].join('\n');
	/* the board under the pages and the stack of pages along the edges (thickness of the book) */
	var VS2 = [
		'attribute vec3 aP;',
		'uniform float uH, uViewW, uCx, uPad;',
		'varying vec3 vP;',
		'void main() {',
		'  vP = aP;',
		'  gl_Position = vec4((aP.x - uCx) * 2.0 / uViewW * uPad, aP.y * 2.0 * uPad, 0.0, 1.0);',
		'}'
	].join('\n');
	var FS2 = [
		'precision mediump float;',
		'uniform vec3 uCol;',
		'uniform float uAxis;', // -1 plain board, 0 lines along y (a side), 1 lines along x (top, bottom)
		'varying vec3 vP;',
		'void main() {',
		'  vec3 col = uCol;',
		'  if (uAxis < -0.5) {',
		'    col *= 0.9 + 0.1 * smoothstep(0.0, 1.0, vP.z);',
		'  } else {',
		'    float u = uAxis > 0.5 ? vP.y : vP.x;',
		'    float ln = 0.5 + 0.5 * sin(u * 520.0);',
		'    col = mix(col, col * 0.78, ln * 0.7);',
		'    col *= 1.0 - 0.28 * vP.z;', // darker towards the outside edge
		'  }',
		'  gl_FragColor = vec4(col, 1.0);',
		'}'
	].join('\n');

	function glSupported() {
		try {
			var cv = document.createElement('canvas');
			return !!(window.WebGLRenderingContext && (cv.getContext('webgl') || cv.getContext('experimental-webgl')));
		} catch (e) { return false; }
	}

	function GlRenderer(host, core) {
		this.peel = true; // a corner lifts at the mouse, and the page is rolled over a fold that follows the pointer
		var cv = null, gl = null, prog = null, prog2 = null, ov = null, buf = null, ibuf = null, buf2 = null, nIdx = 0, loc = {}, loc2 = {}, texs = {}, paper = null, N = 72, M = 48, dirty = false, raf = 0;
		var pages = [], model = null, opts = null, ratio = 1.4;

		function sh(type, src) {
			var s = gl.createShader(type);
			gl.shaderSource(s, src); gl.compileShader(s);
			if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { throw new Error(gl.getShaderInfoLog(s)); }
			return s;
		}
		function link(vs, fs) {
			var pr = gl.createProgram();
			gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs));
			gl.bindAttribLocation(pr, 0, vs === VS ? 'aPos' : 'aP');
			gl.linkProgram(pr);
			if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) { throw new Error(gl.getProgramInfoLog(pr)); }
			return pr;
		}
		function init() {
			gl = cv.getContext('webgl', { antialias: true, alpha: true, premultipliedAlpha: true }) || cv.getContext('experimental-webgl');
			prog = link(VS, FS); prog2 = link(VS2, FS2);
			gl.useProgram(prog);
			['uM', 'uN', 'uOn', 'uR', 'uH', 'uViewW', 'uCx', 'uD', 'uZoff', 'uSc', 'uPad', 'uFront', 'uBack', 'uHasBack', 'uShade', 'uShM', 'uShN', 'uShI', 'uPaperCol', 'uCovF', 'uCovB', 'uInF', 'uInB', 'uOver'].forEach(function (n) { loc[n] = gl.getUniformLocation(prog, n); });
			['uH', 'uViewW', 'uCx', 'uPad', 'uCol', 'uAxis'].forEach(function (n) { loc2[n] = gl.getUniformLocation(prog2, n); });
			var v = [], idx = [], i, r;
			for (r = 0; r <= M; r++) { for (i = 0; i <= N; i++) { v.push(i / N, 0.5 - r / M); } } // a grid, so that the top and the bottom of a page can turn by different angles
			for (r = 0; r < M; r++) {
				for (i = 0; i < N; i++) {
					var p00 = r * (N + 1) + i, p10 = p00 + 1, p01 = p00 + N + 1, p11 = p01 + 1;
					idx.push(p00, p01, p10, p10, p01, p11); // counter-clockwise: the front of the page faces the viewer
				}
			}
			nIdx = idx.length;
			buf = gl.createBuffer();
			gl.bindBuffer(gl.ARRAY_BUFFER, buf);
			gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(v), gl.STATIC_DRAW);
			ibuf = gl.createBuffer();
			gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ibuf);
			gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, new Uint16Array(idx), gl.STATIC_DRAW);
			buf2 = gl.createBuffer();
			gl.enableVertexAttribArray(0);
			gl.disable(gl.CULL_FACE);
			gl.uniform3f(loc.uPaperCol, 0.984, 0.980, 0.965);
			gl.uniform1f(loc.uD, 5.2);
			gl.uniform1f(loc.uPad, PAD); gl.uniform1f(loc.uOver, OVER);
			texs = {};
			paper = tex1x1();
		}
		function useMain() {
			gl.useProgram(prog);
			gl.bindBuffer(gl.ARRAY_BUFFER, buf); gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ibuf);
			gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);
			gl.enable(gl.DEPTH_TEST); gl.depthFunc(gl.LEQUAL);
		}
		/* a flat rectangle of the board / stack: x0..x1 in page widths from the spine, y0..y1 in page heights, l = 0 inside .. 1 outside */
		function rect(x0, y0, x1, y1, col, axis, outerFirst) {
			var a = outerFirst ? 1 : 0, b = 1 - a;
			var v = new Float32Array([x0, y0, a, x1, y0, b, x0, y1, a, x1, y0, b, x1, y1, b, x0, y1, a]);
			gl.bufferData(gl.ARRAY_BUFFER, v, gl.STREAM_DRAW);
			gl.uniform3f(loc2.uCol, col[0], col[1], col[2]); gl.uniform1f(loc2.uAxis, axis);
			gl.drawArrays(gl.TRIANGLES, 0, 6);
		}
		function tex1x1() {
			var t = gl.createTexture();
			gl.bindTexture(gl.TEXTURE_2D, t);
			gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([251, 250, 246, 255]));
			param();
			return t;
		}
		function param() {
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
			gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
		}
		/* the picture of a page: the canvas / image the reader drew in it, or a plain sheet (a media page: a tinted sheet) */
		function sourceOf(i) {
			var p = pages[i];
			if (!p) { return null; }
			var s = p.querySelector('.bk-cv');
			if (s && (s.tagName === 'CANVAS' || (s.tagName === 'IMG' && s.complete && s.naturalWidth))) { return s; }
			return null;
		}
		function mediaCanvas() {
			var c2 = document.createElement('canvas'), x;
			c2.width = 64; c2.height = 64;
			x = c2.getContext('2d');
			var g = x.createLinearGradient(0, 0, 64, 64);
			g.addColorStop(0, '#fff7fb'); g.addColorStop(0.55, '#fbfaf6'); g.addColorStop(1, '#f3e8ff');
			x.fillStyle = g; x.fillRect(0, 0, 64, 64);
			return c2;
		}
		var mediaTex = null;
		function texFor(i) {
			if (i < 0 || i >= pages.length) { return null; }
			var e = texs[i], p = pages[i];
			if (p.classList.contains('bk-mpage')) {
				if (!mediaTex) { mediaTex = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, mediaTex); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, mediaCanvas()); param(); }
				return mediaTex;
			}
			if (e && !e.dirty) { return e.tex; }
			var src = sourceOf(i);
			if (!src) { return e ? e.tex : paper; }
			if (!e) { e = texs[i] = { tex: gl.createTexture(), dirty: true }; trim(i); }
			gl.bindTexture(gl.TEXTURE_2D, e.tex);
			try { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, src); } catch (err) { return paper; }
			param();
			e.dirty = false;
			return e.tex;
		}
		/* keep only the textures near the open page (memory) */
		function trim(near) {
			var keys = Object.keys(texs);
			if (keys.length <= 14) { return; }
			keys.sort(function (a, b) { return Math.abs(b - near) - Math.abs(a - near); });
			keys.slice(0, keys.length - 14).forEach(function (k) { gl.deleteTexture(texs[k].tex); delete texs[k]; });
		}

		/* fold: null for a page lying flat, 'flip' for a page turned over completely, else { M, N, R } (see foldOf); shadow: { M, N, I } of the turning page, for the pages under it */
		function drawLeaf(j, fold, zoff, shadow, cover) {
			var land = model.mode === 'landscape', f = land ? 2 * j : j, b = land ? 2 * j + 1 : -1;
			var tf = texFor(f), tb = b >= 0 ? texFor(b) : null, last = j === model.L - 1;
			gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, tf || paper); gl.uniform1i(loc.uFront, 0);
			gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, tb || paper); gl.uniform1i(loc.uBack, 1);
			gl.uniform1f(loc.uHasBack, tb ? 1 : 0);
			if (!fold) { gl.uniform1f(loc.uOn, 0); gl.uniform1f(loc.uR, 0.0001); gl.uniform2f(loc.uM, 0, 0); gl.uniform2f(loc.uN, 1, 0); }
			else if (fold === 'flip') { gl.uniform1f(loc.uOn, 1); gl.uniform1f(loc.uR, 0.0001); gl.uniform2f(loc.uM, 0, 0); gl.uniform2f(loc.uN, 1, 0); } // the fold is the spine: the page lies mirrored on the other side
			else { gl.uniform1f(loc.uOn, 1); gl.uniform1f(loc.uR, fold.R); gl.uniform2f(loc.uM, fold.M[0], fold.M[1]); gl.uniform2f(loc.uN, fold.N[0], fold.N[1]); }
			gl.uniform1f(loc.uZoff, zoff);
			gl.uniform1f(loc.uSc, cover ? OVER : 1);
			gl.uniform1f(loc.uCovF, j === 0 || (!land && last) ? 1 : 0); gl.uniform1f(loc.uCovB, land && last ? 1 : 0);
			gl.uniform1f(loc.uInF, land && last && j !== 0 ? 1 : 0); gl.uniform1f(loc.uInB, land && j === 0 && !last ? 1 : 0);
			gl.uniform1f(loc.uShade, 0);
			if (shadow) { gl.uniform2f(loc.uShM, shadow.M[0], shadow.M[1]); gl.uniform2f(loc.uShN, shadow.N[0], shadow.N[1]); gl.uniform1f(loc.uShI, shadow.I); } else { gl.uniform1f(loc.uShI, 0); }
			gl.drawElements(gl.TRIANGLES, nIdx, gl.UNSIGNED_SHORT, 0);
		}
		/* The fold of the page that is held by the corner (1, m.cy) and taken to (m.px, m.py): half way between the two, square to the way the corner went;
		   the paper is rolled over a cylinder (the radius grows as the corner leaves its place and shrinks to nothing as the page lands flat on the other side,
		   is bigger for a faster swing and for a hard cover, which does not roll tightly). The fold is moved by half the roll so that the corner arrives exactly at the pointer. */
		function foldOf(m) {
			var cx = 1, cyy = m.cy, dx = cx - m.px, dy = cyy - m.py, dist = Math.sqrt(dx * dx + dy * dy);
			if (dist < 0.0005) { return null; }
			var nx = dx / dist, ny = dy / dist;
			var rad = 0.1 * clamp(dist / 0.3, 0, 1) * clamp((2 - dist) / 0.3, 0, 1) * (m.cover ? 1.7 : 1) * (1 + 0.3 * Math.min(Math.abs(m.v) / 3, 1)) + 0.0001;
			var mx = (cx + m.px) / 2 - nx * Math.PI * rad / 2, my = (cyy + m.py) / 2 - ny * Math.PI * rad / 2;
			return { M: [mx, my], N: [nx, ny], R: rad, I: 0.5 * clamp(dist / 0.3, 0, 1) * clamp((2 - dist) / 0.4, 0, 1) };
		}
		/* the thickness of the book: the board that carries the pages (the back cover) and the pages stacked along the edges, each side
		   as thick as the number of pages that are on it */
		function drawBlock(m, lc, rc) {
			var L = m.L, tmax = Math.min(0.03, 0.006 + 0.0006 * L), BOARD = [0.17, 0.17, 0.2], PAPER = [0.94, 0.93, 0.89], sd, cnt, w, h, o = OVER, hv = 0.5 * OVER;
			gl.disable(gl.DEPTH_TEST);
			gl.useProgram(prog2);
			gl.bindBuffer(gl.ARRAY_BUFFER, buf2);
			gl.vertexAttribPointer(0, 3, gl.FLOAT, false, 0, 0);
			gl.uniform1f(loc2.uH, ratio); gl.uniform1f(loc2.uViewW, m.mode === 'landscape' ? 2 : 1); gl.uniform1f(loc2.uCx, m.cx); gl.uniform1f(loc2.uPad, PAD);
			for (sd = -1; sd <= 1; sd += 2) {
				cnt = sd > 0 ? rc : lc;
				if (cnt <= 0 || (sd < 0 && m.mode !== 'landscape')) { continue; }
				w = Math.max(0.006, tmax * cnt / Math.max(1, L)); h = Math.min(w * 0.6, hv - 0.5);
				rect(sd > 0 ? 0 : -o, -hv, sd > 0 ? o : 0, hv, BOARD, -1, false);
				rect(sd > 0 ? 1 : -1 - w, -0.5, sd > 0 ? 1 + w : -1, 0.5, PAPER, 0, sd < 0);
				rect(sd > 0 ? 0 : -1, 0.5, sd > 0 ? 1 : 0, 0.5 + h, PAPER, 1, false);
				rect(sd > 0 ? 0 : -1, -0.5 - h, sd > 0 ? 1 : 0, -0.5, PAPER, 1, true);
			}
			useMain();
		}

		function render() {
			raf = 0;
			if (!gl || !model || gl.isContextLost()) { return; }
			var m = model, L = m.L, W = cv.width, H = cv.height;
			if (W < 2 || H < 2) { return; }
			gl.viewport(0, 0, W, H);
			gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
			var turning = m.leaf >= 0, j = turning ? m.leaf : m.t, fold = null, shadow = null;
			useMain();
			gl.uniform1f(loc.uH, ratio); gl.uniform1f(loc.uViewW, m.mode === 'landscape' ? 2 : 1); gl.uniform1f(loc.uCx, m.cx);
			drawBlock(m, turning ? j : m.t, L - (turning ? j + 1 : m.t));
			if (turning) {
				fold = foldOf(m);
				if (fold) { shadow = { M: fold.M, N: fold.N, I: fold.I }; }
			}
			// the pages at rest: left (back of the leaf before, turned over) and right (front of the leaf after)
			var left = turning ? j - 1 : m.t - 1, right = turning ? j + 1 : m.t;
			var cvr = function (i) { return i === 0 || i === L - 1; };
			if (m.mode === 'landscape' && left >= 0 && left < L) { drawLeaf(left, 'flip', 0, shadow, cvr(left)); }
			if (right >= 0 && right < L) { drawLeaf(right, null, 0, shadow, cvr(right)); }
			if (turning) { drawLeaf(j, fold, 0.004, null, m.cover); }
			syncOverlay();
		}
		function want() { if (!raf) { raf = requestAnimationFrame(render); } }

		/* the buttons and texts of the open pages are real HTML over the picture (hidden while a page turns) */
		function syncOverlay() {
			if (!ov || !model) { return; }
			var m = model, show = {}, k;
			if (m.leaf < 0 || m.peel) {
				if (m.mode === 'landscape') {
					if (m.t >= 1) { show[2 * m.t - 1] = 'l'; }
					if (2 * m.t < pages.length) { show[2 * m.t] = 'r'; }
				} else { show[m.t] = 'r'; }
			}
			ov.classList.toggle('is-turning', m.leaf >= 0 && !m.peel);
			for (k = 0; k < pages.length; k++) {
				var p = pages[k], side = show[k];
				p.style.visibility = side ? 'visible' : 'hidden';
				p.style.left = m.mode === 'portrait' ? '0' : (side === 'l' ? '0' : '50%');
				p.style.width = m.mode === 'portrait' ? '100%' : '50%';
			}
		}

		this.layout = function (m, list, o) {
			pages = list; opts = o; ratio = o.ratio || 1.4; model = m;
			if (!cv) {
				host.innerHTML = '';
				cv = document.createElement('canvas'); cv.className = 'bkg-cv'; cv.setAttribute('aria-hidden', 'true');
				host.appendChild(cv);
				ov = document.createElement('div'); ov.className = 'bkg-ov';
				host.appendChild(ov);
				cv.addEventListener('webglcontextlost', function (e) { e.preventDefault(); });
				cv.addEventListener('webglcontextrestored', function () { try { init(); } catch (err) { /* stays blank */ } want(); });
				init();
			}
			pages.forEach(function (p) { if (p.parentNode !== ov) { if (p.parentNode) { p.parentNode.removeChild(p); } p.removeAttribute('style'); ov.appendChild(p); } });
			host.classList.add('bk-gl');
			host.classList.toggle('bk-single', m.mode === 'portrait');
			host.style.setProperty('--bk-arc', String((m.mode === 'portrait' ? 1 : 2) / ratio));
			this.resize(m);
		};
		this.resize = function (m) {
			model = m || model;
			if (!cv) { return; }
			var dpr = Math.min(window.devicePixelRatio || 1, 2), w = Math.round(host.clientWidth * dpr), h = Math.round(host.clientHeight * dpr);
			if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; }
			want();
		};
		this.draw = function (m) { model = m; want(); syncOverlay(); };
		this.pageDrawn = function (i) {
			if (texs[i]) { texs[i].dirty = true; }
			want();
		};
		this.destroy = function () { if (raf) { cancelAnimationFrame(raf); } };
	}

	/* ------------------------------------------------------------------ public */
	E.supported = { css3d: true, webgl: glSupported() };
	/* make an engine on the book element (null for WebGL when it cannot start, so that the reader falls back) */
	E.create = function (name, host, o) {
		o = o || {};
		if (name === 'webgl') {
			if (!E.supported.webgl) { return null; }
			try {
				var core = null, r = new GlRenderer(host);
				core = new Core(host, o, r);
				return core.api;
			} catch (e) { if (window.console) { console.warn('mcupr e-book: WebGL did not start', e); } return null; }
		}
		return new Core(host, o, new CssRenderer(host)).api;
	};
})();
