<?php
/**
 * One album: description and the pictures with a lightbox.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$id = get_the_ID();
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 mb-3 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<p class="m-0 text-muted"><?php echo esc_html( get_the_date( 'j M Y' ) ); ?> · <span class="inline-flex items-center gap-1 align-middle"><?php echo mcupr_icon( 'camera', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo (int) count( mcupr_album_images( $id ) ); ?></span></p>
		</div>
	</header>
	<div class="wrap mt-8">
		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<div class="reader mb-8 max-w-3xl"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php the_content(); ?></div>
		<?php endif; ?>
		<?php echo mcupr_album_gallery( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php get_template_part( 'template-parts/share' ); ?>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_album' ) ); ?>">‹ <?php mcupr_e( 'album_title' ); ?></a></p>
	</div>
</article>
	<?php
endwhile;
get_footer();
