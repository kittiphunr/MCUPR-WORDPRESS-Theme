<?php
/**
 * Elementor / Elementor Pro integration.
 *
 * With Elementor Pro's Theme Builder you can design the Header, Footer,
 * Single, Archive (incl. search results) and 404 templates visually; the theme
 * hands those locations to Elementor and falls back to its own Tailwind
 * templates when no Elementor template applies.
 *
 * The utility bar (language, text size, dark mode, accessibility panel, search)
 * is always rendered by the theme so the accessibility features keep working
 * even when the header is replaced by an Elementor template.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Let Elementor Pro Theme Builder take over a location. Returns true if it printed something. */
function mcupr_elementor_location( $location ) {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}

/** Register the core Theme Builder locations. */
add_action(
	'elementor/theme/register_locations',
	static function ( $manager ) {
		$manager->register_all_core_location();
	}
);

/** Is this post designed with the Elementor editor? */
function mcupr_is_elementor_page( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	return $post_id && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/**
 * Elementor's own styles use px; text-size buttons scale rem/em only.
 * Map Elementor global colours to the theme tokens so widgets that use
 * Global Colors follow dark mode / high-contrast modes (see src/input.css).
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		// Elementor's default Google-fonts request would send visitor IPs to a third party.
		add_filter( 'elementor/frontend/print_google_fonts', '__return_false' );
	},
	5
);

/** Shortcodes for the Elementor "Shortcode" widget (custom headers). */
function mcupr_sc_languages() {
	ob_start();
	get_template_part( 'template-parts/lang-switcher' );
	return ob_get_clean();
}
add_shortcode( 'mcupr_languages', 'mcupr_sc_languages' );

function mcupr_sc_search() {
	return get_search_form( array( 'echo' => false ) );
}
add_shortcode( 'mcupr_search', 'mcupr_sc_search' );

function mcupr_sc_tools() {
	ob_start();
	get_template_part( 'template-parts/display-tools' );
	return ob_get_clean();
}
add_shortcode( 'mcupr_tools', 'mcupr_sc_tools' );
