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
require_once MCUPR_DIR . '/inc/colors.php';
require_once MCUPR_DIR . '/inc/customizer.php';
require_once MCUPR_DIR . '/inc/layout-meta.php';
require_once MCUPR_DIR . '/inc/dates.php';
require_once MCUPR_DIR . '/inc/template-tags.php';
require_once MCUPR_DIR . '/inc/elementor.php';
require_once MCUPR_DIR . '/inc/plugins.php';

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
	if ( mcupr_has_polylang() ) {
		$locations['primary'] = __( 'Main menu (Polylang adds one per language)', 'mcupr' );
	} else {
		foreach ( mcupr_ui_languages() as $code => $l ) {
			/* translators: %s: language name */
			$locations[ 'primary_' . $code ] = sprintf( __( 'Main menu (%s)', 'mcupr' ), $l['name'] );
		}
	}
	$locations['utility'] = __( 'Utility bar links (students, staff, alumni…)', 'mcupr' );
	$locations['quick']  = __( 'Quick links', 'mcupr' );
	$locations['footer'] = __( 'Footer links', 'mcupr' );
	register_nav_menus( $locations );
}
add_action( 'after_setup_theme', 'mcupr_setup' );

function mcupr_assets() {
	// Tailwind CSS build (npm run build) – see src/input.css.
	wp_enqueue_style( 'mcupr', MCUPR_URI . '/assets/css/tailwind.css', array(), MCUPR_VERSION );
	wp_add_inline_style( 'mcupr', mcupr_custom_css() );

	// SweetAlert2, self-hosted (no third-party requests).
	wp_enqueue_script( 'mcupr-swal', MCUPR_URI . '/assets/vendor/sweetalert2.all.min.js', array(), '11.26.25', array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_enqueue_script( 'mcupr', MCUPR_URI . '/assets/js/main.js', array( 'mcupr-swal' ), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular( array( 'post', 'page' ) ) && get_theme_mod( 'mcupr_listen', true ) ) {
		wp_enqueue_script( 'mcupr-listen', MCUPR_URI . '/assets/js/listen.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	$cookie_url = mcupr_policy_url( 'cookie' ) ? mcupr_policy_url( 'cookie' ) : mcupr_policy_url( 'privacy' );
	wp_localize_script(
		'mcupr',
		'mcuprI18n',
		array(
			'fontSize'    => mcupr_t( 'status_font' ),
			'reset'       => mcupr_t( 'status_reset' ),
			'submenu'     => mcupr_t( 'submenu' ),
			'cookieTitle' => mcupr_t( 'cookie_title' ),
			'cookieMsg'   => mcupr_t( 'cookie_msg' ),
			'cookieOk'    => mcupr_t( 'cookie_ok' ),
			'cookieLink'  => $cookie_url ? esc_url( $cookie_url ) : '',
			'cookiePolicy' => mcupr_t( 'cookie_policy' ),
			'cancel'      => mcupr_t( 'cancel' ),
			'resetQ'      => mcupr_t( 'reset_q' ),
			'resetQTxt'   => mcupr_t( 'reset_q_txt' ),
			'resetAll'    => mcupr_t( 'reset_all' ),
			'searchEmpty' => mcupr_t( 'search_empty' ),
			'ok'          => mcupr_t( 'cookie_ok' ),
			'close'       => mcupr_t( 'close' ),
			'a11yTitle'   => mcupr_t( 'a11y_helper' ),
			'langTitle'   => mcupr_t( 'language' ),
			'searchTitle' => mcupr_t( 'search' ),
			'cursorView'  => mcupr_t( 'cursor_view' ),
			'on'          => mcupr_t( 'on' ),
			'off'         => mcupr_t( 'off' ),
			'langBadge'   => mcupr_lang_badge(),
			'animDefaultOff' => ! get_theme_mod( 'mcupr_anim_default', true ),
			'searchUrl'   => esc_url_raw( add_query_arg( 's', '', mcupr_home_url() ) ),
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
	$anim_off = get_theme_mod( 'mcupr_anim_default', true ) ? 0 : 1;
	?>
<script>(function(){try{var d=document.documentElement,p=JSON.parse(localStorage.getItem('mcupr_prefs')||'{}');['theme','font','contrast','gray','underline','readable','spacing','cursor','motion','images','tv'].forEach(function(k){if(p[k]!==undefined&&p[k]!==''){d.setAttribute('data-'+k,p[k]);}});if(p.motion===undefined&&<?php echo (int) $anim_off; ?>){d.setAttribute('data-motion','1');}if(p.tv===undefined&&/SMART-?TV|Tizen|Web0S|WebOS|NetCast|HbbTV|VIDAA|Roku|AFT[A-Z]|BRAVIA|CrKey|GoogleTV|Android TV|AppleTV|Viera|PlayStation|Xbox/i.test(navigator.userAgent)){d.setAttribute('data-tv','1');if(p.theme===undefined){d.setAttribute('data-theme','dark');}}}catch(e){}})();</script>
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
	return in_array( mcupr_ui_lang(), array( 'zh', 'ja', 'th' ), true ) ? 60 : 30;
} );
add_filter( 'excerpt_more', static function () {
	return '…';
} );
