/* Icon picker: a select that lists icon names (menu items, the blocks of the home page) becomes a button showing the chosen icon;
   it opens a grid of the icons themselves, with a search box. The select stays in the page as the real field (hidden), so saving and
   the Customizer work as before. Keyboard: Enter opens, arrow keys move in the grid, Escape closes and returns to the button.
   Selects to dress: [data-mcupr-icon-picker] (nav-menus.php) and the Customizer controls named in mcuprIcons.controls. */
( function ( $ ) {
	'use strict';
	var D = window.mcuprIcons;
	if ( ! D ) { return; }
	var uid = 0, openPicker = null;

	function svg( name, size ) {
		var p = D.paths[ name ];
		return p ? '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + p + '</svg>' : '<span class="mcupr-ip-none" aria-hidden="true">∅</span>';
	}
	function esc( s ) { return String( s ).replace( /[&<>"]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ]; } ); }

	function dress( sel ) {
		if ( ! sel || sel.getAttribute( 'data-mcupr-ip' ) ) { return; }
		sel.setAttribute( 'data-mcupr-ip', '1' );
		var id = 'mcupr-ip-' + ( ++uid ), wrap = document.createElement( 'span' ), btn = document.createElement( 'button' ), pop = null, items = [];
		wrap.className = 'mcupr-ip';
		btn.type = 'button'; btn.className = 'mcupr-ip-btn button'; btn.setAttribute( 'aria-haspopup', 'dialog' ); btn.setAttribute( 'aria-expanded', 'false' );
		sel.classList.add( 'mcupr-ip-sel' );
		sel.parentNode.insertBefore( wrap, sel.nextSibling );
		wrap.appendChild( btn );
		var label = sel.id ? document.querySelector( 'label[for="' + sel.id + '"]' ) : null;
		var title = label ? label.textContent.replace( /\s+/g, ' ' ).trim() : D.t.choose;

		function none() { var o = sel.options[ 0 ]; return o && '' === o.value ? o.textContent : D.t.none; }
		function paint() {
			var v = sel.value;
			btn.innerHTML = '<span class="mcupr-ip-cur">' + svg( v, 22 ) + '</span><span class="mcupr-ip-name">' + esc( v || none() ) + '</span><span class="mcupr-ip-caret" aria-hidden="true">▾</span>';
			btn.setAttribute( 'aria-label', title + ': ' + ( v || none() ) );
		}
		function close( back ) {
			if ( pop ) { pop.hidden = true; }
			btn.setAttribute( 'aria-expanded', 'false' );
			if ( openPicker === close ) { openPicker = null; }
			if ( back ) { btn.focus(); }
		}
		function choose( v ) {
			sel.value = v;
			$( sel ).trigger( 'change' ); // the Customizer and the menu screen listen to jQuery's change
			sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			paint();
			close( true );
		}
		function build() {
			pop = document.createElement( 'div' );
			pop.className = 'mcupr-ip-pop'; pop.setAttribute( 'role', 'dialog' ); pop.setAttribute( 'aria-label', title ); pop.id = id;
			var q = document.createElement( 'input' ), grid = document.createElement( 'div' ), empty = document.createElement( 'p' );
			q.type = 'search'; q.className = 'mcupr-ip-q'; q.placeholder = D.t.search; q.setAttribute( 'aria-label', D.t.search );
			grid.className = 'mcupr-ip-grid'; empty.className = 'mcupr-ip-empty'; empty.hidden = true; empty.textContent = D.t.nothing;
			[].forEach.call( sel.options, function ( o ) {
				var b = document.createElement( 'button' );
				b.type = 'button'; b.className = 'mcupr-ip-i'; b.setAttribute( 'data-v', o.value ); b.title = o.value || o.textContent;
				b.innerHTML = svg( o.value, 24 ) + '<span>' + esc( o.value || o.textContent ) + '</span>';
				b.addEventListener( 'click', function () { choose( o.value ); } );
				grid.appendChild( b ); items.push( b );
			} );
			function mark() { items.forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.getAttribute( 'data-v' ) === sel.value ? 'true' : 'false' ); } ); }
			q.addEventListener( 'input', function () {
				var t = q.value.trim().toLowerCase(), n = 0;
				items.forEach( function ( b ) { var show = '' === t || b.getAttribute( 'data-v' ).toLowerCase().indexOf( t ) > -1 || ( '' === b.getAttribute( 'data-v' ) && ! t ); b.hidden = ! show; if ( show ) { n++; } } );
				empty.hidden = n > 0;
			} );
			pop.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) { e.preventDefault(); e.stopPropagation(); close( true ); return; }
				var vis = items.filter( function ( b ) { return ! b.hidden; } ), i = vis.indexOf( document.activeElement );
				if ( i < 0 ) { if ( 'ArrowDown' === e.key && vis[ 0 ] ) { e.preventDefault(); vis[ 0 ].focus(); } return; }
				var cols = Math.max( 1, Math.round( grid.clientWidth / ( vis[ 0 ].offsetWidth + 6 ) ) ), to = null;
				if ( 'ArrowRight' === e.key ) { to = i + 1; } else if ( 'ArrowLeft' === e.key ) { to = i - 1; } else if ( 'ArrowDown' === e.key ) { to = i + cols; } else if ( 'ArrowUp' === e.key ) { to = i - cols; if ( to < 0 ) { e.preventDefault(); q.focus(); return; } }
				if ( null !== to && vis[ to ] ) { e.preventDefault(); vis[ to ].focus(); }
			} );
			pop.appendChild( q ); pop.appendChild( grid ); pop.appendChild( empty );
			wrap.appendChild( pop );
			pop.mark = mark;
		}
		btn.addEventListener( 'click', function () {
			if ( pop && ! pop.hidden ) { close( true ); return; }
			if ( openPicker ) { openPicker( false ); }
			if ( ! pop ) { build(); }
			pop.hidden = false; pop.mark();
			btn.setAttribute( 'aria-expanded', 'true' ); btn.setAttribute( 'aria-controls', id );
			openPicker = close;
			var cur = pop.querySelector( '[aria-pressed="true"]' ); ( cur || pop.querySelector( '.mcupr-ip-q' ) ).focus();
			if ( cur && cur.scrollIntoView ) { cur.scrollIntoView( { block: 'nearest' } ); }
		} );
		$( sel ).on( 'change', paint );
		paint();
	}

	document.addEventListener( 'click', function ( e ) { if ( openPicker && ! e.target.closest( '.mcupr-ip' ) ) { openPicker( false ); } } );
	function scan( root ) { [].forEach.call( ( root || document ).querySelectorAll( 'select[data-mcupr-icon-picker]' ), dress ); }
	scan();
	if ( window.MutationObserver ) { new MutationObserver( function ( list ) { list.forEach( function ( m ) { [].forEach.call( m.addedNodes, function ( n ) { if ( 1 === n.nodeType ) { scan( n ); } } ); } ); } ).observe( document.body, { childList: true, subtree: true } ); }

	// the Customizer: the controls named by the theme
	if ( window.wp && wp.customize ) {
		wp.customize.bind( 'ready', function () {
			( D.controls || [] ).forEach( function ( id ) {
				wp.customize.control( id, function ( c ) { c.deferred.embedded.done( function () { var s = c.container.find( 'select' )[ 0 ]; if ( s ) { s.setAttribute( 'data-mcupr-icon-picker', '1' ); dress( s ); } } ); } );
			} );
		} );
	}
}( window.jQuery ) );
