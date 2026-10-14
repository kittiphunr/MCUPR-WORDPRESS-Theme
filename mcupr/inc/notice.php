<?php
/**
 * Site-wide notice bar: announcement, urgent notice or mourning (black bar with ribbon, optional grey site).
 * Turn it on in Customizer → MCUPR: Notice bar; it can be limited to a date range and shows in the visitor's language.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_notice_data() {
	$on = (bool) get_theme_mod( 'mcupr_notice_on', false );
	if ( ! $on ) {
		return null;
	}
	$text = trim( (string) mcupr_text_mod( 'mcupr_notice_text' ) );
	if ( '' === $text ) {
		return null;
	}
	$today = (string) gmdate( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$from  = trim( (string) get_theme_mod( 'mcupr_notice_from', '' ) );
	$to    = trim( (string) get_theme_mod( 'mcupr_notice_to', '' ) );
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && $today < $from ) {
		return null;
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && $today > $to ) {
		return null;
	}
	$style = (string) get_theme_mod( 'mcupr_notice_style', 'notice' );
	return array(
		'text'  => $text,
		'url'   => (string) get_theme_mod( 'mcupr_notice_url', '' ),
		'label' => trim( (string) mcupr_text_mod( 'mcupr_notice_link' ) ),
		'style' => in_array( $style, array( 'notice', 'urgent', 'mourning' ), true ) ? $style : 'notice',
		'gray'  => (bool) get_theme_mod( 'mcupr_notice_gray', false ) && 'mourning' === $style,
	);
}

/** The bar itself (first thing inside #page). */
function mcupr_notice_bar() {
	$n = mcupr_notice_data();
	if ( ! $n ) {
		return;
	}
	$icon = 'mourning' === $n['style'] ? 'ribbon' : ( 'urgent' === $n['style'] ? 'alert' : 'bell' );
	echo '<div class="notice-bar notice-' . esc_attr( $n['style'] ) . ' print:hidden" role="region" aria-label="' . esc_attr( mcupr_t( 'notice_label' ) ) . '"><div class="notice-in">';
	echo '<span class="notice-ic" aria-hidden="true">' . mcupr_icon( $icon, 20 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p class="notice-t">' . esc_html( $n['text'] );
	if ( $n['url'] ) {
		echo ' <a href="' . esc_url( $n['url'] ) . '">' . esc_html( '' !== $n['label'] ? $n['label'] : mcupr_t( 'read_more' ) ) . '</a>';
	}
	echo '</p></div></div>';
}

/** Mourning "grey site": the existing grayscale switch, on for everybody. */
add_filter(
	'language_attributes',
	static function ( $out ) {
		$n = mcupr_notice_data();
		return ( $n && $n['gray'] ) ? $out . ' data-mourning="1"' : $out;
	}
);

function mcupr_notice_customize( $wp_customize ) {
	$wp_customize->add_section( 'mcupr_notice', array( 'title' => __( 'MCUPR: Notice bar (announcement / mourning)', 'mcupr' ), 'priority' => 31 ) );
	$wp_customize->add_setting( 'mcupr_notice_on', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_notice_on', array( 'section' => 'mcupr_notice', 'label' => __( 'Show the notice bar at the very top of every page', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_notice_style', array( 'default' => 'notice', 'sanitize_callback' => static function ( $v ) { return in_array( $v, array( 'notice', 'urgent', 'mourning' ), true ) ? $v : 'notice'; } ) );
	$wp_customize->add_control( 'mcupr_notice_style', array( 'section' => 'mcupr_notice', 'label' => __( 'Style of the bar', 'mcupr' ), 'type' => 'radio', 'choices' => array( 'notice' => __( 'Announcement (calm colour)', 'mcupr' ), 'urgent' => __( 'Urgent notice (amber)', 'mcupr' ), 'mourning' => __( 'Mourning (black bar with a ribbon)', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_notice_gray', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_notice_gray', array( 'section' => 'mcupr_notice', 'label' => __( 'Mourning style only: show the whole website in black and white', 'mcupr' ), 'type' => 'checkbox' ) );
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_notice_text_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_notice_text_{$code}", array( 'section' => 'mcupr_notice', 'label' => sprintf( /* translators: %s: language */ __( 'Notice text (%s)', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'text' ) );
		$wp_customize->add_setting( "mcupr_notice_link_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_notice_link_{$code}", array( 'section' => 'mcupr_notice', 'label' => sprintf( /* translators: %s: language */ __( 'Link text (%s) – empty = “Read more”', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'text' ) );
	}
	$wp_customize->add_setting( 'mcupr_notice_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_notice_url', array( 'section' => 'mcupr_notice', 'label' => __( 'Link of the notice (optional)', 'mcupr' ), 'type' => 'url' ) );
	$wp_customize->add_setting( 'mcupr_notice_from', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'mcupr_notice_from', array( 'section' => 'mcupr_notice', 'label' => __( 'Show from this date (Gregorian, YYYY-MM-DD, optional)', 'mcupr' ), 'type' => 'text', 'input_attrs' => array( 'placeholder' => '2026-10-14' ) ) );
	$wp_customize->add_setting( 'mcupr_notice_to', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'mcupr_notice_to', array( 'section' => 'mcupr_notice', 'label' => __( 'Hide after this date (Gregorian, YYYY-MM-DD, optional)', 'mcupr' ), 'type' => 'text', 'input_attrs' => array( 'placeholder' => '2026-11-14' ) ) );
}
add_action( 'customize_register', 'mcupr_notice_customize', 25 );
