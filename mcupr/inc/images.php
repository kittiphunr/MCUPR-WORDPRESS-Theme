<?php
/**
 * Pictures: JPEG and PNG uploads are saved as WebP (and cut to a sensible size) the moment they are uploaded, so the
 * Media Library never fills up with 5 MB photos and pages load faster – the single biggest thing for a PageSpeed score.
 * Settings and the total saved: MCUPR → Pictures.
 *
 * Rules (so nothing is spoiled):
 *   - only JPEG and PNG; GIF (may be animated), SVG, WebP and everything else are left alone;
 *   - a picture under the minimum size (default 30 KB) is left alone – nothing to gain;
 *   - if the WebP would not be at least 10% smaller than the original, the original is kept;
 *   - a camera photo is turned the right way up first (WebP does not keep the rotation note);
 *   - pictures longer than the limit (default 2000 px on the long side) are scaled down;
 *   - if the server cannot write WebP (no GD/Imagick WebP support) nothing happens and the page says so.
 * Pictures that are already in the Media Library are not touched (their addresses are used inside pages).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_img_opts() {
	$o = get_option( 'mcupr_img', array() );
	$o = array_merge( array( 'on' => 1, 'quality' => 82, 'max' => 2000, 'min_kb' => 30 ), is_array( $o ) ? $o : array() );
	return array(
		'on'     => ! empty( $o['on'] ) ? 1 : 0,
		'quality' => max( 40, min( 95, (int) $o['quality'] ) ),
		'max'    => max( 800, min( 4000, (int) $o['max'] ) ),
		'min_kb' => max( 0, min( 2000, (int) $o['min_kb'] ) ),
	);
}

/** Can this server write WebP? */
function mcupr_img_supported() {
	return (bool) apply_filters( 'mcupr_img_supported', wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) );
}

/** Pictures converted in this request, by file: array( file => array( from mime, bytes before, bytes after ) ). */
function mcupr_img_log( $file = null, $info = null ) {
	static $log = array();
	if ( null !== $file ) {
		$log[ $file ] = $info;
	}
	return $log;
}

/**
 * wp_handle_upload filter: turn the uploaded JPEG/PNG into a WebP file in the same folder.
 * $upload = array( file, url, type ). Returns the same array, pointing at the WebP when it was converted.
 */
function mcupr_img_convert( $upload ) {
	if ( ! is_array( $upload ) || isset( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['type'] ) ) {
		return $upload;
	}
	$o = mcupr_img_opts();
	if ( ! $o['on'] || ! in_array( $upload['type'], array( 'image/jpeg', 'image/png' ), true ) || ! mcupr_img_supported() ) {
		return $upload;
	}
	$file = $upload['file'];
	if ( ! is_readable( $file ) ) {
		return $upload;
	}
	$before = (int) filesize( $file );
	if ( $before < $o['min_kb'] * 1024 ) {
		return $upload;
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return $upload;
	}
	if ( method_exists( $editor, 'maybe_exif_rotate' ) && 'image/jpeg' === $upload['type'] ) {
		$editor->maybe_exif_rotate(); // WebP has no orientation note: turn the pixels now
	}
	$size = $editor->get_size();
	if ( ! empty( $size['width'] ) && ! empty( $size['height'] ) && max( $size['width'], $size['height'] ) > $o['max'] ) {
		$editor->resize( $o['max'], $o['max'], false );
	}
	$editor->set_quality( $o['quality'] );
	$info = pathinfo( $file );
	$dest = trailingslashit( $info['dirname'] ) . wp_unique_filename( $info['dirname'], $info['filename'] . '.webp' );
	$saved = $editor->save( $dest, 'image/webp' );
	if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_readable( $saved['path'] ) ) {
		return $upload;
	}
	$after = (int) filesize( $saved['path'] );
	if ( $after <= 0 || $after > $before * 0.9 ) { // not clearly smaller: keep the picture as it was
		wp_delete_file( $saved['path'] );
		return $upload;
	}
	wp_delete_file( $file );
	$upload['file'] = $saved['path'];
	$upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . wp_basename( $saved['path'] );
	$upload['type'] = 'image/webp';
	mcupr_img_log( $saved['path'], array( $info['extension'], $before, $after ) );
	return $upload;
}
add_filter(
	'wp_handle_upload',
	static function ( $upload, $context = 'upload' ) {
		return mcupr_img_convert( $upload );
	},
	20,
	2
);

/** Remember what was saved on the attachment (for the total on the settings page). */
add_action(
	'add_attachment',
	static function ( $id ) {
		$f   = get_attached_file( $id );
		$log = mcupr_img_log();
		if ( $f && isset( $log[ $f ] ) ) {
			update_post_meta( $id, '_mcupr_webp', array( 'from' => $log[ $f ][0], 'before' => $log[ $f ][1], 'after' => $log[ $f ][2] ) );
		}
	}
);

