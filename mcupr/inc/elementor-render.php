<?php
/**
 * Elementor pages without Elementor, part 3: the page structure (sections, columns, flexbox containers, widgets) printed with the same
 * HTML and class names Elementor printed, so that the style file Elementor wrote (or the one made from the settings) applies.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** What the rendering of this request collected: CSS rules, fonts, scripts needed, widgets not supported. */
function &mcupr_el_state_ref( $reset = false ) {
	static $st = null;
	if ( null === $st || $reset ) {
		$st = array( 'css' => '', 'fonts' => array(), 'js' => array(), 'unsupported' => array(), 'icons' => false );
	}
	return $st;
}

function mcupr_el_state() {
	return mcupr_el_state_ref();
}

function mcupr_el_collect_css( $css ) {
	$r = &mcupr_el_state_ref();
	$r['css'] .= $css;
}

function mcupr_el_need( $what ) {
	$r = &mcupr_el_state_ref();
	$r['js'][ $what ] = true;
}

function mcupr_el_note_unsupported( $type ) {
	$r = &mcupr_el_state_ref();
	$r['unsupported'][ $type ] = isset( $r['unsupported'][ $type ] ) ? $r['unsupported'][ $type ] + 1 : 1;
}

/** Hide on devices → classes, animation → attributes, custom classes and id: what every element shares. */
function mcupr_el_common_classes( $s ) {
	$c = array();
	foreach ( array( 'desktop', 'tablet', 'mobile' ) as $d ) {
		if ( ! empty( $s[ 'hide_' . $d ] ) ) {
			$c[] = 'elementor-hidden-' . $d;
		}
	}
	foreach ( array( '_css_classes', 'css_classes' ) as $k ) {
		if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
			foreach ( preg_split( '/\s+/', trim( $s[ $k ] ) ) as $cl ) {
				if ( preg_match( '/^[A-Za-z0-9_\-:]+$/', $cl ) ) {
					$c[] = $cl;
				}
			}
		}
	}
	$anim = '';
	foreach ( array( '_animation', 'animation' ) as $k ) {
		if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) && 'none' !== $s[ $k ] && preg_match( '/^[A-Za-z0-9]+$/', $s[ $k ] ) ) {
			$anim = $s[ $k ];
		}
	}
	if ( '' !== $anim ) {
		$c[] = 'elementor-invisible';
	}
	return array( $c, $anim );
}

function mcupr_el_common_attrs( $id, $type, $s, $extra = array() ) {
	list( , $anim ) = mcupr_el_common_classes( $s );
	$attrs = ' data-id="' . esc_attr( $id ) . '" data-element_type="' . esc_attr( $type ) . '"';
	$set   = array();
	if ( '' !== $anim ) {
		$set['animation'] = $anim;
		foreach ( array( '_animation_delay', 'animation_delay' ) as $k ) {
			if ( isset( $s[ $k ] ) && is_numeric( $s[ $k ] ) ) {
				$set['animation_delay'] = (int) $s[ $k ];
			}
		}
		mcupr_el_need( 'animation' );
	}
	$set = array_merge( $set, $extra );
	if ( $set ) {
		$attrs .= " data-settings='" . esc_attr( wp_json_encode( $set ) ) . "'";
	}
	if ( ! empty( $s['_element_id'] ) && is_string( $s['_element_id'] ) && preg_match( '/^[A-Za-z][A-Za-z0-9_\-:.]*$/', $s['_element_id'] ) ) {
		$attrs .= ' id="' . esc_attr( $s['_element_id'] ) . '"';
	}
	return $attrs;
}

/** The elements of a list (sections / containers at the top; columns in a section; widgets in a column), as HTML. */
function mcupr_el_render_list( $elements, $post_id, $parent = null ) {
	$html = '';
	foreach ( (array) $elements as $el ) {
		if ( is_array( $el ) ) {
			$html .= mcupr_el_render_element( $el, $post_id, $parent );
		}
	}
	return $html;
}

