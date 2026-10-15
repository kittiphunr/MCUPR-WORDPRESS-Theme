<?php
/**
 * Discussion board: posting rules, moderation, spam protection, reports, rendering, privacy.
 *   php php/forum.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function fx( $k, $v = array() ) { return array_merge( array( 'tk' => mcupr_form_token( time() - 10 ), 'website' => '', 'consent' => '1' ), $v ); }
wp_set_current_user( 0 );
delete_option( 'mcupr_forum' );
$board = get_term_by( 'slug', 'general', 'mcupr_board' );
t( $board && post_type_exists( 'mcupr_topic' ) && taxonomy_exists( 'mcupr_board' ), 'setup: the post type, the boards and a first board exist' );
$ids = array(); $cids = array();
$topic = static function ( $v = array() ) use ( $board ) {
	return mcupr_forum_submit( 'topic', fx( 'topic', array_merge( array( 'name' => 'ผู้เยี่ยมชม', 'email' => 'v@example.org', 'title' => 'คำถามเรื่องการสมัครเรียน', 'message' => 'อยากทราบวิธีสมัครเรียนและกำหนดการรับสมัครครับ', 'board' => $board->term_id ), $v ) ) );
};
$clear = static function () { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mcupr_fm_%' OR option_name LIKE '_transient_timeout_mcupr_fm_%'" ); wp_cache_flush(); };
$clear();

// ---- a visitor's topic is held for approval
$r = $topic();
t( $r['ok'] && 'pending' === $r['status'] && 'pending' === get_post_status( $r['id'] ), 'topic: a visitor\'s post waits for a moderator by default' );
$ids[] = $r['id'];
t( 'v@example.org' === get_post_meta( $r['id'], '_mcupr_topic_email', true ) && 'ผู้เยี่ยมชม' === mcupr_topic_name( $r['id'] ) && 1 === count( wp_get_object_terms( $r['id'], 'mcupr_board' ) ), 'topic: name, private email and board are stored' );
t( ! get_posts( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'post__in' => array( $r['id'] ) ) ), 'topic: not public while pending' );

// ---- validation
$clear();
$e = $topic( array( 'name' => '', 'email' => 'bad', 'title' => 'x', 'message' => 'สั้น', 'consent' => '0', 'board' => 0 ) );
t( ! $e['ok'] && isset( $e['errors']['name'], $e['errors']['email'], $e['errors']['title'], $e['errors']['message'], $e['errors']['consent'], $e['errors']['board'] ), 'validation: every problem is reported' );
t( isset( $topic( array( 'message' => str_repeat( 'ก', 5001 ) ) )['errors']['message'] ), 'validation: a message over 5,000 characters is refused' );
$bot = mcupr_forum_submit( 'topic', array_merge( fx( 'topic', array( 'name' => 'a', 'email' => 'a@example.org', 'title' => 'หัวข้อทดสอบ', 'message' => 'ข้อความทดสอบยาวพอ', 'board' => $board->term_id ) ), array( 'website' => 'http://spam' ) ) );
t( ! $bot['ok'] && isset( $bot['errors']['spam'] ), 'spam: the hidden trap field rejects a robot' );
$fast = mcupr_forum_submit( 'topic', array_merge( fx( 'topic', array( 'name' => 'a', 'email' => 'a@example.org', 'title' => 'หัวข้อทดสอบ', 'message' => 'ข้อความทดสอบยาวพอ', 'board' => $board->term_id ) ), array( 'tk' => mcupr_form_token( time() ) ) ) );
t( ! $fast['ok'] && isset( $fast['errors']['spam'] ), 'spam: a form filled in under 3 seconds is refused' );
$forged = mcupr_forum_submit( 'topic', array_merge( fx( 'topic', array( 'name' => 'a', 'email' => 'a@example.org', 'title' => 'หัวข้อทดสอบ', 'message' => 'ข้อความทดสอบยาวพอ', 'board' => $board->term_id ) ), array( 'tk' => time() - 20 . '.forged' ) ) );
t( ! $forged['ok'] && isset( $forged['errors']['spam'] ), 'spam: a forged time token is refused' );

// ---- duplicates and rate limit
$clear();
$a = $topic( array( 'title' => 'หัวข้อซ้ำกันทดสอบ' ) ); $b = $topic( array( 'title' => 'หัวข้อซ้ำกันทดสอบ' ) );
t( $a['ok'] && ! $b['ok'] && isset( $b['errors']['dup'] ), 'spam: the same post twice is refused' );
$ids[] = $a['id'];
$clear(); $n = 0;
for ( $i = 0; $i < 8; $i++ ) { $x = $topic( array( 'title' => 'หัวข้อลำดับที่ ' . $i . ' ทดสอบ', 'message' => 'ข้อความทดสอบลำดับ ' . $i . ' ยาวพอแล้ว' ) ); if ( $x['ok'] ) { $n++; $ids[] = $x['id']; } }
t( 6 === $n, 'spam: a visitor can post six times an hour, then is asked to wait (' . $n . ')' );
$clear();

// ---- moderation modes and signals
update_option( 'mcupr_forum', array( 'moderate' => 'none' ) );
$open = $topic( array( 'title' => 'ไม่ต้องรออนุมัติทดสอบ', 'message' => 'ข้อความปกติที่ไม่มีลิงก์ใดๆ เลย' ) ); $ids[] = $open['id'];
t( $open['ok'] && 'publish' === $open['status'], 'moderation "none": the post appears at once' );
$clear();
$links = $topic( array( 'title' => 'มีลิงก์เยอะทดสอบ', 'message' => 'ดูที่ http://a.example http://b.example http://c.example นะครับ' ) ); $ids[] = $links['id'];
t( 'pending' === $links['status'], 'moderation: more than the allowed number of links holds the post even when moderation is off' );
$clear();
update_option( 'mcupr_forum', array( 'moderate' => 'none', 'blocked' => "คาสิโน\nspamword" ) );
$bw = $topic( array( 'title' => 'ข้อความต้องห้ามทดสอบ', 'message' => 'เว็บ คาสิโน ออนไลน์ ฝากถอน' ) ); $ids[] = $bw['id'];
t( 'pending' === $bw['status'], 'moderation: a blocked word holds the post' );
$clear();
update_option( 'mcupr_forum', array( 'moderate' => 'guests' ) );
$u = wp_insert_user( array( 'user_login' => 'fm_member_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'member' . wp_rand() . '@example.org', 'role' => 'subscriber' ) );
wp_set_current_user( $u );
$m = $topic( array( 'title' => 'สมาชิกโพสต์ทดสอบ', 'message' => 'ข้อความของสมาชิกที่เข้าสู่ระบบแล้ว' ) ); $ids[] = $m['id'];
t( 'publish' === $m['status'], 'moderation "guests only": a logged-in member is not held' );
$clear();
update_option( 'mcupr_forum', array( 'post_who' => 'users', 'moderate' => 'all' ) );
wp_set_current_user( 0 );
$no = $topic( array( 'title' => 'ไม่ได้ล็อกอินทดสอบ', 'message' => 'ข้อความทดสอบจากผู้ที่ไม่ได้ล็อกอิน' ) );
t( ! $no['ok'] && isset( $no['errors']['login'] ), '"logged-in users only": a visitor is told to log in' );
$ed = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $ed->ID );
$clear();
$adm = $topic( array( 'title' => 'ผู้ดูแลโพสต์ทดสอบ', 'message' => 'ข้อความของผู้ดูแลระบบ', 'consent' => '0' ) ); $ids[] = $adm['id'];
t( $adm['ok'] && 'publish' === $adm['status'], 'staff: administrators publish at once and need no consent tick' );
wp_set_current_user( 0 );
delete_option( 'mcupr_forum' );

// ---- replies
update_option( 'mcupr_forum', array( 'moderate' => 'none' ) );
$clear();
$t1 = $topic( array( 'title' => 'หัวข้อสำหรับตอบทดสอบ', 'message' => 'ข้อความต้นทางของหัวข้อสำหรับตอบ' ) ); $ids[] = $t1['id'];
$tid = $t1['id'];
$rp = mcupr_forum_submit( 'reply', fx( 'reply', array( 'name' => 'ผู้ตอบ', 'email' => 'r@example.org', 'message' => 'คำตอบแรกของหัวข้อนี้ครับ', 'topic' => $tid ) ) );
t( $rp['ok'] && 'publish' === $rp['status'] && 1 === mcupr_topic_replies( $tid ), 'reply: stored as a comment and counted' );
$c = get_comment( $rp['id'] );
t( '' === $c->comment_author_IP && '' === $c->comment_agent && 'r@example.org' === $c->comment_author_email, 'privacy: no IP address or browser data is stored with a reply' );
$old = mcupr_topic_last( $tid ) ;
$clear();
update_option( 'mcupr_forum', array( 'moderate' => 'all' ) );
$rp2 = mcupr_forum_submit( 'reply', fx( 'reply', array( 'name' => 'ผู้ตอบสอง', 'email' => 'r2@example.org', 'message' => 'คำตอบที่รอการอนุมัติของผู้ดูแล', 'topic' => $tid ) ) );
t( $rp2['ok'] && 'pending' === $rp2['status'] && 1 === mcupr_topic_replies( $tid ), 'reply: held replies are not counted or shown' );
wp_set_comment_status( $rp2['id'], 'approve' );
t( 2 === mcupr_topic_replies( $tid ), 'reply: approving makes it appear' );
$html = mcupr_forum_replies_html( $tid );
t( false !== strpos( $html, 'คำตอบแรก' ) && false === strpos( $html, 'r@example.org' ) && false === strpos( $html, 'r2@example.org' ), 'privacy: reply emails are never printed' );
update_post_meta( $tid, '_mcupr_topic_locked', 1 ); $clear();
$lk = mcupr_forum_submit( 'reply', fx( 'reply', array( 'name' => 'x', 'email' => 'x@example.org', 'message' => 'ตอบหัวข้อที่ล็อกไว้แล้ว', 'topic' => $tid ) ) );
t( ! $lk['ok'] && isset( $lk['errors']['locked'] ), 'locked: no replies to a locked topic' );
delete_post_meta( $tid, '_mcupr_topic_locked' );
update_option( 'mcupr_forum', array( 'moderate' => 'none', 'close_days' => 30 ) );
mcupr_topic_touch( $tid, time() - 40 * DAY_IN_SECONDS );
t( mcupr_topic_locked( $tid ), 'auto-lock: a topic silent for longer than the limit is closed' );
mcupr_topic_touch( $tid );
$bad = mcupr_forum_submit( 'reply', fx( 'reply', array( 'name' => 'x', 'email' => 'x@example.org', 'message' => 'ตอบหัวข้อที่ไม่มีอยู่จริง', 'topic' => 999999 ) ) );
t( ! $bad['ok'] && isset( $bad['errors']['topic'] ), 'reply: an unknown topic is refused' );

// ---- reports
delete_option( 'mcupr_forum' ); $clear();
update_option( 'mcupr_forum', array( 'moderate' => 'none' ) );
$rt = $topic( array( 'title' => 'หัวข้อที่จะถูกรายงานทดสอบ', 'message' => 'ข้อความที่จะถูกรายงานโดยผู้เยี่ยมชม' ) ); $ids[] = $rt['id'];
$res = array();
foreach ( array( 'a', 'b', 'c' ) as $who ) { $_SERVER['REMOTE_ADDR'] = '10.0.0.' . ord( $who ); $res[] = mcupr_forum_report( 'topic', $rt['id'] ); }
t( array( 'ok', 'ok', 'hidden' ) === $res && 'pending' === get_post_status( $rt['id'] ), 'report: three reports from different visitors hide a topic' );
$_SERVER['REMOTE_ADDR'] = '10.9.9.9';
$rt2 = $topic( array( 'title' => 'หัวข้ออีกอันที่ถูกรายงาน', 'message' => 'ข้อความอีกอันที่ถูกรายงานซ้ำๆ' ) ); $ids[] = $rt2['id'];
t( 'ok' === mcupr_forum_report( 'topic', $rt2['id'] ) && 'again' === mcupr_forum_report( 'topic', $rt2['id'] ), 'report: the same visitor cannot report the same post twice' );
$cr = mcupr_forum_submit( 'reply', fx( 'reply', array( 'name' => 'y', 'email' => 'y@example.org', 'message' => 'คำตอบที่จะถูกรายงานเช่นกัน', 'topic' => $tid ) ) );
foreach ( array( 'a', 'b', 'c' ) as $who ) { $_SERVER['REMOTE_ADDR'] = '10.1.0.' . ord( $who ); mcupr_forum_report( 'reply', $cr['id'] ); }
t( '0' === get_comment( $cr['id'] )->comment_approved, 'report: three reports hide a reply until a moderator looks' );
t( 'bad' === mcupr_forum_report( 'topic', 999999 ) && 'bad' === mcupr_forum_report( 'reply', 999999 ), 'report: unknown posts are ignored' );
update_option( 'mcupr_forum', array( 'reports' => 0 ) );
t( 'off' === mcupr_forum_report( 'topic', $rt2['id'] ), 'report: can be switched off' );
unset( $_SERVER['REMOTE_ADDR'] );

// ---- disabled forum
update_option( 'mcupr_forum', array( 'enabled' => 0 ) ); $clear();
$off = $topic( array( 'title' => 'กระดานปิดอยู่ทดสอบ', 'message' => 'ข้อความทดสอบตอนกระดานถูกปิด' ) );
t( ! $off['ok'] && isset( $off['errors']['closed'] ) && false !== strpos( mcupr_forum_html(), 'text-muted' ), 'switch off: posting is refused and the page says it is closed' );
delete_option( 'mcupr_forum' );

// ---- rendering
update_option( 'mcupr_forum', array( 'moderate' => 'none' ) ); $clear();
$pub = $topic( array( 'title' => 'หัวข้อสำหรับแสดงผลทดสอบ', 'message' => "บรรทัดแรก <script>alert(1)</script>\nบรรทัดสอง http://example.org/x" ) ); $ids[] = $pub['id'];
$pin = $topic( array( 'title' => 'หัวข้อที่ปักหมุดทดสอบ', 'message' => 'ข้อความของหัวข้อที่ถูกปักหมุดไว้' ) ); $ids[] = $pin['id'];
update_post_meta( $pin['id'], '_mcupr_topic_sticky', 1 );
$txt = mcupr_forum_text( "บรรทัดแรก <script>alert(1)</script>\nบรรทัดสอง http://example.org/x" );
t( false === strpos( get_post_field( 'post_content', $pub['id'] ), '<script' ), 'text: tags are stripped before saving' );
t( false === strpos( $txt, '<script' ) && false !== strpos( $txt, '&lt;script' ) && false !== strpos( $txt, '<br' ) && false !== strpos( $txt, 'rel="nofollow ugc noopener"' ), 'text: HTML is shown as text, line breaks and links work, links are nofollow' );
$_GET = array(); $idx = mcupr_forum_html();
t( false !== strpos( $idx, 'forum-boards' ) && false !== strpos( $idx, 'ทั่วไป' ) && false !== strpos( $idx, 'หัวข้อสำหรับแสดงผลทดสอบ' ) && false !== strpos( $idx, 'id="fm-topic"' ), 'index: boards, latest topics and the new-topic form' );
t( false === strpos( $idx, 'v@example.org' ), 'privacy: no email appears on the index' );
$_GET = array( 'fb' => 'general' ); $bd = mcupr_forum_html();
t( strpos( $bd, 'หัวข้อที่ปักหมุดทดสอบ' ) < strpos( $bd, 'หัวข้อสำหรับแสดงผลทดสอบ' ) && false !== strpos( $bd, 'is-pinned' ), 'board: pinned topics come first' );
$_GET = array( 'fq' => 'แสดงผลทดสอบ' ); $sr = mcupr_forum_html();
t( false !== strpos( $sr, 'หัวข้อสำหรับแสดงผลทดสอบ' ) && false === strpos( $sr, 'หัวข้อที่ปักหมุดทดสอบ' ), 'search: finds topics by words' );
$_GET = array( 'fq' => 'ไม่มีคำนี้แน่นอนxyz' ); t( false !== strpos( mcupr_forum_html(), 'fm_no_topics' ) || false !== strpos( mcupr_forum_html(), 'ยังไม่มีหัวข้อ' ), 'search: says when nothing matches' );
$_GET = array();
update_option( 'mcupr_forum', array( 'post_who' => 'users' ) );
t( false !== strpos( mcupr_forum_form( $board->term_id ), 'wp-login.php' ) && false === strpos( mcupr_forum_form( $board->term_id ), 'name="message"' ), 'form: visitors see a login link instead of the form when posting needs an account' );
delete_option( 'mcupr_forum' );
t( false !== strpos( mcupr_forum_form( $board->term_id ), 'name="website"' ) && false !== strpos( mcupr_forum_form( $board->term_id ), 'name="consent"' ) && false !== strpos( mcupr_forum_form( $board->term_id ), 'name="tk"' ), 'form: trap field, consent tick and signed token' );

// ---- cleanup
foreach ( $ids as $i ) { wp_delete_post( $i, true ); }
wp_delete_post( $tid, true ); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $u );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
