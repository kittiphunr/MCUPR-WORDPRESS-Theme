<?php
/**
 * Home blocks as widgets (and shortcodes) – no plugin needed:
 *
 *  - Audience cards   (3 big cards: "for applicants / current students / education"; the first one can stand out)
 *  - Figures strip    (big numbers with dividers; {news} {staff} {units} {events} {works} {videos} {albums} count themselves)
 *  - Link cards       (round-icon service cards that note "opens in a new tab", or compact icon links + "see all")
 *  - About block      (title, text, button + four cards)
 *  - Weather bar      (Open-Meteo, fetched by the server and cached – visitors' addresses never reach Open-Meteo)
 *
 * Items are typed one per line, fields separated by |  (in a shortcode, items are separated by ; ).
 * Two widget areas on the front page hold them: "Home – under the hero" and "Home – services".
 * MCUPR → Home blocks adds ready-made samples to the empty areas (never touches an area that has widgets).
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
		$c = wp_count_posts( $types[ $m[1] ] );
		return number_format_i18n( isset( $c->publish ) ? (int) $c->publish : 0 );
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
		$bits[] = esc_html( wp_date( 'D j M', strtotime( $w['tomorrow']['date'] . ' 12:00' ) ) . ': ' . mcupr_wx_word( $w['tomorrow']['code'] ) . ' · ' . mcupr_wx_num( $w['tomorrow']['min'] ) . '–' . mcupr_wx_num( $w['tomorrow']['max'] ) . '°C · ' . mcupr_t( 'wx_rain_short' ) . ' ' . mcupr_wx_num( $w['tomorrow']['rain'] ) . ' ' . mcupr_t( 'wx_mm' ) );
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
		. '<p class="wx-src">' . esc_html( mcupr_t( 'wx_source' ) ) . ' · ' . esc_html( mcupr_t( 'wx_updated' ) ) . ' ' . esc_html( wp_date( 'j/n/Y H:i', $w['time'] ) ) . ' · <a href="' . esc_url( $windy ) . '" target="_blank" rel="noopener">' . esc_html( mcupr_t( 'wx_map' ) ) . '</a></p></div>';
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

/* ------------------------------------------------------------------ widgets */

/** One class for all the block widgets; a subclass names its fields and how to draw them. */
abstract class MCUPR_Block_Widget extends WP_Widget {
	/** @return array field key => array( label, type text|textarea|url|select|checkbox, help, default, choices ) */
	abstract protected function fields();

	abstract protected function draw( $v );

