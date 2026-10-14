<?php
/**
 * Contact details of the organisation for the footer and the contact page: address, telephone, e-mail, opening
 * hours and a Google Maps location (a click-to-load map: no Google connection until the visitor asks for it).
 * Everything is edited in Customizer → MCUPR: Organisation & languages; the values below are only the
 * starting point (MCU Phrae Campus) and are replaced as soon as a field is filled in.
 * Filter 'mcupr_contact_defaults' to change them.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_contact_defaults() {
	return apply_filters(
		'mcupr_contact_defaults',
		array(
			'address' => array(
				'th' => '111 หมู่ที่ 5 บ้านหนองห้า ถนนยันตรกิจโกศล ตำบลแม่คำมี อำเภอเมืองแพร่ จังหวัดแพร่ 54000',
				'en' => '111 Moo 5, Ban Nong Ha, Yantarakitkosol Road, Mae Kham Mi Sub-district, Mueang Phrae District, Phrae 54000, Thailand',
			),
			'tel'     => '054 646 273',
			'hours'   => array(
				'th' => 'วันจันทร์ – วันศุกร์ 08.30 – 16.30 น. (เว้นวันหยุดราชการ)',
				'en' => 'Monday – Friday 08:30 – 16:30 (closed on public holidays)',
			),
			'map'     => 'มหาวิทยาลัยมหาจุฬาลงกรณราชวิทยาลัย วิทยาเขตแพร่ 111 หมู่ 5 ตำบลแม่คำมี อำเภอเมืองแพร่ แพร่ 54000',
		)
	);
}

/** Current-language contact details (Customizer values, else the defaults). */
function mcupr_contact() {
	$d    = mcupr_contact_defaults();
	$lang = mcupr_ui_lang();
	$pick = static function ( $row ) use ( $lang ) {
		return isset( $row[ $lang ] ) ? $row[ $lang ] : ( isset( $row['en'] ) ? $row['en'] : '' );
	};
	$addr  = (string) mcupr_text_mod( 'mcupr_address' );
	$tel   = (string) get_theme_mod( 'mcupr_org_tel', '' );
	$hours = (string) mcupr_text_mod( 'mcupr_hours' );
	$map   = trim( (string) get_theme_mod( 'mcupr_map_query', '' ) );
	return array(
		'address' => '' !== trim( $addr ) ? $addr : $pick( $d['address'] ),
		'tel'     => '' !== trim( $tel ) ? $tel : $d['tel'],
		'email'   => (string) get_theme_mod( 'mcupr_org_email', '' ),
		'hours'   => '' !== trim( $hours ) ? $hours : $pick( $d['hours'] ),
		'map_q'   => '' !== $map ? $map : $d['map'],
		'map_on'  => (bool) get_theme_mod( 'mcupr_map_on', true ),
	);
}

function mcupr_map_embed_url( $q ) {
	$lang = mcupr_ui_lang();
	return 'https://maps.google.com/maps?' . http_build_query( array( 'q' => $q, 'hl' => $lang, 'z' => 16, 'output' => 'embed' ), '', '&', PHP_QUERY_RFC3986 );
}

function mcupr_map_open_url( $q ) {
	return 'https://www.google.com/maps/search/?' . http_build_query( array( 'api' => 1, 'query' => $q ), '', '&', PHP_QUERY_RFC3986 );
}

function mcupr_map_directions_url( $q ) {
	return 'https://www.google.com/maps/dir/?' . http_build_query( array( 'api' => 1, 'destination' => $q ), '', '&', PHP_QUERY_RFC3986 );
}

/** Click-to-load map card: static placeholder + the links; the iframe is created by main.js on request. */
function mcupr_map_card() {
	$c = mcupr_contact();
	if ( ! $c['map_on'] || '' === $c['map_q'] ) {
		return;
	}
	?>
	<div class="fmap" data-map data-src="<?php echo esc_url( mcupr_map_embed_url( $c['map_q'] ) ); ?>" data-title="<?php echo esc_attr( mcupr_t( 'map_title' ) . ': ' . mcupr_org_name() ); ?>">
		<div class="fmap-ph">
			<span class="fmap-pin" aria-hidden="true"><?php echo mcupr_icon( 'pin', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<p class="fmap-t"><?php echo esc_html( mcupr_t( 'map_title' ) ); ?></p>
			<button type="button" class="btn" data-map-load><?php mcupr_e( 'map_show' ); ?></button>
			<p class="fmap-n"><?php mcupr_e( 'map_note' ); ?></p>
		</div>
	</div>
	<p class="fmap-links">
		<a href="<?php echo esc_url( mcupr_map_open_url( $c['map_q'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php mcupr_e( 'map_open' ); ?><span class="sr-only"> (<?php echo esc_html( mcupr_t( 'new_tab' ) ); ?>)</span></a>
		<a href="<?php echo esc_url( mcupr_map_directions_url( $c['map_q'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php mcupr_e( 'map_dir' ); ?><span class="sr-only"> (<?php echo esc_html( mcupr_t( 'new_tab' ) ); ?>)</span></a>
	</p>
	<?php
}

/** Address / telephone / e-mail / hours as an icon list (footer). */
function mcupr_contact_list() {
	$c    = mcupr_contact();
	$rows = array();
	if ( '' !== trim( $c['address'] ) ) {
		$rows[] = array( 'pin', mcupr_t( 'address_label' ), nl2br( esc_html( $c['address'] ) ) );
	}
	if ( '' !== $c['tel'] ) {
		$rows[] = array( 'phone', mcupr_t( 'about_tel' ), '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $c['tel'] ) ) . '">' . esc_html( $c['tel'] ) . '</a>' );
	}
	if ( '' !== $c['email'] ) {
		$rows[] = array( 'mail', mcupr_t( 'about_email' ), '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>' );
	}
	if ( '' !== trim( $c['hours'] ) ) {
		$rows[] = array( 'clock', mcupr_t( 'hours_label' ), nl2br( esc_html( $c['hours'] ) ) );
	}
	if ( ! $rows ) {
		return;
	}
	echo '<address class="fcontact">';
	foreach ( $rows as $r ) {
		echo '<div><span class="fc-ic" aria-hidden="true">' . mcupr_icon( $r[0], 18 ) . '</span><div><span class="fc-l">' . esc_html( $r[1] ) . '</span><span class="fc-v">' . $r[2] . '</span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</address>';
}
