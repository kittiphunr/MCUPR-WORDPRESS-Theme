<?php
/** The "More" panel css: card grid, no scroll bars, groups always open.   php php/more-panel.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/\.menu-more\.is-open>\.sub-menu\{(?=[^}]*display:grid)[^}]*auto-fit/', $css ), 'css: the open panel is a responsive grid' );
t( preg_match( '/\.menu-more>\.sub-menu\{[^}]*scrollbar-width:none/', $css ) && false !== strpos( $css, '.menu-more>.sub-menu::-webkit-scrollbar{display:none}' ), 'css: scroll bars are hidden in every browser (fallback only; the grid normally fits)' );
t( preg_match( '/\.menu-more \.sub-menu \.submenu-toggle\{display:none\}/', $css ), 'css: no accordion arrows inside the panel (the More button itself keeps its arrow)' );
t( ! preg_match( '/\.menu-more \.submenu-toggle\{display:none\}/', $css ), 'css: the rule never hides the More button' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
