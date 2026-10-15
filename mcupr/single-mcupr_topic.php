<?php
/**
 * A forum topic: the first post, the replies and the reply form (see inc/forum.php).
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$id     = get_the_ID();
	$name   = mcupr_topic_name( $id );
	$board  = get_the_terms( $id, 'mcupr_board' );
	$board  = $board && ! is_wp_error( $board ) ? $board[0] : null;
	$byuser = '' !== (string) get_post_meta( $id, '_mcupr_topic_email', true ); // a visitor's post is plain text; one written in the admin may use the editor
	$locked = mcupr_topic_locked( $id );
	$staff  = (int) get_post_field( 'post_author', $id ) && user_can( (int) get_post_field( 'post_author', $id ), 'edit_pages' );
	list( $notices ) = mcupr_forum_notices();
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10">
		<div class="mx-auto w-[min(100%-1.5rem,52rem)] min-[400px]:w-[min(100%-2rem,52rem)]">
			<nav class="forum-crumb" aria-label="<?php echo esc_attr( mcupr_t( 'fm_title' ) ); ?>"><a href="<?php echo esc_url( mcupr_forum_page_url() ); ?>"><?php mcupr_e( 'fm_title' ); ?></a><?php echo $board ? ' › <a href="' . esc_url( add_query_arg( 'fb', $board->slug, mcupr_forum_page_url() ) ) . '">' . esc_html( $board->name ) . '</a>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></nav>
			<h1 class="mt-3 mb-3 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<p class="forum-m mb-0"><?php echo esc_html( mcupr_t( 'fm_by' ) . ' ' . $name ); ?> · <?php echo esc_html( get_the_date( 'j M Y H:i' ) ); ?><?php echo get_post_meta( $id, '_mcupr_topic_sticky', true ) ? ' · <span class="forum-badge">' . esc_html( mcupr_t( 'fm_pinned' ) ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $locked ? ' · <span class="forum-badge forum-badge-l">' . esc_html( mcupr_t( 'fm_locked' ) ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
		</div>
	</header>
	<div class="panel-card mx-auto -mt-6 w-[min(100%-1.5rem,54rem)] min-[400px]:w-[min(100%-2rem,54rem)] relative">
		<div class="forum">
		<?php echo $notices; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div class="forum-op">
			<span class="forum-av" aria-hidden="true"><?php echo esc_html( mcupr_forum_initial( $name ) ); ?></span>
			<div class="forum-rb">
				<p class="forum-rh"><b><?php echo esc_html( $name ); ?></b><?php echo $staff ? ' <span class="forum-badge">' . esc_html( mcupr_t( 'fm_staff' ) ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				<div class="forum-tx"><?php echo $byuser ? mcupr_forum_text( get_the_content() ) : wp_kses_post( wpautop( get_the_content() ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php echo mcupr_forum_report_button( 'topic', $id, get_permalink( $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</div>
		<h2 class="forum-h"><?php echo esc_html( mcupr_t( 'fm_replies' ) . ' (' . mcupr_topic_replies( $id ) . ')' ); ?></h2>
		<?php echo mcupr_forum_replies_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $locked && ! current_user_can( 'edit_pages' ) ) : ?>
			<p class="forum-locked"><?php mcupr_e( 'fm_locked_note' ); ?></p>
		<?php elseif ( mcupr_forum_opts()['enabled'] ) : ?>
			<h2 class="forum-h" id="fm-new"><?php mcupr_e( 'fm_reply' ); ?></h2>
			<?php echo mcupr_forum_form( 0, $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		</div>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( $board ? add_query_arg( 'fb', $board->slug, mcupr_forum_page_url() ) : mcupr_forum_page_url() ); ?>">‹ <?php mcupr_e( 'fm_title' ); ?></a></p>
	</div>
</article>
	<?php
endwhile;
get_footer();
