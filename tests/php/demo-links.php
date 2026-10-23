<?php
/**
 * The demo pack (starter file, settings, home demo, offices demo) must only link to places that open, and each link must go
 * where its label says. Runs on a throw-away copy of the starter site (the same code and plugins, its own database and uploads),
 * served on its own port, so the site the browser tests use is left alone.
 *   php php/demo-links.php .site-starter      (after php/starter-import.php)
 */
$site = rtrim( isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter', '/' );
$site = realpath( $site );
$port = (int) ( getenv( 'PORT' ) ?: 8090 ) + 6;
$copy = dirname( $site ) . '/.site-links';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// ---- the copy: symlinks to everything except the database, the uploads and the config
exec( 'rm -rf ' . escapeshellarg( $copy ) );
mkdir( $copy . '/wp-content', 0777, true );
// (the PHP files at the top are copied, not linked: PHP resolves a link and WordPress would find the real site's config and database)
foreach ( scandir( $site ) as $e ) {
	if ( in_array( $e, array( '.', '..', 'database', 'wp-content', 'wp-config.php' ), true ) ) {
		continue;
	}
	if ( is_dir( "$site/$e" ) ) {
		symlink( "$site/$e", "$copy/$e" );
	} else {
		copy( "$site/$e", "$copy/$e" );
	}
}
foreach ( scandir( "$site/wp-content" ) as $e ) {
	if ( in_array( $e, array( '.', '..', 'uploads' ), true ) ) {
		continue;
	}
	if ( is_dir( "$site/wp-content/$e" ) ) {
		symlink( "$site/wp-content/$e", "$copy/wp-content/$e" );
	} else {
		copy( "$site/wp-content/$e", "$copy/wp-content/$e" );
	}
}
mkdir( "$copy/wp-content/uploads", 0777, true );
exec( 'cp -r ' . escapeshellarg( "$site/database" ) . ' ' . escapeshellarg( "$copy/database" ) );
copy( "$site/wp-config.php", "$copy/wp-config.php" );
$cfg = preg_replace( "/: '\\d+'; \\/\\/ X-Public-Port/", ": '$port'; // X-Public-Port", file_get_contents( "$copy/wp-config.php" ), -1, $n );
file_put_contents( "$copy/wp-config.php", $cfg );
t( 1 === $n, 'the copy of the site is served on its own port (' . $port . ')' );
$base = "http://localhost:$port";

// ---- put the whole demo pack into the copy (in a child process: it is a different site from this one)
$apply = <<<'PHP'
<?php
require $argv[1] . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
$s = json_decode( file_get_contents( $argv[2] . '/mcupr-starter-settings.json' ), true );
mcupr_ss_apply( $s );
mcupr_demo_apply( mcupr_demo_data() );
mcupr_od_apply( mcupr_od_data() );
flush_rewrite_rules( false );
echo 'ok';
PHP;
file_put_contents( "$copy/apply-demo.php", $apply );
$out = shell_exec( 'php ' . escapeshellarg( "$copy/apply-demo.php" ) . ' ' . escapeshellarg( $copy ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/mcupr/starter' ) . ' 2>&1' );
t( false !== strpos( (string) $out, 'ok' ), 'the demo pack is applied to the copy' );

// ---- its own server
$proc = proc_open( 'exec php -S localhost:' . $port . ' -t ' . escapeshellarg( $copy ), array( 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes, null, array( 'PHP_CLI_SERVER_WORKERS' => '0' ) + $_ENV ); // one process, asked one thing at a time: nothing is left running when it is stopped
register_shutdown_function( function () use ( $proc, $copy ) { proc_terminate( $proc ); if ( ! getenv( 'KEEP_LINKS' ) ) { exec( 'rm -rf ' . escapeshellarg( $copy ) ); } } );
for ( $i = 0; $i < 40; $i++ ) {
	if ( @file_get_contents( "$base/" ) !== false ) { break; }
	usleep( 250000 );
}

function get( $url ) {
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 40 ) );
	$body = curl_exec( $ch );
	$r    = array( curl_getinfo( $ch, CURLINFO_HTTP_CODE ), curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL ), (string) $body );
	curl_close( $ch );
	return $r;
}
function norm( $u ) { return rtrim( urldecode( strtok( $u, '#' ) ), '/' ); }

// ---- every link of the pack: the menus, the pages and posts, the settings, and what the front page and the feature pages show
$links = array(); // url => where
$collect = function ( $href, $where ) use ( &$links, $base ) {
	$href = trim( html_entity_decode( $href ) );
	if ( '' === $href || '#' === $href[0] || preg_match( '#^(mailto:|tel:|javascript:)#', $href ) ) { return; }
	if ( '/' === $href[0] && 0 !== strpos( $href, '//' ) ) { $href = $base . $href; }
	if ( 0 !== strpos( $href, $base ) ) { return; } // addresses outside the site are not ours to open
	if ( preg_match( '#/wp-(admin|login)|\?s=|\?mcupr_(ics|print)|/feed/#', $href ) ) { return; }
	$links[ $href ][] = $where;
};
$dom_links = function ( $html, $where ) use ( $collect ) {
	if ( preg_match_all( '#<a\s[^>]*href=("|\')(.*?)\1#is', $html, $m ) ) {
		foreach ( $m[2] as $h ) { $collect( $h, $where ); }
	}
};
$dump = <<<'PHP'
<?php
require $argv[1] . '/wp-load.php';
$o = array( 'menus' => array(), 'content' => array(), 'mods' => array() );
foreach ( get_nav_menu_locations() as $loc => $id ) {
	foreach ( (array) wp_get_nav_menu_items( $id ) as $mi ) { $o['menus'][] = array( $loc, $mi->title, $mi->url ); }
}
foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $p ) {
	$o['content'][] = array( $p->post_type . ':' . $p->post_name, apply_filters( 'the_content', $p->post_content ) );
}
foreach ( get_theme_mods() as $k => $v ) { if ( is_string( $v ) && preg_match( '/_url$/', $k ) && '' !== $v ) { $o['mods'][ $k ] = $v; } }
$fp = get_posts( array( 'post_type' => 'page', 'name' => 'forum', 'post_status' => 'publish', 'numberposts' => 1 ) );
$o['page_link'] = array( apply_filters( 'the_content', '<p><a href="page:forum">f</a> <a href="page:no-such-page-x">g</a></p>' ), $fp ? get_permalink( $fp[0] ) : '' );
add_filter( 'pre_option_home', static function () { return 'http://localhost/sub'; } ); // a site in a sub-folder: links typed as "/events/" must stay inside it
$o['sub'] = apply_filters( 'the_content', '<p><a href="/events/">a</a> <a href="/sub/books/">b</a> <a href="https://example.org/x">c</a></p>' );
echo json_encode( $o );
PHP;
file_put_contents( "$copy/dump.php", $dump );
$d = json_decode( (string) shell_exec( 'php ' . escapeshellarg( "$copy/dump.php" ) . ' ' . escapeshellarg( $copy ) . ' 2>/dev/null | grep -v Deprecated' ), true );
t( is_array( $d ) && count( $d['menus'] ) > 40 && count( $d['content'] ) > 60, 'the copy has the pack: ' . ( $d ? count( $d['menus'] ) . ' menu items, ' . count( $d['content'] ) . ' pages and posts' : 'nothing' ) );
t( '' !== $d['page_link'][1] && false !== strpos( $d['page_link'][0], 'href="' . $d['page_link'][1] . '"' ) && false === strpos( $d['page_link'][0], 'page:' ), '"page:forum" typed in a page becomes the address the forum page really has (whether it sits under its parent or at the top); an unknown page goes to the home page' );
t( false !== strpos( $d['sub'], 'href="http://localhost/sub/events/"' ) && false !== strpos( $d['sub'], 'href="/sub/books/"' ) && false !== strpos( $d['sub'], 'href="https://example.org/x"' ), 'a site in a sub-folder: "/events/" typed in a page becomes /sub/events/; addresses already inside the folder or outside the site stay as they are' );
foreach ( $d['menus'] as $m ) { $collect( $m[2], 'menu ' . $m[0] . ': ' . $m[1] ); }
foreach ( $d['content'] as $c ) { $dom_links( $c[1], $c[0] ); }
foreach ( $d['mods'] as $k => $v ) { $collect( $v, "setting $k" ); }
foreach ( array( '/', '/events/', '/albums/', '/videos/', '/works/', '/books/', '/student/', '/request/', '/office/phrae-campus-office/' ) as $page ) {
	$r = get( $base . $page );
	$dom_links( $r[2], 'page ' . $page );
}
$bad = array();
foreach ( $links as $u => $where ) {
	$r = get( $u );
	if ( $r[0] >= 400 ) { $bad[] = $r[0] . ' ' . str_replace( $base, '', $u ) . ' <- ' . $where[0]; }
	elseif ( norm( $r[1] ) !== norm( $u ) ) { $bad[] = 'redirected ' . str_replace( $base, '', $u ) . ' -> ' . str_replace( $base, '', $r[1] ) . ' <- ' . $where[0]; }
}
t( count( $links ) > 100 && ! $bad, count( $links ) . ' internal links of the pack open, none is redirected elsewhere' . ( $bad ? ' (' . implode( '; ', array_slice( $bad, 0, 5 ) ) . ')' : '' ) );

