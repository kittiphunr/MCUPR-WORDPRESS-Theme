<?php
/** What search engines are told: the description of a page, and the schema.org data (organisation, website, breadcrumb).   php php/seo-data.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$go = static function ( $args ) {
	$q = new WP_Query( $args );
	$GLOBALS['wp_query'] = $q; $GLOBALS['wp_the_query'] = $q;
	$GLOBALS['post'] = $q->post ?? null;
	if ( $q->post ) { setup_postdata( $q->post ); }
	return $q;
};
$graph = static function () {
	ob_start(); mcupr_seo_jsonld(); $h = ob_get_clean();
	if ( ! preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $h, $m ) ) { return array(); }
	$d = json_decode( $m[1], true );
	return is_array( $d ) && ( $d['@context'] ?? '' ) === 'https://schema.org' ? ( $d['@graph'] ?? array() ) : array();
};
$by = static function ( $g, $type ) { foreach ( $g as $n ) { if ( ( $n['@type'] ?? '' ) === $type ) { return $n; } } return null; };

$keep = array();
foreach ( array( 'mcupr_org_tel', 'mcupr_org_email', 'mcupr_address_th', 'mcupr_social_facebook' ) as $k ) { $keep[ $k ] = get_theme_mod( $k, null ); }
$keep_opt = array( get_option( 'show_on_front' ), get_option( 'page_on_front' ) );
update_option( 'show_on_front', 'posts' ); update_option( 'page_on_front', 0 );
set_theme_mod( 'mcupr_org_tel', '0-5464-6273' ); set_theme_mod( 'mcupr_org_email', 'phrae@example.org' );
set_theme_mod( 'mcupr_address_th', '๑๑๑ หมู่ ๕ ตำบลแม่คำมี อำเภอเมืองแพร่ จังหวัดแพร่ ๕๔๐๐๐' ); set_theme_mod( 'mcupr_social_facebook', 'https://www.facebook.com/example' );

// 1. the home page: organisation and website
$q0 = $go( array( 'post_type' => 'post' ) ); $q0->is_home = true; $q0->is_archive = false; $q0->is_singular = false;
t( is_front_page(), 'setup: this is the front page' );
$g   = $graph();
$org = $by( $g, 'CollegeOrUniversity' );
t( $org && $org['name'] === mcupr_org_name() && $org['url'] === home_url( '/' ), 'home: the organisation is a university with its name and address of the site' );
t( $org && '0-5464-6273' === $org['telephone'] && 'phrae@example.org' === $org['email'] && 'TH' === $org['address']['addressCountry'] && false !== strpos( $org['address']['streetAddress'], 'แม่คำมี' ), 'home: telephone, email and postal address are there' );
t( $org && in_array( 'https://www.facebook.com/example', (array) ( $org['sameAs'] ?? array() ), true ), 'home: the social pages are listed as the same organisation' );
$web = $by( $g, 'WebSite' );
t( $web && $web['potentialAction']['@type'] === 'SearchAction' && false !== strpos( $web['potentialAction']['target']['urlTemplate'], '{search_term_string}' ) && $web['publisher']['@id'] === $org['@id'], 'home: the website has its search box and points to the organisation' );
t( null === $by( $g, 'BreadcrumbList' ), 'home: no breadcrumb on the home page' );
remove_theme_mod( 'mcupr_org_tel' ); remove_theme_mod( 'mcupr_org_email' ); remove_theme_mod( 'mcupr_address_th' ); remove_theme_mod( 'mcupr_social_facebook' );
$org2 = $by( $graph(), 'CollegeOrUniversity' );
t( $org2 && ! isset( $org2['telephone'], $org2['email'], $org2['address'], $org2['sameAs'] ), 'home: nothing is printed for what the site has not filled in' );
add_filter( 'mcupr_seo_org_type', static function () { return 'Organization'; } );
t( null !== $by( $graph(), 'Organization' ), 'home: the type can be changed with a filter' );
remove_all_filters( 'mcupr_seo_org_type' );

// 2. an inner page: the way back
$h = mcupr_so_find( 'history' );
$go( array( 'page_id' => $h->ID ) );
$bc = $by( $graph(), 'BreadcrumbList' );
$names = $bc ? wp_list_pluck( $bc['itemListElement'], 'name' ) : array();
t( $bc && $names[0] === mcupr_org_name() && end( $names ) === trim( wp_strip_all_tags( get_the_title( $h->ID ) ) ) && count( $names ) >= 2, 'page: the breadcrumb runs from the home page to this page (' . implode( ' › ', $names ) . ')' );
t( $bc && array_keys( $bc['itemListElement'] ) === range( 0, count( $names ) - 1 ) && wp_list_pluck( $bc['itemListElement'], 'position' ) === range( 1, count( $names ) ), 'page: the positions count from 1 without gaps' );
t( null === $by( $graph(), 'CollegeOrUniversity' ), 'page: the organisation is not repeated on inner pages' );
add_filter( 'mcupr_seo', '__return_false' );
t( array() === $graph(), 'off: the whole thing can be switched off with the mcupr_seo filter' );
remove_all_filters( 'mcupr_seo' );

// 3. a script cannot be closed from inside the data
$id = wp_insert_post( array( 'post_title' => 'x</script><b>y', 'post_status' => 'publish', 'post_type' => 'page', 'post_parent' => $h->ID ) );
$go( array( 'page_id' => $id ) );
ob_start(); mcupr_seo_jsonld(); $raw = ob_get_clean();
t( 1 === substr_count( $raw, '</script>' ), 'safety: a title with </script> in it cannot end the data block' );
wp_delete_post( $id, true );

// 4. the description of the starter pages (what the meta description shows)
foreach ( array( 'about', 'history', 'vision', 'programs', 'location' ) as $slug ) {
	$p = mcupr_so_find( $slug );
	$go( array( 'page_id' => $p->ID ) );
	$d = mcupr_seo_description();
	$ok = $p && mb_strlen( $d ) >= 70 && mb_strlen( $d ) <= 200 && ! preg_match( '/\[[^\]]+\]/u', $d ) && trim( (string) $p->post_excerpt ) !== '';
	t( $ok, "description: $slug has its own description (" . mb_strlen( $d ) . ' letters, no [blanks])' );
}

foreach ( $keep as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); }
update_option( 'show_on_front', $keep_opt[0] ); update_option( 'page_on_front', $keep_opt[1] );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
