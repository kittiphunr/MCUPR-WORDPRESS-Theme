<?php
/**
 * Four-language support (th / en / zh / ja).
 *
 * - UI strings come from inc/strings.php
 * - Language is chosen with ?lang=xx, remembered in a functional cookie
 * - Post / page content can be translated per language via a meta box
 *   (inc/translations.php)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported languages. Order must match inc/strings.php columns.
 * Filter `mcupr_languages` can rename/relabel but not reorder string columns.
 */
function mcupr_languages() {
	return apply_filters(
		'mcupr_languages',
		array(
			'th' => array( 'label' => 'ไทย',      'name' => 'Thai',     'html' => 'th',      'locale' => 'th_TH' ),
			'en' => array( 'label' => 'English', 'name' => 'English',  'html' => 'en',      'locale' => 'en_US' ),
			'zh' => array( 'label' => '中文',      'name' => 'Chinese',  'html' => 'zh-Hans', 'locale' => 'zh_CN' ),
			'ja' => array( 'label' => '日本語',    'name' => 'Japanese', 'html' => 'ja',      'locale' => 'ja' ),
		)
	);
}

function mcupr_default_lang() {
	$lang = get_theme_mod( 'mcupr_default_lang', 'th' );
	return isset( mcupr_languages()[ $lang ] ) ? $lang : 'th';
}

/**
 * Language of the current request.
 */
function mcupr_lang() {
	static $current = null;
	if ( null !== $current ) {
		return $current;
	}
	$langs   = mcupr_languages();
	$current = mcupr_default_lang();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['lang'] ) ) {
		$req = sanitize_key( wp_unslash( $_GET['lang'] ) );
	} elseif ( isset( $_COOKIE['mcupr_lang'] ) ) {
		$req = sanitize_key( wp_unslash( $_COOKIE['mcupr_lang'] ) );
	} else {
		$req = '';
	}
	// phpcs:enable
	if ( $req && isset( $langs[ $req ] ) ) {
		$current = $req;
	}
	return $current;
}

/** Remember the chosen language (functional cookie, 1 year). */
function mcupr_remember_lang() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_admin() || empty( $_GET['lang'] ) || headers_sent() ) {
		return;
	}
	setcookie(
		'mcupr_lang',
		mcupr_lang(),
		array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}
add_action( 'init', 'mcupr_remember_lang' );

/** Translate a UI string. */
function mcupr_t( $key, $lang = '' ) {
	static $strings = null;
	if ( null === $strings ) {
		$strings = require MCUPR_DIR . '/inc/strings.php';
	}
	$lang  = $lang ? $lang : mcupr_lang();
	$codes = array_keys( mcupr_languages() );
	$i     = array_search( $lang, $codes, true );
	if ( ! isset( $strings[ $key ] ) ) {
		return $key;
	}
	$row = $strings[ $key ];
	return isset( $row[ $i ] ) && '' !== $row[ $i ] ? $row[ $i ] : $row[1];
}

function mcupr_e( $key ) {
	echo esc_html( mcupr_t( $key ) );
}

/** URL of the current page in another language. */
function mcupr_lang_url( $code ) {
	return esc_url( add_query_arg( 'lang', $code ) );
}

/** <html lang="…"> */
function mcupr_language_attributes( $output ) {
	if ( is_admin() ) {
		return $output;
	}
	$langs = mcupr_languages();
	$html  = $langs[ mcupr_lang() ]['html'];
	$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $html ) . '"', $output, 1, $n );
	if ( ! $n ) {
		$output .= ' lang="' . esc_attr( $html ) . '"';
	}
	return $output;
}
add_filter( 'language_attributes', 'mcupr_language_attributes' );

/** hreflang alternates for search engines. */
function mcupr_hreflang() {
	foreach ( mcupr_languages() as $code => $l ) {
		printf(
			'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
			esc_attr( $l['html'] ),
			esc_url( add_query_arg( 'lang', $code ) )
		);
	}
}
add_action( 'wp_head', 'mcupr_hreflang', 3 );

/** Per-language option from Customizer, falling back to default language. */
function mcupr_text_mod( $base, $lang = '' ) {
	$lang = $lang ? $lang : mcupr_lang();
	$val  = get_theme_mod( $base . '_' . $lang, '' );
	if ( '' === $val ) {
		$val = get_theme_mod( $base . '_' . mcupr_default_lang(), '' );
	}
	return $val;
}

/** Organisation name in the current language. */
function mcupr_org_name() {
	$name = mcupr_text_mod( 'mcupr_name' );
	return $name ? $name : get_bloginfo( 'name' );
}

/**
 * ' lang="xx"' when the content being shown is in a different language than
 * the page (WCAG 3.1.2 Language of Parts).
 */
function mcupr_part_lang_attr( $post_id = 0 ) {
	$content_lang = mcupr_post_lang( $post_id );
	if ( $content_lang === mcupr_lang() ) {
		return '';
	}
	$langs = mcupr_languages();
	return ' lang="' . esc_attr( $langs[ $content_lang ]['html'] ) . '"';
}
