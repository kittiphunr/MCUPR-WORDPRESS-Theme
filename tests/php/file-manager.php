<?php
/**
 * The file manager of the uploads folder (inc/file-manager.php): paths, names, listing, make / rename / move / delete, what the
 * Media library owns, colour tags.   php php/file-manager.php [site]
 */
$site = isset( $argv[1] ) ? $argv[1] : __DIR__ . '/../.site-starter';
require rtrim( $site, '/' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }
wp_set_current_user( 1 );

$root = mcupr_fm_root();
t( '' !== $root && is_dir( $root ), 'the uploads folder is found' );
$base = 'fmtest-' . wp_generate_password( 6, false );

// ---- paths
foreach ( array( '../etc', 'a/../../b', "a\0b", 'a\\b', '.hidden', 'a/.git', 'mcupr-mail', 'mcupr-import/x', "a\nb" ) as $bad ) {
	t( is_wp_error( mcupr_fm_clean( $bad ) ), 'refused path: ' . json_encode( $bad ) );
}
t( 'a/b' === mcupr_fm_clean( '/a//b/' ) && '' === mcupr_fm_clean( '' ) && '2026/10' === mcupr_fm_clean( '2026/10' ), 'good paths are tidied' );
t( is_wp_error( mcupr_fm_abs( '../' ) ) && is_wp_error( mcupr_fm_abs( 'no-such-folder-xyz' ) ), 'a path that leads out or does not exist gives no real path' );
t( mcupr_fm_abs( '' ) === $root, 'the top is the uploads folder' );
// a link that leads out of uploads is refused and not listed
$lnk = $root . '/' . $base . '-link';
$out = sys_get_temp_dir() . '/fm-outside-' . $base;
mkdir( $out );
file_put_contents( $out . '/secret.txt', 'secret' );
@symlink( $out, $lnk );
if ( is_link( $lnk ) ) {
	t( is_wp_error( mcupr_fm_abs( $base . '-link' ) ) && is_wp_error( mcupr_fm_abs( $base . '-link/secret.txt' ) ), 'a link out of uploads cannot be opened' );
	$names = wp_list_pluck( mcupr_fm_list( '' ), 'name' );
	t( ! in_array( $base . '-link', $names, true ), 'and it is not listed' );
	unlink( $lnk );
} else {
	echo "  (links not possible here: skipped)\n";
}
unlink( $out . '/secret.txt' );
rmdir( $out );

// ---- names
foreach ( array( 'shell.php', 'a.php.jpg', 'x.phtml', '.htaccess', 'run.sh', 'evil.PHP', 'a.exe', "x\0.jpg", '..', '', 'a/b.jpg' ) as $bad ) {
	$r = mcupr_fm_name( $bad, true );
	t( is_wp_error( $r ) || false === strpos( $r, '/' ), 'file name refused or cleaned: ' . json_encode( $bad ) . ( is_wp_error( $r ) ? '' : ' -> ' . $r ) );
}
t( is_wp_error( mcupr_fm_name( 'shell.php', true ) ) && is_wp_error( mcupr_fm_name( 'a.php.jpg', true ) ) && is_wp_error( mcupr_fm_name( 'x.txt.exe', true ) ), 'script names are refused, also with another type after them' );
t( 'รายงานประจำปี 2569.pdf' === mcupr_fm_name( 'รายงานประจำปี 2569.pdf', true ), 'a Thai name with spaces is kept' );
t( is_wp_error( mcupr_fm_name( 'notes.zzz', true ) ), 'a type WordPress does not allow is refused' );
t( 'ข่าว 2569' === mcupr_fm_name( 'ข่าว 2569', false ), 'a folder name has no type to check' );
t( ! is_wp_error( mcupr_fm_name( 'photo.jpg', true ) ) && ! is_wp_error( mcupr_fm_name( 'plan.pdf', true ) ), 'normal files pass' );

