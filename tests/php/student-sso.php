<?php
/** Sign in with the university Google account: the ID token checks, finding the student, the safety rules, the settings.   php php/student-sso.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() { foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } }
function stu( $sid, $email, $o = array() ) {
	$d = array_merge( array( 'sid' => $sid, 'name' => 'นิสิต ' . $sid, 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2567', 'faculty' => 'ก', 'major' => 'ข', 'status' => 'studying', 'email' => $email, 'phone' => '', 'note' => '' ), $o );
	list( , $id ) = mcupr_student_upsert( $d, 'add_update' );
	return $id;
}
function jwt( $c ) { $b = function ( $x ) { return rtrim( strtr( base64_encode( wp_json_encode( $x ) ), '+/', '-_' ), '=' ); }; return $b( array( 'alg' => 'RS256' ) ) . '.' . $b( $c ) . '.sig'; }
wp_set_current_user( 1 );
wipe();
$old = get_option( 'mcupr_sso' );
update_option( 'mcupr_sso', array( 'on' => 1, 'id' => 'client-123', 'secret' => 'sec', 'domain' => 'mcu.ac.th' ) );
$good = array( 'iss' => 'https://accounts.google.com', 'aud' => 'client-123', 'exp' => time() + 600, 'nonce' => 'n1', 'sub' => 'g-111', 'email' => '6401001@mcu.ac.th', 'email_verified' => true, 'hd' => 'mcu.ac.th' );

// ---- ready
t( mcupr_sso_ready(), 'ready: on with id, secret and domain' );
foreach ( array( 'on' => 0, 'id' => '', 'secret' => '', 'domain' => '' ) as $k => $v ) {
	update_option( 'mcupr_sso', array_merge( array( 'on' => 1, 'id' => 'client-123', 'secret' => 'sec', 'domain' => 'mcu.ac.th' ), array( $k => $v ) ) );
	t( ! mcupr_sso_ready(), 'ready: not when ' . $k . ' is missing' );
}
update_option( 'mcupr_sso', array( 'on' => 1, 'id' => 'client-123', 'secret' => 'sec', 'domain' => 'mcu.ac.th' ) );

// ---- the token
t( $good === mcupr_sso_claims( jwt( $good ) ) && null === mcupr_sso_claims( 'a.b' ) && null === mcupr_sso_claims( 'a.!!!.c' ), 'token: the claims are read; garbage is not' );
t( '' === mcupr_sso_check_claims( $good, 'n1' ), 'token: a good one passes' );
$cases = array(
	'iss'        => array( array( 'iss' => 'https://evil.example' ), 'n1' ),
	'aud'        => array( array( 'aud' => 'other-client' ), 'n1' ),
	'exp'        => array( array( 'exp' => time() - 3600 ), 'n1' ),
	'nonce'      => array( array(), 'wrong' ),
	'email'      => array( array( 'email' => 'not-an-address' ), 'n1' ),
	'unverified' => array( array( 'email_verified' => false ), 'n1' ),
	'domain'     => array( array( 'email' => 'x@gmail.com', 'hd' => 'mcu.ac.th' ), 'n1' ),
);
foreach ( $cases as $code => $c ) { t( $code === mcupr_sso_check_claims( array_merge( $good, $c[0] ), $c[1] ), 'token: refused – ' . $code ); }
t( 'domain' === mcupr_sso_check_claims( array_merge( $good, array( 'hd' => 'other.ac.th' ) ), 'n1' ), 'token: refused – Google says another hosted domain' );
t( 'domain' === mcupr_sso_check_claims( array_merge( $good, array( 'email' => 'a@evil-mcu.ac.th' ) ), 'n1' ), 'token: refused – a look-alike domain (evil-mcu.ac.th)' );
t( 'domain' === mcupr_sso_check_claims( array_merge( $good, array( 'email' => 'a@mcu.ac.th.evil.com' ) ), 'n1' ), 'token: refused – the domain only at the start' );
unset( $good['nonce'] );
t( 'nonce' === mcupr_sso_check_claims( $good, '' ) || 'nonce' === mcupr_sso_check_claims( $good, 'n1' ), 'token: no nonce in the token is refused' );
$good['nonce'] = 'n1';
t( '' === mcupr_sso_check_claims( array_merge( $good, array( 'email_verified' => 'true' ) ), 'n1' ), 'token: "true" as text is read as verified' );
t( '' === mcupr_sso_check_claims( array_merge( $good, array( 'aud' => array( 'x', 'client-123' ) ) ), 'n1' ), 'token: the audience may be a list' );

// ---- who
$a = stu( '6401001', '6401001@MCU.ac.th' );
$c = $good;
$r = mcupr_sso_resolve( $c );
t( is_array( $r ) && $r[1] === $a, 'who: the e-mail is matched with the record, whatever the letter case' );
t( is_array( $r ) && mcupr_is_student_user( $r[0] ) && (int) get_user_meta( $r[0], 'mcupr_student_id', true ) === $a, 'who: a login account is made for a student who has none, and it is a student account' );
$again = mcupr_sso_resolve( $c );
t( is_array( $again ) && $again[0] === $r[0], 'who: the second time it is the same account' );
update_user_meta( $r[0], 'mcupr_sso_sub', 'g-111' );
t( 'sub' === mcupr_sso_resolve( array_merge( $c, array( 'sub' => 'g-999' ) ) ), 'who: the same e-mail with another Google account number is refused' );
t( is_array( mcupr_sso_resolve( $c ) ), 'who: and the right one still works' );
t( 'none' === mcupr_sso_resolve( array_merge( $c, array( 'email' => 'nobody@mcu.ac.th' ) ) ), 'who: an e-mail that is in no record' );
update_post_meta( $a, '_mcupr_s_status', 'graduated' );
t( 'ended' === mcupr_sso_resolve( $c ), 'who: a student who no longer studies' );
update_post_meta( $a, '_mcupr_s_status', 'studying' );
$b = stu( '6401002', '6401001@mcu.ac.th' );
t( 'many' === mcupr_sso_resolve( $c ), 'who: an e-mail in two records is refused' );
wp_delete_post( $b, true );

// staff are never signed in this way
$staff = wp_insert_user( array( 'user_login' => 'sso-staff', 'user_pass' => wp_generate_password(), 'user_email' => 'boss@mcu.ac.th', 'role' => 'administrator' ) );
$s2 = stu( '6401003', 'boss@mcu.ac.th' );
t( 'account' === mcupr_sso_resolve( array_merge( $c, array( 'email' => 'boss@mcu.ac.th', 'sub' => 'g-2' ) ) ), 'safety: a student record with the e-mail of an administrator does not give access to the administrator' );
$s3 = stu( '6401004', 'ed@mcu.ac.th' );
$ed = wp_insert_user( array( 'user_login' => '6401004', 'user_pass' => wp_generate_password(), 'user_email' => 'ed@mcu.ac.th', 'role' => 'editor' ) );
update_user_meta( $ed, 'mcupr_student_id', $s3 );
update_post_meta( $s3, '_mcupr_s_user', $ed );
t( 'account' === mcupr_sso_resolve( array_merge( $c, array( 'email' => 'ed@mcu.ac.th', 'sub' => 'g-3' ) ) ), 'safety: a user who can edit is never signed in this way, even if linked to a record' );

// ---- the steps are closed
t( has_action( 'template_redirect' ) && ! has_action( 'admin_post_nopriv_mcupr_sso_save' ), 'safety: the settings cannot be saved without logging in' );
$edi = wp_insert_user( array( 'user_login' => 'sso-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'sso-edi@example.org', 'role' => 'editor' ) );
t( ! user_can( $edi, MCUPR_STU_CAP ), 'safety: an editor cannot change the sign-in settings' );
t( false !== strpos( mcupr_sso_redirect_uri(), 'mcupr_sso=callback' ) && false !== strpos( mcupr_sso_start_url(), 'mcupr_sso=start' ), 'urls: work with plain permalinks too (query strings)' );
ob_start(); do_action( 'mcupr_stu_settings_extra' ); $form = ob_get_clean();
t( false !== strpos( $form, mcupr_sso_redirect_uri() ) && false === strpos( $form, 'value="sec"' ) && false !== strpos( $form, 'type="password"' ), 'settings: shows the address to give Google; the secret is never printed back' );
t( isset( mcupr_audit_actions()['sso'], mcupr_audit_actions()['ssofail'] ), 'log: sign-in and refusal have names' );

update_option( 'mcupr_sso', array( 'on' => 1, 'id' => 'client-123', 'secret' => 'sec', 'domain' => 'mcu.ac.th' ) );
$ep = mcupr_sso_endpoints();
t( 0 === strpos( $ep['auth'], 'https://accounts.google.com/' ) && 0 === strpos( $ep['token'], 'https://' ), 'endpoints: Google, over https' );

$old ? update_option( 'mcupr_sso', $old ) : delete_option( 'mcupr_sso' );
wipe();
foreach ( array( $staff, $ed, $edi ) as $x ) { wp_delete_user( $x ); }
foreach ( get_users( array( 'search' => '640100*', 'search_columns' => array( 'user_login' ) ) ) as $x ) { wp_delete_user( $x->ID ); }
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
