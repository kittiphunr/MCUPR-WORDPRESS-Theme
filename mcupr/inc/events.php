<?php
/**
 * Events calendar – built into the theme (no plugin).
 *
 * - Post type "mcupr_event" (archive /events/) with date / time / place / registration link
 * - Month grid (no JavaScript: previous / next month are plain links, ?ev_m=YYYY-MM) + upcoming list
 * - Shortcodes [mcupr_calendar] and [mcupr_events count="4"]
 * - Add to Google Calendar, .ics download of one event or of all events (?mcupr_ics=ID|all)
 * - schema.org/Event JSON-LD on the event page
 *
 * Post meta (site time zone, sortable strings): _mcupr_ev_start / _mcupr_ev_end "Y-m-d H:i:s",
 * _mcupr_ev_allday 1, _mcupr_ev_place, _mcupr_ev_url.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ post type */

function mcupr_events_register() {
	register_post_type(
		'mcupr_event',
		array(
			'labels'        => array(
				'name'          => __( 'Events', 'mcupr' ),
				'singular_name' => __( 'Event', 'mcupr' ),
				'add_new_item'  => __( 'Add new event', 'mcupr' ),
				'edit_item'     => __( 'Edit event', 'mcupr' ),
				'all_items'     => __( 'All events', 'mcupr' ),
				'menu_name'     => __( 'Events', 'mcupr' ),
			),
			'public'        => true,
			'has_archive'   => 'events',
			'rewrite'       => array( 'slug' => 'events', 'with_front' => false ),
			'menu_icon'     => 'dashicons-calendar-alt',
			'menu_position' => 6,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'  => true,
		)
	);
	// flush the rewrite rules once per theme version (the /events/ URLs exist from the first load after an upload)
	if ( get_option( 'mcupr_rewrite_ver' ) !== MCUPR_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'mcupr_rewrite_ver', MCUPR_VERSION );
	}
}
add_action( 'init', 'mcupr_events_register' );

/** Events carry the same EN / 中文 / 日本語 translation fields as pages and posts. */
add_filter(
	'mcupr_translatable_post_types',
	static function ( $types ) {
		$types[] = 'mcupr_event';
		return $types;
	}
);

/** The events archive page itself needs no post loop. */
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_post_type_archive( 'mcupr_event' ) ) {
			$q->set( 'posts_per_page', 1 );
			$q->set( 'no_found_rows', true );
		}
	}
);

/* ------------------------------------------------------------------ data helpers */

function mcupr_event_ts( $str ) {
	try {
		$d = new DateTimeImmutable( (string) $str, wp_timezone() );
		return $d->getTimestamp();
	} catch ( Exception $e ) {
		return 0;
	}
}

/** array( start, end (timestamps), allday, place, url ) of an event. */
function mcupr_event_info( $post_id ) {
	$start  = mcupr_event_ts( get_post_meta( $post_id, '_mcupr_ev_start', true ) );
	$end    = mcupr_event_ts( get_post_meta( $post_id, '_mcupr_ev_end', true ) );
	$allday = (bool) get_post_meta( $post_id, '_mcupr_ev_allday', true );
	if ( ! $start ) {
		$start = get_post_timestamp( $post_id );
	}
	if ( ! $end || $end < $start ) {
		$end = $start;
	}
	return array(
		'start'  => $start,
		'end'    => $end,
		'allday' => $allday,
		'place'  => (string) get_post_meta( $post_id, '_mcupr_ev_place', true ),
		'url'    => (string) get_post_meta( $post_id, '_mcupr_ev_url', true ),
	);
}

