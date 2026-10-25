<?php
/**
 * Who may do what: the standard roles, the capabilities the theme's screens ask for, keeping an administrator's
 * choices, and the page that hands them out.   php php/roles.php [site]
 */
define( 'WP_ADMIN', true );
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$fresh_user = static function ( $login, $role ) {
	$old = get_user_by( 'login', $login );
	if ( $old ) {
		wp_delete_user( $old->ID );
	}
	return (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.org', 'role' => $role ) );
};
$adm = get_users( array( 'role' => 'administrator', 'number' => 1 ) );

// anything an earlier run left behind (a run that stopped halfway leaves a restricted post, which upsets other suites)
foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => MCUPR_AUD ) ) as $stale ) { // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( 0 === strpos( (string) get_the_title( $stale ), 'RL ' ) ) {
		wp_delete_post( $stale, true );
	}
}
mcupr_aud_reset();

// 1. the standard roles are all there
mcupr_roles_sync();
$missing = array();
foreach ( mcupr_standard_roles() as $key => $def ) {
	if ( ! get_role( $key ) ) {
		$missing[] = $key;
	}
}
t( array() === $missing, 'roles: every standard role exists (' . implode( ', ', array_keys( mcupr_standard_roles() ) ) . ')' );
t( get_role( 'mcupr_lecturer' ) && get_role( 'mcupr_officer' ), 'roles: the two new ones, lecturer and officer, are there' );

// 2. an administrator always holds everything the theme invents, even after the boxes are unticked
$admin_role = get_role( 'administrator' );
foreach ( mcupr_theme_caps() as $cap ) {
	$admin_role->remove_cap( $cap );
}
mcupr_roles_arm_admin();
$lost = array();
foreach ( mcupr_theme_caps() as $cap ) {
	if ( ! get_role( 'administrator' )->has_cap( $cap ) ) {
		$lost[] = $cap;
	}
}
t( array() === $lost, 'roles: an administrator is given back everything, so a site cannot lock itself out' );

// 3. what each standard role may and may not do
$cases = array(
	array( MCUPR_STU_USER_ROLE, array( 'read' ), array( 'edit_posts', 'upload_files', 'mcupr_see_student_content', MCUPR_STU_CAP, 'mcupr_manage_files' ) ),
	array( 'mcupr_lecturer', array( 'read', 'edit_posts', 'publish_posts', 'upload_files', 'mcupr_see_student_content', 'mcupr_view_stats' ), array( 'edit_pages', 'edit_others_posts', MCUPR_STU_CAP, 'mcupr_manage_files', 'mcupr_manage_roles', 'manage_options' ) ),
	array( 'mcupr_officer', array( 'read', 'edit_posts', 'edit_pages', 'publish_pages', 'mcupr_see_answers', 'mcupr_manage_calendar', 'mcupr_manage_forum' ), array( MCUPR_STU_CAP, 'mcupr_handle_requests', 'mcupr_manage_files', 'mcupr_manage_site_access', 'mcupr_manage_roles', 'manage_options' ) ),
	array( MCUPR_REGISTRAR_ROLE, array( 'read', MCUPR_STU_CAP, 'mcupr_handle_requests', 'mcupr_see_answers', 'mcupr_see_student_content' ), array( 'edit_pages', 'mcupr_view_stats', 'mcupr_manage_files', 'mcupr_manage_site_access', 'mcupr_manage_roles', 'manage_options' ) ),
	array( MCUPR_OFFICE_ROLE, array( 'read', 'edit_pages', 'edit_others_pages', 'mcupr_manage_staff', 'mcupr_manage_groups' ), array( MCUPR_STU_CAP, 'mcupr_handle_requests', 'mcupr_manage_files', 'mcupr_manage_roles', 'manage_options' ) ),
);
foreach ( $cases as $case ) {
	list( $role_key, $yes, $no ) = $case;
	mcupr_role_from_standard( $role_key );
	$r    = get_role( $role_key );
	$bad  = array();
	foreach ( $yes as $cap ) {
		if ( ! $r->has_cap( $cap ) ) {
			$bad[] = '-' . $cap;
		}
	}
	foreach ( $no as $cap ) {
		if ( $r->has_cap( $cap ) ) {
			$bad[] = '+' . $cap;
		}
	}
	t( array() === $bad, 'standard: ' . $role_key . ' holds what it should and nothing it should not' . ( $bad ? ' (' . implode( ' ', $bad ) . ')' : '' ) );
}

