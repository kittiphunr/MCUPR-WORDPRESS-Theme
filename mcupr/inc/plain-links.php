<?php
/**
 * Plain permalinks (…/web/?p=123). Some servers do not let a website use "pretty" addresses (…/works/, …/academics/programs/):
 * the server answers "Not Found" before WordPress is asked. When the permalink setting is "Plain" every link the theme
 * prints that points into this website with a pretty path (menus built from a demo, links in content or in the Customizer,
 * links typed as /works/) is turned into its plain form (?post_type=mcupr_work, ?page_id=12, ?mcupr_student=1 …).
 * Nothing changes when the permalinks are pretty.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Are the permalinks "Plain"? */
function mcupr_plain_mode() {
	return ! get_option( 'permalink_structure' );
}

/**
 * The plain form of a link into this website, or the link itself when it is not one (another site, a file, already plain,
 * the home page, or a path nothing on the website answers to).
 */
function mcupr_plain_url( $url ) {
	static $memo = array();
	$url = (string) $url;
	if ( isset( $memo[ $url ] ) ) {
		return $memo[ $url ];
	}
	$memo[ $url ] = $url;
	$p            = wp_parse_url( $url );
	$home         = wp_parse_url( home_url( '/' ) );
	if ( ! $p || empty( $p['path'] ) && empty( $p['host'] ) ) {
		return $url;
	}
	if ( ! empty( $p['host'] ) && ( empty( $home['host'] ) || strtolower( $p['host'] ) !== strtolower( $home['host'] ) ) ) {
		return $url;
	}
	$base = isset( $home['path'] ) ? rtrim( $home['path'], '/' ) : '';
	$path = isset( $p['path'] ) ? $p['path'] : '';
	if ( '' !== $base && 0 !== strpos( $path, $base . '/' ) && $path !== $base ) {
		return $url;
	}
	$rel = trim( substr( $path, strlen( $base ) ), '/' );
	if ( '' === $rel || 0 === strpos( $rel, 'index.php' ) || preg_match( '#^wp-(admin|content|includes|json|login|cron|sitemap)|\.[a-z0-9]{2,5}$#i', $rel ) ) {
		return $url;
	}
	$to = mcupr_plain_target( $rel );
	if ( ! $to ) {
		return $url;
	}
	// what the link carried on top (language, search…) stays
	if ( ! empty( $p['query'] ) ) {
		parse_str( $p['query'], $q );
		$to = add_query_arg( $q, $to );
	}
	if ( ! empty( $p['fragment'] ) ) {
		$to .= '#' . $p['fragment'];
	}
	$memo[ $url ] = $to;
	return $to;
}

/** The plain address of what lives at a pretty path (relative to the site, no slashes at the ends), or ''. */
function mcupr_plain_target( $rel ) {
	$parts = explode( '/', $rel );
	$first = $parts[0];
	// the theme's own pages
	if ( 'student' === $rel && function_exists( 'mcupr_student_portal_url' ) ) {
		return mcupr_student_portal_url();
	}
	if ( 'request' === $rel && function_exists( 'mcupr_rq_track_url' ) ) {
		return mcupr_rq_track_url();
	}
	if ( 'office' === $first && isset( $parts[1] ) && post_type_exists( 'mcupr_office' ) && function_exists( 'mcupr_office_url' ) ) {
		$o = get_page_by_path( $parts[1], OBJECT, 'mcupr_office' );
		if ( $o ) {
			$sec = isset( $parts[2] ) && in_array( $parts[2], array( 'news', 'events', 'people', 'albums', 'videos', 'contact' ), true ) ? $parts[2] : '';
			$pg  = isset( $parts[3], $parts[4] ) && 'page' === $parts[3] ? (int) $parts[4] : 1;
			return mcupr_office_url( $o->ID, $sec, $pg );
		}
	}
	// a page ("academics/programs")
	$page = get_page_by_path( $rel );
	if ( $page ) {
		return get_permalink( $page );
	}
	// the archive of a post type ("works") and one item of it ("profile/name")
	foreach ( get_post_types( array( '_builtin' => false ), 'objects' ) as $type ) {
		$slug = is_array( $type->rewrite ) && ! empty( $type->rewrite['slug'] ) ? $type->rewrite['slug'] : $type->name;
		if ( $slug !== $first ) {
			continue;
		}
		if ( 1 === count( $parts ) && $type->has_archive ) {
			return get_post_type_archive_link( $type->name );
		}
		if ( 2 === count( $parts ) ) {
			$post = get_page_by_path( $parts[1], OBJECT, $type->name );
			if ( $post ) {
				return get_permalink( $post );
			}
		}
	}
	// a category / tag / the categories of the theme's types ("work-category/competitions")
	if ( isset( $parts[1] ) ) {
		$cat = get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category';
		$tag = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag';
		$map = array( $cat => 'category', $tag => 'post_tag' );
		foreach ( get_taxonomies( array( '_builtin' => false, 'public' => true ), 'objects' ) as $tx ) {
			$slug = is_array( $tx->rewrite ) && ! empty( $tx->rewrite['slug'] ) ? $tx->rewrite['slug'] : $tx->name;
			$map[ $slug ] = $tx->name;
		}
		if ( isset( $map[ $first ] ) ) {
			$term = get_term_by( 'slug', end( $parts ), $map[ $first ] );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? '' : $link;
			}
		}
	}
	// a page whose address was typed short ("calendar" for "academics/calendar"): what WordPress itself guesses for a pretty link
	$guess = get_posts( array( 'name' => end( $parts ), 'post_type' => 'any', 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => true, 'no_found_rows' => true ) );
	if ( $guess ) {
		return get_permalink( $guess[0] );
	}
	return '';
}

/** The page printed with its links in plain form: an output filter, only on the website itself and only in Plain mode. */
add_action(
	'template_redirect',
	static function () {
		if ( ! mcupr_plain_mode() || is_feed() || is_robots() || is_trackback() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) || is_embed() ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				if ( false === stripos( $html, '<a ' ) || ! preg_match( '/<(?:html|!doctype)/i', substr( $html, 0, 600 ) ) ) {
					return $html; // not a page of ours (a download, an image…)
				}
				return preg_replace_callback(
					'/(<(?:a|link|form)\b[^>]*?\s(?:href|action)=)(["\'])([^"\']+)\2/i',
					static function ( $m ) {
						$u = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
						$n = mcupr_plain_url( $u );
						return $n === $u ? $m[0] : $m[1] . $m[2] . esc_url( $n ) . $m[2];
					},
					$html
				);
			}
		);
	},
	0
);