// ---- make, list, rename, move, delete
$a = mcupr_fm_mkdir( '', $base );
t( $base === $a && is_dir( $root . '/' . $base ), 'a folder is made at the top' );
t( is_wp_error( mcupr_fm_mkdir( '', $base ) ), 'the same name twice is refused' );
$cl = mcupr_fm_mkdir( $base, '../climb' );
t( ! is_wp_error( $cl ) && 0 === strpos( $cl, $base . '/' ) && ! file_exists( $root . '/climb' ) && is_dir( $root . '/' . $cl ), 'a folder name with .. and a slash is made harmless: the folder lands inside the chosen folder' );
rmdir( $root . '/' . $cl );
$b = mcupr_fm_mkdir( $base, 'ข่าว' );
$c = mcupr_fm_mkdir( $base, 'อัลบั้ม' );
file_put_contents( $root . '/' . $base . '/note.txt', 'hello' );
file_put_contents( $root . '/' . $base . '/ข่าว/a.txt', 'a' );
$list = mcupr_fm_list( $base );
t( 3 === count( $list ) && $list[0]['dir'] && $list[1]['dir'] && ! $list[2]['dir'] && 'note.txt' === $list[2]['name'], 'the list has folders first, then files' );
t( 'doc' === $list[2]['kind'] && 5 === $list[2]['size'] && $list[2]['mtime'] > 0 && false !== strpos( $list[2]['url'], $base . '/note.txt' ), 'a file has its kind, size, time and address' );
t( 1 === $list[0]['count'] && 0 === $list[1]['count'], 'a folder says how many items it holds' );
file_put_contents( $root . '/' . $base . '/.secret', 'x' );
file_put_contents( $root . '/' . $base . '/index.php', '<?php // silence' );
t( 3 === count( mcupr_fm_list( $base ) ), 'hidden files and index.php are not listed' );
t( ! in_array( 'mcupr-mail', wp_list_pluck( mcupr_fm_list( '' ), 'name' ), true ), 'the private folders of the theme are not listed at the top' );
t( 2 === count( mcupr_fm_list( $base, true ) ), 'folders only, for the tree' );

$r = mcupr_fm_rename( $base . '/note.txt', 'บันทึก.txt' );
t( $base . '/บันทึก.txt' === $r && is_file( $root . '/' . $base . '/บันทึก.txt' ), 'a file is renamed' );
t( is_wp_error( mcupr_fm_rename( $base . '/บันทึก.txt', 'x.php' ) ), 'it cannot be renamed into a script' );
file_put_contents( $root . '/' . $base . '/other.txt', 'o' );
t( is_wp_error( mcupr_fm_rename( $base . '/บันทึก.txt', 'other.txt' ) ) && is_file( $root . '/' . $base . '/บันทึก.txt' ) && 'o' === file_get_contents( $root . '/' . $base . '/other.txt' ), 'a rename onto an existing name is refused and nothing is replaced' );
unlink( $root . '/' . $base . '/other.txt' );
t( is_wp_error( mcupr_fm_rename( '', 'x' ) ), 'the uploads folder itself cannot be renamed' );
$r = mcupr_fm_rename( $base . '/ข่าว', 'ข่าวสาร' );
t( $base . '/ข่าวสาร' === $r && is_file( $root . '/' . $base . '/ข่าวสาร/a.txt' ), 'a folder is renamed with what is inside' );

