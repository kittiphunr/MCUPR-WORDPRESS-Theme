<?php
/**
 * Test stub emulating the public Polylang API. Active only for requests
 * carrying the cookie  pll_stub=<current language slug>  (th, en or fr).
 */
if ( isset( $_COOKIE['pll_stub'] ) ) {
	function pll_current_language( $f = 'slug' ) { return $_COOKIE['pll_stub']; }
	function pll_default_language( $f = 'slug' ) { return 'th'; }
	function pll_home_url( $l = '' ) { return home_url( '/' . $_COOKIE['pll_stub'] . '/' ); }
	function pll_get_post_language( $id, $f = 'slug' ) { return 'th'; }
	function pll_the_languages( $args = array() ) {
		$out = array();
		foreach ( array( 'th' => 'th_TH', 'en' => 'en_US', 'fr' => 'fr_FR' ) as $s => $loc ) {
			$out[ $s ] = array( 'slug' => $s, 'locale' => $loc, 'name' => strtoupper( $s ) . '-name', 'url' => home_url( "/$s/" ), 'current_lang' => $s === $_COOKIE['pll_stub'] );
		}
		return $out;
	}
}
