<?php
/**
 * Redirects, missing pages and what people search for – small tools for keeping a website tidy, no plugin needed:
 *
 *  - Redirects (เปลี่ยนเส้นทาง): when a page moves, send the old address to the new one (301 / 302; an address ending
 *    in * matches everything under it). Counts how often each one is used.
 *  - Missing pages (404 log): the addresses people asked for that do not exist, with a counter – make a redirect from
 *    the list in one click. Only the address (no query string) and a counter are kept.
 *  - Search log: what visitors type in the search box (a counter per term, and whether it found anything), so you
 *    know which pages to write. Terms that look like an e-mail address or a long number are never kept. Can be switched off.
 *
 * Admin: MCUPR → Redirects & insights.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_ins_opts() {
	$o = get_option( 'mcupr_ins', array() );
	return array_merge( array( 'log404' => 1, 'logsearch' => 1 ), is_array( $o ) ? $o : array() );
}

/** A request path made comparable: no query string, decoded, lower case, no trailing slash, no home folder prefix. */
function mcupr_ins_norm_path( $uri ) {
	$p    = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '/' !== $home && 0 === strpos( $p, rtrim( $home, '/' ) ) ) {
		$p = substr( $p, strlen( rtrim( $home, '/' ) ) );
	}
	$p = '/' . trim( rawurldecode( $p ), '/' );
	return mb_strtolower( mb_substr( $p, 0, 200 ) );
}

/** The rules as saved (what changes them reads and writes this; the use counts are kept apart, see below). */
function mcupr_redirects() {
	$r = get_option( 'mcupr_redirects', array() );
	return is_array( $r ) ? $r : array();
}

/*
 * How often each rule was used is kept in its own small option (mcupr_redirect_hits: "from" => array( hits, last ),
 * not autoloaded), so a redirect does not rewrite the list of up to 500 rules. Rules saved by older versions carry
 * their own hits / last; the two are added up for the admin screen.
 */
function mcupr_redirect_hits() {
	$h = get_option( 'mcupr_redirect_hits', array() );
	return is_array( $h ) ? $h : array();
}

/** One more use of the rule that starts at $from. */
function mcupr_redirect_hit( $from ) {
	$h          = mcupr_redirect_hits();
	$from       = (string) $from;
	$h[ $from ] = array( 'hits' => ( isset( $h[ $from ]['hits'] ) ? (int) $h[ $from ]['hits'] : 0 ) + 1, 'last' => time() );
	update_option( 'mcupr_redirect_hits', $h, false );
}

/** Forget the counts of $from (a rule made again starts at 0), and of rules that no longer exist. */
function mcupr_redirect_hits_prune( $rules, $from = null ) {
	$h    = mcupr_redirect_hits();
	$have = array_flip( wp_list_pluck( $rules, 'from' ) );
	$new  = array();
	foreach ( $h as $f => $v ) {
		if ( isset( $have[ $f ] ) && ( null === $from || (string) $from !== (string) $f ) ) {
			$new[ $f ] = $v;
		}
	}
	if ( $new !== $h ) {
		update_option( 'mcupr_redirect_hits', $new, false );
	}
}

/** Pure: the rules with the counts kept apart added in (hits summed, the later "last"). */
function mcupr_redirects_merge_hits( $rules, $hits ) {
	foreach ( (array) $rules as $i => $r ) {
		$f = isset( $r['from'] ) ? (string) $r['from'] : '';
		if ( '' !== $f && isset( $hits[ $f ] ) ) {
			$rules[ $i ]['hits'] = ( isset( $r['hits'] ) ? (int) $r['hits'] : 0 ) + (int) $hits[ $f ]['hits'];
			$rules[ $i ]['last'] = max( isset( $r['last'] ) ? (int) $r['last'] : 0, (int) $hits[ $f ]['last'] );
		}
	}
	return $rules;
}

/** The rules with how often each was used (for showing). */
function mcupr_redirects_with_hits() {
	return mcupr_redirects_merge_hits( mcupr_redirects(), mcupr_redirect_hits() );
}

/*
 * The 404 and search logs: each visit adds to a small buffer (options mcupr_404log_buf / mcupr_searchlog_buf, not
 * autoloaded, never expiring), which is added to the saved log at most about once a minute or when it holds 20
 * entries – the saved logs (up to 300 entries each) keep the format they always had. Readers add the buffer in.
 */
