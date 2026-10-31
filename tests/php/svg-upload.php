<?php
/** SVG in the media library: who may upload it, and that every file is cleaned first.   php php/svg-upload.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
require_once ABSPATH . 'wp-admin/includes/file.php';
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

/* who may */
wp_set_current_user( 0 );
t( ! isset( get_allowed_mime_types()['svg'] ), 'a visitor cannot upload SVG' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admin ? $admin[0]->ID : 1 );
t( ! current_user_can( 'unfiltered_html' ) || isset( get_allowed_mime_types()['svg'] ), 'a person with unfiltered_html can upload SVG' );

/* what is kept and what is removed */
$good = '<?xml version="1.0"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="119" height="160"><defs><linearGradient id="g"><stop offset="0" stop-color="#c2185b"/></linearGradient></defs><path d="M0 0h10v10z" fill="url(#g)"/><use xlink:href="#p"/></svg>';
$c    = mcupr_svg_clean( $good );
t( '' !== $c && false !== strpos( $c, '<path' ) && false !== strpos( $c, 'url(#g)' ) && false !== strpos( $c, 'xlink:href="#p"' ) && false === strpos( $c, 'DOCTYPE' ), 'a plain logo keeps its shapes, gradient and inner link; the DOCTYPE goes' );
$bad = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)"><script>alert(2)</script><g onclick="x()"><a xlink:href="javascript:alert(3)"><rect width="1" height="1" style="fill:url(http://evil/x.svg#a)"/></a></g><foreignObject><iframe src="https://evil"/></foreignObject><set attributeName="href" to="javascript:alert(4)"/><style>@import url(https://evil/a.css);</style><image href="https://evil/t.png"/></svg>';
$c   = mcupr_svg_clean( $bad );
$ok  = '' !== $c;
foreach ( array( 'script', 'onload', 'onclick', 'javascript', 'foreignObject', 'iframe', '<set', 'evil', '@import' ) as $needle ) {
	$ok = $ok && false === stripos( $c, $needle );
}
t( $ok, 'scripts, handlers, javascript: links, frames, animation of links and outside addresses are removed' );
t( '' === mcupr_svg_clean( '<svg xmlns="http://www.w3.org/2000/svg"><rect></svg>' ), 'broken XML is refused' );
t( '' === mcupr_svg_clean( '<html><body>hi</body></html>' ), 'not an SVG is refused' );
t( '' === mcupr_svg_clean( '<!DOCTYPE svg [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;">]><svg xmlns="http://www.w3.org/2000/svg"><text>&b;</text></svg>' ), 'entities are refused' );
t( '' === mcupr_svg_clean( '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>' ), 'an external entity is refused' );

/* the upload path: the prefilter cleans the temp file, a bad file is refused */
$tmp = wp_tempnam( 'x.svg' );
file_put_contents( $tmp, $bad );
$r = apply_filters( 'wp_handle_upload_prefilter', array( 'name' => 'logo.svg', 'tmp_name' => $tmp, 'size' => strlen( $bad ), 'type' => 'image/svg+xml', 'error' => 0 ) );
t( empty( $r['error'] ) && false === stripos( (string) file_get_contents( $tmp ), 'script' ), 'upload: the stored file is the cleaned one' );
file_put_contents( $tmp, '<html>not a picture</html>' );
$r = apply_filters( 'wp_handle_upload_prefilter', array( 'name' => 'x.svg', 'tmp_name' => $tmp, 'size' => 10, 'type' => 'image/svg+xml', 'error' => 0 ) );
t( ! empty( $r['error'] ), 'upload: a file that is not an SVG is refused with a message' );
@unlink( $tmp );

/* size */
$f = wp_tempnam( 's.svg' );
file_put_contents( $f, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 12"></svg>' );
t( array( 24, 12 ) === mcupr_svg_size( $f ), 'size comes from the viewBox when there is no width and height' );
@unlink( $f );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
