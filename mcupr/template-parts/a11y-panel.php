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
?>
<section id="a11y-panel" class="a11y-panel" aria-labelledby="a11y-panel-title" hidden>
	<div class="wrap">
		<div class="a11y-panel__head">
			<h2 id="a11y-panel-title"><?php mcupr_e( 'a11y_title' ); ?></h2>
			<button type="button" class="btn-tool btn-tool--text" data-close-panel><?php mcupr_e( 'close' ); ?></button>
		</div>

		<div class="a11y-panel__grid">
			<?php foreach ( $radio_groups as $pref => $group ) : ?>
				<fieldset>
					<legend><?php mcupr_e( $group['label'] ); ?></legend>
					<?php foreach ( $group['options'] as $value => $label ) : ?>
						<label class="choice">
							<input type="radio" name="mcupr-<?php echo esc_attr( $pref ); ?>" data-pref="<?php echo esc_attr( $pref ); ?>" value="<?php echo esc_attr( $value ); ?>">
							<span><?php mcupr_e( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<fieldset>
				<legend><?php mcupr_e( 'a11y_btn' ); ?></legend>
				<?php foreach ( $checks as $pref => $label ) : ?>
					<label class="choice">
						<input type="checkbox" data-pref="<?php echo esc_attr( $pref ); ?>" data-on="<?php echo 'images' === $pref ? 'off' : '1'; ?>">
						<span><?php mcupr_e( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</div>

		<p><button type="button" class="btn" data-reset-prefs><?php mcupr_e( 'reset_all' ); ?></button></p>
	</div>
</section>
