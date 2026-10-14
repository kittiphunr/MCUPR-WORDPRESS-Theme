<?php
/**
 * Imports mcupr/starter/mcupr-starter.xml with the real WordPress Importer into a clean site and checks the result.
 *   MCUPR_NO_FIXTURES=1 SITE_DIR=.site-starter bash bootstrap.sh   (+ the importer plugin in wp-content/plugins)
 *   php php/starter-import.php .site-starter
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
define( 'WP_LOAD_IMPORTERS', true );
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$plugin = WP_PLUGIN_DIR . '/wordpress-importer/wordpress-importer.php';
t( file_exists( $plugin ), 'the WordPress Importer is present' );
require_once $plugin; // defines WP_Import (loads src/ lazily)
if ( ! class_exists( 'WP_Import' ) ) { require_once WP_PLUGIN_DIR . '/wordpress-importer/src/class-wp-import.php'; }
wp_set_current_user( 1 );

$file = __DIR__ . '/../../mcupr/starter/mcupr-starter.xml';
$imp  = new WP_Import();
$imp->fetch_attachments = false;
ob_start();
$imp->import( $file );
$log = ob_get_clean();
t( strpos( $log, 'All done' ) !== false, 'importer finished' );

// ---- pages
$pages = get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) );
$pages = array_values( array_filter( $pages, function ( $p ) { return '' !== get_post_meta( $p->ID, '_mcupr_en_title', true ); } ) ); // not WordPress' own sample pages
t( count( $pages ) === 55, '55 pages imported (' . count( $pages ) . ')' );
$bad = array_filter( $pages, function ( $p ) { return 'publish' !== $p->post_status || '' === trim( $p->post_content ); } );
t( ! $bad, 'every page is published and has content' );
foreach ( array( 'privacy-policy', 'cookie-policy', 'accessibility-statement', 'sitemap', 'contact', 'ita', 'oic' ) as $slug ) {
	t( (bool) get_page_by_path( $slug, OBJECT, 'page' ) || (bool) get_posts( array( 'name' => $slug, 'post_type' => 'page' ) ), "page /$slug exists" );
}
$about = get_posts( array( 'name' => 'about', 'post_type' => 'page' ) );
$hist  = get_posts( array( 'name' => 'history', 'post_type' => 'page' ) );
t( $about && $hist && (int) $hist[0]->post_parent === $about[0]->ID, 'child page parent is remapped (history under about)' );
t( 'Test' !== get_post_meta( $hist[0]->ID, '_mcupr_en_title', true ) && get_post_meta( $hist[0]->ID, '_mcupr_en_title', true ) === 'History', 'translated title meta stored' );
t( 3 === count( get_categories( array( 'hide_empty' => false, 'slug' => array( 'press-releases', 'activities', 'announcements' ) ) ) ) || count( array_filter( array( get_category_by_slug( 'press-releases' ), get_category_by_slug( 'activities' ), get_category_by_slug( 'announcements' ) ) ) ) === 3, 'three news categories' );

// ---- menus
$loc = get_nav_menu_locations();
foreach ( array( 'primary_th', 'primary_en', 'primary_zh', 'primary_ja', 'footer', 'quick', 'utility' ) as $l ) {
	t( ! empty( $loc[ $l ] ), "menu assigned to location $l" );
}
$main = wp_get_nav_menu_items( $loc['primary_th'] );
t( count( $main ) > 30, 'main menu has items (' . count( $main ) . ')' );
$top = array_values( array_filter( $main, function ( $i ) { return ! $i->menu_item_parent; } ) );
t( count( $top ) === 7, '7 top-level items in the main menu (' . count( $top ) . ')' );
$mega = array_filter( $top, function ( $i ) { return get_post_meta( $i->ID, '_mcupr_menu_mega', true ); } );
t( count( $mega ) === 3, '3 mega-menu items' );
$ids = wp_list_pluck( $main, 'ID' );
$broken = array_filter( $main, function ( $i ) use ( $ids ) { return $i->menu_item_parent && ! in_array( (int) $i->menu_item_parent, array_map( 'intval', $ids ), true ); } );
t( ! $broken, 'every sub-item points at an item of the same menu' );
$pg = array_filter( $main, function ( $i ) { return 'post_type' === $i->type; } );
$dead = array_filter( $pg, function ( $i ) { $p = get_post( $i->object_id ); return ! $p || 'publish' !== $p->post_status || ! $i->url; } );
t( ! $dead, 'page items link to published pages (remapped ids)' );
$en = wp_get_nav_menu_items( $loc['primary_en'] );
t( $en && 'Home' === $en[0]->title, 'English menu has English labels' );
$ja = wp_get_nav_menu_items( $loc['primary_ja'] );
t( $ja && 'ホーム' === $ja[0]->title, 'Japanese menu has Japanese labels' );
$footer = wp_get_nav_menu_items( $loc['footer'] );
t( count( array_filter( $footer, function ( $i ) { return ! $i->menu_item_parent; } ) ) === 4, 'footer has 4 columns' );
$home = home_url( '/' );
t( untrailingslashit( $main[0]->url ) === untrailingslashit( $home ), 'the "/" home item resolves to home_url (' . $main[0]->url . ')' );

$icons = array_filter( $main, function ( $i ) { return get_post_meta( $i->ID, '_mcupr_menu_icon', true ); } );
t( count( $icons ) >= 6, 'icons were moved into menu-item meta (' . count( $icons ) . ')' );
$leak = array_filter( $main, function ( $i ) { return (bool) preg_grep( '/^mcupr-/', (array) $i->classes ); } );
t( ! $leak, 'import tokens are removed from the CSS classes' );
t( 3 === (int) get_post_meta( reset( $mega )->ID, '_mcupr_menu_cols', true ), 'mega columns stored' );

// administration chart + works
$units = get_posts( array( 'post_type' => 'mcupr_unit', 'numberposts' => -1, 'post_status' => 'publish' ) );
t( count( $units ) === 13, '13 sample units of the administration chart (' . count( $units ) . ')' );
$pres = get_posts( array( 'post_type' => 'mcupr_unit', 'name' => 'unit-pres' ) );
$vpa  = get_posts( array( 'post_type' => 'mcupr_unit', 'name' => 'unit-vp-acad' ) );
t( $pres && $vpa && (int) $vpa[0]->post_parent === $pres[0]->ID, 'chart: parents are remapped (academic affairs under the president)' );
t( strpos( do_shortcode( '[mcupr_orgchart]' ), 'org-d4' ) !== false, 'chart: four levels render' );
$works = get_posts( array( 'post_type' => 'mcupr_work', 'numberposts' => -1, 'post_status' => 'publish' ) );
t( count( $works ) === 3 && count( get_terms( array( 'taxonomy' => 'mcupr_work_cat', 'hide_empty' => false ) ) ) === 3, '3 sample works and 3 work categories' );
t( '' !== mcupr_work_info( $works[0]->ID )['award'] && wp_get_object_terms( $works[0]->ID, 'mcupr_work_cat' ), 'work details and category imported' );
t( ! wp_get_object_terms( $works[0]->ID, 'category' ), 'imported works carry no news category' );
$wl = array_filter( $main, function ( $i ) { return '/works/' === substr( untrailingslashit( $i->url ), -7 ) || false !== strpos( $i->url, '/works' ); } );
t( (bool) $wl, 'the main menu links to /works/' );

// a second import must not duplicate pages
$n1 = count( $pages );
$imp2 = new WP_Import(); $imp2->fetch_attachments = false;
ob_start(); $imp2->import( $file ); ob_end_clean();
$n2 = count( array_filter( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any' ) ), function ( $p ) { return '' !== get_post_meta( $p->ID, '_mcupr_en_title', true ); } ) );
t( $n2 === $n1, 'importing twice does not duplicate pages (' . $n2 . ')' );
t( count( wp_get_nav_menu_items( $loc['primary_th'] ) ) === count( $main ) && count( wp_get_nav_menu_items( $loc['footer'] ) ) === count( $footer ), 'importing twice does not duplicate menu items' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
