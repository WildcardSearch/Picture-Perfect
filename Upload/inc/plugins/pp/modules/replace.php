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
		'imageLimit' => 12,
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
	global $mybb, $db, $lang, $html;

	$messages = array();

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_thread',
		'tid' => $tid,
	);

	if ($mybb->input['page'] > 1) {
		$redirectInfo['page'] = $mybb->input['page'];
	}

	$url = trim($settings['url']);

	$action = 'replaced';
	if (!$url) {
		$action = 'removed';
	}

	$adjustImageCount = false;
	$thing = 'Image URL';
	if ($settings['text_replacement'] || $action == 'removed') {
		$adjustImageCount = true;
		$thing = 'Image';
	}

	$done = array();
	foreach ($images as $id => $image) {
		if (in_array($id, $done)) {
			continue;
		}

		$info = ppReplacePostedImage($image, $url, $settings['text_replacement'], $settings['replace_all']);
		if ($info['status'] === true) {
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
			} else {
				$done = array_merge($done, $info['affected']);
				continue;
			}

			$done[] = $id;
		} else {
			$messages[] = array(
				'status' => 'error',
				'message' => "{$thing} could not be {$action}: {$info['message']}",
			);
		}
	}

	if ($adjustImageCount) {
		$threadQuery = $db->simple_select('pp_images', 'COUNT(id) as image_count', "tid='{$tid}'");
		$iCount = (int) $db->fetch_field($threadQuery, 'image_count');

		$db->update_query('pp_image_threads', array('image_count' => $iCount), "tid='{$tid}'");

		if ($iCount == 0) {
			unset($redirectInfo['tid']);

			if (isset($mybb->input['fid']) && $mybb->input['fid']) {
				$fid = (int) $mybb->input['fid'];

				$forumQuery = $db->simple_select('pp_images', 'COUNT(id) as image_count', "fid='{$fid}'");
				$fCount = $db->fetch_field($forumQuery, 'image_count');
			}

			if ($fCount) {
				$redirectInfo['action'] = 'view_forum';
				$redirectInfo['fid'] = $mybb->input['fid'];
			} else {
				$redirectInfo['action'] = 'forums';
			}
		}
	}

	$dCount = count($done);
	if ($dCount) {
		$messages[] = array(
			'status' => 'success',
			'message' => "{$dCount} images affected.",
		);
	} else {
		$messages[] = array(
			'status' => 'error',
			'message' => "0 images affected.",
		);
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

?>
