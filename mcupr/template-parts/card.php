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
<article id="post-<?php the_ID(); ?>" <?php post_class( 'tilt lift soft-card relative flex flex-col overflow-hidden' . ( $featured ? ' min-[900px]:col-span-2 min-[900px]:flex-row' : '' ) ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="noimg:hidden <?php echo $featured ? 'min-[900px]:w-1/2' : ''; ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'mcupr-card', array( 'alt' => '', 'loading' => 'lazy', 'class' => 'block aspect-[16/10] w-full object-cover' . ( $featured ? ' min-[900px]:h-full' : '' ) ) ); ?>
		</a>
	<?php endif; ?>
	<div class="px-4 pt-4 pb-5 <?php echo $featured ? 'min-[900px]:flex min-[900px]:w-1/2 min-[900px]:flex-col min-[900px]:justify-center min-[900px]:p-8' : ''; ?>"<?php echo $part_lang; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<p class="mb-1 flex flex-wrap items-center gap-x-3 text-sm text-muted">
			<?php if ( mcupr_first_category() ) : ?>
				<span class="font-bold uppercase tracking-wide text-link"><?php echo esc_html( mcupr_first_category() ); ?></span>
			<?php endif; ?>
			<?php mcupr_date(); ?>
		</p>
		<<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="mb-2 font-bold leading-snug <?php echo $featured ? 'text-2xl min-[900px]:text-3xl' : 'text-lg'; ?>"><a class="inline-block py-1 text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo $heading; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="text-muted [&_p]:m-0"><?php the_excerpt(); ?></div>
	</div>
</article>
