<?php
/**
 * The order of the Customizer, by how it is really used: what is changed every week first (the notice bar, the home page,
 * the menus), then what is set once when a campus starts (identity, logos, organisation, colours, footer), then reading,
 * printing and privacy, then the rarely touched. The long "organisation" and "animation" sections are split by purpose, and
 * inside every long section the settings follow the order a person works in (picture, words, buttons, then looks).
 *
 * Done in one place, after every module has registered its own parts (priority 1000, after the languages), so a new module only has to add its
 * section and this list says where it goes. Sections and panels that are not listed keep their place after the listed ones.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Sections and panels from the top of the Customizer to the bottom. */
function mcupr_cz_order() {
	return array(
		// every week
		'mcupr_notice', 'mcupr_home', 'mcupr_home_panel', 'nav_menus', 'mcupr_langs',
		// once, when a campus starts
		'title_tagline', 'mcupr_logos', 'mcupr_org', 'colors', 'mcupr_footer',
		// how it reads and behaves
		'mcupr_display', 'mcupr_loading', 'mcupr_print', 'mcupr_policy', 'mcupr_cookies',
		// rarely
		'mcupr_credit', 'mcupr_lordicon', 'mcupr_shortcodes', 'header_image', 'background_image', 'static_front_page', 'custom_css', 'themes',
	);
}

/** Settings that move to a section of their own, with the order they have there. */
function mcupr_cz_groups() {
	return array(
		'mcupr_footer'  => array(
			'title' => __( 'MCUPR: Footer – map, social links and visitor statistics', 'mcupr' ),
			'desc'  => __( 'The map card, the icons of the organisation’s pages and the visitor statistics card at the bottom of every page.', 'mcupr' ),
			'items' => array( 'mcupr_map_on', 'mcupr_map_query', 'mcupr_social_facebook', 'mcupr_social_youtube', 'mcupr_social_line', 'mcupr_social_instagram', 'mcupr_social_x', 'mcupr_social_tiktok', 'mcupr_footer_col_contact', 'mcupr_footer_col_map', 'mcupr_footer_col_links', 'mcupr_footer_col_stats', 'mcupr_vc_on', 'mcupr_vc_size', 'mcupr_vc_toggle', 'mcupr_vc_ip', 'mcupr_vc_start' ),
		),
		'mcupr_credit'  => array(
			'title' => __( 'MCUPR: Website credit', 'mcupr' ),
			'desc'  => __( 'Who made the website: shown in a card at the end of the page and in the About dialog.', 'mcupr' ),
			'items' => array( 'mcupr_credit_hide', 'mcupr_credit_name', 'mcupr_credit_en', 'mcupr_credit_role', 'mcupr_credit_tag', 'mcupr_credit_photo', 'mcupr_credit_url', 'mcupr_credit_github', 'mcupr_credit_email', 'mcupr_credit_tel' ),
		),
		'mcupr_loading' => array(
			'title' => __( 'MCUPR: Loading screen', 'mcupr' ),
			'desc'  => __( 'The logo that covers a page while it loads. On every page by default; it can be limited to the first page of a visit or switched off.', 'mcupr' ),
			'items' => array( 'mcupr_preload', 'mcupr_preload_logo', 'mcupr_preload_logo_dark', 'mcupr_preload_text_show', 'mcupr_preload_text' ),
		),
	);
}

/** The order of the settings inside the sections that keep them (a setting not listed goes after the listed ones). */
function mcupr_cz_controls() {
	return array(
		'mcupr_notice'  => array( 'mcupr_notice_on', 'mcupr_notice_style', 'mcupr_notice_text_th', 'mcupr_notice_link_th', 'mcupr_notice_url', 'mcupr_notice_from', 'mcupr_notice_to', 'mcupr_notice_gray' ),
		'mcupr_home'    => array(
			'mcupr_hero_image', 'mcupr_hero_eyebrow_th', 'mcupr_hero_l1_th', 'mcupr_hero_l2_th', 'mcupr_hero_sub_th', 'mcupr_hero_btn1_th', 'mcupr_hero_btn1_url', 'mcupr_hero_btn2_th', 'mcupr_hero_btn2_url',
			'mcupr_hero_overlay', 'mcupr_hero_height', 'mcupr_hero_align', 'mcupr_hero_focus', 'mcupr_hero_quick', 'mcupr_hero_persona', 'mcupr_hero_auto',
			'mcupr_hero2_image', 'mcupr_hero2_title_th', 'mcupr_hero2_sub_th', 'mcupr_hero2_btn_th', 'mcupr_hero2_url',
			'mcupr_hero3_image', 'mcupr_hero3_title_th', 'mcupr_hero3_sub_th', 'mcupr_hero3_btn_th', 'mcupr_hero3_url',
			'mcupr_popular_th', 'mcupr_home_about_page', 'mcupr_home_cat_1', 'mcupr_home_cat_2', 'mcupr_home_cat_3', 'mcupr_cta_title_th', 'mcupr_cta_url',
		),
		'mcupr_org'     => array( 'mcupr_default_lang', 'mcupr_name_th', 'mcupr_about_th', 'mcupr_address_th', 'mcupr_hours_th', 'mcupr_org_tel', 'mcupr_org_email', 'mcupr_form_to' ),
		'mcupr_print'   => array( 'mcupr_print_head_img', 'mcupr_print_head_h', 'mcupr_print_foot_img', 'mcupr_print_foot_h', 'mcupr_print_bg_img', 'mcupr_print_side', 'mcupr_print_text', 'mcupr_print_foot_text_th', 'mcupr_print_source', 'mcupr_print_meta', 'mcupr_print_hide_images' ),
	);
}

/** Header features that sat in the organisation section belong with the menu and the toolbar. */
function mcupr_cz_to_display() {
	return array( 'mcupr_fs_menu' => 13, 'mcupr_utility_bar' => 15 );
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		// 1. new sections for the settings that move
		$prio = 0;
		foreach ( mcupr_cz_groups() as $id => $g ) {
			if ( ! $wp_customize->get_section( $id ) ) {
				$wp_customize->add_section( $id, array( 'title' => $g['title'], 'description' => $g['desc'] ) );
			}
			foreach ( $g['items'] as $n => $cid ) {
				$c = $wp_customize->get_control( $cid );
				if ( $c ) {
					$c->section  = $id;
					$c->priority = ( $n + 1 ) * 10;
				}
			}
		}
		foreach ( mcupr_cz_to_display() as $cid => $p ) {
			$c = $wp_customize->get_control( $cid );
			if ( $c ) {
				$c->section  = 'mcupr_display';
				$c->priority = $p;
			}
		}
		// 2. the order inside the long sections
		foreach ( mcupr_cz_controls() as $sec => $ids ) {
			foreach ( $ids as $n => $cid ) {
				$c = $wp_customize->get_control( $cid );
				if ( $c && $c->section === $sec ) {
					$c->priority = ( $n + 1 ) * 10;
				}
			}
		}
		// 3. the order of the sections and panels themselves
		foreach ( mcupr_cz_order() as $n => $id ) {
			$o = $wp_customize->get_panel( $id );
			$o = $o ? $o : $wp_customize->get_section( $id );
			if ( $o ) {
				$o->priority = ( $n + 1 ) * 10;
			}
		}
		$org = $wp_customize->get_section( 'mcupr_org' );
		if ( $org ) {
			$org->title = __( 'MCUPR: Organisation, language & contact', 'mcupr' );
		}
	},
	1000
);
