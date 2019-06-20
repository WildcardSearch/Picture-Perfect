<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * module wrapper
 */

class PicturePerfectModule extends InstallableModule010001
{
	/**
	 * @var the path
	 */
	protected $path = PICTURE_PERFECT_MOD_URL;

	/**
	 * @var the function prefix
	 */
	protected $prefix = 'pp';

	/**
	 * @var
	 */
	protected $actionPhrase = '';

	/**
	 * @var
	 */
	protected $pageAction = '';

	/**
	 * @var
	 */
	protected $imageLimit = 1;

	/**
	 * @var
	 */
	protected $createsSet = true;

	/**
	 * @var string
	 */
	protected $cacheKey = 'picture_perfect';

	/**
	 * @var string
	 */
	protected $cacheSubKey = 'addons';

	/**
	 * @var string
	 */
	protected $settingGroupName = 'pp_settings';

	/**
	 * @var string
	 */
	protected $uninstallConstant = 'PP_IN_UNINSTALL';

	/**
	 * run the module parser routine
	 *
	 * @return string the return of the module routine
	 */
	public function processImages($images, $settings)
	{
		return $this->run('process_images', $images, $settings);
	}
}

?>
