<?php
/**
 * Footer: widget columns, links and
 * the policy links expected of government websites.
 * (The cookie / PDPA notice is shown with SweetAlert2 from assets/js/main.js.)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

$policy = array(
	'privacy' => array( 'privacy', 'privacy' ),
	'cookie'  => array( 'cookie', 'cookie_policy' ),
	'a11y'    => array( 'a11y', 'a11y_policy' ),
	'sitemap' => array( 'sitemap', 'sitemap' ),
	'contact' => array( 'contact', 'contact' ),
);
$alt     = mcupr_alt_name();

// policy links that the footer menu already shows are not repeated in the last column
$in_menu = array();
$locs    = get_nav_menu_locations();
if ( ! empty( $locs['footer'] ) ) {
	foreach ( (array) wp_get_nav_menu_items( $locs['footer'] ) as $mi ) {
		$in_menu[] = untrailingslashit( $mi->url );
	}
}
$policy_links = array();
foreach ( $policy as $row ) {
	$url = mcupr_policy_url( $row[0] );
	if ( $url && ! in_array( untrailingslashit( $url ), $in_menu, true ) ) {
		$policy_links[] = array( $url, $row[1] );
	}
}
?>
<?php mcupr_slot( 'footer_before' ); ?>
</main>

<footer id="colophon" class="site-footer mt-16 pb-6 text-fg print:hidden" tabindex="-1">
	<div class="wrap soft-card overflow-hidden">
	<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<div class="px-6 border-b border-line py-10">
			<nav aria-label="<?php echo esc_attr( mcupr_t( 'site_links' ) ); ?>">
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 2, 'menu_class' => 'footer-cols' ) ); ?>
			</nav>
		</div>
	<?php endif; ?>
	<?php if ( is_active_sidebar( 'footer-1' ) || is_active_sidebar( 'footer-2' ) || is_active_sidebar( 'footer-3' ) ) : ?>
		<div class="px-6 grid gap-8 border-b border-line py-10 min-[800px]:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]">
			<?php foreach ( array( 'footer-1', 'footer-2', 'footer-3' ) as $area ) { mcupr_widget_area( $area, 'space-y-6' ); } ?>
		</div>
	<?php endif; ?>
	<div class="fgrid px-6 py-10<?php echo $policy_links ? '' : ' fgrid-2'; ?>">
		<div>
			<div class="flex items-start gap-4">
				<?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div>
					<p class="mb-1 text-xl font-bold"><?php echo esc_html( mcupr_org_name() ); ?></p>
					<?php if ( $alt ) : ?>
						<p class="mb-0" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php mcupr_contact_list(); ?>
			<?php mcupr_social_links(); ?>
		</div>

		<div class="fmap-col"><?php mcupr_map_card(); ?></div>

		<?php if ( $policy_links ) : ?>
		<nav aria-label="<?php echo esc_attr( mcupr_t( 'footer_nav' ) ); ?>">
			<h2 class="mb-3 text-lg font-bold"><?php mcupr_e( 'footer_nav' ); ?></h2>
			<ul class="footer-menu">
				<?php foreach ( $policy_links as $row ) : ?>
					<li><a href="<?php echo esc_url( $row[0] ); ?>"><?php mcupr_e( $row[1] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php endif; ?>
	</div>
	<div class="border-t border-line py-4 text-[.9375rem] text-muted">
		<div class="px-6">
			<p class="m-0">© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( mcupr_org_name() ); ?>. <?php mcupr_e( 'rights' ); ?></p>
			<p class="m-0 mt-1">
				<?php
				// the site creator is credited in the About dialog
				echo esc_html( mcupr_t( 'theme_credit' ) . ' ' . MCUPR_VERSION ) . ' · ';
				?>
				<button type="button" class="min-h-8 cursor-pointer border-0 bg-transparent p-0 text-link underline" data-open-about><?php mcupr_e( 'about' ); ?></button>
			</p>
		</div>
	</div>
	</div>
</footer>
</div><!-- #page -->

<button type="button" id="back-to-top" class="btn fixed right-4 bottom-4 z-40 hidden shadow-lg print:hidden" aria-label="<?php echo esc_attr( mcupr_t( 'back_top' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'back_top' ) ); ?>">
	<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>

<?php
$cookie_url = mcupr_policy_url( 'cookie' ) ? mcupr_policy_url( 'cookie' ) : mcupr_policy_url( 'privacy' );
?>
<div id="cookie-notice" class="cookie-notice soft-card" role="region" aria-labelledby="cookie-title">
	<p id="cookie-title" class="m-0 mb-1 font-bold"><?php mcupr_e( 'cookie_title' ); ?></p>
	<p class="m-0 mb-3 text-sm text-fg2"><?php mcupr_e( 'cookie_msg' ); ?><?php if ( $cookie_url ) : ?> <a href="<?php echo esc_url( $cookie_url ); ?>"><?php mcupr_e( 'cookie_policy' ); ?></a><?php endif; ?></p>
	<button type="button" class="btn" data-cookie-ok><?php mcupr_e( 'cookie_ok' ); ?></button>
</div>

<?php get_template_part( 'template-parts/modals' ); ?>

<?php wp_footer(); ?>
</body>
</html>
