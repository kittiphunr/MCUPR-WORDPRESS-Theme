<?php
/**
 * Menu management (inc/menus.php) against the real WordPress: the fields in Appearance → Menus,
 * saving them (capability + sanitising), and the front-end markup.
 *   php php/menus.php [path-to-site]      (default: .site)
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site';
require rtrim( $site, '/' ) . '/wp-load.php';

$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

t( function_exists( 'mcupr_menu_item_save' ), 'the theme is active and loads inc/menus.php' );
wp_set_current_user( 1 );

$menu = wp_create_nav_menu( 'PHP test ' . wp_rand() );
$top  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Top', 'menu-item-url' => '/#t', 'menu-item-status' => 'publish' ) );
$sub  = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Sub', 'menu-item-url' => '/#s', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $top, 'menu-item-description' => 'About <b>sub</b>' ) );

// ---- fields UI
ob_start(); do_action( 'wp_nav_menu_item_custom_fields', $top, get_post( $top ), 0, (object) array(), 0 ); $f0 = ob_get_clean();
ob_start(); do_action( 'wp_nav_menu_item_custom_fields', $sub, get_post( $sub ), 1, (object) array(), 0 ); $f1 = ob_get_clean();
t( strpos( $f0, 'name="menu-item-mcupr-icon[' . $top . ']"' ) !== false && substr_count( $f0, '<option' ) >= count( mcupr_icon_paths() ), 'fields: an icon select listing every icon' );
t( strpos( $f0, 'menu-item-mcupr-mega[' . $top . ']' ) !== false && strpos( $f0, 'menu-item-mcupr-cols[' . $top . ']' ) !== false, 'fields: mega checkbox + columns on a top-level item' );
t( strpos( $f1, 'menu-item-mcupr-icon' ) !== false && strpos( $f1, 'mcupr-mega' ) === false, 'fields: sub-level items get the icon only' );

// ---- saving
$post = function ( $item, $icon, $mega, $cols ) {
	$_POST = array( 'menu-item-mcupr-present' => array( $item => '1' ), 'menu-item-mcupr-icon' => array( $item => $icon ), 'menu-item-mcupr-cols' => array( $item => $cols ) );
	if ( $mega ) { $_POST['menu-item-mcupr-mega'] = array( $item => '1' ); }
};
$post( $top, 'book', true, '4' );
mcupr_menu_item_save( $menu, $top );
t( get_post_meta( $top, '_mcupr_menu_icon', true ) === 'book' && get_post_meta( $top, '_mcupr_menu_mega', true ) === '1' && get_post_meta( $top, '_mcupr_menu_cols', true ) === '4', 'save: icon, mega and 4 columns stored' );
$post( $top, '"><script>', false, '9' );
mcupr_menu_item_save( $menu, $top );
t( get_post_meta( $top, '_mcupr_menu_icon', true ) === '' && get_post_meta( $top, '_mcupr_menu_mega', true ) === '' && get_post_meta( $top, '_mcupr_menu_cols', true ) === '3', 'save: unknown icon dropped, mega cleared, columns clamped to 3' );
$post( $top, 'home', true, '2' );
mcupr_menu_item_save( $menu, $top );
$_POST = array();
mcupr_menu_item_save( $menu, $top ); // e.g. a save from the Customizer without our fields
t( get_post_meta( $top, '_mcupr_menu_icon', true ) === 'home', 'save: a request without our fields leaves the values alone' );
$sub_user = wp_insert_user( array( 'user_login' => 'sub' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub_user );
$post( $top, 'star', true, '4' );
mcupr_menu_item_save( $menu, $top );
t( get_post_meta( $top, '_mcupr_menu_icon', true ) === 'home', 'save: refused without edit_theme_options' );
wp_set_current_user( 1 );
$_POST = array();

// ---- front end
update_post_meta( $top, '_mcupr_menu_mega', 1 );
update_post_meta( $top, '_mcupr_menu_cols', 2 );
update_post_meta( $sub, '_mcupr_menu_icon', 'award' );
$html = wp_nav_menu( array( 'menu' => $menu, 'echo' => false, 'menu_class' => 'menu', 'container' => false ) );
t( strpos( $html, 'mcupr-mega' ) !== false && strpos( $html, 'mega-cols-2' ) !== false, 'front: mega + column classes on the top-level item' );
t( preg_match( '#<a [^>]*><span class="ic has-ic" aria-hidden="true"><svg.*?</svg></span><span class="t">Top</span></a>#s', $html ) === 1, 'front: icon span in front of the text, hidden from screen readers' );
t( strpos( $html, '<span class="d">About sub</span>' ) !== false && strpos( $html, '<b>sub' ) === false, 'front: description (tags stripped by WordPress, escaped by the theme) under a sub-level link' );
$sub_html = substr( $html, strpos( $html, 'Sub' ) - 400 );
t( strpos( $sub_html, 'award' ) === false && strpos( $html, 'has-icon' ) !== false, 'front: has-icon class (the icon itself is inline SVG)' );
$plain = wp_nav_menu( array( 'menu' => $menu, 'echo' => false, 'menu_class' => 'footer-cols', 'container' => false ) );
t( strpos( $plain, 'class="ic has-ic"' ) !== false, 'front: icons also work in other menus (footer)' );
delete_post_meta( $top, '_mcupr_menu_icon' );
$html2 = wp_nav_menu( array( 'menu' => $menu, 'echo' => false, 'menu_class' => 'menu', 'container' => false ) );
t( strpos( $html2, '<span class="ic" aria-hidden="true">T</span>' ) !== false, 'front: top-level item without an icon keeps its initial-letter badge (sidebar / drawer)' );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_nav_menu( $menu );
wp_delete_user( $sub_user );
echo $fails ? "$fails failed\n" : "all passed\n";
exit( $fails ? 1 : 0 );
