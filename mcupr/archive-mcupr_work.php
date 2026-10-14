<?php
/**
 * Works & awards: /works/ and the work categories (/work-category/…).
 * Filters: category chips (own categories, never the news categories) and a year filter.
 *
 * @package MCUPR
 */

get_header();

$mcupr_cur   = is_tax( 'mcupr_work_cat' ) ? get_queried_object_id() : 0;
$mcupr_year  = mcupr_work_year_filter();
$mcupr_base  = get_post_type_archive_link( 'mcupr_work' );
$mcupr_title = is_tax( 'mcupr_work_cat' ) ? single_term_title( '', false ) : mcupr_t( 'works_title' );
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( $mcupr_title ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap mt-10 pb-4">
	<div class="works-filters">
		<nav aria-label="<?php echo esc_attr( mcupr_t( 'work_cats' ) ); ?>">
			<ul class="pill-tabs m-0 list-none">
				<li class="contents"><a href="<?php echo esc_url( $mcupr_base ); ?>" <?php echo $mcupr_cur ? '' : 'aria-current="page"'; ?>><?php mcupr_e( 'all_cats' ); ?></a></li>
				<?php foreach ( get_terms( array( 'taxonomy' => 'mcupr_work_cat', 'hide_empty' => true ) ) as $t ) : ?>
					<li class="contents"><a href="<?php echo esc_url( get_term_link( $t ) ); ?>" <?php echo (int) $t->term_id === $mcupr_cur ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $t->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php $mcupr_years = mcupr_work_years(); ?>
		<?php if ( count( $mcupr_years ) > 1 ) : ?>
			<?php $mcupr_ybase = $mcupr_cur ? get_term_link( $mcupr_cur, 'mcupr_work_cat' ) : $mcupr_base; ?>
			<nav aria-label="<?php echo esc_attr( mcupr_t( 'work_year' ) ); ?>">
				<ul class="pill-tabs m-0 list-none">
					<li class="contents"><a href="<?php echo esc_url( $mcupr_ybase ); ?>" <?php echo $mcupr_year ? '' : 'aria-current="true"'; ?>><?php mcupr_e( 'all_years' ); ?></a></li>
					<?php foreach ( $mcupr_years as $y ) : ?>
						<li class="contents"><a href="<?php echo esc_url( add_query_arg( 'wy', $y, $mcupr_ybase ) ); ?>" <?php echo $y === $mcupr_year ? 'aria-current="true"' : ''; ?>><?php echo esc_html( mcupr_work_year_text( $y ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
	<?php if ( have_posts() ) : ?>
		<div class="works-grid works-cols-3" data-reveal-group>
			<?php
			while ( have_posts() ) :
				the_post();
				mcupr_work_card( null, 'h2' );
			endwhile;
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<?php mcupr_state_card( 'trophy', mcupr_t( 'no_works' ), '', '', 'h2' ); ?>
	<?php endif; ?>
</div>
<?php
mcupr_slot( 'content_after' );
get_footer();
