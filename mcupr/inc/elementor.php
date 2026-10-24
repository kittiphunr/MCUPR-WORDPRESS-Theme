<?php
/**
 * Elementor pages without Elementor.
 *
 * A site that used Elementor (Pro) keeps the whole page in the post meta "_elementor_data" (sections, columns, widgets and their
 * settings) next to the text it printed. When the plugin is gone, this file prints those pages from that data, with the same HTML
 * and class names, and the style Elementor wrote (uploads/elementor/css/) or, when it is not there, the style written again from the
 * settings (inc/elementor-css.php). The data is never changed, so the plugin can come back at any time: while Elementor is active the
 * theme does nothing here.
 *
 * Files: elementor.php (this: detection, the page, the style, the template, the report), elementor-render.php (sections, columns,
 * containers, widgets), elementor-css.php (settings → CSS), elementor-widgets.php (widgets of Elementor), elementor-widgets-pro.php
 * (widgets of Elementor Pro), assets/css/elementor-base.css, assets/js/elementor-compat.js.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

require_once MCUPR_DIR . '/inc/elementor-css.php';
require_once MCUPR_DIR . '/inc/elementor-render.php';
require_once MCUPR_DIR . '/inc/elementor-widgets.php';
require_once MCUPR_DIR . '/inc/elementor-widgets-pro.php';

/** Is the plugin itself running? Then it prints its pages and the theme leaves them alone. */
function mcupr_el_plugin_active() {
	return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin', false );
}

/** The saved data of a post: the list of elements, or null. */
function mcupr_el_data( $post_id ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( is_array( $raw ) ) {
		return $raw ? $raw : null;
	}
	if ( ! is_string( $raw ) || '' === $raw ) {
		return null;
	}
	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) ) {
		$d = json_decode( wp_unslash( $raw ), true );
	}
	return is_array( $d ) && $d ? $d : null;
}

/** Is this post made with Elementor (and the plugin is not here to print it)? */
function mcupr_el_is_built( $post_id ) {
	static $cache = array();
	$post_id = (int) $post_id;
	if ( ! $post_id || mcupr_el_plugin_active() ) {
		return false;
	}
	if ( ! isset( $cache[ $post_id ] ) ) {
		$cache[ $post_id ] = 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && null !== mcupr_el_data( $post_id );
	}
	return $cache[ $post_id ];
}

/** Settings of a page (Elementor "page settings": hide title, …). */
function mcupr_el_page_settings( $post_id ) {
	$s = get_post_meta( $post_id, '_elementor_page_settings', true );
	return is_array( $s ) ? $s : array();
}

/* --------------------------------------------------------------------------------------------- printing */

/** The page as HTML, in the wrapper Elementor printed around it. */
function mcupr_el_render_post( $post_id, $type = '' ) {
	static $depth = 0;
	$post_id = (int) $post_id;
	$data    = mcupr_el_data( $post_id );
	if ( ! $data || $depth > 4 ) {
		return '';
	}
	if ( '' === $type ) {
		$type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
		$type = '' !== $type ? $type : ( 'post' === get_post_type( $post_id ) ? 'wp-post' : 'wp-page' );
	}
	$depth++;
	$html = mcupr_el_render_list( $data, $post_id );
	$depth--;
	return '<div data-elementor-type="' . esc_attr( preg_replace( '/[^a-z0-9\-]/', '', strtolower( $type ) ) ) . '" data-elementor-id="' . $post_id . '" class="elementor elementor-' . $post_id . '">' . $html . '</div>';
}

/** A template of the library (saved section, page, popup …) as HTML. */
function mcupr_el_render_template( $id ) {
	$id = (int) $id;
	if ( ! $id || ! mcupr_el_data( $id ) || 'trash' === get_post_status( $id ) ) {
		return '';
	}
	$type = (string) get_post_meta( $id, '_elementor_template_type', true );
	return mcupr_el_render_post( $id, '' !== $type ? $type : 'section' );
}

