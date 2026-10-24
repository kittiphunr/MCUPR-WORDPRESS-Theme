<?php
/**
 * Minimum-version guard: on an unsupported WordPress / PHP the theme does not
 * load; activation is rolled back to the previous theme with an explanation.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_is_supported() {
	global $wp_version;
	return version_compare( PHP_VERSION, MCUPR_MIN_PHP, '>=' ) && version_compare( $wp_version, MCUPR_MIN_WP, '>=' );
}

function mcupr_compat_message() {
	global $wp_version;
	return sprintf(
		/* translators: 1: required WP, 2: required PHP, 3: current WP, 4: current PHP */
		__( 'The MCUPR theme needs WordPress %1$s and PHP %2$s or newer. You are running WordPress %3$s and PHP %4$s.', 'mcupr' ),
		MCUPR_MIN_WP,
		MCUPR_MIN_PHP,
		$wp_version,
		PHP_VERSION
	);
}

if ( ! mcupr_is_supported() ) {
	add_action(
		'after_switch_theme',
		static function () {
			switch_theme( get_option( 'theme_switched' ) ? get_option( 'theme_switched' ) : WP_DEFAULT_THEME );
			unset( $_GET['activated'] );
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html( mcupr_compat_message() ) . '</p></div>';
				}
			);
		}
	);
	add_action(
		'template_redirect',
		static function () {
			wp_die( esc_html( mcupr_compat_message() ) );
		}
	);
}

/** After activation: refresh permalinks so the theme's pages work at once. */
add_action(
	'after_switch_theme',
	static function () {
		if ( mcupr_is_supported() ) {
			flush_rewrite_rules( false );
		}
	}
);

/*
 * Link rules: every module registers its rules on init as before and says which version of them it wants with
 * mcupr_rewrite_wanted(). One flush runs at the end of init (priority 999), and only when a wanted version differs
 * from the one stored – so an update costs one flush instead of one per module, and an unchanged site none.
 * The key of each module is the name of the option it used to keep its own version in, so a site that was
 * already flushed by an older theme version is not flushed again for nothing.
 */
function mcupr_rewrite_wanted( $key, $version ) {
	$GLOBALS['mcupr_rewrite_wanted'][ (string) $key ] = (string) $version;
}

/** Pure: true when any wanted version is missing from $stored or different there. */
function mcupr_rewrite_needs_flush( array $wanted, $stored ) {
	$stored = is_array( $stored ) ? $stored : array();
	foreach ( $wanted as $k => $v ) {
		if ( ! isset( $stored[ $k ] ) || (string) $stored[ $k ] !== (string) $v ) {
			return true;
		}
	}
	return false;
}

/** Pure: what to store after a check – the stored versions with the wanted ones over them. */
function mcupr_rewrite_merge( array $wanted, $stored ) {
	$stored = is_array( $stored ) ? $stored : array();
	foreach ( $wanted as $k => $v ) {
		$stored[ (string) $k ] = (string) $v;
	}
	return $stored;
}

/** The single flush (true when it flushed). */
function mcupr_rewrite_flush_if_wanted() {
	$wanted = isset( $GLOBALS['mcupr_rewrite_wanted'] ) && is_array( $GLOBALS['mcupr_rewrite_wanted'] ) ? $GLOBALS['mcupr_rewrite_wanted'] : array();
	if ( ! $wanted ) {
		return false;
	}
	$stored = get_option( 'mcupr_rewrite_versions' );
	$fresh  = ! is_array( $stored );
	if ( $fresh ) {
		// first run of this mechanism: the versions the modules stored themselves until now
		$stored = array();
		foreach ( array_keys( $wanted ) as $k ) {
			$old = get_option( $k );
			if ( false !== $old && '' !== (string) $old ) {
				$stored[ $k ] = (string) $old;
			}
		}
	}
	$flush = mcupr_rewrite_needs_flush( $wanted, $stored );
	if ( $flush ) {
		flush_rewrite_rules( false );
	}
	if ( $flush || $fresh ) {
		update_option( 'mcupr_rewrite_versions', mcupr_rewrite_merge( $wanted, $stored ), true );
	}
	return $flush;
}
add_action( 'init', 'mcupr_rewrite_flush_if_wanted', 999 );

/**
 * The "escape" character for fgetcsv() / fputcsv(). PHP 7.4 and later accept '' (no escape character), but PHP 7.0 – 7.3 want exactly
 * one character and otherwise warn and return false – which made a good file look "empty". NUL never occurs in a CSV, so it is safe everywhere.
 */
