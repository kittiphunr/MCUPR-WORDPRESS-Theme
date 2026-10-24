<?php
/**
 * Template Name: Elementor Canvas (theme)
 *
 * The "Canvas" template of Elementor without the plugin: the page alone, nothing of the theme around it.
 * Used by inc/elementor.php for the pages that were saved with elementor_canvas.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'elementor-template-canvas' ); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
?>
<?php wp_footer(); ?>
</body>
</html>
