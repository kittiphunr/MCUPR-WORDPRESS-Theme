<?php
/**
 * Staff hub: import people from an Excel (.xlsx) or CSV file, and export / template download.
 *
 * Flow (all in Staff and executives → Staff hub): download the template (or export the current people) → fill it in
 * → upload → a PREVIEW shows what is new, what updates an existing person and what is skipped (with the reasons)
 * → confirm. Nothing is written before the confirmation. Columns can be in Thai or English; the order does not matter.
 *
 * Matching: by email when the file has one, else by the exact name. Only cells that are filled overwrite – an empty
 * cell never erases what is already saved. Units are matched by name against the Administration chart.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCUPR_IMPORT_MAX_ROWS', 1000 );
define( 'MCUPR_IMPORT_MAX_BYTES', 2 * 1024 * 1024 );

/** Canonical column => array( Thai heading used in the template, accepted headings ). */
function mcupr_import_columns() {
	return array(
		'name'      => array( 'ชื่อ-สกุล', array( 'ชื่อ-สกุล', 'ชื่อสกุล', 'ชื่อ', 'name', 'fullname' ) ),
		'name_en'   => array( 'ชื่ออังกฤษ', array( 'ชื่ออังกฤษ', 'ชื่อภาษาอังกฤษ', 'nameen', 'englishname', 'name(en)' ) ),
		'role'      => array( 'ตำแหน่ง', array( 'ตำแหน่ง', 'ตำแหน่งบริหาร', 'position', 'role' ) ),
		'role_en'   => array( 'ตำแหน่งอังกฤษ', array( 'ตำแหน่งอังกฤษ', 'ตำแหน่งภาษาอังกฤษ', 'positionen', 'roleen', 'position(en)' ) ),
		'rank'      => array( 'ตำแหน่งทางวิชาการ', array( 'ตำแหน่งทางวิชาการ', 'academicrank', 'rank' ) ),
		'type'      => array( 'ประเภทบุคลากร', array( 'ประเภทบุคลากร', 'ประเภท', 'stafftype', 'type' ) ),
		'unit'      => array( 'หน่วยงาน', array( 'หน่วยงาน', 'สังกัด', 'unit', 'department' ) ),
		'head'      => array( 'หัวหน้าหน่วยงาน', array( 'หัวหน้าหน่วยงาน', 'หัวหน้า', 'head', 'headofunit' ) ),
		'group'     => array( 'กลุ่ม', array( 'กลุ่ม', 'กลุ่มบุคลากร', 'group' ) ),
		'email'     => array( 'อีเมล', array( 'อีเมล', 'อีเมลล์', 'email', 'e-mail' ) ),
		'tel'       => array( 'โทร', array( 'โทร', 'โทรศัพท์', 'เบอร์โทร', 'tel', 'phone', 'telephone' ) ),
		'office'    => array( 'ที่ทำงาน', array( 'ที่ทำงาน', 'ห้อง', 'ห้องทำงาน', 'office', 'room' ) ),
		'scholar'   => array( 'ลิงก์งานวิจัย', array( 'ลิงก์งานวิจัย', 'ลิงก์', 'scholar', 'orcid', 'researchlink', 'link' ) ),
		'expertise' => array( 'ความเชี่ยวชาญ', array( 'ความเชี่ยวชาญ', 'expertise' ) ),
		'honors'    => array( 'เครื่องราชฯ', array( 'เครื่องราชฯ', 'เครื่องราช', 'เครื่องราชอิสริยาภรณ์', 'honors', 'decorations' ) ),
		'hide'      => array( 'ซ่อน', array( 'ซ่อน', 'ซ่อนข้อมูล', 'hide', 'hidden' ) ),
		'noprofile' => array( 'ไม่มีหน้าโปรไฟล์', array( 'ไม่มีหน้าโปรไฟล์', 'ไม่มีโปรไฟล์', 'noprofile' ) ),
		'order'     => array( 'ลำดับ', array( 'ลำดับ', 'order' ) ),
		'photo'     => array( 'ภาพ (URL)', array( 'ภาพ(url)', 'ภาพ', 'รูป', 'photo', 'image', 'photourl' ) ),
		'bio'       => array( 'ประวัติโดยสังเขป', array( 'ประวัติโดยสังเขป', 'ประวัติ', 'bio', 'biography' ) ),
	);
}

function mcupr_import_norm_header( $h ) {
	$h = (string) $h;
	$h = preg_replace( '/^\xEF\xBB\xBF/', '', $h );
	return strtolower( preg_replace( '/[\s_\-\/]+/u', '', trim( $h ) ) );
}

/** Map the heading row to canonical keys. */
function mcupr_import_map_headers( $head ) {
	$alias = array();
	foreach ( mcupr_import_columns() as $k => $c ) {
		foreach ( $c[1] as $a ) {
			$alias[ mcupr_import_norm_header( $a ) ] = $k;
		}
	}
	$map = array();
	foreach ( $head as $i => $h ) {
		$n = mcupr_import_norm_header( $h );
		if ( isset( $alias[ $n ] ) && ! in_array( $alias[ $n ], $map, true ) ) {
			$map[ $i ] = $alias[ $n ];
		}
	}
	return $map;
}

