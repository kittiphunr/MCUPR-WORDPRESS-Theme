<?php
/**
 * More Customizer controls: the home hero (text, buttons, overlay, height, alignment, image focus) and the
 * display of view counts. (The rest of the controls are in customizer.php.)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Hero settings with defaults, for front-page.php. */
function mcupr_hero_options() {
	$g = static function ( $k, $d ) {
		$v = get_theme_mod( $k, $d );
		return '' === $v ? $d : $v;
	};
	$sizes = array( 'short', 'medium', 'tall', 'screen' );
	$size  = (string) $g( 'mcupr_hero_height', 'medium' );
	return array(
		'eyebrow' => (string) mcupr_text_mod( 'mcupr_hero_eyebrow' ),
		'sub'     => (string) mcupr_text_mod( 'mcupr_hero_sub' ),
		'btn1'    => array( (string) mcupr_text_mod( 'mcupr_hero_btn1' ), (string) get_theme_mod( 'mcupr_hero_btn1_url', '' ) ),
		'btn2'    => array( (string) mcupr_text_mod( 'mcupr_hero_btn2' ), (string) get_theme_mod( 'mcupr_hero_btn2_url', '' ) ),
		'overlay' => max( 0, min( 85, (int) $g( 'mcupr_hero_overlay', 50 ) ) ),
		'height'  => in_array( $size, $sizes, true ) ? $size : 'medium',
		'align'   => 'left' === $g( 'mcupr_hero_align', 'center' ) ? 'left' : 'center',
		'focus'   => in_array( $g( 'mcupr_hero_focus', 'center' ), array( 'top', 'center', 'bottom' ), true ) ? $g( 'mcupr_hero_focus', 'center' ) : 'center',
		'quick'   => (bool) get_theme_mod( 'mcupr_hero_quick', true ),
	);
}

function mcupr_customize_register_extra( $wp_customize ) {
	$sec = 'mcupr_home';
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$name = mcupr_admin_lang_name( $l['name'] );
		$text = array(
			'hero_eyebrow' => array( /* translators: %s: language */ __( 'Hero: small line above the headline (%s)', 'mcupr' ), 'text' ),
			'hero_sub'     => array( /* translators: %s: language */ __( 'Hero: short paragraph under the headline (%s)', 'mcupr' ), 'textarea' ),
			'hero_btn1'    => array( /* translators: %s: language */ __( 'Hero: first button text (%s)', 'mcupr' ), 'text' ),
			'hero_btn2'    => array( /* translators: %s: language */ __( 'Hero: second button text (%s)', 'mcupr' ), 'text' ),
		);
		foreach ( $text as $key => $def ) {
			$wp_customize->add_setting( "mcupr_{$key}_{$code}", array( 'default' => '', 'sanitize_callback' => 'textarea' === $def[1] ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
			$wp_customize->add_control( "mcupr_{$key}_{$code}", array( 'section' => $sec, 'label' => sprintf( $def[0], $name ), 'type' => $def[1] ) );
		}
	}
	$wp_customize->add_setting( 'mcupr_hero_btn1_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_hero_btn1_url', array( 'section' => $sec, 'label' => __( 'Hero: first button link', 'mcupr' ), 'type' => 'url' ) );
	$wp_customize->add_setting( 'mcupr_hero_btn2_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_hero_btn2_url', array( 'section' => $sec, 'label' => __( 'Hero: second button link', 'mcupr' ), 'type' => 'url' ) );

	$wp_customize->add_setting( 'mcupr_hero_overlay', array( 'default' => 50, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'mcupr_hero_overlay', array( 'section' => $sec, 'label' => __( 'Hero: darkness of the layer over the photo (0 = none, 85 = very dark) so the text stays readable', 'mcupr' ), 'type' => 'range', 'input_attrs' => array( 'min' => 0, 'max' => 85, 'step' => 5 ) ) );
	$wp_customize->add_setting( 'mcupr_hero_height', array( 'default' => 'medium', 'sanitize_callback' => static function ( $v ) { return in_array( $v, array( 'short', 'medium', 'tall', 'screen' ), true ) ? $v : 'medium'; } ) );
	$wp_customize->add_control( 'mcupr_hero_height', array( 'section' => $sec, 'label' => __( 'Hero: height', 'mcupr' ), 'type' => 'select', 'choices' => array( 'short' => __( 'Short', 'mcupr' ), 'medium' => __( 'Medium (default)', 'mcupr' ), 'tall' => __( 'Tall', 'mcupr' ), 'screen' => __( 'Almost the whole screen', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_hero_align', array( 'default' => 'center', 'sanitize_callback' => static function ( $v ) { return 'left' === $v ? 'left' : 'center'; } ) );
	$wp_customize->add_control( 'mcupr_hero_align', array( 'section' => $sec, 'label' => __( 'Hero: text position', 'mcupr' ), 'type' => 'radio', 'choices' => array( 'center' => __( 'Centred', 'mcupr' ), 'left' => __( 'Left', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_hero_focus', array( 'default' => 'center', 'sanitize_callback' => static function ( $v ) { return in_array( $v, array( 'top', 'center', 'bottom' ), true ) ? $v : 'center'; } ) );
	$wp_customize->add_control( 'mcupr_hero_focus', array( 'section' => $sec, 'label' => __( 'Hero: which part of the photo to keep when it is cropped', 'mcupr' ), 'type' => 'radio', 'choices' => array( 'top' => __( 'Top', 'mcupr' ), 'center' => __( 'Middle', 'mcupr' ), 'bottom' => __( 'Bottom', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_hero_quick', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_hero_quick', array( 'section' => $sec, 'label' => __( 'Hero: show the quick-link tiles (menu location “Quick links”)', 'mcupr' ), 'type' => 'checkbox' ) );

	$wp_customize->add_setting( 'mcupr_show_views', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_show_views', array( 'section' => 'mcupr_display', 'label' => __( 'Show the number of views on news, pages, events and works', 'mcupr' ), 'type' => 'checkbox' ) );
}
add_action( 'customize_register', 'mcupr_customize_register_extra', 20 );
