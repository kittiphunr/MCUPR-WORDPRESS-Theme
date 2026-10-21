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

/**
 * Does this visitor see fewer posts than the database holds?  Office content kept off the main site and
 * posts limited to an audience are left out of every list, so the stored counts (term counts, the total of
 * published posts) would promise more than the list shows.
 */
function mcupr_browser_filtered() {
	return ( function_exists( 'mcupr_office_hidden_terms' ) && mcupr_office_hidden_terms() ) || ( function_exists( 'mcupr_aud_hidden_ids' ) && mcupr_aud_hidden_ids() );
}

/** Posts the visitor can see in a list, counted by the same query the list uses (0 = all posts). */
function mcupr_browser_count( $args = array() ) {
	$q = new WP_Query( array_merge( array( 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'ignore_sticky_posts' => true ), $args ) );
	return (int) $q->found_posts;
}

/** All posts the visitor can see (the "All" entry). */
function mcupr_browser_all_total() {
	return mcupr_browser_filtered() ? mcupr_browser_count() : (int) wp_count_posts()->publish;
}

/** Posts of a tag or category the visitor can see (the stored count when nothing is left out). */
function mcupr_browser_term_count( $term ) {
	static $memo = array();
	$key = $term->taxonomy . ':' . $term->term_id . ':' . get_current_user_id() . ':' . count( (array) mcupr_office_hidden_terms() );
	if ( ! isset( $memo[ $key ] ) ) {
		$memo[ $key ] = mcupr_browser_filtered()
			? mcupr_browser_count( 'post_tag' === $term->taxonomy ? array( 'tag_id' => $term->term_id ) : array( 'cat' => $term->term_id ) )
			: (int) $term->count;
	}
	return $memo[ $key ];
}

/** Posts in a parent category including its children (what its archive page lists). */
function mcupr_browser_total( $term ) {
	static $memo = array();
	$key = $term->term_id . ':' . get_current_user_id() . ':' . count( (array) mcupr_office_hidden_terms() );
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	if ( ! get_term_children( $term->term_id, 'category' ) ) {
		return $memo[ $key ] = mcupr_browser_term_count( $term );
	}
	return $memo[ $key ] = mcupr_browser_count( array( 'cat' => $term->term_id ) );
}

/** Non-empty categories grouped by parent id: array( parent_id => WP_Term[] ). */
function mcupr_browser_index() {
	static $idx = null;
	if ( null === $idx ) {
		$idx = array();
		foreach ( get_categories( array( 'hide_empty' => true, 'orderby' => 'name' ) ) as $c ) {
			$idx[ (int) $c->parent ][] = $c;
		}
	}
	return $idx;
}

/** Direct sub-categories of a category (0 = the top level). */
function mcupr_browser_children( $parent_id ) {
	$idx = mcupr_browser_index();
	return isset( $idx[ (int) $parent_id ] ) ? $idx[ (int) $parent_id ] : array();
}

/** The category description written in the editor, as plain text (optionally cut to $max characters). */
function mcupr_browser_desc( $term, $max = 0 ) {
	$d = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $term->description ) ) );
	if ( $max && function_exists( 'mb_strlen' ) && mb_strlen( $d ) > $max ) {
		$d = rtrim( mb_substr( $d, 0, $max - 1 ) ) . '…';
	}
	return $d;
}

/**
 * The category tree for the source list: every level, a disclosure button for the ones with children
 * (open when the current category is inside), counts that include the children.
 */
