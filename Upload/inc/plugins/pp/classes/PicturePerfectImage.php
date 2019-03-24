<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image info
 */

class PicturePerfectImage extends StorableObject010000
{
	protected $setid = 0;
	protected $pid = 0;
	protected $tid = 0;
	protected $fid = 0;
	protected $caption = '';
	protected $url = '';
	protected $original_url = '';

	protected $tableName = 'pp_images';
}

?>
