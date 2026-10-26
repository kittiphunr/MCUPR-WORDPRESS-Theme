<?php
/**
 * The fields that belong to a procurement-style notice (inc/notices.php), all optional:
 *   number (เลขที่), method (วิธีจัดซื้อ), budget (งบประมาณ / ราคากลาง, baht), deadline (วันปิดรับ, a date),
 *   status (manual: open / closed / awarded / cancelled – or "automatic"), winner and agreed price (when awarded).
 *
 * The status a visitor sees:
 *   • a status chosen by hand is shown as it is;
 *   • "automatic": no deadline → no status; a deadline → "open" until the end of that day, then "closed".
 * The list can be filtered by it (?ns=open|closed|awarded|cancelled), and the table, the notice page and the home block show
 * the number, the method, the budget, the status and "closing soon / closes today" next to the deadline.
 *
 * Post meta: _mcupr_nt_no, _mcupr_nt_method, _mcupr_nt_budget, _mcupr_nt_deadline (Y-m-d), _mcupr_nt_status (absent = automatic),
 * _mcupr_nt_winner, _mcupr_nt_price.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_notice_statuses() {
	return array( 'open', 'closed', 'awarded', 'cancelled' );
}

/** A money value from what was typed ("1,250,000.50", " 3 000 "), or null. */
function mcupr_notice_money( $v ) {
	$v = str_replace( array( ',', ' ', "\xc2\xa0" ), '', trim( (string) $v ) );
	if ( '' === $v || ! preg_match( '/^\d{1,12}(\.\d{1,2})?$/', $v ) ) {
		return null;
	}
	return (float) $v;
}

/** The text of a money value: 4,500,000 or 4,500,000.50. */
function mcupr_notice_money_text( $n ) {
	return number_format_i18n( (float) $n, floor( $n ) == $n ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
}

function mcupr_notice_info( $id ) {
	$g = static function ( $k ) use ( $id ) { return (string) get_post_meta( $id, '_mcupr_nt_' . $k, true ); };
	$d = $g( 'deadline' );
	$s = $g( 'status' );
	return array(
		'no'       => $g( 'no' ),
		'method'   => $g( 'method' ),
		'budget'   => mcupr_notice_money( $g( 'budget' ) ),
		'deadline' => preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $dm ) && checkdate( (int) $dm[2], (int) $dm[3], (int) $dm[1] ) ? $d : '', // a date that does not exist is no date
		'status'   => in_array( $s, mcupr_notice_statuses(), true ) ? $s : '',
		'winner'   => $g( 'winner' ),
		'price'    => mcupr_notice_money( $g( 'price' ) ),
	);
}

/**
 * Today in the site's own time zone, as Y-m-d in the Gregorian calendar – the form the date field stores. (wp_date() must not be used
 * for this: the theme turns its year into the Buddhist era, which would make every closing date look as if it had passed.)
 */
function mcupr_notice_today() {
	return ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
}

/** The status a visitor sees: '' (none), open, closed, awarded or cancelled. */
function mcupr_notice_state( $id ) {
	$i = mcupr_notice_info( $id );
	if ( '' !== $i['status'] ) {
		return $i['status'];
	}
	if ( '' !== $i['deadline'] ) {
		return mcupr_notice_today() <= $i['deadline'] ? 'open' : 'closed';
	}
	return '';
}

/** Whole days from today to the deadline (0 = today), or null when there is none. */
function mcupr_notice_days_left( $id ) {
	$d = mcupr_notice_info( $id )['deadline'];
	if ( '' === $d ) {
		return null;
	}
	$tz = wp_timezone();
	$a  = new DateTimeImmutable( mcupr_notice_today(), $tz );
	$b  = new DateTimeImmutable( $d, $tz );
	return (int) $a->diff( $b )->format( '%r%a' );
}

/** The chip of a status: icon + words (never colour alone). */
function mcupr_notice_status_chip( $state ) {
	$map = array(
		'open'      => array( 'nt_st_open', 'check' ),
		'closed'    => array( 'nt_st_closed', 'lock' ),
		'awarded'   => array( 'nt_st_awarded', 'award' ),
		'cancelled' => array( 'nt_st_cancelled', 'alert' ),
	);
	if ( ! isset( $map[ $state ] ) ) {
		return '';
	}
	return '<span class="nt-st nt-st-' . esc_attr( $state ) . '">' . mcupr_icon( $map[ $state ][1], 14 ) . esc_html( mcupr_t( $map[ $state ][0] ) ) . '</span>';
}

