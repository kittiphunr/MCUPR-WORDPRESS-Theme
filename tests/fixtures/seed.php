<?php
/**
 * Installs WordPress, activates the theme and creates fixture content:
 * 10 Thai news posts (post 1 has en/zh translations), images, a privacy page,
 * and menus for every location.
 */
define( 'WP_INSTALLING', true );
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

wp_install( 'MCU Test', 'admin', 'a@example.com', true, '', 'pass1234!', 'th' );
switch_theme( 'mcupr' );
update_option( 'permalink_structure', '/%postname%/' );

// ---- posts
$ids = array();
for ( $i = 1; $i <= 10; $i++ ) {
	$ids[ $i ] = wp_insert_post( array(
		'post_title'   => "ข่าวทดสอบ $i",
		'post_content' => "<p>เนื้อหาข่าวทดสอบหมายเลข $i สำหรับธีม MCUPR</p>",
		'post_excerpt' => "สรุปข่าว $i",
		'post_status'  => 'publish',
		// distinct times, like a real site (older WordPress finds previous/next posts only by date)
		'post_date'    => gmdate( 'Y-m-d H:i:s', time() - ( 11 - $i ) * 60 ),
	) );
}
update_post_meta( $ids[1], '_mcupr_en_title', 'Test news 1' );
update_post_meta( $ids[1], '_mcupr_en_content', '<p>English content for news 1.</p>' );
update_post_meta( $ids[1], '_mcupr_zh_title', '测试新闻 1' );
update_post_meta( $ids[1], '_mcupr_zh_content', '<p>新闻1的中文内容。</p>' );

// ---- categories + long content for reading tests
$cat_news = wp_create_category( 'ข่าวประชาสัมพันธ์' );
$cat_act  = wp_create_category( 'กิจกรรม' );
foreach ( $ids as $n => $pid ) {
	wp_set_post_categories( $pid, array( $n % 2 ? $cat_news : $cat_act ) );
}
wp_update_post( array( 'ID' => $ids[1], 'post_content' => "<p>ข้อความย่อหน้าแรกของข่าวทดสอบ ใช้สำหรับทดสอบการอ่านออกเสียงและการจัดหน้า</p><h2>หัวข้อรอง</h2><p>ย่อหน้าที่สองมีเนื้อหาเพิ่มเติม ธีมนี้แสดงวันที่เป็นพุทธศักราชตามแบบราชการไทย</p><ul><li>รายการที่หนึ่ง</li><li>รายการที่สอง</li></ul><blockquote>คำพูดสำคัญในข่าว</blockquote>" ) );
update_post_meta( $ids[3], '_mcupr_layout', 'cover' );
update_post_meta( $ids[4], '_mcupr_layout', 'sidebar' );

// ---- images (generated with GD) – skipped if GD is missing
if ( function_exists( 'imagecreatetruecolor' ) ) {
	mt_srand( 1 );
	foreach ( array_values( $ids ) as $n => $pid ) {
		$im = imagecreatetruecolor( 1600, 900 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, mt_rand( 40, 200 ), mt_rand( 40, 160 ), mt_rand( 60, 200 ) ) );
		for ( $k = 0; $k < 25; $k++ ) {
			$r = mt_rand( 40, 260 );
			imagefilledellipse( $im, mt_rand( 0, 1600 ), mt_rand( 0, 900 ), $r * 2, $r * 2, imagecolorallocate( $im, mt_rand( 30, 230 ), mt_rand( 30, 230 ), mt_rand( 30, 230 ) ) );
		}
		$tmp = wp_tempnam( 'img.jpg' );
		imagejpeg( $im, $tmp, 80 );
		$aid = media_handle_sideload( array( 'name' => "img$n.jpg", 'tmp_name' => $tmp ), $pid );
		if ( ! is_wp_error( $aid ) ) {
			set_post_thumbnail( $pid, $aid );
			if ( empty( $first ) ) { $first = $aid; }
		}
	}
	if ( ! empty( $first ) ) { set_theme_mod( 'mcupr_hero_image', $first ); }
}

