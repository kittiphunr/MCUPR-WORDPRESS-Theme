<?php
/**
 * Photo albums (อัลบั้มภาพ): post type "mcupr_album" – a title, a text, a date and a set of pictures chosen from the
 * media library (drag to order, captions are the pictures' own captions). Shown at /albums/, on a page of its own with
 * an accessible lightbox (arrow keys, Esc, captions, focus kept inside), and anywhere with [mcupr_albums count="6"].
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_albums_register() {
	register_post_type(
		'mcupr_album',
		array(
			'labels'        => array(
				'name'          => __( 'Photo albums', 'mcupr' ),
				'singular_name' => __( 'Album', 'mcupr' ),
				'menu_name'     => __( 'Photo albums', 'mcupr' ),
				'all_items'     => __( 'All albums', 'mcupr' ),
				'add_new_item'  => __( 'Add an album', 'mcupr' ),
				'edit_item'     => __( 'Edit album', 'mcupr' ),
				'not_found'     => __( 'No albums yet', 'mcupr' ),
			),
			'public'        => true,
			'has_archive'   => 'albums',
			'rewrite'       => array( 'slug' => 'albums', 'with_front' => false ),
			'menu_icon'     => 'dashicons-format-gallery',
			'menu_position' => 7,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);
	mcupr_rewrite_wanted( 'mcupr_rewrite_albums', MCUPR_VERSION ); // one flush for all modules, at the end of init (inc/compat.php)
}
add_action( 'init', 'mcupr_albums_register' );

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_album';
		return $types;
	}
);

/** The picture ids of an album, in order (only ones that still exist as images). */
function mcupr_album_images( $id ) {
	$ids = get_post_meta( $id, '_mcupr_album_images', true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	if ( count( $ids ) > 1 ) {
		_prime_post_caches( $ids, false, true ); // the pictures and their data in two queries, not two each
	}
	return array_values( array_filter( $ids, static function ( $a ) { return $a && wp_attachment_is_image( $a ); } ) );
}

/** The cover: the featured image, else the first picture. */
function mcupr_album_cover_id( $id ) {
	$t = get_post_thumbnail_id( $id );
	if ( $t ) {
		return (int) $t;
	}
	$i = mcupr_album_images( $id );
	return $i ? (int) $i[0] : 0;
}

/* ------------------------------------------------------------------ admin */

add_action(
	'add_meta_boxes_mcupr_album',
	static function () {
		add_meta_box( 'mcupr_album_images', __( 'Pictures of the album', 'mcupr' ), 'mcupr_album_box', 'mcupr_album', 'normal', 'high' );
	}
);

function mcupr_album_box( $post ) {
	wp_nonce_field( 'mcupr_album_save', 'mcupr_album_nonce' );
	echo '<p class="description">' . esc_html__( 'Choose the pictures (select many at once), drag them to put them in order, and write each picture\'s caption in the media library. The featured image on the right is the cover; without one the first picture is used.', 'mcupr' ) . '</p>';
	echo '<ul class="mcs-album" data-album-list>';
	foreach ( mcupr_album_images( $post->ID ) as $aid ) {
		echo '<li data-id="' . (int) $aid . '">' . wp_get_attachment_image( $aid, 'thumbnail', false, array( 'alt' => '' ) ) . '<input type="hidden" name="mcupr_album[]" value="' . (int) $aid . '"><button type="button" class="button-link-delete" data-album-del aria-label="' . esc_attr__( 'Remove this picture', 'mcupr' ) . '">&times;</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul><p><button type="button" class="button button-primary" data-album-add>' . esc_html__( 'Add pictures', 'mcupr' ) . '</button></p>';
}

add_action(
	'save_post_mcupr_album',
	static function ( $post_id ) {
		if ( ! mcupr_can_save_box( $post_id, 'mcupr_album_nonce', 'mcupr_album_save' ) ) {
			return;
		}
		$ids = isset( $_POST['mcupr_album'] ) && is_array( $_POST['mcupr_album'] ) ? array_map( 'absint', wp_unslash( $_POST['mcupr_album'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ids = array_values( array_unique( array_filter( $ids, static function ( $a ) { return $a && wp_attachment_is_image( $a ); } ) ) );
		update_post_meta( $post_id, '_mcupr_album_images', array_slice( $ids, 0, 500 ) );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		$s = get_current_screen();
		if ( ! $s || 'mcupr_album' !== $s->post_type || 'post' !== $s->base ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'mcupr-album-admin', get_theme_file_uri( 'assets/js/album-admin.js' ), array( 'jquery', 'jquery-ui-sortable' ), MCUPR_VERSION, true );
		wp_add_inline_style( 'wp-admin', '.mcs-album{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0;padding:0;list-style:none}.mcs-album li{position:relative;width:96px;height:96px;cursor:move;border:1px solid #c3c4c7;border-radius:6px;overflow:hidden;background:#f6f7f7}.mcs-album img{width:100%;height:100%;object-fit:cover;display:block}.mcs-album button{position:absolute;top:2px;right:2px;width:22px;height:22px;border:0;border-radius:50%;background:#fff;font-size:16px;line-height:1;cursor:pointer}' );
	}
);

add_filter(
	'manage_mcupr_album_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_count'] = __( 'Pictures', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_album_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_count' === $col ) {
			echo (int) count( mcupr_album_images( $id ) );
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ front end */

/** A card of the album list: cover, title, number of pictures. */
function mcupr_album_card( $p, $h = 'h3' ) {
	$h = 'h2' === $h ? 'h2' : 'h3';
	$cover = mcupr_album_cover_id( $p->ID );
	$n     = count( mcupr_album_images( $p->ID ) );
	$o     = '<article class="album-card"><a class="album-cover" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( $cover ? wp_get_attachment_image( $cover, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '<span class="album-none">' . mcupr_icon( 'image', 40 ) . '</span>' ) . '<span class="album-n">' . mcupr_icon( 'camera', 14 ) . (int) $n . '</span></a>';
	$o    .= '<' . $h . ' class="album-t"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></' . $h . '><p class="album-d">' . esc_html( get_the_date( 'j M Y', $p ) ) . '</p></article>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return $o;
}

/** [mcupr_albums count="6" columns="3"] */
add_shortcode(
	'mcupr_albums',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'count' => 6 ), $atts, 'mcupr_albums' );
		$q = get_posts( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 48, (int) $a['count'] ) ), 'no_found_rows' => true ) );
		if ( ! $q ) {
			return mcupr_empty_note( mcupr_t( 'album_none' ), 'image' );
		}
		$o = '<div class="album-grid">';
		foreach ( $q as $p ) {
			$o .= mcupr_album_card( $p );
		}
		return $o . '</div>';
	}
);

