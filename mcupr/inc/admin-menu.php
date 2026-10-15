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
		'people'    => array(
			'label' => __( 'People', 'mcupr' ),
			'items' => array(
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
		add_menu_page( __( 'MCUPR theme', 'mcupr' ), 'MCUPR', 'edit_pages', 'mcupr', 'mcupr_admin_overview', 'dashicons-superhero-alt', 3 );
		add_submenu_page( 'mcupr', __( 'MCUPR overview', 'mcupr' ), __( 'Overview', 'mcupr' ), 'edit_pages', 'mcupr', 'mcupr_admin_overview' );
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
			}
		}
		$take( 'mcupr-blocks' );
		$take( 'mcupr-insights' );
		$take( 'mcupr-status' );
		$out[] = array( __( 'Theme options', 'mcupr' ), 'edit_theme_options', 'customize.php?return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) );
		foreach ( $by as $left ) { // anything else a module put here
			$out[] = $left;
		}
		$submenu['mcupr'] = $out; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	},
	999
);

// Keep the MCUPR menu open and the right item lit on the theme's own screens (lists, editors, category pages).
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
add_filter(
	'submenu_file',
	static function ( $file ) {
		$s = get_current_screen();
		if ( $s && in_array( $s->post_type, mcupr_admin_types(), true ) && ! empty( $s->taxonomy ) ) {
			return 'edit-tags.php?taxonomy=' . $s->taxonomy . '&post_type=' . $s->post_type;
		}
		if ( $s && in_array( $s->post_type, mcupr_admin_types(), true ) && 'post' === $s->base ) {
			return 'edit.php?post_type=' . $s->post_type;
		}
		return $file;
	}
);

/** The overview: every theme section as a card, with what is waiting for you. */
function mcupr_admin_overview() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$waiting = array();
	if ( post_type_exists( 'mcupr_topic' ) ) {
		$n = (int) wp_count_posts( 'mcupr_topic' )->pending;
		$c = (int) get_comments( array( 'status' => 'hold', 'post_type' => 'mcupr_topic', 'count' => true ) );
		if ( $n + $c ) {
			$waiting[] = array( sprintf( /* translators: 1: topics, 2: replies */ __( 'Forum: %1$d topics and %2$d replies to approve', 'mcupr' ), $n, $c ), admin_url( 'admin.php?page=mcupr-forum' ) );
		}
	}
	$st = function_exists( 'mcupr_status' ) ? mcupr_status() : array();
	if ( ! empty( $st['maint']['on'] ) ) {
		$waiting[] = array( __( 'Maintenance mode is ON', 'mcupr' ), admin_url( 'admin.php?page=mcupr-status' ) );
	}
	if ( ! empty( $st['welcome']['on'] ) ) {
		$waiting[] = array( __( 'The welcome page is switched on', 'mcupr' ), admin_url( 'admin.php?page=mcupr-status' ) );
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'MCUPR theme', 'mcupr' ) . ' <small style="font-weight:400;opacity:.7">v' . esc_html( MCUPR_VERSION ) . '</small></h1>';
	echo '<p class="description">' . esc_html__( 'Everything the theme adds to the website is under this menu. Posts, pages, media, comments and users are WordPress’ own and stay in their usual place.', 'mcupr' ) . '</p>';
	if ( $waiting ) {
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Needs your attention', 'mcupr' ) . '</strong></p><ul style="margin:0 0 .8em 1.2em;list-style:disc">';
		foreach ( $waiting as $w ) {
			echo '<li><a href="' . esc_url( $w[1] ) . '">' . esc_html( $w[0] ) . '</a></li>';
		}
		echo '</ul></div>';
	}
	foreach ( mcupr_admin_sections() as $sec ) {
		echo '<h2>' . esc_html( $sec['label'] ) . '</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:12px;max-width:72rem">';
		foreach ( $sec['items'] as $i ) {
			$o = get_post_type_object( $i['type'] );
			if ( ! $o ) {
				continue;
			}
			$cnt = wp_count_posts( $i['type'] );
			$all = (int) $cnt->publish + (int) $cnt->draft + (int) $cnt->pending + (int) $cnt->private + (int) $cnt->future;
			echo '<a class="card" style="display:block;margin:0;padding:14px 16px;text-decoration:none;max-width:none" href="' . esc_url( admin_url( 'edit.php?post_type=' . $i['type'] ) ) . '"><span class="dashicons ' . esc_attr( $i['icon'] ) . '" style="float:right;opacity:.55"></span><strong style="font-size:15px">' . esc_html( $o->labels->menu_name ? $o->labels->menu_name : $o->label ) . '</strong><br><span style="color:#50575e">' . esc_html( $i['desc'] ) . '</span><br><span style="color:#2271b1">' . esc_html( sprintf( /* translators: %d: how many */ __( '%d items', 'mcupr' ), $all ) ) . '</span></a>';
		}
		echo '</div>';
	}
	echo '<h2>' . esc_html__( 'Site', 'mcupr' ) . '</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:12px;max-width:72rem">';
	echo '<a class="card" style="display:block;margin:0;padding:14px 16px;text-decoration:none;max-width:none" href="' . esc_url( admin_url( 'admin.php?page=mcupr-status' ) ) . '"><span class="dashicons dashicons-lock" style="float:right;opacity:.55"></span><strong style="font-size:15px">' . esc_html__( 'Welcome & maintenance', 'mcupr' ) . '</strong><br><span style="color:#50575e">' . esc_html__( 'Welcome page for special occasions, maintenance mode, mourning', 'mcupr' ) . '</span></a>';
	echo '<a class="card" style="display:block;margin:0;padding:14px 16px;text-decoration:none;max-width:none" href="' . esc_url( admin_url( 'customize.php' ) ) . '"><span class="dashicons dashicons-admin-customizer" style="float:right;opacity:.55"></span><strong style="font-size:15px">' . esc_html__( 'Theme options', 'mcupr' ) . '</strong><br><span style="color:#50575e">' . esc_html__( 'Colours, logos, home page sections, menus, footer', 'mcupr' ) . '</span></a>';
	echo '</div></div>';
}
