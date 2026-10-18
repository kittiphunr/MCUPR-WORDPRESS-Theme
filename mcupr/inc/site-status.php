<?php
/**
 * Site status (สถานะเว็บไซต์), one admin page "Welcome & maintenance":
 *
 *  - Maintenance mode (โหมดปิดปรับปรุง): visitors get a 503 "back soon" page (not indexed, Retry-After), editors keep
 *    working on the real site and see a bar that says the mode is on. A secret address lets a reviewer in for 12 hours,
 *    an IP list lets an office through, feeds / sitemap / REST are closed to the public while it is on.
 *  - Welcome page (หน้าต้อนรับ) for special occasions: an occasion preset (royal, New Year, Songkran, anniversary,
 *    graduation, mourning, own), picture, text in four languages, dates, how often a visitor sees it, optional countdown.
 *    Shown once on the first visit to the home page (a functional cookie remembers it), never to search robots.
 *  - Mourning (ไว้อาลัย): the whole site in greyscale between two dates.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ settings */

function mcupr_status_defaults() {
	return array(
		'maint'    => array( 'on' => 0, 'title' => array(), 'msg' => array(), 'back' => '', 'ips' => '', 'key' => '', 'image' => 0 ),
		'welcome'  => array( 'on' => 0, 'preset' => 'custom', 'title' => array(), 'msg' => array(), 'btn' => array(), 'image' => 0, 'url' => '', 'from' => '', 'to' => '', 'days' => 1, 'auto' => 0 ),
		'mourning' => array( 'on' => 0, 'from' => '', 'to' => '' ),
	);
}

function mcupr_status() {
	$d = mcupr_status_defaults();
	$s = get_option( 'mcupr_status', array() );
	$s = is_array( $s ) ? $s : array();
	foreach ( $d as $k => $def ) {
		$s[ $k ] = isset( $s[ $k ] ) && is_array( $s[ $k ] ) ? array_merge( $def, $s[ $k ] ) : $def;
	}
	return $s;
}

/** Occasion presets: colours (all dark enough for white text) and a starting text in Thai and English. */
function mcupr_welcome_presets() {
	return array(
		'custom'      => array( __( 'My own', 'mcupr' ), '#1e293b', '#334155', '', '' , '', '' ),
		'royal'       => array( __( 'Royal occasion (birthday, anniversary)', 'mcupr' ), '#0b1d4d', '#1c3a8a', 'ขอเดชะ ขอถวายพระพรชัยมงคล', 'ด้วยเกล้าด้วยกระหม่อม ขอพระองค์ทรงพระเจริญ [ระบุพระนามและวโรกาส]', 'Long may [name] live', 'On the auspicious occasion of [occasion].' ),
		'newyear'     => array( __( 'New Year', 'mcupr' ), '#1f1147', '#6a1b9a', 'สวัสดีปีใหม่', 'ขอให้ทุกท่านมีความสุข สุขภาพแข็งแรง ตลอดปีใหม่นี้', 'Happy New Year', 'Wishing you good health and happiness throughout the year.' ),
		'songkran'    => array( __( 'Songkran', 'mcupr' ), '#043b6b', '#0369a1', 'สุขสันต์วันสงกรานต์', 'ขอให้ทุกท่านมีความสุข เดินทางปลอดภัย', 'Happy Songkran', 'Wishing you joy and a safe journey.' ),
		'anniversary' => array( __( 'University anniversary', 'mcupr' ), '#3b0a1e', '#9f1239', 'ครบรอบวันก่อตั้ง', 'ร่วมเฉลิมฉลองวันคล้ายวันก่อตั้ง [ระบุปีที่ครบรอบ]', 'Founding anniversary', 'Join us in celebrating our anniversary.' ),
		'graduation'  => array( __( 'Graduation', 'mcupr' ), '#3a2a05', '#92400e', 'ขอแสดงความยินดีกับบัณฑิตใหม่', 'ขอแสดงความยินดีกับบัณฑิตทุกท่าน', 'Congratulations, graduates', 'Warmest congratulations to all our graduates.' ),
		'mourning'    => array( __( 'Mourning', 'mcupr' ), '#000000', '#262626', 'ร่วมน้อมแสดงความอาลัย', 'ขอน้อมแสดงความอาลัยต่อการสูญเสียครั้งนี้ [ระบุรายละเอียด]', 'In mourning', 'We express our deepest condolences.' ),
	);
}

