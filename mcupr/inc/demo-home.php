<?php
/**
 * Home page demo (MCUPR → Import / export settings → “Home page demo”).
 *
 * One click fills every part of the front page with working sample content, to be edited afterwards:
 *   news (with pictures: the slider, the latest news and the hero photo), news by category, upcoming events, a video,
 *   photo albums, the quick-link tiles (a menu), and every home setting (hero texts and buttons, introduction, figures,
 *   audience cards, service cards, links, about block, weather, visit-us details, banner, show/hide switches).
 * Nothing is ever overwritten: every item made here carries a key (_mcupr_demo_key) and is made only if it does not exist;
 * settings are filled only where nothing is typed; the quick-links menu is attached only if that place is still free.
 * “Remove the demo items” deletes what this made (also what was edited) – the settings stay.
 * The data is in starter/mcupr-demo-home.json (the same file can be uploaded on another site).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** A date in the site's time zone, always in the Christian era (wp_date() is turned into Buddhist years by the theme, which breaks what is saved). */
function mcupr_demo_date( $format, $ts ) {
	return ( new DateTimeImmutable( '@' . (int) $ts ) )->setTimezone( wp_timezone() )->format( $format );
}

function mcupr_demo_data() {
	$f = get_theme_file_path( 'starter/mcupr-demo-home.json' );
	$d = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $d ) && ! empty( $d['mcupr_demo'] ) ? $d : null;
}

/** Every post type ("any" leaves out the ones hidden from search, like staff). */
function mcupr_demo_types() {
	return array_values( get_post_types() );
}

