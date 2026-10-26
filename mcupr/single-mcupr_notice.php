<?php
/**
 * One notice: title, type, date, details, its files (download list, PDF reader), share, and the way back to the list.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$cats = get_the_terms( get_the_ID(), 'mcupr_notice_cat' );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="mx-auto w-[min(100%-1.5rem,46rem)] min-[400px]:w-[min(100%-2rem,46rem)]">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 mb-3 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<div class="wk-badges">
				<span class="wk-pill"><?php echo mcupr_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( mcupr_date_text( (int) get_post_timestamp(), 'short' ) ); ?></time></span>
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?><a class="wk-pill wk-pill-cat" href="<?php echo esc_url( get_term_link( $cats[0] ) ); ?>"><?php echo mcupr_icon( 'folder', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $cats[0]->name ); ?></a><?php endif; ?>
				<?php if ( mcupr_notice_is_new( get_post() ) ) : ?><span class="nt-new"><?php mcupr_e( 'nt_new' ); ?></span><?php endif; ?>
			</div>
		</div>
	</header>
	<div class="panel-card mx-auto -mt-6 w-[min(100%-1.5rem,48rem)] min-[400px]:w-[min(100%-2rem,48rem)] relative">
		<?php mcupr_missing_translation_notice(); ?>
		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php the_content(); ?>
			</div>
		<?php endif; ?>
		<?php mcupr_attachments(); ?>
		<?php get_template_part( 'template-parts/share' ); ?>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_notice' ) ); ?>">‹ <?php mcupr_e( 'nt_back' ); ?></a></p>
	</div>
</article>
	<?php
endwhile;
mcupr_slot( 'content_after' );
get_footer();
