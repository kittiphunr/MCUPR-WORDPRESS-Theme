<?php
/**
 * Website statistics for the editors: views per day (kept for 120 days), the most viewed pages and the most
 * downloaded files. A summary on the Dashboard and a full page at Dashboard → Website statistics.
 * Numbers come from the theme's own counters (no tracking script, no data leaves the site).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** One more view today (called by the view counter). */
function mcupr_stats_hit() {
	$days  = get_option( 'mcupr_daily_views', array() );
	$days  = is_array( $days ) ? $days : array();
	$today = (string) wp_date( 'Y-m-d' );
	$days[ $today ] = ( isset( $days[ $today ] ) ? (int) $days[ $today ] : 0 ) + 1;
	krsort( $days );
	$min  = (string) wp_date( 'Y-m-d', time() - 120 * DAY_IN_SECONDS );
	$days = array_filter( $days, static function ( $k ) use ( $min ) { return $k >= $min; }, ARRAY_FILTER_USE_KEY );
	update_option( 'mcupr_daily_views', $days, false );
}

/** array( date => views ) for the last $n days (oldest first), zeros included. */
function mcupr_stats_series( $n = 30 ) {
	$days = get_option( 'mcupr_daily_views', array() );
	$days = is_array( $days ) ? $days : array();
	$out  = array();
	for ( $i = $n - 1; $i >= 0; $i-- ) {
		$d         = (string) wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS );
		$out[ $d ] = isset( $days[ $d ] ) ? (int) $days[ $d ] : 0;
	}
	return $out;
}

function mcupr_stats_total_views() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_mcupr_views'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Most viewed content: array of WP_Post. */
function mcupr_stats_top_posts( $limit = 10 ) {
	return get_posts(
		array(
			'post_type'      => mcupr_tool_types(),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_key'       => '_mcupr_views', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
}

/** Most downloaded files: array of array( title, post, count ). */
function mcupr_stats_top_files( $limit = 10 ) {
	$rows  = array();
	$posts = get_posts( array( 'post_type' => mcupr_tool_types(), 'post_status' => 'publish', 'posts_per_page' => 500, 'meta_key' => '_mcupr_files', 'no_found_rows' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $posts as $p ) {
		foreach ( mcupr_files( $p->ID ) as $f ) {
			if ( $f['count'] > 0 ) {
				$rows[] = array( '' !== trim( $f['title'] ) ? $f['title'] : get_the_title( $f['id'] ), $p, $f['count'] );
			}
		}
	}
	usort( $rows, static function ( $a, $b ) { return $b[2] - $a[2]; } );
	return array_slice( $rows, 0, $limit );
}

/** Tiny bar chart (CSS only). */
function mcupr_stats_bars( $series ) {
	$max = max( 1, max( $series ) );
	echo '<div class="mcupr-bars" role="img" aria-label="' . esc_attr( sprintf( /* translators: %d: days */ __( 'Views per day, last %d days', 'mcupr' ), count( $series ) ) ) . '">';
	foreach ( $series as $d => $v ) {
		printf( '<span title="%1$s: %2$s" style="height:%3$d%%"></span>', esc_attr( $d ), esc_attr( number_format_i18n( $v ) ), (int) max( 2, round( $v / $max * 100 ) ) );
	}
	echo '</div>';
}

function mcupr_stats_css() {
	return '.mcupr-bars{display:flex;align-items:flex-end;gap:3px;height:90px;padding:6px 0;border-bottom:1px solid #c3c4c7}.mcupr-bars span{flex:1;min-width:3px;background:#c2185b;border-radius:2px 2px 0 0}.mcupr-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(9rem,1fr));gap:12px;margin:12px 0}.mcupr-stat-grid div{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:10px 14px}.mcupr-stat-grid b{display:block;font-size:22px}.mcupr-stat-grid small{color:#50575e}';
}

function mcupr_stats_summary_cards() {
	$s     = mcupr_stats_series( 30 );
	$today = (int) end( $s );
	echo '<div class="mcupr-stat-grid">';
	$cards = array(
		__( 'Today', 'mcupr' )         => $today,
		__( 'Last 7 days', 'mcupr' )   => array_sum( array_slice( $s, -7 ) ),
		__( 'Last 30 days', 'mcupr' )  => array_sum( $s ),
		__( 'All time', 'mcupr' )      => mcupr_stats_total_views(),
	);
	foreach ( $cards as $label => $n ) {
		echo '<div><b>' . esc_html( number_format_i18n( $n ) ) . '</b><small>' . esc_html( $label ) . '</small></div>';
	}
	echo '</div>';
}

function mcupr_stats_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Website statistics', 'mcupr' ) . '</h1><style>' . mcupr_stats_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p class="description">' . esc_html__( 'Counted by the theme itself: each visitor once per 6 hours per page; editors and search-engine robots are not counted.', 'mcupr' ) . '</p>';
	mcupr_stats_summary_cards();
	echo '<h2>' . esc_html__( 'Views per day (last 30 days)', 'mcupr' ) . '</h2>';
	mcupr_stats_bars( mcupr_stats_series( 30 ) );
	echo '<h2>' . esc_html__( 'Most viewed pages', 'mcupr' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'mcupr' ) . '</th><th>' . esc_html__( 'Type', 'mcupr' ) . '</th><th>' . esc_html__( 'Views', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( mcupr_stats_top_posts( 15 ) as $p ) {
		$o = get_post_type_object( $p->post_type );
		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></td><td>' . esc_html( $o ? $o->labels->singular_name : $p->post_type ) . '</td><td>' . esc_html( number_format_i18n( mcupr_views( $p->ID ) ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<h2>' . esc_html__( 'Most downloaded files', 'mcupr' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'mcupr' ) . '</th><th>' . esc_html__( 'In', 'mcupr' ) . '</th><th>' . esc_html__( 'Downloads', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( mcupr_stats_top_files( 15 ) as $r ) {
		echo '<tr><td>' . esc_html( $r[0] ) . '</td><td><a href="' . esc_url( get_edit_post_link( $r[1] ) ) . '">' . esc_html( get_the_title( $r[1] ) ) . '</a></td><td>' . esc_html( number_format_i18n( $r[2] ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action(
	'admin_menu',
	static function () {
		add_dashboard_page( __( 'Website statistics', 'mcupr' ), __( 'Website statistics', 'mcupr' ), 'edit_posts', 'mcupr-stats', 'mcupr_stats_page' );
	}
);

add_action(
	'wp_dashboard_setup',
	static function () {
		wp_add_dashboard_widget(
			'mcupr_stats',
			__( 'Website statistics', 'mcupr' ),
			static function () {
				echo '<style>' . mcupr_stats_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
				mcupr_stats_summary_cards();
				mcupr_stats_bars( mcupr_stats_series( 14 ) );
				echo '<p><a href="' . esc_url( admin_url( 'index.php?page=mcupr-stats' ) ) . '">' . esc_html__( 'Open the full statistics', 'mcupr' ) . ' →</a></p>';
			}
		);
	}
);
