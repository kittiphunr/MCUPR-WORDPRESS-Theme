<?php
/**
 * Students (ระบบนิสิต): a private database of students, kept in the website's own database with no plugin.
 *
 *   - post type "mcupr_student" (never public, never in the REST API): student ID, name, level, year, faculty, major,
 *     status, e-mail, phone, a note and the linked login account;
 *   - bulk input: CSV import with preview, chunks and a report (inc/students-import.php), bulk status changes, export;
 *   - login accounts (role "Student") made from the records; the student sets their own password through a link sent to
 *     the registered e-mail (inc/students-portal.php) – no password is ever shown or stored in clear text;
 *   - student-only content: any news, page, event, album or video can be marked "students only" (inc/audience.php);
 *   - role "Registrar" (เจ้าหน้าที่ทะเบียน): manages students without being an administrator.
 * Student data is never printed on public pages; it is also covered by WordPress' personal data export and erase tools.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_STU            = 'mcupr_student';
const MCUPR_STU_USER_ROLE  = 'mcupr_student_user';
const MCUPR_REGISTRAR_ROLE = 'mcupr_registrar';
const MCUPR_STU_CAP        = 'mcupr_manage_students';

/* ------------------------------------------------------------------ vocabulary */

/** Education levels: key => label. */
function mcupr_student_levels() {
	return array(
		'bachelor' => __( 'Bachelor', 'mcupr' ),
		'master'   => __( 'Master', 'mcupr' ),
		'doctor'   => __( 'Doctoral', 'mcupr' ),
		'other'    => __( 'Other', 'mcupr' ),
	);
}

/** Student statuses: key => label. */
function mcupr_student_statuses() {
	return array(
		'studying'  => __( 'Studying', 'mcupr' ),
		'leave'     => __( 'On leave', 'mcupr' ),
		'graduated' => __( 'Graduated', 'mcupr' ),
		'withdrawn' => __( 'Withdrawn', 'mcupr' ),
	);
}

/** The words people write in a file (Thai or English) for each level / status. */
function mcupr_student_synonyms( $kind ) {
	$map = array(
		'level'  => array(
			'bachelor' => array( 'bachelor', 'bachelors', 'undergraduate', 'ug', 'b', 'ตรี', 'ปริญญาตรี', 'ปตรี', 'ป.ตรี', 'บัณฑิต', '1' ),
			'master'   => array( 'master', 'masters', 'graduate', 'm', 'โท', 'ปริญญาโท', 'ปโท', 'ป.โท', 'มหาบัณฑิต' ),
			'doctor'   => array( 'doctor', 'doctoral', 'doctorate', 'phd', 'd', 'เอก', 'ปริญญาเอก', 'ปเอก', 'ป.เอก', 'ดุษฎีบัณฑิต' ),
			'other'    => array( 'other', 'อื่นๆ', 'อื่น' ),
		),
		'status' => array(
			'studying'  => array( 'studying', 'active', 'enrolled', 'normal', 'กำลังศึกษา', 'ศึกษาอยู่', 'ปกติ', 'ภาวะปกติ' ),
			'leave'     => array( 'leave', 'onleave', 'suspended', 'พักการศึกษา', 'ลาพัก', 'ลาพักการศึกษา' ),
			'graduated' => array( 'graduated', 'alumni', 'alumnus', 'สำเร็จการศึกษา', 'จบ', 'จบการศึกษา', 'บัณฑิต' ),
			'withdrawn' => array( 'withdrawn', 'dropped', 'resigned', 'ลาออก', 'พ้นสภาพ', 'พ้นสภาพนิสิต', 'ถูกคัดชื่อออก' ),
		),
	);
	return $map[ $kind ];
}

/** A word from a file → the key, or '' when it is not understood. */
function mcupr_student_normalize( $kind, $word ) {
	$w = mb_strtolower( trim( preg_replace( '/[\s_\-\.]+/u', '', (string) $word ) ) );
	if ( '' === $w ) {
		return '';
	}
	foreach ( mcupr_student_synonyms( $kind ) as $key => $words ) {
		foreach ( $words as $x ) {
			if ( $w === mb_strtolower( preg_replace( '/[\s_\-\.]+/u', '', $x ) ) ) {
				return $key;
			}
		}
	}
	return '';
}

