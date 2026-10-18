<?php
/**
 * The detailed record of a student from the university's central file (86 columns: names, codes, personal details, addresses,
 * guardian, earlier education, monastic details, Dhamma and Pali studies).
 *
 *  - The importer (Import students) reads the central file as it is and keeps these fields with the student. The columns that
 *    identify the student's circumstances most closely (national ID number, blood group, date of birth, religion, height, weight,
 *    passport, guardian's income) are kept ENCRYPTED (AES-256 with keys made from the site's secret keys); the rest are kept as text.
 *  - Who sees it: staff with the student-data permission (administrator, registrar) see everything on the student's record – opening
 *    it is written in the activity log; the student sees their own details in the student portal, with the national ID number and the
 *    passport number shown by their last four characters only. Nobody else; not in lists, search, exports or the REST API.
 *  - The codes of the central file (faculty, programme, level, status) are turned into names with a table that staff can edit
 *    (MCUPR › Students › Faculty and programme codes); a name set there is put on every student with that code at once.
 *  - From the central file also: the year of study is worked out from the year of entry (a student who entered more than twelve years ago
 *    gets a label "beyond the usual length" instead); a student who is not in a complete central file gets a label "not in the central file".
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_SP_PROFILE = '_mcupr_s_profile';
const MCUPR_SP_SECURE  = '_mcupr_s_secure';

/**
 * The fields of the central student file (the university's own system) that are kept with a student.
 * key => array( label, group, sensitive (kept encrypted), type, the column name in the central file, the Thai label ).
 */
