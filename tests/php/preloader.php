<?php
/** The loading screen with the logo: when it is on, what it holds, when it stays away.   php php/preloader.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$head = static function () { ob_start(); mcupr_preload_head(); return ob_get_clean(); };
$body = static function () { ob_start(); mcupr_preload_markup(); return ob_get_clean(); };
$keep = array( get_theme_mod( 'mcupr_preload', null ), get_theme_mod( 'custom_logo', null ), get_theme_mod( 'mcupr_logo_dark', null ) );
foreach ( array( 'mcupr_preload_logo', 'mcupr_preload_logo_dark', 'mcupr_preload_text', 'mcupr_preload_text_show' ) as $k ) { remove_theme_mod( $k ); }
remove_theme_mod( 'mcupr_preload' ); remove_theme_mod( 'custom_logo' ); remove_theme_mod( 'mcupr_logo_dark' );

// 1. the choice
t( 'every' === mcupr_preload_mode() && mcupr_preload_enabled(), 'default: on every page' );
set_theme_mod( 'mcupr_preload', 'nonsense' );
t( 'every' === mcupr_preload_mode(), 'choice: an unknown value is "every"' );
foreach ( array( 'session', 'every' ) as $m ) {
	set_theme_mod( 'mcupr_preload', $m );
	t( false !== strpos( $head(), "mode='$m'" ), "head: the script knows the mode \"$m\"" );
}
set_theme_mod( 'mcupr_preload', 'off' );
t( '' === $head() && '' === $body() && ! mcupr_preload_enabled(), 'off: no script and no screen at all' );
set_theme_mod( 'mcupr_preload', 'every' );
add_filter( 'mcupr_preloader', '__return_false' );
t( '' === $head() && '' === $body(), 'filter: mcupr_preloader can switch it off from code' );
remove_all_filters( 'mcupr_preloader' );

// 2. the screen
$b = $body();
t( 1 === substr_count( $b, 'id="preloader"' ) && false !== strpos( $b, 'aria-hidden="true"' ) && 1 === substr_count( $b, '<img' ), 'screen: one screen, hidden from screen readers, with one logo' );
t( false !== strpos( $b, 'mcu-logo.webp' ), 'screen: without an uploaded logo it shows the emblem of the theme' );
t( false !== strpos( $b, 'pl-ring' ) && false !== strpos( $b, 'pl-arc' ) && false !== strpos( $b, 'pl-name' ) && false !== strpos( $b, 'pl-bar' ), 'screen: ring, name and bar are there' );
t( false !== strpos( $b, esc_html( mcupr_org_name() ) ), 'screen: the name of the site is under the logo' );
$u = wp_upload_bits( 'pl-test-logo.png', null, '' ); $im = imagecreatetruecolor( 200, 200 ); imagepng( $im, $u['file'] );
$a1 = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'pl logo', 'post_status' => 'inherit' ), $u['file'] );
wp_update_attachment_metadata( $a1, wp_generate_attachment_metadata( $a1, $u['file'] ) );
$u2 = wp_upload_bits( 'pl-test-logo-dark.png', null, '' ); imagepng( $im, $u2['file'] );
$a2 = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'pl logo dark', 'post_status' => 'inherit' ), $u2['file'] );
wp_update_attachment_metadata( $a2, wp_generate_attachment_metadata( $a2, $u2['file'] ) );
set_theme_mod( 'custom_logo', $a1 );
$b = $body();
t( false !== strpos( $b, 'pl-test-logo' ) && false !== strpos( $b, 'pl-logo logo-light' ) && false === strpos( $b, 'mcu-logo.webp' ) && 1 === substr_count( $b, '<img' ), 'logo: the uploaded logo is used instead of the emblem' );
set_theme_mod( 'mcupr_logo_dark', $a2 );
$b = $body();
t( 2 === substr_count( $b, '<img' ) && false !== strpos( $b, 'pl-logo logo-dark' ), 'logo: the dark-mode logo comes with it (the theme\'s own light/dark switch shows one)' );

// 2b. its own logo and text
$u3 = wp_upload_bits( 'pl-own-logo.png', null, '' ); imagepng( $im, $u3['file'] );
$a3 = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'pl own', 'post_status' => 'inherit' ), $u3['file'] );
wp_update_attachment_metadata( $a3, wp_generate_attachment_metadata( $a3, $u3['file'] ) );
set_theme_mod( 'mcupr_preload_logo', $a3 );
$b = $body();
t( false !== strpos( $b, 'pl-own-logo' ) && false === strpos( $b, 'pl-test-logo' ) && 1 === substr_count( $b, '<img' ), 'own logo: it wins over the logo of the site, and the dark one of the site is not mixed in' );
set_theme_mod( 'mcupr_preload_logo', 999999 );
t( false !== strpos( $body(), 'pl-test-logo' ), 'own logo: one that no longer exists falls back to the logo of the site' );
set_theme_mod( 'mcupr_preload_logo', $a3 ); set_theme_mod( 'mcupr_preload_logo_dark', $a1 );
$b = $body();
t( 2 === substr_count( $b, '<img' ) && false !== strpos( $b, 'pl-logo logo-dark' ), 'own logo: its dark-mode version is shown with it' );
remove_theme_mod( 'mcupr_preload_logo' ); remove_theme_mod( 'mcupr_preload_logo_dark' );
set_theme_mod( 'mcupr_preload_text', '  สวัสดี <b>MCU</b> แพร่  ' );
$b = $body();
t( false !== strpos( $b, '>สวัสดี MCU แพร่</span>' ) && false === strpos( $b, esc_html( mcupr_org_name() ) . '</span>' ), 'text: the text written for it replaces the name of the site (tags removed)' );
set_theme_mod( 'mcupr_preload_text_show', false );
$b = $body();
t( false === strpos( $b, 'pl-name' ) && false !== strpos( $b, 'pl-ring' ), 'text: switched off, only the logo and the bar remain' );
remove_theme_mod( 'mcupr_preload_text_show' ); remove_theme_mod( 'mcupr_preload_text' );
t( 'every' === mcupr_preload_mode(), 'default: still on every page after all these settings' );

// 3. the script
set_theme_mod( 'mcupr_preload', 'every' );
$h = $head();
t( false !== strpos( $h, 'navigator.webdriver' ) && false !== strpos( $h, "getAttribute('data-motion')==='1'" ) && false !== strpos( $h, 'prefers-reduced-motion' ), 'script: robots and automated browsers, the theme\'s own motion switch and the browser setting keep it away' );
t( false !== strpos( $h, 'min=seen?350:700' ) && false !== strpos( $h, 'max=3200' ), 'script: at least 0.7 s the first time and 0.35 s after, at most 3.2 s' );
t( false !== strpos( $h, "classList.add('pl-on')" ) && false !== strpos( $h, 'pl-out' ) && false !== strpos( $h, 'pl-done' ) && false !== strpos( $h, 'pageshow' ), 'script: it switches the screen on, fades it out, and clears it when a page comes back from the browser\'s memory' );
foreach ( array( 'mcupr_dl', 'download', 'data-no-preload', 'target', 'origin', 'ctrlKey' ) as $needle ) {
	t( false !== strpos( $h, $needle ), "script: links with \"$needle\" are not turned into a page move" );
}
set_theme_mod( 'mcupr_preload', 'session' );
t( false === strpos( $head(), "document.addEventListener('click'" ) || false !== strpos( $head(), "if(mode==='every')" ), 'script: the click handler (screen between pages) only exists for "every"' );
set_theme_mod( 'mcupr_preload', 'every' );

// 4. the stylesheet
$css = (string) file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
foreach ( array( '.pl-box', '.pl-ring', '.pl-arc', '.pl-logo', 'html.pl-on .pl', 'html.pl-out .pl', 'html.pl-leave .pl', 'pl-page-in', 'pl-breathe' ) as $needle ) {
	t( false !== strpos( str_replace( ' ', '', $css ), str_replace( ' ', '', $needle ) ), "css: $needle is in the stylesheet" );
}
t( preg_match( '/\.pl\{[^}]*display:none/', preg_replace( '/\s+/', '', $css ) ) && false !== strpos( preg_replace( '/\s+/', '', $css ), 'html[data-motion="1"].pl{display:none!important' ) || false !== strpos( preg_replace( '/\s+/', '', $css ), 'html[data-motion=1].pl{display:none!important' ), 'css: hidden unless switched on; never with "reduce motion"' );

// 5. the customiser
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$GLOBALS['wp_customize'] = new WP_Customize_Manager();
do_action( 'customize_register', $GLOBALS['wp_customize'] );
$c = $GLOBALS['wp_customize']->get_control( 'mcupr_preload' );
foreach ( array( 'mcupr_preload_logo', 'mcupr_preload_logo_dark', 'mcupr_preload_text_show', 'mcupr_preload_text' ) as $id ) { $cc = $GLOBALS['wp_customize']->get_control( $id ); t( $cc && 'mcupr_display' === $cc->section, "customizer: $id is in the Animation section" ); }
t( $c && 'mcupr_display' === $c->section && array( 'every', 'session', 'off' ) === array_keys( $c->choices ), 'customizer: the choice is in the Animation section: every page / first page of a visit / never' );

foreach ( array( 'mcupr_preload' => $keep[0], 'custom_logo' => $keep[1], 'mcupr_logo_dark' => $keep[2] ) as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); }
foreach ( array( 'mcupr_preload_logo', 'mcupr_preload_logo_dark', 'mcupr_preload_text', 'mcupr_preload_text_show' ) as $k ) { remove_theme_mod( $k ); }
wp_delete_attachment( $a3, true ); wp_delete_attachment( $a1, true ); wp_delete_attachment( $a2, true );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