/* ------------------------------------------------------------------ reading files */

/** CSV text → array of rows (handles BOM, UTF-8 / Windows-874 text, comma / semicolon / tab). */
function mcupr_import_read_csv( $path ) {
	$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = function_exists( 'iconv' ) ? (string) iconv( 'CP874', 'UTF-8//IGNORE', $raw ) : mb_convert_encoding( $raw, 'UTF-8', 'Windows-874' );
	}
	$first = strtok( $raw, "\n" );
	$delim = ',';
	$best  = 0;
	foreach ( array( ',', ';', "\t" ) as $d ) {
		$n = substr_count( (string) $first, $d );
		if ( $n > $best ) {
			$best  = $n;
			$delim = $d;
		}
	}
	$fh = fopen( 'php://memory', 'r+' );
	fwrite( $fh, $raw );
	rewind( $fh );
	$rows = array();
	while ( false !== ( $r = fgetcsv( $fh, 0, $delim, '"', mcupr_csv_escape() ) ) ) {
		if ( 1 === count( $r ) && null === $r[0] ) {
			continue;
		}
		$rows[] = array_map( 'mcupr_csv_unsafe', array_map( 'trim', array_map( 'strval', $r ) ) );
		if ( count( $rows ) > MCUPR_IMPORT_MAX_ROWS + 1 ) {
			break;
		}
	}
	fclose( $fh );
	return $rows;
}

/** .xlsx (first sheet) → array of rows. Needs PHP's zip extension. */
function mcupr_import_read_xlsx( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'nozip', __( 'This server cannot read Excel files. Save the sheet as CSV (UTF-8) and upload that instead.', 'mcupr' ) );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'badzip', __( 'The file is not a valid .xlsx workbook.', 'mcupr' ) );
	}
	$read = static function ( $name ) use ( $zip ) {
		$st = $zip->statName( $name );
		if ( ! $st || $st['size'] > 20 * 1024 * 1024 ) {
			return '';
		}
		return (string) $zip->getFromName( $name );
	};
	$parse = static function ( $xml ) {
		if ( '' === $xml ) {
			return null;
		}
		if ( function_exists( 'libxml_disable_entity_loader' ) && PHP_VERSION_ID < 80000 ) {
			libxml_disable_entity_loader( true ); // phpcs:ignore
		}
		$prev = libxml_use_internal_errors( true );
		$x    = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $prev );
		return $x ? $x : null;
	};
	$shared = array();
	$ss     = $parse( $read( 'xl/sharedStrings.xml' ) );
	if ( $ss ) {
		foreach ( $ss->si as $si ) {
			$t = '';
			if ( isset( $si->t ) ) {
				$t = (string) $si->t;
			} else {
				foreach ( $si->r as $r ) {
					$t .= (string) $r->t;
				}
			}
			$shared[] = $t;
		}
	}
	$sheet_name = 'xl/worksheets/sheet1.xml';
	if ( false === $zip->locateName( $sheet_name ) ) {
		$sheet_name = '';
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$n = $zip->getNameIndex( $i );
			if ( 0 === strpos( $n, 'xl/worksheets/' ) && '.xml' === substr( $n, -4 ) ) {
				$sheet_name = $n;
				break;
			}
		}
	}
	$sh = $parse( $read( $sheet_name ) );
	$zip->close();
	if ( ! $sh ) {
		return new WP_Error( 'nosheet', __( 'No worksheet was found in the file.', 'mcupr' ) );
	}
	$col = static function ( $ref ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( (string) $ref ) );
		$n       = 0;
		for ( $i = 0, $l = strlen( $letters ); $i < $l; $i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $n - 1 );
	};
	$rows = array();
	foreach ( $sh->sheetData->row as $row ) {
		$r = array();
		foreach ( $row->c as $c ) {
			$i = $col( $c['r'] );
			$t = (string) $c['t'];
			if ( 's' === $t ) {
				$v = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : '';
			} elseif ( 'inlineStr' === $t ) {
				$v = isset( $c->is->t ) ? (string) $c->is->t : '';
			} else {
				$v = (string) $c->v;
				if ( is_numeric( $v ) && false !== strpos( $v, '.' ) ) {
					$v = rtrim( rtrim( $v, '0' ), '.' );
				}
			}
			$r[ $i ] = trim( $v );
		}
		if ( $r ) {
			ksort( $r );
			$max  = max( array_keys( $r ) );
			$full = array();
			for ( $k = 0; $k <= $max; $k++ ) {
				$full[] = isset( $r[ $k ] ) ? $r[ $k ] : '';
			}
			$rows[] = $full;
		}
		if ( count( $rows ) > MCUPR_IMPORT_MAX_ROWS + 1 ) {
			break;
		}
	}
	return $rows;
}

