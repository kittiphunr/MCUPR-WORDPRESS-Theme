<?php
/**
 * Template helpers.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Menu for the current language, falling back to default language, then pages. */
function mcupr_primary_menu() {
	$loc = 'primary_' . mcupr_lang();
	if ( ! has_nav_menu( $loc ) ) {
		$loc = 'primary_' . mcupr_default_lang();
	}
	$args = array(
		'menu_class' => 'menu',
		'container'  => false,
		'depth'      => 3,
		'fallback_cb' => 'mcupr_menu_fallback',
	);
	if ( has_nav_menu( $loc ) ) {
		$args['theme_location'] = $loc;
	}
	wp_nav_menu( $args );
}

function mcupr_menu_fallback() {
	echo '<ul class="menu"><li><a href="' . esc_url( mcupr_home_url() ) . '">' . esc_html( mcupr_t( 'home' ) ) . '</a></li>';
	wp_list_pages( array( 'title_li' => '', 'depth' => 2 ) );
	echo '</ul>';
}

/** Breadcrumb (WCAG: multiple ways to locate pages). */
function mcupr_breadcrumb( $wrap = true ) {
	if ( is_front_page() ) {
		return;
	}
	$items = array( array( mcupr_t( 'home' ), mcupr_home_url() ) );
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( $cats ) {
			$items[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
		}
		$items[] = array( get_the_title(), '' );
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $anc ) {
			$items[] = array( get_the_title( $anc ), get_permalink( $anc ) );
		}
		$items[] = array( get_the_title(), '' );
	} elseif ( is_singular( 'mcupr_work' ) ) {
		$items[] = array( mcupr_t( 'works_title' ), get_post_type_archive_link( 'mcupr_work' ) );
		$items[] = array( get_the_title(), '' );
	} elseif ( is_post_type_archive( 'mcupr_work' ) ) {
		$items[] = array( mcupr_t( 'works_title' ), '' );
	} elseif ( is_tax( 'mcupr_work_cat' ) ) {
		$items[] = array( mcupr_t( 'works_title' ), get_post_type_archive_link( 'mcupr_work' ) );
		$items[] = array( single_term_title( '', false ), '' );
	} elseif ( is_singular( 'mcupr_event' ) ) {
		$items[] = array( mcupr_t( 'events_title' ), get_post_type_archive_link( 'mcupr_event' ) );
		$items[] = array( get_the_title(), '' );
	} elseif ( is_post_type_archive( 'mcupr_event' ) ) {
		$items[] = array( mcupr_t( 'events_title' ), '' );
	} elseif ( is_archive() ) {
		$items[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
	} elseif ( is_search() ) {
		$items[] = array( mcupr_t( 'search' ), '' );
	} elseif ( is_404() ) {
		$items[] = array( mcupr_t( 'not_found' ), '' );
	}
	echo '<nav class="' . ( $wrap ? 'wrap my-4 ' : '' ) . 'text-[.9375rem] text-muted" aria-label="' . esc_attr( mcupr_t( 'breadcrumb' ) ) . '"><ol class="m-0 flex list-none flex-wrap gap-x-2 gap-y-1 p-0">';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		$sep = $i ? '<span class="mr-2" aria-hidden="true">›</span>' : '';
		if ( $i === $last || '' === $item[1] ) {
			echo '<li>' . $sep . '<span aria-current="page">' . esc_html( $item[0] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<li>' . $sep . '<a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
	echo '</ol></nav>';
}

/**
 * The page links of a list as one tidy bar: a "Previous" and a "Next" button (greyed, not missing, on the first and the last page, so the
 * bar does not jump), the page numbers (the current one filled), and "Page 2 of 8" under it. On a phone only the current number stays.
 *
 * @param array $items   What paginate_links() gave with 'type' => 'array' (it has the prev / next links in it when they apply).
 * @param int   $current The page being shown.
 * @param int   $total   The number of pages.
 * @return string
 */
function mcupr_pager_html( $items, $current, $total ) {
	if ( $total < 2 || ! $items ) {
		return '';
	}
	$prev = '';
	$next = '';
	$nums = array();
	foreach ( (array) $items as $it ) {
		if ( false !== strpos( $it, 'prev page-numbers' ) ) {
			$prev = $it;
		} elseif ( false !== strpos( $it, 'next page-numbers' ) ) {
			$next = $it;
		} else {
			$nums[] = $it;
		}
	}
	$arrow = static function ( $dir ) {
		return mcupr_feather( 'left' === $dir ? 'chevron-left' : 'chevron-right', 16 );
	};
	$mk = static function ( $html, $kind, $label ) use ( $arrow ) {
		$inner = 'prev' === $kind ? $arrow( 'left' ) . '<span>' . esc_html( $label ) . '</span>' : '<span>' . esc_html( $label ) . '</span>' . $arrow( 'right' );
		if ( '' !== $html && preg_match( '/href=("|\')(.*?)\1/', $html, $m ) ) {
			return '<a class="' . $kind . ' page-numbers" href="' . esc_url( html_entity_decode( $m[2] ) ) . '" rel="' . $kind . '">' . $inner . '</a>';
		}
		return '<span class="' . $kind . ' page-numbers is-off" aria-disabled="true">' . $inner . '</span>';
	};
	$out  = '<nav class="navigation pagination pager" aria-label="' . esc_attr( mcupr_t( 'pagination' ) ) . '"><div class="nav-links">';
	$out .= $mk( $prev, 'prev', mcupr_t( 'prev' ) ) . '<span class="pager-nums">' . implode( '', $nums ) . '</span>' . $mk( $next, 'next', mcupr_t( 'next' ) );
	$out .= '</div><p class="pager-info">' . esc_html( sprintf( mcupr_t( 'page_of' ), (int) $current, (int) $total ) ) . '</p></nav>';
	return $out;
}

/** Localised pagination of the list being shown. */
function mcupr_pagination() {
	global $wp_query;
	$total   = (int) $wp_query->max_num_pages;
	$current = max( 1, (int) get_query_var( 'paged' ) );
	$items   = paginate_links( array( 'total' => $total, 'current' => $current, 'mid_size' => 1, 'type' => 'array', 'prev_text' => 'prev', 'next_text' => 'next' ) );
	echo mcupr_pager_html( $items, $current, $total ); // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Notice shown when a translation is missing. */
function mcupr_missing_translation_notice() {
	if ( mcupr_lang() !== mcupr_default_lang() && ! mcupr_is_translated() ) {
		echo '<p class="mb-4 rounded-lg border-l-4 border-accent bg-surface px-4 py-3" role="note">' . esc_html( mcupr_t( 'tr_missing' ) ) . '</p>';
	}
}

/** role="search" only on the first search form of a page (landmarks must be unique). */
function mcupr_search_role_attr() {
	static $count = 0;
	return 0 === $count++ ? ' role="search"' : '';
}

/** The organisation name in the "other" language, for the Chula-style bilingual brand. */
function mcupr_alt_name() {
	$alt  = 'th' === mcupr_ui_lang() ? 'en' : 'th';
	$name = mcupr_text_mod( 'mcupr_name', $alt );
	return $name && $name !== mcupr_org_name() ? $name : '';
}

/** First category of the current post (card label). */
function mcupr_first_category() {
	$cats = get_the_category();
	return $cats ? $cats[0]->name : '';
}

/** Hero background image (attachment id): Customizer image, else the newest post's thumbnail. */
function mcupr_hero_image_id( $fallback_post_id = 0 ) {
	$id = (int) get_theme_mod( 'mcupr_hero_image', 0 );
	if ( $id && wp_get_attachment_image_url( $id, 'full' ) ) {
		return $id;
	}
	return $fallback_post_id ? (int) get_post_thumbnail_id( $fallback_post_id ) : 0;
}

/** The inline SVG icon set (24x24, stroke, Feather style): name => inner markup. */
function mcupr_icon_paths() {
	return array(
		'layout-top'  => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9.5h18"/><path d="M7 6.8h4"/>',
		'layout-bar'  => '<rect x="3" y="4" width="18" height="16" rx="3"/><rect x="5.5" y="8.5" width="13" height="3.5" rx="1.75"/><path d="M8 10.25h0M11 10.25h4"/>',
		'layout-side' => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9.5 4v16"/><path d="M6.2 8h0M6.2 11h0M6.2 14h0"/>',
		'tag'      => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.2"/>',
		'rows'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
		'contrast' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor"/>',
		'underline' => '<path d="M6 3v7a6 6 0 0 0 12 0V3"/><path d="M4 21h16"/>',
		'type'     => '<path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/>',
		'spacing'  => '<path d="M12 3v18"/><path d="M8 7l4-4 4 4"/><path d="M8 17l4 4 4-4"/>',
		'pointer'  => '<path d="M3 3l7.07 16.97 2.51-7.39 7.39-2.51L3 3z"/>',
		'blossom'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3"/>',
		'tv'       => '<rect x="2" y="7" width="20" height="13" rx="2"/><path d="M17 2l-5 5-5-5"/>',
		'play'     => '<path d="M7 4.5v15l13-7.5z" fill="currentColor"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'trophy'   => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"/>',
		'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
		'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
		'ribbon'   => '<path d="M12 3a5 5 0 0 0-5 5c0 3 2 5 3 8l-3 5 5-2 5 2-3-5c1-3 3-5 3-8a5 5 0 0 0-5-5z"/>',
		'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'alert'    => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
		'edit'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/>',
		'book'     => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
		'bulb'     => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
		'money'    => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
		'bank'     => '<path d="M3 21h18M5 21V10M9 21V10M15 21V10M19 21V10M2 10l10-7 10 7z"/>',
		'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
		'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'mail'     => '<path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><path d="M22 6l-10 7L2 6"/>',
		'cap'      => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
		'news'     => '<path d="M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2z"/><path d="M19 8h2v10a2 2 0 0 1-2 2M8 8h7M8 12h7M8 16h4"/>',
		'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
		'search'   => '<circle cx="11" cy="11" r="7.5"/><path d="M16.5 16.5L22 22"/>',
		'link'     => '<path d="M7 17L17 7M8 7h9v9"/>',
		'sliders'  => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
		'panel'    => '<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16"/>',
		'chevron'  => '<path d="M9 6l6 6-6 6"/>',
		'external' => '<path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
		'cloud'    => '<path d="M7 18a4 4 0 0 1-.6-7.96A5.5 5.5 0 0 1 17 8.5 4.75 4.75 0 0 1 17.5 18z"/><path d="M8 21l1-2M12 21l1-2M16 21l1-2"/>',
		'laptop'   => '<rect x="4" y="5" width="16" height="11" rx="2"/><path d="M2 19h20"/>',
		'home'     => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
		'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
		'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
		'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
		'award'    => '<circle cx="12" cy="8" r="7"/><path d="M8.2 13.9L7 23l5-3 5 3-1.2-9.1"/>',
		'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
		'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21.2l7.8-7.7 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
		'star'     => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		'flag'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
		'camera'   => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
		'video'    => '<path d="M23 7l-7 5 7 5z"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
		'folder'   => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
		'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'check'    => '<path d="M20 6L9 17l-5-5"/>',
		'grid'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
	);
}

/** One icon as inline SVG (unknown names fall back to the arrow). */
function mcupr_icon( $name, $size = 24 ) {
	$paths = mcupr_icon_paths();
	$p     = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['link'];
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Hero quick links: icon (menu item CSS class "icon-xxx" or the item's icon field) above the text. Other menus: inc/menus.php. */
add_filter(
	'nav_menu_item_title',
	static function ( $title, $item, $args ) {
		if ( empty( $args->menu_class ) || 'hero-menu' !== $args->menu_class ) {
			return $title;
		}
		return '<span class="mb-auto text-white/90">' . mcupr_icon( mcupr_menu_item_icon( $item ) ?: 'link', 28 ) . '</span><span>' . $title . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	10,
	3
);

/** Remember whether the tool buttons were printed. */
function mcupr_tools_rendered( $set = false ) {
	static $done = false;
	if ( $set ) {
		$done = true;
	}
	return $done;
}

/** Short language badge text, e.g. "TH". */
function mcupr_lang_badge() {
	return strtoupper( substr( mcupr_lang(), 0, 2 ) );
}

/** Name of the current language, for the globe button's label. */
function mcupr_lang_label() {
	$langs = mcupr_languages();
	$cur   = mcupr_lang();
	return isset( $langs[ $cur ]['label'] ) ? $langs[ $cur ]['label'] : $cur;
}

/** Icon buttons: search, language, display settings, about – used by the default header and [mcupr_tools]. */
function mcupr_tools_cluster( $btn = 'text-fg2 hover:bg-surface' ) {
	mcupr_tools_rendered( true );
	$base = 'relative inline-flex size-11 items-center justify-center rounded-xl border border-control/60 bg-bg p-0 cursor-pointer ' . $btn;
	?>
	<div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
		<a id="search-toggle" class="<?php echo esc_attr( $base ); ?> no-underline" href="<?php echo esc_url( add_query_arg( 's', '', mcupr_home_url() ) ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>">
			<?php echo mcupr_icon( 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
		<button type="button" id="lang-toggle" class="<?php echo esc_attr( $base ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_lang_badge() . ' ' . mcupr_t( 'language' ) . ': ' . mcupr_lang_label() ); ?>" title="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
			<?php echo mcupr_icon( 'globe', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="absolute -top-1.5 -right-1.5 rounded-full bg-primary px-1.5 text-[.65rem] font-bold leading-4 text-on-primary" aria-hidden="true"><?php echo esc_html( mcupr_lang_badge() ); ?></span>
		</button>
		<button type="button" id="a11y-toggle" class="<?php echo esc_attr( $base ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_t( 'settings_title' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'settings_btn' ) ); ?>">
			<?php echo mcupr_icon( 'sliders', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
		<?php
		$modes      = function_exists( 'mcupr_active_modes' ) ? mcupr_active_modes() : array();
		$about_name = mcupr_t( 'about' );
		if ( $modes ) { // editors only: say which special modes are on
			$about_name .= ' — ' . mcupr_t( 'md_on' ) . ' ' . implode( ', ', array_map( static function ( $m ) { return mcupr_t( 'md_' . $m ); }, $modes ) );
		}
		?>
		<button type="button" id="about-toggle" class="<?php echo esc_attr( $base ); ?>"<?php echo $modes ? ' data-mode="' . esc_attr( $modes[0] ) . '" data-modes="' . esc_attr( implode( ' ', $modes ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?> aria-haspopup="dialog" aria-label="<?php echo esc_attr( $about_name ); ?>" title="<?php echo esc_attr( $about_name ); ?>">
			<?php echo mcupr_icon( 'help', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>
	<?php
}

/** Site default menu position: top (three capsules) | bar (one capsule) | side. The old "hybrid" choice is now the sidebar. */
function mcupr_menu_default() {
	$v = get_theme_mod( 'mcupr_menu_layout', 'top' );
	return in_array( $v, array( 'side', 'hybrid' ), true ) ? 'side' : ( 'bar' === $v ? 'bar' : 'top' );
}
/** The university emblem shipped with the theme: the main logo until a logo is uploaded (Site Identity). */
function mcupr_default_logo( $class = '' ) {
	return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( MCUPR_URI . '/assets/img/mcu-logo.webp' ) . '" alt="" width="119" height="160" decoding="async">';
}

/** Header / footer logo (<img>), with optional dark-mode variant. $where: header | footer. */
function mcupr_logo( $where = 'header', $class = '' ) {
	$main = (int) get_theme_mod( 'custom_logo' );
	$dark = (int) get_theme_mod( 'mcupr_logo_dark' );
	$foot = (int) get_theme_mod( 'mcupr_logo_footer' );
	$attr = array( 'alt' => '' );
	if ( 'footer' === $where ) {
		$id = $foot ? $foot : ( $dark ? $dark : $main );
		return $id ? wp_get_attachment_image( $id, 'full', false, $attr + array( 'class' => trim( 'h-16 w-auto ' . $class ) ) ) : mcupr_default_logo( trim( 'h-16 w-auto ' . $class ) );
	}
	$size = 'h-[min(var(--logo-h),3.25rem)] w-auto min-[1100px]:h-[var(--logo-h)]';
	if ( ! $main ) {
		return mcupr_default_logo( trim( "$size $class" ) ); // one emblem for light and dark (no logo-light class)
	}
	$out  = wp_get_attachment_image( $main, 'full', false, $attr + array( 'class' => trim( "$size logo-light $class" ) ) );
	if ( $dark ) {
		$out .= wp_get_attachment_image( $dark, 'full', false, $attr + array( 'class' => trim( "$size logo-dark $class" ) ) );
	}
	return $out;
}

/** Estimated reading time in minutes (Thai / CJK by characters, others by words). */
function mcupr_reading_minutes( $content = '' ) {
	$text = wp_strip_all_tags( $content ? $content : get_post_field( 'post_content', get_the_ID() ) );
	if ( in_array( mcupr_ui_lang(), array( 'th', 'zh', 'ja' ), true ) ) {
		$n = mb_strlen( preg_replace( '/\s+/u', '', $text ) ) / 500;
	} else {
		$n = str_word_count( $text ) / 200;
	}
	return max( 1, (int) ceil( $n ) );
}

/** Pages / ancestors for the "in this section" list. Returns array( root_id, children[] ). */
function mcupr_section_pages( $post_id ) {
	$ancestors = get_post_ancestors( $post_id );
	$root      = $ancestors ? (int) end( $ancestors ) : (int) $post_id;
	$pages     = get_pages( array( 'child_of' => $root, 'sort_column' => 'menu_order,post_title', 'parent' => -1 ) );
	return array( $root, $pages );
}

/** Centered status card (404, no results): round icon, title, text, optional extra HTML. */
function mcupr_state_card( $icon, $title, $text = '', $extra = '', $tag = 'h1' ) {
	$tag = in_array( $tag, array( 'h1', 'h2', 'h3' ), true ) ? $tag : 'h2';
	echo '<div class="state-card"><span class="st-ic">' . mcupr_icon( $icon, 34 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<' . $tag . '>' . esc_html( $title ) . '</' . $tag . '>';
	if ( $text ) {
		echo '<p class="text-muted">' . esc_html( $text ) . '</p>';
	}
	echo $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
}


/**
 * The two share buttons (Facebook, LINE): brand colours, icon + label, open in a new tab.
 * Used at the top of an article (article-tools) and in the share bar at its end.
 */
function mcupr_share_buttons( $url, $title = '' ) {
	$fb   = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
	$line = 'https://social-plugins.line.me/lineit/share?url=' . rawurlencode( $url );
	$new  = ' <span class="sr-only">(' . esc_html( mcupr_t( 'new_tab' ) ) . ')</span>';
	echo '<a class="share-btn share-fb" href="' . esc_url( $fb ) . '" target="_blank" rel="noopener noreferrer">';
	echo '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.5 22v-8.2h2.8l.5-3.3h-3.3V8.4c0-.9.4-1.7 1.8-1.7h1.6V3.8s-1.4-.3-2.8-.3c-2.8 0-4.5 1.7-4.5 4.7v2.3H6.8v3.3h2.8V22z"/></svg>';
	echo '<span>' . esc_html( mcupr_t( 'share_to' ) ) . ' Facebook</span>' . $new . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<a class="share-btn share-line" href="' . esc_url( $line ) . '" target="_blank" rel="noopener noreferrer">';
	echo '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3C6.5 3 2 6.6 2 11c0 3.9 3.5 7.2 8.3 7.9.3.1.8.2.9.5.1.3.1.7 0 1l-.1.8c0 .3-.2 1 .9.5s5.8-3.4 7.9-5.8c1.5-1.6 2.1-3.2 2.1-4.9C22 6.6 17.5 3 12 3zM8.2 13.3H6.1c-.3 0-.5-.2-.5-.5V9.2c0-.3.2-.5.5-.5s.5.2.5.5v3.1h1.6c.3 0 .5.2.5.5s-.2.5-.5.5zm2 0c-.3 0-.5-.2-.5-.5V9.2c0-.3.2-.5.5-.5s.5.2.5.5v3.6c0 .3-.2.5-.5.5zm4.9 0c-.2 0-.4-.1-.5-.3l-1.8-2.5v2.3c0 .3-.2.5-.5.5s-.5-.2-.5-.5V9.2c0-.2.1-.4.3-.5h.2c.2 0 .3.1.4.2l1.8 2.5V9.2c0-.3.2-.5.5-.5s.5.2.5.5v3.6c0 .2-.1.4-.4.5zm3.1-2.4c.3 0 .5.2.5.5s-.2.5-.5.5h-1.2v.8h1.2c.3 0 .5.2.5.5s-.2.5-.5.5h-1.7c-.3 0-.5-.2-.5-.5V9.2c0-.3.2-.5.5-.5h1.7c.3 0 .5.2.5.5s-.2.5-.5.5h-1.2v.8z"/></svg>';
	echo '<span>' . esc_html( mcupr_t( 'share_to' ) ) . ' LINE</span>' . $new . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
}


/** Is a home page section switched on (Customizer: MCUPR: Home page sections)? */
function mcupr_home_on( $key, $default = true ) {
	return (bool) get_theme_mod( 'mcupr_home_' . $key . '_on', $default );
}

/** Load the cover pictures of a list of posts with one query (instead of one query per card). */
function mcupr_prime_thumbs( $posts ) {
	$ids = array();
	foreach ( (array) $posts as $p ) {
		$t = get_post_thumbnail_id( $p );
		if ( $t ) {
			$ids[] = (int) $t;
		}
	}
	if ( count( $ids ) > 1 ) {
		_prime_post_caches( array_values( array_unique( $ids ) ), false, true );
	}
}
