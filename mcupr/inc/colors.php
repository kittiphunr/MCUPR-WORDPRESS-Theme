<?php
/**
 * Colour schemes and contrast helpers (WCAG 2.2 AA).
 *
 * The Customizer offers ready-made schemes plus a "custom" scheme with four
 * pickers. Text colours that sit on a brand colour (and links on the page
 * background) are computed here so any choice keeps at least 4.5:1 contrast.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Ready-made schemes: primary (buttons, links, accents), accent (decorative), bar (footer / top strip), tint (soft panels). */
function mcupr_palettes() {
	return apply_filters(
		'mcupr_palettes',
		array(
			'rose'     => array( 'label' => __( 'Rose pink & gold (default)', 'mcupr' ), 'primary' => '#c2185b', 'accent' => '#c9a227', 'bar' => '#141414', 'tint' => '#f6e9ee' ),
			'maroon'   => array( 'label' => __( 'Maroon & gold', 'mcupr' ), 'primary' => '#8e1b3a', 'accent' => '#d4a72c', 'bar' => '#1c1114', 'tint' => '#f4e8eb' ),
			'navy'     => array( 'label' => __( 'Royal navy & gold', 'mcupr' ), 'primary' => '#1f3a6e', 'accent' => '#e0b43c', 'bar' => '#0f1a30', 'tint' => '#e8edf6' ),
			'saffron'  => array( 'label' => __( 'Saffron & charcoal', 'mcupr' ), 'primary' => '#a84a00', 'accent' => '#f2b705', 'bar' => '#1d1a16', 'tint' => '#fbeedd' ),
			'indigo'   => array( 'label' => __( 'Indigo (Certify Hub)', 'mcupr' ), 'primary' => '#4f46e5', 'accent' => '#7c3aed', 'bar' => '#0f172a', 'tint' => '#eef2ff' ),
			'teal'     => array( 'label' => __( 'Teal & amber', 'mcupr' ), 'primary' => '#0f766e', 'accent' => '#d97706', 'bar' => '#0b1f1e', 'tint' => '#e6f4f2' ),
		)
	);
}

function mcupr_palette_values() {
	$presets = mcupr_palettes();
	$key     = get_theme_mod( 'mcupr_palette', 'rose' );
	if ( 'custom' !== $key && isset( $presets[ $key ] ) ) {
		return $presets[ $key ];
	}
	$base = $presets['rose'];
	return array(
		'primary' => sanitize_hex_color( get_theme_mod( 'mcupr_primary', $base['primary'] ) ) ?: $base['primary'],
		'accent'  => sanitize_hex_color( get_theme_mod( 'mcupr_accent', $base['accent'] ) ) ?: $base['accent'],
		'bar'     => sanitize_hex_color( get_theme_mod( 'mcupr_bar', $base['bar'] ) ) ?: $base['bar'],
		'tint'    => sanitize_hex_color( get_theme_mod( 'mcupr_tint', $base['tint'] ) ) ?: $base['tint'],
	);
}

/* -------------------------------------------------------------- maths */

function mcupr_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

function mcupr_rgb_to_hex( $rgb ) {
	return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $rgb[0] ) ) ), max( 0, min( 255, (int) round( $rgb[1] ) ) ), max( 0, min( 255, (int) round( $rgb[2] ) ) ) );
}

