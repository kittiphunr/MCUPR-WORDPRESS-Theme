<?php
/**
 * Home blocks – set in the Customizer (MCUPR: Home page sections), also available as shortcodes. No plugin needed:
 *
 *  - Audience cards   (3 big cards: "for applicants / current students / education"; the first one can stand out)
 *  - Figures strip    (big numbers with dividers; {news} {staff} {units} {events} {works} {videos} {albums} count themselves)
 *  - Link cards       (round-icon service cards that note "opens in a new tab", or compact icon links + "see all")
 *  - About block      (title, text, button + four cards)
 *  - Weather bar      (Open-Meteo, fetched by the server and cached – visitors' addresses never reach Open-Meteo)
 *
 * Customizer → MCUPR: Home page sections has one section per block, with a box for every field (no syntax to learn).
 * A block with nothing filled in is not shown. In a shortcode, items are typed as  icon|title|address ; icon|title|address
 * MCUPR → Home blocks puts ready-made samples into the blocks that are still empty (it never overwrites what is there).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ small helpers */

/** Lines of "a | b | c" → array of arrays (trimmed). $semi: shortcodes use ; between items. */
function mcupr_blk_parse( $text, $min = 1, $semi = false ) {
	$rows = array();
	foreach ( preg_split( $semi ? '/\r?\n|;/' : '/\r?\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$f = array_map( 'trim', explode( '|', $line ) );
		if ( count( $f ) >= $min ) {
			$rows[] = $f;
		}
	}
	return array_slice( $rows, 0, 24 );
}

/** An address typed by a person: a path (/page/), a full address, or nothing. */
function mcupr_blk_url( $u ) {
	$u = trim( (string) $u );
	if ( '' === $u ) {
		return '';
	}
	if ( 0 === strpos( $u, '//' ) ) {
		return ''; // protocol-relative: could be anywhere
	}
	if ( preg_match( '#^/#', $u ) ) {
		$u = home_url( $u );
	}
	return esc_url_raw( $u, array( 'http', 'https', 'mailto', 'tel' ) );
}

function mcupr_blk_is_external( $url ) {
	$h = wp_parse_url( $url, PHP_URL_HOST );
	return $h && wp_parse_url( home_url(), PHP_URL_HOST ) !== $h;
}

/** An icon name that exists, else "link". */
function mcupr_blk_icon( $name ) {
	$name = sanitize_key( $name );
	return array_key_exists( $name, mcupr_icon_paths() ) ? $name : 'link';
}

/** Number or a self-counting token. */
function mcupr_blk_figure( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^\{([a-z]+)\}$/', $v, $m ) ) {
		$types = array( 'news' => 'post', 'staff' => 'mcupr_person', 'units' => 'mcupr_unit', 'events' => 'mcupr_event', 'works' => 'mcupr_work', 'videos' => 'mcupr_video', 'albums' => 'mcupr_album' );
		if ( ! isset( $types[ $m[1] ] ) || ! post_type_exists( $types[ $m[1] ] ) ) {
			return '';
		}
		return number_format_i18n( mcupr_published_count( $types[ $m[1] ] ) );
	}
	return esc_html( $v );
}

