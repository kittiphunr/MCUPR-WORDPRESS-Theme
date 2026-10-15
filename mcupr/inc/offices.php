<?php
/**
 * Offices (หน่วยงาน / สำนักงาน): mini-sites inside the big site.
 *
 * Each office is a post type "mcupr_office" with its own colour, logo, cover, contact details and head. Content that belongs
 * to an office (news, pages, events, albums, videos, staff) carries a hidden "office" mark, so each office has its own
 * home page, news, events, people, albums, videos and contact page:
 *
 *   /office/<slug>/            the office's home          /office/<slug>/news|events|people|albums|videos|contact/
 *   /offices/                  all offices                [mcupr_offices]  on any page
 *
 * Independence:
 *   - the content of an office is kept out of the university's own lists (home news, news archive, search, feeds,
 *     calendar…) unless the office is set to “also show on the main site”;
 *   - role "Office editor": a person is given one or more offices on their profile and can then only add, edit and
 *     publish content of those offices (nothing of the other offices, no settings).
 * The mark is a non-public taxonomy (one term per office) so queries, admin lists and the database stay plain WordPress.
 * Demo: MCUPR → Import / export settings → “Offices demo” (inc/demo-offices.php).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_OFFICE_TAX  = 'mcupr_office_tax';
const MCUPR_OFFICE_ROLE = 'mcupr_office_editor';

/** The kinds of content an office can own. */
function mcupr_office_types() {
	return apply_filters( 'mcupr_office_types', array( 'post', 'page', 'mcupr_event', 'mcupr_album', 'mcupr_video', 'mcupr_person' ) );
}

function mcupr_offices_register() {
	register_post_type(
		'mcupr_office',
		array(
			'labels'          => array(
				'name'          => __( 'Offices', 'mcupr' ),
				'singular_name' => __( 'Office', 'mcupr' ),
				'menu_name'     => __( 'Offices', 'mcupr' ),
				'all_items'     => __( 'All offices', 'mcupr' ),
				'add_new_item'  => __( 'Add an office', 'mcupr' ),
				'edit_item'     => __( 'Edit office', 'mcupr' ),
				'not_found'     => __( 'No offices yet', 'mcupr' ),
				'attributes'    => __( 'Order in the list', 'mcupr' ),
			),
			'public'          => true,
			'has_archive'     => 'offices',
			'rewrite'         => array( 'slug' => 'office', 'with_front' => false ),
			'menu_icon'       => 'dashicons-building',
			'menu_position'   => 9,
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			'show_in_rest'    => true,
			'capability_type' => 'page',
			'map_meta_cap'    => true,
			'capabilities'    => array( 'create_posts' => 'manage_options' ), // only an administrator adds an office
		)
	);
	register_taxonomy(
		MCUPR_OFFICE_TAX,
		mcupr_office_types(),
		array(
			'labels'            => array( 'name' => __( 'Offices', 'mcupr' ), 'singular_name' => __( 'Office', 'mcupr' ) ),
			'public'            => false,
			'show_ui'           => false,
			'show_in_rest'      => false,
			'show_admin_column' => false,
			'hierarchical'      => false,
			'rewrite'           => false,
			'query_var'         => false,
		)
	);
	// /office/<slug>/<section>/ and /office/<slug>/<section>/page/N/
	add_rewrite_rule( '^office/([^/]+)/(news|events|people|albums|videos|contact)(?:/page/([0-9]+))?/?$', 'index.php?mcupr_office=$matches[1]&mcupr_osec=$matches[2]&mcupr_opage=$matches[3]', 'top' );
	if ( get_option( 'mcupr_rewrite_offices' ) !== MCUPR_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'mcupr_rewrite_offices', MCUPR_VERSION );
	}
}
add_action( 'init', 'mcupr_offices_register', 6 ); // before the other types, so the mark can be attached to them all

