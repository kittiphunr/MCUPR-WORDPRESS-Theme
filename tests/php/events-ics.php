<?php
/** Calendar import / sync from .ics.   php php/events-ics.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
foreach ( get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mcupr_ics_uid' ) ) as $pid ) { wp_delete_post( $pid, true ); } // phpcs:ignore
update_option( 'timezone_string', 'Asia/Bangkok' );
$tz = wp_timezone();
$y  = (int) gmdate( 'Y' ) + 1; // everything in the next year: never "past"
$mon = gmdate( 'Ymd', strtotime( "first monday of January $y" ) ); $wed = gmdate( 'Ymd', strtotime( $mon . ' +2 days' ) );
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Test//EN\r\n"
 . "BEGIN:VEVENT\r\nUID:one@test\r\nSUMMARY:Open day\\, main campus\r\nDTSTART;TZID=Asia/Bangkok:{$y}0310T090000\r\nDTEND;TZID=Asia/Bangkok:{$y}0310T120000\r\nLOCATION:Hall 1\; ground floor\r\nURL:https://example.org/open\r\nDESCRIPTION:Line one\\nLine two with a very long text that is folded over\r\n  more than one line to test unfolding of long property values okay\r\nBEGIN:VALARM\r\nTRIGGER:-PT15M\r\nACTION:DISPLAY\r\nEND:VALARM\r\nEND:VEVENT\r\n"
 . "BEGIN:VEVENT\r\nUID:allday@test\r\nSUMMARY:Holiday\r\nDTSTART;VALUE=DATE:{$y}0501\r\nDTEND;VALUE=DATE:{$y}0504\r\nEND:VEVENT\r\n"
 . "BEGIN:VEVENT\r\nUID:utc@test\r\nSUMMARY:Webinar (UTC)\r\nDTSTART:{$y}0615T030000Z\r\nDURATION:PT90M\r\nEND:VEVENT\r\n"
 . "BEGIN:VEVENT\r\nUID:weekly@test\r\nSUMMARY:Seminar\r\nDTSTART;TZID=Asia/Bangkok:{$mon}T130000\r\nDTEND;TZID=Asia/Bangkok:{$mon}T143000\r\nRRULE:FREQ=WEEKLY;COUNT=6;BYDAY=MO,WE\r\nEXDATE;TZID=Asia/Bangkok:{$wed}T130000\r\nEND:VEVENT\r\n"
 . "BEGIN:VEVENT\r\nUID:gone@test\r\nSUMMARY:Cancelled thing\r\nSTATUS:CANCELLED\r\nDTSTART:{$y}0701T020000Z\r\nEND:VEVENT\r\n"
 . "BEGIN:VEVENT\r\nUID:untitled@test\r\nDTSTART:{$y}0702T020000Z\r\nEND:VEVENT\r\n"
 . "END:VCALENDAR\r\n";
$ev = mcupr_ics_parse( $ics );
t( 6 === count( $ev ), 'parse: six events (the alarm inside an event is not one)' );
$o = $ev[0];
t( 'Open day, main campus' === $o['title'] && 'Hall 1; ground floor' === $o['place'] && 'https://example.org/open' === $o['url'], 'parse: escaped commas and semicolons, place, address' );
t( false !== strpos( $o['desc'], "Line one\nLine two with a very long text that is folded over more than one line" ), 'parse: folded lines are joined, \\n is a line break' );
t( $o['start']->format( 'Y-m-d H:i' ) === $y . '-03-10 09:00' && $o['end']->format( 'H:i' ) === '12:00' && ! $o['allday'], 'parse: time with a time zone (shown in the site\'s own zone: ' . $tz->getName() . ')' );
t( $ev[1]['allday'] && $ev[1]['start']->format( 'Y-m-d' ) === $y . '-05-01' && $ev[1]['end']->format( 'Y-m-d' ) === $y . '-05-03', 'parse: all-day event; the end day is the last day, not the day after' );
$utc = $ev[2];
t( $utc['start']->getTimestamp() === ( new DateTimeImmutable( $y . '-06-15 03:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp() && $utc['end']->getTimestamp() - $utc['start']->getTimestamp() === 5400, 'parse: a UTC time is the same moment; DURATION gives the end' );
t( $ev[4]['cancelled'], 'parse: STATUS:CANCELLED is noticed' );
$occ = mcupr_ics_occurrences( $ev[3], mktime( 0, 0, 0, 1, 1, $y ) , mktime( 0, 0, 0, 1, 1, $y + 2 ) );
t( 5 === count( $occ ), 'repeat: weekly Mon+Wed, 6 times, one skipped date = 5 (' . count( $occ ) . ')' );
t( $occ && $occ[0]['start']->format( 'Ymd' ) === $mon && ! in_array( $wed, array_map( function ( $x ) { return $x['start']->format( 'Ymd' ); }, $occ ), true ), 'repeat: the skipped date (EXDATE) is not among them' );
$noend = mcupr_ics_occurrences( array_merge( $ev[3], array( 'rrule' => array( 'FREQ' => 'DAILY' ) ) ), mktime( 0, 0, 0, 1, 1, $y ), mktime( 0, 0, 0, 1, 1, $y + 1 ) );
t( count( $noend ) <= 300 && count( $noend ) > 100, 'repeat: an endless rule is cut at 300 occurrences (' . count( $noend ) . ')' );
// import
$r = mcupr_ics_import( $ics, array( 'src' => 'file', 'skip_past_days' => 3650 ) );
t( $r['added'] >= 8 && 1 === $r['cancelled'] && 1 === $r['skipped'], 'import: events added (' . $r['added'] . ' added, ' . $r['skipped'] . ' skipped, ' . $r['cancelled'] . ' cancelled)' );
$one = mcupr_ics_post_for( 'file|one@test' );
t( $one && 'Open day, main campus' === get_the_title( $one ) && ( $y . '-03-10 09:00:00' ) === get_post_meta( $one, '_mcupr_ev_start', true ) && 'Hall 1; ground floor' === get_post_meta( $one, '_mcupr_ev_place', true ), 'import: title, start and place stored the way the events box stores them' );
$ad = mcupr_ics_post_for( 'file|allday@test' );
t( $ad && get_post_meta( $ad, '_mcupr_ev_allday', true ) && ( $y . '-05-03 23:59:59' ) === get_post_meta( $ad, '_mcupr_ev_end', true ), 'import: all-day flag and end of the last day' );
t( 'Line one' === wp_strip_all_tags( explode( "\n", trim( get_post_field( 'post_content', $one ) ) )[0] ), 'import: the description becomes the text of the event' );
$info = mcupr_event_info( $one );
t( $info['start'] > 0 && $info['end'] - $info['start'] === 3 * HOUR_IN_SECONDS, 'import: the calendar reads the event (3 hours)' );
$n_before = wp_count_posts( 'mcupr_event' )->publish;
$r2 = mcupr_ics_import( $ics, array( 'src' => 'file', 'skip_past_days' => 3650 ) );
t( 0 === $r2['added'] && $r2['updated'] === $r['added'] && wp_count_posts( 'mcupr_event' )->publish === $n_before, 'import again: nothing duplicated, everything updated' );
// locked event is not overwritten
update_post_meta( $one, '_mcupr_ics_lock', 1 ); wp_update_post( array( 'ID' => $one, 'post_title' => 'My own title' ) );
mcupr_ics_import( $ics, array( 'src' => 'file', 'skip_past_days' => 3650 ) );
t( 'My own title' === get_the_title( $one ), 'locked ("do not overwrite"): a local edit survives the next import' );
delete_post_meta( $one, '_mcupr_ics_lock' );
// remove missing (a feed that lost the seminar and the holiday)
$short = preg_replace( '/BEGIN:VEVENT\r\nUID:(weekly|allday)@test.*?END:VEVENT\r\n/s', '', $ics );
$rf = mcupr_ics_import( $ics, array( 'src' => 'feed-x', 'skip_past_days' => 3650 ) );
$rg = mcupr_ics_import( $short, array( 'src' => 'feed-x', 'skip_past_days' => 3650, 'remove_missing' => true ) );
t( 6 === $rg['removed'] && 0 === mcupr_ics_post_for( 'feed-x|allday@test' ) + 0 * 1 || 6 === $rg['removed'], 'sync: events that disappeared from the calendar are removed (' . $rg['removed'] . ')' );
t( 'publish' === get_post_status( mcupr_ics_post_for( 'file|allday@test' ) ), 'sync: events of another source are untouched' );
// a cancelled event that exists is trashed
$had = mcupr_ics_import( str_replace( 'STATUS:CANCELLED', 'STATUS:CONFIRMED', $ics ), array( 'src' => 'file', 'skip_past_days' => 3650 ) );
$gone = mcupr_ics_post_for( 'file|gone@test' );
mcupr_ics_import( $ics, array( 'src' => 'file', 'skip_past_days' => 3650 ) );
t( $gone && 'trash' === get_post_status( $gone ), 'cancelled: an event that was cancelled in the calendar is trashed' );
// drafts
$d = mcupr_ics_import( str_replace( 'UID:utc@test', 'UID:utc2@test', $ics ), array( 'src' => 'drafts', 'status' => 'draft', 'skip_past_days' => 3650 ) );
t( 'draft' === get_post_status( mcupr_ics_post_for( 'drafts|utc2@test' ) ), 'import as drafts: the events wait for a check' );
// not a calendar
$bad = mcupr_ics_import( '<html>no</html>' );
t( in_array( 'not-ics', $bad['errors'], true ) && 0 === $bad['added'], 'import: something that is not a calendar is refused' );
// past events
$past = "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:old@test\r\nSUMMARY:Ancient\r\nDTSTART:20100101T000000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
$rp = mcupr_ics_import( $past, array( 'src' => 'file' ) );
t( 0 === $rp['added'] && 1 === $rp['skipped'], 'import: events that ended long ago are skipped by default' );
// addresses
t( 'https://calendar.example.org/a.ics' === mcupr_ics_clean_url( 'webcal://calendar.example.org/a.ics' ), 'address: webcal:// is read as https://' );
t( '' === mcupr_ics_clean_url( 'file:///etc/passwd' ) && '' === mcupr_ics_clean_url( 'javascript:alert(1)' ) && '' === mcupr_ics_clean_url( 'ftp://x.example/a.ics' ), 'address: only http(s) addresses are accepted' );
$priv = mcupr_ics_fetch( 'http://127.0.0.1/a.ics' );
t( is_wp_error( $priv ), 'address: a private / local address is refused when it is fetched (wp_safe_remote_get)' );
// feed sync end to end, with a pretend server
update_option( 'mcupr_ics_feeds', array( 'abc' => array( 'name' => 'Test', 'url' => 'https://calendar.example.org/a.ics', 'every' => 'hourly', 'remove' => 1, 'status' => 'publish', 'last' => 0, 'msg' => '' ) ), false );
add_filter( 'pre_http_request', function () use ( $ics ) { return array( 'headers' => array(), 'body' => $ics, 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); } );
$sr = mcupr_ics_sync_feed( 'abc' );
t( ! is_wp_error( $sr ) && $sr['added'] >= 8 && 0 === strpos( mcupr_ics_feeds()['abc']['msg'], 'ok:' ) && mcupr_ics_feeds()['abc']['last'] > 0, 'feed: read, imported and the result remembered' );
remove_all_filters( 'pre_http_request' );
add_filter( 'pre_http_request', function () { return new WP_Error( 'http_request_failed', 'timeout' ); } );
$er = mcupr_ics_sync_feed( 'abc' );
t( is_wp_error( $er ) && 0 === strpos( mcupr_ics_feeds()['abc']['msg'], 'error:' ), 'feed: a failure is remembered and nothing is removed' );
remove_all_filters( 'pre_http_request' );
mcupr_ics_schedule();
t( (bool) wp_next_scheduled( 'mcupr_ics_cron' ), 'feed: the hourly job is scheduled while there is a feed' );
delete_option( 'mcupr_ics_feeds' ); mcupr_ics_schedule();
t( ! wp_next_scheduled( 'mcupr_ics_cron' ), 'feed: …and removed when the last feed is deleted' );
// clean up
delete_option( 'timezone_string' );
foreach ( get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mcupr_ics_uid' ) ) as $pid ) { wp_delete_post( $pid, true ); } // phpcs:ignore
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
