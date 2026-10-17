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
	/* Pink-ish noise (a little of each octave, like the hiss of paper rather than the hiss of a radio) with a crinkle: the loudness of paper that is
	   bending flickers a few dozen times a second. Made fresh for every sound, so that no two page turns are the same. */
	function noise(c, len, crinkle) {
		var buf = c.createBuffer(1, len, c.sampleRate), d = buf.getChannelData(0), i, w, b0 = 0, b1 = 0, b2 = 0, g = 0, gt = 0, gs = 0;
		for (i = 0; i < len; i++) {
			w = Math.random() * 2 - 1;
			b0 = 0.99765 * b0 + w * 0.0990460; b1 = 0.96300 * b1 + w * 0.2965164; b2 = 0.57000 * b2 + w * 1.0526913;
			if (i % 180 === 0) { gt = Math.random(); } // a new target a few hundred times a second
			gs += (gt - gs) * 0.08;
			g = crinkle ? 0.35 + 0.65 * gs * gs * 1.6 : 1;
			d[i] = (b0 + b1 + b2 + w * 0.1848) * 0.14 * Math.min(g, 1.2);
		}
		return buf;
	}
	function burst(c, at, dur, type, freq, gain, pan, q) {
		var len = Math.max(32, Math.floor(c.sampleRate * dur)), src = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain(), node = g;
		src.buffer = noise(c, len, false);
		f.type = type; f.frequency.value = freq; if (q) { f.Q.value = q; }
		g.gain.setValueAtTime(0.0001, at); g.gain.exponentialRampToValueAtTime(gain, at + Math.min(0.006, dur / 4)); g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
		src.connect(f); f.connect(g);
		if (c.createStereoPanner && pan) { var p = c.createStereoPanner(); p.pan.value = pan; g.connect(p); node = p; }
		node.connect(c.destination); src.start(at);
	}
	function thump(c, at, f0, f1, dur, gain, pan) {
		var o = c.createOscillator(), g = c.createGain(), node = g;
		o.type = 'sine'; o.frequency.setValueAtTime(f0, at); o.frequency.exponentialRampToValueAtTime(f1, at + dur);
		g.gain.setValueAtTime(0.0001, at); g.gain.exponentialRampToValueAtTime(gain, at + 0.012); g.gain.exponentialRampToValueAtTime(0.0001, at + dur);
		o.connect(g);
		if (c.createStereoPanner && pan) { var p = c.createStereoPanner(); p.pan.value = pan; g.connect(p); node = p; }
		node.connect(c.destination); o.start(at); o.stop(at + dur + 0.02);
	}
	/* The landing of a page: a soft slap of paper on paper and a low thump (a hard cover: deeper and longer). opts: { speed 0..1.5, cover, dir } */
	function landSound(opts) {
		var c = audio();
		if (!c) { return false; }
		opts = opts || {};
		var sp = Math.max(0.15, Math.min(opts.speed === undefined ? 0.8 : opts.speed, 1.5)), t = c.currentTime + 0.005, j = 0.9 + Math.random() * 0.2, pan = (opts.dir > 0 ? -1 : 1) * 0.3;
		if (opts.cover) {
			thump(c, t, 95 * j, 38, 0.26, 0.5 * sp, pan);
			burst(c, t, 0.12, 'lowpass', 700 * j, 0.32 * sp, pan, 0.7);
			burst(c, t + 0.015, 0.05, 'bandpass', 2200 * j, 0.1 * sp, pan, 1.2);
		} else {
			thump(c, t, 130 * j, 60, 0.12, 0.2 * sp, pan);
			burst(c, t, 0.075, 'bandpass', 1500 * j, 0.3 * sp, pan, 0.8);
			burst(c, t + 0.004, 0.04, 'highpass', 3600, 0.06 * sp, pan);
		}
		return true;
	}
	/* The turning of a page: a sharp flick as it lifts, then the rustle of the paper bending (louder and shorter the faster it goes, wandering from
	   one ear to the other as the page crosses the book), and, unless opts.land === false, the landing. opts: { speed 0..1.5, cover, dir, land } */
	function turnSound(opts) {
		var c = audio();
		if (!c) { return false; }
		opts = opts || {};
		var sp = Math.max(0.3, Math.min(opts.speed === undefined ? 1 : opts.speed, 1.5)), cover = !!opts.cover, dir = opts.dir < 0 ? -1 : 1;
		var t = c.currentTime + 0.005, dur = (cover ? 0.5 : 0.3 + Math.random() * 0.08) / (0.75 + 0.35 * sp), j = 0.88 + Math.random() * 0.24, len = Math.floor(c.sampleRate * dur);
		var src = c.createBufferSource(); src.buffer = noise(c, len, true);
		var bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.Q.value = 0.65;
		var f0 = (cover ? 380 : 700) * j, f1 = (cover ? 1700 : 3400) * j;
		bp.frequency.setValueAtTime(f0, t); bp.frequency.exponentialRampToValueAtTime(f1, t + dur * 0.65); bp.frequency.exponentialRampToValueAtTime(f0 * 1.5, t + dur);
		var hp = c.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = cover ? 160 : 330;
		var g = c.createGain(), peak = (cover ? 0.5 : 0.62) * (0.55 + 0.45 * sp) * j;
		g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(peak, t + dur * 0.32); g.gain.exponentialRampToValueAtTime(peak * 0.5, t + dur * 0.7); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
		src.connect(bp); bp.connect(hp); hp.connect(g);
		if (c.createStereoPanner) {
			var p = c.createStereoPanner();
			p.pan.setValueAtTime(0.32 * dir, t); p.pan.linearRampToValueAtTime(-0.32 * dir, t + dur);
			g.connect(p); p.connect(c.destination);
		} else { g.connect(c.destination); }
		src.start(t);
		burst(c, t, cover ? 0.06 : 0.03, 'highpass', cover ? 1200 : 2600, (cover ? 0.14 : 0.2) * sp * j, 0.32 * dir, 0.7); // the flick as it lifts
		if (cover) { thump(c, t, 70, 52, 0.18, 0.16, 0.2 * dir); } // the cover swinging: a low creak of the spine
		if (opts.land !== false) { var lt = Math.max(0, dur * 0.84); window.setTimeout(function () { landSound({ speed: 0.8 * sp, cover: cover, dir: dir }); }, lt * 1000); }
		return true;
	}

	window.mcuprBook = { turnSound: turnSound, landSound: landSound, audio: audio, reduced: reduced };

	function store(key, val) { try { if (val === undefined) { return window.localStorage.getItem(key); } window.localStorage.setItem(key, val); } catch (e) { /* private mode */ } return null; }
	function fmt(s, a, b) { return String(s).replace('%1$d', a).replace('%2$d', b); }
	function $(sel, el) { return (el || document).querySelector(sel); }
	function loadScript(src) {
		return new Promise(function (res, rej) {
			var x = document.createElement('script');
			x.src = src; x.onload = res; x.onerror = rej;
			document.head.appendChild(x);
		});
	}
	var FXS = ['css3d', 'webgl', 'classic'];
	/* the name of an icon button: for screen readers (hidden text) and as its tooltip */
	function lbl(b, text) { if (!b) { return; } var s = b.querySelector('[data-lbl]'); if (s) { s.textContent = text; } b.title = text; }

	/* ---------------------------------------------------------------- one reader */
	function init(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-book')); } catch (e) { return; }
		if (cfg.src === 'epub') { return; } // book-epub.js takes this one
		var L = cfg.l, stage = $('.bk-stage', el), bookEl = $('.bk-book', el), status = $('.bk-status', el);
		var range = $('[data-act="range"]', el), curEl = $('[data-cur]', el), totalEl = $('[data-total]', el), textEl = $('[data-text]', el);
		var thumbs = $('.bk-thumbs', el), thList = $('.bk-th-list', el);
		var btn = function (a) { return $('[data-act="' + a + '"]', el); };
		var each = function (a, fn) { Array.prototype.forEach.call(el.querySelectorAll('[data-act="' + a + '"]'), fn); }; // prev and next are on the sides and in the toolbar
		var inLb = el.classList.contains('is-lb');
		var zoomEl = $('.bk-zoom', el), zoom = 1, panX = 0, panY = 0, autoT = null, outlineBuilt = false;
		function toast(text) {
			Array.prototype.forEach.call(el.querySelectorAll('.bk-toast'), function (x) { x.parentNode.removeChild(x); });
			var t = document.createElement('p');
			t.className = 'bk-toast'; t.setAttribute('role', 'status'); t.textContent = text;
			el.appendChild(t);
			setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 2200);
		}
		var seq = [], seqOf = {}, media = cfg.media || [], mbar = $('[data-mbar]', el), modal = null, modalFrom = null;
		var pdf = null, total = 0, pages = [], pf = null, ratio = 1.4, rendered = {}, queue = [], busy = false, lastW = 0;
		var soundOn = false, flips = 0, lands = 0, userTurn = false;
		/* how the page turns (light CSS 3D, WebGL that bends the page with light and shadow, or the classic page-flip library) is the choice of the admin: for the whole site, or for this book */
		var fx = FXS.indexOf(cfg.fx) !== -1 ? cfg.fx : 'css3d';
		if (fx === 'webgl' && !(window.mcuprBookEngines && window.mcuprBookEngines.supported.webgl)) { fx = 'css3d'; }

		function fail(msg) { status.textContent = msg || L.error; status.hidden = false; el.setAttribute('data-ready', 'error'); }

		/* ---- sound switch (remembered; on at first when the site says so) */
		function setSound(on, silent) {
			soundOn = on;
			var b = btn('sound');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			lbl(b, on ? L.sound_on : L.sound_off);
			el.setAttribute('data-sound', on ? '1' : '0');
			if (!silent) { store('mcupr_bk_sound', on ? '1' : '0'); if (on) { turnSound(); } }
		}
		var saved = store('mcupr_bk_sound');
		setSound(saved === null ? !!cfg.sound : saved === '1', true);
		btn('sound').addEventListener('click', function () { setSound(!soundOn); });

		/* ---- the pages */
		function sizeFor(cv) {
			var w = (pages[0] && pages[0].clientWidth) || Math.max(260, stage.clientWidth / 2);
			return Math.min(3200, Math.round(w * Math.min(window.devicePixelRatio || 1, 2) * Math.max(1, Math.min(zoom, 3))));
		}
		/* the pages in reading order: the book's own pages, each followed by the "page of its own" extras of it */
		function buildSeq(n) {
			var i, k;
			for (i = 0; i < n; i++) {
				seqOf[i] = seq.length;
				seq.push({ n: i });
				for (k = 0; k < media.length; k++) { if (media[k].mode === 'page' && media[k].page === i + 1) { seq.push({ m: media[k] }); } }
			}
		}
		var KIND = { youtube: 'm_youtube', video: 'm_video', audio: 'm_audio', link: 'm_link' };
		var ICON = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>';
		function mTitle(m) { return m.title || L[KIND[m.kind]]; }
		function player(m) {
			var x;
			if (m.kind === 'youtube') {
				x = document.createElement('iframe');
				x.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(m.yt) + '?autoplay=1&rel=0&playsinline=1';
				x.title = mTitle(m); x.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen'; x.setAttribute('allowfullscreen', ''); x.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			} else if (m.kind === 'video') {
				x = document.createElement('video'); x.controls = true; x.autoplay = true; x.setAttribute('playsinline', ''); x.preload = 'metadata'; x.src = m.src; x.setAttribute('aria-label', mTitle(m));
			} else {
				x = document.createElement('audio'); x.controls = true; x.autoplay = true; x.src = m.src; x.setAttribute('aria-label', mTitle(m));
			}
			return x;
		}
		function stopInline() {
			Array.prototype.forEach.call(el.querySelectorAll('.bk-m-slot.is-on'), function (sl) { sl.classList.remove('is-on'); sl.innerHTML = ''; sl.appendChild(sl._btn); });
		}
		/* the window that plays an extra opened from a button */
		function buildModal() {
			modal = document.createElement('div');
			modal.className = 'bk-modal'; modal.hidden = true;
			modal.innerHTML = '<div class="bk-modal-box" role="dialog" aria-modal="true"><div class="bk-modal-head"><h3 class="bk-modal-t"></h3><button type="button" class="bk-btn bk-modal-x"></button></div><div class="bk-modal-body"></div></div>';
			$('.bk-modal-x', modal).textContent = L.m_close;
			$('.bk-modal-x', modal).addEventListener('click', closeModal);
			modal.addEventListener('mousedown', function (e) { if (e.target === modal) { closeModal(); } });
			modal.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeModal(); return; }
				if (e.key === 'Tab') {
					var f = Array.prototype.filter.call(modal.querySelectorAll('button, [href], iframe, video, audio'), function (n) { return !n.disabled; });
					if (!f.length) { return; }
					var first = f[0], last = f[f.length - 1];
					if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
				}
			});
			el.appendChild(modal);
		}
		function openModal(m, from) {
			if (!modal) { buildModal(); }
			modalFrom = from || null;
			$('.bk-modal-t', modal).textContent = mTitle(m);
			$('.bk-modal-box', modal).setAttribute('aria-label', mTitle(m));
			var body = $('.bk-modal-body', modal);
			body.innerHTML = ''; body.setAttribute('data-kind', m.kind);
			body.appendChild(player(m));
			modal.hidden = false;
			$('.bk-modal-x', modal).focus();
		}
		function closeModal() {
			if (!modal || modal.hidden) { return; }
			$('.bk-modal-body', modal).innerHTML = '';
			modal.hidden = true;
			if (modalFrom && document.contains(modalFrom)) { modalFrom.focus(); }
			if (document.activeElement !== modalFrom) { stage.focus(); }
		}
		/* a button (or a link) that opens one extra */
		function mButton(m, cls) {
			var b;
			if (m.kind === 'link') {
				b = document.createElement('a'); b.href = m.href; b.target = '_blank'; b.rel = 'noopener noreferrer';
				b.setAttribute('aria-label', mTitle(m) + ' – ' + L.m_open);
			} else {
				b = document.createElement('button'); b.type = 'button';
				b.addEventListener('click', function () { openModal(m, b); });
			}
			b.className = cls;
			b.innerHTML = ICON + '<span></span>';
			b.lastChild.textContent = mTitle(m);
			return b;
		}
		function mediaPage(p, m) {
			var wrap = document.createElement('div'), slot = document.createElement('div'), btn2;
			wrap.className = 'bk-media';
			wrap.innerHTML = '<p class="bk-m-kind"></p><h3 class="bk-m-title"></h3>';
			wrap.firstChild.textContent = L[KIND[m.kind]]; wrap.lastChild.textContent = m.title || '';
			slot.className = 'bk-m-slot';
			if (m.kind === 'link') { btn2 = mButton(m, 'bk-chip bk-chip-big'); } else {
				btn2 = document.createElement('button'); btn2.type = 'button'; btn2.className = 'bk-chip bk-chip-big';
				btn2.innerHTML = ICON + '<span></span>'; btn2.lastChild.textContent = L.m_play;
				btn2.addEventListener('click', function () { stopInline(); slot.classList.add('is-on'); slot.innerHTML = ''; slot.appendChild(player(m)); });
			}
			slot._btn = btn2; slot.appendChild(btn2); wrap.appendChild(slot);
			noFlip(wrap);
			p.appendChild(wrap);
			p.classList.add('bk-done', 'bk-mpage');
		}
		/* a click on a button or a player must not turn the page: keep its mouse and touch events from the flip-book */
		function noFlip(node) {
			['mousedown', 'mouseup', 'mousemove', 'touchstart', 'touchend', 'touchmove', 'pointerdown', 'pointerup'].forEach(function (t) { node.addEventListener(t, function (e) { e.stopPropagation(); }); });
		}
		function makePages(n) {
			buildSeq(n);
			total = seq.length;
			var i, p, count = total % 2 ? total + 1 : total; // an even number, so the last page is a back cover on its own
			for (i = 0; i < count; i++) {
				p = document.createElement('div');
				p.className = 'bk-page' + (i === 0 || i === count - 1 ? ' bk-hard' : '') + (i >= total ? ' bk-blank' : '');
				p.setAttribute('data-density', i === 0 || i === count - 1 ? 'hard' : 'soft');
				p.setAttribute('data-i', i);
				p.setAttribute('aria-hidden', 'true');
				if (i < total) {
					if (seq[i].m) { mediaPage(p, seq[i].m); }
					p.insertAdjacentHTML('beforeend', '<span class="bk-num">' + (i + 1) + '</span>');
				}
				bookEl.appendChild(p);
				pages.push(p);
			}
			// the buttons on the pages
			var chips = {};
			media.forEach(function (m) {
				if (m.mode !== 'button' || !(m.page >= 1 && m.page <= n)) { return; }
				var idx = seqOf[m.page - 1];
				if (!chips[idx]) { chips[idx] = document.createElement('div'); chips[idx].className = 'bk-chips'; noFlip(chips[idx]); pages[idx].appendChild(chips[idx]); }
				var b = mButton(m, 'bk-chip');
				b.tabIndex = -1; // the same buttons are in the list under the book, for the keyboard
				chips[idx].appendChild(b);
			});
		}
		function drawPdf(idx) {
			return pdf.getPage(seq[idx].n + 1).then(function (pg) {
				var v1 = pg.getViewport({ scale: 1 }), scale = sizeFor() / v1.width, vp = pg.getViewport({ scale: scale });
				var cv = document.createElement('canvas');
				cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
				cv.className = 'bk-cv';
				return pg.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () {
					var old = pages[idx].querySelector('canvas, img');
					if (old) { old.remove(); }
					pages[idx].insertBefore(cv, pages[idx].firstChild);
					pages[idx].classList.add('bk-done');
					if (pf && pf.pageDrawn) { pf.pageDrawn(idx); }
				});
			});
		}
		function drawImg(idx) {
			return new Promise(function (res) {
				var im = new Image();
				im.className = 'bk-cv'; im.alt = cfg.pages[seq[idx].n].alt; im.decoding = 'async';
				im.onload = im.onerror = function () { pages[idx].insertBefore(im, pages[idx].firstChild); pages[idx].classList.add('bk-done'); if (pf && pf.pageDrawn) { pf.pageDrawn(idx); } res(); };
				im.src = cfg.pages[seq[idx].n].url;
			});
		}
		function pump() {
			if (busy || !queue.length) { return; }
			busy = true;
			var idx = queue.shift();
			(cfg.src === 'pdf' ? drawPdf(idx) : drawImg(idx)).catch(function () { /* one bad page does not stop the book */ }).then(function () { busy = false; pump(); });
		}
		function want(idx) {
			if (idx < 0 || idx >= total || rendered[idx] || seq[idx].m) { return; }
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
			if (!inLb) { try { history.replaceState(null, '', '#page=' + a); } catch (e) { /* ignore */ } }
			store('mcupr_bk_pos', JSON.stringify(Object.assign(JSON.parse(store('mcupr_bk_pos') || '{}'), (function () { var o = {}; o[cfg.id] = a; return o; })())));
			el.setAttribute('aria-label', cfg.title + ' – ' + fmt(L.page, label, total));
			showMedia(s);
			var real = s.filter(function (i) { return i < total; });
			if (cfg.src === 'pdf') {
				Promise.all(real.map(function (i) {
					if (seq[i].m) { return Promise.resolve(mTitle(seq[i].m)); }
					return pdf.getPage(seq[i].n + 1).then(function (pg) { return pg.getTextContent(); }).then(function (tc) { return tc.items.map(function (t) { return t.str; }).join(' '); });
				})).then(function (tx) { textEl.textContent = tx.join('\n\n').slice(0, 6000); }).catch(function () { /* no text */ });
			} else {
				textEl.textContent = real.map(function (i) { return seq[i].m ? mTitle(seq[i].m) : cfg.pages[seq[i].n].alt; }).join(' · ');
			}
		}
		/* the list of the extras of the open pages (under the book; for the keyboard and screen readers) */
		function showMedia(s) {
			if (!media.length) { return; }
			mbar.innerHTML = '';
			var n = 0;
			s.forEach(function (i) {
				if (i >= total) { return; }
				var list = seq[i].m ? [seq[i].m] : media.filter(function (m) { return m.mode === 'button' && m.page === seq[i].n + 1; });
				list.forEach(function (m) { mbar.appendChild(mButton(m, 'bk-mbtn')); n++; });
			});
			mbar.hidden = !n;
		}
		function onFlip() { stopInline(); ensureAround(pf.getCurrentPageIndex()); announce(); }

		/* ---- buttons */
		each('prev', function (b) { b.addEventListener('click', function () { autoStop(); userTurn = true; pf.flipPrev(); }); });
		each('next', function (b) { b.addEventListener('click', function () { autoStop(); userTurn = true; pf.flipNext(); }); });
		btn('first').addEventListener('click', function () { autoStop(); userTurn = true; pf.turnToPage(0); });
		btn('last').addEventListener('click', function () { autoStop(); userTurn = true; pf.turnToPage(pages.length - 1); });
		/* ---- auto-play: turns a page every few seconds until the end or until the reader takes over */
		function autoStop() {
			if (autoT) { clearInterval(autoT); autoT = null; }
			var b = btn('auto');
			if (b && b.getAttribute('aria-pressed') === 'true') { b.setAttribute('aria-pressed', 'false'); lbl(b, L.autoplay); }
		}
		btn('auto').addEventListener('click', function () {
			if (autoT) { autoStop(); return; }
			var b = btn('auto');
			b.setAttribute('aria-pressed', 'true'); lbl(b, L.autoplay_off);
			autoT = setInterval(function () {
				if (!pf || document.hidden || (modal && !modal.hidden)) { return; }
				if (pf.getCurrentPageIndex() >= pages.length - 1) { autoStop(); return; }
				pf.flipNext();
			}, 3500);
		});
		stage.addEventListener('pointerdown', function () { autoStop(); }, true);
		/* ---- zoom: the whole book is scaled and can be dragged about; the pictures are drawn again sharper */
		var zt = null, lastZoom = 1;
		function applyZoom() {
			var w = stage.clientWidth, h = stage.clientHeight, mx = (zoom - 1) * w / 2, my = (zoom - 1) * h / 2;
			panX = Math.max(-mx, Math.min(mx, panX)); panY = Math.max(-my, Math.min(my, panY));
			zoomEl.style.transform = zoom === 1 ? '' : 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
			el.classList.toggle('is-zoomed', zoom > 1);
			btn('zoomout').disabled = zoom <= 1; btn('zoomin').disabled = zoom >= 3;
			if (zoom !== lastZoom) {
				lastZoom = zoom;
				clearTimeout(zt);
				zt = setTimeout(function () { if (pf) { rendered = {}; queue = []; ensureAround(pf.getCurrentPageIndex()); } }, 300);
			}
		}
		function setZoom(z) {
			zoom = Math.max(1, Math.min(3, Math.round(z * 4) / 4));
			if (zoom === 1) { panX = 0; panY = 0; }
			applyZoom();
			if (zoom > 1) { toast(Math.round(zoom * 100) + '%'); }
		}
		btn('zoomin').addEventListener('click', function () { setZoom(zoom + 0.5); });
		btn('zoomout').addEventListener('click', function () { setZoom(zoom - 0.5); });
		stage.addEventListener('dblclick', function (e) { if (e.target.closest && e.target.closest('button, a, iframe, video, audio')) { return; } setZoom(zoom > 1 ? 1 : 2); });
		stage.addEventListener('wheel', function (e) { if (e.ctrlKey) { e.preventDefault(); setZoom(zoom + (e.deltaY < 0 ? 0.25 : -0.25)); } }, { passive: false });
		(function () { // drag to move about while zoomed
			var d = null;
			stage.addEventListener('pointerdown', function (e) { if (zoom > 1 && (e.button || 0) === 0 && !(e.target.closest && e.target.closest('button, a, iframe, video, audio'))) { d = { x: e.clientX, y: e.clientY, px: panX, py: panY }; stage.classList.add('is-panning'); } });
			window.addEventListener('pointermove', function (e) { if (d) { panX = d.px + e.clientX - d.x; panY = d.py + e.clientY - d.y; applyZoom(); } });
			window.addEventListener('pointerup', function () { d = null; stage.classList.remove('is-panning'); });
		})();
		if (fx === 'classic') { btn('zoomin').hidden = true; btn('zoomout').hidden = true; } else { applyZoom(); }
		/* ---- print the open pages */
		function printPages() {
			var idx = shown().filter(function (i) { return i < total && !seq[i].m; });
			if (!idx.length) { return; }
			Promise.all(idx.map(function (i) {
				if (cfg.src === 'pdf') {
					return pdf.getPage(seq[i].n + 1).then(function (pg) {
						var v = pg.getViewport({ scale: 1 }), vp = pg.getViewport({ scale: 1500 / v.width }), cv = document.createElement('canvas');
						cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
						var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
						return pg.render({ canvasContext: cx, viewport: vp }).promise.then(function () { return cv.toDataURL('image/jpeg', 0.9); });
					});
				}
				return Promise.resolve(cfg.pages[seq[i].n].url);
			})).then(function (urls) {
				var f = document.createElement('iframe');
				f.setAttribute('aria-hidden', 'true'); f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
				f.srcdoc = '<!doctype html><title>' + String(cfg.title).replace(/</g, '&lt;') + '</title><style>@page{margin:8mm}body{margin:0}img{display:block;max-width:100%;max-height:277mm;margin:0 auto;page-break-after:always}</style>' + urls.map(function (u) { return '<img src="' + u.replace(/"/g, '&quot;') + '">'; }).join('');
				f.onload = function () {
					var w = f.contentWindow, imgs = w.document.images, left = imgs.length;
					function go() { w.focus(); w.print(); setTimeout(function () { if (f.parentNode) { f.parentNode.removeChild(f); } }, 60000); }
					Array.prototype.forEach.call(imgs, function (im) { if (im.complete) { left--; } else { im.onload = im.onerror = function () { if (--left === 0) { go(); } }; } });
					if (left === 0) { go(); }
				};
				document.body.appendChild(f);
			}).catch(function () { toast(L.error); });
		}
		if (btn('print')) { btn('print').addEventListener('click', printPages); }
		if (btn('close')) { btn('close').addEventListener('click', function () { if (window.mcuprBook && window.mcuprBook.closeLb) { window.mcuprBook.closeLb(el); } }); }
		range.addEventListener('input', function () { curEl.textContent = range.value; });
		range.addEventListener('change', function () { userTurn = true; pf.turnToPage(Math.max(0, Math.min(total - 1, parseInt(range.value, 10) - 1))); });
		stage.addEventListener('keydown', function (e) {
			var k = e.key;
			if (!pf || e.altKey || e.ctrlKey || e.metaKey) { return; }
			if (k === 'ArrowRight' || k === 'PageDown') { e.preventDefault(); userTurn = true; pf.flipNext(); }
			else if (k === 'ArrowLeft' || k === 'PageUp') { e.preventDefault(); userTurn = true; pf.flipPrev(); }
			else if (k === 'Home') { e.preventDefault(); userTurn = true; pf.turnToPage(0); }
			else if (k === 'End') { e.preventDefault(); userTurn = true; pf.turnToPage(pages.length - 1); }
			else if ((k === '+' || k === '=') && fx !== 'classic') { e.preventDefault(); setZoom(zoom + 0.5); }
			else if (k === '-' && fx !== 'classic') { e.preventDefault(); setZoom(zoom - 0.5); }
			else if (k === '0' && fx !== 'classic') { e.preventDefault(); setZoom(1); }
			autoStop();
		});
		btn('fs').addEventListener('click', function () {
			if (document.fullscreenElement) { document.exitFullscreen(); } else if (el.requestFullscreen) { el.requestFullscreen(); }
		});
		document.addEventListener('fullscreenchange', function () {
			var on = document.fullscreenElement === el, b = btn('fs');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			lbl(b, on ? L.exit_fs : L.fullscreen);
			el.classList.toggle('is-fs', on);
			setTimeout(function () { if (pf) { window.dispatchEvent(new Event('resize')); rerender(); } }, 150);
		});
		if (!document.fullscreenEnabled && !el.requestFullscreen) { btn('fs').hidden = true; }
		btn('share').addEventListener('click', function () {
			var u = location.href.split('#')[0] + '#page=' + el.getAttribute('data-page');
			function done() { toast(L.copied); }
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(u).then(done, function () { window.prompt('', u); }); } else { window.prompt('', u); }
		});
		btn('thumbs').addEventListener('click', function () {
			var open = thumbs.hidden;
			thumbs.hidden = !open;
			btn('thumbs').setAttribute('aria-expanded', open ? 'true' : 'false');
			el.classList.toggle('has-thumbs', open);
			if (open) { buildThumbs(); }
		});

		/* ---- the contents of the PDF (its bookmarks) and the two tabs of the panel */
		function goDest(dest) {
			if (!dest) { return; }
			var p = typeof dest === 'string' ? pdf.getDestination(dest) : Promise.resolve(dest);
			p.then(function (arr) { if (!arr) { return null; } var r = arr[0]; return typeof r === 'object' ? pdf.getPageIndex(r) : r; }).then(function (n) {
				if (typeof n === 'number' && seqOf[n] !== undefined) { userTurn = true; pf.turnToPage(seqOf[n]); if (window.innerWidth < 760) { btn('thumbs').click(); } stage.focus(); }
			}).catch(function () { /* a bookmark that points nowhere */ });
		}
		function loadOutline() {
			if (cfg.src !== 'pdf' || !pdf || !pdf.getOutline) { return; }
			pdf.getOutline().then(function (items) {
				if (!items || !items.length) { return; }
				var ol = $('.bk-outline', el), tabs = $('.bk-tabs', el);
				(function add(list, depth) {
					list.forEach(function (it) {
						var li = document.createElement('li'), b = document.createElement('button');
						b.type = 'button'; b.className = 'bk-toc'; b.style.paddingLeft = (0.6 + depth * 0.9) + 'rem';
						b.textContent = (it.title || '').trim() || '…';
						b.addEventListener('click', function () { goDest(it.dest); });
						li.appendChild(b); ol.appendChild(li);
						if (it.items && it.items.length) { add(it.items, depth + 1); }
					});
				})(items, 0);
				tabs.hidden = false;
				Array.prototype.forEach.call(tabs.querySelectorAll('.bk-tab'), function (t) {
					t.addEventListener('click', function () {
						var c = t.getAttribute('data-tab') === 'contents';
						Array.prototype.forEach.call(tabs.querySelectorAll('.bk-tab'), function (o) { o.setAttribute('aria-selected', o === t ? 'true' : 'false'); });
						thList.hidden = c; ol.hidden = !c;
					});
				});
			}).catch(function () { /* no contents */ });
		}

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
						if (seq[n].m) {
							box.innerHTML = '<span class="bk-th-m">' + ICON + '<span></span></span>'; box.firstChild.lastChild.textContent = L[KIND[seq[n].m.kind]];
							return Promise.resolve();
						}
						if (cfg.src === 'pdf') {
							return pdf.getPage(seq[n].n + 1).then(function (pg) {
								var v = pg.getViewport({ scale: 1 }), s = 110 / v.width, vp = pg.getViewport({ scale: s }), cv = document.createElement('canvas');
								cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
								return pg.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () { box.appendChild(cv); });
							});
						}
						var im = new Image(); im.alt = ''; im.loading = 'lazy'; im.src = cfg.pages[seq[n].n].url; box.appendChild(im);
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
			if (fx === 'classic') {
				pf = new window.St.PageFlip(bookEl, {
					width: W, height: H, size: 'stretch', minWidth: 160, maxWidth: 1000, minHeight: 220, maxHeight: 1500,
					showCover: true, usePortrait: true, clickEventForward: true, drawShadow: !reduced, maxShadowOpacity: 0.55,
					flippingTime: reduced ? 1 : 850, showPageCorners: !reduced, mobileScrollSupport: false, swipeDistance: 24, startZIndex: 5
				});
			} else {
				pf = window.mcuprBookEngines.create(fx, bookEl, { ratio: ratio, reduced: reduced });
				if (!pf) { fx = 'css3d'; pf = window.mcuprBookEngines.create('css3d', bookEl, { ratio: ratio, reduced: reduced }); }
			}
			var startAt = 0, m = inLb ? null : /#page=(\d+)/.exec(location.hash), savedPos = null;
			if (m) { startAt = parseInt(m[1], 10) - 1; }
			else { try { savedPos = JSON.parse(store('mcupr_bk_pos') || '{}')[cfg.id]; } catch (e) { savedPos = null; } if (savedPos) { startAt = savedPos - 1; } }
			startAt = Math.max(0, Math.min(total - 1, startAt));
			try { pf.loadFromHTML(pages); } catch (err) {
				if (fx === 'webgl') { // the graphics card said no: the light kind of turn instead
					if (window.console) { console.warn('mcupr e-book: WebGL failed, using CSS 3D', err); }
					fx = 'css3d'; bookEl.className = 'bk-book'; bookEl.removeAttribute('style');
					pf = window.mcuprBookEngines.create('css3d', bookEl, { ratio: ratio, reduced: reduced });
					pf.loadFromHTML(pages);
				} else { throw err; }
			}
			el.setAttribute('data-fx', fx);
			range.max = String(total); totalEl.textContent = total;
			pf.on('flip', onFlip);
			var meta = {}, ownLand = fx !== 'classic'; // our own engines tell when a page lands; the old library does not (its sound has the landing in it)
			pf.on('turnmeta', function (e) { meta = e.data || {}; });
			pf.on('land', function (e) {
				if (!soundOn) { return; }
				var d = e.data || {};
				if (landSound({ speed: d.speed, cover: d.cover, dir: meta.dir })) { lands++; el.setAttribute('data-lands', lands); }
			});
			pf.on('changeState', function (e) {
				if (e.data === 'flipping' || e.data === 'user_fold') { userTurn = userTurn || e.data === 'user_fold'; }
				if (e.data === 'flipping' && soundOn && turnSound({ speed: meta.speed, cover: meta.cover, dir: meta.dir, land: !ownLand })) { flips++; el.setAttribute('data-flips', flips); }
			});
			if (startAt) { pf.turnToPage(startAt); }
			lastW = pages[0].clientWidth;
			ensureAround(startAt);
			status.hidden = true;
			el.setAttribute('data-ready', '1'); el.setAttribute('data-pages', total);
			announce();
			stage.addEventListener('pointerdown', function () { if (soundOn) { audio(); } }, { passive: true });
		}

		function boot() {
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
					loadOutline();
					return doc.getPage(1).then(function (pg) { var v = pg.getViewport({ scale: 1 }); start(doc.numPages, v.height / v.width); });
				}).catch(function (e) { if (window.console) { console.warn('mcupr e-book:', e); } fail(); });
			}
		}
		if (fx === 'classic') {
			loadScript(cfg.pageflip).then(function () { if (!window.St || !window.St.PageFlip) { fail(); } else { boot(); } }, function () { fail(); });
		} else { boot(); }
	}

	window.mcuprBook.init = init;

	/* ---------------------------------------------------------------- a book that opens in a window of its own (lightbox) */
	var lbOpen = null;
	function lbKey(e) {
		if (!lbOpen) { return; }
		var n = lbOpen.el;
		if (e.key === 'Escape') {
			var m = n.querySelector('.bk-modal');
			if ((m && !m.hidden) || document.fullscreenElement) { return; } // those take Escape first
			e.preventDefault(); closeLb(n);
		} else if (e.key === 'Tab') {
			var f = Array.prototype.filter.call(n.querySelectorAll('button, [href], input, [tabindex="0"]'), function (x) { return !x.disabled && !x.hidden && x.offsetParent !== null; });
			if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1];
			if (!n.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
			else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	}
	function closeLb() {
		if (!lbOpen) { return; }
		var o = lbOpen; lbOpen = null;
		if (document.fullscreenElement && document.exitFullscreen) { document.exitFullscreen(); }
		document.removeEventListener('keydown', lbKey, true);
		if (o.el.parentNode) { o.el.parentNode.removeChild(o.el); }
		document.documentElement.classList.remove('bk-lb-open');
		if (o.from) { o.from.focus(); }
	}
	window.mcuprBook.closeLb = closeLb;
	function openLb(b) {
		if (lbOpen) { return; }
		var tpl = document.getElementById(b.getAttribute('data-bk-launch'));
		if (!tpl || !tpl.content || !tpl.content.firstElementChild) { return; }
		var node = tpl.content.firstElementChild.cloneNode(true);
		node.setAttribute('role', 'dialog'); node.setAttribute('aria-modal', 'true');
		document.body.appendChild(node);
		document.documentElement.classList.add('bk-lb-open');
		lbOpen = { el: node, from: b };
		document.addEventListener('keydown', lbKey, true);
		if (node.classList.contains('bk-is-epub')) { if (window.mcuprBookEpub) { window.mcuprBookEpub.init(node); } } else { init(node); }
		var st = node.querySelector('.bk-stage');
		if (st) { st.focus(); }
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-bk-launch]') : null;
		if (b) { e.preventDefault(); openLb(b); }
	});

	Array.prototype.forEach.call(document.querySelectorAll('.bk-reader[data-book]'), init);
})();
