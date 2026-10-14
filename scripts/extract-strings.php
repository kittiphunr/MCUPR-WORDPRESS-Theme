<?php
/**
 * Lists every translatable string of the theme (text domain "mcupr") found in the PHP files.
 *   php scripts/extract-strings.php            # one string per line (JSON-encoded)
 * Used by tests/check.sh to make sure each one has a Thai translation in mcupr/inc/admin-th.php.
 */
$root = dirname( __DIR__ ) . '/mcupr';
$fns  = array( '__', '_e', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e', '_x', 'esc_html_x', 'esc_attr_x' );
$out  = array();
$it   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( 'php' !== $f->getExtension() || false !== strpos( $f->getPathname(), '/assets/' ) || false !== strpos( $f->getPathname(), '/starter/' ) ) {
		continue;
	}
	$t = token_get_all( file_get_contents( $f->getPathname() ) );
	$t = array_values( array_filter( $t, function ( $x ) { return ! ( is_array( $x ) && in_array( $x[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ); } ) );
	$n = count( $t );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( is_array( $t[ $i ] ) && T_STRING === $t[ $i ][0] && in_array( $t[ $i ][1], $fns, true ) && '(' === $t[ $i + 1 ] && is_array( $t[ $i + 2 ] ) && T_CONSTANT_ENCAPSED_STRING === $t[ $i + 2][0] ) {
			// find the closing paren of this call and the domain string before it
			$depth = 0; $dom = ''; $msg = eval( 'return ' . $t[ $i + 2 ][1] . ';' ); // phpcs:ignore
			for ( $j = $i + 1; $j < $n; $j++ ) {
				if ( '(' === $t[ $j ] ) { $depth++; }
				if ( ')' === $t[ $j ] ) { $depth--; if ( 0 === $depth ) { break; } }
				if ( 1 === $depth && is_array( $t[ $j ] ) && T_CONSTANT_ENCAPSED_STRING === $t[ $j ][0] ) { $dom = trim( $t[ $j ][1], "'\"" ); }
			}
			if ( 'mcupr' === $dom ) { $out[ $msg ] = true; }
		}
	}
}
ksort( $out );
foreach ( array_keys( $out ) as $m ) { echo json_encode( $m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n"; }
