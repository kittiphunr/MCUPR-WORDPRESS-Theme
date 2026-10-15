/* Form builder (admin): add, edit, move and remove the fields of a form; the list is sent as JSON when the form is saved. */
(function () {
	'use strict';
	var box = document.getElementById('mcupr-fb-builder');
	var out = document.getElementById('mcupr-fb-json');
	if (!box || !out || !window.mcuprFb) { return; }
	var T = window.mcuprFb.types, L = window.mcuprFb.i18n;
	var fields;
	try { fields = JSON.parse(out.value || '[]') || []; } catch (e) { fields = []; }
	var CHOICE = { select: 1, radio: 1, checkbox: 1 };
	var NOREQ = { heading: 1, note: 1 };
	var TEXTY = { text: 1, textarea: 1, email: 1, tel: 1, number: 1 };

	function el(tag, attrs, kids) {
		var e = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) { if (k === 'text') { e.textContent = attrs[k]; } else { e.setAttribute(k, attrs[k]); } });
		(kids || []).forEach(function (c) { e.appendChild(c); });
		return e;
	}
	function nextId() {
		var n = fields.length + 1, ids = fields.map(function (f) { return f.id; });
		while (ids.indexOf('f' + n) >= 0) { n++; }
		return 'f' + n;
	}
	function sync() { out.value = JSON.stringify(fields.map(function (f) { var c = Object.assign({}, f); if (typeof c.options === 'string') { c.options = c.options.split(/\n/); } return c; })); }

	function field(f, i) {
		var id = 'fbf-' + f.id;
		var card = el('div', { 'class': 'mcupr-fb-card', style: 'border:1px solid #c3c4c7;border-radius:8px;background:#fff;padding:12px;margin:0 0 10px' });
		var head = el('div', { style: 'display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px' });
		head.appendChild(el('strong', { text: (i + 1) + '. ' + T[f.type] }));
		var tools = el('span', { style: 'margin-left:auto;display:inline-flex;gap:4px' });
		[['up', '↑', function () { if (i > 0) { fields.splice(i - 1, 0, fields.splice(i, 1)[0]); draw(); } }],
		 ['down', '↓', function () { if (i < fields.length - 1) { fields.splice(i + 1, 0, fields.splice(i, 1)[0]); draw(); } }],
		 ['del', '✕', function () { if (window.confirm(L.ok)) { fields.splice(i, 1); draw(); } }]].forEach(function (b) {
			var btn = el('button', { type: 'button', 'class': 'button', 'aria-label': L[b[0]] + ' (' + (i + 1) + ')', title: L[b[0]], text: b[1] });
			btn.addEventListener('click', b[2]);
			tools.appendChild(btn);
		});
		head.appendChild(tools);
		card.appendChild(head);
		function row(labelText, input, inputId) {
			var r = el('p', { style: 'margin:6px 0' });
			r.appendChild(el('label', { 'for': inputId, text: labelText, style: 'display:block;font-weight:600;margin-bottom:2px' }));
			r.appendChild(input);
			card.appendChild(r);
		}
		var lab = el('input', { type: 'text', id: id + '-l', 'class': 'large-text', value: f.label || '' });
		lab.addEventListener('input', function () { f.label = lab.value; sync(); });
		row(f.type === 'heading' ? L.text : L.label, lab, id + '-l');
		if (f.type !== 'heading') {
			var help = el('input', { type: 'text', id: id + '-h', 'class': 'large-text', value: f.help || '' });
			help.addEventListener('input', function () { f.help = help.value; sync(); });
			row(f.type === 'note' ? L.text : L.help, help, id + '-h');
		}
		if (TEXTY[f.type]) {
			var ph = el('input', { type: 'text', id: id + '-p', 'class': 'regular-text', value: f.placeholder || '' });
			ph.addEventListener('input', function () { f.placeholder = ph.value; sync(); });
			row(L.ph, ph, id + '-p');
		}
		if (CHOICE[f.type]) {
			var o = el('textarea', { id: id + '-o', rows: '4', 'class': 'large-text' });
			o.value = Array.isArray(f.options) ? f.options.join('\n') : (f.options || '');
			o.addEventListener('input', function () { f.options = o.value; sync(); });
			row(L.opts, o, id + '-o');
		}
		if (!NOREQ[f.type]) {
			var rq = el('input', { type: 'checkbox', id: id + '-r' });
			rq.checked = !!f.required;
			rq.addEventListener('change', function () { f.required = rq.checked ? 1 : 0; sync(); });
			var rl = el('label', { 'for': id + '-r', style: 'display:inline-flex;gap:6px;align-items:center' });
			rl.appendChild(rq); rl.appendChild(document.createTextNode(L.req));
			card.appendChild(el('p', { style: 'margin:6px 0' }, [rl]));
		}
		return card;
	}

	function draw() {
		box.textContent = '';
		if (!fields.length) { box.appendChild(el('p', { text: L.empty, 'class': 'description' })); }
		fields.forEach(function (f, i) { box.appendChild(field(f, i)); });
		var add = el('p', { style: 'margin-top:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:center' });
		add.appendChild(el('strong', { text: L.add }));
		Object.keys(T).forEach(function (t) {
			var b = el('button', { type: 'button', 'class': 'button', text: '+ ' + T[t] });
			b.addEventListener('click', function () {
				var n = { id: nextId(), type: t, label: t === 'consent' ? '' : '', help: '', placeholder: '', required: t === 'consent' ? 1 : 0, options: CHOICE[t] ? ['', ''] : [] };
				if (CHOICE[t]) { n.options = []; }
				fields.push(n); draw();
				var last = box.querySelector('.mcupr-fb-card:last-of-type input[type=text]'); if (last) { last.focus(); }
			});
			add.appendChild(b);
		});
		box.appendChild(add);
		sync();
	}
	draw();
	var form = out.form;
	if (form) { form.addEventListener('submit', sync); }
}());
