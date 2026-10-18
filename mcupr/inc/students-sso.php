<?php
/**
 * "Sign in with the university Google account" for students (Google Workspace, OpenID Connect, authorization-code flow).
 *
 *  - Staff give the Client ID and Client secret of an OAuth client made in Google Cloud Console and the university's domain
 *    (MCUPR › Student portal settings). Nothing is on until all three are given and the switch is on.
 *  - The student presses the button in the portal, signs in at Google and comes back. The website exchanges the code for an
 *    ID token directly with Google over HTTPS (the token is not taken from the browser), checks issuer, audience, expiry, the
 *    one-time "nonce" and "state" (bound to the browser by a cookie), that the e-mail is verified and in the university's
 *    domain (and Google's "hd" says the same).
 *  - The e-mail is matched with the e-mail in the student record: exactly one student, who is studying now. A login account is
 *    made if the student has none. Only student accounts are signed in this way – never staff, never an account that can edit.
 *  - The first sign-in stores Google's account number (sub) with the account; if the same e-mail later comes with another
 *    number (the address was given to somebody else) it is refused.
 *  - Staff keep their own passwords; this changes nothing for them.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_sso_opts() {
	$o = get_option( 'mcupr_sso', array() );
	$o = array_merge( array( 'on' => 0, 'id' => '', 'secret' => '', 'domain' => '' ), is_array( $o ) ? $o : array() );
	if ( defined( 'MCUPR_SSO_SECRET' ) && '' !== MCUPR_SSO_SECRET ) {
		$o['secret'] = (string) MCUPR_SSO_SECRET;
	}
	return array( 'on' => ! empty( $o['on'] ) ? 1 : 0, 'id' => (string) $o['id'], 'secret' => (string) $o['secret'], 'domain' => strtolower( (string) $o['domain'] ) );
}
/** On, and everything it needs is given. */
function mcupr_sso_ready() {
	$o = mcupr_sso_opts();
	return $o['on'] && '' !== $o['id'] && '' !== $o['secret'] && '' !== $o['domain'] && mcupr_stu_opts()['portal'];
}
function mcupr_sso_endpoints() {
	return apply_filters(
		'mcupr_sso_endpoints',
		array(
			'auth'   => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token'  => 'https://oauth2.googleapis.com/token',
			'issuer' => array( 'https://accounts.google.com', 'accounts.google.com' ),
		)
	);
}
function mcupr_sso_redirect_uri() {
	return add_query_arg( 'mcupr_sso', 'callback', home_url( '/' ) );
}
function mcupr_sso_start_url() {
	return add_query_arg( 'mcupr_sso', 'start', home_url( '/' ) );
}

/* ------------------------------------------------------------------ the id token */

function mcupr_sso_b64( $s ) {
	$s = strtr( (string) $s, '-_', '+/' );
	return (string) base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}
/** The claims of an ID token received directly from the token endpoint (its signature is not checked: TLS to Google is the proof). */
function mcupr_sso_claims( $jwt ) {
	$p = explode( '.', (string) $jwt );
	if ( 3 !== count( $p ) ) {
		return null;
	}
	$c = json_decode( mcupr_sso_b64( $p[1] ), true );
	return is_array( $c ) ? $c : null;
}
/** '' when the claims are acceptable, else a short reason code. */
function mcupr_sso_check_claims( $c, $nonce ) {
	$o  = mcupr_sso_opts();
	$ep = mcupr_sso_endpoints();
	if ( empty( $c['iss'] ) || ! in_array( $c['iss'], (array) $ep['issuer'], true ) ) {
		return 'iss';
	}
	$aud = isset( $c['aud'] ) ? (array) $c['aud'] : array();
	if ( ! in_array( $o['id'], $aud, true ) ) {
		return 'aud';
	}
	if ( empty( $c['exp'] ) || (int) $c['exp'] < time() - 60 ) {
		return 'exp';
	}
	if ( empty( $c['nonce'] ) || ! hash_equals( (string) $nonce, (string) $c['nonce'] ) ) {
		return 'nonce';
	}
	if ( empty( $c['sub'] ) || empty( $c['email'] ) || ! is_email( $c['email'] ) ) {
		return 'email';
	}
	if ( true !== $c['email_verified'] && 'true' !== ( $c['email_verified'] ?? '' ) ) {
		return 'unverified';
	}
	$mail = strtolower( $c['email'] );
	if ( substr( $mail, -strlen( '@' . $o['domain'] ) ) !== '@' . $o['domain'] ) {
		return 'domain';
	}
	if ( empty( $c['hd'] ) || strtolower( (string) $c['hd'] ) !== $o['domain'] ) { // a personal account that merely uses a university address has no 'hd'
		return 'domain';
	}
	return '';
}

