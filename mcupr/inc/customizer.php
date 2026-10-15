<?php
/**
 * Customizer settings.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'mcupr_org',
		array(
			'title'    => __( 'MCUPR: Organisation & languages', 'mcupr' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_setting( 'mcupr_default_lang', array( 'default' => 'th', 'sanitize_callback' => 'mcupr_sanitize_lang' ) );
	$choices = array();
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$choices[ $code ] = $l['label'] . ' (' . mcupr_admin_lang_name( $l['name'] ) . ')';
	}
	$wp_customize->add_control( 'mcupr_default_lang', array( 'section' => 'mcupr_org', 'label' => __( 'Default language (language of the main editor)', 'mcupr' ), 'type' => 'select', 'choices' => $choices ) );

	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_name_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_name_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'Organisation name (%s)', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'text' ) );

		$wp_customize->add_setting( "mcupr_address_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( "mcupr_address_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'Address / contact (%s)', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'textarea' ) );
	}

	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_hours_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( "mcupr_hours_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'Opening hours (%s) – footer', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'textarea' ) );
	}
	$wp_customize->add_setting( 'mcupr_map_on', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_map_on', array( 'section' => 'mcupr_org', 'label' => __( 'Show the Google Maps location card in the footer (loads only when the visitor presses “Show map”)', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_map_query', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'mcupr_map_query', array( 'section' => 'mcupr_org', 'label' => __( 'Map location: place name, address or "latitude,longitude" (empty = built-in default)', 'mcupr' ), 'type' => 'text' ) );

	$wp_customize->add_setting( 'mcupr_fs_menu', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_fs_menu', array( 'section' => 'mcupr_org', 'label' => __( 'Show the “All menu” button that opens the whole menu full screen (desktop top bar)', 'mcupr' ), 'type' => 'checkbox' ) );

	$wp_customize->add_setting( 'mcupr_utility_bar', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_utility_bar', array( 'section' => 'mcupr_org', 'label' => __( 'Show the built-in utility bar (language, text size, dark mode, accessibility, search)', 'mcupr' ), 'type' => 'checkbox' ) );

	// Home hero (Chula-style full-width photo with a two-line headline).
	$wp_customize->add_section( 'mcupr_home', array( 'title' => __( 'MCUPR: Home hero & banner', 'mcupr' ), 'priority' => 32 ) );
	$wp_customize->add_setting( 'mcupr_hero_image', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_hero_image', array( 'section' => 'mcupr_home', 'label' => __( 'Hero background image (falls back to the newest post image)', 'mcupr' ), 'mime_type' => 'image' ) ) );
	foreach ( mcupr_ui_languages() as $code => $l ) {
		/* translators: %s: language name */
		foreach ( array( 'hero_l1' => __( 'Hero headline, light line (%s)', 'mcupr' ), 'hero_l2' => __( 'Hero headline, bold line (%s)', 'mcupr' ), 'cta_title' => __( 'Bottom banner text (%s)', 'mcupr' ) ) as $key => $label ) {
			$wp_customize->add_setting( "mcupr_{$key}_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp_customize->add_control( "mcupr_{$key}_{$code}", array( 'section' => 'mcupr_home', 'label' => sprintf( $label, mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'text' ) );
		}
	}
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_popular_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( "mcupr_popular_{$code}", array( 'section' => 'mcupr_home', 'label' => sprintf( /* translators: %s: language */ __( 'Popular searches, comma separated (%s)', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'text' ) );
	}
	// Home sections
	$wp_customize->add_setting( 'mcupr_home_about_page', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'mcupr_home_about_page', array( 'section' => 'mcupr_home', 'label' => __( '“About us” button links to this page (the intro block uses the About text from Organisation & languages)', 'mcupr' ), 'type' => 'dropdown-pages', 'allow_addition' => false ) );
	$cat_choices = array( 0 => __( '— automatic (most used) —', 'mcupr' ) );
	foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) {
		$cat_choices[ $cat->term_id ] = $cat->name;
	}
	for ( $i = 1; $i <= 3; $i++ ) {
		$wp_customize->add_setting( 'mcupr_home_cat_' . $i, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		/* translators: %d: block number */
		$wp_customize->add_control( 'mcupr_home_cat_' . $i, array( 'section' => 'mcupr_home', 'label' => sprintf( __( 'News by category: block %d', 'mcupr' ), $i ), 'type' => 'select', 'choices' => $cat_choices ) );
	}
	$wp_customize->add_section( 'mcupr_home_sections', array( 'title' => __( 'MCUPR: Home page sections', 'mcupr' ), 'description' => __( 'Show only what you need on the home page. The hero and the quick links are always shown. Events, videos and albums appear only when there are some.', 'mcupr' ), 'priority' => 33 ) );
	foreach ( array(
		'about'  => array( true, __( 'Introduction of the organisation (logo, text, “About us” button)', 'mcupr' ) ),
		'numbers' => array( true, __( 'Numbers (students, teachers, faculties, students per level) – shown when filled in', 'mcupr' ) ),
		'slider' => array( true, __( 'Featured news slider', 'mcupr' ) ),
		'events' => array( true, __( 'Upcoming events', 'mcupr' ) ),
		'media'  => array( true, __( 'Newest video and photo albums', 'mcupr' ) ),
		'cats'   => array( false, __( 'News by category (three blocks)', 'mcupr' ) ),
		'visit'  => array( true, __( 'Visit us (address, telephone, e-mail)', 'mcupr' ) ),
	) as $key => $def ) {
		$wp_customize->add_setting( 'mcupr_home_' . $key . '_on', array( 'default' => $def[0], 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_home_' . $key . '_on', array( 'section' => 'mcupr_home_sections', 'label' => $def[1], 'type' => 'checkbox' ) );
	}
	$wp_customize->add_setting( 'mcupr_cta_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_cta_url', array( 'section' => 'mcupr_home', 'label' => __( 'Bottom banner link (optional)', 'mcupr' ), 'type' => 'url' ) );

	$wp_customize->add_section(
		'mcupr_policy',
		array(
			'title'       => __( 'MCUPR: Policy pages (government web standard)', 'mcupr' ),
			'description' => __( 'Pages linked from the footer. Thai government websites are expected to publish these.', 'mcupr' ),
			'priority'    => 31,
		)
	);
	$pages = array(
		'mcupr_page_privacy' => __( 'Privacy policy (PDPA)', 'mcupr' ),
		'mcupr_page_cookie'  => __( 'Cookie policy', 'mcupr' ),
		'mcupr_page_a11y'    => __( 'Accessibility statement', 'mcupr' ),
		'mcupr_page_sitemap' => __( 'Sitemap', 'mcupr' ),
		'mcupr_page_contact' => __( 'Contact us', 'mcupr' ),
	);
	foreach ( $pages as $id => $label ) {
		$wp_customize->add_setting( $id, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( $id, array( 'section' => 'mcupr_policy', 'label' => $label, 'type' => 'dropdown-pages' ) );
	}

	// ---- colour scheme
	$presets = array();
	foreach ( mcupr_palettes() as $key => $pal ) {
		$presets[ $key ] = $pal['label'];
	}
	$presets['custom'] = __( 'Custom colours', 'mcupr' );
	$wp_customize->add_setting( 'mcupr_palette', array( 'default' => 'rose', 'sanitize_callback' => static function ( $v ) use ( $presets ) { return isset( $presets[ $v ] ) ? $v : 'rose'; } ) );
	$wp_customize->add_control( 'mcupr_palette', array( 'section' => 'colors', 'label' => __( 'Colour scheme', 'mcupr' ), 'description' => __( 'Text on brand colours and link colours are adjusted automatically to keep WCAG AA contrast (4.5:1).', 'mcupr' ), 'type' => 'select', 'choices' => $presets ) );
	foreach ( array(
		'mcupr_primary' => array( '#c2185b', __( 'Custom: main colour (buttons, links, highlights)', 'mcupr' ) ),
		'mcupr_accent'  => array( '#c9a227', __( 'Custom: accent colour (decorative lines, ornaments)', 'mcupr' ) ),
		'mcupr_bar'     => array( '#141414', __( 'Custom: dark surface (top strip, footer)', 'mcupr' ) ),
		'mcupr_tint'    => array( '#f6e9ee', __( 'Custom: soft panel background', 'mcupr' ) ),
	) as $id => $def ) {
		$wp_customize->add_setting( $id, array( 'default' => $def[0], 'sanitize_callback' => 'sanitize_hex_color' ) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, array( 'section' => 'colors', 'label' => $def[1], 'active_callback' => static function () { return 'custom' === get_theme_mod( 'mcupr_palette', 'rose' ); } ) ) );
	}

	// ---- logos
	$wp_customize->add_section( 'mcupr_logos', array( 'title' => __( 'MCUPR: Logos', 'mcupr' ), 'description' => __( 'The main logo is set under Site Identity (leave it empty to use the university emblem that comes with the theme). Add a dark-mode version and a footer version here (optional).', 'mcupr' ), 'priority' => 29 ) );
	$wp_customize->add_setting( 'mcupr_logo_dark', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_logo_dark', array( 'section' => 'mcupr_logos', 'label' => __( 'Logo for dark mode', 'mcupr' ), 'mime_type' => 'image' ) ) );
	$wp_customize->add_setting( 'mcupr_logo_footer', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_logo_footer', array( 'section' => 'mcupr_logos', 'label' => __( 'Footer logo (light-on-dark)', 'mcupr' ), 'mime_type' => 'image' ) ) );
	$wp_customize->add_setting( 'mcupr_logo_wide', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_logo_wide', array( 'section' => 'mcupr_logos', 'label' => __( 'Wide logo (logo with the name inside): show only the logo, not the name beside it', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_logo_height', array( 'default' => 56, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( 'mcupr_logo_height', array( 'section' => 'mcupr_logos', 'label' => __( 'Logo height (px)', 'mcupr' ), 'type' => 'range', 'input_attrs' => array( 'min' => 24, 'max' => 140, 'step' => 2 ) ) );

	// ---- display
	$wp_customize->add_section( 'mcupr_display', array( 'title' => __( 'MCUPR: Animation, news & reading', 'mcupr' ), 'priority' => 33 ) );
	$wp_customize->add_setting( 'mcupr_menu_layout', array( 'default' => 'top', 'sanitize_callback' => static function ( $v ) { return in_array( $v, array( 'side', 'hybrid' ), true ) ? 'side' : 'top'; } ) );
	$wp_customize->add_control( 'mcupr_menu_layout', array( 'section' => 'mcupr_display', 'label' => __( 'Menu position (default for new visitors – each visitor can change it)', 'mcupr' ), 'type' => 'radio', 'choices' => array( 'top' => __( 'Top navigation bar', 'mcupr' ), 'side' => __( 'Sidebar (left)', 'mcupr' ) ) ) );
	$wp_customize->add_setting( 'mcupr_util_inline', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_util_inline', array( 'section' => 'mcupr_display', 'label' => __( 'Show the quick links (students, staff, alumni…) in the header instead of tucking them into a "Quick links" button', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_cat_zones', array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control( 'mcupr_cat_zones', array( 'section' => 'mcupr_display', 'label' => __( 'Category zones for the news browser (one per line: Zone name: slug, slug, …). Leave empty to group by parent category.', 'mcupr' ), 'type' => 'textarea' ) );
	$wp_customize->add_setting( 'mcupr_credit_name', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'mcupr_credit_name', array( 'section' => 'mcupr_org', 'label' => __( 'Site credit: made by (leave empty to use the built-in creator, with photo and contacts)', 'mcupr' ), 'type' => 'text' ) );
	$wp_customize->add_setting( 'mcupr_credit_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp_customize->add_control( 'mcupr_credit_url', array( 'section' => 'mcupr_org', 'label' => __( 'Site credit link (optional)', 'mcupr' ), 'type' => 'url' ) );
	foreach ( array(
		'credit_en'    => array( __( 'Site credit: name in English (optional)', 'mcupr' ), 'sanitize_text_field', 'text' ),
		'credit_github' => array( __( 'Site credit: GitHub / profile link (optional)', 'mcupr' ), 'esc_url_raw', 'url' ),
		'credit_role'  => array( __( 'Site credit: role (e.g. Computer officer)', 'mcupr' ), 'sanitize_text_field', 'text' ),
		'credit_tag'   => array( __( 'Site credit: short tagline (e.g. Network · Server · Full-Stack)', 'mcupr' ), 'sanitize_text_field', 'text' ),
		'credit_email' => array( __( 'Site credit: email', 'mcupr' ), 'sanitize_email', 'email' ),
		'credit_tel'   => array( __( 'Site credit: telephone', 'mcupr' ), 'sanitize_text_field', 'text' ),
		'org_email'    => array( __( 'Organisation email (About dialog)', 'mcupr' ), 'sanitize_email', 'email' ),
		'org_tel'      => array( __( 'Organisation telephone (About dialog)', 'mcupr' ), 'sanitize_text_field', 'text' ),
	) as $key => $def ) {
		$wp_customize->add_setting( 'mcupr_' . $key, array( 'default' => '', 'sanitize_callback' => $def[1] ) );
		$wp_customize->add_control( 'mcupr_' . $key, array( 'section' => 'mcupr_org', 'label' => $def[0], 'type' => $def[2] ) );
	}
	$wp_customize->add_setting( 'mcupr_credit_hide', array( 'default' => false, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_credit_hide', array( 'section' => 'mcupr_org', 'label' => __( 'Hide the site credit (footer card and About dialog developer section)', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_credit_photo', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_credit_photo', array( 'section' => 'mcupr_org', 'label' => __( 'Site credit: photo (optional, shown round)', 'mcupr' ), 'mime_type' => 'image' ) ) );
	foreach ( mcupr_ui_languages() as $code => $l ) {
		$wp_customize->add_setting( "mcupr_about_{$code}", array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control( "mcupr_about_{$code}", array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: language */ __( 'About text (%s) – shown in the About dialog', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ) ), 'type' => 'textarea' ) );
	}
	$wp_customize->add_setting( 'mcupr_anim_default', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_anim_default', array( 'section' => 'mcupr_display', 'label' => __( 'Animations on by default (visitors can switch them off in the accessibility dialog)', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_news_layout', array( 'default' => 'reader', 'sanitize_callback' => 'mcupr_sanitize_layout' ) );
	$wp_customize->add_control( 'mcupr_news_layout', array( 'section' => 'mcupr_display', 'label' => __( 'Default news article layout', 'mcupr' ), 'description' => __( 'Can be overridden per post in the "News layout" box on the post editor.', 'mcupr' ), 'type' => 'select', 'choices' => mcupr_layouts() ) );
	$wp_customize->add_setting( 'mcupr_pattern', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_pattern', array( 'section' => 'mcupr_display', 'label' => __( 'Show the ashoka flower background pattern (visitors can hide it)', 'mcupr' ), 'type' => 'checkbox' ) );
	$wp_customize->add_setting( 'mcupr_listen', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
	$wp_customize->add_control( 'mcupr_listen', array( 'section' => 'mcupr_display', 'label' => __( 'Show the "listen to this article" (text-to-speech) tool', 'mcupr' ), 'type' => 'checkbox' ) );
}
add_action( 'customize_register', 'mcupr_customize_register' );

function mcupr_sanitize_lang( $value ) {
	return isset( mcupr_ui_languages()[ $value ] ) ? $value : 'th';
}

/** Policy page URL for a given customizer key, with WP privacy page fallback. */
function mcupr_policy_url( $key ) {
	$id = (int) get_theme_mod( 'mcupr_page_' . $key, 0 );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	// Not chosen in the Customizer: the page with the conventional slug (the starter import creates them).
	$slugs = array(
		'privacy' => 'privacy-policy',
		'cookie'  => 'cookie-policy',
		'a11y'    => 'accessibility-statement',
		'sitemap' => 'sitemap',
		'contact' => 'contact',
	);
	if ( isset( $slugs[ $key ] ) ) {
		$found = get_posts( array( 'name' => $slugs[ $key ], 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'suppress_filters' => true ) );
		if ( $found ) {
			return get_permalink( $found[0] );
		}
	}
	if ( 'privacy' === $key ) {
		return get_privacy_policy_url();
	}
	return '';
}

/** The three news article layouts. */
function mcupr_layouts() {
	return array(
		'reader'  => __( 'Reader – one centred column, large type', 'mcupr' ),
		'cover'   => __( 'Cover – full-width hero image, side rail with tools', 'mcupr' ),
		'sidebar' => __( 'Sidebar – article with related and latest news beside it', 'mcupr' ),
	);
}

function mcupr_sanitize_layout( $v ) {
	return isset( mcupr_layouts()[ $v ] ) ? $v : 'reader';
}

/** Layout for a post: per-post override, else the site default. */
function mcupr_post_layout( $post_id = 0 ) {
	$post_id  = $post_id ? $post_id : get_the_ID();
	$override = $post_id ? get_post_meta( $post_id, '_mcupr_layout', true ) : '';
	return $override && isset( mcupr_layouts()[ $override ] ) ? $override : mcupr_sanitize_layout( get_theme_mod( 'mcupr_news_layout', 'reader' ) );
}
