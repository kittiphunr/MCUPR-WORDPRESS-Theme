<?php
/**
 * Page: tinted band with a wheel watermark and the title, then the content
 * with a "in this section" list of related pages beside it.
 *
 * @package MCUPR
 */

get_header();

	while ( have_posts() ) :
		the_post();
		list( $root, $section ) = mcupr_section_pages( get_the_ID() );
		$has_side               = $section || is_active_sidebar( 'sidebar-page' );
		?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
	$mcupr_chips = '<span class="band-chip">' . mcupr_icon( 'clock', 16 ) . '<span>' . esc_html( mcupr_t( 'updated_on' ) . ' ' . mcupr_date_text( get_post_timestamp( null, 'modified' ) ) ) . '</span></span><span class="band-chip">' . mcupr_icon( 'book', 16 ) . '<span>' . esc_html( sprintf( mcupr_t( 'reading_time' ), mcupr_reading_minutes( get_the_content() ) ) ) . '</span></span>';
	$mcupr_of    = function_exists( 'mcupr_current_office' ) ? mcupr_current_office() : 0;
	if ( $mcupr_of && 'publish' !== get_post_status( $mcupr_of ) && ! current_user_can( 'edit_post', $mcupr_of ) ) {
		$mcupr_of = 0;
	}
	?>
	<?php if ( $mcupr_of ) : ?>
	<div class="wrap of-body mt-8" style="<?php echo esc_attr( mcupr_office_style( $mcupr_of ) ); ?>"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php echo mcupr_office_page_head( $mcupr_of, get_the_title(), has_excerpt() ? get_the_excerpt() : '', $mcupr_chips ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<?php else : ?>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mcu-title mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<div class="band-lead"><?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?></div>
			<?php endif; ?>
			<p class="band-meta no-print"><?php echo $mcupr_chips; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		</div>
	</header>
	<?php endif; ?>

	<div class="wrap <?php echo $mcupr_of ? 'mt-2' : 'mt-10'; ?> grid gap-10 <?php echo $has_side ? 'min-[1000px]:grid-cols-[minmax(0,1fr)_18rem]' : ''; ?>">
		<div class="panel-card min-w-0 <?php echo $has_side ? '' : 'mx-auto w-full max-w-[52rem]'; ?>">
			<?php mcupr_missing_translation_notice(); ?>
			<div class="no-print mb-6"><?php get_template_part( 'template-parts/tools-bar', null, array( 'target' => '#article-body', 'title' => wp_strip_all_tags( get_the_title() ) ) ); ?></div>
			<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		<?php mcupr_attachments(); ?>
			<?php
			// On the section's first page: its sub-pages as tiles (Certify Hub "tile" cards).
			if ( $section && get_the_ID() === $root ) :
				$kids = get_pages( array( 'child_of' => $root, 'parent' => $root, 'sort_column' => 'menu_order,post_title' ) );
				if ( $kids ) :
					?>
					<ul class="not-prose no-print m-0 mt-10 grid list-none gap-4 p-0 min-[600px]:grid-cols-2" aria-label="<?php echo esc_attr( mcupr_t( 'in_section' ) ); ?>">
						<?php foreach ( $kids as $k ) : ?>
							<li class="contents">
								<a class="soft-card lift flex items-center gap-4 p-4 text-fg no-underline" href="<?php echo esc_url( get_permalink( $k ) ); ?>">
									<span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'news', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<span class="min-w-0"><b class="block"><?php echo esc_html( get_the_title( $k ) ); ?></b><span class="block text-sm text-muted"><?php echo esc_html( wp_trim_words( get_the_excerpt( $k ), 14, '…' ) ); ?></span></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
				endif;
			endif;
			?>
			<?php
			if ( comments_open() ) {
				comments_template();
			}
			?>
		</div>

		<?php if ( $has_side ) : ?>
			<aside class="no-print min-[1000px]:sticky min-[1000px]:top-6 min-[1000px]:self-start" <?php echo $section ? 'aria-labelledby="section-title"' : 'aria-label="' . esc_attr( mcupr_t( 'in_section' ) ) . '"'; ?>>
				<?php if ( $section ) : ?>
				<div class="soft-card p-5">
					<h2 id="section-title" class="mcu-title mb-4 text-xl font-bold"><?php mcupr_e( 'in_section' ); ?></h2>
					<ul class="side-tabs">
						<li><a class="font-bold" href="<?php echo esc_url( get_permalink( $root ) ); ?>" <?php echo get_the_ID() === $root ? 'aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $root ) ); ?></a></li>
						<?php foreach ( $section as $sp ) : ?>
							<li><a style="margin-left: <?php echo esc_attr( 0.75 * count( get_post_ancestors( $sp->ID ) ) ); ?>rem" href="<?php echo esc_url( get_permalink( $sp ) ); ?>" <?php echo get_the_ID() === $sp->ID ? 'aria-current="page"' : ''; ?>><?php echo esc_html( get_the_title( $sp ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
				<?php mcupr_widget_area( 'sidebar-page', 'mt-6 space-y-6' ); ?>
			</aside>
		<?php endif; ?>
	</div>
</article>
		<?php
	endwhile;
mcupr_slot( 'content_after' );
get_footer();
