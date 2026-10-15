<?php
/**
 * Students: bulk input from a CSV file (MCUPR → Students → Import students).
 *
 *   1. choose a file (Excel: "Save as CSV UTF-8"; Thai Windows files in TIS-620 are understood too) and a mode;
 *   2. a preview: how many are new / changed / unchanged / wrong, with the reason and the row of every mistake;
 *   3. the import, in chunks of rows so that thousands of students never time out; the page continues by itself;
 *   4. a report (also downloadable as a CSV of the rows that were not imported) and a short log.
 * Columns are recognised by their names in Thai or English, in any order. The same screen offers a template, an
 * export of all students and a "sample file" of fictional students.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_SI_CHUNK = 250;
const MCUPR_SI_MAX   = 20000;

/** Names a column may have in a file → the field. */
function mcupr_si_headers() {
	return array(
		'sid'     => array( 'รหัสนิสิต', 'รหัสนักศึกษา', 'รหัสประจำตัว', 'รหัส', 'studentid', 'studentcode', 'sid', 'id' ),
		'name'    => array( 'ชื่อสกุล', 'ชื่อนามสกุล', 'ชื่อ-สกุล', 'ชื่อ-นามสกุล', 'ชื่อ', 'name', 'fullname', 'studentname' ),
		'name_en' => array( 'ชื่ออังกฤษ', 'ชื่อภาษาอังกฤษ', 'nameen', 'englishname', 'nameenglish' ),
		'level'   => array( 'ระดับ', 'ระดับการศึกษา', 'level', 'degree' ),
		'year'    => array( 'ชั้นปี', 'ปี', 'year', 'yearofstudy' ),
		'entry'   => array( 'ปีที่เข้า', 'ปีที่เข้าศึกษา', 'ปีการศึกษาที่เข้า', 'entry', 'entryyear', 'yearofentry' ),
		'faculty' => array( 'คณะ', 'faculty', 'school' ),
		'major'   => array( 'สาขา', 'สาขาวิชา', 'หลักสูตร', 'major', 'program', 'programme' ),
		'status'  => array( 'สถานะ', 'สถานภาพ', 'status' ),
		'email'   => array( 'อีเมล', 'อีเมลมหาวิทยาลัย', 'email', 'e-mail', 'mail' ),
		'phone'   => array( 'โทร', 'โทรศัพท์', 'เบอร์โทร', 'เบอร์โทรศัพท์', 'phone', 'tel', 'telephone', 'mobile' ),
		'note'    => array( 'หมายเหตุ', 'note', 'notes', 'remark' ),
	);
}

function mcupr_si_key( $s ) {
	return mb_strtolower( preg_replace( '/[\s_\.\-]+/u', '', trim( (string) $s ) ) );
}

/** The text of a file as UTF-8 (BOM removed; TIS-620 / Windows-874 converted). */
function mcupr_si_utf8( $raw ) {
	$raw = (string) $raw;
	if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
		$raw = substr( $raw, 3 );
	} elseif ( 0 === strpos( $raw, "\xFF\xFE" ) || 0 === strpos( $raw, "\xFE\xFF" ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'UTF-16' );
	}
	if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) { // a Thai Windows file (TIS-620 / Windows-874): iconv when there is one, else decoded by hand
		foreach ( array( 'CP874', 'TIS-620', 'ISO-8859-11' ) as $enc ) {
			$c = function_exists( 'iconv' ) ? @iconv( $enc, 'UTF-8//IGNORE', $raw ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( is_string( $c ) && '' !== $c && mb_check_encoding( $c, 'UTF-8' ) ) {
				return $c;
			}
		}
		$extra = array( 0x85 => "\u{2026}", 0x91 => "\u{2018}", 0x92 => "\u{2019}", 0x93 => "\u{201C}", 0x94 => "\u{201D}", 0x95 => "\u{2022}", 0x96 => "\u{2013}", 0x97 => "\u{2014}" );
		$out   = '';
		for ( $i = 0, $n = strlen( $raw ); $i < $n; $i++ ) {
			$b = ord( $raw[ $i ] );
			if ( $b < 0x80 ) {
				$out .= $raw[ $i ];
			} elseif ( $b >= 0xA1 && $b <= 0xFB ) {
				$out .= mb_chr( 0x0E01 + $b - 0xA1, 'UTF-8' );
			} elseif ( isset( $extra[ $b ] ) ) {
				$out .= $extra[ $b ];
			} elseif ( 0xA0 === $b ) {
				$out .= ' ';
			}
		}
		return $out;
	}
	return $raw;
}

