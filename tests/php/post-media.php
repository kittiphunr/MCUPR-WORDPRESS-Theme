<?php
/** Activity photos and PDF readers inside a post: what is chosen, what is shown, what is kept out, what is saved.   php php/post-media.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );

$img = static function ( $n ) {
	$im = imagecreatetruecolor( 400, 300 ); imagefill( $im, 0, 0, imagecolorallocate( $im, 20 * $n, 90, 160 ) );
	$u = wp_upload_bits( "pm-test-$n.jpg", null, '' ); imagejpeg( $im, $u['file'], 70 );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => "pm $n", 'post_status' => 'inherit', 'post_excerpt' => $n < 3 ? "caption $n" : '' ), $u['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $u['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', "alt $n" );
	return $id;
};
$pdf = static function ( $n ) {
	$u = wp_upload_bits( "pm-test-$n.pdf", null, "%PDF-1.4\n% test\n" );
	return wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => "pm pdf $n", 'post_status' => 'inherit' ), $u['file'] );
};
$i1 = $img( 1 ); $i2 = $img( 2 ); $i3 = $img( 3 ); $p1 = $pdf( 1 ); $p2 = $pdf( 2 );
$pid = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'pm test post', 'post_content' => '<p>text</p>' ) );
$GLOBALS['post'] = get_post( $pid ); setup_postdata( $GLOBALS['post'] );
$out = static function () use ( $pid ) { ob_start(); mcupr_attachments( $pid ); return ob_get_clean(); };

// 1. the gallery
t( '' === $out(), 'nothing chosen: nothing shown' );
update_post_meta( $pid, '_mcupr_gallery', array( $i2, $i1, 999999, $p1, $i2, $i3 ) );
t( array( $i2, $i1, $i3 ) === mcupr_post_gallery_ids( $pid ), 'gallery: keeps the order, drops a missing id, a PDF and a repeat' );
$h = $out();
t( 1 === substr_count( $h, 'class="post-gal ' ) && 1 === substr_count( $h, 'data-lightbox' ) && 3 === substr_count( $h, 'data-lb' ), 'gallery: one section, one lightbox, three pictures' );
t( preg_match( '/<a href="[^"]+pm-test-2[^"]*"[^>]*data-cap="caption 2"/', $h ) && false !== strpos( $h, 'alt="alt 1"' ), 'gallery: the caption goes to the lightbox and the alt text to the picture' );
t( false !== strpos( $h, '3' ) && false !== strpos( $h, mcupr_t( 'gallery_title' ) ), 'gallery: heading and count' );
t( 'aria-labelledby="gal-t-' . $pid . '"' === ( preg_match( '/aria-labelledby="gal-t-\d+"/', $h, $m ) ? $m[0] : '' ), 'gallery: the section is named by its heading' );

// 2. the shortcode puts it in the text, and then it is not repeated under it
$GLOBALS['post'] = get_post( $pid );
$in = do_shortcode( '[mcupr_post_gallery]' );
t( 1 === substr_count( $in, 'class="post-gal ' ), 'shortcode: the gallery comes where it is typed' );
t( '' === $out(), 'shortcode: and is not drawn a second time under the text' );

// 3. the PDF reader
update_post_meta( $pid, '_mcupr_files', array(
	array( 'id' => $p1, 'title' => 'รายงาน', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 1 ),
	array( 'id' => $p2, 'title' => 'ดาวน์โหลดอย่างเดียว', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 0 ),
	array( 'id' => $i1, 'title' => 'a picture', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 1 ),
) );
$h = $out();
t( 1 === substr_count( $h, 'data-pdfv' ), 'reader: one reader, for the PDF that is ticked (not the other PDF, not the picture)' );
t( false !== strpos( $h, 'data-src="' . esc_url( wp_get_attachment_url( $p1 ) ) . '"' ) && false !== strpos( $h, 'รายงาน' ), 'reader: it opens that file and carries its name' );
t( false !== strpos( $h, 'data-prev' ) && false !== strpos( $h, 'data-next' ) && false !== strpos( $h, 'data-in' ) && false !== strpos( $h, 'data-out' ) && false !== strpos( $h, 'data-fit' ) && false !== strpos( $h, 'data-full' ) && false !== strpos( $h, 'data-pg' ), 'reader: previous, next, page number, zoom, fit, full screen' );
t( preg_match( '/pdfv-bar"[^>]*\bhidden>/', $h ) && false !== strpos( $h, 'class="pdfv-alt"' ), 'reader: the bar waits for the script, the link to the file is always there' );
t( 3 === substr_count( $h, 'class="file-row"' ), 'download list: all three files are still listed to download' );
t( wp_script_is( 'mcupr-pdf-view', 'enqueued' ) && false !== strpos( (string) wp_scripts()->get_data( 'mcupr-pdf-view', 'data' ), 'pdf.min.js' ), 'reader: the script and the pdf.js files are queued' );
update_post_meta( $pid, '_mcupr_files', array( array( 'id' => $p2, 'title' => 'x', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 0 ) ) );
t( 0 === substr_count( $out(), 'data-pdfv' ), 'reader: no tick, no reader' );

// 4. a post with a password shows neither
update_post_meta( $pid, '_mcupr_files', array( array( 'id' => $p1, 'title' => 'รายงาน', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 1 ) ) );
wp_update_post( array( 'ID' => $pid, 'post_password' => 'secret' ) );
wp_set_current_user( 0 ); $GLOBALS['post'] = get_post( $pid );
t( '' === $out(), 'password: a protected post shows neither the photos nor the reader' );
wp_update_post( array( 'ID' => $pid, 'post_password' => '' ) );
wp_set_current_user( 1 );

// 5. saving from the editor
$_POST = array( 'mcupr_gallery_nonce' => wp_create_nonce( 'mcupr_gallery_save' ), 'mcupr_gallery' => array( (string) $i3, (string) $i1, (string) $p1, '0', (string) $i3, 'abc' ) );
mcupr_gallery_save( $pid );
t( array( $i3, $i1 ) === get_post_meta( $pid, '_mcupr_gallery', true ), 'save: only pictures, in the order given, once each' );
$_POST['mcupr_gallery'] = array();
mcupr_gallery_save( $pid );
t( '' === (string) get_post_meta( $pid, '_mcupr_gallery', true ) || ! metadata_exists( 'post', $pid, '_mcupr_gallery' ), 'save: choosing nothing clears it' );
$_POST = array( 'mcupr_gallery_nonce' => 'bad', 'mcupr_gallery' => array( (string) $i1 ) );
mcupr_gallery_save( $pid );
t( ! metadata_exists( 'post', $pid, '_mcupr_gallery' ), 'save: a wrong nonce changes nothing' );
$_POST = array( 'mcupr_files_nonce' => wp_create_nonce( 'mcupr_files_save' ), 'mcupr_files' => array(
	array( 'id' => (string) $p1, 'title' => 'a', 'show' => '1' ),
	array( 'id' => (string) $i1, 'title' => 'b', 'show' => '1' ),
	array( 'id' => (string) $p2, 'title' => 'c' ),
) );
mcupr_files_save( $pid );
$saved = get_post_meta( $pid, '_mcupr_files', true );
t( 3 === count( $saved ) && 1 === $saved[0]['show'] && 0 === $saved[1]['show'] && 0 === $saved[2]['show'], 'save: "show on the page" is kept only for a PDF that was ticked' );
$_POST = array();

// 6. the editor boxes
ob_start(); mcupr_gallery_box( get_post( $pid ) ); $box = ob_get_clean();
t( false !== strpos( $box, 'data-gal-add' ) && false !== strpos( $box, 'data-gal-list' ) && false !== strpos( $box, 'mcupr_gallery_nonce' ), 'editor: the photo box has its button, its sortable list and its nonce' );
update_post_meta( $pid, '_mcupr_gallery', array( $i1, $i2 ) );
ob_start(); mcupr_gallery_box( get_post( $pid ) ); $box = ob_get_clean();
t( 2 === substr_count( $box, 'name="mcupr_gallery[]"' ), 'editor: the chosen photos are listed with their ids' );
ob_start(); mcupr_files_box( get_post( $pid ) ); $fb = ob_get_clean();
t( substr_count( $fb, 'data-f="show"' ) >= 3 && preg_match( '/data-f="show"[^>]*name="mcupr_files\[0\]\[show\]" value="1" checked/', $fb ) && preg_match( '/name="mcupr_files\[1\]\[show\]" value="1"[^>]* disabled/', $fb ), 'editor: the PDF row has the tick (on), the picture row has it but disabled' );

// 7. albums keep working through the shared grid
$alb = wp_insert_post( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'post_title' => 'pm album' ) );
update_post_meta( $alb, '_mcupr_album_images', array( $i1, $i2 ) );
t( 2 === substr_count( mcupr_album_gallery( $alb ), 'data-lb' ) && mcupr_album_gallery( $alb ) === mcupr_gallery_list( array( $i1, $i2 ) ), 'albums: the album grid is the same grid' );

// 8. words and files
$all = include MCUPR_DIR . '/inc/strings.php';
$miss = array();
foreach ( array( 'gallery_title', 'gallery_count', 'pdf_label', 'pdf_prev', 'pdf_next', 'pdf_page', 'pdf_zoom_in', 'pdf_zoom_out', 'pdf_fit', 'pdf_full', 'pdf_open', 'pdf_loading', 'pdf_fail' ) as $k ) {
	if ( empty( $all[ $k ] ) || 4 !== count( $all[ $k ] ) || in_array( '', $all[ $k ], true ) ) { $miss[] = $k; }
}
t( ! $miss, 'words: every new text exists in Thai, English, Chinese and Japanese' . ( $miss ? ' (' . implode( ',', $miss ) . ')' : '' ) );
$js = (string) file_get_contents( get_theme_file_path( 'assets/js/pdf-view.js' ) );
t( false !== strpos( $js, 'import(cfg.lib)' ) && false !== strpos( $js, 'IntersectionObserver' ) && false !== strpos( $js, 'prefers-reduced-motion' ) && false !== strpos( $js, 'fullscreenchange' ), 'script: loads pdf.js on demand, draws what is near, respects reduced motion, follows full screen' );
foreach ( array( 'pdf.min.js', 'pdf.worker.min.js' ) as $f ) { t( is_readable( get_theme_file_path( 'assets/vendor/pdfjs/' . $f ) ), "files: $f is shipped" ); }

wp_delete_post( $pid, true ); wp_delete_post( $alb, true );
foreach ( array( $i1, $i2, $i3, $p1, $p2 ) as $a ) { wp_delete_attachment( $a, true ); }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
