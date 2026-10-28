<?php
/**
 * Elementor 4 "atomic" elements, printed without the plugin.
 *
 * Elementor 4 saves a page differently from the section / column / widget way: a block is "e-div-block" or "e-flexbox", the widgets are
 * "e-heading", "e-paragraph", "e-button", "e-image", "e-svg", the settings are typed values ({"$$type":"size","value":{"size":48,"unit":"px"}})
 * and the look is not in the settings but in "styles": classes with variants for a breakpoint and a state. This file reads that:
 * the typed values, the styles (as CSS, scoped to the class of the element), the blocks and the five widgets. What it does not know
 * (a property, a type, a widget of its own) is left out of the style or reported like any widget the theme cannot draw; the data is never changed.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** A typed value of Elementor 4 as plain PHP (string, number, array of sizes ...). Anything else comes through as it is. */
function mcupr_el_at( $v ) {
	if ( ! is_array( $v ) || ! isset( $v['$$type'] ) ) {
		return $v;
	}
	$val = isset( $v['value'] ) ? $v['value'] : null;
	switch ( $v['$$type'] ) {
		case 'size':
			if ( is_array( $val ) && isset( $val['size'] ) ) {
				$u = isset( $val['unit'] ) ? (string) $val['unit'] : 'px';
				return 'auto' === $u ? 'auto' : ( 'custom' === $u ? (string) $val['size'] : $val['size'] . $u );
			}
			return '';
		case 'classes':
			return is_array( $val ) ? array_map( 'strval', array_filter( $val, 'is_scalar' ) ) : array();
		default:
			if ( is_array( $val ) ) {
				$o = array();
				foreach ( $val as $k => $x ) {
					$o[ $k ] = mcupr_el_at( $x );
				}
				return $o;
			}
			return $val;
	}
}

/** A value that is safe inside a CSS declaration (no way out of it, no url() to anywhere, no script). */
function mcupr_el_at_safe( $s ) {
	$s = (string) $s;
	if ( preg_match( '/[;{}<>\\\\@]|url\s*\(|expression\s*\(|javascript:|\/\*/i', $s ) ) {
		return '';
	}
	return trim( $s );
}

/** Four sides of a "dimensions" value (logical names) as the CSS shorthand order top right bottom left. */
function mcupr_el_at_sides( $d ) {
	if ( ! is_array( $d ) ) {
		return (string) $d;
	}
	$map = array( 'block-start' => 'top', 'inline-end' => 'right', 'block-end' => 'bottom', 'inline-start' => 'left' );
	$out = array();
	foreach ( $map as $k => $side ) {
		$out[ $side ] = isset( $d[ $k ] ) && '' !== $d[ $k ] ? $d[ $k ] : '0';
	}
	return $out['top'] . ' ' . $out['right'] . ' ' . $out['bottom'] . ' ' . $out['left'];
}

