/* Student login: the card reader types the 10-digit code by itself (very fast, then Enter). A code typed by hand is
   refused (unless staff allow it), so nobody has to say or key in a number, and the digits are never shown. */
(function () {
	'use strict';
	var box = document.getElementById('stu-rfid');
	if (!box) { return; }
	var typing = box.getAttribute('data-card-typing') === '1';
	var note = document.getElementById('stu-rfid-h');
	var form = box.form;
	var last = 0, slow = false, started = 0;
	var MAXGAP = 80;      // ms between two digits from a reader (a person types slower)
	var MAXTOTAL = 700;   // ms for the whole code
	function say(t) { if (note) { note.textContent = t; } }
	function reset() { box.value = ''; last = 0; slow = false; started = 0; }
	box.addEventListener('paste', function (e) { if (!typing) { e.preventDefault(); say(box.getAttribute('data-card-msg')); } });
	box.addEventListener('keydown', function (e) {
		if (typing) { return; }
		var now = Date.now();
		if (e.key === 'Enter') {
			e.preventDefault();
			if (/^\d{10}$/.test(box.value) && !slow && (now - started) <= MAXTOTAL) {
				say(form.getAttribute('data-card-read') || '✓');
				if (form.checkValidity()) { form.requestSubmit ? form.requestSubmit() : form.submit(); } else { form.reportValidity(); }
			} else {
				reset();
				say(box.getAttribute('data-card-msg'));
			}
			return;
		}
		if (e.key.length === 1) {
			if (!/\d/.test(e.key)) { e.preventDefault(); return; }
			if (box.value === '') { started = now; slow = false; }
			else if (now - last > MAXGAP) { slow = true; }
			last = now;
		} else if (e.key === 'Backspace' || e.key === 'Delete') {
			reset();
		}
	});
	form.addEventListener('submit', function () { if (!typing && (slow || !/^\d{10}$/.test(box.value))) { reset(); } });
	box.addEventListener('focus', function () { reset(); say(''); });
}());
