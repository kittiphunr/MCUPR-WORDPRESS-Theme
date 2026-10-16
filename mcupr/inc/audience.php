<?php
/**
 * Student-only content (ผู้เข้าชม: นิสิตเท่านั้น).
 *
 * News, pages, events, works, albums and videos have a box "Who can see this": everyone, or students only (all students who
 * are studying, or only some levels, years of study, faculties or majors). Content for students only
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

/* ------------------------------------------------------------------ who may see it: the rules */

/** Text compared without caring about case or repeated spaces ("คณะ  พุทธศาสตร์" = "คณะ พุทธศาสตร์"). */
function mcupr_aud_norm( $s ) {
	$s = trim( preg_replace( '/\s+/u', ' ', (string) $s ) );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
}

/** Years of study that can be ticked (key => label); 5 means "5 or more". */
function mcupr_aud_years() {
	return array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5+' );
}

/** A year of study as one of the keys above ('' when unknown). */
function mcupr_aud_year_key( $y ) {
	$n = (int) $y;
	return $n < 1 ? '' : ( $n >= 5 ? '5' : (string) $n );
}

/** The separator of a faculty and a major in one value. */
const MCUPR_AUD_SEP = '||';

/**
 * The rules saved on a content item, as typed (labels): levels, years, fac (faculty names), maj ("faculty||major").
 * An empty group means "no limit".
 */
function mcupr_aud_rules( $post_id ) {
	$r = array( 'levels' => array(), 'years' => array(), 'fac' => array(), 'maj' => array() );
	$g = static function ( $key ) use ( $post_id ) {
		$v = get_post_meta( $post_id, $key, true );
		return is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ), 'strlen' ) ) : array();
	};
	$r['levels'] = array_values( array_intersect( $g( '_mcupr_aud_levels' ), array_keys( mcupr_student_levels() ) ) );
	$r['years']  = array_values( array_intersect( $g( '_mcupr_aud_years' ), array_map( 'strval', array_keys( mcupr_aud_years() ) ) ) );
	$r['fac']    = $g( '_mcupr_aud_fac' );
	$r['maj']    = $g( '_mcupr_aud_maj' );
	return $r;
}

/** The rules ready for the checks (faculty and major compared in normal form). */
function mcupr_aud_rules_norm( $r ) {
	$maj = array();
	foreach ( $r['maj'] as $m ) {
		$p = explode( MCUPR_AUD_SEP, $m, 2 );
		if ( 2 === count( $p ) ) {
			$maj[] = mcupr_aud_norm( $p[0] ) . MCUPR_AUD_SEP . mcupr_aud_norm( $p[1] );
		}
	}
	return array(
		'levels' => $r['levels'],
		'years'  => $r['years'],
		'fac'    => array_values( array_unique( array_map( 'mcupr_aud_norm', $r['fac'] ) ) ),
		'maj'    => array_values( array_unique( $maj ) ),
	);
}

