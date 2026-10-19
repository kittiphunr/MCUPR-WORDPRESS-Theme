<?php
/** Import of work groups from a CSV file (the chart of the Phrae campus is the sample).   php php/groups-import.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );

$names = array( 'สำนักงานวิทยาเขตแพร่', 'สำนักวิชาการวิทยาเขตแพร่', 'วิทยาลัยสงฆ์แพร่' );
$offices = array();
foreach ( $names as $n ) {
	$offices[ $n ] = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_status' => 'publish', 'post_title' => $n ) );
}
$sample = get_theme_file_path( 'starter/mcupr-work-groups.csv' );
t( is_readable( $sample ), 'sample: the file is in the theme' );
$table = mcupr_groups_import_table( mcupr_import_read_csv( $sample ) );
t( is_array( $table ) && 13 === count( $table ), 'sample: 13 rows read (the chart has 13 work groups)' );
$per = array();
foreach ( (array) $table as $r ) { $per[ $r['office'] ] = isset( $per[ $r['office'] ] ) ? $per[ $r['office'] ] + 1 : 1; }
t( 5 === $per[ $names[0] ] && 4 === $per[ $names[1] ] && 4 === $per[ $names[2] ], 'sample: 5 + 4 + 4 groups for the three offices' );

$res = mcupr_groups_import_apply( $table );
t( 13 === $res['added'] && 0 === $res['skipped'] && ! $res['problems'], 'import: 13 groups added, nothing skipped, no problems' );
$g = mcupr_office_groups( $offices[ $names[0] ] );
t( 5 === count( $g ) && 'กลุ่มงานบริหาร' === $g[0]->post_title && 'กลุ่มงานคลังและทรัพย์สิน' === $g[4]->post_title, 'import: the campus office has its 5 groups in the order of the file' );
t( 'Administration Group' === get_post_meta( $g[0]->ID, '_mcupr_g_name_en', true ) && 'ส่วนงานบริหาร' === get_post_meta( $g[0]->ID, '_mcupr_g_division', true ) && '' === $g[0]->post_excerpt && $offices[ $names[0] ] === mcupr_group_office( $g[0]->ID ), 'import: English name, the division in its own field (not in the short text), owned by the office' );
t( 4 === count( mcupr_office_groups( $offices[ $names[1] ] ) ) && 4 === count( mcupr_office_groups( $offices[ $names[2] ] ) ), 'import: the other two offices have 4 groups each' );
t( 'กลุ่มงานบริหาร' === mcupr_office_groups( $offices[ $names[2] ] )[0]->post_title, 'import: a group with the same name in another office is its own group' );

// the office home: the groups under the heading of their division, the divisions in the order of the file
$h = mcupr_office_groups_html( $offices[ $names[0] ] );
t( 2 === substr_count( $h, '<h3 class="gl-div">' ) && strpos( $h, 'ส่วนงานบริหาร</h3>' ) < strpos( $h, 'ส่วนคลังและทรัพย์สิน</h3>' ) && 5 === substr_count( $h, '<li class="gl-gcard' ), 'office home: two division headings (administration first), five group cards' );
t( strpos( $h, 'กลุ่มงานวางแผนและงบประมาณ' ) < strpos( $h, 'ส่วนคลังและทรัพย์สิน</h3>' ) && strpos( $h, 'กลุ่มงานการเงิน' ) > strpos( $h, 'ส่วนคลังและทรัพย์สิน</h3>' ), 'office home: every group is under its own division' );
// a file imported before divisions had a field kept them as the first words of the short text: importing again moves them
$old = wp_insert_post( array( 'post_type' => MCUPR_GROUP, 'post_status' => 'publish', 'post_title' => 'กลุ่มงานเก่า', 'post_excerpt' => 'ส่วนเก่า · คำอธิบายของกลุ่ม' ) );
mcupr_group_set_office( $old, $offices[ $names[1] ] );
mcupr_group_memo_reset();
$rf = mcupr_groups_import_apply( mcupr_groups_import_table( array( array( 'office', 'name', 'division' ), array( $names[1], 'กลุ่มงานเก่า', 'ส่วนเก่า' ) ) ) );
t( 1 === $rf['filled'] && 'ส่วนเก่า' === get_post_meta( $old, '_mcupr_g_division', true ) && 'คำอธิบายของกลุ่ม' === get_post( $old )->post_excerpt, 'import: an existing group gets its empty division, and it leaves the short text' );
$rf2 = mcupr_groups_import_apply( mcupr_groups_import_table( array( array( 'office', 'name', 'division' ), array( $names[1], 'กลุ่มงานเก่า', 'ส่วนอื่น' ) ) ) );
t( 0 === $rf2['filled'] && 1 === $rf2['skipped'] && 'ส่วนเก่า' === get_post_meta( $old, '_mcupr_g_division', true ), 'import: a division that is already set is not overwritten' );
$band = ''; $_d = mcupr_group_data( $old ); ob_start(); echo mcupr_group_band( $old, $_d ); $band = ob_get_clean();
t( false !== strpos( $band, 'ส่วนเก่า' ), 'landing page: the band names the division' );
$again = mcupr_groups_import_apply( $table );
t( 0 === $again['added'] && 13 === $again['skipped'], 'import: a second time nothing is added (13 already there)' );

mcupr_group_memo_reset();
$t2 = mcupr_groups_import_table( array( array( 'Office', 'NAME', 'tel' ), array( $offices[ $names[0] ], 'กลุ่มงานทดสอบ', '054-111111' ), array( 'ไม่มีหน่วยงานนี้', 'กลุ่มงานผี', '' ), array( $names[1], '', '' ) ) );
$r2 = mcupr_groups_import_apply( $t2 );
t( 1 === $r2['added'] && 2 === count( $r2['problems'] ), 'import: header names in any case, an office by number, an unknown office and an empty name are reported' );
t( null === mcupr_groups_import_table( array( array( 'a', 'b' ), array( '1', '2' ) ) ), 'import: a file without the columns office and name is refused' );
$dup = mcupr_groups_import_table( array( array( 'office', 'name' ), array( $names[2], 'กลุ่มงานซ้ำ' ), array( $names[2], 'กลุ่มงาน ซ้ำ' ) ) );
$r3 = mcupr_groups_import_apply( $dup );
t( 1 === $r3['added'] && 1 === $r3['skipped'], 'import: the same name twice in one file (spaces ignored) makes one group' );

ob_start(); mcupr_groups_import_page(); $page = ob_get_clean();
t( false !== strpos( $page, 'name="csv"' ) && false !== strpos( $page, 'value="mcupr_groups_import"' ) && false !== strpos( $page, 'name="mcupr_gi_nonce"' ) && false === stripos( $page, 'warning' ), 'screen: the upload form, the sample button and the nonce are there' );
foreach ( get_posts( array( 'post_type' => 'mcupr_group', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) as $id ) { wp_delete_post( $id, true ); }
foreach ( $offices as $id ) { wp_delete_post( $id, true ); }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
