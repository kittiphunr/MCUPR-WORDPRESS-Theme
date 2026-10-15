<?php
/**
 * Notice bar, social links, statistics, people directory, document centre, contact / complaint forms, hero slides.
 *   php php/suggestions.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function mods( $a ) { foreach ( $a as $k => $v ) { null === $v ? remove_theme_mod( $k ) : set_theme_mod( $k, $v ); } }
wp_set_current_user( 0 );

// ================================================================= notice bar
t( null === mcupr_notice_data(), 'notice: off by default' );
mods( array( 'mcupr_notice_on' => true, 'mcupr_notice_text_th' => 'ปิดทำการวันที่ 1 มกราคม', 'mcupr_notice_url' => 'https://example.org/n', 'mcupr_notice_style' => 'urgent' ) );
$n = mcupr_notice_data();
t( $n && 'urgent' === $n['style'] && ! $n['gray'], 'notice: on with text and style' );
ob_start(); mcupr_notice_bar(); $h = ob_get_clean();
t( strpos( $h, 'notice-urgent' ) !== false && strpos( $h, 'ปิดทำการ' ) !== false && strpos( $h, 'href="https://example.org/n"' ) !== false && strpos( $h, 'role="region"' ) !== false, 'notice: bar markup (region, link)' );
mods( array( 'mcupr_notice_from' => gmdate( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ) ) );
t( null === mcupr_notice_data(), 'notice: not shown before its start date' );
mods( array( 'mcupr_notice_from' => '2000-01-01', 'mcupr_notice_to' => '2001-01-01' ) );
t( null === mcupr_notice_data(), 'notice: hidden after its end date' );
mods( array( 'mcupr_notice_from' => null, 'mcupr_notice_to' => null, 'mcupr_notice_style' => 'evil<b>' ) );
t( 'notice' === mcupr_notice_data()['style'], 'notice: an unknown style falls back' );
mods( array( 'mcupr_notice_style' => 'mourning', 'mcupr_notice_gray' => true ) );
t( strpos( apply_filters( 'language_attributes', 'lang="th"' ), 'data-mourning="1"' ) !== false, 'notice: mourning + grey switches the whole site to black and white' );
mods( array( 'mcupr_notice_gray' => false ) );
t( strpos( apply_filters( 'language_attributes', 'lang="th"' ), 'data-mourning' ) === false, 'notice: grey is off unless asked for' );
mods( array( 'mcupr_notice_text_th' => '' ) );
t( null === mcupr_notice_data(), 'notice: no text, no bar' );
foreach ( array( 'mcupr_notice_on', 'mcupr_notice_text_th', 'mcupr_notice_url', 'mcupr_notice_style', 'mcupr_notice_gray' ) as $m ) { remove_theme_mod( $m ); }

// ================================================================= social links
ob_start(); mcupr_social_links(); t( '' === trim( ob_get_clean() ), 'social: nothing without addresses' );
mods( array( 'mcupr_social_facebook' => 'https://facebook.com/mcu', 'mcupr_social_line' => 'https://line.me/R/ti/p/@mcu', 'mcupr_social_x' => 'javascript:alert(1)' ) );
ob_start(); mcupr_social_links(); $h = ob_get_clean();
t( substr_count( $h, '<li>' ) === 2 && strpos( $h, 'aria-label="Facebook' ) !== false && strpos( $h, 'rel="noopener noreferrer"' ) !== false, 'social: only the networks with an address, labelled, safe rel' );
foreach ( array( 'mcupr_social_facebook', 'mcupr_social_line', 'mcupr_social_x' ) as $m ) { remove_theme_mod( $m ); }

// ================================================================= statistics
delete_option( 'mcupr_daily_views' );
mcupr_stats_hit(); mcupr_stats_hit();
$s = mcupr_stats_series( 7 );
t( 7 === count( $s ) && 2 === end( $s ) && 0 === reset( $s ), 'stats: views per day counted, empty days are zeros' );
$old = array( gmdate( 'Y-m-d', time() - 200 * DAY_IN_SECONDS ) => 5 );
update_option( 'mcupr_daily_views', $old + (array) get_option( 'mcupr_daily_views' ) ); mcupr_stats_hit();
t( count( get_option( 'mcupr_daily_views' ) ) <= 120 && ! isset( get_option( 'mcupr_daily_views' )[ key( $old ) ] ), 'stats: only the last 120 days are kept' );
$pp = wp_insert_post( array( 'post_title' => 'Popular', 'post_status' => 'publish' ) ); update_post_meta( $pp, '_mcupr_views', 99999 );
t( mcupr_stats_top_posts( 3 )[0]->ID === $pp && mcupr_stats_total_views() >= 99999, 'stats: the most viewed page is first; the total adds up' );
wp_set_current_user( 1 );
ob_start(); mcupr_stats_page(); $h = ob_get_clean();
t( strpos( $h, 'Popular' ) !== false && strpos( $h, 'mcupr-bars' ) !== false, 'stats: the statistics page lists top pages and the chart' );
wp_set_current_user( 0 );
wp_delete_post( $pp, true );

// ================================================================= people
t( post_type_exists( 'mcupr_person' ) && taxonomy_exists( 'mcupr_person_group' ), 'people: post type and groups exist' );
wp_set_current_user( 1 );
$g1 = wp_insert_term( 'กลุ่มทดสอบ', 'mcupr_person_group' );
$mk = function ( $name, $order, $grp ) use ( $g1 ) { $id = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_title' => $name, 'post_status' => 'publish', 'menu_order' => $order ) ); if ( $grp ) { wp_set_object_terms( $id, array( (int) $g1['term_id'] ), 'mcupr_person_group' ); } return $id; };
$a = $mk( 'ผศ.ดร.ทดสอบ หนึ่ง', 2, true ); $b = $mk( 'นายทดสอบ สอง', 1, true ); $c = $mk( 'Outsider', 1, false );
$_POST = array( 'mcupr_person_nonce' => wp_create_nonce( 'mcupr_person_save' ), 'mcupr_person' => array( 'role' => 'หัวหน้า <b>ฝ่าย</b>', 'dept' => 'ฝ่ายทดสอบ', 'email' => 'a@example.org', 'tel' => '02 111 2222', 'url' => 'javascript:x', 'role_en' => 'Head of Unit' ) );
mcupr_person_save( $a ); $_POST = array(); update_post_meta( $a, "_mcupr_person_hide", array() ); // the hub hides the telephone by default; this person shows everything
t( false === strpos( get_post_meta( $a, '_mcupr_person_role', true ), '<' ) && '' === get_post_meta( $a, '_mcupr_person_url', true ) && 'Head of Unit' === get_post_meta( $a, '_mcupr_en_role', true ), 'people: details saved, sanitised, role translation stored' );
wp_set_current_user( 0 );
$h = do_shortcode( '[mcupr_people group="' . get_term( $g1['term_id'] )->slug . '" columns="3"]' );
t( strpos( $h, 'นายทดสอบ สอง' ) < strpos( $h, 'ผศ.ดร.ทดสอบ' ) && strpos( $h, 'Outsider' ) === false, 'people: ordered by the list number; only the chosen group' );
t( strpos( $h, 'mailto:a@example.org' ) !== false && strpos( $h, 'tel:021112222' ) !== false && strpos( $h, 'people-cols-3' ) !== false, 'people: contact links and column class' );
t( preg_match( '/person-in[^>]*>ท</u', $h ) === 1, 'people: the initial skips the title (ผศ.ดร.) when there is no photo' );
t( strpos( do_shortcode( '[mcupr_people group="nope"]' ), 'people-grid' ) === false, 'people: an empty group says so' );
foreach ( array( $a, $b, $c ) as $x ) { wp_delete_post( $x, true ); } wp_delete_term( $g1['term_id'], 'mcupr_person_group' );

// ================================================================= document centre
$pid = wp_insert_post( array( 'post_title' => 'Doc host', 'post_status' => 'publish' ) );
$up1 = wp_upload_bits( 'mcupr-doc1.pdf', null, "%PDF-1.4\n" ); $at1 = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'x', 'post_status' => 'inherit' ), $up1['file'], $pid );
$up2 = wp_upload_bits( 'mcupr-doc2.xlsx', null, 'PK' ); $at2 = wp_insert_attachment( array( 'post_mime_type' => 'application/vnd.ms-excel', 'post_title' => 'y', 'post_status' => 'inherit' ), $up2['file'], $pid );
update_post_meta( $pid, '_mcupr_files', array( array( 'id' => $at1, 'title' => 'ประกาศจัดซื้อจัดจ้าง ๒๕๖๙', 'size' => '', 'max' => 0, 'count' => 4 ), array( 'id' => $at2, 'title' => 'แบบฟอร์มงบประมาณ', 'size' => '', 'max' => 1, 'count' => 1 ) ) );
$_GET = array();
$h = mcupr_documents_html( 20 );
t( strpos( $h, 'ประกาศจัดซื้อจัดจ้าง' ) !== false && strpos( $h, 'แบบฟอร์มงบประมาณ' ) !== false && strpos( $h, '<table class="docs">' ) !== false, 'documents: files of every post are listed in a table' );
t( strpos( $h, 'mcupr_dl=' . $pid . '-0' ) !== false && strpos( $h, 'file-full' ) !== false, 'documents: download link, and "limit reached" for the full one' );
$_GET = array( 'dq' => 'งบประมาณ' ); $h = mcupr_documents_html( 20 );
t( strpos( $h, 'แบบฟอร์มงบประมาณ' ) !== false && strpos( $h, 'ประกาศจัดซื้อ' ) === false, 'documents: search by words' );
$_GET = array( 'dt' => 'pdf' ); $h = mcupr_documents_html( 20 );
t( strpos( $h, 'ประกาศจัดซื้อ' ) !== false && strpos( $h, 'แบบฟอร์มงบประมาณ' ) === false, 'documents: filter by file type' );
$_GET = array( 'dq' => '"><script>alert(1)</script>' ); t( strpos( mcupr_documents_html( 20 ), '<script>alert' ) === false, 'documents: the search text is escaped' );
$_GET = array();
$pw = wp_insert_post( array( 'post_title' => 'Locked doc', 'post_status' => 'publish', 'post_password' => 'x' ) ); update_post_meta( $pw, '_mcupr_files', array( array( 'id' => $at1, 'title' => 'ลับมาก', 'size' => '', 'max' => 0, 'count' => 0 ) ) );
t( strpos( mcupr_documents_html( 20 ), 'ลับมาก' ) === false, 'documents: files of password-protected posts are not listed' );
foreach ( array( $pid, $pw, $at1, $at2 ) as $x ) { wp_delete_post( $x, true ); }

// ================================================================= forms
$now = time();
$base = function ( $over = array() ) use ( $now ) { return array_merge( array( 'type' => 'contact', 'tk' => mcupr_form_token( $now - 10 ), 'website' => '', 'name' => 'สมชาย ใจดี', 'email' => 'somchai@example.org', 'phone' => '081', 'kind' => 'contact', 'subject' => 'ถามเรื่องรับสมัคร', 'message' => 'อยากทราบกำหนดการรับสมัครนักศึกษาใหม่ครับ', 'consent' => '1' ), $over ); };
delete_transient( mcupr_form_visitor_key() );
$r = mcupr_form_process( $base(), $now );
t( $r['ok'] && preg_match( '/^MC-\d{8}-\d+$/', $r['ref'] ), 'form: a valid message is accepted and gets a reference number (' . $r['ref'] . ')' );
$ids = get_posts( array( 'post_type' => 'mcupr_msg', 'post_status' => 'private', 'numberposts' => 1 ) );
t( $ids && 'somchai@example.org' === get_post_meta( $ids[0]->ID, '_mcupr_msg_email', true ) && false !== strpos( $ids[0]->post_content, 'รับสมัคร' ), 'form: the message is saved for the editors (admin menu "Form messages")' );
t( ! mcupr_form_process( $base( array( 'website' => 'http://spam' ) ), $now )['ok'], 'form: the hidden trap field rejects robots' );
t( ! mcupr_form_process( $base( array( 'tk' => mcupr_form_token( $now - 1 ) ) ), $now )['ok'], 'form: a form filled in under 3 seconds is rejected' );
t( ! mcupr_form_process( $base( array( 'tk' => mcupr_form_token( $now - 4 * HOUR_IN_SECONDS ) ) ), $now )['ok'], 'form: a form older than 2 hours is rejected' );
t( ! mcupr_form_process( $base( array( 'tk' => ( $now - 10 ) . '.forged' ) ), $now )['ok'], 'form: a forged time token is rejected' );
$e = mcupr_form_process( $base( array( 'name' => '', 'email' => 'bad', 'message' => 'สั้น', 'consent' => '' ) ), $now )['errors'];
t( isset( $e['name'], $e['email'], $e['message'], $e['consent'] ), 'form: each missing / invalid field is reported' );
delete_transient( mcupr_form_visitor_key() );
$ok = 0; for ( $i = 0; $i < 7; $i++ ) { if ( mcupr_form_process( $base(), $now )['ok'] ) { $ok++; } }
t( 5 === $ok, 'form: at most 5 messages per visitor per hour (accepted ' . $ok . ')' );
delete_transient( mcupr_form_visitor_key() );
$c = mcupr_form_process( $base( array( 'type' => 'complaint', 'kind' => 'corrupt' ) ), $now );
$cm = get_posts( array( 'post_type' => 'mcupr_msg', 'post_status' => 'private', 'numberposts' => 1, 'meta_key' => '_mcupr_msg_ref', 'meta_value' => $c['ref'] ) );
t( $c['ok'] && $cm && 'complaint' === get_post_meta( $cm[0]->ID, '_mcupr_msg_type', true ) && false !== strpos( $cm[0]->post_title, 'ทุจริต' ), 'form: a complaint keeps its type and category' );
$h = mcupr_form_html( 'complaint' );
t( strpos( $h, 'name="website"' ) !== false && strpos( $h, 'name="consent"' ) !== false && strpos( $h, 'name="_wpnonce"' ) !== false && strpos( $h, 'action="' ) !== false && strpos( $h, 'ร้องเรียนการให้บริการ' ) !== false, 'form: markup has the trap, consent, nonce and the complaint categories' );
t( preg_match_all( '/<label[^>]*for="(mform-complaint-[a-z]+)"/', $h, $m ) && count( array_unique( $m[1] ) ) >= 4 && strpos( $h, 'id="mform-complaint-name"' ) !== false, 'form: every field has a label' );
$_GET = array( 'mf' => 'ok', 'ref' => 'MC-20260101-7"<x>' );
$h = mcupr_form_html( 'contact' ); t( strpos( $h, 'role="status"' ) !== false && strpos( $h, '<x>' ) === false && strpos( $h, 'MC-20260101-7' ) !== false, 'form: the success message shows the (cleaned) reference' );
$_GET = array( 'mf' => 'err', 'mfe' => 'name,email,<script>' );
$h = mcupr_form_html( 'contact' ); t( strpos( $h, 'role="alert"' ) !== false && strpos( $h, '<script>' ) === false && strpos( $h, 'aria-invalid="true"' ) !== false, 'form: errors are announced and the fields marked' );
$_GET = array();
foreach ( get_posts( array( 'post_type' => 'mcupr_msg', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $x ) { wp_delete_post( $x, true ); }
delete_transient( mcupr_form_visitor_key() );

// ================================================================= hero slides
t( array() === mcupr_hero_extra_slides(), 'hero: no extra slides by default' );
$up = wp_upload_bits( 'mcupr-hero2.jpg', null, '' ); $im = imagecreatetruecolor( 1200, 600 ); imagejpeg( $im, $up['file'], 70 );
$sl = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 's2', 'post_status' => 'inherit' ), $up['file'] ); wp_update_attachment_metadata( $sl, wp_generate_attachment_metadata( $sl, $up['file'] ) );
mods( array( 'mcupr_hero2_image' => $sl, 'mcupr_hero2_title_th' => 'สไลด์สอง', 'mcupr_hero2_btn_th' => 'ไป', 'mcupr_hero2_url' => 'https://example.org/2' ) );
$x = mcupr_hero_extra_slides();
t( 1 === count( $x ) && 'สไลด์สอง' === $x[0]['title'] && 'https://example.org/2' === $x[0]['url'], 'hero: a slide with a photo is picked up with its text and link' );
mods( array( 'mcupr_hero3_image' => 99999999 ) ); t( 1 === count( mcupr_hero_extra_slides() ), 'hero: a slide whose photo does not exist is skipped' );
foreach ( array( 'mcupr_hero2_image', 'mcupr_hero2_title_th', 'mcupr_hero2_btn_th', 'mcupr_hero2_url', 'mcupr_hero3_image' ) as $m ) { remove_theme_mod( $m ); } wp_delete_post( $sl, true );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
