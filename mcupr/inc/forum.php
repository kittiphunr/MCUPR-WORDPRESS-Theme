<?php
/**
 * Discussion board (กระดานสนทนา), no plugin.
 *
 *  - Boards (taxonomy "mcupr_board") hold topics (post type "mcupr_topic"); replies are WordPress comments on the topic.
 *  - [mcupr_forum] draws the board index, the topic list of a board (?fb=slug), search (?fq=) and the "new topic" form.
 *    A topic has its own page (/forum/topic/slug/) with the replies and the reply form.
 *  - Open to everyone or to logged-in users only. New posts wait for approval ("moderation": all / guests only / none);
 *    editors and administrators publish at once. Pinned and locked topics, auto-lock after N days, report buttons
 *    (three reports hide a post until a moderator looks), blocked words and a link limit that hold a post for review.
 *  - Protection like the contact form: nonce, hidden trap field, signed time token, per-visitor rate limit, consent (PDPA).
 *    The visitor's e-mail is kept for the moderators only; no IP address or browser data is stored.
 *  - Control: Forum → Forum settings (rules, blocked words, notification address, moderation queue).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ================================================================== settings */

function mcupr_forum_defaults() {
	return array(
		'enabled'     => 1,
		'post_who'    => 'all',     // all | users
		'reply_who'   => 'all',     // all | users
		'moderate'    => 'all',     // all | guests | none
		'blocked'     => '',
		'max_links'   => 2,
		'per_page'    => 15,
		'notify'      => '',
		'reports'     => 1,
		'close_days'  => 0,
	);
}

