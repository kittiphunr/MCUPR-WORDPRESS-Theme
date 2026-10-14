<?php
/**
 * Text size, dark mode and accessibility-panel buttons.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$font_btns = array(
	'dec'   => array( 'A−', 'font_dec' ),
	'reset' => array( 'A', 'font_reset' ),
	'inc'   => array( 'A+', 'font_inc' ),
);
$tool = 'btn-tool !border-on-bar/50 !text-on-bar hover:!bg-white/15 aria-pressed:!bg-white/15 contrast:!border-line';
?>
<div class="flex flex-wrap items-center gap-2" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
	<div class="flex gap-1" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'font_size' ) ); ?>">
		<?php foreach ( $font_btns as $action => $b ) : ?>
			<button type="button" class="<?php echo esc_attr( $tool ); ?> before:content-[attr(data-glyph)]" data-font-action="<?php echo esc_attr( $action ); ?>" data-glyph="<?php echo esc_attr( $b[0] ); ?>" aria-label="<?php echo esc_attr( mcupr_t( $b[1] ) ); ?>" title="<?php echo esc_attr( mcupr_t( $b[1] ) ); ?>"></button>
		<?php endforeach; ?>
	</div>

	<button type="button" class="<?php echo esc_attr( $tool ); ?> group" id="theme-toggle" aria-pressed="false" aria-label="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>">
		<svg class="group-aria-pressed:hidden" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
		<svg class="hidden group-aria-pressed:block" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/></svg>
	</button>

	<button type="button" class="<?php echo esc_attr( $tool ); ?>" id="a11y-toggle" aria-expanded="false" aria-controls="a11y-panel">
		<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm8 6.5-5.5.8v3.2l2 7.5-1.9.5L12.8 15h-1.6l-1.8 5.5-1.9-.5 2-7.5V9.3L4 8.5 4.3 6.6 12 7.8l7.7-1.2z"/></svg>
		<span><?php mcupr_e( 'a11y_btn' ); ?></span>
	</button>
</div>
