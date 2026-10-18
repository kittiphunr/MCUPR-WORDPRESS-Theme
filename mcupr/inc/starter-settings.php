<?php
/**
 * Settings import / export (MCUPR → Import / export settings).
 *
 * The starter *content* file (pages, sample events, forms…) goes in through Tools → Import (WordPress Importer). That
 * importer cannot carry Customizer settings, so this page does it for the theme's own settings: the home page blocks,
 * the numbers block, the switches of the home sections and the redirects. Two files can be read:
 *   • the starter settings that come with the theme (starter/mcupr-starter-settings.json), or
 *   • a file exported from another site (“Download my settings”).
 * Nothing is ever overwritten: a block that already has something typed in is skipped as a whole, a switch that is already
 * set is left as it is, a redirect whose old address already exists is skipped. Every value is cleaned before it is saved
 * and only the keys listed here are accepted, so a file cannot change anything else.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** Is this setting key one the file may carry? Returns array( group, block, n, field ) or null. */
function mcupr_ss_key( $key ) {
	$defs = mcupr_blk_defs();
	if ( preg_match( '/^mcupr_(aud|fig|svc|lnk|abt)_(\d{1,2})_([a-z]+)$/', $key, $m ) ) {
		if ( (int) $m[2] >= 1 && (int) $m[2] <= $defs[ $m[1] ][0] && isset( $defs[ $m[1] ][1][ $m[3] ] ) ) {
			return array( $m[1], $m[1], (int) $m[2], $m[3] );
		}
		return null;
	}
	if ( preg_match( '/^mcupr_(svc)_(title|sub|more|more_url)$/', $key, $m ) || preg_match( '/^mcupr_(lnk)_(title|more|more_url)$/', $key, $m ) || preg_match( '/^mcupr_(abt)_(title|text|button|url)$/', $key, $m ) ) {
		return array( $m[1], $m[1], 0, $m[2] );
	}
	if ( preg_match( '/^mcupr_wx_(lat|lon|place)$/', $key, $m ) ) {
		return array( 'wx', 'wx', 0, $m[1] );
	}
	if ( preg_match( '/^mcupr_num_(students|teachers|faculties|bachelor|master|doctoral|asof)$/', $key, $m ) ) {
		return array( 'num', 'num', 0, $m[1] );
	}
	if ( preg_match( '/^mcupr_((hero_(eyebrow|l1|l2|sub|btn1|btn2)|about|address|cta_title)_(th|en|zh|ja)|hero_btn[12]_url|cta_url|org_tel|org_email)$/', $key ) ) {
		return array( 'home', 'home', 0, $key );
	}
	if ( preg_match( '/^mcupr_home_(numbers|weather|audience|figures|services|links|aboutblock|about|slider|events|media|cats|visit)_on$/', $key ) ) {
		return array( 'toggle', 'toggle', 0, $key );
	}
	return null;
}

/** A value cleaned the way its own Customizer box cleans it. */
function mcupr_ss_clean( $key, $field, $v ) {
	if ( 'toggle' === mcupr_ss_key( $key )[0] ) {
		return ! empty( $v ) && 'false' !== $v && '0' !== $v ? 1 : 0;
	}
	$v = is_scalar( $v ) ? (string) $v : '';
	if ( 'home' === mcupr_ss_key( $key )[0] ) { // hero / about / address / banner texts and addresses of the home page
		if ( preg_match( '/_url$/', $key ) ) {
			return 0 === strpos( $v, 'page:' ) ? 'page:' . sanitize_title( substr( $v, 5 ) ) : esc_url_raw( $v, array( 'http', 'https' ) );
		}
		if ( 'mcupr_org_email' === $key ) {
			return sanitize_email( $v );
		}
		return mb_substr( 0 === strpos( $key, 'mcupr_about_' ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ), 0, 600 );
	}
	if ( 'icon' === $field ) {
		return array_key_exists( $v, mcupr_blk_icon_choices() ) ? $v : '';
	}
	if ( 'url' === $field || 'more_url' === $field ) {
		return 0 === strpos( $v, 'page:' ) ? 'page:' . sanitize_title( substr( $v, 5 ) ) : mcupr_blk_clean_url_setting( $v );
	}
	if ( 0 === strpos( $key, 'mcupr_num_' ) && 'asof' !== $field ) {
		return mcupr_numbers_clean( $v );
	}
	if ( 'lat' === $field || 'lon' === $field ) {
		return preg_match( '/^-?\d{1,3}(\.\d{1,6})?$/', trim( $v ) ) ? trim( $v ) : '';
	}
	if ( 'text' === $field ) {
		return mb_substr( sanitize_textarea_field( $v ), 0, 1000 );
	}
	return mb_substr( sanitize_text_field( $v ), 0, 300 );
}

