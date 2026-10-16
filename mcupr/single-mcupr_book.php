<?php
/**
 * One e-book: the flip-book reader, then the description and the other books of the shelf.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$mcupr_id = get_the_ID();
	$mcupr_au = (string) get_post_meta( $mcupr_id, '_mcupr_bk_author', true );
	$mcupr_yr = (string) get_post_meta( $mcupr_id, '_mcupr_bk_year', true );
	$mcupr_sh = wp_get_object_terms( $mcupr_id, MCUPR_SHELF );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mcu-title mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<?php if ( '' !== $mcupr_au || '' !== $mcupr_yr ) : ?>
				<p class="mt-3 mb-0 text-muted"><?php echo esc_html( trim( ( '' !== $mcupr_au ? sprintf( mcupr_t( 'bk_by' ), $mcupr_au ) : '' ) . ( '' !== $mcupr_yr ? ' · ' . $mcupr_yr : '' ), ' ·' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $mcupr_sh && ! is_wp_error( $mcupr_sh ) ) : ?>
				<ul class="pill-tabs m-0 mt-4 list-none">
					<?php foreach ( $mcupr_sh as $mcupr_t ) : ?>
						<li class="contents"><a href="<?php echo esc_url( get_term_link( $mcupr_t ) ); ?>"><?php echo esc_html( $mcupr_t->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</header>
	<div class="wrap mt-8">
		<?php
		$mcupr_reader = mcupr_book_reader( $mcupr_id, array( 'mode' => 'embed' ) );
		echo '' !== $mcupr_reader ? $mcupr_reader : '<p class="bk-empty">' . esc_html( mcupr_t( 'bk_error' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<div class="mx-auto max-w-4xl">
			<?php if ( '' !== trim( get_the_content() ) ) : ?>
				<div class="reader mt-8"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php the_content(); ?></div>
			<?php endif; ?>
			<?php get_template_part( 'template-parts/share' ); ?>
			<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( MCUPR_BOOK ) ); ?>">‹ <?php mcupr_e( 'bk_title' ); ?></a></p>
		</div>
	</div>
</article>
	<?php
endwhile;
get_footer();
