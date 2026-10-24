<?php
/**
 * File manager for the uploads folder (MCUPR → File manager): the real folders and files of wp-content/uploads, laid out like
 * the Finder of macOS (sidebar, icon and list views, drag to move, rename, quick look) with colour tags that can be put on
 * any file or folder, several to one item, and used as a filter.
 *
 * For administrators only (manage_options). Safe by construction:
 *  - every path is a short relative path that is checked twice (no "..", no hidden names, real path inside uploads, links
 *    that lead out are refused); nothing outside wp-content/uploads can be seen or touched;
 *  - what the Media library knows about (a picture, its sizes) is shown with a lock and cannot be moved, renamed or deleted
 *    here (the pictures in posts would break); use the Media library for those;
 *  - uploads and renames are limited to the file types WordPress allows, never scripts; a name with ".php" in it is refused;
 *  - deleting moves an item to the trash (a hidden folder inside uploads, one unguessable folder per item, closed to the web where the
 *    server reads .htaccess); it can be put back, or deleted for good, and the trash empties itself after 30 days.
 * The colour tags live in an option (path → tags), they follow an item when it is moved or renamed here.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_FM_CAP = 'manage_options';

/* ------------------------------------------------------------------ paths */

/** The real path of wp-content/uploads (no trailing slash), '' when there is none. */
function mcupr_fm_root() {
	$u = wp_get_upload_dir();
	$r = ! empty( $u['basedir'] ) ? realpath( $u['basedir'] ) : false;
	return $r ? untrailingslashit( wp_normalize_path( $r ) ) : '';
}

/** Names that are never shown or touched: hidden files, the folder index files and the theme's private folders at the top. */
function mcupr_fm_hidden( $name, $top = false ) {
	return '' === $name || '.' === $name[0] || 'index.php' === strtolower( $name ) || ( $top && 0 === strpos( $name, 'mcupr-' ) );
}

/**
 * A clean relative path ('' = the uploads folder) or a WP_Error.
 * Only "a/b/c" shapes pass: no "..", no empty or hidden parts, no backslash, no control characters.
 */
function mcupr_fm_clean( $rel ) {
	$rel = (string) $rel;
	if ( false !== strpos( $rel, "\0" ) || preg_match( '/[\x00-\x1f\x7f\\\\]/', $rel ) ) {
		return new WP_Error( 'path', __( 'That place is not allowed.', 'mcupr' ) );
	}
	$parts = array();
	foreach ( explode( '/', trim( $rel, '/' ) ) as $i => $p ) {
		if ( '' === $p ) {
			continue;
		}
		if ( '..' === $p || mcupr_fm_hidden( $p, 0 === count( $parts ) ) ) {
			return new WP_Error( 'path', __( 'That place is not allowed.', 'mcupr' ) );
		}
		$parts[] = $p;
	}
	return implode( '/', $parts );
}

/**
 * The real path of a relative path that must exist, inside uploads, or a WP_Error.
 *
 * @param string $rel  Clean or raw relative path.
 * @param string $kind 'any', 'dir' or 'file'.
 */
function mcupr_fm_abs( $rel, $kind = 'any' ) {
	$root = mcupr_fm_root();
	$rel  = mcupr_fm_clean( $rel );
	if ( '' === $root || is_wp_error( $rel ) ) {
		return is_wp_error( $rel ) ? $rel : new WP_Error( 'root', __( 'The uploads folder is not available.', 'mcupr' ) );
	}
	$abs = '' === $rel ? $root : $root . '/' . $rel;
	$real = realpath( $abs );
	if ( false === $real ) {
		return new WP_Error( 'missing', __( 'That item does not exist any more.', 'mcupr' ) );
	}
	$real = wp_normalize_path( $real );
	if ( $real !== $root && 0 !== strpos( $real, $root . '/' ) ) {
		return new WP_Error( 'path', __( 'That place is not allowed.', 'mcupr' ) ); // a link that leads out of uploads
	}
	if ( 'dir' === $kind && ! is_dir( $real ) ) {
		return new WP_Error( 'kind', __( 'That is not a folder.', 'mcupr' ) );
	}
	if ( 'file' === $kind && ! is_file( $real ) ) {
		return new WP_Error( 'kind', __( 'That is not a file.', 'mcupr' ) );
	}
	return $real;
}

/** A name for a file or a folder as typed by a person: spaces and Thai letters stay, what a file system or a server would misread goes. */
function mcupr_fm_name( $name, $file = true ) {
	$name = wp_strip_all_tags( (string) $name );
	$name = preg_replace( '/[\x00-\x1f\x7f\/\\\\:*?"<>|%#]+/u', '', $name );
	$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ), " .\t" );
	if ( '' === $name || mcupr_fm_hidden( $name ) || strlen( $name ) > 150 ) {
		return new WP_Error( 'name', __( 'That name cannot be used.', 'mcupr' ) );
	}
	if ( preg_match( '/\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|bash|asp|aspx|jsp|exe|bat|cmd|htaccess|htpasswd)(\.|$)/i', $name ) ) {
		return new WP_Error( 'name', __( 'A name with a script type in it is not allowed.', 'mcupr' ) );
	}
	if ( $file ) {
		$t = wp_check_filetype( $name, get_allowed_mime_types() );
		if ( empty( $t['ext'] ) ) {
			return new WP_Error( 'type', __( 'That file type is not allowed on this website.', 'mcupr' ) );
		}
	}
	return $name;
}

/** The place of an item on its folder: array( parent rel, name ). */
function mcupr_fm_split( $rel ) {
	$p = strrpos( $rel, '/' );
	return false === $p ? array( '', $rel ) : array( substr( $rel, 0, $p ), substr( $rel, $p + 1 ) );
}

/* ------------------------------------------------------------------ what the Media library owns */

/**
 * The files of the Media library directly in a folder: rel path => attachment id (the picture and every size of it).
 */
