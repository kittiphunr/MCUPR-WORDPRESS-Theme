<?php
/**
 * Home page: the newest posts of one category as a block (heading + "view all" + 3 compact cards).
 * Args: term (WP_Term)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$term = isset( $args['term'] ) ? $args['term'] : null;
if ( ! $term ) {
	return;
}
$q = new WP_Query(
	array(
		'posts_per_page'      => 3,
		'cat'                 => $term->term_id,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $q->have_posts() ) {
	return;
}
$id = 'cat-' . $term->term_id;
?>
<section aria-labelledby="<?php echo esc_attr( $id ); ?>" data-reveal>
	<div class="mb-4 flex items-center justify-between gap-3">
		<h3 id="<?php echo esc_attr( $id ); ?>" class="m-0 text-xl font-bold"><?php echo esc_html( $term->name ); ?></h3>
		<a class="inline-flex min-h-11 items-center rounded-full px-3 text-sm font-semibold text-link no-underline hover:bg-tint" href="<?php echo esc_url( get_category_link( $term ) ); ?>"><?php mcupr_e( 'view_all' ); ?><span class="sr-only">: <?php echo esc_html( $term->name ); ?></span> <span aria-hidden="true" class="ml-1">→</span></a>
	</div>
	<ul class="soft-card m-0 list-none divide-y divide-line p-2">
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			?>
			<li class="flex gap-3 p-2"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a class="noimg:hidden shrink-0" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'thumbnail', array( 'alt' => '', 'loading' => 'lazy', 'fetchpriority' => 'low', 'decoding' => 'async', 'class' => 'size-16 rounded-2xl object-cover' ) ); ?></a>
				<?php endif; ?>
				<div class="min-w-0">
					<p class="m-0 text-xs text-muted"><?php mcupr_date(); ?></p>
					<a class="inline-block py-0.5 font-semibold leading-snug text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</div>
			</li>
		<?php endwhile; ?>
	</ul>
</section>
<?php wp_reset_postdata(); ?>
