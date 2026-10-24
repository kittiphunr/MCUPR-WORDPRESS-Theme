<?php
/**
 * Who may do what: the standard roles of a university site, the theme's own capabilities, and the rules that keep
 * both in step with what an administrator has set by hand (MCUPR → สิทธิ์ผู้ใช้, inc/roles-admin.php).
 *
 * The theme used to ask WordPress' own capabilities at each of its screens — manage_options for the file manager,
 * edit_pages for the staff hub, edit_posts for the figures — which made "who may see the student records" and "who may
 * change the site's settings" the same question as "who may edit a page". Each of those screens now asks a capability
 * of its own, so they can be handed out one at a time.
 *
 * Two promises are kept here:
 *   - nothing a site can do today stops working: when the capabilities arrive, every role that held the capability a
 *     screen used to ask for is given the new one (mcupr_roles_seed_from_legacy);
 *   - what an administrator sets is never taken away again: a later version of the theme adds the capabilities it has
 *     newly introduced to the roles the standard says should hold them, and touches nothing else.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Bumped whenever mcupr_cap_defs() gains a capability; the roles are brought up to the standard for the new ones only. */
const MCUPR_ROLES_V = 1;

/* ================================================================== the capabilities */

/**
 * Every capability the page can hand out, in the order it is shown.
 *
 * group  – the heading it sits under
 * label  – what it is called
 * note   – one line of explanation, or ''
 * legacy – the capability the theme asked before this one existed ('' for the capabilities that already existed, and
 *          for WordPress' own)
 * danger – true for the few that let somebody take the site over; the page warns about them
 * core   – true for WordPress' own capabilities (the theme does not invent or remove them)
 */