function mcupr_blk_head( $title, $sub = '', $more = '', $more_url = '', $id = '' ) {
	if ( '' === $title && '' === $sub ) {
		return '';
	}
	$out = '<div class="blk-head">';
	$out .= '<div class="min-w-0">' . ( '' !== $title ? '<h2' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="blk-title">' . esc_html( $title ) . '</h2>' : '' ) . ( '' !== $sub ? '<p class="blk-sub">' . esc_html( $sub ) . '</p>' : '' ) . '</div>';
	if ( '' !== $more && '' !== mcupr_blk_url( $more_url ) ) {
		$out .= '<a class="blk-more" href="' . esc_url( mcupr_blk_url( $more_url ) ) . '">' . esc_html( $more ) . ' <span aria-hidden="true">→</span></a>';
	}
	return $out . '</div>';
}

/* ------------------------------------------------------------------ the blocks */

/** icon | eyebrow | title | description | address – the first card stands out. */
function mcupr_blk_audience( $items, $lead = true ) {
	$out = '';
	foreach ( $items as $i => $f ) {
		if ( count( $f ) < 3 ) {
			continue;
		}
		$url   = mcupr_blk_url( isset( $f[4] ) ? $f[4] : '' );
		$tag   = $url ? 'a' : 'div';
		$attrs = $url ? ' href="' . esc_url( $url ) . '"' . ( mcupr_blk_is_external( $url ) ? ' target="_blank" rel="noopener"' : '' ) : '';
		$out  .= '<' . $tag . ' class="aud-card' . ( $lead && 0 === $i ? ' is-lead' : '' ) . '"' . $attrs . '><span class="aud-ic" aria-hidden="true">' . mcupr_icon( mcupr_blk_icon( $f[0] ), 26 ) . '</span><span class="min-w-0"><small>' . esc_html( $f[1] ) . '</small><b>' . esc_html( $f[2] ) . '</b>' . ( isset( $f[3] ) && '' !== $f[3] ? '<span class="aud-d">' . esc_html( $f[3] ) . '</span>' : '' ) . '</span></' . $tag . '>';
	}
	return $out ? '<div class="aud">' . $out . '</div>' : '';
}

/** number | label */
function mcupr_blk_figures( $items ) {
	$out = '';
	foreach ( $items as $f ) {
		if ( count( $f ) < 2 ) {
			continue;
		}
		$n = mcupr_blk_figure( $f[0] );
		if ( '' === $n ) {
			continue;
		}
		$out .= '<div class="fig"><b>' . $n . '</b><span>' . esc_html( $f[1] ) . '</span></div>';
	}
	return $out ? '<div class="figs">' . $out . '</div>' : '';
}

/** icon | title | address – style "service" (round icon on top) or "links" (compact). */
function mcupr_blk_cards( $items, $style = 'service' ) {
	$out = '';
	foreach ( $items as $f ) {
		if ( count( $f ) < 3 ) {
			continue;
		}
		$url = mcupr_blk_url( $f[2] );
		if ( '' === $url ) {
			continue;
		}
		$ext  = mcupr_blk_is_external( $url );
		$out .= '<a class="lc" href="' . esc_url( $url ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '><span class="lc-ic" aria-hidden="true">' . mcupr_icon( mcupr_blk_icon( $f[0] ), 'links' === $style ? 30 : 26 ) . '</span><span class="lc-t">' . esc_html( $f[1] ) . '</span>' . ( $ext && 'links' !== $style ? '<span class="lc-x">' . mcupr_icon( 'external', 15 ) . ' ' . esc_html( mcupr_t( 'blk_new_tab' ) ) . '</span>' : '' ) . ( $ext ? '<span class="sr-only"> (' . esc_html( mcupr_t( 'blk_new_tab' ) ) . ')</span>' : '' ) . '</a>';
	}
	return $out ? '<div class="lcs lcs-' . ( 'links' === $style ? 'links' : 'service' ) . '">' . $out . '</div>' : '';
}

/** Title, text, button on the left; icon | title | description | address cards on the right. */
function mcupr_blk_about( $a, $items ) {
	$cards = '';
	foreach ( $items as $f ) {
		if ( count( $f ) < 2 ) {
			continue;
		}
		$url   = mcupr_blk_url( isset( $f[3] ) ? $f[3] : '' );
		$tag   = $url ? 'a' : 'div';
		$cards .= '<' . $tag . ' class="ab-card"' . ( $url ? ' href="' . esc_url( $url ) . '"' : '' ) . '><span class="ab-ic" aria-hidden="true">' . mcupr_icon( mcupr_blk_icon( $f[0] ), 30 ) . '</span><b>' . esc_html( $f[1] ) . '</b>' . ( isset( $f[2] ) && '' !== $f[2] ? '<span>' . esc_html( $f[2] ) . '</span>' : '' ) . '</' . $tag . '>';
	}
	$btn = '' !== (string) $a['button'] && '' !== mcupr_blk_url( $a['url'] ) ? '<a class="btn" href="' . esc_url( mcupr_blk_url( $a['url'] ) ) . '">' . esc_html( $a['button'] ) . ' <span aria-hidden="true">→</span></a>' : '';
	if ( '' === (string) $a['title'] . (string) $a['text'] && '' === $cards ) {
		return '';
	}
	return '<div class="ab"><div class="ab-text">' . ( '' !== (string) $a['title'] ? '<h2' . ( ! empty( $a['id'] ) ? ' id="' . esc_attr( $a['id'] ) . '"' : '' ) . ' class="blk-title">' . esc_html( $a['title'] ) . '</h2>' : '' ) . ( '' !== (string) $a['text'] ? '<p>' . nl2br( esc_html( $a['text'] ) ) . '</p>' : '' ) . $btn . '</div>' . ( $cards ? '<div class="ab-cards">' . $cards . '</div>' : '' ) . '</div>';
}

/* ------------------------------------------------------------------ weather (Open-Meteo, server side, cached) */

/** Words for the WMO weather code. */
function mcupr_wx_word( $code ) {
	$code = (int) $code;
	if ( 0 === $code ) {
		$k = 'wx_clear';
	} elseif ( $code <= 3 ) {
		$k = 'wx_cloudy';
	} elseif ( $code <= 48 ) {
		$k = 'wx_fog';
	} elseif ( $code <= 57 ) {
		$k = 'wx_drizzle';
	} elseif ( $code <= 67 ) {
		$k = 'wx_rain';
	} elseif ( $code <= 77 ) {
		$k = 'wx_snow';
	} elseif ( $code <= 82 ) {
		$k = 'wx_showers';
	} elseif ( $code <= 86 ) {
		$k = 'wx_snow';
	} else {
		$k = 'wx_storm';
	}
	return mcupr_t( $k );
}

/** array( now => array, today => array, tomorrow => array, time => int ) or null. Cached 30 minutes (5 when it failed). */
function mcupr_wx_data( $lat, $lon ) {
	$lat = round( max( -90, min( 90, (float) $lat ) ), 3 );
	$lon = round( max( -180, min( 180, (float) $lon ) ), 3 );
	$key = 'mcupr_wx_' . md5( $lat . ',' . $lon );
	$c   = get_transient( $key );
	if ( is_array( $c ) ) {
		return empty( $c['fail'] ) ? $c : null;
	}
	$url = add_query_arg(
		array(
			'latitude'      => $lat,
			'longitude'     => $lon,
			'current'       => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
			'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum',
			'timezone'      => 'auto',
			'forecast_days' => 2,
		),
		'https://api.open-meteo.com/v1/forecast'
	);
	$r = wp_remote_get( $url, array( 'timeout' => 4, 'redirection' => 1, 'user-agent' => 'MCUPR-theme/' . MCUPR_VERSION ) );
	$j = is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $j ) || empty( $j['current'] ) || empty( $j['daily']['time'][0] ) ) {
		set_transient( $key, array( 'fail' => 1 ), 5 * MINUTE_IN_SECONDS );
		return null;
	}
	$d    = $j['daily'];
	$day  = static function ( $i ) use ( $d ) {
		return isset( $d['time'][ $i ] ) ? array( 'date' => (string) $d['time'][ $i ], 'code' => (int) $d['weather_code'][ $i ], 'max' => (float) $d['temperature_2m_max'][ $i ], 'min' => (float) $d['temperature_2m_min'][ $i ], 'rain' => (float) $d['precipitation_sum'][ $i ] ) : null;
	};
	$out = array(
		'now'      => array( 'code' => (int) $j['current']['weather_code'], 'temp' => (float) $j['current']['temperature_2m'], 'hum' => (int) $j['current']['relative_humidity_2m'], 'wind' => (float) $j['current']['wind_speed_10m'] ),
		'today'    => $day( 0 ),
		'tomorrow' => $day( 1 ),
		'time'     => time(),
	);
	set_transient( $key, $out, 30 * MINUTE_IN_SECONDS );
	return $out;
}

