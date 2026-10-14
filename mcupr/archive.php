<?php
/**
 * Category / tag / date archives.
 *
 * @package MCUPR
 */

get_header();

	get_template_part( 'template-parts/band', null, array( 'title' => wp_kses_post( get_the_archive_title() ), 'description' => get_the_archive_description(), 'chips' => true ) );
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
	<?php endif; ?>
</div>
<?php
get_footer();
