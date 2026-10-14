<?php
/**
 * Widget areas (Appearance → Widgets / Customizer → Widgets).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_widgets_init() {
	$defaults = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title mcu-title">',
		'after_title'   => '</h2>',
	);
	$areas    = array(
		'sidebar-news' => array( __( 'News sidebar', 'mcupr' ), __( 'Shown beside articles that use the “Sidebar” news layout.', 'mcupr' ) ),
		'sidebar-page' => array( __( 'Page sidebar', 'mcupr' ), __( 'Shown beside pages (below the “in this section” list).', 'mcupr' ) ),
		'home-after'   => array( __( 'Home – below the news', 'mcupr' ), __( 'Full-width area on the front page, between the news and the call-to-action banner.', 'mcupr' ) ),
		'footer-1'     => array( __( 'Footer – column 1', 'mcupr' ), __( 'First column above the footer credits.', 'mcupr' ) ),
		'footer-2'     => array( __( 'Footer – column 2', 'mcupr' ), __( 'Second column above the footer credits.', 'mcupr' ) ),
		'footer-3'     => array( __( 'Footer – column 3', 'mcupr' ), __( 'Third column above the footer credits.', 'mcupr' ) ),
	);
	foreach ( $areas as $id => $a ) {
		register_sidebar( array_merge( $defaults, array( 'id' => $id, 'name' => $a[0], 'description' => $a[1] ) ) );
	}
}
add_action( 'widgets_init', 'mcupr_widgets_init' );

/** Print a widget area, or nothing at all when it is empty. */
function mcupr_widget_area( $id, $class = '' ) {
	if ( ! is_active_sidebar( $id ) ) {
		return;
	}
	echo '<div class="widget-area ' . esc_attr( $class ) . '" data-area="' . esc_attr( $id ) . '">';
	dynamic_sidebar( $id );
	echo '</div>';
}
