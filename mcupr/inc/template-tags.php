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