function mcupr_wx_num( $n ) {
	return number_format_i18n( $n, 1 );
}

function mcupr_blk_weather( $lat, $lon, $place = '' ) {
	if ( '' === trim( (string) $lat ) || '' === trim( (string) $lon ) ) {
		return '';
	}
	$w = mcupr_wx_data( $lat, $lon );
	if ( ! $w ) {
		return '';
	}
	$bits = array(
		esc_html( mcupr_t( 'wx_humidity' ) . ' ' . $w['now']['hum'] . '%' ),
		esc_html( mcupr_t( 'wx_wind' ) . ' ' . mcupr_wx_num( $w['now']['wind'] ) . ' ' . mcupr_t( 'wx_kmh' ) ),
	);
	if ( $w['today'] ) {
		$bits[] = esc_html( mcupr_t( 'wx_today' ) . ' ' . mcupr_wx_num( $w['today']['min'] ) . '–' . mcupr_wx_num( $w['today']['max'] ) . '°C' );
		$bits[] = esc_html( mcupr_t( 'wx_rain_today' ) . ' ' . mcupr_wx_num( $w['today']['rain'] ) . ' ' . mcupr_t( 'wx_mm' ) );
	}
	if ( $w['tomorrow'] ) {
		$bits[] = esc_html( mcupr_date_fmt( 'D j M', strtotime( $w['tomorrow']['date'] . ' 12:00' ) ) . ': ' . mcupr_wx_word( $w['tomorrow']['code'] ) . ' · ' . mcupr_wx_num( $w['tomorrow']['min'] ) . '–' . mcupr_wx_num( $w['tomorrow']['max'] ) . '°C · ' . mcupr_t( 'wx_rain_short' ) . ' ' . mcupr_wx_num( $w['tomorrow']['rain'] ) . ' ' . mcupr_t( 'wx_mm' ) );
	}
	$list  = '<ul class="wx-list">' . implode( '', array_map( static function ( $b ) { return '<li>' . $b . '</li>'; }, $bits ) ) . '</ul>';
	$latf  = round( (float) $lat, 3 );
	$lonf  = round( (float) $lon, 3 );
	$windy = 'https://www.windy.com/' . $latf . '/' . $lonf . '?' . $latf . ',' . $lonf . ',8';
	$cond  = mcupr_wx_word( $w['now']['code'] ) . ( '' !== $place ? ' · ' . $place : '' );
	return '<div class="wx" data-wx><div class="wx-bar"><span class="wx-pill">' . mcupr_icon( 'cloud', 20 ) . '<b>' . esc_html( $cond ) . ' ' . esc_html( mcupr_wx_num( $w['now']['temp'] ) ) . '°C</b></span>'
		. '<div class="wx-view" role="group" aria-label="' . esc_attr( mcupr_t( 'wx_label' ) ) . '"><div class="wx-track">' . $list . '<div class="wx-copy" aria-hidden="true">' . $list . '</div></div></div>'
		. '<button type="button" class="wx-pause" data-wx-pause aria-pressed="false" aria-label="' . esc_attr( mcupr_t( 'wx_pause' ) ) . '" data-l-pause="' . esc_attr( mcupr_t( 'wx_pause' ) ) . '" data-l-play="' . esc_attr( mcupr_t( 'wx_play' ) ) . '"><span class="wx-i-pause" aria-hidden="true"></span></button>'
		. '<a class="wx-windy" href="' . esc_url( $windy ) . '" target="_blank" rel="noopener">' . mcupr_icon( 'external', 16 ) . ' Windy<span class="sr-only"> (' . esc_html( mcupr_t( 'blk_new_tab' ) ) . ')</span></a></div>'
		. '<p class="wx-src">' . esc_html( mcupr_t( 'wx_source' ) ) . ' · ' . esc_html( mcupr_t( 'wx_updated' ) ) . ' ' . esc_html( mcupr_date_fmt( 'j/n/Y H:i', $w['time'] ) ) . ' · <a href="' . esc_url( $windy ) . '" target="_blank" rel="noopener">' . esc_html( mcupr_t( 'wx_map' ) ) . '</a></p></div>';
}

