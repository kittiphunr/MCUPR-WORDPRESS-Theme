/**
 * MCUPR theme: display preferences (text size, dark mode, high contrast…),
 * accessible navigation and cookie notice.
 * Preferences are stored only in the visitor's browser (localStorage).
 */
(function () {
	'use strict';

	var KEY = 'mcupr_prefs';
	var CONSENT_KEY = 'mcupr_consent';
	var FONT_LEVELS = [87.5, 100, 125, 150, 200]; // % of browser default; 200% satisfies WCAG 1.4.4
	var FONT_DEFAULT = 1;
	var ATTRS = ['theme', 'font', 'contrast', 'gray', 'underline', 'readable', 'spacing', 'cursor', 'motion', 'images'];
	var i18n = window.mcuprI18n || {};
	var root = document.documentElement;
	var prefs = {};

	try {
		prefs = JSON.parse(localStorage.getItem(KEY) || '{}') || {};
	} catch (e) {
		prefs = {};
	}

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	function save() {
		try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) { /* storage blocked */ }
	}

	function announce(msg) {
		var el = $('#mcupr-status');
		if (!el) { return; }
		el.textContent = '';
		window.setTimeout(function () { el.textContent = msg; }, 50);
	}

	function fontIndex() {
		var i = parseInt(prefs.font, 10);
		return isNaN(i) ? FONT_DEFAULT : Math.max(0, Math.min(FONT_LEVELS.length - 1, i));
	}

	function isDark() {
		if (prefs.theme === 'dark') { return true; }
		if (prefs.theme === 'light') { return false; }
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function apply() {
		ATTRS.forEach(function (k) {
			if (prefs[k] !== undefined && prefs[k] !== '') {
				root.setAttribute('data-' + k, prefs[k]);
			} else {
				root.removeAttribute('data-' + k);
			}
		});
		sync();
	}

	function sync() {
		var idx = fontIndex();
		$$('[data-font-action]').forEach(function (b) {
			var a = b.getAttribute('data-font-action');
			var off = (a === 'dec' && idx === 0) || (a === 'inc' && idx === FONT_LEVELS.length - 1);
			b.setAttribute('aria-disabled', off ? 'true' : 'false');
		});

		var toggle = $('#theme-toggle');
		if (toggle) { toggle.setAttribute('aria-pressed', isDark() ? 'true' : 'false'); }

		$$('[data-pref]').forEach(function (inp) {
			var k = inp.getAttribute('data-pref');
			if (inp.type === 'radio') {
				var cur = prefs[k] === undefined ? (k === 'theme' ? 'auto' : '') : prefs[k];
				inp.checked = (inp.value === cur);
			} else {
				inp.checked = prefs[k] === (inp.getAttribute('data-on') || '1');
			}
		});
	}

	function setPref(k, v) {
		if (v === '' || v === null || v === undefined || (k === 'theme' && v === 'auto')) {
			delete prefs[k];
		} else {
			prefs[k] = v;
		}
		save();
		apply();
	}

	/* ---- Text size ---- */
	function changeFont(action) {
		var idx = fontIndex();
		if (action === 'inc') { idx = Math.min(FONT_LEVELS.length - 1, idx + 1); }
		if (action === 'dec') { idx = Math.max(0, idx - 1); }
		if (action === 'reset') { idx = FONT_DEFAULT; }
		if (idx === FONT_DEFAULT) { delete prefs.font; } else { prefs.font = String(idx); }
		save();
		apply();
		announce((i18n.fontSize || 'Text size') + ': ' + FONT_LEVELS[idx] + '%');
	}

	document.addEventListener('click', function (e) {
		var fb = e.target.closest('[data-font-action]');
		if (fb) {
			if (fb.getAttribute('aria-disabled') !== 'true') { changeFont(fb.getAttribute('data-font-action')); }
			return;
		}
		if (e.target.closest('#theme-toggle')) {
			setPref('theme', isDark() ? 'light' : 'dark');
			return;
		}
		if (e.target.closest('[data-reset-prefs]')) {
			prefs = {};
			save();
			apply();
			announce(i18n.reset || 'Settings reset');
		}
	});

	document.addEventListener('change', function (e) {
		var inp = e.target.closest && e.target.closest('[data-pref]');
		if (!inp) { return; }
		var k = inp.getAttribute('data-pref');
		if (inp.type === 'radio') {
			setPref(k, inp.value);
		} else {
			setPref(k, inp.checked ? (inp.getAttribute('data-on') || '1') : '');
		}
	});

	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var onMq = function () { sync(); };
		if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }
	}

	/* ---- Accessibility panel (disclosure) ---- */
	var panelBtn = $('#a11y-toggle');
	var panel = $('#a11y-panel');

	function setPanel(open, returnFocus) {
		if (!panel || !panelBtn) { return; }
		panel.hidden = !open;
		panelBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) {
			var first = $('input, button', panel);
			if (first) { first.focus(); }
		} else if (returnFocus) {
			panelBtn.focus();
		}
	}

	if (panelBtn && panel) {
		panelBtn.addEventListener('click', function () { setPanel(panel.hidden, false); });
		panel.addEventListener('click', function (e) {
			if (e.target.closest('[data-close-panel]')) { setPanel(false, true); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !panel.hidden) { setPanel(false, true); }
		});
	}

	/* ---- Navigation ---- */
	var nav = $('#site-nav');
	var navBtn = $('.nav-toggle');

	if (nav && navBtn) {
		navBtn.addEventListener('click', function () {
			var open = navBtn.getAttribute('aria-expanded') !== 'true';
			navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			nav.classList.toggle('is-open', open);
		});

		var subLabel = (window.mcuprI18n && window.mcuprI18n.submenu) || 'Toggle submenu';
		$$('.menu-item-has-children, .page_item_has_children', nav).forEach(function (li) {
			var link = li.querySelector(':scope > a');
			var sub = li.querySelector(':scope > ul');
			if (!link || !sub) { return; }
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'submenu-toggle';
			b.setAttribute('aria-expanded', 'false');
			b.setAttribute('aria-label', subLabel + ': ' + link.textContent.trim());
			b.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
			link.after(b);
			b.addEventListener('click', function () {
				var open = b.getAttribute('aria-expanded') !== 'true';
				b.setAttribute('aria-expanded', open ? 'true' : 'false');
				li.classList.toggle('is-open', open);
			});
		});

		nav.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') { return; }
			var openLi = $('.is-open', nav);
			if (openLi && openLi.matches('li')) {
				openLi.classList.remove('is-open');
				var t = openLi.querySelector(':scope > .submenu-toggle');
				if (t) { t.setAttribute('aria-expanded', 'false'); t.focus(); }
			}
		});
	}

	/* ---- Cookie notice (essential cookies only; remembers acknowledgement) ---- */
	var notice = $('#cookie-notice');
	if (notice) {
		var seen = false;
		try { seen = localStorage.getItem(CONSENT_KEY) === '1'; } catch (e) { /* ignore */ }
		if (!seen) { notice.hidden = false; }
		var ok = $('#cookie-accept');
		if (ok) {
			ok.addEventListener('click', function () {
				try { localStorage.setItem(CONSENT_KEY, '1'); } catch (e) { /* ignore */ }
				notice.hidden = true;
			});
		}
	}

	apply();
})();
