<?php
/** Requests: a form made a request, numbers, status and history, e-mails, who may send / see, files, following a request, the CSV.   php php/requests.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mails = array();
add_filter( 'pre_wp_mail', function ( $r, $a ) use ( &$mails ) { $mails[] = $a; return true; }, 10, 2 );
add_filter( 'mcupr_rq_accept_non_upload', '__return_true' );
$fx = __DIR__ . '/../fixtures/';
function cleanup() {
	foreach ( get_posts( array( 'post_type' => array( 'mcupr_fe', 'mcupr_fb', 'mcupr_student' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'OR', array( 'key' => '_mcupr_rq_test', 'compare' => 'EXISTS' ), array( 'key' => '_mcupr_fe_test', 'compare' => 'EXISTS' ) ) ) ) as $x ) { wp_delete_post( $x, true ); }
}
function form( $title, $fields, $rq = null ) {
	$id = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => $title ) );
	update_post_meta( $id, '_mcupr_fb_fields', mcupr_fb_clean_fields( $fields ) );
	update_post_meta( $id, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'notify' => 'staff@example.org' ) ) );
	update_post_meta( $id, '_mcupr_rq_test', 1 );
	if ( $rq ) { update_post_meta( $id, '_mcupr_rq_opts', $rq ); }
	return $id;
}
function send( $fid, $vals, $files = array() ) {
	delete_transient( mcupr_form_visitor_key() ); // the limit per visitor is not what is tested here
	$data = array( 'tk' => mcupr_form_token( time() - 10 ), 'website' => '', 'f' => $vals );
	return mcupr_fb_submit( $fid, $data, time(), $files );
}
wp_set_current_user( 1 );
$sf = $fx . 'book.pdf';

// ---- the form: a file field, the settings
t( isset( mcupr_fb_types()['file'] ), 'the form builder has a "file" field' );
$clean = mcupr_fb_clean_fields( array( array( 'type' => 'file', 'label' => 'Copy of the ID card', 'required' => 1 ) ) );
t( 'file' === $clean[0]['type'] && 1 === $clean[0]['required'] && array() === $clean[0]['options'], 'a file field keeps its label and "required"' );
$fields = array( array( 'id' => 'email', 'type' => 'email', 'label' => 'E-mail' ), array( 'id' => 'why', 'type' => 'textarea', 'label' => 'Reason', 'required' => 1 ), array( 'id' => 'doc', 'type' => 'file', 'label' => 'Document' ) );
$rf = form( 'Certificate of student status', $fields, array( 'on' => 1, 'who' => 'students', 'owner' => 1 ) );
$pf = form( 'Public document request', $fields, array( 'on' => 1, 'who' => 'all', 'owner' => 0 ) );
$nf = form( 'Plain survey', array( array( 'id' => 'q', 'type' => 'text', 'label' => 'Q' ) ) );
t( mcupr_rq_is_form( $rf ) && mcupr_rq_is_form( $pf ) && ! mcupr_rq_is_form( $nf ), 'only forms that were switched on are requests' );
t( 'students' === mcupr_rq_opts( $rf )['who'] && 'all' === mcupr_rq_opts( $pf )['who'] && 1 === mcupr_rq_opts( $rf )['owner'], 'who can send it, and who sees to it' );
update_post_meta( $nf, '_mcupr_rq_opts', array( 'on' => 1, 'who' => 'nobody', 'owner' => '7' ) );
t( 'students' === mcupr_rq_opts( $nf )['who'] && 7 === mcupr_rq_opts( $nf )['owner'], 'an unknown "who" falls back to students' );
delete_post_meta( $nf, '_mcupr_rq_opts' );

// ---- a student
$stu = array( 'sid' => '8801', 'name' => 'นิสิต คำร้อง', 'name_en' => '', 'level' => 'bachelor', 'year' => '2', 'entry' => '2567', 'faculty' => 'คณะพุทธศาสตร์', 'major' => 'พุทธศาสตร์', 'status' => 'studying', 'email' => 'req-stu@example.org', 'phone' => '', 'note' => '' );
list( , $sp ) = mcupr_student_upsert( $stu, 'add_update' );
update_post_meta( $sp, '_mcupr_rq_test', 1 );
$su = mcupr_student_make_account( $sp );
t( is_int( $su ), 'a student with a login' );
$other = wp_insert_user( array( 'user_login' => 'rq-other', 'user_pass' => wp_generate_password(), 'user_email' => 'other@example.org', 'role' => 'subscriber' ) );
$reg   = wp_insert_user( array( 'user_login' => 'rq-reg', 'user_pass' => wp_generate_password(), 'user_email' => 'reg@example.org', 'role' => MCUPR_REGISTRAR_ROLE ) );
$edi   = wp_insert_user( array( 'user_login' => 'rq-edi', 'user_pass' => wp_generate_password(), 'user_email' => 'edi@example.org', 'role' => 'editor' ) );

// ---- who may send
wp_set_current_user( 0 );
$r = send( $rf, array( 'why' => 'need it' ) );
t( ! $r['ok'] && isset( $r['errors']['student'] ), 'a visitor cannot send a students-only request' );
wp_set_current_user( $other );
$r = send( $rf, array( 'why' => 'need it' ) );
t( ! $r['ok'] && isset( $r['errors']['student'] ), 'a logged-in person who is not a student cannot either' );
t( false !== strpos( mcupr_fb_html( $rf ), 'stu-login' ) || false !== strpos( mcupr_fb_html( $rf ), 'student' ), 'the form says that students should log in first' );
wp_set_current_user( $su );
$mails = array();
$r1    = send( $rf, array( 'why' => 'for a scholarship', 'email' => '' ) );
t( $r1['ok'] && $r1['entry'] > 0, 'a student can send it' );
$e1   = $r1['entry'];
$code = (string) get_post_meta( $e1, '_mcupr_rq_code', true );
update_post_meta( $e1, '_mcupr_fe_test', 1 );
t( 1 === preg_match( '/^REQ-\d{4}-\d{5}$/', $code ), 'the request got a number (' . $code . ')' );
t( 'new' === get_post_meta( $e1, '_mcupr_rq_status', true ) && (int) get_post_meta( $e1, '_mcupr_rq_uid', true ) === $su && (int) get_post_meta( $e1, '_mcupr_rq_sid', true ) === $sp && '8801' === get_post_meta( $e1, '_mcupr_rq_sidcode', true ), 'status "new", the login and the student are kept' );
t( 'req-stu@example.org' === get_post_meta( $e1, '_mcupr_rq_email', true ), 'the e-mail of the student record is used when the form has none' );
t( 1 === (int) get_post_meta( $e1, '_mcupr_rq_owner', true ), 'the person who sees to it is the one set on the form' );
$log = get_post_meta( $e1, '_mcupr_rq_log', true );
t( 1 === count( $log ) && 'new' === $log[0]['s'], 'the history starts with "new"' );
$to = array_map( function ( $m ) { return is_array( $m['to'] ) ? implode( ',', $m['to'] ) : $m['to']; }, $mails );
t( in_array( 'staff@example.org', $to, true ) && in_array( 'req-stu@example.org', $to, true ), 'the staff and the sender are told by e-mail (' . count( $mails ) . ' mails)' );
$conf = array_values( array_filter( $mails, function ( $m ) { return 'req-stu@example.org' === ( is_array( $m['to'] ) ? implode( ',', $m['to'] ) : $m['to'] ); } ) );
t( $conf && false !== strpos( $conf[0]['message'], $code ) && ( false !== strpos( $conf[0]['message'], 'mcupr_request=1' ) || false !== strpos( $conf[0]['message'], '/request/' ) ), 'the e-mail to the sender holds the number and the address to follow it' );
$r2 = send( $rf, array( 'why' => 'second' ) );
$c2 = (string) get_post_meta( $r2['entry'], '_mcupr_rq_code', true );
update_post_meta( $r2['entry'], '_mcupr_fe_test', 1 );
t( $c2 !== $code && (int) substr( $c2, -5 ) === (int) substr( $code, -5 ) + 1, 'numbers go up by one' );
$rn = send( $nf, array( 'q' => 'x' ) );
update_post_meta( $rn['entry'], '_mcupr_fe_test', 1 );
t( $rn['ok'] && ! mcupr_rq_is_entry( $rn['entry'] ), 'an ordinary form is not touched (no number)' );
wp_set_current_user( 0 );
$mails = array();
$r3 = send( $pf, array( 'email' => 'guest@example.org', 'why' => 'visitor' ) );
update_post_meta( $r3['entry'], '_mcupr_fe_test', 1 );
t( $r3['ok'] && mcupr_rq_is_entry( $r3['entry'] ) && 'guest@example.org' === get_post_meta( $r3['entry'], '_mcupr_rq_email', true ) && 0 === (int) get_post_meta( $r3['entry'], '_mcupr_rq_uid', true ), 'anyone can send a public request; their e-mail is kept' );
$e3 = $r3['entry'];

// ---- status and history
$mails = array();
t( ! mcupr_rq_set( $e1, 'nonsense', '', 1 ), 'an unknown status is refused' );
t( ! mcupr_rq_set( $e1, 'new', '', 1 ), 'nothing changes: nothing is written' );
t( mcupr_rq_set( $e1, 'review', "We are checking <b>it</b>.", $edi, true ), 'the status can change' );
$log = get_post_meta( $e1, '_mcupr_rq_log', true );
t( 2 === count( $log ) && 'review' === $log[1]['s'] && 'We are checking it.' === $log[1]['m'] && $edi === $log[1]['u'], 'the history keeps status, message (tags stripped) and who' );
t( 1 === count( $mails ) && 'req-stu@example.org' === ( is_array( $mails[0]['to'] ) ? $mails[0]['to'][0] : $mails[0]['to'] ) && false !== strpos( $mails[0]['message'], 'We are checking it.' ) && false !== strpos( $mails[0]['subject'], $code ), 'the sender is told by e-mail (the number is in the subject, the message in the text)' );
$mails = array();
t( mcupr_rq_set( $e1, 'review', 'A note without a change', $edi, false ) && 0 === count( $mails ), 'a message alone is written, and no e-mail goes when it is not asked for' );
t( mcupr_rq_set( $e1, 'need', 'Please send the copy of your ID card.', $edi, true ) && 'need' === get_post_meta( $e1, '_mcupr_rq_status', true ), 'status "more information needed"' );

// ---- following a request
t( $e1 === mcupr_rq_find( $code, 'req-stu@example.org' ) && $e1 === mcupr_rq_find( strtolower( $code ), 'REQ-STU@example.org' ), 'the number and the e-mail find the request (any case)' );
t( $e1 === mcupr_rq_find( $code, '8801' ), 'the number and the student ID find it too' );
t( 0 === mcupr_rq_find( $code, 'someone@example.org' ) && 0 === mcupr_rq_find( $code, '' ) && 0 === mcupr_rq_find( 'REQ-2026-99999', 'req-stu@example.org' ) && 0 === mcupr_rq_find( "x' OR 1=1", 'a' ), 'a wrong e-mail, nothing, a number that does not exist or rubbish find nothing' );
t( $e3 === mcupr_rq_find( (string) get_post_meta( $e3, '_mcupr_rq_code', true ), 'guest@example.org' ) && 0 === mcupr_rq_find( (string) get_post_meta( $e3, '_mcupr_rq_code', true ), '8801' ), 'a public request is found with its own e-mail only' );
$tl = mcupr_rq_timeline_html( $e1 );
t( substr_count( $tl, '<li' ) === 4 && false !== strpos( $tl, 'Please send the copy' ) && false === strpos( $tl, 'edi' ), 'the history the sender sees: statuses and messages, never the names of the staff' );

// ---- who sees what: the capability and the files
t( user_can( $reg, MCUPR_RQ_CAP ) && user_can( 1, MCUPR_RQ_CAP ) && ! user_can( $edi, MCUPR_RQ_CAP ) && ! user_can( $other, MCUPR_RQ_CAP ) && ! user_can( $su, MCUPR_RQ_CAP ), 'registrar and administrator handle requests; being able to edit pages is not enough, nor is a student or any other account' );
update_user_meta( $edi, 'mcupr_requests', 1 );
clean_user_cache( $edi );
t( user_can( $edi, MCUPR_RQ_CAP ), 'a person ticked "may handle requests" on their profile handles them' );
delete_user_meta( $edi, 'mcupr_requests' );
clean_user_cache( $edi );
t( user_can( $reg, 'edit_post', $e1 ) && ! user_can( $reg, 'edit_post', $rn['entry'] ) && user_can( $edi, 'edit_post', $rn['entry'] ) && ! user_can( $su, 'edit_post', $e1 ), 'the registrar can open requests but not other form answers; a student cannot open any' );
t( user_can( $reg, get_post_type_object( 'mcupr_fe' )->cap->edit_posts ), 'the registrar can open the list of answers (it shows only requests)' );

// ---- files
$fieldsF = mcupr_fb_fields( $rf );
$goodPdf = array( 'file_doc' => array( 'name' => 'ใบสมัคร.pdf', 'type' => 'application/pdf', 'tmp_name' => $sf, 'error' => 0, 'size' => filesize( $sf ) ) );
list( $ok, $er ) = mcupr_rq_check_files( $fieldsF, $goodPdf );
t( isset( $ok['doc'] ) && ! $er && 'application/pdf' === $ok['doc']['mime'], 'a PDF is accepted' );
$png = array( 'file_doc' => array( 'name' => 'card.png', 'type' => 'image/png', 'tmp_name' => $fx . 'book-page-1.png', 'error' => 0, 'size' => filesize( $fx . 'book-page-1.png' ) ) );
t( ! mcupr_rq_check_files( $fieldsF, $png )[1], 'a PNG is accepted' );
$txt = tempnam( sys_get_temp_dir(), 'rq' ); file_put_contents( $txt, "just text\n" );
t( 'file_type' === mcupr_rq_check_files( $fieldsF, array( 'file_doc' => array( 'name' => 'x.pdf', 'tmp_name' => $txt, 'error' => 0, 'size' => 10 ) ) )[1]['doc'], 'text called .pdf is refused (the content is looked at)' );
t( 'file_type' === mcupr_rq_check_files( $fieldsF, array( 'file_doc' => array( 'name' => 'x.php', 'tmp_name' => $sf, 'error' => 0, 'size' => 10 ) ) )[1]['doc'], 'a .php file is refused' );
$big = tempnam( sys_get_temp_dir(), 'rq' ); file_put_contents( $big, str_repeat( 'A', mcupr_rq_max_bytes() + 10 ) );
t( 'file_size' === mcupr_rq_check_files( $fieldsF, array( 'file_doc' => array( 'name' => 'big.pdf', 'tmp_name' => $big, 'error' => 0, 'size' => filesize( $big ) ) ) )[1]['doc'], 'a file over the limit is refused' );
t( 'file_size' === mcupr_rq_check_files( $fieldsF, array( 'file_doc' => array( 'name' => 'a.pdf', 'tmp_name' => $sf, 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0 ) ) )[1]['doc'], 'the server saying "too big" is understood' );
$req = mcupr_fb_clean_fields( array( array( 'id' => 'doc', 'type' => 'file', 'label' => 'D', 'required' => 1 ) ) );
t( 'required' === mcupr_rq_check_files( $req, array() )[1]['doc'], 'a required file that is missing is a mistake' );
wp_set_current_user( $su );
$mails = array();
$rf2 = send( $rf, array( 'why' => 'with a file' ), $goodPdf );
update_post_meta( $rf2['entry'], '_mcupr_fe_test', 1 );
$files = get_post_meta( $rf2['entry'], '_mcupr_rq_files', true );
t( $rf2['ok'] && 1 === count( $files ) && false !== strpos( $files[0]['n'], '.pdf' ), 'the answer keeps its file' );
$path = mcupr_rq_dir( $files[0]['k'] ) . '/' . $files[0]['f'];
t( is_file( $path ) && 0 === strpos( file_get_contents( $path, false, null, 0, 5 ), '%PDF' ), 'the file is on disk' );
$closed = static function ( $base ) {
	$up = wp_upload_dir();
	// either the folder is outside the web root (nothing can ask for it), or it is inside uploads and denied there
	return ! mcupr_path_inside( trailingslashit( wp_normalize_path( realpath( $base ) ) ), trailingslashit( wp_normalize_path( $up['basedir'] ) ) )
		|| ( is_file( $base . '/.htaccess' ) && is_file( $base . '/index.php' ) );
};
t( $closed( mcupr_rq_dir() ) && is_file( mcupr_rq_dir( $files[0]['k'] ) . '/index.php' ), 'the folder is out of the web server\'s reach: outside the web root, or inside uploads with a .htaccess that denies everything' );
t( 24 === strlen( $files[0]['k'] ), 'the folder of the file has an unguessable name' );
$rows = get_post_meta( $rf2['entry'], '_mcupr_fe_rows', true );
t( false !== strpos( $rows[2][1], '.pdf' ), 'the answers list the file name and size' );
wp_set_current_user( $su );
t( mcupr_rq_can_see( $rf2['entry'] ), 'the sender may get the file' );
wp_set_current_user( $reg );
t( mcupr_rq_can_see( $rf2['entry'] ), 'the registrar may get the file' );
wp_set_current_user( $other );
t( ! mcupr_rq_can_see( $rf2['entry'] ), 'another logged-in person may not' );
wp_set_current_user( 0 );
t( ! mcupr_rq_can_see( $rf2['entry'] ), 'a visitor may not' );
// over HTTP: nobody but the sender and the staff gets it
$u  = mcupr_rq_file_url( $rf2['entry'], 0 );
$rr = wp_remote_get( $u, array( 'timeout' => 15, 'redirection' => 0 ) );
if ( is_wp_error( $rr ) ) {
	echo "SKIP the download over HTTP (the site does not answer here)\n";
} else {
	t( 403 === wp_remote_retrieve_response_code( $rr ) && false === strpos( wp_remote_retrieve_body( $rr ), '%PDF' ), 'over HTTP a visitor gets 403 and none of the file' );
}
$dir = mcupr_rq_dir( $files[0]['k'] );
wp_set_current_user( 1 );
wp_delete_post( $rf2['entry'], true );
t( ! is_dir( $dir ), 'deleting the answer deletes its file and folder' );

// ---- the sender's lists
wp_set_current_user( $su );
$mine = mcupr_rq_of_user( $su );
t( count( $mine ) >= 2 && 0 === count( mcupr_rq_of_user( $other ) ), 'the student sees their own requests and nobody else\'s' );
$ph = mcupr_rq_portal_html();
t( false !== strpos( $ph, $code ) && false !== strpos( $ph, 'rq-st' ) && false !== strpos( $ph, 'Certificate of student status' ), 'the portal lists the requests with their number and status' );
t( false === strpos( $ph, (string) get_post_meta( $e3, '_mcupr_rq_code', true ) ), 'the portal does not list another person\'s request' );
t( false !== strpos( $ph, 'rq=' . $rf ) && false !== strpos( $ph, 'rq=' . $pf ) && false === strpos( $ph, 'rq=' . $nf ), 'the portal offers the request forms (not the ordinary forms)' );
t( count( mcupr_rq_forms_for_students() ) >= 1, 'forms for students are listed' );
update_post_meta( $rf, '_mcupr_fb_opts', array_merge( mcupr_fb_opts( $rf ), array( 'to' => '2000-01-01' ) ) );
t( ! in_array( $rf, wp_list_pluck( mcupr_rq_forms_for_students(), 'ID' ), true ), 'a closed request form is not offered' );
update_post_meta( $rf, '_mcupr_fb_opts', array_merge( mcupr_fb_opts( $rf ), array( 'to' => '' ) ) );

// ---- the CSV
$csv = mcupr_fb_csv( $rf );
t( false !== strpos( $csv, 'Number' ) && false !== strpos( $csv, $code ) && false !== strpos( $csv, 'Person who sees to it' ), 'the CSV of a request form has the number, status and owner columns' );
t( false === strpos( mcupr_fb_csv( $nf ), 'Number' ), 'the CSV of an ordinary form has not' );

// ---- the pages
t( false !== strpos( mcupr_rq_track_url(), 'request' ), 'the address to follow a request' );
t( mcupr_rq_track_url() === mcupr_plain_target( 'request' ), 'a link to /request/ is understood by the plain-permalink translator' );
$strings = file_get_contents( get_theme_file_path( 'inc/strings.php' ) );
preg_match_all( "/'(rq_[a-z0-9_]+)' => array\\( ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\"), ('[^']*'|\"[^\"]*\") \\)/", $strings, $mm );
t( count( $mm[1] ) >= 30, 'the request words have all four languages (' . count( $mm[1] ) . ' keys)' );
foreach ( array( 'new', 'review', 'need', 'approved', 'rejected', 'done' ) as $k ) { t( in_array( 'rq_st_' . $k, $mm[1], true ), 'status word: ' . $k ); }

wp_set_current_user( 1 );
cleanup();
wp_delete_user( $other ); wp_delete_user( $reg ); wp_delete_user( $edi ); wp_delete_user( $su );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
