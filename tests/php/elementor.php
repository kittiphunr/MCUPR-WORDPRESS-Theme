<?php
/** Elementor pages printed without the plugin: the saved data is read, the markup and the class names are Elementor's, the style comes from the settings, the widgets, the form, the report.   php php/elementor.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$fails = 0;
function t( $ok, $label ) {
	global $fails;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		$fails++;
	}
}

/** A page with one section, one column and the widgets given. */
function el_make( $elements, $title = 'EL test', $meta = array() ) {
	$pid = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => '<p>old content</p>',
		)
	);
	update_post_meta( $pid, '_elementor_edit_mode', 'builder' );
	update_post_meta( $pid, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
	update_post_meta( $pid, '_elementor_template_type', 'wp-page' );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $pid, $k, $v );
	}
	return $pid;
}

function el_section( $widgets, $section_settings = array(), $column_settings = array() ) {
	return array(
		array(
			'id'       => 'sec001',
			'elType'   => 'section',
			'settings' => $section_settings,
			'elements' => array(
				array(
					'id'       => 'col001',
					'elType'   => 'column',
					'settings' => array_merge( array( '_column_size' => 100 ), $column_settings ),
					'elements' => $widgets,
				),
			),
		),
	);
}

function el_widget( $type, $settings, $id = 'wid001' ) {
	return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings );
}

/** Render a page and give back its HTML (the state is fresh each time). */
function el_render( $pid ) {
	mcupr_el_state_ref( true );
	return mcupr_el_render_post( $pid );
}

/** Render and give back the CSS the settings produced. */
function el_css( $pid ) {
	mcupr_el_state_ref( true );
	mcupr_el_render_post( $pid );
	$st = mcupr_el_state();
	return $st['css'];
}

$made = array();

/* ---------------------------------------------------------------- reading the data */
$pid = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'สวัสดี', 'header_size' => 'h2' ) ) ) ) );
$made[] = $pid;
t( null !== mcupr_el_data( $pid ) && 1 === count( mcupr_el_data( $pid ) ), 'the saved data of a page is read back as a list of sections' );
t( mcupr_el_is_built( $pid ), 'a page saved by Elementor is recognised' );
$plain = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'plain' ) );
$made[] = $plain;
t( ! mcupr_el_is_built( $plain ) && null === mcupr_el_data( $plain ), 'a normal page is left alone' );
$half = el_make( array(), 'half' );
update_post_meta( $half, '_elementor_data', '' );
$made[] = $half;
t( null === mcupr_el_data( $half ) && ! mcupr_el_is_built( $half ), 'a page whose data is empty or broken is left alone (no error)' );

/* ---------------------------------------------------------------- the markup */
$h = el_render( $pid );
t( false !== strpos( $h, 'data-elementor-type="wp-page"' ) && false !== strpos( $h, 'class="elementor elementor-' . $pid . '"' ), 'the page is wrapped the way Elementor wrapped it' );
t( false !== strpos( $h, 'elementor-section elementor-top-section elementor-element elementor-element-sec001' ) && false !== strpos( $h, '<div class="elementor-container' ), 'a section keeps its classes and its container' );
t( false !== strpos( $h, 'elementor-column elementor-col-100' ) && false !== strpos( $h, 'elementor-widget-wrap elementor-element-populated' ), 'a column keeps its width class and the populated wrapper' );
t( false !== strpos( $h, 'elementor-widget elementor-widget-heading' ) && false !== strpos( $h, 'data-widget_type="heading.default"' ) && false !== strpos( $h, '<h2 class="elementor-heading-title' ), 'a widget keeps its classes, its type and its own markup' );
t( false !== strpos( $h, 'สวัสดี' ) && false === strpos( $h, 'old content' ), 'the text comes from the Elementor data, not from the old content of the post' );

