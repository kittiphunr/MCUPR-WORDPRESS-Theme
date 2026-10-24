<?php
/**
 * MCUPR theme bootstrap.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

define( 'MCUPR_MIN_WP', '5.9' );
define( 'MCUPR_MIN_PHP', '7.0' );

define( 'MCUPR_VERSION', '1.54.61' );
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
require_once MCUPR_DIR . '/inc/query-diet.php';
require_once MCUPR_DIR . '/inc/menus.php';
require_once MCUPR_DIR . '/inc/seo.php';
require_once MCUPR_DIR . '/inc/shortcodes.php';
require_once MCUPR_DIR . '/inc/events.php';
require_once MCUPR_DIR . '/inc/contact.php';
require_once MCUPR_DIR . '/inc/post-tools.php';
require_once MCUPR_DIR . '/inc/works.php';
require_once MCUPR_DIR . '/inc/browser.php';
require_once MCUPR_DIR . '/inc/print.php';
require_once MCUPR_DIR . '/inc/offices.php';
require_once MCUPR_DIR . '/inc/offices-front.php';
require_once MCUPR_DIR . '/inc/groups.php';
require_once MCUPR_DIR . '/inc/groups-import.php';
require_once MCUPR_DIR . '/inc/groups-front.php';
require_once MCUPR_DIR . '/inc/students.php';
require_once MCUPR_DIR . '/inc/students-portal.php';
require_once MCUPR_DIR . '/inc/students-profile.php';
require_once MCUPR_DIR . '/inc/students-import.php';
require_once MCUPR_DIR . '/inc/students-care.php';
require_once MCUPR_DIR . '/inc/students-mail.php';
require_once MCUPR_DIR . '/inc/students-grades.php';
require_once MCUPR_DIR . '/inc/students-sso.php';
require_once MCUPR_DIR . '/inc/students-card.php';
require_once MCUPR_DIR . '/inc/audience.php';
require_once MCUPR_DIR . '/inc/books.php';
require_once MCUPR_DIR . '/inc/plain-links.php';
require_once MCUPR_DIR . '/inc/orgchart.php';
require_once MCUPR_DIR . '/inc/staff.php';
require_once MCUPR_DIR . '/inc/staff-import.php';
require_once MCUPR_DIR . '/inc/people.php';
require_once MCUPR_DIR . '/inc/forum.php';
require_once MCUPR_DIR . '/inc/forum-front.php';
require_once MCUPR_DIR . '/inc/faq.php';
require_once MCUPR_DIR . '/inc/albums.php';
require_once MCUPR_DIR . '/inc/polls.php';
require_once MCUPR_DIR . '/inc/videos.php';
require_once MCUPR_DIR . '/inc/site-status.php';
require_once MCUPR_DIR . '/inc/toolbar.php';
require_once MCUPR_DIR . '/inc/events-ics.php';
require_once MCUPR_DIR . '/inc/formbuilder.php';
require_once MCUPR_DIR . '/inc/requests.php';
require_once MCUPR_DIR . '/inc/insights.php';
require_once MCUPR_DIR . '/inc/numbers.php';
require_once MCUPR_DIR . '/inc/blocks.php';
require_once MCUPR_DIR . '/inc/speed.php';
require_once MCUPR_DIR . '/inc/starter-settings.php';
require_once MCUPR_DIR . '/inc/demo-home.php';
require_once MCUPR_DIR . '/inc/demo-offices.php';
require_once MCUPR_DIR . '/inc/images.php';
require_once MCUPR_DIR . '/inc/lordicon.php';
require_once MCUPR_DIR . '/inc/feather.php';
require_once MCUPR_DIR . '/inc/control-center.php';
require_once MCUPR_DIR . '/inc/admin-menu.php';
require_once MCUPR_DIR . '/inc/login.php';
require_once MCUPR_DIR . '/inc/social-login.php';
require_once MCUPR_DIR . '/inc/media-folders.php';
require_once MCUPR_DIR . '/inc/file-manager.php';
require_once MCUPR_DIR . '/inc/documents.php';
require_once MCUPR_DIR . '/inc/forms.php';
require_once MCUPR_DIR . '/inc/stats.php';
require_once MCUPR_DIR . '/inc/notice.php';
require_once MCUPR_DIR . '/inc/social.php';
require_once MCUPR_DIR . '/inc/widgets.php';
require_once MCUPR_DIR . '/inc/setup.php';
require_once MCUPR_DIR . '/inc/assets.php';
