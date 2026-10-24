<?php
/**
 * Staff hub (ศูนย์บุคลากร): the people of the university connected to the administration chart.
 *
 *  - A public profile page for each person (post type "mcupr_person"): photo, position, unit, contact, short
 *    biography, education, expertise, works and royal decorations (เครื่องราชอิสริยาภรณ์).
 *  - Privacy: every contact item and every section can be hidden per person; defaults are set in the hub;
 *    a profile page can be switched off; a private note is never shown; no ID number / home address field exists.
 *  - A person belongs to a unit of the administration chart (and can be its head): the chart boxes then show the
 *    person's photo, name (linked to the profile) and position.
 *  - Royal decorations come from an editable catalogue: Thai name, abbreviation, rank order, the medal image (upload
 *    your own, or the drawn medal with ribbon / metal colours of your choice).
 *  - Control centre: Staff and executives → Staff hub (overview, checks, catalogue, privacy defaults, sections).
 *
 * Show: [mcupr_people unit="ID" search="1"], [mcupr_honors] (roll of honour), the unit box in [mcupr_orgchart].
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ================================================================== settings */

function mcupr_staff_defaults() {
	return array(
		'profiles'      => 1,
		'default_hide'  => array( 'tel', 'office' ),
		'sections'      => array( 'bio' => 1, 'edu' => 1, 'expertise' => 1, 'works' => 1, 'honors' => 1 ),
		'card_honors'   => 1,
		'org_photo'     => 1,
	);
}

