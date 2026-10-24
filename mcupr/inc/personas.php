<?php
/**
 * "I am…" picker above the home quick links (like chula.ac.th): each quick-link item can be marked for some kinds of visitor
 * (future student, current student, parent, staff, alumni); the visitor picks who they are and sees the links for them.
 * Items marked for nobody are shown to everyone. When no item is marked, there is no picker and nothing changes.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The kinds of visitor: key => string key of the label (strings.php). */
function mcupr_personas() {
	return array(
		'future'  => 'persona_future',
		'student' => 'persona_student',
		'parent'  => 'persona_parent',
		'staff'   => 'persona_staff',
		'alumni'  => 'persona_alumni',
	);
}

/** The kinds of visitor a menu item is for (empty = everyone). */
function mcupr_menu_item_for( $item_id ) {
	$v = get_post_meta( $item_id, '_mcupr_menu_for', true );
	$v = is_array( $v ) ? $v : array();
	return array_values( array_intersect( array_keys( mcupr_personas() ), array_map( 'sanitize_key', $v ) ) );
}

/** Admin: who is this item for (a row of tick boxes under each menu item). */
function mcupr_persona_item_field( $item_id, $item, $depth ) {
	if ( 0 !== (int) $depth ) {
		return;
	}
	$on = mcupr_menu_item_for( $item_id );
	echo '<input type="hidden" name="menu-item-mcupr-for-present[' . (int) $item_id . ']" value="1">';
	echo '<fieldset class="field-mcupr-for description description-wide"><legend>' . esc_html__( 'Quick links on the home page: who is this link for?', 'mcupr' ) . '</legend>';
	foreach ( mcupr_personas() as $key => $label ) {
		echo '<label style="margin-right:12px"><input type="checkbox" name="menu-item-mcupr-for[' . (int) $item_id . '][]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $on, true ), true, false ) . '> ' . esc_html( mcupr_t( $label ) ) . '</label>';
	}
	echo '<span class="description" style="display:block">' . esc_html__( 'Tick none to show it to everyone. When some links are ticked, the home page shows an “I am…” picker above the quick links.', 'mcupr' ) . '</span></fieldset>';
}
add_action( 'wp_nav_menu_item_custom_fields', 'mcupr_persona_item_field', 20, 3 );

/** Save the ticks (WordPress has already verified the menu form's nonce). */
function mcupr_persona_item_save( $menu_id, $item_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( ! current_user_can( 'edit_theme_options' ) || empty( $_POST['menu-item-mcupr-for-present'][ $item_id ] ) ) {
		return;
	}
	$raw  = isset( $_POST['menu-item-mcupr-for'][ $item_id ] ) ? (array) wp_unslash( $_POST['menu-item-mcupr-for'][ $item_id ] ) : array();
	$keep = array_values( array_intersect( array_keys( mcupr_personas() ), array_map( 'sanitize_key', $raw ) ) );
	if ( $keep ) {
		update_post_meta( $item_id, '_mcupr_menu_for', $keep );
	} else {
		delete_post_meta( $item_id, '_mcupr_menu_for' );
	}
	// phpcs:enable
}
add_action( 'wp_update_nav_menu_item', 'mcupr_persona_item_save', 10, 2 );

/** The starter file carries the ticks as CSS-class tokens ("mcupr-for-student"); move them into the real meta (the importer drops custom meta). */
function mcupr_persona_import_tokens( $menu_id, $item_id, $args = array() ) {
	if ( empty( $args['menu-item-classes'] ) ) {
		return;
	}
	$classes = is_array( $args['menu-item-classes'] ) ? $args['menu-item-classes'] : preg_split( '/\s+/', (string) $args['menu-item-classes'] );
	$keep    = array();
	$for     = array();
	$found   = false;
	foreach ( $classes as $c ) {
		if ( preg_match( '/^mcupr-for-([a-z]+)$/', $c, $m ) ) {
			if ( isset( mcupr_personas()[ $m[1] ] ) ) {
				$for[] = $m[1];
			}
			$found = true;
		} elseif ( '' !== $c && ! preg_match( '/^mcupr-(icon|mega)-/', $c ) ) { // (those two are taken by menus.php)
			$keep[] = $c;
		}
	}
	if ( $for ) {
		update_post_meta( $item_id, '_mcupr_menu_for', $for );
	}
	if ( $found ) {
		update_post_meta( $item_id, '_menu_item_classes', $keep );
	}
}
add_action( 'wp_update_nav_menu_item', 'mcupr_persona_import_tokens', 6, 3 ); // after menus.php has taken the icon and mega tokens

