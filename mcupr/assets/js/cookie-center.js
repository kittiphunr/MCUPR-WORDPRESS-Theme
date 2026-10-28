/*
 * Cookie centre (inc/cookie-center.php): the banner, the settings dialog, and what follows from the visitor's choice.
 * The first small script in the head has already read the choice (window.mcuprConsent) and, while preferences are not allowed,
 * keeps the theme's localStorage keys in sessionStorage. Here: save the choice (cookie mcupr_cc, one year), take away what is no
 * longer allowed, keep what the visitor set before saying yes, and show embedded content straight away when it is allowed.
 */
(function () {
	'use strict';
	var C = window.mcuprConsent;
	if (!C) { return; }
	var cfg = window.mcuprCc || {};
	var root = document.documentElement;
	var banner = document.getElementById('cookie-notice');
	var dlg = document.getElementById('cc-dialog');
	var opener = null;
	function $$(s, c) { return [].slice.call((c || document).querySelectorAll(s)); }

	function expire(name) {
		var paths = [C.path || '/', '/'];
		paths.forEach(function (p) { document.cookie = name + '=; Max-Age=0; Path=' + p + '; SameSite=Lax'; });
	}

	/* what the visitor set while preferences were not allowed (sessionStorage) is theirs to keep once they say yes */
	function keepSession() {
		try {
			for (var i = 0; i < sessionStorage.length; i++) {
				var k = sessionStorage.key(i);
				if (k && k.indexOf('mcupr_') === 0 && k !== 'mcupr_pl') { localStorage.setItem(k, sessionStorage.getItem(k)); }
			}
		} catch (e) { /* storage not available */ }
	}

	/* ---- embedded content of other websites: shown at once when allowed (never with sound or autoplay, never taking the focus) ---- */
	var io = null;
	var targets = '.gemb[data-gemb] .gemb-load, .vid[data-vid] .vid-play, [data-map] [data-map-load]';
	function fire(btn) {
		var box = btn.closest('.vid');
		if (box) {
			var src = box.getAttribute('data-embed') || '';
			box.setAttribute('data-embed', src.replace(/([?&])autoplay=1(&|$)/, function (m, a, b) { return b ? a : ''; }).replace(/[?&]$/, ''));
		}
		window.__ccAuto = true;
		try { btn.click(); } finally { window.__ccAuto = false; }
	}
	function autoEmbeds() {
		if (!C.has('embeds')) { return; }
		var list = $$(targets).filter(function (b) { return !b.dataset.ccDone; });
		if (!list.length) { return; }
		if (!('IntersectionObserver' in window)) { list.forEach(function (b) { b.dataset.ccDone = '1'; fire(b); }); return; }
		if (!io) {
			io = new IntersectionObserver(function (es) {
				es.forEach(function (e) {
					if (e.isIntersecting) { io.unobserve(e.target); if (C.has('embeds') && e.target.isConnected) { e.target.dataset.ccDone = '1'; fire(e.target); } }
				});
			}, { rootMargin: '300px 0px' });
		}
		list.forEach(function (b) { io.observe(b); });
	}

	/* prev = the choice before this one (null at the start of a page): cookies are only taken away when a yes is taken back, never on every page */
	function effects(o, prev) {
		if (o.p) { keepSession(); } else {
			C.purge();
			if (prev && prev.p) { expire('mcupr_lang'); expire('mcupr_welcome'); }
		}
		if (!o.s && prev && prev.s) { expire('mcupr_seen'); }
		autoEmbeds();
	}

	function save(p, s, e) {
		var o = { v: C.v, t: Math.floor(Date.now() / 1000), p: p ? 1 : 0, s: s ? 1 : 0, e: e ? 1 : 0 };
		C.write(o);
		var prev = C.state;
		C.state = o;
		root.classList.remove('needs-consent');
		effects(o, prev);
		try { document.dispatchEvent(new CustomEvent('mcupr:consent', { detail: o })); } catch (err) { /* old browser */ }
		return o;
	}
	/* the kinds the dialog asks about; the others are left as they are */
	function asked() { return $$('input[data-cc-cat]', dlg).map(function (i) { return i.name; }); }
	function allOn() { return save(1, 1, asked().indexOf('e') < 0 ? 0 : 1); }
	function say(t) { var n = dlg && dlg.querySelector('[data-cc-saved]'); if (n) { n.textContent = ''; window.setTimeout(function () { n.textContent = t || ''; }, 30); } }

	function fill() {
		if (!dlg) { return; }
		$$('input[data-cc-cat]', dlg).forEach(function (i) { i.checked = !!(C.state && C.state[i.name]); });
	}
	function open(from) {
		if (!dlg) { return; }
		opener = from || document.activeElement;
		fill();
		if (typeof dlg.showModal === 'function') { if (!dlg.open) { dlg.showModal(); } } else { dlg.setAttribute('open', ''); }
	}
	function close() {
		if (!dlg) { return; }
		if (typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
		if (opener && opener.focus && opener.isConnected && opener !== document.body) { opener.focus(); }
		opener = null;
	}

	document.addEventListener('click', function (e) {
		var t = e.target.closest && e.target.closest('[data-cc-open],[data-cc-accept],[data-cc-reject],[data-cc-save],[data-cc-close]');
		if (!t) {
			if (dlg && e.target === dlg) { close(); }
			return;
		}
		var inDlg = !!(dlg && dlg.contains(t));
		if (t.hasAttribute('data-cc-open')) { e.preventDefault(); open(t); return; }
		if (t.hasAttribute('data-cc-close')) { close(); return; }
		if (t.hasAttribute('data-cc-accept')) { allOn(); } else if (t.hasAttribute('data-cc-reject')) { save(0, 0, 0); } else if (t.hasAttribute('data-cc-save')) {
			var v = {};
			$$('input[data-cc-cat]', dlg).forEach(function (i) { v[i.name] = i.checked ? 1 : 0; });
			save(v.p, v.s, v.e);
		}
		if (inDlg) { say(cfg.saved); window.setTimeout(close, 350); }
	});
	if (dlg) {
		dlg.addEventListener('cancel', function () { opener = opener || null; });
		dlg.addEventListener('close', function () { if (opener && opener.focus && opener.isConnected) { opener.focus(); } opener = null; });
	}

	/* a choice made in another tab */
	window.addEventListener('focus', function () {
		var m = document.cookie.match(/(?:^|; )mcupr_cc=([^;]*)/);
		if (m && !C.state) { try { var o = JSON.parse(decodeURIComponent(m[1])); if (o && o.v === C.v) { C.state = o; root.classList.remove('needs-consent'); effects(o); } } catch (e) { /* */ } }
	});

	if (C.state) { effects(C.state); }
	window.mcuprConsent.open = open;
	window.mcuprConsent.save = save;
})();