function mcupr_csv_escape() {
	return "\0";
}

/** A CSV value that a spreadsheet will not run as a formula: a leading = + - @ tab or CR gets a ' in front. Unquoted (for fputcsv). */
function mcupr_csv_safe( $s ) {
	$s = (string) $s;
	return '' !== $s && in_array( $s[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $s : $s;
}

/** The way back: a cell that mcupr_csv_safe() prefixed with ' is read as it was typed (so an exported "+66…" phone number imports unchanged). */
function mcupr_csv_unsafe( $s ) {
	return preg_replace( "/^'(?=[=+\\-@\\t\\r])/", '', (string) $s );
}

/** One quoted CSV cell that Excel will not run as a formula. */
function mcupr_csv_cell( $s ) {
	return '"' . str_replace( '"', '""', mcupr_csv_safe( $s ) ) . '"';
}

/** The visitor's network for rate limits: the address, with an IPv6 address cut to its /64 (one household or one rented block has a whole /64 to rotate in). */
function mcupr_client_net() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( false !== strpos( $ip, ':' ) && function_exists( 'inet_pton' ) ) {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false !== $bin && 16 === strlen( $bin ) ) {
			return bin2hex( substr( $bin, 0, 8 ) ) . '::/64';
		}
	}
	return $ip;
}

/**
 * A simple rate limit: true and one more counted while fewer than $max were counted for $key in the last $ttl seconds
 * (the window starts again with each counted try), false once the limit is reached (nothing is counted then).
 */
function mcupr_throttle( $key, $max, $ttl ) {
	$k = 'mcupr_thr_' . md5( $key );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $k, $n + 1, $ttl );
	return true;
}

/** A robot, a link preview or a script rather than a person (no user agent at all counts as a robot): views, the welcome page, insights. */
function mcupr_is_bot() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return '' === $ua || (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|mediapartners|facebookexternalhit|lighthouse|pagespeed|headless|preview|monitor|curl|wget|python-requests/i', $ua );
}

/** The value for the visitor's language from array( 'th' => …, 'en' => … ), else the English one, else $fallback (a key that is set counts, even when empty). */
function mcupr_pick_lang( array $by_lang, $fallback = '' ) {
	$lang = mcupr_ui_lang();
	if ( isset( $by_lang[ $lang ] ) ) {
		return $by_lang[ $lang ];
	}
	return isset( $by_lang['en'] ) ? $by_lang['en'] : $fallback;
}

/**
 * A form or link sent by this site and allowed for this user: the capability (when one is given), then the nonce in
 * $_POST[ $field ] ($src 'post') or $_GET[ $field ] ($src 'get') checked against $action.
 */
function mcupr_check_request( $field, $action, $cap = '', $src = 'post' ) {
	if ( '' !== $cap && ! current_user_can( $cap ) ) {
		return false;
	}
	$in = 'get' === $src ? $_GET : $_POST; // phpcs:ignore WordPress.Security.NonceVerification -- this is the nonce check
	return isset( $in[ $field ] ) && false !== wp_verify_nonce( sanitize_text_field( wp_unslash( $in[ $field ] ) ), $action );
}

/**
 * The headers every page of the site carries.
 *
 * nosniff: the browser takes the content type we send and does not guess another one.
 * Referrer-Policy: a visitor who follows a link away takes the host with them, not the whole address, and nothing at
 *   all when leaving https for http — so an address that carries a request number or a search does not travel.
 * Permissions-Policy: the theme never asks for the camera, the microphone, the place the visitor is or a payment, so
 *   the page says it does not want them either (which also stops anything embedded in it from asking).
 * X-Frame-Options: another site may not put a page of ours inside its own frame — except an embed, which exists to be
 *   shown inside another site's page.
 *
 * There is deliberately no Content-Security-Policy: one that is any use has to name every script and style the site
 * really loads (its plugins, the fonts, what was built with Elementor), so a policy written blind here would quietly
 * break pages. Add one through the filter when you know what a site loads.
 */
add_action(
	'send_headers',
	static function ( $wp = null ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$h = array(
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',
		);
		if ( ! is_object( $wp ) || empty( $wp->query_vars['embed'] ) ) {
			$h['X-Frame-Options'] = 'SAMEORIGIN';
		}
		$h = (array) apply_filters( 'mcupr_security_headers', $h );
		if ( headers_sent() ) {
			return;
		}
		foreach ( $h as $name => $value ) {
			if ( is_string( $name ) && '' !== (string) $value ) {
				header( $name . ': ' . $value, true );
			}
		}
	}
);

