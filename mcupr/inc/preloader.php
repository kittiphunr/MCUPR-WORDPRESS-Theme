<?php
/**
 * Page loading screen: the site's logo (the one uploaded in Site Identity, else the university emblem) breathing inside a
 * turning ring over a soft background, with the name of the site under it. It covers the page while it loads and fades away
 * when the page is ready, so moving from page to page feels like one steady movement: on a click on a link of the site the
 * screen fades in over the old page, and the next page already starts with it.
 *
 * Kept short and safe:
 *   • nothing at all without JavaScript, with "reduce motion" (browser or the theme's own switch), in print, in the
 *     Customizer preview, and for robots and automated browsers (navigator.webdriver) – so screenshots and tests see the page;
 *   • it stays at least 0.7 s on the first page of a visit and 0.35 s on the next ones (so it never just flashes), and at most 3.2 s
 *     whatever the page is still loading;
 *   • links that are not a normal move to another page (new tab, downloads, files, mailto, #anchors, with a key held) do not start it.
 * Customizer → MCUPR: Animation: on every page (the default) / only the first page of a visit / off, its own logo (and one for dark mode), and its own text or none.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_preload_modes() {
	return array( 'every', 'session', 'off' );
}

function mcupr_preload_mode() {
	$m = (string) get_theme_mod( 'mcupr_preload', 'every' );
	return in_array( $m, mcupr_preload_modes(), true ) ? $m : 'every';
}

function mcupr_preload_enabled() {
	return 'off' !== mcupr_preload_mode() && ! is_customize_preview() && ! is_admin() && ! is_feed() && ! is_embed() && (bool) apply_filters( 'mcupr_preloader', true );
}

/** The decision script (head, right after the preferences script, which sets data-motion). */
function mcupr_preload_head() {
	if ( ! mcupr_preload_enabled() ) {
		return;
	}
	$mode = mcupr_preload_mode();
	?>
<script id="mcupr-preload-js">(function(){var d=document.documentElement,w=window,K='mcupr_pl',mode='<?php echo esc_js( $mode ); ?>',seen=false;try{seen=sessionStorage.getItem(K)==='1';sessionStorage.setItem(K,'1');}catch(e){}
function skip(){try{return d.getAttribute('data-motion')==='1'||(w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches)||(navigator.webdriver&&localStorage.getItem('mcupr_preload_test')!=='1');}catch(e){return true;}}
if(skip()){return;}
var t0=Date.now(),min=seen?350:700,max=3200,fin=false;
function reset(){d.classList.remove('pl-on','pl-out','pl-leave');}
w.addEventListener('pageshow',function(e){if(e.persisted){reset();}});
if(mode==='session'&&seen){return;}
d.classList.add('pl-on');
function done(){if(fin){return;}fin=true;setTimeout(function(){d.classList.add('pl-out');setTimeout(function(){reset();d.classList.add('pl-done');},520);},Math.max(0,min-(Date.now()-t0)));}
if(document.readyState==='complete'){done();}else{w.addEventListener('load',done);}
setTimeout(done,max);
if(mode==='every'){document.addEventListener('click',function(e){if(e.defaultPrevented||e.button||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey){return;}var a=e.target.closest&&e.target.closest('a[href]');if(!a||a.target&&a.target!=='_self'||a.hasAttribute('download')||a.hasAttribute('data-no-preload')){return;}var u;try{u=new URL(a.href,location.href);}catch(x){return;}if(u.origin!==location.origin||!/^https?:$/.test(u.protocol)||u.pathname===location.pathname&&u.search===location.search&&u.hash!==''||u.searchParams.has('mcupr_dl')||/\.(pdf|docx?|xlsx?|pptx?|zip|rar|7z|csv|txt|mp3|mp4|jpe?g|png|gif|webp|svg|ics)$/i.test(u.pathname)||/\/wp-admin\/|\/wp-login\.php/.test(u.pathname)){return;}d.classList.remove('pl-out');d.classList.add('pl-on','pl-leave');setTimeout(function(){if(d.classList.contains('pl-leave')){reset();}},6000);},false);}
})();</script>
	<?php
}
add_action( 'wp_head', 'mcupr_preload_head', 2 );

