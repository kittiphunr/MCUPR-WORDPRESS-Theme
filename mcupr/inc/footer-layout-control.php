<?php
/**
 * The Customizer control of the footer board: only a place for the script to draw in, and the setting's value in a hidden field.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

class MCUPR_Footer_Layout_Control extends WP_Customize_Control {
	public $type = 'mcupr_footer_layout';

	protected function render_content() {
		if ( ! empty( $this->label ) ) {
			echo '<span class="customize-control-title">' . esc_html( $this->label ) . '</span>';
		}
		if ( ! empty( $this->description ) ) {
			echo '<span class="description customize-control-description">' . esc_html( $this->description ) . '</span>';
		}
		echo '<div class="mcupr-fl" data-mcupr-fl></div><input type="hidden" class="mcupr-fl-value" value="' . esc_attr( $this->value() ) . '" ' . $this->get_link() . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
