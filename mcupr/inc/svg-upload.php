<?php
/**
 * SVG pictures in the media library (a logo, an icon): WordPress refuses them by default because an SVG is a small program
 * (it can carry scripts, event handlers and links to other sites). So this is allowed only for people who may already publish
 * any markup (the "unfiltered_html" capability: administrators), and every SVG is cleaned before it is stored:
 *  - the file must be well-formed XML whose root is <svg>; entities and a DOCTYPE are refused or removed (XXE, "billion laughs")
 *  - scripts, foreignObject, frames, embeds, audio/video and animation elements that can rewrite links are removed
 *  - every on…= attribute is removed; links (href) may only point inside the picture (#id) or be a small embedded picture (data:image)
 *  - CSS in <style> and style="" loses @import, expression() and url() that points outside the picture
 * The picture then works as any picture: the library shows it, the logo field of the Customizer accepts it.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Who may upload SVG: those who may publish any markup. Change with the `mcupr_svg_upload_allowed` filter. */
function mcupr_svg_allowed() {
	return (bool) apply_filters( 'mcupr_svg_upload_allowed', function_exists( 'current_user_can' ) && current_user_can( 'unfiltered_html' ) );
}

/** CSS made safe: no @import, no expression(), no url() that leaves the picture. */
function mcupr_svg_clean_css( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
	$css = preg_replace( '/@import[^;]*;?/i', '', (string) $css );
	$css = preg_replace( '/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding\s*:/i', 'x(', (string) $css );
	return (string) preg_replace( '/url\(\s*([\'"]?)(?!#|data:image\/(?:png|jpe?g|gif|webp);)[^)]*\)/i', 'none', (string) $css );
}

/**
 * The SVG cleaned, as a string without the XML declaration; '' when it cannot be made safe (not XML, not an SVG, or it uses entities).
 *
 * @param string $svg The contents of the file.
 */
function mcupr_svg_clean( $svg ) {
	$svg = (string) $svg;
	if ( '' === trim( $svg ) || false === stripos( $svg, '<svg' ) || preg_match( '/<!ENTITY/i', $svg ) || ! class_exists( 'DOMDocument' ) ) {
		return '';
	}
	$svg  = preg_replace( '/<!DOCTYPE[^>\[]*(\[.*?\])?\s*>/is', '', $svg );
	$prev = libxml_use_internal_errors( true );
	$dom  = new DOMDocument();
	$ok   = $dom->loadXML( (string) $svg, LIBXML_NONET | LIBXML_NOBLANKS ); // no LIBXML_NOENT: entities are never expanded
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
		return '';
	}

	$xp = new DOMXPath( $dom );
	foreach ( iterator_to_array( $xp->query( '//processing-instruction()' ) ) as $pi ) { // e.g. xml-stylesheet
		$pi->parentNode->removeChild( $pi );
	}
	$drop = array( 'script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'canvas', 'handler', 'listener', 'set', 'animate', 'animatetransform', 'animatemotion', 'animatecolor' );
	foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $el ) {
		$name = strtolower( $el->localName );
		if ( in_array( $name, $drop, true ) ) {
			if ( $el->parentNode ) {
				$el->parentNode->removeChild( $el );
			}
			continue;
		}
		if ( 'style' === $name ) {
			$el->textContent = mcupr_svg_clean_css( $el->textContent );
		}
		foreach ( iterator_to_array( $el->attributes ) as $at ) {
			$an = strtolower( $at->nodeName );
			$av = (string) $at->nodeValue;
			if ( 0 === strpos( $an, 'on' ) ) {
				$el->removeAttributeNode( $at );
			} elseif ( 'href' === $an || 'xlink:href' === $an || 'src' === $an ) {
				if ( ! preg_match( '/^\s*(#|data:image\/(?:png|jpe?g|gif|webp);base64,)/i', $av ) ) {
					$el->removeAttributeNode( $at );
				}
			} elseif ( 'style' === $an ) {
				$at->nodeValue = mcupr_svg_clean_css( $av );
			} elseif ( preg_match( '/javascript\s*:/i', $av ) ) {
				$el->removeAttributeNode( $at );
			}
		}
	}
	return trim( (string) $dom->saveXML( $dom->documentElement ) );
}