/** "Closes 5 Nov 2569" ($label false: only the date) and, while open and near, "closing soon (3 days)" / "closes today". */
function mcupr_notice_deadline_html( $id, $label = true ) {
	$i = mcupr_notice_info( $id );
	if ( '' === $i['deadline'] ) {
		return '';
	}
	$ts   = ( new DateTimeImmutable( $i['deadline'] . ' 12:00:00', wp_timezone() ) )->getTimestamp();
	$o    = '<span class="nt-dl">' . ( $label ? esc_html( mcupr_t( 'nt_deadline' ) ) . ' ' : '' ) . '<time datetime="' . esc_attr( $i['deadline'] ) . '">' . esc_html( mcupr_date_text( $ts, 'short' ) ) . '</time></span>';
	$left = mcupr_notice_days_left( $id );
	if ( 'open' === mcupr_notice_state( $id ) && null !== $left && $left <= 7 ) {
		$o .= ' <span class="nt-soon">' . esc_html( 0 === $left ? mcupr_t( 'nt_today' ) : sprintf( mcupr_t( 'nt_soon' ), number_format_i18n( $left ) ) ) . '</span>';
	}
	return $o;
}

/** The line of facts under a title in the table and the home block: number · method · budget. */
function mcupr_notice_facts_html( $id ) {
	$i   = mcupr_notice_info( $id );
	$out = array();
	if ( '' !== $i['no'] ) {
		$out[] = '<span>' . esc_html( mcupr_t( 'nt_no' ) ) . ' ' . esc_html( $i['no'] ) . '</span>';
	}
	if ( '' !== $i['method'] ) {
		$out[] = '<span>' . esc_html( $i['method'] ) . '</span>';
	}
	if ( null !== $i['budget'] ) {
		$out[] = '<span>' . esc_html( mcupr_t( 'nt_budget' ) ) . ' ' . esc_html( sprintf( mcupr_t( 'nt_baht' ), mcupr_notice_money_text( $i['budget'] ) ) ) . '</span>';
	}
	return $out ? '<span class="nt-facts">' . implode( '', $out ) . '</span>' : '';
}

/** Status chip and deadline, one line. */
function mcupr_notice_state_html( $id ) {
	$chip = mcupr_notice_status_chip( mcupr_notice_state( $id ) );
	$dl   = mcupr_notice_deadline_html( $id );
	return '' !== $chip || '' !== $dl ? '<span class="nt-statusline">' . $chip . ( '' !== $dl ? ' ' . $dl : '' ) . '</span>' : '';
}

/** The facts box of the notice page: array of label => html. */
function mcupr_notice_fact_rows( $id ) {
	$i    = mcupr_notice_info( $id );
	$rows = array();
	$st   = mcupr_notice_state( $id );
	if ( '' !== $i['no'] ) {
		$rows[ mcupr_t( 'nt_no' ) ] = esc_html( $i['no'] );
	}
	if ( '' !== $i['method'] ) {
		$rows[ mcupr_t( 'nt_method' ) ] = esc_html( $i['method'] );
	}
	if ( null !== $i['budget'] ) {
		$rows[ mcupr_t( 'nt_budget' ) ] = esc_html( sprintf( mcupr_t( 'nt_baht' ), mcupr_notice_money_text( $i['budget'] ) ) );
	}
	if ( '' !== $i['deadline'] ) {
		$rows[ mcupr_t( 'nt_deadline' ) ] = mcupr_notice_deadline_html( $id, false );
	}
	if ( '' !== $st ) {
		$rows[ mcupr_t( 'nt_status' ) ] = mcupr_notice_status_chip( $st );
	}
	if ( 'awarded' === $st || '' !== $i['winner'] ) {
		if ( '' !== $i['winner'] ) {
			$rows[ mcupr_t( 'nt_winner' ) ] = esc_html( $i['winner'] );
		}
		if ( null !== $i['price'] ) {
			$rows[ mcupr_t( 'nt_price' ) ] = esc_html( sprintf( mcupr_t( 'nt_baht' ), mcupr_notice_money_text( $i['price'] ) ) );
		}
	}
	return $rows;
}

/* ================================================================== the list filter (?ns=) */

