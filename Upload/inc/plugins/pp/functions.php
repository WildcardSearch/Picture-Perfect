<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * functions file
 */

// disallow direct access to this file for security reasons.
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * retrieve any detected modules
 *
 * @return array PicturePerfectModule
 */
function ppGetAllModules()
{
	$returnArray = array();

	// load all detected modules
	foreach (new DirectoryIterator(PICTURE_PERFECT_MOD_URL) as $file) {
		if (!$file->isFile() ||
			$file->isDot() ||
			$file->isDir()) {
			continue;
		}

		$extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);

		// only PHP files
		if ($extension != 'php') {
			continue;
		}

		// extract the baseName from the module file name
		$filename = $file->getFilename();
		$module = substr($filename, 0, strlen($filename) - 4);

		// attempt to load the module
		$returnArray[$module] = new PicturePerfectModule($module);
	}
	return $returnArray;
}

/**
 * rehost images locally
 *
 * @param  array
 * @param  string
 * @return void
 */
function ppRehostImages($images, $path)
{
	if (!is_array($images) ||
		empty($images) ||
		!ppCreateFolder($path) ) {
		return;
	}

	foreach ($images as $id => &$image) {
		$uniqueID = uniqid();
		file_put_contents("{$path}/{$image['tid']}-{$image['pid']}-{$uniqueID}.{$image['extension']}");
	}
}

/**
 * create a folder if it does not exist
 *
 * @param  string
 * @return void
 */
function ppCreateFolder($folder)
{
	if (!$folder) {
		return false;
	}

	return (file_exists($folder) ||
		@mkdir($folder));
}

/**
 * retrieve image information
 *
 * @param  array
 * @return void
 */
function ppGetImageInfo($images)
{
	if (!is_array($images) ||
		empty($images)) {
		return;
	}

	foreach ($images as $id => &$image) {
		switch ($image['info']['content_type']) {
		case 'image/bmp':
			$image['extension'] = 'bmp';
			break;
		case 'image/png':
			$image['extension'] = 'png';
			break;
		case 'image/gif':
			$image['extension'] = 'gif';
			break;
		case 'image/jpg':
		case 'image/jpeg':
			$image['extension'] = 'jpg';
			break;
		default:
			continue;
		}
		$image['filename'] = pathinfo($image['url'], PATHINFO_FILENAME);
	}

	return $images;
}

/**
 * retrieve file info and content
 *
 * @param  array
 * @return array
 */
