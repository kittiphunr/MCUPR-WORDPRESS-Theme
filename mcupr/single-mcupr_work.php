<?php
/**
 * A work / award: cover photo, details box (contest, award, year, organiser, team), content, files, share.
 * "More works" lists only works of the same work category – never news.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$i    = mcupr_work_info();
	$cats = get_the_terms( get_the_ID(), 'mcupr_work_cat' );
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="mx-auto w-[min(100%-1.5rem,46rem)] min-[400px]:w-[min(100%-2rem,46rem)]">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 mb-3 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<div class="wk-badges">
				<?php if ( '' !== $i['award'] ) : ?><span class="wk-pill wk-pill-award"><?php echo mcupr_icon( 'trophy', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $i['award'] ); ?></span><?php endif; ?>
				<?php if ( $cats && ! is_wp_error( $cats ) ) : ?><a class="wk-pill wk-pill-cat" href="<?php echo esc_url( get_term_link( $cats[0] ) ); ?>"><?php echo mcupr_icon( 'folder', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $cats[0]->name ); ?></a><?php endif; ?>
				<?php $wk_views = mcupr_views_html(); ?>
				<?php if ( '' !== $wk_views ) : ?><span class="wk-pill wk-pill-views"><?php echo $wk_views; // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php endif; ?>
			</div>
		</div>
	</header>

	<div class="panel-card mx-auto -mt-6 w-[min(100%-1.5rem,48rem)] min-[400px]:w-[min(100%-2rem,48rem)] relative">
		<?php mcupr_missing_translation_notice(); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="noimg:hidden m-0 mb-8"><?php the_post_thumbnail( 'mcupr-hero', array( 'class' => 'w-full rounded-2xl', 'sizes' => '(min-width: 800px) 704px, calc(100vw - 4rem)', 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) ); ?></figure>
		<?php endif; ?>
		<?php
		$wk_row = static function ( $class, $icon, $label_key, $html ) {
			return '<div class="wk-f' . $class . '"><dt><span class="wk-ic" aria-hidden="true">' . mcupr_icon( $icon, 20 ) . '</span>' . esc_html( mcupr_t( $label_key ) ) . '</dt><dd>' . $html . '</dd></div>';
		};
		$wk_team = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) $i['team'] ) ), 'strlen' ) );
		?>
		<dl class="wk-facts">
			<?php
		echo '' !== $i['event'] ? $wk_row( ' wk-f-wide wk-f-hero', 'trophy', 'work_event', esc_html( $i['event'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '' !== $i['award'] ? $wk_row( '', 'cap', 'work_award', esc_html( $i['award'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
		echo $i['year'] ? $wk_row( '', 'calendar', 'work_year', esc_html( mcupr_work_year_text( $i['year'] ) ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '' !== $i['org'] ? $wk_row( ' wk-f-wide', 'bank', 'work_org', esc_html( $i['org'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $wk_team ) {
			echo $wk_row( ' wk-f-wide', 'users', 'work_team', '<ul class="wk-team">' . implode( '', array_map( static function ( $n ) { return '<li>' . esc_html( $n ) . '</li>'; }, $wk_team ) ) . '</ul>' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
			?>
		</dl>
		<?php if ( '' !== $i['url'] ) : ?><p class="ev-actions"><a class="btn" href="<?php echo esc_url( $i['url'] ); ?>" rel="noopener"><?php mcupr_e( 'work_link' ); ?></a></p><?php endif; ?>
		<div id="article-body" class="reader"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php the_content(); ?>
		</div>
		<?php mcupr_attachments(); ?>
		<?php get_template_part( 'template-parts/share' ); ?>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_work' ) ); ?>">‹ <?php mcupr_e( 'works_title' ); ?></a></p>
	</div>
</article>
	<?php
	// More works: the same work category only.
	$mcupr_rel = array();
	if ( $cats && ! is_wp_error( $cats ) ) {
		$mcupr_rel = get_posts(
			array(
				'post_type'      => 'mcupr_work',
				'posts_per_page' => 3,
				'post__not_in'   => array( get_the_ID() ),
				'no_found_rows'  => true,
				'tax_query'      => array( array( 'taxonomy' => 'mcupr_work_cat', 'field' => 'term_id', 'terms' => wp_list_pluck( $cats, 'term_id' ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}
	if ( $mcupr_rel ) :
		?>
	<section class="wrap mt-14" aria-labelledby="more-works">
		<h2 id="more-works" class="mcu-title mb-6 text-2xl font-bold"><?php mcupr_e( 'related_works' ); ?></h2>
		<div class="works-grid works-cols-3">
			<?php foreach ( $mcupr_rel as $r ) { mcupr_work_card( $r, 'h3' ); } ?>
		</div>
	</section>
	<?php endif; ?>
	<?php
endwhile;
mcupr_slot( 'content_after' );
get_footer();