add_filter(
	'query_vars',
	static function ( $vars ) {
		$vars[] = 'mcupr_osec';
		$vars[] = 'mcupr_opage';
		$vars[] = 'mcupr_ocat';
		return $vars;
	}
);

// /office/x/news/page/2/ must not be "corrected" back to /office/x/
add_filter(
	'redirect_canonical',
	static function ( $url ) {
		return get_query_var( 'mcupr_osec' ) ? false : $url;
	}
);

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_office';
		return $types;
	}
);

/* ------------------------------------------------------------------ data */

/** The settings of an office (all text, plain). */
function mcupr_office_data( $id ) {
	$g = static function ( $k ) use ( $id ) {
		return (string) get_post_meta( $id, '_mcupr_of_' . $k, true );
	};
	$color = sanitize_hex_color( $g( 'color' ) );
	return array(
		'name_en' => $g( 'name_en' ),
		'color'   => $color ? $color : '#0f766e',
		'logo'    => (int) $g( 'logo' ),
		'head'    => $g( 'head' ),
		'head_role' => $g( 'head_role' ),
		'address' => $g( 'address' ),
		'tel'     => $g( 'tel' ),
		'email'   => $g( 'email' ),
		'hours'   => $g( 'hours' ),
		'website' => $g( 'website' ),
		'facebook' => $g( 'facebook' ),
		'line'    => $g( 'line' ),
		'youtube' => $g( 'youtube' ),
		'main'    => '1' === $g( 'main' ),
	);
}

/** Text colour (white or near-black) that reads best on a background colour. */
function mcupr_office_on_color( $hex ) {
	$hex = ltrim( (string) sanitize_hex_color( $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#ffffff';
	}
	$lin = static function ( $c ) {
		$c = hexdec( $c ) / 255;
		return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	};
	$l = 0.2126 * $lin( substr( $hex, 0, 2 ) ) + 0.7152 * $lin( substr( $hex, 2, 2 ) ) + 0.0722 * $lin( substr( $hex, 4, 2 ) );
	return $l > 0.179 ? '#111827' : '#ffffff'; // the usual threshold: light backgrounds take dark text
}

/** All published offices, in order. */
function mcupr_offices( $status = 'publish' ) {
	return get_posts( array( 'post_type' => 'mcupr_office', 'post_status' => $status, 'posts_per_page' => 100, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true, 'suppress_filters' => true ) );
}

/** The term that marks the content of an office (made when missing). */
function mcupr_office_term_id( $office_id ) {
	$office_id = (int) $office_id;
	$slug      = 'office-' . $office_id;
	$t         = get_term_by( 'slug', $slug, MCUPR_OFFICE_TAX );
	if ( ! $t ) {
		$n = wp_insert_term( $slug, MCUPR_OFFICE_TAX, array( 'slug' => $slug ) );
		if ( is_wp_error( $n ) ) {
			return 0;
		}
		return (int) $n['term_id'];
	}
	return (int) $t->term_id;
}

/** Make sure an office has its term. */
add_action(
	'save_post_mcupr_office',
	static function ( $id ) {
		if ( ! wp_is_post_revision( $id ) ) {
			mcupr_office_term_id( $id );
		}
	}
);

/** Delete an office: its term goes too (the content becomes the university's own again). */
add_action(
	'before_delete_post',
	static function ( $id ) {
		if ( 'mcupr_office' === get_post_type( $id ) ) {
			$t = get_term_by( 'slug', 'office-' . (int) $id, MCUPR_OFFICE_TAX );
			if ( $t ) {
				wp_delete_term( $t->term_id, MCUPR_OFFICE_TAX );
			}
		}
	}
);

/** The office a post belongs to (an office belongs to itself), or 0 for the university's own content. */
function mcupr_office_of( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return 0;
	}
	if ( 'mcupr_office' === get_post_type( $post_id ) ) {
		return $post_id;
	}
	if ( ! in_array( get_post_type( $post_id ), mcupr_office_types(), true ) ) {
		return 0;
	}
	$slugs = wp_get_object_terms( $post_id, MCUPR_OFFICE_TAX, array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $slugs ) ) {
		return 0;
	}
	foreach ( $slugs as $s ) {
		if ( preg_match( '/^office-(\d+)$/', $s, $m ) && 'mcupr_office' === get_post_type( (int) $m[1] ) ) {
			return (int) $m[1];
		}
	}
	return 0;
}

