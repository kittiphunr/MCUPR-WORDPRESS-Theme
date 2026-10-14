<?php
/**
 * Header: skip links, optional utility links, then the site header (with the tool buttons).
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
<body <?php body_class( "bg-canvas text-fg" ); ?>>
<?php wp_body_open(); ?>

<div id="progress" class="fixed inset-x-0 top-0 z-[60] h-1 origin-left scale-x-0 bg-primary print:hidden" aria-hidden="true"></div>

<nav aria-label="<?php echo esc_attr( mcupr_t( 'skip_content' ) ); ?>">
	<?php $skip = 'absolute left-2 -top-16 z-[1000] rounded-lg bg-primary px-4 py-3 font-bold text-on-primary focus:top-2'; ?>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#content" data-skip><?php mcupr_e( 'skip_content' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#site-nav" data-skip data-skip-nav><?php mcupr_e( 'skip_nav' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#colophon" data-skip><?php mcupr_e( 'skip_footer' ); ?></a>
</nav>

<div id="page" class="site-page">
<?php mcupr_notice_bar(); ?>
<?php if ( has_nav_menu( 'utility' ) ) : ?>
	<div class="bg-bar text-[.8125rem] text-on-bar print:hidden">
		<div class="wrap">
			<?php
			wp_nav_menu(
				array(
					'theme_location'       => 'utility',
					'container'            => 'nav',
					'container_aria_label' => mcupr_t( 'utility_links' ),
					'menu_class'           => 'utility-menu',
					'depth'                => 1,
				)
			);
			?>
		</div>
	</div>
<?php endif; ?>

<?php get_template_part( 'template-parts/site-header' ); ?>

<main id="content" tabindex="-1">
<?php mcupr_slot( 'header_after' ); ?>