const MCUPR_INS_FLUSH_SECONDS = 60;
const MCUPR_INS_FLUSH_ITEMS   = 20;

function mcupr_ins_sort( $a, $b ) {
	return $b['n'] <=> $a['n'] ?: $b['t'] <=> $a['t'];
}

/** Pure: a log with buffered entries added (counts summed, the later time; 'f' from the newer one), then capped at 300. */
function mcupr_ins_merge_log( $log, $buf ) {
	$log = is_array( $log ) ? $log : array();
	foreach ( (array) $buf as $k => $e ) {
		if ( ! is_array( $e ) || ! isset( $e['n'], $e['t'] ) ) {
			continue;
		}
		if ( isset( $log[ $k ] ) && is_array( $log[ $k ] ) ) {
			$log[ $k ]['n'] = (int) $log[ $k ]['n'] + (int) $e['n'];
			if ( (int) $e['t'] >= (int) $log[ $k ]['t'] ) {
				$log[ $k ]['t'] = (int) $e['t'];
				if ( isset( $e['f'] ) ) {
					$log[ $k ]['f'] = (int) $e['f'];
				}
			}
		} else {
			$log[ $k ] = $e;
		}
	}
	if ( count( $log ) > 300 ) { // keep the 300 most asked for
		uasort( $log, 'mcupr_ins_sort' );
		$log = array_slice( $log, 0, 300, true );
	}
	return $log;
}

function mcupr_ins_buffer( $name ) {
	$b = get_option( $name . '_buf', array() );
	$b = is_array( $b ) ? $b : array();
	return array(
		't' => isset( $b['t'] ) ? (int) $b['t'] : 0,
		'e' => isset( $b['e'] ) && is_array( $b['e'] ) ? $b['e'] : array(),
	);
}

/** Add the buffer of log $name (mcupr_404log | mcupr_searchlog), or the buffer $b given, to the saved log. */
function mcupr_ins_flush( $name, $b = null ) {
	$b = is_array( $b ) ? $b : mcupr_ins_buffer( $name );
	if ( $b['e'] ) {
		update_option( $name, mcupr_ins_merge_log( get_option( $name, array() ), $b['e'] ), false );
	}
	if ( $b['e'] || $b['t'] ) {
		update_option( $name . '_buf', array( 't' => 0, 'e' => array() ), false );
	}
}

/** Count one entry of log $name: $k its key, $new the entry when it is new ('n' 1, 't' now, …), $extra fields to set each time. */
function mcupr_ins_add( $name, $k, $new, $extra = array() ) {
	$b = mcupr_ins_buffer( $name );
	if ( isset( $b['e'][ $k ] ) ) {
		$b['e'][ $k ]['n'] = (int) $b['e'][ $k ]['n'] + 1;
		$b['e'][ $k ]['t'] = time();
		foreach ( $extra as $f => $v ) {
			$b['e'][ $k ][ $f ] = $v;
		}
	} else {
		$b['e'][ $k ] = array_merge( $new, $extra );
	}
	if ( ! $b['t'] ) {
		$b['t'] = time();
	}
	if ( time() - $b['t'] >= MCUPR_INS_FLUSH_SECONDS || count( $b['e'] ) >= MCUPR_INS_FLUSH_ITEMS ) {
		mcupr_ins_flush( $name, $b );
	} else {
		update_option( $name . '_buf', $b, false );
	}
}

/** A log as the admin screen shows it: saved + buffered. */
function mcupr_ins_log( $name ) {
	return mcupr_ins_merge_log( get_option( $name, array() ), mcupr_ins_buffer( $name )['e'] );
}

/** Find the redirect for a path: exact first, then the longest "prefix*". Returns array( index, rule ) or null. */
function mcupr_ins_match( $path, $rules ) {
	$best = null;
	foreach ( $rules as $i => $r ) {
		$from = $r['from'];
		if ( $from === $path ) {
			return array( $i, $r );
		}
		if ( '*' === substr( $from, -1 ) ) {
			$pre = rtrim( substr( $from, 0, -1 ), '/' );
			if ( '' !== $pre && ( $path === $pre || 0 === strpos( $path, $pre . '/' ) ) && ( ! $best || strlen( $pre ) > strlen( rtrim( substr( $best[1]['from'], 0, -1 ), '/' ) ) ) ) {
				$best = array( $i, $r );
			}
		}
	}
	return $best;
}

