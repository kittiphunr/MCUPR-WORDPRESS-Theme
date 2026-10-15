<?php
/**
 * Profile of a person (staff hub): photo, position, unit, contact, biography, education, expertise, works and
 * royal decorations. Everything the person (or the hub) switched off is not printed.
 *
 * @package MCUPR
 */

get_header();

while ( have_posts() ) :
	the_post();
	$id    = get_the_ID();
	$o     = mcupr_staff_opts();
	$role  = mcupr_person_role( $id );
	$unit  = (int) get_post_meta( $id, '_mcupr_person_unit', true );
	$rank  = (string) get_post_meta( $id, '_mcupr_person_rank', true );
	$type  = (string) get_post_meta( $id, '_mcupr_person_type', true );
	$dept  = (string) get_post_meta( $id, '_mcupr_person_dept', true );
	$mail  = mcupr_person_shows( $id, 'email' ) ? (string) get_post_meta( $id, '_mcupr_person_email', true ) : '';
	$tel   = mcupr_person_shows( $id, 'tel' ) ? (string) get_post_meta( $id, '_mcupr_person_tel', true ) : '';
	$off   = mcupr_person_shows( $id, 'office' ) ? (string) get_post_meta( $id, '_mcupr_person_office', true ) : '';
	$sch   = mcupr_person_shows( $id, 'links' ) ? (string) get_post_meta( $id, '_mcupr_person_scholar', true ) : '';
	$web   = mcupr_person_shows( $id, 'links' ) ? (string) get_post_meta( $id, '_mcupr_person_url', true ) : '';
	$exp   = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $id, '_mcupr_person_expertise', true ) ) ) );
	$edu   = ( ! empty( $o['sections']['edu'] ) && mcupr_person_shows( $id, 'edu' ) ) ? mcupr_person_rows( $id, 'edu' ) : array();
	$works = ( ! empty( $o['sections']['works'] ) && mcupr_person_shows( $id, 'works' ) ) ? mcupr_person_rows( $id, 'works' ) : array();
	$hons  = ( ! empty( $o['sections']['honors'] ) && mcupr_person_shows( $id, 'honors' ) ) ? mcupr_person_honors( $id ) : array();
	$bio   = ( ! empty( $o['sections']['bio'] ) && mcupr_person_shows( $id, 'bio' ) ) ? trim( get_the_content() ) : '';
	$unit_title = $unit ? get_the_title( $unit ) : $dept;
	$by_type = array();
	foreach ( $works as $w ) {
		$by_type[ isset( mcupr_person_work_types()[ $w['type'] ] ) ? $w['type'] : 'other' ][] = $w;
	}
	$contact = $mail || $tel || $off || $sch || $web;
	?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'pb-4' ); ?>>
	<header class="mcu-wheel bg-tint py-10"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="wrap">
			<?php mcupr_breadcrumb( false ); ?>
			<h1 class="mt-4 text-3xl font-bold leading-tight min-[900px]:text-4xl"><?php the_title(); ?></h1>
			<?php if ( '' !== $role ) : ?><p class="mt-2 mb-0 text-lg text-muted"><?php echo esc_html( $role ); ?></p><?php endif; ?>
		</div>
	</header>

	<div class="wrap mt-8">
		<div class="profile">
			<aside class="profile-side" aria-label="<?php echo esc_attr( mcupr_t( 'st_contact' ) ); ?>">
				<?php if ( has_post_thumbnail() ) : ?>
					<?php the_post_thumbnail( 'large', array( 'alt' => get_the_title(), 'class' => 'profile-ph', 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
				<?php else : ?>
					<span class="profile-ph profile-in" aria-hidden="true"><?php echo esc_html( mb_substr( preg_replace( '/^[^\p{L}\p{N}]+/u', '', preg_replace( '/^(ศ\.|รศ\.|ผศ\.|ดร\.|นาย|นางสาว|นาง|พระ|Dr\.|Prof\.|Mr\.|Ms\.|Mrs\.)+\s*/u', '', get_the_title() ) ), 0, 1 ) ); ?></span>
				<?php endif; ?>
				<dl class="profile-facts">
					<?php if ( '' !== $rank ) : ?><div><dt><?php mcupr_e( 'st_rank' ); ?></dt><dd><?php echo esc_html( $rank ); ?></dd></div><?php endif; ?>
					<?php if ( '' !== $unit_title ) : ?><div><dt><?php mcupr_e( 'st_unit' ); ?></dt><dd><?php echo esc_html( $unit_title ); ?><?php echo get_post_meta( $id, '_mcupr_person_head', true ) ? ' <span class="profile-head">' . esc_html( mcupr_t( 'st_head' ) ) . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div><?php endif; ?>
					<?php if ( '' !== $type ) : ?><div><dt><?php mcupr_e( 'st_type' ); ?></dt><dd><?php echo esc_html( $type ); ?></dd></div><?php endif; ?>
					<?php if ( '' !== $off ) : ?><div><dt><?php mcupr_e( 'st_office' ); ?></dt><dd><?php echo esc_html( $off ); ?></dd></div><?php endif; ?>
				</dl>
				<?php if ( $contact ) : ?>
					<ul class="profile-contact">
						<?php if ( $mail ) : ?><li><a href="mailto:<?php echo esc_attr( $mail ); ?>"><?php echo mcupr_icon( 'mail', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $mail ); ?></span></a></li><?php endif; ?>
						<?php if ( $tel ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ); ?>"><?php echo mcupr_icon( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $tel ); ?></span></a></li><?php endif; ?>
						<?php if ( $sch ) : ?><li><a href="<?php echo esc_url( $sch ); ?>" target="_blank" rel="noopener noreferrer"><?php echo mcupr_icon( 'link', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $sch ) ) ); ?></span></a></li><?php endif; ?>
						<?php if ( $web ) : ?><li><a href="<?php echo esc_url( $web ); ?>" target="_blank" rel="noopener noreferrer"><?php echo mcupr_icon( 'globe', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $web ) ) ); ?></span></a></li><?php endif; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $hons ) : ?>
					<p class="profile-medals" aria-hidden="true"><?php foreach ( array_slice( $hons, 0, 4 ) as $h ) { echo '<span title="' . esc_attr( mcupr_honor_name( $h['item'] ) ) . '">' . mcupr_honor_medal( $h['item'], 40, 'medal-s' ) . '</span>'; } // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				<?php endif; ?>
			</aside>

			<div class="profile-main"<?php echo mcupr_part_lang_attr(); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php if ( '' !== $bio ) : ?>
					<section class="profile-sec" aria-labelledby="pf-bio"><h2 id="pf-bio"><?php mcupr_e( 'st_about' ); ?></h2><div class="reader"><?php the_content(); ?></div></section>
				<?php endif; ?>
				<?php if ( $edu ) : ?>
					<section class="profile-sec" aria-labelledby="pf-edu"><h2 id="pf-edu"><?php mcupr_e( 'st_edu' ); ?></h2>
						<ol class="profile-edu">
							<?php foreach ( $edu as $e ) : ?>
								<li><span class="pe-y"><?php echo esc_html( mcupr_year_text( mcupr_year_norm( $e['year'] ) ) ); ?></span><span class="pe-t"><b><?php echo esc_html( $e['degree'] ); ?></b><?php echo '' !== $e['level'] ? ' <em>' . esc_html( $e['level'] ) . '</em>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo '' !== $e['inst'] ? '<small>' . esc_html( $e['inst'] ) . '</small>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>
				<?php if ( $exp && ! empty( $o['sections']['expertise'] ) ) : ?>
					<section class="profile-sec" aria-labelledby="pf-exp"><h2 id="pf-exp"><?php mcupr_e( 'st_expertise' ); ?></h2><ul class="profile-chips"><?php foreach ( $exp as $x ) { echo '<li>' . esc_html( $x ) . '</li>'; } ?></ul></section>
				<?php endif; ?>
				<?php if ( $hons ) : ?>
					<section class="profile-sec" aria-labelledby="pf-hon"><h2 id="pf-hon"><?php mcupr_e( 'st_honors' ); ?></h2>
						<ul class="profile-honors">
							<?php foreach ( $hons as $h ) : ?>
								<li><?php echo mcupr_honor_medal( $h['item'], 64, 'medal' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<div><b><?php echo esc_html( mcupr_honor_name( $h['item'] ) ); ?></b><span class="ph-code"><?php echo esc_html( $h['item']['code'] ); ?></span>
										<span class="ph-meta"><?php if ( $h['year'] ) { echo esc_html( mcupr_t( 'st_honor_year' ) . ' ' . mcupr_year_text( $h['year'] ) ); } ?><?php if ( '' !== $h['gazette'] ) { echo ' · ' . esc_html( mcupr_t( 'st_gazette' ) . ' ' . $h['gazette'] ); } ?></span>
										<?php if ( '' !== $h['note'] ) : ?><span class="ph-meta"><?php echo esc_html( $h['note'] ); ?></span><?php endif; ?></div></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
				<?php if ( $by_type ) : ?>
					<section class="profile-sec" aria-labelledby="pf-works"><h2 id="pf-works"><?php mcupr_e( 'st_works' ); ?></h2>
						<?php foreach ( mcupr_person_work_types() as $tk => $tl ) : ?>
							<?php if ( empty( $by_type[ $tk ] ) ) { continue; } ?>
							<h3 class="profile-wt"><?php mcupr_e( $tl ); ?></h3>
							<?php usort( $by_type[ $tk ], static function ( $a, $b ) { return mcupr_year_norm( $b['year'] ) <=> mcupr_year_norm( $a['year'] ); } ); ?>
							<ul class="profile-works">
								<?php foreach ( $by_type[ $tk ] as $w ) : ?>
									<li><span class="pw-y"><?php echo esc_html( mcupr_year_text( mcupr_year_norm( $w['year'] ) ) ); ?></span><span><?php echo '' !== $w['url'] ? '<a href="' . esc_url( $w['url'] ) . '" rel="noopener">' . esc_html( $w['title'] ) . '</a>' : esc_html( $w['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>
				<?php $mcupr_back = get_page_by_path( 'executives' ); ?>
				<p class="mt-8"><a class="ev-link" href="<?php echo esc_url( $mcupr_back ? get_permalink( $mcupr_back ) : home_url( '/' ) ); ?>">‹ <?php mcupr_e( 'st_back' ); ?></a></p>
			</div>
		</div>
	</div>
</article>
	<?php
endwhile;
get_footer();
