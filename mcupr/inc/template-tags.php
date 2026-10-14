<?php
/**
 * Template helpers.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Menu for the current language, falling back to default language, then pages. */
function mcupr_primary_menu() {
	// Polylang gives every location a per-language variant itself.
	$loc = mcupr_has_polylang() ? 'primary' : 'primary_' . mcupr_lang();
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
function mcupr_breadcrumb() {
	if ( is_front_page() || mcupr_plugin_breadcrumb() ) {
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
	echo '<nav class="wrap my-4 text-[.9375rem] text-muted" aria-label="' . esc_attr( mcupr_t( 'breadcrumb' ) ) . '"><ol class="m-0 flex list-none flex-wrap gap-x-2 gap-y-1 p-0">';
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

/** Localised date. */
function mcupr_date( $post = null ) {
	$post = get_post( $post );
	$ts   = get_post_timestamp( $post );
	$lang = mcupr_ui_lang();
	$text = wp_date( get_option( 'date_format' ), $ts );
	if ( 'th' === $lang ) {
		// Buddhist Era, Thai month names.
		$months = array( '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม' );
		$text   = (int) wp_date( 'j', $ts ) . ' ' . $months[ (int) wp_date( 'n', $ts ) ] . ' ' . ( (int) wp_date( 'Y', $ts ) + 543 );
	} elseif ( 'en' === $lang ) {
		$text = wp_date( 'j F Y', $ts );
	} elseif ( 'zh' === $lang ) {
		$text = wp_date( 'Y年n月j日', $ts );
	} elseif ( 'ja' === $lang ) {
		$text = wp_date( 'Y年n月j日', $ts );
	}
	printf( '<time datetime="%s">%s</time>', esc_attr( wp_date( 'c', $ts ) ), esc_html( $text ) );
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
	return is_front_page() && ! is_paged() && ! ( is_page() && mcupr_is_elementor_page( get_queried_object_id() ) );
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
	);
	$p = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['link'];
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Put an icon (from the menu item's CSS class "icon-xxx") in front of hero quick links. */
add_filter(
	'nav_menu_item_title',
	static function ( $title, $item, $args ) {
		if ( empty( $args->menu_class ) || 'hero-menu' !== $args->menu_class ) {
			return $title;
		}
		$icon = 'link';
		foreach ( (array) $item->classes as $c ) {
			if ( 0 === strpos( $c, 'icon-' ) ) {
				$icon = substr( $c, 5 );
			}
		}
		return '<span class="mb-auto text-white/90">' . mcupr_icon( $icon, 28 ) . '</span><span>' . $title . '</span>';
	},
	10,
	3
);

/** Remember whether the tool buttons were printed (so an Elementor header without them gets a fallback bar). */
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

/** The three icon buttons (accessibility, language, search) – used by the default header and [mcupr_tools]. */
function mcupr_tools_cluster( $btn = 'text-fg hover:bg-surface' ) {
	mcupr_tools_rendered( true );
	$base = 'relative inline-flex size-11 items-center justify-center rounded-full border-0 bg-transparent p-0 cursor-pointer ' . $btn;
	?>
	<div class="flex items-center gap-1" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
		<button type="button" id="a11y-toggle" class="<?php echo esc_attr( $base ); ?> text-xl font-medium" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_t( 'a11y_helper' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'a11y_helper' ) ); ?>"><span aria-hidden="true">Aa</span></button>
		<button type="button" id="lang-toggle" class="<?php echo esc_attr( $base ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_lang_badge() . ' ' . mcupr_t( 'language' ) . ': ' . mcupr_lang_label() ); ?>" title="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
			<?php echo mcupr_icon( 'globe', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="absolute -top-0.5 -right-1 rounded-full bg-primary px-1.5 text-[.65rem] font-bold leading-4 text-on-primary" aria-hidden="true"><?php echo esc_html( mcupr_lang_badge() ); ?></span>
		</button>
		<a id="search-toggle" class="<?php echo esc_attr( $base ); ?> no-underline" href="<?php echo esc_url( add_query_arg( 's', '', mcupr_home_url() ) ); ?>" aria-haspopup="dialog" aria-label="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>">
			<?php echo mcupr_icon( 'search', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<?php
}