$m = mcupr_fm_move( array( $base . '/บันทึก.txt' ), $base . '/อัลบั้ม' );
t( array( $base . '/อัลบั้ม/บันทึก.txt' ) === $m && is_file( $root . '/' . $base . '/อัลบั้ม/บันทึก.txt' ), 'a file is moved into a folder' );
t( is_wp_error( mcupr_fm_move( array( $base ), $base . '/อัลบั้ม' ) ), 'a folder cannot be moved into itself or below itself' );
file_put_contents( $root . '/' . $base . '/บันทึก.txt', 'again' );
t( is_wp_error( mcupr_fm_move( array( $base . '/บันทึก.txt' ), $base . '/อัลบั้ม' ) ), 'a clash on moving is refused, nothing is replaced' );
t( 'again' === file_get_contents( $root . '/' . $base . '/บันทึก.txt' ) && 'hello' === file_get_contents( $root . '/' . $base . '/อัลบั้ม/บันทึก.txt' ), 'both files are as they were' );
t( is_wp_error( mcupr_fm_move( array( '../x' ), $base ) ) && is_wp_error( mcupr_fm_move( array( $base . '/อัลบั้ม' ), '../' ) ), 'moving cannot reach outside uploads' );

// ---- the Media library owns its files
$pic = $root . '/' . $base . '/pic.jpg';
$im  = imagecreatetruecolor( 400, 300 );
imagejpeg( $im, $pic );
$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'pic', 'post_status' => 'inherit' ), $pic );
wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $pic ) );
update_post_meta( $att, '_wp_attached_file', $base . '/pic.jpg' );
$list = mcupr_fm_list( $base );
$byname = array();
foreach ( $list as $e ) { $byname[ $e['name'] ] = $e; }
t( ! empty( $byname['pic.jpg']['locked'] ) && $att === $byname['pic.jpg']['att'], 'a file of the Media library is shown locked, with its id' );
$sized = array_filter( $list, static function ( $e ) { return preg_match( '/^pic-\d+x\d+\.jpg$/', $e['name'] ); } );
t( $sized && ! array_filter( $sized, static function ( $e ) { return ! $e['locked']; } ), 'and so are the sizes made from it' );
t( is_wp_error( mcupr_fm_rename( $base . '/pic.jpg', 'new.jpg' ) ) && is_wp_error( mcupr_fm_delete( array( $base . '/pic.jpg' ) ) ) && is_wp_error( mcupr_fm_move( array( $base . '/pic.jpg' ), $base . '/อัลบั้ม' ) ), 'it cannot be renamed, deleted or moved here' );
t( is_file( $pic ), 'the file is untouched' );
t( is_wp_error( mcupr_fm_delete( array( $base ) ) ) && is_dir( $root . '/' . $base ), 'a folder that holds a file of the Media library cannot be deleted' );
t( is_wp_error( mcupr_fm_rename( $base, $base . '-x' ) ) && is_dir( $root . '/' . $base ), 'nor renamed' );
$top = mcupr_fm_list( '' );
$flag = null;
foreach ( $top as $e ) { if ( $base === $e['name'] ) { $flag = $e['locked']; } }
t( true === $flag, 'the folder itself is shown locked' );

