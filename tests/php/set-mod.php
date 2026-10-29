<?php
/** For the browser tests: set or remove a Customizer setting.   php php/set-mod.php <site> <name> <value|--remove> */
require rtrim( $argv[1], '/' ) . '/wp-load.php';
'--remove' === $argv[3] ? remove_theme_mod( $argv[2] ) : set_theme_mod( $argv[2], $argv[3] );
echo "ok\n";
