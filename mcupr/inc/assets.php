<?php
/**
 * Front-end assets and the pre-paint preference script.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_assets() {
	// Tailwind CSS build (npm run build) – see src/input.css.
	wp_enqueue_style( 'mcupr', MCUPR_URI . '/assets/css/tailwind.css', array(), MCUPR_VERSION );
	wp_add_inline_style( 'mcupr', mcupr_custom_css() );

	// SweetAlert2 (self-hosted) is NOT enqueued: main.js downloads it the first time a dialog is opened.
	wp_enqueue_script( 'mcupr', MCUPR_URI . '/assets/js/main.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular( array( 'post', 'page' ) ) && get_theme_mod( 'mcupr_listen', true ) ) {
		wp_enqueue_script( 'mcupr-listen', MCUPR_URI . '/assets/js/listen.js', array(), MCUPR_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	$cookie_url = mcupr_policy_url( 'cookie' ) ? mcupr_policy_url( 'cookie' ) : mcupr_policy_url( 'privacy' );
	wp_localize_script(
		'mcupr',
		'mcuprI18n',
		array(
			'fontSize'    => mcupr_t( 'status_font' ),
			'reset'       => mcupr_t( 'status_reset' ),
			'submenu'     => mcupr_t( 'submenu' ),
			'cookieTitle' => mcupr_t( 'cookie_title' ),
			'cookieMsg'   => mcupr_t( 'cookie_msg' ),
			'cookieOk'    => mcupr_t( 'cookie_ok' ),
			'cookieLink'  => $cookie_url ? esc_url( $cookie_url ) : '',
			'cookiePolicy' => mcupr_t( 'cookie_policy' ),
			'cancel'      => mcupr_t( 'cancel' ),
			'resetQ'      => mcupr_t( 'reset_q' ),
			'resetQTxt'   => mcupr_t( 'reset_q_txt' ),
			'resetAll'    => mcupr_t( 'reset_all' ),
			'searchEmpty' => mcupr_t( 'search_empty' ),
			'ok'          => mcupr_t( 'cookie_ok' ),
			'close'       => mcupr_t( 'close' ),
			'a11yTitle'   => mcupr_t( 'settings_title' ),
			'langTitle'   => mcupr_t( 'language' ),
			'searchTitle' => mcupr_t( 'search' ),
			'cursorView'  => mcupr_t( 'cursor_view' ),
			'on'          => mcupr_t( 'on' ),
			'off'         => mcupr_t( 'off' ),
			'langBadge'   => mcupr_lang_badge(),
			'animDefaultOff' => ! get_theme_mod( 'mcupr_anim_default', true ),
			'patternDefaultOff' => ! get_theme_mod( 'mcupr_pattern', true ),
			'menuDefault' => mcupr_menu_default(),
			'colorDefault' => get_theme_mod( 'mcupr_palette', 'rose' ),
			'palettes'    => mcupr_palettes_for_js(),
			'expandMenu'  => mcupr_t( 'expand_menu' ),
				'moreMenu'    => mcupr_t( 'more_menu' ),
			'allMenu'     => mcupr_t( 'all_menu' ),
			'closeMenu'   => mcupr_t( 'close_menu' ),
			'collapseMenu' => mcupr_t( 'collapse_menu' ),
			'aboutTitle'  => mcupr_t( 'about' ),
			'searchUrl'   => esc_url_raw( add_query_arg( 's', '', mcupr_home_url() ) ),
				'swalUrl'     => esc_url_raw( MCUPR_URI . '/assets/vendor/sweetalert2.all.min.js?ver=11.26.25' ),
		)
	);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'mcupr_assets' );

/**
 * Apply saved display preferences before first paint (prevents a flash of the
 * wrong theme / font size). Preferences live only in the visitor's browser.
 */
function mcupr_head_prefs() {
	$anim_off = get_theme_mod( 'mcupr_anim_default', true ) ? 0 : 1;
	$pat_off  = get_theme_mod( 'mcupr_pattern', true ) ? 0 : 1;
	$menu     = mcupr_menu_default();
	?>
<script>(function(){try{var d=document.documentElement,p=JSON.parse(localStorage.getItem('mcupr_prefs')||'{}');if(localStorage.getItem('mcupr_consent')!=='1'){d.classList.add('needs-consent');}['theme','font','contrast','gray','underline','readable','spacing','cursor','motion','images','pattern','tv'].forEach(function(k){if(p[k]!==undefined&&p[k]!==''){d.setAttribute('data-'+k,p[k]);}});d.setAttribute('data-menu',(p.menu==='top'||p.menu==='side'||p.menu==='hybrid')?p.menu:'<?php echo esc_js( $menu ); ?>');var m=d.getAttribute('data-menu');if(m==='hybrid'||localStorage.getItem('mcupr_sbmin')==='1'){d.classList.add('sb-min');}if(p.cv&&typeof p.cv==='object'){var s=document.createElement('style');s.id='mcupr-user-colors';var t='';for(var k in p.cv){if(/^--[a-z0-9-]+$/.test(k)&&/^#[0-9a-f]{6}$/i.test(p.cv[k])){t+=k+':'+p.cv[k]+';';}}s.textContent='@layer base{:root:root{'+t+'}}';document.head.appendChild(s);}if(p.motion===undefined&&<?php echo (int) $anim_off; ?>){d.setAttribute('data-motion','1');}if(p.pattern===undefined&&<?php echo (int) $pat_off; ?>){d.setAttribute('data-pattern','off');}if(p.tv===undefined&&/SMART-?TV|Tizen|Web0S|WebOS|NetCast|HbbTV|VIDAA|Roku|AFT[A-Z]|BRAVIA|CrKey|GoogleTV|Android TV|AppleTV|Viera|PlayStation|Xbox/i.test(navigator.userAgent)){d.setAttribute('data-tv','1');if(p.theme===undefined){d.setAttribute('data-theme','dark');}}}catch(e){}})();</script>
	<?php
}
add_action( 'wp_head', 'mcupr_head_prefs', 1 );


/** Preload the regular font (all body text), so the text appears in its final face at once; more preloads would only compete with the CSS and the hero image. */
function mcupr_preload_fonts() {
	foreach ( array( 'Sarabun-400Regular' ) as $f ) { // bold / semi-bold load when the CSS asks for them (they only change the weight, not the layout)
		echo '<link rel="preload" href="' . esc_url( MCUPR_URI . '/assets/fonts/' . $f . '.woff2' ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
}
add_action( 'wp_head', 'mcupr_preload_fonts', 1 );