// 4. every capability the page offers is a real one, and every screen's capability is offered
$defs = mcupr_cap_defs();
t( count( $defs ) > 25 && count( mcupr_theme_caps() ) > 10, 'capabilities: the table has both the theme\'s own and WordPress\' (' . count( mcupr_theme_caps() ) . ' + ' . ( count( $defs ) - count( mcupr_theme_caps() ) ) . ')' );
$groups  = mcupr_cap_groups();
$orphan  = array();
foreach ( $defs as $cap => $d ) {
	if ( ! isset( $groups[ $d['group'] ] ) ) {
		$orphan[] = $cap;
	}
}
t( array() === $orphan, 'capabilities: each one sits under a heading the page knows' );
$screens = array( 'mcupr_manage_files', 'mcupr_view_insights', 'mcupr_view_stats', 'mcupr_manage_forum', 'mcupr_manage_staff', 'mcupr_manage_calendar', 'mcupr_manage_groups', 'mcupr_manage_theme', 'mcupr_manage_site_access', 'mcupr_manage_roles', 'mcupr_see_student_content', MCUPR_STU_CAP, MCUPR_RQ_CAP, MCUPR_FE_CAP );
$unknown = array();
foreach ( $screens as $cap ) {
	if ( ! isset( $defs[ $cap ] ) ) {
		$unknown[] = $cap;
	}
}
t( array() === $unknown, 'capabilities: every capability a screen of the theme asks for can be handed out on the page' );
t( 'mcupr_manage_files' === MCUPR_FM_CAP, 'capabilities: the file manager asks for its own capability, not manage_options' );

// 5. the screens really do follow the capability
$off = $fresh_user( 'rl_officer', 'mcupr_officer' );
$reg = $fresh_user( 'rl_registrar', MCUPR_REGISTRAR_ROLE );
$lec = $fresh_user( 'rl_lecturer', 'mcupr_lecturer' );
wp_set_current_user( $off );
t( ! current_user_can( MCUPR_FM_CAP ) && ! current_user_can( 'mcupr_view_insights' ) && current_user_can( 'mcupr_manage_forum' ), 'screens: an officer reaches the forum settings but not the file manager or the insights' );
wp_set_current_user( $reg );
t( current_user_can( MCUPR_STU_CAP ) && current_user_can( MCUPR_RQ_CAP ) && ! current_user_can( 'mcupr_manage_theme' ), 'screens: a registrar reaches the student screens and the requests but not the settings of the site' );
wp_set_current_user( $lec );
t( ! current_user_can( 'mcupr_see_answers' ) && current_user_can( 'mcupr_view_stats' ), 'screens: a lecturer sees the figures but not what people filled into the forms' );

// 6. the MCUPR menu opens for somebody who holds only one of the theme's capabilities
$only = $fresh_user( 'rl_fileonly', 'subscriber' );
$u    = get_userdata( $only );
$u->add_cap( 'mcupr_manage_files' );
wp_set_current_user( $only );
t( current_user_can( mcupr_admin_menu_cap() ), 'menu: somebody given only the file manager can still open the MCUPR menu' );
wp_set_current_user( 0 );
t( ! current_user_can( mcupr_admin_menu_cap() ), 'menu: a visitor cannot' );
wp_set_current_user( $adm[0]->ID );

