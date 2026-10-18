<?php
/**
 * Speed and four languages (v1.53.0): reply counts, the newest topic per board, CSV export, the office of a post, office numbers,
 * the single link-rule flush, buffered counters, the plain-link cache, Chinese / Japanese dates and welcome texts.
 *   php php/speed-i18n.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$sub  = isset( $argv[2] ) ? $argv[2] : '';
if ( '' !== $sub ) {
	$_GET['lang'] = $sub; // a run as a visitor reading this language (the language of a request is fixed once it is known)
}
require rtrim( $site, '/' ) . '/wp-load.php';
if ( '' !== $sub ) {
	$dt = new DateTimeImmutable( '2026-10-14 09:30:00', wp_timezone() ); // a Wednesday
	$ts = $dt->getTimestamp();
	$pr = mcupr_welcome_presets();
	$hd = mcupr_honor_defaults();
	echo 'SPEEDI18N ' . wp_json_encode(
		array(
			'lang'     => mcupr_ui_lang(),
			'jMY'      => mcupr_date_fmt( 'j M Y', $ts ),
			'jMY_wp'   => wp_date( 'j M Y', $ts ),
			'jFYHi'    => mcupr_date_fmt( 'j F Y H:i', $ts ),
			'jFYHi_wp' => wp_date( 'j F Y H:i', $ts ),
			'jMYHi'    => mcupr_date_fmt( 'j M Y H:i', $ts ),
			'jMYHi_wp' => wp_date( 'j M Y H:i', $ts ),
			'DjM'      => mcupr_date_fmt( 'D j M', $ts ),
			'DjM_wp'   => wp_date( 'D j M', $ts ),
			'num'      => mcupr_date_fmt( 'j/n/Y H:i', $ts ),
			'num_wp'   => wp_date( 'j/n/Y H:i', $ts ),
			'royal_t'  => mcupr_welcome_preset_text( $pr['royal'], 'title' ),
			'royal_m'  => mcupr_welcome_preset_text( $pr['royal'], 'msg' ),
			'ny_t'     => mcupr_welcome_preset_text( $pr['newyear'], 'title' ),
			'own_t'    => mcupr_welcome_preset_text( $pr['custom'], 'title' ),
			'only_th'  => mcupr_welcome_preset_text( array( 'x', '#000', '#000', 'TH', 'THM', '', '', '', '', '', '' ), 'title' ),
			'only_en'  => mcupr_welcome_preset_text( array( 'x', '#000', '#000', 'TH', 'THM', 'EN', 'ENM', '', '', '', '' ), 'msg' ),
			'status'   => mcupr_status_text( array( 'th' => 'TH', 'en' => 'EN', 'zh' => 'ZH', 'ja' => 'JA' ) ),
			'honor'    => mcupr_honor_name( array_merge( $hd[0], array( 'key' => $hd[0]['key'] ) ) ),
			'honor_ed' => mcupr_honor_name( array_merge( $hd[0], array( 'th' => 'ชื่อที่แก้เอง', 'en' => 'Own name' ) ) ),
			'spf'      => mcupr_t( 'spf_birth' ),
			'spg'      => mcupr_t( 'spg_personal' ),
		)
	) . "\n";
	exit( 0 );
}
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function speed_run( $site, $lang ) {
	$out = (string) shell_exec( PHP_BINARY . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $site ) . ' ' . escapeshellarg( $lang ) . ' 2>/dev/null' );
	return preg_match( '/^SPEEDI18N (.*)$/m', $out, $m ) ? json_decode( $m[1], true ) : array();
}
function queries_of( $fn ) {
	global $wpdb;
	$n = $wpdb->num_queries;
	$fn();
	return $wpdb->num_queries - $n;
}
$cleanup = array();
wp_set_current_user( 0 );

// ================================================================= 1. forum
$board = wp_insert_term( 'Speed board ' . wp_generate_password( 4, false ), 'mcupr_board' );
$bid   = is_wp_error( $board ) ? 0 : (int) $board['term_id'];
$t1    = wp_insert_post( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'post_title' => 'Speed topic one' ) );
$t2    = wp_insert_post( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'post_title' => 'Speed topic two' ) );
wp_set_object_terms( $t1, array( $bid ), 'mcupr_board' );
wp_set_object_terms( $t2, array( $bid ), 'mcupr_board' );
mcupr_topic_touch( $t1, time() - 100 );
mcupr_topic_touch( $t2, time() - 50 );
$c_ok = wp_insert_comment( array( 'comment_post_ID' => $t1, 'comment_author' => 'A', 'comment_content' => 'one', 'comment_approved' => 1, 'comment_type' => 'comment' ) );
$c_ok2 = wp_insert_comment( array( 'comment_post_ID' => $t1, 'comment_author' => 'B', 'comment_content' => 'two', 'comment_approved' => 1, 'comment_type' => 'comment' ) );
$c_wait = wp_insert_comment( array( 'comment_post_ID' => $t1, 'comment_author' => 'C', 'comment_content' => 'held', 'comment_approved' => 0, 'comment_type' => 'comment' ) );
t( 2 === mcupr_topic_replies( $t1 ) && 2 === (int) get_post( $t1 )->comment_count && 2 === (int) get_comments( array( 'post_id' => $t1, 'status' => 'approve', 'count' => true ) ), 'forum: the reply count comes from comment_count and equals the approved replies (a held one is not counted)' );
get_post( $t1 );
t( 0 === queries_of( static function () use ( $t1 ) { mcupr_topic_replies( $t1 ); } ), 'forum: counting the replies of a loaded topic costs no query' );
wp_set_comment_status( $c_wait, 'approve' );
t( 3 === mcupr_topic_replies( $t1 ), 'forum: approving a reply adds it to the count at once' );
mcupr_forum_board_last_clear();
t( $t2 === mcupr_forum_board_last( $bid ), 'forum: the newest topic of a board is the one with the latest activity' );
t( is_array( get_transient( 'mcupr_forum_board_last' ) ) && 0 === queries_of( static function () use ( $bid ) { mcupr_forum_board_last( $bid ); } ), 'forum: the newest topic per board is kept (no query the second time)' );
mcupr_topic_touch( $t1, time() );
t( $t1 === mcupr_forum_board_last( $bid ), 'forum: new activity on a topic makes it the newest at once' );
wp_update_post( array( 'ID' => $t1, 'post_status' => 'draft' ) );
t( $t2 === mcupr_forum_board_last( $bid ), 'forum: a topic taken off the site is no longer the newest' );
$cleanup[] = static function () use ( $t1, $t2, $bid ) { wp_delete_post( $t1, true ); wp_delete_post( $t2, true ); if ( $bid ) { wp_delete_term( $bid, 'mcupr_board' ); } };
$src = (string) file_get_contents( get_theme_file_path( 'inc/forum-front.php' ) );
t( false !== strpos( $src, 'cache_users(' ) && false === strpos( $src, "'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'posts_per_page' => 1," ), 'forum: the authors of the rows are loaded in one query, no query per board for its newest topic' );

// ================================================================= 2. CSV export
$form = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'Speed CSV' ) );
update_post_meta( $form, '_mcupr_fb_fields', mcupr_fb_clean_fields( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'ชื่อ' ), array( 'id' => 'b', 'type' => 'text', 'label' => 'เมือง' ) ) ) );
$want = "\xEF\xBB\xBF" . '"#","Date","ชื่อ","เมือง"' . "\r\n";
$ents = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$e = wp_insert_post( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'post_title' => 'e' . $i ) );
	update_post_meta( $e, '_mcupr_fe_form', $form );
	update_post_meta( $e, '_mcupr_fe_rows', array( array( 'ชื่อ', 'คน ' . $i ), array( 'เมือง', 0 === $i % 3 ? '=1+1' : 'แพร่' ) ) );
	$ents[] = $e;
	$want  .= '"' . $i . '","' . get_the_date( 'Y-m-d H:i', $e ) . '","คน ' . $i . '","' . ( 0 === $i % 3 ? "'=1+1" : 'แพร่' ) . '"' . "\r\n";
}
wp_cache_flush();
$csv = '';
$q   = queries_of( static function () use ( $form, &$csv ) { $csv = mcupr_fb_csv( $form ); } );
t( $want === $csv, 'csv: the export gives the same rows as before (number, date, answers, formula guard)' );
t( $q <= 12, 'csv: 12 answers are exported with a handful of queries, not two per row (' . $q . ')' );
$cleanup[] = static function () use ( $form, $ents ) { foreach ( $ents as $e ) { wp_delete_post( $e, true ); } wp_delete_post( $form, true ); };

// ================================================================= 3 + 4. offices
$off  = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_status' => 'publish', 'post_title' => 'Speed office' ) );
$n1   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Speed office news 1' ) );
mcupr_office_assign( $n1, $off );
$slugs = wp_get_object_terms( $n1, MCUPR_OFFICE_TAX, array( 'fields' => 'slugs' ) );
t( $off === mcupr_office_of( $n1 ) && array( 'office-' . $off ) === $slugs && 0 === mcupr_office_of( 0 ) && $off === mcupr_office_of( $off ), 'office of a post: the same answer as before (its office, 0 for none, an office is its own)' );
get_the_terms( $n1, MCUPR_OFFICE_TAX );
t( 0 === queries_of( static function () use ( $n1 ) { mcupr_office_of( $n1 ); } ), 'office of a post: no query when its terms are loaded' );
$cnt = static function () use ( $off ) { $o = array(); foreach ( mcupr_office_counts( $off ) as $c ) { $o[ $c[0] ] = $c[2]; } return $o; };
delete_transient( 'mcupr_office_counts' );
$c1 = $cnt();
t( isset( $c1['news'] ) && 1 === $c1['news'] && is_array( get_transient( 'mcupr_office_counts' ) ), 'office numbers: counted once and kept' );
t( 0 === queries_of( $cnt ), 'office numbers: the second view costs no query' );
$n2 = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Speed office news 2' ) );
mcupr_office_assign( $n2, $off );
$c2 = $cnt();
t( isset( $c2['news'] ) && 2 === $c2['news'], 'office numbers: an item added by an editor is counted at once' );
wp_trash_post( $n2 );
$c3 = $cnt();
t( isset( $c3['news'] ) && 1 === $c3['news'], 'office numbers: an item removed is no longer counted' );
$ev = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_status' => 'draft', 'post_title' => 'Speed office event' ) );
mcupr_office_assign( $ev, $off );
$c4 = $cnt();
wp_update_post( array( 'ID' => $ev, 'post_status' => 'publish' ) );
$c5 = $cnt();
t( ! isset( $c4['events'] ) && isset( $c5['events'] ) && 1 === $c5['events'], 'office numbers: a draft is not counted, publishing it counts it at once' );
$cleanup[] = static function () use ( $off, $n1, $n2, $ev ) { foreach ( array( $n1, $n2, $ev, $off ) as $p ) { wp_delete_post( $p, true ); } };

// ================================================================= 5. one flush for the link rules
t( mcupr_rewrite_needs_flush( array( 'a' => '1' ), array() ) && mcupr_rewrite_needs_flush( array( 'a' => '2' ), array( 'a' => '1' ) ) && ! mcupr_rewrite_needs_flush( array( 'a' => '1' ), array( 'a' => '1', 'b' => '9' ) ) && ! mcupr_rewrite_needs_flush( array(), null ), 'rewrite: a flush is needed only when a wanted version is missing or different' );
t( array( 'a' => '2', 'b' => '9' ) === mcupr_rewrite_merge( array( 'a' => '2' ), array( 'a' => '1', 'b' => '9' ) ), 'rewrite: the stored versions keep the others and take the wanted ones' );
$flushes = 0;
$count   = static function ( $v ) use ( &$flushes ) { $flushes++; return $v; }; // a flush saves rewrite_rules once (pretty or plain permalinks)
add_filter( 'pre_update_option_rewrite_rules', $count );
$keep_w  = isset( $GLOBALS['mcupr_rewrite_wanted'] ) ? $GLOBALS['mcupr_rewrite_wanted'] : array();
$keep_v  = get_option( 'mcupr_rewrite_versions' );
$wanted  = $keep_w;
t( isset( $wanted['mcupr_rewrite_ver'], $wanted['mcupr_rewrite_offices'], $wanted['mcupr_rewrite_students'], $wanted['mcupr_rewrite_staff'] ) && count( $wanted ) >= 9 && MCUPR_VERSION === $wanted['mcupr_rewrite_forum'], 'rewrite: the nine modules ask for their rules by the names of their old options' );
update_option( 'mcupr_rewrite_versions', $wanted );
$flushes = 0;
t( false === mcupr_rewrite_flush_if_wanted() && 0 === $flushes, 'rewrite: nothing changed, no flush' );
$GLOBALS['mcupr_rewrite_wanted']['mcupr_rewrite_test'] = 'x1';
t( true === mcupr_rewrite_flush_if_wanted() && 1 === $flushes, 'rewrite: one changed version, exactly one flush' );
t( false === mcupr_rewrite_flush_if_wanted() && 1 === $flushes && 'x1' === get_option( 'mcupr_rewrite_versions' )['mcupr_rewrite_test'], 'rewrite: once stored, no flush again' );
unset( $GLOBALS['mcupr_rewrite_wanted']['mcupr_rewrite_test'] );
// an older theme version had flushed already: its own options say so, and no flush is needed
delete_option( 'mcupr_rewrite_versions' );
$old = array();
foreach ( $wanted as $k => $v ) { $old[ $k ] = get_option( $k ); update_option( $k, $v ); }
$flushes = 0;
t( false === mcupr_rewrite_flush_if_wanted() && 0 === $flushes && is_array( get_option( 'mcupr_rewrite_versions' ) ), 'rewrite: a site the older version had flushed is not flushed again (its versions are taken over)' );
delete_option( 'mcupr_rewrite_versions' );
foreach ( $wanted as $k => $v ) { delete_option( $k ); }
$flushes = 0;
t( true === mcupr_rewrite_flush_if_wanted() && 1 === $flushes, 'rewrite: a site that never flushed is flushed once' );
foreach ( $old as $k => $v ) { false === $v ? delete_option( $k ) : update_option( $k, $v ); }
false === $keep_v ? delete_option( 'mcupr_rewrite_versions' ) : update_option( 'mcupr_rewrite_versions', $keep_v );
$GLOBALS['mcupr_rewrite_wanted'] = $keep_w;
remove_filter( 'pre_update_option_rewrite_rules', $count );
$ok = true;
foreach ( array( 'events', 'videos', 'albums', 'forum', 'books', 'requests', 'offices', 'staff', 'students-portal' ) as $f ) {
	$ok = $ok && false === strpos( (string) file_get_contents( get_theme_file_path( 'inc/' . $f . '.php' ) ), "!== MCUPR_VERSION ) {\n" );
}
t( $ok, 'rewrite: no module flushes on its own any more' );

// ================================================================= 6. counters that are buffered
$opts = array( 'mcupr_daily_views', 'mcupr_daily_views_buf', 'mcupr_404log', 'mcupr_404log_buf', 'mcupr_searchlog', 'mcupr_searchlog_buf', 'mcupr_redirects', 'mcupr_redirect_hits' );
$keep = array_map( 'get_option', $opts );
foreach ( $opts as $o ) { delete_option( $o ); }
mcupr_stats_hit(); mcupr_stats_hit(); mcupr_stats_hit();
$s = mcupr_stats_series( 3 );
t( 3 === end( $s ) && false === get_option( 'mcupr_daily_views' ), 'views: three views are seen at once by the statistics, while the 120-day list is not rewritten' );
mcupr_stats_flush();
$d = get_option( 'mcupr_daily_views' );
$s = mcupr_stats_series( 3 );
t( is_array( $d ) && 3 === (int) reset( $d ) && 3 === end( $s ) && array() === mcupr_stats_buffer()['d'], 'views: the flush moves them into the saved list (same format: date => views), nothing is counted twice' );
update_option( 'mcupr_daily_views_buf', array( 't' => time() - 120, 'd' => array( (string) wp_date( 'Y-m-d' ) => 1 ) ), false );
mcupr_stats_hit();
$s = mcupr_stats_series( 1 );
t( 5 === (int) get_option( 'mcupr_daily_views' )[ (string) wp_date( 'Y-m-d' ) ] && 5 === end( $s ), 'views: a buffer older than a minute is written with the next view' );
ob_start(); mcupr_stats_summary_cards(); $h = ob_get_clean();
t( false !== strpos( $h, '<b>5</b>' ), 'views: the admin summary shows the counted views' );
// 404 + search
mcupr_ins_add( 'mcupr_404log', md5( '/speed-missing' ), array( 'p' => '/speed-missing', 'n' => 1, 't' => time() ) );
mcupr_ins_add( 'mcupr_404log', md5( '/speed-missing' ), array( 'p' => '/speed-missing', 'n' => 1, 't' => time() ) );
$l = mcupr_ins_log( 'mcupr_404log' );
t( false === get_option( 'mcupr_404log' ) && isset( $l[ md5( '/speed-missing' ) ] ) && 2 === $l[ md5( '/speed-missing' ) ]['n'], '404 log: two visits are buffered and the reader adds them in' );
mcupr_ins_flush( 'mcupr_404log' );
mcupr_ins_add( 'mcupr_404log', md5( '/speed-missing' ), array( 'p' => '/speed-missing', 'n' => 1, 't' => time() ) );
$saved = get_option( 'mcupr_404log' );
$l     = mcupr_ins_log( 'mcupr_404log' );
t( 2 === $saved[ md5( '/speed-missing' ) ]['n'] && array( 'p', 'n', 't' ) === array_keys( $saved[ md5( '/speed-missing' ) ] ) && 3 === $l[ md5( '/speed-missing' ) ]['n'], '404 log: the saved log keeps its format; saved and buffered counts add up' );
mcupr_ins_log_search( 'speed term', 0 );
mcupr_ins_log_search( 'speed term', 4 );
$l = mcupr_ins_log( 'mcupr_searchlog' );
t( 2 === $l[ md5( 'speed term' ) ]['n'] && 4 === $l[ md5( 'speed term' ) ]['f'] && 'speed term' === $l[ md5( 'speed term' ) ]['q'], 'search log: counted in the buffer, "found" from the latest search' );
for ( $i = 0; $i < 25; $i++ ) { mcupr_ins_log_search( 'speed many ' . $i, 1 ); }
t( count( (array) get_option( 'mcupr_searchlog' ) ) >= 20 && count( mcupr_ins_buffer( 'mcupr_searchlog' )['e'] ) < 20, 'search log: a full buffer (20 terms) is written to the saved log' );
// redirects
update_option( 'mcupr_redirects', array( array( 'from' => '/speed-old', 'to' => 'https://example.org/new', 'code' => 301, 'hits' => 4, 'last' => 100 ) ), false );
$before = serialize( get_option( 'mcupr_redirects' ) );
mcupr_redirect_hit( '/speed-old' ); mcupr_redirect_hit( '/speed-old' );
$r = mcupr_redirects_with_hits();
t( serialize( get_option( 'mcupr_redirects' ) ) === $before && 6 === $r[0]['hits'] && $r[0]['last'] >= time() - 5, 'redirects: a use is counted in its own option (the rules are not rewritten); old and new counts add up' );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins[0]->ID );
mcupr_ins_add( 'mcupr_404log', md5( '/speed-buffered-only' ), array( 'p' => '/speed-buffered-only', 'n' => 1, 't' => time() ) );
ob_start(); mcupr_ins_page(); $h = ob_get_clean();
wp_set_current_user( 0 );
t( false !== strpos( $h, '/speed-buffered-only' ) && false !== strpos( $h, 'speed term' ) && false !== strpos( $h, '<td>6' ), 'admin screen: buffered 404s and searches and the redirect count are shown' );
foreach ( $opts as $n => $o ) { false === $keep[ $n ] ? delete_option( $o ) : update_option( $o, $keep[ $n ], false ); }

// ================================================================= 7. plain links kept between requests
$orig  = get_option( 'permalink_structure' );
$fresh = static function () { $st = &mcupr_plain_cache_state(); $st = array( 'key' => '', 'map' => null, 'new' => array(), 'n' => 0, 'off' => false ); }; // as a new request starts
update_option( 'permalink_structure', '' ); $GLOBALS['wp_rewrite']->init();
$pg  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'speed plain', 'post_name' => 'speed-plain-page' ) );
$fresh();
$H   = untrailingslashit( home_url( '/' ) );
$gen = get_option( 'mcupr_plain_gen' );
$u   = mcupr_plain_url( $H . '/speed-plain-page/' );
mcupr_plain_url( $H . '/speed-not-there-xyz/' );
mcupr_plain_cache_save();
$key = mcupr_plain_cache_key();
$map = get_transient( $key );
t( $u === get_permalink( $pg ) && is_array( $map ) && isset( $map['speed-plain-page'] ) && $map['speed-plain-page'] === $u, 'plain links: a found address is kept between requests' );
t( ! isset( $map['speed-not-there-xyz'] ), 'plain links: an address nothing answers to is never kept' );
$fresh();
t( $u === mcupr_plain_cache_get( 'speed-plain-page' ), 'plain links: the next request reads it from the kept list' );
$fresh();
$pg2 = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'speed plain 2', 'post_name' => 'speed-not-there-xyz' ) );
$st  = &mcupr_plain_cache_state();
t( get_option( 'mcupr_plain_gen' ) !== $gen && ( $st['off'] || mcupr_plain_cache_key() !== $key ) && '' === mcupr_plain_cache_get( 'speed-plain-page' ) && false !== strpos( mcupr_plain_target( 'speed-not-there-xyz' ), 'page_id=' . $pg2 ), 'plain links: a new page starts a new generation at once, the old list is not read, and the new page works' );
$fresh();
$u2 = mcupr_plain_target( 'speed-plain-page' );
mcupr_plain_cache_put( 'speed-plain-page', $u2 );
mcupr_plain_cache_save();
$fresh();
wp_delete_post( $pg, true );
$fresh();
t( '' === mcupr_plain_cache_get( 'speed-plain-page' ), 'plain links: after a page is deleted its kept address is not read again' );
wp_delete_post( $pg2, true );
update_option( 'permalink_structure', $orig ); $GLOBALS['wp_rewrite']->init();
$fresh();

// ================================================================= 8 + 9. Chinese / Japanese dates and welcome texts, Thai unchanged
$th = speed_run( $site, 'th' );
$en = speed_run( $site, 'en' );
$zh = speed_run( $site, 'zh' );
$ja = speed_run( $site, 'ja' );
t( isset( $th['lang'], $en['lang'], $zh['lang'], $ja['lang'] ) && 'th' === $th['lang'] && 'zh' === $zh['lang'] && 'ja' === $ja['lang'], 'language runs: th, en, zh and ja visitors' );
t( isset( $th['jMY'] ) && $th['jMY'] === $th['jMY_wp'] && $th['jFYHi'] === $th['jFYHi_wp'] && $th['jMYHi'] === $th['jMYHi_wp'] && $th['DjM'] === $th['DjM_wp'] && $th['num'] === $th['num_wp'] && false !== strpos( $th['jMY'], '2569' ) && false !== strpos( $th['jMY'], 'ต.ค.' ), 'dates: Thai prints exactly what it printed before (Thai month, Buddhist year)' );
t( isset( $en['jMY'] ) && $en['jMY'] === $en['jMY_wp'] && $en['DjM'] === $en['DjM_wp'], 'dates: English unchanged' );
t( isset( $zh['jMY'] ) && '2026年10月14日' === $zh['jMY'] && '2026年10月14日 09:30' === $zh['jFYHi'] && '2026年10月14日 09:30' === $zh['jMYHi'] && '10月14日 周三' === $zh['DjM'] && '2026年10月14日 09:30' === $zh['num'], 'dates: Chinese shows 年月日, the weekday and the time' );
t( isset( $ja['jMY'] ) && '2026年10月14日' === $ja['jMY'] && '10月14日(水)' === $ja['DjM'] && '2026年10月14日 09:30' === $ja['jFYHi'], 'dates: Japanese shows 年月日 and (水)' );
t( isset( $th['royal_t'] ) && 'ขอเดชะ ขอถวายพระพรชัยมงคล' === $th['royal_t'] && 'Happy New Year' === $en['ny_t'] && '新年快乐' === $zh['ny_t'] && false !== strpos( $ja['ny_t'], 'あけましておめでとう' ), 'welcome presets: the text in the visitor\'s language (Thai unchanged)' );
$pre_ok = true;
foreach ( mcupr_welcome_presets() as $k => $p ) {
	$pre_ok = $pre_ok && 11 === count( $p ) && ( 'custom' === $k || ( '' !== $p[7] && '' !== $p[8] && '' !== $p[9] && '' !== $p[10] ) );
}
t( $pre_ok, 'welcome presets: every occasion has a Chinese and a Japanese title and message' );
t( isset( $zh['only_th'] ) && 'TH' === $zh['only_th'] && 'ENM' === $zh['only_en'] && '' === $zh['own_t'], 'welcome presets: the visitor\'s language, else English, else Thai' );
t( isset( $zh['status'] ) && 'ZH' === $zh['status'] && 'JA' === $ja['status'] && 'TH' === $th['status'], 'status text: the visitor\'s own language first' );
t( isset( $zh['honor'] ) && '白象勋章 一等大绶' === $zh['honor'] && 'ปรมาภรณ์ช้างเผือก' === $th['honor'] && 'Own name' === $zh['honor_ed'], 'decorations: a Chinese name for an unchanged standard decoration, the saved name when it was edited, Thai unchanged' );
t( isset( $zh['spf'] ) && '出生日期' === $zh['spf'] && '生年月日' === $ja['spf'] && 'วันเกิด' === $th['spf'] && '個人情報' === $ja['spg'], 'student portal details: labels in Chinese and Japanese, Thai unchanged' );
// no English month format is printed raw on these pages any more
$raw = array();
foreach ( array( 'inc/forum-front.php', 'inc/blocks.php', 'template-parts/status-page.php', 'inc/print.php', 'single-mcupr_topic.php' ) as $f ) {
	if ( preg_match( "#(?:wp_date|mysql2date|get_the_date)\\(\\s*'(?:j M Y|j F Y|D j M|j/n/Y)#", (string) file_get_contents( get_theme_file_path( $f ) ) ) ) {
		$raw[] = $f;
	}
}
t( ! $raw, 'dates: no raw wp_date( \'j M Y\' … ) left in the forum, weather, status and print code (' . implode( ', ', $raw ) . ')' );

foreach ( array_reverse( $cleanup ) as $c ) { $c(); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
