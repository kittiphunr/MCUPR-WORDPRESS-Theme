<?php
/**
 * Students: login accounts and the student portal (/student/).
 *
 * Accounts are made from the records (never with a password anyone sees): the student gets a link by e-mail and sets
 * their own password. The portal has a login form, "first time / forgot password", the student's own details, the content
 * marked "students only" and a change-password form. Students are kept out of wp-admin and have no admin bar.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ settings */

function mcupr_stu_opts() {
	$o = get_option( 'mcupr_stu_opts', array() );
	$o = array_merge( array( 'portal' => 1, 'self' => 1, 'intro' => '', 'help' => '' ), is_array( $o ) ? $o : array() );
	return array( 'portal' => ! empty( $o['portal'] ) ? 1 : 0, 'self' => ! empty( $o['self'] ) ? 1 : 0, 'intro' => (string) $o['intro'], 'help' => (string) $o['help'] );
}

function mcupr_student_portal_url( $args = array() ) {
	// Plain permalinks have no /student/ rule (and a server without rewriting would answer 404): use the query variable instead.
	if ( ! get_option( 'permalink_structure' ) ) {
		return add_query_arg( array_merge( array( 'mcupr_student' => 1 ), $args ), home_url( '/' ) );
	}
	return add_query_arg( $args, home_url( '/student/' ) );
}

/* ------------------------------------------------------------------ accounts */

/** Make (or find) the login account of a student record. Returns the user id or a WP_Error. */
function mcupr_student_make_account( $post_id ) {
	$post_id = (int) $post_id;
	if ( MCUPR_STU !== get_post_type( $post_id ) ) {
		return new WP_Error( 'none', 'not a student' );
	}
	$d = mcupr_student_data( $post_id );
	if ( $d['user'] && get_userdata( $d['user'] ) ) {
		return $d['user'];
	}
	if ( '' === $d['sid'] ) {
		return new WP_Error( 'sid', 'no student id' );
	}
	if ( '' === $d['email'] || ! is_email( $d['email'] ) ) {
		return new WP_Error( 'email', 'no e-mail' );
	}
	$login = sanitize_user( strtolower( $d['sid'] ), true );
	$have  = username_exists( $login );
	if ( $have ) {
		if ( (int) get_user_meta( $have, 'mcupr_student_id', true ) === $post_id && mcupr_is_student_user( $have ) ) {
			update_post_meta( $post_id, '_mcupr_s_user', $have );
			return (int) $have;
		}
		return new WP_Error( 'login_used', 'login used' );
	}
	$other = email_exists( $d['email'] );
	if ( $other ) {
		return new WP_Error( 'email_used', 'e-mail used' );
	}
	$uid = wp_insert_user(
		array(
			'user_login'           => $login,
			'user_pass'            => wp_generate_password( 32, true, true ),
			'user_email'           => $d['email'],
			'display_name'         => $d['name'],
			'first_name'           => $d['name'],
			'role'                 => MCUPR_STU_USER_ROLE,
			'show_admin_bar_front' => 'false',
		)
	);
	if ( is_wp_error( $uid ) ) {
		return $uid;
	}
	update_user_meta( $uid, 'mcupr_student_id', $post_id );
	update_post_meta( $post_id, '_mcupr_s_user', (int) $uid );
	return (int) $uid;
}

/** E-mail the link that lets the student set a password (Thai and English in one message). Returns true or a WP_Error. */
function mcupr_student_send_activation( $post_id ) {
	$d = mcupr_student_data( $post_id );
	if ( ! $d['user'] || ! mcupr_is_student_user( $d['user'] ) ) {
		return new WP_Error( 'account', 'no account' );
	}
	$user = get_userdata( $d['user'] );
	$key  = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return $key;
	}
	$url  = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
	$org  = mcupr_org_name();
	$body = sprintf( mcupr_t( 'stu_mail_body', 'th' ), $d['name'], $org, $url, mcupr_student_portal_url() ) . "\n\n----\n\n" . sprintf( mcupr_t( 'stu_mail_body', 'en' ), $d['name'], $org, $url, mcupr_student_portal_url() );
	$ok   = wp_mail( $user->user_email, sprintf( mcupr_t( 'stu_mail_subject', 'th' ), $org ) . ' / ' . sprintf( mcupr_t( 'stu_mail_subject', 'en' ), $org ), $body );
	if ( ! $ok ) {
		return new WP_Error( 'mail', 'mail failed' );
	}
	update_user_meta( $user->ID, 'mcupr_activation_sent', time() );
	return true;
}

