<?php
/**
 * Elementor pages without Elementor, part 5: widgets that came with Elementor Pro, rebuilt in the theme: posts, slides, form, price table,
 * call to action, flip box, navigation menu, countdown, animated headline, price list.
 *
 * What cannot work without the plugin (popups, Theme Builder templates, the form's connections to other services) is listed in the
 * report of the Elementor tool; nothing is printed for it.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------------------------------ posts */

function mcupr_el_posts_query( $s, $current_id ) {
	$type = (string) mcupr_el_s( $s, 'posts_post_type', 'post' );
	if ( 'current_query' === $type ) {
		$type = 'post';
	}
	if ( ! post_type_exists( $type ) ) {
		$type = 'post';
	}
	$args = array(
		'post_type'           => $type,
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, min( 60, (int) mcupr_el_s( $s, 'posts_per_page', 6 ) ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => preg_replace( '/[^a-z_]/', '', (string) mcupr_el_s( $s, 'orderby', mcupr_el_s( $s, 'posts_orderby', 'date' ) ) ),
		'order'               => 'ASC' === strtoupper( (string) mcupr_el_s( $s, 'order', mcupr_el_s( $s, 'posts_order', 'desc' ) ) ) ? 'ASC' : 'DESC',
	);
	if ( 'yes' === mcupr_el_s( $s, 'posts_exclude_current', '' ) || 'yes' === mcupr_el_s( $s, 'exclude_current', '' ) ) {
		$args['post__not_in'] = array( (int) $current_id );
	}
	$terms = mcupr_el_s( $s, 'posts_include_term_ids', array() );
	if ( is_array( $terms ) && $terms ) {
		$by = array();
		foreach ( $terms as $tid ) {
			$t = get_term( (int) $tid );
			if ( $t && ! is_wp_error( $t ) ) {
				$by[ $t->taxonomy ][] = (int) $t->term_id;
			}
		}
		$args['tax_query'] = array( 'relation' => 'OR' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		foreach ( $by as $tax => $ids ) {
			$args['tax_query'][] = array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $ids );
		}
	}
	return new WP_Query( $args );
}

function mcupr_el_w_posts( $s, $el, $post_id ) {
	$q     = mcupr_el_posts_query( $s, $post_id );
	$cols  = max( 1, min( 6, (int) mcupr_el_s( $s, 'columns', mcupr_el_s( $s, 'classic_columns', 3 ) ) ) );
	$skin  = 'cards' === mcupr_el_s( $s, '_skin', 'classic' ) ? 'cards' : 'classic';
	$pre   = 'cards' === $skin ? 'cards_' : 'classic_';
	$thumb = 'none' !== mcupr_el_s( $s, $pre . 'thumbnail', mcupr_el_s( $s, 'classic_thumbnail', 'top' ) ) && 'none' !== mcupr_el_s( $s, 'thumbnail', 'top' );
	$tsize = (string) mcupr_el_s( $s, $pre . 'thumbnail_size', mcupr_el_s( $s, 'thumbnail_size', 'medium' ) );
	$tsize = '' === $tsize || 'custom' === $tsize ? 'medium' : $tsize;
	$tag   = mcupr_el_tag( mcupr_el_s( $s, $pre . 'title_tag', mcupr_el_s( $s, 'title_tag', 'h3' ) ), 'h3' );
	$exc   = 'yes' === mcupr_el_s( $s, $pre . 'show_excerpt', mcupr_el_s( $s, 'show_excerpt', 'yes' ) );
	$elen  = max( 1, (int) mcupr_el_s( $s, $pre . 'excerpt_length', mcupr_el_s( $s, 'excerpt_length', 25 ) ) );
	$more  = 'yes' === mcupr_el_s( $s, $pre . 'show_read_more', mcupr_el_s( $s, 'show_read_more', 'yes' ) );
	$moret = (string) mcupr_el_s( $s, $pre . 'read_more_text', mcupr_el_s( $s, 'read_more_text', 'Read More »' ) );
	$meta  = mcupr_el_s( $s, $pre . 'meta_data', mcupr_el_s( $s, 'meta_data', array( 'date' ) ) );
	$meta  = is_array( $meta ) ? $meta : array();
	$html  = '<div class="elementor-posts-container elementor-posts elementor-posts--skin-' . $skin . ' elementor-grid elementor-grid-' . $cols . '">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$html .= '<article class="elementor-post elementor-grid-item post-' . get_the_ID() . '">';
		if ( $thumb && has_post_thumbnail() ) {
			$html .= '<a class="elementor-post__thumbnail__link" href="' . esc_url( get_permalink() ) . '" tabindex="-1"><div class="elementor-post__thumbnail">' . get_the_post_thumbnail( null, $tsize ) . '</div></a>';
		}
		$html .= '<div class="elementor-post__text">';
		$html .= '<' . $tag . ' class="elementor-post__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></' . $tag . '>';
		if ( $meta ) {
			$bits = array();
			foreach ( $meta as $m ) {
				if ( 'date' === $m ) {
					$bits[] = '<span class="elementor-post-date">' . esc_html( get_the_date() ) . '</span>';
				} elseif ( 'author' === $m ) {
					$bits[] = '<span class="elementor-post-author">' . esc_html( get_the_author() ) . '</span>';
				} elseif ( 'time' === $m ) {
					$bits[] = '<span class="elementor-post-time">' . esc_html( get_the_time() ) . '</span>';
				} elseif ( 'comments' === $m ) {
					$bits[] = '<span class="elementor-post-avatar">' . esc_html( (string) get_comments_number() ) . '</span>';
				}
			}
			if ( $bits ) {
				$html .= '<div class="elementor-post__meta-data">' . implode( '<span class="elementor-post-sep"> • </span>', $bits ) . '</div>';
			}
		}
		if ( $exc ) {
			$html .= '<div class="elementor-post__excerpt"><p>' . esc_html( wp_trim_words( has_excerpt() ? get_the_excerpt() : wp_strip_all_tags( strip_shortcodes( get_the_content() ) ), $elen ) ) . '</p></div>';
		}
		if ( $more ) {
			$html .= '<a class="elementor-post__read-more" href="' . esc_url( get_permalink() ) . '">' . esc_html( $moret ) . '</a>';
		}
		$html .= '</div></article>';
	}
	wp_reset_postdata();
	return $html . '</div>';
}

