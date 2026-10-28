<?php
/** The order of the Customizer and of the control center's links into it.   php php/customizer-order.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$m = new WP_Customize_Manager();
$GLOBALS['wp_customize'] = $m;
do_action( 'customize_register', $m );

// 1. sections and panels in the order of use
$top = array();
foreach ( $m->panels() as $id => $p ) { if ( 'themes' !== $id ) { $top[ $id ] = (int) $p->priority; } }
foreach ( $m->sections() as $id => $s ) { if ( ! $s->panel ) { $top[ $id ] = (int) $s->priority; } }
$want = array_values( array_filter( mcupr_cz_order(), static function ( $id ) use ( $top ) { return isset( $top[ $id ] ); } ) );
$got  = array_keys( array_intersect_key( $top, array_flip( $want ) ) );
usort( $got, static function ( $a, $b ) use ( $top ) { return $top[ $a ] <=> $top[ $b ]; } );
t( $want === $got && count( $want ) >= 14, 'sections: the listed ones come in the order of use (' . count( $want ) . ' of them)' );
t( $top['mcupr_notice'] < $top['mcupr_home'] && $top['mcupr_home'] < $top['title_tagline'] && $top['title_tagline'] < $top['mcupr_display'] && $top['mcupr_display'] < $top['mcupr_credit'], 'sections: every-week things first, set-once next, reading and privacy, rarely used last' );
$themes = $m->get_panel( 'themes' );
t( ! $themes || (int) $themes->priority >= $top['custom_css'], 'sections: the choice of theme is last' );
$other = array_diff_key( $top, array_flip( mcupr_cz_order() ) );
t( ! $other || min( $other ) > 0, 'sections: nothing else sits at the very top' );

// 2. split sections
$sec = static function ( $id ) use ( $m ) { $c = $m->get_control( $id ); return $c ? $c->section : null; };
t( 'mcupr_footer' === $sec( 'mcupr_map_on' ) && 'mcupr_footer' === $sec( 'mcupr_social_line' ), 'footer: map and social links have a section of their own' );
t( 'mcupr_credit' === $sec( 'mcupr_credit_name' ) && 'mcupr_credit' === $sec( 'mcupr_credit_hide' ), 'credit: the website credit has a section of its own' );
t( 'mcupr_loading' === $sec( 'mcupr_preload' ) && 'mcupr_loading' === $sec( 'mcupr_preload_text' ), 'loading screen: its settings have a section of their own' );
t( 'mcupr_display' === $sec( 'mcupr_utility_bar' ) && 'mcupr_display' === $sec( 'mcupr_fs_menu' ), 'header: the toolbar and the full-screen menu button are with the menu settings' );
t( 'mcupr_org' === $sec( 'mcupr_name_th' ) && 'mcupr_org' === $sec( 'mcupr_org_tel' ) && 'mcupr_org' === $sec( 'mcupr_default_lang' ), 'organisation: name, contact and the main language stay together' );
$left = array();
foreach ( $m->controls() as $c ) { if ( $c->section && ! $m->get_section( $c->section ) ) { $left[] = $c->id . '→' . $c->section; } }
t( ! $left, 'no setting points to a section that does not exist' . ( $left ? ' (' . implode( ', ', $left ) . ')' : '' ) );

// 3. inside a section
$order = static function ( $s ) use ( $m ) { $cs = array(); foreach ( $m->controls() as $c ) { if ( $c->section === $s ) { $cs[] = $c; } } usort( $cs, static function ( $a, $b ) { return $a->priority <=> $b->priority; } ); return wp_list_pluck( $cs, 'id' ); };
$n = $order( 'mcupr_notice' );
t( 'mcupr_notice_on' === $n[0] && array_search( 'mcupr_notice_text_th', $n ) < array_search( 'mcupr_notice_from', $n ) && array_search( 'mcupr_notice_from', $n ) < array_search( 'mcupr_notice_gray', $n ), 'notice: on/off, then the words, then the dates, then the special style' );
$h = $order( 'mcupr_home' );
t( 'mcupr_hero_image' === $h[0] && array_search( 'mcupr_hero_l1_th', $h ) < array_search( 'mcupr_hero_btn1_th', $h ) && array_search( 'mcupr_hero_btn2_url', $h ) < array_search( 'mcupr_hero_height', $h ) && array_search( 'mcupr_hero_height', $h ) < array_search( 'mcupr_hero2_image', $h ) && array_search( 'mcupr_hero3_url', $h ) < array_search( 'mcupr_cta_title_th', $h ), 'home: picture, words, buttons, looks, slide 2, slide 3, then the rest' );
$l = $order( 'mcupr_loading' );
t( array( 'mcupr_preload', 'mcupr_preload_logo', 'mcupr_preload_logo_dark', 'mcupr_preload_text_show', 'mcupr_preload_text' ) === $l, 'loading screen: when, logo, dark logo, show text, text' );

// 4. the control center links
$d = mcupr_cc_data();
$bad = array();
foreach ( $d['items'] as $id => $it ) {
	foreach ( array_merge( array( $it['open'] ), isset( $it['add'] ) ? array( $it['add'] ) : array(), array_column( isset( $it['links'] ) ? $it['links'] : array(), 1 ) ) as $spec ) {
		if ( 0 !== strpos( $spec, 'cust:' ) ) { continue; }
		$arg = substr( $spec, 5 );
		if ( '' === $arg ) { continue; }
		$ok = 0 === strpos( $arg, 'panel:' ) ? (bool) $m->get_panel( substr( $arg, 6 ) ) : (bool) $m->get_section( $arg );
		if ( ! $ok ) { $bad[] = "$id → $spec"; }
	}
}
foreach ( $d['checklist'] as $st ) { if ( ! empty( $st['link'] ) && 0 === strpos( $st['link'], 'cust:' ) && '' !== substr( $st['link'], 5 ) && ! $m->get_section( substr( $st['link'], 5 ) ) && ! $m->get_panel( substr( $st['link'], 5 ) ) ) { $bad[] = 'checklist ' . $st['id']; } }
t( ! $bad, 'control center: every link into the Customizer opens a section or panel that exists' . ( $bad ? ' (' . implode( ', ', $bad ) . ')' : '' ) );
t( 'cust:colors' === $d['items']['colors']['open'], 'control center: "colours" opens the colour section, where the scheme really is' );
t( false !== strpos( mcupr_cc_url( 'cust:panel:mcupr_langs' ), 'autofocus[panel]=mcupr_langs' ) && false !== strpos( mcupr_cc_url( 'cust:mcupr_home' ), 'autofocus[section]=mcupr_home' ), 'control center: a link can open a panel or a section' );
$look = array_keys( array_filter( $d['items'], static function ( $i ) { return 'look' === $i['cat']; } ) );
t( array_search( 'notice', $look ) < array_search( 'identity', $look ) && array_search( 'identity', $look ) < array_search( 'display', $look ) && array_search( 'display', $look ) < array_search( 'credit', $look ) && 'customizer' === end( $look ), 'control center: the "look" menus follow the Customizer (notice, home, menus, then set-once, then reading, rarely used; all-in-one last)' );
foreach ( array( 'org', 'languages', 'footer', 'loading', 'credit' ) as $id ) { t( isset( $d['items'][ $id ] ), "control center: a menu for \"$id\"" ); }
$sys = array_keys( array_filter( $d['items'], static function ( $i ) { return 'system' === $i['cat']; } ) );
t( array_search( 'insights', $sys ) < array_search( 'status', $sys ) && array_search( 'status', $sys ) < array_search( 'speed', $sys ) && 'updates' === end( $sys ), 'control center: tools used often (insights, pictures) before occasional (maintenance) before upkeep (speed, updates)' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
