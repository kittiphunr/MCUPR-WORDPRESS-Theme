<?php
/**
 * Reading / sharing tools for a post or page.
 * Args: layout (inline|rail)
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
$url   = get_permalink();
$title = wp_strip_all_tags( get_the_title() );
$btn   = 'inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center gap-2 rounded-full border-0 bg-transparent px-4 font-semibold text-fg2 no-underline hover:bg-tint hover:text-link';
?>
<div class="no-print pill-tabs !flex items-center" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'article_tools' ) ); ?>">
	<?php mcupr_share_buttons( $url, $title ); ?>
	<button type="button" class="<?php echo esc_attr( $btn ); ?>" data-copy-link="<?php echo esc_url( $url ); ?>" data-copied="<?php echo esc_attr( mcupr_t( 'link_copied' ) ); ?>"><?php mcupr_e( 'copy_link' ); ?></button>
	<button type="button" class="<?php echo esc_attr( $btn ); ?> hidden" data-native-share data-title="<?php echo esc_attr( $title ); ?>" data-url="<?php echo esc_url( $url ); ?>"><?php mcupr_e( 'share' ); ?></button>
	<button type="button" class="<?php echo esc_attr( $btn ); ?>" data-print><?php mcupr_e( 'print' ); ?></button>
</div>