/* ------------------------------------------------------------------ shortcodes */

add_shortcode(
	'mcupr_audience',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'items' => '', 'lead' => '1' ), $atts, 'mcupr_audience' );
		return mcupr_blk_audience( mcupr_blk_parse( $a['items'], 3, true ), '0' !== (string) $a['lead'] );
	}
);
add_shortcode(
	'mcupr_figures',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'items' => '' ), $atts, 'mcupr_figures' );
		return mcupr_blk_figures( mcupr_blk_parse( $a['items'], 2, true ) );
	}
);
add_shortcode(
	'mcupr_cards',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'items' => '', 'style' => 'service', 'title' => '', 'sub' => '', 'more' => '', 'more_url' => '' ), $atts, 'mcupr_cards' );
		$h = mcupr_blk_cards( mcupr_blk_parse( $a['items'], 3, true ), $a['style'] );
		return '' === $h ? '' : mcupr_blk_head( $a['title'], $a['sub'], $a['more'], $a['more_url'] ) . $h;
	}
);
add_shortcode(
	'mcupr_about',
	static function ( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'title' => '', 'text' => '', 'button' => '', 'url' => '', 'items' => '' ), $atts, 'mcupr_about' );
		if ( '' === $a['text'] && '' !== trim( (string) $content ) ) {
			$a['text'] = wp_strip_all_tags( $content );
		}
		return mcupr_blk_about( $a, mcupr_blk_parse( $a['items'], 2, true ) );
	}
);
add_shortcode(
	'mcupr_weather',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'lat' => '', 'lon' => '', 'place' => '' ), $atts, 'mcupr_weather' );
		return mcupr_blk_weather( $a['lat'], $a['lon'], $a['place'] );
	}
);

