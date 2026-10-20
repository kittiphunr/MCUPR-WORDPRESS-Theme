<?php
/**
 * The control center (ศูนย์ควบคุม): one home for everything the theme adds to the admin, laid out like the Windows Control Panel.
 *
 *  - the home page: search, the things done most often, what is on right now, and a few big groups (cards with a picture and a few links);
 *  - a page for each group: every menu in it with a plain sentence of what it is for, buttons to open it or add to it, and "how does it work";
 *  - the start page for a new campus (a checklist), and step-by-step guides ("how do I…");
 *  - on the theme's own screens: a bar "Control center › group › menu" and a button that explains the page.
 *
 * The content (names, descriptions, steps) is in control-center-data.php; the pictures are Feather icons (feather.php).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ the data and the links */

function mcupr_cc_data() {
	static $d = null;
	if ( null === $d ) {
		require_once MCUPR_DIR . '/inc/control-center-data.php';
		$d = mcupr_cc_arrange( mcupr_cc_data_all() );
	}
	return $d;
}

/** A link written as a short text (see control-center-data.php) → an address. */
function mcupr_cc_url( $spec ) {
	$spec = (string) $spec;
	$pos  = strpos( $spec, ':' );
	$kind = false === $pos ? '' : substr( $spec, 0, $pos );
	$arg  = false === $pos ? '' : substr( $spec, $pos + 1 );
	switch ( $kind ) {
		case 'type':
			return admin_url( 'edit.php?post_type=' . $arg );
		case 'new':
			if ( 'post' === $arg ) {
				return admin_url( 'post-new.php' );
			}
			return admin_url( 'post-new.php?post_type=' . $arg );
		case 'page':
			return admin_url( 'admin.php?page=' . $arg );
		case 'cust':
			return admin_url( 'customize.php?' . ( '' !== $arg ? 'autofocus[section]=' . $arg . '&' : '' ) . 'return=' . rawurlencode( admin_url( 'admin.php?page=mcupr' ) ) );
		case 'adm':
			return admin_url( $arg );
		case 'url':
			return $arg;
	}
	return admin_url( 'admin.php?page=mcupr' );
}

/** The right a person needs to see a menu: what the data says, or the right to edit the post type, or to edit pages. */
function mcupr_cc_item_cap( $item ) {
	if ( ! empty( $item['cap'] ) ) {
		return 'STU' === $item['cap'] ? MCUPR_STU_CAP : $item['cap'];
	}
	if ( 0 === strpos( (string) $item['open'], 'type:' ) ) {
		$o = get_post_type_object( substr( $item['open'], 5 ) );
		return $o ? $o->cap->edit_posts : 'edit_pages';
	}
	return 'edit_pages';
}

/** The menus this person may use, by id, each with its addresses (and how many items, for a post type). $cat: only that group. */
function mcupr_cc_items( $cat = '' ) {
	$out = array();
	foreach ( mcupr_cc_data()['items'] as $id => $item ) {
		if ( '' !== $cat && $item['cat'] !== $cat ) {
			continue;
		}
		if ( 0 === strpos( (string) $item['open'], 'type:' ) ) {
			$o = get_post_type_object( substr( $item['open'], 5 ) );
			if ( ! $o || ! mcupr_admin_can_type( $o ) ) {
				continue;
			}
		} elseif ( ! current_user_can( mcupr_cc_item_cap( $item ) ) ) {
			continue;
		}
		$item['id']  = $id;
		$item['url'] = mcupr_cc_url( $item['open'] );
		$item['add_url'] = '';
		if ( ! empty( $item['add'] ) ) {
			$type = 0 === strpos( $item['add'], 'new:' ) ? substr( $item['add'], 4 ) : '';
			$o    = '' !== $type ? get_post_type_object( $type ) : null;
			if ( '' === $type || ( $o && current_user_can( $o->cap->create_posts ) ) ) {
				$item['add_url'] = mcupr_cc_url( $item['add'] );
			}
		}
		$item['count'] = null;
		if ( 0 === strpos( (string) $item['open'], 'type:' ) && ( ! function_exists( 'mcupr_user_offices' ) || null === mcupr_user_offices() ) ) { // (not for people limited to their office: they would count what they may not see)
			$c = wp_count_posts( substr( $item['open'], 5 ) );
			$item['count'] = (int) $c->publish + (int) $c->draft + (int) $c->pending + (int) $c->private + (int) $c->future;
		}
		$out[ $id ] = $item;
	}
	return $out;
}

/** The groups this person has something in (the start page is for administrators, the help page for everyone). */
function mcupr_cc_cats() {
	$out = array();
	foreach ( mcupr_cc_data()['cats'] as $key => $c ) {
		if ( 'start' === $key ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				continue;
			}
		} elseif ( 'help' !== $key && ! mcupr_cc_items( $key ) ) {
			continue;
		}
		$out[ $key ] = $c;
	}
	return $out;
}

/** The address of a group's page. */
function mcupr_cc_cat_url( $key ) {
	return 'overview' === $key ? admin_url( 'admin.php?page=mcupr' ) : admin_url( 'admin.php?page=mcupr&cat=' . $key );
}

/** The submenu slug of a group (the menu opens the page at it). */
function mcupr_cc_cat_slug( $key ) {
	return 'admin.php?page=mcupr&cat=' . $key;
}

/* ------------------------------------------------------------------ the screens of WordPress that belong to a menu */

/** Does the screen being shown match a link of the data (a list, a page of the theme, a core screen)? */
function mcupr_cc_screen_matches( $spec ) {
	global $pagenow;
	$s    = get_current_screen();
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$pos  = strpos( $spec, ':' );
	$kind = substr( $spec, 0, $pos );
	$arg  = substr( $spec, $pos + 1 );
	if ( 'type' === $kind ) {
		return $s && $s->post_type === $arg;
	}
	if ( 'page' === $kind ) {
		return '' !== $page && $page === $arg;
	}
	if ( 'adm' === $kind ) {
		$parts = explode( '?', $arg, 2 );
		if ( $pagenow !== $parts[0] ) {
			return false;
		}
		$q = array();
		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $q );
		}
		foreach ( $q as $k => $v ) {
			if ( ! isset( $_GET[ $k ] ) || sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) !== (string) $v ) { // phpcs:ignore WordPress.Security.NonceVerification
				return false;
			}
		}
		if ( in_array( $parts[0], array( 'edit.php', 'post-new.php' ), true ) && ! isset( $q['post_type'] ) ) {
			return $s && 'post' === $s->post_type; // the list of news, not of another type
		}
		return true;
	}
	return false;
}

