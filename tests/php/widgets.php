<?php
/**
 * The theme's Recent Posts widget (inc/widgets.php): titles cut to 30 characters, "Read more..." link.
 *   php php/widgets.php [path-to-site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site';
require rtrim( $site, '/' ) . '/wp-load.php';

$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$long  = 'ประกาศมหาวิทยาลัยมหาจุฬาลงกรณราชวิทยาลัย วิทยาเขตแพร่ เรื่อง รายชื่อผู้มีสิทธิ์สอบคัดเลือกเข้าศึกษาต่อ';
$short = 'ข่าวสั้น';
$a = wp_insert_post( array( 'post_title' => wp_slash( $long ), 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 5 ) ) );
$b = wp_insert_post( array( 'post_title' => $short, 'post_status' => 'publish', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 10 ) ) );

$s = mcupr_short_title( $long );
t( mb_strlen( $s ) <= 31 && '…' === mb_substr( $s, -1 ), 'long title is cut to 30 characters + … (' . $s . ')' );
t( $short === mcupr_short_title( $short ), 'a short title is left alone' );
t( 'abc…' === mcupr_short_title( 'abcdef', 3 ), 'cut position follows the limit' );
// never leave a Thai tone mark / vowel hanging at the start of the cut: cut right before a combining mark must extend
$t = str_repeat( 'ก', 29 ) . 'ก' . '่' . 'ข';   // 30th char is ก, 31st is the tone mark
t( preg_match( '/^[ก]{30}\x{0E48}…$/u', mcupr_short_title( $t ) ) === 1, 'a tone mark is never separated from its consonant' );

ob_start();
the_widget( 'WP_Widget_Recent_Posts', array( 'title' => 'เรื่องล่าสุด', 'number' => 5, 'show_date' => true ), array( 'before_widget' => '<section class="widget">', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>' ) );
$html = ob_get_clean();
t( strpos( $html, 'class="mcupr-recent"' ) !== false && strpos( $html, 'เรื่องล่าสุด' ) !== false, 'widget is drawn by the theme with its title' );
t( strpos( $html, $long ) === false || strpos( $html, 'aria-label="' ) !== false, 'the full long title is not printed as link text' );
t( substr_count( $html, 'class="rp-more"' ) >= 2 && strpos( $html, 'อ่านต่อ...' ) !== false, 'every item has an "อ่านต่อ..." link' );
t( strpos( $html, 'aria-label="อ่านต่อ: ' . $long ) !== false, 'the Read more link keeps the full title as its accessible name' );
t( strpos( $html, 'class="rp-date"' ) !== false, 'date shown when enabled' );
t( preg_match( '/<a class="rp-title" href="[^"]+">' . preg_quote( $s, '/' ) . '<\/a>/u', $html ) === 1, 'visible title is the short one' );
ob_start();
the_widget( 'WP_Widget_Recent_Posts', array( 'number' => 2 ), array( 'before_widget' => '<section>', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>' ) );
t( strpos( ob_get_clean(), 'rp-date' ) === false, 'no date when disabled' );

wp_delete_post( $a, true ); wp_delete_post( $b, true );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
