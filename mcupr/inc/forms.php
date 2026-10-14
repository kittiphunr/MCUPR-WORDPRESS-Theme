<?php
/**
 * Contact and complaint forms (no plugin): [mcupr_form type="contact"] and [mcupr_form type="complaint"].
 * Works without JavaScript (posts to admin-post.php). Protection: nonce, hidden trap field, minimum fill time,
 * signed time token, per-visitor rate limit, consent tick (PDPA). Every message is saved in the admin
 * (menu "Form messages") with a reference number and e-mailed to the address set in Customizer.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_msg_register() {
	register_post_type(
		'mcupr_msg',
		array(
			'labels'       => array(
				'name'      => __( 'Form messages', 'mcupr' ),
				'menu_name' => __( 'Form messages', 'mcupr' ),
				'all_items' => __( 'All messages', 'mcupr' ),
				'not_found' => __( 'No messages yet', 'mcupr' ),
				'edit_item' => __( 'Message', 'mcupr' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-email-alt',
			'menu_position' => 10,
			'supports'     => array( 'title' ),
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
		)
	);
}
add_action( 'init', 'mcupr_msg_register' );

function mcupr_form_kinds( $type ) {
	if ( 'complaint' === $type ) {
		return array( 'service' => mcupr_t( 'kind_service' ), 'staff' => mcupr_t( 'kind_staff' ), 'corrupt' => mcupr_t( 'kind_corrupt' ), 'suggest' => mcupr_t( 'kind_suggest' ) );
	}
	return array( 'contact' => mcupr_t( 'kind_contact' ), 'suggest' => mcupr_t( 'kind_suggest' ) );
}

function mcupr_form_token( $ts ) {
	return $ts . '.' . substr( hash_hmac( 'sha256', 'mcupr-form|' . $ts, wp_salt( 'auth' ) ), 0, 20 );
}

function mcupr_form_visitor_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return 'mcupr_f_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 ); // only a hash is kept, for one hour
}

/**
 * Validate and store a submission. Returns array( 'ok' => bool, 'errors' => array( field => message ), 'ref' => string ).
 * $data: the POSTed array (unslashed).
 */
