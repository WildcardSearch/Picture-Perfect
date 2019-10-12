<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image info
 */

class PicturePerfectImage extends StorableObject010001
{
	protected $tableName = 'pp_images';

	protected $setid = 0;
	protected $pid = 0;
	protected $tid = 0;
	protected $fid = 0;

	protected $url = '';
	protected $original_url = '';

	protected $caption = '';

	protected $imagechecked = false;
	protected $width = 0;
	protected $height = 0;
	protected $filesize = 0;
	protected $color_average = '';
	protected $color_opposite = '';

	protected $deadimage = false;
	protected $secureimage = false;
}

?>
