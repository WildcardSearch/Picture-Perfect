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
function pp_imgur_rehost_info()
{
	global $mybb;

	return array(
		'title' => 'Imgur Rehosts',
		'description' => 'rehost posted images to Imgur',
		'actionPhrase' => 'Rehost Images to Imgur',
		'pageAction' => 'view_imgur_rehost',
		'imageLimit' => 12,
		'createsSet' => false,
		'version' => '1.0',
		'settings' => array(
			'accessToken' => array(
				'title' => 'Imgur Application Access Token',
				'description' => 'in order to use this module, you must first register an application with Imgur and receive an access token to be used for anonymous upload',
				'optionscode' => 'text',
				'value' => $mybb->settings['pp_accessToken'],
			),
		),
		'installData' => array(
			'settings' => array(
				'pp_accessToken' => array(
					'name' => 'pp_accessToken',
					'title' => 'Imgur Application Access Token',
					'description' => 'in order to use this module, you must first register an application with Imgur and receive an access token to be used for anonymous upload',
					'optionscode' => 'text',
					'value' => '',
					'disporder' => '100'
				),
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
function pp_imgur_rehost_process_images($images, $settings)
{
	global $html, $mybb, $lang;

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_thread',
		'tid' => $tid,
		'page' => $mybb->input['page'],
	);

	// main loop - process the images
	$fail = $success = 0;
	foreach ($images as $id => $image) {
		// already on Imgur?
		if (strpos($image['url'], 'https://i.imgur.com') !== false) {
			$fail++;
			$alreadyRehosted++;
			continue;
		}

		// attempt Imgur upload
		$url = ppImgurRehost($image['url'], $settings);
		if (!$url) {
			$fail++;
			continue;
		}

		// now replace the image URL in the post
		if (!ppReplacePostImage($image['pid'], $image['url'], $url)) {
			$fail++;
			continue;
		} else {
			$success++;
		}

		// update the image
		$image['url'] = $url;
		$newImage = new PicturePerfectImage($image);
		$newImage->save();
	}

	// build messages
	$messages = array();
	if ($success) {
		$messages[] = array(
			'status' => 'success',
			'message' => $lang->sprintf('{1} image(s) rehosted to Imgur successfully', $success),
		);
	}

	if ($fail) {
		if ($alreadyRehosted) {
			if ($alreadyRehosted != $fail) {
				$messages[] = array(
					'status' => 'success',
					'message' => $lang->sprintf('{1} image(s) could not be rehosted to Imgur successfully, {2} because the image(s) were already hosted on Imgur', $fail, $alreadyRehosted),
				);
			} else {
				$messages[] = array(
					'status' => 'error',
					'message' => $lang->sprintf('{1} image(s) could not be rehosted to Imgur successfully because the image(s) were already hosted on Imgur ¯\_(ツ)_/¯', $fail, $alreadyRehosted),
				);
			}
		} else {
			$messages[] = array(
				'status' => 'error',
				'message' => $lang->sprintf('{1} image(s) could not be rehosted to Imgur successfully', $fail),
			);
		}
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}

/**
 * rehost an image to Imgur
 *
 * @param  string
 * @return bool|string
 */
function ppImgurRehost($url, $settings)
{
	$url = trim($url);
	if (!$url) {
		return false;
	}

	$boundary = 'PicturePerfect000020';

	$curl = curl_init();

	curl_setopt_array($curl,
	array(
		CURLOPT_URL => "https://api.imgur.com/3/image",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => "",
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => "POST",
		CURLOPT_POSTFIELDS => "--{$boundary}\r\nContent-Disposition: form-data; name=\"image\"\r\n\r\n{$url}\r\n--{$boundary}--",
		CURLOPT_HTTPHEADER => array(
			"Authorization: Bearer {$settings['accessToken']}",
			"cache-control: no-cache",
			"content-type: multipart/form-data; boundary={$boundary}",
		),
	));

	$response = curl_exec($curl);
	$json = json_decode($response, true);

	curl_close($curl);

	if (!$json['success'] ||
		!$json['data']['link']) {
		return false;
	} else {
		return $json['data']['link'];
	}
}

?>
