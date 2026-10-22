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
?>
<div class="tool-card tool-share print:hidden" role="group" aria-label="<?php echo esc_attr( mcupr_t( 'article_tools' ) ); ?>">
	<p class="tool-lbl"><?php mcupr_e( 'tool_share' ); ?></p>
	<div class="tool-brands"><?php mcupr_share_buttons( $url, $title ); ?></div>
	<div class="tool-tiles">
		<button type="button" class="tool-tile" data-copy-link="<?php echo esc_url( $url ); ?>" data-copied="<?php echo esc_attr( mcupr_t( 'link_copied' ) ); ?>"><?php echo mcupr_feather( 'link', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'copy_link' ); ?></span></button>
		<button type="button" class="tool-tile hidden" data-native-share data-title="<?php echo esc_attr( $title ); ?>" data-url="<?php echo esc_url( $url ); ?>"><?php echo mcupr_feather( 'share-2', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'share' ); ?></span></button>
		<button type="button" class="tool-tile" data-pdf data-pdf-target="#article-body" data-pdf-site="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-pdf-title="<?php echo esc_attr( $title ); ?>" data-pdf-url="<?php echo esc_url( $url ); ?>" data-pdf-busy="<?php echo esc_attr( mcupr_t( 'pdf_busy' ) ); ?>" data-pdf-fail="<?php echo esc_attr( mcupr_t( 'pdf_fail' ) ); ?>" data-pdf-label="<?php echo esc_attr( mcupr_t( 'pdf' ) ); ?>"><?php echo mcupr_feather( 'download', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'pdf' ); ?></span></button>
		<button type="button" class="tool-tile" data-print><?php echo mcupr_feather( 'printer', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php mcupr_e( 'print' ); ?></span></button>
	</div>
</div>
