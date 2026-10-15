<?php
/** Speed check: the judging functions, with a faked home page and faked headers.   php php/speed.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

t( 'ok' === mcupr_speed_rate_ttfb( 120 ) && 'warn' === mcupr_speed_rate_ttfb( 450 ) && 'bad' === mcupr_speed_rate_ttfb( 900 ), 'ttfb: under 200 good, under 600 so-so, slower bad' );

$html = '<html><head><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=X"><link href="/a.css" rel="stylesheet"><script src="/a.js" defer></script><script src="/b.js"></script><script async src="https://www.googletagmanager.com/gtag.js"></script><script>var x=1</script></head><body><img src="https://img.example.com/x.jpg"><iframe src="https://www.youtube.com/embed/x"></iframe></body></html>';
$h = mcupr_speed_outside_hosts( $html, 'example.org' );
t( array( 'www.googletagmanager.com' ) === $h['script'] && array( 'fonts.googleapis.com' ) === $h['style'] && array( 'www.youtube.com' ) === $h['frame'] && array( 'img.example.com' ) === $h['image'], 'hosts: outside scripts, styles, frames and pictures are listed, our own are not' );
t( 1 === count( mcupr_speed_outside_hosts( '<script src="https://example.org/a.js"></script><script src="https://cdn.x.test/a.js"></script>', 'example.org' )['script'] ), 'hosts: the site’s own address is not "outside"' );
t( 1 === mcupr_speed_blocking_scripts( $html ), 'blocking: only the script without defer / async / module counts (inline scripts do not)' );
t( 0 === mcupr_speed_blocking_scripts( '<script type="module" src="/a.js"></script><script defer src="/b.js"></script>' ), 'blocking: module, defer and async are fine' );

// ---- the whole run, with a faked server
$cases = array(
	'good' => array( 'enc' => 'br', 'cache' => 'public, max-age=31536000', 'body' => '<html><body>ok</body></html>' ),
	'bad'  => array( 'enc' => '', 'cache' => '', 'body' => $html ),
);
foreach ( $cases as $name => $c ) {
	add_filter( 'pre_http_request', $f = function ( $pre, $args, $url ) use ( $c ) {
		if ( 0 !== strpos( $url, home_url() ) ) { return $pre; }
		$is_head = 'HEAD' === $args['method'];
		$hdr = array( 'content-type' => $is_head ? 'text/css' : 'text/html' );
		if ( $c['enc'] ) { $hdr['content-encoding'] = $c['enc']; }
		if ( $c['cache'] ) { $hdr['cache-control'] = $c['cache']; }
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'headers' => $hdr, 'body' => $is_head ? '' : $c['body'], 'cookies' => array(), 'filename' => null );
	}, 10, 3 );
	$r  = mcupr_speed_run();
	$by = array(); foreach ( $r['items'] as $i ) { $by[ $i['id'] ] = $i; }
	if ( 'good' === $name ) {
		t( 'ok' === $by['compress']['status'] && 'ok' === $by['assetcache']['status'] && 'ok' === $by['outside']['status'] && 'ok' === $by['blocking']['status'], 'run (good site): compression, asset cache, outside addresses and scripts all fine' );
		t( $by['assetcache']['detail'] && false !== strpos( $by['assetcache']['detail'], '365' ), 'run (good site): the cache time is shown in days' );
	} else {
		t( 'bad' === $by['compress']['status'] && 'bad' === $by['assetcache']['status'], 'run (bad site): no compression and no cache time are flagged "fix this"' );
		t( 'warn' === $by['blocking']['status'] && in_array( $by['outside']['status'], array( 'warn', 'bad' ), true ) && false !== strpos( $by['outside']['detail'], 'googletagmanager' ), 'run (bad site): a blocking script and the outside tracking address are reported' );
		t( '' !== $by['compress']['fix'] && '' !== $by['assetcache']['fix'], 'run (bad site): every problem comes with what to do' );
	}
	t( isset( $by['php'], $by['https'], $by['autoload'], $by['plugins'], $by['opcache'] ), "run ($name): server checks are present" );
	remove_filter( 'pre_http_request', $f, 10 );
}
// the site cannot reach itself
add_filter( 'pre_http_request', $g = function ( $pre, $args, $url ) { return 0 === strpos( $url, home_url() ) ? new WP_Error( 'x', 'blocked' ) : $pre; }, 10, 3 );
$r = mcupr_speed_run(); $ids = wp_list_pluck( $r['items'], 'id' ); $by = array_column( $r['items'], null, 'id' );
t( in_array( 'fetch', $ids, true ) && 'info' === $by['fetch']['status'] && ! in_array( 'compress', $ids, true ), 'run (blocked): says so, and skips the checks that need the page instead of guessing' );
remove_filter( 'pre_http_request', $g, 10 );

// ---- score and images
t( 100 === mcupr_speed_score( array( array( 'status' => 'ok' ), array( 'status' => 'info' ) ) ) && 50 === mcupr_speed_score( array( array( 'status' => 'ok' ), array( 'status' => 'bad' ) ) ) && 75 === mcupr_speed_score( array( array( 'status' => 'ok' ), array( 'status' => 'warn' ) ) ) && 0 === mcupr_speed_score( array() ), 'score: ok counts 1, warn half, bad 0; "info" is not counted' );
$dir = wp_upload_dir(); $big = $dir['path'] . '/speedtest-big.jpg'; file_put_contents( $big, str_repeat( 'x', 600 * 1024 ) );
$aid = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'speedtest', 'post_status' => 'inherit' ), $big );
wp_update_attachment_metadata( $aid, array( 'width' => 3000, 'height' => 2000, 'file' => _wp_relative_upload_path( $big ) ) );
$im = mcupr_speed_images();
t( $im['big'] >= 1 && 'speedtest-big.jpg' === $im['top'][0]['name'] && 'bad' === $im['top'][0]['flag'] && 600 === $im['top'][0]['kb'], 'images: a 600 KB, 3000 px picture is first in the list and "too big"' );
wp_delete_attachment( $aid, true );
t( empty( array_filter( mcupr_speed_images()['top'], function ( $r ) { return 'speedtest-big.jpg' === $r['name']; } ) ), 'images: gone from the list after deleting it' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
