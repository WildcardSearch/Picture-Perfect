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
function pp_restore_info()
{
	global $mybb;

	return array(
		'title' => 'Restore Image',
		'description' => 'if a stored original URL exists, swap it with the current URL',
		'actionPhrase' => 'Restore Images',
		'imageLimit' => 12,
		'createsSet' => false,
		'contentRequired' => false,
		'version' => '1.0',
	);
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_restore_process_images($images)
{
	global $mybb, $db, $lang, $html;

	$messages = array();

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = null;

	foreach ($images as $id => $image) {
		if (!$image['original_url']) {
			$fail++;
			continue;
		}

		$info = ppReplacePostedImage($image, $image['original_url']);
		if ($info['status'] !== true) {
			$fail++;
			continue;
		}

		$ogUrl = $image['url'];
		$image['url'] = $image['original_url'];
		$image['original_url'] = $ogUrl;
		$image['imagechecked'] = false;
		$i = new PicturePerfectImage($image);

		if (!$i->save()) {
			$fail++;
			continue;
		}

		$success++;
	}

	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} image restored successfully', $success),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('{1} image could not be restored successfully', $fail),
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
