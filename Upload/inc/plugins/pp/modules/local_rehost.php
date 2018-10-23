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
function pp_local_rehost_info()
{
	return array(
		'title' => 'Local Rehosts',
		'description' => 'rehost posted images to a location on this server',
		'actionPhrase' => 'Rehost Images Locally',
		'pageAction' => 'view_local_rehost',
		'imageLimit' => 12,
		'createsSet' => false,
		'version' => '1.0',
		'settings' => array(
			'path' => array(
				'title' => 'Path',
				'description' => 'URL relative to the forum root where the rehosted images should be stored',
				'optionscode' => 'text',
				'value' => 'images/picture_perfect/rehost',
			),
			'format' => array(
				'title' => 'Image File Format',
				'description' => 'choose a valid image format',
				'optionscode' => <<<EOF
select
0=Keep Original Format
png=PNG
jpg=JPEG
gif=GIF
bmp=BMP
EOF
				,
				'value' => '0',
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
function pp_local_rehost_process_images($images, $settings)
{
	global $html, $mybb, $lang;

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_set',
		'tid' => $tid,
	);

	// build path
	$basePath = ppCleanPath($settings['path']);
	$path = MYBB_ROOT.$basePath;

	if (!file_exists($path) &&
		@!mkdir($path)) {
		return array(
			'redirect' => $redirectInfo,
			'messages' => array(
				'status' => 'error',
				'message' => 'Could not create installation folder(s)',
			)
		);
	}

	// get the images and some info about them
	$images = ppFetchRemoteFiles($images);
	$images = ppGetImageInfo($images);

	// main loop - process the images
	$fail = $success = 0;
	foreach ($images as $id => $image) {
		$uniqueID = uniqid();
		$baseName = "{$image['tid']}-{$image['pid']}-{$uniqueID}";

		// if an extension isn't specified, keep the original
		$ext = $image['extension'];
		if ($settings['format']) {
			$ext = $settings['format'];
		}

		if ($ext == 'jpeg') {
			$ext = 'jpg';
		}

		$filename = "{$path}/{$baseName}.{$ext}";

		$image['image'] = @imagecreatefromstring($image['content']);

		if (!$image['image'] ||
			!is_resource($image['image'])) {
			$fail++;
			continue;
		}

		switch ($ext) {
		case 'bmp':
			@imagewbmp($image['image'], $filename);
			break;
		case 'gif':
			@imagegif($image['image'], $filename);
			break;
		case 'jpg':
			@imagejpeg($image['image'], $filename);
			break;
		case 'png':
			@imagepng($image['image'], $filename);
			break;
		default:
			$fail++;
			continue;
		}

		// clean up
		@unlink($image['tmp_url']);
		@imagedestroy($image['image']);

		// now swap the image URL in the post
		$url = "{$mybb->settings['bburl']}/{$basePath}/{$baseName}.{$ext}";
		if (!ppReplacePostImage($image['pid'], $image['url'], $url)) {
			$fail++;
		} else {
			$success++;
		}

		// save the image
		$image['url'] = $url;
		$newImage = new PicturePerfectImage($image);
		$newImage->save();
	}

	// build messages
	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} image(s) locally rehosted successfully', $success),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('{1} image(s) could not be locally rehosted successfully', $fail)
		);
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
