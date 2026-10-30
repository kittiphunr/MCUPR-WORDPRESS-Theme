<?php
/**
 * Where the parts of the footer sit. The footer has up to three columns and seven parts: the contact details, the map, the policy
 * links, the visitor statistics and three widget areas of your own (Appearance → Widgets → "Footer – part 1–3"). In the Customizer
 * (MCUPR: Footer) the parts are arranged on a small board: drag a part into a column, or use its column list and the arrows; a part
 * can also be put in "Hidden". Parts in the same column sit one under the other in the order of the board; a column with nothing
 * in it is left out and the others share the width; on a phone the columns stack in order.
 *
 * The arrangement is one setting (mcupr_footer_layout, JSON: {"1":["contact"],"2":["map","links"],"3":["stats"],"0":[]}); while it is
 * not set, the column choices of version 1.79.0 (mcupr_footer_col_*) and then the first arrangement – contact | map and links |
 * statistics, which balances the heights – are used.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_footer_blocks() {
	return array( 'contact', 'map', 'links', 'stats', 'w1', 'w2', 'w3' );
}

/** The column of each part before anyone changes it. */
function mcupr_footer_defaults() {
	return array( 'contact' => 1, 'map' => 2, 'links' => 2, 'stats' => 3, 'w1' => 1, 'w2' => 2, 'w3' => 3 );
}

/** The widget area behind a "w1".."w3" part. */
function mcupr_footer_widget_area( $block ) {
	return 'footer-part-' . substr( $block, 1 );
}

/**
 * The saved arrangement as array( 0 => hidden, 1.. 3 => parts ), every part once. $json: the setting; anything that is not valid
 * gives the arrangement of 1.79.0 (its own column choices) or the first one. Parts the saved board does not know yet go to their usual column.
 */
function mcupr_footer_arrangement( $json = null ) {
	$d    = mcupr_footer_defaults();
	$json = null === $json ? get_theme_mod( 'mcupr_footer_layout', '' ) : $json;
	$in   = is_string( $json ) && '' !== $json ? json_decode( $json, true ) : null;
	$out  = array( 0 => array(), 1 => array(), 2 => array(), 3 => array() );
	$seen = array();
	if ( is_array( $in ) ) {
		foreach ( array( 1, 2, 3, 0 ) as $k ) {
			foreach ( isset( $in[ (string) $k ] ) && is_array( $in[ (string) $k ] ) ? $in[ (string) $k ] : array() as $b ) {
				if ( is_string( $b ) && in_array( $b, mcupr_footer_blocks(), true ) && ! isset( $seen[ $b ] ) ) {
					$out[ $k ][] = $b;
					$seen[ $b ]  = true;
				}
			}
		}
	}
	foreach ( mcupr_footer_blocks() as $b ) {
		if ( isset( $seen[ $b ] ) ) {
			continue;
		}
		$c = $d[ $b ];
		if ( ! is_array( $in ) ) { // the column choices of 1.79.0, while the board has not been saved
			$old = get_theme_mod( 'mcupr_footer_col_' . $b, null );
			if ( null !== $old && ( ( (int) $old >= 1 && (int) $old <= 3 ) || ( 0 === (int) $old && in_array( $b, array( 'contact', 'links' ), true ) ) ) ) {
				$c = (int) $old;
			}
		}
		$out[ $c ][] = $b;
	}
	return $out;
}

/** The setting's value as it is saved: valid JSON of the arrangement. */
function mcupr_footer_sanitize( $json ) {
	return wp_json_encode( mcupr_footer_arrangement( is_string( $json ) ? $json : '' ), JSON_UNESCAPED_SLASHES );
}

/** array( array( parts of the first column that has something ), … ): only the parts in $present, none of the hidden, only the columns that are not empty. */
function mcupr_footer_layout( $present ) {
	$arr  = mcupr_footer_arrangement();
	$cols = array();
	foreach ( array( 1, 2, 3 ) as $k ) {
		$col = array_values( array_intersect( $arr[ $k ], $present ) );
		if ( $col ) {
			$cols[] = $col;
		}
	}
	return $cols;
}

/** One part of the footer. $links: the policy links (array of array( url, string key )). */
function mcupr_footer_block( $block, $links ) {
	switch ( $block ) {
		case 'contact':
			mcupr_contact_list();
			break;
		case 'map':
			echo '<div class="fmap-col">';
			mcupr_map_card();
			echo '</div>';
			break;
		case 'links':
			echo '<nav class="flinks" aria-label="' . esc_attr( mcupr_t( 'footer_nav' ) ) . '"><h2 class="flinks-h">' . esc_html( mcupr_t( 'footer_nav' ) ) . '</h2><ul class="footer-menu">';
			foreach ( $links as $row ) {
				echo '<li><a href="' . esc_url( $row[0] ) . '">' . esc_html( mcupr_t( $row[1] ) ) . '</a></li>';
			}
			echo '</ul></nav>';
			break;
		case 'stats':
			if ( function_exists( 'mcupr_vc_card' ) ) {
				mcupr_vc_card();
			}
			break;
		case 'w1':
		case 'w2':
		case 'w3':
			mcupr_widget_area( mcupr_footer_widget_area( $block ), 'fwidgets' );
			break;
	}
}