/** The index of the restricted content: post id => rules (all groups empty = every student). Kept in one option. */
function mcupr_aud_index() {
	if ( isset( $GLOBALS['mcupr_aud_cache']['idx'] ) ) {
		return $GLOBALS['mcupr_aud_cache']['idx'];
	}
	if ( ! empty( $GLOBALS['mcupr_aud_building'] ) ) {
		return array(); // the query that builds the index must not ask for the index (it filters its own query)
	}
	$idx = get_option( 'mcupr_aud_index2', null );
	if ( ! is_array( $idx ) ) {
		$GLOBALS['mcupr_aud_building'] = true;
		$idx = array();
		$ids = get_posts( array( 'post_type' => array_values( get_post_types() ), 'post_status' => array( 'publish', 'private', 'future' ), 'meta_key' => MCUPR_AUD, 'meta_value' => 'students', 'posts_per_page' => 3000, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $ids as $id ) {
			$idx[ $id ] = mcupr_aud_rules_norm( mcupr_aud_rules( $id ) );
		}
		$GLOBALS['mcupr_aud_building'] = false;
		update_option( 'mcupr_aud_index2', $idx, true );
	}
	$GLOBALS['mcupr_aud_cache']['idx'] = $idx;
	return $idx;
}

function mcupr_aud_reset() {
	delete_option( 'mcupr_aud_index2' );
	delete_option( 'mcupr_aud_index' ); // the older form (levels only)
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

/** Who is looking: staff (see all) or a student (level, year, faculty, major) or neither. */
function mcupr_aud_viewer() {
	$uid = get_current_user_id();
	if ( isset( $GLOBALS['mcupr_aud_cache']['v'][ $uid ] ) ) {
		return $GLOBALS['mcupr_aud_cache']['v'][ $uid ];
	}
	$v = array( 'staff' => false, 'student' => 0, 'level' => '', 'year' => '', 'fac' => '', 'maj' => '' );
	if ( $uid ) {
		$v['staff'] = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) || current_user_can( MCUPR_STU_CAP );
		$sid        = mcupr_current_student();
		if ( $sid ) {
			$v['student'] = $sid;
			$v['level']   = (string) get_post_meta( $sid, '_mcupr_s_level', true );
			$v['year']    = mcupr_aud_year_key( get_post_meta( $sid, '_mcupr_s_year', true ) );
			$v['fac']     = mcupr_aud_norm( get_post_meta( $sid, '_mcupr_s_faculty', true ) );
			$v['maj']     = $v['fac'] . MCUPR_AUD_SEP . mcupr_aud_norm( get_post_meta( $sid, '_mcupr_s_major', true ) );
		}
	}
	$GLOBALS['mcupr_aud_cache']['v'][ $uid ] = $v;
	return $v;
}

/**
 * Do these rules let this student in? Within a group "or", between groups "and"; an empty group is no limit.
 * Faculty and major are one group: the faculty ticked (all its majors) or the major ticked.
 */
function mcupr_aud_matches( $rules, $v ) {
	if ( $rules['levels'] && ! in_array( $v['level'], $rules['levels'], true ) ) {
		return false;
	}
	if ( $rules['years'] && ! in_array( $v['year'], $rules['years'], true ) ) {
		return false;
	}
	if ( ( $rules['fac'] || $rules['maj'] ) && ! ( in_array( $v['fac'], $rules['fac'], true ) || in_array( $v['maj'], $rules['maj'], true ) ) ) {
		return false;
	}
	return true;
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
	return $v['student'] && mcupr_aud_matches( $idx[ $id ], $v );
}

/** Ids this person must not see. */
function mcupr_aud_hidden_ids() {
	if ( ! empty( $GLOBALS['mcupr_aud_building'] ) ) {
		return array(); // not cached: only the query that builds the index asks while it is being built
	}
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

/**
 * What can be ticked for faculty / major, and how many students (studying) each combination has:
 * 'combos' rows [level, year key, faculty (normal form), "faculty||major" (normal form), count], 'fac' faculty (normal form) =>
 * [label, count], 'maj' faculty => [normal "faculty||major" => [major label, count]]. Kept for ten minutes.
 */
function mcupr_aud_options() {
	$c = get_transient( 'mcupr_aud_opts' );
	if ( is_array( $c ) ) {
		return $c;
	}
	global $wpdb;
	$sql  = "SELECT l.meta_value AS lvl, y.meta_value AS yr, f.meta_value AS fac, m.meta_value AS maj, COUNT(*) AS n
		FROM {$wpdb->posts} p
		JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_mcupr_s_status' AND s.meta_value = 'studying'
		LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_mcupr_s_level'
		LEFT JOIN {$wpdb->postmeta} y ON y.post_id = p.ID AND y.meta_key = '_mcupr_s_year'
		LEFT JOIN {$wpdb->postmeta} f ON f.post_id = p.ID AND f.meta_key = '_mcupr_s_faculty'
		LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_mcupr_s_major'
		WHERE p.post_type = %s AND p.post_status IN ('publish','private','draft')
		GROUP BY l.meta_value, y.meta_value, f.meta_value, m.meta_value";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, MCUPR_STU ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$o    = array( 'combos' => array(), 'fac' => array(), 'maj' => array(), 'total' => 0 );
	$best = array();
	foreach ( (array) $rows as $r ) {
		$n    = (int) $r->n;
		$fn   = mcupr_aud_norm( $r->fac );
		$mn   = $fn . MCUPR_AUD_SEP . mcupr_aud_norm( $r->maj );
		$o['combos'][] = array( (string) $r->lvl, mcupr_aud_year_key( $r->yr ), $fn, $mn, $n );
		$o['total']   += $n;
		$fl            = trim( preg_replace( '/\s+/u', ' ', (string) $r->fac ) );
		$ml            = trim( preg_replace( '/\s+/u', ' ', (string) $r->maj ) );
		if ( '' !== $fl ) {
			$o['fac'][ $fn ] = array( isset( $o['fac'][ $fn ] ) ? $o['fac'][ $fn ][0] : $fl, ( isset( $o['fac'][ $fn ] ) ? $o['fac'][ $fn ][1] : 0 ) + $n );
			$best[ $fn ]     = isset( $best[ $fn ] ) && $best[ $fn ][1] >= $n ? $best[ $fn ] : array( $fl, $n );
			if ( '' !== $ml ) {
				$prev                  = isset( $o['maj'][ $fn ][ $mn ] ) ? $o['maj'][ $fn ][ $mn ] : array( $ml, 0 );
				$o['maj'][ $fn ][ $mn ] = array( $prev[0], $prev[1] + $n );
			}
		}
	}
	foreach ( $best as $fn => $b ) {
		$o['fac'][ $fn ][0] = $b[0]; // the spelling most students have
	}
	uasort( $o['fac'], static function ( $x, $y ) { return strcoll( $x[0], $y[0] ); } );
	set_transient( 'mcupr_aud_opts', $o, 10 * MINUTE_IN_SECONDS );
	return $o;
}
foreach ( array( 'save_post_' . MCUPR_STU, 'deleted_post' ) as $mcupr_h ) {
	add_action( $mcupr_h, static function () { delete_transient( 'mcupr_aud_opts' ); } );
}

function mcupr_aud_box( $post ) {
	wp_nonce_field( 'mcupr_aud_save', 'mcupr_aud_nonce' );
	$on  = 'students' === get_post_meta( $post->ID, MCUPR_AUD, true );
	$r   = mcupr_aud_rules( $post->ID );
	$o   = mcupr_aud_options();
	$nr  = mcupr_aud_rules_norm( $r );
	echo '<div class="mcupr-aud" data-combos="' . esc_attr( wp_json_encode( $o['combos'] ) ) . '" data-total="' . (int) $o['total'] . '" data-sep="' . esc_attr( MCUPR_AUD_SEP ) . '">';
	echo '<p><label><input type="radio" name="mcupr_aud" value=""' . checked( ! $on, true, false ) . '> ' . esc_html__( 'Everyone', 'mcupr' ) . '</label><br><label><input type="radio" name="mcupr_aud" value="students"' . checked( $on, true, false ) . '> <strong>' . esc_html__( 'Students only', 'mcupr' ) . '</strong> ' . esc_html__( '(logged in, currently studying)', 'mcupr' ) . '</label></p>';
	echo '<div class="mcupr-aud-rules"' . ( $on ? '' : ' hidden' ) . '>';
	echo '<fieldset style="margin:0 0 .5rem"><legend class="description">' . esc_html__( 'Only these levels (none ticked = every level):', 'mcupr' ) . '</legend>';
	foreach ( mcupr_student_levels() as $k => $label ) {
		echo '<label style="display:block"><input type="checkbox" name="mcupr_aud_levels[]" value="' . esc_attr( $k ) . '" data-k="' . esc_attr( $k ) . '" data-g="level"' . checked( in_array( $k, $r['levels'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset><fieldset style="margin:0 0 .5rem"><legend class="description">' . esc_html__( 'Only these years of study (none ticked = every year):', 'mcupr' ) . '</legend>';
	foreach ( mcupr_aud_years() as $k => $label ) {
		echo '<label style="display:inline-block;margin-right:.75rem"><input type="checkbox" name="mcupr_aud_years[]" value="' . esc_attr( $k ) . '" data-k="' . esc_attr( $k ) . '" data-g="year"' . checked( in_array( (string) $k, $r['years'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset><fieldset style="margin:0 0 .5rem"><legend class="description">' . esc_html__( 'Only these faculties / majors (none ticked = every faculty):', 'mcupr' ) . '</legend>';
	// what is ticked but no student has any more is still shown, so it can be unticked
	$facs = $o['fac'];
	foreach ( $r['fac'] as $fl ) {
		$fn = mcupr_aud_norm( $fl );
		if ( ! isset( $facs[ $fn ] ) ) {
			$facs[ $fn ] = array( $fl, 0 );
		}
	}
	$majs = $o['maj'];
	foreach ( $r['maj'] as $mv ) {
		$pp = explode( MCUPR_AUD_SEP, $mv, 2 );
		if ( 2 !== count( $pp ) ) {
			continue;
		}
		$fn = mcupr_aud_norm( $pp[0] );
		$mn = $fn . MCUPR_AUD_SEP . mcupr_aud_norm( $pp[1] );
		if ( ! isset( $facs[ $fn ] ) ) {
			$facs[ $fn ] = array( $pp[0], 0 );
		}
		if ( ! isset( $majs[ $fn ][ $mn ] ) ) {
			$majs[ $fn ][ $mn ] = array( $pp[1], 0 );
		}
	}
	if ( ! $facs ) {
		echo '<p class="description">' . esc_html__( 'No faculty is in the student database yet.', 'mcupr' ) . '</p>';
	}
	foreach ( $facs as $fn => $f ) {
		$fon  = in_array( $fn, $nr['fac'], true );
		$kids = isset( $majs[ $fn ] ) ? $majs[ $fn ] : array();
		$any  = $fon;
		foreach ( $kids as $mn => $m ) {
			$any = $any || in_array( $mn, $nr['maj'], true );
		}
		echo '<details' . ( $any ? ' open' : '' ) . '><summary><label><input type="checkbox" name="mcupr_aud_fac[]" value="' . esc_attr( $f[0] ) . '" data-k="' . esc_attr( $fn ) . '" data-g="fac"' . checked( $fon, true, false ) . '> ' . esc_html( $f[0] ) . ' <span class="description">(' . (int) $f[1] . ')</span></label></summary><div style="margin-left:1.5rem">';
		foreach ( $kids as $mn => $m ) {
			echo '<label style="display:block"><input type="checkbox" name="mcupr_aud_maj[]" value="' . esc_attr( $f[0] . MCUPR_AUD_SEP . $m[0] ) . '" data-k="' . esc_attr( $mn ) . '" data-g="maj" data-f="' . esc_attr( $fn ) . '"' . checked( $fon || in_array( $mn, $nr['maj'], true ), true, false ) . ( $fon ? ' disabled' : '' ) . '> ' . esc_html( $m[0] ) . ' <span class="description">(' . (int) $m[1] . ')</span></label>';
		}
		echo '</div></details>';
	}
	echo '</fieldset><p class="mcupr-aud-count" aria-live="polite" hidden style="margin:.5rem 0;padding:6px 8px;background:#f0f6fc;border-left:4px solid #2271b1"></p>';
	echo '<p class="description">' . esc_html__( 'Inside one group a student needs any one of the ticked boxes; across groups all groups must match. Ticking a faculty means all its majors.', 'mcupr' ) . '</p></div>';
	echo '<p class="description">' . esc_html__( 'Content for students only is hidden from everyone else everywhere on the website, and its files can only be downloaded by students.', 'mcupr' ) . '</p></div>';
}

/** The live count and the faculty → majors ticking in the box. */
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$s = get_current_screen();
		if ( $s && in_array( $s->post_type, mcupr_aud_types(), true ) ) {
			wp_enqueue_script( 'mcupr-aud-admin', get_theme_file_uri( 'assets/js/audience-admin.js' ), array(), MCUPR_VERSION, true );
			wp_localize_script( 'mcupr-aud-admin', 'mcuprAud', array( 'count' => __( 'Visible to %1$d of %2$d students who are studying now.', 'mcupr' ) ) );
		}
	}
);

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
		$list = static function ( $key, $allowed = null ) {
			$in = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
			$out = array();
			foreach ( array_slice( $in, 0, 400 ) as $v ) {
				$v = is_string( $v ) ? trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $v ) ) ) : '';
				if ( '' !== $v && ( null === $allowed || in_array( $v, $allowed, true ) ) ) {
					$out[] = $v;
				}
			}
			return array_values( array_unique( $out ) );
		};
		$lv  = $list( 'mcupr_aud_levels', array_keys( mcupr_student_levels() ) );
		$yr  = $list( 'mcupr_aud_years', array_map( 'strval', array_keys( mcupr_aud_years() ) ) ); // (array keys like '1' are integers)
		$fac = $list( 'mcupr_aud_fac' );
		$fn  = array_map( 'mcupr_aud_norm', $fac );
		$maj = array();
		foreach ( $list( 'mcupr_aud_maj' ) as $m ) { // a major of a ticked faculty is already in
			$pp = explode( MCUPR_AUD_SEP, $m, 2 );
			if ( 2 === count( $pp ) && '' !== trim( $pp[0] ) && '' !== trim( $pp[1] ) && ! in_array( mcupr_aud_norm( $pp[0] ), $fn, true ) ) {
				$maj[] = trim( $pp[0] ) . MCUPR_AUD_SEP . trim( $pp[1] );
			}
		}
		foreach ( array( '_mcupr_aud_levels' => $lv, '_mcupr_aud_years' => $yr, '_mcupr_aud_fac' => $fac, '_mcupr_aud_maj' => $maj ) as $key => $val ) {
			$on && $val ? update_post_meta( $post_id, $key, $val ) : delete_post_meta( $post_id, $key );
		}
		mcupr_aud_reset();
	},
	30,
	2
);

/** The rules in one line (levels · years · faculties / majors) for lists; '' = every student. */
function mcupr_aud_summary( $r ) {
	$names = mcupr_student_levels();
	$parts = array();
	if ( $r['levels'] ) {
		$parts[] = implode( ', ', array_map( static function ( $k ) use ( $names ) { return isset( $names[ $k ] ) ? $names[ $k ] : $k; }, $r['levels'] ) );
	}
	if ( $r['years'] ) {
		$y       = mcupr_aud_years();
		$parts[] = sprintf( /* translators: %s: 1, 2 */ __( 'Year %s', 'mcupr' ), implode( ', ', array_map( static function ( $k ) use ( $y ) { return $y[ $k ]; }, $r['years'] ) ) );
	}
	$org = $r['fac'];
	foreach ( $r['maj'] as $m ) {
		$org[] = str_replace( MCUPR_AUD_SEP, ' / ', $m );
	}
	if ( $org ) {
		$parts[] = implode( ', ', array_slice( $org, 0, 3 ) ) . ( count( $org ) > 3 ? ' +' . ( count( $org ) - 3 ) : '' );
	}
	return implode( ' · ', $parts );
}

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
						if ( ! mcupr_aud_is_restricted( $id ) ) {
							echo '—';
						} else {
							echo '<span style="display:inline-block;padding:1px 10px;border-radius:999px;background:#1d4ed8;color:#fff;font-size:12px;font-weight:600">🔒 ' . esc_html__( 'Students', 'mcupr' ) . '</span>';
							$sum = mcupr_aud_summary( mcupr_aud_rules( $id ) );
							if ( '' !== $sum ) {
								echo '<br><small>' . esc_html( $sum ) . '</small>';
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
