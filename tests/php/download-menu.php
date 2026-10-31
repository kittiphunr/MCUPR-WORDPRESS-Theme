<?php
/** The Download button: one button and a menu of three files (PDF, PNG, JPG), in the tool bar and the tool box of an article.   php php/download-menu.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$h = mcupr_download_menu( 'act', '#article-body', 'A <b>"title"</b>', 'https://example.org/?a=1&b=2' );
t( 1 === substr_count( $h, 'data-dl-btn' ) && false !== strpos( $h, 'aria-haspopup="menu"' ) && false !== strpos( $h, 'aria-expanded="false"' ), 'one button that opens a menu (aria-haspopup, aria-expanded)' );
t( 3 === substr_count( $h, 'role="menuitem"' ) && false !== strpos( $h, 'data-pdf-fmt="pdf"' ) && false !== strpos( $h, 'data-pdf-fmt="png"' ) && false !== strpos( $h, 'data-pdf-fmt="jpg"' ), 'the menu has PDF, PNG and JPG' );
t( preg_match( '/id="(dl-menu-\d+)" role="menu" hidden/', $h, $m ) && false !== strpos( $h, 'aria-controls="' . $m[1] . '"' ), 'the button controls the menu, which is hidden at first' );
t( 3 === substr_count( $h, 'data-pdf-target="#article-body"' ) && 3 === substr_count( $h, 'data-pdf-site="' . esc_attr( get_bloginfo( 'name' ) ) . '"' ), 'every choice carries the target and the site name' );
t( false === strpos( $h, '<b>"title"' ) && false !== strpos( $h, 'a=1&#038;b=2' ), 'the title and the address are escaped' );
t( false !== strpos( $h, 'class="tool-act"' ) && false === strpos( $h, 'dl-tile' ), 'the tool bar variant uses the tool bar button' );
$t = mcupr_download_menu( 'tile', '#article-body', 'x', 'https://example.org/' );
t( false !== strpos( $t, 'class="tool-tile"' ) && false !== strpos( $t, 'dl dl-tile' ), 'the tool box variant is a tile' );
t( mcupr_download_menu( 'act', '#a', 'x', '/' ) !== $h && preg_match( '/id="(dl-menu-\d+)"/', mcupr_download_menu( 'act', '#a', 'x', '/' ), $m2 ) && $m2[1] !== $m[1], 'two menus on a page get different ids' );
foreach ( array( 'download', 'dl_pdf', 'dl_png', 'dl_jpg', 'pdf_busy', 'pdf_fail', 'img_busy', 'img_fail' ) as $k ) {
	$all = true;
	foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) { $v = mcupr_t( $k, $l ); $all = $all && '' !== $v && $v !== $k; }
	t( $all, "the text “{$k}” exists in 4 languages" );
}
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