	public function widget( $args, $instance ) {
		$v                        = $this->values( $instance );
		$GLOBALS['mcupr_blk_wid'] = isset( $args['widget_id'] ) ? $args['widget_id'] : '';
		$html                     = $this->draw( $v );
		if ( '' === $html ) {
			return;
		}
		echo '<section' . ( isset( $args['widget_id'] ) ? ' id="' . esc_attr( $args['widget_id'] ) . '"' : '' ) . ' class="blk blk-' . esc_attr( $this->id_base ) . '"' . ( isset( $args['widget_id'] ) && false !== strpos( $html, 'id="' . $args['widget_id'] . '-t"' ) ? ' aria-labelledby="' . esc_attr( $args['widget_id'] ) . '-t"' : '' ) . '>' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	protected function values( $instance ) {
		$v = array();
		foreach ( $this->fields() as $k => $f ) {
			$v[ $k ] = isset( $instance[ $k ] ) ? $instance[ $k ] : ( isset( $f[3] ) ? $f[3] : '' );
		}
		return $v;
	}

	public function form( $instance ) {
		$v = $this->values( $instance );
		foreach ( $this->fields() as $k => $f ) {
			$id   = $this->get_field_id( $k );
			$name = $this->get_field_name( $k );
			echo '<p><label for="' . esc_attr( $id ) . '">' . esc_html( $f[0] ) . '</label>';
			if ( 'textarea' === $f[1] ) {
				echo '<textarea class="widefat" rows="6" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $v[ $k ] ) . '</textarea>';
			} elseif ( 'select' === $f[1] ) {
				echo '<select class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $f[4] as $val => $lab ) {
					echo '<option value="' . esc_attr( $val ) . '"' . selected( (string) $v[ $k ], (string) $val, false ) . '>' . esc_html( $lab ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'checkbox' === $f[1] ) {
				echo '<br><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $v[ $k ] ), true, false ) . '>';
			} else {
				echo '<input class="widefat" type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v[ $k ] ) . '">';
			}
			if ( ! empty( $f[2] ) ) {
				echo '<br><span class="description">' . esc_html( $f[2] ) . '</span>';
			}
			echo '</p>';
		}
	}

	public function update( $new, $old ) {
		$out = array();
		foreach ( $this->fields() as $k => $f ) {
			$val = isset( $new[ $k ] ) ? wp_unslash( $new[ $k ] ) : '';
			if ( 'textarea' === $f[1] ) {
				$out[ $k ] = sanitize_textarea_field( $val );
			} elseif ( 'checkbox' === $f[1] ) {
				$out[ $k ] = ! empty( $new[ $k ] ) ? 1 : 0;
			} elseif ( 'select' === $f[1] ) {
				$out[ $k ] = array_key_exists( $val, $f[4] ) ? $val : (string) $f[3];
			} else {
				$out[ $k ] = sanitize_text_field( $val );
			}
		}
		return $out;
	}

	/** The title with its id (so the section is labelled). */
	protected function title_html( $v, $sub = '', $more = '', $more_url = '' ) {
		global $mcupr_blk_wid;
		return mcupr_blk_head( $v['title'], $sub, $more, $more_url, $mcupr_blk_wid ? $mcupr_blk_wid . '-t' : '' );
	}
}

class MCUPR_Widget_Audience extends MCUPR_Block_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_audience', __( 'MCUPR: Audience cards', 'mcupr' ), array( 'description' => __( 'Big cards for visitor groups (applicants, current students…). The first card stands out.', 'mcupr' ) ) );
	}

	protected function fields() {
		return array(
			'items' => array( __( 'Cards – one per line: icon | small text | title | description | address', 'mcupr' ), 'textarea', __( 'Example: edit | For applicants | Admissions | Dates, programmes and how to apply | /admissions/ . Icons: edit, user, book, cap, bank, calendar, mail, news, file, pin, phone, award, shield, heart, star, globe…', 'mcupr' ) ),
			'lead'  => array( __( 'First card stands out', 'mcupr' ), 'checkbox', '', 1 ),
		);
	}

	protected function draw( $v ) {
		return mcupr_blk_audience( mcupr_blk_parse( $v['items'], 3 ), ! empty( $v['lead'] ) );
	}
}

class MCUPR_Widget_Figures extends MCUPR_Block_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_figures', __( 'MCUPR: Figures strip', 'mcupr' ), array( 'description' => __( 'Big numbers in a row. Some count themselves: {news} {staff} {units} {events} {works} {videos} {albums}.', 'mcupr' ) ) );
	}

	protected function fields() {
		return array( 'items' => array( __( 'Figures – one per line: number | label', 'mcupr' ), 'textarea', __( 'Example: 13 | Faculties and units   or   {news} | News and announcements', 'mcupr' ) ) );
	}

	protected function draw( $v ) {
		return mcupr_blk_figures( mcupr_blk_parse( $v['items'], 2 ) );
	}
}