/** The pictures of an album as a grid of lightbox links. */
function mcupr_album_gallery( $id ) {
	$ids = mcupr_album_images( $id );
	if ( ! $ids ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'album_empty' ) ) . '</p>';
	}
	return mcupr_gallery_list( $ids );
}

/** A grid of lightbox links for these pictures (an album, and the activity photos of a post). */
function mcupr_gallery_list( $ids ) {
	$o = '<ul class="album-pics" data-lightbox>';
	foreach ( $ids as $i => $aid ) {
		$full = wp_get_attachment_image_src( $aid, 'full' );
		$cap  = wp_get_attachment_caption( $aid );
		$alt  = (string) get_post_meta( $aid, '_wp_attachment_image_alt', true );
		$o   .= '<li><a href="' . esc_url( $full[0] ) . '" data-lb data-cap="' . esc_attr( $cap ) . '" aria-label="' . esc_attr( ( $cap ? $cap : $alt ) . ( $cap || $alt ? ' – ' : '' ) . ( $i + 1 ) . '/' . count( $ids ) ) . '">' . wp_get_attachment_image( $aid, 'medium', false, array( 'alt' => $alt, 'loading' => $i < 6 ? 'eager' : 'lazy', 'decoding' => 'async' ) ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	return $o . '</ul>';
}