function mcupr_sp_fields() {
	static $f = null;
	if ( null === $f ) {
		$f = array(
		'central_id' => array( __( 'Central student ID', 'mcupr' ), 'ids', 0, 'text', 'STUDENTID', 'รหัสนิสิตส่วนกลาง (STUDENTID)' ),
		'campus_id' => array( __( 'Campus code', 'mcupr' ), 'ids', 0, 'text', 'CAMPUSID', 'รหัสวิทยาเขต' ),
		'level_id' => array( __( 'Level code', 'mcupr' ), 'ids', 0, 'text', 'LEVELID', 'รหัสระดับ' ),
		'faculty_id' => array( __( 'Faculty code', 'mcupr' ), 'ids', 0, 'text', 'FACULTYID', 'รหัสคณะ' ),
		'dept_id' => array( __( 'Department code', 'mcupr' ), 'ids', 0, 'text', 'DEPARTMENTID', 'รหัสภาควิชา' ),
		'program_id' => array( __( 'Programme code', 'mcupr' ), 'ids', 0, 'text', 'PROGRAMID', 'รหัสสาขา/หลักสูตร' ),
		'group_year' => array( __( 'Group year', 'mcupr' ), 'ids', 0, 'text', 'GROUPYEAR', 'ปีกลุ่ม' ),
		'student_group' => array( __( 'Student group', 'mcupr' ), 'ids', 0, 'text', 'STUDENTGROUP', 'กลุ่มนิสิต' ),
		'status_code' => array( __( 'Status code', 'mcupr' ), 'ids', 0, 'text', 'STUDENTSTATUS', 'รหัสสถานภาพ' ),
		'admit_semester' => array( __( 'Semester of entry', 'mcupr' ), 'ids', 0, 'text', 'ADMITSEMESTER', 'ภาคการศึกษาที่เข้า' ),
		'prefix_id' => array( __( 'Title code', 'mcupr' ), 'names', 0, 'text', 'PREFIXID', 'รหัสคำนำหน้า' ),
		'prefix' => array( __( 'Title', 'mcupr' ), 'names', 0, 'text', 'PREFIXNAME', 'คำนำหน้า' ),
		'first_th' => array( __( 'First name', 'mcupr' ), 'names', 0, 'text', 'ชื่อ', 'ชื่อตัว' ),
		'last_th' => array( __( 'Surname / monastic name', 'mcupr' ), 'names', 0, 'text', 'นามสกุล/ฉายา', 'นามสกุล/ฉายา' ),
		'last_th_monk' => array( __( 'Surname (monk)', 'mcupr' ), 'names', 0, 'text', 'นามสกุล (พระ)', 'นามสกุล (พระ)' ),
		'first_en' => array( __( 'First name (English)', 'mcupr' ), 'names', 0, 'text', 'NAME', 'ชื่อ (อังกฤษ)' ),
		'last_en' => array( __( 'Surname / monastic name (English)', 'mcupr' ), 'names', 0, 'text', 'SURNAME/BUDDHISTNAME', 'นามสกุล/ฉายา (อังกฤษ)' ),
		'last_en_monk' => array( __( 'Surname (monk, English)', 'mcupr' ), 'names', 0, 'text', 'SURNAME (พระ)', 'นามสกุล (พระ) (อังกฤษ)' ),
		'wat' => array( __( 'Temple', 'mcupr' ), 'monk', 0, 'text', 'วัด', 'วัด' ),
		'wat_en' => array( __( 'Temple (English)', 'mcupr' ), 'monk', 0, 'text', 'วัด(Eng)', 'วัด (อังกฤษ)' ),
		'prev_status' => array( __( 'Status before leaving the monkhood', 'mcupr' ), 'monk', 0, 'text', 'สถานะเดิมก่อนลาสิกขา', 'สถานะเดิมก่อนลาสิกขา' ),
		'abbot' => array( __( 'Abbot', 'mcupr' ), 'monk', 0, 'text', 'ชื่อเจ้าอาวาส', 'ชื่อเจ้าอาวาส' ),
		'ordained' => array( __( 'Date of ordination', 'mcupr' ), 'monk', 0, 'date', 'วันเดือนปีที่บรรพชา/อุปสมบท', 'วันเดือนปีที่บรรพชา/อุปสมบท' ),
		'nikaya' => array( __( 'Sect (nikaya)', 'mcupr' ), 'monk', 0, 'text', 'นิกาย', 'นิกาย' ),
		'idcard' => array( __( 'National ID number', 'mcupr' ), 'personal', 1, 'digits', 'เลขประจำตัวประชาชน', 'เลขประจำตัวประชาชน' ),
		'blood' => array( __( 'Blood group', 'mcupr' ), 'personal', 1, 'text', 'หมู่โลหิต', 'หมู่โลหิต' ),
		'birth' => array( __( 'Date of birth', 'mcupr' ), 'personal', 1, 'date', 'วันเกิด', 'วันเกิด' ),
		'birthplace' => array( __( 'Place of birth', 'mcupr' ), 'personal', 0, 'text', 'สถานที่เกิด', 'สถานที่เกิด' ),
		'nationality' => array( __( 'Nationality', 'mcupr' ), 'personal', 0, 'text', 'สัญชาติ', 'สัญชาติ' ),
		'religion' => array( __( 'Religion', 'mcupr' ), 'personal', 1, 'text', 'ศาสนา', 'ศาสนา' ),
		'occupation' => array( __( 'Occupation', 'mcupr' ), 'personal', 0, 'text', 'อาชีพ', 'อาชีพ' ),
		'height' => array( __( 'Height (cm)', 'mcupr' ), 'personal', 1, 'num', 'ส่วนสูง', 'ส่วนสูง (ซม.)' ),
		'weight' => array( __( 'Weight (kg)', 'mcupr' ), 'personal', 1, 'num', 'น้ำหนัก', 'น้ำหนัก (กก.)' ),
		'passport' => array( __( 'Passport number', 'mcupr' ), 'personal', 1, 'text', 'เลขที่พาสปอร์ต', 'เลขที่พาสปอร์ต' ),
		'passport_issued' => array( __( 'Passport issued', 'mcupr' ), 'personal', 1, 'date', 'วันที่ออกเอกสาร', 'วันที่ออกพาสปอร์ต' ),
		'passport_expires' => array( __( 'Passport expires', 'mcupr' ), 'personal', 1, 'date', 'วันหมดอายุ', 'วันหมดอายุพาสปอร์ต' ),
		'home_no' => array( __( 'House number', 'mcupr' ), 'home', 0, 'text', 'บ้านเลขที่', 'บ้านเลขที่' ),
		'home_moo' => array( __( 'Moo', 'mcupr' ), 'home', 0, 'text', 'หมู่ที่', 'หมู่ที่' ),
		'home_soi' => array( __( 'Soi', 'mcupr' ), 'home', 0, 'text', 'ซอย', 'ซอย' ),
		'home_road' => array( __( 'Road', 'mcupr' ), 'home', 0, 'text', 'ถนน', 'ถนน' ),
		'home_village' => array( __( 'Village', 'mcupr' ), 'home', 0, 'text', 'หมู่บ้าน', 'หมู่บ้าน' ),
		'home_tambon' => array( __( 'Sub-district', 'mcupr' ), 'home', 0, 'text', 'ตำบล', 'ตำบล' ),
		'home_amphoe' => array( __( 'District', 'mcupr' ), 'home', 0, 'text', 'อำเภอ', 'อำเภอ' ),
		'home_province' => array( __( 'Province', 'mcupr' ), 'home', 0, 'text', 'จังหวัด', 'จังหวัด' ),
		'home_zip' => array( __( 'Postcode', 'mcupr' ), 'home', 0, 'text', 'รหัสไปรษณีย์', 'รหัสไปรษณีย์' ),
		'cur_addr' => array( __( 'Address', 'mcupr' ), 'cur', 0, 'text', 'ที่อยู่ปัจจุบัน', 'ที่อยู่ปัจจุบัน' ),
		'cur_tambon' => array( __( 'Sub-district', 'mcupr' ), 'cur', 0, 'text', 'CURRENTADDRESS2', 'ตำบล (ปัจจุบัน)' ),
		'cur_amphoe' => array( __( 'District', 'mcupr' ), 'cur', 0, 'text', 'CURRENTDISTRICT', 'อำเภอ (ปัจจุบัน)' ),
		'cur_province' => array( __( 'Province', 'mcupr' ), 'cur', 0, 'text', 'CURRENTPROVINCEID', 'จังหวัด (ปัจจุบัน)' ),
		'cur_zip' => array( __( 'Postcode', 'mcupr' ), 'cur', 0, 'text', 'CURRENTZIPCODE', 'รหัสไปรษณีย์ (ปัจจุบัน)' ),
		'cur_phone' => array( __( 'Telephone', 'mcupr' ), 'cur', 0, 'text', 'CURRENTPHONENO', 'โทรศัพท์ (ปัจจุบัน)' ),
		'off_addr' => array( __( 'Address', 'mcupr' ), 'off', 0, 'text', 'ที่อยู่ปฏิบัติศาสนกิจ', 'ที่อยู่ปฏิบัติศาสนกิจ' ),
		'off_tambon' => array( __( 'Sub-district', 'mcupr' ), 'off', 0, 'text', 'OFFICEADDRESS2', 'ตำบล (ปฏิบัติศาสนกิจ)' ),
		'off_amphoe' => array( __( 'District', 'mcupr' ), 'off', 0, 'text', 'OFFICEDISTRICT', 'อำเภอ (ปฏิบัติศาสนกิจ)' ),
		'off_province' => array( __( 'Province', 'mcupr' ), 'off', 0, 'text', 'OFFICEPROVINCEID', 'จังหวัด (ปฏิบัติศาสนกิจ)' ),
		'off_zip' => array( __( 'Postcode', 'mcupr' ), 'off', 0, 'text', 'OFFICEZIPCODE', 'รหัสไปรษณีย์ (ปฏิบัติศาสนกิจ)' ),
		'off_phone' => array( __( 'Telephone', 'mcupr' ), 'off', 0, 'text', 'OFFICEPHONENO', 'โทรศัพท์ (ปฏิบัติศาสนกิจ)' ),
		'off_fax' => array( __( 'Fax', 'mcupr' ), 'off', 0, 'text', 'OFFICEFAXNO', 'โทรสาร (ปฏิบัติศาสนกิจ)' ),
		'ref_name' => array( __( 'Name', 'mcupr' ), 'par', 0, 'text', 'ชื่อบุคคลอ้างอิง', 'ชื่อบุคคลอ้างอิง' ),
		'ref_relation' => array( __( 'Relationship', 'mcupr' ), 'par', 0, 'text', 'ความสัมพันธ์เป็น', 'ความสัมพันธ์เป็น' ),
		'ref_income' => array( __( 'Income', 'mcupr' ), 'par', 1, 'num', 'รายได้บุคคลอ้างอิง', 'รายได้บุคคลอ้างอิง' ),
		'ref_occupation' => array( __( 'Occupation', 'mcupr' ), 'par', 0, 'text', 'อาชีพบุคคลอ้างอิง', 'อาชีพบุคคลอ้างอิง' ),
		'par_addr' => array( __( 'Address', 'mcupr' ), 'par', 0, 'text', 'ที่อยู่ผู้ปกครอง', 'ที่อยู่ผู้ปกครอง' ),
		'par_tambon' => array( __( 'Sub-district', 'mcupr' ), 'par', 0, 'text', 'PARENTADDRESS2', 'ตำบล (ผู้ปกครอง)' ),
		'par_amphoe' => array( __( 'District', 'mcupr' ), 'par', 0, 'text', 'PARENTDISTRICT', 'อำเภอ (ผู้ปกครอง)' ),
		'par_province' => array( __( 'Province', 'mcupr' ), 'par', 0, 'text', 'PARENTPROVINCEID', 'จังหวัด (ผู้ปกครอง)' ),
		'par_zip' => array( __( 'Postcode', 'mcupr' ), 'par', 0, 'text', 'PARENTZIPCODE', 'รหัสไปรษณีย์ (ผู้ปกครอง)' ),
		'par_phone' => array( __( 'Telephone', 'mcupr' ), 'par', 0, 'text', 'PARENTPHONENO', 'โทรศัพท์ (ผู้ปกครอง)' ),
		'edu_degree' => array( __( 'Qualification', 'mcupr' ), 'edu', 0, 'text', 'ชื่อวุฒิที่จบ', 'ชื่อวุฒิที่จบ' ),
		'edu_school' => array( __( 'School', 'mcupr' ), 'edu', 0, 'text', 'จบจากสถานศึกษา', 'จบจากสถานศึกษา' ),
		'edu_year' => array( __( 'Year finished', 'mcupr' ), 'edu', 0, 'text', 'ปีที่จบ', 'ปีที่จบ' ),
		'edu_docno' => array( __( 'Document number', 'mcupr' ), 'edu', 0, 'text', 'เลขที่เอกสาร', 'เลขที่เอกสาร' ),
		'qual_check' => array( __( 'Qualification check sent as', 'mcupr' ), 'edu', 0, 'text', 'ส่งหนังสือตรวจสอบคุณวุฒิ', 'ส่งหนังสือตรวจสอบคุณวุฒิ' ),
		'dhamma_level' => array( __( 'Nak Tham level passed', 'mcupr' ), 'dhamma', 0, 'text', 'สอบไล่ได้นักธรรม', 'สอบไล่ได้นักธรรม' ),
		'dhamma_year' => array( __( 'In year (B.E.)', 'mcupr' ), 'dhamma', 0, 'text', 'เมื่อพศ', 'เมื่อ พ.ศ. (นักธรรม)' ),
		'dhamma_school' => array( __( 'Place of study', 'mcupr' ), 'dhamma', 0, 'text', 'สำนักเรียน', 'สำนักเรียน (นักธรรม)' ),
		'dhamma_docno' => array( __( 'Document number', 'mcupr' ), 'dhamma', 0, 'text', 'เลขที่เอกสาร (ธรรม)', 'เลขที่เอกสาร (ธรรม)' ),
		'pali_admit' => array( __( 'Pali qualification used to apply', 'mcupr' ), 'pali', 0, 'text', 'วุฒิบาลที่ใช้สมัครเข้าศึกษา', 'วุฒิบาลีที่ใช้สมัครเข้าศึกษา' ),
		'pali_level' => array( __( 'Pali grade passed', 'mcupr' ), 'pali', 0, 'text', 'สอบไล่ได้บาลีชั้น', 'สอบไล่ได้บาลีชั้น' ),
		'pali_year' => array( __( 'In year (B.E.)', 'mcupr' ), 'pali', 0, 'text', 'เมื่อพศที่สมัคร', 'เมื่อ พ.ศ. (บาลี)' ),
		'pali_school' => array( __( 'Place of study', 'mcupr' ), 'pali', 0, 'text', 'สำนักเรียนวัด', 'สำนักเรียนวัด (บาลี)' ),
		'pali_docno' => array( __( 'Document number', 'mcupr' ), 'pali', 0, 'text', 'เลขที่เอกสารที่สมัครเข้าเรียน', 'เลขที่เอกสารที่สมัครเข้าเรียน' ),
		'pali_extra' => array( __( 'Extra Pali study', 'mcupr' ), 'pali', 0, 'text', 'เรียนบาลีเสริม', 'เรียนบาลีเสริม' ),
		);
	}
	return $f;
}

