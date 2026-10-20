<?php
/** Work groups of an office (v1.54.0): the post type and its office, the address (pretty and Plain), the parts that show, people and news of a group, the office home, Office editors, cleaning what is typed, the contact card.   php php/office-groups.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$_SERVER['HTTP_HOST'] = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost:8090';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$bad = function ( $h ) { return (bool) preg_match( '/(Warning|Notice|Deprecated|Fatal error|Parse error)/', $h ); };
wp_set_current_user( 1 );
$made = array();
$mk   = function ( $args ) use ( &$made ) { $id = wp_insert_post( array_merge( array( 'post_status' => 'publish' ), $args ) ); $made[] = $id; return $id; };

// ================================================================= 1. the post type, owned by an office
t( post_type_exists( 'mcupr_group' ) && in_array( 'mcupr_group', mcupr_office_types(), true ) && is_object_in_taxonomy( 'mcupr_group', MCUPR_OFFICE_TAX ), 'post type mcupr_group is registered and is one of the kinds of content an office owns (it carries the office mark)' );
$pto = get_post_type_object( 'mcupr_group' );
t( $pto->public && false === $pto->rewrite && 'mcupr_group' === $pto->query_var && 'page' === $pto->capability_type, 'public, no rewrite of its own (the address is the office\'s), query var mcupr_group, edited with the rights of pages' );
$O1 = $mk( array( 'post_type' => 'mcupr_office', 'post_title' => 'สำนักงานทดสอบกลุ่มงาน', 'post_name' => 'og-office-one' ) );
$O2 = $mk( array( 'post_type' => 'mcupr_office', 'post_title' => 'Office two (groups test)', 'post_name' => 'og-office-two' ) );
update_post_meta( $O1, '_mcupr_of_color', '#1d4ed8' );
$G1 = $mk( array( 'post_type' => 'mcupr_group', 'post_title' => 'กลุ่มงานการเงินทดสอบ', 'post_name' => 'og-finance', 'post_excerpt' => 'เบิกจ่ายและค่าธรรมเนียม', 'menu_order' => 2 ) );
$G2 = $mk( array( 'post_type' => 'mcupr_group', 'post_title' => 'กลุ่มงานบริหารทั่วไปทดสอบ', 'post_name' => 'og-general', 'menu_order' => 1 ) );
$G3 = $mk( array( 'post_type' => 'mcupr_group', 'post_title' => 'Group of office two', 'post_name' => 'og-two' ) );
mcupr_group_set_office( $G1, $O1 ); mcupr_group_set_office( $G2, $O1 ); mcupr_group_set_office( $G3, $O2 );
t( $O1 === mcupr_office_of( $G1 ) && $O1 === (int) get_post_meta( $G1, '_mcupr_g_office', true ) && $O1 === mcupr_group_office( $G1 ) && $O2 === mcupr_group_office( $G3 ), 'a group belongs to one office: the office mark and _mcupr_g_office agree' );
mcupr_office_assign( $G3, $O1 );
t( $O1 === (int) get_post_meta( $G3, '_mcupr_g_office', true ), 'moving the mark to another office moves _mcupr_g_office with it' );
mcupr_group_set_office( $G3, $O2 );

// ================================================================= 2. the address, pretty and Plain
global $wp_rewrite;
$pretty = get_option( 'permalink_structure' );
if ( ! $pretty ) { update_option( 'permalink_structure', '/%postname%/' ); $wp_rewrite->init(); }
$u1 = get_permalink( $G1 );
t( (bool) preg_match( '#/office/og-office-one/group/og-finance/$#', $u1 ), 'pretty permalinks: /office/<office>/group/<group>/ (' . $u1 . ')' );
$rules = (array) get_option( 'rewrite_rules' );
t( isset( $rules['^office/([^/]+)/group/([^/]+)/?$'] ) && false !== strpos( $rules['^office/([^/]+)/group/([^/]+)/?$'], 'mcupr_gof=' ), 'the rewrite rule of the group address is stored (one flush through mcupr_rewrite_wanted)' );
t( isset( $GLOBALS['mcupr_rewrite_wanted']['mcupr_rewrite_groups'] ), 'the rule asks for its flush with mcupr_rewrite_wanted()' );
t( $G1 === url_to_postid( $u1 ), 'the pretty address leads back to the group' );
$draft = $mk( array( 'post_type' => 'mcupr_group', 'post_status' => 'draft', 'post_title' => 'ร่างกลุ่มงาน' ) );
mcupr_group_set_office( $draft, $O1 );
t( false !== strpos( get_permalink( $draft ), 'post_type=mcupr_group' ) && false !== strpos( get_permalink( $draft ), 'p=' . $draft ), 'a draft (no slug yet) gets the ?post_type=mcupr_group&p=<id> address' );
update_option( 'permalink_structure', '' ); $wp_rewrite->init();
$up = get_permalink( $G1 );
t( mcupr_plain_mode() && false !== strpos( $up, 'post_type=mcupr_group' ) && false !== strpos( $up, 'p=' . $G1 ) && false === strpos( $up, '/office/' ), 'Plain permalinks: ?post_type=mcupr_group&p=<id> (' . $up . ')' );
$pl = mcupr_plain_url( home_url( '/office/og-office-one/group/og-finance/' ) );
t( false !== strpos( $pl, 'p=' . $G1 ), 'Plain permalinks: a typed /office/<office>/group/<group>/ link is turned into the plain address (' . $pl . ')' );
t( false !== strpos( mcupr_group_news_url( $G1 ), 'mcupr_osec=news' ) && false !== strpos( mcupr_group_news_url( $G1 ), 'ogroup=' . $G1 ), 'Plain permalinks: "all news of the group" opens the office news with ?ogroup=<id>' );
update_option( 'permalink_structure', $pretty ? $pretty : '/%postname%/' ); $wp_rewrite->init();
t( false !== strpos( mcupr_group_news_url( $G1 ), '/office/og-office-one/news/?ogroup=' . $G1 ), 'pretty permalinks: /office/<office>/news/?ogroup=<id>' );
if ( ! $pretty ) { update_option( 'permalink_structure', '' ); $wp_rewrite->init(); }

// ================================================================= 3. the parts that show (pure) and on a real group
t( array( 'services', 'news', 'contact' ) === mcupr_group_sections_pick( array(), array( 'about' => false, 'services' => true, 'news' => true, 'people' => false, 'docs' => false, 'contact' => true ) ), 'pick: only the parts that have something, in the page order' );
t( array( 'news' ) === mcupr_group_sections_pick( array( 'services', 'contact' ), array( 'services' => true, 'news' => true, 'contact' => true ) ), 'pick: a part switched off is left out even when it has content' );
t( array() === mcupr_group_sections_pick( array(), array() ) && array( 'about', 'services', 'news', 'people', 'docs', 'contact' ) === mcupr_group_sections_pick( array(), array_fill_keys( mcupr_group_section_keys(), true ) ), 'pick: nothing → no part; everything → all six in order' );
$empty = array( 'tel' => '', 'email' => '', 'place' => '', 'hours' => array() );
t( ! mcupr_group_has_contact( $empty ) && mcupr_group_has_contact( $empty, true ) && mcupr_group_has_contact( array_merge( $empty, array( 'hours' => array( 'จันทร์–ศุกร์' ) ) ) ), 'contact: hidden with nothing, shown with only a form or only hours' );
t( array() === mcupr_group_sections( $G2 ), 'a new group with nothing in it shows no part at all' );

// ================================================================= 4. cleaning what is typed (services, documents)
$rows = array();
for ( $i = 1; $i <= 11; $i++ ) { $rows[] = array( 'title' => 'บริการ ' . $i, 'text' => 'ข้อความ', 'icon' => 'dollar-sign', 'url' => '/x/' ); }
$rows[0]  = array( 'title' => '<b>ชำระ</b>ค่าธรรมเนียม', 'text' => '<script>x</script>สั้นๆ', 'icon' => 'skull', 'url' => 'javascript:alert(1)' );
$rows[1]  = array( 'title' => '', 'text' => 'no title', 'icon' => 'grid', 'url' => 'https://example.org/' );
$rows[2]  = array( 'title' => 'ลิงก์ดี', 'icon' => 'help-circle', 'url' => 'https://example.org/a b?x=<y>' );
$rows[3]  = 'not a row';
$sv = mcupr_group_clean_services( $rows );
t( 8 === count( $sv ), 'services: at most 8 cards are kept (' . count( $sv ) . ' of 11)' );
t( 'ชำระค่าธรรมเนียม' === $sv[0]['title'] && false === strpos( $sv[0]['text'], '<' ) && '' === $sv[0]['url'], 'services: tags removed, a javascript: link dropped' );
t( 'file-text' === $sv[0]['icon'] && 'help-circle' === $sv[1]['icon'] && ! array_diff( wp_list_pluck( $sv, 'icon' ), array_keys( mcupr_group_icons() ) ), 'services: an icon not in the list becomes the default, every icon is one of the list' );
t( 12 === count( mcupr_group_icons() ) && ! array_diff( array_keys( mcupr_group_icons() ), array_keys( mcupr_feather_set() ) ), 'services: the 12 icons of the list are all in the Feather set' );
t( 'ลิงก์ดี' === $sv[1]['title'] && 0 === strpos( $sv[1]['url'], 'https://example.org/a' ) && false === strpos( $sv[1]['url'], '<' ) && false === strpos( $sv[1]['url'], ' ' ), 'services: a row without a title is dropped; links are escaped (' . $sv[1]['url'] . ')' );
t( 'mailto:a@example.org' === mcupr_group_clean_url( 'mailto:a@example.org' ) && '' === mcupr_group_clean_url( 'data:text/html,x' ) && '/forms/' === mcupr_group_clean_url( '/forms/' ), 'links: mailto: and paths of the site are kept, data: is dropped' );
$att = wp_insert_attachment( array( 'post_title' => 'แบบคำร้องขอคืนเงิน', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ), 'og-refund.pdf', 0 );
$made[] = $att;
$dc = mcupr_group_clean_docs( array( array( 'title' => '', 'att' => (string) $att, 'url' => 'https://ignored.example/' ), array( 'title' => 'ตารางค่าธรรมเนียม', 'att' => '999999', 'url' => 'https://example.org/fees.docx' ), array( 'title' => 'bad', 'url' => 'javascript:alert(1)' ), array( 'title' => 'nothing' ) ) );
t( 2 === count( $dc ) && $att === $dc[0]['att'] && '' === $dc[0]['url'] && 'แบบคำร้องขอคืนเงิน' === $dc[0]['title'] && 0 === $dc[1]['att'] && 'https://example.org/fees.docx' === $dc[1]['url'], 'documents: a file of the Media Library (its title when none is typed), or a link; a missing file, a javascript: link and an empty row are dropped' );
t( 'PDF' === mcupr_group_file_kind( '/up/2026/x.pdf' ) && 'DOCX' === mcupr_group_file_kind( 'https://example.org/a.docx?v=2' ) && '' === mcupr_group_file_kind( 'https://example.org/page/' ), 'documents: the kind is read from the file name' );

// saving the group screen
$_POST = array( 'mcupr_group_nonce' => wp_create_nonce( 'mcupr_group_save' ), 'mcupr_g' => array(
	'show' => array( 'services', 'news', 'people', 'docs', 'contact' ), 'name_en' => '<i>Finance</i> Group', 'tel' => '054-000-011', 'email' => 'not an email', 'place' => 'อาคาร 1 ห้อง 105', 'hours' => "จันทร์–ศุกร์ 08.30–16.30 น.\n\n  พักเที่ยง 12.00–13.00 น.  \n", 'form' => '0',
	'services' => $rows, 'docs' => array( array( 'title' => 'แบบคำร้อง', 'att' => (string) $att ), array( 'title' => 'ตาราง', 'url' => 'https://example.org/fees.docx' ) ),
) );
do_action( 'save_post_mcupr_group', $G1, get_post( $G1 ), true );
$_POST = array();
$d = mcupr_group_data( $G1 );
t( 'Finance Group' === $d['name_en'] && '' === $d['email'] && array( 'จันทร์–ศุกร์ 08.30–16.30 น.', 'พักเที่ยง 12.00–13.00 น.' ) === $d['hours'] && 8 === count( $d['services'] ) && 2 === count( $d['docs'] ) && array() === $d['off'], 'saving: tags removed, a bad e-mail dropped, one line per opening hour, 8 services, 2 documents, every part on' );
$_POST = array( 'mcupr_g' => array( 'name_en' => 'hack' ) );
do_action( 'save_post_mcupr_group', $G1, get_post( $G1 ), true );
$_POST = array();
t( 'Finance Group' === mcupr_group_data( $G1 )['name_en'], 'saving without the nonce changes nothing' );
$docs = mcupr_group_docs( $G1 );
t( 2 === count( $docs ) && 'PDF' === $docs[0]['kind'] && $docs[0]['time'] > 0 && 'DOCX' === $docs[1]['kind'] && 0 === $docs[1]['time'], 'documents to show: the kind and date of a library file, the kind of a link' );

// ================================================================= 5. people and news of a group
$P1 = $mk( array( 'post_type' => 'mcupr_person', 'post_title' => 'นางสมศรี หัวหน้าดี', 'menu_order' => 5 ) );
$P2 = $mk( array( 'post_type' => 'mcupr_person', 'post_title' => 'นายวรเดช ใจดี', 'menu_order' => 1 ) );
$P3 = $mk( array( 'post_type' => 'mcupr_person', 'post_title' => 'คนของสำนักงานอื่น' ) );
$N1 = $mk( array( 'post_type' => 'post', 'post_title' => 'ประกาศของกลุ่มงานการเงิน 1', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ) ) );
$N2 = $mk( array( 'post_type' => 'post', 'post_title' => 'ประกาศของกลุ่มงานการเงิน 2', 'post_date' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
$N3 = $mk( array( 'post_type' => 'post', 'post_title' => 'ข่าวของสำนักงานที่ไม่อยู่ในกลุ่มงาน' ) );
foreach ( array( $P1, $P2, $N1, $N2, $N3 ) as $id ) { mcupr_office_assign( $id, $O1 ); }
mcupr_office_assign( $P3, $O2 );
t( mcupr_group_mark( $P1, $G1, true ) && mcupr_group_mark( $P2, $G1, false ) && mcupr_group_mark( $N1, $G1 ) && mcupr_group_mark( $N2, $G1 ), 'people and news of the office are marked with the group' );
t( ! mcupr_group_mark( $P3, $G1 ) && '' === get_post_meta( $P3, '_mcupr_person_group', true ), 'a person of another office cannot be marked with the group' );
t( ! mcupr_group_mark( $N3, $O1 ) && ! mcupr_group_mark( $N3, $N1 ) && 0 === mcupr_group_of( $N3 ), 'only a work group can be chosen (not an office, not a post)' );
$pp = wp_list_pluck( mcupr_group_people( $G1 ), 'ID' );
t( array( $P1, $P2 ) === array_map( 'intval', $pp ), 'people of the group: the head first, then by order (head ' . $P1 . ', then ' . $P2 . ')' );
t( array( $N2, $N1 ) === array_map( 'intval', wp_list_pluck( mcupr_group_news( $G1 ), 'ID' ) ), 'news of the group: its own posts, newest first, not the office\'s other news' );
t( array( 'services', 'news', 'people', 'docs', 'contact' ) === mcupr_group_sections( $G1 ), 'the group now shows services, news, people, documents and contact' );
update_post_meta( $G1, '_mcupr_g_off', array( 'people' ) );
t( ! in_array( 'people', mcupr_group_sections( $G1 ), true ), 'switching "people" off hides the part' );
delete_post_meta( $G1, '_mcupr_g_off' );
$cnt = mcupr_group_people_counts( $O1 );
t( isset( $cnt[ $G1 ] ) && 2 === $cnt[ $G1 ] && ! isset( $cnt[ $G2 ] ) && is_array( get_transient( 'mcupr_group_counts' ) ), 'people counts: 2 in the finance group, none in the other, kept in a transient' );
global $wpdb;
$q0 = $wpdb->num_queries; mcupr_group_people_counts( $O1 ); $q1 = $wpdb->num_queries;
t( $q1 - $q0 <= 1, 'people counts: read again without counting again (' . ( $q1 - $q0 ) . ' queries)' );
mcupr_group_mark( $P2, 0 );
t( false === get_transient( 'mcupr_group_counts' ) && 1 === mcupr_group_people_counts( $O1 )[ $G1 ], 'people counts: a change of the mark forgets the kept numbers' );
mcupr_group_mark( $P2, $G1, false );
// a person moved to another office loses the group when saved
mcupr_office_assign( $P2, $O2 );
wp_update_post( array( 'ID' => $P2, 'post_title' => 'นายวรเดช ใจดี' ) );
t( 0 === mcupr_group_of( $P2 ), 'a person moved to another office loses the group of the old office when saved' );
mcupr_office_assign( $P2, $O1 ); mcupr_group_mark( $P2, $G1, false );
// the meta box of a person
$_POST = array( 'mcupr_wg_nonce' => wp_create_nonce( 'mcupr_wg_save' ), 'mcupr_wg' => (string) $G1, 'mcupr_wg_head' => '1' );
wp_update_post( array( 'ID' => $P2, 'post_title' => 'นายวรเดช ใจดี' ) );
t( $G1 === mcupr_group_of( $P2 ) && mcupr_group_is_head( $P2 ), 'the "Work group" box of a person saves the group and the head tick' );
$_POST = array( 'mcupr_wg_nonce' => wp_create_nonce( 'mcupr_wg_save' ), 'mcupr_wg' => (string) $G1 );
wp_update_post( array( 'ID' => $P2, 'post_title' => 'นายวรเดช ใจดี' ) );
$_POST = array();
t( ! mcupr_group_is_head( $P2 ), 'unticking "head of the group" saves it' );

// ================================================================= 6. the page
global $wp_query, $wp_the_query, $post;
$wp_query = $wp_the_query = new WP_Query( array( 'post_type' => 'mcupr_group', 'p' => $G1 ) );
$wp_query->the_post();
ob_start(); mcupr_group_render( $G1 ); $h = ob_get_clean();
t( ! $bad( $h ) && 1 === substr_count( $h, '<h1 ' ) && false !== strpos( $h, '<h1 class="gl-name">กลุ่มงานการเงินทดสอบ</h1>' ), 'page: no PHP errors, one h1 – the name of the group' );
t( 5 === substr_count( $h, '<h2 id="gl-' ) && false === strpos( $h, '<h4' ), 'page: an h2 for each of the five parts (and no heading skips a level)' );
foreach ( array( 'services', 'news', 'people', 'docs', 'contact' ) as $k ) {
	t( false !== strpos( $h, '<section id="gl-' . $k . '"' ) && false !== strpos( $h, 'href="#gl-' . $k . '"' ), 'page: part "' . $k . '" and its link in the in-page menu' );
}
t( false !== strpos( $h, 'class="gl-ask" href="#gl-contact"' ) && false !== strpos( $h, 'aria-label="' . esc_attr( mcupr_t( 'gr_on_page' ) ) . '"' ), 'page: the last item of the menu, "Ask the staff", goes to the contact part; the menu is a named nav' );
$tr = mcupr_office_trail( mcupr_group_office( $G1 ), $G1 );
t( false !== strpos( $h, '--of:#1d4ed8' ) && false === strpos( $h, 'gl-crumb' ) && false !== strpos( $tr, 'aria-label="' . esc_attr( mcupr_t( 'breadcrumb' ) ) . '"' ) && false !== strpos( $tr, '>สำนักงานทดสอบกลุ่มงาน</a>' ) && false !== strpos( $tr, '<span aria-current="page">กลุ่มงานการเงินทดสอบ</span>' ), 'page: the office colour; the way home › Offices › office › group is the trail in the office band (not a second one on the page)' );
t( strpos( $h, 'นางสมศรี หัวหน้าดี' ) < strpos( $h, 'นายวรเดช ใจดี' ) && 1 === substr_count( $h, 'gl-person is-head' ) && false !== strpos( $h, mcupr_t( 'gr_head' ) ), 'page: the head comes first, on the larger card, with the label' );
t( false !== strpos( $h, 'ogroup=' . $G1 ) && false !== strpos( $h, 'ประกาศของกลุ่มงานการเงิน 2' ) && false === strpos( $h, 'ข่าวของสำนักงานที่ไม่อยู่ในกลุ่มงาน' ), 'page: the group\'s news with the link to all its news in the office' );
t( false !== strpos( $h, 'class="gl-btn"' ) && false !== strpos( $h, 'PDF' ) && 8 === substr_count( $h, '<li class="gl-card gl-svc-i' ), 'page: download buttons with the kind of file, 8 service cards' );
t( false !== strpos( $h, 'tel:054000011' ) && false === strpos( $h, 'gl-write' ), 'page: the telephone; no "write to us" card (no form, no e-mail)' );
t( false === strpos( $h, 'javascript:' ) && ! preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $h ), 'page: no javascript: link and no emoji' );

// ================================================================= 7. the contact card: a form, or an e-mail button, or nothing
$form = $mk( array( 'post_type' => 'mcupr_fb', 'post_title' => 'ส่งเรื่องถึงกลุ่มงาน (ทดสอบ)' ) );
update_post_meta( $form, '_mcupr_fb_fields', array( array( 'id' => 'f1', 'type' => 'heading', 'label' => 'ส่วนที่ 1' ), array( 'id' => 'f2', 'type' => 'text', 'label' => 'ชื่อ', 'required' => 1 ) ) );
$c = mcupr_group_data( $G1 );
$c['email'] = 'finance@example.org';
$h = mcupr_group_part_contact( $c );
t( false !== strpos( $h, 'mailto:finance@example.org' ) && false !== strpos( $h, 'gl-write' ) && false === strpos( $h, 'fbform' ), 'contact: with an e-mail and no form, a "write an e-mail" button' );
$c['form'] = $form;
$h = mcupr_group_part_contact( $c );
t( mcupr_group_form_ok( $form ) && false !== strpos( $h, 'fbform-' . $form ) && false !== strpos( $h, 'gl-write is-form' ) && false === strpos( $h, '<h2' ), 'contact: a chosen form is shown instead (its headings below the h3, no h2 inside)' );
wp_update_post( array( 'ID' => $form, 'post_status' => 'draft' ) );
$h = mcupr_group_part_contact( $c );
t( ! mcupr_group_form_ok( $form ) && false === strpos( $h, 'fbform-' ) && false !== strpos( $h, 'mailto:finance@example.org' ), 'contact: a form that is not published is not shown (the e-mail button comes back)' );
$c = array_merge( $c, array( 'email' => '', 'tel' => '', 'place' => '', 'hours' => array(), 'form' => 0 ) );
t( false === strpos( mcupr_group_part_contact( $c ), 'gl-card' ) && ! mcupr_group_has_contact( $c, false ), 'contact: nothing at all → the part is hidden' );
$_POST = array( 'mcupr_group_nonce' => wp_create_nonce( 'mcupr_group_save' ), 'mcupr_g' => array( 'show' => mcupr_group_toggle_keys(), 'form' => (string) $N1, 'services' => array(), 'docs' => array() ) );
do_action( 'save_post_mcupr_group', $G2, get_post( $G2 ), true );
$_POST = array();
t( 0 === mcupr_group_data( $G2 )['form'], 'saving: a "form" that is not a form of the form builder is not kept' );

// ================================================================= 8. the office home lists the groups
global $wp_query, $wp_the_query;
$wp_query = $wp_the_query = new WP_Query( array( 'post_type' => 'mcupr_office', 'p' => $O1 ) );
$wp_query->the_post();
ob_start(); mcupr_office_render( $O1, '' ); $h = ob_get_clean();
t( ! $bad( $h ) && false !== strpos( $h, 'aria-labelledby="of-groups"' ) && 2 === substr_count( $h, 'class="gl-gcard"' ), 'office home: the part "Work groups of the office" with a card for each of its 2 groups' );
t( strpos( $h, 'กลุ่มงานบริหารทั่วไปทดสอบ' ) < strpos( $h, 'กลุ่มงานการเงินทดสอบ' ), 'office home: the cards follow "Order in the list" (general 1, finance 2)' );
t( false !== strpos( $h, sprintf( mcupr_t( 'gr_people_n' ), 2 ) ) && false !== strpos( $h, '054-000-011' ) && false !== strpos( $h, esc_url( get_permalink( $G1 ) ) ) && false === strpos( $h, 'Group of office two' ), 'office home: a card has the number of people, the telephone and the link; no group of another office' );
$wp_query = $wp_the_query = new WP_Query( array( 'post_type' => 'mcupr_office', 'p' => $O2 ) );
foreach ( array( $G3 ) as $g ) { wp_update_post( array( 'ID' => $g, 'post_status' => 'draft' ) ); }
ob_start(); mcupr_office_render( $O2, '' ); $h2 = ob_get_clean();
t( false === strpos( $h2, 'of-groups' ), 'office home: an office without (published) groups has no such part' );
wp_update_post( array( 'ID' => $G3, 'post_status' => 'publish' ) );
// the office news of one group
$_GET['ogroup'] = (string) $G1;
$wp_query = $wp_the_query = new WP_Query( array( 'post_type' => 'mcupr_office', 'p' => $O1 ) );
set_query_var( 'mcupr_osec', 'news' );
ob_start(); mcupr_office_render( $O1, 'news' ); $h = ob_get_clean();
unset( $_GET['ogroup'] );
t( false !== strpos( $h, 'class="of-filter"' ) && false !== strpos( $h, 'ประกาศของกลุ่มงานการเงิน 1' ) && false === strpos( $h, 'ข่าวของสำนักงานที่ไม่อยู่ในกลุ่มงาน' ), 'office news ?ogroup=<id>: only the news of that group, with a note and a way back' );
$_GET['ogroup'] = (string) $G3;
t( 0 === mcupr_office_news_group( $O1 ), 'office news: a group of another office in ?ogroup is ignored' );
unset( $_GET['ogroup'] );

// ================================================================= 9. Office editors
$ed = username_exists( 'og_editor' ) ? username_exists( 'og_editor' ) : wp_insert_user( array( 'user_login' => 'og_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'og_editor@example.org', 'role' => MCUPR_OFFICE_ROLE ) );
update_user_meta( $ed, 'mcupr_offices', array( $O1 ) );
wp_set_current_user( $ed );
t( current_user_can( 'edit_post', $G1 ) && current_user_can( 'delete_post', $G1 ) && ! current_user_can( 'edit_post', $G3 ) && ! current_user_can( 'delete_post', $G3 ), 'office editor: edits the groups of their own office, not those of another office' );
t( current_user_can( get_post_type_object( 'mcupr_group' )->cap->create_posts ), 'office editor: can add a group (it is given their office when saved)' );
$mine = mcupr_groups_for_user( $ed );
t( array( $O1 ) === array_keys( $mine ) && in_array( $G1, wp_list_pluck( $mine[ $O1 ], 'ID' ), true ), 'office editor: the "Work group" box only offers the groups of their offices' );
$new = wp_insert_post( array( 'post_type' => 'mcupr_group', 'post_status' => 'publish', 'post_title' => 'กลุ่มงานใหม่ของบรรณาธิการ', 'post_author' => $ed ) );
$made[] = $new;
t( $O1 === mcupr_group_office( $new ) && $O1 === (int) get_post_meta( $new, '_mcupr_g_office', true ), 'office editor: a group they make is put into their office (mark and meta)' );
ob_start(); mcupr_office_member_box( get_post( $new ) ); $box = ob_get_clean();
t( false === strpos( $box, 'value="0"' ) && false === strpos( $box, '>Office two' ), 'the "Belongs to office" box of a group: only their office, and no "university" choice' );
wp_set_current_user( 1 );
ob_start(); mcupr_office_member_box( get_post( $G1 ) ); $box = ob_get_clean();
t( false === strpos( $box, 'value="0"' ) && false !== strpos( $box, 'Office two' ), 'administrator: every office, still no "university" choice for a group' );

// ================================================================= 10. admin list, strings, control center
$cols = apply_filters( 'manage_mcupr_group_posts_columns', array() );
t( isset( $cols['mcupr_g_people'] ), 'admin list: a column with the number of people' );
ob_start(); do_action( 'restrict_manage_posts', 'mcupr_group', 'top' ); $flt = ob_get_clean();
t( false !== strpos( $flt, 'name="mcupr_of_filter"' ) && false !== strpos( $flt, 'สำนักงานทดสอบกลุ่มงาน' ), 'admin list: the administrator\'s filter by office (like the other content of an office)' );
$cc = mcupr_cc_data();
t( isset( $cc['items']['groups'] ) && 'content' === $cc['items']['groups']['cat'] && 'type:mcupr_group' === $cc['items']['groups']['open'] && in_array( 'type:mcupr_group', $cc['items']['groups']['screens'], true ), 'control center: an entry for the work groups in the content group, with its screen' );
$keys = array( 'gr_groups', 'gr_enter', 'gr_people_n', 'gr_on_page', 'gr_about', 'gr_tab_about', 'gr_services', 'gr_tab_services', 'gr_news', 'gr_tab_news', 'gr_news_all', 'gr_news_of', 'gr_news_clear', 'gr_back', 'gr_people', 'gr_tab_people', 'gr_head', 'gr_docs', 'gr_tab_docs', 'gr_download', 'gr_updated', 'gr_contact', 'gr_tab_contact', 'gr_ask', 'gr_channels', 'gr_place', 'gr_write', 'gr_write_lead', 'gr_write_btn' );
$miss = array();
foreach ( $keys as $k ) {
	$row = array_map( function ( $l ) use ( $k ) { return mcupr_t( $k, $l ); }, array( 'th', 'en', 'zh', 'ja' ) );
	if ( $k === $row[1] || count( array_filter( $row ) ) < 4 || count( array_unique( $row ) ) < 3 ) { $miss[] = $k; }
}
t( ! $miss, 'strings: ' . count( $keys ) . ' texts of the group page in four languages' . ( $miss ? ' (missing: ' . implode( ', ', $miss ) . ')' : '' ) );

// ================================================================= 11. kept off the main site like the rest of the office
$q = new WP_Query( array( 's' => 'กลุ่มงานการเงินทดสอบ', 'post_type' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
t( ! in_array( $G1, array_map( 'intval', $q->posts ), true ), 'search of the main site does not find the group of an office that is kept off the main site' );

// ---- clean up
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ed );
foreach ( array_reverse( $made ) as $id ) { wp_delete_post( $id, true ); }
delete_transient( 'mcupr_group_counts' );
wp_set_current_user( 0 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
