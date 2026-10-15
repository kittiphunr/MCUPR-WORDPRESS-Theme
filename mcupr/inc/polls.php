<?php
/**
 * Polls and satisfaction surveys (แบบสำรวจความพึงพอใจ): post type "mcupr_poll" – a question, its choices, single or
 * multiple choice or the five-level satisfaction scale, an optional closing date and a rule for when the results are
 * shown. Place one with [mcupr_poll id="ID"]. One vote per visitor per poll (a cookie and a hashed address kept
 * for 30 days; nothing else is stored), trap field and signed time token against robots. The votes are counts only –
 * no vote is linked to a person.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_poll_register() {
	register_post_type(
		'mcupr_poll',
		array(
			'labels'        => array(
				'name'          => __( 'Polls and surveys', 'mcupr' ),
				'singular_name' => __( 'Poll', 'mcupr' ),
				'menu_name'     => __( 'Polls and surveys', 'mcupr' ),
				'all_items'     => __( 'All polls', 'mcupr' ),
				'add_new_item'  => __( 'Add a poll', 'mcupr' ),
				'edit_item'     => __( 'Edit poll', 'mcupr' ),
				'not_found'     => __( 'No polls yet', 'mcupr' ),
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-chart-bar',
			'menu_position' => 13,
			'supports'      => array( 'title' ),
		)
	);
}
add_action( 'init', 'mcupr_poll_register' );

function mcupr_poll_types() {
	return array(
		'choice'       => __( 'One choice', 'mcupr' ),
		'multi'        => __( 'Several choices', 'mcupr' ),
		'satisfaction' => __( 'Satisfaction: five levels', 'mcupr' ),
	);
}

/** The choices (lines of the text box, or the five satisfaction levels in the visitor's language). */
function mcupr_poll_options( $id ) {
	$type = (string) get_post_meta( $id, '_mcupr_poll_type', true );
	if ( 'satisfaction' === $type ) {
		return array( mcupr_t( 'pl_s5' ), mcupr_t( 'pl_s4' ), mcupr_t( 'pl_s3' ), mcupr_t( 'pl_s2' ), mcupr_t( 'pl_s1' ) );
	}
	$lines = preg_split( '/\R/', (string) get_post_meta( $id, '_mcupr_poll_opts', true ) );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}

function mcupr_poll_votes( $id ) {
	$v = get_post_meta( $id, '_mcupr_poll_votes', true );
	return is_array( $v ) ? array_map( 'intval', $v ) : array();
}

function mcupr_poll_is_closed( $id ) {
	$c = (string) get_post_meta( $id, '_mcupr_poll_close', true );
	return '' !== $c && strtotime( $c . ' 23:59:59' ) < current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
}

/* ------------------------------------------------------------------ admin */

add_action(
	'add_meta_boxes_mcupr_poll',
	static function () {
		add_meta_box( 'mcupr_poll_box', __( 'Poll', 'mcupr' ), 'mcupr_poll_box', 'mcupr_poll', 'normal', 'high' );
		add_meta_box( 'mcupr_poll_res', __( 'Results', 'mcupr' ), 'mcupr_poll_results_box', 'mcupr_poll', 'side', 'default' );
	}
);

