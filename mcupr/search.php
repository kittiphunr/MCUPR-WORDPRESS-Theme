<?php
/**
 * Search results.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap">
	<h1 class="mb-5 mt-4 border-b-4 border-accent pb-2 text-3xl font-bold contrast:border-line max-[700px]:text-2xl"><?php echo esc_html( mcupr_t( 'search_for' ) ); ?>: <span class="text-link"><?php echo esc_html( get_search_query() ); ?></span></h1>

	<?php if ( have_posts() ) : ?>
		<div class="my-6 grid grid-cols-[repeat(auto-fill,minmax(17rem,1fr))] gap-6">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', null, array( 'heading' => 'h2' ) );
			endwhile;
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<p><?php mcupr_e( 'no_results' ); ?>. <?php mcupr_e( 'no_results_tip' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
