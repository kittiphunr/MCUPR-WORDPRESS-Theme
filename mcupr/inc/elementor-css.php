<?php
/**
 * Elementor pages without Elementor, part 2: the style of every element, written as CSS from its saved settings.
 *
 * Elementor keeps the look of an element in its settings (padding, colours, typography, backgrounds, borders, …, each with a tablet and
 * a mobile value) and writes it as CSS when the page is saved. When that file is still in uploads/elementor/css/ the theme uses it as it
 * is (the pages then look as before); when it is not, this file writes the same rules again from the settings.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Breakpoints of Elementor: tablet up to 1024 px, mobile up to 767 px. */
function mcupr_el_devices() {
	return array(
		''       => '',
		'tablet' => '(max-width:1024px)',
		'mobile' => '(max-width:767px)',
	);
}

/** A setting for a device: key, key_tablet, key_mobile. */
function mcupr_el_get( $s, $key, $dev = '' ) {
	$k = '' === $dev ? $key : $key . '_' . $dev;
	return isset( $s[ $k ] ) ? $s[ $k ] : null;
}

function mcupr_el_has( $v ) {
	if ( null === $v || '' === $v || false === $v ) {
		return false;
	}
	if ( is_array( $v ) ) {
		if ( isset( $v['size'] ) ) {
			return '' !== $v['size'] && null !== $v['size'];
		}
		if ( isset( $v['url'] ) ) {
			return '' !== $v['url'];
		}
		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
			if ( isset( $v[ $side ] ) && '' !== $v[ $side ] ) {
				return true;
			}
		}
		return ! empty( $v );
	}
	return true;
}

/** { size, unit } → "12px" ("" when empty). */
function mcupr_el_size( $v, $default_unit = 'px' ) {
	if ( ! is_array( $v ) || ! isset( $v['size'] ) || '' === $v['size'] || null === $v['size'] ) {
		return '';
	}
	$unit = isset( $v['unit'] ) && '' !== $v['unit'] ? $v['unit'] : $default_unit;
	if ( 'custom' === $unit ) {
		return preg_match( '/^[\w\s().,%+\-*\/#]+$/', (string) $v['size'] ) ? (string) $v['size'] : '';
	}
	if ( ! is_numeric( $v['size'] ) ) {
		return '';
	}
	return ( 0 + $v['size'] ) . ( preg_match( '/^(px|em|rem|%|vh|vw|vmin|vmax|deg|s|ms|fr|ch)$/', $unit ) ? $unit : 'px' );
}

/** { top, right, bottom, left, unit } → "1px 2px 3px 4px" ("" when empty). */
function mcupr_el_dim( $v ) {
	if ( ! is_array( $v ) ) {
		return '';
	}
	$unit = isset( $v['unit'] ) && '' !== $v['unit'] ? $v['unit'] : 'px';
	$out  = array();
	$any  = false;
	foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
		if ( isset( $v[ $side ] ) && '' !== $v[ $side ] && is_numeric( $v[ $side ] ) ) {
			$out[] = ( 0 + $v[ $side ] ) . $unit;
			$any   = true;
		} else {
			$out[] = '0';
		}
	}
	return $any ? implode( ' ', $out ) : '';
}

/** A colour that is safe to put in CSS (hex, rgb(a), hsl(a), a word, or a global colour variable). */
function mcupr_el_color( $c ) {
	$c = trim( (string) $c );
	if ( '' === $c ) {
		return '';
	}
	if ( preg_match( '/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\([0-9 ,.%\/]+\)|var\(--[a-z0-9-]+\))$/i', $c ) ) {
		return $c;
	}
	return '';
}

/** A value of a "global" setting (colour / typography) → the CSS variable of Elementor's kit. */
function mcupr_el_global_ref( $s, $key ) {
	if ( empty( $s['__globals__'][ $key ] ) || ! is_string( $s['__globals__'][ $key ] ) ) {
		return '';
	}
	if ( preg_match( '#^globals/(colors|typography)\?id=([A-Za-z0-9_-]+)$#', $s['__globals__'][ $key ], $m ) ) {
		return $m[1] . ':' . $m[2];
	}
	return '';
}

