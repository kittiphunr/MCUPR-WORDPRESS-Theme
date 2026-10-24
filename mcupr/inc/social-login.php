<?php
/**
 * Sign in with Google, Facebook, LINE or ThaID (OAuth 2.0 / OpenID Connect, authorization-code flow).
 *
 *  - For visitors and students.  Staff (anyone who can edit) never sign in this way: their accounts keep passwords.
 *  - A person with no account gets a member account (read, comment, forum, forms) at once, unless that is switched off.
 *  - A student whose e-mail is in the student record, signing in with Google (which says the e-mail is verified), gets the
 *    student account.  Other providers do not vouch for an e-mail, so they never join an existing account by e-mail; a signed-in
 *    person links them from the profile page instead.
 *  - The website talks to the provider directly over HTTPS (code, then token); "state" is bound to the browser by a cookie
 *    and used once, "nonce" and PKCE protect the ID token, issuer / audience / expiry are checked.
 *  - ThaID: the national ID number is never stored; only a keyed hash of it, to recognise the same person next time.
 *  - Each provider needs an app made by the organisation at the provider (Client ID and secret, set under
 *    MCUPR › Visitors › Social sign-in).  Nothing is on until a provider is switched on and has both.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ the providers */

/** What is known about each provider (filterable: endpoints change, and ThaID's differ by agreement). */
function mcupr_social_providers() {
	return apply_filters(
		'mcupr_social_providers',
		array(
			'google'   => array(
				'label'  => 'Google',
				'mark'   => 'G',
				'color'  => '#4285f4',
				'auth'   => 'https://accounts.google.com/o/oauth2/v2/auth',
				'token'  => 'https://oauth2.googleapis.com/token',
				'scope'  => 'openid email profile',
				'issuer' => array( 'https://accounts.google.com', 'accounts.google.com' ),
				'pkce'   => true,
				'oidc'   => true,
			),
			'facebook' => array(
				'label'  => 'Facebook',
				'mark'   => 'f',
				'color'  => '#1877f2',
				'auth'   => 'https://www.facebook.com/v19.0/dialog/oauth',
				'token'  => 'https://graph.facebook.com/v19.0/oauth/access_token',
				'me'     => 'https://graph.facebook.com/v19.0/me',
				'scope'  => 'public_profile,email',
				'pkce'   => false,
				'oidc'   => false,
			),
			'line'     => array(
				'label'  => 'LINE',
				'mark'   => 'L',
				'color'  => '#06c755',
				'auth'   => 'https://access.line.me/oauth2/v2.1/authorize',
				'token'  => 'https://api.line.me/oauth2/v2.1/token',
				'verify' => 'https://api.line.me/oauth2/v2.1/verify',
				'scope'  => 'openid profile email',
				'pkce'   => true,
				'oidc'   => true,
			),
			'thaid'    => array(
				'label'  => 'ThaID',
				'mark'   => 'ID',
				'color'  => '#1f3a8a',
				'auth'   => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/auth/',
				'token'  => 'https://imauth.bora.dopa.go.th/api/v2/oauth2/token/',
				'scope'  => 'pid given_name family_name',
				'pkce'   => false,
				'oidc'   => false,
				'basic'  => true,
			),
		)
	);
}

/** The settings: auto (make member accounts), and per provider on / id / secret (+ ThaID's own endpoints and scope). */
function mcupr_social_opts() {
	$o = get_option( 'mcupr_social', array() );
	$o = is_array( $o ) ? $o : array();
	$r = array( 'auto' => isset( $o['auto'] ) ? ( ! empty( $o['auto'] ) ? 1 : 0 ) : 1 );
	foreach ( array_keys( mcupr_social_providers() ) as $p ) {
		$c      = isset( $o[ $p ] ) && is_array( $o[ $p ] ) ? $o[ $p ] : array();
		$secret = isset( $c['secret'] ) ? (string) $c['secret'] : '';
		$const  = 'MCUPR_SOCIAL_' . strtoupper( $p ) . '_SECRET';
		if ( defined( $const ) && '' !== constant( $const ) ) {
			$secret = (string) constant( $const );
		}
		$r[ $p ] = array(
			'on'     => ! empty( $c['on'] ) ? 1 : 0,
			'id'     => isset( $c['id'] ) ? (string) $c['id'] : '',
			'secret' => $secret,
			'auth'   => isset( $c['auth'] ) ? (string) $c['auth'] : '',
			'token'  => isset( $c['token'] ) ? (string) $c['token'] : '',
			'scope'  => isset( $c['scope'] ) ? (string) $c['scope'] : '',
		);
	}
	return $r;
}

