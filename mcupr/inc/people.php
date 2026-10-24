<?php
/**
 * People directory (executives, lecturers, staff): post type "mcupr_person" with groups, shown as photo cards
 * with  [mcupr_people group="slug" columns="4"].  Name and position can be translated.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_people_register() {
	$o        = function_exists( 'mcupr_staff_opts' ) ? mcupr_staff_opts() : array( 'profiles' => 1 );
	$profiles = ! empty( $o['profiles'] );
	register_post_type(
		'mcupr_person',
		array(
			'labels'          => array(
				'name'          => __( 'Staff and executives', 'mcupr' ),
				'singular_name' => __( 'Person', 'mcupr' ),
				'add_new_item'  => __( 'Add a person', 'mcupr' ),
				'edit_item'     => __( 'Edit person', 'mcupr' ),
				'all_items'     => __( 'All people', 'mcupr' ),
				'menu_name'     => __( 'Staff and executives', 'mcupr' ),
				'not_found'     => __( 'No people yet', 'mcupr' ),
				'attributes'    => __( 'Order in the list', 'mcupr' ),
			),
			'public'              => true,
			'publicly_queryable'  => ! empty( $profiles ),
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => array( 'slug' => 'profile', 'with_front' => false ),
			'show_ui'             => true,
			'show_in_rest'        => $profiles, // with the profile pages switched off nothing of a person is public, REST included
			'menu_icon'           => 'dashicons-groups',
			'menu_position'       => 9,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'capability_type'     => 'page',
			'map_meta_cap'        => true, // without it the per-person checks ask for a primitive 'edit_page' that no role holds
		)
	);
	register_taxonomy(
		'mcupr_person_group',
		'mcupr_person',
		array(
			'labels'            => array(
				'name'          => __( 'People groups', 'mcupr' ),
				'singular_name' => __( 'People group', 'mcupr' ),
				'add_new_item'  => __( 'Add a group (e.g. Executives, Lecturers, Staff)', 'mcupr' ),
				'menu_name'     => __( 'People groups', 'mcupr' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => $profiles, // the taxonomy is not public: it follows the profile pages
		)
	);
}
add_action( 'init', 'mcupr_people_register' );
add_action( 'init', 'mcupr_staff_flush_once', 99 );

function mcupr_person_box_add() {
	add_meta_box( 'mcupr_person', __( 'Person details', 'mcupr' ), 'mcupr_person_box', 'mcupr_person', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'mcupr_person_box_add' );

function mcupr_person_box( $post ) {
	wp_nonce_field( 'mcupr_person_save', 'mcupr_person_nonce' );
	$f = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_person[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	echo '<p class="description">' . esc_html__( 'The title above is the person\'s name with the title (e.g. “Asst. Prof. Dr. Somchai Jaidee”). Choose the photo on the right (“Featured image”) and the group on the right. Use “Order in the list” to arrange people (smaller number first).', 'mcupr' ) . '</p>';
	$f( __( 'Position', 'mcupr' ), 'role', get_post_meta( $post->ID, '_mcupr_person_role', true ), 'text', __( 'e.g. Vice President for Academic Affairs', 'mcupr' ) );
	$f( __( 'Unit / department', 'mcupr' ), 'dept', get_post_meta( $post->ID, '_mcupr_person_dept', true ) );
	$f( __( 'Email (optional)', 'mcupr' ), 'email', get_post_meta( $post->ID, '_mcupr_person_email', true ), 'email' );
	$f( __( 'Telephone (optional)', 'mcupr' ), 'tel', get_post_meta( $post->ID, '_mcupr_person_tel', true ) );
	$f( __( 'Link to a profile page (optional)', 'mcupr' ), 'url', get_post_meta( $post->ID, '_mcupr_person_url', true ), 'url', 'https://' );
	echo '<fieldset style="border:1px solid #c3c4c7;padding:8px 12px;margin-top:8px"><legend><strong>' . esc_html__( 'Name and position in other languages (optional)', 'mcupr' ) . '</strong></legend>';
	foreach ( mcupr_languages() as $code => $l ) {
		if ( $code === mcupr_default_lang() ) {
			continue;
		}
		echo '<p><strong>' . esc_html( $l['label'] ) . '</strong><br><input type="text" class="widefat" style="margin-bottom:4px" placeholder="' . esc_attr__( 'Name', 'mcupr' ) . '" name="mcupr_person[tr_' . esc_attr( $code ) . ']" value="' . esc_attr( get_post_meta( $post->ID, "_mcupr_{$code}_title", true ) ) . '"><input type="text" class="widefat" placeholder="' . esc_attr__( 'Position', 'mcupr' ) . '" name="mcupr_person[role_' . esc_attr( $code ) . ']" value="' . esc_attr( get_post_meta( $post->ID, "_mcupr_{$code}_role", true ) ) . '"></p>';
	}
	echo '</fieldset>';
}

function mcupr_person_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_person_nonce', 'mcupr_person_save' ) ) {
		return;
	}
	$in = isset( $_POST['mcupr_person'] ) && is_array( $_POST['mcupr_person'] ) ? wp_unslash( $_POST['mcupr_person'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$g  = static function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
	};
	update_post_meta( $post_id, '_mcupr_person_role', sanitize_text_field( $g( 'role' ) ) );
	update_post_meta( $post_id, '_mcupr_person_dept', sanitize_text_field( $g( 'dept' ) ) );
	update_post_meta( $post_id, '_mcupr_person_email', sanitize_email( $g( 'email' ) ) );
	update_post_meta( $post_id, '_mcupr_person_tel', sanitize_text_field( $g( 'tel' ) ) );
	update_post_meta( $post_id, '_mcupr_person_url', esc_url_raw( $g( 'url' ) ) );
	foreach ( array_keys( mcupr_languages() ) as $code ) {
		if ( $code === mcupr_default_lang() ) {
			continue;
		}
		foreach ( array( 'title' => 'tr_' . $code, 'role' => 'role_' . $code ) as $meta => $key ) {
			$v = sanitize_text_field( $g( $key ) );
			if ( '' === $v ) {
				delete_post_meta( $post_id, "_mcupr_{$code}_{$meta}" );
			} else {
				update_post_meta( $post_id, "_mcupr_{$code}_{$meta}", $v );
			}
		}
	}
}
add_action( 'save_post_mcupr_person', 'mcupr_person_save' );

/** Position in the visitor's language (falls back to the default). */
function mcupr_person_role( $id ) {
	$lang = mcupr_lang();
	if ( $lang !== mcupr_default_lang() ) {
		$tr = (string) get_post_meta( $id, "_mcupr_{$lang}_role", true );
		if ( '' !== $tr ) {
			return $tr;
		}
	}
	return (string) get_post_meta( $id, '_mcupr_person_role', true );
}

