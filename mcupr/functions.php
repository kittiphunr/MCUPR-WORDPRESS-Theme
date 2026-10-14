<?php
/**
 * MCUPR theme bootstrap.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCUPR_MIN_WP', '5.9' );
define( 'MCUPR_MIN_PHP', '7.0' );

define( 'MCUPR_VERSION', '1.12.0' );
define( 'MCUPR_DIR', get_template_directory() );
define( 'MCUPR_URI', get_template_directory_uri() );

// Refuse to run on unsupported versions (switch back instead of fataling).
require_once MCUPR_DIR . '/inc/compat.php';
require_once MCUPR_DIR . '/inc/admin-th.php';
if ( ! mcupr_is_supported() ) {
	return;
}

require_once MCUPR_DIR . '/inc/i18n.php';
require_once MCUPR_DIR . '/inc/translations.php';
require_once MCUPR_DIR . '/inc/colors.php';
require_once MCUPR_DIR . '/inc/customizer.php';
require_once MCUPR_DIR . '/inc/customizer-extra.php';
require_once MCUPR_DIR . '/inc/layout-meta.php';
require_once MCUPR_DIR . '/inc/dates.php';
require_once MCUPR_DIR . '/inc/template-tags.php';
require_once MCUPR_DIR . '/inc/menus.php';
require_once MCUPR_DIR . '/inc/seo.php';
require_once MCUPR_DIR . '/inc/shortcodes.php';
require_once MCUPR_DIR . '/inc/events.php';
require_once MCUPR_DIR . '/inc/contact.php';
require_once MCUPR_DIR . '/inc/post-tools.php';
require_once MCUPR_DIR . '/inc/works.php';
require_once MCUPR_DIR . '/inc/browser.php';
require_once MCUPR_DIR . '/inc/print.php';
require_once MCUPR_DIR . '/inc/orgchart.php';
require_once MCUPR_DIR . '/inc/people.php';
require_once MCUPR_DIR . '/inc/documents.php';
require_once MCUPR_DIR . '/inc/forms.php';
require_once MCUPR_DIR . '/inc/stats.php';
require_once MCUPR_DIR . '/inc/notice.php';
require_once MCUPR_DIR . '/inc/social.php';
require_once MCUPR_DIR . '/inc/widgets.php';
require_once MCUPR_DIR . '/inc/setup.php';
require_once MCUPR_DIR . '/inc/assets.php';
