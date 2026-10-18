<?php
/**
 * Form builder (แบบฟอร์ม): make your own forms – for asking staff or the public – without a plugin.
 *
 *  - "Forms" (post type mcupr_fb): the fields (short text, long text, e-mail, telephone, number, date, drop-down, one
 *    choice, several choices, agreement, rating, section heading, information text), required or not, help texts, and the
 *    settings (intro, thank-you text, e-mail notices, who may answer, open / close dates, limit, how long answers are kept).
 *  - "Form answers" (post type mcupr_fe): what was sent, readable in the admin and downloadable as CSV (opens in Excel).
 *  - Show a form with [mcupr_form id="12"] or [mcupr_form slug="staff-training"].
 *  - Safe by design: nonce, trap field, signed time token, rate limit per visitor (only a hash kept), no IP address stored,
 *    server-side checks of every field, answers kept only as long as you say.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_fb_register() {
	register_post_type(
		'mcupr_fb',
		array(
			'labels'          => array(
				'name'          => __( 'Forms', 'mcupr' ),
				'singular_name' => __( 'Form', 'mcupr' ),
				'menu_name'     => __( 'Forms', 'mcupr' ),
				'all_items'     => __( 'All forms', 'mcupr' ),
				'add_new_item'  => __( 'Create a form', 'mcupr' ),
				'edit_item'     => __( 'Edit form', 'mcupr' ),
				'not_found'     => __( 'No forms yet', 'mcupr' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'capability_type' => 'page',
			'supports'        => array( 'title' ),
		)
	);
	register_post_type(
		'mcupr_fe',
		array(
			'labels'          => array(
				'name'          => __( 'Form answers', 'mcupr' ),
				'singular_name' => __( 'Answer', 'mcupr' ),
				'menu_name'     => __( 'Form answers', 'mcupr' ),
				'all_items'     => __( 'All answers', 'mcupr' ),
				'edit_item'     => __( 'Answer', 'mcupr' ),
				'not_found'     => __( 'No answers yet', 'mcupr' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'capability_type' => 'page',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'supports'        => array( 'title' ),
		)
	);
}
add_action( 'init', 'mcupr_fb_register' );

function mcupr_fb_types() {
	return array(
		'text'     => __( 'Short text', 'mcupr' ),
		'textarea' => __( 'Long text', 'mcupr' ),
		'email'    => __( 'E-mail address', 'mcupr' ),
		'tel'      => __( 'Telephone', 'mcupr' ),
		'number'   => __( 'Number', 'mcupr' ),
		'date'     => __( 'Date', 'mcupr' ),
		'select'   => __( 'Drop-down list', 'mcupr' ),
		'radio'    => __( 'One choice', 'mcupr' ),
		'checkbox' => __( 'Several choices', 'mcupr' ),
		'consent'  => __( 'Agreement (one box)', 'mcupr' ),
		'rating'   => __( 'Rating 1–5', 'mcupr' ),
		'file'     => __( 'File upload (PDF, JPG, PNG)', 'mcupr' ),
		'heading'  => __( 'Section heading', 'mcupr' ),
		'note'     => __( 'Information text', 'mcupr' ),
	);
}

/* ------------------------------------------------------------------ data */

function mcupr_fb_clean_fields( $raw ) {
	$list = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	$list = is_array( $list ) ? array_slice( $list, 0, 60 ) : array();
	$out  = array();
	$used = array();
	$n    = 0;
	foreach ( $list as $f ) {
		if ( ! is_array( $f ) || empty( $f['type'] ) || ! isset( mcupr_fb_types()[ $f['type'] ] ) ) {
			continue;
		}
		$n++;
		$id = isset( $f['id'] ) ? preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $f['id'] ) ) : '';
		if ( '' === $id || isset( $used[ $id ] ) ) {
			$id = 'f' . $n;
			while ( isset( $used[ $id ] ) ) {
				$id .= 'x';
			}
		}
		$used[ $id ] = 1;
		$opts        = array();
		if ( in_array( $f['type'], array( 'select', 'radio', 'checkbox' ), true ) ) {
			$lines = is_array( $f['options'] ?? null ) ? $f['options'] : preg_split( '/\R/', (string) ( $f['options'] ?? '' ) );
			foreach ( array_slice( $lines, 0, 30 ) as $l ) {
				$l = mb_substr( sanitize_text_field( (string) $l ), 0, 100 );
				if ( '' !== $l ) {
					$opts[] = $l;
				}
			}
		}
		$out[] = array(
			'id'          => $id,
			'type'        => $f['type'],
			'label'       => mb_substr( sanitize_text_field( (string) ( $f['label'] ?? '' ) ), 0, 200 ),
			'help'        => mb_substr( sanitize_textarea_field( (string) ( $f['help'] ?? '' ) ), 0, 600 ),
			'placeholder' => mb_substr( sanitize_text_field( (string) ( $f['placeholder'] ?? '' ) ), 0, 100 ),
			'required'    => ! empty( $f['required'] ) && ! in_array( $f['type'], array( 'heading', 'note' ), true ) ? 1 : 0,
			'options'     => $opts,
		);
	}
	return $out;
}

function mcupr_fb_fields( $form_id ) {
	$f = get_post_meta( $form_id, '_mcupr_fb_fields', true ); // an array, or JSON text (the starter file)
	return mcupr_fb_clean_fields( $f );
}

function mcupr_fb_defaults() {
	return array( 'intro' => '', 'submit' => '', 'success' => '', 'notify' => '', 'copy' => 0, 'who' => 'all', 'from' => '', 'to' => '', 'limit' => 0, 'keep' => 0, 'closed' => '' );
}

function mcupr_fb_opts( $form_id ) {
	$o = get_post_meta( $form_id, '_mcupr_fb_opts', true );
	return array_merge( mcupr_fb_defaults(), is_array( $o ) ? $o : array() );
}

