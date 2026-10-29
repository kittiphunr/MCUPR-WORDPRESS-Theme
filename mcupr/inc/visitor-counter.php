<?php
/**
 * Visitor statistics card in the footer: who is online now, visits today / this week / this month / this year / in all, and
 * the visitor before the latest one (the IP with its last two parts hidden, and the time).
 *
 * Kept simple and private:
 *   • a visit is one visitor in a stretch of 30 minutes (not every page they open); "online now" is anybody seen in the last 5 minutes;
 *   • no cookie and nothing in the visitor's browser: a visitor is told apart for those 30 minutes by a salted hash of the address and
 *     the browser name, kept on the server only that long; the full address is never saved, only its masked form for the last visitor;
 *   • robots, people who edit the site, previews, the Customizer, feeds, searches typed ahead (live search) and prefetching are not counted;
 *   • the numbers are small counters (total, per year, per month for 14 months, per day for 40 days), so the table does not grow.
 * Customizer → MCUPR: Footer: show the card, show the last visitor's address, a number to start the total from (to carry on from an old counter).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_VC_VISIT_SECONDS  = 1800;
const MCUPR_VC_ONLINE_SECONDS = 300;

/** A date in the time zone of the site, always with the Western year (the keys of the counters must not change with the language of the site). */
function mcupr_vc_date( $format, $ts ) {
	return ( new DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( wp_timezone() )->format( $format );
}

function mcupr_vc_enabled() {
	return (bool) get_theme_mod( 'mcupr_vc_on', true );
}

function mcupr_vc_data() {
	$d = get_option( 'mcupr_vc', array() );
	$d = is_array( $d ) ? $d : array();
	return array(
		'total' => isset( $d['total'] ) ? (int) $d['total'] : 0,
		'y'     => isset( $d['y'] ) && is_array( $d['y'] ) ? $d['y'] : array(),
		'm'     => isset( $d['m'] ) && is_array( $d['m'] ) ? $d['m'] : array(),
		'd'     => isset( $d['d'] ) && is_array( $d['d'] ) ? $d['d'] : array(),
		'last'  => isset( $d['last'] ) && is_array( $d['last'] ) ? $d['last'] : null,
		'prev'  => isset( $d['prev'] ) && is_array( $d['prev'] ) ? $d['prev'] : null,
	);
}

/** A robot, a script or a link checker (by the browser name it gives). */
function mcupr_vc_is_bot( $ua ) {
	$ua = (string) $ua;
	return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|curl|wget|python|java\/|go-http|libwww|httpclient|monitor|uptime|lighthouse|pingdom|facebookexternalhit|preview|scanner|headless(?!chrome)/i', $ua );
}

/** The address with its last part(s) hidden: 27.145.*.* or 2001:db8:*:*. */
function mcupr_vc_mask_ip( $ip ) {
	$ip = (string) $ip;
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$p = explode( '.', $ip );
		return $p[0] . '.' . $p[1] . '.*.*';
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$full = strtolower( (string) inet_ntop( (string) inet_pton( $ip ) ) );
		$p    = explode( ':', $full );
		return ( isset( $p[0] ) ? $p[0] : '' ) . ':' . ( isset( $p[1] ) && '' !== $p[1] ? $p[1] : '0' ) . ':*:*';
	}
	return '';
}

/** Add one visit to the counters (pure on its inputs: $now is a timestamp; returns the new data). */
function mcupr_vc_add( $d, $now, $masked ) {
	$y = mcupr_vc_date( 'Y', $now );
	$m = mcupr_vc_date( 'Y-m', $now );
	$day = mcupr_vc_date( 'Y-m-d', $now );
	$d['total']++;
	$d['y'][ $y ]     = ( isset( $d['y'][ $y ] ) ? (int) $d['y'][ $y ] : 0 ) + 1;
	$d['m'][ $m ]     = ( isset( $d['m'][ $m ] ) ? (int) $d['m'][ $m ] : 0 ) + 1;
	$d['d'][ $day ]   = ( isset( $d['d'][ $day ] ) ? (int) $d['d'][ $day ] : 0 ) + 1;
	krsort( $d['m'] );
	krsort( $d['d'] );
	$d['m']    = array_slice( $d['m'], 0, 14, true );
	$d['d']    = array_slice( $d['d'], 0, 40, true );
	$d['prev'] = $d['last'];
	$d['last'] = array( 'ip' => $masked, 't' => (int) $now );
	return $d;
}

