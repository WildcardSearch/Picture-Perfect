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
function pp_try_as_https_info()
{
	global $mybb;

	return array(
		'title' => 'Try Image As HTTPS',
		'description' => 'some images resolve to HTTP, but will also work on HTTPS',
		'actionPhrase' => 'Try Images As HTTPS',
		'imageLimit' => 1,
		'createsSet' => false,
		'contentRequired' => true,
		'version' => '1.0',
	);
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_try_as_https_process_images($images)
{
	global $mybb, $db, $lang, $html;

	$messages = array();

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = null;

	$id = key($images);

	if (substr($images[$id]['url'], 0, 5) === 'https') {
		return array(
			'redirect' => $redirectInfo,
			'messages' => array(
				array(
					'status' => 'error',
					'message' => 'Image is already served using HTTPS',
				),
			),
		);
	}

	$images[$id]['real_url'] = $images[$id]['url'];
	$images[$id]['url'] = str_replace('http', 'https', $images[$id]['url']);

	$image = array_shift($images);

	if ($image['info']['http_code'] != 200) {
		$fail++;
	} else {
		$newUrl = $image['url'];
		$image['url'] = $image['real_url'];
		$info = ppReplacePostedImage($image, $newUrl, false, true);

		if ($info['status'] !== true) {
			$fail++;
		} else {
			$success++;
		}
	}

	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('Image URL worked as HTTPS', $success),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('Image URL did not work as HTTPS', $fail),
		);
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
