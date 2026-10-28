<?php
/**
 * The bottom bar of a phone (below 1100 px, where the menu is a drawer): Home · News · [the big round button that opens the
 * whole menu] · Events · Search. It is in every menu layout (top, bar, side) and every colour mode. The round button is the
 * same menu button as in the header (it opens the same drawer), so nothing is lost when the bar is switched off.
 *
 * Customizer → Animation, news & reading: "Bottom bar on phones". Items can be changed with the `mcupr_dock_items` filter
 * (each: key, label, url, icon, current (bool)).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_dock_enabled() {
	return (bool) get_theme_mod( 'mcupr_dock', true ) && ! is_customize_preview() && ! is_embed() && ! is_404() && ! is_admin() && ! is_feed() && ! is_page_template( 'elementor-canvas.php' );
}

/** The four places beside the round button (two on each side). */
function mcupr_dock_items() {
	$news_on_front = is_front_page() && ! mcupr_is_news_list_view();
	$items         = array(
		array( 'key' => 'home', 'label' => mcupr_t( 'home' ), 'url' => mcupr_home_url(), 'icon' => 'home', 'current' => $news_on_front ),
		array( 'key' => 'news', 'label' => mcupr_t( 'dock_news' ), 'url' => mcupr_news_url(), 'icon' => 'news', 'current' => ( ! is_front_page() || mcupr_is_news_list_view() ) && ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || ( is_date() && ! is_post_type_archive() ) ) ),
	);
	$events = get_post_type_archive_link( 'mcupr_event' );
	if ( $events ) {
		$items[] = array( 'key' => 'events', 'label' => mcupr_t( 'dock_events' ), 'url' => $events, 'icon' => 'calendar', 'current' => is_post_type_archive( 'mcupr_event' ) || is_singular( 'mcupr_event' ) );
	}
	$items[] = array( 'key' => 'search', 'label' => mcupr_t( 'search' ), 'url' => add_query_arg( 's', '', mcupr_home_url() ), 'icon' => 'search', 'current' => is_search() );
	return (array) apply_filters( 'mcupr_dock_items', $items );
}

function mcupr_dock_markup() {
	if ( ! mcupr_dock_enabled() ) {
		return;
	}
	$items = array_values( mcupr_dock_items() );
	$half  = (int) ceil( count( $items ) / 2 );
	$one   = static function ( $it ) {
		$cur = ! empty( $it['current'] );
		return '<a class="dock-i" href="' . esc_url( $it['url'] ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-dock="' . esc_attr( $it['key'] ) . '"><span class="dock-ic">' . mcupr_icon( $it['icon'], 22 ) . '</span><span class="dock-t">' . esc_html( $it['label'] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	};
	echo '<nav id="m-dock" class="m-dock" aria-label="' . esc_attr( mcupr_t( 'dock_nav' ) ) . '">';
	foreach ( array_slice( $items, 0, $half ) as $it ) {
		echo $one( $it ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<button type="button" class="dock-main" aria-controls="site-nav" aria-expanded="false" aria-label="' . esc_attr( mcupr_t( 'open_menu' ) ) . '" data-dock-menu>' . mcupr_icon( 'menu', 26 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	foreach ( array_slice( $items, $half ) as $it ) {
		echo $one( $it ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo "</nav>\n";
}
add_action( 'wp_footer', 'mcupr_dock_markup', 5 );

/** Tell the stylesheet there is a bar (so it can keep the page and the floating buttons clear of it). */
add_filter(
	'language_attributes',
	static function ( $out ) {
		return ( ! is_admin() && function_exists( 'mcupr_dock_enabled' ) && mcupr_dock_enabled() ) ? $out . ' data-dock="1"' : $out;
	}
);

add_action(
	'customize_register',
	static function ( $wp_customize ) {
		if ( ! $wp_customize->get_section( 'mcupr_display' ) ) {
			return;
		}
		$wp_customize->add_setting( 'mcupr_dock', array( 'default' => true, 'sanitize_callback' => static function ( $v ) { return (bool) $v; } ) );
		$wp_customize->add_control( 'mcupr_dock', array( 'section' => 'mcupr_display', 'priority' => 17, 'label' => __( 'Bottom bar on phones', 'mcupr' ), 'description' => __( 'Home, News, the menu button, Events and Search at the bottom of the screen on phones and small tablets.', 'mcupr' ), 'type' => 'checkbox' ) );
	},
	20
);

/* ------------------------------------------------------------------ the home hero of a phone: a round picture at the corner and a search bar floating on its lower edge */

/** Who is in the corner: the signed-in person's picture, else the logo of the site. Shown on phones only (the stylesheet). */
function mcupr_hero_avatar() {
	if ( is_user_logged_in() ) {
		$img = get_avatar( get_current_user_id(), 56, '', '', array( 'class' => 'hero-avatar-img' ) );
	} else {
		$img = mcupr_logo( 'header', 'hero-avatar-img' );
		$img = $img ? $img : mcupr_default_logo( 'hero-avatar-img' );
	}
	return '<span class="hero-avatar" aria-hidden="true">' . $img . '</span>';
}

/** What the search can be limited to (the value is the post type WordPress understands in ?post_type=). */
function mcupr_hero_search_scopes() {
	$scopes = array( '' => mcupr_t( 'scope_all' ), 'post' => mcupr_t( 'dock_news' ) );
	if ( post_type_exists( 'mcupr_event' ) ) {
		$scopes['mcupr_event'] = mcupr_t( 'dock_events' );
	}
	$scopes['page'] = mcupr_t( 'scope_pages' );
	return (array) apply_filters( 'mcupr_hero_search_scopes', $scopes );
}

function mcupr_hero_search() {
	$uid = wp_unique_id( 'hs-' );
	?>
	<form class="hero-search" method="get" role="search" action="<?php echo esc_url( mcupr_home_url() ); ?>" data-hs>
		<label for="<?php echo esc_attr( $uid ); ?>" class="sr-only"><?php mcupr_e( 'search' ); ?></label>
		<?php echo mcupr_icon( 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<input type="search" id="<?php echo esc_attr( $uid ); ?>" name="s" placeholder="<?php echo esc_attr( mcupr_t( 'hero_search_ph' ) ); ?>" autocomplete="off" enterkeyhint="search">
		<?php if ( isset( $_GET['lang'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<input type="hidden" name="lang" value="<?php echo esc_attr( mcupr_lang() ); ?>">
		<?php endif; ?>
		<button type="button" class="hs-filter" aria-haspopup="true" aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>-scope" data-hs-filter><?php echo mcupr_icon( 'sliders', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="sr-only"><?php mcupr_e( 'scope_t' ); ?></span></button>
		<button type="submit" class="sr-only"><?php mcupr_e( 'search' ); ?></button>
		<fieldset class="hs-scope" id="<?php echo esc_attr( $uid ); ?>-scope" hidden>
			<legend><?php mcupr_e( 'scope_t' ); ?></legend>
			<?php foreach ( mcupr_hero_search_scopes() as $val => $label ) : ?>
				<label><input type="radio" name="post_type" value="<?php echo esc_attr( $val ); ?>"<?php echo '' === $val ? ' checked' : ''; ?>> <span><?php echo esc_html( $label ); ?></span></label>
			<?php endforeach; ?>
		</fieldset>
	</form>
	<?php
}
