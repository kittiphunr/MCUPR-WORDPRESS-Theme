<?php
/**
 * "Listen to this" text-to-speech controls (browser speech synthesis – no plugin, no external service).
 * Args: target (CSS selector of the text to read), title (optional text read first).
 * Behaviour: assets/js/listen.js
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
if ( ! get_theme_mod( 'mcupr_listen', true ) ) {
	return;
}
$target = isset( $args['target'] ) ? $args['target'] : '#article-body';
$title  = isset( $args['title'] ) ? $args['title'] : '';
$btn    = 'inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-full border-2 border-primary bg-transparent px-4 py-1 font-semibold text-link';
?>
<section class="no-print rounded-2xl border border-line bg-tint p-3 contrast:border-2" data-listen data-target="<?php echo esc_attr( $target ); ?>" data-title="<?php echo esc_attr( $title ); ?>" aria-label="<?php echo esc_attr( mcupr_t( 'listen' ) ); ?>"
	data-t-speaking="<?php echo esc_attr( mcupr_t( 'speaking' ) ); ?>" data-t-paused="<?php echo esc_attr( mcupr_t( 'pause' ) ); ?>" data-t-done="<?php echo esc_attr( mcupr_t( 'done_reading' ) ); ?>"
	data-t-unsupported="<?php echo esc_attr( mcupr_t( 'tts_unsupported' ) ); ?>" data-t-novoice="<?php echo esc_attr( mcupr_t( 'tts_novoice' ) ); ?>" data-t-pause="<?php echo esc_attr( mcupr_t( 'pause' ) ); ?>" data-t-resume="<?php echo esc_attr( mcupr_t( 'resume' ) ); ?>" data-t-play="<?php echo esc_attr( mcupr_t( 'listen' ) ); ?>">
	<div class="flex flex-wrap items-center gap-2">
		<button type="button" class="<?php echo esc_attr( $btn ); ?> !bg-primary !text-on-primary" data-listen-play>
			<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3l15 9-15 9z" fill="currentColor"/></svg>
			<span data-listen-play-label><?php mcupr_e( 'listen' ); ?></span>
		</button>
		<button type="button" class="<?php echo esc_attr( $btn ); ?>" data-listen-stop hidden>
			<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="5" y="5" width="14" height="14" rx="1.5" fill="currentColor"/></svg>
			<span><?php mcupr_e( 'stop' ); ?></span>
		</button>
		<div class="speed ml-auto" role="radiogroup" aria-labelledby="speed-label" data-listen-rates>
			<span id="speed-label" class="speed-t"><?php mcupr_e( 'speed' ); ?></span>
			<span class="speed-g">
				<?php foreach ( array( '0.75', '1', '1.25', '1.5' ) as $r ) : ?>
					<label><input class="peer sr-only" type="radio" name="listen-rate" value="<?php echo esc_attr( $r ); ?>" data-listen-rate<?php echo '1' === $r ? ' checked' : ''; ?>><span class="speed-b"><?php echo esc_html( $r ); ?>×</span></label>
				<?php endforeach; ?>
			</span>
		</div>
	</div>
	<p class="m-0 mt-2 text-sm text-muted empty:hidden" role="status" data-listen-status></p>
</section>
