<?php
/**
 * Per-post "News layout" override (reader / cover / sidebar).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_layout_meta_box() {
	add_meta_box( 'mcupr_layout', __( 'News layout', 'mcupr' ), 'mcupr_layout_meta_box_html', 'post', 'side', 'default' );
}
add_action( 'add_meta_boxes', 'mcupr_layout_meta_box' );

function mcupr_layout_meta_box_html( $post ) {
	wp_nonce_field( 'mcupr_layout_save', 'mcupr_layout_nonce' );
	$cur = get_post_meta( $post->ID, '_mcupr_layout', true );
	echo '<p><label class="screen-reader-text" for="mcupr_layout_field">' . esc_html__( 'News layout', 'mcupr' ) . '</label><select name="mcupr_layout" id="mcupr_layout_field" class="widefat">';
	printf( '<option value="">%s</option>', esc_html__( 'Site default (Customizer)', 'mcupr' ) );
	foreach ( mcupr_layouts() as $key => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $cur, $key, false ), esc_html( $label ) );
	}
	echo '</select></p>';
}

function mcupr_layout_meta_save( $post_id ) {
	if ( ! mcupr_can_save_box( $post_id, 'mcupr_layout_nonce', 'mcupr_layout_save' ) ) {
		return;
	}
	$val = isset( $_POST['mcupr_layout'] ) ? sanitize_key( wp_unslash( $_POST['mcupr_layout'] ) ) : '';
	if ( $val && isset( mcupr_layouts()[ $val ] ) ) {
		update_post_meta( $post_id, '_mcupr_layout', $val );
	} else {
		delete_post_meta( $post_id, '_mcupr_layout' );
	}
}
add_action( 'save_post', 'mcupr_layout_meta_save' );
