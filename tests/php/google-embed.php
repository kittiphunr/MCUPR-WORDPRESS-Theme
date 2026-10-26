<?php
/** Google Drive / Docs / Photos in a post: which links are understood, what is drawn, what is kept out, what is saved.   php php/google-embed.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );
$ID = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';

// 1. what is understood
$cases = array(
	"https://drive.google.com/file/d/$ID/view?usp=sharing"               => array( 'file', "https://drive.google.com/file/d/$ID/preview" ),
	"https://drive.google.com/file/u/0/d/$ID/edit"                       => array( 'file', "https://drive.google.com/file/d/$ID/preview" ),
	"https://drive.google.com/open?id=$ID"                               => array( 'file', "https://drive.google.com/file/d/$ID/preview" ),
	"http://drive.google.com/uc?id=$ID&export=download"                  => array( 'file', "https://drive.google.com/file/d/$ID/preview" ),
	"https://drive.google.com/drive/folders/$ID?usp=sharing"             => array( 'folder', "https://drive.google.com/embeddedfolderview?id=$ID#list" ),
	"https://drive.google.com/drive/u/1/folders/$ID"                     => array( 'folder', "https://drive.google.com/embeddedfolderview?id=$ID#list" ),
	"https://docs.google.com/document/d/$ID/edit"                        => array( 'doc', "https://docs.google.com/document/d/$ID/preview" ),
	"https://docs.google.com/spreadsheets/d/$ID/edit#gid=0"              => array( 'sheet', "https://docs.google.com/spreadsheets/d/$ID/preview" ),
	"https://docs.google.com/presentation/d/$ID/edit?usp=sharing"        => array( 'slides', "https://docs.google.com/presentation/d/$ID/embed?start=false&loop=false" ),
	"https://docs.google.com/forms/d/e/$ID/viewform?usp=sf_link"         => array( 'form', "https://docs.google.com/forms/d/e/$ID/viewform?embedded=true" ),
	'https://photos.app.goo.gl/AbCdEfGh12345678'                         => array( 'photos', '' ),
	"https://photos.google.com/share/$ID?key=abcDEF123_-xyz"             => array( 'photos', '' ),
);
$ok = true; $bad = '';
foreach ( $cases as $url => $want ) {
	$g = mcupr_google_parse( $url );
	if ( ! $g || $g['kind'] !== $want[0] || $g['embed'] !== $want[1] ) { $ok = false; $bad .= " $url"; }
}
t( $ok, 'parse: files, folders, documents, sheets, slides, forms and photo albums are all understood' . ( $bad ? ' (not: ' . $bad . ')' : '' ) );
$g = mcupr_google_parse( "https://drive.google.com/file/d/$ID/view" );
t( 0 === strpos( $g['open'], 'https://drive.google.com/' ) && false !== strpos( $g['open'], $ID ), 'parse: the link to open it at Google is built from the id' );
$g = mcupr_google_parse( "https://photos.google.com/share/$ID?key=abcDEF123_-xyz&x=<script>" );
t( $g && 'https://photos.google.com/share/' . $ID . '?key=abcDEF123_-xyz' === $g['open'], 'parse: of a photo album link only the id and the key are kept' );

// 2. what is kept out
$evil = array(
	'javascript:alert(1)', "https://evil.example/drive.google.com/file/d/$ID/view", "https://drive.google.com.evil.example/file/d/$ID/view", "https://evil.example/?u=https://drive.google.com/file/d/$ID/view",
	"https://drive.google.com/file/d/short/view", 'https://drive.google.com/', 'https://drive.google.com/drive/my-drive', 'https://docs.google.com/unknown/d/' . $ID,
	"ftp://drive.google.com/file/d/$ID/view", '', '   ', str_repeat( 'a', 700 ), "https://drive.google.com/open?id=" . str_repeat( 'x', 300 ),
	"https://www.google.com/url?q=https://drive.google.com/file/d/$ID/view", 'https://photos.google.com/', 'https://photos.app.goo.gl/',
);
$passed = 0; $leak = '';
foreach ( $evil as $u ) { if ( false === mcupr_google_parse( $u ) ) { $passed++; } else { $leak .= ' ' . substr( $u, 0, 50 ); } }
t( count( $evil ) === $passed, 'safety: other hosts, look-alike hosts, javascript:, odd ids, empty and overlong addresses are all refused' . ( $leak ? ' (accepted:' . $leak . ')' : '' ) );

$g = mcupr_google_parse( "https://drive.google.com/file/d/$ID\"onload=\"x/view" );
t( $g && $ID === $g['id'] && false === strpos( $g['embed'] . $g['open'], '"' ) && false === strpos( $g['embed'] . $g['open'], 'onload' ), 'safety: an id followed by quote marks is cut at the id; the address built from it holds nothing that was typed after' );

// 3. what is drawn
$g = mcupr_google_parse( "https://drive.google.com/file/d/$ID/view" );
$h = mcupr_google_card( $g, 'รายงาน <b>x</b> "q"' );
t( false === strpos( $h, '<iframe' ) && false !== strpos( $h, 'data-gemb' ) && false !== strpos( $h, 'class="gemb-load"' ), 'card: the page has no frame; a button loads it' );
t( false !== strpos( $h, 'data-embed="https://drive.google.com/file/d/' ) && false !== strpos( $h, 'target="_blank" rel="noopener"' ), 'card: the address to load is Google\'s preview, and the link to open it is there' );
t( false === strpos( $h, '<b>x</b>' ) && false !== strpos( $h, '&lt;b&gt;x&lt;/b&gt;' ), 'card: a title with HTML is shown as text' );
t( false !== strpos( $h, mcupr_t( 'gemb_note' ) ) && false !== strpos( $h, mcupr_t( 'gemb_load' ) ), 'card: the note about Google and the button are in the visitor\'s language' );
$hp = mcupr_google_card( mcupr_google_parse( 'https://photos.app.goo.gl/AbCdEfGh12345678' ), 'อัลบั้ม' );
t( false === strpos( $hp, 'gemb-load' ) && false === strpos( $hp, 'data-gemb' ) && false !== strpos( $hp, 'href="https://photos.app.goo.gl/AbCdEfGh12345678"' ) && false !== strpos( $hp, 'noopener' ), 'photos: an album is a card with a button that opens it (it cannot be shown inside the page), no frame, no loader' );

// 4. lines and the box
$it = mcupr_google_line( "รายงาน | https://drive.google.com/file/d/$ID/view" );
t( $it && 'รายงาน' === $it['title'] && 'file' === $it['g']['kind'], 'line: "name | link" gives the card a title' );
$it = mcupr_google_line( "https://drive.google.com/file/d/$ID/view | name" );
t( $it && '' === $it['title'], 'line: "link | name" is read as a link without a title' );
t( null === mcupr_google_line( 'ไม่ใช่ลิงก์ | https://evil.example/x' ) && null === mcupr_google_line( '' ), 'line: a bad link or an empty line gives nothing' );
$pid = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ge test', 'post_content' => 'x' ) );
$_POST = array(
	'mcupr_google_nonce' => wp_create_nonce( 'mcupr_google_save' ),
	'mcupr_gdrive'  => "ไฟล์หนึ่ง | https://drive.google.com/file/d/$ID/view\nhttps://evil.example/x\nhttps://photos.app.goo.gl/AbCdEfGh12345678\n\nhttps://drive.google.com/drive/folders/$ID",
	'mcupr_gphotos' => "https://photos.app.goo.gl/AbCdEfGh12345678\nhttps://drive.google.com/file/d/$ID/view",
);
do_action( 'save_post', $pid, get_post( $pid ), true );
$d = array_map( static function ( $i ) { return $i['g']['kind']; }, mcupr_google_items( $pid, '_mcupr_gdrive' ) );
$f = array_map( static function ( $i ) { return $i['g']['kind']; }, mcupr_google_items( $pid, '_mcupr_gphotos' ) );
t( array( 'file', 'folder' ) === $d && array( 'photos' ) === $f, 'save: only Drive/Docs links are kept in the Drive box and only albums in the Photos box (a bad link and a misplaced one are dropped)' );
t( false === strpos( (string) get_post_meta( $pid, '_mcupr_gdrive', true ), 'evil' ), 'save: what was refused is not stored' );
$_POST['mcupr_google_nonce'] = 'bad'; $_POST['mcupr_gdrive'] = '';
do_action( 'save_post', $pid, get_post( $pid ), true );
t( 2 === count( mcupr_google_items( $pid, '_mcupr_gdrive' ) ), 'save: a wrong nonce changes nothing' );
$_POST = array();
$GLOBALS['post'] = get_post( $pid ); setup_postdata( $GLOBALS['post'] );
ob_start(); mcupr_attachments( $pid ); $out = ob_get_clean();
t( 3 === substr_count( $out, 'class="gemb ' ) && 1 === substr_count( $out, 'gemb-photos' ) && false !== strpos( $out, 'ไฟล์หนึ่ง' ), 'post: the two Drive cards and the album card are drawn under the text' );
ob_start(); mcupr_google_box( get_post( $pid ) ); $box = ob_get_clean();
t( 2 === substr_count( $box, '<textarea' ) && false !== strpos( $box, 'mcupr_google_nonce' ) && false !== strpos( $box, '&#10003;' ), 'editor: the box has its two fields, its nonce and a tick for each link it understands' );
update_post_meta( $pid, '_mcupr_gdrive', "https://evil.example/x\nhttps://drive.google.com/file/d/$ID/view" );
ob_start(); mcupr_google_box( get_post( $pid ) ); $box = ob_get_clean();
t( false !== strpos( $box, '&#10007;' ), 'editor: a link that is not understood is marked' );
wp_update_post( array( 'ID' => $pid, 'post_password' => 'secret' ) ); wp_set_current_user( 0 ); $GLOBALS['post'] = get_post( $pid );
ob_start(); mcupr_attachments( $pid ); t( '' === ob_get_clean(), 'password: a protected post shows no Google card' );
wp_set_current_user( 1 ); wp_update_post( array( 'ID' => $pid, 'post_password' => '' ) );

// 5. in the text
global $wp_embed;
$c = apply_filters( 'the_content', "text\n\nhttps://docs.google.com/presentation/d/$ID/edit?usp=sharing\n\nmore" );
t( false !== strpos( $c, 'gemb-slides' ) && false === strpos( $c, '<iframe' ), 'text: a Docs link alone on a line becomes the card' );
t( 1 === preg_match( '#<div class="gemb-card">.*?<button type="button" class="gemb-load">.*?</div>\s*</section>#s', $c ) && false === strpos( $c, '<p><button' ) && false === strpos( $c, '</p>' . "\n" . '<div class="gemb-main"' ), 'text: and its markup comes through the paragraph filter whole (no stray paragraphs inside the card)' );
$c = $wp_embed->autoembed( "text\n\nhttps://docs.google.com/presentation/d/$ID/edit?usp=sharing\n\nmore" );
t( false !== strpos( $c, '[mcupr_drive url=' ) && false === strpos( $c, '<iframe' ), 'text: the link is first turned into the shortcode' );
$c = $wp_embed->autoembed( "see https://drive.google.com/file/d/$ID/view in the middle of a line" );
t( false === strpos( $c, 'gemb' ), 'text: a link inside a sentence is left as a link' );
$c = $wp_embed->autoembed( "https://evil.example/drive.google.com/file/d/$ID/view" );
t( false === strpos( $c, 'gemb' ), 'text: a link of another site is never turned into a card' );
t( false !== strpos( do_shortcode( "[mcupr_drive url=\"https://drive.google.com/file/d/$ID/view\" title=\"A\"]" ), 'data-gemb' ) && '' === do_shortcode( '[mcupr_drive url="https://evil.example/x"]' ), 'shortcode: [mcupr_drive] draws a card and refuses a bad link' );
t( false !== strpos( do_shortcode( '[mcupr_gphotos url="https://photos.app.goo.gl/AbCdEfGh12345678"]' ), 'gemb-photos' ) && '' === do_shortcode( "[mcupr_gphotos url=\"https://drive.google.com/file/d/$ID/view\"]" ), 'shortcode: [mcupr_gphotos] draws an album card and refuses a Drive link' );

// 6. words and script
$all = include MCUPR_DIR . '/inc/strings.php'; $miss = array();
foreach ( array( 'gemb_note', 'gemb_load', 'gemb_open_drive', 'gemb_open_docs', 'gemb_kind_file', 'gemb_kind_folder', 'gemb_kind_doc', 'gemb_kind_sheet', 'gemb_kind_slides', 'gemb_kind_form', 'gphotos_title', 'gphotos_note', 'gphotos_open' ) as $k ) {
	if ( empty( $all[ $k ] ) || 4 !== count( $all[ $k ] ) || in_array( '', $all[ $k ], true ) ) { $miss[] = $k; }
}
t( ! $miss, 'words: every text exists in Thai, English, Chinese and Japanese' . ( $miss ? ' (' . implode( ',', $miss ) . ')' : '' ) );
$js = (string) file_get_contents( get_theme_file_path( 'assets/js/main.js' ) );
t( false !== strpos( $js, "gemb[data-gemb] .gemb-load" ) && false !== strpos( $js, '(drive|docs)\\.google\\.com' ) && false !== strpos( $js, "sandbox" ), 'script: the frame is built only for a Drive or Docs address, in a sandbox, after the button is pressed' );

wp_delete_post( $pid, true );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