function mcupr_sp_groups() {
	return array(
		'ids' => __( 'Codes from the central system', 'mcupr' ),
		'names' => __( 'Names', 'mcupr' ),
		'personal' => __( 'Personal details', 'mcupr' ),
		'monk' => __( 'Monastic details', 'mcupr' ),
		'home' => __( 'Registered address', 'mcupr' ),
		'cur' => __( 'Current address', 'mcupr' ),
		'off' => __( 'Address of religious duty', 'mcupr' ),
		'par' => __( 'Guardian / reference person', 'mcupr' ),
		'edu' => __( 'Earlier education', 'mcupr' ),
		'dhamma' => __( 'Dhamma studies (Nak Tham)', 'mcupr' ),
		'pali' => __( 'Pali studies', 'mcupr' ),
	);
}

/** Is this a sensitive field (kept encrypted)? */
function mcupr_sp_is_secret( $key ) {
	$f = mcupr_sp_fields();
	return isset( $f[ $key ] ) && 1 === (int) $f[ $key ][2];
}

/* ------------------------------------------------------------------ values */

/** Months of the central file's dates ("28-พ.ย.-85"). */
function mcupr_sp_months() {
	return array( 'ม.ค.' => 1, 'ก.พ.' => 2, 'มี.ค.' => 3, 'เม.ย.' => 4, 'พ.ค.' => 5, 'มิ.ย.' => 6, 'ก.ค.' => 7, 'ส.ค.' => 8, 'ก.ย.' => 9, 'ต.ค.' => 10, 'พ.ย.' => 11, 'ธ.ค.' => 12 );
}