/** Clean one rule typed by a person. Returns array( rule, error key ) – rule is null on error. */
function mcupr_ins_clean_rule( $from, $to, $code ) {
	$from = trim( (string) $from );
	$to   = trim( (string) $to );
	if ( preg_match( '#^https?://#i', $from ) ) {
		$from = (string) wp_parse_url( $from, PHP_URL_PATH ) . ( wp_parse_url( $from, PHP_URL_QUERY ) ? '' : '' );
	}
	$star = '*' === substr( $from, -1 );
	$from = mcupr_ins_norm_path( $from );
	if ( $star && '*' !== substr( $from, -1 ) ) {
		$from .= '/*';
	}
	if ( '/' === $from || '' === trim( $from, '/*' ) ) {
		return array( null, 'from' );
	}
	if ( preg_match( '#^/#', $to ) ) {
		$to = home_url( $to );
	}
	$to = esc_url_raw( $to, array( 'http', 'https' ) );
	if ( ! $to || wp_parse_url( $to, PHP_URL_HOST ) === null ) {
		return array( null, 'to' );
	}
	if ( rtrim( mcupr_ins_norm_path( $to ), '*' ) === rtrim( $from, '*' ) && wp_parse_url( $to, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return array( null, 'loop' );
	}
	return array( array( 'from' => $from, 'to' => $to, 'code' => 302 === (int) $code ? 302 : 301, 'hits' => 0, 'last' => 0 ), '' );
}

/* ------------------------------------------------------------------ the front end: redirect, then log */

add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() || is_admin() || wp_doing_ajax() || 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) {
			return;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$path = mcupr_ins_norm_path( $uri );
		$m    = mcupr_ins_match( $path, mcupr_redirects() );
		if ( $m ) {
			mcupr_redirect_hit( $m[1]['from'] ); // a small option of its own: the rules are not rewritten
			$to = $m[1]['to'];
			if ( '*' === substr( $m[1]['from'], -1 ) && false !== strpos( $to, '{rest}' ) ) {
				$to = str_replace( '{rest}', ltrim( substr( $path, strlen( rtrim( substr( $m[1]['from'], 0, -1 ), '/' ) ) ), '/' ), $to );
			}
			wp_redirect( $to, (int) $m[1]['code'], 'MCUPR' ); // phpcs:ignore WordPress.Security.SafeRedirect – the address was typed by an administrator
			exit;
		}
		$o = mcupr_ins_opts();
		if ( empty( $o['log404'] ) || mcupr_is_bot() || preg_match( '#^/(wp-|favicon|apple-touch|robots|\.well-known)|\.(map|png|jpe?g|gif|svg|ico|css|js|woff2?|webp|php)$#', $path ) ) {
			return;
		}
		mcupr_ins_add( 'mcupr_404log', md5( $path ), array( 'p' => $path, 'n' => 1, 't' => time() ) );
	},
	2
);

/** Remember a search (a counter per term), unless it could be personal. */
function mcupr_ins_log_search( $term, $found ) {
	$term = mb_strtolower( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $term ) ) ) );
	if ( '' === $term || mb_strlen( $term ) > 60 || false !== strpos( $term, '@' ) || preg_match( '/\d{6,}/', str_replace( array( ' ', '-' ), '', $term ) ) || preg_match( '#https?://#', $term ) ) {
		return false;
	}
	mcupr_ins_add( 'mcupr_searchlog', md5( $term ), array( 'q' => $term, 'n' => 1, 't' => time() ), array( 'f' => (int) $found ) );
	return true;
}

