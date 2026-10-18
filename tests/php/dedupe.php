<?php
/** Shared helpers that replaced copies: CSV formula guard, robots, years, language fallback, office text colour, rate limit, request checks.   php php/dedupe.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$sub  = isset( $argv[2] ) ? $argv[2] : '';
if ( '' !== $sub ) {
	$_GET['lang'] = $sub; // a run as a visitor reading this language (the language of a request is fixed once it is known)
}
require rtrim( $site, '/' ) . '/wp-load.php';
if ( '' !== $sub ) {
	echo 'DEDUPE ' . wp_json_encode(
		array(
			'lang'    => mcupr_ui_lang(),
			'year'    => mcupr_year_text( 2026 ),
			'year0'   => mcupr_year_text( 0 ),
			'work'    => mcupr_work_year_text( 2026 ),
			'month'   => mcupr_ev_month_title( 2026, 10 ),
			'evdate'  => mcupr_ev_date( gmmktime( 5, 0, 0, 10, 14, 2026 ) ),
			'days'    => mcupr_ev_n( 'dshort' ),
			'pick'    => mcupr_pick_lang( array( 'th' => 'TH', 'en' => 'EN' ) ),
			'pick_th' => mcupr_pick_lang( array( 'th' => 'TH' ), 'FB' ),
			'status'  => mcupr_status_text( array( 'th' => 'TH', 'en' => 'EN' ) ),
			'stat_th' => mcupr_status_text( array( 'th' => 'TH', 'en' => '' ) ),
		)
	) . "\n";
	exit( 0 );
}
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function dedupe_run( $site, $lang ) {
	$out = (string) shell_exec( PHP_BINARY . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $site ) . ' ' . escapeshellarg( $lang ) . ' 2>/dev/null' );
	return preg_match( '/^DEDUPE (.*)$/m', $out, $m ) ? json_decode( $m[1], true ) : array();
}

// 1. CSV: one guard against spreadsheet formulas for every export
$ok = true;
foreach ( array( '=1+1', '+66', '-5', '@SUM(A1)', "\tx", "\rx" ) as $v ) {
	$ok = $ok && "'" . $v === mcupr_csv_safe( $v ) && '"\'' . $v . '"' === mcupr_csv_cell( $v );
}
t( $ok, 'csv: a value starting with = + - @ tab or CR gets a quote mark in front (plain and quoted cell)' );
t( 'abc' === mcupr_csv_safe( 'abc' ) && '' === mcupr_csv_safe( '' ) && '"a""b"' === mcupr_csv_cell( 'a"b' ) && '"5"' === mcupr_csv_cell( 5 ), 'csv: ordinary values are unchanged, quotes are doubled' );
t( "\"'=1\",\"x\"\r\n" === mcupr_import_csv_line( array( '=1', 'x' ) ) && "'@x" === mcupr_si_cell( '@x' ), 'csv: the staff export and the student export use the same guard' );

// 2. robots
$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;
$bot = static function ( $s ) { $_SERVER['HTTP_USER_AGENT'] = $s; return mcupr_is_bot(); };
t( $bot( '' ) && $bot( 'Mozilla/5.0 (compatible; Googlebot/2.1)' ) && $bot( 'Mediapartners-Google' ) && $bot( 'python-requests/2.31' ) && $bot( 'curl/8.0' ) && $bot( 'Mozilla/5.0 (compatible; bingpreview/2.0)' ) && $bot( 'UptimeMonitor/1.0' ), 'robots: one list (both old patterns together)' );
t( ! $bot( 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120 Safari/537.36' ) && ! $bot( 'Mozilla/5.0 Firefox/130.0' ), 'robots: a browser is a person' );
if ( null === $ua ) { unset( $_SERVER['HTTP_USER_AGENT'] ); } else { $_SERVER['HTTP_USER_AGENT'] = $ua; }

// 3. years, month names and language fallback, as a Thai, an English and a Chinese visitor
$th = dedupe_run( $site, 'th' );
$en = dedupe_run( $site, 'en' );
$zh = dedupe_run( $site, 'zh' );
t( isset( $th['lang'], $en['lang'], $zh['lang'] ) && 'th' === $th['lang'] && 'en' === $en['lang'] && 'zh' === $zh['lang'], 'language runs: th, en and zh visitors' );
t( isset( $th['year'] ) && '2569' === $th['year'] && '2569' === $th['work'] && '' === $th['year0'], 'year: Buddhist era in Thai (works use the same helper), nothing for no year' );
t( isset( $en['year'] ) && '2026' === $en['year'] && '2026' === $en['work'], 'year: Gregorian in English' );
t( isset( $th['month'] ) && 'ตุลาคม 2569' === $th['month'] && '14 ตุลาคม 2569' === $th['evdate'] && array( 'อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.' ) === $th['days'], 'events: Thai month and day names come from the tables of dates.php' );
t( isset( $en['month'] ) && 'October 2026' === $en['month'] && '2026年10月' === $zh['month'], 'events: English and Chinese month titles unchanged' );
t( isset( $th['pick'] ) && 'TH' === $th['pick'] && 'EN' === $en['pick'] && 'EN' === $zh['pick'] && 'FB' === $zh['pick_th'] && 'TH' === $th['pick_th'], 'pick_lang: the visitor\'s language, else English, else the fallback' );
t( isset( $zh['status'] ) && 'EN' === $zh['status'] && 'TH' === $zh['stat_th'] && 'TH' === $th['status'], 'site status text: a Chinese visitor reads English before Thai, Thai when there is no English' );

// 4. office text colour: same side as mcupr_on_color() on brand colours, the office shade of near-black kept
$ok = true;
foreach ( array( '#0f766e', '#1d4ed8', '#fde68a', '#c2185b', '#8e1b3a', '#1f3a6e', '#a84a00', '#4f46e5', '#ffffff', '#000000', '#808080', '#d97706', '#22c55e', '#e11d48' ) as $c ) {
	$o  = mcupr_office_on_color( $c );
	$ok = $ok && ( '#ffffff' === $o ) === ( '#ffffff' === mcupr_on_color( $c ) ) && in_array( $o, array( '#ffffff', '#111827' ), true );
}
t( $ok, 'office colour: white or near-black on the same colours as the site-wide helper' );
t( '#111827' === mcupr_office_on_color( '#777777' ) && '#ffffff' === mcupr_office_on_color( '#757575' ) && '#ffffff' === mcupr_office_on_color( 'nonsense' ) && '#111827' === mcupr_office_on_color( '#fff' ), 'office colour: its own threshold, short hex and a bad value as before' );

// 5. rate limit
$key = 'dedupe-test-' . wp_generate_password( 8, false );
t( mcupr_throttle( $key, 2, 60 ) && mcupr_throttle( $key, 2, 60 ) && ! mcupr_throttle( $key, 2, 60 ) && ! mcupr_throttle( $key, 2, 60 ), 'throttle: two tries in the window, the third and fourth refused' );
t( ! mcupr_stu_throttle( $key, 2, 60 ) && mcupr_stu_throttle( $key, 3, 60 ), 'throttle: the student portal limit is the same counter' );
delete_transient( 'mcupr_thr_' . md5( $key ) );
t( mcupr_throttle( $key, 1, 60 ), 'throttle: a fresh window counts from zero' );
delete_transient( 'mcupr_thr_' . md5( $key ) );

// 6. nonce / capability / meta box checks
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $adm[0]->ID );
$post_bak = $_POST; $get_bak = $_GET;
$_POST = array( 'f' => wp_create_nonce( 'act' ) ); $_GET = array( 'g' => wp_create_nonce( 'act' ) );
t( mcupr_check_request( 'f', 'act' ) && mcupr_check_request( 'f', 'act', 'manage_options' ) && mcupr_check_request( 'g', 'act', '', 'get' ), 'request: a good nonce in POST or GET, with or without a capability' );
t( ! mcupr_check_request( 'f', 'other' ) && ! mcupr_check_request( 'missing', 'act' ) && ! mcupr_check_request( 'g', 'act' ), 'request: wrong action, missing field, or the field in the other place is refused' );
$pid = wp_insert_post( array( 'post_title' => 'dedupe box', 'post_status' => 'draft' ) );
t( mcupr_can_save_box( $pid, 'f', 'act' ) && ! mcupr_can_save_box( $pid, 'f', 'other' ), 'meta box: saved with its nonce by someone who may edit the post' );
wp_set_current_user( 0 );
t( ! mcupr_check_request( 'f', 'act', 'manage_options' ) && ! mcupr_can_save_box( $pid, 'f', 'act' ), 'request: a visitor without the capability is refused' );
$_POST = $post_bak; $_GET = $get_bak;
wp_delete_post( $pid, true );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