/** The menu of the control center the current screen belongs to ('' = none). */
function mcupr_cc_item_for_screen() {
	if ( ! function_exists( 'get_current_screen' ) || ! get_current_screen() ) {
		return '';
	}
	foreach ( mcupr_cc_data()['items'] as $id => $item ) {
		foreach ( isset( $item['screens'] ) ? $item['screens'] : array() as $spec ) {
			if ( mcupr_cc_screen_matches( $spec ) ) {
				return $id;
			}
		}
	}
	return '';
}

/** Which entry of the MCUPR menu lights up: the group of the screen, the group page itself, or the home. */
add_filter(
	'submenu_file',
	static function ( $file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'mcupr' === $page ) {
			$cat = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$all = mcupr_cc_cats();
			return isset( $all[ $cat ] ) ? mcupr_cc_cat_slug( $cat ) : 'mcupr';
		}
		$id = mcupr_cc_item_for_screen();
		if ( '' !== $id ) {
			$cat = mcupr_cc_data()['items'][ $id ]['cat'];
			if ( isset( mcupr_cc_cats()[ $cat ] ) ) {
				return mcupr_cc_cat_slug( $cat );
			}
		}
		return $file;
	}
);

/* ------------------------------------------------------------------ the start checklist */

/** Is a step of the checklist done by itself (it can be seen from the settings)? null = only the person can say. */
function mcupr_cc_auto_done( $key ) {
	switch ( $key ) {
		case 'identity':
			return '' !== trim( (string) get_option( 'blogname' ) ) && '' !== trim( (string) get_option( 'blogdescription' ) ) && 'Just another WordPress site' !== get_option( 'blogdescription' );
		case 'logo':
			return has_custom_logo();
		case 'colors':
			return null !== get_theme_mod( 'mcupr_palette', null );
		case 'menu':
			foreach ( (array) get_nav_menu_locations() as $loc => $menu_id ) {
				if ( 0 === strpos( $loc, 'primary_' ) && $menu_id ) {
					return true;
				}
			}
			return false;
		case 'pages':
			return (int) wp_count_posts( 'page' )->publish >= 3;
		case 'news':
			return (int) wp_count_posts( 'post' )->publish >= 1;
	}
	return null;
}

/** The steps of the checklist with whether each is done: array( step + 'done' (bool) + 'auto' (bool: seen from the settings) ). */
function mcupr_cc_checklist() {
	$manual = get_option( 'mcupr_cc_done', array() );
	$manual = is_array( $manual ) ? $manual : array();
	$out    = array();
	foreach ( mcupr_cc_data()['checklist'] as $st ) {
		$auto       = ! empty( $st['auto'] ) ? mcupr_cc_auto_done( $st['auto'] ) : null;
		$st['auto'] = true === $auto;
		$st['done'] = true === $auto || ! empty( $manual[ $st['id'] ] );
		$out[]      = $st;
	}
	return $out;
}