/** Does the site already have something typed in this group? */
function mcupr_ss_in_use( $group ) {
	if ( in_array( $group, array( 'aud', 'fig', 'svc', 'lnk', 'abt' ), true ) ) {
		if ( mcupr_blk_rows( $group ) ) {
			return true;
		}
		foreach ( array( 'title', 'sub', 'more', 'more_url', 'text', 'button', 'url' ) as $f ) {
			if ( '' !== trim( (string) get_theme_mod( 'mcupr_' . $group . '_' . $f, '' ) ) ) {
				return true;
			}
		}
		return false;
	}
	if ( 'wx' === $group ) {
		return '' !== trim( (string) get_theme_mod( 'mcupr_wx_lat', '' ) ) || '' !== trim( (string) get_theme_mod( 'mcupr_wx_lon', '' ) );
	}
	if ( 'num' === $group ) {
		foreach ( array( 'students', 'teachers', 'faculties', 'bachelor', 'master', 'doctoral' ) as $f ) {
			if ( '' !== trim( (string) get_theme_mod( 'mcupr_num_' . $f, '' ) ) ) {
				return true;
			}
		}
	}
	return false;
}

/** The path of a page by its slug (the page may sit under a parent), or ''. */
function mcupr_ss_resolve( $v ) {
	return 0 === strpos( $v, 'page:' ) ? mcupr_blk_page_url( substr( $v, 5 ) ) : $v;
}

/**
 * Put a settings file's content into the site without overwriting anything.
 * $only: limit to some groups (aud fig svc lnk abt wx num toggle redirects) or null for all.
 * Returns array( added => groups, skipped => groups (already in use), rejected => count, redirects => array( added, skipped ) ).
 */
