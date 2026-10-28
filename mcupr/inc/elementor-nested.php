<?php
/**
 * Widgets of Elementor that hold other elements, and some newer ones, printed without the plugin: the accordion and the tabs that
 * took the place of the old ones in Elementor 3.15+ ("nested-accordion", "nested-tabs": every item is a container of its own),
 * the testimonial carousel, the audio widget and the text on a path. The accordion is plain <details> (it works without script,
 * assets/js/elementor-compat.js only adds "open one at a time"), the tabs show all panels until the script hides all but the first.
 * The audio widget is a link, not a player (nothing is loaded from SoundCloud until the visitor chooses to), and the text on a path is
 * kept as text (its curve is not drawn).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_el_n_ids( $el, $i, $kind ) {
	$base = isset( $el['id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $el['id'] ) : 'x';
	return 'e-n-' . $kind . '-' . $base . '-' . (int) $i;
}

function mcupr_el_w_nested_accordion( $s, $el, $post_id ) {
	$items = mcupr_el_s( $s, 'items', array() );
	if ( ! is_array( $items ) || ! $items ) {
		return '';
	}
	mcupr_el_need( 'nested' );
	$tag      = mcupr_el_tag( mcupr_el_s( $s, 'title_tag', 'div' ), 'div' );
	$first    = 'all_collapsed' !== mcupr_el_s( $s, 'default_state', 'expanded' );
	$one      = 'multiple' !== mcupr_el_s( $s, 'max_items_expended', 'one' );
	$children = array_values( (array) $el['elements'] );
	$html     = '<div class="e-n-accordion"' . ( $one ? ' data-one="1"' : '' ) . '>';
	foreach ( array_values( $items ) as $n => $it ) {
		$i     = $n + 1;
		$id    = mcupr_el_n_ids( $el, $i, 'accordion-item' );
		$title = isset( $it['item_title'] ) ? (string) $it['item_title'] : '';
		$html .= '<details id="' . esc_attr( $id ) . '" class="e-n-accordion-item"' . ( 1 === $i && $first ? ' open' : '' ) . '><summary class="e-n-accordion-item-title" data-accordion-index="' . $i . '"><span class="e-n-accordion-item-title-header"><' . $tag . ' class="e-n-accordion-item-title-text">' . wp_kses_post( $title ) . '</' . $tag . '></span><span class="e-n-accordion-item-title-icon" aria-hidden="true"></span></summary>';
		$html .= '<div class="e-n-accordion-item-content" role="region" aria-labelledby="' . esc_attr( $id ) . '">' . ( isset( $children[ $n ] ) ? mcupr_el_render_element( $children[ $n ], $post_id, $el ) : '' ) . '</div></details>';
	}
	return $html . '</div>';
}

function mcupr_el_wcss_nested_accordion( $s, $root ) {
	$out = '';
	foreach ( array( 'normal_title_color' => '', 'hover_title_color' => ':hover', 'active_title_color' => '[open] > .e-n-accordion-item-title' ) as $k => $sel ) {
		$c = mcupr_el_color_of( $s, $k );
		if ( '' === $c ) {
			continue;
		}
		$out .= mcupr_el_rule( 'active_title_color' === $k ? $root . ' .e-n-accordion-item' . $sel : $root . ' .e-n-accordion-item-title' . $sel, array( 'color:' . $c ) );
	}
	return $out . mcupr_el_typo_rule( $root . ' .e-n-accordion-item-title-text', $s, 'title_typography' );
}

function mcupr_el_w_nested_tabs( $s, $el, $post_id ) {
	$items = mcupr_el_s( $s, 'tabs', array() );
	if ( ! is_array( $items ) || ! $items ) {
		return '';
	}
	mcupr_el_need( 'nested' );
	$children = array_values( (array) $el['elements'] );
	$vertical = in_array( mcupr_el_s( $s, 'tabs_direction', 'block-start' ), array( 'inline-start', 'inline-end', 'left', 'right' ), true );
	$heads    = '';
	$panels   = '';
	foreach ( array_values( $items ) as $n => $it ) {
		$i     = $n + 1;
		$tid   = mcupr_el_n_ids( $el, $i, 'tab-title' );
		$pid   = mcupr_el_n_ids( $el, $i, 'tab-content' );
		$title = isset( $it['tab_title'] ) ? (string) $it['tab_title'] : '';
		$icon  = isset( $it['tab_icon'] ) && is_array( $it['tab_icon'] ) ? mcupr_el_icon( $it['tab_icon'] ) : '';
		$heads .= '<button type="button" id="' . esc_attr( $tid ) . '" class="e-n-tab-title" role="tab" data-tab-index="' . $i . '" aria-selected="' . ( 1 === $i ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $pid ) . '" tabindex="' . ( 1 === $i ? '0' : '-1' ) . '">' . ( '' !== $icon ? '<span class="e-n-tab-icon" aria-hidden="true">' . $icon . '</span>' : '' ) . '<span class="e-n-tab-title-text">' . wp_kses_post( $title ) . '</span></button>';
		$panels .= '<div id="' . esc_attr( $pid ) . '" class="e-n-tab-panel" role="tabpanel" aria-labelledby="' . esc_attr( $tid ) . '" data-tab-index="' . $i . '">' . ( isset( $children[ $n ] ) ? mcupr_el_render_element( $children[ $n ], $post_id, $el ) : '' ) . '</div>';
	}
	return '<div class="e-n-tabs' . ( $vertical ? ' e-n-tabs-vertical' : '' ) . '"><div class="e-n-tabs-heading" role="tablist" aria-orientation="' . ( $vertical ? 'vertical' : 'horizontal' ) . '">' . $heads . '</div><div class="e-n-tabs-content">' . $panels . '</div></div>';
}

function mcupr_el_w_testimonial_carousel( $s ) {
	$slides = mcupr_el_s( $s, 'slides', array() );
	if ( ! is_array( $slides ) || ! $slides ) {
		return '';
	}
	$html = '<div class="e-tc" role="group" aria-roledescription="carousel" tabindex="0">';
	foreach ( array_values( $slides ) as $n => $sl ) {
		$img   = isset( $sl['image'] ) ? mcupr_el_img( $sl['image'], 'thumbnail' ) : '';
		$html .= '<figure class="e-tc-slide" role="group" aria-roledescription="slide" aria-label="' . ( $n + 1 ) . ' / ' . count( $slides ) . '"><blockquote class="elementor-testimonial-content">' . wp_kses_post( isset( $sl['content'] ) ? (string) $sl['content'] : '' ) . '</blockquote><figcaption class="elementor-testimonial-details">' . ( '' !== $img ? '<span class="elementor-testimonial-image">' . $img . '</span>' : '' ) . '<span class="elementor-testimonial-name">' . esc_html( isset( $sl['name'] ) ? (string) $sl['name'] : '' ) . '</span> <span class="elementor-testimonial-job">' . esc_html( isset( $sl['title'] ) ? (string) $sl['title'] : '' ) . '</span></figcaption></figure>';
	}
	return $html . '</div>';
}

function mcupr_el_w_audio( $s ) {
	$l   = mcupr_el_s( $s, 'link', '' );
	$url = is_array( $l ) ? ( isset( $l['url'] ) ? (string) $l['url'] : '' ) : (string) $l;
	$url = esc_url( $url );
	if ( '' === $url ) {
		return false;
	}
	return '<p class="e-audio-link"><a href="' . $url . '" target="_blank" rel="noopener">' . esc_html( mcupr_t( 'el_listen' ) ) . '<span class="sr-only"> ' . esc_html( mcupr_t( 'new_tab' ) ) . '</span></a></p>';
}

function mcupr_el_w_text_path( $s ) {
	$t = (string) mcupr_el_s( $s, 'text', '' );
	return '' === trim( $t ) ? false : '<p class="e-text-path">' . esc_html( $t ) . '</p>';
}

/** The newer "Rating" widget: the scale is a number of stars (1-10) and the value a number, drawn as the star rating. */
function mcupr_el_w_rating( $s ) {
	$sc = mcupr_el_s( $s, 'rating_scale', 5 );
	$sc = (int) ( is_array( $sc ) && isset( $sc['size'] ) ? $sc['size'] : $sc );
	$sc = $sc > 5 ? 10 : 5;
	return mcupr_el_w_star_rating( array_merge( $s, array( 'rating_scale' => $sc, 'rating' => mcupr_el_s( $s, 'rating_value', 5 ) ) ) );
}
