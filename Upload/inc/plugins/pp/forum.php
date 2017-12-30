<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * forum routines
 */

if (!defined('PP_INITIALIZED')) {
	ppInitialize();
}

/**
 * delete image information when a post is deleted
 *
 * @param  int
 * @return void
 */
function ppDeletePost($pid)
{
	global $mybb, $db;

	$db->delete_query('pp_images', "pid='{$pid}'");
}

/**
 * delete image information when a thread is deleted
 *
 * @param  int
 * @return void
 */
function ppModerationDoDeleteThread($tid)
{
	global $db;

	$db->delete_query('pp_images', "tid='{$tid}'");
}

/**
 * merge image information
 *
 * @return void
 */
function ppModerationDoMerge()
{
	global $mybb, $db, $tid, $mergetid;

	$db->update_query('pp_images', array('tid' => $tid), "tid='{$mergetid}'");
}

/**
 * rescan post when edited
 *
 * @param  object post info
 * @return void
 */
function ppEditPost($thisPost)
{
	global $db;

	$pid = (int) $thisPost->data['pid'];
	$db->delete_query('pp_images', "pid='{$pid}'");

	ppStorePostedImages($pid, (int) $thisPost->data['tid'], $thisPost->data['message']);
}

/**
 * scan new posts
 *
 * @return void
 */
function ppNewPost()
{
	global $mybb, $pid, $tid, $post;

	$message = $post['message'];
	// if creating a new thread the message comes from $_POST
	if ($mybb->input['action'] == "do_newthread" &&
		$mybb->request_method == "post") {
		$message = $mybb->input['message'];
	}

	ppStorePostedImages($pid, $tid, $message);
}

/**
 * @return void
 */
function ppInitialize()
{
	define('PP_INITIALIZED', 1);

	global $plugins, $mybb;

	switch (THIS_SCRIPT) {
	case 'newreply.php':
		$plugins->add_hook('newreply_do_newreply_end', 'ppNewPost');
		break;
	case 'newthread.php':
		$plugins->add_hook('newthread_do_newthread_end', 'ppNewPost');
		break;
	case 'moderation.php':
		if ($mybb->input['action'] == 'do_merge') {
			$plugins->add_hook('moderation_do_merge', 'ppModerationDoMerge');
		}
		break;
	}

	$plugins->add_hook('class_moderation_delete_post', 'ppDeletePost');
	$plugins->add_hook('class_moderation_delete_thread', 'ppModerationDoDeleteThread');
	$plugins->add_hook("datahandler_post_update", "ppEditPost");
}

?>
