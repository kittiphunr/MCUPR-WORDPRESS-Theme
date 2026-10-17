<?php
/**
 * Student data care: (1) the activity log (who imported, exported, changed or deleted student data, when), (2) the e-mail
 * test, the last sending problem and the "link without e-mail" fallback, (3) the check that the pretty links
 * (/student/, /office/…) really work.
 *
 * The log is a hidden post type (one row per event, no IP address, no student names – only the record and student ID),
 * cleared after the number of days set in the student portal settings (default 365).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_AUDIT = 'mcupr_audit';

/* ================================================================== 1. the activity log */

add_action(
	'init',
	static function () {
		register_post_type(
			MCUPR_AUDIT,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
			)
		);
		if ( ! wp_next_scheduled( 'mcupr_audit_purge' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mcupr_audit_purge' );
		}
	},
	5
);

/** What the log can say (key => label). */
function mcupr_audit_actions() {
	return array(
		'import'   => __( 'Imported a file', 'mcupr' ),
		'export'   => __( 'Exported the student list', 'mcupr' ),
		'errors'   => __( 'Downloaded the rows that were not imported', 'mcupr' ),
		'bulk'     => __( 'Changed several students at once', 'mcupr' ),
		'edit'     => __( 'Edited a student record', 'mcupr' ),
		'delete'   => __( 'Deleted a student record', 'mcupr' ),
		'make'     => __( 'Made a login account', 'mcupr' ),
		'send'     => __( 'Sent the link to set a password', 'mcupr' ),
		'link'     => __( 'Showed the link to set a password', 'mcupr' ),
		'drop'     => __( 'Deleted a login account', 'mcupr' ),
		'settings' => __( 'Changed the student portal settings', 'mcupr' ),
		'profileview' => __( 'Opened the details from the central file of a student', 'mcupr' ),
		'cardset'  => __( 'Set or removed the card code or national ID number of a student', 'mcupr' ),
		'sso'      => __( 'A student signed in with the Google account', 'mcupr' ),
		'ssofail'  => __( 'A sign-in with the Google account was refused', 'mcupr' ),
		'sgimport' => __( 'Imported schedule or grades', 'mcupr' ),
		'sgdrop'   => __( 'Deleted the schedule or grades of a term', 'mcupr' ),
		'sgview'   => __( 'Opened the schedule and grades of a student', 'mcupr' ),
		'mail'     => __( 'Started an e-mail to students', 'mcupr' ),
		'maildone' => __( 'Finished sending an e-mail to students', 'mcupr' ),
		'unsub'    => __( 'A student stopped receiving announcements', 'mcupr' ),
		'resub'    => __( 'A student receives announcements again', 'mcupr' ),
	);
}

/**
 * Write one event. $data: sid (student ID), post (record ID), n (how many), note (short text, no personal data).
 * Never throws; a failure to log must not stop the work.
 */
