<?php
/**
 * Single post.
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
				<p class="entry-meta"><?php echo esc_html( mcupr_t( 'posted_on' ) ); ?> <?php mcupr_date(); ?></p>
			</header>
			<?php mcupr_missing_translation_notice(); ?>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="entry-thumb"><?php the_post_thumbnail( 'mcupr-hero' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>

		<nav class="post-nav" aria-label="<?php echo esc_attr( mcupr_t( 'pagination' ) ); ?>">
			<?php
			previous_post_link( '<span class="post-nav__prev">%link</span>', '‹ %title' );
			next_post_link( '<span class="post-nav__next">%link</span>', '%title ›' );
			?>
		</nav>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	endwhile;
	?>
</div>
<?php
get_footer();