/** Delete the login account of a record (only when it is a student account – never a staff member). */
function mcupr_student_drop_account( $post_id ) {
	$uid = (int) get_post_meta( $post_id, '_mcupr_s_user', true );
	if ( ! $uid ) {
		return false;
	}
	$u = get_userdata( $uid );
	delete_post_meta( $post_id, '_mcupr_s_user' );
	if ( ! $u || ! mcupr_is_student_user( $uid ) || count( (array) $u->roles ) > 1 ) {
		return false;
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	return wp_delete_user( $uid );
}

/** The student sets a password through the link (or in the portal): the account counts as active. */
add_action(
	'after_password_reset',
	static function ( $user ) {
		if ( mcupr_is_student_user( $user->ID ) ) {
			update_user_meta( $user->ID, 'mcupr_activated', time() );
		}
	}
);

/** The three buttons of the record screen (one student). */
foreach ( array( 'mcupr_stu_make', 'mcupr_stu_send', 'mcupr_stu_drop' ) as $mcupr_stu_action ) {
	add_action(
		'admin_post_' . $mcupr_stu_action,
		static function () use ( $mcupr_stu_action ) {
			$id = isset( $_GET['sid'] ) ? absint( $_GET['sid'] ) : 0;
			if ( ! $id || ! current_user_can( MCUPR_STU_CAP ) || ! check_admin_referer( 'mcupr_stu_' . $mcupr_stu_action . $id ) ) {
				wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
			}
			if ( 'mcupr_stu_make' === $mcupr_stu_action ) {
				$r = mcupr_student_make_account( $id );
			} elseif ( 'mcupr_stu_send' === $mcupr_stu_action ) {
				$r = mcupr_student_send_activation( $id );
			} else {
				$r = mcupr_student_drop_account( $id ) ? true : new WP_Error( 'x', 'x' );
			}
			wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'mcs_done' => is_wp_error( $r ) ? 0 : 1, 'mcs_err' => is_wp_error( $r ) ? 1 : 0 ), admin_url( 'post.php' ) ) );
			exit;
		}
	);
}

/* ------------------------------------------------------------------ students stay out of the admin */

add_filter(
	'show_admin_bar',
	static function ( $show ) {
		return is_user_logged_in() && mcupr_is_student_user() ? false : $show;
	}
);
add_action(
	'admin_init',
	static function () {
		if ( is_user_logged_in() && mcupr_is_student_user() && ! wp_doing_ajax() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			if ( isset( $_SERVER['SCRIPT_NAME'] ) && false !== strpos( (string) $_SERVER['SCRIPT_NAME'], 'admin-post.php' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				return;
			}
			wp_safe_redirect( mcupr_student_portal_url() );
			exit;
		}
	}
);
add_filter(
	'login_redirect',
	static function ( $to, $req, $user ) {
		return $user instanceof WP_User && in_array( MCUPR_STU_USER_ROLE, (array) $user->roles, true ) ? mcupr_student_portal_url() : $to;
	},
	10,
	3
);

/* ------------------------------------------------------------------ the page /student/ */

add_action(
	'init',
	static function () {
		add_rewrite_rule( '^student/?$', 'index.php?mcupr_student=1', 'top' );
		if ( get_option( 'mcupr_rewrite_students' ) !== MCUPR_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'mcupr_rewrite_students', MCUPR_VERSION );
		}
	},
	7
);
add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'mcupr_student';
		return $v;
	}
);
add_filter(
	'pre_handle_404',
	static function ( $pre, $q ) {
		return $q->get( 'mcupr_student' ) && mcupr_stu_opts()['portal'] ? true : $pre;
	},
	10,
	2
);
add_filter(
	'redirect_canonical',
	static function ( $url ) {
		return get_query_var( 'mcupr_student' ) ? false : $url;
	}
);
add_filter(
	'template_include',
	static function ( $t ) {
		if ( get_query_var( 'mcupr_student' ) && mcupr_stu_opts()['portal'] ) {
			status_header( 200 );
			nocache_headers();
			return get_theme_file_path( 'student-portal.php' );
		}
		return $t;
	}
);
add_filter(
	'wp_robots',
	static function ( $r ) {
		if ( get_query_var( 'mcupr_student' ) ) {
			$r['noindex']  = true;
			$r['nofollow'] = true;
		}
		return $r;
	}
);
add_filter(
	'document_title_parts',
	static function ( $p ) {
		if ( get_query_var( 'mcupr_student' ) ) {
			$p['title'] = mcupr_t( 'stu_portal' );
		}
		return $p;
	}
);

/** A few tries per person and per address, so the login cannot be guessed at. Returns false when too many. */
function mcupr_stu_throttle( $key, $max, $window ) {
	$k = 'mcupr_thr_' . md5( $key );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) {
		return false;
	}
	set_transient( $k, $n + 1, $window );
	return true;
}

