<?php
/**
 * News card.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$part_lang = mcupr_part_lang_attr();
$heading   = isset( $args['heading'] ) && 'h2' === $args['heading'] ? 'h2' : 'h3';
$featured  = isset( $args['variant'] ) && 'featured' === $args['variant'];
?>
<?php
// Posters and other tall / very wide pictures are shown whole on a soft blurred copy of themselves instead of being cropped.
$thumb_id = get_post_thumbnail_id();
$meta     = $thumb_id ? wp_get_attachment_metadata( $thumb_id ) : array();
$ratio    = ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ? $meta['width'] / $meta['height'] : 1.6;
$fit      = $ratio < 1.35 || $ratio > 2.1;
$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'tilt lift soft-card news-card relative flex flex-col overflow-hidden' . ( $featured ? ' news-card-feat min-[900px]:col-span-2 min-[900px]:flex-row' : '' ) ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="news-media noimg:hidden<?php echo $fit ? ' is-fit' : ''; ?> <?php echo $featured ? 'min-[900px]:w-1/2' : ''; ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"<?php echo $fit && $thumb ? ' style="--media:url(\'' . esc_url( $thumb ) . '\')"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php the_post_thumbnail( $fit ? 'medium_large' : 'mcupr-card', array( 'alt' => '', 'loading' => 'lazy', 'fetchpriority' => 'low', 'decoding' => 'async', 'sizes' => '(min-width: 1000px) 400px, (min-width: 700px) 45vw, 100vw', 'class' => 'block size-full' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="news-body flex flex-1 flex-col px-5 pt-4 pb-5 <?php echo $featured ? 'min-[900px]:w-1/2 min-[900px]:justify-center min-[900px]:p-8' : ''; ?>"<?php echo $part_lang; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<p class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
			<?php if ( mcupr_first_category() ) : ?>
				<span class="news-cat"><?php echo esc_html( mcupr_first_category() ); ?></span>
			<?php endif; ?>
			<?php mcupr_date(); ?>
		</p>
		<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="news-title mb-2 font-bold leading-snug <?php echo $featured ? 'text-2xl min-[900px]:text-3xl' : 'text-lg'; ?>"><a class="inline-block py-1 text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="news-excerpt text-muted [&_p]:m-0"><?php the_excerpt(); ?></div>
		<p class="news-more" aria-hidden="true"><?php mcupr_e( 'read_more' ); ?> <?php echo mcupr_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	</div>
</article>
