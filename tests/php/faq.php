<?php
/** FAQ: shortcode, groups, open modes, structured data, translation.   php php/faq.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
if ( isset( $argv[2] ) && 'en' === $argv[2] ) { $_GET['lang'] = 'en'; } // second run: the visitor reads English
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
if ( isset( $argv[2] ) && 'en' === $argv[2] ) {
	$q = get_posts( array( 'post_type' => 'mcupr_faq', 'name' => 'faq-1' ) )[0];
	echo mcupr_lang() . '|' . get_the_title( $q ) . '|' . ( false !== strpos( do_shortcode( '[mcupr_faq open="all"]' ), 'See the admissions page.' ) ? 'answer-en' : 'answer-th' ) . "\n";
	exit( 0 );
}
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$h = do_shortcode( '[mcupr_faq]' );
t( substr_count( $h, '<details class="faq-item"' ) === 8 && substr_count( $h, 'class="faq-h"' ) === 3, 'faq: 8 questions in 3 category groups (imported)' );
t( false !== strpos( $h, 'data-faq-find' ) && strpos( $h, ' open>' ) === false && strpos( $h, '" open' ) === false, 'faq: search box when there are more than 4 questions; all closed by default' );
t( substr_count( do_shortcode( '[mcupr_faq open="all"]' ), '<details class="faq-item" open>' ) === 8 && substr_count( do_shortcode( '[mcupr_faq open="first"]' ), '<details class="faq-item" open>' ) === 1, 'faq: open="all" and open="first"' );
$one = do_shortcode( '[mcupr_faq category="site"]' );
t( substr_count( $one, '<details class="faq-item"' ) === 2 && false === strpos( $one, 'data-faq-find' ) && false === strpos( $one, 'class="faq-h"' ), 'faq: one category – only its questions, no heading, no search box for a short list' );
t( false !== strpos( $h, 'ld+json' ) && false !== strpos( $h, '"FAQPage"' ) && false !== strpos( $h, '"acceptedAnswer"' ), 'faq: FAQPage structured data' );
$ld = json_decode( preg_replace( '/^.*<script type="application\/ld\+json">(.*?)<\/script>.*$/s', '$1', $h ), true );
t( is_array( $ld ) && count( $ld['mainEntity'] ) === 8 && false === strpos( wp_json_encode( $ld ), '<p>' ), 'faq: the data is valid JSON, answers without tags' );
t( false === strpos( $h, 'mcupr_org' ), 'faq: shortcodes inside an answer are processed' );
$q = get_posts( array( 'post_type' => 'mcupr_faq', 'name' => 'faq-1' ) )[0];
update_post_meta( $q->ID, '_mcupr_en_title', 'How do I apply?' ); update_post_meta( $q->ID, '_mcupr_en_content', '<p>See the admissions page.</p>' );
$en = trim( (string) shell_exec( PHP_BINARY . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $site ) . ' en 2>/dev/null' ) );
$en = trim( (string) preg_replace( '/^.*(en\|How do I apply\?\|answer-en).*$/s', '$1', $en ) );
t( 'en|How do I apply?|answer-en' === $en, 'faq: the question and the answer can be translated' );
delete_post_meta( $q->ID, '_mcupr_en_title' ); delete_post_meta( $q->ID, '_mcupr_en_content' );
t( false !== strpos( do_shortcode( '[mcupr_faq category="nothing-here"]' ), 'text-muted' ), 'faq: an empty category says so' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