/** One provider with the endpoints in force (a typed-in ThaID address beats the built-in one). */
function mcupr_social_provider( $p ) {
	$all = mcupr_social_providers();
	if ( ! isset( $all[ $p ] ) ) {
		return null;
	}
	$def = $all[ $p ];
	$cfg = mcupr_social_opts();
	foreach ( array( 'auth', 'token', 'scope' ) as $k ) {
		if ( '' !== $cfg[ $p ][ $k ] && ( 'scope' === $k || 0 === strpos( $cfg[ $p ][ $k ], 'https://' ) ) ) {
			$def[ $k ] = $cfg[ $p ][ $k ];
		}
	}
	return $def;
}

/** On, with an id and a secret. */
function mcupr_social_ready( $p ) {
	$o = mcupr_social_opts();
	return isset( $o[ $p ] ) && is_array( $o[ $p ] ) && $o[ $p ]['on'] && '' !== $o[ $p ]['id'] && '' !== $o[ $p ]['secret'];
}

/** The providers that can be used now, in order. */
function mcupr_social_enabled() {
	$out = array();
	foreach ( array_keys( mcupr_social_providers() ) as $p ) {
		if ( mcupr_social_ready( $p ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/** A URL with a query string whose values are properly encoded (add_query_arg() leaves them as they are). */
function mcupr_social_url( $base, $q ) {
	return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . http_build_query( $q, '', '&', PHP_QUERY_RFC3986 );
}

/** The address to register at the provider as the redirect URI. */
function mcupr_social_redirect_uri( $p ) {
	return add_query_arg( 'mcupr_oauth', $p, home_url( '/' ) );
}

/** The address a button points to. $args: redirect_to, from (student), link (signed-in person adds this provider). */
function mcupr_social_start_url( $p, $args = array() ) {
	$q = array( 'mcupr_oauth' => $p, 'start' => 1 );
	if ( ! empty( $args['redirect_to'] ) ) {
		$q['redirect_to'] = $args['redirect_to'];
	}
	if ( ! empty( $args['from'] ) ) {
		$q['from'] = 'student';
	}
	if ( ! empty( $args['link'] ) ) {
		$q['link']     = 1;
		$q['_wpnonce'] = wp_create_nonce( 'mcupr_oauth_link' );
	}
	return mcupr_social_url( home_url( '/' ), $q );
}

/* ------------------------------------------------------------------ begin: where to send the browser */

function mcupr_social_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

/**
 * Remember a sign-in attempt and give the provider's address to go to (or a WP_Error).
 * $ctx: redirect_to, from, link_uid.
 */
function mcupr_social_begin( $p, $ctx = array() ) {
	$prov = mcupr_social_provider( $p );
	if ( ! $prov || ! mcupr_social_ready( $p ) ) {
		return new WP_Error( 'off', 'not on' );
	}
	$cfg      = mcupr_social_opts();
	$state    = bin2hex( random_bytes( 16 ) );
	$nonce    = bin2hex( random_bytes( 16 ) );
	$verifier = mcupr_social_b64url( random_bytes( 32 ) );
	$to       = isset( $ctx['redirect_to'] ) ? wp_validate_redirect( (string) $ctx['redirect_to'], '' ) : '';
	set_transient(
		'mcupr_oa_' . $state,
		array(
			'p'        => $p,
			'nonce'    => $nonce,
			'verifier' => $verifier,
			'to'       => $to,
			'from'     => ! empty( $ctx['from'] ) ? 'student' : '',
			'link'     => ! empty( $ctx['link_uid'] ) ? (int) $ctx['link_uid'] : 0,
		),
		10 * MINUTE_IN_SECONDS
	);
	$q = array(
		'client_id'     => $cfg[ $p ]['id'],
		'redirect_uri'  => mcupr_social_redirect_uri( $p ),
		'response_type' => 'code',
		'scope'         => $prov['scope'],
		'state'         => $state,
	);
	if ( ! empty( $prov['oidc'] ) ) {
		$q['nonce'] = $nonce;
	}
	if ( ! empty( $prov['pkce'] ) ) {
		$q['code_challenge']        = mcupr_social_b64url( hash( 'sha256', $verifier, true ) );
		$q['code_challenge_method'] = 'S256';
	}
	if ( 'google' === $p ) {
		$q['prompt'] = 'select_account';
	}
	return array( 'url' => mcupr_social_url( $prov['auth'], $q ), 'state' => $state );
}

/* ------------------------------------------------------------------ finish: from the code to who it is */

function mcupr_social_http( $method, $url, $args = array() ) {
	$args = array_merge( array( 'timeout' => 15, 'redirection' => 0 ), $args );
	$res  = 'GET' === $method ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$d = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $d ) ? $d : null;
}

/** The claims of an ID token received straight from the token endpoint (TLS to the provider is the proof of origin). */
function mcupr_social_claims( $jwt ) {
	$p = explode( '.', (string) $jwt );
	if ( 3 !== count( $p ) ) {
		return null;
	}
	$s = strtr( $p[1], '-_', '+/' );
	$c = json_decode( (string) base64_decode( $s . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	return is_array( $c ) ? $c : null;
}

/** '' when the claims of an ID token are acceptable, else a short reason. */
function mcupr_social_check_claims( $c, $client_id, $nonce, $issuers ) {
	if ( ! empty( $issuers ) && ( empty( $c['iss'] ) || ! in_array( $c['iss'], (array) $issuers, true ) ) ) {
		return 'iss';
	}
	if ( ! in_array( (string) $client_id, array_map( 'strval', isset( $c['aud'] ) ? (array) $c['aud'] : array() ), true ) ) {
		return 'aud';
	}
	if ( empty( $c['exp'] ) || (int) $c['exp'] < time() - 60 ) {
		return 'exp';
	}
	if ( empty( $c['nonce'] ) || ! hash_equals( (string) $nonce, (string) $c['nonce'] ) ) {
		return 'nonce';
	}
	if ( empty( $c['sub'] ) ) {
		return 'sub';
	}
	return '';
}

/**
 * Trade the code for who the person is.  Returns array( sub, email, verified, name ) or a WP_Error (code = a short reason).
 * The ThaID national ID number only ever leaves here as a keyed hash.
 */
function mcupr_social_identity( $p, $code, $st ) {
	$prov = mcupr_social_provider( $p );
	$cfg  = mcupr_social_opts();
	$id   = $cfg[ $p ]['id'];
	$sec  = $cfg[ $p ]['secret'];
	$uri  = mcupr_social_redirect_uri( $p );
	$body = array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $uri );
	$head = array();
	if ( ! empty( $prov['basic'] ) ) {
		$head['Authorization'] = 'Basic ' . base64_encode( $id . ':' . $sec ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	} else {
		$body['client_id']     = $id;
		$body['client_secret'] = $sec;
	}
	if ( ! empty( $prov['pkce'] ) ) {
		$body['code_verifier'] = $st['verifier'];
	}
	if ( 'facebook' === $p ) {
		$tok = mcupr_social_http( 'GET', mcupr_social_url( $prov['token'], array( 'client_id' => $id, 'client_secret' => $sec, 'redirect_uri' => $uri, 'code' => $code ) ) );
	} else {
		$tok = mcupr_social_http( 'POST', $prov['token'], array( 'body' => $body, 'headers' => $head ) );
	}
	if ( ! $tok ) {
		return new WP_Error( 'token', 'token' );
	}
	if ( 'facebook' === $p ) {
		if ( empty( $tok['access_token'] ) ) {
			return new WP_Error( 'token', 'token' );
		}
		$me = mcupr_social_http(
			'GET',
			mcupr_social_url(
				$prov['me'],
				array(
					'fields'          => 'id,name,email',
					'access_token'    => $tok['access_token'],
					'appsecret_proof' => hash_hmac( 'sha256', $tok['access_token'], $sec ),
				)
			)
		);
		if ( ! $me || empty( $me['id'] ) ) {
			return new WP_Error( 'profile', 'profile' );
		}
		return array( 'sub' => (string) $me['id'], 'email' => isset( $me['email'] ) && is_email( $me['email'] ) ? strtolower( $me['email'] ) : '', 'verified' => false, 'name' => isset( $me['name'] ) ? (string) $me['name'] : '' );
	}
	if ( empty( $tok['id_token'] ) ) {
		return new WP_Error( 'token', 'token' );
	}
	if ( 'line' === $p ) { // LINE checks the token itself
		$c = mcupr_social_http( 'POST', $prov['verify'], array( 'body' => array( 'id_token' => $tok['id_token'], 'client_id' => $id, 'nonce' => $st['nonce'] ) ) );
		if ( ! $c || empty( $c['sub'] ) || ! in_array( (string) $id, array_map( 'strval', isset( $c['aud'] ) ? (array) $c['aud'] : array() ), true ) ) {
			return new WP_Error( 'aud', 'aud' );
		}
		return array( 'sub' => (string) $c['sub'], 'email' => isset( $c['email'] ) && is_email( $c['email'] ) ? strtolower( $c['email'] ) : '', 'verified' => false, 'name' => isset( $c['name'] ) ? (string) $c['name'] : '' );
	}
	$c = mcupr_social_claims( $tok['id_token'] );
	if ( ! $c ) {
		return new WP_Error( 'jwt', 'jwt' );
	}
	if ( 'thaid' === $p ) { // not OpenID Connect proper: no nonce; the identity is the national ID number, kept only as a keyed hash
		$pid = isset( $c['pid'] ) ? preg_replace( '/\D/', '', (string) $c['pid'] ) : ( isset( $tok['pid'] ) ? preg_replace( '/\D/', '', (string) $tok['pid'] ) : '' );
		if ( strlen( $pid ) < 10 ) {
			return new WP_Error( 'pid', 'pid' );
		}
		$name = trim( ( isset( $c['given_name'] ) ? $c['given_name'] : '' ) . ' ' . ( isset( $c['family_name'] ) ? $c['family_name'] : '' ) );
		return array( 'sub' => hash_hmac( 'sha256', 'thaid:' . $pid, wp_salt( 'auth' ) ), 'email' => '', 'verified' => false, 'name' => $name );
	}
	$bad = mcupr_social_check_claims( $c, $id, $st['nonce'], isset( $prov['issuer'] ) ? $prov['issuer'] : array() );
	if ( '' !== $bad ) {
		return new WP_Error( $bad, $bad );
	}
	$mail = isset( $c['email'] ) && is_email( $c['email'] ) ? strtolower( $c['email'] ) : '';
	return array( 'sub' => (string) $c['sub'], 'email' => $mail, 'verified' => '' !== $mail && ( true === $c['email_verified'] || 'true' === ( isset( $c['email_verified'] ) ? $c['email_verified'] : '' ) ), 'name' => isset( $c['name'] ) ? (string) $c['name'] : '' );
}

/* ------------------------------------------------------------------ who it is on this website */

function mcupr_social_meta_key( $p ) {
	return 'mcupr_social_' . $p;
}

/** The user linked to this provider account, or 0. */
function mcupr_social_user_by_sub( $p, $sub ) {
	$u = get_users( array( 'meta_key' => mcupr_social_meta_key( $p ), 'meta_value' => (string) $sub, 'number' => 1, 'fields' => 'ID' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $u ? (int) $u[0] : 0;
}

/** Staff never sign in this way. */
function mcupr_social_is_staff( $user ) {
	return $user && ( user_can( $user, 'edit_posts' ) || user_can( $user, 'edit_pages' ) || user_can( $user, 'manage_options' ) || user_can( $user, 'upload_files' ) );
}

/**
 * Identity → the user id to sign in, or a WP_Error (code: staff, taken, exists, closed, account).
 * Returns array( uid, how ) where how = known | linked | student | joined | new.
 */
function mcupr_social_resolve( $p, $idn, $link_uid = 0 ) {
	$key = mcupr_social_meta_key( $p );
	$sub = (string) $idn['sub'];
	$own = mcupr_social_user_by_sub( $p, $sub );
	// a signed-in person adds this account to theirs
	if ( $link_uid ) {
		$u = get_userdata( $link_uid );
		if ( ! $u || mcupr_social_is_staff( $u ) ) {
			return new WP_Error( 'staff', 'staff' );
		}
		if ( $own && $own !== (int) $link_uid ) {
			return new WP_Error( 'taken', 'taken' );
		}
		update_user_meta( $link_uid, $key, $sub );
		return array( (int) $link_uid, 'linked' );
	}
	if ( $own ) {
		$u = get_userdata( $own );
		if ( ! $u || mcupr_social_is_staff( $u ) ) {
			return new WP_Error( 'staff', 'staff' );
		}
		return array( $own, 'known' );
	}
	// a student: the e-mail the provider vouches for is the one in the student record
	if ( $idn['verified'] && '' !== $idn['email'] && function_exists( 'mcupr_sso_resolve' ) ) {
		$who = mcupr_sso_resolve( array( 'email' => $idn['email'], 'sub' => 'google' === $p ? $sub : 'social:' . $p . ':' . $sub ) );
		if ( is_array( $who ) ) {
			$su = get_userdata( $who[0] );
			if ( $su && ! mcupr_social_is_staff( $su ) ) {
				update_user_meta( $who[0], $key, $sub );
				return array( (int) $who[0], 'student' );
			}
		}
	}
	// an existing member with that (vouched for) e-mail
	if ( $idn['verified'] && '' !== $idn['email'] ) {
		$ex = email_exists( $idn['email'] );
		if ( $ex ) {
			$eu = get_userdata( $ex );
			if ( ! $eu || mcupr_social_is_staff( $eu ) || ( function_exists( 'mcupr_is_student_user' ) && mcupr_is_student_user( $ex ) ) ) { // a student account is only reached through the student record
				return new WP_Error( 'exists', 'exists' );
			}
			update_user_meta( $ex, $key, $sub );
			return array( (int) $ex, 'joined' );
		}
	}
	$o = mcupr_social_opts();
	if ( ! $o['auto'] ) {
		return new WP_Error( 'closed', 'closed' );
	}
	$h     = substr( hash_hmac( 'sha256', $p . ':' . $sub, wp_salt( 'auth' ) ), 0, 12 );
	$login = $p . '_' . $h;
	$mail  = $idn['verified'] && '' !== $idn['email'] ? $idn['email'] : $p . '-' . $h . '@members.invalid';
	$prov  = mcupr_social_provider( $p );
	$name  = '' !== trim( $idn['name'] ) ? sanitize_text_field( $idn['name'] ) : $prov['label'] . ' ' . substr( $h, 0, 4 );
	$uid   = wp_insert_user(
		array(
			'user_login'           => $login,
			'user_pass'            => wp_generate_password( 40, true, true ),
			'user_email'           => $mail,
			'display_name'         => $name,
			'nickname'             => $name,
			'role'                 => 'subscriber',
			'show_admin_bar_front' => 'false',
		)
	);
	if ( is_wp_error( $uid ) ) {
		return new WP_Error( 'account', 'account' );
	}
	update_user_meta( $uid, $key, $sub );
	update_user_meta( $uid, 'mcupr_social_member', 1 );
	return array( (int) $uid, 'new' );
}

/**
 * The whole return trip, without touching the browser: the callback's parameters + the cookie → user id and where to go.
 * Returns array( uid, to, how, from ) or a WP_Error.
 */
function mcupr_social_finish( $p, $get, $cookie_state ) {
	$state = isset( $get['state'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $get['state'] ) : '';
	$st    = '' !== $state ? get_transient( 'mcupr_oa_' . $state ) : false;
	if ( '' !== $state ) {
		delete_transient( 'mcupr_oa_' . $state ); // one time only
	}
	$cookie = preg_replace( '/[^a-f0-9]/', '', (string) $cookie_state );
	if ( '' === $state || '' === $cookie || ! hash_equals( $state, $cookie ) || ! is_array( $st ) || $st['p'] !== $p ) {
		return new WP_Error( 'state', 'state' );
	}
	if ( ! mcupr_social_ready( $p ) ) {
		return new WP_Error( 'off', 'off' );
	}
	if ( isset( $get['error'] ) || empty( $get['code'] ) ) {
		return new WP_Error( 'denied', 'denied' );
	}
	$idn = mcupr_social_identity( $p, sanitize_text_field( (string) $get['code'] ), $st );
	if ( is_wp_error( $idn ) ) {
		return $idn;
	}
	$who = mcupr_social_resolve( $p, $idn, $st['link'] );
	if ( is_wp_error( $who ) ) {
		return $who;
	}
	return array( 'uid' => $who[0], 'how' => $who[1], 'to' => $st['to'], 'from' => $st['from'] );
}

/* ------------------------------------------------------------------ the two requests */

/** Messages for the sign-in page. */
function mcupr_social_error_text( $code ) {
	$map = array(
		'state'   => __( 'The sign-in took too long or was interrupted. Please try again.', 'mcupr' ),
		'denied'  => __( 'The sign-in was cancelled.', 'mcupr' ),
		'staff'   => __( 'Staff sign in with their password.', 'mcupr' ),
		'taken'   => __( 'That account is already linked to another person here.', 'mcupr' ),
		'exists'  => __( 'There is already an account with that e-mail. Sign in with its password, then link this account on your profile page.', 'mcupr' ),
		'closed'  => __( 'New accounts cannot be made this way. Use an account you already have.', 'mcupr' ),
		'wait'    => __( 'Too many tries. Please wait a few minutes.', 'mcupr' ),
		'pid'     => __( 'ThaID did not return the identity that is needed.', 'mcupr' ),
	);
	return isset( $map[ $code ] ) ? $map[ $code ] : __( 'Signing in with that account did not work. Please try again or use your password.', 'mcupr' );
}

add_action(
	'init',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification
		$p = isset( $_GET['mcupr_oauth'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_oauth'] ) ) : '';
		// phpcs:enable
		if ( '' === $p || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		nocache_headers();
		$fail = static function ( $code, $from = '' ) {
			mcupr_audit( 'socialfail', array( 'note' => sanitize_key( $code ) ) );
			if ( 'student' === $from && function_exists( 'mcupr_student_portal_url' ) ) {
				wp_safe_redirect( mcupr_student_portal_url( array( 'e' => 'sso' ) ) );
			} else {
				wp_safe_redirect( add_query_arg( 'mcupr_social_err', rawurlencode( $code ), wp_login_url() ) );
			}
			exit;
		};
		if ( ! mcupr_social_ready( $p ) ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}
		if ( function_exists( 'mcupr_stu_throttle' ) && ! mcupr_stu_throttle( 'oauth|' . mcupr_stu_ip(), 120, 15 * MINUTE_IN_SECONDS ) ) {
			$fail( 'wait' );
		}
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['start'] ) ) {
			$link = ! empty( $_GET['link'] );
			if ( $link ) {
				if ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mcupr_oauth_link' ) ) {
					wp_safe_redirect( wp_login_url() );
					exit;
				}
			} elseif ( is_user_logged_in() ) {
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
			$r = mcupr_social_begin(
				$p,
				array(
					'redirect_to' => isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '',
					'from'        => isset( $_GET['from'] ) && 'student' === $_GET['from'],
					'link_uid'    => $link ? get_current_user_id() : 0,
				)
			);
			if ( is_wp_error( $r ) ) {
				$fail( 'off' );
			}
			mcupr_set_cookie( 'mcupr_oa_state', $r['state'], time() + 600 );
			wp_redirect( $r['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- the provider's own address
			exit;
		}
		$get    = array_map( 'wp_unslash', array_intersect_key( $_GET, array_flip( array( 'state', 'code', 'error' ) ) ) );
		$cookie = isset( $_COOKIE['mcupr_oa_state'] ) ? wp_unslash( $_COOKIE['mcupr_oa_state'] ) : '';
		// phpcs:enable
		mcupr_set_cookie( 'mcupr_oa_state', '', time() - 3600 );
		$res = mcupr_social_finish( $p, $get, $cookie );
		if ( is_wp_error( $res ) ) {
			$fail( $res->get_error_code() );
		}
		$user = get_userdata( $res['uid'] );
		if ( 'linked' === $res['how'] ) { // a signed-in person added an account: back to the profile
			mcupr_audit( 'sociallink', array( 'note' => $p ) );
			wp_safe_redirect( admin_url( 'profile.php?mcupr_linked=' . rawurlencode( $p ) ) );
			exit;
		}
		wp_set_current_user( $res['uid'] );
		wp_set_auth_cookie( $res['uid'], true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		mcupr_audit( 'social', array( 'note' => $p . ' ' . $res['how'] ) );
		if ( function_exists( 'mcupr_is_student_user' ) && mcupr_is_student_user( $res['uid'] ) && function_exists( 'mcupr_student_portal_url' ) ) {
			wp_safe_redirect( mcupr_student_portal_url() );
		} else {
			wp_safe_redirect( '' !== $res['to'] ? $res['to'] : home_url( '/' ) );
		}
		exit;
	},
	1
);

/* ------------------------------------------------------------------ the buttons */

/**
 * The buttons of the providers that are on.  $args: redirect_to, from (student), skip (provider keys not to show), divider.
 */
function mcupr_social_buttons( $args = array() ) {
	$on = array_diff( mcupr_social_enabled(), isset( $args['skip'] ) ? (array) $args['skip'] : array() );
	if ( ! $on ) {
		return '';
	}
	$all = mcupr_social_providers();
	$h   = '<div class="social-login"><div class="social-btns">';
	foreach ( $on as $p ) {
		$h .= '<a class="social-btn social-' . esc_attr( $p ) . '" href="' . esc_url( mcupr_social_start_url( $p, $args ) ) . '" rel="nofollow"><b aria-hidden="true" style="background:' . esc_attr( $all[ $p ]['color'] ) . '">' . esc_html( $all[ $p ]['mark'] ) . '</b><span>' . esc_html( sprintf( mcupr_t( 'soc_with' ), $all[ $p ]['label'] ) ) . '</span></a>';
	}
	$h .= '</div>';
	if ( ! isset( $args['divider'] ) || $args['divider'] ) {
		$h .= '<p class="social-or"><span>' . esc_html( mcupr_t( 'soc_or' ) ) . '</span></p>';
	}
	return $h . '</div>';
}

// inside the sign-in form, the buttons first (the skin moves them to the top)
add_action(
	'login_form',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification
		$to = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		echo mcupr_social_buttons( array( 'redirect_to' => $to ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

add_filter(
	'wp_login_errors',
	static function ( $errors ) {
		if ( isset( $_GET['mcupr_social_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$errors->add( 'mcupr_social', esc_html( mcupr_social_error_text( sanitize_key( wp_unslash( $_GET['mcupr_social_err'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $errors;
	}
);

/* ------------------------------------------------------------------ the profile: what is linked */

add_action( 'show_user_profile', 'mcupr_social_profile_box' );

function mcupr_social_profile_box( $user ) {
	$on = mcupr_social_enabled();
	if ( ! $on || mcupr_social_is_staff( $user ) ) {
		return;
	}
	$all = mcupr_social_providers();
	echo '<h2>' . esc_html__( 'Linked accounts', 'mcupr' ) . '</h2><p class="description">' . esc_html__( 'Link an account to sign in with one press next time.', 'mcupr' ) . '</p><table class="form-table" role="presentation"><tbody>';
	foreach ( $on as $p ) {
		$has = '' !== (string) get_user_meta( $user->ID, mcupr_social_meta_key( $p ), true );
		echo '<tr><th scope="row">' . esc_html( $all[ $p ]['label'] ) . '</th><td>';
		if ( $has ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_social_unlink&p=' . rawurlencode( $p ) ), 'mcupr_social_unlink' );
			echo '<span class="description">' . esc_html__( 'Linked', 'mcupr' ) . '</span> <a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Unlink', 'mcupr' ) . '</a>';
		} else {
			echo '<a class="button" href="' . esc_url( mcupr_social_start_url( $p, array( 'link' => 1 ) ) ) . '">' . esc_html__( 'Link', 'mcupr' ) . '</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'admin_post_mcupr_social_unlink',
	static function () {
		if ( ! is_user_logged_in() || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mcupr_social_unlink' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$p = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : '';
		if ( isset( mcupr_social_providers()[ $p ] ) ) {
			delete_user_meta( get_current_user_id(), mcupr_social_meta_key( $p ) );
		}
		wp_safe_redirect( admin_url( 'profile.php' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ the settings page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Social sign-in', 'mcupr' ), __( 'Social sign-in', 'mcupr' ), 'mcupr_manage_site_access', 'mcupr-social', 'mcupr_social_page' );
	}
);

function mcupr_social_page() {
	if ( ! current_user_can( 'mcupr_manage_site_access' ) ) {
		return;
	}
	$o   = mcupr_social_opts();
	$all = mcupr_social_providers();
	echo '<div class="wrap"><h1>' . esc_html__( 'Social sign-in', 'mcupr' ) . '</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<p style="max-width:56rem">' . esc_html__( 'Visitors and students can sign in with Google, Facebook, LINE or ThaID. For each one, make an app at the provider, give it the redirect address shown below, and paste the Client ID and secret here. Staff always sign in with their password.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_social_save">';
	wp_nonce_field( 'mcupr_social_save', 'mcupr_social_nonce' );
	foreach ( $all as $p => $prov ) {
		$c     = $o[ $p ];
		$state = mcupr_social_ready( $p ) ? array( 'ok', __( 'Ready', 'mcupr' ) ) : ( $c['on'] ? array( 'wn', __( 'Needs a Client ID and secret', 'mcupr' ) ) : array( 'no', __( 'Off', 'mcupr' ) ) );
		echo '<div class="card" style="max-width:56rem;padding:.8rem 1.25rem;margin-bottom:12px"><h2 style="margin:.2em 0"><span style="display:inline-grid;place-items:center;width:26px;height:26px;border-radius:50%;background:' . esc_attr( $prov['color'] ) . ';color:#fff;font-size:12px;vertical-align:middle">' . esc_html( $prov['mark'] ) . '</span> ' . esc_html( $prov['label'] ) . ' <small style="font-weight:400;color:#646970">' . esc_html( $state[1] ) . '</small></h2>';
		echo '<p><label><input type="checkbox" name="' . esc_attr( $p ) . '[on]" value="1"' . checked( $c['on'], 1, false ) . '> ' . esc_html__( 'On', 'mcupr' ) . '</label></p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="soc-' . esc_attr( $p ) . '-id">' . esc_html__( 'Client ID', 'mcupr' ) . '</label></th><td><input id="soc-' . esc_attr( $p ) . '-id" name="' . esc_attr( $p ) . '[id]" type="text" class="large-text" autocomplete="off" value="' . esc_attr( $c['id'] ) . '"></td></tr>';
		$const = 'MCUPR_SOCIAL_' . strtoupper( $p ) . '_SECRET';
		echo '<tr><th scope="row"><label for="soc-' . esc_attr( $p ) . '-secret">' . esc_html__( 'Client secret', 'mcupr' ) . '</label></th><td>';
		if ( defined( $const ) && '' !== constant( $const ) ) {
			echo '<span class="description">' . esc_html( sprintf( /* translators: %s: constant */ __( 'Set in wp-config.php (%s).', 'mcupr' ), $const ) ) . '</span>';
		} else {
			echo '<input id="soc-' . esc_attr( $p ) . '-secret" name="' . esc_attr( $p ) . '[secret]" type="password" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( '' !== $c['secret'] ? __( 'Saved – leave empty to keep it', 'mcupr' ) : '' ) . '">';
		}
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Redirect address', 'mcupr' ) . '</th><td><code id="soc-' . esc_attr( $p ) . '-uri">' . esc_html( mcupr_social_redirect_uri( $p ) ) . '</code> <button type="button" class="button" onclick="navigator.clipboard&amp;&amp;navigator.clipboard.writeText(document.getElementById(\'soc-' . esc_attr( $p ) . '-uri\').textContent)">' . esc_html__( 'Copy', 'mcupr' ) . '</button></td></tr>';
		if ( 'thaid' === $p ) {
			echo '<tr><th scope="row">' . esc_html__( 'Addresses and scope', 'mcupr' ) . '</th><td><p class="description">' . esc_html__( 'ThaID is available to government and approved organisations: ask DGA for access first, and use the addresses and scope you are given. The national ID number is never stored, only a keyed hash of it.', 'mcupr' ) . '</p>';
			echo '<p><input name="thaid[auth]" type="url" class="large-text" placeholder="' . esc_attr( $prov['auth'] ) . '" value="' . esc_attr( $c['auth'] ) . '"></p><p><input name="thaid[token]" type="url" class="large-text" placeholder="' . esc_attr( $prov['token'] ) . '" value="' . esc_attr( $c['token'] ) . '"></p><p><input name="thaid[scope]" type="text" class="large-text" placeholder="' . esc_attr( $prov['scope'] ) . '" value="' . esc_attr( $c['scope'] ) . '"></p></td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '<p><label><input type="checkbox" name="auto" value="1"' . checked( $o['auto'], 1, false ) . '> <strong>' . esc_html__( 'Make a member account for people who sign in for the first time', 'mcupr' ) . '</strong></label><br><span class="description">' . esc_html__( 'A member can read, comment, post in the forum and send forms. Off: only people who already have an account can sign in this way.', 'mcupr' ) . '</span></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form></div>';
}

/** Clean and store the form (separate so tests can call it). A secret left empty is kept. */
function mcupr_social_save( $p_in ) {
	$prev = get_option( 'mcupr_social', array() );
	$new  = array( 'auto' => ! empty( $p_in['auto'] ) ? 1 : 0 );
	foreach ( array_keys( mcupr_social_providers() ) as $p ) {
		$in     = isset( $p_in[ $p ] ) && is_array( $p_in[ $p ] ) ? $p_in[ $p ] : array();
		$secret = isset( $in['secret'] ) ? trim( (string) $in['secret'] ) : '';
		$kept   = isset( $prev[ $p ]['secret'] ) ? (string) $prev[ $p ]['secret'] : '';
		$new[ $p ] = array(
			'on'     => ! empty( $in['on'] ) ? 1 : 0,
			'id'     => isset( $in['id'] ) ? sanitize_text_field( $in['id'] ) : '',
			'secret' => '' !== $secret ? $secret : $kept,
		);
		if ( 'thaid' === $p ) {
			$new[ $p ]['auth']  = isset( $in['auth'] ) && 0 === strpos( trim( (string) $in['auth'] ), 'https://' ) ? esc_url_raw( trim( $in['auth'] ) ) : '';
			$new[ $p ]['token'] = isset( $in['token'] ) && 0 === strpos( trim( (string) $in['token'] ), 'https://' ) ? esc_url_raw( trim( $in['token'] ) ) : '';
			$new[ $p ]['scope'] = isset( $in['scope'] ) ? sanitize_text_field( $in['scope'] ) : '';
		}
	}
	update_option( 'mcupr_social', $new, false );
	mcupr_audit( 'settings', array( 'note' => 'social sign-in' ) );
	return $new;
}

add_action(
	'admin_post_mcupr_social_save',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_social_nonce', 'mcupr_social_save', 'mcupr_manage_site_access' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		mcupr_social_save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-social&saved=1' ) );
		exit;
	}
);
