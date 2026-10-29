<?php
/**
 * Pictures already in the Media Library: turn them all into WebP, cut to the size set in MCUPR → Pictures (WebP), and remove the
 * original files (and the old thumbnails) from the website. The pictures keep their place in the library – same entry, same
 * title, description, alt text and folder – only the file behind it changes, and every address of the old files inside pages,
 * widgets, settings and page-builder data is rewritten to the new ones, so nothing on the site breaks.
 *
 * It works in small batches from the page (ajax), so a library of thousands of pictures does not hit a time limit, and it
 * can be stopped and started again at any point: a picture is only changed when its WebP is written and its entry saved, and the
 * old files are removed last.
 *
 * Same rules as the upload conversion (inc/images.php): only JPEG and PNG; a picture under the minimum size, or one where WebP would
 * not be at least 10% smaller, is kept as it is (and remembered, so it is not tried again unless asked).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_IMGB_KEPT = '_mcupr_img_kept';

/** JPEG and PNG attachments still to look at (the ones kept earlier are left out unless $again). */
function mcupr_imgb_where( $again = false ) {
	global $wpdb;
	$w = "p.post_type = 'attachment' AND p.post_mime_type IN ('image/jpeg','image/png')";
	if ( ! $again ) {
		$w .= $wpdb->prepare( " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} k WHERE k.post_id = p.ID AND k.meta_key = %s)", MCUPR_IMGB_KEPT ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return $w;
}

function mcupr_imgb_count( $again = false ) {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE " . mcupr_imgb_where( $again ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
}

/** Every file of one attachment on disk: the picture, the big-image original and every thumbnail. */
function mcupr_imgb_files( $file, $meta ) {
	$dir = dirname( $file );
	$out = array( $file );
	if ( is_array( $meta ) ) {
		if ( ! empty( $meta['original_image'] ) ) {
			$out[] = $dir . '/' . $meta['original_image'];
		}
		foreach ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $s ) {
			if ( ! empty( $s['file'] ) ) {
				$out[] = $dir . '/' . $s['file'];
			}
		}
	}
	return array_values( array_unique( $out ) );
}

function mcupr_imgb_bytes( $files ) {
	$n = 0;
	foreach ( $files as $f ) {
		if ( is_file( $f ) ) {
			$n += (int) filesize( $f );
		}
	}
	return $n;
}

/**
 * Replace the old addresses by the new ones inside a stored value: plain text, JSON (page builders write the slashes as \/)
 * and PHP-serialised data (rebuilt, so the lengths stay right).
 */
function mcupr_imgb_swap( $v, $map ) {
	if ( is_string( $v ) ) {
		if ( is_serialized( $v ) ) {
			$u = @unserialize( $v, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions
			if ( false === $u && 'b:0;' !== $v ) {
				return $v;
			}
			$n = mcupr_imgb_swap( $u, $map );
			return $n === $u ? $v : serialize( $n ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}
		$from = array();
		$to   = array();
		foreach ( $map as $a => $b ) {
			$from[] = $a;
			$to[]   = $b;
			$from[] = str_replace( '/', '\/', $a );
			$to[]   = str_replace( '/', '\/', $b );
		}
		return str_replace( $from, $to, $v );
	}
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$v[ $k ] = mcupr_imgb_swap( $x, $map );
		}
	}
	return $v;
}

/** Rewrite the addresses in posts, post meta, options and term meta. $map: path under the site (from the uploads folder on) => new path. */
function mcupr_imgb_rewrite( $map, $stems ) {
	global $wpdb;
	if ( ! $map || ! $stems ) {
		return 0;
	}
	$like = array();
	foreach ( $stems as $s ) {
		$like[] = '%' . $wpdb->esc_like( $s ) . '%';
		$like[] = '%' . $wpdb->esc_like( str_replace( '/', '\/', $s ) ) . '%'; // as JSON writes it
	}
	$changed = 0;
	$or      = static function ( $col ) use ( $like, $wpdb ) {
		$p = array();
		foreach ( $like as $l ) {
			$p[] = $wpdb->prepare( "$col LIKE %s", $l ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return '(' . implode( ' OR ', $p ) . ')';
	};
	// posts
	$rows = $wpdb->get_results( "SELECT ID, post_content, post_excerpt, post_content_filtered FROM {$wpdb->posts} WHERE " . $or( 'post_content' ) . ' OR ' . $or( 'post_excerpt' ) . ' OR ' . $or( 'post_content_filtered' ), ARRAY_A ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$new = array();
		foreach ( array( 'post_content', 'post_excerpt', 'post_content_filtered' ) as $c ) {
			$x = mcupr_imgb_swap( $r[ $c ], $map );
			if ( $x !== $r[ $c ] ) {
				$new[ $c ] = $x;
			}
		}
		if ( $new ) {
			$wpdb->update( $wpdb->posts, $new, array( 'ID' => (int) $r['ID'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( (int) $r['ID'] );
			$changed++;
		}
	}
	// post meta and term meta (not the attachment's own file entries: they are written afresh)
	foreach ( array( array( $wpdb->postmeta, 'meta_id', 'post_id' ), array( $wpdb->termmeta, 'meta_id', 'term_id' ) ) as $t ) {
		$rows = $wpdb->get_results( "SELECT {$t[1]} AS id, {$t[2]} AS oid, meta_key, meta_value FROM {$t[0]} WHERE " . $or( 'meta_value' ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			if ( in_array( $r['meta_key'], array( '_wp_attached_file', '_wp_attachment_metadata' ), true ) ) {
				continue;
			}
			$x = mcupr_imgb_swap( $r['meta_value'], $map );
			if ( $x !== $r['meta_value'] ) {
				$wpdb->update( $t[0], array( 'meta_value' => $x ), array( $t[1] => (int) $r['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( $t[0] === $wpdb->postmeta ) {
					wp_cache_delete( (int) $r['oid'], 'post_meta' );
				} else {
					wp_cache_delete( (int) $r['oid'], 'term_meta' );
				}
				$changed++;
			}
		}
	}
	// options (settings, widgets, theme settings); the temporary ones are rebuilt by themselves
	$rows = $wpdb->get_results( "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE " . $or( 'option_value' ) . " AND option_name NOT LIKE '\\_transient%' AND option_name NOT LIKE '\\_site\\_transient%'", ARRAY_A ); // phpcs:ignore WordPress.DB
	foreach ( (array) $rows as $r ) {
		$x = mcupr_imgb_swap( $r['option_value'], $map );
		if ( $x !== $r['option_value'] ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => $x ), array( 'option_id' => (int) $r['option_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( $r['option_name'], 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$changed++;
		}
	}
	return $changed;
}

/**
 * Convert one attachment. Returns array( 'st' => converted | kept | missing | unsupported | error, 'before' => bytes, 'after' => bytes ).
 * Nothing of the old picture is removed until the new one is written, saved in its entry and the addresses are rewritten.
 */
function mcupr_imgb_one( $id ) {
	$id   = (int) $id;
	$file = get_attached_file( $id );
	$mime = get_post_mime_type( $id );
	if ( ! $file || ! is_readable( $file ) || ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
		return array( 'st' => 'missing', 'before' => 0, 'after' => 0 );
	}
	if ( ! mcupr_img_supported() ) {
		return array( 'st' => 'unsupported', 'before' => 0, 'after' => 0 );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$o     = mcupr_img_opts();
	$meta  = wp_get_attachment_metadata( $id );
	$old   = mcupr_imgb_files( $file, $meta );
	$bytes = mcupr_imgb_bytes( $old );
	$main  = (int) filesize( $file );
	$keep  = static function ( $why ) use ( $id, $bytes ) {
		update_post_meta( $id, MCUPR_IMGB_KEPT, $why );
		return array( 'st' => 'kept', 'before' => $bytes, 'after' => $bytes );
	};
	if ( $main < $o['min_kb'] * 1024 ) {
		return $keep( 'small' );
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return array( 'st' => 'error', 'before' => $bytes, 'after' => $bytes );
	}
	if ( method_exists( $editor, 'maybe_exif_rotate' ) && 'image/jpeg' === $mime ) {
		$editor->maybe_exif_rotate();
	}
	$size = $editor->get_size();
	if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) && max( $size['width'], $size['height'] ) > $o['max'] ) {
		$editor->resize( $o['max'], $o['max'], false );
	}
	$editor->set_quality( $o['quality'] );
	$info  = pathinfo( $file );
	$dest  = trailingslashit( $info['dirname'] ) . wp_unique_filename( $info['dirname'], $info['filename'] . '.webp' );
	$saved = $editor->save( $dest, 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
		return array( 'st' => 'error', 'before' => $bytes, 'after' => $bytes );
	}
	$dest = $saved['path'];
	if ( (int) filesize( $dest ) <= 0 || (int) filesize( $dest ) > $main * 0.9 ) {
		wp_delete_file( $dest );
		return $keep( 'no-gain' );
	}

	// the entry now points at the WebP; the thumbnails are made again from it (and no second "-scaled" copy)
	$rel_old = (string) get_post_meta( $id, '_wp_attached_file', true );
	update_attached_file( $id, $dest );
	$rel_new = (string) get_post_meta( $id, '_wp_attached_file', true );
	wp_update_post( array( 'ID' => $id, 'post_mime_type' => 'image/webp', 'guid' => trailingslashit( dirname( wp_get_attachment_url( $id ) ) ) . wp_basename( $dest ) ) );
	add_filter( 'big_image_size_threshold', '__return_false', 99 );
	$new_meta = wp_generate_attachment_metadata( $id, $dest );
	remove_filter( 'big_image_size_threshold', '__return_false', 99 );
	if ( is_array( $new_meta ) ) {
		wp_update_attachment_metadata( $id, $new_meta );
	}
	$new = mcupr_imgb_files( $dest, $new_meta );

	// old address -> new address: the picture, the big-image original, and each thumbnail by its size name
	$base = (string) wp_parse_url( wp_get_upload_dir()['baseurl'], PHP_URL_PATH ) . '/';
	$rd   = dirname( $rel_old );
	$rd   = '.' === $rd ? '' : $rd . '/';
	$nd   = dirname( $rel_new );
	$nd   = '.' === $nd ? '' : $nd . '/';
	$map  = array( $base . $rel_old => $base . $rel_new );
	if ( is_array( $meta ) ) {
		if ( ! empty( $meta['original_image'] ) ) {
			$map[ $base . $rd . $meta['original_image'] ] = $base . $rel_new;
		}
		foreach ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $name => $s ) {
			if ( empty( $s['file'] ) ) {
				continue;
			}
			$to = isset( $new_meta['sizes'][ $name ]['file'] ) ? $nd . $new_meta['sizes'][ $name ]['file'] : $rel_new;
			$map[ $base . $rd . $s['file'] ] = $base . $to;
		}
	}
	uksort( $map, static function ( $a, $b ) {
		return strlen( $b ) <=> strlen( $a );
	} );
	$stems = array();
	foreach ( array_keys( $map ) as $k ) {
		$k = (string) preg_replace( '/-(?:scaled|\d+x\d+)?\.[A-Za-z0-9]+$|\.[A-Za-z0-9]+$/', '', substr( $k, strlen( $base ) ) );
		$stems[ $k ] = true;
	}
	mcupr_imgb_rewrite( $map, array_keys( $stems ) );

	// last: the old files go
	foreach ( $old as $f ) {
		if ( $f !== $dest && ! in_array( $f, $new, true ) && is_file( $f ) ) {
			wp_delete_file( $f );
		}
	}
	delete_post_meta( $id, MCUPR_IMGB_KEPT );
	$after = mcupr_imgb_bytes( $new );
	update_post_meta( $id, '_mcupr_webp', array( 'from' => 'image/png' === $mime ? 'png' : 'jpg', 'before' => $bytes, 'after' => $after ) );
	clean_post_cache( $id );
	return array( 'st' => 'converted', 'before' => $bytes, 'after' => $after );
}

/** A batch: up to $limit pictures with an ID above $after. */
function mcupr_imgb_batch( $after, $limit = 4, $again = false ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE " . mcupr_imgb_where( $again ) . ' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d', (int) $after, (int) $limit ) ); // phpcs:ignore WordPress.DB
	$out = array( 'items' => array(), 'last' => (int) $after, 'end' => count( $ids ) < $limit );
	foreach ( $ids as $id ) {
		$r               = mcupr_imgb_one( (int) $id );
		$r['id']         = (int) $id;
		$out['items'][]  = $r;
		$out['last']     = (int) $id;
	}
	return $out;
}

add_action(
	'wp_ajax_mcupr_imgb',
	static function () {
		if ( ! check_ajax_referer( 'mcupr_imgb', 'nonce', false ) || ! current_user_can( 'mcupr_manage_theme' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mcupr' ) ), 403 );
		}
		if ( empty( $_POST['confirm'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Tick the box to confirm first.', 'mcupr' ) ), 400 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$after = isset( $_POST['after'] ) ? absint( $_POST['after'] ) : 0;
		$again = ! empty( $_POST['again'] );
		$b = mcupr_imgb_batch( $after, 4, $again );
		$b['left'] = mcupr_imgb_count( $again );
		wp_send_json_success( $b );
	}
);

/** The card on the Pictures page. */
function mcupr_imgb_card() {
	$left = mcupr_imgb_count();
	$all  = mcupr_imgb_count( true );
	echo '<div class="card" id="mcupr-imgb" style="max-width:60rem"><h2>' . esc_html__( 'Convert the pictures already in the library', 'mcupr' ) . '</h2>';
	echo '<p>' . esc_html__( 'Turns every JPEG and PNG in the Media Library into WebP at the size set below, and deletes the original files and old thumbnails from the website. Each picture keeps its entry in the library (title, description, alt text, folder) and every place it is used – pages, widgets, settings – is changed to the new file.', 'mcupr' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'The originals cannot be brought back. Make a backup of the files and the database first.', 'mcupr' ) . '</strong></p>';
	echo '<p>' . esc_html( sprintf( /* translators: 1: pictures still to check, 2: all JPEG and PNG */ __( 'To check: %1$d pictures (JPEG and PNG in the library: %2$d).', 'mcupr' ), $left, $all ) ) . '</p>';
	if ( ! mcupr_img_supported() ) {
		echo '<p><strong style="color:#b32d2e">' . esc_html__( 'This server cannot write WebP, so nothing can be converted.', 'mcupr' ) . '</strong></p></div>';
		return;
	}
	echo '<p><label><input type="checkbox" id="mcupr-imgb-ok"> ' . esc_html__( 'I have a backup, and I agree that the original files are deleted for good', 'mcupr' ) . '</label></p>';
	echo '<p><label><input type="checkbox" id="mcupr-imgb-again"> ' . esc_html__( 'Check again the pictures that were kept earlier (small, or not smaller as WebP)', 'mcupr' ) . '</label></p>';
	echo '<p><button type="button" class="button button-primary" id="mcupr-imgb-go" disabled>' . esc_html__( 'Convert all pictures', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-imgb-stop" hidden>' . esc_html__( 'Stop', 'mcupr' ) . '</button></p>';
	echo '<progress id="mcupr-imgb-bar" value="0" max="1" hidden style="width:100%;max-width:30rem"></progress>';
	echo '<p id="mcupr-imgb-msg" role="status" aria-live="polite"></p></div>';
}

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false === strpos( (string) $hook, 'mcupr-images' ) ) {
			return;
		}
		wp_enqueue_script( 'mcupr-images-bulk', get_theme_file_uri( 'assets/js/images-bulk.js' ), array(), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-images-bulk',
			'mcuprImgb',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mcupr_imgb' ),
				't'     => array(
					'run'     => __( 'Converting… %1$d of %2$d done. Keep this page open.', 'mcupr' ),
					'done'    => __( 'Finished: %1$d converted, %2$d kept as they were, %3$d could not be read or converted. Saved %4$s.', 'mcupr' ),
					'stopped' => __( 'Stopped: %1$d converted so far, %4$s saved. You can start again; it carries on with what is left.', 'mcupr' ),
					'fail'    => __( 'The server did not answer. Start again; it carries on with what is left.', 'mcupr' ),
					'none'    => __( 'Nothing to convert.', 'mcupr' ),
				),
			)
		);
	}
);