/** On the hero quick links, an item for some visitors gets a "for-<key>" class (the picker shows or hides it by that). */
add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item, $args ) {
		if ( ! empty( $args->menu_class ) && 'hero-menu' === $args->menu_class ) {
			foreach ( mcupr_menu_item_for( $item->ID ) as $k ) {
				$classes[] = 'for-' . $k;
			}
		}
		return $classes;
	},
	10,
	3
);

/** The kinds of visitor that have at least one link in this menu markup, in the usual order. */
function mcupr_personas_in( $menu_html ) {
	$out = array();
	foreach ( array_keys( mcupr_personas() ) as $k ) {
		if ( preg_match( '/class="[^"]*(?<![\w-])for-' . $k . '(?![\w-])/', $menu_html ) ) {
			$out[] = $k;
		}
	}
	return $out;
}

/* ------------------------------------------------------ filling the groups of a menu that was built before this existed */

/**
 * A sensible guess of who a quick link is for, from its icon and its address.
 * Returns the kinds of visitor, or an empty list for a link that suits everyone (contact, news, the home page…).
 */
function mcupr_persona_guess( $item ) {
	$icon = function_exists( 'mcupr_menu_item_icon' ) ? mcupr_menu_item_icon( $item ) : '';
	$text = mb_strtolower( (string) $item->title . ' ' . (string) $item->url );
	$has  = static function ( $words ) use ( $text ) {
		foreach ( $words as $w ) {
			if ( false !== mb_strpos( $text, $w ) ) {
				return true;
			}
		}
		return false;
	};
	// the order matters: the first that fits wins
	$rules = array(
		array( array( 'alumni' ), array( 'ศิษย์เก่า', 'alumni' ), array( 'alumni' ) ),
		array( array( 'edit', 'pen' ), array( 'สมัคร', 'รับเข้า', 'admission', 'apply', 'tcas' ), array( 'future' ) ),
		array( array( 'money', 'coins' ), array( 'ทุน', 'scholarship', 'ค่าเล่าเรียน', 'tuition', 'fee' ), array( 'future', 'student', 'parent' ) ),
		array( array( 'book', 'cap' ), array( 'หลักสูตร', 'programme', 'program', 'curriculum', 'คณะ', 'faculty' ), array( 'future', 'student' ) ),
		array( array(), array( 'ปฏิทินการศึกษา', 'academic calendar' ), array( 'student', 'parent', 'staff' ) ),
		array( array(), array( 'หอพัก', 'dorm', 'ทะเบียน', 'registrar', 'เกรด', 'grade', 'นิสิตปัจจุบัน', 'พอร์ทัล', 'portal', 'นิสิต', 'นักศึกษา', 'student' ), array( 'student' ) ),
		array( array( 'folder', 'books' ), array( 'ห้องสมุด', 'library', 'e-book', 'คลังความรู้' ), array( 'student', 'staff' ) ),
		array( array(), array( 'บุคลากร', 'staff', 'อาจารย์', 'teacher', 'วิจัย', 'research', 'intranet', 'ภายใน' ), array( 'staff' ) ),
		array( array(), array( 'ผู้ปกครอง', 'parent' ), array( 'parent' ) ),
	);
	foreach ( $rules as $r ) {
		if ( $has( $r[1] ) || ( $icon && in_array( $icon, $r[0], true ) ) ) {
			return $r[2];
		}
	}
	return array(); // everyone (contact, news, the home page…)
}

/** The menu the hero shows its quick links from: the "Quick links" location, else the main menu of the default language. */
function mcupr_persona_quick_menu_id() {
	$loc = get_nav_menu_locations();
	foreach ( array( 'quick', 'primary_' . mcupr_default_lang() ) as $where ) {
		if ( ! empty( $loc[ $where ] ) ) {
			return (int) $loc[ $where ];
		}
	}
	return 0;
}

