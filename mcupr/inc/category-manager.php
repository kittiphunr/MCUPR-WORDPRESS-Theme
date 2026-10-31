<?php
/**
 * Category manager: MCUPR → Categories. One place to see every category, tidy them, and sort the old posts into the right ones.
 *
 *  - It finds categories that say the same thing (the same name with another spelling, spacing or slug: "ข่าว", "ข่าว ", "ข่าว-2")
 *    and merges them into one, keeping every post.
 *  - A unit (หน่วยงาน) already has its own mark: an office (inc/offices.php). A category that only repeats the name of an office
 *    is a copy of it. The page lists those and moves their posts to the office (the category goes), so a unit lives in one place.
 *  - Empty categories can be removed; any category can be renamed or moved under another one.
 *  - Rules "if the title (or text) has these words → put the post in this category / this office" sort the old posts, in small
 *    batches from the page (a preview first, nothing is changed until it is applied and confirmed).
 *  - A new category that repeats an existing one, or the name of an office, is refused when it is made in the admin.
 *
 * Merging and moving are done on the term relationships directly (one query per step), so a category of thousands of posts is
 * not a loop of thousands of saves; the counts and caches are renewed afterwards.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_CAT_RULES = 'mcupr_cat_rules';

/* ------------------------------------------------------------------ names */

/** A name reduced to what it says: lower case, no spaces, no punctuation (so "ข่าว ", "ข่าว." and "ข่าว" are one). */
function mcupr_cat_norm( $name ) {
	$name = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
	$name = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	return (string) preg_replace( '/[\s\p{P}\p{S}\p{Cf}]+/u', '', $name );
}

/** A slug without the "-2" that WordPress adds to a name it has already used. */
function mcupr_cat_slug_base( $slug ) {
	return (string) preg_replace( '/-\d+$/', '', (string) $slug );
}

/** Whether a category name is the name of an office (equal, or one holds the other and the shorter one is at least 4 letters). */
function mcupr_cat_office_match( $cat_name, $office_names ) {
	$c = mcupr_cat_norm( $cat_name );
	if ( '' === $c ) {
		return 0;
	}
	$best = 0;
	foreach ( $office_names as $id => $name ) {
		$o = mcupr_cat_norm( $name );
		if ( '' === $o ) {
			continue;
		}
		if ( $c === $o ) {
			return (int) $id; // an exact copy wins at once
		}
		$short = min( mb_strlen( $c, 'UTF-8' ), mb_strlen( $o, 'UTF-8' ) );
		if ( $short >= 4 && ( false !== strpos( $c, $o ) || false !== strpos( $o, $c ) ) && ! $best ) {
			$best = (int) $id;
		}
	}
	return $best;
}

/** array( office id => title ) of the offices (any status but trash). */
function mcupr_cat_offices() {
	$out = array();
	if ( function_exists( 'mcupr_offices' ) ) {
		foreach ( mcupr_offices( array( 'publish', 'draft', 'pending', 'private' ) ) as $o ) {
			$out[ (int) $o->ID ] = $o->post_title;
		}
	}
	return $out;
}

/* ------------------------------------------------------------------ the report */

/** Every category with its depth (a tree in display order), count, and what is wrong with it. */
function mcupr_cat_report() {
	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}
	$by_parent = array();
	foreach ( $terms as $t ) {
		$by_parent[ (int) $t->parent ][] = $t;
	}
	$rows  = array();
	$walk  = static function ( $parent, $depth ) use ( &$walk, &$rows, $by_parent ) {
		foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $t ) {
			$rows[ (int) $t->term_id ] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => (int) $t->parent, 'count' => (int) $t->count, 'depth' => $depth );
			$walk( (int) $t->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	foreach ( $terms as $t ) { // a category whose parent is missing still shows
		if ( ! isset( $rows[ (int) $t->term_id ] ) ) {
			$rows[ (int) $t->term_id ] = array( 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'parent' => 0, 'count' => (int) $t->count, 'depth' => 0 );
		}
	}
	$default = (int) get_option( 'default_category' );

	$groups = array();
	foreach ( $rows as $r ) {
		$groups[ mcupr_cat_norm( $r['name'] ) ][] = $r['id'];
		if ( mcupr_cat_slug_base( $r['slug'] ) !== $r['slug'] ) {
			$groups[ 'slug:' . mcupr_cat_slug_base( $r['slug'] ) ][] = $r['id'];
		}
	}
	$dups = array();
	foreach ( $groups as $ids ) {
		$ids = array_values( array_unique( $ids ) );
		if ( count( $ids ) > 1 ) {
			sort( $ids );
			$dups[ implode( ',', $ids ) ] = $ids;
		}
	}
	$dups = array_values( $dups );

	$offices = mcupr_cat_offices();
	$like    = array();
	foreach ( $rows as $r ) {
		if ( $r['id'] === $default ) {
			continue;
		}
		$oid = mcupr_cat_office_match( $r['name'], $offices );
		if ( $oid ) {
			$like[ $r['id'] ] = $oid;
		}
	}
	$empty = array();
	foreach ( $rows as $r ) {
		if ( 0 === $r['count'] && $r['id'] !== $default && empty( $by_parent[ $r['id'] ] ) ) {
			$empty[] = $r['id'];
		}
	}
	$default_count = isset( $rows[ $default ] ) ? $rows[ $default ]['count'] : 0;
	return array( 'rows' => $rows, 'dups' => $dups, 'like' => $like, 'empty' => $empty, 'default' => $default, 'default_count' => $default_count, 'offices' => $offices );
}

