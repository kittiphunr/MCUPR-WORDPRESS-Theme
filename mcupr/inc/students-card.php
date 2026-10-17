<?php
/**
 * Student card security code (RFID) and national ID number, as a second step when a student signs in.
 *
 *  - The 10-digit code of the student card (RFID) and the 13-digit national ID number come with the student import (columns
 *    "รหัสบัตร RFID" and "เลขบัตรประชาชน") or are set by staff on the student's record. They are NEVER stored as they are:
 *    only a keyed hash (HMAC-SHA256 with the site's secret keys) is kept, so neither staff nor a copy of the database can read them.
 *  - When the switch is on, a student signs in with student ID + password + the card: tapping the card on a USB reader that
 *    "types" the code (nothing is typed by hand; the box hides the digits), if the record has a card code; else the 13-digit
 *    national ID number. A record with neither cannot sign in (it is told to contact the staff) – the settings page shows how
 *    many students are covered before the switch is turned on.
 *  - The same rule is applied to the ordinary WordPress login page for student accounts, so it cannot be used to go around it.
 *  - Staff accounts are not affected. Signing in with the Google account (when it is on) is its own proof and is not asked for a card.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_CARD_RFID = '_mcupr_s_rfid_h';
const MCUPR_CARD_IDC  = '_mcupr_s_idcard_h';

/* ------------------------------------------------------------------ values and hashes */

function mcupr_card_rfid_ok( $v ) {
	return (bool) preg_match( '/^\d{10}$/', (string) $v );
}
/** 13 digits and the check digit of a Thai national ID number. */
function mcupr_card_idcard_ok( $v ) {
	$v = (string) $v;
	if ( ! preg_match( '/^\d{13}$/', $v ) ) {
		return false;
	}
	$s = 0;
	for ( $i = 0; $i < 12; $i++ ) {
		$s += (int) $v[ $i ] * ( 13 - $i );
	}
	return ( ( 11 - $s % 11 ) % 10 ) === (int) $v[12];
}
/** Digits only (a reader or a spreadsheet may add spaces, dashes or ".0"). */
function mcupr_card_digits( $v ) {
	$v = trim( (string) $v );
	$v = preg_replace( '/\.0+$/', '', $v );
	return preg_replace( '/\D+/', '', $v );
}
function mcupr_card_hash( $kind, $value ) {
	return hash_hmac( 'sha256', $kind . '|' . $value, wp_salt( 'auth' ) );
}
/** A cell of the import file → '' (empty), 'bad' (not valid) or 'h:…' (the hash, which is all that is kept from here on). */
function mcupr_card_import_cell( $kind, $raw ) {
	$d = mcupr_card_digits( $raw );
	if ( '' === $d ) {
		return '';
	}
	$ok = 'rfid' === $kind ? mcupr_card_rfid_ok( $d ) : mcupr_card_idcard_ok( $d );
	return $ok ? 'h:' . mcupr_card_hash( $kind, $d ) : 'bad';
}

function mcupr_card_state( $post_id ) {
	return array( 'rfid' => '' !== (string) get_post_meta( $post_id, MCUPR_CARD_RFID, true ), 'idcard' => '' !== (string) get_post_meta( $post_id, MCUPR_CARD_IDC, true ) );
}
/** hash → student id for every card code (one query, for the importer's "already used" check). */
function mcupr_card_rfid_map() {
	global $wpdb;
	$map = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_value AS h, post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", MCUPR_CARD_RFID ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$map[ $r->h ] = (int) $r->post_id;
	}
	return $map;
}

/* ------------------------------------------------------------------ the settings */

function mcupr_card_opts() {
	$o = get_option( 'mcupr_card', array() );
	$o = is_array( $o ) ? $o : array();
	return array( 'on' => ! empty( $o['on'] ) ? 1 : 0, 'typing' => ! empty( $o['typing'] ) ? 1 : 0 );
}
function mcupr_card_required() {
	return (bool) mcupr_card_opts()['on'];
}

/** How many students who study now: with a card code, with only an ID number, with neither. */
function mcupr_card_coverage() {
	global $wpdb;
	$ids = (array) $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_mcupr_s_status' AND s.meta_value = 'studying' WHERE p.post_type = 'mcupr_student' AND p.post_status <> 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$r   = array_flip( array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", MCUPR_CARD_RFID ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$c   = array_flip( array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", MCUPR_CARD_IDC ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out = array( 'all' => count( $ids ), 'rfid' => 0, 'idcard' => 0, 'none' => 0 );
	foreach ( $ids as $id ) {
		if ( isset( $r[ (string) $id ] ) ) {
			++$out['rfid'];
		} elseif ( isset( $c[ (string) $id ] ) ) {
			++$out['idcard'];
		} else {
			++$out['none'];
		}
	}
	return $out;
}

