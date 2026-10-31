<?php
/**
 * One compact bar for a page: listen (with its speed), print, download as PDF, and the number of views.
 * Args: target (CSS selector of the text), title (read first), views (bool, default true).
 * Behaviour: assets/js/listen.js (listen), assets/js/main.js + assets/js/pdf-export.js (print, PDF).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$target = isset( $args['target'] ) ? $args['target'] : '#article-body';
$title  = isset( $args['title'] ) ? $args['title'] : wp_strip_all_tags( get_the_title() );
$listen = (bool) get_theme_mod( 'mcupr_listen', true );
$views  = ! isset( $args['views'] ) || $args['views'] ? mcupr_views_html() : '';
$attrs  = $listen ? ' data-listen data-target="' . esc_attr( $target ) . '" data-title="' . esc_attr( $title ) . '"'
	. ' data-t-speaking="' . esc_attr( mcupr_t( 'speaking' ) ) . '" data-t-paused="' . esc_attr( mcupr_t( 'pause' ) ) . '" data-t-done="' . esc_attr( mcupr_t( 'done_reading' ) ) . '"'
	. ' data-t-unsupported="' . esc_attr( mcupr_t( 'tts_unsupported' ) ) . '" data-t-novoice="' . esc_attr( mcupr_t( 'tts_novoice' ) ) . '" data-t-pause="' . esc_attr( mcupr_t( 'pause' ) ) . '" data-t-resume="' . esc_attr( mcupr_t( 'resume' ) ) . '" data-t-play="' . esc_attr( mcupr_t( 'listen_short' ) ) . '"' : '';
?>
<div class="tool-bar no-print" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'article_tools' ) ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( $listen ) : ?>
		<div class="tool-lg">
			<button type="button" class="tool-play" data-listen-play>
				<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 4l14 8-14 8z" fill="currentColor"/></svg>
				<span data-listen-play-label><?php mcupr_e( 'listen_short' ); ?></span>
			</button>
			<button type="button" class="tool-stop" data-listen-stop hidden>
				<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="5" width="14" height="14" rx="1.5" fill="currentColor"/></svg>
				<span><?php mcupr_e( 'stop' ); ?></span>
			</button>
			<div class="speed" role="radiogroup" aria-labelledby="speed-label" data-listen-rates>
				<span id="speed-label" class="speed-t sr-only"><?php mcupr_e( 'speed' ); ?></span>
				<span class="speed-g">
					<?php foreach ( array( '0.75', '1', '1.25', '1.5' ) as $r ) : ?>
						<label><input class="peer sr-only" type="radio" name="listen-rate" value="<?php echo esc_attr( $r ); ?>" data-listen-rate<?php echo '1' === $r ? ' checked' : ''; ?>><span class="speed-b"><?php echo esc_html( $r ); ?>×</span></label>
					<?php endforeach; ?>
				</span>
			</div>
		</div>
		<i class="tool-sep" aria-hidden="true"></i>
	<?php endif; ?>
	<div class="tool-ag">
		<button type="button" class="tool-act" data-print><?php echo mcupr_feather( 'printer', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'print' ); ?></span></button>
		<?php echo mcupr_download_menu( 'act', $target, $title, get_permalink() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<?php if ( '' !== $views ) : ?>
		<span class="tool-views"><?php echo $views; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	<?php endif; ?>
	<p class="tool-status" role="status" data-listen-status></p>
</div>
