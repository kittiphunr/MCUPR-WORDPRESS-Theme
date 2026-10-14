<?php
/**
 * News layout 2 – Cover: full-width hero image with the title on it, tools in a sticky side rail.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<article data-layout="cover" id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel relative isolate flex min-h-[22rem] items-end overflow-hidden bg-neutral-800 text-white min-[900px]:min-h-[30rem]"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<span class="noimg:hidden absolute inset-0 -z-20" aria-hidden="true"><?php the_post_thumbnail( 'full', array( 'alt' => '', 'class' => 'size-full object-cover', 'fetchpriority' => 'high' ) ); ?></span>
		<?php endif; ?>
		<span class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/55 to-black/20" aria-hidden="true"></span>
		<div class="wrap pt-24 pb-8 [&_nav]:text-white/90 [&_nav_a]:!text-white">
			<?php mcupr_breadcrumb( false ); ?>
			<div class="mt-6 max-w-4xl">
				<h1 class="mb-4 animate-fade-up text-3xl font-bold leading-tight min-[900px]:text-5xl"><?php the_title(); ?></h1>
				<div class="[&_*]:!text-white [&_span.rounded-full]:!bg-primary [&_span.rounded-full]:!text-on-primary"><?php get_template_part( 'template-parts/article-meta' ); ?></div>
			</div>
		</div>
	</header>

	<div class="wrap mt-10 grid gap-8 min-[1000px]:grid-cols-[14rem_minmax(0,46rem)] min-[1000px]:justify-center">
		<aside class="no-print min-[1000px]:sticky min-[1000px]:top-6 min-[1000px]:self-start" aria-label="<?php echo esc_attr( mcupr_t( 'article_tools' ) ); ?>">
			<div class="space-y-4">
				<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
				<?php get_template_part( 'template-parts/article-tools' ); ?>
			</div>
		</aside>
		<div>
			<?php mcupr_missing_translation_notice(); ?>
			<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
			<?php the_tags( '<p class="mt-8 flex flex-wrap gap-2 text-sm">', ' ', '</p>' ); ?>
			<?php get_template_part( 'template-parts/post-nav' ); ?>
		</div>
	</div>
</article>
<div class="wrap mt-14"><?php get_template_part( 'template-parts/related' ); ?></div>
