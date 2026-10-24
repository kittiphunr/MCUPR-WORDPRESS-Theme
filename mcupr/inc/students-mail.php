<?php
/**
 * E-mail to students in groups (MCUPR › Students › E-mail to students).
 *
 *  - Choose who: the same ticking as for students-only content (levels, years, faculties / majors); only students who are
 *    studying, with a valid e-mail address, who have not asked to stop, once per address.
 *  - Write the message ({name} and {student_id} are replaced), a reply-to address, an attachment (PDF, JPG, PNG), save it as a
 *    template, send a test to yourself, look at the count and a sample, tick "I have checked", send now or at a time.
 *  - Sent one by one (nobody sees the others), in the background, at most N per hour (the mail system of a university
 *    usually allows only so many): the page shows the progress and WP-Cron carries on when the page is closed.
 *  - Every e-mail ends with the organisation, the portal and a link "I do not want announcements" (asks first, then stops them
 *    for that student; the student can start them again). Activation and request e-mails are not announcements and always go.
 *  - The log of the student activity says who sent what to how many; the text is kept only as long as the log is.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_MAILJOB = 'mcupr_mailjob';

/* ------------------------------------------------------------------ the type and the settings */

add_action(
	'init',
	static function () {
		register_post_type(
			MCUPR_MAILJOB,
			array(
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title', 'editor' ),
				'can_export'          => false, // Tools -> Export must not put personal data in a plain file with no record of it: the theme's own audited exports are the way out
				'capability_type'     => array( 'mcupr_student', 'mcupr_students' ),
				'map_meta_cap'        => true,
			)
		);
	},
	5
);

/** How many e-mails an hour the mail system may be asked to send (0 = no limit). */
function mcupr_mail_cap() {
	$o = get_option( 'mcupr_mail_opts', array() );
	return is_array( $o ) && isset( $o['cap'] ) ? max( 0, min( 5000, (int) $o['cap'] ) ) : 100;
}

/** How many more may go out in this hour. */
function mcupr_mail_hour_left() {
	$cap = mcupr_mail_cap();
	if ( 0 === $cap ) {
		return PHP_INT_MAX;
	}
	$h = get_option( 'mcupr_mail_hour', array() );
	$k = (int) floor( time() / HOUR_IN_SECONDS );
	$n = is_array( $h ) && isset( $h['k'], $h['n'] ) && (int) $h['k'] === $k ? (int) $h['n'] : 0;
	return max( 0, $cap - $n );
}
function mcupr_mail_hour_add( $n = 1 ) {
	$h = get_option( 'mcupr_mail_hour', array() );
	$k = (int) floor( time() / HOUR_IN_SECONDS );
	$c = is_array( $h ) && isset( $h['k'], $h['n'] ) && (int) $h['k'] === $k ? (int) $h['n'] : 0;
	update_option( 'mcupr_mail_hour', array( 'k' => $k, 'n' => $c + (int) $n ), false );
}

function mcupr_mail_statuses() {
	return array(
		'scheduled' => __( 'Waiting for its time', 'mcupr' ),
		'running'   => __( 'Sending', 'mcupr' ),
		'done'      => __( 'Done', 'mcupr' ),
		'cancelled' => __( 'Stopped', 'mcupr' ),
	);
}

/* ------------------------------------------------------------------ who: the recipients of some rules */

/**
 * The students that match the rules (levels, years, fac, maj as ticked; empty = all): only those studying.
 * Returns ids (valid e-mail, not stopped, once per address) and the numbers left out.
 */
function mcupr_mail_recipients( $rules ) {
	global $wpdb;
	$rules = array_merge( array( 'levels' => array(), 'years' => array(), 'fac' => array(), 'maj' => array() ), is_array( $rules ) ? $rules : array() );
	$nr    = mcupr_aud_rules_norm( $rules );
	$sql   = "SELECT p.ID AS id, l.meta_value AS lvl, y.meta_value AS yr, f.meta_value AS fac, m.meta_value AS maj, e.meta_value AS email, n.meta_value AS nomail
		FROM {$wpdb->posts} p
		JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_mcupr_s_status' AND s.meta_value = 'studying'
		LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_mcupr_s_level'
		LEFT JOIN {$wpdb->postmeta} y ON y.post_id = p.ID AND y.meta_key = '_mcupr_s_year'
		LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_mcupr_s_faculty'
		LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mcupr_s_major'
		LEFT JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_mcupr_s_email'
		LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = '_mcupr_s_nomail'
		WHERE p.post_type = %s AND p.post_status IN ('publish','private','draft')
		ORDER BY p.ID";
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql, MCUPR_STU ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$out   = array( 'ids' => array(), 'matched' => 0, 'no_email' => 0, 'stopped' => 0, 'dup' => 0 );
	$seen  = array();
	foreach ( (array) $rows as $r ) {
		$fac = mcupr_aud_norm( $r->fac );
		$v   = array( 'level' => (string) $r->lvl, 'year' => mcupr_aud_year_key( $r->yr ), 'fac' => $fac, 'maj' => $fac . MCUPR_AUD_SEP . mcupr_aud_norm( $r->maj ) );
		if ( ! mcupr_aud_matches( $nr, $v ) ) {
			continue;
		}
		$out['matched']++;
		$mail = strtolower( trim( (string) $r->email ) );
		if ( '' === $mail || ! is_email( $mail ) ) {
			$out['no_email']++;
		} elseif ( ! empty( $r->nomail ) ) {
			$out['stopped']++;
		} elseif ( isset( $seen[ $mail ] ) ) {
			$out['dup']++;
		} else {
			$seen[ $mail ] = 1;
			$out['ids'][]  = (int) $r->id;
		}
	}
	return $out;
}