function mcupr_stu_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/** Login form (no JavaScript needed). */
foreach ( array( 'admin_post_nopriv_mcupr_stu_login', 'admin_post_mcupr_stu_login' ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function () {
			if ( ! isset( $_POST['mcupr_stu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_stu_nonce'] ) ), 'mcupr_stu_login' ) ) {
				wp_safe_redirect( mcupr_student_portal_url( array( 'e' => 'login' ) ) );
				exit;
			}
			$login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
			$pass  = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! mcupr_stu_throttle( mcupr_stu_ip() . '|' . strtolower( $login ), 8, 15 * MINUTE_IN_SECONDS ) ) {
				wp_safe_redirect( mcupr_student_portal_url( array( 'e' => 'wait' ) ) );
				exit;
			}
			$u = wp_signon( array( 'user_login' => $login, 'user_password' => $pass, 'remember' => ! empty( $_POST['rememberme'] ) ), is_ssl() );
			$to = mcupr_student_portal_url();
			if ( ! is_wp_error( $u ) && ! empty( $_POST['redirect_to'] ) ) {
				$to = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), $to );
			}
			wp_safe_redirect( is_wp_error( $u ) ? mcupr_student_portal_url( array( 'e' => 'login' ) ) : $to );
			exit;
		}
	);
}

/** "First time / forgot my password": the link goes to the registered e-mail; the answer is always the same. */
foreach ( array( 'admin_post_nopriv_mcupr_stu_forgot', 'admin_post_mcupr_stu_forgot' ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function () {
			$back = mcupr_student_portal_url( array( 'sent' => 1 ) );
			if ( ! isset( $_POST['mcupr_stu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_stu_nonce'] ) ), 'mcupr_stu_forgot' ) || ! mcupr_stu_opts()['portal'] ) {
				wp_safe_redirect( $back );
				exit;
			}
			list( $sid ) = mcupr_student_clean( 'sid', isset( $_POST['sid'] ) ? sanitize_text_field( wp_unslash( $_POST['sid'] ) ) : '' );
			if ( '' !== $sid && mcupr_stu_throttle( 'ip|' . mcupr_stu_ip(), 6, HOUR_IN_SECONDS ) && mcupr_stu_throttle( 'sid|' . $sid, 1, 10 * MINUTE_IN_SECONDS ) ) {
				$id = mcupr_student_find( $sid );
				if ( $id && 'studying' === get_post_meta( $id, '_mcupr_s_status', true ) ) {
					$r = mcupr_student_make_account( $id ); // makes it when the record has an e-mail and the setting allows
					if ( ! is_wp_error( $r ) && ( mcupr_stu_opts()['self'] || get_user_meta( $r, 'mcupr_activation_sent', true ) || get_user_meta( $r, 'mcupr_activated', true ) ) ) {
						mcupr_student_send_activation( $id );
					}
				}
			}
			wp_safe_redirect( $back );
			exit;
		}
	);
}