function mcupr_forum_opts() {
	$saved = get_option( 'mcupr_forum', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$o     = array_merge( mcupr_forum_defaults(), array_intersect_key( $saved, mcupr_forum_defaults() ) );
	$o['post_who']  = in_array( $o['post_who'], array( 'all', 'users' ), true ) ? $o['post_who'] : 'all';
	$o['reply_who'] = in_array( $o['reply_who'], array( 'all', 'users' ), true ) ? $o['reply_who'] : 'all';
	$o['moderate']  = in_array( $o['moderate'], array( 'all', 'guests', 'none' ), true ) ? $o['moderate'] : 'all';
	$o['max_links'] = max( 0, min( 10, (int) $o['max_links'] ) );
	$o['per_page']  = max( 5, min( 50, (int) $o['per_page'] ) );
	$o['close_days'] = max( 0, min( 3650, (int) $o['close_days'] ) );
	return $o;
}

/* ================================================================== post type, boards */

function mcupr_forum_register() {
	register_post_type(
		'mcupr_topic',
		array(
			'labels'             => array(
				'name'          => __( 'Forum topics', 'mcupr' ),
				'singular_name' => __( 'Topic', 'mcupr' ),
				'menu_name'     => __( 'Forum', 'mcupr' ),
				'all_items'     => __( 'All topics', 'mcupr' ),
				'add_new_item'  => __( 'Add a topic', 'mcupr' ),
				'edit_item'     => __( 'Edit topic', 'mcupr' ),
				'not_found'     => __( 'No topics yet', 'mcupr' ),
			),
			'public'             => true,
			'exclude_from_search' => true,
			'has_archive'        => false,
			'rewrite'            => array( 'slug' => 'forum/topic', 'with_front' => false ),
			'menu_icon'          => 'dashicons-format-chat',
			'menu_position'      => 11,
			'supports'           => array( 'title', 'editor' ),
			'show_in_rest'       => false,
		)
	);
	register_taxonomy(
		'mcupr_board',
		'mcupr_topic',
		array(
			'labels'            => array(
				'name'          => __( 'Boards', 'mcupr' ),
				'singular_name' => __( 'Board', 'mcupr' ),
				'add_new_item'  => __( 'Add a board', 'mcupr' ),
				'menu_name'     => __( 'Boards', 'mcupr' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'mcupr_forum_register' );

/** The first run creates one board so the forum works at once. */
add_action(
	'init',
	static function () {
		if ( ! get_option( 'mcupr_forum_boards_seeded' ) && taxonomy_exists( 'mcupr_board' ) ) {
			if ( ! get_terms( array( 'taxonomy' => 'mcupr_board', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids' ) ) ) {
				$th = 'th' === mcupr_default_lang();
				wp_insert_term( $th ? 'ทั่วไป' : 'General', 'mcupr_board', array( 'slug' => 'general', 'description' => $th ? 'พูดคุยเรื่องทั่วไปเกี่ยวกับมหาวิทยาลัย' : 'Talk about anything related to the university.' ) );
			}
			update_option( 'mcupr_forum_boards_seeded', 1 );
		}
		mcupr_rewrite_wanted( 'mcupr_rewrite_forum', MCUPR_VERSION ); // one flush for all modules, at the end of init (inc/compat.php)
	},
	99
);

/** When the forum is switched off its topic pages are 404 (nothing is deleted). */
add_action(
	'template_redirect',
	static function () {
		if ( is_singular( 'mcupr_topic' ) && ! mcupr_forum_opts()['enabled'] && ! current_user_can( 'edit_pages' ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

/** The page that carries [mcupr_forum] (found once, then remembered). */
function mcupr_forum_page_url() {
	$id = (int) get_option( 'mcupr_forum_page', 0 );
	if ( ! $id || 'publish' !== get_post_status( $id ) || false === strpos( (string) get_post_field( 'post_content', $id ), '[mcupr_forum' ) ) {
		$id    = 0;
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => '[mcupr_forum', 'posts_per_page' => 5, 'no_found_rows' => true ) );
		foreach ( $pages as $p ) {
			if ( false !== strpos( $p->post_content, '[mcupr_forum' ) ) {
				$id = $p->ID;
				break;
			}
		}
		update_option( 'mcupr_forum_page', $id, false );
	}
	if ( $id ) {
		return get_permalink( $id );
	}
	return home_url( mcupr_plain_mode() ? '/' : '/forum/' ); // Plain permalinks have no /forum/ address: the home page rather than a 404
}

/* ================================================================== a topic's facts */

function mcupr_topic_name( $id ) {
	$n = (string) get_post_meta( $id, '_mcupr_topic_name', true );
	return '' !== $n ? $n : (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) );
}

/** Approved replies: WordPress keeps this number in comment_count, so no extra query per row. */
function mcupr_topic_replies( $id ) {
	$p = get_post( $id );
	return $p ? (int) $p->comment_count : 0;
}

/**
 * The newest topic of each board, as board id => topic id (0 = none).
 * Worked out once and kept until a topic, its board or its last-activity time changes.
 */
function mcupr_forum_board_last( $term_id ) {
	$map = get_transient( 'mcupr_forum_board_last' );
	if ( ! is_array( $map ) ) {
		$map = array();
	}
	$term_id = (int) $term_id;
	if ( ! array_key_exists( $term_id, $map ) ) {
		$last = get_posts( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids', 'meta_key' => '_mcupr_topic_last', 'orderby' => 'meta_value_num', 'order' => 'DESC', 'tax_query' => array( array( 'taxonomy' => 'mcupr_board', 'terms' => $term_id ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$map[ $term_id ] = $last ? (int) $last[0] : 0;
		set_transient( 'mcupr_forum_board_last', $map, DAY_IN_SECONDS );
	}
	return $map[ $term_id ];
}

function mcupr_forum_board_last_clear() {
	delete_transient( 'mcupr_forum_board_last' );
}
add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( $post && 'mcupr_topic' === $post->post_type ) {
			mcupr_forum_board_last_clear();
		}
	},
	10,
	3
);
add_action(
	'deleted_post',
	static function ( $id, $post = null ) {
		if ( 'mcupr_topic' === ( $post instanceof WP_Post ? $post->post_type : get_post_type( $id ) ) ) {
			mcupr_forum_board_last_clear();
		}
	},
	10,
	2
);
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'mcupr_board' === $taxonomy ) {
			mcupr_forum_board_last_clear();
		}
	},
	10,
	4
);
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function ( $mid, $object_id, $key ) {
			if ( '_mcupr_topic_last' === $key ) {
				mcupr_forum_board_last_clear();
			}
		},
		10,
		3
	);
}
foreach ( array( 'created_mcupr_board', 'edited_mcupr_board', 'delete_mcupr_board' ) as $mcupr_h ) {
	add_action( $mcupr_h, 'mcupr_forum_board_last_clear' );
}

function mcupr_topic_last( $id ) {
	$t = (int) get_post_meta( $id, '_mcupr_topic_last', true );
	return $t ? $t : (int) get_post_time( 'U', true, $id );
}

/** Locked by a moderator, or silent for longer than the "close after" days. */
function mcupr_topic_locked( $id ) {
	if ( get_post_meta( $id, '_mcupr_topic_locked', true ) ) {
		return true;
	}
	$days = mcupr_forum_opts()['close_days'];
	return $days > 0 && ( time() - mcupr_topic_last( $id ) ) > $days * DAY_IN_SECONDS;
}

/** Remember the time of the last activity (used for the order of the list). */
function mcupr_topic_touch( $id, $time = 0 ) {
	update_post_meta( $id, '_mcupr_topic_last', $time ? (int) $time : time() );
}

add_action(
	'save_post_mcupr_topic',
	static function ( $post_id ) {
		if ( ! get_post_meta( $post_id, '_mcupr_topic_last', true ) ) {
			mcupr_topic_touch( $post_id );
		}
	}
);
add_action(
	'transition_comment_status',
	static function ( $new, $old, $comment ) {
		if ( 'approved' === $new && 'mcupr_topic' === get_post_type( $comment->comment_post_ID ) ) {
			mcupr_topic_touch( (int) $comment->comment_post_ID );
		}
	},
	10,
	3
);

/* ================================================================== text */

/** Plain text → safe HTML: line breaks, links (nofollow ugc), nothing else. */
function mcupr_forum_text( $s ) {
	$h = make_clickable( esc_html( $s ) );
	$h = wp_kses( $h, array( 'a' => array( 'href' => true, 'rel' => true ) ) );
	$h = str_replace( 'rel="nofollow"', 'rel="nofollow ugc noopener"', $h ); // make_clickable already adds rel="nofollow"
	return nl2br( $h );
}

function mcupr_forum_initial( $name ) {
	return mb_strtoupper( mb_substr( preg_replace( '/^[^\p{L}\p{N}]+/u', '', trim( $name ) ), 0, 1 ) );
}

/* ================================================================== posting (topic or reply) */

function mcupr_forum_visitor_key( $what ) {
	$ip = mcupr_client_net();
	return 'mcupr_fm_' . $what . '_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ), 0, 16 ); // a hash, kept for an hour
}

/** Does this text need a moderator even if the board is open? (blocked words, too many links) */
function mcupr_forum_suspicious( $text ) {
	$o = mcupr_forum_opts();
	if ( preg_match_all( '#https?://|www\.#i', $text ) > $o['max_links'] ) {
		return true;
	}
	foreach ( preg_split( '/\R/', (string) $o['blocked'] ) as $w ) {
		$w = trim( $w );
		if ( '' !== $w && false !== mb_stripos( $text, $w ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Validate and store a topic ($kind "topic") or a reply ($kind "reply").
 * Returns array( 'ok', 'errors' => array( field => key ), 'status' => publish|pending, 'id', 'url' ).
 */
function mcupr_forum_submit( $kind, $data, $now = 0 ) {
	$now  = $now ? $now : time();
	$o    = mcupr_forum_opts();
	$fail = static function ( $errors ) {
		return array( 'ok' => false, 'errors' => $errors, 'status' => '', 'id' => 0, 'url' => '' );
	};
	if ( ! $o['enabled'] ) {
		return $fail( array( 'closed' => 'fm_err_closed' ) );
	}
	$g = static function ( $k ) use ( $data ) {
		return isset( $data[ $k ] ) && is_scalar( $data[ $k ] ) ? (string) $data[ $k ] : '';
	};
	$tok = explode( '.', $g( 'tk' ) );
	$ts  = isset( $tok[0] ) ? (int) $tok[0] : 0;
	if ( '' !== $g( 'website' ) || ! hash_equals( mcupr_form_token( $ts ), $g( 'tk' ) ) ) {
		return $fail( array( 'spam' => 'form_err_spam' ) );
	}
	$member = is_user_logged_in();
	$staff  = current_user_can( 'edit_pages' );
	$who    = 'topic' === $kind ? $o['post_who'] : $o['reply_who'];
	if ( 'users' === $who && ! $member ) {
		return $fail( array( 'login' => 'fm_err_login' ) );
	}
	$name  = sanitize_text_field( $g( 'name' ) );
	$email = sanitize_email( $g( 'email' ) );
	$msg   = trim( sanitize_textarea_field( $g( 'message' ) ) );
	if ( $member ) {
		$u     = wp_get_current_user();
		$name  = '' !== $name ? $name : $u->display_name;
		$email = is_email( $email ) ? $email : $u->user_email;
	}
	$name   = mb_substr( $name, 0, 60 );
	$errors = array();
	if ( '' === $name ) {
		$errors['name'] = 'form_err_name';
	}
	if ( ! is_email( $email ) ) {
		$errors['email'] = 'form_err_email';
	}
	if ( mb_strlen( $msg ) < 10 ) {
		$errors['message'] = 'form_err_msg';
	}
	if ( mb_strlen( $msg ) > 5000 ) {
		$errors['message'] = 'fm_err_long';
	}
	if ( ! $staff && '1' !== $g( 'consent' ) ) {
		$errors['consent'] = 'form_err_consent';
	}
	$title = '';
	$board = 0;
	$topic = 0;
	if ( 'topic' === $kind ) {
		$title = mb_substr( sanitize_text_field( $g( 'title' ) ), 0, 150 );
		if ( mb_strlen( $title ) < 5 ) {
			$errors['title'] = 'fm_err_title';
		}
		$board = (int) $g( 'board' );
		if ( ! $board || ! term_exists( $board, 'mcupr_board' ) ) {
			$errors['board'] = 'fm_err_board';
		}
	} else {
		$topic = (int) $g( 'topic' );
		if ( ! $topic || 'mcupr_topic' !== get_post_type( $topic ) || 'publish' !== get_post_status( $topic ) ) {
			return $fail( array( 'topic' => 'fm_err_topic' ) );
		}
		if ( mcupr_topic_locked( $topic ) && ! $staff ) {
			return $fail( array( 'locked' => 'fm_err_locked' ) );
		}
	}
	if ( $errors ) {
		return $fail( $errors );
	}
	if ( $now - $ts < 3 || $now - $ts > 2 * HOUR_IN_SECONDS ) {
		return $fail( array( 'spam' => 'form_err_spam' ) );
	}
	$key = mcupr_forum_visitor_key( 'post' );
	if ( ! $staff && (int) get_transient( $key ) >= 6 ) {
		return $fail( array( 'limit' => 'form_err_limit' ) );
	}
	// the same text twice in a row from the same hand is a double click or a bot
	$sig = 'mcupr_fm_sig_' . md5( $kind . '|' . $name . '|' . $title . '|' . $msg . '|' . $topic );
	if ( get_transient( $sig ) ) {
		return $fail( array( 'dup' => 'fm_err_dup' ) );
	}
	// ---- needs a moderator?
	$review = 'none' !== $o['moderate'] && ( 'all' === $o['moderate'] || ! $member );
	if ( $staff ) {
		$review = false;
	} elseif ( mcupr_forum_suspicious( $title . "\n" . $msg ) ) {
		$review = true;
	}
	$status = $review ? 'pending' : 'publish';
	$id     = 0;
	if ( 'topic' === $kind ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'mcupr_topic',
				'post_status'  => $status,
				'post_title'   => $title,
				'post_content' => $msg,
				'post_author'  => $member ? get_current_user_id() : 0,
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return $fail( array( 'spam' => 'form_err' ) );
		}
		wp_set_object_terms( $id, array( $board ), 'mcupr_board' );
		update_post_meta( $id, '_mcupr_topic_name', $name );
		update_post_meta( $id, '_mcupr_topic_email', $email );
		mcupr_topic_touch( $id, $now );
		$url = get_permalink( $id );
	} else {
		$id = wp_insert_comment(
			wp_slash(
				array(
					'comment_post_ID'      => $topic,
					'comment_author'       => $name,
					'comment_author_email' => $email,
					'comment_content'      => $msg,
					'comment_type'         => 'comment',
					'comment_approved'     => $review ? 0 : 1,
					'comment_author_IP'    => '',
					'comment_agent'        => '',
					'user_id'              => $member ? get_current_user_id() : 0,
					'comment_date'         => current_time( 'mysql' ),
					'comment_date_gmt'     => current_time( 'mysql', true ),
				)
			)
		);
		if ( ! $id ) {
			return $fail( array( 'spam' => 'form_err' ) );
		}
		if ( ! $review ) {
			mcupr_topic_touch( $topic, $now );
		}
		$url = get_permalink( $topic );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	set_transient( $sig, 1, 10 * MINUTE_IN_SECONDS );
	if ( $review ) {
		mcupr_forum_notify( $kind, $id, $title, $name, $msg, $topic );
	}
	return array( 'ok' => true, 'errors' => array(), 'status' => $status, 'id' => (int) $id, 'url' => $url );
}

function mcupr_forum_notify( $kind, $id, $title, $name, $msg, $topic = 0 ) {
	$to = sanitize_email( (string) mcupr_forum_opts()['notify'] );
	$to = is_email( $to ) ? $to : get_option( 'admin_email' );
	$edit = 'topic' === $kind ? admin_url( 'post.php?post=' . $id . '&action=edit' ) : admin_url( 'comment.php?action=editcomment&c=' . $id );
	$subject = '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . ( 'topic' === $kind ? __( 'Forum: a topic is waiting for approval', 'mcupr' ) : __( 'Forum: a reply is waiting for approval', 'mcupr' ) );
	$body = ( 'topic' === $kind ? $title : get_the_title( $topic ) ) . "\n" . $name . "\n\n" . $msg . "\n\n" . $edit . "\n" . admin_url( 'admin.php?page=mcupr-forum' );
	wp_mail( $to, $subject, $body );
}

/* ---- the form handlers (admin-post: they work without JavaScript) */

function mcupr_forum_back( $url, $args ) {
	wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'fm', 'fme', 'fmref' ), $url ) ) );
	exit;
}

function mcupr_forum_handler( $kind ) {
	$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	$back = isset( $data['back'] ) ? wp_validate_redirect( esc_url_raw( $data['back'] ), mcupr_forum_page_url() ) : mcupr_forum_page_url();
	if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $data['_wpnonce'] ), 'mcupr_forum' ) ) {
		mcupr_forum_back( $back . '#fm-' . $kind, array( 'fm' => 'err', 'fme' => 'spam' ) );
	}
	$r = mcupr_forum_submit( $kind, $data );
	if ( ! $r['ok'] ) {
		mcupr_forum_back( $back . '#fm-' . $kind, array( 'fm' => 'err', 'fme' => implode( ',', array_keys( $r['errors'] ) ) ) );
	}
	if ( 'publish' === $r['status'] ) {
		wp_safe_redirect( $r['url'] . ( 'reply' === $kind ? '#reply-' . $r['id'] : '' ) );
		exit;
	}
	mcupr_forum_back( $back, array( 'fm' => 'pending' ) );
}
add_action( 'admin_post_nopriv_mcupr_forum_topic', static function () { mcupr_forum_handler( 'topic' ); } );
add_action( 'admin_post_mcupr_forum_topic', static function () { mcupr_forum_handler( 'topic' ); } );
add_action( 'admin_post_nopriv_mcupr_forum_reply', static function () { mcupr_forum_handler( 'reply' ); } );
add_action( 'admin_post_mcupr_forum_reply', static function () { mcupr_forum_handler( 'reply' ); } );

