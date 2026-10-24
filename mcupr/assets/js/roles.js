/*
 * MCUPR -> User roles: find a capability, tick a whole row or a whole column, and a word of warning before a role is
 * given something that could be used to take the site over. Everything here is a convenience: the page works without it.
 */
(function () {
	'use strict';
	var form = document.getElementById('mr-form');
	if (!form) { return; }
	var words = window.mcuprRoles || {};
	var boxes = Array.prototype.slice.call(form.querySelectorAll('.mr-cell input[type="checkbox"]'));
	var rows = Array.prototype.slice.call(form.querySelectorAll('.mr-row'));
	var dirty = form.querySelector('.mr-dirty');
	var empty = document.querySelector('.mr-empty');

	/* ---- say so when something is not saved yet */
	var touched = false;
	function markDirty() {
		if (touched) { return; }
		touched = true;
		if (dirty) { dirty.textContent = words.changed || ''; dirty.hidden = false; }
	}
	window.addEventListener('beforeunload', function (e) {
		if (!touched) { return; }
		e.preventDefault();
		e.returnValue = '';
	});
	form.addEventListener('submit', function () { touched = false; });

	/* ---- a word before handing out something sensitive */
	boxes.forEach(function (box) {
		box.addEventListener('change', function () {
			if (box.checked && box.getAttribute('data-risky') === '1' && words.confirmRisky && !window.confirm(words.confirmRisky)) {
				box.checked = false;
				return;
			}
			markDirty();
		});
	});

	/* ---- all / none for one capability across every role */
	form.querySelectorAll('.mr-rowall').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var cap = btn.getAttribute('data-cap');
			var mine = boxes.filter(function (b) { return b.getAttribute('data-cap') === cap && b.offsetParent !== null; });
			var every = mine.every(function (b) { return b.checked; });
			mine.forEach(function (b) { b.checked = !every; });
			markDirty();
		});
	});

	/* ---- all / none for one role down every capability that is on screen */
	form.querySelectorAll('.mr-col').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var role = btn.getAttribute('data-role');
			var mine = boxes.filter(function (b) {
				return b.getAttribute('data-role') === role && b.closest('tr').offsetParent !== null;
			});
			var every = mine.every(function (b) { return b.checked; });
			mine.forEach(function (b) {
				if (!every && b.getAttribute('data-risky') === '1') { return; } // never handed out in one press
				b.checked = !every;
			});
			markDirty();
		});
	});

	/* ---- find a capability */
	var search = document.getElementById('mr-search');
	if (search) {
		var run = function () {
			var q = search.value.trim().toLowerCase();
			var shown = 0;
			rows.forEach(function (row) {
				var hit = '' === q || (row.getAttribute('data-find') || '').indexOf(q) > -1;
				row.hidden = !hit;
				if (hit) { shown++; }
			});
			form.querySelectorAll('.mr-group').forEach(function (body) {
				var any = Array.prototype.slice.call(body.querySelectorAll('.mr-row')).some(function (r) { return !r.hidden; });
				var head = body.querySelector('.mr-group-row');
				if (head) { head.hidden = !any; }
			});
			if (empty) {
				empty.textContent = words.nothingFound || '';
				empty.hidden = shown > 0;
			}
		};
		search.addEventListener('input', run);
		search.addEventListener('search', run);
	}
})();
