<?php
/**
 * Events archive (/events/): month calendar + upcoming events.
 *
 * @package MCUPR
 */

get_header();

get_template_part( 'template-parts/band', null, array( 'title' => esc_html( mcupr_t( 'events_title' ) ), 'description' => '', 'chips' => false ) );
?>
<div class="wrap mt-10 pb-4">
	<div class="panel-card">
		<?php echo mcupr_calendar_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<section class="mt-10" aria-labelledby="upcoming-h">
		<h2 id="upcoming-h" class="mcu-title text-2xl font-bold"><?php mcupr_e( 'upcoming' ); ?></h2>
		<div class="mt-4"><?php echo mcupr_events_upcoming_html( 8 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<p class="mt-6"><a class="ev-link" href="<?php echo esc_url( add_query_arg( 'mcupr_ics', 'all', home_url( '/' ) ) ); ?>"><?php echo mcupr_icon( 'calendar', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php mcupr_e( 'subscribe_ics' ); ?></a></p>
	</section>
</div>
<?php
mcupr_slot( 'content_after' );
get_footer();
