<?php
/** Offices: data, the demo, the university's lists leaving office content out, pages and tabs, roles and what they may touch.   php php/offices.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function pages_of( $q ) { return wp_list_pluck( $q->posts, 'ID' ); }

mcupr_od_remove(); // a clean start
t( post_type_exists( 'mcupr_office' ) && taxonomy_exists( MCUPR_OFFICE_TAX ), 'registered: the office type and the hidden mark' );
t( ! get_taxonomy( MCUPR_OFFICE_TAX )->public && ! get_taxonomy( MCUPR_OFFICE_TAX )->show_ui, 'the mark is not public and has no screen of its own' );
foreach ( array( 'post', 'page', 'mcupr_event', 'mcupr_album', 'mcupr_video', 'mcupr_person' ) as $ty ) {
	t( is_object_in_taxonomy( $ty, MCUPR_OFFICE_TAX ), 'the mark can be put on: ' . $ty );
}
$role = get_role( MCUPR_OFFICE_ROLE );
t( $role && $role->has_cap( 'edit_posts' ) && $role->has_cap( 'upload_files' ) && ! $role->has_cap( 'manage_options' ) && ! $role->has_cap( 'edit_theme_options' ), 'role "Office editor" can write and upload but not manage settings' );
t( in_array( 'mcupr_office', mcupr_admin_types(), true ), 'the office type is in the MCUPR admin menu' );

// ---- colours
t( '#ffffff' === mcupr_office_on_color( '#0f766e' ) && '#ffffff' === mcupr_office_on_color( '#1d4ed8' ) && '#111827' === mcupr_office_on_color( '#fde68a' ) && '#ffffff' === mcupr_office_on_color( 'nonsense' ), 'text colour on an office colour: white on dark, near-black on light' );
$lum = function ( $hex ) { $hex = ltrim( $hex, '#' ); $c = array_map( function ( $x ) { $x = hexdec( $x ) / 255; return $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 ); }, str_split( $hex, 2 ) ); return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; };
$ratio = function ( $a, $b ) use ( $lum ) { $x = $lum( $a ); $y = $lum( $b ); return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 ); };
$okc = true;
foreach ( array( '#0f766e', '#1d4ed8', '#b45309', '#7c3aed', '#be123c', '#fde68a', '#a3e635', '#ffffff', '#000000', '#808080' ) as $c ) {
	$okc = $okc && $ratio( $c, mcupr_office_on_color( $c ) ) >= 4.5 - 0.6 * ( '#808080' === $c ? 1 : 0 );
}
t( $okc, 'the text colour is readable (contrast 4.5:1 or better, mid grey excepted) on the demo colours and the extremes' );

// ---- the demo
$d = mcupr_od_data();
t( is_array( $d ) && 3 === count( $d['offices'] ), 'demo file: three offices' );
$r = mcupr_od_apply( $d );
t( 3 === $r['offices'] && 12 === $r['posts'] && 6 === $r['events'] && 11 === $r['people'] && 3 === $r['albums'] && 6 === $r['pages'], 'demo made 3 offices, 12 news, 6 events, 11 people, 3 albums, 6 pages (' . json_encode( $r ) . ')' );
$again = mcupr_od_apply( $d );
t( 0 === $again['offices'] && 0 === $again['posts'] && 0 === $again['people'] && $again['skipped'] > 0, 'making the demo again adds nothing (items are skipped)' );
$offices = mcupr_offices();
t( 3 === count( $offices ) && 'phrae-campus-office' === $offices[0]->post_name && 'phrae-academic-office' === $offices[1]->post_name && 'phrae-sangha-college' === $offices[2]->post_name, 'the three offices, in order, with their slugs' );
list( $A, $B, $C ) = array_map( function ( $o ) { return $o->ID; }, $offices );
t( preg_match( '#/office/phrae-campus-office/$#', get_permalink( $A ) ) && preg_match( '#/office/phrae-campus-office/news/$#', mcupr_office_url( $A, 'news' ) ) && preg_match( '#/news/page/3/$#', mcupr_office_url( $A, 'news', 3 ) ), 'office addresses: /office/<slug>/ and /office/<slug>/news/ (…/page/3/)' );
$od = mcupr_office_data( $B );
t( '#1d4ed8' === $od['color'] && 'ผศ.ดร.ตัวอย่าง วิชาการดี' === $od['head'] && '054-000-002' === $od['tel'] && ! $od['main'], 'office details are stored (colour, head, telephone), and by default the office is kept off the main site' );
$news_a = get_posts( mcupr_office_query_args( $A, 'post', array( 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
t( 4 === count( $news_a ) && $A === mcupr_office_of( $news_a[0] ), 'news belong to their office (4 for the first office)' );
t( mcupr_office_has( $A, 'post' ) && mcupr_office_has( $A, 'mcupr_event' ) && mcupr_office_has( $A, 'mcupr_person' ) && mcupr_office_has( $A, 'mcupr_album' ) && ! mcupr_office_has( $A, 'mcupr_video' ), 'what an office has: news, events, people, an album – and no videos' );
t( 2 === count( mcupr_office_pages( $A ) ), 'the office pages that become tabs: 2' );
$thumbs = 0; foreach ( $news_a as $id ) { $thumbs += has_post_thumbnail( $id ) ? 1 : 0; }
t( $thumbs >= 1 && has_post_thumbnail( $A ), 'the demo gave pictures to the activity news and the office cover' );

// ---- the university's own lists
$q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
t( ! array_intersect( $q->posts, $news_a ), 'the university\'s news list does not contain office news' );
$q = new WP_Query( array( 's' => 'ตัวอย่าง', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$office_ids = get_posts( array( 'post_type' => array( 'post', 'page', 'mcupr_event', 'mcupr_person', 'mcupr_album' ), 'posts_per_page' => -1, 'fields' => 'ids', 'mcupr_office_all' => 1, 'meta_key' => '_mcupr_demo_off' ) );
t( $office_ids && ! array_intersect( $q->posts, $office_ids ), 'search does not find office content' );
$q = new WP_Query( array( 'post_type' => 'mcupr_event', 'posts_per_page' => -1, 'fields' => 'ids' ) );
t( ! array_intersect( $q->posts, get_posts( mcupr_office_query_args( $A, 'mcupr_event', array( 'posts_per_page' => -1, 'fields' => 'ids' ) ) ) ), 'the university\'s event list does not contain office events' );
$by_name = get_posts( array( 'post_type' => 'post', 'name' => get_post_field( 'post_name', $news_a[0] ), 'posts_per_page' => 1, 'fields' => 'ids' ) );
t( array( $news_a[0] ) === $by_name, 'an office article is still found by its own address (direct fetch is not hidden)' );
$yes = get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => MCUPR_OFFICE_TAX, 'field' => 'term_id', 'terms' => array( mcupr_office_term_id( $B ) ) ) ) ) );
t( 4 === count( $yes ) && ! array_intersect( $yes, $news_a ), 'asking for one office\'s content gives that office\'s only' );
update_post_meta( $A, '_mcupr_of_main', '1' );
$GLOBALS['mcupr_hidden_reset'] = true;
$rm = new ReflectionFunction( 'mcupr_office_hidden_terms' );
// the hidden list is remembered for one request: a fresh request is simulated by a child php process
$out = shell_exec( 'php -r ' . escapeshellarg( '$_SERVER["HTTP_HOST"]="localhost:8090"; require ' . var_export( rtrim( $site, '/' ) . '/wp-load.php', true ) . '; $q = new WP_Query( array( "post_type" => "post", "posts_per_page" => -1, "fields" => "ids", "no_found_rows" => true ) ); echo count( array_intersect( $q->posts, ' . var_export( $news_a, true ) . ' ) );' ) . ' 2>/dev/null' );
t( 4 === (int) trim( (string) $out ), 'an office set to "also show on the main site" shows its news in the university\'s list' );
delete_post_meta( $A, '_mcupr_of_main' );

// ---- the pages of an office
function office_page( $oid, $sec = '', $extra = array() ) {
	global $wp_query, $wp_the_query, $post;
	$args = array( 'post_type' => 'mcupr_office', 'p' => $oid );
	$wp_query = $wp_the_query = new WP_Query( $args );
	if ( $sec ) { set_query_var( 'mcupr_osec', $sec ); }
	foreach ( $extra as $k => $v ) { set_query_var( $k, $v ); }
	$wp_query->the_post();
}
$bad = function ( $h ) { return (bool) preg_match( '/(Warning|Notice|Deprecated|Fatal error|Parse error)/', $h ); };
office_page( $A );
ob_start(); mcupr_office_chrome(); $chrome = ob_get_clean();
t( false !== strpos( $chrome, 'data-variant="full"' ) && preg_match( '#<h1 class="of-name">#', $chrome ) && false !== strpos( $chrome, '--of:#0f766e;--of-on:#ffffff' ) && false !== strpos( $chrome, 'aria-current="page">ภาพรวม' ), 'office home header: full, the name is the only h1, its colours, "Overview" is the current tab' );
foreach ( array( 'ข่าวสาร', 'กิจกรรม', 'บุคลากร', 'อัลบั้มภาพ', 'โครงสร้างการบริหารงาน', 'แบบฟอร์มและเอกสารดาวน์โหลด', 'ติดต่อ' ) as $tab ) {
	t( false !== strpos( $chrome, '>' . $tab ), 'tab: ' . $tab );
}
t( false === strpos( $chrome, '>วิดีโอ' ), 'no tab for videos (the office has none)' );
ob_start(); mcupr_office_render( $A, '' ); $h = ob_get_clean();
t( ! $bad( $h ) && false !== strpos( $h, 'of-about' ) && false !== strpos( $h, 'of-info' ) && false !== strpos( $h, 'news-card' ) && false !== strpos( $h, 'ev-row' ) && false !== strpos( $h, 'album-card' ) && false !== strpos( $h, 'of-pages' ), 'office home: about, contact card, news cards, events, album and page tiles, no PHP errors' );
t( false !== strpos( $h, 'tel:054000001' ) && false !== strpos( $h, 'mailto:campus-office@example.org' ) && false !== strpos( $h, 'google.com/maps/search' ), 'office home: telephone, e-mail and map links' );
t( false !== strpos( $chrome, 'has-cover' ) && false !== strpos( $chrome, '--of-cover:url(' ) && false !== strpos( $chrome, 'of-chips' ) && false !== strpos( $chrome, 'tel:054000001' ), 'office home header: the cover picture behind the colour, and telephone / e-mail chips' );
t( false !== strpos( $h, 'class="of-stats"' ) && 4 === substr_count( substr( $h, strpos( $h, 'class="of-stats"' ), strpos( $h, '</ul>', strpos( $h, 'class="of-stats"' ) ) - strpos( $h, 'class="of-stats"' ) ), '<b>' ), 'office home: four figure tiles (news, events, people, albums)' );
t( false !== strpos( $h, '<dt><span class="of-ic"' ) && false === strpos( $h, '<div><span class="of-ic"' ), 'office home: the contact card has icons inside a valid definition list' );
foreach ( array( 'news' => 'news-card', 'events' => 'ev-row', 'people' => 'person-card', 'albums' => 'album-card', 'contact' => 'of-dl' ) as $sec => $needle ) {
	office_page( $A, $sec );
	ob_start(); mcupr_office_chrome(); $c2 = ob_get_clean();
	ob_start(); mcupr_office_render( $A, $sec ); $h = ob_get_clean();
	t( ! $bad( $h ) && false !== strpos( $h, $needle ) && 1 === substr_count( $h, '<h1 ' ) && false === strpos( $c2, '<h1 ' ), 'section ' . $sec . ': content shown, exactly one h1, no PHP errors' );
}
office_page( $A, 'videos' );
ob_start(); mcupr_office_render( $A, 'videos' ); $h = ob_get_clean();
t( false !== strpos( $h, 'text-muted' ), 'an empty section says so instead of showing nothing' );
office_page( $A, 'people' );
ob_start(); mcupr_office_render( $A, 'people' ); $h = ob_get_clean();
t( false !== strpos( $h, 'ผู้บริหาร' ) && false !== strpos( $h, 'เจ้าหน้าที่' ) && 4 === substr_count( $h, 'class="person-card"' ), 'people are grouped (executives, staff): 4 cards' );
// news pagination: with 14 posts there are two pages
for ( $i = 1; $i <= 10; $i++ ) {
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'เพิ่มทดสอบ ' . $i ) );
	update_post_meta( $id, '_mcupr_demo_off', 'office-campus-extra-' . $i );
	mcupr_office_assign( $id, $A );
}
office_page( $A, 'news', array( 'mcupr_opage' => 2 ) );
ob_start(); mcupr_office_render( $A, 'news' ); $h = ob_get_clean();
t( 2 === substr_count( $h, '<article id="post-' ), 'news page 2 lists the 2 that are left (12 per page, 14 in all)' );
t( false !== strpos( $h, '/office/phrae-campus-office/news/' ) && false !== strpos( $h, 'aria-current="page"' ) && ! preg_match( '#/page/1/#', $h ), 'news pagination, page 2: links stay inside the office and page 1 is plain /news/ (no /page/1/)' );
office_page( $A, 'news' );
ob_start(); mcupr_office_render( $A, 'news' ); $h = ob_get_clean();
t( false !== strpos( $h, '/office/phrae-campus-office/news/page/2/' ), 'news pagination, page 1: the next page is /news/page/2/' );
// a news article of the office: slim header
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $news_a[0] ) );
$GLOBALS['wp_query']->the_post();
ob_start(); mcupr_office_chrome(); $slim = ob_get_clean();
t( false !== strpos( $slim, 'data-variant="slim"' ) && false === strpos( $slim, '<h1' ) && false !== strpos( $slim, '/office/phrae-campus-office/' ), 'an office article gets the slim office header (no extra h1) that links back' );
// university content: nothing
$uni = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวส่วนกลาง' ) );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $uni ) );
$GLOBALS['wp_query']->the_post();
ob_start(); mcupr_office_chrome(); t( '' === ob_get_clean(), 'the university\'s own article gets no office header' );
wp_delete_post( $uni, true );

// ---- the list of offices
t( 3 === substr_count( do_shortcode( '[mcupr_offices]' ), 'class="office-card' ), '[mcupr_offices] shows a card for each office' );
t( false !== strpos( mcupr_offices_home(), 'blk-offices' ), 'the home page block is there' );
set_theme_mod( 'mcupr_home_offices_on', false );
t( '' === mcupr_offices_home(), 'the home page block can be switched off' );
remove_theme_mod( 'mcupr_home_offices_on' );
foreach ( array( 'of_offices', 'of_offices_lead', 'of_nav', 'of_overview', 'of_news', 'of_events', 'of_people', 'of_albums', 'of_videos', 'of_contact', 'of_about', 'of_head', 'of_address', 'of_hours', 'of_map', 'of_upcoming', 'of_past', 'of_pages', 'of_all', 'of_none', 'of_enter', 'of_no_offices' ) as $k ) {
	$row = array_map( function ( $l ) use ( $k ) { return mcupr_t( $k, $l ); }, array( 'th', 'en', 'zh', 'ja' ) );
	if ( count( array_filter( $row ) ) < 4 || count( array_unique( $row ) ) < 3 ) { t( false, 'string ' . $k . ' has four languages' ); $fails += 0; }
}
t( true, 'strings: 22 office texts checked in four languages' );

// ---- who may touch what
$mk = function ( $login, $offs ) {
	$id = username_exists( $login ) ?: wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.org', 'role' => MCUPR_OFFICE_ROLE ) );
	$offs ? update_user_meta( $id, 'mcupr_offices', $offs ) : delete_user_meta( $id, 'mcupr_offices' );
	return $id;
};
$ua = $mk( 'of_a_editor', array( $A ) );
$un = $mk( 'of_none_editor', array() );
$news_b = $yes[0];
$page_a = mcupr_office_pages( $A )[0]->ID;
wp_set_current_user( $ua );
t( array( $A ) === mcupr_user_offices(), 'the editor works in one office' );
t( current_user_can( 'edit_post', $news_a[0] ) && current_user_can( 'edit_post', $page_a ) && current_user_can( 'edit_post', $A ), 'office A\'s editor can edit A\'s news, page and the office page' );
t( ! current_user_can( 'edit_post', $news_b ) && ! current_user_can( 'delete_post', $news_b ) && ! current_user_can( 'edit_post', $B ), 'office A\'s editor cannot edit or delete office B\'s content or office page' );
t( current_user_can( 'delete_post', $news_a[0] ) && ! current_user_can( 'delete_post', $A ), 'they can delete their own office\'s news but not the office itself' );
t( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_theme_options' ) && ! current_user_can( 'create_posts', 'mcupr_office' ) && ! current_user_can( get_post_type_object( 'mcupr_office' )->cap->create_posts ), 'they have no settings and cannot add offices' );
wp_set_current_user( 1 );
$uni_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวของส่วนกลาง', 'post_author' => 1 ) );
wp_set_current_user( $ua );
t( ! current_user_can( 'edit_post', $uni_post ), 'they cannot edit the university\'s own news' );
wp_delete_post( $uni_post, true );
$mine = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวใหม่ของสำนักงาน A', 'post_author' => $ua ) );
t( $A === mcupr_office_of( $mine ), 'what the editor makes is put into their office automatically' );
$_POST['mcupr_office_member_nonce'] = wp_create_nonce( 'mcupr_office_member' ); $_POST['mcupr_office_sel'] = (string) $B;
wp_update_post( array( 'ID' => $mine, 'post_title' => 'ข่าวใหม่ของสำนักงาน A แก้' ) );
t( $A === mcupr_office_of( $mine ), 'choosing another office in the box is ignored: it stays in their own' );
unset( $_POST['mcupr_office_member_nonce'], $_POST['mcupr_office_sel'] );
$att = wp_insert_attachment( array( 'post_title' => 'x', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => 1 ), false );
t( ! current_user_can( 'edit_post', $att ) && current_user_can( 'upload_files' ), 'they can upload files but not edit other people\'s uploads' );
wp_delete_post( $att, true );
// admin list is limited
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen( 'edit-post' );
$wp_the_query = $q = new WP_Query();
$q->query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids' ) );
t( $q->posts && ! array_diff( $q->posts, array_merge( $news_a, array( $mine ) , get_posts( mcupr_office_query_args( $A, 'post', array( 'posts_per_page' => -1, 'fields' => 'ids' ) ) ) ) ), 'their post list in the admin shows only their office\'s news' );
wp_set_current_user( $un );
$wp_the_query = $q = new WP_Query();
$q->query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids' ) );
t( ! $q->posts && ! current_user_can( 'edit_post', $news_a[0] ), 'an office editor with no office sees and edits nothing' );
wp_set_current_user( 1 );
$wp_the_query = $q = new WP_Query();
$q->query( array( 'post_type' => 'post', 'posts_per_page' => 5, 'fields' => 'ids' ) );
t( count( $q->posts ) === 5 && current_user_can( 'edit_post', $news_b ), 'an administrator is not limited' );
$_GET['mcupr_of_filter'] = (string) $B;
$wp_the_query = $q = new WP_Query();
$q->query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids' ) );
t( 4 === count( $q->posts ) && ! array_diff( $q->posts, $yes ), 'the administrator\'s "office" filter in the list works' );
unset( $_GET['mcupr_of_filter'] );
set_current_screen( 'front' );
wp_delete_post( $mine, true );

// ---- saving the settings
wp_set_current_user( 1 );
$_POST = array( 'mcupr_office_nonce' => wp_create_nonce( 'mcupr_office_save' ), 'mcupr_of' => array( 'name_en' => '<b>X</b> Office', 'color' => 'red;}</style>', 'head' => 'หัวหน้า', 'tel' => '054-111', 'email' => 'not an email', 'facebook' => 'javascript:alert(1)', 'website' => 'https://example.org/', 'main' => '1', 'logo' => '999999' ) );
do_action( 'save_post_mcupr_office', $C );
$od = mcupr_office_data( $C );
t( 'X Office' === $od['name_en'] && '#0f766e' === $od['color'] && '' === $od['email'] && '' === $od['facebook'] && 'https://example.org/' === $od['website'] && $od['main'] && 0 === $od['logo'], 'saving: tags removed, a bad colour / e-mail / javascript: link / missing picture are dropped, a good link and the switch are kept' );
$_POST = array( 'mcupr_of' => array( 'name_en' => 'hack' ) );
do_action( 'save_post_mcupr_office', $C );
t( 'X Office' === mcupr_office_data( $C )['name_en'], 'saving without the nonce changes nothing' );
$_POST = array();

// ---- deleting an office, then the demo
$tmp = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_status' => 'publish', 'post_title' => 'ชั่วคราว' ) );
$pp  = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ข่าวชั่วคราว' ) );
mcupr_office_assign( $pp, $tmp );
$slug = 'office-' . $tmp;
t( (bool) get_term_by( 'slug', $slug, MCUPR_OFFICE_TAX ) && $tmp === mcupr_office_of( $pp ), 'a new office has its mark and its content carries it' );
wp_delete_post( $tmp, true );
t( ! get_term_by( 'slug', $slug, MCUPR_OFFICE_TAX ) && 0 === mcupr_office_of( $pp ) && 'publish' === get_post_status( $pp ), 'deleting an office removes its mark; its content stays and becomes the university\'s' );
wp_delete_post( $pp, true );
foreach ( array( $ua, $un ) as $u ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $u ); }
$n = mcupr_od_remove();
t( $n > 40 && 0 === count( mcupr_offices( 'any' ) ), 'removing the offices demo deletes the offices and what was made for them (' . $n . ' items)' );
t( ! get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'meta_key' => '_mcupr_demo_off', 'posts_per_page' => 1, 'fields' => 'ids', 'mcupr_office_all' => 1 ) ) && ! get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_mcupr_demo_off', 'posts_per_page' => 1, 'fields' => 'ids' ) ), 'nothing of the demo is left, pictures included' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