/**
 * Read the text of a CSV into rows. Returns array( 'cols' => field => column index, 'rows' => list of array( line, values ),
 * 'unknown' => unrecognised header names, 'delimiter' => ',' ) or a WP_Error.
 */
function mcupr_si_parse( $text ) {
	$text = mcupr_si_utf8( $text );
	$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
	if ( '' === trim( $text ) ) {
		return new WP_Error( 'empty', 'empty' );
	}
	$first = strtok( $text, "\n" );
	$best  = ',';
	$max   = -1;
	foreach ( array( ',', "\t", ';' ) as $d ) {
		$n = substr_count( (string) $first, $d );
		if ( $n > $max ) {
			$max  = $n;
			$best = $d;
		}
	}
	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $fh, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	rewind( $fh );
	$head = fgetcsv( $fh, 0, $best, '"', '' );
	if ( ! is_array( $head ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'empty', 'empty' );
	}
	$cols    = array();
	$unknown = array();
	foreach ( $head as $i => $h ) {
		$k     = mcupr_si_key( $h );
		$found = '';
		foreach ( mcupr_si_headers() as $field => $names ) {
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
	if ( ! isset( $cols['sid'] ) || ! isset( $cols['name'] ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'columns', 'the file needs a student ID column and a name column' );
	}
	$rows = array();
	$line = 1;
	while ( false !== ( $r = fgetcsv( $fh, 0, $best, '"', '' ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$line++;
		if ( 1 === count( $r ) && ( null === $r[0] || '' === trim( (string) $r[0] ) ) ) {
			continue; // an empty line
		}
		$rows[] = array( $line, $r );
		if ( count( $rows ) > MCUPR_SI_MAX ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'big', 'too many rows' );
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return array( 'cols' => $cols, 'rows' => $rows, 'unknown' => $unknown, 'delimiter' => $best );
}

/**
 * Check one row of a file against what is in the database.
 * Returns array( 'row' => cleaned values, 'errors' => list of codes, 'warnings' => list of codes ).
 */
function mcupr_si_check( $values, $cols, $map, &$seen, $mode ) {
	$get = static function ( $field ) use ( $values, $cols ) {
		return isset( $cols[ $field ], $values[ $cols[ $field ] ] ) ? (string) $values[ $cols[ $field ] ] : null;
	};
	$row  = array();
	$err  = array();
	$warn = array();
	foreach ( array_keys( mcupr_student_fields() ) as $field ) {
		$raw = $get( $field );
		if ( null === $raw ) {
			$row[ $field ] = null; // the file has no such column: leave it as it is
			continue;
		}
		list( $v, $w ) = mcupr_student_clean( $field, $raw );
		if ( in_array( $field, array( 'level', 'status' ), true ) && '' === trim( $raw ) ) {
			$v = ''; // an empty cell: an update keeps what is there, a new student gets the usual value
		}
		$row[ $field ] = $v;
		if ( '' !== $w ) {
			$warn[] = $w;
		}
	}
	$row['name'] = mb_substr( sanitize_text_field( (string) $get( 'name' ) ), 0, 120 );
	if ( '' === $row['sid'] ) {
		$err[] = 'sid';
	} elseif ( isset( $seen[ $row['sid'] ] ) ) {
		$err[] = 'dup';
	} else {
		$seen[ $row['sid'] ] = true;
	}
	if ( '' === $row['name'] ) {
		$err[] = 'name';
	}
	if ( ! $err ) {
		$exists = isset( $map[ $row['sid'] ] );
		if ( ! $exists && 'update_only' === $mode ) {
			$err[] = 'missing';
		}
	}
	return array( 'row' => $row, 'errors' => $err, 'warnings' => $warn );
}

/** The words for the codes above. */
function mcupr_si_msg( $code ) {
	$m = array(
		'sid'     => __( 'Student ID is missing or not valid (4–20 letters, digits or dashes)', 'mcupr' ),
		'dup'     => __( 'The same student ID appears earlier in the file', 'mcupr' ),
		'name'    => __( 'Name is missing', 'mcupr' ),
		'missing' => __( 'Not in the database (the mode is “update only”)', 'mcupr' ),
		'level'   => __( 'Level not understood, “Bachelor” used', 'mcupr' ),
		'status'  => __( 'Status not understood, “Studying” used', 'mcupr' ),
		'year'    => __( 'Year of study is not a number from 1 to 12, left empty', 'mcupr' ),
		'entry'   => __( 'Year of entry is not a year, left empty', 'mcupr' ),
		'email'   => __( 'E-mail is not valid, left empty', 'mcupr' ),
		'error'   => __( 'Could not be saved', 'mcupr' ),
	);
	return isset( $m[ $code ] ) ? $m[ $code ] : $code;
}

/* ------------------------------------------------------------------ the saved upload (one file per import) */

function mcupr_si_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'mcupr-import';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

function mcupr_si_file( $token ) {
	return preg_match( '/^[a-f0-9]{32}$/', (string) $token ) ? mcupr_si_dir() . '/' . $token . '.json' : '';
}

function mcupr_si_load( $token ) {
	$f = mcupr_si_file( $token );
	if ( ! $f || ! is_readable( $f ) ) {
		return null;
	}
	$d = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $d ) ? $d : null;
}

function mcupr_si_save( $token, $d ) {
	return false !== file_put_contents( mcupr_si_file( $token ), wp_json_encode( $d ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function mcupr_si_cleanup() {
	foreach ( (array) glob( mcupr_si_dir() . '/*.json' ) as $f ) {
		if ( filemtime( $f ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $f );
		}
	}
}

/* ------------------------------------------------------------------ the pages */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Import students', 'mcupr' ), __( 'Import students', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-import', 'mcupr_si_page' );
	}
);

function mcupr_si_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => 'mcupr-student-import' ), $args ), admin_url( 'admin.php' ) );
}