/* ------------------------------------------------------------------ actions on categories */

/** Whether $maybe_child is $id or below it. */
function mcupr_cat_is_under( $maybe_child, $id ) {
	$maybe_child = (int) $maybe_child;
	$id          = (int) $id;
	return $maybe_child === $id || in_array( $id, get_ancestors( $maybe_child, 'category' ), true );
}

/**
 * Merge categories into one: every post of the others goes to $into (once), their sub-categories move under $into's parent
 * chain (under $into itself when that is not below them), and the others are deleted.
 *
 * @return int posts moved, or -1 when it cannot be done.
 */
function mcupr_cat_merge( $into, $from ) {
	global $wpdb;
	$into = (int) $into;
	$from = array_values( array_diff( array_unique( array_map( 'intval', (array) $from ) ), array( $into, (int) get_option( 'default_category' ) ) ) );
	$tt   = static function ( $id ) {
		$t = get_term( (int) $id, 'category' );
		return ( $t && ! is_wp_error( $t ) ) ? (int) $t->term_taxonomy_id : 0;
	};
	$into_tt = $tt( $into );
	if ( ! $into_tt || ! $from ) {
		return -1;
	}
	$moved = 0;
	$did   = 0;
	foreach ( $from as $id ) {
		$from_tt = $tt( $id );
		if ( ! $from_tt || mcupr_cat_is_under( $into, $id ) ) {
			continue; // gone already, or $into is inside it: that would make a loop
		}
		$did++;
		// posts that are not yet in $into move (in chunks); posts already in both just lose the old one below
		$have = array_flip( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $into_tt ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$move = array();
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $from_tt ) ) as $oid ) { // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			if ( ! isset( $have[ (int) $oid ] ) ) {
				$move[] = (int) $oid;
			}
		}
		foreach ( array_chunk( $move, 500 ) as $chunk ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->term_relationships} SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d AND object_id IN (" . implode( ',', array_fill( 0, count( $chunk ), '%d' ) ) . ')', array_merge( array( $into_tt, $from_tt ), $chunk ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		}
		$moved += count( $move );
		$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $from_tt ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( get_terms( array( 'taxonomy' => 'category', 'parent' => $id, 'hide_empty' => false, 'fields' => 'ids' ) ) as $child ) {
			wp_update_term( (int) $child, 'category', array( 'parent' => $into ) );
		}
		wp_delete_term( $id, 'category' );
	}
	if ( ! $did ) {
		return -1;
	}
	wp_update_term_count_now( array( $into_tt ), 'category' );
	clean_term_cache( array_merge( array( $into ), $from ), 'category' );
	wp_cache_delete( 'last_changed', 'posts' );
	return $moved;
}

/**
 * A category that is really an office: the posts of the category are marked with the office, the category is taken off them
 * (a post left with no category gets the default one) and the category is deleted. Done in one go for up to $limit posts.
 *
 * @return array posts done, left (posts still in the category), deleted (bool); or an empty array when it cannot be done.
 */
