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
function pp_replace_info()
{
	global $mybb;

	return array(
		'title' => 'Replace/Remove Image',
		'description' => 'replace or remove a posted image',
		'actionPhrase' => 'Replace/Remove Images',
		'pageAction' => 'view_replace',
		'imageLimit' => 1,
		'createsSet' => false,
		'version' => '1.0',
		'settings' => array(
			'url' => array(
				'title' => 'Replacement URL',
				'description' => 'the URL of the image to use instead of the currently posted image',
				'optionscode' => 'text',
				'value' => '',
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
function pp_replace_process_images($images, $settings)
{
	global $html, $mybb, $lang;

	$messages = array();

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_thread',
		'tid' => $tid,
		'page' => $mybb->input['page'],
	);

	$image = array_pop($images);

	$url = trim($settings['url']);

	if ($url) {
		// replace the image URL in the post
		if (ppReplacePostImage($image['pid'], $image['url'], $url)) {
			$messages[] = array(
				'status' => 'success',
				'message' => 'Image replaced successfully',
			);

			// update the image
			$image['url'] = $url;
			$newImage = new PicturePerfectImage($image);
			$newImage->save();
		} else {
			$messages[] = array(
				'status' => 'error',
				'message' => 'Image URL could not be replaced',
			);
		}
	} else {
		// remove the image from the post
		if (ppRemovePostedImage($image)) {
			$messages[] = array(
				'status' => 'success',
				'message' => 'Image successfully removed from the forum',
			);

			// update the image
			$newImage = new PicturePerfectImage($image);
			$newImage->remove();
		} else {
			$messages[] = array(
				'status' => 'error',
				'message' => 'Image could not be removed from the forum successfully',
			);
		}
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
