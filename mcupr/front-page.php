<?php
/**
 * Front page (Chula-style): full-width hero photo with headline and glass quick
 * links, a scrollable featured-news slider, latest news grid and a pale banner.
 * If the front page is a static page built with Elementor, that design is shown.
 *
 * @package MCUPR
 */

get_header();

// Static front page designed in Elementor: show only its content.
if ( is_page() && mcupr_is_elementor_page( get_queried_object_id() ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	get_footer();
	return;
}

$news   = new WP_Query(
	array(
		'posts_per_page'      => 12,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$posts  = $news->posts;
$slider = array_slice( $posts, 0, 6 );
$rest   = array_slice( $posts, 6 );
$blog   = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '';

$hero_img = mcupr_hero_image_url( $posts ? $posts[0]->ID : 0 );
$line1    = mcupr_text_mod( 'mcupr_hero_l1' );
$line2    = mcupr_text_mod( 'mcupr_hero_l2' );
if ( '' === $line1 . $line2 ) {
	$line2 = mcupr_org_name();
}
$cta_title = mcupr_text_mod( 'mcupr_cta_title' );
$cta_url   = get_theme_mod( 'mcupr_cta_url', '' );
?>
<section class="relative isolate flex min-h-[30rem] flex-col items-center justify-center overflow-hidden bg-neutral-800 px-3 pt-24 pb-16 text-center text-white min-[400px]:min-h-[34rem] min-[400px]:px-4 min-[900px]:min-h-[min(42rem,75vh)] min-[1600px]:min-h-[min(46rem,70vh)]">
	<?php if ( $hero_img ) : ?>
		<img class="noimg:hidden absolute inset-0 -z-20 size-full object-cover" src="<?php echo esc_url( $hero_img ); ?>" alt="" fetchpriority="high">
	<?php endif; ?>
	<span class="absolute inset-0 -z-10 bg-black/50" aria-hidden="true"></span>

	<h1 class="mb-10 max-w-4xl animate-fade-up leading-tight [text-shadow:0_2px_12px_rgb(0_0_0/.5)]">
		<?php if ( $line1 ) : ?><span class="block text-2xl font-light min-[400px]:text-3xl min-[900px]:text-5xl"><?php echo esc_html( $line1 ); ?></span><?php endif; ?>
		<?php if ( $line2 ) : ?><span class="block text-3xl font-bold min-[400px]:text-4xl min-[900px]:text-6xl"><?php echo esc_html( $line2 ); ?></span><?php endif; ?>
	</h1>

	<?php if ( has_nav_menu( 'quick' ) ) : ?>
		<nav class="wrap animate-fade-up [animation-delay:.2s]" aria-label="<?php echo esc_attr( mcupr_t( 'quick_links' ) ); ?>">
			<?php wp_nav_menu( array( 'theme_location' => 'quick', 'container' => false, 'menu_class' => 'hero-menu', 'depth' => 1 ) ); ?>
		</nav>
	<?php endif; ?>
</section>

<?php if ( $slider ) : ?>
<section class="wrap mt-10" aria-labelledby="featured-title" data-carousel data-reveal>
	<h2 id="featured-title" class="sr-only"><?php mcupr_e( 'featured' ); ?></h2>
	<div id="news-slider" class="snap-row" tabindex="0" role="region" aria-label="<?php echo esc_attr( mcupr_t( 'news_slider' ) ); ?>" data-carousel-track>
		<?php
		foreach ( $slider as $p ) :
			setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			?>
			<article class="tilt group relative isolate flex min-h-72 min-w-[88%] flex-col justify-end overflow-hidden rounded-2xl bg-neutral-800 text-white min-[700px]:min-w-[26rem]">
				<?php if ( has_post_thumbnail() ) : ?>
					<span class="noimg:hidden absolute inset-0 -z-20" aria-hidden="true"><?php the_post_thumbnail( 'mcupr-hero', array( 'alt' => '', 'class' => 'size-full object-cover transition-transform duration-500 group-hover:scale-105' ) ); ?></span>
				<?php endif; ?>
				<span class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/50 to-black/10" aria-hidden="true"></span>
				<div class="p-5"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
					<p class="mb-1 text-sm text-white"><?php echo esc_html( mcupr_first_category() ); ?> · <?php mcupr_date(); ?></p>
					<h3 class="mb-4 text-xl font-bold leading-snug"><?php the_title(); ?></h3>
					<a class="inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-5 py-1.5 font-semibold text-black no-underline after:absolute after:inset-0 after:content-[''] hover:bg-neutral-200" href="<?php the_permalink(); ?>">
						<?php mcupr_e( 'read_more' ); ?><span class="sr-only">: <?php the_title(); ?></span> <span aria-hidden="true">→</span>
					</a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<div class="mt-4 flex justify-end gap-2">
		<button type="button" class="btn-tool !size-11 !rounded-full !p-0" data-carousel-prev aria-controls="news-slider" aria-label="<?php echo esc_attr( mcupr_t( 'slide_prev' ) ); ?>"><span aria-hidden="true">←</span></button>
		<button type="button" class="btn-tool !size-11 !rounded-full !p-0" data-carousel-next aria-controls="news-slider" aria-label="<?php echo esc_attr( mcupr_t( 'slide_next' ) ); ?>"><span aria-hidden="true">→</span></button>
	</div>
</section>
<?php endif; ?>

<?php if ( $rest ) : ?>
<section class="wrap mt-14" aria-labelledby="news-title">
	<div class="mb-6 flex items-end justify-between gap-4">
		<h2 id="news-title" class="text-2xl font-bold min-[900px]:text-3xl"><?php mcupr_e( 'news_events' ); ?></h2>
		<?php if ( $blog ) : ?>
			<a class="btn-outline" href="<?php echo esc_url( $blog ); ?>"><?php mcupr_e( 'all_news' ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
	<div class="grid grid-cols-[repeat(auto-fill,minmax(min(17rem,100%),1fr))] gap-6" data-reveal-group>
		<?php
		foreach ( $rest as $p ) {
			setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			get_template_part( 'template-parts/card' );
		}
		?>
	</div>
</section>
<?php endif; ?>
<?php wp_reset_postdata(); ?>

<?php if ( $cta_title ) : ?>
<section class="wrap mt-16" aria-label="<?php echo esc_attr( $cta_title ); ?>">
	<div class="flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-tint px-8 py-12 contrast:border-2 contrast:border-line">
		<p class="m-0 max-w-2xl text-2xl font-bold leading-snug min-[900px]:text-4xl"><?php echo esc_html( $cta_title ); ?></p>
		<?php if ( $cta_url ) : ?>
			<a class="btn" href="<?php echo esc_url( $cta_url ); ?>"><?php mcupr_e( 'read_more' ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php
// Optional extra content when a static (non-Elementor) page is the front page.
if ( is_page() ) {
	while ( have_posts() ) {
		the_post();
		if ( get_the_content() ) {
			echo '<div class="narrow entry-content mt-12"' . mcupr_part_lang_attr() . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			the_content();
			echo '</div>';
		}
	}
}
get_footer();
