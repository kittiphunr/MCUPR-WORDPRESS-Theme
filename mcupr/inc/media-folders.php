<?php
/**
 * Folders for the media library.
 *
 * Folders are virtual (a taxonomy on attachments): no file is moved on the server and no picture link changes. A file sits in
 * at most one folder; folders nest. The media page gets a folder panel (drag files onto a folder, make / rename / delete
 * folders, file-type chips), the media pop-up (picking a picture in a post) gets a folder filter, and the file's details
 * have a "Folder" field. New uploads go into the folder you are looking at. "Unfiled" shows what has no folder.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_MF = 'mcupr_mfolder';

add_action(
	'init',
	static function () {
		register_taxonomy(
			MCUPR_MF,
			'attachment',
			array(
				'labels'             => array(
					'name'          => __( 'Media folders', 'mcupr' ),
					'singular_name' => __( 'Media folder', 'mcupr' ),
				),
				'hierarchical'       => true,
				'public'             => false,
				'show_ui'            => false,
				'show_in_nav_menus'  => false,
				'show_admin_column'  => false,
				'show_in_rest'       => false,
				'rewrite'            => false,
				'query_var'          => false,
				'update_count_callback' => '_update_generic_term_count',
			)
		);
	},
	5
);

/* ------------------------------------------------------------------ data */