function mcupr_audit( $action, $data = array() ) {
	if ( ! post_type_exists( MCUPR_AUDIT ) ) {
		return 0;
	}
	$u  = wp_get_current_user();
	$id = wp_insert_post(
		array(
			'post_type'   => MCUPR_AUDIT,
			'post_status' => 'publish',
			'post_title'  => sanitize_key( $action ),
			'post_name'   => wp_generate_uuid4(), // unique at once: WordPress otherwise tries delete-2, delete-3 … one query each
			'post_author' => (int) $u->ID,
		),
		false,
		false
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	$data = array(
		'sid'  => isset( $data['sid'] ) ? sanitize_text_field( (string) $data['sid'] ) : '',
		'post' => isset( $data['post'] ) ? (int) $data['post'] : 0,
		'n'    => isset( $data['n'] ) ? (int) $data['n'] : 0,
		'note' => isset( $data['note'] ) ? sanitize_text_field( (string) $data['note'] ) : '',
		'by'   => $u->ID ? $u->user_login : 'system',
	);
	update_post_meta( $id, '_mcupr_a', wp_slash( wp_json_encode( $data ) ) );
	return (int) $id;
}

/** Days to keep the log (30 … 3650). */
function mcupr_audit_days() {
	$o = get_option( 'mcupr_stu_opts', array() );
	$d = is_array( $o ) && isset( $o['audit_days'] ) ? (int) $o['audit_days'] : 365;
	return max( 30, min( 3650, $d ) );
}

/** Delete the events older than the retention; at most $limit per call. Returns the number deleted. */
function mcupr_audit_purge( $limit = 500 ) {
	$ids = get_posts(
		array(
			'post_type'      => MCUPR_AUDIT,
			'post_status'    => 'any',
			'posts_per_page' => max( 1, (int) $limit ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - mcupr_audit_days() * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
		)
	);
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	return count( $ids );
}
add_action( 'mcupr_audit_purge', 'mcupr_audit_purge' );

/* ---- where the events come from (the student code calls these hooks) */

add_action(
	'mcupr_student_event',
	static function ( $action, $data = array() ) {
		mcupr_audit( $action, is_array( $data ) ? $data : array() );
	},
	10,
	2
);

/** A student record deleted (any way). */
add_action(
	'before_delete_post',
	static function ( $id ) {
		if ( defined( 'MCUPR_STU' ) && MCUPR_STU === get_post_type( $id ) ) {
			mcupr_audit( 'delete', array( 'post' => $id, 'sid' => (string) get_post_meta( $id, '_mcupr_s_sid', true ) ) );
		}
	},
	5
);

/** A record edited by hand on its screen (not by the import: that one is logged as a whole). */
add_action(
	'save_post_mcupr_student',
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['mcupr_student_nonce'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		mcupr_audit( 'edit', array( 'post' => $post_id, 'sid' => (string) get_post_meta( $post_id, '_mcupr_s_sid', true ) ) );
	},
	99,
	2
);

/* ---- the page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Student activity log', 'mcupr' ), __( 'Student activity log', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-log', 'mcupr_audit_page' );
	}
);

function mcupr_audit_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	$actions = mcupr_audit_actions();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$sel   = isset( $_GET['act'] ) && isset( $actions[ sanitize_key( wp_unslash( $_GET['act'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['act'] ) ) : '';
	$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	$where = static function ( $w ) use ( $sel ) {
		global $wpdb;
		return $w . $wpdb->prepare( " AND {$wpdb->posts}.post_title = %s", $sel ); // phpcs:ignore WordPress.DB.PreparedSQL
	};
	if ( '' !== $sel ) {
		add_filter( 'posts_where', $where );
	}
	$q = new WP_Query( array( 'post_type' => MCUPR_AUDIT, 'post_status' => 'any', 'posts_per_page' => 30, 'paged' => $paged, 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => false ) );
	remove_filter( 'posts_where', $where );
	echo '<div class="wrap"><h1>' . esc_html__( 'Student activity log', 'mcupr' ) . '</h1>';
	/* translators: %d: days */
	echo '<p style="max-width:56rem">' . esc_html( sprintf( __( 'Who imported, exported, changed or deleted student data, and when. The log holds no student names and no IP addresses, and is cleared after %d days (set in the student portal settings).', 'mcupr' ), mcupr_audit_days() ) ) . '</p>';
	echo '<form method="get" style="margin:.5rem 0 1rem"><input type="hidden" name="page" value="mcupr-student-log"><label for="mcupr-act" class="screen-reader-text">' . esc_html__( 'Show', 'mcupr' ) . '</label><select id="mcupr-act" name="act"><option value="">' . esc_html__( 'All events', 'mcupr' ) . '</option>';
	foreach ( $actions as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $sel, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select> <button class="button">' . esc_html__( 'Filter', 'mcupr' ) . '</button></form>';
	if ( ! $q->have_posts() ) {
		echo '<p>' . esc_html__( 'Nothing has been logged yet.', 'mcupr' ) . '</p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'When', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Who', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'What', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Student ID', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Details', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( $q->posts as $p ) {
		$d = json_decode( (string) get_post_meta( $p->ID, '_mcupr_a', true ), true );
		$d = is_array( $d ) ? $d : array();
		$l = isset( $actions[ $p->post_title ] ) ? $actions[ $p->post_title ] : $p->post_title;
		$s = isset( $d['sid'] ) ? $d['sid'] : '';
		if ( $s && ! empty( $d['post'] ) && get_post_type( $d['post'] ) === MCUPR_STU ) {
			$s = '<a href="' . esc_url( get_edit_post_link( (int) $d['post'] ) ) . '">' . esc_html( $s ) . '</a>';
		} else {
			$s = esc_html( $s );
		}
		$det = array();
		if ( ! empty( $d['n'] ) ) {
			/* translators: %d: how many students */
			$det[] = sprintf( __( '%d student(s)', 'mcupr' ), (int) $d['n'] );
		}
		if ( ! empty( $d['note'] ) ) {
			$det[] = $d['note'];
		}
		echo '<tr><td>' . esc_html( get_date_from_gmt( $p->post_date_gmt, 'Y-m-d H:i' ) ) . '</td><td>' . esc_html( isset( $d['by'] ) ? $d['by'] : '' ) . '</td><td>' . esc_html( $l ) . '</td><td>' . $s . '</td><td>' . esc_html( implode( ' · ', $det ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
	$links = paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => max( 1, (int) $q->max_num_pages ) ) );
	if ( $links ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
	}
	echo '</div>';
}

/* ================================================================== 2. e-mail: test, last problem, link without e-mail */

/** wp_mail() that remembers why it failed. Returns true / false. */
function mcupr_stu_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$why = '';
	$cb  = static function ( $err ) use ( &$why ) {
		$why = $err instanceof WP_Error ? $err->get_error_message() : '';
	};
	add_action( 'wp_mail_failed', $cb );
	$ok = wp_mail( $to, $subject, $body, $headers, $attachments );
	remove_action( 'wp_mail_failed', $cb );
	if ( $ok ) {
		delete_option( 'mcupr_stu_mail_last' );
	} else {
		update_option( 'mcupr_stu_mail_last', array( 't' => time(), 'why' => '' !== $why ? mb_substr( $why, 0, 300 ) : 'wp_mail() returned false' ), false );
	}
	return (bool) $ok;
}

/** The last sending problem (array t, why) or empty. */
function mcupr_stu_mail_problem() {
	$p = get_option( 'mcupr_stu_mail_last', array() );
	return is_array( $p ) && ! empty( $p['t'] ) ? $p : array();
}

/** The red note: the last e-mail did not go out. */
function mcupr_stu_mail_notice() {
	$p = mcupr_stu_mail_problem();
	if ( ! $p ) {
		return '';
	}
	/* translators: 1: date, 2: the reason the mail system gave */
	return '<div class="notice notice-error"><p><strong>' . esc_html__( 'The last e-mail to a student could not be sent.', 'mcupr' ) . '</strong> ' . esc_html( sprintf( __( '%1$s – %2$s', 'mcupr' ), wp_date( 'Y-m-d H:i', (int) $p['t'] ), $p['why'] ) ) . ' ' . esc_html__( 'Check the e-mail settings of the website (SMTP), then send a test e-mail from the student portal settings. Meanwhile you can show the link on the student’s record and give it to the student yourself.', 'mcupr' ) . '</p></div>';
}

add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( $s && ( 'edit-mcupr_student' === $s->id || 'mcupr_student' === $s->id ) && current_user_can( MCUPR_STU_CAP ) ) {
			echo mcupr_stu_mail_notice(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
);

/** The test e-mail (settings page). */
add_action(
	'admin_post_mcupr_stu_mailtest',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! isset( $_POST['mcupr_stu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_stu_nonce'] ) ), 'mcupr_stu_mailtest' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-settings&mt=bad' ) );
			exit;
		}
		$org = mcupr_org_name();
		$ok  = mcupr_stu_mail( $to, sprintf( /* translators: %s: site name */ __( 'Test e-mail from %s', 'mcupr' ), $org ), __( 'This is a test e-mail from the student portal settings. If you can read it, the e-mail links for students will arrive too.', 'mcupr' ) );
		mcupr_audit( 'settings', array( 'note' => $ok ? 'test e-mail sent' : 'test e-mail failed' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-settings&mt=' . ( $ok ? 'ok' : 'fail' ) ) );
		exit;
	}
);

/** The link to set a password, shown to the registrar instead of being e-mailed. Kept 5 minutes, for that user only. */
add_action(
	'admin_post_mcupr_stu_link',
	static function () {
		$id = isset( $_GET['sid'] ) ? absint( $_GET['sid'] ) : 0;
		if ( ! $id || ! current_user_can( MCUPR_STU_CAP ) || ! check_admin_referer( 'mcupr_stu_mcupr_stu_link' . $id ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$d    = mcupr_student_data( $id );
		$user = $d['user'] && mcupr_is_student_user( $d['user'] ) ? get_userdata( $d['user'] ) : null;
		$key  = $user ? get_password_reset_key( $user ) : new WP_Error( 'none', 'no account' );
		if ( is_wp_error( $key ) ) {
			wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'mcs_err' => 1, 'mcs_done' => 0 ), admin_url( 'post.php' ) ) );
			exit;
		}
		set_transient( 'mcupr_stu_link_' . get_current_user_id() . '_' . $id, network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' ), 5 * MINUTE_IN_SECONDS );
		mcupr_audit( 'link', array( 'post' => $id, 'sid' => $d['sid'] ) );
		wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'mcs_link' => 1 ), admin_url( 'post.php' ) ) );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'mcupr_student' !== $s->id || empty( $_GET['mcs_link'] ) || empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id  = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$url = get_transient( 'mcupr_stu_link_' . get_current_user_id() . '_' . $id );
		if ( ! $url ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Link to set a password', 'mcupr' ) . '</strong> – ' . esc_html__( 'give it only to this student (it works once and expires). It is shown for five minutes.', 'mcupr' ) . '</p><p><label class="screen-reader-text" for="mcs-link">' . esc_html__( 'Link to set a password', 'mcupr' ) . '</label><input id="mcs-link" type="text" readonly class="large-text code" value="' . esc_attr( $url ) . '" onfocus="this.select()"></p></div>';
	}
);

/* ================================================================== 3. do the pretty links work? */

/**
 * 'plain' (permalinks are Plain: the theme uses ?mcupr_student=1 style links), 'missing' (pretty permalinks but the theme's
 * rules were never saved: /student/ would be a 404) or 'ok'.
 */
function mcupr_rewrite_status() {
	if ( ! get_option( 'permalink_structure' ) ) {
		return 'plain';
	}
	$rules = get_option( 'rewrite_rules' );
	if ( ! is_array( $rules ) || ! $rules ) {
		return 'missing';
	}
	foreach ( array( '^student/?$' ) as $k ) {
		if ( ! isset( $rules[ $k ] ) ) {
			return 'missing';
		}
	}
	return 'ok';
}

add_action(
	'admin_post_mcupr_rewrite_flush',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mcupr_rewrite_flush' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		flush_rewrite_rules( true );
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'mcupr_rw', 'done', $back ? $back : admin_url( 'admin.php?page=mcupr' ) ) );
		exit;
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = get_current_screen();
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'mcupr' ) && ! ( $s && 'edit-mcupr_student' === $s->id ) ) {
			return;
		}
		if ( ! empty( $_GET['mcupr_rw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The link rules were rebuilt.', 'mcupr' ) . '</p></div>';
		}
		$st = mcupr_rewrite_status();
		if ( 'ok' === $st ) {
			return;
		}
		if ( 'plain' === $st ) {
			return; // Plain permalinks are a supported setting (some servers allow nothing else): the links adapt, nothing to tell
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'The links /student/ and /office/… are not saved on the server yet, so they would show “Not Found”.', 'mcupr' ) . ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_rewrite_flush' ), 'mcupr_rewrite_flush' ) ) . '">' . esc_html__( 'Rebuild the link rules', 'mcupr' ) . '</a> ' . esc_html__( 'If it still shows “Not Found”, the server may not allow WordPress to write the .htaccess file: open Settings → Permalinks and copy the rules shown there.', 'mcupr' ) . '</p></div>';
	}
);
