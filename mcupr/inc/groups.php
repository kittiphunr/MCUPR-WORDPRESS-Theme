<?php
/**
 * Work groups (กลุ่มงาน) of an office: each group of an office (finance, general affairs, buildings…) has a landing page of
 * its own inside the office's mini-site:
 *
 *   /office/<office-slug>/group/<group-slug>/      (Plain permalinks: ?post_type=mcupr_group&p=<id>)
 *
 * A group is a post type "mcupr_group" that belongs to one office: it carries the office's hidden mark (MCUPR_OFFICE_TAX,
 * so Office editors edit the groups of their own offices only, and the group is kept off the main site's lists like the
 * rest of the office's content) and the office id in the meta "_mcupr_g_office" (kept in step with the mark).
 *
 * The page has these parts, each one switched off by a tick on the group or hidden by itself when it has nothing to show:
 *   about (the text of the group) · services (up to 8 shortcut cards) · news (the office's posts marked with the group) ·
 *   people (the office's staff marked with the group, the head first) · documents (files to download) · contact
 *   (telephone, e-mail, place, opening hours and a form of the form builder, or an e-mail button).
 * The visitor side is in groups-front.php.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_GROUP = 'mcupr_group';

function mcupr_groups_register() {
	register_post_type(
		MCUPR_GROUP,
		array(
			'labels'              => array(
				'name'               => __( 'Work groups', 'mcupr' ),
				'singular_name'      => __( 'Work group', 'mcupr' ),
				'menu_name'          => __( 'Work groups', 'mcupr' ),
				'all_items'          => __( 'Work groups of the offices', 'mcupr' ),
				'add_new_item'       => __( 'Add a work group', 'mcupr' ),
				'new_item'           => __( 'New work group', 'mcupr' ),
				'edit_item'          => __( 'Edit work group', 'mcupr' ),
				'view_item'          => __( 'View work group', 'mcupr' ),
				'search_items'       => __( 'Search work groups', 'mcupr' ),
				'not_found'          => __( 'No work groups yet', 'mcupr' ),
				'not_found_in_trash' => __( 'No work groups in the trash', 'mcupr' ),
				'attributes'         => __( 'Order in the list', 'mcupr' ),
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false, // the office mark keeps it off the main site's search (inc/offices.php)
			'has_archive'         => false,
			'rewrite'             => false, // the address is /office/<office>/group/<group>/ (the rule below and mcupr_group_url())
			'query_var'           => 'mcupr_group',
			'show_ui'             => true,
			'show_in_rest'        => false, // the classic editor: the boxes below are the whole form
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'editor', 'excerpt', 'page-attributes' ),
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		)
	);
	add_rewrite_rule( '^office/([^/]+)/group/([^/]+)/?$', 'index.php?mcupr_group=$matches[2]&mcupr_gof=$matches[1]', 'top' );
	mcupr_rewrite_wanted( 'mcupr_rewrite_groups', MCUPR_VERSION ); // the one flush of inc/compat.php
}
add_action( 'init', 'mcupr_groups_register', 8 );

add_filter(
	'query_vars',
	static function ( $vars ) {
		$vars[] = 'mcupr_gof';
		return $vars;
	}
);

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = MCUPR_GROUP;
		return $types;
	}
);

/* ------------------------------------------------------------------ the address */

/** The office a group belongs to (0 = none). */
function mcupr_group_office( $gid ) {
	$gid = (int) $gid;
	$o   = (int) get_post_meta( $gid, '_mcupr_g_office', true );
	if ( $o && 'mcupr_office' === get_post_type( $o ) ) {
		return $o;
	}
	return MCUPR_GROUP === get_post_type( $gid ) ? mcupr_office_of( $gid ) : 0;
}

/** Put a group into an office (the mark; the meta follows by itself, see below). */
function mcupr_group_set_office( $gid, $office_id ) {
	mcupr_office_assign( $gid, $office_id );
	mcupr_group_sync_office( $gid );
}

/** The meta "_mcupr_g_office" follows the office mark. */
function mcupr_group_sync_office( $gid ) {
	if ( MCUPR_GROUP !== get_post_type( $gid ) ) {
		return;
	}
	$o = mcupr_office_of( $gid );
	if ( $o ) {
		update_post_meta( $gid, '_mcupr_g_office', $o );
	} else {
		delete_post_meta( $gid, '_mcupr_g_office' );
	}
}
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( MCUPR_OFFICE_TAX === $taxonomy ) {
			mcupr_group_sync_office( $object_id );
		}
	},
	10,
	4
);

/**
 * The address of a group: /office/<office>/group/<group>/ with pretty permalinks; ?post_type=mcupr_group&p=<id> with Plain
 * permalinks, for a draft, or for a group that has no office (yet).
 */
function mcupr_group_url( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$oid    = mcupr_group_office( $post->ID );
	$office = $oid ? get_post( $oid ) : null;
	if ( mcupr_plain_mode() || ! $office || '' === (string) $post->post_name || '' === (string) $office->post_name || in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
		return add_query_arg( array( 'post_type' => MCUPR_GROUP, 'p' => (int) $post->ID ), home_url( '/' ) );
	}
	return home_url( user_trailingslashit( 'office/' . $office->post_name . '/group/' . $post->post_name ) );
}
add_filter(
	'post_type_link',
	static function ( $link, $post ) {
		return $post && MCUPR_GROUP === $post->post_type ? mcupr_group_url( $post ) : $link;
	},
	10,
	2
);