// ---- pages
$privacy = wp_insert_post( array( 'post_title' => 'นโยบายความเป็นส่วนตัว', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => 'x' ) );
set_theme_mod( 'mcupr_page_privacy', $privacy );
update_option( 'mcupr_test_attachment', isset( $first ) ? $first : 0 );
$long = str_repeat( 'ประโยคยาวสำหรับทดสอบการแบ่งข้อความอ่านออกเสียงให้เป็นท่อนสั้นๆ ', 12 );
wp_update_post( array( 'ID' => $ids[5], 'post_content' => '<p>' . $long . '</p><p>English sentence one. English sentence two.</p>' ) );
$about = wp_insert_post( array( 'post_title' => 'เกี่ยวกับมหาวิทยาลัย', 'post_name' => 'about', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<p>หน้าหลักของหมวดเกี่ยวกับมหาวิทยาลัย</p>' ) );
foreach ( array( 'ประวัติ', 'ผู้บริหาร', 'วิสัยทัศน์' ) as $k => $t ) {
	wp_insert_post( array( 'post_title' => $t, 'post_name' => 'about-' . ( $k + 1 ), 'post_type' => 'page', 'post_parent' => $about, 'post_status' => 'publish', 'post_content' => '<p>เนื้อหาหน้า ' . $t . '</p><h2>หัวข้อ</h2><p>รายละเอียดเพิ่มเติม</p>' ) );
}

// ---- customizer
set_theme_mod( 'mcupr_name_th', 'มหาวิทยาลัยทดสอบ' );
set_theme_mod( 'mcupr_name_en', 'Test University' );
set_theme_mod( 'mcupr_address_th', "123 ถนนทดสอบ\nกรุงเทพฯ" );
set_theme_mod( 'mcupr_hero_l1_th', 'เพราะเราเชื่อใน' );
set_theme_mod( 'mcupr_hero_l2_th', 'พลังของคนรุ่นใหม่' );
set_theme_mod( 'mcupr_cta_title_th', 'ค้นพบประสบการณ์ชีวิตในมหาวิทยาลัย ที่มากกว่าการเรียน' );
set_theme_mod( 'mcupr_cta_url', home_url( '/' ) );
set_theme_mod( 'mcupr_popular_th', 'ค่าเทอม, บัญชี, นิเทศ, ปริญญาโท' );

// ---- menus
function mcupr_test_menu( $name, $items, $icons = array() ) {
	$menu = wp_create_nav_menu( $name );
	$n    = 0;
	foreach ( $items as $title => $kids ) {
		$args = array( 'menu-item-title' => $title, 'menu-item-url' => '/#' . md5( $title ), 'menu-item-status' => 'publish' );
		if ( isset( $icons[ $n ] ) ) { $args['menu-item-classes'] = 'icon-' . $icons[ $n ]; }
		$n++;
		$pid = wp_update_nav_menu_item( $menu, 0, $args );
		foreach ( $kids as $kid ) {
			wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => $kid, 'menu-item-url' => '/#' . md5( $title . $kid ), 'menu-item-status' => 'publish', 'menu-item-parent-id' => $pid ) );
		}
	}
	return $menu;
}
$loc = array(
	'primary_th' => mcupr_test_menu( 'Main TH', array( 'เกี่ยวกับ' => array( 'ประวัติ' ), 'ข่าว' => array() ) ),
	'quick'      => mcupr_test_menu( 'Quick', array( 'การสมัครเรียน' => array(), 'หลักสูตร' => array(), 'ทุนการศึกษา' => array(), 'ปฏิทินการศึกษา' => array(), 'ห้องสมุด' => array(), 'ศิษย์เก่า' => array(), 'ติดต่อ' => array() ), array( 'edit', 'book', 'bulb', 'money', 'bank', 'cap', 'mail' ) ),
	'footer'     => mcupr_test_menu( 'Footer', array( 'เกี่ยวกับ ' => array( 'ประวัติ', 'ผู้บริหาร' ), 'การศึกษา' => array( 'หลักสูตร', 'ทุน' ), 'วิจัย' => array( 'ศูนย์วิจัย' ) ) ),
	'utility'    => mcupr_test_menu( 'Utility', array( 'นักศึกษา' => array(), 'บุคลากร' => array(), 'ศิษย์เก่า' => array() ) ),
);
set_theme_mod( 'nav_menu_locations', $loc );

// ---- comments on post 1 (one top-level, one reply by the author)
update_option( 'default_comment_status', 'open' );
wp_update_post( array( 'ID' => $ids[1], 'comment_status' => 'open' ) );
$c1 = wp_insert_comment( array( 'comment_post_ID' => $ids[1], 'comment_author' => 'สมชาย ใจดี', 'comment_author_email' => 'somchai@example.org', 'comment_content' => 'ขอบคุณสำหรับข่าวสารครับ', 'comment_approved' => 1 ) );
wp_insert_comment( array( 'comment_post_ID' => $ids[1], 'comment_parent' => $c1, 'comment_author' => 'ทีมงาน', 'comment_author_email' => 'staff@example.org', 'comment_content' => 'ยินดีครับ', 'comment_approved' => 1 ) );

// ---- widgets (footer-3 is left empty on purpose: empty areas must render nothing)
$w = array( 'text' => array(), 'search' => array(), 'categories' => array() );
$w['text'][2]       = array( 'title' => 'ติดต่อเรา', 'text' => 'โทร 02-000-0000' );
$w['text'][3]       = array( 'title' => 'Page widget', 'text' => 'Beside pages' );
$w['text'][4]       = array( 'title' => 'Home widget', 'text' => 'Below the news' );
$w['text'][5]       = array( 'title' => 'Footer widget', 'text' => 'In the footer' );
$w['search'][2]     = array( 'title' => 'ค้นหา' );
$w['categories'][2] = array( 'title' => 'หมวดหมู่', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 );
foreach ( $w as $base => $inst ) { $inst['_multiwidget'] = 1; update_option( 'widget_' . $base, $inst ); }
update_option( 'sidebars_widgets', array(
	'wp_inactive_widgets' => array(),
	'sidebar-news' => array( 'search-2', 'text-2', 'categories-2' ),
	'sidebar-page' => array( 'text-3' ),
	'home-after'   => array( 'text-4' ),
	'footer-1'     => array( 'text-5' ),
	'footer-2'     => array(),
	'footer-3'     => array(),
) );
echo "seeded\n";
