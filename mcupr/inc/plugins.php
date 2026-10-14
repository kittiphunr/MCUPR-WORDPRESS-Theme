<?php
/**
 * Free plugins the theme integrates with, so it can stay light.
 *
 * - Polylang          : languages, URLs, content translation, menus, hreflang (see inc/i18n.php)
 * - Yoast SEO / Rank Math : SEO + breadcrumbs (breadcrumb markup is used when enabled)
 * - Elementor (+ Pro) : page / Theme Builder templates (see inc/elementor.php)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Breadcrumb from an SEO plugin if one provides it; returns true when printed. */
function mcupr_plugin_breadcrumb() {
	$label = esc_attr( mcupr_t( 'breadcrumb' ) );
	$open  = '<nav class="wrap my-4 text-[.9375rem] text-muted [&_a]:text-link" aria-label="' . $label . '">';
	$html  = '';

	if ( function_exists( 'yoast_breadcrumb' ) ) {
		$html = (string) yoast_breadcrumb( '', '', false );
	} elseif ( function_exists( 'rank_math_the_breadcrumbs' ) ) {
		ob_start();
		rank_math_the_breadcrumbs();
		$html = trim( ob_get_clean() );
	}

	if ( '' === $html ) {
		return false;
	}
	echo $open . wp_kses_post( $html ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return true;
}

/** Recommended free plugins shown once to administrators. */
function mcupr_recommended_plugins() {
	return array(
		array(
			'slug'    => 'polylang',
			'file'    => 'polylang/polylang.php',
			'name'    => 'Polylang',
			'purpose' => __( 'multilingual content, language URLs, per-language menus (replaces the theme\'s built-in translation box)', 'mcupr' ),
			'active'  => mcupr_has_polylang(),
		),
		array(
			'slug'    => 'wordpress-seo',
			'file'    => 'wordpress-seo/wp-seo.php',
			'name'    => 'Yoast SEO',
			'purpose' => __( 'SEO, sitemap and breadcrumbs (or use Rank Math)', 'mcupr' ),
			'active'  => defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ),
		),
		array(
			'slug'    => 'elementor',
			'file'    => 'elementor/elementor.php',
			'name'    => 'Elementor',
			'purpose' => __( 'visual page builder (Elementor Pro adds the Theme Builder)', 'mcupr' ),
			'active'  => defined( 'ELEMENTOR_VERSION' ),
		),
	);
}

function mcupr_admin_notice() {
	if ( ! current_user_can( 'install_plugins' ) || get_user_meta( get_current_user_id(), 'mcupr_dismiss_plugins', true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'plugins' ), true ) ) {
		return;
	}
	$missing = array_filter(
		mcupr_recommended_plugins(),
		static function ( $p ) {
			return ! $p['active'];
		}
	);
	if ( ! $missing ) {
		return;
	}
	$dismiss = wp_nonce_url( add_query_arg( 'mcupr_dismiss_plugins', '1' ), 'mcupr_dismiss_plugins' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'MCUPR theme: recommended free plugins', 'mcupr' ) . '</strong></p><ul style="list-style:disc;margin-left:1.5em">';
	foreach ( $missing as $p ) {
		$url = admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $p['slug'] );
		echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $p['name'] ) . '</a> – ' . esc_html( $p['purpose'] ) . '</li>';
	}
	echo '</ul><p><a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'mcupr' ) . '</a></p></div>';
}
add_action( 'admin_notices', 'mcupr_admin_notice' );

function mcupr_dismiss_notice() {
	if ( isset( $_GET['mcupr_dismiss_plugins'] ) && check_admin_referer( 'mcupr_dismiss_plugins' ) ) {
		update_user_meta( get_current_user_id(), 'mcupr_dismiss_plugins', 1 );
		wp_safe_redirect( remove_query_arg( array( 'mcupr_dismiss_plugins', '_wpnonce' ) ) );
		exit;
	}
}
add_action( 'admin_init', 'mcupr_dismiss_notice' );
