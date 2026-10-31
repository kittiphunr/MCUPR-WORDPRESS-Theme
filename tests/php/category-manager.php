<?php
/** Category manager: duplicates, categories that repeat an office, merge, move to an office, rules for old posts, new duplicates refused.   php php/category-manager.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admin ? $admin[0]->ID : 1 );
$made_terms = array();
$made_posts = array();
$mkcat = static function ( $name, $slug = '', $parent = 0 ) use ( &$made_terms ) {
	$r = wp_insert_term( $name, 'category', array( 'slug' => $slug, 'parent' => $parent ) );
	$id = is_wp_error( $r ) ? 0 : (int) $r['term_id'];
	if ( $id ) { $made_terms[] = $id; }
	return $id;
};
$mkpost = static function ( $title, $cats = array(), $content = '' ) use ( &$made_posts ) {
	$id = wp_insert_post( array( 'post_title' => $title, 'post_content' => $content, 'post_status' => 'publish', 'post_type' => 'post', 'post_category' => $cats ) );
	$made_posts[] = $id;
	return $id;
};
$default = (int) get_option( 'default_category' );

/* leftovers of a run that stopped half way */
foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $old_t ) {
	if ( 0 === strpos( $old_t->slug, 'cm-' ) ) { wp_delete_term( $old_t->term_id, 'category' ); }
}
foreach ( get_posts( array( 'post_type' => array( 'post', 'mcupr_office' ), 'post_status' => 'any', 'posts_per_page' => -1, 's' => '', 'meta_key' => '', 'suppress_filters' => true ) ) as $old_p ) {
	if ( preg_match( '/^(ข่าว [ABC]|ข่าวหน่วยงาน|ประกาศ สำนักงานอธิการบดี [12]|ประกาศรับสมัครนักศึกษา|กิจกรรมวันไหว้ครู|เรื่องอื่น|ประกาศผลสอบ)$|^สำนักงานอธิการบดี$/u', $old_p->post_title ) ) { wp_delete_post( $old_p->ID, true ); }
}

/* names */
t( mcupr_cat_norm( 'ข่าว ' ) === mcupr_cat_norm( 'ข่าว.' ) && mcupr_cat_norm( 'News' ) === mcupr_cat_norm( ' news ' ) && mcupr_cat_norm( 'ข่าว-กิจกรรม' ) === mcupr_cat_norm( 'ข่าวกิจกรรม' ), 'names: spaces, punctuation and case do not make a different name' );
t( 'news' === mcupr_cat_slug_base( 'news-2' ) && 'a-b' === mcupr_cat_slug_base( 'a-b' ), 'slug: the "-2" WordPress adds is dropped' );

/* offices and categories */
$office = wp_insert_post( array( 'post_type' => 'mcupr_office', 'post_title' => 'สำนักงานอธิการบดี', 'post_status' => 'publish' ) );
$made_posts[] = $office;
$a = $mkcat( 'ข่าวกิจกรรม', 'cm-activity' );
$b = $mkcat( 'ข่าว-กิจกรรม', 'cm-activity-b' );
$c = $mkcat( 'ข่าวกิจกรรม', 'cm-activity-2' ); // same name, another slug ("cm-activity-2")
t( $a && $b, 'set-up: two spellings of one name exist' );
$o1 = $mkcat( 'สำนักงานอธิการบดี', 'cm-office' );
$o2 = $mkcat( 'ข่าวสำนักงานอธิการบดี', 'cm-office-news' );
$e  = $mkcat( 'หมวดว่างทดสอบ', 'cm-empty' );
$keep = $mkcat( 'บทความทดสอบ', 'cm-articles' );
$sub  = $mkcat( 'บทความย่อย', 'cm-sub', $a );
$p1 = $mkpost( 'ข่าว A', array( $a ) );
$p2 = $mkpost( 'ข่าว B', array( $b ) );
$p3 = $mkpost( 'ข่าว C', array( $a, $b ) ); // in both
$p4 = $mkpost( 'ประกาศ สำนักงานอธิการบดี 1', array( $o1 ) );
$p5 = $mkpost( 'ประกาศ สำนักงานอธิการบดี 2', array( $o1, $keep ) );
$p6 = $mkpost( 'ข่าวหน่วยงาน', array( $o2 ) );
$rep = mcupr_cat_report();
$grp = null;
foreach ( $rep['dups'] as $g ) { if ( in_array( $a, $g, true ) && in_array( $b, $g, true ) ) { $grp = $g; } }
t( null !== $grp, 'report: the two spellings are one group of duplicates' );
t( isset( $rep['like'][ $o1 ] ) && $rep['like'][ $o1 ] === $office, 'report: a category with the name of an office is found, with its office' );
t( isset( $rep['like'][ $o2 ] ) && $rep['like'][ $o2 ] === $office, 'report: a category that holds the name of an office is found' );
t( in_array( $e, $rep['empty'], true ) && ! in_array( $default, $rep['empty'], true ), 'report: the empty category is found, the default one never' );
t( ! isset( $rep['like'][ $default ] ), 'report: the default category is not offered for moving' );