/** The post made for a demo key (any status, any type), or 0. */
function mcupr_demo_find( $key, $meta = '_mcupr_demo_key' ) {
	$p = get_posts( array( 'post_type' => mcupr_demo_types(), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'inherit' ), 'meta_key' => $meta, 'meta_value' => $key, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'mcupr_office_all' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $p ? (int) $p[0] : 0;
}

/** A soft gradient picture (no text, no photo): attachment id or 0. $seed picks the colours. */
function mcupr_demo_image( $key, $seed, $w = 1600, $h = 900, $meta = '_mcupr_demo_key' ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}
	$have = mcupr_demo_find( 'img-' . $key, $meta );
	if ( $have ) {
		return $have;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$pal = array(
		array( array( 194, 24, 91 ), array( 255, 183, 77 ) ), array( array( 21, 101, 192 ), array( 128, 222, 234 ) ), array( array( 46, 125, 50 ), array( 200, 230, 100 ) ),
		array( array( 106, 27, 154 ), array( 244, 143, 177 ) ), array( array( 0, 105, 92 ), array( 129, 212, 250 ) ), array( array( 191, 54, 12 ), array( 255, 213, 79 ) ),
		array( array( 40, 53, 147 ), array( 179, 157, 219 ) ), array( array( 69, 90, 100 ), array( 255, 204, 128 ) ),
	);
	list( $c1, $c2 ) = $pal[ $seed % count( $pal ) ];
	$im = imagecreatetruecolor( $w, $h );
	for ( $y = 0; $y < $h; $y++ ) { // diagonal-ish gradient, drawn row by row
		$t = $y / $h;
		imageline( $im, 0, $y, $w, $y, imagecolorallocate( $im, (int) ( $c1[0] + ( $c2[0] - $c1[0] ) * $t ), (int) ( $c1[1] + ( $c2[1] - $c1[1] ) * $t ), (int) ( $c1[2] + ( $c2[2] - $c1[2] ) * $t ) ) );
	}
	imagealphablending( $im, true );
	for ( $i = 0; $i < 5; $i++ ) { // a few translucent circles so it is not flat
		$r = (int) ( $h * ( 0.25 + 0.12 * ( ( $seed + $i ) % 4 ) ) );
		imagefilledellipse( $im, (int) ( $w * ( 0.12 + 0.2 * $i ) ), (int) ( $h * ( 0.2 + 0.15 * ( ( $seed * 3 + $i * 2 ) % 5 ) ) ), $r, $r, imagecolorallocatealpha( $im, 255, 255, 255, 112 ) );
	}
	$up   = wp_upload_dir();
	$webp = function_exists( 'imagewebp' );
	$name = wp_unique_filename( $up['path'], 'demo-' . sanitize_file_name( $key ) . ( $webp ? '.webp' : '.jpg' ) );
	$file = trailingslashit( $up['path'] ) . $name;
	wp_mkdir_p( $up['path'] );
	$webp ? imagewebp( $im, $file, 80 ) : imagejpeg( $im, $file, 82 );
	imagedestroy( $im );
	if ( ! is_readable( $file ) ) {
		return 0;
	}
	$id = wp_insert_attachment( array( 'post_mime_type' => $webp ? 'image/webp' : 'image/jpeg', 'post_title' => 'ภาพตัวอย่าง ' . $key, 'post_status' => 'inherit' ), $file );
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	update_post_meta( $id, '_wp_attachment_image_alt', 'ภาพตัวอย่าง' );
	update_post_meta( $id, $meta, 'img-' . $key );
	return (int) $id;
}

/** Make the demo. Returns array of counts: posts, events, video, albums, images, menu (0/1), settings (groups added), skipped (already there). */
function mcupr_demo_apply( $d ) {
	$r = array( 'posts' => 0, 'events' => 0, 'video' => 0, 'albums' => 0, 'images' => 0, 'menu' => 0, 'settings' => array(), 'skipped' => 0 );
	if ( ! is_array( $d ) || empty( $d['mcupr_demo'] ) ) {
		return $r;
	}
	$imgs = static function () use ( &$r ) { $r['images']++; };
	// ---- categories
	$cat_ids = array();
	foreach ( isset( $d['categories'] ) && is_array( $d['categories'] ) ? $d['categories'] : array() as $slug => $name ) {
		$t = get_term_by( 'slug', sanitize_title( $slug ), 'category' );
		if ( ! $t ) {
			$n = wp_insert_term( sanitize_text_field( $name ), 'category', array( 'slug' => sanitize_title( $slug ) ) );
			$t = is_wp_error( $n ) ? null : get_term( $n['term_id'], 'category' );
		}
		if ( $t ) {
			$cat_ids[ sanitize_title( $slug ) ] = (int) $t->term_id;
		}
	}
	// ---- news
	$i = 0;
	foreach ( isset( $d['posts'] ) && is_array( $d['posts'] ) ? array_slice( $d['posts'], 0, 20 ) : array() as $p ) {
		$i++;
		$key = 'post-' . sanitize_key( $p['key'] );
		if ( mcupr_demo_find( $key ) ) {
			$r['skipped']++;
			continue;
		}
		$when = time() - max( 0, (int) $p['days_ago'] ) * DAY_IN_SECONDS;
		$id   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $p['title'] ), 'post_excerpt' => sanitize_text_field( $p['excerpt'] ), 'post_content' => wp_kses_post( wpautop( $p['content'] ) ), 'post_date' => mcupr_demo_date( 'Y-m-d H:i:s', $when ), 'post_category' => isset( $cat_ids[ $p['cat'] ] ) ? array( $cat_ids[ $p['cat'] ] ) : array(), 'tags_input' => array_map( 'sanitize_text_field', (array) $p['tags'] ) ) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_mcupr_demo_key', $key );
		$img = mcupr_demo_image( $key, $i - 1 );
		if ( $img ) {
			set_post_thumbnail( $id, $img );
			$imgs();
		}
		$r['posts']++;
	}
	// ---- events
	foreach ( isset( $d['events'] ) && is_array( $d['events'] ) ? array_slice( $d['events'], 0, 10 ) : array() as $e ) {
		$key = 'event-' . sanitize_key( $e['key'] );
		if ( ! post_type_exists( 'mcupr_event' ) || mcupr_demo_find( $key ) ) {
			$r['skipped']++;
			continue;
		}
		$day = mcupr_demo_date( 'Y-m-d', time() + max( 0, (int) $e['in_days'] ) * DAY_IN_SECONDS );
		$id  = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $e['title'] ), 'post_content' => wp_kses_post( wpautop( $e['content'] ) ) ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_mcupr_demo_key', $key );
			update_post_meta( $id, '_mcupr_ev_allday', ! empty( $e['allday'] ) ? 1 : 0 );
			update_post_meta( $id, '_mcupr_ev_start', $day . ' ' . ( ! empty( $e['allday'] ) ? '00:00:00' : sanitize_text_field( $e['from'] ) . ':00' ) );
			update_post_meta( $id, '_mcupr_ev_end', $day . ' ' . ( ! empty( $e['allday'] ) ? '23:59:00' : sanitize_text_field( $e['to'] ) . ':00' ) );
			update_post_meta( $id, '_mcupr_ev_place', sanitize_text_field( $e['place'] ) );
			$r['events']++;
		}
	}
	// ---- video
	if ( ! empty( $d['video'] ) && post_type_exists( 'mcupr_video' ) ) {
		$key = 'video-' . sanitize_key( $d['video']['key'] );
		if ( mcupr_demo_find( $key ) ) {
			$r['skipped']++;
		} else {
			$id = wp_insert_post( array( 'post_type' => 'mcupr_video', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $d['video']['title'] ), 'post_content' => wp_kses_post( wpautop( $d['video']['content'] ) ) ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_mcupr_demo_key', $key );
				update_post_meta( $id, '_mcupr_video_url', esc_url_raw( $d['video']['url'], array( 'https' ) ) );
				$img = mcupr_demo_image( $key, 3 );
				if ( $img ) {
					set_post_thumbnail( $id, $img );
					$imgs();
				}
				$r['video']++;
			}
		}
	}
	// ---- albums
	$a = 0;
	foreach ( isset( $d['albums'] ) && is_array( $d['albums'] ) ? array_slice( $d['albums'], 0, 8 ) : array() as $al ) {
		$a++;
		$key = 'album-' . sanitize_key( $al['key'] );
		if ( ! post_type_exists( 'mcupr_album' ) || mcupr_demo_find( $key ) ) {
			$r['skipped']++;
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'post_title' => sanitize_text_field( $al['title'] ) ) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_mcupr_demo_key', $key );
			$ids = array();
			for ( $n = 1; $n <= max( 1, min( 8, (int) $al['count'] ) ); $n++ ) {
				$img = mcupr_demo_image( $key . '-' . $n, $a * 2 + $n, 1200, 800 );
				if ( $img ) {
					$ids[] = $img;
					$imgs();
				}
			}
			update_post_meta( $id, '_mcupr_album_images', $ids );
			$r['albums']++;
		}
	}
	// ---- the quick-link tiles: a menu, attached only to a free place
	if ( ! empty( $d['menu']['items'] ) && ! get_term_by( 'name', sanitize_text_field( $d['menu']['name'] ), 'nav_menu' ) ) {
		$mid = wp_create_nav_menu( sanitize_text_field( $d['menu']['name'] ) );
		if ( ! is_wp_error( $mid ) ) {
			$pos = 0;
			foreach ( $d['menu']['items'] as $it ) {
				$url = mcupr_blk_page_url( $it[0] );
				if ( '' === $url ) {
					continue;
				}
				$pos++;
				$iid = wp_update_nav_menu_item( $mid, 0, array( 'menu-item-title' => sanitize_text_field( $it[1] ), 'menu-item-url' => home_url( $url ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-position' => $pos ) );
				if ( $iid && ! is_wp_error( $iid ) ) {
					update_post_meta( $iid, '_mcupr_menu_icon', sanitize_key( $it[2] ) );
				}
			}
			$loc = get_nav_menu_locations();
			if ( $pos && empty( $loc['quick'] ) ) {
				$loc['quick'] = $mid;
				set_theme_mod( 'nav_menu_locations', $loc );
			}
			$r['menu'] = $pos ? 1 : 0;
		}
	}
	// ---- the settings (fill only)
	$rep = mcupr_ss_apply( array( 'theme_mods' => isset( $d['theme_mods'] ) ? $d['theme_mods'] : array() ) );
	$r['settings'] = $rep['added'];
	if ( ! empty( $d['home_cats'] ) && is_array( $d['home_cats'] ) ) {
		foreach ( array_slice( $d['home_cats'], 0, 3 ) as $x => $slug ) {
			$k = 'mcupr_home_cat_' . ( $x + 1 );
			if ( empty( get_theme_mod( $k, 0 ) ) && isset( $cat_ids[ sanitize_title( $slug ) ] ) ) {
				set_theme_mod( $k, $cat_ids[ sanitize_title( $slug ) ] );
			}
		}
	}
	if ( ! get_theme_mod( 'mcupr_hero_image', 0 ) ) {
		$hero = mcupr_demo_image( 'hero', 0, 1920, 900 );
		if ( $hero ) {
			set_theme_mod( 'mcupr_hero_image', $hero );
			$imgs();
		}
	}
	return $r;
}

/** Delete what the demo made (also what was edited). Returns the number of items deleted. */
function mcupr_demo_remove() {
	$n   = 0;
	$ids = get_posts( array( 'post_type' => mcupr_demo_types(), 'post_status' => 'any', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$att = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_mcupr_demo_key', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( array_unique( array_merge( $ids, $att ) ) as $id ) {
		if ( 'attachment' === get_post_type( $id ) && (int) get_theme_mod( 'mcupr_hero_image', 0 ) === (int) $id ) {
			remove_theme_mod( 'mcupr_hero_image' );
		}
		if ( wp_delete_post( $id, true ) ) {
			$n++;
		}
	}
	$d = mcupr_demo_data();
	if ( $d && ! empty( $d['menu']['name'] ) ) {
		$m = wp_get_nav_menu_object( sanitize_text_field( $d['menu']['name'] ) );
		if ( $m ) {
			wp_delete_nav_menu( $m->term_id );
		}
	}
	return $n;
}

function mcupr_demo_message( $r ) {
	$parts = array();
	foreach ( array( 'posts' => __( 'news', 'mcupr' ), 'events' => __( 'events', 'mcupr' ), 'video' => __( 'video', 'mcupr' ), 'albums' => __( 'albums', 'mcupr' ), 'images' => __( 'sample pictures', 'mcupr' ) ) as $k => $label ) {
		if ( $r[ $k ] ) {
			$parts[] = $r[ $k ] . ' ' . $label;
		}
	}
	if ( $r['menu'] ) {
		$parts[] = __( 'quick-link menu', 'mcupr' );
	}
	$out = $parts ? sprintf( /* translators: %s: list */ __( 'Demo made: %s.', 'mcupr' ), implode( ', ', $parts ) ) : __( 'Nothing new was made (the demo items already exist).', 'mcupr' );
	if ( $r['settings'] ) {
		$out .= ' ' . __( 'Home settings were filled in where they were empty.', 'mcupr' );
	}
	return $out;
}