/* ------------------------------------------------------------------ the Customizer: what is typed, and how many of each */

/** The blocks: key => array( toggle key, item count, fields ). Field = key => array( label, kind text|icon|url ). */
function mcupr_blk_defs() {
	return array(
		'aud' => array( 3, array( 'icon' => 'icon', 'eyebrow' => 'text', 'title' => 'text', 'desc' => 'text', 'url' => 'url' ) ),
		'fig' => array( 5, array( 'num' => 'text', 'label' => 'text' ) ),
		'svc' => array( 8, array( 'icon' => 'icon', 'title' => 'text', 'url' => 'url' ) ),
		'lnk' => array( 6, array( 'icon' => 'icon', 'title' => 'text', 'url' => 'url' ) ),
		'abt' => array( 4, array( 'icon' => 'icon', 'title' => 'text', 'desc' => 'text', 'url' => 'url' ) ),
	);
}

/** Rows of what was typed for a block, in the order the render functions expect; items without their main field are skipped. */
function mcupr_blk_rows( $key ) {
	$defs = mcupr_blk_defs();
	$rows = array();
	$main = 'fig' === $key ? 'num' : 'title';
	for ( $i = 1; $i <= $defs[ $key ][0]; $i++ ) {
		$r = array();
		foreach ( array_keys( $defs[ $key ][1] ) as $f ) {
			$r[ $f ] = trim( (string) get_theme_mod( 'mcupr_' . $key . '_' . $i . '_' . $f, '' ) );
		}
		if ( '' !== $r[ $main ] ) {
			$rows[] = array_values( $r );
		}
	}
	return $rows;
}

/** The html of a home block by its key: weather | audience | figures | services | links | about. '' when nothing is filled in. */
function mcupr_blk_home( $which ) {
	switch ( $which ) {
		case 'weather':
			return mcupr_blk_weather( get_theme_mod( 'mcupr_wx_lat', '' ), get_theme_mod( 'mcupr_wx_lon', '' ), (string) get_theme_mod( 'mcupr_wx_place', '' ) );
		case 'audience':
			// icon, eyebrow, title, desc, url  →  the same order as the render function
			return mcupr_blk_audience( mcupr_blk_rows( 'aud' ), true );
		case 'figures':
			$rows = array();
			foreach ( mcupr_blk_rows( 'fig' ) as $r ) {
				$rows[] = array( $r[0], '' !== $r[1] ? $r[1] : '' );
			}
			return mcupr_blk_figures( $rows );
		case 'services':
		case 'links':
			$k    = 'services' === $which ? 'svc' : 'lnk';
			$cards = mcupr_blk_cards( mcupr_blk_rows( $k ), 'services' === $which ? 'service' : 'links' );
			if ( '' === $cards ) {
				return '';
			}
			return mcupr_blk_head( (string) get_theme_mod( 'mcupr_' . $k . '_title', '' ), 'services' === $which ? (string) get_theme_mod( 'mcupr_svc_sub', '' ) : '', (string) get_theme_mod( 'mcupr_' . $k . '_more', '' ), (string) get_theme_mod( 'mcupr_' . $k . '_more_url', '' ), 'blk-' . $k . '-t' ) . $cards;
		case 'about':
			return mcupr_blk_about(
				array( 'title' => (string) get_theme_mod( 'mcupr_abt_title', '' ), 'text' => (string) get_theme_mod( 'mcupr_abt_text', '' ), 'button' => (string) get_theme_mod( 'mcupr_abt_button', '' ), 'url' => (string) get_theme_mod( 'mcupr_abt_url', '' ), 'id' => 'blk-abt-t' ),
				mcupr_blk_rows( 'abt' )
			);
	}
	return '';
}

/** A block as a section of the home page (nothing when it is switched off or empty). $mt = top margin class. */
function mcupr_blk_section( $which, $toggle, $mt = 'mt-8' ) {
	if ( ! mcupr_home_on( $toggle, true ) ) {
		return '';
	}
	$h = mcupr_blk_home( $which );
	if ( '' === $h ) {
		return '';
	}
	$lab = preg_match( '/\bid="(blk-[a-z]+-t)"/', $h, $m ) ? ' aria-labelledby="' . esc_attr( $m[1] ) . '"' : '';
	return '<section class="wrap ' . esc_attr( $mt ) . ' blk blk-' . esc_attr( $which ) . '"' . $lab . ' data-reveal>' . $h . '</section>';
}

