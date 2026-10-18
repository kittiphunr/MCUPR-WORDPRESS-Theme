<?php
/**
 * Class schedule and grades of students (MCUPR › Students › Schedule and grades).
 *
 *  - Staff import two kinds of CSV (the schedule of a term, the grades of a term); the file is checked and previewed first.
 *    A new file replaces what a student already has for the terms in the file; other terms stay.
 *  - The student sees their own weekly schedule (with a calendar file and a print button), their grades term by term,
 *    the average of each term (GPA), the cumulative average (GPAX) and the credits earned – in the student portal only.
 *  - Nobody else sees them: not other students, not the public, not the REST API, not the student export. Staff with the
 *    student-data permission see them on the student's record; importing, viewing and deleting are written in the activity log.
 *  - The averages are worked out by the website from the imported rows and are for looking at – not an official transcript.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_SG_MAX = 20000;
const MCUPR_SG_META = array( 'sched' => '_mcupr_s_sched', 'grades' => '_mcupr_s_grades' );

/* ------------------------------------------------------------------ reading values */

/** A term as "2567/1" (year/semester) from "1/2567", "2567-1", "ภาคเรียนที่ 1/2567" …; '' if it is not one. */
function mcupr_sg_term( $s ) {
	$s = trim( (string) $s );
	if ( preg_match( '/(\d{4})\s*[\/\-\.]\s*([1-3])\b/u', $s, $m ) ) {
		return $m[1] . '/' . $m[2];
	}
	if ( preg_match( '/\b([1-3])\s*[\/\-\.]\s*(\d{4})\b/u', $s, $m ) ) {
		return $m[2] . '/' . $m[1];
	}
	return '';
}
/** "2567/1" → "1/2567" for people. */
function mcupr_sg_term_label( $t ) {
	$p = explode( '/', (string) $t );
	return 2 === count( $p ) ? $p[1] . '/' . $p[0] : (string) $t;
}

/** A day name (Thai or English, short or long) as 1 (Monday) … 7 (Sunday); 0 if unknown. */
function mcupr_sg_day( $s ) {
	$k   = mb_strtolower( preg_replace( '/[\s\.]+/u', '', trim( (string) $s ) ) );
	$k   = preg_replace( '/^วัน/u', '', $k );
	$map = array(
		1 => array( 'จันทร์', 'จ', 'mon', 'monday', '1' ),
		2 => array( 'อังคาร', 'อ', 'tue', 'tues', 'tuesday', '2' ),
		3 => array( 'พุธ', 'พ', 'wed', 'wednesday', '3' ),
		4 => array( 'พฤหัสบดี', 'พฤหัส', 'พฤ', 'thu', 'thur', 'thurs', 'thursday', '4' ),
		5 => array( 'ศุกร์', 'ศ', 'fri', 'friday', '5' ),
		6 => array( 'เสาร์', 'ส', 'sat', 'saturday', '6' ),
		7 => array( 'อาทิตย์', 'อา', 'sun', 'sunday', '7' ),
	);
	foreach ( $map as $n => $names ) {
		if ( in_array( $k, $names, true ) ) {
			return $n;
		}
	}
	return 0;
}

/** A time ("08:30", "8.30", "0830", "8:30 น.") as minutes after midnight; -1 if it is not a time. */
function mcupr_sg_time( $s ) {
	$s = trim( preg_replace( '/\s*น\.?\s*$/u', '', (string) $s ) );
	if ( preg_match( '/^(\d{1,2})\s*[:\.]\s*(\d{2})(?:[:\.]\d{2})?$/', $s, $m ) || preg_match( '/^(\d{2})(\d{2})$/', $s, $m ) ) {
		$h = (int) $m[1];
		$n = (int) $m[2];
		return $h <= 23 && $n <= 59 ? $h * 60 + $n : -1;
	}
	return -1;
}
function mcupr_sg_time_str( $m ) {
	return sprintf( '%02d:%02d', intdiv( (int) $m, 60 ), (int) $m % 60 );
}

/** Grade letters: points for the average (null = not in the average) and whether the credits count as earned. */
function mcupr_sg_grades() {
	return array(
		'A'  => array( 4.0, true ), 'B+' => array( 3.5, true ), 'B' => array( 3.0, true ), 'C+' => array( 2.5, true ),
		'C'  => array( 2.0, true ), 'D+' => array( 1.5, true ), 'D' => array( 1.0, true ), 'F' => array( 0.0, false ),
		'S'  => array( null, true ), 'P' => array( null, true ), 'U' => array( null, false ), 'W' => array( null, false ),
		'I'  => array( null, false ), 'N' => array( null, false ), 'AU' => array( null, false ),
	);
}
function mcupr_sg_grade( $s ) {
	$g = strtoupper( preg_replace( '/\s+/', '', trim( (string) $s ) ) );
	return isset( mcupr_sg_grades()[ $g ] ) ? $g : '';
}

/** The average of rows (each with cr and g): array( credits counted, points, gpa or null, credits earned ). */
function mcupr_sg_stats( $rows ) {
	$gr = mcupr_sg_grades();
	$cr = 0.0;
	$pt = 0.0;
	$ea = 0.0;
	foreach ( $rows as $r ) {
		if ( ! isset( $gr[ $r['g'] ] ) ) {
			continue;
		}
		if ( null !== $gr[ $r['g'] ][0] ) {
			$cr += (float) $r['cr'];
			$pt += (float) $r['cr'] * $gr[ $r['g'] ][0];
		}
		if ( $gr[ $r['g'] ][1] ) {
			$ea += (float) $r['cr'];
		}
	}
	return array( 'credits' => $cr, 'points' => $pt, 'gpa' => $cr > 0 ? round( $pt / $cr + 1e-9, 2 ) : null, 'earned' => $ea );
}
function mcupr_sg_num( $n ) {
	return rtrim( rtrim( number_format( (float) $n, 1, '.', '' ), '0' ), '.' );
}

/* ------------------------------------------------------------------ the files */