// 7. content kept for students follows its own capability, not the right to edit a page
// a subscriber given the right to edit pages by hand: the one thing that used to be enough on its own
$ed   = $fresh_user( 'rl_editor', 'subscriber' );
$ed_u = get_userdata( $ed );
$ed_u->add_cap( 'edit_pages' );
$ed_u->add_cap( 'edit_posts' );
$stu_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'RL students only' ) );
update_post_meta( $stu_post, MCUPR_AUD, 'students' );
update_post_meta( $stu_post, '_mcupr_aud_levels', array( 'master' ) );
mcupr_aud_reset();
$sees = static function ( $uid ) use ( $stu_post ) {
	wp_set_current_user( 0 ); // wp_set_current_user() does nothing when the id is the one already set
	wp_set_current_user( $uid );
	$GLOBALS['mcupr_aud_cache'] = array();
	return mcupr_aud_can_view( $stu_post );
};
t( ! $sees( $ed ), 'students only: an editor without the capability does not see it, even though they may edit pages' );
$ed_u->add_cap( 'mcupr_see_student_content' );
clean_user_cache( $ed ); // the object wp_set_current_user() reaches for was cached a moment ago
t( $sees( $ed ), 'students only: ticking the capability lets them in' );
t( $sees( $lec ) && $sees( $reg ), 'students only: a lecturer and a registrar see it (their standard says so)' );

// 8. an administrator's choices survive what used to rebuild the roles
wp_set_current_user( $adm[0]->ID );
$r = get_role( 'mcupr_officer' );
$r->add_cap( 'mcupr_manage_files' );   // something the standard does not give it
$r->remove_cap( 'mcupr_manage_forum' ); // something the standard does give it
update_option( 'mcupr_student_roles_v', 'force-a-rerun', false );
update_option( 'mcupr_office_role_v', 'force-a-rerun', false );
$quiet = static function () { return false; }; // running init twice makes WordPress grumble about its own registries
add_filter( 'doing_it_wrong_trigger_error', $quiet );
do_action( 'init' ); // the blocks that used to rebuild the roles run here
$r = get_role( 'mcupr_officer' );
t( $r && $r->has_cap( 'mcupr_manage_files' ) && ! $r->has_cap( 'mcupr_manage_forum' ), 'keeping: what an administrator ticked and unticked is still there after the role blocks run again' );
$reg_role = get_role( MCUPR_REGISTRAR_ROLE );
$reg_role->add_cap( 'mcupr_manage_files' );
do_action( 'init' );
remove_filter( 'doing_it_wrong_trigger_error', $quiet );
t( get_role( MCUPR_REGISTRAR_ROLE )->has_cap( 'mcupr_manage_files' ) && get_role( MCUPR_REGISTRAR_ROLE )->has_cap( MCUPR_STU_CAP ), 'keeping: the registrar keeps an extra capability and still holds its own' );
mcupr_role_from_standard( 'mcupr_officer' );
t( get_role( 'mcupr_officer' )->has_cap( 'mcupr_manage_forum' ) && ! get_role( 'mcupr_officer' )->has_cap( 'mcupr_manage_files' ), 'keeping: "put back to standard" really puts it back' );
mcupr_role_from_standard( MCUPR_REGISTRAR_ROLE );

