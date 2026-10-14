<?php
/**
 * Shortcodes: theme shortcodes, plus "shortcode slots" – Customizer text areas
 * whose content (HTML and/or any [shortcode], from any plugin) is printed at
 * fixed places in the theme. Widgets and post content already run shortcodes.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Slot id => [ label, where ]. */
function mcupr_shortcode_slots() {
	return array(
		'header_after' => array( __( 'Below the header (every page)', 'mcupr' ), __( 'e.g. an announcement bar', 'mcupr' ) ),
		'home_top'     => array( __( 'Home: below the hero', 'mcupr' ), __( 'e.g. [mcupr_news count="3"] or a form', 'mcupr' ) ),
		'content_after' => array( __( 'After every article and page', 'mcupr' ), __( 'e.g. a share bar or a newsletter form', 'mcupr' ) ),
		'footer_before' => array( __( 'Above the footer (every page)', 'mcupr' ), __( 'e.g. a map or a contact form', 'mcupr' ) ),
	);
}

/** Customizer text: keep HTML for users who may post it, strip scripts for others. */
function mcupr_sanitize_slot( $value ) {
	return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
}

function mcupr_shortcode_customize( $wp_customize ) {
	$wp_customize->add_section(
		'mcupr_shortcodes',
		array(
			'title'       => __( 'MCUPR: Shortcodes & HTML areas', 'mcupr' ),
			'description' => __( 'Paste a [shortcode] (from the theme or any plugin) or HTML. It is printed at the place named by each box. Leave a box empty to show nothing.', 'mcupr' ),
			'priority'    => 36,
		)
	);
	foreach ( mcupr_shortcode_slots() as $id => $s ) {
		$wp_customize->add_setting( 'mcupr_slot_' . $id, array( 'default' => '', 'sanitize_callback' => 'mcupr_sanitize_slot' ) );
		$wp_customize->add_control(
			'mcupr_slot_' . $id,
			array(
				'section'     => 'mcupr_shortcodes',
				'label'       => $s[0],
				'description' => $s[1],
				'type'        => 'textarea',
			)
		);
	}
}
add_action( 'customize_register', 'mcupr_shortcode_customize' );

/** Print a slot (shortcodes are run); nothing when empty. */
function mcupr_slot( $id ) {
	$raw = trim( (string) get_theme_mod( 'mcupr_slot_' . $id, '' ) );
	if ( '' === $raw ) {
		return;
	}
	echo '<div class="mcupr-slot wrap my-6" data-slot="' . esc_attr( $id ) . '">' . do_shortcode( $raw ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** [mcupr_tools] – the language / text size / dark mode / settings / search buttons. */
function mcupr_sc_tools() {
	ob_start();
	mcupr_tools_cluster();
	return ob_get_clean();
}
add_shortcode( 'mcupr_tools', 'mcupr_sc_tools' );

/** [mcupr_news count="3" category="slug" columns="3"] – latest news cards. */
function mcupr_sc_news( $atts ) {
	$a   = shortcode_atts( array( 'count' => 3, 'category' => '', 'columns' => 3 ), $atts, 'mcupr_news' );
	$q   = new WP_Query(
		array(
			'posts_per_page'      => max( 1, min( 12, (int) $a['count'] ) ),
			'category_name'       => sanitize_text_field( $a['category'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	$col = max( 1, min( 4, (int) $a['columns'] ) );
	$grid = array( 1 => '', 2 => 'min-[700px]:grid-cols-2', 3 => 'min-[700px]:grid-cols-2 min-[1000px]:grid-cols-3', 4 => 'min-[700px]:grid-cols-2 min-[1000px]:grid-cols-4' );
	ob_start();
	if ( $q->have_posts() ) {
		echo '<div class="grid gap-6 ' . esc_attr( $grid[ $col ] ) . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			get_template_part( 'template-parts/card' );
		}
		echo '</div>';
	}
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'mcupr_news', 'mcupr_sc_news' );

/** [mcupr_button url="/contact/" style="solid|outline"]Label[/mcupr_button] */
function mcupr_sc_button( $atts, $content = '' ) {
	$a = shortcode_atts( array( 'url' => '#', 'style' => 'solid' ), $atts, 'mcupr_button' );
	if ( 'outline' === $a['style'] ) {
		$cls = 'inline-flex min-h-11 items-center rounded-full border-2 border-primary px-6 font-bold text-link no-underline hover:bg-tint';
	} else {
		$cls = 'btn';
	}
	return '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $a['url'] ) . '">' . wp_kses_post( do_shortcode( $content ) ) . '</a>';
}
add_shortcode( 'mcupr_button', 'mcupr_sc_button' );

/** Let widgets that print raw text (excerpts, custom HTML) run shortcodes too. */
add_filter( 'widget_text', 'do_shortcode' );
