<?php
/**
 * 404.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap narrow">
	<h1 class="page-title"><?php mcupr_e( 'not_found' ); ?></h1>
	<p><?php mcupr_e( 'not_found_txt' ); ?></p>
	<?php get_search_form(); ?>
	<p><a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php mcupr_e( 'back_home' ); ?></a></p>
</div>
<?php
get_footer();
