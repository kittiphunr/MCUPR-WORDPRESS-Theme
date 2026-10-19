<?php
/** Low-risk hardening (v1.54.4): book files only from inside uploads/, admin notices from codes, import files of their owner, form answers bound to the browser, guests cannot take a staff name, the ID number masked.   php php/low-risk.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// 1. books: a file in a sibling folder (uploads-old/) is not "inside uploads/"
$up = wp_get_upload_dir(); $base = realpath( $up['basedir'] );
t( mcupr_path_inside( $base . '/2030/01/a.pdf', $base ) && ! mcupr_path_inside( $base . '-old/a.pdf', $base ) && ! mcupr_path_inside( $base, $base ) && ! mcupr_path_inside( false, $base ), 'books: only a path under uploads/ passes, uploads-old/ does not' );
t( mcupr_path_inside( 'C:\\site\\uploads\\a.pdf', 'C:\\site\\uploads' ) && ! mcupr_path_inside( 'C:\\site\\uploads-old\\a.pdf', 'C:\\site\\uploads' ), 'books: the same with Windows separators' );

//@@NEXT@@
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