/** The icons that can be chosen: name => name. */
function mcupr_blk_icon_choices() {
	$skip = array( 'layout-top', 'layout-side', 'rows', 'contrast', 'underline', 'type', 'spacing', 'pointer', 'blossom', 'sliders', 'chevron', 'arrow', 'panel', 'eye', 'external' );
	$out  = array( '' => __( '— no icon —', 'mcupr' ) );
	foreach ( array_keys( mcupr_icon_paths() ) as $n ) {
		if ( ! in_array( $n, $skip, true ) ) {
			$out[ $n ] = $n;
		}
	}
	return $out;
}

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		// the panel that holds the home page sections (the show/hide boxes are its first section)
		$wp_customize->add_panel( 'mcupr_home_panel', array( 'title' => __( 'MCUPR: Home page sections', 'mcupr' ), 'description' => __( 'Show or hide each part of the home page and fill in the blocks. A block with nothing typed in is not shown.', 'mcupr' ), 'priority' => 33 ) );
		if ( $wp_customize->get_section( 'mcupr_home_sections' ) ) {
			$wp_customize->get_section( 'mcupr_home_sections' )->panel = 'mcupr_home_panel';
			$wp_customize->get_section( 'mcupr_home_sections' )->title    = __( 'Show or hide the sections', 'mcupr' );
			$wp_customize->get_section( 'mcupr_home_sections' )->priority = 10;
		}
		if ( $wp_customize->get_section( 'mcupr_numbers' ) ) {
			$wp_customize->get_section( 'mcupr_numbers' )->panel    = 'mcupr_home_panel';
			$wp_customize->get_section( 'mcupr_numbers' )->priority = 15;
		}
		$icons = mcupr_blk_icon_choices();
		$text  = static function ( $id, $label, $section, $extra = array() ) use ( $wp_customize ) {
			$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
			$wp_customize->add_control( $id, array_merge( array( 'section' => $section, 'label' => $label, 'type' => 'text' ), $extra ) );
		};
		$area  = static function ( $id, $label, $section ) use ( $wp_customize ) {
			$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
			$wp_customize->add_control( $id, array( 'section' => $section, 'label' => $label, 'type' => 'textarea' ) );
		};
		$icon  = static function ( $id, $label, $section ) use ( $wp_customize, $icons ) {
			$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => static function ( $v ) use ( $icons ) { return array_key_exists( (string) $v, $icons ) ? (string) $v : ''; } ) );
			$wp_customize->add_control( $id, array( 'section' => $section, 'label' => $label, 'type' => 'select', 'choices' => $icons ) );
		};
		$url   = static function ( $id, $label, $section ) use ( $wp_customize ) {
			$wp_customize->add_setting( $id, array( 'default' => '', 'sanitize_callback' => 'mcupr_blk_clean_url_setting' ) );
			$wp_customize->add_control( $id, array( 'section' => $section, 'label' => $label, 'type' => 'text', 'input_attrs' => array( 'placeholder' => '/page/  or  https://…' ) ) );
		};
		$sec   = static function ( $id, $title, $desc, $prio ) use ( $wp_customize ) {
			$wp_customize->add_section( $id, array( 'panel' => 'mcupr_home_panel', 'title' => $title, 'description' => $desc, 'priority' => $prio ) );
		};
		$field = static function ( $kind, $id, $label, $section ) use ( $text, $icon, $url ) {
			if ( 'icon' === $kind ) { $icon( $id, $label, $section ); } elseif ( 'url' === $kind ) { $url( $id, $label, $section ); } else { $text( $id, $label, $section ); }
		};

		// ---- weather
		$sec( 'mcupr_blk_wx', __( 'Weather bar', 'mcupr' ), __( 'Today’s weather from Open-Meteo (free, no key). The server asks Open-Meteo once every 30 minutes; visitors’ addresses are not sent to anyone. Nothing is shown until the latitude and longitude are filled in.', 'mcupr' ), 20 );
		$text( 'mcupr_wx_lat', __( 'Latitude (example: 13.690)', 'mcupr' ), 'mcupr_blk_wx', array( 'input_attrs' => array( 'inputmode' => 'decimal' ) ) );
		$text( 'mcupr_wx_lon', __( 'Longitude (example: 101.076)', 'mcupr' ), 'mcupr_blk_wx', array( 'input_attrs' => array( 'inputmode' => 'decimal' ) ) );
		$text( 'mcupr_wx_place', __( 'Place name (optional)', 'mcupr' ), 'mcupr_blk_wx' );

		// ---- audience cards
		$sec( 'mcupr_blk_aud', __( 'Audience cards', 'mcupr' ), __( 'Big cards under the hero for visitor groups (applicants, current students…). The first card stands out. A card without a title is left out.', 'mcupr' ), 21 );
		$labels = array( 'icon' => __( 'Card %d: icon', 'mcupr' ), 'eyebrow' => __( 'Card %d: small text above the title', 'mcupr' ), 'title' => __( 'Card %d: title', 'mcupr' ), 'desc' => __( 'Card %d: description', 'mcupr' ), 'url' => __( 'Card %d: address', 'mcupr' ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			foreach ( mcupr_blk_defs()['aud'][1] as $f => $kind ) {
				$field( $kind, 'mcupr_aud_' . $i . '_' . $f, sprintf( $labels[ $f ], $i ), 'mcupr_blk_aud' );
			}
		}

		// ---- figures
		$sec( 'mcupr_blk_fig', __( 'Figures strip', 'mcupr' ), __( 'Big numbers in a row. Type a number, or one of {news} {staff} {units} {events} {works} {videos} {albums} to count by itself. A figure without a number is left out. (The students / teachers / faculties block is in “Key numbers”.)', 'mcupr' ), 22 );
		for ( $i = 1; $i <= 5; $i++ ) {
			$text( 'mcupr_fig_' . $i . '_num', sprintf( /* translators: %d: figure number */ __( 'Figure %d: number', 'mcupr' ), $i ), 'mcupr_blk_fig' );
			$text( 'mcupr_fig_' . $i . '_label', sprintf( /* translators: %d: figure number */ __( 'Figure %d: label', 'mcupr' ), $i ), 'mcupr_blk_fig' );
		}

		// ---- service cards and links
		foreach ( array( 'svc' => array( 'mcupr_blk_svc', __( 'Service cards', 'mcupr' ), __( 'Round-icon cards that lead to systems or pages. Outside links open in a new tab and say so. A card without a title is left out.', 'mcupr' ), 23, 8 ), 'lnk' => array( 'mcupr_blk_lnk', __( 'Education links', 'mcupr' ), __( 'A row of compact icon links with a heading and a “see all” link. A link without a title is left out.', 'mcupr' ), 24, 6 ) ) as $k => $d ) {
			$sec( $d[0], $d[1], $d[2], $d[3] );
			$text( 'mcupr_' . $k . '_title', __( 'Heading', 'mcupr' ), $d[0] );
			if ( 'svc' === $k ) {
				$text( 'mcupr_svc_sub', __( 'Line under the heading', 'mcupr' ), $d[0] );
			}
			$text( 'mcupr_' . $k . '_more', __( '“See all” text', 'mcupr' ), $d[0] );
			$url( 'mcupr_' . $k . '_more_url', __( '“See all” address', 'mcupr' ), $d[0] );
			for ( $i = 1; $i <= $d[4]; $i++ ) {
				$icon( 'mcupr_' . $k . '_' . $i . '_icon', sprintf( /* translators: %d: item number */ __( 'Item %d: icon', 'mcupr' ), $i ), $d[0] );
				$text( 'mcupr_' . $k . '_' . $i . '_title', sprintf( /* translators: %d: item number */ __( 'Item %d: title', 'mcupr' ), $i ), $d[0] );
				$url( 'mcupr_' . $k . '_' . $i . '_url', sprintf( /* translators: %d: item number */ __( 'Item %d: address', 'mcupr' ), $i ), $d[0] );
			}
		}

		// ---- about block
		$sec( 'mcupr_blk_abt', __( 'About block', 'mcupr' ), __( 'A title, a short text and a button, with up to four cards beside them. A card without a title is left out.', 'mcupr' ), 25 );
		$text( 'mcupr_abt_title', __( 'Title', 'mcupr' ), 'mcupr_blk_abt' );
		$area( 'mcupr_abt_text', __( 'Text', 'mcupr' ), 'mcupr_blk_abt' );
		$text( 'mcupr_abt_button', __( 'Button text', 'mcupr' ), 'mcupr_blk_abt' );
		$url( 'mcupr_abt_url', __( 'Button address', 'mcupr' ), 'mcupr_blk_abt' );
		for ( $i = 1; $i <= 4; $i++ ) {
			$icon( 'mcupr_abt_' . $i . '_icon', sprintf( /* translators: %d: card number */ __( 'Card %d: icon', 'mcupr' ), $i ), 'mcupr_blk_abt' );
			$text( 'mcupr_abt_' . $i . '_title', sprintf( /* translators: %d: card number */ __( 'Card %d: title', 'mcupr' ), $i ), 'mcupr_blk_abt' );
			$text( 'mcupr_abt_' . $i . '_desc', sprintf( /* translators: %d: card number */ __( 'Card %d: description', 'mcupr' ), $i ), 'mcupr_blk_abt' );
			$url( 'mcupr_abt_' . $i . '_url', sprintf( /* translators: %d: card number */ __( 'Card %d: address', 'mcupr' ), $i ), 'mcupr_blk_abt' );
		}
	},
	20
);

