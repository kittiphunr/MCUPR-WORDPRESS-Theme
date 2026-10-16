<?php
/** Student data care: activity log, e-mail test / last problem / link without e-mail, the check of the pretty links.   php php/student-care.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function evs( $action = '' ) { return get_posts( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => -1, 'title' => $action ? $action : '', 'orderby' => 'ID', 'order' => 'ASC' ) ); }
function wipe_all() { foreach ( get_posts( array( 'post_type' => array( MCUPR_STU, MCUPR_AUDIT ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); } }
wp_set_current_user( 1 );
wipe_all();
delete_option( 'mcupr_stu_mail_last' );

// ---- the log
t( post_type_exists( MCUPR_AUDIT ) && ! get_post_type_object( MCUPR_AUDIT )->public && ! get_post_type_object( MCUPR_AUDIT )->show_in_rest, 'log: a hidden type (not public, not in the REST API)' );
t( (bool) wp_next_scheduled( 'mcupr_audit_purge' ), 'log: the daily clean-up is scheduled' );
$id = mcupr_audit( 'export', array( 'n' => 12, 'note' => 'x<b>y</b>' ) );
$d  = json_decode( (string) get_post_meta( $id, '_mcupr_a', true ), true );
t( $id && 12 === $d['n'] && 'xy' === $d['note'] && 'admin' === $d['by'] || ( $d['by'] ?? '' ) !== '', 'log: an event keeps the actor, the count and a cleaned note' );
t( ! isset( $d['ip'] ) && false === strpos( (string) get_post_meta( $id, '_mcupr_a', true ), $_SERVER['REMOTE_ADDR'] ?? 'no-ip-set' ), 'log: no IP address is stored' );
t( isset( mcupr_audit_actions()['import'], mcupr_audit_actions()['delete'], mcupr_audit_actions()['link'] ), 'log: the actions have names' );

// ---- many events of the same kind stay fast (WordPress looks for a free address one try at a time, unless it is unique at once)
$t0 = microtime( true );
for ( $i = 0; $i < 150; $i++ ) { mcupr_audit( 'errors', array( 'n' => $i ) ); }
$el = microtime( true ) - $t0;
t( $el < 25, 'log: 150 events of one kind are written in ' . round( $el, 1 ) . ' s (not one slow try per earlier event)' );
$names = $GLOBALS['wpdb']->get_col( "SELECT post_name FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'mcupr_audit'" );
t( count( $names ) === count( array_unique( $names ) ) && 0 === preg_match( '/^(errors|delete|export)(-\d+)?$/', (string) $names[ count( $names ) - 1 ] ), 'log: every event has its own address, no errors-2, errors-3 …' );

// ---- retention
update_option( 'mcupr_stu_opts', array( 'audit_days' => 5 ) );
t( 30 === mcupr_audit_days(), 'retention: at least 30 days' );
update_option( 'mcupr_stu_opts', array( 'audit_days' => 99999 ) );
t( 3650 === mcupr_audit_days(), 'retention: at most 3650 days' );
update_option( 'mcupr_stu_opts', array( 'audit_days' => 30 ) );
$old = mcupr_audit( 'import', array( 'n' => 1 ) );
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ), array( 'ID' => $old ) );
clean_post_cache( $old );
$n = mcupr_audit_purge();
t( 1 === $n && ! get_post( $old ) && get_post( $id ), 'retention: an entry older than the limit is deleted, a new one stays' );
delete_option( 'mcupr_stu_opts' );

// ---- the events written by the student code
$row = array( 'sid' => '8801', 'name' => 'ทดสอบ บันทึก', 'name_en' => '', 'level' => 'bachelor', 'year' => '1', 'entry' => '2568', 'faculty' => '', 'major' => '', 'status' => 'studying', 'email' => 'care8801@example.org', 'phone' => '', 'note' => '' );
list( $r, $sid ) = mcupr_student_upsert( $row, 'add_update' );
$out = apply_filters( 'handle_bulk_actions-edit-mcupr_student', 'edit.php', 'mcs_set_leave', array( $sid ) );
$b   = evs( 'bulk' );
t( 1 === count( $b ) && 1 === json_decode( get_post_meta( $b[0]->ID, '_mcupr_a', true ), true )['n'], 'events: a bulk change is one entry with the count' );
wp_delete_post( $sid, true );
$dl = evs( 'delete' );
t( 1 === count( $dl ) && '8801' === json_decode( get_post_meta( $dl[0]->ID, '_mcupr_a', true ), true )['sid'] && false === strpos( (string) get_post_meta( $dl[0]->ID, '_mcupr_a', true ), 'ทดสอบ' ), 'events: a deleted record is logged with its student ID, not its name' );

// ---- the e-mail
$GLOBALS['mode'] = 'fail';
add_filter( 'pre_wp_mail', function () { if ( 'fail' === $GLOBALS['mode'] ) { return false; } if ( 'smtp' === $GLOBALS['mode'] ) { do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP connect failed' ) ); return false; } return true; }, 10, 0 );
t( false === mcupr_stu_mail( 'a@example.org', 's', 'b' ) && 'wp_mail() returned false' === mcupr_stu_mail_problem()['why'], 'mail: a failure is remembered (with a generic reason)' );
$GLOBALS['mode'] = 'smtp';
mcupr_stu_mail( 'a@example.org', 's', 'b' );
t( 'SMTP connect failed' === mcupr_stu_mail_problem()['why'] && false !== strpos( mcupr_stu_mail_notice(), 'SMTP connect failed' ), 'mail: the reason the mail system gave is kept and shown in the red note' );
t( false === strpos( (string) wp_json_encode( mcupr_stu_mail_problem() ), 'example.org' ), 'mail: the problem record holds no e-mail address' );
$GLOBALS['mode'] = 'ok';
t( true === mcupr_stu_mail( 'a@example.org', 's', 'b' ) && ! mcupr_stu_mail_problem() && '' === mcupr_stu_mail_notice(), 'mail: after a good send the note is gone' );
list( , $sid2 ) = mcupr_student_upsert( array_merge( $row, array( 'sid' => '8802', 'email' => 'care8802@example.org' ) ), 'add_update' );
t( ! is_wp_error( mcupr_student_make_account( $sid2 ) ), 'mail: an account for the test student' );
$GLOBALS['mode'] = 'fail';
$r = mcupr_student_send_activation( $sid2 );
t( is_wp_error( $r ) && 'mail' === $r->get_error_code() && mcupr_stu_mail_problem(), 'mail: the activation e-mail reports and remembers a failure' );
$GLOBALS['mode'] = 'ok';
t( true === mcupr_student_send_activation( $sid2 ) && ! mcupr_stu_mail_problem(), 'mail: and a good send clears it' );
mcupr_student_drop_account( $sid2 );
wp_delete_post( $sid2, true );

// ---- are the pretty links there?
add_filter( 'pre_option_permalink_structure', $plain = function () { return ''; } );
t( 'plain' === mcupr_rewrite_status(), 'links: plain permalinks are recognised' );
remove_filter( 'pre_option_permalink_structure', $plain );
add_filter( 'pre_option_permalink_structure', $pretty = function () { return '/%postname%/'; } );
add_filter( 'pre_option_rewrite_rules', $none = function () { return array( 'x/?$' => 'index.php' ); } );
t( 'missing' === mcupr_rewrite_status(), 'links: pretty permalinks without the theme\'s rules are reported' );
remove_filter( 'pre_option_rewrite_rules', $none );
add_filter( 'pre_option_rewrite_rules', $has = function () { return array( '^student/?$' => 'index.php?mcupr_student=1' ); } );
t( 'ok' === mcupr_rewrite_status(), 'links: with the rule saved it is fine' );
remove_filter( 'pre_option_rewrite_rules', $has );
remove_filter( 'pre_option_permalink_structure', $pretty );

// ---- the menu
$menu = file_get_contents( get_theme_file_path( 'inc/admin-menu.php' ) );
t( false !== strpos( $menu, "'mcupr-student-log'" ), 'menu: the log page is listed with the student pages' );
wipe_all();
delete_option( 'mcupr_stu_mail_last' );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