/* ---------------------------------------------------------------- the style from the settings */
$pid2 = el_make(
	el_section(
		array(
			el_widget(
				'heading',
				array(
					'title'                   => 'หัวข้อ',
					'align'                   => 'center',
					'align_mobile'            => 'left',
					'title_color'             => '#c2185b',
					'typography_typography'   => 'custom',
					'typography_font_size'    => array( 'size' => 42, 'unit' => 'px' ),
					'typography_font_size_mobile' => array( 'size' => 24, 'unit' => 'px' ),
					'typography_font_weight'  => '700',
					'_margin'                 => array( 'top' => '10', 'right' => '0', 'bottom' => '20', 'left' => '0', 'unit' => 'px' ),
				)
			),
		),
		array(
			'padding'               => array( 'top' => '40', 'right' => '0', 'bottom' => '40', 'left' => '0', 'unit' => 'px' ),
			'background_background' => 'classic',
			'background_color'      => '#f5f5f5',
			'content_width'         => array( 'size' => 1140, 'unit' => 'px' ),
			'layout'                => 'boxed',
		),
		array( '_inline_size' => 60, '_inline_size_tablet' => 100 )
	),
	'EL style'
);
$made[] = $pid2;
$css = el_css( $pid2 );
t( false !== strpos( $css, '.elementor-' . $pid2 . ' .elementor-element.elementor-element-sec001{padding:40px 0px 40px 0px}' ), 'a section writes its padding' );
t( false !== strpos( $css, 'background-color:#f5f5f5' ) && false !== strpos( $css, '> .elementor-container{max-width:1140px}' ), 'a section writes its background and the width of its content' );
t( false !== strpos( $css, '@media (min-width:768px){.elementor-' . $pid2 . ' .elementor-element.elementor-element-col001{width:60%}}' ) && false !== strpos( $css, '@media (max-width:1024px){.elementor-' . $pid2 . ' .elementor-element.elementor-element-col001{width:100%}}' ), 'a column writes its width from tablets up (below that each column is a row of its own), and the width for tablets' );
t( false !== strpos( $css, 'elementor-element-wid001{text-align:center}' ) && false !== strpos( $css, '@media (max-width:767px){.elementor-' . $pid2 . ' .elementor-element.elementor-element-wid001{text-align:left}}' ), 'a widget writes its alignment per device' );
t( false !== strpos( $css, 'color:#c2185b' ) && false !== strpos( $css, 'font-size:42px;font-weight:700' ) && false !== strpos( $css, '@media (max-width:767px){.elementor-' . $pid2 . ' .elementor-element.elementor-element-wid001 .elementor-heading-title{font-size:24px}' ), 'typography: colour, size and weight, and the size for phones' );
t( false !== strpos( $css, '.elementor-widget-container{margin:10px 0px 20px 0px}' ), 'the margin of a widget goes on its container, as Elementor put it' );
t( false === strpos( $css, 'javascript' ) && false === strpos( $css, '</' ), 'nothing that is not CSS gets into the style' );

/* ---------------------------------------------------------------- a value that is not allowed */
$bad = el_make(
	el_section(
		array(
			el_widget(
				'heading',
				array(
					'title'       => 'x',
					'title_color' => 'red; } body { display:none } .x {',
					'_margin'     => array( 'top' => '10px"><script>alert(1)</script>', 'unit' => 'px' ),
					'_element_id' => 'bad id"><script>',
				)
			),
		),
		array( 'background_background' => 'classic', 'background_image' => array( 'url' => 'javascript:alert(1)' ) )
	),
	'EL bad'
);
$made[] = $bad;
$bcss = el_css( $bad );
$bh   = el_render( $bad );
t( false === strpos( $bcss, 'display:none' ) && false === strpos( $bcss, 'alert(' ) && false === strpos( $bcss, 'javascript:' ), 'a colour, a size or an address that is not one is dropped instead of written into the style' );
t( false === strpos( $bh, '<script>' ) && false === strpos( $bh, 'id="bad id' ), 'markup is escaped: a made-up id or tags in a setting cannot get out' );

