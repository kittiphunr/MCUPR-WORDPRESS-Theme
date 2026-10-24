<?php
/**
 * Animated icons (Lordicon, https://lordicon.com): the site owner names an icon of the theme (news, pin, phone, …) and the
 * code of a Lordicon animation for it; the page keeps its normal line icon and swaps it for the moving one when the player
 * has loaded (so nothing jumps, and a failed download leaves the normal icon). The player (@lordicon/element, MIT) is in
 * assets/vendor/lordicon/ and is fetched only on a page that has such an icon and only when animations are on.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The kinds of play the owner may choose. */
function mcupr_lord_triggers() {
	return array(
		'hover'         => __( 'When the pointer or the keyboard reaches it (recommended)', 'mcupr' ),
		'in'            => __( 'Once, when it scrolls into view', 'mcupr' ),
		'loop-on-hover' => __( 'Again and again while the pointer is on it', 'mcupr' ),
		'loop'          => __( 'Again and again (use for one or two icons only)', 'mcupr' ),
		'click'         => __( 'When it is clicked', 'mcupr' ),
	);
}

/** One value of the list to a URL of an icon file, or '' when it is not one: a code of lordicon.com, a https address or a path of this site, ending .json. */
function mcupr_lord_src( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^[a-z0-9]{6,16}$/i', $v ) ) {
		return 'https://cdn.lordicon.com/' . strtolower( $v ) . '.json';
	}
	if ( preg_match( '#^(https://|/(?!/))[^\s"\'<>]+\.json$#i', $v ) ) {
		return esc_url_raw( $v );
	}
	return '';
}

/** Parse the list "icon name = code" (one per line; # starts a comment) to name => URL, only for names of the theme's own icons. */
function mcupr_lord_parse( $text ) {
	$names = array_keys( mcupr_icon_paths() );
	$out   = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( preg_replace( '/\s*#.*$/', '', $line ) );
		if ( ! preg_match( '/^([a-z0-9-]+)\s*[=:]\s*(\S+)$/i', $line, $m ) ) {
			continue;
		}
		$name = strtolower( $m[1] );
		$src  = mcupr_lord_src( $m[2] );
		if ( '' !== $src && in_array( $name, $names, true ) ) {
			$out[ $name ] = $src;
		}
	}
	return $out;
}

/** The icons that are animated now: name => URL (empty when the feature is off). */
function mcupr_lord_map() {
	static $map = null;
	if ( null === $map ) {
		$map = get_theme_mod( 'mcupr_lord_on', true ) ? mcupr_lord_parse( (string) get_theme_mod( 'mcupr_lord_map', '' ) ) : array();
	}
	return $map;
}

function mcupr_lord_trigger() {
	$t = (string) get_theme_mod( 'mcupr_lord_trigger', 'hover' );
	return isset( mcupr_lord_triggers()[ $t ] ) ? $t : 'hover';
}

/** The normal SVG icon (from mcupr_icon) in a box that the script upgrades to the moving icon. */
function mcupr_lord_wrap( $name, $svg, $size ) {
	$map = is_admin() ? array() : mcupr_lord_map();
	if ( ! isset( $map[ $name ] ) ) {
		return $svg;
	}
	return '<span class="lord" data-lord="' . esc_url( $map[ $name ] ) . '" data-lord-trigger="' . esc_attr( mcupr_lord_trigger() ) . '" style="--lord:' . (int) $size . 'px">' . $svg . '</span>';
}

/** The credit that Lordicon asks for the free icons, in the About dialog next to the other resources. */
function mcupr_lord_credit_row( $rows ) {
	if ( mcupr_lord_map() ) {
		$rows[] = array( 'Lordicon', 'https://lordicon.com/', 'player MIT · icons: Lordicon licence' );
	}
	return $rows;
}
add_filter( 'mcupr_about_resources', 'mcupr_lord_credit_row' );

function mcupr_lord_customize( $wp_customize ) {
	$sec = 'mcupr_lordicon';
	$wp_customize->add_section(
		$sec,
		array(
			'title'       => __( 'MCUPR: Animated icons (Lordicon)', 'mcupr' ),
			'priority'    => 34,
			'description' => __( 'Make an icon of the site move. Pick an animation on lordicon.com, copy its code (for example wxnxiano) and write it next to the name of the icon below. Icons you do not list stay as they are. Free Lordicon icons need a credit: it appears in the About dialog by itself.', 'mcupr' ),
		)
	);
	$wp_customize->add_setting( 'mcupr_lord_on', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_lord_on', array( 'section' => $sec, 'label' => __( 'Use animated icons', 'mcupr' ), 'description' => __( 'Visitors who asked their device for less motion, or switched animations off, always see the normal icons.', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_lord_trigger', array( 'default' => 'hover', 'sanitize_callback' => static function ( $v ) { return isset( mcupr_lord_triggers()[ $v ] ) ? $v : 'hover'; } ) );
	$wp_customize->add_control( 'mcupr_lord_trigger', array( 'section' => $sec, 'label' => __( 'When the icons move', 'mcupr' ), 'type' => 'select', 'choices' => mcupr_lord_triggers() ) );
	$wp_customize->add_setting( 'mcupr_lord_map', array( 'default' => '', 'sanitize_callback' => static function ( $v ) { return implode( "\n", array_map( static function ( $n, $s ) { return $n . ' = ' . preg_replace( '#^https://cdn\.lordicon\.com/([a-z0-9]+)\.json$#', '$1', $s ); }, array_keys( mcupr_lord_parse( $v ) ), mcupr_lord_parse( $v ) ) ); } ) );
	$wp_customize->add_control(
		'mcupr_lord_map',
		array(
			'section'     => $sec,
			'label'       => __( 'Icon = Lordicon code (one per line)', 'mcupr' ),
			/* translators: %s: the names of the icons, comma separated */
			'description' => sprintf( __( 'Example: news = wxnxiano. You may also write the address of an icon file (https://… or /wp-content/…, ending .json). Names you can use: %s', 'mcupr' ), implode( ', ', array_keys( mcupr_icon_paths() ) ) ),
			'type'        => 'textarea',
		)
	);
}
add_action( 'customize_register', 'mcupr_lord_customize', 20 );
