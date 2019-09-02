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
function pp_host_imgur_info()
{
	global $mybb;

	return array(
		'title' => 'Imgur Rehosts',
		'description' => 'rehost posted images to Imgur',
		'actionPhrase' => 'Rehost to Imgur',
		'imageLimit' => 12,
		'createsSet' => false,
		'version' => '1.0',
		'settings' => array(
			'anonymous' => array(
				'title' => 'Upload Anonymously?',
				'description' => 'YES (default) to upload anonymously (requires <span style="font-weight: bold; font-family: courier new;">Imgur Application Client ID</span>), NO to upload to an account designated by your application (requires <span style="font-weight: bold; font-family: courier new;">Imgur Application Access Token</span>)',
				'optionscode' => 'yesno',
				'value' => 1,
			),
			'clientId' => array(
				'title' => 'Imgur Application Client ID',
				'description' => 'in order to use this module, you must first register an application with Imgur and receive a client ID to be used for anonymous upload',
				'optionscode' => 'text',
				'value' => $mybb->settings['pp_clientId'],
			),
			'accessToken' => array(
				'title' => 'Imgur Application Access Token',
				'description' => 'in order to use this module, you must first register an application with Imgur and receive an access token to be used for upload to the designated Imgur account',
				'optionscode' => 'text',
				'value' => $mybb->settings['pp_accessToken'],
			),
		),
		'installData' => array(
			'settings' => array(
				'pp_clientId' => array(
					'name' => 'pp_clientId',
					'title' => 'Imgur Application Client ID',
					'description' => 'in order to use this module, you must first register an application with Imgur and receive a client ID to be used for anonymous upload',
					'optionscode' => 'text',
					'value' => '',
					'disporder' => '100'
				),
				'pp_accessToken' => array(
					'name' => 'pp_accessToken',
					'title' => 'Imgur Application Access Token',
					'description' => 'in order to use this module, you must first register an application with Imgur and receive an access token to be used for anonymous upload',
					'optionscode' => 'text',
					'value' => '',
					'disporder' => '110'
				),
			),
		),
	);
}

/**
 * ensure required settings are available
 *
 * @return bool
 */
function pp_host_imgur_validate_install()
{
	global $mybb;

	return (isset($mybb->settings['pp_clientId']) &&
		!empty($mybb->settings['pp_clientId'])) ||
		(isset($mybb->settings['pp_accessToken']) &&
		!empty($mybb->settings['pp_accessToken']));
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_host_imgur_upload($images, $settings)
{
	global $html, $mybb, $lang;

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = null;

	// main loop - process the images
	$fail = $success = 0;
	$imgurRateLimitReached = false;
	foreach ($images as $id => $image) {
		// already on Imgur?
		if (strpos($image['url'], 'https://i.imgur.com') !== false ||
			strpos($image['url'], 'http://i.imgur.com') !== false) {
			$fail++;
			$alreadyRehosted++;
			continue;
		}

		// attempt Imgur upload
		$info = ppImgurRehost($image['url'], $settings);

		if (!$info['link'] ||
			!empty($info['error'])) {
			if ($info['error']['code'] == 429) {
				$imgurRateLimitReached = true;
				$imagurTooFastMessage = $info['error']['message'];
				break;
			}

			$fail++;
			continue;
		}

		$url = $info['link'];

		// now replace the image URL in the post
		if (!ppReplacePostedImage($image, $url)) {
			$fail++;
			continue;
		} else {
			$success++;
		}

		// update the image
		$image['original_url'] = $image['url'];
		$image['url'] = $url;
		$image['imagechecked'] = false;
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

	if ($imgurRateLimitReached) {
		$messages[] = array(
			'status' => 'success',
			'message' => "Additional Imgur Error Message: &quot;{$imagurTooFastMessage}&quot;",
		);
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

	if ($settings['anonymous'] &&
		isset($settings['clientId']) &&
		$settings['clientId']) {
		$auth = "Client-ID {$settings['clientId']}";
	} elseif (!$settings['anonymous'] &&
		isset($settings['accessToken']) &&
		$settings['accessToken']) {
		$auth = "Bearer {$settings['accessToken']}";
	} else {
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
			"Authorization: {$auth}",
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
		return $json['data'];
	}
}

?>