/**
 * A date as YYYY-MM-DD (Gregorian) or ''. Understands "28-พ.ย.-85" (the central system's two-digit year, Gregorian: 00–49 is 20xx, 50–99 is
 * 19xx, and a date in the future goes back a century unless $allow_future), "2007-11-28", "28/11/2007" and Buddhist-era years (25xx).
 */
function mcupr_sp_date( $raw, $allow_future = false ) {
	$v = trim( (string) $raw );
	if ( '' === $v || preg_match( '/^[\-\.]+$/', $v ) ) {
		return '';
	}
	$d = $m = $y = 0;
	$mon = mcupr_sp_months();
	if ( preg_match( '/^(\d{1,2})\s*-\s*([^\d\-]+?)\s*-\s*(\d{2,4})$/u', $v, $x ) && isset( $mon[ trim( $x[2] ) ] ) ) {
		$d = (int) $x[1];
		$m = $mon[ trim( $x[2] ) ];
		$y = (int) $x[3];
		if ( $y < 100 ) {
			$y += $y < 50 ? 2000 : 1900;
			if ( ! $allow_future && ( $y > (int) gmdate( 'Y' ) || ( $y === (int) gmdate( 'Y' ) && gmmktime( 0, 0, 0, $m, $d, $y ) > time() ) ) ) {
				$y -= 100;
			}
		}
	} elseif ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $x ) ) {
		list( , $y, $m, $d ) = array_map( 'intval', $x );
	} elseif ( preg_match( '/^(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})$/', $v, $x ) ) {
		list( , $d, $m, $y ) = array_map( 'intval', $x );
	} else {
		return '';
	}
	if ( $y > 2400 ) {
		$y -= 543;
	}
	return $y >= 1850 && checkdate( $m, $d, $y ) ? sprintf( '%04d-%02d-%02d', $y, $m, $d ) : '';
}

