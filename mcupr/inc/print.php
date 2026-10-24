<?php
/**
 * Printing a post / page on A4 with a Thai-government-newsletter style letterhead: a header and a footer
 * graphic on every sheet, an optional full-page background, and the article in the clear space between.
 *
 * Everything is editable in the Customizer (MCUPR: Print on A4): header / footer / background images,
 * their heights, the side margins, a text letterhead (logo, organisation name, rule) and footer text that are
 * used when no image is set, and what the printed article shows. A post can switch the letterhead off.
 *
 * The header and footer are `position: fixed` (printed on every page) and sit in the @page margins, so the
 * article never runs under them.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Print settings with defaults (millimetres are clamped to something that fits an A4 sheet). */
function mcupr_print_options() {
	$n = static function ( $key, $def, $min, $max ) {
		$v = get_theme_mod( $key, '' );
		return '' === $v ? $def : max( $min, min( $max, (int) $v ) );
	};
	return array(
		'head_img' => (int) get_theme_mod( 'mcupr_print_head_img', 0 ),
		'foot_img' => (int) get_theme_mod( 'mcupr_print_foot_img', 0 ),
		'bg_img'   => (int) get_theme_mod( 'mcupr_print_bg_img', 0 ),
		'head_h'   => $n( 'mcupr_print_head_h', 36, 10, 80 ),
		'foot_h'   => $n( 'mcupr_print_foot_h', 20, 8, 60 ),
		'side'     => $n( 'mcupr_print_side', 18, 8, 40 ),
		'text_on'  => (bool) get_theme_mod( 'mcupr_print_text', true ),
		'foot_txt' => trim( (string) mcupr_text_mod( 'mcupr_print_foot_text' ) ),
		'source'   => (bool) get_theme_mod( 'mcupr_print_source', true ),
		'meta'     => (bool) get_theme_mod( 'mcupr_print_meta', true ),
		'wide'     => (bool) get_theme_mod( 'mcupr_print_hide_images', false ),
	);
}

/** Is the letterhead printed for the page being shown? */
function mcupr_print_enabled() {
	if ( ! is_singular() ) {
		return false;
	}
	return 'off' !== get_post_meta( get_queried_object_id(), '_mcupr_print', true );
}