function mcupr_el_render_element( $el, $post_id, $parent = null ) {
	$type = isset( $el['elType'] ) ? $el['elType'] : '';
	$id   = isset( $el['id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $el['id'] ) : '';
	if ( '' === $id ) {
		$id = substr( md5( wp_json_encode( $el ) ), 0, 7 );
	}
	$el['id']       = $id;
	$el['settings'] = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
	$el['elements'] = isset( $el['elements'] ) && is_array( $el['elements'] ) ? $el['elements'] : array();
	$root           = '.elementor-' . (int) $post_id . ' .elementor-element.elementor-element-' . $id;
	switch ( $type ) {
		case 'section':
			return mcupr_el_render_section( $el, $post_id, $root );
		case 'column':
			return mcupr_el_render_column( $el, $post_id, $root, $parent );
		case 'container':
			return mcupr_el_render_container( $el, $post_id, $root, $parent );
		case 'widget':
			return mcupr_el_render_widget( $el, $post_id, $root );
		case 'e-div-block':
		case 'e-flexbox':
			return mcupr_el_render_atomic_block( $el, $post_id, $parent );
	}
	return '';
}

function mcupr_el_shapes( $s ) {
	$out = '';
	foreach ( array( 'top', 'bottom' ) as $pos ) {
		$shape = isset( $s[ 'shape_divider_' . $pos ] ) ? $s[ 'shape_divider_' . $pos ] : '';
		if ( ! is_string( $shape ) || '' === $shape ) {
			continue;
		}
		$neg   = ! empty( $s[ 'shape_divider_' . $pos . '_negative' ] );
		$out  .= '<div class="elementor-shape elementor-shape-' . $pos . '" data-negative="' . ( $neg ? 'true' : 'false' ) . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path class="elementor-shape-fill" d="' . esc_attr( mcupr_el_shape_path( $shape, $pos ) ) . '"/></svg></div>';
	}
	return $out;
}

/** A few of Elementor's divider shapes (the rest fall back to a gentle wave). */
function mcupr_el_shape_path( $shape, $pos ) {
	switch ( $shape ) {
		case 'tilt':
			return 'M0,6V0h1000v100L0,6z';
		case 'triangle':
			return 'M0,0h1000L500,100z';
		case 'curve':
			return 'M500,97C126.7,96.3,0.8,19.8,0,0v100l1000,0V1C1000,19.4,873.3,97.8,500,97z';
		case 'arrow':
			return 'M500,98.9L0,0h1000L500,98.9z';
		default:
			return 'M0,0v30c200,50,300,50,500,20s300-50,500,0V0H0z';
	}
}

function mcupr_el_has_bg_overlay( $s ) {
	return isset( $s['background_overlay_background'] ) && in_array( $s['background_overlay_background'], array( 'classic', 'gradient' ), true );
}

function mcupr_el_render_section( $el, $post_id, $root ) {
	$s      = $el['settings'];
	$inner  = ! empty( $el['isInner'] );
	$layout = isset( $s['layout'] ) && 'full_width' === $s['layout'] ? 'full_width' : 'boxed';
	$gap    = isset( $s['gap'] ) && is_string( $s['gap'] ) ? $s['gap'] : 'default';
	$height = isset( $s['height'] ) && is_string( $s['height'] ) ? $s['height'] : 'default';
	list( $cls ) = mcupr_el_common_classes( $s );
	$classes = array( 'elementor-section', $inner ? 'elementor-inner-section' : 'elementor-top-section', 'elementor-element', 'elementor-element-' . $el['id'], 'elementor-section-' . $layout, 'elementor-section-height-' . $height, 'elementor-section-height-default' );
	if ( ! empty( $s['stretch_section'] ) ) {
		$classes[] = 'elementor-section-stretched';
	}
	if ( ! empty( $s['content_position'] ) && is_string( $s['content_position'] ) ) {
		$classes[] = 'elementor-section-content-' . preg_replace( '/[^a-z-]/', '', $s['content_position'] );
	}
	if ( ! empty( $s['structure'] ) ) {
		$classes[] = 'elementor-section-structure';
	}
	$classes = array_merge( $classes, $cls );
	$tag     = isset( $s['html_tag'] ) && in_array( $s['html_tag'], array( 'header', 'footer', 'main', 'article', 'aside', 'nav', 'div', 'section' ), true ) ? $s['html_tag'] : 'section';
	mcupr_el_collect_css( mcupr_el_common_css( $el, $root ) . mcupr_el_css_section( $el, $root ) );
	mcupr_el_note_fonts( $s );
	$html  = '<' . $tag . ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . mcupr_el_common_attrs( $el['id'], 'section', $s ) . '>';
	if ( mcupr_el_has_bg_overlay( $s ) ) {
		$html .= '<div class="elementor-background-overlay"></div>';
	}
	$html .= mcupr_el_shapes( $s );
	$html .= '<div class="elementor-container elementor-column-gap-' . esc_attr( preg_replace( '/[^a-z]/', '', $gap ) ) . '">';
	$html .= mcupr_el_render_list( $el['elements'], $post_id, $el );
	$html .= '</div></' . $tag . '>';
	return $html;
}

function mcupr_el_render_column( $el, $post_id, $root, $parent ) {
	$s     = $el['settings'];
	$inner = $parent && ! empty( $parent['isInner'] );
	$size  = isset( $s['_column_size'] ) && is_numeric( $s['_column_size'] ) ? (int) $s['_column_size'] : 100;
	list( $cls ) = mcupr_el_common_classes( $s );
	$classes = array( 'elementor-column', 'elementor-col-' . $size, $inner ? 'elementor-inner-column' : 'elementor-top-column', 'elementor-element', 'elementor-element-' . $el['id'] );
	$classes = array_merge( $classes, $cls );
	foreach ( array( 'tablet', 'mobile' ) as $d ) {
		$v = mcupr_el_get( $s, '_inline_size', $d );
		$w = mcupr_el_get( $s, '_column_size', $d );
		if ( is_numeric( $v ) || is_numeric( $w ) ) {
			$classes[] = 'elementor-' . ( 'tablet' === $d ? 'md' : 'sm' ) . '-' . (int) ( is_numeric( $v ) ? $v : $w );
		}
	}
	mcupr_el_collect_css( mcupr_el_common_css( $el, $root ) . mcupr_el_css_column( $el, $root ) );
	mcupr_el_note_fonts( $s );
	$populated = ! empty( $el['elements'] );
	$html      = '<div class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . mcupr_el_common_attrs( $el['id'], 'column', $s ) . '>';
	$html     .= '<div class="elementor-widget-wrap' . ( $populated ? ' elementor-element-populated' : '' ) . '">';
	if ( mcupr_el_has_bg_overlay( $s ) ) {
		$html .= '<div class="elementor-background-overlay"></div>';
	}
	$html .= mcupr_el_render_list( $el['elements'], $post_id, $el );
	$html .= '</div></div>';
	return $html;
}

function mcupr_el_render_container( $el, $post_id, $root, $parent ) {
	$s     = $el['settings'];
	$child = $parent && isset( $parent['elType'] ) && 'container' === $parent['elType'];
	$full  = isset( $s['content_width'] ) && 'full' === $s['content_width'];
	list( $cls ) = mcupr_el_common_classes( $s );
	$classes = array( 'elementor-element', 'elementor-element-' . $el['id'], 'e-flex', $full ? 'e-con-full' : 'e-con-boxed', 'e-con', $child ? 'e-child' : 'e-parent' );
	$classes = array_merge( $classes, $cls );
	mcupr_el_collect_css( mcupr_el_common_css( $el, $root ) . mcupr_el_css_section( $el, $root ) );
	mcupr_el_note_fonts( $s );
	$tag  = isset( $s['html_tag'] ) && in_array( $s['html_tag'], array( 'header', 'footer', 'main', 'article', 'aside', 'nav', 'div', 'section' ), true ) ? $s['html_tag'] : 'div';
	$html = '<' . $tag . ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . mcupr_el_common_attrs( $el['id'], 'container', $s ) . '>';
	if ( mcupr_el_has_bg_overlay( $s ) ) {
		$html .= '<div class="elementor-background-overlay"></div>';
	}
	$html .= mcupr_el_shapes( $s );
	$html .= $full ? '' : '<div class="e-con-inner">';
	$html .= mcupr_el_render_list( $el['elements'], $post_id, $el );
	$html .= $full ? '' : '</div>';
	$html .= '</' . $tag . '>';
	return $html;
}

function mcupr_el_note_fonts( $s ) {
	foreach ( mcupr_el_fonts_of( $s ) as $f ) {
		$r                  = &mcupr_el_state_ref();
		$r['fonts'][ $f ] = true;
	}
}

/** The widget, with its own HTML (inc/elementor-widgets.php) in the wrapper Elementor printed. */
function mcupr_el_render_widget( $el, $post_id, $root ) {
	$type = isset( $el['widgetType'] ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $el['widgetType'] ) ) : '';
	$s    = $el['settings'];
	if ( 'global' === $type && ! empty( $el['templateID'] ) ) {
		$g = mcupr_el_global_widget( (int) $el['templateID'] );
		if ( $g ) {
			$type = $g['widgetType'];
			$s    = array_merge( $g['settings'], $s );
		}
	}
	$s = mcupr_el_resolve_dynamic( $s, $post_id );
	$fn = 'mcupr_el_w_' . str_replace( '-', '_', $type );
	if ( '' === $type || ! function_exists( $fn ) ) {
		mcupr_el_note_unsupported( '' === $type ? '(none)' : $type );
		return current_user_can( 'edit_posts' ) && ! is_feed() ? '<div class="elementor-element elementor-element-' . esc_attr( $el['id'] ) . ' elementor-widget mcupr-el-missing" data-id="' . esc_attr( $el['id'] ) . '" style="border:2px dashed #b45309;padding:10px;color:#92400e;background:#fffbeb;font:14px/1.4 sans-serif">' . esc_html( sprintf( /* translators: %s: widget name */ __( 'Elementor widget “%s” is not supported without the plugin; visitors do not see this box.', 'mcupr' ), $type ) ) . '</div>' : '';
	}
	list( $cls ) = mcupr_el_common_classes( $s );
	$classes = array( 'elementor-element', 'elementor-element-' . $el['id'] );
	$w       = isset( $s['_element_width'] ) ? $s['_element_width'] : '';
	if ( 'inherit' === $w ) {
		$classes[] = 'elementor-widget__width-inherit';
	} elseif ( 'initial' === $w ) {
		$classes[] = 'elementor-widget__width-initial';
	} elseif ( 'auto' === $w ) {
		$classes[] = 'elementor-widget__width-auto';
	}
	foreach ( array( 'align', 'text_align' ) as $ak ) {
		if ( isset( $s[ $ak ] ) && is_string( $s[ $ak ] ) && preg_match( '/^(left|center|right|justify)$/', $s[ $ak ] ) && in_array( $type, array( 'button', 'image', 'icon', 'heading', 'text-editor', 'image-gallery', 'counter', 'social-icons', 'star-rating', 'icon-list', 'divider', 'call-to-action', 'testimonial' ), true ) ) {
			$classes[] = 'elementor-align-' . $s[ $ak ];
			foreach ( array( 'tablet', 'mobile' ) as $d ) {
				if ( isset( $s[ $ak . '_' . $d ] ) && is_string( $s[ $ak . '_' . $d ] ) && preg_match( '/^(left|center|right|justify)$/', $s[ $ak . '_' . $d ] ) ) {
					$classes[] = 'elementor-' . $d . '-align-' . $s[ $ak . '_' . $d ];
				}
			}
			break;
		}
	}
	$classes = array_merge( $classes, $cls, array( 'elementor-widget', 'elementor-widget-' . $type ) );
	mcupr_el_collect_css( mcupr_el_common_css( $el, $root ) );
	$wcss = 'mcupr_el_wcss_' . str_replace( '-', '_', $type );
	if ( function_exists( $wcss ) ) {
		mcupr_el_collect_css( (string) call_user_func( $wcss, $s, $root, $el ) );
	}
	mcupr_el_note_fonts( $s );
	$inner = call_user_func( $fn, $s, $el, $post_id );
	if ( false === $inner ) {
		return '';
	}
	return '<div class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"' . mcupr_el_common_attrs( $el['id'], 'widget', $s ) . ' data-widget_type="' . esc_attr( $type ) . '.default"><div class="elementor-widget-container">' . $inner . '</div></div>';
}

/** A global widget: the first widget of a saved template (library post). */
function mcupr_el_global_widget( $template_id ) {
	$data = mcupr_el_data( $template_id );
	if ( ! $data || empty( $data[0]['widgetType'] ) ) {
		return null;
	}
	return array( 'widgetType' => $data[0]['widgetType'], 'settings' => isset( $data[0]['settings'] ) && is_array( $data[0]['settings'] ) ? $data[0]['settings'] : array() );
}

/* ------------------------------------------------------------------------------------------ dynamic tags */

/** The settings with their dynamic tags ([elementor-tag …]) replaced by what the tag gives today. */
function mcupr_el_resolve_dynamic( $s, $post_id ) {
	if ( empty( $s['__dynamic__'] ) || ! is_array( $s['__dynamic__'] ) ) {
		return $s;
	}
	foreach ( $s['__dynamic__'] as $key => $tag ) {
		if ( ! is_string( $tag ) || ! preg_match( '/\[elementor-tag id="[^"]*" name="([^"]+)" settings="([^"]*)"\]/', $tag, $m ) ) {
			continue;
		}
		$name = $m[1];
		$set  = json_decode( rawurldecode( $m[2] ), true );
		$set  = is_array( $set ) ? $set : array();
		$val  = mcupr_el_dynamic_value( $name, $set, $post_id );
		if ( null === $val ) {
			continue;
		}
		$cur = isset( $s[ $key ] ) ? $s[ $key ] : null;
		if ( is_array( $val ) && is_array( $cur ) ) {
			$s[ $key ] = array_merge( $cur, $val );
		} elseif ( is_array( $val ) ) {
			$s[ $key ] = $val;
		} else {
			$s[ $key ] = $val;
		}
	}
	return $s;
}

function mcupr_el_dynamic_value( $name, $set, $post_id ) {
	$p = get_post( $post_id );
	switch ( $name ) {
		case 'post-title':
			return $p ? get_the_title( $p ) : '';
		case 'post-excerpt':
			return $p ? get_the_excerpt( $p ) : '';
		case 'post-url':
			return array( 'url' => $p ? get_permalink( $p ) : '' );
		case 'post-date':
			return $p ? get_the_date( isset( $set['date_format'] ) && '' !== $set['date_format'] && 'default' !== $set['date_format'] ? $set['date_format'] : '', $p ) : '';
		case 'post-time':
			return $p ? get_the_time( '', $p ) : '';
		case 'post-id':
			return (string) (int) $post_id;
		case 'post-featured-image':
			$id = $p ? get_post_thumbnail_id( $p ) : 0;
			return $id ? array( 'id' => $id, 'url' => (string) wp_get_attachment_url( $id ) ) : array( 'id' => '', 'url' => '' );
		case 'site-title':
			return get_bloginfo( 'name' );
		case 'site-tagline':
			return get_bloginfo( 'description' );
		case 'site-url':
			return array( 'url' => home_url( '/' ) );
		case 'author-name':
			return $p ? get_the_author_meta( 'display_name', $p->post_author ) : '';
		case 'author-url':
			return array( 'url' => $p ? get_author_posts_url( $p->post_author ) : '' );
		case 'current-date-time':
			return wp_date( isset( $set['date_format'] ) && '' !== $set['date_format'] && 'default' !== $set['date_format'] ? $set['date_format'] : get_option( 'date_format' ) );
		case 'archive-title':
			return wp_strip_all_tags( get_the_archive_title() );
		case 'request-parameter':
			return '';
		case 'post-custom-field':
		case 'acf-text':
		case 'acf-url':
		case 'acf-image':
			$key = '';
			foreach ( array( 'custom_key', 'key' ) as $k ) {
				if ( ! empty( $set[ $k ] ) ) {
					$key = (string) $set[ $k ];
				}
			}
			if ( 'acf-text' === $name || 'acf-url' === $name || 'acf-image' === $name ) {
				$key = preg_replace( '/^[a-z0-9_]*:/', '', $key );
			}
			if ( '' === $key || 0 === strpos( $key, '_' ) ) {
				return null;
			}
			$v = get_post_meta( $post_id, $key, true );
			if ( 'acf-image' === $name ) {
				$v = is_numeric( $v ) ? array( 'id' => (int) $v, 'url' => (string) wp_get_attachment_url( (int) $v ) ) : array( 'id' => '', 'url' => (string) $v );
				return $v;
			}
			return 'acf-url' === $name ? array( 'url' => (string) $v ) : ( is_scalar( $v ) ? (string) $v : '' );
	}
	return null;
}
