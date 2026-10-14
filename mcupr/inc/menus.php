<?php
/**
 * Menu management: an icon before the text of any menu item, and "mega menu"
 * panels for top-level items of the main menu. Everything is edited where
 * editors already work – Appearance → Menus (and the Customizer menu editor).
 *
 * Per menu item (post meta on the nav_menu_item):
 *   _mcupr_menu_icon   an icon name from mcupr_icon_paths()
 *   _mcupr_menu_mega   1 = show this top-level item's sub-menu as a mega panel
 *   _mcupr_menu_cols   2 | 3 | 4 columns of the mega panel
 *
 * In a mega panel the second level are the columns' headings (with their own
 * children listed below); second-level items without children are plain links.
 * The "Description" field of an item (Screen Options) is shown under its link.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Icon of a menu item: its field, else the legacy CSS class "icon-xxx". */
function mcupr_menu_item_icon( $item ) {
	$icon  = sanitize_key( (string) get_post_meta( $item->ID, '_mcupr_menu_icon', true ) );
	$paths = mcupr_icon_paths();
	if ( $icon && isset( $paths[ $icon ] ) ) {
		return $icon;
	}
	foreach ( (array) $item->classes as $c ) {
		if ( 0 === strpos( (string) $c, 'icon-' ) && isset( $paths[ substr( $c, 5 ) ] ) ) {
			return substr( $c, 5 );
		}
	}
	return '';
}

function mcupr_menu_item_is_mega( $item ) {
	return 0 === (int) $item->menu_item_parent && (bool) get_post_meta( $item->ID, '_mcupr_menu_mega', true );
}

function mcupr_menu_item_cols( $item_id ) {
	$cols = (int) get_post_meta( $item_id, '_mcupr_menu_cols', true );
	return in_array( $cols, array( 2, 3, 4 ), true ) ? $cols : 3;
}

/* ------------------------------------------------------------------ Admin */

/** Fields under each menu item in Appearance → Menus. */
function mcupr_menu_item_fields( $item_id, $item, $depth ) {
	$icon = mcupr_menu_item_icon( $item );
	$mega = (bool) get_post_meta( $item_id, '_mcupr_menu_mega', true );
	$cols = mcupr_menu_item_cols( $item_id );
	echo '<input type="hidden" name="menu-item-mcupr-present[' . (int) $item_id . ']" value="1">';
	echo '<p class="field-mcupr-icon description description-wide"><label for="edit-menu-item-mcupr-icon-' . (int) $item_id . '">' . esc_html__( 'Icon before the text', 'mcupr' ) . '<br>';
	echo '<select class="widefat" id="edit-menu-item-mcupr-icon-' . (int) $item_id . '" name="menu-item-mcupr-icon[' . (int) $item_id . ']"><option value="">' . esc_html__( '— none —', 'mcupr' ) . '</option>';
	foreach ( array_keys( mcupr_icon_paths() ) as $name ) {
		echo '<option value="' . esc_attr( $name ) . '"' . selected( $icon, $name, false ) . '>' . esc_html( $name ) . '</option>';
	}
	echo '</select></label></p>';
	if ( 0 === (int) $depth ) {
		echo '<p class="field-mcupr-mega description description-wide"><label for="edit-menu-item-mcupr-mega-' . (int) $item_id . '"><input type="checkbox" id="edit-menu-item-mcupr-mega-' . (int) $item_id . '" name="menu-item-mcupr-mega[' . (int) $item_id . ']" value="1"' . checked( $mega, true, false ) . '> ' . esc_html__( 'Show the sub-menu as a mega menu (top bar, desktop)', 'mcupr' ) . '</label></p>';
		echo '<p class="field-mcupr-cols description description-wide"><label for="edit-menu-item-mcupr-cols-' . (int) $item_id . '">' . esc_html__( 'Mega menu columns', 'mcupr' ) . '<br><select id="edit-menu-item-mcupr-cols-' . (int) $item_id . '" name="menu-item-mcupr-cols[' . (int) $item_id . ']">';
		foreach ( array( 2, 3, 4 ) as $n ) {
			echo '<option value="' . (int) $n . '"' . selected( $cols, $n, false ) . '>' . (int) $n . '</option>';
		}
		echo '</select></label><span class="description">' . esc_html__( 'Second-level items become column headings; their children are the links. Turn on “Description” in Screen Options to add a line under a link.', 'mcupr' ) . '</span></p>';
	}
}
add_action( 'wp_nav_menu_item_custom_fields', 'mcupr_menu_item_fields', 10, 3 );

