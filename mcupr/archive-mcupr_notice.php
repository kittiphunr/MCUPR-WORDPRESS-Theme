<?php
/**
 * Notices and documents: /notices/ and a notice type (/notice-category/…) – the types on the side, a search box, and a
 * table of the notices (number, details with file links, date, downloads). On a phone the table becomes stacked cards.
 *
 * @package MCUPR
 */

get_header();
$mcupr_cur   = is_tax( 'mcupr_notice_cat' ) ? get_queried_object_id() : 0;
$mcupr_base  = get_post_type_archive_link( 'mcupr_notice' );
$mcupr_here  = $mcupr_cur ? get_term_link( $mcupr_cur, 'mcupr_notice_cat' ) : $mcupr_base;
$mcupr_title = $mcupr_cur ? single_term_title( '', false ) : mcupr_t( 'nt_title' );
$mcupr_nq    = mcupr_notice_search_words();
$mcupr_terms = mcupr_notice_terms();
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( $mcupr_title ), 'description' => '', 'chips' => false ) );
global $wp_query;
$mcupr_found = (int) $wp_query->found_posts;
$mcupr_per   = mcupr_notices_per_page();
$mcupr_first = max( 0, ( max( 1, (int) get_query_var( 'paged' ) ) - 1 ) * $mcupr_per ) + 1;
$mcupr_walk  = static function ( $parent ) use ( &$mcupr_walk, $mcupr_terms, $mcupr_cur ) {
	$o = '';
	foreach ( $mcupr_terms as $t ) {
		if ( (int) $t->parent !== (int) $parent ) {
			continue;
		}
		$o .= '<li><a href="' . esc_url( get_term_link( $t ) ) . '"' . ( (int) $t->term_id === (int) $mcupr_cur ? ' aria-current="page"' : '' ) . '><span>' . esc_html( $t->name ) . '</span><span class="nt-n">' . esc_html( number_format_i18n( $t->count ) ) . '</span></a>';
		$kids = $mcupr_walk( $t->term_id );
		$o   .= '' !== $kids ? '<ul>' . $kids . '</ul>' : '';
		$o   .= '</li>';
	}
	return $o;
};
?>
<div class="wrap mt-10 pb-4">
	<div class="nt-layout">
		<aside class="nt-side" aria-label="<?php echo esc_attr( mcupr_t( 'nt_cats' ) ); ?>">
			<h2 class="nt-side-t"><?php mcupr_e( 'nt_cats' ); ?></h2>
			<ul class="nt-cats">
				<li><a href="<?php echo esc_url( $mcupr_base ); ?>"<?php echo $mcupr_cur ? '' : ' aria-current="page"'; ?>><span><?php mcupr_e( 'nt_all' ); ?></span><span class="nt-n"><?php echo esc_html( number_format_i18n( mcupr_notice_total() ) ); ?></span></a></li>
				<?php echo $mcupr_walk( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</ul>
		</aside>
		<div class="nt-main">
			<form class="nt-search" role="search" method="get" action="<?php echo esc_url( $mcupr_here ); ?>">
				<label class="sr-only" for="nt-q"><?php mcupr_e( 'nt_search' ); ?></label>
				<input id="nt-q" type="search" name="nq" value="<?php echo esc_attr( $mcupr_nq ); ?>" maxlength="80" placeholder="<?php echo esc_attr( mcupr_t( 'nt_search_ph' ) ); ?>" autocomplete="off">
				<button type="submit"><?php echo mcupr_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="sr-only"><?php mcupr_e( 'nt_search' ); ?></span></button>
			</form>
			<p class="nt-found" role="status"><?php echo esc_html( sprintf( mcupr_t( 'nt_found' ), number_format_i18n( $mcupr_found ) ) ); ?><?php if ( '' !== $mcupr_nq ) : ?> · <a href="<?php echo esc_url( $mcupr_here ); ?>"><?php mcupr_e( 'nt_clear' ); ?></a><?php endif; ?></p>
			<?php if ( have_posts() ) : ?>
				<?php echo mcupr_notice_table( $wp_query->posts, $mcupr_first, $mcupr_title ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php mcupr_pagination(); ?>
			<?php else : ?>
				<?php mcupr_state_card( 'bell', '' !== $mcupr_nq ? mcupr_t( 'nt_none_search' ) : mcupr_t( 'nt_none' ), '', '', 'h2' ); ?>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php
get_footer();
