<?php
/**
 * Work groups: import from a CSV file (Work groups → Import).
 *
 * One row = one work group of an office. Columns (first row = these names, in any order; only office and name are needed):
 *   office       the name (or address part, or number) of the office the group belongs to
 *   name         name of the group (Thai)
 *   name_en      English name
 *   division     the division the group sits under, if the chart has one: the office home shows the groups under its heading
 *   description  short text
 *   tel, email, place, hours   contact details (hours: rows separated by " | ")
 * A group that already exists in that office (same name) is left as it is: nothing an editor wrote is overwritten –
 * except that an empty division is filled in from the file (so a file imported before divisions existed can be imported again).
 * Groups are added in the order of the file (the order of the list). Nothing else on the site is touched.
 * The chart of the Phrae campus is in starter/mcupr-work-groups.csv (the “Use the sample file” button).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The office (post id) a cell names: by number, by title (spaces ignored) or by address part. 0 = none. */
function mcupr_groups_find_office( $cell ) {
	$cell = trim( (string) $cell );
	if ( '' === $cell ) {
		return 0;
	}
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( get_posts( array( 'post_type' => 'mcupr_office', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ) as $o ) {
			$map[ 'id' . $o->ID ]                                         = $o->ID;
			$map[ 'ti' . preg_replace( '/\s+/u', '', mb_strtolower( $o->post_title ) ) ] = $o->ID;
			$map[ 'sl' . $o->post_name ]                                   = $o->ID;
		}
	}
	$k = preg_replace( '/\s+/u', '', mb_strtolower( $cell ) );
	foreach ( array( 'id' . $cell, 'ti' . $k, 'sl' . sanitize_title( $cell ) ) as $try ) {
		if ( isset( $map[ $try ] ) ) {
			return (int) $map[ $try ];
		}
	}
	return 0;
}

/** The rows of the file as arrays keyed by the header names (empty rows dropped). */
function mcupr_groups_import_table( array $rows ) {
	$out  = array();
	$head = null;
	foreach ( $rows as $r ) {
		$r = array_map( 'strval', (array) $r );
		if ( ! array_filter( $r, 'strlen' ) ) {
			continue;
		}
		if ( null === $head ) {
			$head = array_map( static function ( $h ) { return strtolower( trim( $h ) ); }, $r );
			continue;
		}
		$row = array();
		foreach ( $head as $i => $h ) {
			if ( '' !== $h ) {
				$row[ $h ] = isset( $r[ $i ] ) ? trim( $r[ $i ] ) : '';
			}
		}
		$out[] = $row;
	}
	return null === $head || ! in_array( 'name', $head, true ) || ! in_array( 'office', $head, true ) ? null : $out;
}

/**
 * Add the groups of a table. Returns array( 'added' => n, 'skipped' => n, 'problems' => array( message… ) ).
 */
