<?php
/**
 * The bottom bar of a phone (below 1100 px, where the menu is a drawer): Home · News · [the big round button that opens the
 * whole menu] · Events · Search. It is in every menu layout (top, bar, side) and every colour mode. The round button is the
 * same menu button as in the header (it opens the same drawer), so nothing is lost when the bar is switched off.
 *
 * Customizer → Animation, news & reading: "Bottom bar on phones". Items can be changed with the `mcupr_dock_items` filter
 * (each: key, label, url, icon, current (bool)).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_dock_enabled() {
	return (bool) get_theme_mod( 'mcupr_dock', true ) && ! is_customize_preview() && ! is_embed() && ! is_404() && ! is_admin() && ! is_feed() && ! is_page_template( 'elementor-canvas.php' );
}

/** The four places beside the round button (two on each side). */
function mcupr_dock_items() {
	$news_on_front = is_front_page() && ! mcupr_is_news_list_view();
	$items         = array(
		array( 'key' => 'home', 'label' => mcupr_t( 'home' ), 'url' => mcupr_home_url(), 'icon' => 'home', 'current' => $news_on_front ),
		array( 'key' => 'news', 'label' => mcupr_t( 'dock_news' ), 'url' => mcupr_news_url(), 'icon' => 'news', 'current' => ( ! is_front_page() || mcupr_is_news_list_view() ) && ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || ( is_date() && ! is_post_type_archive() ) ) ),
	);
	$events = get_post_type_archive_link( 'mcupr_event' );
	if ( $events ) {
		$items[] = array( 'key' => 'events', 'label' => mcupr_t( 'dock_events' ), 'url' => $events, 'icon' => 'calendar', 'current' => is_post_type_archive( 'mcupr_event' ) || is_singular( 'mcupr_event' ) );
	}
	$items[] = array( 'key' => 'search', 'label' => mcupr_t( 'search' ), 'url' => add_query_arg( 's', '', mcupr_home_url() ), 'icon' => 'search', 'current' => is_search() );
	return (array) apply_filters( 'mcupr_dock_items', $items );
}

function mcupr_dock_markup() {
	if ( ! mcupr_dock_enabled() ) {
		return;
	}
	$items = array_values( mcupr_dock_items() );
	$half  = (int) ceil( count( $items ) / 2 );
	$one   = static function ( $it ) {
		$cur = ! empty( $it['current'] );
		return '<a class="dock-i" href="' . esc_url( $it['url'] ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-dock="' . esc_attr( $it['key'] ) . '"><span class="dock-ic">' . mcupr_icon( $it['icon'], 22 ) . '</span><span class="dock-t">' . esc_html( $it['label'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	};
	echo '<nav id="m-dock" class="m-dock" aria-label="' . esc_attr( mcupr_t( 'dock_nav' ) ) . '">';
	foreach ( array_slice( $items, 0, $half ) as $it ) {
		echo $one( $it ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<button type="button" class="dock-main" aria-controls="site-nav" aria-expanded="false" aria-label="' . esc_attr( mcupr_t( 'open_menu' ) ) . '" data-dock-menu>' . mcupr_icon( 'menu', 26 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( array_slice( $items, $half ) as $it ) {
		echo $one( $it ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo "</nav>\n";
}
add_action( 'wp_footer', 'mcupr_dock_markup', 5 );

/** Tell the stylesheet there is a bar (so it can keep the page and the floating buttons clear of it). */
add_filter(
	'language_attributes',
	static function ( $out ) {
		return ( ! is_admin() && function_exists( 'mcupr_dock_enabled' ) && mcupr_dock_enabled() ) ? $out . ' data-dock="1"' : $out;
	}
);

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		if ( ! $wp_customize->get_section( 'mcupr_display' ) ) {
			return;
		}
		$wp_customize->add_setting( 'mcupr_dock', array( 'default' => true, 'sanitize_callback' => static function ( $v ) { return (bool) $v; } ) );
		$wp_customize->add_control( 'mcupr_dock', array( 'section' => 'mcupr_display', 'priority' => 17, 'label' => __( 'Bottom bar on phones', 'mcupr' ), 'description' => __( 'Home, News, the menu button, Events and Search at the bottom of the screen on phones and small tablets.', 'mcupr' ), 'type' => 'checkbox' ) );
	},
	20
);
