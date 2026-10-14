/**
 * "Listen to this" – reads the article aloud with the browser's built-in speech
 * synthesis (no plugin, no external service, nothing leaves the device).
 *
 * Markup: template-parts/listen.php  ([data-listen] with data-target / data-title).
 * - reads the title, then each heading / paragraph / list item in turn
 * - highlights the block being read (.is-speaking) and keeps it in view
 * - play / pause / resume / stop, speed 0.75–1.5× (remembered)
 * - language follows the content's lang attribute (th, en, zh, ja …)
 * - pause = stop + remember position (works on every browser, incl. Android)
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-listen]');
	if (!root) { return; }

	var statusEl = root.querySelector('[data-listen-status]');
	var playBtn = root.querySelector('[data-listen-play]');
	var playLabel = root.querySelector('[data-listen-play-label]');
	var stopBtn = root.querySelector('[data-listen-stop]');
	var rateInputs = [].slice.call(root.querySelectorAll('[data-listen-rate]'));
	var rateGroup = root.querySelector('[data-listen-rates]');
	var rateValue = function () { var c = rateInputs.filter(function (i) { return i.checked; })[0]; return c ? c.value : '1'; };
	var RATE_KEY = 'mcupr_rate';
	var T = function (k) { return root.getAttribute('data-t-' + k) || ''; };

	function say(msg) { if (statusEl) { statusEl.textContent = msg; } }

	var synth = window.speechSynthesis;
	if (!synth || typeof window.SpeechSynthesisUtterance === 'undefined') {
		if (playBtn) { playBtn.hidden = true; }
		if (rateGroup) { rateGroup.hidden = true; }
		say(T('unsupported'));
		return;
	}

	var target = document.querySelector(root.getAttribute('data-target') || '#article-body');
	if (!target) { root.hidden = true; return; }

	try {
		var saved = localStorage.getItem(RATE_KEY);
		if (saved) { rateInputs.forEach(function (i) { i.checked = i.value === saved; }); }
	} catch (e) { /* ignore */ }

	var LANG_MAP = { th: 'th-TH', en: 'en-US', zh: 'zh-CN', ja: 'ja-JP' };
	function speechLang() {
		var holder = target.closest('[lang]') || document.documentElement;
		var code = (holder.getAttribute('lang') || 'th').toLowerCase();
		var short = code.slice(0, 2);
		return code.indexOf('-') > 0 && short !== 'zh' ? holder.getAttribute('lang') : (LANG_MAP[short] || code);
	}

	/* ---- split the article into speakable chunks (long text is cut at sentence ends / spaces) */
	var MAX = 180;
	function chunks(text) {
		var out = [];
		var parts = text.replace(/\s+/g, ' ').trim().split(/(?<=[.!?。！？ฯ])\s+/);
		parts.forEach(function (p) {
			while (p.length > MAX) {
				var cut = p.lastIndexOf(' ', MAX);
				if (cut < MAX * 0.5) { cut = MAX; }
				out.push(p.slice(0, cut).trim());
				p = p.slice(cut).trim();
			}
			if (p) { out.push(p); }
		});
		return out;
	}

	var queue = [];   // { text, el }
	function build() {
		queue = [];
		var title = root.getAttribute('data-title');
		if (title) { chunks(title).forEach(function (c) { queue.push({ text: c, el: null }); }); }
		Array.prototype.forEach.call(target.querySelectorAll('h1, h2, h3, h4, p, li, blockquote, figcaption, td, th'), function (el) {
			if (el.closest('[hidden], .sr-only')) { return; }
			var hasBlockChild = el.querySelector('p, li, h2, h3, h4, blockquote');
			if (hasBlockChild && el.tagName !== 'LI') { return; }
			var text = (el.innerText || el.textContent || '').trim();
			if (!text) { return; }
			chunks(text).forEach(function (c) { queue.push({ text: c, el: el }); });
		});
	}

	var index = 0;
	var token = 0;      // bumped whenever speech is cancelled so late events from old utterances are ignored
	var warned = false;
	var state = 'idle'; // idle | playing | paused
	var current = null;

	function highlight(el) {
		if (current && current !== el) { current.classList.remove('is-speaking'); }
		current = el;
		if (el) {
			el.classList.add('is-speaking');
			var r = el.getBoundingClientRect();
			if (r.top < 80 || r.bottom > window.innerHeight - 40) {
				el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
			}
		}
	}

	function pickVoice(lang) {
		var voices = synth.getVoices ? synth.getVoices() : [];
		var short = lang.slice(0, 2).toLowerCase();
		var exact = voices.filter(function (v) { return v.lang && v.lang.toLowerCase() === lang.toLowerCase(); });
		var loose = voices.filter(function (v) { return v.lang && v.lang.toLowerCase().indexOf(short) === 0; });
		return { voice: exact[0] || loose[0] || null, any: voices.length > 0 };
	}

	function ui() {
		var playing = state === 'playing';
		var paused = state === 'paused';
		if (playLabel) { playLabel.textContent = playing ? T('pause') : (paused ? T('resume') : T('play')); }
		if (playBtn) { playBtn.setAttribute('aria-pressed', String(playing)); }
		if (stopBtn) { stopBtn.hidden = state === 'idle'; }
	}

	function speakNext() {
		if (state !== 'playing') { return; }
		if (index >= queue.length) { finish(true); return; }
		var item = queue[index];
		highlight(item.el);
		var lang = speechLang();
		var u = new window.SpeechSynthesisUtterance(item.text);
		u.lang = lang;
		u.rate = parseFloat(rateValue()) || 1;
		var v = pickVoice(lang);
		if (v.voice) { u.voice = v.voice; }
		var mine = ++token;
		u.onend = function () {
			if (mine === token && state === 'playing') { index++; speakNext(); }
		};
		u.onerror = function (ev) {
			if (mine !== token || (ev && (ev.error === 'canceled' || ev.error === 'interrupted'))) { return; }
			finish(false);
		};
		synth.speak(u);
	}

	function finish(completed) {
		token++;
		synth.cancel();
		state = 'idle';
		index = 0;
		highlight(null);
		ui();
		say(completed ? T('done') : '');
	}

	function play() {
		if (state === 'idle') {
			build();
			if (!queue.length) { return; }
			index = 0;
			var v = pickVoice(speechLang());
			warned = v.any && !v.voice;
		}
		state = 'playing';
		token++;
		synth.cancel();
		ui();
		say(warned ? T('novoice') : T('speaking'));
		speakNext();
	}

	function pause() {
		state = 'paused';
		token++;
		synth.cancel(); // resume re-reads the current chunk (reliable on every browser)
		ui();
		say(T('paused'));
	}

	if (playBtn) {
		playBtn.addEventListener('click', function () {
			if (state === 'playing') { pause(); } else { play(); }
		});
	}
	if (stopBtn) { stopBtn.addEventListener('click', function () { finish(false); }); }
	rateInputs.forEach(function (i) {
		i.addEventListener('change', function () {
			try { localStorage.setItem(RATE_KEY, rateValue()); } catch (e) { /* ignore */ }
			if (state === 'playing') { token++; synth.cancel(); speakNext(); } // continue the current chunk at the new speed
		});
	});

	window.addEventListener('pagehide', function () { synth.cancel(); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && state !== 'idle' && !(window.Swal && window.Swal.isVisible())) { finish(false); }
	});

	if (synth.getVoices && synth.addEventListener) { synth.addEventListener('voiceschanged', function () { /* voices load lazily */ }); }
	ui();
})();
