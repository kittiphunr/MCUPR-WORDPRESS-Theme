<?php
/**
 * Header: skip links, utility bar (language / text size / dark mode /
 * accessibility panel / search), branding and main navigation.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$langs = mcupr_languages();
$cur   = mcupr_lang();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<nav class="skip-links" aria-label="<?php echo esc_attr( mcupr_t( 'skip_content' ) ); ?>">
	<a href="#content"><?php mcupr_e( 'skip_content' ); ?></a>
	<a href="#site-nav"><?php mcupr_e( 'skip_nav' ); ?></a>
	<a href="#colophon"><?php mcupr_e( 'skip_footer' ); ?></a>
</nav>

<div id="mcupr-status" class="screen-reader-text" role="status" aria-live="polite"></div>

<header id="masthead" class="site-header">
	<div class="utility">
		<div class="wrap utility__inner">
			<nav class="lang-switch" aria-label="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
				<span class="utility__label" aria-hidden="true"><?php mcupr_e( 'language' ); ?></span>
				<ul>
					<?php foreach ( $langs as $code => $l ) : ?>
						<li>
							<a href="<?php echo mcupr_lang_url( $code ); // phpcs:ignore WordPress.Security.EscapeOutput ?>"
								lang="<?php echo esc_attr( $l['html'] ); ?>"
								hreflang="<?php echo esc_attr( $l['html'] ); ?>"
								<?php echo $code === $cur ? 'aria-current="true"' : ''; ?>>
								<?php echo esc_html( $l['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="tools" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
				<div class="tools__font" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'font_size' ) ); ?>">
					<button type="button" class="btn-tool" data-font-action="dec" aria-label="<?php echo esc_attr( mcupr_t( 'font_dec' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'font_dec' ) ); ?>"><span aria-hidden="true">A−</span></button>
					<button type="button" class="btn-tool" data-font-action="reset" aria-label="<?php echo esc_attr( mcupr_t( 'font_reset' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'font_reset' ) ); ?>"><span aria-hidden="true">A</span></button>
					<button type="button" class="btn-tool" data-font-action="inc" aria-label="<?php echo esc_attr( mcupr_t( 'font_inc' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'font_inc' ) ); ?>"><span aria-hidden="true">A+</span></button>
				</div>

				<button type="button" class="btn-tool" id="theme-toggle" aria-pressed="false" aria-label="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>">
					<svg class="icon icon-moon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
					<svg class="icon icon-sun" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/></svg>
				</button>

				<button type="button" class="btn-tool btn-tool--text" id="a11y-toggle" aria-expanded="false" aria-controls="a11y-panel">
					<svg class="icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm8 6.5-5.5.8v3.2l2 7.5-1.9.5L12.8 15h-1.6l-1.8 5.5-1.9-.5 2-7.5V9.3L4 8.5 4.3 6.6 12 7.8l7.7-1.2z"/></svg>
					<span><?php mcupr_e( 'a11y_btn' ); ?></span>
				</button>
			</div>

			<?php get_search_form(); ?>
		</div>

		<?php get_template_part( 'template-parts/a11y-panel' ); ?>
	</div>

	<div class="brand">
		<div class="wrap brand__inner">
			<a class="brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php if ( has_custom_logo() ) : ?>
					<?php
					$logo_id = get_theme_mod( 'custom_logo' );
					echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'brand__logo', 'alt' => '' ) );
					?>
				<?php endif; ?>
				<span class="brand__name"><?php echo esc_html( mcupr_org_name() ); ?></span>
			</a>

			<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav">
				<span class="nav-toggle__bars" aria-hidden="true"></span>
				<span><?php mcupr_e( 'menu' ); ?></span>
			</button>
		</div>
	</div>

	<nav id="site-nav" class="site-nav" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
		<div class="wrap"><?php mcupr_primary_menu(); ?></div>
	</nav>
</header>

<main id="content" class="site-main" tabindex="-1">
<?php mcupr_breadcrumb(); ?>
