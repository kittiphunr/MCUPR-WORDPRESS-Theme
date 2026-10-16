/* E-book reader for EPUB files (epub.js): the text flows to fit the screen, so it is read one page at a time with a simple
   turning effect and the same page-turn sound as the flip-book. Keys, swipe, contents, text size, progress, "reduce motion". */
(function () {
	'use strict';
	var B = window.mcuprBook;
	if (!B) { return; }

	function store(key, val) { try { if (val === undefined) { return window.localStorage.getItem(key); } window.localStorage.setItem(key, val); } catch (e) { /* private mode */ } return null; }
	function $(sel, el) { return (el || document).querySelector(sel); }
	function load(src) {
		return new Promise(function (res, rej) {
			var s = document.createElement('script');
			s.src = src; s.onload = res; s.onerror = rej;
			document.head.appendChild(s);
		});
	}

	function init(el) {
		var cfg;
		try { cfg = JSON.parse(el.getAttribute('data-book')); } catch (e) { return; }
		if (cfg.src !== 'epub') { return; }
		var L = cfg.l, stage = $('.bk-stage', el), paper = $('.bk-ep-paper', el), view = $('.bk-ep-view', el), status = $('.bk-status', el);
		var range = $('[data-act="range"]', el), count = $('.bk-count', el), thumbs = $('.bk-thumbs', el), thList = $('.bk-th-list', el);
		var btn = function (a) { return $('[data-act="' + a + '"]', el); };
		var book = null, rendition = null, soundOn = false, flips = 0, turning = false, located = false, fontPct = 100;
		var posKey = 'mcupr_bk_cfi_' + cfg.id, sizeKey = 'mcupr_bk_epfs';

		function fail(msg) { status.textContent = msg || L.error; status.hidden = false; el.setAttribute('data-ready', 'error'); }

		/* ---- sound switch (the same remembered choice as the flip-book) */
		function setSound(on, silent) {
			soundOn = on;
			var b = btn('sound');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			$('[data-lbl]', b).textContent = on ? L.sound_on : L.sound_off;
			el.setAttribute('data-sound', on ? '1' : '0');
			if (!silent) { store('mcupr_bk_sound', on ? '1' : '0'); if (on) { B.turnSound(); } }
		}
		var saved = store('mcupr_bk_sound');
		setSound(saved === null ? !!cfg.sound : saved === '1', true);
		btn('sound').addEventListener('click', function () { setSound(!soundOn); });

		/* ---- words of this reader: contents instead of "all pages", a text size pair, a percentage instead of a page count */
		$('span', btn('thumbs')).textContent = L.ep_toc;
		range.min = '0'; range.max = '100'; range.value = '0'; range.disabled = true;
		range.setAttribute('aria-label', L.ep_progress);
		count.textContent = '0%';
		function sizeBtn(delta, label, text) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'bk-btn bk-fs'; b.setAttribute('aria-label', label); b.title = label; b.textContent = text;
			b.addEventListener('click', function () { setSize(fontPct + delta); });
			btn('share').parentNode.insertBefore(b, btn('share'));
			return b;
		}
		var bSm = sizeBtn(-10, L.ep_smaller, 'A−'), bBg = sizeBtn(10, L.ep_bigger, 'A+');
		function setSize(p) {
			fontPct = Math.max(80, Math.min(200, p));
			bSm.disabled = fontPct <= 80; bBg.disabled = fontPct >= 200;
			store(sizeKey, String(fontPct));
			if (rendition) { rendition.themes.fontSize(fontPct + '%'); }
		}
		var sz = parseInt(store(sizeKey) || '100', 10);
		fontPct = sz >= 80 && sz <= 200 ? sz : 100;
		bSm.disabled = fontPct <= 80; bBg.disabled = fontPct >= 200;

		/* ---- turning (one page at a time) */
		function go(dir) { return dir === 'next' ? rendition.next() : rendition.prev(); }
		function turn(dir) {
			if (!rendition || turning) { return; }
			turning = true;
			if (soundOn && B.turnSound()) { flips++; el.setAttribute('data-flips', flips); }
			if (B.reduced) { Promise.resolve(go(dir)).catch(function () {}).then(function () { turning = false; }); return; }
			paper.classList.add('bk-ep-out-' + dir);
			setTimeout(function () {
				Promise.resolve(go(dir)).catch(function () {}).then(function () {
					paper.classList.remove('bk-ep-out-' + dir);
					paper.classList.add('bk-ep-in-' + dir);
					setTimeout(function () { paper.classList.remove('bk-ep-in-' + dir); turning = false; }, 300);
				});
			}, 260);
			setTimeout(function () { turning = false; }, 1500); // never stay stuck
		}
		btn('prev').addEventListener('click', function () { turn('prev'); });
		btn('next').addEventListener('click', function () { turn('next'); });
		function key(e) {
			if (e.altKey || e.ctrlKey || e.metaKey) { return; }
			var k = e.key;
			if (k === 'ArrowRight' || k === 'PageDown') { if (e.preventDefault) { e.preventDefault(); } turn('next'); }
			else if (k === 'ArrowLeft' || k === 'PageUp') { if (e.preventDefault) { e.preventDefault(); } turn('prev'); }
			else if (k === 'Home' && rendition && book) { if (e.preventDefault) { e.preventDefault(); } rendition.display(0); }
		}
		stage.addEventListener('keydown', key);
		range.addEventListener('change', function () {
			if (!located) { return; }
			var cfi = book.locations.cfiFromPercentage(Math.max(0, Math.min(1, parseInt(range.value, 10) / 100)));
			if (cfi) { rendition.display(cfi); }
		});
		range.addEventListener('input', function () { count.textContent = range.value + '%'; });

		btn('fs').addEventListener('click', function () { if (document.fullscreenElement) { document.exitFullscreen(); } else if (el.requestFullscreen) { el.requestFullscreen(); } });
		document.addEventListener('fullscreenchange', function () {
			var on = document.fullscreenElement === el, b = btn('fs');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			$('[data-lbl]', b).textContent = on ? L.exit_fs : L.fullscreen;
			el.classList.toggle('is-fs', on);
			setTimeout(function () { if (rendition) { rendition.resize(); } }, 200);
		});
		if (!document.fullscreenEnabled && !el.requestFullscreen) { btn('fs').hidden = true; }
		btn('share').addEventListener('click', function () {
			var u = location.href.split('#')[0], b = btn('share'), lbl = $('[data-lbl]', b), old = lbl.textContent;
			function done() { lbl.textContent = L.copied; setTimeout(function () { lbl.textContent = old; }, 2200); }
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(u).then(done, function () { window.prompt('', u); }); } else { window.prompt('', u); }
		});
		btn('thumbs').addEventListener('click', function () {
			var open = thumbs.hidden;
			thumbs.hidden = !open;
			btn('thumbs').setAttribute('aria-expanded', open ? 'true' : 'false');
			el.classList.toggle('has-thumbs', open);
			setTimeout(function () { if (rendition) { rendition.resize(); } }, 60);
		});

		/* ---- contents */
		function buildToc(items, depth) {
			items.forEach(function (it) {
				var li = document.createElement('li'), b = document.createElement('button');
				b.type = 'button'; b.className = 'bk-toc'; b.style.paddingLeft = (0.6 + depth * 0.9) + 'rem';
				b.textContent = (it.label || '').trim();
				b.addEventListener('click', function () { rendition.display(it.href); if (window.innerWidth < 760) { btn('thumbs').click(); } stage.focus(); });
				li.appendChild(b); thList.appendChild(li);
				if (it.subitems && it.subitems.length) { buildToc(it.subitems, depth + 1); }
			});
		}

		/* ---- colours of the page follow the site (light, dark, high contrast) */
		function applyTheme() {
			var cs = getComputedStyle(paper), a = getComputedStyle(el);
			rendition.themes.override('color', cs.color);
			rendition.themes.override('background', cs.backgroundColor);
			rendition.themes.override('font-family', a.fontFamily);
			rendition.themes.override('line-height', '1.75');
			rendition.themes.fontSize(fontPct + '%');
		}

		function relocated(loc) {
			var pct = 0;
			if (loc && loc.start) {
				if (located) { pct = Math.round(book.locations.percentageFromCfi(loc.start.cfi) * 100); }
				else if (typeof loc.start.percentage === 'number') { pct = Math.round(loc.start.percentage * 100); }
				store(posKey, loc.start.cfi);
			}
			range.value = String(pct);
			count.textContent = pct + '%';
			el.setAttribute('data-page', pct);
			el.setAttribute('aria-label', cfg.title + ' – ' + pct + '%');
		}

		if (!window.mcuprBook) { fail(); return; }
		load(cfg.jszip).then(function () { return load(cfg.lib); }).then(function () {
			book = window.ePub(cfg.epub, { openAs: 'epub' });
			rendition = book.renderTo(view, { width: '100%', height: '100%', flow: 'paginated', spread: 'none', allowScriptedContent: false });
			applyTheme();
			rendition.on('relocated', relocated);
			rendition.on('keyup', key);
			var x0 = null;
			rendition.on('touchstart', function (e) { x0 = e.changedTouches ? e.changedTouches[0].screenX : null; });
			rendition.on('touchend', function (e) {
				if (x0 === null || !e.changedTouches) { return; }
				var d = e.changedTouches[0].screenX - x0;
				if (Math.abs(d) > 50) { turn(d < 0 ? 'next' : 'prev'); }
				x0 = null;
			});
			rendition.on('rendered', function () { var f = view.querySelector('iframe'); if (f) { f.title = cfg.title; } });
			book.loaded.navigation.then(function (nav) { buildToc(nav.toc || [], 0); });
			return book.ready.then(function () {
				return rendition.display(store(posKey) || undefined);
			}).then(function () {
				status.hidden = true;
				el.setAttribute('data-ready', '1');
				book.locations.generate(1200).then(function () { located = true; range.disabled = false; relocated(rendition.currentLocation()); }).catch(function () {});
				stage.addEventListener('pointerdown', function () { if (soundOn) { B.audio(); } }, { passive: true });
			});
		}).catch(function (e) { if (window.console) { console.warn('mcupr e-book:', e); } fail(); });
	}

	Array.prototype.forEach.call(document.querySelectorAll('.bk-reader[data-book]'), init);
})();