function mcupr_si_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	mcupr_si_cleanup();
	$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$token = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<style>.mcupr-si .card{max-width:62rem;overflow-wrap:anywhere}.mcupr-si table.widefat{max-width:62rem}.mcupr-si .bar{height:12px;border-radius:999px;background:#dcdcde;overflow:hidden;max-width:40rem}.mcupr-si .bar i{display:block;height:100%;background:#2271b1}.mcupr-si .sc{display:flex;flex-wrap:wrap;gap:10px;margin:1rem 0}.mcupr-si .sc div{min-width:9rem;padding:10px 16px;border:1px solid #dcdcde;border-radius:10px;background:#fff}.mcupr-si .sc b{display:block;font-size:1.6rem;line-height:1.2}.mcupr-si .scroll{overflow-x:auto}</style><div class="wrap mcupr-si"><h1>' . esc_html__( 'Import students', 'mcupr' ) . '</h1>';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['err'] ) ) {
		$e = sanitize_key( wp_unslash( $_GET['err'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$t = array( 'file' => __( 'No file was uploaded (or it is bigger than 5 MB).', 'mcupr' ), 'empty' => __( 'The file is empty.', 'mcupr' ), 'columns' => __( 'The first line must name the columns, and there must be a student ID column (รหัสนิสิต) and a name column (ชื่อ-สกุล).', 'mcupr' ), 'big' => __( 'The file has too many rows (20,000 at most). Split it in parts.', 'mcupr' ), 'gone' => __( 'That import has expired. Upload the file again.', 'mcupr' ) );
		echo '<div class="notice notice-error"><p>' . esc_html( isset( $t[ $e ] ) ? $t[ $e ] : $e ) . '</p></div>';
	}
	if ( 'preview' === $step && $token ) {
		mcupr_si_preview( $token );
	} elseif ( 'run' === $step && $token ) {
		mcupr_si_run( $token );
	} elseif ( 'done' === $step ) {
		mcupr_si_report();
	} else {
		mcupr_si_start();
	}
	echo '</div>';
}

function mcupr_si_start() {
	$counts = mcupr_student_counts();
	echo '<p style="max-width:62rem">' . esc_html__( 'Add or update many students at once from a spreadsheet saved as CSV (in Excel: Save as → CSV UTF-8). The first line names the columns, in Thai or English and in any order. Only the student ID and the name are required.', 'mcupr' ) . '</p>';
	echo '<div class="sc"><div><b>' . (int) $counts['all'] . '</b>' . esc_html__( 'students in the database', 'mcupr' ) . '</div><div><b>' . (int) $counts['studying'] . '</b>' . esc_html__( 'studying', 'mcupr' ) . '</div><div><b>' . (int) $counts['accounts'] . '</b>' . esc_html__( 'with a login account', 'mcupr' ) . '</div></div>';
	echo '<div class="card"><h2>' . esc_html__( '1. Choose the file', 'mcupr' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mcupr_si_upload">';
	wp_nonce_field( 'mcupr_si_upload', 'mcupr_si_nonce' );
	echo '<p><label for="si-file"><strong>' . esc_html__( 'CSV file', 'mcupr' ) . '</strong></label><br><input id="si-file" type="file" name="file" accept=".csv,.txt,.tsv,text/csv,text/plain" required></p><fieldset><legend><strong>' . esc_html__( 'What to do with students already in the database (same student ID)', 'mcupr' ) . '</strong></legend>';
	foreach ( array( 'add_update' => __( 'Add the new and update the others (cells left empty keep what is there)', 'mcupr' ), 'add_only' => __( 'Add the new only; leave existing students as they are', 'mcupr' ), 'update_only' => __( 'Update existing students only; add nobody', 'mcupr' ) ) as $k => $label ) {
		echo '<label style="display:block;margin:.25rem 0"><input type="radio" name="mode" value="' . esc_attr( $k ) . '"' . checked( 'add_update' === $k, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</fieldset><p><label><input type="checkbox" name="accounts" value="1"> ' . esc_html__( 'Also make a login account for each student who has an e-mail (no e-mail is sent yet)', 'mcupr' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Check the file', 'mcupr' ) . '</button></p></form></div>';
	echo '<div class="card"><h2>' . esc_html__( 'Template, sample and export', 'mcupr' ) . '</h2><p>';
	foreach ( array( 'mcupr_si_template' => __( 'Download the template (CSV)', 'mcupr' ), 'mcupr_si_export' => __( 'Export all students (CSV)', 'mcupr' ) ) as $action => $label ) {
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action ), $action ) ) . '">' . esc_html( $label ) . '</a> ';
	}
	echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1rem"><input type="hidden" name="action" value="mcupr_si_upload"><input type="hidden" name="sample" value="1"><input type="hidden" name="mode" value="add_update">';
	wp_nonce_field( 'mcupr_si_upload', 'mcupr_si_nonce' );
	echo '<button class="button">' . esc_html__( 'Try it with the sample file (60 fictional students)', 'mcupr' ) . '</button> <span class="description">' . esc_html__( 'Remove them afterwards: Students → filter or select all → Move to Trash. Their IDs start with 99.', 'mcupr' ) . '</span></form></div>';
	echo '<div class="card"><h2>' . esc_html__( 'The columns', 'mcupr' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Column', 'mcupr' ) . '</th><th>' . esc_html__( 'Names that are recognised', 'mcupr' ) . '</th><th>' . esc_html__( 'Values', 'mcupr' ) . '</th></tr></thead><tbody>';
	$hints = array(
		'sid'     => __( 'Required. 4–20 letters, digits or dashes', 'mcupr' ),
		'name'    => __( 'Required', 'mcupr' ),
		'level'   => __( 'Bachelor / Master / Doctoral (or ตรี / โท / เอก)', 'mcupr' ),
		'status'  => __( 'Studying / On leave / Graduated / Withdrawn (or กำลังศึกษา / พักการศึกษา / สำเร็จการศึกษา / ลาออก)', 'mcupr' ),
		'year'    => __( 'A number, 1–12', 'mcupr' ),
		'email'   => __( 'Needed to make a login account', 'mcupr' ),
	);
	foreach ( mcupr_si_headers() as $field => $names ) {
		echo '<tr><td><code>' . esc_html( $field ) . '</code></td><td>' . esc_html( implode( ', ', array_slice( $names, 0, 5 ) ) ) . '</td><td>' . esc_html( isset( $hints[ $field ] ) ? $hints[ $field ] : '' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
	$log = get_option( 'mcupr_student_imports', array() );
	if ( $log ) {
		echo '<div class="card"><h2>' . esc_html__( 'Recent imports', 'mcupr' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'mcupr' ) . '</th><th>' . esc_html__( 'Who', 'mcupr' ) . '</th><th>' . esc_html__( 'Added', 'mcupr' ) . '</th><th>' . esc_html__( 'Updated', 'mcupr' ) . '</th><th>' . esc_html__( 'Not imported', 'mcupr' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( array_reverse( $log ), 0, 10 ) as $l ) {
			echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', $l['t'] ) ) . '</td><td>' . esc_html( $l['by'] ) . '</td><td>' . (int) $l['added'] . '</td><td>' . (int) $l['updated'] . '</td><td>' . (int) $l['errors'] . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}

/** Upload (or the sample), check, save, go to the preview. */
add_action(
	'admin_post_mcupr_si_upload',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! isset( $_POST['mcupr_si_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_si_nonce'] ) ), 'mcupr_si_upload' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$text = '';
		if ( ! empty( $_POST['sample'] ) ) {
			$f    = get_theme_file_path( 'starter/mcupr-students-sample.csv' );
			$text = is_readable( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		} elseif ( isset( $_FILES['file']['tmp_name'], $_FILES['file']['size'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) && (int) $_FILES['file']['size'] > 0 && (int) $_FILES['file']['size'] <= 5 * MB_IN_BYTES ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$text = (string) file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions
		}
		if ( '' === $text ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => 'file' ) ) );
			exit;
		}
		$p = mcupr_si_parse( $text );
		if ( is_wp_error( $p ) ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => $p->get_error_code() ) ) );
			exit;
		}
		$mode  = isset( $_POST['mode'] ) && in_array( $_POST['mode'], array( 'add_update', 'add_only', 'update_only' ), true ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'add_update';
		$token = bin2hex( random_bytes( 16 ) );
		$ok    = mcupr_si_save( $token, array( 'cols' => $p['cols'], 'rows' => $p['rows'], 'unknown' => $p['unknown'], 'mode' => $mode, 'accounts' => ! empty( $_POST['accounts'] ), 'user' => get_current_user_id(), 'sample' => ! empty( $_POST['sample'] ) ) );
		wp_safe_redirect( $ok ? mcupr_si_url( array( 'step' => 'preview', 't' => $token ) ) : mcupr_si_url( array( 'err' => 'file' ) ) );
		exit;
	}
);

function mcupr_si_preview( $token ) {
	$d = mcupr_si_load( $token );
	if ( ! $d ) {
		echo '<p><a class="button" href="' . esc_url( mcupr_si_url( array( 'err' => 'gone' ) ) ) . '">' . esc_html__( 'Start again', 'mcupr' ) . '</a></p>';
		return;
	}
	$map  = mcupr_student_map();
	$seen = array();
	$n    = array( 'new' => 0, 'upd' => 0, 'err' => 0, 'warn' => 0 );
	$errs = array();
	$show = array();
	foreach ( $d['rows'] as $r ) {
		$c = mcupr_si_check( $r[1], $d['cols'], $map, $seen, $d['mode'] );
		if ( $c['errors'] ) {
			$n['err']++;
			if ( count( $errs ) < 60 ) {
				$errs[] = array( $r[0], $c['row']['sid'], $c['row']['name'], array_map( 'mcupr_si_msg', $c['errors'] ) );
			}
			continue;
		}
		isset( $map[ $c['row']['sid'] ] ) ? $n['upd']++ : $n['new']++;
		if ( $c['warnings'] ) {
			$n['warn']++;
		}
		if ( count( $show ) < 8 ) {
			$show[] = $c['row'];
		}
	}
	$modes = array( 'add_update' => __( 'Add the new and update the others', 'mcupr' ), 'add_only' => __( 'Add the new only', 'mcupr' ), 'update_only' => __( 'Update existing only', 'mcupr' ) );
	echo '<div class="card"><h2>' . esc_html__( '2. Check', 'mcupr' ) . '</h2><p>' . esc_html( sprintf( /* translators: 1: rows, 2: mode */ __( '%1$d rows read. Mode: %2$s.', 'mcupr' ), count( $d['rows'] ), $modes[ $d['mode'] ] ) ) . '</p>';
	echo '<div class="sc"><div><b>' . (int) $n['new'] . '</b>' . esc_html( 'update_only' === $d['mode'] ? __( 'new (will be skipped)', 'mcupr' ) : __( 'new students', 'mcupr' ) ) . '</div><div><b>' . (int) $n['upd'] . '</b>' . esc_html( 'add_only' === $d['mode'] ? __( 'already there (will be skipped)', 'mcupr' ) : __( 'to update', 'mcupr' ) ) . '</div><div><b style="color:' . ( $n['err'] ? '#b32d2e' : 'inherit' ) . '">' . (int) $n['err'] . '</b>' . esc_html__( 'with a mistake (not imported)', 'mcupr' ) . '</div><div><b>' . (int) $n['warn'] . '</b>' . esc_html__( 'with a warning (imported)', 'mcupr' ) . '</div></div>';
	if ( ! empty( $d['unknown'] ) ) {
		echo '<p class="description">' . esc_html( sprintf( /* translators: %s: names */ __( 'Columns that were not recognised and are ignored: %s', 'mcupr' ), implode( ', ', array_slice( $d['unknown'], 0, 12 ) ) ) ) . '</p>';
	}
	if ( $errs ) {
		echo '<h3>' . esc_html__( 'Mistakes', 'mcupr' ) . '</h3><div class="scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Row', 'mcupr' ) . '</th><th>' . esc_html__( 'Student ID', 'mcupr' ) . '</th><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Problem', 'mcupr' ) . '</th></tr></thead><tbody>';
		foreach ( $errs as $e ) {
			echo '<tr><td>' . (int) $e[0] . '</td><td>' . esc_html( $e[1] ) . '</td><td>' . esc_html( $e[2] ) . '</td><td>' . esc_html( implode( '; ', $e[3] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( $n['err'] > count( $errs ) ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %d: more */ __( '…and %d more.', 'mcupr' ), $n['err'] - count( $errs ) ) ) . '</p>';
		}
	}
	if ( $show ) {
		echo '<h3>' . esc_html__( 'The first rows as they will be saved', 'mcupr' ) . '</h3><div class="scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Student ID', 'mcupr' ) . '</th><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Level', 'mcupr' ) . '</th><th>' . esc_html__( 'Year', 'mcupr' ) . '</th><th>' . esc_html__( 'Major / programme', 'mcupr' ) . '</th><th>' . esc_html__( 'Status', 'mcupr' ) . '</th><th>' . esc_html__( 'E-mail', 'mcupr' ) . '</th></tr></thead><tbody>';
		$lv = mcupr_student_levels();
		$st = mcupr_student_statuses();
		foreach ( $show as $r ) {
			echo '<tr><td>' . esc_html( $r['sid'] ) . '</td><td>' . esc_html( $r['name'] ) . '</td><td>' . esc_html( isset( $lv[ $r['level'] ] ) ? $lv[ $r['level'] ] : '' ) . '</td><td>' . esc_html( (string) $r['year'] ) . '</td><td>' . esc_html( (string) $r['major'] ) . '</td><td>' . esc_html( isset( $st[ $r['status'] ] ) ? $st[ $r['status'] ] : '' ) . '</td><td>' . esc_html( (string) $r['email'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:1.25rem"><input type="hidden" name="action" value="mcupr_si_start"><input type="hidden" name="t" value="' . esc_attr( $token ) . '">';
	wp_nonce_field( 'mcupr_si_start' . $token, 'mcupr_si_nonce' );
	echo '<p><label><input type="checkbox" name="accounts" value="1"' . checked( ! empty( $d['accounts'] ), true, false ) . '> ' . esc_html__( 'Also make a login account for each student who has an e-mail (no e-mail is sent yet)', 'mcupr' ) . '</label></p>';
	echo '<p><button class="button button-primary"' . ( $n['new'] + $n['upd'] ? '' : ' disabled' ) . '>' . esc_html__( 'Import now', 'mcupr' ) . '</button> <a class="button" href="' . esc_url( mcupr_si_url() ) . '">' . esc_html__( 'Cancel', 'mcupr' ) . '</a></p></form></div>';
}

/** Begin the run: the choice about accounts is saved, the state is empty. */
add_action(
	'admin_post_mcupr_si_start',
	static function () {
		$token = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : '';
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! $token || ! isset( $_POST['mcupr_si_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_si_nonce'] ) ), 'mcupr_si_start' . $token ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$d = mcupr_si_load( $token );
		if ( ! $d ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => 'gone' ) ) );
			exit;
		}
		$d['accounts'] = ! empty( $_POST['accounts'] );
		$d['state']    = array( 'offset' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'accounts' => 0, 'errors' => array(), 'warn' => 0 );
		mcupr_si_save( $token, $d );
		wp_safe_redirect( mcupr_si_url( array( 'step' => 'run', 't' => $token ) ) );
		exit;
	}
);

