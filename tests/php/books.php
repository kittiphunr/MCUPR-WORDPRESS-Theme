<?php
/** E-books: type, settings, shelf styles, PDF and picture books, the reader markup, the shelf, shortcodes, the guarded stream (student-only books, Range).   php php/books.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
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
t( mcupr_books_opts() === array( 'style' => 'wood', 'custom' => 0, 'view' => 'shelf', 'sound' => 1, 'download' => 1, 'fx' => 'webgl', 'fx_set' => 0, 'mode' => 'embed', 'bg' => '#1f2430' ), 'settings default: wood, shelf view, sound on, download on, WebGL turning (not yet chosen by an admin), embedded, dark background' );
t( array_keys( mcupr_book_fx_list() ) === array( 'css3d', 'webgl', 'classic' ), 'three ways to turn a page: CSS 3D, WebGL, classic' );
update_option( 'mcupr_books_opts', array( 'fx' => 'webgl' ) );
t( 'webgl' === mcupr_books_opts()['fx'] && 1 === mcupr_books_opts()['fx_set'], 'the way to turn can be set to WebGL, and then it counts as chosen' );
update_option( 'mcupr_books_opts', array( 'fx' => 'css3d' ) );
t( 'css3d' === mcupr_books_opts()['fx'] && 1 === mcupr_books_opts()['fx_set'], 'the way to turn can be set to CSS 3D' );
update_option( 'mcupr_books_opts', array( 'fx' => 'flash' ) );
t( 'css3d' === mcupr_books_opts()['fx'], 'an unknown way to turn falls back to CSS 3D' );
// the admin is asked to choose until the settings are saved
delete_option( 'mcupr_books_opts' );
set_current_screen( 'dashboard' );
ob_start(); do_action( 'admin_notices' ); $nt = ob_get_clean();
t( false !== strpos( $nt, 'admin.php?page=mcupr-books' ) && false !== strpos( $nt, 'WebGL' ), 'the admin is told to choose how the pages turn (on the dashboard), with a link to the settings' );
set_current_screen( "mcupr_page_mcupr-books" );
ob_start(); do_action( 'admin_notices' ); $nt2 = ob_get_clean();
t( false !== strpos( $nt2, 'notice-info' ) && false === strpos( $nt2, esc_html__( 'Choose now', 'mcupr' ) ), 'the settings page says it too, without a link to itself' );
set_current_screen( 'edit-post' );
ob_start(); do_action( 'admin_notices' ); $nt3 = ob_get_clean();
t( false === strpos( $nt3, 'mcupr-books' ), 'other screens are left alone' );
update_option( 'mcupr_books_opts', array( 'fx' => 'webgl' ) );
set_current_screen( 'dashboard' );
ob_start(); do_action( 'admin_notices' ); $nt4 = ob_get_clean();
t( false === strpos( $nt4, 'admin.php?page=mcupr-books' ), 'once the settings are saved the notice is gone' );
delete_option( 'mcupr_books_opts' );
$ed0 = wp_insert_user( array( 'user_login' => 'bk-editor0', 'user_pass' => wp_generate_password(), 'user_email' => 'bk-editor0@example.org', 'role' => 'editor' ) );
wp_set_current_user( $ed0 ); set_current_screen( 'dashboard' );
ob_start(); do_action( 'admin_notices' ); $nt5 = ob_get_clean();
t( false === strpos( $nt5, 'admin.php?page=mcupr-books' ), 'an editor is not asked (only an admin can choose)' );
wp_set_current_user( 1 ); wp_delete_user( $ed0 ); set_current_screen( 'front' );
update_option( 'mcupr_books_opts', array( 'style' => 'nope', 'view' => 'x' ) );
t( mcupr_books_opts()['style'] === 'wood' && mcupr_books_opts()['view'] === 'shelf', 'unknown style / view fall back to the defaults' );
delete_option( 'mcupr_books_opts' );

$pdf  = side( $fx . 'book.pdf' );
$epub = side( $fx . 'book.epub' );
$vid  = side( $fx . 'book-video.mp4' );
$snd  = side( $fx . 'book-sound.wav' );
$imgs = array();
for ( $i = 1; $i <= 6; $i++ ) { $imgs[] = side( $fx . "book-page-$i.png" ); }
t( $pdf && 6 === count( array_filter( $imgs ) ), 'fixtures uploaded' );
t( 'application/epub+zip' === get_post_mime_type( $epub ) && 0 === strpos( (string) get_post_mime_type( $vid ), 'video/' ) && 0 === strpos( (string) get_post_mime_type( $snd ), 'audio/' ), 'an .epub, a video and a sound file can be uploaded and are recognised' );

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
t( false !== strpos( $h, 'class="bk-reader"' ) && false !== strpos( $h, 'data-book' ) && false !== strpos( $h, 'pdf.min.js' ), 'PDF reader: markup with the PDF.js address' );
t( false !== strpos( $h, ' download ' ), 'PDF reader: download is offered by default' );
update_post_meta( $b1, '_mcupr_bk_dl', '0' );
t( false === strpos( mcupr_book_reader( $b1 ), ' download ' ), 'PDF reader: the book can hide the download button' );
delete_post_meta( $b1, '_mcupr_bk_dl' );
update_option( 'mcupr_books_opts', array( 'download' => 0 ) );
t( false === strpos( mcupr_book_reader( $b1 ), ' download ' ), 'PDF reader: the setting can hide the download button' );
delete_option( 'mcupr_books_opts' );
$h2 = mcupr_book_reader( $b2 );
t( false !== strpos( $h2, '&quot;pages&quot;' ) && false === strpos( $h2, ' download ' ), 'picture reader: pages listed, no download' );
t( '' === mcupr_book_reader( $b3 ), 'a book with no file shows no reader' );
t( wp_script_is( 'mcupr-book-engines', 'enqueued' ) && wp_script_is( 'mcupr-book-reader', 'enqueued' ) && ! wp_script_is( 'mcupr-pageflip', 'enqueued' ), 'the engines and the reader are enqueued; the page-flip library is not (it is fetched only when chosen)' );
t( false === strpos( $h, 'data-act="fx"' ) && false !== strpos( $h, '&quot;fx&quot;:&quot;webgl&quot;' ) && false !== strpos( $h, 'page-flip.browser.js' ), 'the reader has no effect button; it gets the way chosen for the site (WebGL until chosen) and the address of the library' );
update_option( 'mcupr_books_opts', array( 'fx' => 'webgl' ) );
t( false !== strpos( mcupr_book_reader( $b1 ), '&quot;fx&quot;:&quot;webgl&quot;' ), 'the reader gets the way chosen in the settings' );
// each book can have its own
update_option( 'mcupr_books_opts', array( 'fx' => 'css3d' ) );
update_post_meta( $b1, '_mcupr_bk_fx', 'classic' );
t( 'classic' === mcupr_book_fx( $b1 ) && 'css3d' === mcupr_book_fx( $b2 ) && false !== strpos( mcupr_book_reader( $b1 ), '&quot;fx&quot;:&quot;classic&quot;' ), 'a book can have its own way of turning; the others keep the setting of the site' );
update_post_meta( $b1, '_mcupr_bk_fx', 'flash' );
t( 'css3d' === mcupr_book_fx( $b1 ), 'an unknown way set on a book is ignored' );
$bx = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'Fx Book' ) );
$_POST = array( 'mcupr_book_nonce' => wp_create_nonce( 'mcupr_book_save' ), 'mcupr_bk_src' => 'images', 'mcupr_bk_fx' => 'webgl' );
do_action( 'save_post_mcupr_book', $bx, get_post( $bx ), true );
t( 'webgl' === get_post_meta( $bx, '_mcupr_bk_fx', true ), 'the edit screen saves the way chosen for the book' );
$_POST = array( 'mcupr_book_nonce' => wp_create_nonce( 'mcupr_book_save' ), 'mcupr_bk_src' => 'images', 'mcupr_bk_fx' => '' );
do_action( 'save_post_mcupr_book', $bx, get_post( $bx ), true );
t( '' === (string) get_post_meta( $bx, '_mcupr_bk_fx', true ), '“Same as the settings” removes it' );
$_POST = array();
ob_start(); mcupr_book_box( get_post( $bx ) ); $bb = ob_get_clean();
t( false !== strpos( $bb, 'name="mcupr_bk_fx"' ) && false !== strpos( $bb, esc_html__( 'Same as the settings', 'mcupr' ) ), 'the edit screen of a book has the choice' );
wp_delete_post( $bx, true );
delete_option( 'mcupr_books_opts' );
foreach ( array( 'assets/vendor/pageflip/page-flip.browser.js', 'assets/vendor/pdfjs/pdf.min.js', 'assets/vendor/pdfjs/pdf.worker.min.js', 'assets/js/book-reader.js', 'assets/js/book-shelf.js' ) as $f ) {
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


// ---- round 2: YouTube links, extras of the pages, EPUB
foreach ( array( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ?t=5' => 'dQw4w9WgXcQ', 'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'dQw4w9WgXcQ', 'https://youtube.com/shorts/dQw4w9WgXcQ' => 'dQw4w9WgXcQ', 'dQw4w9WgXcQ' => 'dQw4w9WgXcQ', 'https://m.youtube.com/watch?v=dQw4w9WgXcQ&list=x' => 'dQw4w9WgXcQ', 'https://evil.example/watch?v=dQw4w9WgXcQ' => '', 'https://www.youtube.com/watch?v=short' => '', 'javascript:alert(1)' => '', '' => '' ) as $in => $want ) {
	t( mcupr_book_yt_id( $in ) === $want, 'YouTube id: "' . $in . '" → "' . $want . '"' );
}
update_post_meta( $b1, '_mcupr_bk_media', array(
	array( 'page' => 1, 'kind' => 'youtube', 'mode' => 'button', 'title' => 'Intro', 'yt' => 'dQw4w9WgXcQ' ),
	array( 'page' => 2, 'kind' => 'video', 'mode' => 'page', 'title' => 'Clip', 'att' => $vid ),
	array( 'page' => 2, 'kind' => 'audio', 'mode' => 'button', 'title' => 'Sound', 'att' => $snd ),
	array( 'page' => 3, 'kind' => 'link', 'mode' => 'button', 'title' => 'Site', 'url' => 'https://example.org/x' ),
	array( 'page' => 3, 'kind' => 'link', 'mode' => 'button', 'title' => 'Bad', 'url' => 'javascript:alert(1)' ),
	array( 'page' => 3, 'kind' => 'youtube', 'mode' => 'button', 'yt' => 'nope' ),
	array( 'page' => 3, 'kind' => 'video', 'mode' => 'button', 'att' => $pdf ),
	array( 'page' => 3, 'kind' => 'audio', 'mode' => 'button', 'att' => $vid ),
	array( 'page' => 3, 'kind' => 'flash', 'mode' => 'button', 'url' => 'https://example.org/' ),
) );
$m = mcupr_book_media( $b1 );
t( 4 === count( $m ), 'extras: the four real ones are kept, the five bad ones (script link, bad YouTube id, a PDF as video, a video as sound, an unknown type) are dropped' );
t( 'page' === $m[1]['mode'] && 'button' === $m[0]['mode'], 'extras: the way of showing (button / page of its own) is kept' );
$cfgm = mcupr_book_media_cfg( $b1 );
t( 'dQw4w9WgXcQ' === $cfgm[0]['yt'] && ! empty( $cfgm[1]['src'] ) && ! empty( $cfgm[3]['href'] ), 'extras: the reader gets a YouTube id, file addresses and links' );
t( false !== strpos( mcupr_book_reader( $b1 ), '&quot;media&quot;' ) && false !== strpos( mcupr_book_reader( $b1 ), 'data-mbar' ), 'PDF reader: carries the extras and the list of them' );
t( false === strpos( mcupr_book_reader( $b2 ), '&quot;media&quot;' ), 'a book without extras carries none' );
// saving from the editor box
$_POST = array(
	'mcupr_book_nonce' => wp_create_nonce( 'mcupr_book_save' ),
	'mcupr_bk_src'     => 'pdf',
	'mcupr_bk_pdf'     => $pdf,
	'mcupr_bk_media'   => array(
		array( 'page' => '2', 'kind' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => '<b>Hi</b>', 'mode' => 'page', 'att' => '0' ),
		array( 'page' => '0', 'kind' => 'link', 'url' => 'https://example.org/', 'title' => 'L', 'mode' => 'weird', 'att' => '0' ),
		array( 'page' => '1', 'kind' => 'link', 'url' => 'ftp://example.org/', 'title' => 'bad' ),
		array( 'page' => '1', 'kind' => 'video', 'att' => (string) $vid, 'title' => 'V' ),
	),
);
do_action( 'save_post_mcupr_book', $b1 );
$_POST = array();
$m = mcupr_book_media( $b1 );
t( 3 === count( $m ) && 'dQw4w9WgXcQ' === $m[0]['yt'] && 'Hi' === $m[0]['title'] && 'page' === $m[0]['mode'], 'saving the box: a YouTube link becomes its id, tags are stripped from the name, bad rows are dropped' );
t( 1 === $m[1]['page'] && 'button' === $m[1]['mode'], 'saving the box: page 0 becomes 1 and an unknown mode becomes "button"' );
// EPUB
$b4 = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'EPUB Book' ) );
update_post_meta( $b4, '_mcupr_bk_src', 'epub' );
t( '' === mcupr_book_reader( $b4 ), 'EPUB book with no file shows no reader' );
update_post_meta( $b4, '_mcupr_bk_epub', $epub );
t( 'epub' === mcupr_book_src( $b4 ) && $epub === mcupr_book_epub_id( $b4 ) && 0 === mcupr_book_epub_id( $b1 ), 'EPUB: source and file id' );
$he = mcupr_book_reader( $b4 );
t( false !== strpos( $he, 'bk-is-epub' ) && false !== strpos( $he, 'bk-ep-paper' ) && false !== strpos( $he, 'epub.min.js' ) && false !== strpos( $he, ' download ' ), 'EPUB reader: the page, the libraries and the download button' );
t( false === strpos( $he, '&quot;media&quot;' ) && wp_script_is( 'mcupr-book-epub', 'enqueued' ), 'EPUB reader: no page extras; its script is enqueued' );
foreach ( array( 'assets/vendor/epub/epub.min.js', 'assets/vendor/epub/jszip.min.js', 'assets/js/book-epub.js' ) as $f ) {
	t( is_readable( get_theme_file_path( $f ) ), 'file shipped: ' . $f );
}
t( false !== strpos( mcupr_book_shelf(), 'EPUB Book' ), 'the shelf lists an EPUB book' );


// ---- round 4: the look of Real3D Flipbook: toolbar, lightbox, background
update_option( 'mcupr_books_opts', array( 'mode' => 'lightbox', 'bg' => '#123456' ) );
t( 'lightbox' === mcupr_books_opts()['mode'] && '#123456' === mcupr_books_opts()['bg'], 'settings: lightbox mode and a background colour' );
update_option( 'mcupr_books_opts', array( 'mode' => 'popup', 'bg' => 'red' ) );
t( 'embed' === mcupr_books_opts()['mode'] && '#1f2430' === mcupr_books_opts()['bg'], 'settings: an unknown mode and a bad colour fall back' );
delete_option( 'mcupr_books_opts' );
$he2 = mcupr_book_reader( $b2, array( 'mode' => 'embed' ) );
t( false !== strpos( $he2, 'class="bk-toolbar"' ) && false !== strpos( $he2, 'role="toolbar"' ) && false !== strpos( $he2, 'data-act="zoomin"' ) && false !== strpos( $he2, 'data-act="auto"' ) && false !== strpos( $he2, 'data-act="first"' ) && false !== strpos( $he2, 'data-act="last"' ), 'the reader has a toolbar with first / last / zoom / auto-play' );
update_post_meta( $b1, '_mcupr_bk_dl', 1 );
t( 1 === preg_match( '/data-act="print"/', mcupr_book_reader( $b1 ) ), 'the reader has a print button when the book may be taken away' );
update_post_meta( $b1, '_mcupr_bk_dl', '0' );
t( false === strpos( mcupr_book_reader( $b1 ), 'data-act="print"' ), 'no print button when the book may not be taken away (like the download)' );
delete_post_meta( $b1, '_mcupr_bk_dl' );
t( false !== strpos( $he2, '--bk-bg: #1f2430' ), 'the reader carries the background colour' );
preg_match_all( '/<button[^>]*class="bk-btn bk-tb"[^>]*>(.*?)<\/button>/s', $he2, $btns );
$named = 0;
foreach ( $btns[1] as $inner ) { if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) { $named++; } }
t( count( $btns[1] ) >= 10 && count( $btns[1] ) === $named, 'every toolbar button has a name for screen readers (' . $named . ' of ' . count( $btns[1] ) . ')' );
t( false === strpos( $he2, 'bk-launch' ) && false === strpos( $he2, '<template' ), 'embedded: no cover launcher, no template' );
$hl = mcupr_book_reader( $b2, array( 'mode' => 'lightbox' ) );
t( false !== strpos( $hl, 'class="bk-launch"' ) && false !== strpos( $hl, 'data-bk-launch="bk-tpl-' ) && false !== strpos( $hl, '<template id="bk-tpl-' ) && false !== strpos( $hl, 'is-lb' ) && false !== strpos( $hl, 'data-act="close"' ), 'lightbox: a cover button, the reader inside a template, a close button' );
t( false !== strpos( do_shortcode( '[mcupr_book id="' . $b2 . '" mode="lightbox"]' ), 'bk-launch' ) && false === strpos( do_shortcode( '[mcupr_book id="' . $b2 . '" mode="embed"]' ), 'bk-launch' ), 'shortcode: mode="lightbox" / mode="embed"' );
update_option( 'mcupr_books_opts', array( 'mode' => 'lightbox' ) );
t( false !== strpos( do_shortcode( '[mcupr_book id="' . $b2 . '"]' ), 'bk-launch' ), 'the setting makes books in pages open as a lightbox' );
update_post_meta( $b2, '_mcupr_bk_mode', 'embed' );
t( false === strpos( do_shortcode( '[mcupr_book id="' . $b2 . '"]' ), 'bk-launch' ), 'a book can choose its own mode over the setting' );
delete_post_meta( $b2, '_mcupr_bk_mode' );
delete_option( 'mcupr_books_opts' );
$hep = mcupr_book_reader( $b4, array( 'mode' => 'embed' ) );
t( false === strpos( $hep, 'data-act="first"' ) && false === strpos( $hep, 'data-act="zoomin"' ) && false === strpos( $hep, 'data-act="auto"' ) && false !== strpos( $hep, 'data-act="sound"' ), 'EPUB toolbar has no first / last / zoom / auto-play (the text flows) but has sound' );
foreach ( array( 'zoom-in', 'zoom-out', 'first', 'last', 'print', 'play', 'pause', 'sound', 'mute', 'list', 'grid', 'download', 'share', 'expand', 'shrink', 'close' ) as $ic ) {
	t( 1 === preg_match( '/<path d="[^"]{6,}"/', mcupr_book_icon( $ic ) ), 'toolbar icon: ' . $ic );
}

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
	$vr = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $vid, home_url( '/' ) ) );
	t( ! is_wp_error( $vr ) && 403 === wp_remote_retrieve_response_code( $vr ), 'guarded stream: the video of a student-only book is refused to a visitor' );
	delete_post_meta( $b1, MCUPR_AUD );
	mcupr_aud_reset();
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ) );
	t( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && 0 === strpos( wp_remote_retrieve_body( $r ), '%PDF' ), 'stream: an open book is sent (200, %PDF)' );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ), array( 'Range' => 'bytes=0-9' ) );
	t( ! is_wp_error( $r ) && 206 === wp_remote_retrieve_response_code( $r ) && 10 === strlen( wp_remote_retrieve_body( $r ) ) && 0 === strpos( (string) wp_remote_retrieve_header( $r, 'content-range' ), 'bytes 0-9/' ), 'stream: Range gives 206 with Content-Range' );
	update_post_meta( $b1, '_mcupr_bk_media', array( array( 'page' => 2, 'kind' => 'video', 'mode' => 'page', 'title' => 'Clip', 'att' => $vid ) ) );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $vid, home_url( '/' ) ), array( 'Range' => 'bytes=0-3' ) );
	t( ! is_wp_error( $r ) && 206 === wp_remote_retrieve_response_code( $r ) && 0 === strpos( (string) wp_remote_retrieve_header( $r, 'content-type' ), 'video/' ), 'stream: the video of a book is sent in pieces (206, video/…)' );
	$r = $get( add_query_arg( 'mcupr_bf', $b4 . '-' . $epub, home_url( '/' ) ) );
	t( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && 'PK' === substr( wp_remote_retrieve_body( $r ), 0, 2 ), 'stream: the EPUB of a book is sent (a ZIP)' );
	$r = $get( add_query_arg( 'mcupr_bf', $b2 . '-' . $vid, home_url( '/' ) ) );
	t( ! is_wp_error( $r ) && in_array( wp_remote_retrieve_response_code( $r ), array( 403, 404 ), true ), 'stream: a video that is not an extra of that book is refused' );
	$r = $get( add_query_arg( 'mcupr_bf', $b1 . '-' . $pdf, home_url( '/' ) ), array( 'Range' => 'bytes=99999999-' ) );
	t( ! is_wp_error( $r ) && 416 === wp_remote_retrieve_response_code( $r ), 'stream: a Range past the end gives 416' );
}

// ---- strings in every language (th, en, zh, ja)
$src = file_get_contents( get_theme_file_path( 'inc/strings.php' ) );
preg_match_all( "/'(bk_[a-z0-9_]+)' => array\\( ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\") \\)/", $src, $mm );
t( count( $mm[1] ) >= 30, 'e-book words have all four languages (' . count( $mm[1] ) . ' keys)' );
// the pages of a PDF the editor took out: saved only for that PDF, kept in order, checked against the page count, given to the reader
$save = function ( $post, $post_fields ) { $_POST = array_merge( array( 'mcupr_book_nonce' => wp_create_nonce( 'mcupr_book_save' ), 'mcupr_bk_src' => 'pdf' ), $post_fields ); do_action( 'save_post_mcupr_book', $post, get_post( $post ), true ); $_POST = array(); };
$kb = wp_insert_post( array( 'post_type' => 'mcupr_book', 'post_status' => 'publish', 'post_title' => 'Keep Book' ) );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf, 'mcupr_bk_keep_on' => '1', 'mcupr_bk_keep_for' => $pdf, 'mcupr_bk_pdfn' => 6, 'mcupr_bk_keep' => array( '3', '1', '4', '99', '4', 'x', '6' ) ) );
t( array( 3, 1, 4, 6 ) === mcupr_book_pdf_keep( $kb ) && 6 === (int) get_post_meta( $kb, '_mcupr_bk_pdfn', true ), 'pages: the pages kept are saved in the editor\'s order; a page beyond the PDF, a repeat and a non-number are dropped' );
t( array( 3, 1, 4, 6 ) === json_decode( (string) json_encode( mcupr_book_pdf_keep( $kb ) ) ) && false !== strpos( mcupr_book_reader( $kb ), '&quot;keep&quot;:[3,1,4,6]' ), 'pages: the reader is given the pages to show' );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf ) );
t( array( 3, 1, 4, 6 ) === mcupr_book_pdf_keep( $kb ), 'pages: saving without opening the page manager leaves the choice as it was' );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf, 'mcupr_bk_keep_on' => '1', 'mcupr_bk_keep_for' => $pdf, 'mcupr_bk_pdfn' => 6, 'mcupr_bk_keep' => array( '1', '2', '3', '4', '5', '6' ) ) );
t( array() === mcupr_book_pdf_keep( $kb ) && '' === (string) get_post_meta( $kb, '_mcupr_bk_keep', true ), 'pages: every page in the PDF order is the same as no choice (nothing stored)' );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf, 'mcupr_bk_keep_on' => '1', 'mcupr_bk_keep_for' => $pdf, 'mcupr_bk_pdfn' => 6, 'mcupr_bk_keep' => array( '2', '5' ) ) );
$pdf2 = side( $fx . 'book-landscape.pdf' );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf2 ) );
t( $pdf2 && array() === mcupr_book_pdf_keep( $kb ), 'pages: a different PDF starts again with all its pages' );
$save( $kb, array( 'mcupr_bk_pdf' => $pdf, 'mcupr_bk_keep_on' => '1', 'mcupr_bk_keep_for' => $pdf + 1, 'mcupr_bk_pdfn' => 6, 'mcupr_bk_keep' => array( '2' ) ) );
t( array() === mcupr_book_pdf_keep( $kb ), 'pages: a choice made for another file is not taken' );
ob_start(); mcupr_book_box( get_post( $kb ) ); $bx = ob_get_clean();
t( false !== strpos( $bx, 'id="mcupr-bk-load-pages"' ) && false !== strpos( $bx, 'id="mcupr-bk-pdfpages"' ) && false !== strpos( $bx, 'name="mcupr_bk_keep_on"' ), 'pages: the e-book box has the page manager' );
wp_delete_post( $kb, true );
t( ! file_exists( get_theme_file_path( 'assets/vendor/pdfjs/pdf.min.mjs' ) ) && file_exists( get_theme_file_path( 'assets/vendor/pdfjs/pdf.min.js' ) ) && file_exists( get_theme_file_path( 'assets/vendor/pdfjs/pdf.worker.min.js' ) ), 'PDF.js is served as .js (a web server that sends .mjs with the wrong type would stop the module from loading)' );
foreach ( array( $b1, $b2, $b3, $b4 ) as $x ) { wp_delete_post( $x, true ); }
wp_delete_term( (int) $shelf['term_id'], 'mcupr_shelf' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
