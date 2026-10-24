<?php
/** Languages: the lines that add a language, which languages are on, the texts of an added language, the Customizer tabs (one per language).   php php/languages.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$mods = array( 'mcupr_langs_extra', 'mcupr_lang_on_ja', 'mcupr_lang_on_zh', 'mcupr_lang_on_en', 'mcupr_default_lang', 'mcupr_hero_l1_ko', 'mcupr_hero_l1_th', 'mcupr_hero_l1_en' );
$save = array();
foreach ( $mods as $m ) { $save[ $m ] = get_theme_mod( $m, null ); remove_theme_mod( $m ); }

// --- the lines
$p = mcupr_lang_extra_parse( "ko\nvi | Tiếng Việt | Vietnamese | vi | vi\nsv | Svenska | Swedish | sv | sv_SE\n" );
t( array( 'ko', 'vi', 'sv' ) === array_keys( $p ) && '한국어' === $p['ko']['label'] && 'Korean' === $p['ko']['name'] && 'ko_KR' === $p['ko']['locale'], 'just a code from the list fills in the rest; a full line is taken as written' );
t( 'sv_SE' === $p['sv']['locale'] && 'sv' === $p['sv']['html'], 'a language that is not in the list needs the full line' );
$bad = mcupr_lang_extra_parse( "th\nen | English | English | en | en_US\nurl\non\nx\nkoreanish\nzz\nxx | | | |\nko | 한국어 | Korean | k | ko_KR\nko | 한국어 | Korean | ko | k\nqq | <b>Q</b> | Q | qq | qq\nko\nko" );
t( array( 'qq', 'ko' ) === array_keys( $bad ), 'a built-in code, a reserved one (url, on), a wrong length, no name, a bad html lang or locale and a repeat are all left out (' . implode( ',', array_keys( $bad ) ) . ')' );
t( false === strpos( $bad['qq']['label'], '<' ), 'tags are removed from the name' );
t( "ko | 한국어 | Korean | ko | ko_KR" === mcupr_lang_extra_clean( "ko\nbogus" ), 'the control keeps one complete, valid line for each language' );
foreach ( array_keys( mcupr_lang_presets() ) as $c ) { $pr = mcupr_lang_presets()[ $c ]; $ok = isset( $pr[3] ) && 4 === count( $pr ) && '' !== $pr[0] && 1 === count( mcupr_lang_extra_parse( $c ) ); if ( ! $ok ) { break; } }
t( $ok, 'every language of the list can be added by its code alone' );

// --- which languages are on
t( array( 'th', 'en', 'zh', 'ja' ) === array_keys( mcupr_languages() ), 'nothing set: the four built-in languages, as before' );
set_theme_mod( 'mcupr_lang_on_ja', false ); set_theme_mod( 'mcupr_lang_on_zh', false );
t( array( 'th', 'en' ) === array_keys( mcupr_languages() ), 'Chinese and Japanese switched off: they leave the list' );
set_theme_mod( 'mcupr_default_lang', 'en' ); set_theme_mod( 'mcupr_lang_on_en', false );
t( isset( mcupr_languages()['en'] ), 'the default language cannot be switched off' );
remove_theme_mod( 'mcupr_default_lang' ); remove_theme_mod( 'mcupr_lang_on_en' );
set_theme_mod( 'mcupr_langs_extra', "ko\nvi" );
t( array( 'th', 'en', 'ko', 'vi' ) === array_keys( mcupr_languages() ) && '한국어' === mcupr_languages()['ko']['label'] && 'ko' === mcupr_languages()['ko']['html'], 'added languages follow the built-in ones, with their own name and <html lang>' );
set_theme_mod( 'mcupr_default_lang', 'xx' );
t( 'th' === mcupr_default_lang(), 'a default language that does not exist falls back to Thai' );
set_theme_mod( 'mcupr_default_lang', 'ja' );
t( 'ja' === mcupr_default_lang() && isset( mcupr_languages()['ja'] ), 'the default language stays on even when its box is unticked' );
set_theme_mod( 'mcupr_default_lang', 'ko' );
t( 'ko' === mcupr_default_lang(), 'an added language can be the default one' );
remove_theme_mod( 'mcupr_default_lang' );

// --- texts of an added language
set_theme_mod( 'mcupr_hero_l1_th', 'หัวข้อไทย' ); set_theme_mod( 'mcupr_hero_l1_ko', '한국어 제목' );
t( '한국어 제목' === mcupr_text_mod( 'mcupr_hero_l1', 'ko' ) && 'หัวข้อไทย' === mcupr_text_mod( 'mcupr_hero_l1', 'th' ), 'an added language reads its own text' );
t( 'หัวข้อไทย' === mcupr_text_mod( 'mcupr_hero_l1', 'vi' ), 'a language with nothing written shows the default language text' );
t( 'en' === mcupr_normalize_ui_lang( 'ko' ), 'the buttons and menus of the theme itself are English for an added language');

// --- the Customizer: one tab for each language
function cz_build() {
	$GLOBALS['wp_customize'] = new WP_Customize_Manager();
	do_action( 'customize_register', $GLOBALS['wp_customize'] );
	return $GLOBALS['wp_customize'];
}
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
remove_theme_mod( 'mcupr_lang_on_ja' ); remove_theme_mod( 'mcupr_lang_on_zh' );
$cz = cz_build();
$secs = array_keys( array_filter( $cz->sections(), static function ( $s ) { return 'mcupr_langs' === $s->panel; } ) );
t( array( 'mcupr_langs_set', 'mcupr_lang_en', 'mcupr_lang_zh', 'mcupr_lang_ja', 'mcupr_lang_ko', 'mcupr_lang_vi' ) === $secs, 'the panel has the settings tab and one tab for each language except the default (' . implode( ',', $secs ) . ')' );
$c = $cz->get_control( 'mcupr_hero_l1_en' );
t( 'mcupr_lang_en' === $c->section && 'mcupr_lang_ko' === $cz->get_control( 'mcupr_hero_l1_ko' )->section && 'mcupr_home' === $cz->get_control( 'mcupr_hero_l1_th' )->section, 'the text fields of English and Korean are in their tabs; the default language\'s stay where they were' );
$bad_label = false; foreach ( $cz->controls() as $id => $ct ) { if ( preg_match( '/^mcupr_.+_(th|en|zh|ja|ko|vi)$/', $id ) && 0 !== strpos( $id, 'mcupr_lang_on_' ) && preg_match( '/\((?:Thai|English|Chinese|Japanese|Korean|Vietnamese|ไทย|อังกฤษ|จีน|ญี่ปุ่น)\)/u', (string) $ct->label ) ) { $bad_label = $id; } }
t( false === $bad_label, 'no field has the language in brackets after its label any more' . ( $bad_label ? ' (' . $bad_label . ')' : '' ) );
$n_en = 0; foreach ( $cz->controls() as $id => $ct ) { if ( 'mcupr_lang_en' === $ct->section ) { $n_en++; } }
t( $n_en >= 12, 'the English tab holds its fields (' . $n_en . ')' );
t( 'mcupr_langs_set' === $cz->get_control( 'mcupr_lang_on_en' )->section && 'mcupr_langs_set' === $cz->get_control( 'mcupr_langs_extra' )->section, 'the on/off boxes and the "add languages" box stay in the settings tab' );
t( null !== $cz->get_control( 'mcupr_hero_btn1_url' ) && 'mcupr_home' === $cz->get_control( 'mcupr_hero_btn1_url' )->section, 'a setting whose name only ends like a language code (…_url) is not moved' );
set_theme_mod( 'mcupr_lang_on_zh', false );
$cz2 = cz_build();
t( null === $cz2->get_section( 'mcupr_lang_zh' ) && null !== $cz2->get_section( 'mcupr_lang_en' ) && null !== $cz2->get_control( 'mcupr_lang_on_zh' ), 'a language switched off has no tab any more, but its on/off box stays so it can be brought back' );
foreach ( $save as $m => $v ) { null === $v ? remove_theme_mod( $m ) : set_theme_mod( $m, $v ); }
echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
