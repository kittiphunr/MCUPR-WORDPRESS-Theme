<?php
/**
 * E-books (หนังสืออิเล็กทรอนิกส์): post type "mcupr_book" with a bookshelf (taxonomy "mcupr_shelf"), a 3D flip-book reader and
 * a shelf page that can be shown as wooden / dark / glass / white / metal shelves (or your own picture) or as a plain grid.
 *
 * A book is a PDF or a set of pictures (EPUB comes in the next round). The reader runs in the visitor's browser: PDF.js draws
 * the PDF pages and StPageFlip turns them (both ship inside the theme and load only when a book is opened). The page-turn
 * sound is made by the browser's Web Audio (no sound file, nothing to license) and can be switched off by the visitor.
 *
 * Content for students only: books use the same "Who can see this" box as news (inc/audience.php). The files of such a book are
 * streamed by the theme to people who may read it (…?mcupr_bf=BOOK-FILE). Files in the Media Library keep their own address,
 * which stays reachable by anyone who knows it – the same limit the "Attached files" box of news has.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_BOOK  = 'mcupr_book';
const MCUPR_SHELF = 'mcupr_shelf';

/* ================================================================== the types */

add_action(
	'init',
	static function () {
		register_post_type(
			MCUPR_BOOK,
			array(
				'labels'        => array(
					'name'          => __( 'E-books', 'mcupr' ),
					'singular_name' => __( 'E-book', 'mcupr' ),
					'menu_name'     => __( 'E-books', 'mcupr' ),
					'all_items'     => __( 'All e-books', 'mcupr' ),
					'add_new_item'  => __( 'Add an e-book', 'mcupr' ),
					'edit_item'     => __( 'Edit e-book', 'mcupr' ),
					'not_found'     => __( 'No e-books yet', 'mcupr' ),
				),
				'public'        => true,
				'has_archive'   => 'books',
				'rewrite'       => array( 'slug' => 'books', 'with_front' => false ),
				'menu_icon'     => 'dashicons-book-alt',
				'menu_position' => 9,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest'  => true,
			)
		);
		register_taxonomy(
			MCUPR_SHELF,
			MCUPR_BOOK,
			array(
				'labels'            => array( 'name' => __( 'Bookshelves', 'mcupr' ), 'singular_name' => __( 'Bookshelf', 'mcupr' ), 'add_new_item' => __( 'Add a bookshelf', 'mcupr' ), 'menu_name' => __( 'Bookshelves', 'mcupr' ) ),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'shelf', 'with_front' => false ),
			)
		);
		if ( get_option( 'mcupr_rewrite_books' ) !== MCUPR_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'mcupr_rewrite_books', MCUPR_VERSION );
		}
	},
	9
);

add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = MCUPR_BOOK;
		return $types;
	}
);

/* ================================================================== settings */

/** The shelf styles (key => label). 'custom' uses the picture chosen in the settings. */
function mcupr_book_styles() {
	return array(
		'wood'   => __( 'Wooden shelf', 'mcupr' ),
		'dark'   => __( 'Dark wood', 'mcupr' ),
		'glass'  => __( 'Glass shelf', 'mcupr' ),
		'white'  => __( 'White modern shelf', 'mcupr' ),
		'metal'  => __( 'Metal shelf', 'mcupr' ),
		'custom' => __( 'My own picture', 'mcupr' ),
	);
}

/** The settings with their defaults: style, custom (attachment id of the picture), view (shelf|grid), sound (1/0), download (1/0). */
function mcupr_books_opts() {
	$o = get_option( 'mcupr_books_opts', array() );
	$o = array_merge( array( 'style' => 'wood', 'custom' => 0, 'view' => 'shelf', 'sound' => 1, 'download' => 1, 'fx' => 'css3d', 'mode' => 'embed', 'bg' => '#1f2430' ), is_array( $o ) ? $o : array() );
	return array(
		'style'    => isset( mcupr_book_styles()[ $o['style'] ] ) ? $o['style'] : 'wood',
		'custom'   => (int) $o['custom'],
		'view'     => 'grid' === $o['view'] ? 'grid' : 'shelf',
		'sound'    => ! empty( $o['sound'] ) ? 1 : 0,
		'download' => ! empty( $o['download'] ) ? 1 : 0,
		'fx'       => isset( mcupr_book_fx_list()[ $o['fx'] ] ) ? $o['fx'] : 'css3d',
		'mode'     => 'lightbox' === $o['mode'] ? 'lightbox' : 'embed',
		'bg'       => preg_match( '/^#[0-9a-f]{6}$/i', (string) $o['bg'] ) ? $o['bg'] : '#1f2430',
	);
}

/** How the pages turn (key => label): CSS 3D is the lightest; WebGL bends the page with light and shadow; Classic is the page-flip library. */
function mcupr_book_fx_list() {
	return array(
		'css3d'   => __( 'CSS 3D (lightest)', 'mcupr' ),
		'webgl'   => __( 'WebGL (realistic: light and shadow)', 'mcupr' ),
		'classic' => __( 'Classic (page-flip library, heavier)', 'mcupr' ),
	);
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'E-book settings', 'mcupr' ), __( 'E-book settings', 'mcupr' ), 'manage_options', 'mcupr-books', 'mcupr_books_settings_page' );
	}
);

