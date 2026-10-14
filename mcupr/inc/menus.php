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
