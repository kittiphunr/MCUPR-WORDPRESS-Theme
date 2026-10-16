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
	$o = array_merge( array( 'style' => 'wood', 'custom' => 0, 'view' => 'shelf', 'sound' => 1, 'download' => 1 ), is_array( $o ) ? $o : array() );
	return array(
		'style'    => isset( mcupr_book_styles()[ $o['style'] ] ) ? $o['style'] : 'wood',
		'custom'   => (int) $o['custom'],
		'view'     => 'grid' === $o['view'] ? 'grid' : 'shelf',
		'sound'    => ! empty( $o['sound'] ) ? 1 : 0,
		'download' => ! empty( $o['download'] ) ? 1 : 0,
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
	return in_array( $s, array( 'pdf', 'images' ), true ) ? $s : 'pdf';
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
					'i'      => array(
						'pick'     => __( 'Choose the PDF', 'mcupr' ),
						'pickImgs' => __( 'Choose the pictures', 'mcupr' ),
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

function mcupr_book_box( $post ) {
	wp_nonce_field( 'mcupr_book_save', 'mcupr_book_nonce' );
	$src  = mcupr_book_src( $post->ID );
	$pdf  = mcupr_book_pdf_id( $post->ID );
	$imgs = mcupr_book_image_ids( $post->ID );
	$dl   = get_post_meta( $post->ID, '_mcupr_bk_dl', true );
	$dl   = '' === $dl ? 1 : (int) $dl;
	echo '<p class="description" style="max-width:52rem">' . esc_html__( 'Choose where the pages come from. The reader turns the pages like a real book, with a page-turn sound the visitor can switch off.', 'mcupr' ) . '</p>';
	echo '<fieldset style="margin:.5rem 0 1rem"><legend class="screen-reader-text">' . esc_html__( 'Pages from', 'mcupr' ) . '</legend>';
	echo '<label style="margin-right:1.5rem"><input type="radio" name="mcupr_bk_src" value="pdf"' . checked( $src, 'pdf', false ) . '> <strong>' . esc_html__( 'A PDF file', 'mcupr' ) . '</strong></label>';
	echo '<label><input type="radio" name="mcupr_bk_src" value="images"' . checked( $src, 'images', false ) . '> <strong>' . esc_html__( 'Pictures (one picture per page)', 'mcupr' ) . '</strong></label></fieldset>';
	// PDF
	echo '<div class="mcupr-bk-pane" data-src="pdf"' . ( 'pdf' === $src ? '' : ' hidden' ) . '><input type="hidden" name="mcupr_bk_pdf" id="mcupr-bk-pdf" value="' . (int) $pdf . '"><p><strong id="mcupr-bk-pdf-name">' . ( $pdf ? esc_html( get_the_title( $pdf ) . ' – ' . basename( (string) get_attached_file( $pdf ) ) ) : esc_html__( 'No PDF chosen yet.', 'mcupr' ) ) . '</strong></p><p><button type="button" class="button" id="mcupr-bk-pick-pdf">' . esc_html__( 'Choose the PDF', 'mcupr' ) . '</button> <button type="button" class="button" id="mcupr-bk-cover"' . ( $pdf ? '' : ' disabled' ) . '>' . esc_html__( 'Use the first page as the cover', 'mcupr' ) . '</button> <span id="mcupr-bk-msg" role="status" class="description"></span></p></div>';
	// pictures
	echo '<div class="mcupr-bk-pane" data-src="images"' . ( 'images' === $src ? '' : ' hidden' ) . '><ul id="mcupr-bk-imgs" style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 .75rem;padding:0;list-style:none">';
	foreach ( $imgs as $a ) {
		echo '<li data-id="' . (int) $a . '" style="position:relative;width:96px;border:1px solid #c3c4c7;background:#fff;padding:4px;cursor:move"><input type="hidden" name="mcupr_bk_imgs[]" value="' . (int) $a . '">' . wp_get_attachment_image( $a, 'thumbnail', false, array( 'style' => 'display:block;width:100%;height:auto', 'alt' => '' ) ) . '<button type="button" class="button-link mcupr-bk-rm" style="display:block;margin-top:2px">' . esc_html__( 'Remove', 'mcupr' ) . '</button></li>';
	}
	echo '</ul><p><button type="button" class="button" id="mcupr-bk-pick-imgs">' . esc_html__( 'Add pictures', 'mcupr' ) . '</button> <span class="description">' . esc_html__( 'Drag to change the order. The first picture is the cover when no featured image is set.', 'mcupr' ) . '</span></p></div>';
	// details
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="mcupr-bk-author">' . esc_html__( 'Author', 'mcupr' ) . '</label></th><td><input type="text" class="regular-text" id="mcupr-bk-author" name="mcupr_bk_author" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_bk_author', true ) ) . '"> <label for="mcupr-bk-year" class="screen-reader-text">' . esc_html__( 'Year', 'mcupr' ) . '</label><input type="text" class="small-text" id="mcupr-bk-year" name="mcupr_bk_year" placeholder="' . esc_attr__( 'Year', 'mcupr' ) . '" value="' . esc_attr( (string) get_post_meta( $post->ID, '_mcupr_bk_year', true ) ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="mcupr-bk-color">' . esc_html__( 'Colour of the cover when there is no picture', 'mcupr' ) . '</label></th><td><input type="color" id="mcupr-bk-color" name="mcupr_bk_color" value="' . esc_attr( mcupr_book_color( $post->ID ) ) . '"></td></tr>';
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
		$src = isset( $_POST['mcupr_bk_src'] ) && 'images' === $_POST['mcupr_bk_src'] ? 'images' : 'pdf';
		update_post_meta( $post_id, '_mcupr_bk_src', $src );
		$pdf = isset( $_POST['mcupr_bk_pdf'] ) ? absint( $_POST['mcupr_bk_pdf'] ) : 0;
		$pdf && 'application/pdf' === get_post_mime_type( $pdf ) ? update_post_meta( $post_id, '_mcupr_bk_pdf', $pdf ) : delete_post_meta( $post_id, '_mcupr_bk_pdf' );
		$imgs = isset( $_POST['mcupr_bk_imgs'] ) && is_array( $_POST['mcupr_bk_imgs'] ) ? array_values( array_unique( array_map( 'absint', wp_unslash( $_POST['mcupr_bk_imgs'] ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$imgs = array_slice( array_filter( $imgs, 'wp_attachment_is_image' ), 0, 1000 );
		$imgs ? update_post_meta( $post_id, '_mcupr_bk_imgs', $imgs ) : delete_post_meta( $post_id, '_mcupr_bk_imgs' );
		update_post_meta( $post_id, '_mcupr_bk_author', isset( $_POST['mcupr_bk_author'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_author'] ) ) : '' );
		update_post_meta( $post_id, '_mcupr_bk_year', isset( $_POST['mcupr_bk_year'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_year'] ) ) : '' );
		$col = isset( $_POST['mcupr_bk_color'] ) ? sanitize_text_field( wp_unslash( $_POST['mcupr_bk_color'] ) ) : '';
		preg_match( '/^#[0-9a-f]{6}$/i', $col ) ? update_post_meta( $post_id, '_mcupr_bk_color', $col ) : delete_post_meta( $post_id, '_mcupr_bk_color' );
		update_post_meta( $post_id, '_mcupr_bk_dl', ! empty( $_POST['mcupr_bk_dl'] ) ? 1 : 0 );
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
		if ( ! $att || 'application/pdf' !== get_post_mime_type( $att ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'no pdf', 404 );
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
		$mine = array_merge( array( mcupr_book_pdf_id( $book ) ), mcupr_book_image_ids( $book ) );
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
	$k = array( 'bk_prev', 'bk_next', 'bk_page', 'bk_of', 'bk_sound_on', 'bk_sound_off', 'bk_thumbs', 'bk_fullscreen', 'bk_exit_fs', 'bk_download', 'bk_share', 'bk_loading', 'bk_error', 'bk_copied', 'bk_cover', 'bk_back_cover', 'bk_goto', 'bk_close', 'bk_text', 'bk_spread', 'bk_keys' );
	$o = array();
	foreach ( $k as $key ) {
		$o[ str_replace( 'bk_', '', $key ) ] = mcupr_t( $key );
	}
	return $o;
}

/**
 * The reader of one book (markup + the scripts it needs). Returns '' when the book has nothing to show.
 */
function mcupr_book_reader( $id ) {
	$id  = (int) $id;
	$src = mcupr_book_src( $id );
	$o   = mcupr_books_opts();
	$cfg = array(
		'id'    => $id,
		'src'   => $src,
		'title' => get_the_title( $id ),
		'sound' => (int) $o['sound'],
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
	} else {
		$pages = mcupr_book_image_pages( $id );
		if ( ! $pages ) {
			return '';
		}
		$cfg['pages'] = $pages;
	}
	$dlm = get_post_meta( $id, '_mcupr_bk_dl', true );
	$can = $o['download'] && ( '' === $dlm || (int) $dlm ) && 'pdf' === $src && '' !== $dl;
	if ( $can ) {
		$cfg['dl'] = $dl;
	}
	$hash = (int) $id;
	wp_enqueue_script( 'mcupr-pageflip', get_theme_file_uri( 'assets/vendor/pageflip/page-flip.browser.js' ), array(), MCUPR_VERSION, true );
	wp_enqueue_script( 'mcupr-book-reader', get_theme_file_uri( 'assets/js/book-reader.js' ), array( 'mcupr-pageflip' ), MCUPR_VERSION, true );
	$l = $cfg['l'];
	ob_start();
	?>
<section class="bk-reader" id="bk-<?php echo (int) $hash; ?>" data-book="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>" aria-label="<?php echo esc_attr( get_the_title( $id ) ); ?>">
	<div class="bk-bar">
		<button type="button" class="bk-btn" data-act="thumbs" aria-expanded="false" aria-controls="bk-th-<?php echo (int) $hash; ?>"><?php echo mcupr_icon( 'grid', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $l['thumbs'] ); ?></span></button>
		<button type="button" class="bk-btn" data-act="sound" aria-pressed="false"><?php echo mcupr_icon( 'bell', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span data-lbl><?php echo esc_html( $l['sound_off'] ); ?></span></button>
		<?php if ( $can ) : ?>
			<a class="bk-btn" href="<?php echo esc_url( $cfg['dl'] ); ?>" download><?php echo mcupr_icon( 'download', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $l['download'] ); ?></span></a>
		<?php endif; ?>
		<button type="button" class="bk-btn" data-act="share"><?php echo mcupr_icon( 'link', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span data-lbl><?php echo esc_html( $l['share'] ); ?></span></button>
		<button type="button" class="bk-btn" data-act="fs" aria-pressed="false"><?php echo mcupr_icon( 'panel', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span data-lbl><?php echo esc_html( $l['fullscreen'] ); ?></span></button>
	</div>
	<div class="bk-main">
		<div class="bk-thumbs" id="bk-th-<?php echo (int) $hash; ?>" hidden><ol class="bk-th-list"></ol></div>
		<div class="bk-stage-wrap">
			<button type="button" class="bk-nav bk-prev" data-act="prev" aria-label="<?php echo esc_attr( $l['prev'] ); ?>"><?php echo mcupr_icon( 'chevron', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<div class="bk-stage" tabindex="0" role="group" aria-roledescription="<?php echo esc_attr( $l['spread'] ); ?>" aria-label="<?php echo esc_attr( $l['keys'] ); ?>">
				<div class="bk-book"></div>
				<p class="bk-status" role="status"><?php echo esc_html( $l['loading'] ); ?></p>
			</div>
			<button type="button" class="bk-nav bk-next" data-act="next" aria-label="<?php echo esc_attr( $l['next'] ); ?>"><?php echo mcupr_icon( 'chevron', 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
	</div>
	<div class="bk-foot">
		<label class="bk-slider"><span class="sr-only"><?php echo esc_html( $l['goto'] ); ?></span><input type="range" min="1" max="1" value="1" step="1" data-act="range"></label>
		<p class="bk-count" aria-live="polite"><span data-cur>1</span> / <span data-total>–</span></p>
	</div>
	<div class="bk-text sr-only" data-text aria-label="<?php echo esc_attr( $l['text'] ); ?>"></div>
	<noscript><p class="bk-noscript"><?php echo esc_html( mcupr_t( 'bk_noscript' ) ); ?><?php if ( $can ) : ?> <a href="<?php echo esc_url( $cfg['dl'] ); ?>"><?php echo esc_html( $l['download'] ); ?></a><?php endif; ?></p></noscript>
</section>
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
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'mcupr_book' );
		$id = (int) $a['id'];
		if ( ! $id || MCUPR_BOOK !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || ( function_exists( 'mcupr_aud_can_view' ) && ! mcupr_aud_can_view( $id ) ) ) {
			return '';
		}
		return mcupr_book_reader( $id );
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
