<?php
/**
 * The student portal (/student/): login and "first time / forgot my password" for a visitor; for a student their own
 * details, the content for students only and a change-password form.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

get_header();

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$mcupr_e    = isset( $_GET['e'] ) ? sanitize_key( wp_unslash( $_GET['e'] ) ) : '';
$mcupr_sent = isset( $_GET['sent'] );
$mcupr_pwok = isset( $_GET['pw'] ) && 'ok' === $_GET['pw'];
$mcupr_to   = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( rawurldecode( wp_unslash( $_GET['redirect_to'] ) ) ), '' ) : '';
// phpcs:enable
$mcupr_o    = mcupr_stu_opts();
$mcupr_user = wp_get_current_user();
$mcupr_sid  = is_user_logged_in() ? mcupr_current_student( true ) : 0;
$mcupr_errs = array(
	'login'   => 'stu_bad',
	'wait'    => 'stu_wait',
	'need'    => 'stu_need',
	'pwcur'   => 'stu_pw_cur_bad',
	'pwshort' => 'stu_pw_short',
	'pwsame'  => 'stu_pw_same',
	'sso'     => 'stu_sso_bad',
	'ssonone' => 'stu_sso_none',
	'ssowait' => 'stu_wait',
);
get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'stu_portal' ) ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap stu-wrap mt-10 pb-6">
	<?php if ( '' !== $mcupr_e && isset( $mcupr_errs[ $mcupr_e ] ) ) : ?>
		<p class="stu-note stu-note-err" role="alert"><?php mcupr_e( $mcupr_errs[ $mcupr_e ] ); ?></p>
	<?php endif; ?>

	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="stu-grid">
			<section class="stu-card" aria-labelledby="stu-login-t">
				<h2 id="stu-login-t"><?php mcupr_e( 'stu_login' ); ?></h2>
				<?php if ( '' !== $mcupr_o['intro'] ) : ?><p class="stu-intro"><?php echo nl2br( esc_html( $mcupr_o['intro'] ) ); ?></p><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stu-form">
					<input type="hidden" name="action" value="mcupr_stu_login">
					<?php wp_nonce_field( 'mcupr_stu_login', 'mcupr_stu_nonce' ); ?>
					<?php if ( '' !== $mcupr_to ) : ?><input type="hidden" name="redirect_to" value="<?php echo esc_url( $mcupr_to ); ?>"><?php endif; ?>
					<p><label for="stu-log"><?php mcupr_e( 'stu_id' ); ?></label><input id="stu-log" type="text" name="log" autocomplete="username" autocapitalize="off" required></p>
					<p><label for="stu-pwd"><?php mcupr_e( 'stu_pass' ); ?></label><input id="stu-pwd" type="password" name="pwd" autocomplete="current-password" required></p>
					<p class="stu-check"><label><input type="checkbox" name="rememberme" value="1"> <?php mcupr_e( 'stu_remember' ); ?></label></p>
					<p><button type="submit" class="btn"><?php mcupr_e( 'stu_login' ); ?></button></p>
				</form>
				<?php if ( mcupr_sso_ready() ) : ?><p class="stu-sso"><a class="btn stu-btn2" href="<?php echo esc_url( mcupr_sso_start_url() ); ?>"><?php mcupr_e( 'stu_sso' ); ?></a></p><?php endif; ?>
			</section>
			<section class="stu-card" aria-labelledby="stu-first-t">
				<h2 id="stu-first-t"><?php mcupr_e( 'stu_first' ); ?></h2>
				<p class="stu-intro"><?php mcupr_e( 'stu_first_txt' ); ?></p>
				<?php if ( $mcupr_sent ) : ?><p class="stu-note stu-note-ok" role="status"><?php mcupr_e( 'stu_sent' ); ?></p><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stu-form">
					<input type="hidden" name="action" value="mcupr_stu_forgot">
					<?php wp_nonce_field( 'mcupr_stu_forgot', 'mcupr_stu_nonce' ); ?>
					<p><label for="stu-sid"><?php mcupr_e( 'stu_f_id' ); ?></label><input id="stu-sid" type="text" name="sid" inputmode="text" autocapitalize="off" required></p>
					<p><button type="submit" class="btn stu-btn2"><?php mcupr_e( 'stu_send' ); ?></button></p>
				</form>
			</section>
		</div>
		<?php if ( '' !== $mcupr_o['help'] ) : ?><p class="stu-help"><?php echo nl2br( esc_html( $mcupr_o['help'] ) ); ?></p><?php endif; ?>

	<?php else : ?>
		<div class="stu-top">
			<p class="stu-hello"><?php echo esc_html( sprintf( mcupr_t( 'stu_hello' ), $mcupr_user->display_name ) ); ?></p>
			<a class="stu-out" href="<?php echo esc_url( wp_logout_url( mcupr_student_portal_url() ) ); ?>"><?php mcupr_e( 'stu_logout' ); ?></a>
		</div>
		<?php if ( $mcupr_pwok ) : ?><p class="stu-note stu-note-ok" role="status"><?php mcupr_e( 'stu_pw_ok' ); ?></p><?php endif; ?>
		<?php if ( ! $mcupr_sid ) : ?>
			<p class="stu-note"><?php mcupr_e( 'stu_not_student' ); ?></p>
		<?php elseif ( ! mcupr_current_student() ) : ?>
			<p class="stu-note stu-note-err"><?php mcupr_e( 'stu_ended' ); ?></p>
		<?php endif; ?>
		<div class="stu-grid stu-grid-2">
			<?php if ( $mcupr_sid ) : ?>
				<section class="stu-card" aria-labelledby="stu-me-t">
					<h2 id="stu-me-t"><?php mcupr_e( 'stu_profile' ); ?></h2>
					<?php echo mcupr_student_card_html( $mcupr_sid ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p class="stu-help"><?php mcupr_e( 'stu_wrong' ); ?></p>
				</section>
			<?php endif; ?>
			<section class="stu-card" id="stu-pw" aria-labelledby="stu-pw-t">
				<h2 id="stu-pw-t"><?php mcupr_e( 'stu_pw' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stu-form">
					<input type="hidden" name="action" value="mcupr_stu_pw">
					<?php wp_nonce_field( 'mcupr_stu_pw', 'mcupr_stu_nonce' ); ?>
					<p><label for="stu-cur"><?php mcupr_e( 'stu_pw_now' ); ?></label><input id="stu-cur" type="password" name="cur" autocomplete="current-password" required></p>
					<p><label for="stu-new"><?php mcupr_e( 'stu_pw_new' ); ?></label><input id="stu-new" type="password" name="new" autocomplete="new-password" minlength="8" required></p>
					<p><label for="stu-new2"><?php mcupr_e( 'stu_pw_new2' ); ?></label><input id="stu-new2" type="password" name="new2" autocomplete="new-password" minlength="8" required></p>
					<p><button type="submit" class="btn stu-btn2"><?php mcupr_e( 'stu_pw_save' ); ?></button></p>
				</form>
			</section>
		</div>
		<?php echo mcupr_sg_portal_html( $mcupr_sid ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo mcupr_rq_portal_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php $mcupr_items = mcupr_aud_items( 30 ); ?>
		<section class="stu-sec" aria-labelledby="stu-only-t">
			<h2 id="stu-only-t" class="stu-h"><?php mcupr_e( 'stu_only' ); ?></h2>
			<?php if ( $mcupr_items ) : ?>
				<ul class="stu-list">
					<?php foreach ( $mcupr_items as $mcupr_it ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $mcupr_it ) ); ?>"><span class="stu-l-t"><?php echo esc_html( get_the_title( $mcupr_it ) ); ?></span><span class="stu-l-m"><?php mcupr_date( $mcupr_it ); ?></span></a></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="text-muted"><?php mcupr_e( 'stu_none' ); ?></p>
			<?php endif; ?>
		</section>
		<?php if ( '' !== $mcupr_o['help'] ) : ?><p class="stu-help"><?php echo nl2br( esc_html( $mcupr_o['help'] ) ); ?></p><?php endif; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