function mcupr_cap_defs() {
	static $defs = null;
	if ( null !== $defs ) {
		return $defs;
	}
	$t = static function ( $group, $label, $note = '', $legacy = '', $danger = false, $core = false ) {
		return array( 'group' => $group, 'label' => $label, 'note' => $note, 'legacy' => $legacy, 'danger' => $danger, 'core' => $core );
	};
	$defs = array(
		// ---- the student records
		'mcupr_manage_students'      => $t( 'students', __( 'Student records', 'mcupr' ), __( 'Open, change and delete the records, make login accounts, and all the student screens of the theme.', 'mcupr' ) ),
		'mcupr_see_student_content'  => $t( 'students', __( 'Content kept for students', 'mcupr' ), __( 'See posts, pages, events and albums marked "students only" whatever their year or faculty rules say.', 'mcupr' ), 'edit_pages' ),
		// ---- requests and forms
		'mcupr_handle_requests'      => $t( 'requests', __( 'Handle requests', 'mcupr' ), __( 'See the requests, change their status, and open the papers the sender attached (a copy of an identity card, a house registration).', 'mcupr' ), '', true ),
		'mcupr_see_answers'          => $t( 'requests', __( 'See the answers of forms', 'mcupr' ), __( 'Open the list of what people filled in, and take it out as a file. The requests themselves need the capability above.', 'mcupr' ), 'edit_pages' ),
		// ---- people and offices
		'mcupr_manage_staff'         => $t( 'people', __( 'Staff and executives', 'mcupr' ), __( 'The staff hub: the profiles, the privacy defaults, the decorations catalogue and the import.', 'mcupr' ), 'edit_pages' ),
		'mcupr_manage_groups'        => $t( 'people', __( 'Divisions and groups', 'mcupr' ), __( 'Import and change the divisions of an office.', 'mcupr' ), 'edit_pages' ),
		// ---- the things a site runs day to day
		'mcupr_manage_calendar'      => $t( 'daily', __( 'Calendar feeds', 'mcupr' ), __( 'Add calendars the site fetches events from, and bring them in by hand.', 'mcupr' ), 'edit_pages' ),
		'mcupr_manage_forum'         => $t( 'daily', __( 'The forum', 'mcupr' ), __( 'Its settings, and seeing and moderating what is waiting.', 'mcupr' ), 'edit_pages' ),
		'mcupr_manage_files'         => $t( 'daily', __( 'The file manager', 'mcupr' ), __( 'Browse, upload, move, rename and delete everything in the uploads folder.', 'mcupr' ), 'manage_options', true ),
		// ---- figures
		'mcupr_view_stats'           => $t( 'reports', __( 'Visitor figures', 'mcupr' ), __( 'The panel on the dashboard: views, the pages read most, the files downloaded most.', 'mcupr' ), 'edit_posts' ),
		'mcupr_view_insights'        => $t( 'reports', __( 'Searches, missing pages and redirects', 'mcupr' ), __( 'What visitors searched for, the addresses that led nowhere, and the redirects.', 'mcupr' ), 'manage_options' ),
		// ---- the settings of the site
		'mcupr_manage_theme'         => $t( 'settings', __( 'Appearance and features', 'mcupr' ), __( 'The e-book shelf, the pictures, the blocks, the site notice, the Elementor content and the speed check.', 'mcupr' ), 'manage_options' ),
		'mcupr_manage_site_access'   => $t( 'settings', __( 'Sign-in and settings files', 'mcupr' ), __( 'The address of the sign-in page, signing in with Google or Facebook, and taking all the settings out or putting them back.', 'mcupr' ), 'manage_options', true ),
		'mcupr_manage_roles'         => $t( 'settings', __( 'User roles', 'mcupr' ), __( 'This page: who may do what. Somebody who can change it can give themselves anything.', 'mcupr' ), 'manage_options', true ),
		// ---- WordPress' own: posts
		'edit_posts'                 => $t( 'content', __( 'Write posts', 'mcupr' ), '', '', false, true ),
		'edit_others_posts'          => $t( 'content', __( 'Edit other people\'s posts', 'mcupr' ), '', '', false, true ),
		'publish_posts'              => $t( 'content', __( 'Publish posts', 'mcupr' ), '', '', false, true ),
		'delete_posts'               => $t( 'content', __( 'Delete own posts', 'mcupr' ), '', '', false, true ),
		'delete_others_posts'        => $t( 'content', __( 'Delete other people\'s posts', 'mcupr' ), '', '', false, true ),
		// ---- WordPress' own: pages
		'edit_pages'                 => $t( 'pages', __( 'Write pages', 'mcupr' ), '', '', false, true ),
		'edit_others_pages'          => $t( 'pages', __( 'Edit other people\'s pages', 'mcupr' ), '', '', false, true ),
		'publish_pages'              => $t( 'pages', __( 'Publish pages', 'mcupr' ), '', '', false, true ),
		'delete_pages'               => $t( 'pages', __( 'Delete own pages', 'mcupr' ), '', '', false, true ),
		'delete_others_pages'        => $t( 'pages', __( 'Delete other people\'s pages', 'mcupr' ), '', '', false, true ),
		// ---- WordPress' own: the rest
		'upload_files'               => $t( 'media', __( 'Upload files to the media library', 'mcupr' ), '', '', false, true ),
		'moderate_comments'          => $t( 'media', __( 'Approve comments', 'mcupr' ), '', '', false, true ),
		'manage_categories'          => $t( 'media', __( 'Manage categories and tags', 'mcupr' ), '', '', false, true ),
		'edit_theme_options'         => $t( 'media', __( 'Menus, widgets and the Customizer', 'mcupr' ), '', '', false, true ),
		'list_users'                 => $t( 'admin', __( 'See the list of users', 'mcupr' ), '', '', false, true ),
		'edit_users'                 => $t( 'admin', __( 'Add, change and delete users', 'mcupr' ), __( 'Includes setting somebody else\'s password, so it is as good as being an administrator.', 'mcupr' ), '', true, true ),
		'manage_options'             => $t( 'admin', __( 'All of WordPress\' own settings', 'mcupr' ), __( 'The whole Settings menu, and everything in WordPress that asks for an administrator.', 'mcupr' ), '', true, true ),
	);
	return $defs;
}

/** group key => heading. */
function mcupr_cap_groups() {
	return array(
		'students' => __( 'Student data', 'mcupr' ),
		'requests' => __( 'Requests and forms', 'mcupr' ),
		'people'   => __( 'People and offices', 'mcupr' ),
		'daily'    => __( 'Running the site', 'mcupr' ),
		'reports'  => __( 'Figures and reports', 'mcupr' ),
		'settings' => __( 'Settings of the site', 'mcupr' ),
		'content'  => __( 'Posts', 'mcupr' ),
		'pages'    => __( 'Pages', 'mcupr' ),
		'media'    => __( 'Media, comments and appearance', 'mcupr' ),
		'admin'    => __( 'Users and WordPress itself', 'mcupr' ),
	);
}

