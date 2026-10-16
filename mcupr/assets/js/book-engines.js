/* Page-turning engines for the e-book reader that need no library:
     css3d  – leaves of paper turned with CSS 3D (the lightest: no canvas, no script per frame except one leaf)
     webgl  – one canvas; the page bends as it turns, with light, a glossy sheen and a shadow on the page underneath
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
		var m = { mode: 'landscape', L: 0, t: 0, leaf: -1, th: 0, cx: 0.5 };
		var ms = o.reduced ? 1 : 850;

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
		function rest() { m.mode = mode; m.L = leaves(); m.t = t; m.leaf = -1; m.th = 0; m.cx = cxAt(t); }
		function draw() { R.draw(m); }
		function canTurn(dir) { return dir > 0 ? t < (mode === 'landscape' ? leaves() : total - 1) : t > 0; }

		function begin(dir) {
			m.leaf = dir > 0 ? t : t - 1;
			m.th = dir > 0 ? 0 : 1;
			m.cx = cxAt(m.leaf + m.th);
			ev.emit('changeState', 'flipping');
			draw();
		}
		function setTh(th) { m.th = clamp(th, 0, 1); m.cx = cxAt(m.leaf + m.th); draw(); }
		function finish(dir, committed) {
			if (committed) { t += dir > 0 ? 1 : -1; c = cOf(t); }
			rest();
			draw();
			ev.emit('changeState', 'read');
			if (committed) { ev.emit('flip', c); }
		}
		/* animate the turning leaf to its end (committed) or back to its start */
		function run(dir, committed) {
			var from = m.th, to = committed ? (dir > 0 ? 1 : 0) : (dir > 0 ? 0 : 1), t0 = null, dur = Math.max(1, ms * Math.abs(to - from));
			if (anim) { cancelAnimationFrame(anim.id); }
			anim = { id: 0 };
			function step(now) {
				if (t0 === null) { t0 = now; }
				var p = clamp((now - t0) / dur, 0, 1);
				setTh(from + (to - from) * ease(p));
				if (p < 1) { anim.id = requestAnimationFrame(step); } else { anim = null; finish(dir, committed); }
			}
			anim.id = requestAnimationFrame(step);
		}
		function turn(dir) {
			if (anim || drag && drag.started || !canTurn(dir)) { return; }
			begin(dir);
			run(dir, true);
		}

		/* ---- dragging a corner and tapping the sides */
		function onDown(e) {
			if ((e.button && e.button > 0) || anim) { return; }
			var r = host.getBoundingClientRect();
			drag = { x0: e.clientX, y0: e.clientY, xr: (e.clientX - r.left) / r.width, w: r.width, started: false, dir: 0 };
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
				begin(drag.dir);
			}
			var tip = clamp((drag.dir > 0 ? 1 : -1) + dx / pw, -1, 1);
			setTh(Math.acos(tip) / RAD);
			if (e.cancelable) { e.preventDefault(); }
		}
		function onUp(e) {
			document.removeEventListener('pointermove', onMove);
			document.removeEventListener('pointerup', onUp);
			document.removeEventListener('pointercancel', onUp);
			var d = drag; drag = null;
			if (!d) { return; }
			if (d.started) {
				var commit = d.dir > 0 ? m.th > 0.5 : m.th < 0.5;
				run(d.dir, commit);
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
			destroy: function () { if (ro) { ro.disconnect(); } host.removeEventListener('pointerdown', onDown); if (R.destroy) { R.destroy(); } }
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
	var VS = [
		'attribute vec2 aPos;',
		'uniform float uTheta, uC, uH, uViewW, uCx, uD, uZoff, uLeaf;',
		'varying vec2 vUv; varying float vPhi; varying float vX;',
		'void main() {',
		'  float s = aPos.x;',
		'  float x, z;',
		'  if (abs(uC) < 0.0001) { x = s * cos(uTheta); z = s * sin(uTheta); }',
		'  else { x = (sin(uTheta) - sin(uTheta - uC * s)) / uC; z = (cos(uTheta - uC * s) - cos(uTheta)) / uC; }',
		'  z += uZoff;',
		'  vPhi = uTheta - uC * s;',
		'  vX = x;',
		'  vUv = vec2(s, 0.5 - aPos.y);',
		'  float dz = uD - z;',
		'  float qx = (x - uCx) * uD / dz, qy = aPos.y * uH * uD / dz;',
		'  gl_Position = vec4(qx * 2.0 / uViewW, qy * 2.0 / uH, -z * 0.2, 1.0);',
		'}'
	].join('\n');
	var FS = [
		'precision mediump float;',
		'uniform sampler2D uFront, uBack;',
		'uniform float uHasBack, uShade, uSa, uSb, uSi, uPaper;',
		'uniform vec3 uPaperCol;',
		'varying vec2 vUv; varying float vPhi; varying float vX;',
		'void main() {',
		'  bool front = gl_FrontFacing;',
		'  vec3 col = uPaperCol;',
		'  if (front) { col = texture2D(uFront, vUv).rgb; }',
		'  else if (uHasBack > 0.5) { col = texture2D(uBack, vec2(1.0 - vUv.x, vUv.y)).rgb; }',
		'  vec3 n = vec3(-sin(vPhi), 0.0, cos(vPhi));',
		'  if (!front) { n = -n; }',
		'  vec3 l = normalize(vec3(-0.30, 0.35, 0.89));',
		'  float dif = max(dot(n, l), 0.0) / l.z;',
		'  float lit = 0.5 + 0.5 * dif;',
		'  vec3 h = normalize(l + vec3(0.0, 0.0, 1.0));',
		'  float spec = pow(max(dot(n, h), 0.0), 38.0) * 0.10 * (1.0 - uShade);',
		'  col = col * min(lit, 1.06) + spec;',
		'  col *= 1.0 - 0.17 * exp(-abs(vX) * 7.0);',
		'  if (uSi > 0.0) {',
		'    float d = max(max(uSa - vX, vX - uSb), 0.0);',
		'    col *= 1.0 - uSi * exp(-d * d / 0.06);',
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
		var cv = null, gl = null, prog = null, ov = null, buf = null, loc = {}, texs = {}, paper = null, N = 64, dirty = false, raf = 0;
		var pages = [], model = null, opts = null, ratio = 1.4;

		function sh(type, src) {
			var s = gl.createShader(type);
			gl.shaderSource(s, src); gl.compileShader(s);
			if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { throw new Error(gl.getShaderInfoLog(s)); }
			return s;
		}
		function init() {
			gl = cv.getContext('webgl', { antialias: true, alpha: true, premultipliedAlpha: true }) || cv.getContext('experimental-webgl');
			prog = gl.createProgram();
			gl.attachShader(prog, sh(gl.VERTEX_SHADER, VS)); gl.attachShader(prog, sh(gl.FRAGMENT_SHADER, FS));
			gl.linkProgram(prog);
			if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { throw new Error(gl.getProgramInfoLog(prog)); }
			gl.useProgram(prog);
			['uTheta', 'uC', 'uH', 'uViewW', 'uCx', 'uD', 'uZoff', 'uFront', 'uBack', 'uHasBack', 'uShade', 'uSa', 'uSb', 'uSi', 'uPaperCol'].forEach(function (n) { loc[n] = gl.getUniformLocation(prog, n); });
			var v = [], i;
			for (i = 0; i <= N; i++) { v.push(i / N, 0.5, i / N, -0.5); } // top then bottom: a strip that faces the viewer (counter-clockwise)
			var idx = [];
			buf = gl.createBuffer();
			gl.bindBuffer(gl.ARRAY_BUFFER, buf);
			gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(v), gl.STATIC_DRAW);
			var a = gl.getAttribLocation(prog, 'aPos');
			gl.enableVertexAttribArray(a);
			gl.vertexAttribPointer(a, 2, gl.FLOAT, false, 0, 0);
			gl.enable(gl.DEPTH_TEST); gl.depthFunc(gl.LEQUAL);
			gl.disable(gl.CULL_FACE);
			gl.uniform3f(loc.uPaperCol, 0.984, 0.980, 0.965);
			gl.uniform1f(loc.uD, 5.2);
			texs = {};
			paper = tex1x1();
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

		function drawLeaf(j, theta, curl, zoff, shadow) {
			var f = model.mode === 'landscape' ? 2 * j : j, b = model.mode === 'landscape' ? 2 * j + 1 : -1;
			var tf = texFor(f), tb = b >= 0 ? texFor(b) : null;
			gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, tf || paper); gl.uniform1i(loc.uFront, 0);
			gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, tb || paper); gl.uniform1i(loc.uBack, 1);
			gl.uniform1f(loc.uHasBack, tb ? 1 : 0);
			gl.uniform1f(loc.uTheta, theta); gl.uniform1f(loc.uC, curl); gl.uniform1f(loc.uZoff, zoff);
			gl.uniform1f(loc.uShade, 0);
			if (shadow) { gl.uniform1f(loc.uSa, shadow[0]); gl.uniform1f(loc.uSb, shadow[1]); gl.uniform1f(loc.uSi, shadow[2]); } else { gl.uniform1f(loc.uSi, 0); }
			gl.drawArrays(gl.TRIANGLE_STRIP, 0, (N + 1) * 2);
		}

		function render() {
			raf = 0;
			if (!gl || !model || gl.isContextLost()) { return; }
			var m = model, L = m.L, W = cv.width, H = cv.height;
			if (W < 2 || H < 2) { return; }
			gl.viewport(0, 0, W, H);
			gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
			gl.uniform1f(loc.uH, ratio);
			gl.uniform1f(loc.uViewW, m.mode === 'landscape' ? 2 : 1);
			gl.uniform1f(loc.uCx, m.cx);
			var turning = m.leaf >= 0, j = turning ? m.leaf : m.t, shadow = null, theta, curl, ext, a, b, s;
			if (turning) {
				theta = m.th * RAD;
				curl = 0.95 * Math.sin(theta); // the page bends most when it is upright, and is flat again at both ends
				// where the free edge of the turning page is: its shadow falls between the spine and there
				ext = curl < 0.0001 ? Math.cos(theta) : (Math.sin(theta) - Math.sin(theta - curl)) / curl;
				a = Math.min(0, ext); b = Math.max(0, ext);
				s = [a, b, 0.5 * Math.pow(Math.sin(theta), 0.8)];
			}
			// the pages at rest: left (back of the leaf before) and right (front of the leaf after)
			var left = turning ? j - 1 : m.t - 1, right = turning ? j + 1 : m.t;
			if (m.mode === 'landscape' && left >= 0 && left < L) { drawLeaf(left, RAD, 0, 0, s); }
			if (right >= 0 && right < L) { drawLeaf(right, 0, 0, 0, s); }
			if (turning) { drawLeaf(j, theta, curl, 0.004, null); }
			syncOverlay();
		}
		function want() { if (!raf) { raf = requestAnimationFrame(render); } }

		/* the buttons and texts of the open pages are real HTML over the picture (hidden while a page turns) */
		function syncOverlay() {
			if (!ov || !model) { return; }
			var m = model, show = {}, k;
			if (m.leaf < 0) {
				if (m.mode === 'landscape') {
					if (m.t >= 1) { show[2 * m.t - 1] = 'l'; }
					if (2 * m.t < pages.length) { show[2 * m.t] = 'r'; }
				} else { show[m.t] = 'r'; }
			}
			ov.classList.toggle('is-turning', m.leaf >= 0);
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
