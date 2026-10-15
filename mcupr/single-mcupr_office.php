<?php
/**
 * An office's own pages: its home and sections (news, events, people, albums, videos, contact).
 * The office header and tabs are printed by header.php (mcupr_office_chrome()).
 *
 * @package MCUPR
 */

get_header();
while ( have_posts() ) {
	the_post();
	mcupr_office_render( get_the_ID(), mcupr_office_section() );
}
mcupr_slot( 'content_after' );
get_footer();
