<?php
/**
 * MCUPR → สิทธิ์ผู้ใช้: one table of who may do what. Down the side every capability the theme and WordPress have,
 * grouped; across the top every role on the site. Tick a box, press save.
 *
 * The administrator's column is shown but cannot be changed: inc/roles.php gives an administrator everything on every
 * load, so a site can never tick itself out of its own settings.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** The roles the table shows, in the order it shows them: the theme's first, then WordPress', then any made here. */
function mcupr_roles_listed() {
	$all   = wp_roles()->roles;
	$std   = mcupr_standard_roles();
	$order = array();
	foreach ( array_keys( $std ) as $k ) {
		if ( isset( $all[ $k ] ) ) {
			$order[] = $k;
		}
	}
	foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $k ) {
		if ( isset( $all[ $k ] ) ) {
			$order[] = $k;
		}
	}
	foreach ( array_keys( $all ) as $k ) {
		if ( ! in_array( $k, $order, true ) ) {
			$order[] = $k;
		}
	}
	$out = array();
	foreach ( $order as $k ) {
		$out[ $k ] = $all[ $k ];
	}
	return $out;
}

/** role => how many users hold it. One query for all of them. */
function mcupr_roles_user_counts() {
	$counts = count_users();
	return isset( $counts['avail_roles'] ) && is_array( $counts['avail_roles'] ) ? $counts['avail_roles'] : array();
}

/** Where a role came from: 'standard' (the theme's), 'core' (WordPress'), 'custom' (made on this site). */
function mcupr_role_origin( $role ) {
	if ( mcupr_is_standard_role( $role ) ) {
		return 'standard';
	}
	if ( in_array( $role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true ) ) {
		return 'core';
	}
	return 'custom';
}

/* ================================================================== the menu */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'mcupr', __( 'User roles', 'mcupr' ), __( 'User roles', 'mcupr' ), 'mcupr_manage_roles', 'mcupr-roles', 'mcupr_roles_page' );
	},
	28
);

add_action(
	'admin_enqueue_scripts',
	static function ( $hook ) {
		if ( false === strpos( (string) $hook, 'mcupr-roles' ) ) {
			return;
		}
		wp_enqueue_style( 'mcupr-roles', get_theme_file_uri( 'assets/css/roles.css' ), array(), MCUPR_VERSION );
		wp_enqueue_script( 'mcupr-roles', get_theme_file_uri( 'assets/js/roles.js' ), array(), MCUPR_VERSION, true );
		wp_localize_script(
			'mcupr-roles',
			'mcuprRoles',
			array(
				'nothingFound' => __( 'Nothing matches what you typed.', 'mcupr' ),
				'changed'      => __( 'There are changes you have not saved yet.', 'mcupr' ),
				'confirmRisky' => __( 'This capability lets somebody take the whole site over. Give it to this role?', 'mcupr' ),
			)
		);
	}
);

/* ================================================================== saving */

