<?php
/**
 * Accessibility settings panel (disclosure widget, no focus trap).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

$checks = array(
	'gray'      => 'grayscale',
	'underline' => 'underline',
	'readable'  => 'readable_font',
	'spacing'   => 'text_spacing',
	'cursor'    => 'big_cursor',
	'motion'    => 'reduce_motion',
	'images'    => 'hide_images',
	'tv'        => 'tv_mode',
);
$radio_groups = array(
	'theme'    => array(
		'label'   => 'theme',
		'options' => array( 'auto' => 'theme_auto', 'light' => 'theme_light', 'dark' => 'theme_dark' ),
	),
	'contrast' => array(
		'label'   => 'contrast',
		'options' => array( '' => 'contrast_def', 'wb' => 'contrast_wb', 'yb' => 'contrast_yb', 'by' => 'contrast_by' ),
	),
);
$fieldset = 'm-0 rounded-lg border border-line px-4 py-3';
$legend   = 'px-1.5 font-bold';
$choice   = 'flex min-h-11 cursor-pointer items-center gap-2.5';
$input    = 'size-[1.4rem] accent-primary';
?>
<section id="a11y-panel" class="border-t border-line bg-bg py-4 text-fg" aria-labelledby="a11y-panel-title" hidden>
	<div class="wrap">
		<div class="flex items-center justify-between gap-4">
			<h2 id="a11y-panel-title" class="m-0 text-xl font-bold"><?php mcupr_e( 'a11y_title' ); ?></h2>
			<button type="button" class="btn-tool" data-close-panel><?php mcupr_e( 'close' ); ?></button>
		</div>

		<div class="my-4 grid grid-cols-[repeat(auto-fit,minmax(min(14rem,100%),1fr))] gap-4">
			<?php foreach ( $radio_groups as $pref => $group ) : ?>
				<fieldset class="<?php echo esc_attr( $fieldset ); ?>">
					<legend class="<?php echo esc_attr( $legend ); ?>"><?php mcupr_e( $group['label'] ); ?></legend>
					<?php foreach ( $group['options'] as $value => $label ) : ?>
						<label class="<?php echo esc_attr( $choice ); ?>">
							<input class="<?php echo esc_attr( $input ); ?>" type="radio" name="mcupr-<?php echo esc_attr( $pref ); ?>" data-pref="<?php echo esc_attr( $pref ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<span><?php mcupr_e( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<fieldset class="<?php echo esc_attr( $fieldset ); ?>">
				<legend class="<?php echo esc_attr( $legend ); ?>"><?php mcupr_e( 'a11y_btn' ); ?></legend>
				<?php foreach ( $checks as $pref => $label ) : ?>
					<label class="<?php echo esc_attr( $choice ); ?>">
						<input class="<?php echo esc_attr( $input ); ?>" type="checkbox" data-pref="<?php echo esc_attr( $pref ); ?>" data-on="<?php echo 'images' === $pref ? 'off' : '1'; ?>">
						<span><?php mcupr_e( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</div>

		<p class="m-0"><button type="button" class="btn" data-reset-prefs><?php mcupr_e( 'reset_all' ); ?></button></p>
	</div>
</section>
