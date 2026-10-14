<?php
/**
 * Search results.
 *
 * @package MCUPR
 */

get_header();

	get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'search_for' ) ) . ': <span class=\'text-link\'>' . esc_html( get_search_query() ) . '</span>', 'description' => '', 'chips' => false ) );
	?>
<div class="wrap mt-10">
	<?php if ( have_posts() ) : ?>
		<div class="grid grid-cols-[repeat(auto-fill,minmax(min(17rem,100%),1fr))] gap-6" data-reveal-group>
			<?php
			$mcupr_first = true;
			while ( have_posts() ) :
				the_post();
				// First news item of the first page is shown large.
				$featured = $mcupr_first && ! is_paged() && ! is_search() && has_post_thumbnail();
				$mcupr_first = false;
				get_template_part( 'template-parts/card', null, array( 'heading' => 'h2', 'variant' => $featured ? 'featured' : '' ) );
			endwhile;
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<?php mcupr_state_card( 'search', mcupr_t( 'no_results' ), mcupr_t( 'no_results_tip' ), '', 'h2' ); ?>
		<div class="my-6"><?php get_search_form(); ?></div>
	<?php endif; ?>
</div>
<?php
get_footer();
