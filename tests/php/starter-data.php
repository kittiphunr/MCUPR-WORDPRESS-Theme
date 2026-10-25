<?php
/** The starter file carries the real facts of the campus (history, vision, programmes, strategy, address), not blanks.   php php/starter-data.php */
$dir = __DIR__ . '/../../mcupr/starter/';
$fails = 0;
function t( $ok, $label ) { global $fails; echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n"; if ( ! $ok ) { $fails++; } }

$x = (string) file_get_contents( $dir . 'mcupr-starter.xml' );
$page = static function ( $slug ) use ( $x ) {
	foreach ( explode( '<item>', $x ) as $it ) {
		if ( false !== strpos( $it, '<wp:post_name><![CDATA[' . $slug . ']]></wp:post_name>' ) && false !== strpos( $it, '<wp:post_type>page</wp:post_type>' ) && preg_match( '#<content:encoded><!\[CDATA\[(.*?)\]\]></content:encoded>#s', $it, $m ) ) {
			return $m[1];
		}
	}
	return '';
};
$about = $page( 'about' ); $hist = $page( 'history' ); $vis = $page( 'vision' ); $prog = $page( 'programs' ); $strat = $page( 'strategy' ); $loc = $page( 'location' ); $emb = $page( 'emblem' );

t( false !== strpos( $about, 'วิทยาเขตแพร่' ) && false !== strpos( $about, '๖๓ รูป/คน' ), 'about: names the Phrae campus and its 63 staff' );
t( false !== strpos( $hist, '๑๐ มกราคม' ) && false !== strpos( $hist, '๒๕๔๕' ) && false !== strpos( $hist, 'อาคารพัสดุ' ), 'history: founding date, the move in 2545, and the list of buildings' );
t( false !== strpos( $vis, 'ปญฺญา โลกสฺมิ ปชฺโชโต' ) && false !== strpos( $vis, 'พุทธนวัตกรรม' ) && 4 === substr_count( $vis, '<li><strong>' ), 'vision: motto, vision and exactly four missions' );
t( false !== strpos( $vis, 'ผลิตบัณฑิต' ) && false !== strpos( $vis, 'ทะนุบำรุงศิลปะและวัฒนธรรม' ), 'vision: first and last missions are there' );
t( 8 === substr_count( $prog, '<tr><td>' ), 'programmes: eight programmes listed (5 bachelor, 2 master, 1 doctoral)' );
t( 5 === substr_count( $strat, '<td>๑.' ) + substr_count( $strat, '<td>๒.' ) + substr_count( $strat, '<td>๓.' ) + substr_count( $strat, '<td>๔.' ) + substr_count( $strat, '<td>๕.' ), 'strategy: all five strategies' );
t( false !== strpos( $strat, '๕๘,๘๗๗,๙๐๐' ), 'strategy: the budget total is there' );
t( false !== strpos( $loc, 'แม่คำมี' ) && false !== strpos( $loc, '๕๔๐๐๐' ) && false !== strpos( $loc, '[mcupr_contact]' ), 'location: the address, and the contact card still comes from the settings' );
t( false !== strpos( $emb, 'มจร' ), 'emblem: the meaning of the emblem is written' );

// what is left to fill in is only what the document does not say (and is marked)
foreach ( array( 'about' => $about, 'history' => $hist, 'vision' => $vis, 'programs' => $prog ) as $k => $h ) {
	t( ! preg_match( '/\[(ชื่อ|ระบุ|จำนวน|พ\.ศ\.|เขียน|\.\.\.)/u', $h ), "no blanks left in $k" );
}

$s = json_decode( (string) file_get_contents( $dir . 'mcupr-starter-settings.json' ), true );
$m = isset( $s['theme_mods'] ) ? $s['theme_mods'] : array();
t( '3' === ( $m['mcupr_num_faculties'] ?? '' ) && '36' === ( $m['mcupr_num_teachers'] ?? '' ) && ! isset( $m['mcupr_num_bachelor'] ), 'settings: three faculties, 36 academic staff, and no made-up student numbers' );
t( false !== strpos( (string) ( $m['mcupr_abt_text'] ?? '' ), 'วิทยาเขตแพร่' ) && '0-5464-6273' === ( $m['mcupr_org_tel'] ?? '' ), 'settings: the home page "about" text and the telephone are the campus\'s' );

echo $fails ? "$fails FAILED\n" : "all passed\n";
exit( $fails ? 1 : 0 );