/* ---------------------------------------------------------------- widgets */
$tests = array(
	'text-editor'  => array( array( 'editor' => '<p>ข้อความ</p>' ), 'elementor-text-editor', 'ข้อความ' ),
	'button'       => array( array( 'text' => 'กด', 'link' => array( 'url' => 'https://example.org/', 'is_external' => 'on', 'nofollow' => 'on' ) ), 'elementor-button', 'rel="nofollow noopener"' ),
	'image'        => array( array( 'image' => array( 'url' => 'https://example.org/a.jpg' ), 'caption_source' => 'custom', 'caption' => 'คำบรรยาย' ), 'wp-caption', 'คำบรรยาย' ),
	'divider'      => array( array( 'style' => 'dotted' ), 'elementor-divider-separator', '' ),
	'spacer'       => array( array( 'space' => array( 'size' => 60, 'unit' => 'px' ) ), 'elementor-spacer-inner', '' ),
	'icon'         => array( array( 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ), 'view' => 'stacked', 'shape' => 'circle' ), 'elementor-view-stacked elementor-shape-circle', 'fas fa-star' ),
	'icon-box'     => array( array( 'title_text' => 'หัว', 'description_text' => 'คำอธิบาย', 'selected_icon' => array( 'value' => 'fas fa-star' ) ), 'elementor-icon-box-title', 'คำอธิบาย' ),
	'image-box'    => array( array( 'title_text' => 'หัว', 'image' => array( 'url' => 'https://example.org/a.jpg' ) ), 'elementor-image-box-img', 'หัว' ),
	'icon-list'    => array( array( 'icon_list' => array( array( '_id' => 'a1', 'text' => 'หนึ่ง' ) ) ), 'elementor-icon-list-item', 'หนึ่ง' ),
	'social-icons' => array( array( 'social_icon_list' => array( array( '_id' => 's1', 'social_icon' => array( 'value' => 'fab fa-facebook' ), 'link' => array( 'url' => 'https://facebook.com/x' ) ) ) ), 'elementor-social-icon-facebook', 'facebook.com/x' ),
	'star-rating'  => array( array( 'rating' => 4.5, 'rating_scale' => 5 ), 'elementor-star-rating', '4.5/5' ),
	'counter'      => array( array( 'ending_number' => 12345, 'title' => 'คน' ), 'elementor-counter-number', '12,345' ),
	'progress'     => array( array( 'percent' => array( 'size' => 70 ), 'title' => 'งาน' ), 'elementor-progress-bar', 'width:70%' ),
	'testimonial'  => array( array( 'testimonial_content' => 'ดีมาก', 'testimonial_name' => 'ผู้ใช้' ), 'elementor-testimonial-content', 'ผู้ใช้' ),
	'alert'        => array( array( 'alert_type' => 'warning', 'alert_title' => 'ระวัง', 'alert_description' => 'ข้อความ' ), 'elementor-alert-warning', 'ระวัง' ),
	'tabs'         => array( array( 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'แท็บ', 'tab_content' => '<p>ก</p>' ) ) ), 'elementor-tabs-wrapper', 'แท็บ' ),
	'accordion'    => array( array( 'tabs' => array( array( '_id' => 'a1', 'tab_title' => 'คำถาม', 'tab_content' => '<p>ตอบ</p>' ) ) ), 'elementor-accordion-item', 'คำถาม' ),
	'toggle'       => array( array( 'tabs' => array( array( '_id' => 'g1', 'tab_title' => 'หัว', 'tab_content' => '<p>ก</p>' ) ) ), 'elementor-toggle-item', 'หัว' ),
	'html'         => array( array( 'html' => '<div class="mine">เอง</div>' ), 'class="mine"', 'เอง' ),
	'menu-anchor'  => array( array( 'anchor' => 'go here' ), 'elementor-menu-anchor', 'id="go_here"' ),
	'google_maps'  => array( array( 'address' => 'Bangkok', 'zoom' => array( 'size' => 12 ) ), 'elementor-custom-embed', 'maps.google.com' ),
	'video'        => array( array( 'video_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ), 'elementor-fit-aspect-ratio', 'youtube.com/embed/dQw4w9WgXcQ' ),
	'price-table'  => array( array( 'heading' => 'แพ็กเกจ', 'price' => '990', 'currency_symbol' => 'baht', 'features_list' => array( array( '_id' => 'f1', 'item_text' => 'ข้อหนึ่ง' ) ) ), 'elementor-price-table__price', 'ข้อหนึ่ง' ),
	'price-list'   => array( array( 'price_list' => array( array( '_id' => 'p1', 'title' => 'รายการ', 'price' => '50' ) ) ), 'elementor-price-list', 'รายการ' ),
	'call-to-action' => array( array( 'title' => 'ชวน', 'description' => 'มาเลย', 'button' => 'คลิก', 'link' => array( 'url' => '/go/' ) ), 'elementor-cta__title', 'มาเลย' ),
	'flip-box'     => array( array( 'title_text_a' => 'หน้า', 'title_text_b' => 'หลัง' ), 'elementor-flip-box__back', 'หลัง' ),
	'countdown'    => array( array( 'due_date' => '2030-01-01 00:00' ), 'elementor-countdown-wrapper', 'elementor-countdown-days' ),
	'animated-headline' => array( array( 'before_text' => 'เรา', 'highlighted_text' => 'เก่ง' ), 'elementor-headline', 'เก่ง' ),
	'slides'       => array( array( 'slides' => array( array( '_id' => 'sl1', 'heading' => 'สไลด์', 'description' => 'คำ' ) ) ), 'elementor-slide', 'สไลด์' ),
	'image-gallery' => array( array( 'wp_gallery' => array( array( 'url' => 'https://example.org/a.jpg', 'id' => 0 ) ) ), 'elementor-image-gallery', 'gallery-item' ),
	'image-carousel' => array( array( 'carousel' => array( array( 'url' => 'https://example.org/a.jpg', 'id' => 0 ) ), 'slides_to_show' => 3 ), 'elementor-image-carousel', 'swiper-slide' ),
	'shortcode'    => array( array( 'shortcode' => '[mcupr_tools]' ), 'elementor-shortcode', '' ),
);
$bad_widgets = array();
foreach ( $tests as $type => $spec ) {
	$wp_id  = el_make( el_section( array( el_widget( $type, $spec[0] ) ) ), 'EL ' . $type );
	$made[] = $wp_id;
	$out    = el_render( $wp_id );
	$ok     = false !== strpos( $out, 'elementor-widget-' . $type ) && ( '' === $spec[1] || false !== strpos( $out, $spec[1] ) ) && ( '' === $spec[2] || false !== strpos( $out, $spec[2] ) );
	if ( ! $ok ) {
		$bad_widgets[] = $type;
	}
}
t( ! $bad_widgets, count( $tests ) . ' widgets print their own markup' . ( $bad_widgets ? ': ' . implode( ', ', $bad_widgets ) . ' did not' : '' ) );

/* ---------------------------------------------------------------- a widget that is not supported */
$un = el_make( el_section( array( el_widget( 'lottie', array() ), el_widget( 'heading', array( 'title' => 'ยังอยู่' ), 'wid002' ) ) ), 'EL unsupported' );
$made[] = $un;
wp_set_current_user( 0 );
$uh = el_render( $un );
t( false === strpos( $uh, 'mcupr-el-missing' ) && false !== strpos( $uh, 'ยังอยู่' ), 'a widget the theme cannot print shows nothing to a visitor; the rest of the page is still there' );
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
$uh2 = el_render( $un );
$st  = mcupr_el_state();
t( false !== strpos( $uh2, 'mcupr-el-missing' ) && isset( $st['unsupported']['lottie'] ), 'an editor who is logged in sees a note in its place, and it is counted for the report' );
wp_set_current_user( 0 );

/* ---------------------------------------------------------------- containers (the newer Elementor) */
$con = el_make(
	array(
		array(
			'id'       => 'con001',
			'elType'   => 'container',
			'settings' => array( 'flex_direction' => 'row', 'flex_gap' => array( 'size' => 20, 'unit' => 'px', 'column' => '20', 'row' => '10' ), 'boxed_width' => array( 'size' => 1200, 'unit' => 'px' ), 'padding' => array( 'top' => '30', 'bottom' => '30', 'unit' => 'px' ) ),
			'elements' => array( el_widget( 'heading', array( 'title' => 'ในกล่อง' ) ) ),
		),
	),
	'EL container'
);
$made[] = $con;
$ch = el_render( $con );
$cc = el_css( $con );
t( false !== strpos( $ch, 'e-con' ) && false !== strpos( $ch, 'e-con-boxed' ) && false !== strpos( $ch, 'e-con-inner' ) && false !== strpos( $ch, 'ในกล่อง' ), 'a flexbox container is printed with its classes and its inner box' );
t( false !== strpos( $cc, '--flex-direction:row' ) && false !== strpos( $cc, '--row-gap:10px' ) && false !== strpos( $cc, '--column-gap:20px' ) && false !== strpos( $cc, '--content-width:1200px' ) && false !== strpos( $cc, '--padding-top:30px' ), 'a container writes the properties the layout is built on' );

/* ---------------------------------------------------------------- what the page needs */
$need = el_make( el_section( array( el_widget( 'tabs', array( 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'a', 'tab_content' => 'b' ) ) ) ), el_widget( 'icon', array( 'selected_icon' => array( 'value' => 'fas fa-star' ) ), 'wid002' ) ) ), 'EL needs' );
$made[] = $need;
el_render( $need );
$st = mcupr_el_state();
t( ! empty( $st['js']['tabs'] ) && true === $st['icons'], 'the page says what it needs: the script for the tabs and the icon font' );
$a = mcupr_el_assets( array( $need ) );
t( ! empty( $a['js'] ) && true === $a['icons'] && is_array( $a['files'] ), 'the list of what to load is built from it' );