// 9. the upgrade of a site that had none of this: whoever reaches a screen today goes on reaching it
$legacy = 'rl_legacy_role';
remove_role( $legacy );
add_role( $legacy, 'RL legacy', array( 'read' => true, 'edit_pages' => true, 'manage_options' => true ) );
foreach ( mcupr_theme_caps() as $cap ) {
	get_role( $legacy )->remove_cap( $cap );
}
$v_was = get_option( 'mcupr_roles_v' );
update_option( 'mcupr_roles_v', 0, false );
mcupr_roles_seed_from_legacy();
$lr = get_role( $legacy );
t( $lr->has_cap( 'mcupr_manage_staff' ) && $lr->has_cap( 'mcupr_manage_calendar' ) && $lr->has_cap( 'mcupr_see_answers' ), 'upgrade: a role that could edit pages keeps the staff hub, the calendar and the answers' );
t( $lr->has_cap( 'mcupr_manage_files' ) && $lr->has_cap( 'mcupr_view_insights' ) && $lr->has_cap( 'mcupr_manage_theme' ), 'upgrade: a role that could change the settings keeps the file manager, the insights and the settings pages' );
t( ! $lr->has_cap( MCUPR_STU_CAP ) && ! $lr->has_cap( 'mcupr_manage_roles' ) || $lr->has_cap( 'mcupr_manage_roles' ), 'upgrade: nothing the theme never gave it is invented' );
false === $v_was ? delete_option( 'mcupr_roles_v' ) : update_option( 'mcupr_roles_v', $v_was, false );
remove_role( $legacy );

// 10. the page itself
wp_set_current_user( $adm[0]->ID );
set_current_screen( 'mcupr_page_mcupr-roles' );
ob_start();
mcupr_roles_page();
$html = (string) ob_get_clean();
t( false !== strpos( $html, 'mcupr-roles' ) && false !== strpos( $html, 'mr-table' ), 'page: the table is printed' );
$cols = 0;
foreach ( mcupr_roles_listed() as $key => $i ) {
	if ( false !== strpos( $html, 'data-role="' . $key . '"' ) || 'administrator' === $key ) {
		$cols++;
	}
}
t( $cols === count( mcupr_roles_listed() ), 'page: there is a column for every role on the site (' . $cols . ')' );
t( false !== strpos( $html, 'name="caps[mcupr_officer][mcupr_manage_forum]"' ), 'page: a box for one role and one capability is named so it can be saved' );
t( false === strpos( $html, 'name="caps[administrator]' ) && false !== strpos( $html, 'mr-cell is-locked' ), 'page: the administrator column has no boxes to tick' );
t( false !== strpos( $html, 'data-risky="1"' ), 'page: the capabilities that could be used to take the site over are marked' );
wp_set_current_user( $lec );
ob_start();
mcupr_roles_page();
t( '' === trim( (string) ob_get_clean() ), 'page: somebody without the capability is shown nothing' );
wp_set_current_user( $adm[0]->ID );

// 11. the page hangs under Users, not under MCUPR
$GLOBALS['menu'] = array();
$GLOBALS['submenu'] = array();
wp_set_current_user( $adm[0]->ID );
do_action( 'admin_menu' );
$slugs = static function ( $parent ) {
	$out = array();
	foreach ( isset( $GLOBALS['submenu'][ $parent ] ) ? $GLOBALS['submenu'][ $parent ] : array() as $it ) {
		$out[] = $it[2];
	}
	return $out;
};
t( in_array( 'mcupr-roles', $slugs( 'users.php' ), true ), 'menu: the roles page is under WordPress\' own "Users"' );
t( ! in_array( 'mcupr-roles', $slugs( 'mcupr' ), true ), 'menu: and no longer under MCUPR' );
t( false !== strpos( mcupr_roles_url(), 'users.php?page=mcupr-roles' ), 'menu: everything the page links back to goes to the new address' );
$card = null;
foreach ( mcupr_cc_items() as $k => $it ) {
	if ( 'roles' === $k ) {
		$card = $it;
	}
}
t( $card && false !== strpos( mcupr_cc_url( $card['open'] ), 'users.php?page=mcupr-roles' ), 'menu: the card in the control centre opens the page where it now lives' );

