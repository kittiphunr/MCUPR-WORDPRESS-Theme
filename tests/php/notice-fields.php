<?php
/** The procurement fields of a notice: number, method, budget, closing date, status, winner, price – and what is shown and filtered from them.   php php/notice-fields.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );
$day = static function ( $n ) { return ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( ( $n >= 0 ? '+' : '' ) . $n . ' days' )->format( 'Y-m-d' ); }; // Gregorian, as the date field stores it

// 0. the calendar: the theme prints years in the Buddhist era (wp_date), the stored dates and "today" are Gregorian
$year = (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y' );
t( mcupr_notice_today() === ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ) && 0 === strpos( mcupr_notice_today(), (string) $year ), 'calendar: "today" is the Gregorian date, even where wp_date() gives the Buddhist year (' . wp_date( 'Y' ) . ' vs ' . $year . ')' );

// 1. money
t( 1250000.5 === mcupr_notice_money( '1,250,000.50' ) && 3000.0 === mcupr_notice_money( ' 3 000 ' ) && 0.0 === mcupr_notice_money( '0' ), 'money: commas and spaces are taken away' );
$bad = array( 'abc', '', '12.345', '-5', '1234567890123', '1e5', '5 baht', '๑๐๐' );
$ok = true; foreach ( $bad as $b ) { if ( null !== mcupr_notice_money( $b ) ) { $ok = false; } }
t( $ok, 'money: text, a minus sign, more than two decimals, an exponent and a huge number are refused' );
t( '4,500,000' === mcupr_notice_money_text( 4500000.0 ) && '4,500,000.50' === mcupr_notice_money_text( 4500000.5 ), 'money: shown with thousands separators, decimals only when there are any' );

// 2. the status a visitor sees
$mk = static function ( $title, $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'post_title' => 'nf-test ' . $title, 'post_content' => 'x' ) );
	foreach ( $meta as $k => $v ) { update_post_meta( $id, '_mcupr_nt_' . $k, $v ); }
	return $id;
};
$ids = array(
	'none'      => $mk( 'none' ),
	'future'    => $mk( 'future', array( 'deadline' => $day( 10 ) ) ),
	'today'     => $mk( 'today', array( 'deadline' => $day( 0 ) ) ),
	'past'      => $mk( 'past', array( 'deadline' => $day( -1 ) ) ),
	'forced-open' => $mk( 'forced-open', array( 'deadline' => $day( -5 ), 'status' => 'open' ) ),
	'forced-closed' => $mk( 'forced-closed', array( 'deadline' => $day( 5 ), 'status' => 'closed' ) ),
	'awarded'   => $mk( 'awarded', array( 'status' => 'awarded', 'winner' => 'บริษัท <b>ก</b> จำกัด', 'price' => '1985000', 'budget' => '2100000', 'no' => '12/2570', 'method' => 'e-bidding' ) ),
	'cancelled' => $mk( 'cancelled', array( 'status' => 'cancelled', 'deadline' => $day( 4 ) ) ),
	'junk'      => $mk( 'junk', array( 'status' => 'nonsense', 'deadline' => '2026-13-45', 'budget' => 'lots' ) ),
);
$want = array( 'none' => '', 'future' => 'open', 'today' => 'open', 'past' => 'closed', 'forced-open' => 'open', 'forced-closed' => 'closed', 'awarded' => 'awarded', 'cancelled' => 'cancelled', 'junk' => '' );
$ok = true; $why = '';
foreach ( $want as $k => $w ) { if ( mcupr_notice_state( $ids[ $k ] ) !== $w ) { $ok = false; $why .= " $k=" . mcupr_notice_state( $ids[ $k ] ); } }
t( $ok, 'state: no deadline = none; a deadline = open until the end of that day, then closed; a status chosen by hand wins' . $why );
$i = mcupr_notice_info( $ids['junk'] );
t( '' === $i['status'] && '' === $i['deadline'] && null === $i['budget'], 'state: an unknown status, an impossible date and a budget that is not a number are ignored' );
$gdl = mcupr_notice_deadline_html( $ids['future'] );
t( false !== strpos( $gdl, mcupr_date_text( ( new DateTimeImmutable( $day( 10 ) . ' 12:00:00', wp_timezone() ) )->getTimestamp(), 'short' ) ) && 'open' === mcupr_notice_state( $ids['future'] ), 'calendar: a deadline ten days away is open and is printed with the right year' );
t( 10 === mcupr_notice_days_left( $ids['future'] ) && 0 === mcupr_notice_days_left( $ids['today'] ) && -1 === mcupr_notice_days_left( $ids['past'] ) && null === mcupr_notice_days_left( $ids['none'] ), 'days: counted from today (0 = today, negative = passed, none without a deadline)' );

// 3. what is shown
$chips = array();
foreach ( mcupr_notice_statuses() as $s ) { $chips[ $s ] = mcupr_notice_status_chip( $s ); }
$ok = true; foreach ( $chips as $s => $c ) { if ( false === strpos( $c, '<svg' ) || false === strpos( $c, 'nt-st-' . $s ) || false === strpos( $c, mcupr_t( 'nt_st_' . $s ) ) ) { $ok = false; } }
t( $ok && '' === mcupr_notice_status_chip( '' ) && '' === mcupr_notice_status_chip( 'evil' ), 'chip: each status has an icon and its words (not colour alone); none and an unknown one give nothing' );
$h = mcupr_notice_deadline_html( $ids['future'] );
t( false !== strpos( $h, 'datetime="' . $day( 10 ) . '"' ) && false !== strpos( $h, mcupr_t( 'nt_deadline' ) ) && false === strpos( $h, 'nt-soon' ), 'deadline: the date as <time> with its label; ten days away is not "soon"' );
add_filter( 'mcupr_notice_new_days', '__return_zero' );
$soon = $mk( 'soon', array( 'deadline' => $day( 3 ) ) );
t( false !== strpos( mcupr_notice_deadline_html( $soon ), 'nt-soon' ) && false !== strpos( mcupr_notice_deadline_html( $soon ), '3' ), 'deadline: within a week while open it says "closing soon" with the days left' );
t( false !== strpos( mcupr_notice_deadline_html( $ids['today'] ), mcupr_t( 'nt_today' ) ), 'deadline: on the day it says "closes today"' );
t( false === strpos( mcupr_notice_deadline_html( $ids['past'] ), 'nt-soon' ) && false === strpos( mcupr_notice_deadline_html( $ids['forced-closed'] ), 'nt-soon' ), 'deadline: a closed notice never says "soon"' );
t( false === strpos( mcupr_notice_deadline_html( $ids['future'], false ), mcupr_t( 'nt_deadline' ) . ' ' ), 'deadline: the label can be left out (the notice page has its own)' );
remove_all_filters( 'mcupr_notice_new_days' );
$f = mcupr_notice_facts_html( $ids['awarded'] );
t( false !== strpos( $f, '12/2570' ) && false !== strpos( $f, 'e-bidding' ) && false !== strpos( $f, '2,100,000' ) && '' === mcupr_notice_facts_html( $ids['none'] ), 'facts: number, method and budget in one line; nothing when empty' );
$rows = mcupr_notice_fact_rows( $ids['awarded'] );
t( isset( $rows[ mcupr_t( 'nt_winner' ) ], $rows[ mcupr_t( 'nt_price' ) ], $rows[ mcupr_t( 'nt_status' ) ] ) && false !== strpos( $rows[ mcupr_t( 'nt_price' ) ], '1,985,000' ) && false === strpos( $rows[ mcupr_t( 'nt_winner' ) ], '<b>' ), 'rows: a result notice shows its winner (as text) and the agreed price' );
t( ! isset( mcupr_notice_fact_rows( $ids['future'] )[ mcupr_t( 'nt_winner' ) ] ) && array() === mcupr_notice_fact_rows( $ids['none'] ), 'rows: an open notice has no winner row; an empty one has no rows' );
$row = mcupr_notice_row( get_post( $ids['awarded'] ), 1 );
t( false !== strpos( $row, 'nt-facts' ) && false !== strpos( $row, 'nt-st-awarded' ), 'table: the row carries the facts line and the status chip' );

// 4. the list filter: every notice is in exactly the lists its status says
$all = array_merge( array_values( $ids ), array( $soon ) );
$ok = true; $why = '';
foreach ( mcupr_notice_statuses() as $s ) {
	$got = get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => mcupr_notice_state_query( $s ), 'post__in' => $all ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $all as $id ) {
		if ( in_array( $id, $got, true ) !== ( mcupr_notice_state( $id ) === $s ) ) { $ok = false; $why .= " $s/" . get_the_title( $id ); }
	}
}
t( $ok, 'filter: the query of each status returns exactly the notices whose status is that one (including the automatic ones)' . $why );
$_GET['ns'] = 'open'; t( 'open' === mcupr_notice_filter_state(), 'filter: ?ns=open is read' );
$_GET['ns'] = "open' OR 1=1"; t( '' === mcupr_notice_filter_state(), 'filter: anything else is nothing' );
$_GET['ns'] = 'closed'; $_GET['nq'] = 'งบ ประมาณ';
$pill = mcupr_notice_filter_html( home_url( '/notices/' ) );
t( 5 === substr_count( $pill, '<li class="contents">' ) && 1 === substr_count( $pill, 'aria-current="page"' ) && false !== strpos( $pill, 'ns=closed' ) && false !== strpos( $pill, 'nq=' ), 'filter: five pills, the chosen one marked, the search words kept in the links' );
unset( $_GET['ns'], $_GET['nq'] );

// 5. saving
$id = $mk( 'save' );
$post = function ( $a ) use ( $id ) { $_POST = array_merge( array( 'mcupr_notice_info_nonce' => wp_create_nonce( 'mcupr_notice_info_save' ) ), $a ); do_action( 'save_post_mcupr_notice', $id, get_post( $id ), true ); $_POST = array(); return mcupr_notice_info( $id ); };
$i = $post( array( 'mcupr_nt_no' => ' 18/2570 <b>', 'mcupr_nt_method' => str_repeat( 'ก', 300 ), 'mcupr_nt_budget' => '4,500,000.00', 'mcupr_nt_deadline' => $day( 5 ), 'mcupr_nt_status' => 'awarded', 'mcupr_nt_winner' => 'ผู้ชนะ', 'mcupr_nt_price' => '1.5' ) );
t( '18/2570' === $i['no'] && 120 === mb_strlen( $i['method'] ) && 4500000.0 === $i['budget'] && $day( 5 ) === $i['deadline'] && 'awarded' === $i['status'] && 'ผู้ชนะ' === $i['winner'] && 1.5 === $i['price'], 'save: text is cleaned and held to its length, money and the date are read, the status is kept' );
$i = $post( array( 'mcupr_nt_budget' => 'x', 'mcupr_nt_deadline' => '2026-02-30', 'mcupr_nt_status' => 'weird' ) );
t( null === $i['budget'] && '' === $i['deadline'] && '' === $i['status'] && '' === $i['no'] && ! metadata_exists( 'post', $id, '_mcupr_nt_status' ) && ! metadata_exists( 'post', $id, '_mcupr_nt_no' ), 'save: junk and empty fields remove their meta (an automatic status is stored as nothing)' );
update_post_meta( $id, '_mcupr_nt_no', 'keep' );
$_POST = array( 'mcupr_notice_info_nonce' => 'bad', 'mcupr_nt_no' => 'changed' ); do_action( 'save_post_mcupr_notice', $id, get_post( $id ), true ); $_POST = array();
t( 'keep' === mcupr_notice_info( $id )['no'], 'save: a wrong nonce changes nothing' );

// 6. editor and words
ob_start(); mcupr_notice_info_box( get_post( $ids['awarded'] ) ); $box = ob_get_clean();
t( false !== strpos( $box, 'mcupr_notice_info_nonce' ) && false !== strpos( $box, 'name="mcupr_nt_deadline"' ) && false !== strpos( $box, 'type="date"' ) && false !== strpos( $box, 'value="awarded" selected' ) && false !== strpos( $box, 'list="mcupr-nt-methods"' ), 'editor: the box has its nonce, a date field, the status select with the saved choice, and method suggestions' );
ob_start(); do_action( 'manage_mcupr_notice_posts_custom_column', 'mcupr_status', $ids['awarded'] ); $col = ob_get_clean();
t( false !== strpos( $col, 'nt-st-awarded' ), 'admin: the list shows each notice\'s status' );
$words = include MCUPR_DIR . '/inc/strings.php'; $miss = array();
foreach ( array( 'nt_no', 'nt_method', 'nt_budget', 'nt_baht', 'nt_deadline', 'nt_status', 'nt_all_status', 'nt_st_open', 'nt_st_closed', 'nt_st_awarded', 'nt_st_cancelled', 'nt_soon', 'nt_today', 'nt_winner', 'nt_price' ) as $k ) {
	if ( empty( $words[ $k ] ) || 4 !== count( $words[ $k ] ) || in_array( '', $words[ $k ], true ) ) { $miss[] = $k; }
}
t( ! $miss, 'words: every text exists in Thai, English, Chinese and Japanese' . ( $miss ? ' (' . implode( ',', $miss ) . ')' : '' ) );

foreach ( get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $x ) { if ( 0 === strpos( get_the_title( $x ), 'nf-test' ) ) { wp_delete_post( $x, true ); } }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
