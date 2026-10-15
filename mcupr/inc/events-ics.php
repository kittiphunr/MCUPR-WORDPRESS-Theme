<?php
/**
 * Calendar import and sync (.ics): bring events into the theme's calendar from an .ics file (Google Calendar, Outlook,
 * Apple Calendar, other systems) or keep them in step with a calendar address (the "secret address in iCal format" of
 * Google Calendar, a university system…) that is read again every hour / twice a day / daily. Admin page: MCUPR → "Import
 * and sync calendar". Understands time zones, all-day events, durations, simple repeating events (daily, weekly with
 * days, monthly, yearly; count / until / skipped dates), cancelled events. Re-importing never duplicates (events are
 * matched by their UID); a synced calendar can also remove events that disappeared from it.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ parser */

/** Unfold lines and read the VEVENT blocks into arrays of properties. */
function mcupr_ics_blocks( $text ) {
	$text  = preg_replace( "/\r\n|\r/", "\n", (string) $text );
	$text  = preg_replace( "/\n[ \t]/", '', $text ); // folded lines
	$lines = explode( "\n", $text );
	$out   = array();
	$cur   = null;
	$depth = 0;
	foreach ( $lines as $line ) {
		$u = strtoupper( trim( $line ) );
		if ( 'BEGIN:VEVENT' === $u ) {
			$cur   = array();
			$depth = 0;
			continue;
		}
		if ( null === $cur ) {
			continue;
		}
		if ( 'END:VEVENT' === $u && 0 === $depth ) {
			$out[] = $cur;
			$cur   = null;
			continue;
		}
		if ( 0 === strpos( $u, 'BEGIN:' ) ) { // VALARM etc. inside the event: ignored
			$depth++;
			continue;
		}
		if ( 0 === strpos( $u, 'END:' ) ) {
			$depth = max( 0, $depth - 1 );
			continue;
		}
		if ( $depth > 0 || false === strpos( $line, ':' ) ) {
			continue;
		}
		list( $head, $val ) = explode( ':', $line, 2 );
		$parts  = explode( ';', $head );
		$name   = strtoupper( array_shift( $parts ) );
		$params = array();
		foreach ( $parts as $p ) {
			if ( false !== strpos( $p, '=' ) ) {
				list( $k, $v ) = explode( '=', $p, 2 );
				$params[ strtoupper( $k ) ] = trim( $v, '"' );
			}
		}
		$cur[ $name ][] = array( 'v' => $val, 'p' => $params );
	}
	return $out;
}

function mcupr_ics_unescape( $s ) {
	return str_replace( array( '\\n', '\\N', '\\,', '\;', '\\\\' ), array( "\n", "\n", ',', ';', '\\' ), (string) $s );
}

/** Read a DTSTART/DTEND value: array( DateTimeImmutable in the site's zone, is_all_day ) or null. */
function mcupr_ics_date( $prop ) {
	if ( ! $prop ) {
		return null;
	}
	$v  = trim( $prop['v'] );
	$p  = $prop['p'];
	$tz = wp_timezone();
	try {
		if ( ( isset( $p['VALUE'] ) && 'DATE' === strtoupper( $p['VALUE'] ) ) || preg_match( '/^\d{8}$/', $v ) ) {
			$d = DateTimeImmutable::createFromFormat( 'Ymd H:i:s', substr( $v, 0, 8 ) . ' 00:00:00', $tz );
			return $d ? array( $d, true ) : null;
		}
		if ( ! preg_match( '/^(\d{8})T(\d{6})(Z?)$/', $v, $m ) ) {
			return null;
		}
		if ( 'Z' === $m[3] ) {
			$zone = new DateTimeZone( 'UTC' );
		} elseif ( ! empty( $p['TZID'] ) ) {
			try {
				$zone = new DateTimeZone( $p['TZID'] );
			} catch ( Exception $e ) {
				$zone = $tz;
			}
		} else {
			$zone = $tz;
		}
		$d = DateTimeImmutable::createFromFormat( 'Ymd His', $m[1] . ' ' . $m[2], $zone );
		return $d ? array( $d->setTimezone( $tz ), false ) : null;
	} catch ( Exception $e ) {
		return null;
	}
}