function mcupr_groups_import_apply( array $table ) {
	$res   = array( 'added' => 0, 'skipped' => 0, 'filled' => 0, 'problems' => array() );
	$order = array();
	foreach ( $table as $n => $row ) {
		$line = $n + 2;
		$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
		if ( '' === $name ) {
			$res['problems'][] = sprintf( /* translators: %d: line number */ __( 'Line %d: no name – skipped.', 'mcupr' ), $line );
			continue;
		}
		$oid = mcupr_groups_find_office( isset( $row['office'] ) ? $row['office'] : '' );
		if ( ! $oid ) {
			$res['problems'][] = sprintf( /* translators: 1: line number, 2: office as typed */ __( 'Line %1$d: the office “%2$s” was not found – skipped.', 'mcupr' ), $line, isset( $row['office'] ) ? sanitize_text_field( $row['office'] ) : '' );
			continue;
		}
		$div  = ! empty( $row['division'] ) ? sanitize_text_field( $row['division'] ) : '';
		$have = null;
		foreach ( mcupr_office_groups( $oid ) as $g ) {
			if ( preg_replace( '/\s+/u', '', mb_strtolower( $g->post_title ) ) === preg_replace( '/\s+/u', '', mb_strtolower( $name ) ) ) {
				$have = $g;
				break;
			}
		}
		if ( $have ) {
			if ( '' !== $div && '' === (string) get_post_meta( $have->ID, '_mcupr_g_division', true ) ) {
				update_post_meta( $have->ID, '_mcupr_g_division', $div );
				$ex = (string) $have->post_excerpt; // an earlier import kept the division as the first words of the short text
				if ( $ex === $div || 0 === strpos( $ex, $div . ' · ' ) ) {
					wp_update_post( array( 'ID' => $have->ID, 'post_excerpt' => $ex === $div ? '' : substr( $ex, strlen( $div . ' · ' ) ) ) );
				}
				mcupr_group_memo_reset();
				$res['filled']++;
			} else {
				$res['skipped']++;
			}
			continue;
		}
		$order[ $oid ] = isset( $order[ $oid ] ) ? $order[ $oid ] + 1 : count( mcupr_office_groups( $oid ) ) + 1;
		$gid           = wp_insert_post( array( 'post_type' => MCUPR_GROUP, 'post_status' => 'publish', 'post_title' => $name, 'post_excerpt' => ! empty( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '', 'menu_order' => $order[ $oid ] ), true );
		if ( is_wp_error( $gid ) || ! $gid ) {
			$res['problems'][] = sprintf( /* translators: %d: line number */ __( 'Line %d: the group could not be saved.', 'mcupr' ), $line );
			continue;
		}
		mcupr_group_set_office( $gid, $oid );
		mcupr_group_memo_reset(); // the next row sees this group
		if ( '' !== $div ) {
			update_post_meta( $gid, '_mcupr_g_division', $div );
		}
		foreach ( array( 'name_en', 'tel', 'place' ) as $k ) {
			if ( ! empty( $row[ $k ] ) ) {
				update_post_meta( $gid, '_mcupr_g_' . $k, sanitize_text_field( $row[ $k ] ) );
			}
		}
		if ( ! empty( $row['email'] ) && is_email( $row['email'] ) ) {
			update_post_meta( $gid, '_mcupr_g_email', sanitize_email( $row['email'] ) );
		}
		if ( ! empty( $row['hours'] ) ) {
			update_post_meta( $gid, '_mcupr_g_hours', implode( "\n", mcupr_group_lines( str_replace( ' | ', "\n", sanitize_textarea_field( $row['hours'] ) ) ) ) );
		}
		$res['added']++;
	}
	if ( ( $res['added'] || $res['filled'] ) && function_exists( 'mcupr_office_counts_clear' ) ) {
		mcupr_office_counts_clear();
	}
	return $res;
}

/* ------------------------------------------------------------------ the screen */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Import work groups', 'mcupr' ), __( 'Import', 'mcupr' ), 'edit_pages', 'mcupr-groups-import', 'mcupr_groups_import_page' );
	}
);

/** A short pointer on the list of groups. */
add_action(
	'admin_notices',
	static function () {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $s && 'edit-' . MCUPR_GROUP === $s->id && current_user_can( 'edit_pages' ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Many work groups at once? Import them from a spreadsheet file.', 'mcupr' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=mcupr-groups-import' ) ) . '">' . esc_html__( 'Import work groups', 'mcupr' ) . '</a></p></div>';
		}
	}
);