/** Put a post into an office (0 = the university's own). */
function mcupr_office_assign( $post_id, $office_id ) {
	$office_id = (int) $office_id;
	if ( $office_id && 'mcupr_office' === get_post_type( $office_id ) ) {
		wp_set_object_terms( $post_id, array( mcupr_office_term_id( $office_id ) ), MCUPR_OFFICE_TAX, false );
	} else {
		wp_set_object_terms( $post_id, array(), MCUPR_OFFICE_TAX, false );
	}
}

/** The office the page being shown belongs to (0 = none): an office page itself, or content that carries the mark. */
function mcupr_current_office() {
	static $cache = array();
	$qo = get_queried_object();
	if ( ! $qo || empty( $qo->ID ) || ! is_singular() ) {
		return 0;
	}
	if ( ! isset( $cache[ $qo->ID ] ) ) {
		$cache[ $qo->ID ] = mcupr_office_of( $qo->ID );
	}
	return $cache[ $qo->ID ];
}

/** The section of the office page being shown: '' (home) | news | events | people | albums | videos | contact. */
function mcupr_office_section() {
	$s = (string) get_query_var( 'mcupr_osec' );
	return in_array( $s, array( 'news', 'events', 'people', 'albums', 'videos', 'contact' ), true ) ? $s : '';
}

/** URL of an office's home or one of its sections (page 1). */
function mcupr_office_url( $office_id, $section = '', $page = 1 ) {
	$base = get_permalink( $office_id );
	if ( '' === $section ) {
		return $base;
	}
	return trailingslashit( $base ) . $section . '/' . ( $page > 1 ? 'page/' . (int) $page . '/' : '' );
}