function mcupr_sg_kinds() {
	return array( 'sched' => __( 'Class schedule of a term', 'mcupr' ), 'grades' => __( 'Grades of a term', 'mcupr' ) );
}
/** Column field => names that are recognised, per kind. */
function mcupr_sg_headers( $kind ) {
	$h = array(
		'sid'     => array( 'รหัสนิสิต', 'รหัสนักศึกษา', 'รหัส', 'student id', 'student_id', 'sid', 'id' ),
		'term'    => array( 'ภาคเรียน', 'ภาคการศึกษา', 'ภาคเรียน/ปี', 'term', 'semester' ),
		'code'    => array( 'รหัสวิชา', 'รหัสรายวิชา', 'course code', 'course_code', 'code' ),
		'name'    => array( 'ชื่อวิชา', 'ชื่อรายวิชา', 'รายวิชา', 'course name', 'course', 'subject', 'name', 'course title', 'title' ),
		'credits' => array( 'หน่วยกิต', 'credits', 'credit' ),
	);
	if ( 'sched' === $kind ) {
		$h += array(
			'day'     => array( 'วัน', 'วันเรียน', 'day' ),
			'start'   => array( 'เวลาเริ่ม', 'เริ่ม', 'start', 'from', 'start time' ),
			'end'     => array( 'เวลาสิ้นสุด', 'สิ้นสุด', 'ถึง', 'end', 'to', 'end time' ),
			'room'    => array( 'ห้อง', 'ห้องเรียน', 'room' ),
			'teacher' => array( 'อาจารย์', 'ผู้สอน', 'อาจารย์ผู้สอน', 'teacher', 'instructor' ),
		);
	} else {
		$h['grade'] = array( 'เกรด', 'ผลการเรียน', 'grade' );
	}
	return $h;
}
function mcupr_sg_required( $kind ) {
	return 'sched' === $kind ? array( 'sid', 'term', 'code', 'name', 'day', 'start', 'end' ) : array( 'sid', 'term', 'code', 'credits', 'grade' );
}

/**
 * Read a CSV of one kind. Returns array( rows => list of array( line, post id, row ), missing => unknown student IDs (counted),
 * errors => list of array( line, message ), terms, unknown => unrecognised column names ) or a WP_Error ('empty', 'columns', 'big').
 */