/** Rows (first = headings) → list of canonical-key arrays. */
function mcupr_import_rows_to_records( $rows ) {
	if ( count( $rows ) < 2 ) {
		return new WP_Error( 'empty', __( 'The file has no data rows.', 'mcupr' ) );
	}
	$map = mcupr_import_map_headers( $rows[0] );
	if ( ! in_array( 'name', $map, true ) ) {
		return new WP_Error( 'noname', __( 'The first row must contain the headings, including “ชื่อ-สกุล” (name). Download the template to see them.', 'mcupr' ) );
	}
	$out = array();
	foreach ( array_slice( $rows, 1, MCUPR_IMPORT_MAX_ROWS ) as $i => $r ) {
		$rec = array( '_row' => $i + 2 );
		foreach ( $map as $ci => $key ) {
			$rec[ $key ] = isset( $r[ $ci ] ) ? trim( (string) $r[ $ci ] ) : '';
		}
		if ( '' === implode( '', array_diff_key( $rec, array( '_row' => 1 ) ) ) ) {
			continue; // an empty line
		}
		$out[] = $rec;
	}
	return $out;
}

/* ------------------------------------------------------------------ interpreting cells */

function mcupr_import_bool( $v ) {
	return in_array( strtolower( trim( (string) $v ) ), array( '1', 'y', 'yes', 'true', 'x', "\u{2713}", "\u{2714}", 'ใช่', 'จริง', 'หัวหน้า', 'ซ่อน' ), true );
}

function mcupr_import_hide_keys( $v ) {
	$words = array(
		'email' => 'email', 'อีเมล' => 'email', 'tel' => 'tel', 'phone' => 'tel', 'โทร' => 'tel', 'โทรศัพท์' => 'tel',
		'office' => 'office', 'ที่ทำงาน' => 'office', 'ห้อง' => 'office', 'links' => 'links', 'link' => 'links', 'ลิงก์' => 'links',
		'bio' => 'bio', 'ประวัติ' => 'bio', 'edu' => 'edu', 'education' => 'edu', 'การศึกษา' => 'edu',
		'works' => 'works', 'ผลงาน' => 'works', 'honors' => 'honors', 'เครื่องราชฯ' => 'honors', 'เครื่องราช' => 'honors', 'เครื่องราชอิสริยาภรณ์' => 'honors',
	);
	$out = array();
	foreach ( preg_split( '/[,;\n|]+/u', (string) $v ) as $w ) {
		$w = strtolower( trim( $w ) );
		if ( isset( $words[ $w ] ) ) {
			$out[ $words[ $w ] ] = true;
		}
	}
	return array_keys( $out );
}

/** "ท.ช. 2562; ต.ม. 2557 ราชกิจจาฯ …" → array( array( key, year, gazette ), … ) + unknown tokens. */
function mcupr_import_parse_honors( $v ) {
	$cat   = mcupr_honors();
	$byid  = array();
	$clean = static function ( $s ) {
		return strtolower( preg_replace( '/[\s\.\-]+/u', '', (string) $s ) );
	};
	foreach ( $cat as $k => $h ) {
		$byid[ $clean( $h['code'] ) ] = $k;
		$byid[ $clean( $k ) ]         = $k;
		$byid[ $clean( $h['th'] ) ]   = $k;
	}
	$rows    = array();
	$unknown = array();
	foreach ( preg_split( '/[;\n|]+/u', (string) $v ) as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		$year = '';
		$rest = '';
		if ( preg_match( '/^(.*?)[\s:：]+(\d{4})(?:\s+(.*))?$/u', $part, $m ) ) {
			$name = $m[1];
			$year = $m[2];
			$rest = isset( $m[3] ) ? $m[3] : '';
		} else {
			$name = $part;
		}
		$id = $clean( $name );
		if ( isset( $byid[ $id ] ) ) {
			$rows[] = array( 'key' => $byid[ $id ], 'year' => $year ? (int) $year : '', 'gazette' => sanitize_text_field( $rest ), 'note' => '' );
		} else {
			$unknown[] = $part;
		}
	}
	return array( $rows, $unknown );
}

/** Find a unit of the Administration chart by its name (any language title). */
function mcupr_import_find_unit( $name ) {
	static $units = null;
	if ( null === $units ) {
		$units = get_posts( array( 'post_type' => 'mcupr_unit', 'post_status' => 'publish', 'posts_per_page' => 500, 'no_found_rows' => true ) );
	}
	$n = mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $name ) ) );
	foreach ( $units as $u ) {
		if ( mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $u->post_title ) ) ) === $n ) {
			return (int) $u->ID;
		}
		foreach ( array_keys( mcupr_languages() ) as $code ) {
			$t = (string) get_post_meta( $u->ID, "_mcupr_{$code}_title", true );
			if ( '' !== $t && mb_strtolower( trim( $t ) ) === $n ) {
				return (int) $u->ID;
			}
		}
	}
	return 0;
}

