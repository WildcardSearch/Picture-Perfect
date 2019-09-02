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
function pp_color_average_info()
{
	global $mybb;

	return array(
		'title' => 'Get Image Color Average',
		'description' => 'store an average of the colors in the image and a visible foreground color, as well',
		'actionPhrase' => 'Get Image Color Averages',
		'imageLimit' => 12,
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
function pp_color_average_process_images($images)
{
	global $mybb, $db, $lang, $html;

	$messages = array();

	// set up redirect
	$redirectInfo = null;

	// get the images and some info about them
	$images = ppFetchRemoteFiles($images);
	$images = ppGetImageInfo($images);

	$doneUrls = array();
	foreach ($images as $id => $image) {
		if (in_array($image['url'], $doneUrls)) {
			$success++;
			continue;
		}

		$image['color_average'] = "888888";
		$image['color_opposite'] = "f2f2f2";

		if ($image['extension']) {
			$ca = ppGetImageColorAverage(array('content' => $image['content']));
			if ($ca) {
				$image['color_average'] = $ca['average'];
				$image['color_opposite'] = $ca['opposite'];
			}
		}

		$updateArray = array(
			'color_average' => $image['color_average'],
			'color_opposite' => $image['color_opposite'],
		);

		$url = $db->escape_string($image['url']);
		$result = $db->update_query('pp_images', $updateArray, "url='{$url}'");

		if (!$result) {
			$fail++;
			continue;
		}

		$doneUrls[] = $image['url'];
		$success++;
	}

	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} image color average(s) calculated successfully', $success),
		);
	}

	if ($fail) {
		$messages[] = array(
			'status' => 'error',
			'message' => $lang->sprintf('{1} image color average(s) could not be calculated successfully', $fail),
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
