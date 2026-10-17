<?php
/**
 * "I do not want announcements" (the link at the end of an e-mail to students). Opening the link changes nothing; the button does.
 * Only announcements stop – the e-mails about the student's login and requests always go.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

get_header();

// phpcs:disable WordPress.Security.NonceVerification
$mcupr_token = get_query_var( 'mcupr_unsub' );
$mcupr_token = is_string( $mcupr_token ) ? $mcupr_token : '';
$mcupr_sid   = mcupr_mail_unsub_check( $mcupr_token );
$mcupr_do    = '';
if ( $mcupr_sid && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && mcupr_stu_throttle( 'unsub|' . mcupr_stu_ip(), 30, 15 * MINUTE_IN_SECONDS ) ) {
	$mcupr_do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( 'stop' === $mcupr_do || 'start' === $mcupr_do ) {
		mcupr_mail_set_stopped( $mcupr_sid, 'stop' === $mcupr_do );
	} else {
		$mcupr_do = '';
	}
}
// phpcs:enable
$mcupr_off  = $mcupr_sid && (bool) get_post_meta( $mcupr_sid, '_mcupr_s_nomail', true );
$mcupr_mask = '';
if ( $mcupr_sid ) {
	$mcupr_m    = (string) get_post_meta( $mcupr_sid, '_mcupr_s_email', true );
	$mcupr_at   = strpos( $mcupr_m, '@' );
	$mcupr_mask = false === $mcupr_at ? '' : substr( $mcupr_m, 0, 1 ) . '***' . substr( $mcupr_m, $mcupr_at );
}
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'um_t' ) ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap stu-wrap mt-10 pb-6">
	<div class="stu-grid">
		<section class="stu-card" aria-labelledby="um-h">
			<h2 id="um-h"><?php mcupr_e( 'um_t' ); ?></h2>
			<?php if ( ! $mcupr_sid ) : ?>
				<p class="stu-note stu-note-err" role="alert"><?php mcupr_e( 'um_bad' ); ?></p>
			<?php else : ?>
				<?php if ( 'stop' === $mcupr_do ) : ?><p class="stu-note" role="status"><?php mcupr_e( 'um_done_off' ); ?></p><?php endif; ?>
				<?php if ( 'start' === $mcupr_do ) : ?><p class="stu-note" role="status"><?php mcupr_e( 'um_done_on' ); ?></p><?php endif; ?>
				<p class="stu-intro"><?php echo esc_html( sprintf( mcupr_t( $mcupr_off ? 'um_intro_off' : 'um_intro_on' ), $mcupr_mask ) ); ?></p>
				<form method="post" action="<?php echo esc_url( add_query_arg( 'mcupr_unsub', $mcupr_token, home_url( '/' ) ) ); ?>" class="stu-form">
					<input type="hidden" name="do" value="<?php echo $mcupr_off ? 'start' : 'stop'; ?>">
					<p><button type="submit" class="btn"><?php mcupr_e( $mcupr_off ? 'um_start' : 'um_stop' ); ?></button></p>
				</form>
			<?php endif; ?>
		</section>
	</div>
</div>
<?php
get_footer();
