<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper to handle the plugin's cache
 */

class PicturePerfectCache extends WildcardPluginCache010200
{
	/**
	 * @var  string cache key
	 */
	protected $cacheKey = 'picture_perfect';

	/**
	 * @var  string cache sub key
	 */
	protected $subKey = '';

	/**
	 * @return instance of the child class
	 */
	static public function getInstance()
	{
		static $instance;
		if (!isset($instance)) {
			$instance = new PicturePerfectCache;
		}
		return $instance;
	}
}

?>