function mcupr_form_process( $data, $now = 0 ) {
	$now    = $now ? $now : time();
	$errors = array();
	$type   = ( isset( $data['type'] ) && 'complaint' === $data['type'] ) ? 'complaint' : 'contact';
	$g      = static function ( $k ) use ( $data ) {
		return isset( $data[ $k ] ) && is_string( $data[ $k ] ) ? $data[ $k ] : '';
	};
	// ---- robots: trap field and a signed time token; the 3-second / 2-hour window is judged after the fields
	$tok = explode( '.', $g( 'tk' ) );
	$ts  = isset( $tok[0] ) ? (int) $tok[0] : 0;
	if ( '' !== $g( 'website' ) || ! hash_equals( mcupr_form_token( $ts ), $g( 'tk' ) ) ) {
		return array( 'ok' => false, 'errors' => array( 'spam' => mcupr_t( 'form_err_spam' ) ), 'ref' => '' );
	}
	$too_fast_or_old = ( $now - $ts < 3 || $now - $ts > 2 * HOUR_IN_SECONDS );
	$key = mcupr_form_visitor_key();
	$name  = sanitize_text_field( $g( 'name' ) );
	$email = sanitize_email( $g( 'email' ) );
	$phone = sanitize_text_field( $g( 'phone' ) );
	$subj  = sanitize_text_field( $g( 'subject' ) );
	$kind  = sanitize_key( $g( 'kind' ) );
	$msg   = sanitize_textarea_field( $g( 'message' ) );
	$kinds = mcupr_form_kinds( $type );
	if ( '' === $name ) {
		$errors['name'] = mcupr_t( 'form_err_name' );
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = mcupr_t( 'form_err_email' );
	}
	if ( mb_strlen( $msg ) < 10 ) {
		$errors['message'] = mcupr_t( 'form_err_msg' );
	}
	if ( '1' !== $g( 'consent' ) ) {
		$errors['consent'] = mcupr_t( 'form_err_consent' );
	}
	if ( $errors ) {
		return array( 'ok' => false, 'errors' => $errors, 'ref' => '' );
	}
	if ( $too_fast_or_old ) {
		return array( 'ok' => false, 'errors' => array( 'spam' => mcupr_t( 'form_err_spam' ) ), 'ref' => '' );
	}
	if ( (int) get_transient( $key ) >= 5 ) {
		return array( 'ok' => false, 'errors' => array( 'limit' => mcupr_t( 'form_err_limit' ) ), 'ref' => '' );
	}
	$kind_label = isset( $kinds[ $kind ] ) ? $kinds[ $kind ] : reset( $kinds );
	$title      = ( 'complaint' === $type ? $kind_label : ( '' !== $subj ? $subj : $kind_label ) ) . ' – ' . $name;
	$id         = wp_insert_post( array( 'post_type' => 'mcupr_msg', 'post_status' => 'private', 'post_title' => $title, 'post_content' => $msg ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return array( 'ok' => false, 'errors' => array( 'spam' => mcupr_t( 'form_err' ) ), 'ref' => '' );
	}
	$ref = 'MC-' . gmdate( 'Ymd', $now ) . '-' . $id;
	foreach ( array( 'type' => $type, 'name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subj, 'kind' => $kind_label, 'ref' => $ref ) as $k => $v ) {
		update_post_meta( $id, '_mcupr_msg_' . $k, $v );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	// ---- e-mail to the organisation
	$to = sanitize_email( (string) get_theme_mod( 'mcupr_form_to', '' ) );
	$to = is_email( $to ) ? $to : get_option( 'admin_email' );
	$body = $kind_label . "\n" . mcupr_t( 'form_ref' ) . ': ' . $ref . "\n\n" . mcupr_t( 'form_name' ) . ': ' . $name . "\n" . mcupr_t( 'form_email' ) . ': ' . $email . "\n" . mcupr_t( 'form_phone' ) . ': ' . $phone . "\n\n" . $msg . "\n\n" . admin_url( 'post.php?post=' . $id . '&action=edit' );
	wp_mail( $to, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $title, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
	return array( 'ok' => true, 'errors' => array(), 'ref' => $ref );
}

/** admin-post handler: process, then go back to the page (messages are shown by the form via the query string). */
function mcupr_form_handler() {
	$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	$back = isset( $data['back'] ) ? wp_validate_redirect( esc_url_raw( $data['back'] ), home_url( '/' ) ) : home_url( '/' );
	$fid  = isset( $data['fid'] ) ? sanitize_key( $data['fid'] ) : 'form';
	if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $data['_wpnonce'] ), 'mcupr_form' ) ) {
		wp_safe_redirect( add_query_arg( array( 'mf' => 'err', 'mfe' => 'spam' ), $back ) . '#' . $fid );
		exit;
	}
	$r = mcupr_form_process( $data );
	if ( $r['ok'] ) {
		wp_safe_redirect( add_query_arg( array( 'mf' => 'ok', 'ref' => rawurlencode( $r['ref'] ) ), $back ) . '#' . $fid );
	} else {
		wp_safe_redirect( add_query_arg( array( 'mf' => 'err', 'mfe' => implode( ',', array_keys( $r['errors'] ) ) ), $back ) . '#' . $fid );
	}
	exit;
}
add_action( 'admin_post_nopriv_mcupr_form', 'mcupr_form_handler' );
add_action( 'admin_post_mcupr_form', 'mcupr_form_handler' );

function mcupr_form_html( $type = 'contact' ) {
	$type  = 'complaint' === $type ? 'complaint' : 'contact';
	$fid   = 'mform-' . $type;
	$kinds = mcupr_form_kinds( $type );
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['mf'] ) ? sanitize_key( wp_unslash( $_GET['mf'] ) ) : '';
	$errs  = isset( $_GET['mfe'] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET['mfe'] ) ) ) ) : array();
	$ref   = isset( $_GET['ref'] ) ? preg_replace( '/[^A-Za-z0-9\-]/', '', wp_unslash( $_GET['ref'] ) ) : '';
	// phpcs:enable
	$msgs  = array( 'name' => 'form_err_name', 'email' => 'form_err_email', 'message' => 'form_err_msg', 'consent' => 'form_err_consent', 'spam' => 'form_err_spam', 'limit' => 'form_err_limit' );
	$bad   = array_intersect( array_keys( $msgs ), $errs );
	$privacy = mcupr_policy_url( 'privacy' );
	ob_start();
	echo '<section class="mform" id="' . esc_attr( $fid ) . '">';
	if ( 'ok' === $state ) {
		echo '<div class="mform-ok" role="status"><p><b>' . esc_html( mcupr_t( 'form_ok' ) ) . '</b></p>' . ( $ref ? '<p>' . esc_html( mcupr_t( 'form_ref' ) ) . ': <b class="mform-ref">' . esc_html( $ref ) . '</b></p>' : '' ) . '</div>';
		echo '</section>';
		return ob_get_clean();
	}
	if ( 'err' === $state ) {
		echo '<div class="mform-err" role="alert"><p><b>' . esc_html( mcupr_t( 'form_err' ) ) . '</b></p><ul>';
		foreach ( $bad as $e ) {
			echo '<li>' . esc_html( mcupr_t( $msgs[ $e ] ) ) . '</li>';
		}
		echo '</ul></div>';
	}
	$inv = static function ( $f ) use ( $bad ) {
		return in_array( $f, $bad, true ) ? ' aria-invalid="true"' : '';
	};
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
	echo '<input type="hidden" name="action" value="mcupr_form"><input type="hidden" name="type" value="' . esc_attr( $type ) . '"><input type="hidden" name="fid" value="' . esc_attr( $fid ) . '">';
	echo '<input type="hidden" name="back" value="' . esc_url( remove_query_arg( array( 'mf', 'mfe', 'ref' ), is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' ) ) ) . '">';
	wp_nonce_field( 'mcupr_form', '_wpnonce', true, true );
	echo '<input type="hidden" name="tk" value="' . esc_attr( mcupr_form_token( time() ) ) . '">';
	echo '<div class="mform-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	echo '<p class="mform-note">' . esc_html( mcupr_t( 'form_req' ) ) . '</p>';
	echo '<p><label for="' . esc_attr( $fid ) . '-name">' . esc_html( mcupr_t( 'form_name' ) ) . ' <span aria-hidden="true">*</span></label><input id="' . esc_attr( $fid ) . '-name" name="name" type="text" autocomplete="name" required' . $inv( 'name' ) . '></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="mform-row"><p><label for="' . esc_attr( $fid ) . '-email">' . esc_html( mcupr_t( 'form_email' ) ) . ' <span aria-hidden="true">*</span></label><input id="' . esc_attr( $fid ) . '-email" name="email" type="email" autocomplete="email" required' . $inv( 'email' ) . '></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><label for="' . esc_attr( $fid ) . '-phone">' . esc_html( mcupr_t( 'form_phone' ) ) . '</label><input id="' . esc_attr( $fid ) . '-phone" name="phone" type="tel" autocomplete="tel"></p></div>';
	echo '<p><label for="' . esc_attr( $fid ) . '-kind">' . esc_html( mcupr_t( 'form_kind' ) ) . '</label><select id="' . esc_attr( $fid ) . '-kind" name="kind">';
	foreach ( $kinds as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	if ( 'contact' === $type ) {
		echo '<p><label for="' . esc_attr( $fid ) . '-subject">' . esc_html( mcupr_t( 'form_subject' ) ) . '</label><input id="' . esc_attr( $fid ) . '-subject" name="subject" type="text"></p>';
	}
	echo '<p><label for="' . esc_attr( $fid ) . '-msg">' . esc_html( mcupr_t( 'form_message' ) ) . ' <span aria-hidden="true">*</span></label><textarea id="' . esc_attr( $fid ) . '-msg" name="message" rows="6" required' . $inv( 'message' ) . '></textarea></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p class="mform-consent"><label><input type="checkbox" name="consent" value="1" required' . $inv( 'consent' ) . '> <span>' . esc_html( mcupr_t( 'form_consent' ) ) . ( $privacy ? ' <a href="' . esc_url( $privacy ) . '">' . esc_html( mcupr_t( 'privacy' ) ) . '</a>' : '' ) . '</span></label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><button type="submit" class="btn">' . esc_html( mcupr_t( 'form_send' ) ) . '</button></p></form></section>';
	return ob_get_clean();
}

add_shortcode(
	'mcupr_form',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'type' => 'contact' ), $atts, 'mcupr_form' );
		return mcupr_form_html( $a['type'] );
	}
);

