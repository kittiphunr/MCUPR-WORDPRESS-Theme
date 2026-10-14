<?php
/**
 * Works & awards (portfolio) – what the university took to competitions and contests, kept apart from the news:
 * its own post type "mcupr_work" (archive /works/) and its own categories "mcupr_work_cat" (/work-category/…),
 * so a work never appears in a news category and a news category never lists works.
 *
 * Post meta: _mcupr_work_event, _mcupr_work_award, _mcupr_work_year (Gregorian, int), _mcupr_work_org,
 * _mcupr_work_team, _mcupr_work_url.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_works_register() {
	register_post_type(
		'mcupr_work',
		array(
			'labels'        => array(
				'name'               => __( 'Works & awards', 'mcupr' ),
				'singular_name'      => __( 'Work', 'mcupr' ),
				'add_new_item'       => __( 'Add a new work', 'mcupr' ),
				'edit_item'          => __( 'Edit work', 'mcupr' ),
				'all_items'          => __( 'All works', 'mcupr' ),
				'menu_name'          => __( 'Works & awards', 'mcupr' ),
				'search_items'       => __( 'Search works', 'mcupr' ),
				'not_found'          => __( 'No works yet', 'mcupr' ),
			),
			'public'        => true,
			'has_archive'   => 'works',
			'rewrite'       => array( 'slug' => 'works', 'with_front' => false ),
			'menu_icon'     => 'dashicons-awards',
			'menu_position' => 7,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);
	register_taxonomy(
		'mcupr_work_cat',
		'mcupr_work',
		array(
			'labels'            => array(
				'name'          => __( 'Work categories', 'mcupr' ),
				'singular_name' => __( 'Work category', 'mcupr' ),
				'add_new_item'  => __( 'Add a work category', 'mcupr' ),
				'menu_name'     => __( 'Work categories', 'mcupr' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'work-category', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'mcupr_works_register' );

/** Works are translatable like pages and news. */
add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_work';
		return $types;
	}
);

function mcupr_work_info( $id = 0 ) {
	$id = $id ? $id : get_the_ID();
	return array(
		'event' => (string) get_post_meta( $id, '_mcupr_work_event', true ),
		'award' => (string) get_post_meta( $id, '_mcupr_work_award', true ),
		'year'  => (int) get_post_meta( $id, '_mcupr_work_year', true ),
		'org'   => (string) get_post_meta( $id, '_mcupr_work_org', true ),
		'team'  => (string) get_post_meta( $id, '_mcupr_work_team', true ),
		'url'   => (string) get_post_meta( $id, '_mcupr_work_url', true ),
	);
}

/** Year as shown: พ.ศ. in Thai. */
function mcupr_work_year_text( $year ) {
	return $year ? (string) ( 'th' === mcupr_ui_lang() ? $year + 543 : $year ) : '';
}

/* ------------------------------------------------------------------ admin box */

