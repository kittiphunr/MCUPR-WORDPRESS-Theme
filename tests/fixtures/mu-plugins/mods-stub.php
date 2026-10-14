<?php
/**
 * Test stub: override theme mods per request with the cookie  mod_stub=<json>.
 * The token "ATT" in a value is replaced by the id of a seeded image attachment.
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
			foreach ( $over as $k => $v ) {
				$over[ $k ] = 'ATT' === $v ? $att : $v;
			}
			return array_merge( (array) $value, $over );
		}
	);
}