function mcupr_cat_to_office( $cat_id, $office_id, $limit = 1500 ) {
	$cat_id    = (int) $cat_id;
	$office_id = (int) $office_id;
	if ( ! $cat_id || $cat_id === (int) get_option( 'default_category' ) || ! $office_id || 'mcupr_office' !== get_post_type( $office_id ) || ! function_exists( 'mcupr_office_assign' ) ) {
		return array();
	}
	$ids = get_posts( array( 'post_type' => function_exists( 'mcupr_office_types' ) ? mcupr_office_types() : 'post', 'post_status' => 'any', 'cat' => $cat_id, 'fields' => 'ids', 'posts_per_page' => (int) $limit, 'no_found_rows' => true, 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
	$done = 0;
	foreach ( $ids as $pid ) {
		if ( ! mcupr_office_of( $pid ) ) { // a post that already belongs to an office keeps it
			mcupr_office_assign( $pid, $office_id );
		}
		$now = wp_get_post_categories( $pid, array( 'fields' => 'ids' ) );
		wp_set_post_categories( $pid, array_values( array_diff( $now, array( $cat_id ) ) ) );
		$done++;
	}
	$left = count( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'cat' => $cat_id, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => true, 'suppress_filters' => true ) ) );
	$gone = false;
	if ( ! $left ) {
		$gone = ! is_wp_error( wp_delete_term( $cat_id, 'category' ) );
	}
	return array( 'done' => $done, 'left' => $left, 'deleted' => $gone );
}

/* ------------------------------------------------------------------ rules for the old posts */

/** The saved rules: each array( words (comma separated), target ("cat:ID" or "office:ID"), where ("title" | "all") ). */
function mcupr_cat_rules() {
	$r = get_option( MCUPR_CAT_RULES, array() );
	$o = array();
	foreach ( is_array( $r ) ? $r : array() as $x ) {
		if ( is_array( $x ) && ! empty( $x['words'] ) && ! empty( $x['target'] ) && preg_match( '/^(cat|office):\d+$/', (string) $x['target'] ) ) {
			$o[] = array( 'words' => (string) $x['words'], 'target' => (string) $x['target'], 'where' => ( isset( $x['where'] ) && 'all' === $x['where'] ) ? 'all' : 'title' );
		}
	}
	return $o;
}

/** Whether a rule fits a post (any of the words, in the title, or the title and the text). */
function mcupr_cat_rule_fits( $rule, $title, $content = '' ) {
	$hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title . ( 'all' === $rule['where'] ? ' ' . wp_strip_all_tags( $content ) : '' ), 'UTF-8' ) : strtolower( $title );
	foreach ( explode( ',', (string) $rule['words'] ) as $w ) {
		$w = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $w, 'UTF-8' ) : strtolower( $w ) );
		if ( '' !== $w && false !== strpos( $hay, $w ) ) {
			return true;
		}
	}
	return false;
}

/** The post statuses the rules look at (not the trash, not revisions). */
function mcupr_cat_statuses() {
	return array( 'publish', 'draft', 'pending', 'future', 'private' );
}

/** Posts after an ID, oldest first, for a batch. */
function mcupr_cat_posts_after( $after, $limit ) {
	global $wpdb;
	$st = "'" . implode( "','", array_map( 'esc_sql', mcupr_cat_statuses() ) ) . "'";
	return $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ($st) AND ID > %d ORDER BY ID ASC LIMIT %d", (int) $after, (int) $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
}

function mcupr_cat_posts_total() {
	global $wpdb;
	$st = "'" . implode( "','", array_map( 'esc_sql', mcupr_cat_statuses() ) ) . "'";
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ($st)" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
}

/**
 * Sort one batch of posts by the rules.
 *
 * @param int  $after   Handle posts with an ID above this.
 * @param int  $limit   Posts in the batch.
 * @param bool $apply   false = only count and name some (the preview).
 * @param bool $replace true = the matched categories replace the old ones; false = they are added (the default category then goes).
 * @return array last (ID), n (posts looked at), changed (posts that change), sample (titles of some that change), more (bool)
 */
