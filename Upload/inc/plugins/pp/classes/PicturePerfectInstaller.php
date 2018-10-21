<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper to handle the plugin's installer
 */

class PicturePerfectInstaller extends WildcardPluginInstaller020000
{
	static public function getInstance()
	{
		static $instance;

		if (!isset($instance)) {
			$instance = new PicturePerfectInstaller();
		}
		return $instance;
	}

	/**
	 * link the installer to our data file
	 *
	 * @param  string path to the install data
	 * @return void
	 */
	public function __construct($path = '')
	{
		parent::__construct(MYBB_ROOT . 'inc/plugins/pp/install_data.php');
	}
}

?>
