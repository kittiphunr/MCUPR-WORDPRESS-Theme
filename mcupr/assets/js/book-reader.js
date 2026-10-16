/* E-book reader: a 3D flip-book (StPageFlip) over the pages of a PDF (PDF.js) or a set of pictures, with a page-turn
   sound made by the browser (Web Audio: no sound file). Built for keyboards and screen readers too:
   arrow keys / Home / End turn pages, the text of the open pages is available to screen readers, and with
   "reduce motion" on the pages change at once. */
(function () {
	'use strict';

	var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------------------------------------------------------------- the page-turn sound */
	var ctx = null;
	function audio() {
		if (!ctx) {
			var AC = window.AudioContext || window.webkitAudioContext;
			if (!AC) { return null; }
			try { ctx = new AC(); } catch (e) { return null; }
		}
		if (ctx.state === 'suspended' && ctx.resume) { ctx.resume(); }
		return ctx;
	}
	/* a rustle (filtered noise that rises and fades) and, as the page lands, a soft thud */
	function turnSound() {
		var c = audio();
		if (!c) { return false; }
		var t = c.currentTime, dur = 0.34 + Math.random() * 0.08, len = Math.floor(c.sampleRate * dur);
		var buf = c.createBuffer(1, len, c.sampleRate), d = buf.getChannelData(0), i;
		for (i = 0; i < len; i++) { d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / len, 1.4); }
		var src = c.createBufferSource(); src.buffer = buf;
		var bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 0.7;
		bp.frequency.setValueAtTime(800, t); bp.frequency.exponentialRampToValueAtTime(3400, t + dur * 0.6);
		var hp = c.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 350;
		var g = c.createGain();
		g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.55, t + 0.04); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
		src.connect(bp); bp.connect(hp); hp.connect(g); g.connect(c.destination); src.start(t);
		var o = c.createOscillator(), og = c.createGain(), t2 = t + dur * 0.78;
		o.type = 'sine'; o.frequency.setValueAtTime(120, t2); o.frequency.exponentialRampToValueAtTime(55, t2 + 0.14);
		og.gain.setValueAtTime(0.0001, t2); og.gain.exponentialRampToValueAtTime(0.2, t2 + 0.03); og.gain.exponentialRampToValueAtTime(0.0001, t2 + 0.16);
		o.connect(og); og.connect(c.destination); o.start(t2); o.stop(t2 + 0.18);
		return true;
	}

	function store(key, val) { try { if (val === undefined) { return window.localStorage.getItem(key); } window.localStorage.setItem(key, val); } catch (e) { /* private mode */ } return null; }
	function fmt(s, a, b) { return String(s).replace('%1$d', a).replace('%2$d', b); }
	function $(sel, el) { return (el || document).querySelector(sel); }

	/* ---------------------------------------------------------------- one reader */
	function init(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-book')); } catch (e) { return; }
		var L = cfg.l, stage = $('.bk-stage', el), bookEl = $('.bk-book', el), status = $('.bk-status', el);
		var range = $('[data-act="range"]', el), curEl = $('[data-cur]', el), totalEl = $('[data-total]', el), textEl = $('[data-text]', el);
		var thumbs = $('.bk-thumbs', el), thList = $('.bk-th-list', el);
		var btn = function (a) { return $('[data-act="' + a + '"]', el); };
		var pdf = null, total = 0, pages = [], pf = null, ratio = 1.4, rendered = {}, queue = [], busy = false, lastW = 0;
		var soundOn = false, flips = 0, userTurn = false;

		function fail(msg) { status.textContent = msg || L.error; status.hidden = false; el.setAttribute('data-ready', 'error'); }

		/* ---- sound switch (remembered; on at first when the site says so) */
		function setSound(on, silent) {
			soundOn = on;
			var b = btn('sound');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			$('[data-lbl]', b).textContent = on ? L.sound_on : L.sound_off;
			el.setAttribute('data-sound', on ? '1' : '0');
			if (!silent) { store('mcupr_bk_sound', on ? '1' : '0'); if (on) { turnSound(); } }
		}
		var saved = store('mcupr_bk_sound');
		setSound(saved === null ? !!cfg.sound : saved === '1', true);
		btn('sound').addEventListener('click', function () { setSound(!soundOn); });

		/* ---- the pages */
		function sizeFor(cv) {
			var w = (pages[0] && pages[0].clientWidth) || Math.max(260, stage.clientWidth / 2);
			return Math.min(1700, Math.round(w * Math.min(window.devicePixelRatio || 1, 2)));
		}
		function makePages(n) {
			total = n;
			var i, p, count = n % 2 ? n + 1 : n; // an even number, so the last page is a back cover on its own
			for (i = 0; i < count; i++) {
				p = document.createElement('div');
				p.className = 'bk-page' + (i === 0 || i === count - 1 ? ' bk-hard' : '') + (i >= n ? ' bk-blank' : '');
				p.setAttribute('data-density', i === 0 || i === count - 1 ? 'hard' : 'soft');
				p.setAttribute('data-i', i);
				p.setAttribute('aria-hidden', 'true');
				if (i < n) { p.innerHTML = '<span class="bk-num">' + (i + 1) + '</span>'; }
				bookEl.appendChild(p);
				pages.push(p);
			}
		}
		function drawPdf(idx) {
			return pdf.getPage(idx + 1).then(function (pg) {
				var v1 = pg.getViewport({ scale: 1 }), scale = sizeFor() / v1.width, vp = pg.getViewport({ scale: scale });
				var cv = document.createElement('canvas');
				cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
				cv.className = 'bk-cv';
				return pg.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () {
					var old = pages[idx].querySelector('canvas, img');
					if (old) { old.remove(); }
					pages[idx].insertBefore(cv, pages[idx].firstChild);
					pages[idx].classList.add('bk-done');
				});
			});
		}
		function drawImg(idx) {
			return new Promise(function (res) {
				var im = new Image();
				im.className = 'bk-cv'; im.alt = cfg.pages[idx].alt; im.decoding = 'async';
				im.onload = im.onerror = function () { pages[idx].insertBefore(im, pages[idx].firstChild); pages[idx].classList.add('bk-done'); res(); };
				im.src = cfg.pages[idx].url;
			});
		}
		function pump() {
			if (busy || !queue.length) { return; }
			busy = true;
			var idx = queue.shift();
			(cfg.src === 'pdf' ? drawPdf(idx) : drawImg(idx)).catch(function () { /* one bad page does not stop the book */ }).then(function () { busy = false; pump(); });
		}
		function want(idx) {
			if (idx < 0 || idx >= total || rendered[idx]) { return; }
			rendered[idx] = true;
			queue.push(idx);
		}
		function ensureAround(cur) {
			var order = [cur, cur + 1, cur - 1, cur + 2, cur + 3, cur - 2], k;
			for (k = 0; k < order.length; k++) { want(order[k]); }
			pump();
		}

		/* ---- where we are */
		function shown() { var c = pf.getCurrentPageIndex(), two = pf.getOrientation() === 'landscape'; return two && c > 0 && c < pages.length - 1 ? [c, c + 1] : [c]; }
		function announce() {
			var s = shown(), a = s[0] + 1, b = Math.min(s[s.length - 1] + 1, total);
			var label = s.length > 1 && b !== a ? a + '–' + b : String(a);
			curEl.textContent = label; totalEl.textContent = total;
			range.value = Math.min(a, total);
			el.setAttribute('data-page', a);
			try { history.replaceState(null, '', '#page=' + a); } catch (e) { /* ignore */ }
			store('mcupr_bk_pos', JSON.stringify(Object.assign(JSON.parse(store('mcupr_bk_pos') || '{}'), (function () { var o = {}; o[cfg.id] = a; return o; })())));
			el.setAttribute('aria-label', cfg.title + ' – ' + fmt(L.page, label, total));
			if (cfg.src === 'pdf') {
				var bits = [];
				Promise.all(s.filter(function (i) { return i < total; }).map(function (i) {
					return pdf.getPage(i + 1).then(function (pg) { return pg.getTextContent(); }).then(function (tc) { return tc.items.map(function (t) { return t.str; }).join(' '); });
				})).then(function (tx) { textEl.textContent = tx.join('\n\n').slice(0, 6000); }).catch(function () { /* no text */ });
			} else {
				textEl.textContent = s.filter(function (i) { return i < total; }).map(function (i) { return cfg.pages[i].alt; }).join(' · ');
			}
		}
		function onFlip() { ensureAround(pf.getCurrentPageIndex()); announce(); }

		/* ---- buttons */
		btn('prev').addEventListener('click', function () { userTurn = true; pf.flipPrev(); });
		btn('next').addEventListener('click', function () { userTurn = true; pf.flipNext(); });
		range.addEventListener('input', function () { curEl.textContent = range.value; });
		range.addEventListener('change', function () { userTurn = true; pf.turnToPage(Math.max(0, Math.min(total - 1, parseInt(range.value, 10) - 1))); });
		stage.addEventListener('keydown', function (e) {
			var k = e.key;
			if (!pf || e.altKey || e.ctrlKey || e.metaKey) { return; }
			if (k === 'ArrowRight' || k === 'PageDown') { e.preventDefault(); userTurn = true; pf.flipNext(); }
			else if (k === 'ArrowLeft' || k === 'PageUp') { e.preventDefault(); userTurn = true; pf.flipPrev(); }
			else if (k === 'Home') { e.preventDefault(); userTurn = true; pf.turnToPage(0); }
			else if (k === 'End') { e.preventDefault(); userTurn = true; pf.turnToPage(pages.length - 1); }
		});
		btn('fs').addEventListener('click', function () {
			if (document.fullscreenElement) { document.exitFullscreen(); } else if (el.requestFullscreen) { el.requestFullscreen(); }
		});
		document.addEventListener('fullscreenchange', function () {
			var on = document.fullscreenElement === el, b = btn('fs');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			$('[data-lbl]', b).textContent = on ? L.exit_fs : L.fullscreen;
			el.classList.toggle('is-fs', on);
			setTimeout(function () { if (pf) { window.dispatchEvent(new Event('resize')); rerender(); } }, 150);
		});
		if (!document.fullscreenEnabled && !el.requestFullscreen) { btn('fs').hidden = true; }
		btn('share').addEventListener('click', function () {
			var u = location.href.split('#')[0] + '#page=' + el.getAttribute('data-page'), b = btn('share'), lbl = $('[data-lbl]', b), old = lbl.textContent;
			function done() { lbl.textContent = L.copied; setTimeout(function () { lbl.textContent = old; }, 2200); }
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(u).then(done, function () { window.prompt('', u); }); } else { window.prompt('', u); }
		});
		btn('thumbs').addEventListener('click', function () {
			var open = thumbs.hidden;
			thumbs.hidden = !open;
			btn('thumbs').setAttribute('aria-expanded', open ? 'true' : 'false');
			el.classList.toggle('has-thumbs', open);
			if (open) { buildThumbs(); }
		});

		/* ---- thumbnails (drawn when first opened, one at a time) */
		var thBuilt = false;
		function buildThumbs() {
			if (thBuilt) { return; }
			thBuilt = true;
			var i, todo = [];
			for (i = 0; i < total; i++) {
				(function (n) {
					var li = document.createElement('li'), b = document.createElement('button');
					b.type = 'button'; b.className = 'bk-th';
					b.setAttribute('aria-label', fmt(L.page, n + 1, total));
					b.innerHTML = '<span class="bk-th-box"></span><span class="bk-th-n">' + (n + 1) + '</span>';
					b.addEventListener('click', function () { userTurn = true; pf.turnToPage(n); if (window.innerWidth < 760) { btn('thumbs').click(); } stage.focus(); });
					li.appendChild(b); thList.appendChild(li);
					todo.push(function () {
						var box = b.firstChild;
						if (cfg.src === 'pdf') {
							return pdf.getPage(n + 1).then(function (pg) {
								var v = pg.getViewport({ scale: 1 }), s = 110 / v.width, vp = pg.getViewport({ scale: s }), cv = document.createElement('canvas');
								cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
								return pg.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () { box.appendChild(cv); });
							});
						}
						var im = new Image(); im.alt = ''; im.loading = 'lazy'; im.src = cfg.pages[n].url; box.appendChild(im);
						return Promise.resolve();
					});
				})(i);
			}
			(function next() { var f = todo.shift(); if (f) { f().catch(function () {}).then(function () { setTimeout(next, 0); }); } })();
		}

		/* ---- draw again sharper after a big size change (full screen, rotation) */
		var rt = null;
		function rerender() {
			clearTimeout(rt);
			rt = setTimeout(function () {
				if (!pages[0]) { return; }
				var w = pages[0].clientWidth;
				if (Math.abs(w - lastW) / Math.max(1, lastW) < 0.3) { return; }
				lastW = w; rendered = {}; queue = [];
				ensureAround(pf.getCurrentPageIndex());
			}, 250);
		}
		window.addEventListener('resize', function () { if (pf) { rerender(); } });

		/* ---- start */
		function start(n, firstRatio) {
			ratio = firstRatio || ratio;
			makePages(n);
			var W = 500, H = Math.round(W * ratio);
			el.style.setProperty('--bk-ar', String((2 * W) / H));
			el.style.setProperty('--bk-ar1', String(W / H));
			pf = new window.St.PageFlip(bookEl, {
				width: W, height: H, size: 'stretch', minWidth: 160, maxWidth: 1000, minHeight: 220, maxHeight: 1500,
				showCover: true, usePortrait: true, drawShadow: !reduced, maxShadowOpacity: 0.55,
				flippingTime: reduced ? 1 : 850, showPageCorners: !reduced, mobileScrollSupport: false, swipeDistance: 24, startZIndex: 5
			});
			var startAt = 0, m = /#page=(\d+)/.exec(location.hash), savedPos = null;
			if (m) { startAt = parseInt(m[1], 10) - 1; }
			else { try { savedPos = JSON.parse(store('mcupr_bk_pos') || '{}')[cfg.id]; } catch (e) { savedPos = null; } if (savedPos) { startAt = savedPos - 1; } }
			startAt = Math.max(0, Math.min(total - 1, startAt));
			pf.loadFromHTML(pages);
			range.max = String(total); totalEl.textContent = total;
			pf.on('flip', onFlip);
			pf.on('changeState', function (e) {
				if (e.data === 'flipping' || e.data === 'user_fold') { userTurn = userTurn || e.data === 'user_fold'; }
				if (e.data === 'flipping' && soundOn && turnSound()) { flips++; el.setAttribute('data-flips', flips); }
			});
			if (startAt) { pf.turnToPage(startAt); }
			lastW = pages[0].clientWidth;
			ensureAround(startAt);
			status.hidden = true;
			el.setAttribute('data-ready', '1'); el.setAttribute('data-pages', total);
			announce();
			stage.addEventListener('pointerdown', function () { if (soundOn) { audio(); } }, { passive: true });
		}

		if (!window.St || !window.St.PageFlip) { fail(); return; }
		if (cfg.src === 'images') {
			var first = new Image();
			first.onload = function () { start(cfg.pages.length, first.naturalHeight / first.naturalWidth); };
			first.onerror = function () { start(cfg.pages.length, 1.4); };
			first.src = cfg.pages[0].url;
		} else {
			import(cfg.lib).then(function (lib) {
				lib.GlobalWorkerOptions.workerSrc = cfg.worker;
				return lib.getDocument({ url: cfg.pdf, cMapUrl: cfg.cmaps, cMapPacked: true, standardFontDataUrl: cfg.fonts }).promise;
			}).then(function (doc) {
				pdf = doc;
				return doc.getPage(1).then(function (pg) { var v = pg.getViewport({ scale: 1 }); start(doc.numPages, v.height / v.width); });
			}).catch(function (e) { if (window.console) { console.warn('mcupr e-book:', e); } fail(); });
		}
	}

	Array.prototype.forEach.call(document.querySelectorAll('.bk-reader[data-book]'), init);
})();