/** Which parts have something to show now. */
function mcupr_footer_present( $links ) {
	$c       = mcupr_contact();
	$present = array( 'contact' );
	if ( $c['map_on'] && '' !== $c['map_q'] ) {
		$present[] = 'map';
	}
	if ( $links ) {
		$present[] = 'links';
	}
	if ( function_exists( 'mcupr_vc_enabled' ) && mcupr_vc_enabled() ) {
		$present[] = 'stats';
	}
	foreach ( array( 'w1', 'w2', 'w3' ) as $w ) {
		if ( is_active_sidebar( mcupr_footer_widget_area( $w ) ) ) {
			$present[] = $w;
		}
	}
	return $present;
}

/** The copyright line: the words written in the Customizer ({year} and {name} are filled in), else "© year name. All rights reserved." */
function mcupr_copyright_html() {
	$t = trim( (string) get_theme_mod( 'mcupr_copyright', '' ) );
	if ( '' === $t ) {
		return '© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( mcupr_org_name() ) . '. ' . esc_html( mcupr_t( 'rights' ) );
	}
	$t = str_replace( array( '{year}', '{name}' ), array( esc_html( wp_date( 'Y' ) ), esc_html( mcupr_org_name() ) ), $t );
	return wp_kses( $t, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array(), 'br' => array(), 'span' => array( 'class' => true ) ) );
}

add_action(
	'widgets_init',
	static function () {
		foreach ( array( 1, 2, 3 ) as $n ) {
			register_sidebar(
				array(
					'id'            => 'footer-part-' . $n,
					'name'          => sprintf( /* translators: %d: 1–3 */ __( 'Footer – part %d (placed on the footer board)', 'mcupr' ), $n ),
					'description'   => __( 'A part of the footer you fill with widgets. Customizer → MCUPR: Footer says which column it sits in and where.', 'mcupr' ),
					'before_widget' => '<section id="%1$s" class="widget %2$s">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="widget-title mcu-title">',
					'after_title'   => '</h2>',
				)
			);
		}
	},
	11
);

/** The board: parts, columns, a "Hidden" place; the JavaScript (assets/js/footer-layout-control.js) draws it from the setting. */
function mcupr_footer_board_data() {
	return array(
		'parts' => array(
			'contact' => __( 'Contact details', 'mcupr' ),
			'map'     => __( 'Map', 'mcupr' ),
			'links'   => __( 'Policy links', 'mcupr' ),
			'stats'   => __( 'Visitor statistics', 'mcupr' ),
			'w1'      => __( 'Widgets – part 1', 'mcupr' ),
			'w2'      => __( 'Widgets – part 2', 'mcupr' ),
			'w3'      => __( 'Widgets – part 3', 'mcupr' ),
		),
		'zones' => array(
			1 => __( 'Column 1 (left)', 'mcupr' ),
			2 => __( 'Column 2 (middle)', 'mcupr' ),
			3 => __( 'Column 3 (right)', 'mcupr' ),
			0 => __( 'Hidden', 'mcupr' ),
		),
		't'     => array(
			'up'    => __( 'Move up', 'mcupr' ),
			'down'  => __( 'Move down', 'mcupr' ),
			'zone'  => __( 'Column of %s', 'mcupr' ),
			'drag'  => __( 'Drag to move', 'mcupr' ),
			'empty' => __( 'Nothing here', 'mcupr' ),
			'moved' => __( '%1$s: %2$s, place %3$d', 'mcupr' ),
		),
	);
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		require_once MCUPR_DIR . '/inc/footer-layout-control.php';
		$wp_customize->add_setting( 'mcupr_footer_layout', array( 'default' => '', 'sanitize_callback' => 'mcupr_footer_sanitize' ) );
		$wp_customize->add_control(
			new MCUPR_Footer_Layout_Control(
				$wp_customize,
				'mcupr_footer_layout',
				array(
					'section'     => 'mcupr_org',
					'label'       => __( 'Footer: arrange the parts', 'mcupr' ),
					'description' => __( 'Drag a part into a column, or choose its column and use the arrows. Parts in one column sit one under the other; an empty column is left out; on a phone the columns stack in order. “Widgets – part 1–3” are filled in Appearance → Widgets.', 'mcupr' ),
				)
			)
		);
		$wp_customize->add_setting( 'mcupr_copyright', array( 'default' => '', 'sanitize_callback' => static function ( $v ) { return wp_kses( (string) $v, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array(), 'br' => array(), 'span' => array( 'class' => true ) ) ); } ) );
		$wp_customize->add_control( 'mcupr_copyright', array( 'section' => 'mcupr_org', 'type' => 'textarea', 'label' => __( 'Footer: copyright line', 'mcupr' ), 'description' => __( 'Leave empty for “© year name. All rights reserved.” {year} and {name} are filled in (the year, and the name of the organisation). A link, bold or italic is allowed.', 'mcupr' ) ) );
	},
	11
);

add_action(
	'customize_controls_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'mcupr-footer-board', get_theme_file_uri( 'assets/css/footer-layout-control.css' ), array(), MCUPR_VERSION );
		wp_enqueue_script( 'mcupr-footer-board', get_theme_file_uri( 'assets/js/footer-layout-control.js' ), array( 'customize-controls' ), MCUPR_VERSION, true );
		wp_localize_script( 'mcupr-footer-board', 'mcuprFl', mcupr_footer_board_data() );
	}
);
