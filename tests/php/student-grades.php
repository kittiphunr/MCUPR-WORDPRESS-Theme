<?php
/** Schedule and grades: reading the files, replacing a term, the averages, what the student sees, the calendar file, who may see it.   php php/student-grades.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() {
	foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
	delete_option( 'mcupr_sg_index' ); delete_option( 'mcupr_sg_terms' );
}
function stu( $sid, $o = array() ) {
	$d = array_merge( array( 'sid' => $sid, 'name' => 'นิสิต ' . $sid, 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2567', 'faculty' => 'คณะพุทธศาสตร์', 'major' => 'พุทธศาสตร์', 'status' => 'studying', 'email' => "g$sid@example.org", 'phone' => '', 'note' => '' ), $o );
	list( , $id ) = mcupr_student_upsert( $d, 'add_update' );
	return $id;
}
wp_set_current_user( 1 );
wipe();

// ---- values
t( '2567/1' === mcupr_sg_term( '1/2567' ) && '2567/2' === mcupr_sg_term( '2567-2' ) && '2567/1' === mcupr_sg_term( 'ภาคเรียนที่ 1/2567' ) && '' === mcupr_sg_term( '4/2567' ) && '' === mcupr_sg_term( 'abc' ), 'term: 1/2567, 2567-2 and Thai words are read; a fourth semester is not a term' );
t( 1 === mcupr_sg_day( 'จันทร์' ) && 1 === mcupr_sg_day( 'วันจันทร์' ) && 4 === mcupr_sg_day( 'พฤหัสบดี' ) && 4 === mcupr_sg_day( 'Thu' ) && 7 === mcupr_sg_day( 'อาทิตย์' ) && 0 === mcupr_sg_day( 'xyz' ), 'day: Thai and English names' );
t( 510 === mcupr_sg_time( '08:30' ) && 510 === mcupr_sg_time( '8.30' ) && 510 === mcupr_sg_time( '0830' ) && 510 === mcupr_sg_time( '8:30 น.' ) && -1 === mcupr_sg_time( '25:00' ) && -1 === mcupr_sg_time( 'noon' ), 'time: 08:30, 8.30, 0830, 8:30 น.' );
t( 'B+' === mcupr_sg_grade( ' b+ ' ) && 'AU' === mcupr_sg_grade( 'au' ) && '' === mcupr_sg_grade( 'E' ), 'grade: letters are read, an unknown one is not' );

// ---- the averages
$st = mcupr_sg_stats( array( array( 'cr' => 3, 'g' => 'A' ), array( 'cr' => 3, 'g' => 'B' ), array( 'cr' => 2, 'g' => 'F' ), array( 'cr' => 1, 'g' => 'S' ), array( 'cr' => 3, 'g' => 'W' ) ) );
t( abs( $st['credits'] - 8 ) < 1e-9 && abs( $st['points'] - 21 ) < 1e-9 && 2.63 === $st['gpa'] && abs( $st['earned'] - 7 ) < 1e-9, 'average: (3×4 + 3×3 + 2×0) / 8 = 2.63; S counts as earned but not in the average; F and W do not earn credit' );
t( null === mcupr_sg_stats( array( array( 'cr' => 3, 'g' => 'S' ) ) )['gpa'], 'average: only S/P grades give no average' );

// ---- the files
$a = stu( '9101' ); $b = stu( '9102' );
$csv = "รหัสนิสิต,ภาคเรียน,รหัสวิชา,ชื่อวิชา,หน่วยกิต,วัน,เวลาเริ่ม,เวลาสิ้นสุด,ห้อง,อาจารย์\n9101,1/2567,BUD101,พุทธศาสนา,3,จันทร์,09:00,12:00,A-201,อ.หนึ่ง\n9101,1/2567,BUD102,ปรัชญา,3,อังคาร,13:00,16:00,B-1,อ.สอง\n9102,1/2567,BUD101,พุทธศาสนา,3,จันทร์,09:00,12:00,A-201,อ.หนึ่ง\n9199,1/2567,BUD101,x,3,จันทร์,09:00,12:00,,\n9101,1/2567,BUD103,ผิด,3,จันทร์,12:00,09:00,,\n9101,x,BUD104,ผิด,3,จันทร์,09:00,10:00,,\n9101,1/2567,BUD101,พุทธศาสนา,3,จันทร์,09:00,12:00,A-201,อ.หนึ่ง\n";
$r = mcupr_sg_parse( 'sched', $csv );
t( ! is_wp_error( $r ) && 3 === count( $r['rows'] ) && 2 === count( $r['errors'] ) && array( '9199' => 1 ) === $r['missing'], 'file: 3 good rows, 2 rows with a problem (with their line), 1 student ID that is not known, the repeated row is dropped' );
t( 6 === $r['errors'][0][0] && 7 === $r['errors'][1][0], 'file: the problems say which line' );
t( is_wp_error( mcupr_sg_parse( 'sched', "รหัสนิสิต,ภาคเรียน\n1,1/2567\n" ) ) && 'columns' === mcupr_sg_parse( 'sched', "รหัสนิสิต,ภาคเรียน\n1,1/2567\n" )->get_error_code(), 'file: a missing column is named' );
t( 'empty' === mcupr_sg_parse( 'sched', '' )->get_error_code(), 'file: an empty file' );
$tis = iconv( 'UTF-8', 'CP874', "รหัสนิสิต,ภาคเรียน,รหัสวิชา,หน่วยกิต,เกรด\n9101,1/2567,BUD101,3,A\n" );
$rg  = mcupr_sg_parse( 'grades', $tis );
t( ! is_wp_error( $rg ) && 1 === count( $rg['rows'] ), 'file: a Thai Windows (TIS-620) file is read' );
t( 'grades' && mcupr_sg_parse( 'grades', "sid,term,code,credits,grade\n9101,1/2567,X1,3,Z\n" )['errors'][0][0] === 2, 'file: an unknown grade is a problem' );

// ---- importing replaces the terms in the file only
mcupr_sg_apply( 'sched', $r['rows'] );
t( 2 === count( mcupr_sg_get( $a, 'sched' ) ) && 1 === count( mcupr_sg_get( $b, 'sched' ) ), 'import: each student has their rows' );
$more = mcupr_sg_parse( 'sched', "sid,term,code,name,day,start,end\n9101,2/2567,BUD201,ธรรม,Wed,10:00,12:00\n" );
mcupr_sg_apply( 'sched', $more['rows'] );
t( 3 === count( mcupr_sg_get( $a, 'sched' ) ), 'import: a new term is added, the old one stays' );
$re = mcupr_sg_parse( 'sched', "sid,term,code,name,day,start,end\n9101,1/2567,BUD999,ใหม่,Fri,10:00,12:00\n" );
mcupr_sg_apply( 'sched', $re['rows'] );
$rows = mcupr_sg_get( $a, 'sched' );
t( 2 === count( $rows ) && in_array( 'BUD999', wp_list_pluck( $rows, 'c' ), true ) && ! in_array( 'BUD101', wp_list_pluck( $rows, 'c' ), true ) && in_array( 'BUD201', wp_list_pluck( $rows, 'c' ), true ), 'import: the term in the file is replaced, the other term stays' );
t( 1 === count( mcupr_sg_get( $b, 'sched' ) ), 'import: a student not in the file is left alone' );
$idx = mcupr_sg_index();
t( 2 === $idx['sched']['2567/1']['s'] && 1 === $idx['sched']['2567/2']['s'], 'import: the list of what exists is kept' );
$n = mcupr_sg_drop( 'sched', '2567/1' );
t( 2 === $n && 1 === count( mcupr_sg_get( $a, 'sched' ) ) && array() === mcupr_sg_get( $b, 'sched' ) && ! get_post_meta( $b, '_mcupr_s_sched', true ), 'delete: a term goes for everybody, and an empty list leaves no meta' );

// ---- grades and the page of the student
$g = mcupr_sg_parse( 'grades', "sid,term,code,name,credits,grade\n9101,1/2567,BUD101,พุทธ,3,A\n9101,1/2567,BUD102,ปรัชญา,3,B\n9101,2/2567,BUD201,ธรรม,2,F\n9101,2/2567,BUD202,สมาธิ,1,S\n" );
mcupr_sg_apply( 'grades', $g['rows'] );
$u = wp_insert_user( array( 'user_login' => 'sg-u', 'user_pass' => wp_generate_password(), 'user_email' => 'sg-u@example.org', 'role' => 'subscriber' ) );
update_user_meta( $u, 'mcupr_student_id', $a );
update_post_meta( $a, '_mcupr_s_user', $u );
wp_set_current_user( $u );
$html = mcupr_sg_portal_html( $a );
t( false !== strpos( $html, 'id="sg-grades"' ) && false !== strpos( $html, 'BUD201' ) && false !== strpos( $html, 'ธรรม' ), 'student: sees the grades' );
t( false !== strpos( $html, '3.50' ), 'student: the average of a term (A and B → 3.50)' );
t( (bool) preg_match( '/GPAX|เฉลี่ยสะสม/u', $html ) && false !== strpos( $html, '2.63' ), 'student: the cumulative average is shown (2.63)' );
$all = mcupr_sg_stats( mcupr_sg_get( $a, 'grades' ) );
t( 2.63 === $all['gpa'] && abs( $all['earned'] - 7 ) < 1e-9, 'student: cumulative = (12 + 9 + 0) / 8 = 2.63, credits earned 7 (' . $all['gpa'] . ')' );
t( false !== strpos( $html, 'id="sg-sched"' ) && false !== strpos( $html, 'BUD201' ), 'student: sees the schedule of the latest term' );
t( '' === mcupr_sg_portal_html( $b ), 'student: a student with nothing imported sees no section' );
t( false !== strpos( $html, 'data-sg-print="sg-grades"' ), 'student: has the print button' );

// ---- who else
$hit = new WP_Query( array( 'post_type' => MCUPR_STU, 's' => 'BUD201', 'post_status' => 'any' ) );
t( 0 === $hit->found_posts, 'privacy: not found by searching' );
t( ! get_post_type_object( MCUPR_STU )->show_in_rest, 'privacy: the student type is not in the REST API' );
$d = mcupr_student_data( $a );
t( false === strpos( wp_json_encode( $d ), 'BUD201' ), 'privacy: the student data used by lists and exports has no grades' );
wp_set_current_user( 0 );
$editor = wp_insert_user( array( 'user_login' => 'sg-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'sg-edi@example.org', 'role' => 'editor' ) );
$reg    = wp_insert_user( array( 'user_login' => 'sg-reg', 'user_pass' => wp_generate_password(), 'user_email' => 'sg-reg@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
t( ! user_can( $editor, MCUPR_STU_CAP ) && user_can( $reg, MCUPR_STU_CAP ), 'privacy: an editor has no access to the import, the registrar has' );
t( has_action( 'admin_post_mcupr_sg_upload' ) && ! has_action( 'admin_post_nopriv_mcupr_sg_upload' ) && ! has_action( 'admin_post_nopriv_mcupr_sg_apply' ), 'privacy: the import cannot be reached without logging in' );
wp_set_current_user( 1 );
ob_start(); mcupr_sg_staff_box( get_post( $a ) ); $box = ob_get_clean();
$ev = array_map( function ( $p ) { return $p->post_title; }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
t( false !== strpos( $box, 'BUD201' ) && in_array( 'sgview', $ev, true ), 'staff: the record shows them and opening it is in the log' );
mcupr_audit( 'sgimport', array( 'n' => 1 ) );
t( isset( mcupr_audit_actions()['sgimport'], mcupr_audit_actions()['sgdrop'], mcupr_audit_actions()['sgview'] ), 'staff: the log has names for import, delete and view' );
wp_delete_post( $b, true );

// ---- the calendar file
$tzold = get_option( 'timezone_string' ); update_option( 'timezone_string', 'Asia/Bangkok' );
update_option( 'mcupr_sg_terms', array( '2567/2' => array( 'start' => '2024-11-04', 'end' => '2025-03-07' ) ) );
$ics = mcupr_sg_ics( mcupr_sg_get( $a, 'sched' ), mcupr_sg_term_dates()['2567/2'], $a );
t( 0 === strpos( $ics, "BEGIN:VCALENDAR\r\n" ) && false !== strpos( $ics, 'RRULE:FREQ=WEEKLY;UNTIL=20250307T165959Z;BYDAY=WE' ) && false !== strpos( $ics, 'DTSTART:20241106T030000Z' ) && false !== strpos( $ics, 'DTEND:20241106T050000Z' ), 'calendar: a weekly event on Wednesday from the first Wednesday of the term, in UTC (10:00 Bangkok = 03:00Z)' );
t( false !== strpos( $ics, 'SUMMARY:BUD201 ธรรม' ), 'calendar: the course is the title' );
$wp = mcupr_sg_portal_html( $a );
t( false !== strpos( $wp, 'mcupr_sg_ics=' ), 'calendar: the link appears once the term has dates' );

update_option( 'timezone_string', $tzold );
wipe();
wp_delete_user( $u ); wp_delete_user( $editor ); wp_delete_user( $reg );
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