function mcupr_el_color_of( $s, $key ) {
	$g = mcupr_el_global_ref( $s, $key );
	if ( 0 === strpos( $g, 'colors:' ) ) {
		return 'var(--e-global-color-' . substr( $g, 7 ) . ')';
	}
	return isset( $s[ $key ] ) ? mcupr_el_color( $s[ $key ] ) : '';
}

/** Google font names used by an element's settings (typography_font_family and its global). */
function mcupr_el_fonts_of( $s ) {
	$fonts = array();
	foreach ( $s as $k => $v ) {
		if ( is_string( $k ) && is_string( $v ) && '' !== $v && preg_match( '/font_family$/', $k ) ) {
			$fonts[] = $v;
		}
	}
	return $fonts;
}

/** Typography declarations of a group ("typography" → typography_font_size …) for a device. */
function mcupr_el_typography( $s, $prefix = 'typography', $dev = '' ) {
	$d    = array();
	$ref  = mcupr_el_global_ref( $s, $prefix . '_typography' );
	$name = 0 === strpos( $ref, 'typography:' ) ? substr( $ref, 11 ) : '';
	$on   = isset( $s[ $prefix . '_typography' ] ) && 'custom' === $s[ $prefix . '_typography' ];
	if ( '' !== $name && '' === $dev ) {
		foreach ( array( 'font-family' => 'font-family', 'font-size' => 'font-size', 'font-weight' => 'font-weight', 'text-transform' => 'text-transform', 'font-style' => 'font-style', 'text-decoration' => 'text-decoration', 'line-height' => 'line-height', 'letter-spacing' => 'letter-spacing', 'word-spacing' => 'word-spacing' ) as $prop => $suffix ) {
			$d[] = $prop . ':var(--e-global-typography-' . $name . '-' . $suffix . ')';
		}
	}
	if ( ! $on && '' === $name ) {
		return $d;
	}
	$g = static function ( $k ) use ( $s, $prefix, $dev ) {
		return mcupr_el_get( $s, $prefix . '_' . $k, $dev );
	};
	if ( '' === $dev && ! empty( $s[ $prefix . '_font_family' ] ) && is_string( $s[ $prefix . '_font_family' ] ) ) {
		$fam = preg_replace( '/[^A-Za-z0-9 _\-\x{0E00}-\x{0E7F}]/u', '', $s[ $prefix . '_font_family' ] );
		$d[] = "font-family:'" . $fam . "',sans-serif";
	}
	foreach ( array( 'font_size' => 'font-size', 'line_height' => 'line-height', 'letter_spacing' => 'letter-spacing', 'word_spacing' => 'word-spacing' ) as $k => $prop ) {
		$v = $g( $k );
		if ( mcupr_el_has( $v ) ) {
			$z = mcupr_el_size( $v );
			if ( '' !== $z ) {
				$d[] = $prop . ':' . ( 'line-height' === $prop && isset( $v['unit'] ) && 'px' !== $v['unit'] && 'em' !== $v['unit'] && '' !== $v['unit'] ? ( 0 + $v['size'] ) : $z );
			}
		}
	}
	if ( '' === $dev ) {
		foreach ( array( 'font_weight' => 'font-weight', 'text_transform' => 'text-transform', 'font_style' => 'font-style', 'text_decoration' => 'text-decoration' ) as $k => $prop ) {
			$v = $g( $k );
			if ( is_string( $v ) && '' !== $v && preg_match( '/^[a-z0-9 -]+$/i', $v ) ) {
				$d[] = $prop . ':' . $v;
			}
		}
	}
	return $d;
}

