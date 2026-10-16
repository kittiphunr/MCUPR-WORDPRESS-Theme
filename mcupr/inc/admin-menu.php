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
				array( 'type' => 'mcupr_student', 'pages' => array( 'mcupr-student-import', 'mcupr-student-settings', 'mcupr-student-log' ), 'icon' => 'dashicons-id-alt', 'desc' => __( 'Student database with bulk import, login accounts and student-only content', 'mcupr' ) ),
			),
		),
		'people'    => array(
			'label' => __( 'Staff and offices', 'mcupr' ),
			'items' => array(
				array( 'type' => 'mcupr_office', 'icon' => 'dashicons-building', 'desc' => __( 'Offices as mini-sites inside the website, each with its own news, events, people and staff accounts', 'mcupr' ) ),
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

/**
 * The few main groups the MCUPR menu shows (everything else is a card on the overview, one click away):
 * key => label, the sections of mcupr_admin_sections() it holds, and the admin pages (slugs) of the "tools" group.
 */
function mcupr_admin_groups() {
	return array(
		'content'   => array( 'label' => __( 'Content', 'mcupr' ), 'sections' => array( 'content' ) ),
		'community' => array( 'label' => __( 'Visitors', 'mcupr' ), 'sections' => array( 'community' ) ),
		'students'  => array( 'label' => __( 'Students', 'mcupr' ), 'sections' => array( 'students' ) ),
		'people'    => array( 'label' => __( 'Staff and offices', 'mcupr' ), 'sections' => array( 'people' ) ),
		'tools'     => array( 'label' => __( 'Tools and settings', 'mcupr' ), 'sections' => array() ),
	);
}

/** The admin pages of the "Tools and settings" group. */
function mcupr_admin_tool_pages() {
	return array( 'mcupr-blocks', 'mcupr-speed', 'mcupr-images', 'mcupr-settings-io', 'mcupr-insights', 'mcupr-status' );
}

/** Where a group lives: the overview, scrolled to its heading. */
function mcupr_admin_group_slug( $key ) {
	return 'admin.php?page=mcupr#sec-' . $key;
}

/** Does this person have anything in the group? (the same test as the cards of the overview) */
function mcupr_admin_group_visible( $key ) {
	$g = mcupr_admin_groups();
	if ( ! isset( $g[ $key ] ) ) {
		return false;
	}
	if ( 'tools' === $key ) {
		return current_user_can( 'edit_theme_options' ) || current_user_can( 'manage_options' );
	}
	$secs = mcupr_admin_sections();
	foreach ( $g[ $key ]['sections'] as $sk ) {
		foreach ( isset( $secs[ $sk ] ) ? $secs[ $sk ]['items'] : array() as $i ) {
			$o = get_post_type_object( $i['type'] );
			if ( $o && current_user_can( $o->cap->edit_posts ) ) {
				return true;
			}
		}
	}
	return false;
}

/** The group a theme screen belongs to ('' = the overview itself or not ours). */
function mcupr_admin_group_of( $post_type = '', $page = '' ) {
	foreach ( mcupr_admin_groups() as $key => $g ) {
		$secs = mcupr_admin_sections();
		foreach ( $g['sections'] as $sk ) {
			foreach ( isset( $secs[ $sk ] ) ? $secs[ $sk ]['items'] : array() as $i ) {
				$pages = array_merge( ! empty( $i['page'] ) ? array( $i['page'] ) : array(), isset( $i['pages'] ) ? $i['pages'] : array() );
				if ( ( '' !== $post_type && $i['type'] === $post_type ) || ( '' !== $page && in_array( $page, $pages, true ) ) ) {
					return $key;
				}
			}
		}
	}
	return '' !== $page && in_array( $page, mcupr_admin_tool_pages(), true ) ? 'tools' : '';
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
		$take( 'mcupr-settings-io' );
		$take( 'mcupr-insights' );
		$take( 'mcupr-status' );
		$out[] = array( __( 'Theme options', 'mcupr' ), 'edit_theme_options', 'customize.php?return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) );
		foreach ( $by as $left ) { // anything else a module put here
			$out[] = $left;
		}
		// The menu shows only the overview and the few main groups (each opens the overview at that group); every page below
		// stays registered – so it can be opened, and the menu stays lit – but is hidden from the list (CSS class).
		$first = array_shift( $out );
		$cap   = $first ? $first[1] : 'edit_pages';
		$short = array();
		foreach ( mcupr_admin_groups() as $key => $g ) {
			if ( mcupr_admin_group_visible( $key ) ) {
				$short[] = array( $g['label'], $cap, mcupr_admin_group_slug( $key ), $g['label'] );
			}
		}
		foreach ( $out as $n => $it ) {
			$out[ $n ][4] = trim( ( isset( $it[4] ) ? $it[4] : '' ) . ' mcupr-sub-hidden' );
		}
		$submenu['mcupr'] = array_merge( $first ? array( $first ) : array(), $short, $out ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
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
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$grp  = mcupr_admin_group_of( $s ? (string) $s->post_type : '', $page );
		if ( '' !== $grp && mcupr_admin_group_visible( $grp ) ) {
			return mcupr_admin_group_slug( $grp ); // the group lights up on its own pages, even though the page itself is not in the list
		}
		if ( $s && in_array( $s->post_type, mcupr_admin_types(), true ) && ! empty( $s->taxonomy ) ) {
			return 'edit-tags.php?taxonomy=' . $s->taxonomy . '&post_type=' . $s->post_type;
		}
		if ( $s && in_array( $s->post_type, mcupr_admin_types(), true ) && 'post' === $s->base ) {
			return 'edit.php?post_type=' . $s->post_type;
		}
		return $file;
	}
);

/** The menu list is short: the pages below the groups are hidden from it (still there, one click from the overview). */
add_action(
	'admin_head',
	static function () {
		echo '<style>#adminmenu #toplevel_page_mcupr .wp-submenu li.mcupr-sub-hidden{display:none}.mcupr-crumb{display:flex;width:fit-content;max-width:calc(100% - 180px);flex-wrap:wrap;align-items:center;gap:6px;margin:10px 20px 0 2px;padding:8px 14px;border:1px solid #dcdcde;border-radius:999px;background:#fff;font-size:13px}.mcupr-crumb a{display:inline-flex;align-items:center;min-height:28px;font-weight:600;text-decoration:none}.mcupr-crumb a:first-child{color:#9d174d}.mcupr-crumb span{color:#646970}.auto-fold .mcupr-crumb{margin-left:2px}</style>';
	}
);

/** On every theme screen: a bar "‹ MCUPR overview › group" that goes back to the one control page. */
add_action(
	'in_admin_header',
	static function () {
		$s = get_current_screen();
		if ( ! $s || $s->is_block_editor() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$grp  = mcupr_admin_group_of( (string) $s->post_type, $page );
		if ( '' === $grp || ! mcupr_admin_group_visible( $grp ) ) {
			return;
		}
		$g = mcupr_admin_groups();
		echo '<nav class="mcupr-crumb" aria-label="' . esc_attr__( 'MCUPR', 'mcupr' ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=mcupr' ) ) . '"><span aria-hidden="true">‹&nbsp;</span>' . esc_html__( 'MCUPR overview', 'mcupr' ) . '</a><span aria-hidden="true">›</span><a href="' . esc_url( admin_url( mcupr_admin_group_slug( $grp ) ) ) . '">' . esc_html( $g[ $grp ]['label'] ) . '</a></nav>';
	}
);

/** The overview CSS (the page is a dashboard: banner, status, quick actions, sections, tools). */
function mcupr_admin_overview_css() {
	return '.mcupr-ov{max-width:76rem;--ov-a:#9d174d}.mcupr-ov *{box-sizing:border-box}
.mcupr-ov .ov-hero{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin:16px 0 20px;padding:24px 28px;border-radius:16px;background:linear-gradient(120deg,#7a1239 0%,#9d174d 55%,#be185d 100%);color:#fff}
.mcupr-ov .ov-hero h1{margin:0 0 6px;padding:0;color:#fff;font-size:1.7rem;line-height:1.25}
.mcupr-ov .ov-hero p{margin:0;max-width:44rem;color:#fff;font-size:14px}
.mcupr-ov .ov-ver{display:inline-block;margin-left:8px;padding:2px 10px;border:1px solid #ffffff99;border-radius:999px;font-size:.8rem;font-weight:600;vertical-align:middle}
.mcupr-ov .ov-hero .button{min-height:40px;padding:4px 18px;border-radius:999px;border:0;background:#fff;color:#7a1239;font-weight:600}
.mcupr-ov .ov-hero .button:hover,.mcupr-ov .ov-hero .button:focus{background:#fdf2f8;color:#7a1239}
.mcupr-ov h2{margin:28px 0 12px;padding:0 0 0 12px;border-left:5px solid var(--ov-a);font-size:1.15rem;line-height:1.3}
.mcupr-ov .ov-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:14px}
.mcupr-ov .ov-chips{display:flex;flex-wrap:wrap;gap:10px}
.mcupr-ov h2[id^=sec-]{scroll-margin-top:48px}.mcupr-ov .ov-jump{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 6px}.mcupr-ov .ov-jump b{margin-right:4px;color:#50575e;font-weight:600}.mcupr-ov .ov-jump a{display:inline-flex;align-items:center;min-height:36px;padding:4px 16px;border:2px solid #dcdcde;border-radius:999px;background:#fff;color:#1d2327;font-weight:600;text-decoration:none}.mcupr-ov .ov-jump a:hover,.mcupr-ov .ov-jump a:focus{border-color:var(--ov-a);color:var(--ov-a)}
.mcupr-ov .ov-chip{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:6px 16px;border:2px solid #dcdcde;border-radius:999px;background:#fff;color:#1d2327;font-weight:600;text-decoration:none}
.mcupr-ov .ov-chip i{width:12px;height:12px;border-radius:50%;background:#a7aaad;flex:none}
.mcupr-ov .ov-chip em{font-style:normal;font-weight:400;color:#50575e}
.mcupr-ov .ov-chip.is-on{color:#fff;border-color:transparent}
.mcupr-ov .ov-chip.is-on em{color:#fff}
.mcupr-ov .ov-chip.is-on i{background:#fff}
.mcupr-ov .ov-chip[data-mode="maint"].is-on{background:#b45309}
.mcupr-ov .ov-chip[data-mode="welcome"].is-on{background:#1d4ed8}
.mcupr-ov .ov-chip[data-mode="mourning"].is-on{background:#262626}
.mcupr-ov .ov-chip[data-mode="forum"].is-on{background:#a21caf}.mcupr-ov .ov-chip[data-mode="students"].is-on{background:#1d4ed8}
.mcupr-ov .ov-acts{display:flex;flex-wrap:wrap;gap:10px}
.mcupr-ov .ov-act{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:6px 18px;border:1px solid #c3c4c7;border-radius:12px;background:#fff;color:#1d2327;font-weight:600;text-decoration:none;box-shadow:0 1px 2px #0000000d}
.mcupr-ov .ov-act .dashicons{color:var(--ov-a)}
.mcupr-ov .ov-act:hover,.mcupr-ov .ov-act:focus{border-color:var(--ov-a);color:var(--ov-a)}
.mcupr-ov .ov-cell{height:100%}.mcupr-ov .ov-card{position:relative;display:flex;height:100%;gap:14px;min-width:0;padding:16px;border:1px solid #dcdcde;border-top:4px solid var(--c,#2271b1);border-radius:14px;background:#fff;color:#1d2327;text-decoration:none;box-shadow:0 1px 2px #0000000d;transition:transform .15s,box-shadow .15s}
.mcupr-ov .ov-card:hover,.mcupr-ov .ov-card:focus{transform:translateY(-2px);box-shadow:0 10px 24px -14px #00000066;color:#1d2327}
.mcupr-ov .ov-ic{flex:none;display:grid;place-items:center;width:44px;height:44px;border-radius:50%;background:color-mix(in srgb,var(--c,#2271b1) 14%,#fff);color:var(--c,#2271b1)}
.mcupr-ov .ov-ic .dashicons{width:24px;height:24px;font-size:24px}
.mcupr-ov .ov-t{display:block;font-size:15px;font-weight:700;line-height:1.35;overflow-wrap:anywhere}
.mcupr-ov .ov-d{display:block;margin-top:2px;color:#50575e;font-size:13px;line-height:1.5}
.mcupr-ov .ov-n{display:inline-block;margin-top:6px;padding:1px 10px;border-radius:999px;background:color-mix(in srgb,var(--c,#2271b1) 12%,#fff);color:#1d2327;font-size:12px;font-weight:700}
.mcupr-ov .ov-add{position:absolute;top:10px;right:10px;display:grid;place-items:center;width:32px;height:32px;border-radius:50%;color:var(--c,#2271b1);text-decoration:none}
.mcupr-ov .ov-add:hover,.mcupr-ov .ov-add:focus{background:color-mix(in srgb,var(--c,#2271b1) 14%,#fff)}
.mcupr-ov .ov-card .ov-body{min-width:0;padding-right:20px}.mcupr-ov .ov-cell{display:flex;flex-direction:column}.mcupr-ov .ov-cell>.ov-card{flex:1}
.mcupr-ov .ov-links{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 0;padding:0;list-style:none}
.mcupr-ov .ov-links a{display:inline-flex;align-items:center;min-height:32px;padding:2px 12px;border:1px solid color-mix(in srgb,var(--c,#2271b1) 40%,#fff);border-radius:999px;background:#fff;color:#1d2327;font-size:12px;font-weight:600;text-decoration:none}
.mcupr-ov .ov-links a:hover,.mcupr-ov .ov-links a:focus{background:color-mix(in srgb,var(--c,#2271b1) 12%,#fff);border-color:var(--c,#2271b1)}
.mcupr-ov .ov-warn{margin:0 0 4px;padding:12px 16px;border:1px solid #f0b849;border-left:6px solid #dba617;border-radius:10px;background:#fcf9e8}
.mcupr-ov .ov-warn ul{margin:6px 0 0 1.2em;list-style:disc}
.mcupr-ov .ov-mine{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border:1px solid #dcdcde;border-left:8px solid var(--c,#0f766e);border-radius:14px;background:#fff}
@media (max-width:600px){.mcupr-ov .ov-hero{padding:18px}.mcupr-ov .ov-hero h1{font-size:1.35rem}.mcupr-ov .ov-grid{grid-template-columns:1fr}}';
}

/** One card of the overview. */
function mcupr_admin_card( $url, $icon, $title, $desc, $count = null, $color = '#2271b1', $add = '', $links = array() ) {
	$o  = '<div class="ov-cell" style="position:relative;--c:' . esc_attr( $color ) . '"><a class="ov-card" href="' . esc_url( $url ) . '"><span class="ov-ic" aria-hidden="true"><span class="dashicons ' . esc_attr( $icon ) . '"></span></span><span class="ov-body"><span class="ov-t">' . esc_html( $title ) . '</span><span class="ov-d">' . esc_html( $desc ) . '</span>';
	if ( null !== $count ) {
		$o .= '<span class="ov-n">' . esc_html( sprintf( /* translators: %d: how many */ __( '%d items', 'mcupr' ), $count ) ) . '</span>';
	}
	$o .= '</span></a>';
	if ( $links ) {
		$o .= '<ul class="ov-links" style="--c:' . esc_attr( $color ) . '">';
		foreach ( $links as $l ) {
			$o .= '<li><a href="' . esc_url( $l[1] ) . '">' . esc_html( $l[0] ) . '</a></li>';
		}
		$o .= '</ul>';
	}
	if ( '' !== $add ) {
		$o .= '<a class="ov-add" style="--c:' . esc_attr( $color ) . '" href="' . esc_url( $add ) . '" title="' . esc_attr__( 'Add new', 'mcupr' ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( sprintf( /* translators: %s: section */ __( 'Add new: %s', 'mcupr' ), $title ) ) . '</span></a>';
	}
	return $o . '</div>';
}

/** The small links under a card: its categories / groups, its settings pages, and where visitors see it. Only what the person may open. */
function mcupr_admin_card_links( $i ) {
	global $submenu;
	$title = array();
	foreach ( isset( $submenu['mcupr'] ) ? $submenu['mcupr'] : array() as $it ) {
		$title[ $it[2] ] = array( preg_replace( '/^[—\s]+/u', '', $it[0] ), $it[1] );
	}
	$links = array();
	foreach ( isset( $i['tax'] ) ? $i['tax'] : array() as $tx ) {
		$t = get_taxonomy( $tx );
		if ( $t && current_user_can( 'edit_pages' ) ) {
			$links[] = array( $t->labels->name, admin_url( 'edit-tags.php?taxonomy=' . $tx . '&post_type=' . $i['type'] ) );
		}
	}
	$pages = array_merge( ! empty( $i['page'] ) ? array( $i['page'] ) : array(), isset( $i['pages'] ) ? $i['pages'] : array() );
	foreach ( $pages as $pg ) {
		if ( isset( $title[ $pg ] ) && current_user_can( $title[ $pg ][1] ) ) {
			$links[] = array( $title[ $pg ][0], admin_url( 'admin.php?page=' . $pg ) );
		}
	}
	if ( 'mcupr_student' === $i['type'] ) {
		$links[] = array( __( 'Open the student portal', 'mcupr' ), function_exists( 'mcupr_student_portal_url' ) ? mcupr_student_portal_url() : home_url( '/student/' ) );
	} elseif ( 'mcupr_office' === $i['type'] ) {
		$links[] = array( __( 'See the offices on the website', 'mcupr' ), get_post_type_archive_link( 'mcupr_office' ) );
	}
	return $links;
}

/** The overview ("control page"): a banner, what is on right now, quick actions, every theme section and the tools. */
function mcupr_admin_overview() {
	if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	$mine   = function_exists( 'mcupr_user_offices' ) ? mcupr_user_offices() : null; // null = not limited to offices
	$admin  = current_user_can( 'manage_options' );
	$colors = array( 'content' => '#2563eb', 'community' => '#a21caf', 'people' => '#0f766e' );
	echo '<style>' . mcupr_admin_overview_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="wrap mcupr-ov"><div class="ov-hero"><div><h1>' . esc_html( wp_get_theme()->get( 'Name' ) ? wp_get_theme()->get( 'Name' ) : 'MCUPR' ) . '<span class="ov-ver">v' . esc_html( MCUPR_VERSION ) . '</span></h1><p>' . esc_html__( 'Everything the theme adds to the website is under this menu. Posts, pages, media, comments and users are WordPress’ own and stay in their usual place.', 'mcupr' ) . '</p></div><a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the website', 'mcupr' ) . ' <span class="screen-reader-text">(' . esc_html__( 'opens in a new tab', 'mcupr' ) . ')</span></a></div>';

	// ---- jump bar: the same few groups as the menu (the menu opens this page at a group)
	$jump = '';
	foreach ( mcupr_admin_groups() as $gk => $gg ) {
		if ( mcupr_admin_group_visible( $gk ) ) {
			$jump .= '<a href="#sec-' . esc_attr( $gk ) . '">' . esc_html( $gg['label'] ) . '</a>';
		}
	}
	if ( '' !== $jump ) {
		echo '<nav class="ov-jump" aria-label="' . esc_attr__( 'Go to', 'mcupr' ) . '"><b>' . esc_html__( 'Go to', 'mcupr' ) . '</b>' . $jump . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	// ---- what is on right now (the colours match the Info button of the website)
	$st    = function_exists( 'mcupr_status' ) ? mcupr_status() : array();
	$chips = array();
	if ( $admin ) {
		$url     = admin_url( 'admin.php?page=mcupr-status' );
		$modes   = function_exists( 'mcupr_active_modes' ) ? mcupr_active_modes() : array();
		$chips[] = array( 'maint', __( 'Maintenance mode', 'mcupr' ), ! empty( $st['maint']['on'] ), $url );
		$chips[] = array( 'welcome', __( 'Welcome page', 'mcupr' ), ! empty( $st['welcome']['on'] ), $url );
		$chips[] = array( 'mourning', __( 'Mourning', 'mcupr' ), in_array( 'mourning', $modes, true ), $url );
	}
	if ( current_user_can( MCUPR_STU_CAP ) && function_exists( 'mcupr_student_counts' ) ) {
		$sc      = mcupr_student_counts();
		$chips[] = array( 'students', __( 'Students studying', 'mcupr' ), true, admin_url( 'edit.php?post_type=mcupr_student&mcs_status=studying' ), (int) $sc['studying'] );
	}
	$waiting = array();
	if ( post_type_exists( 'mcupr_topic' ) ) {
		$n = (int) wp_count_posts( 'mcupr_topic' )->pending;
		$c = (int) get_comments( array( 'status' => 'hold', 'post_type' => 'mcupr_topic', 'count' => true ) );
		if ( $n + $c ) {
			$waiting[] = array( sprintf( /* translators: 1: topics, 2: replies */ __( 'Forum: %1$d topics and %2$d replies to approve', 'mcupr' ), $n, $c ), admin_url( 'admin.php?page=mcupr-forum' ) );
			$chips[]   = array( 'forum', __( 'Forum waiting', 'mcupr' ), true, admin_url( 'admin.php?page=mcupr-forum' ), $n + $c );
		}
	}
	if ( $chips ) {
		echo '<h2>' . esc_html__( 'Right now', 'mcupr' ) . '</h2><div class="ov-chips">';
		foreach ( $chips as $c ) {
			echo '<a class="ov-chip' . ( $c[2] ? ' is-on' : '' ) . '" data-mode="' . esc_attr( $c[0] ) . '" href="' . esc_url( $c[3] ) . '"><i aria-hidden="true"></i>' . esc_html( $c[1] ) . ' <em>' . ( isset( $c[4] ) ? (int) $c[4] : esc_html( $c[2] ? __( 'On', 'mcupr' ) : __( 'Off', 'mcupr' ) ) ) . '</em></a>';
		}
		echo '</div>';
	}
	if ( $waiting ) {
		echo '<h2>' . esc_html__( 'Needs your attention', 'mcupr' ) . '</h2><div class="ov-warn"><ul style="margin-top:0">';
		foreach ( $waiting as $w ) {
			echo '<li><a href="' . esc_url( $w[1] ) . '">' . esc_html( $w[0] ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	// ---- the office of a person who works in offices
	if ( $mine ) {
		echo '<h2>' . esc_html__( 'Your office', 'mcupr' ) . '</h2>';
		foreach ( $mine as $oid ) {
			if ( 'mcupr_office' !== get_post_type( $oid ) ) {
				continue;
			}
			$d = mcupr_office_data( $oid );
			echo '<div class="ov-mine" style="--c:' . esc_attr( $d['color'] ) . ';margin-bottom:10px"><strong style="font-size:1.1rem">' . esc_html( get_the_title( $oid ) ) . '</strong><span><a class="button" href="' . esc_url( get_permalink( $oid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the website', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'post.php?post=' . (int) $oid . '&action=edit' ) ) . '">' . esc_html__( 'Edit office', 'mcupr' ) . '</a></span></div>';
		}
	}

	// ---- quick actions
	$acts = array(
		array( 'dashicons-edit', __( 'Write news', 'mcupr' ), admin_url( 'post-new.php' ), 'edit_posts' ),
		array( 'dashicons-calendar-alt', __( 'Add an event', 'mcupr' ), admin_url( 'post-new.php?post_type=mcupr_event' ), 'edit_posts', 'mcupr_event' ),
		array( 'dashicons-format-gallery', __( 'Add a photo album', 'mcupr' ), admin_url( 'post-new.php?post_type=mcupr_album' ), 'edit_posts', 'mcupr_album' ),
		array( 'dashicons-admin-page', __( 'Add a page', 'mcupr' ), admin_url( 'post-new.php?post_type=page' ), 'edit_pages' ),
		array( 'dashicons-admin-media', __( 'Upload pictures', 'mcupr' ), admin_url( 'media-new.php' ), 'upload_files' ),
		array( 'dashicons-admin-customizer', __( 'Edit the home page', 'mcupr' ), admin_url( 'customize.php?autofocus[section]=mcupr_home&return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) ), 'edit_theme_options' ),
	);
	echo '<h2>' . esc_html__( 'Quick actions', 'mcupr' ) . '</h2><div class="ov-acts">';
	foreach ( $acts as $a ) {
		if ( current_user_can( $a[3] ) && ( empty( $a[4] ) || post_type_exists( $a[4] ) ) ) {
			echo '<a class="ov-act" href="' . esc_url( $a[2] ) . '"><span class="dashicons ' . esc_attr( $a[0] ) . '" aria-hidden="true"></span>' . esc_html( $a[1] ) . '</a>';
		}
	}
	echo '</div>';

	// ---- every section
	foreach ( mcupr_admin_sections() as $key => $sec ) {
		$color = isset( $colors[ $key ] ) ? $colors[ $key ] : '#2563eb';
		$cards = '';
		foreach ( $sec['items'] as $i ) {
			$o = get_post_type_object( $i['type'] );
			if ( ! $o || ! current_user_can( $o->cap->edit_posts ) ) {
				continue;
			}
			$all = null;
			if ( null === $mine ) {
				$cnt = wp_count_posts( $i['type'] );
				$all = (int) $cnt->publish + (int) $cnt->draft + (int) $cnt->pending + (int) $cnt->private + (int) $cnt->future;
			}
			$add    = current_user_can( $o->cap->create_posts ) ? admin_url( 'post-new.php?post_type=' . $i['type'] ) : '';
			$cards .= mcupr_admin_card( admin_url( 'edit.php?post_type=' . $i['type'] ), $i['icon'], $o->labels->menu_name ? $o->labels->menu_name : $o->label, $i['desc'], $all, $color, $add, mcupr_admin_card_links( $i ) );
		}
		if ( '' !== $cards ) {
			echo '<h2 id="sec-' . esc_attr( $key ) . '">' . esc_html( $sec['label'] ) . '</h2><div class="ov-grid">' . $cards . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	// ---- the tools and settings (administrators)
	$tools = array(
		array( 'edit_theme_options', admin_url( 'customize.php?return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) ), 'dashicons-admin-customizer', __( 'Theme options', 'mcupr' ), __( 'Colours, logos, home page sections, menus, footer', 'mcupr' ) ),
		array( 'edit_theme_options', admin_url( 'admin.php?page=mcupr-blocks' ), 'dashicons-screenoptions', __( 'Home blocks', 'mcupr' ), __( 'Audience cards, figures, service cards, links, about block, weather', 'mcupr' ) ),
		array( 'manage_options', admin_url( 'admin.php?page=mcupr-status' ), 'dashicons-lock', __( 'Welcome & maintenance', 'mcupr' ), __( 'Welcome page for special occasions, maintenance mode, mourning', 'mcupr' ) ),
		array( 'manage_options', admin_url( 'admin.php?page=mcupr-settings-io' ), 'dashicons-database-import', __( 'Import / export settings', 'mcupr' ), __( 'Content files, settings files and the demos (home page, offices)', 'mcupr' ) ),
		array( 'manage_options', admin_url( 'admin.php?page=mcupr-speed' ), 'dashicons-performance', __( 'Speed check', 'mcupr' ), __( 'How fast the pages are and what slows them down', 'mcupr' ) ),
		array( 'manage_options', admin_url( 'admin.php?page=mcupr-images' ), 'dashicons-format-image', __( 'Pictures (WebP)', 'mcupr' ), __( 'Smaller pictures on upload for faster pages', 'mcupr' ) ),
		array( 'manage_options', admin_url( 'admin.php?page=mcupr-insights' ), 'dashicons-chart-line', __( 'Redirects & insights', 'mcupr' ), __( 'Redirects, pages that were not found, what visitors search for', 'mcupr' ) ),
	);
	$tc = '';
	foreach ( $tools as $t ) {
		if ( current_user_can( $t[0] ) ) {
			$tc .= mcupr_admin_card( $t[1], $t[2], $t[3], $t[4], null, '#475569' );
		}
	}
	if ( '' !== $tc ) {
		echo '<h2 id="sec-tools">' . esc_html__( 'Tools and settings', 'mcupr' ) . '</h2><div class="ov-grid">' . $tc . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	// the menu item of the group being looked at lights up (the address after # tells which; the server cannot see it)
	echo '<script>(function(){var m=document.querySelector("#toplevel_page_mcupr .wp-submenu");if(!m){return;}function sync(){var h=location.hash||"",g=/^#sec-/.test(h);Array.prototype.forEach.call(m.querySelectorAll("li"),function(li){var a=li.querySelector("a");if(!a){return;}var f=a.getAttribute("href")||"",on=g?f.indexOf(h)!==-1&&f.indexOf("#sec-")!==-1:/page=mcupr$/.test(f);li.classList.toggle("current",on);a.classList.toggle("current",on);if(on){a.setAttribute("aria-current","page");}else{a.removeAttribute("aria-current");}});}sync();window.addEventListener("hashchange",sync);})();</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
