<?php
/**
 * News layout 3 – Sidebar: article on the left; related news, latest news and categories on the right.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$box = 'rounded-2xl border border-line bg-bg p-5';
?>
<div class="wrap">
	<?php mcupr_breadcrumb(); ?>
	<div class="mt-2 grid gap-10 min-[1000px]:grid-cols-[minmax(0,1fr)_20rem]">
		<article data-layout="sidebar" id="post-<?php the_ID(); ?>" <?php post_class( 'min-w-0' ); ?>>
			<header<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<h1 class="mcu-title mt-4 mb-5 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
				<?php get_template_part( 'template-parts/article-meta' ); ?>
			</header>
			<?php mcupr_missing_translation_notice(); ?>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="noimg:hidden mt-6"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl' ) ); ?></figure>
			<?php endif; ?>
			<div class="my-6 space-y-3">
				<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
				<?php get_template_part( 'template-parts/article-tools' ); ?>
			</div>
			<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
			<?php the_tags( '<p class="mt-8 flex flex-wrap gap-2 text-sm">', ' ', '</p>' ); ?>
			<?php get_template_part( 'template-parts/post-nav' ); ?>
		</article>

		<aside class="no-print space-y-6 min-[1000px]:sticky min-[1000px]:top-6 min-[1000px]:self-start" aria-label="<?php echo esc_attr( mcupr_t( 'related' ) ); ?>">
			<div class="<?php echo esc_attr( $box ); ?>"><?php get_template_part( 'template-parts/related', null, array( 'count' => 4, 'style' => 'list' ) ); ?></div>
			<?php
			$latest = new WP_Query( array( 'posts_per_page' => 4, 'post__not_in' => array( get_the_ID() ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
			if ( $latest->have_posts() ) :
				?>
				<section class="<?php echo esc_attr( $box ); ?>" aria-labelledby="latest-side">
					<h2 id="latest-side" class="mcu-title mb-4 text-xl font-bold"><?php mcupr_e( 'latest_news' ); ?></h2>
					<ul class="m-0 list-none divide-y divide-line p-0">
						<?php
						while ( $latest->have_posts() ) :
							$latest->the_post();
							?>
							<li class="py-3"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
								<p class="m-0 text-xs text-muted"><?php mcupr_date(); ?></p>
								<a class="inline-block py-0.5 font-semibold leading-snug text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
							</li>
						<?php endwhile; ?>
					</ul>
				</section>
				<?php wp_reset_postdata(); ?>
			<?php endif; ?>
			<section class="<?php echo esc_attr( $box ); ?>" aria-labelledby="cats-side">
				<h2 id="cats-side" class="mcu-title mb-4 text-xl font-bold"><?php mcupr_e( 'news_categories' ); ?></h2>
				<ul class="m-0 flex list-none flex-wrap gap-2 p-0">
					<?php foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) : ?>
						<li><a class="inline-flex min-h-11 items-center rounded-full border border-line px-4 text-fg no-underline hover:border-primary hover:text-link" href="<?php echo esc_url( get_category_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</aside>
	</div>
</div>