function mcupr_fb_clean_opts( $p ) {
	$d    = static function ( $k ) use ( $p ) {
		$v = isset( $p[ $k ] ) ? (string) $p[ $k ] : '';
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $v : '';
	};
	$mail = array();
	foreach ( explode( ',', isset( $p['notify'] ) ? (string) $p['notify'] : '' ) as $e ) {
		$e = sanitize_email( trim( $e ) );
		if ( is_email( $e ) ) {
			$mail[] = $e;
		}
	}
	return array(
		'intro'   => mb_substr( sanitize_textarea_field( isset( $p['intro'] ) ? (string) $p['intro'] : '' ), 0, 2000 ),
		'submit'  => mb_substr( sanitize_text_field( isset( $p['submit'] ) ? (string) $p['submit'] : '' ), 0, 40 ),
		'success' => mb_substr( sanitize_textarea_field( isset( $p['success'] ) ? (string) $p['success'] : '' ), 0, 600 ),
		'closed'  => mb_substr( sanitize_textarea_field( isset( $p['closed'] ) ? (string) $p['closed'] : '' ), 0, 400 ),
		'notify'  => implode( ', ', array_unique( $mail ) ),
		'copy'    => ! empty( $p['copy'] ) ? 1 : 0,
		'who'     => isset( $p['who'] ) && 'users' === $p['who'] ? 'users' : 'all',
		'from'    => $d( 'from' ),
		'to'      => $d( 'to' ),
		'limit'   => isset( $p['limit'] ) ? max( 0, min( 100000, (int) $p['limit'] ) ) : 0,
		'keep'    => isset( $p['keep'] ) ? max( 0, min( 3650, (int) $p['keep'] ) ) : 0,
	);
}

/* ------------------------------------------------------------------ admin: the builder */