/** Published events that overlap [from, to] (timestamps), soonest first. */
function mcupr_events_between( $from, $to, $limit = -1 ) {
	return get_posts(
		array(
			'post_type'        => 'mcupr_event',
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array(
				'relation'     => 'AND',
				'start_clause' => array( 'key' => '_mcupr_ev_start', 'value' => mcupr_ev_fmt( 'Y-m-d H:i:s', $to ), 'compare' => '<=', 'type' => 'CHAR' ),
				'end_clause'   => array( 'key' => '_mcupr_ev_end', 'value' => mcupr_ev_fmt( 'Y-m-d H:i:s', $from ), 'compare' => '>=', 'type' => 'CHAR' ),
			),
			'orderby'          => array( 'start_clause' => 'ASC' ),
		)
	);
}

function mcupr_events_upcoming( $count = 6 ) {
	return mcupr_events_between( current_time( 'timestamp' ), current_time( 'timestamp' ) + 5 * YEAR_IN_SECONDS, $count ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
}

/* ------------------------------------------------------------------ text helpers (4 languages) */

/**
 * Date formatting with the site's time zone but WITHOUT the theme's Thai (พ.ศ.) wp_date filter: stored values,
 * queries, .ics and structured data must stay Gregorian. Display text adds the Buddhist year itself.
 */
function mcupr_ev_fmt( $format, $ts ) {
	try {
		$d = new DateTimeImmutable( '@' . (int) $ts );
		return $d->setTimezone( wp_timezone() )->format( $format );
	} catch ( Exception $e ) {
		return '';
	}
}

function mcupr_ev_names() {
	return array(
		'th' => array(
			'days'   => array( 'อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์' ),
			'dshort' => array( 'อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.' ),
			'months' => array( 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม' ),
			'mshort' => array( 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.' ),
		),
		'en' => array(
			'days'   => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
			'dshort' => array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
			'months' => array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
			'mshort' => array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ),
		),
		'zh' => array(
			'days'   => array( '星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六' ),
			'dshort' => array( '日', '一', '二', '三', '四', '五', '六' ),
			'months' => array( '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月' ),
			'mshort' => array( '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月' ),
		),
		'ja' => array(
			'days'   => array( '日曜日', '月曜日', '火曜日', '水曜日', '木曜日', '金曜日', '土曜日' ),
			'dshort' => array( '日', '月', '火', '水', '木', '金', '土' ),
			'months' => array( '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月' ),
			'mshort' => array( '1月', '2月', '3月', '4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月' ),
		),
	);
}

function mcupr_ev_n( $key ) {
	$all  = mcupr_ev_names();
	$lang = mcupr_ui_lang();
	return isset( $all[ $lang ] ) ? $all[ $lang ][ $key ] : $all['en'][ $key ];
}

/** Display year: Buddhist era in Thai. */
function mcupr_ev_year( $y ) {
	return 'th' === mcupr_ui_lang() ? $y + 543 : $y;
}

/** "ตุลาคม 2569" / "October 2026" / "2026年10月". */
function mcupr_ev_month_title( $y, $m ) {
	$lang = mcupr_ui_lang();
	$yy   = mcupr_ev_year( $y );
	if ( 'zh' === $lang || 'ja' === $lang ) {
		return $yy . '年' . $m . '月';
	}
	$names = mcupr_ev_n( 'months' );
	return $names[ $m - 1 ] . ' ' . $yy;
}

/** "14 ตุลาคม 2569" for a timestamp (own names, independent of the WordPress locale). */
function mcupr_ev_date( $ts ) {
	$d = array( (int) mcupr_ev_fmt( 'j', $ts ), (int) mcupr_ev_fmt( 'n', $ts ), (int) mcupr_ev_fmt( 'Y', $ts ) );
	$y = mcupr_ev_year( $d[2] );
	switch ( mcupr_ui_lang() ) {
		case 'zh':
		case 'ja':
			return $y . '年' . $d[1] . '月' . $d[0] . '日';
		default:
			$m = mcupr_ev_n( 'months' );
			return $d[0] . ' ' . $m[ $d[1] - 1 ] . ' ' . $y;
	}
}

/** Human readable "when": one day, one day with times, or a range. */
function mcupr_event_when( $i ) {
	$same = mcupr_ev_fmt( 'Y-m-d', $i['start'] ) === mcupr_ev_fmt( 'Y-m-d', $i['end'] );
	if ( $i['allday'] ) {
		return $same ? mcupr_ev_date( $i['start'] ) . ' · ' . mcupr_t( 'all_day' ) : mcupr_ev_date( $i['start'] ) . ' – ' . mcupr_ev_date( $i['end'] );
	}
	if ( $same ) {
		$t = mcupr_time_text( $i['start'] );
		if ( $i['end'] > $i['start'] ) {
			$t = 'th' === mcupr_ui_lang()
				? 'เวลา ' . mcupr_ev_fmt( 'H.i', $i['start'] ) . '–' . mcupr_ev_fmt( 'H.i', $i['end'] ) . ' น.'
				: mcupr_ev_fmt( 'H:i', $i['start'] ) . '–' . mcupr_ev_fmt( 'H:i', $i['end'] );
		} elseif ( 'th' === mcupr_ui_lang() ) {
			$t = 'เวลา ' . $t;
		}
		return mcupr_ev_date( $i['start'] ) . ' · ' . $t;
	}
	return mcupr_ev_date( $i['start'] ) . ' ' . mcupr_time_text( $i['start'] ) . ' – ' . mcupr_ev_date( $i['end'] ) . ' ' . mcupr_time_text( $i['end'] );
}

/* ------------------------------------------------------------------ admin: meta box */

function mcupr_event_box_add() {
	add_meta_box( 'mcupr_event', __( 'Event details', 'mcupr' ), 'mcupr_event_box', 'mcupr_event', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'mcupr_event_box_add' );

function mcupr_event_box( $post ) {
	wp_nonce_field( 'mcupr_event_save', 'mcupr_event_nonce' );
	$s = (string) get_post_meta( $post->ID, '_mcupr_ev_start', true );
	$e = (string) get_post_meta( $post->ID, '_mcupr_ev_end', true );
	if ( '' === $s ) {
		$s = mcupr_ev_fmt( 'Y-m-d 09:00:00', time() );
	}
	$f = static function ( $v, $part ) {
		return 'date' === $part ? substr( $v, 0, 10 ) : substr( $v, 11, 5 );
	};
	$allday = (bool) get_post_meta( $post->ID, '_mcupr_ev_allday', true );
	echo '<p><label><input type="checkbox" name="mcupr_ev[allday]" value="1"' . checked( $allday, true, false ) . '> ' . esc_html__( 'All day', 'mcupr' ) . '</label></p>';
	echo '<p><label>' . esc_html__( 'Starts', 'mcupr' ) . '<br><input type="date" name="mcupr_ev[start_date]" value="' . esc_attr( $f( $s, 'date' ) ) . '" required> <input type="time" name="mcupr_ev[start_time]" value="' . esc_attr( $f( $s, 'time' ) ) . '"></label></p>';
	echo '<p><label>' . esc_html__( 'Ends (optional)', 'mcupr' ) . '<br><input type="date" name="mcupr_ev[end_date]" value="' . esc_attr( $e ? $f( $e, 'date' ) : '' ) . '"> <input type="time" name="mcupr_ev[end_time]" value="' . esc_attr( $e ? $f( $e, 'time' ) : '' ) . '"></label></p>';
	echo '<p><label>' . esc_html__( 'Place', 'mcupr' ) . '<br><input type="text" class="widefat" name="mcupr_ev[place]" value="' . esc_attr( get_post_meta( $post->ID, '_mcupr_ev_place', true ) ) . '"></label></p>';
	echo '<p><label>' . esc_html__( 'Registration / more information link', 'mcupr' ) . '<br><input type="url" class="widefat" name="mcupr_ev[url]" value="' . esc_attr( get_post_meta( $post->ID, '_mcupr_ev_url', true ) ) . '" placeholder="https://"></label></p>';
	echo '<p class="description">' . esc_html__( 'Dates are entered in the Gregorian calendar; the site shows them in the visitor\'s language (Buddhist era in Thai).', 'mcupr' ) . '</p>';
}

function mcupr_event_valid_date( $d ) {
	return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

function mcupr_event_valid_time( $t, $fallback ) {
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ? $t : $fallback;
}

function mcupr_event_save( $post_id ) {
	if ( ! isset( $_POST['mcupr_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_event_nonce'] ) ), 'mcupr_event_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['mcupr_ev'] ) && is_array( $_POST['mcupr_ev'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcupr_ev'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$g  = static function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) ? $in[ $k ] : '';
	};
	$allday = '1' === $g( 'allday' );
	$sd     = mcupr_event_valid_date( $g( 'start_date' ) ) ? $g( 'start_date' ) : mcupr_ev_fmt( 'Y-m-d', time() );
	$st     = $allday ? '00:00' : mcupr_event_valid_time( $g( 'start_time' ), '09:00' );
	$ed     = mcupr_event_valid_date( $g( 'end_date' ) ) ? $g( 'end_date' ) : $sd;
	$et     = $allday ? '23:59' : mcupr_event_valid_time( $g( 'end_time' ), $ed === $sd ? $st : '23:59' );
	$start  = $sd . ' ' . $st . ':00';
	$end    = $ed . ' ' . $et . ( $allday ? ':59' : ':00' );
	if ( $end < $start ) {
		$end = $start;
	}
	update_post_meta( $post_id, '_mcupr_ev_start', $start );
	update_post_meta( $post_id, '_mcupr_ev_end', $end );
	if ( $allday ) {
		update_post_meta( $post_id, '_mcupr_ev_allday', 1 );
	} else {
		delete_post_meta( $post_id, '_mcupr_ev_allday' );
	}
	update_post_meta( $post_id, '_mcupr_ev_place', $g( 'place' ) );
	update_post_meta( $post_id, '_mcupr_ev_url', esc_url_raw( $g( 'url' ) ) );
}
add_action( 'save_post_mcupr_event', 'mcupr_event_save' );

/** Admin list: a "When" column, sorted by the event's date (newest first). */
add_filter(
	'manage_mcupr_event_posts_columns',
	static function ( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			if ( 'date' === $k ) {
				$out['mcupr_when'] = __( 'When', 'mcupr' );
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}
);
add_action(
	'manage_mcupr_event_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'mcupr_when' === $col ) {
			$i = mcupr_event_info( $post_id );
			echo esc_html( mcupr_ev_fmt( 'Y-m-d H:i', $i['start'] ) . ( $i['allday'] ? ' (' . __( 'All day', 'mcupr' ) . ')' : '' ) );
		}
	},
	10,
	2
);
add_filter(
	'manage_edit-mcupr_event_sortable_columns',
	static function ( $cols ) {
		$cols['mcupr_when'] = 'mcupr_when';
		return $cols;
	}
);
add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || 'mcupr_event' !== $q->get( 'post_type' ) ) {
			return;
		}
		$ob = $q->get( 'orderby' );
		if ( ! $ob || 'mcupr_when' === $ob ) {
			$q->set( 'meta_key', '_mcupr_ev_start' );
			$q->set( 'orderby', 'meta_value' );
			if ( ! $q->get( 'order' ) ) {
				$q->set( 'order', 'DESC' );
			}
		}
	}
);

