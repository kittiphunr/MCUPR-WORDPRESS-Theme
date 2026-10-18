<?php
/** The toolbar for editors as a floating icon: the setting, the labels, the groups.   php php/toolbar.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function node( $id, $title, $href, $parent = '', $meta = array() ) { return (object) array( 'id' => $id, 'title' => $title, 'href' => $href, 'parent' => $parent, 'group' => false, 'meta' => $meta ); }
wp_set_current_user( 0 );

remove_theme_mod( 'mcupr_toolbar' );
t( 'float' === mcupr_toolbar_mode(), 'default: the floating icon' );
set_theme_mod( 'mcupr_toolbar', 'wp' );
t( 'wp' === mcupr_toolbar_mode(), 'the WordPress bar can be chosen' );
set_theme_mod( 'mcupr_toolbar', 'nonsense' );
t( 'float' === mcupr_toolbar_mode(), 'anything else is the icon' );
remove_theme_mod( 'mcupr_toolbar' );
t( ! mcupr_toolbar_floats(), 'a visitor (no bar) gets no icon' );
wp_set_current_user( 1 );
unset( $GLOBALS['show_admin_bar'] ); // WordPress remembers the answer for the request
t( mcupr_toolbar_floats(), 'a signed-in editor on the site gets the icon' );
set_theme_mod( 'mcupr_toolbar', 'wp' );
t( ! mcupr_toolbar_floats(), 'with the WordPress bar chosen there is no icon' );
remove_theme_mod( 'mcupr_toolbar' );

t( 'Edit Page' === mcupr_toolbar_label( node( 'edit', '<span class="ab-icon"></span>Edit Page', '/x' ) ), 'a label: the little picture is dropped' );
t( '1 Comment in moderation' === mcupr_toolbar_label( node( 'comments', '<span class="ab-icon"></span><span class="ab-label">1</span><span class="screen-reader-text">1 Comment in moderation</span>', '/c' ) ), 'a bare number gets the words for screen readers' );
t( 'Updates' === mcupr_toolbar_label( node( 'updates', '<span class="ab-icon"></span>', '/u', '', array( 'title' => 'Updates' ) ) ), 'an icon only: the title of the item' );

$nodes = array();
foreach ( array(
	node( 'wp-logo', '<span class="ab-icon"></span>', '/about', '' ),
	node( 'about', 'About WordPress', '/about', 'wp-logo' ),
	node( 'site-name', 'My site', '/', '' ),
	node( 'dashboard', 'Dashboard', '/wp-admin/', 'site-name' ),
	node( 'customize', 'Customize', '/customize', '' ),
	node( 'mcupr-maint', 'Maintenance mode is ON', '/status', '' ),
	node( 'new-content', 'New', '/new', '' ),
	node( 'new-post', 'Post', '/new-post', 'new-content' ),
	node( 'new-page', 'Page', '/new-page', 'new-content' ),
	node( 'edit', 'Edit Page', '/edit', '' ),
	node( 'my-account', 'Howdy, X', '/profile', 'top-secondary' ),
	node( 'user-info', '<img> X', '/profile', 'user-actions' ),
	node( 'edit-profile', 'Edit Profile', '/profile', 'user-actions' ),
	node( 'logout', 'Log Out', '/logout', 'user-actions' ),
	node( 'search', '<form></form>', '', 'top-secondary' ),
	node( 'some-plugin', 'Plugin thing', '/plugin', '' ),
	node( 'no-link', 'Heading only', '', '' ),
) as $n ) { $nodes[ $n->id ] = $n; }
$g = mcupr_toolbar_groups( $nodes );
$flat = array();
foreach ( $g as $head => $list ) { foreach ( $list as $it ) { $flat[ $head ][] = $it[0]; } }
$heads = array_keys( $g );
t( array( __( 'This page', 'mcupr' ), __( 'Add new', 'mcupr' ), __( 'The website', 'mcupr' ), __( 'More', 'mcupr' ), __( 'My account', 'mcupr' ) ) === $heads, 'five groups in this order: this page, add new, the website, more, my account' );
t( array( 'Edit Page' ) === $flat[ __( 'This page', 'mcupr' ) ], 'this page: editing it' );
t( array( 'Post', 'Page' ) === $flat[ __( 'Add new', 'mcupr' ) ], 'add new: what the toolbar offers to add' );
$site_list = $flat[ __( 'The website', 'mcupr' ) ];
t( in_array( __( 'MCUPR control center', 'mcupr' ), $site_list, true ) && in_array( 'Dashboard', $site_list, true ) && in_array( 'Customize', $site_list, true ) && in_array( '🔧 Maintenance mode is ON', $site_list, true ), 'the website: the control center, the dashboard, customize and the maintenance notice' );
t( array( 'Plugin thing' ) === $flat[ __( 'More', 'mcupr' ) ], 'more: what another plugin put in the bar' );
t( array( 'Edit Profile', 'Log Out' ) === $flat[ __( 'My account', 'mcupr' ) ], 'my account: profile and log out' );
$all = wp_json_encode( $g );
t( false === strpos( $all, 'About WordPress' ) && false === strpos( $all, 'Heading only' ) && false === strpos( $all, 'form' ), 'the WordPress logo menu, items with no link and the search box are left out' );

wp_set_current_user( 0 );
$none = mcupr_toolbar_groups( array() );
t( array() === $none || array( __( 'The website', 'mcupr' ) ) === array_keys( $none ), 'no items: nothing but what a person with no rights may see' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
