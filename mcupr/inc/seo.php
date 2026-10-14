<?php
/**
 * Basic built-in SEO: meta description and Open Graph / Twitter card.
 * (Canonical URL, title tag, robots (noindex on search), feeds and sitemap come from WordPress.)
 * Disable with: add_filter( 'mcupr_seo', '__return_false' );
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_seo_description() {
	if ( is_singular() ) {
		$text = get_the_excerpt( get_queried_object_id() );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} else {
		$text = '';
	}
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) $text ) ) ) );
	if ( '' === $text ) {
		$text = get_bloginfo( 'description' );
	}
	return wp_html_excerpt( $text, 200, '…' );
}

function mcupr_seo_head() {
	if ( ! apply_filters( 'mcupr_seo', true ) ) {
		return;
	}
	$desc  = mcupr_seo_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
	$img   = is_singular() && has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'mcupr-hero' ) : '';
	if ( ! $img && has_custom_logo() ) {
		$img = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}
	$tags = array(
		'og:type'        => is_singular( 'post' ) ? 'article' : 'website',
		'og:site_name'   => mcupr_org_name(),
		'og:title'       => $title,
		'og:url'         => $url,
		'og:locale'      => str_replace( '-', '_', mcupr_languages()[ mcupr_lang() ]['locale'] ),
		'twitter:card'   => $img ? 'summary_large_image' : 'summary',
	);
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		$tags['og:description'] = $desc;
	}
	if ( $img ) {
		$tags['og:image'] = $img;
	}
	foreach ( $tags as $k => $v ) {
		$attr = 0 === strpos( $k, 'og:' ) ? 'property' : 'name';
		echo '<meta ' . $attr . '="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'mcupr_seo_head', 2 );