function mcupr_books_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = mcupr_books_opts();
	wp_enqueue_media();
	echo '<div class="wrap"><h1>' . esc_html__( 'E-book settings', 'mcupr' ) . '</h1>';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<p style="max-width:56rem">' . esc_html__( 'The bookshelf page is at /books/. Choose how the shelves look and what readers can do. A single bookshelf (category) can have its own style on its edit screen.', 'mcupr' ) . ' <a href="' . esc_url( get_post_type_archive_link( MCUPR_BOOK ) ) . '" target="_blank" rel="noopener">' . esc_html( get_post_type_archive_link( MCUPR_BOOK ) ) . '</a></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_books_settings">';
	wp_nonce_field( 'mcupr_books_settings', 'mcupr_books_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row">' . esc_html__( 'Shelf style', 'mcupr' ) . '</th><td><fieldset>';
	foreach ( mcupr_book_styles() as $k => $l ) {
		echo '<label style="display:inline-block;margin:0 1.25rem .5rem 0"><input type="radio" name="style" value="' . esc_attr( $k ) . '"' . checked( $o['style'], $k, false ) . '> <span class="mcupr-shelf-sw bks bks-' . esc_attr( $k ) . '" aria-hidden="true" style="display:inline-block;width:3rem;height:1.1rem;vertical-align:middle;border-radius:3px"></span> ' . esc_html( $l ) . '</label>';
	}
	echo '</fieldset><p class="description">' . esc_html__( '“My own picture” uses the picture below as the board of the shelf.', 'mcupr' ) . '</p></td></tr>';
	$img = $o['custom'] ? wp_get_attachment_image_url( $o['custom'], 'medium' ) : '';
	echo '<tr><th scope="row"><label for="mcupr-shelf-img">' . esc_html__( 'Picture of the shelf', 'mcupr' ) . '</label></th><td><input type="hidden" id="mcupr-shelf-img" name="custom" value="' . (int) $o['custom'] . '"><p><img id="mcupr-shelf-prev" src="' . esc_url( $img ) . '" alt="" style="max-width:20rem;max-height:6rem;' . ( $img ? '' : 'display:none' ) . '"></p><button type="button" class="button" id="mcupr-shelf-pick">' . esc_html__( 'Choose a picture', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-shelf-clear">' . esc_html__( 'Remove', 'mcupr' ) . '</button><p class="description">' . esc_html__( 'A wide picture of a plank (wood, metal, glass…), at least 1200 px wide. It repeats sideways.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'The bookshelf page opens as', 'mcupr' ) . '</th><td><label><input type="radio" name="view" value="shelf"' . checked( $o['view'], 'shelf', false ) . '> ' . esc_html__( 'Shelves', 'mcupr' ) . '</label> &nbsp; <label><input type="radio" name="view" value="grid"' . checked( $o['view'], 'grid', false ) . '> ' . esc_html__( 'Grid of covers', 'mcupr' ) . '</label><p class="description">' . esc_html__( 'Visitors can switch between the two; their choice is remembered in their browser.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'A book placed in a page opens', 'mcupr' ) . '</th><td><label><input type="radio" name="mode" value="embed"' . checked( $o['mode'], 'embed', false ) . '> ' . esc_html__( 'Inside the page (embedded)', 'mcupr' ) . '</label> &nbsp; <label><input type="radio" name="mode" value="lightbox"' . checked( $o['mode'], 'lightbox', false ) . '> ' . esc_html__( 'In a full-screen window when its cover is pressed (lightbox)', 'mcupr' ) . '</label><p class="description">' . esc_html__( 'For [mcupr_book]. Each book and each shortcode (mode="embed" or mode="lightbox") can choose its own. A lightbox loads the reader only when the cover is pressed, so the page stays light. The page of a single book is always embedded.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-bk-bg">' . esc_html__( 'Reader background', 'mcupr' ) . '</label></th><td><input type="color" id="mcupr-bk-bg" name="bg" value="' . esc_attr( $o['bg'] ) . '"> <span class="description">' . esc_html__( 'The colour behind the book (it fades darker towards the edges).', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'How the pages turn', 'mcupr' ) . '</th><td><fieldset>';
	foreach ( mcupr_book_fx_list() as $k => $l ) {
		echo '<label style="display:block;margin:0 0 .35rem"><input type="radio" name="fx" value="' . esc_attr( $k ) . '"' . checked( $o['fx'], $k, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '</fieldset><p class="description">' . esc_html__( 'The reader has a button to change this for the visitor (the choice is remembered). WebGL falls back to CSS 3D when the device cannot do it. People who asked their device for less motion get no turning animation.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Page-turn sound', 'mcupr' ) . '</th><td><label><input type="checkbox" name="sound" value="1"' . checked( $o['sound'], 1, false ) . '> ' . esc_html__( 'On at first (the reader has a button to switch it off, and the choice is remembered)', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Download button', 'mcupr' ) . '</th><td><label><input type="checkbox" name="download" value="1"' . checked( $o['download'], 1, false ) . '> ' . esc_html__( 'Show “Download” in the reader (each e-book can turn it off)', 'mcupr' ) . '</label></td></tr>';
	echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'mcupr' ) . '</button></p></form>';
	echo '<script>(function(){var f=null,id=document.getElementById("mcupr-shelf-img"),pv=document.getElementById("mcupr-shelf-prev");document.getElementById("mcupr-shelf-pick").addEventListener("click",function(){if(!window.wp||!wp.media){return;}if(!f){f=wp.media({title:' . wp_json_encode( __( 'Choose a picture', 'mcupr' ) ) . ',multiple:false,library:{type:"image"}});f.on("select",function(){var a=f.state().get("selection").first().toJSON();id.value=a.id;pv.src=(a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url);pv.style.display="";});}f.open();});document.getElementById("mcupr-shelf-clear").addEventListener("click",function(){id.value="0";pv.style.display="none";});})();</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<style>' . mcupr_book_shelf_css( true ) . '</style></div>';
}

add_action(
	'admin_post_mcupr_books_settings',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mcupr_books_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_books_nonce'] ) ), 'mcupr_books_settings' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$st  = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'wood';
		$img = isset( $_POST['custom'] ) ? absint( $_POST['custom'] ) : 0;
		update_option(
			'mcupr_books_opts',
			array(
				'style'    => isset( mcupr_book_styles()[ $st ] ) ? $st : 'wood',
				'custom'   => $img && wp_attachment_is_image( $img ) ? $img : 0,
				'view'     => isset( $_POST['view'] ) && 'grid' === $_POST['view'] ? 'grid' : 'shelf',
				'sound'    => ! empty( $_POST['sound'] ) ? 1 : 0,
				'download' => ! empty( $_POST['download'] ) ? 1 : 0,
				'mode'     => isset( $_POST['mode'] ) && 'lightbox' === $_POST['mode'] ? 'lightbox' : 'embed',
				'bg'       => isset( $_POST['bg'] ) && preg_match( '/^#[0-9a-f]{6}$/i', sanitize_text_field( wp_unslash( $_POST['bg'] ) ) ) ? sanitize_text_field( wp_unslash( $_POST['bg'] ) ) : '#1f2430',
				'fx'       => isset( $_POST['fx'], mcupr_book_fx_list()[ sanitize_key( wp_unslash( $_POST['fx'] ) ) ] ) ? sanitize_key( wp_unslash( $_POST['fx'] ) ) : 'css3d',
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-books&saved=1' ) );
		exit;
	}
);

/** A shelf (bookshelf category) can have its own style: a field on the add / edit screens. */
add_action(
	MCUPR_SHELF . '_add_form_fields',
	static function () {
		echo '<div class="form-field"><label for="mcupr-shelf-style">' . esc_html__( 'Shelf style', 'mcupr' ) . '</label><select name="mcupr_shelf_style" id="mcupr-shelf-style"><option value="">' . esc_html__( 'Same as the settings', 'mcupr' ) . '</option>';
		foreach ( mcupr_book_styles() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></div>';
	}
);
add_action(
	MCUPR_SHELF . '_edit_form_fields',
	static function ( $term ) {
		$cur = (string) get_term_meta( $term->term_id, 'mcupr_style', true );
		echo '<tr class="form-field"><th scope="row"><label for="mcupr-shelf-style">' . esc_html__( 'Shelf style', 'mcupr' ) . '</label></th><td><select name="mcupr_shelf_style" id="mcupr-shelf-style"><option value="">' . esc_html__( 'Same as the settings', 'mcupr' ) . '</option>';
		foreach ( mcupr_book_styles() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
	}
);
foreach ( array( 'created_' . MCUPR_SHELF, 'edited_' . MCUPR_SHELF ) as $mcupr_h ) {
	add_action(
		$mcupr_h,
		static function ( $term_id ) {
			if ( ! current_user_can( 'manage_categories' ) ) {
				return;
			}
			$v = isset( $_POST['mcupr_shelf_style'] ) ? sanitize_key( wp_unslash( $_POST['mcupr_shelf_style'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_POST['mcupr_shelf_style'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				isset( mcupr_book_styles()[ $v ] ) ? update_term_meta( $term_id, 'mcupr_style', $v ) : delete_term_meta( $term_id, 'mcupr_style' );
			}
		}
	);
}

/* ================================================================== a book: data */

/** Where a book's pages come from: 'pdf' or 'images' ('epub' later). */
function mcupr_book_src( $id ) {
	$s = (string) get_post_meta( $id, '_mcupr_bk_src', true );
	return in_array( $s, array( 'pdf', 'images', 'epub' ), true ) ? $s : 'pdf';
}

/** The attachment id of the EPUB (0 if none or not an EPUB). */
function mcupr_book_epub_id( $id ) {
	$a = (int) get_post_meta( $id, '_mcupr_bk_epub', true );
	return $a && 'application/epub+zip' === get_post_mime_type( $a ) ? $a : 0;
}

/** WordPress does not know .epub files: allow them for people who may upload, and recognise them (a ZIP to the server's eyes). */
add_filter(
	'upload_mimes',
	static function ( $m ) {
		$m['epub'] = 'application/epub+zip';
		return $m;
	}
);
add_filter(
	'wp_check_filetype_and_ext',
	static function ( $r, $file, $name ) {
		if ( empty( $r['type'] ) && preg_match( '/\.epub$/i', (string) $name ) && is_readable( $file ) && 'PK' === (string) file_get_contents( $file, false, null, 0, 2 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$r = array( 'ext' => 'epub', 'type' => 'application/epub+zip', 'proper_filename' => false );
		}
		return $r;
	},
	10,
	3
);

/** The id of a YouTube video from a link (watch, youtu.be, embed, shorts, live) or the bare id; '' when it is not one. */
function mcupr_book_yt_id( $u ) {
	$u = trim( (string) $u );
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $u ) ) {
		return $u;
	}
	$p = wp_parse_url( $u );
	if ( ! $p || empty( $p['host'] ) ) {
		return '';
	}
	$h  = strtolower( preg_replace( '/^www\./', '', $p['host'] ) );
	$id = '';
	if ( 'youtu.be' === $h && ! empty( $p['path'] ) ) {
		$id = trim( $p['path'], '/' );
	} elseif ( in_array( $h, array( 'youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com' ), true ) ) {
		if ( ! empty( $p['query'] ) ) {
			parse_str( $p['query'], $q );
			$id = isset( $q['v'] ) && is_string( $q['v'] ) ? $q['v'] : '';
		}
		if ( '' === $id && ! empty( $p['path'] ) && preg_match( '#^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $p['path'], $m ) ) {
			$id = $m[1];
		}
	}
	return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
}

/**
 * The extras of the pages of a book, checked: arrays of page (1-based, of the book's own pages), kind (youtube|video|audio|link),
 * mode (button = a button on the page, page = a page of its own after it), title, yt (YouTube id), att (file), url (link).
 */
function mcupr_book_media( $id ) {
	$rows = get_post_meta( $id, '_mcupr_bk_media', true );
	$out  = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || empty( $r['page'] ) || empty( $r['kind'] ) ) {
			continue;
		}
		$k = $r['kind'];
		$i = array( 'page' => max( 1, (int) $r['page'] ), 'kind' => $k, 'mode' => isset( $r['mode'] ) && 'page' === $r['mode'] ? 'page' : 'button', 'title' => isset( $r['title'] ) ? (string) $r['title'] : '', 'yt' => '', 'att' => 0, 'url' => '' );
		if ( 'youtube' === $k ) {
			$i['yt'] = mcupr_book_yt_id( isset( $r['yt'] ) ? $r['yt'] : '' );
			if ( '' === $i['yt'] ) {
				continue;
			}
		} elseif ( 'video' === $k || 'audio' === $k ) {
			$a = isset( $r['att'] ) ? (int) $r['att'] : 0;
			if ( ! $a || 0 !== strpos( (string) get_post_mime_type( $a ), $k . '/' ) ) {
				continue;
			}
			$i['att'] = $a;
		} elseif ( 'link' === $k ) {
			$u = esc_url_raw( isset( $r['url'] ) ? (string) $r['url'] : '', array( 'http', 'https' ) );
			if ( '' === $u ) {
				continue;
			}
			$i['url'] = $u;
		} else {
			continue;
		}
		$out[] = $i;
	}
	return array_slice( $out, 0, 200 );
}

/** The extras ready for the reader's script. */
function mcupr_book_media_cfg( $id ) {
	$o = array();
	foreach ( mcupr_book_media( $id ) as $i ) {
		$t = $i['title'];
		$c = array( 'page' => $i['page'], 'kind' => $i['kind'], 'mode' => $i['mode'], 'title' => $t );
		if ( 'youtube' === $i['kind'] ) {
			$c['yt'] = $i['yt'];
		} elseif ( $i['att'] ) {
			$c['src'] = mcupr_book_file_url( $id, $i['att'] );
			if ( '' === $t ) {
				$c['title'] = get_the_title( $i['att'] );
			}
		} else {
			$c['href'] = $i['url'];
		}
		$o[] = $c;
	}
	return $o;
}

/** The attachment ids of the pictures of an "images" book, in order (only real images). */
function mcupr_book_image_ids( $id ) {
	$ids = get_post_meta( $id, '_mcupr_bk_imgs', true );
	$ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	return array_values( array_filter( $ids, static function ( $i ) { return $i && wp_attachment_is_image( $i ); } ) );
}

/** The attachment id of the PDF (0 if none or not a PDF). */
function mcupr_book_pdf_id( $id ) {
	$a = (int) get_post_meta( $id, '_mcupr_bk_pdf', true );
	return $a && 'application/pdf' === get_post_mime_type( $a ) ? $a : 0;
}

/** Is this book for some students only? */
function mcupr_book_restricted( $id ) {
	return function_exists( 'mcupr_aud_is_restricted' ) && mcupr_aud_is_restricted( $id );
}

/** The address of a file of a book: its own address, or – for a book that is for students only – the theme's guarded stream. */
function mcupr_book_file_url( $book_id, $att_id, $size = '' ) {
	if ( mcupr_book_restricted( $book_id ) ) {
		return add_query_arg( array( 'mcupr_bf' => (int) $book_id . '-' . (int) $att_id . ( '' !== $size ? '-' . $size : '' ) ), home_url( '/' ) );
	}
	if ( '' !== $size && wp_attachment_is_image( $att_id ) ) {
		$u = wp_get_attachment_image_url( $att_id, $size );
		if ( $u ) {
			return $u;
		}
	}
	return (string) wp_get_attachment_url( $att_id );
}

/** The pages of a picture book: array of arrays( url, alt ). */
function mcupr_book_image_pages( $id ) {
	$out = array();
	foreach ( mcupr_book_image_ids( $id ) as $n => $a ) {
		$alt   = trim( (string) get_post_meta( $a, '_wp_attachment_image_alt', true ) );
		$out[] = array( 'url' => mcupr_book_file_url( $id, $a, 'large' ), 'alt' => '' !== $alt ? $alt : sprintf( /* translators: %d: page number */ __( 'Page %d', 'mcupr' ), $n + 1 ) );
	}
	return $out;
}

/** The cover: the featured image, else the first picture of a picture book; else ''. */
function mcupr_book_cover_url( $id, $size = 'medium_large' ) {
	$t = get_the_post_thumbnail_url( $id, $size ); // a cover is a poster shown on lists: it is not guarded
	if ( $t ) {
		return $t;
	}
	if ( 'images' === mcupr_book_src( $id ) ) {
		$ids = mcupr_book_image_ids( $id );
		if ( $ids ) {
			$u = wp_get_attachment_image_url( $ids[0], $size );
			return $u ? $u : '';
		}
	}
	return '';
}

/** A colour for a book without a cover (from its title, or chosen). */
function mcupr_book_color( $id ) {
	$c = (string) get_post_meta( $id, '_mcupr_bk_color', true );
	if ( preg_match( '/^#[0-9a-f]{6}$/i', $c ) ) {
		return $c;
	}
	$pal = array( '#9d174d', '#1d4ed8', '#0f766e', '#b45309', '#6d28d9', '#be123c', '#0e7490', '#4d7c0f', '#a21caf', '#334155' );
	return $pal[ abs( crc32( get_the_title( $id ) . $id ) ) % count( $pal ) ];
}

/* ================================================================== admin: the box of a book */

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'mcupr_book', __( 'E-book pages', 'mcupr' ), 'mcupr_book_box', MCUPR_BOOK, 'normal', 'high' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$s = get_current_screen();
		if ( $s && MCUPR_BOOK === $s->post_type ) {
			wp_enqueue_media();
			wp_enqueue_script( 'mcupr-book-admin', get_theme_file_uri( 'assets/js/book-admin.js' ), array( 'jquery-ui-sortable' ), MCUPR_VERSION, true );
			wp_localize_script(
				'mcupr-book-admin',
				'mcuprBookAdmin',
				array(
					'pdf'    => get_theme_file_uri( 'assets/vendor/pdfjs/pdf.min.mjs' ),
					'worker' => get_theme_file_uri( 'assets/vendor/pdfjs/pdf.worker.min.mjs' ),
					'cmaps'  => get_theme_file_uri( 'assets/vendor/pdfjs/cmaps/' ),
					'fonts'  => get_theme_file_uri( 'assets/vendor/pdfjs/standard_fonts/' ),
					'ajax'   => admin_url( 'admin-ajax.php' ),
					'nonce'  => wp_create_nonce( 'mcupr_book_cover' ),
					'jszip'  => get_theme_file_uri( 'assets/vendor/epub/jszip.min.js' ),
					'epub'   => get_theme_file_uri( 'assets/vendor/epub/epub.min.js' ),
					'i'      => array(
						'pick'     => __( 'Choose the PDF', 'mcupr' ),
						'pickImgs' => __( 'Choose the pictures', 'mcupr' ),
						'pickEpub' => __( 'Choose the EPUB', 'mcupr' ),
						'notEpub'  => __( 'This is not an EPUB file (.epub).', 'mcupr' ),
						'pickVideo' => __( 'Choose the video', 'mcupr' ),
						'pickAudio' => __( 'Choose the sound file', 'mcupr' ),
						'making'   => __( 'Making the cover…', 'mcupr' ),
						'done'     => __( 'The cover is set.', 'mcupr' ),
						'fail'     => __( 'The cover could not be made.', 'mcupr' ),
						'remove'   => __( 'Remove', 'mcupr' ),
					),
				)
			);
		}
	}
);

/** One row of the extras table (also the template for new rows: $i = '__i__'). */
function mcupr_book_media_row( $i, $m ) {
	$n    = 'mcupr_bk_media[' . $i . ']';
	$kind = $m['kind'];
	$what = 'youtube' === $kind ? 'https://youtu.be/' . $m['yt'] : ( 'link' === $kind ? $m['url'] : '' );
	$name = $m['att'] ? get_the_title( $m['att'] ) . ' – ' . basename( (string) get_attached_file( $m['att'] ) ) : '';
	$h    = '<tr class="mcupr-bk-mrow">';
	$h   .= '<td><label class="screen-reader-text">' . esc_html__( 'Page', 'mcupr' ) . '</label><input type="number" min="1" class="small-text" name="' . esc_attr( $n ) . '[page]" value="' . (int) $m['page'] . '"></td>';
	$h   .= '<td><label class="screen-reader-text">' . esc_html__( 'Type', 'mcupr' ) . '</label><select class="mcupr-bk-kind" name="' . esc_attr( $n ) . '[kind]">';
	foreach ( array( 'youtube' => __( 'YouTube link', 'mcupr' ), 'video' => __( 'Video file', 'mcupr' ), 'audio' => __( 'Sound file', 'mcupr' ), 'link' => __( 'Link', 'mcupr' ) ) as $k => $l ) {
		$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $kind, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	$h .= '</select></td>';
	$h .= '<td><input type="hidden" class="mcupr-bk-att" name="' . esc_attr( $n ) . '[att]" value="' . (int) $m['att'] . '">';
	$h .= '<input type="url" class="regular-text mcupr-bk-url" name="' . esc_attr( $n ) . '[url]" placeholder="https://" value="' . esc_attr( $what ) . '"' . ( 'video' === $kind || 'audio' === $kind ? ' hidden' : '' ) . '>';
	$h .= '<span class="mcupr-bk-file"' . ( 'video' === $kind || 'audio' === $kind ? '' : ' hidden' ) . '><button type="button" class="button mcupr-bk-pick-file">' . esc_html__( 'Choose the file', 'mcupr' ) . '</button> <span class="mcupr-bk-fname">' . esc_html( $name ) . '</span></span></td>';
	$h .= '<td><label class="screen-reader-text">' . esc_html__( 'Name shown', 'mcupr' ) . '</label><input type="text" class="regular-text" name="' . esc_attr( $n ) . '[title]" value="' . esc_attr( $m['title'] ) . '"></td>';
	$h .= '<td><label class="screen-reader-text">' . esc_html__( 'Shown as', 'mcupr' ) . '</label><select name="' . esc_attr( $n ) . '[mode]"><option value="button"' . selected( $m['mode'], 'button', false ) . '>' . esc_html__( 'A button on the page', 'mcupr' ) . '</option><option value="page"' . selected( $m['mode'], 'page', false ) . '>' . esc_html__( 'A page of its own', 'mcupr' ) . '</option></select></td>';
	$h .= '<td><button type="button" class="button-link mcupr-bk-mrm">' . esc_html__( 'Remove', 'mcupr' ) . '</button></td></tr>';
	return $h;
}

function mcupr_book_box( $post ) {
	wp_nonce_field( 'mcupr_book_save', 'mcupr_book_nonce' );
	$src  = mcupr_book_src( $post->ID );
	$pdf  = mcupr_book_pdf_id( $post->ID );
	$imgs = mcupr_book_image_ids( $post->ID );
	$epub = mcupr_book_epub_id( $post->ID );
	$dl   = get_post_meta( $post->ID, '_mcupr_bk_dl', true );
	$dl   = '' === $dl ? 1 : (int) $dl;
	echo '<p class="description" style="max-width:52rem">' . esc_html__( 'Choose where the pages come from. The reader turns the pages like a real book, with a page-turn sound the visitor can switch off.', 'mcupr' ) . '</p>';
	echo '<fieldset style="margin:.5rem 0 1rem"><legend class="screen-reader-text">' . esc_html__( 'Pages from', 'mcupr' ) . '</legend>';
	echo '<label style="margin-right:1.5rem"><input type="radio" name="mcupr_bk_src" value="pdf"' . checked( $src, 'pdf', false ) . '> <strong>' . esc_html__( 'A PDF file', 'mcupr' ) . '</strong></label>';
	echo '<label style="margin-right:1.5rem"><input type="radio" name="mcupr_bk_src" value="images"' . checked( $src, 'images', false ) . '> <strong>' . esc_html__( 'Pictures (one picture per page)', 'mcupr' ) . '</strong></label>';
	echo '<label><input type="radio" name="mcupr_bk_src" value="epub"' . checked( $src, 'epub', false ) . '> <strong>' . esc_html__( 'An EPUB file', 'mcupr' ) . '</strong></label></fieldset>';
	// PDF
	echo '<div class="mcupr-bk-pane" data-src="pdf"' . ( 'pdf' === $src ? '' : ' hidden' ) . '><input type="hidden" name="mcupr_bk_pdf" id="mcupr-bk-pdf" value="' . (int) $pdf . '"><p><strong id="mcupr-bk-pdf-name">' . ( $pdf ? esc_html( get_the_title( $pdf ) . ' – ' . basename( (string) get_attached_file( $pdf ) ) ) : esc_html__( 'No PDF chosen yet.', 'mcupr' ) ) . '</strong></p><p><button type="button" class="button" id="mcupr-bk-pick-pdf">' . esc_html__( 'Choose the PDF', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-bk-cover"' . ( $pdf ? '' : ' disabled' ) . '>' . esc_html__( 'Use the first page as the cover', 'mcupr' ) . '</button> <span id="mcupr-bk-msg" role="status" class="description"></span></p></div>';
	// pictures
	echo '<div class="mcupr-bk-pane" data-src="images"' . ( 'images' === $src ? '' : ' hidden' ) . '><ul id="mcupr-bk-imgs" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 .75rem;padding:0;list-style:none">';
	foreach ( $imgs as $a ) {
		echo '<li data-id="' . (int) $a . '" style="position:relative;width:96px;border:1px solid #c3c4c7;background:#fff;padding:4px;cursor:move"><input type="hidden" name="mcupr_bk_imgs[]" value="' . (int) $a . '">' . wp_get_attachment_image( $a, 'thumbnail', false, array( 'style' => 'display:block;width:100%;height:auto', 'alt' => '' ) ) . '<button type="button" class="button-link mcupr-bk-rm" style="display:block;margin-top:2px">' . esc_html__( 'Remove', 'mcupr' ) . '</button></li>';
	}
	echo '</ul><p><button type="button" class="button" id="mcupr-bk-pick-imgs">' . esc_html__( 'Add pictures', 'mcupr' ) . '</button> <span class="description">' . esc_html__( 'Drag to change the order. The first picture is the cover when no featured image is set.', 'mcupr' ) . '</span></p></div>';
	// EPUB
	echo '<div class="mcupr-bk-pane" data-src="epub"' . ( 'epub' === $src ? '' : ' hidden' ) . '><input type="hidden" name="mcupr_bk_epub" id="mcupr-bk-epub" value="' . (int) $epub . '"><p><strong id="mcupr-bk-epub-name">' . ( $epub ? esc_html( get_the_title( $epub ) . ' – ' . basename( (string) get_attached_file( $epub ) ) ) : esc_html__( 'No EPUB chosen yet.', 'mcupr' ) ) . '</strong></p><p><button type="button" class="button" id="mcupr-bk-pick-epub">' . esc_html__( 'Choose the EPUB', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-bk-cover-epub"' . ( $epub ? '' : ' disabled' ) . '>' . esc_html__( 'Use the cover of the EPUB', 'mcupr' ) . '</button> <span id="mcupr-bk-msg-epub" role="status" class="description"></span></p><p class="description">' . esc_html__( 'An EPUB is text that flows to fit the screen, so it is read page by page with a simple turning effect. Extras on a page (videos, links) are for PDF and picture books.', 'mcupr' ) . '</p></div>';
	// extras of the pages
	echo '<div class="mcupr-bk-pane" data-src="pdf images"' . ( 'epub' === $src ? ' hidden' : '' ) . ' style="margin-top:1.25rem"><h3 style="margin:0 0 .25rem">' . esc_html__( 'Extras on the pages', 'mcupr' ) . '</h3><p class="description" style="max-width:52rem">' . esc_html__( 'Add a video, a YouTube link, a sound or a link to a page. “A button on the page” puts a button on that page that opens the player; “A page of its own” adds a page after it that plays the item.', 'mcupr' ) . '</p>';
	echo '<table class="widefat striped" id="mcupr-bk-media" style="max-width:62rem"><thead><tr><th scope="col">' . esc_html__( 'Page', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Type', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'What', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Name shown', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Shown as', 'mcupr' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Remove', 'mcupr' ) . '</span></th></tr></thead><tbody>';
	foreach ( mcupr_book_media( $post->ID ) as $n => $m ) {
		echo mcupr_book_media_row( (string) $n, $m ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table><p><button type="button" class="button" id="mcupr-bk-add-media">' . esc_html__( 'Add an extra', 'mcupr' ) . '</button></p>';
	echo '<script type="text/html" id="mcupr-bk-media-tpl">' . mcupr_book_media_row( '__i__', array( 'page' => 1, 'kind' => 'youtube', 'mode' => 'button', 'title' => '', 'yt' => '', 'att' => 0, 'url' => '' ) ) . '</script></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	// details
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="mcupr-bk-author">' . esc_html__( 'Author', 'mcupr' ) . '</label></th><td><input type="text" class="regular-text" id="mcupr-bk-author" name="mcupr_bk_author" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_bk_author', true ) ) . '"> <label for="mcupr-bk-year" class="screen-reader-text">' . esc_html__( 'Year', 'mcupr' ) . '</label><input type="text" class="small-text" id="mcupr-bk-year" name="mcupr_bk_year" placeholder="' . esc_attr__( 'Year', 'mcupr' ) . '" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_bk_year', true ) ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-bk-color">' . esc_html__( 'Colour of the cover when there is no picture', 'mcupr' ) . '</label></th><td><input type="color" id="mcupr-bk-color" name="mcupr_bk_color" value="' . esc_attr( mcupr_book_color( $post->ID ) ) . '"></td></tr>';
	$bm = (string) get_post_meta( $post->ID, '_mcupr_bk_mode', true );
	echo '<tr><th scope="row"><label for="mcupr-bk-mode">' . esc_html__( 'When placed in a page', 'mcupr' ) . '</label></th><td><select name="mcupr_bk_mode" id="mcupr-bk-mode"><option value="">' . esc_html__( 'Same as the settings', 'mcupr' ) . '</option><option value="embed"' . selected( $bm, 'embed', false ) . '>' . esc_html__( 'Inside the page (embedded)', 'mcupr' ) . '</option><option value="lightbox"' . selected( $bm, 'lightbox', false ) . '>' . esc_html__( 'In a full-screen window (lightbox)', 'mcupr' ) . '</option></select></td></tr>';
	echo '<tr><th scope="row">' . esc_html__( 'Download', 'mcupr' ) . '</th><td><label><input type="checkbox" name="mcupr_bk_dl" value="1"' . checked( $dl, 1, false ) . '> ' . esc_html__( 'Readers can download the file (needs the setting “Download button” to be on)', 'mcupr' ) . '</label><p class="description">' . esc_html__( 'Anything shown on a web page can be saved by a determined reader; this only hides the button.', 'mcupr' ) . '</p></td></tr>';
	echo '</tbody></table>';
}

add_action(
	'save_post_' . MCUPR_BOOK,
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_book_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_book_nonce'] ) ), 'mcupr_book_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$src = isset( $_POST['mcupr_bk_src'] ) && in_array( $_POST['mcupr_bk_src'], array( 'images', 'epub' ), true ) ? sanitize_key( wp_unslash( $_POST['mcupr_bk_src'] ) ) : 'pdf';
		update_post_meta( $post_id, '_mcupr_bk_src', $src );
		$pdf = isset( $_POST['mcupr_bk_pdf'] ) ? absint( $_POST['mcupr_bk_pdf'] ) : 0;
		$pdf && 'application/pdf' === get_post_mime_type( $pdf ) ? update_post_meta( $post_id, '_mcupr_bk_pdf', $pdf ) : delete_post_meta( $post_id, '_mcupr_bk_pdf' );
		$epub = isset( $_POST['mcupr_bk_epub'] ) ? absint( $_POST['mcupr_bk_epub'] ) : 0;
		$epub && 'application/epub+zip' === get_post_mime_type( $epub ) ? update_post_meta( $post_id, '_mcupr_bk_epub', $epub ) : delete_post_meta( $post_id, '_mcupr_bk_epub' );
		$rows = array();
		if ( isset( $_POST['mcupr_bk_media'] ) && is_array( $_POST['mcupr_bk_media'] ) ) {
			foreach ( wp_unslash( $_POST['mcupr_bk_media'] ) as $r ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( ! is_array( $r ) ) {
					continue;
				}
				$k    = isset( $r['kind'] ) ? sanitize_key( $r['kind'] ) : '';
				$link = isset( $r['url'] ) ? trim( (string) $r['url'] ) : '';
				$rows[] = array(
					'page'  => isset( $r['page'] ) ? max( 1, absint( $r['page'] ) ) : 1,
					'kind'  => $k,
					'mode'  => isset( $r['mode'] ) && 'page' === $r['mode'] ? 'page' : 'button',
					'title' => isset( $r['title'] ) ? sanitize_text_field( $r['title'] ) : '',
					'yt'    => 'youtube' === $k ? mcupr_book_yt_id( $link ) : '',
					'att'   => isset( $r['att'] ) ? absint( $r['att'] ) : 0,
					'url'   => 'link' === $k ? esc_url_raw( $link, array( 'http', 'https' ) ) : '',
				);
			}
		}
		update_post_meta( $post_id, '_mcupr_bk_media', $rows );
		$clean = mcupr_book_media( $post_id ); // keep only the rows that hold something real
		$clean ? update_post_meta( $post_id, '_mcupr_bk_media', $clean ) : delete_post_meta( $post_id, '_mcupr_bk_media' );
		$imgs = isset( $_POST['mcupr_bk_imgs'] ) && is_array( $_POST['mcupr_bk_imgs'] ) ? array_values( array_unique( array_map( 'absint', wp_unslash( $_POST['mcupr_bk_imgs'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$imgs = array_slice( array_filter( $imgs, 'wp_attachment_is_image' ), 0, 1000 );
		$imgs ? update_post_meta( $post_id, '_mcupr_bk_imgs', $imgs ) : delete_post_meta( $post_id, '_mcupr_bk_imgs' );
		update_post_meta( $post_id, '_mcupr_bk_author', isset( $_POST['mcupr_bk_author'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_author'] ) ) : '' );
		update_post_meta( $post_id, '_mcupr_bk_year', isset( $_POST['mcupr_bk_year'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_year'] ) ) : '' );
		$col = isset( $_POST['mcupr_bk_color'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_color'] ) ) : '';
		preg_match( '/^#[0-9a-f]{6}$/i', $col ) ? update_post_meta( $post_id, '_mcupr_bk_color', $col ) : delete_post_meta( $post_id, '_mcupr_bk_color' );
		update_post_meta( $post_id, '_mcupr_bk_dl', ! empty( $_POST['mcupr_bk_dl'] ) ? 1 : 0 );
		$bm = isset( $_POST['mcupr_bk_mode'] ) ? sanitize_key( wp_unslash( $_POST['mcupr_bk_mode'] ) ) : '';
		in_array( $bm, array( 'embed', 'lightbox' ), true ) ? update_post_meta( $post_id, '_mcupr_bk_mode', $bm ) : delete_post_meta( $post_id, '_mcupr_bk_mode' );
	}
);

/** "Use the first page as the cover": the browser draws page 1 and sends the picture; the server makes it the featured image. */
add_action(
	'wp_ajax_mcupr_book_cover',
	static function () {
		$id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( ! $id || MCUPR_BOOK !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) || ! check_ajax_referer( 'mcupr_book_cover', 'nonce', false ) ) {
			wp_send_json_error( 'denied', 403 );
		}
		if ( empty( $_FILES['cover'] ) || ! empty( $_FILES['cover']['error'] ) || (int) $_FILES['cover']['size'] > 8 * MB_IN_BYTES ) {
			wp_send_json_error( 'no file', 400 );
		}
		$chk = wp_check_filetype_and_ext( $_FILES['cover']['tmp_name'], 'cover.jpg', array( 'jpg|jpeg' => 'image/jpeg' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $chk['type'] ) || 'image/jpeg' !== $chk['type'] ) {
			wp_send_json_error( 'not a picture', 400 );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$_FILES['cover']['name'] = 'cover-' . $id . '.jpg';
		$att                     = media_handle_upload( 'cover', $id );
		if ( is_wp_error( $att ) ) {
			wp_send_json_error( $att->get_error_message(), 500 );
		}
		set_post_thumbnail( $id, $att );
		wp_send_json_success( array( 'id' => $att, 'url' => wp_get_attachment_image_url( $att, 'medium' ) ) );
	}
);

/** The address of the PDF of a book, for the editor's cover maker (same-origin, Range-capable): only for people who can edit the book. */
add_action(
	'wp_ajax_mcupr_book_pdf_url',
	static function () {
		$id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( ! $id || MCUPR_BOOK !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) || ! check_ajax_referer( 'mcupr_book_cover', 'nonce', false ) ) {
			wp_send_json_error( 'denied', 403 );
		}
		$att = isset( $_POST['att'] ) ? absint( $_POST['att'] ) : mcupr_book_pdf_id( $id ); // the one just chosen, even before the book is saved
		if ( ! $att || ! in_array( get_post_mime_type( $att ), array( 'application/pdf', 'application/epub+zip' ), true ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'no file', 404 );
		}
		wp_send_json_success( array( 'url' => (string) wp_get_attachment_url( $att ) ) );
	}
);

/* ================================================================== guarded files (books for students only) */

/** /?mcupr_bf=BOOK-FILE[-size]: stream a file of a book to a person who may read the book (with Range, for PDF.js). */
add_action(
	'template_redirect',
	static function () {
		if ( ! isset( $_GET['mcupr_bf'] ) || ! preg_match( '/^(\d+)-(\d+)(?:-([a-z_]+))?$/', sanitize_text_field( wp_unslash( $_GET['mcupr_bf'] ) ), $m ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$book = (int) $m[1];
		$att  = (int) $m[2];
		$p    = get_post( $book );
		if ( ! $p || MCUPR_BOOK !== $p->post_type || ( 'publish' !== $p->post_status && ! current_user_can( 'edit_post', $book ) ) || post_password_required( $p ) ) {
			wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
		}
		if ( function_exists( 'mcupr_aud_can_view' ) && ! mcupr_aud_can_view( $book ) ) {
			wp_die( esc_html( mcupr_t( 'stu_denied_t' ) ), '', array( 'response' => 403 ) );
		}
		// only the files that belong to this book
		$mine = array_merge( array( mcupr_book_pdf_id( $book ), mcupr_book_epub_id( $book ) ), mcupr_book_image_ids( $book ), wp_list_pluck( mcupr_book_media( $book ), 'att' ) );
		if ( ! in_array( $att, $mine, true ) ) {
			wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
		}
		$path = get_attached_file( $att );
		if ( ! empty( $m[3] ) && wp_attachment_is_image( $att ) ) {
			$meta = wp_get_attachment_metadata( $att );
			if ( ! empty( $meta['sizes'][ $m[3] ]['file'] ) && $path ) {
				$try = path_join( dirname( $path ), $meta['sizes'][ $m[3] ]['file'] );
				$path = is_readable( $try ) ? $try : $path;
			}
		}
		$real = $path ? realpath( $path ) : false;
		$up   = wp_get_upload_dir();
		$base = realpath( $up['basedir'] );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) || ! is_readable( $real ) ) {
			wp_die( esc_html( mcupr_t( 'file_missing' ) ), '', array( 'response' => 404 ) );
		}
		mcupr_book_stream( $real, (string) get_post_mime_type( $att ) );
	},
	2
);

/** Send a file with HTTP Range support (a PDF reader asks for pieces). */
function mcupr_book_stream( $real, $mime ) {
	$size  = filesize( $real );
	$start = 0;
	$end   = $size - 1;
	$code  = 200;
	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $_SERVER['HTTP_RANGE'] ), $r ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $r[1] ) {
			$start = (int) $r[1];
			$end   = '' !== $r[2] ? min( (int) $r[2], $size - 1 ) : $size - 1;
		} elseif ( '' !== $r[2] ) {
			$start = max( 0, $size - (int) $r[2] );
		}
		if ( $start > $end || $start >= $size ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}
		$code = 206;
	}
	nocache_headers();
	status_header( $code );
	header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $real ) ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	if ( 206 === $code ) {
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	if ( 'HEAD' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$fh = fopen( $real, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fseek( $fh, $start );
		$left = $end - $start + 1;
		while ( $left > 0 && ! feof( $fh ) ) {
			$chunk = fread( $fh, min( 8192, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput
			$left -= strlen( $chunk );
			flush();
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	exit;
}

/* ================================================================== the reader (front end) */

/** The words the reader's script needs, in the language of the page. */
function mcupr_book_labels() {
	$k = array( 'bk_prev', 'bk_next', 'bk_page', 'bk_of', 'bk_sound_on', 'bk_sound_off', 'bk_thumbs', 'bk_fullscreen', 'bk_exit_fs', 'bk_download', 'bk_share', 'bk_loading', 'bk_error', 'bk_copied', 'bk_cover', 'bk_back_cover', 'bk_goto', 'bk_close', 'bk_text', 'bk_spread', 'bk_keys', 'bk_first', 'bk_last', 'bk_zoom_in', 'bk_zoom_out', 'bk_print', 'bk_autoplay', 'bk_autoplay_off', 'bk_pages', 'bk_contents', 'bk_open', 'bk_panel', 'bk_toolbar', 'bk_zoom_reset', 'bk_fx', 'bk_fx_css3d', 'bk_fx_webgl', 'bk_fx_classic', 'bk_m_play', 'bk_m_close', 'bk_m_open', 'bk_m_youtube', 'bk_m_video', 'bk_m_audio', 'bk_m_link', 'bk_m_here', 'bk_m_page', 'bk_ep_toc', 'bk_ep_bigger', 'bk_ep_smaller', 'bk_ep_progress' );
	$o = array();
	foreach ( $k as $key ) {
		$o[ str_replace( 'bk_', '', $key ) ] = mcupr_t( $key );
	}
	return $o;
}

/** The little pictures on the reader's buttons (24 × 24, drawn with a line). $on / $off: two pictures of one button, shown by its state. */
function mcupr_book_icon( $n ) {
	static $d = array(
		'first'    => 'M6 5v14M18 6l-8 6 8 6',
		'last'     => 'M18 5v14M6 6l8 6-8 6',
		'prev'     => 'M15 6l-6 6 6 6',
		'next'     => 'M9 6l6 6-6 6',
		'zoom-in'  => 'M11 5a6 6 0 100 12 6 6 0 000-12zM21 21l-5.2-5.2M11 8v6M8 11h6',
		'zoom-out' => 'M11 5a6 6 0 100 12 6 6 0 000-12zM21 21l-5.2-5.2M8 11h6',
		'play'     => 'M8 5v14l11-7z',
		'pause'    => 'M9 5v14M15 5v14',
		'sound'    => 'M4 9v6h4l5 4V5L8 9zM16.5 9a4 4 0 010 6M19 6.5a8 8 0 010 11',
		'mute'     => 'M4 9v6h4l5 4V5L8 9zM17 9.5l5 5M22 9.5l-5 5',
		'grid'     => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
		'list'     => 'M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01',
		'download' => 'M12 4v11M7 11l5 5 5-5M5 20h14',
		'print'    => 'M7 9V4h10v5M7 17H5v-6h14v6h-2M7 14h10v6H7z',
		'share'    => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
		'expand'   => 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5',
		'shrink'   => 'M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5',
		'fx'       => 'M4 7h10M18 7h2M4 17h2M10 17h10M14 5v4M6 15v4',
		'close'    => 'M6 6l12 12M18 6L6 18',
	);
	$fill = in_array( $n, array( 'play' ), true ) ? 'currentColor' : 'none';
	return '<svg class="bk-i" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="' . $fill . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' . esc_attr( isset( $d[ $n ] ) ? $d[ $n ] : '' ) . '"/></svg>';
}

/** A button of the toolbar: the picture(s), the name for screen readers and as a tooltip. $icon2: the picture of the "on" state. */
function mcupr_book_tb( $act, $icon, $label, $icon2 = '', $attrs = '' ) {
	$pic = '' === $icon2 ? mcupr_book_icon( $icon ) : '<span class="bk-i-off">' . mcupr_book_icon( $icon ) . '</span><span class="bk-i-on">' . mcupr_book_icon( $icon2 ) . '</span>';
	return '<button type="button" class="bk-btn bk-tb" data-act="' . esc_attr( $act ) . '" title="' . esc_attr( $label ) . '"' . $attrs . '>' . $pic . '<span class="bk-lbl" data-lbl>' . esc_html( $label ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * The reader of one book (markup + the scripts it needs). Returns '' when the book has nothing to show.
 * $args['mode']: 'embed' (in the page), 'lightbox' (the cover opens it in a full-screen window); '' = the book's own choice, else the setting.
 */
function mcupr_book_reader( $id, $args = array() ) {
	static $n = 0;
	$id  = (int) $id;
	$src = mcupr_book_src( $id );
	$o   = mcupr_books_opts();
	$cfg = array(
		'id'    => $id,
		'src'   => $src,
		'title' => get_the_title( $id ),
		'sound' => (int) $o['sound'],
		'fx'    => $o['fx'],
		'pageflip' => get_theme_file_uri( 'assets/vendor/pageflip/page-flip.browser.js' ),
		'rm'    => 0,
		'l'     => mcupr_book_labels(),
	);
	$dl = '';
	if ( 'pdf' === $src ) {
		$pdf = mcupr_book_pdf_id( $id );
		if ( ! $pdf ) {
			return '';
		}
		$cfg['pdf']    = mcupr_book_file_url( $id, $pdf );
		$cfg['worker'] = get_theme_file_uri( 'assets/vendor/pdfjs/pdf.worker.min.mjs' );
		$cfg['lib']    = get_theme_file_uri( 'assets/vendor/pdfjs/pdf.min.mjs' );
		$cfg['cmaps']  = get_theme_file_uri( 'assets/vendor/pdfjs/cmaps/' );
		$cfg['fonts']  = get_theme_file_uri( 'assets/vendor/pdfjs/standard_fonts/' );
		$dl            = $cfg['pdf'];
	} elseif ( 'epub' === $src ) {
		$ep = mcupr_book_epub_id( $id );
		if ( ! $ep ) {
			return '';
		}
		$cfg['epub'] = mcupr_book_file_url( $id, $ep );
		$cfg['jszip'] = get_theme_file_uri( 'assets/vendor/epub/jszip.min.js' );
		$cfg['lib']   = get_theme_file_uri( 'assets/vendor/epub/epub.min.js' );
		$dl           = $cfg['epub'];
	} else {
		$pages = mcupr_book_image_pages( $id );
		if ( ! $pages ) {
			return '';
		}
		$cfg['pages'] = $pages;
	}
	if ( 'epub' !== $src ) {
		$media = mcupr_book_media_cfg( $id );
		if ( $media ) {
			$cfg['media'] = $media;
		}
	}
	$dlm   = get_post_meta( $id, '_mcupr_bk_dl', true );
	$allow = $o['download'] && ( '' === $dlm || (int) $dlm ); // the reader may take the book away: the file, a print
	$can   = $allow && in_array( $src, array( 'pdf', 'epub' ), true ) && '' !== $dl;
	if ( $can ) {
		$cfg['dl'] = $dl;
	}
	$cfg['print'] = $allow && 'epub' !== $src ? 1 : 0;
	$mode = isset( $args['mode'] ) && in_array( $args['mode'], array( 'embed', 'lightbox' ), true ) ? $args['mode'] : '';
	if ( '' === $mode ) {
		$bm   = (string) get_post_meta( $id, '_mcupr_bk_mode', true );
		$mode = in_array( $bm, array( 'embed', 'lightbox' ), true ) ? $bm : $o['mode'];
	}
	$lb = 'lightbox' === $mode;
	wp_enqueue_script( 'mcupr-book-engines', get_theme_file_uri( 'assets/js/book-engines.js' ), array(), MCUPR_VERSION, true ); // the page-flip library itself is only fetched when "Classic" is chosen
	wp_enqueue_script( 'mcupr-book-reader', get_theme_file_uri( 'assets/js/book-reader.js' ), array( 'mcupr-book-engines' ), MCUPR_VERSION, true );
	if ( 'epub' === $src ) {
		wp_enqueue_script( 'mcupr-book-epub', get_theme_file_uri( 'assets/js/book-epub.js' ), array( 'mcupr-book-reader' ), MCUPR_VERSION, true );
	}
	++$n;
	$hash = $id . ( $lb ? '-lb' . $n : '' );
	$l    = $cfg['l'];
	$bg   = $o['bg'];
	ob_start();
	?>
<section class="bk-reader<?php echo 'epub' === $src ? ' bk-is-epub' : ''; ?><?php echo $lb ? ' is-lb' : ''; ?>" id="bk-<?php echo esc_attr( $hash ); ?>" style="--bk-bg: <?php echo esc_attr( $bg ); ?>" data-book="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $id ) ); ?>">
	<div class="bk-main">
		<aside class="bk-thumbs" id="bk-th-<?php echo esc_attr( $hash ); ?>" aria-label="<?php echo esc_attr( $l['panel'] ); ?>" hidden>
			<?php if ( 'epub' !== $src ) : ?>
				<div class="bk-tabs" role="tablist" hidden><button type="button" class="bk-tab" role="tab" data-tab="pages" aria-selected="true"><?php echo esc_html( $l['pages'] ); ?></button><button type="button" class="bk-tab" role="tab" data-tab="contents" aria-selected="false"><?php echo esc_html( $l['contents'] ); ?></button></div>
			<?php endif; ?>
			<ol class="bk-th-list"></ol>
			<ol class="bk-outline" hidden></ol>
		</aside>
		<div class="bk-stage-wrap">
			<button type="button" class="bk-nav bk-prev" data-act="prev" aria-label="<?php echo esc_attr( $l['prev'] ); ?>"><?php echo mcupr_book_icon( 'prev' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<div class="bk-stage" tabindex="0" role="group" aria-roledescription="<?php echo esc_attr( $l['spread'] ); ?>" aria-label="<?php echo esc_attr( $l['keys'] ); ?>">
				<?php if ( 'epub' === $src ) : ?>
					<div class="bk-ep"><div class="bk-ep-paper"><div class="bk-ep-view"></div></div></div>
				<?php else : ?>
					<div class="bk-zoom"><div class="bk-book"></div></div>
				<?php endif; ?>
				<p class="bk-status" role="status"><?php echo esc_html( $l['loading'] ); ?></p>
			</div>
			<button type="button" class="bk-nav bk-next" data-act="next" aria-label="<?php echo esc_attr( $l['next'] ); ?>"><?php echo mcupr_book_icon( 'next' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
		<?php if ( $lb ) : ?>
			<button type="button" class="bk-btn bk-close" data-act="close" title="<?php echo esc_attr( $l['close'] ); ?>"><?php echo mcupr_book_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="bk-lbl"><?php echo esc_html( $l['close'] ); ?></span></button>
		<?php endif; ?>
	</div>
	<div class="bk-toolbar" role="toolbar" aria-label="<?php echo esc_attr( $l['toolbar'] ); ?>">
		<?php if ( 'epub' !== $src ) : ?>
			<?php echo mcupr_book_tb( 'first', 'first', $l['first'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<div class="bk-pager">
			<?php echo mcupr_book_tb( 'prev', 'prev', $l['prev'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p class="bk-count" aria-live="polite"><span data-cur>1</span> / <span data-total>–</span></p>
			<?php echo mcupr_book_tb( 'next', 'next', $l['next'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php if ( 'epub' !== $src ) : ?>
			<?php echo mcupr_book_tb( 'last', 'last', $l['last'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<label class="bk-slider"><span class="sr-only"><?php echo esc_html( $l['goto'] ); ?></span><input type="range" min="1" max="1" value="1" step="1" data-act="range"></label>
		<?php if ( 'epub' !== $src ) : ?>
			<?php echo mcupr_book_tb( 'zoomout', 'zoom-out', $l['zoom_out'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo mcupr_book_tb( 'zoomin', 'zoom-in', $l['zoom_in'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo mcupr_book_tb( 'auto', 'play', $l['autoplay'], 'pause', ' aria-pressed="false"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<?php echo mcupr_book_tb( 'sound', 'mute', $l['sound_off'], 'sound', ' aria-pressed="false"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( 'epub' !== $src ) : ?>
			<?php echo mcupr_book_tb( 'fx', 'fx', $l['fx'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<?php echo mcupr_book_tb( 'thumbs', 'epub' === $src ? 'list' : 'grid', 'epub' === $src ? $l['ep_toc'] : $l['thumbs'], '', ' aria-expanded="false" aria-controls="bk-th-' . esc_attr( $hash ) . '"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $cfg['print'] ) : ?>
			<?php echo mcupr_book_tb( 'print', 'print', $l['print'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<?php if ( $can ) : ?>
			<a class="bk-btn bk-tb" href="<?php echo esc_url( $cfg['dl'] ); ?>" download title="<?php echo esc_attr( $l['download'] ); ?>"><?php echo mcupr_book_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="bk-lbl"><?php echo esc_html( $l['download'] ); ?></span></a>
		<?php endif; ?>
		<?php echo mcupr_book_tb( 'share', 'share', $l['share'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo mcupr_book_tb( 'fs', 'expand', $l['fullscreen'], 'shrink', ' aria-pressed="false"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<div class="bk-text sr-only" data-text aria-label="<?php echo esc_attr( $l['text'] ); ?>"></div>
	<div class="bk-mbar" data-mbar role="group" aria-label="<?php echo esc_attr( $l['m_here'] ); ?>" hidden></div>
	<noscript><p class="bk-noscript"><?php echo esc_html( mcupr_t( 'bk_noscript' ) ); ?><?php if ( $can ) : ?> <a href="<?php echo esc_url( $cfg['dl'] ); ?>"><?php echo esc_html( $l['download'] ); ?></a><?php endif; ?></p></noscript>
</section>
	<?php
	$reader = ob_get_clean();
	if ( ! $lb ) {
		return $reader;
	}
	// a lightbox: the cover on the page; the reader sits in a template and is built when the cover is pressed
	$cover = mcupr_book_cover_url( $id );
	ob_start();
	?>
<div class="bk-launch-wrap">
	<button type="button" class="bk-launch" data-bk-launch="bk-tpl-<?php echo esc_attr( $hash ); ?>" aria-haspopup="dialog" style="--bk-c: <?php echo esc_attr( mcupr_book_color( $id ) ); ?>">
		<span class="bk-launch-cover"><?php echo $cover ? '<img src="' . esc_url( $cover ) . '" alt="" loading="lazy">' : '<span class="bk-ph">' . esc_html( get_the_title( $id ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="bk-launch-t"><?php echo esc_html( get_the_title( $id ) ); ?></span>
		<span class="bk-launch-cta"><?php echo mcupr_book_icon( 'play' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $l['open'] ); ?></span>
	</button>
	<template id="bk-tpl-<?php echo esc_attr( $hash ); ?>"><?php echo $reader; // phpcs:ignore WordPress.Security.EscapeOutput ?></template>
</div>
	<?php
	return ob_get_clean();
}

/* ================================================================== the shelf (front end) */

/** The css of the shelf styles; $admin = only the little swatches of the settings page. */
function mcupr_book_shelf_css( $admin = false ) {
	$o  = mcupr_books_opts();
	$bg = $o['custom'] ? wp_get_attachment_image_url( $o['custom'], 'large' ) : '';
	$c  = '.bks-wood{--bks-board:linear-gradient(#b98255,#8a5a34 60%,#6f4526);--bks-back:linear-gradient(#f6efe3,#eadfca)}.bks-dark{--bks-board:linear-gradient(#5b3d29,#3d2819 60%,#2c1c11);--bks-back:linear-gradient(#3b3a3f,#2a292e)}.bks-glass{--bks-board:linear-gradient(#e9f6fb,#a9d3e4 55%,#7fb3c9);--bks-back:linear-gradient(#f4fbfe,#e1f1f8)}.bks-white{--bks-board:linear-gradient(#ffffff,#e5e7eb);--bks-back:linear-gradient(#fafafa,#f1f2f4)}.bks-metal{--bks-board:linear-gradient(#d7dce2,#9aa3ad 55%,#6b7480);--bks-back:linear-gradient(#e8ebef,#d3d8de)}';
	$c .= $bg ? '.bks-custom{--bks-board:url(' . esc_url_raw( $bg ) . ') center/auto 100% repeat-x;--bks-back:linear-gradient(#f6f6f6,#ececec)}' : '.bks-custom{--bks-board:linear-gradient(#b98255,#8a5a34);--bks-back:linear-gradient(#f6efe3,#eadfca)}';
	$c .= '.mcupr-shelf-sw{background:var(--bks-board)}';
	return $c;
}

/** The shelf style of a shelf (bookshelf category): its own, else the setting. */
function mcupr_book_shelf_style( $term_id = 0 ) {
	if ( $term_id ) {
		$s = (string) get_term_meta( $term_id, 'mcupr_style', true );
		if ( isset( mcupr_book_styles()[ $s ] ) ) {
			return $s;
		}
	}
	return mcupr_books_opts()['style'];
}

/** One book on a shelf / in the grid. */
function mcupr_book_card( $id ) {
	$id    = (int) $id;
	$cover = mcupr_book_cover_url( $id );
	$col   = mcupr_book_color( $id );
	$title = get_the_title( $id );
	$au    = (string) get_post_meta( $id, '_mcupr_bk_author', true );
	$h     = 96 + abs( crc32( 'h' . $id ) ) % 44; // books on a shelf are not all the same height
	$o     = '<li class="bk-card" data-id="' . $id . '" data-title="' . esc_attr( mb_strtolower( $title . ' ' . $au ) ) . '" style="--bk-h:' . (int) $h . 'px;--bk-c:' . esc_attr( $col ) . '">';
	$o    .= '<a class="bk-link" href="' . esc_url( get_permalink( $id ) ) . '"><span class="bk-slot"><span class="bk-cover">';
	if ( $cover ) {
		$o .= '<img src="' . esc_url( $cover ) . '" alt="" loading="lazy" decoding="async">';
	} else {
		$o .= '<span class="bk-ph" aria-hidden="true">' . esc_html( $title ) . '</span>';
	}
	$o .= '<span class="bk-lock" aria-hidden="true"' . ( mcupr_book_restricted( $id ) ? '' : ' hidden' ) . '>' . mcupr_icon( 'lock', 16 ) . '</span></span></span>';
	$o .= '<span class="bk-t">' . esc_html( $title ) . '</span>' . ( '' !== $au ? '<span class="bk-a">' . esc_html( $au ) . '</span>' : '' ) . '<span class="bk-resume" hidden></span></a></li>';
	return $o;
}

/**
 * The bookshelf: books grouped by shelf (category), as shelves or as a grid, with a search box and a switch.
 * $args: shelf (slug, '' = all), view (shelf|grid, '' = the setting), style ('' = the setting / the shelf's own), limit.
 */
function mcupr_book_shelf( $args = array() ) {
	$a    = array_merge( array( 'shelf' => '', 'view' => '', 'style' => '', 'limit' => 300 ), $args );
	$o    = mcupr_books_opts();
	$view = 'grid' === $a['view'] ? 'grid' : ( 'shelf' === $a['view'] ? 'shelf' : $o['view'] );
	$q    = array( 'post_type' => MCUPR_BOOK, 'post_status' => 'publish', 'posts_per_page' => (int) $a['limit'], 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true );
	if ( '' !== $a['shelf'] ) {
		$q['tax_query'] = array( array( 'taxonomy' => MCUPR_SHELF, 'field' => 'slug', 'terms' => sanitize_title( $a['shelf'] ) ) );
	}
	$books = get_posts( $q );
	$by    = array();
	$terms = array();
	foreach ( $books as $b ) {
		$ts = wp_get_object_terms( $b->ID, MCUPR_SHELF );
		if ( ! $ts || is_wp_error( $ts ) ) {
			$by[0][] = $b->ID;
			continue;
		}
		foreach ( $ts as $t ) {
			$terms[ $t->term_id ] = $t;
			$by[ $t->term_id ][]  = $b->ID;
		}
	}
	uasort( $terms, static function ( $x, $y ) { return strcoll( $x->name, $y->name ); } );
	$order = array_keys( $terms );
	if ( isset( $by[0] ) ) {
		$order[] = 0;
	}
	wp_enqueue_script( 'mcupr-book-shelf', get_theme_file_uri( 'assets/js/book-shelf.js' ), array(), MCUPR_VERSION, true );
	ob_start();
	?>
<div class="bk-shelf-app" data-view="<?php echo esc_attr( $view ); ?>" data-default="<?php echo esc_attr( $o['view'] ); ?>" data-resume="<?php echo esc_attr( mcupr_t( 'bk_resume' ) ); ?>">
	<?php if ( $books ) : ?>
		<div class="bk-tools">
			<div class="bk-search"><label class="sr-only" for="bk-q"><?php echo esc_html( mcupr_t( 'bk_search' ) ); ?></label><input type="search" id="bk-q" placeholder="<?php echo esc_attr( mcupr_t( 'bk_search' ) ); ?>" autocomplete="off"></div>
			<div class="bk-views" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'bk_view' ) ); ?>">
				<button type="button" class="bk-vbtn" data-view="shelf" aria-pressed="<?php echo 'shelf' === $view ? 'true' : 'false'; ?>"><?php echo esc_html( mcupr_t( 'bk_view_shelf' ) ); ?></button>
				<button type="button" class="bk-vbtn" data-view="grid" aria-pressed="<?php echo 'grid' === $view ? 'true' : 'false'; ?>"><?php echo esc_html( mcupr_t( 'bk_view_grid' ) ); ?></button>
			</div>
		</div>
		<?php foreach ( $order as $tid ) : ?>
			<?php
			$st    = '' !== $a['style'] && isset( mcupr_book_styles()[ $a['style'] ] ) ? $a['style'] : mcupr_book_shelf_style( (int) $tid );
			$title = $tid ? $terms[ $tid ]->name : mcupr_t( 'bk_unshelved' );
			?>
			<section class="bk-row bks bks-<?php echo esc_attr( $st ); ?>" data-shelf="<?php echo (int) $tid; ?>" aria-labelledby="bk-h-<?php echo (int) $tid; ?>">
				<h2 class="bk-row-h" id="bk-h-<?php echo (int) $tid; ?>"><?php echo $tid ? '<a href="' . esc_url( get_term_link( $terms[ $tid ] ) ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="bk-n"><?php echo (int) count( $by[ $tid ] ); ?></span></h2>
				<ul class="bk-books">
					<?php foreach ( $by[ $tid ] as $bid ) { echo mcupr_book_card( $bid ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</ul>
			</section>
		<?php endforeach; ?>
		<p class="bk-none" hidden><?php echo esc_html( mcupr_t( 'bk_nomatch' ) ); ?></p>
	<?php else : ?>
		<p class="bk-empty"><?php echo esc_html( mcupr_t( 'bk_empty' ) ); ?></p>
	<?php endif; ?>
</div>
	<?php
	return ob_get_clean();
}

/** [mcupr_book id=""] and [mcupr_shelf shelf="" view="shelf|grid" style=""]. */
add_shortcode(
	'mcupr_book',
	static function ( $atts ) {
		$a  = shortcode_atts( array( 'id' => 0, 'mode' => '' ), $atts, 'mcupr_book' );
		$id = (int) $a['id'];
		if ( ! $id || MCUPR_BOOK !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ( function_exists( 'mcupr_aud_can_view' ) && ! mcupr_aud_can_view( $id ) ) ) {
			return '';
		}
		return mcupr_book_reader( $id, array( 'mode' => sanitize_key( $a['mode'] ) ) );
	}
);
add_shortcode(
	'mcupr_shelf',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'shelf' => '', 'view' => '', 'style' => '', 'limit' => 300 ), $atts, 'mcupr_shelf' );
		return mcupr_book_shelf( $a );
	}
);

/** The shelf and reader styles go into the page head only where they are used. */
add_action(
	'wp_head',
	static function () {
		$p    = is_singular() ? get_post() : null;
		$used = is_singular( MCUPR_BOOK ) || is_post_type_archive( MCUPR_BOOK ) || is_tax( MCUPR_SHELF ) || ( $p && ( has_shortcode( $p->post_content, 'mcupr_shelf' ) || has_shortcode( $p->post_content, 'mcupr_book' ) ) );
		if ( $used ) {
			echo '<style id="mcupr-shelf-css">' . mcupr_book_shelf_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	30
);
