<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image info
 */

class PicturePerfectImageTask extends StorableObject010000
{
	protected $lid = 0;
	protected $pid = 0;
	protected $setid = 0;
	protected $title = '';
	protected $description = '';
	protected $addon = '';
	protected $settings = array();
	protected $task_order = '';

	protected $tableName = 'pp_image_tasks';

	/**
	 * override load and decode arrays
	 *
	 * @param  array|int data or id
	 * @return bool true on success, false on fail
	 */
	public function load($data)
	{
		if (!parent::load($data)) {
			return false;
		}

		if (isset($this->settings)) {
			// if so decode them
			$this->settings = json_decode($this->settings, true);
		}

		return true;
	}
}

?>