/** Save the fields (WordPress has already verified the menu form's nonce). */
function mcupr_menu_item_save( $menu_id, $item_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( ! current_user_can( 'edit_theme_options' ) || empty( $_POST['menu-item-mcupr-present'][ $item_id ] ) ) {
		return;
	}
	$icon  = isset( $_POST['menu-item-mcupr-icon'][ $item_id ] ) ? sanitize_key( wp_unslash( $_POST['menu-item-mcupr-icon'][ $item_id ] ) ) : '';
	$paths = mcupr_icon_paths();
	if ( $icon && isset( $paths[ $icon ] ) ) {
		update_post_meta( $item_id, '_mcupr_menu_icon', $icon );
	} else {
		delete_post_meta( $item_id, '_mcupr_menu_icon' );
	}
	if ( ! empty( $_POST['menu-item-mcupr-mega'][ $item_id ] ) ) {
		update_post_meta( $item_id, '_mcupr_menu_mega', 1 );
	} else {
		delete_post_meta( $item_id, '_mcupr_menu_mega' );
	}
	$cols = isset( $_POST['menu-item-mcupr-cols'][ $item_id ] ) ? absint( $_POST['menu-item-mcupr-cols'][ $item_id ] ) : 3;
	update_post_meta( $item_id, '_mcupr_menu_cols', in_array( $cols, array( 2, 3, 4 ), true ) ? $cols : 3 );
	// phpcs:enable
}
add_action( 'wp_update_nav_menu_item', 'mcupr_menu_item_save', 10, 2 );

/**
 * The WordPress Importer drops custom meta of menu items, so the starter file carries the icon / mega settings as
 * CSS-class tokens ("mcupr-icon-bank", "mcupr-mega-3"). Move them into the real meta when the item is created.
 */
function mcupr_menu_item_import_tokens( $menu_id, $item_id, $args = array() ) {
	if ( empty( $args['menu-item-classes'] ) ) {
		return;
	}
	$classes = is_array( $args['menu-item-classes'] ) ? $args['menu-item-classes'] : preg_split( '/\s+/', (string) $args['menu-item-classes'] );
	$keep    = array();
	$paths   = mcupr_icon_paths();
	$found   = false;
	foreach ( $classes as $c ) {
		if ( 0 === strpos( $c, 'mcupr-icon-' ) && isset( $paths[ substr( $c, 11 ) ] ) ) {
			update_post_meta( $item_id, '_mcupr_menu_icon', substr( $c, 11 ) );
			$found = true;
		} elseif ( preg_match( '/^mcupr-mega-([234])$/', $c, $m ) ) {
			update_post_meta( $item_id, '_mcupr_menu_mega', 1 );
			update_post_meta( $item_id, '_mcupr_menu_cols', (int) $m[1] );
			$found = true;
		} elseif ( '' !== $c ) {
			$keep[] = $c;
		}
	}
	if ( $found ) {
		update_post_meta( $item_id, '_menu_item_classes', $keep );
	}
}
add_action( 'wp_update_nav_menu_item', 'mcupr_menu_item_import_tokens', 5, 3 );

/* --------------------------------------------------------------- Front end */

/** Classes: mega flag + column count on top-level items, "has-icon" on any item with an icon. */
add_filter(
	'nav_menu_css_class',
	static function ( $classes, $item ) {
		if ( mcupr_menu_item_is_mega( $item ) ) {
			$classes[] = 'mcupr-mega';
			$classes[] = 'mega-cols-' . mcupr_menu_item_cols( $item->ID );
		}
		if ( mcupr_menu_item_icon( $item ) ) {
			$classes[] = 'has-icon';
		}
		return $classes;
	},
	10,
	2
);

