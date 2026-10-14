<?php
/**
 * Language support.
 *
 * Two modes:
 *  1. Polylang (free plugin) active  -> Polylang owns languages, URLs, content
 *     translation, menus and hreflang. The theme only supplies UI strings for
 *     th / en / zh / ja (other languages fall back to English).
 *  2. Standalone (no plugin)         -> built-in switcher (?lang=xx + cookie)
 *     and a per-post "Translations" meta box (inc/translations.php).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Languages the theme ships UI strings for (columns of inc/strings.php). */
function mcupr_ui_languages() {
	return array(
		'th' => array( 'label' => 'ไทย',      'name' => 'Thai',     'html' => 'th',      'locale' => 'th_TH' ),
		'en' => array( 'label' => 'English', 'name' => 'English',  'html' => 'en',      'locale' => 'en_US' ),
		'zh' => array( 'label' => '中文',      'name' => 'Chinese',  'html' => 'zh-Hans', 'locale' => 'zh_CN' ),
		'ja' => array( 'label' => '日本語',    'name' => 'Japanese', 'html' => 'ja',      'locale' => 'ja' ),
	);
}

/** Is the Polylang plugin (free) providing multilingual features? */
function mcupr_has_polylang() {
	return function_exists( 'pll_current_language' ) && function_exists( 'pll_the_languages' );
}

/** Languages active on this site (key = language slug). */
function mcupr_languages() {
	if ( mcupr_has_polylang() ) {
		$out = array();
		foreach ( (array) pll_the_languages( array( 'raw' => 1, 'hide_if_empty' => 0 ) ) as $slug => $l ) {
			$out[ $slug ] = array(
				'label'  => $l['name'],
				'name'   => $l['name'],
				'html'   => str_replace( '_', '-', $l['locale'] ),
				'locale' => $l['locale'],
				'url'    => $l['url'],
			);
		}
		if ( $out ) {
			return $out;
		}
	}
	return apply_filters( 'mcupr_languages', mcupr_ui_languages() );
}

function mcupr_default_lang() {
	if ( mcupr_has_polylang() && function_exists( 'pll_default_language' ) && pll_default_language() ) {
		return pll_default_language();
	}
	$lang = get_theme_mod( 'mcupr_default_lang', 'th' );
	return isset( mcupr_ui_languages()[ $lang ] ) ? $lang : 'th';
}

/** Language (slug) of the current request. */
function mcupr_lang() {
	static $current = null;
	if ( null !== $current && ! mcupr_has_polylang() ) {
		return $current;
	}
	if ( mcupr_has_polylang() ) {
		$slug = pll_current_language();
		return $slug ? $slug : mcupr_default_lang();
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

/** Map any language slug (e.g. "zh-cn", "en_GB") to one of th / en / zh / ja, else "en". */
function mcupr_normalize_ui_lang( $slug ) {
	$short = strtolower( substr( (string) $slug, 0, 2 ) );
	return isset( mcupr_ui_languages()[ $short ] ) ? $short : 'en';
}

/** UI-string language (th / en / zh / ja) for the current request. */
function mcupr_ui_lang() {
	return mcupr_normalize_ui_lang( mcupr_lang() );
}

/** Remember the chosen language (functional cookie, 1 year) – standalone mode only. */
function mcupr_remember_lang() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( mcupr_has_polylang() || is_admin() || empty( $_GET['lang'] ) || headers_sent() ) {
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
	$lang = $lang ? mcupr_normalize_ui_lang( $lang ) : mcupr_ui_lang();
	$i    = array_search( $lang, array_keys( mcupr_ui_languages() ), true );
	if ( ! isset( $strings[ $key ] ) ) {
		return $key;
	}
	$row = $strings[ $key ];
	return isset( $row[ $i ] ) && '' !== $row[ $i ] ? $row[ $i ] : $row[1];
}

function mcupr_e( $key ) {
	echo esc_html( mcupr_t( $key ) );
}

/** Home URL in the current language. */
function mcupr_home_url() {
	return function_exists( 'pll_home_url' ) ? pll_home_url() : home_url( '/' );
}

/** URL of the current page in another language (standalone mode). */
function mcupr_lang_url( $code ) {
	if ( mcupr_has_polylang() ) {
		$langs = mcupr_languages();
		return isset( $langs[ $code ]['url'] ) ? esc_url( $langs[ $code ]['url'] ) : esc_url( mcupr_home_url() );
	}
	return esc_url( add_query_arg( 'lang', $code ) );
}

/** <html lang="…"> – standalone mode (Polylang sets its own). */
function mcupr_language_attributes( $output ) {
	if ( is_admin() || mcupr_has_polylang() ) {
		return $output;
	}
	$langs  = mcupr_languages();
	$html   = $langs[ mcupr_lang() ]['html'];
	$output = preg_replace( '/lang="[^"]*"/', 'lang="' . esc_attr( $html ) . '"', $output, 1, $n );
	if ( ! $n ) {
		$output .= ' lang="' . esc_attr( $html ) . '"';
	}
	return $output;
}
add_filter( 'language_attributes', 'mcupr_language_attributes' );

/** hreflang alternates – standalone mode (Polylang prints its own). */
function mcupr_hreflang() {
	if ( mcupr_has_polylang() ) {
		return;
	}
	foreach ( mcupr_languages() as $code => $l ) {
		printf(
			'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
			esc_attr( $l['html'] ),
			esc_url( add_query_arg( 'lang', $code ) )
		);
	}
}
add_action( 'wp_head', 'mcupr_hreflang', 3 );

/** Per-language Customizer text (th/en/zh/ja), falling back to the default language. */
function mcupr_text_mod( $base, $lang = '' ) {
	$lang = $lang ? $lang : mcupr_ui_lang();
	$val  = get_theme_mod( $base . '_' . $lang, '' );
	if ( '' === $val ) {
		$val = get_theme_mod( $base . '_' . mcupr_normalize_ui_lang( mcupr_default_lang() ), '' );
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
	$html  = isset( $langs[ $content_lang ]['html'] ) ? $langs[ $content_lang ]['html'] : $content_lang;
	return ' lang="' . esc_attr( $html ) . '"';
}
