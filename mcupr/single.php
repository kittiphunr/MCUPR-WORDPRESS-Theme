<?php
/**
 * Single post (news). Three layouts – reader / cover / sidebar – chosen in the
 * Customizer (default) or per post (meta box "News layout").
 *
 * @package MCUPR
 */

get_header();

	while ( have_posts() ) :
		the_post();
		get_template_part( 'template-parts/single', mcupr_post_layout() );
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="narrow mt-12">';
			comments_template();
			echo '</div>';
		}
	endwhile;
mcupr_slot( 'content_after' );
get_footer();
