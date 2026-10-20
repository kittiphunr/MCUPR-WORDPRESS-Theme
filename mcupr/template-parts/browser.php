<?php
/**
 * Finder-style news listing: source list (category zones + tags) · toolbar with view switcher · items.
 * See inc/browser.php.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap mt-10">
	<div class="fb" data-fb data-view="grid">
		<?php echo mcupr_browser_sidebar(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<section class="fb-main" aria-label="<?php echo esc_attr( mcupr_t( 'news_label' ) ); ?>">
			<?php echo mcupr_browser_toolbar(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo mcupr_browser_folders(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( have_posts() ) : ?>
				<div class="fb-items" data-view="grid" data-fb-items data-reveal-group>
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'template-parts/card', null, array( 'heading' => 'h2' ) );
					endwhile;
					?>
				</div>
				<?php mcupr_pagination(); ?>
			<?php else : ?>
				<?php mcupr_state_card( 'search', mcupr_t( 'no_results' ), mcupr_t( 'no_results_tip' ), '', 'h2' ); ?>
			<?php endif; ?>
			<?php echo mcupr_browser_path(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>
	</div>
</div>
<script>(function(){try{var v=localStorage.getItem('mcupr_view');if(v!=='list'&&v!=='gallery'){return;}var r=document.querySelector('[data-fb]');if(r){r.setAttribute('data-view',v);}var i=document.querySelector('[data-fb-items]');if(i){i.setAttribute('data-view',v);}var b=document.querySelectorAll('[data-fb-view]');for(var k=0;k<b.length;k++){b[k].setAttribute('aria-checked',b[k].getAttribute('data-fb-view')===v?'true':'false');}}catch(e){}})();</script>
