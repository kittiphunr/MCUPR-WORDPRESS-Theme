<?php
/**
 * Fallback / blog index (news).
 *
 * @package MCUPR
 */

get_header();

	get_template_part( 'template-parts/band', null, array( 'title' => is_home() && ! is_front_page() ? esc_html( single_post_title( '', false ) ) : esc_html( mcupr_t( 'news_events' ) ), 'description' => '', 'chips' => false ) );
	?>
<?php
get_template_part( 'template-parts/browser' );
get_footer();