/* ---------------------------------------------------------------- the kit (site settings of Elementor) */
$kit = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'kit' ) );
$made[] = $kit;
update_post_meta( $kit, '_elementor_page_settings', array( 'system_colors' => array( array( '_id' => 'primary', 'color' => '#6EC1E4' ) ), 'container_width' => array( 'size' => 1200, 'unit' => 'px' ) ) );
$old_kit = get_option( 'elementor_active_kit' );
update_option( 'elementor_active_kit', $kit );
$kcss = mcupr_el_kit_css();
t( false !== strpos( $kcss, '--e-global-color-primary:#6EC1E4' ) && false !== strpos( $kcss, 'max-width:1200px' ), 'the site settings of Elementor (colours, width) become CSS variables' );
$gl = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'g', '__globals__' => array( 'title_color' => 'globals/colors?id=primary' ) ) ) ) ), 'EL global colour' );
$made[] = $gl;
t( false !== strpos( el_css( $gl ), 'color:var(--e-global-color-primary)' ), 'a colour taken from the site settings is written as its variable' );
false === $old_kit ? delete_option( 'elementor_active_kit' ) : update_option( 'elementor_active_kit', $old_kit );

/* ---------------------------------------------------------------- dynamic tags */
$dyn = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'x', '__dynamic__' => array( 'title' => '[elementor-tag id="aa" name="post-title" settings="%7B%7D"]' ) ) ) ) ), 'ชื่อของหน้า' );
$made[] = $dyn;
t( false !== strpos( el_render( $dyn ), 'ชื่อของหน้า' ), 'a dynamic tag (the title of the post) gives its value' );

