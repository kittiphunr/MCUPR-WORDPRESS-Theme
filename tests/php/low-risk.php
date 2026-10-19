<?php
/** Low-risk hardening (v1.54.4): book files only from inside uploads/, admin notices from codes, import files of their owner, form answers bound to the browser, guests cannot take a staff name, the ID number masked.   php php/low-risk.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// 1. books: a file in a sibling folder (uploads-old/) is not "inside uploads/"
$up = wp_get_upload_dir(); $base = realpath( $up['basedir'] );
t( mcupr_path_inside( $base . '/2030/01/a.pdf', $base ) && ! mcupr_path_inside( $base . '-old/a.pdf', $base ) && ! mcupr_path_inside( $base, $base ) && ! mcupr_path_inside( false, $base ), 'books: only a path under uploads/ passes, uploads-old/ does not' );
t( mcupr_path_inside( 'C:\\site\\uploads\\a.pdf', 'C:\\site\\uploads' ) && ! mcupr_path_inside( 'C:\\site\\uploads-old\\a.pdf', 'C:\\site\\uploads' ), 'books: the same with Windows separators' );

// 2. admin notices: ?msg= is a code with a fixed sentence; any other text in the address shows nothing
$fake = 'Your password expired, call 081-234-5678';
t( '' === mcupr_ics_notice_text( array( 'msg' => $fake ) ) && '' === mcupr_ics_notice_text( array() ) && '' === mcupr_ins_notice_text( sanitize_key( $fake ) ), 'notices: a sentence typed into ?msg= is not shown (calendar, redirects)' );
t( false !== strpos( mcupr_ics_notice_text( array( 'msg' => 'done', 'n1' => '7', 'n2' => '2', 'n3' => '0', 'n4' => 'x<b>' ) ), '7' ) && '' !== mcupr_ics_notice_text( array( 'msg' => 'url' ) ) && '' !== mcupr_ins_notice_text( 'added' ) && '' !== mcupr_ins_notice_text( 'loop' ), 'notices: the known codes still give their messages, numbers only as numbers' );
$old_feeds = get_option( 'mcupr_ics_feeds' );
update_option( 'mcupr_ics_feeds', array( 'lrfeed1' => array( 'name' => 'T', 'url' => 'https://example.org/a.ics', 'every' => 'daily', 'remove' => 0, 'status' => 'publish', 'last' => time(), 'msg' => 'error: timed out' ) ), false );
t( false !== strpos( mcupr_ics_notice_text( array( 'msg' => 'fail', 'feed' => 'lrfeed1' ) ), 'timed out' ) && '' === mcupr_ics_notice_text( array( 'msg' => 'fail', 'feed' => 'nope' ) ), 'notices: a failed sync shows the error the site stored for that feed, an unknown feed nothing' );
false === $old_feeds ? delete_option( 'mcupr_ics_feeds' ) : update_option( 'mcupr_ics_feeds', $old_feeds, false );
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $adm[0]->ID );
$_GET = array( 'page' => 'mcupr-student-import', 'err' => 'call-0812345678-now' );
ob_start(); mcupr_si_page(); $pg = ob_get_clean();
$_GET = array( 'page' => 'mcupr-student-import', 'err' => 'gone' );
ob_start(); mcupr_si_page(); $pg2 = ob_get_clean();
$_GET = array();
t( false === strpos( $pg, 'call-0812345678-now' ) && false === strpos( $pg, 'notice-error' ) && false !== strpos( $pg2, 'notice-error' ), 'notices: the student import shows only its known error codes' );

// 3. student import: the saved upload is only for the person who uploaded it; old ones go also by the daily cron
$imp = mcupr_si_parse( "รหัสนิสิต,ชื่อ-สกุล\n77001,นิสิตลับเฉพาะ\n" );
$own = wp_insert_user( array( 'user_login' => 'lr_owner', 'user_pass' => wp_generate_password(), 'user_email' => 'lr_owner@example.org', 'role' => 'editor' ) );
$oth = wp_insert_user( array( 'user_login' => 'lr_other', 'user_pass' => wp_generate_password(), 'user_email' => 'lr_other@example.org', 'role' => 'editor' ) );
foreach ( array( $own, $oth ) as $u ) { get_userdata( $u )->add_cap( MCUPR_STU_CAP ); }
$tk = bin2hex( random_bytes( 16 ) );
mcupr_si_save( $tk, array( 'cols' => $imp['cols'], 'rows' => $imp['rows'], 'unknown' => array(), 'central' => false, 'mode' => 'add_update', 'accounts' => false, 'user' => $own, 'sample' => false ) );
wp_set_current_user( $oth ); clean_user_cache( $oth );
ob_start(); mcupr_si_preview( $tk ); $pv_other = ob_get_clean();
$other_gets = mcupr_si_load_own( $tk );
wp_set_current_user( $own );
ob_start(); mcupr_si_preview( $tk ); $pv_own = ob_get_clean();
wp_set_current_user( $adm[0]->ID );
t( null === $other_gets && false === strpos( $pv_other, 'นิสิตลับเฉพาะ' ) && false !== strpos( $pv_own, 'นิสิตลับเฉพาะ' ) && null !== mcupr_si_load_own( $tk ), 'import: another staff member cannot open someone else\'s upload by its token; the owner and an administrator can' );
$tk_old = bin2hex( random_bytes( 16 ) );
mcupr_si_save( $tk_old, array( 'rows' => array(), 'user' => $own ) );
touch( mcupr_si_file( $tk_old ), time() - 3 * HOUR_IN_SECONDS );
clearstatcache();
do_action( 'mcupr_si_cleanup_cron' );
clearstatcache();
t( ! file_exists( mcupr_si_file( $tk_old ) ) && file_exists( mcupr_si_file( $tk ) ) && false !== wp_next_scheduled( 'mcupr_si_cleanup_cron' ), 'import: the daily cron deletes uploads older than 2 hours and keeps a fresh one' );
t( file_exists( mcupr_si_dir() . '/.htaccess' ) && file_exists( mcupr_si_dir() . '/index.php' ), 'import: the folder has its deny-all .htaccess and index.php' );
wp_delete_file( mcupr_si_file( $tk ) );
wp_delete_user( $own ); wp_delete_user( $oth );

// 4. form builder: the answers kept after a mistake come back only to the browser that typed them
$lf = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'LR form' ) );
update_post_meta( $lf, '_mcupr_fb_fields', mcupr_fb_clean_fields( array( array( 'id' => 'mail', 'type' => 'email', 'label' => 'Mail', 'required' => 1 ), array( 'id' => 'tel', 'type' => 'text', 'label' => 'Phone' ) ) ) );
$lk = 'lrkey123456x';
set_transient( 'mcupr_fbv_' . $lk, array( 'v' => array( 'tel' => '0899999999' ), 'e' => array( 'mail' => 'required' ), 'h' => hash( 'sha256', 'right-secret' ) ), 600 );
wp_set_current_user( 0 );
$_GET = array( 'fb' => 'err', 'fbid' => (string) $lf, 'fbk' => $lk );
unset( $_COOKIE['mcupr_fbk'] ); $h_none = mcupr_fb_html( $lf );
$_COOKIE['mcupr_fbk'] = 'wrong-secret'; $h_wrong = mcupr_fb_html( $lf );
$_COOKIE['mcupr_fbk'] = 'right-secret'; $h_mine = mcupr_fb_html( $lf );
unset( $_COOKIE['mcupr_fbk'] ); $_GET = array();
t( false === strpos( $h_none, '0899999999' ) && false === strpos( $h_wrong, '0899999999' ) && false !== strpos( $h_none, 'role="alert"' ), 'form: with only the ?fbk= address (no or another cookie) the errors are listed but the typed phone number is not shown' );
t( false !== strpos( $h_mine, 'value="0899999999"' ), 'form: the browser with the matching cookie gets its answers back' );
set_transient( 'mcupr_fbv_' . $lk, array( 'v' => array( 'tel' => '0899999999' ), 'e' => array() ), 600 ); // kept before this change: no hash
$_GET = array( 'fb' => 'err', 'fbid' => (string) $lf, 'fbk' => $lk ); $_COOKIE['mcupr_fbk'] = 'right-secret';
$h_old = mcupr_fb_html( $lf );
unset( $_COOKIE['mcupr_fbk'] ); $_GET = array();
t( false === strpos( $h_old, '0899999999' ), 'form: answers kept without a hash are not shown' );
delete_transient( 'mcupr_fbv_' . $lk );
wp_delete_post( $lf, true );
wp_set_current_user( $adm[0]->ID );

// 5. forum: a visitor cannot post under the name of a member of the staff; guest posts are marked
$fo_old = get_option( 'mcupr_forum' );
$fo = mcupr_forum_opts(); $fo['enabled'] = 1; update_option( 'mcupr_forum', $fo );
$boss = wp_insert_user( array( 'user_login' => 'lr_rector', 'user_pass' => wp_generate_password(), 'user_email' => 'lr_rector@example.org', 'role' => 'editor', 'display_name' => 'Phra Rector  Example' ) );
$brd  = get_term_by( 'slug', 'general', 'mcupr_board' );
$post = static function ( $name ) use ( $brd ) {
	return mcupr_forum_submit( 'topic', array( 'tk' => mcupr_form_token( time() - 10 ), 'website' => '', 'consent' => '1', 'name' => $name, 'email' => 'g@example.org', 'title' => 'คำถามทดสอบชื่อ', 'message' => 'ข้อความทดสอบยาวพอสมควร ' . $name, 'board' => $brd ? $brd->term_id : 0 ) );
};
wp_set_current_user( 0 );
$r1 = $post( 'phra   rector example' );
$r2 = $post( 'LR_RECTOR' );
$r3 = $post( 'ผู้มาเยือนทั่วไป' );
t( ! $r1['ok'] && isset( $r1['errors']['staffname'] ) && ! $r2['ok'] && isset( $r2['errors']['staffname'] ), 'forum: a visitor typing the display name or login of a staff member (any case, extra spaces) is refused' );
t( $r3['ok'] || ! isset( $r3['errors']['staffname'] ), 'forum: an ordinary name is not refused for that reason' );
$_GET = array( 'fm' => 'err', 'fme' => 'staffname' ); list( $nt ) = mcupr_forum_notices(); $_GET = array();
t( false !== strpos( $nt, esc_html( mcupr_t( 'fm_err_staff_name' ) ) ), 'forum: the refusal is told in words on the page' );
if ( $r3['ok'] ) {
	$cid = wp_insert_comment( array( 'comment_post_ID' => $r3['id'], 'comment_author' => 'ผู้ตอบทั่วไป', 'comment_author_email' => 'g2@example.org', 'comment_content' => 'คำตอบทดสอบ', 'comment_approved' => 1, 'user_id' => 0 ) );
	wp_update_post( array( 'ID' => $r3['id'], 'post_status' => 'publish' ) );
	t( false !== strpos( mcupr_forum_replies_html( $r3['id'] ), 'is-guest' ), 'forum: a reply by a visitor carries the guest mark' );
	wp_delete_post( $r3['id'], true );
}
wp_set_current_user( $adm[0]->ID );
wp_delete_user( $boss );
false === $fo_old ? delete_option( 'mcupr_forum' ) : update_option( 'mcupr_forum', $fo_old );

//@@NEXT@@
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
