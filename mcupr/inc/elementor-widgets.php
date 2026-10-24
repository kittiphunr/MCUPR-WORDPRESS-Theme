<?php
/**
 * Elementor pages without Elementor, part 4: the widgets of Elementor (free), each as the HTML Elementor printed for it, and the
 * CSS rules of its style settings. A widget is a function mcupr_el_w_<name>( $settings, $element, $post_id ) that returns the inside of
 * the widget; mcupr_el_wcss_<name>( $settings, $root, $element ) returns its CSS (when the style file of Elementor is not used).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ----------------------------------------------------------------------------------------------- helpers */

function mcupr_el_s( $s, $key, $default = '' ) {
	return isset( $s[ $key ] ) && null !== $s[ $key ] ? $s[ $key ] : $default;
}

/** A link setting ({url, is_external, nofollow}) → array( opening tag, closing tag ); both empty without a URL. */
function mcupr_el_link( $link, $class = '', $extra = '' ) {
	if ( ! is_array( $link ) || empty( $link['url'] ) ) {
		return array( '', '' );
	}
	$rel = array();
	if ( ! empty( $link['nofollow'] ) ) {
		$rel[] = 'nofollow';
	}
	if ( ! empty( $link['is_external'] ) ) {
		$rel[] = 'noopener';
	}
	$url = (string) $link['url'];
	if ( preg_match( '#^(javascript|data|vbscript):#i', $url ) ) {
		return array( '', '' );
	}
	$open = '<a' . ( '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( $url ) . '"' . ( ! empty( $link['is_external'] ) ? ' target="_blank"' : '' ) . ( $rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '' ) . $extra . '>';
	return array( $open, '</a>' );
}

/** An icon setting ({value, library}) → the icon: Font Awesome class, or an uploaded SVG printed inline. */
function mcupr_el_icon( $icon, $class = '' ) {
	if ( ! is_array( $icon ) || empty( $icon['value'] ) ) {
		return '';
	}
	$v = $icon['value'];
	if ( is_array( $v ) ) {
		$url = isset( $v['url'] ) ? (string) $v['url'] : '';
		$id  = isset( $v['id'] ) ? (int) $v['id'] : 0;
		$svg = '';
		if ( $id ) {
			$file = get_attached_file( $id );
			if ( $file && is_readable( $file ) && 'svg' === strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				$svg = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		if ( '' !== $svg && false !== stripos( $svg, '<svg' ) ) {
			$svg = preg_replace( '/<\?xml.*?\?>|<!DOCTYPE[^>]*>|<script\b.*?<\/script>/is', '', $svg );
			$svg = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', (string) $svg );
			return trim( (string) $svg );
		}
		return '' !== $url ? '<img src="' . esc_url( $url ) . '" alt="" class="' . esc_attr( $class ) . '">' : '';
	}
	$v = preg_replace( '/[^A-Za-z0-9 _\-]/', '', (string) $v );
	if ( '' === $v ) {
		return '';
	}
	$r = &mcupr_el_state_ref();
	if ( preg_match( '/\b(fa|fas|far|fab|fal)\b/', $v ) ) {
		$r['icons'] = true;
	}
	return '<i aria-hidden="true" class="' . esc_attr( trim( $v . ' ' . $class ) ) . '"></i>';
}

/** An image setting ({url, id}) → <img> (a library image with its sizes, or the plain address). */
function mcupr_el_img( $image, $size = 'full', $attr = array() ) {
	if ( ! is_array( $image ) ) {
		return '';
	}
	$id = isset( $image['id'] ) ? (int) $image['id'] : 0;
	if ( $id && wp_attachment_is_image( $id ) ) {
		$html = wp_get_attachment_image( $id, $size ? $size : 'full', false, $attr );
		if ( '' !== $html ) {
			return $html;
		}
	}
	if ( empty( $image['url'] ) ) {
		return '';
	}
	$alt = isset( $attr['alt'] ) ? $attr['alt'] : '';
	return '<img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy"' . ( ! empty( $attr['class'] ) ? ' class="' . esc_attr( $attr['class'] ) . '"' : '' ) . '>';
}

/** Rich text of a widget (the text editor, an alert …): paragraphs, shortcodes, embeds. */
function mcupr_el_rich( $html ) {
	$html = (string) $html;
	$html = wpautop( $html );
	$html = do_shortcode( shortcode_unautop( $html ) );
	return $html;
}

function mcupr_el_tag( $tag, $default = 'div', $allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ) ) {
	return in_array( $tag, $allowed, true ) ? $tag : $default;
}

/** A single declaration list for a device from several controls: array( property => array( key, kind ) ). kind: size | dim | color | raw */
function mcupr_el_decls( $s, $map, $dev = '' ) {
	$d = array();
	foreach ( $map as $prop => $def ) {
		list( $key, $kind ) = $def;
		if ( 'color' === $kind ) {
			if ( '' === $dev ) {
				$c = mcupr_el_color_of( $s, $key );
				if ( '' !== $c ) {
					$d[] = $prop . ':' . $c;
				}
			}
			continue;
		}
		$v = mcupr_el_get( $s, $key, $dev );
		if ( ! mcupr_el_has( $v ) ) {
			continue;
		}
		if ( 'size' === $kind ) {
			$z = mcupr_el_size( $v );
			if ( '' !== $z ) {
				$d[] = $prop . ':' . $z;
			}
		} elseif ( 'dim' === $kind ) {
			$z = mcupr_el_dim( $v );
			if ( '' !== $z ) {
				$d[] = $prop . ':' . $z;
			}
		} elseif ( 'raw' === $kind && is_string( $v ) && preg_match( '/^[a-z0-9 .%-]+$/i', $v ) ) {
			$d[] = $prop . ':' . $v;
		}
	}
	return $d;
}

/** The rules of one selector from controls, on all devices. */
function mcupr_el_responsive_rule( $selector, $s, $map ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $selector, mcupr_el_decls( $s, $map, $dev ) ) );
	}
	return $out;
}

/** The typography of a group on all devices. */
function mcupr_el_typo_rule( $selector, $s, $prefix = 'typography' ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $selector, mcupr_el_typography( $s, $prefix, $dev ) ) );
	}
	return $out;
}

/** Text align on a selector (responsive). */
function mcupr_el_align_rule( $selector, $s, $key = 'align' ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$a = mcupr_el_get( $s, $key, $dev );
		if ( is_string( $a ) && preg_match( '/^(left|center|right|justify)$/', $a ) ) {
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $selector, array( 'text-align:' . $a ) ) );
		}
	}
	return $out;
}

/* ----------------------------------------------------------------------------------------------- heading */

function mcupr_el_w_heading( $s ) {
	$title = (string) mcupr_el_s( $s, 'title', '' );
	if ( '' === $title ) {
		return '';
	}
	$tag  = mcupr_el_tag( mcupr_el_s( $s, 'header_size', 'h2' ) );
	$size = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'size', 'default' ) );
	list( $o, $c ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ) );
	return '<' . $tag . ' class="elementor-heading-title elementor-size-' . esc_attr( $size ) . '">' . $o . wp_kses_post( $title ) . $c . '</' . $tag . '>';
}