/* merge */
$moved = mcupr_cat_merge( $a, array( $b ) );
$in_a  = get_posts( array( 'category' => $a, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
sort( $in_a );
$want = array( $p1, $p2, $p3 ); sort( $want );
t( $in_a === $want, 'merge: the posts of both are in the kept category (the post that was in both is there once)' );
t( ! term_exists( $b, 'category' ), 'merge: the merged category is gone' );
t( 3 === (int) get_term( $a, 'category' )->count, 'merge: the count of the kept category is right (3)' );
t( 1 === $moved || 2 === $moved, 'merge: the number of moved posts is reported' );
$sub_ok = term_exists( $sub, 'category' );
t( -1 === mcupr_cat_merge( $sub, array( $a ) ) && term_exists( $a, 'category' ) && $sub_ok, 'merge: a category cannot be merged into one of its own children (no loop)' );
t( -1 === mcupr_cat_merge( $a, array( $default ) ) || term_exists( $default, 'category' ), 'merge: the default category is never merged away' );

/* to the office */
$r = mcupr_cat_to_office( $o1, $office );
t( ! empty( $r ) && 2 === $r['done'] && 0 === $r['left'] && $r['deleted'], 'office: the posts are moved and the category is deleted' );
t( mcupr_office_of( $p4 ) === $office && mcupr_office_of( $p5 ) === $office, 'office: the posts are marked with the office' );
t( ! term_exists( $o1, 'category' ), 'office: the category is gone' );
t( in_array( $keep, wp_get_post_categories( $p5 ), true ), 'office: a post keeps its other category' );
t( array( $default ) === array_map( 'intval', wp_get_post_categories( $p4 ) ), 'office: a post left with no category gets the default one' );
t( array() === mcupr_cat_to_office( $default, $office ), 'office: the default category cannot be moved' );
t( array() === mcupr_cat_to_office( $o2, 999999 ), 'office: an office that does not exist is refused' );

/* rules for old posts */
$news  = $mkcat( 'ประกาศ', 'cm-notice' );
$grad  = $mkcat( 'การศึกษา', 'cm-edu' );
$q1 = $mkpost( 'ประกาศรับสมัครนักศึกษา', array( $default ), 'เนื้อหา' );
$q2 = $mkpost( 'กิจกรรมวันไหว้ครู', array( $default ), 'มีทุนการศึกษาด้วย' );
$q3 = $mkpost( 'เรื่องอื่น', array( $default ), 'ไม่เกี่ยว' );
$q4 = $mkpost( 'ประกาศผลสอบ', array( $keep ), '' );
update_option( MCUPR_CAT_RULES, array(
	array( 'words' => 'ประกาศ, ผลสอบ', 'target' => 'cat:' . $news, 'where' => 'title' ),
	array( 'words' => 'ทุนการศึกษา', 'target' => 'cat:' . $grad, 'where' => 'all' ),
	array( 'words' => 'ไหว้ครู', 'target' => 'office:' . $office, 'where' => 'title' ),
	array( 'words' => 'bad', 'target' => 'nonsense', 'where' => 'title' ),
	array( 'words' => '', 'target' => 'cat:1', 'where' => 'title' ),
) );
t( 3 === count( mcupr_cat_rules() ), 'rules: broken rules (bad target, no words) are dropped when read' );
t( mcupr_cat_rule_fits( array( 'words' => 'ผลสอบ', 'where' => 'title' ), 'ประกาศผลสอบ' ) && ! mcupr_cat_rule_fits( array( 'words' => 'ทุน', 'where' => 'title' ), 'x', 'มีทุน' ) && mcupr_cat_rule_fits( array( 'words' => 'ทุน', 'where' => 'all' ), 'x', 'มีทุน' ), 'rules: the title only, or the title and the text' );
$before = wp_get_post_categories( $q1 );
$pv = mcupr_cat_run_batch( 0, 1000, false, false );
$mine = array( $q1, $q2, $q3, $q4 );
t( $pv['changed'] >= 3 && wp_get_post_categories( $q1 ) === $before, 'rules: the preview counts what would change and changes nothing' );
mcupr_cat_run_batch( 0, 1000, true, false );
t( array( $news ) === array_map( 'intval', wp_get_post_categories( $q1 ) ), 'rules: a matched post gets the category and loses "Uncategorized"' );
t( array( $grad ) === array_map( 'intval', wp_get_post_categories( $q2 ) ) && mcupr_office_of( $q2 ) === $office, 'rules: a rule can fit the text, and a rule can name an office' );
t( array( $default ) === array_map( 'intval', wp_get_post_categories( $q3 ) ), 'rules: a post no rule fits is not touched' );
$q4c = array_map( 'intval', wp_get_post_categories( $q4 ) ); sort( $q4c ); $w4 = array( $keep, $news ); sort( $w4 );
t( $q4c === $w4, 'rules: the category is added to the post\'s own (it keeps what it had)' );
$again = mcupr_cat_run_batch( 0, 1000, false, false );
t( 0 === $again['changed'], 'rules: running again changes nothing more' );
mcupr_cat_run_batch( 0, 1000, true, true );
t( array( $news ) === array_map( 'intval', wp_get_post_categories( $q4 ) ), 'rules: "replace" makes the matched categories the only ones' );
$b1 = mcupr_cat_run_batch( 0, 2, false, false );
t( $b1['n'] <= 2 && true === $b1['more'], 'rules: a batch stops at its size and says there is more' );

/* a new duplicate is refused in the admin, not in code */
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen( 'edit-category' );
$_POST['action'] = 'add-tag';
$dup = wp_insert_term( 'ข่าว กิจกรรม!', 'category' );
t( is_wp_error( $dup ) && 'mcupr_cat_dup' === $dup->get_error_code(), 'new: a category that repeats one is refused with a message' );
$off = wp_insert_term( 'สำนักงานอธิการบดี', 'category' );
t( is_wp_error( $off ) && 'mcupr_cat_office' === $off->get_error_code(), 'new: a category with the name of an office is refused' );
$fresh = wp_insert_term( 'หมวดใหม่ไม่ซ้ำ ' . wp_rand(), 'category' );
t( ! is_wp_error( $fresh ), 'new: a different name is accepted' );
if ( ! is_wp_error( $fresh ) ) { $made_terms[] = (int) $fresh['term_id']; }
unset( $_POST['action'] );
$code = wp_insert_term( 'ข่าว กิจกรรม!', 'category' );
t( ! is_wp_error( $code ) || 'term_exists' === $code->get_error_code(), 'new: code, imports and demos are not questioned' );
if ( ! is_wp_error( $code ) ) { $made_terms[] = (int) $code['term_id']; }

/* the page */
$_GET['msg'] = 'merged'; $_GET['n'] = 3;
ob_start();
mcupr_cat_page();
$html = ob_get_clean();
t( false !== strpos( $html, 'mcupr-cat-dups' ) && false !== strpos( $html, 'mcupr-cat-offices' ) && false !== strpos( $html, 'mcupr-cat-rules' ) && false !== strpos( $html, 'mcupr-cat-all' ), 'page: it renders every section' );
t( false !== strpos( $html, 'mcupr-cat-preview' ) && false !== strpos( $html, 'rule_words[' ), 'page: the rules form and the preview button are there' );
t( false !== strpos( $html, 'notice-success' ), 'page: the result of the last action is shown' );
$cap = mcupr_cc_page_caps();
t( isset( $cap['mcupr-categories'] ) && 'manage_categories' === $cap['mcupr-categories'], 'screen: the page has its capability in the overview' );

/* clean up */
delete_option( MCUPR_CAT_RULES );
foreach ( $made_posts as $pid ) { wp_delete_post( $pid, true ); }
foreach ( array_unique( $made_terms ) as $tid ) { if ( term_exists( $tid, 'category' ) ) { wp_delete_term( $tid, 'category' ); } }
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
