<?php
/**
 * 404: a big (decorative) 404, what happened, a search that already holds a guess from the address, buttons home / news,
 * the main menu's pages and the latest news – so a lost visitor always has a next step.
 *
 * @package MCUPR
 */

get_header();

mcupr_breadcrumb();

// a guess of what was wanted, from the last part of the address (dashes and underscores become spaces)
$mcupr_path  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
$mcupr_last  = rawurldecode( basename( untrailingslashit( $mcupr_path ) ) );
$mcupr_guess = trim( preg_replace( '/\s+/u', ' ', str_replace( array( '-', '_', '+' ), ' ', preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $mcupr_last ) ) ) );
$mcupr_guess = mb_strlen( $mcupr_guess ) >= 3 && ! preg_match( '/^\d+$/', $mcupr_guess ) ? mb_substr( sanitize_text_field( $mcupr_guess ), 0, 60 ) : '';

// the pages of the main menu (top level), the latest news
$mcupr_loc = 'primary_' . mcupr_lang();
if ( ! has_nav_menu( $mcupr_loc ) ) {
	$mcupr_loc = 'primary_' . mcupr_default_lang();
}
$mcupr_links = array();
$mcupr_locs  = get_nav_menu_locations();
if ( ! empty( $mcupr_locs[ $mcupr_loc ] ) ) {
	foreach ( (array) wp_get_nav_menu_items( $mcupr_locs[ $mcupr_loc ] ) as $mcupr_it ) {
		if ( ! (int) $mcupr_it->menu_item_parent && '' !== $mcupr_it->url && '#' !== $mcupr_it->url && count( $mcupr_links ) < 8 ) {
			$mcupr_links[] = array( $mcupr_it->title, $mcupr_it->url );
		}
	}
}
$mcupr_news = get_posts( array( 'posts_per_page' => 3, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
?>
<div class="wrap">
	<div class="nf">
		<p class="nf-code" aria-hidden="true">404</p>
		<h1 class="nf-title"><?php mcupr_e( 'not_found' ); ?></h1>
		<p class="nf-text"><?php mcupr_e( 'not_found_txt' ); ?></p>
		<div class="nf-search"><?php get_search_form(); ?></div>
		<?php if ( '' !== $mcupr_guess ) : ?>
			<p class="nf-guess"><a href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $mcupr_guess ), mcupr_home_url() ) ); ?>"><?php echo esc_html( sprintf( mcupr_t( 'nf_search_for' ), $mcupr_guess ) ); ?></a></p>
		<?php endif; ?>
		<p class="nf-actions">
			<a class="btn" href="<?php echo esc_url( mcupr_home_url() ); ?>"><?php mcupr_e( 'back_home' ); ?></a>
			<a class="nf-btn2" href="<?php echo esc_url( mcupr_news_url() ); ?>"><?php mcupr_e( 'all_news' ); ?></a>
		</p>
		<?php if ( $mcupr_links ) : ?>
			<nav class="nf-sec" aria-labelledby="nf-try">
				<h2 id="nf-try"><?php mcupr_e( 'nf_try' ); ?></h2>
				<ul class="nf-chips">
					<?php foreach ( $mcupr_links as $mcupr_l ) : ?>
						<li><a href="<?php echo esc_url( $mcupr_l[1] ); ?>"><?php echo esc_html( $mcupr_l[0] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
		<?php if ( $mcupr_news ) : ?>
			<section class="nf-sec" aria-labelledby="nf-news">
				<h2 id="nf-news"><?php mcupr_e( 'nf_latest' ); ?></h2>
				<ul class="nf-news">
					<?php foreach ( $mcupr_news as $mcupr_p ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $mcupr_p ) ); ?>"><span class="nf-n-t"><?php echo esc_html( get_the_title( $mcupr_p ) ); ?></span><span class="nf-n-d"><?php mcupr_date( $mcupr_p ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
