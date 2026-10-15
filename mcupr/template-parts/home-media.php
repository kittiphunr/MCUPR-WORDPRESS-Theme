<?php
/**
 * Home page: upcoming events beside the newest video, then the newest photo albums. Each part appears only when there is
 * something to show, and each can be switched off in the Customizer (MCUPR: Home page sections).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

$mcupr_events = mcupr_home_on( 'events', true ) && function_exists( 'mcupr_events_upcoming' ) ? mcupr_events_upcoming( 4 ) : array();
$mcupr_media  = mcupr_home_on( 'media', true );
$mcupr_video  = $mcupr_media && function_exists( 'mcupr_video_card' ) ? get_posts( array( 'post_type' => 'mcupr_video', 'post_status' => 'publish', 'posts_per_page' => 1, 'no_found_rows' => true, 'meta_key' => '_mcupr_video_url', 'meta_compare' => 'EXISTS' ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
$mcupr_albums = $mcupr_media && function_exists( 'mcupr_album_card' ) ? get_posts( array( 'post_type' => 'mcupr_album', 'post_status' => 'publish', 'posts_per_page' => 4, 'no_found_rows' => true ) ) : array();
$mcupr_vid    = $mcupr_video ? $mcupr_video[0] : null;
mcupr_prime_thumbs( array_merge( (array) $mcupr_albums, (array) $mcupr_video ) );
?>
<?php if ( $mcupr_events || $mcupr_vid ) : ?>
<section class="wrap mt-14" aria-label="<?php echo esc_attr( mcupr_t( 'upcoming' ) . ' / ' . mcupr_t( 'vd_title' ) ); ?>" data-reveal>
	<div class="grid items-stretch gap-x-8 gap-y-10 <?php echo $mcupr_events && $mcupr_vid ? 'min-[900px]:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]' : ''; ?>">
		<?php if ( $mcupr_events ) : ?>
			<div class="home-col">
				<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
					<h2 class="m-0 flex items-center gap-3 text-2xl font-bold"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'calendar', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'upcoming' ); ?></h2>
					<?php if ( get_post_type_archive_link( 'mcupr_event' ) ) : ?>
						<a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_event' ) ); ?>"><?php mcupr_e( 'view_all' ); ?> <span aria-hidden="true">→</span></a>
					<?php endif; ?>
				</div>
				<div class="soft-card home-ev">
					<ul class="ev-list">
						<?php
						foreach ( $mcupr_events as $mcupr_ev ) {
							mcupr_event_row( $mcupr_ev );
						}
						?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $mcupr_vid ) : ?>
			<div class="home-col">
				<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
					<h2 class="m-0 flex items-center gap-3 text-2xl font-bold"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'play', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'vd_title' ); ?></h2>
					<a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_video' ) ); ?>"><?php mcupr_e( 'view_all' ); ?> <span aria-hidden="true">→</span></a>
				</div>
				<article class="soft-card vcard">
					<div class="vid-wrap !m-0"><?php echo mcupr_video_html( mcupr_video_url( $mcupr_vid->ID ), get_the_title( $mcupr_vid ), get_post_thumbnail_id( $mcupr_vid ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="vcard-bar">
						<span class="vcard-ic" aria-hidden="true"><?php echo mcupr_icon( 'play', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<div class="min-w-0">
							<h3 class="vcard-t"><a href="<?php echo esc_url( get_permalink( $mcupr_vid ) ); ?>"><?php echo esc_html( get_the_title( $mcupr_vid ) ); ?></a></h3>
							<p class="vcard-m"><?php mcupr_date( $mcupr_vid ); ?></p>
						</div>
						<a class="vcard-go" href="<?php echo esc_url( get_permalink( $mcupr_vid ) ); ?>"><span class="sr-only"><?php echo esc_html( get_the_title( $mcupr_vid ) ); ?></span><span aria-hidden="true">→</span></a>
					</div>
				</article>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( $mcupr_albums ) : ?>
<section class="wrap mt-14" aria-labelledby="home-albums" data-reveal>
	<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
		<h2 id="home-albums" class="m-0 flex items-center gap-3 text-2xl font-bold min-[900px]:text-3xl"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'image', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php mcupr_e( 'album_title' ); ?></h2>
		<a class="ev-link" href="<?php echo esc_url( get_post_type_archive_link( 'mcupr_album' ) ); ?>"><?php mcupr_e( 'view_all' ); ?> <span aria-hidden="true">→</span></a>
	</div>
	<div class="album-grid">
		<?php
		foreach ( $mcupr_albums as $mcupr_alb ) {
			echo mcupr_album_card( $mcupr_alb ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
	</div>
</section>
<?php endif; ?>
