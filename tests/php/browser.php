<?php
/**
 * Finder-style news browser: category zones (hierarchy or Customizer), counts, tags kept apart,
 * the built-in site creator and its overrides.  Runs on the starter site (needs starter-fixtures.php first).
 *   php php/browser.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function mods( $a ) { foreach ( $a as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); } }
wp_set_current_user( 0 );

// ---- zones from the hierarchy
mods( array( 'mcupr_cat_zones' => null ) );
$z = mcupr_browser_zones();
$labels = wp_list_pluck( $z, 'label' );
t( in_array( 'วิชาการ', $labels, true ), 'zones: a parent category with children is a zone' );
$ac = $z[ array_search( 'วิชาการ', $labels, true ) ];
t( $ac['root'] && $ac['root']->slug === 'academic' && wp_list_pluck( $ac['terms'], 'slug' ) === array( 'research', 'service' ), 'zones: its children are the items (' . implode( ',', wp_list_pluck( $ac['terms'], 'slug' ) ) . ')' );
$flat = array();
foreach ( $z as $zone ) { if ( ! $zone['root'] ) { $flat = array_merge( $flat, wp_list_pluck( $zone['terms'], 'slug' ) ); } }
t( in_array( 'uncategorized', $flat, true ) && ! in_array( 'research', $flat, true ), 'zones: flat top-level categories are collected in one zone, children are not repeated' );
$root = $ac['root'];
t( mcupr_browser_total( $root ) === 4, 'zones: a parent counts the posts of its children (' . mcupr_browser_total( $root ) . ')' );

// ---- zones from the Customizer
mods( array( 'mcupr_cat_zones' => "ข่าว: uncategorized, nothing-here\nวิจัย: research, nothing-here\nไม่มีโซน: missing" ) );
$z = mcupr_browser_zones();
$map = array();
foreach ( $z as $zone ) { $map[ $zone['label'] ] = wp_list_pluck( $zone['terms'], 'slug' ); }
t( isset( $map['ข่าว'] ) && $map['ข่าว'] === array( 'uncategorized' ), 'custom zones: slugs are grouped under the given name' );
t( isset( $map['วิจัย'] ) && $map['วิจัย'] === array( 'research' ), 'custom zones: unknown slugs are ignored' );
t( ! isset( $map['ไม่มีโซน'] ), 'custom zones: a zone without any real category is dropped' );
$rest = array();
foreach ( $z as $zone ) { if ( ! in_array( $zone['label'], array( 'ข่าว', 'วิจัย' ), true ) ) { $rest = array_merge( $rest, wp_list_pluck( $zone['terms'], 'slug' ) ); } }
t( in_array( 'service', $rest, true ) && ! in_array( 'research', $rest, true ), 'custom zones: categories not named anywhere are listed under "other"' );
mods( array( 'mcupr_cat_zones' => null ) );

// ---- sidebar markup on a tag archive: tags apart from categories
$tag = get_term_by( 'name', 'โครงการ', 'post_tag' );
global $wp_query;
$wp_query = new WP_Query( array( 'tag_id' => $tag->term_id ) );
$GLOBALS['wp_the_query'] = $wp_query;
$h = mcupr_browser_sidebar();
$cats_at = strpos( $h, 'fb-h"' ); $tags_at = strpos( $h, 'fb-h-tags' );
t( $cats_at !== false && $tags_at !== false && $cats_at < $tags_at, 'sidebar: categories and tags are two separate sections' );
$cat_part = substr( $h, 0, $tags_at );
t( strpos( $cat_part, '/tag/' ) === false, 'sidebar: no tag link appears in the categories section' );
t( strpos( substr( $h, $tags_at ), '/category/' ) === false, 'sidebar: no category link appears in the tags section' );
t( strpos( $h, 'data-fb-filter' ) !== false && strpos( $h, '<details class="fb-drawer" open>' ) !== false, 'sidebar: filter box and the foldable drawer for phones' );
t( preg_match( '/aria-current="page"[^>]*>.*?โครงการ/s', $h ) === 1, 'sidebar: the current tag is marked' );
t( strpos( mcupr_browser_toolbar(), 'data-fb-view="gallery"' ) !== false && strpos( mcupr_browser_toolbar(), 'role="radiogroup"' ) !== false, 'toolbar: view switcher (cards / list / gallery)' );

// ---- the site creator
mods( array( 'mcupr_credit_name' => null, 'mcupr_credit_hide' => null, 'mcupr_credit_photo' => null, 'mcupr_credit_email' => null, 'mcupr_credit_role' => null ) );
$c = mcupr_credit();
t( $c && 'นายกิตติพันธ์ รัตนคร' === $c['name'] && 'Kittiphun Ruttanakorn' === $c['en'] && 'bundled' === $c['img'] && 'kittiphun.rut@mcu.ac.th' === $c['email'] && 'https://github.com/kittiphunr' === $c['github'], 'credit: the built-in creator (name, English name, photo, contacts)' );
t( file_exists( get_theme_file_path( 'assets/img/developer.jpg' ) ) && strpos( mcupr_credit_photo_html( $c, 'x' ), 'assets/img/developer.jpg' ) !== false, 'credit: the bundled photo exists and is used' );
mods( array( 'mcupr_credit_email' => 'me@example.org' ) );
t( 'me@example.org' === mcupr_credit()['email'] && 'นายกิตติพันธ์ รัตนคร' === mcupr_credit()['name'], 'credit: a single field can be overridden' );
mods( array( 'mcupr_credit_email' => null, 'mcupr_credit_name' => 'ทีมงาน' ) );
$c = mcupr_credit();
t( 'ทีมงาน' === $c['name'] && '' === $c['email'] && '' === $c['en'] && '' === $c['img'], 'credit: another name drops the built-in contacts and photo (no one else gets them)' );
$h = mcupr_credit_photo_html( mcupr_credit(), 'x' );
t( strpos( $h, 'developer.jpg' ) === false && strpos( $h, 'credit-in' ) !== false, 'credit: the initial replaces the photo for another creator' );
mods( array( 'mcupr_credit_name' => null, 'mcupr_credit_hide' => true ) );
t( null === mcupr_credit(), 'credit: can be hidden' );
mods( array( 'mcupr_credit_hide' => null ) );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
