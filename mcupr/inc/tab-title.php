<?php
/**
 * The title on the browser tab types itself out, and when it is longer than the tab it scrolls.
 *
 * The browser cuts a long title short ("มหาวิทยาลัยมหาจุฬาลงกรณราชวิทย…"), so the end of the name is never seen. This types
 * the title in, and when it does not fit it slides along the tab for two rounds and then comes to rest on the whole title,
 * which is also the one thing a bookmark, the history and a printout should ever hold.
 *
 * It is a front-end effect only (assets/js/tab-title.js) and it stands down for a visitor who asked for less motion, on a
 * background tab, and while printing. Set it up at Customizer → MCUPR: Animation, news & reading.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** key => array( milliseconds per typed letter, milliseconds per step of the scroll ). */
function mcupr_tab_speeds() {
	return array(
		'slow'   => array( 140, 320 ),
		'normal' => array( 90, 220 ),
		'fast'   => array( 55, 140 ),
	);
}

function mcupr_tab_sanitize_mode( $v ) {
	return in_array( $v, array( 'off', 'type', 'scroll' ), true ) ? $v : 'scroll';
}

function mcupr_tab_sanitize_speed( $v ) {
	return isset( mcupr_tab_speeds()[ $v ] ) ? $v : 'normal';
}

function mcupr_tab_sanitize_width( $v ) {
	return max( 8, min( 60, (int) $v ) );
}

/** What the site has chosen, always inside the limits the script can work with. */
function mcupr_tab_title_opts() {
	$speed = mcupr_tab_sanitize_speed( get_theme_mod( 'mcupr_tab_speed', 'normal' ) );
	$ms    = mcupr_tab_speeds()[ $speed ];
	return array(
		'mode'  => mcupr_tab_sanitize_mode( get_theme_mod( 'mcupr_tab_mode', 'scroll' ) ),
		'speed' => $speed,
		'type'  => $ms[0],  // one letter typed
		'step'  => $ms[1],  // one step of the scroll
		'rest'  => 900,     // the pause between the end of the typing and the start of the scroll
		'width' => mcupr_tab_sanitize_width( get_theme_mod( 'mcupr_tab_width', 24 ) ), // how many letters a tab shows
	);
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		if ( ! $wp_customize->get_section( 'mcupr_display' ) ) {
			return;
		}
		$wp_customize->add_setting( 'mcupr_tab_mode', array( 'default' => 'scroll', 'sanitize_callback' => 'mcupr_tab_sanitize_mode' ) );
		$wp_customize->add_control(
			'mcupr_tab_mode',
			array(
				'section'     => 'mcupr_display',
				'priority'    => 36,
				'label'       => __( 'Title on the browser tab', 'mcupr' ),
				'description' => __( 'Type the title in letter by letter. When it is longer than the tab, it also scrolls along for two rounds and then rests on the whole title. A visitor who asked for less motion always sees the plain title.', 'mcupr' ),
				'type'        => 'select',
				'choices'     => array(
					'scroll' => __( 'Type it, and scroll it when it is too long', 'mcupr' ),
					'type'   => __( 'Only type it', 'mcupr' ),
					'off'    => __( 'Plain title, no effect', 'mcupr' ),
				),
			)
		);
		$wp_customize->add_setting( 'mcupr_tab_speed', array( 'default' => 'normal', 'sanitize_callback' => 'mcupr_tab_sanitize_speed' ) );
		$wp_customize->add_control(
			'mcupr_tab_speed',
			array(
				'section'  => 'mcupr_display',
				'priority' => 37,
				'label'    => __( 'Speed of the tab title', 'mcupr' ),
				'type'     => 'select',
				'choices'  => array(
					'slow'   => __( 'Slow', 'mcupr' ),
					'normal' => __( 'Normal', 'mcupr' ),
					'fast'   => __( 'Fast', 'mcupr' ),
				),
			)
		);
		$wp_customize->add_setting( 'mcupr_tab_width', array( 'default' => 24, 'sanitize_callback' => 'mcupr_tab_sanitize_width' ) );
		$wp_customize->add_control(
			'mcupr_tab_width',
			array(
				'section'     => 'mcupr_display',
				'priority'    => 38,
				'label'       => __( 'Letters that fit on a tab', 'mcupr' ),
				'description' => __( 'The title scrolls only when it has more letters than this. A browser tab shows about 24 to 30; lower it if visitors open many tabs.', 'mcupr' ),
				'type'        => 'range',
				'input_attrs' => array( 'min' => 8, 'max' => 60, 'step' => 1 ),
			)
		);
	},
	20
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$o = mcupr_tab_title_opts();
		if ( 'off' === $o['mode'] || is_customize_preview() ) {
			return;
		}
		wp_enqueue_script( 'mcupr-tab-title', MCUPR_URI . '/assets/js/tab-title.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'mcupr-tab-title', 'mcuprTab', $o );
	}
);
