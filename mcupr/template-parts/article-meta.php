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
<div class="print-meta pm flex flex-wrap items-center gap-2 text-[.95rem] text-muted">
	<?php if ( $cat ) : ?>
		<span class="pm-cat rounded-full bg-primary px-3.5 py-1 text-sm font-bold text-on-primary"><?php echo esc_html( $cat ); ?></span>
	<?php endif; ?>
	<span class="pm-chip inline-flex items-center gap-1.5"><?php echo mcupr_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( mcupr_t( 'posted_on' ) ); ?> <?php mcupr_date( null, 'long' ); ?><?php if ( 'th' === mcupr_ui_lang() ) : ?> เวลา <?php echo esc_html( str_replace( ' ', "\xc2\xa0", mcupr_time_text( $ts ) ) ); // a no-break space: "07.44 น." stays together ?><?php endif; ?></span></span>
	<span class="pm-chip inline-flex items-center gap-1.5"><?php echo mcupr_icon( 'clock', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( sprintf( mcupr_t( 'reading_time' ), mcupr_reading_minutes() ) ); ?></span>
	<?php echo mcupr_views_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
