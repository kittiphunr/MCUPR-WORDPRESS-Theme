<?php
/**
 * Customizer settings.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'mcupr_org',
		array(
			'title'    => __( 'MCUPR: Organisation & languages', 'mcupr' ),
			'priority' => 30,
		)
	);

	if ( ! mcupr_has_polylang() ) {
	$wp_customize->add_setting( 'mcupr_default_lang', array( 'default' => 'th', 'sanitize_callback' => 'mcupr_sanitize_lang' ) );
	$choices = array();
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$choices[ $code ] = $l['label'] . ' (' . $l['name'] . ')';
	}
	$wp_customize->add_control( 'mcupr_default_lang', array( 'section' => 'mcupr_org', 'label' => __( 'Default language (language of the main editor)', 'mcupr' ), 'type' => 'select', 'choices' => $choices ) );
	}

	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_name_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_name_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'Organisation name (%s)', 'mcupr' ), $l['name'] ), 'type' => 'text' ) );

		$wp_customize->add_setting( "mcupr_address_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( "mcupr_address_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'Address / contact (%s)', 'mcupr' ), $l['name'] ), 'type' => 'textarea' ) );
	}

	$wp_customize->add_setting( 'mcupr_utility_bar', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_utility_bar', array( 'section' => 'mcupr_org', 'label' => __( 'Show the built-in utility bar (language, text size, dark mode, accessibility, search)', 'mcupr' ), 'type' => 'checkbox' ) );

	// Home hero (Chula-style full-width photo with a two-line headline).
	$wp_customize->add_section( 'mcupr_home', array( 'title' => __( 'MCUPR: Home hero & banner', 'mcupr' ), 'priority' => 32 ) );
	$wp_customize->add_setting( 'mcupr_hero_image', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_hero_image', array( 'section' => 'mcupr_home', 'label' => __( 'Hero background image (falls back to the newest post image)', 'mcupr' ), 'mime_type' => 'image' ) ) );
	foreach ( mcupr_ui_languages() as $code => $l ) {
		foreach ( array( 'hero_l1' => __( 'Hero headline, light line (%s)', 'mcupr' ), 'hero_l2' => __( 'Hero headline, bold line (%s)', 'mcupr' ), 'cta_title' => __( 'Bottom banner text (%s)', 'mcupr' ) ) as $key => $label ) {
			$wp_customize->add_setting( "mcupr_{$key}_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp_customize->add_control( "mcupr_{$key}_{$code}", array( 'section' => 'mcupr_home', 'label' => sprintf( $label, $l['name'] ), 'type' => 'text' ) );
		}
	}
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_popular_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_popular_{$code}", array( 'section' => 'mcupr_home', 'label' => sprintf( /* translators: %s: language */ __( 'Popular searches, comma separated (%s)', 'mcupr' ), $l['name'] ), 'type' => 'text' ) );
	}
	$wp_customize->add_setting( 'mcupr_cta_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_cta_url', array( 'section' => 'mcupr_home', 'label' => __( 'Bottom banner link (optional)', 'mcupr' ), 'type' => 'url' ) );

	$wp_customize->add_section(
		'mcupr_policy',
		array(
			'title'       => __( 'MCUPR: Policy pages (government web standard)', 'mcupr' ),
			'description' => __( 'Pages linked from the footer. Thai government websites are expected to publish these.', 'mcupr' ),
			'priority'    => 31,
		)
	);
	$pages = array(
		'mcupr_page_privacy' => __( 'Privacy policy (PDPA)', 'mcupr' ),
		'mcupr_page_cookie'  => __( 'Cookie policy', 'mcupr' ),
		'mcupr_page_a11y'    => __( 'Accessibility statement', 'mcupr' ),
		'mcupr_page_sitemap' => __( 'Sitemap', 'mcupr' ),
		'mcupr_page_contact' => __( 'Contact us', 'mcupr' ),
	);
	foreach ( $pages as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( $id, array( 'section' => 'mcupr_policy', 'label' => $label, 'type' => 'dropdown-pages' ) );
	}

	$wp_customize->add_setting( 'mcupr_primary', array( 'default' => '#c2185b', 'sanitize_callback' => 'sanitize_hex_color' ) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mcupr_primary', array( 'section' => 'colors', 'label' => __( 'Primary colour (must keep 4.5:1 contrast with white text)', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_accent', array( 'default' => '#ffb81c', 'sanitize_callback' => 'sanitize_hex_color' ) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'mcupr_accent', array( 'section' => 'colors', 'label' => __( 'Accent colour (decorative only)', 'mcupr' ) ) ) );
}
add_action( 'customize_register', 'mcupr_customize_register' );

function mcupr_sanitize_lang( $value ) {
	return isset( mcupr_ui_languages()[ $value ] ) ? $value : 'th';
}

/** Policy page URL for a given customizer key, with WP privacy page fallback. */
function mcupr_policy_url( $key ) {
	$id = (int) get_theme_mod( 'mcupr_page_' . $key, 0 );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	if ( 'privacy' === $key ) {
		return get_privacy_policy_url();
	}
	return '';
}

/** Inline CSS variables from the Customizer. */
function mcupr_custom_css() {
	$primary = sanitize_hex_color( get_theme_mod( 'mcupr_primary', '#c2185b' ) );
	$accent  = sanitize_hex_color( get_theme_mod( 'mcupr_accent', '#ffb81c' ) );
	if ( ! $primary || ! $accent ) {
		return '';
	}
	// Same cascade layer as the theme tokens so high-contrast modes still win.
	return "@layer base{:root{--primary:{$primary};--accent:{$accent};}}";
}
