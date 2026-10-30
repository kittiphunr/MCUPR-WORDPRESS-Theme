<?php
/** For the browser test of the breadcrumb beside the tools capsule: a post (Cover head) and a page with a very long title, and a short one.   php php/crumb-fixture.php <site> make|clean */
require rtrim( $argv[1], '/' ) . '/wp-load.php';
$long = 'ประชุมคณะกรรมการกองทุนการวิจัยและวิชาการ มจร. วิทยาเขตแพร่ พิจารณาจัดสรรงบประมาณวิจัย ประจำปีงบประมาณ 2569 ครั้งที่ 1/2569';
$slugs = array( 'e2e-crumb-cover', 'e2e-crumb-par', 'e2e-crumb-long', 'e2e-crumb-short' );
foreach ( $slugs as $s ) { foreach ( array( 'post', 'page' ) as $t ) { $p = get_page_by_path( $s, OBJECT, $t ); if ( $p ) { wp_delete_post( $p->ID, true ); } } }
$c = get_term_by( 'name', 'e2e-crumb-group', 'category' ); if ( $c ) { wp_delete_term( $c->term_id, 'category' ); }
if ( 'make' === $argv[2] ) {
	$cat = wp_insert_term( 'e2e-crumb-group', 'category' );
	$cov = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $long, 'post_name' => 'e2e-crumb-cover', 'post_content' => 'x', 'post_category' => array( $cat['term_id'] ) ) );
	update_post_meta( $cov, '_mcupr_layout', 'cover' );
	$par = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'e2e crumb parent', 'post_name' => 'e2e-crumb-par' ) );
	wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $long, 'post_name' => 'e2e-crumb-long', 'post_parent' => $par, 'post_content' => 'x' ) );
	wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Short', 'post_name' => 'e2e-crumb-short', 'post_parent' => $par, 'post_content' => 'x' ) );
	echo wp_json_encode( array( 'cover' => wp_parse_url( get_permalink( $cov ), PHP_URL_PATH ), 'long' => wp_parse_url( home_url( '/e2e-crumb-par/e2e-crumb-long/' ), PHP_URL_PATH ), 'short' => wp_parse_url( home_url( '/e2e-crumb-par/e2e-crumb-short/' ), PHP_URL_PATH ) ) );
} else {
	echo '{}';
}
