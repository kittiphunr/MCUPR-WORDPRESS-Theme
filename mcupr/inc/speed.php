<?php
/**
 * Speed check (MCUPR → Speed check): what the site itself can see about its speed, with a plain-language "what to do".
 * It asks the site's own home page (and a theme file) a few questions – compression, caching, response time – and looks at
 * the PHP set-up, the biggest pictures in the media library, outside addresses used by the page and the plugins. Nothing
 * is sent to any other service. It does not replace Google PageSpeed (which measures from outside), it tells you what to
 * fix before you ask it.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** ok | warn | bad | info for a response time in milliseconds. */
function mcupr_speed_rate_ttfb( $ms ) {
	return $ms < 200 ? 'ok' : ( $ms < 600 ? 'warn' : 'bad' );
}

/** The hosts (other than this site) that a page's HTML pulls scripts, styles, frames and pictures from: array( kind => hosts[] ). */
function mcupr_speed_outside_hosts( $html, $own_host ) {
	$out = array( 'script' => array(), 'style' => array(), 'frame' => array(), 'image' => array() );
	$add = static function ( $kind, $url ) use ( &$out, $own_host ) {
		$h = wp_parse_url( html_entity_decode( $url ), PHP_URL_HOST );
		if ( $h && strtolower( $h ) !== strtolower( $own_host ) ) {
			$out[ $kind ][ strtolower( $h ) ] = true;
		}
	};
	if ( preg_match_all( '#<script\b[^>]*\bsrc=["\']([^"\']+)#i', $html, $m ) ) {
		foreach ( $m[1] as $u ) { $add( 'script', $u ); }
	}
	if ( preg_match_all( '#<link\b[^>]*\brel=["\']stylesheet["\'][^>]*\bhref=["\']([^"\']+)#i', $html, $m ) || preg_match_all( '#<link\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*\brel=["\']stylesheet#i', $html, $m ) ) {
		foreach ( $m[1] as $u ) { $add( 'style', $u ); }
	}
	if ( preg_match_all( '#<iframe\b[^>]*\bsrc=["\']([^"\']+)#i', $html, $m ) ) {
		foreach ( $m[1] as $u ) { $add( 'frame', $u ); }
	}
	if ( preg_match_all( '#<img\b[^>]*\bsrc=["\']([^"\']+)#i', $html, $m ) ) {
		foreach ( $m[1] as $u ) { $add( 'image', $u ); }
	}
	return array_map( 'array_keys', $out );
}

/** Scripts in the page that block it from showing (no defer / async / module). */
function mcupr_speed_blocking_scripts( $html ) {
	$n = 0;
	preg_match_all( '#<script\b[^>]*\bsrc=[^>]*>#i', $html, $tags );
	foreach ( $tags[0] as $t ) {
		if ( ! preg_match( '#\b(defer|async)\b|type=["\']module#i', $t ) ) {
			$n++;
		}
	}
	return $n;
}

/** The biggest pictures of the media library: array( array( id, name, kb, w, h, flag ) ), biggest first. */
function mcupr_speed_images( $limit = 10 ) {
	$ids  = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'posts_per_page' => 300, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
	$rows = array();
	foreach ( $ids as $id ) {
		$f = get_attached_file( $id );
		if ( ! $f || ! is_readable( $f ) ) {
			continue;
		}
		$meta   = wp_get_attachment_metadata( $id );
		$kb     = (int) round( filesize( $f ) / 1024 );
		$w      = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h      = is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$modern = (bool) preg_match( '/\.(webp|avif|svg)$/i', $f );
		$flag   = $kb > 500 || $w > 2400 ? 'bad' : ( ! $modern && $kb > 250 ? 'warn' : '' );
		$rows[] = array( 'id' => $id, 'name' => wp_basename( $f ), 'kb' => $kb, 'w' => $w, 'h' => $h, 'flag' => $flag );
	}
	usort( $rows, static function ( $a, $b ) { return $b['kb'] <=> $a['kb']; } );
	return array( 'total' => count( $rows ), 'big' => count( array_filter( $rows, static function ( $r ) { return '' !== $r['flag']; } ) ), 'top' => array_slice( $rows, 0, $limit ) );
}

