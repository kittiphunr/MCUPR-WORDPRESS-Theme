<?php
/**
 * Online requests (คำร้อง / ขอเอกสาร) with a tracking number and a status – built on the form builder.
 *
 *  - Any form can be made a "request" (a box on its edit screen): who may send it (students who are logged in, or anyone),
 *    who sees to it by default, and whether it takes files. A form gets a field type "file" (PDF, JPG, PNG, up to 5 MB).
 *  - Each answer of such a form gets a number (REQ-2026-00042), a status (new, being checked, more information needed,
 *    approved, not approved, done), a person who sees to it and a history (who, when, the message the sender can read).
 *  - The student sees "My requests" in the student portal; anybody can follow a request on /request/ with the number and the
 *    e-mail address or student ID used – only the status and the messages are shown, never what was written.
 *  - The sender is told by e-mail when the request is received and whenever its status changes (if the staff member ticks it).
 *  - Files are kept in a folder that the web server does not serve, and are given out by PHP only to the staff and to the sender.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_RQ_CAP = 'mcupr_handle_requests';

/* ------------------------------------------------------------------ statuses */

/** key => admin label. The words shown to the sender are mcupr_t( 'rq_st_' . key ). */
function mcupr_rq_statuses() {
	return array(
		'new'      => __( 'New', 'mcupr' ),
		'review'   => __( 'Being checked', 'mcupr' ),
		'need'     => __( 'More information needed', 'mcupr' ),
		'approved' => __( 'Approved', 'mcupr' ),
		'rejected' => __( 'Not approved', 'mcupr' ),
		'done'     => __( 'Done', 'mcupr' ),
	);
}

/** The colour of a status chip (also the shape word for people who cannot tell colours apart is the text itself). */
function mcupr_rq_chip( $status, $lang = '' ) {
	$k = isset( mcupr_rq_statuses()[ $status ] ) ? $status : 'new';
	return '<span class="rq-st" data-st="' . esc_attr( $k ) . '">' . esc_html( mcupr_t( 'rq_st_' . $k, $lang ) ) . '</span>';
}

/* ------------------------------------------------------------------ who can handle requests */

/** Staff who may edit pages, and the registrar role, may handle requests (no role needs rebuilding: the capability is given here). */
add_filter(
	'user_has_cap',
	static function ( $all, $caps, $args, $user ) {
		if ( ! empty( $all[ MCUPR_RQ_CAP ] ) ) {
			return $all;
		}
		if ( ! empty( $all['edit_pages'] ) || ( defined( 'MCUPR_STU_CAP' ) && ! empty( $all[ MCUPR_STU_CAP ] ) ) ) {
			$all[ MCUPR_RQ_CAP ] = true;
		}
		return $all;
	},
	10,
	4
);

