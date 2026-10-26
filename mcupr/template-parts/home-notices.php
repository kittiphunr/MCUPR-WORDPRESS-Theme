<?php
/**
 * Home page: the newest notices (procurement and other announcements) – a dated list with a quick download button, the
 * types as tabs, and "all notices". Shown only when a notice is published and the section is on (Customizer: MCUPR: Home
 * page sections: how many, which type).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

if ( ! mcupr_home_on( 'notices', true ) || ! function_exists( 'mcupr_notices_home' ) ) {
	return;
}
list( $mcupr_nt_posts, $mcupr_nt_term ) = mcupr_notices_home();
if ( ! $mcupr_nt_posts ) {
	return;
}
$mcupr_nt_all  = get_post_type_archive_link( 'mcupr_notice' );
$mcupr_nt_tabs = array_slice( mcupr_notice_terms(), 0, 4 );
usort( $mcupr_nt_tabs, static function ( $a, $b ) { return $b->count <=> $a->count; } );
?>
<section class="wrap mt-14" aria-labelledby="nt-home-t" data-reveal>
	<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
		<h2 id="nt-home-t" class="m-0 flex items-center gap-3 text-2xl font-bold"><span class="stat-ic" aria-hidden="true"><?php echo mcupr_icon( 'bell', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $mcupr_nt_term ? $mcupr_nt_term->name : mcupr_t( 'nt_home_title' ) ); ?></h2>
		<a class="ev-link" href="<?php echo esc_url( $mcupr_nt_term ? get_term_link( $mcupr_nt_term ) : $mcupr_nt_all ); ?>"><?php mcupr_e( 'nt_view_all' ); ?> <span aria-hidden="true">→</span></a>
	</div>
	<?php if ( ! $mcupr_nt_term && count( $mcupr_nt_tabs ) > 1 ) : ?>
		<nav class="mb-4" aria-label="<?php echo esc_attr( mcupr_t( 'nt_cats' ) ); ?>">
			<ul class="pill-tabs m-0 list-none">
				<li class="contents"><a href="<?php echo esc_url( $mcupr_nt_all ); ?>"><?php mcupr_e( 'nt_all' ); ?></a></li>
				<?php foreach ( $mcupr_nt_tabs as $mcupr_t ) : ?>
					<li class="contents"><a href="<?php echo esc_url( get_term_link( $mcupr_t ) ); ?>"><?php echo esc_html( $mcupr_t->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
	<div class="soft-card nt-home">
		<ul class="nt-home-list">
			<?php
			foreach ( $mcupr_nt_posts as $mcupr_p ) :
				$mcupr_ts    = (int) get_post_timestamp( $mcupr_p );
				$mcupr_fs    = mcupr_files( $mcupr_p->ID );
				$mcupr_first = null;
				foreach ( $mcupr_fs as $mcupr_i => $mcupr_f ) {
					if ( ! ( $mcupr_f['max'] > 0 && $mcupr_f['count'] >= $mcupr_f['max'] ) ) {
						$mcupr_first = $mcupr_i;
						break;
					}
				}
				$mcupr_cat = get_the_terms( $mcupr_p->ID, 'mcupr_notice_cat' );
				?>
				<li class="nt-home-row">
					<time class="nt-home-date" datetime="<?php echo esc_attr( gmdate( 'c', $mcupr_ts ) ); ?>"><b><?php echo esc_html( wp_date( 'j', $mcupr_ts ) ); ?></b><span><?php echo esc_html( wp_date( 'M', $mcupr_ts ) ); ?></span><span class="sr-only"> <?php echo esc_html( mcupr_date_text( $mcupr_ts, 'short' ) ); ?></span></time>
					<div class="nt-home-main">
						<a class="nt-t" href="<?php echo esc_url( get_permalink( $mcupr_p ) ); ?>"><?php echo esc_html( get_the_title( $mcupr_p ) ); ?></a>
						<?php echo mcupr_notice_state_html( $mcupr_p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="nt-home-meta">
							<?php if ( mcupr_notice_is_new( $mcupr_p ) ) : ?><span class="nt-new"><?php mcupr_e( 'nt_new' ); ?></span><?php endif; ?>
							<?php if ( $mcupr_cat && ! is_wp_error( $mcupr_cat ) ) : ?><span><?php echo esc_html( $mcupr_cat[0]->name ); ?></span><?php endif; ?>
							<?php if ( $mcupr_fs ) : ?><span><?php echo esc_html( sprintf( mcupr_t( 'nt_dl_n' ), number_format_i18n( mcupr_notice_downloads( $mcupr_p->ID ) ) ) ); ?></span><?php endif; ?>
						</span>
					</div>
					<?php if ( null !== $mcupr_first ) : ?>
						<a class="nt-home-dl" href="<?php echo esc_url( mcupr_download_url( $mcupr_p->ID, $mcupr_first ) ); ?>"><?php echo mcupr_icon( 'download', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span class="sr-only"><?php echo esc_html( mcupr_t( 'download' ) . ' – ' . get_the_title( $mcupr_p ) ); ?></span></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
