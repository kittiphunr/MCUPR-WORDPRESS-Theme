<?php
/**
 * Student-only content (ผู้เข้าชม: นิสิตเท่านั้น).
 *
 * News, pages, events, works, albums and videos have a box "Who can see this": everyone, or students only (all students who
 * are studying, or only some levels). Content for students only
 *   - is not shown on the home page, news lists, search, feeds, sitemaps, the REST API or next / previous links to anyone else;
 *   - sends a visitor who is not logged in to the student portal and then back to the page; a logged-in person who is not
 *     a student sees a polite "students only" page (403);
 *   - keeps its attached files (downloads) behind the same rule;
 *   - is listed in the student portal.
 * Staff who write content (and registrars) always see everything. Pictures put inside the text keep the address of the
 * Media Library – put files that must stay private in the "Files" box of the content, which is served by the theme.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_AUD = '_mcupr_aud';

/** The kinds of content that can be for students only. */
function mcupr_aud_types() {
	$t = function_exists( 'mcupr_tool_types' ) ? mcupr_tool_types() : array( 'post', 'page' );
	return array_values( array_unique( array_merge( $t, array( 'mcupr_album', 'mcupr_video' ) ) ) );
}

/** The index of the restricted content: post id => array of levels (empty = every student). Kept in one option. */
function mcupr_aud_index() {
	if ( isset( $GLOBALS['mcupr_aud_cache']['idx'] ) ) {
		return $GLOBALS['mcupr_aud_cache']['idx'];
	}
	$idx = get_option( 'mcupr_aud_index', null );
	if ( ! is_array( $idx ) ) {
		$idx = array();
		$ids = get_posts( array( 'post_type' => array_values( get_post_types() ), 'post_status' => array( 'publish', 'private', 'future' ), 'meta_key' => MCUPR_AUD, 'meta_value' => 'students', 'posts_per_page' => 3000, 'fields' => 'ids', 'no_found_rows' => true, 'mcupr_office_all' => 1, 'mcupr_aud_all' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $ids as $id ) {
			$lv         = get_post_meta( $id, '_mcupr_aud_levels', true );
			$idx[ $id ] = is_array( $lv ) ? array_values( array_intersect( $lv, array_keys( mcupr_student_levels() ) ) ) : array();
		}
		update_option( 'mcupr_aud_index', $idx, true );
	}
	$GLOBALS['mcupr_aud_cache']['idx'] = $idx;
	return $idx;
}

function mcupr_aud_reset() {
	delete_option( 'mcupr_aud_index' );
	$GLOBALS['mcupr_aud_cache'] = array();
}
foreach ( array( 'deleted_post', 'trashed_post', 'untrashed_post' ) as $mcupr_h ) {
	add_action( $mcupr_h, 'mcupr_aud_reset' );
}
add_action( 'transition_post_status', 'mcupr_aud_reset' );

/** Is this content for students only? */
function mcupr_aud_is_restricted( $post_id ) {
	return isset( mcupr_aud_index()[ (int) $post_id ] );
}

/** Who is looking: staff (see all) or a student (level) or neither. */
function mcupr_aud_viewer() {
	$uid = get_current_user_id();
	if ( isset( $GLOBALS['mcupr_aud_cache']['v'][ $uid ] ) ) {
		return $GLOBALS['mcupr_aud_cache']['v'][ $uid ];
	}
	$v = array( 'staff' => false, 'student' => 0, 'level' => '' );
	if ( $uid ) {
		$v['staff'] = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( MCUPR_STU_CAP );
		$sid        = mcupr_current_student();
		if ( $sid ) {
			$v['student'] = $sid;
			$v['level']   = (string) get_post_meta( $sid, '_mcupr_s_level', true );
		}
	}
	$GLOBALS['mcupr_aud_cache']['v'][ $uid ] = $v;
	return $v;
}

/** May the person looking see this content? */
function mcupr_aud_can_view( $post_id ) {
	$idx = mcupr_aud_index();
	$id  = (int) $post_id;
	if ( ! isset( $idx[ $id ] ) ) {
		return true;
	}
	$v = mcupr_aud_viewer();
	if ( $v['staff'] ) {
		return true;
	}
	return $v['student'] && ( ! $idx[ $id ] || in_array( $v['level'], $idx[ $id ], true ) );
}

/** Ids this person must not see. */
function mcupr_aud_hidden_ids() {
	$uid = get_current_user_id();
	if ( isset( $GLOBALS['mcupr_aud_cache']['h'][ $uid ] ) ) {
		return $GLOBALS['mcupr_aud_cache']['h'][ $uid ];
	}
	$h = array();
	foreach ( array_keys( mcupr_aud_index() ) as $id ) {
		if ( ! mcupr_aud_can_view( $id ) ) {
			$h[] = (int) $id;
		}
	}
	$GLOBALS['mcupr_aud_cache']['h'][ $uid ] = $h;
	return $h;
}

/* ------------------------------------------------------------------ the box on the editor */

add_action(
	'add_meta_boxes',
	static function () {
		foreach ( mcupr_aud_types() as $t ) {
			add_meta_box( 'mcupr_aud', __( 'Who can see this', 'mcupr' ), 'mcupr_aud_box', $t, 'side', 'default' );
		}
	}
);

function mcupr_aud_box( $post ) {
	wp_nonce_field( 'mcupr_aud_save', 'mcupr_aud_nonce' );
	$on = 'students' === get_post_meta( $post->ID, MCUPR_AUD, true );
	$lv = get_post_meta( $post->ID, '_mcupr_aud_levels', true );
	$lv = is_array( $lv ) ? $lv : array();
	echo '<p><label><input type="radio" name="mcupr_aud" value=""' . checked( ! $on, true, false ) . '> ' . esc_html__( 'Everyone', 'mcupr' ) . '</label><br><label><input type="radio" name="mcupr_aud" value="students"' . checked( $on, true, false ) . '> <strong>' . esc_html__( 'Students only', 'mcupr' ) . '</strong> ' . esc_html__( '(logged in, currently studying)', 'mcupr' ) . '</label></p>';
	echo '<fieldset style="margin:0 0 .5rem"><legend class="description">' . esc_html__( 'Only these levels (none ticked = every student):', 'mcupr' ) . '</legend>';
	foreach ( mcupr_student_levels() as $k => $label ) {
		echo '<label style="display:block"><input type="checkbox" name="mcupr_aud_levels[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $lv, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset><p class="description">' . esc_html__( 'Content for students only is hidden from everyone else everywhere on the website, and its files can only be downloaded by students.', 'mcupr' ) . '</p>';
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['mcupr_aud_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_aud_nonce'] ) ), 'mcupr_aud_save' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! in_array( $post->post_type, mcupr_aud_types(), true ) ) {
			return;
		}
		$on = isset( $_POST['mcupr_aud'] ) && 'students' === $_POST['mcupr_aud'];
		$on ? update_post_meta( $post_id, MCUPR_AUD, 'students' ) : delete_post_meta( $post_id, MCUPR_AUD );
		$lv = isset( $_POST['mcupr_aud_levels'] ) && is_array( $_POST['mcupr_aud_levels'] ) ? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['mcupr_aud_levels'] ) ), array_keys( mcupr_student_levels() ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$on && $lv ? update_post_meta( $post_id, '_mcupr_aud_levels', $lv ) : delete_post_meta( $post_id, '_mcupr_aud_levels' );
		mcupr_aud_reset();
	},
	30,
	2
);

