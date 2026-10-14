<?php
/**
 * Default header (used when no Elementor Pro "Header" template applies).
 * Certify Hub style: sticky translucent bar, badge logo + name, pill menu, icon buttons.
 *
 * Menu position (visitor choice, site default in the Customizer):
 *  - top  : pill menu in the bar (>=1100px)
 *  - side : fixed sidebar rail on the left (>=1100px), collapsible, hover to expand
 * Below 1100px both use the slide-in drawer.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$alt  = mcupr_alt_name();
$logo = mcupr_logo( 'header' );
$name = mcupr_org_name();
?>
<header id="masthead" class="site-header sticky top-0 z-30 border-b border-line print:hidden">
	<div class="wrap grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-2 py-2.5 max-[359px]:grid-cols-1 min-[1100px]:grid-cols-[auto_1fr_auto] min-[1100px]:gap-x-6">
		<a class="brand flex min-w-0 items-center gap-3 text-fg no-underline" href="<?php echo esc_url( mcupr_home_url() ); ?>" rel="home">
			<?php if ( $logo ) : ?>
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<span class="badge-logo" aria-hidden="true"><?php echo mcupr_icon( 'cap', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php endif; ?>
			<span class="flex min-w-0 flex-col leading-tight">
				<b class="truncate text-base font-bold"><?php echo esc_html( $name ); ?></b>
				<?php if ( $alt ) : ?>
					<small class="truncate text-xs text-muted" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></small>
				<?php endif; ?>
			</span>
		</a>

		<div class="flex flex-wrap items-center gap-1.5 min-[1100px]:order-3">
			<?php mcupr_tools_cluster(); ?>
			<button type="button" class="nav-toggle inline-flex size-11 cursor-pointer items-center justify-center rounded-xl border border-control/60 bg-bg p-0 text-fg2 min-[1100px]:hidden" aria-expanded="false" aria-controls="site-nav" aria-label="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>" data-label-open="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>" data-label-close="<?php echo esc_attr( mcupr_t( 'close_menu' ) ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</button>
		</div>

		<nav id="site-nav" class="site-nav" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
			<div class="sb-drawer-head flex items-center justify-between border-b border-line p-4 min-[1100px]:hidden">
				<span class="text-lg font-bold"><?php mcupr_e( 'menu' ); ?></span>
				<button type="button" class="inline-flex size-11 cursor-pointer items-center justify-center rounded-full border-0 bg-surface p-0 text-fg" data-close-nav aria-label="<?php echo esc_attr( mcupr_t( 'close_menu' ) ); ?>"><svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
			</div>
			<a class="sb-brand" href="<?php echo esc_url( mcupr_home_url() ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo $logo ? $logo : '<span class="badge-logo">' . mcupr_icon( 'cap', 22 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="t"><b><?php echo esc_html( $name ); ?></b><?php if ( $alt ) : ?><small><?php echo esc_html( $alt ); ?></small><?php endif; ?></span>
			</a>
			<div class="sb-cap"><?php mcupr_primary_menu(); ?></div>
			<div class="sb-cap sb-foot">
				<button type="button" class="sb-btn" data-sb-toggle aria-expanded="true">
					<span class="ic"><?php echo mcupr_icon( 'panel', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="t" data-sb-toggle-label data-collapse="<?php echo esc_attr( mcupr_t( 'collapse_menu' ) ); ?>" data-expand="<?php echo esc_attr( mcupr_t( 'expand_menu' ) ); ?>"><?php mcupr_e( 'collapse_menu' ); ?></span>
				</button>
			</div>
		</nav>
	</div>
</header>
<div id="nav-overlay" class="fixed inset-0 z-[25] bg-black/50 opacity-0 transition-opacity duration-300 [&:not(.is-open)]:pointer-events-none min-[1100px]:hidden" aria-hidden="true" hidden></div>
<button type="button" id="nav-fab" class="nav-fab" aria-controls="site-nav" aria-expanded="false" aria-label="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>"><?php echo mcupr_icon( 'panel', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