/** One chunk of the import, then the page continues by itself until the end. */
function mcupr_si_run( $token ) {
	$d = mcupr_si_load( $token );
	if ( ! $d || empty( $d['state'] ) ) {
		echo '<p><a class="button" href="' . esc_url( mcupr_si_url( array( 'err' => 'gone' ) ) ) . '">' . esc_html__( 'Start again', 'mcupr' ) . '</a></p>';
		return;
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$s     = $d['state'];
	$total = count( $d['rows'] );
	$map   = mcupr_student_map();
	$seen  = array();
	// rows before this chunk count as seen, so a repeated ID later in the file is still found
	for ( $i = 0; $i < $s['offset']; $i++ ) {
		list( $sid ) = mcupr_student_clean( 'sid', isset( $d['cols']['sid'], $d['rows'][ $i ][1][ $d['cols']['sid'] ] ) ? (string) $d['rows'][ $i ][1][ $d['cols']['sid'] ] : '' );
		if ( '' !== $sid ) {
			$seen[ $sid ] = true;
		}
	}
	wp_defer_term_counting( true );
	$end = min( $total, $s['offset'] + MCUPR_SI_CHUNK );
	for ( $i = $s['offset']; $i < $end; $i++ ) {
		$line = $d['rows'][ $i ][0];
		$c    = mcupr_si_check( $d['rows'][ $i ][1], $d['cols'], $map, $seen, $d['mode'] );
		if ( $c['errors'] ) {
			$s['errors'][] = array( $line, $c['row']['sid'], $c['row']['name'], implode( '; ', array_map( 'mcupr_si_msg', $c['errors'] ) ) );
			continue;
		}
		$s['warn'] += $c['warnings'] ? 1 : 0;
		list( $res, $id ) = mcupr_student_upsert( $c['row'], $d['mode'], isset( $map[ $c['row']['sid'] ] ) ? $map[ $c['row']['sid'] ] : 0 );
		if ( 'error' === $res ) {
			$s['errors'][] = array( $line, $c['row']['sid'], $c['row']['name'], mcupr_si_msg( 'error' ) );
			continue;
		}
		if ( $id ) {
			$map[ $c['row']['sid'] ] = $id;
		}
		$k       = 'added' === $res ? 'added' : ( 'updated' === $res ? 'updated' : ( 'unchanged' === $res ? 'unchanged' : 'skipped' ) );
		$s[ $k ]++;
		if ( $d['accounts'] && $id && in_array( $res, array( 'added', 'updated', 'unchanged' ), true ) && '' !== (string) get_post_meta( $id, '_mcupr_s_email', true ) && ! get_post_meta( $id, '_mcupr_s_user', true ) ) {
			if ( ! is_wp_error( mcupr_student_make_account( $id ) ) ) {
				$s['accounts']++;
			}
		}
	}
	wp_defer_term_counting( false );
	$s['offset'] = $end;
	$d['state']  = $s;
	if ( $end < $total ) {
		mcupr_si_save( $token, $d );
		$pct = (int) floor( $end / max( 1, $total ) * 100 );
		echo '<div class="card"><h2>' . esc_html__( '3. Importing…', 'mcupr' ) . '</h2><div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $pct . '"><i style="width:' . (int) $pct . '%"></i></div><p>' . esc_html( sprintf( /* translators: 1: done, 2: all */ __( '%1$d of %2$d rows done. Please keep this page open; it continues by itself.', 'mcupr' ), $end, $total ) ) . '</p><p><a class="button" href="' . esc_url( mcupr_si_url( array( 'step' => 'run', 't' => $token ) ) ) . '">' . esc_html__( 'Continue', 'mcupr' ) . '</a></p></div>';
		echo '<script>setTimeout(function(){location.href=' . wp_json_encode( mcupr_si_url( array( 'step' => 'run', 't' => $token ) ) ) . ';},400);</script>';
		return;
	}
	// finished: keep the report for the next page, drop the upload
	$by  = wp_get_current_user();
	$log = get_option( 'mcupr_student_imports', array() );
	$log[] = array( 't' => time(), 'by' => $by->user_login, 'added' => $s['added'], 'updated' => $s['updated'], 'errors' => count( $s['errors'] ), 'rows' => $total );
	update_option( 'mcupr_student_imports', array_slice( $log, -20 ), false );
	set_transient( 'mcupr_si_report_' . get_current_user_id(), array( 'state' => $s, 'total' => $total, 'sample' => ! empty( $d['sample'] ) ), HOUR_IN_SECONDS );
	wp_delete_file( mcupr_si_file( $token ) );
	echo '<script>location.href=' . wp_json_encode( mcupr_si_url( array( 'step' => 'done' ) ) ) . ';</script><p><a class="button button-primary" href="' . esc_url( mcupr_si_url( array( 'step' => 'done' ) ) ) . '">' . esc_html__( 'Show the report', 'mcupr' ) . '</a></p>';
}

function mcupr_si_report() {
	$r = get_transient( 'mcupr_si_report_' . get_current_user_id() );
	if ( ! $r ) {
		echo '<p><a class="button" href="' . esc_url( mcupr_si_url() ) . '">' . esc_html__( 'Back', 'mcupr' ) . '</a></p>';
		return;
	}
	$s = $r['state'];
	echo '<div class="card"><h2>' . esc_html__( 'Done', 'mcupr' ) . '</h2><div class="sc"><div><b>' . (int) $s['added'] . '</b>' . esc_html__( 'added', 'mcupr' ) . '</div><div><b>' . (int) $s['updated'] . '</b>' . esc_html__( 'updated', 'mcupr' ) . '</div><div><b>' . (int) $s['unchanged'] . '</b>' . esc_html__( 'unchanged', 'mcupr' ) . '</div><div><b>' . (int) $s['skipped'] . '</b>' . esc_html__( 'skipped by the mode', 'mcupr' ) . '</div><div><b>' . (int) $s['accounts'] . '</b>' . esc_html__( 'login accounts made', 'mcupr' ) . '</div><div><b style="color:' . ( $s['errors'] ? '#b32d2e' : 'inherit' ) . '">' . count( $s['errors'] ) . '</b>' . esc_html__( 'not imported', 'mcupr' ) . '</div></div>';
	if ( $s['errors'] ) {
		echo '<h3>' . esc_html__( 'Rows that were not imported', 'mcupr' ) . '</h3><div class="scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Row', 'mcupr' ) . '</th><th>' . esc_html__( 'Student ID', 'mcupr' ) . '</th><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Problem', 'mcupr' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $s['errors'], 0, 100 ) as $e ) {
			echo '<tr><td>' . (int) $e[0] . '</td><td>' . esc_html( $e[1] ) . '</td><td>' . esc_html( $e[2] ) . '</td><td>' . esc_html( $e[3] ) . '</td></tr>';
		}
		echo '</tbody></table></div><p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_si_errors' ), 'mcupr_si_errors' ) ) . '">' . esc_html__( 'Download these rows (CSV)', 'mcupr' ) . '</a></p>';
	}
	echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_student' ) ) . '">' . esc_html__( 'Open the students', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( mcupr_si_url() ) . '">' . esc_html__( 'Import another file', 'mcupr' ) . '</a></p></div>';
}