function mcupr_fm_managed_in( $dir_rel ) {
	global $wpdb;
	$out  = array();
	$like = '' === $dir_rel ? '%' : $wpdb->esc_like( $dir_rel ) . '/%';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $rows as $r ) {
		$f = (string) $r->meta_value;
		list( $d, $n ) = mcupr_fm_split( $f );
		if ( $d !== $dir_rel ) {
			continue;
		}
		$id          = (int) $r->post_id;
		$out[ $f ]   = $id;
		$meta        = wp_get_attachment_metadata( $id );
		$sizes       = is_array( $meta ) && ! empty( $meta['sizes'] ) ? $meta['sizes'] : array();
		foreach ( $sizes as $s ) {
			if ( ! empty( $s['file'] ) ) {
				$out[ ( '' === $d ? '' : $d . '/' ) . $s['file'] ] = $id;
			}
		}
		if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
			$out[ ( '' === $d ? '' : $d . '/' ) . $meta['original_image'] ] = $id;
		}
	}
	return $out;
}

/** Does a folder (or a file) have anything of the Media library in it or below it? */
function mcupr_fm_has_managed( $rel, $is_dir ) {
	global $wpdb;
	if ( ! $is_dir ) {
		list( $d, ) = mcupr_fm_split( $rel );
		return isset( mcupr_fm_managed_in( $d )[ $rel ] );
	}
	$like = $wpdb->esc_like( $rel ) . '/%';
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/* ------------------------------------------------------------------ colour tags */

/** The seven tags: id => array( name, colour ). The names can be changed, the colours are the ones of the Finder. */
function mcupr_fm_tag_defs() {
	$colors = array( 1 => '#ff453a', 2 => '#ff9f0a', 3 => '#ffd60a', 4 => '#32d74b', 5 => '#0a84ff', 6 => '#bf5af2', 7 => '#8e8e93' );
	$names  = array( 1 => __( 'Red', 'mcupr' ), 2 => __( 'Orange', 'mcupr' ), 3 => __( 'Yellow', 'mcupr' ), 4 => __( 'Green', 'mcupr' ), 5 => __( 'Blue', 'mcupr' ), 6 => __( 'Purple', 'mcupr' ), 7 => __( 'Gray', 'mcupr' ) );
	$saved  = get_option( 'mcupr_fm_tagnames', array() );
	$out    = array();
	foreach ( $colors as $id => $c ) {
		$n         = is_array( $saved ) && isset( $saved[ $id ] ) && '' !== trim( (string) $saved[ $id ] ) ? (string) $saved[ $id ] : $names[ $id ];
		$out[ $id ] = array( 'id' => $id, 'name' => $n, 'color' => $c );
	}
	return $out;
}

/** The map path => array( tag ids ). */
function mcupr_fm_tags_all() {
	$t = get_option( 'mcupr_fm_tags', array() );
	return is_array( $t ) ? $t : array();
}

function mcupr_fm_tags_save( $map ) {
	foreach ( $map as $k => $v ) {
		if ( ! $v ) {
			unset( $map[ $k ] );
		}
	}
	update_option( 'mcupr_fm_tags', $map, false );
}

/** An item was moved or renamed: its tags (and those of what is inside a folder) follow. */
function mcupr_fm_tags_follow( $from, $to ) {
	$map = mcupr_fm_tags_all();
	$new = array();
	foreach ( $map as $k => $v ) {
		if ( $k === $from ) {
			$new[ $to ] = $v;
		} elseif ( 0 === strpos( $k, $from . '/' ) ) {
			$new[ $to . substr( $k, strlen( $from ) ) ] = $v;
		} else {
			$new[ $k ] = $v;
		}
	}
	mcupr_fm_tags_save( $new );
}

/** An item was deleted: its tags (and those of what was inside) go. */
function mcupr_fm_tags_forget( $rel ) {
	$map = mcupr_fm_tags_all();
	foreach ( array_keys( $map ) as $k ) {
		if ( $k === $rel || 0 === strpos( $k, $rel . '/' ) ) {
			unset( $map[ $k ] );
		}
	}
	mcupr_fm_tags_save( $map );
}

/* ------------------------------------------------------------------ listing */

function mcupr_fm_kind( $ext ) {
	$ext = strtolower( $ext );
	if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'heic' ), true ) ) {
		return 'image';
	}
	if ( in_array( $ext, array( 'mp4', 'm4v', 'mov', 'webm', 'ogv', 'avi', 'mkv', 'wmv' ), true ) ) {
		return 'video';
	}
	if ( in_array( $ext, array( 'mp3', 'm4a', 'wav', 'ogg', 'oga', 'flac', 'aac' ), true ) ) {
		return 'audio';
	}
	if ( 'pdf' === $ext ) {
		return 'pdf';
	}
	if ( in_array( $ext, array( 'doc', 'docx', 'odt', 'rtf', 'txt', 'md' ), true ) ) {
		return 'doc';
	}
	if ( in_array( $ext, array( 'xls', 'xlsx', 'ods', 'csv' ), true ) ) {
		return 'sheet';
	}
	if ( in_array( $ext, array( 'ppt', 'pptx', 'odp', 'pps', 'ppsx' ), true ) ) {
		return 'slides';
	}
	if ( in_array( $ext, array( 'zip', 'rar', '7z', 'tar', 'gz' ), true ) ) {
		return 'archive';
	}
	return 'other';
}

/** One entry of a folder as the screen needs it. */
function mcupr_fm_entry( $rel, $abs, $managed, $tags_map, $base_url ) {
	$is_dir = is_dir( $abs );
	list( , $name ) = mcupr_fm_split( $rel );
	$ext    = $is_dir ? '' : strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$e      = array(
		'name'   => $name,
		'path'   => $rel,
		'dir'    => $is_dir,
		'size'   => $is_dir ? 0 : (int) @filesize( $abs ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
		'mtime'  => (int) @filemtime( $abs ), // phpcs:ignore WordPress.PHP.NoSilencedErrors
		'ext'    => $ext,
		'kind'   => $is_dir ? 'folder' : mcupr_fm_kind( $ext ),
		'tags'   => isset( $tags_map[ $rel ] ) ? array_values( array_map( 'intval', $tags_map[ $rel ] ) ) : array(),
		'locked' => $is_dir ? mcupr_fm_has_managed( $rel, true ) : isset( $managed[ $rel ] ),
		'att'    => ! $is_dir && isset( $managed[ $rel ] ) ? (int) $managed[ $rel ] : 0,
		'url'    => $is_dir ? '' : $base_url . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $rel ) ) ),
	);
	if ( $is_dir ) {
		$n = 0;
		foreach ( (array) scandir( $abs ) as $c ) {
			if ( '.' !== $c && '..' !== $c && ! mcupr_fm_hidden( $c ) ) {
				$n++;
			}
		}
		$e['count'] = $n;
	}
	return $e;
}