/** Background declarations of a group (background_background …) for a device. $prefix "background" or "_background" … */
function mcupr_el_background( $s, $prefix = 'background', $dev = '' ) {
	$d    = array();
	$type = isset( $s[ $prefix . '_background' ] ) ? $s[ $prefix . '_background' ] : '';
	if ( '' === $type && '' !== $dev ) {
		$type = isset( $s[ $prefix . '_background' ] ) ? $s[ $prefix . '_background' ] : ''; // the type is set on desktop
	}
	$g = static function ( $k ) use ( $s, $prefix, $dev ) {
		return mcupr_el_get( $s, $prefix . '_' . $k, $dev );
	};
	if ( 'classic' === $type || ( '' !== $dev && mcupr_el_has( $g( 'image' ) ) ) ) {
		if ( '' === $dev ) {
			$c = mcupr_el_color_of( $s, $prefix . '_color' );
			if ( '' !== $c ) {
				$d[] = 'background-color:' . $c;
			}
		}
		$img = $g( 'image' );
		if ( mcupr_el_has( $img ) && ! empty( $img['url'] ) ) {
			$d[] = "background-image:url('" . esc_url_raw( $img['url'] ) . "')";
		}
		$pos = $g( 'position' );
		if ( is_string( $pos ) && '' !== $pos ) {
			if ( 'initial' === $pos ) {
				$x = mcupr_el_size( $g( 'xpos' ) );
				$y = mcupr_el_size( $g( 'ypos' ) );
				if ( '' !== $x || '' !== $y ) {
					$d[] = 'background-position:' . ( '' !== $x ? $x : '0' ) . ' ' . ( '' !== $y ? $y : '0' );
				}
			} elseif ( preg_match( '/^[a-z ]+$/', $pos ) ) {
				$d[] = 'background-position:' . $pos;
			}
		}
		foreach ( array( 'attachment' => 'background-attachment', 'repeat' => 'background-repeat', 'size' => 'background-size' ) as $k => $prop ) {
			$v = $g( $k );
			if ( is_string( $v ) && '' !== $v && preg_match( '/^[a-z -]+$/', $v ) ) {
				$d[] = $prop . ':' . $v;
			}
		}
		$w = mcupr_el_size( $g( 'bg_width' ) );
		if ( '' !== $w && 'initial' === $g( 'size' ) ) {
			$d[] = 'background-size:' . $w . ' auto';
		}
	} elseif ( 'gradient' === $type && '' === $dev ) {
		$a    = mcupr_el_color_of( $s, $prefix . '_color' );
		$b    = mcupr_el_color( isset( $s[ $prefix . '_color_b' ] ) ? $s[ $prefix . '_color_b' ] : '' );
		$sa   = mcupr_el_size( isset( $s[ $prefix . '_color_stop' ] ) ? $s[ $prefix . '_color_stop' ] : array( 'size' => 0, 'unit' => '%' ), '%' );
		$sb   = mcupr_el_size( isset( $s[ $prefix . '_color_b_stop' ] ) ? $s[ $prefix . '_color_b_stop' ] : array( 'size' => 100, 'unit' => '%' ), '%' );
		$kind = isset( $s[ $prefix . '_gradient_type' ] ) ? $s[ $prefix . '_gradient_type' ] : 'linear';
		if ( '' !== $a && '' !== $b ) {
			if ( 'radial' === $kind ) {
				$pos = isset( $s[ $prefix . '_gradient_position' ] ) ? preg_replace( '/[^a-z ]/', '', $s[ $prefix . '_gradient_position' ] ) : 'center center';
				$d[] = 'background-image:radial-gradient(at ' . $pos . ',' . $a . ' ' . $sa . ',' . $b . ' ' . $sb . ')';
			} else {
				$ang = isset( $s[ $prefix . '_gradient_angle' ]['size'] ) && is_numeric( $s[ $prefix . '_gradient_angle' ]['size'] ) ? ( 0 + $s[ $prefix . '_gradient_angle' ]['size'] ) : 180;
				$d[] = 'background-image:linear-gradient(' . $ang . 'deg,' . $a . ' ' . $sa . ',' . $b . ' ' . $sb . ')';
			}
		}
	}
	return $d;
}

