<?php
/**
 * 404.
 *
 * @package MCUPR
 */

get_header();

	mcupr_breadcrumb();
	?>
<div class="wrap">
	<?php
	ob_start();
	echo '<div class="my-6">';
	get_search_form();
	echo '</div><a class="btn" href="' . esc_url( mcupr_home_url() ) . '">' . esc_html( mcupr_t( 'back_home' ) ) . '</a>';
	mcupr_state_card( 'alert', mcupr_t( 'not_found' ), mcupr_t( 'not_found_txt' ), ob_get_clean() );
	?>
</div>
<?php
get_footer();
