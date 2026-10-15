<?php
/** The "More" dropdown css: long text wraps, no scroll bar is drawn.   php php/more-panel.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/\.menu-more>\.sub-menu\{(?=[^}]*overflow-x:hidden)(?=[^}]*scrollbar-width:none)/', $css ) && false !== strpos( $css, '.menu-more>.sub-menu::-webkit-scrollbar{display:none}' ), 'css: no sideways scroll and no scroll bar drawn in any browser' );
t( preg_match( '/\.menu-more \.sub-menu li>a\{(?=[^}]*overflow-wrap:anywhere)(?=[^}]*white-space:normal)/', $css ), 'css: long text in the dropdown wraps onto a new line' );
t( false === strpos( $css, 'auto-fit,minmax(14rem' ) && ! preg_match( '/\.menu-more\.is-open>\.sub-menu\{[^}]*display:grid/', $css ), 'css: the dropdown is not turned into a grid / mega menu' );
t( ! preg_match( '/\.menu-more \.submenu-toggle\{display:none\}/', $css ), 'css: the More button keeps its arrow' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