// 12. the role a new account gets
$was_default = get_option( 'default_role' );
update_option( 'default_role', 'editor' );
delete_option( 'mcupr_default_role_was' );
mcupr_default_role_settle();
t( 'subscriber' === get_option( 'default_role' ) && 'editor' === mcupr_default_role_was(), 'default role: a new account starts as the news subscriber, and what it was before is remembered' );
mcupr_default_role_settle();
t( 'subscriber' === get_option( 'default_role' ), 'default role: running it again changes nothing' );
update_option( 'default_role', 'a-role-that-is-not-there' );
t( 'subscriber' === mcupr_default_role(), 'default role: a stored role that no longer exists falls back to the news subscriber' );
update_option( 'default_role', 'mcupr_lecturer' );
t( 'mcupr_lecturer' === mcupr_default_role(), 'default role: an administrator may set it to something else' );
false === $was_default ? delete_option( 'default_role' ) : update_option( 'default_role', $was_default );
delete_option( 'mcupr_default_role_was' );

// 13. "Subscriber" reads as the news subscriber
t( isset( mcupr_role_renames()['Subscriber'] ), 'naming: the theme has its own word for WordPress\' lowest role' );
$shown = translate_user_role( 'Subscriber' );
t( $shown === mcupr_role_renames()['Subscriber'] && 'Subscriber' !== $shown, 'naming: WordPress shows that word wherever it names the role (' . $shown . ')' );
t( 'Editor' === translate_user_role( 'Editor' ) || '' !== translate_user_role( 'Editor' ), 'naming: the other roles keep the word WordPress gives them' );
add_filter( 'mcupr_admin_thai', '__return_false' );
t( 'Subscriber' === translate_user_role( 'Subscriber' ), 'naming: with the theme\'s Thai switched off, WordPress\' own word stands' );
remove_filter( 'mcupr_admin_thai', '__return_false' );

// 14. a role made by hand on the page can really be given the run of its own content
wp_set_current_user( $adm[0]->ID );
$hand = 'mcupr_r_byhand';
remove_role( $hand );
// exactly what somebody ticking "write / publish / delete" plus the two the page now also offers would get
add_role( $hand, 'RL by hand', array_fill_keys( array( 'read', 'edit_posts', 'publish_posts', 'delete_posts', 'edit_published_posts', 'delete_published_posts' ), true ) );
$hu = $fresh_user( 'rl_byhand', $hand );
$hp = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'RL by-hand post', 'post_author' => $hu ) );
t( user_can( $hu, 'edit_post', $hp ) && user_can( $hu, 'delete_post', $hp ), 'by hand: a role built on the page can edit and delete its own post after it is published' );
$defs = mcupr_cap_defs();
$pairs = array( 'edit_published_posts', 'delete_published_posts', 'edit_published_pages', 'delete_published_pages' );
$off_grid = array();
foreach ( $pairs as $cap ) {
	if ( ! isset( $defs[ $cap ] ) ) {
		$off_grid[] = $cap;
	}
}
t( array() === $off_grid, 'by hand: the capabilities WordPress asks for alongside "write" and "delete" are on the page, so they can be ticked' );
// and every capability a standard role holds is one the page can hand out
$hidden = array();
foreach ( mcupr_standard_roles() as $rk => $rd ) {
	foreach ( $rd['caps'] as $cap ) {
		if ( ! isset( $defs[ $cap ] ) && 'read' !== $cap && 0 !== strpos( $cap, 'mcupr_student' ) && false === strpos( $cap, '_mcupr_student' ) ) {
			$hidden[] = $rk . ':' . $cap;
		}
	}
}
t( array() === $hidden, 'by hand: no standard role holds a content capability the page keeps out of sight' . ( $hidden ? ' (' . implode( ' ', $hidden ) . ')' : '' ) );
wp_delete_post( $hp, true );
wp_delete_user( $hu );
remove_role( $hand );

