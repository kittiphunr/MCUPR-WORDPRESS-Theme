<?php
/**
 * Related news (same category, falling back to the latest posts).
 * Args: count (default 3), style (grid|list)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$count = isset( $args['count'] ) ? (int) $args['count'] : 3;
$style = isset( $args['style'] ) ? $args['style'] : 'grid';
$cats  = wp_get_post_categories( get_the_ID() );
$q     = new WP_Query(
	array(
		'posts_per_page'      => $count,
		'post__not_in'        => array( get_the_ID() ),
		'category__in'        => $cats,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $q->have_posts() ) {
	$q = new WP_Query( array( 'posts_per_page' => $count, 'post__not_in' => array( get_the_ID() ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
}
if ( ! $q->have_posts() ) {
	return;
}
$ids = 'related-' . wp_unique_id();
?>
<section class="no-print" aria-labelledby="<?php echo esc_attr( $ids ); ?>">
	<h2 id="<?php echo esc_attr( $ids ); ?>" class="mcu-title mb-6 text-2xl font-bold"><?php mcupr_e( 'related' ); ?></h2>
	<?php if ( 'list' === $style ) : ?>
		<ul class="m-0 list-none divide-y divide-line p-0">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				?>
				<li class="flex gap-3 py-3"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="noimg:hidden shrink-0" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'thumbnail', array( 'alt' => '', 'class' => 'size-16 rounded-lg object-cover' ) ); ?></a>
					<?php endif; ?>
					<div class="min-w-0">
						<p class="m-0 text-xs text-muted"><?php mcupr_date(); ?></p>
						<a class="inline-block py-0.5 font-semibold leading-snug text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</div>
				</li>
			<?php endwhile; ?>
		</ul>
	<?php else : ?>
		<div class="grid grid-cols-[repeat(auto-fill,minmax(min(16rem,100%),1fr))] gap-6">
			<?php
			while ( $q->have_posts() ) :
				$q->the_post();
				get_template_part( 'template-parts/card', null, array( 'heading' => 'h3' ) );
			endwhile;
			?>
		</div>
	<?php endif; ?>
</section>
<?php wp_reset_postdata(); ?>
