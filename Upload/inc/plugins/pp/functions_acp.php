<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * functions file
 */

// disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function ppBuildJumpMenu($tid, $page)
{
	return <<<EOF
<div class="pp-forum-jump-form">
	<form id="forum-jump">
		<input type="hidden" name="module" value="config-pp" />
		<input type="hidden" name="action" value="parse_url" />
		<input type="hidden" name="from[action]" value="view_thread" />
		<input type="hidden" name="from[tid]" value="{$tid}" />
		<input type="hidden" name="from[page]" value="{$page}" />
		<input type="text" name="url" value="" placeholder="Enter TID or link here..." />&nbsp;
		<input type="submit" value="Go" />
	</form>
</div>
EOF;
}

function ppBuildTaskSelector($from='view_thread')
{
	global $db, $lang;

	$taskQuery = $db->simple_select('pp_image_tasks', '*', "pid='0'", array(
		'order_by' => 'task_order',
		'order_dir' => 'ASC',
	));

	$options = '';
	while ($task = $db->fetch_array($taskQuery)) {
		$options .= <<<EOF

	<option value="{$task['id']}">{$task['title']}</option>
EOF;
	}

	return <<<EOF
<span class="inlineSubmit">
	<strong>Process Images:</strong>&nbsp;
	<select name="task">{$options}
		<option value="caption">Update Captions</option>
		<option value="reset_images">Update Image Info</option>
	</select>
	<input type="submit" class="pp_inline_submit button" name="pp_inline_task" value="{$lang->go} (0)"/>
	<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
</span>
EOF;
}

function getAllTaskLists()
{
	global $db;

	$taskLists = array();

	$query = $db->simple_select('pp_image_task_lists', 'id, title', 'active=1');
	if ($db->num_rows($query) <= 0) {
		return $taskLists;
	}

	while ($taskList = $db->fetch_array($query)) {
		$taskLists[$taskList['id']] = $taskList['title'];
	}

	return $taskLists;
}

function ppBuildTaskListSelector()
{
	global $db, $lang;

	$taskOptions = '';
	$taskLists = getAllTaskLists();
	if (count($taskLists) < 1) {
		return false;
	}

	foreach ($taskLists as $id => $title) {
		$taskOptions .= <<<EOF

		<option value="{$id}">{$title}</option>
EOF;
	}

	$taskListSelect = <<<EOF
<span class="inlineSubmit">
	<strong>Add to task list:</strong>&nbsp;
	<select name="tasklist">{$taskOptions}
	</select>
	<input type="submit" class="pp_inline_submit button" name="pp_task_submit" value="{$lang->go} (0)"/>
	<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
</span>
EOF;

	return $taskListSelect;
}

