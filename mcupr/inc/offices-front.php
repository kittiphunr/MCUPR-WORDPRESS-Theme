<?php
/**
 * Offices, the visitor's side: the office header with its tabs (on the office's own pages and on everything that
 * belongs to the office), the office home and its sections, the list of offices ([mcupr_offices], /offices/, the home
 * page block) and the card of one office.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The sections, in the order of the tabs: key => string key of the tab's name. */
function mcupr_office_sections() {
	return array( 'news' => 'of_news', 'events' => 'of_events', 'people' => 'of_people', 'albums' => 'of_albums', 'videos' => 'of_videos', 'contact' => 'of_contact' );
}

/** Which content type fills a section. */
function mcupr_office_section_type( $section ) {
	$map = array( 'news' => 'post', 'events' => 'mcupr_event', 'people' => 'mcupr_person', 'albums' => 'mcupr_album', 'videos' => 'mcupr_video' );
	return isset( $map[ $section ] ) ? $map[ $section ] : '';
}

/** The tabs of an office: list of array( key, label, url, current ). Sections with nothing in them are left out. */
function mcupr_office_tabs( $office_id ) {
	$sec  = is_singular( 'mcupr_office' ) ? mcupr_office_section() : '-';
	$tabs = array( array( 'home', mcupr_t( 'of_overview' ), mcupr_office_url( $office_id ), is_singular( 'mcupr_office' ) && '' === $sec ) );
	foreach ( mcupr_office_sections() as $key => $label ) {
		$type = mcupr_office_section_type( $key );
		if ( $type && ! mcupr_office_has( $office_id, $type ) ) {
			continue;
		}
		if ( 'contact' === $key ) {
			continue; // last, after the pages
		}
		$tabs[] = array( $key, mcupr_t( $label ), mcupr_office_url( $office_id, $key ), $sec === $key );
	}
	$cur = is_page() ? get_queried_object_id() : 0;
	foreach ( mcupr_office_pages( $office_id ) as $p ) {
		$tabs[] = array( 'page-' . $p->ID, get_the_title( $p ), get_permalink( $p ), $cur === $p->ID );
	}
	$tabs[] = array( 'contact', mcupr_t( 'of_contact' ), mcupr_office_url( $office_id, 'contact' ), 'contact' === $sec );
	return $tabs;
}