/** Change the password (logged in). */
add_action(
	'admin_post_mcupr_stu_pw',
	static function () {
		$to = mcupr_student_portal_url();
		if ( ! is_user_logged_in() || ! isset( $_POST['mcupr_stu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_stu_nonce'] ) ), 'mcupr_stu_pw' ) ) {
			wp_safe_redirect( $to );
			exit;
		}
		$u    = wp_get_current_user();
		$cur  = isset( $_POST['cur'] ) ? (string) wp_unslash( $_POST['cur'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$new  = isset( $_POST['new'] ) ? (string) wp_unslash( $_POST['new'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$new2 = isset( $_POST['new2'] ) ? (string) wp_unslash( $_POST['new2'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! mcupr_stu_throttle( 'pw|' . $u->ID, 10, 15 * MINUTE_IN_SECONDS ) ) {
			$e = 'wait';
		} elseif ( ! wp_check_password( $cur, $u->user_pass, $u->ID ) ) {
			$e = 'pwcur';
		} elseif ( mb_strlen( $new ) < 8 ) {
			$e = 'pwshort';
		} elseif ( $new !== $new2 ) {
			$e = 'pwsame';
		} else {
			wp_set_password( $new, $u->ID );
			update_user_meta( $u->ID, 'mcupr_activated', time() );
			wp_set_current_user( $u->ID );
			wp_set_auth_cookie( $u->ID, true );
			wp_safe_redirect( add_query_arg( 'pw', 'ok', $to ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'e', $e, $to ) . '#stu-pw' );
		exit;
	}
);

/** Student's own details for the portal (never anyone else's). */
function mcupr_student_card_html( $sid_post ) {
	$d  = mcupr_student_data( $sid_post );
	$lv = mcupr_student_levels();
	$st = mcupr_student_statuses();
	$ll = array( 'bachelor' => 'stu_lv_bachelor', 'master' => 'stu_lv_master', 'doctor' => 'stu_lv_doctor', 'other' => 'stu_lv_other' );
	$sl = array( 'studying' => 'stu_st_studying', 'leave' => 'stu_st_leave', 'graduated' => 'stu_st_graduated', 'withdrawn' => 'stu_st_withdrawn' );
	$rows = array(
		array( 'stu_f_id', $d['sid'] ),
		array( 'stu_f_level', isset( $ll[ $d['level'] ] ) ? mcupr_t( $ll[ $d['level'] ] ) : '' ),
		array( 'stu_f_year', $d['year'] ),
		array( 'stu_f_faculty', $d['faculty'] ),
		array( 'stu_f_major', $d['major'] ),
		array( 'stu_f_email', $d['email'] ),
	);
	$o  = '<dl class="stu-dl">';
	foreach ( $rows as $r ) {
		if ( '' !== (string) $r[1] ) {
			$o .= '<div><dt>' . esc_html( mcupr_t( $r[0] ) ) . '</dt><dd>' . esc_html( $r[1] ) . '</dd></div>';
		}
	}
	$k  = isset( $sl[ $d['status'] ] ) ? $d['status'] : 'studying';
	$o .= '<div><dt>' . esc_html( mcupr_t( 'stu_f_status' ) ) . '</dt><dd><span class="stu-st" data-st="' . esc_attr( $k ) . '">' . esc_html( mcupr_t( $sl[ $k ] ) ) . '</span></dd></div></dl>';
	return $o;
}

/* ------------------------------------------------------------------ settings page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Student portal settings', 'mcupr' ), __( 'Student portal settings', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-settings', 'mcupr_stu_settings_page' );
	}
);

function mcupr_stu_settings_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	$o = mcupr_stu_opts();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$saved = isset( $_GET['saved'] );
	echo '<div class="wrap"><h1>' . esc_html__( 'Student portal settings', 'mcupr' ) . '</h1>';
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<p style="max-width:56rem">' . esc_html__( 'The student portal is at /student/ (add it to a menu to link to it). Students log in with their student ID or e-mail; they set their first password through a link sent to the e-mail in their record.', 'mcupr' ) . ' <a href="' . esc_url( mcupr_student_portal_url() ) . '" target="_blank" rel="noopener">' . esc_html( mcupr_student_portal_url() ) . '</a></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_stu_settings">';
	wp_nonce_field( 'mcupr_stu_settings', 'mcupr_stu_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Student portal', 'mcupr' ) . '</th><td><label><input type="checkbox" name="portal" value="1"' . checked( $o['portal'], 1, false ) . '> ' . esc_html__( 'On (login and student-only content)', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'First time', 'mcupr' ) . '</th><td><label><input type="checkbox" name="self" value="1"' . checked( $o['self'], 1, false ) . '> ' . esc_html__( 'A student who asks for a password link gets a login account made at once (if the record has an e-mail). Off: only students whose account staff already made can ask.', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th scope="row"><label for="stu-intro">' . esc_html__( 'Text above the login form', 'mcupr' ) . '</label></th><td><textarea id="stu-intro" name="intro" rows="3" class="large-text">' . esc_textarea( $o['intro'] ) . '</textarea></td></tr>';
	echo '<tr><th scope="row"><label for="stu-help">' . esc_html__( 'Who to ask for help', 'mcupr' ) . '</label></th><td><textarea id="stu-help" name="help" rows="2" class="large-text">' . esc_textarea( $o['help'] ) . '</textarea><p class="description">' . esc_html__( 'Shown under the forms, e.g. “Registrar office, 054-000-000, registrar@example.org”.', 'mcupr' ) . '</p></td></tr>';
	echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form></div>';
}

add_action(
	'admin_post_mcupr_stu_settings',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! isset( $_POST['mcupr_stu_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_stu_nonce'] ) ), 'mcupr_stu_settings' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		update_option(
			'mcupr_stu_opts',
			array(
				'portal' => ! empty( $_POST['portal'] ) ? 1 : 0,
				'self'   => ! empty( $_POST['self'] ) ? 1 : 0,
				'intro'  => isset( $_POST['intro'] ) ? sanitize_textarea_field( wp_unslash( $_POST['intro'] ) ) : '',
				'help'   => isset( $_POST['help'] ) ? sanitize_textarea_field( wp_unslash( $_POST['help'] ) ) : '',
			),
			false
		);
		flush_rewrite_rules( false );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-settings&saved=1' ) );
		exit;
	}
);