/** An address typed in the Customizer: a path, an http(s) / mailto / tel address, or nothing. */
function mcupr_blk_clean_url_setting( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( 0 === strpos( $v, '//' ) ) {
		return '';
	}
	if ( 0 === strpos( $v, '/' ) ) {
		return '/' . ltrim( preg_replace( '#[^\p{L}\p{M}\p{N}\-_/.%?=&\#]#u', '', $v ), '/' );
	}
	return esc_url_raw( $v, array( 'http', 'https', 'mailto', 'tel' ) );
}

/* ------------------------------------------------------------------ samples: only into blocks that are still empty, only pages that exist */

function mcupr_blk_page_url( $slug ) {
	$p = get_posts( array( 'post_type' => 'page', 'name' => sanitize_title( $slug ), 'post_status' => 'publish', 'numberposts' => 1, 'no_found_rows' => true ) ); // by slug: the page may sit under a parent
	return $p ? wp_make_link_relative( get_permalink( $p[0] ) ) : '';
}

/** Fill the home blocks that are still empty with the theme's starter settings (the same file as MCUPR → Import / export settings). Returns the groups filled. */
function mcupr_blk_install_samples() {
	$rep = mcupr_ss_apply( mcupr_ss_starter(), array( 'aud', 'fig', 'svc', 'lnk', 'abt' ) );
	return $rep['added'];
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Home blocks', 'mcupr' ), __( 'Home blocks', 'mcupr' ), 'edit_theme_options', 'mcupr-blocks', 'mcupr_blk_page' );
	}
);

