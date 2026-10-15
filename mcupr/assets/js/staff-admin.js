/* Staff hub (admin): repeating rows, medal image picker, colour pickers. */
(function ($) {
	'use strict';

	/* ---- repeating rows (education, works, decorations) ---- */
	$(document).on('click', '[data-rep-add]', function () {
		var box = $(this).closest('[data-rep]');
		var rows = box.find('[data-rep-rows]');
		var n = rows.children().length;
		var html = box.find('[data-rep-tpl]').html().replace(/__i__/g, 'n' + Date.now() + '_' + n);
		rows.append(html);
		rows.children().last().find('input,select').first().trigger('focus');
	});
	$(document).on('click', '[data-rep-del]', function () {
		$(this).closest('.mcs-row').remove();
	});

	/* ---- catalogue of decorations ---- */
	function initColors(ctx) {
		$('.mcs-color', ctx).not('.wp-color-picker').wpColorPicker({ change: function (e, ui) { $(e.target).val(ui.color.toString()); } });
	}
	initColors(document);
	$(document).on('click', '[data-honor-add]', function () {
		var tbody = $('[data-honor-rows]');
		var html = $('[data-honor-tpl]').html().replace(/__i__/g, 'n' + Date.now());
		tbody.append(html);
		initColors(tbody.children().last());
	});
	$(document).on('click', '[data-honor-del]', function () {
		$(this).closest('[data-honor-row]').remove();
	});
	var frame;
	$(document).on('click', '[data-pick-img]', function (e) {
		e.preventDefault();
		var row = $(this).closest('[data-honor-row]');
		frame = wp.media({ title: 'เลือกภาพเครื่องราชอิสริยาภรณ์', multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var a = frame.state().get('selection').first().toJSON();
			row.find('[data-img-id]').val(a.id);
			var u = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
			row.find('[data-medal-preview]').html('<img class="mcs-medal" alt="" src="' + u + '">');
		});
		frame.open();
	});
	$(document).on('click', '[data-clear-img]', function () {
		var row = $(this).closest('[data-honor-row]');
		row.find('[data-img-id]').val(0);
		row.find('[data-medal-preview]').html('<span class="description">—</span>');
	});
}(jQuery));
