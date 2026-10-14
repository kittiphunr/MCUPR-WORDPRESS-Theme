<?php
/**
 * Header: skip links, utility bar (always), then either an Elementor Pro
 * "Header" template or the default Tailwind header.
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
<body <?php body_class( 'bg-bg text-fg' ); ?>>
<?php wp_body_open(); ?>

<nav aria-label="<?php echo esc_attr( mcupr_t( 'skip_content' ) ); ?>">
	<?php $skip = 'absolute left-2 -top-16 z-[1000] rounded-lg bg-primary px-4 py-3 font-bold text-on-primary focus:top-2'; ?>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#content"><?php mcupr_e( 'skip_content' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#site-nav" data-skip-nav><?php mcupr_e( 'skip_nav' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#colophon"><?php mcupr_e( 'skip_footer' ); ?></a>
</nav>

<?php
if ( get_theme_mod( 'mcupr_utility_bar', true ) ) {
	get_template_part( 'template-parts/utility-bar' );
}

if ( ! mcupr_elementor_location( 'header' ) ) {
	get_template_part( 'template-parts/site-header' );
}
?>

<main id="content" tabindex="-1">
