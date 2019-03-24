<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image info
 */

class PicturePerfectImageTaskList extends StorableObject010001
{
	protected $title = '';
	protected $description = '';
	protected $images = '';
	protected $active = false;

	protected $tableName = 'pp_image_task_lists';
}

?>