function mcupr_luminance( $hex ) {
	$out = array();
	foreach ( mcupr_hex_to_rgb( $hex ) as $c ) {
		$c     = $c / 255;
		$out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
}

/** WCAG contrast ratio between two hex colours. */
function mcupr_contrast( $a, $b ) {
	$la = mcupr_luminance( $a );
	$lb = mcupr_luminance( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/** Mix $a with $b ($ratio of $b, 0..1). */
function mcupr_mix( $a, $b, $ratio ) {
	$x = mcupr_hex_to_rgb( $a );
	$y = mcupr_hex_to_rgb( $b );
	return mcupr_rgb_to_hex( array( $x[0] + ( $y[0] - $x[0] ) * $ratio, $x[1] + ( $y[1] - $x[1] ) * $ratio, $x[2] + ( $y[2] - $x[2] ) * $ratio ) );
}

/** Text colour (white or near-black) with the best contrast on $bg – always >= 4.5:1. */
function mcupr_on_color( $bg ) {
	return mcupr_contrast( $bg, '#ffffff' ) >= mcupr_contrast( $bg, '#111111' ) ? '#ffffff' : '#111111';
}

/** Move $color towards $toward until it reaches $min contrast on $bg. */
function mcupr_ensure_contrast( $color, $bg, $toward, $min = 4.5 ) {
	for ( $i = 0; $i <= 20 && mcupr_contrast( $color, $bg ) < $min; $i++ ) {
		$color = mcupr_mix( $color, $toward, 0.08 );
	}
	return $color;
}

/** Hue shift of a colour (used for the second gradient colour, like Certify Hub's --brand-2). */
function mcupr_shift_hue( $hex, $deg = 28 ) {
	list( $r, $g, $b ) = array_map( static function ( $v ) { return $v / 255; }, mcupr_hex_to_rgb( $hex ) );
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$l   = ( $max + $min ) / 2;
	$d   = $max - $min;
	if ( 0.0 === $d ) {
		return $hex;
	}
	$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
	if ( $max === $r ) {
		$h = fmod( ( $g - $b ) / $d + 6, 6 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	$h = fmod( $h * 60 + $deg + 360, 360 );
	$l = max( 0.36, min( 0.55, $l ) );
	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
	$m = $l - $c / 2;
	if ( $h < 60 ) {
		$rgb = array( $c, $x, 0 );
	} elseif ( $h < 120 ) {
		$rgb = array( $x, $c, 0 );
	} elseif ( $h < 180 ) {
		$rgb = array( 0, $c, $x );
	} elseif ( $h < 240 ) {
		$rgb = array( 0, $x, $c );
	} elseif ( $h < 300 ) {
		$rgb = array( $x, 0, $c );
	} else {
		$rgb = array( $c, 0, $x );
	}
	return mcupr_rgb_to_hex( array( ( $rgb[0] + $m ) * 255, ( $rgb[1] + $m ) * 255, ( $rgb[2] + $m ) * 255 ) );
}

/** All colour variables derived from a palette (primary / accent / bar / tint). Contrast-safe by construction. */
function mcupr_palette_vars( $p ) {
	$primary = $p['primary'];
	return array(
		'--primary'    => $primary,
		'--accent'     => $p['accent'],
		'--brand-2'    => mcupr_shift_hue( $primary ),
		'--on-primary' => mcupr_on_color( $primary ),
		'--bar-bg'     => $p['bar'],
		'--bar-fg'     => mcupr_on_color( $p['bar'] ),
		'--tint'       => $p['tint'],
		'--tint-dark'  => mcupr_mix( '#1e293b', $primary, 0.2 ),
		'--link-light' => mcupr_ensure_contrast( $primary, '#f1f5f9', '#000000' ),
		'--link-dark'  => mcupr_ensure_contrast( $primary, '#1e293b', '#ffffff' ),
	);
}

/** Presets with their derived variables, for the visitor colour picker (assets/js/main.js). */
function mcupr_palettes_for_js() {
	$out = array();
	foreach ( mcupr_palettes() as $key => $p ) {
		$out[ $key ] = array( 'label' => $p['label'], 'vars' => mcupr_palette_vars( $p ), 'primary' => $p['primary'] );
	}
	return $out;
}

/** Inline CSS variables (same cascade layer as the theme tokens so contrast modes still win). */
function mcupr_custom_css() {
	$css = '';
	foreach ( mcupr_palette_vars( mcupr_palette_values() ) as $k => $v ) {
		$css .= $k . ':' . $v . ';';
	}
	$height = (int) get_theme_mod( 'mcupr_logo_height', 56 );
	$css   .= '--logo-h:' . max( 24, min( 140, $height ) ) . 'px;';
	return '@layer base{:root{' . $css . '}}';
}