/** The CSS declarations of one variant's props. */
function mcupr_el_at_decl( $props ) {
	$css    = '';
	$simple = array( 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'color', 'text-align', 'text-transform', 'text-decoration', 'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height', 'display', 'flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink', 'flex-basis', 'justify-content', 'align-items', 'align-self', 'align-content', 'gap', 'row-gap', 'column-gap', 'position', 'z-index', 'opacity', 'overflow', 'object-fit', 'aspect-ratio', 'border-width', 'border-style', 'border-color', 'inset-block-start', 'inset-block-end', 'inset-inline-start', 'inset-inline-end', 'order', 'cursor', 'white-space' );
	foreach ( (array) $props as $p => $raw ) {
		$p = (string) $p;
		$v = mcupr_el_at( $raw );
		if ( 'font-family' === $p ) {
			$f = mcupr_el_at_safe( is_string( $v ) ? $v : '' );
			if ( '' !== $f ) {
				$css .= 'font-family:"' . str_replace( '"', '', $f ) . '",sans-serif;';
			}
		} elseif ( in_array( $p, array( 'padding', 'margin' ), true ) ) {
			$x = mcupr_el_at_safe( mcupr_el_at_sides( $v ) );
			if ( '' !== $x ) {
				$css .= $p . ':' . $x . ';';
			}
		} elseif ( 'border-radius' === $p ) {
			if ( is_array( $v ) ) {
				$m = array( 'start-start' => 'border-start-start-radius', 'start-end' => 'border-start-end-radius', 'end-end' => 'border-end-end-radius', 'end-start' => 'border-end-start-radius' );
				foreach ( $m as $k => $prop ) {
					if ( isset( $v[ $k ] ) && '' !== mcupr_el_at_safe( $v[ $k ] ) ) {
						$css .= $prop . ':' . mcupr_el_at_safe( $v[ $k ] ) . ';';
					}
				}
			} elseif ( '' !== mcupr_el_at_safe( $v ) ) {
				$css .= 'border-radius:' . mcupr_el_at_safe( $v ) . ';';
			}
		} elseif ( 'background' === $p ) {
			if ( is_array( $v ) && ! empty( $v['color'] ) && '' !== mcupr_el_at_safe( $v['color'] ) ) {
				$css .= 'background-color:' . mcupr_el_at_safe( $v['color'] ) . ';';
			}
			if ( is_array( $v ) && ! empty( $v['background-overlay'] ) && is_array( $v['background-overlay'] ) ) {
				foreach ( $v['background-overlay'] as $ov ) {
					$img = is_array( $ov ) && isset( $ov['image']['src'] ) ? $ov['image']['src'] : array();
					$url = is_array( $img ) && ! empty( $img['id'] ) ? wp_get_attachment_image_url( (int) $img['id'], 'full' ) : '';
					$url = $url ? $url : ( is_array( $img ) && ! empty( $img['url'] ) ? $img['url'] : '' );
					if ( $url && wp_http_validate_url( $url ) && preg_match( '#^https?://#i', $url ) ) {
						$css .= 'background-image:url("' . esc_url_raw( $url ) . '");background-size:cover;background-position:center;';
						break;
					}
				}
			}
		} elseif ( 'box-shadow' === $p ) {
			$parts = array();
			foreach ( is_array( $v ) ? $v : array() as $sh ) {
				if ( ! is_array( $sh ) ) {
					continue;
				}
				$x = mcupr_el_at_safe( ( isset( $sh['hOffset'] ) ? $sh['hOffset'] : '0px' ) . ' ' . ( isset( $sh['vOffset'] ) ? $sh['vOffset'] : '0px' ) . ' ' . ( isset( $sh['blur'] ) ? $sh['blur'] : '0px' ) . ' ' . ( isset( $sh['spread'] ) ? $sh['spread'] : '0px' ) . ' ' . ( isset( $sh['color'] ) ? $sh['color'] : 'rgba(0,0,0,.5)' ) );
				if ( '' !== $x ) {
					$parts[] = ( ! empty( $sh['position'] ) && 'inset' === $sh['position'] ? 'inset ' : '' ) . $x;
				}
			}
			if ( $parts ) {
				$css .= 'box-shadow:' . implode( ',', $parts ) . ';';
			}
		} elseif ( in_array( $p, $simple, true ) && ( is_scalar( $v ) ) ) {
			$x = mcupr_el_at_safe( $v );
			if ( '' !== $x ) {
				$css .= $p . ':' . $x . ';';
			}
		}
	}
	return $css;
}

/** The "styles" of an element as CSS: each class, each variant (breakpoint, state), scoped to the class. */
function mcupr_el_at_css( $el ) {
	$out = '';
	$bp  = array( 'desktop' => '', 'laptop' => '@media(max-width:1366px)', 'tablet_extra' => '@media(max-width:1200px)', 'tablet' => '@media(max-width:1024px)', 'mobile_extra' => '@media(max-width:880px)', 'mobile' => '@media(max-width:767px)', 'widescreen' => '@media(min-width:2400px)' );
	foreach ( isset( $el['styles'] ) && is_array( $el['styles'] ) ? $el['styles'] : array() as $cid => $cl ) {
		$class = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $cid );
		if ( '' === $class || ! is_array( $cl ) || empty( $cl['variants'] ) ) {
			continue;
		}
		foreach ( (array) $cl['variants'] as $var ) {
			$meta  = isset( $var['meta'] ) && is_array( $var['meta'] ) ? $var['meta'] : array();
			$b     = isset( $meta['breakpoint'] ) && isset( $bp[ $meta['breakpoint'] ] ) ? $meta['breakpoint'] : ( empty( $meta['breakpoint'] ) ? 'desktop' : '' );
			$state = isset( $meta['state'] ) && is_string( $meta['state'] ) && preg_match( '/^(hover|focus|active|focus-visible)$/', $meta['state'] ) ? ':' . $meta['state'] : '';
			if ( '' === $b || ( ! empty( $meta['state'] ) && '' === $state ) ) {
				continue;
			}
			$decl = mcupr_el_at_decl( isset( $var['props'] ) ? $var['props'] : array() );
			if ( '' === $decl ) {
				continue;
			}
			$rule = '.elementor .' . $class . $state . '{' . $decl . '}';
			$out .= '' === $bp[ $b ] ? $rule : $bp[ $b ] . '{' . $rule . '}';
		}
	}
	return $out;
}

