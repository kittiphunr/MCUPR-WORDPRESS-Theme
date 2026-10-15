<?php
/**
 * Videos: /videos/ and the video categories (/video-category/…) – a grid of videos with category chips.
 *
 * @package MCUPR
 */

get_header();
$mcupr_cur   = is_tax( 'mcupr_video_cat' ) ? get_queried_object_id() : 0;
$mcupr_base  = get_post_type_archive_link( 'mcupr_video' );
$mcupr_title = is_tax( 'mcupr_video_cat' ) ? single_term_title( '', false ) : mcupr_t( 'vd_title' );
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( $mcupr_title ), 'description' => '', 'chips' => false ) );
$mcupr_terms = get_terms( array( 'taxonomy' => 'mcupr_video_cat', 'hide_empty' => true ) );
?>
<div class="wrap mt-10 pb-4">
	<?php if ( $mcupr_terms && ! is_wp_error( $mcupr_terms ) ) : ?>
		<nav class="mb-6" aria-label="<?php echo esc_attr( mcupr_t( 'work_cats' ) ); ?>">
			<ul class="pill-tabs m-0 list-none">
				<li class="contents"><a href="<?php echo esc_url( $mcupr_base ); ?>" <?php echo $mcupr_cur ? '' : 'aria-current="page"'; ?>><?php mcupr_e( 'all_cats' ); ?></a></li>
				<?php foreach ( $mcupr_terms as $t ) : ?>
					<li class="contents"><a href="<?php echo esc_url( get_term_link( $t ) ); ?>" <?php echo (int) $t->term_id === $mcupr_cur ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $t->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
	<?php if ( have_posts() ) : ?>
		<div class="album-grid" data-reveal-group>
			<?php
			while ( have_posts() ) {
				the_post();
				echo mcupr_video_card( get_post(), 'h2' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<?php mcupr_state_card( 'play', mcupr_t( 'vd_none' ), '', '', 'h2' ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
