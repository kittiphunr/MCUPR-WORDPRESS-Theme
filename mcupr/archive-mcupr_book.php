<?php
/**
 * E-books: /books/ and a bookshelf (/shelf/…) – the shelves (or the grid of covers) with search.
 *
 * @package MCUPR
 */

get_header();
$mcupr_term  = is_tax( MCUPR_SHELF ) ? get_queried_object() : null;
$mcupr_title = $mcupr_term ? $mcupr_term->name : mcupr_t( 'bk_title' );
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( $mcupr_title ), 'description' => $mcupr_term ? term_description( $mcupr_term ) : '', 'chips' => false ) );
?>
<div class="wrap mt-10 pb-4">
	<?php if ( $mcupr_term ) : ?>
		<p class="mb-6"><a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( MCUPR_BOOK ) ); ?>">‹ <?php mcupr_e( 'bk_all_shelves' ); ?></a></p>
	<?php endif; ?>
	<?php echo mcupr_book_shelf( array( 'shelf' => $mcupr_term ? $mcupr_term->slug : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