/** WP_Query args that fetch what an office owns. */
function mcupr_office_query_args( $office_id, $type, $extra = array() ) {
	return array_merge(
		array(
			'post_type'     => $type,
			'post_status'   => 'publish',
			'no_found_rows' => true,
			'tax_query'     => array( array( 'taxonomy' => MCUPR_OFFICE_TAX, 'field' => 'term_id', 'terms' => array( mcupr_office_term_id( $office_id ) ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		),
		$extra
	);
}

/** Does an office have any published content of a type? */
function mcupr_office_has( $office_id, $type ) {
	static $cache = array();
	$k = $office_id . '|' . $type;
	if ( ! isset( $cache[ $k ] ) ) {
		$q          = new WP_Query( mcupr_office_query_args( $office_id, $type, array( 'posts_per_page' => 1, 'fields' => 'ids' ) ) );
		$cache[ $k ] = ! empty( $q->posts );
	}
	return $cache[ $k ];
}

/** Pages that belong to the office and sit at the top (they become tabs). */
function mcupr_office_pages( $office_id ) {
	return get_posts( mcupr_office_query_args( $office_id, 'page', array( 'post_parent' => 0, 'posts_per_page' => 12, 'orderby' => 'menu_order title', 'order' => 'ASC', 'suppress_filters' => false ) ) );
}

/* ------------------------------------------------------------------ the university's own lists leave office content out */

/** Term ids of the offices that are kept off the main site (remembered in one option, so a page costs no query for it). */
function mcupr_office_hidden_terms() {
	if ( isset( $GLOBALS['mcupr_office_hidden'] ) ) {
		return $GLOBALS['mcupr_office_hidden'];
	}
	$ids = get_option( 'mcupr_office_hidden', null );
	if ( ! is_array( $ids ) ) {
		$ids = array();
		foreach ( mcupr_offices( 'any' ) as $o ) {
			if ( ! mcupr_office_data( $o->ID )['main'] ) {
				$t = get_term_by( 'slug', 'office-' . $o->ID, MCUPR_OFFICE_TAX );
				if ( $t ) {
					$ids[] = (int) $t->term_id;
				}
			}
		}
		update_option( 'mcupr_office_hidden', $ids, true );
	}
	$GLOBALS['mcupr_office_hidden'] = $ids;
	return $ids;
}

/** Forget it: an office was saved or deleted, or its "also show on the main site" switch changed. */
function mcupr_office_hidden_reset() {
	delete_option( 'mcupr_office_hidden' );
	unset( $GLOBALS['mcupr_office_hidden'] );
}
add_action( 'save_post_mcupr_office', 'mcupr_office_hidden_reset', 99 );
add_action( 'deleted_post', 'mcupr_office_hidden_reset' );
add_action( 'trashed_post', 'mcupr_office_hidden_reset' );
add_action( 'untrashed_post', 'mcupr_office_hidden_reset' );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function ( $mid, $post_id, $key ) {
			if ( '_mcupr_of_main' === $key ) {
				mcupr_office_hidden_reset();
			}
		},
		10,
		3
	);
}

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $q->get( 'mcupr_office_all' ) ) {
			return;
		}
		// direct fetches (a single post by id / name / path, a post__in list) and sitemaps are left alone
		if ( $q->is_singular() || $q->get( 'p' ) || $q->get( 'page_id' ) || $q->get( 'name' ) || $q->get( 'pagename' ) || $q->get( 'post__in' ) || $q->get( 'attachment_id' ) ) {
			return;
		}
		$main = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
		if ( $main && $main->get( 'sitemap' ) ) {
			return;
		}
		$pt    = $q->get( 'post_type' );
		$pt    = is_array( $pt ) ? $pt : ( '' === $pt || null === $pt ? array( 'post' ) : array( $pt ) );
		$mine  = array_intersect( $pt, array_merge( mcupr_office_types(), array( 'any' ) ) );
		if ( ! $mine ) {
			return;
		}
		$tax = $q->get( 'tax_query' );
		if ( is_array( $tax ) ) {
			foreach ( $tax as $t ) {
				if ( is_array( $t ) && isset( $t['taxonomy'] ) && MCUPR_OFFICE_TAX === $t['taxonomy'] ) {
					return; // an office asking for its own content
				}
			}
		}
		$hide = mcupr_office_hidden_terms();
		if ( ! $hide ) {
			return;
		}
		$tax   = is_array( $tax ) ? $tax : array();
		$tax[] = array( 'taxonomy' => MCUPR_OFFICE_TAX, 'field' => 'term_id', 'terms' => $hide, 'operator' => 'NOT IN' );
		$q->set( 'tax_query', $tax );
	}
);

/* ------------------------------------------------------------------ the editor screens */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'mcupr_office_settings', __( 'Office details', 'mcupr' ), 'mcupr_office_settings_box', 'mcupr_office', 'normal', 'high' );
		foreach ( mcupr_office_types() as $t ) {
			add_meta_box( 'mcupr_office_member', __( 'Belongs to office', 'mcupr' ), 'mcupr_office_member_box', $t, 'side', 'default' );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$s = get_current_screen();
		if ( $s && 'mcupr_office' === $s->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'mcupr-office-admin', get_theme_file_uri( 'assets/js/office-admin.js' ), array( 'jquery' ), MCUPR_VERSION, true );
		}
	}
);

