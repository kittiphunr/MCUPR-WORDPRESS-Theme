<?php
/**
 * Elementor pages without Elementor, part 6: the admin page "Elementor content" (MCUPR → เนื้อหา Elementor): what is still built with
 * Elementor, which widgets it uses, what the theme cannot print, and the switch for the style files.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The page is only added when the site has Elementor content (so a site that never used it sees nothing). */
function mcupr_el_site_has_content() {
	$n = get_transient( 'mcupr_el_count' );
	if ( false === $n ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' AND p.post_status NOT IN ('trash','auto-draft')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		set_transient( 'mcupr_el_count', $n, HOUR_IN_SECONDS );
	}
	return $n > 0;
}

add_action(
	'admin_menu',
	static function () {
		if ( ! mcupr_el_site_has_content() ) {
			return;
		}
		add_submenu_page( 'mcupr', __( 'Elementor content', 'mcupr' ), __( 'Elementor content', 'mcupr' ), 'manage_options', 'mcupr-elementor', 'mcupr_el_admin_page' );
	},
	30
);

/** The switch: use the style files Elementor left in uploads, or write the style from the settings. */
add_action(
	'admin_post_mcupr_el_settings',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcupr_el_settings' );
		update_option( 'mcupr_el_use_files', isset( $_POST['use_files'] ) ? 'yes' : 'no', false ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		delete_option( 'mcupr_el_report' );
		wp_safe_redirect( add_query_arg( 'saved', '1', admin_url( 'admin.php?page=mcupr-elementor' ) ) );
		exit;
	}
);

