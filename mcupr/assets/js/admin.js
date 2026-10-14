/* Theme admin: the "Attached files" box (media picker, add / remove rows). */
(function () {
	'use strict';
	var box = document.getElementById('mcupr-files');
	var tpl = document.getElementById('mcupr-files-tpl');
	var add = document.getElementById('mcupr-files-add');
	if (!box || !tpl || !add) { return; }
	var next = parseInt(box.getAttribute('data-next'), 10) || 0;
	var L = window.mcuprAdmin || {};

	function pick(row) {
		if (!window.wp || !wp.media) { return; }
		var frame = wp.media({ title: L.choose || 'Choose a file', button: { text: L.use || 'Use this file' }, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			row.querySelector('[data-f=id]').value = a.id;
			row.querySelector('[data-f=name]').textContent = (a.title || '') + ' – ' + (a.filename || '');
			var t = row.querySelector('input[name$="[title]"]');
			if (t && !t.value) { t.value = a.title || ''; }
		});
		frame.open();
	}

	box.addEventListener('click', function (e) {
		var row = e.target.closest('.mcupr-file-row');
		if (!row) { return; }
		if (e.target.closest('[data-pick]')) { pick(row); }
		if (e.target.closest('[data-remove]')) { row.remove(); }
	});
	add.addEventListener('click', function () {
		var html = tpl.innerHTML.replace(/__i__/g, String(next++));
		var wrap = document.createElement('div');
		wrap.innerHTML = html;
		var row = wrap.firstElementChild;
		box.appendChild(row);
		pick(row);
	});
})();
