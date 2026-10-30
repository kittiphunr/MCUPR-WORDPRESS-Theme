<?php
/**
 * The icon picker: the lists of icon names (an icon before a menu item, the icons of the home page blocks) show the icons themselves,
 * in a grid with a search box (assets/js/icon-picker.js). The select stays in the page as the real field, so saving does not change.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The Customizer controls that are icon lists: add one with its id, or read them all with no argument. */
function mcupr_icon_picker_controls( $add = '' ) {
	static $ids = array();
	if ( '' !== $add ) {
		$ids[] = $add;
	}
	return $ids;
}

/** What the script needs: the drawing of every icon of the theme, the Customizer controls to dress, the words. */
function mcupr_icon_picker_data() {
	return array(
		'paths'    => mcupr_icon_paths(),
		'controls' => array_values( array_unique( mcupr_icon_picker_controls() ) ),
		't'        => array(
			'choose'  => __( 'Choose an icon', 'mcupr' ),
			'none'    => __( '— none —', 'mcupr' ),
			'search'  => __( 'Search icons', 'mcupr' ),
			'nothing' => __( 'No icon matches', 'mcupr' ),
		),
	);
}

function mcupr_icon_picker_enqueue() {
	wp_enqueue_style( 'mcupr-icon-picker', get_theme_file_uri( 'assets/css/icon-picker.css' ), array(), MCUPR_VERSION );
	wp_enqueue_script( 'mcupr-icon-picker', get_theme_file_uri( 'assets/js/icon-picker.js' ), array( 'jquery' ), MCUPR_VERSION, true ); // in the footer: the script looks at the page body
	wp_localize_script( 'mcupr-icon-picker', 'mcuprIcons', mcupr_icon_picker_data() );
}

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'nav-menus.php' === $hook ) {
			mcupr_icon_picker_enqueue();
		}
	}
);

add_action(
	'customize_controls_enqueue_scripts',
	static function () {
		mcupr_icon_picker_enqueue();
	}
);
