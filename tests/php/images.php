<?php
/** WebP on upload: conversion, limits, what is left alone, sub-sizes.   php php/images.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = get_option( 'mcupr_img', null ); delete_option( 'mcupr_img' );
$dir = sys_get_temp_dir() . '/mcupr-img-test'; wp_mkdir_p( $dir ); array_map( 'unlink', glob( $dir . '/*' ) );

/** A noisy picture (so it is big as a JPEG): returns the path. */
function pic( $dir, $name, $w, $h, $type = 'jpg', $alpha = false ) {
	$im = imagecreatetruecolor( $w, $h );
	if ( $alpha ) { imagealphablending( $im, false ); imagesavealpha( $im, true ); imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) ); imagealphablending( $im, true ); }
	mt_srand( 7 );
	for ( $i = 0; $i < ( $w * $h ) / 40; $i++ ) { imagefilledellipse( $im, mt_rand( 0, $alpha ? (int) ( $w / 2 ) : $w ), mt_rand( 0, $h ), mt_rand( 5, 60 ), mt_rand( 5, 60 ), imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) ); }
	$f = "$dir/$name.$type";
	'png' === $type ? imagepng( $im, $f, 1 ) : ( 'gif' === $type ? imagegif( $im, $f ) : imagejpeg( $im, $f, 95 ) );
	return $f;
}
$mime = array( 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' );
function up( $f ) { global $mime; $ext = pathinfo( $f, PATHINFO_EXTENSION ); return array( 'file' => $f, 'url' => 'http://example.org/wp-content/uploads/2026/10/' . basename( $f ), 'type' => $mime[ $ext ] ); }

t( mcupr_img_supported(), 'server: this test server can write WebP' );
// ---- a big photo
$f = pic( $dir, 'photo', 3000, 2000 ); $before = filesize( $f );
$r = mcupr_img_convert( up( $f ) );
t( 'image/webp' === $r['type'] && '.webp' === substr( $r['file'], -5 ) && is_readable( $r['file'] ) && ! file_exists( $f ), 'jpeg: converted to WebP, the original file is gone' );
t( 'photo.webp' === basename( $r['file'] ) && 'http://example.org/wp-content/uploads/2026/10/photo.webp' === $r['url'], 'jpeg: same name, new extension, the address follows' );
$sz = getimagesize( $r['file'] );
t( 2000 === max( $sz[0], $sz[1] ) && 'image/webp' === $sz['mime'], 'jpeg: scaled to 2000 px on the long side (' . $sz[0] . '×' . $sz[1] . ')' );
t( filesize( $r['file'] ) < $before * 0.6, 'jpeg: much smaller (' . round( $before / 1024 ) . ' KB → ' . round( filesize( $r['file'] ) / 1024 ) . ' KB)' );
// ---- png with transparency
$f = pic( $dir, 'logo', 1200, 900, 'png', true ); $pbefore = filesize( $f );
$r = mcupr_img_convert( up( $f ) );
t( 'image/webp' === $r['type'], 'png: converted (' . round( $pbefore / 1024 ) . ' KB → ' . ( is_readable( $r['file'] ) ? round( filesize( $r['file'] ) / 1024 ) : 0 ) . ' KB)' );
if ( 'image/webp' === $r['type'] ) { $im = imagecreatefromwebp( $r['file'] ); $a = ( imagecolorat( $im, 1190 * ( imagesx( $im ) / 1200 ), 5 ) >> 24 ) & 127; t( $a > 100, 'png: the transparent part is still transparent (alpha ' . $a . ')' ); }
// ---- left alone
$f = pic( $dir, 'anim', 400, 300, 'gif' ); $r = mcupr_img_convert( up( $f ) );
t( 'image/gif' === $r['type'] && file_exists( $f ), 'gif: left alone' );
$f = pic( $dir, 'tiny', 120, 90, 'jpg' ); $tiny = filesize( $f ); update_option( 'mcupr_img', array( 'min_kb' => 30 ) );
$r = mcupr_img_convert( up( $f ) );
t( 'image/jpeg' === $r['type'] && file_exists( $f ), 'small: a picture under the minimum size (' . round( $tiny / 1024 ) . ' KB) is left alone' );
$f = "$dir/already.webp"; copy( $r['file'] === $f ? $f : ( glob( "$dir/photo.webp" )[0] ), $f ); $r = mcupr_img_convert( up( $f ) );
t( 'image/webp' === $r['type'] && $r['file'] === $f && file_exists( $f ), 'webp: an upload that is already WebP is left alone' );
// flat image that does not shrink: keep original
$im = imagecreatetruecolor( 2000, 1500 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 30, 30 ) ); $f = "$dir/flat.png"; imagepng( $im, $f, 9 );
$r = mcupr_img_convert( up( $f ) );
t( ( 'image/png' === $r['type'] && file_exists( $f ) ) || 'image/webp' === $r['type'], 'flat png: either converted or kept, never lost' );
$f = pic( $dir, 'big2', 2400, 1600 ); $n = count( glob( "$dir/*" ) );
// ---- switches
update_option( 'mcupr_img', array( 'on' => 0 ) );
$r = mcupr_img_convert( up( $f ) );
t( 'image/jpeg' === $r['type'] && file_exists( $f ), 'off: when switched off nothing is converted' );
update_option( 'mcupr_img', array( 'on' => 1, 'max' => 1000, 'quality' => 60 ) );
$r = mcupr_img_convert( up( $f ) ); $sz = getimagesize( $r['file'] );
t( 'image/webp' === $r['type'] && 1000 === max( $sz[0], $sz[1] ), 'settings: the longest side follows the setting (' . $sz[0] . '×' . $sz[1] . ')' );
t( mcupr_img_opts()['quality'] === 60 && 95 === ( update_option( 'mcupr_img', array( 'quality' => 500 ) ) ? mcupr_img_opts()['quality'] : 0 ) && 800 === ( update_option( 'mcupr_img', array( 'max' => 5 ) ) ? mcupr_img_opts()['max'] : 0 ), 'settings: values are kept in range' );
update_option( 'mcupr_img', array( 'on' => 1 ) );
add_filter( 'mcupr_img_supported', '__return_false' );
$f2 = pic( $dir, 'nosupport', 2000, 1400 );
$r = mcupr_img_convert( up( $f2 ) );
t( 'image/jpeg' === $r['type'] && file_exists( $f2 ), 'no WebP support on the server: nothing happens' );
remove_filter( 'mcupr_img_supported', '__return_false' );
t( array( 'error' => 'x' ) === mcupr_img_convert( array( 'error' => 'x' ) ) && 'x' === mcupr_img_convert( 'x' ), 'errors and odd input pass through' );

