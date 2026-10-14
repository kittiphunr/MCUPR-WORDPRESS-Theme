<?php
/**
 * Fallback / blog index (news).
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'archive' ) ) :
	get_template_part( 'template-parts/band', null, array( 'title' => is_home() && ! is_front_page() ? esc_html( single_post_title( '', false ) ) : esc_html( mcupr_t( 'latest_news' ) ), 'description' => '', 'chips' => true ) );
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
		<p><?php mcupr_e( 'no_results' ); ?></p>
		<p><?php mcupr_e( 'no_results_tip' ); ?></p>
	<?php endif; ?>
</div>
<?php
endif;

get_footer();
