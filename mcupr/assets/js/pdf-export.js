/* "Download PDF": the text of the page is drawn in the browser (html2canvas) and put into an A4 PDF (jsPDF).
   The libraries are loaded by main.js only when the button is pressed. The PDF holds the page as pictures of its
   pages (the browser draws Thai correctly; no font is needed in the file). Page breaks are put between paragraphs. */
(function () {
	'use strict';
	var MM_W = 210, MM_H = 297, MARGIN = 15;
	var CONTENT_W = MM_W - 2 * MARGIN, CONTENT_H = MM_H - 2 * MARGIN - 6; // 6 mm kept for the page number
	var CSS_W = 720;
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

	/* a clean copy of the article under a header (site, title, date and address), off screen, in light colours */
	function build(btn, target) {
		var clip = document.createElement('div');
		clip.className = 'pdf-clip';
		clip.setAttribute('aria-hidden', 'true');
		var sheet = document.createElement('div');
		sheet.className = 'pdf-sheet';
		var head = document.createElement('div');
		head.className = 'pdf-head';
		var site = document.createElement('p');
		site.className = 'pdf-site';
		site.textContent = btn.getAttribute('data-pdf-site') || location.hostname;
		var h1 = document.createElement('h1');
		h1.className = 'pdf-title';
		h1.textContent = btn.getAttribute('data-pdf-title') || document.title;
		var meta = document.createElement('p');
		meta.className = 'pdf-meta';
		var d = '';
		try { d = new Date().toLocaleDateString(document.documentElement.lang || 'th', { day: 'numeric', month: 'long', year: 'numeric' }); } catch (e) { d = new Date().toISOString().slice(0, 10); }
		meta.textContent = d + ' · ' + (btn.getAttribute('data-pdf-url') || location.href);
		head.appendChild(site); head.appendChild(h1); head.appendChild(meta);
		var body = target.cloneNode(true);
		body.removeAttribute('id');
		body.classList.add('pdf-body');
		[].forEach.call(body.querySelectorAll('.no-print, script, style, noscript, [hidden], button, [aria-hidden="true"].sr-only, .share-bar'), function (n) { n.remove(); });
		[].forEach.call(body.querySelectorAll('iframe, video, audio, embed, object'), function (n) {
			var p = document.createElement('p');
			p.className = 'pdf-note';
			var src = n.getAttribute('src') || (n.querySelector && n.querySelector('source') ? n.querySelector('source').getAttribute('src') : '') || '';
			p.textContent = src ? src : '';
			if (src) { n.replaceWith(p); } else { n.remove(); }
		});
		[].forEach.call(body.querySelectorAll('img'), function (img) { img.setAttribute('loading', 'eager'); img.removeAttribute('data-src'); });
		sheet.appendChild(head);
		sheet.appendChild(body);
		clip.appendChild(sheet);
		document.body.appendChild(clip);
		return { clip: clip, sheet: sheet };
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
		[].forEach.call(sheet.querySelectorAll('p, li, figure, img, blockquote, pre, tr, hr, .pdf-head'), function (el) {
			out.push(Math.floor(el.getBoundingClientRect().bottom - top));
		});
		return out.filter(function (b) { return b > 0; }).sort(function (a, b) { return a - b; });
	}
	function plan(total, cands) {
		var pages = [], start = 0, guard = 0;
		while (start < total - 1 && guard++ < MAX_PAGES + 5) {
			var limit = start + PAGE_H;
			if (limit >= total) { pages.push([start, total]); break; }
			var best = 0;
			cands.forEach(function (b) { if (b > start + PAGE_H * 0.55 && b <= limit && b > best) { best = b; } });
			var end = best || limit;
			pages.push([start, end]);
			start = end;
		}
		return pages;
	}

	function draw(el) {
		return window.html2canvas(el, { scale: SCALE, backgroundColor: '#ffffff', useCORS: true, logging: false, windowWidth: CSS_W + 40 });
	}

	window.mcuprPdf = function (btn) {
		var target = document.querySelector(btn.getAttribute('data-pdf-target') || '#article-body');
		if (!target || !window.html2canvas || !window.jspdf) { return Promise.reject(new Error('missing')); }
		var built = build(btn, target), clip = built.clip, sheet = built.sheet;
		var fonts = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
		return Promise.all([fonts, imagesReady(sheet)]).then(function () {
			var total = Math.ceil(sheet.getBoundingClientRect().height);
			if (total < 10 || total > PAGE_H * MAX_PAGES) { throw new Error('size'); }
			var pages = plan(total, candidates(sheet));
			var jsPDF = window.jspdf.jsPDF;
			var pdf = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
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
						pdf.addImage(c.toDataURL('image/jpeg', 0.85), 'JPEG', MARGIN, MARGIN, CONTENT_W, (e - s) / CSS_W * CONTENT_W);
						pdf.setFontSize(8);
						pdf.setTextColor(120);
						pdf.text((i + 1) + ' / ' + pages.length, MM_W / 2, MM_H - 9, { align: 'center' });
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
				a.download = fileName(btn.getAttribute('data-pdf-title') || document.title) + '.pdf';
				document.body.appendChild(a);
				a.click();
				a.remove();
				setTimeout(function () { URL.revokeObjectURL(url); }, 20000);
			});
		}).then(function () { clip.remove(); }, function (err) { clip.remove(); throw err; });
	};
}());
