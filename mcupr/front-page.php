<?php
/**
 * Front page (Chula-style): full-width hero photo with headline and glass quick
 * links, a scrollable featured-news slider, latest news grid and a pale banner.
 *
 * @package MCUPR
 */

// Page 2+ and ?mcupr_all=1 (home page set to "latest posts"): the plain, paged news list.
if ( mcupr_is_news_list_view() ) {
	require locate_template( 'index.php' );
	return;
}

get_header();

$news   = new WP_Query(
	array(
		'posts_per_page'      => 12,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$news_posts = $news->posts; // not $posts / $p: those are WordPress globals and templates run in the global scope
$slider     = array_slice( $news_posts, 0, 6 );
$rest       = array_slice( $news_posts, 6 );
$blog   = mcupr_news_url();

$hero_img = mcupr_hero_image_url( $news_posts ? $news_posts[0]->ID : 0 );
$line1    = mcupr_text_mod( 'mcupr_hero_l1' );
$line2    = mcupr_text_mod( 'mcupr_hero_l2' );
if ( '' === $line1 . $line2 ) {
	$line1 = get_bloginfo( 'description' );
	$line2 = mcupr_org_name();
}
$cta_title = mcupr_text_mod( 'mcupr_cta_title' );
$cta_url   = get_theme_mod( 'mcupr_cta_url', '' );
?>
<div class="wrap mt-6"><section class="relative isolate flex min-h-[26rem] rounded-3xl flex-col items-center justify-center overflow-hidden px-3 py-16 text-center min-[400px]:min-h-[30rem] min-[400px]:px-6 min-[900px]:min-h-[min(36rem,70vh)] <?php echo $hero_img ? 'bg-neutral-800 text-white' : 'bg-gradient-to-br from-primary to-brand2 text-on-primary'; ?>">
	<?php if ( $hero_img ) : ?>
		<img class="noimg:hidden absolute inset-0 -z-20 size-full object-cover" src="<?php echo esc_url( $hero_img ); ?>" alt="" fetchpriority="high">
	<?php endif; ?>
	<?php if ( $hero_img ) : ?><span class="absolute inset-0 -z-10 bg-black/50" aria-hidden="true"></span><?php endif; ?>

	<h1 class="mb-10 max-w-4xl animate-fade-up leading-tight [text-shadow:0_2px_12px_rgb(0_0_0/.5)]">
		<?php if ( $line1 ) : ?><span class="block text-2xl font-light min-[400px]:text-3xl min-[900px]:text-5xl"><?php echo esc_html( $line1 ); ?></span><?php endif; ?>
		<?php if ( $line2 ) : ?><span class="block text-3xl font-bold min-[400px]:text-4xl min-[900px]:text-6xl"><?php echo esc_html( $line2 ); ?></span><?php endif; ?>
	</h1>

	<?php
	// Quick links: the "Quick links" menu; until one is assigned, the first items of the main menu.
	$quick_args = has_nav_menu( 'quick' ) ? array( 'theme_location' => 'quick' ) : ( has_nav_menu( 'primary_' . mcupr_lang() ) ? array( 'theme_location' => 'primary_' . mcupr_lang() ) : ( has_nav_menu( 'primary_' . mcupr_default_lang() ) ? array( 'theme_location' => 'primary_' . mcupr_default_lang() ) : array() ) );
	if ( $quick_args ) :
		?>
		<nav class="w-full max-w-6xl animate-fade-up [animation-delay:.2s]" aria-label="<?php echo esc_attr( mcupr_t( 'quick_links' ) ); ?>">
			<?php wp_nav_menu( $quick_args + array( 'container' => false, 'menu_class' => 'hero-menu', 'depth' => 1 ) ); ?>
		</nav>
	<?php endif; ?>
</section></div>
<?php mcupr_slot( 'home_top' ); ?>

<?php
$about_text = mcupr_text_mod( 'mcupr_about' );
if ( $about_text ) :
	$about_page = (int) get_theme_mod( 'mcupr_home_about_page', 0 );
	?>
<section class="wrap mt-10" aria-labelledby="about-intro" data-reveal>
	<div class="soft-card flex flex-wrap items-center gap-6 p-6 min-[900px]:p-8">
		<div class="flex-none"><?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<div class="min-w-0 flex-1 basis-80">
			<h2 id="about-intro" class="mcu-title mb-3 text-2xl font-bold"><?php echo esc_html( mcupr_org_name() ); ?></h2>
			<p class="m-0 whitespace-pre-line text-muted"><?php echo esc_html( $about_text ); ?></p>
		</div>
		<?php if ( $about_page && 'publish' === get_post_status( $about_page ) ) : ?>
			<a class="btn flex-none" href="<?php echo esc_url( get_permalink( $about_page ) ); ?>"><?php mcupr_e( 'about_us' ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $slider ) : ?>
<section class="wrap mt-10" aria-labelledby="featured-title" data-carousel data-reveal>
	<div class="mb-5 flex items-center justify-between gap-4">
		<h2 id="featured-title" class="m-0 flex items-center gap-3 text-2xl font-bold min-[900px]:text-3xl"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'bulb', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'featured' ); ?></h2>
		<div class="flex gap-2">
			<button type="button" class="slide-btn" data-carousel-prev aria-controls="news-slider" aria-label="<?php echo esc_attr( mcupr_t( 'slide_prev' ) ); ?>"><span aria-hidden="true">←</span></button>
			<button type="button" class="slide-btn" data-carousel-next aria-controls="news-slider" aria-label="<?php echo esc_attr( mcupr_t( 'slide_next' ) ); ?>"><span aria-hidden="true">→</span></button>
		</div>
	</div>
	<div id="news-slider" class="snap-row" tabindex="0" role="region" aria-label="<?php echo esc_attr( mcupr_t( 'news_slider' ) ); ?>" data-carousel-track>
		<?php
		foreach ( $slider as $item ) :
			setup_postdata( $GLOBALS['post'] = $item ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			?>
			<article class="tilt group relative isolate flex min-h-72 min-w-[88%] flex-col justify-end overflow-hidden lift rounded-3xl bg-neutral-800 text-white min-[700px]:min-w-[26rem]">
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
</section>
<?php endif; ?>

<?php if ( $rest ) : ?>
<section class="wrap mt-14" aria-labelledby="news-title">
	<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
		<h2 id="news-title" class="m-0 flex items-center gap-3 text-2xl font-bold min-[900px]:text-3xl"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'news', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'news_events' ); ?></h2>
		<?php $home_cats = get_categories( array( 'hide_empty' => true, 'number' => 5, 'orderby' => 'count', 'order' => 'DESC' ) ); ?>
		<nav aria-label="<?php echo esc_attr( mcupr_t( 'news_categories' ) ); ?>">
			<ul class="pill-tabs m-0 list-none">
				<?php if ( $blog ) : ?><li class="contents"><a href="<?php echo esc_url( $blog ); ?>"><?php mcupr_e( 'all_news' ); ?></a></li><?php endif; ?>
				<?php foreach ( $home_cats as $hc ) : ?>
					<li class="contents"><a href="<?php echo esc_url( get_category_link( $hc ) ); ?>"><?php echo esc_html( $hc->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</div>
	<div class="grid grid-cols-[repeat(auto-fill,minmax(min(17rem,100%),1fr))] gap-6" data-reveal-group>
		<?php
		foreach ( $rest as $i => $item ) {
			setup_postdata( $GLOBALS['post'] = $item ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			get_template_part( 'template-parts/card', null, array( 'variant' => 0 === $i && has_post_thumbnail() ? 'featured' : '' ) );
		}
		?>
	</div>
</section>
<?php endif; ?>
<?php wp_reset_postdata(); ?>

<?php
// News by category: the three categories chosen in the Customizer, else the most used ones.
if ( get_theme_mod( 'mcupr_home_cats_on', true ) ) :
	$cat_terms = array();
	for ( $i = 1; $i <= 3; $i++ ) {
		$term = (int) get_theme_mod( 'mcupr_home_cat_' . $i, 0 ) ? get_term( (int) get_theme_mod( 'mcupr_home_cat_' . $i, 0 ), 'category' ) : null;
		if ( $term && ! is_wp_error( $term ) ) {
			$cat_terms[ $term->term_id ] = $term;
		}
	}
	if ( count( $cat_terms ) < 3 ) {
		$default_cat = get_term( (int) get_option( 'default_category' ), 'category' );
		foreach ( get_categories( array( 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) ) as $term ) {
			if ( ! isset( $cat_terms[ $term->term_id ] ) && ! ( $default_cat && ! is_wp_error( $default_cat ) && 'uncategorized' === $default_cat->slug && $default_cat->term_id === $term->term_id ) && count( $cat_terms ) < 3 ) {
				$cat_terms[ $term->term_id ] = $term;
			}
		}
	}
	if ( $cat_terms ) :
		?>
<section class="wrap mt-14" aria-label="<?php echo esc_attr( mcupr_t( 'news_categories' ) ); ?>">
	<div class="grid grid-cols-[repeat(auto-fit,minmax(min(20rem,100%),1fr))] gap-8">
		<?php
		foreach ( $cat_terms as $term ) {
			get_template_part( 'template-parts/home-cat', null, array( 'term' => $term ) );
		}
		?>
	</div>
</section>
	<?php endif; ?>
<?php endif; ?>

<?php if ( is_active_sidebar( 'home-after' ) ) : ?>
<div class="wrap mt-14"><?php mcupr_widget_area( 'home-after', 'grid gap-6 min-[800px]:grid-cols-[repeat(auto-fit,minmax(16rem,1fr))]' ); ?></div>
<?php endif; ?>

<?php
$visit_addr  = mcupr_text_mod( 'mcupr_address' );
$visit_email = get_theme_mod( 'mcupr_org_email', '' );
$visit_tel   = get_theme_mod( 'mcupr_org_tel', '' );
if ( $visit_addr || $visit_email || $visit_tel ) :
	?>
<section class="wrap mt-14" aria-labelledby="visit-title" data-reveal>
	<h2 id="visit-title" class="mb-5 flex items-center gap-3 text-2xl font-bold min-[900px]:text-3xl"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'pin', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'visit_us' ); ?></h2>
	<div class="grid gap-4 min-[800px]:grid-cols-3">
		<?php if ( $visit_addr ) : ?>
			<div class="stat items-start"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'pin', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><address class="min-w-0 not-italic"><small><?php mcupr_e( 'contact_us_at' ); ?></small><span class="block whitespace-pre-line"><?php echo esc_html( $visit_addr ); ?></span></address></div>
		<?php endif; ?>
		<?php if ( $visit_tel ) : ?>
			<div class="stat"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'phone', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="min-w-0"><small><?php mcupr_e( 'about_tel' ); ?></small><a class="text-lg font-bold text-fg no-underline hover:underline" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $visit_tel ) ); ?>"><?php echo esc_html( $visit_tel ); ?></a></span></div>
		<?php endif; ?>
		<?php if ( $visit_email ) : ?>
			<div class="stat"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'mail', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="min-w-0"><small><?php mcupr_e( 'about_email' ); ?></small><a class="break-all text-lg font-bold text-fg no-underline hover:underline" href="mailto:<?php echo esc_attr( $visit_email ); ?>"><?php echo esc_html( $visit_email ); ?></a></span></div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $cta_title ) : ?>
<section class="wrap mt-16" aria-label="<?php echo esc_attr( $cta_title ); ?>">
	<div class="relative isolate flex flex-wrap items-center justify-between gap-6 overflow-hidden rounded-[2rem] bg-primary px-8 py-12 text-on-primary contrast:border-2 contrast:border-line min-[900px]:px-14">
		<span class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_92%_-5%,rgb(255_255_255/.14)_0,transparent_15rem),radial-gradient(circle_at_38%_115%,rgb(255_255_255/.12)_0,transparent_12rem)]" aria-hidden="true"></span>
		<p class="m-0 max-w-2xl text-2xl font-bold leading-snug min-[900px]:text-4xl"><?php echo esc_html( $cta_title ); ?></p>
		<?php if ( $cta_url ) : ?>
			<a class="inline-flex min-h-12 items-center gap-2 rounded-full bg-on-primary px-7 font-bold text-primary no-underline shadow-lg hover:brightness-95" href="<?php echo esc_url( $cta_url ); ?>"><?php mcupr_e( 'read_more' ); ?> <span aria-hidden="true">→</span></a>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php
// Optional extra content when a static page is the front page.
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
