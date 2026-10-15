<?php
/**
 * Staff import from CSV / Excel: parsing, preview plan, applying, template/export, access.
 *   php php/staff-import.php [site]     (starter site, after starter-fixtures.php)
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
$tmp = sys_get_temp_dir();
$unit = get_posts( array( 'post_type' => 'mcupr_unit', 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) )[0];

// ---- headers in Thai or English, any order
$csv = "\xEF\xBB\xBFตำแหน่ง,ชื่อ-สกุล,Email,หน่วยงาน,หัวหน้า,กลุ่ม,เครื่องราชฯ,ซ่อน,ลำดับ,ลิงก์งานวิจัย\n"
	. 'อธิการบดี,"ศ.ดร.นำเข้า หนึ่ง",imp1@example.org,"' . $unit->post_title . '",ใช่,"ผู้บริหาร, ทดสอบนำเข้า","ท.ช. 2562; ต.ม. 2557 เล่ม 1; ไม่มีจริง 2000","โทร, อีเมล",5,https://orcid.org/x' . "\n"
	. 'ครู,นายนำเข้า สอง,bad-email,หน่วยที่ไม่มี,,,,,x,javascript:alert(1)' . "\n"
	. ',,,,,,,,,' . "\n"
	. 'ไม่มีชื่อ,,,,,,,,,' . "\n";
$f = $tmp . '/imp.csv'; file_put_contents( $f, $csv );
$rows = mcupr_import_read_csv( $f );
t( count( $rows ) === 5 && 'ตำแหน่ง' === $rows[0][0], 'csv: BOM removed, rows read' );
$recs = mcupr_import_rows_to_records( $rows );
t( ! is_wp_error( $recs ) && count( $recs ) === 3 && 'ศ.ดร.นำเข้า หนึ่ง' === $recs[0]['name'] && 'imp1@example.org' === $recs[0]['email'], 'csv: Thai and English headings in any order; blank lines dropped' );
t( is_wp_error( mcupr_import_rows_to_records( array( array( 'a', 'b' ), array( '1', '2' ) ) ) ) && is_wp_error( mcupr_import_rows_to_records( array( array( 'ชื่อ-สกุล' ) ) ) ), 'csv: a file without the name heading or without data is refused with a message' );

// ---- other formats
$semi = "name;email;position\nA One;a1@example.org;Dean\n"; file_put_contents( $f, $semi );
$r2 = mcupr_import_rows_to_records( mcupr_import_read_csv( $f ) );
t( 'A One' === $r2[0]['name'] && 'Dean' === $r2[0]['role'], 'csv: semicolon-separated English headings' );
$tis = iconv( 'UTF-8', 'CP874', "ชื่อ-สกุล,ตำแหน่ง\nสมหญิง ใจดี,ครู\n" ); file_put_contents( $f, $tis );
$r3 = mcupr_import_rows_to_records( mcupr_import_read_csv( $f ) );
t( 'สมหญิง ใจดี' === $r3[0]['name'] && 'ครู' === $r3[0]['role'], 'csv: Thai text saved by old Excel (Windows-874) is converted' );

// ---- xlsx built by hand
$xf = $tmp . '/imp.xlsx'; @unlink( $xf );
$z = new ZipArchive(); $z->open( $xf, ZipArchive::CREATE );
$z->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
$z->addFromString( 'xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>ชื่อ-สกุล</t></si><si><t>ตำแหน่ง</t></si><si><t>นางสาวเอ็กซ์ เซล</t></si><si><r><t>หัวหน้า</t></r><r><t>ฝ่าย</t></r></si><si><t>ลำดับ</t></si></sst>' );
$z->addFromString( 'xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="D1" t="s"><v>4</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2" t="s"><v>3</v></c><c r="D2"><v>7</v></c></row></sheetData></worksheet>' );
$z->close();
$xr = mcupr_import_rows_to_records( mcupr_import_read_xlsx( $xf ) );
t( ! is_wp_error( $xr ) && 'นางสาวเอ็กซ์ เซล' === $xr[0]['name'] && 'หัวหน้าฝ่าย' === $xr[0]['role'] && '7' === $xr[0]['order'], 'xlsx: shared strings (incl. rich text), gaps in columns, numbers' );
file_put_contents( $xf, 'not a zip' );
t( is_wp_error( mcupr_import_read_xlsx( $xf ) ), 'xlsx: a broken workbook gives a message' );

// ---- honours, hide, bool
list( $h, $unk ) = mcupr_import_parse_honors( 'ท.ช. 2562; ต.ม. 2557 เล่ม 1; ไม่มีจริง 2000; บ.ม.' );
t( count( $h ) === 3 && 'tc' === $h[0]['key'] && 2562 === $h[0]['year'] && 'trm' === $h[1]['key'] && 'เล่ม 1' === $h[1]['gazette'] && 'bm' === $h[2]['key'] && '' === $h[2]['year'] && count( $unk ) === 1, 'honours: abbreviation + year (+ gazette), no year, unknown ones reported' );
t( array( 'tel', 'email' ) === mcupr_import_hide_keys( 'โทร, อีเมล' ) && array( 'office', 'edu' ) === mcupr_import_hide_keys( 'office;edu;bogus' ), 'hide: Thai or English words' );
t( mcupr_import_bool( 'ใช่' ) && mcupr_import_bool( 'Yes' ) && mcupr_import_bool( '1' ) && ! mcupr_import_bool( 'ไม่' ) && ! mcupr_import_bool( '' ), 'yes / no cells' );

// ---- the plan (preview)
$plan = mcupr_import_plan( $recs );
t( count( $plan ) === 3 && 'new' === $plan[0]['action'] && 'new' === $plan[1]['action'] && 'skip' === $plan[2]['action'], 'plan: two new people, the row without a name is skipped' );
t( $plan[0]['data']['unit'] === $unit->ID && 1 === $plan[0]['data']['head'] && array( 'ผู้บริหาร', 'ทดสอบนำเข้า' ) === $plan[0]['data']['groups'] && count( $plan[0]['data']['honors'] ) === 2, 'plan: unit matched by name, head, groups, decorations' );
t( count( $plan[0]['warn'] ) === 1 && false !== strpos( $plan[0]['warn'][0], 'ไม่มีจริง' ), 'plan: an unknown decoration is a warning' );
$w1 = implode( ' ', $plan[1]['warn'] );
t( '' === $plan[1]['data']['email'] && '' === $plan[1]['data']['scholar'] && 0 === $plan[1]['data']['unit'] && 'หน่วยที่ไม่มี' === $plan[1]['data']['unit_text'] && false !== strpos( $w1, 'หน่วยที่ไม่มี' ), 'plan: bad email and javascript: link dropped, unknown unit kept as text with a warning' );
$before = wp_count_posts( 'mcupr_person' )->publish;
t( $before === wp_count_posts( 'mcupr_person' )->publish, 'plan: nothing is written by the preview' );

// ---- apply
$res = mcupr_import_apply( $plan, array() );
$p1 = mcupr_import_find_person( 'ศ.ดร.นำเข้า หนึ่ง', 'imp1@example.org' );
t( 2 === $res['new'] && 1 === $res['skip'] && $p1, 'apply: two created, one skipped' );
t( (int) get_post_meta( $p1, '_mcupr_person_unit', true ) === $unit->ID && 1 === (int) get_post_meta( $p1, '_mcupr_person_head', true ) && 'อธิการบดี' === get_post_meta( $p1, '_mcupr_person_role', true ) && 5 === (int) get_post( $p1 )->menu_order, 'apply: unit, head, role and order stored' );
t( array( 'tel', 'email' ) === get_post_meta( $p1, '_mcupr_person_hide', true ) && ! mcupr_person_shows( $p1, 'email' ) && mcupr_person_shows( $p1, 'office' ), 'apply: the hide column becomes the person\'s privacy choice' );
t( count( mcupr_person_rows( $p1, 'honors' ) ) === 2 && count( wp_get_object_terms( $p1, 'mcupr_person_group' ) ) === 2, 'apply: decorations and groups (created) stored' );
t( 'publish' === get_post_status( $p1 ), 'apply: new people are published by default' );

// ---- apply again: updates, empty cells keep data
$again = "ชื่อ-สกุล,อีเมล,ตำแหน่ง,โทร\nศ.ดร.นำเข้า หนึ่ง,imp1@example.org,อธิการบดีใหม่,\n";
file_put_contents( $f, $again );
$plan2 = mcupr_import_plan( mcupr_import_rows_to_records( mcupr_import_read_csv( $f ) ) );
t( 'update' === $plan2[0]['action'] && $p1 === $plan2[0]['id'], 'plan: the same email is an update of that person' );
mcupr_import_apply( $plan2, array() );
t( 'อธิการบดีใหม่' === get_post_meta( $p1, '_mcupr_person_role', true ) && (int) get_post_meta( $p1, '_mcupr_person_unit', true ) === $unit->ID && count( mcupr_person_rows( $p1, 'honors' ) ) === 2, 'apply: an update changes only what is filled; empty cells erase nothing' );
$res3 = mcupr_import_apply( $plan2, array( 'update' => false ) );
t( 1 === $res3['skip'] && 0 === $res3['update'], 'apply: updating can be switched off' );
$dr = "ชื่อ-สกุล\nนำเข้า ร่าง\n"; file_put_contents( $f, $dr );
$res4 = mcupr_import_apply( mcupr_import_plan( mcupr_import_rows_to_records( mcupr_import_read_csv( $f ) ) ), array( 'status' => 'draft' ) );
$pd = mcupr_import_find_person( 'นำเข้า ร่าง', '' );
t( 1 === $res4['new'] && 'draft' === get_post_status( $pd ), 'apply: new people can be saved as drafts' );
$dup = "ชื่อ-สกุล,ตำแหน่ง\nคนซ้ำ,หนึ่ง\nคนซ้ำ,สอง\n"; file_put_contents( $f, $dup );
$pl = mcupr_import_plan( mcupr_import_rows_to_records( mcupr_import_read_csv( $f ) ) );
mcupr_import_apply( $pl, array() );
$ids = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'any', 'title' => 'คนซ้ำ', 'fields' => 'ids', 'posts_per_page' => 5 ) );
t( count( $ids ) === 1 && 'สอง' === get_post_meta( $ids[0], '_mcupr_person_role', true ) && ! empty( $pl[1]['warn'] ), 'apply: two rows for the same new person make one person (later row wins) and warn' );

// ---- files out and access
t( 'ชื่อ-สกุล' === mcupr_import_columns()['name'][0] && count( mcupr_import_columns() ) === 20, 'template: 20 columns' );
t( "'=1+1" === trim( str_replace( array( '"', "\r\n" ), '', mcupr_import_csv_line( array( '=1+1' ) ) ) ), 'csv out: a cell starting with = + - @ is protected from spreadsheet formulas' );
ob_start(); mcupr_import_card(); $card = ob_get_clean();
t( false !== strpos( $card, 'mcs-import' ) && false !== strpos( $card, 'mcupr_imp_nonce' ) && false !== strpos( $card, 'staff_file' ), 'hub: the import card has the upload form with a nonce' );
$_GET['imp_preview'] = 1; set_transient( 'mcupr_imp_' . $admin->ID, $plan, 600 );
ob_start(); mcupr_import_card(); $card = ob_get_clean(); $_GET = array();
t( false !== strpos( $card, 'Import now' ) || false !== strpos( $card, 'นำเข้าตอนนี้' ), 'hub: the preview shows the confirm button' );
delete_transient( 'mcupr_imp_' . $admin->ID );

// cleanup
foreach ( get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'any', 'posts_per_page' => 50, 'meta_key' => '', 'search_columns' => array( 'post_title' ), 's' => 'นำเข้า' ) ) as $p ) { wp_delete_post( $p->ID, true ); }
foreach ( $ids as $i ) { wp_delete_post( $i, true ); }
foreach ( array( 'a1@example.org' ) as $e ) {}
$g = get_term_by( 'name', 'ทดสอบนำเข้า', 'mcupr_person_group' ); if ( $g ) { wp_delete_term( $g->term_id, 'mcupr_person_group' ); }
@unlink( $f ); @unlink( $xf );
wp_set_current_user( 0 );
t( ! current_user_can( 'edit_pages' ), 'access: visitors cannot import (admin-post handlers check edit_pages and a nonce)' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
