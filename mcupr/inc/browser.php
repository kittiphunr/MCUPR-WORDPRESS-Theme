<?php
/**
 * Finder-style browser for news listings (all news, category, tag, date archives).
 *
 * A source list on the left (categories grouped into collapsible "zones", tags in their own
 * section – never mixed), a toolbar with a view switcher (cards / list / gallery, remembered per
 * visitor) and, for a category that has sub-categories, a row of "folders".
 *
 * Zones come from the Customizer ("Zone name: slug, slug" per line) or, if empty, from the
 * category hierarchy: every parent with children is a zone; the other top-level categories
 * are collected under "Other categories".
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Posts-page URL (the "All" entry). */
function mcupr_browser_home_url() {
	$pfp = (int) get_option( 'page_for_posts' );
	return $pfp ? get_permalink( $pfp ) : home_url( '/' );
}

/**
 * Category zones: array of array( 'label' => string, 'terms' => WP_Term[], 'root' => WP_Term|null ).
 *
 * @return array
 */
function mcupr_browser_zones() {
	$all = get_categories( array( 'hide_empty' => true, 'orderby' => 'name' ) );
	if ( ! $all ) {
		return array();
	}
	$by_slug = array();
	foreach ( $all as $c ) {
		$by_slug[ $c->slug ] = $c;
	}
	$zones = array();
	$used  = array();
	$conf  = trim( (string) get_theme_mod( 'mcupr_cat_zones', '' ) );
	if ( '' !== $conf ) {
		foreach ( preg_split( '/\r\n|\r|\n/', $conf ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $label, $slugs ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$terms                 = array();
			foreach ( preg_split( '/\s*,\s*/', $slugs ) as $slug ) {
				if ( isset( $by_slug[ $slug ] ) && ! isset( $used[ $slug ] ) ) {
					$terms[]       = $by_slug[ $slug ];
					$used[ $slug ] = true;
				}
			}
			if ( '' !== $label && $terms ) {
				$zones[] = array( 'label' => $label, 'terms' => $terms, 'root' => null );
			}
		}
	} else {
		$children = array();
		foreach ( $all as $c ) {
			if ( $c->parent ) {
				$children[ $c->parent ][] = $c;
			}
		}
		foreach ( $all as $c ) {
			if ( ! $c->parent && ! empty( $children[ $c->term_id ] ) ) {
				$zones[]            = array( 'label' => $c->name, 'terms' => $children[ $c->term_id ], 'root' => $c );
				$used[ $c->slug ]   = true;
				foreach ( $children[ $c->term_id ] as $k ) {
					$used[ $k->slug ] = true;
				}
			}
		}
	}
	$rest = array();
	foreach ( $all as $c ) {
		if ( ! isset( $used[ $c->slug ] ) && ( '' !== $conf || ! $c->parent ) ) {
			$rest[] = $c;
		}
	}
	if ( $rest ) {
		$zones[] = array( 'label' => count( $zones ) ? mcupr_t( 'fb_other' ) : mcupr_t( 'fb_cats' ), 'terms' => $rest, 'root' => null );
	}
	return $zones;
}

/** Posts in a parent category including its children (what its archive page lists). */
function mcupr_browser_total( $term ) {
	if ( ! get_term_children( $term->term_id, 'category' ) ) {
		return (int) $term->count;
	}
	$q = new WP_Query( array( 'cat' => $term->term_id, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'ignore_sticky_posts' => true ) );
	return (int) $q->found_posts;
}

/** Link row inside the source list. */
function mcupr_browser_item( $url, $label, $count, $current, $icon = '' ) {
	return '<li data-fb-item><a href="' . esc_url( $url ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
		. ( $icon ? mcupr_icon( $icon, 16 ) : '' ) . '<span class="fb-l">' . esc_html( $label ) . '</span>'
		. ( null !== $count ? '<span class="fb-n">' . (int) $count . '</span>' : '' ) . '</a></li>';
}

/** The folders row: sub-categories of the current category, or the zones on the main news page. */
function mcupr_browser_folders() {
	$items = array();
	if ( is_category() ) {
		$kids = get_categories( array( 'parent' => get_queried_object_id(), 'hide_empty' => true ) );
		foreach ( $kids as $k ) {
			$items[] = array( get_category_link( $k ), $k->name, $k->count );
		}
	} elseif ( is_home() ) {
		foreach ( mcupr_browser_zones() as $z ) {
			if ( $z['root'] ) {
				$items[] = array( get_category_link( $z['root'] ), $z['root']->name, mcupr_browser_total( $z['root'] ) );
			}
		}
	}
	if ( ! $items ) {
		return '';
	}
	$h = '<nav class="fb-folders" aria-label="' . esc_attr( mcupr_t( is_category() ? 'fb_subcats' : 'fb_zones' ) ) . '"><ul>';
	foreach ( $items as $i ) {
		$h .= '<li><a href="' . esc_url( $i[0] ) . '" title="' . esc_attr( $i[1] ) . '">' . mcupr_icon( 'folder', 40 ) . '<span class="fb-fn">' . esc_html( $i[1] ) . '</span><span class="fb-n">' . (int) $i[2] . '</span></a></li>';
	}
	return $h . '</ul></nav>';
}

/** The source list (categories in zones, tags). */
function mcupr_browser_sidebar() {
	$cur_cat = is_category() ? get_queried_object() : null;
	$cur_tag = is_tag() ? get_queried_object_id() : 0;
	$zones   = mcupr_browser_zones();
	$tags    = get_tags( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	$cat_ids = $cur_cat ? array_merge( array( $cur_cat->term_id ), get_ancestors( $cur_cat->term_id, 'category' ) ) : array();
	$total   = (int) wp_count_posts()->publish;
	ob_start();
	?>
	<aside class="fb-side" aria-label="<?php echo esc_attr( mcupr_t( 'fb_browse' ) ); ?>">
		<details class="fb-drawer" open>
			<summary><?php echo mcupr_icon( 'folder', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'fb_open' ); ?></span></summary>
			<div class="fb-body">
				<label class="fb-find"><span class="sr-only"><?php mcupr_e( 'fb_filter' ); ?></span><?php echo mcupr_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><input type="search" placeholder="<?php echo esc_attr( mcupr_t( 'fb_filter' ) ); ?>" data-fb-filter autocomplete="off"></label>
				<ul class="fb-list"><?php echo mcupr_browser_item( mcupr_browser_home_url(), mcupr_t( 'all_cats' ), $total, is_home(), 'news' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
				<?php if ( $zones ) : ?>
				<h2 class="fb-h"><?php mcupr_e( 'fb_cats' ); ?></h2>
				<?php foreach ( $zones as $z ) : ?>
					<?php
					$open = false;
					foreach ( $z['terms'] as $t ) {
						if ( in_array( $t->term_id, $cat_ids, true ) ) {
							$open = true;
						}
					}
					if ( $z['root'] && in_array( $z['root']->term_id, $cat_ids, true ) ) {
						$open = true;
					}
					?>
					<details class="fb-zone" data-fb-zone<?php echo $open || count( $zones ) < 3 ? ' open' : ''; ?>>
						<summary><span class="fb-z"><?php echo mcupr_icon( 'folder', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $z['label'] ); ?></span><span class="fb-n"><?php echo (int) count( $z['terms'] ); ?></span></summary>
						<ul class="fb-list">
							<?php
							if ( $z['root'] ) {
								echo mcupr_browser_item( get_category_link( $z['root'] ), $z['root']->name, mcupr_browser_total( $z['root'] ), $cur_cat && $cur_cat->term_id === $z['root']->term_id ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							foreach ( $z['terms'] as $t ) {
								echo mcupr_browser_item( get_category_link( $t ), $t->name, $t->count, $cur_cat && $cur_cat->term_id === $t->term_id ); // phpcs:ignore WordPress.Security.EscapeOutput
							}
							?>
						</ul>
					</details>
				<?php endforeach; ?>
				<?php endif; ?>
				<?php if ( $tags ) : ?>
				<h2 class="fb-h fb-h-tags"><?php mcupr_e( 'fb_tags' ); ?></h2>
				<ul class="fb-list fb-tags">
					<?php foreach ( array_slice( $tags, 0, 12 ) as $t ) : ?>
						<?php echo mcupr_browser_item( get_tag_link( $t ), $t->name, $t->count, (int) $t->term_id === $cur_tag, 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $tags ) > 12 ) : ?>
					<details class="fb-zone fb-more" data-fb-zone<?php echo $cur_tag && ! in_array( $cur_tag, wp_list_pluck( array_slice( $tags, 0, 12 ), 'term_id' ), true ) ? ' open' : ''; ?>>
						<summary><span class="fb-z"><?php mcupr_e( 'fb_all_tags' ); ?></span><span class="fb-n"><?php echo (int) count( $tags ); ?></span></summary>
						<ul class="fb-list fb-tags">
							<?php foreach ( array_slice( $tags, 12 ) as $t ) : ?>
								<?php echo mcupr_browser_item( get_tag_link( $t ), $t->name, $t->count, (int) $t->term_id === $cur_tag, 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endif; ?>
				<?php endif; ?>
				<p class="fb-empty" hidden><?php mcupr_e( 'fb_none' ); ?></p>
			</div>
		</details>
	</aside>
	<?php
	return ob_get_clean();
}

/** Toolbar: item count + view switcher. */
function mcupr_browser_toolbar() {
	global $wp_query;
	$views = array( 'grid' => 'grid', 'list' => 'rows', 'gallery' => 'image' );
	ob_start();
	?>
	<div class="fb-bar">
		<p class="fb-count" role="status"><?php echo esc_html( sprintf( mcupr_t( 'fb_count' ), (int) $wp_query->found_posts ) ); ?></p>
		<div class="fb-views" role="radiogroup" aria-label="<?php echo esc_attr( mcupr_t( 'fb_view' ) ); ?>">
			<?php foreach ( $views as $v => $icon ) : ?>
				<button type="button" role="radio" aria-checked="<?php echo 'grid' === $v ? 'true' : 'false'; ?>" data-fb-view="<?php echo esc_attr( $v ); ?>" title="<?php echo esc_attr( mcupr_t( 'fb_' . $v ) ); ?>"><?php echo mcupr_icon( $icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'fb_' . $v ); ?></span></button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
