<?php
/**
 * Frequently asked questions (คำถามที่พบบ่อย): post type "mcupr_faq" (question = title, answer = editor) with
 * categories, shown as an accordion with [mcupr_faq category="slug" search="1" open="first|all|none"].
 * Works without JavaScript (<details>), has a live search, and prints FAQPage structured data.
 * Question and answer can be translated like pages (EN / 中文 / 日本語 fields).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_faq_register() {
	register_post_type(
		'mcupr_faq',
		array(
			'labels'       => array(
				'name'          => __( 'FAQ', 'mcupr' ),
				'singular_name' => __( 'Question', 'mcupr' ),
				'menu_name'     => __( 'FAQ', 'mcupr' ),
				'all_items'     => __( 'All questions', 'mcupr' ),
				'add_new_item'  => __( 'Add a question', 'mcupr' ),
				'edit_item'     => __( 'Edit question', 'mcupr' ),
				'not_found'     => __( 'No questions yet', 'mcupr' ),
				'attributes'    => __( 'Order in the list', 'mcupr' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'menu_icon'    => 'dashicons-editor-help',
			'menu_position' => 12,
			'supports'     => array( 'title', 'editor', 'page-attributes' ),
		)
	);
	register_taxonomy(
		'mcupr_faq_cat',
		'mcupr_faq',
		array(
			'labels'            => array(
				'name'         => __( 'FAQ categories', 'mcupr' ),
				'singular_name' => __( 'FAQ category', 'mcupr' ),
				'add_new_item' => __( 'Add a category (e.g. Admissions, Fees, Dormitory)', 'mcupr' ),
				'menu_name'    => __( 'Categories', 'mcupr' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'mcupr_faq_register' );

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_faq';
		return $types;
	}
);

/** The answer as HTML in the visitor's language. */
function mcupr_faq_answer( $faq ) {
	global $post;
	$prev = $post;
	$post = get_post( is_object( $faq ) ? $faq->ID : $faq ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$raw  = $post->post_content;
	if ( mcupr_lang() !== mcupr_default_lang() ) { // the translation filter only runs in the main loop, so pick the translated answer here
		$tr = mcupr_tr_meta( $post->ID, mcupr_lang(), 'content' );
		$raw = '' !== $tr ? $tr : $raw;
	}
	$html = apply_filters( 'the_content', $raw );
	$post = $prev; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	if ( $prev ) {
		setup_postdata( $prev );
	}
	return $html;
}

/** [mcupr_faq category="slug,slug" search="1" open="first"] */
function mcupr_faq_html( $atts ) {
	$a    = shortcode_atts( array( 'category' => '', 'search' => 1, 'open' => 'none' ), $atts, 'mcupr_faq' );
	$args = array( 'post_type' => 'mcupr_faq', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true );
	$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['category'] ) ) );
	if ( $slugs ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_faq_cat', 'field' => 'slug', 'terms' => $slugs ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	$items = get_posts( $args );
	if ( ! $items ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'faq_none' ) ) . '</p>';
	}
	// group by the first category of each question
	$groups = array();
	foreach ( $items as $p ) {
		$t   = get_the_terms( $p->ID, 'mcupr_faq_cat' );
		$key = $t && ! is_wp_error( $t ) ? $t[0]->term_id : 0;
		$groups[ $key ]['name']    = $t && ! is_wp_error( $t ) ? $t[0]->name : '';
		$groups[ $key ]['items'][] = $p;
	}
	$open = in_array( $a['open'], array( 'first', 'all' ), true ) ? $a['open'] : 'none';
	$ld   = array();
	$n    = 0;
	ob_start();
	echo '<section class="faq" data-faq>';
	if ( $a['search'] && count( $items ) > 4 ) {
		echo '<label class="faq-find">' . mcupr_icon( 'search', 18 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'faq_search' ) ) . '</span><input type="search" placeholder="' . esc_attr( mcupr_t( 'faq_search' ) ) . '" data-faq-find autocomplete="off"></label><p class="faq-none text-muted" hidden>' . esc_html( mcupr_t( 'faq_nomatch' ) ) . '</p>';
	}
	foreach ( $groups as $g ) {
		echo '<div class="faq-group">';
		if ( '' !== $g['name'] && ( count( $groups ) > 1 || ! $slugs ) ) {
			echo '<h2 class="faq-h">' . esc_html( $g['name'] ) . '</h2>';
		}
		foreach ( $g['items'] as $p ) {
			$q   = get_the_title( $p );
			$ans = mcupr_faq_answer( $p );
			echo '<details class="faq-item"' . ( 'all' === $open || ( 'first' === $open && 0 === $n ) ? ' open' : '' ) . '><summary>' . esc_html( $q ) . '</summary><div class="faq-a">' . $ans . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $n < 50 ) {
				$ld[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $ans ) ) );
			}
			$n++;
		}
		echo '</div>';
	}
	echo '</section>';
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}
add_shortcode( 'mcupr_faq', 'mcupr_faq_html' );
