<?php
/**
 * Category / tag / date archives.
 *
 * @package MCUPR
 */

get_header();

	get_template_part( 'template-parts/band', null, array( 'title' => wp_kses_post( get_the_archive_title() ), 'description' => get_the_archive_description(), 'chips' => false ) );
	?>
<?php
get_template_part( 'template-parts/browser' );
get_footer();