// ---- tags
$defs = mcupr_fm_tag_defs();
t( 7 === count( $defs ) && '#ff453a' === $defs[1]['color'] && '#8e8e93' === $defs[7]['color'], 'seven tags with the colours of the Finder' );
t( true === mcupr_fm_tag( array( $base . '/อัลบั้ม' ), array( 1, 5 ), 'add' ), 'a folder gets two tags' );
t( true === mcupr_fm_tag( array( $base . '/อัลบั้ม/บันทึก.txt' ), array( 4 ), 'add' ), 'a file gets one' );
$e = array();
foreach ( mcupr_fm_list( $base ) as $x ) { $e[ $x['name'] ] = $x; }
t( array( 1, 5 ) === $e['อัลบั้ม']['tags'], 'the list shows the tags of an item' );
t( 'hello' === file_get_contents( $root . '/' . $base . '/อัลบั้ม/บันทึก.txt' ) && 1 === mcupr_fm_tag_counts()[4], 'the counts are per tag' );
t( true === mcupr_fm_tag( array( $base . '/อัลบั้ม' ), array( 5 ), 'remove' ) && array( 1 ) === mcupr_fm_list( $base )[ array_search( 'อัลบั้ม', wp_list_pluck( mcupr_fm_list( $base ), 'name' ), true ) ]['tags'], 'a tag is taken off, the other stays' );
t( true === mcupr_fm_tag( array( $base . '/อัลบั้ม' ), array( 2 ), 'set' ) && 1 === count( mcupr_fm_tags_all()[ $base . '/อัลบั้ม' ] ), 'set replaces the tags' );
t( true === mcupr_fm_tag( array( $base . '/อัลบั้ม' ), array( 99, 0, 'x' ), 'add' ) && array( 2 ) === array_map( 'intval', mcupr_fm_tags_all()[ $base . '/อัลบั้ม' ] ), 'a tag that does not exist is ignored' );
t( is_wp_error( mcupr_fm_tag( array( '../x' ), array( 1 ), 'add' ) ), 'tags cannot be put on something outside uploads' );
// they follow
$r = mcupr_fm_rename( $base . '/อัลบั้ม', 'รูป' );
t( ! is_wp_error( $r ), 'the tagged folder is renamed' );
$map = mcupr_fm_tags_all();
t( isset( $map[ $base . '/รูป' ] ) && isset( $map[ $base . '/รูป/บันทึก.txt' ] ) && ! isset( $map[ $base . '/อัลบั้ม' ] ), 'the tags follow the rename, also those of what is inside' );
mcupr_fm_move( array( $base . '/รูป' ), $base . '/ข่าวสาร' );
t( isset( mcupr_fm_tags_all()[ $base . '/ข่าวสาร/รูป' ] ), 'and the move' );
t( 1 === count( mcupr_fm_list_tag( 4 ) ) && $base . '/ข่าวสาร/รูป/บันทึก.txt' === mcupr_fm_list_tag( 4 )[0]['path'], 'a tag lists its items from everywhere' );
t( ! is_wp_error( mcupr_fm_list_tag( 6 ) ) && array() === mcupr_fm_list_tag( 6 ), 'an unused tag lists nothing' );
update_option( 'mcupr_fm_tagnames', array( 1 => 'ต้องตรวจ' ) );
t( 'ต้องตรวจ' === mcupr_fm_tag_defs()[1]['name'] && '#ff453a' === mcupr_fm_tag_defs()[1]['color'], 'a tag can be renamed, its colour stays' );
delete_option( 'mcupr_fm_tagnames' );

