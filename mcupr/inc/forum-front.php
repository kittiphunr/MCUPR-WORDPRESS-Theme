<?php
/**
 * Discussion board: what the visitor sees – [mcupr_forum], the forms and the topic page content.
 * See forum.php for the data, rules and moderation.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Messages and errors that come back in the address after posting. */
function mcupr_forum_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['fm'] ) ? sanitize_key( wp_unslash( $_GET['fm'] ) ) : '';
	$errs  = isset( $_GET['fme'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET['fme'] ) ) ) ) ) : array();
	// phpcs:enable
	$map = array(
		'name' => 'form_err_name', 'email' => 'form_err_email', 'message' => 'form_err_msg', 'consent' => 'form_err_consent', 'spam' => 'form_err_spam', 'limit' => 'form_err_limit',
		'title' => 'fm_err_title', 'board' => 'fm_err_board', 'topic' => 'fm_err_topic', 'locked' => 'fm_err_locked', 'dup' => 'fm_err_dup', 'login' => 'fm_err_login', 'closed' => 'fm_err_closed',
	);
	$out = '';
	if ( 'pending' === $state ) {
		$out .= '<div class="mform-ok" role="status"><p><b>' . esc_html( mcupr_t( 'fm_pending' ) ) . '</b></p></div>';
	} elseif ( 'reported' === $state ) {
		$out .= '<div class="mform-ok" role="status"><p><b>' . esc_html( mcupr_t( 'fm_reported' ) ) . '</b></p></div>';
	} elseif ( 'err' === $state ) {
		$out .= '<div class="mform-err" role="alert"><p><b>' . esc_html( mcupr_t( 'form_err' ) ) . '</b></p><ul>';
		foreach ( array_intersect( array_keys( $map ), $errs ) as $e ) {
			$out .= '<li>' . esc_html( mcupr_t( $map[ $e ] ) ) . '</li>';
		}
		$out .= '</ul></div>';
	}
	return array( $out, $errs );
}

