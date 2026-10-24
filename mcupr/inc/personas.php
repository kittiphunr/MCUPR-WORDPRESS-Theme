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
