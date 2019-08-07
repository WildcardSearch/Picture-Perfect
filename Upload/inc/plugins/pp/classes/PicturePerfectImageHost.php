<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * module wrapper
 */

class PicturePerfectImageHost extends InstallableModule010001
{
	/**
	 * @var the path
	 */
	protected $path = PICTURE_PERFECT_HOST_URL;

	/**
	 * @var the function prefix
	 */
	protected $prefix = 'pp_host';

	/**
	 * @var string
	 */
	protected $cacheKey = 'picture_perfect';

	/**
	 * @var string
	 */
	protected $cacheSubKey = 'hosts';

	/**
	 * @var
	 */
	protected $actionPhrase = '';

	/**
	 * @var string
	 */
	protected $settingGroupName = 'pp_settings';

	/**
	 * @var string
	 */
	protected $uninstallConstant = 'PP_IN_UNINSTALL';

	/**
	 * attempt to load the module's info
	 *
	 * @param  string base name of the module to load
	 * @return bool true on success, false on fail
	 */
	public function load($module)
	{
		if (!parent::load($module)) {
			return false;
		}

		return $this->validateInstall();
	}

	/**
	 * ensure that the host has been set up
	 *
	 * @return string the return of the module routine
	 */
	protected function validateInstall()
	{
		return $this->run('validate_install');
	}

	/**
	 * run the module parser routine
	 *
	 * @return string the return of the module routine
	 */
	public function upload($images, $settings)
	{
		return $this->run('upload', $images, $settings);
	}
}

?>
