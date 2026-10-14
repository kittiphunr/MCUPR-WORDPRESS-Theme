<?php
/**
 * Fallback / blog index.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<h1 class="page-title"><?php echo esc_html( single_post_title( '', false ) ); ?></h1>
	<?php endif; ?>

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
		<h1 class="page-title"><?php mcupr_e( 'no_results' ); ?></h1>
		<p><?php mcupr_e( 'no_results_tip' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
