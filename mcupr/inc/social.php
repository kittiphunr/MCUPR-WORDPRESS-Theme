<?php
/**
 * Social media links for the footer (Customizer → organisation): Facebook, YouTube, LINE Official Account,
 * Instagram, X, TikTok. Only the ones with an address are shown.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_social_networks() {
	return array(
		'facebook'  => array( 'Facebook', '<path fill="currentColor" stroke="none" d="M13.5 22v-8.2h2.8l.5-3.3h-3.3V8.4c0-.9.4-1.7 1.8-1.7h1.6V3.8s-1.4-.3-2.8-.3c-2.8 0-4.5 1.7-4.5 4.7v2.3H6.8v3.3h2.8V22z"/>' ),
		'youtube'   => array( 'YouTube', '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1c.3-1.3.4-2.5.4-3.8s-.1-2.5-.4-3.8z"/><path fill="currentColor" d="M10 9.5v5l4.3-2.5z"/>' ),
		'line'      => array( 'LINE', '<path d="M12 3C6.5 3 2 6.6 2 11c0 3.9 3.5 7.2 8.3 7.9.3.1.8.2.9.5.1.3.1.7 0 1l-.1.8c0 .3-.2 1 .9.5s5.8-3.4 7.9-5.8c1.5-1.6 2.1-3.2 2.1-4.9C22 6.6 17.5 3 12 3z"/><path d="M8 9.5v3.2h2M12 9.5v3.2M14.5 12.7V9.5l2.5 3.2V9.5"/>' ),
		'instagram' => array( 'Instagram', '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor"/>' ),
		'x'         => array( 'X', '<path d="M4 4l16 16M20 4L4 20"/>' ),
		'tiktok'    => array( 'TikTok', '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.3 2.4 1.9 4 4.5 4.2"/>' ),
	);
}

function mcupr_social_customize( $wp_customize ) {
	foreach ( mcupr_social_networks() as $key => $net ) {
		$wp_customize->add_setting( 'mcupr_social_' . $key, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'mcupr_social_' . $key, array( 'section' => 'mcupr_org', 'label' => sprintf( /* translators: %s: network name */ __( 'Link to your %s page (shown as an icon in the footer)', 'mcupr' ), $net[0] ), 'type' => 'url' ) );
	}
}
add_action( 'customize_register', 'mcupr_social_customize', 30 );

function mcupr_social_links() {
	$items = array();
	foreach ( mcupr_social_networks() as $key => $net ) {
		$url = esc_url_raw( (string) get_theme_mod( 'mcupr_social_' . $key, '' ) );
		if ( '' !== $url ) {
			$items[] = '<li><a class="soc-a" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $net[0] . ' (' . mcupr_t( 'new_tab' ) . ')' ) . '"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $net[1] . '</svg></a></li>';
		}
	}
	if ( $items ) {
		echo '<ul class="soc" aria-label="' . esc_attr( mcupr_t( 'social_label' ) ) . '">' . implode( '', $items ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