function mcupr_sg_parse( $kind, $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", mcupr_si_utf8( $text ) );
	if ( '' === trim( $text ) ) {
		return new WP_Error( 'empty', 'empty' );
	}
	$first = (string) strtok( $text, "\n" );
	$best  = ',';
	$max   = -1;
	foreach ( array( ',', "\t", ';' ) as $d ) {
		if ( substr_count( $first, $d ) > $max ) {
			$max  = substr_count( $first, $d );
			$best = $d;
		}
	}
	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	rewind( $fh );
	$head = fgetcsv( $fh, 0, $best, '"', mcupr_csv_escape() );
	if ( ! is_array( $head ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'empty', 'empty' );
	}
	$cols    = array();
	$unknown = array();
	foreach ( $head as $i => $h ) {
		$k     = mcupr_si_key( $h );
		$found = '';
		foreach ( mcupr_sg_headers( $kind ) as $field => $names ) {
			foreach ( $names as $nm ) {
				if ( mcupr_si_key( $nm ) === $k ) {
					$found = $field;
					break 2;
				}
			}
		}
		if ( '' !== $found && ! isset( $cols[ $found ] ) ) {
			$cols[ $found ] = $i;
		} elseif ( '' !== trim( (string) $h ) ) {
			$unknown[] = trim( (string) $h );
		}
	}
	foreach ( mcupr_sg_required( $kind ) as $f ) {
		if ( ! isset( $cols[ $f ] ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'columns', $f );
		}
	}
	$get    = static function ( $r, $f ) use ( $cols ) {
		return isset( $cols[ $f ] ) && isset( $r[ $cols[ $f ] ] ) ? trim( (string) $r[ $cols[ $f ] ] ) : '';
	};
	$out    = array( 'rows' => array(), 'missing' => array(), 'errors' => array(), 'terms' => array(), 'unknown' => $unknown, 'lines' => 0 );
	$seen   = array();
	$ids    = array();
	$line   = 1;
	while ( false !== ( $r = fgetcsv( $fh, 0, $best, '"', mcupr_csv_escape() ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$line++;
		if ( 1 === count( $r ) && ( null === $r[0] || '' === trim( (string) $r[0] ) ) ) {
			continue;
		}
		if ( ++$out['lines'] > MCUPR_SG_MAX ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'big', 'too many rows' );
		}
		$sid  = strtoupper( $get( $r, 'sid' ) );
		$term = mcupr_sg_term( $get( $r, 'term' ) );
		$code = mb_substr( $get( $r, 'code' ), 0, 20 );
		$name = mb_substr( $get( $r, 'name' ), 0, 150 );
		$cr   = str_replace( ',', '.', $get( $r, 'credits' ) );
		$row  = array( 't' => $term, 'c' => $code, 'n' => $name );
		$err  = '';
		if ( '' === $sid ) {
			$err = __( 'no student ID', 'mcupr' );
		} elseif ( '' === $term ) {
			$err = __( 'the term must look like 1/2567', 'mcupr' );
		} elseif ( '' === $code ) {
			$err = __( 'no course code', 'mcupr' );
		} elseif ( 'sched' === $kind && '' === $name ) {
			$err = __( 'no course name', 'mcupr' );
		}
		if ( '' === $err ) {
			if ( '' !== $cr && ( ! is_numeric( $cr ) || (float) $cr < 0 || (float) $cr > 30 ) ) {
				$err = __( 'credits must be a number from 0 to 30', 'mcupr' );
			} elseif ( 'grades' === $kind && '' === $cr ) {
				$err = __( 'no credits', 'mcupr' );
			}
			$row['cr'] = '' === $cr ? 0 : round( (float) $cr, 1 );
		}
		if ( '' === $err && 'sched' === $kind ) {
			$d = mcupr_sg_day( $get( $r, 'day' ) );
			$s = mcupr_sg_time( $get( $r, 'start' ) );
			$e = mcupr_sg_time( $get( $r, 'end' ) );
			if ( ! $d ) {
				$err = __( 'unknown day', 'mcupr' );
			} elseif ( $s < 0 || $e < 0 ) {
				$err = __( 'the times must look like 08:30', 'mcupr' );
			} elseif ( $e <= $s ) {
				$err = __( 'the end must be after the start', 'mcupr' );
			} else {
				$row += array( 'd' => $d, 's' => $s, 'e' => $e, 'r' => mb_substr( $get( $r, 'room' ), 0, 40 ), 'tc' => mb_substr( $get( $r, 'teacher' ), 0, 120 ) );
			}
		}
		if ( '' === $err && 'grades' === $kind ) {
			$g = mcupr_sg_grade( $get( $r, 'grade' ) );
			if ( '' === $g ) {
				$err = __( 'unknown grade (A, B+, B, C+, C, D+, D, F, S, U, P, W, I, N, AU)', 'mcupr' );
			} else {
				$row['g'] = $g;
			}
		}
		if ( '' !== $err ) {
			$out['errors'][] = array( $line, $err );
			continue;
		}
		$key = md5( $sid . wp_json_encode( $row ) );
		if ( isset( $seen[ $key ] ) ) {
			continue; // the same row twice
		}
		$seen[ $key ] = 1;
		if ( ! array_key_exists( $sid, $ids ) ) {
			$ids[ $sid ] = mcupr_student_find( $sid );
		}
		if ( ! $ids[ $sid ] ) {
			$out['missing'][ $sid ] = isset( $out['missing'][ $sid ] ) ? $out['missing'][ $sid ] + 1 : 1;
			continue;
		}
		$out['rows'][]            = array( $ids[ $sid ], $row );
		$out['terms'][ $term ]    = true;
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	ksort( $out['terms'] );
	return $out;
}

/* ------------------------------------------------------------------ storing */

function mcupr_sg_get( $post_id, $kind ) {
	$m = get_post_meta( $post_id, MCUPR_SG_META[ $kind ], true );
	return is_array( $m ) ? $m : array();
}
function mcupr_sg_sort( $rows, $kind ) {
	usort(
		$rows,
		static function ( $a, $b ) use ( $kind ) {
			if ( 'sched' === $kind ) {
				return array( $b['t'], $a['d'], $a['s'], $a['c'] ) <=> array( $a['t'], $b['d'], $b['s'], $b['c'] );
			}
			return array( $a['t'], $a['c'] ) <=> array( $b['t'], $b['c'] );
		}
	);
	return array_values( $rows );
}

/** Put parsed rows into the students: for every student, the terms in the file are replaced, other terms stay. Returns array( students, rows ). */
function mcupr_sg_apply( $kind, $parsed_rows ) {
	$by = array();
	foreach ( $parsed_rows as $x ) {
		$by[ (int) $x[0] ][] = $x[1];
	}
	$n = 0;
	foreach ( $by as $pid => $new ) {
		$terms = array_unique( wp_list_pluck( $new, 't' ) );
		$keep  = array_filter(
			mcupr_sg_get( $pid, $kind ),
			static function ( $r ) use ( $terms ) {
				return ! in_array( $r['t'], $terms, true );
			}
		);
		update_post_meta( $pid, MCUPR_SG_META[ $kind ], mcupr_sg_sort( array_merge( $keep, $new ), $kind ) );
		$n += count( $new );
	}
	mcupr_sg_index_rebuild();
	return array( count( $by ), $n );
}

/** Every term that exists, with how many students and rows – kept in an option, rebuilt after an import or a deletion. */
function mcupr_sg_index_rebuild() {
	global $wpdb;
	$idx = array( 'sched' => array(), 'grades' => array() );
	foreach ( MCUPR_SG_META as $kind => $key ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) ) as $raw ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = maybe_unserialize( $raw );
			if ( ! is_array( $rows ) ) {
				continue;
			}
			$seen = array();
			foreach ( $rows as $r ) {
				if ( ! isset( $idx[ $kind ][ $r['t'] ] ) ) {
					$idx[ $kind ][ $r['t'] ] = array( 's' => 0, 'r' => 0 );
				}
				++$idx[ $kind ][ $r['t'] ]['r'];
				if ( ! isset( $seen[ $r['t'] ] ) ) {
					$seen[ $r['t'] ] = 1;
					++$idx[ $kind ][ $r['t'] ]['s'];
				}
			}
		}
		krsort( $idx[ $kind ] );
	}
	update_option( 'mcupr_sg_index', $idx, false );
	return $idx;
}
function mcupr_sg_index() {
	$i = get_option( 'mcupr_sg_index', null );
	return is_array( $i ) ? $i : mcupr_sg_index_rebuild();
}

/** Delete one term of one kind for every student. Returns how many students had it. */
function mcupr_sg_drop( $kind, $term ) {
	global $wpdb;
	$n = 0;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", MCUPR_SG_META[ $kind ] ) ) as $pid ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = mcupr_sg_get( (int) $pid, $kind );
		$left = array_values(
			array_filter(
				$rows,
				static function ( $r ) use ( $term ) {
					return $r['t'] !== $term;
				}
			)
		);
		if ( count( $left ) !== count( $rows ) ) {
			++$n;
			if ( $left ) {
				update_post_meta( (int) $pid, MCUPR_SG_META[ $kind ], $left );
			} else {
				delete_post_meta( (int) $pid, MCUPR_SG_META[ $kind ] );
			}
		}
	}
	mcupr_sg_index_rebuild();
	return $n;
}

