<?php
/**
 * Personal data: the two tools WordPress gives a person who asks what the site holds about them (Tools → Export
 * personal data / Erase personal data), and the log of who looked at what.
 *
 * The student records already had both (inc/students.php). Everything else a person sends the site did not: the
 * answers of a form, a request with the papers that came with it, a message to the complaint form, a forum topic
 * written as a guest. This file adds those, and writes to the same log (inc/students-care.php) whenever somebody takes
 * personal data out of the site or opens the papers a person sent.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ finding what belongs to an address */

/** The ids of the form answers (requests included) that carry this e-mail address. */
function mcupr_pd_entries( $email ) {
	global $wpdb;
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) {
		return array();
	}
	// a request keeps the address in its own field; any other answer only inside the answers themselves
	$ids = get_posts(
		array(
			'post_type'      => 'mcupr_fe',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_mcupr_rq_email', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$like = '%' . $wpdb->esc_like( $email ) . '%';
	$rows = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_mcupr_fe_rows' AND meta_value LIKE %s LIMIT 100", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	return array_values( array_unique( array_map( 'intval', array_merge( (array) $ids, (array) $rows ) ) ) );
}

/** The ids of posts of $type whose meta $key is this address. */
function mcupr_pd_by_meta( $type, $key, $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) || ! post_type_exists( $type ) ) {
		return array();
	}
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/** One row of the export: a label and a value, the empty ones left out. */
function mcupr_pd_rows( $pairs ) {
	$out = array();
	foreach ( $pairs as $label => $value ) {
		if ( '' !== (string) $value ) {
			$out[] = array( 'name' => $label, 'value' => (string) $value );
		}
	}
	return $out;
}

/* ------------------------------------------------------------------ Tools → Export personal data */

add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $e ) {
		$e['mcupr-form-entry'] = array(
			'exporter_friendly_name' => __( 'Forms filled in and requests sent', 'mcupr' ),
			'callback'               => static function ( $email ) {
				$out = array();
				foreach ( mcupr_pd_entries( $email ) as $id ) {
					$rows  = get_post_meta( $id, '_mcupr_fe_rows', true );
					$pairs = array(
						__( 'Sent on', 'mcupr' )         => get_the_date( 'Y-m-d H:i', $id ),
						__( 'Form', 'mcupr' )            => get_the_title( (int) get_post_meta( $id, '_mcupr_fe_form', true ) ),
						__( 'Request number', 'mcupr' )  => (string) get_post_meta( $id, '_mcupr_rq_code', true ),
						__( 'Status', 'mcupr' )          => (string) get_post_meta( $id, '_mcupr_rq_status', true ),
						__( 'E-mail address', 'mcupr' )  => (string) get_post_meta( $id, '_mcupr_rq_email', true ),
						__( 'Student ID', 'mcupr' )      => (string) get_post_meta( $id, '_mcupr_rq_sidcode', true ),
					);
					foreach ( is_array( $rows ) ? $rows : array() as $r ) {
						if ( is_array( $r ) && isset( $r[0], $r[1] ) ) {
							$pairs[ (string) $r[0] ] = (string) $r[1];
						}
					}
					$files = get_post_meta( $id, '_mcupr_rq_files', true );
					$names = array();
					foreach ( is_array( $files ) ? $files : array() as $f ) {
						$names[] = isset( $f['n'] ) ? (string) $f['n'] : '';
					}
					if ( $names ) {
						$pairs[ __( 'Files sent with it', 'mcupr' ) ] = implode( ', ', array_filter( $names ) );
					}
					$out[] = array( 'group_id' => 'mcupr-form-entry', 'group_label' => __( 'Forms filled in and requests sent', 'mcupr' ), 'item_id' => 'entry-' . $id, 'data' => mcupr_pd_rows( $pairs ) );
				}
				mcupr_pd_audit( 'pdpaexport', count( $out ), 'forms' );
				return array( 'data' => $out, 'done' => true );
			},
		);
		$e['mcupr-message'] = array(
			'exporter_friendly_name' => __( 'Messages to the contact and complaint forms', 'mcupr' ),
			'callback'               => static function ( $email ) {
				$out = array();
				foreach ( mcupr_pd_by_meta( 'mcupr_msg', '_mcupr_msg_email', $email ) as $id ) {
					$pairs = array(
						__( 'Sent on', 'mcupr' )        => get_the_date( 'Y-m-d H:i', $id ),
						__( 'Reference', 'mcupr' )      => (string) get_post_meta( $id, '_mcupr_msg_ref', true ),
						__( 'Kind', 'mcupr' )           => (string) get_post_meta( $id, '_mcupr_msg_kind', true ),
						__( 'Name', 'mcupr' )           => (string) get_post_meta( $id, '_mcupr_msg_name', true ),
						__( 'E-mail address', 'mcupr' ) => (string) get_post_meta( $id, '_mcupr_msg_email', true ),
						__( 'Telephone', 'mcupr' )      => (string) get_post_meta( $id, '_mcupr_msg_phone', true ),
						__( 'Subject', 'mcupr' )        => (string) get_post_meta( $id, '_mcupr_msg_subject', true ),
						__( 'Message', 'mcupr' )        => (string) get_post_field( 'post_content', $id ),
					);
					$out[] = array( 'group_id' => 'mcupr-message', 'group_label' => __( 'Messages to the contact and complaint forms', 'mcupr' ), 'item_id' => 'message-' . $id, 'data' => mcupr_pd_rows( $pairs ) );
				}
				mcupr_pd_audit( 'pdpaexport', count( $out ), 'messages' );
				return array( 'data' => $out, 'done' => true );
			},
		);
		$e['mcupr-forum'] = array(
			'exporter_friendly_name' => __( 'Forum topics written as a guest', 'mcupr' ),
			'callback'               => static function ( $email ) {
				$out = array();
				foreach ( mcupr_pd_by_meta( 'mcupr_topic', '_mcupr_topic_email', $email ) as $id ) {
					$pairs = array(
						__( 'Written on', 'mcupr' )     => get_the_date( 'Y-m-d H:i', $id ),
						__( 'Title', 'mcupr' )          => get_the_title( $id ),
						__( 'Name given', 'mcupr' )     => (string) get_post_meta( $id, '_mcupr_topic_name', true ),
						__( 'E-mail address', 'mcupr' ) => (string) get_post_meta( $id, '_mcupr_topic_email', true ),
						__( 'Text', 'mcupr' )           => (string) get_post_field( 'post_content', $id ),
						__( 'Address', 'mcupr' )        => (string) get_permalink( $id ),
					);
					$out[] = array( 'group_id' => 'mcupr-forum', 'group_label' => __( 'Forum topics written as a guest', 'mcupr' ), 'item_id' => 'topic-' . $id, 'data' => mcupr_pd_rows( $pairs ) );
				}
				mcupr_pd_audit( 'pdpaexport', count( $out ), 'forum' );
				return array( 'data' => $out, 'done' => true );
			},
		);
		return $e;
	}
);

