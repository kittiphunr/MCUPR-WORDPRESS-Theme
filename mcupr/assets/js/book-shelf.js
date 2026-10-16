/* The bookshelf page: switch between shelves and a grid of covers (remembered), search, and "continue reading". */
(function () {
	'use strict';
	function store(k, v) { try { if (v === undefined) { return localStorage.getItem(k); } localStorage.setItem(k, v); } catch (e) { /* private mode */ } return null; }
	Array.prototype.forEach.call(document.querySelectorAll('.bk-shelf-app'), function (app) {
		var q = app.querySelector('#bk-q'), none = app.querySelector('.bk-none'), btns = app.querySelectorAll('.bk-vbtn');
		var saved = store('mcupr_bk_view');
		function setView(v, keep) {
			app.setAttribute('data-view', v);
			Array.prototype.forEach.call(btns, function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-view') === v ? 'true' : 'false'); });
			if (typeof pad === 'function') { setTimeout(pad, 0); }
			if (!keep) { store('mcupr_bk_view', v); }
		}
		// the last board of each shelf is completed with empty slots
		function pad() {
			Array.prototype.forEach.call(app.querySelectorAll('.bk-books'), function (ul) {
				Array.prototype.forEach.call(ul.querySelectorAll('.bk-pad'), function (x) { x.parentNode.removeChild(x); });
				if (app.getAttribute('data-view') !== 'shelf') { return; }
				var cols = getComputedStyle(ul).gridTemplateColumns.split(' ').length, n = ul.querySelectorAll('.bk-card:not([hidden])').length, miss = (cols - (n % cols)) % cols;
				for (var i = 0; i < miss; i++) {
					var li = document.createElement('li'), sp = document.createElement('span');
					li.className = 'bk-pad'; li.setAttribute('aria-hidden', 'true'); sp.className = 'bk-slot'; li.appendChild(sp); ul.appendChild(li);
				}
			});
		}
		var padT; window.addEventListener('resize', function () { clearTimeout(padT); padT = setTimeout(pad, 120); });
		if (saved === 'shelf' || saved === 'grid') { setView(saved, true); }
		Array.prototype.forEach.call(btns, function (b) { b.addEventListener('click', function () { setView(b.getAttribute('data-view')); }); });
		function filter() {
			var t = (q ? q.value : '').trim().toLowerCase(), shown = 0;
			Array.prototype.forEach.call(app.querySelectorAll('.bk-card'), function (c) {
				var hit = !t || (c.getAttribute('data-title') || '').indexOf(t) !== -1;
				c.hidden = !hit;
				if (hit) { shown++; }
			});
			Array.prototype.forEach.call(app.querySelectorAll('.bk-row'), function (r) { r.hidden = !r.querySelector('.bk-card:not([hidden])'); });
			if (none) { none.hidden = shown > 0; }
			pad();
		}
		if (q) { q.addEventListener('input', filter); }
		pad();
		// "continue reading": the page each book was left at
		var pos = {};
		try { pos = JSON.parse(store('mcupr_bk_pos') || '{}'); } catch (e) { pos = {}; }
		var tpl = (document.documentElement.getAttribute('data-bk-resume') || '').trim();
		Array.prototype.forEach.call(app.querySelectorAll('.bk-card'), function (c) {
			var p = pos[c.getAttribute('data-id')], s = c.querySelector('.bk-resume');
			if (p && p > 1 && s && app.getAttribute('data-resume')) {
				s.textContent = app.getAttribute('data-resume').replace('%d', p);
				s.hidden = false;
			}
		});
	});
})();