function mcupr_el_wcss_heading( $s, $root ) {
	$out = mcupr_el_align_rule( $root, $s );
	$col = mcupr_el_color_of( $s, 'title_color' );
	$sel = $root . ' .elementor-heading-title, ' . $root . ' .elementor-heading-title a';
	$out .= mcupr_el_rule( $root . ' .elementor-heading-title', array( '' !== $col ? 'color:' . $col : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-heading-title a', array( '' !== $col ? 'color:' . $col : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-heading-title', $s );
	$out .= mcupr_el_text_shadow( $s, $root . ' .elementor-heading-title' );
	return $out;
}

function mcupr_el_text_shadow( $s, $selector, $prefix = 'text_shadow' ) {
	if ( empty( $s[ $prefix . '_text_shadow_type' ] ) || 'yes' !== $s[ $prefix . '_text_shadow_type' ] || empty( $s[ $prefix . '_text_shadow' ] ) || ! is_array( $s[ $prefix . '_text_shadow' ] ) ) {
		return '';
	}
	$t   = $s[ $prefix . '_text_shadow' ];
	$col = mcupr_el_color( isset( $t['color'] ) ? $t['color'] : 'rgba(0,0,0,0.3)' );
	return mcupr_el_rule( $selector, array( 'text-shadow:' . ( 0 + ( isset( $t['horizontal'] ) ? $t['horizontal'] : 0 ) ) . 'px ' . ( 0 + ( isset( $t['vertical'] ) ? $t['vertical'] : 0 ) ) . 'px ' . ( 0 + ( isset( $t['blur'] ) ? $t['blur'] : 10 ) ) . 'px ' . ( '' !== $col ? $col : 'rgba(0,0,0,.3)' ) ) );
}

/* ------------------------------------------------------------------------------------------- text editor */

function mcupr_el_w_text_editor( $s ) {
	$ed = (string) mcupr_el_s( $s, 'editor', '' );
	$cls = 'elementor-text-editor elementor-clearfix';
	if ( 'yes' === mcupr_el_s( $s, 'drop_cap', '' ) ) {
		$cls .= ' elementor-drop-cap-yes elementor-drop-cap-view-' . preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'drop_cap_view', 'default' ) );
	}
	return '<div class="' . esc_attr( $cls ) . '">' . mcupr_el_rich( $ed ) . '</div>';
}

function mcupr_el_wcss_text_editor( $s, $root ) {
	$out = mcupr_el_align_rule( $root, $s );
	$col = mcupr_el_color_of( $s, 'text_color' );
	$out .= mcupr_el_rule( $root, array( '' !== $col ? 'color:' . $col : '' ) );
	$out .= mcupr_el_typo_rule( $root, $s );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$n = mcupr_el_get( $s, 'text_columns', $dev );
		$g = mcupr_el_size( mcupr_el_get( $s, 'column_gap', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-text-editor', array( is_numeric( $n ) && (int) $n > 1 ? 'columns:' . (int) $n : '', '' !== $g && is_numeric( $n ) ? 'column-gap:' . $g : '' ) ) );
	}
	return $out;
}

/* ----------------------------------------------------------------------------------------------- image */

function mcupr_el_w_image( $s ) {
	$image = mcupr_el_s( $s, 'image', array() );
	if ( ! is_array( $image ) || ( empty( $image['url'] ) && empty( $image['id'] ) ) ) {
		return '';
	}
	$size = (string) mcupr_el_s( $s, 'image_size', 'large' );
	$id   = isset( $image['id'] ) ? (int) $image['id'] : 0;
	if ( 'custom' === $size ) {
		$dim  = mcupr_el_s( $s, 'image_custom_dimension', array() );
		$size = $id && ! empty( $dim['width'] ) && ! empty( $dim['height'] ) ? array( (int) $dim['width'], (int) $dim['height'] ) : 'full';
	}
	$alt = '';
	if ( $id ) {
		$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	}
	$img = mcupr_el_img( $image, $size, array( 'alt' => $alt, 'class' => 'attachment-' . ( is_string( $size ) ? $size : 'custom' ) . ' size-' . ( is_string( $size ) ? $size : 'custom' ) ) );
	if ( '' === $img ) {
		return '';
	}
	$link_to = (string) mcupr_el_s( $s, 'link_to', 'none' );
	$lo = '';
	$lc = '';
	if ( 'custom' === $link_to ) {
		list( $lo, $lc ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ) );
	} elseif ( 'file' === $link_to && ! empty( $image['url'] ) ) {
		list( $lo, $lc ) = mcupr_el_link( array( 'url' => $id ? wp_get_attachment_url( $id ) : $image['url'] ), '', ' data-elementor-open-lightbox="' . ( 'no' === mcupr_el_s( $s, 'open_lightbox', 'default' ) ? 'no' : 'yes' ) . '"' );
		mcupr_el_need( 'lightbox' );
	}
	$html    = $lo . $img . $lc;
	$caption = '';
	$src     = (string) mcupr_el_s( $s, 'caption_source', 'none' );
	if ( 'attachment' === $src && $id ) {
		$caption = (string) wp_get_attachment_caption( $id );
	} elseif ( 'custom' === $src ) {
		$caption = (string) mcupr_el_s( $s, 'caption', '' );
	}
	if ( '' !== $caption ) {
		$html = '<figure class="wp-caption">' . $html . '<figcaption class="widget-image-caption wp-caption-text">' . esc_html( $caption ) . '</figcaption></figure>';
	}
	return $html;
}

function mcupr_el_wcss_image( $s, $root ) {
	$out = mcupr_el_align_rule( $root, $s );
	$img = $root . ' img';
	$out .= mcupr_el_responsive_rule( $img, $s, array( 'width' => array( 'width', 'size' ), 'max-width' => array( 'space', 'size' ), 'height' => array( 'height', 'size' ) ) );
	$of = mcupr_el_s( $s, 'object-fit', '' );
	$out .= mcupr_el_rule( $img, array( is_string( $of ) && preg_match( '/^[a-z-]+$/', $of ) ? 'object-fit:' . $of : '', isset( $s['opacity']['size'] ) && is_numeric( $s['opacity']['size'] ) ? 'opacity:' . ( 0 + $s['opacity']['size'] ) : '' ) );
	$out .= mcupr_el_rule( $img, mcupr_el_border( $s, 'image_border', '', 'image_border_radius', 'image_box_shadow' ) );
	$out .= mcupr_el_responsive_rule( $img, $s, array( 'border-radius' => array( 'image_border_radius', 'dim' ) ) );
	$cc = mcupr_el_color_of( $s, 'caption_text_color' );
	$out .= mcupr_el_rule( $root . ' .widget-image-caption', array( '' !== $cc ? 'color:' . $cc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .widget-image-caption', $s, 'caption_typography' );
	return $out;
}

/* ---------------------------------------------------------------------------------------------- button */

function mcupr_el_button_inner( $s, $text_key = 'text', $icon_key = 'selected_icon' ) {
	$text = (string) mcupr_el_s( $s, $text_key, '' );
	$icon = mcupr_el_icon( mcupr_el_s( $s, $icon_key, array() ) );
	$ia   = 'right' === mcupr_el_s( $s, 'icon_align', 'left' ) ? 'right' : 'left';
	$html = '<span class="elementor-button-content-wrapper">';
	if ( '' !== $icon && 'left' === $ia ) {
		$html .= '<span class="elementor-button-icon elementor-align-icon-left">' . $icon . '</span>';
	}
	$html .= '<span class="elementor-button-text">' . wp_kses_post( $text ) . '</span>';
	if ( '' !== $icon && 'right' === $ia ) {
		$html .= '<span class="elementor-button-icon elementor-align-icon-right">' . $icon . '</span>';
	}
	return $html . '</span>';
}

function mcupr_el_w_button( $s ) {
	$text = (string) mcupr_el_s( $s, 'text', '' );
	$icon = mcupr_el_s( $s, 'selected_icon', array() );
	if ( '' === $text && empty( $icon['value'] ) ) {
		return '';
	}
	$size = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'size', 'sm' ) );
	$type = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'button_type', '' ) );
	$cls  = 'elementor-button elementor-size-' . $size . ( '' !== $type ? ' elementor-button-' . $type : '' );
	$link = mcupr_el_s( $s, 'link', array() );
	$id   = ! empty( $s['button_css_id'] ) && preg_match( '/^[A-Za-z][A-Za-z0-9_\-]*$/', (string) $s['button_css_id'] ) ? ' id="' . esc_attr( $s['button_css_id'] ) . '"' : '';
	list( $o, $c ) = mcupr_el_link( $link, $cls . ' elementor-button-link', $id );
	$inner = mcupr_el_button_inner( $s );
	if ( '' === $o ) {
		return '<div class="elementor-button-wrapper"><a class="' . esc_attr( $cls ) . '" role="button"' . $id . '>' . $inner . '</a></div>';
	}
	return '<div class="elementor-button-wrapper">' . $o . $inner . $c . '</div>';
}