function mcupr_ics_duration( $s ) {
	if ( ! preg_match( '/^([+-])?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', trim( $s ), $m ) ) {
		return 0;
	}
	$sec = ( (int) ( isset( $m[2] ) ? $m[2] : 0 ) ) * WEEK_IN_SECONDS + ( (int) ( isset( $m[3] ) ? $m[3] : 0 ) ) * DAY_IN_SECONDS + ( (int) ( isset( $m[4] ) ? $m[4] : 0 ) ) * HOUR_IN_SECONDS + ( (int) ( isset( $m[5] ) ? $m[5] : 0 ) ) * MINUTE_IN_SECONDS + (int) ( isset( $m[6] ) ? $m[6] : 0 );
	return '-' === $m[1] ? -$sec : $sec;
}

/**
 * Read an .ics text. Returns array of events: uid, title, desc, place, url, start (DateTimeImmutable), end (DateTimeImmutable,
 * inclusive for all-day), allday, cancelled, rrule (array|null), exdates (array of 'Ymd'), recurrence_id (string|'').
 */
function mcupr_ics_parse( $text ) {
	$events = array();
	foreach ( mcupr_ics_blocks( $text ) as $b ) {
		$first = static function ( $k ) use ( $b ) {
			return isset( $b[ $k ][0] ) ? $b[ $k ][0] : null;
		};
		$s = mcupr_ics_date( $first( 'DTSTART' ) );
		if ( ! $s ) {
			continue;
		}
		$e = mcupr_ics_date( $first( 'DTEND' ) );
		if ( $e ) {
			$end = $e[0];
			if ( $s[1] ) { // all-day: DTEND is the day after the last one
				$end = $end->modify( '-1 day' );
				if ( $end < $s[0] ) {
					$end = $s[0];
				}
			}
		} elseif ( $first( 'DURATION' ) ) {
			$end = $s[0]->modify( ( mcupr_ics_duration( $first( 'DURATION' )['v'] ) ) . ' seconds' );
			if ( $s[1] ) {
				$end = $end->modify( '-1 day' );
			}
		} else {
			$end = $s[0];
		}
		$rr = null;
		if ( $first( 'RRULE' ) ) {
			$rr = array();
			foreach ( explode( ';', $first( 'RRULE' )['v'] ) as $kv ) {
				if ( false !== strpos( $kv, '=' ) ) {
					list( $k, $v ) = explode( '=', $kv, 2 );
					$rr[ strtoupper( $k ) ] = strtoupper( $v );
				}
			}
		}
		$ex = array();
		foreach ( isset( $b['EXDATE'] ) ? $b['EXDATE'] : array() as $x ) {
			foreach ( explode( ',', $x['v'] ) as $one ) {
				$d = mcupr_ics_date( array( 'v' => $one, 'p' => $x['p'] ) );
				if ( $d ) {
					$ex[] = $d[0]->format( 'Ymd' );
				}
			}
		}
		$rid = '';
		if ( $first( 'RECURRENCE-ID' ) ) {
			$d   = mcupr_ics_date( $first( 'RECURRENCE-ID' ) );
			$rid = $d ? $d[0]->format( 'Ymd' ) : '';
		}
		$get      = static function ( $k ) use ( $first ) {
			$f = $first( $k );
			return $f ? trim( mcupr_ics_unescape( $f['v'] ) ) : '';
		};
		$status   = strtoupper( $get( 'STATUS' ) );
		$events[] = array(
			'uid'           => $get( 'UID' ),
			'title'         => $get( 'SUMMARY' ),
			'desc'          => $get( 'DESCRIPTION' ),
			'place'         => $get( 'LOCATION' ),
			'url'           => $get( 'URL' ),
			'start'         => $s[0],
			'end'           => $end,
			'allday'        => $s[1],
			'cancelled'     => 'CANCELLED' === $status,
			'rrule'         => $rr,
			'exdates'       => $ex,
			'recurrence_id' => $rid,
		);
	}
	return $events;
}