/** The form for a new topic ($board_id preselects the board) or a reply (pass $topic_id). */
function mcupr_forum_form( $board_id = 0, $topic_id = 0 ) {
	$o     = mcupr_forum_opts();
	$reply = (bool) $topic_id;
	$who   = $reply ? $o['reply_who'] : $o['post_who'];
	if ( 'users' === $who && ! is_user_logged_in() ) {
		return '<p class="forum-login">' . esc_html( mcupr_t( 'fm_login_need' ) ) . ' <a href="' . esc_url( wp_login_url( $reply ? get_permalink( $topic_id ) : mcupr_forum_page_url() ) ) . '">' . esc_html( mcupr_t( 'fm_login' ) ) . '</a></p>';
	}
	list( , $errs ) = mcupr_forum_notices();
	$bad     = $errs;
	$inv     = static function ( $f ) use ( $bad ) {
		return in_array( $f, $bad, true ) ? ' aria-invalid="true"' : '';
	};
	$fid     = $reply ? 'fm-reply' : 'fm-topic';
	$u       = wp_get_current_user();
	$staff   = current_user_can( 'edit_pages' );
	$privacy = mcupr_policy_url( 'privacy' );
	$back    = $reply ? get_permalink( $topic_id ) : mcupr_forum_page_url();
	ob_start();
	echo '<div class="mform"><form class="forum-form" id="' . esc_attr( $fid ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
	echo '<input type="hidden" name="action" value="' . ( $reply ? 'mcupr_forum_reply' : 'mcupr_forum_topic' ) . '"><input type="hidden" name="back" value="' . esc_url( $back ) . '">';
	if ( $reply ) {
		echo '<input type="hidden" name="topic" value="' . (int) $topic_id . '">';
	}
	wp_nonce_field( 'mcupr_forum', '_wpnonce', true, true );
	echo '<input type="hidden" name="tk" value="' . esc_attr( mcupr_form_token( time() ) ) . '">';
	echo '<div class="mform-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	echo '<p class="mform-note">' . esc_html( mcupr_t( 'form_req' ) ) . '</p>';
	echo '<div class="mform-row"><p><label for="' . esc_attr( $fid ) . '-name">' . esc_html( mcupr_t( 'form_name' ) ) . ' <span aria-hidden="true">*</span></label><input id="' . esc_attr( $fid ) . '-name" name="name" type="text" maxlength="60" autocomplete="nickname" value="' . esc_attr( is_user_logged_in() ? $u->display_name : '' ) . '" required' . $inv( 'name' ) . '></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><label for="' . esc_attr( $fid ) . '-email">' . esc_html( mcupr_t( 'form_email' ) ) . ' <span aria-hidden="true">*</span></label><input id="' . esc_attr( $fid ) . '-email" name="email" type="email" autocomplete="email" value="' . esc_attr( is_user_logged_in() ? $u->user_email : '' ) . '" required aria-describedby="' . esc_attr( $fid ) . '-en"' . $inv( 'email' ) . '><small id="' . esc_attr( $fid ) . '-en" class="forum-hint">' . esc_html( mcupr_t( 'fm_email_note' ) ) . '</small></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! $reply ) {
		echo '<div class="mform-row"><p><label for="fm-board">' . esc_html( mcupr_t( 'fm_f_board' ) ) . ' <span aria-hidden="true">*</span></label><select id="fm-board" name="board"' . $inv( 'board' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( get_terms( array( 'taxonomy' => 'mcupr_board', 'hide_empty' => false ) ) as $b ) {
			echo '<option value="' . (int) $b->term_id . '"' . selected( (int) $board_id, (int) $b->term_id, false ) . '>' . esc_html( $b->name ) . '</option>';
		}
		echo '</select></p><p><label for="fm-title">' . esc_html( mcupr_t( 'fm_f_title' ) ) . ' <span aria-hidden="true">*</span></label><input id="fm-title" name="title" type="text" maxlength="150" required' . $inv( 'title' ) . '></p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<p><label for="' . esc_attr( $fid ) . '-msg">' . esc_html( mcupr_t( 'fm_f_msg' ) ) . ' <span aria-hidden="true">*</span></label><textarea id="' . esc_attr( $fid ) . '-msg" name="message" rows="' . ( $reply ? 5 : 7 ) . '" maxlength="5000" required' . $inv( 'message' ) . '></textarea></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! $staff ) {
		echo '<p class="mform-consent"><label><input type="checkbox" name="consent" value="1" required' . $inv( 'consent' ) . '> <span>' . esc_html( mcupr_t( 'form_consent' ) ) . ( $privacy ? ' <a href="' . esc_url( $privacy ) . '">' . esc_html( mcupr_t( 'privacy' ) ) . '</a>' : '' ) . '</span></label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<p><button type="submit" class="btn">' . esc_html( mcupr_t( $reply ? 'fm_reply_send' : 'fm_post' ) ) . '</button></p></form></div>';
	return ob_get_clean();
}

/** A small report button (a form, so it works without JavaScript). */
function mcupr_forum_report_button( $type, $id, $back ) {
	if ( ! mcupr_forum_opts()['reports'] ) {
		return '';
	}
	return '<form class="forum-report" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_forum_report"><input type="hidden" name="rtype" value="' . esc_attr( $type ) . '"><input type="hidden" name="rid" value="' . (int) $id . '"><input type="hidden" name="back" value="' . esc_url( $back ) . '">' . wp_nonce_field( 'mcupr_forum', '_wpnonce', true, false ) . '<button type="submit">' . esc_html( mcupr_t( 'fm_report' ) ) . '</button></form>';
}

/** One topic as a row of the list. */
function mcupr_forum_row( $p, $pinned = false ) {
	$id    = $p->ID;
	$name  = mcupr_topic_name( $id );
	$board = get_the_terms( $id, 'mcupr_board' );
	$n     = mcupr_topic_replies( $id );
	$last  = mcupr_topic_last( $id );
	$o     = '<li class="forum-row' . ( $pinned ? ' is-pinned' : '' ) . '"><span class="forum-av" aria-hidden="true">' . esc_html( mcupr_forum_initial( $name ) ) . '</span><div class="forum-main"><h3 class="forum-t"><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
	if ( $pinned ) {
		$o .= ' <span class="forum-badge">' . esc_html( mcupr_t( 'fm_pinned' ) ) . '</span>';
	}
	if ( mcupr_topic_locked( $id ) ) {
		$o .= ' <span class="forum-badge forum-badge-l">' . esc_html( mcupr_t( 'fm_locked' ) ) . '</span>';
	}
	$o .= '</h3><p class="forum-m">' . esc_html( mcupr_t( 'fm_by' ) . ' ' . $name ) . ( $board && ! is_wp_error( $board ) ? ' · <a href="' . esc_url( add_query_arg( 'fb', $board[0]->slug, mcupr_forum_page_url() ) ) . '">' . esc_html( $board[0]->name ) . '</a>' : '' ) . ' · ' . esc_html( mcupr_date_fmt( 'j M Y', (int) get_post_time( 'U', true, $id ) ) ) . '</p></div>';
	$o .= '<div class="forum-c"><b>' . (int) $n . '</b><span>' . esc_html( mcupr_t( 'fm_replies' ) ) . '</span></div><div class="forum-l"><span>' . esc_html( mcupr_t( 'fm_last' ) ) . '</span><time datetime="' . esc_attr( gmdate( 'c', $last ) ) . '">' . esc_html( mcupr_date_fmt( 'j M Y H:i', $last ) ) . '</time></div></li>';
	return $o;
}

/** [mcupr_forum] */
function mcupr_forum_html() {
	$o = mcupr_forum_opts();
	if ( ! $o['enabled'] ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'fm_closed' ) ) . '</p>';
	}
	$base = mcupr_forum_page_url();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$slug = isset( $_GET['fb'] ) ? sanitize_title( wp_unslash( $_GET['fb'] ) ) : '';
	$q    = isset( $_GET['fq'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['fq'] ) ) ) : '';
	$page = isset( $_GET['fp'] ) ? max( 1, (int) $_GET['fp'] ) : 1;
	// phpcs:enable
	$board = $slug ? get_term_by( 'slug', $slug, 'mcupr_board' ) : null;
	list( $notices ) = mcupr_forum_notices();
	ob_start();
	echo '<section class="forum" id="forum">' . $notices; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="forum-bar"><form class="forum-search" role="search" method="get" action="' . esc_url( $base ) . '"><label class="sr-only" for="fm-q">' . esc_html( mcupr_t( 'fm_search' ) ) . '</label>' . mcupr_icon( 'search', 18 ) . '<input id="fm-q" type="search" name="fq" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr( mcupr_t( 'fm_search' ) ) . '"><button type="submit" class="sr-only">' . esc_html( mcupr_t( 'fm_search' ) ) . '</button></form><a class="btn" href="#fm-topic">' . esc_html( mcupr_t( 'fm_new' ) ) . '</a></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	if ( '' === $q && ! $board ) {
		// ---- the boards
		$boards = get_terms( array( 'taxonomy' => 'mcupr_board', 'hide_empty' => false ) );
		echo '<h2 class="forum-h">' . esc_html( mcupr_t( 'fm_boards' ) ) . '</h2><ul class="forum-boards">';
		foreach ( is_wp_error( $boards ) ? array() : $boards as $b ) {
			$last = mcupr_forum_board_last( $b->term_id );
			$last = $last ? array( $last ) : array();
			echo '<li class="forum-board"><a class="forum-bn" href="' . esc_url( add_query_arg( 'fb', $b->slug, $base ) ) . '">' . mcupr_icon( 'chat', 26 ) . '<span><b>' . esc_html( $b->name ) . '</b>' . ( $b->description ? '<small>' . esc_html( $b->description ) . '</small>' : '' ) . '</span></a><div class="forum-bs"><span><b>' . (int) $b->count . '</b> ' . esc_html( mcupr_t( 'fm_topics' ) ) . '</span>' . ( $last ? '<a href="' . esc_url( get_permalink( $last[0] ) ) . '">' . esc_html( wp_trim_words( get_the_title( $last[0] ), 8, '…' ) ) . '</a>' : '' ) . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
		$latest = get_posts( array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'posts_per_page' => 8, 'no_found_rows' => true, 'meta_key' => '_mcupr_topic_last', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $latest ) {
			cache_users( array_filter( array_map( 'intval', wp_list_pluck( $latest, 'post_author' ) ) ) ); // one query for the names of all rows
			echo '<h2 class="forum-h">' . esc_html( mcupr_t( 'fm_last' ) ) . '</h2><ul class="forum-list">';
			foreach ( $latest as $p ) {
				echo mcupr_forum_row( $p, (bool) get_post_meta( $p->ID, '_mcupr_topic_sticky', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		} else {
			echo '<p class="text-muted">' . esc_html( mcupr_t( 'fm_no_topics' ) ) . '</p>';
		}
	} else {
		// ---- one board, or search results
		$args = array( 'post_type' => 'mcupr_topic', 'post_status' => 'publish', 'posts_per_page' => $o['per_page'], 'paged' => $page, 'meta_key' => '_mcupr_topic_last', 'orderby' => 'meta_value_num', 'order' => 'DESC' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $board ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'mcupr_board', 'terms' => $board->term_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( '' !== $q ) {
			$args['s'] = $q;
		}
		echo '<nav class="forum-crumb" aria-label="' . esc_attr( mcupr_t( 'fm_title' ) ) . '"><a href="' . esc_url( $base ) . '">' . esc_html( mcupr_t( 'fm_all' ) ) . '</a>' . ( $board ? ' › <span>' . esc_html( $board->name ) . '</span>' : '' ) . ( '' !== $q ? ' › <span>' . esc_html( mcupr_t( 'fm_results' ) ) . ': ' . esc_html( $q ) . '</span>' : '' ) . '</nav>';
		if ( $board && $board->description ) {
			echo '<p class="forum-desc">' . esc_html( $board->description ) . '</p>';
		}
		if ( 1 === $page ) {
			$pin = get_posts( array_merge( $args, array( 'posts_per_page' => 20, 'paged' => 1, 'meta_key' => '_mcupr_topic_sticky', 'meta_value' => 1, 'orderby' => 'date' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$pinned_ids = wp_list_pluck( $pin, 'ID' );
		} else {
			$pin = array();
			$pinned_ids = get_posts( array_merge( $args, array( 'posts_per_page' => 20, 'paged' => 1, 'meta_key' => '_mcupr_topic_sticky', 'meta_value' => 1, 'fields' => 'ids' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$args['post__not_in'] = $pinned_ids;
		$qq = new WP_Query( $args );
		cache_users( array_filter( array_map( 'intval', array_merge( wp_list_pluck( $pin, 'post_author' ), wp_list_pluck( $qq->posts, 'post_author' ) ) ) ) );
		echo '<ul class="forum-list">';
		foreach ( $pin as $p ) {
			echo mcupr_forum_row( $p, true ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		foreach ( $qq->posts as $p ) {
			echo mcupr_forum_row( $p ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
		if ( ! $pin && ! $qq->have_posts() ) {
			echo '<p class="text-muted">' . esc_html( mcupr_t( 'fm_no_topics' ) ) . '</p>';
		}
		if ( $qq->max_num_pages > 1 ) {
			echo '<nav class="forum-pages" aria-label="' . esc_attr( mcupr_t( 'fm_topics' ) ) . '">' . paginate_links( array( 'base' => add_query_arg( 'fp', '%#%', $base ), 'format' => '', 'current' => $page, 'total' => $qq->max_num_pages, 'prev_text' => esc_html( mcupr_t( 'fm_prev' ) ), 'next_text' => esc_html( mcupr_t( 'fm_next' ) ), 'add_args' => array_filter( array( 'fb' => $slug, 'fq' => $q ) ) ) ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
	echo '<h2 class="forum-h" id="fm-new">' . esc_html( mcupr_t( 'fm_new' ) ) . '</h2>' . mcupr_forum_form( $board ? $board->term_id : 0 ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}
add_shortcode( 'mcupr_forum', 'mcupr_forum_html' );

/** The replies of a topic as HTML (approved ones only). */
function mcupr_forum_replies_html( $topic_id ) {
	$cs = get_comments( array( 'post_id' => $topic_id, 'status' => 'approve', 'order' => 'ASC', 'number' => 500 ) );
	if ( ! $cs ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'fm_no_replies' ) ) . '</p>';
	}
	$back = get_permalink( $topic_id );
	$o    = '<ol class="forum-replies">';
	foreach ( $cs as $c ) {
		$staff = $c->user_id && user_can( (int) $c->user_id, 'edit_pages' );
		$o    .= '<li class="forum-reply' . ( $staff ? ' is-staff' : '' ) . '" id="reply-' . (int) $c->comment_ID . '"><span class="forum-av" aria-hidden="true">' . esc_html( mcupr_forum_initial( $c->comment_author ) ) . '</span><div class="forum-rb"><p class="forum-rh"><b>' . esc_html( $c->comment_author ) . '</b>' . ( $staff ? ' <span class="forum-badge">' . esc_html( mcupr_t( 'fm_staff' ) ) . '</span>' : '' ) . ' <time datetime="' . esc_attr( mysql2date( 'c', $c->comment_date_gmt ) ) . '">' . esc_html( mcupr_date_fmt( 'j M Y H:i', (int) strtotime( $c->comment_date_gmt . ' +0000' ) ) ) . '</time></p><div class="forum-tx">' . mcupr_forum_text( $c->comment_content ) . '</div>' . mcupr_forum_report_button( 'reply', $c->comment_ID, $back ) . '</div></li>';
	}
	return $o . '</ol>';
}
