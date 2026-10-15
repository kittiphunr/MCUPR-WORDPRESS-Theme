<?php
/**
 * Staff hub: privacy, profile data, royal decorations catalogue, chart link, saving, control centre.
 * Needs the starter site after starter-fixtures.php.   php php/staff.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 0 );
$people = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'posts_per_page' => 10, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
$p0 = $people[0]->ID; $p1 = $people[1]->ID; $p2 = $people[2]->ID;

// ---- privacy
t( mcupr_person_shows( $p0, 'email' ) && ! mcupr_person_shows( $p0, 'tel' ), 'privacy: the person\'s own choice wins (email shown, telephone hidden)' );
t( ! mcupr_person_shows( $p2, 'email' ) && ! mcupr_person_shows( $p2, 'works' ) && ! mcupr_person_shows( $p2, 'edu' ), 'privacy: contact items and sections can be hidden' );
$new = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_title' => 'ทดสอบ ค่าเริ่มต้น', 'post_status' => 'publish' ) );
t( ! mcupr_person_shows( $new, 'tel' ) && ! mcupr_person_shows( $new, 'office' ) && mcupr_person_shows( $new, 'email' ), 'privacy: someone not saved in the hub yet gets the hub\'s defaults (telephone and office hidden)' );
wp_delete_post( $new, true );
t( ! mcupr_person_has_profile( $p1 ) && mcupr_person_has_profile( $p0 ), 'profile: can be switched off per person' );
t( false !== strpos( mcupr_person_url( $p0 ), '/profile/' ) && ( '' === mcupr_person_url( $p1 ) || false === strpos( mcupr_person_url( $p1 ), '/profile/' ) ), 'profile: the name links to the profile page (not when it is switched off)' );
update_option( 'mcupr_staff', array( 'profiles' => 0 ) );
t( ! mcupr_person_has_profile( $p0 ), 'profile: the hub can switch every profile page off' );
delete_option( 'mcupr_staff' );

// ---- years
t( 2019 === mcupr_year_norm( 2562 ) && 2019 === mcupr_year_norm( 2019 ) && 0 === mcupr_year_norm( 12 ), 'year: พ.ศ. or ค.ศ. is accepted, nonsense is dropped' );

// ---- royal decorations catalogue
delete_option( 'mcupr_honors' );
$cat = mcupr_honors();
t( count( $cat ) === 16 && array_keys( $cat )[0] === 'mpc' && isset( $cat['tc'] ) && 'ท.ช.' === $cat['tc']['code'] && 'ทวีติยาภรณ์ช้างเผือก' === $cat['tc']['th'], 'catalogue: the 16 orders and medals in rank order' );
$svg = mcupr_honor_medal( $cat['tc'], 40 );
t( false !== strpos( $svg, '<svg' ) && false !== strpos( $svg, $cat['tc']['c1'] ) && false !== strpos( $svg, $cat['tc']['metal'] ), 'catalogue: the drawn medal uses the chosen ribbon and metal colours' );
$img = $GLOBALS['wpdb']->get_var( "SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type='attachment' AND post_mime_type LIKE 'image/%' ORDER BY ID DESC LIMIT 1" );
$cust = $cat['tc']; $cust['img'] = (int) $img;
t( $img && false !== strpos( mcupr_honor_medal( $cust, 40 ), '<img' ) && false === strpos( mcupr_honor_medal( $cust, 40 ), '<svg' ), 'catalogue: an uploaded medal image replaces the drawn one' );
$hs = mcupr_person_honors( $p0 );
t( count( $hs ) === 2 && 'tc' === $hs[0]['item']['key'] && 'trm' === $hs[1]['item']['key'] && 2019 === $hs[0]['year'], 'decorations: a person\'s list is in rank order, years normalised' );
update_option( 'mcupr_honors', array_map( static function ( $h ) { if ( 'tc' === $h['key'] ) { $h['on'] = 0; } return $h; }, mcupr_honor_defaults() ) );
t( ! isset( mcupr_honors( true )['tc'] ) && isset( mcupr_honors()['tc'] ), 'catalogue: a decoration can be switched off' );
delete_option( 'mcupr_honors' );

// ---- the chart
$unit = (int) get_post_meta( $p0, '_mcupr_person_unit', true );
$list = mcupr_unit_people( $unit );
t( $list && $list[0]->ID === $p0, 'chart: the head of a unit comes first among its people' );
$html = mcupr_orgchart_html();
t( false !== strpos( $html, get_the_title( $p0 ) ) && false !== strpos( $html, '/profile/' ), 'chart: the unit box shows the linked head with a link to the profile' );
t( false === strpos( $html, esc_html( get_the_title( $p1 ) ) . '</a>' ), 'chart: a person without a profile page is not linked' );

// ---- cards respect privacy
ob_start(); mcupr_person_card( get_post( $p0 ) ); $card = ob_get_clean();
t( false !== strpos( $card, 'head@example.org' ) && false === strpos( $card, '054-000-111' ) && false !== strpos( $card, 'person-h' ), 'card: shows the visible email, never the hidden telephone, and medal badges' );
ob_start(); mcupr_person_card( get_post( $p2 ) ); $card2 = ob_get_clean();
t( false === strpos( $card2, 'hidden@example.org' ), 'card: a hidden email is not printed' );

// ---- roll of honour
$roll = do_shortcode( '[mcupr_honors]' );
t( false !== strpos( $roll, 'honor-sec' ) && false !== strpos( $roll, 'ทวีติยาภรณ์ช้างเผือก' ) && false !== strpos( $roll, 'เบญจมาภรณ์มงกุฎไทย' ), 'roll of honour: lists each decoration with its people' );
update_post_meta( $p2, '_mcupr_person_hide', array( 'honors' ) );
t( false === strpos( do_shortcode( '[mcupr_honors]' ), 'เบญจมาภรณ์มงกุฎไทย' ), 'roll of honour: a person who hides decorations is not listed' );
update_post_meta( $p2, '_mcupr_person_hide', array( 'email', 'works', 'edu' ) );

// ---- search / unit filter on the directory
$dir = do_shortcode( '[mcupr_people search="1" unit="' . $unit . '"]' );
t( false !== strpos( $dir, 'data-people-find' ) && substr_count( $dir, 'person-card' ) >= 2, 'directory: search box and unit filter' );

// ---- saving (as an editor): rows are cleaned, privacy stored
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
$_POST = array(
	'mcupr_staff_nonce' => wp_create_nonce( 'mcupr_staff_save' ),
	'mcupr_staff'       => array(
		'unit' => (string) $unit, 'head' => '1', 'rank' => ' ศ. ', 'type' => 'พนักงาน', 'office' => 'ห้อง 1', 'scholar' => 'javascript:alert(1)', 'expertise' => 'ก, ข', 'note' => 'ลับ',
		'edu'    => array( array( 'level' => 'ป.ตรี', 'degree' => '', 'inst' => 'x', 'year' => '2540' ), array( 'level' => 'ป.โท', 'degree' => 'ศศ.ม.', 'inst' => 'y', 'year' => '2545' ) ),
		'works'  => array( array( 'type' => 'evil', 'title' => '<b>ผลงาน</b>', 'year' => '2566', 'url' => 'https://example.org/w' ) ),
		'honors' => array( array( 'key' => 'pm', 'year' => '2565', 'gazette' => 'g', 'note' => '' ), array( 'key' => 'nonexistent', 'year' => '1', 'gazette' => '', 'note' => '' ) ),
		'hide'   => array( 'email', 'bogus', 'works' ),
		'noprofile' => '1',
	),
);
mcupr_staff_save( $p1 );
t( 1 === (int) get_post_meta( $p1, '_mcupr_person_head', true ) && 'ศ.' === get_post_meta( $p1, '_mcupr_person_rank', true ), 'save: fields stored and trimmed' );
t( '' === get_post_meta( $p1, '_mcupr_person_scholar', true ), 'save: a javascript: link is refused' );
t( count( mcupr_person_rows( $p1, 'edu' ) ) === 1 && 'ศศ.ม.' === mcupr_person_rows( $p1, 'edu' )[0]['degree'], 'save: an education row without its main field is dropped' );
$w = mcupr_person_rows( $p1, 'works' );
t( 'other' === $w[0]['type'] && false === strpos( $w[0]['title'], '<' ), 'save: an unknown work type becomes "other" and tags are stripped' );
t( count( mcupr_person_rows( $p1, 'honors' ) ) === 1 && 'pm' === mcupr_person_rows( $p1, 'honors' )[0]['key'], 'save: only decorations that exist in the catalogue are kept' );
t( array( 'email', 'works' ) === get_post_meta( $p1, '_mcupr_person_hide', true ) && 'off' === get_post_meta( $p1, '_mcupr_person_profile', true ), 'save: privacy choices stored, unknown keys dropped' );
$_POST['mcupr_staff_nonce'] = 'bad'; $before = get_post_meta( $p1, '_mcupr_person_rank', true ); $_POST['mcupr_staff']['rank'] = 'hack';
mcupr_staff_save( $p1 );
t( get_post_meta( $p1, '_mcupr_person_rank', true ) === $before, 'save: a wrong nonce changes nothing' );
wp_set_current_user( 0 );
$_POST = array( 'mcupr_staff_nonce' => wp_create_nonce( 'mcupr_staff_save' ), 'mcupr_staff' => array( 'rank' => 'hack' ) );
mcupr_staff_save( $p1 );
t( get_post_meta( $p1, '_mcupr_person_rank', true ) === $before, 'save: without the right to edit the post nothing is stored' );
$_POST = array();
// restore p1 fixture state
update_post_meta( $p1, '_mcupr_person_profile', 'off' ); delete_post_meta( $p1, '_mcupr_person_head' );

// ---- the control centre
wp_set_current_user( $admin->ID );
set_current_screen( 'mcupr_person_page_mcupr-staff-hub' );
ob_start(); mcupr_staff_hub_page(); $hub = ob_get_clean();
t( false !== strpos( $hub, 'mcs-hub' ) && false !== strpos( $hub, 'data-honor-row' ) && substr_count( $hub, 'data-honor-row' ) >= 16, 'hub: overview, settings and the 16-row catalogue are rendered' );
t( false !== strpos( $hub, 'mcupr_hub_nonce' ) && false !== strpos( $hub, 'name="default_hide[]"' ), 'hub: forms carry a nonce and the privacy defaults' );
$c = mcupr_staff_hub_checks();
t( $c['people'] >= 4 && $c['with_honors'] >= 1 && $c['no_profile'] >= 1, 'hub: the overview counts people, decorations and cards without a profile' );
wp_set_current_user( 0 );
t( ! current_user_can( 'edit_pages' ), 'hub: visitors have no access' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
