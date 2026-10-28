<?php
/**
 * Putting the latest starter in over a site that has been lived in: some starter pages untouched, some edited, some
 * deleted or moved to draft, one built with Elementor, pages with their own template / parent / picture / author;
 * plus pages, news, menus and settings that are not the starter's at all. Nothing outside the ticked pages and the
 * starter's own settings may change, and what was overwritten must be possible to bring back.
 *   php php/starter-overwrite-mixed.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );
$pages = mcupr_so_pages();
$old   = static function ( $id ) use ( $wpdb ) { // as if imported long ago and never touched
	$wpdb->update( $wpdb->posts, array( 'post_modified' => '2025-01-01 09:00:00', 'post_date' => '2025-01-01 09:00:00' ), array( 'ID' => $id ) );
	delete_post_meta( $id, '_mcupr_so_hash' ); clean_post_cache( $id );
};
$edit = static function ( $id, $content ) use ( $wpdb ) { // changed by an editor afterwards
	$wpdb->update( $wpdb->posts, array( 'post_content' => $content, 'post_modified' => '2026-02-02 10:00:00' ), array( 'ID' => $id ) );
	delete_post_meta( $id, '_mcupr_so_hash' ); clean_post_cache( $id );
};
$P = static function ( $slug ) { $p = mcupr_so_find( $slug ); return $p ? $p->ID : 0; };

// what the site is before the test, to leave it as it was (the tests after this one read these pages)
$orig_rows = array(); $orig_meta = array();
foreach ( array( 'history', 'about', 'contact', 'sitemap', 'ita', 'oic' ) as $sl ) {
	$pp = mcupr_so_find( $sl );
	if ( $pp ) { $orig_rows[ $pp->ID ] = (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $pp->ID ), ARRAY_A ); $orig_meta[ $pp->ID ] = get_post_meta( $pp->ID ); }
}
$orig_mods = get_option( 'theme_mods_' . get_option( 'stylesheet' ) );
$orig_red  = get_option( 'mcupr_redirects', false );
// ---- the site as it is lived in
$ids = array( 'history' => $P( 'history' ), 'about' => $P( 'about' ), 'contact' => $P( 'contact' ), 'sitemap' => $P( 'sitemap' ), 'ita' => $P( 'ita' ), 'oic' => $P( 'oic' ) );
t( ! in_array( 0, $ids, true ), 'setup: six starter pages are on the test site' );
$author = wp_insert_user( array( 'user_login' => 'mixed-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
$att    = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'mixed pic', 'post_status' => 'inherit' ), '' );

foreach ( $ids as $i ) { $wpdb->update( $wpdb->posts, array( 'post_content' => '<p>old starter text</p>', 'post_excerpt' => '' ), array( 'ID' => $i ) ); $old( $i ); }
$edit( $ids['about'], '<p>about: written by the web team</p>' );                      // edited
$edit( $ids['contact'], '<p>contact: built with Elementor</p>' );                      // edited and built with Elementor
update_post_meta( $ids['contact'], '_elementor_edit_mode', 'builder' );
update_post_meta( $ids['contact'], '_elementor_data', '[{"id":"a1","elType":"section","elements":[]}]' );
$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $ids['sitemap'] ) );          // moved to draft by the owner
$wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $ids['ita'] ) );
wp_trash_post( $ids['oic'] );                                                           // deleted (in the bin)
// a page keeps its own settings
$parent = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My parent', 'post_name' => 'my-parent' ) );
$wpdb->update( $wpdb->posts, array( 'post_parent' => $parent, 'menu_order' => 7, 'post_author' => $author, 'post_password' => 'secret', 'comment_status' => 'closed' ), array( 'ID' => $ids['history'] ) );
update_post_meta( $ids['history'], '_wp_page_template', 'page-wide.php' );
set_post_thumbnail( $ids['history'], $att );
update_post_meta( $ids['history'], '_mcupr_en_title', 'My own English title' );
$wpdb->update( $wpdb->posts, array( 'post_excerpt' => 'my own description of history' ), array( 'ID' => $ids['history'] ) );
$wpdb->update( $wpdb->posts, array( 'post_content' => '<p>old starter text</p>' ), array( 'ID' => $ids['history'] ) ); $old( $ids['history'] );
$snap = static function ( $id ) { $p = get_post( $id ); return array( $p->post_parent, $p->menu_order, $p->post_author, $p->post_password, $p->comment_status, $p->post_date, $p->post_name, $p->post_status, get_post_meta( $id, '_wp_page_template', true ), get_post_thumbnail_id( $id ) ); };
$h0 = $snap( $ids['history'] );

// things that are not the starter's
$mine = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My own page', 'post_name' => 'my-own-page', 'post_content' => '<p>mine</p>' ) );
$news = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'My own news', 'post_content' => '<p>news</p>' ) );
$cmt  = wp_insert_comment( array( 'comment_post_ID' => $news, 'comment_content' => 'hello', 'comment_author' => 'a', 'comment_approved' => 1 ) );
$menu = wp_create_nav_menu( 'Mixed menu' );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Mine', 'menu-item-url' => home_url( '/mine/' ), 'menu-item-status' => 'publish' ) );
set_theme_mod( 'mcupr_primary', '#123456' );
set_theme_mod( 'mcupr_social_facebook', 'https://facebook.com/my-own' );
set_theme_mod( 'mcupr_aud_1_title', 'My own audience card' );                           // a starter group, edited
set_theme_mod( 'mcupr_aud_1_url', '/my-own/' );
$defs = mcupr_blk_defs(); set_theme_mod( 'mcupr_aud_' . (int) $defs['aud'][0] . '_title', 'last slot card' );
list( $rule0 ) = mcupr_ins_clean_rule( '/old-mine/', '/new-mine/', 301 );
update_option( 'mcupr_redirects', array( $rule0 ), false );
$red0 = get_option( 'mcupr_redirects' );
$count = static function () use ( $wpdb ) { return array( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type IN ('post','nav_menu_item','attachment') AND post_status<>'auto-draft'" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->comments" ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->terms" ) ); };
$c0 = $count();
$mods0 = get_theme_mods();

// ---- 1. the card's view of the site
$st = array();
foreach ( array( 'history', 'about', 'contact', 'sitemap', 'ita', 'oic' ) as $s ) { $st[ $s ] = mcupr_so_state( mcupr_so_find( $s ), $pages[ $s ] ); }
t( 'plain' === $st['history'] && 'edited' === $st['about'] && 'edited' === $st['contact'], 'card: untouched = plain, edited = edited (' . json_encode( $st ) . ')' );
t( 'plain' === $st['sitemap'] && 'plain' === $st['ita'], 'card: draft and private starter pages are listed too, as plain' );
t( 'missing' === $st['oic'], 'card: a page in the bin counts as not on this site' );
ob_start(); mcupr_so_card(); $html = ob_get_clean();
t( false !== strpos( $html, 'value="history"' ) && preg_match( '/value="history"[^>]*checked/', $html ) && ! preg_match( '/value="about"[^>]*checked/', $html ) && false === strpos( $html, 'value="oic"' ), 'card: untouched pages ticked, edited pages not ticked, the deleted one not listed' );

// ---- 2. overwrite only what is ticked
$r = mcupr_so_replace_pages( array( 'history', 'sitemap', 'ita', 'oic', 'contact' ) );
t( 4 === $r['replaced'] && 1 === $r['missing'], 'replace: 5 ticked -> 4 replaced and the deleted one reported as not on the site (' . json_encode( $r ) . ')' );
t( '<p>about: written by the web team</p>' === get_post( $ids['about'] )->post_content, 'replace: an edited page that was not ticked keeps its text' );
t( 'trash' === get_post( $ids['oic'] )->post_status && ! mcupr_so_find( 'oic' ), 'replace: the page in the bin stays in the bin and is not made again' );
t( 'draft' === get_post( $ids['sitemap'] )->post_status && 'private' === get_post( $ids['ita'] )->post_status, 'replace: draft and private pages stay draft and private' );
t( $h0 === $snap( $ids['history'] ), 'replace: address, parent, order, author, password, comments, date, template and picture of a page are kept (' . json_encode( array( $h0, $snap( $ids['history'] ) ) ) . ')' );
t( $pages['history']['content'] === get_post( $ids['history'] )->post_content, 'replace: the text of the page is the starter\'s' );
t( $mine && '<p>mine</p>' === get_post( $mine )->post_content && 'My own news' === get_post( $news )->post_title, 'untouched: the site\'s own page and news' );
t( $c0 === $count(), 'untouched: news, menu items, media, comments and categories are exactly as many as before' );
t( 1 === (int) get_comment( $cmt )->comment_approved && 'hello' === get_comment( $cmt )->comment_content, 'untouched: the comment' );
t( 'Mixed menu' === wp_get_nav_menu_object( $menu )->name && 1 === count( wp_get_nav_menu_items( $menu ) ), 'untouched: the menu and its items' );

// a page built with Elementor must really show the new text
$cid = $ids['contact'];
$builder = get_post_meta( $cid, '_elementor_data', true );
t( $pages['contact']['content'] === get_post( $cid )->post_content, 'replace (Elementor): the text of the page is the starter\'s' );
t( '' === (string) $builder || '' === (string) get_post_meta( $cid, '_elementor_edit_mode', true ), 'replace (Elementor): the old Elementor design no longer takes the place of the new text (data: ' . substr( (string) $builder, 0, 30 ) . ')' );

// what was written over can be found again
$bk = get_post_meta( $ids['history'], '_mcupr_so_backup', true );
t( is_array( $bk ) && isset( $bk['excerpt'] ) && 'my own description of history' === $bk['excerpt'], 'backup: the old description (excerpt) of the page is kept too' );
t( is_array( $bk ) && isset( $bk['meta']['_mcupr_en_title'] ) && 'My own English title' === $bk['meta']['_mcupr_en_title'], 'backup: the old translated titles are kept too' );
t( function_exists( 'mcupr_so_restore_page' ) && ! empty( mcupr_so_restore_page( 'history' ) ) && 'my own description of history' === get_post( $ids['history'] )->post_excerpt && 'My own English title' === get_post_meta( $ids['history'], '_mcupr_en_title', true ), 'restore: one page can be put back as it was (text, description, translated titles)' );
t( function_exists( 'mcupr_so_restore_page' ) && $pages['history']['content'] !== get_post( $ids['history'] )->post_content, 'restore: the page has its old text again' );
if ( function_exists( 'mcupr_so_restore_page' ) ) { mcupr_so_replace_pages( array( 'history' ) ); }

// ---- 3. settings
$starter = mcupr_ss_starter();
$rep = mcupr_ss_apply( $starter, null, false );
t( 'My own audience card' === get_theme_mod( 'mcupr_aud_1_title' ), 'normal import: the group the site already uses is left as it is' );
$ex0 = mcupr_ss_export();
if ( function_exists( 'mcupr_so_backup_settings' ) ) { mcupr_so_backup_settings(); }
mcupr_ss_apply( $starter, null, true );
t( 'My own audience card' !== get_theme_mod( 'mcupr_aud_1_title' ) && '' === (string) get_theme_mod( 'mcupr_aud_' . (int) mcupr_blk_defs()['aud'][0] . '_title', '' ) || mcupr_blk_defs()['aud'][0] <= 3, 'overwrite: the starter\'s cards replace the site\'s (and a card in a later slot, beyond the file\'s, is gone)' );
t( '#123456' === get_theme_mod( 'mcupr_primary' ) && 'https://facebook.com/my-own' === get_theme_mod( 'mcupr_social_facebook' ), 'overwrite: settings that are not in the starter (colour, social links) are kept' );
$bks = function_exists( 'mcupr_so_settings_backups' ) ? mcupr_so_settings_backups() : array();
$first = $bks ? end( $bks ) : null; // oldest
$newest = $bks ? reset( $bks ) : null;
t( $bks && isset( $newest['data']['theme_mods']['mcupr_aud_1_title'] ) && 'My own audience card' === $newest['data']['theme_mods']['mcupr_aud_1_title'], 'backup: the settings as they were are saved before the overwrite' );
mcupr_ss_apply( $starter, null, true );
if ( function_exists( 'mcupr_so_backup_settings' ) ) { mcupr_so_backup_settings(); }
mcupr_ss_apply( $starter, null, true );
$bks = function_exists( 'mcupr_so_settings_backups' ) ? mcupr_so_settings_backups() : array();
$has = false;
foreach ( $bks as $b ) { if ( isset( $b['data']['theme_mods']['mcupr_aud_1_title'] ) && 'My own audience card' === $b['data']['theme_mods']['mcupr_aud_1_title'] ) { $has = true; } }
t( $has, 'backup: overwriting again does not throw away the first backup (the original site)' );
$res = function_exists( 'mcupr_so_restore_settings' ) ? mcupr_so_restore_settings( count( $bks ) - 1 ) : false;
t( $res && 'My own audience card' === get_theme_mod( 'mcupr_aud_1_title' ) && '/my-own/' === get_theme_mod( 'mcupr_aud_1_url' ), 'restore: the settings come back as they were (cards)' );
$ex1 = mcupr_ss_export();
$diff = array();
foreach ( array_unique( array_merge( array_keys( $ex0['theme_mods'] ), array_keys( $ex1['theme_mods'] ) ) ) as $k ) {
	if ( ( isset( $ex0['theme_mods'][ $k ] ) ? $ex0['theme_mods'][ $k ] : null ) !== ( isset( $ex1['theme_mods'][ $k ] ) ? $ex1['theme_mods'][ $k ] : null ) ) { $diff[] = $k; }
}
t( ! $diff, 'restore: every setting is exactly as before the overwrites (differences: ' . implode( ',', array_slice( $diff, 0, 8 ) ) . ')' );
t( $red0 === get_option( 'mcupr_redirects' ), 'restore: the redirects are exactly as before too (the starter\'s were taken out again)' );
t( $mods0['mcupr_primary'] === get_theme_mod( 'mcupr_primary' ), 'restore: the colour is still the site\'s' );
t( $c0 === $count(), 'untouched (end): news, menu items, media, comments and categories are still exactly as many as before' );

// ---- leave the site as it was
foreach ( array( $mine, $news, $parent ) as $pid ) { if ( $pid ) { wp_delete_post( $pid, true ); } }
wp_delete_attachment( $att, true );
wp_delete_nav_menu( $menu );
wp_delete_user( $author );
foreach ( $orig_rows as $oid => $row ) {
	$wpdb->update( $wpdb->posts, $row, array( 'ID' => $oid ) );
	foreach ( array_keys( get_post_meta( $oid ) ) as $k ) { delete_post_meta( $oid, $k ); }
	foreach ( $orig_meta[ $oid ] as $k => $vals ) { foreach ( $vals as $v ) { add_post_meta( $oid, $k, maybe_unserialize( $v ) ); } }
	foreach ( wp_get_post_revisions( $oid ) as $rv ) { wp_delete_post_revision( $rv->ID ); }
	clean_post_cache( $oid );
}
update_option( 'theme_mods_' . get_option( 'stylesheet' ), $orig_mods );
if ( false === $orig_red ) { delete_option( 'mcupr_redirects' ); } else { update_option( 'mcupr_redirects', $orig_red, false ); }
delete_option( 'mcupr_so_settings_backup' ); delete_option( 'mcupr_so_settings_backups' );
t( 'trash' !== get_post_status( $ids['oic'] ) && mcupr_so_find( 'oic' ) && 'publish' === mcupr_so_find( 'history' )->post_status, 'cleanup: the test site is as it was (the pages are back, the bin is empty of them)' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
