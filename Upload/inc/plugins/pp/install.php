<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * This file contains the install functions for acp.php
 */

// disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * used by MyBB to provide relevant information about the plugin and
 * also link users to updates
 *
 * @return array plugin info
 */
function pp_info()
{
	global $db, $lang, $mybb, $cache, $cp_style;

	if (!$lang->pp) {
		$lang->load('pp');
	}

	$settingsLink = ppBuildSettingsLink();

	if ($settingsLink) {
		$settingsLink = <<<EOF
				<li style="list-style-image: url(styles/{$cp_style}/images/pp/settings.gif); margin-top: 10px;">
					{$settingsLink}
				</li>
EOF;

		$buttonPic = "styles/{$cp_style}/images/pp/donate.gif";
		$borderPic = "styles/{$cp_style}/images/pp/pixel.gif";

		// only show Manage Images link if active
		$pluginList = $cache->read('plugins');
		$manageLink = '';
		if (!empty($pluginList['active']) &&
			is_array($pluginList['active']) &&
			in_array('pp', $pluginList['active'])) {
			$url = PICTURE_PERFECT_URL;
			$manageLink = <<<EOF
	<li style="list-style-image: url(styles/{$cp_style}/images/pp/manage.png)">
		<a href="{$url}" title="{$lang->pp_manage_images}">{$lang->pp_manage_images}</a>
	</li>
EOF;
		}

		$ppDescription = <<<EOF

<table style="width: 100%;">
	<tr>
		<td style="width: 75%;">
			{$lang->pp_description}
			<ul id="mm_options">
				{$settingsLink}
				{$manageLink}
			</ul>
		</td>
		<td style="text-align: center;">
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="VA5RFLBUC4XM4">
				<input type="image" src="{$buttonPic}" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="{$borderPic}" width="1" height="1">
			</form>
		</td>
	</tr>
</table>
EOF;
	} else {
		$ppDescription = $lang->pp_description;
	}

	$ppDescription .= ppCheckRequirements();

	$name = <<<EOF
<span style="font-familiy: arial; font-size: 1.5em; color: #5C55BB; text-shadow: 2px 2px 2px black;">{$lang->pp}</span>
EOF;
	$author = <<<EOF
</a></small></i><a href="http://www.rantcentralforums.com" title="Rant Central"><span style="font-family: Courier New; font-weight: bold; font-size: 1.2em; color: #117eec;">Wildcard</span></a><i><small><a>
EOF;

    // return the info
	return array(
        'name' => $name,
        'description' => $ppDescription,
        'version' => PICTURE_PERFECT_VERSION,
        'author' => $author,
        'authorsite' => 'http://www.rantcentralforums.com/',
		'compatibility' => '18*',
		"codename" => 'pp',
    );
}

/**
 * check to see if the plugin is installed
 *
 * @return bool true if installed, false if not
 */
function pp_is_installed()
{
	return ppGetSettingsgroup();
}

/**
 *
 *
 * @return void
 */
function pp_install()
{
	global $lang;

	if (!$lang->pp) {
		$lang->load('pp');
	}

	$req = ppCheckRequirements(true);
	if ($req) {
		flash_message("{$lang->pp_cannot_be_installed}<br /><br />{$req}", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	PicturePerfectInstaller::getInstance()->install();

	foreach (array(
		'picture_perfect',
		'picture_perfect/thumbs',
		'picture_perfect/rehost',
		'picture_perfect/temp',
	) as $folder) {
		ppCreateFolder(MYBB_ROOT . "images/{$folder}");
	}
}

/**
 * version check
 *
 * @return void
 */
function pp_activate()
{
	$myCache = PicturePerfectCache::getInstance();

	$oldVersion = $myCache->getVersion();
	if (version_compare($oldVersion, PICTURE_PERFECT_VERSION, '<') &&
		$oldVersion != '' &&
		$oldVersion != 0) {

		// check everything and upgrade if necessary
		pp_install();
    }

	// update the version (so we don't try to upgrade next round)
	$myCache->setVersion(PICTURE_PERFECT_VERSION);

	// change the permissions to on by default
	change_admin_permission('config', 'pp');
}

/**
 * permissions
 *
 * @return void
 */
function pp_deactivate()
{
	// remove the permissions
	change_admin_permission('config', 'pp', -1);
}

/**
 * uninstall
 *
 * @return void
 */
function pp_uninstall()
{
	PicturePerfectInstaller::getInstance()->uninstall();
	PicturePerfectCache::getInstance()->clear();
}

/**
 * retrieves the plugin's settings group gid if it exists
 * attempts to cache repeat calls
 *
 * @return int setting group id
 */
function ppGetSettingsgroup()
{
	static $gid;

	// if we have already stored the value
	if (!isset($gid)) {
		global $db;

		// otherwise we will have to query the db
		$query = $db->simple_select("settinggroups", "gid", "name='pp_settings'");
		$gid = (int) $db->fetch_field($query, 'gid');
	}
	return $gid;
}

/**
 * builds the URL to modify plugin settings if given valid info
 *
 * @param - $gid is an integer representing a valid settings group id
 * @return string setting group URL
 */
function ppBuildSettingsURL($gid)
{
	if ($gid) {
		return "index.php?module=config-settings&amp;action=change&amp;gid={$gid}";
	}
}

/**
 * builds a link to modify plugin settings if it exists
 *
 * @return setting group link HTML
 */
function ppBuildSettingsLink()
{
	global $lang;

	if (!$lang->pp) {
		$lang->load('pp');
	}

	$gid = ppGetSettingsgroup();

	// does the group exist?
	if ($gid) {
		// if so build the URL
		$url = ppBuildSettingsURL($gid);

		// did we get a URL?
		if ($url) {
			// if so build the link
			return <<<EOF
<a href="{$url}" title="{$lang->pp_plugin_settings}">{$lang->pp_plugin_settings}</a>
EOF;
		}
	}
	return false;
}

/**
 * check plugin requirements and display warnings as appropriate
 *
 * @return string warning text
 */
function ppCheckRequirements($deep = false)
{
	global $lang;

	$forumStatus = is_writable(MYBB_ROOT . 'images/');
	if ($deep !== true &&
		$forumStatus) {
		return;
	}

	$issues = '';
	if (!$forumStatus) {
		$issues .= '<br /><span style="font-family: Courier New; font-weight: bolder; font-size: small; color: black;">' . MYBB_ROOT . 'images/</span>';
	}

	if ($deep) {
		$forumSubStatus = ppIsWritable(MYBB_ROOT . 'images/');

		if ($forumStatus &&
			$forumSubStatus) {
			return;
		}

		if (!$forumSubStatus) {
			$issues .= "<br /><span>{$lang->sprintf($lang->pp_subfolders_unwritable, MYBB_ROOT . 'images/</span>')}";
		}
		return "{$lang->pp_folders_requirement_warning}<br />{$issues}";
	}

	return <<<EOF
<br /><br /><div style="border: 1px solid darkred; color: darkred; background: pink;">{$lang->pp_folders_requirement_warning}{$issues}</div>
EOF;
}

/**
 * recursively check mutability of folders
 *
 * @param  string
 * @return bool
 */
function ppIsWritable($rootFolder)
{
	foreach (new DirectoryIterator($rootFolder) as $folder) {
		if (!$folder->isDir() ||
			$folder->isFile() ||
			$folder->isDot()) {
			continue;
		}

		if (!is_writeable($rootFolder . $folder . "/") ||
			!ppIsWritable($rootFolder . $folder . "/")) {
			return false;
		}
	}
	return true;
}

?>