// ---- the trash: a delete is a move, nothing is lost
mcupr_fm_trash_purge(); // an empty trash to begin with
$w = mcupr_fm_weigh( $root . '/' . $base . '/ข่าวสาร' );
t( $w[0] >= 2 && $w[1] >= 2 && $w[2] > 0, 'weighing says files, folders and bytes' );
$d = mcupr_fm_delete( array( $base . '/ข่าวสาร' ) );
t( ! is_wp_error( $d ) && 1 === count( $d ) && preg_match( '/^[a-f0-9]{16}$/', $d[0] ) && ! is_dir( $root . '/' . $base . '/ข่าวสาร' ), 'a delete moves the folder to the trash: it is gone from its place and has an unguessable id' );
$tid = $d[0];
t( is_dir( $root . '/.mcupr-trash/' . $tid . '/ข่าวสาร' ) && is_file( $root . '/.mcupr-trash/' . $tid . '/ข่าวสาร/a.txt' ), 'what is inside went with it, whole' );
t( is_file( $root . '/.mcupr-trash/.htaccess' ) && false !== strpos( file_get_contents( $root . '/.mcupr-trash/.htaccess' ), 'denied' ) && is_file( $root . '/.mcupr-trash/index.php' ), 'the trash is closed to the web (.htaccess and an index file)' );
t( ! in_array( '.mcupr-trash', wp_list_pluck( mcupr_fm_list( '' ), 'name' ), true ) && is_wp_error( mcupr_fm_clean( '.mcupr-trash' ) ) && is_wp_error( mcupr_fm_abs( '.mcupr-trash/' . $tid ) ), 'the trash folder is not listed and cannot be reached as a path' );
$ti = mcupr_fm_trash_items();
t( 1 === count( $ti ) && 'ข่าวสาร' === $ti[0]['name'] && $base === $ti[0]['from'] && $ti[0]['dir'] && $ti[0]['files'] >= 2 && $ti[0]['folders'] >= 2 && $ti[0]['size'] > 0 && $ti[0]['path'] === $tid && 1 === mcupr_fm_trash_count(), 'the trash lists it: name, where it was, when, how much' );
t( ! isset( mcupr_fm_tags_all()[ $base . '/ข่าวสาร/รูป' ] ) && 0 === mcupr_fm_tag_counts()[4], 'its colour tags do not count while it is in the trash' );
t( is_wp_error( mcupr_fm_delete( array( '' ) ) ) && is_wp_error( mcupr_fm_delete( array( '../x' ) ) ), 'the uploads folder itself and outside places cannot be deleted' );
// put back, with the tags
$r = mcupr_fm_trash_restore( array( $tid ) );
t( array( $base . '/ข่าวสาร' ) === $r && is_file( $root . '/' . $base . '/ข่าวสาร/a.txt' ) && 0 === mcupr_fm_trash_count() && ! is_dir( $root . '/.mcupr-trash/' . $tid ), 'put back: the folder is where it was, the trash is empty again' );
t( isset( mcupr_fm_tags_all()[ $base . '/ข่าวสาร/รูป/บันทึก.txt' ] ) && 1 === mcupr_fm_tag_counts()[4], 'the colour tags come back with it, also those of what is inside' );
// a name that is taken meanwhile gets a number; a folder that is gone is made again
$d = mcupr_fm_delete( array( $base . '/ข่าวสาร' ) );
mkdir( $root . '/' . $base . '/ข่าวสาร' );
$r = mcupr_fm_trash_restore( $d );
t( array( $base . '/ข่าวสาร 2' ) === $r && is_file( $root . '/' . $base . '/ข่าวสาร 2/a.txt' ) && is_dir( $root . '/' . $base . '/ข่าวสาร' ), 'put back onto a name that is taken: it gets a number, nothing is replaced' );
rmdir( $root . '/' . $base . '/ข่าวสาร' );
$d = mcupr_fm_delete( array( $base . '/ข่าวสาร 2/รูป' ) );
mcupr_fm_rm( $root . '/' . $base . '/ข่าวสาร 2' );
$r = mcupr_fm_trash_restore( $d );
t( array( $base . '/ข่าวสาร 2/รูป' ) === $r && is_file( $root . '/' . $base . '/ข่าวสาร 2/รูป/บันทึก.txt' ), 'put back when its folder is gone: the folder is made again' );
// a file, then deleted for good; ids are checked
file_put_contents( $root . '/' . $base . '/gone.txt', 'bye' );
$d = mcupr_fm_delete( array( $base . '/gone.txt' ) );
t( ! file_exists( $root . '/' . $base . '/gone.txt' ) && 1 === mcupr_fm_trash_count() && ! mcupr_fm_trash_items()[0]['dir'], 'a file goes to the trash too' );
t( is_wp_error( mcupr_fm_trash_restore( array( '../etc' ) ) ) && is_wp_error( mcupr_fm_trash_restore( array( 'zzzzzzzzzzzzzzzz' ) ) ), 'only ids of the trash are accepted when putting back' );
mcupr_fm_trash_purge( array( '../..', 'nonsense' ) );
t( 1 === mcupr_fm_trash_count(), 'purging with ids that are not in the trash removes nothing' );
$p = mcupr_fm_trash_purge( $d );
t( 1 === $p[0] && 0 === mcupr_fm_trash_count() && ! file_exists( $root . '/.mcupr-trash/' . $d[0] ), 'deleting from the trash is for good, and says how much went' );
// the trash empties itself after a while
file_put_contents( $root . '/' . $base . '/old.txt', 'o' );
file_put_contents( $root . '/' . $base . '/new.txt', 'n' );
$od = mcupr_fm_delete( array( $base . '/old.txt' ) );
$nd = mcupr_fm_delete( array( $base . '/new.txt' ) );
$all = get_option( 'mcupr_fm_trash' );
$all[ $od[0] ]['time'] = time() - 40 * DAY_IN_SECONDS;
update_option( 'mcupr_fm_trash', $all );
mcupr_fm_trash_purge( null, time() - 30 * DAY_IN_SECONDS );
t( 1 === mcupr_fm_trash_count() && ! file_exists( $root . '/.mcupr-trash/' . $od[0] ) && file_exists( $root . '/.mcupr-trash/' . $nd[0] . '/new.txt' ), 'what has been in the trash more than 30 days is removed, the rest stays' );
t( false !== has_action( 'mcupr_fm_purge' ), 'the daily job that does it is hooked' );
// the Media library still owns its files, trash or not
t( is_wp_error( mcupr_fm_delete( array( $base . '/pic.jpg' ) ) ) && is_file( $pic ), 'a file of the Media library is not trashed either' );
mcupr_fm_trash_purge();
t( 0 === mcupr_fm_trash_count() && 0 === count( glob( $root . '/.mcupr-trash/*', GLOB_ONLYDIR ) ), 'emptying the trash leaves nothing behind' );