/** Visits this week (the week starts on the day set in Settings → General), from the days kept. */
function mcupr_vc_week( $days, $now ) {
	$start = (int) get_option( 'start_of_week', 1 );
	$dow   = (int) mcupr_vc_date( 'w', $now );
	$back  = ( $dow - $start + 7 ) % 7;
	$sum   = 0;
	for ( $i = 0; $i <= $back; $i++ ) {
		$k    = mcupr_vc_date( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
		$sum += isset( $days[ $k ] ) ? (int) $days[ $k ] : 0;
	}
	return $sum;
}

/** What the card shows. */
function mcupr_vc_snapshot( $now = 0 ) {
	$now  = $now ? (int) $now : time();
	$d    = mcupr_vc_data();
	$seen = get_transient( 'mcupr_vc_seen' );
	$on   = 0;
	foreach ( is_array( $seen ) ? $seen : array() as $t ) {
		if ( $now - (int) $t <= MCUPR_VC_ONLINE_SECONDS ) {
			$on++;
		}
	}
	$start = max( 0, (int) get_theme_mod( 'mcupr_vc_start', 0 ) );
	return array(
		'online' => max( 1, $on ), // whoever reads the card is online
		'today'  => isset( $d['d'][ mcupr_vc_date( 'Y-m-d', $now ) ] ) ? (int) $d['d'][ mcupr_vc_date( 'Y-m-d', $now ) ] : 0,
		'week'   => mcupr_vc_week( $d['d'], $now ),
		'month'  => isset( $d['m'][ mcupr_vc_date( 'Y-m', $now ) ] ) ? (int) $d['m'][ mcupr_vc_date( 'Y-m', $now ) ] : 0,
		'year'   => isset( $d['y'][ mcupr_vc_date( 'Y', $now ) ] ) ? (int) $d['y'][ mcupr_vc_date( 'Y', $now ) ] : 0,
		'total'  => $start + $d['total'],
		'prev'   => $d['prev'],
	);
}

/** Count the visitor of this request. */
function mcupr_vc_track() {
	if ( ! mcupr_vc_enabled() || is_admin() || is_feed() || is_robots() || is_embed() || is_customize_preview() || is_preview() || is_404() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'GET' !== $method || isset( $_GET['mcupr_live'] ) || ( isset( $_SERVER['HTTP_PURPOSE'] ) && 'prefetch' === strtolower( (string) $_SERVER['HTTP_PURPOSE'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) || ! apply_filters( 'mcupr_vc_count', true ) ) {
		return;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( mcupr_vc_is_bot( $ua ) ) {
		return;
	}
	$ip = function_exists( 'mcupr_client_ip' ) ? mcupr_client_ip() : '';
	$id = substr( wp_hash( $ip . '|' . $ua, 'auth' ), 0, 16 );
	$now  = time();
	$seen = get_transient( 'mcupr_vc_seen' );
	$seen = is_array( $seen ) ? $seen : array();
	$new  = ! isset( $seen[ $id ] ) || $now - (int) $seen[ $id ] > MCUPR_VC_VISIT_SECONDS;
	if ( ! $new && $now - (int) $seen[ $id ] < 60 ) {
		return; // seen a moment ago: no need to write again
	}
	$seen[ $id ] = $now;
	foreach ( $seen as $k => $t ) { // forget anybody not seen for a whole visit
		if ( $now - (int) $t > MCUPR_VC_VISIT_SECONDS ) {
			unset( $seen[ $k ] );
		}
	}
	set_transient( 'mcupr_vc_seen', $seen, MCUPR_VC_VISIT_SECONDS * 2 );
	if ( $new ) {
		update_option( 'mcupr_vc', mcupr_vc_add( mcupr_vc_data(), $now, mcupr_vc_mask_ip( $ip ) ), false );
	}
}
add_action( 'template_redirect', 'mcupr_vc_track', 25 );

/** The sizes of the card, like the widgets of a phone: the whole card, a square of four numbers, one small line. */
function mcupr_vc_sizes() {
	return array( 'full', 'medium', 'small' );
}

function mcupr_vc_size() {
	$s = (string) get_theme_mod( 'mcupr_vc_size', 'full' );
	return in_array( $s, mcupr_vc_sizes(), true ) ? $s : 'full';
}

/**
 * The card (a panel in the colours of the theme), under the contact details. $size: the size the owner chose in the Customizer;
 * a visitor can shrink it to the small line and open it again with the button (kept in the browser, see assets/js/visitor-card.js).
 */
function mcupr_vc_card() {
	if ( ! mcupr_vc_enabled() ) {
		return;
	}
	$s    = mcupr_vc_snapshot();
	$size = mcupr_vc_size();
	$rows = array(
		array( 'online', 'vc_online', $s['online'], 'vc_people' ),
		array( 'today', 'vc_today', $s['today'], 'vc_times' ),
		array( 'week', 'vc_week', $s['week'], 'vc_times' ),
		array( 'month', 'vc_month', $s['month'], 'vc_times' ),
		array( 'year', 'vc_year', $s['year'], 'vc_times' ),
		array( 'total', 'vc_total', $s['total'], 'vc_times' ),
	);
	$can  = (bool) get_theme_mod( 'mcupr_vc_toggle', true );
	echo '<section class="fstat is-' . esc_attr( $size ) . '" id="fstat" data-size="' . esc_attr( $size ) . '" data-full="' . esc_attr( 'small' === $size ? 'full' : $size ) . '" aria-labelledby="fstat-h"><div class="fstat-top"><h2 class="fstat-h" id="fstat-h">' . mcupr_icon( 'trending-up', 20 ) . '<span>' . esc_html( mcupr_t( 'vc_title' ) ) . '</span></h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $can ) {
		echo '<button type="button" class="fstat-tg" hidden aria-controls="fstat" aria-pressed="' . ( 'small' === $size ? 'true' : 'false' ) . '" data-t-small="' . esc_attr( mcupr_t( 'vc_shrink' ) ) . '" data-t-full="' . esc_attr( mcupr_t( 'vc_expand' ) ) . '" aria-label="' . esc_attr( mcupr_t( 'small' === $size ? 'vc_expand' : 'vc_shrink' ) ) . '" title="' . esc_attr( mcupr_t( 'small' === $size ? 'vc_expand' : 'vc_shrink' ) ) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path class="fstat-tg-in" d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/><path class="fstat-tg-out" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></button>';
	}
	echo '</div><dl class="fstat-l">';
	foreach ( $rows as $r ) {
		echo '<div data-k="' . esc_attr( $r[0] ) . '"><dt>' . esc_html( mcupr_t( $r[1] ) ) . '</dt><dd><b>' . esc_html( number_format_i18n( $r[2] ) ) . '</b> <span>' . esc_html( mcupr_t( $r[3] ) ) . '</span></dd></div>';
	}
	echo '</dl>';
	if ( ! empty( $s['prev'] ) ) {
		$show_ip = (bool) get_theme_mod( 'mcupr_vc_ip', true ) && ! empty( $s['prev']['ip'] );
		echo '<div class="fstat-p"><p class="fstat-pl">' . esc_html( mcupr_t( 'vc_prev' ) ) . '</p>';
		if ( $show_ip ) {
			echo '<p>' . mcupr_icon( 'globe', 16 ) . 'IP: <span dir="ltr">' . esc_html( $s['prev']['ip'] ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<p>' . mcupr_icon( 'clock', 16 ) . '<time datetime="' . esc_attr( mcupr_vc_date( 'c', (int) $s['prev']['t'] ) ) . '">' . esc_html( mcupr_vc_date( 'd/m/Y H:i', (int) $s['prev']['t'] ) . mcupr_t( 'vc_clock' ) ) . '</time></p></div>';
	}
	echo '</section>';
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( mcupr_vc_enabled() && get_theme_mod( 'mcupr_vc_toggle', true ) ) {
			wp_enqueue_script( 'mcupr-visitor-card', get_theme_file_uri( 'assets/js/visitor-card.js' ), array(), MCUPR_VERSION, true );
		}
	}
);

/* Customizer: show the card, the last visitor's address, where the total starts. */
add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_setting( 'mcupr_vc_on', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_vc_on', array( 'section' => 'mcupr_org', 'type' => 'checkbox', 'label' => __( 'Show visitor statistics in the footer', 'mcupr' ), 'description' => __( 'Online now, visits today, this week, this month, this year and in all. No cookie is used; a visit is one visitor in 30 minutes; robots and people who edit the site are not counted.', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_vc_ip', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_vc_ip', array( 'section' => 'mcupr_org', 'type' => 'checkbox', 'label' => __( 'Statistics: show the address of the previous visitor', 'mcupr' ), 'description' => __( 'Only the first two parts are shown (27.145.*.*); the full address is never stored. Untick it to show the time only.', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_vc_size', array( 'default' => 'full', 'sanitize_callback' => static function ( $v ) { return in_array( $v, mcupr_vc_sizes(), true ) ? $v : 'full'; } ) );
		$wp_customize->add_control( 'mcupr_vc_size', array( 'section' => 'mcupr_org', 'type' => 'select', 'label' => __( 'Statistics: size of the card', 'mcupr' ), 'choices' => array( 'full' => __( 'Large – every number and the previous visitor', 'mcupr' ), 'medium' => __( 'Medium – four numbers in a square', 'mcupr' ), 'small' => __( 'Small – one line', 'mcupr' ) ) ) );
		$wp_customize->add_setting( 'mcupr_vc_toggle', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_vc_toggle', array( 'section' => 'mcupr_org', 'type' => 'checkbox', 'label' => __( 'Statistics: let visitors shrink the card', 'mcupr' ), 'description' => __( 'A small button on the card makes it one line and opens it again; the choice stays in the visitor’s browser.', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_vc_start', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( 'mcupr_vc_start', array( 'section' => 'mcupr_org', 'type' => 'number', 'label' => __( 'Statistics: start the total from', 'mcupr' ), 'description' => __( 'To carry on from an old counter, type its last number. It is added to the visits counted from now on.', 'mcupr' ), 'input_attrs' => array( 'min' => 0, 'step' => 1 ) ) );
	},
	11
);