/**
 * Item markup: [icon] text [description]. The main menu keeps its initial-letter badge for the
 * sidebar / drawer when a top-level item has no icon.
 */
add_filter(
	'nav_menu_item_title',
	static function ( $title, $item, $args, $depth ) {
		if ( empty( $args->menu_class ) || 'hero-menu' === $args->menu_class ) {
			return $title;
		}
		$icon = mcupr_menu_item_icon( $item );
		$desc = trim( (string) $item->description );
		$out  = $title;
		if ( $icon ) {
			$out = '<span class="ic has-ic" aria-hidden="true">' . mcupr_icon( $icon, 20 ) . '</span><span class="t">' . $title . '</span>';
		} elseif ( 'menu' === $args->menu_class && 0 === (int) $item->menu_item_parent ) {
			$out = '<span class="ic" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $title ), 0, 1 ) ) ) . '</span><span class="t">' . $title . '</span>';
		}
		if ( $desc && $depth > 0 ) {
			$out .= '<span class="d">' . esc_html( $desc ) . '</span>';
		}
		return $out; // phpcs:ignore WordPress.Security.EscapeOutput -- $title is already escaped by WordPress; icons are fixed SVG
	},
	10,
	4
);

/* ------------------------------------------------------------ Starter content */

/** A custom link such as "/" or "/news/" means "on this site": works for sites in a sub-folder too. */
add_filter(
	'wp_setup_nav_menu_item',
	static function ( $item ) {
		if ( ! is_admin() && 'custom' === $item->type && is_string( $item->url ) && 0 === strpos( $item->url, '/' ) && 0 !== strpos( $item->url, '//' ) ) {
			$item->url = home_url( $item->url );
		}
		return $item;
	}
);

/**
 * After the starter file (mcupr/starter/mcupr-starter.xml) was imported: put each imported menu into the
 * location named by its term meta "_mcupr_location" – only into locations that have no menu yet.
 */
function mcupr_dedupe_imported_menu_items( $menu_id ) {
	$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
	if ( ! $items ) {
		return;
	}
	usort(
		$items,
		static function ( $a, $b ) {
			return $a->ID - $b->ID;
		}
	);
	$path = array();
	$seen = array();
	foreach ( $items as $it ) {
		$parent         = (int) $it->menu_item_parent;
		$key            = ( $parent && isset( $path[ $parent ] ) ? $path[ $parent ] : '' ) . '>' . $it->type . '|' . $it->object . '|' . ( 'custom' === $it->type ? 0 : $it->object_id ) . '|' . $it->title . '|' . ( 'custom' === $it->type ? $it->url : '' );
		$path[ $it->ID ] = $key;
		if ( isset( $seen[ $key ] ) ) {
			wp_delete_post( $it->ID, true ); // the starter file was imported twice
		} else {
			$seen[ $key ] = true;
		}
	}
}

function mcupr_assign_imported_menus() {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations = is_array( $locations ) ? $locations : array();
	$registered = get_registered_nav_menus();
	$changed    = false;
	foreach ( get_terms( array( 'taxonomy' => 'nav_menu', 'hide_empty' => false ) ) as $menu ) {
		$loc = (string) get_term_meta( $menu->term_id, '_mcupr_location', true );
		if ( $loc ) {
			mcupr_dedupe_imported_menu_items( $menu->term_id );
		}
		if ( $loc && isset( $registered[ $loc ] ) && empty( $locations[ $loc ] ) ) {
			$locations[ $loc ] = $menu->term_id;
			$changed           = true;
		}
	}
	if ( $changed ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}
add_action( 'import_end', 'mcupr_assign_imported_menus' );

/** A menu item whose link is just "#" is a label (e.g. a mega-menu column heading): no href, so it is not a dead link. */
add_filter(
	'nav_menu_link_attributes',
	static function ( $atts, $item ) {
		if ( 'custom' === $item->type && ( empty( $atts['href'] ) || '#' === $atts['href'] ) ) {
			unset( $atts['href'] );
			$atts['class'] = trim( ( isset( $atts['class'] ) ? $atts['class'] : '' ) . ' menu-label' );
		}
		return $atts;
	},
	10,
	2
);
