<?php
/**
 * Languages of the site: which of the built-in four (Thai, English, Chinese, Japanese) are shown, and extra languages the owner adds
 * (Korean, Vietnamese, …) in Customizer → "MCUPR: Languages & translations". Every language other than the default one has its own tab
 * there with all its texts, instead of one long page with "(Chinese)" after each label.
 *
 * A built-in language keeps its ready-made interface text (menus, buttons). An added language has none, so the interface text shows in
 * English for it (what the visitor reads in the pages, the menu, the hero, the notice bar … is in the added language as soon as the owner
 * fills it in; anything left empty shows the default language's text).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Languages the owner may add by typing just the code: code => label (the language's own name), English name, <html lang>, locale. Left-to-right scripts only (the theme is not drawn for right-to-left). */
function mcupr_lang_presets() {
	return array(
		'ko' => array( '한국어', 'Korean', 'ko', 'ko_KR' ),
		'vi' => array( 'Tiếng Việt', 'Vietnamese', 'vi', 'vi' ),
		'lo' => array( 'ລາວ', 'Lao', 'lo', 'lo' ),
		'my' => array( 'မြန်မာ', 'Burmese', 'my', 'my_MM' ),
		'km' => array( 'ខ្មែរ', 'Khmer', 'km', 'km' ),
		'id' => array( 'Bahasa Indonesia', 'Indonesian', 'id', 'id_ID' ),
		'ms' => array( 'Bahasa Melayu', 'Malay', 'ms', 'ms_MY' ),
		'tl' => array( 'Filipino', 'Filipino', 'fil', 'fil' ),
		'hi' => array( 'हिन्दी', 'Hindi', 'hi', 'hi_IN' ),
		'ne' => array( 'नेपाली', 'Nepali', 'ne', 'ne_NP' ),
		'si' => array( 'සිංහල', 'Sinhala', 'si', 'si_LK' ),
		'fr' => array( 'Français', 'French', 'fr', 'fr_FR' ),
		'de' => array( 'Deutsch', 'German', 'de', 'de_DE' ),
		'es' => array( 'Español', 'Spanish', 'es', 'es_ES' ),
		'pt' => array( 'Português', 'Portuguese', 'pt', 'pt_PT' ),
		'it' => array( 'Italiano', 'Italian', 'it', 'it_IT' ),
		'ru' => array( 'Русский', 'Russian', 'ru', 'ru_RU' ),
	);
}

/** The built-in languages (their interface texts ship with the theme). */
function mcupr_lang_builtin() {
	return mcupr_ui_languages();
}

/** Codes that must not be a language: they end other settings of the theme (…_url, …_on). */
function mcupr_lang_reserved() {
	return array( 'url', 'on', 'off', 'btn', 'alt', 'img', 'tag', 'all', 'new', 'set', 'max', 'min', 'key', 'ids', 'pdf', 'ics' );
}

/**
 * The lines the owner typed ("ko", or "ko | 한국어 | Korean | ko | ko_KR") as code => array( label, name, html, locale ).
 * A short line gets what is missing from the list of presets; a line that is wrong is left out.
 */
function mcupr_lang_extra_parse( $text ) {
	$out     = array();
	$presets = mcupr_lang_presets();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$p    = array_map( 'trim', explode( '|', $line ) );
		$code = strtolower( $p[0] );
		if ( ! preg_match( '/^[a-z]{2,3}$/', $code ) || isset( mcupr_lang_builtin()[ $code ] ) || in_array( $code, mcupr_lang_reserved(), true ) || isset( $out[ $code ] ) ) {
			continue;
		}
		$pre   = isset( $presets[ $code ] ) ? $presets[ $code ] : array( '', '', $code, '' );
		$label = isset( $p[1] ) && '' !== $p[1] ? $p[1] : $pre[0];
		$name  = isset( $p[2] ) && '' !== $p[2] ? $p[2] : ( '' !== $pre[1] ? $pre[1] : $label );
		$html  = isset( $p[3] ) && '' !== $p[3] ? $p[3] : $pre[2];
		$loc   = isset( $p[4] ) && '' !== $p[4] ? $p[4] : ( '' !== $pre[3] ? $pre[3] : $html );
		$label = mb_substr( sanitize_text_field( $label ), 0, 30 );
		$name  = mb_substr( sanitize_text_field( $name ), 0, 40 );
		if ( '' === $label || '' === $name || ! preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $html ) || ! preg_match( '/^[A-Za-z]{2,3}(_[A-Za-z0-9]{2,8})*$/', $loc ) ) {
			continue;
		}
		$out[ $code ] = array( 'label' => $label, 'name' => $name, 'html' => $html, 'locale' => $loc, 'custom' => true );
	}
	return $out;
}