function mcupr_blk_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$msg = isset( $_GET['filled'] ) ? (int) $_GET['filled'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap"><h1>' . esc_html__( 'Home blocks', 'mcupr' ) . '</h1>';
	if ( $msg >= 0 ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msg ? sprintf( /* translators: %d: blocks */ __( 'Samples put into %d blocks. Edit them in the Customizer.', 'mcupr' ), $msg ) : __( 'Nothing added: every block already has content.', 'mcupr' ) ) . '</p></div>';
	}
	$cust = admin_url( 'customize.php?autofocus[panel]=mcupr_home_panel&url=' . rawurlencode( home_url( '/' ) ) );
	echo '<p style="max-width:55rem">' . esc_html__( 'The home page blocks – weather bar, audience cards, figures, service cards, education links and the about block – are set in the Customizer, under “MCUPR: Home page sections”. Each block has its own section with a box for every field. A block with nothing typed in is not shown.', 'mcupr' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( $cust ) . '">' . esc_html__( 'Open the Customizer', 'mcupr' ) . '</a></p>';
	echo '<h2>' . esc_html__( 'Start from samples', 'mcupr' ) . '</h2><p style="max-width:55rem">' . esc_html__( 'One click fills the blocks that are still empty with Thai samples that link to the sample pages (admissions, student portal, programmes, e-services…) when they exist. A block that already has content is never changed.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_blk_samples">';
	wp_nonce_field( 'mcupr_blk_samples', 'mcupr_blk_nonce' );
	echo '<button class="button">' . esc_html__( 'Fill the empty blocks with samples', 'mcupr' ) . '</button></form></div>';
}

add_action(
	'admin_post_mcupr_blk_samples',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_blk_nonce', 'mcupr_blk_samples', 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		wp_safe_redirect( add_query_arg( 'filled', count( mcupr_blk_install_samples() ), admin_url( 'admin.php?page=mcupr-blocks' ) ) );
		exit;
	}
);