/** What is in a folder (hidden names left out), folders first. */
function mcupr_fm_list( $rel, $dirs_only = false ) {
	$abs = mcupr_fm_abs( $rel, 'dir' );
	if ( is_wp_error( $abs ) ) {
		return $abs;
	}
	$rel     = mcupr_fm_clean( $rel );
	$u       = wp_get_upload_dir();
	$tags    = mcupr_fm_tags_all();
	$managed = mcupr_fm_managed_in( $rel );
	$items   = array();
	foreach ( (array) scandir( $abs ) as $n ) {
		if ( '.' === $n || '..' === $n || mcupr_fm_hidden( $n, '' === $rel ) ) {
			continue;
		}
		$child = $abs . '/' . $n;
		if ( is_link( $child ) ) {
			continue; // links are never followed
		}
		if ( $dirs_only && ! is_dir( $child ) ) {
			continue;
		}
		$items[] = mcupr_fm_entry( ( '' === $rel ? '' : $rel . '/' ) . $n, $child, $managed, $tags, $u['baseurl'] );
	}
	usort( $items, static function ( $a, $b ) {
		return $a['dir'] === $b['dir'] ? strnatcasecmp( $a['name'], $b['name'] ) : ( $a['dir'] ? -1 : 1 );
	} );
	return $items;
}

/** The way back to the top: array( array( name, path ) ). */
function mcupr_fm_crumbs( $rel ) {
	$out = array( array( 'name' => __( 'Uploads', 'mcupr' ), 'path' => '' ) );
	$acc = '';
	foreach ( '' === $rel ? array() : explode( '/', $rel ) as $p ) {
		$acc  .= ( '' === $acc ? '' : '/' ) . $p;
		$out[] = array( 'name' => $p, 'path' => $acc );
	}
	return $out;
}

/** How many items carry each tag (those that still exist). */
function mcupr_fm_tag_counts() {
	$root = mcupr_fm_root();
	$out  = array_fill_keys( array_keys( mcupr_fm_tag_defs() ), 0 );
	foreach ( mcupr_fm_tags_all() as $path => $ids ) {
		if ( '' === $root || ! file_exists( $root . '/' . $path ) ) {
			continue;
		}
		foreach ( (array) $ids as $id ) {
			if ( isset( $out[ (int) $id ] ) ) {
				$out[ (int) $id ]++;
			}
		}
	}
	return $out;
}

/** The items that carry a tag, wherever they are. */
function mcupr_fm_list_tag( $tag ) {
	$root    = mcupr_fm_root();
	$u       = wp_get_upload_dir();
	$tags    = mcupr_fm_tags_all();
	$items   = array();
	foreach ( $tags as $path => $ids ) {
		if ( ! in_array( (int) $tag, array_map( 'intval', (array) $ids ), true ) || is_wp_error( mcupr_fm_clean( $path ) ) ) {
			continue;
		}
		$abs = mcupr_fm_abs( $path );
		if ( is_wp_error( $abs ) ) {
			continue;
		}
		list( $d, ) = mcupr_fm_split( $path );
		$items[] = mcupr_fm_entry( $path, $abs, mcupr_fm_managed_in( $d ), $tags, $u['baseurl'] );
	}
	usort( $items, static function ( $a, $b ) { return strnatcasecmp( $a['name'], $b['name'] ); } );
	return $items;
}

/* ------------------------------------------------------------------ operations */

/** A folder inside a folder. */
function mcupr_fm_mkdir( $parent_rel, $name ) {
	$parent = mcupr_fm_abs( $parent_rel, 'dir' );
	$name   = mcupr_fm_name( $name, false );
	if ( is_wp_error( $parent ) ) {
		return $parent;
	}
	if ( is_wp_error( $name ) ) {
		return $name;
	}
	if ( file_exists( $parent . '/' . $name ) ) {
		return new WP_Error( 'exists', __( 'There is already an item with that name here.', 'mcupr' ) );
	}
	if ( ! wp_mkdir_p( $parent . '/' . $name ) ) {
		return new WP_Error( 'fs', __( 'The folder could not be made (check the rights of the uploads folder).', 'mcupr' ) );
	}
	$rel = mcupr_fm_clean( $parent_rel );
	return ( '' === $rel ? '' : $rel . '/' ) . $name;
}

/** Can an item be changed here? (not the top, not what the Media library owns) */
function mcupr_fm_changeable( $rel ) {
	$rel = mcupr_fm_clean( $rel );
	if ( is_wp_error( $rel ) ) {
		return $rel;
	}
	if ( '' === $rel ) {
		return new WP_Error( 'top', __( 'The uploads folder itself cannot be changed.', 'mcupr' ) );
	}
	$abs = mcupr_fm_abs( $rel );
	if ( is_wp_error( $abs ) ) {
		return $abs;
	}
	if ( is_link( $abs ) ) {
		return new WP_Error( 'link', __( 'That place is not allowed.', 'mcupr' ) );
	}
	if ( mcupr_fm_has_managed( $rel, is_dir( $abs ) ) ) {
		return new WP_Error( 'managed', is_dir( $abs ) ? __( 'This folder holds files of the Media library. Move or rename those in the Media library, where the pictures in posts follow.', 'mcupr' ) : __( 'This file belongs to the Media library. Change it there, so the pictures in posts follow.', 'mcupr' ) );
	}
	return $abs;
}