/** Border, radius and shadow of a group ("border", "box_shadow") for a device. */
function mcupr_el_border( $s, $prefix = 'border', $dev = '', $radius_key = 'border_radius', $shadow = 'box_shadow' ) {
	$d = array();
	if ( '' === $dev ) {
		$style = isset( $s[ $prefix . '_border' ] ) ? $s[ $prefix . '_border' ] : '';
		if ( is_string( $style ) && preg_match( '/^(solid|double|dotted|dashed|groove|none)$/', $style ) ) {
			$d[] = 'border-style:' . $style;
			$c   = mcupr_el_color_of( $s, $prefix . '_color' );
			if ( '' !== $c ) {
				$d[] = 'border-color:' . $c;
			}
		}
	}
	if ( isset( $s[ $prefix . '_border' ] ) || '' !== $dev ) {
		$w = mcupr_el_dim( mcupr_el_get( $s, $prefix . '_width', $dev ) );
		if ( '' !== $w ) {
			$d[] = 'border-width:' . $w;
		}
	}
	$r = mcupr_el_dim( mcupr_el_get( $s, $radius_key, $dev ) );
	if ( '' !== $r ) {
		$d[] = 'border-radius:' . $r;
	}
	if ( '' === $dev ) {
		$on = isset( $s[ $shadow . '_box_shadow_type' ] ) && 'yes' === $s[ $shadow . '_box_shadow_type' ];
		$bs = isset( $s[ $shadow . '_box_shadow' ] ) && is_array( $s[ $shadow . '_box_shadow' ] ) ? $s[ $shadow . '_box_shadow' ] : null;
		if ( $on && $bs ) {
			$col = mcupr_el_color( isset( $bs['color'] ) ? $bs['color'] : 'rgba(0,0,0,0.5)' );
			$d[] = 'box-shadow:' . ( 0 + ( isset( $bs['horizontal'] ) ? $bs['horizontal'] : 0 ) ) . 'px ' . ( 0 + ( isset( $bs['vertical'] ) ? $bs['vertical'] : 0 ) ) . 'px ' . ( 0 + ( isset( $bs['blur'] ) ? $bs['blur'] : 10 ) ) . 'px ' . ( 0 + ( isset( $bs['spread'] ) ? $bs['spread'] : 0 ) ) . 'px ' . ( '' !== $col ? $col : 'rgba(0,0,0,.5)' ) . ( isset( $bs['position'] ) && 'inset' === $bs['position'] ? ' inset' : '' );
		}
	}
	return $d;
}

/** One rule: selector{declarations} ("" when there are none). */
function mcupr_el_rule( $selector, $decls ) {
	$decls = array_values( array_filter( (array) $decls ) );
	return $decls ? $selector . '{' . implode( ';', $decls ) . '}' : '';
}

/** The rules of one device, wrapped in its media query. */
function mcupr_el_media( $dev, $css ) {
	$css = trim( $css );
	if ( '' === $css ) {
		return '';
	}
	$q = mcupr_el_devices()[ $dev ];
	return '' === $q ? $css : '@media ' . $q . '{' . $css . '}';
}

/** Spacing and the advanced tab that every element shares: margin, padding, z-index, width, positioning, background, border, hide on device. */
function mcupr_el_common_css( $el, $root ) {
	$s   = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
	$typ = isset( $el['elType'] ) ? $el['elType'] : '';
	$wid = 'widget' === $typ;
	// widgets keep the advanced tab in "_margin" / "_padding" on the widget container; sections and columns in "margin" / "padding"
	$margin_key  = $wid ? '_margin' : 'margin';
	$padding_key = $wid ? '_padding' : 'padding';
	$box         = $wid ? $root . ' > .elementor-widget-container' : $root;
	$bgp         = $wid ? '_background' : 'background';
	$out         = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$css = '';
		$m   = mcupr_el_dim( mcupr_el_get( $s, $margin_key, $dev ) );
		$p   = mcupr_el_dim( mcupr_el_get( $s, $padding_key, $dev ) );
		$css .= mcupr_el_rule( $box, array( '' !== $m ? 'margin:' . $m : '', '' !== $p ? 'padding:' . $p : '' ) );
		if ( '' === $dev ) {
			$z = isset( $s[ $wid ? '_z_index' : 'z_index' ] ) ? $s[ $wid ? '_z_index' : 'z_index' ] : '';
			if ( is_numeric( $z ) ) {
				$css .= mcupr_el_rule( $root, array( 'z-index:' . (int) $z ) );
			}
			$css .= mcupr_el_rule( $box, mcupr_el_background( $s, $bgp ) );
			$css .= mcupr_el_rule( $box, mcupr_el_border( $s, $wid ? '_border' : 'border', '', $wid ? '_border_radius' : 'border_radius', $wid ? '_box_shadow' : 'box_shadow' ) );
		} else {
			$css .= mcupr_el_rule( $box, mcupr_el_background( $s, $bgp, $dev ) );
			$css .= mcupr_el_rule( $box, mcupr_el_border( $s, $wid ? '_border' : 'border', $dev, $wid ? '_border_radius' : 'border_radius', $wid ? '_box_shadow' : 'box_shadow' ) );
		}
		if ( $wid ) {
			// width of the widget: "inherit" = whole row; "initial" = the custom width; the top and left offsets of absolute ones
			$w = isset( $s['_element_width'] ) ? $s['_element_width'] : '';
			if ( '' === $dev && 'initial' === $w ) {
				$cw   = mcupr_el_size( mcupr_el_get( $s, '_element_custom_width', '' ), '%' );
				$rule = mcupr_el_rule( $root, array( '' !== $cw ? 'width:' . $cw : '', 'max-width:100%' ) );
				$css .= '' !== $rule ? '@media (min-width:768px){' . $rule . '}' : ''; // same as a column: its own width only from tablets up
			}
			if ( '' !== $dev ) {
				$cw = mcupr_el_size( mcupr_el_get( $s, '_element_custom_width', $dev ), '%' );
				if ( '' !== $cw ) {
					$css .= mcupr_el_rule( $root, array( 'width:' . $cw, 'max-width:100%' ) );
				}
			}
		}
		$out .= mcupr_el_media( $dev, $css );
	}
	$bgo = ''; // the colour layer over a background (sections and columns)
	if ( ! $wid ) {
		$ov = isset( $s['background_overlay_background'] ) ? $s['background_overlay_background'] : '';
		if ( 'classic' === $ov || 'gradient' === $ov ) {
			$decl = mcupr_el_background( $s, 'background_overlay' );
			$op   = isset( $s['background_overlay_opacity']['size'] ) && is_numeric( $s['background_overlay_opacity']['size'] ) ? (float) $s['background_overlay_opacity']['size'] : 0.5;
			$decl[] = 'opacity:' . $op;
			$bgo  .= mcupr_el_rule( $root . ' > .elementor-background-overlay, ' . $root . ' > .elementor-widget-wrap > .elementor-background-overlay, ' . $root . ' > .elementor-element-populated > .elementor-background-overlay', $decl );
		}
	}
	return $out . $bgo;
}