function mcupr_ss_apply( $data, $only = null ) {
	$rep  = array( 'added' => array(), 'skipped' => array(), 'rejected' => 0, 'redirects' => array( 0, 0 ) );
	$mods = is_array( $data ) && isset( $data['theme_mods'] ) && is_array( $data['theme_mods'] ) ? $data['theme_mods'] : array();
	$want = static function ( $g ) use ( $only ) { return null === $only || in_array( $g, $only, true ); };
	// sort what came into groups, items kept together
	$items = array();
	$plain = array();
	foreach ( $mods as $k => $v ) {
		$k  = (string) $k;
		$kk = mcupr_ss_key( $k );
		if ( ! $kk ) {
			$rep['rejected']++;
			continue;
		}
		list( $g, $blk, $n, $f ) = $kk;
		$val = mcupr_ss_clean( $k, $f, $v );
		if ( 'toggle' === $g ) {
			$plain['toggle'][ $k ] = $val;
		} elseif ( $n ) {
			$items[ $g ][ $n ][ $f ] = $val;
		} elseif ( 'home' === $g ) {
			$plain['home'][ $k ] = $val;
		} else {
			$plain[ $g ][ 'mcupr_' . ( 'wx' === $g || 'num' === $g ? $g : $blk ) . '_' . $f ] = $val;
		}
	}
	$defs = mcupr_blk_defs();
	$groups = array_unique( array_merge( array_keys( $items ), array_keys( $plain ) ) );
	foreach ( $groups as $g ) {
		if ( ! $want( $g ) ) {
			continue;
		}
		if ( 'toggle' === $g ) {
			$set = get_theme_mods();
			$n   = 0;
			foreach ( $plain['toggle'] as $k => $val ) {
				if ( ! array_key_exists( $k, (array) $set ) ) { // a switch that is already set stays as it is
					set_theme_mod( $k, $val );
					$n++;
				}
			}
			if ( $n ) {
				$rep['added'][] = 'toggle';
			}
			continue;
		}
		if ( 'home' === $g ) { // one key at a time: what the site already has stays, the empty ones are filled
			$n = 0;
			foreach ( $plain['home'] as $k => $val ) {
				if ( '' !== trim( (string) get_theme_mod( $k, '' ) ) ) {
					continue;
				}
				if ( preg_match( '/_url$/', $k ) ) {
					$val = mcupr_ss_resolve( $val );
					$val = 0 === strpos( $val, '/' ) ? home_url( $val ) : $val;
				}
				if ( '' !== $val ) {
					set_theme_mod( $k, $val );
					$n++;
				}
			}
			if ( $n ) {
				$rep['added'][] = 'home';
			}
			continue;
		}
		if ( mcupr_ss_in_use( $g ) ) {
			$rep['skipped'][] = $g;
			continue;
		}
		$writes = array();
		if ( isset( $items[ $g ] ) ) {
			ksort( $items[ $g ] );
			$main = 'fig' === $g ? 'num' : 'title';
			$i    = 0;
			foreach ( $items[ $g ] as $it ) {
				if ( '' === ( isset( $it[ $main ] ) ? $it[ $main ] : '' ) ) {
					continue;
				}
				if ( isset( $it['url'] ) ) {
					$it['url'] = mcupr_ss_resolve( $it['url'] );
					if ( '' === $it['url'] ) {
						continue; // the page it points to is not on this site (or no address): leave the card out
					}
				}
				$i++;
				foreach ( array_keys( $defs[ $g ][1] ) as $f ) {
					$writes[ 'mcupr_' . $g . '_' . $i . '_' . $f ] = isset( $it[ $f ] ) ? $it[ $f ] : '';
				}
				if ( $i >= $defs[ $g ][0] ) {
					break;
				}
			}
		}
		if ( isset( $plain[ $g ] ) ) {
			foreach ( $plain[ $g ] as $k => $val ) {
				$val = mcupr_ss_resolve( $val );
				if ( '' !== $val ) {
					$writes[ $k ] = $val;
				}
			}
		}
		if ( in_array( $g, array( 'aud', 'fig', 'svc', 'lnk', 'abt' ), true ) && ! mcupr_ss_has_item( $writes, $g ) ) {
			continue; // headings without any card would show nothing: do not write them
		}
		foreach ( $writes as $k => $val ) {
			set_theme_mod( $k, $val );
		}
		if ( $writes ) {
			$rep['added'][] = $g;
		}
	}
	// redirects: new "from" addresses only
	if ( $want( 'redirects' ) && isset( $data['redirects'] ) && is_array( $data['redirects'] ) && function_exists( 'mcupr_ins_clean_rule' ) ) {
		$rules = mcupr_redirects();
		$have  = wp_list_pluck( $rules, 'from' );
		foreach ( array_slice( $data['redirects'], 0, 200 ) as $r ) {
			if ( ! is_array( $r ) || empty( $r['from'] ) || empty( $r['to'] ) ) {
				continue;
			}
			list( $rule, $err ) = mcupr_ins_clean_rule( $r['from'], $r['to'], isset( $r['code'] ) ? $r['code'] : 301 );
			if ( ! $rule || in_array( $rule['from'], $have, true ) || count( $rules ) >= 500 ) {
				$rep['redirects'][1]++;
				continue;
			}
			$rules[] = $rule;
			$have[]  = $rule['from'];
			$rep['redirects'][0]++;
		}
		if ( $rep['redirects'][0] ) {
			update_option( 'mcupr_redirects', $rules, false );
		}
	}
	return $rep;
}

function mcupr_ss_has_item( $writes, $g ) {
	$main = 'fig' === $g ? 'num' : 'title';
	foreach ( $writes as $k => $v ) {
		if ( preg_match( '/^mcupr_' . $g . '_\d+_' . $main . '$/', $k ) && '' !== $v ) {
			return true;
		}
	}
	return false;
}

/** The settings that come with the theme. */
function mcupr_ss_starter() {
	$f = get_theme_file_path( 'starter/mcupr-starter-settings.json' );
	$d = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
	return is_array( $d ) ? $d : array( 'mcupr_settings' => 1, 'theme_mods' => array(), 'redirects' => array() );
}

