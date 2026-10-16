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

	window.mcuprBook = { turnSound: turnSound, audio: audio, reduced: reduced };

	function store(key, val) { try { if (val === undefined) { return window.localStorage.getItem(key); } window.localStorage.setItem(key, val); } catch (e) { /* private mode */ } return null; }
	function fmt(s, a, b) { return String(s).replace('%1$d', a).replace('%2$d', b); }
	function $(sel, el) { return (el || document).querySelector(sel); }

	/* ---------------------------------------------------------------- one reader */
	function init(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-book')); } catch (e) { return; }
		if (cfg.src === 'epub') { return; } // book-epub.js takes this one
		var L = cfg.l, stage = $('.bk-stage', el), bookEl = $('.bk-book', el), status = $('.bk-status', el);
		var range = $('[data-act="range"]', el), curEl = $('[data-cur]', el), totalEl = $('[data-total]', el), textEl = $('[data-text]', el);
		var thumbs = $('.bk-thumbs', el), thList = $('.bk-th-list', el);
		var btn = function (a) { return $('[data-act="' + a + '"]', el); };
		var seq = [], seqOf = {}, media = cfg.media || [], mbar = $('[data-mbar]', el), modal = null, modalFrom = null;
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
				});
			});
		}
		function drawImg(idx) {
			return new Promise(function (res) {
				var im = new Image();
				im.className = 'bk-cv'; im.alt = cfg.pages[seq[idx].n].alt; im.decoding = 'async';
				im.onload = im.onerror = function () { pages[idx].insertBefore(im, pages[idx].firstChild); pages[idx].classList.add('bk-done'); res(); };
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
			try { history.replaceState(null, '', '#page=' + a); } catch (e) { /* ignore */ }
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
			pf = new window.St.PageFlip(bookEl, {
				width: W, height: H, size: 'stretch', minWidth: 160, maxWidth: 1000, minHeight: 220, maxHeight: 1500,
				showCover: true, usePortrait: true, clickEventForward: true, drawShadow: !reduced, maxShadowOpacity: 0.55,
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