/* ------------------------------------------------------------------ who is it */

/** The students (ids) whose record has this e-mail. */
function mcupr_sso_students_by_email( $mail ) {
	global $wpdb;
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_mcupr_s_email' AND LOWER(TRIM(m.meta_value)) = %s AND p.post_type = %s AND p.post_status <> 'trash'", strtolower( trim( $mail ) ), MCUPR_STU ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Claims → the user id to sign in, or a reason code (string). */
function mcupr_sso_resolve( $c ) {
	$ids = mcupr_sso_students_by_email( $c['email'] );
	if ( 1 !== count( $ids ) ) {
		return $ids ? 'many' : 'none';
	}
	$sid = $ids[0];
	if ( 'studying' !== get_post_meta( $sid, '_mcupr_s_status', true ) ) {
		return 'ended';
	}
	$uid = mcupr_student_make_account( $sid );
	if ( is_wp_error( $uid ) ) {
		return 'account';
	}
	$u = get_userdata( $uid );
	if ( ! $u || ! mcupr_is_student_user( $uid ) || user_can( $u, 'edit_posts' ) || user_can( $u, 'manage_options' ) || (int) get_user_meta( $uid, 'mcupr_student_id', true ) !== $sid ) {
		return 'account';
	}
	$have = (string) get_user_meta( $uid, 'mcupr_sso_sub', true );
	if ( '' !== $have && ! hash_equals( $have, (string) $c['sub'] ) ) {
		return 'sub';
	}
	return array( (int) $uid, $sid );
}

/* ------------------------------------------------------------------ the two steps */

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'mcupr_sso';
		return $v;
	}
);

function mcupr_sso_fail( $code ) {
	mcupr_audit( 'ssofail', array( 'note' => sanitize_key( $code ) ) );
	$map = array( 'none' => 'ssonone', 'ended' => 'ssonone', 'many' => 'ssonone', 'wait' => 'ssowait' );
	wp_safe_redirect( mcupr_student_portal_url( array( 'e' => isset( $map[ $code ] ) ? $map[ $code ] : 'sso' ) ) );
	exit;
}

add_action(
	'template_redirect',
	static function () {
		$step = get_query_var( 'mcupr_sso' );
		if ( ! $step ) {
			return;
		}
		nocache_headers();
		if ( ! mcupr_sso_ready() || is_user_logged_in() ) {
			wp_safe_redirect( mcupr_student_portal_url() );
			exit;
		}
		if ( ! mcupr_stu_throttle( 'sso|' . mcupr_stu_ip(), 120, 15 * MINUTE_IN_SECONDS ) ) {
			mcupr_sso_fail( 'wait' );
		}
		$o  = mcupr_sso_opts();
		$ep = mcupr_sso_endpoints();
		if ( 'start' === $step ) {
			$state = bin2hex( random_bytes( 16 ) );
			$nonce = bin2hex( random_bytes( 16 ) );
			set_transient( 'mcupr_sso_' . $state, $nonce, 10 * MINUTE_IN_SECONDS );
			mcupr_set_cookie( 'mcupr_sso_state', $state, time() + 600 );
			wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect
				add_query_arg(
					array(
						'client_id'     => $o['id'],
						'redirect_uri'  => mcupr_sso_redirect_uri(),
						'response_type' => 'code',
						'scope'         => 'openid email',
						'state'         => $state,
						'nonce'         => $nonce,
						'hd'            => $o['domain'],
						'prompt'        => 'select_account',
					),
					$ep['auth']
				)
			);
			exit;
		}
		if ( 'callback' !== $step ) {
			wp_safe_redirect( mcupr_student_portal_url() );
			exit;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$state  = isset( $_GET['state'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['state'] ) ) : '';
		$code   = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$cookie = isset( $_COOKIE['mcupr_sso_state'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_COOKIE['mcupr_sso_state'] ) ) : '';
		// phpcs:enable
		$nonce = '' !== $state ? get_transient( 'mcupr_sso_' . $state ) : false;
		if ( '' !== $state ) {
			delete_transient( 'mcupr_sso_' . $state ); // one time only
		}
		mcupr_set_cookie( 'mcupr_sso_state', '', time() - 3600 );
		if ( '' === $state || '' === $cookie || ! hash_equals( $state, $cookie ) || false === $nonce ) {
			mcupr_sso_fail( 'state' );
		}
		if ( '' === $code || isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			mcupr_sso_fail( 'denied' );
		}
		$res = wp_remote_post(
			$ep['token'],
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $o['id'],
					'client_secret' => $o['secret'],
					'redirect_uri'  => mcupr_sso_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		$data = is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['id_token'] ) ) {
			mcupr_sso_fail( 'token' );
		}
		$claims = mcupr_sso_claims( $data['id_token'] );
		$bad    = $claims ? mcupr_sso_check_claims( $claims, $nonce ) : 'jwt';
		if ( '' !== $bad ) {
			mcupr_sso_fail( $bad );
		}
		$who = mcupr_sso_resolve( $claims );
		if ( ! is_array( $who ) ) {
			mcupr_sso_fail( $who );
		}
		list( $uid, $sid ) = $who;
		update_user_meta( $uid, 'mcupr_sso_sub', (string) $claims['sub'] );
		$user = get_userdata( $uid );
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, false, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		mcupr_audit( 'sso', array( 'post' => $sid, 'sid' => (string) get_post_meta( $sid, '_mcupr_s_sid', true ) ) );
		wp_safe_redirect( mcupr_student_portal_url() );
		exit;
	},
	1
);

