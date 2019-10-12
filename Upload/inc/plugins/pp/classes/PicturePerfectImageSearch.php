<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * wrapper for image searches
 */

class PicturePerfectImageSearch extends StorableObject010001
{
	protected $tableName = 'pp_image_searches';

	protected $title = 'Image Search';
	protected $description = '';

	protected $url_comparison_method = PP_SEARCH_URL_CONTAINS;
	protected $url = '';

	protected $status = PP_SEARCH_EITHER;
	protected $security_status = PP_SEARCH_EITHER;
	protected $color_average_status = PP_SEARCH_EITHER;
	protected $check_status = PP_SEARCH_EITHER;
	protected $caption_status = PP_SEARCH_EITHER;

	public function buildSqlWhere()
	{
		global $db;

		$where = 'setid=0';
		if (isset($this->url) &&
			strlen($this->url) > 3) {
			$searchUrl = $db->escape_string($this->url);
			$searchUrl = strtr($searchUrl, array(
				'%' => '=%',
				'_' => '=_',
				'=' => '==',
			));

			switch ($this->url_comparison_method) {
			case PP_SEARCH_URL_STARTS_WITH:
				$where .= " AND url LIKE '{$searchUrl}%' ESCAPE '='";
				break;
			case PP_SEARCH_URL_CONTAINS:
				$where .= " AND INSTR(url, '{$searchUrl}') > 0";
				break;
			case PP_SEARCH_URL_ENDS_WITH:
				$where .= " AND url LIKE '%{$searchUrl}' ESCAPE '='";
				break;
			}
		}

		if (isset($this->status)) {
			switch ($this->status) {
			case PP_SEARCH_YES:
				$where .= " AND deadimage=0";
				break;
			case PP_SEARCH_NO:
				$where .= " AND NOT deadimage=0";
				break;
			}
		}

		if (isset($this->security_status)) {
			switch ($this->security_status) {
			case PP_SEARCH_YES:
				$where .= " AND NOT secureimage=0";
				break;
			case PP_SEARCH_NO:
				$where .= " AND secureimage=0";
				break;
			}
		}

		if (isset($this->color_average_status)) {
			switch ($this->color_average_status) {
			case PP_SEARCH_YES:
				$where .= " AND NOT (color_average='' OR color_average IS NULL) AND NOT (color_opposite ='' OR color_opposite IS NULL)";
				break;
			case PP_SEARCH_NO:
				$where .= " AND (color_average='' OR color_average IS NULL) AND (color_opposite ='' OR color_opposite IS NULL)";
				break;
			}
		}

		if (isset($this->check_status)) {
			switch ($this->check_status) {
			case PP_SEARCH_YES:
				$where .= " AND NOT imagechecked=0";
				break;
			case PP_SEARCH_NO:
				$where .= " AND imagechecked=0";
				break;
			}
		}

		if (isset($this->caption_status)) {
			switch ($this->caption_status) {
			case PP_SEARCH_YES:
				$where .= " AND NOT (caption='' OR caption IS NULL)";
				break;
			case PP_SEARCH_NO:
				$where .= " AND (caption='' OR caption IS NULL)";
				break;
			}
		}

		return $where;
	}

	public function buildDescription()
	{
		global $db;

		$descPieces = array();
		if (isset($this->url) &&
			strlen($this->url) > 3) {
			switch ($this->url_comparison_method) {
			case PP_SEARCH_URL_STARTS_WITH:
				$descPieces[] = "start with \"{$this->url}\"";
				break;
			case PP_SEARCH_URL_CONTAINS:
				$descPieces[] = "contain \"{$this->url}\"";
				break;
			case PP_SEARCH_URL_ENDS_WITH:
				$descPieces[] = "end with \"{$this->url}\"";
				break;
			}
		}

		if (isset($this->status)) {
			switch ($this->status) {
			case PP_SEARCH_YES:
				$descPieces[] = "are not dead";
				break;
			case PP_SEARCH_NO:
				$descPieces[] = "are dead";
				break;
			}
		}

		if (isset($this->security_status)) {
			switch ($this->security_status) {
			case PP_SEARCH_YES:
				$descPieces[] = "are secure";
				break;
			case PP_SEARCH_NO:
				$descPieces[] = "are not secure";
				break;
			}
		}

		if (isset($this->color_average_status)) {
			switch ($this->color_average_status) {
			case PP_SEARCH_YES:
				$descPieces[] = "have been color averaged";
				break;
			case PP_SEARCH_NO:
				$descPieces[] = "have not been color averaged";
				break;
			}
		}

		if (isset($this->check_status)) {
			switch ($this->check_status) {
			case PP_SEARCH_YES:
				$descPieces[] = "have been checked";
				break;
			case PP_SEARCH_NO:
				$descPieces[] = "have not been checked";
				break;
			}
		}

		if (isset($this->caption_status)) {
			switch ($this->caption_status) {
			case PP_SEARCH_YES:
				$descPieces[] = "have been captioned";
				break;
			case PP_SEARCH_NO:
				$descPieces[] = "have not been captioned";
				break;
			}
		}

		$pieceCount = count($descPieces);
		if ($pieceCount < 1) {
			$description = 'All Images';
		} elseif ($pieceCount == 2) {
			$description = "Images that {$descPieces[0]} and {$descPieces[1]}";
		} elseif ($pieceCount > 2) {
			$description = 'Images that '.array_shift($descPieces);
			$lastPiece = array_pop($descPieces);

			if (count($descPieces) > 0) {
				$description .= ', '.implode(', ', $descPieces);
			}

			$description .= ", and {$lastPiece}";
		} else {
			$description = "Images that {$descPieces[0]}";
		}

		$this->description = $description.'.';
	}
}

?>
