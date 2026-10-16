<?php
/**
 * Follow a request (/request/): the request number and the e-mail address or student ID used when it was sent.
 * Only the status and the messages are shown – never what was written or attached.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

get_header();

// phpcs:disable WordPress.Security.NonceVerification
$mcupr_post  = 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' );
$mcupr_code  = $mcupr_post ? ( isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '' ) : ( isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '' );
$mcupr_who   = $mcupr_post && isset( $_POST['who'] ) ? sanitize_text_field( wp_unslash( $_POST['who'] ) ) : '';
// phpcs:enable
$mcupr_err   = '';
$mcupr_found = 0;
if ( $mcupr_post ) {
	if ( ! isset( $_POST['mcupr_rq_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_rq_nonce'] ) ), 'mcupr_rq_track' ) ) {
		$mcupr_err = 'rq_track_bad';
	} elseif ( ! mcupr_stu_throttle( 'rqt|' . mcupr_stu_ip(), 20, 15 * MINUTE_IN_SECONDS ) || ! mcupr_stu_throttle( 'rqc|' . strtoupper( $mcupr_code ), 8, 15 * MINUTE_IN_SECONDS ) ) {
		$mcupr_err = 'rq_track_wait';
	} else {
		$mcupr_found = mcupr_rq_find( $mcupr_code, $mcupr_who );
		if ( ! $mcupr_found ) {
			$mcupr_err = 'rq_track_bad';
		}
	}
}
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'rq_track_t' ) ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap stu-wrap mt-10 pb-6">
	<div class="stu-grid">
		<section class="stu-card" aria-labelledby="rq-track-h">
			<h2 id="rq-track-h"><?php mcupr_e( 'rq_track_t' ); ?></h2>
			<p class="stu-intro"><?php mcupr_e( 'rq_track_intro' ); ?></p>
			<?php if ( '' !== $mcupr_err ) : ?><p class="stu-note stu-note-err" role="alert"><?php mcupr_e( $mcupr_err ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( mcupr_rq_track_url() ); ?>" class="stu-form">
				<?php wp_nonce_field( 'mcupr_rq_track', 'mcupr_rq_nonce' ); ?>
				<p><label for="rq-code"><?php mcupr_e( 'rq_track_code' ); ?></label><input id="rq-code" type="text" name="code" value="<?php echo esc_attr( $mcupr_code ); ?>" placeholder="REQ-2026-00001" autocapitalize="characters" autocomplete="off" required></p>
				<p><label for="rq-who"><?php mcupr_e( 'rq_track_who' ); ?></label><input id="rq-who" type="text" name="who" autocomplete="off" autocapitalize="off" required></p>
				<p><button type="submit" class="btn"><?php mcupr_e( 'rq_track_btn' ); ?></button></p>
			</form>
		</section>
		<?php if ( $mcupr_found ) : ?>
			<?php
			$mcupr_form = (int) get_post_meta( $mcupr_found, '_mcupr_fe_form', true );
			$mcupr_st   = (string) get_post_meta( $mcupr_found, '_mcupr_rq_status', true );
			?>
			<section class="stu-card" aria-labelledby="rq-res-h" id="rq-result">
				<h2 id="rq-res-h"><?php echo esc_html( get_the_title( $mcupr_form ) ); ?></h2>
				<p class="rq-m"><?php echo esc_html( (string) get_post_meta( $mcupr_found, '_mcupr_rq_code', true ) ); ?> · <?php echo esc_html( wp_date( 'j M Y', strtotime( get_post_field( 'post_date_gmt', $mcupr_found ) . ' UTC' ) ) ); ?></p>
				<p><?php echo mcupr_rq_chip( $mcupr_st ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				<?php echo mcupr_rq_timeline_html( $mcupr_found ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( mcupr_current_student() ) : ?><p class="stu-help"><a href="<?php echo esc_url( mcupr_student_portal_url() . '#stu-rq' ); ?>"><?php mcupr_e( 'rq_my' ); ?></a></p><?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();