/** A lock in the lists of the editor. */
add_action(
	'admin_init',
	static function () {
		foreach ( mcupr_aud_types() as $t ) {
			add_filter(
				"manage_{$t}_posts_columns",
				static function ( $c ) {
					$c['mcupr_aud'] = __( 'Who sees it', 'mcupr' );
					return $c;
				}
			);
			add_action(
				"manage_{$t}_posts_custom_column",
				static function ( $col, $id ) {
					if ( 'mcupr_aud' === $col ) {
						$lv = mcupr_aud_is_restricted( $id ) ? mcupr_aud_index()[ $id ] : null;
						if ( null === $lv ) {
							echo '—';
						} else {
							$names = mcupr_student_levels();
							echo '<span style="display:inline-block;padding:1px 10px;border-radius:999px;background:#1d4ed8;color:#fff;font-size:12px;font-weight:600">🔒 ' . esc_html__( 'Students', 'mcupr' ) . '</span>';
							if ( $lv ) {
								echo '<br><small>' . esc_html( implode( ', ', array_map( static function ( $k ) use ( $names ) { return isset( $names[ $k ] ) ? $names[ $k ] : $k; }, $lv ) ) ) . '</small>';
							}
						}
					}
				},
				10,
				2
			);
		}
	}
);

/* ------------------------------------------------------------------ keeping it from everyone else */

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() || $q->get( 'mcupr_aud_all' ) || $q->is_singular() || $q->get( 'p' ) || $q->get( 'page_id' ) || $q->get( 'name' ) || $q->get( 'pagename' ) || $q->get( 'post__in' ) || $q->get( 'attachment_id' ) ) {
			return;
		}
		$hide = mcupr_aud_hidden_ids();
		if ( $hide ) {
			$q->set( 'post__not_in', array_values( array_unique( array_merge( (array) $q->get( 'post__not_in' ), $hide ) ) ) );
		}
	},
	20
);

