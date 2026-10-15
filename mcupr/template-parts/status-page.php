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
	$mcupr_pth = 'th' === $mcupr_lang;
	$mcupr_h1  = mcupr_status_text( $mcupr_w['title'], $mcupr_pth ? $mcupr_pr[3] : $mcupr_pr[5] );
	$mcupr_h1  = '' !== $mcupr_h1 ? $mcupr_h1 : $mcupr_name;
	$mcupr_msg = mcupr_status_text( $mcupr_w['msg'], $mcupr_pth ? $mcupr_pr[4] : $mcupr_pr[6] );
	$mcupr_btn = mcupr_status_text( $mcupr_w['btn'], mcupr_t( 'ws_enter' ) );
	$mcupr_c1  = $mcupr_pr[1];
	$mcupr_c2  = $mcupr_pr[2];
	$mcupr_img = (int) $mcupr_w['image'] ? wp_get_attachment_image_url( (int) $mcupr_w['image'], 'full' ) : '';
	$mcupr_go  = add_query_arg( 'mcupr_enter', '1', home_url( '/' ) );
} else {
	$mcupr_m   = $mcupr_s['maint'];
	$mcupr_h1  = mcupr_status_text( $mcupr_m['title'], mcupr_t( 'mt_title' ) );
	$mcupr_msg = mcupr_status_text( $mcupr_m['msg'], mcupr_t( 'mt_msg' ) );
	$mcupr_c1  = '#1e293b';
	$mcupr_c2  = '#334155';
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
<main id="content" class="status-card">
	<div class="status-logo"><?php echo mcupr_logo( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<p class="status-org"><?php echo esc_html( $mcupr_name ); ?></p>
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
		<?php if ( $mcupr_ts ) : ?>
			<p class="status-back"><?php mcupr_e( 'mt_back' ); ?>: <b><?php echo esc_html( wp_date( 'j F Y H:i', $mcupr_ts ) ); ?></b></p>
		<?php endif; ?>
		<?php if ( $mcupr_tel || $mcupr_mail ) : ?>
			<p class="status-contact"><?php mcupr_e( 'mt_contact' ); ?>:
				<?php if ( $mcupr_tel ) : ?><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $mcupr_tel ) ); ?>"><?php echo esc_html( $mcupr_tel ); ?></a><?php endif; ?>
				<?php if ( $mcupr_mail ) : ?><a href="mailto:<?php echo esc_attr( $mcupr_mail ); ?>"><?php echo esc_html( $mcupr_mail ); ?></a><?php endif; ?>
			</p>
		<?php endif; ?>
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
<?php wp_footer(); ?>
</body>
</html>