/** The capabilities the theme invents (the ones it may add to and take from a role). */
function mcupr_theme_caps() {
	$out = array();
	foreach ( mcupr_cap_defs() as $cap => $d ) {
		if ( empty( $d['core'] ) ) {
			$out[] = $cap;
		}
	}
	return $out;
}

/** One capability's definition, or null. */
function mcupr_cap_def( $cap ) {
	$defs = mcupr_cap_defs();
	return isset( $defs[ $cap ] ) ? $defs[ $cap ] : null;
}

/* ================================================================== the standard roles */

/**
 * The roles a university site starts with. 'caps' is the standard: what the role holds when it is created, and what a
 * later version of the theme adds to it for capabilities it has newly introduced.
 */
function mcupr_standard_roles() {
	return array(
		MCUPR_STU_USER_ROLE => array(
			'name' => __( 'Student (login)', 'mcupr' ),
			'note' => __( 'A student signing in to the student portal. Reads their own record, their grades and their timetable, and sends requests — nothing in the admin area.', 'mcupr' ),
			'caps' => array( 'read' ),
		),
		'mcupr_lecturer'    => array(
			'name' => __( 'Lecturer', 'mcupr' ),
			'note' => __( 'Writes and publishes their own news and articles, uploads pictures, and reads what is kept for students.', 'mcupr' ),
			'caps' => array( 'read', 'upload_files', 'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_published_posts', 'mcupr_see_student_content', 'mcupr_view_stats' ),
		),
		'mcupr_officer'     => array(
			'name' => __( 'Officer', 'mcupr' ),
			'note' => __( 'Looks after the content of the site day to day: news and pages, the calendar, the forum, and the answers people send through the forms.', 'mcupr' ),
			'caps' => array(
				'read', 'upload_files', 'moderate_comments', 'manage_categories',
				'edit_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_published_posts', 'edit_others_posts',
				'edit_pages', 'edit_published_pages', 'publish_pages', 'delete_pages', 'delete_published_pages',
				'mcupr_see_student_content', 'mcupr_view_stats', 'mcupr_see_answers', 'mcupr_manage_calendar', 'mcupr_manage_forum',
			),
		),
		MCUPR_REGISTRAR_ROLE => array(
			'name' => __( 'Registrar', 'mcupr' ),
			'note' => __( 'Looks after the student records: the import, the grades and timetables, the login accounts, the announcements and the requests.', 'mcupr' ),
			// the student record is a post type of its own, so the role also needs what WordPress worked out for it
			'caps' => array_values( array_unique( array_merge(
				array( 'read', 'upload_files', MCUPR_STU_CAP, 'mcupr_handle_requests', 'mcupr_see_answers', 'mcupr_see_student_content' ),
				function_exists( 'mcupr_student_caps' ) ? mcupr_student_caps() : array()
			) ) ),
		),
		MCUPR_OFFICE_ROLE   => array(
			'name' => __( 'Office editor', 'mcupr' ),
			'note' => __( 'Writes the content of their own offices only (set on their user profile). Cannot touch another office\'s.', 'mcupr' ),
			// the content side is the list inc/offices.php has always used, so there is one definition of it
			'caps' => array_values( array_unique( array_merge(
				function_exists( 'mcupr_office_role_caps' ) ? array_keys( array_filter( mcupr_office_role_caps() ) ) : array( 'read', 'upload_files' ),
				array( 'mcupr_see_student_content', 'mcupr_manage_staff', 'mcupr_manage_groups', 'mcupr_manage_calendar', 'mcupr_see_answers' )
			) ) ),
		),
	);
}

/** The roles the theme brought with it (the ones that can be put back to the standard). */
function mcupr_is_standard_role( $role ) {
	return isset( mcupr_standard_roles()[ $role ] );
}

/** Roles made on this site through the page (key => name), so they can be deleted again. */
function mcupr_custom_roles() {
	$c = get_option( 'mcupr_custom_roles', array() );
	return is_array( $c ) ? $c : array();
}

/* ================================================================== keeping the roles in step */

