<?php
/**
 * Default header (used when no Elementor Pro "Header" template applies).
 * On the home page it floats over the hero photo (white text, as on
 * chula.ac.th); elsewhere it is a white bar with a pink hover bar on the menu.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$alt  = mcupr_alt_name();
$over = mcupr_header_over_hero();
?>
<header id="masthead" class="<?php echo $over ? 'absolute inset-x-0 z-30 bg-gradient-to-b from-black/70 to-transparent text-white' : 'border-b border-line bg-bg'; ?> print:hidden">
	<div class="wrap flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-4">
		<a class="flex min-w-0 flex-1 items-center gap-4 no-underline <?php echo $over ? 'text-white' : 'text-fg'; ?>" href="<?php echo esc_url( mcupr_home_url() ); ?>" rel="home">
			<?php
			if ( has_custom_logo() ) {
				echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'h-14 w-auto min-[900px]:h-16', 'alt' => '' ) );
			}
			?>
			<span class="flex flex-col leading-tight">
				<span class="text-xl font-bold min-[900px]:text-2xl"><?php echo esc_html( mcupr_org_name() ); ?></span>
				<?php if ( $alt ) : ?>
					<span class="text-sm min-[900px]:text-base <?php echo $over ? 'text-white/85' : 'text-muted'; ?>" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></span>
				<?php endif; ?>
			</span>
		</a>

		<button type="button" class="nav-toggle btn shrink-0 whitespace-nowrap min-[900px]:hidden" aria-expanded="false" aria-controls="site-nav">
			<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
			<span><?php mcupr_e( 'menu' ); ?></span>
		</button>
	</div>

	<nav id="site-nav" class="site-nav hidden min-[900px]:block [&.is-open]:block <?php echo $over ? 'site-nav--over bg-black/90 min-[900px]:bg-transparent' : 'border-t border-line bg-bg'; ?>" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
		<div class="wrap"><?php mcupr_primary_menu(); ?></div>
	</nav>
</header>