function mcupr_el_wcss_button( $s, $root ) {
	$b   = $root . ' .elementor-button';
	$out = mcupr_el_align_rule( $root, $s );
	$d   = array();
	$c   = mcupr_el_color_of( $s, 'button_text_color' );
	if ( '' !== $c ) {
		$d[] = 'color:' . $c;
		$d[] = 'fill:' . $c;
	}
	$bg = mcupr_el_color_of( $s, 'background_color' );
	if ( '' !== $bg ) {
		$d[] = 'background-color:' . $bg;
	}
	$d    = array_merge( $d, mcupr_el_border( $s, 'border', '', 'border_radius', 'button_box_shadow' ) );
	$out .= mcupr_el_rule( $b, $d );
	$out .= mcupr_el_typo_rule( $b, $s );
	$out .= mcupr_el_responsive_rule( $b, $s, array( 'padding' => array( 'text_padding', 'dim' ), 'border-radius' => array( 'border_radius', 'dim' ) ) );
	$hc   = mcupr_el_color_of( $s, 'hover_color' );
	$hb   = mcupr_el_color_of( $s, 'button_background_hover_color' );
	$hbc  = mcupr_el_color( isset( $s['button_hover_border_color'] ) ? $s['button_hover_border_color'] : '' );
	$out .= mcupr_el_rule( $b . ':hover,' . $b . ':focus', array( '' !== $hc ? 'color:' . $hc : '', '' !== $hb ? 'background-color:' . $hb : '', '' !== $hbc ? 'border-color:' . $hbc : '' ) );
	$out .= mcupr_el_rule( $b . ':hover svg,' . $b . ':focus svg', array( '' !== $hc ? 'fill:' . $hc : '' ) );
	$ind  = mcupr_el_size( isset( $s['icon_indent'] ) ? $s['icon_indent'] : null );
	if ( '' !== $ind ) {
		$out .= mcupr_el_rule( $b . ' .elementor-align-icon-right', array( 'margin-left:' . $ind ) );
		$out .= mcupr_el_rule( $b . ' .elementor-align-icon-left', array( 'margin-right:' . $ind ) );
	}
	return $out;
}

/* --------------------------------------------------------------------------------------- divider, spacer */

function mcupr_el_w_divider( $s ) {
	$style = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'style', 'solid' ) );
	$html  = '<div class="elementor-divider"><span class="elementor-divider-separator">';
	$text  = (string) mcupr_el_s( $s, 'text', '' );
	if ( 'yes' === mcupr_el_s( $s, 'look', '' ) || 'line_text' === mcupr_el_s( $s, 'look', '' ) ) {
		$html .= '<span class="elementor-divider__text elementor-divider__element">' . esc_html( $text ) . '</span>';
	}
	return $html . '</span></div>';
}

function mcupr_el_wcss_divider( $s, $root ) {
	$d = array();
	$style = mcupr_el_s( $s, 'style', 'solid' );
	if ( is_string( $style ) && preg_match( '/^(solid|double|dotted|dashed)$/', $style ) ) {
		$d[] = '--divider-border-style:' . $style;
	}
	$w = mcupr_el_size( mcupr_el_s( $s, 'weight', null ) );
	if ( '' !== $w ) {
		$d[] = '--divider-border-width:' . $w;
	}
	$c = mcupr_el_color_of( $s, 'color' );
	if ( '' !== $c ) {
		$d[] = '--divider-color:' . $c;
	}
	$out = mcupr_el_rule( $root . ' .elementor-divider-separator', $d );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$wd = mcupr_el_size( mcupr_el_get( $s, 'width', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-divider-separator', array( '' !== $wd ? 'width:' . $wd : '' ) ) );
		$g = mcupr_el_size( mcupr_el_get( $s, 'gap', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-divider', array( '' !== $g ? 'padding-block:' . $g : '' ) ) );
	}
	$out .= mcupr_el_align_rule( $root . ' .elementor-divider', $s, 'align' );
	return $out;
}

function mcupr_el_w_spacer() {
	return '<div class="elementor-spacer"><div class="elementor-spacer-inner"></div></div>';
}

function mcupr_el_wcss_spacer( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$h = mcupr_el_size( mcupr_el_get( $s, 'space', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-spacer-inner', array( '' !== $h ? 'height:' . $h : '' ) ) );
	}
	return $out;
}

/* ------------------------------------------------------------------------------------------ icon family */

/** The shared style of an icon: view (default / stacked / framed), colours, size, padding, rotation. */
function mcupr_el_icon_css( $s, $sel, $prefix = '' ) {
	$view = (string) mcupr_el_s( $s, 'view', 'default' );
	$p    = mcupr_el_color_of( $s, 'primary_color' );
	$sec  = mcupr_el_color_of( $s, 'secondary_color' );
	$d    = array();
	if ( 'stacked' === $view ) {
		if ( '' !== $p ) {
			$d[] = 'background-color:' . $p;
		}
		if ( '' !== $sec ) {
			$d[] = 'color:' . $sec;
			$d[] = 'fill:' . $sec;
		}
	} elseif ( 'framed' === $view ) {
		if ( '' !== $p ) {
			$d[] = 'color:' . $p;
			$d[] = 'border-color:' . $p;
			$d[] = 'fill:' . $p;
		}
		if ( '' !== $sec ) {
			$d[] = 'background-color:' . $sec;
		}
	} elseif ( '' !== $p ) {
		$d[] = 'color:' . $p;
		$d[] = 'border-color:' . $p;
		$d[] = 'fill:' . $p;
	}
	$out = mcupr_el_rule( $sel, $d );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$dd = array();
		$sz = mcupr_el_size( mcupr_el_get( $s, 'size', $dev ) );
		if ( '' === $sz ) {
			$sz = mcupr_el_size( mcupr_el_get( $s, 'icon_size', $dev ) );
		}
		if ( '' !== $sz ) {
			$dd[] = 'font-size:' . $sz;
		}
		$pd = mcupr_el_size( mcupr_el_get( $s, 'icon_padding', $dev ) );
		if ( '' !== $pd ) {
			$dd[] = 'padding:' . $pd;
		}
		$rot = mcupr_el_get( $s, 'rotate', $dev );
		if ( is_array( $rot ) && isset( $rot['size'] ) && is_numeric( $rot['size'] ) ) {
			$dd[] = 'transform:rotate(' . ( 0 + $rot['size'] ) . 'deg)';
		}
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $sel, $dd ) );
	}
	$bw = mcupr_el_dim( mcupr_el_s( $s, 'border_width', null ) );
	if ( 'framed' === $view && '' !== $bw ) {
		$out .= mcupr_el_rule( $sel, array( 'border-width:' . $bw ) );
	}
	$out .= mcupr_el_responsive_rule( $sel, $s, array( 'border-radius' => array( 'border_radius', 'dim' ) ) );
	return $out;
}

function mcupr_el_icon_cls( $s ) {
	$view  = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'view', 'default' ) );
	$shape = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'shape', 'circle' ) );
	$c     = 'elementor-view-' . $view;
	if ( 'default' !== $view ) {
		$c .= ' elementor-shape-' . $shape;
	}
	return $c;
}

function mcupr_el_w_icon( $s ) {
	$icon = mcupr_el_icon( mcupr_el_s( $s, 'selected_icon', array() ) );
	if ( '' === $icon ) {
		return '';
	}
	list( $o, $c ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ), 'elementor-icon' );
	$inner = '' !== $o ? $o . $icon . $c : '<div class="elementor-icon">' . $icon . '</div>';
	return '<div class="elementor-icon-wrapper ' . esc_attr( mcupr_el_icon_cls( $s ) ) . '">' . $inner . '</div>';
}

function mcupr_el_wcss_icon( $s, $root ) {
	return mcupr_el_align_rule( $root . ' .elementor-icon-wrapper', $s ) . mcupr_el_icon_css( $s, $root . ' .elementor-icon' );
}