/** What this site has now, in the same format (only the keys the format allows, only what is filled in). */
function mcupr_ss_export() {
	$mods = array();
	foreach ( (array) get_theme_mods() as $k => $v ) {
		if ( mcupr_ss_key( (string) $k ) && ( is_bool( $v ) || is_int( $v ) || ( is_string( $v ) && '' !== $v ) ) ) {
			$mods[ $k ] = is_bool( $v ) ? (int) $v : $v;
		}
	}
	ksort( $mods );
	$red = array();
	foreach ( function_exists( 'mcupr_redirects' ) ? mcupr_redirects() : array() as $r ) {
		$red[] = array( 'from' => $r['from'], 'to' => $r['to'], 'code' => (int) $r['code'] );
	}
	return array( 'mcupr_settings' => 1, 'version' => defined( 'MCUPR_VERSION' ) ? MCUPR_VERSION : '', 'exported' => gmdate( 'c' ), 'theme_mods' => $mods, 'redirects' => $red );
}

/** Read an uploaded / typed file's text into the format, or null. */
function mcupr_ss_parse( $text ) {
	$d = json_decode( (string) $text, true, 6 );
	return is_array( $d ) && ( ! empty( $d['mcupr_settings'] ) || ! empty( $d['mcupr_demo'] ) || ! empty( $d['mcupr_demo_offices'] ) ) ? $d : null;
}

/** One line for a result. */
function mcupr_ss_message( $rep ) {
	$names = array( 'aud' => __( 'Audience cards', 'mcupr' ), 'fig' => __( 'Figures strip', 'mcupr' ), 'svc' => __( 'Service cards', 'mcupr' ), 'lnk' => __( 'Education links', 'mcupr' ), 'abt' => __( 'About block', 'mcupr' ), 'wx' => __( 'Weather bar', 'mcupr' ), 'num' => __( 'Numbers', 'mcupr' ), 'home' => __( 'Home page texts', 'mcupr' ), 'toggle' => __( 'Show/hide switches', 'mcupr' ) );
	$map   = static function ( $a ) use ( $names ) { return implode( ', ', array_map( static function ( $g ) use ( $names ) { return isset( $names[ $g ] ) ? $names[ $g ] : $g; }, $a ) ); };
	$out   = array();
	$out[] = $rep['added'] ? sprintf( /* translators: %s: list */ __( 'Added: %s.', 'mcupr' ), $map( $rep['added'] ) ) : __( 'Nothing was added.', 'mcupr' );
	if ( $rep['skipped'] ) {
		$out[] = sprintf( /* translators: %s: list */ __( 'Left as they are (already filled in): %s.', 'mcupr' ), $map( $rep['skipped'] ) );
	}
	if ( $rep['redirects'][0] || $rep['redirects'][1] ) {
		$out[] = sprintf( /* translators: 1: added, 2: skipped */ __( 'Redirects: %1$d added, %2$d skipped (address already used or not usable).', 'mcupr' ), $rep['redirects'][0], $rep['redirects'][1] );
	}
	if ( $rep['rejected'] ) {
		$out[] = sprintf( /* translators: %d: count */ __( '%d entries in the file were ignored (not settings of this theme).', 'mcupr' ), $rep['rejected'] );
	}
	return implode( ' ', $out );
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Import / export settings', 'mcupr' ), __( 'Import / export settings', 'mcupr' ), 'manage_options', 'mcupr-settings-io', 'mcupr_ss_page' );
	}
);

