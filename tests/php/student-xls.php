<?php
/** Student import from the registrar's .xls report (and any .xls table): reading, the table made of it, several files, mistakes.   php php/student-xls.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$fx = __DIR__ . '/../fixtures/';
$bytes = file_get_contents( $fx . 'registrar-report.xls' );
t( 'excel' === mcupr_si_binary_kind( $bytes ), 'xls: recognised as an Excel file' );
$csv = mcupr_si_xls_to_csv( $bytes );
t( ! is_wp_error( $csv ), 'xls: read' . ( is_wp_error( $csv ) ? ' – ' . $csv->get_error_message() : '' ) );
$p = mcupr_si_parse( $csv );
t( ! is_wp_error( $p ) && 5 === count( $p['rows'] ) && isset( $p['cols']['sid'], $p['cols']['name'], $p['cols']['level'], $p['cols']['year'], $p['cols']['entry'], $p['cols']['faculty'], $p['cols']['major'], $p['cols']['note'], $p['cols']['rfid'] ), 'registrar report: 5 students from 2 blocks, all the columns recognised' );
$map = array(); $seen = array(); $rows = array();
foreach ( $p['rows'] as $r ) { $rows[] = mcupr_si_check( $r[1], $p['cols'], $map, $seen, 'add_update' ); }
$r0 = $rows[0]['row']; $r2 = $rows[2]['row'];
t( ! array_filter( wp_list_pluck( $rows, 'errors' ) ) && ! array_filter( wp_list_pluck( $rows, 'warnings' ) ), 'registrar report: no mistakes and no warnings' );
t( '6899000001' === $r0['sid'] && 'พระตัวอย่าง หนึ่ง' === $r0['name'], 'registrar report: student ID (a whole number, no ".0") and name (no leading space)' );
t( 'bachelor' === $r0['level'] && '2568' === $r0['entry'] && 'พุทธศาสตร์' === $r0['faculty'] && 'พระพุทธศาสนา' === $r0['major'] && 'studying' === $r0['status'], 'registrar report: level, year of entry, faculty, programme (without its code), status' );
$yr = mcupr_si_academic_year();
t( (string) ( $yr - 2568 + 1 ) === (string) $r0['year'] && (string) ( $yr - 2568 + 1 ) === (string) $r2['year'], 'registrar report: the year of study is worked out from the year of entry (this academic year ' . $yr . ')' );
t( false !== strpos( $r0['note'], 'วิทยาเขตตัวอย่าง' ) && false !== strpos( $r0['note'], 'ผศ.ดร. ตัวอย่าง ใจดี' ), 'registrar report: campus and adviser go to the note' );
t( false === strpos( $rows[2]['row']['note'], 'อาจารย์ที่ปรึกษา' ), 'registrar report: a block with no adviser has no adviser line' );
t( ! empty( $rows[0]['row']['_rfid'] ) && empty( $rows[1]['row']['_rfid'] ) && ! empty( $rows[2]['row']['_rfid'] ), 'registrar report: the card code of those who have one is kept as a hash (the others have none)' );
t( false === strpos( wp_json_encode( $p ), '4000000001' ), 'registrar report: the card codes are not in what is kept for the preview' );
$ids = wp_list_pluck( wp_list_pluck( $rows, 'row' ), 'sid' );
t( 5 === count( array_unique( $ids ) ), 'registrar report: five different students' );
// a block of another year of entry
$csv2 = mcupr_si_xls_to_csv( file_get_contents( $fx . 'registrar-report-2.xls' ) );
t( ! is_wp_error( $csv2 ) && 3 === count( explode( "\n", trim( $csv2 ) ) ), 'second report: read (header + 2 students)' );
$h1 = strtok( str_replace( "\r\n", "\n", $csv ), "\n" ); $h2 = strtok( str_replace( "\r\n", "\n", $csv2 ), "\n" );
t( $h1 === $h2, 'two reports have the same columns, so they can be imported together' );
// a plain table with the column names in the first row
$tb = mcupr_si_xls_to_csv( file_get_contents( $fx . 'students-table.xls' ) );
$pt = mcupr_si_parse( $tb );
t( ! is_wp_error( $pt ) && 2 === count( $pt['rows'] ) && isset( $pt['cols']['email'] ), 'plain .xls table: read like a CSV' );
$s = array(); $c = mcupr_si_check( $pt['rows'][0][1], $pt['cols'], array(), $s, 'add_update' );
t( '6899000010' === $c['row']['sid'] && 't1@example.org' === $c['row']['email'], 'plain .xls table: numbers become text without ".0"' );
// not an .xls
$bad = mcupr_si_xls_to_csv( "\xD0\xCF\x11\xE0" . str_repeat( 'x', 600 ) );
t( is_wp_error( $bad ), 'broken file: a clear error, not a crash' );
t( ! is_wp_error( mcupr_si_registrar_rows( array() ) ) && null === mcupr_si_registrar_rows( array( array( 'a', 'b' ), array( '1', '2' ) ) ), 'a sheet that is not a registrar report is left as it is' );
// the form takes several files
$_FILES = array( 'file' => array( 'name' => array( 'a.xls', 'b.csv' ), 'tmp_name' => array( '/tmp/a', '/tmp/b' ), 'size' => array( 10, 20 ), 'error' => array( 0, 0 ) ) );
$f = mcupr_si_posted_files();
t( 2 === count( $f ) && 'b.csv' === $f[1]['name'] && 20 === $f[1]['size'] && $f[0]['ok'], 'form: several files in one box are listed' );
$_FILES = array( 'file' => array( 'name' => 'one.csv', 'tmp_name' => '/tmp/x', 'size' => 5, 'error' => 0 ) );
t( 1 === count( mcupr_si_posted_files() ), 'form: a single file (old style) still works' );
$_FILES = array();
echo $fails ? "\n$fails FAILED\n" : "\nALL PASSED\n";
exit( $fails ? 1 : 0 );