/** The capability grid was saved. */
add_action(
	'admin_post_mcupr_roles_save',
	static function () {
		if ( ! current_user_can( 'mcupr_manage_roles' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcupr_roles' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$want    = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array();
		$defs    = mcupr_cap_defs();
		$changed = 0;
		foreach ( mcupr_roles_listed() as $key => $info ) {
			if ( 'administrator' === $key ) {
				continue; // always everything (inc/roles.php)
			}
			$role = get_role( $key );
			if ( ! $role ) {
				continue;
			}
			$ticked = isset( $want[ $key ] ) && is_array( $want[ $key ] ) ? array_map( 'sanitize_key', array_keys( $want[ $key ] ) ) : array();
			foreach ( $defs as $cap => $def ) {
				$has = ! empty( $info['capabilities'][ $cap ] );
				$set = in_array( $cap, $ticked, true );
				if ( $has === $set ) {
					continue;
				}
				if ( $set ) {
					$role->add_cap( $cap );
				} else {
					$role->remove_cap( $cap );
				}
				$changed++;
			}
		}
		mcupr_roles_arm_admin();
		mcupr_roles_back( array( 'saved' => $changed ) );
	}
);

/** A new role. */
add_action(
	'admin_post_mcupr_roles_add',
	static function () {
		if ( ! current_user_can( 'mcupr_manage_roles' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcupr_roles_add' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$name = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
		$like = isset( $_POST['role_like'] ) ? sanitize_key( wp_unslash( $_POST['role_like'] ) ) : '';
		// phpcs:enable
		if ( '' === $name ) {
			mcupr_roles_back( array( 'err' => 'name' ) );
		}
		$key = 'mcupr_r_' . substr( sanitize_key( remove_accents( $name ) ), 0, 20 );
		if ( 'mcupr_r_' === $key ) {
			$key .= substr( md5( $name ), 0, 8 ); // a name written in Thai leaves nothing to make a key from
		}
		if ( get_role( $key ) ) {
			mcupr_roles_back( array( 'err' => 'exists' ) );
		}
		$caps = array( 'read' => true );
		$from = $like && 'none' !== $like ? get_role( $like ) : null;
		if ( $from ) {
			$caps = array_filter( (array) $from->capabilities );
			unset( $caps['mcupr_manage_roles'] ); // a new role does not start out able to give itself anything
		}
		add_role( $key, $name, $caps );
		if ( ! get_role( $key ) ) {
			mcupr_roles_back( array( 'err' => 'add' ) );
		}
		$mine = mcupr_custom_roles();
		$mine[ $key ] = $name;
		update_option( 'mcupr_custom_roles', $mine, false );
		mcupr_roles_back( array( 'added' => 1, 'role' => $key ) );
	}
);

/** Put one of the theme's roles back the way it came. */
add_action(
	'admin_post_mcupr_roles_reset',
	static function () {
		if ( ! current_user_can( 'mcupr_manage_roles' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcupr_roles_reset' );
		$key = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! mcupr_is_standard_role( $key ) || ! mcupr_role_from_standard( $key ) ) {
			mcupr_roles_back( array( 'err' => 'reset' ) );
		}
		mcupr_roles_back( array( 'reset' => 1, 'role' => $key ) );
	}
);

/** Delete a role made on this site. Its people are moved to another one. */
add_action(
	'admin_post_mcupr_roles_delete',
	static function () {
		if ( ! current_user_can( 'mcupr_manage_roles' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mcupr' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcupr_roles_delete' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$key  = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$move = isset( $_POST['move_to'] ) ? sanitize_key( wp_unslash( $_POST['move_to'] ) ) : '';
		// phpcs:enable
		if ( 'custom' !== mcupr_role_origin( $key ) || ! get_role( $key ) ) {
			mcupr_roles_back( array( 'err' => 'delete' ) );
		}
		$users = get_users( array( 'role' => $key, 'fields' => 'ID', 'number' => 500 ) );
		if ( $users ) {
			if ( ! $move || ! get_role( $move ) || $move === $key ) {
				mcupr_roles_back( array( 'err' => 'move' ) );
			}
			foreach ( $users as $uid ) {
				$u = get_userdata( $uid );
				if ( $u ) {
					$u->remove_role( $key );
					$u->add_role( $move );
				}
			}
		}
		remove_role( $key );
		$mine = mcupr_custom_roles();
		unset( $mine[ $key ] );
		$mine ? update_option( 'mcupr_custom_roles', $mine, false ) : delete_option( 'mcupr_custom_roles' );
		mcupr_roles_back( array( 'deleted' => count( $users ) + 1 ) );
	}
);

/** Back to the page with a word about what happened. */
function mcupr_roles_back( $args = array() ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=mcupr-roles' ) ) );
	exit;
}

/* ================================================================== the page */

function mcupr_roles_page() {
	if ( ! current_user_can( 'mcupr_manage_roles' ) ) {
		return;
	}
	$roles  = mcupr_roles_listed();
	$counts = mcupr_roles_user_counts();
	$defs   = mcupr_cap_defs();
	$groups = mcupr_cap_groups();
	$std    = mcupr_standard_roles();
	$act    = esc_url( admin_url( 'admin-post.php' ) );

	echo '<div class="wrap mcupr-roles"><h1>' . esc_html__( 'User roles', 'mcupr' ) . '</h1>';
	mcupr_roles_notices();
	echo '<p class="mr-lede">' . esc_html__( 'Every role on the site is a column, every thing somebody can do is a row. Tick what each role may do and press save. An administrator is not in your hands: they always have everything, so the site can never be locked out of its own settings.', 'mcupr' ) . '</p>';

	// ---- the cards
	echo '<div class="mr-cards">';
	foreach ( $roles as $key => $info ) {
		$origin = mcupr_role_origin( $key );
		$n      = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
		$note   = isset( $std[ $key ]['note'] ) ? $std[ $key ]['note'] : mcupr_role_core_note( $key );
		echo '<div class="mr-card mr-card-' . esc_attr( $origin ) . ( 'administrator' === $key ? ' is-locked' : '' ) . '">';
		echo '<h3>' . esc_html( translate_user_role( $info['name'] ) ) . '</h3>';
		/* translators: %d: how many people hold the role */
		echo '<p class="mr-who">' . esc_html( sprintf( __( '%d people', 'mcupr' ), $n ) ) . '</p>';
		if ( '' !== $note ) {
			echo '<p class="mr-note">' . esc_html( $note ) . '</p>';
		}
		echo '<p class="mr-tag">' . esc_html( 'standard' === $origin ? __( 'Comes with the theme', 'mcupr' ) : ( 'core' === $origin ? __( 'Comes with WordPress', 'mcupr' ) : __( 'Made on this site', 'mcupr' ) ) ) . '</p>';
		echo '<p class="mr-card-actions">';
		if ( $n > 0 ) {
			echo '<a class="button button-small" href="' . esc_url( admin_url( 'users.php?role=' . $key ) ) . '">' . esc_html__( 'See who holds it', 'mcupr' ) . '</a> ';
		}
		if ( 'standard' === $origin ) {
			echo '<form method="post" action="' . $act . '" class="mr-inline" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Put this role back exactly the way the theme made it? What you ticked for it is lost.', 'mcupr' ) ) ) . ')">';
			wp_nonce_field( 'mcupr_roles_reset' );
			echo '<input type="hidden" name="action" value="mcupr_roles_reset"><input type="hidden" name="role" value="' . esc_attr( $key ) . '">';
			echo '<button class="button button-small">' . esc_html__( 'Put back to standard', 'mcupr' ) . '</button></form>';
		} elseif ( 'custom' === $origin ) {
			echo '<form method="post" action="' . $act . '" class="mr-inline" onsubmit="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this role?', 'mcupr' ) ) ) . ')">';
			wp_nonce_field( 'mcupr_roles_delete' );
			echo '<input type="hidden" name="action" value="mcupr_roles_delete"><input type="hidden" name="role" value="' . esc_attr( $key ) . '">';
			if ( $n > 0 ) {
				echo '<label class="mr-move">' . esc_html__( 'Move its people to', 'mcupr' ) . ' <select name="move_to">';
				foreach ( $roles as $k2 => $i2 ) {
					if ( $k2 !== $key ) {
						echo '<option value="' . esc_attr( $k2 ) . '">' . esc_html( translate_user_role( $i2['name'] ) ) . '</option>';
					}
				}
				echo '</select></label> ';
			}
			echo '<button class="button button-small">' . esc_html__( 'Delete', 'mcupr' ) . '</button></form>';
		}
		echo '</p></div>';
	}
	echo '</div>';

	// ---- a new role
	echo '<details class="mr-add"><summary>' . esc_html__( 'Add a role', 'mcupr' ) . '</summary>';
	echo '<form method="post" action="' . $act . '" class="mr-add-form">';
	wp_nonce_field( 'mcupr_roles_add' );
	echo '<input type="hidden" name="action" value="mcupr_roles_add">';
	echo '<p><label>' . esc_html__( 'What it is called', 'mcupr' ) . '<br><input type="text" name="role_name" required maxlength="60" placeholder="' . esc_attr__( 'e.g. Librarian', 'mcupr' ) . '"></label></p>';
	echo '<p><label>' . esc_html__( 'Start from', 'mcupr' ) . '<br><select name="role_like"><option value="none">' . esc_html__( 'Nothing (only signing in)', 'mcupr' ) . '</option>';
	foreach ( $roles as $k2 => $i2 ) {
		if ( 'administrator' !== $k2 ) {
			echo '<option value="' . esc_attr( $k2 ) . '">' . esc_html( translate_user_role( $i2['name'] ) ) . '</option>';
		}
	}
	echo '</select></label></p>';
	echo '<p class="description">' . esc_html__( 'A role copied from another one does not get the right to change this page: give it that yourself if you mean to.', 'mcupr' ) . '</p>';
	submit_button( __( 'Add the role', 'mcupr' ), 'secondary', 'submit', false );
	echo '</form></details>';

	// ---- the table
	echo '<form method="post" action="' . $act . '" id="mr-form">';
	wp_nonce_field( 'mcupr_roles' );
	echo '<input type="hidden" name="action" value="mcupr_roles_save">';
	echo '<div class="mr-bar"><label class="mr-search"><span class="screen-reader-text">' . esc_html__( 'Find a capability', 'mcupr' ) . '</span>';
	echo '<input type="search" id="mr-search" placeholder="' . esc_attr__( 'Find a capability…', 'mcupr' ) . '" autocomplete="off"></label>';
	echo '<span class="mr-bar-right"><span class="mr-dirty" hidden></span>';
	submit_button( __( 'Save', 'mcupr' ), 'primary', 'submit', false );
	echo '</span></div>';

	echo '<div class="mr-wrap"><table class="mr-table"><thead><tr><th scope="col" class="mr-cap-head">' . esc_html__( 'What it lets somebody do', 'mcupr' ) . '</th>';
	foreach ( $roles as $key => $info ) {
		$locked = 'administrator' === $key;
		echo '<th scope="col" class="mr-role-head' . ( $locked ? ' is-locked' : '' ) . '"><span class="mr-role-name">' . esc_html( translate_user_role( $info['name'] ) ) . '</span>';
		if ( $locked ) {
			echo '<span class="mr-all">' . esc_html__( 'always everything', 'mcupr' ) . '</span>';
		} else {
			echo '<button type="button" class="mr-col" data-role="' . esc_attr( $key ) . '">' . esc_html__( 'all / none', 'mcupr' ) . '</button>';
		}
		echo '</th>';
	}
	echo '</tr></thead>';

	foreach ( $groups as $gkey => $glabel ) {
		$rows = array();
		foreach ( $defs as $cap => $def ) {
			if ( $def['group'] === $gkey ) {
				$rows[ $cap ] = $def;
			}
		}
		if ( ! $rows ) {
			continue;
		}
		echo '<tbody class="mr-group" data-group="' . esc_attr( $gkey ) . '"><tr class="mr-group-row"><th colspan="' . ( count( $roles ) + 1 ) . '" scope="colgroup">' . esc_html( $glabel ) . '</th></tr>';
		foreach ( $rows as $cap => $def ) {
			$search = $def['label'] . ' ' . $def['note'] . ' ' . $cap;
			echo '<tr class="mr-row' . ( $def['danger'] ? ' is-risky' : '' ) . '" data-find="' . esc_attr( mb_strtolower( $search ) ) . '">';
			echo '<th scope="row"><span class="mr-label">' . esc_html( $def['label'] );
			if ( $def['danger'] ) {
				echo ' <span class="mr-flag" title="' . esc_attr__( 'Can be used to take the site over', 'mcupr' ) . '">' . esc_html__( 'sensitive', 'mcupr' ) . '</span>';
			}
			if ( ! empty( $def['core'] ) ) {
				echo ' <span class="mr-core">' . esc_html__( 'WordPress', 'mcupr' ) . '</span>';
			}
			echo '</span>';
			if ( '' !== $def['note'] ) {
				echo '<span class="mr-cap-note">' . esc_html( $def['note'] ) . '</span>';
			}
			echo '<code class="mr-cap-key">' . esc_html( $cap ) . '</code>';
			echo '<button type="button" class="mr-rowall" data-cap="' . esc_attr( $cap ) . '">' . esc_html__( 'all / none', 'mcupr' ) . '</button></th>';
			foreach ( $roles as $key => $info ) {
				$has = ! empty( $info['capabilities'][ $cap ] );
				if ( 'administrator' === $key ) {
					echo '<td class="mr-cell is-locked"><span class="mr-yes" aria-label="' . esc_attr__( 'yes', 'mcupr' ) . '"></span></td>';
					continue;
				}
				$id = 'mr-' . $key . '-' . $cap;
				echo '<td class="mr-cell"><input type="checkbox" id="' . esc_attr( $id ) . '" name="caps[' . esc_attr( $key ) . '][' . esc_attr( $cap ) . ']" value="1"' . checked( $has, true, false ) . ' data-role="' . esc_attr( $key ) . '" data-cap="' . esc_attr( $cap ) . '"' . ( $def['danger'] ? ' data-risky="1"' : '' ) . '>';
				echo '<label for="' . esc_attr( $id ) . '" class="screen-reader-text">' . esc_html( $def['label'] . ' – ' . translate_user_role( $info['name'] ) ) . '</label></td>';
			}
			echo '</tr>';
		}
		echo '</tbody>';
	}
	echo '</table></div>';
	echo '<p class="mr-empty" hidden></p>';
	echo '<p class="mr-foot">';
	submit_button( __( 'Save', 'mcupr' ), 'primary', 'submit', false );
	echo '</p></form></div>';
}

/** A line about what one of WordPress' own roles is for. */
function mcupr_role_core_note( $key ) {
	$notes = array(
		'administrator' => __( 'Everything, including the settings of WordPress itself. Give it to as few people as possible.', 'mcupr' ),
		'editor'        => __( 'WordPress\' own content role: writes and publishes anybody\'s posts and pages.', 'mcupr' ),
		'author'        => __( 'WordPress\' own content role: writes and publishes their own posts.', 'mcupr' ),
		'contributor'   => __( 'WordPress\' own content role: writes posts but cannot publish them.', 'mcupr' ),
		'subscriber'    => __( 'Signs in and reads. Nothing else.', 'mcupr' ),
	);
	return isset( $notes[ $key ] ) ? $notes[ $key ] : '';
}

/** What happened last time. */
function mcupr_roles_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['saved'] ) ) {
		$n = (int) $_GET['saved'];
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $n > 0 ? sprintf( /* translators: %d: how many ticks changed */ __( 'Saved. %d change(s).', 'mcupr' ), $n ) : __( 'Saved. Nothing had changed.', 'mcupr' ) ) . '</p></div>';
	}
	if ( isset( $_GET['added'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The role was added. Tick what it may do below.', 'mcupr' ) . '</p></div>';
	}
	if ( isset( $_GET['reset'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The role is back the way the theme made it.', 'mcupr' ) . '</p></div>';
	}
	if ( isset( $_GET['deleted'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The role was deleted.', 'mcupr' ) . '</p></div>';
	}
	if ( isset( $_GET['err'] ) ) {
		$errs = array(
			'name'   => __( 'Give the role a name.', 'mcupr' ),
			'exists' => __( 'There is already a role with that name.', 'mcupr' ),
			'add'    => __( 'The role could not be added.', 'mcupr' ),
			'reset'  => __( 'That role cannot be put back to a standard, because the theme did not make it.', 'mcupr' ),
			'delete' => __( 'Only a role made on this site can be deleted.', 'mcupr' ),
			'move'   => __( 'People still hold that role: choose where to move them first.', 'mcupr' ),
		);
		$k = sanitize_key( wp_unslash( $_GET['err'] ) );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( isset( $errs[ $k ] ) ? $errs[ $k ] : __( 'That did not work.', 'mcupr' ) ) . '</p></div>';
	}
	// phpcs:enable
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'WordPress\' own roles are in this table too', 'mcupr' ) . '</strong> — ' . esc_html__( 'Editor, Author, Contributor and Subscriber. Changing them changes what plugins let those people do as well, so prefer the roles of the theme for the people of the university and leave WordPress\' own as they are.', 'mcupr' ) . '</p></div>';
}
