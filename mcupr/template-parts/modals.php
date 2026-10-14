<?php
/**
 * Content of the dialogs opened by the header buttons. Kept in <template>
 * elements (inert) and shown with SweetAlert2 by assets/js/main.js:
 *   #tpl-a11y  – accessibility helper (text size, contrast, display mode, extras)
 *   #tpl-lang  – language
 *   #tpl-search – full-screen search with recent / popular searches
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

$pill   = 'inline-flex min-h-11 cursor-pointer items-center rounded-full border-2 border-control/70 px-4 py-1 font-semibold peer-checked:border-primary peer-checked:bg-primary peer-checked:text-on-primary peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus';
$row    = 'flex flex-wrap items-center justify-between gap-3 border-b border-line py-4';
$levels = array( 0 => '87.5%', 1 => '100%', 2 => '125%', 3 => '150%', 4 => '200%' );
$sizes  = array( 0 => 'text-sm', 1 => 'text-base', 2 => 'text-xl', 3 => 'text-2xl', 4 => 'text-3xl' );
$checks = array(
	'gray'      => 'grayscale',
	'underline' => 'underline',
	'readable'  => 'readable_font',
	'spacing'   => 'text_spacing',
	'cursor'    => 'big_cursor',
	'pattern'   => 'hide_pattern',
	'images'    => 'hide_images',
	'tv'        => 'tv_mode',
);
?>
<template id="tpl-a11y">
	<div class="text-left text-fg" data-a11y-dialog>
		<fieldset class="<?php echo esc_attr( $row ); ?> m-0 border-x-0 border-t-0 px-0">
			<legend class="float-left mr-3 p-0 text-lg font-semibold"><?php mcupr_e( 'menu_layout' ); ?></legend>
			<div class="seg" role="radiogroup">
				<?php foreach ( array( 'top' => 'menu_top', 'side' => 'menu_side' ) as $v => $label ) : ?>
					<label class="relative"><input class="peer sr-only" type="radio" name="mcupr-menu" data-pref="menu" value="<?php echo esc_attr( $v ); ?>"><span class="seg-b peer-checked:bg-primary peer-checked:text-on-primary peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus"><?php mcupr_e( $label ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="<?php echo esc_attr( $row ); ?> !block">
			<span id="sc-label" class="mb-3 block text-lg font-semibold"><?php mcupr_e( 'site_colors' ); ?></span>
			<div class="flex flex-wrap items-center gap-2.5" role="radiogroup" aria-labelledby="sc-label" data-color-group>
				<?php foreach ( mcupr_palettes() as $key => $pal ) : ?>
					<button type="button" role="radio" aria-checked="false" data-color-preset="<?php echo esc_attr( $key ); ?>" title="<?php echo esc_attr( $pal['label'] ); ?>" aria-label="<?php echo esc_attr( $pal['label'] ); ?>"
						class="size-11 cursor-pointer rounded-full border-4 border-bg p-0 shadow-[0_0_0_2px_var(--control)] aria-checked:shadow-[0_0_0_3px_var(--text)]" style="background:linear-gradient(135deg,<?php echo esc_attr( $pal['primary'] ); ?> 55%,<?php echo esc_attr( $pal['accent'] ); ?> 55%)"></button>
				<?php endforeach; ?>
				<label class="ml-1 inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-control/60 px-3 text-sm font-semibold">
					<input type="color" class="size-7 cursor-pointer rounded-full border-0 bg-transparent p-0" data-color-custom value="#4f46e5" aria-label="<?php echo esc_attr( mcupr_t( 'custom_color' ) ); ?>">
					<?php mcupr_e( 'custom_color' ); ?>
				</label>
			</div>
			<p class="mt-3 mb-0 text-sm text-muted"><?php mcupr_e( 'color_note' ); ?></p>
		</div>

		<div class="<?php echo esc_attr( $row ); ?>">
			<span id="fs-label" class="text-lg font-semibold"><?php mcupr_e( 'font_size' ); ?></span>
			<div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-labelledby="fs-label">
				<?php foreach ( $levels as $n => $pct ) : ?>
					<button type="button" role="radio" aria-checked="false" data-font-level="<?php echo (int) $n; ?>" aria-label="<?php echo esc_attr( sprintf( mcupr_t( 'font_n' ), $pct ) ); ?>" title="<?php echo esc_attr( $pct ); ?>"
						class="inline-flex size-14 cursor-pointer items-center justify-center rounded-full border-0 bg-surface font-bold text-fg aria-checked:bg-primary aria-checked:text-on-primary contrast:border-2 contrast:border-line">
						<span aria-hidden="true" class="<?php echo esc_attr( $sizes[ $n ] ); ?>">A</span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="<?php echo esc_attr( $row ); ?>">
			<span id="hc-label" class="text-lg font-semibold"><?php mcupr_e( 'high_contrast' ); ?></span>
			<div class="flex items-center gap-3">
				<span data-contrast-state class="text-fg"><?php mcupr_e( 'off' ); ?></span>
				<button type="button" role="switch" aria-checked="false" aria-labelledby="hc-label" data-contrast-switch
					class="relative h-9 w-16 cursor-pointer rounded-full border-2 border-line bg-line p-0 transition-colors aria-checked:border-primary aria-checked:bg-primary">
					<span class="absolute top-0.5 left-0.5 size-7 rounded-full bg-bg shadow transition-transform [[aria-checked=true]>&]:translate-x-7" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<fieldset class="m-0 hidden gap-2 border-0 p-0 py-4 [&:not([hidden])]:flex [&:not([hidden])]:flex-wrap" data-contrast-variants hidden>
			<legend class="sr-only"><?php mcupr_e( 'contrast' ); ?></legend>
			<?php foreach ( array( 'wb' => 'contrast_wb', 'yb' => 'contrast_yb', 'by' => 'contrast_by' ) as $v => $label ) : ?>
				<label class="relative"><input class="peer sr-only" type="radio" name="mcupr-contrast" data-pref="contrast" value="<?php echo esc_attr( $v ); ?>"><span class="<?php echo esc_attr( $pill ); ?>"><?php mcupr_e( $label ); ?></span></label>
			<?php endforeach; ?>
		</fieldset>

		<div class="<?php echo esc_attr( $row ); ?>">
			<span id="an-label" class="text-lg font-semibold"><?php mcupr_e( 'animations' ); ?></span>
			<div class="flex items-center gap-3">
				<span data-motion-state class="text-fg"><?php mcupr_e( 'on' ); ?></span>
				<button type="button" role="switch" aria-checked="true" aria-labelledby="an-label" data-motion-switch
					class="relative h-9 w-16 cursor-pointer rounded-full border-2 border-line bg-line p-0 transition-colors aria-checked:border-primary aria-checked:bg-primary">
					<span class="absolute top-0.5 left-0.5 size-7 rounded-full bg-bg shadow transition-transform [[aria-checked=true]>&]:translate-x-7" aria-hidden="true"></span>
				</button>
			</div>
		</div>

		<fieldset class="<?php echo esc_attr( $row ); ?> m-0 border-x-0 border-t-0 px-0">
			<legend class="float-left mr-3 p-0 text-lg font-semibold"><?php mcupr_e( 'theme' ); ?></legend>
			<div class="flex flex-wrap gap-2">
				<?php foreach ( array( 'auto' => 'theme_auto', 'light' => 'theme_light', 'dark' => 'theme_dark' ) as $v => $label ) : ?>
					<label class="relative"><input class="peer sr-only" type="radio" name="mcupr-theme" data-pref="theme" value="<?php echo esc_attr( $v ); ?>"><span class="<?php echo esc_attr( $pill ); ?>"><?php mcupr_e( $label ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<details class="border-b border-line py-3">
			<summary class="flex min-h-11 cursor-pointer items-center text-lg font-semibold"><?php mcupr_e( 'more_options' ); ?></summary>
			<div class="grid gap-x-6 min-[480px]:grid-cols-2">
				<?php foreach ( $checks as $pref => $label ) : ?>
					<label class="flex min-h-11 cursor-pointer items-center gap-2.5">
						<input class="size-5 accent-primary" type="checkbox" data-pref="<?php echo esc_attr( $pref ); ?>" data-on="<?php echo in_array( $pref, array( 'images', 'pattern' ), true ) ? 'off' : '1'; ?>">
						<span><?php mcupr_e( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</details>

		<div class="flex flex-wrap items-center justify-between gap-3 pt-4">
			<p class="m-0 text-sm text-muted" role="status" data-a11y-status></p>
			<button type="button" class="min-h-11 cursor-pointer border-0 bg-transparent p-0 text-link underline" data-reset-prefs><?php mcupr_e( 'reset_all' ); ?></button>
		</div>
	</div>
</template>

<template id="tpl-about">
	<div class="text-left text-fg">
		<div class="mb-4 flex items-center gap-3">
			<?php $about_logo = mcupr_logo( 'header' ); echo $about_logo ? $about_logo : '<span class="badge-logo" aria-hidden="true">' . mcupr_icon( 'cap', 22 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="leading-tight"><b class="block text-lg"><?php echo esc_html( mcupr_org_name() ); ?></b><?php if ( mcupr_alt_name() ) : ?><span class="text-sm text-muted"><?php echo esc_html( mcupr_alt_name() ); ?></span><?php endif; ?></div>
		</div>
		<?php $about = mcupr_text_mod( 'mcupr_about' ); ?>
		<?php if ( $about ) : ?><p class="mt-0 whitespace-pre-line"><?php echo esc_html( $about ); ?></p><?php endif; ?>
		<?php $addr = mcupr_text_mod( 'mcupr_address' ); ?>
		<?php if ( $addr ) : ?><p class="mb-1 font-semibold"><?php mcupr_e( 'contact_us_at' ); ?></p><address class="mb-4 not-italic whitespace-pre-line text-muted"><?php echo esc_html( $addr ); ?></address><?php endif; ?>
		<ul class="m-0 mb-4 flex list-none flex-wrap gap-2 p-0">
			<?php foreach ( array( 'privacy' => 'privacy', 'cookie' => 'cookie_policy', 'a11y' => 'a11y_policy', 'sitemap' => 'sitemap', 'contact' => 'contact' ) as $k => $label ) : ?>
				<?php $u = mcupr_policy_url( $k ); ?>
				<?php if ( $u ) : ?><li><a class="inline-flex min-h-11 items-center rounded-full border border-line px-4 text-fg no-underline hover:border-primary hover:text-link" href="<?php echo esc_url( $u ); ?>"><?php mcupr_e( $label ); ?></a></li><?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<p class="m-0 text-sm text-muted">
			<?php
			$credit = get_theme_mod( 'mcupr_credit_name', '' );
			if ( $credit ) {
				echo esc_html( mcupr_t( 'about_credit' ) . ' ' . $credit ) . ' · ';
			}
			echo esc_html( mcupr_t( 'theme_credit' ) . ' ' . MCUPR_VERSION );
			?>
		</p>
	</div>
</template>

<template id="tpl-lang">
	<div class="text-left"><?php get_template_part( 'template-parts/lang-switcher' ); ?></div>
</template>

<template id="tpl-search">
	<div class="mx-auto w-full max-w-5xl px-2 text-left text-fg min-[700px]:px-8">
		<h2 class="mb-8 text-3xl font-bold"><?php echo esc_html( mcupr_t( 'search_title' ) . ' ' . mcupr_org_name() ); ?></h2>
		<form role="search" method="get" action="<?php echo esc_url( mcupr_home_url() ); ?>" class="relative max-w-3xl" data-search-form>
			<label for="overlay-search" class="sr-only"><?php mcupr_e( 'search' ); ?></label>
			<div class="relative">
				<input id="overlay-search" type="search" name="s" autocomplete="off" placeholder="<?php echo esc_attr( mcupr_t( 'search_ph' ) ); ?>" class="min-h-14 w-full rounded-2xl border-2 border-primary bg-bg py-2 pr-16 pl-5 text-lg text-fg">
				<button type="submit" class="absolute top-1/2 right-2 inline-flex size-11 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-0 bg-transparent p-0 text-fg" aria-label="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>"><?php echo mcupr_icon( 'search', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			</div>
			<p class="mt-2 min-h-6 text-sm font-semibold text-link" role="alert" data-search-msg></p>
		</form>
		<section class="mt-6" data-recent hidden>
			<div class="mb-3 flex items-center gap-4"><h3 class="m-0 text-lg font-bold"><?php mcupr_e( 'recent' ); ?></h3><button type="button" class="min-h-11 cursor-pointer border-0 bg-transparent p-0 text-link underline" data-clear-recent><?php mcupr_e( 'clear' ); ?></button></div>
			<ul class="m-0 flex list-none flex-wrap gap-2 p-0" data-recent-list></ul>
		</section>
		<?php $popular = array_filter( array_map( 'trim', explode( ',', (string) mcupr_text_mod( 'mcupr_popular' ) ) ) ); ?>
		<?php if ( $popular ) : ?>
			<section class="mt-8">
				<h3 class="mb-3 text-lg font-bold"><?php mcupr_e( 'popular' ); ?></h3>
				<ul class="m-0 flex list-none flex-wrap gap-2 p-0">
					<?php foreach ( $popular as $term ) : ?>
						<li><a class="inline-flex min-h-11 items-center rounded-full border border-line px-5 text-fg no-underline hover:border-primary hover:text-link" href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $term ), mcupr_home_url() ) ); ?>"><?php echo esc_html( $term ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</div>
</template>