/* ------------------------------------------------------------------ admin: read the messages */

function mcupr_msg_box_add() {
	add_meta_box( 'mcupr_msg', __( 'Message details', 'mcupr' ), 'mcupr_msg_box', 'mcupr_msg', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'mcupr_msg_box_add' );

function mcupr_msg_box( $post ) {
	$m = static function ( $k ) use ( $post ) {
		return (string) get_post_meta( $post->ID, '_mcupr_msg_' . $k, true );
	};
	echo '<table class="form-table"><tbody>';
	foreach ( array( 'ref' => __( 'Reference number', 'mcupr' ), 'kind' => __( 'Type', 'mcupr' ), 'name' => __( 'Name', 'mcupr' ), 'email' => __( 'Email', 'mcupr' ), 'phone' => __( 'Telephone', 'mcupr' ), 'subject' => __( 'Subject', 'mcupr' ) ) as $k => $label ) {
		if ( '' !== $m( $k ) ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . ( 'email' === $k ? '<a href="mailto:' . esc_attr( $m( $k ) ) . '">' . esc_html( $m( $k ) ) . '</a>' : esc_html( $m( $k ) ) ) . '</td></tr>';
		}
	}
	echo '<tr><th>' . esc_html__( 'Message', 'mcupr' ) . '</th><td style="white-space:pre-wrap">' . esc_html( $post->post_content ) . '</td></tr></tbody></table>';
}

add_filter(
	'manage_mcupr_msg_posts_columns',
	static function () {
		return array( 'cb' => '<input type="checkbox">', 'title' => __( 'Message', 'mcupr' ), 'mcupr_ref' => __( 'Reference number', 'mcupr' ), 'mcupr_from' => __( 'Email', 'mcupr' ), 'date' => __( 'Date', 'mcupr' ) );
	}
);
add_action(
	'manage_mcupr_msg_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_ref' === $col ) {
			echo esc_html( get_post_meta( $id, '_mcupr_msg_ref', true ) );
		}
		if ( 'mcupr_from' === $col ) {
			echo esc_html( get_post_meta( $id, '_mcupr_msg_email', true ) );
		}
	},
	10,
	2
);

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_setting( 'mcupr_form_to', array( 'default' => '', 'sanitize_callback' => 'sanitize_email' ) );
		$wp_customize->add_control( 'mcupr_form_to', array( 'section' => 'mcupr_org', 'label' => __( 'Email address that receives the contact and complaint forms (empty = the site administrator\'s email)', 'mcupr' ), 'type' => 'email' ) );
	},
	35
);
