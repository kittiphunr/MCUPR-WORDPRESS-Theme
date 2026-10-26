<?php
/**
 * Notices and published documents (ประกาศและเอกสารเผยแพร่) – the register a government website keeps for procurement
 * announcements, price enquiries, results, contracts, recruitment, regulations…: a dated list of notices, grouped by type,
 * each with its files and a download counter, searchable, with the newest on the home page.
 *
 *   • post type "mcupr_notice" (archive /notices/), categories "mcupr_notice_cat" (/notice-category/…)
 *   • the date shown is the post's publish date (the owner can change it); the files are the same "Attached files"
 *     box as news (download counter, limit, optional PDF reader)
 *   • /notices/ and a category: a list of the types on the side, a search box (?nq=), a table – number, details with the
 *     file links, date, downloads – that becomes stacked cards on a phone
 *   • [mcupr_notices cat="slug" count="10"] puts the newest of a type on any page
 *   • home page: the newest notices (Customizer: MCUPR: Home page sections)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_notices_register() {
	register_post_type(
		'mcupr_notice',
		array(
			'labels'        => array(
				'name'          => __( 'Notices and documents', 'mcupr' ),
				'singular_name' => __( 'Notice', 'mcupr' ),
				'add_new_item'  => __( 'Add a notice', 'mcupr' ),
				'edit_item'     => __( 'Edit notice', 'mcupr' ),
				'all_items'     => __( 'All notices', 'mcupr' ),
				'menu_name'     => __( 'Notices and documents', 'mcupr' ),
				'search_items'  => __( 'Search notices', 'mcupr' ),
				'not_found'     => __( 'No notices yet', 'mcupr' ),
			),
			'public'        => true,
			'has_archive'   => 'notices',
			'rewrite'       => array( 'slug' => 'notices', 'with_front' => false ),
			'menu_icon'     => 'dashicons-megaphone',
			'menu_position' => 6,
			'supports'      => array( 'title', 'editor', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);
	register_taxonomy(
		'mcupr_notice_cat',
		'mcupr_notice',
		array(
			'labels'            => array(
				'name'          => __( 'Notice types', 'mcupr' ),
				'singular_name' => __( 'Notice type', 'mcupr' ),
				'add_new_item'  => __( 'Add a notice type', 'mcupr' ),
				'menu_name'     => __( 'Notice types', 'mcupr' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'notice-category', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'mcupr_notices_register' );

/** Notices are translatable like pages and news, and carry the attached files box (counter, limit, PDF reader). */
add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_notice';
		return $types;
	}
);
add_filter(
	'mcupr_tool_types',
	static function ( $types ) {
		$types[] = 'mcupr_notice';
		return $types;
	}
);

/* ================================================================== helpers */

function mcupr_notices_per_page() {
	return max( 5, min( 100, (int) apply_filters( 'mcupr_notices_per_page', 20 ) ) );
}

/** Downloads of all the files of a notice. */
function mcupr_notice_downloads( $post_id ) {
	$n = 0;
	foreach ( mcupr_files( $post_id ) as $f ) {
		$n += (int) $f['count'];
	}
	return $n;
}

/** A notice published in the last days gets the "new" mark. */
function mcupr_notice_is_new( $post ) {
	$days = max( 0, (int) apply_filters( 'mcupr_notice_new_days', 7 ) );
	return $days > 0 && ( time() - (int) get_post_timestamp( $post ) ) < $days * DAY_IN_SECONDS;
}

/** The files of a notice as download links (the first few; the rest are on its own page). */
function mcupr_notice_file_links( $post_id, $limit = 3 ) {
	$files = mcupr_files( $post_id );
	if ( ! $files ) {
		return '';
	}
	$o = '<ul class="nt-files">';
	foreach ( array_slice( $files, 0, $limit, true ) as $i => $f ) {
		$name = '' !== trim( $f['title'] ) ? $f['title'] : get_the_title( $f['id'] );
		$full = $f['max'] > 0 && $f['count'] >= $f['max'];
		$kind = mcupr_file_kind( $f['id'] );
		if ( $full ) {
			$o .= '<li><span class="nt-file nt-file-full">' . mcupr_icon( 'file', 16 ) . esc_html( $name ) . ' <small>(' . esc_html( mcupr_t( 'file_full' ) ) . ')</small></span></li>';
		} else {
			$o .= '<li><a class="nt-file" href="' . esc_url( mcupr_download_url( $post_id, $i ) ) . '">' . mcupr_icon( 'download', 16 ) . '<span>' . esc_html( $name ) . '</span> <small>' . esc_html( $kind[1] ) . '</small><span class="sr-only"> – ' . esc_html( mcupr_t( 'download' ) ) . '</span></a></li>';
		}
	}
	if ( count( $files ) > $limit ) {
		$o .= '<li><a class="nt-more" href="' . esc_url( get_permalink( $post_id ) ) . '">' . esc_html( sprintf( mcupr_t( 'nt_more_files' ), number_format_i18n( count( $files ) - $limit ) ) ) . '</a></li>';
	}
	return $o . '</ul>';
}

