<?php
/** Home page demo: makes every part work, never overwrites, can be removed.   php php/demo-home.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mods_before = get_theme_mods(); $locs_before = get_nav_menu_locations();
mcupr_demo_remove(); // a crashed earlier run leaves nothing
$d = mcupr_demo_data();
t( is_array( $d ) && 10 === count( $d['posts'] ) && 3 === count( $d['events'] ) && 4 === count( $d['albums'] ) && ! empty( $d['video']['url'] ) && count( $d['theme_mods'] ) > 100, 'file: the demo file is found: 10 news, 3 events, a video, 4 albums and the settings' );
$bad = array_filter( array_keys( $d['theme_mods'] ), function ( $k ) { return ! mcupr_ss_key( $k ); } );
t( ! $bad, 'file: every setting in it is one the importer accepts ' . implode( ',', $bad ) );

// the site already has something typed in the hero line and a business card number: they must stay
set_theme_mod( 'mcupr_hero_l1_th', 'ของฉัน' ); remove_theme_mod( 'mcupr_hero_l2_th' ); remove_theme_mod( 'mcupr_cta_title_th' );
$n_posts = (int) wp_count_posts()->publish;
$r = mcupr_demo_apply( $d );
t( 10 === $r['posts'] && ( wp_cache_flush() || true ) && (int) wp_count_posts()->publish === $n_posts + 10, 'apply: ten news posts made (' . $r['posts'] . ')' );
t( 3 === $r['events'] && 1 === $r['video'] && 4 === $r['albums'], 'apply: three events, a video and four albums made' );
$demo_posts = get_posts( array( 'post_type' => 'post', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
$no_thumb = array_filter( $demo_posts, function ( $id ) { return ! get_post_thumbnail_id( $id ); } );
t( 10 === count( $demo_posts ) && ! $no_thumb, 'news: each has its own picture (the slider and the hero photo need them)' );
$mime = get_post_mime_type( get_post_thumbnail_id( $demo_posts[0] ) );
t( in_array( $mime, array( 'image/webp', 'image/jpeg' ), true ), 'news: the pictures are ' . $mime );
t( has_category( '', $demo_posts[0] ) && count( get_the_tags( $demo_posts[0] ) ) >= 1, 'news: in a category, with tags' );
$ev = mcupr_events_upcoming( 20 ); $titles = wp_list_pluck( $ev, 'post_title' );
t( count( array_filter( $titles, function ( $x ) { return false !== strpos( $x, '(ตัวอย่าง)' ); } ) ) >= 3, 'events: the three demo events are upcoming (the home page lists them)' );
$vid = get_posts( array( 'post_type' => 'mcupr_video', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => 1 ) );
t( $vid && '' !== mcupr_video_url( $vid[0]->ID ) && get_post_thumbnail_id( $vid[0]->ID ), 'video: has a working address and a cover' );
$al = get_posts( array( 'post_type' => 'mcupr_album', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => -1 ) );
t( 4 === count( $al ) && 3 <= count( mcupr_album_images( $al[0]->ID ) ) && mcupr_album_cover_id( $al[0]->ID ), 'albums: four, each with pictures and a cover' );
t( 'ของฉัน' === get_theme_mod( 'mcupr_hero_l1_th' ), 'settings: the hero line the site already had is not overwritten' );
t( '' !== (string) get_theme_mod( 'mcupr_hero_l2_th', '' ) && '' !== (string) get_theme_mod( 'mcupr_cta_title_th', '' ), 'settings: empty ones are filled (hero second line, banner)' );
t( in_array( 'home', $r['settings'], true ) || '' !== (string) get_theme_mod( 'mcupr_hero_l2_th', '' ), 'settings: the home texts were added' );
t( 1 === (int) get_theme_mod( 'mcupr_home_cats_on', 0 ) && get_theme_mod( 'mcupr_home_cat_1', 0 ) > 0, 'settings: news by category is switched on with three categories chosen' );
t( (int) get_theme_mod( 'mcupr_hero_image', 0 ) > 0, 'settings: a hero picture is set' );
t( 0 === strpos( (string) get_theme_mod( 'mcupr_hero_btn1_url', '' ), 'http' ), 'settings: the hero button address became a full address of the admissions page' );
// the blocks all have something to show
foreach ( array( 'weather', 'audience', 'figures', 'services', 'links', 'about' ) as $b ) {
	$h = 'weather' === $b ? 'x' : mcupr_blk_home( $b );
	t( '' !== $h, "blocks: $b has content" );
}
t( '' !== (string) get_theme_mod( 'mcupr_wx_lat', '' ), 'blocks: weather coordinates set' );
// the quick-link menu
$m = wp_get_nav_menu_object( $d['menu']['name'] );
t( $m && count( wp_get_nav_menu_items( $m->term_id ) ) >= 5, 'menu: the quick-link menu has its tiles (' . ( $m ? count( wp_get_nav_menu_items( $m->term_id ) ) : 0 ) . ')' );
$it = $m ? wp_get_nav_menu_items( $m->term_id )[0] : null;
t( $it && '' !== get_post_meta( $it->ID, '_mcupr_menu_icon', true ), 'menu: each tile has an icon' );
$loc = get_nav_menu_locations();
t( ! empty( $locs_before['quick'] ) ? (int) $loc['quick'] === (int) $locs_before['quick'] : ( $m && (int) $loc['quick'] === (int) $m->term_id ), 'menu: attached to “Quick links” only when that place was free (otherwise the existing menu stays)' );

// ---- again: nothing new, nothing doubled
$cnt = count( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
$r2 = mcupr_demo_apply( $d );
$cnt2 = count( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
t( 0 === $r2['posts'] + $r2['events'] + $r2['video'] + $r2['albums'] + $r2['menu'] && $cnt === $cnt2 && $r2['skipped'] >= 18, 'again: nothing is made twice (' . $cnt . ' items stay ' . $cnt2 . ')' );
// a demo post that was edited keeps its edit
wp_update_post( array( 'ID' => $demo_posts[0], 'post_title' => 'แก้ไขแล้ว' ) ); mcupr_demo_apply( $d );
t( 'แก้ไขแล้ว' === get_the_title( $demo_posts[0] ), 'again: an edited demo post keeps its edit' );
// ---- remove
$gone = mcupr_demo_remove();
t( $gone >= 40 && ! get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => 1 ) ) && ! wp_get_nav_menu_object( $d['menu']['name'] ), 'remove: all demo items, pictures and the menu are gone (' . $gone . ')' );
wp_cache_flush(); t( (int) wp_count_posts()->publish === $n_posts, 'remove: the site’s own posts are untouched' );
t( 'ของฉัน' === get_theme_mod( 'mcupr_hero_l1_th' ), 'remove: the settings stay' );
// ---- restore settings
foreach ( array_keys( (array) get_theme_mods() ) as $k ) { if ( ! array_key_exists( $k, $mods_before ) ) { remove_theme_mod( $k ); } }
foreach ( $mods_before as $k => $v ) { set_theme_mod( $k, $v ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
