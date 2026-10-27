/*
 * PDF reader inside a post (inc/post-media.php): the pages of a PDF scroll in the page, with previous / next, a page
 * number to type, zoom, fit-to-width and full screen, a search box that finds the words in the text and marks them, and a text
 * layer over every drawn page so the words can be selected, copied and read by a screen reader. pdf.js is the one the e-books use. It starts only when the reader
 * is near the screen, draws only the pages near the screen, and gives the memory back for the ones far away.
 * Without JavaScript, or when the file cannot be opened, the link under the reader still opens or downloads the PDF.
 */
(function () {
	'use strict';
	var cfg = window.mcuprPdf;
	if (!cfg) { return; }
	var T = cfg.t || {};
	var libP = null, L = null;
	var STEPS = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];

	function lib() {
		if (!libP) {
			libP = import(cfg.lib).then(function (l) { l.GlobalWorkerOptions.workerSrc = cfg.worker; L = l; return l; });
		}
		return libP;
	}
	function reduced() {
		return document.documentElement.getAttribute('data-motion') === '1' || !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function init(root) {
		var src = root.getAttribute('data-src');
		var bar = root.querySelector('.pdfv-bar');
		var stage = root.querySelector('[data-stage]');
		var status = root.querySelector('[data-status]');
		var inp = root.querySelector('[data-pg]');
		var total = root.querySelector('[data-total]');
		var bPrev = root.querySelector('[data-prev]'), bNext = root.querySelector('[data-next]');
		var bIn = root.querySelector('[data-in]'), bOut = root.querySelector('[data-out]'), bFit = root.querySelector('[data-fit]'), bFull = root.querySelector('[data-full]');
		var q = root.querySelector('[data-q]'), hPrev = root.querySelector('[data-hit-prev]'), hNext = root.querySelector('[data-hit-next]'), hInfo = root.querySelector('[data-hits]');
		var texts = null, hits = [], byPage = {}, hcur = -1, want = false, qt = 0, reading = false;
		var pdf = null, n = 0, step = 2, ratio = 1.414, pages = [], cur = 1, io = null, raf = 0, lastW = 0;

		function fail() {
			status.textContent = T.fail || '';
			status.hidden = false;
			root.setAttribute('data-state', 'failed');
		}
		function width() { return Math.round(Math.max(160, stage.clientWidth - 24) * STEPS[step]); }

		function draw(p) {
			if (p.busy || p.done === width()) { return; }
			var w = width();
			p.busy = true;
			pdf.getPage(p.n).then(function (page) {
				var v1 = page.getViewport({ scale: 1 });
				var dpr = Math.min(window.devicePixelRatio || 1, 2);
				var vp = page.getViewport({ scale: (w / v1.width) * dpr });
				var c = p.canvas;
				c.width = Math.round(vp.width); c.height = Math.round(vp.height);
				c.style.width = w + 'px'; c.style.height = Math.round(vp.height / dpr) + 'px';
				p.el.style.height = c.style.height;
				var cssVp = page.getViewport({ scale: w / v1.width });
				return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () {
					p.done = w;
					return textLayer(p, page, cssVp);
				});
			}).catch(function () { /* a page that fails stays blank; the link is still there */ }).then(function () {
				p.busy = false;
				if (p.done !== width() && p.visible) { draw(p); }
			});
		}
		function drop(p) {
			if (p.done) { p.canvas.width = 0; p.canvas.height = 0; p.done = 0; }
			if (p.text) { p.text.textContent = ''; p.tl = null; }
		}

		/* The words of a page over its picture: selectable, copyable, read by screen readers; the found words are marked. */
		function textLayer(p, page, vp) {
			if (!L || !L.TextLayer) { return null; }
			p.text.textContent = '';
			p.text.style.setProperty('--scale-factor', vp.scale);
			p.text.style.setProperty('--total-scale-factor', vp.scale);
			return page.getTextContent().then(function (tc) {
				var tl = new L.TextLayer({ textContentSource: tc, container: p.text, viewport: vp });
				return tl.render().then(function () { p.tl = tl; marks(p); if (want) { reveal(); } });
			}).catch(function () { /* no text on this page: the picture is still there */ });
		}

		function build() {
			var keep = cur;
			stage.querySelectorAll('.pdfv-page').forEach(function (e) { e.remove(); });
			if (io) { io.disconnect(); }
			var w = width(), h = Math.round(w * ratio);
			lastW = stage.clientWidth;
			pages = [];
			for (var i = 1; i <= n; i++) {
				var el = document.createElement('div');
				el.className = 'pdfv-page';
				el.pdfvIdx = i - 1;
				el.style.width = w + 'px'; el.style.height = h + 'px';
				el.setAttribute('role', 'group');
				el.setAttribute('aria-label', (T.page || '') + ' ' + i + ' / ' + n);
				var c = document.createElement('canvas');
				c.setAttribute('aria-hidden', 'true');
				var tx = document.createElement('div');
				tx.className = 'pdfv-text';
				el.appendChild(c);
				el.appendChild(tx);
				stage.appendChild(el);
				pages.push({ n: i, el: el, canvas: c, text: tx, tl: null, done: 0, busy: false, visible: false });
			}
			io = new IntersectionObserver(function (list) {
				list.forEach(function (en) {
					var p = pages[en.target.pdfvIdx];
					if (!p) { return; }
					p.visible = en.isIntersecting;
					if (en.isIntersecting) { draw(p); } else { drop(p); }
				});
			}, { root: stage, rootMargin: '900px 0px' });
			pages.forEach(function (p) { io.observe(p.el); });
			go(keep, true);
			buttons();
		}

		function buttons() {
			function dis(b, off) { if (b) { b.disabled = off; } }
			dis(bPrev, cur <= 1); dis(bNext, cur >= n); dis(bOut, step <= 0); dis(bIn, step >= STEPS.length - 1);
			inp.value = cur;
		}
		function go(i, instant) {
			i = Math.max(1, Math.min(n, parseInt(i, 10) || 1));
			cur = i;
			var p = pages[i - 1];
			if (p) { stage.scrollTo({ top: p.el.offsetTop - stage.offsetTop - 8, behavior: instant || reduced() ? 'auto' : 'smooth' }); }
			buttons();
		}
		function track() {
			raf = 0;
			var y = stage.scrollTop + stage.clientHeight / 3 + stage.offsetTop;
			var at = 1;
			for (var i = 0; i < pages.length; i++) { if (pages[i].el.offsetTop <= y) { at = i + 1; } else { break; } }
			if (at !== cur) { cur = at; buttons(); }
		}
		function zoom(d) {
			var s = Math.max(0, Math.min(STEPS.length - 1, step + d));
			if (s === step) { return; }
			step = s;
			build();
		}


		/* ---- search: the text of all pages is read once (in the background), then the words are found and marked */
		function info(t) { hInfo.textContent = t || ''; }
		function fmt(f, a) { return String(f).replace(/%(\d)\$s|%s/g, function (m, i) { return a[i ? i - 1 : 0]; }); }
		function readAll() {
			if (texts) { return Promise.resolve(); }
			if (reading) { return reading; }
			var out = [], i = 0;
			function next() {
				if (i >= n) { texts = out; reading = false; return; }
				info(fmt(T.reading || '%s', [(i + 1) + '/' + n]));
				return pdf.getPage(++i).then(function (pg) { return pg.getTextContent(); }).then(function (tc) {
					out[i] = tc.items.filter(function (it) { return typeof it.str === 'string'; }).map(function (it) { return it.str; });
				}, function () { out[i] = []; }).then(next);
			}
			reading = next();
			return reading;
		}
		function find(term) {
			hits = []; byPage = {}; hcur = -1;
			var t = term.toLowerCase();
			if (!t) { return; }
			for (var pg = 1; pg <= n; pg++) {
				var items = texts[pg] || [];
				for (var i = 0; i < items.length; i++) {
					var low = items[i].toLowerCase(), at = 0, k = 0;
					if (low.length !== items[i].length) { continue; }
					while ((at = low.indexOf(t, at)) > -1) {
						var h = { p: pg, i: i, k: k++, at: at };
						(byPage[pg] = byPage[pg] || []).push({ i: i, at: at, g: hits.length });
						hits.push(h);
						at += t.length;
					}
				}
			}
		}
		/* put <mark>s around the found words of a drawn page */
		function marks(p) {
			if (!p.tl || !hits.length || !byPage[p.n]) { return; }
			var divs = p.tl.textDivs, str = p.tl.textContentItemsStr, len = q.value.length, groups = {};
			byPage[p.n].forEach(function (h) { (groups[h.i] = groups[h.i] || []).push(h); });
			Object.keys(groups).forEach(function (i) {
				var d = divs[i], text = str[i];
				if (!d || d.textContent !== text) { return; }
				var f = document.createDocumentFragment(), pos = 0;
				groups[i].forEach(function (h) {
					if (h.at > pos) { f.appendChild(document.createTextNode(text.slice(pos, h.at))); }
					var m = document.createElement('mark');
					m.className = 'pdfv-hit' + (h.g === hcur ? ' is-cur' : '');
					m.setAttribute('data-hit', h.g);
					m.textContent = text.slice(h.at, h.at + len);
					f.appendChild(m);
					pos = h.at + len;
				});
				if (pos < text.length) { f.appendChild(document.createTextNode(text.slice(pos))); }
				d.textContent = '';
				d.appendChild(f);
			});
		}
		function unmark() {
			stage.querySelectorAll('mark.pdfv-hit').forEach(function (m) {
				var par = m.parentNode;
				par.replaceChild(document.createTextNode(m.textContent), m);
				par.normalize();
			});
		}
		function reveal() {
			var m = stage.querySelector('mark[data-hit="' + hcur + '"]');
			if (!m) { return; }
			want = false;
			var r = m.getBoundingClientRect(), s = stage.getBoundingClientRect();
			stage.scrollTo({ top: stage.scrollTop + (r.top - s.top) - stage.clientHeight / 3, left: stage.scrollLeft + (r.left - s.left) - stage.clientWidth / 2 + r.width / 2, behavior: reduced() ? 'auto' : 'smooth' });
		}
		function show(g) {
			if (!hits.length) { return; }
			g = (g + hits.length) % hits.length;
			var old = stage.querySelector('mark.is-cur');
			if (old) { old.classList.remove('is-cur'); }
			hcur = g;
			var m = stage.querySelector('mark[data-hit="' + g + '"]');
			if (m) { m.classList.add('is-cur'); }
			info(fmt(T.hits || '%1$s / %2$s', [g + 1, hits.length, hits[g].p]));
			want = true;
			go(hits[g].p, true);
			reveal();
		}
		function buttonsFind() { hPrev.disabled = hNext.disabled = hits.length < 1; }
		function run() {
			var term = q.value.trim();
			unmark();
			if (!term || !pdf) { hits = []; byPage = {}; hcur = -1; info(''); buttonsFind(); return; }
			var mine = term;
			readAll().then(function () {
				if (q.value.trim() !== mine) { return; }
				var any = texts.some(function (t) { return t && t.join('').trim() !== ''; });
				if (!any) { hits = []; info(T.noscan || ''); buttonsFind(); return; }
				find(mine);
				pages.forEach(marks);
				buttonsFind();
				if (!hits.length) { info(T.nohit || ''); return; }
				show(0);
			});
		}
		if (q) {
			q.addEventListener('input', function () { window.clearTimeout(qt); qt = window.setTimeout(run, 350); });
			q.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					window.clearTimeout(qt);
					if (hits.length && q.value.trim() !== '' && texts) { show(hcur + (e.shiftKey ? -1 : 1)); } else { run(); }
				} else if (e.key === 'Escape' && q.value) { q.value = ''; run(); }
			});
			hPrev.addEventListener('click', function () { show(hcur - 1); });
			hNext.addEventListener('click', function () { show(hcur + 1); });
		}

		function start() {
			lib().then(function (l) {
				return l.getDocument({ url: src, cMapUrl: cfg.cmaps, cMapPacked: true, standardFontDataUrl: cfg.fonts }).promise;
			}).then(function (doc) {
				pdf = doc; n = doc.numPages;
				total.textContent = n; inp.max = n;
				return doc.getPage(1);
			}).then(function (pg) {
				var v = pg.getViewport({ scale: 1 });
				ratio = v.height / v.width;
				status.hidden = true;
				bar.hidden = false;
				build();
				root.setAttribute('data-state', 'ready');
				root.setAttribute('data-pages', String(n));
			}).catch(function (e) { if (window.console) { console.warn('mcupr pdf:', e); } fail(); });
		}

		stage.addEventListener('scroll', function () { if (!raf) { raf = window.requestAnimationFrame(track); } }, { passive: true });
		bPrev.addEventListener('click', function () { go(cur - 1); });
		bNext.addEventListener('click', function () { go(cur + 1); });
		bIn.addEventListener('click', function () { zoom(1); });
		bOut.addEventListener('click', function () { zoom(-1); });
		bFit.addEventListener('click', function () { if (step !== 2) { step = 2; build(); } });
		inp.addEventListener('change', function () { go(inp.value); });
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(inp.value); } });
		if (document.fullscreenEnabled && root.requestFullscreen) {
			bFull.addEventListener('click', function () {
				if (document.fullscreenElement) { document.exitFullscreen(); } else { root.requestFullscreen(); }
			});
			document.addEventListener('fullscreenchange', function () {
				var on = document.fullscreenElement === root;
				bFull.setAttribute('aria-pressed', on ? 'true' : 'false');
				if (pdf) { window.setTimeout(build, 60); }
			});
		} else { bFull.hidden = true; }
		var rt = 0;
		window.addEventListener('resize', function () {
			window.clearTimeout(rt);
			rt = window.setTimeout(function () { if (pdf && Math.abs(stage.clientWidth - lastW) > 8) { build(); } }, 200);
		});

		if ('IntersectionObserver' in window) {
			var first = new IntersectionObserver(function (l) {
				if (l.some(function (e) { return e.isIntersecting; })) { first.disconnect(); start(); }
			}, { rootMargin: '400px 0px' });
			first.observe(root);
		} else { start(); }
	}

	Array.prototype.forEach.call(document.querySelectorAll('[data-pdfv]'), init);
})();
