<?php
/**
 * One home in the admin for everything the theme adds: a top-level "MCUPR" menu (หมวดธีม MCUPR) with an overview page, and
 * every theme section under it – events, works, albums, videos, FAQ, forum, polls, form messages, staff, org chart,
 * welcome & maintenance – so it is clear at a glance which menus belong to the theme. WordPress' own menus (Posts, Pages,
 * Media…) stay where they are.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Our post types, in the order of the menu, each with the taxonomies that go right after it. */
function mcupr_admin_sections() {
	return array(
		'content'   => array(
			'label' => __( 'Content', 'mcupr' ),
			'items' => array(
				array( 'type' => 'mcupr_event', 'page' => 'mcupr-ics', 'icon' => 'dashicons-calendar-alt', 'desc' => __( 'Events and the calendar', 'mcupr' ) ),
				array( 'type' => 'mcupr_work', 'tax' => array( 'mcupr_work_cat' ), 'icon' => 'dashicons-awards', 'desc' => __( 'Works and awards', 'mcupr' ) ),
				array( 'type' => 'mcupr_album', 'icon' => 'dashicons-format-gallery', 'desc' => __( 'Photo albums with a lightbox', 'mcupr' ) ),
				array( 'type' => 'mcupr_video', 'tax' => array( 'mcupr_video_cat' ), 'icon' => 'dashicons-video-alt3', 'desc' => __( 'Videos from YouTube, Vimeo or a file', 'mcupr' ) ),
				array( 'type' => 'mcupr_book', 'tax' => array( 'mcupr_shelf' ), 'page' => 'mcupr-books', 'icon' => 'dashicons-book-alt', 'desc' => __( 'E-books with a 3D page-turning reader and bookshelves', 'mcupr' ) ),
				array( 'type' => 'mcupr_faq', 'tax' => array( 'mcupr_faq_cat' ), 'icon' => 'dashicons-editor-help', 'desc' => __( 'Frequently asked questions', 'mcupr' ) ),
			),
		),
		'community' => array(
			'label' => __( 'Visitors', 'mcupr' ),
			'items' => array(
				array( 'type' => 'mcupr_topic', 'tax' => array( 'mcupr_board' ), 'page' => 'mcupr-forum', 'icon' => 'dashicons-format-chat', 'desc' => __( 'Discussion board', 'mcupr' ) ),
				array( 'type' => 'mcupr_poll', 'icon' => 'dashicons-chart-bar', 'desc' => __( 'Polls and satisfaction surveys', 'mcupr' ) ),
				array( 'type' => 'mcupr_fb', 'icon' => 'dashicons-feedback', 'desc' => __( 'Make your own forms – questionnaires, requests, registrations', 'mcupr' ) ),
				array( 'type' => 'mcupr_fe', 'icon' => 'dashicons-database', 'desc' => __( 'Answers to your forms, with CSV download', 'mcupr' ) ),
				array( 'type' => 'mcupr_msg', 'icon' => 'dashicons-email-alt', 'desc' => __( 'Messages sent through the website forms', 'mcupr' ) ),
			),
		),
		'students'  => array(
			'label' => __( 'Students', 'mcupr' ),
			'items' => array(
				array( 'type' => 'mcupr_student', 'pages' => array( 'mcupr-student-import', 'mcupr-student-settings', 'mcupr-student-log', 'mcupr-requests', 'mcupr-student-mail', 'mcupr-student-grades', 'mcupr-student-codes' ), 'icon' => 'dashicons-id-alt', 'desc' => __( 'Student database with bulk import, login accounts and student-only content', 'mcupr' ) ),
			),
		),
		'people'    => array(
			'label' => __( 'Staff and offices', 'mcupr' ),
			'items' => array(
				array( 'type' => 'mcupr_office', 'icon' => 'dashicons-building', 'desc' => __( 'Offices as mini-sites inside the website, each with its own news, events, people and staff accounts', 'mcupr' ) ),
				array( 'type' => 'mcupr_group', 'icon' => 'dashicons-groups', 'desc' => __( 'Work groups of the offices, each with a landing page of its own', 'mcupr' ) ),
				array( 'type' => 'mcupr_person', 'tax' => array( 'mcupr_person_group' ), 'page' => 'mcupr-staff-hub', 'icon' => 'dashicons-groups', 'desc' => __( 'Staff profiles, honours and the staff hub', 'mcupr' ) ),
				array( 'type' => 'mcupr_unit', 'icon' => 'dashicons-networking', 'desc' => __( 'The administration chart', 'mcupr' ) ),
			),
		),
	);
}

function mcupr_admin_types() {
	$t = array();
	foreach ( mcupr_admin_sections() as $s ) {
		foreach ( $s['items'] as $i ) {
			$t[] = $i['type'];
		}
	}
	return $t;
}

