<?php
/**
 * Default header (used when no Elementor Pro "Header" template applies):
 * bilingual brand on a white bar, then a white main menu with a pink hover bar.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$alt = mcupr_alt_name();
?>
<header id="masthead" class="border-b border-line bg-bg print:hidden">
	<div class="wrap flex items-center justify-between gap-4 py-4">
		<a class="flex items-center gap-4 text-fg no-underline" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			if ( has_custom_logo() ) {
				echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'h-16 w-auto min-[900px]:h-20', 'alt' => '' ) );
			}
			?>
			<span class="flex flex-col leading-tight">
				<span class="text-xl font-bold min-[900px]:text-3xl"><?php echo esc_html( mcupr_org_name() ); ?></span>
				<?php if ( $alt ) : ?>
					<span class="text-sm text-muted min-[900px]:text-lg" lang="<?php echo esc_attr( 'th' === mcupr_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></span>
				<?php endif; ?>
			</span>
		</a>

		<button type="button" class="nav-toggle btn min-[900px]:hidden" aria-expanded="false" aria-controls="site-nav">
			<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
			<span><?php mcupr_e( 'menu' ); ?></span>
		</button>
	</div>

	<nav id="site-nav" class="site-nav hidden border-t border-line bg-bg min-[900px]:block [&.is-open]:block" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
		<div class="wrap"><?php mcupr_primary_menu(); ?></div>
	</nav>
</header>
