<?php
/** E-books: type, settings, shelf styles, PDF and picture books, the reader markup, the shelf, shortcodes, the guarded stream (student-only books, Range).   php php/books.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function side( $f ) { $tmp = wp_tempnam( $f ); copy( $f, $tmp ); $id = media_handle_sideload( array( 'name' => basename( $f ), 'tmp_name' => $tmp ), 0 ); return is_wp_error( $id ) ? 0 : $id; }
$fx = __DIR__ . '/../fixtures/';
wp_set_current_user( 1 );

t( post_type_exists( 'mcupr_book' ) && taxonomy_exists( 'mcupr_shelf' ), 'the e-book type and the bookshelf taxonomy exist' );
t( array_keys( mcupr_book_styles() ) === array( 'wood', 'dark', 'glass', 'white', 'metal', 'custom' ), 'six shelf styles' );
delete_option( 'mcupr_books_opts' );
t( mcupr_books_opts() === array( 'style' => 'wood', 'custom' => 0, 'view' => 'shelf', 'sound' => 1, 'download' => 1 ), 'settings default: wood, shelf view, sound on, download on' );
update_option( 'mcupr_books_opts', array( 'style' => 'nope', 'view' => 'x' ) );
t( mcupr_books_opts()['style'] === 'wood' && mcupr_books_opts()['view'] === 'shelf', 'unknown style / view fall back to the defaults' );
delete_option( 'mcupr_books_opts' );

$pdf  = side( $fx . 'book.pdf' );
$imgs = array();
for ( $i = 1; $i <= 6; $i++ ) { $imgs[] = side( $fx . "book-page-$i.png" ); }
t( $pdf && 6 === count( array_filter( $imgs ) ), 'fixtures uploaded' );

foreach ( get_posts( array( 'post_type' => 'mcupr_book', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $x ) { wp_delete_post( $x, true ); }
foreach ( get_terms( array( 'taxonomy' => 'mcupr_shelf', 'hide_empty' => false, 'fields' => 'ids' ) ) as $x ) { wp_delete_term( $x, 'mcupr_shelf' ); }
$shelf = wp_insert_term( 'Shelf T', 'mcupr_shelf' );
$b1 = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'PDF Book' ) );
update_post_meta( $b1, '_mcupr_bk_src', 'pdf' ); update_post_meta( $b1, '_mcupr_bk_pdf', $pdf ); update_post_meta( $b1, '_mcupr_bk_author', 'Ann' );
wp_set_object_terms( $b1, (int) $shelf['term_id'], 'mcupr_shelf' );
$b2 = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'Picture Book' ) );
update_post_meta( $b2, '_mcupr_bk_src', 'images' ); update_post_meta( $b2, '_mcupr_bk_imgs', $imgs );
$b3 = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'Empty Book' ) );
update_post_meta( $b3, '_mcupr_bk_src', 'pdf' );

t( 'pdf' === mcupr_book_src( $b1 ) && 'images' === mcupr_book_src( $b2 ), 'book source: pdf / images' );
t( mcupr_book_pdf_id( $b1 ) === $pdf && 0 === mcupr_book_pdf_id( $b2 ), 'the PDF id is read only for a PDF' );
t( mcupr_book_image_ids( $b2 ) === array_map( 'intval', $imgs ), 'the picture ids keep their order' );
update_post_meta( $b2, '_mcupr_bk_imgs', array_merge( $imgs, array( $pdf, 999999 ) ) );
t( count( mcupr_book_image_ids( $b2 ) ) === 6, 'a PDF or a missing id is not taken as a picture' );
update_post_meta( $b2, '_mcupr_bk_imgs', $imgs );
t( '' !== mcupr_book_cover_url( $b2 ), 'a picture book uses its first picture as the cover' );

// ---- the reader
$h = mcupr_book_reader( $b1 );
t( false !== strpos( $h, 'class="bk-reader"' ) && false !== strpos( $h, 'data-book' ) && false !== strpos( $h, 'pdf.min.mjs' ), 'PDF reader: markup with the PDF.js address' );
t( false !== strpos( $h, ' download>' ), 'PDF reader: download is offered by default' );
update_post_meta( $b1, '_mcupr_bk_dl', '0' );
t( false === strpos( mcupr_book_reader( $b1 ), ' download>' ), 'PDF reader: the book can hide the download button' );
delete_post_meta( $b1, '_mcupr_bk_dl' );
update_option( 'mcupr_books_opts', array( 'download' => 0 ) );
t( false === strpos( mcupr_book_reader( $b1 ), ' download>' ), 'PDF reader: the setting can hide the download button' );
delete_option( 'mcupr_books_opts' );
$h2 = mcupr_book_reader( $b2 );
t( false !== strpos( $h2, '&quot;pages&quot;' ) && false === strpos( $h2, ' download>' ), 'picture reader: pages listed, no download' );
t( '' === mcupr_book_reader( $b3 ), 'a book with no file shows no reader' );
t( wp_script_is( 'mcupr-pageflip', 'enqueued' ) && wp_script_is( 'mcupr-book-reader', 'enqueued' ), 'the page-flip and reader scripts are enqueued' );
foreach ( array( 'assets/vendor/pageflip/page-flip.browser.js', 'assets/vendor/pdfjs/pdf.min.mjs', 'assets/vendor/pdfjs/pdf.worker.min.mjs', 'assets/js/book-reader.js', 'assets/js/book-shelf.js' ) as $f ) {
	t( is_readable( get_theme_file_path( $f ) ), 'file shipped: ' . $f );
}

// ---- shelf and shortcodes
$s = mcupr_book_shelf();
t( false !== strpos( $s, 'bk-shelf-app' ) && false !== strpos( $s, 'PDF Book' ) && false !== strpos( $s, 'Shelf T' ) && false !== strpos( $s, 'bk-vbtn' ), 'the shelf lists the books by bookshelf with the two views' );
t( false !== strpos( $s, 'bks-wood' ), 'the shelf has the wooden style by default' );
update_option( 'mcupr_books_opts', array( 'style' => 'metal' ) );
t( false !== strpos( mcupr_book_shelf(), 'bks-metal' ), 'the style comes from the settings' );
update_term_meta( (int) $shelf['term_id'], 'mcupr_style', 'glass' );
t( 'glass' === mcupr_book_shelf_style( (int) $shelf['term_id'] ), 'a bookshelf can have its own style' );
t( false !== strpos( mcupr_book_shelf(), 'bks-glass' ) && false !== strpos( mcupr_book_shelf(), 'bks-metal' ), 'rows use their own style, others the settings one' );
delete_option( 'mcupr_books_opts' );
t( false !== strpos( do_shortcode( '[mcupr_shelf style="dark"]' ), 'bks-dark' ), '[mcupr_shelf style=""] overrides the style' );
t( false !== strpos( do_shortcode( '[mcupr_book id="' . $b2 . '"]' ), 'bk-reader' ), '[mcupr_book id=""] prints the reader' );
t( '' === do_shortcode( '[mcupr_book id="999999"]' ), '[mcupr_book] with an unknown id prints nothing' );
$css = mcupr_book_shelf_css();
t( is_string( $css ), 'the shelf CSS can be built' );

// ---- the guarded stream (needs the site to answer over HTTP)
$get = static function ( $url, $headers = array() ) { return wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 0, 'headers' => $headers ) ); };
$u   = mcupr_book_file_url( $b1, $pdf );
t( false !== strpos( $u, '.pdf' ), 'a public book gives the file its own address' );
$probe = $get( home_url( '/' ) );
if ( is_wp_error( $probe ) ) {
	echo "SKIP the stream checks (the site does not answer over HTTP here)\n";
} else {
	update_post_meta( $b1, MCUPR_AUD, 'students' );
	mcupr_aud_reset();
	t( mcupr_book_restricted( $b1 ), 'a book marked for students is restricted' );
	$ru = mcupr_book_file_url( $b1, $pdf );
	t( false !== strpos( $ru, 'mcupr_bf=' . $b1 . '-' . $pdf ), 'a restricted book gives its file through the guarded address' );
	$r = $get( $ru );
	t( ! is_wp_error( $r ) && 403 === wp_remote_retrieve_response_code( $r ), 'guarded stream: a visitor gets 403' );
	t( ! is_wp_error( $r ) && false === strpos( wp_remote_retrieve_body( $r ), '%PDF' ), 'guarded stream: nothing of the file is sent' );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $imgs[0], home_url( '/' ) ) );
	t( ! is_wp_error( $r ) && in_array( wp_remote_retrieve_response_code( $r ), array( 403, 404 ), true ), 'guarded stream: a file that is not the book\'s is refused' );
	delete_post_meta( $b1, MCUPR_AUD );
	mcupr_aud_reset();
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ) );
	t( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && 0 === strpos( wp_remote_retrieve_body( $r ), '%PDF' ), 'stream: an open book is sent (200, %PDF)' );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ), array( 'Range' => 'bytes=0-9' ) );
	t( ! is_wp_error( $r ) && 206 === wp_remote_retrieve_response_code( $r ) && 10 === strlen( wp_remote_retrieve_body( $r ) ) && 0 === strpos( (string) wp_remote_retrieve_header( $r, 'content-range' ), 'bytes 0-9/' ), 'stream: Range gives 206 with Content-Range' );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ), array( 'Range' => 'bytes=99999999-' ) );
	t( ! is_wp_error( $r ) && 416 === wp_remote_retrieve_response_code( $r ), 'stream: a Range past the end gives 416' );
}

// ---- strings in every language (th, en, zh, ja)
$src = file_get_contents( get_theme_file_path( 'inc/strings.php' ) );
preg_match_all( "/'(bk_[a-z0-9_]+)' => array\\( ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\") \\)/", $src, $mm );
t( count( $mm[1] ) >= 30, 'e-book words have all four languages (' . count( $mm[1] ) . ' keys)' );
foreach ( array( $b1, $b2, $b3 ) as $x ) { wp_delete_post( $x, true ); }
wp_delete_term( (int) $shelf['term_id'], 'mcupr_shelf' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
