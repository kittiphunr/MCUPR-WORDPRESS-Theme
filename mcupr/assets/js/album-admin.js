/* Album editor (admin): choose pictures from the media library, drag to order, remove. */
(function ($) {
	'use strict';
	var list = $('[data-album-list]');
	if (!list.length) { return; }
	list.sortable({ tolerance: 'pointer' });
	list.on('click', '[data-album-del]', function () { $(this).closest('li').remove(); });
	var frame;
	$('[data-album-add]').on('click', function (e) {
		e.preventDefault();
		frame = wp.media({ title: 'เลือกภาพเข้าอัลบั้ม', multiple: 'add', library: { type: 'image' }, button: { text: 'เพิ่มเข้าอัลบั้ม' } });
		frame.on('select', function () {
			frame.state().get('selection').each(function (a) {
				a = a.toJSON();
				if (list.find('[data-id="' + a.id + '"]').length) { return; }
				var u = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
				list.append('<li data-id="' + a.id + '"><img alt="" src="' + u + '"><input type="hidden" name="mcupr_album[]" value="' + a.id + '"><button type="button" class="button-link-delete" data-album-del aria-label="ลบภาพนี้">&times;</button></li>');
			});
		});
		frame.open();
	});
}(jQuery));
