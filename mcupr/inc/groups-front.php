<?php
/**
 * Work groups, the visitor's side: the landing page of a group (single-mcupr_group.php → mcupr_group_render()) and the
 * cards of the groups on the office home (mcupr_office_groups_html()). The data is in groups.php.
 *
 * The page sits under the slim office header (the group carries the office mark, so mcupr_office_chrome() prints it) and
 * takes the office colour (--of / --of-on) for its band, its icons and the chosen tab.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The parts of the page: key => array( string key of the heading, string key of the tab, Feather icon ). */
function mcupr_group_part_names() {
	return array(
		'about'    => array( 'gr_about', 'gr_tab_about', 'info' ),
		'services' => array( 'gr_services', 'gr_tab_services', 'grid' ),
		'stats'    => array( 'gr_stats', 'gr_tab_stats', 'trending-up' ),
		'news'     => array( 'gr_news', 'gr_tab_news', 'bell' ),
		'people'   => array( 'gr_people', 'gr_tab_people', 'users' ),
		'docs'     => array( 'gr_docs', 'gr_tab_docs', 'file-text' ),
		'contact'  => array( 'gr_contact', 'gr_tab_contact', 'phone' ),
	);
}

/** The letter in the round mark: the first letter of the name without "กลุ่มงาน", "งาน"… */
function mcupr_group_initial( $name ) {
	$t = trim( preg_replace( '/^(กลุ่มงาน|กลุ่ม|ฝ่าย|งาน|หน่วย)\s*/u', '', (string) $name ) );
	$t = '' === $t ? (string) $name : $t;
	return mb_substr( $t, 0, 1 );
}

/** The address of the office news of one group. */
function mcupr_group_news_url( $gid ) {
	$oid = mcupr_group_office( $gid );
	return $oid ? add_query_arg( 'ogroup', (int) $gid, mcupr_office_url( $oid, 'news' ) ) : '';
}

/** The heading of a part. */
function mcupr_group_h2( $key ) {
	$n = mcupr_group_part_names()[ $key ];
	return '<h2 id="gl-' . esc_attr( $key ) . '-t" class="gl-h"><span class="gl-ic" aria-hidden="true">' . mcupr_feather( $n[2], 20 ) . '</span>' . esc_html( mcupr_t( $n[0] ) ) . '</h2>';
}

/**
 * The left of the top of the page: where the group belongs, its name (the h1), the one-line description, "start here" when there are
 * services (the tiles beside it) and the chips (hours, telephone, place).
 */
function mcupr_group_band( $gid, array $d, array $secs = array() ) {
	$eye = array();
	if ( $d['office'] ) {
		$eye[] = esc_html( get_the_title( $d['office'] ) );
	}
	if ( '' !== $d['division'] ) {
		$eye[] = esc_html( $d['division'] );
	}
	$o = '<header class="gl-band">';
	if ( $eye ) {
		$o .= '<p class="gl-eye">' . implode( ' · ', $eye ) . '</p>';
	}
	$o .= '<h1 class="gl-name">' . esc_html( get_the_title( $gid ) ) . '</h1>';
	if ( '' !== $d['name_en'] ) {
		$o .= '<p class="gl-sub" lang="en">' . esc_html( $d['name_en'] ) . '</p>';
	}
	if ( has_excerpt( $gid ) ) {
		$o .= '<p class="gl-lead"' . mcupr_part_lang_attr( $gid ) . '>' . esc_html( get_the_excerpt( $gid ) ) . '</p>';
	}
	if ( in_array( 'services', $secs, true ) ) {
		$o .= '<p class="gl-start">' . mcupr_feather( 'arrow-right', 18 ) . '<span>' . esc_html( mcupr_t( 'gr_start' ) ) . '</span></p>';
	}
	$chips = array();
	if ( $d['hours'] ) {
		$chips[] = '<li><span class="gl-chip">' . mcupr_feather( 'clock', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'of_hours' ) ) . ': </span>' . esc_html( $d['hours'][0] ) . '</span></li>';
	}
	if ( '' !== $d['tel'] ) {
		$chips[] = '<li><a class="gl-chip" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['tel'] ) ) . '">' . mcupr_feather( 'phone', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $d['tel'] ) . '</a></li>';
	}
	if ( '' !== $d['place'] ) {
		$chips[] = '<li><span class="gl-chip">' . mcupr_feather( 'map-pin', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'gr_place' ) ) . ': </span>' . esc_html( $d['place'] ) . '</span></li>';
	}
	if ( $chips ) {
		$o .= '<ul class="gl-chips">' . implode( '', $chips ) . '</ul>';
	}
	return $o . '</header>';
}

