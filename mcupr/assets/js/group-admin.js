/* Work group editor (admin): repeating rows (services, documents) and the file picker of the Media Library. */
(function ($) {
	'use strict';
	var cfg = window.mcuprGroup || { pick: 'Choose a file', max: 8 };

	function limit(box) {
		var max = parseInt(box.attr('data-gr-max'), 10) || 0;
		if (max) { box.find('[data-gr-add]').prop('disabled', box.find('[data-gr-rows]').children().length >= max); }
	}
	$('[data-gr-rep]').each(function () { limit($(this)); });

	$(document).on('click', '[data-gr-add]', function () {
		var box = $(this).closest('[data-gr-rep]');
		var rows = box.find('[data-gr-rows]');
		var max = parseInt(box.attr('data-gr-max'), 10) || 0;
		if (max && rows.children().length >= max) { return; }
		var html = box.find('[data-gr-tpl]').html().replace(/__i__/g, 'n' + Date.now() + '_' + rows.children().length);
		rows.append(html);
		rows.children().last().find('input:not([type=hidden]),select').first().trigger('focus');
		limit(box);
	});
	$(document).on('click', '[data-gr-del]', function () {
		var box = $(this).closest('[data-gr-rep]');
		$(this).closest('[data-gr-row]').remove();
		limit(box);
		box.find('[data-gr-add]').trigger('focus');
	});

	var frame;
	$(document).on('click', '[data-gr-pick]', function (e) {
		e.preventDefault();
		var row = $(this).closest('[data-gr-row]');
		if (!window.wp || !wp.media) { return; }
		frame = wp.media({ title: cfg.pick, multiple: false });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			row.find('[data-gr-att]').val(a.id);
			row.find('[data-gr-fname]').text(a.filename || a.title || '');
			var t = row.find('input[name$="[title]"]');
			if (t.length && !t.val()) { t.val(a.title || ''); }
		});
		frame.open();
	});
	$(document).on('click', '[data-gr-unpick]', function () {
		var row = $(this).closest('[data-gr-row]');
		row.find('[data-gr-att]').val('');
		row.find('[data-gr-fname]').text('');
	});
}(jQuery));