function mcupr_poll_box( $post ) {
	wp_nonce_field( 'mcupr_poll_save', 'mcupr_poll_nonce' );
	$type = (string) get_post_meta( $post->ID, '_mcupr_poll_type', true );
	echo '<p class="description">' . esc_html__( 'The title above is the question. Show the poll on a page with the shortcode below.', 'mcupr' ) . '</p>';
	if ( 'auto-draft' !== $post->post_status ) {
		echo '<p><code>[mcupr_poll id="' . (int) $post->ID . '"]</code></p>';
	}
	echo '<p><label><strong>' . esc_html__( 'Type', 'mcupr' ) . '</strong><br><select name="mcupr_poll[type]">';
	foreach ( mcupr_poll_types() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $type ? $type : 'choice', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label><strong>' . esc_html__( 'Choices (one per line; not used for the satisfaction scale)', 'mcupr' ) . '</strong><br><textarea class="widefat" rows="6" name="mcupr_poll[opts]">' . esc_textarea( (string) get_post_meta( $post->ID, '_mcupr_poll_opts', true ) ) . '</textarea></label></p>';
	echo '<p><label><strong>' . esc_html__( 'Show the results', 'mcupr' ) . '</strong><br><select name="mcupr_poll[results]">';
	foreach ( array( 'after' => __( 'After the visitor has voted (and when the poll is closed)', 'mcupr' ), 'always' => __( 'Always', 'mcupr' ), 'never' => __( 'Never on the website (only here)', 'mcupr' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) get_post_meta( $post->ID, '_mcupr_poll_results', true ) ? (string) get_post_meta( $post->ID, '_mcupr_poll_results', true ) : 'after', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label></p>';
	echo '<p><label><strong>' . esc_html__( 'Closes on (optional)', 'mcupr' ) . '</strong><br><input type="date" name="mcupr_poll[close]" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_poll_close', true ) ) . '"></label></p>';
	echo '<p><label><input type="checkbox" name="mcupr_poll[reset]" value="1"> ' . esc_html__( 'Delete all votes (cannot be undone)', 'mcupr' ) . '</label></p>';
}

function mcupr_poll_results_box( $post ) {
	$opts  = mcupr_poll_options( $post->ID );
	$votes = mcupr_poll_votes( $post->ID );
	$total = array_sum( $votes );
	echo '<p><strong>' . esc_html( sprintf( /* translators: %d: votes */ __( 'Votes: %d', 'mcupr' ), $total ) ) . '</strong></p><ol>';
	foreach ( $opts as $i => $o ) {
		$n = isset( $votes[ $i ] ) ? $votes[ $i ] : 0;
		echo '<li>' . esc_html( $o ) . ' — ' . (int) $n . ( $total ? ' (' . round( $n * 100 / $total ) . '%)' : '' ) . '</li>';
	}
	echo '</ol>';
}

add_action(
	'save_post_mcupr_poll',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_poll_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_poll_nonce'] ) ), 'mcupr_poll_save' ) || ! current_user_can( 'edit_post', $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		$in   = isset( $_POST['mcupr_poll'] ) && is_array( $_POST['mcupr_poll'] ) ? wp_unslash( $_POST['mcupr_poll'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$type = isset( $in['type'], mcupr_poll_types()[ $in['type'] ] ) ? $in['type'] : 'choice';
		update_post_meta( $post_id, '_mcupr_poll_type', $type );
		$lines = array_slice( array_filter( array_map( static function ( $l ) { return mb_substr( sanitize_text_field( $l ), 0, 150 ); }, preg_split( '/\R/', isset( $in['opts'] ) ? (string) $in['opts'] : '' ) ), 'strlen' ), 0, 20 );
		$old   = (string) get_post_meta( $post_id, '_mcupr_poll_opts', true );
		update_post_meta( $post_id, '_mcupr_poll_opts', implode( "\n", $lines ) );
		update_post_meta( $post_id, '_mcupr_poll_results', isset( $in['results'] ) && in_array( $in['results'], array( 'after', 'always', 'never' ), true ) ? $in['results'] : 'after' );
		$close = isset( $in['close'] ) ? (string) $in['close'] : '';
		update_post_meta( $post_id, '_mcupr_poll_close', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $close ) ? $close : '' );
		if ( ! empty( $in['reset'] ) || implode( "\n", $lines ) !== $old && '' !== $old ) {
			delete_post_meta( $post_id, '_mcupr_poll_votes' ); // changing the choices would mix up the old counts
		}
	}
);

