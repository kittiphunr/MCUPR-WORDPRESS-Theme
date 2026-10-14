<?php
/**
 * Front page: featured story + headlines, quick links, latest news.
 *
 * @package MCUPR
 */

get_header();

$news = new WP_Query(
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
<?php if ( $hero ) : ?>
<section class="hero" aria-labelledby="latest-title">
	<div class="wrap">
		<h2 id="latest-title" class="section-title"><?php mcupr_e( 'latest_news' ); ?></h2>
		<div class="hero__grid">
			<?php
			foreach ( $hero as $p ) :
				setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				?>
				<article class="hero__main">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="hero__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'mcupr-hero', array( 'alt' => '' ) ); ?></a>
					<?php endif; ?>
					<div class="hero__text"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
						<p class="card__meta"><?php mcupr_date(); ?></p>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<div><?php the_excerpt(); ?></div>
					</div>
				</article>
			<?php endforeach; ?>

			<?php if ( $side ) : ?>
				<ul class="hero__list">
					<?php
					foreach ( $side as $p ) :
						setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
						?>
						<li<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
							<span class="card__meta"><?php mcupr_date(); ?></span>
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
<section class="quick" aria-labelledby="quick-title">
	<div class="wrap">
		<h2 id="quick-title" class="section-title"><?php mcupr_e( 'quick_links' ); ?></h2>
		<?php wp_nav_menu( array( 'theme_location' => 'quick', 'container' => false, 'menu_class' => 'quick__list', 'depth' => 1 ) ); ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $rest ) : ?>
<section class="news" aria-labelledby="news-title">
	<div class="wrap">
		<h2 id="news-title" class="section-title"><?php mcupr_e( 'all_news' ); ?></h2>
		<div class="grid">
			<?php
			foreach ( $rest as $p ) {
				setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				get_template_part( 'template-parts/card' );
			}
			?>
		</div>
		<?php $blog = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : ''; ?>
		<?php if ( $blog ) : ?>
			<p class="more"><a class="btn" href="<?php echo esc_url( $blog ); ?>"><?php mcupr_e( 'all_news' ); ?></a></p>
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
			echo '<div class="wrap narrow entry-content"' . mcupr_part_lang_attr() . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			the_content();
			echo '</div>';
		}
	}
}
get_footer();
