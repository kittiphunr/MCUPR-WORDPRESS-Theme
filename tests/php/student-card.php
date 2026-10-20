<?php
/** Student card (RFID) and national ID number: import (hash only), duplicates, the check at sign-in, the WordPress login page, staff box, settings.   php php/student-card.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() { foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } }
wp_set_current_user( 1 );
wipe();
$old = get_option( 'mcupr_card' );
delete_option( 'mcupr_card' );

// ---- values
t( mcupr_card_rfid_ok( '4028251661' ) && ! mcupr_card_rfid_ok( '402825166' ) && ! mcupr_card_rfid_ok( '40282516611' ) && ! mcupr_card_rfid_ok( '40282516ab' ), 'rfid: exactly 10 digits' );
t( mcupr_card_idcard_ok( '1101700203450' ) && ! mcupr_card_idcard_ok( '1101700203451' ) && ! mcupr_card_idcard_ok( '110170020345' ) && ! mcupr_card_idcard_ok( 'abcdefghijklm' ), 'national ID: 13 digits with the right check digit' );
t( '4028251661' === mcupr_card_digits( ' 4028251661.0 ' ) && '1101700203450' === mcupr_card_digits( '1-1017-00203-45-0' ), 'values: spaces, dashes and ".0" from a spreadsheet are removed' );
$h = mcupr_card_import_cell( 'rfid', '4028251661' );
t( 0 === strpos( $h, 'h:' ) && false === strpos( $h, '4028251661' ) && 71 === strlen( $h ) && '|1661' === substr( $h, -5 ) && array( substr( $h, 2, 64 ), '1661' ) === mcupr_card_cell_split( substr( $h, 2 ) ), 'hash: the cell becomes a keyed hash and the last 4 digits, not the number' );
t( $h === mcupr_card_import_cell( 'rfid', '4028251661.0' ) && 'bad' === mcupr_card_import_cell( 'rfid', '123' ) && '' === mcupr_card_import_cell( 'rfid', '  ' ) && 'bad' === mcupr_card_import_cell( 'idcard', '1101700203451' ), 'hash: same value same hash; short, empty and wrong-check-digit are told apart' );
t( mcupr_card_import_cell( 'rfid', '1101700203450' ) !== mcupr_card_import_cell( 'idcard', '1101700203450' ), 'hash: a card code and an ID number never share a hash' );

// ---- import
$csv  = "รหัสนิสิต,ชื่อ-สกุล,ระดับ,รหัสบัตร RFID,เลขบัตรประชาชน\n";
$csv .= "9201,นิสิต หนึ่ง,ตรี,4028251661,\n9202,นิสิต สอง,ตรี,,1101700203450\n9203,นิสิต สาม,ตรี,4028251661,\n9204,นิสิต สี่,ตรี,12345,1101700203451\n9205,นิสิต ห้า,ตรี,,\n";
$p = mcupr_si_parse( $csv );
t( ! is_wp_error( $p ) && isset( $p['cols']['rfid'], $p['cols']['idcard'] ), 'import: the two columns are recognised (Thai names)' );
$raw = wp_json_encode( $p );
t( false === strpos( $raw, '4028251661' ) && false === strpos( $raw, '1101700203450' ), 'import: the rows kept for the preview have no card code and no ID number in them' );
$map = mcupr_student_map(); $seen = array(); $rows = array();
foreach ( $p['rows'] as $r ) { $rows[] = mcupr_si_check( $r[1], $p['cols'], $map, $seen, 'add_update' ); }
t( ! $rows[0]['errors'] && ! empty( $rows[0]['row']['_rfid'] ) && 64 === strlen( $rows[0]['row']['_rfid'] ), 'import: a valid card code is accepted' );
t( ! $rows[1]['errors'] && ! empty( $rows[1]['row']['_idcard'] ), 'import: a valid ID number is accepted' );
t( in_array( 'rfid_dup', $rows[2]['errors'], true ), 'import: the same card code on two rows is a mistake for the second' );
t( ! $rows[3]['errors'] && in_array( 'rfid', $rows[3]['warnings'], true ) && in_array( 'idcard', $rows[3]['warnings'], true ) && empty( $rows[3]['row']['_rfid'] ), 'import: a wrong code or number is a warning, and is not saved; the student is still imported' );
t( ! $rows[4]['errors'] && empty( $rows[4]['row']['_rfid'] ) && empty( $rows[4]['row']['_idcard'] ), 'import: empty cells are fine' );
// run the import as the page does
foreach ( $rows as $i => $c ) {
	if ( $c['errors'] ) { continue; }
	list( $res, $id ) = mcupr_student_upsert( $c['row'], 'add_update', 0 );
	foreach ( array( '_rfid' => MCUPR_CARD_RFID, '_idcard' => MCUPR_CARD_IDC ) as $rk => $mk ) { if ( ! empty( $c['row'][ $rk ] ) ) { update_post_meta( $id, $mk, $c['row'][ $rk ] ); } }
}
$s1 = mcupr_student_find( '9201' ); $s2 = mcupr_student_find( '9202' ); $s4 = mcupr_student_find( '9204' );
t( mcupr_card_state( $s1 ) === array( 'rfid' => true, 'idcard' => false ) && mcupr_card_state( $s2 ) === array( 'rfid' => false, 'idcard' => true ) && mcupr_card_state( $s4 ) === array( 'rfid' => false, 'idcard' => false ), 'import: stored per student' );
t( false === strpos( wp_json_encode( get_post_meta( $s1 ) ), '4028251661' ) && false === strpos( wp_json_encode( get_post_meta( $s2 ) ), '1101700203450' ), 'import: the database holds only hashes' );
// a card code already belonging to another student
mcupr_si_rfid_owner( '', 0, true );
$p2 = mcupr_si_parse( "sid,name,rfid\n9204,นิสิต สี่,4028251661\n" );
$s  = array(); $c = mcupr_si_check( $p2['rows'][0][1], $p2['cols'], mcupr_student_map(), $s, 'add_update' );
t( in_array( 'rfid_used', $c['errors'], true ), 'import: a card code that belongs to another student already is refused' );
$p3 = mcupr_si_parse( "sid,name,rfid\n9201,นิสิต หนึ่ง,4028251661\n" );
$s  = array(); $c = mcupr_si_check( $p3['rows'][0][1], $p3['cols'], mcupr_student_map(), $s, 'add_update' );
t( ! in_array( 'rfid_used', $c['errors'], true ), 'import: the same card code for the same student again is fine' );
// a file with only the ID numbers (no name column)
$p4 = mcupr_si_parse( "รหัสนิสิต,เลขบัตรประชาชน\n9204,1101700203450\n9999,1101700203450\n" );
t( ! is_wp_error( $p4 ), 'import: a file with only student ID and ID number is read' );
$s = array(); $m = mcupr_student_map();
$a = mcupr_si_check( $p4['rows'][0][1], $p4['cols'], $m, $s, 'update_only' ); $b = mcupr_si_check( $p4['rows'][1][1], $p4['cols'], $m, $s, 'add_update' );
t( ! $a['errors'] && ! empty( $a['row']['_idcard'] ), 'import: …works for a student who is there' );
t( in_array( 'name', $b['errors'], true ), 'import: …but a new student still needs a name' );
t( ! is_wp_error( mcupr_si_parse( "รหัสนิสิต,ชื่อ\n1,x\n" ) ) && is_wp_error( mcupr_si_parse( "รหัสนิสิต,สาขา\n1,x\n" ) ), 'import: still needs the name column unless the file carries card codes' );
$ex = ''; foreach ( mcupr_student_data( $s1 ) as $k => $v ) { $ex .= $k . '=' . $v . ';'; }
t( false === strpos( $ex, '4028251661' ) && ! in_array( 'rfid', array_keys( mcupr_si_columns() ), true ), 'export: the student data and the export columns have no card data' );

// a real Excel / PDF file is told apart from a CSV (it used to look "empty")
t( 'excel' === mcupr_si_binary_kind( "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat( "\0", 40 ) ) && 'excel' === mcupr_si_binary_kind( "PK\x03\x04abc" ) && 'pdf' === mcupr_si_binary_kind( '%PDF-1.7' ) && '' === mcupr_si_binary_kind( "\xEF\xBB\xBFรหัสนิสิต,ชื่อ\n" ) && '' === mcupr_si_binary_kind( "sid,name\n1,x\n" ), 'import: an .xls / .xlsx / PDF is recognised before reading, a CSV (with or without BOM) is not' );

// old PHP (7.0 – 7.3) rejects an empty escape character in fgetcsv/fputcsv and returns false: a good file then looked "empty"
t( 1 === strlen( mcupr_csv_escape() ), 'csv: the escape character is exactly one character (PHP 7.0 – 7.3 need that)' );
$bad = array();
foreach ( glob( __DIR__ . '/../../mcupr/inc/*.php' ) as $f ) { if ( preg_match( '/(fgetcsv|fputcsv)\([^;]*\'"\'\s*,\s*\'\'\s*\)/', file_get_contents( $f ) ) ) { $bad[] = basename( $f ); } }
t( ! $bad, 'csv: no code gives fgetcsv/fputcsv an empty escape character' . ( $bad ? ' (' . implode( ', ', $bad ) . ')' : '' ) );
foreach ( array( 'no BOM' => "รหัสนิสิต,ชื่อ-สกุล\n1234,นิสิต หนึ่ง\n", 'CRLF' => "รหัสนิสิต,ชื่อ-สกุล\r\n1234,นิสิต หนึ่ง\r\n", 'BOM' => "\xEF\xBB\xBFรหัสนิสิต,ชื่อ-สกุล\n1234,นิสิต หนึ่ง\n", 'semicolon' => "รหัสนิสิต;ชื่อ-สกุล\n1234;นิสิต หนึ่ง\n", 'tab' => "รหัสนิสิต\tชื่อ-สกุล\n1234\tนิสิต หนึ่ง\n", 'no final newline' => "รหัสนิสิต,ชื่อ-สกุล\n1234,นิสิต หนึ่ง" ) as $label => $txt ) {
	$pp = mcupr_si_parse( $txt );
	t( ! is_wp_error( $pp ) && 1 === count( $pp['rows'] ), 'csv: a file with ' . $label . ' is read' );
}

// ---- the check at sign-in
t( '' === mcupr_card_verify( $s1, '4028251661', '' ), 'check: the right card code' );
t( 'card' === mcupr_card_verify( $s1, '4028251662', '' ) && 'card' === mcupr_card_verify( $s1, '', '' ), 'check: a wrong or missing card code' );
t( 'card' === mcupr_card_verify( $s1, '', '1101700203450' ), 'check: the ID number is not accepted when the record has a card code' );
t( '' === mcupr_card_verify( $s2, '', '1101700203450' ) && '' === mcupr_card_verify( $s2, 'x', '1-1017-00203-45-0' ), 'check: with no card code the national ID number is asked for (dashes allowed)' );
t( 'card' === mcupr_card_verify( $s2, '', '1101700203459' ) && 'card' === mcupr_card_verify( $s2, '4028251661', '' ), 'check: a wrong ID number; a card code does not replace it' );
t( 'cardnone' === mcupr_card_verify( $s4, '4028251661', '1101700203450' ), 'check: a record with neither cannot be verified' );

// ---- sign in
foreach ( array( $s1, $s2, $s4 ) as $x ) { update_post_meta( $x, '_mcupr_s_email', 'card' . $x . '@example.org' ); }
$u1 = mcupr_student_make_account( $s1 ); $u2 = mcupr_student_make_account( $s2 );
wp_set_password( 'Card-pass-12345', $u1 ); wp_set_password( 'Card-pass-12345', $u2 );
$l1 = get_userdata( $u1 )->user_login;
t( ! is_wp_error( wp_authenticate( $l1, 'Card-pass-12345' ) ), 'login page: switch off – the password alone works as before' );
update_option( 'mcupr_card', array( 'on' => 1, 'typing' => 0 ) );
t( mcupr_card_required() && is_wp_error( wp_authenticate( $l1, 'Card-pass-12345' ) ), 'login page: switch on – a student account cannot go in with the password alone on the ordinary WordPress login' );
mcupr_card_portal_flag( true );
t( ! is_wp_error( wp_authenticate( $l1, 'Card-pass-12345' ) ), 'portal: the portal form does the card check itself (password accepted there)' );
mcupr_card_portal_flag( false );
$ed = wp_insert_user( array( 'user_login' => 'card-staff', 'user_pass' => 'Staff-pass-12345', 'user_email' => 'card-staff@example.org', 'role' => 'editor' ) );
t( ! is_wp_error( wp_authenticate( 'card-staff', 'Staff-pass-12345' ) ), 'staff: not affected' );
// the portal form over http
$postf = function ( $login, $pwd, $extra ) {
	$cu = get_current_user_id(); wp_set_current_user( 0 ); $nonce = wp_create_nonce( 'mcupr_stu_login' ); wp_set_current_user( $cu );
	$r = wp_remote_post( admin_url( 'admin-post.php' ), array( 'timeout' => 20, 'redirection' => 0, 'body' => array_merge( array( 'action' => 'mcupr_stu_login', 'mcupr_stu_nonce' => $nonce, 'log' => $login, 'pwd' => $pwd ), $extra ) ) );
	return is_wp_error( $r ) ? array( 0, '', array() ) : array( wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_header( $r, 'location' ), wp_remote_retrieve_header( $r, 'set-cookie' ) );
};
$srv = @fsockopen( 'localhost', 8090, $en, $es, 1 );
if ( $srv ) {
	fclose( $srv );
	delete_transient( 'mcupr_thr_' . md5( '127.0.0.1|' . strtolower( $l1 ) ) );
	$cookies = function ( $r ) { return is_array( $r[2] ) ? implode( ' ', $r[2] ) : (string) $r[2]; };
	$r = $postf( $l1, 'Card-pass-12345', array( 'rfid' => '4028251661' ) );
	t( 302 === $r[0] && false !== strpos( $cookies( $r ), 'wordpress_logged_in' ) && false === strpos( $r[1], 'e=' ), 'portal form: password + the right card = signed in' );
	$r = $postf( $l1, 'Card-pass-12345', array( 'rfid' => '4028251660' ) );
	t( false !== strpos( $r[1], 'e=login' ) && false === strpos( $cookies( $r ), 'wordpress_logged_in' ), 'portal form: a wrong card is refused, no cookie' );
	$r = $postf( $l1, 'Card-pass-12345', array() );
	t( false !== strpos( $r[1], 'e=login' ) && false === strpos( $cookies( $r ), 'wordpress_logged_in' ), 'portal form: no card at all is refused' );
	$r = $postf( $l1, 'wrong-password', array( 'rfid' => '4028251661' ) );
	t( false !== strpos( $r[1], 'e=login' ), 'portal form: a wrong password is still the usual message (the card is not even looked at)' );
	$l2 = get_userdata( $u2 )->user_login;
	$r = $postf( $l2, 'Card-pass-12345', array( 'idc' => '1101700203450' ) );
	t( 302 === $r[0] && false !== strpos( $cookies( $r ), 'wordpress_logged_in' ), 'portal form: no card code on record – the national ID number signs in' );
	$r = $postf( $l2, 'Card-pass-12345', array( 'idc' => '1101700203459' ) );
	t( false !== strpos( $r[1], 'e=login' ), 'portal form: …a wrong ID number does not' );
	$u4 = mcupr_student_make_account( $s4 );
	if ( ! is_wp_error( $u4 ) ) {
		wp_set_password( 'Card-pass-12345', $u4 );
		$r = $postf( get_userdata( $u4 )->user_login, 'Card-pass-12345', array( 'rfid' => '4028251661', 'idc' => '1101700203450' ) );
		t( false !== strpos( $r[1], 'e=login' ), 'portal form: a record with neither is told to contact the staff' );
	} else { t( true, 'portal form: (no account could be made for the record with neither – skipped)' ); }
	$page = wp_remote_retrieve_body( wp_remote_get( mcupr_student_portal_url(), array( 'timeout' => 20 ) ) );
	t( false !== strpos( $page, 'name="rfid"' ) && false !== strpos( $page, 'name="idc"' ) && false !== strpos( $page, 'type="password" name="rfid"' ), 'portal page: has the card box (digits hidden) and the ID number box' );
	update_option( 'mcupr_card', array( 'on' => 0, 'typing' => 0 ) );
	$page = wp_remote_retrieve_body( wp_remote_get( mcupr_student_portal_url(), array( 'timeout' => 20 ) ) );
	t( false === strpos( $page, 'name="rfid"' ), 'portal page: with the switch off there is no card box' );
	update_option( 'mcupr_card', array( 'on' => 1, 'typing' => 0 ) );
} else { t( true, 'portal form: (no web server on :8090 – skipped)' ); }

// ---- the staff box and the settings
$cov = mcupr_card_coverage();
t( 4 === $cov['all'] && 1 === $cov['rfid'] && 1 === $cov['idcard'] && 2 === $cov['none'], 'settings: the numbers – with a card, with only an ID number, with neither' );
ob_start(); do_action( 'mcupr_stu_settings_extra' ); $form = ob_get_clean();
t( false !== strpos( $form, 'name="on"' ) && false !== strpos( $form, 'name="typing"' ), 'settings: the switch and "typing by hand" are there' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_rfid' => '5555555555', 'mcupr_card_idc' => '1101700203450' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( mcupr_card_state( $s4 ) === array( 'rfid' => true, 'idcard' => true ), 'staff: a card code and an ID number set on the record' );
ob_start(); mcupr_card_box( get_post( $s4 ) ); $bx0 = ob_get_clean();
t( '••••••5555' === mcupr_card_rfid_shown( $s4 ) && false !== strpos( $bx0, 'placeholder="••••••5555"' ) && false === strpos( $bx0, '5555555555' ), 'staff: the card code is shown by its last 4 digits, never in full' );
$s6 = wp_insert_post( array( 'post_type' => MCUPR_STU, 'post_status' => 'publish', 'post_title' => 'Old card' ) ); update_post_meta( $s6, MCUPR_CARD_RFID, str_repeat( 'a', 64 ) );
t( '' === mcupr_card_rfid_shown( $s6 ) && mcupr_card_state( $s6 )['rfid'], 'staff: a card set before the last digits were kept just says "set"' ); wp_delete_post( $s6, true );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_rfid' => '4028251661' );
do_action( 'save_post_mcupr_student', $s2, get_post( $s2 ), true );
t( ! mcupr_card_state( $s2 )['rfid'], 'staff: a card code that belongs to another student is not set' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_clear_rfid' => '1' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( ! mcupr_card_state( $s4 )['rfid'] && mcupr_card_state( $s4 )['idcard'], 'staff: the card code removed, the ID number kept' );
t( '' === mcupr_card_rfid_shown( $s4 ), 'staff: removing the card code removes its last digits too' );
// the box shows the number from the central details and keeps both places in step
t( '1101700203450' === ( mcupr_sp_get( $s4 )['idcard'] ?? '' ), 'sync: an ID number set in the box is also in the central details' );
ob_start(); mcupr_card_box( get_post( $s4 ) ); $bx = ob_get_clean();
t( false === strpos( $bx, '1101700203450' ) && false !== strpos( $bx, 'placeholder="•••••••••3450"' ), 'sync: the box shows the number masked (last 4 digits), not in full' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_idc' => '1234567890123' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( '1234567890123' === mcupr_sp_get( $s4 )['idcard'] && ! mcupr_card_state( $s4 )['idcard'], 'sync: a number with a wrong check digit is kept centrally but is not a sign-in factor' );
ob_start(); mcupr_card_box( get_post( $s4 ) ); $bx = ob_get_clean();
t( false !== strpos( $bx, 'placeholder="•••••••••0123"' ) && false !== strpos( $bx, esc_html__( 'in the central details, but the check digit is wrong (cannot be used to sign in)', 'mcupr' ) ), 'sync: the box says the check digit is wrong' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_idc' => '1101700203450' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( mcupr_card_state( $s4 )['idcard'] && '1101700203450' === mcupr_sp_get( $s4 )['idcard'], 'sync: a valid number sets both again' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_clear_idc' => '1' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( ! mcupr_card_state( $s4 )['idcard'] && empty( mcupr_sp_get( $s4 )['idcard'] ), 'sync: removing clears both' );
$_POST = array( 'mcupr_card_box_nonce' => wp_create_nonce( 'mcupr_card_box' ), 'mcupr_card_idc' => '1101700203450' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
$_POST = array( 'mcupr_card_rfid' => '5555555555' );
do_action( 'save_post_mcupr_student', $s4, get_post( $s4 ), true );
t( ! mcupr_card_state( $s4 )['rfid'], 'staff: without the nonce nothing is saved' );
// a repeat import keeps what is already set on the record and only fills what is empty
$hs = get_post_meta( $s4, MCUPR_CARD_IDC, true );
$hc = get_post_meta( $s1, MCUPR_CARD_RFID, true );
$pr = mcupr_si_parse( "รหัสนิสิต,ชื่อ-สกุล,รหัสบัตร RFID,เลขบัตรประชาชน\n9204,นิสิตสี่,9876543210,3100200000008\n9201,นิสิตหนึ่ง,9876543211,3100200000008\n" );
$tk = bin2hex( random_bytes( 16 ) );
mcupr_si_save( $tk, array( 'cols' => $pr['cols'], 'rows' => $pr['rows'], 'unknown' => array(), 'mode' => 'add_update', 'accounts' => false, 'user' => 1, 'sample' => false, 'state' => array( 'offset' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'accounts' => 0, 'errors' => array(), 'warn' => 0 ) ) );
ob_start(); mcupr_si_run( $tk ); ob_get_clean();
t( $hs === get_post_meta( $s4, MCUPR_CARD_IDC, true ) && '1101700203450' === mcupr_sp_get( $s4 )['idcard'], 'import again: an ID number already set (hash and central copy) is not overwritten' );
t( $hc === get_post_meta( $s1, MCUPR_CARD_RFID, true ), 'import again: a card code already set is not overwritten' );
t( ! empty( get_post_meta( $s4, MCUPR_CARD_RFID, true ) ) && ! empty( get_post_meta( $s1, MCUPR_CARD_IDC, true ) ), 'import again: an empty card code / ID number is still filled from the file' );
$_POST = array();
$ev = array_map( function ( $p ) { return $p->post_title; }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
$ex = implode( ' ', array_map( function ( $p ) { return get_post_meta( $p->ID, '_mcupr_a', true ); }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) ) );
t( in_array( 'cardset', $ev, true ) && false === strpos( $ex, '5555555555' ) && false === strpos( $ex, '1101700203450' ), 'log: setting a card is logged, without the numbers' );
t( has_action( 'admin_post_mcupr_card_save' ) && ! has_action( 'admin_post_nopriv_mcupr_card_save' ), 'settings: only for logged-in staff' );
$edi = wp_insert_user( array( 'user_login' => 'card-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'card-edi@example.org', 'role' => 'editor' ) );
t( ! user_can( $edi, MCUPR_STU_CAP ), 'settings: an editor cannot change them' );

$old ? update_option( 'mcupr_card', $old ) : delete_option( 'mcupr_card' );
wipe();
foreach ( array( $ed, $edi, $u1, $u2 ) as $x ) { if ( $x && ! is_wp_error( $x ) ) { wp_delete_user( $x ); } }
foreach ( get_users( array( 'search' => '920*', 'search_columns' => array( 'user_login' ) ) ) as $x ) { wp_delete_user( $x->ID ); }
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