/* ---- reports: three reports hide a post until a moderator has looked */

/** @return string 'ok' | 'hidden' | 'again' | 'off' | 'bad' */
function mcupr_forum_report( $type, $id ) {
	$o  = mcupr_forum_opts();
	$id = (int) $id;
	if ( ! $o['reports'] || ! $o['enabled'] ) {
		return 'off';
	}
	if ( 'topic' === $type ) {
		if ( 'mcupr_topic' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return 'bad';
		}
	} else {
		$c = get_comment( $id );
		if ( ! $c || 'mcupr_topic' !== get_post_type( $c->comment_post_ID ) || '1' !== $c->comment_approved ) {
			return 'bad';
		}
	}
	$key = 'mcupr_fm_rp_' . $type . $id . '_' . substr( mcupr_forum_visitor_key( 'r' ), -16 );
	if ( get_transient( $key ) ) {
		return 'again';
	}
	set_transient( $key, 1, DAY_IN_SECONDS );
	$n = 1 + (int) ( 'topic' === $type ? get_post_meta( $id, '_mcupr_reports', true ) : get_comment_meta( $id, '_mcupr_reports', true ) );
	if ( 'topic' === $type ? update_post_meta( $id, '_mcupr_reports', $n ) : update_comment_meta( $id, '_mcupr_reports', $n ) ) {
		$to = sanitize_email( (string) $o['notify'] );
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );
		if ( 1 === $n || 3 === $n ) {
			wp_mail( $to, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . __( 'Forum: a post was reported', 'mcupr' ), ( 'topic' === $type ? get_permalink( $id ) : get_comment_link( $id ) ) . "\n" . admin_url( 'admin.php?page=mcupr-forum' ) );
		}
	}
	$by = 'topic' === $type ? (int) get_post_field( 'post_author', $id ) : (int) $c->user_id;
	if ( $n >= 3 && ! ( $by && user_can( $by, 'edit_pages' ) ) ) { // three anonymous reports must not take down a post of the staff: they go to the moderator's mail instead
		if ( 'topic' === $type ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'pending' ) );
		} else {
			wp_set_comment_status( $id, 'hold' );
		}
		return 'hidden';
	}
	return 'ok';
}

