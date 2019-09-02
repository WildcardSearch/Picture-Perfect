<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image info
 */

class PicturePerfectImageThread extends StorableObject010001
{
	protected $tableName = 'pp_image_threads';

	protected $tid = 0;
	protected $fid = 0;

	protected $image_count = 0;
}

?>
