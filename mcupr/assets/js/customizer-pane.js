/* The first screen of the Customizer: sections in labelled groups, each with an icon tile and one line of help, without the
   repeated "MCUPR:". Only the look changes: the sections, their settings and what they do are WordPress' own. */
( function ( api, $ ) {
	'use strict';
	var D = window.mcuprCzPane;
	if ( ! D || ! api ) { return; }
	var P = {
		flag: '<path d="M4 22V4M4 4h12l-2 4 2 4H4"/>',
		image: '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
		building: '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
		layout: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 21V9"/>',
		grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		bell: '<path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>',
		home: '<path d="M3 11l9-8 9 8M5 10v10h14V10M10 20v-6h4v6"/>',
		palette: '<path d="M12 3a9 9 0 100 18c1.4 0 2-1 2-2 0-1.5-1-1.7-1-3 0-1 .8-2 2-2h2.5A3.5 3.5 0 0021 10.5C21 6.4 17 3 12 3z"/><path d="M7.5 11h.01M10 7.5h.01M14.5 7.5h.01"/>',
		sliders: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
		printer: '<path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
		shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
		menu: '<path d="M4 6h16M4 12h16M4 18h10"/>',
		box: '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8zM3.3 7.5L12 12.5l8.7-5M12 22V12.5"/>',
		code: '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
		brush: '<path d="M9.1 11.9L20 1l3 3-10.9 10.9M9.1 11.9c-2.6 0-4.1 1.7-4.1 4 0 2-1.2 3-3 3.1 1.4 1.5 3.3 2 5 2 2.8 0 5-2 5-5 0-1.6-.7-3.2-2.9-4.1z"/>',
		dots: '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>'
	};
	function svg( name ) {
		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ( P[ name ] || P.dots ) + '</svg>';
	}
	/** Take "MCUPR: " off the first text of an element (what is inside it, such as a screen-reader hint, stays). */
	function strip( $el ) {
		$el.contents().each( function () {
			if ( 3 === this.nodeType && /MCUPR:\s*/.test( this.nodeValue ) ) {
				this.nodeValue = this.nodeValue.replace( /MCUPR:\s*/, '' );
				return false;
			}
		} );
	}
	function node( id ) {
		var $n = $( '#accordion-section-' + id + ', #accordion-panel-' + id ).first();
		return $n.length ? $n : null;
	}
	function dress( $li, item, headText ) {
		var $t = $li.children( '.accordion-section-title, .accordion-panel-title' ).first();
		if ( ! $t.length || $li.data( 'mcuprCz' ) ) { return; }
		$li.data( 'mcuprCz', 1 );
		var $host = $t.children( 'button' ).first(); // newer WordPress puts the title inside a button
		if ( ! $host.length ) { $host = $t; }
		strip( $host );
		strip( $( '#sub-accordion-section-' + item.id + ' .customize-section-title h3, #sub-accordion-panel-' + item.id + ' .customize-panel-title, #sub-accordion-panel-' + item.id + ' .panel-title' ) );
		var label = $.trim( $host.contents().filter( function () { return 3 === this.nodeType; } ).text() );
		$host.contents().filter( function () { return 3 === this.nodeType; } ).remove();
		$host.addClass( 'mcupr-cz-row' ).prepend( '<span class="mcupr-cz-ic">' + svg( item.icon ) + '</span><span class="mcupr-cz-tx"><span class="mcupr-cz-t"></span>' + ( item.sub ? '<span class="mcupr-cz-sub"></span>' : '' ) + '</span>' );
		$host.find( '.mcupr-cz-t' ).text( label );
		if ( item.sub ) { $host.find( '.mcupr-cz-sub' ).text( item.sub ); }
		if ( headText ) { $li.addClass( 'mcupr-cz-first' ).prepend( $( '<span class="mcupr-cz-group" role="presentation"></span>' ).text( headText ) ); } // the heading rides inside the first item of its group, so it stays with it when WordPress sorts the list
	}
	/** WordPress keeps the list in the order of the priorities: give the groups consecutive ones. */
	function place( id, priority ) {
		var c = api.section( id ) || api.panel( id );
		if ( c && c.priority ) { c.priority( priority ); }
	}
	api.bind( 'ready', function () {
		var n = 0, used = {};
		$.each( D.groups, function ( gi, g ) {
			var first = true;
			$.each( g.items, function ( _, it ) {
				var $li = node( it.id );
				if ( ! $li ) { return; }
				used[ $li.attr( 'id' ) ] = 1;
				place( it.id, 10 + ( ++n ) );
				dress( $li, it, first ? g.title : '' );
				first = false;
			} );
		} );
		var firstRest = true;
		api.section.each( function ( sec ) { if ( ! sec.panel() ) { restItem( sec, 'section' ); } } );
		api.panel.each( function ( pan ) { restItem( pan, 'panel' ); } );
		function restItem( c, kind ) {
			var $li = node( c.id );
			if ( ! $li || used[ $li.attr( 'id' ) ] || 'themes' === c.id || /^publish_settings$|^widgets-/.test( c.id ) ) { return; }
			used[ $li.attr( 'id' ) ] = 1;
			place( c.id, 500 + ( ++n ) );
			dress( $li, { id: c.id, icon: 'dots', sub: '' }, firstRest ? D.rest : '' );
			firstRest = false;
		}
	} );
}( window.wp && window.wp.customize, window.jQuery ) );
