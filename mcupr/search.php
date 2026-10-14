<?php
/**
 * Search results.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap">
	<h1 class="page-title"><?php echo esc_html( mcupr_t( 'search_for' ) ); ?>: <span class="query"><?php echo esc_html( get_search_query() ); ?></span></h1>

	<?php if ( have_posts() ) : ?>
		<div class="grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card' );
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
