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

/** Localised pagination. */
function mcupr_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => '<span aria-hidden="true">‹</span> ' . esc_html( mcupr_t( 'prev' ) ),
			'next_text'          => esc_html( mcupr_t( 'next' ) ) . ' <span aria-hidden="true">›</span>',
			'screen_reader_text' => mcupr_t( 'pagination' ),
			'aria_label'         => mcupr_t( 'pagination' ),
		)
	);
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

/** Home page shows the hero, so the header floats over it. */
function mcupr_header_over_hero() {
	return is_front_page() && ! is_paged();
}

/** Hero background image URL: Customizer image, else the newest post thumbnail. */
function mcupr_hero_image_url( $fallback_post_id = 0 ) {
	$id = (int) get_theme_mod( 'mcupr_hero_image', 0 );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( $url ) {
			return $url;
		}
	}
	return $fallback_post_id ? (string) get_the_post_thumbnail_url( $fallback_post_id, 'full' ) : '';
}

/** Small inline SVG icon set (24x24, stroke). */
function mcupr_icon( $name, $size = 24 ) {
	$paths = array(
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
		'home'     => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>',
	);
	$p = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['link'];
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Put an icon (from the menu item's CSS class "icon-xxx") in front of hero quick links. */
add_filter(
	'nav_menu_item_title',
	static function ( $title, $item, $args ) {
		if ( empty( $args->menu_class ) ) {
			return $title;
		}
		$icon = '';
		foreach ( (array) $item->classes as $c ) {
			if ( 0 === strpos( $c, 'icon-' ) ) {
				$icon = substr( $c, 5 );
			}
		}
		if ( 'hero-menu' === $args->menu_class ) {
			return '<span class="mb-auto text-white/90">' . mcupr_icon( $icon ? $icon : 'link', 28 ) . '</span><span>' . $title . '</span>';
		}
		if ( 'menu' === $args->menu_class && 0 === (int) $item->menu_item_parent ) {
			// Main menu: icon (or initial letter) for the sidebar / drawer, text in .t
			$ic = $icon ? mcupr_icon( $icon, 20 ) : esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $title ), 0, 1 ) ) );
			return '<span class="ic" aria-hidden="true">' . $ic . '</span><span class="t">' . $title . '</span>';
		}
		return $title;
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
		<button type="button" id="about-toggle" class="<?php echo esc_attr( $base ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_t( 'about' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'about' ) ); ?>">
			<?php echo mcupr_icon( 'help', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>
	<?php
}

/** Site default menu position (top | side | hybrid). */
function mcupr_menu_default() {
	$v = get_theme_mod( 'mcupr_menu_layout', 'top' );
	return in_array( $v, array( 'side', 'hybrid' ), true ) ? $v : 'top';
}
/** The university emblem shipped with the theme: the main logo until a logo is uploaded (Site Identity). */
function mcupr_default_logo( $class = '' ) {
	return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( MCUPR_URI . '/assets/img/mcu-logo.svg' ) . '" alt="" width="743" height="1000">';
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