add_filter(
	'manage_mcupr_poll_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_votes'] = __( 'Votes', 'mcupr' );
		$cols['mcupr_code']  = __( 'Shortcode', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_poll_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_votes' === $col ) {
			echo (int) array_sum( mcupr_poll_votes( $id ) ) . ( mcupr_poll_is_closed( $id ) ? ' · ' . esc_html__( 'closed', 'mcupr' ) : '' );
		}
		if ( 'mcupr_code' === $col ) {
			echo '<code>[mcupr_poll id="' . (int) $id . '"]</code>';
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ voting */

function mcupr_poll_voter_key( $id ) {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return 'mcupr_pv_' . (int) $id . '_' . substr( hash_hmac( 'sha256', $ip . '|' . (int) $id, wp_salt( 'auth' ) ), 0, 16 );
}

function mcupr_poll_has_voted( $id ) {
	return isset( $_COOKIE[ 'mcupr_pv_' . (int) $id ] ) || (bool) get_transient( mcupr_poll_voter_key( $id ) );
}

/**
 * Record a vote. $choices: array of option indexes. Returns 'ok' | 'closed' | 'again' | 'spam' | 'none' | 'bad'.
 */
function mcupr_poll_vote( $id, $choices, $data = array(), $now = 0 ) {
	$now = $now ? $now : time();
	$id  = (int) $id;
	if ( 'mcupr_poll' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
		return 'bad';
	}
	$tk  = isset( $data['tk'] ) ? (string) $data['tk'] : '';
	$tok = explode( '.', $tk );
	$ts  = isset( $tok[0] ) ? (int) $tok[0] : 0;
	if ( ! empty( $data['website'] ) || ! hash_equals( mcupr_form_token( $ts ), $tk ) || $now - $ts < 2 || $now - $ts > 6 * HOUR_IN_SECONDS ) {
		return 'spam';
	}
	if ( mcupr_poll_is_closed( $id ) ) {
		return 'closed';
	}
	if ( mcupr_poll_has_voted( $id ) ) {
		return 'again';
	}
	$opts = mcupr_poll_options( $id );
	$type = (string) get_post_meta( $id, '_mcupr_poll_type', true );
	$ch   = array_values( array_unique( array_filter( array_map( 'intval', (array) $choices ), static function ( $c ) use ( $opts ) { return isset( $opts[ $c ] ); } ) ) );
	if ( ! $ch ) {
		return 'none';
	}
	if ( 'multi' !== $type ) {
		$ch = array( $ch[0] );
	}
	$votes = mcupr_poll_votes( $id );
	foreach ( $ch as $c ) {
		$votes[ $c ] = ( isset( $votes[ $c ] ) ? $votes[ $c ] : 0 ) + 1;
	}
	update_post_meta( $id, '_mcupr_poll_votes', $votes );
	set_transient( mcupr_poll_voter_key( $id ), 1, 30 * DAY_IN_SECONDS );
	return 'ok';
}

function mcupr_poll_handler() {
	$id   = isset( $_POST['poll'] ) ? (int) $_POST['poll'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ) ), home_url( '/' ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mcupr_poll' ) ) {
		$r = 'spam';
	} else {
		$ch = isset( $_POST['choice'] ) ? (array) wp_unslash( $_POST['choice'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$r  = mcupr_poll_vote( $id, $ch, array( 'tk' => isset( $_POST['tk'] ) ? sanitize_text_field( wp_unslash( $_POST['tk'] ) ) : '', 'website' => isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '' ) );
	}
	if ( 'ok' === $r ) {
		setcookie( 'mcupr_pv_' . $id, '1', array( 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'samesite' => 'Lax' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	wp_safe_redirect( add_query_arg( array( 'pv' => $r, 'pid' => $id ), remove_query_arg( array( 'pv', 'pid' ), $back ) ) . '#poll-' . $id );
	exit;
}
add_action( 'admin_post_nopriv_mcupr_poll', 'mcupr_poll_handler' );
add_action( 'admin_post_mcupr_poll', 'mcupr_poll_handler' );

/* ------------------------------------------------------------------ display */

function mcupr_poll_results_html( $id ) {
	$opts  = mcupr_poll_options( $id );
	$votes = mcupr_poll_votes( $id );
	$total = array_sum( $votes );
	$type  = (string) get_post_meta( $id, '_mcupr_poll_type', true );
	$base  = 'multi' === $type ? max( 1, (int) max( $votes ? $votes : array( 1 ) ) ) : max( 1, $total );
	$o     = '<ol class="poll-res">';
	foreach ( $opts as $i => $label ) {
		$n   = isset( $votes[ $i ] ) ? $votes[ $i ] : 0;
		$pct = $total ? ( 'multi' === $type ? round( $n * 100 / $base ) : round( $n * 100 / $total ) ) : 0;
		$o  .= '<li><div class="poll-rl"><span>' . esc_html( $label ) . '</span><b>' . (int) $n . ' · ' . (int) round( $total ? $n * 100 / max( 1, 'multi' === $type ? array_sum( $votes ) : $total ) : 0 ) . '%</b></div><div class="poll-bar" role="img" aria-label="' . esc_attr( $label . ': ' . $n ) . '"><span style="width:' . (int) $pct . '%"></span></div></li>';
	}
	return $o . '</ol><p class="poll-tot">' . esc_html( sprintf( mcupr_t( 'pl_total' ), $total ) ) . '</p>';
}

/** [mcupr_poll id="12"] */
add_shortcode(
	'mcupr_poll',
	static function ( $atts ) {
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'mcupr_poll' );
		$id = (int) $a['id'];
		if ( ! $id ) {
			$p  = get_posts( array( 'post_type' => 'mcupr_poll', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true ) );
			$id = $p ? $p[0]->ID : 0;
		}
		if ( ! $id || 'mcupr_poll' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$opts   = mcupr_poll_options( $id );
		$type   = (string) get_post_meta( $id, '_mcupr_poll_type', true );
		$show   = (string) get_post_meta( $id, '_mcupr_poll_results', true );
		$closed = mcupr_poll_is_closed( $id );
		$voted  = mcupr_poll_has_voted( $id );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['pv'], $_GET['pid'] ) && (int) $_GET['pid'] === $id ? sanitize_key( wp_unslash( $_GET['pv'] ) ) : '';
		// phpcs:enable
		$can_see = 'always' === $show || ( 'never' !== $show && ( $voted || $closed ) );
		$q       = get_the_title( $id );
		ob_start();
		echo '<section class="poll" id="poll-' . (int) $id . '" aria-labelledby="poll-q-' . (int) $id . '"><h2 class="poll-q" id="poll-q-' . (int) $id . '">' . esc_html( $q ) . '</h2>';
		if ( 'ok' === $state ) {
			echo '<div class="mform-ok" role="status"><p><b>' . esc_html( mcupr_t( 'pl_thanks' ) ) . '</b></p></div>';
		} elseif ( in_array( $state, array( 'again', 'closed', 'none', 'spam', 'bad' ), true ) ) {
			$m = array( 'again' => 'pl_again', 'closed' => 'pl_closed', 'none' => 'pl_none', 'spam' => 'form_err_spam', 'bad' => 'form_err_spam' );
			echo '<div class="mform-err" role="alert"><p><b>' . esc_html( mcupr_t( $m[ $state ] ) ) . '</b></p></div>';
		}
		if ( ! $closed && ! $voted && $opts ) {
			echo '<form class="poll-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_poll"><input type="hidden" name="poll" value="' . (int) $id . '"><input type="hidden" name="back" value="' . esc_url( remove_query_arg( array( 'pv', 'pid' ), is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' ) ) ) . '">';
			wp_nonce_field( 'mcupr_poll', '_wpnonce', true, true );
			echo '<input type="hidden" name="tk" value="' . esc_attr( mcupr_form_token( time() ) ) . '"><div class="mform-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
			echo '<fieldset class="poll-opts' . ( 'satisfaction' === $type ? ' is-scale' : '' ) . '"><legend class="sr-only">' . esc_html( $q ) . '</legend>';
			foreach ( $opts as $i => $label ) {
				echo '<label class="poll-o"><input type="' . ( 'multi' === $type ? 'checkbox' : 'radio' ) . '" name="choice[]" value="' . (int) $i . '"><span>' . esc_html( $label ) . '</span></label>';
			}
			echo '</fieldset><p><button type="submit" class="btn">' . esc_html( mcupr_t( 'pl_vote' ) ) . '</button></p></form>';
		} elseif ( $closed ) {
			echo '<p class="poll-note">' . esc_html( mcupr_t( 'pl_closed' ) ) . '</p>';
		} elseif ( $voted && 'ok' !== $state ) {
			echo '<p class="poll-note">' . esc_html( mcupr_t( 'pl_again' ) ) . '</p>';
		}
		if ( $can_see ) {
			echo mcupr_poll_results_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</section>';
		return ob_get_clean();
	}
);
