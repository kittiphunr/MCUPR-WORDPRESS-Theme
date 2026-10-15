<?php
/**
 * The "add-ons" starter file (mcupr/starter/mcupr-starter-addons.xml) on a site that already has the older starter content:
 * only the new things appear, nothing old is overwritten or duplicated.   php php/starter-addons.php .site-starter
 * (run right after php/starter-import.php; it leaves the site as it found it)
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
define( 'WP_LOAD_IMPORTERS', true );
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
require_once WP_PLUGIN_DIR . '/wordpress-importer/wordpress-importer.php';
if ( ! class_exists( 'WP_Import' ) ) { require_once WP_PLUGIN_DIR . '/wordpress-importer/src/class-wp-import.php'; }
wp_set_current_user( 1 );
function by_slug( $slug, $type ) { $p = get_posts( array( 'post_type' => $type, 'name' => $slug, 'post_status' => 'any', 'posts_per_page' => 1 ) ); return $p ? $p[0] : null; }
function count_all() {
	$o = array();
	foreach ( array( 'page', 'nav_menu_item', 'mcupr_event', 'mcupr_work', 'mcupr_unit', 'mcupr_faq', 'mcupr_person', 'mcupr_topic', 'mcupr_fb', 'mcupr_poll' ) as $t ) {
		$o[ $t ] = (int) wp_count_posts( $t )->publish;
	}
	$o['menus'] = count( wp_get_nav_menus() );
	return $o;
}
$new_pages = array( 'documents', 'events-calendar', 'forum', 'welcome-demo', 'internal-form', 'survey', 'student-portal', 'study-plan', 'donate', 'quality-assurance' );
// make this site look like one that has only the OLD starter: remove the new pages and the new samples, edit an old page
foreach ( $new_pages as $s ) { $p = by_slug( $s, 'page' ); if ( $p ) { wp_delete_post( $p->ID, true ); } }
foreach ( array( 'mcupr_fb', 'mcupr_poll' ) as $t ) { foreach ( get_posts( array( 'post_type' => $t, 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $p ) { wp_delete_post( $p->ID, true ); } }
$hist = by_slug( 'history', 'page' );
wp_update_post( array( 'ID' => $hist->ID, 'post_content' => 'MY OWN TEXT – do not touch', 'post_title' => 'ประวัติของเรา (แก้เอง)' ) );
$mine = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'หน้าของฉัน', 'post_content' => 'mine' ) );
$before = count_all();
$main   = wp_get_nav_menu_items( get_nav_menu_locations()['primary_th'] );
$file   = __DIR__ . '/../../mcupr/starter/mcupr-starter-addons.xml';
t( file_exists( $file ), 'the add-ons file exists' );
$imp = new WP_Import(); $imp->fetch_attachments = false;
ob_start(); $imp->import( $file ); $log = ob_get_clean();
t( false !== strpos( $log, 'All done' ), 'add-ons: the importer finished' );
$after = count_all();
foreach ( $new_pages as $s ) { t( null !== by_slug( $s, 'page' ), 'add-ons: the new page "' . $s . '" arrived' ); }
t( 'MY OWN TEXT – do not touch' === get_post_field( 'post_content', $hist->ID ) && 'ประวัติของเรา (แก้เอง)' === get_post_field( 'post_title', $hist->ID ), 'add-ons: an old page you edited is not overwritten' );
t( 'mine' === get_post_field( 'post_content', $mine ), 'add-ons: your own page is untouched' );
t( $after['page'] === $before['page'] + count( $new_pages ), 'add-ons: exactly the ten new pages were added (' . $before['page'] . ' → ' . $after['page'] . ')' );
t( $after['nav_menu_item'] === $before['nav_menu_item'] && $after['menus'] === $before['menus'], 'add-ons: menus are left alone (' . $after['nav_menu_item'] . ' items, ' . $after['menus'] . ' menus)' );
$main2 = wp_get_nav_menu_items( get_nav_menu_locations()['primary_th'] );
t( count( $main ) === count( $main2 ), 'add-ons: the main menu has the same items' );
foreach ( array( 'mcupr_event', 'mcupr_work', 'mcupr_unit', 'mcupr_faq', 'mcupr_person' ) as $t ) { t( $after[ $t ] === $before[ $t ], 'add-ons: ' . $t . ' samples were not duplicated (' . $after[ $t ] . ')' ); }
t( 1 === $after['mcupr_fb'] && 1 === $after['mcupr_poll'], 'add-ons: a sample form and a sample poll were added' );
$form = by_slug( 'staff-needs', 'mcupr_fb' );
$f    = mcupr_fb_fields( $form->ID );
t( 10 === count( $f ) && 'ชื่อ-สกุล' === $f[1]['label'] && 'select' === $f[2]['type'] && 4 === count( $f[2]['options'] ), 'add-ons: the sample form arrived with all its fields, Thai letters intact (' . count( $f ) . ')' );
t( 'users' !== mcupr_fb_opts( $form->ID )['who'] && 'open' === mcupr_fb_state( $form->ID ), 'add-ons: the sample form is open' );
t( false !== strpos( get_post_field( 'post_content', by_slug( 'internal-form', 'page' )->ID ), '[mcupr_form slug="staff-needs"]' ) && false !== strpos( do_shortcode( '[mcupr_form slug="staff-needs"]' ), 'fbform-' . $form->ID ), 'add-ons: the form page shows the form' );
// importing the add-ons a second time changes nothing
$imp2 = new WP_Import(); $imp2->fetch_attachments = false;
ob_start(); $imp2->import( $file ); ob_end_clean();
t( count_all() === $after, 'add-ons: importing them again changes nothing' );
// leave the site as it was
wp_delete_post( $mine, true );
wp_update_post( array( 'ID' => $hist->ID, 'post_content' => '', 'post_title' => 'ประวัติและอัตลักษณ์' ) );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