/* ---------------------------------------------------------------- the template shortcode */
$tplpid = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'ส่วนที่บันทึกไว้' ) ) ) ), 'EL template' );
$made[] = $tplpid;
update_post_meta( $tplpid, '_elementor_template_type', 'section' );
t( false !== strpos( do_shortcode( '[elementor-template id="' . $tplpid . '"]' ), 'ส่วนที่บันทึกไว้' ), 'a saved template can be put on a page with [elementor-template id="…"]' );
t( '' === do_shortcode( '[elementor-template id="999999"]' ), 'a template that does not exist prints nothing' );

/* ---------------------------------------------------------------- the form */
$form_el = el_widget(
	'form',
	array(
		'form_name'     => 'ติดต่อ',
		'email_to'      => 'to@example.org',
		'button_text'   => 'ส่ง',
		'email_subject' => 'ข้อความใหม่',
		'form_fields'   => array(
			array( '_id' => 'n', 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'ชื่อ', 'required' => 'true', 'width' => '50' ),
			array( '_id' => 'e', 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'อีเมล', 'required' => 'true', 'width' => '50' ),
			array( '_id' => 'm', 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => 'ข้อความ', 'rows' => 5 ),
		),
	),
	'frm001'
);
$fp     = el_make( el_section( array( $form_el ) ), 'EL form' );
$made[] = $fp;
$fh     = el_render( $fp );
t( false !== strpos( $fh, '<form class="elementor-form"' ) && false !== strpos( $fh, 'name="form_fields[name]"' ) && false !== strpos( $fh, 'type="email"' ) && false !== strpos( $fh, '<textarea' ), 'the form is printed with its fields and their kinds' );
t( false !== strpos( $fh, 'required="required"' ) && false !== strpos( $fh, 'name="mcupr_hp"' ) && false !== strpos( $fh, 'name="action" value="mcupr_el_form"' ) && false !== strpos( $fh, '_wpnonce' ), 'the form keeps its required fields, has a trap for robots, a nonce and the address that takes it' );
t( null !== mcupr_el_find_widget( mcupr_el_data( $fp ), 'frm001' ), 'the form of a page is found again by its id when the answers arrive' );

