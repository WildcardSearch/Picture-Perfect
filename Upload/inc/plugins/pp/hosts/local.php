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
	$redirectInfo = null;

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

		$result = ppLocalRehostImage($image, $filename, $settings);

		if (!$result) {
			$fail++;
		}

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

function pp_host_local_upload_from_url($url, $filename='')
{
	
}

function pp_host_local_upload_from_base64($url, $filename='')
{
	
}

function pp_host_local_upload_from_form($url, $filename='')
{
	if (!is_array($_FILES) ||
		empty($_FILES)) {
		return false;
	}

	foreach ($_FILES as $key => $file) {
		$result = ppLocalHostCheckUploadedImage($file);

		if (!is_array($result) ||
			empty($result)) {
			return false;
		}

		if (!move_uploaded_file($url, $filename)) {
			return false;
		}
	}
}

function pp_host_local_upload_from_string($url, $filename='')
{
	
}

function ppLocalRehostImage($image, $filename, $settings)
{
	$result = false;

	if ($settings['format'] &&
		$settings['format'] != $ext) {
		$i = @imagecreatefromstring($image['content']);

		if (!$i ||
			!is_resource($i)) {
			return false;
		}

		$result = false;
		switch ($ext) {
		case 'bmp':
			$result = @imagewbmp($image['image'], $filename);
			break;
		case 'gif':
			$result = @imagegif($image['image'], $filename);
			break;
		case 'jpg':
			$result = @imagejpeg($image['image'], $filename);
			break;
		case 'png':
			$result = @imagepng($image['image'], $filename);
			break;
		}

		// clean up
		@imagedestroy($image['image']);
	} else {
		$bw = file_put_contents($filename, $image['content']);

		$result = $bw > 0;
	}

	return $result;
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

/**
 * check an uploaded image and return info
 *
 * @return array
 */
function ppLocalHostCheckUploadedImage($file)
{
	if (isset($file['error']) &&
		$file['error'] != UPLOAD_ERR_OK) {
		switch ($file['error']) {
		case UPLOAD_ERR_NO_FILE:
			return 'No file sent.';
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return 'Exceeded filesize limit.';
		default:
			return 'Unknown errors.';
		}
	}

	$ext = 'png';
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	switch ($finfo->file($file['tmp_name'])) {
	case 'image/gif':
		$ext = 'gif';
		break;
	case 'image/png':
		$ext = 'png';
		break;
	case 'image/x-ms-bmp':
	case 'image/x-windows-bmp':
	case 'image/bmp':
		$ext = 'bmp';
		break;
	case 'image/jpeg':
		$ext = 'jpg';
		break;
	default:
		return 'Invalid file format.';
	}

	$file['ext'] = $ext;

	return $file;
}

?>
