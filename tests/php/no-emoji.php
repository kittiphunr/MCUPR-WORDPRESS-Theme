<?php
/** No emoji anywhere in what the theme draws or prints (they look different on every computer): icons are the Feather set.   php php/no-emoji.php */
define( 'MCUPR_ROOT', dirname( __DIR__, 2 ) . '/mcupr' );
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$rx    = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{20E3}]/u';
$skip  = array( '/assets/vendor/', '/inc/lib/', '/inc/admin-th-strings.php', '/assets/css/tailwind.css', '/assets/fonts/', '/inc/feather-icons.php' );
$bad   = array();
$count = 0;
$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( MCUPR_ROOT, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	$p = str_replace( '\\', '/', $f->getPathname() );
	if ( ! preg_match( '/\.(php|js|css|html)$/', $p ) ) {
		continue;
	}
	$short = substr( $p, strlen( MCUPR_ROOT ) );
	foreach ( $skip as $s ) {
		if ( 0 === strpos( $short, $s ) ) {
			continue 2;
		}
	}
	$count++;
	foreach ( file( $p ) as $n => $line ) {
		if ( preg_match( $rx, $line, $m ) ) {
			$bad[] = $short . ':' . ( $n + 1 ) . ' ' . $m[0];
		}
	}
}
t( $count > 100, "scanned $count theme files" );
t( ! $bad, 'no emoji, pictograph or dingbat in the theme' . ( $bad ? ': ' . implode( ', ', array_slice( $bad, 0, 8 ) ) : '' ) );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