/**
 * A folder for files that hold personal data (what a request brought with it, what a mail job will attach).
 *
 * Outside the web root when the server lets us write there: then nothing can ask the web server for the file at all,
 * which is the only protection that does not depend on which web server is running. Only if that is not possible does
 * it fall back to a folder inside uploads with index.php and an .htaccess, and that .htaccess is read by Apache alone
 * — on nginx or LiteSpeed it does nothing, so there the unguessable folder name is all that stands between the file and
 * the internet. Both are written again if somebody removed them.
 *
 * mcupr_private_bases() gives the same folder and the one inside uploads, newest first: a site that kept its files in
 * uploads before this goes on finding them there.
 */
function mcupr_private_dir( $name ) {
	$name = preg_replace( '/[^a-z-]/', '', (string) $name );
	$out  = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'mcupr-private-' . $name;
	if ( is_dir( $out ) ? wp_is_writable( $out ) : ( wp_is_writable( dirname( $out ) ) && wp_mkdir_p( $out ) && wp_is_writable( $out ) ) ) {
		return untrailingslashit( $out );
	}
	$up  = wp_upload_dir();
	$dir = untrailingslashit( trailingslashit( $up['basedir'] ) . 'mcupr-' . $name );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/** Where files of this kind may be: the folder in use first, then the older one inside uploads. */
function mcupr_private_bases( $name ) {
	$up  = wp_upload_dir();
	$now = mcupr_private_dir( $name );
	$old = untrailingslashit( trailingslashit( $up['basedir'] ) . 'mcupr-' . preg_replace( '/[^a-z-]/', '', (string) $name ) );
	return $now === $old ? array( $now ) : array( $now, $old );
}

/** The folder of one token inside mcupr_private_dir( $name ): the one that is already there, else the one to write in. */
function mcupr_private_token_dir( $name, $token ) {
	$token = preg_replace( '/[^a-f0-9]/', '', (string) $token );
	if ( '' === $token ) {
		return '';
	}
	$bases = mcupr_private_bases( $name );
	foreach ( $bases as $base ) {
		if ( is_dir( $base . '/' . $token ) ) {
			return $base . '/' . $token;
		}
	}
	return $bases[0] . '/' . $token;
}

/** Is this real path inside any of the folders files of this kind may be in? */
function mcupr_private_path_ok( $real, $name ) {
	foreach ( mcupr_private_bases( $name ) as $base ) {
		$b = realpath( $base );
		if ( $b && mcupr_path_inside( $real, $b ) ) {
			return true;
		}
	}
	return false;
}

/** Is the real path $real inside the folder $base (both already through realpath())? A sibling such as uploads-old/ is not. */
function mcupr_path_inside( $real, $base ) {
	if ( ! $real || ! $base ) {
		return false;
	}
	$real = wp_normalize_path( $real );
	$base = trailingslashit( wp_normalize_path( $base ) );
	return 0 === strpos( $real, $base );
}

/** A meta box may be saved: its nonce is good, this is not an autosave, and the user may edit the post. */
function mcupr_can_save_box( $post_id, $field, $action ) {
	return mcupr_check_request( $field, $action ) && ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) && current_user_can( 'edit_post', $post_id );
}

/** setcookie() for every PHP version the theme supports (7.0 – 7.2 have no options array): first-party, SameSite=Lax, path / unless one is given. */
function mcupr_set_cookie( $name, $value, $expires, $httponly = true, $path = '/' ) {
	if ( headers_sent() ) {
		return;
	}
	if ( PHP_VERSION_ID >= 70300 ) {
		setcookie( $name, $value, array( 'expires' => $expires, 'path' => $path, 'secure' => is_ssl(), 'httponly' => $httponly, 'samesite' => 'Lax' ) );
	} else {
		// PHP < 7.3 has no options array (and rejects ';' in the path), so send the header ourselves.
		header(
			sprintf(
				'Set-Cookie: %s=%s%s; Path=%s; %sSameSite=Lax%s',
				$name,
				rawurlencode( (string) $value ),
				$expires > 0 ? '; Expires=' . gmdate( 'D, d M Y H:i:s \G\M\T', $expires ) . '; Max-Age=' . max( 0, $expires - time() ) : '',
				$path,
				$httponly ? 'HttpOnly; ' : '',
				is_ssl() ? '; Secure' : ''
			),
			false
		);
	}
}
