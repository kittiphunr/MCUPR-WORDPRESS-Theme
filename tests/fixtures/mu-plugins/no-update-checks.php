<?php
/** Test sites are offline: stop WordPress' update checks, whose connection warning would break headers on redirects. */
foreach ( array( 'update_plugins', 'update_themes', 'update_core' ) as $t ) {
	add_filter( 'pre_site_transient_' . $t, static function () {
		return (object) array( 'last_checked' => time(), 'version_checked' => get_bloginfo( 'version' ), 'response' => array(), 'translations' => array(), 'no_update' => array(), 'updates' => array() );
	} );
}