add_action(
	'template_redirect',
	static function () {
		if ( ! is_search() || is_admin() || ! empty( $_GET['mcupr_welcome'] ) || empty( mcupr_ins_opts()['logsearch'] ) || mcupr_is_bot() || is_paged() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wp_query;
		mcupr_ins_log_search( get_search_query( false ), (int) $wp_query->found_posts );
	},
	3
);

/* ------------------------------------------------------------------ admin page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Redirects & insights', 'mcupr' ), __( 'Redirects & insights', 'mcupr' ), 'manage_options', 'mcupr-insights', 'mcupr_ins_page' );
	}
);

function mcupr_ins_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o     = mcupr_ins_opts();
	$rules = mcupr_redirects_with_hits();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$pre_from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
	$msg      = isset( $_GET['msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['msg'] ) ) ) : '';
	// phpcs:enable
	echo '<div class="wrap"><h1>' . esc_html__( 'Redirects & insights', 'mcupr' ) . '</h1>';
	if ( '' !== $msg ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	$form = static function ( $do, $inner, $btn, $class = 'button' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="mcupr_ins"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		wp_nonce_field( 'mcupr_ins', 'mcupr_ins_nonce' );
		echo $inner . '<button class="' . esc_attr( $class ) . '">' . esc_html( $btn ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
	};
	// ---- redirects
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '1. Redirects', 'mcupr' ) . '</h2><p class="description">' . esc_html__( 'When a page moves, send the old address to the new one. “From” is the old path, for example /news/old-title/ ; an address ending in * (such as /old-section/*) matches everything under it, and {rest} in the “To” address is replaced by the part that matched.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_ins"><input type="hidden" name="do" value="add">';
	wp_nonce_field( 'mcupr_ins', 'mcupr_ins_nonce' );
	echo '<p><label>' . esc_html__( 'From', 'mcupr' ) . ' <input type="text" name="from" value="' . esc_attr( $pre_from ) . '" class="regular-text" placeholder="/old-page/" required></label> <label>' . esc_html__( 'To', 'mcupr' ) . ' <input type="text" name="to" class="regular-text" placeholder="/new-page/ or https://…" required></label> <label><select name="code"><option value="301">301 ' . esc_html__( 'permanent', 'mcupr' ) . '</option><option value="302">302 ' . esc_html__( 'temporary', 'mcupr' ) . '</option></select></label> <button class="button button-primary">' . esc_html__( 'Add redirect', 'mcupr' ) . '</button></p></form>';
	if ( $rules ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'From', 'mcupr' ) . '</th><th>' . esc_html__( 'To', 'mcupr' ) . '</th><th>' . esc_html__( 'Type', 'mcupr' ) . '</th><th>' . esc_html__( 'Used', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rules as $i => $r ) {
			echo '<tr><td><code>' . esc_html( $r['from'] ) . '</code></td><td style="word-break:break-all">' . esc_html( $r['to'] ) . '</td><td>' . (int) $r['code'] . '</td><td>' . (int) $r['hits'] . ( $r['last'] ? ' · ' . esc_html( wp_date( 'j M Y', $r['last'] ) ) : '' ) . '</td><td>';
			$form( 'del', '<input type="hidden" name="i" value="' . (int) $i . '">', __( 'Delete', 'mcupr' ), 'button-link-delete' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
	// ---- 404
	$log = mcupr_ins_log( 'mcupr_404log' );
	uasort( $log, 'mcupr_ins_sort' );
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '2. Pages that were asked for but do not exist (404)', 'mcupr' ) . '</h2>';
	if ( $log ) {
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Address', 'mcupr' ) . '</th><th>' . esc_html__( 'Times', 'mcupr' ) . '</th><th>' . esc_html__( 'Last', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( array_slice( $log, 0, 40, true ) as $e ) {
			echo '<tr><td><code>' . esc_html( $e['p'] ) . '</code></td><td>' . (int) $e['n'] . '</td><td>' . esc_html( wp_date( 'j M Y H:i', $e['t'] ) ) . '</td><td><a class="button" href="' . esc_url( add_query_arg( 'from', rawurlencode( $e['p'] ), admin_url( 'admin.php?page=mcupr-insights' ) ) ) . '#wpbody">' . esc_html__( 'Make a redirect', 'mcupr' ) . '</a></td></tr>';
		}
		echo '</tbody></table><p>';
		$form( 'clear404', '', __( 'Clear the list', 'mcupr' ) );
		echo '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'Nothing yet.', 'mcupr' ) . '</p>';
	}
	echo '</div>';
	// ---- searches
	$sl = mcupr_ins_log( 'mcupr_searchlog' );
	uasort( $sl, 'mcupr_ins_sort' );
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '3. What visitors search for', 'mcupr' ) . '</h2>';
	if ( $sl ) {
		$none = array_filter( $sl, static function ( $e ) { return empty( $e['f'] ); } );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Search', 'mcupr' ) . '</th><th>' . esc_html__( 'Times', 'mcupr' ) . '</th><th>' . esc_html__( 'Found something?', 'mcupr' ) . '</th><th>' . esc_html__( 'Last', 'mcupr' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $sl, 0, 40, true ) as $e ) {
			echo '<tr><td>' . esc_html( $e['q'] ) . '</td><td>' . (int) $e['n'] . '</td><td>' . ( ! empty( $e['f'] ) ? mcupr_feather_i( 'check', 14 ) . '<span class="screen-reader-text">' . esc_html__( 'Yes', 'mcupr' ) . '</span>' : '<strong style="color:#b32d2e">' . esc_html__( 'No – write about this', 'mcupr' ) . '</strong>' ) . '</td><td>' . esc_html( wp_date( 'j M Y', $e['t'] ) ) . '</td></tr>';
		}
		echo '</tbody></table><p>' . esc_html( sprintf( /* translators: %d: terms */ __( '%d of the searches found nothing.', 'mcupr' ), count( $none ) ) ) . ' ';
		$form( 'clearsearch', '', __( 'Clear the list', 'mcupr' ) );
		echo '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'Nothing yet.', 'mcupr' ) . '</p>';
	}
	echo '</div>';
	// ---- switches
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Logs', 'mcupr' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_ins"><input type="hidden" name="do" value="opts">';
	wp_nonce_field( 'mcupr_ins', 'mcupr_ins_nonce' );
	echo '<p><label><input type="checkbox" name="log404" value="1"' . checked( ! empty( $o['log404'] ), true, false ) . '> ' . esc_html__( 'Keep a list of missing pages (only the address and a counter; no visitor data)', 'mcupr' ) . '</label><br><label><input type="checkbox" name="logsearch" value="1"' . checked( ! empty( $o['logsearch'] ), true, false ) . '> ' . esc_html__( 'Keep a list of searches (only the words and a counter; never e-mail addresses or long numbers)', 'mcupr' ) . '</label></p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></form></div></div>';
}

