<?php
/**
 * Cookie centre: what the website keeps in the visitor's browser, asked for and changeable by the visitor (PDPA B.E. 2562).
 *
 * Four kinds: necessary (always on), preferences (language, display settings, e-book place ...), statistics (the anonymous view counter),
 * embedded content (videos, maps and documents of other websites that are shown straight away instead of after a click). Nothing but the
 * necessary is kept for long before the visitor chooses: without a choice the preference and statistics cookies live until the browser
 * is closed, the preferences in localStorage live in sessionStorage, embedded content waits for its button.
 *
 * The choice is the cookie "mcupr_cc" = {"v":1,"t":unix time,"p":0|1,"s":0|1,"e":0|1} for one year (v = the version: raise it in the
 * Customizer to ask everybody again). The first small script (head) reads it, shows the banner when there is none, and moves the
 * localStorage keys of the theme (mcupr_*) to sessionStorage while preferences are not allowed; assets/js/cookie-center.js is the banner,
 * the dialog and what happens when the choice changes.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_cc_enabled() {
	return (bool) apply_filters( 'mcupr_cookie_center', (bool) get_theme_mod( 'mcupr_cc_on', true ) ) && ! is_admin() && ! is_feed() && ! is_embed() && ! is_customize_preview();
}

/** The version of the question: when it is raised, everybody is asked again. */
function mcupr_cc_version() {
	return max( 1, (int) get_theme_mod( 'mcupr_cc_ver', 1 ) );
}

/** The kinds that are asked about, in order: key => cookie letter. "necessary" is always there and always on. */
function mcupr_cc_categories() {
	$c = array( 'preferences' => 'p' );
	if ( get_theme_mod( 'mcupr_cc_stats', true ) ) {
		$c['statistics'] = 's';
	}
	if ( get_theme_mod( 'mcupr_cc_embeds', true ) ) {
		$c['embeds'] = 'e';
	}
	return apply_filters( 'mcupr_cc_categories', $c );
}

