<?php
/** The footer board: the arrangement and its fall-backs, widget parts, the copyright line, the colour of the statistics card.   php php/footer-layout.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$all = array( 'contact', 'map', 'links', 'stats', 'w1', 'w2', 'w3' );
$keep = array( get_theme_mod( 'mcupr_footer_layout', null ), get_option( 'sidebars_widgets' ), get_option( 'widget_text' ) );
$clear = static function () { remove_theme_mod( 'mcupr_footer_layout' ); foreach ( array( 'contact', 'map', 'links', 'stats' ) as $b ) { remove_theme_mod( 'mcupr_footer_col_' . $b ); } };
$clear();
$show = static function ( $cols ) { return implode( ' | ', array_map( static function ( $c ) { return implode( '+', $c ); }, $cols ) ); };

// ---- the arrangement
t( 'contact+w1 | map+links+w2 | stats+w3' === $show( mcupr_footer_layout( $all ) ), 'the first arrangement: contact | map and links | statistics, a widget part at the end of each column' );
t( 'contact | map+links | stats' === $show( mcupr_footer_layout( array( 'contact', 'map', 'links', 'stats' ) ) ), 'without widgets: contact | map and links | statistics (balanced)' );
t( 'contact | links | stats' === $show( mcupr_footer_layout( array( 'contact', 'links', 'stats' ) ) ), 'without the map the links take the middle column' );
t( 'contact | stats' === $show( mcupr_footer_layout( array( 'contact', 'stats' ) ) ), 'a column with nothing in it is left out' );
set_theme_mod( 'mcupr_footer_layout', '{"1":["stats","contact"],"2":[],"3":["w2","map"],"0":["links"]}' );
t( 'stats+contact+w1 | w2+map+w3' === $show( mcupr_footer_layout( $all ) ), 'a saved board: the order of each column is kept (parts it does not mention come last in their usual column), an empty column is left out' );
t( ! in_array( 'links', array_merge( ...mcupr_footer_layout( $all ) ), true ), 'a part in "Hidden" is not shown' );
$arr = mcupr_footer_arrangement();
t( array( 'links' ) === $arr[0] && in_array( 'w1', $arr[1], true ) && in_array( 'w3', $arr[3], true ), 'parts the board does not mention go to their usual column (widgets 1 and 3)' );
set_theme_mod( 'mcupr_footer_layout', '{"1":["contact","contact","nonsense",7],"2":"map","3":["links"]}' );
$arr = mcupr_footer_arrangement();
t( array( 'contact' ) === array_slice( $arr[1], 0, 1 ) && 1 === count( array_keys( array_merge( ...array_values( $arr ) ), 'contact' ) ) && in_array( 'map', $arr[2], true ), 'an unknown part, a part twice and a wrong list are ignored' );
$s = json_decode( mcupr_footer_sanitize( 'broken{' ), true );
t( is_array( $s ) && 7 === count( array_merge( ...array_values( $s ) ) ), 'what is saved is valid JSON holding every part once, whatever came in' );
$clear();
set_theme_mod( 'mcupr_footer_col_stats', 1 );
t( 'contact+stats+w1 | map+links+w2 | w3' === $show( mcupr_footer_layout( $all ) ), 'while the board is not saved, the column choices of 1.79.0 still count' );
$clear();

// ---- widget parts
t( isset( $GLOBALS['wp_registered_sidebars']['footer-part-1'], $GLOBALS['wp_registered_sidebars']['footer-part-3'] ), 'three widget areas for the footer parts are registered' );
t( ! in_array( 'w1', mcupr_footer_present( array() ), true ), 'an empty widget part is not shown' );
update_option( 'widget_text', array( 2 => array( 'title' => 'My box', 'text' => 'hello footer', 'filter' => false ), '_multiwidget' => 1 ) );
$sw = (array) get_option( 'sidebars_widgets' ); $sw['footer-part-1'] = array( 'text-2' ); update_option( 'sidebars_widgets', $sw ); $GLOBALS['_wp_sidebars_widgets'] = $sw; $GLOBALS['wp_widget_factory']->widgets['WP_Widget_Text']->_register(); // (the widget is made known after the option is set)
t( in_array( 'w1', mcupr_footer_present( array() ), true ), 'a widget part with a widget in it is shown' );
ob_start(); mcupr_footer_block( 'w1', array() ); $h = ob_get_clean();
t( false !== strpos( $h, 'fwidgets' ) && false !== strpos( $h, 'hello footer' ) && false !== strpos( $h, 'My box' ), 'the widget prints inside the part' );

// ---- the copyright line
remove_theme_mod( 'mcupr_copyright' );
t( false !== strpos( mcupr_copyright_html(), '©' ) && false !== strpos( mcupr_copyright_html(), esc_html( mcupr_org_name() ) ), 'copyright: the automatic line has the year and the name' );
set_theme_mod( 'mcupr_copyright', 'Copy {year} by <strong>{name}</strong> – <a href="https://example.org/x">terms</a><script>alert(1)</script>' );
$c = mcupr_copyright_html();
t( false !== strpos( $c, wp_date( 'Y' ) ) && false !== strpos( $c, '<strong>' ) && false !== strpos( $c, 'href="https://example.org/x"' ) && false === strpos( $c, '<script' ), 'copyright: {year} and {name} are filled, a link and bold stay, a script goes' );
remove_theme_mod( 'mcupr_copyright' );

// ---- the colour of the statistics card
t( '' === mcupr_vc_color_style(), 'colour: the theme\'s own colour adds nothing' );
$bad = array();
foreach ( mcupr_vc_colors() as $k => $c ) { $l = mcupr_vc_luminance( $c[1] ); if ( 1.05 / ( $l + 0.05 ) < 4.5 ) { $bad[] = $k; } }
t( ! $bad, 'colour: white text on every ready-made colour is at least 4.5 : 1' . ( $bad ? ' (' . implode( ', ', $bad ) . ')' : '' ) );
t( '#ffffff' === mcupr_vc_readable( '#1e293b' ) && '#000000' === mcupr_vc_readable( '#ffeb3b' ) && '#000000' === mcupr_vc_readable( '#ffffff' ), 'colour: the text is white on dark colours and near-black on light ones' );
$worst = 99; foreach ( array( '#777777', '#808080', '#8a8a8a', '#ff8800', '#00aa88', '#cc7700' ) as $hex ) { $f = mcupr_vc_readable( $hex ); $lf = mcupr_vc_luminance( $f ); $lb = mcupr_vc_luminance( $hex ); $r = ( max( $lf, $lb ) + 0.05 ) / ( min( $lf, $lb ) + 0.05 ); $worst = min( $worst, $r ); }
t( $worst >= 4.5, 'colour: even on mid tones the better of white and black reads at 4.5 : 1 or more (worst ' . round( $worst, 2 ) . ')' );
set_theme_mod( 'mcupr_vc_color', 'green' );
t( false !== strpos( mcupr_vc_color_style(), '--vc-bg:#166534' ) && false !== strpos( mcupr_vc_color_style(), '--vc-fg:#ffffff' ), 'colour: a ready-made colour gives its background and a readable text' );
set_theme_mod( 'mcupr_vc_color', 'custom' ); set_theme_mod( 'mcupr_vc_color_custom', '#ffd54f' );
t( false !== strpos( mcupr_vc_color_style(), '--vc-bg:#ffd54f' ) && false !== strpos( mcupr_vc_color_style(), '--vc-fg:#000000' ), 'colour: my own colour is used, with dark text on a light one' );
set_theme_mod( 'mcupr_vc_color_custom', 'red; background:url(x)' );
t( '' === mcupr_vc_color_style(), 'colour: a wrong value (not a #hex) adds nothing' );
set_theme_mod( 'mcupr_vc_color', 'green' ); set_theme_mod( 'mcupr_vc_on', true );
ob_start(); mcupr_vc_card(); $card = ob_get_clean();
t( false !== strpos( $card, 'style="--vc-bg:#166534' ), 'colour: the card carries it' );
remove_theme_mod( 'mcupr_vc_color' ); remove_theme_mod( 'mcupr_vc_color_custom' ); remove_theme_mod( 'mcupr_vc_on' );

// ---- the Customizer
require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
$m = new WP_Customize_Manager(); $GLOBALS['wp_customize'] = $m; do_action( 'customize_register', $m );
$ok = true; foreach ( array( 'mcupr_footer_layout', 'mcupr_copyright', 'mcupr_vc_color', 'mcupr_vc_color_custom' ) as $id ) { $c = $m->get_control( $id ); if ( ! $c || 'mcupr_footer' !== $c->section ) { $ok = false; } }
t( $ok, 'the board, the copyright line and the card colour are in the footer section of the Customizer' );
t( 'mcupr_footer_layout' === $m->get_control( 'mcupr_footer_layout' )->type, 'the board is its own kind of control' );
$d = mcupr_footer_board_data();
t( 7 === count( $d['parts'] ) && array( 1, 2, 3, 0 ) === array_keys( $d['zones'] ), 'the script is given seven parts and four places (three columns and "hidden")' );

null === $keep[0] ? remove_theme_mod( 'mcupr_footer_layout' ) : set_theme_mod( 'mcupr_footer_layout', $keep[0] );
update_option( 'sidebars_widgets', $keep[1] ); $GLOBALS['_wp_sidebars_widgets'] = $keep[1]; false === $keep[2] ? delete_option( 'widget_text' ) : update_option( 'widget_text', $keep[2] );
echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