add_action(
	'add_meta_boxes_mcupr_fb',
	static function () {
		add_meta_box( 'mcupr_fb_fields', __( 'Fields of the form', 'mcupr' ), 'mcupr_fb_fields_box', 'mcupr_fb', 'normal', 'high' );
		add_meta_box( 'mcupr_fb_opts', __( 'Settings', 'mcupr' ), 'mcupr_fb_opts_box', 'mcupr_fb', 'normal', 'default' );
		add_meta_box( 'mcupr_fb_side', __( 'Use this form', 'mcupr' ), 'mcupr_fb_side_box', 'mcupr_fb', 'side', 'high' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		$s = get_current_screen();
		if ( $s && 'mcupr_fb' === $s->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script( 'mcupr-fb-admin', MCUPR_URI . '/assets/js/formbuilder-admin.js', array(), MCUPR_VERSION, true );
			wp_localize_script( 'mcupr-fb-admin', 'mcuprFb', array( 'types' => mcupr_fb_types(), 'i18n' => array(
				'label' => __( 'Question / label', 'mcupr' ), 'help' => __( 'Help text (optional)', 'mcupr' ), 'ph' => __( 'Example shown in the box (optional)', 'mcupr' ), 'req' => __( 'Required', 'mcupr' ),
				'opts' => __( 'Choices (one per line)', 'mcupr' ), 'up' => __( 'Move up', 'mcupr' ), 'down' => __( 'Move down', 'mcupr' ), 'del' => __( 'Remove this field', 'mcupr' ),
				'add' => __( 'Add a field:', 'mcupr' ), 'empty' => __( 'No fields yet. Add the first one below.', 'mcupr' ), 'text' => __( 'Text to show', 'mcupr' ), 'ok' => __( 'Remove this field?', 'mcupr' ),
			) ) );
		}
	}
);

function mcupr_fb_fields_box( $post ) {
	wp_nonce_field( 'mcupr_fb_save', 'mcupr_fb_nonce' );
	echo '<p class="description">' . esc_html__( 'Add the questions in the order they should appear. Use the arrows to move a field. The form works on phones too.', 'mcupr' ) . '</p>';
	echo '<input type="hidden" id="mcupr-fb-json" name="mcupr_fb_json" value="' . esc_attr( wp_json_encode( mcupr_fb_fields( $post->ID ) ) ) . '"><div id="mcupr-fb-builder"></div>';
}

function mcupr_fb_opts_box( $post ) {
	$o = mcupr_fb_opts( $post->ID );
	echo '<table class="form-table" role="presentation"><tbody>';
	$row = static function ( $label, $html ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	};
	$row( __( 'Text above the form', 'mcupr' ), '<textarea name="mcupr_fb[intro]" rows="3" class="large-text">' . esc_textarea( $o['intro'] ) . '</textarea>' );
	$row( __( 'Send button text', 'mcupr' ), '<input type="text" name="mcupr_fb[submit]" class="regular-text" value="' . esc_attr( $o['submit'] ) . '" placeholder="' . esc_attr__( 'Send', 'mcupr' ) . '">' );
	$row( __( 'Thank-you message', 'mcupr' ), '<textarea name="mcupr_fb[success]" rows="2" class="large-text" placeholder="' . esc_attr__( 'Thank you. Your answers were received.', 'mcupr' ) . '">' . esc_textarea( $o['success'] ) . '</textarea>' );
	$row( __( 'Who can answer', 'mcupr' ), '<select name="mcupr_fb[who]"><option value="all"' . selected( $o['who'], 'all', false ) . '>' . esc_html__( 'Everyone', 'mcupr' ) . '</option><option value="users"' . selected( $o['who'], 'users', false ) . '>' . esc_html__( 'Only people logged in to this website (internal)', 'mcupr' ) . '</option></select>' );
	$row( __( 'Open from / until', 'mcupr' ), '<input type="date" name="mcupr_fb[from]" value="' . esc_attr( $o['from'] ) . '"> – <input type="date" name="mcupr_fb[to]" value="' . esc_attr( $o['to'] ) . '"> <span class="description">' . esc_html__( 'Optional. Outside these days the form is closed.', 'mcupr' ) . '</span>' );
	$row( __( 'Closed message', 'mcupr' ), '<textarea name="mcupr_fb[closed]" rows="2" class="large-text" placeholder="' . esc_attr__( 'This form is closed.', 'mcupr' ) . '">' . esc_textarea( $o['closed'] ) . '</textarea>' );
	$row( __( 'At most this many answers', 'mcupr' ), '<input type="number" min="0" name="mcupr_fb[limit]" value="' . (int) $o['limit'] . '"> <span class="description">' . esc_html__( '0 = no limit. The form closes when it is reached.', 'mcupr' ) . '</span>' );
	$row( __( 'Tell by e-mail', 'mcupr' ), '<input type="text" name="mcupr_fb[notify]" class="regular-text" value="' . esc_attr( $o['notify'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '"> <span class="description">' . esc_html__( 'Addresses separated by commas; empty = the site administrator.', 'mcupr' ) . '</span><br><label><input type="checkbox" name="mcupr_fb[copy]" value="1"' . checked( ! empty( $o['copy'] ), true, false ) . '> ' . esc_html__( 'Send a copy of the answers to the person who answered (if the form asks for an e-mail address)', 'mcupr' ) . '</label>' );
	$row( __( 'Keep the answers', 'mcupr' ), '<input type="number" min="0" max="3650" name="mcupr_fb[keep]" value="' . (int) $o['keep'] . '"> <span class="description">' . esc_html__( 'days, then delete them automatically (0 = keep until you delete them). Personal data should not be kept longer than needed.', 'mcupr' ) . '</span>' );
	echo '</tbody></table>';
}

function mcupr_fb_side_box( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Save the form first; then you get the code to show it.', 'mcupr' ) . '</p>';
		return;
	}
	echo '<p>' . esc_html__( 'Put this on any page or post:', 'mcupr' ) . '</p><p><code>[mcupr_form id="' . (int) $post->ID . '"]</code></p>';
	$n = mcupr_fb_count( $post->ID );
	echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_fe&mcupr_form=' . (int) $post->ID ) ) . '">' . esc_html( sprintf( /* translators: %d: answers */ __( 'Answers: %d', 'mcupr' ), $n ) ) . '</a> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_fb_export&form=' . (int) $post->ID ), 'mcupr_fb_export' ) ) . '">' . esc_html__( 'Download CSV (Excel)', 'mcupr' ) . '</a></p>';
}

add_action(
	'save_post_mcupr_fb',
	static function ( $post_id ) {
		if ( ! mcupr_can_save_box( $post_id, 'mcupr_fb_nonce', 'mcupr_fb_save' ) ) {
			return;
		}
		$json = isset( $_POST['mcupr_fb_json'] ) ? wp_unslash( $_POST['mcupr_fb_json'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_post_meta( $post_id, '_mcupr_fb_fields', mcupr_fb_clean_fields( $json ) ); // an array (not JSON text: WordPress strips the backslashes of \\u0e01 when it saves meta)
		$in = isset( $_POST['mcupr_fb'] ) && is_array( $_POST['mcupr_fb'] ) ? wp_unslash( $_POST['mcupr_fb'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		update_post_meta( $post_id, '_mcupr_fb_opts', mcupr_fb_clean_opts( $in ) );
	}
);

function mcupr_fb_count( $form_id ) {
	return (int) ( new WP_Query( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'meta_key' => '_mcupr_fe_form', 'meta_value' => (int) $form_id, 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery
}

add_filter(
	'manage_mcupr_fb_posts_columns',
	static function ( $c ) {
		return array( 'cb' => $c['cb'], 'title' => $c['title'], 'mcupr_fb_code' => __( 'Shortcode', 'mcupr' ), 'mcupr_fb_n' => __( 'Answers', 'mcupr' ), 'mcupr_fb_open' => __( 'Status', 'mcupr' ), 'date' => $c['date'] );
	}
);
add_action(
	'manage_mcupr_fb_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_fb_code' === $col ) {
			echo '<code>[mcupr_form id="' . (int) $id . '"]</code>';
		} elseif ( 'mcupr_fb_n' === $col ) {
			echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_fe&mcupr_form=' . (int) $id ) ) . '">' . (int) mcupr_fb_count( $id ) . '</a>';
		} elseif ( 'mcupr_fb_open' === $col ) {
			$s = mcupr_fb_state( $id );
			echo esc_html( 'open' === $s ? __( 'Open', 'mcupr' ) : ( 'users' === $s ? __( 'Open (logged-in only)', 'mcupr' ) : __( 'Closed', 'mcupr' ) ) );
		}
	},
	10,
	2
);

/* ------------------------------------------------------------------ open / closed */

/** 'open' | 'users' (open, needs a login) | 'closed' */
function mcupr_fb_state( $form_id ) {
	$o = mcupr_fb_opts( $form_id );
	if ( 'publish' !== get_post_status( $form_id ) || 'mcupr_fb' !== get_post_type( $form_id ) ) {
		return 'closed';
	}
	$t = current_time( 'Y-m-d' );
	if ( ( '' !== $o['from'] && $t < $o['from'] ) || ( '' !== $o['to'] && $t > $o['to'] ) ) {
		return 'closed';
	}
	if ( (int) $o['limit'] > 0 && mcupr_fb_count( $form_id ) >= (int) $o['limit'] ) {
		return 'closed';
	}
	return 'users' === $o['who'] ? 'users' : 'open';
}

/* ------------------------------------------------------------------ validation and saving */

/** Check the submitted values against the fields. Returns array( values (id => string|array), errors (id => message key) ). */
function mcupr_fb_validate( $fields, $in ) {
	$vals = array();
	$errs = array();
	$in   = is_array( $in ) ? $in : array();
	foreach ( $fields as $f ) {
		if ( in_array( $f['type'], array( 'heading', 'note', 'file' ), true ) ) {
			continue; // a file is checked with the upload (mcupr_fb_files)
		}
		$id  = $f['id'];
		$raw = isset( $in[ $id ] ) ? $in[ $id ] : ( 'checkbox' === $f['type'] ? array() : '' );
		$req = ! empty( $f['required'] ) || 'consent' === $f['type'] && ! empty( $f['required'] );
		switch ( $f['type'] ) {
			case 'checkbox':
				$raw = is_array( $raw ) ? $raw : array();
				$v   = array();
				foreach ( $raw as $x ) {
					$x = sanitize_text_field( (string) $x );
					if ( in_array( $x, $f['options'], true ) ) {
						$v[] = $x;
					}
				}
				$v = array_values( array_unique( $v ) );
				if ( $req && ! $v ) {
					$errs[ $id ] = 'pick';
				}
				$vals[ $id ] = $v;
				break;
			case 'consent':
				$v = '1' === ( is_string( $raw ) ? $raw : '' ) ? '1' : '';
				if ( $req && '1' !== $v ) {
					$errs[ $id ] = 'agree';
				}
				$vals[ $id ] = $v;
				break;
			default:
				$s = is_string( $raw ) ? trim( 'textarea' === $f['type'] ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw ) ) : '';
				$s = mb_substr( $s, 0, 'textarea' === $f['type'] ? 5000 : 300 );
				if ( '' === $s ) {
					if ( $req ) {
						$errs[ $id ] = 'required';
					}
				} elseif ( 'email' === $f['type'] && ! is_email( $s ) ) {
					$errs[ $id ] = 'email';
				} elseif ( 'tel' === $f['type'] && ! preg_match( '/^[0-9+\-\s().#]{5,25}$/', $s ) ) {
					$errs[ $id ] = 'tel';
				} elseif ( 'number' === $f['type'] && ! is_numeric( $s ) ) {
					$errs[ $id ] = 'number';
				} elseif ( 'date' === $f['type'] && ! ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) ) {
					$errs[ $id ] = 'date';
				} elseif ( in_array( $f['type'], array( 'select', 'radio' ), true ) && ! in_array( $s, $f['options'], true ) ) {
					$errs[ $id ] = 'pick';
				} elseif ( 'rating' === $f['type'] && ! in_array( $s, array( '1', '2', '3', '4', '5' ), true ) ) {
					$errs[ $id ] = 'pick';
				}
				$vals[ $id ] = $s;
		}
	}
	return array( $vals, $errs );
}

function mcupr_fb_value_text( $v ) {
	return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
}

/**
 * Process one submission: all checks, then store and e-mail. $data = the unslashed POST.
 * Returns array( ok, errors (id => key | 'spam' | 'limit' | 'closed' | 'login'), values, entry (id) ).
 */
function mcupr_fb_submit( $form_id, $data, $now = 0, $files = null ) {
	$now    = $now ? $now : time();
	$form_id = (int) $form_id;
	$bad    = static function ( $k ) use ( $form_id ) {
		return array( 'ok' => false, 'errors' => array( $k => $k ), 'values' => array(), 'entry' => 0 );
	};
	if ( 'mcupr_fb' !== get_post_type( $form_id ) ) {
		return $bad( 'closed' );
	}
	$g   = static function ( $k ) use ( $data ) {
		return isset( $data[ $k ] ) && is_string( $data[ $k ] ) ? $data[ $k ] : '';
	};
	$tok = explode( '.', $g( 'tk' ) );
	$ts  = isset( $tok[0] ) ? (int) $tok[0] : 0;
	if ( '' !== $g( 'website' ) || ! hash_equals( mcupr_form_token( $ts ), $g( 'tk' ) ) ) {
		return $bad( 'spam' );
	}
	$state = mcupr_fb_state( $form_id );
	if ( 'closed' === $state ) {
		return $bad( 'closed' );
	}
	if ( 'users' === $state && ! is_user_logged_in() ) {
		return $bad( 'login' );
	}
	$guard = apply_filters( 'mcupr_fb_guard', '', $form_id ); // e.g. a request that only students may send
	if ( '' !== $guard ) {
		return $bad( $guard );
	}
	$fields           = mcupr_fb_fields( $form_id );
	list( $vals, $errs ) = mcupr_fb_validate( $fields, isset( $data['f'] ) ? $data['f'] : array() );
	$files            = null === $files ? ( isset( $_FILES ) && is_array( $_FILES ) ? $_FILES : array() ) : $files; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$ups              = function_exists( 'mcupr_rq_check_files' ) ? mcupr_rq_check_files( $fields, $files ) : array( array(), array() );
	$errs             = array_merge( $errs, $ups[1] );
	if ( $errs ) {
		return array( 'ok' => false, 'errors' => $errs, 'values' => $vals, 'entry' => 0 );
	}
	if ( $now - $ts < 3 || $now - $ts > 2 * HOUR_IN_SECONDS ) {
		return array( 'ok' => false, 'errors' => array( 'spam' => 'spam' ), 'values' => $vals, 'entry' => 0 );
	}
	$key = mcupr_form_visitor_key();
	if ( (int) get_transient( $key ) >= 8 ) {
		return array( 'ok' => false, 'errors' => array( 'limit' => 'limit' ), 'values' => $vals, 'entry' => 0 );
	}
	$rows = array();
	$mail = '';
	foreach ( $fields as $f ) {
		if ( in_array( $f['type'], array( 'heading', 'note' ), true ) ) {
			continue;
		}
		if ( 'file' === $f['type'] ) {
			$rows[] = array( $f['label'], isset( $ups[0][ $f['id'] ] ) ? $ups[0][ $f['id'] ]['name'] . ' (' . size_format( $ups[0][ $f['id'] ]['size'] ) . ')' : '' );
			continue;
		}
		$t = mcupr_fb_value_text( $vals[ $f['id'] ] );
		if ( 'consent' === $f['type'] ) {
			$t = '1' === $t ? __( 'Agreed', 'mcupr' ) : __( 'No', 'mcupr' );
		}
		$rows[] = array( $f['label'], $t );
		if ( 'email' === $f['type'] && '' === $mail && is_email( $vals[ $f['id'] ] ) ) {
			$mail = $vals[ $f['id'] ];
		}
	}
	$n     = mcupr_fb_count( $form_id ) + 1;
	$title = get_the_title( $form_id ) . ' #' . $n;
	$body  = '';
	foreach ( $rows as $r ) {
		$body .= $r[0] . ': ' . $r[1] . "\n";
	}
	$id = wp_insert_post( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'post_title' => $title, 'post_content' => $body ) );
	if ( ! $id || is_wp_error( $id ) ) {
		return array( 'ok' => false, 'errors' => array( 'spam' => 'spam' ), 'values' => $vals, 'entry' => 0 );
	}
	update_post_meta( $id, '_mcupr_fe_form', $form_id );
	update_post_meta( $id, '_mcupr_fe_rows', $rows ); // what was asked and answered, as it was then
	do_action( 'mcupr_fb_entry', $id, $form_id, $vals, $fields, $mail, $ups[0] ); // requests: number, status, files, the e-mail to the sender
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	$o  = mcupr_fb_opts( $form_id );
	$to = array_filter( array_map( 'trim', explode( ',', $o['notify'] ) ), 'is_email' );
	$to = $to ? $to : array( get_option( 'admin_email' ) );
	$hd = $mail ? array( 'Reply-To: ' . $mail ) : array();
	wp_mail( $to, '[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] ' . $title, $body . "\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ), $hd );
	if ( ! empty( $o['copy'] ) && $mail ) {
		$thanks = '' !== $o['success'] ? $o['success'] : __( 'Thank you. Your answers were received.', 'mcupr' );
		$own    = is_user_logged_in() && 0 === strcasecmp( (string) wp_get_current_user()->user_email, $mail ); // the full answers only to the address of the person who is signed in
		if ( mcupr_throttle( 'fb_copies', 60, HOUR_IN_SECONDS ) ) { // and never more than 60 such mails an hour from the whole site
			wp_mail( $mail, wp_specialchars_decode( get_the_title( $form_id ), ENT_QUOTES ), $own ? $thanks . "\n\n" . $body : $thanks ); // anyone else: only the thanks, never what was typed
		}
	}
	return array( 'ok' => true, 'errors' => array(), 'values' => $vals, 'entry' => $id );
}

function mcupr_fb_handler() {
	$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	$fid  = isset( $data['fb'] ) ? (int) $data['fb'] : 0;
	$back = isset( $data['back'] ) ? wp_validate_redirect( esc_url_raw( $data['back'] ), home_url( '/' ) ) : home_url( '/' );
	$anch = '#fbform-' . $fid;
	if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $data['_wpnonce'] ), 'mcupr_fb_' . $fid ) ) {
		wp_safe_redirect( add_query_arg( array( 'fb' => 'err', 'fbid' => $fid, 'fbe' => 'spam' ), $back ) . $anch );
		exit;
	}
	$r = mcupr_fb_submit( $fid, $data );
	if ( $r['ok'] ) {
		wp_safe_redirect( add_query_arg( apply_filters( 'mcupr_fb_ok_args', array( 'fb' => 'ok', 'fbid' => $fid ), $r['entry'], $fid ), $back ) . $anch );
		exit;
	}
	$k = wp_generate_password( 12, false );
	set_transient( 'mcupr_fbv_' . $k, array( 'v' => $r['values'], 'e' => $r['errors'] ), 15 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( array( 'fb' => 'err', 'fbid' => $fid, 'fbk' => $k ), $back ) . $anch );
	exit;
}
add_action( 'admin_post_nopriv_mcupr_fb', 'mcupr_fb_handler' );
add_action( 'admin_post_mcupr_fb', 'mcupr_fb_handler' );

/* ------------------------------------------------------------------ the form on the page */

function mcupr_fb_error_text( $key ) {
	$m = array( 'required' => 'fb_e_req', 'email' => 'form_err_email', 'tel' => 'fb_e_tel', 'number' => 'fb_e_num', 'date' => 'fb_e_date', 'pick' => 'fb_e_pick', 'agree' => 'form_err_consent', 'spam' => 'form_err_spam', 'file_type' => 'rq_e_type', 'file_size' => 'rq_e_size', 'file_fail' => 'rq_e_fail', 'student' => 'rq_e_student', 'limit' => 'form_err_limit', 'closed' => 'fb_closed', 'login' => 'fb_login' );
	return mcupr_t( isset( $m[ $key ] ) ? $m[ $key ] : 'form_err_spam' );
}

function mcupr_fb_html( $form_id ) {
	$form_id = (int) $form_id;
	if ( 'mcupr_fb' !== get_post_type( $form_id ) || 'publish' !== get_post_status( $form_id ) ) {
		return '';
	}
	$o      = mcupr_fb_opts( $form_id );
	$fields = mcupr_fb_fields( $form_id );
	$wrap   = 'fbform-' . $form_id;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['fb'], $_GET['fbid'] ) && (int) $_GET['fbid'] === $form_id ? sanitize_key( wp_unslash( $_GET['fb'] ) ) : '';
	$sess  = array( 'v' => array(), 'e' => array() );
	if ( 'err' === $state && isset( $_GET['fbk'] ) ) {
		$t    = get_transient( 'mcupr_fbv_' . preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_GET['fbk'] ) ) );
		$sess = is_array( $t ) ? array_merge( $sess, $t ) : $sess;
	}
	// phpcs:enable
	ob_start();
	echo '<section class="mform fbform" id="' . esc_attr( $wrap ) . '">';
	if ( 'ok' === $state ) {
		echo '<div class="mform-ok" role="status" tabindex="-1"><p><b>' . esc_html( '' !== $o['success'] ? $o['success'] : mcupr_t( 'fb_thanks' ) ) . '</b></p>' . apply_filters( 'mcupr_fb_ok_html', '', $form_id ) . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return ob_get_clean();
	}
	$st = mcupr_fb_state( $form_id );
	if ( 'closed' === $st ) {
		echo '<div class="mform-err" role="status"><p><b>' . esc_html( '' !== $o['closed'] ? $o['closed'] : mcupr_t( 'fb_closed' ) ) . '</b></p></div></section>';
		return ob_get_clean();
	}
	$gate = apply_filters( 'mcupr_fb_gate_html', '', $form_id );
	if ( '' !== $gate ) {
		echo $gate . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return ob_get_clean();
	}
	if ( 'users' === $st && ! is_user_logged_in() ) {
		echo '<div class="mform-err" role="status"><p><b>' . esc_html( mcupr_t( 'fb_login' ) ) . '</b></p><p><a class="btn" href="' . esc_url( wp_login_url( is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' ) ) ) . '">' . esc_html( mcupr_t( 'fb_login_btn' ) ) . '</a></p></div></section>';
		return ob_get_clean();
	}
	$errs = $sess['e'];
	$vals = $sess['v'];
	if ( 'err' === $state ) {
		echo '<div class="mform-err" role="alert" tabindex="-1" data-fb-errors><p><b>' . esc_html( mcupr_t( 'form_err' ) ) . '</b></p><ul>';
		$labels = array();
		foreach ( $fields as $f ) {
			$labels[ $f['id'] ] = $f['label'];
		}
		foreach ( $errs as $k => $e ) {
			if ( isset( $labels[ $k ] ) ) {
				echo '<li><a href="#' . esc_attr( $wrap . '-' . $k ) . '">' . esc_html( $labels[ $k ] . ': ' . mcupr_fb_error_text( $e ) ) . '</a></li>';
			} else {
				echo '<li>' . esc_html( mcupr_fb_error_text( $e ) ) . '</li>';
			}
		}
		echo '</ul></div>';
	}
	$has_file = (bool) array_filter( $fields, static function ( $f ) { return 'file' === $f['type']; } );
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate' . ( $has_file ? ' enctype="multipart/form-data"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="mcupr_fb"><input type="hidden" name="fb" value="' . (int) $form_id . '">';
	echo '<input type="hidden" name="back" value="' . esc_url( remove_query_arg( array( 'fb', 'fbid', 'fbk', 'fbe', 'rqc' ), apply_filters( 'mcupr_fb_back', is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' ), $form_id ) ) ) . '">';
	wp_nonce_field( 'mcupr_fb_' . $form_id, '_wpnonce', true, true );
	echo '<input type="hidden" name="tk" value="' . esc_attr( mcupr_form_token( time() ) ) . '"><div class="mform-trap" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
	if ( '' !== $o['intro'] ) {
		echo '<div class="fb-intro">' . wp_kses_post( wpautop( esc_html( $o['intro'] ) ) ) . '</div>';
	}
	if ( array_filter( $fields, static function ( $f ) { return ! empty( $f['required'] ); } ) ) {
		echo '<p class="mform-note">' . esc_html( mcupr_t( 'form_req' ) ) . '</p>';
	}
	foreach ( $fields as $f ) {
		$fid  = $wrap . '-' . $f['id'];
		$name = 'f[' . $f['id'] . ']';
		$err  = isset( $errs[ $f['id'] ] ) ? $errs[ $f['id'] ] : '';
		$val  = isset( $vals[ $f['id'] ] ) ? $vals[ $f['id'] ] : '';
		$req  = ! empty( $f['required'] );
		$star = $req ? ' <span aria-hidden="true">*</span>' : '';
		$desc = array();
		if ( '' !== $f['help'] ) {
			$desc[] = $fid . '-h';
		}
		if ( $err ) {
			$desc[] = $fid . '-e';
		}
		$attr = ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . ( $err ? ' aria-invalid="true"' : '' ) . ( $req ? ' required aria-required="true"' : '' );
		$tail = ( '' !== $f['help'] ? '<span class="fb-help" id="' . esc_attr( $fid . '-h' ) . '">' . esc_html( $f['help'] ) . '</span>' : '' ) . ( $err ? '<span class="fb-err" id="' . esc_attr( $fid . '-e' ) . '">' . esc_html( mcupr_fb_error_text( $err ) ) . '</span>' : '' );
		if ( 'heading' === $f['type'] ) {
			echo '<h2 class="fbf-h">' . esc_html( $f['label'] ) . '</h2>';
			continue;
		}
		if ( 'note' === $f['type'] ) {
			echo '<p class="fb-note">' . nl2br( esc_html( '' !== $f['help'] ? $f['help'] : $f['label'] ) ) . '</p>';
			continue;
		}
		echo '<div class="fb-f fb-' . esc_attr( $f['type'] ) . '" id="' . esc_attr( $fid ) . '">';
		if ( in_array( $f['type'], array( 'radio', 'checkbox', 'rating' ), true ) ) {
			echo '<fieldset' . ( $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '' ) . '><legend>' . esc_html( $f['label'] ) . $star . '</legend><div class="fb-opts' . ( 'rating' === $f['type'] ? ' is-rating' : '' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
			$opts = 'rating' === $f['type'] ? array( '1', '2', '3', '4', '5' ) : $f['options'];
			foreach ( $opts as $i => $op ) {
				$oid = $fid . '-o' . $i;
				$chk = 'checkbox' === $f['type'] ? in_array( $op, (array) $val, true ) : ( (string) $val === (string) $op );
				echo '<label class="fb-o" for="' . esc_attr( $oid ) . '"><input id="' . esc_attr( $oid ) . '" type="' . ( 'checkbox' === $f['type'] ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name . ( 'checkbox' === $f['type'] ? '[]' : '' ) ) . '" value="' . esc_attr( $op ) . '"' . checked( $chk, true, false ) . ( $req && 'checkbox' !== $f['type'] && 0 === $i ? ' required' : '' ) . '><span>' . esc_html( $op ) . '</span></label>';
			}
			echo '</div>' . $tail . '</fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'file' === $f['type'] ) {
			echo '<label for="' . esc_attr( $fid . '-i' ) . '">' . esc_html( $f['label'] ) . $star . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<input id="' . esc_attr( $fid . '-i' ) . '" type="file" name="file_' . esc_attr( $f['id'] ) . '" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"' . $attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="fb-help" id="' . esc_attr( $fid . '-f' ) . '">' . esc_html( sprintf( mcupr_t( 'rq_file_hint' ), size_format( function_exists( 'mcupr_rq_max_bytes' ) ? mcupr_rq_max_bytes() : 5 * MB_IN_BYTES ) ) ) . '</span>' . $tail; // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( 'consent' === $f['type'] ) {
			echo '<p class="mform-consent"><label for="' . esc_attr( $fid . '-c' ) . '"><input id="' . esc_attr( $fid . '-c' ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( '1' === $val, true, false ) . $attr . '> <span>' . esc_html( $f['label'] ) . $star . '</span></label></p>' . $tail; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<label for="' . esc_attr( $fid . '-i' ) . '">' . esc_html( $f['label'] ) . $star . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( 'textarea' === $f['type'] ) {
				echo '<textarea id="' . esc_attr( $fid . '-i' ) . '" name="' . esc_attr( $name ) . '" rows="5" maxlength="5000" placeholder="' . esc_attr( $f['placeholder'] ) . '"' . $attr . '>' . esc_textarea( (string) $val ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} elseif ( 'select' === $f['type'] ) {
				echo '<select id="' . esc_attr( $fid . '-i' ) . '" name="' . esc_attr( $name ) . '"' . $attr . '><option value="">' . esc_html( mcupr_t( 'fb_choose' ) ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput
				foreach ( $f['options'] as $op ) {
					echo '<option value="' . esc_attr( $op ) . '"' . selected( (string) $val, $op, false ) . '>' . esc_html( $op ) . '</option>';
				}
				echo '</select>';
			} else {
				$map = array( 'email' => 'email', 'tel' => 'tel', 'number' => 'text', 'date' => 'date' );
				$ac  = array( 'email' => 'email', 'tel' => 'tel' );
				echo '<input id="' . esc_attr( $fid . '-i' ) . '" type="' . esc_attr( isset( $map[ $f['type'] ] ) ? $map[ $f['type'] ] : 'text' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '" placeholder="' . esc_attr( $f['placeholder'] ) . '"' . ( 'number' === $f['type'] ? ' inputmode="decimal"' : '' ) . ( isset( $ac[ $f['type'] ] ) ? ' autocomplete="' . esc_attr( $ac[ $f['type'] ] ) . '"' : '' ) . ( 'text' === $f['type'] ? ' maxlength="300"' : '' ) . $attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo $tail; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
	echo '<p><button type="submit" class="btn">' . esc_html( '' !== $o['submit'] ? $o['submit'] : apply_filters( 'mcupr_fb_submit_label', mcupr_t( 'form_send' ), $form_id ) ) . '</button></p></form></section>';
	return ob_get_clean();
}

/* ------------------------------------------------------------------ admin: the answers */

add_action(
	'add_meta_boxes_mcupr_fe',
	static function () {
		add_meta_box( 'mcupr_fe_rows', __( 'Answer', 'mcupr' ), 'mcupr_fe_box', 'mcupr_fe', 'normal', 'high' );
	}
);
function mcupr_fe_box( $post ) {
	$rows = get_post_meta( $post->ID, '_mcupr_fe_rows', true );
	$rows = is_array( $rows ) ? $rows : array();
	$fid  = (int) get_post_meta( $post->ID, '_mcupr_fe_form', true );
	echo '<p>' . esc_html__( 'Form:', 'mcupr' ) . ' <a href="' . esc_url( get_edit_post_link( $fid ) ) . '">' . esc_html( get_the_title( $fid ) ) . '</a> · ' . esc_html( get_the_date( 'j M Y H:i', $post ) ) . '</p><table class="widefat striped"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><th style="width:30%">' . esc_html( $r[0] ) . '</th><td>' . nl2br( esc_html( $r[1] ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

add_filter(
	'manage_mcupr_fe_posts_columns',
	static function () {
		return array( 'cb' => '<input type="checkbox">', 'title' => __( 'Answer', 'mcupr' ), 'mcupr_fe_form' => __( 'Form', 'mcupr' ), 'mcupr_fe_sum' => __( 'Summary', 'mcupr' ), 'date' => __( 'Date', 'mcupr' ) );
	}
);
add_action(
	'manage_mcupr_fe_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_fe_form' === $col ) {
			echo esc_html( get_the_title( (int) get_post_meta( $id, '_mcupr_fe_form', true ) ) );
		} elseif ( 'mcupr_fe_sum' === $col ) {
			$rows = get_post_meta( $id, '_mcupr_fe_rows', true );
			$out  = array();
			foreach ( is_array( $rows ) ? $rows : array() as $r ) {
				if ( '' !== $r[1] && count( $out ) < 3 ) {
					$out[] = $r[0] . ': ' . mb_strimwidth( $r[1], 0, 40, '…' );
				}
			}
			echo esc_html( implode( ' · ', $out ) );
		}
	},
	10,
	2
);
add_action(
	'restrict_manage_posts',
	static function ( $type ) {
		if ( 'mcupr_fe' !== $type ) {
			return;
		}
		$cur = isset( $_GET['mcupr_form'] ) ? (int) $_GET['mcupr_form'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="mcupr_form"><option value="">' . esc_html__( 'All forms', 'mcupr' ) . '</option>';
		foreach ( get_posts( array( 'post_type' => 'mcupr_fb', 'post_status' => 'any', 'posts_per_page' => 100, 'orderby' => 'title', 'order' => 'ASC' ) ) as $f ) {
			echo '<option value="' . (int) $f->ID . '"' . selected( $cur, $f->ID, false ) . '>' . esc_html( get_the_title( $f ) ) . '</option>';
		}
		echo '</select>';
		if ( $cur ) {
			echo ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mcupr_fb_export&form=' . $cur ), 'mcupr_fb_export' ) ) . '">' . esc_html__( 'Download CSV (Excel)', 'mcupr' ) . '</a>';
		}
	}
);
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() && $q->is_main_query() && 'mcupr_fe' === $q->get( 'post_type' ) && ! empty( $_GET['mcupr_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$q->set( 'meta_key', '_mcupr_fe_form' ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$q->set( 'meta_value', (int) $_GET['mcupr_form'] ); // phpcs:ignore WordPress.DB.SlowDBQuery,WordPress.Security.NonceVerification
		}
	}
);

/* mcupr_csv_cell() lives in inc/compat.php (every CSV export guards formulas the same way). */

/** The CSV text of a form's answers (columns = the questions that were asked, in order of first appearance). */
function mcupr_fb_csv( $form_id ) {
	$ids  = get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => '_mcupr_fe_form', 'meta_value' => (int) $form_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$cols = array();
	foreach ( mcupr_fb_fields( $form_id ) as $f ) {
		if ( ! in_array( $f['type'], array( 'heading', 'note' ), true ) && ! in_array( $f['label'], $cols, true ) ) {
			$cols[] = $f['label'];
		}
	}
	$data = array();
	foreach ( $ids as $id ) {
		$row = array();
		foreach ( (array) get_post_meta( $id, '_mcupr_fe_rows', true ) as $r ) {
			$row[ $r[0] ] = $r[1];
			if ( ! in_array( $r[0], $cols, true ) ) {
				$cols[] = $r[0]; // a question that was removed later still keeps its column
			}
		}
		$data[ $id ] = $row;
	}
	$rq  = function_exists( 'mcupr_rq_is_form' ) && mcupr_rq_is_form( $form_id ); // a request has a number, a status and someone to see to it
	$out = "\xEF\xBB\xBF" . implode( ',', array_map( 'mcupr_csv_cell', array_merge( array( '#', 'Date' ), $rq ? mcupr_rq_csv_head() : array(), $cols ) ) ) . "\r\n";
	$n   = 0;
	foreach ( $data as $id => $row ) {
		$n++;
		$cells = array_merge( array( (string) $n, get_the_date( 'Y-m-d H:i', $id ) ), $rq ? mcupr_rq_csv_cells( $id ) : array() );
		foreach ( $cols as $c ) {
			$cells[] = isset( $row[ $c ] ) ? $row[ $c ] : '';
		}
		$out .= implode( ',', array_map( 'mcupr_csv_cell', $cells ) ) . "\r\n";
	}
	return $out;
}

add_action(
	'admin_post_mcupr_fb_export',
	static function () {
		if ( ! mcupr_check_request( '_wpnonce', 'mcupr_fb_export', 'edit_pages', 'get' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$fid = isset( $_GET['form'] ) ? (int) $_GET['form'] : 0;
		if ( 'mcupr_fb' !== get_post_type( $fid ) ) {
			wp_die( esc_html__( 'Unknown form.', 'mcupr' ), 404 );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( get_post_field( 'post_name', $fid ) . '-answers.csv' ) . '"' );
		echo mcupr_fb_csv( $fid ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
);

/* ------------------------------------------------------------------ keeping answers only as long as asked */

function mcupr_fb_prune() {
	foreach ( get_posts( array( 'post_type' => 'mcupr_fb', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $fid ) {
		$keep = (int) mcupr_fb_opts( $fid )['keep'];
		if ( $keep < 1 ) {
			continue;
		}
		foreach ( get_posts( array( 'post_type' => 'mcupr_fe', 'post_status' => 'private', 'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true, 'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $keep * DAY_IN_SECONDS ) ) ), 'meta_key' => '_mcupr_fe_form', 'meta_value' => $fid ) ) as $eid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			wp_delete_post( $eid, true );
		}
	}
}
add_action( 'mcupr_fb_prune', 'mcupr_fb_prune' );
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'mcupr_fb_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mcupr_fb_prune' );
		}
	}
);

/** [mcupr_form id="12"] or [mcupr_form slug="staff-training"] – the form builder's forms (the old type="contact|complaint" still works). */
add_filter(
	'mcupr_form_shortcode',
	static function ( $html, $atts ) {
		$id = ! empty( $atts['id'] ) ? (int) $atts['id'] : 0;
		if ( ! $id && ! empty( $atts['slug'] ) ) {
			$p  = get_posts( array( 'post_type' => 'mcupr_fb', 'name' => sanitize_title( $atts['slug'] ), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
			$id = $p ? (int) $p[0] : 0;
		}
		return $id ? mcupr_fb_html( $id ) : $html;
	},
	10,
	2
);
