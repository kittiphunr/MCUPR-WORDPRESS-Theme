<?php
/** Photo albums: pictures, cover, saving, gallery markup, shortcode.   php php/albums.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$alb = get_posts( array( 'post_type' => 'mcupr_album', 'name' => 'fx-album' ) )[0];
$imgs = mcupr_album_images( $alb->ID );
t( post_type_exists( 'mcupr_album' ) && count( $imgs ) === 4, 'album: four pictures' );
t( mcupr_album_cover_id( $alb->ID ) === $imgs[0], 'album: the first picture is the cover when there is no featured image' );
set_post_thumbnail( $alb->ID, $imgs[2] );
t( mcupr_album_cover_id( $alb->ID ) === $imgs[2], 'album: a featured image wins as the cover' );
delete_post_thumbnail( $alb->ID );
$g = mcupr_album_gallery( $alb->ID );
t( substr_count( $g, 'data-lb' ) === 4 && substr_count( $g, 'data-lightbox' ) === 1 && false !== strpos( $g, 'data-cap="คำบรรยายภาพที่ 1"' ) && false !== strpos( $g, 'alt="ภาพตัวอย่าง 1"' ), 'gallery: lightbox links with captions and alt text' );
t( preg_match_all( '/aria-label="[^"]*[0-9]\/4"/', $g ) === 4, 'gallery: every link announces its position (n/4)' );
$page = get_posts( array( 'post_type' => 'page', 'posts_per_page' => 1 ) )[0];
$text = wp_insert_attachment( array( 'post_mime_type' => 'text/plain', 'post_title' => 'x', 'post_status' => 'inherit' ), '' ) ;
update_post_meta( $alb->ID, '_mcupr_album_images', array_merge( array( 999999, $text, 0 ), $imgs ) );
t( mcupr_album_images( $alb->ID ) === $imgs, 'album: missing files and non-pictures are dropped from the list' );
// saving (as an editor)
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]; wp_set_current_user( $admin->ID );
$_POST = array( 'mcupr_album_nonce' => wp_create_nonce( 'mcupr_album_save' ), 'mcupr_album' => array( (string) $imgs[3], (string) $imgs[1], (string) $imgs[3], 'abc', '999999', (string) $text ) );
do_action( 'save_post_mcupr_album', $alb->ID );
t( get_post_meta( $alb->ID, '_mcupr_album_images', true ) === array( $imgs[3], $imgs[1] ), 'save: order kept, duplicates, junk and non-pictures removed' );
$_POST = array( 'mcupr_album_nonce' => 'bad', 'mcupr_album' => array( (string) $imgs[0] ) ); do_action( 'save_post_mcupr_album', $alb->ID );
t( get_post_meta( $alb->ID, '_mcupr_album_images', true ) === array( $imgs[3], $imgs[1] ), 'save: a wrong nonce changes nothing' );
wp_set_current_user( 0 ); $_POST = array( 'mcupr_album_nonce' => wp_create_nonce( 'mcupr_album_save' ), 'mcupr_album' => array( (string) $imgs[0] ) ); do_action( 'save_post_mcupr_album', $alb->ID );
t( get_post_meta( $alb->ID, '_mcupr_album_images', true ) === array( $imgs[3], $imgs[1] ), 'save: without the right to edit nothing changes' );
$_POST = array();
update_post_meta( $alb->ID, '_mcupr_album_images', $imgs );
$sc = do_shortcode( '[mcupr_albums count="3"]' );
t( false !== strpos( $sc, 'album-card' ) && false !== strpos( $sc, 'กิจกรรมวันเปิดบ้านตัวอย่าง' ) && false !== strpos( $sc, '>4<' ) || false !== strpos( $sc, '</svg>4</span>' ), '[mcupr_albums]: cards with cover, title and the number of pictures' );
$empty = wp_insert_post( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'post_title' => 'ว่าง' ) );
t( false !== strpos( mcupr_album_gallery( $empty ), 'text-muted' ), 'gallery: an empty album says so' );
wp_delete_post( $empty, true ); wp_delete_post( $text, true );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
