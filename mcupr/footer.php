<?php
/**
 * Footer: contact, policy links required of government sites, cookie notice.
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

<footer id="colophon" class="site-footer" tabindex="-1">
	<div class="wrap footer__grid">
		<div class="footer__about">
			<p class="footer__name"><?php echo esc_html( mcupr_org_name() ); ?></p>
			<?php if ( $address ) : ?>
				<address><?php echo nl2br( esc_html( $address ) ); ?></address>
			<?php endif; ?>
		</div>

		<nav class="footer__links" aria-label="<?php echo esc_attr( mcupr_t( 'footer_nav' ) ); ?>">
			<ul>
				<?php foreach ( $policy as $row ) : ?>
					<?php $url = mcupr_policy_url( $row[0] ); ?>
					<?php if ( $url ) : ?>
						<li><a href="<?php echo esc_url( $url ); ?>"><?php mcupr_e( $row[1] ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<?php wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1 ) ); ?>
			<?php endif; ?>
		</nav>
	</div>
	<div class="footer__legal">
		<div class="wrap">
			<p>© <?php echo esc_html( (int) wp_date( 'Y' ) + ( 'th' === mcupr_lang() ? 543 : 0 ) ); ?> <?php echo esc_html( mcupr_org_name() ); ?>. <?php mcupr_e( 'rights' ); ?></p>
		</div>
	</div>
</footer>

<div id="cookie-notice" class="cookie" role="region" aria-label="<?php echo esc_attr( mcupr_t( 'cookie_label' ) ); ?>" hidden>
	<div class="wrap cookie__inner">
		<p>
			<?php mcupr_e( 'cookie_msg' ); ?>
			<?php $cu = mcupr_policy_url( 'cookie' ) ? mcupr_policy_url( 'cookie' ) : mcupr_policy_url( 'privacy' ); ?>
			<?php if ( $cu ) : ?>
				<a href="<?php echo esc_url( $cu ); ?>"><?php mcupr_e( 'cookie_policy' ); ?></a>
			<?php endif; ?>
		</p>
		<button type="button" class="btn" id="cookie-accept"><?php mcupr_e( 'cookie_ok' ); ?></button>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
