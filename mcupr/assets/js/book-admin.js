/* The e-book box in the editor: choose the PDF or the pictures (drag to order), and make the cover from the first page. */
(function ($) {
	'use strict';
	var A = window.mcuprBookAdmin || {};
	var I = A.i || {};

	// the radio choice shows its own part
	function panes() {
		var v = $('input[name="mcupr_bk_src"]:checked').val();
		$('.mcupr-bk-pane').each(function () { $(this).prop('hidden', $(this).attr('data-src') !== v); });
	}
	$(document).on('change', 'input[name="mcupr_bk_src"]', panes);
	panes();

	// the PDF
	var pdfFrame = null;
	$('#mcupr-bk-pick-pdf').on('click', function () {
		if (!window.wp || !wp.media) { return; }
		if (!pdfFrame) {
			pdfFrame = wp.media({ title: I.pick, multiple: false, library: { type: 'application/pdf' } });
			pdfFrame.on('select', function () {
				var a = pdfFrame.state().get('selection').first().toJSON();
				$('#mcupr-bk-pdf').val(a.id);
				$('#mcupr-bk-pdf-name').text((a.title || '') + ' – ' + (a.filename || ''));
				$('#mcupr-bk-cover').prop('disabled', false);
			});
		}
		pdfFrame.open();
	});

	// the pictures
	var imgFrame = null, $list = $('#mcupr-bk-imgs');
	if ($list.length && $.fn.sortable) { $list.sortable({ tolerance: 'pointer' }); }
	$('#mcupr-bk-pick-imgs').on('click', function () {
		if (!window.wp || !wp.media) { return; }
		if (!imgFrame) {
			imgFrame = wp.media({ title: I.pickImgs, multiple: 'add', library: { type: 'image' } });
			imgFrame.on('select', function () {
				imgFrame.state().get('selection').each(function (m) {
					var a = m.toJSON();
					if ($list.find('li[data-id="' + a.id + '"]').length) { return; }
					var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
					var $li = $('<li style="position:relative;width:96px;border:1px solid #c3c4c7;background:#fff;padding:4px;cursor:move"></li>').attr('data-id', a.id);
					$li.append($('<input type="hidden" name="mcupr_bk_imgs[]">').val(a.id), $('<img alt="" style="display:block;width:100%;height:auto">').attr('src', url), $('<button type="button" class="button-link mcupr-bk-rm" style="display:block;margin-top:2px"></button>').text(I.remove));
					$list.append($li);
				});
			});
		}
		imgFrame.open();
	});
	$list.on('click', '.mcupr-bk-rm', function () { $(this).closest('li').remove(); });

	// the cover from the first page of the PDF (drawn here, sent as a JPEG)
	$('#mcupr-bk-cover').on('click', function () {
		var id = $('#mcupr-bk-pdf').val(), $msg = $('#mcupr-bk-msg'), post = $('#post_ID').val();
		if (!id || !post) { return; }
		$msg.text(I.making);
		$.ajax({ url: A.ajax, data: { action: 'mcupr_book_pdf_url', post: post, att: id, nonce: A.nonce }, method: 'POST' }).then(function (r) {
			if (!r || !r.success) { throw new Error('url'); }
			return import(A.pdf).then(function (lib) {
				lib.GlobalWorkerOptions.workerSrc = A.worker;
				return lib.getDocument({ url: r.data.url, cMapUrl: A.cmaps, cMapPacked: true, standardFontDataUrl: A.fonts }).promise;
			});
		}).then(function (doc) {
			return doc.getPage(1);
		}).then(function (pg) {
			var v1 = pg.getViewport({ scale: 1 }), vp = pg.getViewport({ scale: 800 / v1.width }), cv = document.createElement('canvas');
			cv.width = Math.floor(vp.width); cv.height = Math.floor(vp.height);
			var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
			return pg.render({ canvasContext: cx, viewport: vp }).promise.then(function () {
				return new Promise(function (res) { cv.toBlob(res, 'image/jpeg', 0.88); });
			});
		}).then(function (blob) {
			var fd = new FormData();
			fd.append('action', 'mcupr_book_cover'); fd.append('post', post); fd.append('nonce', A.nonce); fd.append('cover', blob, 'cover.jpg');
			return fetch(A.ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
		}).then(function (r) {
			if (!r || !r.success) { throw new Error('upload'); }
			$msg.text(I.done);
			var t = document.getElementById('set-post-thumbnail');
			if (t && r.data && r.data.url) { $(t).html('<img src="' + r.data.url + '" alt="" style="max-width:100%;height:auto">'); }
		}).catch(function () { $msg.text(I.fail); });
	});
})(jQuery);
