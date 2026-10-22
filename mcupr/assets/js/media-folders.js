/* Media folders: the folder panel on the media page, the folder filter in the media grid and pop-up, drag to move, upload into a folder. */
(function ($) {
	'use strict';
	var D = window.mcuprMF;
	if (!D) { return; }
	var T = D.t;
	var cur = D.current === '' ? '' : String(D.current); // '' all, 'none' unfiled, or a folder id
	var panel = null;

	function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
	function fmt(s, n) { return String(s).replace('%d', n); }

	/* ---------------------------------------------------------------- data */
	function byParent() {
		var m = {};
		D.folders.forEach(function (f) { (m[f.parent] = m[f.parent] || []).push(f); });
		return m;
	}
	function flat() {
		var m = byParent(), out = [];
		(function walk(p, d) {
			if (d > 8) { return; }
			(m[p] || []).forEach(function (f) { out.push({ id: f.id, name: f.name, depth: d, parent: f.parent }); walk(f.id, d + 1); });
		})(0, 0);
		return out;
	}
	function find(id) { return D.folders.filter(function (f) { return String(f.id) === String(id); })[0]; }
	function count(id) { return (D.counts.folders && D.counts.folders[id]) || 0; }
	function pathOf(id) {
		var out = [], f = find(id), g = 0;
		while (f && g++ < 10) { out.unshift(f); f = f.parent ? find(f.parent) : null; }
		return out;
	}
	function api(action, data) {
		return $.post(D.ajax, $.extend({ action: 'mcupr_mf_' + action, _wpnonce: D.nonce }, data || {})).then(function (r) {
			if (!r || !r.success) { return $.Deferred().reject((r && r.data && r.data.message) || 'error'); }
			D.folders = r.data.folders || D.folders;
			D.counts = r.data.counts || D.counts;
			$(document).trigger('mcupr-mf-changed');
			return r.data;
		});
	}
	function toast(msg) {
		var n = $('<div class="mf-toast" role="status"></div>').text(msg).appendTo('body');
		setTimeout(function () { n.addClass('is-out'); setTimeout(function () { n.remove(); }, 400); }, 2200);
	}

	/* ---------------------------------------------------------------- the library being shown */
	function library() {
		var f = wp.media && wp.media.frame, st = f && f.state && f.state();
		return st && st.get && st.get('library');
	}
	function setLibraryFolder(id) {
		var lib = library();
		if (lib && lib.props) {
			if (id === '' || id == null) { lib.props.unset('mcupr_folder'); } else { lib.props.set({ mcupr_folder: id }); }
		}
	}
	function refreshLibrary() {
		var lib = library();
		if (lib && lib.props) { lib.props.set({ mcupr_r: Date.now() }); }
	}
	function uploadParam(id) {
		var v = id && id !== 'none' ? id : '';
		if (window.wp && wp.Uploader && wp.Uploader.defaults) {
			wp.Uploader.defaults.multipart_params = wp.Uploader.defaults.multipart_params || {};
			wp.Uploader.defaults.multipart_params.mcupr_folder = v;
		}
		if (window._wpPluploadSettings && _wpPluploadSettings.defaults) {
			_wpPluploadSettings.defaults.multipart_params = _wpPluploadSettings.defaults.multipart_params || {};
			_wpPluploadSettings.defaults.multipart_params.mcupr_folder = v;
		}
		var f = wp.media && wp.media.frame;
		try {
			var up = f && f.uploader && f.uploader.uploader && f.uploader.uploader.uploader;
			if (up && up.settings) { up.settings.multipart_params = up.settings.multipart_params || {}; up.settings.multipart_params.mcupr_folder = v; }
		} catch (e) { /* the uploader is not built yet */ }
	}

	/* ---------------------------------------------------------------- choose a folder (panel and filter) */
	function choose(id, fromPanel) {
		id = id == null ? '' : String(id);
		if (D.list) {
			var u = new URL(window.location.href);
			u.searchParams.delete('paged');
			if (id === '') { u.searchParams.delete('mcupr_folder'); } else { u.searchParams.set('mcupr_folder', id); }
			window.location.href = u.toString();
			return;
		}
		cur = id;
		setLibraryFolder(id);
		uploadParam(id);
		if (D.page && window.history && history.replaceState) {
			var h = new URL(window.location.href);
			if (id === '') { h.searchParams.delete('mcupr_folder'); } else { h.searchParams.set('mcupr_folder', id); }
			history.replaceState(null, '', h.toString());
		}
		$(document).trigger('mcupr-mf-chosen');
	}

	/* ---------------------------------------------------------------- the panel */
	function node(f, m, depth) {
		var kids = m[f.id] || [];
		var open = isOpen(f.id);
		var h = '<li data-mf="' + f.id + '" role="treeitem" aria-expanded="' + (kids.length ? (open ? 'true' : 'false') : 'false') + '"' + (kids.length ? '' : ' aria-level="' + (depth + 1) + '"') + '>';
		h += '<div class="mf-row' + (String(cur) === String(f.id) ? ' is-cur' : '') + '" data-drop="' + f.id + '">';
		h += kids.length ? '<button type="button" class="mf-tog" data-tog="' + f.id + '" aria-label="' + esc(f.name) + '"></button>' : '<span class="mf-tog mf-tog-none" aria-hidden="true"></span>';
		h += '<a href="#" class="mf-link" data-choose="' + f.id + '"><svg class="mf-ic" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><span class="mf-name">' + esc(f.name) + '</span><span class="mf-n">' + count(f.id) + '</span></a></div>';
		if (kids.length) {
			h += '<ul role="group"' + (open ? '' : ' hidden') + '>';
			kids.forEach(function (k) { h += node(k, m, depth + 1); });
			h += '</ul>';
		}
		return h + '</li>';
	}
	var openSet = {};
	function isOpen(id) {
		if (openSet[id] != null) { return openSet[id]; }
		var p = pathOf(cur), i;
		for (i = 0; i < p.length; i++) { if (String(p[i].id) === String(id)) { return true; } }
		return false;
	}
	function renderPanel() {
		if (!panel) { return; }
		var m = byParent();
		var h = '<h2 class="mf-h">' + esc(T.folders) + '</h2><ul role="tree" class="mf-tree">';
		h += '<li role="treeitem"><div class="mf-row' + (cur === '' ? ' is-cur' : '') + '"><span class="mf-tog mf-tog-none"></span><a href="#" class="mf-link" data-choose=""><svg class="mf-ic" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" opacity=".55" d="M3 5h18v14H3z"/></svg><span class="mf-name">' + esc(T.all) + '</span><span class="mf-n">' + D.counts.all + '</span></a></div></li>';
		h += '<li role="treeitem"><div class="mf-row' + (cur === 'none' ? ' is-cur' : '') + '" data-drop="0"><span class="mf-tog mf-tog-none"></span><a href="#" class="mf-link" data-choose="none"><svg class="mf-ic" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="3 2" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><span class="mf-name">' + esc(T.none) + '</span><span class="mf-n">' + D.counts.none + '</span></a></div></li>';
		(m[0] || []).forEach(function (f) { h += node(f, m, 0); });
		h += '</ul>';
		{
			h += '<div class="mf-tools"><button type="button" class="button" data-act="new">+ ' + esc(T.newFolder) + '</button>';
			if (D.canEdit && cur && cur !== 'none') { h += ' <button type="button" class="button" data-act="rename">' + esc(T.rename) + '</button> <button type="button" class="button" data-act="delete">' + esc(T.delete) + '</button>'; }
			h += '</div>';
		}
		h += '<p class="mf-hint">' + esc(T.dragHint) + '</p>';
		panel.html(h);
		renderStrip();
	}

	/* the path and the sub-folders above the files */
	var strip = null;
	function renderStrip() {
		if (!strip) { return; }
		var h = '', p = cur && cur !== 'none' ? pathOf(cur) : [];
		var label = cur === '' ? T.all : (cur === 'none' ? T.none : '');
		h += '<nav class="mf-path" aria-label="' + esc(T.folders) + '"><a href="#" data-choose="">' + esc(T.all) + '</a>';
		if (cur === 'none') { h += ' <span aria-hidden="true">›</span> <b>' + esc(T.none) + '</b>'; }
		p.forEach(function (f, i) {
			h += ' <span aria-hidden="true">›</span> ' + (i === p.length - 1 ? '<b>' + esc(f.name) + '</b>' : '<a href="#" data-choose="' + f.id + '">' + esc(f.name) + '</a>');
		});
		h += '</nav>';
		var kids = (byParent()[cur && cur !== 'none' ? cur : 0] || []);
		if (kids.length && cur !== 'none') {
			h += '<ul class="mf-sub" aria-label="' + esc(T.sub) + '">';
			kids.forEach(function (k) { h += '<li data-drop="' + k.id + '"><a href="#" data-choose="' + k.id + '"><svg width="30" height="26" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><b>' + esc(k.name) + '</b><small>' + esc(fmt(T.files, count(k.id))) + '</small></a></li>'; });
			h += '</ul>';
		}
		strip.html(h);
	}

	/* the file-type chips (they press the type filter WordPress already has) */
	function chips() {
		var h = '<div class="mf-chips" role="group" aria-label="' + esc(T.types['']) + '">';
		var cur_t = new URL(window.location.href).searchParams.get('post_mime_type') || '';
		Object.keys(T.types).forEach(function (k) {
			var on = (D.list ? cur_t === k : (library() && (library().props.get('type') || '') === k));
			h += '<button type="button" class="mf-chip' + (on ? ' is-on' : '') + '" data-type="' + k + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + esc(T.types[k]) + '</button>';
		});
		return h + '</div>';
	}
	function pickType(k) {
		if (D.list) {
			var u = new URL(window.location.href);
			u.searchParams.delete('paged');
			if (k === '') { u.searchParams.delete('post_mime_type'); } else { u.searchParams.set('post_mime_type', k); }
			window.location.href = u.toString();
			return;
		}
		var lib = library();
		if (lib && lib.props) { if (k === '') { lib.props.unset('type'); } else { lib.props.set({ type: k }); } }
		$('.mf-chips').replaceWith(chips());
	}

	/* ---------------------------------------------------------------- actions */
	function ask(title, value, cb, parentSel) {
		var box = $('<div class="mf-dlg-bg"><form class="mf-dlg" role="dialog" aria-modal="true" aria-label="' + esc(title) + '"><label>' + esc(title) + '<input type="text" maxlength="80" value="' + esc(value || '') + '" required></label>' + (parentSel || '') + '<p class="mf-dlg-b"><button type="submit" class="button button-primary">' + esc(T.ok) + '</button> <button type="button" class="button" data-x>' + esc(T.cancel) + '</button></p><p class="mf-dlg-e" role="alert"></p></form></div>').appendTo('body');
		var inp = box.find('input[type=text]').focus().select();
		function close() { box.remove(); }
		box.on('click', '[data-x]', close).on('keydown', function (e) { if (e.key === 'Escape') { close(); } });
		box.find('form').on('submit', function (e) {
			e.preventDefault();
			var par = box.find('select').val();
			cb($.trim(inp.val()), par).then(close, function (m) { box.find('.mf-dlg-e').text(m); });
		});
	}
	function parentSelect(selected) {
		var h = '<label>' + esc(T.inside) + '<select><option value="0">' + esc(T.top) + '</option>';
		flat().forEach(function (f) { h += '<option value="' + f.id + '"' + (String(f.id) === String(selected) ? ' selected' : '') + '>' + new Array(f.depth + 1).join('— ') + esc(f.name) + '</option>'; });
		return h + '</select></label>';
	}
	function actions(e) {
		var a = $(e.currentTarget).data('act');
		if (a === 'new') {
			ask(T.name, '', function (name, par) { return api('create', { name: name, parent: par || 0 }).then(function (r) { openSet[par] = true; choose(r.id); }); }, parentSelect(cur && cur !== 'none' ? cur : 0));
		} else if (a === 'rename' && find(cur)) {
			ask(T.name, find(cur).name, function (name) { return api('rename', { id: cur, name: name }); });
		} else if (a === 'delete' && find(cur) && window.confirm(T.confirm)) {
			var p = find(cur).parent;
			api('delete', { id: cur }).then(function () { choose(p ? p : ''); });
		}
	}

	/* ---------------------------------------------------------------- drag and drop */
	var dragIds = [];
	function selectedIds(first) {
		var ids = [];
		$('.attachments .attachment.selected, .attachments .attachment[aria-checked="true"]').each(function () { var i = $(this).data('id'); if (i) { ids.push(+i); } });
		$('#the-list input[name="media[]"]:checked').each(function () { ids.push(+this.value); });
		if (first && ids.indexOf(+first) < 0) { ids = [+first]; }
		return ids;
	}
	function makeDraggable() {
		$('.attachments .attachment:not([draggable])').attr('draggable', 'true');
		$('#the-list > tr:not([draggable])').attr('draggable', 'true');
	}
	function dropOn(target, folder) {
		if (!dragIds.length) { return; }
		var ids = dragIds.slice();
		dragIds = [];
		api('move', { ids: ids, folder: folder }).then(function (r) {
			toast(fmt(T.moved, r.moved));
			if (D.list) { window.location.reload(); } else { refreshLibrary(); }
		}, function (m) { toast(m); });
	}
	function bindDrag() {
		$(document).on('dragstart', '.attachments .attachment, #the-list > tr', function (e) {
			var id = $(this).data('id') || ($(this).attr('id') || '').replace('post-', '');
			dragIds = selectedIds(id);
			var dt = e.originalEvent.dataTransfer;
			if (dt) { dt.effectAllowed = 'move'; dt.setData('text/plain', 'mcupr-files:' + dragIds.join(',')); }
			$('body').addClass('mf-dragging');
		}).on('dragend', function () { $('body').removeClass('mf-dragging'); $('.mf-over').removeClass('mf-over'); });
		$(document).on('dragover', '[data-drop]', function (e) {
			if (!dragIds.length) { return; }
			e.preventDefault();
			e.originalEvent.dataTransfer.dropEffect = 'move';
			$(this).addClass('mf-over');
		}).on('dragleave', '[data-drop]', function () { $(this).removeClass('mf-over'); })
			.on('drop', '[data-drop]', function (e) {
				e.preventDefault();
				$(this).removeClass('mf-over');
				dropOn(this, +$(this).data('drop'));
			});
		if (window.MutationObserver) {
			new MutationObserver(makeDraggable).observe(document.body, { childList: true, subtree: true });
		}
		makeDraggable();
	}

	/* ---------------------------------------------------------------- the media page layout */
	function buildPage() {
		var wrap = $('#wpbody-content .wrap').first();
		if (!wrap.length || $('.mf-layout').length) { return; }
		var head = wrap.children('h1, .wp-heading-inline, .page-title-action, .wp-header-end');
		var rest = wrap.children().not(head);
		var layout = $('<div class="mf-layout"><aside class="mf-panel" aria-label="' + esc(T.folders) + '"></aside><div class="mf-main"></div></div>');
		wrap.append(layout);
		panel = layout.find('.mf-panel');
		var main = layout.find('.mf-main');
		strip = $('<div class="mf-strip"></div>');
		main.append($(chips())).append(strip).append(rest);
		// the grid (the media frame) is built a moment later, straight into the page: bring it in beside the panel
		if (window.MutationObserver) {
			new MutationObserver(function (list) {
				list.forEach(function (m) {
					$.each(m.addedNodes, function (i, n) {
						if (n.nodeType === 1 && n.parentNode === wrap[0] && !$(n).is('.mf-layout, h1, .wp-header-end, .page-title-action, .notice, #message')) { main.append(n); }
					});
				});
			}).observe(wrap[0], { childList: true });
		}
		panel.on('click', '[data-choose]', function (e) { e.preventDefault(); choose($(this).data('choose')); })
			.on('click', '[data-tog]', function () { var id = $(this).data('tog'); openSet[id] = !isOpen(id); renderPanel(); })
			.on('click', '[data-act]', actions);
		strip.on('click', '[data-choose]', function (e) { e.preventDefault(); choose($(this).data('choose')); });
		main.on('click', '.mf-chip', function () { pickType($(this).data('type')); });
		renderPanel();
	}

	/* ---------------------------------------------------------------- the filter in the media grid and the pop-up */
	function patchViews() {
		if (!(window.wp && wp.media && wp.media.view && wp.media.view.AttachmentsBrowser) || wp.media.view.AttachmentsBrowser.prototype.mfPatched) { return; }
		var Filter = wp.media.View.extend({
			tagName: 'select',
			className: 'attachment-filters mf-filter',
			attributes: { 'aria-label': T.filter },
			events: { change: 'change' },
			initialize: function () {
				this.listenTo(this.model, 'change:mcupr_folder', this.sync);
				$(document).on('mcupr-mf-changed.mf' + this.cid, this.render.bind(this));
			},
			render: function () {
				var h = '<option value="">' + esc(T.filter + ': ' + T.all) + '</option><option value="none">' + esc(T.filter + ': ' + T.none) + '</option>';
				flat().forEach(function (f) { h += '<option value="' + f.id + '">' + new Array(f.depth + 1).join('— ') + esc(f.name) + '</option>'; });
				this.$el.html(h);
				this.sync();
				return this;
			},
			sync: function () { this.$el.val(String(this.model.get('mcupr_folder') || '')); },
			change: function () { var v = this.$el.val(); if (D.page) { choose(v); } else if (v) { this.model.set({ mcupr_folder: v }); } else { this.model.unset('mcupr_folder'); } }
		});
		var Move = wp.media.View.extend({
			tagName: 'select',
			className: 'attachment-filters mf-move',
			attributes: { 'aria-label': T.moveTo },
			events: { change: 'change' },
			initialize: function () {
				var sel = this.options.selection;
				this.listenTo(sel, 'add remove reset', this.toggle);
				$(document).on('mcupr-mf-changed.mfm' + this.cid, this.render.bind(this));
			},
			render: function () {
				var h = '<option value="">' + esc(T.moveTo) + '</option><option value="0">' + esc(T.none) + '</option>';
				flat().forEach(function (f) { h += '<option value="' + f.id + '">' + new Array(f.depth + 1).join('— ') + esc(f.name) + '</option>'; });
				this.$el.html(h);
				this.toggle();
				return this;
			},
			toggle: function () { this.$el.toggleClass('hidden', !(this.options.selection && this.options.selection.length)); },
			change: function () {
				var v = this.$el.val(), sel = this.options.selection;
				if (v === '' || !sel || !sel.length) { return; }
				var ids = sel.map(function (m) { return m.id; });
				this.$el.val('');
				api('move', { ids: ids, folder: v }).then(function (r) { toast(fmt(T.moved, r.moved)); refreshLibrary(); });
			}
		});
		var create = wp.media.view.AttachmentsBrowser.prototype.createToolbar;
		wp.media.view.AttachmentsBrowser.prototype.createToolbar = function () {
			create.apply(this, arguments);
			if (this.options.filters === false && !this.model) { return; }
			try {
				this.toolbar.set('mcuprFolder', new Filter({ controller: this.controller, model: this.collection.props, priority: -75 }).render());
				var selection = this.options.selection;
				if (selection) { this.toolbar.set('mcuprMove', new Move({ controller: this.controller, selection: selection, priority: -74 }).render()); }
			} catch (e) { /* a toolbar without room for it */ }
		};
		wp.media.view.AttachmentsBrowser.prototype.mfPatched = true;
	}

	/* ---------------------------------------------------------------- start */
	patchViews();
	$(function () {
		patchViews();
		if (D.page) {
			buildPage();
			bindDrag();
			if (!D.list) {
				var tries = 0, t = setInterval(function () {
					if (library() || ++tries > 50) {
						clearInterval(t);
						if (cur !== '') { setLibraryFolder(cur); }
						uploadParam(cur);
						renderStrip();
						$('.mf-chips').replaceWith(chips());
					}
				}, 100);
			}
			$(document).on('mcupr-mf-changed mcupr-mf-chosen', function () { renderPanel(); });
		} else {
			uploadParam('');
		}
	});
}(jQuery));
