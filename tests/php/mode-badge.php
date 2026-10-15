<?php
/** The Info button shows which special mode is on (editors only), and the Info panel lists them with the settings link.   php php/mode-badge.php [site] */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
$keep = get_option( 'mcupr_status', null );
$set  = function ( $m, $w, $n ) { $s = mcupr_status(); $s['maint']['on'] = $m; $s['welcome']['on'] = $w; $s['welcome']['from'] = ''; $s['welcome']['to'] = ''; $s['mourning']['on'] = $n; $s['mourning']['from'] = ''; $s['mourning']['to'] = ''; update_option( 'mcupr_status', $s ); };
$cluster = function () { ob_start(); mcupr_tools_cluster(); return ob_get_clean(); };
$about   = function () { ob_start(); include get_theme_file_path( 'template-parts/modals.php' ); $h = ob_get_clean(); preg_match( '#<template id="tpl-about">(.*?)</template>#s', $h, $m ); return isset( $m[1] ) ? $m[1] : ''; };

wp_set_current_user( 0 );
$set( 1, 1, 1 );
t( array() === mcupr_active_modes(), 'visitor: no modes are revealed' );
$h = $cluster();
t( false === strpos( $h, 'data-mode' ) && false === strpos( $about(), 'about-modes' ), 'visitor: the Info button is plain and the panel has no modes section' );

wp_set_current_user( 1 );
$set( 0, 0, 0 );
t( array() === mcupr_active_modes() && false === strpos( $cluster(), 'data-mode' ) && false === strpos( $about(), 'about-modes' ), 'editor, nothing on: plain button, no section' );

$set( 1, 0, 0 );
$h = $cluster();
t( array( 'maint' ) === mcupr_active_modes() && preg_match( '/id="about-toggle"[^>]*data-mode="maint"/', $h ), 'editor, maintenance: the Info button is marked maint (orange)' );
t( preg_match( '/id="about-toggle"[^>]*aria-label="[^"]*' . preg_quote( mcupr_t( 'md_maint' ), '/' ) . '/u', $h ), 'editor, maintenance: the accessible name says which mode is on (not colour alone)' );
$a = $about();
t( false !== strpos( $a, 'about-modes' ) && false !== strpos( $a, 'data-mode="maint"' ) && false !== strpos( $a, 'admin.php?page=mcupr-status' ), 'editor, maintenance: the panel lists the mode as a chip with the settings link' );
t( false === strpos( do_action_str(), 'maint-bar' ), 'the old orange strip is gone' );
function do_action_str() { ob_start(); do_action( 'wp_body_open' ); return ob_get_clean(); }
t( ! in_array( 'has-maint', get_body_class(), true ), 'no body class that makes room for a strip' );

$set( 1, 1, 1 );
$h = $cluster();
t( array( 'maint', 'welcome', 'mourning' ) === mcupr_active_modes() && false !== strpos( $h, 'data-modes="maint welcome mourning"' ), 'all three on: maintenance wins the colour, all are listed' );
t( 3 === substr_count( $about(), 'class="mode-chip"' ), 'all three on: three chips in the panel' );

$set( 0, 0, 1 );
t( array( 'mourning' ) === mcupr_active_modes(), 'mourning only' );
$s = mcupr_status(); $s['mourning']['from'] = '2999-01-01'; $s['mourning']['to'] = '2999-01-02'; update_option( 'mcupr_status', $s );
t( array() === mcupr_active_modes(), 'mourning set for dates not reached yet: not counted as on' );

$css = file_get_contents( get_theme_file_path( 'assets/css/tailwind.css' ) );
t( false !== strpos( $css, '#about-toggle#about-toggle[data-mode=maint]' ) && false !== strpos( $css, '#b45309' ) && false === strpos( $css, '.maint-bar' ), 'css: orange Info button for maintenance, old strip css removed' );
t( false === strpos( file_get_contents( get_theme_file_path( 'assets/js/main.js' ) ), 'maint-h' ), 'js: strip height code removed' );
if ( null === $keep ) { delete_option( 'mcupr_status' ); } else { update_option( 'mcupr_status', $keep ); }
wp_set_current_user( 0 );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