function mcupr_el_w_icon_box( $s ) {
	$icon  = mcupr_el_icon( mcupr_el_s( $s, 'selected_icon', array() ) );
	$title = (string) mcupr_el_s( $s, 'title_text', '' );
	$desc  = (string) mcupr_el_s( $s, 'description_text', '' );
	$tag   = mcupr_el_tag( mcupr_el_s( $s, 'title_size', 'h3' ) );
	list( $o, $c ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ) );
	$html = '<div class="elementor-icon-box-wrapper ' . esc_attr( mcupr_el_icon_cls( $s ) ) . '">';
	if ( '' !== $icon ) {
		$html .= '<div class="elementor-icon-box-icon"><' . ( '' !== $o ? 'a href="' . esc_url( $s['link']['url'] ) . '"' : 'span' ) . ' class="elementor-icon">' . $icon . '</' . ( '' !== $o ? 'a' : 'span' ) . '></div>';
	}
	$html .= '<div class="elementor-icon-box-content">';
	if ( '' !== $title ) {
		$html .= '<' . $tag . ' class="elementor-icon-box-title"><' . ( '' !== $o ? 'a href="' . esc_url( $s['link']['url'] ) . '"' : 'span' ) . '>' . wp_kses_post( $title ) . '</' . ( '' !== $o ? 'a' : 'span' ) . '></' . $tag . '>';
	}
	if ( '' !== $desc ) {
		$html .= '<p class="elementor-icon-box-description">' . wp_kses_post( $desc ) . '</p>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_icon_box( $s, $root ) {
	$out  = mcupr_el_align_rule( $root . ' .elementor-icon-box-wrapper', $s, 'text_align' ) . mcupr_el_icon_css( $s, $root . ' .elementor-icon' );
	$pos  = (string) mcupr_el_s( $s, 'position', 'top' );
	$sp   = mcupr_el_size( mcupr_el_s( $s, 'icon_space', null ) );
	if ( '' !== $sp ) {
		$prop = 'left' === $pos ? 'margin-right' : ( 'right' === $pos ? 'margin-left' : 'margin-bottom' );
		$out .= mcupr_el_rule( $root . ' .elementor-icon-box-icon', array( $prop . ':' . $sp ) );
	}
	$ts   = mcupr_el_size( mcupr_el_s( $s, 'title_bottom_space', null ) );
	$tc   = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-box-title', array( '' !== $tc ? 'color:' . $tc : '', '' !== $ts ? 'margin-bottom:' . $ts : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-icon-box-title', $s, 'title_typography' );
	$dc   = mcupr_el_color_of( $s, 'description_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-box-description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-icon-box-description', $s, 'description_typography' );
	return $out;
}

function mcupr_el_w_image_box( $s ) {
	$image = mcupr_el_s( $s, 'image', array() );
	$title = (string) mcupr_el_s( $s, 'title_text', '' );
	$desc  = (string) mcupr_el_s( $s, 'description_text', '' );
	$tag   = mcupr_el_tag( mcupr_el_s( $s, 'title_size', 'h3' ) );
	$link  = mcupr_el_s( $s, 'link', array() );
	list( $o, $c ) = mcupr_el_link( $link );
	$html = '<div class="elementor-image-box-wrapper">';
	$img  = mcupr_el_img( $image, mcupr_el_s( $s, 'thumbnail_size', 'full' ) );
	if ( '' !== $img ) {
		$html .= '<figure class="elementor-image-box-img">' . $o . $img . $c . '</figure>';
	}
	$html .= '<div class="elementor-image-box-content">';
	if ( '' !== $title ) {
		$html .= '<' . $tag . ' class="elementor-image-box-title">' . $o . wp_kses_post( $title ) . $c . '</' . $tag . '>';
	}
	if ( '' !== $desc ) {
		$html .= '<p class="elementor-image-box-description">' . wp_kses_post( $desc ) . '</p>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_image_box( $s, $root ) {
	$out  = mcupr_el_align_rule( $root . ' .elementor-image-box-wrapper', $s, 'text_align' );
	$pos  = (string) mcupr_el_s( $s, 'position', 'top' );
	$sp   = mcupr_el_size( mcupr_el_s( $s, 'image_space', null ) );
	if ( '' !== $sp ) {
		$prop = 'left' === $pos ? 'margin-right' : ( 'right' === $pos ? 'margin-left' : 'margin-bottom' );
		$out .= mcupr_el_rule( $root . ' .elementor-image-box-img', array( $prop . ':' . $sp ) );
	}
	$out .= mcupr_el_responsive_rule( $root . ' .elementor-image-box-img img', $s, array( 'width' => array( 'image_size', 'size' ) ) );
	$tc   = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-image-box-title, ' . $root . ' .elementor-image-box-title a', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-image-box-title', $s, 'title_typography' );
	$dc   = mcupr_el_color_of( $s, 'description_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-image-box-description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-image-box-description', $s, 'description_typography' );
	return $out;
}