function mcupr_groups_import_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
	}
	$act = esc_url( admin_url( 'admin-post.php' ) );
	echo '<div class="wrap"><h1>' . esc_html__( 'Import work groups', 'mcupr' ) . '</h1>';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['res'] ) && is_string( $_GET['res'] ) && preg_match( '/^(\d+)-(\d+)-(\d+)-(\d+)$/', wp_unslash( $_GET['res'] ), $m ) ) {
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( /* translators: 1: added, 2: left as it was, 3: problems */ __( 'Done: %1$d work groups added, %2$d already there (left as they are), %3$d got their division, %4$d lines with a problem.', 'mcupr' ), (int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4] ) ) . '</p>';
		$p = get_transient( 'mcupr_groups_import_problems_' . get_current_user_id() );
		if ( is_array( $p ) && $p ) {
			echo '<ul style="list-style:disc;margin-left:1.5rem">';
			foreach ( array_slice( $p, 0, 30 ) as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	echo '<p>' . esc_html__( 'One row for each work group. Save the sheet as CSV (UTF-8) and upload it. Groups that already exist in the same office are not changed.', 'mcupr' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Columns (first row):', 'mcupr' ) . '</strong> <code>office</code> ' . esc_html__( '(the name of the office, exactly as on the site)', 'mcupr' ) . ', <code>name</code>, <code>name_en</code>, <code>division</code>, <code>description</code>, <code>tel</code>, <code>email</code>, <code>place</code>, <code>hours</code></p>';
	echo '<form method="post" action="' . $act . '" enctype="multipart/form-data" style="margin:1rem 0">'; // phpcs:ignore WordPress.Security.EscapeOutput
	wp_nonce_field( 'mcupr_groups_import', 'mcupr_gi_nonce' );
	echo '<input type="hidden" name="action" value="mcupr_groups_import"><p><label><strong>' . esc_html__( 'CSV file', 'mcupr' ) . '</strong><br><input type="file" name="csv" accept=".csv,.txt,text/csv" required></label></p><button class="button button-primary">' . esc_html__( 'Import', 'mcupr' ) . '</button></form>';
	echo '<form method="post" action="' . $act . '" style="margin:1rem 0">'; // phpcs:ignore WordPress.Security.EscapeOutput
	wp_nonce_field( 'mcupr_groups_import', 'mcupr_gi_nonce' );
	echo '<input type="hidden" name="action" value="mcupr_groups_import"><input type="hidden" name="sample" value="1"><p class="description">' . esc_html__( 'Or use the sample file: the chart of the Phrae campus (13 work groups of the campus office, the academic office and the Buddhist college). The offices must exist first with the same names.', 'mcupr' ) . '</p><button class="button">' . esc_html__( 'Use the sample file', 'mcupr' ) . '</button> <a class="button button-link" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_groups_sample' ), 'mcupr_groups_sample' ) ) . '">' . esc_html__( 'Download the sample file', 'mcupr' ) . '</a></form></div>';
}

add_action(
	'admin_post_mcupr_groups_import',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_gi_nonce', 'mcupr_groups_import', 'edit_pages' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$back = admin_url( 'admin.php?page=mcupr-groups-import' );
		$path = '';
		if ( ! empty( $_POST['sample'] ) ) {
			$path = get_theme_file_path( 'starter/mcupr-work-groups.csv' );
		} elseif ( isset( $_FILES['csv']['tmp_name'], $_FILES['csv']['name'], $_FILES['csv']['size'] ) && is_uploaded_file( $_FILES['csv']['tmp_name'] ) && $_FILES['csv']['size'] <= 2 * MB_IN_BYTES && preg_match( '/\.(csv|txt)$/i', (string) $_FILES['csv']['name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$path = $_FILES['csv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$table = '' !== $path && is_readable( $path ) ? mcupr_groups_import_table( mcupr_import_read_csv( $path ) ) : null;
		if ( null === $table ) {
			wp_safe_redirect( add_query_arg( 'res', '0-0-0-1', $back ) );
			set_transient( 'mcupr_groups_import_problems_' . get_current_user_id(), array( __( 'The file could not be read: it needs a first row with at least the columns office and name (CSV, UTF-8, up to 2 MB).', 'mcupr' ) ), 5 * MINUTE_IN_SECONDS );
			exit;
		}
		$r = mcupr_groups_import_apply( array_slice( $table, 0, 500 ) );
		set_transient( 'mcupr_groups_import_problems_' . get_current_user_id(), $r['problems'], 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'res', $r['added'] . '-' . $r['skipped'] . '-' . $r['filled'] . '-' . count( $r['problems'] ), $back ) );
		exit;
	}
);

add_action(
	'admin_post_mcupr_groups_sample',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mcupr_groups_sample' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mcupr-work-groups.csv"' );
		readfile( get_theme_file_path( 'starter/mcupr-work-groups.csv' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);
