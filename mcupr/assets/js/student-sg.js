/* Student portal: print one section (the schedule or the grades) – the rest of the page is hidden while printing. */
(function () {
	'use strict';
	var done = function () { document.body.removeAttribute('data-sg-print'); };
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-sg-print]') : null;
		if (!b) { return; }
		document.body.setAttribute('data-sg-print', b.getAttribute('data-sg-print'));
		window.addEventListener('afterprint', done, { once: true });
		window.print();
	});
}());
