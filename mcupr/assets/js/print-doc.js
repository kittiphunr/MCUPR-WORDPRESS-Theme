/* The A4 document of a page: the title, a line of date and category, the text of the page, the activity photos under it, the address of
   each video (and a picture of it) and of each PDF shown on the page; nothing of the website around it
   (no menu, breadcrumbs, counters, share buttons, "previous / next"). Print and "Download PDF" are both made from this one document,
   so they look the same: the letterhead of Customizer → Print on A4 is around it (assets in inc/print.php). */
(function () {
	'use strict';
	var D = window.mcuprDoc;
	if (!D) { return; }
	var JUNK = 'script, style, noscript, template, [hidden], button, input, select, textarea, form, nav, aside, .no-print, .print\\:hidden, .share-bar, .tool-bar, .tool-card, .post-nav, .post-navigation, .related, .comments, .sr-only, [aria-hidden="true"]:not(svg):not(img)';

	/* a video of the page is a cover with a play button (the player is only built when it is pressed): on paper it is the cover and the address to open it */
	function videos(body) {
		[].forEach.call(body.querySelectorAll('.vid[data-vid]'), function (v) {
			var a = v.querySelector('a.vid-play'), href = a ? a.getAttribute('href') : '';
			if (!href) { v.remove(); return; }
			var box = document.createElement('div'), img = v.querySelector('img'), p = document.createElement('p');
			box.className = 'pd-vid';
			if (img && !D.noimg) { var c = document.createElement('img'); c.src = img.currentSrc || img.src; c.alt = ''; box.appendChild(c); }
			p.className = 'pd-note';
			p.textContent = (v.getAttribute('data-title') || '') + ' – ' + href;
			box.appendChild(p);
			( v.parentNode && v.parentNode.classList.contains('vid-wrap') ? v.parentNode : v ).replaceWith(box); // (the box around a player is hidden on paper)
		});
	}

	function clean(body) {
		videos(body);
		[].forEach.call(body.querySelectorAll(JUNK), function (n) { n.remove(); });
		[].forEach.call(body.querySelectorAll('iframe, video, audio, embed, object'), function (n) {
			var src = n.getAttribute('src') || (n.querySelector && n.querySelector('source') ? n.querySelector('source').getAttribute('src') : '') || '';
			if (src) {
				var p = document.createElement('p');
				p.className = 'pd-note';
				p.textContent = src;
				n.replaceWith(p);
			} else { n.remove(); }
		});
		if (D.noimg) { [].forEach.call(body.querySelectorAll('img, picture, figure'), function (n) { n.remove(); }); }
		[].forEach.call(body.querySelectorAll('img'), function (img) { img.setAttribute('loading', 'eager'); img.removeAttribute('data-src'); img.removeAttribute('srcset'); img.removeAttribute('sizes'); });
		[].forEach.call(body.querySelectorAll('[id]'), function (n) { n.removeAttribute('id'); });
	}

	/** The document, or null when this page has no text to print. */
	function build() {
		var target = document.querySelector(D.target || '#article-body');
		if (!target) { return null; }
		var doc = document.createElement('div');
		doc.className = 'print-doc';
		var lang = target.getAttribute('lang') || document.documentElement.getAttribute('lang');
		if (lang) { doc.setAttribute('lang', lang); }
		if (D.meta) {
			var m = document.createElement('p');
			m.className = 'pd-meta';
			m.textContent = D.meta;
			doc.appendChild(m);
		}
		var h = document.createElement('h1');
		h.className = 'pd-title';
		h.textContent = D.title || window.mcuprFullTitle || document.title; // the whole title, never one half-typed on the tab
		doc.appendChild(h);
		var body = target.cloneNode(true);
		body.removeAttribute('id');
		body.className = 'pd-body reader';
		clean(body);
		doc.appendChild(body);
		extras(doc, target);
		return doc;
	}

	/* what the page shows under its text: the activity photos (a grid of small pictures, no lightbox) and, for each PDF shown on the
	   page, its name and address. They are not inside the text box, so they are taken from the page itself. */
	function after(target, n) { return !!(target.compareDocumentPosition(n) & Node.DOCUMENT_POSITION_FOLLOWING) && !target.contains(n); }
	function extras(doc, target) {
		var gal = [].filter.call(document.querySelectorAll('.post-gal'), function (n) { return after(target, n); })[0];
		if (gal && !D.noimg) {
			var pics = [].filter.call(gal.querySelectorAll('img'), function (i) { return i.getAttribute('src') || i.currentSrc; });
			if (pics.length) {
				var sec = document.createElement('section'), h = document.createElement('h2'), grid = document.createElement('div'), t = gal.querySelector('.post-gal-t');
				sec.className = 'pd-gal';
				h.textContent = t ? t.textContent.replace(/\s+/g, ' ').trim() : '';
				if (h.textContent) { sec.appendChild(h); }
				grid.className = 'pd-gal-g';
				pics.forEach(function (i) {
					var c = document.createElement('img');
					c.src = i.currentSrc || i.getAttribute('src'); c.alt = i.getAttribute('alt') || ''; c.setAttribute('loading', 'eager');
					grid.appendChild(c);
				});
				sec.appendChild(grid);
				doc.appendChild(sec);
			}
		}
		[].forEach.call(document.querySelectorAll('.pdfv[data-src]'), function (v) {
			if (!after(target, v)) { return; }
			var t = v.querySelector('.pdfv-t'), p = document.createElement('p');
			p.className = 'pd-note';
			p.textContent = (t ? t.textContent.replace(/\s+/g, ' ').trim() : 'PDF') + ' – ' + v.getAttribute('data-src');
			doc.appendChild(p);
		});
	}

	function start() {
		if (document.querySelector('body > .print-doc')) { return; }
		var d = build();
		if (!d) { return; }
		document.body.appendChild(d);
		document.documentElement.classList.add('printing-doc');
	}
	function stop() {
		[].forEach.call(document.querySelectorAll('body > .print-doc'), function (n) { n.remove(); });
		document.documentElement.classList.remove('printing-doc');
	}
	window.addEventListener('beforeprint', start);
	window.addEventListener('afterprint', stop);
	if (window.matchMedia) {
		var mq = window.matchMedia('print');
		var on = function (e) { if (e.matches) { start(); } else { stop(); } };
		if (mq.addEventListener) { mq.addEventListener('change', on); } else if (mq.addListener) { mq.addListener(on); }
	}
	D.build = build;
	D.print = function () { start(); window.print(); };
}());