class MCUPR_Widget_Cards extends MCUPR_Block_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_cards', __( 'MCUPR: Link cards', 'mcupr' ), array( 'description' => __( 'Icon cards that lead to systems or pages, with a heading and a “see all” link. Outside links open in a new tab and say so.', 'mcupr' ) ) );
	}

	protected function fields() {
		return array(
			'title'    => array( __( 'Heading', 'mcupr' ), 'text' ),
			'sub'      => array( __( 'Line under the heading', 'mcupr' ), 'text' ),
			'more'     => array( __( '“See all” text', 'mcupr' ), 'text' ),
			'more_url' => array( __( '“See all” address', 'mcupr' ), 'text' ),
			'style'    => array( __( 'Look', 'mcupr' ), 'select', '', 'service', array( 'service' => __( 'Service cards (round icon on top)', 'mcupr' ), 'links' => __( 'Compact icon links', 'mcupr' ) ) ),
			'items'    => array( __( 'Cards – one per line: icon | title | address', 'mcupr' ), 'textarea', __( 'Example: laptop | e-Learning | https://elearning.example.org', 'mcupr' ) ),
		);
	}

	protected function draw( $v ) {
		$h = mcupr_blk_cards( mcupr_blk_parse( $v['items'], 3 ), $v['style'] );
		return '' === $h ? '' : $this->title_html( $v, $v['sub'], $v['more'], $v['more_url'] ) . $h;
	}
}

class MCUPR_Widget_About extends MCUPR_Block_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_about', __( 'MCUPR: About block', 'mcupr' ), array( 'description' => __( 'A title, a short text and a button, with up to four cards beside them.', 'mcupr' ) ) );
	}

	protected function fields() {
		return array(
			'title'  => array( __( 'Title', 'mcupr' ), 'text' ),
			'text'   => array( __( 'Text', 'mcupr' ), 'textarea' ),
			'button' => array( __( 'Button text', 'mcupr' ), 'text' ),
			'url'    => array( __( 'Button address', 'mcupr' ), 'text' ),
			'items'  => array( __( 'Cards – one per line: icon | title | description | address', 'mcupr' ), 'textarea' ),
		);
	}

	protected function draw( $v ) {
		global $mcupr_blk_wid;
		$v['id'] = $mcupr_blk_wid ? $mcupr_blk_wid . '-t' : '';
		return mcupr_blk_about( $v, array_slice( mcupr_blk_parse( $v['items'], 2 ), 0, 4 ) );
	}
}

class MCUPR_Widget_Weather extends MCUPR_Block_Widget {
	public function __construct() {
		parent::__construct( 'mcupr_weather', __( 'MCUPR: Weather bar', 'mcupr' ), array( 'description' => __( 'Today’s weather from Open-Meteo (free, no key). The server asks Open-Meteo once every 30 minutes; visitors’ addresses are not sent to anyone.', 'mcupr' ) ) );
	}

	protected function fields() {
		return array(
			'lat'   => array( __( 'Latitude', 'mcupr' ), 'text', __( 'Example: 13.690 (nothing is shown until latitude and longitude are filled in)', 'mcupr' ) ),
			'lon'   => array( __( 'Longitude', 'mcupr' ), 'text', __( 'Example: 101.076', 'mcupr' ) ),
			'title' => array( __( 'Place name (optional)', 'mcupr' ), 'text' ),
		);
	}

	protected function draw( $v ) {
		return mcupr_blk_weather( $v['lat'], $v['lon'], $v['title'] );
	}
}

add_action(
	'widgets_init',
	static function () {
		foreach ( array( 'MCUPR_Widget_Audience', 'MCUPR_Widget_Figures', 'MCUPR_Widget_Cards', 'MCUPR_Widget_About', 'MCUPR_Widget_Weather' ) as $c ) {
			register_widget( $c );
		}
	}
);

/* ------------------------------------------------------------------ samples: only into empty areas, only pages that exist */

function mcupr_blk_page_url( $slug ) {
	$p = get_posts( array( 'post_type' => 'page', 'name' => sanitize_title( $slug ), 'post_status' => 'publish', 'numberposts' => 1, 'no_found_rows' => true ) ); // by slug: the page may sit under a parent
	return $p ? wp_make_link_relative( get_permalink( $p[0] ) ) : '';
}

