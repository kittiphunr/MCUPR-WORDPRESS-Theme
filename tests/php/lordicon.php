<?php
/** Animated icons (Lordicon): the list in the Customizer is read safely, an icon that is not listed is unchanged, a listed one is wrapped around the normal SVG.   php php/lordicon.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$m = mcupr_lord_parse( "news = WxNxIaNo\n# a comment\npin: https://example.org/a/pin.json  # tail\nphone = /wp-content/uploads/p.json\nbogus = abcdefgh\nmail = javascript:alert(1)\nsearch = http://example.org/x.json\ncap = https://example.org/x.js\nbulb = a b\n= abcdefgh\n" );
t( isset( $m['news'] ) && 'https://cdn.lordicon.com/wxnxiano.json' === $m['news'], 'a code of lordicon.com becomes its address (lower case)' );
t( isset( $m['pin'] ) && 'https://example.org/a/pin.json' === $m['pin'] && isset( $m['phone'] ) && '/wp-content/uploads/p.json' === $m['phone'], 'a https address or a path of the site ending .json is kept; # starts a comment; ":" works like "="' );
t( ! isset( $m['bogus'] ) && ! isset( $m['mail'] ) && ! isset( $m['search'] ) && ! isset( $m['cap'] ) && ! isset( $m['bulb'] ), 'unknown icon names, javascript:, http:, a file that is not .json and a value with a space are dropped' );
t( 3 === count( $m ), 'only the three good lines are left (' . count( $m ) . ')' );

// off by default: nothing changes
$plain = mcupr_icon( 'news', 22 );
t( false === strpos( $plain, 'class="lord"' ) && 0 === strpos( $plain, '<svg' ), 'with nothing listed an icon is the plain SVG' );

// a listed icon is wrapped (the list is read once per request, so check in a fresh process)
set_theme_mod( 'mcupr_lord_map', "news = wxnxiano" ); set_theme_mod( 'mcupr_lord_on', true ); set_theme_mod( 'mcupr_lord_trigger', 'loop' );
$php = 'require ' . var_export( rtrim( $site, '/' ) . '/wp-load.php', true ) . '; echo mcupr_icon( "news", 22 ), "|", mcupr_icon( "pin", 22 ), "|", wp_json_encode( mcupr_lord_map() ), "|", mcupr_lord_trigger();';
$f = sys_get_temp_dir() . '/lord-' . getmypid() . '.php'; file_put_contents( $f, '<?php ' . $php );
$out = explode( '|', trim( shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>/dev/null' ) ) );
t( isset( $out[0] ) && false !== strpos( $out[0], 'class="lord" data-lord="https://cdn.lordicon.com/wxnxiano.json" data-lord-trigger="loop" style="--lord:22px"><svg' ) && '</span>' === substr( $out[0], -7 ), 'a listed icon is the SVG inside a .lord box with its address, trigger and size' );
t( isset( $out[1] ) && 0 === strpos( $out[1], '<svg' ), 'an icon that is not listed stays the plain SVG' );

set_theme_mod( 'mcupr_lord_on', false );
file_put_contents( $f, '<?php ' . $php );
$off = explode( '|', trim( shell_exec( 'php ' . escapeshellarg( $f ) . ' 2>/dev/null' ) ) );
t( isset( $off[0] ) && 0 === strpos( $off[0], '<svg' ) && '[]' === $off[2], 'switched off, the list is ignored' );

unlink( $f );
remove_theme_mod( 'mcupr_lord_map' ); remove_theme_mod( 'mcupr_lord_on' ); remove_theme_mod( 'mcupr_lord_trigger' );
echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