/** The little round badge: the office's logo, or its first letter on its colour. */
function mcupr_office_badge( $office_id, $size = 56 ) {
	$d = mcupr_office_data( $office_id );
	if ( $d['logo'] && wp_attachment_is_image( $d['logo'] ) ) {
		return '<span class="of-badge of-badge-img" aria-hidden="true">' . wp_get_attachment_image( $d['logo'], array( $size * 2, $size * 2 ), false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</span>';
	}
	$t = trim( preg_replace( '/^(สำนักงาน|สำนักวิชา|สำนัก|วิทยาลัย|ศูนย์|งาน|กอง)/u', '', get_the_title( $office_id ) ) );
	$t = '' === $t ? get_the_title( $office_id ) : $t;
	return '<span class="of-badge" aria-hidden="true">' . esc_html( mb_substr( $t, 0, 1 ) ) . '</span>';
}

/** Inline style that sets the colours of an office. */
function mcupr_office_style( $office_id ) {
	$d = mcupr_office_data( $office_id );
	return '--of:' . $d['color'] . ';--of-on:' . mcupr_office_on_color( $d['color'] );
}

/**
 * The office header: the name and the tabs. "full" on the office's own pages, "slim" above anything that belongs to the
 * office (a news article, a page, an event…). Printed right after the site header.
 */
function mcupr_office_chrome() {
	$oid = mcupr_current_office();
	if ( ! $oid || 'publish' !== get_post_status( $oid ) && ! current_user_can( 'edit_post', $oid ) ) {
		return;
	}
	$full = is_singular( 'mcupr_office' );
	$sec  = $full ? mcupr_office_section() : '-';
	$d    = mcupr_office_data( $oid );
	$name = get_the_title( $oid );
	$h1   = $full && '' === $sec;
	$tag  = $h1 ? 'h1' : 'p';
	$cover = $full ? get_the_post_thumbnail_url( $oid, 'large' ) : '';
	echo '<section class="office-chrome' . ( $cover ? ' has-cover' : '' ) . '" data-variant="' . ( $full ? 'full' : 'slim' ) . '" style="' . esc_attr( mcupr_office_style( $oid ) . ( $cover ? ';--of-cover:url(' . esc_url_raw( $cover ) . ')' : '' ) ) . '" aria-label="' . esc_attr( $name ) . '"><div class="of-band"><div class="wrap">';
	echo '<p class="of-up"><a href="' . esc_url( get_post_type_archive_link( 'mcupr_office' ) ) . '">' . mcupr_feather( 'chevron-left', 14 ) . '<span>' . esc_html( mcupr_t( 'of_offices' ) ) . '</span></a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="of-id">' . mcupr_office_badge( $oid ) . '<div class="min-w-0">'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<' . $tag . ' class="of-name"><a href="' . esc_url( get_permalink( $oid ) ) . '">' . esc_html( $name ) . '</a></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $d['name_en'] ) {
		echo '<p class="of-en" lang="en">' . esc_html( $d['name_en'] ) . '</p>';
	}
	if ( $full && '' === $sec && has_excerpt( $oid ) ) {
		echo '<p class="of-tag">' . esc_html( get_the_excerpt( $oid ) ) . '</p>';
	}
	if ( $full && ( '' !== $d['tel'] || '' !== $d['email'] ) ) {
		echo '<ul class="of-chips">';
		if ( '' !== $d['tel'] ) {
			echo '<li><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['tel'] ) ) . '">' . mcupr_icon( 'phone', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $d['tel'] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( '' !== $d['email'] ) {
			echo '<li><a href="mailto:' . esc_attr( $d['email'] ) . '">' . mcupr_icon( 'mail', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_email' ) ) . ': </span>' . esc_html( $d['email'] ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</ul>';
	}
	echo '</div></div></div></div>';
	$tabs = mcupr_office_tabs( $oid );
	$cur  = '';
	foreach ( $tabs as $t ) {
		if ( $t[3] ) {
			$cur = $t[1];
		}
	}
	// Small screens (JS only): one button that opens the list. Without JS the list stays as it is.
	echo '<nav class="of-tabs" aria-label="' . esc_attr( mcupr_t( 'of_nav' ) ) . '"><div class="wrap"><button type="button" class="of-tabs-btn" aria-expanded="false" aria-controls="of-tabs-list"><span>' . esc_html( mcupr_t( 'of_nav' ) ) . ( '' !== $cur ? ': <b>' . esc_html( $cur ) . '</b>' : '' ) . '</span>' . mcupr_icon( 'chevron', 18 ) . '</button><ul id="of-tabs-list">'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( $tabs as $t ) {
		echo '<li><a href="' . esc_url( $t[2] ) . '"' . ( $t[3] ? ' aria-current="page"' : '' ) . '>' . esc_html( $t[1] ) . '</a></li>';
	}
	echo '</ul></div></nav></section>';
}

/** One office as a card (the list of offices, the home page block). */
function mcupr_office_card( $o, $h = 'h3' ) {
	$h  = 'h2' === $h ? 'h2' : 'h3';
	$d  = mcupr_office_data( $o->ID );
	$ex = has_excerpt( $o ) ? get_the_excerpt( $o ) : wp_trim_words( wp_strip_all_tags( $o->post_content ), 28, '…' );
	$cv = get_the_post_thumbnail_url( $o, 'medium_large' );
	$s  = '<article class="office-card soft-card lift" style="' . esc_attr( mcupr_office_style( $o->ID ) . ( $cv ? ';--of-cover:url(' . esc_url_raw( $cv ) . ')' : '' ) ) . '"><div class="oc-top' . ( $cv ? ' has-cover' : '' ) . '">' . mcupr_office_badge( $o->ID, 48 ) . '</div><div class="oc-body">'; // phpcs:ignore WordPress.Security.EscapeOutput
	$s .= '<' . $h . ' class="oc-name"><a href="' . esc_url( get_permalink( $o ) ) . '">' . esc_html( get_the_title( $o ) ) . '</a></' . $h . '>';
	if ( '' !== $d['name_en'] ) {
		$s .= '<p class="oc-en" lang="en">' . esc_html( $d['name_en'] ) . '</p>';
	}
	if ( '' !== $ex ) {
		$s .= '<p class="oc-ex">' . esc_html( $ex ) . '</p>';
	}
	$s .= '<ul class="oc-meta">';
	if ( '' !== $d['tel'] ) {
		$s .= '<li>' . mcupr_icon( 'phone', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $d['tel'] ) . '</li>';
	}
	if ( '' !== $d['email'] ) {
		$s .= '<li>' . mcupr_icon( 'mail', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_email' ) ) . ': </span>' . esc_html( $d['email'] ) . '</li>';
	}
	$s .= '</ul><p class="oc-go"><a class="oc-link" href="' . esc_url( get_permalink( $o ) ) . '">' . esc_html( mcupr_t( 'of_enter' ) ) . ' <span aria-hidden="true">→</span><span class="sr-only">: ' . esc_html( get_the_title( $o ) ) . '</span></a></p></div></article>';
	return $s;
}

/** The grid of all offices. */
function mcupr_offices_html( $h = 'h3' ) {
	$list = mcupr_offices();
	if ( ! $list ) {
		return '<p class="text-muted">' . esc_html( mcupr_t( 'of_no_offices' ) ) . '</p>';
	}
	$s = '<div class="office-grid">';
	foreach ( $list as $o ) {
		$s .= mcupr_office_card( $o, $h );
	}
	return $s . '</div>';
}

/** [mcupr_offices] */
add_shortcode(
	'mcupr_offices',
	static function () {
		return mcupr_offices_html( 'h3' );
	}
);

/** The home page block (nothing when no office is published or the block is switched off). */
function mcupr_offices_home() {
	if ( ! mcupr_home_on( 'offices', true ) || ! mcupr_offices() ) {
		return '';
	}
	return '<section class="wrap mt-12 blk blk-offices" aria-labelledby="blk-offices-t" data-reveal><div class="mb-6 flex flex-wrap items-center justify-between gap-4"><h2 id="blk-offices-t" class="m-0 flex items-center gap-3 text-2xl font-bold min-[900px]:text-3xl"><span class="stat-ic" aria-hidden="true">' . mcupr_icon( 'cap', 22 ) . '</span>' . esc_html( mcupr_t( 'of_offices' ) ) . '</h2></div>' . mcupr_offices_html( 'h3' ) . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/* ------------------------------------------------------------------ what is inside an office page */

/** The contact / head / social card of an office. */
function mcupr_office_info_html( $oid, $big = false, $title = true ) {
	$d = mcupr_office_data( $oid );
	$o = '<div class="of-info soft-card' . ( $big ? ' of-info-big' : '' ) . '">' . ( $title ? '<h2 class="of-info-t"><span class="of-sec-ic" aria-hidden="true">' . mcupr_feather( 'phone', 20 ) . '</span><span>' . esc_html( mcupr_t( 'of_contact' ) ) . '</span></h2>' : '' ) . '<dl class="of-dl">';
	$row = static function ( $label, $html, $icon = 'info' ) {
		return '<div><dt><span class="of-ic" aria-hidden="true">' . mcupr_icon( $icon, 18 ) . '</span>' . esc_html( $label ) . '</dt><dd>' . $html . '</dd></div>';
	};
	if ( '' !== $d['head'] ) {
		$o .= $row( '' !== $d['head_role'] ? $d['head_role'] : mcupr_t( 'of_head' ), esc_html( $d['head'] ), 'user' );
	}
	if ( '' !== $d['address'] ) {
		$o .= $row( mcupr_t( 'of_address' ), nl2br( esc_html( $d['address'] ) ) . ' <a class="of-map" href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( preg_replace( '/\s+/u', ' ', $d['address'] ) ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( mcupr_t( 'of_map' ) ) . '<span class="sr-only"> (' . esc_html( mcupr_t( 'new_tab' ) ) . ')</span></a>', 'pin' );
	}
	if ( '' !== $d['tel'] ) {
		$o .= $row( mcupr_t( 'about_tel' ), '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['tel'] ) ) . '">' . esc_html( $d['tel'] ) . '</a>', 'phone' );
	}
	if ( '' !== $d['email'] ) {
		$o .= $row( mcupr_t( 'about_email' ), '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a>', 'mail' );
	}
	if ( '' !== $d['hours'] ) {
		$o .= $row( mcupr_t( 'of_hours' ), esc_html( $d['hours'] ), 'clock' );
	}
	$o .= '</dl>';
	$links = array();
	foreach ( array( 'website' => mcupr_t( 'about_web' ), 'facebook' => 'Facebook', 'line' => 'LINE', 'youtube' => 'YouTube' ) as $k => $label ) {
		if ( '' !== $d[ $k ] ) {
			$links[] = '<a class="of-soc" href="' . esc_url( $d[ $k ] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '<span class="sr-only"> (' . esc_html( mcupr_t( 'new_tab' ) ) . ')</span></a>';
		}
	}
	if ( $links ) {
		$o .= '<p class="of-socs">' . implode( '', $links ) . '</p>';
	}
	return $o . '</div>';
}

/** Upcoming or past events of an office. */
function mcupr_office_events( $oid, $when = 'upcoming', $limit = 20 ) {
	$now = mcupr_ev_fmt( 'Y-m-d H:i:s', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$up  = 'upcoming' === $when;
	$q   = new WP_Query(
		mcupr_office_query_args(
			$oid,
			'mcupr_event',
			array(
				'posts_per_page' => $limit,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation'     => 'AND',
					'start_clause' => array( 'key' => '_mcupr_ev_start', 'compare' => 'EXISTS' ),
					'end_clause'   => array( 'key' => '_mcupr_ev_end', 'value' => $now, 'compare' => $up ? '>=' : '<', 'type' => 'CHAR' ),
				),
				'orderby'        => array( 'start_clause' => $up ? 'ASC' : 'DESC' ),
			)
		)
	);
	return $q->posts;
}

function mcupr_office_events_html( $events ) {
	if ( ! $events ) {
		return '';
	}
	ob_start();
	echo '<ul class="ev-list">';
	foreach ( $events as $ev ) {
		mcupr_event_row( $ev );
	}
	echo '</ul>';
	return ob_get_clean();
}

/** A heading + content block of an office page. Empty content gives nothing. */
function mcupr_office_block( $id, $title, $html, $more = '' ) {
	if ( '' === trim( (string) $html ) ) {
		return '';
	}
	$icons = array( 'of-news' => 'file-text', 'of-ev-up' => 'calendar', 'of-events' => 'calendar', 'of-ev-past' => 'clock', 'of-albums' => 'image', 'of-pages' => 'book', 'of-groups' => 'grid' );
	$icon  = isset( $icons[ $id ] ) ? $icons[ $id ] : ( 0 === strpos( $id, 'of-pg-' ) ? 'users' : '' );
	$ic    = '' !== $icon ? '<span class="of-sec-ic" aria-hidden="true">' . mcupr_feather( $icon, 20 ) . '</span>' : '';
	return '<section class="of-sec" aria-labelledby="' . esc_attr( $id ) . '"><div class="of-sec-h"><h2 id="' . esc_attr( $id ) . '" class="of-sec-t">' . $ic . '<span>' . esc_html( $title ) . '</span></h2>' . $more . '</div>' . $html . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

function mcupr_office_more( $oid, $section ) {
	return '<a class="of-more" href="' . esc_url( mcupr_office_url( $oid, $section ) ) . '">' . esc_html( mcupr_t( 'of_all' ) ) . mcupr_feather( 'arrow-right', 15 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** News cards of an office (a loop over a query). */
function mcupr_office_news_html( $q, $heading = 'h3' ) {
	ob_start();
	echo '<div class="grid grid-cols-[repeat(auto-fill,minmax(min(17rem,100%),1fr))] gap-6" data-reveal-group>';
	while ( $q->have_posts() ) {
		$q->the_post();
		get_template_part( 'template-parts/card', null, array( 'heading' => $heading ) );
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}

function mcupr_office_pager( $oid, $section, $current, $total ) {
	if ( $total < 2 ) {
		return '';
	}
	$first = mcupr_office_url( $oid, $section );
	$links = paginate_links(
		array(
			'base'      => mcupr_plain_mode() ? $first . ( false !== strpos( $first, '?' ) ? '&' : '?' ) . 'mcupr_opage=%#%' : trailingslashit( $first ) . 'page/%#%/',
			'format'    => '',
			'current'   => $current,
			'total'     => $total,
			'mid_size'  => 1,
			'type'      => 'list',
			'add_args'  => mcupr_office_pager_args(),
			'prev_text' => '<span aria-hidden="true">‹</span> ' . esc_html( mcupr_t( 'prev' ) ),
			'next_text' => esc_html( mcupr_t( 'next' ) ) . ' <span aria-hidden="true">›</span>',
		)
	);
	$links = preg_replace( array( '#/page/1/(["\'])#', '#(?:&amp;|&\#038;|&|\?)mcupr_opage=1(["\'])#' ), array( '/$1', '$1' ), (string) $links );
	return '<nav class="navigation pagination" aria-label="' . esc_attr( mcupr_t( 'pagination' ) ) . '"><div class="nav-links">' . $links . '</div></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** What the office news list was filtered by (category, work group), kept on the links of the pages. */
function mcupr_office_pager_args() {
	// phpcs:disable WordPress.Security.NonceVerification
	$a = array();
	if ( isset( $_GET['ocat'] ) && '' !== sanitize_title( wp_unslash( $_GET['ocat'] ) ) ) {
		$a['ocat'] = sanitize_title( wp_unslash( $_GET['ocat'] ) );
	}
	if ( isset( $_GET['ogroup'] ) && absint( wp_unslash( $_GET['ogroup'] ) ) ) {
		$a['ogroup'] = absint( wp_unslash( $_GET['ogroup'] ) );
	}
	// phpcs:enable
	return $a;
}

/** How much an office has, for the tiles on its home: array of array( section, label, count ), empty ones left out. */
function mcupr_office_counts( $oid ) {
	$oid = (int) $oid;
	// The numbers are kept (mcupr_office_counts_clear() forgets them when content changes). What restricted
	// content this visitor may not see is part of the key, so nobody sees a number that counts it.
	$hide = function_exists( 'mcupr_aud_hidden_ids' ) ? mcupr_aud_hidden_ids() : array();
	sort( $hide );
	$k   = md5( implode( ',', $hide ) );
	$all = get_transient( 'mcupr_office_counts' );
	$all = is_array( $all ) ? $all : array();
	if ( isset( $all[ $oid ][ $k ] ) && is_array( $all[ $oid ][ $k ] ) ) {
		$nums = $all[ $oid ][ $k ];
	} else {
		$nums = array();
		foreach ( array( 'news', 'events', 'people', 'albums', 'videos' ) as $sec ) {
			$q            = new WP_Query( mcupr_office_query_args( $oid, mcupr_office_section_type( $sec ), array( 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false ) ) );
			$nums[ $sec ] = (int) $q->found_posts;
		}
		$keep = isset( $all[ $oid ] ) && is_array( $all[ $oid ] ) ? array_slice( $all[ $oid ], -19, null, true ) : array();
		$keep[ $k ]  = $nums;
		$all[ $oid ] = $keep;
		set_transient( 'mcupr_office_counts', $all, 12 * HOUR_IN_SECONDS );
	}
	$out = array();
	foreach ( array( 'news' => 'of_news', 'events' => 'of_events', 'people' => 'of_people', 'albums' => 'of_albums', 'videos' => 'of_videos' ) as $sec => $label ) {
		if ( ! empty( $nums[ $sec ] ) ) {
			$out[] = array( $sec, mcupr_t( $label ), (int) $nums[ $sec ] );
		}
	}
	return $out;
}

/** Forget the kept office numbers (any change to content an office can own, or to which office owns it). */
function mcupr_office_counts_clear() {
	delete_transient( 'mcupr_office_counts' );
	delete_transient( 'mcupr_group_counts' ); // the people of each work group (inc/groups.php)
}
add_action(
	'save_post',
	static function ( $id, $post ) {
		if ( $post && in_array( $post->post_type, array_merge( mcupr_office_types(), array( 'mcupr_office' ) ), true ) ) {
			mcupr_office_counts_clear();
		}
	},
	10,
	2
);
add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( $new !== $old && $post && in_array( $post->post_type, array_merge( mcupr_office_types(), array( 'mcupr_office' ) ), true ) ) {
			mcupr_office_counts_clear();
		}
	},
	10,
	3
);
add_action(
	'deleted_post',
	static function ( $id, $post = null ) {
		if ( in_array( $post instanceof WP_Post ? $post->post_type : get_post_type( $id ), array_merge( mcupr_office_types(), array( 'mcupr_office' ) ), true ) ) {
			mcupr_office_counts_clear();
		}
	},
	10,
	2
);
add_action(
	'set_object_terms',
	static function ( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( MCUPR_OFFICE_TAX === $taxonomy ) {
			mcupr_office_counts_clear();
		}
	},
	10,
	4
);
add_action( 'delete_' . MCUPR_OFFICE_TAX, 'mcupr_office_counts_clear' );

/** Print the body of an office page for a section ('' = the office home). */
/** The head of a page that belongs to an office: the capsule back to the office, then the same panel as the section pages. */
function mcupr_office_page_head( $oid, $title, $lead = '', $chips = '' ) {
	$o  = '<p class="of-up of-up-page"><a href="' . esc_url( get_permalink( $oid ) ) . '">' . mcupr_feather( 'chevron-left', 14 ) . '<span>' . esc_html( get_the_title( $oid ) ) . '</span></a></p>';
	$o .= '<header class="of-head"><span class="of-head-ic" aria-hidden="true">' . mcupr_feather( 'file-text', 28 ) . '</span><div class="of-head-t"><h1 class="of-h1">' . esc_html( $title ) . '</h1>';
	if ( '' !== $lead ) {
		$o .= '<p class="of-head-lead">' . esc_html( $lead ) . '</p>';
	}
	if ( '' !== $chips ) {
		$o .= '<p class="of-head-meta">' . $chips . '</p>';
	}
	return $o . '</div></header>';
}

/** The head of a section page of an office: a panel with the icon, the title, one line about it and – when there are items – how many. */
function mcupr_office_head( $title, $icon, $count = null, $lead = '', $unit = 'of_n_unit_items' ) {
	$o = '<header class="of-head"><span class="of-head-ic" aria-hidden="true">' . mcupr_feather( $icon, 28 ) . '</span><div class="of-head-t"><h1 class="of-h1">' . esc_html( $title ) . '</h1>';
	if ( '' !== $lead ) {
		$o .= '<p class="of-head-lead">' . esc_html( $lead ) . '</p>';
	}
	$o .= '</div>';
	if ( null !== $count && $count > 0 ) {
		$o .= '<p class="of-count"><b>' . (int) $count . '</b><span>' . esc_html( mcupr_t( $unit ) ) . '</span></p>';
	}
	return $o . '</header>';
}

function mcupr_office_render( $oid, $section ) {
	$post = get_post( $oid );
	echo '<div class="wrap of-body mt-8 pb-4" style="' . esc_attr( mcupr_office_style( $oid ) ) . '">';
	switch ( $section ) {
		case 'news':
			$page = max( 1, (int) get_query_var( 'mcupr_opage' ) );
			$cat  = isset( $_GET['ocat'] ) ? sanitize_title( wp_unslash( $_GET['ocat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$args = array( 'posts_per_page' => 12, 'paged' => $page, 'no_found_rows' => false );
			if ( '' !== $cat ) {
				$args['category_name'] = $cat;
			}
			$grp = function_exists( 'mcupr_office_news_group' ) ? mcupr_office_news_group( $oid ) : 0; // ?ogroup=<id>: the news of one work group
			if ( $grp ) {
				$args['meta_query'] = array( array( 'key' => '_mcupr_post_group', 'value' => (string) $grp ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			}
			$q = new WP_Query( mcupr_office_query_args( $oid, 'post', $args ) );
			// the categories used by this office's news
			$all  = new WP_Query( mcupr_office_query_args( $oid, 'post', array( 'posts_per_page' => 200, 'fields' => 'ids' ) ) );
			$cats = $all->posts ? wp_get_object_terms( $all->posts, 'category', array( 'orderby' => 'name' ) ) : array();
			echo mcupr_office_head( mcupr_t( 'of_news' ), 'file-text', (int) $q->found_posts, sprintf( mcupr_t( 'of_lead_news' ), get_the_title( $oid ) ), 'of_n_unit_news' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $grp ) {
				echo '<p class="of-filter"><span>' . esc_html( sprintf( mcupr_t( 'gr_news_of' ), get_the_title( $grp ) ) ) . '</span> <a href="' . esc_url( get_permalink( $grp ) ) . '">' . esc_html( mcupr_t( 'gr_back' ) ) . '</a> <a href="' . esc_url( mcupr_office_url( $oid, 'news' ) ) . '">' . esc_html( mcupr_t( 'gr_news_clear' ) ) . '</a></p>';
			}
			if ( count( $cats ) > 1 ) {
				echo '<nav class="mb-6" aria-label="' . esc_attr( mcupr_t( 'news_categories' ) ) . '"><ul class="pill-tabs m-0 list-none"><li class="contents"><a href="' . esc_url( mcupr_office_url( $oid, 'news' ) ) . '"' . ( '' === $cat ? ' aria-current="page"' : '' ) . '>' . esc_html( mcupr_t( 'all_cats' ) ) . '</a></li>';
				foreach ( $cats as $c ) {
					echo '<li class="contents"><a href="' . esc_url( add_query_arg( 'ocat', $c->slug, mcupr_office_url( $oid, 'news' ) ) ) . '"' . ( $cat === $c->slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $c->name ) . '</a></li>';
				}
				echo '</ul></nav>';
			}
			echo $q->have_posts() ? mcupr_office_news_html( $q, 'h2' ) : '<p class="text-muted">' . esc_html( mcupr_t( 'of_none' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo mcupr_office_pager( $oid, 'news', $page, (int) $q->max_num_pages ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'events':
			$up = mcupr_office_events( $oid, 'upcoming', 30 );
			$pa = mcupr_office_events( $oid, 'past', 30 );
			echo mcupr_office_head( mcupr_t( 'of_events' ), 'calendar', count( $up ) + count( $pa ), sprintf( mcupr_t( 'of_lead_events' ), get_the_title( $oid ) ), 'of_n_unit_events' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo mcupr_office_block( 'of-ev-up', mcupr_t( 'of_upcoming' ), mcupr_office_events_html( $up ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo mcupr_office_block( 'of-ev-past', mcupr_t( 'of_past' ), mcupr_office_events_html( $pa ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! $up && ! $pa ) {
				echo '<p class="text-muted">' . esc_html( mcupr_t( 'of_none' ) ) . '</p>';
			}
			break;
		case 'people':
			$people = get_posts( mcupr_office_query_args( $oid, 'mcupr_person', array( 'posts_per_page' => 200, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) );
			echo mcupr_office_head( mcupr_t( 'of_people' ), 'users', count( $people ), sprintf( mcupr_t( 'of_lead_people' ), get_the_title( $oid ) ), 'of_n_unit_people' ); // phpcs:ignore WordPress.Security.EscapeOutput
			$groups = array();
			foreach ( $people as $p ) {
				$t = wp_get_object_terms( $p->ID, 'mcupr_person_group' );
				$k = $t && ! is_wp_error( $t ) ? $t[0]->name : '';
				$groups[ $k ][] = $p;
			}
			if ( ! $groups ) {
				echo '<p class="text-muted">' . esc_html( mcupr_t( 'of_none' ) ) . '</p>';
			}
			$i = 0;
			foreach ( $groups as $name => $list ) {
				$i++;
				ob_start();
				echo '<div class="people-grid people-cols-4">';
				foreach ( $list as $p ) {
					mcupr_person_card( $p );
				}
				echo '</div>';
				echo mcupr_office_block( 'of-pg-' . $i, '' !== $name ? $name : mcupr_t( 'of_people' ), ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			break;
		case 'albums':
		case 'videos':
			$items = get_posts( mcupr_office_query_args( $oid, 'albums' === $section ? 'mcupr_album' : 'mcupr_video', array( 'posts_per_page' => 48, 'orderby' => 'date', 'order' => 'DESC' ) ) );
			echo mcupr_office_head( mcupr_t( 'albums' === $section ? 'of_albums' : 'of_videos' ), 'albums' === $section ? 'image' : 'video', count( $items ), sprintf( mcupr_t( 'albums' === $section ? 'of_lead_albums' : 'of_lead_videos' ), get_the_title( $oid ) ), 'albums' === $section ? 'of_n_unit_albums' : 'of_n_unit_videos' ); // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! $items ) {
				echo '<p class="text-muted">' . esc_html( mcupr_t( 'of_none' ) ) . '</p>';
				break;
			}
			echo '<div class="album-grid">';
			foreach ( $items as $it ) {
				echo 'albums' === $section ? mcupr_album_card( $it, 'h2' ) : mcupr_video_card( $it, 'h2' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div>';
			break;
		case 'contact':
			echo mcupr_office_head( mcupr_t( 'of_contact' ), 'phone', null, sprintf( mcupr_t( 'of_lead_contact' ), get_the_title( $oid ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo mcupr_office_info_html( $oid, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		default:
			$about  = trim( $post->post_content );
			$counts = mcupr_office_counts( $oid );
			if ( $counts ) {
				echo '<ul class="of-stats" aria-label="' . esc_attr( mcupr_t( 'of_nav' ) ) . '">';
				foreach ( $counts as $c ) {
					$ico = array( 'news' => 'file-text', 'events' => 'calendar', 'people' => 'users', 'albums' => 'image', 'videos' => 'video', 'contact' => 'phone' );
					echo '<li><a href="' . esc_url( mcupr_office_url( $oid, $c[0] ) ) . '"><span class="of-stat-ic" aria-hidden="true">' . mcupr_feather( isset( $ico[ $c[0] ] ) ? $ico[ $c[0] ] : 'grid', 22 ) . '</span><span class="of-stat-t"><b>' . (int) $c[2] . '</b><span>' . esc_html( $c[1] ) . '</span></span><span class="of-stat-go" aria-hidden="true">' . mcupr_feather( 'arrow-right', 18 ) . '</span></a></li>';
				}
				echo '</ul>';
			}
			echo '<div class="of-home' . ( $about ? '' : ' of-home-solo' ) . '">';
			if ( $about ) {
				echo '<section class="of-about panel-card min-w-0" aria-labelledby="of-about-t"><h2 id="of-about-t" class="of-sec-t"><span class="of-sec-ic" aria-hidden="true">' . mcupr_feather( 'book', 20 ) . '</span><span>' . esc_html( mcupr_t( 'of_about' ) ) . '</span></h2><div class="reader"' . mcupr_part_lang_attr() . '>' . apply_filters( 'the_content', $post->post_content ) . '</div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo mcupr_office_info_html( $oid ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			echo function_exists( 'mcupr_office_groups_html' ) ? mcupr_office_groups_html( $oid ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- the work groups (inc/groups-front.php)
			$news = new WP_Query( mcupr_office_query_args( $oid, 'post', array( 'posts_per_page' => 6 ) ) );
			if ( $news->have_posts() ) {
				echo mcupr_office_block( 'of-news', mcupr_t( 'of_news' ), mcupr_office_news_html( $news ), mcupr_office_more( $oid, 'news' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo mcupr_office_block( 'of-events', mcupr_t( 'of_upcoming' ), mcupr_office_events_html( mcupr_office_events( $oid, 'upcoming', 4 ) ), mcupr_office_has( $oid, 'mcupr_event' ) ? mcupr_office_more( $oid, 'events' ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
			$albums = get_posts( mcupr_office_query_args( $oid, 'mcupr_album', array( 'posts_per_page' => 3, 'orderby' => 'date', 'order' => 'DESC' ) ) );
			if ( $albums ) {
				ob_start();
				echo '<div class="album-grid">';
				foreach ( $albums as $al ) {
					echo mcupr_album_card( $al, 'h3' ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</div>';
				echo mcupr_office_block( 'of-albums', mcupr_t( 'of_albums' ), ob_get_clean(), mcupr_office_more( $oid, 'albums' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$pages = mcupr_office_pages( $oid );
			if ( $pages ) {
				ob_start();
				echo '<ul class="not-prose m-0 grid list-none gap-4 p-0 min-[600px]:grid-cols-2 min-[1000px]:grid-cols-3">';
				foreach ( $pages as $p ) {
					echo '<li class="contents"><a class="soft-card lift flex items-center gap-4 p-4 text-fg no-underline" href="' . esc_url( get_permalink( $p ) ) . '"><span class="stat-ic" aria-hidden="true">' . mcupr_icon( 'news', 22 ) . '</span><span class="min-w-0"><b class="block">' . esc_html( get_the_title( $p ) ) . '</b><span class="block text-sm text-muted">' . esc_html( wp_trim_words( get_the_excerpt( $p ), 14, '…' ) ) . '</span></span></a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</ul>';
				echo mcupr_office_block( 'of-pages', mcupr_t( 'of_pages' ), ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
	}
	echo '</div>';
}

/** The page title of an office section ("News – Office name"). */
add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_singular( 'mcupr_office' ) && mcupr_office_section() ) {
			$parts['title'] = mcupr_t( mcupr_office_sections()[ mcupr_office_section() ] ) . ' – ' . get_the_title();
		}
		return $parts;
	}
);