function mcupr_person_card( $p ) {
	$id    = $p->ID;
	$name  = get_the_title( $p );
	$role  = mcupr_person_role( $id );
	$dept  = (string) get_post_meta( $id, '_mcupr_person_dept', true );
	$unit  = (int) get_post_meta( $id, '_mcupr_person_unit', true );
	if ( '' === $dept && $unit ) {
		$dept = get_the_title( $unit );
	}
	$mail  = mcupr_person_shows( $id, 'email' ) ? (string) get_post_meta( $id, '_mcupr_person_email', true ) : '';
	$tel   = mcupr_person_shows( $id, 'tel' ) ? (string) get_post_meta( $id, '_mcupr_person_tel', true ) : '';
	$url   = mcupr_person_url( $id );
	$o     = mcupr_staff_opts();
	echo '<article class="person-card">';
	if ( has_post_thumbnail( $p ) ) {
		echo get_the_post_thumbnail( $p, 'medium', array( 'alt' => '', 'class' => 'person-ph', 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<span class="person-ph person-in" aria-hidden="true">' . esc_html( mb_substr( preg_replace( '/^[^\p{L}\p{N}]+/u', '', trim( preg_replace( '/^(ศ\.|รศ\.|ผศ\.|ดร\.|นาย|นางสาว|นาง|พระ|Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)+\s*/u', '', preg_replace( '/^[^\p{L}\p{N}]+/u', '', $name ) ) ) ), 0, 1 ) ) . '</span>';
	}
	echo '<p class="person-n"><strong>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</strong></p>';
	if ( '' !== $role ) {
		echo '<p class="person-r">' . esc_html( $role ) . '</p>';
	}
	if ( '' !== $dept ) {
		echo '<p class="person-d">' . esc_html( $dept ) . '</p>';
	}
	if ( ! empty( $o['card_honors'] ) && mcupr_person_shows( $id, 'honors' ) ) {
		$hs = mcupr_person_honors( $id );
		if ( $hs ) {
			echo '<p class="person-h" aria-label="' . esc_attr( mcupr_t( 'st_honors' ) ) . '">';
			foreach ( array_slice( $hs, 0, 3 ) as $h ) {
				echo '<span class="person-hb" title="' . esc_attr( mcupr_honor_name( $h['item'] ) ) . '">' . mcupr_honor_medal( $h['item'], 28, 'medal-s' ) . '<span class="sr-only">' . esc_html( mcupr_honor_name( $h['item'] ) ) . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</p>';
		}
	}
	if ( $mail || $tel ) {
		echo '<p class="person-c">';
		if ( $mail ) {
			echo '<a href="mailto:' . esc_attr( $mail ) . '">' . mcupr_icon( 'mail', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_email' ) ) . ': </span>' . esc_html( $mail ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( $tel ) {
			echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . mcupr_icon( 'phone', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $tel ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</p>';
	}
	echo '</article>';
}

/** [mcupr_people group="slug" columns="4"] */
add_shortcode(
	'mcupr_people',
	static function ( $atts ) {
		$a    = shortcode_atts( array( 'group' => '', 'columns' => 4, 'count' => 100, 'unit' => 0, 'search' => 0 ), $atts, 'mcupr_people' );
		$args = array(
			'post_type'      => 'mcupr_person',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 200, (int) $a['count'] ) ),
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( '' !== $a['group'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_person_group', 'field' => 'slug', 'terms' => array_map( 'sanitize_title', explode( ',', $a['group'] ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( (int) $a['unit'] ) {
			$args['meta_query'] = array( array( 'key' => '_mcupr_person_unit', 'value' => (int) $a['unit'], 'type' => 'NUMERIC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$people = get_posts( $args );
		if ( ! $people ) {
			return '<p class="text-muted">' . esc_html( mcupr_t( 'no_people' ) ) . '</p>';
		}
		ob_start();
		if ( $a['search'] ) {
			echo '<label class="people-find"><span class="sr-only">' . esc_html( mcupr_t( 'st_search' ) ) . '</span>' . mcupr_icon( 'search', 18 ) . '<input type="search" placeholder="' . esc_attr( mcupr_t( 'st_search' ) ) . '" data-people-find autocomplete="off"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="people-grid people-cols-' . (int) max( 1, min( 5, (int) $a['columns'] ) ) . '"' . ( $a['search'] ? ' data-people-grid' : '' ) . '>';
		foreach ( $people as $p ) {
			mcupr_person_card( $p );
		}
		echo '</div>';
		return ob_get_clean();
	}
);
