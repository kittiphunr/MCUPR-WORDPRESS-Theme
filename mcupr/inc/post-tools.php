<?php
/**
 * Per-post tools: view counter (editable) and file attachments (images / PDF / any file) with a size label,
 * a download counter and an optional download limit.
 *
 * Post meta: _mcupr_views (int); _mcupr_files = array( array( id, title, size, max, count ), … )
 * Download link: /?mcupr_dl=POSTID-INDEX  (counted, limit enforced, file streamed by PHP).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Post types that have a view counter and file attachments. */
function mcupr_tool_types() {
	return apply_filters( 'mcupr_tool_types', array( 'post', 'page', 'mcupr_event', 'mcupr_work' ) );
}

/* ================================================================== views */

function mcupr_views( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	return (int) get_post_meta( $post_id, '_mcupr_views', true );
}

/** Count one view of a singular page: not for editors, bots, prefetch, or the same visitor again within 6 hours. */
function mcupr_count_view() {
	if ( ! is_singular( mcupr_tool_types() ) || is_preview() || mcupr_is_bot() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'GET' !== $method || ( isset( $_SERVER['HTTP_PURPOSE'] ) && 'prefetch' === strtolower( (string) $_SERVER['HTTP_PURPOSE'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$id = get_queried_object_id();
	if ( ! $id || current_user_can( 'edit_post', $id ) || post_password_required( $id ) ) {
		return;
	}
	$seen = isset( $_COOKIE['mcupr_seen'] ) ? array_filter( array_map( 'absint', explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['mcupr_seen'] ) ) ) ) ) : array();
	if ( in_array( $id, $seen, true ) ) {
		return;
	}
	$seen[] = $id;
	$seen   = array_slice( $seen, -30 );
	if ( ! headers_sent() ) {
		// first-party, functional (de-duplicates the counter); expires in 6 hours
		$value = implode( '.', $seen );
		$exp   = time() + 6 * HOUR_IN_SECONDS;
		mcupr_set_cookie( 'mcupr_seen', $value, $exp, false, COOKIEPATH ? COOKIEPATH : '/' );
	}
	global $wpdb;
	$done = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = '_mcupr_views'", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( ! $done ) {
		add_post_meta( $id, '_mcupr_views', 1, true );
	}
	wp_cache_delete( $id, 'post_meta' );
	mcupr_stats_hit();
}
add_action( 'template_redirect', 'mcupr_count_view', 20 );

/** An eye icon and "1,234 views" for article meta lines. */
function mcupr_views_html( $post_id = 0 ) {
	if ( ! get_theme_mod( 'mcupr_show_views', true ) ) {
		return '';
	}
	$n = mcupr_views( $post_id );
	return '<span class="pm-chip inline-flex items-center gap-1.5">' . mcupr_icon( 'eye', 16 ) . esc_html( sprintf( mcupr_t( 'views_fmt' ), number_format_i18n( $n ) ) ) . '</span>';
}

function mcupr_views_box_add() {
	foreach ( mcupr_tool_types() as $type ) {
		add_meta_box( 'mcupr_views', __( 'Number of views', 'mcupr' ), 'mcupr_views_box', $type, 'side', 'default' );
	}
}
add_action( 'add_meta_boxes', 'mcupr_views_box_add' );

function mcupr_views_box( $post ) {
	wp_nonce_field( 'mcupr_views_save', 'mcupr_views_nonce' );
	echo '<p><label><input type="number" min="0" step="1" class="widefat" name="mcupr_views_set" value="' . esc_attr( mcupr_views( $post->ID ) ) . '"></label></p>';
	echo '<p class="description">' . esc_html__( 'Counted automatically (each visitor once per 6 hours; editors and search-engine robots are not counted). You can type a different number here to correct or reset it.', 'mcupr' ) . '</p>';
}

function mcupr_views_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_views_nonce', 'mcupr_views_save' ) || ! isset( $_POST['mcupr_views_set'] ) ) {
		return;
	}
	update_post_meta( $post_id, '_mcupr_views', absint( wp_unslash( $_POST['mcupr_views_set'] ) ) );
}
add_action( 'save_post', 'mcupr_views_save' );