add_action(
	'admin_post_nopriv_mcupr_forum_report',
	static function () {
		mcupr_forum_report_handler();
	}
);
add_action(
	'admin_post_mcupr_forum_report',
	static function () {
		mcupr_forum_report_handler();
	}
);
function mcupr_forum_report_handler() {
	$back = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), mcupr_forum_page_url() ) : mcupr_forum_page_url(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! mcupr_check_request( '_wpnonce', 'mcupr_forum' ) ) {
		mcupr_forum_back( $back, array( 'fm' => 'err', 'fme' => 'spam' ) );
	}
	$type = isset( $_POST['rtype'] ) && 'topic' === $_POST['rtype'] ? 'topic' : 'reply';
	$r    = mcupr_forum_report( $type, isset( $_POST['rid'] ) ? (int) $_POST['rid'] : 0 );
	if ( 'hidden' === $r && 'topic' === $type ) {
		$back = mcupr_forum_page_url();
	}
	mcupr_forum_back( $back, array( 'fm' => 'reported' ) );
}

/* ================================================================== admin: topic options, columns, settings */

add_action(
	'add_meta_boxes_mcupr_topic',
	static function () {
		add_meta_box(
			'mcupr_topic_opts',
			__( 'Topic options', 'mcupr' ),
			static function ( $post ) {
				wp_nonce_field( 'mcupr_topic_opts', 'mcupr_topic_nonce' );
				echo '<p><label><input type="checkbox" name="mcupr_topic[sticky]" value="1"' . checked( '1', (string) get_post_meta( $post->ID, '_mcupr_topic_sticky', true ), false ) . '> <strong>' . esc_html__( 'Pinned (shown first in its board)', 'mcupr' ) . '</strong></label></p>';
				echo '<p><label><input type="checkbox" name="mcupr_topic[locked]" value="1"' . checked( '1', (string) get_post_meta( $post->ID, '_mcupr_topic_locked', true ), false ) . '> <strong>' . esc_html__( 'Locked (no new replies)', 'mcupr' ) . '</strong></label></p>';
				echo '<p><label>' . esc_html__( 'Author name', 'mcupr' ) . '<br><input type="text" class="widefat" name="mcupr_topic[name]" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_topic_name', true ) ) . '"></label></p>';
				$mail = (string) get_post_meta( $post->ID, '_mcupr_topic_email', true );
				if ( '' !== $mail ) {
					echo '<p class="description">' . esc_html__( 'Author email (moderators only, never shown):', 'mcupr' ) . ' ' . esc_html( $mail ) . '</p>';
				}
				$rep = (int) get_post_meta( $post->ID, '_mcupr_reports', true );
				if ( $rep ) {
					echo '<p><strong>' . esc_html( sprintf( /* translators: %d: number of reports */ __( 'Reported %d time(s)', 'mcupr' ), $rep ) ) . '</strong></p>';
				}
			},
			'mcupr_topic',
			'side'
		);
	}
);
add_action(
	'save_post_mcupr_topic',
	static function ( $post_id ) {
		if ( ! mcupr_can_save_box( $post_id, 'mcupr_topic_nonce', 'mcupr_topic_opts' ) ) {
			return;
		}
		$in = isset( $_POST['mcupr_topic'] ) && is_array( $_POST['mcupr_topic'] ) ? wp_unslash( $_POST['mcupr_topic'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( array( 'sticky', 'locked' ) as $k ) {
			if ( ! empty( $in[ $k ] ) ) {
				update_post_meta( $post_id, '_mcupr_topic_' . $k, 1 );
			} else {
				delete_post_meta( $post_id, '_mcupr_topic_' . $k );
			}
		}
		update_post_meta( $post_id, '_mcupr_topic_name', sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ) );
	}
);

add_filter(
	'manage_mcupr_topic_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_replies'] = __( 'Replies', 'mcupr' );
		$cols['mcupr_flags']   = __( 'Status', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_topic_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_replies' === $col ) {
			$pending = (int) get_comments( array( 'post_id' => $id, 'status' => 'hold', 'count' => true ) );
			echo (int) mcupr_topic_replies( $id ) . ( $pending ? ' <a href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated&p=' . $id ) ) . '">(+' . (int) $pending . ' ' . esc_html__( 'waiting', 'mcupr' ) . ')</a>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( 'mcupr_flags' === $col ) {
			$f = array();
			if ( get_post_meta( $id, '_mcupr_topic_sticky', true ) ) {
				$f[] = __( 'Pinned', 'mcupr' );
			}
			if ( get_post_meta( $id, '_mcupr_topic_locked', true ) ) {
				$f[] = __( 'Locked', 'mcupr' );
			}
			if ( (int) get_post_meta( $id, '_mcupr_reports', true ) ) {
				$f[] = sprintf( /* translators: %d: reports */ __( 'Reported ×%d', 'mcupr' ), (int) get_post_meta( $id, '_mcupr_reports', true ) );
			}
			echo esc_html( $f ? implode( ' · ', $f ) : '—' );
		}
	},
	10,
	2
);

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Forum settings', 'mcupr' ), __( 'Forum settings', 'mcupr' ), 'edit_pages', 'mcupr-forum', 'mcupr_forum_settings_page', 0 );
	}
);