function mcupr_staff_opts() {
	$saved = get_option( 'mcupr_staff', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$d     = mcupr_staff_defaults();
	$o     = array_merge( $d, array_intersect_key( $saved, $d ) );
	$o['sections']     = array_merge( $d['sections'], isset( $saved['sections'] ) && is_array( $saved['sections'] ) ? $saved['sections'] : array() );
	$o['default_hide'] = isset( $saved['default_hide'] ) && is_array( $saved['default_hide'] ) ? array_values( array_intersect( $saved['default_hide'], array_keys( mcupr_staff_privacy_keys() ) ) ) : $d['default_hide'];
	return $o;
}

/** What can be hidden on a profile (contact items and sections). */
function mcupr_staff_privacy_keys() {
	return array(
		'email'  => __( 'Email', 'mcupr' ),
		'tel'    => __( 'Telephone', 'mcupr' ),
		'office' => __( 'Office / room', 'mcupr' ),
		'links'  => __( 'Profile and research links', 'mcupr' ),
		'bio'    => __( 'Biography', 'mcupr' ),
		'edu'    => __( 'Education', 'mcupr' ),
		'works'  => __( 'Works', 'mcupr' ),
		'honors' => __( 'Royal decorations', 'mcupr' ),
	);
}

/** Is this item of the person public? (the person's own choice, else the hub's default for people not yet saved) */
function mcupr_person_shows( $id, $key ) {
	$hide = get_post_meta( $id, '_mcupr_person_hide', true );
	if ( metadata_exists( 'post', $id, '_mcupr_person_hide' ) && is_array( $hide ) ) {
		return ! in_array( $key, $hide, true );
	}
	$o = mcupr_staff_opts();
	return ! in_array( $key, $o['default_hide'], true );
}

/** A year entered as พ.ศ. (>= 2400) or ค.ศ. → Gregorian. */
function mcupr_year_norm( $y ) {
	$y = (int) $y;
	if ( $y >= 2400 ) {
		$y -= 543;
	}
	return ( $y >= 1800 && $y <= 2200 ) ? $y : 0;
}

/** Year as shown: พ.ศ. in Thai, ค.ศ. otherwise. */
/* ================================================================== royal decorations catalogue */

function mcupr_honor_defaults() {
	$e = '#c62828';
	$m = '#1565c0';
	$rows = array(
		array( 'mpc', 'ม.ป.ช.', 'ปรมาภรณ์ช้างเผือก', 'Knight Grand Cross (First Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'mvm', 'ม.ว.ม.', 'ปรมาภรณ์มงกุฎไทย', 'Knight Grand Cross (First Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'pc', 'ป.ช.', 'ประถมาภรณ์ช้างเผือก', 'Knight Grand Cross (Second Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'pm', 'ป.ม.', 'ประถมาภรณ์มงกุฎไทย', 'Knight Grand Cross (Second Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'tc', 'ท.ช.', 'ทวีติยาภรณ์ช้างเผือก', 'Knight Commander (Third Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'tm', 'ท.ม.', 'ทวีติยาภรณ์มงกุฎไทย', 'Knight Commander (Third Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'trc', 'ต.ช.', 'ตริตาภรณ์ช้างเผือก', 'Commander (Fourth Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'trm', 'ต.ม.', 'ตริตาภรณ์มงกุฎไทย', 'Commander (Fourth Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'jc', 'จ.ช.', 'จัตุรถาภรณ์ช้างเผือก', 'Companion (Fifth Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'jm', 'จ.ม.', 'จัตุรถาภรณ์มงกุฎไทย', 'Companion (Fifth Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'bc', 'บ.ช.', 'เบญจมาภรณ์ช้างเผือก', 'Member (Sixth Class) of the Most Exalted Order of the White Elephant', 'w' ),
		array( 'bm', 'บ.ม.', 'เบญจมาภรณ์มงกุฎไทย', 'Member (Sixth Class) of the Most Noble Order of the Crown of Thailand', 'c' ),
		array( 'rtc', 'ร.ท.ช.', 'เหรียญทองช้างเผือก', 'Gold Medal of the Order of the White Elephant', 'w' ),
		array( 'rsc', 'ร.ง.ช.', 'เหรียญเงินช้างเผือก', 'Silver Medal of the Order of the White Elephant', 'w' ),
		array( 'rtm', 'ร.ท.ม.', 'เหรียญทองมงกุฎไทย', 'Gold Medal of the Order of the Crown of Thailand', 'c' ),
		array( 'rsm', 'ร.ง.ม.', 'เหรียญเงินมงกุฎไทย', 'Silver Medal of the Order of the Crown of Thailand', 'c' ),
	);
	$out = array();
	foreach ( $rows as $i => $r ) {
		$silver = in_array( $r[0], array( 'rsc', 'rsm' ), true );
		$out[]  = array(
			'key'   => $r[0],
			'code'  => $r[1],
			'th'    => $r[2],
			'en'    => $r[3],
			'order' => ( $i + 1 ) * 10,
			'img'   => 0,
			'c1'    => 'w' === $r[4] ? $e : $m,
			'c2'    => 'w' === $r[4] ? '#f7f1d8' : '#f2c230',
			'metal' => $silver ? '#b9c0c9' : '#d4a72c',
			'on'    => 1,
		);
	}
	return $out;
}

/** The catalogue as saved (or the defaults), sorted by rank order; key => item. */
function mcupr_honors( $only_on = false ) {
	$saved = get_option( 'mcupr_honors', null );
	$rows  = is_array( $saved ) && $saved ? $saved : mcupr_honor_defaults();
	$out   = array();
	foreach ( $rows as $r ) {
		if ( empty( $r['key'] ) ) {
			continue;
		}
		$r = array_merge( array( 'code' => '', 'th' => '', 'en' => '', 'order' => 999, 'img' => 0, 'c1' => '#c62828', 'c2' => '#f7f1d8', 'metal' => '#d4a72c', 'on' => 1 ), $r );
		if ( $only_on && empty( $r['on'] ) ) {
			continue;
		}
		$out[ $r['key'] ] = $r;
	}
	uasort(
		$out,
		static function ( $a, $b ) {
			return (int) $a['order'] <=> (int) $b['order'];
		}
	);
	return $out;
}

/** Name of a decoration in the visitor's language. */
function mcupr_honor_name( $h ) {
	$lang = mcupr_ui_lang();
	if ( ( 'zh' === $lang || 'ja' === $lang ) && isset( $h['key'] ) ) {
		// a standard decoration whose Thai name was not changed has a Chinese / Japanese name (inc/strings.php, hon_*)
		$k = 'hon_' . $h['key'];
		if ( $k !== mcupr_t( $k, 'th' ) && mcupr_t( $k, 'th' ) === $h['th'] ) {
			return mcupr_t( $k, $lang );
		}
	}
	$en = 'th' !== $lang && '' !== $h['en'];
	return $en ? $h['en'] : $h['th'];
}

/** The medal: the uploaded image, else a drawn medal in the catalogue's colours. */
function mcupr_honor_medal( $h, $size = 56, $class = 'medal' ) {
	if ( ! empty( $h['img'] ) && wp_get_attachment_image_src( (int) $h['img'], 'thumbnail' ) ) {
		return wp_get_attachment_image( (int) $h['img'], 'thumbnail', false, array( 'alt' => '', 'class' => $class . ' medal-img', 'loading' => 'lazy', 'decoding' => 'async', 'style' => 'width:' . (int) $size . 'px;height:auto' ) );
	}
	$c1 = sanitize_hex_color( $h['c1'] ) ? $h['c1'] : '#c62828';
	$c2 = sanitize_hex_color( $h['c2'] ) ? $h['c2'] : '#f7f1d8';
	$mt = sanitize_hex_color( $h['metal'] ) ? $h['metal'] : '#d4a72c';
	$w  = (int) round( $size * 0.75 );
	return '<svg class="' . esc_attr( $class ) . ' medal-svg" width="' . $w . '" height="' . (int) $size . '" viewBox="0 0 48 64" aria-hidden="true" focusable="false">'
		. '<path d="M12 0h12v30l-6 4-6-4z" fill="' . esc_attr( $c1 ) . '"/><path d="M24 0h12v30l-6 4-6-4z" fill="' . esc_attr( $c2 ) . '"/>'
		. '<circle cx="24" cy="44" r="17" fill="' . esc_attr( $mt ) . '" stroke="rgba(0,0,0,.28)" stroke-width="1.5"/><circle cx="24" cy="44" r="12.5" fill="none" stroke="rgba(255,255,255,.55)" stroke-width="1.4"/>'
		. '<path d="M24 36l2.4 5 5.5.7-4 3.8 1 5.4-4.9-2.7-4.9 2.7 1-5.4-4-3.8 5.5-.7z" fill="rgba(255,255,255,.8)"/></svg>';
}

/* ================================================================== a person's data */

function mcupr_person_work_types() {
	return array(
		'research'   => 'wt_research',
		'article'    => 'wt_article',
		'book'       => 'wt_book',
		'innovation' => 'wt_innovation',
		'award'      => 'wt_award',
		'service'    => 'wt_service',
		'other'      => 'wt_other',
	);
}

/** Repeating rows saved as an array of arrays. */
function mcupr_person_rows( $id, $meta ) {
	$v = get_post_meta( $id, '_mcupr_person_' . $meta, true );
	return is_array( $v ) ? $v : array();
}

/** Is a profile page available for this person? */
function mcupr_person_has_profile( $id ) {
	$o = mcupr_staff_opts();
	return ! empty( $o['profiles'] ) && 'off' !== get_post_meta( $id, '_mcupr_person_profile', true ) && 'publish' === get_post_status( $id );
}

/** Where a person's name links to: the profile page, else the old "link" field, else nowhere. */
function mcupr_person_url( $id ) {
	if ( mcupr_person_has_profile( $id ) ) {
		return get_permalink( $id );
	}
	return (string) get_post_meta( $id, '_mcupr_person_url', true );
}

/** The decorations of a person as array( item, year, gazette, note ), highest rank first. */
function mcupr_person_honors( $id ) {
	$cat = mcupr_honors();
	$out = array();
	foreach ( mcupr_person_rows( $id, 'honors' ) as $r ) {
		if ( ! isset( $r['key'], $cat[ $r['key'] ] ) ) {
			continue;
		}
		$out[] = array( 'item' => $cat[ $r['key'] ], 'year' => mcupr_year_norm( isset( $r['year'] ) ? $r['year'] : 0 ), 'gazette' => isset( $r['gazette'] ) ? (string) $r['gazette'] : '', 'note' => isset( $r['note'] ) ? (string) $r['note'] : '' );
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return (int) $a['item']['order'] <=> (int) $b['item']['order'] ?: $a['year'] <=> $b['year'];
		}
	);
	return $out;
}

/** People of the administration chart: unit id => array of person posts (heads first). */
function mcupr_unit_people_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map    = array();
	$people = get_posts(
		array(
			'post_type'      => 'mcupr_person',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => '_mcupr_person_unit', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $people as $p ) {
		$map[ (int) get_post_meta( $p->ID, '_mcupr_person_unit', true ) ][] = $p;
	}
	foreach ( $map as $uid => $list ) {
		usort(
			$list,
			static function ( $a, $b ) {
				return (int) get_post_meta( $b->ID, '_mcupr_person_head', true ) <=> (int) get_post_meta( $a->ID, '_mcupr_person_head', true );
			}
		);
		$map[ $uid ] = $list;
	}
	return $map;
}

function mcupr_unit_people( $unit_id ) {
	$m = mcupr_unit_people_map();
	return isset( $m[ (int) $unit_id ] ) ? $m[ (int) $unit_id ] : array();
}

/* ================================================================== admin: the person's boxes */

function mcupr_staff_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ( 'mcupr_person' !== $screen->post_type && false === strpos( (string) $hook, 'mcupr-staff-hub' ) ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'mcupr-staff-admin', get_theme_file_uri( 'assets/js/staff-admin.js' ), array( 'jquery', 'wp-color-picker' ), MCUPR_VERSION, true );
	wp_add_inline_style( 'wp-color-picker', '.mcs-rows{display:grid;gap:8px}.mcs-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr)) auto;gap:6px;align-items:end;padding:8px;border:1px solid #dcdcde;border-radius:6px;background:#f6f7f7}.mcs-row label{font-size:12px;font-weight:600}.mcs-row input,.mcs-row select{width:100%}.mcs-hub .card{max-width:none;padding:12px 16px}.mcs-stats{display:flex;flex-wrap:wrap;gap:10px;margin:8px 0}.mcs-stat{min-width:140px;padding:10px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.mcs-stat b{display:block;font-size:24px}.mcs-medal{width:44px;height:58px;object-fit:contain;vertical-align:middle}.mcs-ht td{vertical-align:middle}.mcs-ht input[type=text],.mcs-ht input[type=number]{width:100%}.mcs-priv label{display:block;margin:4px 0}' );
}
add_action( 'admin_enqueue_scripts', 'mcupr_staff_admin_assets' );

/** Render a repeater: $schema = array( key => array( label, type [text|number|url|select], options? ) ). */
function mcupr_staff_repeater( $name, $rows, $schema, $add_label ) {
	$row = static function ( $i, $r ) use ( $name, $schema ) {
		echo '<div class="mcs-row">';
		foreach ( $schema as $k => $f ) {
			$val = isset( $r[ $k ] ) ? $r[ $k ] : '';
			$nm  = 'mcupr_staff[' . $name . '][' . $i . '][' . $k . ']';
			echo '<label>' . esc_html( $f[0] ) . '<br>';
			if ( 'select' === $f[1] ) {
				echo '<select name="' . esc_attr( $nm ) . '">';
				foreach ( $f[2] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input type="' . esc_attr( $f[1] ) . '" name="' . esc_attr( $nm ) . '" value="' . esc_attr( $val ) . '"' . ( ! empty( $f[2] ) ? ' placeholder="' . esc_attr( $f[2] ) . '"' : '' ) . '>';
			}
			echo '</label>';
		}
		echo '<button type="button" class="button-link-delete" data-rep-del aria-label="' . esc_attr__( 'Remove this row', 'mcupr' ) . '">&times;</button></div>';
	};
	echo '<div class="mcs-rep" data-rep><div class="mcs-rows" data-rep-rows>';
	foreach ( array_values( $rows ) as $i => $r ) {
		$row( $i, $r );
	}
	echo '</div><p><button type="button" class="button" data-rep-add>' . esc_html( $add_label ) . '</button></p><template data-rep-tpl>';
	$row( '__i__', array() );
	echo '</template></div>';
}

/** Keep only the known keys of each row; drop rows without their main field. */
function mcupr_staff_clean_rows( $rows, $schema, $main ) {
	$out = array();
	foreach ( is_array( $rows ) ? $rows : array() as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$c = array();
		foreach ( $schema as $k => $type ) {
			$v = isset( $r[ $k ] ) ? (string) $r[ $k ] : '';
			if ( 'url' === $type ) {
				$c[ $k ] = esc_url_raw( $v );
			} elseif ( 'int' === $type ) {
				$c[ $k ] = $v ? (int) $v : '';
			} else {
				$c[ $k ] = sanitize_text_field( $v );
			}
		}
		if ( '' !== (string) $c[ $main ] ) {
			$out[] = $c;
		}
	}
	return $out;
}

function mcupr_staff_boxes_add() {
	add_meta_box( 'mcupr_person_profile', __( 'Profile and chart position', 'mcupr' ), 'mcupr_staff_box_profile', 'mcupr_person', 'normal', 'default' );
	add_meta_box( 'mcupr_person_edu', __( 'Education', 'mcupr' ), 'mcupr_staff_box_edu', 'mcupr_person', 'normal', 'default' );
	add_meta_box( 'mcupr_person_works', __( 'Works', 'mcupr' ), 'mcupr_staff_box_works', 'mcupr_person', 'normal', 'default' );
	add_meta_box( 'mcupr_person_honors', __( 'Royal decorations (เครื่องราชอิสริยาภรณ์)', 'mcupr' ), 'mcupr_staff_box_honors', 'mcupr_person', 'normal', 'default' );
	add_meta_box( 'mcupr_person_privacy', __( 'Privacy', 'mcupr' ), 'mcupr_staff_box_privacy', 'mcupr_person', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'mcupr_staff_boxes_add' );

/** Units as options, indented by level. */
function mcupr_staff_unit_options( $selected ) {
	$out = '<option value="0">' . esc_html__( '— none —', 'mcupr' ) . '</option>';
	$walk = static function ( $nodes, $d ) use ( &$walk, &$out, $selected ) {
		foreach ( $nodes as $n ) {
			$out .= '<option value="' . (int) $n['post']->ID . '"' . selected( (int) $selected, (int) $n['post']->ID, false ) . '>' . esc_html( str_repeat( '— ', $d ) . get_the_title( $n['post'] ) ) . '</option>';
			$walk( $n['kids'], $d + 1 );
		}
	};
	$walk( mcupr_org_tree( 0 ), 0 );
	return $out;
}

function mcupr_staff_box_profile( $post ) {
	wp_nonce_field( 'mcupr_staff_save', 'mcupr_staff_nonce' );
	$m  = static function ( $k ) use ( $post ) {
		return get_post_meta( $post->ID, '_mcupr_person_' . $k, true );
	};
	$in = static function ( $label, $name, $val, $type = 'text', $ph = '' ) {
		echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input type="' . esc_attr( $type ) . '" class="widefat" name="mcupr_staff[' . esc_attr( $name ) . ']" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '"></label></p>';
	};
	echo '<p class="description">' . esc_html__( 'Link the person to a unit of the Administration chart: the chart box then shows the photo, the name and the position. Tick “Head of the unit” for the person who leads it. The biography is the main text box above.', 'mcupr' ) . '</p>';
	echo '<p><label><strong>' . esc_html__( 'Unit in the Administration chart', 'mcupr' ) . '</strong><br><select class="widefat" name="mcupr_staff[unit]">' . mcupr_staff_unit_options( (int) $m( 'unit' ) ) . '</select></label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p><label><input type="checkbox" name="mcupr_staff[head]" value="1"' . checked( '1', (string) $m( 'head' ), false ) . '> <strong>' . esc_html__( 'Head of the unit (shown in the chart box)', 'mcupr' ) . '</strong></label></p>';
	$in( __( 'Academic rank / title', 'mcupr' ), 'rank', $m( 'rank' ), 'text', __( 'e.g. Assistant Professor Dr.', 'mcupr' ) );
	$in( __( 'Staff type', 'mcupr' ), 'type', $m( 'type' ), 'text', __( 'e.g. Government officer / University employee / Lecturer', 'mcupr' ) );
	$in( __( 'Office / room', 'mcupr' ), 'office', $m( 'office' ), 'text', __( 'e.g. Building 2, room 201', 'mcupr' ) );
	$in( __( 'Research profile link (Scopus / Google Scholar / ORCID)', 'mcupr' ), 'scholar', $m( 'scholar' ), 'url', 'https://' );
	$in( __( 'Expertise (separate with commas)', 'mcupr' ), 'expertise', $m( 'expertise' ), 'text', __( 'e.g. Buddhist studies, Pali, Ethics', 'mcupr' ) );
}

function mcupr_staff_box_edu( $post ) {
	echo '<p class="description">' . esc_html__( 'One row per degree, newest first.', 'mcupr' ) . '</p>';
	mcupr_staff_repeater(
		'edu',
		mcupr_person_rows( $post->ID, 'edu' ),
		array(
			'level'  => array( __( 'Level', 'mcupr' ), 'text', __( 'Doctorate', 'mcupr' ) ),
			'degree' => array( __( 'Degree and field', 'mcupr' ), 'text', __( 'Ph.D. (Buddhist Studies)', 'mcupr' ) ),
			'inst'   => array( __( 'Institution', 'mcupr' ), 'text' ),
			'year'   => array( __( 'Year', 'mcupr' ), 'number', '2560' ),
		),
		__( '+ Add a degree', 'mcupr' )
	);
}

function mcupr_staff_box_works( $post ) {
	$types = array();
	foreach ( mcupr_person_work_types() as $k => $label ) {
		$types[ $k ] = mcupr_t( $label );
	}
	echo '<p class="description">' . esc_html__( 'Research, articles, books, innovations, awards… shown on the profile grouped by type, newest first. Year: พ.ศ. or ค.ศ.', 'mcupr' ) . '</p>';
	mcupr_staff_repeater(
		'works',
		mcupr_person_rows( $post->ID, 'works' ),
		array(
			'type'  => array( __( 'Type', 'mcupr' ), 'select', $types ),
			'title' => array( __( 'Title', 'mcupr' ), 'text' ),
			'year'  => array( __( 'Year', 'mcupr' ), 'number', '2567' ),
			'url'   => array( __( 'Link', 'mcupr' ), 'url', 'https://' ),
		),
		__( '+ Add a work', 'mcupr' )
	);
}

function mcupr_staff_box_honors( $post ) {
	$cat   = mcupr_honors( true );
	$opts  = array( '' => __( '— choose —', 'mcupr' ) );
	foreach ( $cat as $k => $h ) {
		$opts[ $k ] = $h['code'] . ' · ' . $h['th'];
	}
	echo '<p class="description">' . esc_html__( 'Choose from the catalogue (Staff hub → Royal decorations catalogue, where the medal pictures are edited). Year: พ.ศ. or ค.ศ.', 'mcupr' ) . '</p>';
	mcupr_staff_repeater(
		'honors',
		mcupr_person_rows( $post->ID, 'honors' ),
		array(
			'key'     => array( __( 'Decoration', 'mcupr' ), 'select', $opts ),
			'year'    => array( __( 'Year received', 'mcupr' ), 'number', '2565' ),
			'gazette' => array( __( 'Royal Gazette reference', 'mcupr' ), 'text', __( 'Volume / part / page', 'mcupr' ) ),
			'note'    => array( __( 'Note', 'mcupr' ), 'text' ),
		),
		__( '+ Add a decoration', 'mcupr' )
	);
}

function mcupr_staff_box_privacy( $post ) {
	$o   = mcupr_staff_opts();
	$set = metadata_exists( 'post', $post->ID, '_mcupr_person_hide' ) ? (array) get_post_meta( $post->ID, '_mcupr_person_hide', true ) : $o['default_hide'];
	echo '<div class="mcs-priv"><p class="description">' . esc_html__( 'Tick what must NOT be shown on the website.', 'mcupr' ) . '</p>';
	foreach ( mcupr_staff_privacy_keys() as $k => $label ) {
		echo '<label><input type="checkbox" name="mcupr_staff[hide][]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $set, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '<hr><label><input type="checkbox" name="mcupr_staff[noprofile]" value="1"' . checked( 'off', get_post_meta( $post->ID, '_mcupr_person_profile', true ), false ) . '> <strong>' . esc_html__( 'No profile page (card only)', 'mcupr' ) . '</strong></label>';
	echo '<p><label><strong>' . esc_html__( 'Private note (never shown on the website)', 'mcupr' ) . '</strong><br><textarea class="widefat" rows="3" name="mcupr_staff[note]">' . esc_textarea( get_post_meta( $post->ID, '_mcupr_person_note', true ) ) . '</textarea></label></p></div>';
}

function mcupr_staff_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_staff_nonce', 'mcupr_staff_save' ) ) {
		return;
	}
	$in = isset( $_POST['mcupr_staff'] ) && is_array( $_POST['mcupr_staff'] ) ? wp_unslash( $_POST['mcupr_staff'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$g  = static function ( $k ) use ( $in ) {
		return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
	};
	$unit = (int) $g( 'unit' );
	if ( $unit && 'mcupr_unit' !== get_post_type( $unit ) ) {
		$unit = 0;
	}
	update_post_meta( $post_id, '_mcupr_person_unit', $unit );
	update_post_meta( $post_id, '_mcupr_person_head', '1' === $g( 'head' ) ? 1 : 0 );
	foreach ( array( 'rank', 'type', 'office', 'expertise' ) as $k ) {
		update_post_meta( $post_id, '_mcupr_person_' . $k, sanitize_text_field( $g( $k ) ) );
	}
	update_post_meta( $post_id, '_mcupr_person_scholar', esc_url_raw( $g( 'scholar' ) ) );
	update_post_meta( $post_id, '_mcupr_person_note', sanitize_textarea_field( $g( 'note' ) ) );
	update_post_meta( $post_id, '_mcupr_person_edu', mcupr_staff_clean_rows( isset( $in['edu'] ) ? $in['edu'] : array(), array( 'level' => 'text', 'degree' => 'text', 'inst' => 'text', 'year' => 'int' ), 'degree' ) );
	update_post_meta( $post_id, '_mcupr_person_works', mcupr_staff_clean_rows( isset( $in['works'] ) ? $in['works'] : array(), array( 'type' => 'text', 'title' => 'text', 'year' => 'int', 'url' => 'url' ), 'title' ) );
	$honors = mcupr_staff_clean_rows( isset( $in['honors'] ) ? $in['honors'] : array(), array( 'key' => 'text', 'year' => 'int', 'gazette' => 'text', 'note' => 'text' ), 'key' );
	$cat    = mcupr_honors();
	update_post_meta( $post_id, '_mcupr_person_honors', array_values( array_filter( $honors, static function ( $r ) use ( $cat ) { return isset( $cat[ $r['key'] ] ); } ) ) );
	$types = array_keys( mcupr_person_work_types() );
	$works = get_post_meta( $post_id, '_mcupr_person_works', true );
	foreach ( (array) $works as $i => $w ) {
		if ( ! in_array( $w['type'], $types, true ) ) {
			$works[ $i ]['type'] = 'other';
		}
	}
	update_post_meta( $post_id, '_mcupr_person_works', $works );
	$hide = isset( $in['hide'] ) && is_array( $in['hide'] ) ? array_values( array_intersect( array_map( 'sanitize_key', $in['hide'] ), array_keys( mcupr_staff_privacy_keys() ) ) ) : array();
	update_post_meta( $post_id, '_mcupr_person_hide', $hide );
	if ( '1' === $g( 'noprofile' ) ) {
		update_post_meta( $post_id, '_mcupr_person_profile', 'off' );
	} else {
		delete_post_meta( $post_id, '_mcupr_person_profile' );
	}
}
add_action( 'save_post_mcupr_person', 'mcupr_staff_save' );

/** Admin list: unit, head and decorations columns. */
add_filter(
	'manage_mcupr_person_posts_columns',
	static function ( $cols ) {
		$cols['mcupr_unit']   = __( 'Unit', 'mcupr' );
		$cols['mcupr_honors'] = __( 'Decorations', 'mcupr' );
		return $cols;
	}
);
add_action(
	'manage_mcupr_person_posts_custom_column',
	static function ( $col, $id ) {
		if ( 'mcupr_unit' === $col ) {
			$u = (int) get_post_meta( $id, '_mcupr_person_unit', true );
			echo $u ? esc_html( get_the_title( $u ) ) . ( get_post_meta( $id, '_mcupr_person_head', true ) ? ' ' . mcupr_feather_i( 'star', 13 ) : '' ) : '—';
		}
		if ( 'mcupr_honors' === $col ) {
			$n = count( mcupr_person_rows( $id, 'honors' ) );
			echo $n ? (int) $n : '—';
		}
	},
	10,
	2
);

/** The unit box (admin): the people linked to it. */
add_action(
	'add_meta_boxes_mcupr_unit',
	static function () {
		add_meta_box(
			'mcupr_unit_people',
			__( 'People in this unit', 'mcupr' ),
			static function ( $post ) {
				$list = mcupr_unit_people( $post->ID );
				if ( ! $list ) {
					echo '<p class="description">' . esc_html__( 'No one is linked yet. Open a person (Staff and executives) and choose this unit.', 'mcupr' ) . '</p>';
					return;
				}
				echo '<ul>';
				foreach ( $list as $p ) {
					echo '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>' . ( get_post_meta( $p->ID, '_mcupr_person_head', true ) ? ' ' . mcupr_feather_i( 'star', 13 ) : '' ) . '</li>';
				}
				echo '</ul><p class="description">' . esc_html__( 'Starred = head of the unit (shown in the chart box).', 'mcupr' ) . '</p>';
			},
			'mcupr_unit',
			'side'
		);
	}
);

/* ================================================================== admin: the control centre */

function mcupr_staff_hub_menu() {
	add_submenu_page( 'mcupr', __( 'Staff hub', 'mcupr' ), __( 'Staff hub', 'mcupr' ), 'edit_pages', 'mcupr-staff-hub', 'mcupr_staff_hub_page', 0 );
}
add_action( 'admin_menu', 'mcupr_staff_hub_menu' );

function mcupr_staff_hub_save() {
	if ( ! mcupr_check_request( 'mcupr_hub_nonce', 'mcupr_hub', 'edit_pages' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'mcupr' ), '', array( 'response' => 403 ) );
	}
	$what = isset( $_POST['hub_form'] ) ? sanitize_key( wp_unslash( $_POST['hub_form'] ) ) : '';
	if ( 'settings' === $what ) {
		$keys = array_keys( mcupr_staff_privacy_keys() );
		$hide = isset( $_POST['default_hide'] ) && is_array( $_POST['default_hide'] ) ? array_values( array_intersect( array_map( 'sanitize_key', wp_unslash( $_POST['default_hide'] ) ), $keys ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sec  = array();
		foreach ( array_keys( mcupr_staff_defaults()['sections'] ) as $s ) {
			$sec[ $s ] = isset( $_POST['sections'][ $s ] ) ? 1 : 0;
		}
		update_option(
			'mcupr_staff',
			array(
				'profiles'     => isset( $_POST['profiles'] ) ? 1 : 0,
				'default_hide' => $hide,
				'sections'     => $sec,
				'card_honors'  => isset( $_POST['card_honors'] ) ? 1 : 0,
				'org_photo'    => isset( $_POST['org_photo'] ) ? 1 : 0,
			),
			false
		);
		flush_rewrite_rules( false );
	}
	if ( 'honors' === $what ) {
		if ( isset( $_POST['reset_honors'] ) ) {
			delete_option( 'mcupr_honors' );
		} else {
			$rows = isset( $_POST['honor'] ) && is_array( $_POST['honor'] ) ? wp_unslash( $_POST['honor'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$out  = array();
			$seen = array();
			foreach ( $rows as $r ) {
				if ( ! is_array( $r ) ) {
					continue;
				}
				$key = sanitize_key( isset( $r['key'] ) ? $r['key'] : '' );
				$th  = sanitize_text_field( isset( $r['th'] ) ? $r['th'] : '' );
				if ( '' === $th ) {
					continue;
				}
				if ( '' === $key || isset( $seen[ $key ] ) ) {
					$key = 'x' . substr( md5( $th . count( $out ) ), 0, 6 );
				}
				$seen[ $key ] = true;
				$col = static function ( $v, $d ) {
					$c = sanitize_hex_color( (string) $v );
					return $c ? $c : $d;
				};
				$out[] = array(
					'key'   => $key,
					'code'  => sanitize_text_field( isset( $r['code'] ) ? $r['code'] : '' ),
					'th'    => $th,
					'en'    => sanitize_text_field( isset( $r['en'] ) ? $r['en'] : '' ),
					'order' => (int) ( isset( $r['order'] ) ? $r['order'] : 999 ),
					'img'   => absint( isset( $r['img'] ) ? $r['img'] : 0 ),
					'c1'    => $col( isset( $r['c1'] ) ? $r['c1'] : '', '#c62828' ),
					'c2'    => $col( isset( $r['c2'] ) ? $r['c2'] : '', '#f7f1d8' ),
					'metal' => $col( isset( $r['metal'] ) ? $r['metal'] : '', '#d4a72c' ),
					'on'    => empty( $r['on'] ) ? 0 : 1,
				);
			}
			update_option( 'mcupr_honors', $out, false );
		}
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'mcupr_person', 'page' => 'mcupr-staff-hub', 'saved' => $what ), admin_url( 'edit.php' ) ) );
	exit;
}
add_action( 'admin_post_mcupr_staff_hub', 'mcupr_staff_hub_save' );

/** Checks for the overview: what is missing. */
function mcupr_staff_hub_checks() {
	$people = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'any', 'posts_per_page' => 1000, 'no_found_rows' => true ) );
	$units  = get_posts( array( 'post_type' => 'mcupr_unit', 'post_status' => 'publish', 'posts_per_page' => 500, 'no_found_rows' => true ) );
	$map    = mcupr_unit_people_map();
	$r      = array( 'people' => 0, 'units' => count( $units ), 'no_unit' => array(), 'no_photo' => array(), 'no_head' => array(), 'with_honors' => 0, 'hidden_contact' => 0, 'no_profile' => 0 );
	foreach ( $people as $p ) {
		if ( 'publish' !== $p->post_status ) {
			continue;
		}
		$r['people']++;
		if ( ! (int) get_post_meta( $p->ID, '_mcupr_person_unit', true ) ) {
			$r['no_unit'][] = $p;
		}
		if ( ! has_post_thumbnail( $p ) ) {
			$r['no_photo'][] = $p;
		}
		if ( mcupr_person_rows( $p->ID, 'honors' ) ) {
			$r['with_honors']++;
		}
		if ( ! mcupr_person_shows( $p->ID, 'email' ) || ! mcupr_person_shows( $p->ID, 'tel' ) ) {
			$r['hidden_contact']++;
		}
		if ( 'off' === get_post_meta( $p->ID, '_mcupr_person_profile', true ) ) {
			$r['no_profile']++;
		}
	}
	foreach ( $units as $u ) {
		$heads = array_filter( isset( $map[ $u->ID ] ) ? $map[ $u->ID ] : array(), static function ( $p ) { return (int) get_post_meta( $p->ID, '_mcupr_person_head', true ); } );
		if ( ! $heads && '' === trim( (string) get_post_meta( $u->ID, '_mcupr_unit_person', true ) ) ) {
			$r['no_head'][] = $u;
		}
	}
	return $r;
}

function mcupr_staff_hub_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$o   = mcupr_staff_opts();
	$c   = mcupr_staff_hub_checks();
	$act = admin_url( 'admin-post.php' );
	$list = static function ( $items, $edit = true ) {
		if ( ! $items ) {
			echo '<p>' . mcupr_feather_i( 'check', 14 ) . ' ' . esc_html__( 'Nothing to fix.', 'mcupr' ) . '</p>';
			return;
		}
		echo '<ul style="columns:2;margin:4px 0 0 18px;list-style:disc">';
		foreach ( array_slice( $items, 0, 40 ) as $p ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
		}
		echo '</ul>';
	};
	echo '<div class="wrap mcs-hub"><h1>' . esc_html__( 'Staff hub', 'mcupr' ) . '</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'The control centre for the people of the university: who is where in the Administration chart, what is shown on their public profile, and the catalogue of royal decorations.', 'mcupr' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=mcupr_person' ) ) . '">' . esc_html__( 'Add a person', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_person' ) ) . '">' . esc_html__( 'All people', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=mcupr_unit' ) ) . '">' . esc_html__( 'Administration chart', 'mcupr' ) . '</a></p>';

	// ---- overview
	echo '<div class="card"><h2>' . esc_html__( 'Overview', 'mcupr' ) . '</h2><div class="mcs-stats">';
	foreach ( array(
		__( 'People', 'mcupr' )                     => $c['people'],
		__( 'Units in the chart', 'mcupr' )          => $c['units'],
		__( 'With royal decorations', 'mcupr' )      => $c['with_honors'],
		__( 'With a hidden contact item', 'mcupr' )  => $c['hidden_contact'],
		__( 'Card only (no profile page)', 'mcupr' ) => $c['no_profile'],
	) as $label => $n ) {
		echo '<div class="mcs-stat"><b>' . (int) $n . '</b>' . esc_html( $label ) . '</div>';
	}
	echo '</div><h3>' . esc_html__( 'Units without a head', 'mcupr' ) . '</h3>';
	$list( $c['no_head'] );
	echo '<h3>' . esc_html__( 'People not linked to a unit', 'mcupr' ) . '</h3>';
	$list( $c['no_unit'] );
	echo '<h3>' . esc_html__( 'People without a photo', 'mcupr' ) . '</h3>';
	$list( $c['no_photo'] );
	echo '</div>';

	mcupr_import_card();

	// ---- settings
	echo '<div class="card"><h2>' . esc_html__( 'Profiles and privacy defaults', 'mcupr' ) . '</h2><form method="post" action="' . esc_url( $act ) . '"><input type="hidden" name="action" value="mcupr_staff_hub"><input type="hidden" name="hub_form" value="settings">';
	wp_nonce_field( 'mcupr_hub', 'mcupr_hub_nonce' );
	echo '<p><label><input type="checkbox" name="profiles" value="1"' . checked( ! empty( $o['profiles'] ), true, false ) . '> <strong>' . esc_html__( 'Public profile pages (/profile/name/)', 'mcupr' ) . '</strong></label></p>';
	echo '<p><strong>' . esc_html__( 'Hidden by default for people not saved yet (each person can change it)', 'mcupr' ) . '</strong></p><div class="mcs-priv">';
	foreach ( mcupr_staff_privacy_keys() as $k => $label ) {
		echo '<label><input type="checkbox" name="default_hide[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $o['default_hide'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</div><p><strong>' . esc_html__( 'Sections shown on a profile', 'mcupr' ) . '</strong></p><div class="mcs-priv">';
	$sec_labels = array( 'bio' => __( 'Biography', 'mcupr' ), 'edu' => __( 'Education', 'mcupr' ), 'expertise' => __( 'Expertise', 'mcupr' ), 'works' => __( 'Works', 'mcupr' ), 'honors' => __( 'Royal decorations', 'mcupr' ) );
	foreach ( $sec_labels as $k => $label ) {
		echo '<label><input type="checkbox" name="sections[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $o['sections'][ $k ] ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '</div><p><label><input type="checkbox" name="card_honors" value="1"' . checked( ! empty( $o['card_honors'] ), true, false ) . '> ' . esc_html__( 'Show small medal badges on the people cards', 'mcupr' ) . '</label><br><label><input type="checkbox" name="org_photo" value="1"' . checked( ! empty( $o['org_photo'] ), true, false ) . '> ' . esc_html__( 'Show the head\'s photo in the Administration chart boxes', 'mcupr' ) . '</label></p>';
	submit_button( __( 'Save settings', 'mcupr' ), 'primary', 'submit', false );
	echo '</form></div>';

	// ---- catalogue
	$cat = mcupr_honors();
	echo '<div class="card"><h2>' . esc_html__( 'Royal decorations catalogue', 'mcupr' ) . '</h2><p>' . esc_html__( 'The orders and medals people can be given on their profile. Upload a picture of each medal, or leave it empty to use the drawn medal with the ribbon and metal colours below. Smaller “Order” numbers rank higher. The ribbon colours supplied are only a starting point: set them to match the real decoration.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( $act ) . '"><input type="hidden" name="action" value="mcupr_staff_hub"><input type="hidden" name="hub_form" value="honors">';
	wp_nonce_field( 'mcupr_hub', 'mcupr_hub_nonce' );
	echo '<table class="widefat striped mcs-ht" data-honors><thead><tr><th>' . esc_html__( 'On', 'mcupr' ) . '</th><th>' . esc_html__( 'Medal', 'mcupr' ) . '</th><th>' . esc_html__( 'Abbr.', 'mcupr' ) . '</th><th>' . esc_html__( 'Name (Thai)', 'mcupr' ) . '</th><th>' . esc_html__( 'Name (English)', 'mcupr' ) . '</th><th>' . esc_html__( 'Order', 'mcupr' ) . '</th><th>' . esc_html__( 'Ribbon 1', 'mcupr' ) . '</th><th>' . esc_html__( 'Ribbon 2', 'mcupr' ) . '</th><th>' . esc_html__( 'Metal', 'mcupr' ) . '</th><th></th></tr></thead><tbody data-honor-rows>';
	$row = static function ( $i, $h ) {
		$n = 'honor[' . $i . ']';
		echo '<tr data-honor-row><td><input type="checkbox" name="' . esc_attr( $n ) . '[on]" value="1"' . checked( ! empty( $h['on'] ), true, false ) . '><input type="hidden" name="' . esc_attr( $n ) . '[key]" value="' . esc_attr( $h['key'] ) . '"></td>';
		echo '<td><span data-medal-preview>' . mcupr_honor_medal( $h, 44, 'mcs-medal' ) . '</span><input type="hidden" name="' . esc_attr( $n ) . '[img]" value="' . (int) $h['img'] . '" data-img-id><br><button type="button" class="button button-small" data-pick-img>' . esc_html__( 'Image', 'mcupr' ) . '</button> <button type="button" class="button-link" data-clear-img>' . esc_html__( 'Use drawn medal', 'mcupr' ) . '</button></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td><input type="text" name="' . esc_attr( $n ) . '[code]" value="' . esc_attr( $h['code'] ) . '" size="7"></td><td><input type="text" name="' . esc_attr( $n ) . '[th]" value="' . esc_attr( $h['th'] ) . '"></td><td><input type="text" name="' . esc_attr( $n ) . '[en]" value="' . esc_attr( $h['en'] ) . '"></td><td><input type="number" name="' . esc_attr( $n ) . '[order]" value="' . (int) $h['order'] . '" style="width:70px"></td>';
		foreach ( array( 'c1', 'c2', 'metal' ) as $c ) {
			echo '<td><input type="text" class="mcs-color" name="' . esc_attr( $n ) . '[' . esc_attr( $c ) . ']" value="' . esc_attr( $h[ $c ] ) . '" data-default-color="' . esc_attr( $h[ $c ] ) . '"></td>';
		}
		echo '<td><button type="button" class="button-link-delete" data-honor-del aria-label="' . esc_attr__( 'Remove', 'mcupr' ) . '">&times;</button></td></tr>';
	};
	$i = 0;
	foreach ( $cat as $h ) {
		$row( $i++, $h );
	}
	echo '</tbody></table><template data-honor-tpl>';
	$row( '__i__', array( 'key' => '', 'code' => '', 'th' => '', 'en' => '', 'order' => 500, 'img' => 0, 'c1' => '#c62828', 'c2' => '#f7f1d8', 'metal' => '#d4a72c', 'on' => 1 ) );
	echo '</template><p><button type="button" class="button" data-honor-add>' . esc_html__( '+ Add a decoration or medal', 'mcupr' ) . '</button> ';
	submit_button( __( 'Save catalogue', 'mcupr' ), 'primary', 'submit', false );
	echo ' <button type="submit" name="reset_honors" value="1" class="button" onclick="return confirm(\'' . esc_js( __( 'Restore the standard list? Your changes to the catalogue are lost.', 'mcupr' ) ) . '\')">' . esc_html__( 'Restore the standard list', 'mcupr' ) . '</button></p></form></div>';

	echo '<div class="card"><h2>' . esc_html__( 'Show it on the website', 'mcupr' ) . '</h2><ul style="list-style:disc;margin-left:18px"><li><code>[mcupr_people group="slug" unit="ID" search="1"]</code> — ' . esc_html__( 'cards (cards link to the profile pages)', 'mcupr' ) . '</li><li><code>[mcupr_honors]</code> — ' . esc_html__( 'roll of honour: who received which decoration', 'mcupr' ) . '</li><li><code>[mcupr_orgchart]</code> — ' . esc_html__( 'the chart: boxes show the linked head with photo and a link to the profile', 'mcupr' ) . '</li></ul></div></div>';
}

/* ================================================================== front end */

/** A profile switched off (or all profiles off) is a 404. */
add_action(
	'template_redirect',
	static function () {
		if ( is_singular( 'mcupr_person' ) && ! mcupr_person_has_profile( get_queried_object_id() ) && ! current_user_can( 'edit_post', get_queried_object_id() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

/**
 * The same privacy over REST as on the page itself: WordPress serves /wp-json/wp/v2/mcupr_person to anybody, and the
 * 404 above only covers the page. So a person who asked for no profile page is not given out at all, and a biography
 * the person asked to hide is taken out of the answer. Somebody who may edit the person still sees everything.
 */
add_filter(
	'rest_prepare_mcupr_person',
	static function ( $response, $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}
		if ( ! mcupr_person_shows( $post->ID, 'bio' ) || empty( mcupr_staff_opts()['sections']['bio'] ) ) {
			$data = $response->get_data();
			foreach ( array( 'content', 'excerpt' ) as $k ) {
				if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
					$data[ $k ]['rendered'] = '';
					unset( $data[ $k ]['raw'] );
				}
			}
			$response->set_data( $data );
		}
		return $response;
	},
	10,
	2
);

/** The lists over REST leave out anybody who asked for no profile page. */
add_filter(
	'rest_mcupr_person_query',
	static function ( $args ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => '_mcupr_person_profile', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_mcupr_person_profile', 'value' => 'off', 'compare' => '!=' ),
			);
		}
		return $args;
	}
);

/**
 * Asking REST for one such person is answered the way WordPress answers for a post that is not there. It is done here,
 * after the answer was built, because the route that builds it cannot be given an error without tripping over itself.
 */
add_filter(
	'rest_request_after_callbacks',
	static function ( $response, $handler, $request ) {
		if ( ! preg_match( '#^/wp/v2/mcupr_person/(\\d+)$#', (string) $request->get_route(), $m ) ) {
			return $response;
		}
		$id = (int) $m[1];
		if ( 'mcupr_person' !== get_post_type( $id ) || mcupr_person_has_profile( $id ) || current_user_can( 'edit_post', $id ) ) {
			return $response;
		}
		// the very words and the code WordPress itself uses for a post that is not there, so a reader cannot tell the two apart
		return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	},
	10,
	3
);

/** A person who asked for no profile page is kept out of the sitemap too (the page itself is a 404). */
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $type ) {
		if ( 'mcupr_person' === $type ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => '_mcupr_person_profile', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_mcupr_person_profile', 'value' => 'off', 'compare' => '!=' ),
			);
		}
		return $args;
	},
	10,
	2
);

/** Profile URLs exist from the first load after an update. */
function mcupr_staff_flush_once() {
	mcupr_rewrite_wanted( 'mcupr_rewrite_staff', MCUPR_VERSION ); // one flush for all modules, at the end of init (inc/compat.php)
}

/** [mcupr_honors]: roll of honour – each decoration with the people who received it. */
add_shortcode(
	'mcupr_honors',
	static function () {
		$cat    = mcupr_honors( true );
		$people = get_posts( array( 'post_type' => 'mcupr_person', 'post_status' => 'publish', 'posts_per_page' => 500, 'orderby' => 'menu_order title', 'order' => 'ASC', 'no_found_rows' => true ) );
		$by     = array();
		foreach ( $people as $p ) {
			if ( ! mcupr_person_shows( $p->ID, 'honors' ) ) {
				continue;
			}
			foreach ( mcupr_person_honors( $p->ID ) as $h ) {
				$by[ $h['item']['key'] ][] = array( 'p' => $p, 'year' => $h['year'] );
			}
		}
		if ( ! $by ) {
			return '<p class="text-muted">' . esc_html( mcupr_t( 'no_people' ) ) . '</p>';
		}
		ob_start();
		echo '<div class="honor-roll">';
		foreach ( $cat as $k => $h ) {
			if ( empty( $by[ $k ] ) ) {
				continue;
			}
			echo '<section class="honor-sec"><header class="honor-h">' . mcupr_honor_medal( $h, 52, 'medal' ) . '<div><h3>' . esc_html( mcupr_honor_name( $h ) ) . '</h3><p>' . esc_html( $h['code'] ) . '</p></div></header><ul>'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( $by[ $k ] as $r ) {
				$url = mcupr_person_url( $r['p']->ID );
				echo '<li>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $r['p'] ) ) . '</a>' : esc_html( get_the_title( $r['p'] ) ) ) . ( $r['year'] ? ' <span>' . esc_html( mcupr_year_text( $r['year'] ) ) . '</span>' : '' ) . '</li>';
			}
			echo '</ul></section>';
		}
		echo '</div>';
		return ob_get_clean();
	}
);

/** Schema.org Person for the profile – only what the person shows. */
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'mcupr_person' ) ) {
			return;
		}
		$id = get_queried_object_id();
		$d  = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'worksFor' => array( '@type' => 'Organization', 'name' => mcupr_org_name() ),
		);
		$role = mcupr_person_role( $id );
		if ( '' !== $role ) {
			$d['jobTitle'] = $role;
		}
		if ( has_post_thumbnail( $id ) ) {
			$d['image'] = get_the_post_thumbnail_url( $id, 'medium' );
		}
		if ( mcupr_person_shows( $id, 'email' ) && is_email( get_post_meta( $id, '_mcupr_person_email', true ) ) ) {
			$d['email'] = get_post_meta( $id, '_mcupr_person_email', true );
		}
		if ( mcupr_person_shows( $id, 'tel' ) && get_post_meta( $id, '_mcupr_person_tel', true ) ) {
			$d['telephone'] = get_post_meta( $id, '_mcupr_person_tel', true );
		}
		if ( mcupr_person_shows( $id, 'links' ) && get_post_meta( $id, '_mcupr_person_scholar', true ) ) {
			$d['sameAs'] = array( get_post_meta( $id, '_mcupr_person_scholar', true ) );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	25
);