/** The columns of a student record: field => [ label, meta key ]. */
function mcupr_student_fields() {
	return array(
		'sid'     => array( __( 'Student ID', 'mcupr' ), '_mcupr_s_sid' ),
		'name_en' => array( __( 'Name in English', 'mcupr' ), '_mcupr_s_name_en' ),
		'level'   => array( __( 'Level', 'mcupr' ), '_mcupr_s_level' ),
		'year'    => array( __( 'Year of study', 'mcupr' ), '_mcupr_s_year' ),
		'entry'   => array( __( 'Year of entry', 'mcupr' ), '_mcupr_s_entry' ),
		'faculty' => array( __( 'Faculty', 'mcupr' ), '_mcupr_s_faculty' ),
		'major'   => array( __( 'Major / programme', 'mcupr' ), '_mcupr_s_major' ),
		'status'  => array( __( 'Status', 'mcupr' ), '_mcupr_s_status' ),
		'email'   => array( __( 'E-mail', 'mcupr' ), '_mcupr_s_email' ),
		'phone'   => array( __( 'Telephone', 'mcupr' ), '_mcupr_s_phone' ),
		'note'    => array( __( 'Note (staff only)', 'mcupr' ), '_mcupr_s_note' ),
	);
}

/** A student record as a plain array (name = the post title). */
function mcupr_student_data( $post_id ) {
	$d = array( 'id' => (int) $post_id, 'name' => get_the_title( $post_id ) );
	foreach ( mcupr_student_fields() as $k => $f ) {
		$d[ $k ] = (string) get_post_meta( $post_id, $f[1], true );
	}
	$d['user'] = (int) get_post_meta( $post_id, '_mcupr_s_user', true );
	return $d;
}

/** Make a value fit a field (text fields are cleaned, choices must be one of the keys). Returns array( value, warning ). */
function mcupr_student_clean( $field, $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	switch ( $field ) {
		case 'sid':
			return array( preg_match( '/^[A-Za-z0-9\-]{4,20}$/', $v ) ? strtoupper( $v ) : '', '' );
		case 'level':
			$k = mcupr_student_normalize( 'level', $v );
			return array( '' !== $k ? $k : 'bachelor', '' !== $v && '' === $k ? 'level' : '' );
		case 'status':
			$k = mcupr_student_normalize( 'status', $v );
			return array( '' !== $k ? $k : 'studying', '' !== $v && '' === $k ? 'status' : '' );
		case 'year':
			$y = preg_match( '/^\d{1,2}$/', $v ) && (int) $v >= 1 && (int) $v <= 12 ? (string) (int) $v : '';
			return array( $y, '' !== $v && '' === $y ? 'year' : '' );
		case 'entry':
			$y = preg_match( '/^(25|24|20|19)\d{2}$/', $v ) ? $v : '';
			return array( $y, '' !== $v && '' === $y ? 'entry' : '' );
		case 'email':
			$e = sanitize_email( $v );
			return array( '' !== $e && is_email( $e ) ? strtolower( $e ) : '', '' !== $v && ( '' === $e || ! is_email( $e ) ) ? 'email' : '' );
		case 'note':
			return array( sanitize_textarea_field( $v ), '' );
		default:
			return array( mb_substr( sanitize_text_field( $v ), 0, 120 ), '' );
	}
}

/** All student IDs in the database: sid => post id (one query, for the importer). */
function mcupr_student_map() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT m.meta_value AS sid, m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_mcupr_s_sid' AND p.post_type = 'mcupr_student' AND p.post_status <> 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$map  = array();
	foreach ( $rows as $r ) {
		$map[ strtoupper( $r->sid ) ] = (int) $r->post_id;
	}
	return $map;
}

/** The record with a student ID, or 0. */
function mcupr_student_find( $sid ) {
	$sid = strtoupper( trim( (string) $sid ) );
	if ( '' === $sid ) {
		return 0;
	}
	$p = get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'meta_key' => '_mcupr_s_sid', 'meta_value' => $sid, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'mcupr_office_all' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $p ? (int) $p[0] : 0;
}

