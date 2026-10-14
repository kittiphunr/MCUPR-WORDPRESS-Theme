<?php
/**
 * Single post.
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'single' ) ) :
	mcupr_breadcrumb();
	while ( have_posts() ) :
		the_post();
		if ( mcupr_is_elementor_page() ) {
			the_content(); // Designed in Elementor: full width, no theme title.
			continue;
		}
		?>
<div class="narrow">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( mcupr_first_category() ) : ?>
					<p class="mb-1 text-sm font-bold uppercase tracking-wide text-link"><?php echo esc_html( mcupr_first_category() ); ?></p>
				<?php endif; ?>
				<h1 class="mb-5 mt-4 border-b-4 border-primary pb-2 text-3xl font-bold contrast:border-line max-[700px]:text-2xl"><?php the_title(); ?></h1>
				<p class="text-muted"><?php echo esc_html( mcupr_t( 'posted_on' ) ); ?> <?php mcupr_date(); ?></p>
			</header>
			<?php mcupr_missing_translation_notice(); ?>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="noimg:hidden mb-6"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'rounded-lg' ) ); ?></figure>
			<?php endif; ?>
			<div class="entry-content"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>

		<nav class="my-8 flex justify-between gap-4" aria-label="<?php echo esc_attr( mcupr_t( 'pagination' ) ); ?>">
			<?php
			previous_post_link( '<span>%link</span>', '‹ %title' );
			next_post_link( '<span>%link</span>', '%title ›' );
			?>
		</nav>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
</div>
		<?php
	endwhile;
endif;

get_footer();
