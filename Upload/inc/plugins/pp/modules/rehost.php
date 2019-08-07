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
function pp_rehost_info()
{
	return array(
		'title' => 'Rehost',
		'description' => 'rehost posted images using an installed image host module',
		'actionPhrase' => 'Rehost Images',
		'imageLimit' => 12,
		'createsSet' => false,
		'version' => '1',
	);
}

/**
 * process images
 *
 * @param  array
 * @return void
 */
function pp_rehost_process_images($images, $settings)
{
	global $html, $mybb, $lang;

	// set up redirect
	$tid = $images[key($images)]['tid'];
	$redirectInfo = array(
		'action' => 'view_thread',
		'tid' => $tid,
		'page' => $mybb->input['page'],
	);

	$fail = true;
	if (!empty($settings['host'])) {
		$host = new PicturePerfectImageHost($settings['host']);

		if ($host->isValid()) {
			$fail = false;
		}
	}

	if ($fail) {
		return array(
			'redirect' => $redirectInfo,
			'messages' => 'invalid host',
		);
	}

	return $host->upload($images, $settings);
}

?>