/** Hide-on-device flags are classes (elementor-hidden-*): the base stylesheet has the media queries. */

/** Section / container settings → CSS. */
function mcupr_el_css_section( $el, $root ) {
	$s   = $el['settings'];
	$out = '';
	$isc = isset( $el['elType'] ) && 'container' === $el['elType'];
	if ( ! $isc ) {
		$layout = isset( $s['layout'] ) ? $s['layout'] : 'boxed';
		$cw     = mcupr_el_size( mcupr_el_get( $s, 'content_width' ) );
		if ( 'boxed' === $layout && '' !== $cw ) {
			$out .= mcupr_el_rule( $root . ' > .elementor-container', array( 'max-width:' . $cw ) );
		}
		foreach ( mcupr_el_devices() as $dev => $q ) {
			if ( '' !== $dev ) {
				$cwd = mcupr_el_size( mcupr_el_get( $s, 'content_width', $dev ) );
				if ( 'boxed' === $layout && '' !== $cwd ) {
					$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' > .elementor-container', array( 'max-width:' . $cwd ) ) );
				}
			}
			$h = mcupr_el_size( mcupr_el_get( $s, 'custom_height', $dev ) );
			if ( '' !== $h && ( isset( $s['height'] ) && 'min-height' === $s['height'] ) ) {
				$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' > .elementor-container', array( 'min-height:' . $h ) ) );
			}
		}
		if ( isset( $s['gap'] ) && 'custom' === $s['gap'] ) {
			$g = mcupr_el_size( isset( $s['gap_columns_custom'] ) ? $s['gap_columns_custom'] : null );
			if ( '' !== $g ) {
				$half = (float) $s['gap_columns_custom']['size'] / 2 . ( isset( $s['gap_columns_custom']['unit'] ) ? $s['gap_columns_custom']['unit'] : 'px' );
				$out .= mcupr_el_rule( $root . ' > .elementor-container > .elementor-column > .elementor-element-populated', array( 'padding:' . $half ) );
			}
		}
	} else {
		// flexbox container: Elementor sets custom properties that the base stylesheet turns into layout
		foreach ( mcupr_el_devices() as $dev => $q ) {
			$v = array();
			$map = array(
				'flex_direction'  => '--flex-direction',
				'flex_wrap'       => '--flex-wrap',
				'flex_justify_content' => '--justify-content',
				'flex_align_items' => '--align-items',
				'flex_align_content' => '--align-content',
				'flex_align_self' => '--align-self',
			);
			foreach ( $map as $k => $var ) {
				$val = mcupr_el_get( $s, $k, $dev );
				if ( is_string( $val ) && '' !== $val && preg_match( '/^[a-z-]+$/', $val ) ) {
					$v[] = $var . ':' . $val;
				}
			}
			$gap = mcupr_el_get( $s, 'flex_gap', $dev );
			if ( is_array( $gap ) && ( isset( $gap['column'] ) || isset( $gap['row'] ) || isset( $gap['size'] ) ) ) {
				$u  = isset( $gap['unit'] ) && '' !== $gap['unit'] ? $gap['unit'] : 'px';
				$cr = isset( $gap['row'] ) && '' !== $gap['row'] ? ( 0 + $gap['row'] ) . $u : ( isset( $gap['size'] ) && '' !== $gap['size'] ? ( 0 + $gap['size'] ) . $u : '' );
				$cc = isset( $gap['column'] ) && '' !== $gap['column'] ? ( 0 + $gap['column'] ) . $u : $cr;
				if ( '' !== $cr ) {
					$v[] = '--row-gap:' . $cr;
					$v[] = '--column-gap:' . $cc;
				}
			}
			$w = mcupr_el_size( mcupr_el_get( $s, 'width', $dev ) );
			if ( '' !== $w ) {
				$v[] = '--width:' . $w;
			}
			$cwid = mcupr_el_size( mcupr_el_get( $s, 'boxed_width', $dev ) );
			if ( '' !== $cwid ) {
				$v[] = '--content-width:' . $cwid;
				$v[] = '--container-max-width:' . $cwid;
			}
			$mh = mcupr_el_size( mcupr_el_get( $s, 'min_height', $dev ) );
			if ( '' !== $mh ) {
				$v[] = '--min-height:' . $mh;
			}
			$p = mcupr_el_get( $s, 'padding', $dev );
			if ( is_array( $p ) ) {
				$u = isset( $p['unit'] ) && '' !== $p['unit'] ? $p['unit'] : 'px';
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( isset( $p[ $side ] ) && '' !== $p[ $side ] && is_numeric( $p[ $side ] ) ) {
						$v[] = '--padding-' . $side . ':' . ( 0 + $p[ $side ] ) . $u;
					}
				}
			}
			$m = mcupr_el_get( $s, 'margin', $dev );
			if ( is_array( $m ) ) {
				$u = isset( $m['unit'] ) && '' !== $m['unit'] ? $m['unit'] : 'px';
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( isset( $m[ $side ] ) && '' !== $m[ $side ] && is_numeric( $m[ $side ] ) ) {
						$v[] = '--margin-' . $side . ':' . ( 0 + $m[ $side ] ) . $u;
					}
				}
			}
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $root, $v ) );
		}
	}
	return $out;
}

