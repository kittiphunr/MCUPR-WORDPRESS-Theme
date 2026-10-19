<?php
/**
 * Offices demo (MCUPR → Import / export settings → “Offices demo”).
 *
 * One click makes three offices as mini-sites with working sample content: the campus office, the academic office and the
 * Buddhist college – each with its colour, head, contact details, news and announcements (with pictures), events, people,
 * a photo album and pages of its own (which become tabs); the first two also get work groups (inc/groups.php) with
 * services, contact details and some of their people and news marked with the group (no documents: the demo has no files). Everything carries a key (_mcupr_demo_off), is made only if it
 * does not exist yet, and “Remove the offices demo” deletes it all (also what was edited). Nothing else is touched.
 * The data is in starter/mcupr-demo-offices.json (the same file can be uploaded on another site).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_od_data() {
	$f = get_theme_file_path( 'starter/mcupr-demo-offices.json' );
	$d = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $d ) && ! empty( $d['mcupr_demo_offices'] ) ? $d : null;
}

/** Make the demo. Returns counts: offices, posts, events, people, albums, pages, images, skipped. */
function mcupr_od_apply( $d ) {
	$r = array( 'offices' => 0, 'posts' => 0, 'events' => 0, 'people' => 0, 'albums' => 0, 'pages' => 0, 'groups' => 0, 'images' => 0, 'skipped' => 0 );
	if ( ! is_array( $d ) || empty( $d['mcupr_demo_offices'] ) || empty( $d['offices'] ) || ! is_array( $d['offices'] ) ) {
		return $r;
	}
	$M     = '_mcupr_demo_off';
	$img   = static function ( $post_id, $key, $seed, $w = 1600, $h = 900 ) use ( &$r, $M ) {
		$id = mcupr_demo_image( $key, $seed, $w, $h, $M );
		if ( $id ) {
			if ( $post_id ) {
				set_post_thumbnail( $post_id, $id );
			}
			$r['images']++;
		}
		return $id;
	};
	$txt   = static function ( $a, $k ) {
		return isset( $a[ $k ] ) && is_string( $a[ $k ] ) ? sanitize_text_field( $a[ $k ] ) : '';
	};
	$terms = static function ( $map, $tax ) {
		$out = array();
		foreach ( is_array( $map ) ? $map : array() as $slug => $name ) {
			$slug = sanitize_title( $slug );
			$t    = get_term_by( 'slug', $slug, $tax );
			if ( ! $t ) {
				$n = wp_insert_term( sanitize_text_field( $name ), $tax, array( 'slug' => $slug ) );
				$t = is_wp_error( $n ) ? null : get_term( $n['term_id'], $tax );
			}
			if ( $t ) {
				$out[ $slug ] = (int) $t->term_id;
			}
		}
		return $out;
	};
	$cats   = $terms( isset( $d['categories'] ) ? $d['categories'] : array(), 'category' );
	$groups = post_type_exists( 'mcupr_person' ) ? $terms( isset( $d['groups'] ) ? $d['groups'] : array(), 'mcupr_person_group' ) : array();
	$seed   = 0;
	foreach ( array_slice( $d['offices'], 0, 12 ) as $o ) {
		$seed++;
		$ok = 'office-' . sanitize_key( $o['key'] );
		$oid = mcupr_demo_find( $ok, $M );
		if ( $oid ) {
			$r['skipped']++;
		} else {
			$oid = wp_insert_post(
				array(
					'post_type'    => 'mcupr_office',
					'post_status'  => 'publish',
					'post_title'   => $txt( $o, 'title' ),
					'post_name'    => sanitize_title( $txt( $o, 'slug' ) ),
					'post_excerpt' => $txt( $o, 'excerpt' ),
					'post_content' => wp_kses_post( wpautop( isset( $o['content'] ) ? $o['content'] : '' ) ),
					'menu_order'   => (int) ( isset( $o['order'] ) ? $o['order'] : $seed ),
				)
			);
			if ( ! $oid || is_wp_error( $oid ) ) {
				continue;
			}
			update_post_meta( $oid, $M, $ok );
			foreach ( array( 'name_en', 'head', 'head_role', 'tel', 'hours' ) as $k ) {
				update_post_meta( $oid, '_mcupr_of_' . $k, $txt( $o, $k ) );
			}
			update_post_meta( $oid, '_mcupr_of_address', sanitize_textarea_field( isset( $o['address'] ) ? $o['address'] : '' ) );
			update_post_meta( $oid, '_mcupr_of_email', sanitize_email( isset( $o['email'] ) ? $o['email'] : '' ) );
			update_post_meta( $oid, '_mcupr_of_color', (string) sanitize_hex_color( isset( $o['color'] ) ? $o['color'] : '' ) );
			foreach ( array( 'facebook', 'line', 'website', 'youtube' ) as $k ) {
				if ( ! empty( $o[ $k ] ) ) {
					update_post_meta( $oid, '_mcupr_of_' . $k, esc_url_raw( $o[ $k ], array( 'https' ) ) );
				}
			}
			$img( $oid, $ok . '-cover', $seed + 1, 1600, 700 );
			$r['offices']++;
		}
		$oid = (int) $oid;
		// ---- news and announcements
		$i = 0;
		foreach ( isset( $o['posts'] ) && is_array( $o['posts'] ) ? array_slice( $o['posts'], 0, 12 ) : array() as $p ) {
			$i++;
			$key = $ok . '-post-' . sanitize_key( $p['key'] );
			if ( mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
				continue;
			}
			$when = time() - max( 0, (int) $p['days_ago'] ) * DAY_IN_SECONDS;
			$id   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $txt( $p, 'title' ), 'post_excerpt' => $txt( $p, 'excerpt' ), 'post_content' => wp_kses_post( wpautop( $p['content'] ) ), 'post_date' => mcupr_demo_date( 'Y-m-d H:i:s', $when ) ) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, $M, $key );
			if ( ! empty( $p['cat'] ) && isset( $cats[ sanitize_title( $p['cat'] ) ] ) ) {
				wp_set_post_categories( $id, array( $cats[ sanitize_title( $p['cat'] ) ] ) );
			}
			mcupr_office_assign( $id, $oid );
			if ( 'office-activity' === ( isset( $p['cat'] ) ? $p['cat'] : '' ) ) {
				$img( $id, $key, $seed * 3 + $i );
			}
			$r['posts']++;
		}
		// ---- events
		foreach ( isset( $o['events'] ) && is_array( $o['events'] ) ? array_slice( $o['events'], 0, 8 ) : array() as $e ) {
			$key = $ok . '-event-' . sanitize_key( $e['key'] );
			if ( ! post_type_exists( 'mcupr_event' ) || mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
				continue;
			}
			$day = mcupr_demo_date( 'Y-m-d', time() + max( 0, (int) $e['in_days'] ) * DAY_IN_SECONDS );
			$id  = wp_insert_post( array( 'post_type' => 'mcupr_event', 'post_status' => 'publish', 'post_title' => $txt( $e, 'title' ), 'post_content' => wp_kses_post( wpautop( $e['content'] ) ) ) );
			if ( $id && ! is_wp_error( $id ) ) {
				$all = ! empty( $e['allday'] );
				update_post_meta( $id, $M, $key );
				update_post_meta( $id, '_mcupr_ev_allday', $all ? 1 : 0 );
				update_post_meta( $id, '_mcupr_ev_start', $day . ' ' . ( $all ? '00:00:00' : $txt( $e, 'from' ) . ':00' ) );
				update_post_meta( $id, '_mcupr_ev_end', $day . ' ' . ( $all ? '23:59:00' : $txt( $e, 'to' ) . ':00' ) );
				update_post_meta( $id, '_mcupr_ev_place', $txt( $e, 'place' ) );
				mcupr_office_assign( $id, $oid );
				$r['events']++;
			}
		}
		// ---- people
		$n = 0;
		foreach ( isset( $o['people'] ) && is_array( $o['people'] ) ? array_slice( $o['people'], 0, 30 ) : array() as $pp ) {
			$n++;
			$key = $ok . '-person-' . sanitize_key( $pp['key'] );
			if ( ! post_type_exists( 'mcupr_person' ) || mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'post_title' => $txt( $pp, 'name' ), 'menu_order' => $n ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, $M, $key );
				update_post_meta( $id, '_mcupr_person_role', $txt( $pp, 'role' ) );
				update_post_meta( $id, '_mcupr_person_email', sanitize_email( isset( $pp['email'] ) ? $pp['email'] : '' ) );
				update_post_meta( $id, '_mcupr_person_tel', $txt( $pp, 'tel' ) );
				if ( ! empty( $pp['group'] ) && isset( $groups[ sanitize_title( $pp['group'] ) ] ) ) {
					wp_set_object_terms( $id, array( $groups[ sanitize_title( $pp['group'] ) ] ), 'mcupr_person_group' );
				}
				mcupr_office_assign( $id, $oid );
				$r['people']++;
			}
		}
		// ---- album
		if ( ! empty( $o['album'] ) && post_type_exists( 'mcupr_album' ) ) {
			$key = $ok . '-album-' . sanitize_key( $o['album']['key'] );
			if ( mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
			} else {
				$id = wp_insert_post( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'post_title' => $txt( $o['album'], 'title' ) ) );
				if ( $id && ! is_wp_error( $id ) ) {
					update_post_meta( $id, $M, $key );
					$ids = array();
					for ( $k = 1; $k <= max( 1, min( 8, (int) $o['album']['count'] ) ); $k++ ) {
						$ai = $img( 0, $key . '-' . $k, $seed * 2 + $k, 1200, 800 );
						if ( $ai ) {
							$ids[] = $ai;
						}
					}
					update_post_meta( $id, '_mcupr_album_images', $ids );
					mcupr_office_assign( $id, $oid );
					$r['albums']++;
				}
			}
		}
		// ---- pages (they become tabs)
		$n = 0;
		foreach ( isset( $o['pages'] ) && is_array( $o['pages'] ) ? array_slice( $o['pages'], 0, 12 ) : array() as $pg ) {
			$n++;
			$key = $ok . '-page-' . sanitize_key( $pg['key'] );
			if ( mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
				continue;
			}
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $txt( $pg, 'title' ), 'post_excerpt' => $txt( $pg, 'excerpt' ), 'post_content' => wp_kses_post( wpautop( $pg['content'] ) ), 'menu_order' => $n ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, $M, $key );
				mcupr_office_assign( $id, $oid );
				$r['pages']++;
			}
		}
		// ---- work groups (inc/groups.php), with the people and news of the office marked with them
		$n = 0;
		foreach ( isset( $o['work_groups'] ) && is_array( $o['work_groups'] ) && post_type_exists( 'mcupr_group' ) ? array_slice( $o['work_groups'], 0, 8 ) : array() as $wg ) {
			$n++;
			$key = $ok . '-group-' . sanitize_key( $wg['key'] );
			if ( mcupr_demo_find( $key, $M ) ) {
				$r['skipped']++;
				continue;
			}
			$gid = wp_insert_post( array( 'post_type' => 'mcupr_group', 'post_status' => 'publish', 'post_title' => $txt( $wg, 'title' ), 'post_name' => sanitize_title( $txt( $wg, 'slug' ) ), 'post_excerpt' => $txt( $wg, 'excerpt' ), 'post_content' => wp_kses_post( wpautop( isset( $wg['content'] ) ? $wg['content'] : '' ) ), 'menu_order' => (int) ( isset( $wg['order'] ) ? $wg['order'] : $n ) ) );
			if ( ! $gid || is_wp_error( $gid ) ) {
				continue;
			}
			update_post_meta( $gid, $M, $key );
			mcupr_group_set_office( $gid, $oid );
			foreach ( array( 'name_en', 'tel', 'place' ) as $k ) {
				if ( '' !== $txt( $wg, $k ) ) {
					update_post_meta( $gid, '_mcupr_g_' . $k, $txt( $wg, $k ) );
				}
			}
			if ( ! empty( $wg['email'] ) ) {
				update_post_meta( $gid, '_mcupr_g_email', sanitize_email( $wg['email'] ) );
			}
			if ( ! empty( $wg['hours'] ) && is_string( $wg['hours'] ) ) {
				update_post_meta( $gid, '_mcupr_g_hours', implode( "\n", mcupr_group_lines( sanitize_textarea_field( $wg['hours'] ) ) ) );
			}
			$sv = mcupr_group_clean_services( isset( $wg['services'] ) ? $wg['services'] : array() );
			if ( $sv ) {
				update_post_meta( $gid, '_mcupr_g_services', $sv );
			}
			foreach ( array( 'posts' => 'post', 'people' => 'person' ) as $list => $kind ) {
				foreach ( isset( $o[ $list ] ) && is_array( $o[ $list ] ) ? $o[ $list ] : array() as $it ) {
					if ( isset( $it['work_group'], $it['key'] ) && $it['work_group'] === $wg['key'] ) {
						$pid = mcupr_demo_find( $ok . '-' . $kind . '-' . sanitize_key( $it['key'] ), $M );
						if ( $pid ) {
							mcupr_group_mark( $pid, $gid, 'person' === $kind ? ! empty( $it['ghead'] ) : null );
						}
					}
				}
			}
			$r['groups']++;
		}
	}
	return $r;
}