function mcupr_ss_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = get_transient( 'mcupr_ss_msg_' . get_current_user_id() );
	if ( $msg ) {
		delete_transient( 'mcupr_ss_msg_' . get_current_user_id() );
	}
	$form = static function ( $do, $btn, $primary = false, $extra = '', $multipart = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . ' style="margin:.5rem 0"><input type="hidden" name="action" value="mcupr_ss"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
		wp_nonce_field( 'mcupr_ss', 'mcupr_ss_nonce' );
		echo $extra . '<button class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $btn ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
	};
	echo '<style>.mcupr-ss .button{white-space:normal;height:auto;min-height:30px;line-height:1.4;padding:.35rem .8rem;text-align:center;max-width:100%}.mcupr-ss input[type=file]{max-width:100%}.mcupr-ss .card{overflow-wrap:anywhere}</style>';
	echo '<div class="wrap mcupr-ss"><h1>' . esc_html__( 'Import / export settings', 'mcupr' ) . '</h1>';
	if ( $msg ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
	}
	echo '<p style="max-width:62rem">' . esc_html__( 'Two kinds of files bring the theme’s ready-made content into a site. Neither ever overwrites what is already there.', 'mcupr' ) . '</p>';
	echo '<div class="card" style="max-width:62rem"><h2>' . esc_html__( '1. Content file (pages and samples)', 'mcupr' ) . '</h2><p>' . esc_html__( 'Pages and sample items: the add-ons file has only what is new since the first starter file (document centre, events calendar, forum, welcome page samples, internal questionnaire, satisfaction survey, student portal, study plans, donate, quality assurance, and the sample events, works, FAQ, form, poll). WordPress Importer skips anything with the same title and date, so your own pages are not touched and importing twice adds nothing. Menus are not in it: add the new pages to a menu yourself.', 'mcupr' ) . '</p><p>';
	echo '<a class="button" href="' . esc_url( get_theme_file_uri( 'starter/mcupr-starter-addons.xml' ) ) . '" download>' . esc_html__( 'Download the add-ons file (new items only)', 'mcupr' ) . '</a> <a class="button" href="' . esc_url( get_theme_file_uri( 'starter/mcupr-starter.xml' ) ) . '" download>' . esc_html__( 'Download the full starter file (for a new site)', 'mcupr' ) . '</a> <a class="button button-primary" href="' . esc_url( admin_url( 'import.php' ) ) . '">' . esc_html__( 'Open Tools → Import', 'mcupr' ) . '</a></p></div>';
	echo '<div class="card" style="max-width:62rem"><h2>' . esc_html__( '2. Settings file (home page blocks, numbers, redirects)', 'mcupr' ) . '</h2><p>' . esc_html__( 'The home page blocks, the numbers block, the show/hide switches and the redirects. A block that already has something typed in is skipped as a whole, a switch that is already set stays, and a redirect whose old address exists is skipped.', 'mcupr' ) . '</p>';
	$form( 'starter', __( 'Fill the empty parts from the theme’s starter settings', 'mcupr' ), true );
	echo '<h3>' . esc_html__( 'Use a file', 'mcupr' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data"><input type="hidden" name="action" value="mcupr_ss"><input type="hidden" name="do" value="upload">';
	wp_nonce_field( 'mcupr_ss', 'mcupr_ss_nonce' );
	echo '<p><label for="mcupr-ss-file">' . esc_html__( 'Settings file (.json)', 'mcupr' ) . '</label><br><input id="mcupr-ss-file" type="file" name="file" accept=".json,application/json" required> <button class="button">' . esc_html__( 'Import the file (fill only)', 'mcupr' ) . '</button></p></form>';
	echo '<h3>' . esc_html__( 'Download', 'mcupr' ) . '</h3><p>';
	foreach ( array( 'dl_starter' => __( 'Starter settings file', 'mcupr' ), 'dl_mine' => __( 'My settings (to move them to another site)', 'mcupr' ) ) as $do => $label ) {
		echo '<span style="display:inline-block;margin-right:.5rem">';
		$form( $do, $label );
		echo '</span>';
	}
	echo '</p></div>';
	echo '<div class="card" style="max-width:62rem"><h2>' . esc_html__( '3. Home page demo', 'mcupr' ) . '</h2><p>' . esc_html__( 'One click makes every part of the front page work with sample content, so you can edit it into your own: news with pictures (slider, latest news, hero photo), news by category, upcoming events, a video, photo albums, the quick-link tiles, and all the home page settings. Nothing is overwritten: items already made are skipped, settings are filled only where nothing is typed, and the quick-link menu is attached only if that place is free. The pictures are plain coloured gradients: replace them with your own.', 'mcupr' ) . '</p>';
	$form( 'demo', __( 'Make the home page demo', 'mcupr' ), true );
	echo '<p>';
	foreach ( array( 'dl_demo' => __( 'Download the demo file', 'mcupr' ), 'demo_remove' => __( 'Remove the demo items', 'mcupr' ) ) as $do => $label ) {
		echo '<span style="display:inline-block;margin-right:.5rem">';
		$form( $do, $label );
		echo '</span>';
	}
	echo '</p><p class="description">' . esc_html__( '“Remove the demo items” deletes the news, events, video, albums, pictures and menu that the demo made (also if you edited them). The settings stay.', 'mcupr' ) . '</p></div>';
	echo '<div class="card" style="max-width:62rem"><h2>' . esc_html__( '4. Offices demo', 'mcupr' ) . '</h2><p>' . esc_html__( 'Makes three offices as mini-sites inside the website – the campus office, the academic office and the Buddhist college – each with its own colour, head, contact details, news and announcements, events, people, a photo album and pages. Nothing is overwritten; items that already exist are skipped. The offices appear on the home page, at /offices/ and in MCUPR → Offices; add /offices/ to a menu to link to them.', 'mcupr' ) . '</p>';
	$form( 'demo_offices', __( 'Make the offices demo', 'mcupr' ), true );
	echo '<p>';
	foreach ( array( 'dl_demo_offices' => __( 'Download the offices demo file', 'mcupr' ), 'demo_offices_remove' => __( 'Remove the offices demo', 'mcupr' ) ) as $do => $label ) {
		echo '<span style="display:inline-block;margin-right:.5rem">';
		$form( $do, $label );
		echo '</span>';
	}
	echo '</p><p class="description">' . esc_html__( '“Remove the offices demo” deletes the offices and everything the demo made for them (also if you edited it).', 'mcupr' ) . '</p></div></div>';
}

