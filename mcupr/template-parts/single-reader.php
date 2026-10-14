<?php
/**
 * News layout 1 – Reader: one centred column, large type.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<article data-layout="reader" id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="mx-auto w-[min(100%-1.5rem,46rem)] min-[400px]:w-[min(100%-2rem,46rem)]">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 mb-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<?php get_template_part( 'template-parts/article-meta' ); ?>
		</div>
	</header>

	<div class="panel-card mx-auto -mt-6 w-[min(100%-1.5rem,48rem)] min-[400px]:w-[min(100%-2rem,48rem)] relative">
		<?php mcupr_missing_translation_notice(); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="noimg:hidden m-0 mb-8"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl', 'sizes' => '(min-width: 800px) 704px, calc(100vw - 4rem)', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
		<?php endif; ?>
		<div class="mb-8 space-y-3">
			<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
			<?php get_template_part( 'template-parts/article-tools' ); ?>
		</div>
		<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
		<?php mcupr_attachments(); ?>
		<?php the_tags( '<p class="mt-8 flex flex-wrap gap-2 text-sm [&_a]:inline-flex [&_a]:min-h-8 [&_a]:items-center [&_a]:rounded-full [&_a]:bg-tint [&_a]:px-3 [&_a]:no-underline"><span class="sr-only">' . esc_html( mcupr_t( 'tags' ) ) . '</span>', '', '</p>' ); ?>
		<?php get_template_part( 'template-parts/share' ); ?>
		<?php get_template_part( 'template-parts/post-nav' ); ?>
	</div>
</article>
<div class="wrap mt-14"><?php get_template_part( 'template-parts/related' ); ?></div>
