<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this is the main plugin file
 */

// disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

define('PICTURE_PERFECT_VERSION', '0.0.5');
define('PICTURE_PERFECT_MOD_URL', MYBB_ROOT.'inc/plugins/pp/modules');

// register custom class autoloader
spl_autoload_register('ppClassAutoLoad');

require_once MYBB_ROOT.'inc/plugins/pp/functions.php';

// load the install/admin routines only if in ACP.
if (defined('IN_ADMINCP')) {
    require_once MYBB_ROOT.'inc/plugins/pp/acp.php';
} else {
	require_once MYBB_ROOT.'inc/plugins/pp/forum.php';
}

/**
 * class autoloader
 *
 * @param string the name of the class to load
 */
function ppClassAutoLoad($className) {
	$path = MYBB_ROOT."inc/plugins/pp/classes/{$className}.php";

	if (file_exists($path)) {
		require_once $path;
	}
}

?>
