<?php
/**
 * The data hardening: who sees a request and the papers sent with it, what REST gives out about a person, the sitemap,
 * the staff export button, the guard on the Elementor form.   php php/data-hardening.php [site]
 */
define( 'WP_ADMIN', true ); // the list of answers is filtered only on an admin screen
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

// ---- the people: an editor who may edit pages, and the same person ticked as a request handler
$fresh_user = static function ( $login, $role ) {
	$old = get_user_by( 'login', $login );
	if ( $old ) {
		wp_delete_user( $old->ID );
	}
	return (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.org', 'role' => $role ) );
};
$ed = $fresh_user( 'dh_editor', 'editor' );
$tk = $fresh_user( 'dh_ticked', 'editor' );
update_user_meta( $tk, 'mcupr_requests', 1 );
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );

// ---- a request form and a plain form, one answer each
$rform = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'DH request form' ) );
update_post_meta( $rform, '_mcupr_rq_opts', array( 'on' => 1, 'who' => 'all', 'owner' => 0 ) );
$pform = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'DH plain form' ) );
$rq = wp_insert_post( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'post_title' => 'DH request answer' ) );
update_post_meta( $rq, '_mcupr_fe_form', $rform );
update_post_meta( $rq, '_mcupr_rq_code', 'REQ-2026-99001' );
update_post_meta( $rq, '_mcupr_rq_uid', 0 );
$pa = wp_insert_post( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'post_title' => 'DH plain answer' ) );
update_post_meta( $pa, '_mcupr_fe_form', $pform );

// 1. the right to handle requests is given on purpose, not by the right to edit pages
wp_set_current_user( $ed );
$ed_rq = current_user_can( MCUPR_RQ_CAP );
wp_set_current_user( $tk );
$tk_rq = current_user_can( MCUPR_RQ_CAP );
wp_set_current_user( $adm[0]->ID );
$ad_rq = current_user_can( MCUPR_RQ_CAP );
t( ! $ed_rq && $tk_rq && $ad_rq, 'requests: editing pages is not enough; the tick on the profile and being an administrator are' );

// 2. the answers screen still opens for somebody who only builds forms
wp_set_current_user( $ed );
t( current_user_can( MCUPR_FE_CAP ) && current_user_can( 'edit_post', $pa ), 'answers: a form builder still opens the list and a plain answer' );

// 3. a request answer is closed to them — reading, editing and deleting alike
wp_set_current_user( $ed );
$ed_can = array( current_user_can( 'read_post', $rq ), current_user_can( 'edit_post', $rq ), current_user_can( 'delete_post', $rq ), mcupr_rq_can_see( $rq ) );
wp_set_current_user( $tk );
$tk_can = array( current_user_can( 'read_post', $rq ), current_user_can( 'edit_post', $rq ), current_user_can( 'delete_post', $rq ), mcupr_rq_can_see( $rq ) );
t( array( false, false, false, false ) === $ed_can, 'request answer: a form builder may not read, edit, delete it, nor open its files' );
t( array( true, true, true, true ) === $tk_can, 'request answer: the person ticked for requests may do all four' );

// 4. the list of answers keeps the requests away from a form builder, and shows them to a handler
$list = static function ( $uid, $args = array() ) {
	wp_set_current_user( $uid );
	$q = new WP_Query();
	$old = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;
	$GLOBALS['wp_the_query'] = $q; // is_main_query() compares against this one
	$ids = $q->query( array_merge( array( 'post_type' => 'mcupr_fe', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true ), $args ) );
	$GLOBALS['wp_the_query'] = $old;
	return array_map( 'intval', (array) $ids );
};
$ed_list = $list( $ed );
$tk_list = $list( $tk );
wp_set_current_user( $adm[0]->ID );
t( ! in_array( $rq, $ed_list, true ) && in_array( $pa, $ed_list, true ), 'answers list: a form builder sees the plain answer and not the request' );
t( in_array( $rq, $tk_list, true ), 'answers list: a request handler sees the request' );

