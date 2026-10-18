<?php
/** The control center (MCUPR › Control center): the home, a page for each group, the start checklist, the guides, the data behind them – and what each kind of person sees.   php php/admin-overview.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
define( 'WP_ADMIN', true );
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$page = function ( $get = array() ) { $_GET = $get + array( 'page' => 'mcupr' ); ob_start(); mcupr_admin_overview(); $h = ob_get_clean(); $_GET = array(); return preg_replace( '#<style>.*?</style>#s', '', $h ); }; // without the page's own css text
$keep = get_option( 'mcupr_status', null );
$set  = function ( $m, $w, $n ) { $s = mcupr_status(); $s['maint']['on'] = $m; $s['welcome']['on'] = $w; $s['welcome']['from'] = ''; $s['welcome']['to'] = ''; $s['mourning']['on'] = $n; $s['mourning']['from'] = ''; $s['mourning']['to'] = ''; update_option( 'mcupr_status', $s ); };
mcupr_od_remove();
mcupr_od_apply( mcupr_od_data() );
$offices = mcupr_offices();
$data    = mcupr_cc_data();

// ---- the data itself
t( 8 === count( $data['cats'] ) && array( 'start', 'content', 'look', 'visitors', 'students', 'people', 'system', 'help' ) === array_keys( $data['cats'] ), 'eight groups, in this order: start, content, look, visitors, students, people, system, help' );
$set_icons = mcupr_feather_set();
$bad = array(); $nolink = array();
foreach ( $data['cats'] as $k => $c ) { if ( ! isset( $set_icons[ $c['icon'] ] ) ) { $bad[] = 'cat ' . $k; } }
foreach ( $data['items'] as $id => $it ) {
	foreach ( array( 'cat', 'icon', 'title', 'open', 'desc', 'what', 'steps' ) as $f ) { if ( empty( $it[ $f ] ) ) { $bad[] = $id . ':' . $f; } }
	if ( ! isset( $data['cats'][ $it['cat'] ] ) ) { $bad[] = $id . ':cat?'; }
	if ( ! isset( $set_icons[ $it['icon'] ] ) ) { $bad[] = $id . ':icon ' . $it['icon']; }
	foreach ( array_merge( array( $it['open'] ), ! empty( $it['add'] ) ? array( $it['add'] ) : array(), array_map( function ( $l ) { return $l[1]; }, isset( $it['links'] ) ? $it['links'] : array() ), isset( $it['screens'] ) ? $it['screens'] : array() ) as $sp ) { if ( ! preg_match( '/^(type|new|page|cust|adm|url):/', $sp ) ) { $nolink[] = $id . ':' . $sp; } }
	if ( ! empty( $it['guide'] ) && ! isset( $data['guides'][ $it['guide'] ] ) ) { $bad[] = $id . ':guide?'; }
}
foreach ( $data['guides'] as $gid => $g ) { if ( ! isset( $set_icons[ $g['icon'] ] ) || count( $g['steps'] ) < 3 || empty( $g['time'] ) ) { $bad[] = 'guide ' . $gid; } foreach ( $g['steps'] as $st ) { if ( ! empty( $st[2] ) && ! preg_match( '/^(type|new|page|cust|adm|url):/', $st[2] ) ) { $nolink[] = $gid . ':' . $st[2]; } } }
foreach ( $data['checklist'] as $st ) { if ( ! isset( $set_icons[ $st['icon'] ] ) || ! preg_match( '/^(type|new|page|cust|adm|url):/', $st['link'] ) ) { $bad[] = 'step ' . $st['id']; } }
t( ! $bad, 'the data is complete: every menu has a group, an icon from the Feather set, a sentence, what it is and steps' . ( $bad ? ' (' . implode( ', ', $bad ) . ')' : '' ) );
t( ! $nolink, 'every link in the data is of a known kind' . ( $nolink ? ' (' . implode( ', ', $nolink ) . ')' : '' ) );
t( count( $data['items'] ) >= 40 && count( $data['guides'] ) >= 12 && 10 === count( $data['checklist'] ), 'the content: ' . count( $data['items'] ) . ' menus, ' . count( $data['guides'] ) . ' guides, 10 steps for a new campus' );
$fe = mcupr_feather( 'edit-3', 20 );
t( false !== strpos( $fe, '<svg' ) && false !== strpos( $fe, 'aria-hidden="true"' ) && false !== strpos( $fe, 'width="20"' ) && false !== strpos( mcupr_feather( 'no-such-icon' ), '<circle' ) && is_file( MCUPR_DIR . '/inc/feather-LICENSE.txt' ), 'Feather icons: inline svg, hidden from screen readers, an unknown name gives a circle, the MIT licence is kept' );
t( count( $set_icons ) >= 280, 'the whole Feather set is there (' . count( $set_icons ) . ' icons)' );
t( ! preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', file_get_contents( MCUPR_DIR . '/inc/control-center-data.php' ) . file_get_contents( MCUPR_DIR . '/inc/control-center.php' ) ), 'no emoji anywhere in the control center (every computer draws them differently)' );

// ---- the links
t( admin_url( 'edit.php?post_type=mcupr_event' ) === mcupr_cc_url( 'type:mcupr_event' ) && admin_url( 'post-new.php' ) === mcupr_cc_url( 'new:post' ) && admin_url( 'post-new.php?post_type=page' ) === mcupr_cc_url( 'new:page' ) && admin_url( 'admin.php?page=mcupr-ics' ) === mcupr_cc_url( 'page:mcupr-ics' ) && false !== strpos( mcupr_cc_url( 'cust:mcupr_home' ), 'autofocus[section]=mcupr_home' ) && false === strpos( mcupr_cc_url( 'cust:' ), 'autofocus' ), 'links: list, new, page, customizer (with or without a section)' );

wp_set_current_user( 1 );
$set( 0, 0, 0 );
$h = $page();
t( false !== strpos( $h, 'class="mcc-hero"' ) && false !== strpos( $h, 'v' . MCUPR_VERSION ) && false !== strpos( $h, 'id="mcc-q"' ) && false !== strpos( $h, 'id="mcc-index"' ), 'home: the title with the version, a search box and its index' );
$idx = json_decode( preg_replace( '#.*<script type="application/json" id="mcc-index">(.*?)</script>.*#s', '$1', $h ), true );
t( is_array( $idx ) && count( $idx ) >= count( $data['guides'] ) + 40 && 5 === count( $idx[0] ), 'search: the index lists every menu and every guide (' . ( is_array( $idx ) ? count( $idx ) : 0 ) . ' entries)' );
t( 8 === substr_count( $h, 'class="mcc-card"' ), 'home: a card for each of the eight groups (' . substr_count( $h, 'class="mcc-card"' ) . ')' );
t( 3 === preg_match_all( '/class="mcc-chip" data-mode="(maint|welcome|mourning)"/', $h ) && ! preg_match( '/is-on" data-mode="(maint|welcome|mourning)"/', $h ), 'status: three modes, all off' );
$set( 1, 0, 1 );
$h = $page();
t( 2 === preg_match_all( '/class="mcc-chip is-on" data-mode="(maint|mourning)"/', $h ) && false !== strpos( $h, 'class="mcc-chip" data-mode="welcome"' ), 'status: maintenance and mourning are lit, welcome is not' );
t( substr_count( $h, 'class="mcc-act"' ) >= 5 && false !== strpos( $h, 'autofocus%5Bsection%5D=mcupr_home' ), 'done most often: the shortcuts (' . substr_count( $h, 'class="mcc-act"' ) . '), the home page one opens the Customizer on its section' );
t( false === strpos( $h, 'dashicons' ), 'no Dashicons: every picture is a Feather icon' );

// ---- a group, the checklist, the guides
foreach ( array_keys( $data['cats'] ) as $k ) {
	$g = $page( array( 'cat' => $k ) );
	t( false !== strpos( $g, 'class="mcc-crumbs"' ) && false !== strpos( $g, 'class="mcc-side"' ) && false !== strpos( $g, '<h1>' . esc_html( $data['cats'][ $k ]['title'] ) . '</h1>' ), 'group "' . $k . '": breadcrumbs, the list of groups and its title' );
}
$g = $page( array( 'cat' => 'content' ) );
t( substr_count( $g, 'class="mcc-row"' ) >= 9 && substr_count( $g, '<details class="mcc-how">' ) >= 9 && false !== strpos( $g, 'post_type=mcupr_event' ) && false !== strpos( $g, 'class="mcc-n"' ), 'content group: a row for each menu with its count, a "how do I use it" and buttons (' . substr_count( $g, 'class="mcc-row"' ) . ' rows)' );
t( false !== strpos( $g, esc_html( $data['items']['events']['what'] ) ) && false !== strpos( $g, esc_html( $data['items']['events']['steps'][0] ) ) && false !== strpos( $g, 'page=mcupr&#038;cat=help&#038;guide=add-event' ), 'a row explains what the menu is, the steps, and links the guide' );
$st = $page( array( 'cat' => 'start' ) );
t( 10 === preg_match_all( '/<li class="mcc-step( is-done)?">/', $st ) && false !== strpos( $st, 'role="progressbar"' ), 'start: ten steps and a progress bar' );
delete_option( 'mcupr_cc_done' );
$done0 = count( array_filter( wp_list_pluck( mcupr_cc_checklist(), 'done' ) ) );
update_option( 'mcupr_cc_done', array( 'home' => 1, 'org' => 1 ) );
$l = mcupr_cc_checklist(); $by = array(); foreach ( $l as $x ) { $by[ $x['id'] ] = $x; }
t( $by['home']['done'] && ! $by['home']['auto'] && $by['org']['done'] && count( array_filter( wp_list_pluck( $l, 'done' ) ) ) === $done0 + 2, 'start: a step can be marked as done by hand' );
$fakenews = null;
t( isset( $by['news'] ) && is_bool( $by['news']['auto'] ) && ( (int) wp_count_posts( 'post' )->publish >= 1 ) === $by['news']['done'], 'start: "first news" is checked by itself from the posts' );
delete_option( 'mcupr_cc_done' );
$gh = $page( array( 'cat' => 'help' ) );
t( substr_count( $gh, 'class="mcc-guides"' ) === 1 && substr_count( $gh, 'guide=' ) >= count( $data['guides'] ), 'help: every guide is listed' );
$gd = $page( array( 'cat' => 'help', 'guide' => 'add-news' ) );
t( false !== strpos( $gd, esc_html( $data['guides']['add-news']['title'] ) ) && substr_count( $gd, 'class="mcc-step"' ) === count( $data['guides']['add-news']['steps'] ) && false !== strpos( $gd, 'post-new.php' ), 'a guide: its title, every step and a button that goes to the right place' );
t( false !== strpos( $page( array( 'cat' => 'nonsense' ) ), 'class="mcc-hero"' ), 'an unknown group opens the home' );

// ---- the menu is complete: everything the menu holds is reachable from the home or a group
global $submenu;
$GLOBALS['menu'] = $GLOBALS['submenu'] = array();
do_action( 'admin_menu' );
$all = $page(); foreach ( array_keys( mcupr_cc_cats() ) as $k ) { $all .= $page( array( 'cat' => $k ) ); }
$miss = array();
foreach ( isset( $submenu['mcupr'] ) ? $submenu['mcupr'] : array() as $it ) {
	$slug = $it[2];
	if ( 'mcupr' === $slug || false !== strpos( $slug, 'post-new.php' ) || 0 === strpos( $slug, 'admin.php?page=mcupr&cat=' ) ) { continue; }
	$url = false !== strpos( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
	if ( false === strpos( $all, esc_url( $url ) ) && false === strpos( $all, str_replace( '&', '&#038;', $url ) ) && false === strpos( $all, $url ) ) { $miss[] = $slug; }
}
t( ! $miss && count( $submenu['mcupr'] ) > 20, 'the control center reaches every entry of the MCUPR menu (' . count( $submenu['mcupr'] ) . ' entries; missing: ' . implode( ', ', $miss ) . ')' );

// ---- the bar on the theme's own screens and the help dialog
global $pagenow;
$_GET = array(); $pagenow = 'edit.php'; $_GET['post_type'] = 'mcupr_event';
set_current_screen( 'edit-mcupr_event' );
t( 'events' === mcupr_cc_item_for_screen(), 'screen: the events list belongs to the "events" menu' );
ob_start(); do_action( 'in_admin_header' ); $bar = ob_get_clean();
t( false !== strpos( $bar, 'class="mcc-crumbs"' ) && false !== strpos( $bar, 'data-mcc-help' ) && false !== strpos( $bar, '<dialog' ) && false !== strpos( $bar, esc_html( $data['items']['events']['what'] ) ), 'screen: breadcrumbs, a button "About this page" and a dialog with the explanation' );
$_GET = array( 'page' => 'mcupr-speed' ); $pagenow = 'admin.php'; set_current_screen( 'mcupr_page_mcupr-speed' );
t( 'speed' === mcupr_cc_item_for_screen(), 'screen: a page of the theme is found by its slug' );
$_GET = array(); $pagenow = 'edit.php'; set_current_screen( 'edit-post' );
t( 'posts' === mcupr_cc_item_for_screen(), 'screen: the list of news is "news", not another list' );
$_GET = array( 'post_type' => 'page' ); set_current_screen( 'edit-page' );
t( 'pages' === mcupr_cc_item_for_screen(), 'screen: the list of pages is "pages"' );
$pagenow = 'index.php'; $_GET = array(); set_current_screen( 'dashboard' );
t( '' === mcupr_cc_item_for_screen(), 'screen: the dashboard belongs to no menu' );

// ---- a person who works in one office
$ua = username_exists( 'ov_office_editor' ) ?: wp_insert_user( array( 'user_login' => 'ov_office_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'ov@example.org', 'role' => MCUPR_OFFICE_ROLE ) );
update_user_meta( $ua, 'mcupr_offices', array( $offices[0]->ID ) );
wp_set_current_user( $ua );
$h = $page();
t( false !== strpos( $h, 'mcc-mine' ) && false !== strpos( $h, esc_html( get_the_title( $offices[0] ) ) ), 'an office editor sees their own office' );
t( false === strpos( $h, 'mcupr-settings-io' ) && false === strpos( $h, 'mcupr-speed' ) && false === strpos( $h, 'data-mode="maint"' ) && false === strpos( $h, 'autofocus%5Bsection%5D' ), 'an office editor gets none of the tools, no maintenance chip and no Customizer shortcut' );
t( ! isset( mcupr_cc_cats()['start'] ) && isset( mcupr_cc_cats()['help'] ) && ! isset( mcupr_cc_cats()['system'] ), 'an office editor has no start checklist and no system group, but has help' );
t( substr_count( $h, 'class="mcc-act"' ) >= 2 && null === ( mcupr_cc_items( 'content' )['events']['count'] ), 'an office editor has the writing shortcuts and no counts (they would count what the editor may not see)' );
wp_set_current_user( 0 );
t( '' === $page(), 'a visitor gets nothing' );
wp_set_current_user( 1 );
$_POST = array( 'action' => 'mcupr_cc_done', 'step' => 'home', 'done' => '1' ); // without the nonce nothing is kept (the handler stops the request)
$ed = wp_insert_user( array( 'user_login' => 'ov_ed' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'ov_ed' . wp_rand() . '@example.org', 'role' => 'editor' ) );
wp_set_current_user( $ed );
t( isset( mcupr_cc_cats()['content'] ) && ! isset( mcupr_cc_cats()['system'] ) && ! isset( mcupr_cc_cats()['start'] ) && ! isset( mcupr_cc_items( 'look' )['display'] ), 'an editor has the content but not the system group, the checklist or the theme options' );
$_POST = array();
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ua ); wp_delete_user( $ed );
mcupr_od_remove();
if ( null === $keep ) { delete_option( 'mcupr_status' ); } else { update_option( 'mcupr_status', $keep ); }
wp_set_current_user( 0 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