add_action(
	'mcupr_stu_settings_extra',
	static function () {
		$o = mcupr_card_opts();
		$c = mcupr_card_coverage();
		echo '<h2>' . esc_html__( 'Student card at sign-in', 'mcupr' ) . '</h2><p style="max-width:56rem">' . esc_html__( 'As a second step, a student taps the student card on a USB card reader (it types the 10-digit security code by itself; nothing is typed by hand). A student whose record has no card code gives the 13-digit national ID number instead. The codes are kept only as a one-way hash.', 'mcupr' ) . '</p>';
		echo '<table class="widefat striped" style="max-width:36rem"><tbody>';
		/* translators: %d: number of students */
		echo '<tr><td>' . esc_html__( 'Students who study now', 'mcupr' ) . '</td><td>' . (int) $c['all'] . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'with a card code (tap the card)', 'mcupr' ) . '</td><td>' . (int) $c['rfid'] . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'with only a national ID number', 'mcupr' ) . '</td><td>' . (int) $c['idcard'] . '</td></tr>';
		echo '<tr><td><strong>' . esc_html__( 'with neither (cannot sign in while this is on)', 'mcupr' ) . '</strong></td><td><strong>' . (int) $c['none'] . '</strong></td></tr></tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_card_save">';
		wp_nonce_field( 'mcupr_card_save', 'mcupr_card_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Ask for the card', 'mcupr' ) . '</th><td><label><input type="checkbox" name="on" value="1"' . checked( $o['on'], 1, false ) . '> ' . esc_html__( 'On: students sign in with the card (or the national ID number)', 'mcupr' ) . '</label><p class="description">' . esc_html__( 'Import the card codes and ID numbers first (Import students) – students with neither cannot sign in.', 'mcupr' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Typing by hand', 'mcupr' ) . '</th><td><label><input type="checkbox" name="typing" value="1"' . checked( $o['typing'], 1, false ) . '> ' . esc_html__( 'Also accept the code typed by hand (slower than a reader). Off: only a reader is accepted.', 'mcupr' ) . '</label></td></tr>';
		echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form>';
	}
);

add_action(
	'admin_post_mcupr_card_save',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! isset( $_POST['mcupr_card_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_card_nonce'] ) ), 'mcupr_card_save' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		update_option( 'mcupr_card', array( 'on' => ! empty( $_POST['on'] ) ? 1 : 0, 'typing' => ! empty( $_POST['typing'] ) ? 1 : 0 ), false );
		mcupr_audit( 'settings', array( 'note' => 'student card sign-in ' . ( ! empty( $_POST['on'] ) ? 'on' : 'off' ) ) );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-settings&saved=1' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ staff: on the student's record */

