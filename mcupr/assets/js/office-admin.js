/* Office editor screen: choose / remove the logo from the Media Library. */
(function ($) {
	'use strict';
	$(function () {
		var box = $('.mcupr-of-logo');
		if (!box.length || !window.wp || !wp.media) { return; }
		var frame;
		var input = box.find('input[type=hidden]');
		var img = box.find('.mcupr-of-logo-img');
		box.on('click', '.mcupr-of-pick', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({ title: box.find('strong').text(), library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var a = frame.state().get('selection').first().toJSON();
					var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					input.val(a.id);
					img.empty().append($('<img>', { src: url, alt: '', style: 'height:64px;width:auto;display:block;margin-bottom:6px' }));
				});
			}
			frame.open();
		});
		box.on('click', '.mcupr-of-clear', function (e) {
			e.preventDefault();
			input.val('');
			img.empty();
		});
	});
})(jQuery);
