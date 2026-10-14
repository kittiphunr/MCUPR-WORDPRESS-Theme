<?php
/**
 * Test stub emulating Elementor Pro Theme Builder's elementor_theme_do_location().
 * Active only for requests carrying the cookie  el_stub=header,footer,single,archive
 */
function elementor_theme_do_location( $loc ) {
	$on = isset( $_COOKIE['el_stub'] ) ? explode( ',', $_COOKIE['el_stub'] ) : array();
	if ( ! in_array( $loc, $on, true ) ) {
		return false;
	}
	echo '<div id="el-' . esc_attr( $loc ) . '" class="elementor-location-' . esc_attr( $loc ) . '">Elementor ' . esc_html( $loc ) . '</div>';
	return true;
}
