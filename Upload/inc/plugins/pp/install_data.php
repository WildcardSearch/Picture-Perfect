<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains data used by classes/installer.php
 */

$tables = array(
	'pgsql' => array(
		'pp_image_threads' => array(
			'id' => 'SERIAL',
			'tid' => 'INT NOT NULL',
			'fid' => 'INT NOT NULL',
			'image_count' => 'INT DEFAULT 0',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
		'pp_images' => array(
			'id' => 'SERIAL',
			'setid' => 'INT',
			'pid' => 'INT NOT NULL',
			'tid' => 'INT NOT NULL',
			'fid' => 'INT NOT NULL',

			'url' => 'TEXT NOT NULL',
			'original_url' => 'TEXT',

			'caption' => 'TEXT',

			'imagechecked' => 'INT DEFAULT 0',
			'width' => 'INT DEFAULT 0',
			'height' => 'INT DEFAULT 0',
			'filesize' => 'INT DEFAULT 0',
			'color_average' => 'TEXT',
			'color_opposite' => 'TEXT',

			'deadimage' => 'INT DEFAULT 0',
			'secureimage' => 'INT DEFAULT 0',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
		'pp_image_sets' => array(
			'id' => 'SERIAL',
			'title' => 'TEXT',
			'description' => 'TEXT',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
		'pp_image_tasks' => array(
			'id' => 'SERIAL',
			'lid' => 'INT',
			'pid' => 'INT',
			'setid' => 'INT',
			'title' => 'TEXT',
			'description' => 'TEXT',
			'addon' => 'TEXT NOT NULL',
			'settings' => 'TEXT',
			'task_order' => 'INT DEFAULT 0',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
		'pp_image_task_lists' => array(
			'id' => 'SERIAL',
			'title' => 'TEXT',
			'description' => 'TEXT',
			'images' => 'TEXT',
			'active' => 'INT DEFAULT 0',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
	),
	'pp_image_threads' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'tid' => 'INT(10) NOT NULL',
		'fid' => 'INT(10) NOT NULL',
		'image_count' => 'INT(10) DEFAULT 0',
		'dateline' => 'INT(10)',
	),
	'pp_images' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'setid' => 'INT(10)',
		'pid' => 'INT(10) NOT NULL',
		'tid' => 'INT(10) NOT NULL',
		'fid' => 'INT(10) NOT NULL',

		'url' => 'TEXT NOT NULL',
		'original_url' => 'TEXT',

		'caption' => 'TEXT',

		'imagechecked' => 'INT(1) DEFAULT 0',
		'width' => 'INT(10) DEFAULT 0',
		'height' => 'INT(10) DEFAULT 0',
		'filesize' => 'INT(10) DEFAULT 0',
		'color_average' => 'TEXT',
		'color_opposite' => 'TEXT',

		'deadimage' => 'INT(1) DEFAULT 0',
		'secureimage' => 'INT(1) DEFAULT 0',

		'dateline' => 'INT(10)',
	),
	'pp_image_sets' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'title' => 'TEXT',
		'description' => 'TEXT',
		'dateline' => 'INT(10)',
	),
	'pp_image_tasks' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'lid' => 'INT(10)',
		'pid' => 'INT(10)',
		'setid' => 'INT(10)',
		'title' => 'TEXT',
		'description' => 'TEXT',
		'addon' => 'TEXT NOT NULL',
		'settings' => 'TEXT',
		'task_order' => 'INT(10) DEFAULT 0',
		'dateline' => 'INT(10) NOT NULL',
	),
	'pp_image_task_lists' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'title' => 'TEXT',
		'description' => 'TEXT',
		'images' => 'TEXT',
		'active' => 'INT(1) DEFAULT 0',
		'dateline' => 'INT(10) NOT NULL',
	),
);

$settings = array(
	'pp_settings' => array(
		'group' => array(
			'name' => 'pp_settings',
			'title' => $lang->pp,
			'description' => $lang->pp_settingsgroup_description,
			'disporder' => '107',
			'isdefault' => 0,
		),
		'settings' => array(
			'pp_images_per_row' => array(
				'name' => 'pp_images_per_row',
				'title' => 'Images Per Row',
				'description' => 'In View Thread ACP page, enter the number of images per row. eg. 3 (default)',
				'optionscode' => 'text',
				'value' => '3',
				'disporder' => '10',
			),
			'pp_minify_js' => array(
				'name' => 'pp_minify_js',
				'title' => $lang->pp_minify_js_title,
				'description' => $lang->pp_minify_js_desc,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '20',
			),
		),
	),
);

