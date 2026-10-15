<?php
/** Home blocks: parsing, safety, the five blocks, the Customizer fields, weather (with a faked Open-Meteo), samples.   php php/blocks.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mods_before = get_theme_mods();

// ---- parsing
$r = mcupr_blk_parse( "edit | A | B\n\n  \nbook|C", 2 );
t( 2 === count( $r ) && 'A' === $r[0][1], 'parse: blank lines skipped, fields trimmed' );
t( 3 === count( mcupr_blk_parse( 'a|b;c|d;e|f', 2, true ) ) && 1 === count( mcupr_blk_parse( 'a|b;c|d', 2, false ) ), 'parse: a shortcode splits items on ;, a widget on lines' );
t( 1 === count( mcupr_blk_parse( "x\ny|z", 2 ) ), 'parse: lines with too few fields are dropped' );
t( 24 === count( mcupr_blk_parse( implode( "\n", array_fill( 0, 40, 'a|b' ) ), 2 ) ), 'parse: at most 24 items' );
// ---- addresses
t( home_url( '/x/' ) === mcupr_blk_url( '/x/' ) && '' === mcupr_blk_url( 'javascript:alert(1)' ) && '' === mcupr_blk_url( '//evil.test/x' ) && 'https://a.test/' === mcupr_blk_url( 'https://a.test/' ), 'url: paths made absolute; script and protocol-relative addresses refused' );
t( mcupr_blk_is_external( 'https://elsewhere.test/' ) && ! mcupr_blk_is_external( home_url( '/p/' ) ), 'url: outside addresses recognised' );
t( 'link' === mcupr_blk_icon( 'no-such-icon' ) && 'book' === mcupr_blk_icon( 'book' ) && 'laptop' === mcupr_blk_icon( 'laptop' ), 'icon: unknown names fall back' );
// ---- audience
$h = mcupr_blk_audience( mcupr_blk_parse( "edit | ผู้สนใจ | <b>สมัคร</b> | อธิบาย | /a/\nuser | นิสิต | พอร์ทัล | | https://x.test/\nbook | e | f", 3 ) );
t( 1 === substr_count( $h, 'is-lead' ) && 3 === substr_count( $h, 'aud-card' ) && false === strpos( $h, '<b>สมัคร' ), 'audience: first card leads, text escaped' );
t( false !== strpos( $h, 'target="_blank"' ) && 1 === substr_count( $h, 'target="_blank"' ) && false !== strpos( $h, '<div class="aud-card"' ), 'audience: only the outside link opens a tab; a card without address is not a link' );
t( 0 === substr_count( mcupr_blk_audience( mcupr_blk_parse( 'edit|a|b', 3 ), false ), 'is-lead' ), 'audience: the lead can be switched off' );
// ---- figures
$h = mcupr_blk_figures( mcupr_blk_parse( "12,500 | นิสิต\n{news} | ข่าว\n{nothing} | x\n{staff} | คน", 2 ) );
t( 3 === substr_count( $h, 'class="fig"' ) && false !== strpos( $h, '12,500' ), 'figures: a typed number and self-counting ones; an unknown token is left out' );
$c = wp_count_posts( 'post' );
t( false !== strpos( $h, '<b>' . number_format_i18n( (int) $c->publish ) . '</b>' ), 'figures: {news} is the number of published posts' );
t( '' === mcupr_blk_figures( array() ), 'figures: nothing, nothing shown' );
// ---- cards
$h = mcupr_blk_cards( mcupr_blk_parse( "book | ทะเบียน | /reg/\nlaptop | e-Learning | https://el.test/\nmail | ไม่มีที่อยู่ |", 3 ) );
t( 2 === substr_count( $h, 'class="lc"' ) && 1 === substr_count( $h, 'lc-x' ) && 1 === substr_count( $h, 'rel="noopener"' ), 'cards: a card without address is dropped; the outside one says it opens a new tab' );
t( false === strpos( mcupr_blk_cards( mcupr_blk_parse( 'book|x|https://el.test/', 3 ), 'links' ), 'lc-x' ) && false !== strpos( mcupr_blk_cards( mcupr_blk_parse( 'book|x|https://el.test/', 3 ), 'links' ), 'sr-only' ), 'cards: compact style hides the note but keeps it for screen readers' );
// ---- about
$h = mcupr_blk_about( array( 'title' => 'T<i>', 'text' => "a\nb", 'button' => 'Go', 'url' => '/v/', 'id' => 'wid-t' ), mcupr_blk_parse( "flag | V | D | /v/\ngrid | S", 2 ) );
t( false !== strpos( $h, 'id="wid-t"' ) && false === strpos( $h, '<i>' ) && false !== strpos( $h, '<br' ) && 2 === substr_count( $h, 'class="ab-card"' ) && false !== strpos( $h, 'class="btn"' ), 'about: title, text with line break, button and cards; escaped' );
t( '' === mcupr_blk_about( array( 'title' => '', 'text' => '', 'button' => '', 'url' => '' ), array() ), 'about: empty = nothing' );
// ---- shortcodes
t( 2 === substr_count( do_shortcode( '[mcupr_figures items="1|a;2|b"]' ), 'class="fig"' ), 'shortcode: [mcupr_figures]' );
t( false !== strpos( do_shortcode( '[mcupr_audience items="edit|a|b|c|/x/;user|d|e"]' ), 'aud-card' ), 'shortcode: [mcupr_audience]' );
t( false !== strpos( do_shortcode( '[mcupr_cards title="T" items="book|a|/x/" more="All" more_url="/y/"]' ), 'blk-more' ), 'shortcode: [mcupr_cards] with heading and see-all link' );
t( false !== strpos( do_shortcode( '[mcupr_about title="T"]Hello[/mcupr_about]' ), 'Hello' ), 'shortcode: [mcupr_about] takes the text between the tags' );
// ---- weather with a fake Open-Meteo
$calls = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$calls ) {
	if ( false === strpos( $url, 'api.open-meteo.com' ) ) { return $pre; }
	$calls++;
	return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => array(), 'body' => wp_json_encode( array(
		'current' => array( 'temperature_2m' => 31.4, 'relative_humidity_2m' => 89, 'wind_speed_10m' => 0.2, 'weather_code' => 3 ),
		'daily'   => array( 'time' => array( '2026-10-15', '2026-10-16' ), 'weather_code' => array( 3, 95 ), 'temperature_2m_max' => array( 32.2, 32.4 ), 'temperature_2m_min' => array( 25, 24.5 ), 'precipitation_sum' => array( 4.7, 10.9 ) ),
	) ), 'cookies' => array(), 'filename' => null );
}, 10, 3 );
delete_transient( 'mcupr_wx_' . md5( '13.69,101.076' ) );
$h = mcupr_blk_weather( '13.69', '101.076', 'ฉะเชิงเทรา' );
t( false !== strpos( $h, 'ฉะเชิงเทรา' ) && false !== strpos( $h, '89%' ) && false !== strpos( $h, 'Open-Meteo' ) && false !== strpos( $h, 'windy.com/13.69/101.076' ), 'weather: place, humidity, source and the Windy link' );
t( false !== strpos( $h, 'พายุฝนฟ้าคะนอง' ) && false !== strpos( $h, 'data-wx-pause' ) && false !== strpos( $h, 'aria-hidden="true"><ul' ), 'weather: tomorrow in words; a pause button; the copy of the text for the loop is hidden from screen readers' );
mcupr_blk_weather( '13.69', '101.076' );
t( 1 === $calls, 'weather: the answer is cached (one request for two pages)' );
t( '' === mcupr_blk_weather( '', '' ), 'weather: without a place nothing is shown and nothing is asked' );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { return false !== strpos( $url, 'api.open-meteo.com' ) ? new WP_Error( 'x', 'down' ) : $pre; }, 5, 3 );
remove_all_filters( 'pre_http_request', 10 );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { return false !== strpos( $url, 'api.open-meteo.com' ) ? new WP_Error( 'x', 'down' ) : $pre; }, 10, 3 );
t( '' === mcupr_blk_weather( '1.5', '2.5' ), 'weather: when Open-Meteo is down the bar is simply not shown' );
remove_all_filters( 'pre_http_request' );
// ---- the Customizer: what is typed becomes the block
$keys = array(); foreach ( mcupr_blk_defs() as $k => $d ) { for ( $i = 1; $i <= $d[0]; $i++ ) { foreach ( array_keys( $d[1] ) as $f ) { $keys[] = "mcupr_{$k}_{$i}_{$f}"; } } }
foreach ( $keys as $k ) { remove_theme_mod( $k ); }
t( 5 === count( mcupr_blk_defs() ) && 3 === mcupr_blk_defs()['aud'][0] && 8 === mcupr_blk_defs()['svc'][0], 'customizer: five blocks with the number of items the Customizer offers' );
t( array() === mcupr_blk_rows( 'aud' ) && '' === mcupr_blk_home( 'audience' ) && '' === mcupr_blk_section( 'audience', 'audience' ), 'customizer: nothing typed = nothing shown' );
set_theme_mod( 'mcupr_aud_1_title', 'สมัคร' ); set_theme_mod( 'mcupr_aud_1_icon', 'edit' ); set_theme_mod( 'mcupr_aud_1_url', '/a/' );
set_theme_mod( 'mcupr_aud_2_eyebrow', 'no title: left out' );
set_theme_mod( 'mcupr_aud_3_title', 'หลักสูตร' );
$rows = mcupr_blk_rows( 'aud' );
t( 2 === count( $rows ) && 'edit' === $rows[0][0] && 'สมัคร' === $rows[0][2], 'customizer: an item without its main field is skipped; fields come in the order the block expects' );
$h = mcupr_blk_home( 'audience' );
t( 2 === substr_count( $h, 'class="aud-card' ) + substr_count( $h, 'class="aud-card is-lead"' ) - substr_count( $h, 'class="aud-card is-lead"' ) && 1 === substr_count( $h, 'is-lead' ), 'customizer: the audience block shows the two cards, the first leading' );
$sec = mcupr_blk_section( 'audience', 'audience' );
t( 0 === strpos( $sec, '<section' ) && false !== strpos( $sec, 'blk-audience' ), 'section: wrapped as a home page section' );
set_theme_mod( 'mcupr_home_audience_on', false );
t( '' === mcupr_blk_section( 'audience', 'audience' ), 'section: the show/hide box switches it off without losing what was typed' );
remove_theme_mod( 'mcupr_home_audience_on' );
set_theme_mod( 'mcupr_fig_1_num', '{news}' ); set_theme_mod( 'mcupr_fig_1_label', 'ข่าว' ); set_theme_mod( 'mcupr_fig_2_num', '1,250' ); set_theme_mod( 'mcupr_fig_2_label', 'บุคลากร' );
t( 2 === substr_count( mcupr_blk_home( 'figures' ), 'class="fig"' ), 'customizer: figures from two filled boxes' );
set_theme_mod( 'mcupr_svc_1_title', 'ทะเบียน' ); set_theme_mod( 'mcupr_svc_1_url', '/reg/' ); set_theme_mod( 'mcupr_svc_title', 'บริการ' ); set_theme_mod( 'mcupr_svc_more', 'ทั้งหมด' ); set_theme_mod( 'mcupr_svc_more_url', '/all/' );
$h = mcupr_blk_home( 'services' );
t( false !== strpos( $h, 'lcs-service' ) && false !== strpos( $h, 'blk-svc-t' ) && false !== strpos( $h, 'blk-more' ), 'customizer: service cards with heading and "see all"' );
set_theme_mod( 'mcupr_lnk_1_title', 'หลักสูตร' ); set_theme_mod( 'mcupr_lnk_1_url', '/p/' );
t( false !== strpos( mcupr_blk_home( 'links' ), 'lcs-links' ), 'customizer: compact links' );
set_theme_mod( 'mcupr_abt_title', 'เกี่ยวกับ' ); set_theme_mod( 'mcupr_abt_1_title', 'วิสัยทัศน์' );
$h = mcupr_blk_home( 'about' );
t( false !== strpos( $h, 'id="blk-abt-t"' ) && 1 === substr_count( $h, 'class="ab-card"' ), 'customizer: about block with its card' );
t( false !== strpos( mcupr_blk_section( 'about', 'aboutblock' ), 'aria-labelledby="blk-abt-t"' ), 'section: labelled by its heading' );
// the url setting
t( '' === mcupr_blk_clean_url_setting( '//evil.test/x' ) && '' === mcupr_blk_clean_url_setting( 'javascript:alert(1)' ) && false === strpos( mcupr_blk_clean_url_setting( '/thai/หน้า/<script>' ), '<' ) && 0 === strpos( mcupr_blk_clean_url_setting( '/thai/หน้า/<script>' ), '/thai/หน้า/' ) && 'https://a.test/x' === mcupr_blk_clean_url_setting( ' https://a.test/x ' ) && '' === mcupr_blk_clean_url_setting( '' ), 'url setting: paths and https kept; script, protocol-relative and tags removed' );
// the Customizer really offers them
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
global $wp_customize; $wp_customize = new WP_Customize_Manager(); do_action( 'customize_register', $wp_customize );
t( null !== $wp_customize->get_panel( 'mcupr_home_panel' ) && 'mcupr_home_panel' === $wp_customize->get_section( 'mcupr_home_sections' )->panel && 'mcupr_home_panel' === $wp_customize->get_section( 'mcupr_numbers' )->panel, 'customizer: one panel “Home page sections” holds the show/hide section, the numbers and the blocks' );
$in_panel = 0; foreach ( $wp_customize->sections() as $sid => $sc ) { if ( 'mcupr_home_panel' === $sc->panel ) { $in_panel++; } }
t( $in_panel >= 8, "customizer: the panel has its sections ($in_panel)" );
t( null !== $wp_customize->get_control( 'mcupr_aud_3_url' ) && null !== $wp_customize->get_control( 'mcupr_svc_8_title' ) && null !== $wp_customize->get_control( 'mcupr_abt_4_desc' ) && null !== $wp_customize->get_control( 'mcupr_wx_lat' ) && null !== $wp_customize->get_control( 'mcupr_home_services_on' ), 'customizer: the boxes and the show/hide checkbox exist' );
$st = $wp_customize->get_setting( 'mcupr_aud_1_icon' ); t( 'edit' === $st->sanitize( 'edit' ) && '' === $st->sanitize( 'no-such-icon' ), 'customizer: the icon setting accepts only known icons' );
$wl = $wp_customize->get_setting( 'mcupr_fig_1_label' ); t( 'x' === $wl->sanitize( '<b>x</b>' ), 'customizer: text boxes are cleaned' );
t( ! isset( $GLOBALS['wp_widget_factory']->widgets['MCUPR_Widget_Audience'] ) && ! is_registered_sidebar( 'home-top' ) && ! is_registered_sidebar( 'home-mid' ), 'no widgets and no extra widget areas any more' );
// ---- samples: only empty blocks, only pages that exist
foreach ( $keys as $k ) { remove_theme_mod( $k ); }
foreach ( array( 'mcupr_svc_title', 'mcupr_svc_sub', 'mcupr_svc_more', 'mcupr_svc_more_url', 'mcupr_lnk_title', 'mcupr_lnk_more', 'mcupr_lnk_more_url', 'mcupr_abt_title', 'mcupr_abt_text', 'mcupr_abt_button', 'mcupr_abt_url' ) as $k ) { remove_theme_mod( $k ); }
set_theme_mod( 'mcupr_fig_1_num', '77' ); set_theme_mod( 'mcupr_fig_1_label', 'ของฉัน' ); // the figures block is "in use"
$done = mcupr_blk_install_samples();
t( ! in_array( 'fig', $done, true ) && '77' === get_theme_mod( 'mcupr_fig_1_num' ) && 'ของฉัน' === get_theme_mod( 'mcupr_fig_1_label' ) && '' === (string) get_theme_mod( 'mcupr_fig_2_num', '' ), 'samples: a block that already has content is not touched, not even its empty boxes' );
t( in_array( 'svc', $done, true ) && 'บริการและระบบสารสนเทศ' === get_theme_mod( 'mcupr_svc_title' ) && 'ระบบทะเบียน' === get_theme_mod( 'mcupr_svc_1_title' ), 'samples: an empty block gets its heading and items (Thai text intact)' );
$n_aud = count( mcupr_blk_rows( 'aud' ) );
t( $n_aud >= 1 && 0 === strpos( get_theme_mod( 'mcupr_aud_1_url' ), '/' ), 'samples: addresses are the paths of the pages that exist (' . $n_aud . ' audience cards)' );
t( array() === mcupr_blk_install_samples(), 'samples: pressing the button again changes nothing' );
set_theme_mod( 'mcupr_svc_1_title', 'แก้ไขแล้ว' ); mcupr_blk_install_samples();
t( 'แก้ไขแล้ว' === get_theme_mod( 'mcupr_svc_1_title' ), 'samples: an edited item stays as it is' );
// ---- restore
foreach ( get_theme_mods() as $k => $v ) { if ( ! array_key_exists( $k, $mods_before ) ) { remove_theme_mod( $k ); } }
foreach ( $mods_before as $k => $v ) { set_theme_mod( $k, $v ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
