<?php
/**
 * Page: tinted band with a wheel watermark and the title, then the content
 * with a "in this section" list of related pages beside it.
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
		list( $root, $section ) = mcupr_section_pages( get_the_ID() );
		$has_side               = (bool) $section;
		?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mcu-title mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
		</div>
	</header>

	<div class="wrap mt-10 grid gap-10 <?php echo $has_side ? 'min-[1000px]:grid-cols-[minmax(0,1fr)_18rem]' : ''; ?>">
		<div class="panel-card min-w-0 <?php echo $has_side ? '' : 'mx-auto w-full max-w-[52rem]'; ?>">
			<?php mcupr_missing_translation_notice(); ?>
			<div class="no-print mb-6 space-y-3">
				<?php get_template_part( 'template-parts/listen', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?>
				<div class="flex flex-wrap items-center gap-3">
					<button type="button" class="inline-flex min-h-11 cursor-pointer items-center rounded-full border border-line bg-bg px-4 text-fg hover:border-primary hover:text-link" data-print><?php mcupr_e( 'print' ); ?></button>
					<span class="text-sm text-muted"><?php mcupr_e( 'updated_on' ); ?> <?php echo esc_html( mcupr_date_text( get_post_timestamp( null, 'modified' ) ) ); ?></span>
				</div>
			</div>
			<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
			<?php
			if ( comments_open() ) {
				comments_template();
			}
			?>
		</div>

		<?php if ( $has_side ) : ?>
			<aside class="no-print min-[1000px]:sticky min-[1000px]:top-6 min-[1000px]:self-start" aria-labelledby="section-title">
				<div class="rounded-2xl border border-line bg-bg p-5">
					<h2 id="section-title" class="mcu-title mb-4 text-xl font-bold"><?php mcupr_e( 'in_section' ); ?></h2>
					<ul class="m-0 list-none p-0">
						<li><a class="flex min-h-11 items-center rounded-lg px-2 font-bold text-fg no-underline hover:bg-surface <?php echo get_the_ID() === $root ? 'bg-tint' : ''; ?>" href="<?php echo esc_url( get_permalink( $root ) ); ?>" <?php echo get_the_ID() === $root ? 'aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $root ) ); ?></a></li>
						<?php foreach ( $section as $p ) : ?>
							<li><a class="flex min-h-11 items-center rounded-lg py-1 pr-2 text-fg no-underline hover:bg-surface <?php echo get_the_ID() === $p->ID ? 'bg-tint font-bold' : ''; ?>" style="padding-left: <?php echo esc_attr( 0.5 + 1 * count( get_post_ancestors( $p->ID ) ) ); ?>rem" href="<?php echo esc_url( get_permalink( $p ) ); ?>" <?php echo get_the_ID() === $p->ID ? 'aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $p ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</aside>
		<?php endif; ?>
	</div>
</article>
		<?php
	endwhile;
endif;

get_footer();
