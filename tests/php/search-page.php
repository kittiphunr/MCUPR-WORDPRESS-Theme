<?php
/** The search results page: title, empty search, the form on the page, the kind of each result.   php php/search-page.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$main = static function ( $args ) { global $wp_the_query, $wp_query; $wp_the_query = $wp_query = new WP_Query(); $wp_query->query( $args ); return $wp_query; };

// 1. an empty search lists nothing; a real one lists
$q = $main( array( 's' => '' ) );
t( $q->is_search() && 0 === count( $q->posts ), 'empty search: nothing is listed (WordPress would list every post)' );
$q = $main( array( 's' => '   ' ) );
t( 0 === count( $q->posts ), 'empty search: spaces only is empty too' );
$q = $main( array( 's' => 'ข่าว' ) );
t( $q->is_search() && count( $q->posts ) > 0, 'a real search lists its results (' . count( $q->posts ) . ' on the page)' );

// 2. the title in the language of the page
$_GET['s'] = 'ข่าว'; $q = $main( array( 's' => 'ข่าว' ) );
set_query_var( 's', 'ข่าว' );
$p = apply_filters( 'document_title_parts', array( 'title' => 'Search Results for “ข่าว”', 'site' => 'x' ) );
t( 0 === strpos( $p['title'], mcupr_t( 'search_for' ) . ': ' ) && false !== strpos( $p['title'], 'ข่าว' ) && false === strpos( $p['title'], 'Search Results' ), 'title: "' . $p['title'] . '" in the language of the page, not WordPress\'s English' );
$q = $main( array( 's' => '' ) ); set_query_var( 's', '' );
$p = apply_filters( 'document_title_parts', array( 'title' => 'Search Results for “”', 'site' => 'x' ) );
t( mcupr_t( 'search_title' ) === $p['title'], 'title: an empty search is just "' . $p['title'] . '"' );
unset( $_GET['s'] );

// 3. the form of the page
$q = $main( array( 's' => 'ข่าว <b>"x"</b>' ) );
set_query_var( 's', 'ข่าว <b>"x"</b>' );
ob_start(); mcupr_search_page_form(); $f = ob_get_clean();
t( 1 === substr_count( $f, 'class="sp-form"' ) && 1 === substr_count( $f, 'role="search"' ) && 1 === substr_count( $f, 'name="s"' ) && false !== strpos( $f, 'data-sp' ), 'form: one search form, with the landmark, the field and the script hook' );
t( false === strpos( $f, '<b>"x"' ) && false !== strpos( $f, esc_attr( 'ข่าว <b>"x"</b>' ) ), 'form: the words are put back in the field, escaped' );
t( preg_match( '/<label for="(sp-\d+)" class="sr-only">/', $f, $m ) && false !== strpos( $f, 'id="' . $m[1] . '"' ), 'form: the field has a label (hidden, for screen readers)' );
$scopes = mcupr_hero_search_scopes();
t( substr_count( $f, 'name="post_type"' ) === count( $scopes ) && 1 === substr_count( $f, ' checked' ) && false !== strpos( $f, 'value="" checked' ), 'form: the choices are radio buttons, "everything" is on when none is asked' );
$_GET['post_type'] = 'page';
ob_start(); mcupr_search_page_form(); $f2 = ob_get_clean();
t( false !== strpos( $f2, 'value="page" checked' ) && 1 === substr_count( $f2, ' checked' ), 'form: the kind that was asked is the one that is on' );
$_GET['post_type'] = 'evil'; ob_start(); mcupr_search_page_form(); $f3 = ob_get_clean(); unset( $_GET['post_type'] );
t( false !== strpos( $f3, 'value="" checked' ), 'form: an unknown kind in the address is treated as everything' );
t( false !== strpos( $f, '<legend class="sr-only">' ), 'form: the choices are a group with a name' );

// 4. the popular words
$h = mcupr_search_popular_html();
t( '' === $h || ( false !== strpos( $h, 'class="sr-chips"' ) && false !== strpos( $h, 'href="' ) ), 'popular words: chips that search again (or nothing when there are none)' );

// 5. a card says what kind of page it is
$po = get_posts( array( 'numberposts' => 1, 'post_type' => 'post' ) );
if ( $po ) {
	global $post; $post = $po[0]; setup_postdata( $post );
	ob_start(); get_template_part( 'template-parts/card', null, array( 'heading' => 'h2', 'kind' => 'KIND<x>' ) ); $c = ob_get_clean();
	t( false !== strpos( $c, 'class="news-kind">KIND&lt;x&gt;<' ), 'card: the kind of the result is shown, escaped' );
	ob_start(); get_template_part( 'template-parts/card', null, array( 'heading' => 'h2' ) ); $c2 = ob_get_clean();
	t( false === strpos( $c2, 'news-kind' ), 'card: elsewhere (no kind given) the card is as it was' );
	wp_reset_postdata();
}
foreach ( array( 'sp_hint' ) as $k ) { foreach ( array( 'th', 'en', 'zh', 'ja' ) as $l ) { if ( mcupr_t( $k, $l ) === $k || '' === mcupr_t( $k, $l ) ) { t( false, "language $l: $k" ); } } }
t( true, 'languages: the hint of the page is in all four languages' );

// 6. the template and the stylesheet
$tpl = (string) file_get_contents( get_theme_file_path( 'search.php' ) );
t( false !== strpos( $tpl, 'mcupr_search_page_form()' ) && false === strpos( $tpl, 'get_search_form' ) && false !== strpos( $tpl, "'kind'" ), 'template: the form is on the page, the results carry their kind' );
$css = preg_replace( '/\s+/', '', (string) file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) ) );
foreach ( array( '.sp-form', '.sp-chip', '.sp-field', '.news-kind', '.sp-status' ) as $needle ) { t( false !== strpos( $css, $needle ), "css: $needle is in the stylesheet" ); }

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
