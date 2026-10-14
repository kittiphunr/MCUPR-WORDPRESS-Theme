<?php
/**
 * Front page: featured story + headlines, quick links, latest news.
 *
 * @package MCUPR
 */

get_header();

$news  = new WP_Query(
	array(
		'posts_per_page'      => 9,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$posts = $news->posts;
$hero  = array_slice( $posts, 0, 1 );
$side  = array_slice( $posts, 1, 4 );
$rest  = array_slice( $posts, 5 );
?>
<h1 class="sr-only"><?php echo esc_html( mcupr_org_name() ); ?></h1>

<?php if ( $hero ) : ?>
<section aria-labelledby="latest-title">
	<div class="wrap">
		<h2 id="latest-title" class="mb-4 mt-8 border-l-[6px] border-accent pl-3 text-2xl font-bold contrast:border-line"><?php mcupr_e( 'latest_news' ); ?></h2>
		<div class="grid items-start gap-6 min-[900px]:grid-cols-[2fr_1fr]">
			<?php
			foreach ( $hero as $p ) :
				setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				?>
				<article class="overflow-hidden rounded-lg border border-line bg-surface">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="noimg:hidden" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'mcupr-hero', array( 'alt' => '', 'class' => 'block aspect-video w-full object-cover' ) ); ?></a>
					<?php endif; ?>
					<div class="px-5 py-4"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<p class="mb-1 text-sm text-muted"><?php mcupr_date(); ?></p>
						<h3 class="mb-2 text-2xl font-bold leading-snug"><a class="text-fg" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div><?php the_excerpt(); ?></div>
					</div>
				</article>
			<?php endforeach; ?>

			<?php if ( $side ) : ?>
				<ul class="m-0 list-none divide-y divide-line rounded-lg border border-line p-0">
					<?php
					foreach ( $side as $p ) :
						setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						?>
						<li class="px-4 py-3.5"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<span class="block text-sm text-muted"><?php mcupr_date(); ?></span>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( has_nav_menu( 'quick' ) ) : ?>
<section aria-labelledby="quick-title">
	<div class="wrap">
		<h2 id="quick-title" class="mb-4 mt-8 border-l-[6px] border-accent pl-3 text-2xl font-bold contrast:border-line"><?php mcupr_e( 'quick_links' ); ?></h2>
		<?php wp_nav_menu( array( 'theme_location' => 'quick', 'container' => false, 'menu_class' => 'quick-menu', 'depth' => 1 ) ); ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $rest ) : ?>
<section aria-labelledby="news-title">
	<div class="wrap">
		<h2 id="news-title" class="mb-4 mt-8 border-l-[6px] border-accent pl-3 text-2xl font-bold contrast:border-line"><?php mcupr_e( 'all_news' ); ?></h2>
		<div class="my-6 grid grid-cols-[repeat(auto-fill,minmax(17rem,1fr))] gap-6">
			<?php
			foreach ( $rest as $p ) {
				setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				get_template_part( 'template-parts/card' );
			}
			?>
		</div>
		<?php $blog = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : ''; ?>
		<?php if ( $blog ) : ?>
			<p class="mt-6 text-center"><a class="btn" href="<?php echo esc_url( $blog ); ?>"><?php mcupr_e( 'all_news' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>
<?php
wp_reset_postdata();

// Optional page content when a static page is used as the front page.
if ( is_page() ) {
	while ( have_posts() ) {
		the_post();
		if ( get_the_content() ) {
			echo '<div class="narrow entry-content mt-8"' . mcupr_part_lang_attr() . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			the_content();
			echo '</div>';
		}
	}
}
get_footer();
