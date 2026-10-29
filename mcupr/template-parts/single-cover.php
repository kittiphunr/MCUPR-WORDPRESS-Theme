<?php
/**
 * News layout 2 – Cover: full-width hero image with the title on it, tools in a sticky side rail.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<article data-layout="cover" id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<?php if ( mcupr_office_shown() ) : ?>
		<?php get_template_part( 'template-parts/office-article-head' ); ?>
	<?php else : ?>
	<header class="post-hero<?php echo has_post_thumbnail() ? ' has-photo' : ''; ?> mcu-wheel relative isolate flex min-h-[24rem] items-end overflow-hidden bg-neutral-800 text-white min-[900px]:min-h-[32rem]"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<span class="noimg:hidden absolute inset-0 -z-20" aria-hidden="true"><?php the_post_thumbnail( 'mcupr-hero', array( 'alt' => '', 'class' => 'size-full object-cover', 'sizes' => '100vw', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></span>
		<?php endif; ?>
		<span class="post-hero-scrim" aria-hidden="true"></span>
		<div class="wrap pt-24 pb-24 [&_nav]:text-white/90 [&_nav_a]:!text-white">
			<?php mcupr_breadcrumb( false ); ?>
			<div class="mt-8 max-w-4xl">
				<?php get_template_part( 'template-parts/article-meta', null, array( 'part' => 'cat' ) ); ?>
				<h1 class="post-title mt-4 mb-5 animate-fade-up"><?php the_title(); ?></h1>
				<?php get_template_part( 'template-parts/article-meta', null, array( 'part' => 'info' ) ); ?>
			</div>
		</div>
	</header>
	<?php endif; ?>

	<div class="wrap relative z-10 -mt-14 grid gap-8 min-[1000px]:grid-cols-[14rem_minmax(0,1fr)]">
		<aside class="no-print min-[1000px]:sticky min-[1000px]:top-6 min-[1000px]:self-start" aria-label="<?php echo esc_attr( mcupr_t( 'article_tools' ) ); ?>">
			<div class="space-y-4">
				<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
				<?php get_template_part( 'template-parts/article-tools' ); ?>
			</div>
		</aside>
		<div class="panel-card min-w-0">
			<div class="post-col mx-auto max-w-[52rem]">
			<?php mcupr_missing_translation_notice(); ?>
			<?php if ( mcupr_office_shown() && has_post_thumbnail() ) : ?>
				<figure class="noimg:hidden m-0 mb-6"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl', 'sizes' => '(min-width: 1300px) 940px, (min-width: 1000px) 70vw, calc(100vw - 4rem)', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
			<?php endif; ?>
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
		</div>
	</div>
</article>
<div class="wrap mt-14"><?php get_template_part( 'template-parts/related' ); ?></div>