// ---- the whole upload path: wp_handle_sideload → attachment → sub-sizes
$f3 = pic( $dir, 'campus', 2600, 1700 ); $copy = "$dir/campus-upload.jpg"; copy( $f3, $copy );
$fileinfo = array( 'name' => 'campus.jpg', 'type' => 'image/jpeg', 'tmp_name' => $copy, 'error' => 0, 'size' => filesize( $copy ) );
$res = wp_handle_sideload( $fileinfo, array( 'test_form' => false ) );
t( empty( $res['error'] ) && 'image/webp' === $res['type'] && 'webp' === pathinfo( $res['file'], PATHINFO_EXTENSION ), 'upload path: wp_handle_sideload returns a WebP (' . ( isset( $res['error'] ) ? $res['error'] : basename( $res['file'] ) ) . ')' );
if ( empty( $res['error'] ) ) {
	$aid = wp_insert_attachment( array( 'post_mime_type' => $res['type'], 'post_title' => 'campus', 'post_status' => 'inherit' ), $res['file'] );
	wp_update_attachment_metadata( $aid, wp_generate_attachment_metadata( $aid, $res['file'] ) );
	$meta = wp_get_attachment_metadata( $aid );
	t( 'image/webp' === get_post_mime_type( $aid ) && ! empty( $meta['sizes']['medium']['file'] ) && '.webp' === substr( $meta['sizes']['medium']['file'], -5 ), 'attachment: it is a WebP and its smaller sizes are WebP too' );
	$m = get_post_meta( $aid, '_mcupr_webp', true );
	t( is_array( $m ) && 'jpg' === $m['from'] && $m['after'] < $m['before'], 'attachment: what was saved is recorded' );
	list( $cnt, $saved ) = mcupr_img_totals();
	t( $cnt >= 1 && $saved > 0, 'totals: the settings page can show the total saved (' . $cnt . ' pictures, ' . round( $saved / 1024 ) . ' KB)' );
	wp_delete_attachment( $aid, true );
}
array_map( 'unlink', glob( $dir . '/*' ) ); @rmdir( $dir );
if ( null === $keep ) { delete_option( 'mcupr_img' ); } else { update_option( 'mcupr_img', $keep, false ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
