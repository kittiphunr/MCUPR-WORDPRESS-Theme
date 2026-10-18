<?php
/**
 * The WordPress toolbar (the black bar) for signed-in editors, as a floating icon.
 *
 * In place of the bar across the top of every page there is one round button at the bottom left, next to the "collapse menu" button;
 * it opens a card with what the bar had, in groups: this page, add new, the site, my account (and anything else a plugin put in the bar).
 * The items are taken from the real toolbar, so they follow the signed-in person's rights and whatever else adds to the bar. The bar itself is not
 * drawn and does not push the page down. Customizer › "MCUPR: Animation, news & reading" › "Toolbar for editors": floating icon (default) or the WordPress bar.
 *
 * @package MCUPR
 */

defined( 'ABSPATH' ) || exit;

/** 'float' (the icon) or 'wp' (the bar of WordPress). */
function mcupr_toolbar_mode() {
	$m = get_theme_mod( 'mcupr_toolbar', 'float' );
	return 'wp' === $m ? 'wp' : 'float';
}

/** True on the site (not the admin) when the icon takes the place of the bar. */
function mcupr_toolbar_floats() {
	return ! is_admin() && 'float' === mcupr_toolbar_mode() && function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing();
}

// The page is not pushed down by the bar, and the bar is not drawn.
add_action(
	'after_setup_theme',
	static function () {
		if ( 'float' === mcupr_toolbar_mode() ) {
			add_theme_support( 'admin-bar', array( 'callback' => '__return_false' ) );
		}
	},
	20
);
add_action(
	'wp',
	static function () {
		if ( mcupr_toolbar_floats() ) {
			remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
			remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 ); // (older themes without wp_body_open)
			add_action( 'wp_footer', 'mcupr_toolbar_render', 1000 );
		}
	}
);
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( mcupr_toolbar_floats() ) {
			wp_dequeue_style( 'admin-bar' );
			wp_dequeue_script( 'admin-bar' );
		}
	},
	100
);
add_filter(
	'body_class',
	static function ( $c ) {
		return mcupr_toolbar_floats() ? array_values( array_diff( $c, array( 'admin-bar' ) ) ) : $c;
	},
	20
);

/** The text of a toolbar item: its title without the little pictures and the text for screen readers that comes with them. */
function mcupr_toolbar_label( $node ) {
	$t = (string) $node->title;
	$t = preg_replace( '#<span[^>]*screen-reader-text[^>]*>.*?</span>#is', '', $t );
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $t ) ) );
	if ( ( '' === $t || ctype_digit( $t ) ) && preg_match( '#<span[^>]*screen-reader-text[^>]*>(.*?)</span>#is', (string) $node->title, $m ) ) { // a bare number (comments, updates): the words that go with it
		$t = trim( wp_strip_all_tags( $m[1] ) );
	}
	if ( '' === $t && ! empty( $node->meta['title'] ) ) {
		$t = (string) $node->meta['title'];
	}
	return '' !== $t ? $t : (string) $node->id;
}

/**
 * The groups of the card, from the toolbar's items: array( heading => array( array( label, href, class, target ), … ) ).
 * $nodes: the toolbar's items by id (WP_Admin_Bar::get_nodes()).
 */
