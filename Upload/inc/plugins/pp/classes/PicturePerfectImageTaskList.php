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

	/**
	 * add image ids, culling any duplicates
	 *
	 * @param  array|int
	 * @return int
	 */
	public function addImages($ids)
	{
		if (!is_array($ids)) {
			$ids = (array) $ids;
		}

		if (empty($ids)) {
			return 0;
		}

		$iArray = explode(',', $this->images);
		if (count($iArray) < 1) {
			$this->images = implode(',', $ids);
			return count($ids);
		}

		$allIds = array_merge($iArray, $ids);
		$allIds = array_unique($allIds);

		$this->images = implode(',', $allIds);

		$adjustedCount = (int) count($allIds) - count($iArray);

		return $adjustedCount;
	}
}

?>
