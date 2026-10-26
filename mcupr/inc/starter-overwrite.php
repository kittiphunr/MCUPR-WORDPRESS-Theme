<?php
/**
 * Put the latest starter in over what a site already has (MCUPR → Import / export settings, card 5).
 *
 * The normal imports never overwrite: WordPress Importer skips a page whose title and date it already has, and the
 * settings import only fills what is empty. This card is the one place that does replace, and only when asked:
 *   • pages: the text, the description for search engines (excerpt) and the translated titles of the starter pages the site already has (matched by address),
 *     ticked one by one; a page its owner edited is marked and not ticked at first. The old text is kept twice before
 *     anything is written: as a revision and as a copy in the page's own "before overwrite" field.
 *   • settings: the home page blocks, figures, switches and telephone of the starter settings file; what was there
 *     is saved as a file that can be downloaded again.
 * Menus, news, events and every other item are not touched, and pages the site does not have are not made here
 * (they come in with Tools → Import).
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The pages of the starter files: slug => array( title, content, meta ). The theme's own files, read-only. */
function mcupr_so_pages() {
	$out = array();
	foreach ( array( 'mcupr-starter.xml', 'mcupr-starter-addons.xml' ) as $file ) {
		$f = get_theme_file_path( 'starter/' . $file );
		if ( ! is_readable( $f ) ) {
			continue;
		}
		$x = simplexml_load_string( (string) file_get_contents( $f ), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $x || ! isset( $x->channel->item ) ) {
			continue;
		}
		foreach ( $x->channel->item as $it ) {
			$wp = $it->children( 'http://wordpress.org/export/1.2/' );
			$ct = $it->children( 'http://purl.org/rss/1.0/modules/content/' );
			if ( 'page' !== (string) $wp->post_type || '' === (string) $wp->post_name || isset( $out[ (string) $wp->post_name ] ) ) {
				continue;
			}
			$meta = array();
			foreach ( $wp->postmeta as $pm ) {
				$k = (string) $pm->meta_key;
				if ( preg_match( '/^_mcupr_[a-z]{2,3}_title$/', $k ) ) {
					$meta[ $k ] = (string) $pm->meta_value;
				}
			}
			$ex = $it->children( 'http://wordpress.org/export/1.2/excerpt/' );
			$out[ (string) $wp->post_name ] = array( 'title' => (string) $it->title, 'content' => (string) $ct->encoded, 'excerpt' => trim( (string) $ex->encoded ), 'meta' => $meta );
		}
	}
	return $out;
}

/** The page of this site with that address, or null. */
function mcupr_so_find( $slug ) {
	$p = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true ) );
	return $p ? $p[0] : null;
}

/**
 * Where a starter page stands on this site:
 * 'missing' (not on the site), 'same' (already the starter's text), 'plain' (old starter text, never edited),
 * 'edited' (changed by someone).
 */
function mcupr_so_state( $post, $page ) {
	if ( ! $post ) {
		return 'missing';
	}
	// the description search engines show is the page's excerpt: a page is only "the latest" when that came too (a starter page without one leaves the site's own alone)
	if ( trim( (string) $post->post_content ) === trim( $page['content'] ) && ( '' === $page['excerpt'] || trim( (string) $post->post_excerpt ) === $page['excerpt'] ) ) {
		return 'same';
	}
	$mine = (string) get_post_meta( $post->ID, '_mcupr_so_hash', true );
	if ( '' !== $mine && md5( (string) $post->post_content ) === $mine ) {
		return 'plain'; // the text this tool wrote last time, untouched since
	}
	return $post->post_modified === $post->post_date ? 'plain' : 'edited';
}

/** Replace the chosen pages. Returns array( replaced, same, missing, skipped ). */
function mcupr_so_replace_pages( $slugs ) {
	$rep   = array( 'replaced' => 0, 'same' => 0, 'missing' => 0, 'skipped' => 0 );
	$pages = mcupr_so_pages();
	foreach ( array_unique( (array) $slugs ) as $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! isset( $pages[ $slug ] ) ) {
			$rep['skipped']++;
			continue;
		}
		$post = mcupr_so_find( $slug );
		if ( ! $post ) {
			$rep['missing']++;
			continue;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			$rep['skipped']++;
			continue;
		}
		$pg = $pages[ $slug ];
		if ( 'same' === mcupr_so_state( $post, $pg ) ) {
			$rep['same']++;
			continue;
		}
		update_post_meta( $post->ID, '_mcupr_so_backup', wp_slash( array( 'title' => $post->post_title, 'content' => $post->post_content, 'at' => time() ) ) );
		if ( function_exists( 'wp_save_post_revision' ) ) {
			wp_save_post_revision( $post->ID ); // the old text as a revision, if the site keeps revisions
		}
		$new = array( 'ID' => $post->ID, 'post_title' => $pg['title'], 'post_content' => $pg['content'] );
		if ( '' !== $pg['excerpt'] ) {
			$new['post_excerpt'] = $pg['excerpt'];
		}
		$r = wp_update_post( wp_slash( $new ), true );
		if ( is_wp_error( $r ) || ! $r ) {
			$rep['skipped']++;
			continue;
		}
		foreach ( $pg['meta'] as $k => $v ) {
			update_post_meta( $post->ID, $k, wp_slash( $v ) );
		}
		update_post_meta( $post->ID, '_mcupr_so_hash', md5( (string) get_post_field( 'post_content', $post->ID ) ) );
		$rep['replaced']++;
	}
	return $rep;
}

/** Save what the settings are now, so the overwrite can be undone by importing that file. */
function mcupr_so_backup_settings() {
	update_option( 'mcupr_so_settings_backup', array( 'at' => time(), 'data' => mcupr_ss_export() ), false );
}