/** One cell as the value to keep ('' = nothing). */
function mcupr_sp_clean( $key, $raw ) {
	$f = mcupr_sp_fields();
	if ( ! isset( $f[ $key ] ) ) {
		return '';
	}
	$v = trim( preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', (string) $raw ) ) );
	if ( '' === $v || preg_match( '/^[\-\.]+$/', $v ) ) { // the central file writes "-" or "." for "nothing"
		return '';
	}
	switch ( $f[ $key ][3] ) {
		case 'date':
			return mcupr_sp_date( $v, 'passport_expires' === $key ); // (a passport expires in the future; a birth or an ordination does not)
		case 'digits':
			return preg_replace( '/\D+/', '', $v );
		case 'num':
			$n = str_replace( ',', '', $v );
			return is_numeric( $n ) ? (string) ( 0 + $n ) : '';
		default:
			return mb_substr( sanitize_text_field( $v ), 0, 255 );
	}
}

/* ------------------------------------------------------------------ encryption of the sensitive fields */

function mcupr_sp_can_encrypt() {
	return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' ) && in_array( 'aes-256-cbc', array_map( 'strtolower', openssl_get_cipher_methods() ), true );
}
function mcupr_sp_key( $label ) {
	return hash_hmac( 'sha256', 'mcupr-student-profile|' . $label, wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
}
/** 'x:' + base64( iv . mac . ciphertext ) (AES-256-CBC, encrypt-then-MAC), or '' when encryption is not possible here. */
function mcupr_sp_encrypt( $plain ) {
	if ( '' === (string) $plain || ! mcupr_sp_can_encrypt() ) {
		return '';
	}
	$iv = random_bytes( 16 );
	$ct = openssl_encrypt( (string) $plain, 'aes-256-cbc', mcupr_sp_key( 'enc' ), OPENSSL_RAW_DATA, $iv );
	if ( false === $ct ) {
		return '';
	}
	return 'x:' . base64_encode( $iv . hash_hmac( 'sha256', $iv . $ct, mcupr_sp_key( 'mac' ), true ) . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}
/** The text of an 'x:' value, or '' (not encrypted by this site, changed, or the secret keys are not the same any more). */
function mcupr_sp_decrypt( $blob ) {
	$blob = (string) $blob;
	if ( 0 !== strpos( $blob, 'x:' ) || ! mcupr_sp_can_encrypt() ) {
		return '';
	}
	$raw = base64_decode( substr( $blob, 2 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	if ( false === $raw || strlen( $raw ) < 49 ) {
		return '';
	}
	$iv  = substr( $raw, 0, 16 );
	$mac = substr( $raw, 16, 32 );
	$ct  = substr( $raw, 48 );
	if ( ! hash_equals( hash_hmac( 'sha256', $iv . $ct, mcupr_sp_key( 'mac' ), true ), $mac ) ) {
		return '';
	}
	$p = openssl_decrypt( $ct, 'aes-256-cbc', mcupr_sp_key( 'enc' ), OPENSSL_RAW_DATA, $iv );
	return false === $p ? '' : $p;
}

/* ------------------------------------------------------------------ keeping them */

/** array( 'p' => key => text, 's' => key => 'x:…' ) of a student. */
function mcupr_sp_raw( $post_id ) {
	$p = json_decode( (string) get_post_meta( $post_id, MCUPR_SP_PROFILE, true ), true );
	$s = json_decode( (string) get_post_meta( $post_id, MCUPR_SP_SECURE, true ), true );
	return array( 'p' => is_array( $p ) ? $p : array(), 's' => is_array( $s ) ? $s : array() );
}
/** Every detail of a student as key => text (the sensitive ones decrypted). */
function mcupr_sp_get( $post_id ) {
	$r   = mcupr_sp_raw( $post_id );
	$out = $r['p'];
	foreach ( $r['s'] as $k => $blob ) {
		$out[ $k ] = mcupr_sp_decrypt( $blob );
	}
	return $out;
}
/** Add details: only what is not empty replaces what is there (an empty cell keeps the old value). $p plain text, $s 'x:…' values. */
function mcupr_sp_put( $post_id, $p, $s ) {
	$r = mcupr_sp_raw( $post_id );
	foreach ( (array) $p as $k => $v ) {
		if ( '' !== (string) $v && isset( mcupr_sp_fields()[ $k ] ) && ! mcupr_sp_is_secret( $k ) ) {
			$r['p'][ $k ] = (string) $v;
		}
	}
	foreach ( (array) $s as $k => $v ) {
		if ( '' !== (string) $v && 0 === strpos( (string) $v, 'x:' ) && mcupr_sp_is_secret( $k ) ) {
			$r['s'][ $k ] = (string) $v;
		}
	}
	$old = mcupr_sp_raw( $post_id );
	if ( $r['p'] !== $old['p'] ) {
		update_post_meta( $post_id, MCUPR_SP_PROFILE, wp_slash( wp_json_encode( $r['p'], JSON_UNESCAPED_UNICODE ) ) );
	}
	if ( $r['s'] !== $old['s'] ) {
		update_post_meta( $post_id, MCUPR_SP_SECURE, wp_slash( wp_json_encode( $r['s'] ) ) );
	}
	return $r['p'] !== $old['p'] || $r['s'] !== $old['s'];
}

/** Drop one encrypted detail (used when staff remove the national ID number). */
function mcupr_sp_forget( $post_id, $key ) {
	$r = mcupr_sp_raw( $post_id );
	if ( isset( $r['s'][ $key ] ) ) {
		unset( $r['s'][ $key ] );
		update_post_meta( $post_id, MCUPR_SP_SECURE, wp_slash( wp_json_encode( $r['s'] ) ) );
	}
}

/* ------------------------------------------------------------------ the codes → names table */

function mcupr_sp_code_defaults() {
	return array(
		'faculty' => array( '1' => 'พุทธศาสตร์', '2' => 'ครุศาสตร์', '4' => 'สังคมศาสตร์' ),
		'program' => array( '6515011' => 'พระพุทธศาสนา', '6710201' => 'สังคมศึกษา', '6710203' => 'การสอนภาษาอังกฤษ', '62102001' => 'สังคมศึกษา', '62102003' => 'การสอนภาษาอังกฤษ', '63104002' => 'นิติศาสตรบัณฑิต', '6850402' => 'นิติศาสตรบัณฑิต', '64501401' => 'รัฐศาสตรบัณฑิต', '67501401' => 'รัฐศาสตร์' ),
	);
}
/** The table: array( faculty => id => name, program => id => name ) – what staff saved over the defaults. */
function mcupr_sp_codes() {
	$s = get_option( 'mcupr_sp_codes', array() );
	$s = is_array( $s ) ? $s : array();
	$d = mcupr_sp_code_defaults();
	foreach ( array( 'faculty', 'program' ) as $k ) {
		$d[ $k ] = ( isset( $s[ $k ] ) && is_array( $s[ $k ] ) ? $s[ $k ] : array() ) + $d[ $k ];
	}
	return $d;
}
function mcupr_sp_code_name( $kind, $id ) {
	$c = mcupr_sp_codes();
	$n = isset( $c[ $kind ][ (string) $id ] ) ? trim( (string) $c[ $kind ][ (string) $id ] ) : '';
	return $n;
}
/** The central codes for level and status → this website's. */
function mcupr_sp_level_of( $id ) {
	$m = apply_filters( 'mcupr_sp_levels', array( '1' => 'bachelor', '2' => 'master', '3' => 'doctor' ) );
	return isset( $m[ (string) $id ] ) ? $m[ (string) $id ] : '';
}
function mcupr_sp_status_of( $id ) {
	$m = apply_filters( 'mcupr_sp_statuses', array( '10' => 'studying' ) );
	return isset( $m[ (string) $id ] ) ? $m[ (string) $id ] : '';
}

/* ------------------------------------------------------------------ the importer's part */

/** Column names of the central file (and the Thai names of this website's own file) → the field, for mcupr_si_headers(). */
function mcupr_sp_headers() {
	$h = array();
	foreach ( mcupr_sp_fields() as $k => $f ) {
		$h[ $k ] = array( $f[4], $f[5] );
	}
	$h['first_th'][] = 'ชื่อ';
	return $h;
}

/**
 * Add what the central file brings to a row of the importer. $get( key ) = the cell of that column or null; the row has the usual fields.
 * Sets: name, name_en, faculty, major, level, status, year, and '_p' (plain details), '_s' ('x:…' sensitive details), '_central', '_overdue'.
 */
function mcupr_sp_row( $row, $get ) {
	$p = array();
	$s = array();
	foreach ( mcupr_sp_fields() as $k => $f ) {
		$cell = $get( $k );
		if ( null === $cell || '' === $cell ) {
			continue;
		}
		if ( 1 === (int) $f[2] ) {
			$s[ $k ] = $cell; // already 'x:…' (made when the file was read)
		} else {
			$v = mcupr_sp_clean( $k, $cell );
			if ( '' !== $v ) {
				$p[ $k ] = $v;
			}
		}
	}
	if ( isset( $row['phone'] ) && preg_match( '/^[\-\.\s]+$/', (string) $row['phone'] ) ) {
		$row['phone'] = ''; // "-" is how the central file writes "none"
	}
	$row['_p']       = $p;
	$row['_s']       = $s;
	$row['_central'] = null !== $get( 'first_th' ) ? 1 : 0;
	$row['_overdue'] = 0;
	if ( ! $row['_central'] ) {
		return $row;
	}
	// names: title + first name + surname / monastic name (what the registrar's report shows)
	$th = trim( ( isset( $p['prefix'] ) ? $p['prefix'] : '' ) . ( isset( $p['first_th'] ) ? $p['first_th'] : '' ) . ' ' . ( isset( $p['last_th'] ) ? $p['last_th'] : '' ) );
	if ( '' !== $th ) {
		$row['name'] = mb_substr( $th, 0, 120 );
	}
	$en = trim( ( isset( $p['first_en'] ) ? $p['first_en'] : '' ) . ' ' . ( isset( $p['last_en'] ) ? $p['last_en'] : '' ) );
	if ( '' !== $en && empty( $row['name_en'] ) ) {
		$row['name_en'] = mb_substr( $en, 0, 120 );
	}
	// the codes
	if ( isset( $p['faculty_id'] ) && '' !== ( $n = mcupr_sp_code_name( 'faculty', $p['faculty_id'] ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$row['faculty'] = $n;
	}
	if ( isset( $p['program_id'] ) && '' !== ( $n = mcupr_sp_code_name( 'program', $p['program_id'] ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$row['major'] = $n;
	}
	if ( isset( $p['level_id'] ) && '' !== ( $n = mcupr_sp_level_of( $p['level_id'] ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$row['level'] = $n;
	}
	if ( isset( $p['status_code'] ) && '' !== ( $n = mcupr_sp_status_of( $p['status_code'] ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$row['status'] = $n;
	}
	// the year of study from the year of entry; beyond twelve years it is a label instead
	if ( ! empty( $row['entry'] ) ) {
		$yr = mcupr_si_academic_year() - (int) $row['entry'] + 1;
		if ( $yr > 12 ) {
			$row['year']     = '';
			$row['_overdue'] = 1;
		} else {
			$row['year'] = (string) max( 1, $yr );
		}
	}
	return $row;
}

/** After the row is saved: keep the details and the labels. Returns true when something changed. */
function mcupr_sp_store( $post_id, $row ) {
	$changed = false;
	if ( ! empty( $row['_p'] ) || ! empty( $row['_s'] ) ) {
		$changed = mcupr_sp_put( $post_id, isset( $row['_p'] ) ? $row['_p'] : array(), isset( $row['_s'] ) ? $row['_s'] : array() );
		foreach ( array( 'faculty_id' => '_mcupr_s_fac_id', 'program_id' => '_mcupr_s_prog_id' ) as $k => $mk ) {
			if ( ! empty( $row['_p'][ $k ] ) && (string) get_post_meta( $post_id, $mk, true ) !== (string) $row['_p'][ $k ] ) {
				update_post_meta( $post_id, $mk, $row['_p'][ $k ] );
			}
		}
	}
	if ( ! empty( $row['_central'] ) ) {
		update_post_meta( $post_id, '_mcupr_s_central', '1' );
		if ( ! empty( $row['_overdue'] ) ) {
			update_post_meta( $post_id, '_mcupr_s_overdue', '1' );
		} else {
			delete_post_meta( $post_id, '_mcupr_s_overdue' );
		}
	}
	return $changed;
}

/** A complete central file was imported: whoever was not in it gets the label (and whoever was, loses it). Returns how many are labelled. */
function mcupr_sp_mark_missing( $present_ids ) {
	global $wpdb;
	$present = array_flip( array_map( 'intval', (array) $present_ids ) );
	$n       = 0;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status <> 'trash'", MCUPR_STU ) ) as $id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( isset( $present[ (int) $id ] ) ) {
			update_post_meta( (int) $id, '_mcupr_s_central', '1' );
		} else {
			update_post_meta( (int) $id, '_mcupr_s_central', '0' );
			++$n;
		}
	}
	return $n;
}

/* ------------------------------------------------------------------ the labels on a record */

function mcupr_sp_labels( $post_id ) {
	$out = array();
	if ( '1' === (string) get_post_meta( $post_id, '_mcupr_s_overdue', true ) ) {
		$out['overdue'] = __( 'Beyond the usual length of the programme', 'mcupr' );
	}
	if ( '0' === (string) get_post_meta( $post_id, '_mcupr_s_central', true ) ) {
		$out['central'] = __( 'Not in the central file', 'mcupr' );
	}
	return $out;
}
add_filter(
	'manage_mcupr_student_posts_columns',
	static function ( $c ) {
		$c['mcs_labels'] = __( 'Labels', 'mcupr' );
		return $c;
	},
	20
);
add_action(
	'manage_mcupr_student_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcs_labels' === $col ) {
			$l = mcupr_sp_labels( $id );
			echo $l ? '<span style="display:inline-block;padding:2px 8px;border:1px solid #996800;border-radius:999px;color:#614200;background:#fcf9e8;font-size:12px">' . implode( '</span> <span style="display:inline-block;padding:2px 8px;border:1px solid #996800;border-radius:999px;color:#614200;background:#fcf9e8;font-size:12px">', array_map( 'esc_html', $l ) ) . '</span>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ showing the details */

/** Details by group as an HTML list. $for 'staff' = everything; 'student' = their own, the ID number and the passport number by the last four characters. */
function mcupr_sp_html( $post_id, $for = 'staff', $lang = 'en' ) {
	$vals   = mcupr_sp_get( $post_id );
	$groups = mcupr_sp_groups();
	$th     = array( 'ids' => 'รหัสจากส่วนกลาง', 'names' => 'ชื่อ', 'personal' => 'ข้อมูลส่วนตัว', 'monk' => 'ข้อมูลสงฆ์', 'home' => 'ที่อยู่ตามทะเบียนบ้าน', 'cur' => 'ที่อยู่ปัจจุบัน', 'off' => 'ที่อยู่ปฏิบัติศาสนกิจ', 'par' => 'ผู้ปกครอง / บุคคลอ้างอิง', 'edu' => 'ประวัติการศึกษา', 'dhamma' => 'การศึกษานักธรรม', 'pali' => 'การศึกษาบาลี' );
	$h      = '';
	foreach ( $groups as $g => $glabel ) {
		$rows = '';
		foreach ( mcupr_sp_fields() as $k => $f ) {
			if ( $f[1] !== $g || ! isset( $vals[ $k ] ) || '' === $vals[ $k ] ) {
				continue;
			}
			$v = $vals[ $k ];
			if ( 'student' === $for && in_array( $k, array( 'idcard', 'passport' ), true ) ) {
				$v = str_repeat( '•', max( 0, mb_strlen( $v ) - 4 ) ) . mb_substr( $v, -4 );
			}
			if ( 'date' === $f[3] && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) ) {
				$v = 'th' === $lang ? (int) $m[3] . '/' . (int) $m[2] . '/' . ( (int) $m[1] + 543 ) : $v;
			}
			$rows .= '<div><dt>' . esc_html( 'th' === $lang ? $f[5] : $f[0] ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
		}
		if ( '' !== $rows ) {
			$h .= '<details class="sp-g"' . ( 'names' === $g || 'personal' === $g ? ' open' : '' ) . '><summary>' . esc_html( 'th' === $lang ? $th[ $g ] : $glabel ) . '</summary><dl class="stu-dl sp-dl">' . $rows . '</dl></details>';
		}
	}
	return $h;
}

add_action(
	'add_meta_boxes_mcupr_student',
	static function () {
		if ( current_user_can( MCUPR_STU_CAP ) ) {
			add_meta_box( 'mcupr_student_sp', __( 'Details from the central file', 'mcupr' ), 'mcupr_sp_box', MCUPR_STU, 'normal', 'default' );
		}
	}
);
function mcupr_sp_box( $post ) {
	$labels = mcupr_sp_labels( $post->ID );
	foreach ( $labels as $l ) {
		echo '<p style="margin:.25rem 0"><strong style="color:#614200">' . mcupr_feather_i( 'flag', 14 ) . ' ' . esc_html( $l ) . '</strong></p>';
	}
	$html = mcupr_sp_html( $post->ID, 'staff', 'th' === substr( determine_locale(), 0, 2 ) ? 'th' : 'en' );
	if ( '' === $html ) {
		echo '<p>' . esc_html__( 'No details from the central file for this student.', 'mcupr' ) . '</p>';
		return;
	}
	mcupr_audit( 'profileview', array( 'post' => (int) $post->ID, 'sid' => (string) get_post_meta( $post->ID, '_mcupr_s_sid', true ) ) );
	echo '<p class="description">' . esc_html__( 'Only staff with the student-data permission see this (the national ID number, blood group, date of birth, religion, height, weight, passport and the guardian’s income are kept encrypted). Opening it is written in the activity log. To change something, change it in the central system and import the file again.', 'mcupr' ) . '</p><style>.sp-g{margin:.5rem 0;border:1px solid #c3c4c7;border-radius:4px;background:#fff}.sp-g>summary{cursor:pointer;padding:8px 12px;font-weight:600}.sp-dl{margin:0;padding:4px 12px 12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:8px 16px}.sp-dl div{min-width:0}.sp-dl dt{font-size:12px;color:#50575e}.sp-dl dd{margin:0;overflow-wrap:anywhere}</style>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** The student's own details in the portal. */
function mcupr_sp_portal_html( $post_id ) {
	$lang = function_exists( 'mcupr_lang' ) ? mcupr_lang() : 'th';
	$html = mcupr_sp_html( $post_id, 'student', 'th' === $lang ? 'th' : 'en' );
	if ( '' === $html ) {
		return '';
	}
	return '<section class="stu-sec sp-sec" id="sp-mine" aria-labelledby="sp-mine-t"><h2 id="sp-mine-t" class="stu-h">' . esc_html( mcupr_t( 'sp_mine' ) ) . '</h2><p class="stu-help">' . esc_html( mcupr_t( 'sp_note' ) ) . '</p>' . $html . '</section>';
}

/* ------------------------------------------------------------------ the table of codes (staff) */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Faculty and programme codes', 'mcupr' ), __( 'Faculty and programme codes', 'mcupr' ), MCUPR_STU_CAP, 'mcupr-student-codes', 'mcupr_sp_codes_page' );
	}
);

/** The codes in use: kind => id => number of students. */
function mcupr_sp_codes_in_use() {
	global $wpdb;
	$out = array( 'faculty' => array(), 'program' => array() );
	foreach ( array( 'faculty' => '_mcupr_s_fac_id', 'program' => '_mcupr_s_prog_id' ) as $kind => $mk ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT meta_value AS id, COUNT(*) AS n FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' GROUP BY meta_value ORDER BY n DESC", $mk ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$out[ $kind ][ (string) $r->id ] = (int) $r->n;
		}
	}
	return $out;
}

function mcupr_sp_codes_page() {
	if ( ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$saved = isset( $_GET['applied'] ) ? absint( $_GET['applied'] ) : -1;
	$use   = mcupr_sp_codes_in_use();
	$map   = mcupr_sp_codes();
	echo '<div class="wrap"><h1>' . esc_html__( 'Faculty and programme codes', 'mcupr' ) . '</h1>';
	if ( $saved >= 0 ) {
		/* translators: %d: students changed */
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Saved. %d students were updated.', 'mcupr' ), $saved ) ) . '</p></div>';
	}
	echo '<p style="max-width:56rem">' . esc_html__( 'The central file has codes, not names. Give each code its name here; it is put on every student with that code now and at every later import. A code with no name leaves the faculty or programme of the student as it is (empty for a new student).', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_sp_codes">';
	wp_nonce_field( 'mcupr_sp_codes', 'mcupr_sp_nonce' );
	foreach ( array( 'faculty' => __( 'Faculties', 'mcupr' ), 'program' => __( 'Programmes', 'mcupr' ) ) as $kind => $title ) {
		$ids = array_unique( array_merge( array_keys( $use[ $kind ] ), array_keys( $map[ $kind ] ) ) );
		sort( $ids, SORT_NATURAL );
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( ! $ids ) {
			echo '<p>' . esc_html__( 'No codes yet. Import the central file first.', 'mcupr' ) . '</p>';
			continue;
		}
		echo '<table class="widefat striped" style="max-width:48rem"><thead><tr><th scope="col">' . esc_html__( 'Code', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Name', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Students', 'mcupr' ) . '</th></tr></thead><tbody>';
		foreach ( $ids as $id ) {
			$name = isset( $map[ $kind ][ $id ] ) ? $map[ $kind ][ $id ] : '';
			echo '<tr><td><code>' . esc_html( $id ) . '</code></td><td><label class="screen-reader-text" for="mcc-' . esc_attr( $kind . '-' . $id ) . '">' . esc_html( sprintf( /* translators: %s: code */ __( 'Name of code %s', 'mcupr' ), $id ) ) . '</label><input id="mcc-' . esc_attr( $kind . '-' . $id ) . '" name="' . esc_attr( $kind ) . '[' . esc_attr( $id ) . ']" type="text" class="regular-text" value="' . esc_attr( $name ) . '"' . ( '' === $name ? ' style="border-color:#b32d2e"' : '' ) . '></td><td>' . (int) ( isset( $use[ $kind ][ $id ] ) ? $use[ $kind ][ $id ] : 0 ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p><button class="button button-primary">' . esc_html__( 'Save and apply', 'mcupr' ) . '</button></p></form></div>';
}

add_action(
	'admin_post_mcupr_sp_codes',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_sp_nonce', 'mcupr_sp_codes', MCUPR_STU_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$new = array( 'faculty' => array(), 'program' => array() );
		foreach ( array( 'faculty', 'program' ) as $kind ) {
			$in = isset( $_POST[ $kind ] ) && is_array( $_POST[ $kind ] ) ? wp_unslash( $_POST[ $kind ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( array_slice( $in, 0, 400, true ) as $id => $name ) {
				$id = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $id );
				if ( '' !== $id && is_string( $name ) ) {
					$new[ $kind ][ $id ] = mb_substr( sanitize_text_field( $name ), 0, 120 );
				}
			}
		}
		update_option( 'mcupr_sp_codes', $new, false );
		$n = mcupr_sp_apply_codes();
		mcupr_audit( 'settings', array( 'note' => 'faculty and programme codes' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-student-codes&applied=' . $n ) );
		exit;
	}
);

/** Put the names of the table on every student that has the code. Returns how many students changed. */
function mcupr_sp_apply_codes() {
	global $wpdb;
	$n = 0;
	foreach ( array( 'faculty' => array( '_mcupr_s_fac_id', '_mcupr_s_faculty' ), 'program' => array( '_mcupr_s_prog_id', '_mcupr_s_major' ) ) as $kind => $m ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value AS id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", $m[0] ) ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$name = mcupr_sp_code_name( $kind, $r->id );
			if ( '' !== $name && (string) get_post_meta( $r->post_id, $m[1], true ) !== $name ) {
				update_post_meta( (int) $r->post_id, $m[1], $name );
				++$n;
			}
		}
	}
	if ( $n ) {
		mcupr_aud_reset(); // the choice of faculties for the students-only content is counted again
	}
	return $n;
}