/** The choice the visitor made (from the cookie), or null. A cookie of an older version counts as no choice. */
function mcupr_cc_record() {
	static $rec = false;
	if ( false !== $rec ) {
		return $rec;
	}
	$rec = null;
	if ( isset( $_COOKIE['mcupr_cc'] ) ) {
		$d = json_decode( rawurldecode( wp_unslash( (string) $_COOKIE['mcupr_cc'] ) ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_array( $d ) && isset( $d['v'] ) && (int) $d['v'] === mcupr_cc_version() ) {
			$rec = array( 't' => isset( $d['t'] ) ? (int) $d['t'] : 0, 'p' => empty( $d['p'] ) ? 0 : 1, 's' => empty( $d['s'] ) ? 0 : 1, 'e' => empty( $d['e'] ) ? 0 : 1 );
		}
	}
	return $rec;
}

/** May this kind be kept / loaded? ("necessary" always; the others only when the visitor said yes; everything is allowed when the centre is off, the site then has its own way.) */
function mcupr_cc_allows( $cat ) {
	if ( 'necessary' === $cat ) {
		return true;
	}
	if ( ! get_theme_mod( 'mcupr_cc_on', true ) ) {
		return true;
	}
	if ( 'statistics' === $cat && ! get_theme_mod( 'mcupr_cc_stats', true ) ) {
		return true; // not asked about: the anonymous counter works as it did
	}
	$map = array( 'preferences' => 'p', 'statistics' => 's', 'embeds' => 'e' );
	$r   = mcupr_cc_record();
	return isset( $map[ $cat ] ) && $r && ! empty( $r[ $map[ $cat ] ] );
}

/** Cookies that are kept longer only with permission live until the browser is closed without it. */
add_filter(
	'mcupr_cookie_expires',
	static function ( $exp, $name ) {
		if ( $exp <= 0 ) {
			return $exp;
		}
		$cat = in_array( $name, array( 'mcupr_lang', 'mcupr_welcome' ), true ) ? 'preferences' : ( 'mcupr_seen' === $name ? 'statistics' : '' );
		return '' !== $cat && ! mcupr_cc_allows( $cat ) ? 0 : $exp;
	},
	10,
	2
);

/**
 * Everything the website keeps: array( name, kind, who, purpose key, how long key ). Plugins add their own with the filter.
 * "kind": necessary / preferences / statistics / embeds.
 */
function mcupr_cc_inventory() {
	$rows = array(
		array( 'mcupr_cc', 'necessary', 'cookie', 'cc_p_cc', 'cc_d_year' ),
		array( 'wordpress_* / wp-settings-*', 'necessary', 'cookie', 'cc_p_wp', 'cc_d_wp' ),
		array( 'mcupr_fbk', 'necessary', 'cookie', 'cc_p_form', 'cc_d_15m' ),
		array( 'mcupr_oa_state / mcupr_sso_state', 'necessary', 'cookie', 'cc_p_login', 'cc_d_10m' ),
		array( 'mcupr_maint_ok', 'necessary', 'cookie', 'cc_p_maint', 'cc_d_12h' ),
		array( 'mcupr_pv_*', 'necessary', 'cookie', 'cc_p_poll', 'cc_d_30d' ),
		array( 'mcupr_lang', 'preferences', 'cookie', 'cc_p_lang', 'cc_d_year_or_session' ),
		array( 'mcupr_welcome', 'preferences', 'cookie', 'cc_p_welcome', 'cc_d_days_or_session' ),
		array( 'mcupr_prefs', 'preferences', 'storage', 'cc_p_prefs', 'cc_d_forever_or_session' ),
		array( 'mcupr_sbmin / mcupr_view / mcupr_persona / mcupr_recent / mcupr_rate / mcupr_vc_size', 'preferences', 'storage', 'cc_p_ui', 'cc_d_forever_or_session' ),
		array( 'mcupr_bk_*', 'preferences', 'storage', 'cc_p_book', 'cc_d_forever_or_session' ),
		array( 'comment_author_*', 'preferences', 'cookie', 'cc_p_comment', 'cc_d_year' ),
		array( 'mcupr_seen', 'statistics', 'cookie', 'cc_p_seen', 'cc_d_6h_or_session' ),
		array( 'drive.google.com / docs.google.com', 'embeds', 'third', 'cc_p_google', 'cc_d_theirs' ),
		array( 'youtube-nocookie.com / player.vimeo.com', 'embeds', 'third', 'cc_p_video', 'cc_d_theirs' ),
		array( 'google.com/maps', 'embeds', 'third', 'cc_p_map', 'cc_d_theirs' ),
	);
	return apply_filters( 'mcupr_cc_inventory', $rows );
}

/** The words for a "how long" key (kept apart so that the table in the dialog and the policy page say the same). */
function mcupr_cc_text( $key, $lang = '' ) {
	$k = (string) $key;
	if ( function_exists( 'mcupr_t' ) ) {
		$t = mcupr_t( $k, $lang );
		if ( $t !== $k ) {
			return $t;
		}
	}
	return $k;
}

function mcupr_cc_category_title( $cat, $lang = '' ) {
	$map = array( 'necessary' => 'cc_nec_t', 'preferences' => 'cc_pref_t', 'statistics' => 'cc_stat_t', 'embeds' => 'cc_emb_t' );
	return mcupr_cc_text( isset( $map[ $cat ] ) ? $map[ $cat ] : $cat, $lang );
}

/** The table of what is kept, of the kinds that are asked about (and the necessary): one table per kind. */
function mcupr_cc_table_html( $only = '', $lang = '' ) {
	$cats = array_merge( array( 'necessary' => 'n' ), mcupr_cc_categories() );
	$out  = '';
	foreach ( $cats as $cat => $_l ) {
		if ( '' !== $only && $only !== $cat ) {
			continue;
		}
		$rows = array_filter( mcupr_cc_inventory(), static function ( $r ) use ( $cat ) { return $r[1] === $cat; } );
		if ( ! $rows ) {
			continue;
		}
		$out .= '<table class="cc-table"><caption>' . esc_html( mcupr_cc_category_title( $cat, $lang ) ) . '</caption><thead><tr><th scope="col">' . esc_html( mcupr_t( 'cc_h_name', $lang ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'cc_h_purpose', $lang ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'cc_h_dur', $lang ) ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$out .= '<tr><td><code>' . esc_html( $r[0] ) . '</code></td><td>' . esc_html( mcupr_cc_text( $r[3], $lang ) ) . '</td><td>' . esc_html( mcupr_cc_text( $r[4], $lang ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';
	}
	return $out;
}

/* -------------------------------------------------------------------- the first script (head) */

/**
 * Before anything else: read the choice, show the banner when there is none, keep the preferences out of localStorage until they are
 * allowed. A visitor who accepted the old notice (localStorage mcupr_consent = 1, which spoke of language and display settings) has said
 * yes to preferences, and the cookie is written for them so that the server knows too.
 */
function mcupr_cc_head() {
	if ( ! mcupr_cc_enabled() ) {
		return;
	}
	$ver = mcupr_cc_version();
	?>
<script id="mcupr-consent-js">(function(){var d=document.documentElement,V=<?php echo (int) $ver; ?>,P='<?php echo esc_js( COOKIEPATH ? COOKIEPATH : '/' ); ?>',st=null,L={p:'preferences',s:'statistics',e:'embeds'};
function read(){var m=document.cookie.match(/(?:^|; )mcupr_cc=([^;]*)/);if(!m){return null;}try{var o=JSON.parse(decodeURIComponent(m[1]));return o&&o.v===V?o:null;}catch(e){return null;}}
function write(o){try{document.cookie='mcupr_cc='+encodeURIComponent(JSON.stringify(o))+'; Max-Age=31536000; Path='+P+'; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');}catch(e){}}
st=read();
if(!st){try{if(V===1&&localStorage.getItem('mcupr_consent')==='1'){st={v:1,t:Math.floor(Date.now()/1000),p:1,s:0,e:0,l:1};write(st);}}catch(e){}}
var C=window.mcuprConsent={v:V,path:P,state:st,write:write,has:function(c){if(c==='necessary'){return true;}var k={preferences:'p',statistics:'s',embeds:'e'}[c];return !!(C.state&&k&&C.state[k]);}};
if(!st){d.classList.add('needs-consent');}
var NEC={mcupr_cc:1,mcupr_consent:1,mcupr_preload_test:1},S=Storage.prototype,o={s:S.setItem,g:S.getItem,r:S.removeItem};
function hold(k,self){try{return self===localStorage&&typeof k==='string'&&k.indexOf('mcupr_')===0&&!NEC[k]&&!C.has('preferences');}catch(e){return false;}}
S.setItem=function(k,v){return hold(k,this)?o.s.call(sessionStorage,k,v):o.s.call(this,k,v);};
S.getItem=function(k){return hold(k,this)?o.g.call(sessionStorage,k):o.g.call(this,k);};
S.removeItem=function(k){if(hold(k,this)){o.r.call(sessionStorage,k);}return o.r.call(this,k);};
C.purge=function(){try{var ks=[],i;for(i=0;i<localStorage.length;i++){var k=localStorage.key(i);if(k&&k.indexOf('mcupr_')===0&&!NEC[k]){ks.push(k);}}ks.forEach(function(k){o.r.call(localStorage,k);});}catch(e){}};
})();</script>
	<?php
}
add_action( 'wp_head', 'mcupr_cc_head', 0 );

/* -------------------------------------------------------------------- banner and dialog (footer) */

function mcupr_cc_markup() {
	if ( ! mcupr_cc_enabled() ) {
		return;
	}
	$url  = mcupr_policy_url( 'cookie' ) ? mcupr_policy_url( 'cookie' ) : mcupr_policy_url( 'privacy' );
	$cats = mcupr_cc_categories();
	$desc = array( 'necessary' => 'cc_nec_d', 'preferences' => 'cc_pref_d', 'statistics' => 'cc_stat_d', 'embeds' => 'cc_emb_d' );
	?>
<div id="cookie-notice" class="cookie-notice cc-banner soft-card" role="region" aria-labelledby="cookie-title">
	<p id="cookie-title" class="m-0 mb-1 font-bold"><?php mcupr_e( 'cc_title' ); ?></p>
	<p class="m-0 mb-3 text-sm text-fg2"><?php mcupr_e( 'cc_msg' ); ?><?php if ( $url ) : ?> <a href="<?php echo esc_url( $url ); ?>"><?php mcupr_e( 'cookie_policy' ); ?></a><?php endif; ?></p>
	<div class="cc-btns">
		<button type="button" class="btn" data-cc-accept><?php mcupr_e( 'cc_accept_all' ); ?></button>
		<button type="button" class="btn btn-outline" data-cc-reject><?php mcupr_e( 'cc_reject' ); ?></button>
		<button type="button" class="btn btn-outline" data-cc-open><?php mcupr_e( 'cc_settings' ); ?></button>
	</div>
</div>
<dialog id="cc-dialog" class="cc-dialog soft-card" aria-labelledby="cc-title" aria-describedby="cc-intro">
	<form method="dialog" class="cc-form" id="cc-form">
		<div class="cc-head">
			<h2 id="cc-title" class="m-0 text-xl font-bold"><?php mcupr_e( 'cc_title' ); ?></h2>
			<button type="button" class="cc-x" data-cc-close aria-label="<?php echo esc_attr( mcupr_t( 'cc_close' ) ); ?>"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>
		</div>
		<p id="cc-intro" class="m-0 mb-3 text-sm text-fg2"><?php mcupr_e( 'cc_intro' ); ?><?php if ( $url ) : ?> <a href="<?php echo esc_url( $url ); ?>"><?php mcupr_e( 'cookie_policy' ); ?></a><?php endif; ?></p>
		<div class="cc-cats">
			<?php
			foreach ( array_merge( array( 'necessary' => 'n' ), $cats ) as $cat => $letter ) :
				$id = 'cc-c-' . $cat;
				?>
			<fieldset class="cc-cat">
				<legend class="sr-only"><?php echo esc_html( mcupr_cc_category_title( $cat ) ); ?></legend>
				<div class="cc-cat-top">
					<label for="<?php echo esc_attr( $id ); ?>" class="cc-cat-t"><?php echo esc_html( mcupr_cc_category_title( $cat ) ); ?></label>
					<?php if ( 'necessary' === $cat ) : ?>
						<span class="cc-always" id="<?php echo esc_attr( $id ); ?>-s"><?php mcupr_e( 'cc_always' ); ?></span>
						<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="cc-sw" role="switch" checked disabled aria-describedby="<?php echo esc_attr( $id ); ?>-d">
					<?php else : ?>
						<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="cc-sw" role="switch" name="<?php echo esc_attr( $letter ); ?>" data-cc-cat="<?php echo esc_attr( $cat ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-d">
					<?php endif; ?>
				</div>
				<p id="<?php echo esc_attr( $id ); ?>-d" class="cc-cat-d"><?php echo esc_html( mcupr_t( $desc[ $cat ] ) ); ?></p>
				<details class="cc-more"><summary><?php mcupr_e( 'cc_show' ); ?></summary><?php echo mcupr_cc_table_html( $cat ); // phpcs:ignore WordPress.Security.EscapeOutput ?></details>
			</fieldset>
			<?php endforeach; ?>
		</div>
		<p class="cc-saved sr-only" role="status" aria-live="polite" data-cc-saved></p>
		<div class="cc-btns cc-foot">
			<button type="button" class="btn" data-cc-save><?php mcupr_e( 'cc_save' ); ?></button>
			<button type="button" class="btn btn-outline" data-cc-accept><?php mcupr_e( 'cc_accept_all' ); ?></button>
			<button type="button" class="btn btn-outline" data-cc-reject><?php mcupr_e( 'cc_reject' ); ?></button>
		</div>
	</form>
</dialog>
	<?php
}
add_action( 'wp_footer', 'mcupr_cc_markup', 5 );

/** The words and numbers the script needs. */
function mcupr_cc_assets() {
	if ( ! mcupr_cc_enabled() ) {
		return;
	}
	wp_enqueue_script( 'mcupr-cc', MCUPR_URI . '/assets/js/cookie-center.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'mcupr-cc', 'mcuprCc', array( 'saved' => mcupr_t( 'cc_saved' ), 'cookies' => array( 'mcupr_seen', 'mcupr_lang', 'mcupr_welcome' ) ) );
}
add_action( 'wp_enqueue_scripts', 'mcupr_cc_assets', 20 );

/** "Cookie settings" button: in the footer, and [mcupr_cookie_settings] anywhere (the cookie policy page). */
function mcupr_cc_button( $class = '' ) {
	return '<button type="button" class="' . esc_attr( $class ) . '" data-cc-open>' . esc_html( mcupr_t( 'cc_footer' ) ) . '</button>';
}

add_shortcode(
	'mcupr_cookie_settings',
	static function () {
		return get_theme_mod( 'mcupr_cc_on', true ) ? '<p class="cc-sc">' . mcupr_cc_button( 'btn' ) . '</p>' : '';
	}
);
add_shortcode(
	'mcupr_cookie_table',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'kind' => '' ), $atts, 'mcupr_cookie_table' );
		return '<div class="cc-tables">' . mcupr_cc_table_html( sanitize_key( $a['kind'] ) ) . '</div>';
	}
);

/* -------------------------------------------------------------------- Customizer */

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		$wp_customize->add_section( 'mcupr_cookies', array( 'title' => __( 'MCUPR: Cookies and privacy', 'mcupr' ), 'priority' => 97, 'description' => __( 'The visitor chooses which cookies and stored settings the website may keep: necessary only, with display settings, with anonymous statistics, with embedded content. Raise the version to ask everybody again (after a change of the cookie policy).', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_cc_on', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_cc_on', array( 'section' => 'mcupr_cookies', 'type' => 'checkbox', 'label' => __( 'Ask visitors about cookies (banner and settings)', 'mcupr' ), 'description' => __( 'Turn off only if the website has another consent tool.', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_cc_stats', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_cc_stats', array( 'section' => 'mcupr_cookies', 'type' => 'checkbox', 'label' => __( 'Ask about anonymous statistics (view counter)', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_cc_embeds', array( 'default' => true, 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wp_customize->add_control( 'mcupr_cc_embeds', array( 'section' => 'mcupr_cookies', 'type' => 'checkbox', 'label' => __( 'Ask about embedded content (videos, maps, Google documents)', 'mcupr' ), 'description' => __( 'With a yes they are shown at once; without it each waits for its button.', 'mcupr' ) ) );
		$wp_customize->add_setting( 'mcupr_cc_ver', array( 'default' => 1, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( 'mcupr_cc_ver', array( 'section' => 'mcupr_cookies', 'type' => 'number', 'input_attrs' => array( 'min' => 1, 'max' => 999 ), 'label' => __( 'Version of the question', 'mcupr' ), 'description' => __( 'Raise this number to ask every visitor again.', 'mcupr' ) ) );
	},
	30
);
