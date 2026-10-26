<?php
/**
 * Google Drive files and folders, Google Docs / Sheets / Slides / Forms, and Google Photos albums inside a post.
 *
 *   • Drive & Docs: paste the share link. A file, a document, a sheet, a presentation, a form or a whole folder is shown as a
 *     card; pressing "show here" loads Google's own preview in the page (nothing is loaded from Google before – the same
 *     privacy rule as videos from other sites). The link to open it at Google is always there.
 *   • Google Photos: Google does not allow an album to be shown inside another website (only linked), so an album is a card with a
 *     button that opens it. The photos that should be seen on the site itself go in the "Activity photos" box (inc/post-media.php).
 *   • Three ways to use it: the "Google Drive and Google Photos" box under the text (one link a line, "Name | link" to give a title),
 *     a link of Drive/Docs alone on a line of the text, or the shortcodes [mcupr_drive url="…" title="…"] / [mcupr_gphotos url="…"].
 *
 * Only these addresses are accepted (https; drive.google.com, docs.google.com, photos.google.com, photos.app.goo.gl) and the
 * address that goes into the frame is built here from the file's id, never taken from what was typed.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/**
 * Understand a Google link. Returns array( kind, id, embed, open ) or false.
 * kind: file | folder | doc | sheet | slides | form | photos.
 */
function mcupr_google_parse( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || strlen( $url ) > 600 || ! preg_match( '#^https?://#i', $url ) ) {
		return false;
	}
	$p = wp_parse_url( $url );
	if ( ! $p || empty( $p['host'] ) ) {
		return false;
	}
	$host = strtolower( $p['host'] );
	$path = isset( $p['path'] ) ? $p['path'] : '';
	$q    = array();
	if ( ! empty( $p['query'] ) ) {
		parse_str( $p['query'], $q );
	}
	$id = '[A-Za-z0-9_-]{10,200}';
	if ( 'drive.google.com' === $host ) {
		if ( preg_match( '#/folders/(' . $id . ')#', $path, $m ) ) {
			return array( 'kind' => 'folder', 'id' => $m[1], 'embed' => 'https://drive.google.com/embeddedfolderview?id=' . $m[1] . '#list', 'open' => 'https://drive.google.com/drive/folders/' . $m[1] );
		}
		if ( preg_match( '#/file/(?:u/\d+/)?d/(' . $id . ')#', $path, $m ) || ( isset( $q['id'] ) && is_string( $q['id'] ) && preg_match( '#^' . $id . '$#', $q['id'] ) && preg_match( '#^/(open|uc)$#', $path ) && ( $m = array( 1 => $q['id'] ) ) ) ) {
			return array( 'kind' => 'file', 'id' => $m[1], 'embed' => 'https://drive.google.com/file/d/' . $m[1] . '/preview', 'open' => 'https://drive.google.com/file/d/' . $m[1] . '/view' );
		}
		return false;
	}
	if ( 'docs.google.com' === $host ) {
		if ( preg_match( '#^/document/(?:u/\d+/)?d/(' . $id . ')#', $path, $m ) ) {
			return array( 'kind' => 'doc', 'id' => $m[1], 'embed' => 'https://docs.google.com/document/d/' . $m[1] . '/preview', 'open' => 'https://docs.google.com/document/d/' . $m[1] . '/view' );
		}
		if ( preg_match( '#^/spreadsheets/(?:u/\d+/)?d/(' . $id . ')#', $path, $m ) ) {
			return array( 'kind' => 'sheet', 'id' => $m[1], 'embed' => 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/preview', 'open' => 'https://docs.google.com/spreadsheets/d/' . $m[1] . '/view' );
		}
		if ( preg_match( '#^/presentation/(?:u/\d+/)?d/(' . $id . ')#', $path, $m ) ) {
			return array( 'kind' => 'slides', 'id' => $m[1], 'embed' => 'https://docs.google.com/presentation/d/' . $m[1] . '/embed?start=false&loop=false', 'open' => 'https://docs.google.com/presentation/d/' . $m[1] . '/view' );
		}
		if ( preg_match( '#^/forms/d/e/(' . $id . ')/viewform#', $path, $m ) ) {
			return array( 'kind' => 'form', 'id' => $m[1], 'embed' => 'https://docs.google.com/forms/d/e/' . $m[1] . '/viewform?embedded=true', 'open' => 'https://docs.google.com/forms/d/e/' . $m[1] . '/viewform' );
		}
		return false;
	}
	if ( 'photos.app.goo.gl' === $host && preg_match( '#^/[A-Za-z0-9_-]{6,60}/?$#', $path ) ) {
		return array( 'kind' => 'photos', 'id' => trim( $path, '/' ), 'embed' => '', 'open' => 'https://photos.app.goo.gl/' . trim( $path, '/' ) );
	}
	if ( 'photos.google.com' === $host && preg_match( '#^/(?:u/\d+/)?(?:share|album)/([A-Za-z0-9_-]{10,200})#', $path, $m ) ) {
		$key = isset( $q['key'] ) && is_string( $q['key'] ) && preg_match( '#^[A-Za-z0-9_-]{4,100}$#', $q['key'] ) ? '?key=' . $q['key'] : '';
		return array( 'kind' => 'photos', 'id' => $m[1], 'embed' => '', 'open' => 'https://photos.google.com/share/' . $m[1] . $key );
	}
	return false;
}

