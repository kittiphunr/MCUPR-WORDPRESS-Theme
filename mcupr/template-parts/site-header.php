<?php
/**
 * Default header (used when no Elementor Pro "Header" template applies):
 * logo + bilingual name, centred main menu with dropdown chevrons, and icon
 * buttons for accessibility (Aa), language (globe) and search – as on chula.ac.th.
 * Below 1100px the menu becomes a slide-in drawer opened by the menu button.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$alt = mcupr_alt_name();
?>
<header id="masthead" class="border-b border-line bg-bg print:hidden">
	<div class="wrap grid grid-cols-[1fr_auto] items-center gap-x-3 py-3 min-[1100px]:grid-cols-[auto_1fr_auto] min-[1100px]:gap-x-6">
		<a class="flex min-w-0 items-center gap-3 text-fg no-underline" href="<?php echo esc_url( mcupr_home_url() ); ?>" rel="home">
			<?php echo mcupr_logo( 'header' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="flex min-w-0 flex-col leading-tight">
				<span class="text-lg font-bold min-[1100px]:text-xl"><?php echo esc_html( mcupr_org_name() ); ?></span>
				<?php if ( $alt ) : ?>
					<span class="text-sm text-muted" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></span>
				<?php endif; ?>
			</span>
		</a>

		<div class="flex items-center gap-1 min-[1100px]:order-3">
			<?php mcupr_tools_cluster(); ?>
			<button type="button" class="nav-toggle inline-flex size-11 cursor-pointer items-center justify-center rounded-full border-0 bg-surface p-0 text-fg min-[1100px]:hidden" aria-expanded="false" aria-controls="site-nav" aria-label="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>" data-label-open="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>" data-label-close="<?php echo esc_attr( mcupr_t( 'close_menu' ) ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</button>
		</div>

		<nav id="site-nav" class="site-nav" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
			<div class="flex items-center justify-between border-b border-line p-4 min-[1100px]:hidden">
				<span class="text-lg font-bold"><?php mcupr_e( 'menu' ); ?></span>
				<button type="button" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-full border-0 bg-surface p-0 text-fg" data-close-nav aria-label="<?php echo esc_attr( mcupr_t( 'close_menu' ) ); ?>"><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
			</div>
			<?php mcupr_primary_menu(); ?>
		</nav>
	</div>
</header>
<div id="nav-overlay" class="fixed inset-0 z-40 bg-black/50 opacity-0 transition-opacity duration-300 [&:not(.is-open)]:pointer-events-none min-[1100px]:hidden" aria-hidden="true" hidden></div>