function mcupr_status_today() {
	return current_time( 'Y-m-d' );
}

function mcupr_status_in_dates( $from, $to ) {
	$t = mcupr_status_today();
	return ( '' === $from || $t >= $from ) && ( '' === $to || $t <= $to );
}

/** The text of a per-language field in the visitor's language, else Thai, else English. */
function mcupr_status_text( $arr, $fallback = '' ) {
	$arr = is_array( $arr ) ? $arr : array();
	foreach ( array( mcupr_ui_lang(), 'th', 'en' ) as $l ) {
		if ( isset( $arr[ $l ] ) && '' !== trim( $arr[ $l ] ) ) {
			return $arr[ $l ];
		}
	}
	return $fallback;
}

function mcupr_maintenance_end_ts( $m ) {
	if ( empty( $m['back'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $m['back'] ) ) {
		return 0;
	}
	$dt = date_create_from_format( 'Y-m-d\TH:i', $m['back'], wp_timezone() );
	return $dt ? $dt->getTimestamp() : 0;
}

/* ------------------------------------------------------------------ admin page */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'Welcome & maintenance', 'mcupr' ), __( 'Welcome & maintenance', 'mcupr' ), 'manage_options', 'mcupr-status', 'mcupr_status_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false !== strpos( (string) $hook, 'mcupr-status' ) ) {
			wp_enqueue_media();
			wp_add_inline_script( 'media-editor', "document.addEventListener('click',function(e){var b=e.target.closest('[data-pick]');if(!b){return;}e.preventDefault();var f=wp.media({multiple:false,library:{type:'image'}});f.on('select',function(){var a=f.state().get('selection').first().toJSON();document.getElementById(b.getAttribute('data-pick')).value=a.id;var p=document.getElementById(b.getAttribute('data-pick')+'-pv');if(p){p.src=(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url);p.hidden=false;}});f.open();});" );
		}
	}
);