/** Turn a repeating event into its occurrences between two timestamps (at most 300). Unsupported rules give the first one only. */
function mcupr_ics_occurrences( $ev, $from, $to ) {
	if ( empty( $ev['rrule'] ) ) {
		return array( $ev );
	}
	$r     = $ev['rrule'];
	$freq  = isset( $r['FREQ'] ) ? $r['FREQ'] : '';
	$step  = isset( $r['INTERVAL'] ) ? max( 1, (int) $r['INTERVAL'] ) : 1;
	$count = isset( $r['COUNT'] ) ? (int) $r['COUNT'] : 0;
	$until = null;
	if ( ! empty( $r['UNTIL'] ) ) {
		$u     = mcupr_ics_date( array( 'v' => $r['UNTIL'], 'p' => array() ) );
		$until = $u ? $u[0]->setTime( 23, 59, 59 ) : null;
	}
	if ( ! in_array( $freq, array( 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ), true ) || isset( $r['BYSETPOS'] ) || ( 'MONTHLY' === $freq && isset( $r['BYDAY'] ) ) ) {
		return array( $ev ); // beyond what we expand: the first event is still shown
	}
	$len  = $ev['end']->getTimestamp() - $ev['start']->getTimestamp();
	$days = array( 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 );
	$want = array();
	if ( 'WEEKLY' === $freq && ! empty( $r['BYDAY'] ) ) {
		foreach ( explode( ',', $r['BYDAY'] ) as $d ) {
			$d = substr( $d, -2 );
			if ( isset( $days[ $d ] ) ) {
				$want[] = $days[ $d ];
			}
		}
	}
	if ( ! $want ) {
		$want = array( (int) $ev['start']->format( 'w' ) );
	}
	sort( $want );
	$out   = array();
	$n     = 0;
	$loops = 0;
	$base  = $ev['start'];
	while ( $loops++ < 1500 && count( $out ) < 300 ) {
		$cands = array();
		if ( 'WEEKLY' === $freq ) {
			$week_start = $base->modify( 'sunday this week' )->modify( '-0 days' );
			if ( (int) $base->format( 'w' ) !== 0 ) {
				$week_start = $base->modify( '-' . (int) $base->format( 'w' ) . ' days' );
			} else {
				$week_start = $base;
			}
			foreach ( $want as $w ) {
				$cands[] = $week_start->modify( '+' . $w . ' days' )->setTime( (int) $ev['start']->format( 'G' ), (int) $ev['start']->format( 'i' ), (int) $ev['start']->format( 's' ) );
			}
		} else {
			$cands[] = $base;
		}
		foreach ( $cands as $c ) {
			if ( $c < $ev['start'] ) {
				continue;
			}
			if ( $until && $c > $until ) {
				break 2;
			}
			if ( $c->getTimestamp() > $to ) {
				break 2;
			}
			$n++;
			if ( $count && $n > $count ) {
				break 2;
			}
			$endc = $c->modify( ( $len ) . ' seconds' );
			if ( $endc->getTimestamp() >= $from && ! in_array( $c->format( 'Ymd' ), $ev['exdates'], true ) ) {
				$o            = $ev;
				$o['start']   = $c;
				$o['end']     = $endc;
				$o['rrule']   = null;
				$o['occ']     = $c->format( 'Ymd' );
				$out[]        = $o;
			}
		}
		$unit = array( 'DAILY' => 'days', 'WEEKLY' => 'weeks', 'MONTHLY' => 'months', 'YEARLY' => 'years' )[ $freq ];
		$base = $base->modify( '+' . $step . ' ' . $unit );
	}
	return $out;
}

/* ------------------------------------------------------------------ import */

function mcupr_ics_post_for( $key ) {
	$q = get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ), 'posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids', 'meta_key' => '_mcupr_ics_uid', 'meta_value' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $q ? (int) $q[0] : 0;
}

