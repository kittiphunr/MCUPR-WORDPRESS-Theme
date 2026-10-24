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

// ---- the tree, descriptions, folders and the path bar
$ac  = get_term_by( 'slug', 'academic', 'category' );
$res = get_term_by( 'slug', 'research', 'category' );
wp_update_term( $res->term_id, 'category', array( 'description' => 'ผลงานวิจัยของอาจารย์และนิสิต' ) );
$idx = mcupr_browser_index();
t( isset( $idx[0] ) && isset( $idx[ $ac->term_id ] ) && wp_list_pluck( $idx[ $ac->term_id ], 'slug' ) === array( 'research', 'service' ), 'tree: categories are indexed by their parent' );
$tr = mcupr_browser_tree( 0, 0, array() );
t( substr_count( $tr, 'data-fb-node' ) === 1 && substr_count( $tr, 'aria-expanded="false"' ) === 1 && substr_count( $tr, 'class="fb-subwrap" hidden' ) === 1, 'tree: a branch with children has a button and starts closed when the current category is elsewhere' );
$tr = mcupr_browser_tree( 0, (int) $res->term_id, array( (int) $ac->term_id, (int) $res->term_id ) );
t( substr_count( $tr, 'aria-expanded="true"' ) === 1 && strpos( $tr, 'class="fb-subwrap" hidden' ) === false && substr_count( $tr, 'is-cur' ) === 1, 'tree: the branch holding the current category is open and the category is marked once' );
t( preg_match( '/<span class="fb-n">4<\/span>/', $tr ) === 1, 'tree: a parent counts the posts of its children' );
t( strpos( $tr, 'fb-lv1' ) !== false, 'tree: the level below is marked as a level for indenting' );
wp_update_term( $ac->term_id, 'category', array( 'description' => "<b>งาน</b>\n   ของคณะ <script>x</script>" ) );
$ac = get_term( $ac->term_id, 'category' );
t( 'งาน ของคณะ x' === mcupr_browser_desc( $ac ) || 'งาน ของคณะ' === mcupr_browser_desc( $ac ), 'description: tags and extra spaces are removed (' . mcupr_browser_desc( $ac ) . ')' );
$long = (object) array( 'description' => str_repeat( 'ก', 300 ) );
t( mb_strlen( mcupr_browser_desc( $long, 140 ) ) === 140 && mb_substr( mcupr_browser_desc( $long, 140 ), -1 ) === '…', 'description: a long one is cut with an ellipsis' );
global $wp_query;
$wp_query = new WP_Query( array( 'cat' => $ac->term_id ) );
$GLOBALS['wp_the_query'] = $wp_query;
$f = mcupr_browser_folders();
t( substr_count( $f, 'class="fb-fd fb-d0"' ) === 2 && strpos( $f, 'fb-fh' ) !== false, 'folders: inside a category its sub-categories are the folders' );
t( preg_match( '/fb-fn">งานวิจัย<.*?fb-fm">2 รายการ|fb-fn">งานวิจัย<.*?fb-fm">[^<]*2/su', $f ) === 1, 'folders: each shows its name and item count' );
$fh = mcupr_browser_folders();
t( preg_match( '/fb-fd-t">ผลงานวิจัย[^<]*</u', $fh ) === 1, 'folders: the category description is shown in its folder' );
$tb = mcupr_browser_toolbar();
t( strpos( $tb, 'class="fb-up"' ) !== false && strpos( $tb, 'fb-name">วิชาการ<' ) !== false && strpos( $tb, '2 โฟลเดอร์' ) !== false, 'toolbar: up button, the name of the place and its folder count' );
$pb = mcupr_browser_path();
t( substr_count( $pb, '<li' ) === 2 && strpos( $pb, 'aria-current="page"' ) !== false, 'path bar: all > the category, the last step is the current one' );
$GLOBALS['wp_query'] = $wp_query = new WP_Query( array( 'cat' => $res->term_id ) );
$pb = mcupr_browser_path();
t( substr_count( $pb, '<li' ) === 3 && strpos( $pb, '/category/academic' ) !== false && mcupr_browser_folders() === '', 'path bar: two levels down has three steps with the parent linked; a category without sub-categories has no folders' );
$GLOBALS['wp_query'] = $wp_query = new WP_Query( array( 'post_type' => 'post' ) );
$wp_query->is_home = true;
$GLOBALS['wp_the_query'] = $wp_query;
$fl = mcupr_browser_folders();
t( substr_count( $fl, 'class="fb-fd fb-d0"' ) === count( mcupr_browser_children( 0 ) ) && substr_count( $fl, 'class="fb-fk"' ) === 1 && substr_count( $fl, 'fb-fd fb-d1' ) === 2, 'folders: on the main page the top-level categories are the folders, the next level hangs under its parent' );
t( strpos( mcupr_browser_toolbar(), 'class="fb-up is-off"' ) !== false && substr_count( mcupr_browser_path(), '<li' ) === 1, 'main page: nothing above it (up button off), the path is just "all"' );
wp_update_term( $ac->term_id, 'category', array( 'description' => '' ) );
wp_update_term( $res->term_id, 'category', array( 'description' => '' ) );

