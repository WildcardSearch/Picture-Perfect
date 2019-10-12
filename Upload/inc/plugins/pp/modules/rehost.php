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
		'contentRequired' => false,
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

	$fail = true;
	if (!empty($settings['host'])) {
		$host = new PicturePerfectImageHost($settings['host']);

		if ($host->isValid()) {
			$fail = false;
		}
	}

	if ($fail) {
		return array(
			'redirect' => null,
			'messages' => array(
				array(
					'status' => 'error',
					'message' => 'invalid host',
				),
			),
		);
	}

	return $host->upload($images, $settings);
}

?>
