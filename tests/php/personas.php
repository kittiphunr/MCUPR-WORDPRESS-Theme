<?php
/** The "I am…" picker over the home quick links: who a link is for (meta, tokens of the starter file), the class on the hero tiles, the kinds found in the menu, the picker in the hero.   php php/personas.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$names = mcupr_personas();
t( array( 'future', 'student', 'parent', 'staff', 'alumni' ) === array_keys( $names ), 'five kinds of visitor, in this order' );
$ok = true;
foreach ( $names as $k => $key ) { foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) { if ( mcupr_t( $key, $l ) === $key || '' === mcupr_t( $key, $l ) ) { $ok = false; } } }
t( $ok && '' !== mcupr_t( 'persona_iam', 'ja' ) && '' !== mcupr_t( 'persona_pick', 'zh' ) && '' !== mcupr_t( 'persona_shown', 'en' ), 'every label has its text in the four languages' );

// a throw-away menu: three items
$mid = wp_create_nav_menu( 'persona-test-' . getmypid() );
$ids = array();
foreach ( array( 'A', 'B', 'C' ) as $i => $n ) {
	$ids[ $n ] = wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => $n, 'menu-item-url' => home_url( '/' . strtolower( $n ) . '/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-position' => $i + 1 ) );
}
// the admin form: ticks are saved, an unknown kind and a stranger's request are not
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
$_POST = array( 'menu-item-mcupr-for-present' => array( $ids['A'] => '1' ), 'menu-item-mcupr-for' => array( $ids['A'] => array( 'student', 'hacker', 'parent' ) ) );
mcupr_persona_item_save( $mid, $ids['A'] );
t( array( 'student', 'parent' ) === mcupr_menu_item_for( $ids['A'] ), 'the ticks are saved (a kind that does not exist is dropped)' );
$_POST = array( 'menu-item-mcupr-for' => array( $ids['B'] => array( 'staff' ) ) ); // no "present" field: not our form
mcupr_persona_item_save( $mid, $ids['B'] );
t( array() === mcupr_menu_item_for( $ids['B'] ), 'a request without the form field changes nothing' );
wp_set_current_user( 0 );
$_POST = array( 'menu-item-mcupr-for-present' => array( $ids['B'] => '1' ), 'menu-item-mcupr-for' => array( $ids['B'] => array( 'staff' ) ) );
mcupr_persona_item_save( $mid, $ids['B'] );
t( array() === mcupr_menu_item_for( $ids['B'] ), 'a visitor who is not allowed to edit menus cannot save them' );
$_POST = array();
// the starter file's tokens
mcupr_persona_import_tokens( $mid, $ids['C'], array( 'menu-item-classes' => array( 'mcupr-icon-book', 'mcupr-for-alumni', 'mcupr-for-nobody', 'mine' ) ) );
t( array( 'alumni' ) === mcupr_menu_item_for( $ids['C'] ) && array( 'mine' ) === array_values( (array) get_post_meta( $ids['C'], '_menu_item_classes', true ) ), 'tokens of the starter file become the tick (a made-up kind is dropped); other classes stay' );

// the hero tiles carry the classes; the kinds are found in the markup
$html = wp_nav_menu( array( 'menu' => $mid, 'container' => false, 'menu_class' => 'hero-menu', 'depth' => 1, 'echo' => false ) );
t( preg_match( '/<li[^>]*class="[^"]*\bfor-student\b[^"]*\bfor-parent\b/', $html ) || preg_match( '/<li[^>]*class="[^"]*\bfor-parent\b[^"]*\bfor-student\b/', $html ), 'a tile for some visitors has a for-… class for each' );
t( array( 'student', 'parent', 'alumni' ) === mcupr_personas_in( $html ), 'the kinds found in the menu: ' . implode( ',', mcupr_personas_in( $html ) ) );
$plain = wp_nav_menu( array( 'menu' => $mid, 'container' => false, 'menu_class' => 'menu', 'depth' => 1, 'echo' => false ) );
t( false === strpos( $plain, 'for-student' ), 'other menus do not get the classes' );
t( array() === mcupr_personas_in( '<ul class="hero-menu"><li class="menu-item">x</li></ul>' ), 'no marked link: no kind, so no picker' );
t( array() === mcupr_personas_in( '<li class="menu-item before-student-for-student">x</li>' ), 'a class that only contains the name does not count' );

// the hero shows the picker only when links are marked, and only when it is switched on
$loc = get_nav_menu_locations(); $old = isset( $loc['quick'] ) ? $loc['quick'] : null;
$loc['quick'] = $mid; set_theme_mod( 'nav_menu_locations', $loc );
ob_start(); get_template_part( 'template-parts/hero', null, array( 'hero_id' => 0, 'hero_img' => false, 'line1' => 'a', 'line2' => 'b' ) ); $hero = ob_get_clean();
t( false !== strpos( $hero, 'data-persona="student,parent,alumni"' ) && false !== strpos( $hero, 'role="listbox"' ) && 3 === substr_count( $hero, 'data-persona-opt=' ), 'the hero has the picker with one option per kind' );
t( false !== strpos( $hero, 'class="persona" hidden' ), 'the picker starts hidden (no script: all the links are shown)' );
set_theme_mod( 'mcupr_hero_persona', false );
ob_start(); get_template_part( 'template-parts/hero', null, array( 'hero_id' => 0, 'hero_img' => false, 'line1' => 'a', 'line2' => 'b' ) ); $off = ob_get_clean();
t( false === strpos( $off, 'data-persona' ) && false !== strpos( $off, 'hero-menu' ), 'switched off in the Customizer: no picker, the links stay' );
remove_theme_mod( 'mcupr_hero_persona' );
if ( null === $old ) { unset( $loc['quick'] ); } else { $loc['quick'] = $old; }
set_theme_mod( 'nav_menu_locations', $loc );
wp_delete_nav_menu( $mid );

/* ---------------------------------------------- the one-press offer for a menu built before this existed */
$mid2 = wp_create_nav_menu( 'persona-fill-' . getmypid() );
$rows = array(
	array( 'สมัครเรียน', '/admissions/', 'edit', array( 'future' ) ),
	array( 'หลักสูตรที่เปิดสอน', '/programs/', 'book', array( 'future', 'student' ) ),
	array( 'ทุนการศึกษา', '/scholarships/', 'money', array( 'future', 'student', 'parent' ) ),
	array( 'ปฏิทินการศึกษา', '/calendar/', 'calendar', array( 'student', 'parent', 'staff' ) ),
	array( 'ห้องสมุด', '/library/', 'folder', array( 'student', 'staff' ) ),
	array( 'ศิษย์เก่า', '/alumni/', 'users', array( 'alumni' ) ),
	array( 'ติดต่อ', '/contact/', 'mail', array() ),
);
$ids2 = array();
foreach ( $rows as $i => $r ) {
	$iid = wp_update_nav_menu_item( $mid2, 0, array( 'menu-item-title' => $r[0], 'menu-item-url' => home_url( $r[1] ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-position' => $i + 1 ) );
	update_post_meta( $iid, '_mcupr_menu_icon', $r[2] );
	$ids2[] = $iid;
}
$loc2 = get_nav_menu_locations();
$old2 = isset( $loc2['quick'] ) ? $loc2['quick'] : null;
$loc2['quick'] = $mid2;
set_theme_mod( 'nav_menu_locations', $loc2 );
delete_option( 'mcupr_persona_hide_hint' );
t( $mid2 === mcupr_persona_quick_menu_id() && 7 === count( mcupr_persona_quick_items() ), 'the links of the home page are found through the "Quick links" location' );
t( mcupr_persona_needs_filling(), 'a menu whose links say nothing about who they are for is offered the one-press fill' );
$n2 = mcupr_persona_fill();
$got = array();
foreach ( $ids2 as $k => $iid ) {
	$got[] = mcupr_menu_item_for( $iid );
}
$want = array_map( static function ( $r ) { return $r[3]; }, $rows );
t( 6 === $n2 && $want === $got, 'the guess matches what a person would tick (apply, programmes, scholarships, calendar, library, alumni; contact stays for everyone)' );
t( ! mcupr_persona_needs_filling(), 'once the links have their groups the offer is gone' );
t( array( 'future', 'student', 'parent', 'staff', 'alumni' ) === mcupr_personas_in( wp_nav_menu( array( 'menu' => $mid2, 'container' => false, 'menu_class' => 'hero-menu', 'depth' => 1, 'echo' => false ) ) ), 'and the home page now has all five kinds in its picker' );
foreach ( $ids2 as $iid ) {
	delete_post_meta( $iid, '_mcupr_menu_for' );
}
update_option( 'mcupr_persona_hide_hint', 1, false );
t( ! mcupr_persona_needs_filling(), '"do not ask again" stops the offer even when nothing is marked' );
delete_option( 'mcupr_persona_hide_hint' );
set_theme_mod( 'mcupr_hero_persona', false );
t( ! mcupr_persona_needs_filling(), 'and so does switching the picker off' );
remove_theme_mod( 'mcupr_hero_persona' );
if ( null === $old2 ) { unset( $loc2['quick'] ); } else { $loc2['quick'] = $old2; }
set_theme_mod( 'nav_menu_locations', $loc2 );
wp_delete_nav_menu( $mid2 );

echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