/** One request to ourselves. Returns array( code, ms, headers, body ) or null. */
function mcupr_speed_fetch( $url, $method = 'GET' ) {
	$args = array(
		'timeout'    => 12,
		'redirection' => 2,
		'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
		'user-agent' => 'MCUPR-SpeedCheck-bot/1.0', // a "bot": no welcome page, no counters, nothing logged
		'headers'    => array( 'Accept-Encoding' => 'gzip, deflate, br' ),
		'method'     => $method,
	);
	$t = microtime( true );
	$r = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) {
		return null;
	}
	$hd = wp_remote_retrieve_headers( $r );
	$hd = is_object( $hd ) && method_exists( $hd, 'getAll' ) ? $hd->getAll() : (array) $hd;
	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $r ),
		'ms'      => (int) round( ( microtime( true ) - $t ) * 1000 ),
		'headers' => array_change_key_case( $hd, CASE_LOWER ),
		'body'    => 'HEAD' === $method ? '' : (string) wp_remote_retrieve_body( $r ),
	);
}

function mcupr_speed_header( $h, $name ) {
	if ( ! isset( $h[ $name ] ) ) {
		return '';
	}
	return is_array( $h[ $name ] ) ? implode( ', ', $h[ $name ] ) : (string) $h[ $name ];
}