/** The fonts the styles name (so that they are delivered like the fonts of the old kind of settings). */
function mcupr_el_at_fonts( $el ) {
	foreach ( isset( $el['styles'] ) && is_array( $el['styles'] ) ? $el['styles'] : array() as $cl ) {
		foreach ( isset( $cl['variants'] ) && is_array( $cl['variants'] ) ? $cl['variants'] : array() as $var ) {
			if ( isset( $var['props']['font-family'] ) ) {
				$f = mcupr_el_at( $var['props']['font-family'] );
				if ( is_string( $f ) && '' !== $f && ! mcupr_el_is_system_font( $f ) ) {
					$r                  = &mcupr_el_state_ref();
					$r['fonts'][ $f ] = true;
				}
			}
		}
	}
}

/** The class attribute of an atomic element: its base class, the style classes, the global ones. */
function mcupr_el_at_classes( $s, $base ) {
	$c = isset( $s['classes'] ) ? mcupr_el_at( $s['classes'] ) : array();
	$c = is_array( $c ) ? $c : array();
	$c = array_map( static function ( $x ) { return preg_replace( '/[^A-Za-z0-9_\-]/', '', $x ); }, $c );
	return esc_attr( trim( $base . ' ' . implode( ' ', array_filter( $c ) ) ) );
}

/** A link setting: array( url, blank ) or null. */
function mcupr_el_at_link( $s ) {
	if ( empty( $s['link'] ) ) {
		return null;
	}
	$l = mcupr_el_at( $s['link'] );
	if ( is_string( $l ) ) {
		return array( esc_url( $l ), false );
	}
	if ( ! is_array( $l ) ) {
		return null;
	}
	$d = isset( $l['destination'] ) ? $l['destination'] : ( isset( $l['url'] ) ? $l['url'] : '' );
	if ( is_array( $d ) ) {
		$d = isset( $d['url'] ) ? $d['url'] : ( isset( $d['id'] ) ? get_permalink( (int) $d['id'] ) : '' );
	}
	$u = esc_url( (string) $d );
	return '' === $u ? null : array( $u, ! empty( $l['isTargetBlank'] ) || ( isset( $l['target'] ) && '_blank' === $l['target'] ) );
}

function mcupr_el_at_wrap_link( $inner, $link ) {
	return $link ? '<a href="' . $link[0] . '"' . ( $link[1] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $inner . '</a>' : $inner;
}

/** e-div-block / e-flexbox: a box with its children. */
function mcupr_el_render_atomic_block( $el, $post_id, $parent ) {
	$s    = $el['settings'];
	$tag  = isset( $s['tag'] ) ? mcupr_el_at( $s['tag'] ) : 'div';
	$tag  = in_array( $tag, array( 'div', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'section', 'a' ), true ) && 'a' !== $tag ? $tag : 'div';
	$kind = 'e-flexbox' === $el['elType'] ? 'e-flexbox-base' : 'e-div-block-base';
	mcupr_el_collect_css( mcupr_el_at_css( $el ) );
	mcupr_el_at_fonts( $el );
	$child = $parent && isset( $parent['elType'] ) && in_array( $parent['elType'], array( 'e-div-block', 'e-flexbox', 'container' ), true );
	return '<' . $tag . ' class="elementor-element elementor-element-' . esc_attr( $el['id'] ) . ' e-con e-atomic-element ' . ( $child ? 'e-child ' : 'e-parent ' ) . mcupr_el_at_classes( $s, $kind ) . '" data-id="' . esc_attr( $el['id'] ) . '" data-element_type="' . esc_attr( $el['elType'] ) . '">' . mcupr_el_render_list( $el['elements'], $post_id, $el ) . '</' . $tag . '>';
}

/** The style of an atomic widget (the widget wrapper calls this for every widget type). */
function mcupr_el_at_widget_css( $s, $root, $el ) {
	mcupr_el_at_fonts( $el );
	return mcupr_el_at_css( $el );
}
function mcupr_el_wcss_e_heading( $s, $root, $el ) { return mcupr_el_at_widget_css( $s, $root, $el ); }
function mcupr_el_wcss_e_paragraph( $s, $root, $el ) { return mcupr_el_at_widget_css( $s, $root, $el ); }
function mcupr_el_wcss_e_button( $s, $root, $el ) { return mcupr_el_at_widget_css( $s, $root, $el ); }
function mcupr_el_wcss_e_image( $s, $root, $el ) { return mcupr_el_at_widget_css( $s, $root, $el ); }
function mcupr_el_wcss_e_svg( $s, $root, $el ) { return mcupr_el_at_widget_css( $s, $root, $el ); }

function mcupr_el_w_e_heading( $s ) {
	$tag = isset( $s['tag'] ) ? mcupr_el_at( $s['tag'] ) : 'h2';
	$tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' ), true ) ? $tag : 'h2';
	$t   = isset( $s['title'] ) ? mcupr_el_at( $s['title'] ) : '';
	$in  = wp_kses( is_string( $t ) ? $t : '', array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(), 'br' => array(), 'span' => array(), 'sup' => array(), 'sub' => array() ) );
	return '<' . $tag . ' class="' . mcupr_el_at_classes( $s, 'e-heading-base' ) . '">' . mcupr_el_at_wrap_link( $in, mcupr_el_at_link( $s ) ) . '</' . $tag . '>';
}