/** Every folder: array( array( id, parent, name, own ) ) ordered by name. */
function mcupr_mf_folders() {
	$out = array();
	$ts  = get_terms( array( 'taxonomy' => MCUPR_MF, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
	if ( is_wp_error( $ts ) ) {
		return $out;
	}
	foreach ( $ts as $t ) {
		$out[] = array( 'id' => (int) $t->term_id, 'parent' => (int) $t->parent, 'name' => $t->name, 'own' => (int) $t->count );
	}
	return $out;
}

/** Files in a folder and in the folders below it (what the folder shows). */
function mcupr_mf_count( $term_id ) {
	$q = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'tax_query' => array( array( 'taxonomy' => MCUPR_MF, 'field' => 'term_id', 'terms' => array( (int) $term_id ), 'include_children' => true ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return (int) $q->found_posts;
}

/** Totals for the panel: all files, files with no folder, and per folder (with the folders below). */
function mcupr_mf_counts() {
	$all = array_sum( array_map( 'intval', (array) wp_count_attachments() ) );
	$q   = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'tax_query' => array( array( 'taxonomy' => MCUPR_MF, 'operator' => 'NOT EXISTS' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$per = array();
	foreach ( mcupr_mf_folders() as $f ) {
		$per[ $f['id'] ] = mcupr_mf_count( $f['id'] );
	}
	return array( 'all' => $all, 'none' => (int) $q->found_posts, 'folders' => $per );
}

/** The folder of a file (term id) or 0. */
function mcupr_mf_of( $attachment_id ) {
	$t = wp_get_object_terms( (int) $attachment_id, MCUPR_MF, array( 'fields' => 'ids' ) );
	return is_wp_error( $t ) || ! $t ? 0 : (int) $t[0];
}

/** Put files in a folder (0 = no folder). Returns how many were moved; a person only moves files they may edit. */
function mcupr_mf_move( $ids, $folder ) {
	$folder = (int) $folder;
	if ( $folder && ! term_exists( $folder, MCUPR_MF ) ) {
		return 0;
	}
	$n = 0;
	foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			continue;
		}
		wp_set_object_terms( $id, $folder ? array( $folder ) : array(), MCUPR_MF );
		++$n;
	}
	return $n;
}

/** Make a folder. Returns the id or a WP_Error. The same name twice under one parent is refused. */
function mcupr_mf_create( $name, $parent = 0 ) {
	$name   = trim( sanitize_text_field( $name ) );
	$parent = (int) $parent;
	if ( '' === $name || mb_strlen( $name ) > 80 ) {
		return new WP_Error( 'name', __( 'Give the folder a name (up to 80 characters).', 'mcupr' ) );
	}
	if ( $parent && ! term_exists( $parent, MCUPR_MF ) ) {
		return new WP_Error( 'parent', __( 'That parent folder does not exist.', 'mcupr' ) );
	}
	$have = get_terms( array( 'taxonomy' => MCUPR_MF, 'hide_empty' => false, 'parent' => $parent, 'name' => $name, 'fields' => 'ids' ) );
	if ( ! is_wp_error( $have ) && $have ) {
		return new WP_Error( 'dup', __( 'There is already a folder with that name here.', 'mcupr' ) );
	}
	$r = wp_insert_term( $name, MCUPR_MF, array( 'parent' => $parent, 'slug' => sanitize_title( $name ) . '-' . wp_generate_password( 4, false ) ) );
	return is_wp_error( $r ) ? $r : (int) $r['term_id'];
}

/** Rename a folder. */
function mcupr_mf_rename( $id, $name ) {
	$name = trim( sanitize_text_field( $name ) );
	$t    = get_term( (int) $id, MCUPR_MF );
	if ( ! $t || is_wp_error( $t ) || '' === $name || mb_strlen( $name ) > 80 ) {
		return new WP_Error( 'name', __( 'Give the folder a name (up to 80 characters).', 'mcupr' ) );
	}
	$have = get_terms( array( 'taxonomy' => MCUPR_MF, 'hide_empty' => false, 'parent' => (int) $t->parent, 'name' => $name, 'fields' => 'ids', 'exclude' => array( (int) $id ) ) );
	if ( ! is_wp_error( $have ) && $have ) {
		return new WP_Error( 'dup', __( 'There is already a folder with that name here.', 'mcupr' ) );
	}
	$r = wp_update_term( (int) $id, MCUPR_MF, array( 'name' => $name ) );
	return is_wp_error( $r ) ? $r : true;
}

/** Move a folder under another one (0 = top level); never into itself or below itself. */
function mcupr_mf_reparent( $id, $parent ) {
	$id     = (int) $id;
	$parent = (int) $parent;
	$t      = get_term( $id, MCUPR_MF );
	if ( ! $t || is_wp_error( $t ) || $id === $parent || ( $parent && ! term_exists( $parent, MCUPR_MF ) ) ) {
		return new WP_Error( 'parent', 'parent' );
	}
	if ( $parent && ( in_array( $id, get_ancestors( $parent, MCUPR_MF ), true ) ) ) {
		return new WP_Error( 'loop', __( 'A folder cannot go inside itself.', 'mcupr' ) );
	}
	$r = wp_update_term( $id, MCUPR_MF, array( 'parent' => $parent ) );
	return is_wp_error( $r ) ? $r : true;
}

/** Delete a folder: its files and its sub-folders move up to its parent, nothing is deleted from the library. */
function mcupr_mf_delete( $id ) {
	$id = (int) $id;
	$t  = get_term( $id, MCUPR_MF );
	if ( ! $t || is_wp_error( $t ) ) {
		return new WP_Error( 'none', 'none' );
	}
	$own = get_objects_in_term( $id, MCUPR_MF );
	if ( ! is_wp_error( $own ) ) {
		foreach ( $own as $aid ) {
			wp_set_object_terms( (int) $aid, $t->parent ? array( (int) $t->parent ) : array(), MCUPR_MF );
		}
	}
	foreach ( get_terms( array( 'taxonomy' => MCUPR_MF, 'hide_empty' => false, 'parent' => $id, 'fields' => 'ids' ) ) as $kid ) {
		wp_update_term( (int) $kid, MCUPR_MF, array( 'parent' => (int) $t->parent ) );
	}
	$r = wp_delete_term( $id, MCUPR_MF );
	return is_wp_error( $r ) || ! $r ? new WP_Error( 'x', 'x' ) : true;
}

/* ------------------------------------------------------------------ the library lists */

/** The folder asked for: a term id, or 'none' for the unfiled, or '' for all. */
function mcupr_mf_asked( $raw ) {
	if ( 'none' === $raw ) {
		return 'none';
	}
	return is_numeric( $raw ) && (int) $raw > 0 ? (int) $raw : '';
}

function mcupr_mf_tax_query( $asked ) {
	if ( 'none' === $asked ) {
		return array( array( 'taxonomy' => MCUPR_MF, 'operator' => 'NOT EXISTS' ) );
	}
	if ( $asked ) {
		return array( array( 'taxonomy' => MCUPR_MF, 'field' => 'term_id', 'terms' => array( $asked ), 'include_children' => true ) );
	}
	return array();
}

// the grid and the pop-up ask by Ajax
add_filter(
	'ajax_query_attachments_args',
	static function ( $args ) {
		$q = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$f = mcupr_mf_asked( isset( $q['mcupr_folder'] ) ? sanitize_text_field( (string) $q['mcupr_folder'] ) : '' );
		$t = mcupr_mf_tax_query( $f );
		if ( $t ) {
			$args['tax_query'] = array_merge( isset( $args['tax_query'] ) ? (array) $args['tax_query'] : array(), $t ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return $args;
	}
);

// the list view of the media page
add_action(
	'pre_get_posts',
	static function ( $q ) {
		global $pagenow;
		if ( ! is_admin() || ! $q->is_main_query() || 'upload.php' !== $pagenow ) {
			return;
		}
		$f = mcupr_mf_asked( isset( $_GET['mcupr_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['mcupr_folder'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$t = mcupr_mf_tax_query( $f );
		if ( $t ) {
			$q->set( 'tax_query', $t ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	}
);

// a new upload goes into the folder being looked at
add_action(
	'add_attachment',
	static function ( $id ) {
		$f = isset( $_REQUEST['mcupr_folder'] ) ? (int) $_REQUEST['mcupr_folder'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $f > 0 && term_exists( $f, MCUPR_MF ) && current_user_can( 'upload_files' ) ) {
			wp_set_object_terms( (int) $id, array( $f ), MCUPR_MF );
		}
	}
);

/* ------------------------------------------------------------------ the file's own details */

add_filter(
	'attachment_fields_to_edit',
	static function ( $fields, $post ) {
		$cur = mcupr_mf_of( $post->ID );
		$h   = '<select name="attachments[' . (int) $post->ID . '][mcupr_folder]" id="attachments-' . (int) $post->ID . '-mcupr_folder"><option value="0">' . esc_html__( '— No folder —', 'mcupr' ) . '</option>';
		foreach ( mcupr_mf_flat() as $f ) {
			$h .= '<option value="' . (int) $f['id'] . '"' . selected( $cur, $f['id'], false ) . '>' . esc_html( str_repeat( '— ', $f['depth'] ) . $f['name'] ) . '</option>';
		}
		$fields['mcupr_folder'] = array( 'label' => __( 'Folder', 'mcupr' ), 'input' => 'html', 'html' => $h . '</select>' );
		return $fields;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	static function ( $post, $attachment ) {
		if ( isset( $attachment['mcupr_folder'] ) && current_user_can( 'edit_post', $post['ID'] ) ) {
			mcupr_mf_move( array( (int) $post['ID'] ), (int) $attachment['mcupr_folder'] );
		}
		return $post;
	},
	10,
	2
);

/** Folders in tree order with their depth: array( array( id, name, depth ) ). */
function mcupr_mf_flat() {
	$by = array();
	foreach ( mcupr_mf_folders() as $f ) {
		$by[ $f['parent'] ][] = $f;
	}
	$out  = array();
	$walk = static function ( $p, $d ) use ( &$walk, &$out, $by ) {
		if ( $d > 8 || empty( $by[ $p ] ) ) {
			return;
		}
		foreach ( $by[ $p ] as $f ) {
			$out[] = array( 'id' => $f['id'], 'name' => $f['name'], 'depth' => $d );
			$walk( $f['id'], $d + 1 );
		}
	};
	$walk( 0, 0 );
	return $out;
}

/* ------------------------------------------------------------------ Ajax (make, rename, move, delete, count) */

function mcupr_mf_ajax_guard( $cap = 'upload_files' ) {
	if ( ! check_ajax_referer( 'mcupr_mf', '_wpnonce', false ) || ! current_user_can( $cap ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mcupr' ) ), 403 );
	}
}

function mcupr_mf_reply( $extra = array() ) {
	wp_send_json_success( array_merge( array( 'folders' => mcupr_mf_folders(), 'counts' => mcupr_mf_counts() ), $extra ) );
}

foreach ( array( 'create', 'rename', 'move', 'delete', 'reparent', 'list' ) as $mcupr_mf_act ) {
	add_action(
		'wp_ajax_mcupr_mf_' . $mcupr_mf_act,
		static function () use ( $mcupr_mf_act ) {
			mcupr_mf_ajax_guard( in_array( $mcupr_mf_act, array( 'rename', 'delete', 'reparent' ), true ) ? 'edit_others_posts' : 'upload_files' );
			$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
			switch ( $mcupr_mf_act ) {
				case 'create':
					$r = mcupr_mf_create( isset( $p['name'] ) ? (string) $p['name'] : '', isset( $p['parent'] ) ? (int) $p['parent'] : 0 );
					break;
				case 'rename':
					$r = mcupr_mf_rename( isset( $p['id'] ) ? (int) $p['id'] : 0, isset( $p['name'] ) ? (string) $p['name'] : '' );
					break;
				case 'reparent':
					$r = mcupr_mf_reparent( isset( $p['id'] ) ? (int) $p['id'] : 0, isset( $p['parent'] ) ? (int) $p['parent'] : 0 );
					break;
				case 'delete':
					$r = mcupr_mf_delete( isset( $p['id'] ) ? (int) $p['id'] : 0 );
					break;
				case 'move':
					$ids = isset( $p['ids'] ) ? array_map( 'intval', (array) $p['ids'] ) : array();
					$n   = mcupr_mf_move( $ids, isset( $p['folder'] ) ? (int) $p['folder'] : 0 );
					mcupr_mf_reply( array( 'moved' => $n ) );
					return;
				default:
					$r = true;
			}
			if ( is_wp_error( $r ) ) {
				wp_send_json_error( array( 'message' => $r->get_error_message() ) );
			}
			mcupr_mf_reply( array( 'id' => is_int( $r ) ? $r : 0 ) );
		}
	);
}

/* ------------------------------------------------------------------ the screens */

/** What the script needs. */
function mcupr_mf_script_data( $on_page ) {
	// phpcs:ignore WordPress.Security.NonceVerification
	$asked = mcupr_mf_asked( isset( $_GET['mcupr_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['mcupr_folder'] ) ) : '' );
	return array(
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'mcupr_mf' ),
		'page'    => (bool) $on_page,
		'list'    => $on_page && isset( $_GET['mode'] ) && 'list' === $_GET['mode'], // phpcs:ignore WordPress.Security.NonceVerification
		'current' => $asked,
		'folders' => mcupr_mf_folders(),
		'counts'  => mcupr_mf_counts(),
		'canEdit' => current_user_can( 'edit_others_posts' ),
		'mode'    => get_user_option( 'media_library_mode', get_current_user_id() ),
		't'       => array(
			'all'       => __( 'All files', 'mcupr' ),
			'none'      => __( 'Unfiled', 'mcupr' ),
			'folders'   => __( 'Folders', 'mcupr' ),
			'sub'       => __( 'Sub-folders', 'mcupr' ),
			'newFolder' => __( 'New folder', 'mcupr' ),
			'rename'    => __( 'Rename', 'mcupr' ),
			'delete'    => __( 'Delete', 'mcupr' ),
			'name'      => __( 'Folder name', 'mcupr' ),
			'confirm'   => __( 'Delete this folder? Its files and sub-folders move up one level; nothing is deleted from the library.', 'mcupr' ),
			'files'     => __( '%d files', 'mcupr' ),
			'moveTo'    => __( 'Move to folder…', 'mcupr' ),
			'moved'     => __( '%d files moved', 'mcupr' ),
			'dragHint'  => __( 'Drag files onto a folder to move them.', 'mcupr' ),
			'filter'    => __( 'Folder', 'mcupr' ),
			'types'     => array(
				'' => __( 'All types', 'mcupr' ),
				'image' => __( 'Images', 'mcupr' ),
				'video' => __( 'Video', 'mcupr' ),
				'audio' => __( 'Audio', 'mcupr' ),
				'application' => __( 'Documents', 'mcupr' ),
			),
			'ok'        => __( 'OK', 'mcupr' ),
			'cancel'    => __( 'Cancel', 'mcupr' ),
			'inside'    => __( 'Inside', 'mcupr' ),
			'top'       => __( 'Top level', 'mcupr' ),
		),
	);
}

// the media page itself
add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( 'upload.php' !== $hook || ! current_user_can( 'upload_files' ) ) {
			return;
		}
		wp_enqueue_style( 'mcupr-media-folders', get_theme_file_uri( 'assets/css/media-folders.css' ), array(), MCUPR_VERSION );
		wp_enqueue_script( 'mcupr-media-folders', get_theme_file_uri( 'assets/js/media-folders.js' ), array( 'jquery', 'media-views' ), MCUPR_VERSION, true );
		wp_localize_script( 'mcupr-media-folders', 'mcuprMF', mcupr_mf_script_data( true ) );
	}
);

// the pop-up (picking a picture in a post, the Customizer…)
add_action(
	'wp_enqueue_media',
	static function () {
		if ( ! current_user_can( 'upload_files' ) || wp_script_is( 'mcupr-media-folders', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'mcupr-media-folders', get_theme_file_uri( 'assets/css/media-folders.css' ), array(), MCUPR_VERSION );
		wp_enqueue_script( 'mcupr-media-folders', get_theme_file_uri( 'assets/js/media-folders.js' ), array( 'jquery', 'media-views' ), MCUPR_VERSION, true );
		wp_localize_script( 'mcupr-media-folders', 'mcuprMF', mcupr_mf_script_data( false ) );
	}
);

// list view: a "Folder" column and a bulk move
add_filter(
	'manage_media_columns',
	static function ( $c ) {
		$c['mcupr_folder'] = __( 'Folder', 'mcupr' );
		return $c;
	}
);
add_action(
	'manage_media_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_folder' !== $col ) {
			return;
		}
		$f = mcupr_mf_of( $id );
		$t = $f ? get_term( $f, MCUPR_MF ) : null;
		echo $t && ! is_wp_error( $t ) ? '<a href="' . esc_url( add_query_arg( array( 'mode' => 'list', 'mcupr_folder' => $f ), admin_url( 'upload.php' ) ) ) . '">' . esc_html( $t->name ) . '</a>' : '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No folder', 'mcupr' ) . '</span>';
	},
	10,
	2
);
add_filter(
	'bulk_actions-upload',
	static function ( $a ) {
		foreach ( mcupr_mf_flat() as $f ) {
			$a[ 'mcupr_mf_' . $f['id'] ] = sprintf( /* translators: %s: folder name */ __( 'Move to folder: %s', 'mcupr' ), str_repeat( '— ', $f['depth'] ) . $f['name'] );
		}
		$a['mcupr_mf_0'] = __( 'Move to: no folder', 'mcupr' );
		return $a;
	}
);
add_filter(
	'handle_bulk_actions-upload',
	static function ( $to, $action, $ids ) {
		if ( 0 === strpos( (string) $action, 'mcupr_mf_' ) ) {
			$n  = mcupr_mf_move( (array) $ids, (int) substr( $action, 9 ) );
			$to = add_query_arg( 'mcupr_moved', $n, remove_query_arg( array( 'mcupr_moved' ), $to ) );
		}
		return $to;
	},
	10,
	3
);
add_action(
	'admin_notices',
	static function () {
		global $pagenow;
		if ( 'upload.php' === $pagenow && isset( $_GET['mcupr_moved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%d files moved', 'mcupr' ), (int) $_GET['mcupr_moved'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
);
