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
	if ( '' === $text ) {
		// a page always has a description: the About text, else at least the organisation's name
		$text = trim( preg_replace( '/\s+/u', ' ', (string) mcupr_text_mod( 'mcupr_about' ) ) );
		$text = '' !== $text ? $text : mcupr_org_name();
	}
	return wp_html_excerpt( $text, 200, '…' );
}

/** Absolute URL of the page being shown (no language parameter). */
function mcupr_current_url() {
	if ( is_singular() ) {
		return (string) get_permalink();
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
	}
	return home_url( '/' );
}

function mcupr_seo_head() {
	if ( ! apply_filters( 'mcupr_seo', true ) ) {
		return;
	}
	$desc  = mcupr_seo_description();
	$title = wp_get_document_title();
	$url   = mcupr_current_url();
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
		echo '<meta ' . $attr . '="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- $attr is one of two fixed words
	}
}
add_action( 'wp_head', 'mcupr_seo_head', 2 );

/**
 * Structured data for search engines (schema.org, JSON-LD):
 *   • home page: the organisation (a university: name, logo, address, telephone, social pages) and the website with its search box;
 *   • every other page, post or item: the way back (breadcrumb: home › parent pages › this page).
 * Everything comes from what the site already holds; nothing is printed for a value that is empty.
 * Change the organisation type with: add_filter( 'mcupr_seo_org_type', fn() => 'Organization' );
 */
function mcupr_seo_graph() {
	$home  = home_url( '/' );
	$graph = array();
	if ( is_front_page() ) {
		$org = array(
			'@type' => (string) apply_filters( 'mcupr_seo_org_type', 'CollegeOrUniversity' ),
			'@id'   => $home . '#org',
			'name'  => mcupr_org_name(),
			'url'   => $home,
		);
		if ( has_custom_logo() ) {
			$org['logo'] = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		}
		$desc = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) mcupr_text_mod( 'mcupr_about' ) ) ) );
		if ( '' !== $desc ) {
			$org['description'] = wp_html_excerpt( $desc, 300, '…' );
		}
		$tel = trim( (string) get_theme_mod( 'mcupr_org_tel', '' ) );
		if ( '' !== $tel ) {
			$org['telephone'] = $tel;
		}
		$mail = sanitize_email( (string) get_theme_mod( 'mcupr_org_email', '' ) );
		if ( '' !== $mail ) {
			$org['email'] = $mail;
		}
		$addr = trim( preg_replace( '/\s+/u', ' ', (string) mcupr_text_mod( 'mcupr_address' ) ) );
		if ( '' !== $addr ) {
			$org['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => $addr, 'addressCountry' => 'TH' );
		}
		$same = array();
		foreach ( array_keys( mcupr_social_networks() ) as $key ) {
			$u = esc_url_raw( (string) get_theme_mod( 'mcupr_social_' . $key, '' ) );
			if ( '' !== $u ) {
				$same[] = $u;
			}
		}
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		$graph[] = $org;
		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#site',
			'url'             => $home,
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => mcupr_languages()[ mcupr_lang() ]['html'],
			'publisher'       => array( '@id' => $home . '#org' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	} elseif ( is_singular() ) {
		$id    = get_queried_object_id();
		$trail = array_reverse( array_map( 'intval', get_post_ancestors( $id ) ) );
		$trail[] = $id;
		$items = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => mcupr_org_name(), 'item' => $home ) );
		foreach ( $trail as $pid ) {
			$title = trim( wp_strip_all_tags( get_the_title( $pid ) ) );
			if ( '' === $title ) {
				continue;
			}
			$items[] = array( '@type' => 'ListItem', 'position' => count( $items ) + 1, 'name' => $title, 'item' => (string) get_permalink( $pid ) );
		}
		if ( count( $items ) > 1 ) {
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
		}
	}
	return $graph;
}

function mcupr_seo_jsonld() {
	if ( ! apply_filters( 'mcupr_seo', true ) ) {
		return;
	}
	$graph = mcupr_seo_graph();
	if ( $graph ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
add_action( 'wp_head', 'mcupr_seo_jsonld', 4 );
