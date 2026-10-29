/* The visitor statistics card of the footer: a button shrinks it to one line and opens it again (like a widget of a phone).
   The choice stays in this browser (localStorage), never leaves it. Without this script the card shows the size the owner chose. */
( function () {
	'use strict';
	var card = document.getElementById( 'fstat' );
	var btn = card && card.querySelector( '.fstat-tg' );
	if ( ! card || ! btn ) { return; }
	var KEY = 'mcupr_vc_size', full = card.getAttribute( 'data-full' ) || 'full';
	function set( size ) {
		card.setAttribute( 'data-size', size );
		card.className = card.className.replace( /\bis-(?:full|medium|small)\b/g, '' ).replace( /\s+/g, ' ' ).trim() + ' is-' + size;
		var small = 'small' === size, t = btn.getAttribute( small ? 'data-t-full' : 'data-t-small' );
		btn.setAttribute( 'aria-pressed', small ? 'true' : 'false' );
		btn.setAttribute( 'aria-label', t ); btn.setAttribute( 'title', t );
	}
	var saved = null;
	try { saved = localStorage.getItem( KEY ); } catch ( e ) { /* storage blocked */ }
	if ( 'small' === saved || 'open' === saved ) { set( 'small' === saved ? 'small' : full ); }
	btn.hidden = false;
	btn.addEventListener( 'click', function () {
		var small = 'small' !== card.getAttribute( 'data-size' );
		set( small ? 'small' : full );
		try { localStorage.setItem( KEY, small ? 'small' : 'open' ); } catch ( e ) { /* storage blocked */ }
	} );
}() );
