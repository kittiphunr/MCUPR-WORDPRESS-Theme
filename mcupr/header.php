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
<body <?php body_class( 'bg-bg text-fg' ); ?>>
<?php wp_body_open(); ?>

<nav aria-label="<?php echo esc_attr( mcupr_t( 'skip_content' ) ); ?>">
	<?php
	$skip = 'absolute left-2 -top-16 z-[1000] rounded-lg bg-primary px-4 py-3 font-bold text-on-primary focus:top-2';
	?>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#content"><?php mcupr_e( 'skip_content' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#site-nav"><?php mcupr_e( 'skip_nav' ); ?></a>
	<a class="<?php echo esc_attr( $skip ); ?>" href="#colophon"><?php mcupr_e( 'skip_footer' ); ?></a>
</nav>

<header id="masthead" class="border-b border-line bg-bg print:hidden">
	<div class="border-b border-line bg-surface text-[.9375rem]">
		<div class="wrap flex flex-wrap items-center justify-between gap-x-5 gap-y-2 py-2">
			<nav class="flex items-center gap-2" aria-label="<?php echo esc_attr( mcupr_t( 'language' ) ); ?>">
				<span aria-hidden="true"><?php mcupr_e( 'language' ); ?></span>
				<ul class="m-0 flex list-none flex-wrap gap-1 p-0">
					<?php foreach ( $langs as $code => $l ) : ?>
						<li>
							<a class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border-2 border-transparent px-3 text-fg no-underline hover:border-line aria-[current]:bg-primary aria-[current]:font-bold aria-[current]:text-on-primary contrast:border-line"
								href="<?php echo mcupr_lang_url( $code ); // phpcs:ignore WordPress.Security.EscapeOutput ?>"
								lang="<?php echo esc_attr( $l['html'] ); ?>"
								hreflang="<?php echo esc_attr( $l['html'] ); ?>"
								<?php echo $code === $cur ? 'aria-current="true"' : ''; ?>>
								<?php echo esc_html( $l['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>

			<div class="flex flex-wrap items-center gap-2" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'utility_nav' ) ); ?>">
				<div class="flex gap-1" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'font_size' ) ); ?>">
					<?php
					$font_btns = array(
						'dec'   => array( 'A−', 'font_dec' ),
						'reset' => array( 'A', 'font_reset' ),
						'inc'   => array( 'A+', 'font_inc' ),
					);
					foreach ( $font_btns as $action => $b ) :
						?>
						<button type="button" class="btn-tool before:content-[attr(data-glyph)]" data-font-action="<?php echo esc_attr( $action ); ?>" data-glyph="<?php echo esc_attr( $b[0] ); ?>" aria-label="<?php echo esc_attr( mcupr_t( $b[1] ) ); ?>" title="<?php echo esc_attr( mcupr_t( $b[1] ) ); ?>"></button>
					<?php endforeach; ?>
				</div>

				<button type="button" class="btn-tool group" id="theme-toggle" aria-pressed="false" aria-label="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>" title="<?php echo esc_attr( mcupr_t( 'theme_toggle' ) ); ?>">
					<svg class="group-aria-pressed:hidden" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
					<svg class="hidden group-aria-pressed:block" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/></svg>
				</button>

				<button type="button" class="btn-tool" id="a11y-toggle" aria-expanded="false" aria-controls="a11y-panel">
					<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm8 6.5-5.5.8v3.2l2 7.5-1.9.5L12.8 15h-1.6l-1.8 5.5-1.9-.5 2-7.5V9.3L4 8.5 4.3 6.6 12 7.8l7.7-1.2z"/></svg>
					<span><?php mcupr_e( 'a11y_btn' ); ?></span>
				</button>
			</div>

			<?php get_search_form(); ?>
		</div>

		<?php get_template_part( 'template-parts/a11y-panel' ); ?>
	</div>

	<div class="wrap flex items-center justify-between gap-4 py-4">
		<a class="flex items-center gap-4 text-fg no-underline" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			if ( has_custom_logo() ) {
				echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'class' => 'h-[4.5rem] w-auto', 'alt' => '' ) );
			}
			?>
			<span class="text-xl font-bold leading-tight min-[900px]:text-2xl"><?php echo esc_html( mcupr_org_name() ); ?></span>
		</a>

		<button type="button" class="nav-toggle btn min-[900px]:hidden" aria-expanded="false" aria-controls="site-nav">
			<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
			<span><?php mcupr_e( 'menu' ); ?></span>
		</button>
	</div>

	<nav id="site-nav" class="site-nav hidden bg-primary text-on-primary min-[900px]:block contrast:border-y-2 contrast:border-line [&.is-open]:block" aria-label="<?php echo esc_attr( mcupr_t( 'main_nav' ) ); ?>">
		<div class="wrap"><?php mcupr_primary_menu(); ?></div>
	</nav>
</header>

<main id="content" class="min-h-[50vh] pb-12" tabindex="-1">
<?php mcupr_breadcrumb(); ?>