function mcupr_el_w_icon_list( $s ) {
	$items  = mcupr_el_s( $s, 'icon_list', array() );
	$inline = 'inline' === mcupr_el_s( $s, 'view', 'traditional' );
	$html   = '<ul class="elementor-icon-list-items' . ( $inline ? ' elementor-inline-items' : '' ) . '">';
	foreach ( (array) $items as $it ) {
		$icon = mcupr_el_icon( isset( $it['selected_icon'] ) ? $it['selected_icon'] : array() );
		$text = isset( $it['text'] ) ? (string) $it['text'] : '';
		list( $o, $c ) = mcupr_el_link( isset( $it['link'] ) ? $it['link'] : array() );
		$id   = isset( $it['_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $it['_id'] ) : '';
		$html .= '<li class="elementor-icon-list-item' . ( $inline ? ' elementor-inline-item' : '' ) . ( '' !== $id ? ' elementor-repeater-item-' . esc_attr( $id ) : '' ) . '">' . $o;
		if ( '' !== $icon ) {
			$html .= '<span class="elementor-icon-list-icon">' . $icon . '</span>';
		}
		$html .= '<span class="elementor-icon-list-text">' . wp_kses_post( $text ) . '</span>' . $c . '</li>';
	}
	return $html . '</ul>';
}

function mcupr_el_wcss_icon_list( $s, $root ) {
	$out = '';
	$ic  = mcupr_el_color_of( $s, 'icon_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-list-icon i', array( '' !== $ic ? 'color:' . $ic : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-list-icon svg', array( '' !== $ic ? 'fill:' . $ic : '' ) );
	$out .= mcupr_el_responsive_rule( $root . ' .elementor-icon-list-icon', $s, array( 'font-size' => array( 'icon_size', 'size' ), 'width' => array( 'icon_size', 'size' ) ) );
	$tc  = mcupr_el_color_of( $s, 'text_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-list-text', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-icon-list-item', $s, 'icon_typography' );
	$ti  = mcupr_el_size( mcupr_el_s( $s, 'text_indent', null ) );
	$out .= mcupr_el_rule( $root . ' .elementor-icon-list-text', array( '' !== $ti ? 'padding-left:' . $ti : '' ) );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$sb = mcupr_el_size( mcupr_el_get( $s, 'space_between', $dev ) );
		if ( '' !== $sb ) {
			$h   = (float) mcupr_el_get( $s, 'space_between', $dev )['size'] / 2 . ( isset( mcupr_el_get( $s, 'space_between', $dev )['unit'] ) ? mcupr_el_get( $s, 'space_between', $dev )['unit'] : 'px' );
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:last-child)', array( 'padding-bottom:' . $h ) ) . mcupr_el_rule( $root . ' .elementor-icon-list-items:not(.elementor-inline-items) .elementor-icon-list-item:not(:first-child)', array( 'margin-top:' . $h ) ) . mcupr_el_rule( $root . ' .elementor-icon-list-items.elementor-inline-items .elementor-icon-list-item', array( 'margin-right:' . $h, 'margin-left:' . $h ) ) );
		}
	}
	return $out;
}

/* ---------------------------------------------------------------------------------------- social icons */

function mcupr_el_social_name( $value ) {
	$v = is_array( $value ) ? ( isset( $value['value'] ) ? $value['value'] : '' ) : (string) $value;
	return preg_match( '/fa-([a-z0-9\-]+)/', (string) $v, $m ) ? preg_replace( '/-(square|alt|f)$/', '', $m[1] ) : 'link';
}

function mcupr_el_w_social_icons( $s ) {
	$items = mcupr_el_s( $s, 'social_icon_list', array() );
	$shape = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'shape', 'rounded' ) );
	$html  = '<div class="elementor-social-icons-wrapper elementor-grid">';
	foreach ( (array) $items as $it ) {
		$name = mcupr_el_social_name( isset( $it['social_icon'] ) ? $it['social_icon'] : '' );
		$icon = mcupr_el_icon( isset( $it['social_icon'] ) ? $it['social_icon'] : array() );
		$id   = isset( $it['_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $it['_id'] ) : '';
		list( $o ) = mcupr_el_link( isset( $it['link'] ) ? $it['link'] : array(), 'elementor-icon elementor-social-icon elementor-social-icon-' . $name . ( '' !== $id ? ' elementor-repeater-item-' . $id : '' ) );
		if ( '' === $o ) {
			$o = '<a class="elementor-icon elementor-social-icon elementor-social-icon-' . esc_attr( $name ) . '">';
		}
		$html .= '<span class="elementor-grid-item">' . $o . '<span class="elementor-screen-only">' . esc_html( ucfirst( $name ) ) . '</span>' . $icon . '</a></span>';
	}
	return $html . '</div>';
}

function mcupr_el_wcss_social_icons( $s, $root ) {
	$out = mcupr_el_align_rule( $root . ' .elementor-social-icons-wrapper', $s );
	if ( 'custom' === mcupr_el_s( $s, 'icon_color', 'default' ) ) {
		$p   = mcupr_el_color_of( $s, 'icon_primary_color' );
		$sec = mcupr_el_color_of( $s, 'icon_secondary_color' );
		$out .= mcupr_el_rule( $root . ' .elementor-social-icon', array( '' !== $p ? 'background-color:' . $p : '' ) );
		$out .= mcupr_el_rule( $root . ' .elementor-social-icon i', array( '' !== $sec ? 'color:' . $sec : '' ) );
		$out .= mcupr_el_rule( $root . ' .elementor-social-icon svg', array( '' !== $sec ? 'fill:' . $sec : '' ) );
	}
	$out .= mcupr_el_responsive_rule( $root . ' .elementor-social-icon', $s, array( 'font-size' => array( 'icon_size', 'size' ) ) );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$pd = mcupr_el_size( mcupr_el_get( $s, 'icon_padding', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-social-icon', array( '' !== $pd ? 'padding:' . $pd : '' ) ) );
		$sp = mcupr_el_get( $s, 'icon_spacing', $dev );
		if ( is_array( $sp ) && isset( $sp['size'] ) && is_numeric( $sp['size'] ) ) {
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-social-icons-wrapper', array( '--grid-column-gap:' . ( 0 + $sp['size'] ) . ( isset( $sp['unit'] ) ? $sp['unit'] : 'px' ) ) ) );
		}
	}
	$cols = mcupr_el_s( $s, 'columns', '' );
	return $out;
}

/* ----------------------------------------------------------------------------------------- star rating */

function mcupr_el_w_star_rating( $s ) {
	$scale  = (int) mcupr_el_s( $s, 'rating_scale', 5 );
	$scale  = in_array( $scale, array( 5, 10 ), true ) ? $scale : 5;
	$rating = max( 0, min( $scale, (float) mcupr_el_s( $s, 'rating', 5 ) ) );
	$stars  = '';
	$full   = (int) floor( $rating );
	$part   = $rating - $full;
	for ( $i = 1; $i <= $scale; $i++ ) {
		if ( $i <= $full ) {
			$stars .= '<i class="elementor-star-full">&#9733;</i>';
		} elseif ( $i === $full + 1 && $part > 0 ) {
			$stars .= '<i class="elementor-star-' . (int) round( $part * 10 ) . '">&#9733;</i>';
		} else {
			$stars .= '<i class="elementor-star-empty">&#9733;</i>';
		}
	}
	$title = (string) mcupr_el_s( $s, 'title', '' );
	return '<div class="elementor-star-rating__wrapper">' . ( '' !== $title ? '<div class="elementor-star-rating__title">' . esc_html( $title ) . '</div>' : '' ) . '<div class="elementor-star-rating" title="' . esc_attr( $rating . '/' . $scale ) . '" itemtype="http://schema.org/Rating" itemscope="" itemprop="reviewRating">' . $stars . '<span itemprop="ratingValue" class="elementor-screen-only">' . esc_html( $rating . '/' . $scale ) . '</span></div></div>';
}

function mcupr_el_wcss_star_rating( $s, $root ) {
	$out  = mcupr_el_align_rule( $root, $s );
	$col  = mcupr_el_color_of( $s, 'stars_color' );
	$un   = mcupr_el_color_of( $s, 'stars_unmarked_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-star-rating i:before', array( '' !== $col ? 'color:' . $col : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-star-rating i', array( '' !== $un ? 'color:' . $un : '' ) );
	$out .= mcupr_el_responsive_rule( $root . ' .elementor-star-rating', $s, array( 'font-size' => array( 'icon_size', 'size' ) ) );
	return $out;
}

/* ------------------------------------------------------------------------------------ counter, progress */

function mcupr_el_w_counter( $s ) {
	$end   = mcupr_el_s( $s, 'ending_number', 100 );
	$start = mcupr_el_s( $s, 'starting_number', 0 );
	$sep   = mcupr_el_s( $s, 'thousand_separator', 'yes' );
	$sep_c = 'yes' === $sep ? ( 'default' === mcupr_el_s( $s, 'thousand_separator_char', '' ) || '' === mcupr_el_s( $s, 'thousand_separator_char', '' ) ? ',' : (string) mcupr_el_s( $s, 'thousand_separator_char', ',' ) ) : '';
	$shown = is_numeric( $end ) ? number_format( 0 + $end, 0, '.', $sep_c ) : (string) $end;
	$title = (string) mcupr_el_s( $s, 'title', '' );
	$dur   = (int) mcupr_el_s( $s, 'duration', 2000 );
	mcupr_el_need( 'counter' );
	return '<div class="elementor-counter"><div class="elementor-counter-number-wrapper"><span class="elementor-counter-number-prefix">' . esc_html( (string) mcupr_el_s( $s, 'prefix', '' ) ) . '</span><span class="elementor-counter-number" data-duration="' . (int) $dur . '" data-to-value="' . esc_attr( (string) $end ) . '" data-from-value="' . esc_attr( (string) $start ) . '" data-delimiter="' . esc_attr( $sep_c ) . '">' . esc_html( $shown ) . '</span><span class="elementor-counter-number-suffix">' . esc_html( (string) mcupr_el_s( $s, 'suffix', '' ) ) . '</span></div>' . ( '' !== $title ? '<div class="elementor-counter-title">' . esc_html( $title ) . '</div>' : '' ) . '</div>';
}

function mcupr_el_wcss_counter( $s, $root ) {
	$out  = mcupr_el_align_rule( $root, $s );
	$nc   = mcupr_el_color_of( $s, 'number_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-counter-number-wrapper', array( '' !== $nc ? 'color:' . $nc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-counter-number-wrapper', $s, 'typography_number' );
	$tc   = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-counter-title', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-counter-title', $s, 'typography_title' );
	return $out;
}

function mcupr_el_w_progress( $s ) {
	$pct   = isset( $s['percent']['size'] ) && is_numeric( $s['percent']['size'] ) ? max( 0, min( 100, (float) $s['percent']['size'] ) ) : 50;
	$title = (string) mcupr_el_s( $s, 'title', '' );
	$inner = (string) mcupr_el_s( $s, 'inner_text', '' );
	$html  = '<div class="elementor-progress-wrapper" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $pct ) . '">';
	if ( '' !== $title ) {
		$html .= '<span class="elementor-title">' . esc_html( $title ) . '</span>';
	}
	$html .= '<div class="elementor-progress-bar" data-max="' . esc_attr( $pct ) . '" style="width:' . esc_attr( $pct ) . '%">';
	if ( '' !== $inner ) {
		$html .= '<span class="elementor-progress-text">' . esc_html( $inner ) . '</span>';
	}
	if ( 'yes' === mcupr_el_s( $s, 'display_percentage', 'show' ) || 'show' === mcupr_el_s( $s, 'display_percentage', 'show' ) ) {
		$html .= '<span class="elementor-progress-percentage">' . esc_html( $pct ) . '%</span>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_progress( $s, $root ) {
	$bar  = mcupr_el_color_of( $s, 'bar_color' );
	$bg   = mcupr_el_color_of( $s, 'bar_bg_color' );
	$out  = mcupr_el_rule( $root . ' .elementor-progress-bar', array( '' !== $bar ? 'background-color:' . $bar : '', ( ! empty( $s['bar_height']['size'] ) ? 'height:' . mcupr_el_size( $s['bar_height'] ) : '' ), ( ! empty( $s['bar_height']['size'] ) ? 'line-height:' . mcupr_el_size( $s['bar_height'] ) : '' ) ) );
	$out .= mcupr_el_rule( $root . ' .elementor-progress-wrapper', array( '' !== $bg ? 'background-color:' . $bg : '' ) );
	$rd   = mcupr_el_size( isset( $s['bar_border_radius'] ) ? $s['bar_border_radius'] : null );
	$out .= mcupr_el_rule( $root . ' .elementor-progress-wrapper, ' . $root . ' .elementor-progress-bar', array( '' !== $rd ? 'border-radius:' . $rd : '' ) );
	$tc   = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-title', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-title', $s, 'typography' );
	return $out;
}

/* ---------------------------------------------------------------------------------- testimonial, alert */

function mcupr_el_w_testimonial( $s ) {
	$content = (string) mcupr_el_s( $s, 'testimonial_content', '' );
	$name    = (string) mcupr_el_s( $s, 'testimonial_name', '' );
	$job     = (string) mcupr_el_s( $s, 'testimonial_job', '' );
	$img     = mcupr_el_img( mcupr_el_s( $s, 'testimonial_image', array() ), 'thumbnail' );
	$pos     = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'testimonial_image_position', 'aside' ) );
	$html    = '<div class="elementor-testimonial-wrapper"><div class="elementor-testimonial-content">' . wp_kses_post( $content ) . '</div><div class="elementor-testimonial-meta elementor-has-image elementor-testimonial-image-position-' . esc_attr( $pos ) . '"><div class="elementor-testimonial-meta-inner">';
	if ( '' !== $img ) {
		$html .= '<div class="elementor-testimonial-image">' . $img . '</div>';
	}
	$html .= '<div class="elementor-testimonial-details"><div class="elementor-testimonial-name">' . esc_html( $name ) . '</div><div class="elementor-testimonial-job">' . esc_html( $job ) . '</div></div>';
	return $html . '</div></div></div>';
}

function mcupr_el_wcss_testimonial( $s, $root ) {
	$out  = mcupr_el_align_rule( $root . ' .elementor-testimonial-wrapper', $s, 'testimonial_alignment' );
	$cc   = mcupr_el_color_of( $s, 'content_content_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-testimonial-content', array( '' !== $cc ? 'color:' . $cc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-testimonial-content', $s, 'content_typography' );
	$nc   = mcupr_el_color_of( $s, 'name_text_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-testimonial-name', array( '' !== $nc ? 'color:' . $nc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-testimonial-name', $s, 'name_typography' );
	$jc   = mcupr_el_color_of( $s, 'job_text_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-testimonial-job', array( '' !== $jc ? 'color:' . $jc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-testimonial-job', $s, 'job_typography' );
	return $out;
}

function mcupr_el_w_alert( $s ) {
	$type  = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'alert_type', 'info' ) );
	$title = (string) mcupr_el_s( $s, 'alert_title', '' );
	$desc  = (string) mcupr_el_s( $s, 'alert_description', '' );
	$html  = '<div class="elementor-alert elementor-alert-' . esc_attr( $type ) . '" role="alert"><span class="elementor-alert-title">' . wp_kses_post( $title ) . '</span><span class="elementor-alert-description">' . wp_kses_post( $desc ) . '</span>';
	if ( 'show' === mcupr_el_s( $s, 'show_dismiss', 'show' ) ) {
		$html .= '<button type="button" class="elementor-alert-dismiss"><span aria-hidden="true">&times;</span><span class="elementor-screen-only">Dismiss alert</span></button>';
		mcupr_el_need( 'alert' );
	}
	return $html . '</div>';
}

function mcupr_el_wcss_alert( $s, $root ) {
	$d   = array();
	$bg  = mcupr_el_color_of( $s, 'background' );
	$bc  = mcupr_el_color_of( $s, 'border_color' );
	$out = mcupr_el_rule( $root . ' .elementor-alert', array( '' !== $bg ? 'background-color:' . $bg : '', '' !== $bc ? 'border-color:' . $bc : '' ) );
	$tc  = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-alert-title', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-alert-title', $s, 'alert_title_typography' );
	$dc  = mcupr_el_color_of( $s, 'description_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-alert-description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-alert-description', $s, 'alert_description_typography' );
	return $out;
}

/* ------------------------------------------------------------------------- tabs, accordion, toggle */

function mcupr_el_tab_ids( $el, $i ) {
	$base = isset( $el['id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $el['id'] ) : 'x';
	return array( 'elementor-tab-title-' . $base . $i, 'elementor-tab-content-' . $base . $i );
}

function mcupr_el_w_tabs( $s, $el ) {
	$items    = mcupr_el_s( $s, 'tabs', array() );
	$vertical = 'vertical' === mcupr_el_s( $s, 'type', 'horizontal' );
	if ( ! $items ) {
		return '';
	}
	mcupr_el_need( 'tabs' );
	$titles  = '';
	$content = '';
	foreach ( array_values( (array) $items ) as $n => $it ) {
		$i = $n + 1;
		list( $tid, $cid ) = mcupr_el_tab_ids( $el, $i );
		$title = isset( $it['tab_title'] ) ? (string) $it['tab_title'] : '';
		$titles .= '<div id="' . esc_attr( $tid ) . '" class="elementor-tab-title elementor-tab-desktop-title" data-tab="' . $i . '" role="tab" aria-controls="' . esc_attr( $cid ) . '" aria-selected="' . ( 1 === $i ? 'true' : 'false' ) . '" tabindex="' . ( 1 === $i ? '0' : '-1' ) . '">' . wp_kses_post( $title ) . '</div>';
		$content .= '<div class="elementor-tab-title elementor-tab-mobile-title" data-tab="' . $i . '" role="tab" aria-controls="' . esc_attr( $cid ) . '" tabindex="-1">' . wp_kses_post( $title ) . '</div><div id="' . esc_attr( $cid ) . '" class="elementor-tab-content elementor-clearfix" data-tab="' . $i . '" role="tabpanel" aria-labelledby="' . esc_attr( $tid ) . '"' . ( 1 === $i ? '' : ' hidden' ) . '>' . mcupr_el_rich( isset( $it['tab_content'] ) ? (string) $it['tab_content'] : '' ) . '</div>';
	}
	return '<div class="elementor-tabs"><div class="elementor-tabs-wrapper" role="tablist" aria-orientation="' . ( $vertical ? 'vertical' : 'horizontal' ) . '">' . $titles . '</div><div class="elementor-tabs-content-wrapper">' . $content . '</div></div>';
}

function mcupr_el_wcss_tabs( $s, $root ) {
	$out = '';
	$tc  = mcupr_el_color_of( $s, 'tab_color' );
	$ac  = mcupr_el_color_of( $s, 'tab_active_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-tab-title', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-tab-title.elementor-active', array( '' !== $ac ? 'color:' . $ac : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-tab-title', $s, 'tab_typography' );
	$cc  = mcupr_el_color_of( $s, 'content_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-tab-content', array( '' !== $cc ? 'color:' . $cc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-tab-content', $s, 'content_typography' );
	$bc  = mcupr_el_color_of( $s, 'border_color' );
	$bw  = mcupr_el_size( isset( $s['border_width'] ) ? $s['border_width'] : null );
	$out .= mcupr_el_rule( $root . ' .elementor-tab-title, ' . $root . ' .elementor-tab-title:before, ' . $root . ' .elementor-tab-title:after, ' . $root . ' .elementor-tab-content, ' . $root . ' .elementor-tabs-content-wrapper', array( '' !== $bc ? 'border-color:' . $bc : '', '' !== $bw ? 'border-width:' . $bw : '' ) );
	$bg  = mcupr_el_color_of( $s, 'background_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-tab-title.elementor-active, ' . $root . ' .elementor-tab-content', array( '' !== $bg ? 'background-color:' . $bg : '' ) );
	return $out;
}

function mcupr_el_acc_icon( $s ) {
	$closed = mcupr_el_icon( mcupr_el_s( $s, 'selected_icon', array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ) ) );
	$open   = mcupr_el_icon( mcupr_el_s( $s, 'selected_active_icon', array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ) ) );
	if ( '' === $closed ) {
		$closed = mcupr_el_icon( array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ) );
	}
	if ( '' === $open ) {
		$open = mcupr_el_icon( array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ) );
	}
	$al = 'right' === mcupr_el_s( $s, 'icon_align', 'left' ) ? 'right' : 'left';
	return array( '<span class="elementor-accordion-icon elementor-accordion-icon-' . $al . '" aria-hidden="true"><span class="elementor-accordion-icon-closed">' . $closed . '</span><span class="elementor-accordion-icon-opened">' . $open . '</span></span>', $al );
}

function mcupr_el_w_accordion( $s, $el ) {
	$items = mcupr_el_s( $s, 'tabs', array() );
	if ( ! $items ) {
		return '';
	}
	mcupr_el_need( 'accordion' );
	list( $icon ) = mcupr_el_acc_icon( $s );
	$tag  = mcupr_el_tag( mcupr_el_s( $s, 'title_html_tag', 'div' ), 'div' );
	$html = '<div class="elementor-accordion">';
	foreach ( array_values( (array) $items ) as $n => $it ) {
		$i = $n + 1;
		list( $tid, $cid ) = mcupr_el_tab_ids( $el, $i );
		$html .= '<div class="elementor-accordion-item"><' . $tag . ' id="' . esc_attr( $tid ) . '" class="elementor-tab-title" data-tab="' . $i . '" role="button" aria-controls="' . esc_attr( $cid ) . '" aria-expanded="false" tabindex="0">' . $icon . '<a class="elementor-accordion-title" tabindex="0">' . wp_kses_post( isset( $it['tab_title'] ) ? (string) $it['tab_title'] : '' ) . '</a></' . $tag . '><div id="' . esc_attr( $cid ) . '" class="elementor-tab-content elementor-clearfix" data-tab="' . $i . '" role="region" aria-labelledby="' . esc_attr( $tid ) . '" hidden>' . mcupr_el_rich( isset( $it['tab_content'] ) ? (string) $it['tab_content'] : '' ) . '</div></div>';
	}
	return $html . '</div>';
}

function mcupr_el_wcss_accordion( $s, $root ) {
	$out  = mcupr_el_wcss_tabs( $s, $root );
	$ic   = mcupr_el_color_of( $s, 'icon_color' );
	$iac  = mcupr_el_color_of( $s, 'icon_active_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-accordion-icon, ' . $root . ' .elementor-accordion-icon i', array( '' !== $ic ? 'color:' . $ic : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-active .elementor-accordion-icon, ' . $root . ' .elementor-active .elementor-accordion-icon i', array( '' !== $iac ? 'color:' . $iac : '' ) );
	$out .= mcupr_el_responsive_rule( $root . ' .elementor-accordion-icon', $s, array( 'width' => array( 'icon_space', 'size' ) ) );
	$out .= mcupr_el_rule( $root . ' .elementor-accordion .elementor-accordion-title', array( ( $c = mcupr_el_color_of( $s, 'title_color' ) ) ? 'color:' . $c : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-accordion .elementor-active .elementor-accordion-title', array( ( $c = mcupr_el_color_of( $s, 'tab_active_color' ) ) ? 'color:' . $c : '' ) );
	return $out;
}

function mcupr_el_w_toggle( $s, $el ) {
	$items = mcupr_el_s( $s, 'tabs', array() );
	if ( ! $items ) {
		return '';
	}
	mcupr_el_need( 'toggle' );
	list( $icon ) = mcupr_el_acc_icon( $s );
	$icon = str_replace( 'elementor-accordion-icon', 'elementor-toggle-icon', $icon );
	$tag  = mcupr_el_tag( mcupr_el_s( $s, 'title_html_tag', 'div' ), 'div' );
	$html = '<div class="elementor-toggle">';
	foreach ( array_values( (array) $items ) as $n => $it ) {
		$i = $n + 1;
		list( $tid, $cid ) = mcupr_el_tab_ids( $el, $i );
		$html .= '<div class="elementor-toggle-item"><' . $tag . ' id="' . esc_attr( $tid ) . '" class="elementor-tab-title" data-tab="' . $i . '" role="button" aria-controls="' . esc_attr( $cid ) . '" aria-expanded="false" tabindex="0">' . $icon . '<a class="elementor-toggle-title" tabindex="0">' . wp_kses_post( isset( $it['tab_title'] ) ? (string) $it['tab_title'] : '' ) . '</a></' . $tag . '><div id="' . esc_attr( $cid ) . '" class="elementor-tab-content elementor-clearfix" data-tab="' . $i . '" role="region" aria-labelledby="' . esc_attr( $tid ) . '" hidden>' . mcupr_el_rich( isset( $it['tab_content'] ) ? (string) $it['tab_content'] : '' ) . '</div></div>';
	}
	return $html . '</div>';
}

function mcupr_el_wcss_toggle( $s, $root ) {
	return mcupr_el_wcss_accordion( $s, $root );
}

/* ------------------------------------------------------------------- html, shortcode, anchor, video, map */

function mcupr_el_w_html( $s ) {
	return '<div class="elementor-custom-html">' . (string) mcupr_el_s( $s, 'html', '' ) . '</div>';
}

function mcupr_el_w_shortcode( $s ) {
	return '<div class="elementor-shortcode">' . do_shortcode( shortcode_unautop( (string) mcupr_el_s( $s, 'shortcode', '' ) ) ) . '</div>';
}

function mcupr_el_w_menu_anchor( $s ) {
	$a = (string) mcupr_el_s( $s, 'anchor', '' );
	return '<div id="' . esc_attr( preg_replace( '/[^A-Za-z0-9_\-]/', '', str_replace( ' ', '_', $a ) ) ) . '" class="elementor-menu-anchor"></div>';
}

function mcupr_el_w_google_maps( $s ) {
	$addr = (string) mcupr_el_s( $s, 'address', '' );
	if ( '' === $addr ) {
		return '';
	}
	$zoom = isset( $s['zoom']['size'] ) && is_numeric( $s['zoom']['size'] ) ? (int) $s['zoom']['size'] : 10;
	$src  = 'https://maps.google.com/maps?q=' . rawurlencode( $addr ) . '&t=m&z=' . $zoom . '&output=embed&iwloc=near';
	return '<div class="elementor-custom-embed"><iframe loading="lazy" src="' . esc_url( $src ) . '" title="' . esc_attr( $addr ) . '" aria-label="' . esc_attr( $addr ) . '"></iframe></div>';
}

function mcupr_el_wcss_google_maps( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$h = mcupr_el_size( mcupr_el_get( $s, 'height', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' iframe', array( '' !== $h ? 'height:' . $h : '' ) ) );
	}
	return $out;
}

/** A YouTube / Vimeo / Dailymotion address → the embed address; "" when it is not one. */
function mcupr_el_video_embed( $type, $url, $s ) {
	$url = (string) $url;
	if ( 'youtube' === $type && preg_match( '#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|v=)([A-Za-z0-9_\-]{11})#', $url, $m ) ) {
		$host = 'yes' === mcupr_el_s( $s, 'yt_privacy', '' ) ? 'www.youtube-nocookie.com' : 'www.youtube.com';
		$q    = array( 'feature' => 'oembed', 'rel' => 'yes' === mcupr_el_s( $s, 'rel', '' ) ? '1' : '0' );
		if ( 'yes' === mcupr_el_s( $s, 'autoplay', '' ) ) {
			$q['autoplay'] = 1;
		}
		if ( 'yes' === mcupr_el_s( $s, 'mute', '' ) ) {
			$q['mute'] = 1;
		}
		if ( 'yes' === mcupr_el_s( $s, 'loop', '' ) ) {
			$q['loop'] = 1;
			$q['playlist'] = $m[1];
		}
		if ( 'yes' !== mcupr_el_s( $s, 'controls', 'yes' ) && '' !== mcupr_el_s( $s, 'controls', 'yes' ) ) {
			$q['controls'] = 0;
		}
		if ( ! empty( $s['start'] ) && is_numeric( $s['start'] ) ) {
			$q['start'] = (int) $s['start'];
		}
		return 'https://' . $host . '/embed/' . $m[1] . '?' . http_build_query( $q );
	}
	if ( 'vimeo' === $type && preg_match( '#vimeo\.com/(?:video/)?(\d+)#', $url, $m ) ) {
		$q = array();
		if ( 'yes' === mcupr_el_s( $s, 'vimeo_autoplay', mcupr_el_s( $s, 'autoplay', '' ) ) ) {
			$q['autoplay'] = 1;
		}
		return 'https://player.vimeo.com/video/' . $m[1] . ( $q ? '?' . http_build_query( $q ) : '' );
	}
	if ( 'dailymotion' === $type && preg_match( '#dailymotion\.com/video/([A-Za-z0-9]+)#', $url, $m ) ) {
		return 'https://www.dailymotion.com/embed/video/' . $m[1];
	}
	return '';
}

function mcupr_el_w_video( $s ) {
	$type   = (string) mcupr_el_s( $s, 'video_type', 'youtube' );
	$ratio  = preg_replace( '/[^0-9]/', '', (string) mcupr_el_s( $s, 'aspect_ratio', '169' ) );
	$ratio  = '' === $ratio ? '169' : $ratio;
	$overlay = 'yes' === mcupr_el_s( $s, 'show_image_overlay', '' ) ? mcupr_el_s( $s, 'image_overlay', array() ) : array();
	if ( 'hosted' === $type ) {
		$h = mcupr_el_s( $s, 'hosted_url', array() );
		if ( empty( $h['url'] ) ) {
			return '';
		}
		$attrs = ' controls' . ( 'yes' === mcupr_el_s( $s, 'autoplay', '' ) ? ' autoplay muted' : '' ) . ( 'yes' === mcupr_el_s( $s, 'loop', '' ) ? ' loop' : '' ) . ( 'yes' === mcupr_el_s( $s, 'mute', '' ) ? ' muted' : '' );
		return '<div class="elementor-wrapper elementor-open-inline"><video class="elementor-video" src="' . esc_url( $h['url'] ) . '"' . $attrs . ' playsinline></video></div>';
	}
	$url = (string) mcupr_el_s( $s, $type . '_url', '' );
	$src = mcupr_el_video_embed( $type, $url, $s );
	if ( '' === $src ) {
		return '';
	}
	$frame = '<iframe class="elementor-video-iframe" src="' . esc_url( $src ) . '" title="' . esc_attr( ucfirst( $type ) . ' video' ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>';
	if ( ! empty( $overlay['url'] ) ) {
		mcupr_el_need( 'video' );
		$play = 'yes' === mcupr_el_s( $s, 'show_play_icon', 'yes' ) ? '<div class="elementor-custom-embed-play" role="button" tabindex="0" aria-label="Play"><i class="eicon-play" aria-hidden="true">&#9654;</i></div>' : '';
		return '<div class="elementor-wrapper elementor-fit-aspect-ratio elementor-aspect-ratio-' . esc_attr( $ratio ) . ' elementor-open-inline" data-src="' . esc_attr( $src ) . '"><div class="elementor-custom-embed-image-overlay" style="background-image:url(' . esc_url( $overlay['url'] ) . ')">' . $play . '</div></div>';
	}
	return '<div class="elementor-wrapper elementor-fit-aspect-ratio elementor-aspect-ratio-' . esc_attr( $ratio ) . ' elementor-open-inline">' . $frame . '</div>';
}

/* ---------------------------------------------------------------------------- galleries (image) */

function mcupr_el_w_image_gallery( $s ) {
	$gal  = mcupr_el_s( $s, 'wp_gallery', array() );
	if ( ! $gal ) {
		return '';
	}
	$cols = max( 1, min( 9, (int) mcupr_el_s( $s, 'gallery_columns', 4 ) ) );
	$size = (string) mcupr_el_s( $s, 'thumbnail_size', 'thumbnail' );
	$link = (string) mcupr_el_s( $s, 'gallery_link', 'file' );
	$html = '<div class="elementor-image-gallery"><div class="gallery gallery-columns-' . $cols . ' gallery-size-' . esc_attr( $size ) . '">';
	foreach ( (array) $gal as $g ) {
		$id  = isset( $g['id'] ) ? (int) $g['id'] : 0;
		$img = mcupr_el_img( $g, $size );
		if ( '' === $img ) {
			continue;
		}
		$url = $id ? (string) wp_get_attachment_url( $id ) : ( isset( $g['url'] ) ? $g['url'] : '' );
		if ( 'file' === $link ) {
			mcupr_el_need( 'lightbox' );
			$img = '<a href="' . esc_url( $url ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="all-' . (int) $id . '">' . $img . '</a>';
		} elseif ( 'attachment' === $link && $id ) {
			$img = '<a href="' . esc_url( get_attachment_link( $id ) ) . '">' . $img . '</a>';
		}
		$html .= '<figure class="gallery-item"><div class="gallery-icon">' . $img . '</div></figure>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_image_gallery( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$g = mcupr_el_size( mcupr_el_get( $s, 'image_spacing_custom', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .gallery-item', array( '' !== $g ? 'padding:' . ( (float) $s['image_spacing_custom']['size'] / 2 ) . 'px' : '' ) ) );
	}
	return $out;
}

function mcupr_el_w_image_carousel( $s ) {
	$car = mcupr_el_s( $s, 'carousel', array() );
	if ( ! $car ) {
		return '';
	}
	mcupr_el_need( 'carousel' );
	$show = max( 1, min( 10, (int) mcupr_el_s( $s, 'slides_to_show', 3 ) ) );
	$size = (string) mcupr_el_s( $s, 'thumbnail_size', 'medium' );
	$html = '<div class="elementor-image-carousel-wrapper swiper-container" dir="ltr" data-show="' . $show . '" data-autoplay="' . ( 'yes' === mcupr_el_s( $s, 'autoplay', 'yes' ) ? '1' : '0' ) . '" data-speed="' . (int) mcupr_el_s( $s, 'autoplay_speed', 5000 ) . '"><div class="elementor-image-carousel swiper-wrapper">';
	foreach ( (array) $car as $c ) {
		$img = mcupr_el_img( $c, $size );
		if ( '' !== $img ) {
			$html .= '<div class="swiper-slide"><figure class="swiper-slide-inner">' . $img . '</figure></div>';
		}
	}
	return $html . '</div></div>';
}

function mcupr_el_w_sidebar( $s ) {
	$id = (string) mcupr_el_s( $s, 'sidebar', '' );
	if ( '' === $id || ! is_registered_sidebar( $id ) ) {
		return false;
	}
	ob_start();
	dynamic_sidebar( $id );
	return (string) ob_get_clean();
}

function mcupr_el_w_read_more( $s ) {
	$text = (string) mcupr_el_s( $s, 'text', 'Read More »' );
	return '<a class="elementor-button-link" href="' . esc_url( get_permalink() ) . '">' . esc_html( $text ) . '</a>';
}

/** A template of the library by its id ("Template" widget and the [elementor-template] shortcode). */
function mcupr_el_w_template( $s ) {
	$id = (int) mcupr_el_s( $s, 'template_id', 0 );
	return $id ? mcupr_el_render_template( $id ) : '';
}
