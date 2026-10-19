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

//@@NEXT@@
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