/** Text of the control cleaned: one valid, complete line for each language. */
function mcupr_lang_extra_clean( $text ) {
	$lines = array();
	foreach ( mcupr_lang_extra_parse( $text ) as $code => $d ) {
		$lines[] = implode( ' | ', array( $code, $d['label'], $d['name'], $d['html'], $d['locale'] ) );
	}
	return implode( "\n", $lines );
}

/** The built-in languages that were switched off in the Customizer (never the default language). */
function mcupr_lang_off() {
	$default = get_theme_mod( 'mcupr_default_lang', 'th' );
	$off     = array();
	foreach ( array_keys( mcupr_lang_builtin() ) as $code ) {
		if ( $code !== $default && ! get_theme_mod( 'mcupr_lang_on_' . $code, true ) ) {
			$off[] = $code;
		}
	}
	return $off;
}

/** The languages on this site: the built-in ones that are on, then the ones the owner added. */
add_filter(
	'mcupr_languages',
	static function ( $langs ) {
		foreach ( mcupr_lang_off() as $code ) {
			unset( $langs[ $code ] );
		}
		foreach ( mcupr_lang_extra_parse( (string) get_theme_mod( 'mcupr_langs_extra', '' ) ) as $code => $d ) {
			$langs[ $code ] = $d;
		}
		return $langs;
	}
);

/** Every code that may be a suffix of a language setting (built-in, added, and the ones switched off, whose texts stay stored). */
function mcupr_lang_all_codes() {
	return array_values( array_unique( array_merge( array_keys( mcupr_lang_builtin() ), array_keys( mcupr_lang_extra_parse( (string) get_theme_mod( 'mcupr_langs_extra', '' ) ) ) ) ) );
}

/* --------------------------------------------------------------------------------------------------- Customizer */

/** Where the controls of a text go in a language's tab: base name => position (the rest after these). */
function mcupr_lang_order() {
	return array_flip(
		array(
			'mcupr_name', 'mcupr_address', 'mcupr_hours', 'mcupr_about', 'mcupr_hero_eyebrow', 'mcupr_hero_l1', 'mcupr_hero_l2', 'mcupr_hero_sub', 'mcupr_hero_btn1', 'mcupr_hero_btn2',
			'mcupr_cta_title', 'mcupr_popular', 'mcupr_notice_text', 'mcupr_notice_link', 'mcupr_print_foot_text',
		)
	);
}

/** A label without the language in brackets: "Hero: first button text (Chinese)" → "Hero: first button text". */
function mcupr_lang_strip_label( $label, $l ) {
	$names = array_unique( array( (string) $l['name'], mcupr_admin_lang_name( $l['name'] ), (string) $l['label'] ) );
	foreach ( $names as $n ) {
		if ( '' !== $n ) {
			$label = str_replace( array( ' (' . $n . ')', '(' . $n . ') ', '(' . $n . ')' ), '', $label );
		}
	}
	return trim( preg_replace( '/\s{2,}/u', ' ', $label ) );
}

