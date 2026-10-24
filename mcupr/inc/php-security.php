<?php
/**
 * PHP security check (the critical remote-code-execution flaws of 21 October 2026, CVE-2026-109618 and CVE-2026-109619).
 *
 * A theme cannot patch PHP itself: the flaws are in the PHP engine, and only the host's update (and a restart of PHP and the web server) closes
 * them. What the theme can do is not let it go unnoticed: it compares the running PHP with the fixed releases, tells administrators in the
 * admin (a notice, Tools → Site Health and the speed check), and says exactly what to ask the host. Hosts and Linux distributions often fix
 * a flaw without changing the version number, so an administrator who has the host's word for it can hide the notice (it comes back when the
 * PHP version changes).
 *
 * Fixed releases (the PHP Group's advisory of 21 October 2026): 8.2.35, 8.3.36, 8.4.27, 8.5.12. PHP before 8.2 has no official fix.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The first release of each branch that has the fix. */
function mcupr_php_fixed_releases() {
	return array(
		'8.2' => '8.2.35',
		'8.3' => '8.3.36',
		'8.4' => '8.4.27',
		'8.5' => '8.5.12',
	);
}

/**
 * Where a PHP version stands.
 *
 * @param string $version A version string such as "8.3.6" or "8.2.34-1+deb12u1" (default: the running PHP).
 * @return array state: 'ok' (fixed or newer than every known branch), 'old' (a branch with a fixed release this one is below), 'eol' (no official fix exists), 'unknown'; fixed: the release to move to ('' when none).
 */
function mcupr_php_security( $version = '' ) {
	$version = '' === $version ? PHP_VERSION : (string) $version;
	if ( ! preg_match( '/^(\d+)\.(\d+)\.(\d+)/', $version, $m ) ) {
		return array( 'state' => 'unknown', 'fixed' => '', 'version' => $version );
	}
	$plain  = $m[1] . '.' . $m[2] . '.' . $m[3];
	$branch = $m[1] . '.' . $m[2];
	$fixed  = mcupr_php_fixed_releases();
	if ( isset( $fixed[ $branch ] ) ) {
		return array( 'state' => version_compare( $plain, $fixed[ $branch ], '>=' ) ? 'ok' : 'old', 'fixed' => $fixed[ $branch ], 'version' => $version );
	}
	if ( version_compare( $branch, '8.2', '<' ) ) {
		return array( 'state' => 'eol', 'fixed' => $fixed['8.2'], 'version' => $version );
	}
	return array( 'state' => 'ok', 'fixed' => '', 'version' => $version ); // a branch newer than any the advisory knows
}

/** Has an administrator said the host fixed it, for this very PHP version? */
function mcupr_php_security_acked( $version = '' ) {
	$version = '' === $version ? PHP_VERSION : (string) $version;
	$a       = get_option( 'mcupr_php_sec_ack', array() );
	return is_array( $a ) && isset( $a['v'] ) && (string) $a['v'] === $version;
}

/** The sentence about the state (no HTML). Empty when all is well. */
function mcupr_php_security_text( $s ) {
	if ( 'old' === $s['state'] ) {
		/* translators: 1: running PHP version, 2: first fixed release of this branch */
		return sprintf( __( 'This server runs PHP %1$s, older than the fixed release %2$s for the critical remote-code-execution flaws of 21 October 2026 (CVE-2026-109618 and CVE-2026-109619). Until PHP is updated, a visitor could run code on the server without logging in.', 'mcupr' ), $s['version'], $s['fixed'] );
	}
	if ( 'eol' === $s['state'] ) {
		/* translators: 1: running PHP version, 2: the oldest branch that has a fixed release */
		return sprintf( __( 'This server runs PHP %1$s, which no longer gets fixes from the PHP project, and the critical remote-code-execution flaws of 21 October 2026 (CVE-2026-109618 and CVE-2026-109619) have no official release for it. Move the site to PHP 8.2.35 or newer (8.3.36, 8.4.27 and 8.5.12 are fixed too).', 'mcupr' ), $s['version'] );
	}
	return '';
}

