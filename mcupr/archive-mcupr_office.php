<?php
/**
 * All offices: /offices/ – a card for each.
 *
 * @package MCUPR
 */

get_header();
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'of_offices' ) ), 'description' => esc_html( mcupr_t( 'of_offices_lead' ) ), 'chips' => false ) );
?>
<div class="wrap mt-10 pb-4">
	<?php echo mcupr_offices_html( 'h2' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