/** The in-page menu (only for the parts that are shown; the last item, "Ask the staff", goes to the contact part). */
function mcupr_group_tabs( array $secs ) {
	if ( count( $secs ) < 2 ) {
		return '';
	}
	$names = mcupr_group_part_names();
	$o     = '<nav class="gl-tabs" aria-label="' . esc_attr( mcupr_t( 'gr_on_page' ) ) . '" data-gl-tabs><ul>';
	foreach ( $secs as $k ) {
		$o .= '<li><a href="#gl-' . esc_attr( $k ) . '">' . mcupr_feather( $names[ $k ][2], 16 ) . '<span>' . esc_html( mcupr_t( $names[ $k ][1] ) ) . '</span></a></li>';
	}
	if ( in_array( 'contact', $secs, true ) ) {
		$o .= '<li class="gl-ask-li"><a class="gl-ask" href="#gl-contact">' . mcupr_feather( 'help-circle', 16 ) . '<span>' . esc_html( mcupr_t( 'gr_ask' ) ) . '</span></a></li>';
	}
	return $o . '</ul></nav>';
}

function mcupr_group_part_about( $gid ) {
	$post = get_post( $gid );
	return '<div class="gl-card gl-about reader"' . mcupr_part_lang_attr( $gid ) . '>' . apply_filters( 'the_content', $post->post_content ) . '</div>'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/** The figures: big numbers with what they count. */
function mcupr_group_part_stats( array $d ) {
	$o = '<ul class="gl-card gl-figs">';
	foreach ( $d['stats'] as $st ) {
		$o .= '<li><b>' . esc_html( $st['num'] ) . '</b>' . ( '' !== $st['label'] ? '<span>' . esc_html( $st['label'] ) . '</span>' : '' ) . '</li>';
	}
	return $o . '</ul>';
}

function mcupr_group_part_services( array $d ) {
	$o = '<ul class="gl-svc">';
	foreach ( $d['services'] as $s ) {
		$title = '' !== $s['url'] ? '<a class="gl-stretch" href="' . esc_url( $s['url'] ) . '">' . esc_html( $s['title'] ) . '</a>' : esc_html( $s['title'] );
		$o    .= '<li class="gl-card gl-svc-i' . ( '' !== $s['url'] ? ' has-link' : '' ) . '"><span class="gl-svc-ic" aria-hidden="true">' . mcupr_feather( $s['icon'], 22 ) . '</span><h3 class="gl-svc-t">' . $title . '</h3>' . ( '' !== $s['text'] ? '<p class="gl-svc-x">' . esc_html( $s['text'] ) . '</p>' : '' ) . '</li>';
	}
	return $o . '</ul>';
}

/** The little date block of a news item. */
function mcupr_group_date_badge( $ts ) {
	$lang = mcupr_ui_lang();
	$mon  = in_array( $lang, array( 'zh', 'ja' ), true ) ? wp_date( 'Y年n月', $ts ) : wp_date( 'M Y', $ts );
	return '<time class="gl-date" datetime="' . esc_attr( wp_date( 'Y-m-d', $ts ) ) . '"><b>' . esc_html( wp_date( 'j', $ts ) ) . '</b><small>' . esc_html( $mon ) . '</small></time>';
}

function mcupr_group_part_news( $gid ) {
	$o = '<div class="gl-card gl-news"><ul>';
	foreach ( mcupr_group_news( $gid ) as $p ) {
		$ex = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $p ) ), 22, '…' );
		$o .= '<li>' . mcupr_group_date_badge( get_post_timestamp( $p ) ) . '<div class="min-w-0"><h3 class="gl-news-t"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>' . ( '' !== $ex ? '<p>' . esc_html( $ex ) . '</p>' : '' ) . '</div></li>';
	}
	$o .= '</ul><p class="gl-more-p"><a class="gl-more" href="' . esc_url( mcupr_group_news_url( $gid ) ) . '">' . esc_html( mcupr_t( 'gr_news_all' ) ) . mcupr_feather( 'arrow-right', 18 ) . '</a></p></div>';
	return $o;
}