/** Lines whose address is a page of this site that does not exist are dropped. */
function mcupr_blk_lines( $rows, $url_at ) {
	$out = array();
	foreach ( $rows as $r ) {
		$slug = $r[ $url_at ];
		if ( 0 === strpos( $slug, 'page:' ) ) {
			$u = mcupr_blk_page_url( substr( $slug, 5 ) );
			if ( '' === $u ) {
				continue;
			}
			$r[ $url_at ] = $u;
		}
		$out[] = implode( ' | ', $r );
	}
	return implode( "\n", $out );
}

/** What the sample button adds: widget id base => array( area, instance ). */
function mcupr_blk_samples() {
	return array(
		'home-top' => array(
			array( 'mcupr_audience', array( 'lead' => 1, 'items' => mcupr_blk_lines( array(
				array( 'edit', 'ผู้สนใจสมัคร', 'รับสมัครนิสิต/นักศึกษา', 'กำหนดการ หลักสูตร และระบบรับสมัคร', 'page:admissions' ),
				array( 'user', 'นิสิตปัจจุบัน', 'พอร์ทัลนิสิต', 'เข้าสู่ระบบ ปฏิทิน และเอกสารดาวน์โหลด', 'page:student-portal' ),
				array( 'book', 'การศึกษา', 'หลักสูตรที่เปิดสอน', 'หลักสูตรที่เปิดสอนและแผนการเรียน', 'page:programs' ),
			), 4 ) ) ),
			array( 'mcupr_figures', array( 'items' => "{news} | ข่าว/ประกาศ\n{staff} | บุคลากร\n{units} | คณะ/หน่วยงาน\n{events} | กิจกรรม" ) ),
		),
		'home-mid' => array(
			array( 'mcupr_cards', array( 'title' => 'บริการและระบบสารสนเทศ', 'sub' => 'แตะรายการเพื่อเข้าใช้ระบบ — ลิงก์ภายนอกจะเปิดแท็บใหม่', 'more' => 'ระบบสารสนเทศทั้งหมด', 'more_url' => mcupr_blk_page_url( 'eservice' ), 'style' => 'service', 'items' => mcupr_blk_lines( array(
				array( 'book', 'ระบบทะเบียน', 'page:registrar' ),
				array( 'laptop', 'e-Learning', 'page:eservice' ),
				array( 'mail', 'อีเมล', 'page:eservice' ),
				array( 'calendar', 'ปฏิทินการศึกษา', 'page:calendar' ),
			), 2 ) ) ),
			array( 'mcupr_cards', array( 'title' => 'การศึกษา', 'more' => 'ดูทั้งหมด', 'more_url' => mcupr_blk_page_url( 'academics' ), 'style' => 'links', 'items' => mcupr_blk_lines( array(
				array( 'book', 'หลักสูตรที่เปิดสอน', 'page:programs' ),
				array( 'rows', 'แผนการเรียน', 'page:study-plan' ),
				array( 'calendar', 'ปฏิทินการศึกษา', 'page:calendar' ),
				array( 'file', 'ระเบียบ/ข้อบังคับ', 'page:laws' ),
			), 2 ) ) ),
			array( 'mcupr_about', array( 'title' => 'เกี่ยวกับ ' . get_bloginfo( 'name' ), 'text' => 'ใส่ข้อความแนะนำหน่วยงานสั้นๆ ที่นี่', 'button' => 'ดูประวัติและวิสัยทัศน์', 'url' => mcupr_blk_page_url( 'vision' ), 'items' => mcupr_blk_lines( array(
				array( 'flag', 'วิสัยทัศน์ / พันธกิจ', 'ทิศทางและเป้าหมายของหน่วยงาน', 'page:vision' ),
				array( 'grid', 'โครงสร้างการบริหาร', 'หน่วยงานและคณะภายใน', 'page:structure' ),
				array( 'heart', 'บริจาคให้หน่วยงาน', 'สนับสนุนการศึกษาและกิจกรรม', 'page:donate' ),
				array( 'award', 'ประกันคุณภาพ', 'การประกันคุณภาพการศึกษา', 'page:quality-assurance' ),
			), 3 ) ) ),
		),
	);
}