add_shortcode(
	'elementor-template',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'id' => 0 ), $atts, 'elementor-template' );
		return mcupr_el_print( (int) $a['id'] );
	}
);

/* --------------------------------------------------------------------------------------------- the style */

/** The style file Elementor wrote for a post, if it is still there: array( path, url ) or null. */
function mcupr_el_css_file( $name ) {
	static $up = null;
	if ( null === $up ) {
		$up = wp_get_upload_dir();
	}
	$rel = '/elementor/css/' . $name . '.css';
	if ( ! empty( $up['basedir'] ) && is_readable( $up['basedir'] . $rel ) && filesize( $up['basedir'] . $rel ) > 20 ) {
		return array( $up['basedir'] . $rel, $up['baseurl'] . $rel );
	}
	return null;
}

function mcupr_el_use_files() {
	return (bool) apply_filters( 'mcupr_el_use_style_files', get_option( 'mcupr_el_use_files', 'yes' ) !== 'no' );
}

/** The kit (site settings: colours, typography, container width) as CSS variables and defaults, when its file is not there. */
function mcupr_el_kit_css() {
	$kit = (int) get_option( 'elementor_active_kit', 0 );
	if ( ! $kit ) {
		return '';
	}
	$s   = mcupr_el_page_settings( $kit );
	$out = '';
	$vars = array();
	foreach ( array( 'system_colors', 'custom_colors' ) as $k ) {
		foreach ( isset( $s[ $k ] ) && is_array( $s[ $k ] ) ? $s[ $k ] : array() as $c ) {
			$id  = isset( $c['_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $c['_id'] ) : '';
			$col = isset( $c['color'] ) ? mcupr_el_color( $c['color'] ) : '';
			if ( '' !== $id && '' !== $col ) {
				$vars[] = '--e-global-color-' . $id . ':' . $col;
			}
		}
	}
	foreach ( array( 'system_typography', 'custom_typography' ) as $k ) {
		foreach ( isset( $s[ $k ] ) && is_array( $s[ $k ] ) ? $s[ $k ] : array() as $t ) {
			$id = isset( $t['_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $t['_id'] ) : '';
			if ( '' === $id ) {
				continue;
			}
			if ( ! empty( $t['typography_font_family'] ) ) {
				$vars[] = "--e-global-typography-{$id}-font-family:'" . preg_replace( '/[^A-Za-z0-9 _\-\x{0E00}-\x{0E7F}]/u', '', (string) $t['typography_font_family'] ) . "'";
				$r = &mcupr_el_state_ref();
				$r['fonts'][ (string) $t['typography_font_family'] ] = true;
			}
			foreach ( array( 'font_weight' => 'font-weight', 'text_transform' => 'text-transform', 'font_style' => 'font-style', 'text_decoration' => 'text-decoration' ) as $kk => $css ) {
				if ( ! empty( $t[ 'typography_' . $kk ] ) && is_string( $t[ 'typography_' . $kk ] ) && preg_match( '/^[a-z0-9 -]+$/i', $t[ 'typography_' . $kk ] ) ) {
					$vars[] = "--e-global-typography-{$id}-{$css}:" . $t[ 'typography_' . $kk ];
				}
			}
			foreach ( array( 'font_size' => 'font-size', 'line_height' => 'line-height', 'letter_spacing' => 'letter-spacing', 'word_spacing' => 'word-spacing' ) as $kk => $css ) {
				$z = isset( $t[ 'typography_' . $kk ] ) ? mcupr_el_size( $t[ 'typography_' . $kk ] ) : '';
				if ( '' !== $z ) {
					$vars[] = "--e-global-typography-{$id}-{$css}:" . ( 'line-height' === $css && isset( $t[ 'typography_' . $kk ]['unit'] ) && ! in_array( $t[ 'typography_' . $kk ]['unit'], array( 'px', 'em' ), true ) ? ( 0 + $t[ 'typography_' . $kk ]['size'] ) : $z );
				}
			}
		}
	}
	if ( $vars ) {
		$out .= '.elementor-kit-' . $kit . ',:root{' . implode( ';', $vars ) . '}';
	}
	$w = mcupr_el_size( isset( $s['container_width'] ) ? $s['container_width'] : null );
	if ( '' !== $w ) {
		$out .= '.elementor-section.elementor-section-boxed>.elementor-container{max-width:' . $w . '}:root{--container-max-width:' . $w . '}';
	}
	$sp = mcupr_el_size( isset( $s['space_between_widgets'] ) ? $s['space_between_widgets'] : null );
	if ( '' !== $sp ) {
		$out .= ':root{--kit-widget-spacing:' . $sp . '}';
	}
	foreach ( array( 'body' => 'body .elementor', 'h1' => '.elementor h1', 'h2' => '.elementor h2', 'h3' => '.elementor h3', 'h4' => '.elementor h4', 'h5' => '.elementor h5', 'h6' => '.elementor h6' ) as $k => $sel ) {
		$out .= mcupr_el_typo_rule( $sel, $s, $k . '_typography' );
		$c = mcupr_el_color_of( $s, $k . '_color' );
		$out .= mcupr_el_rule( $sel, array( '' !== $c ? 'color:' . $c : '' ) );
	}
	foreach ( array( 'link_normal_color' => '.elementor a', 'link_hover_color' => '.elementor a:hover' ) as $k => $sel ) {
		$c = isset( $s[ $k ] ) ? mcupr_el_color( $s[ $k ] ) : '';
		$out .= mcupr_el_rule( $sel, array( '' !== $c ? 'color:' . $c : '' ) );
	}
	return $out;
}

/** Local copies of the fonts Elementor saved: uploads/elementor/google-fonts/css/<name>.css. */
function mcupr_el_font_files( $fonts ) {
	$up  = wp_get_upload_dir();
	$out = array();
	foreach ( array_keys( $fonts ) as $f ) {
		$slug = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '', $f ) );
		$rel  = '/elementor/google-fonts/css/' . $slug . '.css';
		if ( ! empty( $up['basedir'] ) && is_readable( $up['basedir'] . $rel ) ) {
			$out[ 'mcupr-el-font-' . $slug ] = $up['baseurl'] . $rel;
		}
	}
	return $out;
}

/** All that the page needs besides its HTML: array( files => handle => url, css => string, fonts_missing => names, js => flags, icons => bool ). */
function mcupr_el_assets( $post_ids ) {
	$st    = mcupr_el_state();
	$files = array();
	$own   = $st['css'];
	if ( mcupr_el_use_files() ) {
		$have_all = true;
		foreach ( $post_ids as $pid ) {
			$f = mcupr_el_css_file( 'post-' . (int) $pid );
			if ( $f ) {
				$files[ 'mcupr-el-post-' . (int) $pid ] = $f[1] . '?ver=' . filemtime( $f[0] );
			} else {
				$have_all = false;
			}
		}
		// the rules written from the settings are used only for the posts that have no file: they are marked by their post's class
		if ( $files ) {
			$own = mcupr_el_css_without( $own, array_keys( $files ) );
		}
	}
	$kit = (int) get_option( 'elementor_active_kit', 0 );
	$kf  = $kit && mcupr_el_use_files() ? mcupr_el_css_file( 'post-' . $kit ) : null;
	$gf  = mcupr_el_use_files() ? mcupr_el_css_file( 'global' ) : null;
	if ( $gf ) {
		$files = array( 'mcupr-el-global' => $gf[1] . '?ver=' . filemtime( $gf[0] ) ) + $files;
	}
	if ( $kf ) {
		$files = array( 'mcupr-el-kit' => $kf[1] . '?ver=' . filemtime( $kf[0] ) ) + $files;
		$kitcss = '';
	} else {
		$kitcss = mcupr_el_kit_css();
	}
	$st    = mcupr_el_state();
	$ffs   = mcupr_el_font_files( $st['fonts'] );
	$miss  = array();
	foreach ( array_keys( $st['fonts'] ) as $f ) {
		$slug = strtolower( preg_replace( '/[^A-Za-z0-9]+/', '', $f ) );
		if ( ! isset( $ffs[ 'mcupr-el-font-' . $slug ] ) && ! mcupr_el_is_system_font( $f ) ) {
			$miss[] = $f;
		}
	}
	return array( 'files' => $files, 'fonts' => $ffs, 'css' => $kitcss . $own, 'fonts_missing' => $miss, 'js' => $st['js'], 'icons' => $st['icons'] );
}

/** The rules of the posts that have their own file are taken out of what was written from the settings. */
function mcupr_el_css_without( $css, $handles ) {
	foreach ( $handles as $h ) {
		$pid = (int) preg_replace( '/[^0-9]/', '', $h );
		if ( $pid ) {
			// every rule of that page starts with ".elementor-<id> .elementor-element" (a selector never holds a brace, so this cannot bite into another rule)
			$css = preg_replace( '/\.elementor-' . $pid . ' \.elementor-element[^{}]*\{[^{}]*\}/', '', (string) $css );
		}
	}
	return preg_replace( '/@media[^{]*\{\s*\}/', '', (string) $css ); // media blocks left empty by that
}

function mcupr_el_is_system_font( $f ) {
	return in_array( strtolower( trim( $f ) ), array( 'arial', 'helvetica', 'verdana', 'tahoma', 'trebuchet ms', 'times new roman', 'georgia', 'courier new', 'sarabun', 'sans-serif', 'serif', 'monospace', 'system-ui' ), true );
}

/** Put the base style, the style of the page and the script on the page: through the queue before the head is printed, in the body after. */
function mcupr_el_deliver( $a ) {
	static $done = array();
	$head_open = ! did_action( 'wp_head' ) || doing_action( 'wp_enqueue_scripts' ); // the queue is still open while the scripts of the page are being gathered
	if ( empty( $done['base'] ) ) {
		$done['base'] = true;
		if ( $head_open ) {
			wp_enqueue_style( 'mcupr-elementor', get_theme_file_uri( 'assets/css/elementor-base.css' ), array(), MCUPR_VERSION );
		} else {
			echo '<link rel="stylesheet" href="' . esc_url( get_theme_file_uri( 'assets/css/elementor-base.css' ) . '?ver=' . MCUPR_VERSION ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
	}
	if ( $a['icons'] && empty( $done['icons'] ) ) {
		$done['icons'] = true;
		$u = get_theme_file_uri( 'assets/vendor/fontawesome/css/all.min.css' );
		if ( $head_open ) {
			wp_enqueue_style( 'mcupr-el-fa', $u, array(), '5.15.4' );
		} else {
			echo '<link rel="stylesheet" href="' . esc_url( $u ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
	}
	foreach ( array_merge( $a['files'], $a['fonts'] ) as $h => $u ) {
		if ( ! empty( $done[ $h ] ) ) {
			continue;
		}
		$done[ $h ] = true;
		if ( $head_open ) {
			wp_enqueue_style( $h, $u, array( 'mcupr-elementor' ), null );
		} else {
			echo '<link rel="stylesheet" href="' . esc_url( $u ) . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
		}
	}
	$css = trim( (string) $a['css'] );
	if ( '' !== $css ) {
		$key = 'css-' . md5( $css );
		if ( empty( $done[ $key ] ) ) {
			$done[ $key ] = true;
			if ( $head_open ) {
				wp_add_inline_style( 'mcupr-elementor', $css );
			} else {
				echo '<style id="mcupr-el-' . esc_attr( substr( md5( $css ), 0, 8 ) ) . '">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	}
	if ( $a['js'] && empty( $done['js'] ) ) {
		$done['js'] = true;
		if ( $head_open ) {
			wp_enqueue_script( 'mcupr-elementor', get_theme_file_uri( 'assets/js/elementor-compat.js' ), array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		} else {
			echo '<script src="' . esc_url( get_theme_file_uri( 'assets/js/elementor-compat.js' ) . '?ver=' . MCUPR_VERSION ) . '" defer></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		}
	}
}

/** A post or template printed with what it needs (used where the page is built before the head is printed, and for templates inside content). */
function mcupr_el_print( $post_id ) {
	if ( mcupr_el_plugin_active() ) {
		return '';
	}
	mcupr_el_state_ref( true );
	$html = mcupr_el_render_template( $post_id );
	if ( '' === $html ) {
		mcupr_el_state_ref( true );
		return '';
	}
	$ids = array( (int) $post_id );
	preg_match_all( '/data-elementor-id="(\d+)"/', $html, $m );
	$ids = array_unique( array_merge( $ids, array_map( 'intval', $m[1] ) ) );
	$a   = mcupr_el_assets( $ids );
	ob_start();
	mcupr_el_deliver( $a );
	$tags = ob_get_clean();
	mcupr_el_report_note( $a );
	return $tags . $html;
}

/* ----------------------------------------------------------------------------------- the page itself */

/** The pages of the site that are made with Elementor and printed from their data: the post of this request. */
function mcupr_el_current() {
	if ( ! is_singular() ) {
		return 0;
	}
	$id = (int) get_queried_object_id();
	return $id && mcupr_el_is_built( $id ) ? $id : 0;
}

/** Before the head is printed: print the page now (kept for the content filter) so its style goes into the head. */
function mcupr_el_prepare() {
	static $prepared = array();
	$id = mcupr_el_current();
	if ( ! $id || isset( $prepared[ $id ] ) ) {
		return;
	}
	if ( post_password_required( $id ) ) {
		return;
	}
	mcupr_el_state_ref( true );
	$html = mcupr_el_render_post( $id );
	if ( '' === $html ) {
		return;
	}
	$a = mcupr_el_assets( array( $id ) );
	mcupr_el_deliver( $a );
	mcupr_el_report_note( $a );
	$prepared[ $id ] = $html;
	$GLOBALS['mcupr_el_prepared'] = $prepared;
}
add_action( 'wp_enqueue_scripts', 'mcupr_el_prepare', 5 );

/** The content of an Elementor page is its printed data (a placeholder goes through the content filters, the page replaces it last, so that paragraphs are not added to it). */
function mcupr_el_content( $content ) {
	static $held = array();
	if ( 'release' === $content ) {
		return $held;
	}
	if ( ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$id = get_the_ID();
	if ( ! $id || ! mcupr_el_is_built( $id ) || post_password_required( $id ) ) {
		return $content;
	}
	$html = isset( $GLOBALS['mcupr_el_prepared'][ $id ] ) ? $GLOBALS['mcupr_el_prepared'][ $id ] : '';
	if ( '' === $html ) {
		mcupr_el_state_ref( true );
		$html = mcupr_el_render_post( $id );
		if ( '' === $html ) {
			return $content;
		}
		$a = mcupr_el_assets( array( $id ) );
		ob_start();
		mcupr_el_deliver( $a );
		$html = ob_get_clean() . $html;
	}
	$key          = 'mcupr-el-' . $id . '-' . count( $held );
	$held[ $key ] = $html;
	return '<!--' . $key . '-->';
}
add_filter( 'the_content', 'mcupr_el_content', 9 );

function mcupr_el_content_late( $content ) {
	if ( false === strpos( $content, '<!--mcupr-el-' ) ) {
		return $content;
	}
	foreach ( mcupr_el_content( 'release' ) as $key => $html ) {
		$content = str_replace( array( '<p><!--' . $key . '--></p>', '<!--' . $key . '-->' ), $html, $content );
	}
	return $content;
}
add_filter( 'the_content', 'mcupr_el_content_late', 99999 );

/** The template of the page: Canvas (nothing around the content), Full width (the theme's header and footer, no title), or the theme's page with a wide content. */
add_filter(
	'template_include',
	static function ( $template ) {
		$id = mcupr_el_current();
		if ( ! $id ) {
			return $template;
		}
		$tpl = (string) get_post_meta( $id, '_wp_page_template', true );
		if ( 'elementor_canvas' === $tpl ) {
			return get_theme_file_path( 'elementor-canvas.php' );
		}
		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			return $template;
		}
		return get_theme_file_path( 'elementor-page.php' );
	},
	99
);

/** "Hide title" of the page settings and the full-width template. */
function mcupr_el_hide_title( $post_id ) {
	$s   = mcupr_el_page_settings( $post_id );
	$tpl = (string) get_post_meta( $post_id, '_wp_page_template', true );
	return ( isset( $s['hide_title'] ) && 'yes' === $s['hide_title'] ) || 'elementor_header_footer' === $tpl;
}

/** The body classes Elementor gave a page (some of its styles hang on them). */
add_filter(
	'body_class',
	static function ( $c ) {
		$id = mcupr_el_current();
		if ( $id ) {
			$c[] = 'elementor-default';
			$c[] = 'elementor-page';
			$c[] = 'elementor-page-' . $id;
			$kit = (int) get_option( 'elementor_active_kit', 0 );
			if ( $kit ) {
				$c[] = 'elementor-kit-' . $kit;
			}
		}
		return $c;
	}
);

/* -------------------------------------------------------------------------------------------- report */

/** What the printing met: widgets that cannot be printed without the plugin, fonts missing, for the report page (kept for the last 200 pages). */
function mcupr_el_report_note( $a ) {
	$st  = mcupr_el_state();
	if ( ! $st['unsupported'] && ! $a['fonts_missing'] ) {
		return;
	}
	$rep = get_option( 'mcupr_el_report', array() );
	$rep = is_array( $rep ) ? $rep : array();
	$id  = (int) get_queried_object_id();
	$rep[ $id ] = array( 'time' => time(), 'unsupported' => $st['unsupported'], 'fonts' => $a['fonts_missing'] );
	$rep = array_slice( $rep, -200, null, true );
	update_option( 'mcupr_el_report', $rep, false );
}

/** All the posts with Elementor data and what is in them (for the report page and the tests): array( id => array( type, title, widgets => name => count, unsupported => names ) ). */
function mcupr_el_inventory( $limit = 500 ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' AND p.post_status <> 'trash' AND p.post_status <> 'auto-draft' ORDER BY p.ID DESC LIMIT %d", (int) $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out = array();
	foreach ( (array) $ids as $id ) {
		$data = mcupr_el_data( (int) $id );
		if ( ! $data ) {
			continue;
		}
		$w   = array();
		$walk = static function ( $els ) use ( &$walk, &$w ) {
			foreach ( (array) $els as $e ) {
				if ( isset( $e['elType'] ) && 'widget' === $e['elType'] && ! empty( $e['widgetType'] ) ) {
					$w[ $e['widgetType'] ] = isset( $w[ $e['widgetType'] ] ) ? $w[ $e['widgetType'] ] + 1 : 1;
				}
				if ( ! empty( $e['elements'] ) ) {
					$walk( $e['elements'] );
				}
			}
		};
		$walk( $data );
		$bad = array();
		foreach ( array_keys( $w ) as $name ) {
			if ( 'global' !== $name && ! function_exists( 'mcupr_el_w_' . str_replace( '-', '_', $name ) ) ) {
				$bad[] = $name;
			}
		}
		$p    = get_post( (int) $id );
		$out[ (int) $id ] = array(
			'type'        => $p ? $p->post_type : '',
			'status'      => $p ? $p->post_status : '',
			'title'       => $p ? get_the_title( $p ) : '',
			'template'    => (string) get_post_meta( (int) $id, '_elementor_template_type', true ),
			'widgets'     => $w,
			'unsupported' => $bad,
			'css_file'    => (bool) mcupr_el_css_file( 'post-' . (int) $id ),
		);
	}
	return $out;
}
