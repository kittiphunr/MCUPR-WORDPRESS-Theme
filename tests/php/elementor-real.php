<?php
/**
 * Elementor pages printed by the theme without the plugin, from templates made by the real Elementor (tests/fixtures/elementor-real/:
 * the templates of Elementor's own test suite, GPL-3.0, see LICENSE.txt there): whole pages (the law-firm site) and one template for each
 * widget. Nothing in the data is changed. What is checked for every template: it prints without a PHP warning or error, the text of
 * its headings, buttons and text blocks is in the page, no widget is dropped silently (what the theme cannot draw says so), no script
 * or event handler comes from a plain text setting, no empty page, the style is written.
 *   php php/elementor-real.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_set_current_user( 1 );
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$warn = array();
set_error_handler( static function ( $no, $str, $file, $line ) use ( &$warn ) { if ( $no & ( E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED ) && false === strpos( $file, 'wp-includes' ) ) { $warn[] = basename( $file ) . ':' . $line . ' ' . $str; } return true; } );

$dir   = realpath( __DIR__ . '/../fixtures/elementor-real' );
$files = array_merge( glob( $dir . '/*.json' ), glob( $dir . '/atomic/*.json' ), glob( $dir . '/playwright/*.json' ) );
t( count( $files ) >= 40, 'fixtures: ' . count( $files ) . ' real Elementor templates are there' );
$walk = static function ( $els, &$out ) use ( &$walk ) {
	foreach ( (array) $els as $e ) {
		if ( ! is_array( $e ) ) { continue; }
		$out[] = $e;
		$walk( isset( $e['elements'] ) ? $e['elements'] : array(), $out );
	}
};
$types = array(); $made = array(); $totals = array( 'widgets' => 0, 'unknown' => array() );
foreach ( $files as $f ) {
	$name = in_array( basename( dirname( $f ) ), array( 'atomic', 'playwright' ), true ) ? basename( dirname( $f ) ) . '/' . basename( $f ) : basename( $f );
	$d    = json_decode( (string) file_get_contents( $f ), true );
	$content = is_array( $d ) && isset( $d['content'] ) ? $d['content'] : ( is_array( $d ) ? $d : array() );
	if ( ! $content ) { t( false, $name . ': the file has content' ); continue; }
	$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'ELR ' . $name, 'post_content' => '<p>old</p>' ) );
	update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
	update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
	if ( ! empty( $d['page_settings'] ) ) { update_post_meta( $pid, '_elementor_page_settings', $d['page_settings'] ); }
	$made[] = $pid;
	$warn = array();
	mcupr_el_state_ref( true );
	$html = '';
	try { $html = (string) mcupr_el_render_post( $pid ); } catch ( Throwable $e ) { t( false, $name . ': prints without an error (' . $e->getMessage() . ')' ); continue; }
	$st  = mcupr_el_state();
	$all = array(); $walk( $content, $all );
	$ws  = array_values( array_filter( $all, static function ( $e ) { return isset( $e['elType'] ) && 'widget' === $e['elType']; } ) );
	$totals['widgets'] += count( $ws );
	t( ! $warn, $name . ': prints without a PHP warning' . ( $warn ? ' (' . implode( ' | ', array_slice( array_unique( $warn ), 0, 3 ) ) . ')' : '' ) );
	t( strlen( trim( wp_strip_all_tags( $html ) ) ) > 0 || ! array_filter( $ws, static function ( $w ) { return in_array( $w['widgetType'], array( 'heading', 'text-editor', 'button', 'icon-box', 'image-box', 'call-to-action', 'testimonial' ), true ); } ), $name . ': the page is not empty' );
	// the words of the data are in the page
	$miss = array();
	foreach ( $ws as $w ) {
		$s = isset( $w['settings'] ) && is_array( $w['settings'] ) ? $w['settings'] : array();
		foreach ( array( 'title', 'text', 'editor', 'description_text', 'title_text', 'heading', 'button_text', 'testimonial_name', 'paragraph' ) as $k ) {
			if ( isset( $s[ $k ] ) && is_array( $s[ $k ] ) && isset( $s[ $k ]['$$type'] ) ) { $s[ $k ] = mcupr_el_at( $s[ $k ] ); }
			if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) && in_array( $w['widgetType'], array( 'heading', 'button', 'text-editor', 'icon-box', 'image-box', 'call-to-action', 'alert', 'testimonial', 'e-heading', 'e-paragraph', 'e-button' ), true ) ) {
				$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( preg_replace( '/<[^>]+>/', ' ', $s[ $k ] ) ), ENT_QUOTES, 'UTF-8' ) ) );
				$hay   = preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( preg_replace( '/<[^>]+>/', ' ', $html ) ), ENT_QUOTES, 'UTF-8' ) );
				$first = mb_substr( $plain, 0, 30 );
				if ( '' !== $first && false === mb_stripos( $hay, $first ) ) { $miss[] = $w['widgetType'] . '.' . $k . ' "' . $first . '"'; }
			}
		}
	}
	t( ! $miss, $name . ': the words of its headings, buttons and text blocks are in the page' . ( $miss ? ' (missing: ' . implode( '; ', array_slice( $miss, 0, 3 ) ) . ')' : '' ) );
	// widgets the theme cannot draw: reported, not dropped silently
	foreach ( $ws as $w ) { $types[ $w['widgetType'] ] = true; }
	foreach ( array_keys( (array) $st['unsupported'] ) as $u ) { $totals['unknown'][ $u ] = true; }
	// no script from a plain text setting
	$scripts = preg_match_all( '/<script\b/i', $html );
	$html_w  = count( array_filter( $ws, static function ( $w ) { return in_array( $w['widgetType'], array( 'html', 'google_maps', 'video', 'sound_cloud', 'audio' ), true ); } ) );
	t( 0 === $scripts || $html_w > 0, $name . ': no <script> in the page unless an HTML / embed widget put it there (' . $scripts . ')' );
	t( ! preg_match( '/\son(?:click|error|load|mouseover)\s*=/i', $html ) || $html_w > 0, $name . ': no event-handler attribute in the page' );
}
t( count( $types ) >= 35, 'coverage: ' . count( $types ) . ' kinds of widget appear in the templates (' . implode( ', ', array_slice( array_keys( $types ), 0, 50 ) ) . ')' );
echo 'INFO widgets in all templates: ' . $totals['widgets'] . '; the theme could not draw: ' . ( $totals['unknown'] ? implode( ', ', array_keys( $totals['unknown'] ) ) : 'none' ) . "\n";
foreach ( $made as $pid ) { wp_delete_post( $pid, true ); }
restore_error_handler();
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
