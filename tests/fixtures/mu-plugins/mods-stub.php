<?php
/**
 * Test stub: override theme mods per request with the cookie  mod_stub=<json>.
 * The token "ATT" in a value is replaced by the id of a seeded image attachment.
 * "CAT:<name>" in a value is replaced by that category's id.
 * "MENU:<name>" in a value is replaced by that menu's id (e.g. nav_menu_locations).
 * Example: {"mcupr_palette":"navy","custom_logo":"ATT"}
 */
if ( isset( $_COOKIE['mod_stub'] ) ) {
	add_filter(
		'option_theme_mods_mcupr',
		function ( $value ) {
			$over = json_decode( stripslashes( $_COOKIE['mod_stub'] ), true );
			if ( ! is_array( $over ) ) {
				return $value;
			}
			$att = (int) get_option( 'mcupr_test_attachment' );
			$resolve = function ( $v ) use ( &$resolve, $att ) {
				if ( is_array( $v ) ) {
					return array_map( $resolve, $v );
				}
				if ( 'ATT' === $v ) {
					return $att;
				}
				if ( is_string( $v ) && 0 === strpos( $v, 'MENU:' ) ) { // "MENU:Main TH" -> the id of the menu with that name
					$menu = wp_get_nav_menu_object( substr( $v, 5 ) );
					return $menu ? (int) $menu->term_id : 0;
				}
				if ( is_string( $v ) && 0 === strpos( $v, 'CAT:' ) ) { // "CAT:<name>" -> the id of the category with that name
					$term = get_term_by( 'name', substr( $v, 4 ), 'category' );
					return $term ? (int) $term->term_id : 0;
				}
				return $v;
			};
			foreach ( $over as $k => $v ) {
				$over[ $k ] = $resolve( $v );
			}
			return array_merge( (array) $value, $over );
		}
	);
}
