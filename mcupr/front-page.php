<?php
/**
 * Front page (Chula-style): hero with featured story + three headlines,
 * quick links, then the latest news. If the front page is a static page built
 * with Elementor, that design is shown instead.
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

$news  = new WP_Query(
	array(
		'posts_per_page'      => 10,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$posts = $news->posts;
$hero  = array_slice( $posts, 0, 1 );
$side  = array_slice( $posts, 1, 3 );
$rest  = array_slice( $posts, 4 );
$blog  = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : '';

/** Overlay tile used in the hero. */
$tile = static function ( $big ) {
	$lang = mcupr_part_lang_attr();
	?>
	<article class="group relative isolate flex min-h-56 flex-col justify-end overflow-hidden rounded-lg bg-neutral-800 text-white <?php echo $big ? 'min-h-72 min-[900px]:row-span-3 min-[900px]:min-h-[28rem]' : ''; ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<span class="noimg:hidden absolute inset-0 -z-10" aria-hidden="true"><?php the_post_thumbnail( $big ? 'mcupr-hero' : 'mcupr-card', array( 'alt' => '', 'class' => 'size-full object-cover transition-transform duration-500 group-hover:scale-105' ) ); ?></span>
		<?php endif; ?>
		<span class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/45 to-transparent" aria-hidden="true"></span>
		<div class="p-5"<?php echo $lang; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<p class="mb-1 flex flex-wrap gap-x-3 text-sm text-white">
				<?php if ( mcupr_first_category() ) : ?>
					<span class="rounded bg-primary px-2 py-0.5 font-bold text-on-primary"><?php echo esc_html( mcupr_first_category() ); ?></span>
				<?php endif; ?>
				<?php mcupr_date(); ?>
			</p>
			<h3 class="<?php echo $big ? 'text-2xl min-[900px]:text-3xl' : 'text-lg'; ?> font-bold leading-snug"><a class="text-white after:absolute after:inset-0 after:content-['']" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		</div>
	</article>
	<?php
};
?>
<h1 class="sr-only"><?php echo esc_html( mcupr_org_name() ); ?></h1>

<?php if ( $hero ) : ?>
<section class="wrap mt-6" aria-labelledby="latest-title">
	<h2 id="latest-title" class="sr-only"><?php mcupr_e( 'featured' ); ?></h2>
	<div class="grid gap-4 min-[900px]:grid-cols-[2fr_1fr] min-[900px]:grid-rows-3">
		<?php
		foreach ( $hero as $p ) {
			setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$tile( true );
		}
		foreach ( $side as $p ) {
			setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			$tile( false );
		}
		?>
	</div>
</section>
<?php endif; ?>

<?php if ( has_nav_menu( 'quick' ) ) : ?>
<section class="wrap mt-12" aria-labelledby="quick-title">
	<div class="mb-5 flex items-end justify-between gap-4 border-b border-line pb-2"><h2 id="quick-title" class="relative text-2xl font-bold after:absolute after:-bottom-[calc(.5rem+1px)] after:left-0 after:h-1 after:w-16 after:bg-primary after:content-[""] contrast:after:bg-[var(--text)]"><?php mcupr_e( 'quick_links' ); ?></h2></div>
	<?php wp_nav_menu( array( 'theme_location' => 'quick', 'container' => false, 'menu_class' => 'quick-menu', 'depth' => 1 ) ); ?>
</section>
<?php endif; ?>

<?php if ( $rest ) : ?>
<section class="wrap mt-12" aria-labelledby="news-title">
	<div class="mb-5 flex items-end justify-between gap-4 border-b border-line pb-2">
		<h2 id="news-title" class="relative text-2xl font-bold after:absolute after:-bottom-[calc(.5rem+1px)] after:left-0 after:h-1 after:w-16 after:bg-primary after:content-[""] contrast:after:bg-[var(--text)]"><?php mcupr_e( 'latest_news' ); ?></h2>
		<?php if ( $blog ) : ?>
			<a class="font-bold" href="<?php echo esc_url( $blog ); ?>"><?php mcupr_e( 'all_news' ); ?> <span aria-hidden="true">›</span></a>
		<?php endif; ?>
	</div>
	<div class="grid grid-cols-[repeat(auto-fill,minmax(17rem,1fr))] gap-6">
		<?php
		foreach ( $rest as $p ) {
			setup_postdata( $GLOBALS['post'] = $p ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			get_template_part( 'template-parts/card' );
		}
		?>
	</div>
</section>
<?php endif; ?>
<?php
wp_reset_postdata();

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