// /office/<office>/group/<group>/ is checked below (the office in the address must be the group's own), not by WordPress' guess
add_filter(
	'redirect_canonical',
	static function ( $url ) {
		return get_query_var( 'mcupr_gof' ) ? false : $url;
	}
);

add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( MCUPR_GROUP ) ) {
			return;
		}
		$gof = (string) get_query_var( 'mcupr_gof' );
		if ( '' === $gof ) {
			return;
		}
		$gid  = get_queried_object_id();
		$oid  = mcupr_group_office( $gid );
		$slug = $oid ? (string) get_post_field( 'post_name', $oid ) : '';
		if ( '' === $slug || rawurldecode( strtolower( $gof ) ) !== rawurldecode( strtolower( $slug ) ) ) {
			wp_safe_redirect( get_permalink( $gid ), 301 );
			exit;
		}
	}
);

/** "Group – Office" in the browser tab. */
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_singular( MCUPR_GROUP ) ) {
			$oid = mcupr_group_office( get_queried_object_id() );
			if ( $oid ) {
				$parts['title'] = get_the_title( get_queried_object_id() ) . ' – ' . get_the_title( $oid );
			}
		}
		return $parts;
	}
);

/* ------------------------------------------------------------------ data */

/** What was read for this request (news, people, documents, groups of an office…), forgotten whenever any of it changes. */
function &mcupr_group_memo() {
	static $m = array();
	return $m;
}

function mcupr_group_memo_reset() {
	$m = &mcupr_group_memo();
	$m = array();
}
foreach ( array( 'save_post', 'deleted_post', 'transition_post_status', 'set_object_terms' ) as $mcupr_h ) {
	add_action( $mcupr_h, 'mcupr_group_memo_reset' );
}

/** The parts of a group page, in their order. "about" is the text of the group (it has no switch: no text, no part). */
function mcupr_group_section_keys() {
	return array( 'about', 'services', 'news', 'people', 'docs', 'contact' );
}

/** The parts that can be switched off on a group. */
function mcupr_group_toggle_keys() {
	return array( 'services', 'news', 'people', 'docs', 'contact' );
}

/** The icons a service card may have (a short list, so every group page looks alike): Feather name => what it is for. */
function mcupr_group_icons() {
	return array(
		'file-text'    => __( 'Document', 'mcupr' ),
		'edit-3'       => __( 'Request / application', 'mcupr' ),
		'dollar-sign'  => __( 'Money / fees', 'mcupr' ),
		'credit-card'  => __( 'Payment', 'mcupr' ),
		'check-circle' => __( 'Approval / results', 'mcupr' ),
		'calendar'     => __( 'Calendar / booking', 'mcupr' ),
		'clipboard'    => __( 'Registration / checklist', 'mcupr' ),
		'book-open'    => __( 'Manual / rules', 'mcupr' ),
		'users'        => __( 'People / meetings', 'mcupr' ),
		'tool'         => __( 'Repairs / maintenance', 'mcupr' ),
		'award'        => __( 'Scholarships / awards', 'mcupr' ),
		'help-circle'  => __( 'Questions / help', 'mcupr' ),
	);
}

/** Lines of a text box (opening hours): trimmed, empty ones left out, at most 12. */
function mcupr_group_lines( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $l ) {
		$l = trim( $l );
		if ( '' !== $l ) {
			$out[] = $l;
		}
	}
	return array_slice( $out, 0, 12 );
}

/** Everything a group keeps. */
function mcupr_group_data( $gid ) {
	$gid = (int) $gid;
	$g   = static function ( $k ) use ( $gid ) {
		return get_post_meta( $gid, '_mcupr_g_' . $k, true );
	};
	$sv  = $g( 'services' );
	$dc  = $g( 'docs' );
	$off = $g( 'off' );
	return array(
		'office'   => mcupr_group_office( $gid ),
		'name_en'  => (string) $g( 'name_en' ),
		'tel'      => (string) $g( 'tel' ),
		'email'    => (string) $g( 'email' ),
		'place'    => (string) $g( 'place' ),
		'hours'    => mcupr_group_lines( (string) $g( 'hours' ) ),
		'form'     => (int) $g( 'form' ),
		'services' => is_array( $sv ) ? $sv : array(),
		'docs'     => is_array( $dc ) ? $dc : array(),
		'off'      => is_array( $off ) ? array_values( array_intersect( mcupr_group_toggle_keys(), $off ) ) : array(),
	);
}

/** A link typed by an editor: http(s), mailto:, tel: or a path of this site; anything else (javascript:…) gives ''. */
function mcupr_group_clean_url( $u ) {
	$u = trim( (string) $u );
	return '' === $u ? '' : (string) esc_url_raw( $u, array( 'http', 'https', 'mailto', 'tel' ) );
}

