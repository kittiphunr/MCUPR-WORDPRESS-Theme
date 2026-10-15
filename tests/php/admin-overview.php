<?php
/** The control page (MCUPR → Overview): banner, status chips, quick actions, sections, tools – and what a person who works in one office sees.   php php/admin-overview.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$page = function () { ob_start(); mcupr_admin_overview(); return preg_replace( '#<style>.*?</style>#s', '', ob_get_clean() ); }; // without the page's own css text
$keep = get_option( 'mcupr_status', null );
$set  = function ( $m, $w, $n ) { $s = mcupr_status(); $s['maint']['on'] = $m; $s['welcome']['on'] = $w; $s['welcome']['from'] = ''; $s['welcome']['to'] = ''; $s['mourning']['on'] = $n; $s['mourning']['from'] = ''; $s['mourning']['to'] = ''; update_option( 'mcupr_status', $s ); };
mcupr_od_remove();
mcupr_od_apply( mcupr_od_data() );
$offices = mcupr_offices();

wp_set_current_user( 1 );
$set( 0, 0, 0 );
$h = $page();
t( false !== strpos( $h, 'class="ov-hero"' ) && false !== strpos( $h, 'v' . MCUPR_VERSION ) && false !== strpos( $h, home_url( '/' ) ), 'banner: the theme name, the version and a link to the website' );
t( 3 === preg_match_all( '/class="ov-chip" data-mode="(maint|welcome|mourning)"/', $h ) && ! preg_match( '/is-on" data-mode="(maint|welcome|mourning)"/', $h ), 'status: three modes, all off' );
$set( 1, 0, 1 );
$h = $page();
t( 2 === preg_match_all( '/class="ov-chip is-on" data-mode="(maint|mourning)"/', $h ) && false !== strpos( $h, 'class="ov-chip" data-mode="welcome"' ), 'status: maintenance and mourning are lit, welcome is not (the colours of the Info button)' );
t( substr_count( $h, 'class="ov-act"' ) >= 5, 'quick actions: an administrator gets them all (' . substr_count( $h, 'class="ov-act"' ) . ')' );
t( false !== strpos( $h, 'autofocus%5Bsection%5D=mcupr_home' ), 'quick action: edit the home page opens the Customizer on the home section' );
t( substr_count( $h, 'class="ov-card"' ) >= 18 && false !== strpos( $h, 'ov-add' ) && false !== strpos( $h, 'ov-n' ), 'cards: every section with its count and an "add" button (' . substr_count( $h, 'class="ov-card"' ) . ')' );
foreach ( array( 'mcupr-blocks', 'mcupr-status', 'mcupr-settings-io', 'mcupr-speed', 'mcupr-images', 'mcupr-insights', 'customize.php' ) as $tool ) {
	t( false !== strpos( $h, $tool ), 'tools: ' . $tool );
}
t( false !== strpos( $h, 'post_type=mcupr_office' ), 'the offices card is there' );

// a person who works in one office
$ua = username_exists( 'ov_office_editor' ) ?: wp_insert_user( array( 'user_login' => 'ov_office_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'ov@example.org', 'role' => MCUPR_OFFICE_ROLE ) );
update_user_meta( $ua, 'mcupr_offices', array( $offices[0]->ID ) );
wp_set_current_user( $ua );
$h = $page();
t( false !== strpos( $h, 'ov-mine' ) && false !== strpos( $h, esc_html( get_the_title( $offices[0] ) ) ), 'an office editor sees their own office' );
t( false === strpos( $h, 'mcupr-settings-io' ) && false === strpos( $h, 'mcupr-speed' ) && false === strpos( $h, 'data-mode="maint"' ) && false === strpos( $h, 'Tools and settings' ) && false === strpos( $h, 'ov-n"' ), 'an office editor does not see the tools, the site modes or the counts of everything' );
t( substr_count( $h, 'class="ov-act"' ) >= 2 && false === strpos( $h, 'autofocus%5Bsection%5D' ), 'an office editor has the writing shortcuts but not the Customizer' );
wp_set_current_user( 0 );
t( '' === $page(), 'a visitor gets nothing' );
wp_set_current_user( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ua );
mcupr_od_remove();
if ( null === $keep ) { delete_option( 'mcupr_status' ); } else { update_option( 'mcupr_status', $keep ); }
wp_set_current_user( 0 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