/** Run all the checks. Returns array( time, items => array( array( id, status, title, detail, fix ) ), images, hosts ). */
function mcupr_speed_run() {
	$items = array();
	$add   = static function ( $id, $status, $title, $detail, $fix = '' ) use ( &$items ) {
		$items[] = compact( 'id', 'status', 'title', 'detail', 'fix' );
	};
	$home     = home_url( '/' );
	$own_host = (string) wp_parse_url( $home, PHP_URL_HOST );

	// ---- this server
	$php = PHP_VERSION;
	$add( 'php', version_compare( $php, '8.2', '>=' ) ? 'ok' : ( version_compare( $php, '8.0', '>=' ) ? 'warn' : 'bad' ), __( 'PHP version', 'mcupr' ), 'PHP ' . $php, __( 'Ask the host to switch the site to PHP 8.2 or newer: it is noticeably faster and safer.', 'mcupr' ) );
	$op = function_exists( 'opcache_get_status' ) && (bool) ini_get( 'opcache.enable' );
	$add( 'opcache', $op ? 'ok' : 'warn', __( 'OPcache (keeps compiled PHP in memory)', 'mcupr' ), $op ? __( 'On', 'mcupr' ) : __( 'Off or not available', 'mcupr' ), $op ? '' : __( 'Ask the host to enable OPcache (opcache.enable=1).', 'mcupr' ) );
	$add( 'https', is_ssl() || 0 === strpos( $home, 'https://' ) ? 'ok' : 'bad', __( 'HTTPS', 'mcupr' ), 0 === strpos( $home, 'https://' ) ? __( 'The site address starts with https://', 'mcupr' ) : __( 'The site address starts with http://', 'mcupr' ), __( 'Install a certificate and change the WordPress address and Site address to https:// (Settings → General). Browsers and Google both reward it.', 'mcupr' ) );
	$add( 'debug', defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warn' : 'ok', __( 'Debug mode', 'mcupr' ), defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'WP_DEBUG is on', 'mcupr' ) : __( 'Off', 'mcupr' ), __( 'Turn WP_DEBUG off on the live site (wp-config.php): it slows pages and can show errors to visitors.', 'mcupr' ) );
	$oc = wp_using_ext_object_cache();
	$add( 'objcache', $oc ? 'ok' : 'info', __( 'Object cache (Redis / Memcached)', 'mcupr' ), $oc ? __( 'In use', 'mcupr' ) : __( 'Not in use', 'mcupr' ), $oc ? '' : __( 'Optional: on a busy site, ask the host for Redis or Memcached.', 'mcupr' ) );
	global $wpdb;
	$auto_kb = (int) round( (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ) / 1024 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$add( 'autoload', $auto_kb < 800 ? 'ok' : ( $auto_kb < 2000 ? 'warn' : 'bad' ), __( 'Settings loaded on every page', 'mcupr' ), $auto_kb . ' KB', __( 'This is the size of the options WordPress loads on every request. Over about 800 KB usually means a plugin that left a lot of data behind: remove plugins you do not use.', 'mcupr' ) );

	// ---- the home page, asked twice (the second answer shows what a visitor gets when a cache is warm)
	$first  = mcupr_speed_fetch( $home );
	$second = $first ? mcupr_speed_fetch( $home ) : null;
	$html   = '';
	if ( ! $first || ! $second ) {
		$add( 'fetch', 'info', __( 'Asking the home page', 'mcupr' ), __( 'The site could not reach its own address (some hosts block this).', 'mcupr' ), __( 'The checks that need it were skipped. Test the address on pagespeed.web.dev instead.', 'mcupr' ) );
	} else {
		$html = $second['body'];
		if ( 200 !== $second['code'] ) {
			$add( 'code', 'warn', __( 'Home page answer', 'mcupr' ), 'HTTP ' . $second['code'], __( 'The home page did not answer 200. If maintenance mode is on, the results below describe the maintenance page.', 'mcupr' ) );
		}
		$add( 'ttfb', mcupr_speed_rate_ttfb( $second['ms'] ), __( 'Response time of the home page', 'mcupr' ), sprintf( /* translators: 1: first, 2: second request ms */ __( 'First %1$d ms · second %2$d ms', 'mcupr' ), $first['ms'], $second['ms'] ), __( 'Under 200 ms is good. If it is slower, switch on a page cache (the host’s own cache, or Nginx FastCGI cache). This matters most for the mobile score. Note: this is measured from the server itself, so a visitor’s time is longer.', 'mcupr' ) );
		$enc = strtolower( mcupr_speed_header( $second['headers'], 'content-encoding' ) );
		$add( 'compress', false !== strpos( $enc, 'br' ) ? 'ok' : ( false !== strpos( $enc, 'gzip' ) ? 'ok' : 'bad' ), __( 'Compression of pages', 'mcupr' ), '' !== $enc ? strtoupper( $enc ) : __( 'None', 'mcupr' ), __( 'Ask the host to enable Brotli (or gzip) for text: HTML, CSS and JavaScript shrink to a quarter.', 'mcupr' ) );
		$kb = (int) round( strlen( $html ) / 1024 );
		$add( 'htmlsize', $kb < 150 ? 'ok' : ( $kb < 300 ? 'warn' : 'bad' ), __( 'Size of the home page HTML', 'mcupr' ), $kb . ' KB', __( 'A very large page usually means many widgets or a long list on the home page.', 'mcupr' ) );
		$block = mcupr_speed_blocking_scripts( $html );
		$add( 'blocking', 0 === $block ? 'ok' : ( $block < 3 ? 'warn' : 'bad' ), __( 'Scripts that hold the page back', 'mcupr' ), sprintf( /* translators: %d: scripts */ __( 'Scripts without defer or async: %d', 'mcupr' ), $block ), __( 'The theme’s own scripts are deferred. Scripts added by plugins that are not, delay the first view: find which plugin adds them (the list below shows the plugins) and ask for a deferred version or remove it.', 'mcupr' ) );
		$hosts = mcupr_speed_outside_hosts( $html, $own_host );
		$cnt   = count( $hosts['script'] ) + count( $hosts['style'] ) + count( $hosts['frame'] );
		$add( 'outside', 0 === $cnt ? 'ok' : ( count( $hosts['script'] ) > 1 ? 'bad' : 'warn' ), __( 'Scripts, styles and frames from other websites', 'mcupr' ), 0 === $cnt ? __( 'None', 'mcupr' ) : implode( ', ', array_unique( array_merge( $hosts['script'], $hosts['style'], $hosts['frame'] ) ) ), __( 'Every outside address costs a connection and sends your visitors’ data to that company. Remove tracking scripts you do not need, or load them only after the visitor agrees.', 'mcupr' ) );

		// ---- a theme file: cache headers
		$css = mcupr_speed_fetch( MCUPR_URI . '/assets/css/tailwind.css?ver=' . MCUPR_VERSION, 'HEAD' );
		if ( $css ) {
			$cc  = mcupr_speed_header( $css['headers'], 'cache-control' );
			$age = preg_match( '/max-age=(\d+)/', $cc, $m ) ? (int) $m[1] : 0;
			if ( ! $age && ! empty( $css['headers']['expires'] ) ) {
				$age = max( 0, strtotime( mcupr_speed_header( $css['headers'], 'expires' ) ) - time() );
			}
			$add( 'assetcache', $age >= 2592000 ? 'ok' : ( $age >= 86400 ? 'warn' : 'bad' ), __( 'Caching of theme files (CSS, JS, fonts)', 'mcupr' ), $age ? sprintf( /* translators: %d: days */ __( 'Kept for %d days', 'mcupr' ), (int) round( $age / DAY_IN_SECONDS ) ) : __( 'No cache time given', 'mcupr' ), __( 'Ask the host to send “Cache-Control: max-age=31536000” for files in /wp-content/. The theme’s files change address (?ver=) whenever they change, so this is safe.', 'mcupr' ) );
			$cenc = strtolower( mcupr_speed_header( $css['headers'], 'content-encoding' ) );
			$add( 'csscompress', '' !== $cenc ? 'ok' : 'warn', __( 'Compression of the theme’s CSS', 'mcupr' ), '' !== $cenc ? strtoupper( $cenc ) : __( 'None', 'mcupr' ), __( 'Same as page compression: enable Brotli or gzip for CSS and JavaScript files.', 'mcupr' ) );
		}
	}

	// ---- pictures
	$img = mcupr_speed_images();
	if ( $img['total'] ) {
		$add( 'images', 0 === $img['big'] ? 'ok' : ( $img['big'] < 5 ? 'warn' : 'bad' ), __( 'Large pictures in the media library', 'mcupr' ), sprintf( /* translators: 1: large, 2: checked */ __( '%1$d of the latest %2$d pictures are large', 'mcupr' ), $img['big'], $img['total'] ), __( 'Resize pictures to about 1600 px on the long side and save them as WebP or well-compressed JPEG (under about 250 KB) before uploading. A cover picture over 500 KB is the most common reason for a slow first view.', 'mcupr' ) );
	}

	// ---- plugins
	$plugins = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	$np = count( $plugins );
	$add( 'plugins', $np <= 10 ? 'ok' : ( $np <= 20 ? 'warn' : 'bad' ), __( 'Active plugins', 'mcupr' ), (string) $np, __( 'The theme needs no plugin. Each plugin adds code to every page: switch off and delete the ones you do not use.', 'mcupr' ) );

	return array( 'time' => time(), 'items' => $items, 'images' => $img, 'hosts' => isset( $hosts ) ? $hosts : array(), 'plugins' => $plugins );
}

/** 0–100: how many of the checks that could be judged are fine (ok = 1, warn = ½). */
function mcupr_speed_score( $items ) {
	$n = 0;
	$s = 0;
	foreach ( $items as $i ) {
		if ( 'info' === $i['status'] ) {
			continue;
		}
		$n++;
		$s += 'ok' === $i['status'] ? 1 : ( 'warn' === $i['status'] ? 0.5 : 0 );
	}
	return $n ? (int) round( 100 * $s / $n ) : 0;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Speed check', 'mcupr' ), __( 'Speed check', 'mcupr' ), 'manage_options', 'mcupr-speed', 'mcupr_speed_page' );
	}
);

function mcupr_speed_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$res = get_option( 'mcupr_speed_last', array() );
	echo '<div class="wrap"><h1>' . esc_html__( 'Speed check', 'mcupr' ) . '</h1>';
	echo '<p style="max-width:60rem">' . esc_html__( 'The site checks itself: compression, caching, response time, the PHP set-up, the biggest pictures, outside addresses and plugins – and tells you what to do. Nothing is sent to any other service. This is a first look from the inside; Google PageSpeed measures from the outside, so run it too.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:1rem"><input type="hidden" name="action" value="mcupr_speed_run">';
	wp_nonce_field( 'mcupr_speed_run', 'mcupr_speed_nonce' );
	echo '<button class="button button-primary">' . esc_html( $res ? __( 'Check again', 'mcupr' ) : __( 'Check now', 'mcupr' ) ) . '</button></form>';
	echo '<a class="button" href="' . esc_url( 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open Google PageSpeed for this site', 'mcupr' ) . ' ↗</a>';
	if ( empty( $res['items'] ) ) {
		echo '<p class="description" style="margin-top:1rem">' . esc_html__( 'Press “Check now”. It takes a few seconds.', 'mcupr' ) . '</p></div>';
		return;
	}
	$score = mcupr_speed_score( $res['items'] );
	$cls   = $score >= 90 ? '#1a7f37' : ( $score >= 60 ? '#9a6700' : '#b32d2e' );
	echo '<div class="card" style="max-width:none;margin-top:1rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap"><div style="font-size:2.6rem;font-weight:800;line-height:1;color:' . esc_attr( $cls ) . '">' . (int) $score . '<small style="font-size:1rem;color:#50575e">/100</small></div><div><strong>' . esc_html__( 'Inside check score', 'mcupr' ) . '</strong><br><span class="description">' . esc_html( sprintf( /* translators: %s: date */ __( 'Checked %s. This is not the Google score; it counts the items below.', 'mcupr' ), wp_date( 'j M Y H:i', $res['time'] ) ) ) . '</span></div></div>';
	$labels = array( 'ok' => array( '✓', __( 'Good', 'mcupr' ), '#1a7f37' ), 'warn' => array( '!', __( 'Could be better', 'mcupr' ), '#9a6700' ), 'bad' => array( '✕', __( 'Fix this', 'mcupr' ), '#b32d2e' ), 'info' => array( 'i', __( 'For your information', 'mcupr' ), '#50575e' ) );
	echo '<div style="overflow-x:auto;margin-top:1rem"><table class="widefat striped" style="word-break:break-word"><thead><tr><th>' . esc_html__( 'Check', 'mcupr' ) . '</th><th>' . esc_html__( 'Result', 'mcupr' ) . '</th><th>' . esc_html__( 'What to do', 'mcupr' ) . '</th></tr></thead><tbody>';
	$order = array( 'bad' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3 );
	$rows  = $res['items'];
	usort( $rows, static function ( $a, $b ) use ( $order ) { return $order[ $a['status'] ] <=> $order[ $b['status'] ]; } );
	foreach ( $rows as $r ) {
		$l = $labels[ $r['status'] ];
		echo '<tr><td style="width:24%"><strong>' . esc_html( $r['title'] ) . '</strong></td><td style="width:26%"><span style="display:inline-block;min-width:1.4em;text-align:center;font-weight:700;color:' . esc_attr( $l[2] ) . '" aria-hidden="true">' . esc_html( $l[0] ) . '</span> <strong style="color:' . esc_attr( $l[2] ) . '">' . esc_html( $l[1] ) . '</strong><br>' . esc_html( $r['detail'] ) . '</td><td>' . ( 'ok' === $r['status'] ? '' : esc_html( $r['fix'] ) ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	if ( ! empty( $res['images']['top'] ) ) {
		echo '<h2>' . esc_html__( 'The biggest pictures', 'mcupr' ) . '</h2><div style="overflow-x:auto"><table class="widefat striped" style="max-width:50rem;word-break:break-word"><thead><tr><th>' . esc_html__( 'File', 'mcupr' ) . '</th><th>' . esc_html__( 'Size', 'mcupr' ) . '</th><th>' . esc_html__( 'Pixels', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $res['images']['top'] as $i ) {
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $i['id'] ) ) . '">' . esc_html( $i['name'] ) . '</a></td><td>' . (int) $i['kb'] . ' KB</td><td>' . (int) $i['w'] . '×' . (int) $i['h'] . '</td><td>' . ( 'bad' === $i['flag'] ? '<strong style="color:#b32d2e">' . esc_html__( 'Too big', 'mcupr' ) . '</strong>' : ( 'warn' === $i['flag'] ? '<span style="color:#9a6700">' . esc_html__( 'Could be smaller', 'mcupr' ) . '</span>' : '' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	if ( ! empty( $res['plugins'] ) ) {
		echo '<h2>' . esc_html__( 'Active plugins', 'mcupr' ) . '</h2><p>' . esc_html( implode( ' · ', array_map( static function ( $p ) { return dirname( $p ) !== '.' ? dirname( $p ) : basename( $p, '.php' ); }, $res['plugins'] ) ) ) . '</p>';
	}
	echo '</div>';
}

add_action(
	'admin_post_mcupr_speed_run',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mcupr_speed_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_speed_nonce'] ) ), 'mcupr_speed_run' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		update_option( 'mcupr_speed_last', mcupr_speed_run(), false );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-speed' ) );
		exit;
	}
);