/** The logo of the loading screen: the one chosen for it, else the uploaded site logo (with its dark-mode version), else the emblem of the theme. */
function mcupr_preload_logo() {
	$own = (int) get_theme_mod( 'mcupr_preload_logo' );
	if ( $own && wp_attachment_is_image( $own ) ) {
		$main = $own;
		$dark = (int) get_theme_mod( 'mcupr_preload_logo_dark' );
	} else {
		$main = (int) get_theme_mod( 'custom_logo' );
		$dark = (int) get_theme_mod( 'mcupr_logo_dark' );
	}
	if ( ! $main ) {
		return mcupr_default_logo( 'pl-logo' );
	}
	$o = wp_get_attachment_image( $main, 'full', false, array( 'alt' => '', 'class' => 'pl-logo logo-light', 'decoding' => 'async', 'loading' => 'eager' ) );
	if ( $dark ) {
		$o .= wp_get_attachment_image( $dark, 'full', false, array( 'alt' => '', 'class' => 'pl-logo logo-dark', 'decoding' => 'async', 'loading' => 'eager' ) );
	}
	return $o;
}

/** The words under the logo: the text written for it, else the name of the site; empty when the admin hid the text. */
function mcupr_preload_text() {
	if ( ! get_theme_mod( 'mcupr_preload_text_show', true ) ) {
		return '';
	}
	$t = trim( wp_strip_all_tags( (string) get_theme_mod( 'mcupr_preload_text', '' ) ) );
	return '' !== $t ? $t : mcupr_org_name();
}

/** The screen itself, first thing in the body (hidden by the stylesheet unless the head script switched it on). */
function mcupr_preload_markup() {
	if ( ! mcupr_preload_enabled() ) {
		return;
	}
	echo '<div id="preloader" class="pl" aria-hidden="true"><span class="pl-bg"></span><div class="pl-box"><span class="pl-ring"><svg viewBox="0 0 100 100" focusable="false"><circle class="pl-track" cx="50" cy="50" r="46"/><circle class="pl-arc" cx="50" cy="50" r="46"/></svg>' . mcupr_preload_logo() . '</span>' . ( '' !== mcupr_preload_text() ? '<span class="pl-name">' . esc_html( mcupr_preload_text() ) . '</span>' : '' ) . '<span class="pl-bar"><i></i></span></div></div>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'wp_body_open', 'mcupr_preload_markup', 1 );

/* Customizer: when the screen shows. */
add_action(
	'customize_register',
	static function ( $wp_customize ) {
		if ( ! $wp_customize->get_section( 'mcupr_display' ) ) {
			return;
		}
		$wp_customize->add_setting( 'mcupr_preload', array( 'default' => 'every', 'sanitize_callback' => static function ( $v ) { return in_array( $v, mcupr_preload_modes(), true ) ? $v : 'every'; } ) );
		$wp_customize->add_control(
			'mcupr_preload',
			array(
				'section'     => 'mcupr_display',
				'priority'    => 33,
				'label'       => __( 'Loading screen with the logo', 'mcupr' ),
				'description' => __( 'The logo appears while a page loads and fades away when it is ready. It is never shown to visitors who asked for less motion, in print, or to robots.', 'mcupr' ),
				'type'        => 'select',
				'choices'     => array(
					'every'   => __( 'On every page (and between pages)', 'mcupr' ),
					'session' => __( 'Only on the first page of a visit', 'mcupr' ),
					'off'     => __( 'Never', 'mcupr' ),
				),
			)
		);
		$wp_customize->add_setting( 'mcupr_preload_logo', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_preload_logo', array( 'section' => 'mcupr_display', 'priority' => 34, 'label' => __( 'Loading screen: its own logo', 'mcupr' ), 'description' => __( 'Leave empty to use the logo of the site.', 'mcupr' ), 'mime_type' => 'image' ) ) );
		$wp_customize->add_setting( 'mcupr_preload_logo_dark', array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'mcupr_preload_logo_dark', array( 'section' => 'mcupr_display', 'priority' => 35, 'label' => __( 'Loading screen: its logo for dark mode', 'mcupr' ), 'description' => __( 'Only used together with its own logo above.', 'mcupr' ), 'mime_type' => 'image' ) ) );
		$wp_customize->add_setting( 'mcupr_preload_text_show', array( 'default' => true, 'sanitize_callback' => static function ( $v ) { return (bool) $v; } ) );
		$wp_customize->add_control( 'mcupr_preload_text_show', array( 'section' => 'mcupr_display', 'priority' => 36, 'label' => __( 'Loading screen: show the text under the logo', 'mcupr' ), 'type' => 'checkbox' ) );
		$wp_customize->add_setting( 'mcupr_preload_text', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
		$wp_customize->add_control( 'mcupr_preload_text', array( 'section' => 'mcupr_display', 'priority' => 37, 'label' => __( 'Loading screen: text', 'mcupr' ), 'description' => __( 'Leave empty to show the name of the site.', 'mcupr' ), 'type' => 'text' ) );
	},
	20
);
