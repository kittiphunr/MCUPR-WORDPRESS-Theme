<?php
/**
 * "The university in numbers": students, teachers, faculties and students at each level (bachelor, master,
 * doctorate). Typed in the Customizer (MCUPR: Numbers), shown as a block on the home page, as a widget
 * (any widget area) and with the shortcode [mcupr_numbers]. Blank numbers are simply left out; if the total of
 * students is blank it is the sum of the three levels.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The numbers that can be filled in: key => array( label string key, icon ). */
function mcupr_numbers_keys() {
	return array(
		'students' => array( 'nm_students', 'users' ),
		'teachers' => array( 'nm_teachers', 'user' ),
		'faculties' => array( 'nm_faculties', 'bank' ),
		'bachelor' => array( 'nm_bachelor', 'book' ),
		'master'   => array( 'nm_master', 'cap' ),
		'doctoral' => array( 'nm_doctoral', 'award' ),
	);
}

/** Only digits (people paste "12,500" or "12 500"); blank stays blank. */
function mcupr_numbers_clean( $v ) {
	$d = preg_replace( '/\D/', '', (string) $v );
	return '' === $d ? '' : (string) min( 99999999, (int) $d );
}

/** array( key => int ) for the numbers that have a value (students is the sum of the levels when left blank). */
function mcupr_numbers() {
	$out = array();
	foreach ( array_keys( mcupr_numbers_keys() ) as $k ) {
		$v = mcupr_numbers_clean( get_theme_mod( 'mcupr_num_' . $k, '' ) );
		if ( '' !== $v ) {
			$out[ $k ] = (int) $v;
		}
	}
	if ( ! isset( $out['students'] ) ) {
		$sum = ( isset( $out['bachelor'] ) ? $out['bachelor'] : 0 ) + ( isset( $out['master'] ) ? $out['master'] : 0 ) + ( isset( $out['doctoral'] ) ? $out['doctoral'] : 0 );
		if ( $sum > 0 ) {
			$out['students'] = $sum;
		}
	}
	return $out;
}

function mcupr_numbers_has() {
	return (bool) mcupr_numbers();
}

/** The tiles. $compact = one column (sidebar / footer widget). */
function mcupr_numbers_html( $compact = false ) {
	$n = mcupr_numbers();
	if ( ! $n ) {
		return '';
	}
	$keys  = mcupr_numbers_keys();
	$tile  = static function ( $k, $value, $total = 0 ) use ( $keys ) {
		$pct = $total > 0 ? ' <span class="nm-pct">(' . esc_html( number_format_i18n( $value * 100 / $total, $value * 100 / $total < 10 ? 1 : 0 ) ) . '%)</span>' : '';
		return '<div class="stat nm-tile"><span class="stat-ic" aria-hidden="true">' . mcupr_icon( $keys[ $k ][1], 22 ) . '</span><span class="min-w-0"><small>' . esc_html( mcupr_t( $keys[ $k ][0] ) ) . '</small><b>' . esc_html( number_format_i18n( $value ) ) . '</b>' . $pct . '</span></div>';
	};
	$grid  = $compact ? 'grid gap-3' : 'grid grid-cols-[repeat(auto-fit,minmax(min(14rem,100%),1fr))] gap-4';
	$html  = '';
	$main  = '';
	foreach ( array( 'students', 'teachers', 'faculties' ) as $k ) {
		if ( isset( $n[ $k ] ) ) {
			$main .= $tile( $k, $n[ $k ] );
		}
	}
	if ( $main ) {
		$html .= '<div class="' . esc_attr( $grid ) . '">' . $main . '</div>';
	}
	$lv = '';
	$lt = ( isset( $n['bachelor'] ) ? $n['bachelor'] : 0 ) + ( isset( $n['master'] ) ? $n['master'] : 0 ) + ( isset( $n['doctoral'] ) ? $n['doctoral'] : 0 );
	foreach ( array( 'bachelor', 'master', 'doctoral' ) as $k ) {
		if ( isset( $n[ $k ] ) ) {
			$lv .= $tile( $k, $n[ $k ], $lt );
		}
	}
	if ( $lv ) {
		$html .= '<h3 class="nm-sub">' . esc_html( mcupr_t( 'nm_by_level' ) ) . '</h3><div class="' . esc_attr( $grid ) . '">' . $lv . '</div>';
	}
	$asof = (string) get_theme_mod( 'mcupr_num_asof', '' );
	if ( '' !== $asof ) {
		$html .= '<p class="nm-asof">' . esc_html( $asof ) . '</p>';
	}
	return '<div class="nm' . ( $compact ? ' nm-compact' : '' ) . '">' . $html . '</div>';
}

add_shortcode(
	'mcupr_numbers',
	static function () {
		return mcupr_numbers_html();
	}
);

class MCUPR_Numbers_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_numbers', __( 'MCUPR: Numbers (students, teachers, faculties)', 'mcupr' ), array( 'description' => __( 'The figures typed in Customizer → MCUPR: Numbers.', 'mcupr' ) ) );
	}

	public function widget( $args, $instance ) {
		$html = mcupr_numbers_html( true );
		if ( '' === $html ) {
			return;
		}
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' !== $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html__( 'Title', 'mcupr' ) . '</label><input class="widefat" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" type="text" value="' . esc_attr( $title ) . '"></p>';
	}

	public function update( $new, $old ) {
		return array( 'title' => sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' ) );
	}
}
add_action( 'widgets_init', static function () { register_widget( 'MCUPR_Numbers_Widget' ); } );

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_section( 'mcupr_numbers', array( 'title' => __( 'MCUPR: Numbers (students, teachers, faculties)', 'mcupr' ), 'description' => __( 'Figures for the home page block, the “MCUPR: Numbers” widget and the [mcupr_numbers] shortcode. Leave a box empty to leave that figure out. If “Students (total)” is empty it is the sum of the three levels.', 'mcupr' ), 'priority' => 34 ) );
		$labels = array(
			'students'  => __( 'Students (total)', 'mcupr' ),
			'teachers'  => __( 'Teachers', 'mcupr' ),
			'faculties' => __( 'Faculties', 'mcupr' ),
			'bachelor'  => __( 'Students: bachelor’s degree', 'mcupr' ),
			'master'    => __( 'Students: master’s degree', 'mcupr' ),
			'doctoral'  => __( 'Students: doctoral degree', 'mcupr' ),
		);
		foreach ( $labels as $k => $label ) {
			$wp_customize->add_setting( 'mcupr_num_' . $k, array( 'default' => '', 'sanitize_callback' => 'mcupr_numbers_clean', 'transport' => 'refresh' ) );
			$wp_customize->add_control( 'mcupr_num_' . $k, array( 'section' => 'mcupr_numbers', 'label' => $label, 'type' => 'text', 'input_attrs' => array( 'inputmode' => 'numeric', 'placeholder' => '0' ) ) );
		}
		$wp_customize->add_setting( 'mcupr_num_asof', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'mcupr_num_asof', array( 'section' => 'mcupr_numbers', 'label' => __( 'Note under the figures (for example: Data as of academic year 2568)', 'mcupr' ), 'type' => 'text' ) );
	}
);
