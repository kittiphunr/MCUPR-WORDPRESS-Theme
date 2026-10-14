<?php
/**
 * Thai for everything the editor sees: Customizer, meta boxes, menu fields and admin lists.
 * The theme's English source strings are translated by the dictionary in admin-th-strings.php, so the whole
 * settings area reads in Thai whatever language WordPress itself uses. Switch it off with
 *   add_filter( 'mcupr_admin_thai', '__return_false' );
 * or give the whole site's own translation priority by loading a .mo file for the "mcupr" text domain.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_admin_th_dictionary() {
	static $d = null;
	if ( null === $d ) {
		$d = include MCUPR_DIR . '/inc/admin-th-strings.php';
	}
	return $d;
}

function mcupr_admin_th_active() {
	return apply_filters( 'mcupr_admin_thai', true );
}

function mcupr_admin_th_gettext( $translated, $text, $domain ) {
	if ( 'mcupr' !== $domain || $translated !== $text || ! mcupr_admin_th_active() ) {
		return $translated;
	}
	$d = mcupr_admin_th_dictionary();
	return isset( $d[ $text ] ) ? $d[ $text ] : $translated;
}
add_filter( 'gettext', 'mcupr_admin_th_gettext', 20, 3 );

/** Language names as the editor reads them in the admin: Thai, English, Chinese… in Thai when the Thai admin is on. */
function mcupr_admin_lang_name( $name ) {
	static $th = array( 'Thai' => 'ไทย', 'English' => 'อังกฤษ', 'Chinese' => 'จีน', 'Japanese' => 'ญี่ปุ่น' );
	return ( mcupr_admin_th_active() && isset( $th[ $name ] ) ) ? $th[ $name ] : $name;
}
