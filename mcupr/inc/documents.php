<?php
/**
 * Document centre: every file attached to any published post / page / event / work in one searchable list
 * (the place for ITA, procurement, forms and announcements).  [mcupr_documents per_page="20"]
 * Filters (plain GET links, no JavaScript): dq = search words, dt = type, dp = page.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

function mcupr_doc_types() {
	return array(
		'pdf'   => array( 'pdf' ),
		'doc'   => array( 'doc', 'docx', 'odt', 'rtf', 'txt' ),
		'sheet' => array( 'xls', 'xlsx', 'csv', 'ods' ),
		'slide' => array( 'ppt', 'pptx', 'odp' ),
		'image' => array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ),
	);
}

function mcupr_doc_type_of( $ext ) {
	$ext = strtolower( $ext );
	foreach ( mcupr_doc_types() as $k => $list ) {
		if ( in_array( $ext, $list, true ) ) {
			return $k;
		}
	}
	return 'other';
}

/** All files of published content, newest post first. */
function mcupr_documents_collect() {
	$rows  = array();
	$posts = get_posts(
		array(
			'post_type'      => mcupr_tool_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'meta_key'       => '_mcupr_files', // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'has_password'   => false,
		)
	);
	foreach ( $posts as $p ) {
		foreach ( mcupr_files( $p->ID ) as $i => $f ) {
			$path = (string) get_attached_file( $f['id'] );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$rows[] = array(
				'post'  => $p,
				'index' => $i,
				'file'  => $f,
				'name'  => '' !== trim( $f['title'] ) ? $f['title'] : get_the_title( $f['id'] ),
				'ext'   => $ext,
				'type'  => mcupr_doc_type_of( $ext ),
				'size'  => mcupr_file_size_text( $f ),
				'date'  => get_post_timestamp( $p ),
			);
		}
	}
	return $rows;
}

