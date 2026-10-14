/**
 * MCUPR theme script.
 *  - display preferences (text size, dark mode, contrast, extras, TV mode) saved in localStorage
 *  - SweetAlert2 dialogs: accessibility helper, language, full-screen search,
 *    cookie / PDPA notice and status toasts
 *  - accessible navigation (dropdowns, mobile drawer), news slider, back-to-top
 *  - TV remote support (D-pad spatial navigation, Back key)
 *  - effects: scroll reveal, card tilt, interactive cursor, eased scrolling, scroll progress
 */
(function () {
	'use strict';

	var KEY = 'mcupr_prefs';
	var CONSENT_KEY = 'mcupr_consent';
	var RECENT_KEY = 'mcupr_recent';
	var FONT_LEVELS = [87.5, 100, 125, 150, 200]; // % of browser default; 200% satisfies WCAG 1.4.4
	var FONT_DEFAULT = 1;
	var ATTRS = ['theme', 'font', 'contrast', 'gray', 'underline', 'readable', 'spacing', 'cursor', 'motion', 'images', 'pattern', 'tv'];
	var TV_UA = /SMART-?TV|Tizen|Web0S|WebOS|NetCast|HbbTV|VIDAA|Roku|AFT[A-Z]|BRAVIA|CrKey|GoogleTV|Android TV|AppleTV|Viera|PlayStation|Xbox/i;
	var isTvDevice = TV_UA.test(navigator.userAgent);
	var i18n = window.mcuprI18n || {};
	var root = document.documentElement;
	var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
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

	/* ------------------------------------------------------------------ prefs */
	function fontIndex() {
		var i = parseInt(prefs.font, 10);
		return isNaN(i) ? FONT_DEFAULT : Math.max(0, Math.min(FONT_LEVELS.length - 1, i));
	}

	function motionOn() {
		return root.getAttribute('data-motion') !== '1' &&
			!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	/* ------------------------------------------------------------ colour scheme (visitor) */
	function hex2rgb(h) { h = h.replace('#', ''); return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)]; }
	function rgb2hex(a) { return '#' + a.map(function (v) { v = Math.max(0, Math.min(255, Math.round(v))); return (v < 16 ? '0' : '') + v.toString(16); }).join(''); }
	function lum(h) {
		var c = hex2rgb(h).map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrast(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
	function mix(a, b, t) { var x = hex2rgb(a), y = hex2rgb(b); return rgb2hex([x[0] + (y[0] - x[0]) * t, x[1] + (y[1] - x[1]) * t, x[2] + (y[2] - x[2]) * t]); }
	function onColor(bg) { return contrast(bg, '#ffffff') >= contrast(bg, '#111111') ? '#ffffff' : '#111111'; }
	function ensure(c, bg, toward) { for (var i = 0; i <= 20 && contrast(c, bg) < 4.5; i++) { c = mix(c, toward, 0.08); } return c; }
	function shiftHue(hex, deg) {
		var c = hex2rgb(hex).map(function (v) { return v / 255; });
		var mx = Math.max(c[0], c[1], c[2]), mn = Math.min(c[0], c[1], c[2]), l = (mx + mn) / 2, d = mx - mn;
		if (d === 0) { return hex; }
		var s = l > 0.5 ? d / (2 - mx - mn) : d / (mx + mn), h;
		if (mx === c[0]) { h = ((c[1] - c[2]) / d + 6) % 6; } else if (mx === c[1]) { h = (c[2] - c[0]) / d + 2; } else { h = (c[0] - c[1]) / d + 4; }
		h = (h * 60 + deg + 360) % 360;
		l = Math.max(0.36, Math.min(0.55, l));
		var k = (1 - Math.abs(2 * l - 1)) * s, x = k * (1 - Math.abs((h / 60) % 2 - 1)), m = l - k / 2, rgb;
		if (h < 60) { rgb = [k, x, 0]; } else if (h < 120) { rgb = [x, k, 0]; } else if (h < 180) { rgb = [0, k, x]; } else if (h < 240) { rgb = [0, x, k]; } else if (h < 300) { rgb = [x, 0, k]; } else { rgb = [k, 0, x]; }
		return rgb2hex(rgb.map(function (v) { return (v + m) * 255; }));
	}
	/* Any colour is allowed: text on it and link colours are derived so contrast stays >= 4.5:1. */
	function deriveVars(primary) {
		var second = shiftHue(primary, 28);
		return {
			'--primary': primary, '--accent': second, '--brand-2': second, '--on-primary': onColor(primary),
			'--tint': mix(primary, '#ffffff', 0.9), '--tint-dark': mix('#1e293b', primary, 0.2),
			'--link-light': ensure(primary, '#f1f5f9', '#000000'), '--link-dark': ensure(primary, '#1e293b', '#ffffff')
		};
	}
	function applyColors() {
		var el = document.getElementById('mcupr-user-colors');
		if (!prefs.cv || typeof prefs.cv !== 'object') { if (el) { el.remove(); } return; }
		if (!el) { el = document.createElement('style'); el.id = 'mcupr-user-colors'; document.head.appendChild(el); }
		var t = '';
		Object.keys(prefs.cv).forEach(function (k) { if (/^--[a-z0-9-]+$/.test(k) && /^#[0-9a-f]{6}$/i.test(prefs.cv[k])) { t += k + ':' + prefs.cv[k] + ';'; } });
		el.textContent = '@layer base{:root:root{' + t + '}}';
	}
	function setColor(choice) {
		var pal = i18n.palettes || {};
		if (choice === 'default' || choice === i18n.colorDefault) {
			delete prefs.color; delete prefs.cv;
		} else if (pal[choice]) {
			prefs.color = choice; prefs.cv = pal[choice].vars;
		} else if (/^#[0-9a-f]{6}$/i.test(choice)) {
			prefs.color = choice.toLowerCase(); prefs.cv = deriveVars(choice.toLowerCase());
		}
		save();
		apply();
	}

	function apply() {
		ATTRS.forEach(function (k) {
			if (prefs[k] !== undefined && prefs[k] !== '') {
				root.setAttribute('data-' + k, prefs[k]);
			} else if (k === 'tv' && isTvDevice) {
				root.setAttribute('data-tv', '1'); // TV browsers get TV mode (and dark theme) automatically
			} else if (k === 'pattern' && i18n.patternDefaultOff) {
				root.setAttribute('data-pattern', 'off'); // site default: pattern hidden
			} else if (k === 'motion' && i18n.animDefaultOff) {
				root.setAttribute('data-motion', '1'); // site default: animations off
			} else if (k === 'theme' && isTvDevice && prefs.tv !== '0') {
				root.setAttribute('data-theme', 'dark');
			} else {
				root.removeAttribute('data-' + k);
			}
		});
		var menuMode = prefs.menu === 'top' || prefs.menu === 'side' || prefs.menu === 'hybrid' ? prefs.menu : (i18n.menuDefault || 'top');
		root.setAttribute('data-menu', menuMode);
		var sbMin = false;
		try { sbMin = localStorage.getItem('mcupr_sbmin') === '1'; } catch (err) { /* ignore */ }
		root.classList.toggle('sb-min', menuMode === 'hybrid' || sbMin);
		applyColors();
		sync();
		Cursor.sync();
	}

	/* Reflect the saved state in any controls that are on the page (the dialog is built on demand). */
	function sync() {
		var idx = fontIndex();
		$$('[data-font-level]').forEach(function (b) {
			b.setAttribute('aria-checked', String(parseInt(b.getAttribute('data-font-level'), 10) === idx));
		});

		var on = prefs.contrast !== undefined && prefs.contrast !== '';
		$$('[data-contrast-switch]').forEach(function (b) { b.setAttribute('aria-checked', String(on)); });
		$$('[data-contrast-state]').forEach(function (s) { s.textContent = on ? (i18n.on || 'On') : (i18n.off || 'Off'); });
		$$('[data-contrast-variants]').forEach(function (f) { f.hidden = !on; });

		var anim = motionOn();
		$$('[data-motion-switch]').forEach(function (b) { b.setAttribute('aria-checked', String(anim)); });
		$$('[data-motion-state]').forEach(function (s) { s.textContent = anim ? (i18n.on || 'On') : (i18n.off || 'Off'); });

		var curColor = prefs.color || i18n.colorDefault;
		$$('[data-color-preset]').forEach(function (b) { b.setAttribute('aria-checked', String(b.getAttribute('data-color-preset') === curColor)); });
		$$('[data-color-custom]').forEach(function (inp) {
			var base = /^#/.test(curColor) ? curColor : ((i18n.palettes && i18n.palettes[curColor] && i18n.palettes[curColor].primary) || '#4f46e5');
			if (document.activeElement !== inp) { inp.value = base; }
		});

		$$('[data-pref]').forEach(function (inp) {
			var k = inp.getAttribute('data-pref');
			if (inp.type === 'radio') {
				var cur = prefs[k] === undefined ? (k === 'theme' ? 'auto' : (k === 'menu' ? (i18n.menuDefault || 'top') : '')) : prefs[k];
				inp.checked = (inp.value === cur);
			} else {
				inp.checked = k === 'tv' ? root.getAttribute('data-tv') === '1' : (k === 'pattern' ? root.getAttribute('data-pattern') === 'off' : prefs[k] === (inp.getAttribute('data-on') || '1'));
			}
		});
	}

	function setPref(k, v) {
		if (v === '' || v === null || v === undefined || (k === 'theme' && v === 'auto') || (k === 'menu' && v === (i18n.menuDefault || 'top'))) {
			delete prefs[k];
		} else {
			prefs[k] = v;
		}
		save();
		apply();
	}

	/* ---------------------------------------------------------------- SweetAlert2 */
	function swalBase(extra) {
		var base = {
			buttonsStyling: false,
			closeButtonHtml: '&times;',
			customClass: {
				popup: 'swal-popup',
				title: 'swal-title',
				htmlContainer: 'swal-text',
				confirmButton: 'swal-confirm',
				cancelButton: 'swal-cancel',
				closeButton: 'swal-close'
			}
		};
		if (!motionOn()) {
			base.showClass = { popup: '', backdrop: '' };
			base.hideClass = { popup: '', backdrop: '' };
		}
		return Object.assign(base, extra);
	}

	/* Status message: inside an open dialog it goes to the dialog's live region, otherwise a toast. */
	function announce(msg) {
		var live = $('[data-a11y-status]');
		if (live) {
			live.textContent = '';
			window.setTimeout(function () { live.textContent = msg; }, 30);
			return;
		}
		if (!window.Swal || window.Swal.isVisible()) { return; }
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

	/* Open a <template id="…"> in a SweetAlert2 dialog. */
	function openDialog(tplId, opts) {
		var tpl = document.getElementById(tplId);
		if (!tpl || !window.Swal) { return false; }
		var box = document.createElement('div');
		box.appendChild(tpl.content.cloneNode(true));
		window.Swal.fire(swalBase(Object.assign({
			html: box,
			showConfirmButton: false,
			showCloseButton: true,
			closeButtonAriaLabel: i18n.close || 'Close',
			focusConfirm: false,
			keydownListenerCapture: true, // Esc / Tab keep working even if focus fell to <body> (e.g. a button was hidden)
			customClass: { popup: 'swal-popup swal-modal', title: 'swal-title', htmlContainer: '!m-0 !p-0 !text-left', closeButton: 'swal-close' },
			willOpen: function () { sync(); }
		}, opts)));
		return true;
	}

	/* ---- header buttons open the dialogs; clicks inside dialogs ---- */
	document.addEventListener('click', function (e) {
		var t = e.target;
		if (t.closest('#a11y-toggle')) {
			openDialog('tpl-a11y', {
				title: i18n.a11yTitle,
				width: 'min(100% - 1rem, 34rem)',
				didOpen: function () { var f = $('[data-font-level][aria-checked="true"]') || $('[data-font-level]'); if (f) { f.focus(); } }
			});
			return;
		}
		if (t.closest('#about-toggle, [data-open-about]')) {
			openDialog('tpl-about', {
				title: i18n.aboutTitle, // kept for screen readers; the dialog shows its own header (logo + name)
				width: 'min(100% - 1rem, 36rem)',
				customClass: { popup: 'swal-popup swal-modal', title: 'sr-only', htmlContainer: '!m-0 !p-0 !text-left', closeButton: 'swal-close' }
			});
			return;
		}
		if (t.closest('#lang-toggle')) {
			openDialog('tpl-lang', {
				title: i18n.langTitle,
				width: 'min(100% - 1rem, 30rem)',
				didOpen: function () { var f = $('.swal2-html-container a[aria-current]') || $('.swal2-html-container a'); if (f) { f.focus(); } }
			});
			return;
		}
		if (t.closest('#search-toggle')) {
			var opened = openDialog('tpl-search', {
				title: i18n.searchTitle,
				width: '100%',
				grow: 'fullscreen',
				heightAuto: false,
				padding: 0,
				customClass: { popup: 'swal-popup swal-fullscreen', title: 'sr-only', htmlContainer: '!m-0 !p-0 !text-left !overflow-y-auto', closeButton: 'swal-close' },
				didOpen: function () { renderRecent(); var i = $('#overlay-search'); if (i) { i.focus(); } }
			});
			if (opened) { e.preventDefault(); }
			return;
		}

		var cp = t.closest('[data-color-preset]');
		if (cp) { setColor(cp.getAttribute('data-color-preset')); announce(cp.getAttribute('title') || ''); return; }
		var lvl = t.closest('[data-font-level]');
		if (lvl) {
			var n = parseInt(lvl.getAttribute('data-font-level'), 10);
			if (n === FONT_DEFAULT) { delete prefs.font; } else { prefs.font = String(n); }
			save();
			apply();
			announce((i18n.fontSize || 'Text size') + ': ' + FONT_LEVELS[n] + '%');
			return;
		}
		if (t.closest('[data-contrast-switch]')) {
			var on = prefs.contrast !== undefined && prefs.contrast !== '';
			setPref('contrast', on ? '' : 'wb');
			return;
		}
		if (t.closest('[data-motion-switch]')) {
			var animOn = prefs.motion === undefined ? !i18n.animDefaultOff : prefs.motion !== '1';
			prefs.motion = animOn ? '1' : '0'; // '1' = animations off, '0' = explicitly on
			if ((prefs.motion === '1') === !!i18n.animDefaultOff) { delete prefs.motion; }
			save();
			apply();
			return;
		}
		if (t.closest('[data-reset-prefs]')) {
			prefs = {};
			save();
			apply();
			announce(i18n.reset || 'Settings reset');
			return;
		}
		if (t.closest('[data-clear-recent]')) {
			try { localStorage.removeItem(RECENT_KEY); } catch (err) { /* ignore */ }
			renderRecent();
			var si = $('#overlay-search');
			if (si) { si.focus(); } // the Clear button just disappeared
		}
	});

	document.addEventListener('input', function (e) {
		var ci = e.target.closest && e.target.closest('[data-color-custom]');
		if (ci) { setColor(ci.value); }
	});

	document.addEventListener('change', function (e) {
		var inp = e.target.closest && e.target.closest('[data-pref]');
		if (!inp) { return; }
		var k = inp.getAttribute('data-pref');
		if (inp.type === 'radio') {
			setPref(k, inp.value);
		} else if (k === 'pattern') {
			prefs.pattern = inp.checked ? 'off' : 'on';
			if ((prefs.pattern === 'off') === !!i18n.patternDefaultOff) { delete prefs.pattern; }
			save();
			apply();
		} else if (k === 'tv') {
			prefs.tv = inp.checked ? '1' : '0';
			save();
			apply();
		} else {
			setPref(k, inp.checked ? (inp.getAttribute('data-on') || '1') : '');
		}
	});

	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var onMq = function () { sync(); };
		if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }
	}

	/* ---- Search dialog: recent searches + empty-query message ---- */
	function recent() {
		try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]') || []; } catch (e) { return []; }
	}

	function renderRecent() {
		var box = $('[data-recent]');
		var list = $('[data-recent-list]');
		if (!box || !list) { return; }
		var items = recent();
		list.textContent = '';
		items.forEach(function (term) {
			var li = document.createElement('li');
			var a = document.createElement('a');
			a.className = 'inline-flex min-h-11 items-center rounded-full border border-line px-5 text-fg no-underline hover:border-primary hover:text-link';
			a.href = (i18n.searchUrl || '/?s=') + encodeURIComponent(term);
			a.textContent = term;
			li.appendChild(a);
			list.appendChild(li);
		});
		box.hidden = !items.length;
	}

	document.addEventListener('submit', function (e) {
		var form = e.target.closest && e.target.closest('[data-search-form]');
		if (!form) { return; }
		var input = form.querySelector('input[type="search"]');
		var msg = form.querySelector('[data-search-msg]');
		var term = input ? input.value.trim() : '';
		if (!term) {
			e.preventDefault();
			if (msg) { msg.textContent = i18n.searchEmpty || ''; }
			if (input) { input.focus(); }
			return;
		}
		try {
			var items = recent().filter(function (x) { return x !== term; });
			items.unshift(term);
			localStorage.setItem(RECENT_KEY, JSON.stringify(items.slice(0, 6)));
		} catch (err) { /* ignore */ }
	});

	/* ---- article tools: copy link, native share, print ---- */
	$$('[data-native-share]').forEach(function (b) { if (navigator.share) { b.classList.remove('hidden'); } });
	document.addEventListener('click', function (e) {
		var c = e.target.closest('[data-copy-link]');
		if (c) {
			var done = function () { announce(c.getAttribute('data-copied') || 'Link copied'); };
			var url = c.getAttribute('data-copy-link');
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(url).then(done, done); } else { done(); }
			return;
		}
		var s = e.target.closest('[data-native-share]');
		if (s && navigator.share) {
			navigator.share({ title: s.getAttribute('data-title'), url: s.getAttribute('data-url') }).catch(function () {});
			return;
		}
		if (e.target.closest('[data-print]')) { window.print(); }
	});

	/* ---------------------------------------------------------------- navigation */
	var nav = $('#site-nav');
	var navBtn = $('.nav-toggle');
	var fab = $('#nav-fab');
	var overlay = $('#nav-overlay');
	var desktopMq = window.matchMedia ? window.matchMedia('(min-width: 1100px)') : { matches: true };

	function setNav(open, returnFocus) {
		if (!nav || !navBtn) { return; }
		nav.classList.toggle('is-open', open);
		navBtn.setAttribute('aria-expanded', String(open));
		navBtn.setAttribute('aria-label', navBtn.getAttribute(open ? 'data-label-close' : 'data-label-open') || '');
		if (fab) { fab.setAttribute('aria-expanded', String(open)); fab.setAttribute('aria-label', navBtn.getAttribute(open ? 'data-label-close' : 'data-label-open') || ''); }
		if (overlay) {
			if (open) { overlay.hidden = false; }
			window.requestAnimationFrame(function () {
				overlay.classList.toggle('is-open', open);
				overlay.classList.toggle('opacity-0', !open);
				overlay.classList.toggle('opacity-100', open);
			});
			if (!open) { window.setTimeout(function () { if (!nav.classList.contains('is-open')) { overlay.hidden = true; } }, 320); }
		}
		root.classList.toggle('overflow-hidden', open && !desktopMq.matches);
		if (open) {
			var first = $('a, button', nav);
			window.setTimeout(function () { if (first) { first.focus(); } }, 50);
		} else if (returnFocus) {
			navBtn.focus();
		}
	}

	if (nav && navBtn) {
		navBtn.addEventListener('click', function () { setNav(navBtn.getAttribute('aria-expanded') !== 'true', false); });
		if (fab) { fab.addEventListener('click', function () { setNav(navBtn.getAttribute('aria-expanded') !== 'true', false); }); }

		/* sidebar rail: collapse / expand (remembered) */
		var sbBtn = $('[data-sb-toggle]', nav);
		if (sbBtn) {
			var sbLabel = $('[data-sb-toggle-label]', sbBtn);
			var syncSb = function () {
				var min = root.classList.contains('sb-min');
				sbBtn.setAttribute('aria-expanded', String(!min));
				if (sbLabel) { sbLabel.textContent = min ? (sbLabel.getAttribute('data-expand') || '') : (sbLabel.getAttribute('data-collapse') || ''); }
			};
			sbBtn.addEventListener('click', function () {
				root.classList.toggle('sb-min');
				try { localStorage.setItem('mcupr_sbmin', root.classList.contains('sb-min') ? '1' : '0'); } catch (err) { /* ignore */ }
				syncSb();
			});
			syncSb();
		}
		if (overlay) { overlay.addEventListener('click', function () { setNav(false, true); }); }
		var closeNav = $('[data-close-nav]', nav);
		if (closeNav) { closeNav.addEventListener('click', function () { setNav(false, true); }); }
		if (desktopMq.addEventListener) { desktopMq.addEventListener('change', function () { setNav(false, false); }); }

		var subLabel = i18n.submenu || 'Toggle submenu';
		$$('.menu-item-has-children, .page_item_has_children', nav).forEach(function (li) {
			var link = li.querySelector(':scope > a');
			var sub = li.querySelector(':scope > ul');
			if (!link || !sub) { return; }
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'submenu-toggle';
			b.setAttribute('aria-expanded', 'false');
			b.setAttribute('aria-label', subLabel + ': ' + (link.querySelector('.t') || link).textContent.trim());
			b.innerHTML = '<svg width="14" height="14" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
			link.after(b);
			b.addEventListener('click', function () {
				var open = b.getAttribute('aria-expanded') !== 'true';
				b.setAttribute('aria-expanded', String(open));
				li.classList.toggle('is-open', open);
			});
		});

		/* Priority+ menu: on the top bar the main menu stays on ONE row; items that do not fit move into a
		   "More" dropdown (re-measured on resize, font load, text-size / menu-position changes). */
		var menuUl = $('.menu', nav);
		var moreLabel = i18n.moreMenu || 'More';
		var fitting = false;
		function fitMenu() {
			if (!menuUl || fitting) { return; }
			fitting = true;
			var old = menuUl.querySelector(':scope > .menu-more');
			if (old) {
				$$(':scope > .sub-menu > li', old).forEach(function (li) { menuUl.insertBefore(li, old); });
				old.remove();
			}
			if (desktopMq.matches && root.getAttribute('data-menu') === 'top') {
				var avail = menuUl.clientWidth;
				var items = $$(':scope > li', menuUl);
				var used = function () { return items.reduce(function (n, li) { return n + li.getBoundingClientRect().width + 2; }, 0); };
				if (items.length > 1 && used() > avail + 1) {
					var more = document.createElement('li');
					more.className = 'menu-item menu-item-has-children menu-more';
					var mb = document.createElement('button');
					mb.type = 'button';
					mb.className = 'submenu-toggle more-btn';
					mb.setAttribute('aria-expanded', 'false');
					mb.innerHTML = '<span>' + moreLabel + '</span><svg width="14" height="14" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
					var sub = document.createElement('ul');
					sub.className = 'sub-menu';
					more.appendChild(mb);
					more.appendChild(sub);
					menuUl.appendChild(more);
					mb.addEventListener('click', function () {
						var open = mb.getAttribute('aria-expanded') !== 'true';
						mb.setAttribute('aria-expanded', String(open));
						more.classList.toggle('is-open', open);
					});
					var shown = items.slice();
					while (shown.length > 1 && (shown.reduce(function (n, li) { return n + li.getBoundingClientRect().width + 2; }, 0) + more.getBoundingClientRect().width) > avail) {
						sub.insertBefore(shown.pop(), sub.firstChild);
					}
					more.classList.toggle('has-current', !!sub.querySelector('.current-menu-item, .current_page_item, .current-menu-ancestor'));
				}
			}
			fitting = false;
		}
		var fitTimer = 0;
		var fitSoon = function () { window.clearTimeout(fitTimer); fitTimer = window.setTimeout(fitMenu, 60); };
		if (menuUl) {
			fitMenu();
			if (window.ResizeObserver) { new ResizeObserver(fitSoon).observe(nav); } else { window.addEventListener('resize', fitSoon); }
			if (window.MutationObserver) { new MutationObserver(fitSoon).observe(root, { attributes: true, attributeFilter: ['data-menu', 'data-font', 'data-tv'] }); }
			if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fitSoon); }
			if (desktopMq.addEventListener) { desktopMq.addEventListener('change', fitSoon); }
		}

		/* click outside closes an open dropdown (desktop) */
		document.addEventListener('click', function (e) {
			if (!desktopMq.matches || e.target.closest('.site-nav li.is-open')) { return; }
			$$('.site-nav li.is-open').forEach(function (li) {
				li.classList.remove('is-open');
				var t = li.querySelector(':scope > .submenu-toggle');
				if (t) { t.setAttribute('aria-expanded', 'false'); }
			});
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || (window.Swal && window.Swal.isVisible())) { return; }
			var openLi = $('li.is-open', nav);
			if (openLi) {
				openLi.classList.remove('is-open');
				var t = openLi.querySelector(':scope > .submenu-toggle');
				if (t) { t.setAttribute('aria-expanded', 'false'); t.focus(); }
				return;
			}
			if (nav.classList.contains('is-open')) { setNav(false, true); }
		});
	}

	/* Skip-to-menu link only makes sense when the default menu exists */
	if (!nav) { $$('[data-skip-nav]').forEach(function (a) { a.remove(); }); }

	/* ---------------------------------------------------------------- news slider */
	$$('[data-carousel]').forEach(function (box) {
		var track = $('[data-carousel-track]', box);
		if (!track) { return; }
		var step = function (dir) {
			var card = track.firstElementChild;
			var w = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
			track.scrollBy({ left: dir * w, behavior: motionOn() ? 'smooth' : 'auto' });
		};
		var prev = $('[data-carousel-prev]', box);
		var next = $('[data-carousel-next]', box);
		if (prev) { prev.addEventListener('click', function () { step(-1); }); }
		if (next) { next.addEventListener('click', function () { step(1); }); }
	});

	/* ---------------------------------------------------------------- eased scrolling */
	var scrollAnim = 0;
	function cancelScroll() {
		if (!scrollAnim) { return; }
		window.cancelAnimationFrame(scrollAnim);
		scrollAnim = 0;
		root.style.scrollBehavior = '';
	}

	function smoothScrollTo(y) {
		cancelScroll();
		var startY = window.scrollY;
		var maxY = root.scrollHeight - window.innerHeight;
		var endY = Math.max(0, Math.min(maxY, y));
		var dist = Math.abs(endY - startY);
		if (!motionOn() || dist < 2) { window.scrollTo(0, endY); return; }
		var duration = Math.min(1400, Math.max(600, 400 + dist * 0.35));
		var ease = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }; // easeInOutCubic
		root.style.scrollBehavior = 'auto'; // native smooth scrolling would animate every step again
		var t0 = performance.now();
		var frame = function (now) {
			var p = Math.min(1, (now - t0) / duration);
			window.scrollTo(0, startY + (endY - startY) * ease(p));
			if (p < 1) { scrollAnim = window.requestAnimationFrame(frame); } else { scrollAnim = 0; root.style.scrollBehavior = ''; }
		};
		scrollAnim = window.requestAnimationFrame(frame);
	}
	['wheel', 'touchstart', 'keydown'].forEach(function (ev) { window.addEventListener(ev, cancelScroll, { passive: true }); });

	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href^="#"]');
		if (!a || a.hasAttribute('data-skip') || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
		var hash = a.getAttribute('href');
		var target = hash.length > 1 && document.getElementById(decodeURIComponent(hash.slice(1)));
		if (!target) { return; }
		e.preventDefault();
		var pad = parseFloat(getComputedStyle(root).scrollPaddingTop) || 0;
		smoothScrollTo(target.getBoundingClientRect().top + window.scrollY - pad);
		try { history.replaceState(null, '', hash); } catch (err) { /* ignore */ }
	});

	/* ---------------------------------------------------------------- back to top, progress */
	var topBtn = $('#back-to-top');
	var progress = $('#progress');
	function onScroll() {
		var max = root.scrollHeight - window.innerHeight;
		if (progress) { progress.style.transform = 'scaleX(' + (max > 0 ? window.scrollY / max : 0) + ')'; }
		if (topBtn) { topBtn.classList.toggle('hidden', window.scrollY < 600); }
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
	if (topBtn) {
		topBtn.addEventListener('click', function () {
			smoothScrollTo(0);
			var h = $('#masthead') || document.body;
			var first = $('a, button', h);
			if (first) { first.focus({ preventScroll: true }); }
		});
	}

	/* ---------------------------------------------------------------- scroll reveal */
	var revealer = 'IntersectionObserver' in window
		? new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('in'); revealer.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 })
		: null;

	function reveal() {
		var els = [];
		$$('[data-reveal-group]').forEach(function (g) {
			Array.prototype.forEach.call(g.children, function (el, i) {
				el.style.setProperty('--d', Math.min(i, 8) * 80 + 'ms');
				els.push(el);
			});
		});
		$$('[data-reveal], .reveal').forEach(function (el) { if (els.indexOf(el) < 0) { els.push(el); } });
		// Hide instantly (no transition), otherwise already-painted content would flash and fade out.
		els.forEach(function (el) { el.style.transition = 'none'; el.classList.add('reveal'); });
		void root.offsetWidth; // flush styles
		els.forEach(function (el) {
			el.style.transition = '';
			if (revealer) { revealer.observe(el); } else { el.classList.add('in'); }
		});
	}

	/* ---------------------------------------------------------------- card tilt (mouse only) */
	if (finePointer) {
		var active = null;
		var resetTilt = function (el) {
			el.classList.remove('tilting');
			el.style.removeProperty('--rx');
			el.style.removeProperty('--ry');
		};
		document.addEventListener('pointermove', function (e) {
			var el = motionOn() && root.getAttribute('data-tv') !== '1' && e.pointerType === 'mouse' && e.target.closest ? e.target.closest('.tilt') : null;
			if (active && active !== el) { resetTilt(active); }
			active = el;
			if (!el) { return; }
			var r = el.getBoundingClientRect();
			var px = (e.clientX - r.left) / r.width;
			var py = (e.clientY - r.top) / r.height;
			el.style.setProperty('--rx', ((0.5 - py) * 8).toFixed(2) + 'deg');
			el.style.setProperty('--ry', ((px - 0.5) * 10).toFixed(2) + 'deg');
			el.style.setProperty('--gx', (px * 100).toFixed(1) + '%');
			el.style.setProperty('--gy', (py * 100).toFixed(1) + '%');
			el.classList.add('tilting');
		}, { passive: true });
		document.addEventListener('pointerleave', function () { if (active) { resetTilt(active); } });
	}

	/* ---------------------------------------------------------------- interactive cursor */
	/* A dot that tracks the pointer and a ring that trails it. The ring grows over
	   links/buttons and becomes a labelled "view" bubble over clickable cards/images.
	   Mouse only; off with reduced motion, high-contrast, the large-cursor option and TV mode. */
	var Cursor = (function () {
		if (!finePointer) { return { sync: function () {} }; }
		var CLICKABLE = 'a, button, [role="button"], [role="radio"], [role="switch"], label, summary';
		var dot = null, ring = null, label = null, raf = 0, on = false, shown = false;
		var x = window.innerWidth / 2, y = window.innerHeight / 2, rx = x, ry = y;

		function allowed() {
			return motionOn() && !root.getAttribute('data-contrast') && root.getAttribute('data-cursor') !== '1' && root.getAttribute('data-tv') !== '1';
		}
		function hide() { shown = false; root.classList.remove('cursor-visible'); }

		function build() {
			dot = document.createElement('div');
			ring = document.createElement('div');
			label = document.createElement('span');
			dot.className = 'cursor-dot';
			ring.className = 'cursor-ring';
			ring.appendChild(label);
			dot.setAttribute('aria-hidden', 'true');
			ring.setAttribute('aria-hidden', 'true');
			document.body.append(ring, dot);

			window.addEventListener('pointermove', function (e) {
				if (!on || e.pointerType !== 'mouse') { return; }
				x = e.clientX; y = e.clientY;
				dot.style.transform = 'translate3d(' + x + 'px, ' + y + 'px, 0)';
				if (!shown) { shown = true; rx = x; ry = y; root.classList.add('cursor-visible'); }
			}, { passive: true });
			document.addEventListener('pointerleave', hide);
			window.addEventListener('pointerdown', function () { ring.classList.add('is-down'); });
			window.addEventListener('pointerup', function () { ring.classList.remove('is-down'); });
			document.addEventListener('pointerover', function (e) {
				if (!on) { return; }
				var t = e.target;
				var clickable = t.closest && t.closest(CLICKABLE);
				var isImage = t.tagName === 'IMG' || (t.closest && t.closest('.card, [data-view]') && t.closest('a'));
				ring.classList.remove('is-link', 'is-view', 'is-media');
				dot.classList.remove('is-hidden');
				if (clickable && isImage) {
					label.textContent = i18n.cursorView || 'View';
					ring.classList.add('is-view');
					dot.classList.add('is-hidden');
				} else if (clickable) {
					ring.classList.add('is-link');
					dot.classList.add('is-hidden');
				}
			}, { passive: true });
		}

		function follow() {
			rx += (x - rx) * 0.18; ry += (y - ry) * 0.18;
			ring.style.transform = 'translate3d(' + rx + 'px, ' + ry + 'px, 0)';
			raf = window.requestAnimationFrame(follow);
		}

		function sync() {
			var want = allowed();
			if (on === want) { return; }
			on = want;
			if (on) {
				if (!dot) { build(); }
				root.classList.add('has-cursor');
				raf = window.requestAnimationFrame(follow);
			} else {
				window.cancelAnimationFrame(raf);
				if (dot) { hide(); }
				root.classList.remove('has-cursor');
			}
		}
		return { sync: sync };
	})();

	/* ---------------------------------------------------------------- TV remote */
	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';
	var DIRS = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' };

	function visibleFocusables() {
		return $$(FOCUSABLE).filter(function (el) {
			if (el.closest('[hidden], .hidden, [inert], .invisible') || el.getAttribute('aria-hidden') === 'true') { return false; }
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
			var dx = b.left + b.width / 2 - ax, dy = b.top + b.height / 2 - ay, main, cross;
			if (dir === 'right') { if (dx <= 0) { return; } main = Math.max(0, b.left - a.right); cross = Math.abs(dy); }
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
		var back = e.key === 'XF86Back' || e.key === 'BrowserBack' || e.keyCode === 10009 || e.keyCode === 461;
		if (back) {
			e.preventDefault();
			if (window.Swal && window.Swal.isVisible()) { window.Swal.close(); }
			else if (nav && nav.classList.contains('is-open')) { setNav(false, true); }
			else if (window.history.length > 1) { window.history.back(); }
			return;
		}
		var dir = DIRS[e.key];
		if (!dir) { return; }
		var t = a && a.tagName;
		if (t === 'TEXTAREA' || (t === 'INPUT' && /^(text|search|email|url|tel|password|number)$/.test(a.type) && (dir === 'left' || dir === 'right'))) { return; }
		if (a && a.closest && a.closest('.swal2-popup') && a.classList.contains('swal2-confirm')) { return; }
		if (spatialMove(dir)) { e.preventDefault(); }
	});

	/* ---------------------------------------------------------------- cookie / PDPA notice */
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
	reveal();
})();
