<?php
/**
 * Per-post translations (title / excerpt / content) stored as post meta.
 * The post's own fields are the default-language version.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_translatable_post_types() {
	return apply_filters( 'mcupr_translatable_post_types', array( 'post', 'page' ) );
}

function mcupr_tr_meta( $post_id, $lang, $field ) {
	return (string) get_post_meta( $post_id, "_mcupr_{$lang}_{$field}", true );
}

/** Language actually shown for this post (falls back to default if no translation). */
function mcupr_post_lang( $post_id = 0 ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$lang    = mcupr_lang();
	$default = mcupr_default_lang();
	if ( $lang === $default || ! $post_id ) {
		return $default;
	}
	$has = mcupr_tr_meta( $post_id, $lang, 'title' ) || mcupr_tr_meta( $post_id, $lang, 'content' );
	return $has ? $lang : $default;
}

function mcupr_is_translated( $post_id = 0 ) {
	return mcupr_post_lang( $post_id ) === mcupr_lang();
}

/* ---------------------------------------------------------------- Front end */

function mcupr_filter_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! $post_id ) {
		return $title;
	}
	$lang = mcupr_lang();
	if ( $lang === mcupr_default_lang() ) {
		return $title;
	}
	$tr = mcupr_tr_meta( $post_id, $lang, 'title' );
	return '' !== $tr ? $tr : $title;
}
add_filter( 'the_title', 'mcupr_filter_title', 10, 2 );

function mcupr_filter_document_title( $parts ) {
	if ( is_singular() && ! is_admin() ) {
		$tr = mcupr_tr_meta( get_queried_object_id(), mcupr_lang(), 'title' );
		if ( $tr && mcupr_lang() !== mcupr_default_lang() ) {
			$parts['title'] = $tr;
		}
	}
	if ( ! empty( $parts['site'] ) ) {
		$parts['site'] = mcupr_org_name();
	}
	return $parts;
}
add_filter( 'document_title_parts', 'mcupr_filter_document_title' );

function mcupr_filter_content( $content ) {
	if ( is_admin() || ! in_the_loop() ) {
		return $content;
	}
	$lang = mcupr_lang();
	if ( $lang === mcupr_default_lang() ) {
		return $content;
	}
	$tr = mcupr_tr_meta( get_the_ID(), $lang, 'content' );
	return '' !== $tr ? $tr : $content;
}
add_filter( 'the_content', 'mcupr_filter_content', 1 );

function mcupr_filter_excerpt( $excerpt, $post = null ) {
	$post = get_post( $post );
	if ( is_admin() || ! $post ) {
		return $excerpt;
	}
	$lang = mcupr_lang();
	if ( $lang === mcupr_default_lang() ) {
		return $excerpt;
	}
	$tr = mcupr_tr_meta( $post->ID, $lang, 'excerpt' );
	if ( '' !== $tr ) {
		return $tr;
	}
	$body = mcupr_tr_meta( $post->ID, $lang, 'content' );
	return '' !== $body ? wp_trim_words( wp_strip_all_tags( strip_shortcodes( $body ) ), 40, '…' ) : $excerpt;
}
add_filter( 'get_the_excerpt', 'mcupr_filter_excerpt', 10, 2 );

/* -------------------------------------------------------------------- Admin */

function mcupr_add_meta_box() {
	foreach ( mcupr_translatable_post_types() as $type ) {
		add_meta_box( 'mcupr_translations', __( 'Translations (EN / 中文 / 日本語 / ไทย)', 'mcupr' ), 'mcupr_meta_box', $type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'mcupr_add_meta_box' );

function mcupr_meta_box( $post ) {
	wp_nonce_field( 'mcupr_tr_save', 'mcupr_tr_nonce' );
	$default = mcupr_default_lang();
	echo '<p class="description">' . esc_html__( 'The main editor holds the default-language version. Fill in the other languages below; empty fields fall back to the default language.', 'mcupr' ) . '</p>';
	foreach ( mcupr_languages() as $code => $l ) {
		if ( $code === $default ) {
			continue;
		}
		echo '<fieldset style="border:1px solid #c3c4c7;padding:12px;margin:12px 0"><legend><strong>' . esc_html( $l['label'] . ' (' . $l['name'] . ')' ) . '</strong></legend>';
		printf(
			'<p><label>%1$s<br><input type="text" class="widefat" name="mcupr_tr[%2$s][title]" value="%3$s"></label></p>',
			esc_html__( 'Title', 'mcupr' ),
			esc_attr( $code ),
			esc_attr( mcupr_tr_meta( $post->ID, $code, 'title' ) )
		);
		printf(
			'<p><label>%1$s<br><textarea class="widefat" rows="2" name="mcupr_tr[%2$s][excerpt]">%3$s</textarea></label></p>',
			esc_html__( 'Excerpt', 'mcupr' ),
			esc_attr( $code ),
			esc_textarea( mcupr_tr_meta( $post->ID, $code, 'excerpt' ) )
		);
		echo '<p><strong>' . esc_html__( 'Content', 'mcupr' ) . '</strong></p>';
		wp_editor(
			mcupr_tr_meta( $post->ID, $code, 'content' ),
			'mcupr_tr_' . $code . '_content',
			array(
				'textarea_name' => "mcupr_tr[{$code}][content]",
				'textarea_rows' => 8,
				'media_buttons' => true,
				'teeny'         => true,
			)
		);
		echo '</fieldset>';
	}
}

function mcupr_save_translations( $post_id ) {
	if ( ! isset( $_POST['mcupr_tr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcupr_tr_nonce'] ) ), 'mcupr_tr_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$input = isset( $_POST['mcupr_tr'] ) && is_array( $_POST['mcupr_tr'] ) ? wp_unslash( $_POST['mcupr_tr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( array_keys( mcupr_languages() ) as $code ) {
		if ( empty( $input[ $code ] ) || ! is_array( $input[ $code ] ) ) {
			continue;
		}
		$row = $input[ $code ];
		$vals = array(
			'title'   => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
			'excerpt' => isset( $row['excerpt'] ) ? sanitize_textarea_field( $row['excerpt'] ) : '',
			'content' => isset( $row['content'] ) ? wp_kses_post( $row['content'] ) : '',
		);
		foreach ( $vals as $field => $val ) {
			if ( '' === $val ) {
				delete_post_meta( $post_id, "_mcupr_{$code}_{$field}" );
			} else {
				update_post_meta( $post_id, "_mcupr_{$code}_{$field}", $val );
			}
		}
	}
}
add_action( 'save_post', 'mcupr_save_translations' );
