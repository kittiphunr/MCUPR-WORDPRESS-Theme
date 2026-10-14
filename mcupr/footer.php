<?php
/**
 * Footer: Elementor Pro "Footer" template, or the default dark footer with
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
$address = mcupr_text_mod( 'mcupr_address' );
$alt     = mcupr_alt_name();
?>
</main>

<?php if ( ! mcupr_elementor_location( 'footer' ) ) : ?>
<footer id="colophon" class="mt-16 border-t-[6px] border-primary bg-bar text-on-bar contrast:border-line print:hidden" tabindex="-1">
	<?php if ( has_nav_menu( 'footer' ) ) : ?>
		<div class="wrap border-b border-on-bar/25 py-10">
			<nav aria-label="<?php echo esc_attr( mcupr_t( 'site_links' ) ); ?>">
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 2, 'menu_class' => 'footer-cols' ) ); ?>
			</nav>
		</div>
	<?php endif; ?>
	<div class="wrap grid gap-8 py-10 min-[900px]:grid-cols-[2fr_1fr]">
		<div class="flex items-start gap-4">
			<?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div>
				<p class="mb-1 text-xl font-bold"><?php echo esc_html( mcupr_org_name() ); ?></p>
				<?php if ( $alt ) : ?>
					<p class="mb-3" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></p>
				<?php endif; ?>
				<?php if ( $address ) : ?>
					<address class="not-italic"><?php echo nl2br( esc_html( $address ) ); ?></address>
				<?php endif; ?>
			</div>
		</div>

		<nav aria-label="<?php echo esc_attr( mcupr_t( 'footer_nav' ) ); ?>">
			<h2 class="mb-3 text-lg font-bold"><?php mcupr_e( 'footer_nav' ); ?></h2>
			<ul class="footer-menu">
				<?php foreach ( $policy as $row ) : ?>
					<?php $url = mcupr_policy_url( $row[0] ); ?>
					<?php if ( $url ) : ?>
						<li><a href="<?php echo esc_url( $url ); ?>"><?php mcupr_e( $row[1] ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>
	<div class="border-t border-on-bar/25 py-4 text-[.9375rem]">
		<div class="wrap">
			<p class="m-0">© <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( mcupr_org_name() ); ?>. <?php mcupr_e( 'rights' ); ?></p>
		</div>
	</div>
</footer>
<?php endif; ?>

<button type="button" id="back-to-top" class="btn fixed right-4 bottom-4 z-40 hidden shadow-lg print:hidden" aria-label="<?php echo esc_attr( mcupr_t( 'back_top' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'back_top' ) ); ?>">
	<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
</button>

<?php get_template_part( 'template-parts/modals' ); ?>

<?php wp_footer(); ?>
</body>
</html>