/** Counts by status, for the overview and the list. */
function mcupr_student_counts() {
	global $wpdb;
	$out  = array( 'all' => 0, 'accounts' => 0 );
	foreach ( array_keys( mcupr_student_statuses() ) as $k ) {
		$out[ $k ] = 0;
	}
	$rows = $wpdb->get_results( "SELECT m.meta_value AS s, COUNT(*) AS n FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_mcupr_s_status' AND p.post_type = 'mcupr_student' AND p.post_status = 'publish' GROUP BY m.meta_value" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $rows as $r ) {
		if ( isset( $out[ $r->s ] ) ) {
			$out[ $r->s ] = (int) $r->n;
		}
		$out['all'] += (int) $r->n;
	}
	$out['accounts'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_mcupr_s_user' AND m.meta_value > 0 AND p.post_type = 'mcupr_student' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $out;
}

/**
 * Add or change a student from a row of already cleaned values.
 * $mode: 'add_update' | 'add_only' | 'update_only'. Returns array( result, post id ) where result is
 * added | updated | unchanged | skipped | error.
 */
function mcupr_student_upsert( $row, $mode = 'add_update', $existing_id = null ) {
	$sid = $row['sid'];
	$id  = null === $existing_id ? mcupr_student_find( $sid ) : (int) $existing_id;
	if ( $id && 'add_only' === $mode ) {
		return array( 'skipped', $id );
	}
	if ( ! $id && 'update_only' === $mode ) {
		return array( 'skipped', 0 );
	}
	$fields = mcupr_student_fields();
	if ( $id ) {
		$changed = false;
		if ( '' !== $row['name'] && get_the_title( $id ) !== $row['name'] ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $row['name'] ) );
			$changed = true;
		}
		foreach ( $fields as $k => $f ) {
			if ( 'sid' === $k || ! array_key_exists( $k, $row ) || null === $row[ $k ] ) {
				continue;
			}
			if ( '' === $row[ $k ] ) {
				continue; // an empty cell in an update leaves what is there
			}
			if ( (string) get_post_meta( $id, $f[1], true ) !== (string) $row[ $k ] ) {
				update_post_meta( $id, $f[1], $row[ $k ] );
				$changed = true;
			}
		}
		return array( $changed ? 'updated' : 'unchanged', $id );
	}
	$id = wp_insert_post( array( 'post_type' => MCUPR_STU, 'post_status' => 'publish', 'post_title' => $row['name'] ), true );
	if ( is_wp_error( $id ) || ! $id ) {
		return array( 'error', 0 );
	}
	$row['level']  = '' === (string) ( isset( $row['level'] ) ? $row['level'] : '' ) ? 'bachelor' : $row['level'];
	$row['status'] = '' === (string) ( isset( $row['status'] ) ? $row['status'] : '' ) ? 'studying' : $row['status'];
	foreach ( $fields as $k => $f ) {
		if ( isset( $row[ $k ] ) && '' !== $row[ $k ] ) {
			update_post_meta( $id, $f[1], $row[ $k ] );
		}
	}
	return array( 'added', (int) $id );
}

/* ------------------------------------------------------------------ the type and the roles */

