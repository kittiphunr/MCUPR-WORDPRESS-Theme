<?php
/**
 * Category / tag / date archives.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap">
	<h1 class="page-title"><?php the_archive_title(); ?></h1>
	<?php the_archive_description( '<div class="archive-desc">', '</div>' ); ?>

	<?php if ( have_posts() ) : ?>
		<div class="grid">
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
get_footer();
