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
function pp_host_local_info()
{
	return array(
		'title' => 'Local Rehosts',
		'description' => 'rehost posted images to a location on this server',
		'actionPhrase' => 'Rehost Locally',
		'imageLimit' => 12,
		'version' => '1',
		'settings' => array(
			'path' => array(
				'title' => 'Path',
				'description' => 'location relative to the forum root where the rehosted images should be stored (eg. images or myimages/rehost)',
				'optionscode' => 'text',
				'value' => 'images/picture_perfect/rehost',
			),
			'domain' => array(
				'title' => 'Domain',
				'description' => 'leave this setting blank to use the board URL or enter a new domain here (useful for rehosting images to a subdomain) eg. http://images.myforum.com',
				'optionscode' => 'text',
				'value' => '',
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
 * ensure required settings are available
 *
 * @return bool
 */
function pp_host_local_validate_install()
{
	return true;
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_host_local_upload($images, $settings)
{
	global $html, $mybb, $lang;

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_thread',
		'tid' => $tid,
		'page' => $mybb->input['page'],
	);

	// build path
	$domain = $mybb->settings['bburl'];
	if ($settings['domain']) {
		$domain = $settings['domain'];
	}

	$domain = ppCleanPath($domain);

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
		// already local?
		if (strpos($image['url'], $mybb->settings['bburl']) !== false) {
			$fail++;
			$alreadyRehosted++;
			continue;
		}

		// if an extension isn't specified, keep the original
		$ext = $image['extension'];
		if ($settings['format']) {
			$ext = $settings['format'];
		}

		if ($ext == 'jpeg') {
			$ext = 'jpg';
		} elseif (!$ext) {
			$ext = 'png';
		}

		$baseName = ppBuildRehostBaseName($path, $ext);
		$filename = "{$path}/{$baseName}.{$ext}";

		if ($settings['format'] &&
			$settings['format'] != $ext) {
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
			@imagedestroy($image['image']);
		} else {
			file_put_contents($filename, $image['content']);
		}

		@unlink($image['tmp_url']);

		// now swap the image URL in the post
		if ($domain == $mybb->settings['bburl']) {
			$url = "{$domain}/{$basePath}/{$baseName}.{$ext}";
		} else {
			$url = "{$domain}/{$baseName}.{$ext}";
		}

		if (!ppReplacePostedImage($image, $url)) {
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
		if ($alreadyRehosted) {
			if ($alreadyRehosted != $fail) {
				$messages[] = array(
					'status' => 'success',
					'message' => $lang->sprintf('{1} image(s) could not be successfully rehosted locally, {2} because the image(s) were already hosted locally', $fail, $alreadyRehosted),
				);
			} else {
				$messages[] = array(
					'status' => 'error',
					'message' => $lang->sprintf('{1} image(s) could not be successfully rehosted locally because the image(s) were already hosted locally', $alreadyRehosted),
				);
			}
		} else {
			$messages[] = array(
				'status' => 'error',
				'message' => $lang->sprintf('{1} image(s) could not be successfully rehosted locally', $fail),
			);
		}
	}

	return array(
		'redirect' => $redirectInfo,
		'messages' => $messages,
	);
}


/**
 * build a unique filename for an image that is at least 4 chars long
 *
 * @param  string
 * @param  string
 * @return array
 */
function ppBuildRehostBaseName($path, $ext='png')
{
	$goodChars = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));

	$filename = '';
	while (strlen($filename) < 4 || file_exists("{$path}/{$filename}.{$ext}")) {
		$filename .= $goodChars[rand(0, count($goodChars) - 1)];
	}

	return $filename;
}

?>
