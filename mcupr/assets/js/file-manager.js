/* The file manager of the uploads folder, laid out like the Finder of macOS: a sidebar (the folders and the colour tags), a bar with
   back / forward, the way of seeing (icons or list), actions and a search, the folder's contents, and a path bar with the count.
   Drag items onto a folder to move them, drag files from the computer to upload, right-click for a menu, Enter renames, Space is
   Quick Look, Delete deletes (after asking). What the Media library owns is shown with a lock and cannot be changed here.
   The server (inc/file-manager.php) checks everything again: nothing here is trusted. */
( function () {
	'use strict';
	var C = window.mcuprFM;
	var root = document.getElementById( 'mcupr-fm' );
	if ( ! C || ! root ) { return; }
	var T = C.t, I = C.icons;

	/* ------------------------------------------------------------ small helpers */
	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( undefined !== html ) { n.innerHTML = html; }
		return n;
	}
	function esc( s ) { return String( s ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }
	function fmt( s ) { var a = Array.prototype.slice.call( arguments, 1 ), i = 0; return String( s ).replace( /%(\d+\$)?[ds]/g, function ( m, n ) { var v = n ? a[ parseInt( n, 10 ) - 1 ] : a[ i++ ]; return undefined === v ? '' : v; } ); }
	function size( b ) {
		if ( b < 1024 ) { return b + ' B'; }
		if ( b < 1048576 ) { return ( b / 1024 ).toFixed( b < 10240 ? 1 : 0 ) + ' KB'; }
		if ( b < 1073741824 ) { return ( b / 1048576 ).toFixed( b < 10485760 ? 1 : 0 ) + ' MB'; }
		return ( b / 1073741824 ).toFixed( 1 ) + ' GB';
	}
	function when( t ) {
		try { return new Date( t * 1000 ).toLocaleString( document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' } ); } catch ( e ) { return new Date( t * 1000 ).toISOString().slice( 0, 16 ).replace( 'T', ' ' ); }
	}
	function store( k, v ) { try { if ( undefined === v ) { return localStorage.getItem( 'mcuprFM_' + k ); } localStorage.setItem( 'mcuprFM_' + k, v ); } catch ( e ) { return null; } return null; }
	function parentOf( p ) { var i = p.lastIndexOf( '/' ); return i < 0 ? '' : p.slice( 0, i ); }
	function baseOf( p ) { var i = p.lastIndexOf( '/' ); return i < 0 ? p : p.slice( i + 1 ); }

	function post( action, data, onProgress ) {
		return new Promise( function ( ok, no ) {
			var fd = new FormData();
			fd.append( 'action', 'mcupr_fm_' + action );
			fd.append( '_wpnonce', C.nonce );
			Object.keys( data || {} ).forEach( function ( k ) {
				var v = data[ k ];
				if ( Array.isArray( v ) ) { v.forEach( function ( x ) { fd.append( k + '[]', x ); } ); } else if ( v && v.__files ) { v.__files.forEach( function ( f ) { fd.append( k + '[]', f, f.name ); } ); } else { fd.append( k, v ); }
			} );
			var x = new XMLHttpRequest();
			x.open( 'POST', C.ajax );
			x.responseType = 'json';
			if ( onProgress && x.upload ) { x.upload.onprogress = function ( e ) { if ( e.lengthComputable ) { onProgress( e.loaded / e.total ); } }; }
			x.onload = function () {
				var r = x.response;
				if ( r && r.success ) { ok( r.data ); } else { no( ( r && r.data && r.data.message ) || T.failed ); }
			};
			x.onerror = function () { no( T.failed ); };
			x.send( fd );
		} );
	}

	var live = el( 'div', 'fm-live' );
	live.setAttribute( 'role', 'status' );
	live.setAttribute( 'aria-live', 'polite' );
	var toastTimer;
	function toast( msg, bad ) {
		live.textContent = msg;
		live.className = 'fm-live is-on' + ( bad ? ' is-bad' : '' );
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () { live.className = 'fm-live'; }, bad ? 7000 : 3200 );
	}

	/* ------------------------------------------------------------ glyphs */
	var DEFS = '<svg class="fm-defs" width="0" height="0" aria-hidden="true" focusable="false"><defs><linearGradient id="fmf1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7cc4fb"/><stop offset="1" stop-color="#3a9bf0"/></linearGradient><linearGradient id="fmf2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#58b3fa"/><stop offset="1" stop-color="#1f86e8"/></linearGradient></defs></svg>'; /* the folder's colours live here, once, where they are always drawn (a hidden sidebar would take them away) */
	var FOLDER = '<svg viewBox="0 0 64 52" aria-hidden="true" focusable="false"><path d="M4 8a4 4 0 0 1 4-4h14l5 5h29a4 4 0 0 1 4 4v3H4z" fill="url(#fmf1)"/><rect x="4" y="14" width="56" height="34" rx="4" fill="url(#fmf2)"/></svg>';
	var COLORS = { image: '#ff7a59', video: '#8e6bf0', audio: '#e0568f', pdf: '#e5483f', doc: '#3a86e8', sheet: '#2fa35b', slides: '#f08a24', archive: '#a07a4f', other: '#8e8e93' };
	function fileGlyph( e ) {
		var c = COLORS[ e.kind ] || COLORS.other, label = esc( ( e.ext || '' ).slice( 0, 4 ).toUpperCase() );
		return '<svg viewBox="0 0 48 60" aria-hidden="true" focusable="false"><path d="M6 4a4 4 0 0 1 4-4h20l12 12v44a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4z" fill="#fff" stroke="#c7c7cc" stroke-width="1.5"/><path d="M30 0l12 12H34a4 4 0 0 1-4-4z" fill="#e5e5ea"/><rect x="6" y="38" width="36" height="14" rx="2" fill="' + c + '"/><text x="24" y="48.5" text-anchor="middle" font-size="9" font-weight="700" fill="#fff" font-family="system-ui,sans-serif">' + label + '</text></svg>';
	}
	function glyph( e ) { return e.dir ? FOLDER : fileGlyph( e ); }
	function kindName( e ) { var k = e.kind; return ( T[ k ] || T.other ) + ( e.ext && ! e.dir ? ' · ' + e.ext.toUpperCase() : '' ); }
	function tagDef( id ) { for ( var i = 0; i < C.tags.length; i++ ) { if ( C.tags[ i ].id === id ) { return C.tags[ i ]; } } return null; }
	function dots( e ) {
		return ( e.tags || [] ).map( function ( id ) { var d = tagDef( id ); return d ? '<i class="fm-dot" style="background:' + d.color + '" title="' + esc( d.name ) + '"></i>' : ''; } ).join( '' );
	}

	/* ------------------------------------------------------------ state */
	var S = {
		path: '', tag: 0, items: [], crumbs: [], sel: {}, last: null, q: '', counts: {},
		view: store( 'view' ) === 'list' ? 'list' : 'icons', sort: { key: 'name', dir: 1 },
		hist: [], hi: -1, drag: null, tree: { '': null }, open: { '': true }, renaming: null
	};

	/* ------------------------------------------------------------ the skeleton */
	root.innerHTML =
		'<aside class="fm-side" aria-label="' + esc( T.locations ) + '">' +
		'<p class="fm-side-h">' + esc( T.locations ) + '</p><ul class="fm-tree" role="tree"></ul>' +
		'<p class="fm-side-h">' + esc( T.tags ) + '</p><ul class="fm-tags"></ul></aside>' +
		'<div class="fm-main">' +
		'<div class="fm-bar" role="toolbar" aria-label="' + esc( T.title ) + '">' +
		'<div class="fm-nav"><button type="button" class="fm-btn" data-act="back" aria-label="' + esc( T.back ) + '" title="' + esc( T.back ) + '">' + I[ 'chevron-left' ] + '</button><button type="button" class="fm-btn" data-act="fwd" aria-label="' + esc( T.forward ) + '" title="' + esc( T.forward ) + '">' + I[ 'chevron-right' ] + '</button></div>' +
		'<h2 class="fm-title"></h2>' +
		'<div class="fm-seg" role="group" aria-label="' + esc( T.icons ) + ' / ' + esc( T.list ) + '"><button type="button" class="fm-btn" data-view="icons" aria-label="' + esc( T.icons ) + '" title="' + esc( T.icons ) + '">' + I.grid + '</button><button type="button" class="fm-btn" data-view="list" aria-label="' + esc( T.list ) + '" title="' + esc( T.list ) + '">' + I.list + '</button></div>' +
		'<div class="fm-acts"><button type="button" class="fm-btn" data-act="mkdir" aria-label="' + esc( T.newFolder ) + '" title="' + esc( T.newFolder ) + '">' + I[ 'folder-plus' ] + '</button><button type="button" class="fm-btn" data-act="upload" aria-label="' + esc( T.upload ) + '" title="' + esc( T.upload ) + '">' + I.upload + '</button><button type="button" class="fm-btn" data-act="download" aria-label="' + esc( T.download ) + '" title="' + esc( T.download ) + '">' + I.download + '</button><button type="button" class="fm-btn fm-danger" data-act="delete" aria-label="' + esc( T[ 'delete' ] ) + '" title="' + esc( T[ 'delete' ] ) + '">' + I[ 'trash-2' ] + '</button></div>' +
		'<label class="fm-search">' + I.search + '<input type="search" placeholder="' + esc( T.search ) + '" aria-label="' + esc( T.search ) + '"></label>' +
		'</div>' +
		'<div class="fm-body" tabindex="0" role="listbox" aria-multiselectable="true" aria-label="' + esc( T.title ) + '"></div>' +
		'<div class="fm-status"><nav class="fm-path" aria-label="' + esc( T.locations ) + '"></nav><span class="fm-count"></span></div>' +
		'</div>' +
		'<input type="file" class="fm-file" multiple hidden>' +
		'<div class="fm-menu" role="menu" hidden></div><div class="fm-drop-note" hidden>' + esc( T.dropHere ) + '</div>';
	root.insertAdjacentHTML( 'afterbegin', DEFS );
	root.appendChild( live );
	var $ = function ( s ) { return root.querySelector( s ); };
	var elTree = $( '.fm-tree' ), elTags = $( '.fm-tags' ), elBody = $( '.fm-body' ), elTitle = $( '.fm-title' ), elPath = $( '.fm-path' ), elCount = $( '.fm-count' ), elMenu = $( '.fm-menu' ), elFile = $( '.fm-file' ), elQ = $( '.fm-search input' ), elDrop = $( '.fm-drop-note' );

	/* ------------------------------------------------------------ data */
	function byPath( p ) { for ( var i = 0; i < S.items.length; i++ ) { if ( S.items[ i ].path === p ) { return S.items[ i ]; } } return null; }
	function selected() { return S.items.filter( function ( e ) { return S.sel[ e.path ]; } ); }
	function shown() {
		var q = S.q.trim().toLowerCase();
		var a = S.items.filter( function ( e ) { return ! q || e.name.toLowerCase().indexOf( q ) >= 0; } );
		var k = S.sort.key, d = S.sort.dir;
		a.sort( function ( x, y ) {
			if ( x.dir !== y.dir ) { return x.dir ? -1 : 1; }
			var r;
			if ( 'date' === k ) { r = x.mtime - y.mtime; } else if ( 'size' === k ) { r = x.size - y.size; } else if ( 'kind' === k ) { r = kindName( x ).localeCompare( kindName( y ) ); } else { r = 0; }
			if ( ! r ) { r = x.name.localeCompare( y.name, undefined, { numeric: true, sensitivity: 'base' } ); return r * ( 'name' === k ? d : 1 ); }
			return r * d;
		} );
		return a;
	}

	function load( path, tag, push ) {
		S.renaming = null;
		return post( 'list', tag ? { tag: tag } : { path: path } ).then( function ( d ) {
			S.path = tag ? '' : d.path;
			S.tag = tag || 0;
			S.items = d.items;
			S.crumbs = d.crumbs;
			S.counts = d.counts || S.counts;
			S.sel = {};
			S.last = null;
			S.q = '';
			elQ.value = '';
			if ( push !== false ) {
				S.hist = S.hist.slice( 0, S.hi + 1 );
				S.hist.push( { path: S.path, tag: S.tag } );
				S.hi = S.hist.length - 1;
			}
			if ( ! tag ) { S.tree[ S.path ] = d.items.filter( function ( e ) { return e.dir; } ); S.open[ S.path ] = true; }
			render();
			return ensureAncestors( S.path );
		}, function ( m ) { toast( m, true ); } );
	}
	function reload() { return load( S.path, S.tag, false ); }
	function go( p ) { return load( p, 0 ); }

	/* the sidebar tree: folders load when they are opened */
	function ensureAncestors( p ) {
		var parts = p ? p.split( '/' ) : [], acc = '', jobs = [];
		S.open[ '' ] = true;
		parts.forEach( function ( x ) { acc += ( acc ? '/' : '' ) + x; S.open[ acc ] = true; } );
		[ '' ].concat( parts.map( function ( x, i ) { return parts.slice( 0, i + 1 ).join( '/' ); } ) ).forEach( function ( a ) {
			if ( ! S.tree[ a ] ) { jobs.push( post( 'list', { path: a, dirs: '1' } ).then( function ( d ) { S.tree[ a ] = d.items; } ) ); }
		} );
		return Promise.all( jobs ).then( renderTree, renderTree );
	}
	function refreshTree( paths ) {
		var jobs = [];
		( paths || [] ).concat( [ S.path ] ).forEach( function ( p ) {
			if ( S.open[ p ] || '' === p ) { jobs.push( post( 'list', { path: p, dirs: '1' } ).then( function ( d ) { S.tree[ p ] = d.items; }, function () { delete S.tree[ p ]; } ) ); }
		} );
		return Promise.all( jobs ).then( renderTree );
	}

	/* ------------------------------------------------------------ drawing */
	function render() { renderTree(); renderTags(); renderBar(); renderBody(); renderStatus(); }

	function renderTree() {
		elTree.innerHTML = '';
		function node( p, name, depth ) {
			var kids = S.tree[ p ], open = !! S.open[ p ], li = el( 'li', 'fm-node' );
			li.setAttribute( 'role', 'treeitem' );
			li.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			var row = el( 'div', 'fm-row' + ( ! S.tag && S.path === p ? ' is-on' : '' ) );
			row.style.paddingLeft = ( 8 + depth * 14 ) + 'px';
			row.setAttribute( 'data-drop', p );
			var hasKids = null === kids || undefined === kids || kids.length > 0;
			var tw = el( 'button', 'fm-tw' + ( open ? ' is-open' : '' ) + ( hasKids ? '' : ' is-empty' ), I[ 'chevron-right' ] );
			tw.type = 'button';
			tw.setAttribute( 'tabindex', '-1' );
			tw.setAttribute( 'aria-label', name );
			tw.addEventListener( 'click', function ( ev ) {
				ev.stopPropagation();
				S.open[ p ] = ! S.open[ p ];
				if ( S.open[ p ] && ! S.tree[ p ] ) { post( 'list', { path: p, dirs: '1' } ).then( function ( d ) { S.tree[ p ] = d.items; renderTree(); } ); } else { renderTree(); }
			} );
			var ic = el( 'span', 'fm-ti', '' === p ? I[ 'hard-drive' ] : FOLDER );
			var lab = el( 'span', 'fm-tl' );
			lab.textContent = name;
			row.appendChild( tw ); row.appendChild( ic ); row.appendChild( lab );
			row.addEventListener( 'click', function () { go( p ); } );
			li.appendChild( row );
			if ( open && kids && kids.length ) {
				var ul = el( 'ul' );
				ul.setAttribute( 'role', 'group' );
				kids.forEach( function ( k ) { ul.appendChild( node( k.path, k.name, depth + 1 ) ); } );
				li.appendChild( ul );
			}
			return li;
		}
		elTree.appendChild( node( '', T.uploads, 0 ) );
	}

	function renderTags() {
		elTags.innerHTML = '';
		C.tags.forEach( function ( d ) {
			var li = el( 'li' ), b = el( 'button', 'fm-tagrow' + ( S.tag === d.id ? ' is-on' : '' ) );
			b.type = 'button';
			b.setAttribute( 'data-drop-tag', d.id );
			b.innerHTML = '<i class="fm-dot fm-dot-l" style="background:' + d.color + '"></i><span class="fm-tl"></span><span class="fm-n">' + ( S.counts[ d.id ] || '' ) + '</span>';
			b.querySelector( '.fm-tl' ).textContent = d.name;
			var wait;
			b.addEventListener( 'click', function ( ev ) { if ( ev.detail > 1 ) { return; } clearTimeout( wait ); wait = setTimeout( function () { if ( S.tag === d.id ) { go( '' ); } else { load( '', d.id ); } }, 230 ); } ); // a double click renames: it must not filter first
			b.addEventListener( 'dblclick', function ( ev ) { ev.preventDefault(); clearTimeout( wait ); renameTag( d, b ); } );
			b.addEventListener( 'contextmenu', function ( ev ) { ev.preventDefault(); showMenu( ev.clientX, ev.clientY, [ { label: T.renameTag, run: function () { renameTag( d, b ); } } ] ); } );
			li.appendChild( b );
			elTags.appendChild( li );
		} );
	}
	function renameTag( d, row ) {
		var lab = row.querySelector( '.fm-tl' ), inp = el( 'input', 'fm-edit' );
		inp.value = d.name;
		inp.maxLength = 30;
		inp.setAttribute( 'aria-label', T.renameTag );
		lab.replaceWith( inp );
		inp.focus();
		inp.select();
		var done = false;
		function finish( save ) {
			if ( done ) { return; }
			done = true;
			var v = inp.value.trim();
			if ( save && v && v !== d.name ) {
				post( 'tagname', { id: d.id, name: v } ).then( function ( r ) { C.tags = r.tags; render(); }, function ( m ) { toast( m, true ); renderTags(); } );
			} else { renderTags(); }
		}
		inp.addEventListener( 'keydown', function ( ev ) { if ( 'Enter' === ev.key ) { finish( true ); } else if ( 'Escape' === ev.key ) { finish( false ); } ev.stopPropagation(); } );
		inp.addEventListener( 'blur', function () { finish( true ); } );
		inp.addEventListener( 'click', function ( ev ) { ev.stopPropagation(); } );
	}

	function renderBar() {
		var d = S.tag ? tagDef( S.tag ) : null;
		elTitle.innerHTML = '';
		if ( d ) { elTitle.innerHTML = '<i class="fm-dot fm-dot-l" style="background:' + d.color + '"></i> '; elTitle.appendChild( document.createTextNode( d.name ) ); } else { elTitle.textContent = S.path ? baseOf( S.path ) : T.uploads; }
		root.querySelector( '[data-act="back"]' ).disabled = S.hi <= 0;
		root.querySelector( '[data-act="fwd"]' ).disabled = S.hi >= S.hist.length - 1;
		[].forEach.call( root.querySelectorAll( '[data-view]' ), function ( b ) { b.classList.toggle( 'is-on', b.getAttribute( 'data-view' ) === S.view ); b.setAttribute( 'aria-pressed', b.getAttribute( 'data-view' ) === S.view ? 'true' : 'false' ); } );
		var any = selected().length > 0, can = selected().filter( function ( e ) { return ! e.locked; } ).length > 0;
		root.querySelector( '[data-act="download"]' ).disabled = ! any;
		root.querySelector( '[data-act="delete"]' ).disabled = ! can;
		root.querySelector( '[data-act="mkdir"]' ).disabled = !! S.tag;
		root.querySelector( '[data-act="upload"]' ).disabled = !! S.tag;
	}

	function renderBody() {
		var list = shown();
		elBody.innerHTML = '';
		elBody.className = 'fm-body is-' + S.view;
		if ( ! list.length ) {
			elBody.appendChild( el( 'p', 'fm-empty', esc( S.q ? T.noMatch : ( S.tag ? T.noTagItems : T.empty ) ) ) );
			return;
		}
		if ( 'list' === S.view ) {
			var head = el( 'div', 'fm-lh' );
			[ [ 'name', T.name ], [ 'date', T.modified ], [ 'size', T.size ], [ 'kind', T.kind ] ].forEach( function ( c ) {
				var b = el( 'button', 'fm-lc fm-c-' + c[ 0 ] );
				b.type = 'button';
				b.textContent = c[ 1 ] + ( S.sort.key === c[ 0 ] ? ( S.sort.dir > 0 ? ' ▲' : ' ▼' ) : '' );
				b.addEventListener( 'click', function () { S.sort = { key: c[ 0 ], dir: S.sort.key === c[ 0 ] ? -S.sort.dir : 1 }; renderBody(); } );
				head.appendChild( b );
			} );
			elBody.appendChild( head );
		}
		list.forEach( function ( e ) { elBody.appendChild( item( e ) ); } );
	}

	function item( e ) {
		var n = el( 'div', 'fm-it' + ( S.sel[ e.path ] ? ' is-sel' : '' ) + ( e.locked ? ' is-locked' : '' ) + ( e.dir ? ' is-dir' : '' ) );
		n.setAttribute( 'role', 'option' );
		n.setAttribute( 'aria-selected', S.sel[ e.path ] ? 'true' : 'false' );
		n.setAttribute( 'data-path', e.path );
		n.draggable = ! e.locked;
		var thumb = ! e.dir && 'image' === e.kind && 'svg' !== e.ext && e.url && 'icons' === S.view ? '<img src="' + esc( e.url ) + '" alt="" loading="lazy" draggable="false">' : glyph( e );
		var lock = e.locked ? '<span class="fm-lock" title="' + esc( T.locked ) + '">' + I.lock + '</span>' : '';
		if ( 'list' === S.view ) {
			n.innerHTML = '<span class="fm-c-name"><span class="fm-g">' + glyph( e ) + '</span><span class="fm-nm"></span>' + lock + '<span class="fm-dots">' + dots( e ) + '</span></span><span class="fm-c-date">' + esc( when( e.mtime ) ) + '</span><span class="fm-c-size">' + ( e.dir ? '—' : esc( size( e.size ) ) ) + '</span><span class="fm-c-kind">' + esc( kindName( e ) ) + '</span>';
		} else {
			n.innerHTML = '<span class="fm-g' + ( thumb !== glyph( e ) ? ' is-photo' : '' ) + '">' + thumb + lock + '</span><span class="fm-nm"></span><span class="fm-dots">' + dots( e ) + '</span>';
		}
		n.querySelector( '.fm-nm' ).textContent = e.name;
		n.title = e.name + ( e.locked ? ' — ' + T.locked : '' );
		if ( S.renaming === e.path ) { startRename( n, e ); }
		return n;
	}
	function renderStatus() {
		elPath.innerHTML = '';
		( S.tag ? [ { name: T.uploads, path: '' } ] : S.crumbs ).forEach( function ( c, i, a ) {
			var b = el( 'button', 'fm-crumb' );
			b.type = 'button';
			b.setAttribute( 'data-drop', c.path );
			b.innerHTML = ( 0 === i ? I[ 'hard-drive' ] : FOLDER ) + '<span></span>';
			b.querySelector( 'span' ).textContent = c.name;
			if ( i === a.length - 1 && ! S.tag ) { b.setAttribute( 'aria-current', 'location' ); }
			b.addEventListener( 'click', function () { go( c.path ); } );
			elPath.appendChild( b );
		} );
		var sel = selected().length, all = shown().length;
		elCount.textContent = sel ? fmt( T.selected, sel, all ) : fmt( T.items, all );
	}
	function paintSelection() {
		[].forEach.call( elBody.querySelectorAll( '.fm-it' ), function ( n ) {
			var on = !! S.sel[ n.getAttribute( 'data-path' ) ];
			n.classList.toggle( 'is-sel', on );
			n.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		renderBar();
		renderStatus();
	}

	/* ------------------------------------------------------------ selection */
	function pick( path, ev ) {
		var list = shown().map( function ( e ) { return e.path; } );
		if ( ev && ev.shiftKey && S.last && list.indexOf( S.last ) >= 0 ) {
			var a = list.indexOf( S.last ), b = list.indexOf( path ), lo = Math.min( a, b ), hi = Math.max( a, b );
			if ( ! ( ev.metaKey || ev.ctrlKey ) ) { S.sel = {}; }
			list.slice( lo, hi + 1 ).forEach( function ( p ) { S.sel[ p ] = true; } );
		} else if ( ev && ( ev.metaKey || ev.ctrlKey ) ) {
			if ( S.sel[ path ] ) { delete S.sel[ path ]; } else { S.sel[ path ] = true; }
			S.last = path;
		} else {
			S.sel = {};
			S.sel[ path ] = true;
			S.last = path;
		}
		paintSelection();
	}
	function itemOf( target ) { var n = target.closest ? target.closest( '.fm-it' ) : null; return n ? byPath( n.getAttribute( 'data-path' ) ) : null; }

	elBody.addEventListener( 'click', function ( ev ) {
		if ( ev.target.closest( '.fm-edit' ) ) { return; }
		var e = itemOf( ev.target );
		if ( e ) { pick( e.path, ev ); } else if ( ! ev.target.closest( '.fm-lh' ) ) { S.sel = {}; paintSelection(); }
	} );
	elBody.addEventListener( 'dblclick', function ( ev ) {
		var e = itemOf( ev.target );
		if ( e ) { open( e ); }
	} );
	function open( e ) {
		if ( e.dir ) { go( e.path ); } else { quick( e ); }
	}

	/* ------------------------------------------------------------ keyboard */
	elBody.addEventListener( 'keydown', function ( ev ) {
		if ( ev.target.closest( '.fm-edit' ) ) { return; }
		var list = shown(), cur = selected(), k = ev.key;
		if ( ( ev.metaKey || ev.ctrlKey ) && 'a' === k.toLowerCase() ) { ev.preventDefault(); S.sel = {}; list.forEach( function ( e ) { S.sel[ e.path ] = true; } ); paintSelection(); return; }
		if ( 'ArrowRight' === k || 'ArrowDown' === k || 'ArrowLeft' === k || 'ArrowUp' === k ) {
			ev.preventDefault();
			if ( ! list.length ) { return; }
			var idx = S.last ? list.map( function ( e ) { return e.path; } ).indexOf( S.last ) : -1;
			var step = 'ArrowRight' === k || 'ArrowDown' === k ? 1 : -1;
			if ( 'icons' === S.view && ( 'ArrowDown' === k || 'ArrowUp' === k ) ) {
				var first = elBody.querySelector( '.fm-it' ), per = first ? Math.max( 1, Math.floor( elBody.clientWidth / first.offsetWidth ) ) : 1;
				step *= per;
			}
			var to = list[ Math.max( 0, Math.min( list.length - 1, idx < 0 ? 0 : idx + step ) ) ];
			pick( to.path, ev.shiftKey ? { shiftKey: true } : null );
			var node = elBody.querySelector( '[data-path="' + ( window.CSS && CSS.escape ? CSS.escape( to.path ) : to.path ) + '"]' );
			if ( node && node.scrollIntoView ) { node.scrollIntoView( { block: 'nearest' } ); }
			return;
		}
		if ( ' ' === k && cur.length ) { ev.preventDefault(); quick( cur[ 0 ] ); return; }
		if ( 'Enter' === k && 1 === cur.length ) { ev.preventDefault(); beginRename( cur[ 0 ] ); return; }
		if ( ( 'Delete' === k || 'Backspace' === k ) && cur.length ) { ev.preventDefault(); askDelete( cur ); return; }
		if ( 'Escape' === k ) { S.sel = {}; paintSelection(); }
	} );

	/* ------------------------------------------------------------ rename */
	function beginRename( e ) {
		if ( e.locked ) { toast( e.dir ? T.locked : T.locked, true ); return; }
		S.renaming = e.path;
		renderBody();
	}
	function startRename( n, e ) {
		var nm = n.querySelector( '.fm-nm' ), inp = el( 'input', 'fm-edit' );
		inp.value = e.name;
		inp.setAttribute( 'aria-label', T.rename );
		nm.replaceWith( inp );
		inp.focus();
		var dot = e.dir ? -1 : e.name.lastIndexOf( '.' );
		inp.setSelectionRange( 0, dot > 0 ? dot : e.name.length );
		var done = false;
		function finish( save ) {
			if ( done ) { return; }
			done = true;
			S.renaming = null;
			var v = inp.value.trim();
			if ( save && v && v !== e.name ) {
				post( 'rename', { path: e.path, name: v } ).then( function ( r ) {
					var keep = r.path;
					return reload().then( function () { S.sel = {}; S.sel[ keep ] = true; S.last = keep; paintSelection(); return refreshTree( [ parentOf( e.path ) ] ); } );
				}, function ( m ) { toast( m, true ); renderBody(); } );
			} else { renderBody(); }
		}
		inp.addEventListener( 'keydown', function ( ev ) { if ( 'Enter' === ev.key ) { ev.preventDefault(); finish( true ); } else if ( 'Escape' === ev.key ) { finish( false ); } ev.stopPropagation(); } );
		inp.addEventListener( 'blur', function () { finish( true ); } );
		inp.addEventListener( 'click', function ( ev ) { ev.stopPropagation(); } );
		inp.addEventListener( 'dblclick', function ( ev ) { ev.stopPropagation(); } );
	}

	/* ------------------------------------------------------------ actions */
	function newFolder() {
		if ( S.tag ) { return; }
		var base = T.untitled, name = base, n = 1, names = S.items.map( function ( e ) { return e.name; } );
		while ( names.indexOf( name ) >= 0 ) { n++; name = base + ' ' + n; }
		post( 'mkdir', { path: S.path, name: name } ).then( function ( r ) {
			return reload().then( function () {
				var e = byPath( r.path );
				if ( e ) { S.sel = {}; S.sel[ e.path ] = true; S.last = e.path; S.renaming = e.path; renderBody(); paintSelection(); }
				return refreshTree( [ S.path ] );
			} );
		}, function ( m ) { toast( m, true ); } );
	}
	function moveTo( paths, dest ) {
		paths = paths.filter( function ( p ) { return parentOf( p ) !== dest; } );
		if ( ! paths.length ) { return; }
		post( 'move', { paths: paths, dest: dest } ).then( function () {
			toast( T.moved );
			return reload().then( function () { return refreshTree( [ dest ].concat( paths.map( parentOf ) ) ); } );
		}, function ( m ) { toast( m, true ); reload(); } );
	}
	function askDelete( list ) {
		var del = list.filter( function ( e ) { return ! e.locked; } );
		if ( ! del.length ) { toast( T.locked, true ); return; }
		post( 'weigh', { paths: del.map( function ( e ) { return e.path; } ) } ).then( function ( w ) {
			confirmBox( T.confirm, fmt( T.confirmTxt, w.files, w.folders, size( w.bytes ) ), T[ 'delete' ], function () {
				post( 'delete', { paths: del.map( function ( e ) { return e.path; } ) } ).then( function () {
					toast( T.deleted );
					return reload().then( function () { return refreshTree( del.map( function ( e ) { return parentOf( e.path ); } ) ); } );
				}, function ( m ) { toast( m, true ); } );
			} );
		}, function ( m ) { toast( m, true ); } );
	}
	function confirmBox( title, text, okLabel, run ) {
		var wrap = el( 'div', 'fm-modal' ), box = el( 'div', 'fm-box' );
		box.setAttribute( 'role', 'alertdialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.innerHTML = '<h3></h3><p></p><div class="fm-box-act"><button type="button" class="fm-pill" data-no></button><button type="button" class="fm-pill fm-pill-danger" data-yes></button></div>';
		box.querySelector( 'h3' ).textContent = title;
		box.querySelector( 'p' ).textContent = text;
		box.querySelector( '[data-no]' ).textContent = T.cancel;
		box.querySelector( '[data-yes]' ).textContent = okLabel;
		wrap.appendChild( box );
		root.appendChild( wrap );
		var last = document.activeElement;
		function close() { wrap.remove(); if ( last && last.focus ) { last.focus(); } }
		box.querySelector( '[data-no]' ).addEventListener( 'click', close );
		box.querySelector( '[data-yes]' ).addEventListener( 'click', function () { close(); run(); } );
		wrap.addEventListener( 'click', function ( ev ) { if ( ev.target === wrap ) { close(); } } );
		box.addEventListener( 'keydown', function ( ev ) { if ( 'Escape' === ev.key ) { ev.stopPropagation(); close(); } } );
		box.querySelector( '[data-no]' ).focus();
	}
	function download( list ) {
		if ( ! list.length ) { return; }
		var q = [ 'action=mcupr_fm_download', '_wpnonce=' + encodeURIComponent( C.nonce ) ];
		list.forEach( function ( e ) { q.push( 'p[]=' + encodeURIComponent( e.path ) ); } );
		var f = el( 'iframe' );
		f.className = 'fm-dl';
		f.setAttribute( 'aria-hidden', 'true' );
		f.setAttribute( 'tabindex', '-1' );
		f.addEventListener( 'load', function () {
			try { var t = ( f.contentDocument && f.contentDocument.body ? f.contentDocument.body.innerText : '' ).trim(); if ( t ) { toast( t.slice( 0, 200 ), true ); } } catch ( e ) { /* a download: nothing to read */ }
			setTimeout( function () { f.remove(); }, 1000 );
		} );
		f.src = C.ajax + '?' + q.join( '&' );
		root.appendChild( f );
		setTimeout( function () { if ( f.parentNode ) { f.remove(); } }, 600000 );
	}
	function upload( files, dest ) {
		files = [].slice.call( files );
		if ( ! files.length ) { return; }
		var big = files.filter( function ( f ) { return f.size > C.max; } );
		if ( big.length ) { toast( big[ 0 ].name + ': ' + fmt( T.tooBig, size( C.max ) ), true ); files = files.filter( function ( f ) { return f.size <= C.max; } ); if ( ! files.length ) { return; } }
		toast( T.uploading );
		post( 'upload', { path: dest, files: { __files: files } }, function ( p ) { live.textContent = T.uploading + ' ' + Math.round( p * 100 ) + '%'; } ).then( function ( r ) {
			var bad = ( r.bad || [] ).map( function ( b ) { return b[ 0 ] + ': ' + b[ 1 ]; } );
			toast( fmt( T.uploaded, ( r.done || [] ).length ) + ( bad.length ? ' ' + bad.join( ' · ' ) : '' ), bad.length > 0 );
			return reload();
		}, function ( m ) { toast( m, true ); } );
	}
	function setTag( list, id ) {
		var all = list.length && list.every( function ( e ) { return ( e.tags || [] ).indexOf( id ) >= 0; } );
		post( 'tag', { paths: list.map( function ( e ) { return e.path; } ), tags: [ id ], mode: all ? 'remove' : 'add' } ).then( function ( r ) {
			S.counts = r.counts;
			return reload();
		}, function ( m ) { toast( m, true ); } );
	}
	function clearTags( list ) {
		post( 'tag', { paths: list.map( function ( e ) { return e.path; } ), tags: [], mode: 'set' } ).then( function ( r ) { S.counts = r.counts; return reload(); }, function ( m ) { toast( m, true ); } );
	}

	/* ------------------------------------------------------------ the bar */
	root.querySelector( '.fm-bar' ).addEventListener( 'click', function ( ev ) {
		var b = ev.target.closest( 'button' );
		if ( ! b || b.disabled ) { return; }
		var act = b.getAttribute( 'data-act' ), v = b.getAttribute( 'data-view' );
		if ( v ) { S.view = v; store( 'view', v ); renderBar(); renderBody(); return; }
		if ( 'back' === act && S.hi > 0 ) { S.hi--; var h = S.hist[ S.hi ]; load( h.path, h.tag, false ); }
		if ( 'fwd' === act && S.hi < S.hist.length - 1 ) { S.hi++; var f = S.hist[ S.hi ]; load( f.path, f.tag, false ); }
		if ( 'mkdir' === act ) { newFolder(); }
		if ( 'upload' === act ) { elFile.click(); }
		if ( 'download' === act ) { download( selected() ); }
		if ( 'delete' === act ) { askDelete( selected() ); }
	} );
	elFile.addEventListener( 'change', function () { upload( elFile.files, S.path ); elFile.value = ''; } );
	elQ.addEventListener( 'input', function () { S.q = elQ.value; renderBody(); renderStatus(); } );

	/* ------------------------------------------------------------ the menu */
	function showMenu( x, y, rows ) {
		elMenu.innerHTML = '';
		rows.forEach( function ( r ) {
			if ( r.tags ) {
				var row = el( 'div', 'fm-mtags' );
				row.setAttribute( 'role', 'group' );
				row.setAttribute( 'aria-label', T.tagMenu );
				C.tags.forEach( function ( d ) {
					var on = r.list.length && r.list.every( function ( e ) { return ( e.tags || [] ).indexOf( d.id ) >= 0; } );
					var b = el( 'button', 'fm-mdot' + ( on ? ' is-on' : '' ), '<i style="background:' + d.color + '"></i>' );
					b.type = 'button';
					b.setAttribute( 'role', 'menuitemcheckbox' );
					b.setAttribute( 'aria-checked', on ? 'true' : 'false' );
					b.setAttribute( 'aria-label', d.name );
					b.title = d.name;
					b.addEventListener( 'click', function () { hideMenu(); setTag( r.list, d.id ); } );
					row.appendChild( b );
				} );
				elMenu.appendChild( row );
				return;
			}
			if ( r.sep ) { elMenu.appendChild( el( 'hr' ) ); return; }
			var b = el( 'button', 'fm-mi' + ( r.danger ? ' is-danger' : '' ) );
			b.type = 'button';
			b.setAttribute( 'role', 'menuitem' );
			b.textContent = r.label;
			if ( r.disabled ) { b.disabled = true; }
			b.addEventListener( 'click', function () { hideMenu(); r.run(); } );
			elMenu.appendChild( b );
		} );
		elMenu.hidden = false;
		var rc = root.getBoundingClientRect();
		elMenu.style.left = Math.max( 4, Math.min( x - rc.left, rc.width - elMenu.offsetWidth - 6 ) ) + 'px';
		elMenu.style.top = Math.max( 4, Math.min( y - rc.top, rc.height - elMenu.offsetHeight - 6 ) ) + 'px';
		var first = elMenu.querySelector( 'button:not(:disabled)' );
		if ( first ) { first.focus(); }
	}
	function hideMenu() { elMenu.hidden = true; }
	document.addEventListener( 'click', function ( ev ) { if ( ! elMenu.hidden && ! elMenu.contains( ev.target ) ) { hideMenu(); } } );
	document.addEventListener( 'keydown', function ( ev ) { if ( 'Escape' === ev.key && ! elMenu.hidden ) { hideMenu(); elBody.focus(); } } );
	elBody.addEventListener( 'contextmenu', function ( ev ) {
		ev.preventDefault();
		var e = itemOf( ev.target );
		if ( e ) { if ( ! S.sel[ e.path ] ) { pick( e.path, null ); } } else { S.sel = {}; paintSelection(); }
		var list = selected();
		if ( ! list.length ) {
			showMenu( ev.clientX, ev.clientY, [ { label: T.newFolder, disabled: !! S.tag, run: newFolder }, { label: T.upload, disabled: !! S.tag, run: function () { elFile.click(); } } ] );
			return;
		}
		var one = 1 === list.length ? list[ 0 ] : null, locked = list.some( function ( x ) { return x.locked; } );
		showMenu( ev.clientX, ev.clientY, [
			{ label: one && one.dir ? T.open : T.quick, run: function () { open( list[ 0 ] ); } },
			{ label: T.rename, disabled: ! one || one.locked, run: function () { beginRename( one ); } },
			{ label: T.download, run: function () { download( list ); } },
			{ sep: true },
			{ tags: true, list: list },
			{ label: T.noTags, run: function () { clearTags( list ); } },
			{ sep: true },
			{ label: T[ 'delete' ], danger: true, disabled: locked, run: function () { askDelete( list ); } }
		] );
	} );
	elMenu.addEventListener( 'keydown', function ( ev ) {
		if ( 'ArrowDown' !== ev.key && 'ArrowUp' !== ev.key ) { return; }
		ev.preventDefault();
		var bs = [].slice.call( elMenu.querySelectorAll( 'button:not(:disabled)' ) ), i = bs.indexOf( document.activeElement );
		bs[ ( i + ( 'ArrowDown' === ev.key ? 1 : -1 ) + bs.length ) % bs.length ].focus();
	} );

	/* ------------------------------------------------------------ drag and drop */
	elBody.addEventListener( 'dragstart', function ( ev ) {
		var e = itemOf( ev.target );
		if ( ! e || e.locked ) { ev.preventDefault(); return; }
		if ( ! S.sel[ e.path ] ) { pick( e.path, null ); }
		S.drag = selected().filter( function ( x ) { return ! x.locked; } ).map( function ( x ) { return x.path; } );
		ev.dataTransfer.effectAllowed = 'move';
		ev.dataTransfer.setData( 'text/plain', S.drag.join( '\n' ) );
		root.classList.add( 'is-dragging' );
	} );
	elBody.addEventListener( 'dragend', function () { S.drag = null; root.classList.remove( 'is-dragging' ); clearDrop(); } );
	function dropTarget( t ) {
		var n = t.closest ? t.closest( '[data-drop], .fm-it.is-dir, [data-drop-tag]' ) : null;
		if ( ! n ) { return null; }
		if ( n.classList.contains( 'fm-it' ) ) { return { node: n, dest: n.getAttribute( 'data-path' ) }; }
		if ( n.hasAttribute( 'data-drop-tag' ) ) { return { node: n, tag: parseInt( n.getAttribute( 'data-drop-tag' ), 10 ) }; }
		return { node: n, dest: n.getAttribute( 'data-drop' ) };
	}
	function clearDrop() { [].forEach.call( root.querySelectorAll( '.fm-over' ), function ( n ) { n.classList.remove( 'fm-over' ); } ); elDrop.hidden = true; root.classList.remove( 'is-files' ); }
	function hasFiles( ev ) { return ev.dataTransfer && [].indexOf.call( ev.dataTransfer.types || [], 'Files' ) >= 0; }
	root.addEventListener( 'dragover', function ( ev ) {
		var files = hasFiles( ev ), t = dropTarget( ev.target );
		if ( ! S.drag && ! files ) { return; }
		if ( files && S.tag ) { return; }
		ev.preventDefault();
		ev.dataTransfer.dropEffect = S.drag ? 'move' : 'copy';
		[].forEach.call( root.querySelectorAll( '.fm-over' ), function ( n ) { n.classList.remove( 'fm-over' ); } );
		if ( t && ( S.drag ? ( undefined !== t.dest && S.drag.indexOf( t.dest ) < 0 && ! S.tag ) || ( undefined !== t.tag ) : undefined !== t.dest ) ) { t.node.classList.add( 'fm-over' ); }
		if ( files ) { root.classList.add( 'is-files' ); elDrop.hidden = false; }
	} );
	root.addEventListener( 'dragleave', function ( ev ) { if ( ! root.contains( ev.relatedTarget ) ) { clearDrop(); } } );
	root.addEventListener( 'drop', function ( ev ) {
		var files = hasFiles( ev ), t = dropTarget( ev.target );
		if ( ! S.drag && ! files ) { return; }
		ev.preventDefault();
		var drag = S.drag;
		S.drag = null;
		clearDrop();
		root.classList.remove( 'is-dragging' );
		if ( files ) {
			if ( S.tag ) { return; }
			var dest = t && undefined !== t.dest ? t.dest : S.path;
			upload( ev.dataTransfer.files, dest );
			return;
		}
		if ( ! t ) { return; }
		if ( undefined !== t.tag ) { setTag( drag.map( byPath ).filter( Boolean ), t.tag ); return; }
		if ( undefined !== t.dest && ! S.tag && drag.indexOf( t.dest ) < 0 ) { moveTo( drag, t.dest ); }
		if ( undefined !== t.dest && S.tag && drag.indexOf( t.dest ) < 0 ) { moveTo( drag, t.dest ); }
	} );

	/* ------------------------------------------------------------ quick look */
	var ql = null;
	function previewable() { return shown().filter( function ( e ) { return ! e.dir; } ); }
	function quick( e ) {
		if ( e.dir ) { open( e ); return; }
		if ( ql ) { ql.wrap.remove(); }
		var wrap = el( 'div', 'fm-modal fm-ql' ), box = el( 'div', 'fm-qbox' );
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', e.name );
		var media;
		if ( 'image' === e.kind && e.url ) { media = '<img src="' + esc( e.url ) + '" alt="">'; } else if ( 'pdf' === e.kind && e.url ) { media = '<iframe src="' + esc( e.url ) + '" title="' + esc( e.name ) + '"></iframe>'; } else if ( 'video' === e.kind && e.url ) { media = '<video src="' + esc( e.url ) + '" controls></video>'; } else if ( 'audio' === e.kind && e.url ) { media = '<audio src="' + esc( e.url ) + '" controls></audio>'; } else { media = '<div class="fm-qg">' + glyph( e ) + '</div><p class="fm-qno">' + esc( T.noPreview ) + '</p>'; }
		box.innerHTML = '<div class="fm-qh"><span class="fm-qt"></span><span class="fm-dots">' + dots( e ) + '</span><span class="fm-qa"></span><button type="button" class="fm-btn" data-close aria-label="' + esc( T.close ) + '">' + I.x + '</button></div><div class="fm-qm">' + media + '</div><div class="fm-qi"><span>' + esc( kindName( e ) ) + '</span><span>' + esc( size( e.size ) ) + '</span><span>' + esc( when( e.mtime ) ) + '</span></div>';
		box.querySelector( '.fm-qt' ).textContent = e.name;
		var acts = box.querySelector( '.fm-qa' );
		if ( e.url ) { var a = el( 'a', 'fm-pill' ); a.href = e.url; a.target = '_blank'; a.rel = 'noopener'; a.textContent = T.openTab; acts.appendChild( a ); }
		if ( e.att ) { var m = el( 'a', 'fm-pill' ); m.href = C.media + e.att; m.textContent = T.inMedia; acts.appendChild( m ); }
		wrap.appendChild( box );
		root.appendChild( wrap );
		var last = document.activeElement;
		ql = { wrap: wrap, item: e };
		function close() { wrap.remove(); ql = null; if ( last && last.focus ) { last.focus(); } }
		function step( d ) { var l = previewable(), i = l.map( function ( x ) { return x.path; } ).indexOf( ql.item.path ); if ( l.length > 1 ) { var to = l[ ( i + d + l.length ) % l.length ]; wrap.remove(); ql = null; pick( to.path, null ); quick( to ); } }
		box.querySelector( '[data-close]' ).addEventListener( 'click', close );
		wrap.addEventListener( 'click', function ( ev ) { if ( ev.target === wrap ) { close(); } } );
		wrap.addEventListener( 'keydown', function ( ev ) {
			if ( 'Escape' === ev.key || ' ' === ev.key && ev.target === wrap ) { ev.preventDefault(); ev.stopPropagation(); close(); } else if ( 'ArrowRight' === ev.key ) { step( 1 ); } else if ( 'ArrowLeft' === ev.key ) { step( -1 ); }
		} );
		wrap.setAttribute( 'tabindex', '-1' );
		box.querySelector( '[data-close]' ).focus();
	}

	/* ------------------------------------------------------------ start */
	go( '' );
}() );
