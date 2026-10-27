<?php
/**
 * Pictures and PDF inside a post (news, event, work, page):
 *   • "Activity photos" box: pick many pictures from the media library at once, drag to order, remove. The post shows
 *     them as a grid with the same accessible lightbox as the albums, under the text (or where [mcupr_post_gallery] is put).
 *   • "Show this PDF on the page": a tick on a PDF in the "Attached files" box puts a reader under the text – pages scroll
 *     in the page, zoom, jump to a page, full screen – and keeps the download button.
 * Post meta: _mcupr_gallery = array of attachment ids; _mcupr_files[n]['show'] = 1 (see post-tools.php).
 * Both are drawn by mcupr_attachments(), which every single template already calls.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ================================================================== the activity photos */

/** The picture ids of a post's gallery that still exist and are pictures, in order. */
function mcupr_post_gallery_ids( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$ids     = get_post_meta( $post_id, '_mcupr_gallery', true );
	$out     = array();
	foreach ( is_array( $ids ) ? $ids : array() as $id ) {
		$id = (int) $id;
		if ( $id && ! in_array( $id, $out, true ) && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/** True once a post's gallery was placed by the shortcode, so the automatic one is left out. */
function mcupr_post_gallery_placed( $post_id, $set = false ) {
	static $placed = array();
	if ( $set ) {
		$placed[ (int) $post_id ] = true;
	}
	return ! empty( $placed[ (int) $post_id ] );
}

/** The gallery of a post: heading with the count, a grid of lightbox links. */
function mcupr_post_gallery_html( $post_id ) {
	$ids = mcupr_post_gallery_ids( $post_id );
	if ( ! $ids ) {
		return '';
	}
	$hid = 'gal-t-' . (int) $post_id;
	return '<section class="post-gal no-print" aria-labelledby="' . esc_attr( $hid ) . '"><h2 class="post-gal-t" id="' . esc_attr( $hid ) . '">' . mcupr_icon( 'image', 22 ) . esc_html( mcupr_t( 'gallery_title' ) ) . ' <span class="post-gal-n">' . esc_html( sprintf( mcupr_t( 'gallery_count' ), number_format_i18n( count( $ids ) ) ) ) . '</span></h2>' . mcupr_gallery_list( $ids ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** [mcupr_post_gallery] – put the gallery inside the text instead of under it. */
add_shortcode(
	'mcupr_post_gallery',
	static function () {
		$id = get_the_ID();
		if ( ! $id ) {
			return '';
		}
		mcupr_post_gallery_placed( $id, true );
		return mcupr_post_gallery_html( $id );
	}
);

/* ================================================================== the PDF reader */

/** The files of a post that are PDFs marked "show on the page": array( array( title, url, size ), … ). */
function mcupr_post_pdfs( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$out     = array();
	foreach ( mcupr_files( $post_id ) as $f ) {
		if ( empty( $f['show'] ) || 'application/pdf' !== get_post_mime_type( $f['id'] ) ) {
			continue;
		}
		$url = wp_get_attachment_url( $f['id'] );
		if ( ! $url ) {
			continue;
		}
		$out[] = array( 'id' => $f['id'], 'title' => '' !== trim( $f['title'] ) ? $f['title'] : get_the_title( $f['id'] ), 'url' => $url, 'size' => mcupr_file_size_text( $f ) );
	}
	return $out;
}

/** The reader of one PDF. The script draws the pages; without it there is the link. */
function mcupr_pdf_viewer_html( $post_id, $n, $pdf ) {
	$id  = 'pdfv-' . (int) $post_id . '-' . (int) $n;
	$btn = static function ( $attr, $key, $icon ) {
		return '<button type="button" class="pdfv-b" ' . $attr . ' aria-label="' . esc_attr( mcupr_t( $key ) ) . '" title="' . esc_attr( mcupr_t( $key ) ) . '">' . mcupr_feather( $icon, 18 ) . '</button>';
	};
	$o  = '<section class="pdfv no-print" data-pdfv data-src="' . esc_url( $pdf['url'] ) . '" aria-labelledby="' . esc_attr( $id ) . '-t">';
	$o .= '<h2 class="pdfv-t" id="' . esc_attr( $id ) . '-t">' . mcupr_icon( 'file', 22 ) . esc_html( $pdf['title'] ) . '</h2>';
	$o .= '<div class="pdfv-bar" role="toolbar" aria-label="' . esc_attr( mcupr_t( 'pdf_label' ) ) . '" hidden>';
	$o .= $btn( 'data-prev', 'pdf_prev', 'chevron-left' );
	$o .= '<label class="pdfv-pg"><span class="sr-only">' . esc_html( mcupr_t( 'pdf_page' ) ) . '</span><input type="number" min="1" value="1" inputmode="numeric" data-pg> <span aria-hidden="true">/</span> <span data-total>–</span></label>';
	$o .= $btn( 'data-next', 'pdf_next', 'chevron-right' );
	$o .= '<span class="pdfv-sp" aria-hidden="true"></span>';
	$o .= $btn( 'data-out', 'pdf_zoom_out', 'minus' ) . $btn( 'data-in', 'pdf_zoom_in', 'plus' ) . $btn( 'data-fit', 'pdf_fit', 'maximize' ) . $btn( 'data-full', 'pdf_full', 'maximize-2' );
	$o .= '<div class="pdfv-find" role="search"><label class="pdfv-q"><span class="sr-only">' . esc_html( mcupr_t( 'pdf_search' ) ) . '</span>' . mcupr_feather( 'search', 16 ) . '<input type="search" data-q placeholder="' . esc_attr( mcupr_t( 'pdf_search_ph' ) ) . '" autocomplete="off" enterkeyhint="search"></label>' . $btn( 'data-hit-prev disabled', 'pdf_hit_prev', 'chevron-up' ) . $btn( 'data-hit-next disabled', 'pdf_hit_next', 'chevron-down' ) . '<span class="pdfv-hits" data-hits role="status" aria-live="polite"></span></div>';
	$o .= '<a class="pdfv-open" href="' . esc_url( $pdf['url'] ) . '" target="_blank" rel="noopener">' . mcupr_icon( 'download', 18 ) . '<span>' . esc_html( mcupr_t( 'pdf_open' ) ) . '</span><span class="sr-only"> ' . esc_html( mcupr_t( 'new_tab' ) ) . '</span></a>';
	$o .= '</div>';
	$o .= '<div class="pdfv-stage" data-stage tabindex="0" role="region" aria-label="' . esc_attr( mcupr_t( 'pdf_label' ) ) . ' – ' . esc_attr( $pdf['title'] ) . '"><p class="pdfv-status" data-status role="status">' . esc_html( mcupr_t( 'pdf_loading' ) ) . '</p></div>';
	$o .= '<p class="pdfv-alt"><a href="' . esc_url( $pdf['url'] ) . '" target="_blank" rel="noopener">' . esc_html( mcupr_t( 'pdf_open' ) ) . '</a>' . ( '' !== $pdf['size'] ? ' <span class="pdfv-sz">(' . esc_html( $pdf['size'] ) . ')</span>' : '' ) . '</p>';
	return $o . '</section>';
}

/** What the reader script needs: its own words, the pdf.js files. Printed once. */
function mcupr_pdf_viewer_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	wp_enqueue_script( 'mcupr-pdf-view', get_theme_file_uri( 'assets/js/pdf-view.js' ), array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script(
		'mcupr-pdf-view',
		'mcuprPdf',
		array(
			'lib'    => get_theme_file_uri( 'assets/vendor/pdfjs/pdf.min.js' ),
			'worker' => get_theme_file_uri( 'assets/vendor/pdfjs/pdf.worker.min.js' ),
			'cmaps'  => get_theme_file_uri( 'assets/vendor/pdfjs/cmaps/' ),
			'fonts'  => get_theme_file_uri( 'assets/vendor/pdfjs/standard_fonts/' ),
			't'      => array(
				'loading' => mcupr_t( 'pdf_loading' ),
				'fail'    => mcupr_t( 'pdf_fail' ),
				'page'    => mcupr_t( 'pdf_page' ),
				'hits'    => mcupr_t( 'pdf_hits' ),
				'nohit'   => mcupr_t( 'pdf_nohit' ),
				'reading' => mcupr_t( 'pdf_reading' ),
				'noscan'  => mcupr_t( 'pdf_noscan' ),
			),
		)
	);
}

/** Pictures and readers of a post, drawn by mcupr_attachments(). */
function mcupr_post_media( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( post_password_required( $post_id ) ) {
		return;
	}
	if ( ! mcupr_post_gallery_placed( $post_id ) ) {
		echo mcupr_post_gallery_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$pdfs = mcupr_post_pdfs( $post_id );
	foreach ( $pdfs as $n => $pdf ) {
		echo mcupr_pdf_viewer_html( $post_id, $n, $pdf ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $pdfs ) {
		mcupr_pdf_viewer_assets();
	}
	if ( function_exists( 'mcupr_google_media' ) ) {
		mcupr_google_media( $post_id ); // Google Drive files and Google Photos albums (inc/google-embed.php)
	}
}

/* ================================================================== admin: the "Activity photos" box */

function mcupr_gallery_box_add() {
	foreach ( mcupr_tool_types() as $type ) {
		add_meta_box( 'mcupr_gallery', __( 'Activity photos', 'mcupr' ), 'mcupr_gallery_box', $type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'mcupr_gallery_box_add' );

function mcupr_gallery_box( $post ) {
	wp_nonce_field( 'mcupr_gallery_save', 'mcupr_gallery_nonce' );
	echo '<p class="description">' . esc_html__( 'Choose the photos of the activity (you can pick many at once). Drag a photo to change the order. Visitors see them as a grid under the text and can open each one large, with the caption you wrote in the media library. To put the grid inside the text, type [mcupr_post_gallery] where you want it.', 'mcupr' ) . '</p>';
	echo '<ul class="mcupr-gal" data-gal-list>';
	foreach ( mcupr_post_gallery_ids( $post->ID ) as $id ) {
		$src = wp_get_attachment_image_url( $id, 'thumbnail' );
		echo '<li data-id="' . (int) $id . '"><img alt="" src="' . esc_url( (string) $src ) . '"><input type="hidden" name="mcupr_gallery[]" value="' . (int) $id . '"><button type="button" class="button-link-delete" data-gal-del aria-label="' . esc_attr__( 'Remove this photo', 'mcupr' ) . '">&times;</button></li>';
	}
	echo '</ul>';
	echo '<p><button type="button" class="button button-primary" data-gal-add>' . esc_html__( 'Choose photos', 'mcupr' ) . '</button> <span class="description" data-gal-count></span></p>';
}

function mcupr_gallery_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_gallery_nonce', 'mcupr_gallery_save' ) ) {
		return;
	}
	$in  = isset( $_POST['mcupr_gallery'] ) && is_array( $_POST['mcupr_gallery'] ) ? array_map( 'absint', wp_unslash( $_POST['mcupr_gallery'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$out = array();
	foreach ( array_slice( $in, 0, 300 ) as $id ) {
		if ( $id && ! in_array( $id, $out, true ) && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) ) {
			$out[] = $id;
		}
	}
	if ( $out ) {
		update_post_meta( $post_id, '_mcupr_gallery', $out );
	} else {
		delete_post_meta( $post_id, '_mcupr_gallery' );
	}
}
add_action( 'save_post', 'mcupr_gallery_save' );

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, mcupr_tool_types(), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'mcupr-gallery-admin', get_theme_file_uri( 'assets/js/post-gallery-admin.js' ), array( 'jquery-ui-sortable' ), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-gallery-admin',
			'mcuprGal',
			array(
				'title'  => __( 'Choose the photos of the activity', 'mcupr' ),
				'button' => __( 'Add these photos', 'mcupr' ),
				'remove' => __( 'Remove this photo', 'mcupr' ),
				/* translators: %s: number of photos */
				'count'  => __( '%s photos chosen', 'mcupr' ),
			)
		);
		wp_add_inline_style( 'wp-admin', '.mcupr-gal{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 10px;padding:0;list-style:none}.mcupr-gal li{position:relative;width:96px;height:96px;margin:0;border:1px solid #c3c4c7;border-radius:8px;overflow:hidden;background:#f6f7f7;cursor:move}.mcupr-gal img{display:block;width:100%;height:100%;object-fit:cover}.mcupr-gal [data-gal-del]{position:absolute;top:2px;right:2px;width:28px;height:28px;border-radius:50%;background:#fff;color:#b32d2e;font-size:20px;line-height:26px;text-align:center;text-decoration:none;border:1px solid #c3c4c7;cursor:pointer}' );
	}
);
