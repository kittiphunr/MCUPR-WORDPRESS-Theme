<?php
/** Convert the pictures already in the library: the file behind an entry, the addresses everywhere, the originals gone.   php php/images-bulk.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = get_option( 'mcupr_img', null ); delete_option( 'mcupr_img' );
wp_set_current_user( 1 );
$dir = sys_get_temp_dir() . '/mcupr-imgb-test'; wp_mkdir_p( $dir ); array_map( 'unlink', glob( $dir . '/*' ) );

function pic( $dir, $name, $w, $h, $type = 'jpg' ) {
	$im = imagecreatetruecolor( $w, $h ); mt_srand( 11 );
	for ( $i = 0; $i < ( $w * $h ) / 40; $i++ ) { imagefilledellipse( $im, mt_rand( 0, $w ), mt_rand( 0, $h ), mt_rand( 5, 60 ), mt_rand( 5, 60 ), imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) ); }
	$f = "$dir/$name.$type"; 'png' === $type ? imagepng( $im, $f, 1 ) : imagejpeg( $im, $f, 95 );
	return $f;
}
/** An attachment as it was uploaded before the conversion existed (no conversion filter: the file is made by hand). */
function legacy( $dir, $name, $w, $h, $type ) {
	$src = pic( $dir, $name, $w, $h, $type );
	$up  = wp_upload_dir();
	$dst = $up['path'] . '/' . wp_unique_filename( $up['path'], basename( $src ) );
	copy( $src, $dst );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'png' === $type ? 'image/png' : 'image/jpeg', 'post_title' => $name, 'post_status' => 'inherit' ), $dst );
	add_filter( 'big_image_size_threshold', '__return_false' );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dst ) );
	remove_filter( 'big_image_size_threshold', '__return_false' );
	update_post_meta( $id, '_wp_attachment_image_alt', 'alt ' . $name );
	return $id;
}
$id    = legacy( $dir, 'imgb-photo', 3000, 2000, 'jpg' );
$old   = get_attached_file( $id );
$meta  = wp_get_attachment_metadata( $id );
$oldu  = wp_get_attachment_url( $id );
$thumb = dirname( $oldu ) . '/' . $meta['sizes']['medium']['file'];
$files = mcupr_imgb_files( $old, $meta );
$before = mcupr_imgb_bytes( $files );
t( count( $files ) >= 3 && is_file( $old ), 'setup: a 3000 px JPEG with its thumbnails is in the library (' . count( $files ) . ' files, ' . round( $before / 1024 ) . ' KB)' );
$path = wp_parse_url( $oldu, PHP_URL_PATH ); $tpath = wp_parse_url( $thumb, PHP_URL_PATH );
$pid  = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'imgb page', 'post_content' => '<img src="' . $oldu . '"> <img src="' . $thumb . '"> <img src="' . $path . '">' ) );
update_post_meta( $pid, 'imgb_serial', array( 'a' => $oldu, 'n' => array( 'x' => $thumb ), 'len' => 5 ) );
update_post_meta( $pid, 'imgb_json', wp_slash( wp_json_encode( array( 'image' => array( 'url' => $oldu ) ) ) ) ); // slashes written as \/
update_option( 'imgb_opt', array( 'logo' => $oldu, 'id' => $id ), false );
update_option( 'imgb_plain', $oldu, false );
$other = legacy( $dir, 'imgb-other', 1500, 1000, 'jpg' ); // a picture that must not be touched by the rewrite
$ou    = wp_get_attachment_url( $other );
$opid  = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'imgb other', 'post_content' => '<img src="' . $ou . '">' ) );