function mcupr_el_w_e_paragraph( $s ) {
	$t  = isset( $s['paragraph'] ) ? mcupr_el_at( $s['paragraph'] ) : '';
	$in = wp_kses( is_string( $t ) ? $t : '', array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'b' => array(), 'strong' => array(), 'i' => array(), 'em' => array(), 'u' => array(), 'br' => array(), 'span' => array(), 'sup' => array(), 'sub' => array() ) );
	return '<p class="' . mcupr_el_at_classes( $s, 'e-paragraph-base' ) . '">' . $in . '</p>';
}

function mcupr_el_w_e_button( $s ) {
	$t  = isset( $s['text'] ) ? mcupr_el_at( $s['text'] ) : '';
	$t  = esc_html( wp_strip_all_tags( is_string( $t ) ? $t : '' ) );
	$l  = mcupr_el_at_link( $s );
	$cl = mcupr_el_at_classes( $s, 'e-button-base' );
	if ( $l ) {
		return '<a class="' . $cl . '" href="' . $l[0] . '"' . ( $l[1] ? ' target="_blank" rel="noopener"' : '' ) . '>' . $t . '</a>';
	}
	return '<button type="button" class="' . $cl . '">' . $t . '</button>';
}

/** The address of a picture setting ("image-src": an attachment, or an address), and its description. */
function mcupr_el_at_src( $src ) {
	$src = mcupr_el_at( $src );
	$id  = is_array( $src ) && ! empty( $src['id'] ) ? (int) $src['id'] : 0;
	$url = $id ? wp_get_attachment_image_url( $id, 'full' ) : '';
	$url = $url ? $url : ( is_array( $src ) && ! empty( $src['url'] ) ? (string) $src['url'] : '' );
	return array( preg_match( '#^https?://#i', $url ) || 0 === strpos( $url, '/' ) ? esc_url( $url ) : '', $id );
}

function mcupr_el_w_e_image( $s ) {
	$im = isset( $s['image'] ) ? mcupr_el_at( $s['image'] ) : array();
	list( $url, $id ) = mcupr_el_at_src( isset( $im['src'] ) ? $im['src'] : array() );
	if ( '' === $url ) {
		return false;
	}
	$alt = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
	$img = '<img class="' . mcupr_el_at_classes( $s, 'e-image-base' ) . '" src="' . $url . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	return mcupr_el_at_wrap_link( $img, mcupr_el_at_link( $s ) );
}

function mcupr_el_w_e_svg( $s ) {
	list( $url, $id ) = mcupr_el_at_src( isset( $s['svg'] ) ? $s['svg'] : array() );
	if ( '' === $url ) {
		return false;
	}
	return '<img class="' . mcupr_el_at_classes( $s, 'e-svg-base' ) . '" src="' . $url . '" alt="" loading="lazy" decoding="async">'; // as a picture: an SVG file is never put into the page itself
}
