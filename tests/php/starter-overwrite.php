<?php
/** Putting the latest starter in over what a site has: pages one by one, the settings, the backups, who may.   php php/starter-overwrite.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );

$pages = mcupr_so_pages();
t( count( $pages ) >= 60 && isset( $pages['history'], $pages['about'] ) && false !== strpos( $pages['history']['content'], 'วัดพระบาทมิ่งเมืองวรวิหาร' ) && ! empty( $pages['history']['meta']['_mcupr_en_title'] ), 'read: the starter files give their pages with text and translated titles' );

$h = mcupr_so_find( 'history' );
t( (bool) $h, 'setup: the history page is on the test site' );
if ( ! $h ) { echo "cannot go on\n"; exit( 1 ); }
$id = $h->ID;
$set = static function ( $content ) use ( $id, $wpdb ) { // plain = as imported (modified == date)
	$wpdb->update( $wpdb->posts, array( 'post_content' => $content, 'post_modified' => '2025-01-01 09:00:00', 'post_date' => '2025-01-01 09:00:00' ), array( 'ID' => $id ) );
	delete_post_meta( $id, '_mcupr_so_hash' ); delete_post_meta( $id, '_mcupr_so_backup' ); clean_post_cache( $id );
	return get_post( $id );
};

// 1. states
$p = $set( '<p>old starter text</p>' );
t( 'plain' === mcupr_so_state( $p, $pages['history'] ), 'state: old text that was never edited is "plain"' );
$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-03-03 10:00:00' ), array( 'ID' => $id ) ); clean_post_cache( $id );
t( 'edited' === mcupr_so_state( get_post( $id ), $pages['history'] ), 'state: a page changed after it was imported is "edited"' );
$set( $pages['history']['content'] );
t( 'same' === mcupr_so_state( get_post( $id ), $pages['history'] ), 'state: the same text is "same"' );
t( 'missing' === mcupr_so_state( null, $pages['history'] ), 'state: no page is "missing"' );

// 2. replace: only what is chosen
$set( '<p>my own words</p>' );
$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-03-03 10:00:00' ), array( 'ID' => $id ) ); clean_post_cache( $id );
$r = mcupr_so_replace_pages( array( 'about' ) );
t( '<p>my own words</p>' === get_post( $id )->post_content, 'replace: a page that was not chosen is left alone' );
$r = mcupr_so_replace_pages( array( 'history', 'history', 'no-such-page-xyz', 'about' ) );
$now = get_post( $id );
t( $pages['history']['content'] === $now->post_content && $pages['history']['title'] === $now->post_title, 'replace: the chosen page now has the starter text and title' );
t( 1 <= $r['replaced'] && 1 === $r['skipped'], 'replace: listed twice counts once, an unknown address is skipped (' . json_encode( $r ) . ')' );
$bk = get_post_meta( $id, '_mcupr_so_backup', true );
t( is_array( $bk ) && '<p>my own words</p>' === $bk['content'], 'backup: the old text is kept in the page\'s own field' );
$revs = wp_get_post_revisions( $id );
$had  = false;
foreach ( $revs as $rv ) { if ( '<p>my own words</p>' === $rv->post_content ) { $had = true; } }
t( $had || ! wp_revisions_enabled( $now ), 'backup: the old text is also a revision (when the site keeps revisions)' );
t( $pages['history']['meta']['_mcupr_en_title'] === get_post_meta( $id, '_mcupr_en_title', true ), 'replace: the translated title came with it' );
t( 'same' === mcupr_so_state( get_post( $id ), $pages['history'] ), 'replace: the page now reads "already the latest"' );
$r2 = mcupr_so_replace_pages( array( 'history' ) );
t( 1 === $r2['same'] && 0 === $r2['replaced'], 'replace: doing it again changes nothing' );
$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-04-04 10:00:00', 'post_content' => $now->post_content . '<p>+</p>' ), array( 'ID' => $id ) ); clean_post_cache( $id );
t( 'edited' === mcupr_so_state( get_post( $id ), $pages['history'] ), 'state: a change after the tool wrote is "edited" again' );
$wpdb->update( $wpdb->posts, array( 'post_content' => $now->post_content ), array( 'ID' => $id ) ); clean_post_cache( $id );
t( 'same' === mcupr_so_state( get_post( $id ), $pages['history'] ), 'state: back to the same text is "same"' );

// 3. who may
$sub = wp_insert_user( array( 'user_login' => 'so_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub );
ob_start(); mcupr_so_card(); $card = ob_get_clean();
t( '' === $card, 'who: a reader does not see the card' );
$set( '<p>x</p>' );
$r3 = mcupr_so_replace_pages( array( 'history' ) );
t( '<p>x</p>' === get_post( $id )->post_content && 1 === $r3['skipped'], 'who: and cannot replace a page even if the request is made by hand' );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );
wp_delete_user( $sub );

// 4. the card
$set( '<p>old</p>' );
ob_start(); mcupr_so_card(); $card = ob_get_clean();
t( preg_match( '/value="history" id="so-history" checked/', $card ) && false !== strpos( $card, 'name="sure"' ) && false !== strpos( $card, 'required' ), 'card: an unedited old page is ticked, and the confirmation is required' );
$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-03-03 10:00:00' ), array( 'ID' => $id ) ); clean_post_cache( $id );
ob_start(); mcupr_so_card(); $card = ob_get_clean();
t( preg_match( '/value="history" id="so-history"(?! checked)/', $card ), 'card: an edited page is listed but not ticked' );

// 5. settings
$keep = get_theme_mods();
set_theme_mod( 'mcupr_aud_1_title', 'OLD CARD' ); set_theme_mod( 'mcupr_svc_7_title', 'left over' ); set_theme_mod( 'mcupr_svc_7_url', '/x/' ); set_theme_mod( 'mcupr_num_bachelor', '18,000' ); set_theme_mod( 'mcupr_num_teachers', '1,250' ); set_theme_mod( 'mcupr_abt_text', 'old about' );
$st = mcupr_ss_starter();
mcupr_ss_apply( $st );
t( 'OLD CARD' === get_theme_mod( 'mcupr_aud_1_title' ) && '1,250' === get_theme_mod( 'mcupr_num_teachers' ), 'settings: the normal import still leaves filled blocks alone' );
mcupr_so_backup_settings();
$bkp = get_option( 'mcupr_so_settings_backup' );
t( is_array( $bkp ) && 'OLD CARD' === ( $bkp['data']['theme_mods']['mcupr_aud_1_title'] ?? '' ), 'settings: the old settings are saved as a file first' );
mcupr_ss_apply( $st, null, true );
t( $st['theme_mods']['mcupr_aud_1_title'] === get_theme_mod( 'mcupr_aud_1_title' ) && '36' === get_theme_mod( 'mcupr_num_teachers' ), 'settings: overwrite puts the starter cards and numbers in' );
t( null === get_theme_mod( 'mcupr_svc_7_title', null ) && null === get_theme_mod( 'mcupr_svc_7_url', null ), 'settings: a card the starter does not have is gone, not left behind' );
t( null === get_theme_mod( 'mcupr_num_bachelor', null ) || '' === get_theme_mod( 'mcupr_num_bachelor' ), 'settings: a number the starter leaves out is cleared' );
t( $st['theme_mods']['mcupr_abt_text'] === get_theme_mod( 'mcupr_abt_text' ), 'settings: the home page "about" text is the starter\'s' );
foreach ( get_theme_mods() as $k => $v ) { remove_theme_mod( $k ); }
foreach ( (array) $keep as $k => $v ) { set_theme_mod( $k, $v ); }
delete_option( 'mcupr_so_settings_backup' );
$set( $pages['history']['content'] );
$wpdb->update( $wpdb->posts, array( 'post_modified' => '2025-01-01 09:00:00' ), array( 'ID' => $id ) );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