function mcupr_office_settings_box( $post ) {
	wp_nonce_field( 'mcupr_office_save', 'mcupr_office_nonce' );
	$d = mcupr_office_data( $post->ID );
	$f = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_of[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	echo '<p class="description">' . esc_html__( 'The title is the office\'s name. The text box is its “About” text; the excerpt is a one-line description; the featured image is its cover picture; “Order in the list” sets the order. Content (news, pages, events, albums, videos, staff) is added to the office from the “Belongs to office” box on each of them.', 'mcupr' ) . '</p>';
	$f( __( 'Name in English (optional)', 'mcupr' ), 'name_en', $d['name_en'], 'text', 'Phrae Campus Office' );
	echo '<p><label><strong>' . esc_html__( 'Colour of the office', 'mcupr' ) . '</strong><br><input type="color" name="mcupr_of[color]" value="' . esc_attr( $d['color'] ) . '"></label> <span class="description">' . esc_html__( 'Used for the office header and tabs. The text on it turns white or black by itself so it stays readable.', 'mcupr' ) . '</span></p>';
	$logo = $d['logo'] ? wp_get_attachment_image( $d['logo'], array( 64, 64 ), false, array( 'style' => 'height:64px;width:auto;display:block;margin-bottom:6px' ) ) : '';
	echo '<div class="mcupr-of-logo"><strong>' . esc_html__( 'Logo of the office (optional)', 'mcupr' ) . '</strong><br><span class="mcupr-of-logo-img">' . $logo . '</span><input type="hidden" name="mcupr_of[logo]" value="' . (int) $d['logo'] . '"><button type="button" class="button mcupr-of-pick">' . esc_html__( 'Choose a picture', 'mcupr' ) . '</button> <button type="button" class="button-link mcupr-of-clear">' . esc_html__( 'Remove', 'mcupr' ) . '</button></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$f( __( 'Head of the office (name)', 'mcupr' ), 'head', $d['head'] );
	$f( __( 'Head of the office (position)', 'mcupr' ), 'head_role', $d['head_role'], 'text', __( 'e.g. Director', 'mcupr' ) );
	echo '<p><label><strong>' . esc_html__( 'Address', 'mcupr' ) . '</strong><br><textarea class="widefat" rows="2" name="mcupr_of[address]">' . esc_textarea( $d['address'] ) . '</textarea></label></p>';
	$f( __( 'Telephone', 'mcupr' ), 'tel', $d['tel'] );
	$f( __( 'Email', 'mcupr' ), 'email', $d['email'], 'email' );
	$f( __( 'Opening hours', 'mcupr' ), 'hours', $d['hours'], 'text', __( 'e.g. Mon–Fri 08:30–16:30', 'mcupr' ) );
	$f( __( 'Website (if it also has its own)', 'mcupr' ), 'website', $d['website'], 'url', 'https://' );
	$f( 'Facebook', 'facebook', $d['facebook'], 'url', 'https://facebook.com/…' );
	$f( 'LINE', 'line', $d['line'], 'url', 'https://line.me/…' );
	$f( 'YouTube', 'youtube', $d['youtube'], 'url', 'https://youtube.com/…' );
	echo '<p><label><input type="checkbox" name="mcupr_of[main]" value="1"' . checked( $d['main'], true, false ) . '> <strong>' . esc_html__( 'Also show this office\'s news, events and other content on the main site', 'mcupr' ) . '</strong></label><br><span class="description">' . esc_html__( 'Off (recommended): the content of this office stays on the office\'s own pages and is kept out of the university\'s home page, news list, calendar and search.', 'mcupr' ) . '</span></p>';
}

add_action(
	'save_post_mcupr_office',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_office_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_office_nonce'] ) ), 'mcupr_office_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in = isset( $_POST['mcupr_of'] ) && is_array( $_POST['mcupr_of'] ) ? wp_unslash( $_POST['mcupr_of'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$g  = static function ( $k ) use ( $in ) {
			return isset( $in[ $k ] ) && is_string( $in[ $k ] ) ? $in[ $k ] : '';
		};
		$set = static function ( $k, $v ) use ( $post_id ) {
			'' === $v ? delete_post_meta( $post_id, '_mcupr_of_' . $k ) : update_post_meta( $post_id, '_mcupr_of_' . $k, $v );
		};
		$set( 'name_en', sanitize_text_field( $g( 'name_en' ) ) );
		$set( 'color', (string) sanitize_hex_color( $g( 'color' ) ) );
		$logo = absint( $g( 'logo' ) );
		$set( 'logo', $logo && wp_attachment_is_image( $logo ) ? (string) $logo : '' );
		foreach ( array( 'head', 'head_role', 'tel', 'hours' ) as $k ) {
			$set( $k, sanitize_text_field( $g( $k ) ) );
		}
		$set( 'address', sanitize_textarea_field( $g( 'address' ) ) );
		$set( 'email', sanitize_email( $g( 'email' ) ) );
		foreach ( array( 'website', 'facebook', 'line', 'youtube' ) as $k ) {
			$set( $k, esc_url_raw( $g( $k ), array( 'http', 'https' ) ) );
		}
		$set( 'main', ! empty( $in['main'] ) ? '1' : '' );
	}
);

/** The offices this user may work in: null = all (administrators), else array of office ids. */
function mcupr_user_offices( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$u       = get_userdata( $user_id );
	if ( ! $u ) {
		return array();
	}
	$mine = get_user_meta( $user_id, 'mcupr_offices', true );
	$mine = is_array( $mine ) ? array_values( array_filter( array_map( 'absint', $mine ) ) ) : array();
	if ( in_array( MCUPR_OFFICE_ROLE, (array) $u->roles, true ) || $mine ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return null;
		}
		return $mine;
	}
	return null;
}

function mcupr_office_member_box( $post ) {
	wp_nonce_field( 'mcupr_office_member', 'mcupr_office_member_nonce' );
	$cur   = mcupr_office_of( $post->ID );
	$mine  = mcupr_user_offices();
	$items = array();
	foreach ( mcupr_offices( 'any' ) as $o ) {
		if ( null === $mine || in_array( $o->ID, $mine, true ) ) {
			$items[] = $o;
		}
	}
	echo '<p><label class="screen-reader-text" for="mcupr-office-sel">' . esc_html__( 'Belongs to office', 'mcupr' ) . '</label><select id="mcupr-office-sel" name="mcupr_office_sel" class="widefat">';
	if ( null === $mine ) {
		echo '<option value="0">' . esc_html__( 'The university (main site)', 'mcupr' ) . '</option>';
	}
	foreach ( $items as $o ) {
		echo '<option value="' . (int) $o->ID . '"' . selected( $cur, $o->ID, false ) . '>' . esc_html( get_the_title( $o ) ) . '</option>';
	}
	echo '</select></p><p class="description">' . esc_html__( 'Content of an office is shown on that office\'s own pages and kept out of the main site\'s lists (unless the office is set to show on the main site).', 'mcupr' ) . '</p>';
}

/** Save the office of a post. People who work in offices can only choose their own, and everything they make gets one. */
add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! in_array( $post->post_type, mcupr_office_types(), true ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		$mine = mcupr_user_offices();
		if ( null !== $mine && ! $mine ) {
			return; // nothing to give
		}
		$posted = isset( $_POST['mcupr_office_member_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_office_member_nonce'] ) ), 'mcupr_office_member' ) && isset( $_POST['mcupr_office_sel'] );
		$want   = $posted ? absint( wp_unslash( $_POST['mcupr_office_sel'] ) ) : mcupr_office_of( $post_id );
		if ( null !== $mine ) {
			if ( ! in_array( $want, $mine, true ) ) {
				$want = $mine[0]; // theirs only
			}
		} elseif ( ! $posted ) {
			return; // an administrator saving without the box (quick edit, import): leave it as it is
		}
		if ( mcupr_office_of( $post_id ) !== $want ) {
			mcupr_office_assign( $post_id, $want );
		}
	},
	20,
	2
);

