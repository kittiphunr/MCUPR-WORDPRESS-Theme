<?php
/**
 * Page.
 *
 * @package MCUPR
 */

get_header();
?>
<div class="wrap narrow">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
			<header class="entry-header"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<h1 class="page-title"><?php the_title(); ?></h1>
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
	endwhile;
	?>
</div>
<?php
get_footer();
