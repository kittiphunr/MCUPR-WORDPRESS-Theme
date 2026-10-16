<?php
/** The office menu folds into one button on small screens (JS) and stays as tabs otherwise.   php php/office-tabs.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$php = file_get_contents( get_theme_file_path( 'inc/offices-front.php' ) );
t( false !== strpos( $php, 'class="of-tabs-btn" aria-expanded="false" aria-controls="of-tabs-list"' ) && false !== strpos( $php, '<ul id="of-tabs-list">' ), 'markup: a real button that controls the list (aria-expanded, aria-controls)' );
t( false !== strpos( $php, "mcupr_t( 'of_nav' )" ) && '' !== mcupr_t( 'of_nav' ), 'markup: the button is named with the menu name (and the current tab)' );
$js = file_get_contents( get_theme_file_path( 'assets/js/main.js' ) );
t( false !== strpos( $js, "classList.add('js-tabs')" ) && false !== strpos( $js, "'Escape'" ) && false !== strpos( $js, 'aria-expanded' ), 'js: the folding is added by script (no script: the list stays open), Escape closes' );
$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( preg_match( '/\.of-tabs-btn\{display:none\}/', $css ), 'css: the button is hidden by default (wide screens, no script)' );
t( preg_match( '/@media \(max-width:\s*640px\)\{[^@]*\.of-tabs\.js-tabs \.of-tabs-btn\{display:flex/', $css ) || preg_match( '/@media \(max-width: ?640px\)\s*\{\s*\.of-tabs\.js-tabs \.of-tabs-btn/', $css ) || false !== strpos( $css, '.of-tabs.js-tabs .of-tabs-btn' ), 'css: the button shows on small screens only when the script ran' );
t( false !== strpos( $css, '.of-tabs.js-tabs:not(.is-open) ul' ), 'css: the list is folded until opened' );
t( preg_match( '/html\[data-contrast\] \.of-tabs\.js-tabs \.of-tabs-btn\{[^}]*border:2px solid var\(--border\)/', $css ), 'css: high contrast gives the button a 2px outline' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
