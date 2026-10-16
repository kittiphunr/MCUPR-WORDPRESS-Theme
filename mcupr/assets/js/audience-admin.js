/* "Who can see this" box: show the rules only for students-only content, tick a faculty = all its majors,
   and say how many students (studying now) would see the content. */
(function () {
	'use strict';
	var box = document.querySelector('.mcupr-aud');
	if (!box) { return; }
	var rules = box.querySelector('.mcupr-aud-rules');
	var out = box.querySelector('.mcupr-aud-count');
	var combos = [];
	try { combos = JSON.parse(box.getAttribute('data-combos') || '[]'); } catch (e) { combos = []; }
	var total = parseInt(box.getAttribute('data-total') || '0', 10);
	var msg = (window.mcuprAud && window.mcuprAud.count) || '%1$d / %2$d';

	function ticked(group) {
		return Array.prototype.filter.call(box.querySelectorAll('input[data-g="' + group + '"]'), function (i) { return i.checked && !i.disabled; })
			.map(function (i) { return i.getAttribute('data-k'); });
	}
	function has(list, v) { return list.indexOf(v) !== -1; }
	function update() {
		var on = box.querySelector('input[name="mcupr_aud"][value="students"]').checked;
		rules.hidden = !on;
		if (!on) { out.hidden = true; return; }
		var lv = ticked('level'), yr = ticked('year'), fac = ticked('fac'), maj = ticked('maj');
		var n = 0;
		combos.forEach(function (c) {
			if (lv.length && !has(lv, c[0])) { return; }
			if (yr.length && !has(yr, c[1])) { return; }
			if ((fac.length || maj.length) && !(has(fac, c[2]) || has(maj, c[3]))) { return; }
			n += c[4];
		});
		out.textContent = msg.replace('%1$d', n).replace('%2$d', total);
		out.hidden = false;
	}
	box.addEventListener('change', function (e) {
		var t = e.target;
		if (t.matches('input[data-g="fac"]')) {
			// a ticked faculty covers all its majors: show them ticked and leave them out of the form
			Array.prototype.forEach.call(box.querySelectorAll('input[data-g="maj"][data-f="' + t.getAttribute('data-k').replace(/"/g, '\\"') + '"]'), function (m) {
				m.checked = t.checked;
				m.disabled = t.checked;
			});
			if (t.checked) { show(t.closest('.mcupr-aud-fac'), true); }
		}
		update();
	});
	box.addEventListener('click', function (e) {
		var b = e.target.closest('.mcupr-aud-tgl');
		if (!b) { return; }
		show(b.closest('.mcupr-aud-fac'), b.getAttribute('aria-expanded') !== 'true');
	});
	// the majors of a faculty: shown or folded (all shown without script)
	function show(row, open) {
		var b = row && row.querySelector('.mcupr-aud-tgl');
		if (!b) { return; }
		var list = document.getElementById(b.getAttribute('aria-controls'));
		if (list) { list.hidden = !open; }
		b.setAttribute('aria-expanded', open ? 'true' : 'false');
		b.firstElementChild.textContent = open ? '\u25BE' : '\u25B8';
	}
	Array.prototype.forEach.call(box.querySelectorAll('.mcupr-aud-fac'), function (row) { show(row, row.getAttribute('data-open') === '1'); });
	update();
})();