/** Send the form the way a visitor would, and say where it was sent back to and what the e-mail was. */
function el_send( $pid, $form_id, $fields, $nonce = null ) {
	$GLOBALS['el_mail'] = null;
	$_POST = array_merge(
		array(
			'action'      => 'mcupr_el_form',
			'post_id'     => $pid,
			'form_id'     => $form_id,
			'_wpnonce'    => null === $nonce ? wp_create_nonce( 'mcupr_el_form_' . $form_id ) : $nonce,
			'form_fields' => $fields,
		)
	);
	$_REQUEST = $_POST;
	$where    = '';
	$catch    = static function ( $loc ) use ( &$where ) {
		$where = $loc;
		throw new Exception( 'redirect' );
	};
	add_filter( 'wp_redirect', $catch, 99 );
	try {
		mcupr_el_form_submit();
	} catch ( Exception $e ) {
		// the redirect
	}
	remove_filter( 'wp_redirect', $catch, 99 );
	$_POST    = array();
	$_REQUEST = array();
	return array( $where, $GLOBALS['el_mail'] );
}
add_filter(
	'pre_wp_mail',
	static function ( $null, $atts ) {
		$GLOBALS['el_mail'] = $atts;
		return true;
	},
	10,
	2
);
list( $where, $mail ) = el_send( $fp, 'frm001', array( 'name' => 'ผู้ส่ง', 'email' => 'me@example.org', 'message' => "บรรทัดหนึ่ง\nสอง" ) );
t( false !== strpos( $where, 'mcupr_form=frm001-ok' ) && is_array( $mail ), 'a form that is filled in correctly is sent and the visitor is told it went' );
t( $mail && 'to@example.org' === $mail['to'] && false !== strpos( $mail['message'], 'ผู้ส่ง' ) && false !== strpos( $mail['message'], 'บรรทัดหนึ่ง' ) && in_array( 'Reply-To: me@example.org', (array) $mail['headers'], true ), 'the e-mail goes to the address of the form, with the answers and the sender to reply to' );
list( $where2 ) = el_send( $fp, 'frm001', array( 'name' => '', 'email' => 'me@example.org' ) );
t( false !== strpos( $where2, 'frm001-err' ), 'a form with a required field left empty is not sent' );
list( $where3 ) = el_send( $fp, 'frm001', array( 'name' => 'a', 'email' => 'not-an-address' ) );
t( false !== strpos( $where3, 'frm001-err' ), 'an e-mail address that is not one is not sent' );
list( $where4, $mail4 ) = el_send( $fp, 'frm001', array( 'name' => 'a', 'email' => 'me@example.org' ), 'wrong-nonce' );
t( false !== strpos( $where4, 'frm001-err' ) && null === $mail4, 'a form sent without the right nonce is refused' );
$GLOBALS['el_mail'] = null;
$_POST = array( 'action' => 'mcupr_el_form', 'post_id' => $fp, 'form_id' => 'frm001', '_wpnonce' => wp_create_nonce( 'mcupr_el_form_frm001' ), 'form_fields' => array( 'name' => 'a', 'email' => 'me@example.org' ), 'mcupr_hp' => 'robot' );
$_REQUEST = $_POST;
$where5 = '';
$c5 = static function ( $loc ) use ( &$where5 ) {
	$where5 = $loc;
	throw new Exception( 'r' ); };