/**
 * Put the events of an .ics text into the calendar. $opts: src (source id), status (publish|draft), skip_past_days (events that
 * ended longer ago are ignored), remove_missing (trash events of this source that are no longer in the file).
 * Returns array( added, updated, skipped, removed, cancelled, errors (array of text) ).
 */
function mcupr_ics_import( $text, $opts = array() ) {
	$o   = array_merge( array( 'src' => 'file', 'status' => 'publish', 'skip_past_days' => 30, 'remove_missing' => false ), $opts );
	$res = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'removed' => 0, 'cancelled' => 0, 'errors' => array() );
	if ( false === stripos( (string) $text, 'BEGIN:VCALENDAR' ) ) {
		$res['errors'][] = 'not-ics';
		return $res;
	}
	$now   = time();
	$from  = $now - (int) $o['skip_past_days'] * DAY_IN_SECONDS;
	$to    = $now + 2 * YEAR_IN_SECONDS;
	$sync  = wp_generate_uuid4(); // told apart even when two syncs happen in the same second
	$seen  = array();
	$evs   = mcupr_ics_parse( $text );
	$moved = array(); // UID|date of a single changed occurrence of a repeating event
	foreach ( $evs as $ev ) {
		if ( '' !== $ev['recurrence_id'] ) {
			$moved[ $ev['uid'] . '|' . $ev['recurrence_id'] ] = true;
		}
	}
	foreach ( $evs as $ev ) {
		$uid = '' !== $ev['uid'] ? $ev['uid'] : md5( $ev['title'] . $ev['start']->format( 'c' ) );
		foreach ( mcupr_ics_occurrences( $ev, $from, $to ) as $oc ) {
			$suffix = ! empty( $oc['occ'] ) ? '#' . $oc['occ'] : ( '' !== $ev['recurrence_id'] ? '#' . $ev['recurrence_id'] : '' );
			if ( ! empty( $oc['occ'] ) && isset( $moved[ $uid . '|' . $oc['occ'] ] ) ) {
				continue; // this day was changed by its own entry
			}
			$key = $o['src'] . '|' . $uid . $suffix;
			if ( $oc['end']->getTimestamp() < $from ) {
				$res['skipped']++;
				continue;
			}
			$id = mcupr_ics_post_for( $key );
			if ( $ev['cancelled'] ) {
				if ( $id ) {
					wp_trash_post( $id );
				}
				$res['cancelled']++;
				continue;
			}
			if ( '' === $ev['title'] ) {
				$res['skipped']++;
				continue;
			}
			$seen[ $key ] = true;
			$content      = '' !== $ev['desc'] ? wpautop( make_clickable( esc_html( $ev['desc'] ) ) ) : '';
			$arr          = array( 'post_type' => 'mcupr_event', 'post_title' => wp_strip_all_tags( $ev['title'] ), 'post_content' => $content, 'post_status' => 'draft' === $o['status'] ? 'draft' : 'publish' );
			if ( $id ) {
				if ( get_post_meta( $id, '_mcupr_ics_lock', true ) ) {
					$res['skipped']++;
					update_post_meta( $id, '_mcupr_ics_seen', $sync );
					continue;
				}
				$arr['ID'] = $id;
				wp_update_post( $arr );
				$res['updated']++;
			} else {
				$id = wp_insert_post( $arr );
				if ( ! $id || is_wp_error( $id ) ) {
					$res['errors'][] = 'insert';
					continue;
				}
				update_post_meta( $id, '_mcupr_ics_uid', $key );
				$res['added']++;
			}
			$fmt = $oc['allday'] ? array( 'Y-m-d 00:00:00', 'Y-m-d 23:59:59' ) : array( 'Y-m-d H:i:s', 'Y-m-d H:i:s' );
			update_post_meta( $id, '_mcupr_ev_start', $oc['start']->format( $fmt[0] ) );
			update_post_meta( $id, '_mcupr_ev_end', $oc['end']->format( $fmt[1] ) );
			if ( $oc['allday'] ) {
				update_post_meta( $id, '_mcupr_ev_allday', 1 );
			} else {
				delete_post_meta( $id, '_mcupr_ev_allday' );
			}
			update_post_meta( $id, '_mcupr_ev_place', mb_substr( $ev['place'], 0, 200 ) );
			update_post_meta( $id, '_mcupr_ev_url', esc_url_raw( $ev['url'], array( 'http', 'https' ) ) );
			update_post_meta( $id, '_mcupr_ics_src', $o['src'] );
			update_post_meta( $id, '_mcupr_ics_seen', $sync );
		}
	}
	if ( $o['remove_missing'] ) {
		$old = get_posts( array( 'post_type' => 'mcupr_event', 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_mcupr_ics_src', 'value' => $o['src'] ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $old as $pid ) {
			if ( (string) get_post_meta( $pid, '_mcupr_ics_seen', true ) !== $sync && ! get_post_meta( $pid, '_mcupr_ics_lock', true ) && mcupr_event_info( $pid )['end'] >= $from ) {
				wp_trash_post( $pid );
				$res['removed']++;
			}
		}
	}
	return $res;
}

