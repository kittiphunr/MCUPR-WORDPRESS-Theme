<?php
/**
 * View counter, file attachments, works & awards, administration chart, Thai admin text, hero options.
 *   php php/content-tools.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function visit( $id, $type = 'post' ) {
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $id, 'post_type' => $type ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	mcupr_count_view();
}
wp_set_current_user( 0 );

// ================================================================= views
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux) Firefox/130.0';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_COOKIE = array();
$pid = wp_insert_post( array( 'post_title' => 'Views test', 'post_status' => 'publish' ) );
t( 0 === mcupr_views( $pid ), 'a new post has 0 views' );
visit( $pid ); t( 1 === mcupr_views( $pid ), 'a visit is counted' );
$_COOKIE['mcupr_seen'] = (string) $pid;
visit( $pid ); t( 1 === mcupr_views( $pid ), 'the same visitor is not counted again (cookie)' );
$_COOKIE = array();
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
visit( $pid ); t( 1 === mcupr_views( $pid ), 'search-engine robots are not counted' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Firefox/130.0';
wp_set_current_user( 1 );
visit( $pid ); t( 1 === mcupr_views( $pid ), 'editors are not counted' );
wp_set_current_user( 0 );
visit( $pid ); t( 2 === mcupr_views( $pid ), 'another visitor is counted' );
// edit the number
wp_set_current_user( 1 );
$_POST = array( 'mcupr_views_nonce' => wp_create_nonce( 'mcupr_views_save' ), 'mcupr_views_set' => '1234' );
mcupr_views_save( $pid ); $_POST = array();
t( 1234 === mcupr_views( $pid ), 'the number of views can be edited' );
$_POST = array( 'mcupr_views_set' => '5' ); mcupr_views_save( $pid ); $_POST = array();
t( 1234 === mcupr_views( $pid ), 'no nonce -> not changed' );
$GLOBALS['post'] = get_post( $pid ); setup_postdata( $GLOBALS['post'] );
t( strpos( mcupr_views_html( $pid ), '1,234' ) !== false, 'shown as a number with separators: ' . wp_strip_all_tags( mcupr_views_html( $pid ) ) );
set_theme_mod( 'mcupr_show_views', false );
t( '' === mcupr_views_html( $pid ), 'can be hidden in the Customizer' );
remove_theme_mod( 'mcupr_show_views' );
wp_set_current_user( 0 );

// ================================================================= attachments
$upload = wp_upload_bits( 'mcupr-test.pdf', null, "%PDF-1.4\n% test\n" );
$att    = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'รายงานทดสอบ', 'post_status' => 'inherit' ), $upload['file'], $pid );
$up2    = wp_upload_bits( 'mcupr-test.txt', null, str_repeat( 'x', 2048 ) );
$att2   = wp_insert_attachment( array( 'post_mime_type' => 'text/plain', 'post_title' => 'Second', 'post_status' => 'inherit' ), $up2['file'], $pid );
wp_set_current_user( 1 );
$_POST = array( 'mcupr_files_nonce' => wp_create_nonce( 'mcupr_files_save' ), 'mcupr_files' => array(
	array( 'id' => $att, 'title' => 'รายงานประจำปี', 'size' => '', 'max' => '2', 'count' => '1' ),
	array( 'id' => 999999, 'title' => 'not an attachment' ),
	array( 'id' => $att2, 'title' => '<b>Text</b>', 'size' => '2 KB', 'max' => '0', 'count' => '0' ),
) );
mcupr_files_save( $pid ); $_POST = array();
$f = mcupr_files( $pid );
t( 2 === count( $f ) && $f[0]['id'] === $att && 2 === $f[0]['max'] && 1 === $f[0]['count'], 'files saved; ids that are not attachments are dropped' );
t( false === strpos( $f[1]['title'], '<' ), 'file names are sanitised' );
$_POST = array( 'mcupr_files' => array( array( 'id' => $att ) ) ); mcupr_files_save( $pid ); $_POST = array();
t( 2 === count( mcupr_files( $pid ) ), 'no nonce -> not changed' );
wp_set_current_user( 0 );
$GLOBALS['post'] = get_post( $pid ); setup_postdata( $GLOBALS['post'] );
ob_start(); mcupr_attachments( $pid ); $html = ob_get_clean();
t( strpos( $html, 'class="files' ) !== false && strpos( $html, 'รายงานประจำปี' ) !== false && substr_count( $html, 'class="file-row"' ) === 2, 'the list shows both files' );
t( strpos( $html, 'PDF' ) !== false && strpos( $html, 'ดาวน์โหลดแล้ว 1 ครั้ง' ) !== false && strpos( $html, 'เหลืออีก 1 ครั้ง' ) !== false, 'type, download count and the remaining downloads are shown' );
t( strpos( $html, '2 KB' ) !== false, 'the size text typed by the editor is used' );
t( preg_match( '/mcupr_dl=' . $pid . '-0/', $html ) === 1, 'download links go through the counting URL' );
// limit reached
$rows = get_post_meta( $pid, '_mcupr_files', true ); $rows[0]['count'] = 2; update_post_meta( $pid, '_mcupr_files', $rows );
ob_start(); mcupr_attachments( $pid ); $html = ob_get_clean();
t( strpos( $html, 'file-full' ) !== false && strpos( $html, 'mcupr_dl=' . $pid . '-0' ) === false, 'at the limit: no download link, a notice instead' );
// password protected posts show no files
$pw = wp_insert_post( array( 'post_title' => 'Locked', 'post_status' => 'publish', 'post_password' => 'secret' ) );
update_post_meta( $pw, '_mcupr_files', array( array( 'id' => $att, 'title' => 'x', 'size' => '', 'max' => 0, 'count' => 0 ) ) );
ob_start(); mcupr_attachments( $pw ); t( '' === trim( ob_get_clean() ), 'password-protected posts do not list their files' );
ob_start(); mcupr_files_box( get_post( $pid ) ); $box = ob_get_clean();
t( strpos( $box, 'mcupr_files[0][max]' ) !== false && strpos( $box, 'id="mcupr-files-tpl"' ) !== false, 'the admin box renders the rows and a template' );

// ================================================================= works
t( post_type_exists( 'mcupr_work' ) && taxonomy_exists( 'mcupr_work_cat' ), 'works post type and its own categories exist' );
t( ! is_object_in_taxonomy( 'mcupr_work', 'category' ) && ! is_object_in_taxonomy( 'post', 'mcupr_work_cat' ), 'works and news do not share categories' );
wp_set_current_user( 1 );
$cat1 = wp_insert_term( 'การแข่งขันทดสอบ', 'mcupr_work_cat' ); $cat2 = wp_insert_term( 'นวัตกรรมทดสอบ', 'mcupr_work_cat' );
$w1 = wp_insert_post( array( 'post_type' => 'mcupr_work', 'post_title' => 'Work one', 'post_status' => 'publish' ) );
$w2 = wp_insert_post( array( 'post_type' => 'mcupr_work', 'post_title' => 'Work two', 'post_status' => 'publish' ) );
wp_set_object_terms( $w1, array( (int) $cat1['term_id'] ), 'mcupr_work_cat' ); wp_set_object_terms( $w2, array( (int) $cat2['term_id'] ), 'mcupr_work_cat' );
$_POST = array( 'mcupr_work_nonce' => wp_create_nonce( 'mcupr_work_save' ), 'mcupr_work' => array( 'event' => 'Contest <i>X</i>', 'award' => 'Gold', 'year' => '2026', 'org' => 'Org', 'team' => "A\nB", 'url' => 'javascript:alert(1)' ) );
mcupr_work_save( $w1 ); $_POST = array();
$i = mcupr_work_info( $w1 );
t( 'Gold' === $i['award'] && 2026 === $i['year'] && false === strpos( $i['event'], '<' ) && '' === $i['url'], 'work details saved and sanitised (bad link dropped)' );
$_POST = array( 'mcupr_work_nonce' => wp_create_nonce( 'mcupr_work_save' ), 'mcupr_work' => array( 'year' => '99999' ) ); mcupr_work_save( $w2 ); $_POST = array();
t( 0 === mcupr_work_info( $w2 )['year'], 'an impossible year is ignored' );
$in_news = get_posts( array( 'post_type' => 'post', 'category' => 0, 'numberposts' => -1, 'fields' => 'ids' ) );
t( ! in_array( $w1, $in_news, true ), 'a work is not part of the news list' );
$only = get_posts( array( 'post_type' => 'mcupr_work', 'tax_query' => array( array( 'taxonomy' => 'mcupr_work_cat', 'field' => 'term_id', 'terms' => (int) $cat1['term_id'] ) ), 'fields' => 'ids' ) );
t( array( $w1 ) === $only, 'a work category lists only its own works' );
t( in_array( 2026, mcupr_work_years(), true ), 'the year filter knows the years that have works' );
$html = do_shortcode( '[mcupr_works count="5" category="' . get_term( $cat1['term_id'] )->slug . '"]' );
t( strpos( $html, 'Work one' ) !== false && strpos( $html, 'Work two' ) === false && strpos( $html, 'Gold' ) !== false, '[mcupr_works category=…] lists that category with the award' );
t( 'th' !== mcupr_ui_lang() || '2569' === mcupr_work_year_text( 2026 ), 'the year is shown in the Buddhist era in Thai' );

// ================================================================= administration chart
t( post_type_exists( 'mcupr_unit' ) && is_post_type_hierarchical( 'mcupr_unit' ), 'the administration chart is a hierarchical post type' );
$top = wp_insert_post( array( 'post_type' => 'mcupr_unit', 'post_title' => 'สำนักอธิการบดี', 'post_status' => 'publish', 'menu_order' => 1 ) );
$vp1 = wp_insert_post( array( 'post_type' => 'mcupr_unit', 'post_title' => 'รองอธิการบดีฝ่ายวิชาการ', 'post_status' => 'publish', 'post_parent' => $top, 'menu_order' => 2 ) );
$vp2 = wp_insert_post( array( 'post_type' => 'mcupr_unit', 'post_title' => 'รองอธิการบดีฝ่ายบริหาร', 'post_status' => 'publish', 'post_parent' => $top, 'menu_order' => 1 ) );
$dep = wp_insert_post( array( 'post_type' => 'mcupr_unit', 'post_title' => 'กองบริการการศึกษา', 'post_status' => 'publish', 'post_parent' => $vp1 ) );
$_POST = array( 'mcupr_unit_nonce' => wp_create_nonce( 'mcupr_unit_save' ), 'mcupr_unit' => array( 'person' => 'ผศ.ดร.ทดสอบ <b>ใจดี</b>', 'role' => 'อธิการบดี', 'url' => 'https://example.org/', 'tr_en' => 'Office of the President' ) );
mcupr_unit_save( $top ); $_POST = array();
t( 'อธิการบดี' === get_post_meta( $top, '_mcupr_unit_role', true ) && false === strpos( get_post_meta( $top, '_mcupr_unit_person', true ), '<' ), 'unit details saved and sanitised' );
t( 'Office of the President' === get_post_meta( $top, '_mcupr_en_title', true ), 'unit name in another language saved' );
$tree = mcupr_org_tree();
$mine = null; foreach ( $tree as $n ) { if ( $n['post']->ID === $top ) { $mine = $n; } }
t( $mine && 2 === count( $mine['kids'] ) && $mine['kids'][0]['post']->ID === $vp2, 'children follow the order number (smaller first)' );
t( $mine['kids'][1]['kids'][0]['post']->ID === $dep, 'a third level hangs under its parent' );
$html = do_shortcode( '[mcupr_orgchart root="' . $top . '"]' );
// the root of a sub-tree starts with its children
t( strpos( $html, 'รองอธิการบดีฝ่ายวิชาการ' ) !== false && strpos( $html, 'กองบริการการศึกษา' ) !== false && strpos( $html, 'org-d1' ) !== false, '[mcupr_orgchart root=ID] draws the sub-tree' );
$html = do_shortcode( '[mcupr_orgchart]' );
t( strpos( $html, 'ผศ.ดร.ทดสอบ' ) !== false && strpos( $html, 'href="https://example.org/"' ) !== false && substr_count( $html, '<ul' ) >= 3, 'the full chart shows people, links and nested lists' );
t( preg_match( '/<ul class="org">.*org-d2/s', $html ) === 1, 'second-level boxes carry their level class' );
// a loop (A under B under A) must not hang
wp_update_post( array( 'ID' => $top, 'post_parent' => $dep ) );
$t0 = microtime( true ); mcupr_org_tree(); t( microtime( true ) - $t0 < 2, 'a circular parent setting cannot hang the page' );
wp_update_post( array( 'ID' => $top, 'post_parent' => 0 ) );
t( '' !== get_post_meta( $top, '_mcupr_en_title', true ), 'admin list columns are registered' );

// ================================================================= Thai admin text + hero
t( 'ระดับในผัง (ขั้นการบริหาร)' === __( 'Level in the chart', 'mcupr' ), 'admin text is translated to Thai' );
add_filter( 'mcupr_admin_thai', '__return_false' ); t( 'Level in the chart' === __( 'Level in the chart', 'mcupr' ), 'the Thai admin can be switched off' ); remove_filter( 'mcupr_admin_thai', '__return_false' );
t( 'ไทย' === mcupr_admin_lang_name( 'Thai' ) && 'จีน' === mcupr_admin_lang_name( 'Chinese' ), 'language names read in Thai in the admin' );
$h = mcupr_hero_options();
t( 50 === $h['overlay'] && 'medium' === $h['height'] && 'center' === $h['align'] && $h['quick'] && '' === $h['eyebrow'], 'hero defaults keep today\'s look' );
set_theme_mod( 'mcupr_hero_overlay', 999 ); set_theme_mod( 'mcupr_hero_height', 'evil' ); set_theme_mod( 'mcupr_hero_align', 'left' );
$h = mcupr_hero_options();
t( 85 === $h['overlay'] && 'medium' === $h['height'] && 'left' === $h['align'], 'hero options are clamped / validated' );
foreach ( array( 'mcupr_hero_overlay', 'mcupr_hero_height', 'mcupr_hero_align' ) as $m ) { remove_theme_mod( $m ); }

// clean up
foreach ( array( $pid, $pw, $w1, $w2, $top, $vp1, $vp2, $dep, $att, $att2 ) as $x ) { wp_delete_post( $x, true ); }
wp_delete_term( $cat1['term_id'], 'mcupr_work_cat' ); wp_delete_term( $cat2['term_id'], 'mcupr_work_cat' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