add_filter( 'wp_redirect', $c5, 99 );
try {
	mcupr_el_form_submit();
} catch ( Exception $e ) {
	// the redirect
}
remove_filter( 'wp_redirect', $c5, 99 );
$_POST = array();
$_REQUEST = array();
t( false !== strpos( $where5, 'frm001-err' ) && null === $GLOBALS['el_mail'], 'a robot that fills the hidden field sends nothing' );

/* ---------------------------------------------------------------- hide title, template, body classes */
$hid    = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'x' ) ) ) ), 'EL hidden title' );
$made[] = $hid;
update_post_meta( $hid, '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
t( mcupr_el_hide_title( $hid ) && ! mcupr_el_hide_title( $pid ), '“hide title” of the page settings is followed' );
$fw     = el_make( el_section( array( el_widget( 'heading', array( 'title' => 'x' ) ) ) ), 'EL full width' );
$made[] = $fw;
update_post_meta( $fw, '_wp_page_template', 'elementor_header_footer' );
t( mcupr_el_hide_title( $fw ), 'the “full width” template of Elementor also means no title band' );
t( is_readable( get_theme_file_path( 'elementor-canvas.php' ) ) && is_readable( get_theme_file_path( 'elementor-page.php' ) ), 'the two templates of the theme are there (canvas and the normal page)' );

/* ---------------------------------------------------------------- the report */
$inv = mcupr_el_inventory();
t( isset( $inv[ $un ] ) && in_array( 'lottie', $inv[ $un ]['unsupported'], true ) && isset( $inv[ $un ]['widgets']['heading'] ), 'the report lists every page, its widgets and what cannot be printed' );
t( isset( $inv[ $pid ] ) && 'page' === $inv[ $pid ]['type'] && ! $inv[ $pid ]['unsupported'], 'a page whose widgets are all supported says so' );
t( mcupr_el_site_has_content(), 'the admin page is offered because the site has Elementor content' );

/* ---------------------------------------------------------------- the style files of Elementor */
$up  = wp_get_upload_dir();
$dir = $up['basedir'] . '/elementor/css';
wp_mkdir_p( $dir );
file_put_contents( $dir . '/post-' . $pid2 . '.css', '.elementor-' . $pid2 . ' .elementor-element.elementor-element-sec001{padding:1px}' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
update_option( 'mcupr_el_use_files', 'yes' );
mcupr_el_state_ref( true );
mcupr_el_render_post( $pid2 );
$a2 = mcupr_el_assets( array( $pid2 ) );
t( isset( $a2['files'][ 'mcupr-el-post-' . $pid2 ] ) && false === strpos( $a2['css'], 'elementor-element-sec001' ), 'when the style file Elementor wrote is still there it is used, and the rules of that page are not written again' );
update_option( 'mcupr_el_use_files', 'no' );
mcupr_el_state_ref( true );
mcupr_el_render_post( $pid2 );
$a3 = mcupr_el_assets( array( $pid2 ) );
t( ! $a3['files'] && false !== strpos( $a3['css'], 'elementor-element-sec001' ), 'switched off, the theme writes the style itself and loads no file' );
update_option( 'mcupr_el_use_files', 'yes' );
@unlink( $dir . '/post-' . $pid2 . '.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

/* ---------------------------------------------------------------- the plugin comes back */
t( ! mcupr_el_plugin_active(), 'without the plugin the theme prints the pages' );
t( null !== mcupr_el_data( $pid ) && 'builder' === get_post_meta( $pid, '_elementor_edit_mode', true ), 'nothing of Elementor is changed: the data and the mark are still as they were, so the plugin can come back' );

/* ---------------------------------------------------------------- clean up */
foreach ( $made as $m ) {
	wp_delete_post( $m, true );
}
delete_option( 'mcupr_el_report' );
delete_transient( 'mcupr_el_count' );
echo $fails ? "FAILED: $fails\n" : "ALL PASSED\n";
exit( $fails ? 1 : 0 );
