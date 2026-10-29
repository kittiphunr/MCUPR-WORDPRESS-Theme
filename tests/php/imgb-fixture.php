<?php
/** For the browser test of "convert the pictures already in the library".   php php/imgb-fixture.php <site> make|check|clean  (prints JSON) */
$site = $argv[1]; $cmd = $argv[2];
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
global $wpdb;
if ( 'make' === $cmd ) {
	// every other picture of the site is marked "kept", so the test touches only its own
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_mime_type IN ('image/jpeg','image/png')" ) as $i ) { update_post_meta( $i, '_mcupr_img_kept', 'e2e-hold' ); }
	$ids = array(); $urls = array();
	foreach ( array( array( 'e2e-imgb-a', 3000, 2000 ), array( 'e2e-imgb-b', 2600, 1700 ) ) as $p ) {
		$im = imagecreatetruecolor( $p[1], $p[2] ); mt_srand( 5 );
		for ( $i = 0; $i < ( $p[1] * $p[2] ) / 40; $i++ ) { imagefilledellipse( $im, mt_rand( 0, $p[1] ), mt_rand( 0, $p[2] ), mt_rand( 5, 60 ), mt_rand( 5, 60 ), imagecolorallocate( $im, mt_rand( 0, 255 ), mt_rand( 0, 255 ), mt_rand( 0, 255 ) ) ); }
		$up = wp_upload_dir(); $dst = $up['path'] . '/' . wp_unique_filename( $up['path'], $p[0] . '.jpg' ); imagejpeg( $im, $dst, 95 );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => $p[0], 'post_status' => 'inherit' ), $dst );
		add_filter( 'big_image_size_threshold', '__return_false' );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dst ) );
		remove_filter( 'big_image_size_threshold', '__return_false' );
		$ids[] = $id; $urls[] = wp_get_attachment_url( $id );
	}
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'e2e-imgb-post', 'post_name' => 'e2e-imgb-post', 'post_content' => '<img class="e2e-imgb" src="' . $urls[0] . '" alt=""><img class="e2e-imgb" src="' . $urls[1] . '" alt="">' ) );
	echo wp_json_encode( array( 'ids' => $ids, 'urls' => $urls, 'post' => $post, 'link' => get_permalink( $post ) ) );
} elseif ( 'check' === $cmd ) {
	$ids = array_map( 'intval', explode( ',', $argv[3] ) ); $out = array();
	foreach ( $ids as $id ) { $f = get_attached_file( $id ); $out[] = array( 'mime' => get_post_mime_type( $id ), 'file' => basename( $f ), 'exists' => is_file( $f ), 'jpgLeft' => count( glob( dirname( $f ) . '/e2e-imgb-*.jpg' ) ) ); }
	echo wp_json_encode( $out );
} else {
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_title LIKE 'e2e-imgb-%'" ) as $i ) { wp_delete_attachment( $i, true ); }
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_name='e2e-imgb-post'" ) as $i ) { wp_delete_post( $i, true ); }
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key='_mcupr_img_kept' AND meta_value='e2e-hold'" );
	echo '{}';
}