/** An existing person: by email, else by exact name. */
function mcupr_import_find_person( $name, $email ) {
	if ( '' !== $email ) {
		$q = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'any', 'posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mcupr_person_email', 'value' => $email ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $q ) {
			return (int) $q[0];
		}
	}
	$q = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'any', 'posts_per_page' => 50, 'no_found_rows' => true, 'title' => $name ) );
	foreach ( $q as $p ) {
		if ( $p->post_title === $name ) {
			return (int) $p->ID;
		}
	}
	return 0;
}

/* ------------------------------------------------------------------ plan (preview) and apply */

/** Check every record and say what would happen. Nothing is written. */
function mcupr_import_plan( $records ) {
	$plan  = array();
	$seen  = array();
	foreach ( $records as $r ) {
		$e = array();
		$w = array();
		$name = sanitize_text_field( isset( $r['name'] ) ? $r['name'] : '' );
		if ( '' === $name ) {
			$plan[] = array( 'row' => $r['_row'], 'action' => 'skip', 'name' => '', 'err' => array( __( 'No name.', 'mcupr' ) ), 'warn' => array(), 'data' => array(), 'id' => 0 );
			continue;
		}
		$email = isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
		if ( '' !== ( isset( $r['email'] ) ? $r['email'] : '' ) && '' === $email ) {
			$w[] = __( 'The email address is not valid and was ignored.', 'mcupr' );
		}
		$unit = 0;
		if ( ! empty( $r['unit'] ) ) {
			$unit = mcupr_import_find_unit( $r['unit'] );
			if ( ! $unit ) {
				$w[] = sprintf( /* translators: %s: unit name */ __( 'Unit “%s” is not in the Administration chart; the name is kept as plain text.', 'mcupr' ), $r['unit'] );
			}
		}
		$honors = array();
		if ( ! empty( $r['honors'] ) ) {
			list( $honors, $unk ) = mcupr_import_parse_honors( $r['honors'] );
			foreach ( $unk as $u ) {
				$w[] = sprintf( /* translators: %s: text of the cell */ __( 'Decoration “%s” is not in the catalogue and was ignored.', 'mcupr' ), $u );
			}
		}
		$scholar = '';
		if ( ! empty( $r['scholar'] ) ) {
			$scholar = esc_url_raw( $r['scholar'] );
			if ( '' === $scholar ) {
				$w[] = __( 'The research link is not a valid web address and was ignored.', 'mcupr' );
			}
		}
		$photo = '';
		if ( ! empty( $r['photo'] ) ) {
			$photo = esc_url_raw( $r['photo'], array( 'http', 'https' ) );
			if ( '' === $photo ) {
				$w[] = __( 'The photo address is not valid and was ignored.', 'mcupr' );
			}
		}
		$key = '' !== $email ? 'e:' . strtolower( $email ) : 'n:' . $name;
		if ( isset( $seen[ $key ] ) ) {
			$w[] = sprintf( /* translators: %d: row number */ __( 'Same person as row %d: both rows are applied in order.', 'mcupr' ), $seen[ $key ] );
		}
		$seen[ $key ] = $r['_row'];
		$id   = mcupr_import_find_person( $name, $email );
		$data = array(
			'name'      => $name,
			'name_en'   => sanitize_text_field( isset( $r['name_en'] ) ? $r['name_en'] : '' ),
			'role'      => sanitize_text_field( isset( $r['role'] ) ? $r['role'] : '' ),
			'role_en'   => sanitize_text_field( isset( $r['role_en'] ) ? $r['role_en'] : '' ),
			'rank'      => sanitize_text_field( isset( $r['rank'] ) ? $r['rank'] : '' ),
			'type'      => sanitize_text_field( isset( $r['type'] ) ? $r['type'] : '' ),
			'unit'      => $unit,
			'unit_text' => $unit ? '' : sanitize_text_field( isset( $r['unit'] ) ? $r['unit'] : '' ),
			'head'      => isset( $r['head'] ) && '' !== $r['head'] ? ( mcupr_import_bool( $r['head'] ) ? 1 : 0 ) : null,
			'groups'    => array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/[,;|]+/u', isset( $r['group'] ) ? $r['group'] : '' ) ) ) ),
			'email'     => $email,
			'tel'       => sanitize_text_field( isset( $r['tel'] ) ? $r['tel'] : '' ),
			'office'    => sanitize_text_field( isset( $r['office'] ) ? $r['office'] : '' ),
			'scholar'   => $scholar,
			'expertise' => sanitize_text_field( isset( $r['expertise'] ) ? $r['expertise'] : '' ),
			'honors'    => $honors,
			'hide'      => isset( $r['hide'] ) && '' !== $r['hide'] ? mcupr_import_hide_keys( $r['hide'] ) : null,
			'noprofile' => isset( $r['noprofile'] ) && '' !== $r['noprofile'] ? ( mcupr_import_bool( $r['noprofile'] ) ? 1 : 0 ) : null,
			'order'     => isset( $r['order'] ) && is_numeric( $r['order'] ) ? (int) $r['order'] : null,
			'photo'     => $photo,
			'bio'       => isset( $r['bio'] ) ? wp_kses_post( $r['bio'] ) : '',
		);
		$plan[] = array( 'row' => $r['_row'], 'action' => $id ? 'update' : 'new', 'name' => $name, 'err' => $e, 'warn' => $w, 'data' => $data, 'id' => $id );
	}
	return $plan;
}

