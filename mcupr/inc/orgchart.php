<?php
/**
 * Organisation chart (ผังการบริหารงาน), editable in the admin: post type "mcupr_unit" (Administration chart).
 * Each unit / position has a name, the person in the position, a role, a link and a photo; its place in the
 * chart is the "Parent" (the unit above it) and the "Order" among its siblings – the levels of administration.
 * Show it anywhere with  [mcupr_orgchart]  (optional: root="ID" to start from one unit, depth="4").
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_orgchart_register() {
	register_post_type(
		'mcupr_unit',
		array(
			'labels'          => array(
				'name'               => __( 'Administration chart', 'mcupr' ),
				'singular_name'      => __( 'Unit / position', 'mcupr' ),
				'add_new_item'       => __( 'Add a unit / position', 'mcupr' ),
				'edit_item'          => __( 'Edit unit / position', 'mcupr' ),
				'all_items'          => __( 'All units', 'mcupr' ),
				'menu_name'          => __( 'Administration chart', 'mcupr' ),
				'parent_item_colon'  => __( 'Unit above (parent):', 'mcupr' ),
				'attributes'         => __( 'Level in the chart', 'mcupr' ),
				'not_found'          => __( 'No units yet', 'mcupr' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => true,
			'hierarchical'    => true,
			'menu_icon'       => 'dashicons-networking',
			'menu_position'   => 8,
			'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
			'capability_type' => 'page',
		)
	);
}
add_action( 'init', 'mcupr_orgchart_register' );

/* ------------------------------------------------------------------ admin */

function mcupr_unit_box_add() {
	add_meta_box( 'mcupr_unit', __( 'Unit details', 'mcupr' ), 'mcupr_unit_box', 'mcupr_unit', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'mcupr_unit_box_add' );

function mcupr_unit_box( $post ) {
	wp_nonce_field( 'mcupr_unit_save', 'mcupr_unit_nonce' );
	$f = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_unit[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	echo '<p class="description">' . esc_html__( 'The title above is the name of the unit (e.g. “Office of the President”). Put the unit in the chart with “Level in the chart” on the right: choose the unit above it as Parent, and use Order to arrange units on the same level (smaller number first).', 'mcupr' ) . '</p>';
	$f( __( 'Person in the position (optional)', 'mcupr' ), 'person', get_post_meta( $post->ID, '_mcupr_unit_person', true ), 'text', __( 'e.g. Assoc. Prof. Dr. Somchai Jaidee', 'mcupr' ) );
	$f( __( 'Position / role (optional)', 'mcupr' ), 'role', get_post_meta( $post->ID, '_mcupr_unit_role', true ), 'text', __( 'e.g. President', 'mcupr' ) );
	$f( __( 'Link when the box is clicked (optional)', 'mcupr' ), 'url', get_post_meta( $post->ID, '_mcupr_unit_url', true ), 'url', 'https://' );
	echo '<fieldset style="border:1px solid #c3c4c7;padding:8px 12px;margin-top:8px"><legend><strong>' . esc_html__( 'Unit name in other languages (optional)', 'mcupr' ) . '</strong></legend>';
	foreach ( mcupr_languages() as $code => $l ) {
		if ( $code === mcupr_default_lang() ) {
			continue;
		}
		echo '<p><label>' . esc_html( $l['label'] ) . '<br><input type="text" class="widefat" name="mcupr_unit[tr_' . esc_attr( $code ) . ']" value="' . esc_attr( get_post_meta( $post->ID, "_mcupr_{$code}_title", true ) ) . '"></label></p>';
	}
	echo '</fieldset>';
}

function mcupr_unit_save( $post_id ) {
	if ( ! isset( $_POST['mcupr_unit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_unit_nonce'] ) ), 'mcupr_unit_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['mcupr_unit'] ) && is_array( $_POST['mcupr_unit'] ) ? wp_unslash( $_POST['mcupr_unit'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$g  = static function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
	};
	update_post_meta( $post_id, '_mcupr_unit_person', sanitize_text_field( $g( 'person' ) ) );
	update_post_meta( $post_id, '_mcupr_unit_role', sanitize_text_field( $g( 'role' ) ) );
	update_post_meta( $post_id, '_mcupr_unit_url', esc_url_raw( $g( 'url' ) ) );
	foreach ( array_keys( mcupr_languages() ) as $code ) {
		if ( $code === mcupr_default_lang() ) {
			continue;
		}
		$v = sanitize_text_field( $g( 'tr_' . $code ) );
		if ( '' === $v ) {
			delete_post_meta( $post_id, "_mcupr_{$code}_title" );
		} else {
			update_post_meta( $post_id, "_mcupr_{$code}_title", $v );
		}
	}
}
add_action( 'save_post_mcupr_unit', 'mcupr_unit_save' );

/** Admin list: level + person columns, in chart order. */
add_filter(
	'manage_mcupr_unit_posts_columns',
	static function ( $cols ) {
		return array(
			'cb'            => isset( $cols['cb'] ) ? $cols['cb'] : '',
			'title'         => __( 'Unit', 'mcupr' ),
			'mcupr_level'   => __( 'Level', 'mcupr' ),
			'mcupr_person'  => __( 'Person / position', 'mcupr' ),
		);
	}
);
add_action(
	'manage_mcupr_unit_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_level' === $col ) {
			echo (int) count( get_post_ancestors( $id ) ) + 1;
		}
		if ( 'mcupr_person' === $col ) {
			echo esc_html( trim( get_post_meta( $id, '_mcupr_unit_person', true ) . ' ' . get_post_meta( $id, '_mcupr_unit_role', true ) ) );
		}
	},
	10,
	2
);
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() && $q->is_main_query() && 'mcupr_unit' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', 'menu_order title' );
			$q->set( 'order', 'ASC' );
		}
	}
);

