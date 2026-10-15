<?php
/** Adds a PDF (download limit 2) and an image to the first sample work of the starter site, for the e2e test. */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$w = get_posts( array( 'post_type' => 'mcupr_work', 'name' => 'work-1' ) );
if ( ! $w ) { echo "no sample work\n"; exit( 1 ); }
$id  = $w[0]->ID;
$pdf = wp_upload_bits( 'e2e-report.pdf', null, "%PDF-1.4\n% e2e\n" );
$a1  = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'เกียรติบัตร', 'post_status' => 'inherit' ), $pdf['file'], $id );
$im  = imagecreatetruecolor( 800, 500 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 40, 90 ) );
$tmp = wp_tempnam( 'e2e.jpg' ); imagejpeg( $im, $tmp, 80 );
$up  = wp_upload_bits( 'e2e-photo.jpg', null, file_get_contents( $tmp ) );
$a2  = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'ภาพทีม', 'post_status' => 'inherit' ), $up['file'], $id );
wp_update_attachment_metadata( $a2, wp_generate_attachment_metadata( $a2, $up['file'] ) );
update_post_meta( $id, '_mcupr_files', array(
	array( 'id' => $a1, 'title' => 'เกียรติบัตรชนะเลิศ', 'size' => '', 'max' => 2, 'count' => 0 ),
	array( 'id' => $a2, 'title' => 'ภาพถ่ายทีม', 'size' => '350 KB', 'max' => 0, 'count' => 7 ),
) );
update_post_meta( $id, '_mcupr_views', 120 );
// hero: the first slide's photo + two more slides, and social links
set_theme_mod( 'mcupr_hero_image', $a2 );
foreach ( array( 2 => array( 40, 90, 160 ), 3 => array( 20, 120, 80 ) ) as $n => $rgb ) {
	$im = imagecreatetruecolor( 1200, 600 ); imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
	$u  = wp_upload_bits( "e2e-hero$n.jpg", null, '' ); imagejpeg( $im, $u['file'], 70 );
	$sid = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "hero $n", 'post_status' => 'inherit' ), $u['file'] );
	wp_update_attachment_metadata( $sid, wp_generate_attachment_metadata( $sid, $u['file'] ) );
	set_theme_mod( "mcupr_hero{$n}_image", $sid );
	set_theme_mod( "mcupr_hero{$n}_title_th", "สไลด์ที่ $n" );
	set_theme_mod( "mcupr_hero{$n}_sub_th", 'ข้อความสั้นของสไลด์' );
	set_theme_mod( "mcupr_hero{$n}_btn_th", 'ดูเพิ่มเติม' );
	set_theme_mod( "mcupr_hero{$n}_url", home_url( '/about/' ) );
}
set_theme_mod( 'mcupr_social_facebook', 'https://www.facebook.com/example' );
set_theme_mod( 'mcupr_social_line', 'https://line.me/R/ti/p/@example' );
// news browser: a zone (parent category with two children), tags and a few posts
$par = wp_insert_term( 'วิชาการ', 'category', array( 'slug' => 'academic' ) );
$par = is_wp_error( $par ) ? (int) get_term_by( 'slug', 'academic', 'category' )->term_id : (int) $par['term_id'];
$kids = array();
foreach ( array( 'research' => 'งานวิจัย', 'service' => 'บริการวิชาการ' ) as $slug => $name ) {
	$t = wp_insert_term( $name, 'category', array( 'slug' => $slug, 'parent' => $par ) );
	$kids[ $slug ] = is_wp_error( $t ) ? (int) get_term_by( 'slug', $slug, 'category' )->term_id : (int) $t['term_id'];
}
foreach ( array(
	array( 'ผลงานวิจัยดีเด่นประจำปี', 'research', array( 'วิจัย', 'รางวัล' ) ),
	array( 'โครงการบริการวิชาการแก่ชุมชน', 'service', array( 'ชุมชน', 'โครงการ' ) ),
	array( 'เปิดรับสมัครทุนวิจัย', 'research', array( 'วิจัย', 'โครงการ' ) ),
	array( 'อบรมเชิงปฏิบัติการ', 'service', array( 'โครงการ' ) ),
) as $i => $pd ) {
	wp_insert_post( array( 'post_title' => $pd[0], 'post_status' => 'publish', 'post_content' => 'ข้อความตัวอย่าง', 'post_category' => array( $kids[ $pd[1] ] ), 'tags_input' => $pd[2], 'post_date' => gmdate( 'Y-m-d H:i:s', time() - ( $i + 1 ) * 3600 ) ) );
}
// printing: a long post (several A4 pages) and letterhead graphics
$para = '';
for ( $i = 1; $i <= 40; $i++ ) { $para .= "<p>ย่อหน้าที่ $i ของข่าวยาวสำหรับทดสอบการพิมพ์ ข้อความตัวอย่างเพื่อให้เนื้อหาไหลต่อไปยังหน้ากระดาษถัดไปหลายหน้า และตรวจว่าหัวกระดาษกับท้ายกระดาษพิมพ์ซ้ำทุกแผ่น โดยเนื้อหาไม่ทับกราฟิก</p>\n"; }
$long = wp_insert_post( array( 'post_title' => 'ข่าวยาวสำหรับทดสอบการพิมพ์', 'post_name' => 'print-long', 'post_status' => 'publish', 'post_content' => $para ) );
foreach ( array( 'head' => array( 2480, 425, array( 200, 40, 90 ) ), 'foot' => array( 2480, 236, array( 30, 100, 160 ) ) ) as $k => $d ) {
	$im = imagecreatetruecolor( $d[0], $d[1] ); imagefill( $im, 0, 0, imagecolorallocate( $im, $d[2][0], $d[2][1], $d[2][2] ) );
	$u  = wp_upload_bits( "e2e-print-$k.jpg", null, '' ); imagejpeg( $im, $u['file'], 70 );
	$aid = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "print $k", 'post_status' => 'inherit' ), $u['file'] );
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $u['file'] ) );
	set_theme_mod( "mcupr_print_{$k}_img", $aid );
}
// staff hub: link people to units, decorations, education, works, privacy
$units  = get_posts( array( 'post_type' => 'mcupr_unit', 'post_status' => 'publish', 'posts_per_page' => 5, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
$people = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'posts_per_page' => 10, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
if ( $units && count( $people ) >= 4 ) {
	$p0 = $people[0]->ID; $p1 = $people[1]->ID; $p2 = $people[2]->ID;
	update_post_meta( $p0, '_mcupr_person_unit', $units[0]->ID ); update_post_meta( $p0, '_mcupr_person_head', 1 );
	update_post_meta( $p0, '_mcupr_person_rank', 'ผู้ช่วยศาสตราจารย์ ดร.' ); update_post_meta( $p0, '_mcupr_person_type', 'ข้าราชการ' );
	update_post_meta( $p0, '_mcupr_person_email', 'head@example.org' ); update_post_meta( $p0, '_mcupr_person_tel', '054-000-111' ); update_post_meta( $p0, '_mcupr_person_office', 'อาคาร 1 ห้อง 101' );
	update_post_meta( $p0, '_mcupr_person_hide', array( 'tel' ) );
	update_post_meta( $p0, '_mcupr_person_expertise', 'พุทธศาสตร์, บาลี, จริยศาสตร์' );
	update_post_meta( $p0, '_mcupr_person_edu', array( array( 'level' => 'ปริญญาเอก', 'degree' => 'พธ.ด. (พระพุทธศาสนา)', 'inst' => 'มหาวิทยาลัยมหาจุฬาลงกรณราชวิทยาลัย', 'year' => 2560 ), array( 'level' => 'ปริญญาโท', 'degree' => 'พธ.ม. (ปรัชญา)', 'inst' => 'มหาจุฬาลงกรณราชวิทยาลัย', 'year' => 2552 ) ) );
	update_post_meta( $p0, '_mcupr_person_works', array( array( 'type' => 'research', 'title' => 'งานวิจัยตัวอย่าง ก', 'year' => 2565, 'url' => 'https://example.org/a' ), array( 'type' => 'article', 'title' => 'บทความตัวอย่าง ข', 'year' => 2023, 'url' => '' ), array( 'type' => 'research', 'title' => 'งานวิจัยตัวอย่าง ค', 'year' => 2020, 'url' => '' ) ) );
	update_post_meta( $p0, '_mcupr_person_honors', array( array( 'key' => 'tc', 'year' => 2562, 'gazette' => 'เล่ม 136 ตอน 12 ข', 'note' => '' ), array( 'key' => 'trm', 'year' => 2557, 'gazette' => '', 'note' => 'ตัวอย่าง' ) ) );
	wp_update_post( array( 'ID' => $p0, 'post_content' => 'ประวัติโดยสังเขปของผู้บริหารตัวอย่าง' ) );
	update_post_meta( $p1, '_mcupr_person_profile', 'off' ); update_post_meta( $p1, '_mcupr_person_unit', $units[0]->ID );
	update_post_meta( $p2, '_mcupr_person_unit', isset( $units[1] ) ? $units[1]->ID : $units[0]->ID ); update_post_meta( $p2, '_mcupr_person_head', 1 );
	update_post_meta( $p2, '_mcupr_person_email', 'hidden@example.org' ); update_post_meta( $p2, '_mcupr_person_hide', array( 'email', 'works', 'edu' ) );
	update_post_meta( $p2, '_mcupr_person_edu', array( array( 'level' => 'x', 'degree' => 'ปริญญาลับ', 'inst' => 'y', 'year' => 2550 ) ) );
	update_post_meta( $p2, '_mcupr_person_note', 'บันทึกส่วนตัวห้ามแสดง' );
	file_put_contents( ABSPATH . 'staff-fixture.json', wp_json_encode( array( 'p0' => get_post_field( 'post_name', $p0 ), 'p1' => get_post_field( 'post_name', $p1 ), 'p2' => get_post_field( 'post_name', $p2 ), 'name0' => get_the_title( $p0 ), 'name1' => get_the_title( $p1 ), 'unit0' => get_the_title( $units[0] ) ), JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $p2, '_mcupr_person_honors', array( array( 'key' => 'bm', 'year' => 2020, 'gazette' => '', 'note' => '' ) ) );
}
echo "ok $id\n";
