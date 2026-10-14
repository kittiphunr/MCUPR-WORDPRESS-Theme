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
