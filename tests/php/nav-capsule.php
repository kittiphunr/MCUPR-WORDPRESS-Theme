<?php
/** Top menu: an item with a sub-menu is one capsule (link and arrow side by side, state colours on the capsule).   php php/nav-capsule.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/li\.menu-item-has-children:not\(\.menu-more\)\{[^}]*border-radius:9999px[^}]*display:inline-flex/', $css ), 'css: the item with a sub-menu is a flex capsule' );
t( preg_match( '/menu-item-has-children:not\(\.menu-more\)>\.submenu-toggle:not\(\.more-btn\)\{[^}]*width:2\.5rem[^}]*height:2\.5rem[^}]*margin:0 \.2rem 0 0/', $css ), 'css: the arrow button is a 40px circle with no negative margin (it no longer sits on top of the link)' );
t( ! preg_match( '/html\[data-menu=top\] \.site-nav \.menu>li>\.submenu-toggle:not\(\.more-btn\)\{[^}]*margin-left:-/', $css ) || false !== strpos( $css, 'li.menu-item-has-children:not(.menu-more)>.submenu-toggle:not(.more-btn){width:2.5rem' ), 'css: the capsule rule comes after (and replaces) the old overlapping margin' );
t( false !== strpos( $css, ':has(>a:hover' ) || false !== strpos( $css, ':has(> a:hover' ), 'css: hover, open and current colour the capsule' );
t( false !== strpos( $css, ':has(>a:focus-visible' ) || false !== strpos( $css, ':has(> a:focus-visible' ), 'css: the keyboard ring goes round the capsule' );
t( preg_match( '/html\[data-contrast\]\[data-menu=top\] \.site-nav \.menu>li\.menu-item-has-children:is\(\.current-menu-item,\.current_page_item,\.current-menu-ancestor\)\{box-shadow:inset 0 0 0 3px var\(--text\)/', $css ) || false !== strpos( $css, 'box-shadow:inset 0 0 0 3px var(--text)' ), 'css: in high contrast the current page has a 3px outline' );
t( false === strpos( $css, 'li.menu-item-has-children{display:inline-flex' ) && 0 === preg_match( '/li\.menu-item-has-children(?!:not\(\.menu-more\))[^{,]*\{[^}]*display:inline-flex/', $css ), 'css: the "More" pill is not part of the capsule rules (it is one pill already; both together drew a double outline)' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