/* ------------------------------------------------------------------ month calendar */

function mcupr_cal_url( $ym ) {
	$base = is_singular() ? get_permalink( get_queried_object_id() ) : ( is_post_type_archive( 'mcupr_event' ) ? get_post_type_archive_link( 'mcupr_event' ) : home_url( '/' ) );
	return add_query_arg( 'ev_m', $ym, $base ) . '#calendar';
}

/** Month shown: ?ev_m=YYYY-MM (validated), else the current month. */
function mcupr_cal_month() {
	$now = array( (int) mcupr_ev_fmt( 'Y', time() ), (int) mcupr_ev_fmt( 'n', time() ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw = isset( $_GET['ev_m'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_m'] ) ) : '';
	if ( preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m ) && (int) $m[1] >= 2000 && (int) $m[1] <= 2100 ) {
		return array( (int) $m[1], (int) $m[2] );
	}
	return $now;
}

/** Month grid + the month's events as a list. */
function mcupr_calendar_html() {
	list( $y, $m ) = mcupr_cal_month();
	$tz       = wp_timezone();
	$first    = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $y, $m ), $tz );
	$days     = (int) $first->format( 't' );
	$offset   = (int) $first->format( 'w' ); // Sunday first
	$from     = $first->getTimestamp();
	$to       = $first->modify( '+' . $days . ' days' )->getTimestamp() - 1;
	$events   = mcupr_events_between( $from, $to );
	$by_day   = array();
	foreach ( $events as $ev ) {
		$i  = mcupr_event_info( $ev->ID );
		$d0 = max( $i['start'], $from );
		$d1 = min( $i['end'], $to );
		for ( $d = (int) mcupr_ev_fmt( 'j', $d0 ); $d <= (int) mcupr_ev_fmt( 'j', $d1 ); $d++ ) {
			$by_day[ $d ][] = $ev;
		}
	}
	$prev  = $first->modify( '-1 month' )->format( 'Y-m' );
	$next  = $first->modify( '+1 month' )->format( 'Y-m' );
	$title = mcupr_ev_month_title( $y, $m );
	$today = mcupr_ev_fmt( 'Y-n-j', time() );
	$dn    = mcupr_ev_n( 'days' );
	$ds    = mcupr_ev_n( 'dshort' );

	ob_start();
	echo '<div class="cal-wrap" id="calendar">';
	echo '<div class="cal-nav">';
	echo '<a class="cal-btn" rel="prev" href="' . esc_url( mcupr_cal_url( $prev ) ) . '" aria-label="' . esc_attr( mcupr_t( 'prev_month' ) ) . '"><span aria-hidden="true">‹</span><span class="cal-btn-t">' . esc_html( mcupr_t( 'prev_month' ) ) . '</span></a>';
	echo '<h2 class="cal-title" id="cal-title">' . esc_html( $title ) . '</h2>';
	echo '<a class="cal-btn" rel="next" href="' . esc_url( mcupr_cal_url( $next ) ) . '" aria-label="' . esc_attr( mcupr_t( 'next_month' ) ) . '"><span class="cal-btn-t">' . esc_html( mcupr_t( 'next_month' ) ) . '</span><span aria-hidden="true">›</span></a>';
	echo '<a class="cal-btn cal-today" href="' . esc_url( mcupr_cal_url( mcupr_ev_fmt( 'Y-m', time() ) ) ) . '">' . esc_html( mcupr_t( 'this_month' ) ) . '</a>';
	echo '</div>';
	echo '<table class="cal" aria-labelledby="cal-title"><thead><tr>';
	for ( $w = 0; $w < 7; $w++ ) {
		echo '<th scope="col"><abbr title="' . esc_attr( $dn[ $w ] ) . '">' . esc_html( $ds[ $w ] ) . '</abbr></th>';
	}
	echo '</tr></thead><tbody>';
	$cells = $offset + $days;
	$rows  = (int) ceil( $cells / 7 );
	for ( $r = 0; $r < $rows; $r++ ) {
		echo '<tr>';
		for ( $c = 0; $c < 7; $c++ ) {
			$d = $r * 7 + $c - $offset + 1;
			if ( $d < 1 || $d > $days ) {
				echo '<td class="cal-out"></td>';
				continue;
			}
			$is_today = ( $y . '-' . $m . '-' . $d ) === $today;
			$cls      = 'cal-d' . ( $is_today ? ' is-today' : '' ) . ( isset( $by_day[ $d ] ) ? ' has-ev' : '' ) . ( 0 === $c ? ' is-sun' : '' );
			echo '<td class="' . esc_attr( $cls ) . '"' . ( $is_today ? ' aria-current="date"' : '' ) . '>';
			echo '<span class="cal-n">' . (int) $d . '</span>';
			if ( isset( $by_day[ $d ] ) ) {
				echo '<ul class="cal-evs">';
				foreach ( $by_day[ $d ] as $ev ) {
					echo '<li><a href="' . esc_url( get_permalink( $ev ) ) . '">' . esc_html( get_the_title( $ev ) ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '<h3 class="cal-sub">' . esc_html( mcupr_t( 'month_events' ) ) . '</h3>';
	if ( $events ) {
		echo '<ul class="ev-list">';
		foreach ( $events as $ev ) {
			mcupr_event_row( $ev );
		}
		echo '</ul>';
	} else {
		echo '<p class="text-muted">' . esc_html( mcupr_t( 'no_events' ) ) . '</p>';
	}
	echo '</div>';
	return ob_get_clean();
}

/** One list row: date badge, title, when / where. */
function mcupr_event_row( $ev ) {
	$i  = mcupr_event_info( $ev->ID );
	$ms = mcupr_ev_n( 'mshort' );
	$mi = (int) mcupr_ev_fmt( 'n', $i['start'] ) - 1;
	echo '<li class="ev-row"><span class="ev-badge" aria-hidden="true"><b>' . (int) mcupr_ev_fmt( 'j', $i['start'] ) . '</b><small>' . esc_html( $ms[ $mi ] ) . '</small></span><span class="ev-main">';
	echo '<a class="ev-title" href="' . esc_url( get_permalink( $ev ) ) . '">' . esc_html( get_the_title( $ev ) ) . '</a>';
	echo '<span class="ev-meta">' . esc_html( mcupr_event_when( $i ) ) . ( '' !== $i['place'] ? ' · ' . esc_html( $i['place'] ) : '' ) . '</span>';
	echo '</span></li>';
}

function mcupr_events_upcoming_html( $count = 6 ) {
	$events = mcupr_events_upcoming( $count );
	if ( ! $events ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'no_events' ) ) . '</p>';
	}
	ob_start();
	echo '<ul class="ev-list">';
	foreach ( $events as $ev ) {
		mcupr_event_row( $ev );
	}
	echo '</ul>';
	return ob_get_clean();
}

/** [mcupr_calendar] – the month calendar. */
add_shortcode(
	'mcupr_calendar',
	static function () {
		return mcupr_calendar_html();
	}
);

/** [mcupr_events count="4"] – upcoming events. */
add_shortcode(
	'mcupr_events',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'count' => 4 ), $atts, 'mcupr_events' );
		return mcupr_events_upcoming_html( max( 1, min( 20, (int) $a['count'] ) ) );
	}
);

/* ------------------------------------------------------------------ add to calendar: Google link + .ics */

function mcupr_event_google_url( $post_id ) {
	$i = mcupr_event_info( $post_id );
	if ( $i['allday'] ) {
		$dates = mcupr_ev_fmt( 'Ymd', $i['start'] ) . '/' . mcupr_ev_fmt( 'Ymd', $i['end'] + DAY_IN_SECONDS );
	} else {
		$dates = gmdate( 'Ymd\THis\Z', $i['start'] ) . '/' . gmdate( 'Ymd\THis\Z', max( $i['end'], $i['start'] + HOUR_IN_SECONDS ) );
	}
	return 'https://calendar.google.com/calendar/render?' . http_build_query(
		array(
			'action'   => 'TEMPLATE',
			'text'     => wp_strip_all_tags( get_the_title( $post_id ) ),
			'dates'    => $dates,
			'details'  => get_permalink( $post_id ),
			'location' => $i['place'],
		),
		'',
		'&',
		PHP_QUERY_RFC3986
	);
}

function mcupr_ics_escape( $s ) {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n", "\r" ), array( '\\\\', '\\;', '\\,', '\\n', '\\n', '\\n' ), (string) $s );
}

/** RFC 5545 line folding at 75 octets without splitting a UTF-8 character. */
function mcupr_ics_fold( $line ) {
	$out = '';
	$len = 0;
	foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$n = strlen( $ch );
		if ( $len + $n > 74 ) {
			$out .= "\r\n ";
			$len  = 1;
		}
		$out .= $ch;
		$len += $n;
	}
	return $out . "\r\n";
}