/** The status asked for in the list, or ''. */
function mcupr_notice_filter_state() {
	$ns = isset( $_GET['ns'] ) ? sanitize_key( wp_unslash( $_GET['ns'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	return in_array( $ns, mcupr_notice_statuses(), true ) ? $ns : '';
}

/** The meta query for a status (the same rules as mcupr_notice_state()). */
function mcupr_notice_state_query( $state ) {
	$today = mcupr_notice_today();
	$auto  = array( 'key' => '_mcupr_nt_status', 'compare' => 'NOT EXISTS' );
	if ( 'open' === $state ) {
		return array(
			'relation' => 'OR',
			array( 'key' => '_mcupr_nt_status', 'value' => 'open' ),
			array( 'relation' => 'AND', $auto, array( 'key' => '_mcupr_nt_deadline', 'value' => $today, 'compare' => '>=', 'type' => 'CHAR' ) ),
		);
	}
	if ( 'closed' === $state ) {
		return array(
			'relation' => 'OR',
			array( 'key' => '_mcupr_nt_status', 'value' => 'closed' ),
			array( 'relation' => 'AND', $auto, array( 'key' => '_mcupr_nt_deadline', 'value' => $today, 'compare' => '<', 'type' => 'CHAR' )),
		);
	}
	return array( array( 'key' => '_mcupr_nt_status', 'value' => $state ) );
}

add_action(
	'pre_get_posts',
	static function ( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! ( $q->is_post_type_archive( 'mcupr_notice' ) || $q->is_tax( 'mcupr_notice_cat' ) ) ) {
			return;
		}
		$ns = mcupr_notice_filter_state();
		if ( '' !== $ns ) {
			$q->set( 'meta_query', mcupr_notice_state_query( $ns ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
	},
	20
);

/** Does any notice use the status or a deadline? (Otherwise the filter would only be clutter.) */
function mcupr_notice_uses_status() {
	$n = get_posts( array( 'post_type' => 'mcupr_notice', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( 'relation' => 'OR', array( 'key' => '_mcupr_nt_status', 'compare' => 'EXISTS' ), array( 'key' => '_mcupr_nt_deadline', 'compare' => 'EXISTS' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return (bool) $n;
}

/** The filter as a row of pills; $here = the address of the list (a type or all), the search words are kept. */
function mcupr_notice_filter_html( $here ) {
	if ( ! mcupr_notice_uses_status() ) {
		return '';
	}
	$cur = mcupr_notice_filter_state();
	$nq  = mcupr_notice_search_words();
	$o   = '<nav class="nt-filter" aria-label="' . esc_attr( mcupr_t( 'nt_status' ) ) . '"><span class="nt-filter-t">' . esc_html( mcupr_t( 'nt_status' ) ) . '</span><ul class="pill-tabs m-0 list-none">';
	foreach ( array_merge( array( '' ), mcupr_notice_statuses() ) as $s ) {
		$url = add_query_arg( array_filter( array( 'nq' => $nq, 'ns' => $s ) ), $here );
		$o  .= '<li class="contents"><a href="' . esc_url( $url ) . '"' . ( $s === $cur ? ' aria-current="page"' : '' ) . '>' . esc_html( '' === $s ? mcupr_t( 'nt_all_status' ) : mcupr_t( 'nt_st_' . $s ) ) . '</a></li>';
	}
	return $o . '</ul></nav>';
}

/* ================================================================== admin */

add_action(
	'add_meta_boxes_mcupr_notice',
	static function () {
		add_meta_box( 'mcupr_notice_info', __( 'Notice details (procurement)', 'mcupr' ), 'mcupr_notice_info_box', 'mcupr_notice', 'normal', 'high' );
	}
);

function mcupr_notice_info_box( $post ) {
	wp_nonce_field( 'mcupr_notice_info_save', 'mcupr_notice_info_nonce' );
	$i  = mcupr_notice_info( $post->ID );
	$f  = static function ( $label, $html, $hint = '' ) { return '<p><label><strong>' . esc_html( $label ) . '</strong><br>' . $html . '</label>' . ( '' !== $hint ? '<br><span class="description">' . esc_html( $hint ) . '</span>' : '' ) . '</p>'; };
	$in = static function ( $name, $type, $val, $extra = '' ) { return '<input class="widefat" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" ' . $extra . '>'; };
	echo '<p class="description">' . esc_html__( 'All of these are optional. They appear in the table, on the notice page and on the home page, and let visitors filter the list by status.', 'mcupr' ) . '</p>';
	echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:0 1.25rem">';
	echo $f( __( 'Notice or project number', 'mcupr' ), $in( 'mcupr_nt_no', 'text', $i['no'], 'maxlength="80" placeholder="18/2570"' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $f( __( 'Procurement method', 'mcupr' ), $in( 'mcupr_nt_method', 'text', $i['method'], 'maxlength="120" list="mcupr-nt-methods"' ) . '<datalist id="mcupr-nt-methods"><option value="ประกวดราคาอิเล็กทรอนิกส์ (e-bidding)"><option value="ตลาดอิเล็กทรอนิกส์ (e-market)"><option value="วิธีเฉพาะเจาะจง"><option value="วิธีคัดเลือก"><option value="สอบราคา"></datalist>' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $f( __( 'Budget or reference price (baht)', 'mcupr' ), $in( 'mcupr_nt_budget', 'text', null === $i['budget'] ? '' : (string) $i['budget'], 'inputmode="decimal" placeholder="4500000"' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $f( __( 'Closing date (offers or applications)', 'mcupr' ), $in( 'mcupr_nt_deadline', 'date', $i['deadline'] ), __( 'Open until the end of this day, then closed – unless you choose a status below.', 'mcupr' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	$opts = '<option value="">' . esc_html__( 'Automatic (from the closing date)', 'mcupr' ) . '</option>';
	foreach ( mcupr_notice_statuses() as $s ) {
		$opts .= '<option value="' . esc_attr( $s ) . '"' . selected( $i['status'], $s, false ) . '>' . esc_html( array( 'open' => __( 'Open', 'mcupr' ), 'closed' => __( 'Closed', 'mcupr' ), 'awarded' => __( 'Result announced', 'mcupr' ), 'cancelled' => __( 'Cancelled', 'mcupr' ) )[ $s ] ) . '</option>';
	}
	echo $f( __( 'Status', 'mcupr' ), '<select class="widefat" name="mcupr_nt_status">' . $opts . '</select>' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $f( __( 'Winner or selected party', 'mcupr' ), $in( 'mcupr_nt_winner', 'text', $i['winner'], 'maxlength="160"' ), __( 'For a result notice.', 'mcupr' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $f( __( 'Agreed price (baht)', 'mcupr' ), $in( 'mcupr_nt_price', 'text', null === $i['price'] ? '' : (string) $i['price'], 'inputmode="decimal"' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	$st = mcupr_notice_state( $post->ID );
	if ( '' !== $st ) {
		echo '<p class="description">' . esc_html__( 'Visitors see now:', 'mcupr' ) . ' ' . mcupr_notice_status_chip( $st ) . ' ' . mcupr_notice_deadline_html( $post->ID ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

add_action(
	'save_post_mcupr_notice',
	static function ( $post_id ) {
		if ( ! mcupr_can_save_box( $post_id, 'mcupr_notice_info_nonce', 'mcupr_notice_info_save' ) ) {
			return;
		}
		$p   = static function ( $k ) { return isset( $_POST[ $k ] ) ? trim( (string) wp_unslash( $_POST[ $k ] ) ) : ''; }; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		$set = static function ( $key, $val ) use ( $post_id ) {
			if ( null === $val || '' === $val ) {
				delete_post_meta( $post_id, '_mcupr_nt_' . $key );
			} else {
				update_post_meta( $post_id, '_mcupr_nt_' . $key, $val );
			}
		};
		$set( 'no', mb_substr( sanitize_text_field( $p( 'mcupr_nt_no' ) ), 0, 80 ) );
		$set( 'method', mb_substr( sanitize_text_field( $p( 'mcupr_nt_method' ) ), 0, 120 ) );
		$b = mcupr_notice_money( $p( 'mcupr_nt_budget' ) );
		$set( 'budget', null === $b ? '' : (string) $b );
		$d = $p( 'mcupr_nt_deadline' );
		$set( 'deadline', preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $d : '' );
		$s = $p( 'mcupr_nt_status' );
		$set( 'status', in_array( $s, mcupr_notice_statuses(), true ) ? $s : '' );
		$set( 'winner', mb_substr( sanitize_text_field( $p( 'mcupr_nt_winner' ) ), 0, 160 ) );
		$pr = mcupr_notice_money( $p( 'mcupr_nt_price' ) );
		$set( 'price', null === $pr ? '' : (string) $pr );
	}
);

add_filter(
	'manage_mcupr_notice_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_status'] = __( 'Status', 'mcupr' );
		return $cols;
	},
	20
);
add_action(
	'manage_mcupr_notice_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'mcupr_status' === $col ) {
			$st = mcupr_notice_state( $post_id );
			echo '' === $st ? '–' : mcupr_notice_status_chip( $st ) . ' ' . mcupr_notice_deadline_html( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	10,
	2
);
