<?php
/**
 * Single post (news). Three layouts – reader / cover / sidebar – chosen in the
 * Customizer (default) or per post (meta box "News layout").
 *
 * @package MCUPR
 */

get_header();

if ( ! mcupr_elementor_location( 'single' ) ) :
	while ( have_posts() ) :
		the_post();
		if ( mcupr_is_elementor_page() ) {
			mcupr_breadcrumb();
			the_content(); // Designed in Elementor: full width, no theme title.
			continue;
		}
		get_template_part( 'template-parts/single', mcupr_post_layout() );
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="narrow mt-12">';
			comments_template();
			echo '</div>';
		}
	endwhile;
endif;

get_footer();