/** A column and a filter in the lists of the content that can belong to an office. */
add_action(
	'admin_init',
	static function () {
		foreach ( mcupr_office_types() as $t ) {
			add_filter(
				"manage_{$t}_posts_columns",
				static function ( $cols ) {
					$cols['mcupr_office'] = __( 'Office', 'mcupr' );
					return $cols;
				}
			);
			add_action(
				"manage_{$t}_posts_custom_column",
				static function ( $col, $id ) {
					if ( 'mcupr_office' === $col ) {
						$o = mcupr_office_of( $id );
						echo $o ? esc_html( get_the_title( $o ) ) : '—';
					}
				},
				10,
				2
			);
		}
	}
);

add_action(
	'restrict_manage_posts',
	static function ( $post_type ) {
		if ( ! in_array( $post_type, mcupr_office_types(), true ) || null !== mcupr_user_offices() ) {
			return;
		}
		$offices = mcupr_offices( 'any' );
		if ( ! $offices ) {
			return;
		}
		$cur = isset( $_GET['mcupr_of_filter'] ) ? (int) $_GET['mcupr_of_filter'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<label class="screen-reader-text" for="mcupr-of-filter">' . esc_html__( 'Filter by office', 'mcupr' ) . '</label><select id="mcupr-of-filter" name="mcupr_of_filter"><option value="-1">' . esc_html__( 'All offices', 'mcupr' ) . '</option><option value="0"' . selected( $cur, 0, false ) . '>' . esc_html__( 'The university (main site)', 'mcupr' ) . '</option>';
		foreach ( $offices as $o ) {
			echo '<option value="' . (int) $o->ID . '"' . selected( $cur, $o->ID, false ) . '>' . esc_html( get_the_title( $o ) ) . '</option>';
		}
		echo '</select>';
	}
);

/** The admin lists: the filter above for administrators; people who work in offices only see their offices' content. */
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$type = $q->get( 'post_type' );
		$type = '' === $type ? 'post' : $type;
		if ( ! is_string( $type ) || ! in_array( $type, mcupr_office_types(), true ) ) {
			return;
		}
		$mine = mcupr_user_offices();
		$tax  = array();
		if ( null !== $mine ) {
			$terms = array_map( 'mcupr_office_term_id', $mine );
			if ( ! $terms ) {
				$q->set( 'post__in', array( 0 ) );
				return;
			}
			$tax[] = array( 'taxonomy' => MCUPR_OFFICE_TAX, 'field' => 'term_id', 'terms' => $terms );
		} elseif ( isset( $_GET['mcupr_of_filter'] ) && (int) $_GET['mcupr_of_filter'] >= 0 ) { // phpcs:ignore WordPress.Security.NonceVerification
			$of = (int) $_GET['mcupr_of_filter']; // phpcs:ignore WordPress.Security.NonceVerification
			$tax[] = $of
				? array( 'taxonomy' => MCUPR_OFFICE_TAX, 'field' => 'term_id', 'terms' => array( mcupr_office_term_id( $of ) ) )
				: array( 'taxonomy' => MCUPR_OFFICE_TAX, 'operator' => 'NOT EXISTS' );
		}
		if ( $tax ) {
			$q->set( 'mcupr_office_all', 1 );
			$q->set( 'tax_query', $tax );
		}
	}
);

