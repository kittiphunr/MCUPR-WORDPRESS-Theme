<?php
/**
 * Category / tag / date archives.
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'archive' ) ) :
	mcupr_breadcrumb();
	?>
<div class="wrap">
	<h1 class="mb-5 mt-4 border-b-4 border-primary pb-2 text-3xl font-bold contrast:border-line max-[700px]:text-2xl"><?php the_archive_title(); ?></h1>
	<?php the_archive_description( '<div class="mb-4 text-muted">', '</div>' ); ?>

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
		<p><?php mcupr_e( 'no_results' ); ?></p>
	<?php endif; ?>
</div>
<?php
endif;

get_footer();
