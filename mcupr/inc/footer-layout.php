<?php
/**
 * Where the parts of the footer sit. The footer has up to three columns and four parts – the contact details, the map, the policy
 * links and the visitor statistics – and each part can be put in any column (Customizer → MCUPR: Footer). Parts in the same column
 * sit one under the other in that order; a column with nothing in it is left out and the others share the width; on a phone the
 * columns stack in order. The first arrangement balances the heights: contact | map | links and statistics.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_footer_blocks() {
	return array( 'contact', 'map', 'links', 'stats' );
}

/** The column of each part before anyone changes it. */
function mcupr_footer_defaults() {
	return array( 'contact' => 1, 'map' => 2, 'links' => 3, 'stats' => 3 );
}

/** Parts that can also be hidden (the map and the statistics have a switch of their own). */
function mcupr_footer_hideable() {
	return array( 'contact', 'links' );
}

/** The column (1–3, or 0 for hidden) of a part. */
function mcupr_footer_col( $block ) {
	$d = mcupr_footer_defaults();
	$c = (int) get_theme_mod( 'mcupr_footer_col_' . $block, $d[ $block ] );
	if ( 0 === $c && in_array( $block, mcupr_footer_hideable(), true ) ) {
		return 0;
	}
	return ( $c >= 1 && $c <= 3 ) ? $c : $d[ $block ];
}

/** array( array( parts of the first column that has something ), … ): only the parts in $present, only the columns that are not empty. */
function mcupr_footer_layout( $present ) {
	$cols = array( 1 => array(), 2 => array(), 3 => array() );
	foreach ( mcupr_footer_blocks() as $b ) {
		$c = in_array( $b, $present, true ) ? mcupr_footer_col( $b ) : 0;
		if ( $c ) {
			$cols[ $c ][] = $b;
		}
	}
	return array_values( array_filter( $cols ) );
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
	return $present;
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$labels = array(
			'contact' => __( 'Footer: column of the contact details', 'mcupr' ),
			'map'     => __( 'Footer: column of the map', 'mcupr' ),
			'links'   => __( 'Footer: column of the policy links', 'mcupr' ),
			'stats'   => __( 'Footer: column of the visitor statistics', 'mcupr' ),
		);
		foreach ( mcupr_footer_blocks() as $n => $b ) {
			$choices = array( 1 => __( 'Column 1 (left)', 'mcupr' ), 2 => __( 'Column 2 (middle)', 'mcupr' ), 3 => __( 'Column 3 (right)', 'mcupr' ) );
			if ( in_array( $b, mcupr_footer_hideable(), true ) ) {
				$choices[0] = __( 'Hide', 'mcupr' );
			}
			$d = mcupr_footer_defaults();
			$wp_customize->add_setting( 'mcupr_footer_col_' . $b, array( 'default' => $d[ $b ], 'sanitize_callback' => static function ( $v ) use ( $b ) { $v = (int) $v; return ( $v >= 1 && $v <= 3 ) || ( 0 === $v && in_array( $b, mcupr_footer_hideable(), true ) ) ? $v : mcupr_footer_defaults()[ $b ]; } ) );
			$wp_customize->add_control(
				'mcupr_footer_col_' . $b,
				array(
					'section'     => 'mcupr_org',
					'type'        => 'select',
					'label'       => $labels[ $b ],
					'description' => 0 === $n ? __( 'Put each part of the footer in the column you want. Parts in the same column sit one under the other (contact, map, links, statistics); an empty column is left out. On a phone the columns stack in this order.', 'mcupr' ) : '',
					'choices'     => $choices,
				)
			);
		}
	},
	11
);
