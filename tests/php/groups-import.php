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
t( 'Administration Group' === get_post_meta( $g[0]->ID, '_mcupr_g_name_en', true ) && 'ส่วนงานบริหาร' === get_post_meta( $g[0]->ID, '_mcupr_g_division', true ) && false === strpos( $g[0]->post_excerpt, 'ส่วนงานบริหาร' ) && '' !== $g[0]->post_excerpt && $offices[ $names[0] ] === mcupr_group_office( $g[0]->ID ), 'import: English name, the division in its own field (not in the short text, which is the description), owned by the office' );
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
// the service tiles and the figures: from the sample (the library and finance groups), only filled in where a group has none, bad values cleaned
$lib = null; $fin = null;
foreach ( mcupr_office_groups( $offices[ $names[1] ] ) as $x ) { if ( 'กลุ่มงานห้องสมุดและสารสนเทศ' === $x->post_title ) { $lib = $x; } }
foreach ( mcupr_office_groups( $offices[ $names[0] ] ) as $x ) { if ( 'กลุ่มงานการเงิน' === $x->post_title ) { $fin = $x; } }
$ls = $lib ? get_post_meta( $lib->ID, '_mcupr_g_services', true ) : array(); $lt = $lib ? get_post_meta( $lib->ID, '_mcupr_g_stats', true ) : array(); $fs = $fin ? get_post_meta( $fin->ID, '_mcupr_g_services', true ) : array();
t( is_array( $ls ) && 4 === count( $ls ) && 0 === strpos( $ls[0]['title'], 'ยืม-คืนและต่ออายุหนังสือ' ) && 'book-open' === $ls[0]['icon'] && is_array( $lt ) && 4 === count( $lt ) && '12,480' === $lt[0]['num'] && is_array( $fs ) && 3 === count( $fs ), 'import: the service tiles and the figures of the sample are saved (4 + 4 for the library, 3 services for finance)' );
// every one of the 13 groups of the sample has its description, contact, 3-4 service tiles and 2-4 figures, all marked as examples
$all = 0; $okall = 0;
foreach ( $offices as $oid ) {
	foreach ( mcupr_office_groups( $oid ) as $x ) {
		if ( false === strpos( $x->post_title, 'กลุ่มงาน' ) || 'กลุ่มงานเก่า' === $x->post_title || 'กลุ่มงานทดสอบ' === $x->post_title || 'กลุ่มงานตัวเลข' === $x->post_title ) { continue; }
		$all++;
		$sv = get_post_meta( $x->ID, '_mcupr_g_services', true ); $st = get_post_meta( $x->ID, '_mcupr_g_stats', true );
		if ( is_array( $sv ) && count( $sv ) >= 3 && count( $sv ) <= 4 && is_array( $st ) && count( $st ) >= 2 && count( $st ) <= 4 && '' !== get_post_meta( $x->ID, '_mcupr_g_tel', true ) && false !== strpos( (string) $x->post_excerpt, '(ตัวอย่าง)' ) && false !== strpos( $sv[0]['title'], '(ตัวอย่าง)' ) && false !== strpos( $st[0]['label'], '(ตัวอย่าง)' ) && 'example.org' === substr( (string) get_post_meta( $x->ID, '_mcupr_g_email', true ), -11 ) ) { $okall++; }
	}
}
t( 13 === $all && 13 === $okall, 'sample: all 13 groups come with a description, contact, 3-4 services and 2-4 figures, each marked "(ตัวอย่าง)" and with an example.org address (' . $okall . '/' . $all . ')' );
$tab = mcupr_groups_import_table( array( array( 'office', 'name', 'service1_title', 'service1_icon', 'service1_url', 'service2_title', 'stat1_num', 'stat1_label', 'stat2_num', 'stat5_num' ), array( $names[0], 'กลุ่มงานตัวเลข', 'ขอบริการ', 'not-an-icon', 'javascript:alert(1)', '', '1,000', 'ครั้ง', '', '9' ) ) );
$rn = mcupr_groups_import_apply( $tab );
$ng = null; foreach ( mcupr_office_groups( $offices[ $names[0] ] ) as $x ) { if ( 'กลุ่มงานตัวเลข' === $x->post_title ) { $ng = $x; } }
$ns = $ng ? get_post_meta( $ng->ID, '_mcupr_g_services', true ) : array(); $nt = $ng ? get_post_meta( $ng->ID, '_mcupr_g_stats', true ) : array();
t( 1 === $rn['added'] && is_array( $ns ) && 1 === count( $ns ) && 'file-text' === $ns[0]['icon'] && '' === $ns[0]['url'] && is_array( $nt ) && 1 === count( $nt ) && '1,000' === $nt[0]['num'], 'import: an unknown icon becomes the default, a javascript: link is dropped, a row without a title or number is left out, columns beyond 4 are ignored' );
update_post_meta( $ng->ID, '_mcupr_g_services', array( array( 'title' => 'Editor made', 'text' => '', 'icon' => 'users', 'url' => '' ) ) );
$tab2 = mcupr_groups_import_table( array( array( 'office', 'name', 'service1_title', 'stat1_num', 'stat1_label' ), array( $names[0], 'กลุ่มงานตัวเลข', 'From the file', '7', 'new figure' ) ) );
mcupr_groups_import_apply( $tab2 );
t( 'Editor made' === get_post_meta( $ng->ID, '_mcupr_g_services', true )[0]['title'] && '1,000' === get_post_meta( $ng->ID, '_mcupr_g_stats', true )[0]['num'], 'import: what an editor wrote (services, figures) is not overwritten by the file' );
delete_post_meta( $ng->ID, '_mcupr_g_stats' );
$rr = mcupr_groups_import_apply( $tab2 );
t( 1 === $rr['filled'] && '7' === get_post_meta( $ng->ID, '_mcupr_g_stats', true )[0]['num'] && 'Editor made' === get_post_meta( $ng->ID, '_mcupr_g_services', true )[0]['title'], 'import: figures that were empty are filled in from the file, the services the editor wrote stay' );
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
