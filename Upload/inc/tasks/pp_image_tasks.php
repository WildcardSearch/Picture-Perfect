<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * perform queued image tasks
 */

/**
 * @param  int task id
 * @return void
 */
function task_pp_image_tasks($task)
{
	global $mybb, $db, $lang;

	if (!$lang->pp) {
		$lang->load('pp', false, true);
	}

	$cache = PicturePerfectCache::getInstance();

	$currentTask = $cache->read('current_task');
	if (empty($currentTask)) {
		$query = $db->simple_select('pp_image_task_lists', '*', "active=1 AND NOT images=''", array('order_by' => 'dateline', 'order_dir' => 'ASC', 'limit' => 1));

		if ($db->num_rows($query) == 0) {
			$report = 'Nothing to do. [No current task, no active tasks with pending images]';
			add_task_log($task, $report);
			return;
		}

		$taskList = $db->fetch_array($query);

		$imageList = $taskList['images'];
		$lid = (int) $taskList['id'];

		$query = $db->simple_select('pp_image_tasks', '*', "lid='{$lid}'", array('order_by' => 'task_order', 'order_dir' => 'ASC'));

		$imageLimit = 12;
		$tasks = array();
		while ($data = $db->fetch_array($query)) {
			$addon = new PicturePerfectModule($data['addon']);

			if (!$addon->isValid()) {
				continue;
			}

			if (!empty($data['settings'])) {
				$data['settings'] = json_decode($data['settings'], true);
			}

			$tasks["{$data['addon']}-{$data['id']}"] = $data;

			$lowerLimit = min($addon->get('imageLimit'), $imageLimit);
		}

		if (empty($tasks)) {
			$report = 'Bad task list.';
			add_task_log($task, $report);
			return;
		}

		$imageArray = explode(',', $imageList);

		if (empty($imageArray)) {
			$report = 'Bad image list.';
			add_task_log($task, $report);
			return;
		}

		$imageArray = array_map('intval', $imageArray);
		$imageList = implode(',', $imageArray);

		$query = $db->simple_select('pp_images', '*', "id IN({$imageList})");

		if ($db->num_rows($query) == 0) {
			$report = 'Bad image list.';
			add_task_log($task, $report);
			return;
		}

		$images = array();
		while ($image = $db->fetch_array($query)) {
			$images[$image['id']] = $image;
		}

		$currentTask = array(
			'taskList' => $taskList,
			'tasks' => $tasks,
			'limit' => $imageLimit,
			'images' => $images,
		);

		foreach ($imageArray as $imageId) {
			foreach ($tasks as $key => $data) {
				if (!$imageId) {
					continue;
				}

				$currentTask['images'][$imageId]['tasks'][] = $key;
			}
		}

		$images = $currentTask['images'];
		$cache->update('current_task', $currentTask);
	} else {
		$tasks = $currentTask['tasks'];
		$taskList = $currentTask['taskList'];
		$images = $currentTask['images'];
		$imageLimit = $currentTask['limit'];
	}

	$taskImages = array_slice($images, 0, $imageLimit);

	// mark the task list as up-to-date if there are no images
	if (empty($taskImages)) {
		$tl = new PicturePerfectImageTaskList($taskList);
		$tl->set('images', '');
		$tl->save();

		$cache->update('current_task', null);

		$report = 'Nothing to do. [No images available to task]';
		add_task_log($task, $report);
		return;
	}

	$taskKey = key($taskImages[0]['tasks']);
	$thisTask = $taskImages[0]['tasks'][$taskKey];

	// modules get setid from $_REQUEST
	$mybb->input['setid'] = $tasks[$thisTask]['setid'];

	$taskPieces = explode('-', $thisTask);
	$moduleKey = $taskPieces[0];

	$module = new PicturePerfectModule($moduleKey);

	if (!$module->isValid()) {
		$report = 'Invalid module.';
		add_task_log($task, $report);
		return;
	}

	$contentRequired = $module->get('contentRequired');
	$storeImage = $module->get('storeImage');
	$hostSettings = array();
	if ($module->get('baseName') === 'rehost') {
		$imageHost = $mybb->input['host'];
		if (!$imageHost) {
			$report = 'Bad image host.';
			add_task_log($task, $report);
			return;
		}

		if ($imageHost) {
			$host = new PicturePerfectImageHost($imageHost);

			if ($host->isValid()) {
				$contentRequired = $host->get('contentRequired');
			}
		}
	}

	if ($contentRequired) {
		$taskImages = ppFetchRemoteFiles($taskImages, $storeImage);
	}

	$info = $module->processImages($taskImages, $tasks[$thisTask]['settings']);

	$ids = '';
	$idArray = array();
	foreach ($taskImages as $i) {
		$idArray[] = (int) $i['id'];
	}

	$ids = implode(',', $idArray);

	$mArray = array();
	foreach ((array) $info['messages'] as $m) {
		$mArray[] = ucfirst($m['status']).': '.$m['message'];
	}

	$messages = implode('; ', $mArray);
	$report .= "{$messages} ({$ids})";

	$removedIds = array();
	foreach ($taskImages as $id => $image) {
		$images[$image['id']]['tasks'] = array_filter($image['tasks'], function($val) use ($thisTask) {
			return $val != $thisTask;
		});

		if (empty($images[$image['id']]['tasks'])) {
			unset($images[$image['id']]);
			$removedIds[] = $image['id'];
		}
	}

	if (!empty($removedIds) &&
		!empty($taskList['images'])) {
		$currentArray = explode(',', $taskList['images']);

		$currentArray = array_filter($currentArray, function($val) use ($removedIds) {
			return !in_array($val, $removedIds);
		});

		$taskList['images'] = implode(',', $currentArray);
		$currentTask['taskList'] = $taskList;

		$tl = new PicturePerfectImageTaskList($taskList);
		$tl->save();
	}

	if (empty($images)) {
		$cache->update('current_task', null);
	} else {
		$currentTask['images'] = $images;
		$cache->update('current_task', $currentTask);
	}

	// add an entry to the log
	add_task_log($task, $report);
}

?>
