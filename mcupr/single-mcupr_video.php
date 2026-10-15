<?php
/**
 * One video: the player, then the description.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$mcupr_id = get_the_ID();
	$mcupr_v  = mcupr_video_parse( mcupr_video_url( $mcupr_id ) );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mcu-title mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<p class="mt-3 mb-0 text-muted"><?php echo esc_html( get_the_date( 'j M Y' ) ); ?></p>
		</div>
	</header>
	<div class="wrap mt-8">
		<div class="mx-auto max-w-4xl">
			<?php if ( $mcupr_v ) : ?>
				<div class="vid-wrap"><?php echo mcupr_video_html( mcupr_video_url( $mcupr_id ), get_the_title(), get_post_thumbnail_id() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php if ( 'file' !== $mcupr_v['provider'] ) : ?>
					<p class="mt-3 text-sm"><a class="ev-link" href="<?php echo esc_url( $mcupr_v['watch'] ); ?>" rel="noopener" target="_blank"><?php mcupr_e( 'vd_watch' ); ?> ↗<span class="sr-only"> (<?php mcupr_e( 'new_tab' ); ?>)</span></a></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( '' !== trim( get_the_content() ) ) : ?>
				<div class="reader mt-8"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php the_content(); ?></div>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/share' ); ?>
			<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_video' ) ); ?>">‹ <?php mcupr_e( 'vd_title' ); ?></a></p>
		</div>
	</div>
</article>
	<?php
endwhile;
get_footer();
