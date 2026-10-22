<?php
/**
 * Stand-alone full page for the welcome page and the maintenance page (inc/site-status.php).
 * Uses wp_head()/wp_footer() so the theme's fonts, colours and the visitor's own accessibility settings apply.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$mcupr_kind = isset( $GLOBALS['mcupr_status_kind'] ) && 'welcome' === $GLOBALS['mcupr_status_kind'] ? 'welcome' : 'maint';
$mcupr_s    = mcupr_status();
$mcupr_lang = mcupr_ui_lang();
$mcupr_name = mcupr_org_name();
$mcupr_pre  = mcupr_welcome_presets();

if ( 'welcome' === $mcupr_kind ) {
	$mcupr_w   = $mcupr_s['welcome'];
	$mcupr_pr  = isset( $mcupr_pre[ $mcupr_w['preset'] ] ) ? $mcupr_pre[ $mcupr_w['preset'] ] : $mcupr_pre['custom'];
	$mcupr_h1  = mcupr_status_text( $mcupr_w['title'], mcupr_welcome_preset_text( $mcupr_pr, 'title' ) );
	$mcupr_h1  = '' !== $mcupr_h1 ? $mcupr_h1 : $mcupr_name;
	$mcupr_msg = mcupr_status_text( $mcupr_w['msg'], mcupr_welcome_preset_text( $mcupr_pr, 'msg' ) );
	$mcupr_btn = mcupr_status_text( $mcupr_w['btn'], mcupr_t( 'ws_enter' ) );
	$mcupr_c1  = $mcupr_pr[1];
	$mcupr_c2  = $mcupr_pr[2];
	$mcupr_img = (int) $mcupr_w['image'] ? wp_get_attachment_image_url( (int) $mcupr_w['image'], 'full' ) : '';
	$mcupr_go  = add_query_arg( 'mcupr_enter', '1', home_url( '/' ) );
	if ( ! empty( $GLOBALS['mcupr_status_sample'] ) && isset( $mcupr_pre[ $GLOBALS['mcupr_status_sample'] ] ) ) { // a sample: the preset's own text, no picture, the button just goes home
		$mcupr_pr  = $mcupr_pre[ $GLOBALS['mcupr_status_sample'] ];
		$mcupr_h1  = mcupr_welcome_preset_text( $mcupr_pr, 'title' );
		$mcupr_msg = mcupr_welcome_preset_text( $mcupr_pr, 'msg' );
		$mcupr_btn = mcupr_t( 'ws_enter' );
		$mcupr_c1  = $mcupr_pr[1];
		$mcupr_c2  = $mcupr_pr[2];
		$mcupr_img = '';
		$mcupr_go  = home_url( '/' );
		$mcupr_w['auto'] = 0;
	}
} else {
	$mcupr_m   = $mcupr_s['maint'];
	$mcupr_h1  = mcupr_status_text( $mcupr_m['title'], mcupr_t( 'mt_title' ) );
	$mcupr_msg = mcupr_status_text( $mcupr_m['msg'], mcupr_t( 'mt_msg' ) );
	$mcupr_c1  = '#1c1c1f';
	$mcupr_c2  = '#34343a';
	$mcupr_img = (int) $mcupr_m['image'] ? wp_get_attachment_image_url( (int) $mcupr_m['image'], 'full' ) : '';
	$mcupr_ts  = mcupr_maintenance_end_ts( $mcupr_m );
	$mcupr_tel = (string) get_theme_mod( 'mcupr_org_tel', '' );
	$mcupr_mail = (string) get_theme_mod( 'mcupr_org_email', '' );
}
$mcupr_title = wp_strip_all_tags( $mcupr_h1 ) . ' – ' . $mcupr_name;
add_filter( 'pre_get_document_title', static function () use ( $mcupr_title ) { return $mcupr_title; }, 99 );
$mcupr_style = '--s1:' . $mcupr_c1 . ';--s2:' . $mcupr_c2 . ( $mcupr_img ? ';--simg:url(\'' . esc_url_raw( $mcupr_img ) . '\')' : '' );
?>
<!doctype html>
<html <?php language_attributes(); ?> class="status-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body class="status-body status-<?php echo esc_attr( $mcupr_kind ); ?><?php echo $mcupr_img ? ' has-img' : ''; ?>" style="<?php echo esc_attr( $mcupr_style ); ?>">
<?php wp_body_open(); ?>
<?php if ( 'maint' === $mcupr_kind ) : ?>
<div class="status-tape status-tape-top" aria-hidden="true"></div>
<div class="status-tape status-tape-bottom" aria-hidden="true"></div>
<?php endif; ?>
<main id="content" class="status-card">
	<?php if ( 'maint' === $mcupr_kind ) : ?>
		<div class="status-side">
			<div class="status-logo"><?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p class="status-org"><?php echo esc_html( $mcupr_name ); ?></p>
			<svg class="status-scene" viewBox="0 0 220 180" width="220" height="180" aria-hidden="true" focusable="false">
				<g class="sc-gear sc-g1"><circle cx="78" cy="116" r="40" fill="none" stroke="#facc15" stroke-width="12" stroke-dasharray="11.2 11.2"/><circle cx="78" cy="116" r="34" fill="#facc15"/><circle cx="78" cy="116" r="12" fill="#1c1c1f"/></g>
				<g class="sc-gear sc-g2"><circle cx="146" cy="136" r="26" fill="none" stroke="#e5e7eb" stroke-width="9" stroke-dasharray="9.1 9.1"/><circle cx="146" cy="136" r="21" fill="#e5e7eb"/><circle cx="146" cy="136" r="8" fill="#1c1c1f"/></g>
				<g class="sc-hat"><path d="M52 62a46 46 0 0 1 92 0z" fill="#facc15"/><rect x="92" y="14" width="12" height="48" rx="3" fill="#f59e0b"/><path d="M44 62h108a7 7 0 0 1 0 14H44a7 7 0 0 1 0-14z" fill="#f59e0b"/><path d="M62 52a36 36 0 0 1 14-22" fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="4" stroke-linecap="round"/></g>
				<g class="sc-wrench"><path d="M172 38a14 14 0 0 0-17 17l-36 36a8 8 0 0 0 11 11l36-36a14 14 0 0 0 17-17l-9 9-8-3-3-8z" fill="#f3f4f6" stroke="#111827" stroke-width="3" stroke-linejoin="round"/></g>
				<g class="sc-spark"><path d="M186 30l8-8M192 44l11-2M176 22l1-11" stroke="#facc15" stroke-width="3" stroke-linecap="round"/></g>
			</svg>
		</div>
		<div class="status-main">
		<p class="status-badge"><span class="status-gear" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M14.7 6.3a4 4 0 0 0 5 5L21 17.5a2.1 2.1 0 0 1-3 3l-6.2-6.2a4 4 0 0 1-5-5l2.9 2.9 2.4-.6.6-2.4z"/></svg></span><?php mcupr_e( 'mt_badge' ); ?></p>
	<?php else : ?>
		<div class="status-logo"><?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<p class="status-org"><?php echo esc_html( $mcupr_name ); ?></p>
	<?php endif; ?>
	<h1 class="status-h"><?php echo esc_html( $mcupr_h1 ); ?></h1>
	<?php if ( '' !== $mcupr_msg ) : ?>
		<p class="status-msg"><?php echo nl2br( esc_html( $mcupr_msg ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
	<?php endif; ?>
	<?php if ( 'welcome' === $mcupr_kind ) : ?>
		<p class="status-act"><a class="status-btn" href="<?php echo esc_url( $mcupr_go ); ?>" autofocus><?php echo esc_html( $mcupr_btn ); ?> <span aria-hidden="true">→</span></a></p>
		<?php if ( (int) $mcupr_w['auto'] > 0 ) : ?>
			<p class="status-auto" data-auto="<?php echo (int) $mcupr_w['auto']; ?>" data-go="<?php echo esc_url( $mcupr_go ); ?>" data-text="<?php echo esc_attr( mcupr_t( 'ws_auto' ) ); ?>"><span data-cd></span> <button type="button" class="status-stop" data-stop><?php mcupr_e( 'ws_stop' ); ?></button></p>
		<?php endif; ?>
	<?php else : ?>
		<div class="status-bar" aria-hidden="true"><i></i></div>
		<?php if ( $mcupr_ts ) : ?>
			<p class="status-back"><?php mcupr_e( 'mt_back' ); ?>: <b><?php echo esc_html( mcupr_date_fmt( 'j F Y H:i', $mcupr_ts ) ); ?></b></p>
			<?php if ( $mcupr_ts > time() ) : ?>
				<ul class="status-cd" aria-hidden="true" data-ts="<?php echo (int) $mcupr_ts; ?>">
					<li><b data-u="d">0</b><span><?php mcupr_e( 'mt_d' ); ?></span></li>
					<li><b data-u="h">0</b><span><?php mcupr_e( 'mt_h' ); ?></span></li>
					<li><b data-u="m">0</b><span><?php mcupr_e( 'mt_m' ); ?></span></li>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $mcupr_tel || $mcupr_mail ) : ?>
			<div class="status-contact"><span class="status-cl"><?php mcupr_e( 'mt_contact' ); ?></span>
				<?php if ( $mcupr_tel ) : ?><a class="status-chip" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $mcupr_tel ) ); ?>"><?php echo mcupr_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $mcupr_tel ); ?></a><?php endif; ?>
				<?php if ( $mcupr_mail ) : ?><a class="status-chip" href="mailto:<?php echo esc_attr( $mcupr_mail ); ?>"><?php echo mcupr_icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $mcupr_mail ); ?></a><?php endif; ?>
			</div>
		<?php endif; ?>
		<p class="status-retry"><a class="status-btn2" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php mcupr_e( 'mt_retry' ); ?></a></p>
	<?php endif; ?>
<?php if ( 'maint' === $mcupr_kind ) : ?>
		</div>
<?php endif; ?>
</main>
<?php if ( 'welcome' === $mcupr_kind && (int) $mcupr_w['auto'] > 0 ) : ?>
<script>
(function () {
	var box = document.querySelector('[data-auto]'); if (!box) { return; }
	var n = parseInt(box.getAttribute('data-auto'), 10), out = box.querySelector('[data-cd]'), stopped = false;
	var paint = function () { out.textContent = box.getAttribute('data-text').replace('%d', n); };
	paint();
	var t = setInterval(function () { if (stopped) { return; } n--; if (n <= 0) { clearInterval(t); location.href = box.getAttribute('data-go'); return; } paint(); }, 1000);
	box.querySelector('[data-stop]').addEventListener('click', function () { stopped = true; clearInterval(t); box.hidden = true; });
}());
</script>
<?php endif; ?>
<?php if ( 'maint' === $mcupr_kind && ! empty( $mcupr_ts ) && $mcupr_ts > time() ) : ?>
<script>
(function () {
	var box = document.querySelector('[data-ts]'); if (!box) { return; }
	var end = parseInt(box.getAttribute('data-ts'), 10) * 1000;
	var set = function (u, v) { var e = box.querySelector('[data-u=' + u + ']'); if (e) { e.textContent = v; } };
	var tick = function () {
		var left = Math.max(0, Math.floor((end - Date.now()) / 60000));
		set('d', Math.floor(left / 1440)); set('h', Math.floor((left % 1440) / 60)); set('m', left % 60);
		if (left <= 0) { box.hidden = true; }
	};
	tick(); setInterval(tick, 30000);
}());
</script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
