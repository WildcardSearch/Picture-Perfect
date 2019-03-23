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
				'title' => 'Replacement Content',
				'description' => 'the URL or text replacement to use instead of the currently posted image',
				'optionscode' => 'text',
				'value' => '',
			),
			'text_replacement' => array(
				'title' => 'Replacement With Text',
				'description' => 'YES to replace the entire MyCode with the supplied text (above), NO (default) to replace the image URL in the post<br />Replacement text may contain BB Code.',
				'optionscode' => 'yesno',
				'value' => '0',
			),
			'replace_all' => array(
				'title' => 'Replacement All',
				'description' => 'YES to replace any other images in the post with the same URL, NO (default) to only replace the selected image',
				'optionscode' => 'yesno',
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

	$action = 'replaced';
	if (!$url) {
		$action = 'removed';
	}

	$thing = 'Image URL';
	if ($settings['text_replacement'] || $action == 'removed') {
		$thing = 'Image';
	}

	// replace the image URL in the post
	if (ppReplacePostedImage($image, $url, $settings['text_replacement'], $settings['replace_all'])) {
		$messages[] = array(
			'status' => 'success',
			'message' => "{$thing} {$action} successfully",
		);

		if ($action == 'replaced' && !$settings['replace_all'] && !$settings['text_replacement']) {
			// update the image
			$image['url'] = $url;
			$newImage = new PicturePerfectImage($image);
			$newImage->save();
		} elseif (!$settings['replace_all']) {
			// update the image
			$newImage = new PicturePerfectImage($image);
			$newImage->remove();
		}
	} else {
		$messages[] = array(
			'status' => 'error',
			'message' => "{$thing} could not be {$action}",
		);
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
