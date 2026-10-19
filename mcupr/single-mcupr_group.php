<?php
/**
 * The landing page of a work group of an office: /office/<office>/group/<group>/ (Plain permalinks: ?post_type=mcupr_group&p=<id>).
 * The office header is printed by header.php (mcupr_office_chrome()); the page itself by mcupr_group_render() (inc/groups-front.php).
 *
 * @package MCUPR
 */

get_header();
while ( have_posts() ) {
	the_post();
	mcupr_group_render( get_the_ID() );
}
mcupr_slot( 'content_after' );
get_footer();
