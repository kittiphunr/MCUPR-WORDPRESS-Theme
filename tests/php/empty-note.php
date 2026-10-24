<?php
/** The small "nothing here yet" note inside a page section.   php php/empty-note.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$h = mcupr_empty_note( 'ยังไม่มีข้อมูล', 'book' );
t( 0 === strpos( $h, '<p class="empty-note"><span class="empty-ic" aria-hidden="true"><svg' ) && false !== strpos( $h, '<span>ยังไม่มีข้อมูล</span></p>' ), 'a note is a paragraph with an icon chip (hidden from screen readers) and the sentence' );
$x = mcupr_empty_note( '<script>alert(1)</script> & "q"' );
t( false === strpos( $x, '<script>' ) && false !== strpos( $x, '&lt;script&gt;' ), 'the sentence is escaped' );
t( false !== strpos( mcupr_empty_note( 'x', 'no-such-icon' ), '<svg' ), 'an unknown icon name still draws an icon (the default one)' );
// the places that use it
$src = '';
foreach ( array( 'offices-front', 'groups-front', 'albums', 'videos', 'books' ) as $f ) { $src .= file_get_contents( MCUPR_DIR . '/inc/' . $f . '.php' ); }
t( substr_count( $src, 'mcupr_empty_note(' ) >= 9 && false === strpos( $src, '<p class="bk-empty">' ) && false === strpos( $src, "gl-empty text-muted" ), 'offices, groups, albums, videos and books use it instead of a bare grey line (' . substr_count( $src, 'mcupr_empty_note(' ) . ' places)' );
echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
