/* Activity photos box (admin): choose many pictures at once, drag to order, remove. */
(function ($) {
	'use strict';
	var list = $('[data-gal-list]');
	if (!list.length) { return; }
	var L = window.mcuprGal || {};
	var out = $('[data-gal-count]');

	function count() {
		var n = list.children().length;
		out.text(n ? String(L.count || '%s').replace('%s', n) : '');
	}
	list.sortable({ tolerance: 'pointer' });
	list.on('click', '[data-gal-del]', function () { $(this).closest('li').remove(); count(); });
	count();

	var frame;
	$('[data-gal-add]').on('click', function (e) {
		e.preventDefault();
		if (!window.wp || !wp.media) { return; }
		frame = wp.media({ title: L.title || 'Choose photos', multiple: 'add', library: { type: 'image' }, button: { text: L.button || 'Add' } });
		frame.on('select', function () {
			frame.state().get('selection').each(function (a) {
				a = a.toJSON();
				if (list.find('[data-id="' + a.id + '"]').length) { return; }
				var u = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
				var li = $('<li>').attr('data-id', a.id);
				li.append($('<img alt="">').attr('src', u));
				li.append($('<input type="hidden" name="mcupr_gallery[]">').val(a.id));
				li.append($('<button type="button" class="button-link-delete" data-gal-del>').attr('aria-label', L.remove || 'Remove').html('&times;'));
				list.append(li);
			});
			count();
		});
		frame.open();
	});
}(jQuery));
