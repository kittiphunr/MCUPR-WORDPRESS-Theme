<?php
/**
 * Theme setup: supports, image sizes, menus, body classes, excerpts.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_setup() {
	load_theme_textdomain( 'mcupr', MCUPR_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 120,
			'width'       => 120,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_image_size( 'mcupr-card', 640, 400, true );
	add_image_size( 'mcupr-hero', 1200, 675, true );

	// One main menu per language (falls back to the default language's menu).
	$locations = array();
	foreach ( mcupr_ui_languages() as $code => $l ) {
		/* translators: %s: language name */
		$locations[ 'primary_' . $code ] = sprintf( __( 'Main menu (%s)', 'mcupr' ), $l['name'] );
	}
	$locations['utility'] = __( 'Utility bar links (students, staff, alumni…)', 'mcupr' );
	$locations['quick']  = __( 'Quick links', 'mcupr' );
	$locations['footer'] = __( 'Footer links', 'mcupr' );
	register_nav_menus( $locations );
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'after_setup_theme', 'mcupr_setup' );

/** The WordPress default <body> classes plus current language. */
function mcupr_body_class( $classes ) {
	$classes[] = 'lang-' . mcupr_lang();
	return $classes;
}
add_filter( 'body_class', 'mcupr_body_class' );

/** Longer, more useful excerpt for Thai/CJK text (no spaces between words). */
add_filter( 'excerpt_length', static function () {
	return in_array( mcupr_ui_lang(), array( 'zh', 'ja', 'th' ), true ) ? 60 : 30;
} );
add_filter( 'excerpt_more', static function () {
	return '…';
} );

/** "Category: News" -> "News" (the prefix is English-only; the band title says enough). */
add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

/** Browser-tab icon: the emblem, until a Site Icon is set in the Customizer. */
add_action(
	'wp_head',
	static function () {
		if ( ! has_site_icon() ) {
			echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( MCUPR_URI . '/assets/img/mcu-logo.svg' ) . '">' . "\n";
		}
	},
	2
);