add_action(
	'admin_post_mcupr_ins',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_ins_nonce', 'mcupr_ins', 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$msg = '';
		if ( 'add' === $do ) {
			list( $rule, $err ) = mcupr_ins_clean_rule( isset( $_POST['from'] ) ? wp_unslash( $_POST['from'] ) : '', isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '', isset( $_POST['code'] ) ? (int) $_POST['code'] : 301 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( $rule ) {
				$rules = array_values( array_filter( mcupr_redirects(), static function ( $r ) use ( $rule ) { return $r['from'] !== $rule['from']; } ) );
				array_unshift( $rules, $rule );
				$rules = array_slice( $rules, 0, 500 );
				update_option( 'mcupr_redirects', $rules, false );
				mcupr_redirect_hits_prune( $rules, $rule['from'] ); // the new rule starts at 0, as before
				mcupr_ins_flush( 'mcupr_404log' ); // so the address is taken out of the buffered visits too
				$log = get_option( 'mcupr_404log', array() );
				unset( $log[ md5( $rule['from'] ) ] );
				update_option( 'mcupr_404log', is_array( $log ) ? $log : array(), false );
				$msg = __( 'Redirect added.', 'mcupr' );
			} else {
				$msg = array( 'from' => __( 'The “From” address is not usable (give a path such as /old-page/).', 'mcupr' ), 'to' => __( 'The “To” address is not usable (a path or an https:// address).', 'mcupr' ), 'loop' => __( 'A redirect to the same address would loop.', 'mcupr' ) )[ $err ];
			}
		} elseif ( 'del' === $do ) {
			$rules = mcupr_redirects();
			unset( $rules[ isset( $_POST['i'] ) ? (int) $_POST['i'] : -1 ] );
			update_option( 'mcupr_redirects', array_values( $rules ), false );
			mcupr_redirect_hits_prune( $rules );
			$msg = __( 'Deleted.', 'mcupr' );
		} elseif ( 'clear404' === $do ) {
			delete_option( 'mcupr_404log' );
			delete_option( 'mcupr_404log_buf' );
			$msg = __( 'Cleared.', 'mcupr' );
		} elseif ( 'clearsearch' === $do ) {
			delete_option( 'mcupr_searchlog' );
			delete_option( 'mcupr_searchlog_buf' );
			$msg = __( 'Cleared.', 'mcupr' );
		} elseif ( 'opts' === $do ) {
			update_option( 'mcupr_ins', array( 'log404' => ! empty( $_POST['log404'] ) ? 1 : 0, 'logsearch' => ! empty( $_POST['logsearch'] ) ? 1 : 0 ), false );
			$msg = __( 'Saved.', 'mcupr' );
		}
		wp_safe_redirect( add_query_arg( 'msg', rawurlencode( $msg ), admin_url( 'admin.php?page=mcupr-insights' ) ) );
		exit;
	}
);
