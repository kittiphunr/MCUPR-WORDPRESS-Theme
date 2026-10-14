<?php
/**
 * Comments: a soft card with bubble-style comments and a pill-shaped form.
 *
 * @package MCUPR
 */

if ( post_password_required() ) {
	return;
}

/** One comment as a card: round avatar, name, date, text, pill reply link. */
function mcupr_comment( $comment, $args, $depth ) {
	$is_author = get_comment_author_email( $comment ) && (int) $comment->user_id && (int) $comment->user_id === (int) get_post_field( 'post_author', $comment->comment_post_ID );
	?>
	<li id="comment-<?php comment_ID(); ?>" <?php comment_class( 'list-none' ); ?>>
		<article class="comment-card flex gap-3 min-[600px]:gap-4">
			<?php /* Initial in a round badge: no Gravatar request, so no visitor data goes to a third party (PDPA). */ ?>
			<span class="stat-ic !size-12 text-lg font-bold uppercase" aria-hidden="true"><?php echo esc_html( mb_substr( trim( get_comment_author( $comment ) ), 0, 1 ) ); ?></span>
			<div class="min-w-0 flex-1">
				<div class="comment-bubble">
					<p class="m-0 flex flex-wrap items-center gap-x-3 gap-y-1">
						<b class="text-base"><?php echo esc_html( get_comment_author( $comment ) ); ?></b>
						<?php if ( $is_author ) : ?><span class="rounded-full bg-primary px-2.5 text-xs font-bold text-on-primary"><?php mcupr_e( 'c_author' ); ?></span><?php endif; ?>
						<a class="text-sm text-muted no-underline" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>"><time datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>"><?php echo esc_html( mcupr_date_text( get_comment_date( 'U', $comment ) ) ); ?></time></a>
					</p>
					<?php if ( '0' === $comment->comment_approved ) : ?>
						<p class="mt-2 mb-0 rounded-lg bg-tint px-3 py-1 text-sm"><?php mcupr_e( 'c_moderation' ); ?></p>
					<?php endif; ?>
					<div class="mt-2 [&_p]:m-0 [&_p+p]:mt-3"><?php comment_text(); ?></div>
				</div>
				<?php
				comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'      => $depth,
							'max_depth'  => $args['max_depth'],
							'reply_text'    => mcupr_t( 'c_reply' ),
							'reply_to_text' => mcupr_t( 'c_reply' ) . ' %s',
							'before'     => '<p class="m-0 mt-1 pl-2">',
							'after'      => '</p>',
						)
					)
				);
				?>
			</div>
		</article>
	<?php
	// WordPress closes the <li> itself.
}

$count = (int) get_comments_number();
$cls   = is_page() ? 'mt-12 border-t border-line pt-8' : 'soft-card mt-12 p-5 min-[700px]:p-9';
?>
<section id="comments" class="<?php echo esc_attr( $cls ); ?>" aria-labelledby="comments-title">
	<h2 id="comments-title" class="mcu-title mb-6 flex items-center gap-3 text-2xl font-bold">
		<span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'chat', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<?php mcupr_e( 'comments' ); ?><?php echo $count ? ' (' . esc_html( number_format_i18n( $count ) ) . ')' : ''; ?>
	</h2>
	<?php if ( have_comments() ) : ?>
		<ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'callback' => 'mcupr_comment', 'avatar_size' => 48 ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php if ( ! comments_open() && $count ) : ?>
		<p class="rounded-2xl bg-tint px-4 py-3 text-center"><?php mcupr_e( 'c_closed' ); ?></p>
	<?php endif; ?>
	<?php
	$req = get_option( 'require_name_email' );
	$ph  = '<p class="comment-form-%1$s"><label for="%1$s">%2$s%3$s</label><input id="%1$s" name="%1$s" type="%4$s" autocomplete="%5$s" %6$s></p>';
	$star = $req ? ' <span aria-hidden="true">*</span>' : '';
	comment_form(
		array(
			'title_reply'          => mcupr_t( 'leave_reply' ),
			'title_reply_before'   => '<h3 id="reply-title" class="comment-reply-title">',
			'title_reply_after'    => '</h3>',
			'label_submit'         => mcupr_t( 'post_comment' ),
			'class_submit'         => 'submit',
			'comment_notes_before' => '<p class="comment-notes text-sm text-muted">' . esc_html( mcupr_t( 'c_required' ) ) . '</p>',
			'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html( mcupr_t( 'c_comment' ) ) . ' <span aria-hidden="true">*</span></label><textarea id="comment" name="comment" cols="45" rows="5" maxlength="65525" required></textarea></p>',
			'fields'               => array(
				'author'  => sprintf( $ph, 'author', esc_html( mcupr_t( 'c_name' ) ), $star, 'text', 'name', $req ? 'required' : '' ),
				'email'   => sprintf( $ph, 'email', esc_html( mcupr_t( 'c_email' ) ), $star, 'email', 'email', $req ? 'required' : '' ),
				'url'     => sprintf( $ph, 'url', esc_html( mcupr_t( 'c_url' ) ), '', 'url', 'url', '' ),
				'cookies' => '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"><label for="wp-comment-cookies-consent">' . esc_html( mcupr_t( 'c_cookies' ) ) . '</label></p>',
			),
		)
	);
	?>
</section>