/** One person of the group (the head on a larger card). */
function mcupr_group_person( $p, $head ) {
	$id   = $p->ID;
	$name = get_the_title( $p );
	$role = mcupr_person_role( $id );
	$url  = mcupr_person_url( $id );
	$o    = '<li class="gl-card gl-person' . ( $head ? ' is-head' : '' ) . '">';
	if ( has_post_thumbnail( $p ) ) {
		$o .= get_the_post_thumbnail( $p, 'thumbnail', array( 'alt' => '', 'class' => 'gl-ph', 'loading' => 'lazy', 'decoding' => 'async' ) );
	} else {
		$plain = trim( preg_replace( '/^(ศ\.|รศ\.|ผศ\.|ดร\.|นาย|นางสาว|นาง|พระมหา|พระ|อาจารย์|Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)+\s*/u', '', $name ) );
		$o    .= '<span class="gl-ph gl-ph-in" aria-hidden="true">' . esc_html( mb_substr( '' !== $plain ? $plain : $name, 0, 1 ) ) . '</span>';
	}
	$o .= '<div class="gl-pbody">';
	if ( $head ) {
		$o .= '<p class="gl-badge">' . esc_html( mcupr_t( 'gr_head' ) ) . '</p>';
	}
	$o .= '<h3 class="gl-pn">' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) ) . '</h3>';
	if ( '' !== $role ) {
		$o .= '<p class="gl-pr">' . esc_html( $role ) . '</p>';
	}
	if ( $head ) {
		$mail = mcupr_person_shows( $id, 'email' ) ? (string) get_post_meta( $id, '_mcupr_person_email', true ) : '';
		$tel  = mcupr_person_shows( $id, 'tel' ) ? (string) get_post_meta( $id, '_mcupr_person_tel', true ) : '';
		if ( '' !== $mail || '' !== $tel ) {
			$o .= '<p class="gl-pc">';
			if ( '' !== $mail ) {
				$o .= '<a href="mailto:' . esc_attr( $mail ) . '">' . mcupr_feather( 'mail', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_email' ) ) . ': </span>' . esc_html( $mail ) . '</a>';
			}
			if ( '' !== $tel ) {
				$o .= '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . mcupr_feather( 'phone', 16 ) . '<span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $tel ) . '</a>';
			}
			$o .= '</p>';
		}
	}
	return $o . '</div></li>';
}

function mcupr_group_part_people( $gid ) {
	$o = '<ul class="gl-people">';
	foreach ( mcupr_group_people( $gid ) as $p ) {
		$o .= mcupr_group_person( $p, mcupr_group_is_head( $p->ID ) );
	}
	return $o . '</ul>';
}

function mcupr_group_part_docs( $gid ) {
	$o = '<div class="gl-card gl-docs"><ul>';
	foreach ( mcupr_group_docs( $gid ) as $doc ) {
		$meta = array_filter( array( $doc['kind'], $doc['size'], $doc['time'] ? mcupr_t( 'gr_updated' ) . ' ' . mcupr_date_fmt( 'j M Y', $doc['time'] ) : '' ) );
		$o   .= '<li><span class="gl-doc-ic" aria-hidden="true">' . mcupr_feather( 'file-text', 20 ) . '</span><div class="gl-doc-t"><span class="gl-doc-n">' . esc_html( $doc['title'] ) . '</span>' . ( $meta ? '<small>' . esc_html( implode( ' · ', $meta ) ) . '</small>' : '' ) . '</div>';
		$o   .= '<a class="gl-btn" href="' . esc_url( $doc['url'] ) . '"' . ( $doc['time'] ? ' download' : '' ) . '>' . mcupr_feather( 'download', 16 ) . '<span>' . esc_html( mcupr_t( 'gr_download' ) ) . '</span><span class="sr-only">: ' . esc_html( $doc['title'] . ( '' !== $doc['kind'] ? ' (' . $doc['kind'] . ')' : '' ) ) . '</span></a></li>';
	}
	return $o . '</ul></div>';
}

/** The form of the form builder inside the contact part: its own section headings become h4 (the page has h2 > h3 above them). */
function mcupr_group_form_html( $form_id ) {
	$h = mcupr_fb_html( $form_id );
	return (string) preg_replace( '#<h2 class="fbf-h">(.*?)</h2>#s', '<h4 class="fbf-h">$1</h4>', $h );
}

