<?php
/**
 * A group heading in the Customizer: a title and one line of help, no setting.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Customize_Control' ) && ! class_exists( 'MCUPR_Customize_Heading' ) ) {
	class MCUPR_Customize_Heading extends WP_Customize_Control {
		/** @var string */
		public $type = 'mcupr_heading';

		/** Title + help (the base class would print a label and a description box of its own). */
		protected function render_content() {
			echo '<div class="mcupr-cz-head"><span class="mcupr-cz-head-t">' . esc_html( $this->label ) . '</span>';
			if ( '' !== (string) $this->description ) {
				echo '<span class="mcupr-cz-head-d">' . esc_html( $this->description ) . '</span>';
			}
			echo '</div>';
		}
	}
}