/* ------------------------------------------------------------------ settings (on the student portal settings page) */

add_action(
	'mcupr_stu_settings_extra',
	static function () {
		$o = mcupr_sso_opts();
		echo '<h2>' . esc_html__( 'Sign in with the university Google account', 'mcupr' ) . '</h2><p style="max-width:56rem">' . esc_html__( 'Students press one button and sign in with their university Google account (Google Workspace). Their e-mail address must be the one in their student record. Staff are not affected.', 'mcupr' ) . '</p>';
		echo '<ol style="max-width:56rem"><li>' . esc_html__( 'In Google Cloud Console make an OAuth client of the type “Web application”.', 'mcupr' ) . '</li><li>' . esc_html__( 'Give it this address as an authorised redirect URI:', 'mcupr' ) . ' <code>' . esc_html( mcupr_sso_redirect_uri() ) . '</code></li><li>' . esc_html__( 'Copy its Client ID and Client secret here, and give the university’s e-mail domain.', 'mcupr' ) . '</li></ol>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_sso_save">';
		wp_nonce_field( 'mcupr_sso_save', 'mcupr_sso_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Google sign-in', 'mcupr' ) . '</th><td><label><input type="checkbox" name="on" value="1"' . checked( $o['on'], 1, false ) . '> ' . esc_html__( 'On (shows the button in the student portal)', 'mcupr' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="sso-id">' . esc_html__( 'Client ID', 'mcupr' ) . '</label></th><td><input id="sso-id" name="id" type="text" class="large-text" autocomplete="off" value="' . esc_attr( $o['id'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="sso-secret">' . esc_html__( 'Client secret', 'mcupr' ) . '</label></th><td><input id="sso-secret" name="secret" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( '' !== $o['secret'] ? __( 'Saved – leave empty to keep it', 'mcupr' ) : '' ) . '"><p class="description">' . esc_html__( 'Kept on this website only. It can also be set in wp-config.php as MCUPR_SSO_SECRET.', 'mcupr' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="sso-domain">' . esc_html__( 'University e-mail domain', 'mcupr' ) . '</label></th><td><input id="sso-domain" name="domain" type="text" class="regular-text" placeholder="example.ac.th" value="' . esc_attr( $o['domain'] ) . '"><p class="description">' . esc_html__( 'Only Google accounts of this domain can sign in (without the @).', 'mcupr' ) . '</p></td></tr>';
		echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button> ' . ( mcupr_sso_ready() ? '<span class="description">' . esc_html__( 'The button is shown in the student portal.', 'mcupr' ) . '</span>' : '<span class="description">' . esc_html__( 'The button is not shown yet: it needs the switch, the Client ID, the Client secret and the domain.', 'mcupr' ) . '</span>' ) . '</p></form>';
	}
);

add_action(
	'admin_post_mcupr_sso_save',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_sso_nonce', 'mcupr_sso_save', MCUPR_STU_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$secret = isset( $_POST['secret'] ) ? trim( (string) wp_unslash( $_POST['secret'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$dom    = isset( $_POST['domain'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['domain'] ) ), " @\t" ) ) : '';
		update_option(
			'mcupr_sso',
			array(
				'on'     => ! empty( $_POST['on'] ) ? 1 : 0,
				'id'     => isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '',
				'secret' => '' !== $secret ? $secret : get_option( 'mcupr_sso', array() )['secret'] ?? '',
				'domain' => preg_match( '/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}$/', $dom ) ? $dom : '',
			),
			false
		);
		mcupr_audit( 'settings', array( 'note' => 'google sign-in' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-settings&saved=1' ) );
		exit;
	}
);