/** array( count, bytes saved ) over everything converted so far. */
function mcupr_img_totals() {
	global $wpdb;
	$rows  = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_mcupr_webp' LIMIT 20000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$saved = 0;
	foreach ( $rows as $r ) {
		$m = maybe_unserialize( $r );
		if ( is_array( $m ) && isset( $m['before'], $m['after'] ) ) {
			$saved += max( 0, (int) $m['before'] - (int) $m['after'] );
		}
	}
	return array( count( $rows ), $saved );
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Pictures (WebP)', 'mcupr' ), __( 'Pictures (WebP)', 'mcupr' ), 'mcupr_manage_theme', 'mcupr-images', 'mcupr_img_page' );
	}
);

function mcupr_img_page() {
	if ( ! current_user_can( 'mcupr_manage_theme' ) ) {
		return;
	}
	$o   = mcupr_img_opts();
	$sup = mcupr_img_supported();
	list( $n, $saved ) = mcupr_img_totals();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$done = isset( $_GET['saved'] );
	echo '<style>.mcupr-im .card{overflow-wrap:anywhere}.mcupr-im input[type=number]{width:6rem}</style><div class="wrap mcupr-im"><h1>' . esc_html__( 'Pictures (WebP)', 'mcupr' ) . '</h1>';
	if ( $done ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<p style="max-width:60rem">' . esc_html__( 'JPEG and PNG pictures are saved as WebP, and cut to a sensible size, the moment they are uploaded. WebP files are typically 25–35% smaller with no visible difference, which is the biggest single help for page speed. Pictures already in the Media Library are not changed (their addresses are used inside your pages).', 'mcupr' ) . '</p>';
	echo '<div class="card" style="max-width:60rem"><h2>' . esc_html__( 'Status', 'mcupr' ) . '</h2><p>';
	if ( $sup ) {
		echo '<strong style="color:#1a7f37">' . mcupr_feather_i( 'check', 15 ) . ' ' . esc_html__( 'This server can write WebP.', 'mcupr' ) . '</strong>';
	} else {
		echo '<strong style="color:#b32d2e">' . mcupr_feather_i( 'x', 15 ) . ' ' . esc_html__( 'This server cannot write WebP (no WebP support in GD or Imagick). Nothing will be converted: ask the host to enable it.', 'mcupr' ) . '</strong>';
	}
	echo '</p><p>' . esc_html( sprintf( /* translators: 1: pictures, 2: size */ __( 'Converted so far: %1$d pictures, %2$s saved.', 'mcupr' ), $n, size_format( $saved ) ) ) . '</p></div>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_img">';
	wp_nonce_field( 'mcupr_img', 'mcupr_img_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Convert uploads to WebP', 'mcupr' ) . '</th><td><label><input type="checkbox" name="on" value="1"' . checked( $o['on'], 1, false ) . '> ' . esc_html__( 'On', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-im-q">' . esc_html__( 'Quality (40–95)', 'mcupr' ) . '</label></th><td><input id="mcupr-im-q" type="number" name="quality" min="40" max="95" value="' . (int) $o['quality'] . '"> <span class="description">' . esc_html__( '82 is a good balance. Higher = bigger files.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-im-m">' . esc_html__( 'Longest side (pixels)', 'mcupr' ) . '</label></th><td><input id="mcupr-im-m" type="number" name="max" min="800" max="4000" step="100" value="' . (int) $o['max'] . '"> <span class="description">' . esc_html__( 'Larger pictures are scaled down to this. 2000 is plenty for a full-width cover picture.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-im-k">' . esc_html__( 'Leave pictures smaller than (KB)', 'mcupr' ) . '</label></th><td><input id="mcupr-im-k" type="number" name="min_kb" min="0" max="2000" value="' . (int) $o['min_kb'] . '"> <span class="description">' . esc_html__( 'Small pictures (icons, logos) have nothing to gain.', 'mcupr' ) . '</span></td></tr>';
	echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form>';
	echo '<p class="description" style="max-width:60rem">' . esc_html__( 'Kept as they are: GIF (may be animated), SVG, WebP, and any picture where WebP would not be at least 10% smaller. Camera photos are turned the right way up first.', 'mcupr' ) . '</p></div>';
}

add_action(
	'admin_post_mcupr_img',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_img_nonce', 'mcupr_img', 'mcupr_manage_theme' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		update_option(
			'mcupr_img',
			array(
				'on'      => ! empty( $_POST['on'] ) ? 1 : 0,
				'quality' => isset( $_POST['quality'] ) ? (int) $_POST['quality'] : 82,
				'max'     => isset( $_POST['max'] ) ? (int) $_POST['max'] : 2000,
				'min_kb'  => isset( $_POST['min_kb'] ) ? (int) $_POST['min_kb'] : 30,
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-images&saved=1' ) );
		exit;
	}
);
