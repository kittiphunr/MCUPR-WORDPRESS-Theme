<?php
/**
 * News layout 1 – Reader: one centred column, large type.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<article data-layout="reader" id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<?php if ( mcupr_office_shown() ) : ?>
		<?php get_template_part( 'template-parts/office-article-head' ); ?>
	<?php else : ?>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<?php get_template_part( 'template-parts/article-meta', null, array( 'part' => 'cat' ) ); ?>
			<h1 class="post-title mt-4 mb-5"><?php the_title(); ?></h1>
			<?php get_template_part( 'template-parts/article-meta', null, array( 'part' => 'info' ) ); ?>
		</div>
	</header>
	<?php endif; ?>

	<div class="panel-card wrap <?php echo mcupr_office_shown() ? 'mt-6' : '-mt-6'; ?> relative">
		<?php mcupr_missing_translation_notice(); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="noimg:hidden m-0 mb-8"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl', 'sizes' => '(min-width: 1300px) 1200px, calc(100vw - 4rem)', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
		<?php endif; ?>
		<div class="mb-8 space-y-3">
			<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
			<?php get_template_part( 'template-parts/article-tools' ); ?>
		</div>
		<div id="article-body" class="reader mx-auto max-w-[48rem]"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
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
