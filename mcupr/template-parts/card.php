<?php
/**
 * News card.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$part_lang = mcupr_part_lang_attr();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="card__thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( 'mcupr-card', array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>
	<div class="card__body"<?php echo $part_lang; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<p class="card__meta"><?php mcupr_date(); ?></p>
		<h3 class="card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<div class="card__excerpt"><?php the_excerpt(); ?></div>
	</div>
</article>
