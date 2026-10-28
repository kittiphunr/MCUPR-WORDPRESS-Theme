<?php
/** The cookie centre: the choice, what may be kept, the list of everything the theme keeps (it must match the code), the words in four languages.   php php/cookie-center.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$root = realpath( __DIR__ . '/../../mcupr' );

// 1. the choice: no cookie, a wrong one, an old version, a good one
unset( $_COOKIE['mcupr_cc'] );
$reset = static function () { $r = new ReflectionFunction( 'mcupr_cc_record' ); foreach ( $r->getStaticVariables() as $k => $v ) {} };
function cc_fresh( $cookie ) { global $argv; $_COOKIE['mcupr_cc'] = $cookie; return $cookie; }
t( ! mcupr_cc_allows( 'preferences' ) && mcupr_cc_allows( 'necessary' ), 'no choice: only the necessary is allowed' );
t( mcupr_cc_version() >= 1 && array_keys( mcupr_cc_categories() ) === array( 'preferences', 'statistics', 'embeds' ), 'the kinds asked about: preferences, statistics, embeds' );

// the record is read once per request: check the parser through a child process-free path (the function reads $_COOKIE the first time)
$code = '$_COOKIE["mcupr_cc"]=rawurlencode(json_encode(array("v"=>1,"t"=>5,"p"=>1,"s"=>0,"e"=>1))); require ' . var_export( rtrim( $site, '/' ) . '/wp-load.php', true ) . '; echo json_encode(array(mcupr_cc_allows("preferences"),mcupr_cc_allows("statistics"),mcupr_cc_allows("embeds"),mcupr_cc_allows("necessary")));';
$out = trim( (string) shell_exec( 'php -r ' . escapeshellarg( $code ) . ' 2>/dev/null' ) );
t( '[true,false,true,true]' === $out, 'a saved choice is read: preferences yes, statistics no, embeds yes (' . $out . ')' );
foreach ( array( 'old version' => array( 'v' => 99, 'p' => 1, 's' => 1, 'e' => 1 ), 'rubbish' => 'not json' ) as $name => $val ) {
	$c   = '$_COOKIE["mcupr_cc"]=' . ( is_array( $val ) ? 'rawurlencode(json_encode(' . var_export( $val, true ) . '))' : var_export( $val, true ) ) . '; require ' . var_export( rtrim( $site, '/' ) . '/wp-load.php', true ) . '; echo json_encode(array(mcupr_cc_allows("preferences"),mcupr_cc_allows("embeds")));';
	$out = trim( (string) shell_exec( 'php -r ' . escapeshellarg( $c ) . ' 2>/dev/null' ) );
	t( '[false,false]' === $out, 'a cookie of another version or rubbish counts as no choice (' . $name . ')' );
}

// 2. cookies that are kept longer only with permission
t( 0 === apply_filters( 'mcupr_cookie_expires', time() + 3600, 'mcupr_lang' ) && 0 === apply_filters( 'mcupr_cookie_expires', time() + 3600, 'mcupr_seen' ) && 0 === apply_filters( 'mcupr_cookie_expires', time() + 3600, 'mcupr_welcome' ), 'without a yes the language, view-counter and welcome cookies are session cookies' );
$e = time() + 3600;
t( $e === apply_filters( 'mcupr_cookie_expires', $e, 'mcupr_pv_5' ) && $e === apply_filters( 'mcupr_cookie_expires', $e, 'mcupr_fbk' ), 'necessary cookies keep their time (poll vote, form key)' );
t( 0 === apply_filters( 'mcupr_cookie_expires', 0, 'mcupr_lang' ), 'a session cookie stays a session cookie' );

// 3. the list of what is kept matches the code
$inv   = mcupr_cc_inventory();
$names = implode( ' ', array_map( static function ( $r ) { return $r[0]; }, $inv ) );
$files = array_merge( glob( $root . '/inc/*.php' ), glob( $root . '/*.php' ), glob( $root . '/assets/js/*.js' ) );
$found = array();
foreach ( $files as $f ) {
	$src = (string) file_get_contents( $f );
	if ( preg_match_all( "/mcupr_set_cookie\(\s*'([a-z_]+)'/", $src, $m ) ) { foreach ( $m[1] as $n ) { $found[ $n ] = basename( $f ); } }
	if ( preg_match_all( "/(?:localStorage|store)\.?\(?\s*(?:getItem|setItem)?\(?'(mcupr_[a-z_]+)'/", $src, $m ) ) { foreach ( $m[1] as $n ) { $found[ $n ] = basename( $f ); } }
	if ( preg_match_all( "/(?:KEY|K) = '(mcupr_[a-z_]+)'/", $src, $m ) ) { foreach ( $m[1] as $n ) { $found[ $n ] = basename( $f ); } }
}
$skip = array( 'mcupr_consent', 'mcupr_preload_test', 'mcupr_pl', 'mcupr_cc' ); // the old notice key and test / session helpers (mcupr_cc is listed)
$miss = array();
foreach ( $found as $n => $f ) {
	if ( in_array( $n, $skip, true ) ) { continue; }
	$base = rtrim( $n, '_' );
	$ok   = false !== strpos( $names, $n ) || ( preg_match( '/^(mcupr_pv|mcupr_bk)_/', $n ) && false !== strpos( $names, substr( $n, 0, strrpos( $n, '_' ) ) ) ) || ( 0 === strpos( $n, 'mcupr_bk_' ) && false !== strpos( $names, 'mcupr_bk_*' ) ) || ( 0 === strpos( $n, 'mcupr_pv_' ) && false !== strpos( $names, 'mcupr_pv_*' ) );
	if ( ! $ok ) { $miss[] = $n . ' (' . $f . ')'; }
}
t( ! $miss, 'every cookie and stored key of the theme is in the list the visitor sees' . ( $miss ? ': missing ' . implode( ', ', $miss ) : ' (' . count( $found ) . ' found in the code)' ) );
t( count( array_filter( $inv, static function ( $r ) { return 'necessary' === $r[1]; } ) ) >= 5 && count( array_filter( $inv, static function ( $r ) { return 'embeds' === $r[1]; } ) ) >= 3, 'the list has the necessary kind and the embedded content of other websites' );

// 4. the words: every key in four languages, the table in every language
$strings = require $root . '/inc/strings.php';
$keys    = array();
foreach ( $inv as $r ) { $keys[] = $r[3]; $keys[] = $r[4]; }
foreach ( array( 'cc_title', 'cc_msg', 'cc_intro', 'cc_accept_all', 'cc_reject', 'cc_settings', 'cc_save', 'cc_close', 'cc_footer', 'cc_saved', 'cc_always', 'cc_show', 'cc_nec_t', 'cc_nec_d', 'cc_pref_t', 'cc_pref_d', 'cc_stat_t', 'cc_stat_d', 'cc_emb_t', 'cc_emb_d', 'cc_h_name', 'cc_h_purpose', 'cc_h_dur' ) as $k ) { $keys[] = $k; }
$bad = array();
foreach ( array_unique( $keys ) as $k ) { if ( ! isset( $strings[ $k ] ) || 4 !== count( $strings[ $k ] ) || count( array_filter( array_map( 'trim', $strings[ $k ] ) ) ) !== 4 ) { $bad[] = $k; } }
t( ! $bad, 'every word of the banner, the dialog and the list is there in Thai, English, Chinese and Japanese' . ( $bad ? ': ' . implode( ', ', $bad ) : ' (' . count( array_unique( $keys ) ) . ' texts)' ) );
foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) {
	$h = mcupr_cc_table_html( 'necessary', $l );
	t( false !== strpos( $h, '<table class="cc-table">' ) && false !== strpos( $h, 'mcupr_cc' ) && false !== strpos( $h, esc_html( $strings['cc_h_name'][ array_search( $l, array( 'th', 'en', 'zh', 'ja' ), true ) ] ) ), 'the table of the necessary cookies is built in ' . $l );
}

// 5. shortcodes, the footer button, the head script
t( false !== strpos( do_shortcode( '[mcupr_cookie_settings]' ), 'data-cc-open' ), '[mcupr_cookie_settings] is a button that opens the settings' );
$tb = do_shortcode( '[mcupr_cookie_table]' );
t( 4 === substr_count( $tb, '<table' ) && 1 === substr_count( do_shortcode( '[mcupr_cookie_table kind="statistics"]' ), '<table' ), '[mcupr_cookie_table] is one table for each kind, or only the kind asked for' );
t( false !== strpos( mcupr_cc_button( 'x' ), 'class="x"' ), 'the footer button takes a class' );
ob_start(); mcupr_cc_head(); $head = ob_get_clean();
t( '' === $head || ( false !== strpos( $head, 'mcuprConsent' ) && false !== strpos( $head, 'needs-consent' ) && ! preg_match( '/<script[^>]*src=/', $head ) ), 'the head script is inline, small (' . strlen( $head ) . ' bytes) and sets up window.mcuprConsent' );
t( strlen( $head ) < 3500, 'the head script stays under 3.5 KB' );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