/* ------------------------------------------------------------------ feeds (sync) */

function mcupr_ics_feeds() {
	$f = get_option( 'mcupr_ics_feeds', array() );
	return is_array( $f ) ? $f : array();
}

/** Normalise a calendar address typed by a person: webcal:// → https://, only http(s). Returns '' when not usable. */
function mcupr_ics_clean_url( $u ) {
	$u = trim( (string) $u );
	$u = preg_replace( '#^webcals?://#i', 'https://', $u );
	$u = esc_url_raw( $u, array( 'http', 'https' ) );
	return $u && wp_parse_url( $u, PHP_URL_HOST ) ? $u : ''; // (private / local addresses are refused again when it is fetched: wp_safe_remote_get)
}

function mcupr_ics_fetch( $url ) {
	$r = wp_safe_remote_get( $url, array( 'timeout' => 15, 'redirection' => 3, 'limit_response_size' => 2 * MB_IN_BYTES, 'headers' => array( 'Accept' => 'text/calendar, text/plain, */*' ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'http', 'HTTP ' . (int) wp_remote_retrieve_response_code( $r ) );
	}
	return (string) wp_remote_retrieve_body( $r );
}

/** Read one feed now and import it. Stores when and how it went. Returns the import result or WP_Error. */
function mcupr_ics_sync_feed( $id ) {
	$feeds = mcupr_ics_feeds();
	if ( ! isset( $feeds[ $id ] ) ) {
		return new WP_Error( 'none', 'unknown feed' );
	}
	$f    = $feeds[ $id ];
	$body = mcupr_ics_fetch( $f['url'] );
	if ( is_wp_error( $body ) ) {
		$feeds[ $id ]['last'] = time();
		$feeds[ $id ]['msg']  = 'error: ' . $body->get_error_message();
		update_option( 'mcupr_ics_feeds', $feeds, false );
		return $body;
	}
	$res                  = mcupr_ics_import( $body, array( 'src' => 'feed-' . $id, 'status' => $f['status'], 'remove_missing' => ! empty( $f['remove'] ) ) );
	$feeds                = mcupr_ics_feeds();
	$feeds[ $id ]['last'] = time();
	$feeds[ $id ]['msg']  = $res['errors'] ? 'error: ' . implode( ', ', $res['errors'] ) : sprintf( 'ok: +%d ~%d -%d', $res['added'], $res['updated'], $res['removed'] );
	update_option( 'mcupr_ics_feeds', $feeds, false );
	return $res;
}

function mcupr_ics_every_seconds( $e ) {
	return array( 'hourly' => HOUR_IN_SECONDS, 'twicedaily' => 12 * HOUR_IN_SECONDS, 'daily' => DAY_IN_SECONDS )[ in_array( $e, array( 'hourly', 'twicedaily', 'daily' ), true ) ? $e : 'daily' ];
}

add_action(
	'mcupr_ics_cron',
	static function () {
		foreach ( mcupr_ics_feeds() as $id => $f ) {
			if ( time() - (int) $f['last'] >= mcupr_ics_every_seconds( $f['every'] ) - 120 ) {
				mcupr_ics_sync_feed( $id );
			}
		}
	}
);
/** Schedule the hourly job while there is a feed, remove it when there is none. */
function mcupr_ics_schedule() {
	$has = (bool) mcupr_ics_feeds();
	$ts  = wp_next_scheduled( 'mcupr_ics_cron' );
	if ( $has && ! $ts ) {
		wp_schedule_event( time() + 300, 'hourly', 'mcupr_ics_cron' );
	} elseif ( ! $has && $ts ) {
		wp_unschedule_event( $ts, 'mcupr_ics_cron' );
	}
}
add_action( 'init', 'mcupr_ics_schedule' );

/* ------------------------------------------------------------------ admin page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Import and sync calendar', 'mcupr' ), __( 'Import and sync calendar', 'mcupr' ), 'edit_pages', 'mcupr-ics', 'mcupr_ics_page' );
	}
);

function mcupr_ics_result_text( $r ) {
	if ( is_wp_error( $r ) ) {
		return sprintf( /* translators: %s: error */ __( 'Could not read the calendar: %s', 'mcupr' ), $r->get_error_message() );
	}
	if ( in_array( 'not-ics', $r['errors'], true ) ) {
		return __( 'This is not a calendar file (.ics).', 'mcupr' );
	}
	return sprintf( /* translators: 1: added 2: updated 3: removed 4: skipped */ __( 'Done: %1$d added, %2$d updated, %3$d removed, %4$d skipped (past events, cancelled or without a title).', 'mcupr' ), $r['added'], $r['updated'], $r['removed'], $r['skipped'] + $r['cancelled'] );
}

