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
	if (prefs.menu === 'hybrid') { prefs.menu = 'side'; } /* the hybrid layout was retired: it is the sidebar now */

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	/* keep Tab / Shift+Tab inside a dialog: from the last of its focusable elements back to the first, and the other way */
	function trapTab(e, f) {
		if (!f.length) { return; }
		var first = f[0], last = f[f.length - 1];
		if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
		else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
	}

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
		var menuPick = prefs.menu === 'top' || prefs.menu === 'bar' || prefs.menu === 'side' || prefs.menu === 'hybrid' ? prefs.menu : (i18n.menuDefault || 'top');
		if (menuPick === 'hybrid') { menuPick = 'side'; }
		var menuMode = menuPick === 'side' ? 'side' : 'top'; /* "bar" is the top menu drawn as one capsule */
		root.setAttribute('data-menu', menuMode);
		if (menuPick === 'bar') { root.setAttribute('data-menubar', '1'); } else { root.removeAttribute('data-menubar'); }
		var sbMin = false;
		try { sbMin = localStorage.getItem('mcupr_sbmin') === '1'; } catch (err) { /* ignore */ }
		root.classList.toggle('sb-min', menuMode !== 'top' && sbMin);
		applyColors();
		sync();
		Cursor.sync();
		Lord.sync();
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

	/* ---------------------------------------------------------------- SweetAlert2 (loaded on first use) */
	var swalPromise = null;
	function loadSwal() {
		if (window.Swal) { return Promise.resolve(window.Swal); }
		if (!swalPromise) {
			swalPromise = new Promise(function (resolve, reject) {
				var el = document.createElement('script');
				el.src = i18n.swalUrl;
				el.onload = function () { resolve(window.Swal); };
				el.onerror = function () { swalPromise = null; reject(new Error('SweetAlert2 failed to load')); };
				document.head.appendChild(el);
			});
		}
		return swalPromise;
	}
	/* warm it up the moment a visitor reaches for a dialog button, so the click feels instant */
	['#a11y-toggle', '#lang-toggle', '#search-toggle', '#about-toggle', '[data-open-about]'].forEach(function (sel) {
		$$(sel).forEach(function (el) {
			['pointerenter', 'focus', 'touchstart'].forEach(function (ev) { el.addEventListener(ev, function () { loadSwal().catch(function () {}); }, { once: true, passive: true }); });
		});
	});

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
		loadSwal().then(function (Swal) {
			if (Swal.isVisible()) { return; }
			Swal.fire(swalBase({
				toast: true,
				position: 'bottom-end',
				icon: 'success',
				title: msg,
				showConfirmButton: false,
				timer: 1800,
				customClass: { popup: 'swal-toast', title: 'swal-title' }
			}));
		}).catch(function () { /* no toast without the library */ });
	}

	/* Open a <template id="…"> in a SweetAlert2 dialog. */
	function openDialog(tplId, opts) {
		var tpl = document.getElementById(tplId);
		if (!tpl) { return false; }
		var box = document.createElement('div');
		box.appendChild(tpl.content.cloneNode(true));
		loadSwal().then(function (Swal) { Swal.fire(swalBase(Object.assign({
			html: box,
			showConfirmButton: false,
			showCloseButton: true,
			closeButtonAriaLabel: i18n.close || 'Close',
			focusConfirm: false,
			keydownListenerCapture: true, // Esc / Tab keep working even if focus fell to <body> (e.g. a button was hidden)
			customClass: { popup: 'swal-popup swal-modal', title: 'swal-title', htmlContainer: '!m-0 !p-0 !text-left', closeButton: 'swal-close' },
			willOpen: function () { sync(); }
		}, opts))); }).catch(function () { /* library blocked: nothing to open */ });
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
		var msg = form.querySelector('[data-search-msg]') || (form.parentNode && form.parentNode.querySelector('[data-search-msg]'));
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
		if (e.target.closest('[data-print]')) { if (window.mcuprDoc && window.mcuprDoc.print) { window.mcuprDoc.print(); } else { window.print(); } } /* the A4 document (print-doc.js) is put together just before the printing starts */
		var pdf = e.target.closest('[data-pdf]');
		if (pdf && !pdf.hasAttribute('aria-busy')) {
			/* the PDF is made in the browser; the libraries (about 600 KB) are fetched only now, one after the other */
			var urls = (window.mcuprI18n && window.mcuprI18n.pdfScripts) || [];
			var label = $('span', pdf), was = label ? label.textContent : '';
			pdf.setAttribute('aria-busy', 'true');
			if (label) { label.textContent = pdf.getAttribute('data-pdf-busy') || was; }
			var done = function (msg) {
				pdf.removeAttribute('aria-busy');
				if (label) { label.textContent = was; }
				if (msg) { announce(msg); }
			};
			var load = function (i) {
				if (i >= urls.length) { return Promise.resolve(); }
				if (i === urls.length - 1 && window.mcuprPdf) { return Promise.resolve(); }
				return new Promise(function (ok, no) {
					if ((i === 0 && window.html2canvas) || (i === 1 && window.jspdf)) { ok(); return; }
					var s = document.createElement('script');
					s.src = urls[i];
					s.onload = ok;
					s.onerror = no;
					document.head.appendChild(s);
				}).then(function () { return load(i + 1); });
			};
			load(0).then(function () { return window.mcuprPdf(pdf); }).then(function () { done(''); }, function () { done(pdf.getAttribute('data-pdf-fail') || 'PDF failed'); });
		}
	});

	/* ---------------------------------------------------------------- the search bar of the home hero (phones): the button chooses what to search in */
	$$('[data-hs]').forEach(function (f) {
		var b = $('[data-hs-filter]', f), box = $('.hs-scope', f);
		if (!b || !box) { return; }
		var input = $('input[type="search"]', f), res = document.getElementById(f.getAttribute('data-hs-res') || '');
		var rerun = function () {};

		/* the results come under the bar, with no page to load: the first few from /?s=…&mcupr_live=1 (inc/live-search.php) */
		if (input && res && window.fetch && window.URL && window.URLSearchParams) {
			var T = { busy: res.getAttribute('data-t-busy') || '', none: res.getAttribute('data-t-none') || '', count: res.getAttribute('data-t-count') || '', all: res.getAttribute('data-t-all') || '', error: res.getAttribute('data-t-error') || '' };
			var base = res.getAttribute('data-live') || '/', cache = {}, timer = 0, ctl = null, shown = '';
			var fmt = function (t, v) { return t.replace('%s', function () { return v; }).replace('%d', function () { return v; }); }; /* (a function: a word with $& or $' in it must not be read as a pattern) */
			var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text !== undefined) { e.textContent = text; } return e; };
			var safe = function (u) { try { var x = new URL(u, location.href); return /^https?:$/.test(x.protocol) ? x.href : ''; } catch (e) { return ''; } };
			var clear = function () { shown = ''; res.hidden = true; res.textContent = ''; res.removeAttribute('aria-busy'); };
			var scope = function () { var c = $('input[name="post_type"]:checked', f); return c ? c.value : ''; };
			var qs = function (q, final) {
				var p = new URLSearchParams();
				p.set('s', q);
				if (scope()) { p.set('post_type', scope()); }
				var lg = $('input[name="lang"]', f);
				if (lg) { p.set('lang', lg.value); }
				p.set('mcupr_live', '1');
				if (final) { p.set('final', '1'); }
				return p.toString();
			};
			var render = function (d) {
				res.textContent = '';
				res.removeAttribute('aria-busy');
				res.appendChild(el('p', 'hs-count', d.total ? fmt(T.count, d.total) : fmt(T.none, d.q)));
				if (d.items && d.items.length) {
					var ul = el('ul', 'hs-list');
					d.items.forEach(function (it) {
						var href = safe(it.u);
						if (!href) { return; }
						var li = el('li'), a = el('a', 'hs-item'), tx = el('span', 'hs-text');
						a.href = href;
						var th = it.i ? safe(it.i) : '';
						if (th) { var im = el('img', 'hs-thumb'); im.src = th; im.alt = ''; im.width = 52; im.height = 52; im.loading = 'lazy'; im.decoding = 'async'; a.appendChild(im); }
						if (it.k) { tx.appendChild(el('span', 'hs-k', it.k)); }
						tx.appendChild(el('span', 'hs-t', it.t));
						if (it.d) { tx.appendChild(el('span', 'hs-d', it.d)); }
						a.appendChild(tx); li.appendChild(a); ul.appendChild(li);
					});
					res.appendChild(ul);
				}
				if (d.total > (d.items || []).length && safe(d.all)) { var all = el('a', 'hs-all', fmt(T.all, d.total)); all.href = safe(d.all); res.appendChild(all); }
				res.hidden = false;
				shown = d.q;
			};
			var run = function (q, final) {
				if (q.length < 2) { clear(); return; }
				var key = qs(q, false);
				if (!final && cache[key]) { render(cache[key]); return; }
				if (ctl) { ctl.abort(); }
				ctl = window.AbortController ? new AbortController() : null;
				res.setAttribute('aria-busy', 'true');
				if (res.hidden) { res.textContent = ''; res.appendChild(el('p', 'hs-count', T.busy)); res.hidden = false; }
				fetch(base + '?' + qs(q, final), { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ctl ? ctl.signal : undefined })
					.then(function (r) { if (!r.ok || !/json/.test(r.headers.get('Content-Type') || '')) { throw new Error('bad'); } return r.json(); })
					.then(function (d) { cache[key] = d; if (input.value.trim() === q) { render(d); if (final) { res.scrollIntoView({ block: 'nearest', behavior: motionOn() ? 'smooth' : 'auto' }); } } })
					.catch(function (err) {
						if (err && err.name === 'AbortError') { return; }
						if (final) { res.textContent = ''; res.appendChild(el('p', 'hs-count', T.error)); res.hidden = false; window.setTimeout(function () { f.submit(); }, 600); } else { clear(); }
					});
			};
			rerun = function () { var q = input.value.trim(); if (q.length >= 2) { window.clearTimeout(timer); run(q, false); } };
			input.addEventListener('input', function () { window.clearTimeout(timer); var q = input.value.trim(); if (q.length < 2) { if (ctl) { ctl.abort(); } clear(); return; } timer = window.setTimeout(function () { run(q, false); }, 280); });
			f.addEventListener('submit', function (e) {
				var q = input.value.trim();
				if (q.length < 2) { return; } /* too short for a live answer: the normal search page */
				e.preventDefault();
				window.clearTimeout(timer);
				run(q, true);
				if (document.activeElement === input) { input.blur(); } /* the keyboard goes away, the results are in view */
			});
			input.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' && !res.hidden) { var first = $('a', res); if (first) { e.preventDefault(); first.focus(); } } });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !res.hidden && box.hidden && (f.contains(document.activeElement) || res.contains(document.activeElement))) { clear(); input.focus(); } });
		}

		function set(open) { box.hidden = !open; b.setAttribute('aria-expanded', String(open)); }
		function sync() { var c = $('input[name="post_type"]:checked', f); if (c && c.value !== '') { b.setAttribute('data-on', ''); } else { b.removeAttribute('data-on'); } }
		b.addEventListener('click', function () { set(box.hidden); });
		f.addEventListener('change', function (e) { if (e.target && e.target.name === 'post_type') { sync(); set(false); b.focus(); rerun(); } });
		f.addEventListener('submit', function (e) { if (e.defaultPrevented) { return; } var c = $('input[name="post_type"]:checked', f); if (c && c.value === '') { c.disabled = true; } }); /* "everything" adds nothing to the address */
		window.addEventListener('pageshow', function () { $$('input[name="post_type"]', f).forEach(function (r) { r.disabled = false; }); sync(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) { set(false); b.focus(); } });
		document.addEventListener('click', function (e) { if (!box.hidden && !f.contains(e.target)) { set(false); } });
		sync();
	});

	/* ---------------------------------------------------------------- navigation */
	var nav = $('#site-nav');
	var navBtn = $('.nav-toggle');
	var fab = $('#nav-fab');
	var dockBtn = $('[data-dock-menu]');
	var overlay = $('#nav-overlay');
	var desktopMq = window.matchMedia ? window.matchMedia('(min-width: 1100px)') : { matches: true };

	function setNav(open, returnFocus) {
		if (!nav || !navBtn) { return; }
		nav.classList.toggle('is-open', open);
		navBtn.setAttribute('aria-expanded', String(open));
		navBtn.setAttribute('aria-label', navBtn.getAttribute(open ? 'data-label-close' : 'data-label-open') || '');
		if (fab) { fab.setAttribute('aria-expanded', String(open)); fab.setAttribute('aria-label', navBtn.getAttribute(open ? 'data-label-close' : 'data-label-open') || ''); }
		if (dockBtn) { dockBtn.setAttribute('aria-expanded', String(open)); dockBtn.setAttribute('aria-label', navBtn.getAttribute(open ? 'data-label-close' : 'data-label-open') || ''); }
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
		if (dockBtn) { dockBtn.addEventListener('click', function () { setNav(navBtn.getAttribute('aria-expanded') !== 'true', false); }); }

		/* sidebar rail: collapse / expand (remembered) */
		var sbBtn = $('[data-sb-toggle]', nav);
		if (sbBtn) {
			var sbLabel = $('[data-sb-toggle-label]', sbBtn);
			var syncSb = function () {
				var min = root.classList.contains('sb-min');
				sbBtn.setAttribute('aria-expanded', String(!min));
				var lab = sbLabel ? (min ? (sbLabel.getAttribute('data-expand') || '') : (sbLabel.getAttribute('data-collapse') || '')) : '';
				if (sbLabel) { sbLabel.textContent = lab; }
				sbBtn.setAttribute('aria-label', lab);
				sbBtn.setAttribute('title', lab);
				/* collapsed: the labels are hidden, so give each icon a tooltip */
				$$('.menu > li > a', nav).forEach(function (a) {
					var t = $('.t', a) || a;
					if (min) { a.setAttribute('title', (t.textContent || '').trim()); } else { a.removeAttribute('title'); }
				});
			};
			var sbTimer = 0;
			sbBtn.addEventListener('click', function () {
				if (motionOn()) { root.classList.add('sb-anim'); clearTimeout(sbTimer); sbTimer = setTimeout(function () { root.classList.remove('sb-anim'); }, 700); }
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

		/* only one dropdown / mega panel open at a time (desktop): opening one closes the others */
		function closeOthers(li) {
			if (!desktopMq.matches) { return; }
			$$('.site-nav li.is-open', nav).forEach(function (o) {
				if (o === li || o.contains(li) || li.contains(o)) { return; }
				o.classList.remove('is-open');
				var t = o.querySelector(':scope > .submenu-toggle');
				if (t) { t.setAttribute('aria-expanded', 'false'); }
			});
		}

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
				if (open) { closeOthers(li); }
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
						if (open) { closeOthers(more); }
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

	/* the page band is pulled up under the floating header: keep --hdr-h (header height + its top margin) current */
	var hdr = document.getElementById('masthead');
	if (hdr && window.ResizeObserver) {
		var syncHdr = function () { root.style.setProperty('--hdr-h', (hdr.offsetHeight + (parseFloat(getComputedStyle(hdr).marginTop) || 0)) + 'px'); };
		new ResizeObserver(syncHdr).observe(hdr);
	}

	/* ---------------------------------------------------------------- home slider (up to 3 slides; no JavaScript = the first slide) */
	var heroBox = $('[data-hero]');
	if (heroBox) {
		var hSlides = $$('[data-slide]', heroBox);
		var hBgs = [$(':scope > img', heroBox)].concat($$(':scope > .hero-bg', heroBox)).filter(Boolean);
		var hDots = $$('[data-hero-dot]', heroBox);
		var hCtl = $('[data-hero-ctl]', heroBox);
		var hPause = $('[data-hero-pause]', heroBox);
		var hIdx = 0;
		var hTimer = 0;
		var hStopped = false;
		var hShow = function (n) {
			hIdx = (n + hSlides.length) % hSlides.length;
			hSlides.forEach(function (el, i) { el.classList.toggle('is-active', i === hIdx); });
			hBgs.forEach(function (el, i) { el.classList.toggle('is-active', i === hIdx); });
			hDots.forEach(function (d, i) { if (i === hIdx) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); } });
		};
		var hAuto = function () {
			window.clearInterval(hTimer);
			if (heroBox.getAttribute('data-auto') === '1' && !hStopped && motionOn() && !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) {
				hTimer = window.setInterval(function () { hShow(hIdx + 1); }, 7000);
			}
		};
		hBgs.forEach(function (el, i) { el.classList.toggle('is-active', i === 0); });
		if (hCtl) { hCtl.hidden = false; }
		heroBox.setAttribute('data-js', '1');
		var hPrev = $('[data-hero-prev]', heroBox);
		var hNext = $('[data-hero-next]', heroBox);
		if (hPrev) { hPrev.addEventListener('click', function () { hShow(hIdx - 1); hAuto(); }); }
		if (hNext) { hNext.addEventListener('click', function () { hShow(hIdx + 1); hAuto(); }); }
		hDots.forEach(function (d, i) { d.addEventListener('click', function () { hShow(i); hAuto(); }); });
		if (heroBox.getAttribute('data-auto') === '1' && hPause) {
			hPause.hidden = false;
			hPause.addEventListener('click', function () {
				hStopped = !hStopped;
				hPause.setAttribute('aria-pressed', String(hStopped));
				hAuto();
			});
		}
		heroBox.addEventListener('mouseenter', function () { window.clearInterval(hTimer); });
		heroBox.addEventListener('mouseleave', hAuto);
		heroBox.addEventListener('focusin', function () { window.clearInterval(hTimer); });
		hAuto();
	}

	/* ---------------------------------------------------------------- full-screen menu (built on first use from the main menu) */
	var fsBtn = $('[data-fs-open]');
	if (fsBtn && nav) {
		var fsBox = null;
		var fsLast = null;
		var buildFs = function () {
			var src = $('.menu', nav);
			if (!src) { return null; }
			var box = document.createElement('div');
			box.id = 'fs-menu';
			box.className = 'fs-menu';
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');
			box.setAttribute('aria-label', i18n.allMenu || 'All menu');
			box.hidden = true;
			var site = ($('.brand .t b') || $('.brand') || {}).textContent || '';
			var esc = function (t) { return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); };
			var head = '<div class="fs-head"><span class="fs-title"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>' + esc(i18n.allMenu || 'All menu') + '</span>'
				+ '<label class="fs-find"><span class="sr-only">' + esc(i18n.fsFind || 'Search the menu') + '</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" data-fs-find placeholder="' + esc(i18n.fsFind || 'Search the menu') + '" autocomplete="off"></label>'
				+ '<button type="button" class="fs-close" data-fs-close aria-label="' + esc(i18n.closeMenu || 'Close') + '"><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button></div>';
			box.innerHTML = head + '<nav class="fs-body" aria-label="' + esc(i18n.allMenu || 'All menu') + '"></nav>';
			var list = document.createElement('ul');
			list.className = 'fs-list';
			var top = src.cloneNode(true);
			/* drop everything that only makes sense in the bar: toggles, "More", ids, state classes */
			$$('.submenu-toggle, .menu-more', top).forEach(function (n) { n.remove(); });
			$$('[id]', top).forEach(function (n) { n.removeAttribute('id'); });
			$$('li', top).forEach(function (li) { li.classList.remove('is-open', 'current-menu-item'); });
			$$('a[aria-current]', top).forEach(function (a) { a.removeAttribute('aria-current'); });
			/* the items the priority+ bar moved into "More" are still in the real menu's "More" list: bring them back */
			var more = $(':scope > .menu-more > .sub-menu', src);
			if (more) { $$(':scope > li', more).forEach(function (li) { top.appendChild(li.cloneNode(true)); }); $$('.submenu-toggle', top).forEach(function (n) { n.remove(); }); }
			$$('[id]', top).forEach(function (n) { n.removeAttribute('id'); });
			$$('li.is-open', top).forEach(function (li) { li.classList.remove('is-open'); });
			while (top.firstChild) { list.appendChild(top.firstChild); }
			/* the items with nothing under them (home, contact …) are not cards: they become round buttons in the corner of the foot */
			var rounds = document.createElement('div');
			rounds.className = 'fs-round';
			$$(':scope > li', list).forEach(function (li) {
				var a = $(':scope > a', li);
				var ic = a ? $('.ic', a) : null;
				if (!a || !ic || $('ul', li) || !a.getAttribute('href')) { return; }
				var label = a.textContent.trim();
				var b = document.createElement('a');
				b.className = 'fs-rb';
				b.href = a.getAttribute('href');
				b.setAttribute('aria-label', label);
				b.setAttribute('data-label', label);
				b.innerHTML = ic.innerHTML;
				rounds.appendChild(b);
				li.remove();
			});
			/* the split: the groups as a rail on the left, the chosen group (its sections as cards) on the right */
			var isLabel = function (a) { return a && (!a.getAttribute('href') || a.classList.contains('menu-label')); };
			var rail = document.createElement('ul');
			rail.className = 'fs-list';
			rail.setAttribute('role', 'tablist');
			rail.setAttribute('aria-orientation', 'vertical');
			var panes = document.createElement('div');
			panes.className = 'fs-panes';
			var tabs = [], pns = [], titles = [], start0 = 0;
			$$(':scope > li', list).forEach(function (li, i) {
				var a = $(':scope > a', li), sub = $(':scope > ul', li), title = a ? a.textContent.replace(/\s+/g, ' ').trim() : '';
				var ic = a ? $('.ic', a) : null, href = a ? a.getAttribute('href') : '', n = 0, groups = [], cur = null;
				if (li.classList.contains('current-menu-ancestor') || li.classList.contains('current-menu-parent') || li.classList.contains('current-menu-item')) { start0 = i; }
				/* the second level is a section (with its links under it) or a plain link; plain links in a row share one card */
				if (sub) { var loose = null; $$(':scope > li', sub).forEach(function (sli) { var sa = $(':scope > a', sli), inner = $(':scope > ul', sli); if (inner && sa) { loose = null; groups.push({ t: sa.textContent.replace(/\s+/g, ' ').trim(), href: sa.getAttribute('href') || '', items: $$(':scope > li', inner) }); } else if (isLabel(sa) && sa) { loose = null; groups.push({ t: sa.textContent.replace(/\s+/g, ' ').trim(), items: [] }); } else { if (!loose) { loose = { t: '', items: [] }; groups.push(loose); } loose.items.push(sli); } }); }
				var tid = 'fs-tab-' + i, pid = 'fs-pane-' + i;
				var tli = document.createElement('li');
				tli.setAttribute('role', 'presentation');
				var tb = document.createElement('button');
				tb.type = 'button'; tb.className = 'fs-tab'; tb.id = tid;
				tb.setAttribute('role', 'tab'); tb.setAttribute('aria-controls', pid); tb.setAttribute('aria-selected', 'false'); tb.tabIndex = -1;
				if (ic) { tb.appendChild(ic.cloneNode(true)); }
				var tn = document.createElement('span'); tn.className = 'fs-tn'; tn.textContent = title; tb.appendChild(tn);
				var pane = document.createElement('section');
				pane.className = 'fs-pane'; pane.id = pid; pane.hidden = true;
				pane.setAttribute('role', 'tabpanel'); pane.setAttribute('aria-labelledby', tid);
				var ph = document.createElement('h2');
				if (href && href !== '#') { var pa = document.createElement('a'); pa.href = href; pa.textContent = title; ph.appendChild(pa); } else { ph.textContent = title; }
				pane.appendChild(ph);
				var gw = document.createElement('div'); gw.className = 'fs-groups';
				groups.forEach(function (g) {
					var gd = document.createElement('div'); gd.className = 'fs-g';
					if (g.t) { var h3 = document.createElement('h3'); if (g.href && g.href !== '#') { var ha = document.createElement('a'); ha.href = g.href; ha.textContent = g.t; h3.appendChild(ha); } else { h3.textContent = g.t; } gd.appendChild(h3); }
					var gu = document.createElement('ul');
					g.items.forEach(function (sli) { gu.appendChild(sli); });
					gd.appendChild(gu); gw.appendChild(gd);
				});
				pane.appendChild(gw);
				n = $$('a[href]', gw).length;
				var tc = document.createElement('span'); tc.className = 'fs-tc'; tc.textContent = String(n); tc.setAttribute('aria-hidden', 'true'); tb.appendChild(tc);
				tli.appendChild(tb); rail.appendChild(tli); panes.appendChild(pane);
				tabs.push(tb); pns.push(pane); titles.push(title);
			});
			var results = document.createElement('section');
			results.className = 'fs-pane fs-results'; results.hidden = true;
			panes.appendChild(results);
			var chosen = 0;
			var pick = function (i, focus) {
				chosen = i;
				tabs.forEach(function (t, k) { t.setAttribute('aria-selected', k === i ? 'true' : 'false'); t.tabIndex = k === i ? 0 : -1; pns[k].hidden = k !== i || !results.hidden; });
				if (focus) { tabs[i].focus(); }
			};
			tabs.forEach(function (t, i) {
				t.addEventListener('click', function () { pick(i, false); });
				t.addEventListener('mouseenter', function () { if (results.hidden) { pick(i, false); } });
				t.addEventListener('keydown', function (e) {
					var k = e.key, to = -1;
					if (k === 'ArrowDown' || k === 'ArrowRight') { to = (i + 1) % tabs.length; } else if (k === 'ArrowUp' || k === 'ArrowLeft') { to = (i - 1 + tabs.length) % tabs.length; } else if (k === 'Home') { to = 0; } else if (k === 'End') { to = tabs.length - 1; }
					if (to > -1) { e.preventDefault(); if (!results.hidden) { return; } pick(to, true); }
				});
			});
			var split = document.createElement('div');
			split.className = 'fs-split';
			split.appendChild(rail); split.appendChild(panes);
			$('.fs-body', box).appendChild(split);
			if (tabs.length) { pick(start0, false); }
			var none = document.createElement('p');
			none.className = 'fs-none'; none.hidden = true; none.textContent = i18n.fsNone || '';
			$('.fs-body', box).appendChild(none);
			/* the quick links (students, staff, alumni…) as a strip at the foot */
			var qsrc = $$('.hdr-util a, .nav-util a');
			if (qsrc.length) {
				var seen = {};
				var q = document.createElement('div');
				q.className = 'fs-quick';
				q.innerHTML = '<span>' + esc(i18n.quickLinks || '') + '</span>';
				qsrc.forEach(function (a) {
					var key = a.getAttribute('href') + '|' + a.textContent.trim();
					if (seen[key]) { return; }
					seen[key] = true;
					var c = document.createElement('a');
					c.href = a.getAttribute('href'); c.textContent = a.textContent.trim();
					if (a.target) { c.target = a.target; c.rel = 'noopener'; }
					q.appendChild(c);
				});
				box.appendChild(q);
			}
			if (rounds.children.length) {
				var foot = $('.fs-quick', box);
				if (!foot) { foot = document.createElement('div'); foot.className = 'fs-quick'; box.appendChild(foot); }
				foot.appendChild(rounds);
			}
			var find = $('[data-fs-find]', box);
			find.addEventListener('input', function () {
				var t = find.value.trim().toLowerCase(), any = false;
				split.classList.toggle('is-searching', !!t);
				if (!t) {
					results.hidden = true; results.textContent = ''; none.hidden = true;
					tabs.forEach(function (tb) { tb.classList.remove('is-dim'); });
					pick(chosen, false);
					return;
				}
				results.textContent = '';
				var gw = document.createElement('div'); gw.className = 'fs-groups';
				pns.forEach(function (p, i) {
					var catHit = titles[i].toLowerCase().indexOf(t) !== -1;
					var hits = $$('a[href]', p).filter(function (l) { return !l.closest('h2') && (catHit || (l.textContent || '').toLowerCase().indexOf(t) !== -1); });
					tabs[i].classList.toggle('is-dim', !hits.length);
					if (!hits.length) { return; }
					any = true;
					var gd = document.createElement('div'); gd.className = 'fs-g';
					var h3 = document.createElement('h3'); h3.textContent = titles[i]; gd.appendChild(h3);
					var gu = document.createElement('ul');
					hits.forEach(function (l) { var li = document.createElement('li'); var c = document.createElement('a'); c.href = l.getAttribute('href'); c.textContent = l.textContent.replace(/\s+/g, ' ').trim(); if (l.target) { c.target = l.target; c.rel = 'noopener'; } li.appendChild(c); gu.appendChild(li); });
					gd.appendChild(gu); gw.appendChild(gd);
				});
				results.appendChild(gw);
				results.hidden = !any; none.hidden = any;
				pns.forEach(function (p) { p.hidden = true; });
			});
			box.setAttribute('data-site', site.trim());
			document.body.appendChild(box);
			box.addEventListener('click', function (e) {
				if (e.target.closest('[data-fs-close]') || e.target.closest('a[href]')) { closeFs(e.target.closest('a[href]') ? false : true); }
			});
			return box;
		};
		var fsFocusable = function () { return $$('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled])', fsBox).filter(function (n) { return n.offsetParent !== null; }); };
		var openFs = function () {
			fsBox = fsBox || buildFs();
			if (!fsBox) { return; }
			fsLast = document.activeElement;
			// the menu grows out of the "All menu" button and its cards rise one after another
			var r = fsBtn.getBoundingClientRect();
			fsBox.style.setProperty('--fs-x', Math.round(r.left + r.width / 2) + 'px');
			fsBox.style.setProperty('--fs-y', Math.round(r.top + r.height / 2) + 'px');
			$$('.fs-list > li', fsBox).forEach(function (li, i) { li.style.setProperty('--i', Math.min(i, 11)); });
			fsBox.hidden = false;
			root.classList.add('fs-open-lock');
			fsBtn.setAttribute('aria-expanded', 'true');
			requestAnimationFrame(function () { requestAnimationFrame(function () { fsBox.classList.add('is-in'); var c = $('[data-fs-close]', fsBox); if (c) { c.focus({ preventScroll: true }); } }); });
		};
		var closeFs = function (restore) {
			if (!fsBox || fsBox.hidden) { return; }
			root.classList.remove('fs-open-lock');
			fsBtn.setAttribute('aria-expanded', 'false');
			var hideIt = function () { if (!fsBox.classList.contains('is-in')) { fsBox.hidden = true; } };
			fsBox.classList.remove('is-in');
			if (!motionOn()) { fsBox.hidden = true; } else { // let it shrink back into the button, then take it out
				var done = function (e) { if (e && e.target !== fsBox) { return; } fsBox.removeEventListener('transitionend', done); hideIt(); };
				fsBox.addEventListener('transitionend', done);
				setTimeout(hideIt, 650);
			}
			if (restore !== false && fsLast && fsLast.focus) { fsLast.focus({ preventScroll: true }); }
		};
		fsBtn.addEventListener('click', function () { if (fsBox && fsBox.classList.contains('is-in')) { closeFs(); } else { openFs(); } }); /* "open" means is-in: while it is still shrinking away a press opens it again */
		document.addEventListener('keydown', function (e) {
			if (!fsBox || fsBox.hidden) { return; }
			if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); closeFs(); return; }
			if (e.key === 'Tab') { trapTab(e, fsFocusable()); }
		}, true);
		if (desktopMq.addEventListener) { desktopMq.addEventListener('change', function () { closeFs(false); }); }
	}

	/* ---------------------------------------------------------------- click-to-load Google Map (footer) */
	$$('[data-map]').forEach(function (box) {
		var btn = $('[data-map-load]', box);
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var f = document.createElement('iframe');
			f.src = box.getAttribute('data-src');
			f.title = box.getAttribute('data-title') || 'Map';
			f.loading = 'lazy';
			f.referrerPolicy = 'no-referrer-when-downgrade';
			f.className = 'fmap-frame';
			box.textContent = '';
			box.appendChild(f);
			box.classList.add('is-loaded');
		});
	});

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

	/* ---------------------------------------------------------------- weather bar: the scrolling text can be paused */
	$$('[data-wx]').forEach(function (box) {
		var btn = $('[data-wx-pause]', box);
		if (!btn) { return; }
		btn.addEventListener('click', function () {
			var on = btn.getAttribute('aria-pressed') !== 'true';
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			btn.setAttribute('aria-label', btn.getAttribute(on ? 'data-l-play' : 'data-l-pause'));
			box.classList.toggle('is-paused', on);
		});
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
	/* Press and hold (or right-click / arrow up) on the button: the quick links pop up above it. */
	var quickPop = null;
	function closeQuick() { if (quickPop) { quickPop.remove(); quickPop = null; } }
	function openQuick() {
		closeQuick();
		var top = $('#site-nav ul');
		var src = $$('.util-panel a').concat(top ? $$('li > a', top).filter(function (a) { return a.closest('ul') === top; }) : []);
		var seen = {};
		var links = src.filter(function (a) { var k = a.getAttribute('href'); if (!k || seen[k]) { return false; } seen[k] = 1; return true; }).slice(0, 8);
		if (!links.length) { return false; }
		quickPop = document.createElement('div');
		quickPop.className = 'top-quick';
		quickPop.setAttribute('role', 'group');
		quickPop.setAttribute('aria-label', i18n.quickLinks || 'Quick links');
		var ul = document.createElement('ul');
		links.forEach(function (a) {
			var li = document.createElement('li');
			var c = document.createElement('a');
			c.href = a.getAttribute('href');
			c.textContent = (a.textContent || '').trim();
			li.appendChild(c);
			ul.appendChild(li);
		});
		quickPop.appendChild(ul);
		document.body.appendChild(quickPop);
		var f = $('a', quickPop);
		if (f) { f.focus(); }
		return true;
	}
	if (topBtn) {
		var holdTimer = 0;
		var held = false;
		var startHold = function () { held = false; clearTimeout(holdTimer); holdTimer = setTimeout(function () { held = openQuick(); }, 550); };
		var endHold = function () { clearTimeout(holdTimer); };
		topBtn.addEventListener('pointerdown', function (e) { if (e.button === 0) { startHold(); } });
		['pointerup', 'pointerleave', 'pointercancel'].forEach(function (n) { topBtn.addEventListener(n, endHold); });
		topBtn.addEventListener('contextmenu', function (e) { e.preventDefault(); held = openQuick(); });
		topBtn.addEventListener('keydown', function (e) { if (e.key === 'ArrowUp' || (e.shiftKey && e.key === 'F10')) { e.preventDefault(); held = openQuick(); } });
		document.addEventListener('click', function (e) { if (quickPop && !quickPop.contains(e.target) && !topBtn.contains(e.target)) { closeQuick(); } });
		document.addEventListener('keydown', function (e) {
			if (!quickPop) { return; }
			if (e.key === 'Escape') { e.preventDefault(); closeQuick(); topBtn.focus(); }
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				var items = $$('a', quickPop);
				var i = items.indexOf(document.activeElement);
				items[(i + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length].focus();
			}
		});
		window.addEventListener('scroll', closeQuick, { passive: true });
		topBtn.addEventListener('click', function (e) {
			if (held) { held = false; e.preventDefault(); return; }
			closeQuick();
			smoothScrollTo(0);
			var h = $('#masthead') || document.body;
			var first = $('a, button', h);
			if (first) { first.focus({ preventScroll: true }); }
		});
	}

	/* ---------------------------------------------------------------- option tiles: tooltip stays inside the dialog */
	// (the dialog is built on demand, so this listens on the document and creates the tip the first time)
	function placeTip(o) {
		var tip = o.querySelector('.opt-tip');
		if (!tip) {
			tip = document.createElement('span');
			tip.className = 'opt-tip';
			tip.setAttribute('aria-hidden', 'true');
			tip.textContent = o.getAttribute('data-tip');
			o.appendChild(tip);
		}
		tip.style.setProperty('--tx', '0px');
		// the nearest ancestor that clips (the dialog's scrolling body), not the dialog itself
		var box = o.parentElement;
		while (box && box !== document.body && !/(auto|hidden|scroll|clip)/.test(getComputedStyle(box).overflowX)) { box = box.parentElement; }
		if (!box || box === document.body) { box = document.documentElement; }
		var b = box.getBoundingClientRect();
		tip.classList.remove('is-below');
		var r = tip.getBoundingClientRect();
		var lo = Math.max(b.left, 0) + 8, hi = Math.min(b.right, window.innerWidth) - 8;
		var dx = 0;
		if (r.left < lo) { dx = lo - r.left; } else if (r.right > hi) { dx = hi - r.right; }
		tip.style.setProperty('--tx', Math.round(dx) + 'px');
		if (r.top < b.top + 2) { tip.classList.add('is-below'); }
	}
	['pointerover', 'focusin'].forEach(function (n) {
		document.addEventListener(n, function (e) {
			var o = e.target.closest && e.target.closest('.opt[data-tip]');
			if (o) { placeTip(o); }
		});
	});

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
	/* Animated icons (Lordicon, chosen in the Customizer): each .lord holds the normal SVG; once the player is loaded the moving icon
	   takes its place. If the player or the icon file cannot be loaded, or animations are off, the normal SVG stays. */
	var Lord = (function () {
		var loading = null;
		function player() {
			if (!loading) {
				loading = i18n.lordUrl ? import(i18n.lordUrl).then(function () { return customElements.whenDefined('lord-icon'); }) : Promise.reject(new Error('no player'));
			}
			return loading;
		}
		function make(box) {
			if (box.getAttribute('data-lord-state')) { return; }
			box.setAttribute('data-lord-state', 'wait');
			var icon = document.createElement('lord-icon');
			icon.setAttribute('src', box.getAttribute('data-lord'));
			icon.setAttribute('trigger', box.getAttribute('data-lord-trigger') || 'hover');
			icon.setAttribute('current-color', '');
			icon.setAttribute('aria-hidden', 'true');
			var host = box.closest('a,button');
			if (host) { icon.target = host; }
			icon.addEventListener('ready', function () { box.setAttribute('data-lord-state', 'live'); });
			icon.addEventListener('error', function () { box.setAttribute('data-lord-state', 'fail'); icon.remove(); });
			box.appendChild(icon);
		}
		function sync() {
			if (!i18n.lordUrl || !motionOn()) { return; }
			var boxes = $$('.lord[data-lord]').filter(function (b) { return !b.getAttribute('data-lord-state'); });
			if (!boxes.length) { return; }
			player().then(function () { boxes.forEach(make); }, function () { /* the normal icons stay */ });
		}
		return { sync: sync };
	})();

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
					label.innerHTML = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
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

	/* The cookie / PDPA banner, the settings and what follows from the choice are in cookie-center.js (inc/cookie-center.php). */

	/* ---- Finder-style news browser: view switcher (remembered) + source-list filter ---- */
	var fb = $('[data-fb]');
	if (fb) {
		var fbItems = $('[data-fb-items]', fb);
		var fbBtns = $$('[data-fb-view]', fb);
		fbBtns.forEach(function (b) {
			b.addEventListener('click', function () {
				var v = b.getAttribute('data-fb-view');
				if (fbItems) { fbItems.setAttribute('data-view', v); }
				fb.setAttribute('data-view', v);
				fbBtns.forEach(function (o) { o.setAttribute('aria-checked', String(o === b)); });
				try { localStorage.setItem('mcupr_view', v); } catch (e) { /* ignore */ }
			});
			b.addEventListener('keydown', function (e) {
				var i = fbBtns.indexOf(b);
				var n = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? i + 1 : e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? i - 1 : -1;
				if (n < 0) { return; }
				e.preventDefault();
				var t = fbBtns[(n + fbBtns.length) % fbBtns.length];
				t.focus();
				t.click();
			});
		});
		/* phones: the source list starts folded (it is open in the HTML for no-JS) */
		var fbDrawer = $('.fb-drawer', fb);
		if (fbDrawer && window.matchMedia && window.matchMedia('(max-width: 899px)').matches) { fbDrawer.removeAttribute('open'); }
		if (fbDrawer && window.matchMedia) {
			window.matchMedia('(min-width: 900px)').addEventListener('change', function (e) { if (e.matches) { fbDrawer.setAttribute('open', ''); } });
		}
		/* the category tree: a button opens / closes the level below (open in the HTML for no-JS only when the current category is inside) */
		var fbSetNode = function (li, open) {
			var t = $(':scope > .fb-row > [data-fb-tog]', li), box = $(':scope > .fb-subwrap', li);
			if (!t || !box) { return; }
			t.setAttribute('aria-expanded', String(open));
			box.hidden = !open;
			t.setAttribute('aria-label', (open ? fbText.collapse : fbText.expand) + ' ' + t.getAttribute('data-name'));
		};
		var fbSide = $('.fb-side', fb);
		var fbTree = $('.fb-tree', fb);
		var fbText = { expand: fbTree ? fbTree.getAttribute('data-expand') : '', collapse: fbTree ? fbTree.getAttribute('data-collapse') : '' };
		if (fbSide) {
			fbSide.addEventListener('click', function (e) {
				var b = e.target.closest ? e.target.closest('[data-fb-tog]') : null;
				if (!b) { return; }
				fbSetNode(b.closest('li'), b.getAttribute('aria-expanded') !== 'true');
			});
		}
		var fbFind = $('[data-fb-filter]', fb);
		if (fbFind) {
			var fbZones = $$('[data-fb-zone]', fb);
			var fbWas = fbZones.map(function (z) { return z.hasAttribute('open'); });
			var fbNodeWas = new Map(), fbSearching = false;
			fbFind.addEventListener('input', function () {
				var q = fbFind.value.trim().toLowerCase();
				var shown = 0;
				if (q && !fbSearching) { $$('[data-fb-node]', fb).forEach(function (li) { var t = $(':scope > .fb-row > [data-fb-tog]', li); fbNodeWas.set(li, !!t && t.getAttribute('aria-expanded') === 'true'); }); }
				fbSearching = !!q;
				$$('[data-fb-item]', fb).forEach(function (li) {
					var hit = !q || li.textContent.toLowerCase().indexOf(q) !== -1;
					li.hidden = !hit;
					if (hit) { shown++; }
				});
				fbZones.forEach(function (z, i) {
					var any = $$('[data-fb-item]:not([hidden])', z).length > 0;
					z.hidden = !any;
					if (q) { z.open = any; } else { z.open = fbWas[i]; }
				});
				$$('.fb-h', fb).forEach(function (h) {
					var nxt = h.nextElementSibling, vis = false;
					while (nxt && !nxt.classList.contains('fb-h') && !nxt.classList.contains('fb-empty')) {
						if (!nxt.hidden && ($$('[data-fb-item]:not([hidden])', nxt).length || nxt.matches('[data-fb-item]'))) { vis = true; }
						nxt = nxt.nextElementSibling;
					}
					h.hidden = !vis;
				});
				$$('[data-fb-node]', fb).forEach(function (li) {
					if (q) { fbSetNode(li, !li.hidden); } else { fbSetNode(li, fbNodeWas.get(li)); }
				});
				var em = $('.fb-empty', fb);
				if (em) { em.hidden = shown > 0; }
			});
		}
	}

	/* ---- small screens: when the name beside the logo does not fit, show the logo alone ---- */
	var brandLink = $('#masthead .brand');
	if (brandLink) {
		var brandText = brandLink.lastElementChild;
		var fitBrand = function () {
			if (!brandText || brandText === brandLink.firstElementChild) { return; }
			if (window.innerWidth >= 700) { brandLink.classList.remove('is-compact'); brandLink.removeAttribute('aria-label'); return; }
			brandLink.classList.remove('is-compact');
			var b = $('b', brandText);
			var cut = false;
			[b, $('small', brandText)].forEach(function (el) {
				if (el && (el.scrollWidth > el.clientWidth + 1 || el.scrollHeight > el.clientHeight + 1)) { cut = true; }
			});
			if (brandLink.getBoundingClientRect().right > (brandLink.parentNode.getBoundingClientRect().right + 1)) { cut = true; }
			brandLink.classList.toggle('is-compact', cut);
			/* only the logo is left: the link needs a name of its own (otherwise the visible text is its name) */
			if (cut) { brandLink.setAttribute('aria-label', brandLink.getAttribute('data-name') || ''); } else { brandLink.removeAttribute('aria-label'); }
		};
		fitBrand();
		if (window.ResizeObserver) { new ResizeObserver(fitBrand).observe(document.documentElement); } else { window.addEventListener('resize', fitBrand); }
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fitBrand); }
	}

	/* ---- ring around the sidebar's expand button: a runner while the page loads, then the scroll progress ---- */
	var ringFoot = $('.sb-foot');
	var ring = ringFoot && $('.sb-ring', ringFoot);
	if (ring) {
		var rects = $$('rect', ring);
		var barRect = $('.sb-ring-bar', ring);
		var sizeRing = function () {
			var w = ringFoot.offsetWidth, h = ringFoot.offsetHeight;
			if (!w || !h) { return; }
			ring.setAttribute('width', w); ring.setAttribute('height', h);
			rects.forEach(function (r) {
				r.setAttribute('x', 1.5); r.setAttribute('y', 1.5);
				r.setAttribute('width', w - 3); r.setAttribute('height', h - 3);
				r.setAttribute('rx', (Math.min(w, h) - 3) / 2);
			});
		};
		var ringTick = false;
		var drawRing = function () {
			ringTick = false;
			var max = document.documentElement.scrollHeight - window.innerHeight;
			var p = max > 0 ? Math.max(0, Math.min(1, window.pageYOffset / max)) : 0;
			barRect.style.strokeDasharray = (p * 100).toFixed(2) + ' 100';
			barRect.style.visibility = p < 0.005 ? 'hidden' : 'visible'; /* no dot at the very top */
		};
		sizeRing(); drawRing();
		if (window.ResizeObserver) { new ResizeObserver(function () { sizeRing(); drawRing(); }).observe(ringFoot); } else { window.addEventListener('resize', sizeRing); }
		window.addEventListener('scroll', function () { if (!ringTick) { ringTick = true; window.requestAnimationFrame(drawRing); } }, { passive: true });
	}
	/* the "loading" state (set in <head>) ends when the page has finished loading */
	var doneLoading = function () { root.classList.remove('page-loading'); };
	if (document.readyState === 'complete') { doneLoading(); } else { window.addEventListener('load', doneLoading); }

	/* ---- quick links (student / staff / alumni…) tucked into one drop-down button ---- */
	$$('[data-util]').forEach(function (box) {
		var btn = $('[data-util-toggle]', box);
		var panel = $('.util-panel', box);
		if (!btn || !panel) { return; }
		var setUtil = function (open) {
			panel.hidden = !open;
			btn.setAttribute('aria-expanded', String(open));
		};
		btn.addEventListener('click', function (e) { e.stopPropagation(); setUtil(panel.hidden); });
		document.addEventListener('click', function (e) { if (!panel.hidden && !box.contains(e.target)) { setUtil(false); } });
		box.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); setUtil(false); btn.focus(); } });
	});

	/* ---- staff directory: live search over the cards ---- */
	$$('[data-people-find]').forEach(function (inp) {
		var grid = inp.closest('.people-find').nextElementSibling;
		if (!grid) { return; }
		inp.addEventListener('input', function () {
			var q = inp.value.trim().toLowerCase();
			$$('.person-card', grid).forEach(function (c) { c.hidden = !!q && (c.textContent || '').toLowerCase().indexOf(q) === -1; });
		});
	});

	/* ---- videos from other sites: nothing is loaded until the cover is pressed (then the player replaces it) ---- */
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('.vid[data-vid] .vid-play');
		if (!a) { return; }
		var box = a.closest('.vid');
		var src = box.getAttribute('data-embed');
		if (!src || !/^https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(src)) { return; }
		e.preventDefault();
		var f = document.createElement('iframe');
		f.src = src;
		f.title = box.getAttribute('data-title') || 'Video';
		f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
		f.allowFullscreen = true;
		f.className = 'vid-frame';
		f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		a.replaceWith(f);
		var n = $('.vid-note', box);
		if (n) { n.remove(); }
		box.classList.add('is-on');
		if (!window.__ccAuto) { f.focus(); } // not when the cookie choice showed it by itself
	});

	/* ---- Google Drive / Docs in a post: nothing is loaded from Google until "show here" is pressed ---- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('.gemb[data-gemb] .gemb-load');
		if (!b) { return; }
		var box = b.closest('.gemb');
		var src = box.getAttribute('data-embed');
		if (!src || !/^https:\/\/(drive|docs)\.google\.com\//.test(src)) { return; }
		var f = document.createElement('iframe');
		f.src = src;
		f.title = box.getAttribute('data-title') || 'Google';
		f.className = 'gemb-frame';
		f.loading = 'lazy';
		f.allowFullscreen = true;
		f.setAttribute('allow', 'fullscreen');
		f.setAttribute('referrerpolicy', 'no-referrer');
		f.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms');
		var card = $('.gemb-card', box);
		box.insertBefore(f, card.nextSibling);
		var note = $('.gemb-note', card);
		if (note) { note.hidden = true; }
		b.hidden = true;
		box.classList.add('is-on');
		if (!window.__ccAuto) { f.focus(); }
	});

	/* ---- FAQ: live search (opens the matching questions) ---- */
	$$('[data-faq]').forEach(function (box) {
		var inp = $('[data-faq-find]', box);
		if (!inp) { return; }
		var none = $('.faq-none', box);
		inp.addEventListener('input', function () {
			var q = inp.value.trim().toLowerCase();
			var shown = 0;
			$$('.faq-item', box).forEach(function (d) {
				var hit = !q || (d.textContent || '').toLowerCase().indexOf(q) !== -1;
				d.hidden = !hit;
				if (q && hit) { d.open = true; }
				if (hit) { shown++; }
			});
			$$('.faq-group', box).forEach(function (g) { g.hidden = !$$('.faq-item:not([hidden])', g).length; });
			if (none) { none.hidden = shown > 0; }
		});
	});

	/* ---- photo albums: accessible lightbox ---- */
	var lbBox = null;
	var lbLinks = [];
	var lbIndex = 0;
	var lbFrom = null;
	function lbShow(i) {
		lbIndex = (i + lbLinks.length) % lbLinks.length;
		var a = lbLinks[lbIndex];
		var img = $('.lb-img', lbBox);
		img.src = a.getAttribute('href');
		img.alt = a.getAttribute('data-cap') || '';
		$('.lb-cap', lbBox).textContent = a.getAttribute('data-cap') || '';
		$('.lb-count', lbBox).textContent = (lbIndex + 1) + ' / ' + lbLinks.length;
		var single = lbLinks.length < 2;
		$('.lb-prev', lbBox).hidden = single; $('.lb-next', lbBox).hidden = single;
	}
	function lbClose() {
		if (!lbBox || lbBox.hidden) { return; }
		lbBox.hidden = true;
		root.classList.remove('lb-open');
		if (lbFrom && lbFrom.focus) { lbFrom.focus(); }
	}
	function lbOpen(links, i, from) {
		if (!lbBox) {
			lbBox = document.createElement('div');
			lbBox.className = 'lb';
			lbBox.setAttribute('role', 'dialog');
			lbBox.setAttribute('aria-modal', 'true');
			lbBox.setAttribute('aria-label', i18n.lbClose || 'Picture');
			lbBox.hidden = true;
			lbBox.innerHTML = '<figure class="lb-fig"><img class="lb-img" alt=""><figcaption><span class="lb-cap"></span><span class="lb-count" aria-live="polite"></span></figcaption></figure>'
				+ '<button type="button" class="lb-btn lb-close" aria-label="' + (i18n.lbClose || 'Close') + '"><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>'
				+ '<button type="button" class="lb-btn lb-prev" aria-label="' + (i18n.lbPrev || 'Previous') + '"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
				+ '<button type="button" class="lb-btn lb-next" aria-label="' + (i18n.lbNext || 'Next') + '"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
			document.body.appendChild(lbBox);
			lbBox.addEventListener('click', function (e) {
				if (e.target.closest('.lb-close') || e.target === lbBox) { lbClose(); }
				else if (e.target.closest('.lb-prev')) { lbShow(lbIndex - 1); }
				else if (e.target.closest('.lb-next')) { lbShow(lbIndex + 1); }
			});
			var tx = null;
			lbBox.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
			lbBox.addEventListener('touchend', function (e) {
				if (tx === null) { return; }
				var dx = e.changedTouches[0].clientX - tx; tx = null;
				if (Math.abs(dx) > 50) { lbShow(lbIndex + (dx < 0 ? 1 : -1)); }
			}, { passive: true });
			document.addEventListener('keydown', function (e) {
				if (lbBox.hidden) { return; }
				if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); lbClose(); }
				else if (e.key === 'ArrowLeft') { lbShow(lbIndex - 1); }
				else if (e.key === 'ArrowRight') { lbShow(lbIndex + 1); }
				else if (e.key === 'Tab') { trapTab(e, $$('button', lbBox).filter(function (b) { return !b.hidden; })); }
			}, true);
		}
		lbLinks = links; lbFrom = from;
		lbBox.hidden = false;
		root.classList.add('lb-open');
		lbShow(i);
		$('.lb-close', lbBox).focus();
	}
	document.addEventListener('click', function (e) {
		var a = e.target.closest('[data-lightbox] a[data-lb]');
		if (!a) { return; }
		e.preventDefault();
		var links = $$('a[data-lb]', a.closest('[data-lightbox]'));
		lbOpen(links, links.indexOf(a), a);
	});

	/* Office menu on small screens: the tabs fold into one button (the list stays open without JS) */
	(function () {
		var nav = $('.of-tabs');
		var btn = nav && $('.of-tabs-btn', nav);
		if (!btn) { return; }
		nav.classList.add('js-tabs');
		function set(open) {
			nav.classList.toggle('is-open', open);
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
		btn.addEventListener('click', function () { set(!nav.classList.contains('is-open')); });
		nav.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) { set(false); btn.focus(); }
		});
	})();

	/* Work group page: the in-page menu marks the part being read (aria-current), and its height is kept in --gl-tabs-h so
	   a part or a focused element is not hidden under it (scroll-padding, see input.css) */
	(function () {
		var nav = $('[data-gl-tabs]');
		if (!nav) { return; }
		var links = $$('a[href^="#gl-"]:not(.gl-ask)', nav);
		var parts = links.map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); });
		if (window.ResizeObserver) {
			new ResizeObserver(function () { root.style.setProperty('--gl-tabs-h', nav.offsetHeight + 'px'); }).observe(nav);
		}
		var busy = false;
		var hold = 0;
		function setCur(cur) {
			links.forEach(function (a, i) { if (i === cur) { a.setAttribute('aria-current', 'true'); } else { a.removeAttribute('aria-current'); } });
		}
		/* a part chosen in the menu (or with "ask the staff") is the current one, even when the page cannot scroll it to the top */
		$$('a[href^="#gl-"]', nav).forEach(function (a) {
			a.addEventListener('click', function () {
				var i = links.map(function (l) { return l.getAttribute('href'); }).indexOf(a.getAttribute('href'));
				if (i >= 0) { setCur(i); hold = Date.now() + 1200; }
			});
		});
		function mark() {
			busy = false;
			if (Date.now() < hold) { return; }
			var nb = Math.max(0, nav.getBoundingClientRect().bottom); /* (the menu does not stick on small screens) */
			var line = nb + Math.min(120, Math.max(24, (window.innerHeight - nb) / 3)); /* a part is "being read" once its top passes this line */
			var cur = -1;
			parts.forEach(function (p, i) { if (p && p.getBoundingClientRect().top <= line) { cur = i; } });
			if (cur < 0) { cur = 0; }
			if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) { cur = parts.length - 1; } /* the end of the page: the last part */
			setCur(cur);
		}
		window.addEventListener('scroll', function () { if (!busy) { busy = true; window.requestAnimationFrame(mark); } }, { passive: true });
		window.addEventListener('resize', mark);
		mark();
	})();

	/* "I am…" picker above the home quick links: the links marked for other kinds of visitor are hidden; the choice is remembered. */
	(function () {
		var box = document.querySelector('[data-persona]');
		var picker = box && box.querySelector('[data-persona-picker]');
		var menu = box && box.querySelector('.hero-menu');
		if (!picker || !menu) { return; }
		var keys = box.getAttribute('data-persona').split(',');
		var btn = picker.querySelector('.persona-btn');
		var list = picker.querySelector('.persona-list');
		var opts = $$('[data-persona-opt]', list);
		var cur = picker.querySelector('[data-persona-cur]');
		var live = picker.querySelector('[data-persona-live]');
		var items = $$('li', menu).map(function (li) {
			return { li: li, tags: li.className.split(/\s+/).filter(function (c) { return c.indexOf('for-') === 0; }).map(function (c) { return c.slice(4); }) };
		});
		function label(k) { var o = opts.filter(function (x) { return x.getAttribute('data-persona-opt') === k; })[0]; return o ? o.textContent : k; }
		function show(k, user) {
			items.forEach(function (it) { it.li.hidden = it.tags.length > 0 && it.tags.indexOf(k) < 0; });
			opts.forEach(function (o) { o.setAttribute('aria-selected', String(o.getAttribute('data-persona-opt') === k)); });
			cur.textContent = label(k);
			menu.setAttribute('data-ready', '1');
			menu.classList.remove('persona-swap');
			void menu.offsetWidth;
			menu.classList.add('persona-swap');
			if (user) {
				try { localStorage.setItem('mcupr_persona', k); } catch (err) { /* ignore */ }
				live.textContent = (box.getAttribute('data-persona-shown') || '') + ' ' + label(k);
			}
		}
		function isOpen() { return !list.hidden; }
		function close(back) { list.hidden = true; btn.setAttribute('aria-expanded', 'false'); if (back) { btn.focus(); } }
		function open() {
			list.hidden = false;
			btn.setAttribute('aria-expanded', 'true');
			var sel = opts.filter(function (o) { return o.getAttribute('aria-selected') === 'true'; })[0] || opts[0];
			sel.focus();
		}
		var saved = '';
		try { saved = localStorage.getItem('mcupr_persona') || ''; } catch (err) { /* ignore */ }
		picker.hidden = false;
		show(keys.indexOf(saved) >= 0 ? saved : keys[0], false);
		btn.addEventListener('click', function () { if (isOpen()) { close(false); } else { open(); } });
		btn.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); } });
		list.addEventListener('click', function (e) {
			var o = e.target.closest('[data-persona-opt]');
			if (o) { show(o.getAttribute('data-persona-opt'), true); close(true); }
		});
		list.addEventListener('keydown', function (e) {
			var i = opts.indexOf(document.activeElement);
			if (e.key === 'ArrowDown') { e.preventDefault(); opts[Math.min(opts.length - 1, i + 1)].focus(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); opts[Math.max(0, i - 1)].focus(); }
			else if (e.key === 'Home') { e.preventDefault(); opts[0].focus(); }
			else if (e.key === 'End') { e.preventDefault(); opts[opts.length - 1].focus(); }
			else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (i >= 0) { show(opts[i].getAttribute('data-persona-opt'), true); close(true); } }
			else if (e.key === 'Escape') { e.preventDefault(); close(true); }
			else if (e.key === 'Tab') { close(false); }
		});
		document.addEventListener('click', function (e) { if (isOpen() && !picker.contains(e.target)) { close(false); } });
	})();

	apply();
	reveal();
})();
