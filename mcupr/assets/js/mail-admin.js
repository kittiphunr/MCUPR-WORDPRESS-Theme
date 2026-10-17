/* E-mail to students: fill the form from a template, check who gets it (count + first e-mail), and show the sending progress. */
(function () {
	'use strict';
	var C = window.mcuprMail;
	var form = document.getElementById('mcupr-mail-form');
	if (!C || !form) { return; }
	var $ = function (id) { return document.getElementById(id); };
	var fmt = function (s) { var a = arguments; return s.replace(/%(\d)\$d/g, function (m, i) { return a[+i]; }); };

	var tpl = $('mm-tpl');
	if (tpl) {
		tpl.addEventListener('change', function () {
			var t = C.tpls[tpl.value];
			if (!t) { return; }
			$('mm-subject').value = t.subject || '';
			$('mm-body').value = t.body || '';
			$('mm-reply').value = t.reply || '';
		});
	}

	function post(data, cb, fail) {
		var x = new XMLHttpRequest();
		x.open('POST', C.ajax);
		x.onload = function () {
			var r = null;
			try { r = JSON.parse(x.responseText); } catch (e) { r = null; }
			if (r && r.success) { cb(r.data); } else { fail(); }
		};
		x.onerror = fail;
		var p = [];
		Object.keys(data).forEach(function (k) {
			[].concat(data[k]).forEach(function (v) { p.push(encodeURIComponent(k + (Array.isArray(data[k]) ? '[]' : '')) + '=' + encodeURIComponent(v)); });
		});
		x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		x.send(p.join('&'));
	}

	var out = $('mm-preview-out'), sample = $('mm-sample');
	$('mm-preview').addEventListener('click', function () {
		var d = { action: 'mcupr_mail_preview', n: C.nonce, body: $('mm-body').value };
		[].forEach.call(form.querySelectorAll('input[name^="mcupr_aud_"]:checked'), function (i) {
			var k = i.name.replace('[]', '');
			(d[k] = d[k] || []).push(i.value);
		});
		out.textContent = '…';
		post(d, function (r) {
			if (!r.n) { out.textContent = C.s.none; sample.hidden = true; return; }
			out.textContent = fmt(C.s.count, r.n, r.matched, r.no_email, r.stopped, r.dup);
			sample.textContent = C.s.sample + '\n\n' + r.sample;
			sample.hidden = false;
		}, function () { out.textContent = C.s.fail; });
	});

	var rows = [].slice.call(document.querySelectorAll('tr[data-job][data-live="1"]'));
	function step(tr) {
		post({ action: 'mcupr_mail_step', n: C.nonce, job: tr.getAttribute('data-job') }, function (r) {
			tr.querySelector('.mm-st').textContent = r.label;
			tr.querySelector('.mm-sent').textContent = r.sent + ' / ' + r.total;
			tr.querySelector('.mm-failed').textContent = r.failed;
			tr.querySelector('.mm-skipped').textContent = r.skipped;
			if (r.live) { setTimeout(function () { step(tr); }, 15000); } else { tr.setAttribute('data-live', '0'); }
		}, function () { setTimeout(function () { step(tr); }, 30000); });
	}
	rows.forEach(function (tr) { setTimeout(function () { step(tr); }, 3000); });
}());