t( mcupr_imgb_count() >= 2, 'the page counts the pictures still to check' );
$r = mcupr_imgb_one( $id );
t( 'converted' === $r['st'], 'one picture: converted' );
$new = get_attached_file( $id ); $nm = wp_get_attachment_metadata( $id ); $nu = wp_get_attachment_url( $id );
t( '.webp' === substr( $new, -5 ) && is_readable( $new ) && 'image/webp' === get_post_mime_type( $id ) && '.webp' === substr( $nu, -5 ), 'the entry now points at a WebP file, its type is image/webp, its address is the new one' );
t( 2000 === max( getimagesize( $new )[0], getimagesize( $new )[1] ), 'cut to the longest side set (2000 px)' );
$gone = true; foreach ( $files as $f ) { if ( file_exists( $f ) ) { $gone = false; } }
t( $gone, 'the original and every old thumbnail are deleted from the disk' );
$left = true; foreach ( mcupr_imgb_files( $new, $nm ) as $f ) { if ( ! is_file( $f ) ) { $left = false; } }
t( $left && ! empty( $nm['sizes']['medium']['file'] ) && '.webp' === substr( $nm['sizes']['medium']['file'], -5 ), 'the thumbnails were made again, as WebP' );
t( $r['after'] < $r['before'] * 0.5, 'much smaller in all (' . round( $r['before'] / 1024 ) . ' KB → ' . round( $r['after'] / 1024 ) . ' KB)' );
t( 'imgb-photo' === get_the_title( $id ) && 'alt imgb-photo' === get_post_meta( $id, '_wp_attachment_image_alt', true ), 'title and alt text stay on the entry' );
$p = get_post( $pid ); $newthumb = dirname( $nu ) . '/' . $nm['sizes']['medium']['file'];
t( false === strpos( $p->post_content, basename( $old ) ) && false !== strpos( $p->post_content, $nu ) && false !== strpos( $p->post_content, $newthumb ), 'a page: the picture, its thumbnail and a path without the host all point to the new files' );
$ser = get_post_meta( $pid, 'imgb_serial', true );
t( is_array( $ser ) && $nu === $ser['a'] && $newthumb === $ser['n']['x'] && 5 === $ser['len'], 'serialised post meta is rewritten and stays valid' );
$js = get_post_meta( $pid, 'imgb_json', true );
t( false !== strpos( $js, str_replace( '/', '\/', wp_parse_url( $nu, PHP_URL_PATH ) ) ) && false === strpos( $js, basename( $old ) ) && is_array( json_decode( $js, true ) ), 'JSON with escaped slashes is rewritten and stays valid' );
$op = get_option( 'imgb_opt' );
t( $nu === $op['logo'] && $id === $op['id'] && $nu === get_option( 'imgb_plain' ), 'options (array and plain) are rewritten' );
t( false !== strpos( get_post( $opid )->post_content, $ou ), 'another picture\'s address is left alone' );
$m = get_post_meta( $id, '_mcupr_webp', true );
t( is_array( $m ) && $m['before'] === $r['before'], 'the saved size is remembered for the total' );
// a second go does nothing more to it
t( 0 === count( array_filter( mcupr_imgb_batch( $id - 1, 50 )['items'], static function ( $i ) use ( $id ) { return $i['id'] === $id; } ) ), 'a converted picture is not listed again' );

// ---- kept: small, and no gain
update_option( 'mcupr_img', array( 'on' => 1, 'quality' => 82, 'max' => 2000, 'min_kb' => 30 ) );
$s = legacy( $dir, 'imgb-small', 40, 40, 'png' );
$sf = get_attached_file( $s );
t( filesize( $sf ) < 30 * 1024 && 'kept' === mcupr_imgb_one( $s )['st'] && is_file( $sf ) && 'image/png' === get_post_mime_type( $s ), 'a small picture is kept as it is' );
$left1 = array_filter( mcupr_imgb_batch( $s - 1, 50 )['items'], static function ( $i ) use ( $s ) { return $i['id'] === $s; } );
$left2 = array_filter( mcupr_imgb_batch( $s - 1, 50, true )['items'], static function ( $i ) use ( $s ) { return $i['id'] === $s; } );
t( ! $left1 && $left2, 'a kept picture is not tried again, unless asked to check again' );
$gone2 = legacy( $dir, 'imgb-missing', 1200, 800, 'jpg' ); unlink( get_attached_file( $gone2 ) );
t( 'missing' === mcupr_imgb_one( $gone2 )['st'], 'a missing file is reported, nothing breaks' );

// ---- the ajax step needs the nonce, the right and the confirmation
$_POST = array( 'action' => 'mcupr_imgb', 'nonce' => 'bad', 'confirm' => '1' );
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new Exception( 'died' ); }; } );
add_filter( 'wp_doing_ajax', '__return_true' );
ob_start(); try { do_action( 'wp_ajax_mcupr_imgb' ); } catch ( Exception $e ) {} $out = ob_get_clean();
t( false !== strpos( $out, '"success":false' ), 'ajax: a wrong nonce is refused' );
$_POST = array( 'action' => 'mcupr_imgb', 'nonce' => wp_create_nonce( 'mcupr_imgb' ), 'after' => '0' );
ob_start(); try { do_action( 'wp_ajax_mcupr_imgb' ); } catch ( Exception $e ) {} $out = ob_get_clean();
t( false !== strpos( $out, '"success":false' ), 'ajax: without the confirmation nothing is converted' );
$_POST = array();

foreach ( array( $pid, $opid ) as $x ) { wp_delete_post( $x, true ); }
foreach ( array( $id, $other, $s, $gone2 ) as $x ) { wp_delete_attachment( $x, true ); }
delete_option( 'imgb_opt' ); delete_option( 'imgb_plain' );
if ( null === $keep ) { delete_option( 'mcupr_img' ); } else { update_option( 'mcupr_img', $keep ); }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