function mcupr_ics_vevent( $post_id ) {
	$i   = mcupr_event_info( $post_id );
	$uid = $post_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
	$l   = array( 'BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) );
	if ( $i['allday'] ) {
		$l[] = 'DTSTART;VALUE=DATE:' . mcupr_ev_fmt( 'Ymd', $i['start'] );
		$l[] = 'DTEND;VALUE=DATE:' . mcupr_ev_fmt( 'Ymd', $i['end'] + DAY_IN_SECONDS );
	} else {
		$l[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', $i['start'] );
		$l[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', max( $i['end'], $i['start'] + 900 ) );
	}
	$l[] = 'SUMMARY:' . mcupr_ics_escape( wp_strip_all_tags( get_the_title( $post_id ) ) );
	if ( '' !== $i['place'] ) {
		$l[] = 'LOCATION:' . mcupr_ics_escape( $i['place'] );
	}
	$l[] = 'URL:' . get_permalink( $post_id );
	$l[] = 'END:VEVENT';
	return implode( '', array_map( 'mcupr_ics_fold', $l ) );
}

/** ?mcupr_ics=ID (one event) or ?mcupr_ics=all (feed to subscribe to) */
function mcupr_ics_output() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['mcupr_ics'] ) ) {
		return;
	}
	$which = sanitize_text_field( wp_unslash( $_GET['mcupr_ics'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'all' === $which ) {
		$ids = get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => 'publish', 'posts_per_page' => 500, 'fields' => 'ids', 'no_found_rows' => true ) );
		$name = 'events';
	} else {
		$p = get_post( absint( $which ) );
		if ( ! $p || 'mcupr_event' !== $p->post_type || 'publish' !== $p->post_status ) {
			status_header( 404 );
			return;
		}
		$ids  = array( $p->ID );
		$name = 'event-' . $p->ID;
	}
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '.ics"' );
	echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//MCUPR//Events//EN\r\nCALSCALE:GREGORIAN\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $ids as $id ) {
		echo mcupr_ics_vevent( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo "END:VCALENDAR\r\n";
	exit;
}
add_action( 'template_redirect', 'mcupr_ics_output', 1 );

/* ------------------------------------------------------------------ schema.org/Event */

function mcupr_event_jsonld() {
	if ( ! is_singular( 'mcupr_event' ) ) {
		return;
	}
	$id = get_queried_object_id();
	$i  = mcupr_event_info( $id );
	$d  = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => wp_strip_all_tags( get_the_title( $id ) ),
		'startDate'           => $i['allday'] ? mcupr_ev_fmt( 'Y-m-d', $i['start'] ) : mcupr_ev_fmt( 'c', $i['start'] ),
		'endDate'             => $i['allday'] ? mcupr_ev_fmt( 'Y-m-d', $i['end'] ) : mcupr_ev_fmt( 'c', $i['end'] ),
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'url'                 => get_permalink( $id ),
		'description'         => mcupr_seo_description(),
		'organizer'           => array( '@type' => 'Organization', 'name' => mcupr_org_name(), 'url' => home_url( '/' ) ),
	);
	if ( '' !== $i['place'] ) {
		$d['location'] = array( '@type' => 'Place', 'name' => $i['place'] );
	}
	if ( has_post_thumbnail( $id ) ) {
		$d['image'] = get_the_post_thumbnail_url( $id, 'mcupr-hero' );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'wp_head', 'mcupr_event_jsonld', 20 );
