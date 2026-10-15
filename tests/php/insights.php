<?php
/** Redirects, 404 log, search log.   php php/insights.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$_SERVER['REMOTE_ADDR'] = '198.51.100.24';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = array( get_option( 'mcupr_redirects' ), get_option( 'mcupr_404log' ), get_option( 'mcupr_searchlog' ), get_option( 'mcupr_ins' ) );
delete_option( 'mcupr_redirects' ); delete_option( 'mcupr_404log' ); delete_option( 'mcupr_searchlog' ); delete_option( 'mcupr_ins' );

// ---- paths
t( '/news/old-title' === mcupr_ins_norm_path( '/News/Old-Title/?utm=1#x' ), 'path: query, case and trailing slash ignored' );
t( '/ข่าว/เก่า' === mcupr_ins_norm_path( '/%E0%B8%82%E0%B9%88%E0%B8%B2%E0%B8%A7/%E0%B9%80%E0%B8%81%E0%B9%88%E0%B8%B2/' ), 'path: Thai addresses are decoded' );
t( '/a' === mcupr_ins_norm_path( 'https://example.org/a/' ), 'path: a full address gives its path' );

// ---- cleaning a rule
list( $r, $e ) = mcupr_ins_clean_rule( '/Old/Page/', '/new/', 301 );
t( $r && '/old/page' === $r['from'] && home_url( '/new/' ) === $r['to'] && 301 === $r['code'], 'rule: from normalised, a local "to" made absolute' );
list( $r, $e ) = mcupr_ins_clean_rule( '/old-section/*', 'https://example.org/x/{rest}', 302 );
t( $r && '/old-section/*' === $r['from'] && 302 === $r['code'], 'rule: a star keeps matching a whole section; 302 kept' );
list( $r, $e ) = mcupr_ins_clean_rule( '/', '/x/', 301 );
t( ! $r && 'from' === $e, 'rule: the home page cannot be redirected' );
list( $r, $e ) = mcupr_ins_clean_rule( '/*', '/x/', 301 );
t( ! $r && 'from' === $e, 'rule: "everything" is refused' );
list( $r, $e ) = mcupr_ins_clean_rule( '/a/', 'javascript:alert(1)', 301 );
t( ! $r && 'to' === $e, 'rule: a script address is refused' );
list( $r, $e ) = mcupr_ins_clean_rule( '/a/', '/a', 301 );
t( ! $r && 'loop' === $e, 'rule: a redirect to itself is refused' );
list( $r, $e ) = mcupr_ins_clean_rule( '/a/', '/b/', 307 );
t( $r && 301 === $r['code'], 'rule: only 301 and 302 are allowed' );

// ---- matching
$rules = array(
	array( 'from' => '/old', 'to' => 'https://example.org/1', 'code' => 301, 'hits' => 0, 'last' => 0 ),
	array( 'from' => '/sec/*', 'to' => 'https://example.org/2/{rest}', 'code' => 301, 'hits' => 0, 'last' => 0 ),
	array( 'from' => '/sec/deep/*', 'to' => 'https://example.org/3', 'code' => 301, 'hits' => 0, 'last' => 0 ),
);
$m = mcupr_ins_match( '/old', $rules );
t( $m && 0 === $m[0], 'match: exact' );
t( null === mcupr_ins_match( '/older', $rules ), 'match: /old does not match /older' );
$m = mcupr_ins_match( '/sec/a/b', $rules );
t( $m && 1 === $m[0], 'match: a star covers what is below' );
$m = mcupr_ins_match( '/sec/deep/x', $rules );
t( $m && 2 === $m[0], 'match: the longest star wins' );
t( null === mcupr_ins_match( '/secret', $rules ), 'match: /sec/* does not match /secret' );

// ---- searches
t( true === mcupr_ins_log_search( '  ทุน   การศึกษา ', 0 ), 'search: a normal term is kept' );
mcupr_ins_log_search( 'ทุน การศึกษา', 3 );
$sl = get_option( 'mcupr_searchlog' );
t( 1 === count( $sl ) && 2 === reset( $sl )['n'] && ! empty( reset( $sl )['f'] ), 'search: the same term is counted, not repeated' );
t( false === mcupr_ins_log_search( 'someone@example.org', 0 ), 'search: an e-mail address is never kept' );
t( false === mcupr_ins_log_search( '1234567890123', 0 ), 'search: a long number is never kept' );
t( false === mcupr_ins_log_search( 'https://x.test', 0 ), 'search: an address is never kept' );
t( false === mcupr_ins_log_search( str_repeat( 'ก', 61 ), 0 ), 'search: very long text is never kept' );
t( false === mcupr_ins_log_search( '   ', 0 ), 'search: empty is not kept' );
for ( $i = 0; $i < 320; $i++ ) { mcupr_ins_log_search( 'term ' . $i, 0 ); }
t( count( get_option( 'mcupr_searchlog' ) ) <= 300, 'search: the list is capped at 300' );

// ---- put back what was there (the admin handler ends with exit, so it is checked in the browser test)
foreach ( array( 'mcupr_redirects', 'mcupr_404log', 'mcupr_searchlog', 'mcupr_ins' ) as $n => $opt ) { if ( false === $keep[ $n ] ) { delete_option( $opt ); } else { update_option( $opt, $keep[ $n ], false ); } }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
