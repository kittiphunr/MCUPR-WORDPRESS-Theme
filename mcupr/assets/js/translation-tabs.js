/* Language tabs of the translation box: one language at a time instead of one long page. */
( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var bar = document.querySelector( '.mcupr-trtabs' );
		if ( ! bar ) { return; }
		var tabs = [].slice.call( bar.querySelectorAll( '[data-tr-tab]' ) );
		var panels = [].slice.call( document.querySelectorAll( '[data-tr-panel]' ) );
		var key = 'mcuprTrTab';
		function show( c ) {
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-tr-tab' ) === c;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
			} );
			panels.forEach( function ( p ) { p.hidden = p.getAttribute( 'data-tr-panel' ) !== c; } );
			try { localStorage.setItem( key, c ); } catch ( e ) {}
			window.dispatchEvent( new Event( 'resize' ) );
		}
		var first = tabs[ 0 ].getAttribute( 'data-tr-tab' );
		var other = tabs.length > 1 ? tabs[ 1 ].getAttribute( 'data-tr-tab' ) : first;
		var want = other;
		try { want = localStorage.getItem( key ) || other; } catch ( e ) {}
		if ( ! tabs.some( function ( t ) { return t.getAttribute( 'data-tr-tab' ) === want; } ) ) { want = other; }
		bar.hidden = false;
		bar.addEventListener( 'click', function ( e ) {
			var t = e.target.closest( '[data-tr-tab]' );
			if ( t ) { show( t.getAttribute( 'data-tr-tab' ) ); }
		} );
		bar.addEventListener( 'keydown', function ( e ) {
			var i = tabs.indexOf( document.activeElement ), d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
			if ( d && i > -1 ) {
				e.preventDefault();
				var n = tabs[ ( i + d + tabs.length ) % tabs.length ];
				n.focus();
				show( n.getAttribute( 'data-tr-tab' ) );
			}
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest && e.target.closest( '[data-tr-top]' ) ) {
				var t = document.getElementById( 'title' ) || document.querySelector( '.editor-post-title__input' );
				window.scrollTo( 0, 0 );
				if ( t ) { t.focus(); }
			}
		} );
		show( want );
	} );
}() );
