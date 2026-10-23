/* The A4 document of a page: the title, a line of date and category, and the text of the page, nothing of the website around it
   (no menu, breadcrumbs, counters, share buttons, "previous / next"). Print and "Download PDF" are both made from this one document,
   so they look the same: the letterhead of Customizer → Print on A4 is around it (assets in inc/print.php). */
(function () {
	'use strict';
	var D = window.mcuprDoc;
	if (!D) { return; }
	var JUNK = 'script, style, noscript, template, [hidden], button, input, select, textarea, form, nav, aside, .no-print, .print\\:hidden, .share-bar, .tool-bar, .tool-card, .post-nav, .post-navigation, .related, .comments, .sr-only, [aria-hidden="true"]:not(svg):not(img)';

	function clean(body) {
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
		h.textContent = D.title || document.title;
		doc.appendChild(h);
		var body = target.cloneNode(true);
		body.removeAttribute('id');
		body.className = 'pd-body reader';
		clean(body);
		doc.appendChild(body);
		return doc;
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