function mcupr_el_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$inv    = mcupr_el_inventory();
	$active = mcupr_el_plugin_active();
	$rep    = get_option( 'mcupr_el_report', array() );
	$rep    = is_array( $rep ) ? $rep : array();
	$widgets = array();
	$missing = array();
	$files   = 0;
	foreach ( $inv as $row ) {
		foreach ( $row['widgets'] as $w => $n ) {
			$widgets[ $w ] = isset( $widgets[ $w ] ) ? $widgets[ $w ] + $n : $n;
		}
		foreach ( $row['unsupported'] as $w ) {
			$missing[ $w ] = isset( $missing[ $w ] ) ? $missing[ $w ] + 1 : 1;
		}
		if ( $row['css_file'] ) {
			$files++;
		}
	}
	arsort( $widgets );
	arsort( $missing );
	$fonts = array();
	foreach ( $rep as $r ) {
		foreach ( isset( $r['fonts'] ) ? (array) $r['fonts'] : array() as $f ) {
			$fonts[ $f ] = true;
		}
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Elementor content', 'mcupr' ) . '</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'mcupr' ) . '</p></div>';
	}
	if ( $active ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'The Elementor plugin is active, so it prints these pages itself. The theme takes over as soon as the plugin is switched off — nothing of what you built is lost.', 'mcupr' ) . '</p></div>';
	} else {
		/* translators: %d: number of posts and pages */
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '%d items built with Elementor are printed by the theme; the Elementor plugin is not needed for them.', 'mcupr' ), count( $inv ) ) ) . '</p></div>';
	}
	echo '<p>' . esc_html__( 'The theme reads what Elementor saved for each page (its sections, columns and widgets) and prints it with the same markup and the same style. Nothing is converted or rewritten: the data stays as it is, so installing Elementor again gives you the editor back.', 'mcupr' ) . '</p>';

	echo '<h2>' . esc_html__( 'The style of the pages', 'mcupr' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'mcupr_el_settings' );
	echo '<input type="hidden" name="action" value="mcupr_el_settings">';
	echo '<p><label><input type="checkbox" name="use_files" value="1"' . checked( mcupr_el_use_files(), true, false ) . '> ' . esc_html__( 'Use the style files Elementor left in wp-content/uploads/elementor/css/ (recommended: they are exactly what the pages looked like)', 'mcupr' ) . '</label></p>';
	/* translators: 1: number of pages with a file, 2: number of pages */
	echo '<p class="description">' . esc_html( sprintf( __( '%1$d of %2$d pages still have their style file. For the others the theme writes the style again from the saved settings. Unticking the box makes the theme write the style for every page.', 'mcupr' ), $files, count( $inv ) ) ) . '</p>';
	submit_button( __( 'Save', 'mcupr' ) );
	echo '</form>';

	if ( $missing ) {
		echo '<h2>' . esc_html__( 'Widgets the theme cannot print', 'mcupr' ) . '</h2><p>' . esc_html__( 'These are left out of the page (visitors see nothing in their place; an editor who is logged in sees a note). Everything else on those pages is printed as before.', 'mcupr' ) . '</p><ul class="ul-disc">';
		foreach ( $missing as $w => $n ) {
			/* translators: %d: number of pages */
			echo '<li><code>' . esc_html( $w ) . '</code> — ' . esc_html( sprintf( __( 'on %d pages', 'mcupr' ), $n ) ) . '</li>';
		}
		echo '</ul>';
	}
	if ( $fonts ) {
		echo '<h2>' . esc_html__( 'Fonts', 'mcupr' ) . '</h2><p>' . esc_html__( 'These fonts were chosen in Elementor but no copy of them is on the server, so the pages fall back to the font of the theme. Ask your web team to add them, or change the font of those elements.', 'mcupr' ) . '</p><p><code>' . esc_html( implode( ', ', array_keys( $fonts ) ) ) . '</code></p>';
	}

	echo '<h2>' . esc_html__( 'Pages built with Elementor', 'mcupr' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'mcupr' ) . '</th><th>' . esc_html__( 'Kind', 'mcupr' ) . '</th><th>' . esc_html__( 'Widgets', 'mcupr' ) . '</th><th>' . esc_html__( 'Style file', 'mcupr' ) . '</th><th>' . esc_html__( 'Not printed', 'mcupr' ) . '</th></tr></thead><tbody>';
	foreach ( $inv as $id => $row ) {
		$link  = get_permalink( $id );
		$title = '' !== $row['title'] ? $row['title'] : '#' . $id;
		echo '<tr><td><strong>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</strong> <a class="row-actions" href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html__( '(edit)', 'mcupr' ) . '</a></td>';
		echo '<td>' . esc_html( '' !== $row['template'] ? $row['template'] : $row['type'] ) . ( 'publish' === $row['status'] ? '' : ' <em>(' . esc_html( $row['status'] ) . ')</em>' ) . '</td>';
		echo '<td>' . esc_html( (string) array_sum( $row['widgets'] ) ) . '</td>';
		echo '<td>' . ( $row['css_file'] ? '✓' : '—' ) . '</td>';
		echo '<td>' . ( $row['unsupported'] ? '<code>' . esc_html( implode( ', ', $row['unsupported'] ) ) . '</code>' : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	if ( $widgets ) {
		echo '<h2>' . esc_html__( 'Widgets used on the site', 'mcupr' ) . '</h2><p>';
		$bits = array();
		foreach ( $widgets as $w => $n ) {
			$ok     = 'global' === $w || function_exists( 'mcupr_el_w_' . str_replace( '-', '_', $w ) );
			$bits[] = ( $ok ? '✓ ' : '✗ ' ) . $w . ' (' . (int) $n . ')';
		}
		echo esc_html( implode( ' · ', $bits ) ) . '</p>';
	}
	echo '<h2>' . esc_html__( 'What cannot come back without the plugin', 'mcupr' ) . '</h2><ul class="ul-disc">';
	foreach ( array(
		__( 'Pop-ups of Elementor Pro: they were shown by the plugin on rules it kept for itself. Their content is still saved and can be put on a page with the shortcode [elementor-template id="123"].', 'mcupr' ),
		__( 'Theme Builder templates (your own header, footer, single-post or archive layouts): the theme uses its own header and footer instead. A saved header can be put into a page with the same shortcode.', 'mcupr' ),
		__( 'What the form sent to other services (Mailchimp, a CRM, a webhook): the theme sends the answers by e-mail to the address of the form instead.', 'mcupr' ),
		__( 'The editor itself: to change these pages you either install Elementor again or edit the page in the normal WordPress editor (which replaces the Elementor content).', 'mcupr' ),
	) as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ul></div>';
}
