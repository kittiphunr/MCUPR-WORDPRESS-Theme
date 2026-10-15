<?php
/** The theme's admin menus live together under "MCUPR".   php php/admin-menu.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
define( 'WP_ADMIN', true );
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
global $menu, $submenu, $_wp_submenu_nopriv, $_wp_menu_nopriv, $pagenow, $typenow;
$pagenow = 'index.php'; $typenow = '';
require ABSPATH . 'wp-admin/menu.php';
$top  = array();
foreach ( $menu as $m ) { $top[] = $m[2]; }
t( in_array( 'mcupr', $top, true ), 'there is one top-level "MCUPR" menu' );
$ours = array( 'mcupr_event', 'mcupr_work', 'mcupr_album', 'mcupr_video', 'mcupr_faq', 'mcupr_topic', 'mcupr_poll', 'mcupr_msg', 'mcupr_person', 'mcupr_unit' );
$stray = array_filter( $top, function ( $s ) use ( $ours ) { foreach ( $ours as $o ) { if ( false !== strpos( $s, 'post_type=' . $o ) ) { return true; } } return 'mcupr-status' === $s; } );
t( ! $stray, 'none of the theme\'s sections is a top-level menu of its own' . ( $stray ? ' (' . implode( ', ', $stray ) . ')' : '' ) );
$sub = array_map( function ( $s ) { return $s[2]; }, $submenu['mcupr'] );
foreach ( $ours as $o ) { t( in_array( 'edit.php?post_type=' . $o, $sub, true ), 'MCUPR menu: ' . $o . ' list' ); }
foreach ( array( 'mcupr-forum', 'mcupr-staff-hub', 'mcupr-status' ) as $p ) { t( in_array( $p, $sub, true ), 'MCUPR menu: page ' . $p ); }
foreach ( array( 'mcupr_work_cat', 'mcupr_video_cat', 'mcupr_faq_cat', 'mcupr_board', 'mcupr_person_group' ) as $tx ) {
	t( count( array_filter( $sub, function ( $s ) use ( $tx ) { return 0 === strpos( $s, 'edit-tags.php?taxonomy=' . $tx ); } ) ) === 1, 'MCUPR menu: the categories of ' . $tx );
}
t( 0 === count( array_filter( $sub, function ( $s ) { return 0 === strpos( $s, 'post-new.php?post_type=mcupr' ); } ) ), 'MCUPR menu: no "Add new" duplicates (the lists have the button)' );
$names = array_map( function ( $s ) { return wp_strip_all_tags( $s[0] ); }, $submenu['mcupr'] );
t( 0 === count( array_filter( $names, function ( $n ) { return preg_match( '/^(All |ทั้งหมด)|ทั้งหมด$/u', $n ); } ) ), 'MCUPR menu: the entries carry the names of the sections (not "All …")' . ' – ' . implode( ' | ', $names ) );
t( 'mcupr' === $sub[0], 'MCUPR menu: the overview comes first' );
$iv = array_search( 'edit.php?post_type=mcupr_video', $sub, true ); $ivc = array_search( 'edit-tags.php?taxonomy=mcupr_video_cat&post_type=mcupr_video', $sub, true );
t( false !== $ivc && $ivc === $iv + 1, 'MCUPR menu: a section\'s categories follow it' );
t( (bool) array_filter( $sub, function ( $s ) { return 0 === strpos( $s, 'customize.php' ); } ), 'MCUPR menu: a shortcut to the theme options' );
// the other menus are untouched
foreach ( array( 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'themes.php' ) as $core ) { t( in_array( $core, $top, true ), 'WordPress menu kept: ' . $core ); }
// an editor (not an administrator) sees the content sections but not the maintenance page
$ed = wp_insert_user( array( 'user_login' => 'ed' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'ed' . wp_rand() . '@example.org', 'role' => 'editor' ) );
t( ! user_can( $ed, 'manage_options' ) && user_can( $ed, 'edit_pages' ), 'an editor can use the MCUPR sections (edit_pages) but not the maintenance page (manage_options)' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ed );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
