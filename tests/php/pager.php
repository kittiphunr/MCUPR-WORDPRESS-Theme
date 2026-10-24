<?php
/**
 * The page links of a list (mcupr_pager_html in inc/template-tags.php): Previous / Next as buttons that stay when there is no page to
 * go to, the numbers, "Page 2 of 8", the same bar for the news list, the offices and the forum.   php php/pager.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

function items( $current, $total ) { return paginate_links( array( 'base' => home_url( '/page/%#%/' ), 'format' => '', 'current' => $current, 'total' => $total, 'mid_size' => 1, 'type' => 'array', 'prev_text' => 'prev', 'next_text' => 'next' ) ); }

t( '' === mcupr_pager_html( items( 1, 1 ), 1, 1 ) && '' === mcupr_pager_html( array(), 1, 5 ), 'one page needs no page links' );

$h = mcupr_pager_html( items( 1, 5 ), 1, 5 );
t( false !== strpos( $h, 'class="prev page-numbers is-off"' ) && false !== strpos( $h, 'aria-disabled="true"' ) && preg_match( '#<a class="next page-numbers" href="[^"]*/page/2/" rel="next">#', $h ), 'first page: Previous is there but off (the bar does not jump), Next goes to page 2' );
t( false !== strpos( $h, 'aria-current="page"' ) && 1 === preg_match( '#<span aria-current="page" class="page-numbers current">1</span>#', $h ), 'the current page is marked for screen readers' );
$h = mcupr_pager_html( items( 3, 5 ), 3, 5 );
t( preg_match( '#<a class="prev page-numbers" href="[^"]*/page/2/" rel="prev">#', $h ) && preg_match( '#<a class="next page-numbers" href="[^"]*/page/4/" rel="next">#', $h ), 'a middle page: Previous goes to 2 and Next to 4' );
$h = mcupr_pager_html( items( 5, 5 ), 5, 5 );
t( preg_match( '#<a class="prev page-numbers" href="[^"]*/page/4/"#', $h ) && false !== strpos( $h, 'class="next page-numbers is-off"' ), 'last page: Next is off' );
t( false !== strpos( $h, 'class="pager-info"' ) && false !== strpos( $h, esc_html( sprintf( mcupr_t( 'page_of' ), 5, 5 ) ) ) && false !== strpos( $h, 'aria-label="' . esc_attr( mcupr_t( 'pagination' ) ) . '"' ), '"Page 5 of 5" under the bar and the navigation is named' );
t( false === strpos( $h, '>prev<' ) && false === strpos( $h, '>next<' ) && false !== strpos( $h, '<svg' ), 'the words are the localised ones and the arrows are drawn' );
t( 1 === substr_count( $h, '<nav ' ) && false !== strpos( $h, 'class="navigation pagination pager"' ), 'one navigation landmark, with the classes the style uses' );
$all = include MCUPR_DIR . '/inc/strings.php';
foreach ( array( 'th', 'en', 'zh', 'ja' ) as $i => $l ) {
	$row = $all['page_of'];
	t( false !== strpos( (string) $row[ $i ], '%1$d' ) && false !== strpos( (string) $row[ $i ], '%2$d' ), 'page_of has both numbers in ' . $l );
}
$long = mcupr_pager_html( items( 6, 12 ), 6, 12 );
t( substr_count( $long, 'class="page-numbers dots"' ) >= 1 && false !== strpos( $long, '>12<' ) && false !== strpos( $long, '>1<' ), 'a long list has the first and last page and dots between' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
