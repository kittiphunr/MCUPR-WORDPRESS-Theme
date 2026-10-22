<?php
/**
 * The sign-in page: dressed like the theme, and its address can be changed.
 *
 * With a private address switched on (Control center > System > Sign-in address), wp-login.php and the
 * sign-in side of wp-admin answer "not found" to anyone who is not signed in; the sign-in form lives at
 * /<the address> instead.  Nothing is stored anywhere but the one option, and one line in wp-config.php
 * (define( 'MCUPR_LOGIN_OFF', true );) switches the whole thing off if the address is ever forgotten.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ the address */

/** Names a private address must not take: WordPress paths and the usual guesses. */
function mcupr_login_reserved() {
	return array( 'wp-admin', 'wp-login', 'wp-login.php', 'wp-content', 'wp-includes', 'wp-json', 'wp-register', 'wp-signup', 'wp-activate', 'wp-cron', 'xmlrpc', 'admin', 'login', 'signin', 'sign-in', 'dashboard', 'feed', 'comments', 'embed', 'sitemap', 'robots', 'index', 'page', 'category', 'tag', 'author', 'search', 'attachment' );
}

/** Clean a typed address: lower-case letters, digits and hyphens, 4 to 40 characters; '' if it cannot be used. */
function mcupr_login_clean_slug( $raw ) {
	$s = strtolower( trim( (string) $raw ) );
	$s = trim( preg_replace( '/[^a-z0-9-]+/', '-', $s ), '-' );
	$s = preg_replace( '/-{2,}/', '-', $s );
	return ( strlen( $s ) >= 4 && strlen( $s ) <= 40 ) ? $s : '';
}

/** Why a clean address cannot be used (a message), or '' when it can. */
function mcupr_login_slug_problem( $slug ) {
	if ( '' === $slug ) {
		return __( 'Use 4 to 40 letters (a-z), digits or hyphens.', 'mcupr' );
	}
	if ( in_array( $slug, mcupr_login_reserved(), true ) ) {
		return __( 'That name is reserved – pick another one.', 'mcupr' );
	}
	$taken = get_posts( array( 'name' => $slug, 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'mcupr_aud_all' => 1, 'mcupr_office_all' => 1 ) );
	if ( $taken || get_page_by_path( $slug ) ) {
		return __( 'A page or post already uses that address – pick another one.', 'mcupr' );
	}
	return '';
}

/** The private address in use ('' when the feature is off, or switched off in wp-config.php). */
function mcupr_login_slug() {
	if ( defined( 'MCUPR_LOGIN_OFF' ) && MCUPR_LOGIN_OFF ) {
		return '';
	}
	if ( is_multisite() ) {
		return '';
	}
	return mcupr_login_clean_slug( get_option( 'mcupr_login_slug', '' ) );
}

/** Full URL of the sign-in form on this site. */
function mcupr_login_form_url( $slug = null ) {
	$slug = null === $slug ? mcupr_login_slug() : $slug;
	return '' === $slug ? site_url( 'wp-login.php' ) : home_url( '/' . $slug . '/' );
}

/**
 * What to do with a request: 'login' (show the sign-in form), 'block' (answer not found) or '' (carry on).
 *
 * @param string $path      Path below the site's home, no query, no slashes at the ends ('wp-admin/index.php').
 * @param bool   $logged_in Is the visitor signed in?
 * @param string $slug      The private address ('' = feature off).
 */
function mcupr_login_decide( $path, $logged_in, $slug ) {
	if ( '' === $slug ) {
		return '';
	}
	$path = strtolower( trim( (string) $path, '/' ) );
	if ( $path === $slug ) {
		return 'login';
	}
	if ( in_array( $path, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
		return 'block';
	}
	if ( ! $logged_in && ( 'wp-admin' === $path || 0 === strpos( $path, 'wp-admin/' ) ) ) {
		// what visitors (and the sign-in page itself) fetch from there must keep working
		$open = array( 'wp-admin/admin-ajax.php', 'wp-admin/admin-post.php', 'wp-admin/load-scripts.php', 'wp-admin/load-styles.php' );
		return in_array( $path, $open, true ) ? '' : 'block';
	}
	return '';
}

/** Path of the current request below the site's home. */
function mcupr_login_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return trim( rawurldecode( $path ), '/' );
}