function mcupr_php_security_steps() {
	return __( 'What to do now: ask your host (or the person who runs the server) to update PHP to a fixed release, and then to restart PHP and the web server so that every worker uses the new version. A theme cannot patch PHP itself.', 'mcupr' );
}

function mcupr_php_security_backport() {
	return __( 'Some hosts and Linux distributions (Ubuntu, Debian, Red Hat, CloudLinux) fix the flaws without changing the version number. If your host has confirmed that, you can hide this notice; it comes back when the PHP version changes.', 'mcupr' );
}

function mcupr_php_security_url() {
	return 'https://www.php.net/archive/2026.php';
}

/** The notice for administrators, on every admin page, until the PHP is fixed or the administrator hides it. */
function mcupr_php_security_notice() {
	$s = mcupr_php_security();
	if ( 'ok' === $s['state'] || 'unknown' === $s['state'] || ! current_user_can( 'manage_options' ) || mcupr_php_security_acked() ) {
		return;
	}
	echo '<div class="notice notice-error" id="mcupr-php-security"><p><strong>' . esc_html__( 'Security: PHP needs an update', 'mcupr' ) . '</strong></p>';
	echo '<p>' . esc_html( mcupr_php_security_text( $s ) ) . '</p>';
	echo '<p>' . esc_html( mcupr_php_security_steps() ) . '</p>';
	echo '<p>' . esc_html( mcupr_php_security_backport() ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 1em .5em 0">';
	wp_nonce_field( 'mcupr_php_sec_ack' );
	echo '<input type="hidden" name="action" value="mcupr_php_sec_ack"><button type="submit" class="button">' . esc_html__( 'My host confirmed the fix – hide this notice', 'mcupr' ) . '</button></form>';
	echo '<a class="button button-primary" href="' . esc_url( mcupr_php_security_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Read the PHP announcement', 'mcupr' ) . '<span class="screen-reader-text"> (' . esc_html__( 'opens in a new tab', 'mcupr' ) . ')</span></a></div>';
}
add_action( 'admin_notices', 'mcupr_php_security_notice' );

/** "My host confirmed the fix": remember it for this PHP version. */
function mcupr_php_security_ack() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mcupr_php_sec_ack' );
	update_option( 'mcupr_php_sec_ack', array( 'v' => PHP_VERSION, 'by' => get_current_user_id(), 't' => time() ), false );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_mcupr_php_sec_ack', 'mcupr_php_security_ack' );

/** Tools → Site Health: the same check. */
add_filter(
	'site_status_tests',
	static function ( $tests ) {
		$tests['direct']['mcupr_php_security'] = array(
			'label' => __( 'PHP has the October 2026 security fix', 'mcupr' ),
			'test'  => static function () {
				$s     = mcupr_php_security();
				$acked = mcupr_php_security_acked();
				$good  = 'ok' === $s['state'] || 'unknown' === $s['state'] || $acked;
				return array(
					'label'       => $good ? sprintf( /* translators: %s: PHP version */ __( 'PHP %s is not affected by the October 2026 flaws', 'mcupr' ), $s['version'] ) : sprintf( /* translators: %s: PHP version */ __( 'PHP %s needs the October 2026 security update', 'mcupr' ), $s['version'] ),
					'status'      => $good ? 'good' : 'critical',
					'badge'       => array( 'label' => __( 'Security', 'mcupr' ), 'color' => $good ? 'blue' : 'red' ),
					'description' => '<p>' . esc_html( $good ? ( 'ok' === $s['state'] ? __( 'The running PHP version is a fixed release or newer.', 'mcupr' ) : __( 'An administrator confirmed that the host has fixed it.', 'mcupr' ) ) : mcupr_php_security_text( $s ) ) . '</p>' . ( $good ? '' : '<p>' . esc_html( mcupr_php_security_steps() ) . '</p><p>' . esc_html( mcupr_php_security_backport() ) . '</p>' ),
					'actions'     => $good ? '' : '<a href="' . esc_url( mcupr_php_security_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Read the PHP announcement', 'mcupr' ) . '</a>',
					'test'        => 'mcupr_php_security',
				);
			},
		);
		return $tests;
	}
);
