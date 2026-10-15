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
$opt_icons = array( 'gray' => 'contrast', 'underline' => 'underline', 'readable' => 'type', 'spacing' => 'spacing', 'cursor' => 'pointer', 'pattern' => 'blossom', 'images' => 'image', 'tv' => 'tv' );
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
		<section class="settings-group" aria-labelledby="sg-display">
			<h3 id="sg-display" class="eyebrow"><?php mcupr_e( 'grp_display' ); ?></h3>
		<fieldset class="<?php echo esc_attr( $row ); ?> m-0 border-x-0 border-t-0 px-0">
			<legend class="float-left mr-3 p-0 text-lg font-semibold"><?php mcupr_e( 'menu_layout' ); ?></legend>
			<div class="mode-grid" role="radiogroup" aria-label="<?php echo esc_attr( mcupr_t( 'menu_layout' ) ); ?>">
				<?php foreach ( array( 'top' => array( 'menu_top', 'layout-top' ), 'bar' => array( 'menu_bar', 'layout-bar' ), 'side' => array( 'menu_side', 'layout-side' ) ) as $v => $d ) : ?>
					<label class="opt" data-tip="<?php echo esc_attr( mcupr_t( $d[0] ) ); ?>"><input class="peer sr-only" type="radio" name="mcupr-menu" data-pref="menu" value="<?php echo esc_attr( $v ); ?>"><span class="opt-b"><?php echo mcupr_icon( $d[1], 28 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="sr-only"><?php mcupr_e( $d[0] ); ?></span></span></label>
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
		<fieldset class="<?php echo esc_attr( $row ); ?> m-0 border-x-0 border-t-0 px-0">
			<legend class="float-left mr-3 p-0 text-lg font-semibold"><?php mcupr_e( 'theme' ); ?></legend>
			<div class="flex flex-wrap gap-2">
				<?php foreach ( array( 'auto' => 'theme_auto', 'light' => 'theme_light', 'dark' => 'theme_dark' ) as $v => $label ) : ?>
					<label class="relative"><input class="peer sr-only" type="radio" name="mcupr-theme" data-pref="theme" value="<?php echo esc_attr( $v ); ?>"><span class="<?php echo esc_attr( $pill ); ?>"><?php mcupr_e( $label ); ?></span></label>
				<?php endforeach; ?>
			</div>
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
		</section>

		<section class="settings-group" aria-labelledby="sg-text">
			<h3 id="sg-text" class="eyebrow"><?php mcupr_e( 'grp_text' ); ?></h3>
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
		</section>

		<section class="settings-group" aria-labelledby="sg-access">
			<h3 id="sg-access" class="eyebrow"><?php mcupr_e( 'grp_access' ); ?></h3>
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
		<details class="border-b border-line py-3">
			<summary class="flex min-h-11 cursor-pointer items-center text-lg font-semibold"><?php mcupr_e( 'more_options' ); ?></summary>
			<div class="opt-grid" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'more_options' ) ); ?>">
				<?php foreach ( $checks as $pref => $label ) : ?>
					<label class="opt" data-tip="<?php echo esc_attr( mcupr_t( $label ) ); ?>">
						<input class="peer sr-only" type="checkbox" data-pref="<?php echo esc_attr( $pref ); ?>" data-on="<?php echo in_array( $pref, array( 'images', 'pattern' ), true ) ? 'off' : '1'; ?>">
						<span class="opt-b peer-checked:bg-primary peer-checked:text-on-primary peer-focus-visible:outline peer-focus-visible:outline-[3px] peer-focus-visible:outline-offset-2 peer-focus-visible:outline-focus"><?php echo mcupr_icon( $opt_icons[ $pref ], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="sr-only"><?php mcupr_e( $label ); ?></span></span>
					</label>
				<?php endforeach; ?>
			</div>
		</details>
		</section>

		<div class="flex flex-wrap items-center justify-between gap-3 pt-4">
			<p class="m-0 text-sm text-muted" role="status" data-a11y-status></p>
			<button type="button" class="min-h-11 cursor-pointer border-0 bg-transparent p-0 text-link underline" data-reset-prefs><?php mcupr_e( 'reset_all' ); ?></button>
		</div>
	</div>
</template>

<template id="tpl-about">
	<?php
	$org        = mcupr_org_name();
	$about_logo = mcupr_logo( 'header' );
	$cr         = mcupr_credit();
	$credit     = $cr ? $cr['name'] : '';
	$o_email    = get_theme_mod( 'mcupr_org_email', '' );
	$o_tel      = get_theme_mod( 'mcupr_org_tel', '' );
	$about      = mcupr_text_mod( 'mcupr_about' );
	$addr       = mcupr_text_mod( 'mcupr_address' );
	$row        = static function ( $label, $value, $href = '', $external = false ) {
		$inner = $href ? '<a href="' . esc_url( $href ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $value ) . '</a>' : esc_html( $value );
		return '<li><span>' . esc_html( $label ) . '</span>' . $inner . '</li>';
	};
	?>
	<div class="text-left text-fg">
		<header class="about-head">
			<?php echo $about_logo ? $about_logo : '<span class="badge-logo" aria-hidden="true">' . mcupr_icon( 'cap', 22 ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="min-w-0">
				<p class="m-0 text-xl leading-snug font-bold"><?php echo esc_html( sprintf( mcupr_t( 'about_title' ), $org ) ); ?></p>
				<?php if ( mcupr_alt_name() ) : ?><p class="m-0 text-sm text-muted"><?php echo esc_html( mcupr_alt_name() ); ?></p><?php endif; ?>
			</div>
		</header>

		<?php $modes = function_exists( 'mcupr_active_modes' ) ? mcupr_active_modes() : array(); ?>
		<?php if ( $modes ) : ?>
		<section class="about-sec about-modes" aria-labelledby="ab-modes">
			<h3 id="ab-modes"><?php mcupr_e( 'md_title' ); ?></h3>
			<ul class="mode-list">
				<?php foreach ( $modes as $m ) : ?>
					<li class="mode-chip" data-mode="<?php echo esc_attr( $m ); ?>"><span class="mode-dot" aria-hidden="true"></span><?php echo esc_html( mcupr_t( 'md_' . $m ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="m-0 text-sm text-muted"><?php mcupr_e( 'md_note' ); ?> <a class="mode-set" href="<?php echo esc_url( admin_url( 'admin.php?page=mcupr-status' ) ); ?>"><?php mcupr_e( 'mt_bar_link' ); ?></a></p>
		</section>
		<?php endif; ?>

		<?php if ( $cr ) : ?>
		<section class="about-sec" aria-labelledby="ab-dev">
			<h3 id="ab-dev"><?php mcupr_e( 'about_dev' ); ?></h3>
			<div class="dev">
				<?php echo mcupr_credit_photo_html( $cr, 'dev-photo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="min-w-0">
					<p class="dev-name"><?php echo esc_html( $cr['name'] ); ?></p>
					<?php if ( $cr['en'] ) : ?><p class="dev-en"><?php echo esc_html( $cr['en'] ); ?></p><?php endif; ?>
					<?php if ( $cr['role'] ) : ?><p class="dev-role"><?php echo esc_html( $cr['role'] ); ?></p><?php endif; ?>
					<p class="dev-org"><?php echo esc_html( $org ); ?></p>
					<?php if ( $cr['tag'] ) : ?><p class="dev-tag"><?php echo esc_html( $cr['tag'] ); ?></p><?php endif; ?>
				</div>
			</div>
			<?php if ( $cr['email'] || $cr['tel'] || $cr['url'] || $cr['github'] ) : ?>
				<ul class="dev-contact">
					<?php
					echo $cr['email'] ? $row( mcupr_t( 'about_email' ), $cr['email'], 'mailto:' . $cr['email'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cr['tel'] ? $row( mcupr_t( 'about_tel' ), $cr['tel'], 'tel:' . preg_replace( '/[^0-9+]/', '', $cr['tel'] ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cr['github'] ? $row( 'GitHub', preg_replace( '#^https?://#', '', untrailingslashit( $cr['github'] ) ), $cr['github'], true ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cr['url'] ? $row( mcupr_t( 'about_web' ), preg_replace( '#^https?://#', '', untrailingslashit( $cr['url'] ) ), $cr['url'], true ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</ul>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<section class="about-sec" aria-labelledby="ab-site">
			<h3 id="ab-site"><?php mcupr_e( 'about' ); ?></h3>
			<?php if ( $about ) : ?><p class="whitespace-pre-line"><?php echo esc_html( $about ); ?></p><?php endif; ?>
			<ul class="about-list">
				<?php foreach ( array( 'about_f1', 'about_f2', 'about_f3', 'about_f4', 'about_f5' ) as $k ) : ?><li><?php mcupr_e( $k ); ?></li><?php endforeach; ?>
			</ul>
			<p class="about-label"><?php mcupr_e( 'about_stack' ); ?></p>
			<p class="about-stack"><?php foreach ( array( 'WordPress', 'Tailwind CSS', 'SweetAlert2', 'Sarabun', 'WCAG 2.2 AA' ) as $chip ) : ?><span class="chip-gray"><?php echo esc_html( $chip ); ?></span><?php endforeach; ?></p>
		</section>

		<section class="about-sec" aria-labelledby="ab-tech">
			<h3 id="ab-tech"><?php mcupr_e( 'about_tech' ); ?></h3>
			<p class="text-sm text-muted"><?php mcupr_e( 'about_tech_note' ); ?></p>
			<ul class="about-list">
				<?php
				foreach ( array(
					array( 'WordPress', 'https://wordpress.org/', 'GPLv2+' ),
					array( 'Tailwind CSS', 'https://tailwindcss.com/', 'MIT' ),
					array( 'SweetAlert2', 'https://sweetalert2.github.io/', 'MIT' ),
					array( 'Sarabun (Cadson Demak)', 'https://fonts.google.com/specimen/Sarabun', 'SIL OFL 1.1' ),
					array( 'Feather Icons', 'https://feathericons.com/', 'MIT' ),
				) as $r ) :
					?>
					<li><a href="<?php echo esc_url( $r[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $r[0] ); ?></a> (<?php echo esc_html( $r[2] ); ?>)</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="about-sec" aria-labelledby="ab-rights">
			<h3 id="ab-rights"><?php mcupr_e( 'about_rights' ); ?></h3>
			<ul class="about-list">
				<?php foreach ( array( 'about_r1', 'about_r2', 'about_r3', 'about_r4' ) as $k ) : ?><li><?php mcupr_e( $k ); ?></li><?php endforeach; ?>
			</ul>
			<ul class="m-0 flex list-none flex-wrap gap-2 p-0">
				<?php foreach ( array( 'privacy' => 'privacy', 'cookie' => 'cookie_policy', 'a11y' => 'a11y_policy', 'sitemap' => 'sitemap', 'contact' => 'contact' ) as $k => $label ) : ?>
					<?php $u = mcupr_policy_url( $k ); ?>
					<?php if ( $u ) : ?><li><a class="inline-flex min-h-11 items-center rounded-full border border-line px-4 text-fg no-underline hover:border-primary hover:text-link" href="<?php echo esc_url( $u ); ?>"><?php mcupr_e( $label ); ?></a></li><?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="about-sec" aria-labelledby="ab-contact">
			<h3 id="ab-contact"><?php mcupr_e( 'about_contact' ); ?></h3>
			<p class="mb-1"><b><?php echo esc_html( $org ); ?></b></p>
			<?php if ( $addr ) : ?><address class="mb-2 not-italic whitespace-pre-line text-muted"><?php echo esc_html( $addr ); ?></address><?php endif; ?>
			<?php if ( $o_email || $o_tel ) : ?>
				<ul class="dev-contact">
					<?php
					echo $o_email ? $row( mcupr_t( 'about_email' ), $o_email, 'mailto:' . $o_email ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					echo $o_tel ? $row( mcupr_t( 'about_tel' ), $o_tel, 'tel:' . preg_replace( '/[^0-9+]/', '', $o_tel ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</ul>
			<?php endif; ?>
			<p class="mt-3 mb-0 text-sm text-muted">
				<?php
				if ( $credit ) {
					echo esc_html( mcupr_t( 'about_credit' ) . ' ' . $credit ) . ' · ';
				}
				echo esc_html( mcupr_t( 'about_build' ) . ' MCUPR ' . MCUPR_VERSION );
				?>
			</p>
		</section>
	</div>
</template>

<template id="tpl-lang">
	<div class="text-left"><?php get_template_part( 'template-parts/lang-switcher' ); ?></div>
</template>

<template id="tpl-search">
	<?php
	$mcupr_terms = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( mcupr_text_mod( 'mcupr_popular' ) ? mcupr_text_mod( 'mcupr_popular' ) : mcupr_t( 'popular_terms' ) ) ) ) ) );
	$mcupr_eg    = $mcupr_terms ? sprintf( mcupr_t( 'sr_eg' ), $mcupr_terms[0] ) : mcupr_t( 'search_ph' );
	?>
	<div class="sr-box">
		<p class="sr-kicker" aria-hidden="true"><?php echo mcupr_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( mcupr_org_name() ); ?></p>
		<h2 class="sr-title"><?php mcupr_e( 'search_title' ); ?></h2>
		<p class="sr-sub"><?php mcupr_e( 'sr_sub' ); ?></p>
		<form role="search" method="get" action="<?php echo esc_url( mcupr_home_url() ); ?>" class="sr-form" data-search-form>
			<label for="overlay-search" class="sr-only"><?php mcupr_e( 'search' ); ?></label>
			<span class="sr-ic" aria-hidden="true"><?php echo mcupr_icon( 'search', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<input id="overlay-search" type="search" name="s" autocomplete="off" enterkeyhint="search" placeholder="<?php echo esc_attr( $mcupr_eg ); ?>" class="sr-input">
			<button type="submit" class="sr-go" aria-label="<?php echo esc_attr( mcupr_t( 'search' ) ); ?>"><span class="sr-go-t" aria-hidden="true"><?php mcupr_e( 'sr_go' ); ?></span><span class="sr-go-i" aria-hidden="true"><?php echo mcupr_icon( 'arrow', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></button>
		</form>
		<p class="sr-msg" role="alert" data-search-msg></p>
		<section class="sr-sec" data-recent hidden>
			<div class="sr-h"><h3><?php mcupr_e( 'recent' ); ?></h3><button type="button" class="sr-clear" data-clear-recent><?php mcupr_e( 'clear' ); ?></button></div>
			<ul class="sr-chips" data-recent-list></ul>
		</section>
		<?php if ( $mcupr_terms ) : ?>
			<section class="sr-sec">
				<div class="sr-h"><h3><?php echo esc_html( mcupr_text_mod( 'mcupr_popular' ) ? mcupr_t( 'popular' ) : mcupr_t( 'sr_try' ) ); ?></h3></div>
				<ul class="sr-chips">
					<?php foreach ( $mcupr_terms as $mcupr_term ) : ?>
						<li><a href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $mcupr_term ), mcupr_home_url() ) ); ?>"><?php echo mcupr_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $mcupr_term ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		<p class="sr-hint" aria-hidden="true"><?php mcupr_e( 'sr_hint' ); ?></p>
	</div>
</template>