function mcupr_ics_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$feeds = mcupr_ics_feeds();
	$every = array( 'hourly' => __( 'Every hour', 'mcupr' ), 'twicedaily' => __( 'Twice a day', 'mcupr' ), 'daily' => __( 'Once a day', 'mcupr' ) );
	echo '<div class="wrap"><h1>' . esc_html__( 'Import and sync calendar', 'mcupr' ) . '</h1>';
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['msg'] ) ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( rawurldecode( $_GET['msg'] ) ) ) ) . '</p></div>';
	}
	// phpcs:enable
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '1. Import a file (.ics)', 'mcupr' ) . '</h2><p class="description">' . esc_html__( 'Export your calendar from Google Calendar, Outlook or Apple Calendar as an .ics file and choose it here. Importing the same file again updates the events instead of duplicating them.', 'mcupr' ) . '</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_ics_upload">';
	wp_nonce_field( 'mcupr_ics_upload', 'mcupr_ics_nonce' );
	echo '<p><input type="file" name="ics" accept=".ics,text/calendar" required></p><p><label>' . esc_html__( 'Publish the imported events', 'mcupr' ) . ' <select name="status"><option value="publish">' . esc_html__( 'at once', 'mcupr' ) . '</option><option value="draft">' . esc_html__( 'as drafts (to check first)', 'mcupr' ) . '</option></select></label></p>';
	submit_button( __( 'Import', 'mcupr' ), 'primary', 'submit', false );
	echo '</form></div>';

	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '2. Keep in step with a calendar address (sync)', 'mcupr' ) . '</h2><p class="description">' . esc_html__( 'Paste the address of a calendar in iCal format (Google Calendar: Settings → the calendar → “Secret address in iCal format”). The theme reads it again automatically. Events that came from it are kept up to date here, so change them in the original calendar – an event you edit here is overwritten at the next sync unless you tick “Do not overwrite” in its box.', 'mcupr' ) . '</p>';
	if ( $feeds ) {
		echo '<table class="widefat striped" style="margin:1em 0"><thead><tr><th>' . esc_html__( 'Name', 'mcupr' ) . '</th><th>' . esc_html__( 'Address', 'mcupr' ) . '</th><th>' . esc_html__( 'Reads', 'mcupr' ) . '</th><th>' . esc_html__( 'Last time', 'mcupr' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $feeds as $id => $f ) {
			echo '<tr><td><strong>' . esc_html( $f['name'] ) . '</strong></td><td style="word-break:break-all"><code>' . esc_html( preg_replace( '#(//[^/]{0,12})[^?]*#', '$1…', $f['url'] ) ) . '</code></td><td>' . esc_html( $every[ $f['every'] ] ) . ( ! empty( $f['remove'] ) ? '<br><small>' . esc_html__( 'removes events that disappear', 'mcupr' ) . '</small>' : '' ) . '</td><td>' . ( $f['last'] ? esc_html( wp_date( 'j M Y H:i', $f['last'] ) . ' — ' . $f['msg'] ) : '—' ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline"><input type="hidden" name="action" value="mcupr_ics_feed"><input type="hidden" name="feed" value="' . esc_attr( $id ) . '">';
			wp_nonce_field( 'mcupr_ics_feed', 'mcupr_ics_nonce' );
			echo '<button class="button" name="do" value="sync">' . esc_html__( 'Sync now', 'mcupr' ) . '</button> <button class="button-link-delete" name="do" value="delete" onclick="return confirm(\'' . esc_js( __( 'Stop syncing this calendar? Events already imported stay.', 'mcupr' ) ) . '\')">' . esc_html__( 'Stop', 'mcupr' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_ics_feed"><input type="hidden" name="do" value="add">';
	wp_nonce_field( 'mcupr_ics_feed', 'mcupr_ics_nonce' );
	echo '<table class="form-table" role="presentation"><tbody><tr><th><label for="ics-name">' . esc_html__( 'Name', 'mcupr' ) . '</label></th><td><input id="ics-name" name="name" class="regular-text" placeholder="' . esc_attr__( 'e.g. Academic calendar', 'mcupr' ) . '"></td></tr>';
	echo '<tr><th><label for="ics-url">' . esc_html__( 'Calendar address', 'mcupr' ) . '</label></th><td><input id="ics-url" type="url" name="url" class="large-text" placeholder="https://calendar.google.com/calendar/ical/…/basic.ics" required></td></tr>';
	echo '<tr><th><label for="ics-every">' . esc_html__( 'Read it', 'mcupr' ) . '</label></th><td><select id="ics-every" name="every">';
	foreach ( $every as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( 'daily', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></td></tr><tr><th>' . esc_html__( 'Options', 'mcupr' ) . '</th><td><label><input type="checkbox" name="remove" value="1" checked> ' . esc_html__( 'Remove from the website the events that were deleted in the original calendar', 'mcupr' ) . '</label><br><label>' . esc_html__( 'Publish', 'mcupr' ) . ' <select name="status"><option value="publish">' . esc_html__( 'at once', 'mcupr' ) . '</option><option value="draft">' . esc_html__( 'as drafts', 'mcupr' ) . '</option></select></label></td></tr></tbody></table>';
	submit_button( __( 'Add and read now', 'mcupr' ), 'primary', 'submit', false );
	echo '</form></div>';
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( '3. The other way: subscribe to this website’s calendar', 'mcupr' ) . '</h2><p>' . esc_html__( 'Visitors and staff can add all events to their own calendar app with this address:', 'mcupr' ) . '</p><p><code>' . esc_html( home_url( '/?mcupr_ics=all' ) ) . '</code></p></div></div>';
}

function mcupr_ics_back( $msg ) {
	wp_safe_redirect( add_query_arg( 'msg', rawurlencode( $msg ), admin_url( 'admin.php?page=mcupr-ics' ) ) );
	exit;
}

add_action(
	'admin_post_mcupr_ics_upload',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) || ! isset( $_POST['mcupr_ics_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_ics_nonce'] ) ), 'mcupr_ics_upload' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$f = isset( $_FILES['ics'] ) ? $_FILES['ics'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( empty( $f['tmp_name'] ) || ! empty( $f['error'] ) || $f['size'] > 2 * MB_IN_BYTES || ! preg_match( '/\.ics$/i', (string) $f['name'] ) ) {
			mcupr_ics_back( __( 'Choose an .ics file of at most 2 MB.', 'mcupr' ) );
		}
		$text = file_get_contents( $f['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res  = mcupr_ics_import( (string) $text, array( 'src' => 'file', 'status' => isset( $_POST['status'] ) && 'draft' === $_POST['status'] ? 'draft' : 'publish' ) );
		mcupr_ics_back( mcupr_ics_result_text( $res ) );
	}
);

add_action(
	'admin_post_mcupr_ics_feed',
	static function () {
		if ( ! current_user_can( 'edit_pages' ) || ! isset( $_POST['mcupr_ics_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_ics_nonce'] ) ), 'mcupr_ics_feed' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$feeds = mcupr_ics_feeds();
		if ( 'add' === $do ) {
			$url = mcupr_ics_clean_url( isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! $url ) {
				mcupr_ics_back( __( 'This address cannot be used (it must start with https://, http:// or webcal://).', 'mcupr' ) );
			}
			$id           = substr( md5( $url ), 0, 10 );
			$feeds[ $id ] = array(
				'name'   => mb_substr( sanitize_text_field( isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '' ), 0, 80 ) ?: __( 'Calendar', 'mcupr' ),
				'url'    => $url,
				'every'  => isset( $_POST['every'] ) && in_array( $_POST['every'], array( 'hourly', 'twicedaily', 'daily' ), true ) ? $_POST['every'] : 'daily',
				'remove' => ! empty( $_POST['remove'] ) ? 1 : 0,
				'status' => isset( $_POST['status'] ) && 'draft' === $_POST['status'] ? 'draft' : 'publish',
				'last'   => 0,
				'msg'    => '',
			);
			update_option( 'mcupr_ics_feeds', $feeds, false );
			mcupr_ics_schedule();
			mcupr_ics_back( mcupr_ics_result_text( mcupr_ics_sync_feed( $id ) ) );
		}
		$id = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : '';
		if ( 'sync' === $do && isset( $feeds[ $id ] ) ) {
			mcupr_ics_back( mcupr_ics_result_text( mcupr_ics_sync_feed( $id ) ) );
		}
		if ( 'delete' === $do && isset( $feeds[ $id ] ) ) {
			unset( $feeds[ $id ] );
			update_option( 'mcupr_ics_feeds', $feeds, false );
			mcupr_ics_schedule();
			mcupr_ics_back( __( 'Stopped. Events already imported stay on the website.', 'mcupr' ) );
		}
		mcupr_ics_back( '' );
	}
);

/* ------------------------------------------------------------------ "do not overwrite" box on imported events */

add_action(
	'add_meta_boxes_mcupr_event',
	static function ( $post ) {
		if ( get_post_meta( $post->ID, '_mcupr_ics_uid', true ) ) {
			add_meta_box( 'mcupr_ics_lock', __( 'Imported from a calendar', 'mcupr' ), 'mcupr_ics_lock_box', 'mcupr_event', 'side', 'default' );
		}
	}
);
function mcupr_ics_lock_box( $post ) {
	wp_nonce_field( 'mcupr_ics_lock', 'mcupr_ics_lock_nonce' );
	echo '<p>' . esc_html__( 'This event came from a calendar file or address and is updated when that calendar changes.', 'mcupr' ) . '</p><label><input type="checkbox" name="mcupr_ics_lock" value="1"' . checked( (bool) get_post_meta( $post->ID, '_mcupr_ics_lock', true ), true, false ) . '> ' . esc_html__( 'Do not overwrite my changes', 'mcupr' ) . '</label>';
}
add_action(
	'save_post_mcupr_event',
	static function ( $post_id ) {
		if ( ! isset( $_POST['mcupr_ics_lock_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_ics_lock_nonce'] ) ), 'mcupr_ics_lock' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! empty( $_POST['mcupr_ics_lock'] ) ) {
			update_post_meta( $post_id, '_mcupr_ics_lock', 1 );
		} else {
			delete_post_meta( $post_id, '_mcupr_ics_lock' );
		}
	}
);