function mcupr_customize_langs( $wp_customize ) {
	$langs   = mcupr_languages();
	$default = mcupr_default_lang();

	$wp_customize->add_panel(
		'mcupr_langs',
		array(
			'title'       => __( 'MCUPR: Languages & translations', 'mcupr' ),
			'description' => __( 'Add or remove languages, and write the texts of each language in its own tab. A field left empty shows the text of the default language.', 'mcupr' ),
			'priority'    => 29,
		)
	);
	$wp_customize->add_section( 'mcupr_langs_set', array( 'panel' => 'mcupr_langs', 'title' => __( 'Languages on the site (add / remove)', 'mcupr' ), 'priority' => 1 ) );
	foreach ( mcupr_lang_builtin() as $code => $l ) {
		if ( $code === $default ) {
			continue;
		}
		$wp_customize->add_setting( 'mcupr_lang_on_' . $code, array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_lang_on_' . $code, array( 'section' => 'mcupr_langs_set', 'label' => sprintf( /* translators: 1: language name, 2: its own name */ __( 'Show %1$s (%2$s) on the site', 'mcupr' ), mcupr_admin_lang_name( $l['name'] ), $l['label'] ), 'description' => __( 'Untick to remove this language from the language picker, the menus and the fields. What you already wrote stays saved.', 'mcupr' ), 'type' => 'checkbox' ) );
	}
	$wp_customize->add_setting( 'mcupr_langs_extra', array( 'default' => '', 'sanitize_callback' => 'mcupr_lang_extra_clean' ) );
	$wp_customize->add_control(
		'mcupr_langs_extra',
		array(
			'section'     => 'mcupr_langs_set',
			'label'       => __( 'Add languages (one per line)', 'mcupr' ),
			/* translators: %s: codes, comma separated */
			'description' => sprintf( __( 'Type just the code of a language from this list and the rest is filled in: %s. Another language: code | name in the picker | English name | html lang | locale, for example: sv | Svenska | Swedish | sv | sv_SE. Delete the line to remove the language. The buttons and menus of the theme itself stay in English for an added language; the pages, the hero, the notice and your texts are yours to write in it.', 'mcupr' ), implode( ', ', array_keys( mcupr_lang_presets() ) ) ),
			'type'        => 'textarea',
		)
	);

	// one tab (section) for each language other than the default; the text controls of that language move into it
	$order = mcupr_lang_order();
	$codes = array();
	foreach ( $langs as $code => $l ) {
		if ( $code !== $default ) {
			$codes[] = $code;
		}
	}
	$sec_pri = 10;
	foreach ( $codes as $code ) {
		$wp_customize->add_section(
			'mcupr_lang_' . $code,
			array(
				'panel'       => 'mcupr_langs',
				'title'       => $langs[ $code ]['label'] . ' – ' . mcupr_admin_lang_name( $langs[ $code ]['name'] ),
				'description' => sprintf( /* translators: %s: the default language */ __( 'Everything written here is for this language. A field left empty shows the %s text.', 'mcupr' ), mcupr_admin_lang_name( $langs[ $default ]['name'] ) ),
				'priority'    => $sec_pri++,
			)
		);
	}
	$all = mcupr_lang_all_codes();
	foreach ( $wp_customize->controls() as $id => $c ) {
		if ( 0 === strpos( $id, 'mcupr_lang_on_' ) || ! preg_match( '/^(mcupr_.+)_([a-z]{2,3})$/', $id, $m ) || ! in_array( $m[2], $all, true ) || in_array( $m[2], mcupr_lang_reserved(), true ) ) {
			continue;
		}
		$code = $m[2];
		if ( ! isset( $langs[ $code ] ) || ( $code !== $default && ! $wp_customize->get_control( $m[1] . '_' . $default ) ) ) { // a text of a language has its twin in the default language's tab
			continue;
		}
		$c->label = mcupr_lang_strip_label( (string) $c->label, $langs[ $code ] );
		if ( $code !== $default ) {
			$c->section  = 'mcupr_lang_' . $code;
			$c->priority = isset( $order[ $m[1] ] ) ? 10 + $order[ $m[1] ] : 900;
		}
	}
}
add_action( 'customize_register', 'mcupr_customize_langs', 999 );