function ppFetchRemoteFiles($files)
{
	if (!is_array($files) ||
		empty($files)) {
		return false;
	}

	$multiHandle = curl_multi_init();
	$threads = null;
	foreach ($files as $id => $file) {
		$h = $file['handle'] = curl_init($file['url']);
		curl_setopt($h, CURLOPT_USERAGENT, 'Picture Perfect');

		curl_setopt($h, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($h, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($h, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($h, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($h, CURLOPT_TIMEOUT, 20);

		curl_multi_add_handle($multiHandle, $h);

		$files[$id] = $file;
	}

	do {
		$n = curl_multi_exec($multiHandle, $threads);
	} while($threads > 0);

	foreach ((array) $files as $id => $file) {
		$h = $file['handle'];
		$file['info'] = curl_getinfo($h);

		$file['content'] = null;
		if ($file['info']['http_code'] == 200) {
			$file['content'] = curl_multi_getcontent($h);

			$file['tmp_url'] = MYBB_ROOT."images/picture_perfect/temp/temp_{$id}";
			file_put_contents($file['tmp_url'], $file['content']);
		} else {
			$file['error'] = curl_error($h);
		}
		curl_multi_remove_handle($multiHandle, $h);
		curl_close($h);

		$files[$id] = $file;
	}
	curl_multi_close($multiHandle);

	return $files;
}

/**
 * resize image but retain aspect ratio.
 *
 * @param string file name
 * @param string new file name
 * @param int
 * @param int
 * @param bool
 *
 * returns true upon success and false on failure.
 */
function ppResizeImage($source, $destination, $width, $height, $crop=false)
{
	// Check the image file header
	if (!list($w, $h) = @getimagesize($source)) {
		return false;
	}

	$type = strtolower(substr(strrchr($destination, "."), 1));
	if ($type == 'jpeg') {
		$type = 'jpg';
	}

	switch($type) {
	case 'bmp':
		$img = @imagecreatefromwbmp($source); break;
	case 'gif':
		$img = @imagecreatefromgif($source); break;
	case 'jpg':
		$img = @imagecreatefromjpeg($source); break;
	case 'png':
		$img = @imagecreatefrompng($source); break;
	default:
		return false;
	}

	if ($crop &&
		$w > $width &&
		$h > $height) {
		$ratio = max($width / $w, $height / $h);
		$h = $height / $ratio;
		$x = ($w - $width / $ratio) / 2;
		$w = $width / $ratio;
	} elseif ($w > $width || $h > $height) {
		$ratio = min($width / $w, $height / $h);
		$width = $w * $ratio;
		$height = $h * $ratio;
		$x = 0;
	}

	if ($type == 'gif' &&
		ppIsAnimatedGif($source)) {
		if (class_exists('Imagick')) {
			return ppResizeAnimatedGif($source, $destination, $width, $height);
		}

		return false;
	}

	$new = @imagecreatetruecolor($width, $height);

	// preserve transparency
	if ($type == "gif" or $type == "png") {
		@imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
		@imagealphablending($new, false);
		@imagesavealpha($new, true);
	}

	@imagecopyresampled($new, $img, 0, 0, $x, 0, $width, $height, $w, $h);

	switch ($type) {
	case 'bmp':
		@imagewbmp($new, $destination);
		break;
	case 'gif':
		@imagegif($new, $destination);
		break;
	case 'jpg':
		@imagejpeg($new, $destination);
		break;
	case 'png':
		@imagepng($new, $destination);
		break;
	}
	return true;
}

/**
 * resize an animated GIF
 *
 * @param string file name
 * @param string new file name
 * @param int
 * @param int
 *
 * returns true upon success and false on failure.
 */
function ppResizeAnimatedGif($source, $destination, $width, $height)
{
	$imagick = new Imagick($source);

	$format = $imagick->getImageFormat();
	if ($format != 'GIF') {
		return false;
	}

	$imagick = $imagick->coalesceImages();

	do {
		$imagick->resizeImage($width, $height, Imagick::FILTER_BOX, 1);
	} while ($imagick->nextImage());

	$imagick = $imagick->deconstructImages();
	$imagick->writeImages($destination, true);

	$imagick->clear();
	$imagick->destroy();

	return true;
}

/**
 * detect animated GIF image
 *
 * from https://github.com/Sybio/GifFrameExtractor by Clément Guillemain
 *
 * @param  string
 * @return bool
 */
function ppIsAnimatedGif($filename)
{
	if (!($fh = @fopen($filename, 'rb'))) {
		return false;
	}
	
	$count = 0;

	while (!feof($fh) && $count < 2) {
		// read 100kb at a time
		$chunk = fread($fh, 1024 * 100);

		// matches any image data blocks
		$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
	}
	
	fclose($fh);
	return $count > 1;
}

/**
 * creates database rows detailing the various images posted in threads
 *
 * @param  int
 * @param  int
 * @return string
 */
function ppStorePostedImages($pid, $tid, $fid, $message)
{
	global $db;

	$insert_arrays = array();
	foreach((array) ppGetPostImages($message) as $source) {
		$insert_arrays[] = array(
			'setid' => 0,
			'pid' => (int) $pid,
			'tid' => (int) $tid,
			'fid' => (int) $fid,
			'url' => $db->escape_string($source),
			'dateline' => TIME_NOW,
		);
	}

	if (!empty($insert_arrays)) {
		$db->insert_query_multiple('pp_images', $insert_arrays);
	}

	return count($insert_arrays);
}

/**
 * checks post message for posted images and returns their URLs
 *
 * @param  int
 * @param  int
 * @return string
 */
function ppGetPostImages($message, $full=false)
{
	$patterns = array(
		array(
			"key" => 2,
			"pattern" => "#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is"
		),
		array(
			"key" => 4,
			"pattern" => "#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is"
		),
		array(
			"key" => 3,
			"pattern" => "#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is"
		),
		array(
			"key" => 5,
			"pattern" => "#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is"
		),
	);

	$message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "", $message);
	$message = ppStripQuotes($message);

	if (strlen($message) == 0) {
		return;
	}

	$images = array();
	foreach ($patterns as $patternArray) {
		preg_match_all($patternArray['pattern'], $message, $matches, PREG_SET_ORDER);

		if (!is_array($matches) ||
			empty($matches)) {
			continue;
		}

		foreach ($matches as $match) {
			$url = $match[$patternArray['key']];

			if ($full !== true) {
				$images[] = $url;
				continue;
			}

			$images[] = $match[0];
		}
	}
	return $images;
}

/**
 * strips all quote tags (and their contents) from a post message
 *
 * @param  string
 * @return string
 */
function ppStripQuotes($message)
{
	// Assign pattern and replace values.
	$pattern = array(
		"#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si",
		"#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si",
		"#\[\/quote\](\r\n?|\n?)#si"
	);

	do {
		$message = preg_replace($pattern, '', $message, -1, $count);
	} while($count);

	$find = array(
		"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
		"#(\r\n*|\n*)<\/blockquote>#"
	);
	return preg_replace($find, '', $message);
}

/**
 * replace the URL of any image MyCodes in the specified post
 *
 * @param  int
 * @param  string
 * @param  string
 * @return bool success/fail
 */
function ppReplacePostImage($pid, $currentUrl, $newUrl)
{
	global $db;

	$pid = (int) $pid;
	if (!$pid) {
		return false;
	}

	$query = $db->simple_select('posts', 'message', "pid='{$pid}'");
	if ($db->num_rows($query) <= 0) {
		return false;
	}

	$message = $db->fetch_field($query, 'message');

	$message = str_replace($currentUrl, $newUrl, $message);

	$db->update_query('posts', array('message' => $db->escape_string($message)), "pid='{$pid}'");

	return true;
}

/**
 * remove any image MyCodes in the specified post completely
 *
 * @param  array image info
 * @return bool success/fail
 */
function ppRemovePostedImage($image)
{
	global $db;

	$pid = (int) $image['pid'];
	if (!$pid) {
		return false;
	}

	$query = $db->simple_select('posts', 'message', "pid='{$pid}'");
	if ($db->num_rows($query) <= 0) {
		return false;
	}

	$message = $db->fetch_field($query, 'message');

	$images = ppGetPostImages($message, true);

	foreach($images as $fullCode) {
		if (strpos($fullCode, $image['url']) === false) {
			continue;
		}

		$message = str_replace($fullCode, '', $message);
	}

	$db->update_query('posts', array('message' => $db->escape_string($message)), "pid='{$pid}'");

	$threadQuery = $db->simple_select('pp_image_threads', 'image_count', "tid='{$tid}'");
	$imageCount = (int) $db->fetch_field($query, 'image_count') - 1;

	if ($imageCount < 0) {
		$imageCount = 0;
	}

	$db->update_query('pp_image_threads', array('image_count' => $imageCount), "tid='{$tid}'");

	return strpos($image['url'], $message) === false;
}

/**
 * trim preceding/trailing slashes
 *
 * @param  string
 * @param  bool true to only check preceding slashes
 * @return string clean path
 */
function ppCleanPath($path, $preOnly=false)
{
	if (substr($path, 0, 1) == '/') {
		$path = substr($path, 1);
	}

	if ($preOnly) {
		return $path;
	}

	if (substr($path, strlen($path) - 1) == '/') {
		$path = substr($path, 0, strlen($path) - 1);
	}

	return $path;
}

/**
 * remove protocol if given and trim if query if applicable
 *
 * @param  string
 * @return string clean path
 */
function ppCleanDomain($domain='')
{
	if (!$domain) {
		return '';
	}

	$hostname = str_replace(array('http://', 'https://'), '', $domain);

	if (!$hostname) {
		return '';
	}

	$hostname_array = explode('/', $hostname);

	if (count($hostname_array) == 0) {
		return '';
	}

	$hostname = $hostname_array[0];
	if (!$hostname) {
		return '';
	}

	return $hostname;
}

/**
 * trim domain string and check to see that it exists
 *
 * @param  string
 * @return string clean path
 */
function ppValidateDomain($domain)
{
	$domain = ppCleanDomain($domain);

	if (!$domain) {
		return false;
	}

	$resolved_ip = gethostbyname($hostname);

	// gethostbyname returns the given string on error
	if ($resolved_ip == $hostname) {
		return false;
	}

	return true;
}

?>
