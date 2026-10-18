<?php
/**
 * Videos (วิดีโอ): post type "mcupr_video" for videos kept on other sites (YouTube, Vimeo) or a direct file – paste the
 * address, the theme does the rest. The player is a "click to play" cover: nothing is loaded from the video site, and no
 * cookie is set, until the visitor presses play (PDPA friendly); YouTube plays from youtube-nocookie.com. The cover is the
 * featured image, else (for YouTube) the picture YouTube provides, fetched once by the server when the video is saved.
 * Also: a "Video" box on news posts, a [mcupr_video url=""] / [mcupr_videos] shortcode, and addresses pasted alone on a line
 * in any editor get the same player.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_videos_register() {
	register_post_type(
		'mcupr_video',
		array(
			'labels'        => array(
				'name'          => __( 'Videos', 'mcupr' ),
				'singular_name' => __( 'Video', 'mcupr' ),
				'menu_name'     => __( 'Videos', 'mcupr' ),
				'all_items'     => __( 'All videos', 'mcupr' ),
				'add_new_item'  => __( 'Add a video', 'mcupr' ),
				'edit_item'     => __( 'Edit video', 'mcupr' ),
				'not_found'     => __( 'No videos yet', 'mcupr' ),
			),
			'public'        => true,
			'has_archive'   => 'videos',
			'rewrite'       => array( 'slug' => 'videos', 'with_front' => false ),
			'menu_icon'     => 'dashicons-video-alt3',
			'menu_position' => 8,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);
	register_taxonomy(
		'mcupr_video_cat',
		'mcupr_video',
		array(
			'labels'            => array( 'name' => __( 'Video categories', 'mcupr' ), 'singular_name' => __( 'Video category', 'mcupr' ), 'add_new_item' => __( 'Add a category', 'mcupr' ) ),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'video-category', 'with_front' => false ),
		)
	);
	if ( get_option( 'mcupr_rewrite_videos' ) !== MCUPR_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'mcupr_rewrite_videos', MCUPR_VERSION );
	}
}
add_action( 'init', 'mcupr_videos_register' );

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_video';
		return $types;
	}
);

/**
 * Understand a video address. Returns false, or array( 'provider' => youtube|vimeo|file, 'id' => …, 'embed' => url,
 * 'watch' => url ). Only these providers are accepted – anything else is not turned into an iframe.
 */
function mcupr_video_parse( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return false;
	}
	$p = wp_parse_url( $url );
	if ( empty( $p['host'] ) ) {
		return false;
	}
	$host = strtolower( preg_replace( '/^(www|m|music)\./i', '', $p['host'] ) );
	parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );
	$path  = isset( $p['path'] ) ? $p['path'] : '';
	$start = 0;
	$t     = isset( $q['t'] ) ? $q['t'] : ( isset( $q['start'] ) ? $q['start'] : '' );
	if ( preg_match( '/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', (string) $t, $m ) && '' !== (string) $t ) {
		$start = (int) ( isset( $m[1] ) ? $m[1] : 0 ) * 3600 + (int) ( isset( $m[2] ) ? $m[2] : 0 ) * 60 + (int) ( isset( $m[3] ) ? $m[3] : 0 );
	}
	$id = '';
	if ( 'youtu.be' === $host ) {
		$id = trim( $path, '/' );
	} elseif ( in_array( $host, array( 'youtube.com', 'youtube-nocookie.com' ), true ) ) {
		if ( '/watch' === $path && ! empty( $q['v'] ) ) {
			$id = (string) $q['v'];
		} elseif ( preg_match( '#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m ) ) {
			$id = $m[1];
		}
	}
	if ( $id ) {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{6,15}$/', $id ) ) {
			return false;
		}
		$embed = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1&autoplay=1' . ( $start ? '&start=' . $start : '' );
		return array( 'provider' => 'youtube', 'id' => $id, 'embed' => $embed, 'watch' => 'https://www.youtube.com/watch?v=' . $id . ( $start ? '&t=' . $start . 's' : '' ) );
	}
	if ( 'vimeo.com' === $host && preg_match( '#^/(?:video/)?(\d{5,12})#', $path, $m ) ) {
		return array( 'provider' => 'vimeo', 'id' => $m[1], 'embed' => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1&autoplay=1', 'watch' => 'https://vimeo.com/' . $m[1] );
	}
	if ( preg_match( '/\.(mp4|webm|ogv)$/i', $path ) ) {
		return array( 'provider' => 'file', 'id' => '', 'embed' => esc_url_raw( $url ), 'watch' => esc_url_raw( $url ) );
	}
	return false;
}