// ---- each link goes where its label says
$by = array();
foreach ( $d['menus'] as $m ) { $by[ $m[0] ][ $m[1] ] = rtrim( str_replace( $base, '', $m[2] ), '/' ); }
t( '/events' === ( $by['primary_th']['ปฏิทินกิจกรรม'] ?? '' ), 'main menu: "ปฏิทินกิจกรรม" opens the events calendar (/events/)' );
t( '/events' === ( $by['primary_en']['Events calendar'] ?? '' ), 'English menu: "Events calendar" opens /events/' );
t( '/books' === ( $by['primary_th']['หนังสืออิเล็กทรอนิกส์'] ?? '' ), 'main menu: "หนังสืออิเล็กทรอนิกส์" opens /books/' );
t( '/works' === ( $by['primary_th']['ผลงานและรางวัล'] ?? '' ) && '/albums' === ( $by['primary_th']['อัลบั้มภาพ'] ?? '' ) && '/videos' === ( $by['primary_th']['วิดีโอ'] ?? '' ), 'main menu: works, albums and videos open their own lists' );
t( '/academics/calendar' === ( $by['quick']['ปฏิทินการศึกษา'] ?? '' ), 'quick links: "ปฏิทินการศึกษา" opens the academic calendar page' );
$mods = array_map( static function ( $u ) use ( $base ) { return rtrim( str_replace( $base, '', $u ), '/' ); }, $d['mods'] );
t( '/events' === ( $mods['mcupr_svc_4_url'] ?? '' ) && '/student' === ( $mods['mcupr_aud_2_url'] ?? '' ), 'home settings: the calendar tile opens /events/, the student card opens the student portal (/student/)' );
$home = get( $base . '/' )[2];
t( preg_match( '#href="[^"]*/events/"[^>]*>[^<]*ปฏิทินกิจกรรม#u', $home ) || preg_match( '#href="[^"]*/events/"#', $home ), 'the front page links to the events calendar' );
$quick = get( $base . '/' )[2];
t( false !== strpos( $quick, '/student/' ), 'the front page links to the student portal' );
// the page that lists the systems for students no longer guesses addresses
$sp = '';
foreach ( $d['content'] as $c ) { if ( 'page:student-portal' === $c[0] ) { $sp = $c[1]; } }
t( false !== strpos( $sp, '/student/' ) && false !== strpos( $sp, '/events/' ) && false === strpos( $sp, 'href="/calendar/"' ), 'student portal page: links the real portal, the events calendar and the academic calendar at its true address' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