function mcupr_students_register() {
	register_post_type(
		MCUPR_STU,
		array(
			'labels'              => array(
				'name'          => __( 'Students', 'mcupr' ),
				'singular_name' => __( 'Student', 'mcupr' ),
				'menu_name'     => __( 'Students', 'mcupr' ),
				'all_items'     => __( 'All students', 'mcupr' ),
				'add_new_item'  => __( 'Add a student', 'mcupr' ),
				'edit_item'     => __( 'Edit student', 'mcupr' ),
				'search_items'  => __( 'Search students', 'mcupr' ),
				'not_found'     => __( 'No students yet', 'mcupr' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_rest'        => false,
			'show_in_nav_menus'   => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-id-alt',
			'capability_type'     => array( 'mcupr_student', 'mcupr_students' ),
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'mcupr_students_register', 6 );

/** The capabilities of the type, as a list. */
function mcupr_student_caps() {
	$o = get_post_type_object( MCUPR_STU );
	return $o ? array_values( array_unique( array_filter( array_merge( array_values( (array) $o->cap ), array( MCUPR_STU_CAP ) ) ) ) ) : array( MCUPR_STU_CAP );
}

add_action(
	'init',
	static function () {
		$caps = mcupr_student_caps();
		$v    = md5( wp_json_encode( $caps ) . '2' ); // made again whenever the list changes
		if ( get_option( 'mcupr_student_roles_v' ) === $v && get_role( MCUPR_REGISTRAR_ROLE ) && get_role( MCUPR_STU_USER_ROLE ) ) {
			return;
		}
		$all = array_fill_keys( $caps, true );
		remove_role( MCUPR_REGISTRAR_ROLE );
		add_role( MCUPR_REGISTRAR_ROLE, __( 'Registrar', 'mcupr' ), array_merge( array( 'read' => true ), $all ) );
		remove_role( MCUPR_STU_USER_ROLE );
		add_role( MCUPR_STU_USER_ROLE, __( 'Student (login)', 'mcupr' ), array( 'read' => true ) );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps as $c ) {
				$admin->add_cap( $c );
			}
		}
		update_option( 'mcupr_student_roles_v', $v );
	},
	8
);

/** Is this user a student with a login (role)? */
function mcupr_is_student_user( $user_id = 0 ) {
	$u = get_userdata( $user_id ? $user_id : get_current_user_id() );
	return $u && in_array( MCUPR_STU_USER_ROLE, (array) $u->roles, true );
}

/**
 * The student record of the person logged in, or 0. Only a student who is currently studying counts as "a student"
 * for the student-only content; $any = true returns the record whatever the status.
 */
function mcupr_current_student( $any = false, $user_id = 0 ) {
	static $cache = array();
	$uid = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $uid ) {
		return 0;
	}
	if ( ! isset( $cache[ $uid ] ) ) {
		$cache[ $uid ] = (int) get_user_meta( $uid, 'mcupr_student_id', true );
		if ( $cache[ $uid ] && MCUPR_STU !== get_post_type( $cache[ $uid ] ) ) {
			$cache[ $uid ] = 0;
		}
	}
	$id = $cache[ $uid ];
	if ( ! $id || $any ) {
		return $id;
	}
	return 'studying' === get_post_meta( $id, '_mcupr_s_status', true ) ? $id : 0;
}

/* ------------------------------------------------------------------ the editor and the list */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'mcupr_student', __( 'Student details', 'mcupr' ), 'mcupr_student_box', MCUPR_STU, 'normal', 'high' );
		add_meta_box( 'mcupr_student_acc', __( 'Login account', 'mcupr' ), 'mcupr_student_acc_box', MCUPR_STU, 'side', 'default' );
	}
);

