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
		'pp_images' => array(
			'id' => 'SERIAL',
			'setid' => 'INT',
			'pid' => 'INT NOT NULL',
			'tid' => 'INT NOT NULL',
			'url' => 'TEXT NOT NULL',
			'caption' => 'TEXT',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
		'pp_image_sets' => array(
			'id' => 'SERIAL',
			'title' => 'TEXT',
			'description' => 'TEXT',
			'dateline' => 'INT NOT NULL, PRIMARY KEY(id)',
		),
	),
	'pp_images' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'setid' => 'INT(10)',
		'pid' => 'INT(10) NOT NULL',
		'tid' => 'INT(10) NOT NULL',
		'url' => 'TEXT NOT NULL',
		'caption' => 'TEXT',
		'dateline' => 'INT(10)',
	),
	'pp_image_sets' => array(
		'id' => 'INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY',
		'title' => 'TEXT',
		'description' => 'TEXT',
		'dateline' => 'INT(10)',
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
			'pp_minify_js' => array(
				'name' => 'pp_minify_js',
				'title' => $lang->pp_minify_js_title,
				'description' => $lang->pp_minify_js_desc,
				'optionscode' => 'yesno',
				'value' => '0',
				'disporder' => '50'
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
	),
);

?>