/** Width and height of an SVG file in pixels (width/height, else the viewBox); 0 when it says neither. */
function mcupr_svg_size( $file ) {
	$head = ( $file && is_readable( $file ) ) ? (string) file_get_contents( $file, false, null, 0, 4096 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$w    = 0;
	$h    = 0;
	if ( preg_match( '/<svg\b[^>]*>/is', $head, $m ) ) {
		$tag = $m[0];
		if ( preg_match( '/\swidth\s*=\s*["\']\s*([\d.]+)\s*(?:px)?\s*["\']/i', $tag, $a ) && preg_match( '/\sheight\s*=\s*["\']\s*([\d.]+)\s*(?:px)?\s*["\']/i', $tag, $b ) ) {
			$w = (float) $a[1];
			$h = (float) $b[1];
		} elseif ( preg_match( '/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*["\']/i', $tag, $v ) ) {
			$w = (float) $v[1];
			$h = (float) $v[2];
		}
	}
	return array( (int) round( $w ), (int) round( $h ) );
}

if ( function_exists( 'add_filter' ) ) {
	/* the type is allowed (for those who may) … */
	add_filter(
		'upload_mimes',
		static function ( $mimes ) {
			if ( mcupr_svg_allowed() ) {
				$mimes['svg'] = 'image/svg+xml';
			}
			return $mimes;
		}
	);

	/* … WordPress's own check of the contents (it cannot tell an SVG from text) accepts it … */
	add_filter(
		'wp_check_filetype_and_ext',
		static function ( $data, $file, $filename ) {
			if ( mcupr_svg_allowed() && 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) ) {
				$body = ( is_readable( (string) $file ) && filesize( (string) $file ) < 2 * MB_IN_BYTES ) ? (string) file_get_contents( (string) $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false !== stripos( $body, '<svg' ) ) {
					return array( 'ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false );
				}
			}
			return $data;
		},
		10,
		3
	);

	/* … and the file is cleaned before it is stored (a file that cannot be made safe is refused). */
	add_filter(
		'wp_handle_upload_prefilter',
		static function ( $file ) {
			if ( empty( $file['name'] ) || 'svg' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) || ! mcupr_svg_allowed() || empty( $file['tmp_name'] ) ) {
				return $file;
			}
			$clean = mcupr_svg_clean( (string) file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( '' === $clean ) {
				$file['error'] = __( 'This SVG file could not be read, or it cannot be made safe.', 'mcupr' );
				return $file;
			}
			file_put_contents( $file['tmp_name'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
			$file['size'] = strlen( $clean );
			return $file;
		}
	);

	/* The picture works as any picture: the library shows it and it can be a logo (it has a size and a "full" size). */
	add_filter( 'file_is_displayable_image', static function ( $ok, $path ) {
		return $ok || 'svg' === strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
	}, 10, 2 );

	add_filter(
		'wp_generate_attachment_metadata',
		static function ( $meta, $id ) {
			if ( 'image/svg+xml' === get_post_mime_type( $id ) ) {
				$path        = get_attached_file( $id );
				list( $w, $h ) = mcupr_svg_size( $path );
				$meta        = is_array( $meta ) ? $meta : array();
				$meta['width']  = $w;
				$meta['height'] = $h;
				$meta['file']   = _wp_relative_upload_path( (string) $path );
			}
			return $meta;
		},
		10,
		2
	);

	add_filter(
		'image_downsize',
		static function ( $out, $id ) {
			if ( 'image/svg+xml' !== get_post_mime_type( $id ) ) {
				return $out;
			}
			list( $w, $h ) = mcupr_svg_size( get_attached_file( $id ) );
			return array( wp_get_attachment_url( $id ), $w, $h, false );
		},
		10,
		2
	);

	add_filter(
		'wp_prepare_attachment_for_js',
		static function ( $response, $attachment ) {
			if ( isset( $response['mime'] ) && 'image/svg+xml' === $response['mime'] ) {
				list( $w, $h ) = mcupr_svg_size( get_attached_file( $attachment->ID ) );
				$size          = array( 'url' => $response['url'], 'width' => $w, 'height' => $h, 'orientation' => $h > $w ? 'portrait' : 'landscape' );
				$response['sizes'] = array( 'full' => $size, 'thumbnail' => $size );
				$response['image'] = array( 'src' => $response['url'], 'width' => $w, 'height' => $h );
				$response['thumb'] = array( 'src' => $response['url'], 'width' => $w, 'height' => $h );
			}
			return $response;
		},
		10,
		2
	);
}