/** Delete what the demo made (also what was edited). Returns the number of items deleted. */
function mcupr_od_remove() {
	$n = 0;
	foreach ( array( mcupr_demo_types(), 'attachment' ) as $type ) {
		$ids = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'meta_key' => '_mcupr_demo_off', 'posts_per_page' => 500, 'fields' => 'ids', 'no_found_rows' => true, 'mcupr_office_all' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $ids as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$n++;
			}
		}
	}
	$d = mcupr_od_data();
	if ( $d ) { // the categories and groups it made, when nothing else uses them
		foreach ( array( 'categories' => 'category', 'groups' => 'mcupr_person_group' ) as $k => $tax ) {
			foreach ( isset( $d[ $k ] ) && is_array( $d[ $k ] ) ? array_keys( $d[ $k ] ) : array() as $slug ) {
				$t = get_term_by( 'slug', sanitize_title( $slug ), $tax );
				if ( $t && 0 === (int) $t->count ) {
					wp_delete_term( $t->term_id, $tax );
				}
			}
		}
	}
	return $n;
}

function mcupr_od_message( $r ) {
	$parts = array();
	foreach ( array( 'offices' => __( 'offices', 'mcupr' ), 'posts' => __( 'news', 'mcupr' ), 'events' => __( 'events', 'mcupr' ), 'people' => __( 'people', 'mcupr' ), 'albums' => __( 'albums', 'mcupr' ), 'pages' => __( 'pages', 'mcupr' ), 'groups' => __( 'work groups', 'mcupr' ), 'images' => __( 'sample pictures', 'mcupr' ) ) as $k => $label ) {
		if ( $r[ $k ] ) {
			$parts[] = $r[ $k ] . ' ' . $label;
		}
	}
	return $parts ? sprintf( /* translators: %s: list */ __( 'Offices demo made: %s.', 'mcupr' ), implode( ', ', $parts ) ) : __( 'Nothing new was made (the demo items already exist).', 'mcupr' );
}