add_action(
	'admin_post_mcupr_cc_done',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'], $_POST['step'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mcupr_cc_done' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$step = sanitize_key( wp_unslash( $_POST['step'] ) );
		$ids  = wp_list_pluck( mcupr_cc_data()['checklist'], 'id' );
		if ( in_array( $step, $ids, true ) ) {
			$d = get_option( 'mcupr_cc_done', array() );
			$d = is_array( $d ) ? $d : array();
			if ( ! empty( $_POST['done'] ) ) {
				$d[ $step ] = 1;
			} else {
				unset( $d[ $step ] );
			}
			update_option( 'mcupr_cc_done', $d, false );
		}
		wp_safe_redirect( mcupr_cc_cat_url( 'start' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ pieces of a page */

function mcupr_cc_icon_tile( $icon, $color, $size = 22 ) {
	return '<span class="mcc-ic" style="--c:' . esc_attr( $color ) . '">' . mcupr_feather( $icon, $size ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** The line at the top of a page: Control center › group › page (the last is the page being read). */
function mcupr_cc_crumbs( $trail ) {
	$o = '<nav class="mcc-crumbs" aria-label="' . esc_attr__( 'You are here', 'mcupr' ) . '"><a href="' . esc_url( mcupr_cc_cat_url( 'overview' ) ) . '">' . mcupr_feather( 'home', 16 ) . esc_html__( 'Control center', 'mcupr' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$n = count( $trail );
	foreach ( $trail as $i => $t ) {
		$o .= mcupr_feather( 'chevron-right', 14, 'mcc-sep' ); // phpcs:ignore WordPress.Security.EscapeOutput
		$o .= $i === $n - 1 || empty( $t[1] ) ? '<span aria-current="page">' . esc_html( $t[0] ) . '</span>' : '<a href="' . esc_url( $t[1] ) . '">' . esc_html( $t[0] ) . '</a>';
	}
	return $o . '</nav>';
}

/** The help of one menu as a few lines: what it is, how to use it, tips, and the guide if there is one. */
function mcupr_cc_help_html( $item ) {
	$o = '';
	if ( ! empty( $item['what'] ) ) {
		$o .= '<p>' . esc_html( $item['what'] ) . '</p>';
	}
	if ( ! empty( $item['steps'] ) ) {
		$o .= '<h3>' . esc_html__( 'How to use it', 'mcupr' ) . '</h3><ol>';
		foreach ( $item['steps'] as $st ) {
			$o .= '<li>' . esc_html( $st ) . '</li>';
		}
		$o .= '</ol>';
	}
	if ( ! empty( $item['tips'] ) ) {
		$o .= '<h3>' . esc_html__( 'Good to know', 'mcupr' ) . '</h3><ul>';
		foreach ( $item['tips'] as $tip ) {
			$o .= '<li>' . esc_html( $tip ) . '</li>';
		}
		$o .= '</ul>';
	}
	if ( ! empty( $item['guide'] ) && isset( mcupr_cc_data()['guides'][ $item['guide'] ] ) ) {
		$g  = mcupr_cc_data()['guides'][ $item['guide'] ];
		$o .= '<p><a class="button" href="' . esc_url( mcupr_cc_guide_url( $item['guide'] ) ) . '">' . mcupr_feather( 'book-open', 16 ) . esc_html( sprintf( /* translators: %s: the name of the guide */ __( 'Step by step: %s', 'mcupr' ), $g['title'] ) ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $o;
}

function mcupr_cc_guide_url( $id ) {
	return admin_url( 'admin.php?page=mcupr&cat=help&guide=' . rawurlencode( $id ) );
}

/** One row of a group page: the picture, the name, a sentence, buttons, and the help that opens under it. */
function mcupr_cc_row( $item, $color ) {
	$o  = '<li class="mcc-row" id="m-' . esc_attr( $item['id'] ) . '"><div class="mcc-row-main">' . mcupr_cc_icon_tile( $item['icon'], $color ); // phpcs:ignore WordPress.Security.EscapeOutput
	$o .= '<div class="mcc-row-t"><h3><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
	if ( null !== $item['count'] ) {
		$o .= '<span class="mcc-n">' . esc_html( sprintf( /* translators: %d: how many */ __( '%d items', 'mcupr' ), $item['count'] ) ) . '</span>';
	}
	$o .= '</h3><p>' . esc_html( $item['desc'] ) . '</p>';
	$ls = array();
	foreach ( isset( $item['links'] ) ? $item['links'] : array() as $l ) {
		$ls[] = '<a href="' . esc_url( mcupr_cc_url( $l[1] ) ) . '">' . esc_html( $l[0] ) . '</a>';
	}
	if ( $ls ) {
		$o .= '<p class="mcc-links">' . implode( '', $ls ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$o .= '</div><div class="mcc-row-b"><a class="button button-primary" href="' . esc_url( $item['url'] ) . '">' . esc_html__( 'Open it', 'mcupr' ) . '</a>';
	if ( '' !== $item['add_url'] ) {
		$o .= '<a class="button" href="' . esc_url( $item['add_url'] ) . '">' . mcupr_feather( 'plus', 16 ) . esc_html__( 'Add new', 'mcupr' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$o .= '</div></div>';
	if ( ! empty( $item['what'] ) ) {
		$o .= '<details class="mcc-how"><summary>' . mcupr_feather( 'help-circle', 16 ) . esc_html__( 'What is it for and how do I use it?', 'mcupr' ) . '</summary><div class="mcc-how-b">' . mcupr_cc_help_html( $item ) . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $o . '</li>';
}

/** Search box + the index it searches (every menu and guide, as plain links; the script shows what matches). */
function mcupr_cc_search_box() {
	$index = array();
	foreach ( mcupr_cc_items() as $it ) {
		$index[] = array( $it['title'], $it['desc'], $it['url'], mcupr_cc_data()['cats'][ $it['cat'] ]['title'], $it['icon'] );
	}
	foreach ( mcupr_cc_data()['guides'] as $gid => $g ) {
		$index[] = array( $g['title'], __( 'Step by step', 'mcupr' ), mcupr_cc_guide_url( $gid ), __( 'Help', 'mcupr' ), $g['icon'] );
	}
	$o  = '<div class="mcc-search" role="search"><label class="screen-reader-text" for="mcc-q">' . esc_html__( 'Search the control center', 'mcupr' ) . '</label>' . mcupr_feather( 'search', 18 ) . '<input type="search" id="mcc-q" autocomplete="off" placeholder="' . esc_attr__( 'Search: for example news, logo, menu, students…', 'mcupr' ) . '" aria-controls="mcc-res"><div id="mcc-res" class="mcc-res" hidden></div><p class="screen-reader-text" id="mcc-live" aria-live="polite"></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$o .= '<script type="application/json" id="mcc-index">' . wp_json_encode( $index, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	return $o;
}

/* ------------------------------------------------------------------ the pages */

/** The home page of the control center. */
function mcupr_cc_render_home() {
	$cats  = mcupr_cc_cats();
	$data  = mcupr_cc_data();
	$admin = current_user_can( 'manage_options' );
	$mine  = function_exists( 'mcupr_user_offices' ) ? mcupr_user_offices() : null;
	echo '<header class="mcc-hero"><div><h1>' . mcupr_feather( 'sliders', 28 ) . esc_html__( 'Control center', 'mcupr' ) . '<span class="mcc-ver">v' . esc_html( MCUPR_VERSION ) . '</span></h1><p>' . esc_html__( 'Everything you can change on this website, in one place. Pick a group, or type what you want to do.', 'mcupr' ) . '</p></div>' . mcupr_cc_search_box() . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput

	// ---- what is on right now
	$chips = array();
	$st    = function_exists( 'mcupr_status' ) ? mcupr_status() : array();
	if ( $admin ) {
		$url     = admin_url( 'admin.php?page=mcupr-status' );
		$modes   = function_exists( 'mcupr_active_modes' ) ? mcupr_active_modes() : array();
		$chips[] = array( 'maint', __( 'Maintenance mode', 'mcupr' ), ! empty( $st['maint']['on'] ), $url, 'power' );
		$chips[] = array( 'welcome', __( 'Welcome page', 'mcupr' ), ! empty( $st['welcome']['on'] ), $url, 'gift' );
		$chips[] = array( 'mourning', __( 'Mourning', 'mcupr' ), in_array( 'mourning', $modes, true ), $url, 'moon' );
	}
	if ( current_user_can( MCUPR_STU_CAP ) && function_exists( 'mcupr_student_counts' ) ) {
		$sc      = mcupr_student_counts();
		$chips[] = array( 'students', __( 'Students studying', 'mcupr' ), true, admin_url( 'edit.php?post_type=mcupr_student&mcs_status=studying' ), 'users', (int) $sc['studying'] );
	}
	$waiting = array();
	if ( post_type_exists( 'mcupr_topic' ) && current_user_can( 'edit_pages' ) ) {
		$n = (int) wp_count_posts( 'mcupr_topic' )->pending;
		$c = (int) get_comments( array( 'status' => 'hold', 'post_type' => 'mcupr_topic', 'count' => true ) );
		if ( $n + $c ) {
			$waiting[] = array( sprintf( /* translators: 1: topics, 2: replies */ __( 'Forum: %1$d topics and %2$d replies to approve', 'mcupr' ), $n, $c ), admin_url( 'admin.php?page=mcupr-forum' ) );
			$chips[]   = array( 'forum', __( 'Forum waiting', 'mcupr' ), true, admin_url( 'admin.php?page=mcupr-forum' ), 'message-square', $n + $c );
		}
	}
	if ( $chips ) {
		echo '<section aria-labelledby="mcc-now"><h2 id="mcc-now">' . esc_html__( 'Right now', 'mcupr' ) . '</h2><div class="mcc-chips">';
		foreach ( $chips as $c ) {
			echo '<a class="mcc-chip' . ( $c[2] ? ' is-on' : '' ) . '" data-mode="' . esc_attr( $c[0] ) . '" href="' . esc_url( $c[3] ) . '">' . mcupr_feather( $c[4], 16 ) . esc_html( $c[1] ) . ' <em>' . ( isset( $c[5] ) ? (int) $c[5] : esc_html( $c[2] ? __( 'ON', 'mcupr' ) : __( 'off', 'mcupr' ) ) ) . '</em></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></section>';
	}
	if ( $waiting ) {
		echo '<section class="mcc-warn" aria-labelledby="mcc-att"><h2 id="mcc-att">' . mcupr_feather( 'alert-circle', 18 ) . esc_html__( 'Needs your attention', 'mcupr' ) . '</h2><ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $waiting as $w ) {
			echo '<li><a href="' . esc_url( $w[1] ) . '">' . esc_html( $w[0] ) . '</a></li>';
		}
		echo '</ul></section>';
	}
	if ( $mine ) {
		foreach ( $mine as $oid ) {
			if ( 'mcupr_office' !== get_post_type( $oid ) ) {
				continue;
			}
			$d = mcupr_office_data( $oid );
			echo '<section class="mcc-mine" style="--c:' . esc_attr( $d['color'] ) . '"><h2>' . mcupr_feather( 'briefcase', 18 ) . esc_html__( 'Your office', 'mcupr' ) . '</h2><p><strong>' . esc_html( get_the_title( $oid ) ) . '</strong> <a class="button" href="' . esc_url( get_permalink( $oid ) ) . '">' . esc_html__( 'View the office page', 'mcupr' ) . '</a></p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	// ---- done most often
	$acts = array(
		array( 'edit-3', __( 'Write news', 'mcupr' ), admin_url( 'post-new.php' ), 'edit_posts' ),
		array( 'calendar', __( 'Add an event', 'mcupr' ), admin_url( 'post-new.php?post_type=mcupr_event' ), 'edit_posts', 'mcupr_event' ),
		array( 'camera', __( 'Add a photo album', 'mcupr' ), admin_url( 'post-new.php?post_type=mcupr_album' ), 'edit_posts', 'mcupr_album' ),
		array( 'file', __( 'Add a page', 'mcupr' ), admin_url( 'post-new.php?post_type=page' ), 'edit_pages' ),
		array( 'upload-cloud', __( 'Upload pictures', 'mcupr' ), admin_url( 'media-new.php' ), 'upload_files' ),
		array( 'home', __( 'Edit the home page', 'mcupr' ), mcupr_cc_url( 'cust:mcupr_home' ), 'edit_theme_options' ),
	);
	$rows = '';
	foreach ( $acts as $a ) {
		if ( current_user_can( $a[3] ) && ( empty( $a[4] ) || post_type_exists( $a[4] ) ) ) {
			$rows .= '<a class="mcc-act" href="' . esc_url( $a[2] ) . '">' . mcupr_feather( $a[0], 18 ) . esc_html( $a[1] ) . '</a>';
		}
	}
	if ( '' !== $rows ) {
		echo '<section aria-labelledby="mcc-often"><h2 id="mcc-often">' . esc_html__( 'Done most often', 'mcupr' ) . '</h2><div class="mcc-acts">' . $rows . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	// ---- the groups
	echo '<section aria-labelledby="mcc-groups"><h2 id="mcc-groups">' . esc_html__( 'Groups', 'mcupr' ) . '</h2><div class="mcc-grid">';
	foreach ( $cats as $key => $c ) {
		$its   = mcupr_cc_items( $key );
		$links = '';
		if ( 'start' === $key ) {
			$list = mcupr_cc_checklist();
			$done = count( array_filter( wp_list_pluck( $list, 'done' ) ) );
			$links = '<li><span class="mcc-prog" role="img" aria-label="' . esc_attr( sprintf( /* translators: 1: done, 2: all */ __( '%1$d of %2$d steps done', 'mcupr' ), $done, count( $list ) ) ) . '"><i style="width:' . (int) round( 100 * $done / max( 1, count( $list ) ) ) . '%"></i></span>' . esc_html( sprintf( __( '%1$d of %2$d steps done', 'mcupr' ), $done, count( $list ) ) ) . '</li>';
		} elseif ( 'help' === $key ) {
			foreach ( array_slice( $data['guides'], 0, 3, true ) as $gid => $g ) {
				$links .= '<li><a href="' . esc_url( mcupr_cc_guide_url( $gid ) ) . '">' . esc_html( $g['title'] ) . '</a></li>';
			}
		} else {
			foreach ( array_slice( $its, 0, 4, true ) as $it ) {
				$links .= '<li><a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['title'] ) . '</a></li>';
			}
		}
		echo '<article class="mcc-card" style="--c:' . esc_attr( $c['color'] ) . '"><a class="mcc-card-h" href="' . esc_url( mcupr_cc_cat_url( $key ) ) . '">' . mcupr_cc_icon_tile( $c['icon'], $c['color'], 26 ) . '<span><b>' . esc_html( $c['title'] ) . '</b><small>' . esc_html( $c['desc'] ) . '</small></span></a><ul>' . $links . '</ul><a class="mcc-more" href="' . esc_url( mcupr_cc_cat_url( $key ) ) . '">' . esc_html__( 'See all', 'mcupr' ) . mcupr_feather( 'arrow-right', 16 ) . '</a></article>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div></section>';
}

/** The side list of groups on the pages inside. */
function mcupr_cc_side( $current ) {
	$o = '<nav class="mcc-side" aria-label="' . esc_attr__( 'Groups', 'mcupr' ) . '"><a href="' . esc_url( mcupr_cc_cat_url( 'overview' ) ) . '">' . mcupr_feather( 'home', 18 ) . esc_html__( 'Control center', 'mcupr' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( mcupr_cc_cats() as $key => $c ) {
		$o .= '<a href="' . esc_url( mcupr_cc_cat_url( $key ) ) . '"' . ( $key === $current ? ' class="is-current" aria-current="page"' : '' ) . '>' . mcupr_feather( $c['icon'], 18 ) . esc_html( $c['title'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $o . '</nav>';
}

/** The page of one group (and, for "help" and "start", their own content). */
function mcupr_cc_render_cat( $key ) {
	$cats = mcupr_cc_cats();
	$c    = $cats[ $key ];
	echo mcupr_cc_crumbs( array( array( $c['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="mcc-split">' . mcupr_cc_side( $key ) . '<main class="mcc-main"><header class="mcc-ph">' . mcupr_cc_icon_tile( $c['icon'], $c['color'], 30 ) . '<div><h1>' . esc_html( $c['title'] ) . '</h1><p>' . esc_html( $c['desc'] ) . '</p></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( 'start' === $key ) {
		mcupr_cc_render_start();
	} elseif ( 'help' === $key ) {
		mcupr_cc_render_help();
	} else {
		echo '<ul class="mcc-rows">';
		foreach ( mcupr_cc_items( $key ) as $it ) {
			echo mcupr_cc_row( $it, $c['color'] ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
	}
	echo '</main></div>';
}

/** The checklist for a new campus. */
function mcupr_cc_render_start() {
	$list = mcupr_cc_checklist();
	$done = count( array_filter( wp_list_pluck( $list, 'done' ) ) );
	$pct  = (int) round( 100 * $done / max( 1, count( $list ) ) );
	echo '<div class="mcc-progress"><b>' . esc_html( sprintf( /* translators: 1: done, 2: all */ __( '%1$d of %2$d steps done', 'mcupr' ), $done, count( $list ) ) ) . '</b><span class="mcc-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $pct . '"><i style="width:' . (int) $pct . '%"></i></span></div>';
	echo '<ol class="mcc-steps">';
	foreach ( $list as $n => $st ) {
		echo '<li class="mcc-step' . ( $st['done'] ? ' is-done' : '' ) . '"><span class="mcc-stn" aria-hidden="true">' . ( $st['done'] ? mcupr_feather( 'check', 18 ) : (int) ( $n + 1 ) ) . '</span><div class="mcc-step-t"><h3>' . esc_html( $st['title'] ) . ( $st['done'] ? '<span class="screen-reader-text"> – ' . esc_html__( 'done', 'mcupr' ) . '</span>' : '' ) . '</h3><p>' . esc_html( $st['desc'] ) . '</p></div><div class="mcc-step-b"><a class="button' . ( $st['done'] ? '' : ' button-primary' ) . '" href="' . esc_url( mcupr_cc_url( $st['link'] ) ) . '">' . esc_html( $st['done'] ? __( 'Open again', 'mcupr' ) : __( 'Do it now', 'mcupr' ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $st['auto'] ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_cc_done"><input type="hidden" name="step" value="' . esc_attr( $st['id'] ) . '">' . wp_nonce_field( 'mcupr_cc_done', '_wpnonce', true, false ) . '<input type="hidden" name="done" value="' . ( $st['done'] ? '0' : '1' ) . '"><button class="button-link mcc-mark" type="submit">' . esc_html( $st['done'] ? __( 'Mark as not done', 'mcupr' ) : __( 'Mark as done', 'mcupr' ) ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<span class="mcc-auto">' . esc_html__( 'Checked automatically', 'mcupr' ) . '</span>';
		}
		echo '</div></li>';
	}
	echo '</ol>';
}

/** The help group: the guides, and what the theme is. */
function mcupr_cc_render_help() {
	$gid = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$gs  = mcupr_cc_data()['guides'];
	if ( '' !== $gid && isset( $gs[ $gid ] ) ) {
		mcupr_cc_render_guide( $gid );
		return;
	}
	echo '<h2 class="mcc-h2">' . esc_html__( 'How do I…', 'mcupr' ) . '</h2><ul class="mcc-guides">';
	foreach ( $gs as $id => $g ) {
		echo '<li><a href="' . esc_url( mcupr_cc_guide_url( $id ) ) . '">' . mcupr_cc_icon_tile( $g['icon'], '#7c3aed', 20 ) . '<span><b>' . esc_html( $g['title'] ) . '</b><small>' . esc_html( sprintf( /* translators: 1: number of steps, 2: time */ __( '%1$d steps · about %2$s', 'mcupr' ), count( $g['steps'] ), $g['time'] ) ) . '</small></span>' . mcupr_feather( 'chevron-right', 18 ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul>';
	$th = wp_get_theme();
	echo '<h2 class="mcc-h2">' . esc_html__( 'About this theme', 'mcupr' ) . '</h2><div class="mcc-about"><p>' . mcupr_feather( 'info', 18 ) . esc_html( sprintf( /* translators: 1: theme name, 2: version */ __( '%1$s, version %2$s', 'mcupr' ), $th->get( 'Name' ), MCUPR_VERSION ) ) . '</p><p>' . esc_html__( 'Words used here: a "post" is one news item; a "page" is content that stays (About us, Contact); "Customizer" is the editor where you change the look and see the result at once; "publish" makes it visible to visitors; "draft" keeps it to yourself.', 'mcupr' ) . '</p><p>' . esc_html__( 'Icons: Feather (MIT licence).', 'mcupr' ) . '</p></div>';
}

/** One guide: the steps, each with what to do and a button that opens the right place. */
function mcupr_cc_render_guide( $id ) {
	$g = mcupr_cc_data()['guides'][ $id ];
	echo '<div class="mcc-guide-h"><h2>' . esc_html( $g['title'] ) . '</h2><p>' . esc_html( sprintf( /* translators: 1: number of steps, 2: time */ __( '%1$d steps · about %2$s', 'mcupr' ), count( $g['steps'] ), $g['time'] ) ) . '</p></div><ol class="mcc-steps">';
	foreach ( $g['steps'] as $n => $st ) {
		echo '<li class="mcc-step"><span class="mcc-stn" aria-hidden="true">' . (int) ( $n + 1 ) . '</span><div class="mcc-step-t"><h3>' . esc_html( $st[0] ) . '</h3><p>' . esc_html( $st[1] ) . '</p></div>';
		if ( ! empty( $st[2] ) ) {
			echo '<div class="mcc-step-b"><a class="button button-primary" href="' . esc_url( mcupr_cc_url( $st[2] ) ) . '">' . esc_html__( 'Go there', 'mcupr' ) . mcupr_feather( 'arrow-right', 16 ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</li>';
	}
	echo '</ol><p><a href="' . esc_url( mcupr_cc_cat_url( 'help' ) ) . '">' . mcupr_feather( 'arrow-left', 16 ) . esc_html__( 'All guides', 'mcupr' ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** The page behind the "Control center" menu entries (the home, a group, a guide). */
function mcupr_admin_overview() {
	if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( MCUPR_STU_CAP ) ) {
		return;
	}
	$cat = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$all = mcupr_cc_cats();
	echo '<style>' . mcupr_cc_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="wrap mcc">';
	if ( '' !== $cat && isset( $all[ $cat ] ) ) {
		$gid = isset( $_GET['guide'] ) ? sanitize_key( wp_unslash( $_GET['guide'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'help' === $cat && '' !== $gid && isset( mcupr_cc_data()['guides'][ $gid ] ) ) {
			echo mcupr_cc_crumbs( array( array( $all['help']['title'], mcupr_cc_cat_url( 'help' ) ), array( mcupr_cc_data()['guides'][ $gid ]['title'], '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="mcc-split">' . mcupr_cc_side( 'help' ) . '<main class="mcc-main"><header class="mcc-ph">' . mcupr_cc_icon_tile( $all['help']['icon'], $all['help']['color'], 30 ) . '<div><h1>' . esc_html( $all['help']['title'] ) . '</h1></div></header>'; // phpcs:ignore WordPress.Security.EscapeOutput
			mcupr_cc_render_guide( $gid );
			echo '</main></div>';
		} else {
			mcupr_cc_render_cat( $cat );
		}
	} else {
		mcupr_cc_render_home();
	}
	echo '</div><script>' . mcupr_cc_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/* ------------------------------------------------------------------ on the theme's own screens */

/** On a screen that belongs to a menu: "Control center › group › menu" and a button that explains the page. */
add_action(
	'in_admin_header',
	static function () {
		$s = get_current_screen();
		if ( ! $s || $s->is_block_editor() || 'toplevel_page_mcupr' === $s->id ) {
			return;
		}
		$id = mcupr_cc_item_for_screen();
		if ( '' === $id ) {
			return;
		}
		$data = mcupr_cc_data();
		$item = $data['items'][ $id ];
		$cats = mcupr_cc_cats();
		if ( ! isset( $cats[ $item['cat'] ] ) ) {
			return;
		}
		$item['id'] = $id;
		echo '<div class="mcc mcc-topbar"><style>' . mcupr_cc_css() . '</style>' . mcupr_cc_crumbs( array( array( $cats[ $item['cat'] ]['title'], mcupr_cc_cat_url( $item['cat'] ) ), array( $item['title'], '' ) ) ) . '<button type="button" class="button mcc-helpbtn" aria-haspopup="dialog" data-mcc-help>' . mcupr_feather( 'help-circle', 16 ) . esc_html__( 'About this page', 'mcupr' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<dialog class="mcc-dialog" aria-labelledby="mcc-dlg-t"><div class="mcc-dialog-h"><h2 id="mcc-dlg-t">' . mcupr_cc_icon_tile( $item['icon'], $cats[ $item['cat'] ]['color'], 20 ) . esc_html( $item['title'] ) . '</h2><button type="button" class="button-link mcc-x" data-mcc-close aria-label="' . esc_attr__( 'Close', 'mcupr' ) . '">' . mcupr_feather( 'x', 20 ) . '</button></div><div class="mcc-how-b">' . mcupr_cc_help_html( $item ) . '</div></dialog></div><script>' . mcupr_cc_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/** The menu of the theme stays one entry per group; pages below are reached through the groups. */
add_action(
	'admin_head',
	static function () {
		echo '<style>#adminmenu #toplevel_page_mcupr .wp-submenu li.mcupr-sub-hidden{display:none}</style>';
	}
);

/* ------------------------------------------------------------------ look and behaviour */

function mcupr_cc_css() {
	return <<<'CSS'
.mcc{--mc-b:#dcdcde;--mc-t:#1d2327;--mc-m:#50575e;--mc-bg:#fff;--mc-s:#f6f7f7;max-width:80rem;color:var(--mc-t)}
.mcc *{box-sizing:border-box}
.mcc .fi{flex:none;vertical-align:-3px}
.mcc h1,.mcc h2,.mcc h3{color:var(--mc-t)}
.mcc a{text-decoration:none}
.mcc-hero{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:18px;margin:16px 0 8px;padding:22px 26px;border-radius:18px;background:linear-gradient(135deg,#9d174d,#7c2d12);color:#fff}
.mcc-hero h1{display:flex;align-items:center;gap:10px;margin:0 0 6px;padding:0;color:#fff;font-size:1.7rem;line-height:1.25}
.mcc-hero p{margin:0;max-width:34rem;color:#fff;font-size:14px}
.mcc-ver{margin-left:6px;padding:2px 10px;border:1px solid #ffffff99;border-radius:999px;font-size:12px;font-weight:600}
.mcc-search{position:relative;flex:1 1 20rem;max-width:28rem}
.mcc-search>.fi{position:absolute;left:14px;top:13px;color:#646970}
.mcc-search input[type=search]{width:100%;min-height:44px;padding:6px 16px 6px 42px;border:0;border-radius:999px;background:#fff;color:#1d2327;font-size:15px;box-shadow:0 4px 18px #0003}
.mcc-search input[type=search]:focus{outline:3px solid #fbcfe8;outline-offset:1px}
.mcc-res{position:absolute;z-index:20;left:0;right:0;top:calc(100% + 8px);max-height:22rem;overflow:auto;padding:6px;border:1px solid var(--mc-b);border-radius:16px;background:#fff;box-shadow:0 14px 40px #0003}
.mcc-res a{display:flex;align-items:center;gap:12px;min-height:44px;padding:6px 10px;border-radius:12px;color:var(--mc-t)}
.mcc-res a:hover,.mcc-res a:focus,.mcc-res a.is-sel{background:#fdf2f8;outline:0}
.mcc-res b{display:block;font-size:14px}.mcc-res small{display:block;color:var(--mc-m);font-size:12px}
.mcc-res .mcc-none{padding:12px;color:var(--mc-m)}
.mcc h2{margin:26px 0 12px;padding:0;font-size:1.1rem;line-height:1.3}
.mcc-chips,.mcc-acts{display:flex;flex-wrap:wrap;gap:10px}
.mcc-chip,.mcc-act{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:6px 16px;border:1px solid var(--mc-b);border-radius:999px;background:var(--mc-bg);color:var(--mc-t);font-weight:600}
.mcc-chip.is-on{border-color:#16a34a;background:#f0fdf4}
.mcc-chip.is-on[data-mode=maint]{border-color:#b45309;background:#fffbeb}.mcc-chip.is-on[data-mode=welcome]{border-color:#1d4ed8;background:#eff6ff}.mcc-chip.is-on[data-mode=mourning]{border-color:#262626;background:#f5f5f5}
.mcc-chip em{font-style:normal;font-weight:400;color:var(--mc-m)}
.mcc-act:hover,.mcc-chip:hover{border-color:#9d174d;background:#fdf2f8}
.mcc-warn{margin-top:18px;padding:12px 18px;border:1px solid #f59e0b;border-radius:14px;background:#fffbeb}
.mcc-warn h2{display:flex;align-items:center;gap:8px;margin:0 0 6px}.mcc-warn ul{margin:0;padding-left:20px}
.mcc-mine{margin-top:18px;padding:12px 18px;border:1px solid var(--c);border-left-width:6px;border-radius:14px;background:var(--mc-bg)}.mcc-mine h2{display:flex;gap:8px;margin:0 0 6px}.mcc-mine p{margin:0}
.mcc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(19rem,1fr));gap:16px}
.mcc-card{display:flex;flex-direction:column;padding:16px;border:1px solid var(--mc-b);border-radius:18px;background:var(--mc-bg);transition:box-shadow .15s,border-color .15s}
.mcc-card:hover{border-color:var(--c);box-shadow:0 8px 26px #0000001a}
.mcc-card-h{display:flex;align-items:center;gap:14px;color:var(--mc-t)}
.mcc-card-h b{display:block;font-size:1.05rem}.mcc-card-h small{display:block;margin-top:2px;color:var(--mc-m);font-size:13px;line-height:1.45}
.mcc-ic{display:inline-grid;flex:none;place-items:center;width:46px;height:46px;border-radius:14px;background:color-mix(in srgb,var(--c) 14%,#fff);color:var(--c)}
.mcc-card ul{flex:1;margin:12px 0 8px;padding:0;list-style:none}
.mcc-card li{margin:0;padding:3px 0;border-top:1px solid #f0f0f1}.mcc-card li:first-child{border-top:0}
.mcc-card li a{display:block;padding:5px 2px;color:var(--mc-t)}.mcc-card li a:hover{color:var(--c)}
.mcc-more{display:inline-flex;align-items:center;gap:6px;font-weight:600;color:var(--c)}
.mcc-prog,.mcc-bar{display:block;height:8px;margin:2px 0 6px;border-radius:99px;background:#e5e7eb;overflow:hidden}.mcc-prog i,.mcc-bar i{display:block;height:100%;border-radius:99px;background:var(--c,#c2410c)}
.mcc-crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:10px 0 6px;font-size:13px}
.mcc-crumbs a{display:inline-flex;align-items:center;gap:5px;color:#9d174d;font-weight:600}.mcc-crumbs span{color:var(--mc-m)}.mcc-sep{color:#8c8f94}
.mcc-split{display:grid;grid-template-columns:15rem minmax(0,1fr);gap:20px;align-items:start}
.mcc-side{position:sticky;top:44px;display:flex;flex-direction:column;gap:4px;padding:10px;border:1px solid var(--mc-b);border-radius:18px;background:var(--mc-bg)}
.mcc-side a{display:flex;align-items:center;gap:10px;min-height:42px;padding:6px 12px;border-radius:12px;color:var(--mc-t);font-weight:600}
.mcc-side a:hover{background:var(--mc-s)}.mcc-side a.is-current{background:#9d174d;color:#fff}
.mcc-ph{display:flex;align-items:center;gap:16px;margin:0 0 14px;padding:16px 18px;border:1px solid var(--mc-b);border-radius:18px;background:var(--mc-bg)}
.mcc-ph h1{margin:0;padding:0;font-size:1.5rem}.mcc-ph p{margin:4px 0 0;color:var(--mc-m)}
.mcc-ph .mcc-ic{width:56px;height:56px;border-radius:16px}
.mcc-rows,.mcc-steps,.mcc-guides{margin:0;padding:0;list-style:none}
.mcc-row{margin:0 0 12px;padding:14px 16px;border:1px solid var(--mc-b);border-radius:18px;background:var(--mc-bg)}
.mcc-row-main{display:flex;flex-wrap:wrap;align-items:center;gap:14px}
.mcc-row-t{flex:1 1 18rem;min-width:0}
.mcc-row h3{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:0;font-size:1.05rem}
.mcc-row h3 a{color:var(--mc-t)}.mcc-row h3 a:hover{color:#9d174d}
.mcc-n{padding:1px 10px;border-radius:99px;background:var(--mc-s);color:var(--mc-m);font-size:12px;font-weight:600}
.mcc-row p{margin:3px 0 0;color:var(--mc-m)}
.mcc-links{display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:8px!important}.mcc-links a{color:#9d174d;font-weight:600;font-size:13px}
.mcc-row-b{display:flex;flex-wrap:wrap;gap:8px}
.mcc .button{display:inline-flex;align-items:center;gap:6px;min-height:40px;padding:2px 16px;border-radius:999px}
.mcc .button-primary{border-color:#9d174d;background:#9d174d}.mcc .button-primary:hover,.mcc .button-primary:focus{border-color:#7a1239;background:#7a1239}
.mcc-how{margin-top:10px;border-top:1px dashed var(--mc-b);padding-top:6px}
.mcc-how summary{display:inline-flex;align-items:center;gap:6px;min-height:36px;cursor:pointer;color:#9d174d;font-weight:600}
.mcc-how-b{padding:6px 4px 4px 22px}.mcc-how-b h3{margin:12px 0 4px;font-size:.95rem}.mcc-how-b ol,.mcc-how-b ul{margin:0;padding-left:22px}.mcc-how-b ul{list-style:disc}.mcc-how-b ol{list-style:decimal}.mcc-how-b li{margin:3px 0}
.mcc-steps{counter-reset:s}
.mcc-step{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:0 0 10px;padding:14px 16px;border:1px solid var(--mc-b);border-radius:18px;background:var(--mc-bg)}
.mcc-stn{display:inline-grid;flex:none;place-items:center;width:38px;height:38px;border-radius:50%;background:#9d174d;color:#fff;font-weight:700}
.mcc-step.is-done{background:#f0fdf4;border-color:#bbf7d0}.mcc-step.is-done .mcc-stn{background:#16a34a}
.mcc-step-t{flex:1 1 16rem;min-width:0}.mcc-step h3{margin:0;font-size:1rem}.mcc-step p{margin:3px 0 0;color:var(--mc-m)}
.mcc-step-b{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.mcc .mcc-mark{color:#9d174d;font-weight:600;min-height:32px;text-decoration:none}.mcc .mcc-mark:hover{text-decoration:underline}.mcc-auto{color:var(--mc-m);font-size:12px}
.mcc-progress{display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin:0 0 14px}.mcc-progress .mcc-bar{flex:1 1 12rem;max-width:26rem}
.mcc-h2{margin:6px 0 12px}
.mcc-guides li{margin:0 0 8px}.mcc-guides a{display:flex;align-items:center;gap:14px;min-height:56px;padding:8px 16px;border:1px solid var(--mc-b);border-radius:16px;background:var(--mc-bg);color:var(--mc-t)}
.mcc-guides a:hover{border-color:#7c3aed}.mcc-guides span{flex:1}.mcc-guides b{display:block}.mcc-guides small{color:var(--mc-m)}
.mcc-guide-h{margin:0 0 12px}.mcc-guide-h h2{margin:0}.mcc-guide-h p{margin:4px 0 0;color:var(--mc-m)}
.mcc-about{padding:14px 18px;border:1px solid var(--mc-b);border-radius:16px;background:var(--mc-bg)}.mcc-about p{margin:0 0 8px}
.mcc-topbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;max-width:none;margin:0 20px 0 2px}
.mcc-topbar .mcc-crumbs{margin:10px 0}
.mcc-helpbtn{margin-top:4px}
.mcc-dialog{width:min(36rem,calc(100vw - 2rem));max-height:80vh;padding:0;border:1px solid var(--mc-b);border-radius:20px;background:var(--mc-bg);color:var(--mc-t);box-shadow:0 20px 60px #0004}
.mcc-dialog::backdrop{background:#0006}
.mcc-dialog-h{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid var(--mc-b)}
.mcc-dialog-h h2{display:flex;align-items:center;gap:12px;margin:0;font-size:1.15rem}.mcc-dialog .mcc-ic{width:38px;height:38px;border-radius:12px}
.mcc-dialog .mcc-how-b{padding:12px 20px 18px}
.mcc-x{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;color:var(--mc-t)}.mcc-x:hover{background:var(--mc-s)}
.mcc a:focus-visible,.mcc button:focus-visible,.mcc summary:focus-visible,.mcc input:focus-visible{outline:3px solid #9d174d;outline-offset:2px}
@media (max-width:900px){.mcc-split{grid-template-columns:1fr}.mcc-side{position:static;flex-direction:row;flex-wrap:wrap}}
@media (prefers-reduced-motion:reduce){.mcc-card{transition:none}}
CSS;
}

function mcupr_cc_js() {
	return <<<'JS'
(function(){
 var q=document.getElementById('mcc-q'),res=document.getElementById('mcc-res'),idx=document.getElementById('mcc-index'),live=document.getElementById('mcc-live');
 if(q&&res&&idx&&!q.dataset.on){q.dataset.on='1';var data=[];try{data=JSON.parse(idx.textContent)}catch(e){}
  var sel=-1;function norm(s){return String(s||'').toLowerCase()}
  function render(){var v=norm(q.value).trim();sel=-1;if(!v){res.hidden=true;res.innerHTML='';if(live)live.textContent='';return}
   var hits=data.filter(function(d){return norm(d[0]).indexOf(v)>-1||norm(d[1]).indexOf(v)>-1||norm(d[3]).indexOf(v)>-1}).slice(0,8);
   res.hidden=false;res.innerHTML='';
   if(!hits.length){var n=document.createElement('p');n.className='mcc-none';n.textContent=q.getAttribute('data-none')||'ไม่พบ ลองคำอื่น เช่น ข่าว โลโก้ เมนู นิสิต';res.appendChild(n)}
   hits.forEach(function(d){var a=document.createElement('a');a.href=d[2];var s=document.createElement('span');var b=document.createElement('b');b.textContent=d[0];var m=document.createElement('small');m.textContent=d[3]+' · '+d[1];s.appendChild(b);s.appendChild(m);a.appendChild(s);res.appendChild(a)});
   if(live)live.textContent=hits.length+' รายการ'}
  q.addEventListener('input',render);
  q.addEventListener('keydown',function(e){var l=res.querySelectorAll('a');if(e.key==='Escape'){res.hidden=true;return}
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){if(!l.length)return;e.preventDefault();sel=(sel+(e.key==='ArrowDown'?1:-1)+l.length)%l.length;Array.prototype.forEach.call(l,function(x,i){x.classList.toggle('is-sel',i===sel)});l[sel].scrollIntoView({block:'nearest'})}
   if(e.key==='Enter'&&sel>-1){e.preventDefault();location.href=l[sel].href}});
  document.addEventListener('click',function(e){if(!res.contains(e.target)&&e.target!==q){res.hidden=true}})}
 var b=document.querySelector('[data-mcc-help]'),d=document.querySelector('.mcc-dialog');
 if(b&&d&&!b.dataset.on){b.dataset.on='1';b.addEventListener('click',function(){if(d.showModal){d.showModal()}else{d.setAttribute('open','')}});
  var x=d.querySelector('[data-mcc-close]');if(x){x.addEventListener('click',function(){d.close()})}
  d.addEventListener('click',function(e){if(e.target===d){d.close()}})}
})();
JS;
}
