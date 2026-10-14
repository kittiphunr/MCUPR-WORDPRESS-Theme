<?php
/**
 * Contact details / footer map (inc/contact.php).   php php/contact.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$mods = array( 'mcupr_address_th', 'mcupr_address_en', 'mcupr_org_tel', 'mcupr_hours_th', 'mcupr_map_query', 'mcupr_map_on', 'mcupr_org_email' );
$orig = array();
foreach ( $mods as $m ) { $orig[ $m ] = get_theme_mod( $m, null ); remove_theme_mod( $m ); }
$c = mcupr_contact();
t( strpos( $c['address'], 'แม่คำมี' ) !== false && '054 646 273' === $c['tel'] && '' !== $c['hours'] && '' !== $c['map_q'] && $c['map_on'], 'defaults: address, telephone, hours and map are filled in' );
set_theme_mod( 'mcupr_address_th', "1 ถนนทดสอบ\nกรุงเทพฯ" ); set_theme_mod( 'mcupr_org_tel', '02 123 4567' ); set_theme_mod( 'mcupr_hours_th', 'ทุกวัน 9–17' ); set_theme_mod( 'mcupr_map_query', '13.75,100.5' ); set_theme_mod( 'mcupr_org_email', 'a@example.org' );
$c = mcupr_contact();
t( strpos( $c['address'], 'ทดสอบ' ) !== false && '02 123 4567' === $c['tel'] && 'ทุกวัน 9–17' === $c['hours'] && '13.75,100.5' === $c['map_q'], 'Customizer values replace the defaults' );
ob_start(); mcupr_contact_list(); $l = ob_get_clean();
t( strpos( $l, 'href="tel:021234567"' ) !== false && strpos( $l, 'mailto:a@example.org' ) !== false && strpos( $l, '<br' ) !== false, 'contact list: tel / mailto links, address line break' );
ob_start(); mcupr_map_card(); $m = ob_get_clean();
t( strpos( $m, '<iframe' ) === false, 'map card: no iframe (no Google connection) until the visitor asks' );
t( strpos( $m, 'data-src="https://maps.google.com/maps?q=13.75%2C100.5' ) !== false && strpos( $m, 'output=embed' ) !== false, 'map card: embed URL for the location' );
t( strpos( $m, 'google.com/maps/search/?api=1&#038;query=13.75%2C100.5' ) !== false || strpos( $m, 'google.com/maps/search/?api=1&amp;query=13.75%2C100.5' ) !== false, 'map card: "open in Google Maps" link' );
t( strpos( $m, 'maps/dir/' ) !== false && strpos( $m, 'rel="noopener noreferrer"' ) !== false, 'map card: directions link, safe rel' );
set_theme_mod( 'mcupr_map_on', false );
ob_start(); mcupr_map_card(); t( '' === trim( ob_get_clean() ), 'map card can be switched off' );
t( strpos( mcupr_sc_contact(), '02 123 4567' ) !== false && strpos( mcupr_sc_org( array( 'field' => 'tel' ) ), '021234567' ) !== false, '[mcupr_contact] / [mcupr_org] use the same details' );
foreach ( $mods as $m ) { null === $orig[ $m ] ? remove_theme_mod( $m ) : set_theme_mod( $m, $orig[ $m ] ); } // leave the site as it was
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
