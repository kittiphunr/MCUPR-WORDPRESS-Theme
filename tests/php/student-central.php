<?php
/** The details of the university's central student file: reading, encryption, codes → names, labels, who sees what.   php php/student-central.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() { foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } }
wp_set_current_user( 1 );
wipe();
delete_option( 'mcupr_sp_codes' );
$fx  = __DIR__ . '/../fixtures/';
$csv = file_get_contents( $fx . 'central-sample.csv' );

// ---- the list of fields
$f = mcupr_sp_fields();
t( 83 === count( $f ), 'fields: 83 details besides student ID, entry year and the phone (86 columns of the central file)' );
t( 10 === count( array_filter( $f, function ( $x ) { return 1 === (int) $x[2]; } ) ) && mcupr_sp_is_secret( 'idcard' ) && mcupr_sp_is_secret( 'religion' ) && mcupr_sp_is_secret( 'blood' ) && mcupr_sp_is_secret( 'birth' ) && mcupr_sp_is_secret( 'height' ) && mcupr_sp_is_secret( 'weight' ) && mcupr_sp_is_secret( 'passport' ) && mcupr_sp_is_secret( 'ref_income' ) && ! mcupr_sp_is_secret( 'home_province' ), 'fields: the sensitive ones are the ID number, blood group, date of birth, religion, height, weight, passport (3) and the guardian’s income' );
t( 11 === count( mcupr_sp_groups() ) && ! array_diff( array_unique( wp_list_pluck( $f, 1 ) ), array_keys( mcupr_sp_groups() ) ), 'fields: every field is in a known group' );

// ---- dates and values
t( '2005-11-28' === mcupr_sp_date( '28-พ.ย.-05' ) && '1985-11-28' === mcupr_sp_date( '28-พ.ย.-85' ) && '1949-01-01' === mcupr_sp_date( '01-ม.ค.-49' ) && '1930-02-11' === mcupr_sp_date( '11-ก.พ.-30' ) && '2030-02-11' === mcupr_sp_date( '11-ก.พ.-30', true ) && '2030-02-11' === mcupr_sp_clean( 'passport_expires', '11-ก.พ.-30' ), 'date: two-digit years (00–49 = 20xx, 50–99 = 19xx; a date in the future goes back a century)' );
t( '2007-03-03' === mcupr_sp_date( '2007-03-03' ) && '2007-03-03' === mcupr_sp_date( '3/3/2550' ) && '' === mcupr_sp_date( '31-ก.พ.-05' ) && '' === mcupr_sp_date( '-' ) && '' === mcupr_sp_date( 'abc' ), 'date: ISO, d/m/yyyy with a Buddhist year, impossible dates and rubbish give nothing' );
t( '' === mcupr_sp_clean( 'home_soi', '-' ) && '' === mcupr_sp_clean( 'home_soi', ' . ' ) && 'แพร่' === mcupr_sp_clean( 'birthplace', "\tแพร่ " ) && 'Bade City' === mcupr_sp_clean( 'home_province', "\tBade City" ), 'value: "-" and "." mean nothing; tabs and spaces are trimmed' );
t( '1101700999998' === mcupr_sp_clean( 'idcard', '1-1017-00999-99-8' ) && '170' === mcupr_sp_clean( 'height', '170' ) && '' === mcupr_sp_clean( 'height', 'tall' ) && '15000' === mcupr_sp_clean( 'ref_income', '15,000' ), 'value: digits and numbers' );

// ---- encryption
$e = mcupr_sp_encrypt( '1101700999998' );
t( 0 === strpos( $e, 'x:' ) && false === strpos( $e, '1101700999998' ) && '1101700999998' === mcupr_sp_decrypt( $e ), 'encryption: round trip; the number is not in the text' );
t( mcupr_sp_encrypt( 'a' ) !== mcupr_sp_encrypt( 'a' ), 'encryption: the same text gives different results each time (random IV)' );
$raw = base64_decode( substr( $e, 2 ) ); $raw[ strlen( $raw ) - 1 ] = chr( ord( $raw[ strlen( $raw ) - 1 ] ) ^ 1 );
t( '' === mcupr_sp_decrypt( 'x:' . base64_encode( $raw ) ) && '' === mcupr_sp_decrypt( 'plain' ) && '' === mcupr_sp_decrypt( 'x:abc' ), 'encryption: a changed or foreign value is not accepted (HMAC)' );

// ---- reading the central file
$p = mcupr_si_parse( $csv );
t( ! is_wp_error( $p ) && ! empty( $p['central'] ) && 4 === count( $p['rows'] ), 'file: the central file is recognised (4 students)' );
t( 11 === $p['cols']['sid'] && 0 === $p['cols']['central_id'] && ! isset( $p['cols']['name'] ), 'file: STUDENTCODE is the student ID, STUDENTID the central one, and “ชื่อ” is not taken as the whole name' );
t( ! array_diff( array_keys( mcupr_sp_fields() ), array_keys( $p['cols'] ) ), 'file: every one of the 83 columns is recognised' . ( array_diff( array_keys( mcupr_sp_fields() ), array_keys( $p['cols'] ) ) ? ' – missing: ' . implode( ',', array_diff( array_keys( mcupr_sp_fields() ), array_keys( $p['cols'] ) ) ) : '' ) );
$json = wp_json_encode( $p );
t( false === strpos( $json, '1101700999998' ) && false === strpos( $json, 'MI0000002' ) && false === strpos( $json, 'คริสต์' ) && false === strpos( $json, '15000' ) && false === strpos( $json, 'โอ' . '"' ) && false === strpos( $json, '2005-11-28' ) && false === strpos( $json, '28-พ.ย.-05' ), 'file: the sensitive values are not in what is kept for the preview (they are encrypted when the file is read)' );

// ---- the rows
$map = array(); $seen = array(); $c = array();
foreach ( $p['rows'] as $r ) { $c[] = mcupr_si_check( $r[1], $p['cols'], $map, $seen, 'add_update' ); }
$r1 = $c[0]['row']; $r2 = $c[1]['row']; $r3 = $c[2]['row']; $r4 = $c[3]['row'];
t( ! array_filter( wp_list_pluck( $c, 'errors' ) ), 'rows: no mistakes' );
t( '6999100001' === $r1['sid'] && 'พระตัวอย่างหนึ่ง ธมฺมสาโร' === $r1['name'] && 'Tuayang Thammasaro' === $r1['name_en'], 'rows: the name is title + first name + surname/monastic name; the English name too' );
t( 'พุทธศาสตร์' === $r1['faculty'] && 'พระพุทธศาสนา' === $r1['major'] && 'bachelor' === $r1['level'] && 'studying' === $r1['status'] && '2569' === $r1['entry'] && '054000001' === $r1['phone'], 'rows: faculty and programme by their codes, level and status by theirs, year of entry, telephone' );
$yr = mcupr_si_academic_year();
t( (string) ( $yr - 2569 + 1 ) === (string) $r1['year'] && (string) ( $yr - 2568 + 1 ) === (string) $r2['year'], 'rows: the year of study from the year of entry' );
t( 'นางสาวตัวอย่างสอง รักเรียน' === $r2['name'] && 'ครุศาสตร์' === $r3['faculty'] && 'สังคมศาสตร์' === $r2['faculty'], 'rows: another title and faculty' );
t( '' === (string) $r3['year'] && 1 === $r3['_overdue'] && 0 === $r1['_overdue'], 'rows: a student of 2550 gets no year of study and the label instead' );
t( '' === (string) $r3['major'] && '5010999' === $r3['_p']['program_id'], 'rows: a programme code with no name leaves the programme empty (the code is kept)' );
t( 'นายตัวอย่างสาม ตกค้าง' === $r3['name'] && 'ตัวอย่างสี่ ไม่มีคำนำหน้า' === $r4['name'], 'rows: a row without a title is fine' );
t( ! empty( $r1['_idcard'] ) && 64 === strlen( $r1['_idcard'] ) && empty( $r2['_idcard'] ) && in_array( 'idcard', $c[1]['warnings'], true ), 'rows: a valid ID number gives the sign-in hash; an invalid one is only a warning (it is still kept)' );
t( isset( $r2['_s']['idcard'], $r2['_s']['passport'], $r2['_s']['religion'], $r2['_s']['birth'] ) && ! isset( $r4['_s']['idcard'] ), 'rows: sensitive values are carried as encrypted text, only where the file has them' );
t( '' === (string) $r2['phone'], 'rows: a telephone of "-" means none' );
t( 'Bade City' === $r2['_p']['home_province'] && ! isset( $r2['_p']['home_soi'] ) && ! isset( $r2['_p']['ref_name'] ), 'rows: "-" and empty cells give no value' );

// ---- keeping them
$ids = array();
foreach ( $c as $x ) { list( , $id ) = mcupr_student_upsert( $x['row'], 'add_update', 0 ); mcupr_sp_store( $id, $x['row'] ); $ids[] = $id; }
$a = $ids[0]; $b = $ids[1]; $o = $ids[2]; $z = $ids[3];
$got = mcupr_sp_get( $a );
t( '1101700999998' === $got['idcard'] && 'โอ' === $got['blood'] && '2005-11-28' === $got['birth'] && 'พุทธ' === $got['religion'] && '170' === $got['height'] && '15000' === $got['ref_income'] && 'แพร่' === $got['home_province'] && 'วัดตัวอย่าง' === $got['wat'], 'keep: everything comes back, the sensitive ones decrypted' );
$metas = json_encode( get_post_meta( $a ), JSON_UNESCAPED_UNICODE );
t( false === strpos( $metas, '1101700999998' ) && false === strpos( $metas, 'พุทธ"' ) && false === strpos( $metas, '2005-11-28' ) && false !== strpos( $metas, 'วัดตัวอย่าง' ), 'keep: in the database the sensitive values are encrypted and the others are plain' );
t( '1' === get_post_meta( $a, '_mcupr_s_central', true ) && '1' === get_post_meta( $o, '_mcupr_s_overdue', true ) && '' === (string) get_post_meta( $a, '_mcupr_s_overdue', true ), 'keep: the labels' );
t( '1' === get_post_meta( $a, '_mcupr_s_fac_id', true ) && '6515011' === get_post_meta( $a, '_mcupr_s_prog_id', true ), 'keep: the faculty and programme codes are kept for the table of codes' );
// again with a new file: empty cells keep what is there, filled ones replace
$csv2 = str_replace( "054000001,99 วัดตัวอย่าง", "054999999,99 วัดตัวอย่าง", $csv );
$csv2 = preg_replace( '/วัดตัวอย่าง,Wat Example,/u', ',Wat Example,', $csv2 );
$p2 = mcupr_si_parse( $csv2 ); $map2 = mcupr_student_map(); $seen2 = array();
$c2 = mcupr_si_check( $p2['rows'][0][1], $p2['cols'], $map2, $seen2, 'add_update' );
mcupr_student_upsert( $c2['row'], 'add_update', $a ); $changed = mcupr_sp_store( $a, $c2['row'] );
t( 'วัดตัวอย่าง' === mcupr_sp_get( $a )['wat'] && '054999999' === get_post_meta( $a, '_mcupr_s_phone', true ), 'again: an empty cell keeps the old value, a filled one replaces it' );
$changed2 = mcupr_sp_store( $a, $c2['row'] );
t( false === $changed2, 'again: the same file again changes nothing' );
t( false !== mcupr_si_binary_kind( '' ) , 'again: (binary check still works)' );

// ---- the table of codes
$use = mcupr_sp_codes_in_use();
t( isset( $use['program']['5010999'], $use['faculty']['2'] ) && 1 === $use['program']['5010999'], 'codes: the codes in use are counted' );
update_option( 'mcupr_sp_codes', array( 'faculty' => array(), 'program' => array( '5010999' => 'สาขาที่ตั้งชื่อใหม่' ) ) );
$n = mcupr_sp_apply_codes();
t( 1 === $n && 'สาขาที่ตั้งชื่อใหม่' === get_post_meta( $o, '_mcupr_s_major', true ), 'codes: a name given to a code is put on the students with it at once' );
t( 'พระพุทธศาสนา' === get_post_meta( $a, '_mcupr_s_major', true ), 'codes: the default names are there without being saved' );
t( has_action( 'admin_post_mcupr_sp_codes' ) && ! has_action( 'admin_post_nopriv_mcupr_sp_codes' ), 'codes: only for signed-in staff' );

// ---- the label "not in the central file"
$other = stu_sid( '5000000001' );
$m = mcupr_sp_mark_missing( $ids );
t( 1 === $m && '0' === get_post_meta( $other, '_mcupr_s_central', true ) && '1' === get_post_meta( $a, '_mcupr_s_central', true ), 'labels: whoever is not in a complete central file gets “not in the central file”; nobody is changed otherwise' );
t( 'studying' === get_post_meta( $other, '_mcupr_s_status', true ) && isset( mcupr_sp_labels( $other )['central'] ) && isset( mcupr_sp_labels( $o )['overdue'] ) && ! mcupr_sp_labels( $a ), 'labels: the status of the student is untouched; the labels are shown' );
mcupr_sp_mark_missing( array_merge( $ids, array( $other ) ) );
t( '1' === get_post_meta( $other, '_mcupr_s_central', true ), 'labels: and it goes when the student is in the next file' );

// ---- who sees
ob_start(); mcupr_sp_box( get_post( $a ) ); $box = ob_get_clean();
t( false !== strpos( $box, '1101700999998' ) && false !== strpos( $box, 'วัดตัวอย่าง' ), 'staff: the record shows everything (the ID number in full)' );
$ev = array_map( function ( $p ) { return $p->post_title; }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
$lg = implode( ' ', array_map( function ( $p ) { return get_post_meta( $p->ID, '_mcupr_a', true ); }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) ) );
t( in_array( 'profileview', $ev, true ) && false === strpos( $lg, '1101700999998' ) && isset( mcupr_audit_actions()['profileview'] ), 'staff: opening it is in the log, without the details' );
$mine = mcupr_sp_html( $a, 'student', 'en' );
t( false !== strpos( $mine, '•••••••••9998' ) && false === strpos( $mine, '1101700999998' ) && false !== strpos( $mine, 'พุทธ' ) && false !== strpos( $mine, 'วัดตัวอย่าง' ), 'student: sees their own details; the ID number only by its last four digits' );
$minb = mcupr_sp_html( $b, 'student', 'th' );
t( false !== strpos( $minb, '•••••' . '0123' ) && false !== strpos( $minb, '•••••' . '0002' ) && false !== strpos( $minb, '3/3/2550' ), 'student: the passport number too; dates in the Buddhist year in Thai' );
$portal = mcupr_sp_portal_html( $a );
t( false !== strpos( $portal, 'id="sp-mine"' ) && '' === mcupr_sp_portal_html( $other ), 'student: a section in the portal, none for a student with no details' );
$editor = wp_insert_user( array( 'user_login' => 'sp-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'sp-edi@example.org', 'role' => 'editor' ) );
$reg    = wp_insert_user( array( 'user_login' => 'sp-reg', 'user_pass' => wp_generate_password(), 'user_email' => 'sp-reg@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
t( ! user_can( $editor, MCUPR_STU_CAP ) && user_can( $reg, MCUPR_STU_CAP ), 'who: an editor cannot see the details, the registrar can' );
$res = new WP_Query( array( 'post_type' => MCUPR_STU, 's' => 'Thammasaro', 'post_status' => 'any' ) );
t( 0 === $res->found_posts, 'who: not found by searching the student list for a detail' );
$x = ''; foreach ( mcupr_student_data( $a ) as $k => $v ) { $x .= $k . '=' . $v . ';'; }
t( false === strpos( $x, '1101700999998' ) && false === strpos( $x, 'พุทธ;' ), 'who: the student data used by lists and exports has none of the details' );

// ---- without encryption nothing sensitive is kept
t( mcupr_sp_can_encrypt(), 'this server can encrypt (the importer warns when one cannot)' );

wipe();
delete_option( 'mcupr_sp_codes' );
foreach ( array( $editor, $reg ) as $u ) { wp_delete_user( $u ); }
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
function stu_sid( $sid ) { list( , $id ) = mcupr_student_upsert( array( 'sid' => $sid, 'name' => 'นิสิต ' . $sid, 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2569', 'faculty' => 'ก', 'major' => 'ข', 'status' => 'studying', 'email' => '', 'phone' => '', 'note' => '' ), 'add_update' ); return $id; }
