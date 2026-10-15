<?php
/** Polls and satisfaction surveys: voting rules, results, saving.   php php/polls.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
function mk( $title, $type, $opts, $meta = array() ) {
	$id = wp_insert_post( array( 'post_type' => 'mcupr_poll', 'post_status' => 'publish', 'post_title' => $title ) );
	update_post_meta( $id, '_mcupr_poll_type', $type );
	update_post_meta( $id, '_mcupr_poll_opts', $opts );
	foreach ( $meta as $k => $v ) { update_post_meta( $id, $k, $v ); }
	return $id;
}
function tok( $now ) { return array( 'tk' => mcupr_form_token( $now - 10 ), 'website' => '' ); }
function fresh() { global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%mcupr_pv_%'" ); wp_cache_flush(); $_COOKIE = array(); }
$now = time();
$a = mk( 'Colour?', 'choice', "Red\nGreen\nBlue" );
fresh();
t( 'spam' === mcupr_poll_vote( $a, array( 0 ), array( 'tk' => 'x', 'website' => '' ), $now ), 'poll: a bad time token is refused' );
t( 'spam' === mcupr_poll_vote( $a, array( 0 ), array( 'tk' => mcupr_form_token( $now - 10 ), 'website' => 'x' ), $now ), 'poll: the trap field is refused' );
t( 'spam' === mcupr_poll_vote( $a, array( 0 ), array( 'tk' => mcupr_form_token( $now ), 'website' => '' ), $now ), 'poll: a vote sent within two seconds is refused' );
t( 'none' === mcupr_poll_vote( $a, array( 9 ), tok( $now ), $now ), 'poll: an unknown choice counts for nothing' );
t( 'ok' === mcupr_poll_vote( $a, array( 1, 2 ), tok( $now ), $now ) && array( 1 => 1 ) === mcupr_poll_votes( $a ), 'poll: one choice – only the first is counted' );
t( 'again' === mcupr_poll_vote( $a, array( 0 ), tok( $now ), $now ) && array( 1 => 1 ) === mcupr_poll_votes( $a ), 'poll: the same visitor cannot vote twice' );
fresh();
$m = mk( 'Fruit?', 'multi', "A\nB\nC" );
t( 'ok' === mcupr_poll_vote( $m, array( 0, 2, 2, 7 ), tok( $now ), $now ) && array( 0 => 1, 2 => 1 ) === mcupr_poll_votes( $m ), 'poll: several choices – counted once each, unknown ignored' );
fresh();
$s = mk( 'Satisfied?', 'satisfaction', '' );
t( 5 === count( mcupr_poll_options( $s ) ), 'poll: the satisfaction scale has five levels' );
t( 'ok' === mcupr_poll_vote( $s, array( 0 ), tok( $now ), $now ), 'poll: satisfaction vote' );
fresh();
$c = mk( 'Old', 'choice', "X\nY", array( '_mcupr_poll_close' => gmdate( 'Y-m-d', $now - 3 * DAY_IN_SECONDS ) ) );
t( mcupr_poll_is_closed( $c ) && 'closed' === mcupr_poll_vote( $c, array( 0 ), tok( $now ), $now ), 'poll: a closed poll takes no votes' );
t( ! mcupr_poll_is_closed( $a ), 'poll: no closing date – open' );
t( 'bad' === mcupr_poll_vote( 999999, array( 0 ), tok( $now ), $now ), 'poll: unknown poll' );
fresh();
$h = do_shortcode( '[mcupr_poll id="' . $a . '"]' );
t( substr_count( $h, 'name="choice[]"' ) === 3 && false !== strpos( $h, 'type="radio"' ) && false === strpos( $h, 'poll-res' ), 'poll: form with radio buttons, results hidden before voting' );
t( false !== strpos( do_shortcode( '[mcupr_poll id="' . $m . '"]' ), 'type="checkbox"' ), 'poll: checkboxes for several choices' );
t( false !== strpos( do_shortcode( '[mcupr_poll id="' . $s . '"]' ), 'is-scale' ), 'poll: scale layout' );
$_COOKIE[ 'mcupr_pv_' . $a ] = '1';
$h = do_shortcode( '[mcupr_poll id="' . $a . '"]' );
t( false === strpos( $h, '<form' ) && false !== strpos( $h, 'poll-res' ) && false !== strpos( $h, 'poll-bar' ), 'poll: after voting – no form, results shown' );
update_post_meta( $a, '_mcupr_poll_results', 'never' );
t( false === strpos( do_shortcode( '[mcupr_poll id="' . $a . '"]' ), 'poll-res' ), 'poll: results can be hidden on the website' );
update_post_meta( $a, '_mcupr_poll_results', 'always' ); $_COOKIE = array(); fresh();
$h = do_shortcode( '[mcupr_poll id="' . $a . '"]' );
t( false !== strpos( $h, '<form' ) && false !== strpos( $h, 'poll-res' ), 'poll: results can always be shown' );
t( false === strpos( do_shortcode( '[mcupr_poll id="' . $c . '"]' ), '<form' ), 'poll: closed – no form' );
// saving from the admin form
wp_set_current_user( 1 );
$_POST = array( 'mcupr_poll_nonce' => wp_create_nonce( 'mcupr_poll_save' ), 'mcupr_poll' => array( 'type' => 'evil', 'opts' => "<b>One</b>\n\n Two \n", 'results' => 'zzz', 'close' => 'tomorrow' ) );
wp_update_post( array( 'ID' => $m, 'post_title' => 'Fruit?' ) );
t( 'choice' === get_post_meta( $m, '_mcupr_poll_type', true ) && "One\nTwo" === get_post_meta( $m, '_mcupr_poll_opts', true ) && 'after' === get_post_meta( $m, '_mcupr_poll_results', true ) && '' === get_post_meta( $m, '_mcupr_poll_close', true ), 'poll: saving cleans the type, the choices, the results rule and the date' );
t( ! get_post_meta( $m, '_mcupr_poll_votes', true ), 'poll: changing the choices clears the old counts' );
$_POST = array();
foreach ( array( $a, $m, $s, $c ) as $i ) { wp_delete_post( $i, true ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
