<?php
/** The visitor statistics card of the footer: masking, robots, the counters, the week, what the card shows.   php php/visitor-counter.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = array( get_option( 'mcupr_vc', null ), get_option( 'start_of_week' ), get_theme_mod( 'mcupr_vc_start', null ), get_transient( 'mcupr_vc_seen' ) );
delete_option( 'mcupr_vc' ); delete_transient( 'mcupr_vc_seen' ); remove_theme_mod( 'mcupr_vc_start' );

// ---- the address is hidden
t( '27.145.*.*' === mcupr_vc_mask_ip( '27.145.8.20' ), 'IPv4: the last two parts are hidden (27.145.*.*)' );
t( '2001:db8:*:*' === mcupr_vc_mask_ip( '2001:0db8:85a3::8a2e:370:7334' ), 'IPv6: only the first two groups are kept' );
t( '' === mcupr_vc_mask_ip( 'not an address' ) && '' === mcupr_vc_mask_ip( '' ), 'a wrong or empty address gives nothing' );

// ---- robots
foreach ( array( 'Googlebot/2.1', 'Mozilla/5.0 (compatible; bingbot/2.0)', 'curl/8.0', 'python-requests/2.31', 'Lighthouse', '' ) as $ua ) { t( mcupr_vc_is_bot( $ua ), 'a robot: "' . $ua . '"' ); }
foreach ( array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36', 'Mozilla/5.0 HeadlessChrome/120' ) as $ua ) { t( ! mcupr_vc_is_bot( $ua ), 'a person: "' . substr( $ua, 0, 40 ) . '…"' ); }

// ---- the counters (Wednesday 28 October 2026, 10:00 site time; the week starts on Monday)
update_option( 'start_of_week', 1 );
$now = (int) ( new DateTimeImmutable( '2026-10-28 10:00:00', wp_timezone() ) )->getTimestamp();
$d   = mcupr_vc_data();
t( 0 === $d['total'] && null === $d['last'], 'a new site starts at nothing' );
$d = mcupr_vc_add( $d, $now - 2 * DAY_IN_SECONDS, '1.1.*.*' );   // Monday 26
$d = mcupr_vc_add( $d, $now - 1 * DAY_IN_SECONDS, '2.2.*.*' );   // Tuesday 27
$d = mcupr_vc_add( $d, $now - 1 * DAY_IN_SECONDS, '3.3.*.*' );
$d = mcupr_vc_add( $d, $now - 3 * DAY_IN_SECONDS, '4.4.*.*' );   // Sunday 25: the week before
$d = mcupr_vc_add( $d, $now, '5.5.*.*' );
t( 5 === $d['total'] && 5 === $d['y']['2026'] && 5 === $d['m']['2026-10'], 'every visit is in the total, the year and the month' );
t( 4 === mcupr_vc_week( $d['d'], $now ), 'the week (Monday to today) has the visits of Monday (1), Tuesday (2) and today (1), not Sunday' );
update_option( 'start_of_week', 0 );
t( 5 === mcupr_vc_week( $d['d'], $now ), 'with the week starting on Sunday, Sunday counts too' );
update_option( 'start_of_week', 1 );
t( '5.5.*.*' === $d['last']['ip'] && '4.4.*.*' === $d['prev']['ip'] && $now === $d['last']['t'], 'the latest visitor is kept, and the one before the latest' );
// old numbers are dropped, the table does not grow
$big = mcupr_vc_data();
for ( $i = 0; $i < 60; $i++ ) { $big = mcupr_vc_add( $big, $now - $i * DAY_IN_SECONDS, '9.9.*.*' ); }
for ( $i = 0; $i < 20; $i++ ) { $big = mcupr_vc_add( $big, $now - $i * 31 * DAY_IN_SECONDS, '9.9.*.*' ); }
t( count( $big['d'] ) <= 40 && count( $big['m'] ) <= 14 && $big['total'] === 80, 'days are kept for 40, months for 14; the total keeps counting (' . count( $big['d'] ) . ' days, ' . count( $big['m'] ) . ' months)' );

// ---- what the card shows
update_option( 'mcupr_vc', $d, false );
set_transient( 'mcupr_vc_seen', array( 'a' => $now - 30, 'b' => $now - 200, 'c' => $now - 400, 'd' => $now - 1000 ), 3600 );
$s = mcupr_vc_snapshot( $now );
t( 2 === $s['online'], 'online now: those seen in the last 5 minutes (2 of 4)' );
t( 1 === $s['today'] && 4 === $s['week'] && 5 === $s['month'] && 5 === $s['year'] && 5 === $s['total'], 'today 1, week 4, month 5, year 5, in all 5' );
set_theme_mod( 'mcupr_vc_start', 1000 );
t( 1005 === mcupr_vc_snapshot( $now )['total'], 'the number to start from is added to the total' );
delete_transient( 'mcupr_vc_seen' );
t( 1 === mcupr_vc_snapshot( $now )['online'], 'whoever reads the card counts as online (never 0)' );

// ---- the card
set_theme_mod( 'mcupr_vc_on', true ); set_theme_mod( 'mcupr_vc_ip', true );
ob_start(); mcupr_vc_card(); $h = ob_get_clean();
t( false !== strpos( $h, 'class="fstat"' ) && false !== strpos( $h, 'aria-labelledby="fstat-h"' ) && 6 === substr_count( $h, '<dt>' ), 'the card has its heading and its six rows' );
t( false !== strpos( $h, '5.5.*.*' ) || false !== strpos( $h, '4.4.*.*' ), 'the address shown is a masked one, never a whole one' );
t( ! preg_match( '/\d+\.\d+\.\d+\.\d+/', $h ), 'no whole address is anywhere in the card' );
set_theme_mod( 'mcupr_vc_ip', false );
ob_start(); mcupr_vc_card(); $h2 = ob_get_clean();
t( false === strpos( $h2, 'IP:' ) && false !== strpos( $h2, '<time' ), 'with the address switched off, the time is still shown' );
set_theme_mod( 'mcupr_vc_on', false );
ob_start(); mcupr_vc_card(); $h3 = ob_get_clean();
t( '' === $h3 && ! mcupr_vc_enabled(), 'switched off: nothing is shown' );
foreach ( array( 'vc_title', 'vc_online', 'vc_today', 'vc_week', 'vc_month', 'vc_year', 'vc_total', 'vc_prev', 'vc_people', 'vc_times' ) as $k ) { t( mcupr_t( $k, 'th' ) !== $k && mcupr_t( $k, 'en' ) !== $k && mcupr_t( $k, 'zh' ) !== mcupr_t( $k, 'en' ) && mcupr_t( $k, 'ja' ) !== mcupr_t( $k, 'en' ), "words in four languages: $k" ); }

remove_theme_mod( 'mcupr_vc_on' ); remove_theme_mod( 'mcupr_vc_ip' );
null === $keep[0] ? delete_option( 'mcupr_vc' ) : update_option( 'mcupr_vc', $keep[0], false );
update_option( 'start_of_week', $keep[1] );
null === $keep[2] ? remove_theme_mod( 'mcupr_vc_start' ) : set_theme_mod( 'mcupr_vc_start', $keep[2] );
false === $keep[3] ? delete_transient( 'mcupr_vc_seen' ) : set_transient( 'mcupr_vc_seen', $keep[3], 3600 );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
