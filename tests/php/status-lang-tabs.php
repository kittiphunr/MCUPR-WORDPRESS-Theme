<?php
/** The welcome & maintenance page: one tab per language instead of every language stacked.   php php/status-lang-tabs.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
wp_set_current_user( 1 );
ob_start();
mcupr_status_page();
$html = ob_get_clean();
$L    = array_keys( mcupr_ui_languages() );

preg_match_all( '/data-lang-tab="([a-z-]+)"/', $html, $tabs );
t( $L === $tabs[1], 'tabs: one tab for each language, in the language order' );
t( 1 === substr_count( $html, 'role="tablist"' ) && false !== strpos( $html, 'class="mcupr-lang-tabs" role="tablist"' ) && preg_match( '/mcupr-lang-tabs"[^>]*\bhidden>/', $html ), 'tabs: one bar, hidden until the script shows it (without script every language stays visible)' );
foreach ( array( 'm_title', 'm_msg', 'w_title', 'w_msg', 'w_btn' ) as $f ) {
	$n = preg_match_all( '/<p class="mcupr-lang-row" data-lang="([a-z-]+)">.*?name="' . $f . '\[([a-z-]+)\]"/s', $html, $m );
	$ok = count( $L ) === $n;
	foreach ( $m[1] as $i => $c ) { $ok = $ok && $c === $m[2][ $i ]; }
	t( $ok, "rows: $f has one row for each language, tagged with the same language as its field" );
}
// saving still reads every language, tabs or not
t( false !== strpos( file_get_contents( get_theme_file_path( 'inc/site-status.php' ) ), "localStorage.setItem(key,c)" ), 'script: the chosen language is remembered between visits' );
t( false !== strpos( $html, 'name="m_title[th]"' ) && false !== strpos( $html, 'name="w_btn[ja]"' ), 'form: every language field is still in the form (hidden fields are submitted)' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