/**
 * Give an administrator everything the theme invents. Done on every load of the roles file rather than remembered, so
 * a site can never lock its own administrators out of a screen — not even by unticking the boxes on the page.
 */
function mcupr_roles_arm_admin() {
	$admin = get_role( 'administrator' );
	if ( ! $admin ) {
		return;
	}
	foreach ( mcupr_theme_caps() as $cap ) {
		if ( ! $admin->has_cap( $cap ) ) {
			$admin->add_cap( $cap );
		}
	}
}

/**
 * The one-off that keeps a site working the day the capabilities arrive: every role that holds the capability a screen
 * used to ask for is given the new one. An editor who reached the staff hub through edit_pages goes on reaching it.
 */
function mcupr_roles_seed_from_legacy() {
	foreach ( wp_roles()->role_objects as $key => $role ) {
		if ( 'administrator' === $key ) {
			continue;
		}
		foreach ( mcupr_cap_defs() as $cap => $def ) {
			if ( '' === $def['legacy'] || ! empty( $def['core'] ) ) {
				continue;
			}
			$had = ! empty( $role->capabilities[ $def['legacy'] ] )
				|| ( 'mcupr_see_student_content' === $cap && ! empty( $role->capabilities[ MCUPR_STU_CAP ] ) );
			if ( $had && empty( $role->capabilities[ $cap ] ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}

/** Make a role from the standard (used when it is missing, and by "put back to the standard"). */
function mcupr_role_from_standard( $key ) {
	$std = mcupr_standard_roles();
	if ( ! isset( $std[ $key ] ) ) {
		return false;
	}
	remove_role( $key );
	add_role( $key, $std[ $key ]['name'], array_fill_keys( $std[ $key ]['caps'], true ) );
	return (bool) get_role( $key );
}

/**
 * Bring the roles up to date: make the standard ones that are missing, and add to every standard role the
 * capabilities this version of the theme has newly introduced. Nothing is ever taken away, so what an administrator
 * set on the page stays set.
 */
function mcupr_roles_sync() {
	$was  = (int) get_option( 'mcupr_roles_v', 0 );
	$std  = mcupr_standard_roles();
	$made = array();
	foreach ( $std as $key => $def ) {
		if ( ! get_role( $key ) ) {
			mcupr_role_from_standard( $key );
			$made[] = $key;
		}
	}
	if ( 0 === $was ) {
		// the capabilities are new to this site: keep every screen reachable by whoever reaches it today
		mcupr_roles_seed_from_legacy();
	}
	if ( $was < MCUPR_ROLES_V ) {
		foreach ( $std as $key => $def ) {
			$role = get_role( $key );
			if ( ! $role || in_array( $key, $made, true ) ) {
				continue; // a role just made from the standard already holds all of it
			}
			foreach ( $def['caps'] as $cap ) {
				if ( mcupr_cap_is_new( $cap, $was ) && ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}
	mcupr_roles_arm_admin();
	if ( $was !== MCUPR_ROLES_V ) {
		update_option( 'mcupr_roles_v', MCUPR_ROLES_V, false );
	}
}

/**
 * Was this capability introduced after the version the site last saw? With one version so far everything is new to a
 * site that has never run the roles file, and nothing is new to one that has.
 */
function mcupr_cap_is_new( $cap, $site_version ) {
	return $site_version < MCUPR_ROLES_V;
}

add_action( 'init', 'mcupr_roles_sync', 9 ); // after the post types (6) and before anything that asks a capability

/* ================================================================== asking */

/**
 * May this person do $cap? The same answer as current_user_can(), with one addition: while a site has not yet run the
 * roles file (a half-finished upgrade, a role created by hand) the capability a screen used to ask for still answers,
 * so a screen can never become unreachable by everybody.
 */
function mcupr_can( $cap, $user_id = 0 ) {
	$who = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	if ( ! $who || ! $who->exists() ) {
		return false;
	}
	if ( $who->has_cap( $cap ) ) {
		return true;
	}
	$def = mcupr_cap_def( $cap );
	if ( $def && '' !== $def['legacy'] && (int) get_option( 'mcupr_roles_v', 0 ) < MCUPR_ROLES_V ) {
		return $who->has_cap( $def['legacy'] );
	}
	return false;
}
