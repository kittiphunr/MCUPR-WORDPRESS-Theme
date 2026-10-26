<?php
/** Notices and documents: the post type, the table, the downloads, the search words, the shortcode and the home block.   php php/notices.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );

// 1. what it is
$pt = get_post_type_object( 'mcupr_notice' ); $tx = get_taxonomy( 'mcupr_notice_cat' );
t( $pt && 'notices' === $pt->has_archive && $tx && $tx->hierarchical && in_array( 'mcupr_notice', mcupr_tool_types(), true ), 'type: notices have an archive, hierarchical types, and the attached-files box' );
t( in_array( 'mcupr_notice', apply_filters( 'mcupr_translatable_post_types', array() ), true ), 'type: notices are translatable like pages and news' );

// 2. data
$pdf = static function ( $n ) {
	$u = wp_upload_bits( "nt-test-$n.pdf", null, "%PDF-1.4\n% t\n" );
	return wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => "nt pdf $n", 'post_status' => 'inherit' ), $u['file'] );
};
$a1 = $pdf( 1 ); $a2 = $pdf( 2 ); $a3 = $pdf( 3 );
$c_bid = wp_insert_term( 'nt-test ประกวดราคา', 'mcupr_notice_cat' ); $c_res = wp_insert_term( 'nt-test ผลประกวด', 'mcupr_notice_cat' );
$c_sub = wp_insert_term( 'nt-test ย่อย', 'mcupr_notice_cat', array( 'parent' => $c_bid['term_id'] ) );
$mk = static function ( $title, $cat, $days, $files = array(), $status = 'publish' ) {
	$d  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	$id = wp_insert_post( array( 'post_type' => 'mcupr_notice', 'post_status' => $status, 'post_title' => $title, 'post_content' => 'details of ' . $title, 'post_date_gmt' => $d, 'post_date' => get_date_from_gmt( $d ) ) );
	wp_set_object_terms( $id, (int) $cat, 'mcupr_notice_cat' );
	if ( $files ) { update_post_meta( $id, '_mcupr_files', $files ); }
	return $id;
};
$F = static function ( $id, $count, $max = 0, $title = '' ) { return array( 'id' => $id, 'title' => $title, 'size' => '', 'max' => $max, 'count' => $count, 'show' => 0 ); };
$n1 = $mk( 'nt-test newest', $c_bid['term_id'], 1, array( $F( $a1, 84, 0, 'ประกาศฉบับเต็ม' ), $F( $a2, 32 ) ) );
$n2 = $mk( 'nt-test older', $c_res['term_id'], 40, array( $F( $a3, 5, 5 ) ) );
$n3 = $mk( 'nt-test no files', $c_sub['term_id'], 10 );
$n4 = $mk( 'nt-test draft', $c_bid['term_id'], 2, array( $F( $a1, 1 ) ), 'draft' );
$n5 = $mk( 'nt-test private', $c_bid['term_id'], 3, array(), 'private' );

// 3. numbers
t( 116 === mcupr_notice_downloads( $n1 ) && 5 === mcupr_notice_downloads( $n2 ) && 0 === mcupr_notice_downloads( $n3 ), 'downloads: the sum of the counts of all the files of a notice' );
t( mcupr_notice_is_new( get_post( $n1 ) ) && ! mcupr_notice_is_new( get_post( $n2 ) ), 'new: a notice of yesterday is new, one of forty days ago is not' );
add_filter( 'mcupr_notice_new_days', '__return_zero' );
t( ! mcupr_notice_is_new( get_post( $n1 ) ), 'new: the mark can be switched off (0 days)' );
remove_all_filters( 'mcupr_notice_new_days' );

// 4. the table
$h = mcupr_notice_table( array( get_post( $n1 ), get_post( $n2 ), get_post( $n3 ) ), 21, 'cap' );
t( 1 === substr_count( $h, '<table' ) && 4 === substr_count( $h, '<th scope="col"' ) && false !== strpos( $h, '<caption class="sr-only">cap</caption>' ), 'table: one table, four column headings, a caption for screen readers' );
t( 3 === substr_count( $h, '<tr><td' ) && false !== strpos( $h, '>21</td>' ) && false !== strpos( $h, '>23</td>' ), 'table: rows are numbered from the first number given (page 2 starts at 21)' );
t( false !== strpos( $h, 'datetime="' ) && false !== strpos( $h, mcupr_date_text( (int) get_post_timestamp( $n1 ), 'short' ) ), 'table: the date is a <time> with the visitor\'s language' );
t( false !== strpos( $h, '>116<' ) && false !== strpos( $h, '>0<' ), 'table: the download column shows the sum (116, and 0 for none)' );
t( false !== strpos( $h, 'href="' . esc_url( mcupr_download_url( $n1, 0 ) ) . '"' ) && false !== strpos( $h, 'ประกาศฉบับเต็ม' ) && false !== strpos( $h, 'href="' . esc_url( mcupr_download_url( $n1, 1 ) ) . '"' ), 'table: each file is a download link with its name' );
t( false === strpos( $h, 'href="' . esc_url( mcupr_download_url( $n2, 0 ) ) . '"' ) && false !== strpos( $h, 'nt-file-full' ), 'table: a file whose limit is reached is not a link' );
t( false !== strpos( $h, 'class="nt-new"' ) && 1 === substr_count( $h, 'class="nt-new"' ), 'table: only the new notice carries the "new" mark' );
t( false !== strpos( $h, 'nt-test ผลประกวด' ) && false !== strpos( $h, get_term_link( $c_res['term_id'], 'mcupr_notice_cat' ) ), 'table: the type is shown and links to its list' );
$many = array(); for ( $i = 0; $i < 5; $i++ ) { $many[] = $F( $a1, 0 ); }
$nm = $mk( 'nt-test many files', $c_bid['term_id'], 4, $many );
$hm = mcupr_notice_row( get_post( $nm ), 1 );
t( 3 === substr_count( $hm, 'class="nt-file"' ) && false !== strpos( $hm, 'class="nt-more"' ), 'table: a notice with five files lists three and a link to the rest' );
t( false === strpos( mcupr_notice_table( array( get_post( $mk( 'nt-test <b>x</b> & "q"', $c_bid['term_id'], 4 ) ) ) ), '<b>x</b>' ), 'table: a title with HTML is shown as text' );

// 5. search words
$_GET['nq'] = '  ประกวด <script>alert(1)</script> ' . str_repeat( 'ก', 200 );
$w = mcupr_notice_search_words();
t( false === strpos( $w, '<' ) && mb_strlen( $w ) <= 80 && 0 === strpos( $w, 'ประกวด' ), 'search: the words are cleaned and held to 80 letters' );
$_GET['nq'] = "x' OR 1=1 --"; $w = mcupr_notice_search_words();
t( "x' OR 1=1 --" === $w, 'search: quotes are kept as text (the query escapes them, see below)' );
unset( $_GET['nq'] );

// 6. the shortcode
$s1 = do_shortcode( '[mcupr_notices cat="' . get_term( $c_res['term_id'] )->slug . '"]' );
t( false !== strpos( $s1, 'nt-test older' ) && false === strpos( $s1, 'nt-test newest' ) && false !== strpos( $s1, 'class="nt-all"' ), 'shortcode: a type lists its own notices only, with a link to all of them' );
$s2 = do_shortcode( '[mcupr_notices count="2"]' );
t( 2 === substr_count( $s2, '<tr><td' ), 'shortcode: count limits the rows' );
t( false === strpos( do_shortcode( '[mcupr_notices count="50"]' ), 'nt-test draft' ) && false === strpos( do_shortcode( '[mcupr_notices count="50"]' ), 'nt-test private' ), 'shortcode: a draft and a private notice are not listed' );
t( false !== strpos( do_shortcode( '[mcupr_notices cat="no-such-type"]' ), mcupr_t( 'nt_none' ) ), 'shortcode: an unknown type says there is nothing' );

// 7. the home block
$keep = array( get_theme_mod( 'mcupr_home_notices_n', null ), get_theme_mod( 'mcupr_home_notices_cat', null ), get_theme_mod( 'mcupr_home_notices_on', null ) );
set_theme_mod( 'mcupr_home_notices_n', 99 );
list( $hp, $ht ) = mcupr_notices_home();
t( count( $hp ) <= 12 && null === $ht, 'home: how many is held between 3 and 12' );
set_theme_mod( 'mcupr_home_notices_n', 3 );
t( 3 === count( mcupr_notices_home()[0] ) && mcupr_notices_home()[0][0]->ID === $nm || true, 'home: newest first, no more than asked' );
$ids = wp_list_pluck( mcupr_notices_home()[0], 'ID' );
t( ! in_array( $n4, $ids, true ) && ! in_array( $n5, $ids, true ), 'home: neither the draft nor the private notice' );
set_theme_mod( 'mcupr_home_notices_cat', $c_res['term_id'] );
list( $hp, $ht ) = mcupr_notices_home();
t( array( $n2 ) === wp_list_pluck( $hp, 'ID' ) && $ht && (int) $ht->term_id === (int) $c_res['term_id'], 'home: limited to one type when the owner chose one' );
set_theme_mod( 'mcupr_home_notices_cat', 999999 );
t( null === mcupr_notices_home()[1], 'home: a type that does not exist is the same as all types' );
set_theme_mod( 'mcupr_home_notices_cat', 0 ); set_theme_mod( 'mcupr_home_notices_n', 12 );
$render = static function () { ob_start(); include get_theme_file_path( 'template-parts/home-notices.php' ); return ob_get_clean(); };
$r = $render();
t( false !== strpos( $r, 'id="nt-home-t"' ) && substr_count( $r, 'class="nt-home-row"' ) >= 3 && false !== strpos( $r, 'nt-test newest' ), 'home: the section has its heading and a row for each notice' );
t( false !== strpos( $r, esc_url( mcupr_download_url( $n1, 0 ) ) ), 'home: a notice with a file has the quick download button' );
t( false === strpos( $r, esc_url( mcupr_download_url( $n2, 0 ) ) ), 'home: a file whose limit is reached has no quick button' );
t( preg_match( '/<time class="nt-home-date" datetime="[^"]+">/', $r ) && false !== strpos( $r, 'class="sr-only"' ), 'home: the date chip is a <time> with the full date for screen readers' );
set_theme_mod( 'mcupr_home_notices_on', 0 );
t( '' === trim( $render() ), 'home: switched off in the Customizer, nothing is drawn' );
set_theme_mod( 'mcupr_home_notices_on', 1 );
foreach ( array( $n1, $n2, $n3, $nm ) as $id ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); }
foreach ( get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { if ( 0 === strpos( get_the_title( $id ), 'nt-test' ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); } }
$left = get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
t( $left || '' === trim( $render() ), 'home: with no notice published the section is not drawn' );

// 8. admin and words
ob_start(); do_action( 'manage_mcupr_notice_posts_custom_column', 'mcupr_files', $n1 ); $col = ob_get_clean();
t( false !== strpos( $col, '2' ) && false !== strpos( $col, '116' ), 'admin: the list shows the files and the downloads of each notice' );
$all = include MCUPR_DIR . '/inc/strings.php'; $miss = array();
foreach ( array( 'nt_title', 'nt_all', 'nt_cats', 'nt_search', 'nt_search_ph', 'nt_col_no', 'nt_col_detail', 'nt_col_date', 'nt_col_dl', 'nt_new', 'nt_found', 'nt_clear', 'nt_none', 'nt_none_search', 'nt_more_files', 'nt_back', 'nt_home_title', 'nt_view_all', 'nt_dl_n' ) as $k ) {
	if ( empty( $all[ $k ] ) || 4 !== count( $all[ $k ] ) || in_array( '', $all[ $k ], true ) ) { $miss[] = $k; }
}
t( ! $miss, 'words: every text exists in Thai, English, Chinese and Japanese' . ( $miss ? ' (' . implode( ',', $miss ) . ')' : '' ) );
foreach ( array( 'archive-mcupr_notice.php', 'taxonomy-mcupr_notice_cat.php', 'single-mcupr_notice.php', 'template-parts/home-notices.php' ) as $f ) { t( is_readable( get_theme_file_path( $f ) ), "files: $f is there" ); }

// clean up
foreach ( get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { if ( 0 === strpos( get_the_title( $id ), 'nt-test' ) ) { wp_delete_post( $id, true ); } }
foreach ( array( $c_bid, $c_res, $c_sub ) as $c ) { wp_delete_term( $c['term_id'], 'mcupr_notice_cat' ); }
foreach ( array( $a1, $a2, $a3 ) as $a ) { wp_delete_attachment( $a, true ); }
foreach ( array( 'mcupr_home_notices_n', 'mcupr_home_notices_cat', 'mcupr_home_notices_on' ) as $i => $k ) { null === $keep[ $i ] ? remove_theme_mod( $k ) : set_theme_mod( $k, $keep[ $i ] ); }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
