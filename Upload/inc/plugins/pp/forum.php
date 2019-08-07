<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * forum routines
 */

ppInitialize();

/**
 * delete image information when a post is deleted
 *
 * @param  int
 * @return void
 */
function ppDeletePost($pid)
{
	global $mybb, $db;

	$pid = (int) $pid;
	if (!$pid) {
		return;
	}

	$query = $db->simple_select('pp_images', 'id', "pid='{$pid}' AND setid='0'");
	$imageCount = (int) $db->num_rows($query);

	$db->delete_query('pp_images', "pid='{$pid}' AND setid='0'");

	$query = $db->simple_select('posts', 'tid', "pid='{$pid}'");
	$tid = (int) $db->fetch_field($query, 'tid');

	if (!$tid) {
		return;
	}

	$query = $db->simple_select('pp_image_threads', 'tid, image_count', "tid={$tid}");
	if ($db->num_rows($query) == 0) {
		return;
	}

	$imageThread = $db->fetch_array($query);

	if ($imageThread['tid']) {
		if ($imageThread['image_count'] - $imageCount <= 0) {
			$db->delete_query('pp_image_threads', "tid='{$tid}'");
		} else {
			$newCount = $imageThread['image_count'] - $imageCount;
			$db->update_query('pp_image_threads', array('image_count' => $newCount), "tid='{$tid}'");
		}
	}
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

	$db->delete_query('pp_images', "tid='{$tid}' AND setid='0'");
	$db->delete_query('pp_image_threads', "tid='{$tid}'");
}

/**
 * merge image information
 *
 * @return void
 */
function ppModerationDoMerge($args)
{
	global $db;

	extract($args);

	$query = $db->simple_select('pp_images', '*', "tid='{$mergetid}' AND setid='0'");
	$movedImageCount = $db->num_rows($query);

	$db->update_query('pp_images', array('tid' => $tid), "tid='{$mergetid}' AND setid='0'");

	$query = $db->simple_select('pp_image_threads', 'image_count', "tid='{$tid}'");
	if ($db->num_rows($query)) {
		$currentCount = $db->fetch_field($query, 'image_count');
		$newCount = (int) $currentCount+$movedImageCount;
		$db->update_query('pp_image_threads', array('image_count' => $newCount), "tid='{$tid}'");
	} else {
		$query = $db->simple_select('threads', 'fid', "tid='{$tid}'");
		$fid = $db->fetch_field($query, 'fid');
		$insertArray = array(
			'tid' => $tid,
			'fid' => $fid,
			'image_count' => $newCount,
			'dateline' => TIME_NOW,
		);

		$db->insert_query('pp_image_threads', $insertArray);
	}

	$db->delete_query('pp_image_threads', "tid='{$mergetid}'");
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

	$tid = (int) $thisPost->data['tid'];
	$pid = (int) $thisPost->data['pid'];
	$fid = (int) $thisPost->data['fid'];

	$query = $db->simple_select('pp_images', 'COUNT(pid) as imageCount', "pid='{$pid}' AND setid='0'");
	$oldImageCount = $db->fetch_field($query, 'imageCount');

	$db->delete_query('pp_images', "pid='{$pid}' AND setid='0'");

	$updatedImageCount = ppStorePostedImages($pid, $tid, $fid, $thisPost->data['message']);

	$query = $db->simple_select('pp_image_threads', 'image_count', "tid='{$tid}'");

	if ($db->num_rows($query)) {
		$threadImageCount = $db->fetch_field($query, 'image_count');

		$threadImageCount += -($oldImageCount - $updatedImageCount);
		
		$db->update_query('pp_image_threads', array('image_count' => $threadImageCount), "tid='{$tid}'");
	} else {
		$insertArray = array(
			'tid' => $tid,
			'fid' => $fid,
			'image_count' => $updatedImageCount,
			'dateline' => TIME_NOW,
		);

		$db->insert_query('pp_image_threads', $insertArray);
	}
}

/**
 * scan new posts
 *
 * @return void
 */
function ppNewPost()
{
	global $mybb, $db, $pid, $tid, $fid, $post;

	$message = $post['message'];
	// if creating a new thread the message comes from $_POST
	if ($mybb->input['action'] == "do_newthread" &&
		$mybb->request_method == "post") {
		$message = $mybb->input['message'];
	}

	$imageCount = ppStorePostedImages($pid, $tid, $fid, $message);

	$query = $db->simple_select('pp_image_threads', 'image_count', "tid='{$tid}'");

	if ($db->num_rows($query)) {
		$oldCount = $db->fetch_field($query, 'image_count');
		$newCount = (int) $oldCount+$imageCount;
		$db->update_query('pp_image_threads', array('image_count' => $newCount), "tid='{$tid}'");
	} else {
		$insertArray = array(
			'tid' => $tid,
			'fid' => $fid,
			'image_count' => $imageCount,
			'dateline' => TIME_NOW,
		);

		$db->insert_query('pp_image_threads', $insertArray);
	}
}

/**
 * @return void
 */
function ppInitialize()
{
	global $plugins, $mybb;

	switch (THIS_SCRIPT) {
	case 'newreply.php':
		$plugins->add_hook('newreply_do_newreply_end', 'ppNewPost');
		break;
	case 'newthread.php':
		$plugins->add_hook('newthread_do_newthread_end', 'ppNewPost');
		break;
	case 'xmlhttp.php':
		$plugins->add_hook('xmlhttp', 'ppXmlhttp');
		break;
	}

	$plugins->add_hook('class_moderation_merge_threads', 'ppModerationDoMerge');
	$plugins->add_hook('class_moderation_delete_post_start', 'ppDeletePost');
	$plugins->add_hook('class_moderation_delete_thread', 'ppModerationDoDeleteThread');
	$plugins->add_hook("datahandler_post_update", "ppEditPost");
}

function ppXmlhttp()
{
	global $mybb;

	if ($mybb->input['action'] !== 'pp' ||
		!trim($mybb->input['mode'])) {
		return;
	}

	$function = 'ppXmlhttp'.trim($mybb->input['mode']);
	if (!function_exists($function)) {
		return;
	}

	$function();
	exit;
}

function ppXmlhttpGetImages()
{
	global $mybb;

	require_once MYBB_ROOT.'inc/plugins/pp/functions_imagefeed.php';

	$ipp = 100;
	if ((int) $mybb->input['ipp'] > 0) {
		$ipp = (int) $mybb->input['ipp'];
	}

	$start = 0;
	if ((int) $mybb->input['start'] > 0) {
		$start = (int) $mybb->input['start'];
	}

	$tid = 0;
	if ((int) $mybb->input['tid'] > 0) {
		$tid = (int) $mybb->input['tid'];
	}

	$sortVal = 'DESC';
	if ($mybb->input['sort'] === 'ASC') {
		$sortVal = 'ASC';
	}

	$data = ppGetImages($tid, $start, $ipp, $sortVal);
	ppOutputJson($data);
}

/**
 * output a value as JSON to the browser
 *
 * @param  mixed
 * @return void
 */
function ppOutputJson($data)
{
	$json = json_encode($data);

	if (!$json) {
		return false;
	}

	// send our headers.
	header('Content-type: application/json');
	echo($json);
	exit;
}

?>