function mcupr_browser_tree( $parent_id, $cur_id, $open_ids, $level = 0 ) {
	$kids = mcupr_browser_children( $parent_id );
	if ( ! $kids ) {
		return '';
	}
	$h = '<ul class="fb-tree' . ( $level ? ' fb-lv fb-lv' . (int) $level : '" data-expand="' . esc_attr( mcupr_t( 'fb_expand' ) ) . '" data-collapse="' . esc_attr( mcupr_t( 'fb_collapse' ) ) ) . '">';
	foreach ( $kids as $t ) {
		$sub  = mcupr_browser_children( $t->term_id );
		$cur  = $cur_id === (int) $t->term_id;
		$open = in_array( (int) $t->term_id, $open_ids, true );
		$uid  = 'fbn-' . (int) $t->term_id;
		$h   .= '<li data-fb-item' . ( $sub ? ' data-fb-node' : '' ) . '><div class="fb-row' . ( $cur ? ' is-cur' : '' ) . '">';
		if ( $sub ) {
			$h .= '<button type="button" class="fb-tog" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $uid ) . '" aria-label="' . esc_attr( ( $open ? mcupr_t( 'fb_collapse' ) : mcupr_t( 'fb_expand' ) ) . ' ' . $t->name ) . '" data-name="' . esc_attr( $t->name ) . '" data-fb-tog></button>';
		} else {
			$h .= '<span class="fb-tog fb-tog-none" aria-hidden="true"></span>';
		}
		$h .= '<a href="' . esc_url( get_category_link( $t ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . '>' . mcupr_icon( 'folder', 16 ) . '<span class="fb-l">' . esc_html( $t->name ) . '</span><span class="fb-n">' . (int) mcupr_browser_total( $t ) . '</span></a></div>';
		if ( $sub ) {
			$h .= '<div id="' . esc_attr( $uid ) . '" class="fb-subwrap"' . ( $open ? '' : ' hidden' ) . '>' . mcupr_browser_tree( $t->term_id, $cur_id, $open_ids, $level + 1 ) . '</div>';
		}
		$h .= '</li>';
	}
	return $h . '</ul>';
}

/** Link row inside the source list. */
function mcupr_browser_item( $url, $label, $count, $current, $icon = '' ) {
	return '<li data-fb-item><a href="' . esc_url( $url ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
		. ( $icon ? mcupr_icon( $icon, 16 ) : '' ) . '<span class="fb-l">' . esc_html( $label ) . '</span>'
		. ( null !== $count ? '<span class="fb-n">' . (int) $count . '</span>' : '' ) . '</a></li>';
}

/**
 * The folders of the current place, as in Finder / Files: the top-level categories on the main news page,
 * the sub-categories inside a category.  One markup, two looks: big folder icons (cards / gallery) or
 * rows with the description and the next level indented (list).
 */
function mcupr_browser_folders() {
	$parent = is_category() ? get_queried_object_id() : ( is_home() ? 0 : -1 );
	if ( $parent < 0 ) {
		return '';
	}
	$top = mcupr_browser_children( $parent );
	if ( ! $top ) {
		return '';
	}
	$h = '<nav class="fb-folders" aria-label="' . esc_attr( mcupr_t( 'fb_folders' ) ) . '"><div class="fb-fh" aria-hidden="true"><span>' . esc_html( mcupr_t( 'fb_name' ) ) . '</span><span>' . esc_html( mcupr_t( 'fb_items' ) ) . '</span><span>' . esc_html( mcupr_t( 'fb_about' ) ) . '</span></div><ul>';
	foreach ( $top as $t ) {
		$h .= mcupr_browser_folder( $t, 0 );
	}
	return $h . '</ul></nav>';
}

/** One folder (and, one level down, its own folders – shown in the list look only). */
function mcupr_browser_folder( $t, $depth ) {
	$kids = $depth < 1 ? mcupr_browser_children( $t->term_id ) : array();
	$desc = mcupr_browser_desc( $t, 140 );
	$all  = mcupr_browser_children( $t->term_id );
	$meta = sprintf( mcupr_t( 'fb_count' ), mcupr_browser_total( $t ) ) . ( $all ? ' · ' . sprintf( mcupr_t( 'fb_n_folders' ), count( $all ) ) : '' );
	$h    = '<li class="fb-fd fb-d' . (int) $depth . '"><a href="' . esc_url( get_category_link( $t ) ) . '" title="' . esc_attr( $t->name . ( $desc ? ' – ' . $desc : '' ) ) . '">'
		. '<span class="fb-fi">' . mcupr_icon( 'folder', 56 ) . '</span>'
		. '<span class="fb-fn">' . esc_html( $t->name ) . '</span>'
		. '<span class="fb-fm">' . esc_html( $meta ) . '</span>'
		. '<span class="fb-fc">' . (int) mcupr_browser_total( $t ) . '</span>'
		. '<span class="fb-fd-t">' . esc_html( $desc ) . '</span></a>';
	if ( $kids ) {
		$h .= '<ul class="fb-fk">';
		foreach ( $kids as $k ) {
			$h .= mcupr_browser_folder( $k, $depth + 1 );
		}
		$h .= '</ul>';
	}
	return $h . '</li>';
}

/** The Finder path bar: where you are, every step a link. */
function mcupr_browser_path() {
	$steps = array( array( mcupr_browser_home_url(), mcupr_t( 'all_cats' ) ) );
	$cur   = '';
	if ( is_category() ) {
		$q = get_queried_object();
		foreach ( array_reverse( get_ancestors( $q->term_id, 'category' ) ) as $aid ) {
			$steps[] = array( get_category_link( $aid ), get_cat_name( $aid ) );
		}
		$cur = $q->name;
	} elseif ( is_tag() ) {
		$steps[] = array( '', mcupr_t( 'fb_tag_label' ) );
		$cur     = single_tag_title( '', false );
	} elseif ( ! is_home() ) {
		$cur = wp_strip_all_tags( get_the_archive_title() );
	}
	if ( '' !== $cur ) {
		$steps[] = array( '', $cur );
	} else {
		$steps[ count( $steps ) - 1 ][0] = '';
	}
	$h = '<nav class="fb-path" aria-label="' . esc_attr( mcupr_t( 'fb_path' ) ) . '"><ol>';
	foreach ( $steps as $i => $st ) {
		$last = $i === count( $steps ) - 1;
		$h   .= '<li' . ( $last ? ' aria-current="page"' : '' ) . '>' . mcupr_icon( 'folder', 14 ) . ( $st[0] && ! $last ? '<a href="' . esc_url( $st[0] ) . '">' . esc_html( $st[1] ) . '</a>' : '<span>' . esc_html( $st[1] ) . '</span>' ) . '</li>';
	}
	return $h . '</ol></nav>';
}

/** The source list (categories in zones, tags). */
function mcupr_browser_sidebar() {
	$cur_cat = is_category() ? get_queried_object() : null;
	$cur_tag = is_tag() ? get_queried_object_id() : 0;
	$custom  = '' !== trim( (string) get_theme_mod( 'mcupr_cat_zones', '' ) );
	$tree    = ! $custom && mcupr_browser_index();
	$zones   = $custom ? mcupr_browser_zones() : array();
	$tags    = get_tags( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	$cat_ids = $cur_cat ? array_merge( array( $cur_cat->term_id ), get_ancestors( $cur_cat->term_id, 'category' ) ) : array();
	$total   = mcupr_browser_all_total();
	ob_start();
	?>
	<aside class="fb-side" aria-label="<?php echo esc_attr( mcupr_t( 'fb_browse' ) ); ?>">
		<details class="fb-drawer" open>
			<summary><?php echo mcupr_icon( 'folder', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'fb_open' ); ?></span></summary>
			<div class="fb-body">
				<label class="fb-find"><span class="sr-only"><?php mcupr_e( 'fb_filter' ); ?></span><?php echo mcupr_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><input type="search" placeholder="<?php echo esc_attr( mcupr_t( 'fb_filter' ) ); ?>" data-fb-filter autocomplete="off"></label>
				<ul class="fb-list"><?php echo mcupr_browser_item( mcupr_browser_home_url(), mcupr_t( 'all_cats' ), $total, is_home(), 'news' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
				<?php if ( $tree ) : ?>
				<h2 class="fb-h"><?php mcupr_e( 'fb_cats' ); ?></h2>
				<noscript><style>.fb-subwrap[hidden]{display:block}</style></noscript>
				<?php echo mcupr_browser_tree( 0, $cur_cat ? (int) $cur_cat->term_id : 0, array_map( 'intval', $cat_ids ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php elseif ( $zones ) : ?>
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
								echo mcupr_browser_item( get_category_link( $t ), $t->name, mcupr_browser_term_count( $t ), $cur_cat && $cur_cat->term_id === $t->term_id ); // phpcs:ignore WordPress.Security.EscapeOutput
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
						<?php echo mcupr_browser_item( get_tag_link( $t ), $t->name, mcupr_browser_term_count( $t ), (int) $t->term_id === $cur_tag, 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $tags ) > 12 ) : ?>
					<details class="fb-zone fb-more" data-fb-zone<?php echo $cur_tag && ! in_array( $cur_tag, wp_list_pluck( array_slice( $tags, 0, 12 ), 'term_id' ), true ) ? ' open' : ''; ?>>
						<summary><span class="fb-z"><?php mcupr_e( 'fb_all_tags' ); ?></span><span class="fb-n"><?php echo (int) count( $tags ); ?></span></summary>
						<ul class="fb-list fb-tags">
							<?php foreach ( array_slice( $tags, 12 ) as $t ) : ?>
								<?php echo mcupr_browser_item( get_tag_link( $t ), $t->name, mcupr_browser_term_count( $t ), (int) $t->term_id === $cur_tag, 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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

/** Toolbar: up one level, where you are + how many folders / items, view switcher. */
function mcupr_browser_toolbar() {
	global $wp_query;
	$views = array( 'grid' => 'grid', 'list' => 'rows', 'gallery' => 'image' );
	$up    = '';
	$title = mcupr_t( 'all_cats' );
	$folds = 0;
	if ( is_category() ) {
		$q     = get_queried_object();
		$title = $q->name;
		$up    = $q->parent ? get_category_link( $q->parent ) : mcupr_browser_home_url();
		$folds = count( mcupr_browser_children( $q->term_id ) );
	} elseif ( is_tag() ) {
		$title = single_tag_title( '', false );
		$up    = mcupr_browser_home_url();
	} elseif ( is_home() ) {
		$folds = count( mcupr_browser_children( 0 ) );
	} else {
		$title = wp_strip_all_tags( get_the_archive_title() );
		$up    = mcupr_browser_home_url();
	}
	$count = sprintf( mcupr_t( 'fb_count' ), (int) $wp_query->found_posts );
	ob_start();
	?>
	<div class="fb-bar">
		<?php if ( $up ) : ?>
			<a class="fb-up" href="<?php echo esc_url( $up ); ?>" aria-label="<?php echo esc_attr( mcupr_t( 'fb_up' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'fb_up' ) ); ?>"><?php echo mcupr_feather( 'arrow-left', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
		<?php else : ?>
			<span class="fb-up is-off" aria-hidden="true"><?php echo mcupr_feather( 'arrow-left', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php endif; ?>
		<div class="fb-ttl">
			<p class="fb-name"><?php echo esc_html( $title ); ?></p>
			<p class="fb-count" role="status"><?php echo esc_html( ( $folds ? sprintf( mcupr_t( 'fb_n_folders' ), $folds ) . ' · ' : '' ) . $count ); ?></p>
		</div>
		<div class="fb-views" role="radiogroup" aria-label="<?php echo esc_attr( mcupr_t( 'fb_view' ) ); ?>">
			<?php foreach ( $views as $v => $icon ) : ?>
				<button type="button" role="radio" aria-checked="<?php echo 'grid' === $v ? 'true' : 'false'; ?>" data-fb-view="<?php echo esc_attr( $v ); ?>" title="<?php echo esc_attr( mcupr_t( 'fb_' . $v ) ); ?>"><?php echo mcupr_icon( $icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'fb_' . $v ); ?></span></button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