/* ------------------------------------------------------------------ the role "Office editor" and what it may touch */

function mcupr_office_role_caps() {
	return array(
		'read' => true, 'upload_files' => true,
		'edit_posts' => true, 'edit_published_posts' => true, 'publish_posts' => true, 'delete_posts' => true, 'delete_published_posts' => true, 'edit_others_posts' => true, 'delete_others_posts' => true,
		'edit_pages' => true, 'edit_published_pages' => true, 'publish_pages' => true, 'delete_pages' => true, 'delete_published_pages' => true, 'edit_others_pages' => true, 'delete_others_pages' => true,
	);
}

add_action(
	'init',
	static function () {
		$v = md5( wp_json_encode( mcupr_office_role_caps() ) ); // the role is made again whenever what it may do changes
		if ( get_option( 'mcupr_office_role_v' ) !== $v || ! get_role( MCUPR_OFFICE_ROLE ) ) {
			remove_role( MCUPR_OFFICE_ROLE );
			add_role( MCUPR_OFFICE_ROLE, __( 'Office editor', 'mcupr' ), mcupr_office_role_caps() );
			update_option( 'mcupr_office_role_v', $v );
		}
	},
	7
);

/**
 * Who may edit / delete / publish what: someone who works in offices only touches content of their offices (their
 * own uploads in the Media Library). The office page itself can be edited by its own people but never deleted by them.
 */
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'publish_post', 'edit_page', 'delete_page' ), true ) || empty( $args[0] ) || in_array( 'do_not_allow', $caps, true ) ) {
			return $caps;
		}
		$mine = mcupr_user_offices( $user_id );
		if ( null === $mine ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post ) {
			return $caps;
		}
		if ( 'attachment' === $post->post_type ) {
			return (int) $post->post_author === (int) $user_id ? $caps : array( 'do_not_allow' );
		}
		if ( 'mcupr_office' === $post->post_type ) {
			return in_array( $cap, array( 'delete_post', 'delete_page' ), true ) || ! in_array( $post->ID, $mine, true ) ? array( 'do_not_allow' ) : $caps;
		}
		if ( ! in_array( $post->post_type, mcupr_office_types(), true ) ) {
			return $caps;
		}
		$of = mcupr_office_of( $post->ID );
		if ( $of ) {
			return in_array( $of, $mine, true ) ? $caps : array( 'do_not_allow' );
		}
		// not in any office yet: only a brand-new draft of theirs (it is given an office when saved)
		return (int) $post->post_author === (int) $user_id && 'publish' !== $post->post_status ? $caps : array( 'do_not_allow' );
	},
	10,
	4
);

