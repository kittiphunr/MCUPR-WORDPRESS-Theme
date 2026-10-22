<?php
/**
 * Media folders: making, renaming, moving under another folder, deleting, putting files in, the lists that filter by folder,
 * counts, the file's own field, bulk moves, uploads into a folder, and who may do what.
 *   php php/media-folders.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
foreach ( mcupr_mf_folders() as $old ) { wp_delete_term( $old['id'], MCUPR_MF ); }
wp_set_current_user( 1 );

// a few files of our own
$mk = static function ( $name, $mime = 'image/jpeg' ) { return wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => $mime, 'post_status' => 'inherit', 'guid' => home_url( '/' . $name ) ) ); };
$a = $mk( 'mf-a.jpg' ); $b = $mk( 'mf-b.jpg' ); $c = $mk( 'mf-c.pdf', 'application/pdf' ); $d = $mk( 'mf-d.jpg' );
$mine = array( $a, $b, $c, $d );

t( taxonomy_exists( MCUPR_MF ) && in_array( 'attachment', get_taxonomy( MCUPR_MF )->object_type, true ) && get_taxonomy( MCUPR_MF )->hierarchical && ! get_taxonomy( MCUPR_MF )->public, 'folders: a hierarchical, non-public taxonomy of attachments' );

// ---- make, name, nest
$news = mcupr_mf_create( 'ข่าวประชาสัมพันธ์' );
$y69  = mcupr_mf_create( 'ปี 2569', $news );
$y68  = mcupr_mf_create( 'ปี 2568', $news );
$docs = mcupr_mf_create( 'เอกสาร' );
t( is_int( $news ) && is_int( $y69 ) && is_int( $docs ) && (int) get_term( $y69, MCUPR_MF )->parent === $news, 'create: folders and sub-folders' );
t( is_wp_error( mcupr_mf_create( 'ปี 2569', $news ) ) && 'dup' === mcupr_mf_create( 'ปี 2569', $news )->get_error_code(), 'create: the same name twice under one parent is refused' );
t( is_int( mcupr_mf_create( 'ปี 2569', $docs ) ), 'create: the same name under another parent is fine' );
t( is_wp_error( mcupr_mf_create( '   ' ) ) && is_wp_error( mcupr_mf_create( str_repeat( 'ก', 81 ) ) ) && is_wp_error( mcupr_mf_create( 'x', 999999 ) ), 'create: an empty name, a name over 80 characters and a missing parent are refused' );
$x = mcupr_mf_create( '<b>bold</b><script>alert(1)</script>' );
t( is_int( $x ) && false === strpos( get_term( $x, MCUPR_MF )->name, '<' ), 'create: tags are stripped from a folder name' );
wp_delete_term( $x, MCUPR_MF );

// ---- files in folders
mcupr_mf_move( array( $a, $b ), $y69 );
mcupr_mf_move( array( $c ), $docs );
t( mcupr_mf_of( $a ) === $y69 && mcupr_mf_of( $c ) === $docs && 0 === mcupr_mf_of( $d ), 'move: files are put in a folder (one each); no folder is 0' );
mcupr_mf_move( array( $a ), $y68 );
t( mcupr_mf_of( $a ) === $y68 && 1 === count( wp_get_object_terms( $a, MCUPR_MF ) ), 'move: a file is in one folder only – moving replaces' );
t( 0 === mcupr_mf_move( array( $a ), 999999 ) && mcupr_mf_of( $a ) === $y68, 'move: a folder that does not exist moves nothing' );
t( 0 === mcupr_mf_move( array( get_option( 'page_on_front' ) ?: 1, -4, 0 ), $docs ), 'move: only attachments move' );
mcupr_mf_move( array( $a ), $y69 );

// ---- counts and the lists
$cn = mcupr_mf_counts();
t( 2 === $cn['folders'][ $y69 ] && 2 === $cn['folders'][ $news ] && 1 === $cn['folders'][ $docs ] && 0 === $cn['folders'][ $y68 ], 'counts: a folder counts the files below it too (' . $cn['folders'][ $news ] . ' in the parent)' );
t( $cn['all'] >= 4 && $cn['none'] >= 1 && $cn['all'] > $cn['none'], 'counts: all files and the unfiled' );
$ids = static function ( $args ) { return array_values( array_intersect( (array) ( new WP_Query( array_merge( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ), $args ) ) )->posts, $GLOBALS['mine'] ) ); };
$GLOBALS['mine'] = $mine;
$q1 = $ids( array( 'tax_query' => mcupr_mf_tax_query( $news ) ) );
t( 2 === count( $q1 ) && in_array( $a, $q1, true ) && in_array( $b, $q1, true ), 'list: a folder shows its files and those of its sub-folders' );
t( array( $d ) === $ids( array( 'tax_query' => mcupr_mf_tax_query( 'none' ) ) ), 'list: "unfiled" shows only files with no folder' );
t( array() === $ids( array( 'tax_query' => mcupr_mf_tax_query( $y68 ) ) ), 'list: an empty folder shows nothing' );
t( 'none' === mcupr_mf_asked( 'none' ) && 7 === mcupr_mf_asked( '7' ) && '' === mcupr_mf_asked( '-3' ) && '' === mcupr_mf_asked( 'x' ) && '' === mcupr_mf_asked( '' ), 'list: the folder asked for is a number, "none" or nothing' );
// the Ajax query of the grid and pop-up
$_REQUEST['query'] = array( 'mcupr_folder' => (string) $docs );
$args = apply_filters( 'ajax_query_attachments_args', array( 'post_type' => 'attachment' ) );
t( ! empty( $args['tax_query'] ) && (int) $args['tax_query'][0]['terms'][0] === $docs, 'grid: the Ajax query gets the folder' );
$_REQUEST['query'] = array( 'mcupr_folder' => '' );
t( empty( apply_filters( 'ajax_query_attachments_args', array() )['tax_query'] ), 'grid: no folder asked, no filter' );
unset( $_REQUEST['query'] );

// ---- the file's own field and saving it
$f = apply_filters( 'attachment_fields_to_edit', array(), get_post( $c ) );
t( isset( $f['mcupr_folder'] ) && false !== strpos( $f['mcupr_folder']['html'], 'value="' . $docs . '" selected' ) && false !== strpos( $f['mcupr_folder']['html'], '— ' ), 'field: the file\'s details have a Folder select with the current one chosen and the tree indented' );
apply_filters( 'attachment_fields_to_save', array( 'ID' => $c ), array( 'mcupr_folder' => (string) $y68 ) );
t( mcupr_mf_of( $c ) === $y68, 'field: saving the details moves the file' );
apply_filters( 'attachment_fields_to_save', array( 'ID' => $c ), array( 'mcupr_folder' => '0' ) );
t( 0 === mcupr_mf_of( $c ), 'field: "no folder" takes it out' );
mcupr_mf_move( array( $c ), $docs );

// ---- rename, move a folder, delete
t( true === mcupr_mf_rename( $docs, 'เอกสารและแบบฟอร์ม' ) && 'เอกสารและแบบฟอร์ม' === get_term( $docs, MCUPR_MF )->name, 'rename: works' );
t( is_wp_error( mcupr_mf_rename( $y68, 'ปี 2569' ) ) && is_wp_error( mcupr_mf_rename( $y68, '' ) ), 'rename: a name already used there, or empty, is refused' );
t( true === mcupr_mf_reparent( $y68, $docs ) && (int) get_term( $y68, MCUPR_MF )->parent === $docs, 'folder move: a folder goes under another' );
t( is_wp_error( mcupr_mf_reparent( $docs, $y68 ) ) && is_wp_error( mcupr_mf_reparent( $docs, $docs ) ), 'folder move: never into itself or below itself' );
t( true === mcupr_mf_reparent( $y68, 0 ) && 0 === (int) get_term( $y68, MCUPR_MF )->parent, 'folder move: to the top level' );
t( true === mcupr_mf_delete( $y69 ), 'delete: a folder with files' );
t( mcupr_mf_of( $a ) === $news && mcupr_mf_of( $b ) === $news, 'delete: its files move up to its parent, none is deleted' );
t( null !== get_post( $a ) && 'attachment' === get_post_type( $a ), 'delete: the library keeps every file' );
mcupr_mf_delete( $news );
t( 0 === mcupr_mf_of( $a ) && null !== get_post( $a ), 'delete: a top-level folder leaves its files unfiled' );
$kid = mcupr_mf_create( 'kid', $docs );
mcupr_mf_delete( $docs );
t( 0 === (int) get_term( $kid, MCUPR_MF )->parent && mcupr_mf_of( $c ) === 0, 'delete: sub-folders move up too' );
wp_delete_term( $kid, MCUPR_MF );

// ---- bulk move and uploads
$g = mcupr_mf_create( 'bulk' );
$to = apply_filters( 'handle_bulk_actions-upload', 'upload.php', 'mcupr_mf_' . $g, array( $a, $b, $c ) );
t( mcupr_mf_of( $a ) === $g && mcupr_mf_of( $b ) === $g && mcupr_mf_of( $c ) === $g, 'bulk: "Move to folder" in the list view moves every ticked file' );
t( false !== strpos( $to, 'mcupr_moved=3' ), 'bulk: the page says how many moved' );
t( isset( apply_filters( 'bulk_actions-upload', array() )[ 'mcupr_mf_' . $g ] ) && isset( apply_filters( 'bulk_actions-upload', array() )['mcupr_mf_0'] ), 'bulk: every folder and "no folder" are in the bulk actions' );
$_REQUEST['mcupr_folder'] = (string) $g;
$up = $mk( 'mf-up.jpg' );
do_action( 'add_attachment', $up );
t( mcupr_mf_of( $up ) === $g, 'upload: a new file goes into the folder being looked at' );
$_REQUEST['mcupr_folder'] = '999999';
$up2 = $mk( 'mf-up2.jpg' );
do_action( 'add_attachment', $up2 );
t( 0 === mcupr_mf_of( $up2 ), 'upload: a folder that does not exist is ignored' );
unset( $_REQUEST['mcupr_folder'] );

// ---- who may do what
$author = wp_insert_user( array( 'user_login' => 'mfauthor', 'user_pass' => wp_generate_password(), 'user_email' => 'mfauthor@example.org', 'role' => 'author' ) );
$subs   = wp_insert_user( array( 'user_login' => 'mfsubs', 'user_pass' => wp_generate_password(), 'user_email' => 'mfsubs@example.org', 'role' => 'subscriber' ) );
$own    = wp_insert_attachment( array( 'post_title' => 'mine', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_author' => $author, 'guid' => home_url( '/own.jpg' ) ) );
wp_set_current_user( $author );
t( 1 === mcupr_mf_move( array( $own ), $g ) && mcupr_mf_of( $own ) === $g, 'rights: an author moves their own file' );
t( 0 === mcupr_mf_move( array( $a ), 0 ) && mcupr_mf_of( $a ) === $g, 'rights: an author cannot move someone else\'s file' );
t( user_can( $author, 'upload_files' ) && ! user_can( $author, 'edit_others_posts' ) && ! user_can( $subs, 'upload_files' ), 'rights: authors may make and move, only editors rename and delete, subscribers do nothing' );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'mcupr_mf' );
t( false !== check_ajax_referer( 'mcupr_mf', '_wpnonce', false ), 'rights: the nonce of the folder tools is accepted' );
$_REQUEST['_wpnonce'] = 'bad';
t( false === check_ajax_referer( 'mcupr_mf', '_wpnonce', false ), 'rights: a wrong nonce is refused' );
unset( $_REQUEST['_wpnonce'] );
wp_set_current_user( $subs );
t( 0 === mcupr_mf_move( array( $own ), 0 ) && mcupr_mf_of( $own ) === $g, 'rights: a subscriber moves nothing' );
wp_set_current_user( 1 );

// ---- the script data
$sd = mcupr_mf_script_data( true );
t( ! empty( $sd['nonce'] ) && isset( $sd['folders'], $sd['counts'], $sd['t']['types'] ) && $sd['page'], 'script: the page gets folders, counts, a nonce and its texts' );

// clean up
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array_merge( $mine, array( $up, $up2, $own ) ) as $id ) { wp_delete_attachment( $id, true ); }
foreach ( mcupr_mf_folders() as $old ) { wp_delete_term( $old['id'], MCUPR_MF ); }
wp_delete_user( $author ); wp_delete_user( $subs );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
