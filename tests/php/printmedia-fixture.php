<?php
/** For the browser test of the printed page: a post with activity photos, a video address and a PDF shown on the page.   php php/printmedia-fixture.php <site> make|clean */
require rtrim( $argv[1], '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( array( 'e2e-print-media', 'e2e-print-media-plain' ) as $s ) { $p = get_page_by_path( $s, OBJECT, 'post' ); if ( $p ) { wp_delete_post( $p->ID, true ); } }
global $wpdb;
foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_title LIKE 'e2e-pm-%'" ) as $a ) { wp_delete_attachment( $a, true ); }
if ( 'make' === $argv[2] ) {
	$up = wp_upload_dir(); $ids = array();
	for ( $i = 1; $i <= 4; $i++ ) {
		$im = imagecreatetruecolor( 640, 480 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 40 * $i, 90, 200 - 30 * $i ) );
		$f = $up['path'] . '/' . wp_unique_filename( $up['path'], "e2e-pm-$i.jpg" ); imagejpeg( $im, $f, 80 );
		$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "e2e-pm-$i", 'post_status' => 'inherit' ), $f );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $f ) ); $ids[] = $id;
	}
	$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'e2e print media', 'post_name' => 'e2e-print-media', 'post_content' => '<p>text of the post e2e-print-text</p><p>see the <a href="https://docs.google.com/document/d/1AbCdEfGh/edit">minutes e2e-inline</a> here</p>' ) );
	update_post_meta( $post, '_mcupr_gallery', $ids );
	$pf = $up['path'] . '/' . wp_unique_filename( $up['path'], 'e2e-pm-doc.pdf' ); file_put_contents( $pf, "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF" );
	$pid = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'e2e-pm-doc', 'post_status' => 'inherit' ), $pf );
	update_post_meta( $post, '_mcupr_files', array( array( 'id' => $pid, 'title' => 'Budget report e2e', 'show' => 1 ) ) );
	update_post_meta( $post, '_mcupr_gdrive', 'Report e2e | https://drive.google.com/file/d/1QrFileId123/view' );
	update_post_meta( $post, '_mcupr_gphotos', 'https://photos.app.goo.gl/QrAlbum123' );
	update_post_meta( $post, '_mcupr_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
	$plain = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'e2e print media plain', 'post_name' => 'e2e-print-media-plain', 'post_content' => '<p>only text</p>' ) );
	echo wp_json_encode( array( 'media' => wp_parse_url( get_permalink( $post ), PHP_URL_PATH ), 'plain' => wp_parse_url( get_permalink( $plain ), PHP_URL_PATH ), 'n' => count( $ids ) ) );
} else {
	echo '{}';
}
