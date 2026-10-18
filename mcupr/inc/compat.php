<?php
/**
 * Minimum-version guard: on an unsupported WordPress / PHP the theme does not
 * load; activation is rolled back to the previous theme with an explanation.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_is_supported() {
	global $wp_version;
	return version_compare( PHP_VERSION, MCUPR_MIN_PHP, '>=' ) && version_compare( $wp_version, MCUPR_MIN_WP, '>=' );
}

function mcupr_compat_message() {
	global $wp_version;
	return sprintf(
		/* translators: 1: required WP, 2: required PHP, 3: current WP, 4: current PHP */
		__( 'The MCUPR theme needs WordPress %1$s and PHP %2$s or newer. You are running WordPress %3$s and PHP %4$s.', 'mcupr' ),
		MCUPR_MIN_WP,
		MCUPR_MIN_PHP,
		$wp_version,
		PHP_VERSION
	);
}

if ( ! mcupr_is_supported() ) {
	add_action(
		'after_switch_theme',
		static function () {
			switch_theme( get_option( 'theme_switched' ) ? get_option( 'theme_switched' ) : WP_DEFAULT_THEME );
			unset( $_GET['activated'] );
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html( mcupr_compat_message() ) . '</p></div>';
				}
			);
		}
	);
	add_action(
		'template_redirect',
		static function () {
			wp_die( esc_html( mcupr_compat_message() ) );
		}
	);
}

/** After activation: refresh permalinks so the theme's pages work at once. */
add_action(
	'after_switch_theme',
	static function () {
		if ( mcupr_is_supported() ) {
			flush_rewrite_rules( false );
		}
	}
);

/**
 * The "escape" character for fgetcsv() / fputcsv(). PHP 7.4 and later accept '' (no escape character), but PHP 7.0 – 7.3 want exactly
 * one character and otherwise warn and return false – which made a good file look "empty". NUL never occurs in a CSV, so it is safe everywhere.
 */
function mcupr_csv_escape() {
	return "\0";
}

/** The visitor's network for rate limits: the address, with an IPv6 address cut to its /64 (one household or one rented block has a whole /64 to rotate in). */
function mcupr_client_net() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( false !== strpos( $ip, ':' ) && function_exists( 'inet_pton' ) ) {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false !== $bin && 16 === strlen( $bin ) ) {
			return bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}
	}
	return $ip;
}

/** setcookie() for every PHP version the theme supports (7.0 – 7.2 have no options array): first-party, SameSite=Lax, path / unless one is given. */
function mcupr_set_cookie( $name, $value, $expires, $httponly = true, $path = '/' ) {
	if ( headers_sent() ) {
		return;
	}
	if ( PHP_VERSION_ID >= 70300 ) {
		setcookie( $name, $value, array( 'expires' => $expires, 'path' => $path, 'secure' => is_ssl(), 'httponly' => $httponly, 'samesite' => 'Lax' ) );
	} else {
		// PHP < 7.3 has no options array (and rejects ';' in the path), so send the header ourselves.
		header(
			sprintf(
				'Set-Cookie: %s=%s%s; Path=%s; %sSameSite=Lax%s',
				$name,
				rawurlencode( (string) $value ),
				$expires > 0 ? '; Expires=' . gmdate( 'D, d M Y H:i:s \G\M\T', $expires ) . '; Max-Age=' . max( 0, $expires - time() ) : '',
				$path,
				$httponly ? 'HttpOnly; ' : '',
				is_ssl() ? '; Secure' : ''
			),
			false
		);
	}
}
