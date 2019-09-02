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
function pp_resolve_url_info()
{
	global $mybb;

	return array(
		'title' => 'Resolve Image URL',
		'description' => 'resolve the URL to it\'s final value',
		'actionPhrase' => 'Resolve Image URLs',
		'imageLimit' => 1,
		'createsSet' => false,
		'version' => '1.0',
	);
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_resolve_url_process_images($images)
{
	global $mybb, $db, $lang, $html;

	$messages = array();

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = null;

	$id = key($images);
	$images = array($images[$id]);

	// get the images and some info about them
	$images = ppFetchRemoteFiles($images);

	$image = array_shift($images);
	$url = $image['url'];
	$resolvedUrl = $image['info']['url'];

	if ($url === $resolvedUrl) {
		$fail++;
	} else {
		$info = ppReplacePostedImage($image, $resolvedUrl, false, true);
		if ($info['status'] !== true) {
			$fail++;
		} else {
			$resolutionMessage = 'resolved and updated to the new URL';
			if (substr($resolvedUrl, 0, 5) === 'https' &&
				substr($url, 0, 5) !== 'https') {
				$resolutionMessage = 'resolved to HTTPS and updated to the new URL';
			}

			$success++;
			$beginUrl = $url;
			$endUrl = $resolvedUrl;
		}
	}

	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} image URL {2}:<br />{3} to<br />{4}', $success, $resolutionMessage, $beginUrl, $endUrl),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('{1} image URL is already resolved', $fail),
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
