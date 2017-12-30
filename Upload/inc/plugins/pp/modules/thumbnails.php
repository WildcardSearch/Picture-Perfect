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
function pp_thumbnails_process_images($args)
{
	global $html, $mybb;
	extract($args);
	$tid = $images[key($images)]['tid'];

	$redirectAction = 'view_set';
	$redirecMode = '';
	$imageSet = new PicturePerfectImageSet($mybb->input['setid']);
	if (!$imageSet->isValid()) {
		$redirectAction = 'edit_set';
		$redirecMode = 'new_set';
		$imageSet->set('title', 'New Image Set');
		$imageSet->save();
	}

	$setId = $imageSet->get('id');

	$returnArray = array(
		'action' => $redirectAction,
		'mode' => $redirecMode,
		'id' => $setId,
	);

	$basePath = "images/picture_perfect/thumbs/{$tid}-{$settings['max_width']}x{$settings['max_height']}";
	$path = MYBB_ROOT . $basePath;

	if (!file_exists($path) &&
		@!mkdir($path)) {
		return false;
	}

	$images = ppFetchRemoteFiles($images);
	$images = ppGetImageInfo($images);

	foreach ($images as $id => $image) {
		$uniqueID = uniqid();
		$baseName = "{$image['tid']}-{$image['pid']}-{$uniqueID}";

		$filename = "{$path}/{$baseName}.{$image['extension']}";

		if (ppResizeImage($image['tmp_url'], $filename, $settings['max_width'], $settings['max_height']) !== true) {
			continue;
		}

		@unlink($image['tmp_url']);

		$image['id'] = null;
		$image['setid'] = $setId;
		$image['url'] = "{$mybb->settings['bburl']}/{$basePath}/{$baseName}.{$image['extension']}";
		$newImage = new PicturePerfectImage($image);
		$newImage->save();
	}

	return $returnArray;
}

?>