/** The label of a kind of link, in the visitor's language. */
function mcupr_google_kind_label( $kind ) {
	$map = array( 'file' => 'gemb_kind_file', 'folder' => 'gemb_kind_folder', 'doc' => 'gemb_kind_doc', 'sheet' => 'gemb_kind_sheet', 'slides' => 'gemb_kind_slides', 'form' => 'gemb_kind_form', 'photos' => 'gphotos_title' );
	return isset( $map[ $kind ] ) ? mcupr_t( $map[ $kind ] ) : '';
}

/** One card for a parsed link. $title '' = the name of the kind. */
function mcupr_google_card( $g, $title = '' ) {
	$label = mcupr_google_kind_label( $g['kind'] );
	$name  = '' !== trim( $title ) ? trim( $title ) : $label;
	if ( 'photos' === $g['kind'] ) {
		return '<section class="gemb gemb-photos no-print" aria-label="' . esc_attr( $name ) . '"><div class="gemb-card"><span class="gemb-ic" aria-hidden="true">' . mcupr_icon( 'image', 26 ) . '</span><div class="gemb-main"><b class="gemb-name">' . esc_html( $name ) . '</b><span class="gemb-note">' . esc_html( mcupr_t( 'gphotos_note' ) ) . '</span></div><a class="gemb-open gemb-open-main" href="' . esc_url( $g['open'] ) . '" target="_blank" rel="noopener">' . mcupr_icon( 'external', 18 ) . '<span>' . esc_html( mcupr_t( 'gphotos_open' ) ) . '</span><span class="sr-only"> ' . esc_html( mcupr_t( 'new_tab' ) ) . '</span></a></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$open = in_array( $g['kind'], array( 'file', 'folder' ), true ) ? mcupr_t( 'gemb_open_drive' ) : mcupr_t( 'gemb_open_docs' );
	return '<section class="gemb gemb-' . esc_attr( $g['kind'] ) . ' no-print" data-gemb data-embed="' . esc_url( $g['embed'] ) . '" data-title="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name ) . '"><div class="gemb-card"><span class="gemb-ic" aria-hidden="true">' . mcupr_icon( 'folder' === $g['kind'] ? 'folder' : 'file', 26 ) . '</span><div class="gemb-main"><b class="gemb-name">' . esc_html( $name ) . '</b><span class="gemb-note">' . ( $name !== $label ? esc_html( $label ) . ' · ' : '' ) . esc_html( mcupr_t( 'gemb_note' ) ) . '</span></div><button type="button" class="gemb-load">' . esc_html( mcupr_t( 'gemb_load' ) ) . '</button><a class="gemb-open" href="' . esc_url( $g['open'] ) . '" target="_blank" rel="noopener">' . mcupr_icon( 'external', 18 ) . '<span>' . esc_html( $open ) . '</span><span class="sr-only"> ' . esc_html( mcupr_t( 'new_tab' ) ) . '</span></a></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** "Name | link" or "link" → array( title, parsed ) or null. */
function mcupr_google_line( $line ) {
	$line = trim( (string) $line );
	if ( '' === $line ) {
		return null;
	}
	$title = '';
	if ( false !== strpos( $line, '|' ) ) {
		list( $a, $b ) = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( preg_match( '#^https?://#i', $b ) ) {
			$title = sanitize_text_field( $a );
			$line  = $b;
		} elseif ( preg_match( '#^https?://#i', $a ) ) {
			$line = $a;
		}
	}
	$g = mcupr_google_parse( $line );
	return $g ? array( 'title' => mb_substr( $title, 0, 120 ), 'g' => $g, 'line' => ( '' !== $title ? $title . ' | ' : '' ) . $line ) : null;
}

/** The lines saved in a box ('_mcupr_gdrive' or '_mcupr_gphotos') as parsed items. */
function mcupr_google_items( $post_id, $key ) {
	$out = array();
	foreach ( preg_split( '/\R/u', (string) get_post_meta( $post_id, $key, true ) ) as $line ) {
		$it = mcupr_google_line( $line );
		if ( $it && ( '_mcupr_gphotos' === $key ? 'photos' === $it['g']['kind'] : 'photos' !== $it['g']['kind'] ) ) {
			$out[] = $it;
		}
	}
	return array_slice( $out, 0, 20 );
}

/** Drive and Photos cards of a post, drawn by mcupr_post_media(). */
function mcupr_google_media( $post_id ) {
	foreach ( mcupr_google_items( $post_id, '_mcupr_gdrive' ) as $it ) {
		echo mcupr_google_card( $it['g'], $it['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	foreach ( mcupr_google_items( $post_id, '_mcupr_gphotos' ) as $it ) {
		echo mcupr_google_card( $it['g'], $it['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/* ---------------------------------------------------------------- in the text */

/** A Drive / Docs / Photos link alone on a line of the text becomes the card (the same way a YouTube link does). */
add_action(
	'init',
	static function () {
		wp_embed_register_handler(
			'mcupr_google',
			'#https?://(?:drive\.google\.com|docs\.google\.com|photos\.google\.com|photos\.app\.goo\.gl)/[^\s<>"\']+#i',
			static function ( $m ) {
				// the shortcode, not the card: shortcodes run after wpautop, which would cut the card's markup into paragraphs
				$g = mcupr_google_parse( $m[0] );
				return $g ? '[' . ( 'photos' === $g['kind'] ? 'mcupr_gphotos' : 'mcupr_drive' ) . ' url="' . esc_attr( $g['open'] ) . '"]' : '';
			}
		);
	}
);

add_shortcode(
	'mcupr_drive',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'url' => '', 'title' => '' ), $atts, 'mcupr_drive' );
		$g = mcupr_google_parse( $a['url'] );
		return $g && 'photos' !== $g['kind'] ? mcupr_google_card( $g, sanitize_text_field( $a['title'] ) ) : '';
	}
);
add_shortcode(
	'mcupr_gphotos',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'url' => '', 'title' => '' ), $atts, 'mcupr_gphotos' );
		$g = mcupr_google_parse( $a['url'] );
		return $g && 'photos' === $g['kind'] ? mcupr_google_card( $g, sanitize_text_field( $a['title'] ) ) : '';
	}
);

/* ---------------------------------------------------------------- the box */

add_action(
	'add_meta_boxes',
	static function () {
		foreach ( mcupr_tool_types() as $type ) {
			add_meta_box( 'mcupr_google', __( 'Google Drive and Google Photos', 'mcupr' ), 'mcupr_google_box', $type, 'normal', 'default' );
		}
	}
);

function mcupr_google_box( $post ) {
	wp_nonce_field( 'mcupr_google_save', 'mcupr_google_nonce' );
	$row = static function ( $name, $key, $label, $hint, $ph ) use ( $post ) {
		$val = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><textarea class="widefat" rows="3" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $val ) . '</textarea></label></p>';
		echo '<p class="description">' . esc_html( $hint ) . '</p>';
		foreach ( preg_split( '/\R/u', $val ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$it = mcupr_google_line( $line );
			echo '<p class="description" style="margin:2px 0;color:' . ( $it ? '#15803d' : '#b32d2e' ) . '">' . ( $it ? '&#10003; ' . esc_html( mcupr_google_kind_label( $it['g']['kind'] ) ) : '&#10007; ' . esc_html__( 'Not a Google link this theme can show:', 'mcupr' ) ) . ' <code>' . esc_html( mb_substr( $line, 0, 80 ) ) . '</code></p>';
		}
	};
	echo '<p class="description">' . esc_html__( 'Paste share links, one on each line (write “Name | link” to give it a title). Visitors see a card and press “show here” to load Google’s preview inside the page; nothing is loaded from Google before.', 'mcupr' ) . '</p>';
	$row( 'mcupr_gdrive', '_mcupr_gdrive', __( 'Google Drive files and folders, Docs, Sheets, Slides, Forms', 'mcupr' ), __( 'In Google Drive: Share → set “Anyone with the link” → Copy link. The file must be shared, or visitors will be asked to sign in.', 'mcupr' ), 'https://drive.google.com/file/d/…/view' );
	$row( 'mcupr_gphotos', '_mcupr_gphotos', __( 'Google Photos albums', 'mcupr' ), __( 'Google does not let an album be shown inside another site, so visitors get a card with a button that opens the album. To show photos on this page itself, use the “Activity photos” box.', 'mcupr' ), 'https://photos.app.goo.gl/…' );
}

add_action(
	'save_post',
	static function ( $post_id ) {
		if ( ! mcupr_can_save_box( $post_id, 'mcupr_google_nonce', 'mcupr_google_save' ) ) {
			return;
		}
		foreach ( array( 'mcupr_gdrive' => '_mcupr_gdrive', 'mcupr_gphotos' => '_mcupr_gphotos' ) as $field => $key ) {
			$raw  = isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$keep = array();
			foreach ( preg_split( '/\R/u', $raw ) as $line ) {
				$it = mcupr_google_line( $line );
				if ( $it && ( '_mcupr_gphotos' === $key ? 'photos' === $it['g']['kind'] : 'photos' !== $it['g']['kind'] ) && count( $keep ) < 20 ) {
					$keep[] = $it['line'];
				}
			}
			if ( $keep ) {
				update_post_meta( $post_id, $key, implode( "\n", $keep ) );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
);
