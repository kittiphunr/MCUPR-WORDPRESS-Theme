<?php
/** E-mail to students in groups: who gets it, the text, one by one, the hourly limit, the time, the attachment, stop announcements.   php php/student-mail.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mails = array();
add_filter( 'pre_wp_mail', function ( $r, $a ) use ( &$mails ) { $mails[] = $a; return true; }, 10, 2 );
add_filter( 'mcupr_mail_accept_non_upload', '__return_true' );
function wipe() {
	foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_MAILJOB, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
	delete_option( 'mcupr_mail_hour' ); delete_option( 'mcupr_mail_opts' ); delete_option( 'mcupr_mail_tpls' );
}
function stu( $sid, $o = array() ) {
	$d = array_merge( array( 'sid' => $sid, 'name' => 'นิสิต ' . $sid, 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2567', 'faculty' => 'คณะพุทธศาสตร์', 'major' => 'พุทธศาสตร์', 'status' => 'studying', 'email' => "s$sid@example.org", 'phone' => '', 'note' => '' ), $o );
	list( , $id ) = mcupr_student_upsert( $d, 'add_update' );
	return $id;
}
wp_set_current_user( 1 );
wipe();
$fx = __DIR__ . '/../fixtures/';

// ---- who
$a = stu( '9001' ); $b = stu( '9002', array( 'year' => '2' ) ); $c = stu( '9003', array( 'level' => 'master', 'faculty' => 'คณะครุศาสตร์', 'major' => 'การสอน' ) );
$d = stu( '9004', array( 'email' => '' ) ); $e = stu( '9005', array( 'status' => 'graduated' ) ); $f = stu( '9006', array( 'email' => 's9001@example.org' ) );
$all = mcupr_mail_recipients( array() );
t( array( $a, $b, $c ) === $all['ids'], 'who: all that study, have an e-mail, once per address (no graduate, no empty e-mail, no twin)' );
t( 1 === $all['no_email'] && 1 === $all['dup'] && 5 === $all['matched'], 'who: the numbers left out are counted' );
t( array( $a, $b ) === mcupr_mail_recipients( array( 'levels' => array( 'bachelor' ) ) )['ids'], 'who: ticking a level' );
t( array( $b ) === mcupr_mail_recipients( array( 'levels' => array( 'bachelor' ), 'years' => array( '2' ) ) )['ids'], 'who: level and year together' );
t( array( $c ) === mcupr_mail_recipients( array( 'fac' => array( 'คณะครุศาสตร์' ) ) )['ids'], 'who: ticking a faculty' );
mcupr_mail_set_stopped( $b, true );
$r = mcupr_mail_recipients( array() );
t( array( $a, $c ) === $r['ids'] && 1 === $r['stopped'], 'who: a student who stopped announcements is left out and counted' );
mcupr_mail_set_stopped( $b, false );
t( in_array( $b, mcupr_mail_recipients( array() )['ids'], true ), 'who: and comes back when they start again' );

// ---- the text and the unsubscribe link
$tx = mcupr_mail_text( 'สวัสดี {name} ({student_id})', $a );
t( false !== strpos( $tx, 'สวัสดี นิสิต 9001 (9001)' ), 'text: {name} and {student_id} are filled in' );
t( false !== strpos( $tx, mcupr_mail_unsub_url( $a ) ) && false !== strpos( $tx, mcupr_student_portal_url() ), 'text: ends with the portal and the link to stop' );
t( false === strpos( mcupr_mail_text( '{name}', $b ), mcupr_mail_unsub_token( $a ) ), 'text: the link is the student’s own' );
$tok = mcupr_mail_unsub_token( $a );
t( $a === mcupr_mail_unsub_check( $tok ) && 0 === mcupr_mail_unsub_check( $tok . 'x' ) && 0 === mcupr_mail_unsub_check( '999999.' . str_repeat( 'a', 32 ) ), 'link: only the right token works' );
update_post_meta( $a, '_mcupr_s_email', 'changed@example.org' );
t( 0 === mcupr_mail_unsub_check( $tok ), 'link: stops working when the e-mail address in the record is changed' );
update_post_meta( $a, '_mcupr_s_email', 's9001@example.org' );
t( in_array( 'List-Unsubscribe: <' . mcupr_mail_unsub_url( $a ) . '>', mcupr_mail_headers( 'help@example.org', $a ), true ) && in_array( 'Reply-To: help@example.org', mcupr_mail_headers( 'help@example.org', $a ), true ), 'headers: List-Unsubscribe and Reply-To' );
t( ! preg_grep( '/Reply-To/', mcupr_mail_headers( 'not-an-address', $a ) ), 'headers: a bad reply-to address is dropped' );

// ---- sending: one by one
$mails = array();
$id  = mcupr_mail_create( 'ประกาศ', 'ข้อความ {name}', 'help@example.org', array(), mcupr_mail_recipients( array() )['ids'], null );
$job = mcupr_mail_job( $id );
t( $id && 'running' === $job['status'] && 3 === $job['total'] && wp_next_scheduled( 'mcupr_mail_tick' ), 'send: a job is made and the background run is planned' );
$job = mcupr_mail_run( $id );
t( 'done' === $job['status'] && 3 === $job['sent'] && 3 === count( $mails ), 'send: every student gets one e-mail' );
t( 1 === count( (array) $mails[0]['to'] ) && 'ประกาศ' === $mails[0]['subject'] && false !== strpos( $mails[0]['message'], 'ข้อความ นิสิต 9001' ), 'send: each e-mail has one address and the student’s own name' );
t( count( array_unique( array_map( function ( $m ) { return implode( ',', (array) $m['to'] ); }, $mails ) ) ) === 3, 'send: three different addresses, no one sees another' );
$ev = array_map( function ( $p ) { return $p->post_title; }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
t( in_array( 'mail', $ev, true ) && in_array( 'maildone', $ev, true ), 'send: the log says it started and ended' );
$before = count( $mails );
mcupr_mail_run( $id );
t( count( $mails ) === $before, 'send: a finished job sends nothing more' );

// ---- the hourly limit
$mails = array(); delete_option( 'mcupr_mail_hour' );
update_option( 'mcupr_mail_opts', array( 'cap' => 2 ) );
t( 2 === mcupr_mail_cap(), 'limit: the setting is read' );
$id  = mcupr_mail_create( 'Slow', 'x', '', array(), array( $a, $b, $c ), null );
$job = mcupr_mail_run( $id );
t( 2 === count( $mails ) && 'running' === $job['status'] && ! empty( $job['wait'] ) && 2 === $job['sent'], 'limit: only 2 go out in this hour, the job waits' );
$job = mcupr_mail_run( $id );
t( 2 === count( $mails ), 'limit: asking again in the same hour sends nothing' );
update_option( 'mcupr_mail_hour', array( 'k' => (int) floor( time() / HOUR_IN_SECONDS ) - 1, 'n' => 2 ) );
$job = mcupr_mail_run( $id );
t( 3 === count( $mails ) && 'done' === $job['status'], 'limit: the next hour the rest goes' );
update_option( 'mcupr_mail_opts', array( 'cap' => 0 ) );
t( PHP_INT_MAX === mcupr_mail_hour_left(), 'limit: 0 means no limit' );
delete_option( 'mcupr_mail_opts' );
t( 100 === mcupr_mail_cap(), 'limit: 100 an hour by default' );

// ---- left out while sending
$mails = array(); delete_option( 'mcupr_mail_hour' );
$id = mcupr_mail_create( 'Later change', 'x', '', array(), array( $a, $b, $c ), null );
mcupr_mail_set_stopped( $b, true ); update_post_meta( $c, '_mcupr_s_status', 'graduated' );
$job = mcupr_mail_run( $id );
t( 1 === count( $mails ) && 2 === $job['skipped'] && 'done' === $job['status'], 'send: who stopped or graduated since the list was made is skipped' );
mcupr_mail_set_stopped( $b, false ); update_post_meta( $c, '_mcupr_s_status', 'studying' );

// ---- failure
$mails = array(); delete_option( 'mcupr_mail_hour' );
add_filter( 'pre_wp_mail', $fail = function () { return false; }, 20 );
$id  = mcupr_mail_create( 'Fail', 'x', '', array(), array( $a, $b ), null );
$job = mcupr_mail_run( $id );
remove_filter( 'pre_wp_mail', $fail, 20 );
t( 2 === $job['failed'] && 0 === $job['sent'] && 'done' === $job['status'] && array( $a, $b ) === $job['failed_ids'], 'send: failures are counted and the job still ends' );

// ---- the time
$mails = array(); delete_option( 'mcupr_mail_hour' );
$id  = mcupr_mail_create( 'Tomorrow', 'x', '', array(), array( $a ), null, time() + DAY_IN_SECONDS );
$job = mcupr_mail_run( $id );
t( 'scheduled' === $job['status'] && ! $mails, 'time: a message for tomorrow waits' );
$j = mcupr_mail_job( $id ); $j['at'] = time() - 5; mcupr_mail_job_put( $j );
$job = mcupr_mail_run( $id );
t( 'done' === $job['status'] && 1 === count( $mails ), 'time: it goes when the time comes' );

// ---- cancel
$id = mcupr_mail_create( 'Stop me', 'x', '', array(), array( $a, $b ), null, time() + DAY_IN_SECONDS );
t( mcupr_mail_cancel( $id ) && 'cancelled' === mcupr_mail_job( $id )['status'] && ! mcupr_mail_cancel( $id ), 'cancel: stops a waiting job, once' );
$mails = array(); mcupr_mail_run( $id );
t( ! $mails, 'cancel: a stopped job sends nothing' );

// ---- the attachment
$mails = array(); delete_option( 'mcupr_mail_hour' );
list( $file, $err ) = mcupr_mail_check_att( array( 'name' => 'a b.pdf', 'tmp_name' => $fx . 'book.pdf', 'size' => filesize( $fx . 'book.pdf' ), 'error' => 0 ) );
t( $file && '' === $err && 'application/pdf' === $file['mime'], 'attachment: a PDF is accepted' );
list( $x, $err ) = mcupr_mail_check_att( array( 'name' => 'x.php.pdf', 'tmp_name' => __FILE__, 'size' => filesize( __FILE__ ), 'error' => 0 ) );
t( ! $x && 'type' === $err, 'attachment: a PHP file named .pdf is refused' );
list( $x, $err ) = mcupr_mail_check_att( array( 'name' => 'big.pdf', 'tmp_name' => $fx . 'book.pdf', 'size' => 5 * MB_IN_BYTES, 'error' => 0 ) );
t( ! $x && 'size' === $err, 'attachment: a file over the limit is refused' );
list( $x, $err ) = mcupr_mail_check_att( array( 'error' => UPLOAD_ERR_NO_FILE ) );
t( null === $x && '' === $err, 'attachment: none chosen is fine' );
$kept = mcupr_mail_keep_att( $file );
$path = mcupr_mail_att_path( $kept );
t( $kept && is_file( $path ) && 0 === strpos( $path, mcupr_mail_dir() ) && is_file( mcupr_mail_dir() . '/.htaccess' ), 'attachment: kept in the closed folder' );
$id = mcupr_mail_create( 'With file', 'x', '', array(), array( $a, $b ), $kept );
$job = mcupr_mail_run( $id );
t( 2 === count( $mails ) && ! empty( $mails[0]['attachments'] ) && ! empty( $mails[1]['attachments'] ), 'attachment: goes with each e-mail' );
t( ! is_file( $path ) && null === mcupr_mail_job( $id )['att'], 'attachment: deleted when the job ends' );
$kept2 = mcupr_mail_keep_att( $file ); $p2 = mcupr_mail_att_path( $kept2 );
$id = mcupr_mail_create( 'Cancel file', 'x', '', array(), array( $a ), $kept2, time() + DAY_IN_SECONDS );
mcupr_mail_cancel( $id );
t( ! is_file( $p2 ), 'attachment: deleted when the job is stopped' );

// ---- the unsubscribe page
$tok = mcupr_mail_unsub_token( $c );
$url = mcupr_mail_unsub_url( $c );
$get = wp_remote_get( $url, array( 'timeout' => 20 ) );
t( ! is_wp_error( $get ) && 200 === wp_remote_retrieve_response_code( $get ), 'page: the link opens (' . ( is_wp_error( $get ) ? $get->get_error_message() : wp_remote_retrieve_response_code( $get ) ) . ')' );
if ( ! is_wp_error( $get ) ) {
	$h = wp_remote_retrieve_body( $get );
	t( false !== strpos( $h, 'name="do"' ) && false === strpos( $h, 's9003@example.org' ) && false !== strpos( $h, 's***@example.org' ) && ! get_post_meta( $c, '_mcupr_s_nomail', true ), 'page: opening the link changes nothing, and the full address is not shown' );
	t( false !== strpos( $h, 'noindex' ), 'page: not for search engines' );
	$post = wp_remote_post( $url, array( 'timeout' => 20, 'body' => array( 'do' => 'stop' ) ) );
	wp_cache_flush(); // (the page ran in another process)
	t( (bool) get_post_meta( $c, '_mcupr_s_nomail', true ), 'page: the button stops announcements for that student' );
	t( ! get_post_meta( $a, '_mcupr_s_nomail', true ), 'page: and only for that student' );
	wp_remote_post( $url, array( 'timeout' => 20, 'body' => array( 'do' => 'start' ) ) );
	wp_cache_flush();
	t( ! get_post_meta( $c, '_mcupr_s_nomail', true ), 'page: and starts them again' );
	$bad = wp_remote_post( home_url( '/?mcupr_unsub=' . $tok . 'x' ), array( 'timeout' => 20, 'body' => array( 'do' => 'stop' ) ) );
	wp_cache_flush();
	t( ! get_post_meta( $c, '_mcupr_s_nomail', true ) && 200 === wp_remote_retrieve_response_code( $bad ), 'page: a wrong token does nothing' );
}
$ev = array_map( function ( $p ) { return $p->post_title; }, get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1 ) ) );
t( in_array( 'unsub', $ev, true ) && in_array( 'resub', $ev, true ), 'page: stopping and starting are in the log' );

// ---- the admin page and the handlers
$o = mcupr_mail_cap();
ob_start(); mcupr_mail_page(); $pg = ob_get_clean();
t( false !== strpos( $pg, 'mm-subject' ) && false !== strpos( $pg, 'mcupr_aud_levels[]' ) && false !== strpos( $pg, 'name="checked"' ) && false !== strpos( $pg, 'enctype="multipart/form-data"' ), 'admin: the page has the message, the choice of who, the "checked" box and the file' );
t( false !== strpos( $pg, 'name="at"' ) && false !== strpos( $pg, 'name="tplname"' ) && false !== strpos( $pg, 'name="cap"' ), 'admin: the time, the template name and the limit' );
$map = array_map( function ( $s ) { return $s; }, array_keys( mcupr_audit_actions() ) );
t( ! array_diff( array( 'mail', 'maildone', 'unsub', 'resub' ), $map ), 'admin: the new log events have names' );
t( has_action( 'admin_post_mcupr_mail_send' ) && has_action( 'admin_post_mcupr_mail_cancel' ) && has_action( 'wp_ajax_mcupr_mail_preview' ) && ! has_action( 'wp_ajax_nopriv_mcupr_mail_preview' ) && ! has_action( 'wp_ajax_nopriv_mcupr_mail_step' ), 'admin: handlers exist, and none for visitors who are not logged in' );
$editor = wp_insert_user( array( 'user_login' => 'mm-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'mm-edi@example.org', 'role' => 'editor' ) );
$reg    = wp_insert_user( array( 'user_login' => 'mm-reg', 'user_pass' => wp_generate_password(), 'user_email' => 'mm-reg@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
t( ! user_can( $editor, MCUPR_STU_CAP ) && user_can( $reg, MCUPR_STU_CAP ), 'admin: an editor cannot use it, the registrar can' );
$mf = file_get_contents( __DIR__ . '/../../mcupr/inc/students-mail.php' );
t( substr_count( $mf, 'wp_verify_nonce' ) >= 1 && substr_count( $mf, 'check_ajax_referer' ) >= 2, 'admin: every handler checks the nonce' );

// ---- the clean-up
$old = mcupr_mail_create( 'Old', 'x', '', array(), array( $a ), mcupr_mail_keep_att( $file ) );
wp_update_post( array( 'ID' => $old, 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 800 * DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 800 * DAY_IN_SECONDS ) ) );
$op = mcupr_mail_att_path( mcupr_mail_job( $old )['att'] );
do_action( 'mcupr_audit_purge' );
t( ! mcupr_mail_job( $old ) && ! is_file( $op ), 'clean-up: an old job and its file go with the log' );

wipe();
wp_delete_user( $editor ); wp_delete_user( $reg );
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
