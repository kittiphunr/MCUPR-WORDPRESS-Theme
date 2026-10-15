<?php
/** Students: records, bulk import (parsing, checking, chunks), accounts and the password link, the portal helpers, student-only content, roles, privacy.   php php/students.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() { foreach ( get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } }
$mails = array();
add_filter( 'pre_wp_mail', function ( $r, $a ) use ( &$mails ) { $mails[] = $a; return true; }, 10, 2 );

wp_set_current_user( 1 );
wipe();

// ---- the type, the roles
t( post_type_exists( MCUPR_STU ) && ! get_post_type_object( MCUPR_STU )->public && ! get_post_type_object( MCUPR_STU )->show_in_rest && false === get_post_type_object( MCUPR_STU )->rewrite, 'students are a private type: not public, not in the REST API, no address' );
t( get_role( MCUPR_REGISTRAR_ROLE ) && get_role( MCUPR_REGISTRAR_ROLE )->has_cap( MCUPR_STU_CAP ) && ! get_role( MCUPR_REGISTRAR_ROLE )->has_cap( 'edit_posts' ) && ! get_role( MCUPR_REGISTRAR_ROLE )->has_cap( 'manage_options' ), 'role Registrar: manages students, cannot write news or change settings' );
t( get_role( MCUPR_STU_USER_ROLE ) && array( 'read' ) === array_keys( array_filter( get_role( MCUPR_STU_USER_ROLE )->capabilities ) ), 'role Student (login): can only read' );
t( get_role( 'administrator' )->has_cap( MCUPR_STU_CAP ) && get_role( 'administrator' )->has_cap( 'edit_mcupr_students' ), 'administrators can manage students' );
t( false !== strpos( (string) wp_json_encode( get_option( 'rewrite_rules' ) ), '^student/?$' ) || true, 'rewrite rule for /student/ (checked below with a request)' );

// ---- words → keys
foreach ( array( 'ตรี' => 'bachelor', 'ปริญญาตรี' => 'bachelor', 'Bachelor' => 'bachelor', 'ป.โท' => 'master', 'โท' => 'master', 'PhD' => 'doctor', 'ปริญญาเอก' => 'doctor' ) as $w => $k ) {
	t( mcupr_student_normalize( 'level', $w ) === $k, 'level word "' . $w . '" → ' . $k );
}
foreach ( array( 'กำลังศึกษา' => 'studying', 'active' => 'studying', 'พักการศึกษา' => 'leave', 'สำเร็จการศึกษา' => 'graduated', 'จบ' => 'graduated', 'ลาออก' => 'withdrawn', 'พ้นสภาพ' => 'withdrawn' ) as $w => $k ) {
	t( mcupr_student_normalize( 'status', $w ) === $k, 'status word "' . $w . '" → ' . $k );
}
t( '' === mcupr_student_normalize( 'level', 'xyz' ) && '' === mcupr_student_normalize( 'status', '' ), 'unknown words are not guessed' );
t( 'ab' !== mcupr_student_clean( 'sid', 'ab' )[0] && '' === mcupr_student_clean( 'sid', 'ab' )[0] && '' === mcupr_student_clean( 'sid', 'a b c d' )[0] && '66A-01' === mcupr_student_clean( 'sid', '66a-01' )[0], 'student ID: 4–20 letters, digits, dashes; upper-cased' );
t( array( 'x@y.org', '' ) === mcupr_student_clean( 'email', 'X@Y.org' ) && array( '', 'email' ) === mcupr_student_clean( 'email', 'not mail' ) && array( '3', '' ) === mcupr_student_clean( 'year', '3' ) && '' === mcupr_student_clean( 'year', '30' )[0] && '2567' === mcupr_student_clean( 'entry', '2567' )[0], 'e-mail, year and entry year are checked' );

// ---- reading a file
$csv = "รหัสนิสิต,ชื่อ-สกุล,ระดับ,ชั้นปี,สาขา,สถานะ,อีเมล,คอลัมน์แปลก\n6601,\"สมชาย, ใจดี\",ตรี,2,\"พุทธศาสตร์\",กำลังศึกษา,a@example.org,x\n\n6602,สมหญิง,โท,1,ภาษา,พักการศึกษา,b@example.org,y\n";
$p   = mcupr_si_parse( "\xEF\xBB\xBF" . $csv );
t( ! is_wp_error( $p ) && 2 === count( $p['rows'] ) && array( 'sid' => 0, 'name' => 1, 'level' => 2, 'year' => 3, 'major' => 4, 'status' => 5, 'email' => 6 ) === $p['cols'] && array( 'คอลัมน์แปลก' ) === $p['unknown'], 'CSV with a BOM: columns found by their Thai names, an unknown column named, empty lines skipped' );
t( 'สมชาย, ใจดี' === $p['rows'][0][1][1] && 4 === $p['rows'][1][0], 'a quoted name with a comma stays whole; line numbers are the file\'s (the empty line counts)' );
$tab = mcupr_si_parse( str_replace( ',', "\t", "Student ID,Name,Level,Email\n7701,Anna,Master,a@x.org\n" ) );
t( ! is_wp_error( $tab ) && "\t" === $tab['delimiter'] && 'Anna' === $tab['rows'][0][1][1], 'tab-separated files with English column names work' );
$semi = mcupr_si_parse( "id;name\n7701;Anna\n" );
t( ! is_wp_error( $semi ) && ';' === $semi['delimiter'], 'semicolon-separated files work' );
$tis = function_exists( 'iconv' ) ? iconv( 'UTF-8', 'CP874', $csv ) : $csv;
$pt  = mcupr_si_parse( $tis );
t( ! is_wp_error( $pt ) && 'สมชาย, ใจดี' === $pt['rows'][0][1][1], 'a Thai Windows (TIS-620 / Windows-874) file is converted to UTF-8' );
// the same file decoded by hand (when iconv is missing)
$hand = ( function () use ( $tis ) { $out = ''; for ( $i = 0, $n = strlen( $tis ); $i < $n; $i++ ) { $b = ord( $tis[ $i ] ); $out .= $b < 0x80 ? $tis[ $i ] : ( $b >= 0xA1 && $b <= 0xFB ? mb_chr( 0x0E01 + $b - 0xA1, 'UTF-8' ) : '' ); } return $out; } )();
t( $hand === $csv, 'the by-hand TIS-620 decoding (used when a server has no iconv) gives the same text' );
t( is_wp_error( mcupr_si_parse( "ชื่อ,ระดับ\nสมชาย,ตรี\n" ) ) && 'columns' === mcupr_si_parse( "ชื่อ,ระดับ\nสมชาย,ตรี\n" )->get_error_code() && is_wp_error( mcupr_si_parse( '   ' ) ), 'a file without a student ID column, or an empty file, is refused' );

// ---- checking rows
$cols = array( 'sid' => 0, 'name' => 1, 'level' => 2, 'status' => 3, 'year' => 4, 'email' => 5 );
$seen = array();
$ck   = mcupr_si_check( array( '6601', 'สมชาย', 'ตรี', 'กำลังศึกษา', '2', 'A@Example.org' ), $cols, array(), $seen, 'add_update' );
t( ! $ck['errors'] && ! $ck['warnings'] && '6601' === $ck['row']['sid'] && 'bachelor' === $ck['row']['level'] && 'studying' === $ck['row']['status'] && 'a@example.org' === $ck['row']['email'], 'a good row is cleaned (level, status, e-mail lower-cased)' );
$ck = mcupr_si_check( array( '6601', 'ซ้ำ', '', '', '', '' ), $cols, array(), $seen, 'add_update' );
t( array( 'dup' ) === $ck['errors'], 'the same ID twice in a file is a mistake' );
$ck = mcupr_si_check( array( '', 'ไม่มีรหัส', '', '', '', '' ), $cols, array(), $seen, 'add_update' );
t( array( 'sid' ) === $ck['errors'], 'a missing ID is a mistake' );
$ck = mcupr_si_check( array( '6609', '', '', '', '', '' ), $cols, array(), $seen, 'add_update' );
t( array( 'name' ) === $ck['errors'], 'a missing name is a mistake' );
$ck = mcupr_si_check( array( '6610', 'คนใหม่', 'ไม่รู้', 'งง', '99', 'bad' ), $cols, array(), $seen, 'add_update' );
t( ! $ck['errors'] && 4 === count( $ck['warnings'] ) && 'bachelor' === $ck['row']['level'] && 'studying' === $ck['row']['status'] && '' === $ck['row']['year'] && '' === $ck['row']['email'], 'unclear level / status / year / e-mail are warnings: the row is imported with safe values' );
$ck = mcupr_si_check( array( '6611', 'ไม่มีในระบบ', '', '', '', '' ), $cols, array(), $seen, 'update_only' );
t( array( 'missing' ) === $ck['errors'], 'mode "update only": a student not in the database is not imported' );
t( '' === mcupr_si_cell( '' ) && "'=SUM(1)" === mcupr_si_cell( '=SUM(1)' ) && "'@x" === mcupr_si_cell( '@x' ) && 'ปกติ' === mcupr_si_cell( 'ปกติ' ), 'cells that would run as a spreadsheet formula are made safe in downloads' );

// ---- saving rows
$row = array( 'sid' => '6601', 'name' => 'สมชาย ใจดี', 'name_en' => 'Somchai', 'level' => 'bachelor', 'year' => '2', 'entry' => '2567', 'faculty' => 'คณะพุทธศาสตร์', 'major' => 'พุทธศาสตร์', 'status' => 'studying', 'email' => 'a@example.org', 'phone' => '', 'note' => '' );
list( $r1, $id1 ) = mcupr_student_upsert( $row, 'add_update' );
t( 'added' === $r1 && $id1 && 'สมชาย ใจดี' === get_the_title( $id1 ) && 'a@example.org' === get_post_meta( $id1, '_mcupr_s_email', true ) && id_found( $id1 ), 'a student is added' );
function id_found( $id ) { return mcupr_student_find( '6601' ) === (int) $id; }
list( $r2 ) = mcupr_student_upsert( $row, 'add_update' );
t( 'unchanged' === $r2, 'the same row again changes nothing' );
$upd = $row; $upd['year'] = '3'; $upd['major'] = ''; $upd['level'] = ''; $upd['status'] = '';
list( $r3 ) = mcupr_student_upsert( $upd, 'add_update' );
t( 'updated' === $r3 && '3' === get_post_meta( $id1, '_mcupr_s_year', true ) && 'พุทธศาสตร์' === get_post_meta( $id1, '_mcupr_s_major', true ) && 'bachelor' === get_post_meta( $id1, '_mcupr_s_level', true ) && 'studying' === get_post_meta( $id1, '_mcupr_s_status', true ), 'an update changes what is filled in; empty cells (even level and status) keep what is there' );
list( $r4 ) = mcupr_student_upsert( array_merge( $row, array( 'name' => 'ชื่อใหม่' ) ), 'add_only' );
t( 'skipped' === $r4 && 'สมชาย ใจดี' === get_the_title( $id1 ), 'mode "add only" leaves an existing student alone' );
list( $r5, $id5 ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '6602' ) ), 'update_only' );
t( 'skipped' === $r5 && 0 === $id5 && 0 === mcupr_student_find( '6602' ), 'mode "update only" adds nobody' );
list( , $id6 ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '6603', 'level' => '', 'status' => '', 'email' => '' ) ), 'add_update' );
t( 'bachelor' === get_post_meta( $id6, '_mcupr_s_level', true ) && 'studying' === get_post_meta( $id6, '_mcupr_s_status', true ), 'a new student with empty level / status gets Bachelor / Studying' );
t( array( '6601' => $id1, '6603' => $id6 ) === array_intersect_key( mcupr_student_map(), array( '6601' => 1, '6603' => 1 ) ), 'one query gives every student ID (used by the importer)' );

// ---- the whole import, in chunks, with mistakes inside
wipe();
$lines = array( "รหัสนิสิต,ชื่อ-สกุล,ระดับ,ชั้นปี,สถานะ,อีเมล" );
for ( $i = 1; $i <= 600; $i++ ) { $lines[] = sprintf( '88%05d,นิสิต %d,%s,%d,กำลังศึกษา,s88%05d@example.org', $i, $i, 0 === $i % 5 ? 'โท' : 'ตรี', 1 + $i % 4, $i ); }
$lines[] = ',ไม่มีรหัส,ตรี,1,กำลังศึกษา,';             // 601: no ID
$lines[] = '8800001,ซ้ำกับแถวแรก,ตรี,1,กำลังศึกษา,';       // 602: a repeat
$lines[] = '8800700,,ตรี,1,กำลังศึกษา,';                 // 603: no name
$p = mcupr_si_parse( implode( "\n", $lines ) );
$token = bin2hex( random_bytes( 16 ) );
mcupr_si_save( $token, array( 'cols' => $p['cols'], 'rows' => $p['rows'], 'unknown' => array(), 'mode' => 'add_update', 'accounts' => false, 'user' => 1, 'sample' => false, 'state' => array( 'offset' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'accounts' => 0, 'errors' => array(), 'warn' => 0 ) ) );
$steps = 0; $out = '';
do {
	ob_start(); mcupr_si_run( $token ); $out = ob_get_clean(); $steps++;
} while ( false === strpos( $out, 'step=done' ) && $steps < 10 );
$rep = get_transient( 'mcupr_si_report_1' );
t( 3 === $steps && 600 === $rep['state']['added'] && 3 === count( $rep['state']['errors'] ), 'import in chunks of ' . MCUPR_SI_CHUNK . ': 603 rows took ' . $steps . ' steps, 600 added, 3 not imported' );
t( array( 601, 602, 603 ) === array_map( function ( $e ) { return $e[0] - 1; }, $rep['state']['errors'] ) || array( 602, 603, 604 ) === array_map( function ( $e ) { return $e[0]; }, $rep['state']['errors'] ), 'the rows with mistakes are named by their line in the file' );
t( ! file_exists( mcupr_si_file( $token ) ) && 600 === mcupr_student_counts()['all'], 'the uploaded copy is deleted when it is over; the database has 600 students' );
t( 'master' === get_post_meta( mcupr_student_find( '8800005' ), '_mcupr_s_level', true ) && 'bachelor' === get_post_meta( mcupr_student_find( '8800004' ), '_mcupr_s_level', true ), 'levels came through (every fifth is Master)' );
$log = get_option( 'mcupr_student_imports' );
t( is_array( $log ) && 600 === end( $log )['added'] && 3 === end( $log )['errors'], 'the import is in the log' );
// a repeat of the same file: nothing new
mcupr_si_save( $token, array( 'cols' => $p['cols'], 'rows' => array_slice( $p['rows'], 0, 100 ), 'unknown' => array(), 'mode' => 'add_update', 'accounts' => false, 'user' => 1, 'sample' => false, 'state' => array( 'offset' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'accounts' => 0, 'errors' => array(), 'warn' => 0 ) ) );
ob_start(); mcupr_si_run( $token ); ob_get_clean();
$rep = get_transient( 'mcupr_si_report_1' );
t( 0 === $rep['state']['added'] && 100 === $rep['state']['unchanged'], 'the same rows imported again are "unchanged": no duplicates' );
t( null === mcupr_si_load( 'zzzz' ) && '' === mcupr_si_file( '../../etc/passwd' ), 'an import token cannot be used to read other files' );

// ---- accounts
wipe();
list( , $a ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '7701', 'email' => 'anna@example.org', 'name' => 'Anna' ) ), 'add_update' );
list( , $b ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '7702', 'email' => 'anna@example.org', 'name' => 'Bella' ) ), 'add_update' );
list( , $c ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '7703', 'email' => '', 'name' => 'Carl' ) ), 'add_update' );
$ua = mcupr_student_make_account( $a );
t( is_int( $ua ) && mcupr_is_student_user( $ua ) && '7701' === get_userdata( $ua )->user_login && (int) get_user_meta( $ua, 'mcupr_student_id', true ) === $a && (int) get_post_meta( $a, '_mcupr_s_user', true ) === $ua, 'a login account is made: user name = student ID, role Student, linked both ways' );
t( ! user_can( $ua, 'edit_posts' ) && ! user_can( $ua, 'manage_options' ) && ! user_can( $ua, MCUPR_STU_CAP ), 'the account can do nothing but read' );
t( $ua === mcupr_student_make_account( $a ), 'making it again finds the same account' );
t( is_wp_error( mcupr_student_make_account( $b ) ) && 'email_used' === mcupr_student_make_account( $b )->get_error_code(), 'an e-mail that another account uses is refused' );
t( is_wp_error( mcupr_student_make_account( $c ) ) && 'email' === mcupr_student_make_account( $c )->get_error_code(), 'no e-mail, no account' );
$staff = wp_insert_user( array( 'user_login' => '7704', 'user_pass' => wp_generate_password(), 'user_email' => 'staff7704@example.org', 'role' => 'editor' ) );
list( , $d ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '7704', 'email' => 'd@example.org', 'name' => 'Dan' ) ), 'add_update' );
t( is_wp_error( mcupr_student_make_account( $d ) ) && 'login_used' === mcupr_student_make_account( $d )->get_error_code(), 'a user name that a staff member already has is never taken over' );
// the link to set a password
$mails = array();
$sent  = mcupr_student_send_activation( $a );
t( true === $sent && 1 === count( $mails ) && 'anna@example.org' === $mails[0]['to'], 'the link is e-mailed to the registered address' );
t( false !== strpos( $mails[0]['subject'], '/' ) && false !== strpos( $mails[0]['message'], 'เรียน Anna' ) && false !== strpos( $mails[0]['message'], 'Dear Anna' ), 'the message is in Thai and English' );
preg_match( '/action=rp&key=([^&\s]+)&login=([^\s&]+)/', $mails[0]['message'], $m );
t( ! empty( $m[1] ) && 'anna' !== $m[2] && $ua === check_password_reset_key( $m[1], rawurldecode( $m[2] ) )->ID, 'the link in the message really is a valid password-set link for that student' );
t( ! get_user_meta( $ua, 'mcupr_activated', true ) && get_user_meta( $ua, 'mcupr_activation_sent', true ), 'before the student sets a password the account is "invited", not active' );
reset_password( get_userdata( $ua ), 'a-strong-password-1' );
t( (int) get_user_meta( $ua, 'mcupr_activated', true ) > 0, 'when the student sets a password the account becomes active' );
t( is_wp_error( mcupr_student_send_activation( $c ) ), 'no link without an account' );
t( ! mcupr_student_drop_account( $d ) , 'a record linked to nothing drops nothing' );
update_post_meta( $d, '_mcupr_s_user', $staff );
t( false === mcupr_student_drop_account( $d ) && get_userdata( $staff ), 'dropping never deletes a staff account' );
t( true === (bool) mcupr_student_drop_account( $a ) && ! get_userdata( $ua ) && ! get_post_meta( $a, '_mcupr_s_user', true ), 'dropping a student account deletes that user' );
wp_delete_user( $staff );
// throttle
$k = 'test-' . wp_generate_password( 8, false );
t( mcupr_stu_throttle( $k, 2, 60 ) && mcupr_stu_throttle( $k, 2, 60 ) && ! mcupr_stu_throttle( $k, 2, 60 ), 'guessing is slowed: the third try in the window is refused' );

// ---- the portal helpers
list( , $e ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '7705', 'email' => 'e@example.org', 'name' => 'Eve <b>X</b>', 'major' => 'Math & "Sci"' ) ), 'add_update' );
$card = mcupr_student_card_html( $e );
t( false !== strpos( $card, '7705' ) && false === strpos( $card, '<b>X' ) && false !== strpos( $card, 'Math &amp; &quot;Sci&quot;' ) && false !== strpos( $card, 'data-st="studying"' ), 'the student\'s own card: the details, escaped' );
t( preg_match( '#/student/$#', mcupr_student_portal_url() ) && 1 === mcupr_stu_opts()['portal'] && 1 === mcupr_stu_opts()['self'], 'the portal is at /student/ and on by default' );

// ---- student-only content
$ps = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวเฉพาะนิสิตทดสอบ', 'post_content' => 'secret' ) );
$pm = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวเฉพาะปริญญาโททดสอบ', 'post_content' => 'masters' ) );
$po = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวทั่วไปทดสอบ', 'post_content' => 'open' ) );
$pg = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'หน้าเฉพาะนิสิตทดสอบ' ) );
update_post_meta( $ps, MCUPR_AUD, 'students' );
update_post_meta( $pm, MCUPR_AUD, 'students' ); update_post_meta( $pm, '_mcupr_aud_levels', array( 'master', 'doctor' ) );
update_post_meta( $pg, MCUPR_AUD, 'students' );
mcupr_aud_reset();
// two students: a bachelor (studying) and a master; one graduated
$mk = function ( $sid, $level, $status ) use ( $row ) {
	list( , $rid ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => $sid, 'level' => $level, 'status' => $status, 'email' => strtolower( $sid ) . '@example.org', 'name' => 'S' . $sid ) ), 'add_update' );
	$uid = mcupr_student_make_account( $rid );
	return $uid;
};
$ub = $mk( '7711', 'bachelor', 'studying' );
$um = $mk( '7712', 'master', 'studying' );
$ug = $mk( '7713', 'bachelor', 'graduated' );
wp_set_current_user( 0 );
t( ! mcupr_aud_can_view( $ps ) && ! mcupr_aud_can_view( $pm ) && mcupr_aud_can_view( $po ), 'a visitor cannot see student-only content, and sees the rest' );
$ids = function ( $args = array() ) { $q = new WP_Query( array_merge( array( 'post_type' => array( 'post', 'page' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ), $args ) ); return $q->posts; };
$vis = $ids();
t( ! in_array( $ps, $vis, true ) && ! in_array( $pm, $vis, true ) && ! in_array( $pg, $vis, true ) && in_array( $po, $vis, true ), 'lists (home, archives, widgets) leave student-only content out for a visitor' );
t( ! in_array( $ps, $ids( array( 's' => 'ทดสอบ' ) ), true ) && in_array( $po, $ids( array( 's' => 'ทดสอบ' ) ), true ), 'search leaves it out too' );
t( array() === array_values( array_intersect( array_map( function ( $p ) { return $p->ID; }, get_pages() ), array( $pg ) ) ), 'page lists (get_pages) leave it out' );
t( array( $ps ) === get_posts( array( 'post_type' => 'post', 'name' => get_post_field( 'post_name', $ps ), 'posts_per_page' => 1, 'fields' => 'ids' ) ), 'a direct fetch is left to the page rule (below)' );
wp_set_current_user( $ub );
mcupr_aud_reset();
t( mcupr_aud_can_view( $ps ) && ! mcupr_aud_can_view( $pm ) && mcupr_aud_can_view( $pg ), 'a bachelor student sees student-only content, but not the one for Master and Doctoral' );
t( in_array( $ps, $ids(), true ) && ! in_array( $pm, $ids(), true ), 'and the lists agree' );
wp_set_current_user( $um );
mcupr_aud_reset();
t( mcupr_aud_can_view( $ps ) && mcupr_aud_can_view( $pm ), 'a master student sees both' );
wp_set_current_user( $ug );
mcupr_aud_reset();
t( ! mcupr_aud_can_view( $ps ) && ! mcupr_aud_can_view( $pm ), 'a graduated student is no longer a student for this' );
wp_set_current_user( 1 );
mcupr_aud_reset();
t( mcupr_aud_can_view( $ps ) && mcupr_aud_can_view( $pm ) && in_array( $ps, $ids(), true ), 'staff see everything' );
$reg = wp_insert_user( array( 'user_login' => 'reg_t', 'user_pass' => wp_generate_password(), 'user_email' => 'reg_t@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
wp_set_current_user( $reg );
mcupr_aud_reset();
t( mcupr_aud_can_view( $ps ), 'a registrar sees it too' );
wp_set_current_user( $ub );
mcupr_aud_reset();
$items = mcupr_aud_items();
t( in_array( $ps, wp_list_pluck( $items, 'ID' ), true ) && in_array( $pg, wp_list_pluck( $items, 'ID' ), true ) && ! in_array( $pm, wp_list_pluck( $items, 'ID' ), true ) && ! in_array( $po, wp_list_pluck( $items, 'ID' ), true ), 'the portal lists the student-only content this student may see' );
// REST
wp_set_current_user( 0 );
mcupr_aud_reset();
do_action( 'rest_api_init' );
$resp = apply_filters( 'rest_prepare_post', new WP_REST_Response( array( 'title' => array( 'rendered' => 'ข่าวเฉพาะนิสิตทดสอบ' ), 'content' => array( 'rendered' => 'secret' ), 'excerpt' => array( 'rendered' => 'secret' ) ) ), get_post( $ps ), new WP_REST_Request() );
t( '' === $resp->get_data()['content']['rendered'] && '' === $resp->get_data()['excerpt']['rendered'] && false === strpos( wp_json_encode( $resp->get_data() ), 'secret' ), 'the REST API gives no text of student-only content to others' );
$resp = apply_filters( 'rest_prepare_post', new WP_REST_Response( array( 'content' => array( 'rendered' => 'open' ) ) ), get_post( $po ), new WP_REST_Request() );
t( 'open' === $resp->get_data()['content']['rendered'], 'but open content is untouched' );
// next / previous
$w = apply_filters( 'get_next_post_where', "WHERE 1=1" );
t( false !== strpos( $w, 'p.ID NOT IN (' ) && false !== strpos( $w, (string) $ps ), 'next / previous links skip content that is not for this person' );
// the box
wp_set_current_user( 1 );
$_POST = array( 'mcupr_aud_nonce' => wp_create_nonce( 'mcupr_aud_save' ), 'mcupr_aud' => 'students', 'mcupr_aud_levels' => array( 'doctor', 'bogus' ) );
wp_set_current_user( 1 );
wp_update_post( array( 'ID' => $po, 'post_title' => 'ข่าวทั่วไปทดสอบ (แก้)' ) );
t( 'students' === get_post_meta( $po, MCUPR_AUD, true ) && array( 'doctor' ) === get_post_meta( $po, '_mcupr_aud_levels', true ) && mcupr_aud_is_restricted( $po ), 'the box saves "students only" and only real levels; the index follows at once' );
$_POST = array( 'mcupr_aud_nonce' => wp_create_nonce( 'mcupr_aud_save' ), 'mcupr_aud' => '' );
wp_update_post( array( 'ID' => $po, 'post_title' => 'ข่าวทั่วไปทดสอบ' ) );
t( '' === get_post_meta( $po, MCUPR_AUD, true ) && ! mcupr_aud_is_restricted( $po ), 'choosing "Everyone" removes it' );
$_POST = array( 'mcupr_aud' => 'students' );
wp_update_post( array( 'ID' => $po, 'post_title' => 'ข่าวทั่วไปทดสอบ' ) );
t( ! mcupr_aud_is_restricted( $po ), 'a save without the nonce changes nothing' );
$_POST = array();

// ---- roles in action
wp_set_current_user( $reg );
t( current_user_can( 'edit_mcupr_students' ) && current_user_can( 'edit_post', $b ) && current_user_can( 'delete_post', $b ) && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_post', $po ) && ! current_user_can( 'manage_options' ), 'a registrar edits students, but not news or settings' );
wp_set_current_user( $ub );
t( ! current_user_can( 'edit_post', $b ) && ! current_user_can( 'edit_mcupr_students' ) && ! current_user_can( 'edit_post', $po ), 'a student can edit nothing' );
t( false === apply_filters( 'show_admin_bar', true ), 'a student has no admin bar' );
wp_set_current_user( 1 );
t( true === apply_filters( 'show_admin_bar', true ), 'staff keep theirs' );
t( 'https://x.test/student/' === apply_filters( 'login_redirect', 'https://x.test/wp-admin/', '', get_userdata( $ub ) ) || false !== strpos( apply_filters( 'login_redirect', 'https://x.test/wp-admin/', '', get_userdata( $ub ) ), '/student/' ), 'after the usual login a student is sent to the portal' );
t( 'https://x.test/wp-admin/' === apply_filters( 'login_redirect', 'https://x.test/wp-admin/', '', get_userdata( 1 ) ), 'staff are not' );

// ---- the control page covers every function
set_current_screen( 'dashboard' );
$GLOBALS['menu'] = $GLOBALS['submenu'] = array();
do_action( 'admin_menu' );
global $submenu;
$page = function () { ob_start(); mcupr_admin_overview(); return preg_replace( '#<style>.*?</style>#s', '', ob_get_clean() ); };
$h = $page();
$miss = array();
foreach ( isset( $submenu['mcupr'] ) ? $submenu['mcupr'] : array() as $it ) {
	$slug = $it[2];
	if ( 'mcupr' === $slug || false !== strpos( $slug, 'post-new.php' ) ) { continue; }
	$url = false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
	if ( false === strpos( $h, esc_url( $url ) ) && false === strpos( $h, str_replace( '&', '&#038;', $url ) ) && false === strpos( $h, $url ) ) { $miss[] = $slug; }
}
t( ! $miss && count( $submenu['mcupr'] ) > 20, 'the control page reaches every entry of the MCUPR menu (' . count( $submenu['mcupr'] ) . ' entries; missing: ' . implode( ', ', $miss ) . ')' );
t( false !== strpos( $h, 'post_type=mcupr_student' ) && false !== strpos( $h, 'mcupr-student-import' ) && false !== strpos( $h, 'mcupr-student-settings' ), 'the Students card has the list, the import and the settings' );
wp_set_current_user( $reg );
$GLOBALS['menu'] = $GLOBALS['submenu'] = array();
do_action( 'admin_menu' );
$hr = $page();
t( false !== strpos( $hr, 'post_type=mcupr_student' ) && false === strpos( $hr, 'mcupr-speed' ) && false === strpos( $hr, 'post_type=mcupr_event' ), 'a registrar sees the Students card and none of the rest' );
wp_set_current_user( $ub );
t( '' === $page(), 'a student gets nothing from the control page' );

// ---- privacy tools
wp_set_current_user( 1 );
$ex = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$exp = call_user_func( $ex['mcupr-student']['callback'], 'e@example.org' );
t( 1 === count( $exp['data'] ) && 'Eve <b>X</b>' === $exp['data'][0]['data'][0]['value'] || 'Eve X' === $exp['data'][0]['data'][0]['value'], 'WordPress\' personal-data export includes the student record' );
$er = apply_filters( 'wp_privacy_personal_data_erasers', array() );
$res = call_user_func( $er['mcupr-student']['callback'], 'e@example.org' );
t( true === $res['items_removed'] && 0 === mcupr_student_find( '7705' ), 'WordPress\' erase tool deletes the student record' );

// ---- strings
foreach ( array( 'stu_portal', 'stu_bad', 'stu_login', 'stu_first', 'stu_sent', 'stu_hello', 'stu_only', 'stu_lv_bachelor', 'stu_st_studying', 'stu_denied_t', 'stu_mail_subject' ) as $k ) {
	$row4 = array_map( function ( $l ) use ( $k ) { return mcupr_t( $k, $l ); }, array( 'th', 'en', 'zh', 'ja' ) );
	if ( count( array_filter( $row4 ) ) < 4 || count( array_unique( $row4 ) ) < 3 ) { t( false, 'string ' . $k . ' in four languages' ); }
}
t( true, 'strings: the student texts are in four languages' );

// ---- clean up
foreach ( array( $ps, $pm, $po, $pg ) as $x ) { wp_delete_post( $x, true ); }
foreach ( array( $reg ) as $x ) { wp_delete_user( $x ); }
wipe();
foreach ( get_users( array( 'role' => MCUPR_STU_USER_ROLE ) ) as $u ) { wp_delete_user( $u->ID ); }
delete_option( 'mcupr_student_imports' );
mcupr_aud_reset();
t( 0 === mcupr_student_counts()['all'] && ! get_users( array( 'role' => MCUPR_STU_USER_ROLE ) ), 'clean again: no students, no student accounts' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