/** The service cards as sent by the form: title (needed), short text, an icon of the list, a link; at most 8. */
function mcupr_group_clean_services( $rows ) {
	$icons = mcupr_group_icons();
	$out   = array();
	foreach ( is_array( $rows ) ? $rows : array() as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$s     = static function ( $k ) use ( $r ) {
			return isset( $r[ $k ] ) && is_scalar( $r[ $k ] ) ? (string) $r[ $k ] : '';
		};
		$title = sanitize_text_field( $s( 'title' ) );
		if ( '' === $title ) {
			continue;
		}
		$icon  = $s( 'icon' );
		$out[] = array(
			'title' => mb_substr( $title, 0, 80 ),
			'text'  => mb_substr( sanitize_text_field( $s( 'text' ) ), 0, 160 ),
			'icon'  => isset( $icons[ $icon ] ) ? $icon : 'file-text',
			'url'   => mcupr_group_clean_url( $s( 'url' ) ),
		);
		if ( count( $out ) >= 8 ) {
			break;
		}
	}
	return $out;
}

/** The documents as sent by the form: title, a file of the Media Library (its id) or a link; at most 30. */
function mcupr_group_clean_docs( $rows ) {
	$out = array();
	foreach ( is_array( $rows ) ? $rows : array() as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$title = isset( $r['title'] ) && is_scalar( $r['title'] ) ? sanitize_text_field( (string) $r['title'] ) : '';
		$att   = isset( $r['att'] ) && is_scalar( $r['att'] ) ? absint( $r['att'] ) : 0;
		$att   = $att && 'attachment' === get_post_type( $att ) ? $att : 0;
		$url   = $att ? '' : mcupr_group_clean_url( isset( $r['url'] ) && is_scalar( $r['url'] ) ? $r['url'] : '' );
		if ( ! $att && '' === $url ) {
			continue;
		}
		if ( '' === $title ) {
			$title = $att ? get_the_title( $att ) : sanitize_text_field( rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
		}
		$out[] = array( 'title' => mb_substr( '' !== $title ? $title : __( 'Document', 'mcupr' ), 0, 120 ), 'att' => $att, 'url' => $url );
		if ( count( $out ) >= 30 ) {
			break;
		}
	}
	return $out;
}

/** Is this a published form of the form builder? */
function mcupr_group_form_ok( $form_id ) {
	$form_id = (int) $form_id;
	return $form_id > 0 && 'mcupr_fb' === get_post_type( $form_id ) && 'publish' === get_post_status( $form_id );
}

/** Pure: which parts show, in order – those not switched off ($off) that have something ($has: key => bool). */
function mcupr_group_sections_pick( array $off, array $has ) {
	$out = array();
	foreach ( mcupr_group_section_keys() as $k ) {
		if ( ! in_array( $k, $off, true ) && ! empty( $has[ $k ] ) ) {
			$out[] = $k;
		}
	}
	return $out;
}

/** Pure: does the contact part have anything (telephone, e-mail, place, hours, or a form that can be shown)? */
function mcupr_group_has_contact( array $d, $form_ok = false ) {
	return '' !== $d['tel'] || '' !== $d['email'] || '' !== $d['place'] || ! empty( $d['hours'] ) || $form_ok;
}

/** The parts of a group page that are shown, in order. */
function mcupr_group_sections( $gid ) {
	$d   = mcupr_group_data( $gid );
	$has = array(
		'about'    => '' !== trim( (string) get_post_field( 'post_content', $gid ) ),
		'services' => ! empty( $d['services'] ),
		'news'     => in_array( 'news', $d['off'], true ) ? false : (bool) mcupr_group_news( $gid ),
		'people'   => in_array( 'people', $d['off'], true ) ? false : (bool) mcupr_group_people( $gid ),
		'docs'     => (bool) mcupr_group_docs( $gid ),
		'contact'  => mcupr_group_has_contact( $d, mcupr_group_form_ok( $d['form'] ) ),
	);
	return mcupr_group_sections_pick( $d['off'], $has );
}

/** The latest news of a group: the office's posts marked with it (one query, kept for the request). */
function mcupr_group_news( $gid, $limit = 5 ) {
	$cache = &mcupr_group_memo();
	$gid   = (int) $gid;
	$k     = 'news|' . $gid . '|' . (int) $limit;
	if ( ! isset( $cache[ $k ] ) ) {
		$oid         = mcupr_group_office( $gid );
		$cache[ $k ] = $oid ? get_posts(
			mcupr_office_query_args(
				$oid,
				'post',
				array(
					'posts_per_page'   => (int) $limit,
					'orderby'          => 'date',
					'order'            => 'DESC',
					'meta_query'       => array( array( 'key' => '_mcupr_post_group', 'value' => (string) $gid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
					'suppress_filters' => false,
				)
			)
		) : array();
	}
	return $cache[ $k ];
}

/** Pure: heads of the group first, then the others, each in the order they came. */
function mcupr_group_heads_first( array $people, array $heads ) {
	$a = array();
	$b = array();
	foreach ( $people as $p ) {
		if ( in_array( (int) $p->ID, $heads, true ) ) {
			$a[] = $p;
		} else {
			$b[] = $p;
		}
	}
	return array_merge( $a, $b );
}

/** The people of a group: the office's staff marked with it, the head(s) first, then by "Order in the list" (one query). */
function mcupr_group_people( $gid ) {
	$cache = &mcupr_group_memo();
	$gid   = (int) $gid;
	$k     = 'people|' . $gid;
	if ( ! isset( $cache[ $k ] ) ) {
		$oid    = mcupr_group_office( $gid );
		$people = $oid ? get_posts(
			mcupr_office_query_args(
				$oid,
				'mcupr_person',
				array(
					'posts_per_page'   => 100,
					'orderby'          => 'menu_order title',
					'order'            => 'ASC',
					'meta_query'       => array( array( 'key' => '_mcupr_person_group', 'value' => (string) $gid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
					'suppress_filters' => false,
				)
			)
		) : array();
		$heads  = array();
		foreach ( $people as $p ) {
			if ( '1' === (string) get_post_meta( $p->ID, '_mcupr_person_ghead', true ) ) { // the meta came with the query
				$heads[] = (int) $p->ID;
			}
		}
		$cache[ $k ] = mcupr_group_heads_first( $people, $heads );
	}
	return $cache[ $k ];
}

/** Is a person a head of their group? */
function mcupr_group_is_head( $person_id ) {
	return '1' === (string) get_post_meta( $person_id, '_mcupr_person_ghead', true );
}

/** Pure: the kind of a file from its name or address ("PDF", "DOCX"…), or ''. */
function mcupr_group_file_kind( $path ) {
	$p   = (string) wp_parse_url( (string) $path, PHP_URL_PATH );
	$ext = strtolower( pathinfo( '' !== $p ? $p : (string) $path, PATHINFO_EXTENSION ) );
	return preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? strtoupper( $ext ) : '';
}

/**
 * The documents of a group, ready to show: title, url, kind, size (text, files of the Media Library only) and the time it
 * was last changed (0 for a link). The attachments are read in one go.
 */
function mcupr_group_docs( $gid ) {
	$cache = &mcupr_group_memo();
	$gid   = (int) $gid;
	$ck    = 'docs|' . $gid;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$rows = mcupr_group_data( $gid )['docs'];
	$ids  = array();
	foreach ( $rows as $r ) {
		if ( ! empty( $r['att'] ) ) {
			$ids[] = (int) $r['att'];
		}
	}
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
	}
	$out = array();
	foreach ( $rows as $r ) {
		$att = isset( $r['att'] ) ? (int) $r['att'] : 0;
		if ( $att && 'attachment' === get_post_type( $att ) ) {
			$url  = (string) wp_get_attachment_url( $att );
			$file = (string) get_attached_file( $att );
			$meta = wp_get_attachment_metadata( $att );
			$size = is_array( $meta ) && ! empty( $meta['filesize'] ) ? (int) $meta['filesize'] : ( '' !== $file && is_readable( $file ) ? (int) filesize( $file ) : 0 );
			$out[] = array(
				'title' => (string) $r['title'],
				'url'   => $url,
				'kind'  => mcupr_group_file_kind( '' !== $file ? $file : $url ),
				'size'  => $size ? size_format( $size, $size >= MB_IN_BYTES ? 1 : 0 ) : '',
				'time'  => (int) get_post_modified_time( 'U', true, $att ),
			);
		} elseif ( ! empty( $r['url'] ) ) {
			$out[] = array( 'title' => (string) $r['title'], 'url' => (string) $r['url'], 'kind' => mcupr_group_file_kind( $r['url'] ), 'size' => '', 'time' => 0 );
		}
	}
	$out = array_values(
		array_filter(
			$out,
			static function ( $d ) {
				return '' !== $d['url'];
			}
		)
	);
	$cache[ $ck ] = $out;
	return $out;
}

/** The groups of an office, in order ("Order in the list", then the name). One query, kept for the request. */
function mcupr_office_groups( $oid ) {
	$cache = &mcupr_group_memo();
	$oid   = (int) $oid;
	$k     = 'office|' . $oid;
	if ( ! isset( $cache[ $k ] ) ) {
		$cache[ $k ] = $oid ? get_posts( mcupr_office_query_args( $oid, MCUPR_GROUP, array( 'posts_per_page' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC', 'suppress_filters' => false ) ) ) : array();
	}
	return $cache[ $k ];
}

/**
 * How many people each group of an office has: array( group id => n ). Counted with two queries for all the groups of
 * the office together and kept like the office numbers (mcupr_office_counts_clear() forgets them).
 */
function mcupr_group_people_counts( $oid ) {
	$oid  = (int) $oid;
	$hide = function_exists( 'mcupr_aud_hidden_ids' ) ? mcupr_aud_hidden_ids() : array();
	sort( $hide );
	$k   = md5( implode( ',', $hide ) );
	$all = get_transient( 'mcupr_group_counts' );
	$all = is_array( $all ) ? $all : array();
	if ( isset( $all[ $oid ][ $k ] ) && is_array( $all[ $oid ][ $k ] ) ) {
		return $all[ $oid ][ $k ];
	}
	$ids = $oid ? get_posts( mcupr_office_query_args( $oid, 'mcupr_person', array( 'posts_per_page' => 1000, 'fields' => 'ids', 'meta_key' => '_mcupr_person_group' ) ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
	$n   = array();
	if ( $ids ) {
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			$g = (int) get_post_meta( $id, '_mcupr_person_group', true );
			if ( $g ) {
				$n[ $g ] = isset( $n[ $g ] ) ? $n[ $g ] + 1 : 1;
			}
		}
	}
	$keep        = isset( $all[ $oid ] ) && is_array( $all[ $oid ] ) ? array_slice( $all[ $oid ], -19, null, true ) : array();
	$keep[ $k ]  = $n;
	$all[ $oid ] = $keep;
	set_transient( 'mcupr_group_counts', $all, 12 * HOUR_IN_SECONDS );
	return $n;
}

// marking a post or a person with a group changes the numbers
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function ( $mid, $post_id, $key ) {
			if ( in_array( $key, array( '_mcupr_person_group', '_mcupr_post_group', '_mcupr_person_ghead' ), true ) && function_exists( 'mcupr_office_counts_clear' ) ) {
				mcupr_office_counts_clear();
			}
			if ( 0 === strpos( (string) $key, '_mcupr_g_' ) || in_array( $key, array( '_mcupr_person_group', '_mcupr_post_group', '_mcupr_person_ghead', '_thumbnail_id' ), true ) ) {
				mcupr_group_memo_reset();
			}
		},
		10,
		3
	);
}

/* ------------------------------------------------------------------ marking news and people with a group */

/** The groups this user may choose, by office: array( office id => array( group posts ) ). Administrators get all. */
function mcupr_groups_for_user( $user_id = 0 ) {
	$cache   = &mcupr_group_memo();
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$ck      = 'user|' . $user_id;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$mine = mcupr_user_offices( $user_id );
	$all  = get_posts( array( 'post_type' => MCUPR_GROUP, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => 300, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true, 'mcupr_office_all' => 1 ) );
	$out  = array();
	foreach ( $all as $g ) {
		$o = mcupr_group_office( $g->ID );
		if ( $o && ( null === $mine || in_array( $o, $mine, true ) ) ) {
			$out[ $o ][] = $g;
		}
	}
	$cache[ $ck ] = $out;
	return $out;
}

/** The meta key that marks a post or a person with its group ('' for other types). */
function mcupr_group_mark_key( $type ) {
	if ( 'post' === $type ) {
		return '_mcupr_post_group';
	}
	return 'mcupr_person' === $type ? '_mcupr_person_group' : '';
}

/** The group a post or a person is marked with (0 = none). */
function mcupr_group_of( $post_id ) {
	$key = mcupr_group_mark_key( get_post_type( $post_id ) );
	return '' === $key ? 0 : (int) get_post_meta( $post_id, $key, true );
}

/**
 * Mark a post or a person with a group of its own office (0 = no group). A group of another office, or something that is not
 * a group, is refused and the mark is removed. $head (people only): true / false = head of the group or not, null = unchanged.
 * Returns true when the post now carries $gid.
 */
function mcupr_group_mark( $post_id, $gid, $head = null ) {
	$type = get_post_type( $post_id );
	$key  = mcupr_group_mark_key( $type );
	if ( '' === $key ) {
		return false;
	}
	$gid = (int) $gid;
	$ok  = $gid && MCUPR_GROUP === get_post_type( $gid ) && mcupr_group_office( $gid ) && mcupr_group_office( $gid ) === mcupr_office_of( $post_id );
	if ( ! $ok ) {
		delete_post_meta( $post_id, $key );
		if ( 'mcupr_person' === $type ) {
			delete_post_meta( $post_id, '_mcupr_person_ghead' );
		}
		return false;
	}
	update_post_meta( $post_id, $key, $gid );
	if ( 'mcupr_person' === $type && null !== $head ) {
		if ( $head ) {
			update_post_meta( $post_id, '_mcupr_person_ghead', '1' );
		} else {
			delete_post_meta( $post_id, '_mcupr_person_ghead' );
		}
	}
	return true;
}

add_action(
	'add_meta_boxes',
	static function ( $type ) {
		if ( in_array( $type, array( 'post', 'mcupr_person' ), true ) && mcupr_groups_for_user() ) {
			add_meta_box( 'mcupr_work_group', __( 'Work group', 'mcupr' ), 'mcupr_group_member_box', $type, 'side', 'default' );
		}
	}
);

function mcupr_group_member_box( $post ) {
	wp_nonce_field( 'mcupr_wg_save', 'mcupr_wg_nonce' );
	$cur = mcupr_group_of( $post->ID );
	echo '<p><label for="mcupr-wg-sel"><strong>' . esc_html__( 'Belongs to the work group', 'mcupr' ) . '</strong></label><br><select id="mcupr-wg-sel" name="mcupr_wg" class="widefat"><option value="0">' . esc_html__( '— no work group —', 'mcupr' ) . '</option>';
	foreach ( mcupr_groups_for_user() as $oid => $groups ) {
		echo '<optgroup label="' . esc_attr( get_the_title( $oid ) ) . '">';
		foreach ( $groups as $g ) {
			echo '<option value="' . (int) $g->ID . '"' . selected( $cur, $g->ID, false ) . '>' . esc_html( get_the_title( $g ) ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></p>';
	if ( 'mcupr_person' === $post->post_type ) {
		echo '<p><label><input type="checkbox" name="mcupr_wg_head" value="1"' . checked( mcupr_group_is_head( $post->ID ), true, false ) . '> ' . esc_html__( 'Head of the work group (shown first, on a larger card)', 'mcupr' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'The person is shown on the page of the chosen work group. Choose a group of the same office as in “Belongs to office”.', 'mcupr' ) . '</p>';
	} else {
		echo '<p class="description">' . esc_html__( 'The news is shown on the page of the chosen work group (the latest five) and in the office news of that group. Choose a group of the same office as in “Belongs to office”.', 'mcupr' ) . '</p>';
	}
}

/** Save the group of a post or a person – after its office (priority 20), so the group can be checked against it. */
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, array( 'post', 'mcupr_person' ), true ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( mcupr_can_save_box( $post_id, 'mcupr_wg_nonce', 'mcupr_wg_save' ) ) {
			$gid  = isset( $_POST['mcupr_wg'] ) ? absint( wp_unslash( $_POST['mcupr_wg'] ) ) : 0;
			$head = 'mcupr_person' === $post->post_type ? ! empty( $_POST['mcupr_wg_head'] ) : null;
			mcupr_group_mark( $post_id, $gid, $head );
			return;
		}
		// saved some other way (quick edit, import): a group of another office is dropped
		$gid = mcupr_group_of( $post_id );
		if ( $gid ) {
			mcupr_group_mark( $post_id, $gid );
		}
	},
	30,
	2
);

/* ------------------------------------------------------------------ the group's own editor screen */

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$s = get_current_screen();
		if ( $s && MCUPR_GROUP === $s->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'mcupr-group-admin', get_theme_file_uri( 'assets/js/group-admin.js' ), array( 'jquery' ), MCUPR_VERSION, true );
			wp_localize_script( 'mcupr-group-admin', 'mcuprGroup', array( 'pick' => __( 'Choose a file', 'mcupr' ), 'max' => 8 ) );
			wp_register_style( 'mcupr-group-admin', false, array(), MCUPR_VERSION );
			wp_enqueue_style( 'mcupr-group-admin' );
			wp_add_inline_style( 'mcupr-group-admin', '.mcg-help{margin:.25rem 0 .75rem;padding:.5rem .75rem;border-left:4px solid #2271b1;background:#f0f6fc}.mcg-rows{display:grid;gap:8px}.mcg-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr)) auto;gap:8px;align-items:end;padding:10px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7}.mcg-row label{font-size:12px;font-weight:600}.mcg-row input,.mcg-row select{width:100%}.mcg-file{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.mcg-file .mcg-fname{font-weight:400;overflow-wrap:anywhere}.mcg-show label{display:inline-block;margin:2px 14px 2px 0}' );
		}
	}
);

/** The nonce of the group boxes (outside the boxes, so it is there even when a box is hidden in "Screen Options"). */
add_action(
	'edit_form_after_title',
	static function ( $post ) {
		if ( MCUPR_GROUP === $post->post_type ) {
			wp_nonce_field( 'mcupr_group_save', 'mcupr_group_nonce' );
			echo '<p class="mcg-help">' . esc_html__( 'The name above is the name of the work group. The text box below is “About the work group” (optional). The excerpt is a one-line description shown on the office home page. Choose the office in “Belongs to office” on the right; “Order in the list” sets the order on the office home page.', 'mcupr' ) . '</p>';
		}
	}
);

add_action(
	'add_meta_boxes_' . MCUPR_GROUP,
	static function () {
		add_meta_box( 'mcupr_group_parts', __( 'Parts of the page', 'mcupr' ), 'mcupr_group_box_parts', MCUPR_GROUP, 'normal', 'high' );
		add_meta_box( 'mcupr_group_services', __( 'Services (shortcut cards)', 'mcupr' ), 'mcupr_group_box_services', MCUPR_GROUP, 'normal', 'high' );
		add_meta_box( 'mcupr_group_docs', __( 'Forms and documents to download', 'mcupr' ), 'mcupr_group_box_docs', MCUPR_GROUP, 'normal', 'default' );
		add_meta_box( 'mcupr_group_contact', __( 'Contact', 'mcupr' ), 'mcupr_group_box_contact', MCUPR_GROUP, 'normal', 'default' );
		add_meta_box( 'mcupr_group_who', __( 'News and people of the group', 'mcupr' ), 'mcupr_group_box_who', MCUPR_GROUP, 'side', 'default' );
	}
);

function mcupr_group_box_parts( $post ) {
	$d     = mcupr_group_data( $post->ID );
	$names = array(
		'services' => __( 'Services', 'mcupr' ),
		'news'     => __( 'Announcements and news', 'mcupr' ),
		'people'   => __( 'People', 'mcupr' ),
		'docs'     => __( 'Documents to download', 'mcupr' ),
		'contact'  => __( 'Contact', 'mcupr' ),
	);
	echo '<p class="mcg-help">' . esc_html__( 'Untick a part to hide it. A part with nothing in it is hidden by itself, so you can leave everything ticked.', 'mcupr' ) . '</p><p class="mcg-show">';
	foreach ( $names as $k => $label ) {
		echo '<label><input type="checkbox" name="mcupr_g[show][]" value="' . esc_attr( $k ) . '"' . checked( ! in_array( $k, $d['off'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</p><p><label><strong>' . esc_html__( 'Name in English (optional)', 'mcupr' ) . '</strong><br><input type="text" class="widefat" name="mcupr_g[name_en]" value="' . esc_attr( $d['name_en'] ) . '" placeholder="Finance and Accounting Group"></label></p>';
	if ( 'publish' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Address of the page:', 'mcupr' ) . ' <a href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">' . esc_html( rawurldecode( get_permalink( $post ) ) ) . '</a></p>';
	}
}

/** One repeater: $fields = array( key => array( label, type [text|url|select|file], options? ) ). */
function mcupr_group_repeater( $name, $rows, $fields, $add_label, $max = 0 ) {
	$row = static function ( $i, $r ) use ( $name, $fields ) {
		echo '<div class="mcg-row" data-gr-row>';
		foreach ( $fields as $k => $f ) {
			$val = isset( $r[ $k ] ) ? (string) $r[ $k ] : '';
			$nm  = 'mcupr_g[' . $name . '][' . $i . '][' . $k . ']';
			if ( 'file' === $f[1] ) {
				$fname = $val && 'attachment' === get_post_type( (int) $val ) ? basename( (string) get_attached_file( (int) $val ) ) : '';
				echo '<div><span style="font-size:12px;font-weight:600">' . esc_html( $f[0] ) . '</span><div class="mcg-file"><input type="hidden" name="' . esc_attr( $nm ) . '" value="' . esc_attr( $val ) . '" data-gr-att><button type="button" class="button" data-gr-pick>' . esc_html__( 'Choose a file', 'mcupr' ) . '</button><span class="mcg-fname" data-gr-fname>' . esc_html( $fname ) . '</span><button type="button" class="button-link" data-gr-unpick>' . esc_html__( 'Remove', 'mcupr' ) . '</button></div></div>';
				continue;
			}
			echo '<label>' . esc_html( $f[0] ) . '<br>';
			if ( 'select' === $f[1] ) {
				echo '<select name="' . esc_attr( $nm ) . '">';
				foreach ( $f[2] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input type="' . esc_attr( $f[1] ) . '" name="' . esc_attr( $nm ) . '" value="' . esc_attr( $val ) . '"' . ( ! empty( $f[2] ) ? ' placeholder="' . esc_attr( $f[2] ) . '"' : '' ) . '>';
			}
			echo '</label>';
		}
		echo '<button type="button" class="button-link-delete" data-gr-del aria-label="' . esc_attr__( 'Remove this row', 'mcupr' ) . '">&times;</button></div>';
	};
	echo '<div data-gr-rep' . ( $max ? ' data-gr-max="' . (int) $max . '"' : '' ) . '><div class="mcg-rows" data-gr-rows>';
	foreach ( array_values( $rows ) as $i => $r ) {
		$row( $i, $r );
	}
	echo '</div><p><button type="button" class="button" data-gr-add>' . esc_html( $add_label ) . '</button></p><template data-gr-tpl>';
	$row( '__i__', array() );
	echo '</template></div>';
}

function mcupr_group_box_services( $post ) {
	$icons = mcupr_group_icons();
	echo '<p class="mcg-help">' . esc_html__( 'Up to 8 cards for what people come to the group for (e.g. “Pay a fee”, “Ask for a refund”). Each card: a short title, one line of text, a picture from the list and a link (a page of the website, a form, or another website).', 'mcupr' ) . '</p>';
	mcupr_group_repeater(
		'services',
		mcupr_group_data( $post->ID )['services'],
		array(
			'title' => array( __( 'Title', 'mcupr' ), 'text', __( 'e.g. Pay a fee', 'mcupr' ) ),
			'text'  => array( __( 'Short text', 'mcupr' ), 'text', __( 'e.g. How, where and when', 'mcupr' ) ),
			'icon'  => array( __( 'Picture', 'mcupr' ), 'select', $icons ),
			'url'   => array( __( 'Link', 'mcupr' ), 'url', 'https://' ),
		),
		__( '+ Add a service', 'mcupr' ),
		8
	);
}

function mcupr_group_box_docs( $post ) {
	echo '<p class="mcg-help">' . esc_html__( 'Forms and documents people can download. Choose a file from the Media Library (its kind, size and date are shown by themselves) or paste a link instead.', 'mcupr' ) . '</p>';
	mcupr_group_repeater(
		'docs',
		mcupr_group_data( $post->ID )['docs'],
		array(
			'title' => array( __( 'Title', 'mcupr' ), 'text', __( 'e.g. Refund request form', 'mcupr' ) ),
			'att'   => array( __( 'File from the Media Library', 'mcupr' ), 'file' ),
			'url'   => array( __( 'or a link', 'mcupr' ), 'url', 'https://' ),
		),
		__( '+ Add a document', 'mcupr' )
	);
}

function mcupr_group_box_contact( $post ) {
	$d = mcupr_group_data( $post->ID );
	$f = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_g[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	echo '<p class="mcg-help">' . esc_html__( 'How people reach the group. Leave a box empty to leave it out. With no form chosen, a button to write an e-mail is shown when there is an e-mail address.', 'mcupr' ) . '</p>';
	$f( __( 'Telephone', 'mcupr' ), 'tel', $d['tel'], 'text', __( 'e.g. 054-000-011 ext. 101', 'mcupr' ) );
	$f( __( 'Email', 'mcupr' ), 'email', $d['email'], 'email' );
	$f( __( 'Place', 'mcupr' ), 'place', $d['place'], 'text', __( 'e.g. Office building, 1st floor, room 105', 'mcupr' ) );
	echo '<p><label><strong>' . esc_html__( 'Opening hours (one line each)', 'mcupr' ) . '</strong><br><textarea class="widefat" rows="3" name="mcupr_g[hours]" placeholder="' . esc_attr__( 'e.g. Mon–Fri 08:30–16:30', 'mcupr' ) . '">' . esc_textarea( implode( "\n", $d['hours'] ) ) . '</textarea></label></p>';
	$forms = get_posts( array( 'post_type' => 'mcupr_fb', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
	echo '<p><label><strong>' . esc_html__( 'Message form (optional)', 'mcupr' ) . '</strong><br><select class="widefat" name="mcupr_g[form]"><option value="0">' . esc_html__( '— no form —', 'mcupr' ) . '</option>';
	foreach ( $forms as $fm ) {
		echo '<option value="' . (int) $fm->ID . '"' . selected( $d['form'], $fm->ID, false ) . '>' . esc_html( get_the_title( $fm ) ) . '</option>';
	}
	echo '</select></label><br><span class="description">' . esc_html__( 'A form made in MCUPR → Forms. Its answers arrive in “Form answers” like the answers of any form.', 'mcupr' ) . '</span></p>';
}

function mcupr_group_box_who( $post ) {
	$oid = mcupr_group_office( $post->ID );
	$n   = $oid ? mcupr_group_people_counts( $oid ) : array();
	echo '<p class="description">' . esc_html__( 'The news and the people of the group are not typed here: open a news item or a person of the office and choose this group in their “Work group” box. The page then shows the latest five news and everyone of the group (the head first).', 'mcupr' ) . '</p>';
	if ( $oid ) {
		/* translators: %d: number of people */
		echo '<p><strong>' . esc_html( sprintf( __( 'People in this group: %d', 'mcupr' ), isset( $n[ $post->ID ] ) ? (int) $n[ $post->ID ] : 0 ) ) . '</strong></p>';
	}
}

add_action(
	'save_post_' . MCUPR_GROUP,
	static function ( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ! mcupr_can_save_box( $post_id, 'mcupr_group_nonce', 'mcupr_group_save' ) ) {
			return;
		}
		$in  = isset( $_POST['mcupr_g'] ) && is_array( $_POST['mcupr_g'] ) ? wp_unslash( $_POST['mcupr_g'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$g   = static function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
		};
		$set = static function ( $k, $v ) use ( $post_id ) {
			if ( '' === $v || array() === $v ) {
				delete_post_meta( $post_id, '_mcupr_g_' . $k );
			} else {
				update_post_meta( $post_id, '_mcupr_g_' . $k, $v );
			}
		};
		$show = isset( $in['show'] ) && is_array( $in['show'] ) ? array_map( 'sanitize_key', array_filter( $in['show'], 'is_string' ) ) : array();
		$set( 'off', array_values( array_diff( mcupr_group_toggle_keys(), $show ) ) );
		$set( 'name_en', sanitize_text_field( $g( 'name_en' ) ) );
		$set( 'tel', sanitize_text_field( $g( 'tel' ) ) );
		$set( 'email', (string) sanitize_email( $g( 'email' ) ) );
		$set( 'place', sanitize_text_field( $g( 'place' ) ) );
		$set( 'hours', implode( "\n", mcupr_group_lines( sanitize_textarea_field( $g( 'hours' ) ) ) ) );
		$form = absint( $g( 'form' ) );
		$set( 'form', $form && 'mcupr_fb' === get_post_type( $form ) ? (string) $form : '' );
		$set( 'services', mcupr_group_clean_services( isset( $in['services'] ) ? $in['services'] : array() ) );
		$set( 'docs', mcupr_group_clean_docs( isset( $in['docs'] ) ? $in['docs'] : array() ) );
	}
);

// the office id follows the mark (the "Belongs to office" box saves it at priority 20)
add_action(
	'save_post_' . MCUPR_GROUP,
	static function ( $post_id ) {
		if ( ! wp_is_post_revision( $post_id ) ) {
			mcupr_group_sync_office( $post_id );
		}
	},
	30
);

/* ------------------------------------------------------------------ the admin list */

add_filter(
	'manage_' . MCUPR_GROUP . '_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_g_people'] = __( 'People', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_' . MCUPR_GROUP . '_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_g_people' === $col ) {
			$oid = mcupr_group_office( $id );
			$n   = $oid ? mcupr_group_people_counts( $oid ) : array(); // one count for all the groups of an office
			echo (int) ( isset( $n[ $id ] ) ? $n[ $id ] : 0 );
		}
	},
	10,
	2
);

// the list is in the order of the office home page unless a column is clicked
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() && $q->is_main_query() && MCUPR_GROUP === $q->get( 'post_type' ) && ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$q->set( 'orderby', 'menu_order title' );
			$q->set( 'order', 'ASC' );
		}
	}
);