function mcupr_work_box_add() {
	add_meta_box( 'mcupr_work', __( 'Work details', 'mcupr' ), 'mcupr_work_box', 'mcupr_work', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'mcupr_work_box_add' );

function mcupr_work_box( $post ) {
	wp_nonce_field( 'mcupr_work_save', 'mcupr_work_nonce' );
	$i   = mcupr_work_info( $post->ID );
	$row = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_work[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	$row( __( 'Competition / contest / event', 'mcupr' ), 'event', $i['event'], 'text', __( 'e.g. National Student Innovation Contest 2026', 'mcupr' ) );
	$row( __( 'Award or result', 'mcupr' ), 'award', $i['award'], 'text', __( 'e.g. Gold medal / 1st runner-up / Excellence award', 'mcupr' ) );
	$row( __( 'Year (Gregorian, e.g. 2026 – shown as พ.ศ. in Thai)', 'mcupr' ), 'year', $i['year'] ? $i['year'] : '', 'number', (string) wp_date( 'Y' ) );
	$row( __( 'Organiser', 'mcupr' ), 'org', $i['org'] );
	echo '<p><label><strong>' . esc_html__( 'Team / people involved (one per line)', 'mcupr' ) . '</strong><br><textarea class="widefat" rows="4" name="mcupr_work[team]">' . esc_textarea( $i['team'] ) . '</textarea></label></p>';
	$row( __( 'Reference link (optional)', 'mcupr' ), 'url', $i['url'], 'url', 'https://' );
	echo '<p class="description">' . esc_html__( 'Add photos and certificates in “Attached files” below and choose the cover image on the right. Pick a work category on the right: works have their own categories, separate from the news.', 'mcupr' ) . '</p>';
}

function mcupr_work_save( $post_id ) {
	if ( ! isset( $_POST['mcupr_work_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_work_nonce'] ) ), 'mcupr_work_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in   = isset( $_POST['mcupr_work'] ) && is_array( $_POST['mcupr_work'] ) ? wp_unslash( $_POST['mcupr_work'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$g    = static function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
	};
	$year = absint( $g( 'year' ) );
	update_post_meta( $post_id, '_mcupr_work_event', sanitize_text_field( $g( 'event' ) ) );
	update_post_meta( $post_id, '_mcupr_work_award', sanitize_text_field( $g( 'award' ) ) );
	update_post_meta( $post_id, '_mcupr_work_year', ( $year >= 1900 && $year <= 2200 ) ? $year : 0 );
	update_post_meta( $post_id, '_mcupr_work_org', sanitize_text_field( $g( 'org' ) ) );
	update_post_meta( $post_id, '_mcupr_work_team', sanitize_textarea_field( $g( 'team' ) ) );
	update_post_meta( $post_id, '_mcupr_work_url', esc_url_raw( $g( 'url' ) ) );
}
add_action( 'save_post_mcupr_work', 'mcupr_work_save' );

/* ------------------------------------------------------------------ queries / archive */

/** Distinct years that have works (newest first). */
function mcupr_work_years() {
	global $wpdb;
	$rows = $wpdb->get_col( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_mcupr_work_year' AND pm.meta_value > 0 AND p.post_type = 'mcupr_work' AND p.post_status = 'publish' ORDER BY pm.meta_value + 0 DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array_map( 'intval', (array) $rows );
}

function mcupr_work_year_filter() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$y = isset( $_GET['wy'] ) ? absint( $_GET['wy'] ) : 0;
	return ( $y >= 1900 && $y <= 2200 ) ? $y : 0;
}

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'mcupr_work' ) || $q->is_tax( 'mcupr_work_cat' ) ) ) {
			return;
		}
		$q->set( 'posts_per_page', 12 );
		$y = mcupr_work_year_filter();
		if ( $y ) {
			$q->set( 'meta_query', array( array( 'key' => '_mcupr_work_year', 'value' => $y, 'type' => 'NUMERIC' ) ) );
		}
	}
);

/** One work as a card. */
function mcupr_work_card( $post = null, $tag = 'h2' ) {
	$post = get_post( $post );
	$i    = mcupr_work_info( $post->ID );
	$tag  = in_array( $tag, array( 'h2', 'h3' ), true ) ? $tag : 'h2';
	$cats = get_the_terms( $post, 'mcupr_work_cat' );
	echo '<article class="work-card soft-card lift">';
	echo '<a class="work-img" href="' . esc_url( get_permalink( $post ) ) . '" tabindex="-1" aria-hidden="true">';
	if ( has_post_thumbnail( $post ) ) {
		echo get_the_post_thumbnail( $post, 'mcupr-card', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'noimg:hidden' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<span class="work-ph">' . mcupr_icon( 'trophy', 44 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</a><div class="work-b">';
	if ( '' !== $i['award'] ) {
		echo '<p class="work-award">' . mcupr_icon( 'trophy', 16 ) . esc_html( $i['award'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<' . $tag . ' class="work-t"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$meta = array_filter( array( $i['event'], mcupr_work_year_text( $i['year'] ) ) );
	if ( $meta ) {
		echo '<p class="work-m">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
	}
	if ( $cats && ! is_wp_error( $cats ) ) {
		echo '<p class="work-c"><span>' . esc_html( $cats[0]->name ) . '</span></p>';
	}
	echo '</div></article>';
}

/** [mcupr_works count="6" category="slug" columns="3"] */
add_shortcode(
	'mcupr_works',
	static function ( $atts ) {
		$a   = shortcode_atts( array( 'count' => 6, 'category' => '', 'columns' => 3 ), $atts, 'mcupr_works' );
		$args = array(
			'post_type'           => 'mcupr_work',
			'posts_per_page'      => max( 1, min( 24, (int) $a['count'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( '' !== $a['category'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_work_cat', 'field' => 'slug', 'terms' => sanitize_title( $a['category'] ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$q    = new WP_Query( $args );
		$cols = max( 1, min( 4, (int) $a['columns'] ) );
		ob_start();
		if ( $q->have_posts() ) {
			echo '<div class="works-grid works-cols-' . (int) $cols . '">';
			while ( $q->have_posts() ) {
				$q->the_post();
				mcupr_work_card( null, 'h3' );
			}
			echo '</div>';
		} else {
			echo '<p class="text-muted">' . esc_html( mcupr_t( 'no_works' ) ) . '</p>';
		}
		wp_reset_postdata();
		return ob_get_clean();
	}
);
