<?php
/**
 * Photo albums: /albums/ – a grid of albums.
 *
 * @package MCUPR
 */

get_header();
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'album_title' ) ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap mt-10 pb-4">
	<?php if ( have_posts() ) : ?>
		<div class="album-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				echo mcupr_album_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<?php mcupr_state_card( 'image', mcupr_t( 'album_none' ), '', '', 'h2' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