function mcupr_group_part_contact( array $d ) {
	$cards = '';
	$rows  = '';
	if ( '' !== $d['tel'] ) {
		$rows .= '<li>' . mcupr_feather( 'phone', 18 ) . '<span><span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $d['tel'] ) ) . '">' . esc_html( $d['tel'] ) . '</a></span></li>';
	}
	if ( '' !== $d['email'] ) {
		$rows .= '<li>' . mcupr_feather( 'mail', 18 ) . '<span><span class="sr-only">' . esc_html( mcupr_t( 'about_email' ) ) . ': </span><a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a></span></li>';
	}
	if ( '' !== $d['place'] ) {
		$rows .= '<li>' . mcupr_feather( 'map-pin', 18 ) . '<span><span class="sr-only">' . esc_html( mcupr_t( 'gr_place' ) ) . ': </span>' . esc_html( $d['place'] ) . '</span></li>';
	}
	if ( '' !== $rows ) {
		$cards .= '<div class="gl-card gl-cc"><h3 class="gl-h3">' . esc_html( mcupr_t( 'gr_channels' ) ) . '</h3><ul class="gl-rows">' . $rows . '</ul></div>';
	}
	if ( $d['hours'] ) {
		$cards .= '<div class="gl-card gl-cc"><h3 class="gl-h3">' . esc_html( mcupr_t( 'of_hours' ) ) . '</h3><ul class="gl-hours">';
		foreach ( $d['hours'] as $l ) {
			$cards .= '<li>' . esc_html( $l ) . '</li>';
		}
		$cards .= '</ul></div>';
	}
	if ( mcupr_group_form_ok( $d['form'] ) ) {
		$cards .= '<div class="gl-card gl-cc gl-write is-form"><h3 class="gl-h3">' . esc_html( mcupr_t( 'gr_write' ) ) . '</h3>' . mcupr_group_form_html( $d['form'] ) . '</div>';
	} elseif ( '' !== $d['email'] ) {
		$cards .= '<div class="gl-card gl-cc gl-write"><h3 class="gl-h3">' . esc_html( mcupr_t( 'gr_write' ) ) . '</h3><p class="gl-write-x">' . esc_html( mcupr_t( 'gr_write_lead' ) ) . '</p><a class="gl-btn is-solid" href="mailto:' . esc_attr( $d['email'] ) . '">' . mcupr_feather( 'mail', 16 ) . '<span>' . esc_html( mcupr_t( 'gr_write_btn' ) ) . '</span></a></div>';
	}
	return '<div class="gl-contact">' . $cards . '</div>';
}