/** One row of the table. $no = the running number. */
function mcupr_notice_row( $post, $no ) {
	$id  = $post->ID;
	$new = mcupr_notice_is_new( $post ) ? ' <span class="nt-new">' . esc_html( mcupr_t( 'nt_new' ) ) . '</span>' : '';
	$cat = get_the_terms( $id, 'mcupr_notice_cat' );
	$tag = $cat && ! is_wp_error( $cat ) ? ' <a class="nt-cat" href="' . esc_url( get_term_link( $cat[0] ) ) . '">' . esc_html( $cat[0]->name ) . '</a>' : '';
	return '<tr><td class="nt-c-no" data-label="' . esc_attr( mcupr_t( 'nt_col_no' ) ) . '">' . (int) $no . '</td>'
		. '<td class="nt-c-main"><a class="nt-t" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>' . $new . $tag . mcupr_notice_file_links( $id ) . '</td>'
		. '<td class="nt-c-date" data-label="' . esc_attr( mcupr_t( 'nt_col_date' ) ) . '"><time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( mcupr_date_text( (int) get_post_timestamp( $post ), 'short' ) ) . '</time></td>'
		. '<td class="nt-c-dl" data-label="' . esc_attr( mcupr_t( 'nt_col_dl' ) ) . '">' . esc_html( number_format_i18n( mcupr_notice_downloads( $id ) ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** The table of a list of notices ($posts), numbered from $first. */
function mcupr_notice_table( $posts, $first = 1, $caption = '' ) {
	$o  = '<div class="nt-tablewrap"><table class="nt-table"><caption class="sr-only">' . esc_html( '' !== $caption ? $caption : mcupr_t( 'nt_title' ) ) . '</caption><thead><tr>';
	$o .= '<th scope="col" class="nt-c-no">' . esc_html( mcupr_t( 'nt_col_no' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'nt_col_detail' ) ) . '</th><th scope="col" class="nt-c-date">' . esc_html( mcupr_t( 'nt_col_date' ) ) . '</th><th scope="col" class="nt-c-dl">' . esc_html( mcupr_t( 'nt_col_dl' ) ) . '</th>';
	$o .= '</tr></thead><tbody>';
	foreach ( array_values( (array) $posts ) as $i => $p ) {
		$o .= mcupr_notice_row( $p, $first + $i );
	}
	return $o . '</tbody></table></div>';
}

/** The notice types with their counts, parents first (children indented in the list). */
function mcupr_notice_terms() {
	$terms = get_terms( array( 'taxonomy' => 'mcupr_notice_cat', 'hide_empty' => true, 'orderby' => 'name' ) );
	return is_wp_error( $terms ) ? array() : $terms;
}

/** Total number of published notices. */
function mcupr_notice_total() {
	$c = wp_count_posts( 'mcupr_notice' );
	return isset( $c->publish ) ? (int) $c->publish : 0;
}

/* ================================================================== the list pages */

/** /notices/ and a notice type: newest first, 20 a page, and the search words (?nq=) in the title or the text. */
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'mcupr_notice' ) || $q->is_tax( 'mcupr_notice_cat' ) ) ) {
			return;
		}
		$q->set( 'posts_per_page', mcupr_notices_per_page() );
		$q->set( 'orderby', 'date' );
		$q->set( 'order', 'DESC' );
		$nq = mcupr_notice_search_words();
		if ( '' !== $nq ) {
			add_filter(
				'posts_search',
				static function ( $search, $query ) use ( $nq ) {
					global $wpdb;
					if ( ! $query->is_main_query() ) {
						return $search;
					}
					$like = '%' . $wpdb->esc_like( $nq ) . '%';
					return $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s) ", $like, $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				},
				10,
				2
			);
		}
	}
);