/* ------------------------------------------------------------------ the message */

function mcupr_mail_unsub_token( $sid ) {
	$mail = strtolower( (string) get_post_meta( $sid, '_mcupr_s_email', true ) );
	return (int) $sid . '.' . substr( hash_hmac( 'sha256', 'unsub|' . (int) $sid . '|' . $mail, wp_salt( 'auth' ) ), 0, 32 );
}
/** The student a link belongs to (0 when the link is not valid any more, e.g. the e-mail address was changed). */
function mcupr_mail_unsub_check( $token ) {
	if ( ! preg_match( '/^(\d+)\.([a-f0-9]{32})$/', (string) $token, $m ) || MCUPR_STU !== get_post_type( (int) $m[1] ) ) {
		return 0;
	}
	return hash_equals( mcupr_mail_unsub_token( (int) $m[1] ), (string) $token ) ? (int) $m[1] : 0;
}
function mcupr_mail_unsub_url( $sid ) {
	return add_query_arg( 'mcupr_unsub', mcupr_mail_unsub_token( $sid ), home_url( '/' ) );
}

/** The text sent to one student: {name} and {student_id} filled in, the closing lines added. */
function mcupr_mail_text( $body, $sid ) {
	$d    = mcupr_student_data( $sid );
	$org  = mcupr_org_name();
	$text = str_replace( array( '{name}', '{student_id}' ), array( $d['name'], $d['sid'] ), (string) $body );
	$text .= "\n\n----\n" . sprintf( mcupr_t( 'mail_footer', 'th' ), $org ) . "\n" . sprintf( mcupr_t( 'mail_footer', 'en' ), $org ) . "\n" . mcupr_student_portal_url();
	$text .= "\n\n" . mcupr_t( 'mail_unsub', 'th' ) . ' / ' . mcupr_t( 'mail_unsub', 'en' ) . "\n" . mcupr_mail_unsub_url( $sid );
	return $text;
}
function mcupr_mail_headers( $reply, $sid ) {
	$h = array( 'List-Unsubscribe: <' . mcupr_mail_unsub_url( $sid ) . '>' );
	if ( '' !== $reply && is_email( $reply ) ) {
		$h[] = 'Reply-To: ' . $reply;
	}
	return $h;
}

/* ------------------------------------------------------------------ the attachment */