function mcupr_status_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = mcupr_status();
	$m = $s['maint'];
	$w = $s['welcome'];
	$n = $s['mourning'];
	$L = mcupr_ui_languages();
	if ( empty( $m['key'] ) ) {
		$m['key'] = wp_generate_password( 16, false );
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Welcome & maintenance', 'mcupr' ) . '</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	if ( ! empty( $m['on'] ) ) {
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Maintenance mode is ON: visitors see the “back soon” page. You can see the real site because you are logged in.', 'mcupr' ) . '</strong></p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_status_save">';
	wp_nonce_field( 'mcupr_status_save', 'mcupr_status_nonce' );
	$langs = static function ( $name, $vals, $rows, $ph ) use ( $L ) {
		foreach ( $L as $code => $l ) {
			echo '<p><label><span style="display:inline-block;min-width:5rem">' . esc_html( mcupr_admin_lang_name( $l['name'] ) ) . '</span>';
			if ( $rows > 1 ) {
				echo '<textarea name="' . esc_attr( $name ) . '[' . esc_attr( $code ) . ']" rows="' . (int) $rows . '" class="large-text" style="width:min(40rem,100%)" placeholder="' . esc_attr( 'th' === $code ? $ph : '' ) . '">' . esc_textarea( isset( $vals[ $code ] ) ? $vals[ $code ] : '' ) . '</textarea>';
			} else {
				echo '<input type="text" name="' . esc_attr( $name ) . '[' . esc_attr( $code ) . ']" class="regular-text" style="width:min(40rem,100%)" value="' . esc_attr( isset( $vals[ $code ] ) ? $vals[ $code ] : '' ) . '" placeholder="' . esc_attr( 'th' === $code ? $ph : '' ) . '">';
			}
			echo '</label></p>';
		}
	};
	$pick = static function ( $id, $val ) {
		$u = $val ? wp_get_attachment_image_url( $val, 'thumbnail' ) : '';
		echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . (int) $val . '"><img id="' . esc_attr( $id ) . '-pv" src="' . esc_url( $u ) . '" alt="" style="max-width:9rem;height:auto;vertical-align:middle;border-radius:.5rem"' . ( $u ? '' : ' hidden' ) . '> <button type="button" class="button" data-pick="' . esc_attr( $id ) . '">' . esc_html__( 'Choose a picture', 'mcupr' ) . '</button> <label><input type="checkbox" name="' . esc_attr( $id ) . '_clear" value="1"> ' . esc_html__( 'Remove', 'mcupr' ) . '</label>';
	};

	// ----- maintenance
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Maintenance mode', 'mcupr' ) . '</h2><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th>' . esc_html__( 'Status', 'mcupr' ) . '</th><td><label><input type="checkbox" name="m_on" value="1"' . checked( ! empty( $m['on'] ), true, false ) . '> <strong>' . esc_html__( 'Maintenance mode is on', 'mcupr' ) . '</strong></label><p class="description">' . esc_html__( 'Visitors see a “back soon” page (HTTP 503, not indexed by search engines). Logged-in editors still see and edit the real site.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Title', 'mcupr' ) . '</th><td>';
	$langs( 'm_title', $m['title'], 1, 'ขออภัย เว็บไซต์ปิดปรับปรุงชั่วคราว' );
	echo '</td></tr><tr><th>' . esc_html__( 'Message', 'mcupr' ) . '</th><td>';
	$langs( 'm_msg', $m['msg'], 3, 'เรากำลังปรับปรุงระบบ จะกลับมาให้บริการโดยเร็วที่สุด' );
	echo '</td></tr><tr><th><label for="m_back">' . esc_html__( 'Expected back', 'mcupr' ) . '</label></th><td><input id="m_back" type="datetime-local" name="m_back" value="' . esc_attr( $m['back'] ) . '"> <span class="description">' . esc_html__( 'Optional. Shown to visitors and sent to search engines (Retry-After).', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th>' . esc_html__( 'Picture (optional)', 'mcupr' ) . '</th><td>';
	$pick( 'm_image', (int) $m['image'] );
	echo '</td></tr><tr><th><label for="m_ips">' . esc_html__( 'Let these addresses in', 'mcupr' ) . '</label></th><td><textarea id="m_ips" name="m_ips" rows="3" class="large-text" style="width:min(40rem,100%)">' . esc_textarea( $m['ips'] ) . '</textarea><p class="description">' . esc_html__( 'IP addresses, one per line (for example the office). They see the real site.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Reviewer link', 'mcupr' ) . '</th><td><input type="hidden" name="m_key" value="' . esc_attr( $m['key'] ) . '"><code>' . esc_html( home_url( '/?mcupr_preview=' . $m['key'] ) ) . '</code><p class="description">' . esc_html__( 'Anyone who opens this address sees the real site for 12 hours (a cookie). Share it only with people you trust; save to keep it, delete the key text to make a new one.', 'mcupr' ) . '</p> <label><input type="checkbox" name="m_newkey" value="1"> ' . esc_html__( 'Make a new link (the old one stops working)', 'mcupr' ) . '</label></td></tr>';
	echo '<tr><th>' . esc_html__( 'See it', 'mcupr' ) . '</th><td><a class="button" target="_blank" rel="noopener" href="' . esc_url( home_url( '/?mcupr_maint=preview' ) ) . '">' . esc_html__( 'Preview the page', 'mcupr' ) . '</a></td></tr>';
	echo '</tbody></table></div>';

	// ----- welcome
	$presets = mcupr_welcome_presets();
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Welcome page', 'mcupr' ) . '</h2><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th>' . esc_html__( 'Status', 'mcupr' ) . '</th><td><label><input type="checkbox" name="w_on" value="1"' . checked( ! empty( $w['on'] ), true, false ) . '> <strong>' . esc_html__( 'Show the welcome page', 'mcupr' ) . '</strong></label><p class="description">' . esc_html__( 'A full-screen page shown on the first visit to the home page, with a button into the website. Search engines never see it.', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th><label for="w_preset">' . esc_html__( 'Occasion', 'mcupr' ) . '</label></th><td><select id="w_preset" name="w_preset">';
	foreach ( $presets as $k => $p ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $w['preset'], $k, false ) . '>' . esc_html( $p[0] ) . '</option>';
	}
	echo '</select><p class="description">' . esc_html__( 'Sets the colours, and the text below if you leave it empty (replace the [brackets]).', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Title', 'mcupr' ) . '</th><td>';
	$langs( 'w_title', $w['title'], 1, '' );
	echo '</td></tr><tr><th>' . esc_html__( 'Message', 'mcupr' ) . '</th><td>';
	$langs( 'w_msg', $w['msg'], 3, '' );
	echo '</td></tr><tr><th>' . esc_html__( 'Button text', 'mcupr' ) . '</th><td>';
	$langs( 'w_btn', $w['btn'], 1, 'เข้าสู่เว็บไซต์' );
	echo '</td></tr><tr><th>' . esc_html__( 'Picture', 'mcupr' ) . '</th><td>';
	$pick( 'w_image', (int) $w['image'] );
	echo '<p class="description">' . esc_html__( 'Fills the screen with a dark layer on top so the text stays readable. Use a large picture (at least 1600 px wide).', 'mcupr' ) . '</p></td></tr>';
	echo '<tr><th><label for="w_url">' . esc_html__( 'Button goes to', 'mcupr' ) . '</label></th><td><input id="w_url" type="url" class="regular-text" name="w_url" value="' . esc_attr( $w['url'] ) . '" placeholder="' . esc_attr( home_url( '/' ) ) . '"> <span class="description">' . esc_html__( 'Empty = the home page.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th>' . esc_html__( 'Dates', 'mcupr' ) . '</th><td><label>' . esc_html__( 'From', 'mcupr' ) . ' <input type="date" name="w_from" value="' . esc_attr( $w['from'] ) . '"></label> <label>' . esc_html__( 'to', 'mcupr' ) . ' <input type="date" name="w_to" value="' . esc_attr( $w['to'] ) . '"></label> <span class="description">' . esc_html__( 'Optional. Outside these days the page is not shown even if it is switched on.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th><label for="w_days">' . esc_html__( 'Show again after', 'mcupr' ) . '</label></th><td><input id="w_days" type="number" min="0" max="365" name="w_days" value="' . (int) $w['days'] . '"> <span class="description">' . esc_html__( 'days (0 = once each time the browser is opened).', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th><label for="w_auto">' . esc_html__( 'Go in automatically after', 'mcupr' ) . '</label></th><td><input id="w_auto" type="number" min="0" max="60" name="w_auto" value="' . (int) $w['auto'] . '"> <span class="description">' . esc_html__( 'seconds (0 = the visitor presses the button). A visible countdown with a stop button is shown.', 'mcupr' ) . '</span></td></tr>';
	echo '<tr><th>' . esc_html__( 'See it', 'mcupr' ) . '</th><td><a class="button" target="_blank" rel="noopener" href="' . esc_url( home_url( '/?mcupr_welcome=preview' ) ) . '">' . esc_html__( 'Preview the page (save first)', 'mcupr' ) . '</a></td></tr>';
	echo '</tbody></table></div>';

	// ----- mourning
	echo '<div class="card" style="max-width:none"><h2>' . esc_html__( 'Mourning (greyscale)', 'mcupr' ) . '</h2><table class="form-table" role="presentation"><tbody><tr><th>' . esc_html__( 'Status', 'mcupr' ) . '</th><td><label><input type="checkbox" name="n_on" value="1"' . checked( ! empty( $n['on'] ), true, false ) . '> ' . esc_html__( 'Show the whole website in black and white', 'mcupr' ) . '</label> &nbsp; <label>' . esc_html__( 'From', 'mcupr' ) . ' <input type="date" name="n_from" value="' . esc_attr( $n['from'] ) . '"></label> <label>' . esc_html__( 'to', 'mcupr' ) . ' <input type="date" name="n_to" value="' . esc_attr( $n['to'] ) . '"></label> <span class="description">' . esc_html__( 'Dates are optional.', 'mcupr' ) . '</span></td></tr></tbody></table></div>';
	submit_button( __( 'Save settings', 'mcupr' ) );
	echo '</form></div>';
}

add_action(
	'admin_post_mcupr_status_save',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['mcupr_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_status_nonce'] ) ), 'mcupr_status_save' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		mcupr_status_save( $p );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-status&saved=1' ) );
		exit;
	}
);

/** Clean and store the form (separate so tests can call it). */
function mcupr_status_save( $p ) {
	$old   = mcupr_status();
	$langs = array_keys( mcupr_ui_languages() );
	$per   = static function ( $name, $max ) use ( $p, $langs ) {
		$o = array();
		foreach ( $langs as $l ) {
			$v = isset( $p[ $name ][ $l ] ) && is_scalar( $p[ $name ][ $l ] ) ? (string) $p[ $name ][ $l ] : '';
			$v = 'msg' === substr( $name, -3 ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
			$o[ $l ] = mb_substr( $v, 0, $max );
		}
		return $o;
	};
	$date = static function ( $k ) use ( $p ) {
		$v = isset( $p[ $k ] ) ? (string) $p[ $k ] : '';
		return preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $d ) && checkdate( (int) $d[2], (int) $d[3], (int) $d[1] ) ? $v : '';
	};
	$img = static function ( $k, $oldv ) use ( $p ) {
		if ( ! empty( $p[ $k . '_clear' ] ) ) {
			return 0;
		}
		$id = isset( $p[ $k ] ) ? absint( $p[ $k ] ) : 0;
		return $id && wp_attachment_is_image( $id ) ? $id : ( isset( $p[ $k ] ) ? 0 : (int) $oldv );
	};
	$back = isset( $p['m_back'] ) ? (string) $p['m_back'] : '';
	$ips  = array();
	foreach ( preg_split( '/\R/', isset( $p['m_ips'] ) ? (string) $p['m_ips'] : '' ) as $ip ) {
		$ip = trim( $ip );
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$ips[] = $ip;
		}
	}
	$key = isset( $p['m_key'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $p['m_key'] ) : '';
	if ( strlen( $key ) < 12 || ! empty( $p['m_newkey'] ) ) {
		$key = wp_generate_password( 16, false );
	}
	$preset = isset( $p['w_preset'], mcupr_welcome_presets()[ $p['w_preset'] ] ) ? $p['w_preset'] : 'custom';
	$url    = isset( $p['w_url'] ) ? esc_url_raw( trim( (string) $p['w_url'] ), array( 'http', 'https' ) ) : '';
	$s      = array(
		'maint'    => array(
			'on'    => ! empty( $p['m_on'] ) ? 1 : 0,
			'title' => $per( 'm_title', 120 ),
			'msg'   => $per( 'm_msg', 600 ),
			'back'  => preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $back ) ? $back : '',
			'ips'   => implode( "\n", array_unique( $ips ) ),
			'key'   => $key,
			'image' => $img( 'm_image', $old['maint']['image'] ),
		),
		'welcome'  => array(
			'on'     => ! empty( $p['w_on'] ) ? 1 : 0,
			'preset' => $preset,
			'title'  => $per( 'w_title', 120 ),
			'msg'    => $per( 'w_msg', 600 ),
			'btn'    => $per( 'w_btn', 40 ),
			'image'  => $img( 'w_image', $old['welcome']['image'] ),
			'url'    => $url,
			'from'   => $date( 'w_from' ),
			'to'     => $date( 'w_to' ),
			'days'   => isset( $p['w_days'] ) ? max( 0, min( 365, (int) $p['w_days'] ) ) : 1,
			'auto'   => isset( $p['w_auto'] ) ? max( 0, min( 60, (int) $p['w_auto'] ) ) : 0,
		),
		'mourning' => array( 'on' => ! empty( $p['n_on'] ) ? 1 : 0, 'from' => $date( 'n_from' ), 'to' => $date( 'n_to' ) ),
	);
	update_option( 'mcupr_status', $s, false );
	return $s;
}

/* ------------------------------------------------------------------ who may see what */

function mcupr_status_is_editor() {
	return is_user_logged_in() && current_user_can( 'edit_pages' );
}

function mcupr_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

function mcupr_maint_cookie_value( $m ) {
	return substr( hash_hmac( 'sha256', 'maint|' . $m['key'], wp_salt( 'auth' ) ), 0, 20 );
}

/** Is maintenance mode shutting this visitor out? */
function mcupr_maintenance_blocks() {
	$m = mcupr_status()['maint'];
	if ( empty( $m['on'] ) || mcupr_status_is_editor() ) {
		return false;
	}
	$ips = array_filter( array_map( 'trim', preg_split( '/\R/', (string) $m['ips'] ) ) );
	if ( $ips && in_array( mcupr_client_ip(), $ips, true ) ) {
		return false;
	}
	if ( ! empty( $m['key'] ) && isset( $_COOKIE['mcupr_maint_ok'] ) && hash_equals( mcupr_maint_cookie_value( $m ), (string) $_COOKIE['mcupr_maint_ok'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return false;
	}
	return true;
}

add_action(
	'init',
	static function () {
		// the reviewer link
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['mcupr_preview'] ) && ! is_admin() && ! headers_sent() ) {
			$m = mcupr_status()['maint'];
			if ( ! empty( $m['key'] ) && hash_equals( (string) $m['key'], (string) sanitize_text_field( wp_unslash( $_GET['mcupr_preview'] ) ) ) ) {
				mcupr_set_cookie( 'mcupr_maint_ok', mcupr_maint_cookie_value( $m ), time() + 12 * HOUR_IN_SECONDS, true, COOKIEPATH ? COOKIEPATH : '/' );
				wp_safe_redirect( home_url( '/' ) );
				exit;
			}
		}
		// the visitor pressed "enter" on the welcome page
		if ( isset( $_GET['mcupr_enter'] ) && ! is_admin() && ! headers_sent() ) {
			$w    = mcupr_status()['welcome'];
			$days = max( 0, (int) $w['days'] );
			// 0 days = every visit: a session cookie, so it is not shown again until the browser has been closed
			mcupr_set_cookie( 'mcupr_welcome', mcupr_welcome_version(), $days > 0 ? time() + $days * DAY_IN_SECONDS : 0, false, COOKIEPATH ? COOKIEPATH : '/' );
			$to = '' !== $w['url'] ? wp_validate_redirect( $w['url'], home_url( '/' ) ) : home_url( '/' );
			wp_safe_redirect( $to );
			exit;
		}
		// phpcs:enable
	},
	1
);

/* ------------------------------------------------------------------ the pages */

function mcupr_welcome_version() {
	return substr( md5( wp_json_encode( mcupr_status()['welcome'] ) ), 0, 10 );
}

/** Is the welcome page to be shown to this visitor now? */
function mcupr_welcome_wanted() {
	$w = mcupr_status()['welcome'];
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['mcupr_welcome'] ) && 'preview' === $_GET['mcupr_welcome'] && mcupr_status_is_editor() ) {
		return true;
	}
	if ( isset( $_GET['mcupr_welcome'] ) && 'sample' === $_GET['mcupr_welcome'] && isset( $_GET['occasion'], mcupr_welcome_presets()[ sanitize_key( wp_unslash( $_GET['occasion'] ) ) ] ) ) {
		return true; // a sample of an occasion (fixed text, nothing from the settings), open to everyone
	}
	// phpcs:enable
	if ( empty( $w['on'] ) || ! mcupr_status_in_dates( $w['from'], $w['to'] ) || mcupr_is_bot() || mcupr_status_is_editor() ) {
		return false;
	}
	if ( 'GET' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) || (int) get_query_var( 'paged' ) > 1 || isset( $_GET['mcupr_all'] ) ) { // phpcs:ignore
		return false;
	}
	return ! ( isset( $_COOKIE['mcupr_welcome'] ) && hash_equals( mcupr_welcome_version(), (string) $_COOKIE['mcupr_welcome'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$preview_m = isset( $_GET['mcupr_maint'] ) && 'preview' === $_GET['mcupr_maint'] && mcupr_status_is_editor();
		// phpcs:enable
		if ( $preview_m || mcupr_maintenance_blocks() ) {
			$m  = mcupr_status()['maint'];
			$ts = mcupr_maintenance_end_ts( $m );
			status_header( 503 );
			header( 'Retry-After: ' . ( $ts && $ts > time() ? min( $ts - time(), WEEK_IN_SECONDS ) : HOUR_IN_SECONDS ) );
			header( 'X-Robots-Tag: noindex, nofollow' );
			nocache_headers();
			include get_template_directory() . '/template-parts/status-page.php';
			exit;
		}
		if ( is_front_page() && mcupr_welcome_wanted() ) {
			status_header( 200 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
			$GLOBALS['mcupr_status_kind'] = 'welcome';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['mcupr_welcome'], $_GET['occasion'] ) && 'sample' === $_GET['mcupr_welcome'] ) {
				$GLOBALS['mcupr_status_sample'] = sanitize_key( wp_unslash( $_GET['occasion'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			include get_template_directory() . '/template-parts/status-page.php';
			exit;
		}
	},
	0
);

// Close the feeds' cousins too: the REST API is for logged-in users only while maintenance is on.
add_filter(
	'rest_authentication_errors',
	static function ( $result ) {
		if ( ! empty( $result ) || ! mcupr_maintenance_blocks() ) {
			return $result;
		}
		return new WP_Error( 'mcupr_maintenance', __( 'The website is under maintenance.', 'mcupr' ), array( 'status' => 503 ) );
	}
);

/**
 * The modes that are switched on right now, for editors only (nobody else sees them): array of keys, most important first.
 * Used to colour the Info button and to list them in the Info panel.
 */
function mcupr_active_modes() {
	if ( ! function_exists( 'mcupr_status_is_editor' ) || ! mcupr_status_is_editor() ) {
		return array();
	}
	$s   = mcupr_status();
	$out = array();
	if ( ! empty( $s['maint']['on'] ) ) {
		$out[] = 'maint';
	}
	if ( ! empty( $s['welcome']['on'] ) && mcupr_status_in_dates( $s['welcome']['from'], $s['welcome']['to'] ) ) {
		$out[] = 'welcome';
	}
	if ( ! empty( $s['mourning']['on'] ) && mcupr_status_in_dates( $s['mourning']['from'], $s['mourning']['to'] ) ) {
		$out[] = 'mourning';
	}
	return $out;
}

add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		if ( ! empty( mcupr_status()['maint']['on'] ) ) {
			$bar->add_node( array( 'id' => 'mcupr-maint', 'title' => mcupr_feather_i( 'tool', 16 ) . ' ' . esc_html__( 'Maintenance mode is ON', 'mcupr' ), 'href' => admin_url( 'admin.php?page=mcupr-status' ), 'meta' => array( 'class' => 'cc-maint' ) ) );
		}
	},
	100
);

// Mourning: greyscale between the dates.
add_filter(
	'language_attributes',
	static function ( $out ) {
		$n = mcupr_status()['mourning'];
		return ( ! empty( $n['on'] ) && mcupr_status_in_dates( $n['from'], $n['to'] ) ) ? $out . ' data-mourning="1"' : $out;
	}
);

/** [mcupr_welcome_samples]: a link to a sample welcome page for every occasion (used by the starter file's demo page). */
add_shortcode(
	'mcupr_welcome_samples',
	static function () {
		$o = '<ul class="welcome-samples">';
		foreach ( mcupr_welcome_presets() as $k => $p ) {
			if ( 'custom' === $k ) {
				continue;
			}
			$o .= '<li><a href="' . esc_url( add_query_arg( array( 'mcupr_welcome' => 'sample', 'occasion' => $k ), home_url( '/' ) ) ) . '" target="_blank" rel="noopener"><span class="welcome-dot" style="background:linear-gradient(135deg,' . esc_attr( $p[1] ) . ',' . esc_attr( $p[2] ) . ')" aria-hidden="true"></span>' . esc_html( $p[0] ) . '<span class="sr-only"> (' . esc_html( mcupr_t( 'new_tab' ) ) . ')</span></a></li>';
		}
		return $o . '</ul>';
	}
);
