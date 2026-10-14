<?php
/**
 * Previous / next post as two cards.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$prev = get_previous_post();
$next = get_next_post();
if ( ! $prev && ! $next ) {
	return;
}
$card = 'flex min-h-20 flex-col justify-center rounded-2xl border border-line p-4 text-fg no-underline hover:border-primary';
?>
<nav class="no-print mt-10 grid gap-4 min-[600px]:grid-cols-2" aria-label="<?php echo esc_attr( mcupr_t( 'pagination' ) ); ?>">
	<?php if ( $prev ) : ?>
		<a class="<?php echo esc_attr( $card ); ?>" href="<?php echo esc_url( get_permalink( $prev ) ); ?>" rel="prev"><span class="text-sm text-muted">‹ <?php mcupr_e( 'prev' ); ?></span><span class="font-semibold"><?php echo esc_html( get_the_title( $prev ) ); ?></span></a>
	<?php else : ?>
		<span></span>
	<?php endif; ?>
	<?php if ( $next ) : ?>
		<a class="<?php echo esc_attr( $card ); ?> text-right" href="<?php echo esc_url( get_permalink( $next ) ); ?>" rel="next"><span class="text-sm text-muted"><?php mcupr_e( 'next' ); ?> ›</span><span class="font-semibold"><?php echo esc_html( get_the_title( $next ) ); ?></span></a>
	<?php endif; ?>
</nav>