add_action(
	'add_meta_boxes_mcupr_student',
	static function () {
		if ( current_user_can( MCUPR_STU_CAP ) ) {
			add_meta_box( 'mcupr_student_card', __( 'Student card', 'mcupr' ), 'mcupr_card_box', MCUPR_STU, 'side', 'default' );
		}
	}
);
function mcupr_card_box( $post ) {
	wp_nonce_field( 'mcupr_card_box', 'mcupr_card_box_nonce' );
	$s = mcupr_card_state( $post->ID );
	echo '<p class="description">' . esc_html__( 'The codes are kept only as a one-way hash: they cannot be shown again. Leave a box empty to keep what is there.', 'mcupr' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Card code (RFID)', 'mcupr' ) . ':</strong> ' . esc_html( $s['rfid'] ? __( 'set', 'mcupr' ) : __( 'not set', 'mcupr' ) ) . '<br><label class="screen-reader-text" for="mcs-rfid">' . esc_html__( 'New card code (10 digits)', 'mcupr' ) . '</label><input id="mcs-rfid" name="mcupr_card_rfid" type="password" inputmode="numeric" maxlength="10" autocomplete="off" class="regular-text" style="width:100%" placeholder="' . esc_attr__( '10 digits – tap the card here', 'mcupr' ) . '">';
	if ( $s['rfid'] ) {
		echo '<br><label><input type="checkbox" name="mcupr_card_clear_rfid" value="1"> ' . esc_html__( 'Remove the card code', 'mcupr' ) . '</label>';
	}
	echo '</p><p><strong>' . esc_html__( 'National ID number', 'mcupr' ) . ':</strong> ' . esc_html( $s['idcard'] ? __( 'set', 'mcupr' ) : __( 'not set', 'mcupr' ) ) . '<br><label class="screen-reader-text" for="mcs-idc">' . esc_html__( 'New national ID number (13 digits)', 'mcupr' ) . '</label><input id="mcs-idc" name="mcupr_card_idc" type="password" inputmode="numeric" maxlength="13" autocomplete="off" class="regular-text" style="width:100%" placeholder="' . esc_attr__( '13 digits', 'mcupr' ) . '">';
	if ( $s['idcard'] ) {
		echo '<br><label><input type="checkbox" name="mcupr_card_clear_idc" value="1"> ' . esc_html__( 'Remove the national ID number', 'mcupr' ) . '</label>';
	}
	echo '</p>';
}
add_action(
	'save_post_mcupr_student',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_card_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_card_box_nonce'] ) ), 'mcupr_card_box' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( MCUPR_STU_CAP ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$changed = array();
		foreach ( array( 'rfid' => array( MCUPR_CARD_RFID, 'mcupr_card_rfid', 'mcupr_card_clear_rfid' ), 'idcard' => array( MCUPR_CARD_IDC, 'mcupr_card_idc', 'mcupr_card_clear_idc' ) ) as $kind => $f ) {
			if ( ! empty( $_POST[ $f[2] ] ) ) {
				delete_post_meta( $post_id, $f[0] );
				$changed[] = $kind . ' removed';
				continue;
			}
			$h = mcupr_card_import_cell( $kind, isset( $_POST[ $f[1] ] ) ? (string) wp_unslash( $_POST[ $f[1] ] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( 0 === strpos( $h, 'h:' ) ) {
				$hash  = substr( $h, 2 );
				$map   = 'rfid' === $kind ? mcupr_card_rfid_map() : array();
				if ( 'rfid' === $kind && isset( $map[ $hash ] ) && (int) $map[ $hash ] !== (int) $post_id ) {
					continue; // another student has this card
				}
				update_post_meta( $post_id, $f[0], $hash );
				$changed[] = $kind . ' set';
			}
		}
		if ( $changed ) {
			mcupr_audit( 'cardset', array( 'post' => (int) $post_id, 'sid' => (string) get_post_meta( $post_id, '_mcupr_s_sid', true ), 'note' => implode( ', ', $changed ) ) );
		}
	}
);

/* ------------------------------------------------------------------ the check at sign-in */

/**
 * '' = fine; 'card' = the code / number is wrong or missing; 'cardnone' = the record has neither (contact the staff).
 * $rfid, $idc as posted (digits only are kept).
 */
function mcupr_card_verify( $student_post_id, $rfid, $idc ) {
	$st = mcupr_card_state( $student_post_id );
	if ( $st['rfid'] ) {
		$d = mcupr_card_digits( $rfid );
		return mcupr_card_rfid_ok( $d ) && hash_equals( (string) get_post_meta( $student_post_id, MCUPR_CARD_RFID, true ), mcupr_card_hash( 'rfid', $d ) ) ? '' : 'card';
	}
	if ( $st['idcard'] ) {
		$d = mcupr_card_digits( $idc );
		return mcupr_card_idcard_ok( $d ) && hash_equals( (string) get_post_meta( $student_post_id, MCUPR_CARD_IDC, true ), mcupr_card_hash( 'idcard', $d ) ) ? '' : 'card';
	}
	return 'cardnone';
}

/** The portal login (inc/students-portal.php) says it does the check itself. */
function mcupr_card_portal_flag( $set = null ) {
	static $on = false;
	if ( null !== $set ) {
		$on = (bool) $set;
	}
	return $on;
}

/** The ordinary WordPress login page: a student account is not let in with the password alone while the card is asked for. */
add_filter(
	'authenticate',
	static function ( $user ) {
		if ( ! $user instanceof WP_User || mcupr_card_portal_flag() || ! mcupr_card_required() || ! function_exists( 'mcupr_is_student_user' ) || ! mcupr_is_student_user( $user->ID ) ) {
			return $user;
		}
		return new WP_Error( 'mcupr_card', __( 'Students sign in at the student portal, with the student card.', 'mcupr' ) );
	},
	99
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( get_query_var( 'mcupr_student' ) && ! is_user_logged_in() && mcupr_card_required() ) {
			wp_enqueue_script( 'mcupr-card', get_theme_file_uri( 'assets/js/student-card.js' ), array(), MCUPR_VERSION, true );
		}
	}
);