add_action(
	'admin_post_mcupr_forum_settings',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_forum_nonce', 'mcupr_forum_settings', 'edit_pages' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		$p = static function ( $k ) {
			return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		};
		update_option(
			'mcupr_forum',
			array(
				'enabled'    => isset( $_POST['enabled'] ) ? 1 : 0,
				'post_who'   => 'users' === $p( 'post_who' ) ? 'users' : 'all',
				'reply_who'  => 'users' === $p( 'reply_who' ) ? 'users' : 'all',
				'moderate'   => in_array( $p( 'moderate' ), array( 'all', 'guests', 'none' ), true ) ? $p( 'moderate' ) : 'all',
				'blocked'    => sanitize_textarea_field( $p( 'blocked' ) ),
				'max_links'  => (int) $p( 'max_links' ),
				'per_page'   => (int) $p( 'per_page' ),
				'notify'     => sanitize_email( $p( 'notify' ) ),
				'reports'    => isset( $_POST['reports'] ) ? 1 : 0,
				'close_days' => (int) $p( 'close_days' ),
			)
		);
		wp_safe_redirect( add_query_arg( array( 'post_type' => 'mcupr_topic', 'page' => 'mcupr-forum', 'saved' => 1 ), admin_url( 'edit.php' ) ) );
		exit;
	}
);

