<?php
/**
 * Share bar at the end of a news article: Facebook and LINE.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="share-bar no-print" role="group" aria-labelledby="share-bar-title">
	<h2 id="share-bar-title" class="share-bar-t"><?php mcupr_e( 'share_this' ); ?></h2>
	<div class="share-bar-b"><?php mcupr_share_buttons( get_permalink(), wp_strip_all_tags( get_the_title() ) ); ?></div>
</div>
