/* Customizer: the board that arranges the parts of the footer (inc/footer-layout.php).
   A part is dragged into a column, or moved with its column list and the arrows (keyboard, touch, screen readers). The setting holds
   {"1":[…],"2":[…],"3":[…],"0":[hidden]}; the preview shows the footer again after every change. */
( function ( wp, $ ) {
	'use strict';
	var D = window.mcuprFl;
	if ( ! wp || ! wp.customize || ! D ) { return; }
	var ZONES = [ 1, 2, 3, 0 ];

	function parse( raw ) {
		var o = { 0: [], 1: [], 2: [], 3: [] }, j = null, seen = {};
		try { j = JSON.parse( raw ); } catch ( e ) { j = null; }
		ZONES.forEach( function ( k ) {
			( j && j[ k ] || [] ).forEach( function ( id ) { if ( D.parts[ id ] && ! seen[ id ] ) { o[ k ].push( id ); seen[ id ] = 1; } } );
		} );
		Object.keys( D.parts ).forEach( function ( id ) { if ( ! seen[ id ] ) { o[ 'contact' === id || 'w1' === id ? 1 : ( 'stats' === id || 'w3' === id ? 3 : 2 ) ].push( id ); } } );
		return o;
	}

	function build( control ) {
		var box = control.container[ 0 ].querySelector( '[data-mcupr-fl]' ), hid = control.container[ 0 ].querySelector( '.mcupr-fl-value' ), S, live, drag = null;
		if ( ! box ) { return; }
		S = parse( hid.value || control.setting.get() );
		live = document.createElement( 'p' );
		live.className = 'screen-reader-text'; live.setAttribute( 'role', 'status' ); live.setAttribute( 'aria-live', 'polite' );
		box.parentNode.appendChild( live );

		function zoneOf( id ) { for ( var i = 0; i < ZONES.length; i++ ) { if ( S[ ZONES[ i ] ].indexOf( id ) > -1 ) { return ZONES[ i ]; } } return 2; }
		function move( id, zone, index ) {
			var from = zoneOf( id );
			S[ from ].splice( S[ from ].indexOf( id ), 1 );
			if ( null == index || index > S[ zone ].length ) { index = S[ zone ].length; }
			S[ zone ].splice( index, 0, id );
			save();
			draw();
			var again = box.querySelector( '[data-id="' + id + '"] select' );
			if ( again && ! drag ) { again.focus(); }
			live.textContent = D.t.moved.replace( '%1$s', D.parts[ id ] ).replace( '%2$s', D.zones[ zone ] ).replace( '%3$d', S[ zone ].indexOf( id ) + 1 );
		}
		function save() {
			var out = {};
			ZONES.forEach( function ( k ) { out[ k ] = S[ k ]; } );
			var v = JSON.stringify( out );
			hid.value = v;
			control.setting.set( v );
		}
		function chip( id, zone, i, n ) {
			var li = document.createElement( 'li' ), h = document.createElement( 'span' ), nm = document.createElement( 'span' ), sel = document.createElement( 'select' ), up = document.createElement( 'button' ), dn = document.createElement( 'button' );
			li.className = 'mcupr-fl-chip'; li.setAttribute( 'data-id', id ); li.draggable = true;
			h.className = 'mcupr-fl-h'; h.setAttribute( 'aria-hidden', 'true' ); h.title = D.t.drag; h.textContent = '⠿';
			nm.className = 'mcupr-fl-n'; nm.textContent = D.parts[ id ];
			sel.setAttribute( 'aria-label', D.t.zone.replace( '%s', D.parts[ id ] ) );
			ZONES.forEach( function ( k ) { var o = document.createElement( 'option' ); o.value = k; o.textContent = D.zones[ k ]; if ( k === zone ) { o.selected = true; } sel.appendChild( o ); } );
			sel.addEventListener( 'change', function () { move( id, parseInt( sel.value, 10 ) ); } );
			[ [ up, '↑', D.t.up, -1 ], [ dn, '↓', D.t.down, 1 ] ].forEach( function ( b ) {
				b[ 0 ].type = 'button'; b[ 0 ].className = 'mcupr-fl-b'; b[ 0 ].textContent = b[ 1 ]; b[ 0 ].setAttribute( 'aria-label', b[ 2 ] + ': ' + D.parts[ id ] ); b[ 0 ].title = b[ 2 ];
				b[ 0 ].disabled = ( b[ 3 ] < 0 && i === 0 ) || ( b[ 3 ] > 0 && i === n - 1 );
				b[ 0 ].addEventListener( 'click', function () { move( id, zone, i + b[ 3 ] ); } );
			} );
			li.addEventListener( 'dragstart', function ( e ) { drag = id; li.classList.add( 'is-drag' ); try { e.dataTransfer.setData( 'text/plain', id ); e.dataTransfer.effectAllowed = 'move'; } catch ( x ) { /* old browser */ } } );
			li.addEventListener( 'dragend', function () { drag = null; li.classList.remove( 'is-drag' ); box.querySelectorAll( '.is-over' ).forEach( function ( z ) { z.classList.remove( 'is-over' ); } ); } );
			[ h, nm, sel, up, dn ].forEach( function ( e ) { li.appendChild( e ); } );
			return li;
		}
		function draw() {
			box.innerHTML = '';
			ZONES.forEach( function ( k ) {
				var z = document.createElement( 'div' ), t = document.createElement( 'h4' ), ul = document.createElement( 'ul' );
				z.className = 'mcupr-fl-zone' + ( 0 === k ? ' is-hidden' : '' ); z.setAttribute( 'data-zone', k );
				t.textContent = D.zones[ k ]; ul.setAttribute( 'aria-label', D.zones[ k ] );
				if ( ! S[ k ].length ) { var e = document.createElement( 'li' ); e.className = 'mcupr-fl-empty'; e.textContent = D.t.empty; ul.appendChild( e ); }
				S[ k ].forEach( function ( id, i ) { ul.appendChild( chip( id, k, i, S[ k ].length ) ); } );
				z.addEventListener( 'dragover', function ( e ) { if ( ! drag ) { return; } e.preventDefault(); z.classList.add( 'is-over' ); } );
				z.addEventListener( 'dragleave', function ( e ) { if ( ! z.contains( e.relatedTarget ) ) { z.classList.remove( 'is-over' ); } } );
				z.addEventListener( 'drop', function ( e ) {
					if ( ! drag ) { return; }
					e.preventDefault();
					var idx = S[ k ].length, kids = ul.querySelectorAll( '.mcupr-fl-chip' );
					for ( var i = 0; i < kids.length; i++ ) { var r = kids[ i ].getBoundingClientRect(); if ( e.clientY < r.top + r.height / 2 ) { idx = i; break; } }
					var from = zoneOf( drag ), cur = S[ from ].indexOf( drag );
					if ( from === k && cur < idx ) { idx--; }
					var id = drag; drag = null;
					move( id, k, idx );
				} );
				z.appendChild( t ); z.appendChild( ul ); box.appendChild( z );
			} );
		}
		draw();
	}

	wp.customize.control( 'mcupr_footer_layout', function ( control ) { control.deferred.embedded.done( function () { build( control ); } ); } );
}( window.wp, window.jQuery ) );