/** The two repeating graphics and the optional background (hidden on screen, shown by the print CSS). */
function mcupr_print_markup() {
	if ( ! mcupr_print_enabled() ) {
		return;
	}
	$o   = mcupr_print_options();
	$img = static function ( $id, $class ) {
		return wp_get_attachment_image( $id, 'full', false, array( 'alt' => '', 'class' => $class, 'loading' => 'eager', 'decoding' => 'sync' ) );
	};
	echo '<div class="print-sheet" aria-hidden="true">';
	if ( $o['bg_img'] ) {
		echo '<div class="print-bg">' . $img( $o['bg_img'], 'print-bg-img' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<div class="print-head">';
	if ( $o['head_img'] ) {
		echo $img( $o['head_img'], 'print-fill' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} elseif ( $o['text_on'] ) {
		$logo = mcupr_logo( 'footer', 'print-logo' );
		echo '<div class="print-lh">' . $logo . '<div class="print-lh-t"><b>' . esc_html( mcupr_org_name() ) . '</b>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( mcupr_alt_name() ) {
			echo '<span>' . esc_html( mcupr_alt_name() ) . '</span>';
		}
		echo '</div></div><div class="print-rule"></div>';
	}
	echo '</div><div class="print-foot">';
	if ( $o['foot_img'] ) {
		echo $img( $o['foot_img'], 'print-fill' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		$txt = $o['foot_txt'] ? $o['foot_txt'] : trim( (string) mcupr_text_mod( 'mcupr_address' ) );
		echo '<div class="print-rule"></div><p class="print-ft">' . nl2br( esc_html( $txt ) ) . '</p>';
	}
	if ( $o['source'] ) {
		echo '<p class="print-src">' . esc_html( mcupr_t( 'print_from' ) . ' ' . get_permalink() . ' · ' . mcupr_date_fmt( 'j M Y' ) ) . '</p>';
	}
	echo '</div></div>';
}
add_action( 'wp_footer', 'mcupr_print_markup', 5 );

/** The page geometry: the @page margins hold the header / footer. */
function mcupr_print_css() {
	if ( ! mcupr_print_enabled() ) {
		return;
	}
	$o = mcupr_print_options();
	$h = $o['head_h'];
	$f = $o['foot_h'];
	$s = $o['side'];
	// @page margin 0 so the graphics can use the full sheet; the margins are padding that is repeated on every page
	// (box-decoration-break: clone) and the header / footer are fixed, i.e. printed on every page.
	$css = "@page{size:A4 portrait;margin:0}"
		. "html body,html[data-menu] body{padding:{$h}mm {$s}mm {$f}mm {$s}mm!important;box-decoration-break:clone;-webkit-box-decoration-break:clone}"
		. ".print-head{top:0;left:0;right:0;height:{$h}mm}"
		. ".print-foot{bottom:0;left:0;right:0;height:{$f}mm}"
		. ( $o['meta'] ? '' : '.print-meta{display:none!important}' )
		. ( $o['wide'] ? '.entry-content img,.print-photo{display:none!important}' : '' );
	echo '<style id="mcupr-print" media="print">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $o['bg_img'] ) {
		echo '<style media="print">.print-bg{display:block}</style>' . "\n";
	}
}
add_action( 'wp_head', 'mcupr_print_css', 30 );

/**
 * What the A4 document of this page is made of (assets/js/print-doc.js): the title, a line with the category and the date
 * (posts only, when the Customizer keeps that line on) and the geometry of the sheet. Print and "Download PDF" both use it.
 *
 * @return array|null Null when this page is not printed with the letterhead.
 */
function mcupr_print_doc_data() {
	if ( ! mcupr_print_enabled() ) {
		return null;
	}
	$o    = mcupr_print_options();
	$post = get_queried_object();
	$meta = '';
	if ( $o['meta'] && $post instanceof WP_Post && 'post' === $post->post_type ) {
		$cat  = mcupr_first_category();
		$meta = ( $cat ? $cat . ' · ' : '' ) . mcupr_t( 'posted_on' ) . ' ' . mcupr_date_text( get_post_timestamp( $post ), 'long' );
	}
	return array(
		'target'   => '#article-body',
		'title'    => wp_strip_all_tags( get_the_title( $post ) ),
		'meta'     => $meta,
		'noimg'    => $o['wide'],
		'head_h'   => $o['head_h'],
		'foot_h'   => $o['foot_h'],
		'side'     => $o['side'],
		'foot_img' => (bool) $o['foot_img'],
	);
}
add_action(
	'wp_enqueue_scripts',
	static function () {
		$d = mcupr_print_doc_data();
		if ( ! $d ) {
			return;
		}
		wp_register_script( 'mcupr-print-doc', get_theme_file_uri( 'assets/js/print-doc.js' ), array(), MCUPR_VERSION, true );
		wp_add_inline_script( 'mcupr-print-doc', 'window.mcuprDoc=' . wp_json_encode( $d ) . ';', 'before' );
		wp_enqueue_script( 'mcupr-print-doc' );
	}
);

/** Customizer. */
function mcupr_print_customize( $wp_customize ) {
	$wp_customize->add_section( 'mcupr_print', array( 'title' => __( 'MCUPR: Print on A4', 'mcupr' ), 'description' => __( 'The Print button of a post or page prints an A4 sheet with a header and a footer graphic on every page, like a Thai government newsletter. Upload images made for A4 (210 mm wide); the article is printed in the space between.', 'mcupr' ), 'priority' => 36 ) );
	foreach ( array(
		'print_head_img' => __( 'Header graphic (full sheet width, e.g. 2480 px wide for 36 mm tall)', 'mcupr' ),
		'print_foot_img' => __( 'Footer graphic (full sheet width)', 'mcupr' ),
		'print_bg_img'   => __( 'Background image for the whole sheet (optional, e.g. a faint watermark, 210 × 297 mm)', 'mcupr' ),
	) as $key => $label ) {
		$wp_customize->add_setting( 'mcupr_' . $key, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_' . $key, array( 'section' => 'mcupr_print', 'label' => $label, 'mime_type' => 'image' ) ) );
	}
	foreach ( array(
		'print_head_h' => array( __( 'Header height (mm)', 'mcupr' ), 36, 10, 80 ),
		'print_foot_h' => array( __( 'Footer height (mm)', 'mcupr' ), 20, 8, 60 ),
		'print_side'   => array( __( 'Left and right margin of the text (mm)', 'mcupr' ), 18, 8, 40 ),
	) as $key => $d ) {
		$wp_customize->add_setting( 'mcupr_' . $key, array( 'default' => $d[1], 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( 'mcupr_' . $key, array( 'section' => 'mcupr_print', 'label' => $d[0], 'type' => 'number', 'input_attrs' => array( 'min' => $d[2], 'max' => $d[3], 'step' => 1 ) ) );
	}
	$wp_customize->add_setting( 'mcupr_print_text', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_print_text', array( 'section' => 'mcupr_print', 'label' => __( 'Without a header graphic: print the logo and the organisation name as the letterhead', 'mcupr' ), 'type' => 'checkbox' ) );
	foreach ( mcupr_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_print_foot_text_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( "mcupr_print_foot_text_{$code}", array( 'section' => 'mcupr_print', /* translators: %s: language */ 'label' => sprintf( __( 'Footer text without a footer graphic (%s) – empty = the address of the organisation', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'textarea' ) );
	}
	$wp_customize->add_setting( 'mcupr_print_source', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_print_source', array( 'section' => 'mcupr_print', 'label' => __( 'Print the web address and date at the foot of every sheet', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_print_meta', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_print_meta', array( 'section' => 'mcupr_print', 'label' => __( 'Print the category and date line above the title', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_print_hide_images', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_print_hide_images', array( 'section' => 'mcupr_print', 'label' => __( 'Do not print the pictures of the article (text only, saves ink)', 'mcupr' ), 'type' => 'checkbox' ) );
}
add_action( 'customize_register', 'mcupr_print_customize', 35 );

/** Per-post switch: no letterhead (plain print). */
add_action(
	'add_meta_boxes',
	static function () {
		foreach ( array( 'post', 'page' ) as $pt ) {
			add_meta_box( 'mcupr_print', __( 'Print on A4', 'mcupr' ), 'mcupr_print_box', $pt, 'side', 'low' );
		}
	}
);
function mcupr_print_box( $post ) {
	wp_nonce_field( 'mcupr_print', 'mcupr_print_nonce' );
	echo '<label><input type="checkbox" name="mcupr_print_off" value="1"' . checked( 'off', get_post_meta( $post->ID, '_mcupr_print', true ), false ) . '> ' . esc_html__( 'Print without the letterhead (plain A4)', 'mcupr' ) . '</label>';
}
add_action(
	'save_post',
	static function ( $post_id ) {
		if ( ! mcupr_check_request( 'mcupr_print_nonce', 'mcupr_print' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['mcupr_print_off'] ) ) {
			update_post_meta( $post_id, '_mcupr_print', 'off' );
		} else {
			delete_post_meta( $post_id, '_mcupr_print' );
		}
	}
);