function mcupr_toolbar_groups( $nodes ) {
	$nodes = is_array( $nodes ) ? $nodes : array();
	$used  = array( 'wp-logo' => 1, 'search' => 1, 'top-secondary' => 1, 'my-account' => 1, 'user-actions' => 1, 'user-info' => 1, 'site-name' => 1, 'appearance' => 1 );
	$kids  = static function ( $parent ) use ( $nodes ) {
		$o = array();
		foreach ( $nodes as $n ) {
			if ( $n->parent === $parent && $n->href ) {
				$o[] = $n;
			}
		}
		return $o;
	};
	$item = static function ( $n, $label = '' ) use ( &$used ) {
		$used[ $n->id ] = 1;
		$cl = ! empty( $n->meta['class'] ) ? (string) $n->meta['class'] : '';
		return array( '' !== $label ? $label : mcupr_toolbar_label( $n ), (string) $n->href, $cl, ! empty( $n->meta['target'] ) ? (string) $n->meta['target'] : '' );
	};
	$g = array( 'page' => array(), 'add' => array(), 'site' => array(), 'other' => array(), 'me' => array() );
	foreach ( array( 'edit', 'view', 'preview', 'archive' ) as $id ) {
		if ( isset( $nodes[ $id ] ) && $nodes[ $id ]->href ) {
			$g['page'][] = $item( $nodes[ $id ] );
		}
	}
	if ( isset( $nodes['new-content'] ) ) {
		foreach ( $kids( 'new-content' ) as $n ) {
			$g['add'][] = $item( $n );
		}
		$used['new-content'] = 1;
	}
	if ( current_user_can( 'edit_pages' ) ) {
		$g['site'][] = array( __( 'MCUPR control center', 'mcupr' ), admin_url( 'admin.php?page=mcupr' ), '', '' );
	}
	foreach ( array( 'dashboard', 'customize', 'themes', 'widgets', 'menus', 'background', 'header', 'mcupr-maint', 'comments', 'updates' ) as $id ) {
		if ( isset( $nodes[ $id ] ) && $nodes[ $id ]->href ) {
			$g['site'][] = $item( $nodes[ $id ] );
		}
	}
	if ( isset( $nodes['site-name'] ) && $nodes['site-name']->href && ! isset( $nodes['dashboard'] ) ) {
		$g['site'][] = $item( $nodes['site-name'] );
	}
	foreach ( array( 'edit-profile', 'logout' ) as $id ) {
		if ( isset( $nodes[ $id ] ) && $nodes[ $id ]->href ) {
			$g['me'][] = $item( $nodes[ $id ] );
		}
	}
	foreach ( $nodes as $id => $n ) { // whatever else is in the bar (plugins): by what is there, not by name
		if ( isset( $used[ $id ] ) || ! $n->href || in_array( $n->parent, array( 'wp-logo', 'wp-logo-external' ), true ) ) { // the WordPress logo menu (about, documentation, support) is left out
			continue;
		}
		$g['other'][] = $item( $n );
	}
	$heads = array(
		'page'  => __( 'This page', 'mcupr' ),
		'add'   => __( 'Add new', 'mcupr' ),
		'site'  => __( 'The website', 'mcupr' ),
		'other' => __( 'More', 'mcupr' ),
		'me'    => __( 'My account', 'mcupr' ),
	);
	$out = array();
	foreach ( $g as $k => $list ) {
		if ( $list ) {
			$out[ $heads[ $k ] ] = $list;
		}
	}
	return $out;
}

/** The icon and the card, at the end of the page. */
function mcupr_toolbar_render() {
	global $wp_admin_bar;
	if ( ! is_object( $wp_admin_bar ) || ! mcupr_toolbar_floats() ) {
		return;
	}
	$wp_admin_bar->initialize();
	$wp_admin_bar->add_menus();
	do_action_ref_array( 'admin_bar_menu', array( &$wp_admin_bar ) );
	$groups = mcupr_toolbar_groups( $wp_admin_bar->get_nodes() );
	$u      = wp_get_current_user();
	if ( ! $groups ) {
		return;
	}
	$id = 'mcupr-cc-panel';
	echo '<div id="mcupr-cc" class="cc print:hidden"><button type="button" class="cc-fab" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" title="' . esc_attr__( 'Tools for editors', 'mcupr' ) . '">' . mcupr_icon( 'edit', 22 ) . '<span class="sr-only">' . esc_html__( 'Tools for editors', 'mcupr' ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<nav id="' . esc_attr( $id ) . '" class="cc-panel" aria-label="' . esc_attr__( 'Tools for editors', 'mcupr' ) . '" hidden>';
	echo '<p class="cc-who">' . esc_html( $u->display_name ) . '</p>';
	foreach ( $groups as $head => $list ) {
		echo '<section class="cc-g' . ( count( $list ) > 6 ? ' cc-chips' : '' ) . '"><h2 class="cc-h">' . esc_html( $head ) . '</h2><ul>';
		foreach ( $list as $it ) {
			echo '<li><a href="' . esc_url( $it[1] ) . '"' . ( $it[3] ? ' target="' . esc_attr( $it[3] ) . '" rel="noopener"' : '' ) . '>' . ( false !== strpos( $it[2], 'cc-maint' ) ? mcupr_feather_i( 'tool', 16 ) . ' ' : '' ) . esc_html( $it[0] ) . '</a></li>';
		}
		echo '</ul></section>';
	}
	echo '</nav></div>';
	?>
<script>(function(){var r=document.getElementById('mcupr-cc');if(!r){return;}var b=r.querySelector('.cc-fab'),p=r.querySelector('.cc-panel');function set(o){p.hidden=!o;b.setAttribute('aria-expanded',o?'true':'false');r.classList.toggle('is-open',o);}b.addEventListener('click',function(){set(p.hidden);});document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!p.hidden){set(false);b.focus();}});document.addEventListener('click',function(e){if(!p.hidden&&!r.contains(e.target)){set(false);}});})();</script>
	<?php
}