/* ------------------------------------------------------------------ the chart */

/** Units as a nested array: each = array( post, children ). */
function mcupr_org_tree( $root = 0 ) {
	$units = get_posts(
		array(
			'post_type'        => 'mcupr_unit',
			'post_status'      => 'publish',
			'posts_per_page'   => 300,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	$by = array();
	foreach ( $units as $u ) {
		$by[ (int) $u->post_parent ][] = $u;
	}
	$build = static function ( $pid, $seen ) use ( &$build, $by ) {
		$out = array();
		foreach ( isset( $by[ $pid ] ) ? $by[ $pid ] : array() as $u ) {
			if ( in_array( $u->ID, $seen, true ) ) {
				continue; // never loop
			}
			$out[] = array( 'post' => $u, 'kids' => $build( $u->ID, array_merge( $seen, array( $u->ID ) ) ) );
		}
		return $out;
	};
	return $build( (int) $root, array() );
}

function mcupr_org_node( $n, $depth, $max ) {
	$u      = $n['post'];
	$person = (string) get_post_meta( $u->ID, '_mcupr_unit_person', true );
	$role   = (string) get_post_meta( $u->ID, '_mcupr_unit_role', true );
	$url    = (string) get_post_meta( $u->ID, '_mcupr_unit_url', true );
	$name   = get_the_title( $u );
	echo '<li class="org-li org-d' . (int) min( $depth, 4 ) . '"><div class="org-node">';
	if ( has_post_thumbnail( $u ) ) {
		echo get_the_post_thumbnail( $u, 'thumbnail', array( 'alt' => '', 'class' => 'org-ph', 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<span class="org-t">';
	echo '<b class="org-name">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</b>';
	if ( '' !== $person ) {
		echo '<span class="org-person">' . esc_html( $person ) . '</span>';
	}
	if ( '' !== $role ) {
		echo '<span class="org-role">' . esc_html( $role ) . '</span>';
	}
	echo '</span></div>';
	if ( $n['kids'] && $depth < $max ) {
		// the first two levels below the top box are drawn as rows joined by lines; deeper levels as indented lists
		echo '<ul' . ( $depth <= 2 ? ' class="org-row"' : '' ) . '>';
		foreach ( $n['kids'] as $k ) {
			mcupr_org_node( $k, $depth + 1, $max );
		}
		echo '</ul>';
	}
	echo '</li>';
}

function mcupr_orgchart_html( $root = 0, $max = 5 ) {
	$tree = mcupr_org_tree( $root );
	if ( ! $tree ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'no_units' ) ) . '</p>';
	}
	ob_start();
	echo '<div class="org-wrap" role="group" aria-label="' . esc_attr( mcupr_t( 'org_chart' ) ) . '"><ul class="org">';
	foreach ( $tree as $n ) {
		mcupr_org_node( $n, 1, max( 1, (int) $max ) );
	}
	echo '</ul></div>';
	return ob_get_clean();
}

add_shortcode(
	'mcupr_orgchart',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'root' => 0, 'depth' => 5 ), $atts, 'mcupr_orgchart' );
		return mcupr_orgchart_html( absint( $a['root'] ), absint( $a['depth'] ) );
	}
);
