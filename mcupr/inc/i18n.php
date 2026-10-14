<?php
/**
 * Language support.
 *
 * Built-in switcher (?lang=xx + cookie) and a per-post "Translations" meta box
 * (inc/translations.php). UI strings ship for th / en / zh / ja.
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

/** Languages active on this site (key = language slug). */
function mcupr_languages() {
	return apply_filters( 'mcupr_languages', mcupr_ui_languages() );
}

function mcupr_default_lang() {
	$lang = get_theme_mod( 'mcupr_default_lang', 'th' );
	return isset( mcupr_ui_languages()[ $lang ] ) ? $lang : 'th';
}

/** Language (slug) of the current request. */
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

/** Map any language slug (e.g. "zh-cn", "en_GB") to one of th / en / zh / ja, else "en". */
function mcupr_normalize_ui_lang( $slug ) {
	$short = strtolower( substr( (string) $slug, 0, 2 ) );
	return isset( mcupr_ui_languages()[ $short ] ) ? $short : 'en';
}

/** UI-string language (th / en / zh / ja) for the current request. */
function mcupr_ui_lang() {
	return mcupr_normalize_ui_lang( mcupr_lang() );
}

/** Remember the chosen language (functional cookie, 1 year). */
function mcupr_remember_lang() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( is_admin() || empty( $_GET['lang'] ) || headers_sent() ) {
		return;
	}
	$expires = time() + YEAR_IN_SECONDS;
	$path    = COOKIEPATH ? COOKIEPATH : '/';
	if ( version_compare( PHP_VERSION, '7.3.0', '>=' ) ) {
		setcookie(
			'mcupr_lang',
			mcupr_lang(),
			array(
				'expires'  => $expires,
				'path'     => $path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	} else {
		// PHP < 7.3 has no options array (and rejects ';' in the path), so send the header ourselves.
		header(
			sprintf(
				'Set-Cookie: mcupr_lang=%s; Expires=%s; Max-Age=%d; Path=%s; HttpOnly; SameSite=Lax%s',
				rawurlencode( mcupr_lang() ),
				gmdate( 'D, d M Y H:i:s \G\M\T', $expires ),
				YEAR_IN_SECONDS,
				$path,
				is_ssl() ? '; Secure' : ''
			),
			false
		);
	}
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
	return home_url( '/' );
}

/** URL of the current page in another language (standalone mode). */
function mcupr_lang_url( $code ) {
	return esc_url( add_query_arg( 'lang', $code ) );
}

/** <html lang="…">. */
function mcupr_language_attributes( $output ) {
	if ( is_admin() ) {
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

/** hreflang alternates. */
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
