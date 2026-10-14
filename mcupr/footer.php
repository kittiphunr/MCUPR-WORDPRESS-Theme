<?php
/**
 * Footer: contact and policy links required of government sites.
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
?>
</main>

<footer id="colophon" class="border-t-[6px] border-primary bg-surface contrast:border-line print:hidden" tabindex="-1">
	<div class="wrap grid gap-8 py-8 min-[700px]:grid-cols-[2fr_1fr]">
		<div>
			<p class="mb-2 text-xl font-bold"><?php echo esc_html( mcupr_org_name() ); ?></p>
			<?php if ( $address ) : ?>
				<address class="not-italic"><?php echo nl2br( esc_html( $address ) ); ?></address>
			<?php endif; ?>
		</div>

		<nav aria-label="<?php echo esc_attr( mcupr_t( 'footer_nav' ) ); ?>">
			<ul class="footer-menu">
				<?php foreach ( $policy as $row ) : ?>
					<?php $url = mcupr_policy_url( $row[0] ); ?>
					<?php if ( $url ) : ?>
						<li><a class="inline-block py-1" href="<?php echo esc_url( $url ); ?>"><?php mcupr_e( $row[1] ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1, 'menu_class' => 'footer-menu' ) );
			}
			?>
		</nav>
	</div>
	<div class="border-t border-line py-4 text-[.9375rem] text-muted">
		<div class="wrap">
			<p class="m-0">© <?php echo esc_html( (int) wp_date( 'Y' ) + ( 'th' === mcupr_lang() ? 543 : 0 ) ); ?> <?php echo esc_html( mcupr_org_name() ); ?>. <?php mcupr_e( 'rights' ); ?></p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
