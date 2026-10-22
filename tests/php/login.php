<?php
/**
 * The sign-in page: private address (cleaning, routing decisions, links, saving) and the theme look.
 *   php php/login.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
delete_option( 'mcupr_login_slug' );
wp_set_current_user( 1 );

// ---- cleaning and reasons
t( 'staff-door' === mcupr_login_clean_slug( '  Staff Door ' ), 'slug: spaces and capitals become a clean hyphenated name' );
t( 'a-b-cdef' === mcupr_login_clean_slug( 'a__b--cdef!' ), 'slug: odd characters and doubled hyphens are squeezed' );
t( '' === mcupr_login_clean_slug( 'abc' ) && '' === mcupr_login_clean_slug( str_repeat( 'a', 41 ) ) && '' === mcupr_login_clean_slug( 'ประตู' ), 'slug: shorter than 4, longer than 40 or not a-z/0-9 is refused' );
t( '' !== mcupr_login_slug_problem( 'admin' ) && '' !== mcupr_login_slug_problem( 'wp-login' ) && '' !== mcupr_login_slug_problem( '' ), 'slug: reserved and guessable names are refused' );
$pg = wp_insert_post( array( 'post_title' => 'About', 'post_name' => 'taken-name', 'post_type' => 'page', 'post_status' => 'publish' ) );
t( '' !== mcupr_login_slug_problem( 'taken-name' ) && '' === mcupr_login_slug_problem( 'free-name-xyz' ), 'slug: an address used by a page is refused, a free one is accepted' );
wp_delete_post( $pg, true );

// ---- off: nothing changes
t( '' === mcupr_login_slug() && '' === mcupr_login_decide( 'wp-login.php', false, mcupr_login_slug() ) && '' === mcupr_login_decide( 'wp-admin/', false, '' ), 'off: no request is touched' );
t( false !== strpos( wp_login_url(), 'wp-login.php' ), 'off: links go to wp-login.php as before' );

// ---- routing decisions
$s = 'staff-door';
$d = static function ( $path, $in ) use ( $s ) { return mcupr_login_decide( $path, $in, $s ); };
t( 'login' === $d( 'staff-door', false ) && 'login' === $d( '/staff-door/', true ) && 'login' === $d( 'STAFF-DOOR', false ), 'route: the private address shows the sign-in form' );
t( 'block' === $d( 'wp-login.php', false ) && 'block' === $d( 'wp-login.php', true ) && 'block' === $d( 'wp-register.php', false ), 'route: wp-login.php and wp-register.php are not found, even when signed in' );
t( 'block' === $d( 'wp-admin', false ) && 'block' === $d( 'wp-admin/', false ) && 'block' === $d( 'wp-admin/index.php', false ) && 'block' === $d( 'wp-admin/options.php', false ), 'route: wp-admin is not found for anyone not signed in' );
t( '' === $d( 'wp-admin/', true ) && '' === $d( 'wp-admin/options.php', true ), 'route: a signed-in person still reaches wp-admin' );
t( '' === $d( 'wp-admin/admin-ajax.php', false ) && '' === $d( 'wp-admin/admin-post.php', false ) && '' === $d( 'wp-admin/load-scripts.php', false ) && '' === $d( 'wp-admin/load-styles.php', false ), 'route: what visitors and the sign-in page itself fetch from wp-admin keeps working' );
t( '' === $d( '', false ) && '' === $d( 'about', false ) && '' === $d( 'staff-doors', false ) && '' === $d( 'wp-content/uploads/a.jpg', false ), 'route: ordinary pages are left alone' );

// ---- saving
$mails = array();
add_filter( 'pre_wp_mail', static function ( $n, $a ) use ( &$mails ) { $mails[] = $a; return true; }, 10, 2 );
$r = mcupr_login_save( array( 'on' => 1, 'slug' => 'Staff Door' ) );
t( $r[0] && 'staff-door' === get_option( 'mcupr_login_slug' ) && 'staff-door' === mcupr_login_slug(), 'save: a clean address is stored and used' );
t( 1 === count( $mails ) && $mails[0]['to'] === get_option( 'admin_email' ) && false !== strpos( $mails[0]['message'], '/staff-door/' ), 'save: the new address is emailed to the administrator' );
$r = mcupr_login_save( array( 'on' => 1, 'slug' => 'staff-door' ) );
t( $r[0] && 1 === count( $mails ), 'save: saving the same address again sends no second email' );
$r = mcupr_login_save( array( 'on' => 1, 'slug' => 'admin' ) );
t( ! $r[0] && '' !== $r[1] && 'staff-door' === get_option( 'mcupr_login_slug' ), 'save: a refused address leaves the old one in place and says why' );
t( mcupr_login_form_url() === home_url( '/staff-door/' ), 'url: the form is at the private address' );

// ---- links
t( home_url( '/staff-door/' ) === wp_login_url(), 'links: wp_login_url() gives the private address' );
t( 0 === strpos( wp_lostpassword_url(), home_url( '/staff-door/' ) ) && false !== strpos( wp_lostpassword_url(), 'action=lostpassword' ), 'links: the lost-password link keeps its action' );
t( 0 === strpos( wp_logout_url(), home_url( '/staff-door/' ) ) && false !== strpos( wp_logout_url(), 'action=logout' ), 'links: the sign-out link points to the private address' );
t( home_url( '/staff-door/?action=rp&key=k' ) === network_site_url( 'wp-login.php?action=rp&key=k', 'login' ), 'links: the password-reset link of the student portal points to the private address' );
t( site_url( 'wp-login.php', 'login_post' ) === home_url( '/staff-door/' ), 'links: the sign-in form posts to the private address' );
t( admin_url( 'index.php' ) === home_url( '/wp-admin/index.php' ), 'links: other links are not touched' );
t( home_url( '/staff-door/?loggedout=true' ) === apply_filters( 'wp_redirect', home_url( '/wp-login.php?loggedout=true' ), 302 ), 'links: a redirect to wp-login.php is sent to the private address' );

// ---- who may change it
wp_set_current_user( 0 );
$_POST = array( 'mcupr_login_nonce' => 'x' );
t( ! mcupr_check_request( 'mcupr_login_nonce', 'mcupr_login_save', 'manage_options' ), 'permission: not allowed without being signed in as an administrator' );

// ---- the theme look
ob_start();
do_action( 'login_enqueue_scripts' );
wp_print_styles();
$css = ob_get_clean();
t( false !== strpos( $css, 'login.css' ) && false !== strpos( $css, '--primary' ) && false !== strpos( $css, '--logo-img' ), 'look: the sign-in page loads the theme skin with the site colours and logo' );
t( in_array( 'mcupr-login', apply_filters( 'login_body_class', array() ), true ), 'look: the page body carries the theme class' );
t( false !== strpos( apply_filters( 'login_message', '' ), 'mcupr-login-sub' ), 'look: a one-line caption names the site' );
t( file_exists( dirname( __DIR__, 2 ) . '/mcupr/assets/css/login.css' ), 'look: the stylesheet exists' );

// ---- switched off from wp-config.php (last: a constant cannot be taken back)
update_option( 'mcupr_login_slug', 'staff-door', false );
define( 'MCUPR_LOGIN_OFF', true );
t( '' === mcupr_login_slug() && false !== strpos( wp_login_url(), 'wp-login.php' ) && '' === mcupr_login_decide( 'wp-login.php', false, mcupr_login_slug() ), 'recovery: MCUPR_LOGIN_OFF in wp-config.php switches the private address off, wp-login.php works again' );
delete_option( 'mcupr_login_slug' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