/** Write the plan. $opts: update (bool, default true), status (publish|draft), photos (bool). */
function mcupr_import_apply( $plan, $opts = array() ) {
	$opts   = array_merge( array( 'update' => true, 'status' => 'publish', 'photos' => false ), $opts );
	$status = in_array( $opts['status'], array( 'publish', 'draft' ), true ) ? $opts['status'] : 'publish';
	$res    = array( 'new' => 0, 'update' => 0, 'skip' => 0, 'photos' => 0, 'photo_fail' => 0, 'ids' => array() );
	$by_row = array();
	foreach ( $plan as $p ) {
		if ( 'skip' === $p['action'] || ( 'update' === $p['action'] && ! $opts['update'] ) ) {
			$res['skip']++;
			continue;
		}
		$d  = $p['data'];
		$id = (int) $p['id'];
		// a second row for a person created earlier in the same file updates that person
		$k = '' !== $d['email'] ? 'e:' . strtolower( $d['email'] ) : 'n:' . $d['name'];
		if ( ! $id && isset( $by_row[ $k ] ) ) {
			$id = $by_row[ $k ];
		}
		$post = array( 'post_type' => 'mcupr_person', 'post_title' => $d['name'] );
		if ( $id ) {
			$post['ID'] = $id;
			if ( '' !== $d['bio'] ) {
				$post['post_content'] = $d['bio'];
			}
			if ( null !== $d['order'] ) {
				$post['menu_order'] = $d['order'];
			}
			wp_update_post( wp_slash( $post ) );
			$res['update']++;
		} else {
			$post['post_status']  = $status;
			$post['post_content'] = $d['bio'];
			$post['menu_order']   = null !== $d['order'] ? $d['order'] : 0;
			$id                   = wp_insert_post( wp_slash( $post ) );
			if ( ! $id || is_wp_error( $id ) ) {
				$res['skip']++;
				continue;
			}
			$res['new']++;
		}
		$by_row[ $k ] = $id;
		$res['ids'][] = $id;
		$set = static function ( $meta, $val ) use ( $id ) {
			if ( '' !== $val && null !== $val ) {
				update_post_meta( $id, '_mcupr_person_' . $meta, $val );
			}
		};
		foreach ( array( 'rank', 'type', 'office', 'expertise', 'scholar', 'email', 'tel' ) as $f ) {
			$set( $f, $d[ $f ] );
		}
		$set( 'role', $d['role'] );
		$set( 'dept', $d['unit_text'] );
		if ( $d['unit'] ) {
			update_post_meta( $id, '_mcupr_person_unit', $d['unit'] );
		}
		if ( null !== $d['head'] ) {
			update_post_meta( $id, '_mcupr_person_head', $d['head'] );
		}
		$lang_default = mcupr_default_lang();
		foreach ( array( 'name_en' => 'title', 'role_en' => 'role' ) as $f => $meta ) {
			if ( '' !== $d[ $f ] && 'en' !== $lang_default ) {
				update_post_meta( $id, "_mcupr_en_{$meta}", $d[ $f ] );
			}
		}
		if ( $d['groups'] ) {
			$terms = array();
			foreach ( $d['groups'] as $g ) {
				$t = term_exists( $g, 'mcupr_person_group' );
				if ( ! $t ) {
					$t = wp_insert_term( $g, 'mcupr_person_group' );
				}
				if ( ! is_wp_error( $t ) ) {
					$terms[] = (int) ( is_array( $t ) ? $t['term_id'] : $t );
				}
			}
			if ( $terms ) {
				wp_set_object_terms( $id, $terms, 'mcupr_person_group' );
			}
		}
		if ( $d['honors'] ) {
			update_post_meta( $id, '_mcupr_person_honors', $d['honors'] );
		}
		if ( null !== $d['hide'] ) {
			update_post_meta( $id, '_mcupr_person_hide', $d['hide'] );
		}
		if ( null !== $d['noprofile'] ) {
			if ( $d['noprofile'] ) {
				update_post_meta( $id, '_mcupr_person_profile', 'off' );
			} else {
				delete_post_meta( $id, '_mcupr_person_profile' );
			}
		}
		if ( $opts['photos'] && '' !== $d['photo'] && ! has_post_thumbnail( $id ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$aid = media_sideload_image( $d['photo'], $id, $d['name'], 'id' );
			if ( is_wp_error( $aid ) ) {
				$res['photo_fail']++;
			} else {
				set_post_thumbnail( $id, (int) $aid );
				$res['photos']++;
			}
		}
	}
	return $res;
}

/* ------------------------------------------------------------------ files out: template and export */

/** One CSV line, every cell quoted and guarded against formulas (mcupr_csv_cell). */
function mcupr_import_csv_line( $cells ) {
	return implode( ',', array_map( 'mcupr_csv_cell', $cells ) ) . "\r\n";
}

function mcupr_import_send_csv( $filename, $lines ) {
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	echo "\xEF\xBB\xBF" . $lines; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

add_action(
	'admin_post_mcupr_staff_template',
	static function () {
		if ( ! mcupr_check_request( '_wpnonce', 'mcupr_staff_file', 'edit_pages', 'get' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		$cols  = mcupr_import_columns();
		$lines = mcupr_import_csv_line( array_map( static function ( $c ) { return $c[0]; }, array_values( $cols ) ) );
		$ex    = array(
			'name' => 'ผศ.ดร.สมชาย ใจดี', 'name_en' => 'Asst. Prof. Dr. Somchai Jaidee', 'role' => 'รองอธิการบดีฝ่ายวิชาการ', 'role_en' => 'Vice President for Academic Affairs',
			'rank' => 'ผู้ช่วยศาสตราจารย์ ดร.', 'type' => 'ข้าราชการ', 'unit' => 'สำนักอธิการบดี', 'head' => 'ใช่', 'group' => 'ผู้บริหาร', 'email' => 'somchai@example.ac.th',
			'tel' => '054-000-000', 'office' => 'อาคาร 1 ห้อง 101', 'scholar' => 'https://orcid.org/0000-0000-0000-0000', 'expertise' => 'พุทธศาสตร์, บาลี',
			'honors' => 'ท.ช. 2562; ต.ม. 2557', 'hide' => 'โทร, ที่ทำงาน', 'noprofile' => '', 'order' => '1', 'photo' => '', 'bio' => '',
		);
		$lines .= mcupr_import_csv_line( array_map( static function ( $k ) use ( $ex ) { return isset( $ex[ $k ] ) ? $ex[ $k ] : ''; }, array_keys( $cols ) ) );
		mcupr_import_send_csv( 'staff-template.csv', $lines );
	}
);

add_action(
	'admin_post_mcupr_staff_export',
	static function () {
		// the file is the whole staff directory as it is stored, telephone numbers and addresses the person asked to hide
		// included (it has to be, or putting the file back would wipe them), so it is for administrators only.
		if ( ! mcupr_check_request( '_wpnonce', 'mcupr_staff_file', 'manage_options', 'get' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		$cols   = mcupr_import_columns();
		$lines  = mcupr_import_csv_line( array_map( static function ( $c ) { return $c[0]; }, array_values( $cols ) ) );
		$people = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 2000, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true ) );
		foreach ( $people as $p ) {
			$m      = static function ( $k ) use ( $p ) {
				return (string) get_post_meta( $p->ID, '_mcupr_person_' . $k, true );
			};
			$unit   = (int) $m( 'unit' );
			$groups = wp_get_object_terms( $p->ID, 'mcupr_person_group', array( 'fields' => 'names' ) );
			$cat    = mcupr_honors();
			$hon    = array();
			foreach ( mcupr_person_rows( $p->ID, 'honors' ) as $h ) {
				if ( isset( $cat[ $h['key'] ] ) ) {
					$hon[] = trim( $cat[ $h['key'] ]['code'] . ' ' . $h['year'] . ' ' . $h['gazette'] );
				}
			}
			$hide = get_post_meta( $p->ID, '_mcupr_person_hide', true );
			$row  = array(
				'name' => $p->post_title, 'name_en' => (string) get_post_meta( $p->ID, '_mcupr_en_title', true ), 'role' => $m( 'role' ), 'role_en' => (string) get_post_meta( $p->ID, '_mcupr_en_role', true ),
				'rank' => $m( 'rank' ), 'type' => $m( 'type' ), 'unit' => $unit ? get_the_title( $unit ) : $m( 'dept' ), 'head' => $m( 'head' ) ? 'ใช่' : '',
				'group' => is_wp_error( $groups ) ? '' : implode( ', ', $groups ), 'email' => $m( 'email' ), 'tel' => $m( 'tel' ), 'office' => $m( 'office' ), 'scholar' => $m( 'scholar' ),
				'expertise' => $m( 'expertise' ), 'honors' => implode( '; ', $hon ), 'hide' => is_array( $hide ) ? implode( ', ', $hide ) : '', 'noprofile' => 'off' === $m( 'profile' ) ? 'ใช่' : '',
				'order' => (string) $p->menu_order, 'photo' => has_post_thumbnail( $p ) ? (string) get_the_post_thumbnail_url( $p, 'full' ) : '', 'bio' => wp_strip_all_tags( $p->post_content ),
			);
			$lines .= mcupr_import_csv_line( array_map( static function ( $k ) use ( $row ) { return isset( $row[ $k ] ) ? $row[ $k ] : ''; }, array_keys( $cols ) ) );
		}
		mcupr_import_send_csv( 'staff-' . gmdate( 'Y-m-d' ) . '.csv', $lines );
	}
);

/* ------------------------------------------------------------------ upload → preview → confirm */

function mcupr_import_back( $args = array() ) {
	wp_safe_redirect( add_query_arg( array_merge( array( 'post_type' => 'mcupr_person', 'page' => 'mcupr-staff-hub' ), $args ), admin_url( 'edit.php' ) ) . '#mcs-import' );
	exit;
}

add_action(
	'admin_post_mcupr_staff_import',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_imp_nonce', 'mcupr_import', 'edit_pages' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		$uid  = get_current_user_id();
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		if ( 'cancel' === $step ) {
			delete_transient( 'mcupr_imp_' . $uid );
			mcupr_import_back();
		}
		if ( 'confirm' === $step ) {
			$plan = get_transient( 'mcupr_imp_' . $uid );
			if ( ! is_array( $plan ) ) {
				mcupr_import_back( array( 'imp_err' => 'expired' ) );
			}
			$res = mcupr_import_apply( $plan, array( 'update' => ! empty( $_POST['do_update'] ), 'status' => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'publish', 'photos' => ! empty( $_POST['do_photos'] ) ) );
			delete_transient( 'mcupr_imp_' . $uid );
			mcupr_import_back( array( 'imp_done' => $res['new'] . '-' . $res['update'] . '-' . $res['skip'] . '-' . $res['photos'] . '-' . $res['photo_fail'] ) );
		}
		// upload
		$f = isset( $_FILES['staff_file'] ) ? $_FILES['staff_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
			mcupr_import_back( array( 'imp_err' => 'nofile' ) );
		}
		if ( (int) $f['size'] > MCUPR_IMPORT_MAX_BYTES ) {
			mcupr_import_back( array( 'imp_err' => 'big' ) );
		}
		$ext = strtolower( pathinfo( sanitize_file_name( $f['name'] ), PATHINFO_EXTENSION ) );
		if ( 'xlsx' === $ext ) {
			$rows = mcupr_import_read_xlsx( $f['tmp_name'] );
		} elseif ( in_array( $ext, array( 'csv', 'txt', 'tsv' ), true ) ) {
			$rows = mcupr_import_read_csv( $f['tmp_name'] );
		} else {
			mcupr_import_back( array( 'imp_err' => 'type' ) );
		}
		$recs = is_wp_error( $rows ) ? $rows : mcupr_import_rows_to_records( $rows );
		if ( is_wp_error( $recs ) ) {
			set_transient( 'mcupr_imp_msg_' . $uid, $recs->get_error_message(), 600 );
			mcupr_import_back( array( 'imp_err' => 'parse' ) );
		}
		set_transient( 'mcupr_imp_' . $uid, mcupr_import_plan( $recs ), HOUR_IN_SECONDS );
		mcupr_import_back( array( 'imp_preview' => 1 ) );
	}
);

/** The import / export card of the hub. */
function mcupr_import_card() {
	$uid  = get_current_user_id();
	$file = wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_staff_template' ), 'mcupr_staff_file' );
	$exp  = wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_staff_export' ), 'mcupr_staff_file' );
	echo '<div class="card" id="mcs-import"><h2>' . esc_html__( 'Import and export', 'mcupr' ) . '</h2>';
	// messages
	$errs = array(
		'nofile'  => __( 'No file was uploaded.', 'mcupr' ),
		'big'     => __( 'The file is larger than 2 MB.', 'mcupr' ),
		'type'    => __( 'Upload an .xlsx or .csv file.', 'mcupr' ),
		'expired' => __( 'The preview expired. Upload the file again.', 'mcupr' ),
	);
	if ( isset( $_GET['imp_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$k   = sanitize_key( wp_unslash( $_GET['imp_err'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$msg = 'parse' === $k ? (string) get_transient( 'mcupr_imp_msg_' . $uid ) : ( isset( $errs[ $k ] ) ? $errs[ $k ] : '' );
		if ( $msg ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}
	if ( isset( $_GET['imp_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$n = array_map( 'intval', explode( '-', sanitize_text_field( wp_unslash( $_GET['imp_done'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$n = array_pad( $n, 5, 0 );
		/* translators: 1: new, 2: updated, 3: skipped */
		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( __( 'Import finished: %1$d added, %2$d updated, %3$d skipped.', 'mcupr' ), $n[0], $n[1], $n[2] ) ) . ( $n[3] || $n[4] ? ' ' . esc_html( sprintf( /* translators: 1: photos ok, 2: photos failed */ __( 'Photos: %1$d loaded, %2$d failed.', 'mcupr' ), $n[3], $n[4] ) ) : '' ) . '</p></div>';
	}
	$plan = isset( $_GET['imp_preview'] ) ? get_transient( 'mcupr_imp_' . $uid ) : false; // phpcs:ignore WordPress.Security.NonceVerification
	if ( is_array( $plan ) ) {
		$cnt = array( 'new' => 0, 'update' => 0, 'skip' => 0 );
		foreach ( $plan as $p ) {
			$cnt[ $p['action'] ]++;
		}
		echo '<h3>' . esc_html__( 'Preview – nothing has been saved yet', 'mcupr' ) . '</h3><p><strong>' . (int) $cnt['new'] . '</strong> ' . esc_html__( 'new', 'mcupr' ) . ' · <strong>' . (int) $cnt['update'] . '</strong> ' . esc_html__( 'will update an existing person', 'mcupr' ) . ' · <strong>' . (int) $cnt['skip'] . '</strong> ' . esc_html__( 'skipped', 'mcupr' ) . '</p>';
		echo '<div style="max-height:360px;overflow:auto"><table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__( 'Result', 'mcupr' ) . '</th><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Unit', 'mcupr' ) . '</th><th>' . esc_html__( 'Notes', 'mcupr' ) . '</th></tr></thead><tbody>';
		$labels = array( 'new' => __( 'New', 'mcupr' ), 'update' => __( 'Update', 'mcupr' ), 'skip' => __( 'Skipped', 'mcupr' ) );
		foreach ( array_slice( $plan, 0, 300 ) as $p ) {
			$d = $p['data'];
			echo '<tr><td>' . (int) $p['row'] . '</td><td><strong>' . esc_html( $labels[ $p['action'] ] ) . '</strong></td><td>' . esc_html( $p['name'] ) . '</td><td>' . esc_html( $d ? ( $d['unit'] ? get_the_title( $d['unit'] ) : $d['unit_text'] ) : '' ) . '</td><td>' . esc_html( implode( ' ', array_merge( $p['err'], $p['warn'] ) ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px"><input type="hidden" name="action" value="mcupr_staff_import">';
		wp_nonce_field( 'mcupr_import', 'mcupr_imp_nonce' );
		echo '<p><label><input type="checkbox" name="do_update" value="1" checked> ' . esc_html__( 'Update people who already exist (matched by email, else by name). Empty cells never erase saved data.', 'mcupr' ) . '</label><br><label><input type="checkbox" name="do_photos" value="1"> ' . esc_html__( 'Download photos from the “Photo (URL)” column (this server connects to those addresses)', 'mcupr' ) . '</label></p>';
		echo '<p><label>' . esc_html__( 'New people are', 'mcupr' ) . ' <select name="status"><option value="publish">' . esc_html__( 'published', 'mcupr' ) . '</option><option value="draft">' . esc_html__( 'saved as drafts', 'mcupr' ) . '</option></select></label></p>';
		echo '<button type="submit" name="step" value="confirm" class="button button-primary">' . esc_html__( 'Import now', 'mcupr' ) . '</button> <button type="submit" name="step" value="cancel" class="button">' . esc_html__( 'Cancel', 'mcupr' ) . '</button></form>';
	} else {
		echo '<p>' . esc_html__( 'Add many people at once from a spreadsheet. Download the template, fill it in (Thai or English headings, any column order), save as .xlsx or .csv (UTF-8) and upload it. You see a preview first; nothing is saved until you confirm.', 'mcupr' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( $file ) . '">' . esc_html__( 'Download the template (CSV)', 'mcupr' ) . '</a>';
		if ( current_user_can( 'manage_options' ) ) {
			echo ' <a class="button" href="' . esc_url( $exp ) . '">' . esc_html__( 'Export everyone (CSV)', 'mcupr' ) . '</a>';
		}
		echo '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_staff_import"><input type="hidden" name="step" value="upload">';
		wp_nonce_field( 'mcupr_import', 'mcupr_imp_nonce' );
		echo '<p><input type="file" name="staff_file" accept=".xlsx,.csv,.tsv,.txt" required> <button type="submit" class="button button-primary">' . esc_html__( 'Upload and preview', 'mcupr' ) . '</button></p></form>';
		echo '<details><summary>' . esc_html__( 'Columns', 'mcupr' ) . '</summary><p style="max-width:70em">' . esc_html( implode( ' · ', array_map( static function ( $c ) { return $c[0]; }, array_values( mcupr_import_columns() ) ) ) ) . '</p><ul style="list-style:disc;margin-left:18px"><li>' . esc_html__( 'Only the name is required. Unit: the name of a unit of the Administration chart.', 'mcupr' ) . '</li><li>' . esc_html__( 'Head: yes / ใช่ / 1. Group: names separated by commas (created if new).', 'mcupr' ) . '</li><li>' . esc_html__( 'Decorations: abbreviation and year separated by semicolons, e.g. ท.ช. 2562; ต.ม. 2557 (a Royal Gazette reference may follow the year).', 'mcupr' ) . '</li><li>' . esc_html__( 'Hide: email, tel, office, links, bio, edu, works, honors (or the Thai words), separated by commas.', 'mcupr' ) . '</li></ul></details>';
	}
	echo '</div>';
}