/** The player: a cover with a play button; the real player is built by main.js when it is pressed. */
function mcupr_video_html( $url, $title = '', $poster_id = 0 ) {
	$v = mcupr_video_parse( $url );
	if ( ! $v ) {
		return '';
	}
	$label  = $title ? $title : mcupr_t( 'vd_video' );
	$poster = $poster_id ? wp_get_attachment_image( $poster_id, 'large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '';
	if ( 'file' === $v['provider'] ) {
		$img = $poster_id ? wp_get_attachment_image_url( $poster_id, 'large' ) : '';
		return '<div class="vid is-file"><video controls preload="none" playsinline' . ( $img ? ' poster="' . esc_url( $img ) . '"' : '' ) . ' aria-label="' . esc_attr( $label ) . '"><source src="' . esc_url( $v['embed'] ) . '"></video></div>';
	}
	$o  = '<div class="vid" data-vid="' . esc_attr( $v['provider'] ) . '" data-embed="' . esc_url( $v['embed'] ) . '" data-title="' . esc_attr( $label ) . '">';
	$o .= '<a class="vid-play" href="' . esc_url( $v['watch'] ) . '" rel="noopener" aria-label="' . esc_attr( sprintf( mcupr_t( 'vd_play' ), $label ) ) . '">' . $poster . '<span class="vid-btn" aria-hidden="true">' . mcupr_icon( 'play', 30 ) . '</span></a>';
	$o .= '<p class="vid-note">' . esc_html( mcupr_t( 'vd_note' ) ) . ' ' . esc_html( 'youtube' === $v['provider'] ? 'YouTube' : 'Vimeo' ) . '</p></div>';
	return $o;
}

/** The video address of a post (a video, or a news post with the "Video" box filled in). */
function mcupr_video_url( $id = 0 ) {
	return (string) get_post_meta( $id ? $id : get_the_ID(), '_mcupr_video_url', true );
}

/* ------------------------------------------------------------------ admin */

add_action(
	'add_meta_boxes',
	static function ( $type ) {
		if ( in_array( $type, array( 'mcupr_video', 'post' ), true ) ) {
			add_meta_box( 'mcupr_video_box', __( 'Video from another site', 'mcupr' ), 'mcupr_video_box', $type, 'normal', 'high' );
		}
	}
);

function mcupr_video_box( $post ) {
	wp_nonce_field( 'mcupr_video_save', 'mcupr_video_nonce' );
	$url = mcupr_video_url( $post->ID );
	echo '<p class="description">' . esc_html__( 'Paste the address of a YouTube or Vimeo video (or a link to an .mp4 file). It is shown with a click-to-play cover; nothing is loaded from the video site until a visitor presses play.', 'mcupr' ) . '</p>';
	echo '<p><label><strong>' . esc_html__( 'Video address', 'mcupr' ) . '</strong><br><input type="url" class="widefat" name="mcupr_video_url" value="' . esc_attr( $url ) . '" placeholder="https://www.youtube.com/watch?v=…"></label></p>';
	if ( $url ) {
		$v = mcupr_video_parse( $url );
		echo '<p>' . ( $v ? '<span style="color:#15803d">' . mcupr_feather_i( 'check', 14 ) . ' ' . esc_html( sprintf( /* translators: %s: provider */ __( 'Recognised: %s', 'mcupr' ), 'file' === $v['provider'] ? 'MP4' : ucfirst( $v['provider'] ) ) ) . '</span>' : '<span style="color:#b91c1c">' . mcupr_feather_i( 'x', 14 ) . ' ' . esc_html__( 'This address is not recognised (use YouTube, Vimeo or a link to an .mp4 file).', 'mcupr' ) . '</span>' ) . '</p>';
	}
	if ( 'mcupr_video' === $post->post_type ) {
		echo '<p class="description">' . esc_html__( 'The cover is the featured image. Without one, the picture YouTube provides is used (fetched once, when you save).', 'mcupr' ) . '</p>';
	}
}

/** For a YouTube video without a cover: fetch YouTube's picture once (server side) and keep it in the media library. */
function mcupr_video_fetch_cover( $post_id, $v ) {
	if ( 'youtube' !== $v['provider'] || has_post_thumbnail( $post_id ) || ( defined( 'MCUPR_NO_REMOTE' ) && MCUPR_NO_REMOTE ) ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	foreach ( array( 'maxresdefault', 'hqdefault' ) as $name ) {
		$tmp = download_url( 'https://i.ytimg.com/vi/' . $v['id'] . '/' . $name . '.jpg', 15 );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}
		$size = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $size || $size[0] < 300 ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			continue;
		}
		$aid = media_handle_sideload( array( 'name' => 'youtube-' . $v['id'] . '.jpg', 'tmp_name' => $tmp ), $post_id );
		if ( ! is_wp_error( $aid ) ) {
			set_post_thumbnail( $post_id, $aid );
			return;
		}
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! in_array( $post->post_type, array( 'mcupr_video', 'post' ), true ) || ! mcupr_can_save_box( $post_id, 'mcupr_video_nonce', 'mcupr_video_save' ) ) {
			return;
		}
		$url = isset( $_POST['mcupr_video_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['mcupr_video_url'] ) ), array( 'http', 'https' ) ) : '';
		$v   = $url ? mcupr_video_parse( $url ) : false;
		if ( $v ) {
			update_post_meta( $post_id, '_mcupr_video_url', $url );
			if ( 'mcupr_video' === $post->post_type ) {
				mcupr_video_fetch_cover( $post_id, $v );
			}
		} else {
			delete_post_meta( $post_id, '_mcupr_video_url' ); // an address we do not understand is never kept (and never shown)
		}
	},
	10,
	2
);

add_filter(
	'manage_mcupr_video_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_vurl'] = __( 'Video address', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_video_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_vurl' === $col ) {
			$u = mcupr_video_url( $id );
			echo $u ? esc_html( $u ) : '—';
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ front end */

/** A card of the video list: cover with a play badge, title, date. */
function mcupr_video_card( $p, $h = 'h3' ) {
	$h = 'h2' === $h ? 'h2' : 'h3';
	$tid = get_post_thumbnail_id( $p );
	$o   = '<article class="album-card video-card"><a class="album-cover" href="' . esc_url( get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . ( $tid ? wp_get_attachment_image( $tid, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) : '<span class="album-none">' . mcupr_icon( 'play', 40 ) . '</span>' ) . '<span class="vid-badge">' . mcupr_icon( 'play', 22 ) . '</span></a>';
	$o  .= '<' . $h . ' class="album-t"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></' . $h . '><p class="album-d">' . esc_html( get_the_date( 'j M Y', $p ) ) . '</p></article>';
	return $o;
}

/** [mcupr_video url="https://youtu.be/…" title=""]  and  [mcupr_videos count="6" category="slug"] */
add_shortcode(
	'mcupr_video',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'url' => '', 'title' => '' ), $atts, 'mcupr_video' );
		$h = mcupr_video_html( $a['url'], $a['title'] );
		return $h ? '<div class="vid-wrap">' . $h . '</div>' : '';
	}
);
add_shortcode(
	'mcupr_videos',
	static function ( $atts ) {
		$a    = shortcode_atts( array( 'count' => 6, 'category' => '' ), $atts, 'mcupr_videos' );
		$args = array( 'post_type' => 'mcupr_video', 'post_status' => 'publish', 'posts_per_page' => max( 1, min( 48, (int) $a['count'] ) ), 'no_found_rows' => true );
		if ( $a['category'] ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_video_cat', 'field' => 'slug', 'terms' => array_map( 'sanitize_title', explode( ',', $a['category'] ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$q = get_posts( $args );
		if ( ! $q ) {
			return '<p class="text-muted">' . esc_html( mcupr_t( 'vd_none' ) ) . '</p>';
		}
		$o = '<div class="album-grid">';
		foreach ( $q as $p ) {
			$o .= mcupr_video_card( $p );
		}
		return $o . '</div>';
	}
);

/** An address pasted alone on a line in any editor: the same player (YouTube, Vimeo), instead of WordPress' own iframe. */
add_filter(
	'embed_oembed_html',
	static function ( $html, $url ) {
		$v = mcupr_video_parse( $url );
		if ( $v && 'file' !== $v['provider'] ) {
			return '<div class="vid-wrap">' . mcupr_video_html( $url ) . '</div>';
		}
		return $html;
	},
	10,
	2
);

/** News posts with a video address: the player comes first in the article, whatever layout is used. */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_singular( 'post' ) && in_the_loop() && is_main_query() && mcupr_video_url() ) {
			$h = mcupr_video_html( mcupr_video_url(), get_the_title() );
			if ( $h ) {
				return '<div class="vid-wrap">' . $h . '</div>' . $content;
			}
		}
		return $content;
	},
	5
);
