<?php
/**
 * Single event: date / place / register box, add-to-calendar buttons, content.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$info = mcupr_event_info( get_the_ID() );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="mx-auto w-[min(100%-1.5rem,46rem)] min-[400px]:w-[min(100%-2rem,46rem)]">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 mb-2 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<p class="m-0 text-lg text-muted"><?php echo esc_html( mcupr_event_when( $info ) ); ?></p>
		</div>
	</header>

	<div class="panel-card mx-auto -mt-6 w-[min(100%-1.5rem,48rem)] min-[400px]:w-[min(100%-2rem,48rem)] relative">
		<?php mcupr_missing_translation_notice(); ?>
		<dl class="ev-info">
			<div><dt><?php echo mcupr_icon( 'calendar', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'ev_when' ); ?></span></dt><dd><?php echo esc_html( mcupr_event_when( $info ) ); ?></dd></div>
			<?php if ( '' !== $info['place'] ) : ?>
				<div><dt><?php echo mcupr_icon( 'pin', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'ev_where' ); ?></span></dt><dd><?php echo esc_html( $info['place'] ); ?></dd></div>
			<?php endif; ?>
		</dl>
		<p class="ev-actions">
			<?php if ( '' !== $info['url'] ) : ?>
				<a class="btn" href="<?php echo esc_url( $info['url'] ); ?>"><?php mcupr_e( 'ev_register' ); ?></a>
			<?php endif; ?>
			<a class="ev-link" href="<?php echo esc_url( mcupr_event_google_url( get_the_ID() ) ); ?>" target="_blank" rel="noopener noreferrer"><?php mcupr_e( 'add_gcal' ); ?><span class="sr-only"> (<?php echo esc_html( mcupr_t( 'new_tab' ) ); ?>)</span></a>
			<a class="ev-link" href="<?php echo esc_url( add_query_arg( 'mcupr_ics', get_the_ID(), home_url( '/' ) ) ); ?>"><?php mcupr_e( 'download_ics' ); ?></a>
		</p>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="noimg:hidden m-0 mb-8"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl', 'sizes' => '(min-width: 800px) 704px, calc(100vw - 4rem)', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
		<?php endif; ?>
		<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php the_content(); ?>
		</div>
		<div class="mt-8"><?php get_template_part( 'template-parts/article-tools' ); ?></div>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_event' ) ); ?>">‹ <?php mcupr_e( 'events_title' ); ?></a></p>
	</div>
</article>
	<?php
endwhile;
mcupr_slot( 'content_after' );
get_footer();