/** The whole landing page of a group (under the office header). */
function mcupr_group_render( $gid ) {
	$gid  = (int) $gid;
	$d    = mcupr_group_data( $gid );
	$secs = mcupr_group_sections( $gid );
	echo '<div class="wrap gl mt-6 pb-6"' . ( $d['office'] ? ' style="' . esc_attr( mcupr_office_style( $d['office'] ) ) . '"' : '' ) . '>';
	$html_of = static function ( $k ) use ( $gid, $d ) {
		switch ( $k ) {
			case 'about':
				return mcupr_group_part_about( $gid );
			case 'services':
				return mcupr_group_part_services( $d );
			case 'stats':
				return mcupr_group_part_stats( $d );
			case 'news':
				return mcupr_group_part_news( $gid );
			case 'people':
				return mcupr_group_part_people( $gid );
			case 'docs':
				return mcupr_group_part_docs( $gid );
			default:
				return mcupr_group_part_contact( $d );
		}
	};
	$section = static function ( $k ) use ( $html_of ) {
		return '<section id="gl-' . esc_attr( $k ) . '" class="gl-sec gl-sec-' . esc_attr( $k ) . '" aria-labelledby="gl-' . esc_attr( $k ) . '-t">' . mcupr_group_h2( $k ) . $html_of( $k ) . '</section>';
	};
	// the top: the group on the left, what people come for (the services) as big tiles on the right
	$has_svc = in_array( 'services', $secs, true );
	echo '<div class="gl-top' . ( $has_svc ? ' has-svc' : '' ) . '">' . mcupr_group_band( $gid, $d, $secs ) . ( $has_svc ? $section( 'services' ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo mcupr_group_tabs( $secs ); // phpcs:ignore WordPress.Security.EscapeOutput
	// then three parts side by side (figures, news, people), the text of the group, and the documents beside the contact
	$rest = array_diff( $secs, array( 'services' ) );
	$rows = array(
		'a' => array_values( array_intersect( array( 'stats', 'news', 'people' ), $rest ) ),
		'b' => array_values( array_intersect( array( 'docs', 'contact' ), $rest ) ),
	);
	if ( $rows['a'] ) {
		echo '<div class="gl-rows gl-rows-a n' . count( $rows['a'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $rows['a'] as $k ) {
			echo $section( $k ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
	if ( in_array( 'about', $rest, true ) ) {
		echo $section( 'about' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $rows['b'] ) {
		echo '<div class="gl-rows gl-rows-b n' . count( $rows['b'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $rows['b'] as $k ) {
			echo $section( $k ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}
	if ( ! $secs ) {
		echo '<p class="gl-empty text-muted">' . esc_html( mcupr_t( 'of_none' ) ) . '</p>';
	}
	echo '</div>';
}

/* ------------------------------------------------------------------ the office home: the groups of the office */

/** One group as a card (the office home). $people: how many people it has. */
function mcupr_group_card( $g, $people ) {
	$d   = mcupr_group_data( $g->ID );
	$ex  = has_excerpt( $g ) ? get_the_excerpt( $g ) : '';
	$url = get_permalink( $g );
	$o   = '<li class="gl-gcard"><h3 class="gl-gname"><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $g ) ) . '</a></h3>';
	if ( '' !== $ex ) {
		$o .= '<p class="gl-gex">' . esc_html( $ex ) . '</p>';
	}
	$meta = '';
	if ( $people ) {
		$meta .= '<li>' . mcupr_feather( 'users', 16 ) . '<span>' . esc_html( sprintf( mcupr_t( 'gr_people_n' ), (int) $people ) ) . '</span></li>';
	}
	if ( '' !== $d['tel'] ) {
		$meta .= '<li>' . mcupr_feather( 'phone', 16 ) . '<span><span class="sr-only">' . esc_html( mcupr_t( 'about_tel' ) ) . ': </span>' . esc_html( $d['tel'] ) . '</span></li>';
	}
	if ( '' !== $meta ) {
		$o .= '<ul class="gl-gmeta">' . $meta . '</ul>';
	}
	return $o . '<p class="gl-ggo"><a class="gl-glink" href="' . esc_url( $url ) . '">' . esc_html( mcupr_t( 'gr_enter' ) ) . mcupr_feather( 'arrow-right', 16 ) . '<span class="sr-only">: ' . esc_html( get_the_title( $g ) ) . '</span></a></p></li>';
}

/** The part "Work groups of the office" of the office home ('' when the office has no group). No query per card. */
function mcupr_office_groups_html( $oid ) {
	$groups = mcupr_office_groups( $oid );
	if ( ! $groups ) {
		return '';
	}
	$n   = mcupr_group_people_counts( $oid );
	$by  = array(); // division => groups, in the order the divisions first appear ('' = no division, shown first without a heading)
	foreach ( $groups as $g ) {
		$by[ (string) get_post_meta( $g->ID, '_mcupr_g_division', true ) ][] = $g;
	}
	if ( isset( $by[''] ) && count( $by ) > 1 ) {
		$by = array( '' => $by[''] ) + $by;
	}
	$o = '';
	foreach ( $by as $division => $list ) {
		$o .= '' !== $division ? '<h3 class="gl-div">' . esc_html( $division ) . '</h3>' : '';
		$o .= '<ul class="gl-gcards">';
		foreach ( $list as $g ) {
			$o .= mcupr_group_card( $g, isset( $n[ $g->ID ] ) ? (int) $n[ $g->ID ] : 0 );
		}
		$o .= '</ul>';
	}
	return mcupr_office_block( 'of-groups', mcupr_t( 'gr_groups' ), $o );
}

/** The group asked for in the office news (?ogroup=<id>): a published group of this office, or 0. */
function mcupr_office_news_group( $oid ) {
	$g = isset( $_GET['ogroup'] ) ? absint( wp_unslash( $_GET['ogroup'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	return $g && MCUPR_GROUP === get_post_type( $g ) && 'publish' === get_post_status( $g ) && (int) $oid === mcupr_group_office( $g ) ? $g : 0;
}