/** The words typed in the notice search box (cleaned, at most 80 letters). */
function mcupr_notice_search_words() {
	$nq = isset( $_GET['nq'] ) ? sanitize_text_field( wp_unslash( $_GET['nq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return mb_substr( trim( $nq ), 0, 80 );
}

/** [mcupr_notices cat="slug" count="10"] – the newest notices (of one type) as the same table, without the side list. */
add_shortcode(
	'mcupr_notices',
	static function ( $atts ) {
		$a    = shortcode_atts( array( 'cat' => '', 'count' => 10 ), $atts, 'mcupr_notices' );
		$args = array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 50, (int) $a['count'] ) ), 'no_found_rows' => true );
		$term = null;
		if ( '' !== $a['cat'] ) {
			$term = get_term_by( 'slug', sanitize_title( $a['cat'] ), 'mcupr_notice_cat' );
			if ( ! $term ) {
				return mcupr_empty_note( mcupr_t( 'nt_none' ), 'bell' );
			}
			$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_notice_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$posts = get_posts( $args );
		if ( ! $posts ) {
			return mcupr_empty_note( mcupr_t( 'nt_none' ), 'bell' );
		}
		$link = $term && ! is_wp_error( get_term_link( $term ) ) ? get_term_link( $term ) : get_post_type_archive_link( 'mcupr_notice' );
		return mcupr_notice_table( $posts, 1, $term ? $term->name : '' ) . '<p class="nt-all"><a class="ev-link" href="' . esc_url( $link ) . '">' . esc_html( mcupr_t( 'nt_view_all' ) ) . ' <span aria-hidden="true">→</span></a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/* ================================================================== the home page block */

/** The newest notices for the home page: array( posts, term-or-null ). */
function mcupr_notices_home() {
	$n    = max( 3, min( 12, (int) get_theme_mod( 'mcupr_home_notices_n', 6 ) ) );
	$cat  = (int) get_theme_mod( 'mcupr_home_notices_cat', 0 );
	$args = array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => $n, 'no_found_rows' => true );
	$term = $cat ? get_term( $cat, 'mcupr_notice_cat' ) : null;
	$term = $term && ! is_wp_error( $term ) ? $term : null;
	if ( $term ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_notice_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	return array( get_posts( $args ), $term );
}

/* ================================================================== admin */

add_filter(
	'manage_mcupr_notice_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_files'] = __( 'Files / downloads', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_notice_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'mcupr_files' === $col ) {
			echo esc_html( sprintf( /* translators: 1: files, 2: downloads */ __( '%1$d files · %2$d downloads', 'mcupr' ), count( mcupr_files( $post_id ) ), mcupr_notice_downloads( $post_id ) ) );
		}
	},
	10,
	2
);

/* Customizer: the home block. */
add_action(
	'customize_register',
	static function ( $wp_customize ) {
		if ( ! $wp_customize->get_section( 'mcupr_home_sections' ) ) {
			return;
		}
		$wp_customize->add_setting( 'mcupr_home_notices_n', array( 'default' => 6, 'sanitize_callback' => static function ( $v ) { return max( 3, min( 12, (int) $v ) ); } ) );
		$wp_customize->add_control( 'mcupr_home_notices_n', array( 'section' => 'mcupr_home_sections', 'label' => __( 'Notices on the home page: how many', 'mcupr' ), 'type' => 'number', 'input_attrs' => array( 'min' => 3, 'max' => 12 ) ) );
		$choices = array( 0 => __( 'All types', 'mcupr' ) );
		foreach ( get_terms( array( 'taxonomy' => 'mcupr_notice_cat', 'hide_empty' => false ) ) as $t ) {
			if ( $t instanceof WP_Term ) {
				$choices[ $t->term_id ] = $t->name;
			}
		}
		$wp_customize->add_setting( 'mcupr_home_notices_cat', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( 'mcupr_home_notices_cat', array( 'section' => 'mcupr_home_sections', 'label' => __( 'Notices on the home page: which type', 'mcupr' ), 'type' => 'select', 'choices' => $choices ) );
	},
	30
);