/* ------------------------------------------------------------------ downloads */

/** A cell safe to open in a spreadsheet (a leading = + - @ would run as a formula). */
function mcupr_si_cell( $v ) {
	$v = (string) $v;
	return '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $v : $v;
}

function mcupr_si_csv_out( $filename, $rows ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo "\xEF\xBB\xBF"; // so that Excel reads Thai
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $rows as $r ) {
		fputcsv( $out, array_map( 'mcupr_si_cell', $r ), ',', '"', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/** The columns in the order of the template and the export. */
function mcupr_si_columns() {
	return array( 'sid' => 'รหัสนิสิต', 'name' => 'ชื่อ-สกุล', 'name_en' => 'ชื่ออังกฤษ', 'level' => 'ระดับ', 'year' => 'ชั้นปี', 'entry' => 'ปีที่เข้า', 'faculty' => 'คณะ', 'major' => 'สาขา', 'status' => 'สถานะ', 'email' => 'อีเมล', 'phone' => 'โทร', 'note' => 'หมายเหตุ' );
}

add_action(
	'admin_post_mcupr_si_template',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! check_admin_referer( 'mcupr_si_template' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		mcupr_si_csv_out(
			'mcupr-students-template.csv',
			array(
				array_values( mcupr_si_columns() ),
				array( '6600000001', 'นายตัวอย่าง ใจดี', 'Tuayang Jaidee', 'ตรี', '2', '2567', 'คณะพุทธศาสตร์', 'พุทธศาสตร์', 'กำลังศึกษา', 'tuayang.j@example.org', '081-000-0001', '' ),
				array( '6600000002', 'นางสาวสมหญิง รักเรียน', '', 'โท', '1', '2568', 'คณะมนุษยศาสตร์', 'ภาษาอังกฤษ', 'กำลังศึกษา', 'somying.r@example.org', '', 'ตัวอย่างแถวที่สอง' ),
			)
		);
	}
);

add_action(
	'admin_post_mcupr_si_export',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! check_admin_referer( 'mcupr_si_export' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$rows = array( array_values( mcupr_si_columns() ) );
		$ids  = get_posts( array( 'post_type' => MCUPR_STU, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$d    = mcupr_student_data( $id );
			$line = array();
			foreach ( array_keys( mcupr_si_columns() ) as $k ) {
				$line[] = isset( $d[ $k ] ) ? $d[ $k ] : '';
			}
			$line[1] = $d['name'];
			$rows[]  = $line;
		}
		mcupr_si_csv_out( 'mcupr-students-' . gmdate( 'Y-m-d' ) . '.csv', $rows );
	}
);

add_action(
	'admin_post_mcupr_si_errors',
	static function () {
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! check_admin_referer( 'mcupr_si_errors' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$r    = get_transient( 'mcupr_si_report_' . get_current_user_id() );
		$rows = array( array( 'แถว', 'รหัสนิสิต', 'ชื่อ-สกุล', 'ปัญหา' ) );
		foreach ( $r ? $r['state']['errors'] : array() as $e ) {
			$rows[] = $e;
		}
		mcupr_si_csv_out( 'mcupr-students-not-imported.csv', $rows );
	}
);