function mcupr_cat_run_batch( $after, $limit, $apply, $replace ) {
	$rules   = mcupr_cat_rules();
	$default = (int) get_option( 'default_category' );
	$out     = array( 'last' => (int) $after, 'n' => 0, 'changed' => 0, 'sample' => array(), 'more' => false );
	$posts   = mcupr_cat_posts_after( $after, $limit );
	foreach ( $posts as $p ) {
		$out['last'] = (int) $p->ID;
		$out['n']++;
		$cats    = array();
		$office  = 0;
		foreach ( $rules as $rule ) {
			if ( ! mcupr_cat_rule_fits( $rule, $p->post_title, $p->post_content ) ) {
				continue;
			}
			list( $kind, $id ) = explode( ':', $rule['target'] );
			if ( 'cat' === $kind && term_exists( (int) $id, 'category' ) ) {
				$cats[] = (int) $id;
			} elseif ( 'office' === $kind && ! $office && 'mcupr_office' === get_post_type( (int) $id ) ) {
				$office = (int) $id;
			}
		}
		if ( ! $cats && ! $office ) {
			continue;
		}
		$now  = wp_get_post_categories( $p->ID, array( 'fields' => 'ids' ) );
		$new  = $cats ? array_values( array_unique( $replace ? $cats : array_merge( $now, $cats ) ) ) : $now;
		if ( $cats && ! $replace && count( $new ) > 1 ) {
			$new = array_values( array_diff( $new, array( $default ) ) ); // "Uncategorized" is not kept beside a real category
		}
		$cat_change    = $cats && ( array_diff( $new, $now ) || array_diff( $now, $new ) );
		$office_change = $office && function_exists( 'mcupr_office_of' ) && mcupr_office_of( $p->ID ) !== $office;
		if ( ! $cat_change && ! $office_change ) {
			continue;
		}
		$out['changed']++;
		if ( count( $out['sample'] ) < 8 ) {
			$out['sample'][] = get_the_title( $p->ID );
		}
		if ( $apply ) {
			if ( $cat_change ) {
				wp_set_post_categories( $p->ID, $new );
			}
			if ( $office_change ) {
				mcupr_office_assign( $p->ID, $office );
			}
		}
	}
	$out['more'] = count( $posts ) === (int) $limit;
	return $out;
}

/* ------------------------------------------------------------------ handlers */

function mcupr_cat_back( $msg, $n = 0 ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'mcupr-categories', 'msg' => $msg, 'n' => (int) $n ), admin_url( 'admin.php' ) ) . '#mcupr-cat-' . $msg );
	exit;
}