/** Admin lists: a "Views" column. */
add_action(
	'admin_init',
	static function () {
		foreach ( mcupr_tool_types() as $type ) {
			add_filter(
				"manage_{$type}_posts_columns",
				static function ( $cols ) {
					$cols['mcupr_views'] = __( 'Views', 'mcupr' );
					return $cols;
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				static function ( $col, $id ) {
					if ( 'mcupr_views' === $col ) {
						echo esc_html( number_format_i18n( mcupr_views( $id ) ) );
					}
				},
				10,
				2
			);
		}
	}
);

/* ================================================================== file attachments */

function mcupr_files( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$files   = get_post_meta( $post_id, '_mcupr_files', true );
	$out     = array();
	foreach ( is_array( $files ) ? $files : array() as $f ) {
		if ( ! empty( $f['id'] ) && get_post( (int) $f['id'] ) && 'attachment' === get_post_type( (int) $f['id'] ) ) {
			$out[] = array(
				'id'    => (int) $f['id'],
				'title' => isset( $f['title'] ) ? (string) $f['title'] : '',
				'size'  => isset( $f['size'] ) ? (string) $f['size'] : '',
				'max'   => isset( $f['max'] ) ? (int) $f['max'] : 0,
				'count' => isset( $f['count'] ) ? (int) $f['count'] : 0,
				'show'  => ! empty( $f['show'] ) ? 1 : 0, // a PDF read on the page (inc/post-media.php)
			);
		}
	}
	return $out;
}

/** Short type label: PDF, Word, Excel, Image… */
function mcupr_file_kind( $att_id ) {
	$mime = (string) get_post_mime_type( $att_id );
	$ext  = strtoupper( pathinfo( (string) get_attached_file( $att_id ), PATHINFO_EXTENSION ) );
	if ( 0 === strpos( $mime, 'image/' ) ) {
		return array( 'image', mcupr_t( 'file_image' ) . ( $ext ? ' ' . $ext : '' ) );
	}
	return array( 'file', $ext ? $ext : mcupr_t( 'file_generic' ) );
}

function mcupr_file_size_text( $f ) {
	if ( '' !== trim( $f['size'] ) ) {
		return $f['size'];
	}
	$p = get_attached_file( $f['id'] );
	return $p && file_exists( $p ) ? size_format( filesize( $p ), 1 ) : '';
}

function mcupr_download_url( $post_id, $index ) {
	return add_query_arg( 'mcupr_dl', $post_id . '-' . $index, home_url( '/' ) );
}

/** The "attached files" list of a post. */
function mcupr_attachments( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	if ( post_password_required( $post_id ) ) {
		return;
	}
	mcupr_post_media( $post_id ); // the activity photos and the PDF readers come before the download list
	$files = mcupr_files( $post_id );
	if ( ! $files ) {
		return;
	}
	echo '<section class="files no-print" aria-labelledby="files-t-' . (int) $post_id . '"><h2 class="files-t" id="files-t-' . (int) $post_id . '">' . mcupr_icon( 'file', 22 ) . esc_html( mcupr_t( 'files_title' ) ) . '</h2><ul class="files-list">'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $files as $i => $f ) {
		list( $kind, $kind_label ) = mcupr_file_kind( $f['id'] );
		$name   = '' !== trim( $f['title'] ) ? $f['title'] : get_the_title( $f['id'] );
		$meta   = array( $kind_label );
		$size   = mcupr_file_size_text( $f );
		if ( '' !== $size ) {
			$meta[] = $size;
		}
		$meta[] = sprintf( mcupr_t( 'file_count' ), number_format_i18n( $f['count'] ) );
		$full   = $f['max'] > 0 && $f['count'] >= $f['max'];
		if ( $f['max'] > 0 && ! $full ) {
			$meta[] = sprintf( mcupr_t( 'file_left' ), number_format_i18n( $f['max'] - $f['count'] ) );
		}
		echo '<li class="file-row"><span class="file-ic" aria-hidden="true">';
		if ( 'image' === $kind && wp_get_attachment_image_src( $f['id'], 'thumbnail' ) ) {
			echo wp_get_attachment_image( $f['id'], 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo mcupr_icon( 'file', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</span><span class="file-main"><b class="file-name">' . esc_html( $name ) . '</b><span class="file-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span></span>';
		if ( $full ) {
			echo '<span class="file-full">' . esc_html( mcupr_t( 'file_full' ) ) . '</span>';
		} else {
			echo '<a class="file-dl" href="' . esc_url( mcupr_download_url( $post_id, $i ) ) . '">' . mcupr_icon( 'download', 18 ) . '<span>' . esc_html( mcupr_t( 'download' ) ) . '</span><span class="sr-only"> ' . esc_html( $name ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</li>';
	}
	echo '</ul></section>';
}

/** Stream a file: /?mcupr_dl=POSTID-INDEX */
function mcupr_download_handler() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['mcupr_dl'] ) || ! preg_match( '/^(\d+)-(\d+)$/', sanitize_text_field( wp_unslash( $_GET['mcupr_dl'] ) ), $m ) ) {
		return;
	}
	$post_id = (int) $m[1];
	$index   = (int) $m[2];
	$post    = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, mcupr_tool_types(), true ) || ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post_id ) ) || post_password_required( $post ) ) {
		wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
	}
	if ( function_exists( 'mcupr_aud_can_view' ) && ! mcupr_aud_can_view( $post_id ) ) { // content for students only keeps its files for students only
		wp_die( esc_html( mcupr_t( 'stu_denied_t' ) ), '', array( 'response' => 403 ) );
	}
	$raw   = get_post_meta( $post_id, '_mcupr_files', true );
	$files = mcupr_files( $post_id );
	if ( ! is_array( $raw ) || ! isset( $files[ $index ] ) ) {
		wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
	}
	$f    = $files[ $index ];
	$path = get_attached_file( $f['id'] );
	$real = $path ? realpath( $path ) : false;
	$up   = wp_get_upload_dir();
	$base = realpath( $up['basedir'] );
	if ( ! $real || ! $base || ! mcupr_path_inside( $real, $base ) || ! is_readable( $real ) ) {
		wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
	}
	if ( $f['max'] > 0 && $f['count'] >= $f['max'] ) {
		wp_die( esc_html( mcupr_t( 'file_full' ) ), '', array( 'response' => 403 ) );
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'GET' === $method && ! mcupr_is_bot() ) {
		$rows = get_post_meta( $post_id, '_mcupr_files', true );
		// the stored row that belongs to this attachment (indexes of mcupr_files() skip deleted attachments)
		$seen = -1;
		foreach ( $rows as $k => $r ) {
			if ( ! empty( $r['id'] ) && get_post( (int) $r['id'] ) && 'attachment' === get_post_type( (int) $r['id'] ) ) {
				$seen++;
				if ( $seen === $index ) {
					$rows[ $k ]['count'] = ( isset( $r['count'] ) ? (int) $r['count'] : 0 ) + 1;
					break;
				}
			}
		}
		update_post_meta( $post_id, '_mcupr_files', $rows );
	}
	$ext  = pathinfo( $real, PATHINFO_EXTENSION );
	$name = sanitize_file_name( ( '' !== trim( $f['title'] ) ? $f['title'] : pathinfo( $real, PATHINFO_FILENAME ) ) . ( $ext ? '.' . $ext : '' ) );
	$mime = (string) get_post_mime_type( $f['id'] );
	nocache_headers();
	header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
	header( 'Content-Disposition: attachment; filename="' . rawurlencode( basename( $real ) ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
	header( 'Content-Length: ' . filesize( $real ) );
	header( 'X-Content-Type-Options: nosniff' );
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	if ( 'HEAD' !== $method ) {
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	exit;
}
add_action( 'template_redirect', 'mcupr_download_handler', 2 );

/* ------------------------------------------------------------------ admin: the "Attached files" box */

function mcupr_files_box_add() {
	foreach ( mcupr_tool_types() as $type ) {
		add_meta_box( 'mcupr_files', __( 'Attached files (images, PDF, documents)', 'mcupr' ), 'mcupr_files_box', $type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'mcupr_files_box_add' );

function mcupr_files_row( $i, $f ) {
	$att  = $f['id'] ? get_the_title( $f['id'] ) . ' – ' . basename( (string) get_attached_file( $f['id'] ) ) : '';
	echo '<div class="mcupr-file-row">';
	echo '<input type="hidden" name="mcupr_files[' . esc_attr( $i ) . '][id]" value="' . esc_attr( $f['id'] ) . '" data-f="id">';
	echo '<p><strong data-f="name">' . esc_html( $att ) . '</strong> <button type="button" class="button" data-pick>' . esc_html__( 'Choose file', 'mcupr' ) . '</button> <button type="button" class="button-link button-link-delete" data-remove>' . esc_html__( 'Remove', 'mcupr' ) . '</button></p>';
	echo '<p class="mcupr-file-grid"><label>' . esc_html__( 'Name shown to visitors', 'mcupr' ) . '<br><input type="text" class="widefat" name="mcupr_files[' . esc_attr( $i ) . '][title]" value="' . esc_attr( $f['title'] ) . '"></label>';
	echo '<label>' . esc_html__( 'File size text (empty = automatic)', 'mcupr' ) . '<br><input type="text" class="widefat" placeholder="1.2 MB" name="mcupr_files[' . esc_attr( $i ) . '][size]" value="' . esc_attr( $f['size'] ) . '"></label>';
	echo '<label>' . esc_html__( 'Downloads so far (you can edit it)', 'mcupr' ) . '<br><input type="number" min="0" class="widefat" name="mcupr_files[' . esc_attr( $i ) . '][count]" value="' . esc_attr( $f['count'] ) . '"></label>';
	echo '<label>' . esc_html__( 'Maximum downloads (0 = unlimited)', 'mcupr' ) . '<br><input type="number" min="0" class="widefat" name="mcupr_files[' . esc_attr( $i ) . '][max]" value="' . esc_attr( $f['max'] ) . '"></label></p>';
	echo '<p class="mcupr-file-show"><label><input type="checkbox" data-f="show" name="mcupr_files[' . esc_attr( $i ) . '][show]" value="1"' . checked( ! empty( $f['show'] ), true, false ) . ( $f['id'] && 'application/pdf' !== get_post_mime_type( $f['id'] ) ? ' disabled' : '' ) . '> <strong>' . esc_html__( 'Show this PDF on the page', 'mcupr' ) . '</strong> – ' . esc_html__( 'visitors read it right under the text (scroll, zoom, full screen) and still get the download button. Only for PDF files.', 'mcupr' ) . '</label></p>';
	echo '</div>';
}

function mcupr_files_box( $post ) {
	wp_nonce_field( 'mcupr_files_save', 'mcupr_files_nonce' );
	echo '<p class="description">' . esc_html__( 'Add images, PDF or other files to this post. Visitors see a list with the size and how many times each file was downloaded; set a maximum number of downloads if you want to limit it.', 'mcupr' ) . '</p>';
	echo '<div id="mcupr-files" data-next="' . esc_attr( count( mcupr_files( $post->ID ) ) ) . '">';
	foreach ( mcupr_files( $post->ID ) as $i => $f ) {
		mcupr_files_row( $i, $f );
	}
	echo '</div>';
	echo '<p><button type="button" class="button button-primary" id="mcupr-files-add">' . esc_html__( 'Add a file', 'mcupr' ) . '</button></p>';
	echo '<template id="mcupr-files-tpl">';
	mcupr_files_row( '__i__', array( 'id' => 0, 'title' => '', 'size' => '', 'max' => 0, 'count' => 0, 'show' => 0 ) );
	echo '</template>';
}

function mcupr_files_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_files_nonce', 'mcupr_files_save' ) ) {
		return;
	}
	$in  = isset( $_POST['mcupr_files'] ) && is_array( $_POST['mcupr_files'] ) ? wp_unslash( $_POST['mcupr_files'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$out = array();
	foreach ( $in as $row ) {
		$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			continue;
		}
		$out[] = array(
			'id'    => $id,
			'title' => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
			'size'  => isset( $row['size'] ) ? sanitize_text_field( $row['size'] ) : '',
			'max'   => isset( $row['max'] ) ? absint( $row['max'] ) : 0,
			'count' => isset( $row['count'] ) ? absint( $row['count'] ) : 0,
			'show'  => ! empty( $row['show'] ) && 'application/pdf' === get_post_mime_type( $id ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_post_meta( $post_id, '_mcupr_files', $out );
	} else {
		delete_post_meta( $post_id, '_mcupr_files' );
	}
}
add_action( 'save_post', 'mcupr_files_save' );

/** Media picker + row handling for the box (post edit screens only). */
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
		wp_enqueue_script( 'mcupr-admin', MCUPR_URI . '/assets/js/admin.js', array(), MCUPR_VERSION, true );
		wp_localize_script( 'mcupr-admin', 'mcuprAdmin', array( 'choose' => __( 'Choose a file', 'mcupr' ), 'use' => __( 'Use this file', 'mcupr' ) ) );
		wp_add_inline_style( 'wp-admin', '.mcupr-file-row{border:1px solid #c3c4c7;border-radius:8px;padding:10px 14px;margin:0 0 10px;background:#fff}.mcupr-file-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:10px}' );
	}
);