/** May this person see the card / group of a post type? (The answers of the forms are for people who can edit pages; the registrar sees only the requests, through the "Requests" card of the students.) */
function mcupr_admin_can_type( $o ) {
	if ( 'mcupr_fe' === $o->name ) {
		return current_user_can( 'edit_pages' );
	}
	return current_user_can( $o->cap->edit_posts );
}

// Our post types live under the "MCUPR" menu.
add_filter(
	'register_post_type_args',
	static function ( $args, $type ) {
		if ( in_array( $type, mcupr_admin_types(), true ) ) {
			$args['show_in_menu'] = 'mcupr';
			unset( $args['menu_position'] );
		}
		return $args;
	},
	10,
	2
);

add_action(
	'admin_menu',
	static function () {
		$cap = ! current_user_can( 'edit_pages' ) && current_user_can( MCUPR_STU_CAP ) ? MCUPR_STU_CAP : 'edit_pages'; // registrars (who cannot edit pages) get the menu too
		add_menu_page( __( 'MCUPR theme', 'mcupr' ), 'MCUPR', $cap, 'mcupr', 'mcupr_admin_overview', 'dashicons-superhero-alt', 3 );
		add_submenu_page( 'mcupr', __( 'MCUPR overview', 'mcupr' ), __( 'Overview', 'mcupr' ), $cap, 'mcupr', 'mcupr_admin_overview' );
	},
	5
);

/** Put the submenu in a sensible order, drop the "Add new" duplicates (the list pages have the button) and add the shortcuts. */
add_action(
	'admin_menu',
	static function () {
		global $submenu;
		if ( empty( $submenu['mcupr'] ) ) {
			return;
		}
		$by = array();
		foreach ( $submenu['mcupr'] as $it ) {
			$by[ $it[2] ] = $it;
		}
		$out   = array();
		$take  = static function ( $slug ) use ( &$by, &$out ) {
			if ( isset( $by[ $slug ] ) ) {
				$out[] = $by[ $slug ];
				unset( $by[ $slug ] );
			}
		};
		$take( 'mcupr' );
		foreach ( mcupr_admin_sections() as $sec ) {
			foreach ( $sec['items'] as $i ) {
				if ( isset( $by[ 'edit.php?post_type=' . $i['type'] ] ) ) { // "Events", not "All events": the sections are named, the lists are what they open
					$o = get_post_type_object( $i['type'] );
					if ( $o && ! empty( $o->labels->menu_name ) ) {
						$by[ 'edit.php?post_type=' . $i['type'] ][0] = $o->labels->menu_name;
					}
				}
				$take( 'edit.php?post_type=' . $i['type'] );
				unset( $by[ 'post-new.php?post_type=' . $i['type'] ] );
				foreach ( isset( $i['tax'] ) ? $i['tax'] : array() as $tx ) {
					$tax = get_taxonomy( $tx );
					if ( $tax ) {
						$out[] = array( '— ' . $tax->labels->name, 'edit_pages', 'edit-tags.php?taxonomy=' . $tx . '&post_type=' . $i['type'] );
					}
				}
				if ( ! empty( $i['page'] ) ) {
					$take( $i['page'] );
				}
				foreach ( isset( $i['pages'] ) ? $i['pages'] : array() as $pg ) {
					$take( $pg );
				}
			}
		}
		$take( 'mcupr-blocks' );
		$take( 'mcupr-speed' );
		$take( 'mcupr-images' );
		$take( 'mcupr-files' );
		$take( 'mcupr-elementor' );
		$take( 'mcupr-settings-io' );
		$take( 'mcupr-insights' );
		$take( 'mcupr-status' );
		$take( 'mcupr-login' );
		$take( 'mcupr-social' );
		$out[] = array( __( 'Theme options', 'mcupr' ), 'edit_theme_options', 'customize.php?return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) );
		foreach ( $by as $left ) { // anything else a module put here
			$out[] = $left;
		}
		// The menu shows only the overview and the few main groups (each opens the overview at that group); every page below
		// stays registered – so it can be opened, and the menu stays lit – but is hidden from the list (CSS class).
		$first = array_shift( $out );
		$cap   = $first ? $first[1] : 'edit_pages';
		$short = array();
		foreach ( mcupr_cc_cats() as $key => $c ) {
			$short[] = array( $c['title'], $cap, mcupr_cc_cat_slug( $key ), $c['title'] );
		}
		if ( $first ) {
			$first[0] = __( 'Control center', 'mcupr' );
		}
		foreach ( $out as $n => $it ) {
			$out[ $n ][4] = trim( ( isset( $it[4] ) ? $it[4] : '' ) . ' mcupr-sub-hidden' );
		}
		$submenu['mcupr'] = array_merge( $first ? array( $first ) : array(), $short, $out ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	},
	999
);

// Keep the MCUPR menu open on the theme's own screens (lists, editors, category pages); which entry lights up is in control-center.php.
add_filter(
	'parent_file',
	static function ( $parent ) {
		$s = get_current_screen();
		if ( $s && in_array( $s->post_type, mcupr_admin_types(), true ) ) {
			return 'mcupr';
		}
		return $parent;
	}
);