/** Answer "not found" with the theme's own page. */
function mcupr_login_not_found() {
	global $wp_query;
	if ( $wp_query ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
	$t = get_404_template();
	if ( $t ) {
		include $t;
	} else {
		echo '<!doctype html><title>404</title><h1>404</h1>';
	}
	exit;
}

add_action(
	'init',
	static function () {
		$slug = mcupr_login_slug();
		if ( '' === $slug || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$what = mcupr_login_decide( mcupr_login_request_path(), is_user_logged_in(), $slug );
		if ( 'block' === $what ) {
			mcupr_login_not_found();
		}
		if ( 'login' === $what ) {
			global $pagenow, $user_login, $error, $interim_login, $action, $errors; // wp-login.php expects these in the global scope
			$pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$GLOBALS['mcupr_login_inside'] = true;
			nocache_headers();
			require_once ABSPATH . 'wp-login.php';
			exit;
		}
	},
	0
);

// /admin, /login and /dashboard would otherwise be sent on to the real sign-in page – and give the address away
add_action(
	'init',
	static function () {
		if ( '' !== mcupr_login_slug() ) {
			remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
		}
	},
	1
);

/** Every link WordPress builds to wp-login.php points at the private address. */
function mcupr_login_rewrite_url( $url ) {
	$slug = mcupr_login_slug();
	if ( '' === $slug || false === strpos( (string) $url, 'wp-login.php' ) ) {
		return $url;
	}
	$home = home_url( '/' );
	$net  = site_url( '/' );
	foreach ( array_unique( array( $home, $net ) ) as $b ) {
		if ( 0 === strpos( $url, $b . 'wp-login.php' ) ) {
			$rest = substr( $url, strlen( $b . 'wp-login.php' ) );
			return $b . $slug . '/' . $rest;
		}
	}
	return $url;
}
add_filter( 'site_url', 'mcupr_login_rewrite_url', 100 );
add_filter( 'network_site_url', 'mcupr_login_rewrite_url', 100 );
add_filter(
	'wp_redirect',
	static function ( $location ) {
		return mcupr_login_rewrite_url( $location );
	},
	100
);

/* ------------------------------------------------------------------ the look */

add_action(
	'login_enqueue_scripts',
	static function () {
		$logo = '';
		$id   = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$src  = wp_get_attachment_image_url( $id, 'medium' );
			$logo = $src ? $src : '';
		}
		if ( '' === $logo ) {
			$logo = MCUPR_URI . '/assets/img/mcu-logo.webp';
		}
		$font = MCUPR_URI . '/assets/fonts/';
		$css  = '@font-face{font-family:Sarabun;font-weight:400;font-display:swap;src:local("Sarabun"),url(' . esc_url( $font . 'Sarabun-400Regular.woff2' ) . ') format("woff2")}'
			. '@font-face{font-family:Sarabun;font-weight:700;font-display:swap;src:local("Sarabun Bold"),url(' . esc_url( $font . 'Sarabun-700Bold.woff2' ) . ') format("woff2")}'
			. mcupr_custom_css()
			. ':root{--logo-img:url(' . esc_url( $logo ) . ')}';
		wp_register_style( 'mcupr-login', false, array( 'login' ), MCUPR_VERSION );
		wp_enqueue_style( 'mcupr-login' );
		wp_add_inline_style( 'mcupr-login', $css );
		wp_enqueue_style( 'mcupr-login-skin', get_theme_file_uri( 'assets/css/login.css' ), array( 'login', 'mcupr-login' ), MCUPR_VERSION );
	}
);

add_filter( 'login_headerurl', 'home_url' );
add_filter(
	'login_headertext',
	static function () {
		return get_bloginfo( 'name' );
	}
);
add_filter(
	'login_body_class',
	static function ( $classes ) {
		$classes[] = 'mcupr-login';
		return $classes;
	}
);
add_filter(
	'login_message',
	static function ( $message ) {
		return '<p class="mcupr-login-sub">' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html( mcupr_t( 'login_staff' ) ) . '</p>' . $message;
	}
);

/* ------------------------------------------------------------------ the settings page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Sign-in address', 'mcupr' ), __( 'Sign-in address', 'mcupr' ), 'manage_options', 'mcupr-login', 'mcupr_login_page' );
	}
);

function mcupr_login_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$slug  = mcupr_login_slug();
	$stored = mcupr_login_clean_slug( get_option( 'mcupr_login_slug', '' ) );
	$off   = defined( 'MCUPR_LOGIN_OFF' ) && MCUPR_LOGIN_OFF;
	echo '<div class="wrap"><h1>' . esc_html__( 'Sign-in address', 'mcupr' ) . '</h1>';
	$msg = isset( $_GET['mcupr_msg'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'saved' === $msg ) {
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	} elseif ( 'bad' === $msg ) {
		$why = isset( $_GET['why'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['why'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-error"><p>' . esc_html( $why ) . '</p></div>';
	}
	if ( $off ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The private address is switched off in wp-config.php (MCUPR_LOGIN_OFF). Remove that line to use it.', 'mcupr' ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'Bots try wp-login.php and wp-admin on every site. With a private address, those two answer “page not found” to anyone who is not signed in, and the sign-in form is only at the address you choose.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="card" style="max-width:46rem;padding:1rem 1.25rem">';
	echo '<input type="hidden" name="action" value="mcupr_login_save">';
	wp_nonce_field( 'mcupr_login_save', 'mcupr_login_nonce' );
	echo '<p><label><input type="checkbox" name="on" value="1"' . checked( '' !== $stored, true, false ) . '> <strong>' . esc_html__( 'Use a private sign-in address', 'mcupr' ) . '</strong></label></p>';
	echo '<p><label for="mcupr-login-slug"><strong>' . esc_html__( 'Address', 'mcupr' ) . '</strong></label><br>';
	echo '<code>' . esc_html( home_url( '/' ) ) . '</code> <input type="text" id="mcupr-login-slug" name="slug" value="' . esc_attr( $stored ) . '" class="regular-text" maxlength="40" placeholder="staff-door" pattern="[A-Za-z0-9-]{4,40}" spellcheck="false" autocomplete="off"></p>';
	echo '<p class="description">' . esc_html__( '4 to 40 letters (a-z), digits or hyphens. Pick something others will not guess, not “admin” or “login”.', 'mcupr' ) . '</p>';
	if ( '' !== $slug ) {
		echo '<p><strong>' . esc_html__( 'Your sign-in address now', 'mcupr' ) . '</strong><br><code id="mcupr-login-now">' . esc_html( mcupr_login_form_url( $slug ) ) . '</code> <button type="button" class="button" onclick="navigator.clipboard&amp;&amp;navigator.clipboard.writeText(document.getElementById(\'mcupr-login-now\').textContent)">' . esc_html__( 'Copy', 'mcupr' ) . '</button></p>';
	}
	echo '<p class="description" style="background:#fff7e0;border:1px solid #f0d98a;padding:.6rem .8rem;border-radius:6px">' . esc_html__( 'Write the new address down. When you save a change, a copy is also emailed to the site administrator address. If it is ever lost, add this line to wp-config.php to switch the private address off:', 'mcupr' ) . '<br><code>define( \'MCUPR_LOGIN_OFF\', true );</code></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form></div>';
}

/** Check and store the form (separate so tests can call it). Returns array( ok bool, message string ). */
function mcupr_login_save( $p ) {
	$on   = ! empty( $p['on'] );
	$slug = $on ? mcupr_login_clean_slug( isset( $p['slug'] ) ? $p['slug'] : '' ) : '';
	$old  = mcupr_login_clean_slug( get_option( 'mcupr_login_slug', '' ) );
	if ( $on ) {
		$why = $slug === $old ? '' : mcupr_login_slug_problem( $slug );
		if ( '' !== $why ) {
			return array( false, $why );
		}
	}
	update_option( 'mcupr_login_slug', $slug, false );
	if ( $slug !== $old && '' !== $slug ) {
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( /* translators: %s: site name */ __( '[%s] New sign-in address', 'mcupr' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			sprintf( /* translators: %s: URL */ __( "The sign-in address of the site is now:\n\n%s\n\nThe old address (wp-login.php and wp-admin) no longer shows the form. If you lose this address, add define( 'MCUPR_LOGIN_OFF', true ); to wp-config.php.", 'mcupr' ), mcupr_login_form_url( $slug ) )
		);
	}
	return array( true, '' );
}

add_action(
	'admin_post_mcupr_login_save',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_login_nonce', 'mcupr_login_save', 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$res = mcupr_login_save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$url = admin_url( 'admin.php?page=mcupr-login&mcupr_msg=' . ( $res[0] ? 'saved' : 'bad&why=' . rawurlencode( $res[1] ) ) );
		wp_safe_redirect( $url );
		exit;
	}
);