function mcupr_student_box( $post ) {
	wp_nonce_field( 'mcupr_student_save', 'mcupr_student_nonce' );
	$d = mcupr_student_data( $post->ID );
	echo '<p class="description">' . esc_html__( 'The title above is the student\'s full name. This information is never shown on the website; only staff who manage students can see it.', 'mcupr' ) . '</p><table class="form-table" role="presentation">';
	$text = static function ( $k, $label, $val, $type = 'text' ) {
		echo '<tr><th scope="row"><label for="mcs-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input id="mcs-' . esc_attr( $k ) . '" class="regular-text" type="' . esc_attr( $type ) . '" name="mcupr_s[' . esc_attr( $k ) . ']" value="' . esc_attr( $val ) . '"></td></tr>';
	};
	$text( 'sid', __( 'Student ID', 'mcupr' ), $d['sid'] );
	$text( 'name_en', __( 'Name in English', 'mcupr' ), $d['name_en'] );
	foreach ( array( 'level' => mcupr_student_levels(), 'status' => mcupr_student_statuses() ) as $k => $opts ) {
		echo '<tr><th scope="row"><label for="mcs-' . esc_attr( $k ) . '">' . esc_html( mcupr_student_fields()[ $k ][0] ) . '</label></th><td><select id="mcs-' . esc_attr( $k ) . '" name="mcupr_s[' . esc_attr( $k ) . ']">';
		foreach ( $opts as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( '' === $d[ $k ] ? ( 'level' === $k ? 'bachelor' : 'studying' ) : $d[ $k ], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	$text( 'year', __( 'Year of study', 'mcupr' ), $d['year'], 'number' );
	$text( 'entry', __( 'Year of entry', 'mcupr' ), $d['entry'], 'number' );
	$text( 'faculty', __( 'Faculty', 'mcupr' ), $d['faculty'] );
	$text( 'major', __( 'Major / programme', 'mcupr' ), $d['major'] );
	$text( 'email', __( 'E-mail', 'mcupr' ), $d['email'], 'email' );
	$text( 'phone', __( 'Telephone', 'mcupr' ), $d['phone'] );
	echo '<tr><th scope="row"><label for="mcs-note">' . esc_html__( 'Note (staff only)', 'mcupr' ) . '</label></th><td><textarea id="mcs-note" class="large-text" rows="3" name="mcupr_s[note]">' . esc_textarea( $d['note'] ) . '</textarea></td></tr></table>';
}

add_action(
	'save_post_mcupr_student',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_student_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_student_nonce'] ) ), 'mcupr_student_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['mcupr_s'] ) && is_array( $_POST['mcupr_s'] ) ? wp_unslash( $_POST['mcupr_s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( mcupr_student_fields() as $k => $f ) {
			list( $v ) = mcupr_student_clean( $k, isset( $in[ $k ] ) && is_string( $in[ $k ] ) ? $in[ $k ] : '' );
			if ( 'sid' === $k ) {
				$other = '' !== $v ? mcupr_student_find( $v ) : 0;
				if ( '' === $v || ( $other && (int) $other !== (int) $post_id ) ) {
					continue; // an empty or already used ID is not saved: the old one stays
				}
			}
			'' === $v && ! in_array( $k, array( 'level', 'status' ), true ) ? delete_post_meta( $post_id, $f[1] ) : update_post_meta( $post_id, $f[1], $v );
		}
	}
);

function mcupr_student_acc_box( $post ) {
	$uid  = (int) get_post_meta( $post->ID, '_mcupr_s_user', true );
	$user = $uid ? get_userdata( $uid ) : null;
	if ( ! $user ) {
		echo '<p>' . esc_html__( 'No login account yet.', 'mcupr' ) . '</p>';
		if ( 'auto-draft' !== $post->post_status ) {
			$f = static function ( $action, $label ) use ( $post ) {
				echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&sid=' . (int) $post->ID ), 'mcupr_stu_' . $action . $post->ID ) ) . '">' . esc_html( $label ) . '</a></p>';
			};
			$f( 'mcupr_stu_make', __( 'Make a login account', 'mcupr' ) );
		}
		echo '<p class="description">' . esc_html__( 'Needs a valid e-mail address that no other account uses. The student sets a password through a link sent to it.', 'mcupr' ) . '</p>';
		return;
	}
	$sent = (int) get_user_meta( $uid, 'mcupr_activation_sent', true );
	$act  = (int) get_user_meta( $uid, 'mcupr_activated', true );
	echo '<p><strong>' . esc_html( $user->user_login ) . '</strong><br>' . esc_html( $user->user_email ) . '</p><p>' . esc_html( $act ? __( 'The student has set a password.', 'mcupr' ) : ( $sent ? __( 'Link sent, password not set yet.', 'mcupr' ) : __( 'No link sent yet.', 'mcupr' ) ) ) . '</p>';
	foreach ( array( 'mcupr_stu_send' => __( 'Send the link to set a password', 'mcupr' ), 'mcupr_stu_drop' => __( 'Delete the login account', 'mcupr' ) ) as $action => $label ) {
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&sid=' . (int) $post->ID ), 'mcupr_stu_' . $action . $post->ID ) ) . '">' . esc_html( $label ) . '</a></p>';
	}
}

/** The list: columns, sorting, filters, search by ID / e-mail, bulk actions. */
add_filter(
	'manage_mcupr_student_posts_columns',
	static function () {
		return array(
			'cb'           => '<input type="checkbox">',
			'mcs_sid'      => __( 'Student ID', 'mcupr' ),
			'title'        => __( 'Name', 'mcupr' ),
			'mcs_level'    => __( 'Level', 'mcupr' ),
			'mcs_year'     => __( 'Year', 'mcupr' ),
			'mcs_major'    => __( 'Major / programme', 'mcupr' ),
			'mcs_status'   => __( 'Status', 'mcupr' ),
			'mcs_account'  => __( 'Login', 'mcupr' ),
		);
	}
);
add_filter(
	'manage_edit-mcupr_student_sortable_columns',
	static function ( $c ) {
		$c['mcs_sid']  = 'mcs_sid';
		$c['mcs_year'] = 'mcs_year';
		return $c;
	}
);
add_action(
	'manage_mcupr_student_posts_custom_column',
	static function ( $col, $id ) {
		$d = mcupr_student_data( $id );
		switch ( $col ) {
			case 'mcs_sid':
				echo '<code>' . esc_html( $d['sid'] ) . '</code>';
				break;
			case 'mcs_level':
				$l = mcupr_student_levels();
				echo esc_html( isset( $l[ $d['level'] ] ) ? $l[ $d['level'] ] : '—' );
				break;
			case 'mcs_year':
				echo esc_html( '' !== $d['year'] ? $d['year'] : '—' );
				break;
			case 'mcs_major':
				echo esc_html( trim( $d['major'] . ( '' !== $d['faculty'] ? ' · ' . $d['faculty'] : '' ), ' ·' ) ?: '—' );
				break;
			case 'mcs_status':
				$s   = mcupr_student_statuses();
				$col = array( 'studying' => '#15803d', 'leave' => '#b45309', 'graduated' => '#1d4ed8', 'withdrawn' => '#6b7280' );
				$k   = isset( $s[ $d['status'] ] ) ? $d['status'] : 'studying';
				echo '<span style="display:inline-block;padding:1px 10px;border-radius:999px;background:' . esc_attr( $col[ $k ] ) . ';color:#fff;font-size:12px;font-weight:600">' . esc_html( $s[ $k ] ) . '</span>';
				break;
			case 'mcs_account':
				if ( ! $d['user'] || ! get_userdata( $d['user'] ) ) {
					echo '—';
				} elseif ( get_user_meta( $d['user'], 'mcupr_activated', true ) ) {
					echo '<span style="color:#15803d;font-weight:600">✓ ' . esc_html__( 'Active', 'mcupr' ) . '</span>';
				} else {
					echo '<span style="color:#b45309">✉ ' . esc_html( get_user_meta( $d['user'], 'mcupr_activation_sent', true ) ? __( 'Invited', 'mcupr' ) : __( 'Account made', 'mcupr' ) ) . '</span>';
				}
				break;
		}
	},
	10,
	2
);

add_action(
	'restrict_manage_posts',
	static function ( $type ) {
		if ( MCUPR_STU !== $type ) {
			return;
		}
		$cur = static function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		};
		foreach ( array( 'mcs_level' => array( __( 'All levels', 'mcupr' ), mcupr_student_levels() ), 'mcs_status' => array( __( 'All statuses', 'mcupr' ), mcupr_student_statuses() ) ) as $name => $set ) {
			echo '<select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $set[0] ) . '"><option value="">' . esc_html( $set[0] ) . '</option>';
			foreach ( $set[1] as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur( $name ), $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}
		echo '<input type="number" name="mcs_year" min="1" max="12" value="' . esc_attr( $cur( 'mcs_year' ) ) . '" placeholder="' . esc_attr__( 'Year', 'mcupr' ) . '" aria-label="' . esc_attr__( 'Year of study', 'mcupr' ) . '" style="width:6rem">';
	}
);

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || MCUPR_STU !== $q->get( 'post_type' ) ) {
			return;
		}
		$meta = array();
		foreach ( array( 'mcs_level' => '_mcupr_s_level', 'mcs_status' => '_mcupr_s_status', 'mcs_year' => '_mcupr_s_year' ) as $name => $key ) {
			if ( isset( $_GET[ $name ] ) && '' !== $_GET[ $name ] ) { // phpcs:ignore WordPress.Security.NonceVerification
				$meta[] = array( 'key' => $key, 'value' => sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}
		if ( $meta ) {
			$q->set( 'meta_query', $meta ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$ob = $q->get( 'orderby' );
		if ( 'mcs_sid' === $ob ) {
			$q->set( 'meta_key', '_mcupr_s_sid' );
			$q->set( 'orderby', 'meta_value' );
		} elseif ( 'mcs_year' === $ob ) {
			$q->set( 'meta_key', '_mcupr_s_year' );
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( '' === $ob ) {
			$q->set( 'meta_key', '_mcupr_s_sid' );
			$q->set( 'orderby', 'meta_value' );
			$q->set( 'order', 'ASC' );
		}
	}
);

/** Search the list by name, student ID or e-mail. */
add_filter(
	'posts_search',
	static function ( $search, $q ) {
		global $wpdb;
		if ( ! is_admin() || ! $q->is_main_query() || MCUPR_STU !== $q->get( 'post_type' ) || '' === (string) $q->get( 's' ) ) {
			return $search;
		}
		$like = '%' . $wpdb->esc_like( (string) $q->get( 's' ) ) . '%';
		return $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_mcupr_s_sid','_mcupr_s_email','_mcupr_s_major') AND meta_value LIKE %s))", $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL
	},
	10,
	2
);

/** Bulk actions: status, accounts, e-mail links. */
add_filter(
	'bulk_actions-edit-mcupr_student',
	static function ( $a ) {
		$new = array();
		foreach ( mcupr_student_statuses() as $k => $label ) {
			/* translators: %s: status */
			$new[ 'mcs_set_' . $k ] = sprintf( __( 'Set status: %s', 'mcupr' ), $label );
		}
		$new['mcs_make'] = __( 'Make login accounts', 'mcupr' );
		$new['mcs_send'] = __( 'Send the link to set a password', 'mcupr' );
		$new['mcs_drop'] = __( 'Delete login accounts', 'mcupr' );
		return array_merge( $a, $new );
	}
);
add_filter(
	'handle_bulk_actions-edit-mcupr_student',
	static function ( $redirect, $action, $ids ) {
		if ( 0 !== strpos( $action, 'mcs_' ) || ! current_user_can( MCUPR_STU_CAP ) ) {
			return $redirect;
		}
		$n = 0;
		$e = 0;
		foreach ( array_slice( array_map( 'absint', $ids ), 0, 500 ) as $id ) {
			if ( 0 === strpos( $action, 'mcs_set_' ) ) {
				$k = substr( $action, 8 );
				if ( isset( mcupr_student_statuses()[ $k ] ) ) {
					update_post_meta( $id, '_mcupr_s_status', $k );
					$n++;
				}
			} elseif ( 'mcs_make' === $action ) {
				$r = mcupr_student_make_account( $id );
				is_wp_error( $r ) ? $e++ : $n++;
			} elseif ( 'mcs_send' === $action ) {
				$r = mcupr_student_send_activation( $id );
				is_wp_error( $r ) ? $e++ : $n++;
			} elseif ( 'mcs_drop' === $action ) {
				mcupr_student_drop_account( $id ) ? $n++ : $e++;
			}
		}
		return add_query_arg( array( 'mcs_done' => $n, 'mcs_err' => $e ), $redirect );
	},
	10,
	3
);
add_action(
	'admin_notices',
	static function () {
		if ( ! isset( $_GET['mcs_done'] ) || 'mcupr_student' !== get_current_screen()->post_type ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$n = (int) $_GET['mcs_done']; // phpcs:ignore WordPress.Security.NonceVerification
		$e = isset( $_GET['mcs_err'] ) ? (int) $_GET['mcs_err'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		/* translators: 1: done, 2: not done */
		echo '<div class="notice notice-' . ( $e ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( sprintf( __( 'Done: %1$d. Not done (no e-mail, e-mail used by someone else, no account…): %2$d.', 'mcupr' ), $n, $e ) ) . '</p></div>';
	}
);

/* ------------------------------------------------------------------ privacy: WordPress' export and erase tools */

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $e ) {
		$e['mcupr-student'] = array(
			'exporter_friendly_name' => __( 'Student record', 'mcupr' ),
			'callback'               => static function ( $email ) {
				$out = array();
				foreach ( get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'meta_key' => '_mcupr_s_email', 'meta_value' => strtolower( $email ), 'posts_per_page' => 20, 'no_found_rows' => true ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
					$data = array( array( 'name' => __( 'Name', 'mcupr' ), 'value' => $p->post_title ) );
					foreach ( mcupr_student_fields() as $k => $f ) {
						$v = (string) get_post_meta( $p->ID, $f[1], true );
						if ( '' !== $v ) {
							$data[] = array( 'name' => $f[0], 'value' => $v );
						}
					}
					$out[] = array( 'group_id' => 'mcupr-student', 'group_label' => __( 'Student record', 'mcupr' ), 'item_id' => 'student-' . $p->ID, 'data' => $data );
				}
				return array( 'data' => $out, 'done' => true );
			},
		);
		return $e;
	}
);
add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $e ) {
		$e['mcupr-student'] = array(
			'eraser_friendly_name' => __( 'Student record', 'mcupr' ),
			'callback'             => static function ( $email ) {
				$n = 0;
				foreach ( get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'meta_key' => '_mcupr_s_email', 'meta_value' => strtolower( $email ), 'posts_per_page' => 20, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
					wp_delete_post( $id, true );
					$n++;
				}
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $e;
	}
);

/** Deleting a record deletes its login account too (when it is a student account). */
add_action(
	'before_delete_post',
	static function ( $id ) {
		if ( MCUPR_STU === get_post_type( $id ) ) {
			mcupr_student_drop_account( $id );
		}
	}
);

/** The student list needs no "all dates" filter. */
add_filter(
	'disable_months_dropdown',
	static function ( $disable, $post_type ) {
		return MCUPR_STU === $post_type ? true : $disable;
	},
	10,
	2
);
