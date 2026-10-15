<?php
/** Videos: address parsing, the click-to-play player, saving, oEmbed, news posts.   php php/videos.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
define( 'MCUPR_NO_REMOTE', true );
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$yt = 'dQw4w9WgXcQ';
foreach ( array( "https://www.youtube.com/watch?v=$yt", "https://youtu.be/$yt", "https://m.youtube.com/watch?v=$yt&feature=share", "https://www.youtube.com/shorts/$yt", "https://www.youtube.com/embed/$yt", "https://www.youtube-nocookie.com/embed/$yt", "https://www.youtube.com/live/$yt" ) as $u ) {
	$v = mcupr_video_parse( $u );
	t( $v && 'youtube' === $v['provider'] && $yt === $v['id'] && 0 === strpos( $v['embed'], 'https://www.youtube-nocookie.com/embed/' . $yt ), 'parse: ' . $u );
}
$v = mcupr_video_parse( "https://youtu.be/$yt?t=1m30s" );
t( false !== strpos( $v['embed'], 'start=90' ) && false !== strpos( $v['watch'], 't=90s' ), 'parse: the start time is kept (1m30s = 90)' );
$v = mcupr_video_parse( 'https://vimeo.com/123456789' );
t( $v && 'vimeo' === $v['provider'] && 'https://player.vimeo.com/video/123456789?dnt=1&autoplay=1' === $v['embed'], 'parse: Vimeo, with do-not-track' );
t( mcupr_video_parse( 'https://example.org/clip.mp4' )['provider'] === 'file', 'parse: a direct .mp4 file' );
foreach ( array( '', 'javascript:alert(1)', 'https://evil.example/watch?v=' . $yt, 'https://www.youtube.com/watch?v=<script>', 'https://www.youtube.com/watch?v=a', 'ftp://youtu.be/' . $yt, 'https://youtube.com.evil.example/watch?v=' . $yt ) as $bad ) {
	t( false === mcupr_video_parse( $bad ), 'parse: refused ' . ( $bad ? $bad : '(empty)' ) );
}
$h = mcupr_video_html( "https://youtu.be/$yt", 'Open day' );
t( false !== strpos( $h, 'data-vid="youtube"' ) && false === strpos( $h, '<iframe' ) && false !== strpos( $h, 'class="vid-play"' ) && false !== strpos( $h, 'Open day' ), 'player: a cover with a play link, no iframe (nothing loaded before play)' );
t( '' === mcupr_video_html( 'https://evil.example/x' ), 'player: an unknown address gives nothing' );
t( false !== strpos( mcupr_video_html( 'https://example.org/a.mp4' ), '<video controls preload="none"' ), 'player: a file uses <video> without preloading' );
t( false !== strpos( do_shortcode( "[mcupr_video url=\"https://youtu.be/$yt\"]" ), 'vid-wrap' ) && '' === do_shortcode( '[mcupr_video url="nope"]' ), 'shortcode [mcupr_video]' );
t( false !== strpos( apply_filters( 'embed_oembed_html', '<iframe src="https://www.youtube.com/embed/x"></iframe>', "https://www.youtube.com/watch?v=$yt", array(), 0 ), 'data-vid="youtube"' ), 'a pasted YouTube address in the editor gets the same player (no cookie iframe from WordPress)' );
// saving
wp_set_current_user( 1 );
$vid = wp_insert_post( array( 'post_type' => 'mcupr_video', 'post_status' => 'publish', 'post_title' => 'Video test' ) );
$_POST = array( 'mcupr_video_nonce' => wp_create_nonce( 'mcupr_video_save' ), 'mcupr_video_url' => "https://youtu.be/$yt" );
wp_update_post( array( 'ID' => $vid, 'post_title' => 'Video test' ) );
t( "https://youtu.be/$yt" === mcupr_video_url( $vid ), 'save: a recognised address is kept' );
$_POST['mcupr_video_url'] = 'https://evil.example/x';
wp_update_post( array( 'ID' => $vid, 'post_title' => 'Video test' ) );
t( '' === mcupr_video_url( $vid ), 'save: an unrecognised address is removed' );
$_POST = array( 'mcupr_video_url' => "https://youtu.be/$yt" );
wp_update_post( array( 'ID' => $vid, 'post_title' => 'Video test' ) );
t( '' === mcupr_video_url( $vid ), 'save: without the nonce nothing is saved' );
// news post with a video
$np = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'News with video', 'post_content' => '<p>Body text.</p>' ) );
update_post_meta( $np, '_mcupr_video_url', "https://youtu.be/$yt" );
$q = new WP_Query( array( 'p' => $np ) );
$GLOBALS['wp_query'] = $q; $q->the_post();
$GLOBALS['wp_the_query'] = $q;
$c = apply_filters( 'the_content', get_post_field( 'post_content', $np ) );
t( 0 === strpos( $c, '<div class="vid-wrap">' ) && false !== strpos( $c, 'Body text.' ), 'news post: the player comes first in the article' );
wp_reset_postdata();
// list
$_POST = array();
update_post_meta( $vid, '_mcupr_video_url', "https://youtu.be/$yt" );
t( false !== strpos( do_shortcode( '[mcupr_videos count="3"]' ), 'video-card' ), 'shortcode [mcupr_videos]: a grid of cards' );
wp_delete_post( $vid, true ); wp_delete_post( $np, true );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
