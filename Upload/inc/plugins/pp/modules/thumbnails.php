<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * default module
 */

/**
 * module info
 *
 * @return void
 */
function pp_thumbnails_info()
{
	return array(
		'title' => 'Thumbnails',
		'description' => 'creates thumbnails from posted images',
		'actionPhrase' => 'Create Thumbnails',
		'pageAction' => 'view_thumbnails',
		'imageLimit' => 12,
		'createsSet' => true,
		'contentRequired' => true,
		'storeImage' => true,
		'version' => '1.0',
		'settings' => array(
			'max_width' => array(
				'title' => 'Maximum Width',
				'description' => 'in pixels',
				'optionscode' => 'text',
				'value' => '240',
			),
			'max_height' => array(
				'title' => 'Maximum Height',
				'description' => 'in pixels',
				'optionscode' => 'text',
				'value' => '240',
			),
		),
	);
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_thumbnails_process_images($images, $settings)
{
	global $html, $mybb, $lang;

	$tid = $images[key($images)]['tid'];
	$from = trim($mybb->input['from']);
	$fromId = (int) $mybb->input['fromid'];

	$redirectAction = 'view_set';
	$redirectMode = '';
	$imageSet = new PicturePerfectImageSet($mybb->input['setid']);
	if (!$imageSet->isValid()) {
		$redirectAction = 'edit_set';
		$redirectMode = 'new_set';
		$imageSet->set('title', 'New Image Set');
		$imageSet->save();
	}

	$setId = $imageSet->get('id');

	$redirectInfo = array(
		'action' => $redirectAction,
		'mode' => $redirectMode,
		'id' => $setId,
	);

	$basePath = "images/picture_perfect/thumbs/{$tid}-{$settings['max_width']}x{$settings['max_height']}";
	$path = MYBB_ROOT.$basePath;

	if (!file_exists($path) &&
		@!mkdir($path)) {
		return array(
			ppBuildRedirectUrlArray($fromId, $from),
			'messages' => array(
				'status' => 'error',
				'message' => 'Image folder could not be created.',
			),
		);
	}

	$images = ppGetImageInfo($images);

	$success = $fail = 0;
	foreach ($images as $id => $image) {
		$uniqueID = uniqid();
		$baseName = "{$image['tid']}-{$image['pid']}-{$uniqueID}";

		$filename = "{$path}/{$baseName}.{$image['extension']}";

		if (ppResizeImage($image['tmp_url'], $filename, $settings['max_width'], $settings['max_height']) !== true) {
			$fail++;
			continue;
		} else {
			$success++;
		}

		@unlink($image['tmp_url']);

		$image['id'] = null;
		$image['setid'] = $setId;
		$image['url'] = "{$mybb->settings['bburl']}/{$basePath}/{$baseName}.{$image['extension']}";
		$newImage = new PicturePerfectImage($image);
		$newImage->save();
	}

	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} thumbnail image(s) created successfully', $success),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('{1} thumbnail image(s) could not be created successfully', $fail),
		);
	}

	if (!$success &&
		!$fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => 'No valid image found.',
		);
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