function mcupr_fm_rename( $rel, $new_name ) {
	$abs = mcupr_fm_changeable( $rel );
	if ( is_wp_error( $abs ) ) {
		return $abs;
	}
	$rel  = mcupr_fm_clean( $rel );
	$dir  = is_dir( $abs );
	$name = mcupr_fm_name( $new_name, ! $dir );
	if ( is_wp_error( $name ) ) {
		return $name;
	}
	list( $parent, $old ) = mcupr_fm_split( $rel );
	if ( $old === $name ) {
		return $rel;
	}
	$target = dirname( $abs ) . '/' . $name;
	if ( file_exists( $target ) ) {
		return new WP_Error( 'exists', __( 'There is already an item with that name here.', 'mcupr' ) );
	}
	if ( ! @rename( $abs, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return new WP_Error( 'fs', __( 'The item could not be renamed.', 'mcupr' ) );
	}
	$to = ( '' === $parent ? '' : $parent . '/' ) . $name;
	mcupr_fm_tags_follow( $rel, $to );
	return $to;
}

/** Move items into a folder. Returns the new paths or a WP_Error naming the first item that could not move (the ones before it did move). */
function mcupr_fm_move( $rels, $dest_rel ) {
	$dest = mcupr_fm_abs( $dest_rel, 'dir' );
	if ( is_wp_error( $dest ) ) {
		return $dest;
	}
	$dest_rel = mcupr_fm_clean( $dest_rel );
	$out      = array();
	foreach ( $rels as $rel ) {
		$abs = mcupr_fm_changeable( $rel );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$rel = mcupr_fm_clean( $rel );
		list( $parent, $name ) = mcupr_fm_split( $rel );
		if ( $parent === $dest_rel ) {
			continue; // already there
		}
		if ( is_dir( $abs ) && ( $dest_rel === $rel || 0 === strpos( $dest_rel . '/', $rel . '/' ) ) ) {
			return new WP_Error( 'into', __( 'A folder cannot be moved into itself.', 'mcupr' ) );
		}
		if ( file_exists( $dest . '/' . $name ) ) {
			/* translators: %s: name */
			return new WP_Error( 'exists', sprintf( __( 'There is already an item called "%s" in that folder.', 'mcupr' ), $name ) );
		}
		if ( ! @rename( $abs, $dest . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return new WP_Error( 'fs', __( 'The item could not be moved.', 'mcupr' ) );
		}
		$to = ( '' === $dest_rel ? '' : $dest_rel . '/' ) . $name;
		mcupr_fm_tags_follow( $rel, $to );
		$out[] = $to;
	}
	return $out;
}

/** What a delete would remove: array( files, folders, bytes ). */
function mcupr_fm_weigh( $abs ) {
	$f = 0;
	$d = 0;
	$b = 0;
	if ( is_dir( $abs ) && ! is_link( $abs ) ) {
		$d++;
		foreach ( (array) scandir( $abs ) as $c ) {
			if ( '.' === $c || '..' === $c ) {
				continue;
			}
			$w  = mcupr_fm_weigh( $abs . '/' . $c );
			$f += $w[0];
			$d += $w[1];
			$b += $w[2];
		}
	} else {
		$f++;
		$b += (int) @filesize( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	return array( $f, $d, $b );
}

function mcupr_fm_rm( $abs ) {
	if ( is_dir( $abs ) && ! is_link( $abs ) ) {
		foreach ( (array) scandir( $abs ) as $c ) {
			if ( '.' !== $c && '..' !== $c ) {
				mcupr_fm_rm( $abs . '/' . $c );
			}
		}
		return @rmdir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}
	return wp_delete_file_from_directory( $abs, mcupr_fm_root() );
}

/* ------------------------------------------------------------------ the trash */

const MCUPR_FM_TRASH = '.mcupr-trash';

/** The real path of the trash (made, with a file that closes it to the web), or '' when it cannot be made. */
function mcupr_fm_trash_dir() {
	$root = mcupr_fm_root();
	if ( '' === $root ) {
		return '';
	}
	$dir = $root . '/' . MCUPR_FM_TRASH;
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	if ( ! is_file( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}
	if ( ! is_file( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/** What is in the trash: id => array( name, from (the path it had), time, dir, files, folders, bytes, tags ). Entries whose files are gone are dropped. */
function mcupr_fm_trash_all() {
	$all = get_option( 'mcupr_fm_trash', array() );
	$all = is_array( $all ) ? $all : array();
	$dir = mcupr_fm_root() . '/' . MCUPR_FM_TRASH;
	$out = array();
	foreach ( $all as $id => $e ) {
		$id = (string) $id; // (an id of sixteen digits only is a number as an array key: it was thrown out as "not an id", with its files left behind)
		if ( preg_match( '/^[a-f0-9]{16}$/', $id ) && ! empty( $e['name'] ) && file_exists( $dir . '/' . $id . '/' . $e['name'] ) ) {
			$out[ $id ] = $e;
		}
	}
	if ( count( $out ) !== count( $all ) ) {
		update_option( 'mcupr_fm_trash', $out, false );
	}
	return $out;
}

/** The trash for the screen: newest first, in the shape of the list entries. */
function mcupr_fm_trash_items() {
	$items = array();
	foreach ( mcupr_fm_trash_all() as $id => $e ) {
		$ext    = ! empty( $e['dir'] ) ? '' : strtolower( pathinfo( $e['name'], PATHINFO_EXTENSION ) );
		$items[] = array(
			'name'    => $e['name'],
			'path'    => (string) $id,
			'trash'   => true,
			'dir'     => ! empty( $e['dir'] ),
			'size'    => (int) $e['bytes'],
			'mtime'   => (int) $e['time'],
			'ext'     => $ext,
			'kind'    => ! empty( $e['dir'] ) ? 'folder' : mcupr_fm_kind( $ext ),
			'tags'    => isset( $e['tags'][ $e['from'] ] ) ? array_values( array_map( 'intval', (array) $e['tags'][ $e['from'] ] ) ) : array(),
			'locked'  => false,
			'att'     => 0,
			'url'     => '',
			'from'    => (string) mcupr_fm_split( (string) $e['from'] )[0],
			'files'   => (int) $e['files'],
			'folders' => (int) $e['folders'],
		);
	}
	usort( $items, static function ( $a, $b ) { return $b['mtime'] - $a['mtime']; } );
	return $items;
}

function mcupr_fm_trash_count() {
	return count( mcupr_fm_trash_all() );
}

/**
 * Move items to the trash (nothing is lost): their colour tags go with them. Returns the ids in the trash or a WP_Error.
 * What the Media library owns, the top and anything outside uploads are refused, as before.
 */
function mcupr_fm_delete( $rels ) {
	$abss = array();
	foreach ( $rels as $rel ) {
		$abs = mcupr_fm_changeable( $rel );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$abss[ mcupr_fm_clean( $rel ) ] = $abs;
	}
	$dir = mcupr_fm_trash_dir();
	if ( '' === $dir ) {
		return new WP_Error( 'fs', __( 'The trash could not be made (check the rights of the uploads folder).', 'mcupr' ) );
	}
	$all = mcupr_fm_trash_all();
	$map = mcupr_fm_tags_all();
	$ids = array();
	foreach ( $abss as $rel => $abs ) {
		$id   = bin2hex( random_bytes( 8 ) );
		$name = basename( $abs );
		if ( ! wp_mkdir_p( $dir . '/' . $id ) || ! @rename( $abs, $dir . '/' . $id . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			@rmdir( $dir . '/' . $id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			update_option( 'mcupr_fm_trash', $all, false );
			return new WP_Error( 'fs', __( 'Something could not be moved to the trash (check the rights of the uploads folder).', 'mcupr' ) );
		}
		$w    = mcupr_fm_weigh( $dir . '/' . $id . '/' . $name );
		$mine = array();
		foreach ( $map as $k => $v ) {
			if ( $k === $rel || 0 === strpos( $k, $rel . '/' ) ) {
				$mine[ $k ] = $v;
			}
		}
		$all[ $id ] = array( 'name' => $name, 'from' => $rel, 'time' => time(), 'dir' => is_dir( $dir . '/' . $id . '/' . $name ), 'files' => $w[0], 'folders' => $w[1], 'bytes' => $w[2], 'tags' => $mine );
		mcupr_fm_tags_forget( $rel );
		$ids[] = $id;
	}
	update_option( 'mcupr_fm_trash', $all, false );
	return $ids;
}

/** A name that is free in a folder: "name 2.ext". */
function mcupr_fm_free_name( $abs_dir, $name ) {
	if ( ! file_exists( $abs_dir . '/' . $name ) ) {
		return $name;
	}
	$ext  = pathinfo( $name, PATHINFO_EXTENSION );
	$base = '' === $ext ? $name : substr( $name, 0, -( strlen( $ext ) + 1 ) );
	for ( $n = 2; $n < 1000; $n++ ) {
		$try = $base . ' ' . $n . ( '' === $ext ? '' : '.' . $ext );
		if ( ! file_exists( $abs_dir . '/' . $try ) ) {
			return $try;
		}
	}
	return $base . ' ' . wp_generate_password( 6, false ) . ( '' === $ext ? '' : '.' . $ext );
}

/** Put items back where they were (the folder is made again if it is gone; a name that is taken gets a number). Returns the new paths. */
function mcupr_fm_trash_restore( $ids ) {
	$all   = mcupr_fm_trash_all();
	$trash = mcupr_fm_root() . '/' . MCUPR_FM_TRASH;
	$out   = array();
	foreach ( $ids as $id ) {
		if ( ! isset( $all[ $id ] ) ) {
			return new WP_Error( 'missing', __( 'That item is not in the trash any more.', 'mcupr' ) );
		}
		$e = $all[ $id ];
		list( $parent, $old ) = mcupr_fm_split( (string) $e['from'] );
		$parent = mcupr_fm_clean( $parent );
		if ( is_wp_error( $parent ) ) {
			$parent = '';
		}
		$root = mcupr_fm_root();
		$dest = '' === $parent ? $root : $root . '/' . $parent;
		if ( ! is_dir( $dest ) && ( ! wp_mkdir_p( $dest ) || ! is_dir( $dest ) ) ) {
			$parent = '';
			$dest   = $root;
		}
		$real = realpath( $dest );
		if ( false === $real || ( wp_normalize_path( $real ) !== $root && 0 !== strpos( wp_normalize_path( $real ), $root . '/' ) ) ) {
			return new WP_Error( 'path', __( 'That place is not allowed.', 'mcupr' ) );
		}
		$name = mcupr_fm_free_name( $dest, $e['name'] );
		if ( ! @rename( $trash . '/' . $id . '/' . $e['name'], $dest . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return new WP_Error( 'fs', __( 'The item could not be put back.', 'mcupr' ) );
		}
		@rmdir( $trash . '/' . $id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		$to  = ( '' === $parent ? '' : $parent . '/' ) . $name;
		$map = mcupr_fm_tags_all();
		foreach ( (array) $e['tags'] as $k => $v ) {
			$nk = $to . substr( $k, strlen( (string) $e['from'] ) );
			if ( ! is_wp_error( mcupr_fm_clean( $nk ) ) && file_exists( $root . '/' . $nk ) ) {
				$map[ $nk ] = $v;
			}
		}
		mcupr_fm_tags_save( $map );
		unset( $all[ $id ] );
		$out[] = $to;
	}
	update_option( 'mcupr_fm_trash', $all, false );
	return $out;
}

/** Delete items from the trash for good (ids; none = all). Returns array( files, folders ) removed. */
function mcupr_fm_trash_purge( $ids = null, $older_than = 0 ) {
	$all   = mcupr_fm_trash_all();
	$trash = mcupr_fm_root() . '/' . MCUPR_FM_TRASH;
	$files = 0;
	$dirs  = 0;
	foreach ( $all as $id => $e ) {
		if ( null !== $ids && ! in_array( (string) $id, array_map( 'strval', (array) $ids ), true ) ) {
			continue;
		}
		if ( $older_than && (int) $e['time'] > $older_than ) {
			continue;
		}
		mcupr_fm_rm( $trash . '/' . $id );
		if ( ! file_exists( $trash . '/' . $id ) ) {
			$files += (int) $e['files'];
			$dirs  += (int) $e['folders'];
			unset( $all[ $id ] );
		}
	}
	update_option( 'mcupr_fm_trash', $all, false );
	return array( $files, $dirs );
}

/** The trash empties itself of what is older than 30 days (once a day). */
add_action(
	'admin_init',
	static function () {
		if ( ! wp_next_scheduled( 'mcupr_fm_purge' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mcupr_fm_purge' );
		}
	}
);
add_action(
	'mcupr_fm_purge',
	static function () {
		if ( '' !== mcupr_fm_root() && is_dir( mcupr_fm_root() . '/' . MCUPR_FM_TRASH ) ) {
			mcupr_fm_trash_purge( null, time() - (int) apply_filters( 'mcupr_fm_trash_days', 30 ) * DAY_IN_SECONDS );
		}
		mcupr_fm_tags_prune();
	}
);

/**
 * The map of tags follows a file that is moved or renamed, but a file deleted outside the file manager (by FTP, by a
 * plugin, by emptying the trash) leaves its row behind, and the map is rewritten whole on every change. Once a day the
 * rows whose file or folder is no longer there are dropped, so the map stays the size of what is really on disk.
 */
function mcupr_fm_tags_prune() {
	$root = mcupr_fm_root();
	if ( '' === $root ) {
		return 0;
	}
	$map  = mcupr_fm_tags_all();
	$gone = 0;
	foreach ( array_keys( $map ) as $path ) {
		if ( ! file_exists( $root . '/' . ltrim( (string) $path, '/' ) ) ) {
			unset( $map[ $path ] );
			$gone++;
		}
	}
	if ( $gone ) {
		mcupr_fm_tags_save( $map );
	}
	return $gone;
}

/** Put tags on items (mode add), take them off (remove) or replace them (set). */
function mcupr_fm_tag( $rels, $tag_ids, $mode ) {
	$defs = mcupr_fm_tag_defs();
	$ids  = array_values( array_unique( array_filter( array_map( 'intval', (array) $tag_ids ), static function ( $i ) use ( $defs ) { return isset( $defs[ $i ] ); } ) ) );
	$map  = mcupr_fm_tags_all();
	foreach ( $rels as $rel ) {
		$abs = mcupr_fm_abs( $rel );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$rel = mcupr_fm_clean( $rel );
		if ( '' === $rel ) {
			continue;
		}
		$cur = isset( $map[ $rel ] ) ? array_map( 'intval', (array) $map[ $rel ] ) : array();
		if ( 'set' === $mode ) {
			$cur = $ids;
		} elseif ( 'remove' === $mode ) {
			$cur = array_values( array_diff( $cur, $ids ) );
		} else {
			$cur = array_values( array_unique( array_merge( $cur, $ids ) ) );
		}
		$map[ $rel ] = $cur;
	}
	mcupr_fm_tags_save( $map );
	return true;
}

/** Files typed on the screen or dropped on it: into a folder, with the rules of names and types. */
function mcupr_fm_upload( $dest_rel, $files ) {
	$dest = mcupr_fm_abs( $dest_rel, 'dir' );
	if ( is_wp_error( $dest ) ) {
		return $dest;
	}
	$dest_rel = mcupr_fm_clean( $dest_rel );
	$done     = array();
	$bad      = array();
	$count    = isset( $files['name'] ) ? count( (array) $files['name'] ) : 0;
	for ( $i = 0; $i < $count; $i++ ) {
		$orig = (string) $files['name'][ $i ];
		$tmp  = (string) $files['tmp_name'][ $i ];
		$err  = (int) $files['error'][ $i ];
		if ( UPLOAD_ERR_OK !== $err || ! is_uploaded_file( $tmp ) ) {
			$bad[] = array( $orig, UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ? __( 'The file is too big.', 'mcupr' ) : __( 'The file did not arrive.', 'mcupr' ) );
			continue;
		}
		$name = mcupr_fm_name( $orig, true );
		if ( is_wp_error( $name ) ) {
			$bad[] = array( $orig, $name->get_error_message() );
			continue;
		}
		if ( filesize( $tmp ) > wp_max_upload_size() ) {
			$bad[] = array( $orig, __( 'The file is too big.', 'mcupr' ) );
			continue;
		}
		$check = wp_check_filetype_and_ext( $tmp, $name, get_allowed_mime_types() ); // the content must fit the type
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			$bad[] = array( $orig, __( 'The content does not fit the file type.', 'mcupr' ) );
			continue;
		}
		if ( $check['proper_filename'] ) {
			$name = $check['proper_filename'];
		}
		$final = wp_unique_filename( $dest, $name ); // never replaces a file
		if ( ! move_uploaded_file( $tmp, $dest . '/' . $final ) ) {
			$bad[] = array( $orig, __( 'The file could not be saved.', 'mcupr' ) );
			continue;
		}
		@chmod( $dest . '/' . $final, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		$done[] = ( '' === $dest_rel ? '' : $dest_rel . '/' ) . $final;
	}
	return array( 'done' => $done, 'bad' => $bad );
}

/* ------------------------------------------------------------------ the requests (admin-ajax) */

/** Only administrators with a good nonce: every request goes through here. */
function mcupr_fm_guard() {
	if ( ! check_ajax_referer( 'mcupr_fm', '_wpnonce', false ) || ! current_user_can( MCUPR_FM_CAP ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'mcupr' ) ), 403 );
	}
}

function mcupr_fm_post_paths() {
	$p = isset( $_POST['paths'] ) ? (array) wp_unslash( $_POST['paths'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	return array_slice( array_values( array_filter( array_map( 'strval', $p ), 'strlen' ) ), 0, 500 );
}

function mcupr_fm_req( $key ) {
	return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
}

function mcupr_fm_fail( $e ) {
	wp_send_json_error( array( 'message' => $e->get_error_message(), 'code' => $e->get_error_code() ) );
}

$mcupr_fm_actions = array(
	'list'     => static function () {
		$tag = (int) mcupr_fm_req( 'tag' );
		if ( $tag ) {
			wp_send_json_success( array( 'path' => '', 'tag' => $tag, 'crumbs' => array(), 'items' => mcupr_fm_list_tag( $tag ), 'counts' => mcupr_fm_tag_counts(), 'trash' => mcupr_fm_trash_count() ) );
		}
		$rel = mcupr_fm_clean( mcupr_fm_req( 'path' ) );
		if ( is_wp_error( $rel ) ) {
			mcupr_fm_fail( $rel );
		}
		$items = mcupr_fm_list( $rel, '1' === mcupr_fm_req( 'dirs' ) );
		if ( is_wp_error( $items ) ) {
			mcupr_fm_fail( $items );
		}
		wp_send_json_success( array( 'path' => $rel, 'crumbs' => mcupr_fm_crumbs( $rel ), 'items' => $items, 'counts' => mcupr_fm_tag_counts(), 'trash' => mcupr_fm_trash_count() ) );
	},
	'mkdir'    => static function () {
		$r = mcupr_fm_mkdir( mcupr_fm_req( 'path' ), mcupr_fm_req( 'name' ) );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'path' => $r ) );
	},
	'rename'   => static function () {
		$r = mcupr_fm_rename( mcupr_fm_req( 'path' ), mcupr_fm_req( 'name' ) );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'path' => $r ) );
	},
	'move'     => static function () {
		$r = mcupr_fm_move( mcupr_fm_post_paths(), mcupr_fm_req( 'dest' ) );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'paths' => $r ) );
	},
	'delete'   => static function () {
		$r = mcupr_fm_delete( mcupr_fm_post_paths() );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'ids' => $r, 'trash' => mcupr_fm_trash_count() ) );
	},
	'trash'    => static function () {
		wp_send_json_success( array( 'items' => mcupr_fm_trash_items(), 'counts' => mcupr_fm_tag_counts(), 'trash' => mcupr_fm_trash_count() ) );
	},
	'restore'  => static function () {
		$r = mcupr_fm_trash_restore( mcupr_fm_post_paths() );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'paths' => $r, 'trash' => mcupr_fm_trash_count(), 'counts' => mcupr_fm_tag_counts() ) );
	},
	'purge'    => static function () {
		$ids = mcupr_fm_post_paths();
		$r   = mcupr_fm_trash_purge( 'all' === mcupr_fm_req( 'scope' ) ? null : $ids );
		wp_send_json_success( array( 'files' => $r[0], 'folders' => $r[1], 'trash' => mcupr_fm_trash_count() ) );
	},
	'tag'      => static function () {
		$ids  = isset( $_POST['tags'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tags'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$mode = in_array( mcupr_fm_req( 'mode' ), array( 'add', 'remove', 'set' ), true ) ? mcupr_fm_req( 'mode' ) : 'add';
		$r    = mcupr_fm_tag( mcupr_fm_post_paths(), $ids, $mode );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( array( 'counts' => mcupr_fm_tag_counts() ) );
	},
	'tagname'  => static function () {
		$id    = (int) mcupr_fm_req( 'id' );
		$defs  = mcupr_fm_tag_defs();
		$name  = trim( sanitize_text_field( mcupr_fm_req( 'name' ) ) );
		if ( ! isset( $defs[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'No such tag.', 'mcupr' ) ) );
		}
		$saved = get_option( 'mcupr_fm_tagnames', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$saved[ $id ] = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 30 ) : substr( $name, 0, 30 );
		update_option( 'mcupr_fm_tagnames', $saved, false );
		wp_send_json_success( array( 'tags' => array_values( mcupr_fm_tag_defs() ) ) );
	},
	'upload'   => static function () {
		$files = isset( $_FILES['files'] ) ? $_FILES['files'] : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$r     = mcupr_fm_upload( mcupr_fm_req( 'path' ), $files );
		is_wp_error( $r ) ? mcupr_fm_fail( $r ) : wp_send_json_success( $r );
	},
);
foreach ( array_keys( $mcupr_fm_actions ) as $mcupr_fm_act ) {
	add_action(
		'wp_ajax_mcupr_fm_' . $mcupr_fm_act,
		static function () use ( $mcupr_fm_act, $mcupr_fm_actions ) {
			mcupr_fm_guard();
			call_user_func( $mcupr_fm_actions[ $mcupr_fm_act ] );
		}
	);
}

/** A file, or several items as a zip, to save. GET with the nonce. */
add_action(
	'wp_ajax_mcupr_fm_download',
	static function () {
		if ( ! check_ajax_referer( 'mcupr_fm', '_wpnonce', false ) || ! current_user_can( MCUPR_FM_CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		$raw   = isset( $_GET['p'] ) ? (array) wp_unslash( $_GET['p'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$items = array();
		foreach ( array_slice( $raw, 0, 500 ) as $rel ) {
			$abs = mcupr_fm_abs( (string) $rel );
			if ( is_wp_error( $abs ) || is_link( $abs ) ) {
				wp_die( esc_html__( 'That place is not allowed.', 'mcupr' ), '', array( 'response' => 400 ) );
			}
			$items[ mcupr_fm_clean( (string) $rel ) ] = $abs;
		}
		if ( ! $items ) {
			wp_die( esc_html__( 'Nothing chosen.', 'mcupr' ), '', array( 'response' => 400 ) );
		}
		nocache_headers();
		if ( 1 === count( $items ) && is_file( reset( $items ) ) ) {
			$abs  = reset( $items );
			$name = basename( $abs );
			header( 'Content-Type: application/octet-stream' );
			header( "Content-Disposition: attachment; filename=\"" . rawurlencode( $name ) . "\"; filename*=UTF-8''" . rawurlencode( $name ) );
			header( 'Content-Length: ' . filesize( $abs ) );
			header( 'X-Content-Type-Options: nosniff' );
			readfile( $abs ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'This server cannot make zip files.', 'mcupr' ), '', array( 'response' => 500 ) );
		}
		$total = 0;
		$files = array();
		$add   = static function ( $abs, $zip_name ) use ( &$add, &$files, &$total ) {
			if ( is_link( $abs ) ) {
				return;
			}
			if ( is_dir( $abs ) ) {
				foreach ( (array) scandir( $abs ) as $c ) {
					if ( '.' !== $c && '..' !== $c && ! mcupr_fm_hidden( $c ) ) {
						$add( $abs . '/' . $c, $zip_name . '/' . $c );
					}
				}
				return;
			}
			$total += (int) filesize( $abs );
			$files[ $zip_name ] = $abs;
		};
		foreach ( $items as $rel => $abs ) {
			$add( $abs, basename( $abs ) );
		}
		if ( count( $files ) > 5000 || $total > 1024 * 1024 * 1024 ) {
			wp_die( esc_html__( 'That is too much for one zip file (over 5000 files or 1 GB). Choose less.', 'mcupr' ), '', array( 'response' => 413 ) );
		}
		$tmp = wp_tempnam( 'mcupr-fm' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'The zip file could not be made.', 'mcupr' ), '', array( 'response' => 500 ) );
		}
		foreach ( $files as $zn => $abs ) {
			$zip->addFile( $abs, $zn );
		}
		$zip->close();
		$name = ( 1 === count( $items ) ? basename( reset( $items ) ) : 'files' ) . '.zip';
		header( 'Content-Type: application/zip' );
		header( "Content-Disposition: attachment; filename=\"" . rawurlencode( $name ) . "\"; filename*=UTF-8''" . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		exit;
	}
);

/* ------------------------------------------------------------------ the page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'File manager', 'mcupr' ), __( 'File manager', 'mcupr' ), MCUPR_FM_CAP, 'mcupr-files', 'mcupr_fm_page' );
	}
);

function mcupr_fm_icons() {
	$out = array();
	foreach ( array( 'folder', 'file', 'file-text', 'image', 'film', 'music', 'archive', 'trash-2', 'upload', 'download', 'grid', 'list', 'search', 'chevron-left', 'chevron-right', 'rotate-ccw', 'tag', 'x', 'edit-2', 'folder-plus', 'lock', 'hard-drive', 'eye', 'external-link', 'info', 'check', 'refresh-cw' ) as $n ) {
		$out[ $n ] = mcupr_feather( $n, 18 );
	}
	return $out;
}

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false === strpos( (string) $hook, 'mcupr-files' ) ) {
			return;
		}
		wp_enqueue_style( 'mcupr-file-manager', get_theme_file_uri( 'assets/css/file-manager.css' ), array(), MCUPR_VERSION );
		wp_enqueue_script( 'mcupr-file-manager', get_theme_file_uri( 'assets/js/file-manager.js' ), array(), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-file-manager',
			'mcuprFM',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'mcupr_fm' ),
				'tags'  => array_values( mcupr_fm_tag_defs() ),
				'icons' => mcupr_fm_icons(),
				'max'   => (int) wp_max_upload_size(),
				'media' => admin_url( 'post.php?action=edit&post=' ),
				't'     => array(
					'title'      => __( 'File manager', 'mcupr' ),
					'uploads'    => __( 'Uploads', 'mcupr' ),
					'locations'  => __( 'Locations', 'mcupr' ),
					'tags'       => __( 'Tags', 'mcupr' ),
					'back'       => __( 'Back', 'mcupr' ),
					'forward'    => __( 'Forward', 'mcupr' ),
					'icons'      => __( 'As icons', 'mcupr' ),
					'list'       => __( 'As list', 'mcupr' ),
					'search'     => __( 'Search this folder', 'mcupr' ),
					'newFolder'  => __( 'New folder', 'mcupr' ),
					'untitled'   => __( 'untitled folder', 'mcupr' ),
					'upload'     => __( 'Upload', 'mcupr' ),
					'download'   => __( 'Download', 'mcupr' ),
					'delete'     => __( 'Delete', 'mcupr' ),
					'rename'     => __( 'Rename', 'mcupr' ),
					'open'       => __( 'Open', 'mcupr' ),
					'quick'      => __( 'Quick look', 'mcupr' ),
					'tagMenu'    => __( 'Tags', 'mcupr' ),
					'noTags'     => __( 'Remove all tags', 'mcupr' ),
					'name'       => __( 'Name', 'mcupr' ),
					'modified'   => __( 'Date modified', 'mcupr' ),
					'size'       => __( 'Size', 'mcupr' ),
					'kind'       => __( 'Kind', 'mcupr' ),
					'items'      => __( '%d items', 'mcupr' ),
					'selected'   => __( '%1$d of %2$d selected', 'mcupr' ),
					'empty'      => __( 'This folder is empty. Drag files here to upload.', 'mcupr' ),
					'noTagItems' => __( 'Nothing has this tag yet.', 'mcupr' ),
					'noMatch'    => __( 'Nothing matches.', 'mcupr' ),
					'locked'     => __( 'In the Media library: change it there', 'mcupr' ),
					'inMedia'    => __( 'Edit in the Media library', 'mcupr' ),
					'confirm'    => __( 'Delete for good?', 'mcupr' ),
					'confirmTxt' => __( 'This removes %1$d files and %2$d folders (%3$s) from the trash. It cannot be undone.', 'mcupr' ),
					'trash'      => __( 'Trash', 'mcupr' ),
					'trashEmpty' => __( 'The trash is empty. What you delete waits here for 30 days.', 'mcupr' ),
					'trashed'    => __( 'Moved to the trash.', 'mcupr' ),
					'undo'       => __( 'Undo', 'mcupr' ),
					'putBack'    => __( 'Put back', 'mcupr' ),
					'restored'   => __( 'Put back.', 'mcupr' ),
					'deleteNow'  => __( 'Delete permanently', 'mcupr' ),
					'emptyTrash' => __( 'Empty the trash', 'mcupr' ),
					'deletedOn'  => __( 'Date deleted', 'mcupr' ),
					'whereFrom'  => __( 'Where it was', 'mcupr' ),
					'cancel'     => __( 'Cancel', 'mcupr' ),
					'failed'     => __( 'That did not work. Try again.', 'mcupr' ),
					'uploading'  => __( 'Uploading…', 'mcupr' ),
					'tooBig'     => __( 'Too big (the limit is %s).', 'mcupr' ),
					'moved'      => __( 'Moved.', 'mcupr' ),
					'deleted'    => __( 'Deleted.', 'mcupr' ),
					'uploaded'   => __( '%d uploaded.', 'mcupr' ),
					'renameTag'  => __( 'Rename tag', 'mcupr' ),
					'folder'     => __( 'Folder', 'mcupr' ),
					'image'      => __( 'Image', 'mcupr' ),
					'video'      => __( 'Video', 'mcupr' ),
					'audio'      => __( 'Audio', 'mcupr' ),
					'pdf'        => __( 'PDF document', 'mcupr' ),
					'doc'        => __( 'Document', 'mcupr' ),
					'sheet'      => __( 'Spreadsheet', 'mcupr' ),
					'slides'     => __( 'Presentation', 'mcupr' ),
					'archive'    => __( 'Archive', 'mcupr' ),
					'other'      => __( 'File', 'mcupr' ),
					'close'      => __( 'Close', 'mcupr' ),
					'dropHere'   => __( 'Drop to upload', 'mcupr' ),
					'noPreview'  => __( 'No preview for this kind of file.', 'mcupr' ),
					'openTab'    => __( 'Open in a new tab', 'mcupr' ),
				),
			)
		);
	}
);

function mcupr_fm_page() {
	if ( ! current_user_can( MCUPR_FM_CAP ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mcupr' ) );
	}
	echo '<div class="wrap mcupr-fm-wrap"><h1 class="screen-reader-text">' . esc_html__( 'File manager', 'mcupr' ) . '</h1>';
	if ( '' === mcupr_fm_root() ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'The uploads folder is not available.', 'mcupr' ) . '</p></div></div>';
		return;
	}
	echo '<div id="mcupr-fm" class="fm" data-fm><noscript><p>' . esc_html__( 'The file manager needs JavaScript.', 'mcupr' ) . '</p></noscript></div></div>';
}