function mcupr_documents_html( $per_page = 20 ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$q    = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : '';
	$type = isset( $_GET['dt'] ) ? sanitize_key( wp_unslash( $_GET['dt'] ) ) : '';
	$page = isset( $_GET['dp'] ) ? max( 1, absint( $_GET['dp'] ) ) : 1;
	// phpcs:enable
	$types = array( 'pdf' => 'PDF', 'doc' => mcupr_t( 'doc_type_doc' ), 'sheet' => mcupr_t( 'doc_type_sheet' ), 'slide' => mcupr_t( 'doc_type_slide' ), 'image' => mcupr_t( 'file_image' ), 'other' => mcupr_t( 'doc_type_other' ) );
	if ( ! isset( $types[ $type ] ) ) {
		$type = '';
	}
	$rows = mcupr_documents_collect();
	if ( '' !== $type ) {
		$rows = array_filter( $rows, static function ( $r ) use ( $type ) { return $r['type'] === $type; } );
	}
	if ( '' !== $q ) {
		$words = preg_split( '/\s+/u', mb_strtolower( $q ), -1, PREG_SPLIT_NO_EMPTY );
		$rows  = array_filter(
			$rows,
			static function ( $r ) use ( $words ) {
				$hay = mb_strtolower( $r['name'] . ' ' . get_the_title( $r['post'] ) );
				foreach ( $words as $w ) {
					if ( false === mb_strpos( $hay, $w ) ) {
						return false;
					}
				}
				return true;
			}
		);
	}
	$rows  = array_values( $rows );
	$total = count( $rows );
	$pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );
	$page  = min( $page, $pages );
	$slice = array_slice( $rows, ( $page - 1 ) * $per_page, $per_page );
	$base  = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( '/' );
	$id    = 'docs-' . wp_rand( 100, 999 );

	ob_start();
	echo '<div class="docs-wrap" id="documents">';
	echo '<form class="docs-form" method="get" action="' . esc_url( $base ) . '#documents" role="search" aria-label="' . esc_attr( mcupr_t( 'docs_search' ) ) . '">';
	echo '<label class="docs-f" for="' . esc_attr( $id ) . '-q"><span>' . esc_html( mcupr_t( 'docs_search' ) ) . '</span><input id="' . esc_attr( $id ) . '-q" type="search" name="dq" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr( mcupr_t( 'docs_ph' ) ) . '"></label>';
	echo '<label class="docs-f" for="' . esc_attr( $id ) . '-t"><span>' . esc_html( mcupr_t( 'docs_type' ) ) . '</span><select id="' . esc_attr( $id ) . '-t" name="dt"><option value="">' . esc_html( mcupr_t( 'docs_all_types' ) ) . '</option>';
	foreach ( $types as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></label><button type="submit" class="btn">' . esc_html( mcupr_t( 'search' ) ) . '</button></form>';
	echo '<p class="docs-count" role="status">' . esc_html( sprintf( mcupr_t( 'docs_count' ), number_format_i18n( $total ) ) ) . '</p>';
	if ( $slice ) {
		echo '<table class="docs"><caption class="sr-only">' . esc_html( mcupr_t( 'docs_title' ) ) . '</caption><thead><tr><th scope="col">' . esc_html( mcupr_t( 'docs_name' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'docs_from' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'docs_type' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'docs_date' ) ) . '</th><th scope="col">' . esc_html( mcupr_t( 'docs_dl' ) ) . '</th></tr></thead><tbody>';
		foreach ( $slice as $r ) {
			$full = $r['file']['max'] > 0 && $r['file']['count'] >= $r['file']['max'];
			echo '<tr><th scope="row" data-l="' . esc_attr( mcupr_t( 'docs_name' ) ) . '"><span class="docs-n">' . esc_html( $r['name'] ) . '</span></th>';
			echo '<td data-l="' . esc_attr( mcupr_t( 'docs_from' ) ) . '"><a href="' . esc_url( get_permalink( $r['post'] ) ) . '">' . esc_html( get_the_title( $r['post'] ) ) . '</a></td>';
			echo '<td data-l="' . esc_attr( mcupr_t( 'docs_type' ) ) . '">' . esc_html( strtoupper( $r['ext'] ) . ( '' !== $r['size'] ? ' · ' . $r['size'] : '' ) ) . '</td>';
			echo '<td data-l="' . esc_attr( mcupr_t( 'docs_date' ) ) . '"><time datetime="' . esc_attr( wp_date( 'c', $r['date'] ) ) . '">' . esc_html( mcupr_date_text( $r['date'] ) ) . '</time></td>';
			echo '<td data-l="' . esc_attr( mcupr_t( 'docs_dl' ) ) . '">';
			if ( $full ) {
				echo '<span class="file-full">' . esc_html( mcupr_t( 'file_full' ) ) . '</span>';
			} else {
				echo '<a class="file-dl" href="' . esc_url( mcupr_download_url( $r['post']->ID, $r['index'] ) ) . '">' . mcupr_icon( 'download', 18 ) . '<span>' . esc_html( mcupr_t( 'download' ) ) . '</span><span class="sr-only"> ' . esc_html( $r['name'] ) . '</span></a> <small class="docs-c">' . esc_html( sprintf( mcupr_t( 'file_count' ), number_format_i18n( $r['file']['count'] ) ) ) . '</small>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $pages > 1 ) {
			$link = static function ( $p ) use ( $base, $q, $type ) {
				return esc_url( add_query_arg( array_filter( array( 'dq' => $q, 'dt' => $type, 'dp' => $p > 1 ? $p : null ) ), $base ) . '#documents' );
			};
			echo '<nav class="docs-pg" aria-label="' . esc_attr( mcupr_t( 'pagination' ) ) . '">';
			if ( $page > 1 ) {
				echo '<a class="btn-outline" rel="prev" href="' . $link( $page - 1 ) . '">‹ ' . esc_html( mcupr_t( 'prev' ) ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<span>' . esc_html( $page . ' / ' . $pages ) . '</span>';
			if ( $page < $pages ) {
				echo '<a class="btn-outline" rel="next" href="' . $link( $page + 1 ) . '">' . esc_html( mcupr_t( 'next' ) ) . ' ›</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</nav>';
		}
	} else {
		echo '<p class="text-muted">' . esc_html( mcupr_t( 'docs_none' ) ) . '</p>';
	}
	echo '</div>';
	return ob_get_clean();
}

add_shortcode(
	'mcupr_documents',
	static function ( $atts ) {
		$a = shortcode_atts( array( 'per_page' => 20 ), $atts, 'mcupr_documents' );
		return mcupr_documents_html( max( 5, min( 100, (int) $a['per_page'] ) ) );
	}
);
