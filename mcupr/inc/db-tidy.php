<?php
/**
 * Tidying what the theme keeps in the options table, done once per site.
 *
 * WordPress reads every "autoload" option on every request, admin and front end alike, so an option that is wanted on
 * a few screens — or one that grows with the content — costs every visitor something. Several of the theme's options
 * ended up autoloaded, some because update_option() was called without its third argument (which makes a new option
 * autoload). They are written with false from now on, and the rows that already exist are put right here.
 *
 * An option cannot simply be re-saved with a different autoload: older WordPress leaves the column alone when the
 * value has not changed. So each one is deleted and added again, which does change the column on every version the
 * theme supports. The derived ones are not kept at all: they are transients now and work themselves out again.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

const MCUPR_DB_TIDY = 2;

/** Options that must keep their value but stop being read on every request. */
function mcupr_db_tidy_keep() {
	return array( 'mcupr_forum', 'mcupr_staff', 'mcupr_honors', 'mcupr_office_role_v', 'mcupr_student_roles_v', 'mcupr_forum_boards_seeded' );
}

/** Options that held something the theme can work out again, and are kept as transients now. */
function mcupr_db_tidy_drop() {
	return array(
		'mcupr_aud_index2',     // the index of the content kept for students (rebuilt on demand, now not autoloaded)
		'mcupr_aud_index',      // the same thing in its oldest form (levels only), never written since
		'mcupr_pubcounts',      // published posts per type (one COUNT(*), now a transient)
		'mcupr_office_hidden',  // the offices kept off the main site (now a transient)
	);
}

function mcupr_db_tidy_run() {
	foreach ( mcupr_db_tidy_keep() as $name ) {
		$value = get_option( $name, null );
		if ( null === $value ) {
			continue;
		}
		delete_option( $name );
		add_option( $name, $value, '', false );
	}
	foreach ( mcupr_db_tidy_drop() as $name ) {
		delete_option( $name );
	}
	update_option( 'mcupr_db_tidy', MCUPR_DB_TIDY, false );
}

add_action(
	'admin_init',
	static function () {
		if ( (int) get_option( 'mcupr_db_tidy', 0 ) < MCUPR_DB_TIDY ) {
			mcupr_db_tidy_run();
		}
	},
	1
);