// ---- counts match the list: posts kept off the main site are not promised
wp_set_current_user( 0 );
$published = (int) wp_count_posts()->publish;
$unc       = get_term_by( 'slug', 'uncategorized', 'category' );
t( ! mcupr_browser_filtered() && mcupr_browser_all_total() === $published && mcupr_browser_term_count( $unc ) === (int) $unc->count, 'counts: with nothing left out the stored counts are used' );
$ids = get_posts( array( 'numberposts' => 2, 'fields' => 'ids', 'category' => $unc->term_id, 'suppress_filters' => true ) );
$ot  = wp_insert_term( 'office-count-test', MCUPR_OFFICE_TAX );
$otid = is_wp_error( $ot ) ? (int) get_term_by( 'slug', 'office-count-test', MCUPR_OFFICE_TAX )->term_id : (int) $ot['term_id'];
foreach ( $ids as $pid ) { wp_set_object_terms( $pid, array( $otid ), MCUPR_OFFICE_TAX ); }
set_transient( 'mcupr_office_hidden', array( $otid ), HOUR_IN_SECONDS ); // where the theme keeps it (inc/offices.php)
unset( $GLOBALS['mcupr_office_hidden'] );
$hidden = count( $ids );
t( mcupr_browser_filtered() && mcupr_browser_all_total() === $published - $hidden, 'counts: "All" leaves out the posts of an office kept off the main site (' . mcupr_browser_all_total() . ' of ' . $published . ')' );
t( mcupr_browser_term_count( $unc ) === (int) $unc->count - $hidden, 'counts: a category counts only what its list shows (' . mcupr_browser_term_count( $unc ) . ')' );
$listed = new WP_Query( array( 'posts_per_page' => 1, 'no_found_rows' => false ) );
t( (int) $listed->found_posts === mcupr_browser_all_total(), 'counts: "All" equals the number of posts the list itself finds' );
$tr = mcupr_browser_tree( 0, 0, array() );
t( preg_match( '/Uncategorized<\/span><span class="fb-n">' . ( (int) $unc->count - $hidden ) . '</', $tr ) === 1 || preg_match( '/fb-n">' . ( (int) $unc->count - $hidden ) . '<\/span>/', $tr ) === 1, 'counts: the tree shows the visible count too' );
foreach ( $ids as $pid ) { wp_set_object_terms( $pid, array(), MCUPR_OFFICE_TAX ); }
wp_delete_term( $otid, MCUPR_OFFICE_TAX );
mcupr_office_hidden_reset();
t( ! mcupr_browser_filtered() && mcupr_browser_all_total() === $published, 'counts: back to the stored counts once nothing is left out' );

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
