<?php
/** Settings import / export: whitelist, cleaning, never overwrites, redirects, round trip.   php php/settings-io.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mods_before = get_theme_mods(); $red_before = get_option( 'mcupr_redirects', null );
function wipe() { foreach ( array_keys( (array) get_theme_mods() ) as $k ) { if ( mcupr_ss_key( (string) $k ) ) { remove_theme_mod( $k ); } } delete_option( 'mcupr_redirects' ); }
wipe();

// ---- the starter file that comes with the theme
$st = mcupr_ss_starter();
t( ! empty( $st['mcupr_settings'] ) && count( $st['theme_mods'] ) > 50, 'starter file: found, marked, and has the sample settings (' . count( $st['theme_mods'] ) . ')' );
$bad = array_filter( array_keys( $st['theme_mods'] ), function ( $k ) { return ! mcupr_ss_key( $k ); } );
t( ! $bad, 'starter file: every key in it is one the importer accepts ' . implode( ',', $bad ) );

// ---- the whitelist
t( null !== mcupr_ss_key( 'mcupr_aud_3_url' ) && null !== mcupr_ss_key( 'mcupr_svc_8_icon' ) && null !== mcupr_ss_key( 'mcupr_wx_lat' ) && null !== mcupr_ss_key( 'mcupr_num_asof' ) && null !== mcupr_ss_key( 'mcupr_home_services_on' ), 'whitelist: the theme’s own block, weather, numbers and switch keys are accepted' );
t( null === mcupr_ss_key( 'mcupr_aud_4_url' ) && null === mcupr_ss_key( 'mcupr_svc_9_title' ) && null === mcupr_ss_key( 'mcupr_aud_1_evil' ) && null === mcupr_ss_key( 'blogname' ) && null === mcupr_ss_key( 'custom_css' ) && null === mcupr_ss_key( 'mcupr_logo_img' ) && null === mcupr_ss_key( 'nav_menu_locations' ), 'whitelist: item numbers past the limit, unknown fields and anything else (site name, CSS, menus, images) are refused' );

// ---- cleaning
t( 1 === mcupr_ss_clean( 'mcupr_home_links_on', 'mcupr_home_links_on', '1' ) && 0 === mcupr_ss_clean( 'mcupr_home_links_on', 'mcupr_home_links_on', 'false' ) && 0 === mcupr_ss_clean( 'mcupr_home_links_on', 'mcupr_home_links_on', 0 ), 'clean: switches become 1 or 0' );
t( '' === mcupr_ss_clean( 'mcupr_svc_1_icon', 'icon', 'no-such' ) && 'book' === mcupr_ss_clean( 'mcupr_svc_1_icon', 'icon', 'book' ), 'clean: unknown icons are dropped' );
t( '' === mcupr_ss_clean( 'mcupr_svc_1_url', 'url', 'javascript:alert(1)' ) && '' === mcupr_ss_clean( 'mcupr_svc_1_url', 'url', '//evil.test' ) && 'page:vision' === mcupr_ss_clean( 'mcupr_svc_1_url', 'url', 'page:Vision' ), 'clean: script and protocol-relative addresses dropped; page:slug kept as a slug' );
t( '12500' === mcupr_ss_clean( 'mcupr_num_students', 'students', '12,500 คน' ) && '13.69' === mcupr_ss_clean( 'mcupr_wx_lat', 'lat', '13.69' ) && '' === mcupr_ss_clean( 'mcupr_wx_lat', 'lat', '13.69; DROP' ), 'clean: numbers keep digits; coordinates must look like coordinates' );
t( false === strpos( mcupr_ss_clean( 'mcupr_aud_1_title', 'title', '<script>x</script>หัวข้อ' ), '<' ) && 300 >= mb_strlen( mcupr_ss_clean( 'mcupr_aud_1_title', 'title', str_repeat( 'ก', 999 ) ) ), 'clean: tags removed and long text cut' );

// ---- importing the starter into an empty site
$rep = mcupr_ss_apply( $st );
t( count( array_diff( array( 'aud', 'fig', 'svc', 'lnk', 'abt', 'num' ), $rep['added'] ) ) === 0 && 0 === $rep['rejected'], 'import: every group of the starter file arrived into an empty site (' . implode( ',', $rep['added'] ) . ')' );
t( '36' === get_theme_mod( 'mcupr_num_teachers' ) && '3' === get_theme_mod( 'mcupr_num_faculties' ) && 'บริการและระบบสารสนเทศ' === get_theme_mod( 'mcupr_svc_title' ), 'import: Thai text and numbers intact' );
t( 0 === strpos( (string) get_theme_mod( 'mcupr_aud_1_url' ), '/' ) || '' === (string) get_theme_mod( 'mcupr_aud_1_url' ), 'import: page:slug became the path of the page' );

// ---- importing again, and over a site that has its own content: nothing is overwritten
$before = get_theme_mods();
$rep = mcupr_ss_apply( $st );
t( ! array_diff( array( 'aud', 'fig', 'svc', 'lnk', 'abt', 'num' ), $rep['skipped'] ) && ! $rep['added'] && get_theme_mods() === $before, 'again: everything already filled in is skipped and nothing changes' );
wipe();
set_theme_mod( 'mcupr_svc_1_title', 'ของฉัน' ); set_theme_mod( 'mcupr_svc_1_url', '/mine/' ); set_theme_mod( 'mcupr_num_teachers', '5' );
$rep = mcupr_ss_apply( $st );
t( 'ของฉัน' === get_theme_mod( 'mcupr_svc_1_title' ) && '' === (string) get_theme_mod( 'mcupr_svc_2_title', '' ) && '' === (string) get_theme_mod( 'mcupr_svc_title', '' ), 'overwrite: a block with the site’s own content is left whole (not even its empty boxes or heading are filled)' );
t( '5' === get_theme_mod( 'mcupr_num_teachers' ) && '' === (string) get_theme_mod( 'mcupr_num_faculties', '' ) && in_array( 'svc', $rep['skipped'], true ) && in_array( 'num', $rep['skipped'], true ) && in_array( 'aud', $rep['added'], true ), 'overwrite: the numbers block is skipped too, while empty blocks are filled' );

// ---- switches
wipe(); set_theme_mod( 'mcupr_home_links_on', false );
mcupr_ss_apply( array( 'mcupr_settings' => 1, 'theme_mods' => array( 'mcupr_home_links_on' => 1, 'mcupr_home_services_on' => 0 ) ) );
t( false === get_theme_mod( 'mcupr_home_links_on' ) && 0 === get_theme_mod( 'mcupr_home_services_on' ), 'switches: one that is already set stays, one that is not set is set' );

// ---- missing pages: the card is left out and the rest renumbered
wipe();
mcupr_ss_apply( array( 'mcupr_settings' => 1, 'theme_mods' => array(
	'mcupr_lnk_1_icon' => 'book', 'mcupr_lnk_1_title' => 'หายไป', 'mcupr_lnk_1_url' => 'page:no-such-page-xyz',
	'mcupr_lnk_2_icon' => 'book', 'mcupr_lnk_2_title' => 'มีอยู่', 'mcupr_lnk_2_url' => '/there/',
	'mcupr_lnk_title' => 'หัวข้อ',
) ) );
t( 'มีอยู่' === get_theme_mod( 'mcupr_lnk_1_title' ) && '' === (string) get_theme_mod( 'mcupr_lnk_2_title', '' ) && 'หัวข้อ' === get_theme_mod( 'mcupr_lnk_title' ), 'pages: a card whose page does not exist is left out and the next one moves up' );
wipe();
mcupr_ss_apply( array( 'mcupr_settings' => 1, 'theme_mods' => array( 'mcupr_lnk_1_title' => 'x', 'mcupr_lnk_1_url' => 'page:no-such-page-xyz', 'mcupr_lnk_title' => 'หัวข้อเปล่า' ) ) );
t( '' === (string) get_theme_mod( 'mcupr_lnk_title', '' ), 'pages: a heading is not written when no card is left under it' );

// ---- only some groups
wipe();
$rep = mcupr_ss_apply( $st, array( 'fig' ) );
t( array( 'fig' ) === $rep['added'] && '' === (string) get_theme_mod( 'mcupr_svc_title', '' ) && '' === (string) get_theme_mod( 'mcupr_num_teachers', '' ), 'only: limited to the groups asked for' );
// the Home blocks sample button goes through the same code
wipe();
t( in_array( 'svc', mcupr_blk_install_samples(), true ) && '' === (string) get_theme_mod( 'mcupr_num_teachers', '' ), 'home blocks button: fills blocks (not the numbers) through the same importer' );

// ---- rejected entries
wipe();
$rep = mcupr_ss_apply( array( 'mcupr_settings' => 1, 'theme_mods' => array( 'blogname' => 'hacked', 'custom_css' => 'body{display:none}', 'mcupr_fig_1_num' => '5', 'mcupr_fig_1_label' => 'ok' ) ) );
t( 2 === $rep['rejected'] && 'hacked' !== get_option( 'blogname' ) && '5' === get_theme_mod( 'mcupr_fig_1_num' ), 'rejected: other settings in a file are ignored and counted, the good ones are kept' );
t( null === mcupr_ss_parse( '{"a":1}' ) && null === mcupr_ss_parse( 'not json' ) && is_array( mcupr_ss_parse( '{"mcupr_settings":1,"theme_mods":{}}' ) ), 'parse: only files marked as MCUPR settings are accepted' );

// ---- redirects
wipe();
update_option( 'mcupr_redirects', array( array( 'from' => '/old', 'to' => 'https://example.org/mine', 'code' => 302, 'hits' => 3, 'last' => 1 ) ), false );
$rep = mcupr_ss_apply( array( 'mcupr_settings' => 1, 'redirects' => array(
	array( 'from' => '/Old/', 'to' => '/other/', 'code' => 301 ),
	array( 'from' => '/new-one/', 'to' => '/target/', 'code' => 301 ),
	array( 'from' => '/', 'to' => '/x/' ),
	array( 'from' => '/bad/', 'to' => 'javascript:alert(1)' ),
) ) );
$r = mcupr_redirects();
t( 2 === count( $r ) && 'https://example.org/mine' === $r[0]['to'] && 3 === $r[0]['hits'], 'redirects: an old address that is already redirected keeps its target and its count' );
t( array( 1, 3 ) === $rep['redirects'] && '/new-one' === $r[1]['from'], 'redirects: the new one is added; the duplicate, the home page and the script address are skipped (1 added, 3 skipped)' );

// ---- export and round trip
wipe();
set_theme_mod( 'mcupr_aud_1_title', 'สมัคร' ); set_theme_mod( 'mcupr_aud_1_url', '/a/' ); set_theme_mod( 'mcupr_home_services_on', false ); set_theme_mod( 'blogdescription_x', 'nope' ); set_theme_mod( 'mcupr_logo_img', 99 );
update_option( 'mcupr_redirects', array( array( 'from' => '/x', 'to' => 'https://example.org/', 'code' => 301, 'hits' => 9, 'last' => 0 ) ), false );
$ex = mcupr_ss_export();
t( 'สมัคร' === $ex['theme_mods']['mcupr_aud_1_title'] && 0 === $ex['theme_mods']['mcupr_home_services_on'] && ! isset( $ex['theme_mods']['mcupr_logo_img'] ) && ! isset( $ex['theme_mods']['blogdescription_x'] ), 'export: only the settings of the format, switches as 0/1, nothing else' );
t( array( array( 'from' => '/x', 'to' => 'https://example.org/', 'code' => 301 ) ) === $ex['redirects'], 'export: redirects without their counters' );
$json = wp_json_encode( $ex, JSON_UNESCAPED_UNICODE );
wipe();
$rep = mcupr_ss_apply( mcupr_ss_parse( $json ) );
t( 'สมัคร' === get_theme_mod( 'mcupr_aud_1_title' ) && '/a/' === get_theme_mod( 'mcupr_aud_1_url' ) && 0 === get_theme_mod( 'mcupr_home_services_on' ) && 1 === count( mcupr_redirects() ), 'round trip: export → wipe → import gives the same settings back' );
$msg = mcupr_ss_message( $rep );
t( '' !== $msg && false !== strpos( $msg, 'Redirects' ) || false !== strpos( $msg, 'เปลี่ยนเส้นทาง' ), 'message: a readable summary line' );

// ---- restore
wipe();
foreach ( $mods_before as $k => $v ) { set_theme_mod( $k, $v ); }
if ( null !== $red_before ) { update_option( 'mcupr_redirects', $red_before, false ); }
remove_theme_mod( 'blogdescription_x' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
