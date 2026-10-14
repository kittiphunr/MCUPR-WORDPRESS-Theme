<?php
/**
 * Page.
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'single' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( mcupr_is_elementor_page() ) {
			the_content(); // Designed in Elementor: full width, no theme title.
			continue;
		}
		mcupr_breadcrumb();
		?>
<div class="narrow">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<h1 class="mb-5 mt-4 border-b-4 border-primary pb-2 text-3xl font-bold contrast:border-line max-[700px]:text-2xl"><?php the_title(); ?></h1>
			</header>
			<?php mcupr_missing_translation_notice(); ?>
			<div class="entry-content"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>
		<?php
		if ( comments_open() ) {
			comments_template();
		}
		?>
</div>
		<?php
	endwhile;
endif;

get_footer();
