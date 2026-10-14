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
echo "ok $id\n";