// 5. REST: a person who asked for no profile page is not given out, and a hidden biography is taken out
$opts = get_option( 'mcupr_staff' );
$o = mcupr_staff_opts();
$o['profiles'] = 1;
$o['sections']['bio'] = 1;
update_option( 'mcupr_staff', $o );
mcupr_people_register();
$p_open = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'post_title' => 'DH open person', 'post_content' => 'BIOGRAPHY-OPEN' ) );
update_post_meta( $p_open, '_mcupr_person_hide', array() );
$p_nobio = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'post_title' => 'DH quiet person', 'post_content' => 'BIOGRAPHY-HIDDEN' ) );
update_post_meta( $p_nobio, '_mcupr_person_hide', array( 'bio' ) );
$p_off = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'post_title' => 'DH no page', 'post_content' => 'BIOGRAPHY-OFF' ) );
update_post_meta( $p_off, '_mcupr_person_profile', 'off' );
$rest = static function ( $id ) {
	$r = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/mcupr_person/' . $id ) );
	return array( $r->get_status(), is_array( $r->get_data() ) && isset( $r->get_data()['content']['rendered'] ) ? (string) $r->get_data()['content']['rendered'] : '' );
};
wp_set_current_user( 0 );
list( $s_open, $c_open )   = $rest( $p_open );
list( $s_nobio, $c_nobio ) = $rest( $p_nobio );
list( $s_off )             = $rest( $p_off );
t( 200 === $s_open && false !== strpos( $c_open, 'BIOGRAPHY-OPEN' ), 'rest person: a profile that is open is given out with its biography' );
t( 200 === $s_nobio && false === strpos( $c_nobio, 'BIOGRAPHY-HIDDEN' ), 'rest person: a biography the person hid is not in the answer' );
t( 404 === $s_off, 'rest person: somebody who asked for no profile page is not given out at all' );
wp_set_current_user( $adm[0]->ID );
list( $s_off_adm, $c_off_adm ) = $rest( $p_off );
t( 200 === $s_off_adm && false !== strpos( $c_off_adm, 'BIOGRAPHY-OFF' ), 'rest person: somebody who may edit the person still sees everything' );

// 6. with the profile pages switched off nothing of a person is in REST at all
$o['profiles'] = 0;
update_option( 'mcupr_staff', $o );
mcupr_people_register();
$type = get_post_type_object( 'mcupr_person' );
$tax  = get_taxonomy( 'mcupr_person_group' );
t( empty( $type->show_in_rest ) && empty( $tax->show_in_rest ), 'rest person: the switch for the profile pages also closes the REST route and the groups' );
$o['profiles'] = 1;
update_option( 'mcupr_staff', $o );
mcupr_people_register();

// 7. the sitemap leaves out somebody who asked for no profile page
$args = apply_filters( 'wp_sitemaps_posts_query_args', array( 'post_type' => 'mcupr_person' ), 'mcupr_person' );
$args = array_merge( $args, array( 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 50, 'no_found_rows' => true ) );
$in_map = array_map( 'intval', get_posts( $args ) );
t( in_array( $p_open, $in_map, true ) && ! in_array( $p_off, $in_map, true ), 'sitemap: a person without a profile page is not announced' );

// 8. the staff export button is only shown to an administrator
$card = static function ( $uid ) {
	wp_set_current_user( $uid );
	ob_start();
	mcupr_import_card();
	return (string) ob_get_clean();
};
t( false === strpos( $card( $ed ), 'mcupr_staff_export' ) && false !== strpos( $card( $adm[0]->ID ), 'mcupr_staff_export' ), 'staff export: the button for the whole directory is shown to an administrator only' );

// 9. the Elementor form carries the signed moment, and the moment is checked
$form_html = mcupr_el_w_form( array( 'form_fields' => array( array( 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Name' ) ) ), array( 'id' => 'abc123' ), 0 );
t( false !== strpos( $form_html, 'name="mcupr_tk"' ) && false !== strpos( $form_html, 'name="mcupr_hp"' ), 'elementor form: the printed form carries the trap field and the signed moment' );
preg_match( '/name="mcupr_tk" value="([^"]+)"/', $form_html, $m );
$tok = isset( $m[1] ) ? $m[1] : '';
$ts  = (int) strtok( $tok, '.' );
t( '' !== $tok && hash_equals( mcupr_form_token( $ts ), $tok ) && ! hash_equals( mcupr_form_token( $ts ), $ts . '.0000000000000000000' ), 'elementor form: the moment is signed, a made-up one does not pass' );
$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
$allowed = 0;
for ( $i = 0; $i < 12; $i++ ) {
	if ( mcupr_throttle( 'elform|' . mcupr_client_net(), 8, HOUR_IN_SECONDS ) ) {
		$allowed++;
	}
}
t( 8 === $allowed, 'elementor form: one network may send eight forms an hour, the ninth is refused' );
delete_transient( 'mcupr_thr_' . md5( 'elform|' . mcupr_client_net() ) );

// 10. the CSV of a request form's answers needs the right to handle requests
t( mcupr_rq_is_form( $rform ) && ! mcupr_rq_is_form( $pform ), 'forms: the request form is marked as one, the plain form is not' );

foreach ( array( $rq, $pa, $rform, $pform, $p_open, $p_nobio, $p_off ) as $id ) {
	wp_delete_post( $id, true );
}
wp_set_current_user( $adm[0]->ID );
wp_delete_user( $ed );
wp_delete_user( $tk );
false === $opts ? delete_option( 'mcupr_staff' ) : update_option( 'mcupr_staff', $opts );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