/** Add the samples to the areas that are still empty. Returns the number of widgets added. */
function mcupr_blk_install_samples() {
	$sidebars = wp_get_sidebars_widgets();
	$added    = 0;
	foreach ( mcupr_blk_samples() as $area => $widgets ) {
		if ( ! empty( $sidebars[ $area ] ) ) {
			continue; // someone has already put widgets here: leave the area alone
		}
		$ids = array();
		foreach ( $widgets as $w ) {
			$opt  = get_option( 'widget_' . $w[0], array() );
			$opt  = is_array( $opt ) ? $opt : array();
			$n    = 2;
			while ( isset( $opt[ $n ] ) ) {
				$n++;
			}
			$opt[ $n ] = $w[1];
			update_option( 'widget_' . $w[0], $opt );
			$ids[] = $w[0] . '-' . $n;
			$added++;
		}
		$sidebars[ $area ] = $ids;
	}
	if ( $added ) {
		wp_set_sidebars_widgets( $sidebars );
	}
	return $added;
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
	$sidebars = wp_get_sidebars_widgets();
	$msg      = isset( $_GET['added'] ) ? (int) $_GET['added'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="wrap"><h1>' . esc_html__( 'Home blocks', 'mcupr' ) . '</h1>';
	if ( $msg >= 0 ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msg ? sprintf( /* translators: %d: widgets */ __( '%d sample blocks added. Edit them at Appearance → Widgets.', 'mcupr' ), $msg ) : __( 'Nothing added: both areas already have widgets.', 'mcupr' ) ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'The front page has two widget areas for ready-made blocks: “Home – under the hero” (audience cards, figures, weather) and “Home – services” (link cards, about block). Add the blocks at Appearance → Widgets, or put the samples in with one click.', 'mcupr' ) . '</p><ul style="list-style:disc;margin-left:1.5em">';
	foreach ( array( 'MCUPR: Audience cards', 'MCUPR: Figures strip', 'MCUPR: Link cards', 'MCUPR: About block', 'MCUPR: Weather bar' ) as $n ) {
		echo '<li>' . esc_html( $n ) . '</li>';
	}
	echo '</ul><p>';
	foreach ( array( 'home-top' => __( 'Home – under the hero', 'mcupr' ), 'home-mid' => __( 'Home – services', 'mcupr' ) ) as $id => $label ) {
		echo '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( ! empty( $sidebars[ $id ] ) ? sprintf( /* translators: %d: widgets */ __( '%d widgets', 'mcupr' ), count( $sidebars[ $id ] ) ) : __( 'empty', 'mcupr' ) ) . '<br>';
	}
	echo '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_blk_samples">';
	wp_nonce_field( 'mcupr_blk_samples', 'mcupr_blk_nonce' );
	echo '<button class="button button-primary">' . esc_html__( 'Add sample blocks to the empty areas', 'mcupr' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'widgets.php' ) ) . '">' . esc_html__( 'Open Widgets', 'mcupr' ) . '</a></form>';
	echo '<p class="description">' . esc_html__( 'Areas that already have widgets are never changed. Samples link to the sample pages (admissions, student portal, programmes, e-services…) when they exist.', 'mcupr' ) . '</p></div>';
}

add_action(
	'admin_post_mcupr_blk_samples',
	static function () {
		if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['mcupr_blk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_blk_nonce'] ) ), 'mcupr_blk_samples' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		wp_safe_redirect( add_query_arg( 'added', mcupr_blk_install_samples(), admin_url( 'admin.php?page=mcupr-blocks' ) ) );
		exit;
	}
);
