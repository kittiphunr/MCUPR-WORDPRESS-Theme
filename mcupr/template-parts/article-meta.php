<?php
/**
 * Category chip, long Thai-government style date, reading time.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$ts  = get_post_timestamp();
$cat = mcupr_first_category();
?>
<div class="print-meta flex flex-wrap items-center gap-x-4 gap-y-1 text-[.95rem] text-muted">
	<?php if ( $cat ) : ?>
		<span class="rounded-full bg-primary px-3 py-0.5 text-sm font-bold text-on-primary"><?php echo esc_html( $cat ); ?></span>
	<?php endif; ?>
	<span class="inline-flex items-center gap-1.5"><?php echo mcupr_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( mcupr_t( 'posted_on' ) ); ?> <?php mcupr_date( null, 'long' ); ?><?php if ( 'th' === mcupr_ui_lang() ) : ?> เวลา <?php echo esc_html( mcupr_time_text( $ts ) ); ?><?php endif; ?></span>
	<span class="inline-flex items-center gap-1.5"><?php echo mcupr_icon( 'clock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( sprintf( mcupr_t( 'reading_time' ), mcupr_reading_minutes() ) ); ?></span>
	<?php echo mcupr_views_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