/** The profile: which offices a person works in (administrators set it). */
function mcupr_office_profile_fields( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mine = get_user_meta( $user->ID, 'mcupr_offices', true );
	$mine = is_array( $mine ) ? array_map( 'absint', $mine ) : array();
	wp_nonce_field( 'mcupr_user_offices', 'mcupr_user_offices_nonce' );
	echo '<h2>' . esc_html__( 'Offices', 'mcupr' ) . '</h2><table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Works in these offices', 'mcupr' ) . '</th><td>';
	$offices = mcupr_offices( 'any' );
	if ( ! $offices ) {
		echo '<p class="description">' . esc_html__( 'No offices yet.', 'mcupr' ) . '</p>';
	}
	foreach ( $offices as $o ) {
		echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="mcupr_user_offices[]" value="' . (int) $o->ID . '"' . checked( in_array( $o->ID, $mine, true ), true, false ) . '> ' . esc_html( get_the_title( $o ) ) . '</label>';
	}
	echo '<p class="description">' . esc_html__( 'A person with offices can only add, edit and publish content of those offices (use the role “Office editor”). Leave all unticked for no limit. Administrators are never limited.', 'mcupr' ) . '</p></td></tr></table>';
}
add_action( 'show_user_profile', 'mcupr_office_profile_fields' );
add_action( 'edit_user_profile', 'mcupr_office_profile_fields' );

function mcupr_office_profile_save( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mcupr_user_offices_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_user_offices_nonce'] ) ), 'mcupr_user_offices' ) ) {
		return;
	}
	$ids = isset( $_POST['mcupr_user_offices'] ) && is_array( $_POST['mcupr_user_offices'] ) ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['mcupr_user_offices'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$ids ? update_user_meta( $user_id, 'mcupr_offices', $ids ) : delete_user_meta( $user_id, 'mcupr_offices' );
}
add_action( 'personal_options_update', 'mcupr_office_profile_save' );
add_action( 'edit_user_profile_update', 'mcupr_office_profile_save' );