/** The items of that menu (top level only, the ones the hero shows). */
function mcupr_persona_quick_items() {
	$id = mcupr_persona_quick_menu_id();
	if ( ! $id ) {
		return array();
	}
	$out = array();
	foreach ( (array) wp_get_nav_menu_items( $id ) as $it ) {
		if ( $it instanceof WP_Post && ! (int) $it->menu_item_parent ) {
			$out[] = $it;
		}
	}
	return $out;
}

/** Is the picker switched on, with a menu that has items and not one of them marked? */
function mcupr_persona_needs_filling() {
	if ( ! get_theme_mod( 'mcupr_hero_persona', true ) || ! get_theme_mod( 'mcupr_hero_quick', true ) || get_option( 'mcupr_persona_hide_hint' ) ) {
		return false;
	}
	$items = mcupr_persona_quick_items();
	if ( count( $items ) < 3 ) {
		return false;
	}
	foreach ( $items as $it ) {
		if ( mcupr_menu_item_for( $it->ID ) ) {
			return false;
		}
	}
	return true;
}

/** Fill the groups of the quick links that have none. Returns how many items were given a group. */
function mcupr_persona_fill() {
	$n = 0;
	foreach ( mcupr_persona_quick_items() as $it ) {
		if ( mcupr_menu_item_for( $it->ID ) ) {
			continue;
		}
		$for = mcupr_persona_guess( $it );
		if ( $for ) {
			update_post_meta( $it->ID, '_mcupr_menu_for', $for );
			$n++;
		}
	}
	return $n;
}

/** The offer, on the menus screen and on the MCUPR overview, to fill the groups in one press. */
function mcupr_persona_hint() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$where  = $screen ? $screen->id : '';
	if ( ! current_user_can( 'edit_theme_options' ) || ! in_array( $where, array( 'nav-menus', 'toplevel_page_mcupr' ), true ) || ! mcupr_persona_needs_filling() ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'The “I am…” picker on the home page is not showing yet', 'mcupr' ) . '</strong></p>';
	echo '<p>' . esc_html__( 'It appears as soon as the quick links of the home page say who they are for. None of them does yet, so every visitor sees all of them. You can tick the groups under each link below, or let the theme suggest them from the name of each link — you can change every tick afterwards.', 'mcupr' ) . '</p>';
	echo '<p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( 'mcupr_persona_fill' );
	echo '<input type="hidden" name="action" value="mcupr_persona_fill">';
	echo '<button type="submit" class="button button-primary">' . esc_html__( 'Suggest the groups for me', 'mcupr' ) . '</button> ';
	echo '</form> <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	wp_nonce_field( 'mcupr_persona_fill' );
	echo '<input type="hidden" name="action" value="mcupr_persona_fill"><input type="hidden" name="hide" value="1">';
	echo '<button type="submit" class="button">' . esc_html__( 'No thanks, do not ask again', 'mcupr' ) . '</button></form></p></div>';
}
add_action( 'admin_notices', 'mcupr_persona_hint' );

function mcupr_persona_fill_action() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mcupr_persona_fill' );
	$back = wp_get_referer() ? wp_get_referer() : admin_url( 'nav-menus.php' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['hide'] ) ) {
		update_option( 'mcupr_persona_hide_hint', 1, false );
		wp_safe_redirect( $back );
		exit;
	}
	$n = mcupr_persona_fill();
	wp_safe_redirect( add_query_arg( 'mcupr_personas', (int) $n, $back ) );
	exit;
}
add_action( 'admin_post_mcupr_persona_fill', 'mcupr_persona_fill_action' );

add_action(
	'admin_notices',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['mcupr_personas'] ) || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n = (int) $_GET['mcupr_personas'];
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $n ? sprintf( /* translators: %d: number of links */ __( 'Done: %d quick links were given a group. Open the home page to see the “I am…” picker, and change any tick under the links below.', 'mcupr' ), $n ) : __( 'None of the quick links could be matched to a group. Tick the groups under each link below by hand.', 'mcupr' ) ) . '</p></div>';
	}
);
