<?php
/**
 * Utility bar (dark strip at the very top, as on chula.ac.th):
 * quick links on the left; language, text size, dark mode, accessibility, search on the right.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<section id="utility-bar" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>" class="bg-bar text-[.9375rem] text-on-bar print:hidden">
	<div class="wrap flex flex-wrap items-center justify-between gap-x-5 gap-y-2 py-2">
		<?php
		if ( has_nav_menu( 'utility' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'utility',
					'container'      => 'nav',
					'container_aria_label' => mcupr_t( 'utility_links' ),
					'menu_class'     => 'utility-menu',
					'depth'          => 1,
				)
			);
		} else {
			echo '<span></span>';
		}
		?>
		<div class="flex flex-wrap items-center gap-x-5 gap-y-2">
			<?php get_template_part( 'template-parts/lang-switcher' ); ?>
			<?php get_template_part( 'template-parts/display-tools' ); ?>
			<?php get_search_form(); ?>
		</div>
	</div>
	<?php get_template_part( 'template-parts/a11y-panel' ); ?>
</section>
