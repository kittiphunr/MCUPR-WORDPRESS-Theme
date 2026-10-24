<?php
/** The PHP security check (October 2026 remote-code-execution flaws): which versions are fine, the admin notice, hiding it, Site Health.   php php/php-security.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// the table of versions
$st = static function ( $v ) { return mcupr_php_security( $v )['state']; };
t( 'old' === $st( '8.2.34' ) && 'ok' === $st( '8.2.35' ) && 'ok' === $st( '8.2.40' ), '8.2: 8.2.34 is old, 8.2.35 and later are fixed' );
t( 'old' === $st( '8.3.35' ) && 'old' === $st( '8.3.6' ) && 'ok' === $st( '8.3.36' ), '8.3: 8.3.35 and 8.3.6 are old, 8.3.36 is fixed' );
t( 'old' === $st( '8.4.26' ) && 'ok' === $st( '8.4.27' ), '8.4: 8.4.26 is old, 8.4.27 is fixed' );
t( 'old' === $st( '8.5.11' ) && 'ok' === $st( '8.5.12' ), '8.5: 8.5.11 is old, 8.5.12 is fixed ' );
t( 'eol' === $st( '8.1.30' ) && 'eol' === $st( '7.4.33' ) && 'eol' === $st( '7.0.0' ), 'before 8.2 there is no official fix (eol)' );
t( 'ok' === $st( '8.6.0' ) && 'ok' === $st( '9.0.1' ), 'a branch newer than the advisory knows is taken as fine' );
t( 'old' === $st( '8.2.34-1+deb12u1' ) && 'ok' === $st( '8.3.36-1ubuntu1' ), 'a distribution suffix after the number is ignored' );
t( 'unknown' === $st( 'banana' ) && 'unknown' === $st( '8.x' ), 'text that is not a version is "unknown", not a false alarm' );
t( '8.3.36' === mcupr_php_security( '8.3.6' )['fixed'] && '8.2.35' === mcupr_php_security( '7.4.0' )['fixed'], 'the answer names the release to move to' );

// the notice
delete_option( 'mcupr_php_sec_ack' );
$run_notice = static function () { ob_start(); mcupr_php_security_notice(); return ob_get_clean(); };
wp_set_current_user( 0 );
t( '' === $run_notice(), 'a visitor sees no notice' );
if ( $old_ed = get_user_by( 'login', 'ps_editor' ) ) { wp_delete_user( $old_ed->ID ); }
$ed = wp_insert_user( array( 'user_login' => 'ps_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'ps_editor@example.org', 'role' => 'editor' ) );
wp_set_current_user( $ed );
t( '' === $run_notice(), 'an editor sees no notice (administrators only)' );
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
$vulnerable = 'old' === mcupr_php_security()['state'] || 'eol' === mcupr_php_security()['state'];
$n = $run_notice();
if ( $vulnerable ) {
	t( false !== strpos( $n, 'notice-error' ) && false !== strpos( $n, PHP_VERSION ) && false !== strpos( $n, 'CVE-2026-109618' ) && false !== strpos( $n, esc_html( mcupr_php_security_steps() ) ) && false !== strpos( $n, 'mcupr_php_sec_ack' ), 'an administrator on an old PHP gets the notice: the version, the CVEs, what to ask the host, the way to hide it' );
	t( false !== strpos( $n, 'noopener' ) && false !== strpos( $n, 'https://www.php.net/archive/2026.php' ), 'the link to the PHP announcement opens safely in a new tab' );
	// hide it
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'mcupr_php_sec_ack' ); $_POST = array( 'action' => 'mcupr_php_sec_ack', '_wpnonce' => $_REQUEST['_wpnonce'] );
	add_filter( 'wp_redirect', static function () { throw new Exception( 'redirected' ); } );
	try { mcupr_php_security_ack(); } catch ( Exception $e ) { /* the redirect */ }
	t( mcupr_php_security_acked() && '' === $run_notice(), 'after "my host confirmed the fix" the notice is gone' );
	t( ! mcupr_php_security_acked( '9.9.9' ), 'the confirmation belongs to this PHP version only: another version brings the notice back' );
	// a request without a good nonce changes nothing
	delete_option( 'mcupr_php_sec_ack' ); $_REQUEST['_wpnonce'] = 'bad'; $_POST['_wpnonce'] = 'bad';
	$blocked = false;
	add_filter( 'wp_die_handler', static function () { return static function () { throw new Exception( 'died' ); }; } );
	try { mcupr_php_security_ack(); } catch ( Exception $e ) { $blocked = 'died' === $e->getMessage(); }
	t( $blocked && ! mcupr_php_security_acked(), 'a request with a bad nonce is refused and hides nothing' );
} else {
	t( '' === $n, 'this PHP (' . PHP_VERSION . ') is fixed: no notice' );
}
// Site Health
$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );
t( isset( $tests['direct']['mcupr_php_security']['test'] ), 'Site Health has the check' );
$r = call_user_func( $tests['direct']['mcupr_php_security']['test'] );
t( in_array( $r['status'], array( 'good', 'critical' ), true ) && ( $vulnerable ? 'critical' === $r['status'] : 'good' === $r['status'] ) && false !== strpos( $r['label'], PHP_VERSION ), 'Site Health says ' . $r['status'] . ' for PHP ' . PHP_VERSION );
update_option( 'mcupr_php_sec_ack', array( 'v' => PHP_VERSION, 'by' => 0, 't' => time() ), false );
$r2 = call_user_func( $tests['direct']['mcupr_php_security']['test'] );
t( 'good' === $r2['status'], 'once an administrator confirmed the fix, Site Health is good' );
wp_delete_user( $ed );
echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