// an id of sixteen digits only (about one in two thousand): PHP turns it into a number as an array key, which once threw it out of the trash
$digit = '1234567890123456';
wp_mkdir_p( $root . '/.mcupr-trash/' . $digit );
file_put_contents( $root . '/.mcupr-trash/' . $digit . '/digits.txt', 'd' );
$all = get_option( 'mcupr_fm_trash', array() );
$all[ $digit ] = array( 'name' => 'digits.txt', 'from' => $base . '/digits.txt', 'time' => time(), 'dir' => false, 'files' => 1, 'folders' => 0, 'bytes' => 1, 'tags' => array() );
update_option( 'mcupr_fm_trash', $all, false );
$ti = mcupr_fm_trash_items();
t( 1 === count( $ti ) && $digit === $ti[0]['path'] && is_string( $ti[0]['path'] ), 'an id of digits only still shows in the trash (as a string)' );
$r = mcupr_fm_trash_restore( array( $digit ) );
t( array( $base . '/digits.txt' ) === $r && is_file( $root . '/' . $base . '/digits.txt' ) && 0 === mcupr_fm_trash_count(), 'and it can be put back' );
wp_mkdir_p( $root . '/.mcupr-trash/' . $digit );
file_put_contents( $root . '/.mcupr-trash/' . $digit . '/digits.txt', 'd' );
$all = get_option( 'mcupr_fm_trash', array() );
$all[ $digit ] = array( 'name' => 'digits.txt', 'from' => $base . '/digits2.txt', 'time' => time(), 'dir' => false, 'files' => 1, 'folders' => 0, 'bytes' => 1, 'tags' => array() );
update_option( 'mcupr_fm_trash', $all, false );
$p = mcupr_fm_trash_purge( array( $digit ) );
t( 1 === $p[0] && 0 === mcupr_fm_trash_count() && ! file_exists( $root . '/.mcupr-trash/' . $digit ), 'and it can be deleted for good (nothing left behind)' );

// ---- clean up
wp_delete_attachment( $att, true );
foreach ( glob( $root . '/' . $base . '/*' ) as $f ) { is_dir( $f ) ? mcupr_fm_rm( $f ) : @unlink( $f ); }
@unlink( $root . '/' . $base . '/.secret' );
@unlink( $root . '/' . $base . '/index.php' );
@rmdir( $root . '/' . $base );
mcupr_fm_tags_save( array() );
t( ! is_dir( $root . '/' . $base ), 'the test folder is cleaned up' );

echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