add_action(
	'admin_post_mcupr_cat',
	static function () {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		check_admin_referer( 'mcupr_cat', 'mcupr_cat_nonce' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$ids = static function ( $key ) {
			return isset( $_POST[ $key ] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		};
		if ( 'merge' === $do ) {
			$into = isset( $_POST['keep'] ) ? absint( $_POST['keep'] ) : 0;
			$from = array_diff( $ids( 'group' ), array( $into ) );
			$n    = mcupr_cat_merge( $into, $from );
			mcupr_cat_back( $n < 0 ? 'fail' : 'merged', max( 0, $n ) );
		}
		if ( 'to_office' === $do ) {
			$r = mcupr_cat_to_office( isset( $_POST['cat'] ) ? absint( $_POST['cat'] ) : 0, isset( $_POST['office'] ) ? absint( $_POST['office'] ) : 0 );
			mcupr_cat_back( $r ? ( $r['left'] ? 'partial' : 'office' ) : 'fail', $r ? $r['done'] : 0 );
		}
		if ( 'delete' === $do ) {
			$n = 0;
			foreach ( $ids( 'del' ) as $id ) {
				$t = get_term( $id, 'category' );
				if ( $t && ! is_wp_error( $t ) && 0 === (int) $t->count && $id !== (int) get_option( 'default_category' ) && ! is_wp_error( wp_delete_term( $id, 'category' ) ) ) {
					$n++;
				}
			}
			mcupr_cat_back( 'deleted', $n );
		}
		if ( 'rules' === $do ) {
			$rules = array();
			$w     = isset( $_POST['rule_words'] ) ? (array) wp_unslash( $_POST['rule_words'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$t     = isset( $_POST['rule_target'] ) ? (array) wp_unslash( $_POST['rule_target'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$wh    = isset( $_POST['rule_where'] ) ? (array) wp_unslash( $_POST['rule_where'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$del   = isset( $_POST['rule_del'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rule_del'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( $w as $i => $words ) {
				$words = sanitize_text_field( (string) $words );
				$tg    = isset( $t[ $i ] ) ? sanitize_text_field( (string) $t[ $i ] ) : '';
				if ( '' === trim( $words, ", \t" ) || ! preg_match( '/^(cat|office):\d+$/', $tg ) || in_array( (int) $i, $del, true ) ) {
					continue;
				}
				$rules[] = array( 'words' => $words, 'target' => $tg, 'where' => ( isset( $wh[ $i ] ) && 'all' === $wh[ $i ] ) ? 'all' : 'title' );
			}
			update_option( MCUPR_CAT_RULES, $rules, false );
			mcupr_cat_back( 'rules', count( $rules ) );
		}
		mcupr_cat_back( 'fail' );
	}
);

add_action(
	'wp_ajax_mcupr_cat_run',
	static function () {
		if ( ! check_ajax_referer( 'mcupr_cat_run', 'nonce', false ) || ! current_user_can( 'manage_categories' ) || ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mcupr' ) ), 403 );
		}
		$apply = ! empty( $_POST['apply'] );
		if ( $apply && empty( $_POST['confirm'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Tick the box to confirm first.', 'mcupr' ) ), 400 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$after = isset( $_POST['after'] ) ? absint( $_POST['after'] ) : 0;
		$b     = mcupr_cat_run_batch( $after, $apply ? 40 : 200, $apply, ! empty( $_POST['replace'] ) );
		$b['total'] = mcupr_cat_posts_total();
		wp_send_json_success( $b );
	}
);

/** A new category that repeats one that exists, or an office, is refused when it is made in the admin (the category screen or the post editor). */
add_filter(
	'pre_insert_term',
	static function ( $term, $taxonomy ) {
		if ( 'category' !== $taxonomy || ! is_admin() || is_wp_error( $term ) ) {
			return $term;
		}
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $action, array( 'add-tag', 'add-category' ), true ) ) {
			return $term; // imports, demos and code are not questioned
		}
		$n = mcupr_cat_norm( $term );
		if ( '' === $n ) {
			return $term;
		}
		foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'id=>name' ) ) as $name ) {
			if ( mcupr_cat_norm( $name ) === $n ) {
				/* translators: %s: the name of the category that exists */
				return new WP_Error( 'mcupr_cat_dup', sprintf( __( 'A category like this already exists: “%s”. Use it, or merge them in MCUPR → Categories.', 'mcupr' ), $name ) );
			}
		}
		foreach ( mcupr_cat_offices() as $oname ) {
			if ( mcupr_cat_norm( $oname ) === $n ) {
				/* translators: %s: the name of the office */
				return new WP_Error( 'mcupr_cat_office', sprintf( __( '“%s” is an office. A unit is marked on the post as an office, not as a category: choose the office in the post instead.', 'mcupr' ), $oname ) );
			}
		}
		return $term;
	},
	10,
	2
);

/* ------------------------------------------------------------------ the page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Categories', 'mcupr' ), __( 'Categories', 'mcupr' ), 'manage_categories', 'mcupr-categories', 'mcupr_cat_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false === strpos( (string) $hook, 'mcupr-categories' ) ) {
			return;
		}
		wp_enqueue_script( 'mcupr-category-manager', get_theme_file_uri( 'assets/js/category-manager.js' ), array(), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-category-manager',
			'mcuprCat',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mcupr_cat_run' ),
				't'     => array(
					'run'     => __( 'Working… %1$d of %2$d posts looked at, %3$d to change. Keep this page open.', 'mcupr' ),
					'runApply'=> __( 'Working… %1$d of %2$d posts looked at, %3$d changed. Keep this page open.', 'mcupr' ),
					'previewDone' => __( 'Preview: %1$d of %2$d posts would change. Nothing has been changed yet.', 'mcupr' ),
					'applyDone'   => __( 'Finished: %1$d of %2$d posts were changed.', 'mcupr' ),
					'none'    => __( 'No rule fits any post. Add rules above and save them first.', 'mcupr' ),
					'fail'    => __( 'The server did not answer. Start again; posts that were already sorted stay as they are.', 'mcupr' ),
					'sample'  => __( 'For example:', 'mcupr' ),
				),
			)
		);
	}
);

/** <option>s of the categories (as a tree) and offices for a rule's target. */
function mcupr_cat_target_options( $rows, $offices, $selected = '' ) {
	$o = '<optgroup label="' . esc_attr__( 'Category', 'mcupr' ) . '">';
	foreach ( $rows as $r ) {
		$v  = 'cat:' . $r['id'];
		$o .= '<option value="' . esc_attr( $v ) . '"' . selected( $selected, $v, false ) . '>' . esc_html( str_repeat( '— ', $r['depth'] ) . $r['name'] ) . '</option>';
	}
	$o .= '</optgroup>';
	if ( $offices ) {
		$o .= '<optgroup label="' . esc_attr__( 'Office', 'mcupr' ) . '">';
		foreach ( $offices as $id => $name ) {
			$v  = 'office:' . $id;
			$o .= '<option value="' . esc_attr( $v ) . '"' . selected( $selected, $v, false ) . '>' . esc_html( $name ) . '</option>';
		}
		$o .= '</optgroup>';
	}
	return $o;
}

function mcupr_cat_form_open( $do, $extra = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . $extra . '><input type="hidden" name="action" value="mcupr_cat"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
	wp_nonce_field( 'mcupr_cat', 'mcupr_cat_nonce' );
}

function mcupr_cat_page() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$rep  = mcupr_cat_report();
	$rows = $rep['rows'];
	$off  = $rep['offices'];
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$msg = isset( $_GET['msg'] ) ? sanitize_key( wp_unslash( $_GET['msg'] ) ) : '';
	$num = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;
	// phpcs:enable
	$notes = array(
		/* translators: %d: posts */
		'merged'  => array( 'success', sprintf( __( 'Merged. %d posts moved into the category you kept.', 'mcupr' ), $num ) ),
		/* translators: %d: posts */
		'office'  => array( 'success', sprintf( __( 'Done. %d posts are marked with the office, and the category is removed.', 'mcupr' ), $num ) ),
		/* translators: %d: posts */
		'partial' => array( 'warning', sprintf( __( '%d posts were moved to the office, but the category still has posts. Run it again to finish.', 'mcupr' ), $num ) ),
		/* translators: %d: categories */
		'deleted' => array( 'success', sprintf( __( '%d empty categories deleted.', 'mcupr' ), $num ) ),
		/* translators: %d: rules */
		'rules'   => array( 'success', sprintf( __( 'Rules saved (%d).', 'mcupr' ), $num ) ),
		'fail'    => array( 'error', __( 'That could not be done (check the choice, and that a category is not moved under itself).', 'mcupr' ) ),
	);
	echo '<style>.mcupr-cat .card{max-width:72rem;overflow-wrap:anywhere}.mcupr-cat table.widefat td,.mcupr-cat table.widefat th{vertical-align:middle}.mcupr-cat .num,.mcupr-cat .nw{text-align:right;white-space:nowrap}.mcupr-cat table.widefat{table-layout:auto;width:100%}.mcupr-cat code{word-break:break-all}.mcupr-cat input[type=text].wide{width:100%;max-width:22rem}.mcupr-cat .dim{color:#646970}.mcupr-cat .row-actions-inline{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}</style>';
	echo '<div class="wrap mcupr-cat"><h1>' . esc_html__( 'Categories', 'mcupr' ) . '</h1>';
	if ( isset( $notes[ $msg ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $notes[ $msg ][0] ) . ' is-dismissible" id="mcupr-cat-' . esc_attr( $msg ) . '"><p>' . esc_html( $notes[ $msg ][1] ) . '</p></div>';
	}
	echo '<p style="max-width:60rem">' . esc_html__( 'Keep one category for one subject. Units (offices) are marked on a post as an office, so they do not need a category too. Back up the database before merging or moving: these changes touch many posts.', 'mcupr' ) . '</p>';

	// summary
	echo '<div class="card"><h2>' . esc_html__( 'Summary', 'mcupr' ) . '</h2><ul style="margin:0 0 .5rem 1.2rem;list-style:disc">';
	/* translators: %d: categories */
	echo '<li>' . esc_html( sprintf( __( 'Categories: %d', 'mcupr' ), count( $rows ) ) ) . '</li>';
	/* translators: %d: groups */
	echo '<li>' . esc_html( sprintf( __( 'Groups that say the same thing: %d', 'mcupr' ), count( $rep['dups'] ) ) ) . '</li>';
	/* translators: %d: categories */
	echo '<li>' . esc_html( sprintf( __( 'Categories that look like an office: %d', 'mcupr' ), count( $rep['like'] ) ) ) . '</li>';
	/* translators: %d: categories */
	echo '<li>' . esc_html( sprintf( __( 'Empty categories: %d', 'mcupr' ), count( $rep['empty'] ) ) ) . '</li>';
	/* translators: 1: posts, 2: the name of the default category */
	echo '<li>' . esc_html( sprintf( __( 'Posts still in the default category “%2$s”: %1$d', 'mcupr' ), $rep['default_count'], isset( $rows[ $rep['default'] ] ) ? $rows[ $rep['default'] ]['name'] : '' ) ) . '</li>';
	echo '</ul></div>';

	// duplicates
	echo '<div class="card" id="mcupr-cat-dups"><h2>' . esc_html__( '1. Categories that say the same thing', 'mcupr' ) . '</h2>';
	if ( ! $rep['dups'] ) {
		echo '<p>' . esc_html__( 'None found.', 'mcupr' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'Choose the one to keep in each group. The others are merged into it: their posts move to it and they are deleted.', 'mcupr' ) . '</p>';
		foreach ( $rep['dups'] as $g ) {
			mcupr_cat_form_open( 'merge' );
			echo '<table class="widefat striped" style="margin-bottom:1rem"><thead><tr><th>' . esc_html__( 'Keep', 'mcupr' ) . '</th><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Slug', 'mcupr' ) . '</th><th class="num">' . esc_html__( 'Posts', 'mcupr' ) . '</th></tr></thead><tbody>';
			$best = $g[0];
			foreach ( $g as $id ) {
				if ( isset( $rows[ $id ] ) && $rows[ $id ]['count'] > $rows[ $best ]['count'] ) {
					$best = $id;
				}
			}
			foreach ( $g as $id ) {
				if ( ! isset( $rows[ $id ] ) ) {
					continue;
				}
				echo '<tr><td><input type="radio" name="keep" value="' . (int) $id . '"' . checked( $id, $best, false ) . ' aria-label="' . esc_attr__( 'Keep', 'mcupr' ) . '"><input type="hidden" name="group[]" value="' . (int) $id . '"></td><td>' . esc_html( $rows[ $id ]['name'] ) . '</td><td><code>' . esc_html( rawurldecode( $rows[ $id ]['slug'] ) ) . '</code></td><td class="num">' . (int) $rows[ $id ]['count'] . '</td></tr>';
			}
			echo '</tbody></table><p><button class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Merge the others into the one you kept?', 'mcupr' ) ) . '\')">' . esc_html__( 'Merge into the one kept', 'mcupr' ) . '</button></p></form>';
		}
	}
	echo '</div>';

	// office look-alikes
	echo '<div class="card" id="mcupr-cat-offices"><h2>' . esc_html__( '2. Categories that repeat an office', 'mcupr' ) . '</h2>';
	if ( ! $rep['like'] ) {
		echo '<p>' . esc_html__( 'None found.', 'mcupr' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'A unit is kept as an office. For each category below, check the office it matches, then move its posts to the office: the posts are marked with the office and the category is deleted.', 'mcupr' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Category', 'mcupr' ) . '</th><th class="num">' . esc_html__( 'Posts', 'mcupr' ) . '</th><th>' . esc_html__( 'Office', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $rep['like'] as $cid => $oid ) {
			$fid = 'mcupr-cat-office-' . (int) $cid;
			mcupr_cat_form_open( 'to_office', ' id="' . esc_attr( $fid ) . '"' );
			echo '<input type="hidden" name="cat" value="' . (int) $cid . '"></form>';
			echo '<tr><td>' . esc_html( $rows[ $cid ]['name'] ) . '</td><td class="num">' . (int) $rows[ $cid ]['count'] . '</td><td><select name="office" form="' . esc_attr( $fid ) . '" aria-label="' . esc_attr__( 'Office', 'mcupr' ) . '">';
			foreach ( $off as $id => $name ) {
				echo '<option value="' . (int) $id . '"' . selected( $id, $oid, false ) . '>' . esc_html( $name ) . '</option>';
			}
			echo '</select></td><td><button class="button" form="' . esc_attr( $fid ) . '" onclick="return confirm(\'' . esc_js( __( 'Move the posts to the office and delete the category?', 'mcupr' ) ) . '\')">' . esc_html__( 'Move to the office', 'mcupr' ) . '</button></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';

	// empty
	echo '<div class="card" id="mcupr-cat-empty"><h2>' . esc_html__( '3. Empty categories', 'mcupr' ) . '</h2>';
	if ( ! $rep['empty'] ) {
		echo '<p>' . esc_html__( 'None found.', 'mcupr' ) . '</p>';
	} else {
		mcupr_cat_form_open( 'delete' );
		echo '<p>';
		foreach ( $rep['empty'] as $id ) {
			echo '<label style="display:inline-block;margin:0 1rem .4rem 0"><input type="checkbox" name="del[]" value="' . (int) $id . '" checked> ' . esc_html( $rows[ $id ]['name'] ) . '</label>';
		}
		echo '</p><p><button class="button" onclick="return confirm(\'' . esc_js( __( 'Delete the ticked categories?', 'mcupr' ) ) . '\')">' . esc_html__( 'Delete the ticked categories', 'mcupr' ) . '</button></p></form>';
	}
	echo '</div>';

	// rules
	$rules = mcupr_cat_rules();
	$rules[] = array( 'words' => '', 'target' => '', 'where' => 'title' ); // a blank row to add one
	echo '<div class="card" id="mcupr-cat-rules"><h2>' . esc_html__( '4. Sort the old posts into the right categories', 'mcupr' ) . '</h2>';
	echo '<p>' . esc_html__( 'Write rules: when the title (or the title and the text) has one of the words, put the post in that category or office. Several rules can fit one post. Save the rules, look at the preview, then apply.', 'mcupr' ) . '</p>';
	mcupr_cat_form_open( 'rules' );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Words (separate with commas)', 'mcupr' ) . '</th><th>' . esc_html__( 'Look in', 'mcupr' ) . '</th><th>' . esc_html__( 'Put in', 'mcupr' ) . '</th><th>' . esc_html__( 'Remove', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( $rules as $i => $r ) {
		echo '<tr><td><input type="text" class="wide" name="rule_words[' . (int) $i . ']" value="' . esc_attr( $r['words'] ) . '" aria-label="' . esc_attr__( 'Words', 'mcupr' ) . '"></td>';
		echo '<td><select name="rule_where[' . (int) $i . ']"><option value="title"' . selected( $r['where'], 'title', false ) . '>' . esc_html__( 'the title', 'mcupr' ) . '</option><option value="all"' . selected( $r['where'], 'all', false ) . '>' . esc_html__( 'the title and the text', 'mcupr' ) . '</option></select></td>';
		echo '<td><select name="rule_target[' . (int) $i . ']"><option value="">—</option>' . mcupr_cat_target_options( $rows, $off, $r['target'] ) . '</select></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td>' . ( '' !== $r['words'] ? '<input type="checkbox" name="rule_del[]" value="' . (int) $i . '" aria-label="' . esc_attr__( 'Remove', 'mcupr' ) . '">' : '' ) . '</td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save the rules', 'mcupr' ) . '</button></p></form>';
	echo '<hr><p><label><input type="checkbox" id="mcupr-cat-replace"> ' . esc_html__( 'Replace the old categories of a post that a rule fits (otherwise the new ones are added, and “Uncategorized” goes)', 'mcupr' ) . '</label></p>';
	echo '<p><button type="button" class="button" id="mcupr-cat-preview">' . esc_html__( 'Preview (changes nothing)', 'mcupr' ) . '</button></p>';
	echo '<p><label><input type="checkbox" id="mcupr-cat-ok"> ' . esc_html__( 'I have a backup, and I agree that the posts are changed', 'mcupr' ) . '</label></p>';
	echo '<p><button type="button" class="button button-primary" id="mcupr-cat-apply" disabled>' . esc_html__( 'Apply the rules to all posts', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-cat-stop" hidden>' . esc_html__( 'Stop', 'mcupr' ) . '</button></p>';
	echo '<progress id="mcupr-cat-bar" value="0" max="1" hidden style="width:100%;max-width:30rem"></progress><p id="mcupr-cat-msg" role="status" aria-live="polite"></p><ul id="mcupr-cat-sample" style="margin-left:1.2rem;list-style:disc"></ul></div>';

	// all categories
	echo '<div class="card" id="mcupr-cat-all"><h2>' . esc_html__( '5. All categories', 'mcupr' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Slug', 'mcupr' ) . '</th><th class="num">' . esc_html__( 'Posts', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( str_repeat( '— ', $r['depth'] ) . $r['name'] ) . '</td><td><code>' . esc_html( rawurldecode( $r['slug'] ) ) . '</code></td><td class="num"><a href="' . esc_url( admin_url( 'edit.php?cat=' . $r['id'] ) ) . '">' . (int) $r['count'] . '</a></td><td class="nw"><a href="' . esc_url( admin_url( 'term.php?taxonomy=category&tag_ID=' . $r['id'] ) ) . '">' . esc_html__( 'Edit (name, slug, parent)', 'mcupr' ) . '</a></td></tr>';
	}
	echo '</tbody></table></div></div>';
}
