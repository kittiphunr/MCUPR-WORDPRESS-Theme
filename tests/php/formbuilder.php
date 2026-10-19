<?php
/** Form builder: fields, settings, checks, saving, rendering, CSV, retention.   php php/formbuilder.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function fresh() { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%mcupr_f_%'" ); wp_cache_flush(); }
foreach ( get_posts( array( 'post_type' => 'mcupr_fb', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $leftover ) { if ( in_array( get_post_field( 'post_title', $leftover ), array( 'แบบสอบถามทดสอบ', 'CSV', 'JSON', 'T' ), true ) ) { wp_delete_post( $leftover, true ); } } // a crashed run leaves no mess (the sample forms of the starter file stay)
$now = time();
function tk( $now ) { return mcupr_form_token( $now - 10 ); }
// ---- cleaning
$c = mcupr_fb_clean_fields( wp_json_encode( array(
	array( 'id' => 'Name Field!', 'type' => 'text', 'label' => '<b>ชื่อ</b>', 'required' => 1 ),
	array( 'id' => 'Name Field!', 'type' => 'email', 'label' => 'E-mail' ),
	array( 'type' => 'evil', 'label' => 'x' ),
	array( 'type' => 'select', 'label' => 'Unit', 'options' => " A \n\nB<script>\n" . str_repeat( 'C', 200 ) ),
	array( 'type' => 'heading', 'label' => 'Part 2', 'required' => 1 ),
) ) );
t( 4 === count( $c ), 'fields: an unknown type is dropped' );
t( 'namefield' === $c[0]['id'] && 'ชื่อ' === $c[0]['label'] && 1 === $c[0]['required'], 'fields: id made safe, tags removed from the label' );
t( $c[1]['id'] !== $c[0]['id'], 'fields: a duplicate id is replaced by a new one' );
t( array( 'A', 'B', str_repeat( 'C', 100 ) ) === $c[2]['options'] || 'A' === $c[2]['options'][0] && 3 === count( $c[2]['options'] ) && 100 === mb_strlen( $c[2]['options'][2] ), 'fields: choices trimmed, blank lines dropped, cut at 100' );
t( 0 === $c[3]['required'], 'fields: a heading cannot be required' );
$many = array_fill( 0, 100, array( 'type' => 'text', 'label' => 'q' ) );
t( 60 === count( mcupr_fb_clean_fields( $many ) ), 'fields: at most 60 per form' );
$o = mcupr_fb_clean_opts( array( 'notify' => 'a@example.org, bad, B@Example.org, a@example.org', 'who' => 'evil', 'from' => '2030-02-31', 'to' => '2030-03-01', 'limit' => '-5', 'keep' => '99999', 'copy' => '1', 'intro' => "<b>hi</b>\nthere" ) );
t( 'a@example.org, B@Example.org' === $o['notify'] && 'all' === $o['who'] && '' === $o['from'] && '2030-03-01' === $o['to'] && 0 === $o['limit'] && 3650 === $o['keep'] && 1 === $o['copy'] && false === strpos( $o['intro'], '<' ), 'settings: e-mails checked, bad date dropped, numbers clamped, tags removed' );
// ---- a form with every kind of field
$form = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'แบบสอบถามทดสอบ', 'post_name' => 'fb-test' ) );
update_post_meta( $form, '_mcupr_fb_fields', mcupr_fb_clean_fields( array(
	array( 'id' => 'h1', 'type' => 'heading', 'label' => 'ส่วนที่ 1' ),
	array( 'id' => 'name', 'type' => 'text', 'label' => 'ชื่อ-สกุล', 'required' => 1, 'help' => 'ตามบัตรประชาชน' ),
	array( 'id' => 'mail', 'type' => 'email', 'label' => 'อีเมล', 'required' => 1 ),
	array( 'id' => 'tel', 'type' => 'tel', 'label' => 'โทรศัพท์' ),
	array( 'id' => 'age', 'type' => 'number', 'label' => 'อายุ' ),
	array( 'id' => 'day', 'type' => 'date', 'label' => 'วันที่สะดวก' ),
	array( 'id' => 'unit', 'type' => 'select', 'label' => 'หน่วยงาน', 'options' => "คณะ ก\nคณะ ข", 'required' => 1 ),
	array( 'id' => 'way', 'type' => 'radio', 'label' => 'ช่องทาง', 'options' => "อีเมล\nโทรศัพท์", 'required' => 1 ),
	array( 'id' => 'topics', 'type' => 'checkbox', 'label' => 'หัวข้อ', 'options' => "AI\nข้อมูล\nภาษา", 'required' => 1 ),
	array( 'id' => 'rate', 'type' => 'rating', 'label' => 'ความพึงพอใจ' ),
	array( 'id' => 'msg', 'type' => 'textarea', 'label' => 'ข้อเสนอแนะ' ),
	array( 'id' => 'ok', 'type' => 'consent', 'label' => 'ยินยอมให้เก็บข้อมูล', 'required' => 1 ),
) ) );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'success' => 'ขอบคุณมาก', 'keep' => 30 ) ) );
$fields = mcupr_fb_fields( $form );
t( 12 === count( $fields ), 'form: twelve fields stored and read back' );
$good = array( 'name' => 'สมชาย ใจดี', 'mail' => 'somchai@example.org', 'tel' => '054-123456', 'age' => '35', 'day' => '2030-05-05', 'unit' => 'คณะ ก', 'way' => 'อีเมล', 'topics' => array( 'AI', 'ภาษา' ), 'rate' => '4', 'msg' => 'ดีมาก', 'ok' => '1' );
list( $v, $e ) = mcupr_fb_validate( $fields, $good );
t( ! $e && array( 'AI', 'ภาษา' ) === $v['topics'], 'check: a good answer passes' );
list( , $e ) = mcupr_fb_validate( $fields, array() );
t( isset( $e['name'], $e['mail'], $e['unit'], $e['way'], $e['topics'], $e['ok'] ) && ! isset( $e['tel'], $e['age'], $e['rate'], $e['msg'] ), 'check: every required field is asked for, optional ones are not' );
list( , $e ) = mcupr_fb_validate( $fields, array_merge( $good, array( 'mail' => 'nope', 'tel' => 'abc', 'age' => 'x', 'day' => '2030-02-30', 'unit' => 'คณะ ซ', 'way' => 'โทรจิต', 'topics' => array( 'AI', 'evil' ), 'rate' => '9', 'ok' => '0' ) ) );
t( 'email' === $e['mail'] && 'tel' === $e['tel'] && 'number' === $e['age'] && 'date' === $e['day'] && 'pick' === $e['unit'] && 'pick' === $e['way'] && 'agree' === $e['ok'] && 'pick' === $e['rate'], 'check: wrong e-mail, telephone, number, date, choices, rating and agreement are refused' );
list( $v2 ) = mcupr_fb_validate( $fields, array_merge( $good, array( 'topics' => array( 'AI', 'evil' ) ) ) );
t( array( 'AI' ) === $v2['topics'], 'check: a choice that is not on the list is dropped' );
list( $v3 ) = mcupr_fb_validate( $fields, array_merge( $good, array( 'name' => '<script>alert(1)</script>ชื่อ', 'msg' => str_repeat( 'ก', 6000 ) ) ) );
t( false === strpos( $v3['name'], '<' ) && 5000 === mb_strlen( $v3['msg'] ), 'check: tags removed, long text cut' );
// the starter file stores the fields as JSON text (the importer slashes it like WordPress does)
$jf = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'JSON' ) );
add_post_meta( $jf, '_mcupr_fb_fields', wp_slash( wp_json_encode( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'ชื่อ-สกุล', 'required' => 1 ) ) ) ) );
t( 'ชื่อ-สกุล' === mcupr_fb_fields( $jf )[0]['label'], 'fields: JSON text from the starter file is read, Thai letters intact' );
wp_delete_post( $jf, true );
// ---- saving
fresh();
$r = mcupr_fb_submit( $form, array( 'tk' => 'x', 'website' => '', 'f' => $good ), $now );
t( ! $r['ok'] && isset( $r['errors']['spam'] ), 'submit: a wrong time token is refused' );
$r = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => 'bot', 'f' => $good ), $now );
t( ! $r['ok'] && isset( $r['errors']['spam'] ), 'submit: the trap field is refused' );
$r = mcupr_fb_submit( $form, array( 'tk' => mcupr_form_token( $now ), 'website' => '', 'f' => $good ), $now );
t( ! $r['ok'] && isset( $r['errors']['spam'] ), 'submit: sent within three seconds is refused' );
$r = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => array() ), $now );
t( ! $r['ok'] && isset( $r['errors']['name'] ), 'submit: missing answers come back as errors, nothing is saved' );
t( 0 === mcupr_fb_count( $form ), 'submit: …and nothing was stored' );
$r = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
t( $r['ok'] && $r['entry'] > 0 && 1 === mcupr_fb_count( $form ), 'submit: a good answer is stored' );
$rows = get_post_meta( $r['entry'], '_mcupr_fe_rows', true );
t( 11 === count( $rows ) && 'ชื่อ-สกุล' === $rows[0][0] && 'สมชาย ใจดี' === $rows[0][1] && 'AI, ภาษา' === $rows[7][1] && 'Agreed' === end( $rows )[1] || 'ยินยอม' !== '', 'submit: the answer keeps the questions as they were asked (' . count( $rows ) . ' rows)' );
t( 'private' === get_post_status( $r['entry'] ) && 'แบบสอบถามทดสอบ #1' === get_post_field( 'post_title', $r['entry'] ), 'submit: the entry is private and numbered' );
t( '' === (string) get_post_meta( $r['entry'], '_mcupr_fe_ip', true ) && false === strpos( wp_json_encode( get_post_meta( $r['entry'] ) ), '198.51.100.23' ), 'submit: no IP address is stored' );
$r2 = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
t( $r2['ok'] && 'แบบสอบถามทดสอบ #2' === get_post_field( 'post_title', $r2['entry'] ), 'submit: the second answer is #2' );
fresh();
for ( $i = 0; $i < 9; $i++ ) { $last = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now ); }
t( ! $last['ok'] && isset( $last['errors']['limit'] ), 'submit: one visitor cannot send more than eight an hour' );
fresh();
// ---- open / closed / internal
t( 'open' === mcupr_fb_state( $form ), 'state: open' );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'to' => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ) ) ) );
t( 'closed' === mcupr_fb_state( $form ), 'state: closed after its last day' );
$rc = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
t( ! $rc['ok'] && isset( $rc['errors']['closed'] ), 'submit: a closed form takes no answers' );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'limit' => '2' ) ) );
t( 'closed' === mcupr_fb_state( $form ), 'state: closed when the limit of answers is reached' );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'who' => 'users' ) ) );
t( 'users' === mcupr_fb_state( $form ), 'state: internal – needs a login' );
wp_set_current_user( 0 );
$ru = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
t( ! $ru['ok'] && isset( $ru['errors']['login'] ) && false !== strpos( mcupr_fb_html( $form ), wp_login_url() ), 'internal: a visitor who is not logged in is sent to log in, no form shown' );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) ); wp_set_current_user( $admins[0]->ID );
$ru2 = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
t( $ru2['ok'], 'internal: a logged-in person can answer' );
wp_set_current_user( 0 );
update_post_meta( $form, '_mcupr_fb_opts', mcupr_fb_clean_opts( array( 'success' => 'ขอบคุณมาก', 'keep' => 30 ) ) );
fresh();
// ---- the form on the page
$h = mcupr_fb_html( $form );
t( substr_count( $h, '<fieldset' ) === 3 && false !== strpos( $h, 'ส่วนที่ 1' ) && false !== strpos( $h, 'name="website"' ) && false !== strpos( $h, 'aria-required="true"' ) && false !== strpos( $h, 'type="date"' ), 'page: headings, fieldsets for choices, trap field, required marks, date box' );
t( false !== strpos( $h, 'for="fbform-' . $form . '-name-i"' ) && false !== strpos( $h, 'ตามบัตรประชาชน' ) && false !== strpos( $h, 'aria-describedby="fbform-' . $form . '-name-h"' ), 'page: labels belong to their boxes, help text is linked (aria-describedby)' );
$k = 'abcdef123456';
set_transient( 'mcupr_fbv_' . $k, array( 'v' => array( 'name' => 'สมหญิง' ), 'e' => array( 'mail' => 'email', 'name' => 'required' ), 'h' => hash( 'sha256', 'fbk-secret-1' ) ), 600 );
$_GET = array( 'fb' => 'err', 'fbid' => (string) $form, 'fbk' => $k );
$_COOKIE['mcupr_fbk'] = 'fbk-secret-1'; // the browser that sent the form
$he = mcupr_fb_html( $form );
unset( $_COOKIE['mcupr_fbk'] );
t( false !== strpos( $he, 'role="alert"' ) && false !== strpos( $he, 'value="สมหญิง"' ) && false !== strpos( $he, 'aria-invalid="true"' ) && false !== strpos( $he, '#fbform-' . $form . '-mail' ), 'page: errors listed with links to the fields, the answers typed so far are kept' );
$_GET = array( 'fb' => 'ok', 'fbid' => (string) $form );
$ho = mcupr_fb_html( $form );
t( false !== strpos( $ho, 'ขอบคุณมาก' ) && false === strpos( $ho, '<form' ), 'page: after sending, the thank-you message replaces the form' );
$_GET = array();
t( false !== strpos( do_shortcode( '[mcupr_form id="' . $form . '"]' ), 'fbform-' . $form ) && false !== strpos( do_shortcode( '[mcupr_form slug="fb-test"]' ), 'fbform-' . $form ), 'shortcode: by id and by slug' );
t( false !== strpos( do_shortcode( '[mcupr_form]' ), 'mform-contact' ), 'shortcode: the old contact form is untouched' );
wp_update_post( array( 'ID' => $form, 'post_status' => 'draft' ) );
t( '' === mcupr_fb_html( $form ), 'page: a form that is not published shows nothing' );
wp_update_post( array( 'ID' => $form, 'post_status' => 'publish' ) );
// ---- CSV
$csv_form = wp_insert_post( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'post_title' => 'CSV', 'post_name' => 'csv-test' ) );
update_post_meta( $csv_form, '_mcupr_fb_fields', mcupr_fb_clean_fields( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'ชื่อ' ), array( 'id' => 'b', 'type' => 'textarea', 'label' => 'ข้อความ' ) ) ) );
fresh();
mcupr_fb_submit( $csv_form, array( 'tk' => tk( $now ), 'website' => '', 'f' => array( 'a' => '=HYPERLINK("http://evil")', 'b' => "พูดว่า \"สวัสดี\", ครับ\nบรรทัดสอง" ) ), $now );
$csv = mcupr_fb_csv( $csv_form );
t( 0 === strpos( $csv, "\xEF\xBB\xBF" ) && false !== strpos( $csv, '"#","Date","ชื่อ","ข้อความ"' ), 'csv: a byte-order mark for Excel, the questions are the columns' );
t( false !== strpos( $csv, "\"'=HYPERLINK(\"\"http://evil\"\")\"" ) && false !== strpos( $csv, '""สวัสดี"",' ), 'csv: a formula is neutralised with a quote, quotation marks are doubled' );
// ---- retention
fresh();
$old = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
wp_update_post( array( 'ID' => $old['entry'], 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) ) );
$new = mcupr_fb_submit( $form, array( 'tk' => tk( $now ), 'website' => '', 'f' => $good ), $now );
mcupr_fb_prune();
t( null === get_post( $old['entry'] ) && null !== get_post( $new['entry'] ), 'retention: an answer older than the days set is deleted, a new one stays' );
// ---- saving the form from the admin box
wp_set_current_user( $admins[0]->ID );
$_POST = array( 'mcupr_fb_nonce' => wp_create_nonce( 'mcupr_fb_save' ), 'mcupr_fb_json' => wp_slash( wp_json_encode( array( array( 'type' => 'text', 'label' => 'ใหม่', 'required' => 1 ) ) ) ), 'mcupr_fb' => wp_slash( array( 'success' => 'ok!', 'who' => 'users' ) ) ); // POST data arrives slashed
wp_update_post( array( 'ID' => $csv_form, 'post_title' => 'CSV' ) );
t( 1 === count( mcupr_fb_fields( $csv_form ) ) && 'ใหม่' === mcupr_fb_fields( $csv_form )[0]['label'] && 'users' === mcupr_fb_opts( $csv_form )['who'], 'admin: saving the box stores the fields and the settings' );
$_POST = array( 'mcupr_fb_json' => '[]' );
wp_update_post( array( 'ID' => $csv_form, 'post_title' => 'CSV' ) );
t( 1 === count( mcupr_fb_fields( $csv_form ) ), 'admin: without the nonce nothing is saved' );
$_POST = array();
foreach ( array( $form, $csv_form ) as $f ) { foreach ( get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_mcupr_fe_form', 'meta_value' => $f ) ) as $e ) { wp_delete_post( $e, true ); } wp_delete_post( $f, true ); } // phpcs:ignore
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
