<?php
/**
 * Feather icons (https://feathericons.com – MIT, see feather-LICENSE.txt) for the admin of the theme: the same line drawing on every
 * computer (no emoji, which each system draws in its own way). mcupr_feather( 'edit-3', 20 ) gives an inline <svg> in the colour of the text.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** name => inner SVG markup of the whole set (loaded once, only when an icon is wanted). */
function mcupr_feather_set() {
	static $set = null;
	if ( null === $set ) {
		$set = include MCUPR_DIR . '/inc/feather-icons.php';
	}
	return $set;
}

/** An icon that sits in a line of text (a status mark, a label): same icon, aligned to the letters. */
function mcupr_feather_i( $name, $size = 14, $color = '' ) {
	return str_replace( '<svg class="fi', '<svg style="display:inline-block;vertical-align:-.15em' . ( '' !== $color ? ';color:' . esc_attr( $color ) : '' ) . '" class="fi', mcupr_feather( $name, $size ) );
}

/** An icon as inline SVG (decorative: hidden from screen readers; the text next to it names the thing). Unknown names give a plain circle. */
function mcupr_feather( $name, $size = 24, $class = '' ) {
	$set   = mcupr_feather_set();
	$inner = isset( $set[ $name ] ) ? $set[ $name ] : $set['circle'];
	return '<svg class="fi' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
}
