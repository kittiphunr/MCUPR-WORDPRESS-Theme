<?php
/**
 * Search results: the form with the choice of what to search in, how many results, and the results as cards that say what
 * kind of page they are. An empty search lists nothing (inc/search-page.php) and asks for a word.
 *
 * @package MCUPR
 */

get_header();

$mcupr_w = mcupr_search_words();
global $wp_query;

get_template_part(
	'template-parts/band',
	null,
	array(
		'title'       => '' === $mcupr_w ? esc_html( mcupr_t( 'search_title' ) ) : esc_html( mcupr_t( 'search_for' ) ) . ': <span class=\'text-link\'>' . esc_html( $mcupr_w ) . '</span>',
		'description' => '',
		'chips'       => false,
	)
);
?>
<div class="wrap mt-8">
	<div class="sp-top"><?php mcupr_search_page_form(); ?></div>
	<?php if ( '' === $mcupr_w ) : ?>
		<p class="sp-status" role="status"><?php mcupr_e( 'sp_hint' ); ?></p>
		<?php echo mcupr_search_popular_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php elseif ( have_posts() ) : ?>
		<p class="sp-status" role="status"><?php echo esc_html( sprintf( mcupr_t( 'live_count' ), number_format_i18n( (int) $wp_query->found_posts ) ) ); ?></p>
		<div class="grid grid-cols-[repeat(auto-fill,minmax(min(17rem,100%),1fr))] gap-6" data-reveal-group>
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', null, array( 'heading' => 'h2', 'variant' => '', 'kind' => mcupr_live_kind( get_post() ) ) );
			endwhile;
			?>
		</div>
		<?php mcupr_pagination(); ?>
	<?php else : ?>
		<p class="sp-status" role="status"><?php echo esc_html( sprintf( mcupr_t( 'live_none' ), $mcupr_w ) ); ?></p>
		<?php mcupr_state_card( 'search', mcupr_t( 'no_results' ), mcupr_t( 'no_results_tip' ), '', 'h2' ); ?>
		<?php echo mcupr_search_popular_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php endif; ?>
</div>
<?php
get_footer();
