<?php
/** Student-only content limited by level, year of study, faculty and major.   php php/audience-scope.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function wipe() { foreach ( get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { mcupr_student_drop_account( $id ); wp_delete_post( $id, true ); } }
function as_user( $uid ) { wp_set_current_user( $uid ); $GLOBALS['mcupr_aud_cache'] = array(); }
add_filter( 'pre_wp_mail', '__return_true' );
wp_set_current_user( 1 );
wipe();

// ---- the small helpers
t( mcupr_aud_norm( "  คณะ   พุทธศาสตร์ " ) === mcupr_aud_norm( 'คณะ พุทธศาสตร์' ) && mcupr_aud_norm( 'ABC' ) === 'abc', 'text is compared without case or repeated spaces' );
t( '' === mcupr_aud_year_key( '' ) && '' === mcupr_aud_year_key( 'x' ) && '1' === mcupr_aud_year_key( '1' ) && '4' === mcupr_aud_year_key( 4 ) && '5' === mcupr_aud_year_key( '7' ), 'a year of study becomes 1–4 or 5 (5 or more); unknown stays empty' );

// ---- the rules (within a group "or", across groups "and"; faculty covers its majors)
$v = function ( $level, $year, $fac, $maj ) { return array( 'level' => $level, 'year' => mcupr_aud_year_key( $year ), 'fac' => mcupr_aud_norm( $fac ), 'maj' => mcupr_aud_norm( $fac ) . MCUPR_AUD_SEP . mcupr_aud_norm( $maj ) ); };
$rules = function ( $r ) { return mcupr_aud_rules_norm( array_merge( array( 'levels' => array(), 'years' => array(), 'fac' => array(), 'maj' => array() ), $r ) ); };
$all = $rules( array() );
t( mcupr_aud_matches( $all, $v( 'bachelor', 1, 'A', 'x' ) ) && mcupr_aud_matches( $all, $v( '', '', '', '' ) ), 'no rules: every student' );
$r1 = $rules( array( 'levels' => array( 'bachelor' ), 'years' => array( '1', '2' ), 'fac' => array( 'คณะพุทธศาสตร์' ) ) );
t( mcupr_aud_matches( $r1, $v( 'bachelor', 2, 'คณะพุทธศาสตร์', 'พุทธศาสตร์' ) ), 'level and year and faculty all match: in' );
t( ! mcupr_aud_matches( $r1, $v( 'bachelor', 3, 'คณะพุทธศาสตร์', 'พุทธศาสตร์' ) ), 'wrong year: out' );
t( ! mcupr_aud_matches( $r1, $v( 'master', 1, 'คณะพุทธศาสตร์', 'พุทธศาสตร์' ) ), 'wrong level: out' );
t( ! mcupr_aud_matches( $r1, $v( 'bachelor', 1, 'คณะสังคมศาสตร์', 'รัฐศาสตร์' ) ), 'wrong faculty: out' );
t( mcupr_aud_matches( $r1, $v( 'bachelor', 1, 'คณะพุทธศาสตร์', 'อะไรก็ได้' ) ), 'a faculty covers every one of its majors' );
t( mcupr_aud_matches( $rules( array( 'fac' => array( 'คณะ พุทธศาสตร์' ) ) ), $v( 'bachelor', 1, 'คณะ   พุทธศาสตร์', 'x' ) ), 'repeated spaces in a faculty name do not matter' );
t( ! mcupr_aud_matches( $rules( array( 'fac' => array( 'คณะ พุทธศาสตร์' ) ) ), $v( 'bachelor', 1, 'คณะพุทธศาสตร์', 'x' ) ), 'but a different spelling is a different faculty (the editor sees both and ticks both)' );
$r2 = $rules( array( 'fac' => array( 'คณะพุทธศาสตร์' ), 'maj' => array( 'คณะสังคมศาสตร์' . MCUPR_AUD_SEP . 'รัฐศาสตร์' ) ) );
t( mcupr_aud_matches( $r2, $v( 'bachelor', 1, 'คณะสังคมศาสตร์', 'รัฐศาสตร์' ) ) && mcupr_aud_matches( $r2, $v( 'bachelor', 1, 'คณะพุทธศาสตร์', 'ปรัชญา' ) ), 'faculty or major: either lets a student in' );
t( ! mcupr_aud_matches( $r2, $v( 'bachelor', 1, 'คณะสังคมศาสตร์', 'การพัฒนาชุมชน' ) ), 'another major of that faculty is out' );
t( ! mcupr_aud_matches( $rules( array( 'years' => array( '5' ) ) ), $v( 'bachelor', '', 'A', 'x' ) ) && mcupr_aud_matches( $rules( array( 'years' => array( '5' ) ) ), $v( 'master', 6, 'A', 'x' ) ), 'a student with no year is out of year rules; year 6 counts as 5+' );
t( ! mcupr_aud_matches( $rules( array( 'fac' => array( 'A' ) ) ), $v( 'bachelor', 1, '', '' ) ), 'a student with no faculty is out of faculty rules' );

// ---- the real thing: students, accounts, a news item
$mk = function ( $sid, $level, $year, $fac, $maj, $status = 'studying' ) {
	list( , $id ) = mcupr_student_upsert( array( 'sid' => $sid, 'name' => 'ทดสอบ ' . $sid, 'name_en' => '', 'level' => $level, 'year' => $year, 'entry' => '2568', 'faculty' => $fac, 'major' => $maj, 'status' => $status, 'email' => strtolower( $sid ) . '@example.org', 'phone' => '', 'note' => '' ), 'add_update' );
	$uid = mcupr_student_make_account( $id );
	return array( $id, $uid );
};
list( , $uA ) = $mk( 'AUD001', 'bachelor', '2', 'คณะพุทธศาสตร์', 'พุทธศาสตร์' );
list( , $uB ) = $mk( 'AUD002', 'bachelor', '3', 'คณะพุทธศาสตร์', 'พุทธศาสตร์' );
list( , $uC ) = $mk( 'AUD003', 'master', '1', 'คณะพุทธศาสตร์', 'ปรัชญา' );
list( , $uD ) = $mk( 'AUD004', 'bachelor', '1', 'คณะสังคมศาสตร์', 'รัฐศาสตร์' );
list( , $uE ) = $mk( 'AUD005', 'bachelor', '2', 'คณะพุทธศาสตร์', 'พุทธศาสตร์', 'leave' );
$post = wp_insert_post( array( 'post_title' => 'ประกาศเฉพาะกลุ่ม (aud test)', 'post_content' => 'x', 'post_status' => 'publish' ) );

// saving through the box: the form values go through the same code as the editor
$save = function ( $fields ) use ( $post ) {
	$_POST = array_merge( array( 'mcupr_aud_nonce' => wp_create_nonce( 'mcupr_aud_save' ) ), $fields );
	do_action( 'save_post', $post, get_post( $post ), true );
	$_POST = array();
	$GLOBALS['mcupr_aud_cache'] = array();
};
$save( array( 'mcupr_aud' => 'students', 'mcupr_aud_levels' => array( 'bachelor', 'nonsense' ), 'mcupr_aud_years' => array( '1', '2', '9' ), 'mcupr_aud_fac' => array( 'คณะพุทธศาสตร์' ), 'mcupr_aud_maj' => array( 'คณะพุทธศาสตร์' . MCUPR_AUD_SEP . 'พุทธศาสตร์', 'คณะสังคมศาสตร์' . MCUPR_AUD_SEP . 'รัฐศาสตร์', 'broken-no-separator' ) ) );
$rr = mcupr_aud_rules( $post );
t( $rr['levels'] === array( 'bachelor' ) && $rr['years'] === array( '1', '2' ), 'saving: an unknown level or year is dropped' );
t( $rr['fac'] === array( 'คณะพุทธศาสตร์' ) && $rr['maj'] === array( 'คณะสังคมศาสตร์' . MCUPR_AUD_SEP . 'รัฐศาสตร์' ), 'saving: a major of a ticked faculty is not kept twice; a broken value is dropped' );
as_user( $uA );
t( mcupr_aud_can_view( $post ), 'student A (bachelor, year 2, Buddhism): sees it' );
as_user( $uB );
t( ! mcupr_aud_can_view( $post ), 'student B (year 3): does not' );
as_user( $uC );
t( ! mcupr_aud_can_view( $post ), 'student C (master): does not' );
as_user( $uD );
t( mcupr_aud_can_view( $post ), 'student D (Social science, year 1, bachelor): in through the ticked major (level and year also match)' );
as_user( $uE );
t( ! mcupr_aud_can_view( $post ), 'student E (on leave): not studying, does not' );
as_user( 0 );
t( ! mcupr_aud_can_view( $post ), 'a visitor does not' );
as_user( 1 );
t( mcupr_aud_can_view( $post ), 'staff always see it' );
$listed = function () use ( $post ) { $q = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID' ) ); return in_array( $post, array_map( 'intval', $q->posts ), true ); };
as_user( $uA );
t( $listed(), 'a student in the group finds it in the lists' );
as_user( $uB );
t( ! $listed(), 'a student outside the group does not find it in the lists' );
as_user( 0 );
t( ! $listed(), 'a visitor does not find it in the lists' );

// ---- the list column, the options and the box
t( 'ปริญญาตรี' === mcupr_student_levels()['bachelor'] || '' !== mcupr_aud_summary( $rr ), 'column: the rules are summarised' );
$sum = mcupr_aud_summary( $rr );
t( false !== strpos( $sum, 'คณะพุทธศาสตร์' ) && false !== strpos( $sum, '1, 2' ), 'column: the summary names the years and the faculty (' . $sum . ')' );
delete_transient( 'mcupr_aud_opts' );
$o = mcupr_aud_options();
t( 4 === $o['total'], 'options: counts only students who are studying (4 of 5)' );
$fn = mcupr_aud_norm( 'คณะพุทธศาสตร์' );
t( isset( $o['fac'][ $fn ] ) && 3 === $o['fac'][ $fn ][1] && isset( $o['maj'][ $fn ] ) && 2 === count( $o['maj'][ $fn ] ), 'options: a faculty with its count and its majors' );
mcupr_student_upsert( array( 'sid' => 'AUD006', 'name' => 'ทดสอบ AUD006', 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2568', 'faculty' => 'คณะครุศาสตร์', 'major' => 'การสอน', 'status' => 'studying', 'email' => 'aud006@example.org', 'phone' => '', 'note' => '' ), 'add_update' );
t( false === get_transient( 'mcupr_aud_opts' ), 'options: a saved student clears the ten-minute memory' );
ob_start();
mcupr_aud_box( get_post( $post ) );
$html = ob_get_clean();
t( false !== strpos( $html, 'name="mcupr_aud_years[]"' ) && false !== strpos( $html, 'name="mcupr_aud_fac[]"' ) && false !== strpos( $html, 'name="mcupr_aud_maj[]"' ) && false !== strpos( $html, 'data-combos=' ), 'box: levels, years, faculties, majors and the data for the live count' );
t( preg_match( '/name="mcupr_aud_fac\[\]" value="คณะพุทธศาสตร์"[^>]*checked/u', $html ) && preg_match( '/name="mcupr_aud_maj\[\]" value="คณะพุทธศาสตร์\|\|พุทธศาสตร์"[^>]*checked[^>]*disabled/u', $html ), 'box: a ticked faculty shows its majors ticked and left out of the form' );

// ---- the index can be built from nothing (it asks the database, which asks the index…)
mcupr_aud_reset();
$t0 = microtime( true );
$idx = mcupr_aud_index();
t( isset( $idx[ $post ] ) && microtime( true ) - $t0 < 20 && empty( $GLOBALS['mcupr_aud_building'] ), 'index: rebuilt from nothing without looping, and it finds the restricted content' );
as_user( $uB );
t( ! mcupr_aud_can_view( $post ), 'index: the rebuilt index still keeps student B out' );

// ---- the old index (levels only) is not read; a fresh one is made
update_option( 'mcupr_aud_index', array( $post => array( 'bachelor' ) ), true );
mcupr_aud_reset();
t( false === get_option( 'mcupr_aud_index', false ), 'upgrade: the older index option is removed' );

wp_delete_post( $post, true );
wipe();
mcupr_aud_reset();
delete_transient( 'mcupr_aud_opts' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
