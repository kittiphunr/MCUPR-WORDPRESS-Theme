<?php
/**
 * MCUPR theme bootstrap.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCUPR_VERSION', '1.0.0' );
define( 'MCUPR_DIR', get_template_directory() );
define( 'MCUPR_URI', get_template_directory_uri() );

require_once MCUPR_DIR . '/inc/i18n.php';
require_once MCUPR_DIR . '/inc/translations.php';
require_once MCUPR_DIR . '/inc/customizer.php';
require_once MCUPR_DIR . '/inc/template-tags.php';

function mcupr_setup() {
	load_theme_textdomain( 'mcupr', MCUPR_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
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
	foreach ( mcupr_languages() as $code => $l ) {
		/* translators: %s: language name */
		$locations[ 'primary_' . $code ] = sprintf( __( 'Main menu (%s)', 'mcupr' ), $l['name'] );
	}
	$locations['quick']  = __( 'Quick links', 'mcupr' );
	$locations['footer'] = __( 'Footer links', 'mcupr' );
	register_nav_menus( $locations );
}
add_action( 'after_setup_theme', 'mcupr_setup' );

function mcupr_assets() {
	wp_enqueue_style( 'mcupr', get_stylesheet_uri(), array(), MCUPR_VERSION );
	wp_add_inline_style( 'mcupr', mcupr_custom_css() );
	wp_enqueue_script( 'mcupr', MCUPR_URI . '/assets/js/main.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'mcupr',
		'mcuprI18n',
		array(
			'fontSize' => mcupr_t( 'status_font' ),
			'reset'    => mcupr_t( 'status_reset' ),
			'submenu'  => mcupr_t( 'submenu' ),
		)
	);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'mcupr_assets' );

/**
 * Apply saved display preferences before first paint (prevents a flash of the
 * wrong theme / font size). Preferences live only in the visitor's browser.
 */
function mcupr_head_prefs() {
	?>
<script>(function(){try{var d=document.documentElement,p=JSON.parse(localStorage.getItem('mcupr_prefs')||'{}');['theme','font','contrast','gray','underline','readable','spacing','cursor','motion','images'].forEach(function(k){if(p[k]!==undefined&&p[k]!==''){d.setAttribute('data-'+k,p[k]);}});}catch(e){}})();</script>
	<?php
}
add_action( 'wp_head', 'mcupr_head_prefs', 1 );

/** The WordPress default <body> classes plus current language. */
function mcupr_body_class( $classes ) {
	$classes[] = 'lang-' . mcupr_lang();
	return $classes;
}
add_filter( 'body_class', 'mcupr_body_class' );

/** Longer, more useful excerpt for Thai/CJK text (no spaces between words). */
add_filter( 'excerpt_length', static function () {
	return in_array( mcupr_lang(), array( 'zh', 'ja', 'th' ), true ) ? 60 : 30;
} );
add_filter( 'excerpt_more', static function () {
	return '…';
} );