/** The list of answers opens for the people who handle requests (and shows them only the requests). */
add_filter(
	'register_post_type_args',
	static function ( $args, $type ) {
		if ( 'mcupr_fe' === $type ) {
			$args['capabilities'] = array_merge( isset( $args['capabilities'] ) ? $args['capabilities'] : array(), array(
				'edit_posts'         => MCUPR_RQ_CAP,
				'edit_others_posts'  => MCUPR_RQ_CAP,
				'read_private_posts' => MCUPR_RQ_CAP,
			) );
		}
		return $args;
	},
	10,
	2
);
/** One answer: a request needs the capability; any other answer still needs the right to edit pages. */
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		if ( in_array( $cap, array( 'edit_post', 'read_post' ), true ) && ! empty( $args[0] ) && 'mcupr_fe' === get_post_type( (int) $args[0] ) ) {
			return mcupr_rq_is_entry( (int) $args[0] ) ? array( MCUPR_RQ_CAP ) : array( 'edit_pages' );
		}
		return $caps;
	},
	20,
	4
);
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'mcupr_fe' !== $q->get( 'post_type' ) ) {
			return;
		}
		$only = ! current_user_can( 'edit_pages' ) || ! empty( $_GET['mcupr_rq'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$meta = (array) $q->get( 'meta_query' );
		if ( $only ) {
			$meta[] = array( 'key' => '_mcupr_rq_code', 'compare' => 'EXISTS' );
		}
		$st = isset( $_GET['mcupr_rq_status'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_rq_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( mcupr_rq_statuses()[ $st ] ) ) {
			$meta[] = array( 'key' => '_mcupr_rq_status', 'value' => $st );
		}
		if ( ! empty( $_GET['mcupr_rq_owner'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$meta[] = array( 'key' => '_mcupr_rq_owner', 'value' => (int) $_GET['mcupr_rq_owner'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( $meta ) {
			$q->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	}
);

/* ------------------------------------------------------------------ the settings of a request form */

function mcupr_rq_defaults() {
	return array( 'on' => 0, 'who' => 'students', 'owner' => 0 );
}

function mcupr_rq_opts( $form_id ) {
	$o = get_post_meta( $form_id, '_mcupr_rq_opts', true );
	$o = array_merge( mcupr_rq_defaults(), is_array( $o ) ? $o : array() );
	return array(
		'on'    => ! empty( $o['on'] ) ? 1 : 0,
		'who'   => 'all' === $o['who'] ? 'all' : 'students',
		'owner' => (int) $o['owner'],
	);
}

/** Is this form a request? */
function mcupr_rq_is_form( $form_id ) {
	return 'mcupr_fb' === get_post_type( $form_id ) && mcupr_rq_opts( $form_id )['on'];
}

/** Is this answer a request (it has a number)? */
function mcupr_rq_is_entry( $id ) {
	return '' !== (string) get_post_meta( $id, '_mcupr_rq_code', true );
}

add_action(
	'add_meta_boxes_mcupr_fb',
	static function () {
		add_meta_box( 'mcupr_rq_opts', __( 'Request (tracking number and status)', 'mcupr' ), 'mcupr_rq_form_box', 'mcupr_fb', 'normal', 'default' );
	}
);

function mcupr_rq_form_box( $post ) {
	$o = mcupr_rq_opts( $post->ID );
	echo '<p class="description" style="max-width:52rem">' . esc_html__( 'Make this form a request (for example “Certificate of student status”): each answer gets a number, a status and a history, the sender can follow it and is told by e-mail. Add a “File upload” field if documents must be attached.', 'mcupr' ) . '</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Use as a request', 'mcupr' ) . '</th><td><label><input type="checkbox" name="mcupr_rq[on]" value="1"' . checked( $o['on'], 1, false ) . '> ' . esc_html__( 'Yes – give each answer a number, a status and a history', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Who can send it', 'mcupr' ) . '</th><td><label style="display:block"><input type="radio" name="mcupr_rq[who]" value="students"' . checked( $o['who'], 'students', false ) . '> ' . esc_html__( 'Students who are logged in (they follow it in the student portal)', 'mcupr' ) . '</label><label style="display:block"><input type="radio" name="mcupr_rq[who]" value="all"' . checked( $o['who'], 'all', false ) . '> ' . esc_html__( 'Anyone (they follow it with the number and an e-mail address)', 'mcupr' ) . '</label></td></tr>';
	$users = get_users( array( 'capability' => MCUPR_RQ_CAP, 'orderby' => 'display_name', 'number' => 200, 'fields' => array( 'ID', 'display_name' ) ) );
	echo '<tr><th scope="row"><label for="mcupr-rq-owner">' . esc_html__( 'Person who sees to it', 'mcupr' ) . '</label></th><td><select name="mcupr_rq[owner]" id="mcupr-rq-owner"><option value="0">' . esc_html__( 'Nobody yet', 'mcupr' ) . '</option>';
	foreach ( $users as $u ) {
		echo '<option value="' . (int) $u->ID . '"' . selected( $o['owner'], (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
	}
	echo '</select> <span class="description">' . esc_html__( 'Given to each new request; it can be changed on each one.', 'mcupr' ) . '</span></td></tr>';
	echo '</tbody></table>';
}

add_action(
	'save_post_mcupr_fb',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_fb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_fb_nonce'] ) ), 'mcupr_fb_save' ) || ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		$in = isset( $_POST['mcupr_rq'] ) && is_array( $_POST['mcupr_rq'] ) ? wp_unslash( $_POST['mcupr_rq'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_post_meta(
			$post_id,
			'_mcupr_rq_opts',
			array(
				'on'    => ! empty( $in['on'] ) ? 1 : 0,
				'who'   => isset( $in['who'] ) && 'all' === $in['who'] ? 'all' : 'students',
				'owner' => isset( $in['owner'] ) ? absint( $in['owner'] ) : 0,
			)
		);
	},
	20
);

/* ------------------------------------------------------------------ who may send a request form */

add_filter(
	'mcupr_fb_guard',
	static function ( $err, $form_id ) {
		if ( '' === $err && mcupr_rq_is_form( $form_id ) && 'students' === mcupr_rq_opts( $form_id )['who'] && ! mcupr_current_student() ) {
			return 'student';
		}
		return $err;
	},
	10,
	2
);
add_filter(
	'mcupr_fb_gate_html',
	static function ( $html, $form_id ) {
		if ( '' === $html && mcupr_rq_is_form( $form_id ) && 'students' === mcupr_rq_opts( $form_id )['who'] && ! mcupr_current_student() ) {
			return '<div class="mform-err" role="status"><p><b>' . esc_html( mcupr_t( 'rq_need_student' ) ) . '</b></p><p><a class="btn" href="' . esc_url( mcupr_student_portal_url() ) . '">' . esc_html( mcupr_t( 'stu_login' ) ) . '</a></p></div>';
		}
		return $html;
	},
	10,
	2
);
/** The sender is brought back to where the form was, with the number. */
add_filter(
	'mcupr_fb_ok_args',
	static function ( $args, $entry, $form_id ) {
		if ( $entry && mcupr_rq_is_entry( $entry ) ) {
			$args['rqc'] = (string) get_post_meta( $entry, '_mcupr_rq_code', true );
		}
		return $args;
	},
	10,
	3
);
add_filter(
	'mcupr_fb_ok_html',
	static function ( $html, $form_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$c = isset( $_GET['rqc'] ) ? sanitize_text_field( wp_unslash( $_GET['rqc'] ) ) : '';
		if ( mcupr_rq_is_form( $form_id ) && preg_match( '/^REQ-\d{4}-\d{5}$/', $c ) ) {
			$html .= '<p class="rq-ok-code">' . esc_html( sprintf( mcupr_t( 'rq_ok_code' ), $c ) ) . '</p><p><a href="' . esc_url( mcupr_rq_track_url( array( 'code' => $c ) ) ) . '">' . esc_html( mcupr_t( 'rq_ok_track' ) ) . '</a></p>';
		}
		return $html;
	},
	10,
	2
);
add_filter(
	'mcupr_fb_submit_label',
	static function ( $label, $form_id ) {
		return mcupr_rq_is_form( $form_id ) ? mcupr_t( 'rq_send' ) : $label;
	},
	10,
	2
);
/** A request form placed in the student portal sends the student back to the portal. */
add_filter(
	'mcupr_fb_back',
	static function ( $url, $form_id ) {
		return mcupr_rq_is_form( $form_id ) && get_query_var( 'mcupr_student' ) ? mcupr_student_portal_url( array( 'rq' => (int) $form_id ) ) : $url;
	},
	10,
	2
);

/* ------------------------------------------------------------------ files */

function mcupr_rq_max_bytes() {
	return (int) min( 5 * MB_IN_BYTES, wp_max_upload_size() );
}

/** The folder of the files (outside what the web server shows as far as it can be told: unguessable names, nothing listed, PHP gives them out). */
function mcupr_rq_dir( $token = '' ) {
	$up   = wp_upload_dir();
	$base = trailingslashit( $up['basedir'] ) . 'mcupr-requests';
	if ( ! is_dir( $base ) ) {
		wp_mkdir_p( $base );
		file_put_contents( $base . '/index.php', "<?php // silence\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $base . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return '' === $token ? $base : $base . '/' . preg_replace( '/[^a-f0-9]/', '', $token );
}

/** What each "file" field of the form was given: array( id => array( tmp, name, size, mime ) ) and the errors ( id => key ). Nothing is stored yet. */
function mcupr_rq_check_files( $fields, $files ) {
	$ok   = array();
	$errs = array();
	foreach ( $fields as $f ) {
		if ( 'file' !== $f['type'] ) {
			continue;
		}
		$k = 'file_' . $f['id'];
		$u = isset( $files[ $k ] ) && is_array( $files[ $k ] ) ? $files[ $k ] : null;
		if ( ! $u || ! isset( $u['error'] ) || UPLOAD_ERR_NO_FILE === (int) $u['error'] ) {
			if ( ! empty( $f['required'] ) ) {
				$errs[ $f['id'] ] = 'required';
			}
			continue;
		}
		if ( in_array( (int) $u['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
			$errs[ $f['id'] ] = 'file_size';
			continue;
		}
		if ( UPLOAD_ERR_OK !== (int) $u['error'] || empty( $u['tmp_name'] ) ) {
			$errs[ $f['id'] ] = 'file_fail';
			continue;
		}
		$size = isset( $u['size'] ) ? (int) $u['size'] : 0;
		if ( $size < 1 || $size > mcupr_rq_max_bytes() || filesize( $u['tmp_name'] ) > mcupr_rq_max_bytes() ) {
			$errs[ $f['id'] ] = 'file_size';
			continue;
		}
		$name = sanitize_file_name( wp_basename( (string) ( $u['name'] ?? '' ) ) );
		$chk  = wp_check_filetype_and_ext( $u['tmp_name'], $name, array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) );
		if ( empty( $chk['ext'] ) || empty( $chk['type'] ) ) {
			$errs[ $f['id'] ] = 'file_type';
			continue;
		}
		$ok[ $f['id'] ] = array( 'tmp' => $u['tmp_name'], 'name' => mb_substr( $name, 0, 120 ), 'size' => $size, 'mime' => $chk['type'] );
	}
	return array( $ok, $errs );
}

/** Keep the checked files of an answer: returns the list kept (token, name, stored name, size, mime, label). */
function mcupr_rq_store_files( $entry, $ups, $fields ) {
	$labels = array();
	foreach ( $fields as $f ) {
		$labels[ $f['id'] ] = $f['label'];
	}
	$out = array();
	foreach ( $ups as $fid => $u ) {
		$token = bin2hex( random_bytes( 12 ) );
		$dir   = mcupr_rq_dir( $token );
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$stored = wp_unique_filename( $dir, $u['name'] );
		$moved  = is_uploaded_file( $u['tmp'] ) ? move_uploaded_file( $u['tmp'], $dir . '/' . $stored ) : ( apply_filters( 'mcupr_rq_accept_non_upload', false ) && copy( $u['tmp'], $dir . '/' . $stored ) );
		if ( $moved ) {
			$out[] = array( 'k' => $token, 'n' => $u['name'], 'f' => $stored, 's' => (int) $u['size'], 'm' => $u['mime'], 'l' => isset( $labels[ $fid ] ) ? $labels[ $fid ] : '' );
		}
	}
	return $out;
}

/** The address that gives out file $i of an answer (to the staff and to the sender only). */
function mcupr_rq_file_url( $entry, $i ) {
	return add_query_arg( 'mcupr_rqf', (int) $entry . '-' . (int) $i, home_url( '/' ) );
}

/** May this person see the files of this request? */
function mcupr_rq_can_see( $entry ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return current_user_can( MCUPR_RQ_CAP ) || (int) get_post_meta( $entry, '_mcupr_rq_uid', true ) === get_current_user_id();
}

add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_GET['mcupr_rqf'] ) || ! preg_match( '/^(\d+)-(\d+)$/', sanitize_text_field( wp_unslash( $_GET['mcupr_rqf'] ) ), $m ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$entry = (int) $m[1];
		$i     = (int) $m[2];
		if ( 'mcupr_fe' !== get_post_type( $entry ) || ! mcupr_rq_is_entry( $entry ) ) {
			wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
		}
		if ( ! mcupr_rq_can_see( $entry ) ) {
			wp_die( esc_html( mcupr_t( 'stu_denied_t' ) ), '', array( 'response' => 403 ) );
		}
		$files = get_post_meta( $entry, '_mcupr_rq_files', true );
		$f     = is_array( $files ) && isset( $files[ $i ] ) ? $files[ $i ] : null;
		$path  = $f ? mcupr_rq_dir( $f['k'] ) . '/' . wp_basename( $f['f'] ) : '';
		$real  = $path ? realpath( $path ) : false;
		$base  = realpath( mcupr_rq_dir() );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) || ! is_readable( $real ) ) {
			wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'Content-Type: ' . $f['m'] );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $f['n'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Content-Length: ' . filesize( $real ) );
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	1
);

/** The files go when the answer goes. */
add_action(
	'before_delete_post',
	static function ( $id ) {
		if ( 'mcupr_fe' !== get_post_type( $id ) ) {
			return;
		}
		$files = get_post_meta( $id, '_mcupr_rq_files', true );
		foreach ( is_array( $files ) ? $files : array() as $f ) {
			$dir = mcupr_rq_dir( $f['k'] );
			foreach ( (array) glob( $dir . '/*' ) as $x ) {
				if ( is_file( $x ) ) {
					wp_delete_file( $x );
				}
			}
			if ( is_dir( $dir ) ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
	}
);

/* ------------------------------------------------------------------ a new request */

/** The next number: REQ-2026-00042 (the count restarts each year; the number is checked not to exist). */
function mcupr_rq_next_code() {
	$y   = current_time( 'Y' );
	$key = 'mcupr_rq_seq_' . $y;
	do {
		$n    = (int) get_option( $key, 0 ) + 1;
		update_option( $key, $n, false );
		$code = sprintf( 'REQ-%s-%05d', $y, $n );
		$dup  = get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'any', 'meta_key' => '_mcupr_rq_code', 'meta_value' => $code, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	} while ( $dup );
	return $code;
}

add_action(
	'mcupr_fb_entry',
	static function ( $id, $form_id, $vals, $fields, $mail, $ups ) {
		if ( ! mcupr_rq_is_form( $form_id ) ) {
			return;
		}
		$o    = mcupr_rq_opts( $form_id );
		$code = mcupr_rq_next_code();
		$uid  = get_current_user_id();
		$sid  = $uid ? mcupr_current_student( true ) : 0;
		$sd   = $sid ? mcupr_student_data( $sid ) : array();
		$to   = '' !== $mail ? $mail : ( ! empty( $sd['email'] ) && is_email( $sd['email'] ) ? $sd['email'] : '' );
		if ( '' === $to && $uid ) {
			$u  = get_userdata( $uid );
			$to = $u && is_email( $u->user_email ) ? $u->user_email : '';
		}
		update_post_meta( $id, '_mcupr_rq_code', $code );
		update_post_meta( $id, '_mcupr_rq_status', 'new' );
		update_post_meta( $id, '_mcupr_rq_owner', $o['owner'] );
		update_post_meta( $id, '_mcupr_rq_uid', $uid );
		update_post_meta( $id, '_mcupr_rq_sid', $sid );
		update_post_meta( $id, '_mcupr_rq_sidcode', ! empty( $sd['sid'] ) ? strtolower( (string) $sd['sid'] ) : '' );
		update_post_meta( $id, '_mcupr_rq_email', $to );
		update_post_meta( $id, '_mcupr_rq_lang', function_exists( 'mcupr_lang' ) ? mcupr_lang() : '' );
		update_post_meta( $id, '_mcupr_rq_log', array( array( 't' => time(), 's' => 'new', 'm' => '', 'u' => $uid, 'n' => '' ) ) );
		if ( $ups ) {
			update_post_meta( $id, '_mcupr_rq_files', mcupr_rq_store_files( $id, $ups, $fields ) );
		}
		wp_update_post( array( 'ID' => $id, 'post_title' => $code . ' · ' . get_the_title( $form_id ) ) );
		if ( '' !== $to ) {
			$lang = (string) get_post_meta( $id, '_mcupr_rq_lang', true );
			wp_mail(
				$to,
				'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . sprintf( mcupr_t( 'rq_mail_new_s', $lang ), $code ),
				sprintf( mcupr_t( 'rq_mail_new_b', $lang ), get_the_title( $form_id ), $code ) . "\n\n" . mcupr_t( 'rq_mail_track', $lang ) . "\n" . mcupr_rq_track_url( array( 'code' => $code ) )
			);
		}
	},
	10,
	6
);

/* ------------------------------------------------------------------ changing the status */

/**
 * Set the status (and/or add a message the sender can read) of a request and keep the history.
 * Returns false when nothing changed. $mail: tell the sender by e-mail.
 */
function mcupr_rq_set( $id, $status, $message = '', $uid = 0, $mail = false ) {
	if ( ! mcupr_rq_is_entry( $id ) || ! isset( mcupr_rq_statuses()[ $status ] ) ) {
		return false;
	}
	$old = (string) get_post_meta( $id, '_mcupr_rq_status', true );
	$msg = mb_substr( sanitize_textarea_field( (string) $message ), 0, 2000 );
	if ( $old === $status && '' === $msg ) {
		return false;
	}
	$log   = get_post_meta( $id, '_mcupr_rq_log', true );
	$log   = is_array( $log ) ? $log : array();
	$u     = $uid ? get_userdata( $uid ) : false;
	$log[] = array( 't' => time(), 's' => $status, 'm' => $msg, 'u' => (int) $uid, 'n' => $u ? $u->display_name : '' );
	update_post_meta( $id, '_mcupr_rq_status', $status );
	update_post_meta( $id, '_mcupr_rq_log', $log );
	if ( $mail ) {
		$to = (string) get_post_meta( $id, '_mcupr_rq_email', true );
		if ( is_email( $to ) ) {
			$lang = (string) get_post_meta( $id, '_mcupr_rq_lang', true );
			$code = (string) get_post_meta( $id, '_mcupr_rq_code', true );
			wp_mail(
				$to,
				'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . sprintf( mcupr_t( 'rq_mail_upd_s', $lang ), $code, mcupr_t( 'rq_st_' . $status, $lang ) ),
				sprintf( mcupr_t( 'rq_mail_upd_b', $lang ), $code, mcupr_t( 'rq_st_' . $status, $lang ) ) . ( '' !== $msg ? "\n\n" . $msg : '' ) . "\n\n" . mcupr_t( 'rq_mail_track', $lang ) . "\n" . mcupr_rq_track_url( array( 'code' => $code ) )
			);
		}
	}
	do_action( 'mcupr_rq_changed', $id, $old, $status );
	return true;
}

/* ------------------------------------------------------------------ admin: one request, the list, the CSV */

add_action(
	'add_meta_boxes_mcupr_fe',
	static function ( $post ) {
		if ( mcupr_rq_is_entry( $post->ID ) ) {
			add_meta_box( 'mcupr_rq_box', __( 'Request: status and history', 'mcupr' ), 'mcupr_rq_box', 'mcupr_fe', 'side', 'high' );
			add_meta_box( 'mcupr_rq_hist', __( 'History and files', 'mcupr' ), 'mcupr_rq_hist_box', 'mcupr_fe', 'normal', 'default' );
		}
	}
);

function mcupr_rq_box( $post ) {
	wp_nonce_field( 'mcupr_rq_save', 'mcupr_rq_nonce' );
	$st    = (string) get_post_meta( $post->ID, '_mcupr_rq_status', true );
	$owner = (int) get_post_meta( $post->ID, '_mcupr_rq_owner', true );
	$mail  = (string) get_post_meta( $post->ID, '_mcupr_rq_email', true );
	echo '<p><strong>' . esc_html( (string) get_post_meta( $post->ID, '_mcupr_rq_code', true ) ) . '</strong></p>';
	echo '<p><label for="mcupr-rq-status">' . esc_html__( 'Status', 'mcupr' ) . '</label><br><select name="mcupr_rq_status" id="mcupr-rq-status" style="width:100%">';
	foreach ( mcupr_rq_statuses() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></p>';
	$users = get_users( array( 'capability' => MCUPR_RQ_CAP, 'orderby' => 'display_name', 'number' => 200, 'fields' => array( 'ID', 'display_name' ) ) );
	echo '<p><label for="mcupr-rq-owner2">' . esc_html__( 'Person who sees to it', 'mcupr' ) . '</label><br><select name="mcupr_rq_owner" id="mcupr-rq-owner2" style="width:100%"><option value="0">' . esc_html__( 'Nobody yet', 'mcupr' ) . '</option>';
	foreach ( $users as $u ) {
		echo '<option value="' . (int) $u->ID . '"' . selected( $owner, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="mcupr-rq-msg">' . esc_html__( 'Message to the sender (they can read it)', 'mcupr' ) . '</label><br><textarea name="mcupr_rq_msg" id="mcupr-rq-msg" rows="4" style="width:100%"></textarea></p>';
	if ( is_email( $mail ) ) {
		echo '<p><label><input type="checkbox" name="mcupr_rq_mail" value="1" checked> ' . esc_html( sprintf( /* translators: %s: e-mail address */ __( 'Tell the sender by e-mail (%s)', 'mcupr' ), $mail ) ) . '</label></p>';
	} else {
		echo '<p class="description">' . esc_html__( 'There is no e-mail address for the sender, so the status can only be followed on the website.', 'mcupr' ) . '</p>';
	}
	echo '<p class="description">' . esc_html__( 'Press “Update” to save.', 'mcupr' ) . '</p>';
}

function mcupr_rq_hist_box( $post ) {
	$log = get_post_meta( $post->ID, '_mcupr_rq_log', true );
	$log = is_array( $log ) ? array_reverse( $log ) : array();
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'mcupr' ) . '</th><th>' . esc_html__( 'Status', 'mcupr' ) . '</th><th>' . esc_html__( 'Message to the sender', 'mcupr' ) . '</th><th>' . esc_html__( 'By', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( $log as $r ) {
		$by = '' !== $r['n'] ? $r['n'] : ( ! empty( $r['u'] ) ? '' : __( 'The sender', 'mcupr' ) );
		echo '<tr><td>' . esc_html( wp_date( 'j M Y H:i', (int) $r['t'] ) ) . '</td><td>' . esc_html( mcupr_rq_statuses()[ $r['s'] ] ?? $r['s'] ) . '</td><td>' . nl2br( esc_html( $r['m'] ) ) . '</td><td>' . esc_html( $by ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$files = get_post_meta( $post->ID, '_mcupr_rq_files', true );
	if ( is_array( $files ) && $files ) {
		echo '<h4>' . esc_html__( 'Files', 'mcupr' ) . '</h4><ul>';
		foreach ( $files as $i => $f ) {
			echo '<li><a href="' . esc_url( mcupr_rq_file_url( $post->ID, $i ) ) . '">' . esc_html( $f['n'] ) . '</a> (' . esc_html( size_format( (int) $f['s'] ) ) . ')' . ( '' !== $f['l'] ? ' – ' . esc_html( $f['l'] ) : '' ) . '</li>';
		}
		echo '</ul>';
	}
}

add_action(
	'save_post_mcupr_fe',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_rq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_rq_nonce'] ) ), 'mcupr_rq_save' ) || ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! mcupr_rq_is_entry( $post_id ) ) {
			return;
		}
		$owner = isset( $_POST['mcupr_rq_owner'] ) ? absint( $_POST['mcupr_rq_owner'] ) : 0;
		if ( $owner && ! user_can( $owner, MCUPR_RQ_CAP ) ) {
			$owner = 0;
		}
		update_post_meta( $post_id, '_mcupr_rq_owner', $owner );
		$st = isset( $_POST['mcupr_rq_status'] ) ? sanitize_key( wp_unslash( $_POST['mcupr_rq_status'] ) ) : '';
		if ( isset( mcupr_rq_statuses()[ $st ] ) ) {
			mcupr_rq_set( $post_id, $st, isset( $_POST['mcupr_rq_msg'] ) ? wp_unslash( $_POST['mcupr_rq_msg'] ) : '', get_current_user_id(), ! empty( $_POST['mcupr_rq_mail'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
	}
);

add_filter(
	'manage_mcupr_fe_posts_columns',
	static function ( $c ) {
		$new = array();
		foreach ( $c as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['mcupr_rq_status'] = __( 'Status', 'mcupr' );
				$new['mcupr_rq_owner']  = __( 'Person who sees to it', 'mcupr' );
			}
		}
		return $new;
	},
	20
);
add_action(
	'manage_mcupr_fe_posts_custom_column',
	static function ( $col, $id ) {
		if ( ! mcupr_rq_is_entry( $id ) ) {
			return;
		}
		if ( 'mcupr_rq_status' === $col ) {
			$s = (string) get_post_meta( $id, '_mcupr_rq_status', true );
			echo '<span class="mcupr-rq-chip" data-st="' . esc_attr( $s ) . '" style="display:inline-block;padding:1px 10px;border-radius:999px;color:#fff;font-size:12px;font-weight:600;background:' . esc_attr( mcupr_rq_color( $s ) ) . '">' . esc_html( mcupr_rq_statuses()[ $s ] ?? $s ) . '</span>';
		} elseif ( 'mcupr_rq_owner' === $col ) {
			$u = get_userdata( (int) get_post_meta( $id, '_mcupr_rq_owner', true ) );
			echo $u ? esc_html( $u->display_name ) : '—';
		}
	},
	10,
	2
);
function mcupr_rq_color( $s ) {
	$c = array( 'new' => '#1d4ed8', 'review' => '#b45309', 'need' => '#6d28d9', 'approved' => '#15803d', 'rejected' => '#b91c1c', 'done' => '#0f766e' );
	return isset( $c[ $s ] ) ? $c[ $s ] : '#334155';
}
add_filter(
	'views_edit-mcupr_fe',
	static function ( $views ) {
		$out = array();
		$cur = isset( $_GET['mcupr_rq_status'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_rq_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( mcupr_rq_statuses() as $k => $l ) {
			$n = (int) ( new WP_Query( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'meta_key' => '_mcupr_rq_status', 'meta_value' => $k, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery
			$out[ 'rq_' . $k ] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_fe&mcupr_rq=1&mcupr_rq_status=' . $k ) ) . '"' . ( $cur === $k ? ' class="current"' : '' ) . '>' . esc_html( $l ) . ' <span class="count">(' . $n . ')</span></a>';
		}
		return array_merge( $views, $out );
	}
);
add_action(
	'restrict_manage_posts',
	static function ( $type ) {
		if ( 'mcupr_fe' !== $type ) {
			return;
		}
		$cur = isset( $_GET['mcupr_rq_owner'] ) ? (int) $_GET['mcupr_rq_owner'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$st  = isset( $_GET['mcupr_rq_status'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_rq_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="mcupr_rq_status"><option value="">' . esc_html__( 'Any status', 'mcupr' ) . '</option>';
		foreach ( mcupr_rq_statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select><select name="mcupr_rq_owner"><option value="">' . esc_html__( 'Anyone sees to it', 'mcupr' ) . '</option>';
		foreach ( get_users( array( 'capability' => MCUPR_RQ_CAP, 'orderby' => 'display_name', 'number' => 200, 'fields' => array( 'ID', 'display_name' ) ) ) as $u ) {
			echo '<option value="' . (int) $u->ID . '"' . selected( $cur, (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
		}
		echo '</select>';
	},
	20
);

function mcupr_rq_csv_head() {
	return array( 'Number', 'Status', 'Person who sees to it', 'Last change' );
}
function mcupr_rq_csv_cells( $id ) {
	$s   = (string) get_post_meta( $id, '_mcupr_rq_status', true );
	$u   = get_userdata( (int) get_post_meta( $id, '_mcupr_rq_owner', true ) );
	$log = get_post_meta( $id, '_mcupr_rq_log', true );
	$t   = is_array( $log ) && $log ? (int) end( $log )['t'] : 0;
	return array( (string) get_post_meta( $id, '_mcupr_rq_code', true ), mcupr_rq_statuses()[ $s ] ?? $s, $u ? $u->display_name : '', $t ? wp_date( 'Y-m-d H:i', $t ) : '' );
}

/** "Requests" is one click from the MCUPR overview (and opens the list of requests). */
add_action(
	'admin_menu',
	static function () {
		$hook = add_submenu_page( 'mcupr', __( 'Requests', 'mcupr' ), __( 'Requests', 'mcupr' ), MCUPR_RQ_CAP, 'mcupr-requests', '__return_null' );
		if ( $hook ) {
			add_action(
				'load-' . $hook,
				static function () {
					wp_safe_redirect( admin_url( 'edit.php?post_type=mcupr_fe&mcupr_rq=1' ) );
					exit;
				}
			);
		}
	}
);

/* ------------------------------------------------------------------ the student's view */

/** The requests of a user, newest first (each: id, code, form title, date, status). */
function mcupr_rq_of_user( $uid ) {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'meta_key' => '_mcupr_rq_uid', 'meta_value' => (int) $uid, 'posts_per_page' => 50, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( mcupr_rq_is_entry( $p->ID ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/** The request forms a student may start now. */
function mcupr_rq_forms_for_students() {
	$out = array();
	foreach ( get_posts( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $f ) {
		if ( mcupr_rq_is_form( $f->ID ) && 'closed' !== mcupr_fb_state( $f->ID ) ) {
			$out[] = $f;
		}
	}
	return $out;
}

/** The history of a request as the sender sees it: status, message, when – nothing about who inside the staff. */
function mcupr_rq_timeline_html( $id, $lang = '' ) {
	$log = get_post_meta( $id, '_mcupr_rq_log', true );
	$log = is_array( $log ) ? $log : array();
	$h   = '<ol class="rq-tl">';
	foreach ( $log as $i => $r ) {
		$h .= '<li' . ( $i === count( $log ) - 1 ? ' class="is-cur"' : '' ) . '><span class="rq-tl-s">' . mcupr_rq_chip( $r['s'], $lang ) . '</span> <time datetime="' . esc_attr( gmdate( 'c', (int) $r['t'] ) ) . '">' . esc_html( wp_date( 'j M Y H:i', (int) $r['t'] ) ) . '</time>' . ( '' !== $r['m'] ? '<p class="rq-tl-m">' . nl2br( esc_html( $r['m'] ) ) . '</p>' : '' ) . '</li>';
	}
	return $h . '</ol>';
}

/** "My requests" for the student portal. */
function mcupr_rq_portal_html() {
	$uid = get_current_user_id();
	$h   = '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rq = isset( $_GET['rq'] ) ? (int) $_GET['rq'] : 0;
	if ( $rq && mcupr_rq_is_form( $rq ) ) {
		return '<section class="stu-sec" id="stu-rq" aria-labelledby="rq-new-t"><h2 id="rq-new-t" class="stu-h">' . esc_html( get_the_title( $rq ) ) . '</h2><p><a href="' . esc_url( mcupr_student_portal_url() . '#stu-rq' ) . '">‹ ' . esc_html( mcupr_t( 'rq_back' ) ) . '</a></p>' . mcupr_fb_html( $rq ) . '</section>';
	}
	$h .= '<section class="stu-sec" id="stu-rq" aria-labelledby="rq-my-t"><h2 id="rq-my-t" class="stu-h">' . esc_html( mcupr_t( 'rq_my' ) ) . '</h2>';
	$forms = mcupr_current_student() ? mcupr_rq_forms_for_students() : array();
	if ( $forms ) {
		$h .= '<p class="rq-new-l">' . esc_html( mcupr_t( 'rq_choose' ) ) . '</p><ul class="rq-forms">';
		foreach ( $forms as $f ) {
			$h .= '<li><a class="btn rq-btn" href="' . esc_url( mcupr_student_portal_url( array( 'rq' => $f->ID ) ) . '#stu-rq' ) . '">' . esc_html( get_the_title( $f ) ) . '</a></li>';
		}
		$h .= '</ul>';
	}
	$mine = mcupr_rq_of_user( $uid );
	if ( ! $mine ) {
		$h .= '<p class="text-muted">' . esc_html( mcupr_t( 'rq_none' ) ) . '</p></section>';
		return $h;
	}
	$h .= '<ul class="rq-list">';
	foreach ( $mine as $p ) {
		$code = (string) get_post_meta( $p->ID, '_mcupr_rq_code', true );
		$st   = (string) get_post_meta( $p->ID, '_mcupr_rq_status', true );
		$form = (int) get_post_meta( $p->ID, '_mcupr_fe_form', true );
		$h   .= '<li class="rq-item"><details><summary><span class="rq-t">' . esc_html( get_the_title( $form ) ) . '</span><span class="rq-m">' . esc_html( $code ) . ' · ' . esc_html( wp_date( 'j M Y', strtotime( $p->post_date_gmt . ' UTC' ) ) ) . '</span>' . mcupr_rq_chip( $st ) . '</summary>' . mcupr_rq_timeline_html( $p->ID );
		$files = get_post_meta( $p->ID, '_mcupr_rq_files', true );
		if ( is_array( $files ) && $files ) {
			$h .= '<p class="rq-files"><b>' . esc_html( mcupr_t( 'rq_files' ) ) . ':</b> ';
			foreach ( $files as $i => $f ) {
				$h .= '<a href="' . esc_url( mcupr_rq_file_url( $p->ID, $i ) ) . '">' . esc_html( $f['n'] ) . '</a> ';
			}
			$h .= '</p>';
		}
		$h .= '</details></li>';
	}
	return $h . '</ul></section>';
}

/* ------------------------------------------------------------------ following a request (anyone, with the number and the e-mail / student ID) */

function mcupr_rq_track_url( $args = array() ) {
	if ( mcupr_plain_mode() ) {
		return add_query_arg( array_merge( array( 'mcupr_request' => 1 ), $args ), home_url( '/' ) );
	}
	return add_query_arg( $args, home_url( '/request/' ) );
}

/** The request that goes with this number AND this e-mail address or student ID (or 0). The two must match: the number alone shows nothing. */
function mcupr_rq_find( $code, $who ) {
	$code = strtoupper( trim( (string) $code ) );
	$who  = strtolower( trim( (string) $who ) );
	if ( ! preg_match( '/^REQ-\d{4}-\d{5}$/', $code ) || '' === $who ) {
		return 0;
	}
	$p = get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'meta_key' => '_mcupr_rq_code', 'meta_value' => $code, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( ! $p ) {
		return 0;
	}
	$id = (int) $p[0];
	if ( hash_equals( strtolower( (string) get_post_meta( $id, '_mcupr_rq_email', true ) ), $who ) || hash_equals( (string) get_post_meta( $id, '_mcupr_rq_sidcode', true ), $who ) ) {
		return $id;
	}
	return 0;
}

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^request/?$', 'index.php?mcupr_request=1', 'top' );
		if ( get_option( 'mcupr_rewrite_requests' ) !== MCUPR_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'mcupr_rewrite_requests', MCUPR_VERSION );
		}
	},
	7
);
add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'mcupr_request';
		return $v;
	}
);
add_filter(
	'pre_handle_404',
	static function ( $pre, $q ) {
		return $q->get( 'mcupr_request' ) ? true : $pre;
	},
	10,
	2
);
add_filter(
	'redirect_canonical',
	static function ( $url ) {
		return get_query_var( 'mcupr_request' ) ? false : $url;
	}
);
add_filter(
	'template_include',
	static function ( $t ) {
		if ( get_query_var( 'mcupr_request' ) ) {
			status_header( 200 );
			nocache_headers();
			return get_theme_file_path( 'request-track.php' );
		}
		return $t;
	}
);
add_filter(
	'wp_robots',
	static function ( $r ) {
		if ( get_query_var( 'mcupr_request' ) ) {
			$r['noindex']  = true;
			$r['nofollow'] = true;
		}
		return $r;
	}
);
add_filter(
	'document_title_parts',
	static function ( $p ) {
		if ( get_query_var( 'mcupr_request' ) ) {
			$p['title'] = mcupr_t( 'rq_track_t' );
		}
		return $p;
	}
);