/* ------------------------------------------------------------------ Tools → Erase personal data */

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $e ) {
		$e['mcupr-form-entry'] = array(
			'eraser_friendly_name' => __( 'Forms filled in and requests sent', 'mcupr' ),
			'callback'             => static function ( $email ) {
				$n = 0;
				foreach ( mcupr_pd_entries( $email ) as $id ) {
					wp_delete_post( $id, true ); // the files go with it (inc/requests.php, before_delete_post)
					$n++;
				}
				mcupr_pd_audit( 'pdpaerase', $n, 'forms' );
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		$e['mcupr-message'] = array(
			'eraser_friendly_name' => __( 'Messages to the contact and complaint forms', 'mcupr' ),
			'callback'             => static function ( $email ) {
				$n = 0;
				foreach ( mcupr_pd_by_meta( 'mcupr_msg', '_mcupr_msg_email', $email ) as $id ) {
					wp_delete_post( $id, true );
					$n++;
				}
				mcupr_pd_audit( 'pdpaerase', $n, 'messages' );
				return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		/*
		 * A forum topic other people have answered is not deleted: that would take their answers with it. The name and
		 * the address of the person who wrote it are removed, and the topic stays as something somebody wrote.
		 */
		$e['mcupr-forum'] = array(
			'eraser_friendly_name' => __( 'Forum topics written as a guest', 'mcupr' ),
			'callback'             => static function ( $email ) {
				$n   = 0;
				$msg = array();
				foreach ( mcupr_pd_by_meta( 'mcupr_topic', '_mcupr_topic_email', $email ) as $id ) {
					delete_post_meta( $id, '_mcupr_topic_email' );
					update_post_meta( $id, '_mcupr_topic_name', __( 'Removed on request', 'mcupr' ) );
					$n++;
				}
				if ( $n ) {
					$msg[] = __( 'The name and the e-mail address were removed from the forum topics. The topics themselves stay, because other people answered them.', 'mcupr' );
				}
				mcupr_pd_audit( 'pdpaerase', $n, 'forum' );
				return array( 'items_removed' => $n > 0, 'items_retained' => $n > 0, 'messages' => $msg, 'done' => true );
			},
		);
		return $e;
	}
);

/* ------------------------------------------------------------------ the log */

/** Write one event about personal data, if the log is there at all. $note says which kind, never who. */
function mcupr_pd_audit( $action, $n = 0, $note = '' ) {
	if ( $n < 1 || ! function_exists( 'mcupr_audit' ) ) {
		return;
	}
	mcupr_audit( $action, array( 'n' => (int) $n, 'note' => (string) $note ) );
}

/** Somebody opened a file a person sent with a request. */
add_action(
	'mcupr_rq_file_served',
	static function ( $entry ) {
		mcupr_pd_audit( 'rqfile', 1, (string) get_post_meta( (int) $entry, '_mcupr_rq_code', true ) );
	}
);

/** Somebody took the answers of a form out as a file. */
add_action(
	'mcupr_fb_exported',
	static function ( $form_id, $rows ) {
		mcupr_pd_audit( 'fbexport', max( 1, (int) $rows ), get_the_title( (int) $form_id ) );
	},
	10,
	2
);

/** Somebody took the whole staff directory out as a file. */
add_action(
	'mcupr_staff_exported',
	static function ( $rows ) {
		mcupr_pd_audit( 'staffexport', max( 1, (int) $rows ) );
	}
);
