<?php
/**
 * 404.
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'single' ) ) :
	mcupr_breadcrumb();
	?>
<div class="narrow">
	<h1 class="mb-5 mt-4 border-b-4 border-primary pb-2 text-3xl font-bold contrast:border-line max-[700px]:text-2xl"><?php mcupr_e( 'not_found' ); ?></h1>
	<p><?php mcupr_e( 'not_found_txt' ); ?></p>
	<div class="my-6"><?php get_search_form(); ?></div>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php mcupr_e( 'back_home' ); ?></a></p>
</div>
<?php
endif;

get_footer();