/* ------------------------------------------------------------------ the admin page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Schedule and grades', 'mcupr' ), __( 'Schedule and grades', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-grades', 'mcupr_sg_page' );
	}
);

function mcupr_sg_back( $args ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=mcupr-student-grades' ) ) );
	exit;
}
function mcupr_sg_guard( $action ) {
	if ( ! mcupr_check_request( 'mcupr_sg_nonce', $action, MCUPR_STU_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
	}
}

function mcupr_sg_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
	$token = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : '';
	$msg   = isset( $_GET['m'] ) ? sanitize_key( wp_unslash( $_GET['m'] ) ) : '';
	$n1    = isset( $_GET['n1'] ) ? absint( $_GET['n1'] ) : 0;
	$n2    = isset( $_GET['n2'] ) ? absint( $_GET['n2'] ) : 0;
	// phpcs:enable
	echo '<div class="wrap"><h1>' . esc_html__( 'Schedule and grades', 'mcupr' ) . '</h1>';
	$errs = array(
		'file'    => __( 'No file was uploaded (or it is bigger than 5 MB).', 'mcupr' ),
		'empty'   => __( 'The file is empty.', 'mcupr' ),
		'columns' => __( 'A column is missing. The first line must name the columns; download the template to see them.', 'mcupr' ),
		'big'     => __( 'The file has too many rows (20,000 at most). Split it in parts.', 'mcupr' ),
		'gone'    => __( 'That import has expired. Upload the file again.', 'mcupr' ),
		'nothing' => __( 'There is nothing to import in that file.', 'mcupr' ),
		'date'    => __( 'Please give the term as 1/2567 and both dates, the end after the start.', 'mcupr' ),
	);
	$oks  = array(
		/* translators: 1: students, 2: rows */
		'done'  => sprintf( __( 'Imported: %2$d rows for %1$d students.', 'mcupr' ), $n1, $n2 ),
		/* translators: %d: students */
		'drop'  => sprintf( __( 'Deleted for %d students.', 'mcupr' ), $n1 ),
		'dates' => __( 'Saved.', 'mcupr' ),
	);
	if ( isset( $errs[ $msg ] ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $errs[ $msg ] ) . '</p></div>';
	} elseif ( isset( $oks[ $msg ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $oks[ $msg ] ) . '</p></div>';
	}
	if ( 'preview' === $step && $token ) {
		mcupr_sg_preview( $token );
		echo '</div>';
		return;
	}
	echo '<p style="max-width:56rem">' . esc_html__( 'Import the class schedule and the grades of a term from a spreadsheet saved as CSV (in Excel: Save as → CSV UTF-8). Each student sees only their own, in the student portal. A new file replaces what a student already has for the terms in the file.', 'mcupr' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="card" style="max-width:56rem"><input type="hidden" name="action" value="mcupr_sg_upload">';
	wp_nonce_field( 'mcupr_sg_upload', 'mcupr_sg_nonce' );
	echo '<h2>' . esc_html__( '1. Choose the file', 'mcupr' ) . '</h2><fieldset><legend class="screen-reader-text">' . esc_html__( 'What the file holds', 'mcupr' ) . '</legend>';
	foreach ( mcupr_sg_kinds() as $k => $label ) {
		echo '<label style="display:block;margin:.25rem 0"><input type="radio" name="kind" value="' . esc_attr( $k ) . '"' . checked( 'sched' === $k, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset><p><label for="sg-file"><strong>' . esc_html__( 'CSV file', 'mcupr' ) . '</strong></label><br><input id="sg-file" type="file" name="file" accept=".csv,.txt,.tsv,text/csv,text/plain" required></p><p><button class="button button-primary">' . esc_html__( 'Check the file', 'mcupr' ) . '</button></p></form>';

	echo '<div class="card" style="max-width:56rem"><h2>' . esc_html__( 'Templates and columns', 'mcupr' ) . '</h2><p>';
	foreach ( mcupr_sg_kinds() as $k => $label ) {
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_sg_template&kind=' . $k ), 'mcupr_sg_template' ) ) . '">' . esc_html( sprintf( /* translators: %s: kind of file */ __( 'Template: %s', 'mcupr' ), $label ) ) . '</a> ';
	}
	echo '</p><p class="description">' . esc_html__( 'Columns can be in Thai or English and in any order. Schedule: student ID, term, course code, course name, day, start, end (room, teacher, credits are optional). Grades: student ID, term, course code, credits, grade (course name is optional). The term is written as 1/2567; times as 08:30; grades A, B+, B, C+, C, D+, D, F, S, U, P, W, I, N or AU.', 'mcupr' ) . '</p></div>';

	$idx = mcupr_sg_index();
	echo '<h2>' . esc_html__( 'What is imported', 'mcupr' ) . '</h2>';
	$any = false;
	foreach ( mcupr_sg_kinds() as $k => $label ) {
		foreach ( $idx[ $k ] as $t => $c ) {
			if ( ! $any ) {
				echo '<table class="widefat striped" style="max-width:56rem"><thead><tr><th scope="col">' . esc_html__( 'Kind', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Term', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Students', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Rows', 'mcupr' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Delete', 'mcupr' ) . '</span></th></tr></thead><tbody>';
				$any = true;
			}
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( mcupr_sg_term_label( $t ) ) . '</td><td>' . (int) $c['s'] . '</td><td>' . (int) $c['r'] . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(this.getAttribute(\'data-c\')||\'OK?\')" data-c="' . esc_attr__( 'Delete this term for every student? This cannot be undone.', 'mcupr' ) . '"><input type="hidden" name="action" value="mcupr_sg_drop"><input type="hidden" name="kind" value="' . esc_attr( $k ) . '"><input type="hidden" name="term" value="' . esc_attr( $t ) . '">';
			wp_nonce_field( 'mcupr_sg_drop', 'mcupr_sg_nonce' );
			/* translators: 1: kind, 2: term */
			echo '<button class="button-link button-link-delete" aria-label="' . esc_attr( sprintf( __( 'Delete %1$s of term %2$s', 'mcupr' ), $label, mcupr_sg_term_label( $t ) ) ) . '">' . esc_html__( 'Delete', 'mcupr' ) . '</button></form></td></tr>';
		}
	}
	echo $any ? '</tbody></table>' : '<p>' . esc_html__( 'Nothing yet.', 'mcupr' ) . '</p>';

	$dates = mcupr_sg_term_dates();
	echo '<h2>' . esc_html__( 'Term dates (for the calendar file)', 'mcupr' ) . '</h2><p class="description" style="max-width:56rem">' . esc_html__( 'A student can add the schedule to the phone calendar. For that, give the first and the last day of the term.', 'mcupr' ) . '</p>';
	if ( $dates ) {
		echo '<ul>';
		foreach ( $dates as $t => $d ) {
			echo '<li>' . esc_html( mcupr_sg_term_label( $t ) . ': ' . $d['start'] . ' → ' . $d['end'] ) . '</li>';
		}
		echo '</ul>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_sg_dates">';
	wp_nonce_field( 'mcupr_sg_dates', 'mcupr_sg_nonce' );
	echo '<p><label for="sg-term">' . esc_html__( 'Term', 'mcupr' ) . '</label> <input id="sg-term" name="term" type="text" class="small-text" placeholder="1/2567" required> <label for="sg-ds">' . esc_html__( 'First day', 'mcupr' ) . '</label> <input id="sg-ds" name="start" type="date" required> <label for="sg-de">' . esc_html__( 'Last day', 'mcupr' ) . '</label> <input id="sg-de" name="end" type="date" required> <button class="button">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form></div>';
}

function mcupr_sg_term_dates() {
	$d = get_option( 'mcupr_sg_terms', array() );
	return is_array( $d ) ? $d : array();
}

add_action(
	'admin_post_mcupr_sg_dates',
	static function () {
		mcupr_sg_guard( 'mcupr_sg_dates' );
		$t = mcupr_sg_term( isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$s = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$e = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$ok = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $e ) && strtotime( $s ) && strtotime( $e ) && strtotime( $e ) > strtotime( $s );
		if ( '' === $t || ! $ok ) {
			mcupr_sg_back( array( 'm' => 'date' ) );
		}
		$all       = mcupr_sg_term_dates();
		$all[ $t ] = array( 'start' => $s, 'end' => $e );
		krsort( $all );
		update_option( 'mcupr_sg_terms', array_slice( $all, 0, 40, true ), false );
		mcupr_sg_back( array( 'm' => 'dates' ) );
	}
);

add_action(
	'admin_post_mcupr_sg_template',
	static function () {
		if ( ! mcupr_check_request( '_wpnonce', 'mcupr_sg_template', MCUPR_STU_CAP, 'get' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$kind = isset( $_GET['kind'] ) && 'grades' === $_GET['kind'] ? 'grades' : 'sched';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . ( 'grades' === $kind ? 'grades-template.csv' : 'schedule-template.csv' ) . '"' );
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 'sched' === $kind ) {
			fputcsv( $out, array_map( 'mcupr_csv_safe', array( 'รหัสนิสิต', 'ภาคเรียน', 'รหัสวิชา', 'ชื่อวิชา', 'หน่วยกิต', 'วัน', 'เวลาเริ่ม', 'เวลาสิ้นสุด', 'ห้อง', 'อาจารย์' ) ), ',', '"', mcupr_csv_escape() );
			fputcsv( $out, array_map( 'mcupr_csv_safe', array( '6401001', '1/2567', 'BUD101', 'พุทธศาสนาเบื้องต้น', '3', 'จันทร์', '09:00', '12:00', 'A-201', 'อ.ตัวอย่าง ใจดี' ) ), ',', '"', mcupr_csv_escape() );
		} else {
			fputcsv( $out, array_map( 'mcupr_csv_safe', array( 'รหัสนิสิต', 'ภาคเรียน', 'รหัสวิชา', 'ชื่อวิชา', 'หน่วยกิต', 'เกรด' ) ), ',', '"', mcupr_csv_escape() );
			fputcsv( $out, array_map( 'mcupr_csv_safe', array( '6401001', '1/2567', 'BUD101', 'พุทธศาสนาเบื้องต้น', '3', 'A' ) ), ',', '"', mcupr_csv_escape() );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
);

/** Upload → read → keep the result for an hour → the preview. */
add_action(
	'admin_post_mcupr_sg_upload',
	static function () {
		mcupr_sg_guard( 'mcupr_sg_upload' );
		$kind = isset( $_POST['kind'] ) && 'grades' === $_POST['kind'] ? 'grades' : 'sched'; // phpcs:ignore WordPress.Security.NonceVerification
		$f    = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security
		if ( ! is_array( $f ) || empty( $f['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $f['error'] || (int) $f['size'] > 5 * MB_IN_BYTES ) {
			mcupr_sg_back( array( 'm' => 'file' ) );
		}
		$raw = file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res = mcupr_sg_parse( $kind, (string) $raw );
		if ( is_wp_error( $res ) ) {
			mcupr_sg_back( array( 'm' => $res->get_error_code() ) );
		}
		if ( ! $res['rows'] ) {
			if ( $res['missing'] || $res['errors'] ) {
				$tok = bin2hex( random_bytes( 12 ) );
				set_transient( 'mcupr_sg_' . $tok, array( 'u' => get_current_user_id(), 'kind' => $kind ) + $res, HOUR_IN_SECONDS );
				mcupr_sg_back( array( 'step' => 'preview', 't' => $tok ) );
			}
			mcupr_sg_back( array( 'm' => 'nothing' ) );
		}
		$tok = bin2hex( random_bytes( 12 ) );
		set_transient( 'mcupr_sg_' . $tok, array( 'u' => get_current_user_id(), 'kind' => $kind ) + $res, HOUR_IN_SECONDS );
		mcupr_sg_back( array( 'step' => 'preview', 't' => $tok ) );
	}
);

function mcupr_sg_preview( $token ) {
	$d = get_transient( 'mcupr_sg_' . $token );
	if ( ! is_array( $d ) || (int) $d['u'] !== get_current_user_id() ) {
		mcupr_sg_back( array( 'm' => 'gone' ) );
	}
	$kinds = mcupr_sg_kinds();
	$stu   = count( array_unique( wp_list_pluck( $d['rows'], 0 ) ) );
	echo '<h2>' . esc_html( $kinds[ $d['kind'] ] ) . '</h2><div style="display:flex;flex-wrap:wrap;gap:10px;margin:1rem 0">';
	foreach ( array( count( $d['rows'] ) => __( 'rows to import', 'mcupr' ), $stu => __( 'students', 'mcupr' ), count( $d['terms'] ) => __( 'terms', 'mcupr' ), count( $d['errors'] ) => __( 'rows with a problem (left out)', 'mcupr' ), count( $d['missing'] ) => __( 'student IDs not in the database (left out)', 'mcupr' ) ) as $n => $l ) {
		echo '<div style="min-width:9rem;padding:10px 16px;border:1px solid #dcdcde;border-radius:10px;background:#fff"><b style="display:block;font-size:1.6rem;line-height:1.2">' . (int) $n . '</b>' . esc_html( $l ) . '</div>';
	}
	echo '</div>';
	if ( $d['terms'] ) {
		echo '<p>' . esc_html__( 'Terms in the file:', 'mcupr' ) . ' <strong>' . esc_html( implode( ', ', array_map( 'mcupr_sg_term_label', array_keys( $d['terms'] ) ) ) ) . '</strong>. ' . esc_html__( 'For every student in the file, what they already have for these terms is replaced.', 'mcupr' ) . '</p>';
	}
	if ( $d['unknown'] ) {
		echo '<p class="description">' . esc_html__( 'Columns that were not used:', 'mcupr' ) . ' ' . esc_html( implode( ', ', array_slice( $d['unknown'], 0, 12 ) ) ) . '</p>';
	}
	if ( $d['errors'] ) {
		echo '<h3>' . esc_html__( 'Rows with a problem', 'mcupr' ) . '</h3><ul style="max-width:56rem">';
		foreach ( array_slice( $d['errors'], 0, 30 ) as $e ) {
			/* translators: 1: line number, 2: the problem */
			echo '<li>' . esc_html( sprintf( __( 'Line %1$d: %2$s', 'mcupr' ), $e[0], $e[1] ) ) . '</li>';
		}
		echo '</ul>';
		if ( count( $d['errors'] ) > 30 ) {
			echo '<p>' . esc_html( sprintf( /* translators: %d: more problems */ __( '… and %d more.', 'mcupr' ), count( $d['errors'] ) - 30 ) ) . '</p>';
		}
	}
	if ( $d['missing'] ) {
		echo '<h3>' . esc_html__( 'Student IDs that are not in the student database', 'mcupr' ) . '</h3><p style="max-width:56rem">' . esc_html( implode( ', ', array_slice( array_keys( $d['missing'] ), 0, 40 ) ) ) . ( count( $d['missing'] ) > 40 ? ' …' : '' ) . '</p>';
	}
	if ( $d['rows'] ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_sg_apply"><input type="hidden" name="t" value="' . esc_attr( $token ) . '">';
		wp_nonce_field( 'mcupr_sg_apply', 'mcupr_sg_nonce' );
		echo '<p><label><input type="checkbox" name="ok" value="1" required> ' . esc_html__( 'I have checked the numbers above', 'mcupr' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Import', 'mcupr' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=mcupr-student-grades' ) ) . '">' . esc_html__( 'Cancel', 'mcupr' ) . '</a></p></form>';
	} else {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=mcupr-student-grades' ) ) . '">' . esc_html__( 'Back', 'mcupr' ) . '</a></p>';
	}
}

add_action(
	'admin_post_mcupr_sg_apply',
	static function () {
		mcupr_sg_guard( 'mcupr_sg_apply' );
		$tok = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$d   = get_transient( 'mcupr_sg_' . $tok );
		if ( ! is_array( $d ) || (int) $d['u'] !== get_current_user_id() || empty( $d['rows'] ) ) {
			mcupr_sg_back( array( 'm' => 'gone' ) );
		}
		if ( empty( $_POST['ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			mcupr_sg_back( array( 'step' => 'preview', 't' => $tok ) );
		}
		list( $s, $n ) = mcupr_sg_apply( $d['kind'], $d['rows'] );
		delete_transient( 'mcupr_sg_' . $tok );
		mcupr_audit( 'sgimport', array( 'n' => $n, 'note' => $d['kind'] . ' ' . implode( ',', array_keys( $d['terms'] ) ) . ' / ' . $s ) );
		mcupr_sg_back( array( 'm' => 'done', 'n1' => $s, 'n2' => $n ) );
	}
);

add_action(
	'admin_post_mcupr_sg_drop',
	static function () {
		mcupr_sg_guard( 'mcupr_sg_drop' );
		$kind = isset( $_POST['kind'] ) && 'grades' === $_POST['kind'] ? 'grades' : 'sched'; // phpcs:ignore WordPress.Security.NonceVerification
		$term = mcupr_sg_term( isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $term ) {
			mcupr_sg_back( array( 'm' => 'nothing' ) );
		}
		$n = mcupr_sg_drop( $kind, $term );
		mcupr_audit( 'sgdrop', array( 'n' => $n, 'note' => $kind . ' ' . $term ) );
		mcupr_sg_back( array( 'm' => 'drop', 'n1' => $n ) );
	}
);

/* ------------------------------------------------------------------ staff: on the student's record */

add_action(
	'add_meta_boxes',
	static function () {
		if ( current_user_can( MCUPR_STU_CAP ) ) {
			add_meta_box( 'mcupr_student_sg', __( 'Schedule and grades', 'mcupr' ), 'mcupr_sg_staff_box', MCUPR_STU, 'normal', 'default' );
		}
	}
);
function mcupr_sg_staff_box( $post ) {
	$sc = mcupr_sg_get( $post->ID, 'sched' );
	$gr = mcupr_sg_get( $post->ID, 'grades' );
	if ( ! $sc && ! $gr ) {
		echo '<p>' . esc_html__( 'Nothing imported for this student.', 'mcupr' ) . '</p>';
		return;
	}
	mcupr_audit( 'sgview', array( 'post' => (int) $post->ID, 'sid' => (string) get_post_meta( $post->ID, '_mcupr_s_sid', true ) ) );
	echo '<p class="description">' . esc_html__( 'Only staff with the student-data permission see this. Opening it is written in the activity log.', 'mcupr' ) . '</p>';
	echo mcupr_sg_grades_html( $gr, false ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo mcupr_sg_sched_html( $sc, '', false ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/* ------------------------------------------------------------------ what the student sees */

function mcupr_sg_day_name( $n ) {
	return mcupr_t( 'sg_d' . (int) $n );
}

/** The schedule of one term as a list per day. */
function mcupr_sg_sched_html( $rows, $term = '', $public = true ) {
	$by = array();
	foreach ( $rows as $r ) {
		if ( '' === $term || $r['t'] === $term ) {
			$by[ (int) $r['d'] ][ $r['t'] ][] = $r;
		}
	}
	if ( ! $by ) {
		return '';
	}
	ksort( $by );
	$h = '';
	foreach ( $by as $d => $terms ) {
		foreach ( $terms as $t => $list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return $a['s'] <=> $b['s'];
				}
			);
			$h .= '<div class="sg-day"><h3>' . esc_html( $public ? mcupr_sg_day_name( $d ) : mcupr_sg_day_name( $d ) . ' · ' . mcupr_sg_term_label( $t ) ) . '</h3><ul>';
			foreach ( $list as $r ) {
				$h .= '<li><span class="sg-time">' . esc_html( mcupr_sg_time_str( $r['s'] ) . '–' . mcupr_sg_time_str( $r['e'] ) ) . '</span> <span class="sg-c"><b>' . esc_html( $r['c'] ) . '</b> ' . esc_html( $r['n'] ) . '</span>';
				$meta = array_filter( array( '' !== $r['r'] ? mcupr_t( 'sg_room' ) . ' ' . $r['r'] : '', $r['tc'], $r['cr'] ? mcupr_sg_num( $r['cr'] ) . ' ' . mcupr_t( 'sg_cr' ) : '' ) );
				if ( $meta ) {
					$h .= '<span class="sg-m">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
				}
				$h .= '</li>';
			}
			$h .= '</ul></div>';
		}
	}
	return '<div class="sg-sched">' . $h . '</div>';
}

/** Grades term by term with the average of each term and the cumulative average. */
function mcupr_sg_grades_html( $rows, $public = true ) {
	if ( ! $rows ) {
		return '';
	}
	$by = array();
	foreach ( $rows as $r ) {
		$by[ $r['t'] ][] = $r;
	}
	krsort( $by );
	$h = '';
	foreach ( $by as $t => $list ) {
		$st = mcupr_sg_stats( $list );
		$h .= '<table class="sg-tbl"><caption>' . esc_html( sprintf( mcupr_t( 'sg_term' ), explode( '/', $t )[1], explode( '/', $t )[0] ) ) . '</caption><thead><tr><th scope="col">' . esc_html( mcupr_t( 'sg_code' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'sg_course' ) ) . '</th><th scope="col" class="sg-num">' . esc_html( mcupr_t( 'sg_cr' ) ) . '</th><th scope="col" class="sg-num">' . esc_html( mcupr_t( 'sg_grade' ) ) . '</th></tr></thead><tbody>';
		foreach ( $list as $r ) {
			$h .= '<tr><td>' . esc_html( $r['c'] ) . '</td><td>' . esc_html( $r['n'] ) . '</td><td class="sg-num">' . esc_html( mcupr_sg_num( $r['cr'] ) ) . '</td><td class="sg-num"><b>' . esc_html( $r['g'] ) . '</b></td></tr>';
		}
		$h .= '</tbody><tfoot><tr><th scope="row" colspan="2">' . esc_html( mcupr_t( 'sg_gpa' ) ) . '</th><td class="sg-num">' . esc_html( mcupr_sg_num( $st['earned'] ) ) . '</td><td class="sg-num"><b>' . esc_html( null === $st['gpa'] ? '–' : number_format( $st['gpa'], 2 ) ) . '</b></td></tr></tfoot></table>';
	}
	$all = mcupr_sg_stats( $rows );
	$h  .= '<dl class="sg-sum"><div><dt>' . esc_html( mcupr_t( 'sg_gpax' ) ) . '</dt><dd>' . esc_html( null === $all['gpa'] ? '–' : number_format( $all['gpa'], 2 ) ) . '</dd></div><div><dt>' . esc_html( mcupr_t( 'sg_earned' ) ) . '</dt><dd>' . esc_html( mcupr_sg_num( $all['earned'] ) ) . '</dd></div></dl>';
	return '<div class="sg-grades">' . $h . '</div>';
}

/** The two sections of the student portal. $sid = the student's record. */
function mcupr_sg_portal_html( $sid ) {
	$sc = mcupr_sg_get( $sid, 'sched' );
	$gr = mcupr_sg_get( $sid, 'grades' );
	if ( ! $sc && ! $gr ) {
		return '';
	}
	$h = '';
	if ( $sc ) {
		$terms = array_values( array_unique( wp_list_pluck( $sc, 't' ) ) );
		rsort( $terms );
		$want = isset( $_GET['sgt'] ) ? mcupr_sg_term( sanitize_text_field( wp_unslash( $_GET['sgt'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$term = in_array( $want, $terms, true ) ? $want : $terms[0];
		$h   .= '<section class="stu-sec sg-sec" id="sg-sched" aria-labelledby="sg-sched-t"><h2 id="sg-sched-t" class="stu-h">' . esc_html( mcupr_t( 'sg_sched' ) ) . '</h2>';
		$h   .= '<div class="sg-bar print:hidden">';
		if ( count( $terms ) > 1 ) {
			$h .= '<form method="get" action="' . esc_url( mcupr_student_portal_url() ) . '#sg-sched" class="sg-pick">';
			foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( 'sgt' !== $k && is_string( $v ) ) {
					$h .= '<input type="hidden" name="' . esc_attr( sanitize_key( $k ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $v ) ) ) . '">';
				}
			}
			$h .= '<label for="sg-term">' . esc_html( mcupr_t( 'sg_pick' ) ) . '</label> <select id="sg-term" name="sgt">';
			foreach ( $terms as $t ) {
				$h .= '<option value="' . esc_attr( mcupr_sg_term_label( $t ) ) . '"' . selected( $t, $term, false ) . '>' . esc_html( mcupr_sg_term_label( $t ) ) . '</option>';
			}
			$h .= '</select> <button type="submit" class="btn stu-btn2">' . esc_html( mcupr_t( 'sg_show' ) ) . '</button></form>';
		}
		$h .= '<button type="button" class="btn stu-btn2" data-sg-print="sg-sched">' . esc_html( mcupr_t( 'sg_print' ) ) . '</button>';
		if ( isset( mcupr_sg_term_dates()[ $term ] ) ) {
			$h .= ' <a class="btn stu-btn2" href="' . esc_url( add_query_arg( 'mcupr_sg_ics', mcupr_sg_term_label( $term ), home_url( '/' ) ) ) . '">' . esc_html( mcupr_t( 'sg_ics' ) ) . '</a>';
		}
		$h .= '</div><p class="sg-cap">' . esc_html( sprintf( mcupr_t( 'sg_term' ), explode( '/', $term )[1], explode( '/', $term )[0] ) ) . '</p>' . mcupr_sg_sched_html( $sc, $term ) . '</section>';
	}
	if ( $gr ) {
		$h .= '<section class="stu-sec sg-sec" id="sg-grades" aria-labelledby="sg-grades-t"><h2 id="sg-grades-t" class="stu-h">' . esc_html( mcupr_t( 'sg_grades' ) ) . '</h2><div class="sg-bar print:hidden"><button type="button" class="btn stu-btn2" data-sg-print="sg-grades">' . esc_html( mcupr_t( 'sg_print' ) ) . '</button></div>' . mcupr_sg_grades_html( $gr ) . '<p class="stu-help">' . esc_html( mcupr_t( 'sg_note' ) ) . '</p></section>';
	}
	return $h;
}

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( get_query_var( 'mcupr_student' ) ) {
			wp_enqueue_script( 'mcupr-sg', get_theme_file_uri( 'assets/js/student-sg.js' ), array(), MCUPR_VERSION, true );
		}
	}
);

/** The weekly schedule as a calendar file (.ics) – the student's own, for a term whose dates are known. */
add_filter(
	'query_vars',
	static function ( $v ) {
		$v[] = 'mcupr_sg_ics';
		return $v;
	}
);
add_action(
	'template_redirect',
	static function () {
		$q = get_query_var( 'mcupr_sg_ics' );
		if ( ! $q ) {
			return;
		}
		$sid   = is_user_logged_in() ? mcupr_current_student( true ) : 0;
		$term  = mcupr_sg_term( (string) $q );
		$dates = mcupr_sg_term_dates();
		if ( ! $sid || '' === $term || ! isset( $dates[ $term ] ) ) {
			wp_safe_redirect( mcupr_student_portal_url() );
			exit;
		}
		$rows = array_filter(
			mcupr_sg_get( $sid, 'sched' ),
			static function ( $r ) use ( $term ) {
				return $r['t'] === $term;
			}
		);
		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="schedule-' . str_replace( '/', '-', mcupr_sg_term_label( $term ) ) . '.ics"' );
		echo mcupr_sg_ics( $rows, $dates[ $term ], $sid ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);
function mcupr_sg_ics_text( $s ) {
	return str_replace( array( '\\', ';', ',', "\n", "\r" ), array( '\\\\', '\;', '\,', '\n', '' ), (string) $s );
}
function mcupr_sg_ics( $rows, $dates, $sid ) {
	$tz    = wp_timezone();
	$until = ( new DateTime( $dates['end'] . ' 23:59:59', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	$out   = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//MCUPR//Schedule//EN', 'CALSCALE:GREGORIAN' );
	$days  = array( 1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU' );
	foreach ( $rows as $i => $r ) {
		$d0 = new DateTime( $dates['start'], $tz );
		while ( (int) $d0->format( 'N' ) !== (int) $r['d'] ) {
			$d0->modify( '+1 day' );
		}
		$st = ( clone $d0 )->setTime( intdiv( $r['s'], 60 ), $r['s'] % 60 )->setTimezone( new DateTimeZone( 'UTC' ) );
		$en = ( clone $d0 )->setTime( intdiv( $r['e'], 60 ), $r['e'] % 60 )->setTimezone( new DateTimeZone( 'UTC' ) );
		$out = array_merge(
			$out,
			array(
				'BEGIN:VEVENT',
				'UID:' . md5( $sid . '|' . $r['t'] . '|' . $r['c'] . '|' . $r['d'] . '|' . $r['s'] . '|' . $i ) . '@mcupr',
				'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
				'DTSTART:' . $st->format( 'Ymd\THis\Z' ),
				'DTEND:' . $en->format( 'Ymd\THis\Z' ),
				'RRULE:FREQ=WEEKLY;UNTIL=' . $until . ';BYDAY=' . $days[ (int) $r['d'] ],
				'SUMMARY:' . mcupr_sg_ics_text( $r['c'] . ' ' . $r['n'] ),
				'LOCATION:' . mcupr_sg_ics_text( $r['r'] ),
				'DESCRIPTION:' . mcupr_sg_ics_text( $r['tc'] ),
				'END:VEVENT',
			)
		);
	}
	$out[] = 'END:VCALENDAR';
	return implode( "\r\n", $out ) . "\r\n";
}
