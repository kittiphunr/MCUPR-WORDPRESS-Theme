<?php
/** Welcome page, maintenance mode, mourning: settings, who sees what.   php php/site-status.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/120 Safari/537.36';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
delete_option( 'mcupr_status' );
t( 0 === mcupr_status()['maint']['on'] && ! mcupr_maintenance_blocks() && ! mcupr_welcome_wanted(), 'defaults: everything off, nobody is blocked' );
t( mcupr_home_on( 'events', true ) && ! mcupr_home_on( 'cats', false ), 'home sections: defaults (events on, category blocks off)' );
// saving
wp_set_current_user( 1 );
$s = mcupr_status_save( array(
	'm_on' => '1', 'm_title' => array( 'th' => '<b>ปิดปรับปรุง</b>', 'en' => str_repeat( 'x', 300 ), 'xx' => 'ignored' ), 'm_msg' => array( 'th' => "บรรทัด 1\nบรรทัด 2<script>" ),
	'm_back' => '2030-01-02T09:30', 'm_ips' => "203.0.113.50\nnot-an-ip\n2001:db8::1\n203.0.113.50", 'm_key' => 'short',
	'w_on' => '1', 'w_preset' => 'evil', 'w_url' => 'javascript:alert(1)', 'w_from' => '2030-13-45', 'w_to' => '2030-02-01', 'w_days' => '9999', 'w_auto' => '-5',
	'n_on' => '1', 'n_from' => 'x', 'n_to' => '2030-03-03',
) );
t( 'ปิดปรับปรุง' === $s['maint']['title']['th'] && 120 === mb_strlen( $s['maint']['title']['en'] ) && ! isset( $s['maint']['title']['xx'] ), 'save: titles are cleaned, cut at 120, only known languages' );
t( false === strpos( $s['maint']['msg']['th'], '<' ) && false !== strpos( $s['maint']['msg']['th'], "\n" ), 'save: the message keeps its line breaks, loses tags' );
t( "203.0.113.50\n2001:db8::1" === $s['maint']['ips'], 'save: only real, unique IP addresses are kept' );
t( strlen( $s['maint']['key'] ) >= 12 && 'short' !== $s['maint']['key'], 'save: a too-short reviewer key is replaced by a strong one' );
t( '2030-01-02T09:30' === $s['maint']['back'] && mcupr_maintenance_end_ts( $s['maint'] ) > 0, 'save: the expected-back time is kept and understood' );
t( 'custom' === $s['welcome']['preset'] && '' === $s['welcome']['url'] && '' === $s['welcome']['from'] && '2030-02-01' === $s['welcome']['to'], 'save: unknown preset, javascript: address and a bad date are dropped' );
t( 365 === $s['welcome']['days'] && 0 === $s['welcome']['auto'], 'save: days and countdown are clamped' );
t( '' === $s['mourning']['from'] && '2030-03-03' === $s['mourning']['to'], 'save: mourning dates' );
$k1 = $s['maint']['key'];
$s = mcupr_status_save( array( 'm_on' => '1', 'm_key' => $k1, 'm_newkey' => '1' ) );
t( $s['maint']['key'] !== $k1, 'save: "make a new link" changes the key' );
// maintenance: who is blocked
$key = $s['maint']['key'];
mcupr_status_save( array( 'm_on' => '1', 'm_key' => $key, 'm_ips' => '203.0.113.50' ) );
wp_set_current_user( 0 );
t( mcupr_maintenance_blocks(), 'maintenance: a visitor is blocked' );
wp_set_current_user( 1 );
t( ! mcupr_maintenance_blocks(), 'maintenance: an editor is not' );
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
t( ! mcupr_maintenance_blocks(), 'maintenance: an allowed IP address is not' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$_COOKIE['mcupr_maint_ok'] = 'wrong';
t( mcupr_maintenance_blocks(), 'maintenance: a wrong reviewer cookie does not help' );
$_COOKIE['mcupr_maint_ok'] = mcupr_maint_cookie_value( mcupr_status()['maint'] );
t( ! mcupr_maintenance_blocks(), 'maintenance: the right reviewer cookie lets in' );
unset( $_COOKIE['mcupr_maint_ok'] );
$r = apply_filters( 'rest_authentication_errors', null );
t( is_wp_error( $r ) && 503 === $r->get_error_data()['status'], 'maintenance: the REST API is closed to the public' );
mcupr_status_save( array( 'm_key' => $key ) ); // off
t( ! mcupr_maintenance_blocks() && ! is_wp_error( apply_filters( 'rest_authentication_errors', null ) ), 'maintenance off: open again' );
// welcome
wp_set_current_user( 0 );
$today = current_time( 'Y-m-d' );
mcupr_status_save( array( 'w_on' => '1', 'w_days' => '1', 'w_from' => gmdate( 'Y-m-d', strtotime( $today ) + 86400 * 2 ) ) );
t( ! mcupr_welcome_wanted(), 'welcome: before its first day it is not shown' );
mcupr_status_save( array( 'w_on' => '1', 'w_days' => '1', 'w_from' => gmdate( 'Y-m-d', strtotime( $today ) - 86400 ), 'w_to' => gmdate( 'Y-m-d', strtotime( $today ) + 86400 ) ) );
t( mcupr_welcome_wanted(), 'welcome: inside its dates it is shown' );
$_COOKIE['mcupr_welcome'] = mcupr_welcome_version();
t( ! mcupr_welcome_wanted(), 'welcome: not again once the visitor has entered (cookie)' );
mcupr_status_save( array( 'w_on' => '1', 'w_days' => '1', 'w_title' => array( 'th' => 'ใหม่' ), 'w_from' => gmdate( 'Y-m-d', strtotime( $today ) - 86400 ), 'w_to' => gmdate( 'Y-m-d', strtotime( $today ) + 86400 ) ) );
t( mcupr_welcome_wanted(), 'welcome: changing the page shows it again to everyone' );
unset( $_COOKIE['mcupr_welcome'] );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
t( ! mcupr_welcome_wanted(), 'welcome: search robots never get it' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120';
wp_set_current_user( 1 );
t( ! mcupr_welcome_wanted(), 'welcome: editors browse without it' );
$_GET['mcupr_welcome'] = 'preview';
t( mcupr_welcome_wanted(), 'welcome: …unless they ask for the preview' );
unset( $_GET['mcupr_welcome'] );
wp_set_current_user( 0 );
mcupr_status_save( array( 'w_days' => '1' ) ); // off
t( ! mcupr_welcome_wanted(), 'welcome off: not shown' );
// presets
$pre = mcupr_welcome_presets();
t( count( $pre ) >= 6 && isset( $pre['royal'], $pre['mourning'], $pre['songkran'] ), 'presets: royal, mourning, Songkran… exist' );
foreach ( $pre as $k => $p ) {
	$l = function ( $hex ) { $c = array_map( 'hexdec', str_split( ltrim( $hex, '#' ), 2 ) ); foreach ( $c as &$v ) { $v /= 255; $v = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ); } return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; };
	t( 1.05 / ( $l( $p[1] ) + 0.05 ) >= 7 && 1.05 / ( $l( $p[2] ) + 0.05 ) >= 4.5, 'presets: white text is readable on the colours of "' . $k . '"' );
}
// samples (open to everyone, for the starter file's demo page)
wp_set_current_user( 0 );
$_GET['mcupr_welcome'] = 'sample'; $_GET['occasion'] = 'songkran';
t( mcupr_welcome_wanted(), 'sample: a known occasion shows its sample to anyone' );
$_GET['occasion'] = 'nonsense';
t( ! mcupr_welcome_wanted(), 'sample: an unknown occasion shows nothing' );
unset( $_GET['mcupr_welcome'], $_GET['occasion'] );
t( substr_count( do_shortcode( '[mcupr_welcome_samples]' ), 'mcupr_welcome=sample' ) === count( $pre ) - 1, 'sample: [mcupr_welcome_samples] links every occasion except "my own"' );
// mourning
mcupr_status_save( array( 'n_on' => '1', 'n_from' => gmdate( 'Y-m-d', strtotime( $today ) - 86400 ), 'n_to' => gmdate( 'Y-m-d', strtotime( $today ) + 86400 ) ) );
t( false !== strpos( apply_filters( 'language_attributes', 'lang="th"' ), 'data-mourning="1"' ), 'mourning: the page is marked inside the dates' );
mcupr_status_save( array( 'n_on' => '1', 'n_to' => gmdate( 'Y-m-d', strtotime( $today ) - 86400 ) ) );
t( false === strpos( apply_filters( 'language_attributes', 'lang="th"' ), 'data-mourning' ), 'mourning: not after its last day' );
delete_option( 'mcupr_status' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