function mcupr_mail_dir( $token = '' ) {
	return '' === $token ? mcupr_private_dir( 'mail' ) : mcupr_private_token_dir( 'mail', $token );
}
function mcupr_mail_att_max() {
	return (int) min( 2 * MB_IN_BYTES, wp_max_upload_size() );
}
/** Check one uploaded file ($_FILES entry): array( file, error ) – file = tmp, name, size, mime. */
function mcupr_mail_check_att( $u ) {
	if ( ! is_array( $u ) || ! isset( $u['error'] ) || UPLOAD_ERR_NO_FILE === (int) $u['error'] ) {
		return array( null, '' );
	}
	if ( in_array( (int) $u['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
		return array( null, 'size' );
	}
	if ( UPLOAD_ERR_OK !== (int) $u['error'] || empty( $u['tmp_name'] ) ) {
		return array( null, 'fail' );
	}
	$size = isset( $u['size'] ) ? (int) $u['size'] : 0;
	if ( $size < 1 || $size > mcupr_mail_att_max() || filesize( $u['tmp_name'] ) > mcupr_mail_att_max() ) {
		return array( null, 'size' );
	}
	$name = sanitize_file_name( wp_basename( (string) ( $u['name'] ?? '' ) ) );
	$chk  = wp_check_filetype_and_ext( $u['tmp_name'], $name, array( 'pdf' => 'application/pdf', 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) );
	if ( empty( $chk['ext'] ) || empty( $chk['type'] ) ) {
		return array( null, 'type' );
	}
	return array( array( 'tmp' => $u['tmp_name'], 'name' => mb_substr( $name, 0, 120 ), 'size' => $size, 'mime' => $chk['type'] ), '' );
}
/** Keep a checked file; returns array( k, n, f, s ) or null. */
function mcupr_mail_keep_att( $f ) {
	$token = bin2hex( random_bytes( 12 ) );
	$dir   = mcupr_mail_dir( $token );
	wp_mkdir_p( $dir );
	file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$stored = wp_unique_filename( $dir, $f['name'] );
	$moved  = is_uploaded_file( $f['tmp'] ) ? move_uploaded_file( $f['tmp'], $dir . '/' . $stored ) : ( apply_filters( 'mcupr_mail_accept_non_upload', false ) && copy( $f['tmp'], $dir . '/' . $stored ) );
	return $moved ? array( 'k' => $token, 'n' => $f['name'], 'f' => $stored, 's' => (int) $f['size'] ) : null;
}
function mcupr_mail_att_path( $a ) {
	return is_array( $a ) && ! empty( $a['k'] ) ? mcupr_mail_dir( $a['k'] ) . '/' . wp_basename( (string) $a['f'] ) : '';
}
function mcupr_mail_att_delete( $a ) {
	if ( ! is_array( $a ) || empty( $a['k'] ) ) {
		return;
	}
	$dir = mcupr_mail_dir( $a['k'] );
	foreach ( (array) glob( $dir . '/*' ) as $x ) {
		if ( is_file( $x ) ) {
			wp_delete_file( $x );
		}
	}
	if ( is_dir( $dir ) ) {
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

/* ------------------------------------------------------------------ the jobs */

function mcupr_mail_job( $id ) {
	if ( MCUPR_MAILJOB !== get_post_type( $id ) ) {
		return null;
	}
	$m = get_post_meta( $id, '_mcupr_mj', true );
	$m = array_merge( array( 'status' => 'running', 'at' => 0, 'reply' => '', 'recips' => array(), 'pos' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'failed_ids' => array(), 'att' => null, 'rules' => array(), 'by' => 0, 'done' => 0 ), is_array( $m ) ? $m : array() );
	return array_merge( $m, array( 'id' => (int) $id, 'subject' => (string) get_post_field( 'post_title', $id ), 'body' => (string) get_post_field( 'post_content', $id ), 'total' => count( (array) $m['recips'] ) ) );
}
function mcupr_mail_job_put( $job ) {
	$keep = array( 'status', 'at', 'reply', 'recips', 'pos', 'sent', 'failed', 'skipped', 'failed_ids', 'att', 'rules', 'by', 'done' );
	update_post_meta( $job['id'], '_mcupr_mj', array_intersect_key( $job, array_flip( $keep ) ) );
}

/** Make a job (status "running", or "scheduled" for a time in the future) and let WP-Cron carry on. */
function mcupr_mail_create( $subject, $body, $reply, $rules, $ids, $att, $at = 0 ) {
	$id = wp_insert_post( array( 'post_type' => MCUPR_MAILJOB, 'post_status' => 'private', 'post_title' => mb_substr( sanitize_text_field( $subject ), 0, 150 ), 'post_content' => mb_substr( sanitize_textarea_field( $body ), 0, 5000 ), 'post_name' => wp_generate_uuid4() ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	$job = array( 'id' => $id, 'status' => $at > time() + 30 ? 'scheduled' : 'running', 'at' => (int) $at, 'reply' => is_email( $reply ) ? $reply : '', 'recips' => array_values( array_map( 'intval', $ids ) ), 'pos' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'failed_ids' => array(), 'att' => $att, 'rules' => $rules, 'by' => get_current_user_id(), 'done' => 0 );
	mcupr_mail_job_put( $job );
	mcupr_audit( 'mail', array( 'n' => count( $ids ), 'note' => mb_substr( sanitize_text_field( $subject ), 0, 80 ) ) );
	mcupr_mail_schedule( $job['at'] > time() ? $job['at'] : time() + 20 );
	return (int) $id;
}

function mcupr_mail_schedule( $when ) {
	if ( ! wp_next_scheduled( 'mcupr_mail_tick' ) ) {
		wp_schedule_single_event( max( time() + 5, (int) $when ), 'mcupr_mail_tick' );
	}
}

/**
 * Send the next e-mails of a job (at most $limit, for at most $seconds, never above the hourly limit).
 * Returns the job (with 'wait' = true when it is held back by the hourly limit).
 */
function mcupr_mail_run( $id, $limit = 20, $seconds = 20 ) {
	$job = mcupr_mail_job( $id );
	if ( ! $job ) {
		return null;
	}
	if ( 'scheduled' === $job['status'] && $job['at'] <= time() ) {
		$job['status'] = 'running';
		mcupr_mail_job_put( $job );
	}
	$job['wait'] = false;
	if ( 'running' !== $job['status'] ) {
		return $job;
	}
	$lock = 'mcupr_mail_lock_' . $id;
	if ( get_transient( $lock ) ) {
		return $job; // another request is sending this one
	}
	set_transient( $lock, 1, MINUTE_IN_SECONDS );
	$deadline = time() + max( 1, (int) $seconds );
	$left     = mcupr_mail_hour_left();
	$n        = 0;
	$att      = mcupr_mail_att_path( $job['att'] );
	$att      = '' !== $att && is_readable( $att ) ? array( $att ) : array();
	while ( $job['pos'] < $job['total'] && $n < $limit && time() < $deadline ) {
		$sid = (int) $job['recips'][ $job['pos'] ];
		// still a student who studies, with an address, who has not asked to stop?
		$mail = strtolower( trim( (string) get_post_meta( $sid, '_mcupr_s_email', true ) ) );
		if ( 'studying' !== get_post_meta( $sid, '_mcupr_s_status', true ) || ! is_email( $mail ) || get_post_meta( $sid, '_mcupr_s_nomail', true ) ) {
			$job['skipped']++;
			$job['pos']++;
			continue;
		}
		if ( $left <= 0 ) {
			$job['wait'] = true;
			break;
		}
		$ok = mcupr_stu_mail( $mail, $job['subject'], mcupr_mail_text( $job['body'], $sid ), mcupr_mail_headers( $job['reply'], $sid ), $att );
		$job['pos']++;
		$n++;
		$left--;
		mcupr_mail_hour_add( 1 );
		if ( $ok ) {
			$job['sent']++;
		} else {
			$job['failed']++;
			if ( count( $job['failed_ids'] ) < 200 ) {
				$job['failed_ids'][] = $sid;
			}
		}
	}
	if ( $job['pos'] >= $job['total'] ) {
		$job['status'] = 'done';
		$job['done']   = time();
		mcupr_mail_att_delete( $job['att'] );
		$job['att'] = null;
	}
	mcupr_mail_job_put( $job );
	delete_transient( $lock );
	if ( 'done' === $job['status'] ) {
		do_action( 'mcupr_mail_done', $job );
	}
	return $job;
}

function mcupr_mail_cancel( $id ) {
	$job = mcupr_mail_job( $id );
	if ( ! $job || ! in_array( $job['status'], array( 'running', 'scheduled' ), true ) ) {
		return false;
	}
	$job['status'] = 'cancelled';
	$job['done']   = time();
	mcupr_mail_att_delete( $job['att'] );
	$job['att'] = null;
	mcupr_mail_job_put( $job );
	return true;
}

/** WP-Cron: carry on with the jobs that are waiting or sending, then ask to be called again while something is left. */
add_action(
	'mcupr_mail_tick',
	static function () {
		$again = 0;
		foreach ( get_posts( array( 'post_type' => MCUPR_MAILJOB, 'post_status' => 'private', 'posts_per_page' => 20, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $id ) {
			$job = mcupr_mail_job( $id );
			if ( ! $job || ! in_array( $job['status'], array( 'running', 'scheduled' ), true ) ) {
				continue;
			}
			if ( 'scheduled' === $job['status'] && $job['at'] > time() ) {
				$again = $again ? min( $again, $job['at'] ) : $job['at'];
				continue;
			}
			$job = mcupr_mail_run( $id, 20, 20 );
			if ( $job && in_array( $job['status'], array( 'running', 'scheduled' ), true ) ) {
				$again = $again ? min( $again, time() + ( ! empty( $job['wait'] ) ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS ) ) : time() + ( ! empty( $job['wait'] ) ? 5 * MINUTE_IN_SECONDS : MINUTE_IN_SECONDS );
			}
		}
		if ( $again ) {
			wp_schedule_single_event( max( time() + 30, $again ), 'mcupr_mail_tick' );
		}
	}
);

add_action(
	'mcupr_mail_done',
	static function ( $job ) {
		mcupr_audit( 'maildone', array( 'n' => (int) $job['sent'], 'note' => mb_substr( sanitize_text_field( $job['subject'] ), 0, 60 ) ) );
	}
);

/** Finished jobs and forgotten files go with the log (same number of days). */
add_action(
	'mcupr_audit_purge',
	static function () {
		$old = get_posts( array( 'post_type' => MCUPR_MAILJOB, 'post_status' => 'any', 'posts_per_page' => 100, 'fields' => 'ids', 'no_found_rows' => true, 'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - mcupr_audit_days() * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ) ) );
		foreach ( $old as $id ) {
			$job = mcupr_mail_job( $id );
			if ( $job ) {
				mcupr_mail_att_delete( $job['att'] );
			}
			wp_delete_post( $id, true );
		}
		// a file that was chosen and then never used
		$keep = array();
		foreach ( get_posts( array( 'post_type' => MCUPR_MAILJOB, 'post_status' => 'any', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) {
			$j = mcupr_mail_job( $id );
			if ( $j && ! empty( $j['att']['k'] ) ) {
				$keep[ $j['att']['k'] ] = 1;
			}
		}
		$sweep = array();
		foreach ( mcupr_private_bases( 'mail' ) as $mail_base ) {
			$sweep = array_merge( $sweep, (array) glob( $mail_base . '/*', GLOB_ONLYDIR ) );
		}
		foreach ( $sweep as $d ) {
			if ( ! isset( $keep[ basename( $d ) ] ) && filemtime( $d ) < time() - 3 * DAY_IN_SECONDS ) {
				mcupr_mail_att_delete( array( 'k' => basename( $d ) ) );
			}
		}
	}
);

/* ------------------------------------------------------------------ the templates (saved texts) */

function mcupr_mail_templates() {
	$t = get_option( 'mcupr_mail_tpls', array() );
	return is_array( $t ) ? array_slice( $t, 0, 30, true ) : array();
}

/* ------------------------------------------------------------------ the unsubscribe page */

add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'mcupr_unsub';
		return $v;
	}
);
add_filter(
	'pre_handle_404',
	static function ( $pre, $q ) {
		return $q->get( 'mcupr_unsub' ) ? true : $pre;
	},
	10,
	2
);
add_filter(
	'redirect_canonical',
	static function ( $url ) {
		return get_query_var( 'mcupr_unsub' ) ? false : $url;
	}
);
add_filter(
	'template_include',
	static function ( $t ) {
		if ( get_query_var( 'mcupr_unsub' ) ) {
			status_header( 200 );
			nocache_headers();
			return get_theme_file_path( 'mail-unsub.php' );
		}
		return $t;
	}
);
add_filter(
	'wp_robots',
	static function ( $r ) {
		if ( get_query_var( 'mcupr_unsub' ) ) {
			$r['noindex']  = true;
			$r['nofollow'] = true;
		}
		return $r;
	}
);
add_filter(
	'document_title_parts',
	static function ( $p ) {
		if ( get_query_var( 'mcupr_unsub' ) ) {
			$p['title'] = mcupr_t( 'um_t' );
		}
		return $p;
	}
);

/** Stop or start the announcements of a student (the link in the e-mail). POST only: opening the link changes nothing. */
function mcupr_mail_set_stopped( $sid, $stop ) {
	if ( $stop ) {
		update_post_meta( $sid, '_mcupr_s_nomail', time() );
	} else {
		delete_post_meta( $sid, '_mcupr_s_nomail' );
	}
	mcupr_audit( $stop ? 'unsub' : 'resub', array( 'post' => (int) $sid, 'sid' => (string) get_post_meta( $sid, '_mcupr_s_sid', true ) ) );
}

/* ------------------------------------------------------------------ the admin page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'E-mail to students', 'mcupr' ), __( 'E-mail to students', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-mail', 'mcupr_mail_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'mcupr-student-mail' !== $_GET['page'] || ! current_user_can( MCUPR_STU_CAP ) ) {
			return;
		}
		wp_enqueue_script( 'mcupr-aud-admin', get_theme_file_uri( 'assets/js/audience-admin.js' ), array(), MCUPR_VERSION, true );
		wp_enqueue_script( 'mcupr-mail-admin', get_theme_file_uri( 'assets/js/mail-admin.js' ), array( 'mcupr-aud-admin' ), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-mail-admin',
			'mcuprMail',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mcupr_mail_ajax' ),
				'tpls'  => mcupr_mail_templates(),
				's'     => array(
					/* translators: 1: students that will get it, 2: students who match, 3: no e-mail, 4: stopped announcements, 5: same address as another */
					'count'  => __( 'Will receive it: %1$d. Match the choice: %2$d; left out: %3$d without a valid e-mail, %4$d who stopped announcements, %5$d with an address already in the list.', 'mcupr' ),
					'sample' => __( 'The first e-mail would read:', 'mcupr' ),
					'none'   => __( 'Nobody matches this choice.', 'mcupr' ),
					'fail'   => __( 'Could not check now. Try again.', 'mcupr' ),
					'sent'   => __( 'Sent %1$d of %2$d', 'mcupr' ),
				),
			)
		);
	}
);

function mcupr_mail_draft_key() {
	return 'mcupr_mail_draft_' . get_current_user_id();
}

function mcupr_mail_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$msg   = isset( $_GET['m'] ) ? sanitize_key( wp_unslash( $_GET['m'] ) ) : '';
	$draft = get_transient( mcupr_mail_draft_key() );
	// phpcs:enable
	$draft = is_array( $draft ) ? array_merge( array( 'subject' => '', 'body' => '', 'reply' => '', 'at' => '', 'rules' => array( 'levels' => array(), 'years' => array(), 'fac' => array(), 'maj' => array() ) ), $draft ) : array( 'subject' => '', 'body' => '', 'reply' => wp_get_current_user()->user_email, 'at' => '', 'rules' => array( 'levels' => array(), 'years' => array(), 'fac' => array(), 'maj' => array() ) );
	$notes = array(
		'sent'   => array( 'success', __( 'Started. The e-mails go out in the background; you can close this page.', 'mcupr' ) ),
		'later'  => array( 'success', __( 'Saved. It will start at the time you chose.', 'mcupr' ) ),
		'test'   => array( 'success', __( 'The test e-mail was handed to the mail system. Check your inbox (and the spam folder).', 'mcupr' ) ),
		'tpl'    => array( 'success', __( 'The template was saved.', 'mcupr' ) ),
		'tpldel' => array( 'success', __( 'The template was deleted.', 'mcupr' ) ),
		'cap'    => array( 'success', __( 'Saved.', 'mcupr' ) ),
		'stop'   => array( 'success', __( 'Stopped. Nothing more will be sent.', 'mcupr' ) ),
		'empty'  => array( 'error', __( 'Please write a subject and a message.', 'mcupr' ) ),
		'nobody' => array( 'error', __( 'Nobody matches this choice, so nothing was sent.', 'mcupr' ) ),
		'check'  => array( 'error', __( 'Please tick “I have checked the message and who gets it”.', 'mcupr' ) ),
		'att'    => array( 'error', __( 'The attachment must be a PDF, JPG or PNG file of at most 2 MB.', 'mcupr' ) ),
		'time'   => array( 'error', __( 'The time to send must be in the future.', 'mcupr' ) ),
		'fail'   => array( 'error', __( 'The test e-mail could not be sent. Check the e-mail settings of the website.', 'mcupr' ) ),
		'name'   => array( 'error', __( 'Please type a name for the template.', 'mcupr' ) ),
	);
	echo '<div class="wrap"><h1>' . esc_html__( 'E-mail to students', 'mcupr' ) . '</h1>';
	if ( isset( $notes[ $msg ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $notes[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $notes[ $msg ][1] ) . '</p></div>';
	}
	echo mcupr_stu_mail_notice(); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p style="max-width:56rem">' . esc_html__( 'Send one message to a group of students who are studying now. Each student gets their own e-mail (nobody sees the others), sent in the background a few at a time, at most the number per hour set below. Every e-mail ends with a link to stop announcements.', 'mcupr' ) . '</p>';

	$o = mcupr_aud_options();
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="mcupr-mail-form"><input type="hidden" name="action" value="mcupr_mail_send">';
	wp_nonce_field( 'mcupr_mail_send', 'mcupr_mail_nonce' );
	$tpls = mcupr_mail_templates();
	echo '<table class="form-table" role="presentation"><tbody>';
	if ( $tpls ) {
		echo '<tr><th scope="row"><label for="mm-tpl">' . esc_html__( 'Start from a template', 'mcupr' ) . '</label></th><td><select id="mm-tpl"><option value="">—</option>';
		foreach ( $tpls as $k => $t ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $t['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '<tr><th scope="row"><label for="mm-subject">' . esc_html__( 'Subject', 'mcupr' ) . '</label></th><td><input id="mm-subject" name="subject" type="text" class="large-text" maxlength="150" value="' . esc_attr( $draft['subject'] ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="mm-body">' . esc_html__( 'Message', 'mcupr' ) . '</label></th><td><textarea id="mm-body" name="body" rows="10" class="large-text" maxlength="5000">' . esc_textarea( $draft['body'] ) . '</textarea><p class="description">' . esc_html__( 'Type {name} and {student_id} where the student’s name and ID should appear. Plain text. The name of the organisation, the student portal and the link to stop announcements are added at the end.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="mm-reply">' . esc_html__( 'Replies go to', 'mcupr' ) . '</label></th><td><input id="mm-reply" name="reply" type="email" class="regular-text" value="' . esc_attr( $draft['reply'] ) . '"><p class="description">' . esc_html__( 'Optional. Where a student’s reply to this e-mail should arrive.', 'mcupr' ) . '</p></td></tr>';
	/* translators: %s: the biggest file, e.g. 2 MB */
	echo '<tr><th scope="row"><label for="mm-att">' . esc_html__( 'Attachment', 'mcupr' ) . '</label></th><td><input id="mm-att" name="att" type="file" accept=".pdf,.jpg,.jpeg,.png"><p class="description">' . esc_html( sprintf( __( 'Optional. PDF, JPG or PNG, at most %s. It is not checked for viruses; send only files you trust.', 'mcupr' ), size_format( mcupr_mail_att_max() ) ) ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Who gets it', 'mcupr' ) . '</th><td><div class="mcupr-aud" id="mm-aud" data-msg="' . esc_attr__( 'This choice matches %1$d of %2$d students who are studying now.', 'mcupr' ) . '" data-combos="' . esc_attr( wp_json_encode( $o['combos'] ) ) . '" data-total="' . (int) $o['total'] . '" data-sep="' . esc_attr( MCUPR_AUD_SEP ) . '">';
	mcupr_aud_rules_fields( $draft['rules'], $o, false );
	echo '</div></td></tr>';
	echo '<tr><th scope="row"><label for="mm-at">' . esc_html__( 'When', 'mcupr' ) . '</label></th><td><input id="mm-at" name="at" type="datetime-local" value="' . esc_attr( $draft['at'] ) . '"><p class="description">' . esc_html__( 'Leave empty to start now. The time is in the time zone of the website.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="mm-tplname">' . esc_html__( 'Save as a template', 'mcupr' ) . '</label></th><td><input id="mm-tplname" name="tplname" type="text" class="regular-text" maxlength="60"> <button class="button" name="do" value="tpl" formnovalidate>' . esc_html__( 'Save the template', 'mcupr' ) . '</button></td></tr>';
	echo '</tbody></table>';
	echo '<div id="mm-check" class="notice notice-info inline" aria-live="polite" style="max-width:56rem"><p><button type="button" class="button" id="mm-preview">' . esc_html__( 'Check who gets it', 'mcupr' ) . '</button> <span id="mm-preview-out"></span></p><pre id="mm-sample" hidden style="white-space:pre-wrap;background:#fff;border:1px solid #c3c4c7;padding:8px;max-height:16rem;overflow:auto"></pre></div>';
	echo '<p><label><input type="checkbox" name="checked" value="1"> ' . esc_html__( 'I have checked the message and who gets it', 'mcupr' ) . '</label></p>';
	echo '<p><button class="button button-primary" name="do" value="send">' . esc_html__( 'Send', 'mcupr' ) . '</button> <button class="button" name="do" value="test" formnovalidate>' . esc_html__( 'Send a test to myself', 'mcupr' ) . '</button></p></form>';

	// the jobs
	$jobs = get_posts( array( 'post_type' => MCUPR_MAILJOB, 'post_status' => 'private', 'posts_per_page' => 20, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'no_found_rows' => true ) );
	echo '<h2>' . esc_html__( 'Sent and waiting', 'mcupr' ) . '</h2>';
	if ( ! $jobs ) {
		echo '<p>' . esc_html__( 'Nothing yet.', 'mcupr' ) . '</p>';
	} else {
		$st = mcupr_mail_statuses();
		echo '<table class="widefat striped" style="max-width:62rem"><thead><tr><th scope="col">' . esc_html__( 'Subject', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Status', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Sent', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Failed', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Left out', 'mcupr' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Stop', 'mcupr' ) . '</span></th></tr></thead><tbody>';
		foreach ( $jobs as $id ) {
			$j    = mcupr_mail_job( $id );
			$live = in_array( $j['status'], array( 'running', 'scheduled' ), true );
			echo '<tr data-job="' . (int) $id . '" data-live="' . ( $live ? '1' : '0' ) . '"><td>' . esc_html( $j['subject'] ) . '<br><span class="description">' . esc_html( wp_date( 'Y-m-d H:i', strtotime( get_post_field( 'post_date_gmt', $id ) . ' UTC' ) ) ) . ( 'scheduled' === $j['status'] ? ' → ' . esc_html( wp_date( 'Y-m-d H:i', $j['at'] ) ) : '' ) . '</span></td>';
			echo '<td class="mm-st">' . esc_html( $st[ $j['status'] ] ?? $j['status'] ) . '</td><td class="mm-sent">' . (int) $j['sent'] . ' / ' . (int) $j['total'] . '</td><td class="mm-failed">' . (int) $j['failed'] . '</td><td class="mm-skipped">' . (int) $j['skipped'] . '</td><td>';
			if ( $live ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_mail_cancel"><input type="hidden" name="job" value="' . (int) $id . '">';
				wp_nonce_field( 'mcupr_mail_cancel', 'mcupr_mail_nonce' );
				echo '<button class="button button-small">' . esc_html__( 'Stop', 'mcupr' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	// the settings and the templates
	echo '<h2>' . esc_html__( 'Limit per hour', 'mcupr' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_mail_cap">';
	wp_nonce_field( 'mcupr_mail_cap', 'mcupr_mail_nonce' );
	echo '<p><label for="mm-cap">' . esc_html__( 'At most this many e-mails an hour (0 = no limit)', 'mcupr' ) . '</label> <input id="mm-cap" name="cap" type="number" min="0" max="5000" class="small-text" value="' . (int) mcupr_mail_cap() . '"> <button class="button">' . esc_html__( 'Save', 'mcupr' ) . '</button></p><p class="description" style="max-width:56rem">' . esc_html__( 'Ask the people who run the university mail server how many e-mails a website may send in an hour, and set a little less. A message for more students than that simply takes more hours. Sending depends on visits to the website (WP-Cron); on a quiet site, ask your web host to run it on a timer.', 'mcupr' ) . '</p></form>';
	if ( $tpls ) {
		echo '<h2>' . esc_html__( 'Templates', 'mcupr' ) . '</h2><ul>';
		foreach ( $tpls as $k => $t ) {
			echo '<li>' . esc_html( $t['name'] ) . ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="mcupr_mail_tpldel"><input type="hidden" name="k" value="' . esc_attr( $k ) . '">';
			wp_nonce_field( 'mcupr_mail_tpldel', 'mcupr_mail_nonce' );
			/* translators: %s: template name */
			echo '<button class="button-link button-link-delete" aria-label="' . esc_attr( sprintf( __( 'Delete the template %s', 'mcupr' ), $t['name'] ) ) . '">' . esc_html__( 'Delete', 'mcupr' ) . '</button></form></li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

function mcupr_mail_guard( $action ) {
	if ( ! mcupr_check_request( 'mcupr_mail_nonce', $action, MCUPR_STU_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
	}
}
function mcupr_mail_back( $m ) {
	wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-mail&m=' . $m ) );
	exit;
}

/** Compose → send now / at a time, send a test, or save the text as a template. */
add_action(
	'admin_post_mcupr_mail_send',
	static function () {
		mcupr_mail_guard( 'mcupr_mail_send' );
		// phpcs:disable WordPress.Security.NonceVerification
		$do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'send';
		$subject = isset( $_POST['subject'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['subject'] ) ), 0, 150 ) : '';
		$body    = isset( $_POST['body'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['body'] ) ), 0, 5000 ) : '';
		$reply   = isset( $_POST['reply'] ) ? sanitize_email( wp_unslash( $_POST['reply'] ) ) : '';
		$atraw   = isset( $_POST['at'] ) ? sanitize_text_field( wp_unslash( $_POST['at'] ) ) : '';
		$tplname = isset( $_POST['tplname'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['tplname'] ) ), 0, 60 ) : '';
		$rules   = mcupr_aud_post_rules();
		$checked = ! empty( $_POST['checked'] );
		// phpcs:enable
		set_transient( mcupr_mail_draft_key(), array( 'subject' => $subject, 'body' => $body, 'reply' => $reply, 'at' => $atraw, 'rules' => $rules ), HOUR_IN_SECONDS );

		if ( 'tpl' === $do ) {
			if ( '' === $tplname ) {
				mcupr_mail_back( 'name' );
			}
			$t = mcupr_mail_templates();
			$k = substr( md5( $tplname ), 0, 10 );
			$t[ $k ] = array( 'name' => $tplname, 'subject' => $subject, 'body' => $body, 'reply' => $reply );
			update_option( 'mcupr_mail_tpls', array_slice( $t, -30, 30, true ), false );
			mcupr_mail_back( 'tpl' );
		}
		if ( '' === trim( $subject ) || '' === trim( $body ) ) {
			mcupr_mail_back( 'empty' );
		}
		list( $file, $err ) = mcupr_mail_check_att( isset( $_FILES['att'] ) ? $_FILES['att'] : null ); // phpcs:ignore WordPress.Security
		if ( '' !== $err ) {
			mcupr_mail_back( 'att' );
		}
		$rec = mcupr_mail_recipients( $rules );

		if ( 'test' === $do ) {
			$me   = wp_get_current_user()->user_email;
			$sid  = $rec['ids'] ? (int) $rec['ids'][0] : 0;
			$kept = $file ? mcupr_mail_keep_att( $file ) : null;
			$path = $kept ? mcupr_mail_att_path( $kept ) : '';
			$ok   = $sid && is_email( $me ) ? mcupr_stu_mail( $me, '[TEST] ' . $subject, mcupr_mail_text( $body, $sid ), mcupr_mail_headers( $reply, $sid ), '' !== $path ? array( $path ) : array() ) : false;
			mcupr_mail_att_delete( $kept );
			mcupr_mail_back( $ok ? 'test' : ( $sid ? 'fail' : 'nobody' ) );
		}

		if ( ! $checked ) {
			mcupr_mail_back( 'check' );
		}
		if ( ! $rec['ids'] ) {
			mcupr_mail_back( 'nobody' );
		}
		$at = 0;
		if ( '' !== $atraw ) {
			try {
				$at = ( new DateTime( $atraw, wp_timezone() ) )->getTimestamp();
			} catch ( Exception $e ) {
				$at = 0;
			}
			if ( $at && $at <= time() + 30 ) {
				mcupr_mail_back( 'time' );
			}
		}
		$kept = $file ? mcupr_mail_keep_att( $file ) : null;
		if ( $file && ! $kept ) {
			mcupr_mail_back( 'att' );
		}
		$id = mcupr_mail_create( $subject, $body, $reply, $rules, $rec['ids'], $kept, $at );
		if ( ! $id ) {
			mcupr_mail_att_delete( $kept );
			mcupr_mail_back( 'fail' );
		}
		delete_transient( mcupr_mail_draft_key() );
		mcupr_mail_back( $at ? 'later' : 'sent' );
	}
);

add_action(
	'admin_post_mcupr_mail_cancel',
	static function () {
		mcupr_mail_guard( 'mcupr_mail_cancel' );
		mcupr_mail_cancel( isset( $_POST['job'] ) ? absint( $_POST['job'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		mcupr_mail_back( 'stop' );
	}
);
add_action(
	'admin_post_mcupr_mail_cap',
	static function () {
		mcupr_mail_guard( 'mcupr_mail_cap' );
		update_option( 'mcupr_mail_opts', array( 'cap' => isset( $_POST['cap'] ) ? max( 0, min( 5000, absint( $_POST['cap'] ) ) ) : 100 ), false ); // phpcs:ignore WordPress.Security.NonceVerification
		mcupr_audit( 'settings', array( 'note' => 'mail limit' ) );
		mcupr_mail_back( 'cap' );
	}
);
add_action(
	'admin_post_mcupr_mail_tpldel',
	static function () {
		mcupr_mail_guard( 'mcupr_mail_tpldel' );
		$t = mcupr_mail_templates();
		unset( $t[ isset( $_POST['k'] ) ? sanitize_key( wp_unslash( $_POST['k'] ) ) : '' ] ); // phpcs:ignore WordPress.Security.NonceVerification
		update_option( 'mcupr_mail_tpls', $t, false );
		mcupr_mail_back( 'tpldel' );
	}
);

/** The check: how many would get it, why the others are left out, and the first e-mail as it would read. */
add_action(
	'wp_ajax_mcupr_mail_preview',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! check_ajax_referer( 'mcupr_mail_ajax', 'n', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$rec  = mcupr_mail_recipients( mcupr_aud_post_rules() );
		$body = isset( $_POST['body'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['body'] ) ), 0, 5000 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'n' => count( $rec['ids'] ), 'matched' => $rec['matched'], 'no_email' => $rec['no_email'], 'stopped' => $rec['stopped'], 'dup' => $rec['dup'], 'sample' => $rec['ids'] ? mcupr_mail_text( $body, (int) $rec['ids'][0] ) : '' ) );
	}
);

/** While the page is open it nudges the sending along and shows the numbers. */
add_action(
	'wp_ajax_mcupr_mail_step',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! check_ajax_referer( 'mcupr_mail_ajax', 'n', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$id  = isset( $_POST['job'] ) ? absint( $_POST['job'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$job = mcupr_mail_run( $id, 10, 8 );
		if ( ! $job ) {
			wp_send_json_error( null, 404 );
		}
		$st = mcupr_mail_statuses();
		wp_send_json_success( array( 'status' => $job['status'], 'label' => $st[ $job['status'] ] ?? $job['status'], 'sent' => (int) $job['sent'], 'total' => (int) $job['total'], 'failed' => (int) $job['failed'], 'skipped' => (int) $job['skipped'], 'live' => in_array( $job['status'], array( 'running', 'scheduled' ), true ) ) );
	}
);
