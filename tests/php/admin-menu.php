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
$ours = array( 'mcupr_event', 'mcupr_album', 'mcupr_video', 'mcupr_faq', 'mcupr_topic', 'mcupr_poll', 'mcupr_msg', 'mcupr_person', 'mcupr_unit' );
$stray = array_filter( $top, function ( $s ) use ( $ours ) { foreach ( $ours as $o ) { if ( false !== strpos( $s, 'post_type=' . $o ) ) { return true; } } return 'mcupr-status' === $s; } );
t( ! $stray, 'none of the theme\'s sections is a top-level menu of its own' . ( $stray ? ' (' . implode( ', ', $stray ) . ')' : '' ) );
$sub = array_map( function ( $s ) { return $s[2]; }, $submenu['mcupr'] );
foreach ( $ours as $o ) { t( in_array( 'edit.php?post_type=' . $o, $sub, true ), 'MCUPR menu: ' . $o . ' list' ); }
// works and awards have a menu of their own, like the notices (a card in the control center, not a line under MCUPR)
t( in_array( 'edit.php?post_type=mcupr_work', $top, true ) && ! in_array( 'edit.php?post_type=mcupr_work', $sub, true ), 'works and awards: a top-level menu of its own, no longer under MCUPR' );
t( 0 === count( array_filter( $sub, function ( $x ) { return false !== strpos( $x, 'mcupr_work' ); } ) ), 'works and awards: nothing of it is left in the MCUPR submenu (not even its categories)' );
t( in_array( 'edit.php?post_type=mcupr_notice', $top, true ), 'notices and documents: a top-level menu of its own' );
$wsub = array_map( function ( $x ) { return $x[2]; }, isset( $submenu['edit.php?post_type=mcupr_work'] ) ? $submenu['edit.php?post_type=mcupr_work'] : array() );
t( in_array( 'edit.php?post_type=mcupr_work', $wsub, true ) && in_array( 'post-new.php?post_type=mcupr_work', $wsub, true ) && 1 === count( array_filter( $wsub, function ( $x ) { return 0 === strpos( $x, 'edit-tags.php?taxonomy=mcupr_work_cat' ); } ) ), 'works and awards: its own menu has the list, "add new" and the work categories' );
add_filter( 'mcupr_admin_standalone_types', '__return_empty_array' );
t( array() === mcupr_admin_standalone_types(), 'standalone: the filter can put every section back under MCUPR' );
remove_all_filters( 'mcupr_admin_standalone_types' );
t( array( 'mcupr_work' ) === mcupr_admin_standalone_types(), 'standalone: by default only works and awards keep a menu of their own' );
foreach ( array( 'mcupr-forum', 'mcupr-staff-hub', 'mcupr-status' ) as $p ) { t( in_array( $p, $sub, true ), 'MCUPR menu: page ' . $p ); }
t( in_array( 'mcupr-groups-import', $sub, true ), 'MCUPR menu: page mcupr-groups-import (the import of work groups is under MCUPR, not under the list of groups that no longer has a menu of its own)' );
$GLOBALS['pagenow'] = 'admin.php'; $GLOBALS['plugin_page'] = 'mcupr-groups-import'; $_GET['page'] = 'mcupr-groups-import';
t( user_can_access_admin_page(), 'work groups import: an administrator can open admin.php?page=mcupr-groups-import (no "not allowed" page)' );
$GLOBALS['pagenow'] = 'index.php'; $GLOBALS['plugin_page'] = ''; unset( $_GET['page'] );
foreach ( array( 'mcupr_video_cat', 'mcupr_faq_cat', 'mcupr_board', 'mcupr_person_group' ) as $tx ) {
	t( count( array_filter( $sub, function ( $s ) use ( $tx ) { return 0 === strpos( $s, 'edit-tags.php?taxonomy=' . $tx ); } ) ) === 1, 'MCUPR menu: the categories of ' . $tx );
}
t( 0 === count( array_filter( $sub, function ( $s ) { return 0 === strpos( $s, 'post-new.php?post_type=mcupr' ); } ) ), 'MCUPR menu: no "Add new" duplicates (the lists have the button)' );
$names = array_map( function ( $s ) { return wp_strip_all_tags( $s[0] ); }, $submenu['mcupr'] );
t( 0 === count( array_filter( $names, function ( $n ) { return preg_match( '/^(All |ทั้งหมด)|ทั้งหมด$/u', $n ); } ) ), 'MCUPR menu: the entries carry the names of the sections (not "All …")' . ' – ' . implode( ' | ', $names ) );
t( 'mcupr' === $sub[0], 'MCUPR menu: the overview comes first' );
$iv = array_search( 'edit.php?post_type=mcupr_video', $sub, true ); $ivc = array_search( 'edit-tags.php?taxonomy=mcupr_video_cat&post_type=mcupr_video', $sub, true );
t( false !== $ivc && $ivc === $iv + 1, 'MCUPR menu: a section\'s categories follow it' );
t( (bool) array_filter( $sub, function ( $s ) { return 0 === strpos( $s, 'customize.php' ); } ), 'MCUPR menu: a shortcut to the theme options' );
// the menu is short: the control center and the groups are shown, everything else stays registered but is hidden
$shown = array_values( array_filter( $submenu['mcupr'], function ( $it ) { return false === strpos( isset( $it[4] ) ? $it[4] : '', 'mcupr-sub-hidden' ); } ) );
$shown_slugs = array_map( function ( $it ) { return $it[2]; }, $shown );
t( 'mcupr' === $shown_slugs[0] && 9 === count( $shown_slugs ), 'MCUPR menu: only the control center and 8 groups are shown (' . count( $shown_slugs ) . ')' );
t( 'Control center' === wp_strip_all_tags( $submenu['mcupr'][0][0] ) || 'ศูนย์ควบคุม' === wp_strip_all_tags( $submenu['mcupr'][0][0] ), 'MCUPR menu: the first entry is the control center' );
foreach ( array( 'start', 'content', 'look', 'visitors', 'students', 'people', 'system', 'help' ) as $g ) { t( in_array( 'admin.php?page=mcupr&cat=' . $g, $shown_slugs, true ), 'MCUPR menu: the group "' . $g . '" has its page' ); }
t( count( $sub ) > 20 && count( $submenu['mcupr'] ) === count( $sub ), 'MCUPR menu: the pages below the groups are still registered (' . count( $sub ) . ' in all), so they can be opened' );
// a registrar (no right to edit pages) sees the control center, the students group and help
$rg = wp_insert_user( array( 'user_login' => 'rg' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'rg' . wp_rand() . '@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
$prev = get_current_user_id();
wp_set_current_user( $rg );
// their standard also allows uploading a file and opening the answers of the forms (where the requests are listed)
t( array( 'content', 'visitors', 'students', 'help' ) === array_keys( mcupr_cc_cats() ), 'a registrar has the students group, the media library, the form answers and help, nothing else' );
wp_set_current_user( $ed2 = wp_insert_user( array( 'user_login' => 'ed2' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'ed2' . wp_rand() . '@example.org', 'role' => 'editor' ) ) );
$ec = array_keys( mcupr_cc_cats() );
t( in_array( 'content', $ec, true ) && ! in_array( 'system', $ec, true ) && ! in_array( 'look', $ec, true ) && ! in_array( 'start', $ec, true ), 'an editor has the content group but not the system or look groups (no theme options)' );
wp_set_current_user( $prev );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $rg ); wp_delete_user( $ed2 );
// the other menus are untouched
foreach ( array( 'edit.php', 'upload.php', 'edit.php?post_type=page', 'edit-comments.php', 'themes.php' ) as $core ) { t( in_array( $core, $top, true ), 'WordPress menu kept: ' . $core ); }
// an editor (not an administrator) sees the content sections but not the maintenance page
$ed = wp_insert_user( array( 'user_login' => 'ed' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'ed' . wp_rand() . '@example.org', 'role' => 'editor' ) );
t( ! user_can( $ed, 'manage_options' ) && user_can( $ed, 'edit_pages' ), 'an editor can use the MCUPR sections (edit_pages) but not the maintenance page (manage_options)' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ed );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