function mcupr_el_wcss_posts( $s, $root ) {
	$cols = max( 1, min( 6, (int) mcupr_el_s( $s, 'columns', mcupr_el_s( $s, 'classic_columns', 3 ) ) ) );
	$out  = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$c = mcupr_el_get( $s, 'columns', $dev );
		if ( is_numeric( $c ) && '' !== $dev ) {
			$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-grid', array( 'grid-template-columns:repeat(' . max( 1, (int) $c ) . ',1fr)' ) ) );
		}
		$gc = mcupr_el_size( mcupr_el_get( $s, 'item_gap', $dev ) );
		$gr = mcupr_el_size( mcupr_el_get( $s, 'row_gap', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root, array( '' !== $gc ? '--grid-column-gap:' . $gc : '', '' !== $gr ? '--grid-row-gap:' . $gr : '' ) ) );
	}
	$tc  = mcupr_el_color_of( $s, 'classic_title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-post__title, ' . $root . ' .elementor-post__title a', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-post__title', $s, 'classic_title_typography' );
	$ec  = mcupr_el_color_of( $s, 'classic_excerpt_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-post__excerpt p', array( '' !== $ec ? 'color:' . $ec : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-post__excerpt', $s, 'classic_excerpt_typography' );
	$mc  = mcupr_el_color_of( $s, 'classic_meta_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-post__meta-data', array( '' !== $mc ? 'color:' . $mc : '' ) );
	$rc  = mcupr_el_color_of( $s, 'classic_read_more_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-post__read-more', array( '' !== $rc ? 'color:' . $rc : '' ) );
	return $out;
}

/* ----------------------------------------------------------------------------------------------- slides */

function mcupr_el_w_slides( $s ) {
	$slides = mcupr_el_s( $s, 'slides', array() );
	if ( ! $slides ) {
		return '';
	}
	mcupr_el_need( 'slides' );
	$nav = (string) mcupr_el_s( $s, 'navigation', 'both' );
	$html = '<div class="elementor-slides-wrapper elementor-main-swiper" dir="ltr" data-autoplay="' . ( 'yes' === mcupr_el_s( $s, 'autoplay', 'yes' ) ? '1' : '0' ) . '" data-speed="' . (int) mcupr_el_s( $s, 'autoplay_speed', 5000 ) . '" data-nav="' . esc_attr( $nav ) . '"><div class="elementor-slides">';
	foreach ( array_values( (array) $slides ) as $i => $sl ) {
		$bg   = isset( $sl['background_image']['url'] ) && '' !== $sl['background_image']['url'] ? "background-image:url('" . esc_url( $sl['background_image']['url'] ) . "');" : '';
		$col  = isset( $sl['background_color'] ) ? mcupr_el_color( $sl['background_color'] ) : '';
		$id   = isset( $sl['_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $sl['_id'] ) : '';
		$html .= '<div class="elementor-slide' . ( 0 === $i ? ' is-active' : '' ) . ( '' !== $id ? ' elementor-repeater-item-' . esc_attr( $id ) : '' ) . '" style="' . esc_attr( $bg . ( '' !== $col ? 'background-color:' . $col . ';' : '' ) ) . '"><div class="elementor-background-overlay"></div><div class="elementor-slide-inner"><div class="elementor-slide-content">';
		if ( ! empty( $sl['heading'] ) ) {
			$html .= '<div class="elementor-slide-heading">' . wp_kses_post( $sl['heading'] ) . '</div>';
		}
		if ( ! empty( $sl['description'] ) ) {
			$html .= '<div class="elementor-slide-description">' . wp_kses_post( $sl['description'] ) . '</div>';
		}
		if ( ! empty( $sl['button_text'] ) ) {
			list( $o ) = mcupr_el_link( isset( $sl['link'] ) ? $sl['link'] : array(), 'elementor-button elementor-slide-button elementor-size-sm' );
			if ( '' === $o ) {
				$o = '<span class="elementor-button elementor-slide-button elementor-size-sm">';
				$html .= $o . esc_html( $sl['button_text'] ) . '</span>';
			} else {
				$html .= $o . esc_html( $sl['button_text'] ) . '</a>';
			}
		}
		$html .= '</div></div></div>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_slides( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$h = mcupr_el_size( mcupr_el_get( $s, 'slides_height', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-slide', array( '' !== $h ? 'height:' . $h : '' ) ) );
	}
	$hc = mcupr_el_color_of( $s, 'heading_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-slide-heading', array( '' !== $hc ? 'color:' . $hc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-slide-heading', $s, 'heading_typography' );
	$dc = mcupr_el_color_of( $s, 'description_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-slide-description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-slide-description', $s, 'description_typography' );
	return $out;
}

/* ------------------------------------------------------------------------------------------------ form */

/** A form of Elementor Pro, sent by the theme: the answers go by e-mail to the address of the form (and to a page if one is set). */
function mcupr_el_w_form( $s, $el, $post_id ) {
	$fields = mcupr_el_s( $s, 'form_fields', array() );
	if ( ! $fields ) {
		return '';
	}
	$name = (string) mcupr_el_s( $s, 'form_name', 'New Form' );
	$html = '<form class="elementor-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" name="' . esc_attr( $name ) . '">';
	$html .= '<input type="hidden" name="action" value="mcupr_el_form"><input type="hidden" name="post_id" value="' . (int) $post_id . '"><input type="hidden" name="form_id" value="' . esc_attr( $el['id'] ) . '">' . wp_nonce_field( 'mcupr_el_form_' . $el['id'], '_wpnonce', true, false );
	$html .= '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Leave this empty <input type="text" name="mcupr_hp" tabindex="-1" autocomplete="off"></label></div>';
	$html .= '<div class="elementor-form-fields-wrapper elementor-labels-' . ( 'above' === mcupr_el_s( $s, 'label_position', 'above' ) ? 'above' : preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'label_position', 'above' ) ) ) . '">';
	foreach ( (array) $fields as $f ) {
		$cid   = isset( $f['custom_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $f['custom_id'] ) : '';
		$type  = isset( $f['field_type'] ) ? preg_replace( '/[^a-z]/', '', (string) $f['field_type'] ) : 'text';
		$label = isset( $f['field_label'] ) ? (string) $f['field_label'] : '';
		$ph    = isset( $f['placeholder'] ) ? (string) $f['placeholder'] : '';
		$req   = ! empty( $f['required'] ) && 'true' === (string) $f['required'] || ( isset( $f['required'] ) && 'yes' === $f['required'] );
		$w     = isset( $f['width'] ) && is_numeric( $f['width'] ) ? (int) $f['width'] : 100;
		$name_attr = 'form_fields[' . $cid . ']';
		$fid   = 'form-field-' . $cid;
		if ( 'hidden' === $type ) {
			$html .= '<input type="hidden" name="' . esc_attr( $name_attr ) . '" value="' . esc_attr( isset( $f['field_value'] ) ? (string) $f['field_value'] : '' ) . '">';
			continue;
		}
		$cls   = 'elementor-field-type-' . $type . ' elementor-field-group elementor-column elementor-field-group-' . $cid . ' elementor-col-' . $w . ( $req ? ' elementor-field-required' : '' );
		$html .= '<div class="' . esc_attr( $cls ) . '">';
		if ( '' !== $label && 'html' !== $type && 'acceptance' !== $type ) {
			$html .= '<label for="' . esc_attr( $fid ) . '" class="elementor-field-label">' . esc_html( $label ) . '</label>';
		}
		$req_attr = $req ? ' required="required" aria-required="true"' : '';
		switch ( $type ) {
			case 'textarea':
				$rows  = isset( $f['rows'] ) && is_numeric( $f['rows'] ) ? (int) $f['rows'] : 4;
				$html .= '<textarea class="elementor-field-textual elementor-field elementor-size-sm" name="' . esc_attr( $name_attr ) . '" id="' . esc_attr( $fid ) . '" rows="' . $rows . '" placeholder="' . esc_attr( $ph ) . '"' . $req_attr . '></textarea>';
				break;
			case 'select':
				$html .= '<div class="elementor-field elementor-select-wrapper"><select class="elementor-field-textual elementor-size-sm" name="' . esc_attr( $name_attr ) . '" id="' . esc_attr( $fid ) . '"' . $req_attr . '>';
				foreach ( preg_split( '/\r\n|\r|\n/', isset( $f['field_options'] ) ? (string) $f['field_options'] : '' ) as $opt ) {
					$parts = explode( '|', $opt );
					$html .= '<option value="' . esc_attr( isset( $parts[1] ) ? $parts[1] : $parts[0] ) . '">' . esc_html( $parts[0] ) . '</option>';
				}
				$html .= '</select></div>';
				break;
			case 'radio':
			case 'checkbox':
				$html .= '<div class="elementor-field-subgroup">';
				foreach ( preg_split( '/\r\n|\r|\n/', isset( $f['field_options'] ) ? (string) $f['field_options'] : '' ) as $n => $opt ) {
					$parts = explode( '|', $opt );
					if ( '' === trim( $parts[0] ) ) {
						continue;
					}
					$html .= '<span class="elementor-field-option"><input type="' . $type . '" value="' . esc_attr( isset( $parts[1] ) ? $parts[1] : $parts[0] ) . '" id="' . esc_attr( $fid . '-' . $n ) . '" name="' . esc_attr( $name_attr . ( 'checkbox' === $type ? '[]' : '' ) ) . '"' . ( $req && 'radio' === $type ? ' required="required"' : '' ) . '> <label for="' . esc_attr( $fid . '-' . $n ) . '">' . esc_html( $parts[0] ) . '</label></span>';
				}
				$html .= '</div>';
				break;
			case 'acceptance':
				$html .= '<div class="elementor-field-subgroup"><span class="elementor-field-option"><input type="checkbox" name="' . esc_attr( $name_attr ) . '" id="' . esc_attr( $fid ) . '" value="on"' . $req_attr . '> <label for="' . esc_attr( $fid ) . '">' . wp_kses_post( isset( $f['acceptance_text'] ) ? (string) $f['acceptance_text'] : $label ) . '</label></span></div>';
				break;
			case 'html':
				$html .= isset( $f['field_html'] ) ? (string) $f['field_html'] : '';
				break;
			default:
				$in = in_array( $type, array( 'email', 'tel', 'number', 'url', 'date', 'time', 'password' ), true ) ? $type : 'text';
				$html .= '<input size="1" type="' . $in . '" name="' . esc_attr( $name_attr ) . '" id="' . esc_attr( $fid ) . '" class="elementor-field elementor-size-sm elementor-field-textual" placeholder="' . esc_attr( $ph ) . '"' . $req_attr . '>';
		}
		$html .= '</div>';
	}
	$btn = (string) mcupr_el_s( $s, 'button_text', 'Send' );
	$bsz = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'button_size', 'sm' ) );
	$bw  = isset( $s['button_width'] ) && is_numeric( $s['button_width'] ) ? (int) $s['button_width'] : 100;
	$html .= '<div class="elementor-field-group elementor-column elementor-field-type-submit elementor-col-' . $bw . '"><button type="submit" class="elementor-button elementor-size-' . esc_attr( $bsz ) . '"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">' . esc_html( $btn ) . '</span></span></button></div>';
	$html .= '</div></form>';
	$sent = isset( $_GET['mcupr_form'] ) ? sanitize_key( wp_unslash( $_GET['mcupr_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $sent === $el['id'] . '-ok' ) {
		$html .= '<div class="elementor-message elementor-message-success" role="alert">' . esc_html( (string) mcupr_el_s( $s, 'success_message', 'The form was sent successfully.' ) ) . '</div>';
	} elseif ( $sent === $el['id'] . '-err' ) {
		$html .= '<div class="elementor-message elementor-message-danger" role="alert">' . esc_html( (string) mcupr_el_s( $s, 'error_message', 'An error occurred.' ) ) . '</div>';
	}
	return $html;
}

function mcupr_el_wcss_form( $s, $root ) {
	$out = '';
	$lc  = mcupr_el_color_of( $s, 'label_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-field-label', array( '' !== $lc ? 'color:' . $lc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-field-label', $s, 'label_typography' );
	$fc  = mcupr_el_color_of( $s, 'field_text_color' );
	$fb  = mcupr_el_color_of( $s, 'field_background_color' );
	$bc  = mcupr_el_color_of( $s, 'field_border_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-field-textual', array( '' !== $fc ? 'color:' . $fc : '', '' !== $fb ? 'background-color:' . $fb : '', '' !== $bc ? 'border-color:' . $bc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-field-textual', $s, 'field_typography' );
	$out .= mcupr_el_rule( $root . ' .elementor-field-textual', array( ( $w = mcupr_el_dim( mcupr_el_s( $s, 'field_border_width', null ) ) ) ? 'border-width:' . $w : '', ( $r = mcupr_el_dim( mcupr_el_s( $s, 'field_border_radius', null ) ) ) ? 'border-radius:' . $r : '' ) );
	$b   = $root . ' .elementor-button';
	$btc = mcupr_el_color_of( $s, 'button_text_color' );
	$btb = mcupr_el_color_of( $s, 'button_background_color' );
	$out .= mcupr_el_rule( $b, array_merge( array( '' !== $btc ? 'color:' . $btc : '', '' !== $btb ? 'background-color:' . $btb : '' ), mcupr_el_border( $s, 'button_border', '', 'button_border_radius', 'button_box_shadow' ) ) );
	$out .= mcupr_el_typo_rule( $b, $s, 'button_typography' );
	$hb  = mcupr_el_color_of( $s, 'button_background_hover_color' );
	$hc  = mcupr_el_color_of( $s, 'button_hover_color' );
	$out .= mcupr_el_rule( $b . ':hover,' . $b . ':focus', array( '' !== $hc ? 'color:' . $hc : '', '' !== $hb ? 'background-color:' . $hb : '' ) );
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$cg = mcupr_el_size( mcupr_el_get( $s, 'column_gap', $dev ) );
		$rg = mcupr_el_size( mcupr_el_get( $s, 'row_gap', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-form-fields-wrapper', array( '' !== $cg ? 'column-gap:' . $cg : '', '' !== $rg ? 'row-gap:' . $rg : '' ) ) );
	}
	return $out;
}

/** The form is sent: checked, put in an e-mail, the visitor is sent back (or on to the address the form names). */
function mcupr_el_form_submit() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$form_id = isset( $_POST['form_id'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$back    = $post_id ? get_permalink( $post_id ) : home_url( '/' );
	$ok      = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'mcupr_el_form_' . $form_id ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$form    = $ok && $post_id && 'publish' === get_post_status( $post_id ) ? mcupr_el_find_widget( mcupr_el_data( $post_id ), $form_id ) : null;
	if ( ! $form || ! empty( $_POST['mcupr_hp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		wp_safe_redirect( add_query_arg( 'mcupr_form', $form_id . '-err', $back ) );
		exit;
	}
	$s     = isset( $form['settings'] ) ? $form['settings'] : array();
	$vals  = isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ? wp_unslash( $_POST['form_fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
	$lines = array();
	$from  = '';
	foreach ( (array) mcupr_el_s( $s, 'form_fields', array() ) as $f ) {
		$cid = isset( $f['custom_id'] ) ? (string) $f['custom_id'] : '';
		$v   = isset( $vals[ $cid ] ) ? $vals[ $cid ] : '';
		$v   = is_array( $v ) ? implode( ', ', array_map( 'sanitize_text_field', $v ) ) : ( 'textarea' === ( isset( $f['field_type'] ) ? $f['field_type'] : '' ) ? sanitize_textarea_field( $v ) : sanitize_text_field( $v ) );
		if ( ( ! empty( $f['required'] ) && 'true' === (string) $f['required'] ) && '' === $v ) {
			wp_safe_redirect( add_query_arg( 'mcupr_form', $form_id . '-err', $back ) );
			exit;
		}
		if ( isset( $f['field_type'] ) && 'email' === $f['field_type'] && '' !== $v ) {
			if ( ! is_email( $v ) ) {
				wp_safe_redirect( add_query_arg( 'mcupr_form', $form_id . '-err', $back ) );
				exit;
			}
			$from = $v;
		}
		$lines[] = ( isset( $f['field_label'] ) && '' !== $f['field_label'] ? $f['field_label'] : $cid ) . ': ' . $v;
	}
	$to      = (string) mcupr_el_s( $s, 'email_to', get_option( 'admin_email' ) );
	$to      = implode( ',', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $to ) ) ) ) );
	$to      = '' !== $to ? $to : get_option( 'admin_email' );
	$subject = wp_strip_all_tags( (string) mcupr_el_s( $s, 'email_subject', 'New message from ' . get_bloginfo( 'name' ) ) );
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( '' !== $from ) {
		$headers[] = 'Reply-To: ' . $from;
	}
	$sent = wp_mail( $to, str_replace( array( "\r", "\n" ), ' ', $subject ), implode( "\n", $lines ) . "\n\n" . $back, $headers );
	$redir = mcupr_el_s( $s, 'redirect_to', '' );
	if ( $sent && is_string( $redir ) && '' !== $redir ) {
		wp_safe_redirect( $redir );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'mcupr_form', $form_id . ( $sent ? '-ok' : '-err' ), $back ) . '#elementor-' . $form_id );
	exit;
}
add_action( 'admin_post_nopriv_mcupr_el_form', 'mcupr_el_form_submit' );
add_action( 'admin_post_mcupr_el_form', 'mcupr_el_form_submit' );

/** A widget of the saved data by its id. */
function mcupr_el_find_widget( $elements, $id ) {
	foreach ( (array) $elements as $e ) {
		if ( isset( $e['id'], $e['elType'] ) && 'widget' === $e['elType'] && (string) $e['id'] === (string) $id ) {
			return $e;
		}
		if ( ! empty( $e['elements'] ) ) {
			$f = mcupr_el_find_widget( $e['elements'], $id );
			if ( $f ) {
				return $f;
			}
		}
	}
	return null;
}

/* ------------------------------------------------------------------------------- price table, price list */

function mcupr_el_w_price_table( $s ) {
	$head = (string) mcupr_el_s( $s, 'heading', '' );
	$sub  = (string) mcupr_el_s( $s, 'sub_heading', '' );
	$cur  = (string) mcupr_el_s( $s, 'currency_symbol', '$' );
	$cur  = 'custom' === $cur ? (string) mcupr_el_s( $s, 'currency_symbol_custom', '' ) : ( array( 'dollar' => '$', 'euro' => '€', 'baht' => '฿', 'franc' => '₣', 'guilder' => 'ƒ', 'krona' => 'kr', 'lira' => '₤', 'peseta' => '₧', 'peso' => '₱', 'pound' => '£', 'real' => 'R$', 'rupee' => '₹', 'rupee_indian' => '₹', 'shekel' => '₪', 'won' => '₩', 'yen' => '¥' )[ $cur ] ?? $cur );
	$price = (string) mcupr_el_s( $s, 'price', '0' );
	$per   = (string) mcupr_el_s( $s, 'period', '' );
	$html  = '<div class="elementor-price-table"><div class="elementor-price-table__header">';
	if ( '' !== $head ) {
		$html .= '<h3 class="elementor-price-table__heading">' . esc_html( $head ) . '</h3>';
	}
	if ( '' !== $sub ) {
		$html .= '<span class="elementor-price-table__subheading">' . esc_html( $sub ) . '</span>';
	}
	$html .= '</div><div class="elementor-price-table__price"><span class="elementor-price-table__currency">' . esc_html( $cur ) . '</span><span class="elementor-price-table__integer-part">' . esc_html( $price ) . '</span>' . ( '' !== $per ? '<span class="elementor-price-table__period elementor-typo-excluded">' . esc_html( $per ) . '</span>' : '' ) . '</div>';
	$feat = mcupr_el_s( $s, 'features_list', array() );
	if ( $feat ) {
		$html .= '<ul class="elementor-price-table__features-list">';
		foreach ( (array) $feat as $f ) {
			$html .= '<li class="elementor-repeater-item-' . esc_attr( isset( $f['_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', (string) $f['_id'] ) : '' ) . '"><div class="elementor-price-table__feature-inner">' . wp_kses_post( isset( $f['item_text'] ) ? (string) $f['item_text'] : '' ) . '</div></li>';
		}
		$html .= '</ul>';
	}
	$bt = (string) mcupr_el_s( $s, 'button_text', '' );
	if ( '' !== $bt ) {
		list( $o ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ), 'elementor-price-table__button elementor-button elementor-size-md' );
		$html .= '<div class="elementor-price-table__footer">' . ( '' !== $o ? $o . esc_html( $bt ) . '</a>' : '<span class="elementor-price-table__button elementor-button elementor-size-md">' . esc_html( $bt ) . '</span>' ) . '</div>';
	}
	$rb = (string) mcupr_el_s( $s, 'ribbon_title', '' );
	if ( 'yes' === mcupr_el_s( $s, 'show_ribbon', '' ) && '' !== $rb ) {
		$html .= '<div class="elementor-price-table__ribbon"><div class="elementor-price-table__ribbon-inner">' . esc_html( $rb ) . '</div></div>';
	}
	return $html . '</div>';
}

function mcupr_el_wcss_price_table( $s, $root ) {
	$out = '';
	$hb  = mcupr_el_color_of( $s, 'header_bg_color' );
	$hc  = mcupr_el_color_of( $s, 'heading_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-price-table__header', array( '' !== $hb ? 'background-color:' . $hb : '' ) );
	$out .= mcupr_el_rule( $root . ' .elementor-price-table__heading', array( '' !== $hc ? 'color:' . $hc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-price-table__heading', $s, 'heading_typography' );
	$pc  = mcupr_el_color_of( $s, 'price_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-price-table__price', array( '' !== $pc ? 'color:' . $pc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-price-table__integer-part', $s, 'price_typography' );
	$fb  = mcupr_el_color_of( $s, 'features_bg_color' );
	$fc  = mcupr_el_color_of( $s, 'features_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-price-table__features-list', array( '' !== $fb ? 'background-color:' . $fb : '', '' !== $fc ? 'color:' . $fc : '' ) );
	$bc  = mcupr_el_color_of( $s, 'button_text_color' );
	$bb  = mcupr_el_color_of( $s, 'button_background_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-price-table__button', array( '' !== $bc ? 'color:' . $bc : '', '' !== $bb ? 'background-color:' . $bb : '' ) );
	return $out;
}

function mcupr_el_w_price_list( $s ) {
	$items = mcupr_el_s( $s, 'price_list', array() );
	$html  = '<ul class="elementor-price-list">';
	foreach ( (array) $items as $it ) {
		list( $o, $c ) = mcupr_el_link( isset( $it['link'] ) ? $it['link'] : array() );
		$img = isset( $it['image'] ) ? mcupr_el_img( $it['image'], 'thumbnail' ) : '';
		$html .= '<li>' . $o . '<div class="elementor-price-list-item">' . ( '' !== $img ? '<div class="elementor-price-list-image">' . $img . '</div>' : '' ) . '<div class="elementor-price-list-text"><div class="elementor-price-list-header"><span class="elementor-price-list-title">' . esc_html( isset( $it['title'] ) ? (string) $it['title'] : '' ) . '</span><span class="elementor-price-list-separator"></span><span class="elementor-price-list-price">' . esc_html( isset( $it['price'] ) ? (string) $it['price'] : '' ) . '</span></div><p class="elementor-price-list-description">' . wp_kses_post( isset( $it['item_description'] ) ? (string) $it['item_description'] : '' ) . '</p></div></div>' . $c . '</li>';
	}
	return $html . '</ul>';
}

/* ------------------------------------------------------------------------------- call to action, flip box */

function mcupr_el_w_call_to_action( $s ) {
	$title = (string) mcupr_el_s( $s, 'title', '' );
	$desc  = (string) mcupr_el_s( $s, 'description', '' );
	$btn   = (string) mcupr_el_s( $s, 'button', '' );
	$img   = mcupr_el_img( mcupr_el_s( $s, 'bg_image', array() ), 'full' );
	$skin  = 'classic' === mcupr_el_s( $s, 'skin', 'classic' ) ? 'classic' : 'cover';
	$bg    = mcupr_el_s( $s, 'bg_image', array() );
	$html  = '<div class="elementor-cta elementor-cta--skin-' . $skin . '">';
	$html .= '<div class="elementor-cta__bg-wrapper"><div class="elementor-cta__bg elementor-bg"' . ( ! empty( $bg['url'] ) ? ' style="background-image:url(' . esc_url( $bg['url'] ) . ')"' : '' ) . '></div><div class="elementor-cta__bg-overlay"></div></div>';
	$html .= '<div class="elementor-cta__content">';
	if ( '' !== $title ) {
		$html .= '<' . mcupr_el_tag( mcupr_el_s( $s, 'title_tag', 'h2' ), 'h2' ) . ' class="elementor-cta__title elementor-cta__content-item elementor-content-item">' . wp_kses_post( $title ) . '</' . mcupr_el_tag( mcupr_el_s( $s, 'title_tag', 'h2' ), 'h2' ) . '>';
	}
	if ( '' !== $desc ) {
		$html .= '<div class="elementor-cta__description elementor-cta__content-item elementor-content-item">' . wp_kses_post( $desc ) . '</div>';
	}
	if ( '' !== $btn ) {
		list( $o ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ), 'elementor-cta__button elementor-button elementor-size-md' );
		$html .= '<div class="elementor-cta__button-wrapper elementor-cta__content-item elementor-content-item">' . ( '' !== $o ? $o . esc_html( $btn ) . '</a>' : '<span class="elementor-cta__button elementor-button elementor-size-md">' . esc_html( $btn ) . '</span>' ) . '</div>';
	}
	return $html . '</div></div>';
}

function mcupr_el_wcss_call_to_action( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$h = mcupr_el_size( mcupr_el_get( $s, 'height', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-cta', array( '' !== $h ? 'min-height:' . $h : '' ) ) );
	}
	$ov  = mcupr_el_color_of( $s, 'overlay_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-cta__bg-overlay', array( '' !== $ov ? 'background-color:' . $ov : '' ) );
	$tc  = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-cta__title', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-cta__title', $s, 'title_typography' );
	$dc  = mcupr_el_color_of( $s, 'description_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-cta__description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-cta__description', $s, 'description_typography' );
	$bc  = mcupr_el_color_of( $s, 'button_text_color' );
	$bb  = mcupr_el_color_of( $s, 'button_background_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-cta__button', array( '' !== $bc ? 'color:' . $bc : '', '' !== $bb ? 'background-color:' . $bb : '' ) );
	return $out;
}

function mcupr_el_w_flip_box( $s ) {
	$side = static function ( $x ) use ( $s ) {
		$bg = mcupr_el_s( $s, 'background_image_' . $x, array() );
		return '<div class="elementor-flip-box__' . ( 'a' === $x ? 'front' : 'back' ) . ' elementor-flip-box__layer"><div class="elementor-flip-box__layer__overlay"><div class="elementor-flip-box__layer__inner"><div class="elementor-flip-box__layer__title">' . wp_kses_post( (string) mcupr_el_s( $s, 'title_text_' . $x, '' ) ) . '</div><div class="elementor-flip-box__layer__description">' . wp_kses_post( (string) mcupr_el_s( $s, 'description_text_' . $x, '' ) ) . '</div></div></div></div>';
	};
	$effect = preg_replace( '/[^a-z-]/', '', (string) mcupr_el_s( $s, 'flip_effect', 'flip' ) );
	$dir    = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'flip_direction', 'up' ) );
	$html   = '<div class="elementor-flip-box elementor-flip-box--effect-' . esc_attr( $effect ) . ' elementor-flip-box--direction-' . esc_attr( $dir ) . '">' . $side( 'a' );
	$back   = $side( 'b' );
	$bt     = (string) mcupr_el_s( $s, 'button_text', '' );
	if ( '' !== $bt ) {
		list( $o ) = mcupr_el_link( mcupr_el_s( $s, 'link', array() ), 'elementor-flip-box__button elementor-button elementor-size-md' );
		$btn  = '' !== $o ? $o . esc_html( $bt ) . '</a>' : '<span class="elementor-flip-box__button elementor-button elementor-size-md">' . esc_html( $bt ) . '</span>';
		$back = str_replace( '</div></div></div>', '</div>' . $btn . '</div></div>', $back );
	}
	return $html . $back . '</div>';
}

function mcupr_el_wcss_flip_box( $s, $root ) {
	$out = '';
	foreach ( mcupr_el_devices() as $dev => $q ) {
		$h = mcupr_el_size( mcupr_el_get( $s, 'height', $dev ) );
		$out .= mcupr_el_media( $dev, mcupr_el_rule( $root . ' .elementor-flip-box', array( '' !== $h ? 'height:' . $h : '' ) ) );
	}
	foreach ( array( 'a' => 'front', 'b' => 'back' ) as $x => $name ) {
		$bg  = mcupr_el_s( $s, 'background_color_' . $x, '' );
		$img = mcupr_el_s( $s, 'background_image_' . $x, array() );
		$ov  = mcupr_el_color( mcupr_el_s( $s, 'background_overlay_color_' . $x, '' ) );
		$d   = array( mcupr_el_color( $bg ) ? 'background-color:' . mcupr_el_color( $bg ) : '', ! empty( $img['url'] ) ? "background-image:url('" . esc_url_raw( $img['url'] ) . "')" : '', 'background-size:cover', 'background-position:center' );
		$out .= mcupr_el_rule( $root . ' .elementor-flip-box__' . $name, $d );
		$out .= mcupr_el_rule( $root . ' .elementor-flip-box__' . $name . ' .elementor-flip-box__layer__overlay', array( '' !== $ov ? 'background-color:' . $ov : '' ) );
		$tc  = mcupr_el_color_of( $s, 'title_color_' . $x );
		$dc  = mcupr_el_color_of( $s, 'description_color_' . $x );
		$out .= mcupr_el_rule( $root . ' .elementor-flip-box__' . $name . ' .elementor-flip-box__layer__title', array( '' !== $tc ? 'color:' . $tc : '' ) );
		$out .= mcupr_el_rule( $root . ' .elementor-flip-box__' . $name . ' .elementor-flip-box__layer__description', array( '' !== $dc ? 'color:' . $dc : '' ) );
	}
	return $out;
}

/* ------------------------------------------------------------------------ nav menu, countdown, headline */

function mcupr_el_w_nav_menu( $s ) {
	$slug = (string) mcupr_el_s( $s, 'menu', '' );
	if ( '' === $slug ) {
		return false;
	}
	$layout = preg_replace( '/[^a-z]/', '', (string) mcupr_el_s( $s, 'layout', 'horizontal' ) );
	$menu   = wp_nav_menu(
		array(
			'menu'       => $slug,
			'menu_class' => 'elementor-nav-menu elementor-nav-menu--layout-' . $layout,
			'container'  => 'nav',
			'container_class' => 'elementor-nav-menu--main elementor-nav-menu__container elementor-nav-menu--layout-' . $layout,
			'echo'       => false,
			'fallback_cb' => '__return_empty_string',
		)
	);
	return $menu ? (string) $menu : false;
}

function mcupr_el_w_countdown( $s ) {
	$due = (string) mcupr_el_s( $s, 'due_date', '' );
	if ( '' === $due ) {
		return '';
	}
	mcupr_el_need( 'countdown' );
	$parts = array( 'days' => 'show_days', 'hours' => 'show_hours', 'minutes' => 'show_minutes', 'seconds' => 'show_seconds' );
	$lbl   = array( 'days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds' );
	$html  = '<div class="elementor-countdown-wrapper" data-date="' . esc_attr( strtotime( $due ) ? (string) strtotime( $due ) : '0' ) . '">';
	foreach ( $parts as $k => $key ) {
		if ( 'yes' !== mcupr_el_s( $s, $key, 'yes' ) ) {
			continue;
		}
		$html .= '<div class="elementor-countdown-item"><span class="elementor-countdown-digits elementor-countdown-' . $k . '">00</span>' . ( 'yes' === mcupr_el_s( $s, 'show_labels', 'yes' ) ? '<span class="elementor-countdown-label">' . esc_html( $lbl[ $k ] ) . '</span>' : '' ) . '</div>';
	}
	return $html . '</div>';
}

function mcupr_el_w_animated_headline( $s ) {
	$before = (string) mcupr_el_s( $s, 'before_text', '' );
	$hl     = (string) mcupr_el_s( $s, 'highlighted_text', '' );
	$rot    = preg_split( '/\r\n|\r|\n/', (string) mcupr_el_s( $s, 'rotating_text', '' ) );
	$after  = (string) mcupr_el_s( $s, 'after_text', '' );
	$tag    = mcupr_el_tag( mcupr_el_s( $s, 'header_size', 'h3' ), 'h3' );
	$word   = '' !== $hl ? $hl : ( isset( $rot[0] ) ? $rot[0] : '' );
	return '<' . $tag . ' class="elementor-headline"><span class="elementor-headline-plain-text">' . esc_html( $before ) . '</span> <span class="elementor-headline-dynamic-wrapper"><span class="elementor-headline-dynamic-text elementor-headline-text-active">' . esc_html( $word ) . '</span></span> <span class="elementor-headline-plain-text">' . esc_html( $after ) . '</span></' . $tag . '>';
}

function mcupr_el_wcss_animated_headline( $s, $root ) {
	$out = mcupr_el_align_rule( $root, $s );
	$tc  = mcupr_el_color_of( $s, 'title_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-headline', array( '' !== $tc ? 'color:' . $tc : '' ) );
	$out .= mcupr_el_typo_rule( $root . ' .elementor-headline', $s, 'title_typography' );
	$hc  = mcupr_el_color_of( $s, 'words_color' );
	$out .= mcupr_el_rule( $root . ' .elementor-headline-dynamic-text', array( '' !== $hc ? 'color:' . $hc : '' ) );
	return $out;
}
