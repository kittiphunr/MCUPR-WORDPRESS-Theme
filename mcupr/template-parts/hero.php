<?php
/**
 * Home hero: one slide (photo, headline, text, buttons) or a slider of up to three slides, plus the quick links.
 * Args: hero_id, hero_img, line1, line2.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$hero_id  = isset( $args['hero_id'] ) ? (int) $args['hero_id'] : 0;
$hero_img = ! empty( $args['hero_img'] );
$line1    = isset( $args['line1'] ) ? $args['line1'] : '';
$line2    = isset( $args['line2'] ) ? $args['line2'] : '';
$hero     = mcupr_hero_options();
$left     = 'left' === $hero['align'];
$extra    = mcupr_hero_extra_slides();
$multi    = $hero_img && $extra;
$total    = 1 + count( $extra );
$pos      = 'center' === $hero['focus'] ? 'center' : $hero['focus'];
$img_attr = static function ( $first ) use ( $pos ) {
	return array( 'class' => 'noimg:hidden absolute inset-0 -z-20 size-full object-cover' . ( $first ? '' : ' hero-bg' ), 'style' => 'object-position:' . $pos, 'alt' => '', 'loading' => $first ? 'eager' : 'lazy', 'fetchpriority' => $first ? 'high' : 'low', 'decoding' => 'async', 'sizes' => '(min-width: 1260px) 1200px, 100vw' );
};
// slide 1 = the settings of the hero; slides 2-3 = their own text
$slides = array( array( 'eyebrow' => $hero['eyebrow'], 'l1' => $line1, 'l2' => $line2, 'sub' => $hero['sub'], 'btn1' => $hero['btn1'], 'btn2' => $hero['btn2'] ) );
foreach ( $extra as $e ) {
	$slides[] = array( 'eyebrow' => '', 'l1' => '', 'l2' => $e['title'], 'sub' => $e['sub'], 'btn1' => array( $e['btn'], $e['url'] ), 'btn2' => array( '', '' ) );
}
?>
<div class="wrap hero-wrap mt-6"><section class="hero hero-<?php echo esc_attr( $hero['height'] ); ?> relative isolate flex flex-col overflow-hidden rounded-3xl px-3 py-16 min-[400px]:px-6 <?php echo $left ? 'items-start justify-center text-left min-[900px]:px-14' : 'items-center justify-center text-center'; ?> <?php echo $hero_img ? 'bg-neutral-800 text-white' : 'bg-gradient-to-br from-primary to-brand2 text-on-primary'; ?>"<?php echo $multi ? ' data-hero data-auto="' . ( get_theme_mod( 'mcupr_hero_auto', false ) ? '1' : '0' ) . '" role="group" aria-roledescription="carousel" aria-label="' . esc_attr( mcupr_t( 'hero_label' ) ) . '"' : ''; ?>>
	<?php if ( $hero_img ) : ?>
		<?php
		// The LCP element: right size (srcset), eager, high priority, decoded off the main thread.
		echo wp_get_attachment_image( $hero_id, 'mcupr-hero', false, $img_attr( true ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $extra as $e ) {
			echo wp_get_attachment_image( $e['image'], 'mcupr-hero', false, $img_attr( false ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		<span class="absolute inset-0 -z-10" style="background:rgb(0 0 0 / <?php echo esc_attr( $hero['overlay'] / 100 ); ?>)" aria-hidden="true"></span>
	<?php endif; ?>

	<?php if ( $multi ) : // the page heading stays in the accessibility tree whichever slide is showing ?>
		<h1 class="sr-only"><?php echo esc_html( trim( $line1 . ' ' . $line2 ) ); ?></h1>
	<?php endif; ?>
	<div class="hero-stage <?php echo $left ? 'items-start' : 'items-center'; ?>">
		<?php foreach ( $slides as $i => $sl ) : ?>
			<div class="hero-slide <?php echo $left ? 'items-start' : 'items-center'; ?><?php echo 0 === $i ? ' is-active' : ''; ?>" data-slide<?php echo $multi ? ' role="group" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' / ' . $total ) . '"' : ''; ?>>
				<?php if ( '' !== $sl['eyebrow'] ) : ?><p class="hero-eyebrow animate-fade-up"><?php echo esc_html( $sl['eyebrow'] ); ?></p><?php endif; ?>
				<?php $tag = ( 0 === $i && ! $multi ) ? 'h1' : 'p'; ?>
				<<?php echo $tag; ?><?php echo ( $multi && 0 === $i ) ? ' aria-hidden="true"' : ''; ?> class="animate-fade-up leading-tight [text-shadow:0_2px_12px_rgb(0_0_0/.5)] max-w-4xl <?php echo ( '' !== $sl['sub'] || $sl['btn1'][0] ) ? 'mb-5' : 'mb-10'; ?>">
					<?php if ( $sl['l1'] ) : ?><span class="block text-2xl font-light min-[400px]:text-3xl min-[900px]:text-5xl"><?php echo esc_html( $sl['l1'] ); ?></span><?php endif; ?>
					<?php if ( $sl['l2'] ) : ?><span class="block text-3xl font-bold min-[400px]:text-4xl min-[900px]:text-6xl"><?php echo esc_html( $sl['l2'] ); ?></span><?php endif; ?>
				</<?php echo $tag; ?>>
				<?php if ( '' !== $sl['sub'] ) : ?><p class="hero-sub animate-fade-up"><?php echo esc_html( $sl['sub'] ); ?></p><?php endif; ?>
				<?php if ( ( $sl['btn1'][0] && $sl['btn1'][1] ) || ( $sl['btn2'][0] && $sl['btn2'][1] ) ) : ?>
					<p class="hero-btns animate-fade-up">
						<?php if ( $sl['btn1'][0] && $sl['btn1'][1] ) : ?><a class="hero-btn hero-btn-1" href="<?php echo esc_url( $sl['btn1'][1] ); ?>"><?php echo esc_html( $sl['btn1'][0] ); ?></a><?php endif; ?>
						<?php if ( $sl['btn2'][0] && $sl['btn2'][1] ) : ?><a class="hero-btn hero-btn-2" href="<?php echo esc_url( $sl['btn2'][1] ); ?>"><?php echo esc_html( $sl['btn2'][0] ); ?></a><?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $multi ) : ?>
		<div class="hero-ctl" data-hero-ctl hidden>
			<button type="button" class="hero-arrow" data-hero-prev aria-label="<?php echo esc_attr( mcupr_t( 'prev' ) ); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 18l-6-6 6-6"/></svg></button>
			<ul class="hero-dots">
				<?php for ( $d = 0; $d < $total; $d++ ) : ?>
					<li><button type="button" data-hero-dot="<?php echo (int) $d; ?>" aria-label="<?php echo esc_attr( sprintf( mcupr_t( 'hero_goto' ), $d + 1 ) ); ?>"<?php echo 0 === $d ? ' aria-current="true"' : ''; ?>></button></li>
				<?php endfor; ?>
			</ul>
			<button type="button" class="hero-arrow" data-hero-next aria-label="<?php echo esc_attr( mcupr_t( 'next' ) ); ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 18l6-6-6-6"/></svg></button>
			<button type="button" class="hero-arrow hero-pause" data-hero-pause hidden aria-pressed="false" aria-label="<?php echo esc_attr( mcupr_t( 'hero_pause' ) ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/></svg></button>
		</div>
	<?php endif; ?>

	<?php
	// Quick links: the "Quick links" menu; until one is assigned, the first items of the main menu.
	$quick_args = has_nav_menu( 'quick' ) ? array( 'theme_location' => 'quick' ) : ( has_nav_menu( 'primary_' . mcupr_lang() ) ? array( 'theme_location' => 'primary_' . mcupr_lang() ) : ( has_nav_menu( 'primary_' . mcupr_default_lang() ) ? array( 'theme_location' => 'primary_' . mcupr_default_lang() ) : array() ) );
	if ( $quick_args && $hero['quick'] ) :
		$quick_html = wp_nav_menu( $quick_args + array( 'container' => false, 'menu_class' => 'hero-menu', 'depth' => 1, 'echo' => false ) );
		$personas   = $hero['persona'] ? mcupr_personas_in( (string) $quick_html ) : array();
		?>
		<div class="hero-quick mt-5 w-full max-w-6xl animate-fade-up [animation-delay:.2s]"<?php echo $personas ? ' data-persona="' . esc_attr( implode( ',', $personas ) ) . '" data-persona-shown="' . esc_attr( mcupr_t( 'persona_shown' ) ) . '"' : ''; ?>>
			<?php if ( $personas ) : ?>
				<div class="persona" hidden data-persona-picker>
					<span class="persona-iam" id="persona-iam"><?php mcupr_e( 'persona_iam' ); ?></span>
					<span class="persona-wrap">
					<button type="button" class="persona-btn" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="persona-iam persona-cur" title="<?php echo esc_attr( mcupr_t( 'persona_pick' ) ); ?>">
						<span id="persona-cur" data-persona-cur><?php echo esc_html( mcupr_t( mcupr_personas()[ $personas[0] ] ) ); ?></span>
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
					</button>
					<span class="sr-only" role="status" aria-live="polite" data-persona-live></span>
					<ul class="persona-list" role="listbox" aria-labelledby="persona-iam" hidden>
						<?php foreach ( $personas as $k ) : ?>
							<li role="option" tabindex="-1" data-persona-opt="<?php echo esc_attr( $k ); ?>" aria-selected="false"><?php echo esc_html( mcupr_t( mcupr_personas()[ $k ] ) ); ?></li>
						<?php endforeach; ?>
					</ul>
					</span>
				</div>
			<?php endif; ?>
			<nav aria-label="<?php echo esc_attr( mcupr_t( 'quick_links' ) ); ?>">
				<?php echo $quick_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by wp_nav_menu ?>
			</nav>
		</div>
	<?php endif; ?>
</section></div>
