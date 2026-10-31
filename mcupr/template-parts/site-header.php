<?php
/**
 * Site header.
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
		<?php $wide = (bool) get_theme_mod( 'mcupr_logo_wide', false ) && $logo; ?>
		<a class="brand<?php echo $wide ? ' brand-wide' : ''; ?> flex min-w-0 items-center gap-3 text-fg no-underline" href="<?php echo esc_url( mcupr_home_url() ); ?>" rel="home" data-name="<?php echo esc_attr( $name ); ?>">
			<?php if ( $logo ) : ?>
				<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<span class="badge-logo" aria-hidden="true"><?php echo mcupr_icon( 'cap', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<?php endif; ?>
			<span class="<?php echo $wide ? 'sr-only' : 'flex min-w-0 flex-col leading-tight'; ?>">
				<b class="line-clamp-2 text-base leading-snug font-bold"><?php echo esc_html( $name ); ?></b>
				<?php if ( $alt ) : ?>
					<small class="truncate text-xs text-muted" lang="<?php echo esc_attr( 'th' === mcupr_ui_lang() ? 'en' : 'th' ); ?>"><?php echo esc_html( $alt ); ?></small>
				<?php endif; ?>
			</span>
		</a>

		<div class="flex flex-wrap items-center gap-1.5 min-[1100px]:order-3">
			<?php if ( has_nav_menu( 'utility' ) ) : ?>
				<?php // No black bar any more: the quick links (students, staff, alumni…) are tucked into one drop-down button (or shown inline when chosen in the Customizer) here, and sit at the end of the menu drawer on phones. Copies without ids. ?>
				<?php $tuck = ! get_theme_mod( 'mcupr_util_inline', false ); ?>
				<div class="hdr-util-wrap<?php echo $tuck ? ' is-tucked' : ''; ?>"<?php echo $tuck ? ' data-util' : ''; ?>>
					<?php if ( $tuck ) : ?>
						<button type="button" class="util-btn" aria-expanded="false" aria-controls="util-panel" data-util-toggle><?php echo mcupr_icon( 'users', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'quick_links' ); ?></span><?php echo mcupr_icon( 'chevron', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
					<?php endif; ?>
					<div class="util-panel"<?php echo $tuck ? ' id="util-panel" hidden' : ''; ?>>
						<?php add_filter( 'nav_menu_item_id', '__return_empty_string' ); ?>
						<?php
						wp_nav_menu(
							array(
								'theme_location'       => 'utility',
								'container'            => 'nav',
								'container_class'      => 'hdr-util',
								'container_aria_label' => mcupr_t( 'utility_links' ),
								'menu_id'              => 'utility-menu-header',
								'menu_class'           => '',
								'depth'                => 1,
							)
						);
						?>
						<?php remove_filter( 'nav_menu_item_id', '__return_empty_string' ); ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( get_theme_mod( 'mcupr_fs_menu', true ) ) : ?>
			<button type="button" class="fs-open" aria-haspopup="dialog" aria-controls="fs-menu" aria-expanded="false" data-fs-open>
				<?php echo mcupr_icon( 'grid', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'all_menu' ); ?></span>
			</button>
			<?php endif; ?>
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
			<button type="button" class="nav-back" data-nav-back hidden><?php echo mcupr_icon( 'chevron', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'nav_back' ); ?></span></button>
			<form class="nav-search" role="search" method="get" action="<?php echo esc_url( mcupr_home_url() ); ?>">
				<?php echo mcupr_icon( 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<label class="sr-only" for="nav-s"><?php mcupr_e( 'search' ); ?></label>
				<input type="search" id="nav-s" name="s" placeholder="<?php echo esc_attr( mcupr_t( 'search_ph' ) ); ?>" autocomplete="off" enterkeyhint="search">
				<?php if ( isset( $_GET['lang'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><input type="hidden" name="lang" value="<?php echo esc_attr( mcupr_lang() ); ?>"><?php endif; ?>
			</form>
			<a class="sb-brand" href="<?php echo esc_url( mcupr_home_url() ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo $logo ? $logo : '<span class="badge-logo">' . mcupr_icon( 'cap', 22 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span class="t"><b><?php echo esc_html( $name ); ?></b><?php if ( $alt ) : ?><small><?php echo esc_html( $alt ); ?></small><?php endif; ?></span>
			</a>
			<div class="sb-cap"><?php mcupr_primary_menu(); ?></div>
			<?php if ( has_nav_menu( 'utility' ) ) : ?>
				<?php add_filter( 'nav_menu_item_id', '__return_empty_string' ); ?>
				<?php
				wp_nav_menu(
					array(
						'theme_location'       => 'utility',
						'container'            => 'nav',
						'container_class'      => 'nav-util',
						'container_aria_label' => mcupr_t( 'utility_links' ),
						'menu_id'              => 'utility-menu-drawer',
						'menu_class'           => '',
						'depth'                => 1,
					)
				);
				?>
				<?php remove_filter( 'nav_menu_item_id', '__return_empty_string' ); ?>
			<?php endif; ?>
			<?php
			// "Latest" of the phone's start menu: the three newest news items.
			$recent = function_exists( 'mcupr_dock_enabled' ) && mcupr_dock_enabled() ? get_posts( array( 'numberposts' => 3, 'no_found_rows' => true, 'ignore_sticky_posts' => true ) ) : array();
			if ( $recent ) :
				?>
			<section class="nav-recent" aria-labelledby="nav-recent-h">
				<h2 id="nav-recent-h"><?php mcupr_e( 'latest_news' ); ?></h2>
				<ul>
					<?php foreach ( $recent as $rp ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $rp ) ); ?>"><span class="nr-t"><?php echo esc_html( get_the_title( $rp ) ); ?></span><span class="nr-d"><?php echo esc_html( get_the_date( '', $rp ) ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<?php endif; ?>
			<div class="sb-cap sb-foot">
				<svg class="sb-ring" aria-hidden="true" focusable="false"><rect class="sb-ring-track" pathLength="100"/><rect class="sb-ring-spin" pathLength="100"/><rect class="sb-ring-bar" pathLength="100"/></svg>
				<button type="button" class="sb-btn" data-sb-toggle aria-expanded="true">
					<span class="ic"><?php echo mcupr_icon( 'panel', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="t" data-sb-toggle-label data-collapse="<?php echo esc_attr( mcupr_t( 'collapse_menu' ) ); ?>" data-expand="<?php echo esc_attr( mcupr_t( 'expand_menu' ) ); ?>"><?php mcupr_e( 'collapse_menu' ); ?></span>
				</button>
			</div>
			<div class="nav-tools" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
				<button type="button" class="nav-tool" data-nav-tool="#search-toggle"><?php echo mcupr_icon( 'search', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'search' ); ?></span></button>
				<button type="button" class="nav-tool" data-nav-tool="#lang-toggle"><?php echo mcupr_icon( 'globe', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><b class="nav-tool-badge" aria-hidden="true"><?php echo esc_html( mcupr_lang_badge() ); ?></b><span><?php mcupr_e( 'language' ); ?></span></button>
				<button type="button" class="nav-tool" data-nav-tool="#a11y-toggle"><?php echo mcupr_icon( 'sliders', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'settings_btn' ); ?></span></button>
				<button type="button" class="nav-tool" data-nav-tool="#about-toggle"><?php echo mcupr_icon( 'help', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'about' ); ?></span></button>
			</div>
		</nav>
	</div>
</header>
<script>(function(){var h=document.getElementById('masthead');if(h){var m=parseFloat(getComputedStyle(h).marginTop)||0;document.documentElement.style.setProperty('--hdr-h',(h.offsetHeight+m)+'px');}})();</script>
<div id="nav-overlay" class="fixed inset-0 z-[25] bg-black/50 opacity-0 transition-opacity duration-300 [&:not(.is-open)]:pointer-events-none min-[1100px]:hidden" aria-hidden="true" hidden></div>
<button type="button" id="nav-fab" class="nav-fab" aria-controls="site-nav" aria-expanded="false" aria-label="<?php echo esc_attr( mcupr_t( 'open_menu' ) ); ?>"><?php echo mcupr_icon( 'panel', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