function ppBuildImageCard($image, $baseDomain, $currentPage, $fromId, $from='view_thread', $postNumber=0)
{
	global $mybb, $cp_style, $html, $modules, $hosts;

	$id = (int) $image['id'];
	$pid = $image['pid'];

	$checkedClass = '';
	if (!$image['imagechecked']) {
		$checkedClass = ' pp-image-unchecked';
	}

	$imageClass = '';
	if (strpos($image['url'], $baseDomain) !== false) {
		$imageClass = ' localImage';
	}

	$cbSep = '?';
	if (strpos($image['url'], '?') !== false) {
		$cbSep = '&amp;';
	}

	$fromId = (int) $fromId;
	if (!$fromId) {
		return false;
	}

	switch ($from) {
	case 'search_results':
		break;
	default:
		
	}

	$cacheBuster = "{$cbSep}dateline={$image['dateline']}";

	$postUrl = get_post_link($pid);
	$postUrl = "{$mybb->settings['bburl']}/{$postUrl}#pid{$pid}";

	$postLinkCaption = 'Post';
	if ($postNumber > 0) {
		$postLinkCaption = "#{$postNumber}";
	}

	$postLink = $html->link($postUrl, $postLinkCaption, array('target' => '_blank'));

	$imageLink = $html->link($image['url'], 'Image', array('target' => '_blank'));

	$urlInfo = parse_url($image['url']);

	$basePiece = str_replace('www.', '', $urlInfo['host']);

	$domainTitle = htmlspecialchars_uni($basePiece);

	$domainLength = my_strlen($basePiece);
	$fs = 1;
	if ($domainLength > 24) {
		$domArray = explode('.', $basePiece);
		$pieceCount = count($domArray);

		if ($pieceCount > 2) {
			$middlePiece = floor($pieceCount / 2) - 1;

			$firstPiece = implode('.', array_slice($domArray, 0, $middlePiece+1));
			$secondPiece = implode('.', array_slice($domArray, $middlePiece+1));

			$domArray[$middlePiece] .= "<br />";
			$basePiece = implode('.', $domArray);

			$domainLength = (int) max(strlen($firstPiece), strlen($secondPiece)) * 1.5;
		}
	}

	if ($domainLength > 10) {
		$fs = (float) ($domainLength - 10) * 0.03;
		$fs = max(.3, 1 - ($fs));
	}

	$popup = new PopupMenu("control_{$id}", 'Options');

	foreach ((array) $modules as $addon => $module) {
		if ($addon === 'rehost') {
			continue;
		}

		$urlArray = array(
			'action' => 'process_images',
			'mode' => 'configure',
			'addon' => $addon,
			'pp_inline_ids' => array($id),
			'page' => $currentPage,
			'from' => $from,
			'fromid' => $fromId,
		);

		if ($module->hasSettings !== true) {
			$urlArray['mode'] = 'finalize';
		}

		$popup->add_item($module->get('actionPhrase'), $html->url($urlArray));
	}

	foreach ((array) $hosts as $addon => $host) {
		$urlArray = array(
			'action' => 'process_images',
			'mode' => 'configure',
			'host' => $addon,
			'addon' => 'rehost',
			'pp_inline_ids' => array($id),
			'page' => $currentPage,
			'from' => $from,
			'fromid' => $fromId,
		);

		if ($host->hasSettings !== true) {
			$urlArray['mode'] = 'finalize';
		}

		$popup->add_item($host->get('actionPhrase'), $html->url($urlArray));
	}

	$popup->add_item('Update Caption', $html->url(array(
		'action' => 'update_caption',
		'id' => $id,
		'tid' => $tid,
		'fid' => $fid,
		'page' => $currentPage,
		'from' => $from,
		'fromid' => $fromId,
	)));

	$popup->add_item('Update Image Info', $html->url(array(
		'action' => 'process_images',
		'task' => 'reset_images',
		'pp_inline_ids' => array($id),
		'tid' => $tid,
		'fid' => $fid,
		'page' => $currentPage,
		'from' => $from,
		'fromid' => $fromId,
	)));

	// add z-index to popup (hacky, I know)
	$thisPopup = str_replace(' class="popup_menu', ' class="popup_menu image-popup', $popup->fetch());

	$checkId = "imageCheck_{$id}";

	$secureText = 'http';
	$secureClass = 'pp-image-http';
	if ($image['secureimage']) {
		$secureText = 'https';
		$secureClass = 'pp-image-https';
	}

	$statusText = 'good';
	$statusClass = 'pp-good-image';
	if ($image['deadimage']) {
		$statusText = 'dead';
		$statusClass = 'pp-dead-image';
	}

	$width = $height = '?';
	if ($image['width']) {
		$width = $image['width'];
	}

	if ($image['height']) {
		$height = $image['height'];
	}

	$bgColor = '';
	$colorAvg =  <<<EOF

	<div class="pp-image-color-average" style="background-color: white; color: black; border: 1px solid white;">Color Average: <em>n/a</em></div>
EOF;
	$bgFallback = ", url(styles/{$cp_style}/images/pp/bad-image.png)";
	if ($image['color_average'] &&
		$image['color_opposite']) {
		$colorAvg = <<<EOF

	<div class="pp-image-color-average" style="background-color: #{$image['color_average']}; color: #{$image['color_opposite']}; border: 1px solid #{$image['color_opposite']};">Color Average: #{$image['color_average']}</div>
EOF;
		$bgColor = " background-color: #{$image['color_average']};";
		$bgFallback = '';
	}

	return <<<EOF
<div class="imageContainer">
	<label class="checkContainer" for="{$checkId}">
		<div class="thumbnail{$imageClass}{$checkedClass}" style="background-image: url('{$image['url']}{$cacheBuster}'){$bgFallback};{$bgColor}" data-imageid="{$id}" data-url="{$image['url']}">
			<input id="{$checkId}" type="checkbox" name="pp_inline_ids[{$id}]" value="" class="checkbox_input pp_check" />
			<span class="checkmark"></span>
			<div class="domain-label" title="{$domainTitle}" style="font-size: {$fs}em;">{$basePiece}</div>
		</div>
	</label>
	<div class="imageInfo">
		<div id="image-standard-info-{$id}" class="infoRow imageLinks">
			{$postLink} | {$imageLink} | <span class="pp-image-security {$secureClass}">{$secureText}</span>
		</div>
		<div id="image-advanced-info-{$id}" class="infoRow imageDimensions">
			<span class="pp-image-dimensions">{$width}x{$height}</span> | <span class="pp-image-status {$statusClass}">{$statusText}</span>
		</div>{$colorAvg}
		<div class="infoRow captionRow">
			<input class="captionInput" type="text" name="image_captions[{$id}]" value="{$image['caption']}" placeholder="Your caption here..." title="{$image['caption']}" />
		</div>
		<div class="buttonRow">
			{$thisPopup}
		</div>
	</div>
</div>
EOF;
}

function ppGetPostNumbersForThread($tid)
{
	global $db;

	$tid = (int) $tid;
	$postNumbers = array();

	$query = $db->simple_select('posts', 'pid', "tid='{$tid}'", array('order_by' => 'pid', 'order_dir' => 'ASC'));
	$pn = 1;
	while ($pid = $db->fetch_field($query, 'pid')) {
		$postNumbers[$pid] = $pn;
		$pn++;
	}

	return $postNumbers;
}

function ppBuildRedirectUrlArray($fromId, $from='view_thread')
{
	global $mybb;

	$redirectArray = array(
		'action' => $from,
		'page' => $mybb->input['page'],
	);

	switch ($from) {
	case 'search_results':
		$fromIdString = 'id';
		break;
	default:
		$fromIdString = 'tid';
		$redirectArray['fid'] = $mybb->input['fid'];
	}

	$redirectArray[$fromIdString] = $fromId;

	return $redirectArray;
}

?>