$images = array(
	'folder' => 'pp',
	'acp' => array(
		'donate.gif' => array(
			'image' => <<<EOF
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
EOF
		),
		'pixel.gif' => array(
			'image' => <<<EOF
R0lGODlhAQABAID/AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==
EOF
		),
		'settings.gif' => array(
			'image' => <<<EOF
R0lGODlhEAAQAOMLAAAAAAMDAwYGBgoKCg0NDRoaGh0dHUlJSVhYWIeHh5aWlv///////////////////yH5BAEKAA8ALAAAAAAQABAAAARe8Mn5lKJ4nqRMOtmDPBvQAZ+IIQZgtoAxUodsEKcNSqXd2ahdwlWQWVgDV6JiaDYVi4VlSq1Gf87L0GVUsARK3tBm6LAAu4ktUC6yMueYgjubjHrzVJ2WKKdCFBYhEQA7
EOF
		),
		'manage.png' => array(
			'image' => <<<EOF
iVBORw0KGgoAAAANSUhEUgAAABAAAAAQBAMAAADt3eJSAAAAGFBMVEUAAAAlJSUmJiZERERGRkZNTU2Ojo7///9zy8a1AAAAAWJLR0QAiAUdSAAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB+ECBRcENABjRZEAAABLSURBVAjXY2AAAbPycga2tLS0ADCjvLy8AA9DFMwoAGuEMFTLAxhYQQy38gSIMIhhbCyg4gJiAM0NS0sDMoCagQagMYBSaWAGWCMAcOcmgAP/MAEAAAAASUVORK5CYII=
EOF
		),
		'bad-image.png' => array(
			'image' => <<<EOF
iVBORw0KGgoAAAANSUhEUgAAAJYAAACWCAYAAAA8AXHiAAAABmJLR0QARABzANJqWiAHAAAACXBIWXMAAC3UAAAt1AEYYcVpAAAAB3RJTUUH4wIQDyQMhd8twwAAABl0RVh0Q29tbWVudABDcmVhdGVkIHdpdGggR0lNUFeBDhcAAAGVSURBVHja7dqxDcMgAERRYrER0zIUMzl9Cld3ReT3Wuu6L5AsPnvvezw45zx9HmutYW//6xpQICyEhbAQFggLYfFm038a+8beiYWrEGEhLBAWwkJYEDb9p7Fv7J1YuAoRFsICYSEshAVh3mPZV/ZOLFyFCAthgbAQFsKCMO+x7Ct7JxauQoSFsEBYCAthQZj3WPaVvRMLVyHCQlggLISFsCDMeyz7yt6JhasQYSEsEBbCQlgQ5j2WfWXvxMJViLAQFggLYSEsCPMey76yd2LhKkRYCAuEhbAQFoR5j2Vf2TuxcBUiLIQFwkJYCAvCvMeyr+ydWLgKERbCAmEhLIQFYd5j2Vf2TixchQgLYYGwEBbCgjDvsewreycWrkKEhbBAWAgLYUGY91j2lb0TC1chwkJYICyEhbAgzHss+8reiYWrEGEhLBAWwkJYEOY9ln1l78TCVYiwEBYIC2EhLAjzHsu+sndi4SpEWAgLhIWwEBaEeY9lX9k7sXAVIiyEBcJCWAgLwrzHsq/snVi4ChEWwgJh8Se+5aYgOLxkeTMAAAAASUVORK5CYII=
EOF
		),
		'add.png' => array(
			'image' => <<<EOF
iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9wMGBAtOZp8lRIAAAEYSURBVDjLY1DaKM3EQCZQ2ijNxAhlMNzzf4oiuffx7v9Hnx5hYGBgYLCWtmFwlnVlRNOMqkdpozQjsubQHQH/lTZK/1faKP3fZYrD/4V75/9HUssNY8Odf8//6X+ljdJEOfue/9OvGAZADWFQ2ijNjEcz5z3/p/+QxViQnU3I9nqelm8MeyHseOdERrgBex/v/j/j6lS8mhdfX4gRyM6yrowsDAwMDEefHmE4+/M0Ts33ZG9jiMFiiOw0gBIG1tI2DIfOHMRps8gxCQw56xwbhAHOsq6MyPGMzc9BmiEofFjCgscCLFSVNkoz1PO0YI2RWYJTGNFTLBPe5InuJUg6wW4AIc1ohsCTPSWZif+e/9OPKKaRkZ1VAUVpe31b/eHeAAAAAElFTkSuQmCC
EOF
		),
	),
);

?>
