<?php
/** Home blocks: parsing, safety, the five blocks, widgets, weather (with a faked Open-Meteo), samples.   php php/blocks.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep_sb = wp_get_sidebars_widgets();

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
// ---- widgets
foreach ( array( 'MCUPR_Widget_Audience', 'MCUPR_Widget_Figures', 'MCUPR_Widget_Cards', 'MCUPR_Widget_About', 'MCUPR_Widget_Weather' ) as $cls ) { t( class_exists( $cls ) && isset( $GLOBALS['wp_widget_factory']->widgets[ $cls ] ), "widget: $cls is registered" ); }
$w = new MCUPR_Widget_Cards();
$u = $w->update( array( 'title' => '<b>T</b>', 'style' => 'evil', 'items' => "a|b|/c/\n<script>", 'more' => 'x', 'more_url' => '/y/', 'sub' => '' ), array() );
t( 'service' === $u['style'] && false === strpos( $u['title'], '<' ) && false === strpos( $u['items'], '<script>' ), 'widget: saved values are cleaned (a bad style falls back)' );
ob_start(); $w->widget( array( 'widget_id' => 'mcupr_cards-9', 'before_widget' => '', 'after_widget' => '' ), array( 'title' => 'บริการ', 'style' => 'service', 'items' => 'book | ทะเบียน | /reg/' ) ); $o = ob_get_clean();
t( false !== strpos( $o, 'aria-labelledby="mcupr_cards-9-t"' ), 'widget: the section is labelled by its heading' );
ob_start(); $w->widget( array( 'widget_id' => 'mcupr_cards-9' ), array( 'items' => '' ) ); t( '' === ob_get_clean(), 'widget: nothing when there is nothing to show' );
ob_start(); $w->form( array() ); t( false !== strpos( ob_get_clean(), '<textarea' ), 'widget: the form has the items box' );
// ---- samples: only empty areas, only pages that exist
$sb = $keep_sb; unset( $sb['home-top'], $sb['home-mid'] ); $sb['home-mid'] = array(); $sb['home-top'] = array( 'mcupr_figures-77' );
wp_set_sidebars_widgets( $sb );
$opt_fig = get_option( 'widget_mcupr_figures' );
$n = mcupr_blk_install_samples();
$after = wp_get_sidebars_widgets();
t( array( 'mcupr_figures-77' ) === $after['home-top'], 'samples: an area that already has a widget is not touched' );
t( 3 === $n && 3 === count( $after['home-mid'] ), 'samples: the empty area got its three blocks (' . $n . ')' );
t( get_option( 'widget_mcupr_figures' ) === $opt_fig, 'samples: the existing widget settings are untouched' );
$before_n = count( get_option( 'widget_mcupr_cards', array() ) );
t( 0 === mcupr_blk_install_samples() && count( get_option( 'widget_mcupr_cards', array() ) ) === $before_n, 'samples: pressing the button again adds nothing' );
$cards_opt = get_option( 'widget_mcupr_cards' ); $mid = $after['home-mid'];
$txt = wp_json_encode( $cards_opt, JSON_UNESCAPED_UNICODE );
t( false !== strpos( $txt, 'บริการและระบบสารสนเทศ' ), 'samples: Thai texts saved intact' );
// restore
wp_set_sidebars_widgets( $keep_sb );
foreach ( $mid as $id ) { list( $base, $num ) = explode( '-', $id ); $o2 = get_option( 'widget_' . $base ); unset( $o2[ (int) $num ] ); update_option( 'widget_' . $base, $o2 ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
