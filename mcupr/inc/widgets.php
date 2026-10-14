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

/* -------------------------------------------------------------- Recent Posts widget: short titles + "Read more..." */

/**
 * First $max characters of a title (never cutting a Thai vowel / tone mark off its consonant), "…" when shortened.
 */
function mcupr_short_title( $title, $max = 30 ) {
	$title = trim( wp_strip_all_tags( (string) $title ) );
	if ( mb_strlen( $title ) <= $max ) {
		return $title;
	}
	$cut = $max;
	while ( $cut < mb_strlen( $title ) && preg_match( '/^[\x{0E31}\x{0E34}-\x{0E3A}\x{0E47}-\x{0E4E}]$/u', mb_substr( $title, $cut, 1 ) ) ) {
		$cut++;
	}
	return rtrim( mb_substr( $title, 0, $cut ) ) . '…';
}

/**
 * WordPress' "Recent Posts" widget is drawn by the theme: each title is cut to 30 characters and followed by a
 * "Read more..." link (the link's accessible name carries the full title). Filter 'mcupr_recent_title_length' changes 30.
 */
add_filter(
	'widget_display_callback',
	static function ( $instance, $widget, $args ) {
		if ( ! ( $widget instanceof WP_Widget_Recent_Posts ) || ! is_array( $instance ) ) {
			return $instance;
		}
		$n     = isset( $instance['number'] ) ? max( 1, min( 15, (int) $instance['number'] ) ) : 5;
		$posts = get_posts(
			array(
				'numberposts'         => $n,
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		if ( ! $posts ) {
			return false;
		}
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Recent Posts', 'default' );
		$title = apply_filters( 'widget_title', $title, $instance, $widget->id_base );
		$max   = (int) apply_filters( 'mcupr_recent_title_length', 30 );
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<ul class="mcupr-recent">';
		foreach ( $posts as $p ) {
			$full = trim( wp_strip_all_tags( get_the_title( $p ) ) );
			echo '<li>';
			if ( ! empty( $instance['show_date'] ) ) {
				echo '<span class="rp-date">' . esc_html( mcupr_date_text( get_post_timestamp( $p ) ) ) . '</span>';
			}
			echo '<a class="rp-title" href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( mcupr_short_title( $full, $max ) ) . '</a>';
			echo '<a class="rp-more" href="' . esc_url( get_permalink( $p ) ) . '" aria-label="' . esc_attr( mcupr_t( 'read_more' ) . ': ' . $full ) . '">' . esc_html( mcupr_t( 'read_more' ) ) . '...</a>';
			echo '</li>';
		}
		echo '</ul>';
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	},
	10,
	3
);