/** Column settings → CSS. */
function mcupr_el_css_column( $el, $root ) {
	$s   = $el['settings'];
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$w = mcupr_el_get( $s, '_inline_size', $dev );
		if ( is_numeric( $w ) && '' !== $w ) {
			// the width a column was given is for tablets and up: below that every column is a row of its own, as Elementor wrote it
			$rule = mcupr_el_rule( $root, array( 'width:' . ( 0 + $w ) . '%' ) );
			$out .= '' === $dev ? ( '' !== $rule ? '@media (min-width:768px){' . $rule . '}' : '' ) : mcupr_el_media( $dev, $rule );
		} elseif ( '' !== $dev ) {
			$cs = mcupr_el_get( $s, '_column_size', $dev );
			if ( is_numeric( $cs ) ) {
				$out .= mcupr_el_media( $dev, mcupr_el_rule( $root, array( 'width:' . ( 0 + $cs ) . '%' ) ) );
			}
		}
		$sp = mcupr_el_size( mcupr_el_get( $s, 'space_between_widgets', $dev ) );
		if ( '' !== $sp ) {
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' > .elementor-widget-wrap > .elementor-widget:not(.elementor-widget__width-auto):not(.elementor-widget__width-initial):not(:last-child):not(.elementor-absolute)', array( 'margin-bottom:' . $sp ) ) );
		}
	}
	return $out;
}
