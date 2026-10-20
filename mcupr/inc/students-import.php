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
	$base = array(
		'sid'     => array( 'รหัสนิสิต', 'รหัสนักศึกษา', 'รหัสประจำตัว', 'รหัส', 'studentid', 'studentcode', 'sid', 'id' ),
		'name'    => array( 'ชื่อสกุล', 'ชื่อนามสกุล', 'ชื่อ-สกุล', 'ชื่อ-นามสกุล', 'ชื่อ', 'name', 'fullname', 'studentname' ),
		'name_en' => array( 'ชื่ออังกฤษ', 'ชื่อภาษาอังกฤษ', 'nameen', 'englishname', 'nameenglish' ),
		'level'   => array( 'ระดับ', 'ระดับการศึกษา', 'level', 'degree' ),
		'year'    => array( 'ชั้นปี', 'ปี', 'year', 'yearofstudy' ),
		'entry'   => array( 'ปีที่เข้า', 'ปีที่เข้าศึกษา', 'ปีการศึกษาที่เข้า', 'entry', 'entryyear', 'yearofentry', 'ADMITACADYEAR' ),
		'faculty' => array( 'คณะ', 'faculty', 'school' ),
		'major'   => array( 'สาขา', 'สาขาวิชา', 'หลักสูตร', 'major', 'program', 'programme' ),
		'status'  => array( 'สถานะ', 'สถานภาพ', 'status' ),
		'email'   => array( 'อีเมล', 'อีเมลมหาวิทยาลัย', 'email', 'e-mail', 'mail' ),
		'phone'   => array( 'โทร', 'โทรศัพท์', 'เบอร์โทร', 'เบอร์โทรศัพท์', 'phone', 'tel', 'telephone', 'mobile' ),
		'note'    => array( 'หมายเหตุ', 'note', 'notes', 'remark' ),
		'rfid'    => array( 'รหัสบัตร RFID', 'รหัสบัตรrfid', 'รหัสความปลอดภัย', 'รหัสบัตร', 'rfid', 'rfidcode', 'cardcode', 'securitycode' ),
		'idcard'  => array( 'เลขบัตรประชาชน', 'เลขประจำตัวประชาชน', 'เลขบัตร ปชช', 'เลขบัตรประจำตัวประชาชน', 'idcard', 'nationalid', 'citizenid', 'pid' ),
	);
	$all  = $base;
	foreach ( mcupr_sp_headers() as $k => $names ) { // the columns of the central file; a field that has names of its own too keeps both
		$all[ $k ] = isset( $all[ $k ] ) ? array_merge( $all[ $k ], $names ) : $names;
	}
	return $all;
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
				$out .= html_entity_decode( '&#' . ( 0x0E01 + $b - 0xA1 ) . ';', ENT_QUOTES, 'UTF-8' ); // (mb_chr needs PHP 7.2)
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
	$head = fgetcsv( $fh, 0, $best, '"', mcupr_csv_escape() );
	if ( ! is_array( $head ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'empty', 'empty' );
	}
	$cols    = array();
	$unknown = array();
	$hk      = array_map( 'mcupr_si_key', $head );
	// the university's central file (STUDENTCODE is the student ID, STUDENTID the central one, "ชื่อ" the first name) or this website's own file made from it
	$central = ( in_array( 'studentcode', $hk, true ) && in_array( 'studentid', $hk, true ) ) || ( in_array( mcupr_si_key( mcupr_sp_fields()['central_id'][5] ), $hk, true ) && in_array( mcupr_si_key( mcupr_sp_fields()['first_th'][5] ), $hk, true ) );
	foreach ( $head as $i => $h ) {
		$k     = mcupr_si_key( $h );
		$found = '';
		foreach ( mcupr_si_headers() as $field => $names ) {
			if ( $central && ( ( in_array( $field, array( 'name', 'name_en' ), true ) && in_array( $k, array( 'ชื่อ', 'name' ), true ) ) || ( 'sid' === $field && 'studentid' === $k ) ) ) {
				continue;
			}
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
	if ( ! isset( $cols['sid'] ) || ( ! isset( $cols['name'] ) && ! isset( $cols['first_th'] ) && ! isset( $cols['rfid'] ) && ! isset( $cols['idcard'] ) ) ) {
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return new WP_Error( 'columns', 'the file needs a student ID column and a name column' );
	}
	$rows = array();
	$line = 1;
	while ( false !== ( $r = fgetcsv( $fh, 0, $best, '"', mcupr_csv_escape() ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$line++;
		if ( 1 === count( $r ) && ( null === $r[0] || '' === trim( (string) $r[0] ) ) ) {
			continue; // an empty line
		}
		if ( isset( $cols['rfid'] ) ) { // the card code is turned into a hash at once
			$r[ $cols['rfid'] ] = mcupr_card_import_cell( 'rfid', isset( $r[ $cols['rfid'] ] ) ? $r[ $cols['rfid'] ] : '' );
		}
		foreach ( mcupr_sp_fields() as $sk => $sf ) { // the sensitive details (ID number, blood group, date of birth, religion …) are encrypted at once
			if ( 1 === (int) $sf[2] && isset( $cols[ $sk ] ) ) {
				$clean               = mcupr_sp_clean( $sk, isset( $r[ $cols[ $sk ] ] ) ? $r[ $cols[ $sk ] ] : '' );
				$r[ $cols[ $sk ] ] = '' === $clean ? '' : mcupr_sp_encrypt( $clean );
			}
		}
		$rows[] = array( $line, $r );
		if ( count( $rows ) > MCUPR_SI_MAX ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'big', 'too many rows' );
		}
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return array( 'cols' => $cols, 'rows' => $rows, 'unknown' => $unknown, 'delimiter' => $best, 'central' => $central );
}

/**
 * Check one row of a file against what is in the database.
 * Returns array( 'row' => cleaned values, 'errors' => list of codes, 'warnings' => list of codes ).
 */
/** Which student has this card-code hash (one query for the whole request; the run adds what it writes). $set: remember it; $reset: forget all. */
function mcupr_si_rfid_owner( $hash, $set = 0, $reset = false ) {
	static $map = null;
	if ( $reset ) {
		$map = null;
		return 0;
	}
	if ( null === $map ) {
		$map = mcupr_card_rfid_map();
	}
	if ( $set ) {
		$map[ $hash ] = (int) $set;
	}
	return isset( $map[ $hash ] ) ? (int) $map[ $hash ] : 0;
}

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
	$row          = mcupr_sp_row( $row, $get );
	$cell = $get( 'rfid' );
	if ( null === $cell || '' === $cell ) {
		$row['_rfid'] = null;
	} elseif ( 'bad' === $cell || 0 !== strpos( $cell, 'h:' ) ) {
		$row['_rfid'] = null;
		$warn[]       = 'rfid';
	} else {
		list( $row['_rfid'], $row['_rfid4'] ) = mcupr_card_cell_split( substr( $cell, 2 ) );
	}
	// the national ID number: kept (encrypted) as it is in the file; the hash for signing in only when it is a valid number
	$cell = $get( 'idcard' );
	$row['_idcard'] = null;
	if ( null !== $cell && '' !== $cell ) {
		$plain = mcupr_sp_decrypt( $cell );
		if ( '' === $plain ) {
			$warn[] = 'nosecure';
		} elseif ( mcupr_card_idcard_ok( $plain ) ) {
			$row['_idcard'] = mcupr_card_hash( 'idcard', $plain );
		} else {
			$warn[] = 'idcard';
		}
	}
	if ( ! empty( $row['_rfid'] ) ) {
		$own   = isset( $map[ $row['sid'] ] ) ? (int) $map[ $row['sid'] ] : 0;
		$owner = mcupr_si_rfid_owner( $row['_rfid'] );
		if ( isset( $seen[ 'rfid:' . $row['_rfid'] ] ) && $seen[ 'rfid:' . $row['_rfid'] ] !== $row['sid'] ) {
			$err[] = 'rfid_dup';
		} elseif ( $owner && $owner !== $own ) {
			$err[] = 'rfid_used';
		} else {
			$seen[ 'rfid:' . $row['_rfid'] ] = $row['sid'];
		}
	}
	if ( '' === $row['sid'] ) {
		$err[] = 'sid';
	} elseif ( isset( $seen[ $row['sid'] ] ) ) {
		$err[] = 'dup';
	} else {
		$seen[ $row['sid'] ] = true;
	}
	if ( '' === $row['name'] && ( isset( $cols['name'] ) || ! isset( $map[ $row['sid'] ] ) ) ) { // a file without a name column (card codes only) is fine for students already there
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
/** 'excel' for a real Excel file (.xls / .xlsx), 'pdf' for a PDF, else ''. Such a file is not a CSV and would only look "empty". */
function mcupr_si_binary_kind( $raw ) {
	$h = substr( (string) $raw, 0, 8 );
	if ( "\xD0\xCF\x11\xE0" === substr( $h, 0, 4 ) || 0 === strpos( $h, 'PK' . "\x03\x04" ) ) {
		return 'excel';
	}
	return 0 === strpos( $h, '%PDF' ) ? 'pdf' : '';
}

/** The current academic year (Buddhist era): the new year starts in June. */
function mcupr_si_academic_year() {
	$y = (int) mcupr_ev_fmt( 'Y', time() ) + 543 - ( (int) mcupr_ev_fmt( 'n', time() ) < 6 ? 1 : 0 ); // (wp_date would give the Buddhist year already)
	return (int) apply_filters( 'mcupr_si_academic_year', $y );
}

/** A cell of an .xls as text: whole numbers without ".0", no stray spaces. */
function mcupr_si_xls_cell( $v ) {
	if ( is_float( $v ) || is_int( $v ) || ( is_string( $v ) && is_numeric( $v ) && false !== strpos( $v, '.' ) ) ) {
		$f = (float) $v;
		return floor( $f ) === $f && abs( $f ) < 1e15 ? number_format( $f, 0, '', '' ) : (string) $v;
	}
	return trim( preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', (string) $v ) ) );
}

/**
 * The registrar's report (one block per programme: a line "คณะ : … / วิทยาเขต … / ภาคการศึกษาที่เข้า 1 / 2567 / สาขาวิชา : 6515011 พระพุทธศาสนา / ระดับ : …
 * / ชื่ออาจารย์ที่ปรึกษา : …", then the students: number, student ID, name, …, card code) as the rows of the usual table, or null when the
 * sheet is not such a report. The year of study is worked out from the year of entry.
 */
function mcupr_si_registrar_rows( $rows ) {
	$head = array( 'รหัสนิสิต', 'ชื่อ-สกุล', 'ระดับ', 'ชั้นปี', 'ปีที่เข้า', 'คณะ', 'สาขา', 'สถานะ', 'หมายเหตุ', 'รหัสบัตร RFID' );
	$out  = array( $head );
	$ctx  = null;
	$now  = mcupr_si_academic_year();
	foreach ( $rows as $r ) {
		$c = array_map( 'mcupr_si_xls_cell', array_pad( (array) $r, 15, '' ) );
		$g = null;
		foreach ( array_slice( $c, 0, 9 ) as $cell ) {
			if ( preg_match( '/^คณะ\s*:\s*(.+)$/u', $cell, $m ) ) {
				$g = array( 'fac' => trim( $m[1] ) );
				break;
			}
		}
		if ( null !== $g ) { // a block header
			$all = implode( ' | ', array_slice( $c, 0, 9 ) );
			preg_match( '/วิทยาเขต\s*([^|]*)/u', $all, $m1 );
			preg_match( '/ภาคการศึกษาที่เข้า\s*\d*\s*\/\s*(\d{4})/u', $all, $m2 );
			preg_match( '/สาขาวิชา\s*:\s*\d*\s*([^|]+)/u', $all, $m3 );
			preg_match( '/ระดับ\s*:\s*([^|]+)/u', $all, $m4 );
			preg_match( '/ชื่ออาจารย์ที่ปรึกษา\s*:\s*([^|]*)/u', $all, $m5 );
			$g['campus'] = isset( $m1[1] ) ? trim( $m1[1] ) : '';
			$g['entry']  = isset( $m2[1] ) ? $m2[1] : '';
			$g['major']  = isset( $m3[1] ) ? trim( $m3[1] ) : '';
			$g['level']  = isset( $m4[1] ) ? trim( $m4[1] ) : '';
			$g['adv']    = isset( $m5[1] ) ? trim( $m5[1] ) : '';
			$ctx         = $g;
			continue;
		}
		if ( $ctx && preg_match( '/^\d+$/', $c[9] ) && preg_match( '/^[A-Za-z0-9\-]{4,20}$/', $c[10] ) && '' !== $c[11] ) { // a student of the block
			$entry = (int) $ctx['entry'];
			$note  = array();
			if ( '' !== $ctx['campus'] ) {
				$note[] = 'วิทยาเขต' . $ctx['campus'];
			}
			if ( '' !== $ctx['adv'] ) {
				$note[] = 'อาจารย์ที่ปรึกษา: ' . $ctx['adv'];
			}
			$card  = mcupr_card_digits( $c[13] );
			$out[] = array( $c[10], $c[11], $ctx['level'], $entry ? max( 1, $now - $entry + 1 ) : '', $ctx['entry'], $ctx['fac'], $ctx['major'], 'กำลังศึกษา', implode( ' · ', $note ), $card );
		}
	}
	return $ctx && count( $out ) > 1 ? $out : null;
}

/** The text (CSV) of an .xls file – the registrar's report, or any table with the column names in the first row – or a WP_Error. */
function mcupr_si_xls_to_csv( $bytes ) {
	if ( ! class_exists( '\Shuchkin\SimpleXLS' ) ) {
		require_once get_theme_file_path( 'inc/lib/SimpleXLS.php' );
	}
	$x = \Shuchkin\SimpleXLS::parseData( $bytes );
	if ( ! $x ) {
		return new WP_Error( 'xls', \Shuchkin\SimpleXLS::parseError() );
	}
	$rows = $x->rows( 0 );
	$tab  = mcupr_si_registrar_rows( $rows );
	if ( null === $tab ) {
		$tab = array();
		foreach ( $rows as $r ) {
			$tab[] = array_map( 'mcupr_si_xls_cell', (array) $r );
		}
	}
	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $tab as $r ) {
		fputcsv( $fh, $r, ',', '"', mcupr_csv_escape() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	rewind( $fh );
	$text = (string) stream_get_contents( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $text;
}

/** The files chosen in the form as a list of array( name, tmp, size ) (one box, several files). */
function mcupr_si_posted_files() {
	$out = array();
	if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $out;
	}
	$f = $_FILES['file']; // phpcs:ignore WordPress.Security
	foreach ( (array) $f['tmp_name'] as $i => $tmp ) {
		$err = is_array( $f['error'] ) ? $f['error'][ $i ] : $f['error'];
		$out[] = array( 'name' => is_array( $f['name'] ) ? (string) $f['name'][ $i ] : (string) $f['name'], 'tmp' => $tmp, 'size' => (int) ( is_array( $f['size'] ) ? $f['size'][ $i ] : $f['size'] ), 'ok' => UPLOAD_ERR_OK === (int) $err );
	}
	return array_slice( $out, 0, 12 );
}

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
		'rfid'    => __( 'The card code is not 10 digits, not saved', 'mcupr' ),
		'idcard'  => __( 'The national ID number is kept as it is in the file, but it is not a valid number (13 digits with the right check digit), so it cannot be used to sign in', 'mcupr' ),
		'nosecure' => __( 'The ID number could not be read back (no encryption on this server), not saved', 'mcupr' ),
		'rfid_dup' => __( 'The same card code is on another row of the file', 'mcupr' ),
		'rfid_used' => __( 'This card code already belongs to another student', 'mcupr' ),
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
	}
	// the files hold personal data: no listing and no direct download (written again if someone removed them)
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
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

/** The saved upload, only for the person who uploaded it (or an administrator); anyone else gets null, as for an expired one. */
function mcupr_si_load_own( $token ) {
	$d = mcupr_si_load( $token );
	if ( ! $d ) {
		return null;
	}
	$owner = isset( $d['user'] ) ? (int) $d['user'] : 0;
	return ( $owner && get_current_user_id() === $owner ) || current_user_can( 'manage_options' ) ? $d : null;
}

function mcupr_si_save( $token, $d ) {
	return false !== file_put_contents( mcupr_si_file( $token ), wp_json_encode( $d ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/** Delete saved uploads older than $age seconds (when the import page is opened: a day; the daily cron: 2 hours). */
function mcupr_si_cleanup( $age = DAY_IN_SECONDS ) {
	foreach ( (array) glob( mcupr_si_dir() . '/*.json' ) as $f ) {
		if ( $f && filemtime( $f ) < time() - (int) $age ) {
			wp_delete_file( $f );
		}
	}
}

/** Once a day, even when nobody opens the import page: an upload left half-way (personal data) is not kept for long. */
add_action(
	'mcupr_si_cleanup_cron',
	static function () {
		mcupr_si_cleanup( 2 * HOUR_IN_SECONDS );
	}
);
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'mcupr_si_cleanup_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mcupr_si_cleanup_cron' );
		}
	}
);

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
		$t = array( 'file' => __( 'No file was uploaded (or it is bigger than 5 MB).', 'mcupr' ), 'empty' => __( 'The file is empty.', 'mcupr' ), 'excel' => __( 'An .xlsx file cannot be imported as it is. Open it in Excel and choose Save as → CSV UTF-8 (or Excel 97-2003 .xls), then choose that file here.', 'mcupr' ), 'xls' => __( 'The .xls file could not be read. Open it in Excel and choose Save as → CSV UTF-8, then choose that .csv file here.', 'mcupr' ), 'mixed' => __( 'The files have different columns, so they cannot be imported together. Import them one at a time.', 'mcupr' ), 'pdf' => __( 'This is a PDF, not a spreadsheet. Choose a .csv file.', 'mcupr' ), 'columns' => __( 'The first line must name the columns, and there must be a student ID column (รหัสนิสิต) and a name column (ชื่อ-สกุล).', 'mcupr' ), 'big' => __( 'The file has too many rows (20,000 at most). Split it in parts.', 'mcupr' ), 'gone' => __( 'That import has expired. Upload the file again.', 'mcupr' ) );
		if ( isset( $t[ $e ] ) ) { // an unknown code shows nothing: no sentence taken from the address
			echo '<div class="notice notice-error"><p>' . esc_html( $t[ $e ] ) . '</p>';
			$dg = get_transient( 'mcupr_si_diag_' . get_current_user_id() );
			if ( is_array( $dg ) && in_array( $e, array( 'empty', 'columns', 'xls' ), true ) ) {
				/* translators: 1: size in bytes, 2: PHP version, 3: the first line of the file */
				echo '<p class="description">' . esc_html( sprintf( __( 'What the website saw: %1$d bytes, PHP %2$s. First line: “%3$s”', 'mcupr' ), $dg['size'], $dg['php'], $dg['head'] ) ) . '</p>';
			}
			echo '</div>';
		}
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
	echo '<p style="max-width:62rem"><strong>' . esc_html__( 'The registrar’s .xls report can be chosen as it is', 'mcupr' ) . '</strong> – ' . esc_html__( 'one file or several at once (the reports of different years of entry). Each programme block gives the faculty, programme, level, year of entry, campus and adviser; the year of study is worked out from the year of entry, and the card code (RFID) is read when the report has one.', 'mcupr' ) . '</p>';
	echo '<div class="sc"><div><b>' . (int) $counts['all'] . '</b>' . esc_html__( 'students in the database', 'mcupr' ) . '</div><div><b>' . (int) $counts['studying'] . '</b>' . esc_html__( 'studying', 'mcupr' ) . '</div><div><b>' . (int) $counts['accounts'] . '</b>' . esc_html__( 'with a login account', 'mcupr' ) . '</div></div>';
	echo '<div class="card"><h2>' . esc_html__( '1. Choose the file', 'mcupr' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mcupr_si_upload">';
	wp_nonce_field( 'mcupr_si_upload', 'mcupr_si_nonce' );
	echo '<p><label for="si-file"><strong>' . esc_html__( 'File (CSV, or the registrar\'s .xls report)', 'mcupr' ) . '</strong></label><br><input id="si-file" type="file" name="file[]" multiple accept=".csv,.txt,.tsv,.xls,text/csv,text/plain,application/vnd.ms-excel" required></p><fieldset><legend><strong>' . esc_html__( 'What to do with students already in the database (same student ID)', 'mcupr' ) . '</strong></legend>';
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
		if ( ! mcupr_check_request( 'mcupr_si_nonce', 'mcupr_si_upload', MCUPR_STU_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$text = '';
		if ( ! empty( $_POST['sample'] ) ) {
			$f    = get_theme_file_path( 'starter/mcupr-students-sample.csv' );
			$text = is_readable( $f ) ? (string) file_get_contents( $f ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		} else {
			$parts = array();
			$total = 0;
			foreach ( mcupr_si_posted_files() as $pf ) {
				if ( ! $pf['ok'] || $pf['size'] < 1 || $pf['size'] > 5 * MB_IN_BYTES || ! is_uploaded_file( $pf['tmp'] ) ) {
					continue;
				}
				$total += $pf['size'];
				$raw    = (string) file_get_contents( $pf['tmp'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( 0 === strpos( $raw, "\xD0\xCF\x11\xE0" ) ) { // an .xls: the registrar's report or a table
					$raw = mcupr_si_xls_to_csv( $raw );
					if ( is_wp_error( $raw ) ) {
						set_transient( 'mcupr_si_diag_' . get_current_user_id(), array( 'size' => $pf['size'], 'php' => PHP_VERSION, 'head' => $pf['name'], 'why' => (string) $raw->get_error_message() ), 10 * MINUTE_IN_SECONDS );
						wp_safe_redirect( mcupr_si_url( array( 'err' => 'xls' ) ) );
						exit;
					}
				}
				$parts[] = array( $pf['name'], $raw );
			}
			if ( count( $parts ) > 1 ) { // several files make one import: they must have the same columns
				$text = '';
				$h0   = null;
				foreach ( $parts as $i => $pt ) {
					$t    = str_replace( array( "\r\n", "\r" ), "\n", mcupr_si_utf8( $pt[1] ) );
					$nl   = strpos( $t, "\n" );
					$head = false === $nl ? $t : substr( $t, 0, $nl );
					if ( 0 === $i ) {
						$h0 = $head;
					} elseif ( $head !== $h0 ) {
						wp_safe_redirect( mcupr_si_url( array( 'err' => 'mixed' ) ) );
						exit;
					} else {
						$t = false === $nl ? '' : substr( $t, $nl + 1 );
					}
					$text .= rtrim( $t, "\n" ) . "\n";
				}
			} elseif ( $parts ) {
				$text = $parts[0][1];
			}
		}
		if ( '' === $text ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => 'file' ) ) );
			exit;
		}
		$kind = mcupr_si_binary_kind( $text );
		if ( '' !== $kind ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => $kind ) ) );
			exit;
		}
		$p = mcupr_si_parse( $text );
		if ( is_wp_error( $p ) ) {
			// what was seen, so that "the file is empty" can be understood: size, PHP, the first line (the column names only)
			$first = (string) strtok( str_replace( array( "\r\n", "\r" ), "\n", mcupr_si_utf8( $text ) ), "\n" );
			set_transient( 'mcupr_si_diag_' . get_current_user_id(), array( 'size' => strlen( $text ), 'php' => PHP_VERSION, 'head' => mb_substr( sanitize_text_field( $first ), 0, 200 ), 'why' => (string) $p->get_error_message() ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( mcupr_si_url( array( 'err' => $p->get_error_code() ) ) );
			exit;
		}
		$mode  = isset( $_POST['mode'] ) && in_array( $_POST['mode'], array( 'add_update', 'add_only', 'update_only' ), true ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'add_update';
		$token = bin2hex( random_bytes( 16 ) );
		$ok    = mcupr_si_save( $token, array( 'cols' => $p['cols'], 'rows' => $p['rows'], 'unknown' => $p['unknown'], 'central' => ! empty( $p['central'] ), 'mode' => $mode, 'accounts' => ! empty( $_POST['accounts'] ), 'user' => get_current_user_id(), 'sample' => ! empty( $_POST['sample'] ) ) );
		wp_safe_redirect( $ok ? mcupr_si_url( array( 'step' => 'preview', 't' => $token ) ) : mcupr_si_url( array( 'err' => 'file' ) ) );
		exit;
	}
);

function mcupr_si_preview( $token ) {
	$d = mcupr_si_load_own( $token );
	if ( ! $d ) {
		echo '<p><a class="button" href="' . esc_url( mcupr_si_url( array( 'err' => 'gone' ) ) ) . '">' . esc_html__( 'Start again', 'mcupr' ) . '</a></p>';
		return;
	}
	$map  = mcupr_student_map();
	$seen = array();
	$n    = array( 'new' => 0, 'upd' => 0, 'err' => 0, 'warn' => 0 );
	$x    = array( 'central' => 0, 'overdue' => 0, 'secret' => 0, 'unmapped' => array(), 'idc' => 0 );
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
		if ( ! empty( $c['row']['_central'] ) ) {
			++$x['central'];
			$x['overdue'] += (int) $c['row']['_overdue'];
			$x['secret']  += empty( $c['row']['_s'] ) ? 0 : 1;
			if ( ! empty( $c['row']['_p']['program_id'] ) && '' === mcupr_sp_code_name( 'program', $c['row']['_p']['program_id'] ) ) {
				$x['unmapped'][ $c['row']['_p']['program_id'] ] = ( isset( $x['unmapped'][ $c['row']['_p']['program_id'] ] ) ? $x['unmapped'][ $c['row']['_p']['program_id'] ] : 0 ) + 1;
			}
		}
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
	if ( $x['central'] ) {
		/* translators: 1: students from the central file, 2: with sensitive details (kept encrypted), 3: beyond the usual length */
		echo '<p>' . esc_html( sprintf( __( 'This is the central file: %1$d students; %2$d with sensitive details (kept encrypted); %3$d beyond the usual length of the programme (no year of study, a label instead).', 'mcupr' ), $x['central'], $x['secret'], $x['overdue'] ) ) . '</p>';
		if ( ! mcupr_sp_can_encrypt() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'This server cannot encrypt (the PHP openssl extension is missing), so the sensitive details will NOT be saved.', 'mcupr' ) . '</p></div>';
		}
		if ( $x['unmapped'] ) {
			arsort( $x['unmapped'] );
			$list = array();
			foreach ( $x['unmapped'] as $code => $cnt ) {
				$list[] = $code . ' (' . $cnt . ')';
			}
			echo '<p class="description">' . esc_html__( 'Programme codes with no name yet (the programme is left empty until you name them under “Faculty and programme codes”):', 'mcupr' ) . ' ' . esc_html( implode( ', ', array_slice( $list, 0, 20 ) ) ) . '</p>';
		}
	}
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
	if ( ! empty( $d['central'] ) ) {
		echo '<p><label><input type="checkbox" name="mark_missing" value="1" checked> ' . esc_html__( 'This is the complete list from the central system: give the label “Not in the central file” to students who are not in it (nobody is deleted or changed otherwise)', 'mcupr' ) . '</label></p>';
	}
	echo '<p><label><input type="checkbox" name="accounts" value="1"' . checked( ! empty( $d['accounts'] ), true, false ) . '> ' . esc_html__( 'Also make a login account for each student who has an e-mail (no e-mail is sent yet)', 'mcupr' ) . '</label></p>';
	echo '<p><button class="button button-primary"' . ( $n['new'] + $n['upd'] ? '' : ' disabled' ) . '>' . esc_html__( 'Import now', 'mcupr' ) . '</button> <a class="button" href="' . esc_url( mcupr_si_url() ) . '">' . esc_html__( 'Cancel', 'mcupr' ) . '</a></p></form></div>';
}

/** Begin the run: the choice about accounts is saved, the state is empty. */
add_action(
	'admin_post_mcupr_si_start',
	static function () {
		$token = isset( $_POST['t'] ) ? sanitize_key( wp_unslash( $_POST['t'] ) ) : '';
		if ( ! current_user_can( MCUPR_STU_CAP ) || ! $token || ! mcupr_check_request( 'mcupr_si_nonce', 'mcupr_si_start' . $token ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$d = mcupr_si_load_own( $token );
		if ( ! $d ) {
			wp_safe_redirect( mcupr_si_url( array( 'err' => 'gone' ) ) );
			exit;
		}
		$d['accounts'] = ! empty( $_POST['accounts'] );
		$d['state']    = array( 'offset' => 0, 'added' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'accounts' => 0, 'errors' => array(), 'warn' => 0, 'present' => array() );
		$d['mark_missing'] = ! empty( $d['central'] ) && ! empty( $_POST['mark_missing'] );
		mcupr_si_save( $token, $d );
		wp_safe_redirect( mcupr_si_url( array( 'step' => 'run', 't' => $token ) ) );
		exit;
	}
);

/** One chunk of the import, then the page continues by itself until the end. */
function mcupr_si_run( $token ) {
	$d = mcupr_si_load_own( $token );
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
			if ( isset( $map[ $c['row']['sid'] ] ) ) {
				$s['present'][] = (int) $map[ $c['row']['sid'] ]; // it is in the file, only this row was not good
			}
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
		if ( $id ) {
			$s['present'][] = (int) $id;
		}
		if ( $id && in_array( $res, array( 'added', 'updated', 'unchanged' ), true ) ) {
			// a card code or ID number already set on the record is kept: the file only fills what is empty (the central copy of the ID number stays in step with its hash)
			if ( '' !== (string) get_post_meta( $id, MCUPR_CARD_IDC, true ) ) {
				unset( $c['row']['_s']['idcard'] );
			}
			if ( mcupr_sp_store( $id, $c['row'] ) && 'unchanged' === $res ) {
				$res = 'updated'; // only the details from the central file were new
			}
			foreach ( array( '_rfid' => MCUPR_CARD_RFID, '_idcard' => MCUPR_CARD_IDC ) as $rk => $mk ) {
				if ( ! empty( $c['row'][ $rk ] ) && '' === (string) get_post_meta( $id, $mk, true ) ) {
					update_post_meta( $id, $mk, $c['row'][ $rk ] );
					if ( '_rfid' === $rk ) {
						if ( ! empty( $c['row']['_rfid4'] ) ) {
							update_post_meta( $id, MCUPR_CARD_RFID4, $c['row']['_rfid4'] );
						}
						mcupr_si_rfid_owner( $c['row'][ $rk ], $id );
					}
					$res = 'unchanged' === $res ? 'updated' : $res;
				}
			}
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
	// finished: whoever is not in a complete central file gets the label; the choices of the students-only content are counted again
	$s['missing'] = 0;
	if ( ! empty( $d['mark_missing'] ) ) {
		$s['missing'] = mcupr_sp_mark_missing( $s['present'] );
	}
	unset( $s['present'] );
	mcupr_aud_reset();
	// keep the report for the next page, drop the upload
	$by  = wp_get_current_user();
	$log = get_option( 'mcupr_student_imports', array() );
	$log[] = array( 't' => time(), 'by' => $by->user_login, 'added' => $s['added'], 'updated' => $s['updated'], 'errors' => count( $s['errors'] ), 'rows' => $total );
	update_option( 'mcupr_student_imports', array_slice( $log, -20 ), false );
	mcupr_audit( 'import', array( 'n' => $total, 'note' => sprintf( 'added %d, updated %d, not imported %d', $s['added'], $s['updated'], count( $s['errors'] ) ) . ( ! empty( $d['sample'] ) ? ' (sample file)' : '' ) ) );
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
	echo '<div class="card"><h2>' . esc_html__( 'Done', 'mcupr' ) . '</h2><div class="sc"><div><b>' . (int) $s['added'] . '</b>' . esc_html__( 'added', 'mcupr' ) . '</div><div><b>' . (int) $s['updated'] . '</b>' . esc_html__( 'updated', 'mcupr' ) . '</div><div><b>' . (int) $s['unchanged'] . '</b>' . esc_html__( 'unchanged', 'mcupr' ) . '</div><div><b>' . (int) $s['skipped'] . '</b>' . esc_html__( 'skipped by the mode', 'mcupr' ) . '</div><div><b>' . (int) $s['accounts'] . '</b>' . esc_html__( 'login accounts made', 'mcupr' ) . '</div>' . ( ! empty( $s['missing'] ) ? '<div><b>' . (int) $s['missing'] . '</b>' . esc_html__( 'labelled “Not in the central file”', 'mcupr' ) . '</div>' : '' ) . '<div><b style="color:' . ( $s['errors'] ? '#b32d2e' : 'inherit' ) . '">' . count( $s['errors'] ) . '</b>' . esc_html__( 'not imported', 'mcupr' ) . '</div></div>';
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

/** A cell safe to open in a spreadsheet (kept for older callers: see mcupr_csv_safe()). */
function mcupr_si_cell( $v ) {
	return mcupr_csv_safe( $v );
}

function mcupr_si_csv_out( $filename, $rows ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo "\xEF\xBB\xBF"; // so that Excel reads Thai
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $rows as $r ) {
		fputcsv( $out, array_map( 'mcupr_csv_safe', $r ), ',', '"', mcupr_csv_escape() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/** The columns in the order of the template and the export. */
function mcupr_si_columns() {
	return array( 'sid' => 'รหัสนิสิต', 'name' => 'ชื่อ-สกุล', 'name_en' => 'ชื่ออังกฤษ', 'level' => 'ระดับ', 'year' => 'ชั้นปี', 'entry' => 'ปีที่เข้า', 'faculty' => 'คณะ', 'major' => 'สาขา', 'status' => 'สถานะ', 'email' => 'อีเมล', 'phone' => 'โทร', 'note' => 'หมายเหตุ' );
}

/** Columns of the template (the card columns are for import only: the export never has them, they are not kept). */
function mcupr_si_template_columns() {
	return array_merge( array_values( mcupr_si_columns() ), array( 'รหัสบัตร RFID', 'เลขบัตรประชาชน' ) );
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
				mcupr_si_template_columns(),
				array( '6600000001', 'นายตัวอย่าง ใจดี', 'Tuayang Jaidee', 'ตรี', '2', '2567', 'คณะพุทธศาสตร์', 'พุทธศาสตร์', 'กำลังศึกษา', 'tuayang.j@example.org', '081-000-0001', '', '1234567890', '' ),
				array( '6600000002', 'นางสาวสมหญิง รักเรียน', '', 'โท', '1', '2568', 'คณะมนุษยศาสตร์', 'ภาษาอังกฤษ', 'กำลังศึกษา', 'somying.r@example.org', '', 'ตัวอย่างแถวที่สอง', '', '' ),
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
		mcupr_audit( 'export', array( 'n' => count( $ids ) ) );
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
		mcupr_audit( 'errors', array( 'n' => count( $rows ) - 1 ) );
		mcupr_si_csv_out( 'mcupr-students-not-imported.csv', $rows );
	}
);