function mcupr_forum_settings_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$o = mcupr_forum_opts();
	$pt = (int) wp_count_posts( 'mcupr_topic' )->pending;
	$pc = (int) get_comments( array( 'status' => 'hold', 'post_type' => 'mcupr_topic', 'count' => true ) );
	$rp = (int) ( new WP_Query( array( 'post_type' => 'mcupr_topic', 'post_status' => 'any', 'meta_key' => '_mcupr_reports', 'meta_compare' => '>', 'meta_value' => 0, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery
	echo '<div class="wrap"><h1>' . esc_html__( 'Forum settings', 'mcupr' ) . '</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Waiting for you', 'mcupr' ) . '</h2><p><a class="button' . ( $pt ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_topic&post_status=pending' ) ) . '">' . esc_html( sprintf( /* translators: %d: topics */ __( 'Topics to approve: %d', 'mcupr' ), $pt ) ) . '</a> <a class="button' . ( $pc ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ) . '">' . esc_html( sprintf( /* translators: %d: replies */ __( 'Replies to approve: %d', 'mcupr' ), $pc ) ) . '</a> <span>' . esc_html( sprintf( /* translators: %d: topics */ __( 'Reported topics: %d', 'mcupr' ), $rp ) ) . '</span> <a class="button" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=mcupr_board&post_type=mcupr_topic' ) ) . '">' . esc_html__( 'Boards', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( mcupr_forum_page_url() ) . '">' . esc_html__( 'Open the forum', 'mcupr' ) . '</a></p></div>';
	echo '<div class="card" style="max-width:none"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_forum_settings">';
	wp_nonce_field( 'mcupr_forum_settings', 'mcupr_forum_nonce' );
	$sel = static function ( $name, $val, $opts ) {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $val, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	};
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th>' . esc_html__( 'Forum', 'mcupr' ) . '</th><td><label><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $o['enabled'] ), true, false ) . '> ' . esc_html__( 'The forum is open (switching it off closes posting and hides the topic pages; nothing is deleted)', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th>' . esc_html__( 'Who can start a topic', 'mcupr' ) . '</th><td>';
	$sel( 'post_who', $o['post_who'], array( 'all' => __( 'Everyone', 'mcupr' ), 'users' => __( 'Logged-in users only', 'mcupr' ) ) );
	echo '</td></tr><tr><th>' . esc_html__( 'Who can reply', 'mcupr' ) . '</th><td>';
	$sel( 'reply_who', $o['reply_who'], array( 'all' => __( 'Everyone', 'mcupr' ), 'users' => __( 'Logged-in users only', 'mcupr' ) ) );
	echo '</td></tr><tr><th>' . esc_html__( 'Approval before a post appears', 'mcupr' ) . '</th><td>';
	$sel( 'moderate', $o['moderate'], array( 'all' => __( 'Every post (editors are not held)', 'mcupr' ), 'guests' => __( 'Only posts of visitors who are not logged in', 'mcupr' ), 'none' => __( 'None (blocked words and link limit still hold a post)', 'mcupr' ) ) );
	echo '</td></tr><tr><th><label for="fm-blocked">' . esc_html__( 'Blocked words', 'mcupr' ) . '</label></th><td><textarea id="fm-blocked" name="blocked" rows="4" class="large-text">' . esc_textarea( $o['blocked'] ) . '</textarea><p class="description">' . esc_html__( 'One word or phrase per line. A post containing one is held for approval.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th><label for="fm-links">' . esc_html__( 'Most links in one post', 'mcupr' ) . '</label></th><td><input id="fm-links" type="number" min="0" max="10" name="max_links" value="' . (int) $o['max_links'] . '"> <span class="description">' . esc_html__( 'More links hold the post for approval.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th><label for="fm-pp">' . esc_html__( 'Topics per page', 'mcupr' ) . '</label></th><td><input id="fm-pp" type="number" min="5" max="50" name="per_page" value="' . (int) $o['per_page'] . '"></td></tr>';
	echo '<tr><th><label for="fm-days">' . esc_html__( 'Close a topic after (days without activity, 0 = never)', 'mcupr' ) . '</label></th><td><input id="fm-days" type="number" min="0" max="3650" name="close_days" value="' . (int) $o['close_days'] . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Report buttons', 'mcupr' ) . '</th><td><label><input type="checkbox" name="reports" value="1"' . checked( ! empty( $o['reports'] ), true, false ) . '> ' . esc_html__( 'Visitors can report a post; three reports hide it until a moderator looks', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th><label for="fm-notify">' . esc_html__( 'Send notices to', 'mcupr' ) . '</label></th><td><input id="fm-notify" type="email" class="regular-text" name="notify" value="' . esc_attr( $o['notify'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"><p class="description">' . esc_html__( 'New posts waiting for approval and reports. Empty = the site administrator.', 'mcupr' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Save settings', 'mcupr' ) );
	echo '</form><p class="description">' . esc_html__( 'Put [mcupr_forum] on a page to show the forum. Visitors\' e-mail addresses are visible to moderators only; no IP address is stored.', 'mcupr' ) . '</p></div></div>';
}
