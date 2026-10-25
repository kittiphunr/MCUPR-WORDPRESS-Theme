/* "Download PDF": the same A4 document that is printed (assets/js/print-doc.js: title, date, the text of the page) is drawn in the browser
   (html2canvas) and put into an A4 PDF (jsPDF), with the letterhead of Customizer → Print on A4 on every sheet: header, footer, background.
   The libraries are loaded by main.js only when the button is pressed. The PDF holds the pages as pictures (the browser draws Thai
   correctly; no font is needed in the file). Page breaks are put between paragraphs. */
(function () {
	'use strict';
	var D = window.mcuprDoc || {};
	var MM_W = 210, MM_H = 297, PX_MM = 96 / 25.4;
	var SIDE = D.side || 18, HEAD_H = D.head_h || 36, FOOT_H = D.foot_h || 20, PAD = 3; // millimetres: the margins of the sheet and the room kept round the text
	var CONTENT_W = MM_W - 2 * SIDE, CONTENT_H = MM_H - HEAD_H - FOOT_H - 2 * PAD;
	var CSS_W = Math.round(CONTENT_W * PX_MM);
	var PAGE_H = Math.floor(CSS_W * CONTENT_H / CONTENT_W);
	var SCALE = 1.6;
	var ONE_SHOT = 14000; // a canvas of more pixels than this in height may fail (phones): longer pages are drawn one sheet at a time
	var MAX_PAGES = 60;

	/* a file name every browser keeps: letters, digits and hyphens (some browsers drop a Thai name and save a file called "download"
	   with no extension); the page's own name is inside the PDF. The English words of the title when there are any, else the site's name. */
	function fileName(t) {
		var words = String(t || '').match(/[A-Za-z0-9]+/g) || [];
		var base = words.join('-').toLowerCase();
		if (base.length < 3) { base = location.hostname.replace(/^www\./, '').replace(/[^a-z0-9]+/gi, '-').toLowerCase(); }
		var d = new Date();
		var ymd = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
		return (base.slice(0, 60).replace(/^-+|-+$/g, '') || 'page') + '-' + ymd;
	}

	/* the document, off screen, in light colours, as wide as the text of the sheet */
	function build() {
		var doc = D.build ? D.build() : null;
		if (!doc) { return null; }
		var clip = document.createElement('div');
		clip.className = 'pdf-clip';
		clip.setAttribute('aria-hidden', 'true');
		clip.style.width = CSS_W + 'px';
		var sheet = document.createElement('div');
		sheet.className = 'pdf-sheet';
		sheet.style.width = CSS_W + 'px';
		sheet.appendChild(doc);
		clip.appendChild(sheet);
		document.body.appendChild(clip);
		return { clip: clip, sheet: sheet };
	}

	/* the header or the footer of the letterhead as a picture: the same markup the printed sheet uses (hidden on screen) */
	function letter(which, heightMm) {
		var src = document.querySelector('.print-sheet .print-' + which);
		if (!src) { return Promise.resolve(null); }
		var box = document.createElement('div');
		box.className = 'pdf-letter';
		box.setAttribute('aria-hidden', 'true');
		box.style.height = heightMm + 'mm';
		var node = src.cloneNode(true);
		box.appendChild(node);
		document.body.appendChild(box);
		return imagesReady(box).then(function () {
			return window.html2canvas(box, { scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false, windowWidth: Math.round(MM_W * PX_MM) + 40 });
		}).then(function (c) { box.remove(); return c; }, function () { box.remove(); return null; });
	}

	/* the whole-sheet background picture (optional), as a JPEG */
	function background() {
		var img = document.querySelector('.print-sheet .print-bg-img');
		if (!img || !img.complete || !img.naturalWidth) { return null; }
		try {
			var c = document.createElement('canvas');
			c.width = Math.min(img.naturalWidth, 1240);
			c.height = Math.round(c.width * MM_H / MM_W);
			var ctx = c.getContext('2d');
			ctx.fillStyle = '#fff';
			ctx.fillRect(0, 0, c.width, c.height);
			ctx.drawImage(img, 0, 0, c.width, c.height);
			return c.toDataURL('image/jpeg', 0.8);
		} catch (e) { return null; }
	}

	function imagesReady(sheet) {
		return Promise.all([].map.call(sheet.querySelectorAll('img'), function (img) {
			if (img.complete && img.naturalWidth) { return null; }
			return new Promise(function (ok) { img.onload = img.onerror = ok; setTimeout(ok, 8000); });
		}));
	}

	/* where a page may end: after a paragraph, a list item, a picture … (never right after a heading) */
	function candidates(sheet) {
		var top = sheet.getBoundingClientRect().top, out = [];
		[].forEach.call(sheet.querySelectorAll('p, li, figure, img, blockquote, pre, tr, hr, .pd-meta, .pd-title'), function (el) {
			out.push(Math.floor(el.getBoundingClientRect().bottom - top));
		});
		return out.filter(function (b) { return b > 0; }).sort(function (a, b) { return a - b; });
	}
	/* html2canvas draws the text a few pixels lower than the page measured it (Thai letters hang below the line): a sheet ends that much
	   below the end of the paragraph, so the last line is whole and the next paragraph has not begun. */
	var DROP = 7;
	function plan(total, cands) {
		var pages = [], start = 0, guard = 0;
		while (start < total - 1 && guard++ < MAX_PAGES + 5) {
			var limit = start + PAGE_H;
			if (limit >= total) { pages.push([start, total]); break; }
			var best = 0;
			cands.forEach(function (b) { if (b > start + PAGE_H * 0.55 && b + DROP <= limit && b > best) { best = b; } });
			var end = best ? best + DROP : limit;
			pages.push([start, end]);
			start = end;
		}
		return pages;
	}

	function draw(el) {
		return window.html2canvas(el, { scale: SCALE, backgroundColor: '#ffffff', useCORS: true, logging: false, windowWidth: CSS_W + 40 });
	}

	window.mcuprPdf = function (btn) {
		if (!window.html2canvas || !window.jspdf) { return Promise.reject(new Error('missing')); }
		var built = build();
		if (!built) { return Promise.reject(new Error('missing')); }
		var clip = built.clip, sheet = built.sheet;
		var fonts = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
		return Promise.all([fonts, imagesReady(sheet), letter('head', HEAD_H), letter('foot', FOOT_H)]).then(function (got) {
			var headPic = got[2], footPic = got[3], bg = background();
			var total = Math.ceil(sheet.getBoundingClientRect().height);
			if (total < 10 || total > PAGE_H * MAX_PAGES) { throw new Error('size'); }
			var pages = plan(total, candidates(sheet));
			var jsPDF = window.jspdf.jsPDF;
			var pdf = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
			var headUrl = headPic ? headPic.toDataURL('image/jpeg', 0.9) : '', footUrl = footPic ? footPic.toDataURL('image/jpeg', 0.9) : '';
			var whole = total * SCALE <= ONE_SHOT ? draw(clip) : null;
			var chain = Promise.resolve(whole);
			pages.forEach(function (pg, i) {
				chain = chain.then(function (big) {
					var s = pg[0], e = pg[1], canvas;
					if (big) {
						canvas = document.createElement('canvas');
						canvas.width = big.width;
						canvas.height = Math.max(1, Math.round((e - s) * SCALE));
						var ctx = canvas.getContext('2d');
						ctx.fillStyle = '#fff';
						ctx.fillRect(0, 0, canvas.width, canvas.height);
						ctx.drawImage(big, 0, Math.round(s * SCALE), big.width, canvas.height, 0, 0, big.width, canvas.height);
						return finish(canvas, big);
					}
					clip.style.height = (e - s) + 'px';
					sheet.style.marginTop = (-s) + 'px';
					return draw(clip).then(function (c) { return finish(c, null); });
					function finish(c, keep) {
						if (i) { pdf.addPage(); }
						if (bg) { pdf.addImage(bg, 'JPEG', 0, 0, MM_W, MM_H); }
						pdf.addImage(c.toDataURL('image/jpeg', 0.85), 'JPEG', SIDE, HEAD_H + PAD, CONTENT_W, (e - s) / CSS_W * CONTENT_W);
						if (headUrl) { pdf.addImage(headUrl, 'JPEG', 0, 0, MM_W, HEAD_H); }
						if (footUrl) { pdf.addImage(footUrl, 'JPEG', 0, MM_H - FOOT_H, MM_W, FOOT_H); }
						if (!D.foot_img) {
							pdf.setFontSize(8);
							pdf.setTextColor(120);
							pdf.text((i + 1) + ' / ' + pages.length, MM_W - SIDE, MM_H - 2.5, { align: 'right' });
						}
						var lab = btn.querySelector('span');
						if (lab && pages.length > 1) { lab.textContent = (btn.getAttribute('data-pdf-busy') || '') + ' ' + (i + 1) + '/' + pages.length; }
						return keep;
					}
				});
			});
			return chain.then(function () {
				// our own link so the file keeps the page's (Thai) name
				var url = URL.createObjectURL(pdf.output('blob'));
				var a = document.createElement('a');
				a.href = url;
				a.download = fileName(btn.getAttribute('data-pdf-title') || D.title || window.mcuprFullTitle || document.title) + '.pdf';
				document.body.appendChild(a);
				a.click();
				a.remove();
				setTimeout(function () { URL.revokeObjectURL(url); }, 20000);
			});
		}).then(function () { clip.remove(); }, function (err) { clip.remove(); throw err; });
	};
}());
