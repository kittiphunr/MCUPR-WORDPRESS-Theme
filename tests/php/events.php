<?php
/**
 * Events calendar (inc/events.php) against the real WordPress: saving, queries, month grid, .ics, Google link, JSON-LD.
 *   php php/events.php .site-starter
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';

$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

t( post_type_exists( 'mcupr_event' ), 'post type mcupr_event is registered' );
t( in_array( 'mcupr_event', mcupr_translatable_post_types(), true ), 'events are translatable (EN / 中文 / 日本語 fields)' );
wp_set_current_user( 1 );
foreach ( get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $old ) {
	if ( strpos( get_post_meta( $old, '_mcupr_ev_start', true ), '2031-' ) === 0 || '' === get_post_meta( $old, '_mcupr_ev_start', true ) || 'Draft' === get_the_title( $old ) || 'Bad input' === get_the_title( $old ) ) {
		wp_delete_post( $old, true ); // left by an earlier run
	}
}

// ---- saving through the meta box
function mk_event( $title, $in ) {
	$id = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_title' => wp_slash( $title ), 'post_status' => 'publish' ) );
	$_POST = array( 'mcupr_event_nonce' => wp_create_nonce( 'mcupr_event_save' ), 'mcupr_ev' => $in );
	mcupr_event_save( $id );
	$_POST = array();
	return $id;
}
$a = mk_event( 'Timed, with; special\\chars', array( 'start_date' => '2031-03-05', 'start_time' => '09:30', 'end_date' => '', 'end_time' => '11:00', 'place' => 'Hall A', 'url' => 'https://example.org/reg' ) );
t( '2031-03-05 09:30:00' === get_post_meta( $a, '_mcupr_ev_start', true ) && '2031-03-05 11:00:00' === get_post_meta( $a, '_mcupr_ev_end', true ), 'timed event: start / end stored, end date defaults to the start date' );
$b = mk_event( 'Three days', array( 'start_date' => '2031-03-10', 'end_date' => '2031-03-12', 'allday' => '1' ) );
t( '2031-03-10 00:00:00' === get_post_meta( $b, '_mcupr_ev_start', true ) && '2031-03-12 23:59:59' === get_post_meta( $b, '_mcupr_ev_end', true ) && get_post_meta( $b, '_mcupr_ev_allday', true ), 'all-day range stored (00:00 → 23:59:59)' );
$c = mk_event( 'Bad input', array( 'start_date' => '2031-13-45', 'start_time' => '99:99', 'end_date' => '2000-01-01', 'place' => '<b>x</b>', 'url' => 'javascript:alert(1)' ) );
t( preg_match( '/^\d{4}-\d{2}-\d{2} 09:00:00$/', get_post_meta( $c, '_mcupr_ev_start', true ) ), 'invalid date / time fall back to today 09:00' );
t( get_post_meta( $c, '_mcupr_ev_end', true ) >= get_post_meta( $c, '_mcupr_ev_start', true ), 'end is never before start' );
t( false === strpos( (string) get_post_meta( $c, '_mcupr_ev_place', true ), '<' ) && '' === (string) get_post_meta( $c, '_mcupr_ev_url', true ), 'place is sanitised, javascript: URL rejected' );
$_POST = array( 'mcupr_ev' => array( 'start_date' => '2031-04-04' ) );
mcupr_event_save( $a );
t( '2031-03-05 09:30:00' === get_post_meta( $a, '_mcupr_ev_start', true ), 'no nonce → nothing saved' );
$_POST = array();
wp_set_current_user( 0 );
$_POST = array( 'mcupr_event_nonce' => wp_create_nonce( 'mcupr_event_save' ), 'mcupr_ev' => array( 'start_date' => '2031-04-04' ) );
mcupr_event_save( $a );
t( '2031-03-05 09:30:00' === get_post_meta( $a, '_mcupr_ev_start', true ), 'a visitor cannot save event data' );
$_POST = array();
wp_set_current_user( 1 );

ob_start(); mcupr_event_box( get_post( $a ) ); $box = ob_get_clean();
t( strpos( $box, 'name="mcupr_ev[start_date]"' ) !== false && strpos( $box, 'value="2031-03-05"' ) !== false && strpos( $box, 'Hall A' ) !== false, 'meta box shows the saved values' );

// ---- queries
$ts = function ( $s ) { return mcupr_event_ts( $s ); };
$mar = wp_list_pluck( mcupr_events_between( $ts( '2031-03-01 00:00:00' ), $ts( '2031-03-31 23:59:59' ) ), 'ID' );
t( in_array( $a, $mar, true ) && in_array( $b, $mar, true ), 'events in March 2031 are found' );
t( ! in_array( $a, wp_list_pluck( mcupr_events_between( $ts( '2031-04-01 00:00:00' ), $ts( '2031-04-30 23:59:59' ) ), 'ID' ), true ), 'not found in April' );
t( in_array( $b, wp_list_pluck( mcupr_events_between( $ts( '2031-03-12 12:00:00' ), $ts( '2031-03-12 13:00:00' ) ), 'ID' ), true ), 'a multi-day event is found on its last day' );
t( $mar[0] === $a, 'results are ordered by start date' );
$draft = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_title' => 'Draft', 'post_status' => 'draft' ) );
update_post_meta( $draft, '_mcupr_ev_start', '2031-03-06 10:00:00' ); update_post_meta( $draft, '_mcupr_ev_end', '2031-03-06 11:00:00' );
t( ! in_array( $draft, wp_list_pluck( mcupr_events_between( $ts( '2031-03-01 00:00:00' ), $ts( '2031-03-31 23:59:59' ) ), 'ID' ), true ), 'drafts never show' );
t( count( mcupr_events_upcoming( 20 ) ) >= 2, 'upcoming list includes the future events' );

// ---- month grid (March 2031 starts on a Saturday)
$_GET['ev_m'] = '2031-03';
$html = mcupr_calendar_html();
t( strpos( $html, '<caption' ) === false && strpos( $html, 'aria-labelledby="cal-title"' ) !== false, 'table is named by its heading' );
t( substr_count( $html, '<th scope="col">' ) === 7, '7 column headers with scope' );
t( substr_count( $html, '<td class="cal-d' ) === 31, '31 day cells' );
t( preg_match( '/<td class="cal-out"><\/td>(<td class="cal-out"><\/td>){5}<td class="cal-d/', $html ), 'first day is placed under Saturday (5 empty cells before it)' );
t( substr_count( $html, 'Three days' ) >= 3, 'the 3-day event is listed on each of its days' );
t( strpos( $html, 'ev_m=2031-02' ) !== false && strpos( $html, 'ev_m=2031-04' ) !== false, 'previous / next month links' );
$_GET['ev_m'] = "2031-03'\"><script>";
t( strpos( mcupr_calendar_html(), '<script>' ) === false, 'a malicious ev_m is ignored' );
$_GET['ev_m'] = '1500-01';
t( mcupr_cal_month()[0] !== 1500, 'out-of-range year is ignored' );
$_GET = array();

// ---- text
$i = mcupr_event_info( $a );
t( strpos( mcupr_event_when( $i ), '2574' ) !== false || strpos( mcupr_event_when( $i ), '2031' ) !== false, 'date text: ' . mcupr_event_when( $i ) );
t( 'th' !== mcupr_ui_lang() || strpos( mcupr_event_when( $i ), '2574' ) !== false, 'Thai UI shows the Buddhist year' );

// ---- ics + Google
$ics = mcupr_ics_vevent( $a );
t( strpos( $ics, "BEGIN:VEVENT\r\n" ) === 0 && strpos( $ics, 'DTSTART:2031030' ) !== false && strpos( $ics, 'SUMMARY:Timed\\, with\; special\\\\chars' ) !== false, 'ics: VEVENT with escaped summary' );
$long = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_title' => str_repeat( 'กิจกรรมยาวมาก', 12 ), 'post_status' => 'publish' ) );
$lines = explode( "\r\n", rtrim( mcupr_ics_vevent( $long ), "\r\n" ) );
t( max( array_map( 'strlen', $lines ) ) <= 75, 'ics: lines folded at 75 octets' );
t( preg_match( '//u', mcupr_ics_vevent( $long ) ) === 1, 'ics: folding never splits a UTF-8 character' );
t( strpos( mcupr_ics_vevent( $b ), 'DTSTART;VALUE=DATE:20310310' ) !== false && strpos( mcupr_ics_vevent( $b ), 'DTEND;VALUE=DATE:20310313' ) !== false, 'ics: all-day event ends the day after (exclusive)' );
$g = mcupr_event_google_url( $a );
t( strpos( $g, "https://calendar.google.com/calendar/render?" ) === 0 && strpos( $g, 'action=TEMPLATE' ) !== false && strpos( $g, 'Hall%20A' ) !== false && strpos( $g, 'with%3B' ) !== false && strpos( $g, ' ' ) === false, 'Google Calendar link has title, dates and place' );

// ---- JSON-LD
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $a, 'post_type' => 'mcupr_event' ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
ob_start(); mcupr_event_jsonld(); $ld = ob_get_clean();
$j = json_decode( trim( preg_replace( '/<\/?script[^>]*>/', '', $ld ) ), true );
t( $j && 'Event' === $j['@type'] && '2031-03-05T09:30:00' === substr( $j['startDate'], 0, 19 ) && 'Hall A' === $j['location']['name'], 'JSON-LD: schema.org/Event with start date and place' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