function mcupr_so_card() {
	if ( ! current_user_can( 'mcupr_manage_site_access' ) || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	$pages = mcupr_so_pages();
	$names = array(
		'missing' => __( 'Not on this site', 'mcupr' ),
		'same'    => __( 'Already the latest', 'mcupr' ),
		'plain'   => __( 'Old starter text (not edited)', 'mcupr' ),
		'edited'  => __( 'Edited by you', 'mcupr' ),
	);
	echo '<div class="card" style="max-width:62rem"><h2>' . esc_html__( '5. Replace with the latest starter (overwrites)', 'mcupr' ) . '</h2>';
	echo '<p>' . esc_html__( 'Use this when the site already has the starter pages and you want the newer text in them. Only the pages you tick are replaced (text and translated titles); menus, news and other items are not touched. Before a page is replaced its old text is kept as a revision and as a copy in the page itself.', 'mcupr' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcupr_so">';
	wp_nonce_field( 'mcupr_so', 'mcupr_so_nonce' );
	echo '<table class="widefat striped" style="max-width:100%"><thead><tr><th scope="col" style="width:5rem">' . esc_html__( 'Replace', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'Page', 'mcupr' ) . '</th><th scope="col">' . esc_html__( 'On this site', 'mcupr' ) . '</th></tr></thead><tbody>';
	$any = false;
	foreach ( $pages as $slug => $pg ) {
		$post  = mcupr_so_find( $slug );
		$state = mcupr_so_state( $post, $pg );
		if ( 'missing' === $state ) {
			continue;
		}
		$any = true;
		echo '<tr><td><input type="checkbox" name="pages[]" value="' . esc_attr( $slug ) . '" id="so-' . esc_attr( $slug ) . '"' . ( 'plain' === $state ? ' checked' : '' ) . ( 'same' === $state ? ' disabled' : '' ) . '></td>';
		echo '<td><label for="so-' . esc_attr( $slug ) . '">' . esc_html( $pg['title'] ) . '</label> <code>' . esc_html( $slug ) . '</code></td><td>' . esc_html( $names[ $state ] ) . '</td></tr>';
	}
	if ( ! $any ) {
		echo '<tr><td colspan="3">' . esc_html__( 'None of the starter pages is on this site yet: import the content file first (card 1).', 'mcupr' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description">' . esc_html__( 'Pages marked “Edited by you” are not ticked: replacing them removes your changes from the page (the old text stays in its revisions).', 'mcupr' ) . '</p>';
	echo '<p><label><input type="checkbox" name="settings" value="1"> ' . esc_html__( 'Also replace the home page settings (cards, figures, switches, telephone) with the starter’s. The settings as they are now are saved first, as a file you can download.', 'mcupr' ) . '</label></p>';
	echo '<p><label><input type="checkbox" name="sure" value="1" required> <strong>' . esc_html__( 'I understand that the ticked pages and settings will be replaced.', 'mcupr' ) . '</strong></label></p>';
	echo '<p><button class="button button-primary">' . esc_html__( 'Replace with the latest starter', 'mcupr' ) . '</button> ';
	$bk = get_option( 'mcupr_so_settings_backup' );
	if ( is_array( $bk ) && ! empty( $bk['data'] ) ) {
		echo '<button class="button" name="download_backup" value="1" formnovalidate>' . esc_html( sprintf( /* translators: %s: date */ __( 'Download the settings as they were before the last overwrite (%s)', 'mcupr' ), wp_date( 'Y-m-d H:i', (int) $bk['at'] ) ) ) . '</button>';
	}
	echo '</p></form></div>';
}

add_action(
	'admin_post_mcupr_so',
	static function () {
		if ( ! mcupr_check_request( 'mcupr_so_nonce', 'mcupr_so', 'mcupr_manage_site_access' ) || ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'mcupr' ), 403 );
		}
		if ( ! empty( $_POST['download_backup'] ) ) {
			$bk = get_option( 'mcupr_so_settings_backup' );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="mcupr-settings-before-overwrite.json"' );
			echo wp_json_encode( is_array( $bk ) && isset( $bk['data'] ) ? $bk['data'] : array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput
			exit;
		}
		$msg = '';
		if ( empty( $_POST['sure'] ) ) {
			$msg = __( 'Nothing was replaced: the confirmation box was not ticked.', 'mcupr' );
		} else {
			$slugs = isset( $_POST['pages'] ) && is_array( $_POST['pages'] ) ? array_map( 'sanitize_title', wp_unslash( $_POST['pages'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$r     = mcupr_so_replace_pages( $slugs );
			$parts = array( sprintf( /* translators: %d: pages */ __( 'Pages replaced: %d.', 'mcupr' ), $r['replaced'] ) );
			if ( $r['same'] || $r['missing'] || $r['skipped'] ) {
				$parts[] = sprintf( /* translators: 1: already latest, 2: not on the site, 3: not allowed or unknown */ __( 'Left as they were: %1$d already the latest, %2$d not on this site, %3$d not allowed.', 'mcupr' ), $r['same'], $r['missing'], $r['skipped'] );
			}
			if ( ! empty( $_POST['settings'] ) ) {
				mcupr_so_backup_settings();
				$parts[] = __( 'Home page settings replaced:', 'mcupr' ) . ' ' . mcupr_ss_message( mcupr_ss_apply( mcupr_ss_starter(), null, true ) );
			}
			$msg = implode( ' ', $parts );
		}
		set_transient( 'mcupr_ss_msg_' . get_current_user_id(), $msg, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=mcupr-settings-io' ) );
		exit;
	}
);
