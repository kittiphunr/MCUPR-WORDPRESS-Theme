/**
 * MCUPR theme: display preferences (text size, dark mode, high contrast…),
 * accessible navigation, and SweetAlert2 dialogs / toasts (cookie notice,
 * reset confirmation, status messages, empty-search warning).
 * Preferences are stored only in the visitor's browser (localStorage).
 */
(function () {
	'use strict';

	var KEY = 'mcupr_prefs';
	var CONSENT_KEY = 'mcupr_consent';
	var FONT_LEVELS = [87.5, 100, 125, 150, 200]; // % of browser default; 200% satisfies WCAG 1.4.4
	var FONT_DEFAULT = 1;
	var ATTRS = ['theme', 'font', 'contrast', 'gray', 'underline', 'readable', 'spacing', 'cursor', 'motion', 'images', 'tv'];
	var TV_UA = /SMART-?TV|Tizen|Web0S|WebOS|NetCast|HbbTV|VIDAA|Roku|AFT[A-Z]|BRAVIA|CrKey|GoogleTV|Android TV|AppleTV|Viera|PlayStation|Xbox/i;
	var isTvDevice = TV_UA.test(navigator.userAgent);
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

	/* ---- SweetAlert2 helpers (skin classes are defined in src/input.css) ---- */
	function motionOff() {
		return root.getAttribute('data-motion') === '1' ||
			(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function swalBase(extra) {
		var base = {
			buttonsStyling: false,
			customClass: {
				popup: 'swal-popup',
				title: 'swal-title',
				htmlContainer: 'swal-text',
				confirmButton: 'swal-confirm',
				cancelButton: 'swal-cancel'
			}
		};
		if (motionOff()) { base.showClass = { popup: '', backdrop: '' }; base.hideClass = { popup: '', backdrop: '' }; }
		return Object.assign(base, extra);
	}

	/* Short, non-blocking status message (SweetAlert2 toasts are role="status") */
	function announce(msg) {
		if (!window.Swal) { return; }
		window.Swal.fire(swalBase({
			toast: true,
			position: 'bottom-end',
			icon: 'success',
			title: msg,
			showConfirmButton: false,
			timer: 1800,
			customClass: { popup: 'swal-toast', title: 'swal-title' }
		}));
	}

	function fontIndex() {
		var i = parseInt(prefs.font, 10);
		return isNaN(i) ? FONT_DEFAULT : Math.max(0, Math.min(FONT_LEVELS.length - 1, i));
	}

	function isDark() {
		if (prefs.theme === undefined && isTvDevice && prefs.tv !== '0') { return true; }
		if (prefs.theme === 'dark') { return true; }
		if (prefs.theme === 'light') { return false; }
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function apply() {
		ATTRS.forEach(function (k) {
			if (prefs[k] !== undefined && prefs[k] !== '') {
				root.setAttribute('data-' + k, prefs[k]);
			} else if (k === 'tv' && isTvDevice) {
				root.setAttribute('data-tv', '1'); // TV browsers get TV mode (and dark theme) automatically
			} else if (k === 'theme' && isTvDevice && prefs.tv !== '0') {
				root.setAttribute('data-theme', 'dark');
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
				inp.checked = k === 'tv' ? root.getAttribute('data-tv') === '1' : prefs[k] === (inp.getAttribute('data-on') || '1');
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
			var doReset = function () {
				prefs = {};
				save();
				apply();
				announce(i18n.reset || 'Settings reset');
			};
			if (!window.Swal) { doReset(); return; }
			window.Swal.fire(swalBase({
				icon: 'question',
				title: i18n.resetQ,
				text: i18n.resetQTxt,
				showCancelButton: true,
				confirmButtonText: i18n.resetAll,
				cancelButtonText: i18n.cancel,
				focusCancel: true
			})).then(function (r) { if (r.isConfirmed) { doReset(); } });
		}
	});

	document.addEventListener('change', function (e) {
		var inp = e.target.closest && e.target.closest('[data-pref]');
		if (!inp) { return; }
		var k = inp.getAttribute('data-pref');
		if (inp.type === 'radio') {
			setPref(k, inp.value);
		} else {
			if (k === 'tv') {
				prefs.tv = inp.checked ? '1' : '0';
				save();
				apply();
			} else {
				setPref(k, inp.checked ? (inp.getAttribute('data-on') || '1') : '');
			}
		}
	});

	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var onMq = function () { sync(); };
		if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }
	}

	/* ---- Empty search warning ---- */
	document.addEventListener('submit', function (e) {
		var form = e.target.closest && e.target.closest('.search-form');
		if (!form || !window.Swal) { return; }
		var input = form.querySelector('input[type="search"]');
		if (input && !input.value.trim()) {
			e.preventDefault();
			window.Swal.fire(swalBase({
				icon: 'warning',
				text: i18n.searchEmpty,
				confirmButtonText: i18n.ok,
				willClose: function () { input.focus(); }
			}));
		}
	});

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

	/* ---- TV remote: D-pad spatial navigation + Back key (TV mode only) ---- */
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';
	var DIRS = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' };

	function visibleFocusables() {
		return $$(FOCUSABLE).filter(function (el) {
			if (el.closest('[hidden], .hidden, [inert]') || el.getAttribute('aria-hidden') === 'true') { return false; }
			var r = el.getBoundingClientRect();
			return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
		});
	}

	function spatialMove(dir) {
		var cur = document.activeElement;
		var list = visibleFocusables();
		if (!cur || cur === document.body || list.indexOf(cur) < 0) {
			var inView = list.filter(function (el) { var r = el.getBoundingClientRect(); return r.top >= 0 && r.top < window.innerHeight; });
			if (inView[0]) { inView[0].focus(); return true; }
			return false;
		}
		var a = cur.getBoundingClientRect();
		var ax = a.left + a.width / 2, ay = a.top + a.height / 2;
		var best = null, bestScore = Infinity;
		list.forEach(function (el) {
			if (el === cur) { return; }
			var b = el.getBoundingClientRect();
			var bx = b.left + b.width / 2, by = b.top + b.height / 2;
			var dx = bx - ax, dy = by - ay, main, cross;
			if (dir === 'right') { if (b.left < a.right - 2 && dx <= 0) { return; } main = Math.max(0, b.left - a.right); cross = Math.abs(dy); if (dx <= 0) { return; } }
			if (dir === 'left') { if (dx >= 0) { return; } main = Math.max(0, a.left - b.right); cross = Math.abs(dy); }
			if (dir === 'down') { if (dy <= 0) { return; } main = Math.max(0, b.top - a.bottom); cross = Math.abs(dx); }
			if (dir === 'up') { if (dy >= 0) { return; } main = Math.max(0, a.top - b.bottom); cross = Math.abs(dx); }
			var score = main + cross * 2;
			if (score < bestScore) { bestScore = score; best = el; }
		});
		if (best) { best.focus(); best.scrollIntoView({ block: 'nearest', inline: 'nearest' }); return true; }
		return false;
	}

	document.addEventListener('keydown', function (e) {
		if (root.getAttribute('data-tv') !== '1' || e.altKey || e.ctrlKey || e.metaKey) { return; }
		var a = document.activeElement;
		if (a && a.closest && a.closest('.swal2-container')) { return; }
		var back = e.key === 'XF86Back' || e.key === 'BrowserBack' || e.keyCode === 10009 || e.keyCode === 461;
		if (back) {
			e.preventDefault();
			if (panel && !panel.hidden) { setPanel(false, true); } else if (window.history.length > 1) { window.history.back(); }
			return;
		}
		var dir = DIRS[e.key];
		if (!dir) { return; }
		var t = a && a.tagName;
		if (t === 'TEXTAREA' || (t === 'INPUT' && /^(text|search|email|url|tel|password|number)$/.test(a.type) && (dir === 'left' || dir === 'right'))) { return; }
		if (spatialMove(dir)) { e.preventDefault(); }
	});

	/* ---- Skip-to-menu link only makes sense when the default menu exists ---- */
	if (!document.getElementById('site-nav')) {
		$$('[data-skip-nav]').forEach(function (a) { a.remove(); });
	}

	/* ---- News slider (scroll-snap row with prev/next buttons) ---- */
	$$('[data-carousel]').forEach(function (box) {
		var track = $('[data-carousel-track]', box);
		if (!track) { return; }
		var step = function (dir) {
			var card = track.firstElementChild;
			var w = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
			track.scrollBy({ left: dir * w, behavior: motionOff() ? 'auto' : 'smooth' });
		};
		var prev = $('[data-carousel-prev]', box);
		var next = $('[data-carousel-next]', box);
		if (prev) { prev.addEventListener('click', function () { step(-1); }); }
		if (next) { next.addEventListener('click', function () { step(1); }); }
	});

	/* ---- Back to top ---- */
	var topBtn = $('#back-to-top');
	if (topBtn) {
		var toggleTop = function () { topBtn.classList.toggle('hidden', window.scrollY < 600); };
		window.addEventListener('scroll', toggleTop, { passive: true });
		toggleTop();
		topBtn.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: motionOff() ? 'auto' : 'smooth' });
			var h = $('#skip-top') || $('#masthead') || document.body;
			var first = $('a, button', h);
			if (first) { first.focus({ preventScroll: true }); }
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

	/* ---- Cookie / PDPA notice (essential cookies only) ---- */
	function esc(t) {
		var d = document.createElement('div');
		d.textContent = t;
		return d.innerHTML;
	}

	var seen = false;
	try { seen = localStorage.getItem(CONSENT_KEY) === '1'; } catch (e) { /* ignore */ }
	if (!seen && window.Swal && i18n.cookieMsg) {
		var link = i18n.cookieLink ? ' <a href="' + esc(i18n.cookieLink) + '" target="_blank" rel="noopener">' + esc(i18n.cookiePolicy) + '</a>' : '';
		window.Swal.fire(swalBase({
			position: 'bottom-end',
			backdrop: false,
			grow: false,
			icon: 'info',
			title: i18n.cookieTitle,
			html: esc(i18n.cookieMsg) + link,
			confirmButtonText: i18n.cookieOk,
			allowOutsideClick: false,
			allowEscapeKey: false
		})).then(function () {
			try { localStorage.setItem(CONSENT_KEY, '1'); } catch (e) { /* ignore */ }
		});
	}

	apply();
})();