add_action(
	'admin_post_mcupr_ss',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_ss_nonce', 'mcupr_ss', 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$msg = '';
		if ( 'dl_starter' === $do || 'dl_mine' === $do ) {
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mcupr-settings-' . ( 'dl_starter' === $do ? 'starter' : 'mine' ) . '.json"' );
			echo wp_json_encode( 'dl_starter' === $do ? mcupr_ss_starter() : mcupr_ss_export(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		if ( 'dl_demo' === $do ) {
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mcupr-demo-home.json"' );
			echo wp_json_encode( mcupr_demo_data(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		if ( 'dl_demo_offices' === $do ) {
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mcupr-demo-offices.json"' );
			echo wp_json_encode( mcupr_od_data(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		if ( 'demo_offices' === $do ) {
			$d   = mcupr_od_data();
			$msg = $d ? mcupr_od_message( mcupr_od_apply( $d ) ) : __( 'The demo file is missing from the theme.', 'mcupr' );
		} elseif ( 'demo_offices_remove' === $do ) {
			$msg = sprintf( /* translators: %d: items */ __( 'Removed %d demo items.', 'mcupr' ), mcupr_od_remove() );
		} elseif ( 'demo' === $do ) {
			$d   = mcupr_demo_data();
			$msg = $d ? mcupr_demo_message( mcupr_demo_apply( $d ) ) : __( 'The demo file is missing from the theme.', 'mcupr' );
		} elseif ( 'demo_remove' === $do ) {
			$msg = sprintf( /* translators: %d: items */ __( 'Removed %d demo items.', 'mcupr' ), mcupr_demo_remove() );
		} elseif ( 'starter' === $do ) {
			$msg = mcupr_ss_message( mcupr_ss_apply( mcupr_ss_starter() ) );
		} elseif ( 'upload' === $do ) {
			$ok = isset( $_FILES['file']['tmp_name'], $_FILES['file']['size'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) && (int) $_FILES['file']['size'] > 0 && (int) $_FILES['file']['size'] <= 200 * 1024; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$d  = $ok ? mcupr_ss_parse( file_get_contents( $_FILES['file']['tmp_name'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions
			$msg = $d ? ( ! empty( $d['mcupr_demo_offices'] ) ? mcupr_od_message( mcupr_od_apply( $d ) ) : ( ! empty( $d['mcupr_demo'] ) ? mcupr_demo_message( mcupr_demo_apply( $d ) ) : mcupr_ss_message( mcupr_ss_apply( $d ) ) ) ) : __( 'That is not a MCUPR settings file (or it is larger than 200 KB).', 'mcupr' );
		}
		set_transient( 'mcupr_ss_msg_' . get_current_user_id(), $msg, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-settings-io' ) );
		exit;
	}
);
