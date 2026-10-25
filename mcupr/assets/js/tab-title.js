/*
 * The title on the browser tab types itself out; when it is longer than the tab it scrolls along for two rounds and then
 * comes to rest on the whole title (inc/tab-title.php sets it up).
 *
 * The title of a page is also what a bookmark, the history, a printout and a PDF file name are made from, so this never
 * leaves a half-typed one behind: the whole title is put back when the effect ends, when the tab goes to the background,
 * before printing and when the page is left. The whole title is kept in window.mcuprFullTitle for the scripts that need it.
 *
 * It does nothing for a visitor who asked for less motion (the browser setting or the theme's own switch).
 */
(function () {
	'use strict';
	var cfg = window.mcuprTab;
	var root = document.documentElement;
	var full = document.title;
	if (!cfg || cfg.mode === 'off' || !full) { return; }

	window.mcuprFullTitle = full;

	function reduced() {
		return root.getAttribute('data-motion') === '1' ||
			!!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}
	if (reduced()) { return; }

	/* letters as a reader counts them: a Thai consonant with its vowel and tone mark above or below is one */
	function letters(text) {
		if (window.Intl && Intl.Segmenter) {
			try {
				return Array.from(new Intl.Segmenter('th', { granularity: 'grapheme' }).segment(text), function (s) { return s.segment; });
			} catch (e) { /* fall through */ }
		}
		return text.match(/[^ัิ-ฺ็-๎][ัิ-ฺ็-๎]*/g) || [text];
	}

	var chars = letters(full);
	var n = chars.length;
	if (n < 2) { return; }

	var typeMs = Math.max(20, parseInt(cfg.type, 10) || 90);
	var stepMs = Math.max(20, parseInt(cfg.step, 10) || 220);
	var restMs = Math.max(0, parseInt(cfg.rest, 10) || 900);
	var width = Math.max(4, parseInt(cfg.width, 10) || 24);
	var timer = 0;

	function state(s) { root.setAttribute('data-tab-title', s); }

	/* stop, and put the whole title back */
	function finish() {
		if (timer) { window.clearTimeout(timer); timer = 0; }
		if (document.title !== full) { document.title = full; }
		state('done');
	}

	function later(fn, ms) { timer = window.setTimeout(fn, ms); }

	function stillGoing() {
		if (document.hidden || reduced()) { finish(); return false; }
		return true;
	}

	/* ---- the scroll: a window of `width` letters sliding along the title, two rounds, then rest */
	function scroll() {
		var loop = chars.concat([' ', '•', ' ']); // the title, a dot, and round again
		var len = loop.length;
		var pos = 0;
		var last = len * 2;
		state('scroll');
		(function step() {
			if (!stillGoing()) { return; }
			pos++;
			if (pos >= last) { finish(); return; }
			var out = '';
			for (var k = 0; k < width; k++) { out += loop[(pos + k) % len]; }
			document.title = out;
			later(step, stepMs);
		})();
	}

	/* ---- the typing: one more letter, with a bar after it like a cursor */
	var i = 0;
	state('typing');
	(function type() {
		if (!stillGoing()) { return; }
		i++;
		if (i >= n) {
			document.title = full;
			if (cfg.mode === 'scroll' && n > width) {
				later(scroll, restMs);
			} else {
				finish();
			}
			return;
		}
		document.title = chars.slice(0, i).join('') + '|';
		later(type, typeMs);
	})();

	/* never leave a partial title where something else will read it */
	document.addEventListener('visibilitychange', function () { if (document.hidden) { finish(); } });
	window.addEventListener('beforeprint', finish);
	window.addEventListener('pagehide', finish);
})();
