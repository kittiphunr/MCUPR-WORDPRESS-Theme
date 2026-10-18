<?php
/**
 * Thai government date format, built into the theme (no plugin needed).
 *
 * When the page language is Thai, every date printed through wp_date() /
 * the_date() / get_the_date() / comment dates uses Thai month and day names and
 * the Buddhist Era year (พ.ศ. = ค.ศ. + 543), e.g. "14 ตุลาคม 2569" or
 * "วันอังคารที่ 14 ตุลาคม พ.ศ. 2569". Machine formats (ISO 8601 "c", "U", "r")
 * and the HTML datetime="" attribute stay in the Gregorian calendar.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_th_months( $short = false ) {
	return $short
		? array( 1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.' )
		: array( 1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม' );
}

function mcupr_th_days( $short = false ) {
	return $short
		? array( 'อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.' )
		: array( 'อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์' );
}

/** A Gregorian year as shown: the Buddhist Era year (+543) when the page is Thai, otherwise as it is; '' for none. */
function mcupr_year_text( $y ) {
	return $y ? (string) ( 'th' === mcupr_ui_lang() ? $y + 543 : $y ) : '';
}

/** Escape literal text for DateTime::format(). */
function mcupr_date_literal( $text ) {
	return preg_replace( '/(.)/u', '\\\\$1', $text );
}

/**
 * Format a timestamp the Thai way. $format uses PHP date() tokens; F, M, l, D, Y, y
 * are replaced by Thai month/day names and Buddhist Era years.
 */
function mcupr_th_date( $format, $timestamp, DateTimeZone $tz ) {
	$dt     = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $tz );
	$months = mcupr_th_months();
	$mshort = mcupr_th_months( true );
	$days   = mcupr_th_days();
	$dshort = mcupr_th_days( true );
	$be     = (int) $dt->format( 'Y' ) + 543;
	$out    = '';
	$len    = strlen( $format );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $format[ $i ];
		if ( '\\' === $c ) { // escaped literal – pass through untouched
			$out .= $c . ( $format[ ++$i ] ?? '' );
			continue;
		}
		switch ( $c ) {
			case 'F':
				$out .= mcupr_date_literal( $months[ (int) $dt->format( 'n' ) ] );
				break;
			case 'M':
				$out .= mcupr_date_literal( $mshort[ (int) $dt->format( 'n' ) ] );
				break;
			case 'l':
				$out .= mcupr_date_literal( 'วัน' . $days[ (int) $dt->format( 'w' ) ] );
				break;
			case 'D':
				$out .= mcupr_date_literal( $dshort[ (int) $dt->format( 'w' ) ] );
				break;
			case 'Y':
				$out .= mcupr_date_literal( (string) $be );
				break;
			case 'y':
				$out .= mcupr_date_literal( substr( (string) $be, -2 ) );
				break;
			default:
				$out .= $c;
		}
	}
	return $dt->format( $out );
}

/** wp_date filter: Thai names + พ.ศ. for human-readable formats when the page is Thai. */
function mcupr_filter_wp_date( $date, $format, $timestamp, $timezone ) {
	if ( is_admin() || 'th' !== mcupr_ui_lang() || preg_match( '/^(c|U|r|e|T|P|O)$/', $format ) || false !== strpos( $format, 'Y-m-d\\T' ) ) {
		return $date;
	}
	return mcupr_th_date( $format, $timestamp, $timezone );
}
add_filter( 'wp_date', 'mcupr_filter_wp_date', 10, 4 );

/** Thai official clock style: 09.30 น. */
function mcupr_time_text( $ts ) {
	if ( 'th' === mcupr_ui_lang() ) {
		return wp_date( 'H.i', $ts ) . ' น.';
	}
	return wp_date( 'H:i', $ts );
}

/**
 * Date as text. style: short (14 ตุลาคม 2569) | long (วันอังคารที่ 14 ตุลาคม พ.ศ. 2569).
 * Other languages: localised equivalents.
 */
function mcupr_date_text( $ts, $style = 'short' ) {
	switch ( mcupr_ui_lang() ) {
		case 'th':
			return 'long' === $style
				? wp_date( 'l', $ts ) . 'ที่ ' . wp_date( 'j F', $ts ) . ' พ.ศ. ' . wp_date( 'Y', $ts )
				: wp_date( 'j F Y', $ts );
		case 'zh':
		case 'ja':
			return wp_date( 'Y年n月j日', $ts );
		default:
			return 'long' === $style ? wp_date( 'l, j F Y', $ts ) : wp_date( 'j F Y', $ts );
	}
}

/**
 * A human date (and time) for a wp_date() format such as 'j M Y', 'j F Y H:i', 'D j M' or 'j/n/Y H:i', in the
 * visitor's language. Thai and English print wp_date( $format ) exactly as before (Thai gets its month and day names
 * and the Buddhist year from the wp_date filter above). Chinese and Japanese get 2026年10月14日 (mcupr_date_text),
 * 10月14日 when the format has no year, the weekday (周三 / (水)) when it has one, and the time (mcupr_time_text)
 * when it has one.
 */
function mcupr_date_fmt( $format, $ts = null ) {
	$ts   = null === $ts ? time() : (int) $ts;
	$lang = mcupr_ui_lang();
	if ( 'zh' !== $lang && 'ja' !== $lang ) {
		return wp_date( $format, $ts );
	}
	$plain = preg_replace( '/\\\\./', '', (string) $format ); // escaped characters are text, not tokens
	$out   = false !== strpbrk( $plain, 'Yyo' ) ? mcupr_date_text( $ts ) : wp_date( 'n月j日', $ts );
	if ( false !== strpbrk( $plain, 'Dl' ) ) {
		$w    = (int) wp_date( 'w', $ts );
		$zh   = array( '日', '一', '二', '三', '四', '五', '六' );
		$ja   = array( '日', '月', '火', '水', '木', '金', '土' );
		$out .= 'zh' === $lang ? ' 周' . $zh[ $w ] : '(' . $ja[ $w ] . ')';
	}
	if ( false !== strpbrk( $plain, 'GHgh' ) ) {
		$out .= ' ' . mcupr_time_text( $ts );
	}
	return $out;
}

/** Print a <time> element (datetime attribute stays Gregorian ISO 8601). */
function mcupr_date( $post = null, $style = 'short' ) {
	$post = get_post( $post );
	$ts   = get_post_timestamp( $post );
	printf( '<time datetime="%s">%s</time>', esc_attr( wp_date( 'c', $ts ) ), esc_html( mcupr_date_text( $ts, $style ) ) );
}