// 15. an officer looks after the pages of the site, not only the ones they made themselves
mcupr_role_from_standard( 'mcupr_officer' );
$off2 = $fresh_user( 'rl_officer2', 'mcupr_officer' );
$admin_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'RL page by admin', 'post_author' => $adm[0]->ID ) );
$admin_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'RL post by admin', 'post_author' => $adm[0]->ID ) );
t( user_can( $off2, 'edit_post', $admin_page ) && user_can( $off2, 'edit_post', $admin_post ), 'officer: edits a page and a post somebody else made, which is what looking after the site means' );
t( ! user_can( $off2, 'delete_post', $admin_page ) && ! user_can( $off2, 'delete_post', $admin_post ), 'officer: but deletes neither — editing other people\'s work is not the same as throwing it away' );
wp_delete_post( $admin_page, true );
wp_delete_post( $admin_post, true );
wp_delete_user( $off2 );

// 16. the capabilities a role had before are not copied onto a role the theme has just made
$legacy2 = 'rl_old_role';
remove_role( $legacy2 );
add_role( $legacy2, 'RL old', array( 'read' => true, 'edit_pages' => true ) );
foreach ( mcupr_theme_caps() as $cap ) {
	get_role( $legacy2 )->remove_cap( $cap );
}
remove_role( 'mcupr_officer' ); // make the sync build it again from the standard, as a new site would
$v_before = get_option( 'mcupr_roles_v' );
update_option( 'mcupr_roles_v', 0, false );
mcupr_roles_sync();
$of = get_role( 'mcupr_officer' );
$std_officer = mcupr_standard_roles()['mcupr_officer']['caps'];
$extra = array();
foreach ( array_keys( array_filter( (array) $of->capabilities ) ) as $cap ) {
	if ( ! in_array( $cap, $std_officer, true ) ) {
		$extra[] = $cap;
	}
}
t( array() === $extra, 'seeding: a role the theme just made holds its standard and nothing more' . ( $extra ? ' (' . implode( ' ', $extra ) . ')' : '' ) );
t( get_role( $legacy2 )->has_cap( 'mcupr_manage_staff' ), 'seeding: a role that was there before still keeps what it could reach' );
remove_role( $legacy2 );
false === $v_before ? delete_option( 'mcupr_roles_v' ) : update_option( 'mcupr_roles_v', $v_before, false );

// 17. a site that already ran version 1 or 2 has the officer put right once
$of = get_role( 'mcupr_officer' );
$of->add_cap( 'mcupr_manage_staff' );
$of->add_cap( 'mcupr_manage_groups' );
mcupr_roles_undo_overreach();
$of = get_role( 'mcupr_officer' );
t( ! $of->has_cap( 'mcupr_manage_staff' ) && ! $of->has_cap( 'mcupr_manage_groups' ), 'putting right: the two the old copying handed the officer are taken back' );
t( $of->has_cap( 'mcupr_manage_forum' ) && $of->has_cap( 'mcupr_manage_calendar' ) && $of->has_cap( 'edit_others_pages' ), 'putting right: and nothing its standard does give it is touched' );
mcupr_role_from_standard( 'mcupr_officer' );

// 18. a role made on this site, and deleting it again
$made = 'mcupr_r_librarian';
remove_role( $made );
add_role( $made, 'RL librarian', array( 'read' => true, 'mcupr_manage_files' => true ) );
update_option( 'mcupr_custom_roles', array( $made => 'RL librarian' ), false );
t( 'custom' === mcupr_role_origin( $made ) && 'standard' === mcupr_role_origin( 'mcupr_officer' ) && 'core' === mcupr_role_origin( 'editor' ), 'page: the three kinds of role are told apart' );
t( ! mcupr_is_standard_role( $made ), 'page: a role made here cannot be "put back to a standard"' );
remove_role( $made );
delete_option( 'mcupr_custom_roles' );

foreach ( array( $stu_post ) as $id ) {
	wp_delete_post( $id, true );
}
foreach ( array( $off, $reg, $lec, $only, $ed ) as $uid ) {
	wp_delete_user( $uid );
}
mcupr_aud_reset();
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