add_filter(
	'get_pages',
	static function ( $pages ) {
		$hide = mcupr_aud_hidden_ids();
		if ( ! $hide || ! is_array( $pages ) ) {
			return $pages;
		}
		return array_values( array_filter( $pages, static function ( $p ) use ( $hide ) { return ! is_object( $p ) || ! in_array( (int) $p->ID, $hide, true ); } ) );
	}
);

/** Next / previous article links must not name content this person may not see. */
foreach ( array( 'previous', 'next' ) as $mcupr_dir ) {
	add_filter(
		"get_{$mcupr_dir}_post_where",
		static function ( $where ) {
			$hide = mcupr_aud_hidden_ids();
			return $hide ? $where . ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $hide ) ) . ')' : $where;
		}
	);
}

/** The REST API: no text of restricted content for anyone who may not see it. */
add_action(
	'rest_api_init',
	static function () {
		foreach ( mcupr_aud_types() as $t ) {
			add_filter(
				"rest_prepare_{$t}",
				static function ( $response, $post ) {
					if ( $post && ! mcupr_aud_can_view( $post->ID ) ) {
						$d = $response->get_data();
						foreach ( array( 'content', 'excerpt' ) as $k ) {
							if ( isset( $d[ $k ] ) ) {
								$d[ $k ] = array( 'rendered' => '', 'protected' => true );
							}
						}
						if ( isset( $d['title'] ) ) {
							$d['title'] = array( 'rendered' => mcupr_t( 'stu_only' ) );
						}
						$response->set_data( $d );
					}
					return $response;
				},
				10,
				2
			);
		}
	}
);

add_filter(
	'wp_robots',
	static function ( $r ) {
		if ( is_singular() && mcupr_aud_is_restricted( get_queried_object_id() ) ) {
			$r['noindex'] = true;
		}
		return $r;
	}
);

/** The page itself: a visitor goes to log in, someone else gets a polite "students only". */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! $id || mcupr_aud_can_view( $id ) ) {
			return;
		}
		nocache_headers();
		if ( ! is_user_logged_in() && mcupr_stu_opts()['portal'] ) {
			wp_safe_redirect( mcupr_student_portal_url( array( 'e' => 'need', 'redirect_to' => rawurlencode( get_permalink( $id ) ) ) ) );
			exit;
		}
		status_header( 403 );
		get_header();
		echo '<div class="wrap">';
		mcupr_state_card(
			'alert',
			mcupr_t( 'stu_denied_t' ),
			mcupr_t( is_user_logged_in() ? 'stu_denied_txt2' : 'stu_denied_txt' ),
			'<p class="mt-6"><a class="btn" href="' . esc_url( mcupr_stu_opts()['portal'] ? mcupr_student_portal_url() : mcupr_home_url() ) . '">' . esc_html( mcupr_t( mcupr_stu_opts()['portal'] ? 'stu_portal' : 'back_home' ) ) . '</a></p>'
		);
		echo '</div>';
		get_footer();
		exit;
	},
	1
);

/** The content the person looking may see that is for students only, newest first (for the portal). */
function mcupr_aud_items( $limit = 30 ) {
	$ids = array_values( array_filter( array_keys( mcupr_aud_index() ), 'mcupr_aud_can_view' ) );
	if ( ! $ids ) {
		return array();
	}
	return get_posts( array( 'post_type' => mcupr_aud_types(), 'post__in' => $ids, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC', 'posts_per_page' => $limit, 'no_found_rows' => true, 'mcupr_aud_all' => 1, 'mcupr_office_all' => 1 ) );
}
