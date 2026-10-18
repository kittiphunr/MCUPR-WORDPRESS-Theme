<?php
/** Hardening: networks for rate limits, the REST list of student-only book files, Google sign-in domain, application passwords, forum reports, form copies.   php php/security.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// 1. one network = one IPv6 /64
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa::1'; $a = mcupr_client_net();
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:bbbb::9'; $b = mcupr_client_net();
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:3::1'; $c = mcupr_client_net();
$_SERVER['REMOTE_ADDR'] = '203.0.113.7'; $d = mcupr_client_net();
t( $a === $b && $a !== $c && '203.0.113.7' === $d, 'network: addresses of one IPv6 /64 are one visitor, another /64 is another, IPv4 is unchanged' );
$_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:aaaa::1'; $k1 = mcupr_forum_visitor_key( 'x' ); $_SERVER['REMOTE_ADDR'] = '2001:db8:1:2:cccc::5'; $k2 = mcupr_forum_visitor_key( 'x' );
t( $k1 === $k2, 'network: the forum limit key is the same inside one /64' );

// 2. Google sign-in needs the hosted-domain claim
$old_sso = get_option( 'mcupr_sso' );
update_option( 'mcupr_sso', array( 'on' => 1, 'id' => 'client-123', 'secret' => 'sec', 'domain' => 'mcu.ac.th' ) );
$good = array( 'iss' => 'https://accounts.google.com', 'aud' => 'client-123', 'exp' => time() + 600, 'nonce' => 'n1', 'sub' => 'g-1', 'email' => '6401001@mcu.ac.th', 'email_verified' => true );
t( '' === mcupr_sso_check_claims( array_merge( $good, array( 'hd' => 'mcu.ac.th' ) ), 'n1' ) && 'domain' === mcupr_sso_check_claims( $good, 'n1' ), 'google: a token without the hosted-domain claim is refused, with it accepted' );
false === $old_sso ? delete_option( 'mcupr_sso' ) : update_option( 'mcupr_sso', $old_sso );

// 3. application passwords are not for students
$stu = wp_insert_user( array( 'user_login' => 'sec_stu', 'user_pass' => wp_generate_password(), 'user_email' => 'sec_stu@example.org', 'role' => MCUPR_STU_USER_ROLE ) );
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
add_filter( 'wp_is_application_passwords_available', '__return_true' );
t( ! wp_is_application_passwords_available_for_user( get_userdata( $stu ) ) && wp_is_application_passwords_available_for_user( $adm[0] ), 'app passwords: none for a student account, still there for staff' );

// 4. REST: files of a student-only book are not listed to a visitor
$book = wp_insert_post( array( 'post_type' => MCUPR_BOOK, 'post_status' => 'publish', 'post_title' => 'Sec book' ) );
$att  = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'secbook', 'post_status' => 'inherit' ), 'secbook.pdf', 0 );
update_post_meta( $book, '_mcupr_bk_pdf', $att );
update_post_meta( $book, MCUPR_AUD, 'students' ); mcupr_aud_reset();
$ids = static function () { $r = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media' ) ); return wp_list_pluck( (array) $r->get_data(), 'id' ); };
$src = static function ( $id ) { $r = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $id ) ); $d = $r->get_data(); return isset( $d['source_url'] ) ? $d['source_url'] : null; };
wp_set_current_user( 0 );
t( ! in_array( $att, $ids(), true ), 'rest: a visitor does not get the file of a student-only book in the media list' );
t( '' === $src( $att ), 'rest: and no address when asking for that one file' );
wp_set_current_user( $adm[0]->ID );
t( in_array( $att, $ids(), true ) && '' !== $src( $att ), 'rest: staff still see it' );
delete_post_meta( $book, MCUPR_AUD ); mcupr_aud_reset();
wp_set_current_user( 0 );
t( in_array( $att, $ids(), true ), 'rest: a book that is for everyone keeps its files in the list' );

// 5. forum: three anonymous reports do not hide a post of the staff
$ft = wp_insert_post( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'post_title' => 'Official', 'post_author' => $adm[0]->ID ) );
$fo = mcupr_forum_opts(); $fo['enabled'] = 1; $fo['reports'] = 1; update_option( 'mcupr_forum', $fo );
foreach ( array( 1, 2, 3, 4 ) as $i ) { $_SERVER['REMOTE_ADDR'] = '198.51.100.' . $i; mcupr_forum_report( 'topic', $ft ); }
t( 'publish' === get_post_status( $ft ), 'forum: a topic written by staff stays up after anonymous reports' );

// 6. forms: the copy goes to the sender's own address with the answers, to anyone else only a thanks
$form = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'Sec form' ) );
$mails = array();
add_filter( 'pre_wp_mail', static function ( $n, $atts ) use ( &$mails ) { $mails[] = $atts; return true; }, 10, 2 );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'copy' => '1', 'success' => 'THANKS' ) ) );
update_post_meta( $form, '_mcupr_fb_fields', mcupr_fb_clean_fields( array( array( 'id' => 'mail', 'type' => 'email', 'label' => 'Mail', 'required' => 1 ), array( 'id' => 'msg', 'type' => 'textarea', 'label' => 'Text' ) ) ) );
$now = time(); $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
wp_set_current_user( 0 );
$r = mcupr_fb_submit( $form, array( 'tk' => mcupr_form_token( $now - 10 ), 'website' => '', 'f' => array( 'mail' => 'victim@example.org', 'msg' => 'SECRET-TEXT' ) ), $now );
$to_victim = null; foreach ( $mails as $m ) { if ( 'victim@example.org' === ( is_array( $m['to'] ) ? $m['to'][0] : $m['to'] ) ) { $to_victim = $m; } }
t( $r['ok'] && $to_victim && false !== strpos( $to_victim['message'], 'THANKS' ) && false === strpos( $to_victim['message'], 'SECRET-TEXT' ), 'form copy: a guest typing someone else\'s address sends only the thanks, never the typed text' );

foreach ( array( $att, $book, $ft, $form ) as $id ) { wp_delete_post( $id, true ); }
wp_delete_user( $stu );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
