<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * this file contains the ACP functionality and depends upon install.php
 * for plugin info and installation routines
 */

// disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}
define('PICTURE_PERFECT_URL', 'index.php?module=config-pp');
require_once MYBB_ROOT . 'inc/plugins/pp/install.php';

/**
 * the ACP page router
 *
 * @return void
 */
$plugins->add_hook('admin_load', 'pp_admin');
function pp_admin()
{
	global $page;
	if ($page->active_action != 'pp') {
		// not our turn
		return false;
	}

	global $mybb, $lang, $html, $min, $modules;
	if (!$lang->pp) {
		$lang->load('pp');
	}

	if ($mybb->settings['pp_minify_js']) {
		$min = '.min';
	}

	// URL, link and image markup generator
	$html = new HTMLGenerator010000(PICTURE_PERFECT_URL, array('ajax'));

	$modules = ppGetAllModules();

	// if there is an existing function for the action
	$page_function = 'pp_admin_' . $mybb->input['action'];
	if (function_exists($page_function)) {
		// run it
		$page_function();
	} else {
		// default to the main page
		pp_admin_main();
	}
	// get out
	exit();
}

/**
 * main page
 *
 * @return void
 */
function pp_admin_main()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$page->add_breadcrumb_item($lang->pp_admin_main);

	// set up the page header
	$page->extra_header .= <<<EOF
	<script type="text/javascript" src="jscripts/pp/inline{$min}.js"></script>
	<script type="text/javascript">
	<!--
	PP.inline.setup({
		go: '{$lang->go}',
		noSelection: '{$lang->pp_inline_selection_error}',
	});
	// -->
	</script>

EOF;

	$page->output_header("{$lang->pp} - {$lang->pp_admin_main}");
	pp_output_tabs('pp');

	$query = $db->simple_select('pp_image_threads', 'COUNT(id) as resultCount');
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 10;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(), 'post');

	echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="inline_action">
			<option value="delete">Delete</option>
		</select>
		<input id="pp_inline_submit" type="submit" class="button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input id="pp_inline_clear" type="button" class="button" name="pp_inline_clear" value="{$lang->clear}"/>
		<input type="hidden" name="page" value="{$mybb->input['page']}" />
	</span>
</div>
<br />
EOF;

	$table = new Table;
	$table->construct_header($lang->pp_thread, array('width' => '80%'));
	$table->construct_header($lang->pp_image_count, array('width' => '15%'));
	$table->construct_header($form->generate_check_box('', '', '', array('id' => 'pp_select_all')), array('style' => 'width: 1%'));

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last YourCode on page)
	if (!isset($mybb->input['page']) ||
		$mybb->input['page'] == '' ||
		(int) $mybb->input['page'] < 1) {
		// no page, page = 1
		$mybb->input['page'] = 1;
	} else if ($mybb->input['page'] > $totalPages) {
		// past last page? page = last page
		$mybb->input['page'] = $totalPages;
	} else {
		// in range? page = # in link
		$mybb->input['page'] = (int) $mybb->input['page'];
	}

	// more than one page?
	$start = ($mybb->input['page'] - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url());
		echo($pagination . '<br />');
	}

	$limitSql = "LIMIT {$perPage}";
	if ($start > 0) {
		$limitSql = "LIMIT {$start}, {$perPage}";
	}

	$queryString = <<<EOF
		SELECT i.id, i.tid, i.image_count, t.subject
		FROM {$db->table_prefix}pp_image_threads i
		LEFT JOIN {$db->table_prefix}threads t ON(t.tid=i.tid)
		ORDER BY tid ASC
		{$limitSql}
EOF;

	$query = $db->write_query($queryString);

	if ($db->num_rows($query) > 0) {
		while ($thread = $db->fetch_array($query)) {
			$table->construct_cell($html->link($html->url(array('action' => 'view_thread', 'tid' => $thread['tid'])), $thread['subject']));
			$table->construct_cell($thread['image_count']);
			$table->construct_cell($form->generate_check_box("pp_inline_ids[{$thread['tid']}]", '', '', array('class' => 'pp_check')));
			$table->construct_row();

			$done++;
			if ($done >= $perPage) {
				break;
			}
		}
	} else {
		$table->construct_cell($lang->pp_no_image_threads, array('colspan' => 3));
		$table->construct_row();
	}

	$table->output($lang->pp_image_threads);
	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
		// if so show pagination on the right this time just to be weird
		echo($pagination);
	}

	$page->output_footer();
}

/**
 * view and manage image sets
 *
 * @return void
 */
function pp_admin_sets()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$page->add_breadcrumb_item($lang->pp_admin_sets);

	// set up the page header
	$page->extra_header .= <<<EOF
	<script type="text/javascript" src="jscripts/pp/inline{$min}.js"></script>
	<script type="text/javascript">
	<!--
	PP.inline.setup({
		go: '{$lang->go}',
		noSelection: '{$lang->pp_inline_selection_error}',
	});
	// -->
	</script>

EOF;

	if ($mybb->request_method == 'post') {
		if ($mybb->input['mode'] == 'inline') {
			// verify incoming POST request
			if (!verify_post_check($mybb->input['my_post_key'])) {
				flash_message($lang->invalid_post_verify_key2, 'error');
				admin_redirect($html->url(array('action' => 'sets', 'page' => $mybb->input['page'])));
			}

			if (!is_array($mybb->input['pp_inline_ids']) ||
				empty($mybb->input['pp_inline_ids'])) {
				flash_message($lang->pp_inline_selection_error, 'error');
				admin_redirect($html->url(array('action' => 'sets', 'page' => $mybb->input['page'])));
			}

			$job_count = 0;
			foreach ($mybb->input['pp_inline_ids'] as $id => $throw_away) {
				$imageSet = new PicturePerfectImageSet($id);
				if (!$imageSet->isValid()) {
					continue;
				}

				switch ($mybb->input['inline_action']) {
				case 'delete':
					$action = $lang->pp_deleted;
					if (!$imageSet->remove()) {
						continue 2;
					}
					break;
				}
				++$job_count;
			}
			flash_message($lang->sprintf($lang->pp_inline_success, $job_count, $lang->pp_image_sets, $action), 'success');
			admin_redirect($html->url(array('action' => 'sets', 'page' => $mybb->input['page'])));
		}
	}

	if ($mybb->input['mode'] == 'delete') {
		// verify incoming POST request
		if (!verify_post_check($mybb->input['my_post_key'])) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect($html->url(array('action' => 'sets')));
		}

		// good info?
		if (isset($mybb->input['id']) &&
			(int) $mybb->input['id']) {
			// then attempt deletion
			$imageSet = new PicturePerfectImageSet($mybb->input['id']);
			if ($imageSet->isValid()) {
				$success = $imageSet->remove();
			}
		}

		if ($success) {
			// yay for us
			flash_message($lang->sprintf($lang->pp_message_success, $lang->pp_image_sets, $lang->pp_deleted), 'success');
			yourcode_build_cache();
		} else {
			// boo, we suck
			flash_message($lang->sprintf($lang->pp_message_fail, $lang->pp_image_sets, $lang->pp_deleted), 'error');
		}
		admin_redirect($html->url(array('action' => 'sets')));
	}

	$page->output_header("{$lang->pp} - {$lang->pp_admin_sets}");
	pp_output_tabs('pp_sets');

	// get a total count on the YourCodes
	$query = $db->simple_select('pp_image_sets', 'COUNT(id) AS resultCount');
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 10;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array('action' => 'sets', 'mode' => 'inline', 'inline_action' => 'delete')), 'post');

	echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="inline_action">
			<option value="delete">Delete</option>
		</select>
		<input id="pp_inline_submit" type="submit" class="button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input id="pp_inline_clear" type="button" class="button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;

	$table = new Table;
	$table->construct_header($lang->pp_image_sets_title, array('width' => '35%'));
	$table->construct_header($lang->pp_image_sets_description, array('width' => '35%'));
	$table->construct_header($lang->pp_image_count, array('width' => '15%'));
	$table->construct_header($lang->pp_delete, array('width' => '10%'));
	$table->construct_header($form->generate_check_box('', '', '', array('id' => 'pp_select_all')), array('style' => 'width: 1%'));

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last YourCode on page)
	if (!isset($mybb->input['page']) ||
		$mybb->input['page'] == '' ||
		(int) $mybb->input['page'] < 1) {
		// no page, page = 1
		$mybb->input['page'] = 1;
	} else if ($mybb->input['page'] > $totalPages) {
		// past last page? page = last page
		$mybb->input['page'] = $totalPages;
	} else {
		// in range? page = # in link
		$mybb->input['page'] = (int) $mybb->input['page'];
	}

	// more than one page?
	$start = ($mybb->input['page'] - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'sets')));
		echo($pagination . '<br />');
	}

	$query = $db->simple_select('pp_image_sets', '*', '', array('order_by' => 'title ASC', 'limit_start' => $start, 'limit' => $perPage));
	while ($imageSet = $db->fetch_array($query)) {
		$imageSets[$imageSet['id']] = $imageSet;
	}

	if (!empty($imageSets)) {
		foreach ($imageSets as $id => $imageSet) {
			$query = $db->simple_select('pp_images', 'COUNT(id) as image_count', "setid={$id}");
			$imageCount = $db->fetch_field($query, 'image_count');

			$deleteUrl = $html->url(array('action' => 'sets', 'mode' => 'delete', 'id' => $id, 'my_post_key' => $mybb->post_code));
			$deleteLink = $html->link($deleteUrl, $lang->pp_delete);

			$table->construct_cell($html->link($html->url(array('action' => 'view_set', 'id' => $id)), $imageSet['title']));
			$table->construct_cell($imageSet['description']);
			$table->construct_cell($imageCount);
			$table->construct_cell($deleteLink);
			$table->construct_cell($form->generate_check_box("pp_inline_ids[{$id}]", '', '', array('class' => 'pp_check')));
			$table->construct_row();
		}
	} else {
		$table->construct_cell($lang->pp_no_image_sets, array('colspan' => 5));
		$table->construct_row();
	}

	$table->output($lang->pp_image_sets);
	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
		// if so show pagination on the right this time just to be weird
		echo($pagination);
	}

	$page->output_footer();
}

/**
 * view image threads
 *
 * @return void
 */
function pp_admin_view_thread()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$page->add_breadcrumb_item($lang->pp_admin_view_thread);

	$selected = $mybb->input['selected_ids'];
	$tid = (int) $mybb->input['tid'];
	$titleQuery = $db->simple_select('threads', 'subject', "tid={$tid}");
	$threadTitle = $db->fetch_field($titleQuery, 'subject');

	// set up the page header
	$page->extra_header .= <<<EOF
	<script type="text/javascript" src="jscripts/pp/inline{$min}.js"></script>
	<script type="text/javascript">
	<!--
	PP.inline.setup({
		go: '{$lang->go}',
		noSelection: '{$lang->pp_inline_selection_error}',
	});
	// -->
	</script>

	<style>
		.pp_select_all {
			float: right;
		}

		td.selectedImage {
			background: #0083ff !important;
		}

		td.ppImage {
			text-align: center;
		}

		td.emptyCell {
			border: none !important;
		}

		.thumbnail {
			width: 240px;
			cursor: pointer;
		}

		img.localImage {
			border: 4px solid green;
		}
	</style>

EOF;

	$page->output_header("{$lang->pp} - {$lang->pp_admin_view_thread}");
	pp_output_tabs('pp_view_thread', $threadTitle, $tid);

	// get a total count on the YourCodes
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', "tid={$tid} AND setid=0");
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array('action' => 'process_images', 'mode' => 'configure')), 'post');

	if (is_array($modules) &&
		!empty($modules)) {
		$options = '';
		foreach ($modules as $key => $module) {
			$options .= <<<EOF

			<option value="{$key}">{$module->get('actionPhrase')}</option>
EOF;
		}

		echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="addon">{$options}
		</select>
		<input id="pp_inline_submit" type="submit" class="button" name="pp_inline_submit" value="{$lang->go} ({$selectedCount})"/>
		<input id="pp_inline_clear" type="button" class="button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;
	}

	$table = new Table;

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last YourCode on page)
	if (!isset($mybb->input['page']) ||
		$mybb->input['page'] == '' ||
		(int) $mybb->input['page'] < 1) {
		// no page, page = 1
		$mybb->input['page'] = 1;
	} else if ($mybb->input['page'] > $totalPages) {
		// past last page? page = last page
		$mybb->input['page'] = $totalPages;
	} else {
		// in range? page = # in link
		$mybb->input['page'] = (int) $mybb->input['page'];
	}

	// more than one page?
	$start = ($mybb->input['page'] - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_thread', 'tid' => $tid)));
		echo($pagination . '<br />');
	}

	$query = $db->simple_select('pp_images', '*', "tid={$tid} AND setid=0", array('limit_start' => $start, 'limit' => $perPage));

	$count = 0;
	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	foreach ($images as $id => $image) {
		$imageClass = '';
		if (strpos($image['url'], $mybb->settings['bburl']) !== false) {
			$imageClass = ' localImage';
		}

		$imageElement = $html->img($image['url'], array('class' => "thumbnail{$imageClass}"));

		$table->construct_cell($form->generate_check_box("selected_ids[{$id}]", '', $imageElement, array('class' => 'pp_check')), array('class' => 'ppImage'), array('class' => 'ppImage'));

		$count++;
		if ($count == 4) {
			$count = 0;
			$table->construct_row();
		}
	}

	if ($count > 0 &&
		$count < 4) {
		while ($count < 4) {
			$table->construct_cell('', array('class' => 'emptyCell'));
			$count++;
		}
		$table->construct_row();
	}

	$checkbox = $form->generate_check_box('', '', '', array('id' => 'pp_select_all', 'class' => 'pp_select_all'));

	$table->output($lang->pp_images . $checkbox);

	foreach ((array) $selected as $id => $throwAway) {
		echo $form->generate_hidden_field("selected_ids[{$id}]", 1);
	}
	echo $form->generate_hidden_field('tid', $tid);

	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
		// if so show pagination on the right this time just to be weird
		echo($pagination);
	}

	$page->output_footer();
}

/**
 * edit image set details
 *
 * @return void
 */
function pp_admin_edit_set()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$page->add_breadcrumb_item($lang->pp_admin_edit_set);

	$page->output_header("{$lang->pp} - {$lang->pp_admin_edit_set}");
	pp_output_tabs('pp_edit_set');

	$data = array();
	$id = (int) $mybb->input['id'];
	$imageSet = new PicturePerfectImageSet($id);
	if ($imageSet->isValid()) {
		$data = $imageSet->get('data');
	}

	if ($mybb->request_method == 'post') {
		$imageSet->set($mybb->input);
		if (!$imageSet->save()) {
			flash_message($lang->pp_edit_set_fail_message, 'error');
			admin_redirect($html->url(array('action' => 'edit_set', 'id' => $id)));
		}

		flash_message($lang->pp_edit_set_success_message, 'success');
		admin_redirect($html->url(array('action' => 'view_set', 'id' => $id)));
	}

	$form = new Form($html->url(array('action' => 'edit_set', 'id' => $id)), 'post');

	$formContainer = new FormContainer($lang->pp_admin_edit_set);

	$formContainer->output_row($lang->pp_image_sets_title, $lang->pp_image_sets_title_description, $form->generate_text_box('title', $data['title'], array('id' => 'setting_title')), 'title', array('id' => 'setting_title'));

	$formContainer->output_row($lang->pp_image_sets_description, $lang->pp_image_sets_description_desc, $form->generate_text_box('description', $data['description'], array('id' => 'setting_description')), 'description', array('id' => 'setting_description'));

	$formContainer->end();
	$buttons[] = $form->generate_submit_button($lang->pp_edit_set_submit, array('name' => 'edit_set_submit'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * view and manage image sets
 *
 * @return void
 */
function pp_admin_view_set()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$page->add_breadcrumb_item($lang->pp_admin_view_set);

	// set up the page header
	$page->extra_header .= <<<EOF
	<script type="text/javascript" src="jscripts/pp/inline{$min}.js"></script>
	<script type="text/javascript">
	<!--
	PP.inline.setup({
		go: '{$lang->go}',
		noSelection: '{$lang->pp_inline_selection_error}',
	});
	// -->
	</script>

	<style>
		.pp_select_all {
			float: right;
		}

		td.selectedImage {
			background: #0083ff !important;
		}

		td.ppImage {
			text-align: center;
			width: 260px;
		}

		td.emptyCell {
			border: none !important;
		}

		.thumbnail {
			width: 240px;
			cursor: pointer;
		}

		img.localImage {
			border: 4px solid green;
		}
	</style>

EOF;

	$page->output_header("{$lang->pp} - {$lang->pp_admin_view_set}");
	pp_output_tabs('pp_view_set');

	$id = (int) $mybb->input['id'];
	$imageSet = new PicturePerfectImageSet($id);

	// get a total count on the YourCodes
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', "setid={$id}");
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array('action' => 'view_set', 'mode' => 'inline')), 'post');

	echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="inline_action">
			<option value="delete">Delete</option>
		</select>
		<input id="pp_inline_submit" type="submit" class="button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input id="pp_inline_clear" type="button" class="button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;

	$table = new Table;

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last YourCode on page)
	if (!isset($mybb->input['page']) ||
		$mybb->input['page'] == '' ||
		(int) $mybb->input['page'] < 1) {
		// no page, page = 1
		$mybb->input['page'] = 1;
	} else if ($mybb->input['page'] > $totalPages) {
		// past last page? page = last page
		$mybb->input['page'] = $totalPages;
	} else {
		// in range? page = # in link
		$mybb->input['page'] = (int) $mybb->input['page'];
	}

	// more than one page?
	$start = ($mybb->input['page'] - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_set', 'id' => $id)));
		echo($pagination . '<br />');
	}

	$query = $db->simple_select('pp_images', '*', "setid={$id}", array('limit_start' => $start, 'limit' => $perPage));
	$count = 0;
	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	$cacheBuster = '?dateline='.TIME_NOW;
	foreach ($images as $id => $image) {
		$imageClass = '';
		if (strpos($image['url'], $mybb->settings['bburl']) !== false) {
			$imageClass = ' localImage';
		}

		$imageElement = $html->img($image['url'].$cacheBuster, array('class' => "thumbnail{$imageClass}"));

		$table->construct_cell($form->generate_check_box("selected_ids[{$id}]", '', $imageElement, array('class' => 'pp_check')), array('class' => 'ppImage'), array('class' => 'ppImage'));

		$count++;
		if ($count == 4) {
			$count = 0;
			$table->construct_row();
		}
	}

	if ($count > 0 &&
		$count < 4) {
		while ($count < 4) {
			$table->construct_cell('', array('class' => 'emptyCell'));
			$count++;
		}
		$table->construct_row();
	}

	$checkbox = $form->generate_check_box('', '', '', array('id' => 'pp_select_all', 'class' => 'pp_select_all'));

	$table->output($lang->pp_image_set . " &mdash; {$imageSet->get('title')}" . $checkbox);
	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
		// if so show pagination on the right this time just to be weird
		echo($pagination);
	}

	$page->output_footer();
}

/**
 * perform image tasks
 *
 * @return void
 */
function pp_admin_process_images()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$selected = $mybb->input['selected_ids'];
	$selectedCount = count($selected);

	$tid = (int) $mybb->input['tid'];
	$redirectUrl = $html->url(array('action' => 'view_thread', 'tid' => $tid));

	if (!is_array($selected) ||
		empty($selected)) {
		flash_message('no images', 'error');
		admin_redirect($redirectUrl);
	}

	$module = new PicturePerfectModule($mybb->input['addon']);
	if (!$module->isValid()) {
		flash_message($lang->pp_process_images_fail_invalid_module, 'error');
		admin_redirect($redirectUrl);
	}

	if ($selectedCount > $module->get('imageLimit')) {
		flash_message($lang->sprintf($lang->pp_process_images_fail_exceed_module_limit, $module->get('imageLimit')), 'error');
		admin_redirect($redirectUrl);
	}

	$page->add_breadcrumb_item($lang->pp_admin_process_images);

	// set up the page header
	$page->extra_header .= <<<EOF
	<style>
		div.infoTitle {
			font-weight: bold;
			font-size: 1.5em;
			text-shadow: 1px 1px 2px grey;
		}

		div.infoDescription {
			font-style: italic;
		}
	</style>

EOF;

	if ($mybb->input['mode'] == 'finalize') {
		$extra = '';
		if ($mybb->input['setid'] == 'new') {
			$mybb->input['setid'] = 0;
			$extra = $lang->pp_process_images_finalize_success_extra;
		}

		$imageArray = array_keys($selected);
		$imageArray = array_map('intval', $imageArray);
		$imageList = implode(',', $imageArray);
		$query = $db->simple_select('pp_images', '*', "id IN({$imageList})");
		if ($db->num_rows($query) == 0) {
			flash_message($lang->pp_process_images_fail_no_images, 'error');
			admin_redirect($redirectUrl);
		}

		while ($image = $db->fetch_array($query)) {
			$images[$image['id']] = $image;
		}

		$settings = array();
		foreach ($module->get('settings') as $name => $setting) {
			$settings[$name] = $setting['value'];
			if (isset($mybb->input[$name])) {
				$settings[$name] = $mybb->input[$name];
			}
		}

		$info = $module->processImages($images, $settings);

		flash_message($info['message'], $info['status']);
		admin_redirect($html->url($info['redirect']));
	}

	$page->output_header("{$lang->pp} - {$lang->pp_admin_process_images}");
	pp_output_tabs('pp_process_images', $threadTitle, $tid);

	echo <<<EOF
	<div class="form_button_wrapper">
		<div class="infoTitle">
			<span>{$module->get('actionPhrase')}</span>
		</div>
		<div class="infoDescription">
			<span>ready to process {$selectedCount} image(s)</span>
		</div>
	</div>
	<br />
EOF;
	$form = new Form($html->url(array('action' => 'process_images', 'mode' => 'finalize')), 'post');
	$formContainer = new FormContainer($module->get('title') . ' Settings');

	if ($module->get('createsSet')) {
		$setArray = array('new' => 'new set');

		$query = $db->simple_select('pp_image_sets', 'id,title');
		while ($set = $db->fetch_array($query)) {
			$setArray[$set['id']] = $set['title'];
		}

		$formContainer->output_row($lang->pp_image_set, $lang->pp_image_set_desc, $form->generate_select_box('setid', $setArray, 'new', array('id' => 'setting_setid')), 'setid', array('id' => 'setting_setid'));
	}

	$module->outputSettings($formContainer);

	foreach ((array) $selected as $id => $throwAway) {
		echo $form->generate_hidden_field("selected_ids[{$id}]", 1);
	}
	echo $form->generate_hidden_field('addon', $mybb->input['addon']);

	$formContainer->end();
	$buttons[] = $form->generate_submit_button($lang->pp_process_submit, array('name' => 'process_submit'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * @param  array items on the config tab
 * @return void
 */
$plugins->add_hook('admin_config_action_handler', 'pp_admin_config_action_handler');
function pp_admin_config_action_handler(&$action)
{
	$action['pp'] = array('active' => 'pp');
}

/**
 * add an entry to the ACP Config page menu
 *
 * @param  array menu
 * @return void
 */
$plugins->add_hook('admin_config_menu', 'pp_admin_config_menu');
function pp_admin_config_menu(&$sub_menu)
{
	global $lang;
	if (!$lang->pp) {
		$lang->load('pp');
	}

	end($sub_menu);
	$key = (key($sub_menu)) + 10;
	$sub_menu[$key] = array(
		'id' => 'pp',
		'title' => $lang->pp,
		'link' => PICTURE_PERFECT_URL
	);
}

/**
 * add an entry to admin permissions list
 *
 * @param  array permission types
 * @return void
 */
$plugins->add_hook('admin_config_permissions', 'pp_admin_config_permissions');
function pp_admin_config_permissions(&$admin_permissions)
{
	global $lang;

	if (!$lang->pp) {
		$lang->load('pp');
	}
	$admin_permissions['pp'] = $lang->pp_admin_permissions_desc;
}

/**
 * perform image scan after standard installation
 *
 * @return void
 */
$plugins->add_hook('admin_config_plugins_activate_commit', 'pp_admin_config_plugins_activate_commit');
function pp_admin_config_plugins_activate_commit() {
	global $lang, $db;

	$query = $db->simple_select('pp_images', 'id');
	if ($db->num_rows($query) > 0) {
		return;
	}

	if (!$lang->pp) {
		$lang->load('pp');
	}

	$query = $db->simple_select('posts', 'pid');
	$count = (int) $db->num_rows($query);

	flash_message($lang->pp_finalizing_installation, 'success');
	admin_redirect("index.php?module=config-pp&action=scan&count={$count}");
}

/**
 * scan forum posts for images and store info
 *
 * @return void
 */
function pp_admin_scan()
{
	global $mybb, $page, $db, $lang, $cache, $min;
	if ($page->active_action != 'pp') {
		return false;
	}

	if (!$lang->pp) {
		$lang->load('pp');
	}

	$inProgress = (int) $mybb->input['in_progress'];
	$start = (int) $mybb->input['start'];
	$ppp = (int) $mybb->input['posts_per_page'];
	if ($ppp == 0) {
		$ppp = 10000;
	}
	$totalCount = (int) $mybb->input['count'];

	if ($mybb->request_method == 'post') {
		$threadCache = $cache->read('pp_thread_cache');

		if (!is_array($threadCache) ||
			empty($threadCache)) {
			$threadCache = array();
		}

		$done = false;

		$query = $db->simple_select('posts', 'pid, tid, message', '', array('limit' => $ppp, 'limit_start' => $start, 'order_by' => 'dateline', 'order_dir', 'ASC'));

		$count = $db->num_rows($query);
		if ($count == 0 ||
			$count < $ppp) {
			$done = true;
		}

		$insert_arrays = array();
		while ($post = $db->fetch_array($query)) {
			foreach ((array) ppGetPostImages($post['message']) as $source) {
				$threadCache[$post['tid']]++;

				$insert_arrays[] = array(
					'setid' => 0,
					'pid' => (int) $post['pid'],
					'tid' => (int) $post['tid'],
					'url' => $source,
					'dateline' => TIME_NOW,
				);
			}
		}

		if (!empty($insert_arrays)) {
			$db->insert_query_multiple('pp_images', $insert_arrays);
		}

		if ($done) {
			$insert_arrays = array();

			foreach ($threadCache as $tid => $count) {
				$insert_arrays[] = array(
					'tid' => (int) $tid,
					'image_count' => (int) $count,
					'dateline' => TIME_NOW,
				);
			}

			if (!empty($insert_arrays)) {
				$db->insert_query_multiple('pp_image_threads', $insert_arrays);
			}

			$cache->update('pp_thread_cache', null);

			flash_message($lang->pp_installation_finished, 'success');
			admin_redirect('index.php?module=config-plugins');
		}

		$start += $ppp;

		$cache->update('pp_thread_cache', $threadCache);
	}

	$page->add_breadcrumb_item($lang->pp_installation);

	if ($inProgress) {
		// set up the page header
		$page->extra_header .= <<<EOF
		<script src="../jscripts/pp/pp{$min}.js" type="text/javascript"></script>
		<script type="text/javascript">
			pp.setup("{$lang->pp_redirect_button_text}");
		</script>

EOF;
	}

	$page->output_header("{$lang->pp} - {$lang->pp_installation}");

	if ($inProgress) {
		$message = $lang->sprintf($lang->pp_installation_progress, $start, $totalCount);
		$info = <<<EOF
<div style="width: 100%; height: 40px; background: #f2f2f2; text-align: center; font-weight: bold;">
	<h1>{$message}</h1>
<div>
EOF;
		echo($info);
	}

	$form = new Form('index.php?module=config-pp', 'post');

	$form_container = new FormContainer($lang->pp_analyze_posted_images);
	$form_container->output_row_header($lang->name);
	$form_container->output_row_header($lang->pp_posts_per_page, array('width' => 50));
	$form_container->output_row_header('&nbsp;');

	$form_container->output_cell("<label>{$lang->pp_analyze_posted_images}</label><div class=\"description\">{$lang->pp_analyze_posted_images_description}</div>");
	$form_container->output_cell($form->generate_numeric_field('posts_per_page', $ppp, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'analyze_posts', 'class' => 'button_yes', 'id' => 'analyze_submit')) . $form->generate_hidden_field('start', $start) . $form->generate_hidden_field('count', $totalCount) . $form->generate_hidden_field('in_progress', 1) . $form->generate_hidden_field('action', 'scan'));
	$form_container->construct_row();

	$form_container->end();

	$form->end();

	$page->output_footer();
	exit;
}

/**
 * Output ACP tabs
 *
 * @param  string current tab
 * @return void
 */
function pp_output_tabs($current, $threadTitle='', $tid=0)
{
	global $page, $lang, $mybb, $html, $modules;

	$subTabs['pp'] = array(
		'title' => $lang->pp_image_threads,
		'link' => $html->url(),
		'description' => $lang->pp_admin_edit_set_desc,
	);

	$subTabs['pp_sets'] = array(
		'title' => $lang->pp_admin_sets,
		'link' => $html->url(array('action' => 'sets')),
		'description' => $lang->pp_admin_sets_desc,
	);

	switch ($current) {
	case 'pp_view_set':
		$subTabs['pp_view_set'] = array(
			'title' => $lang->pp_admin_view_set,
			'link' => $html->url(array('action' => 'view_set')),
			'description' => $lang->pp_admin_view_set_desc,
		);
		break;
	case 'pp_view_thread':
		$urlArray = array('action' => 'view_thread');
		if ($tid) {
			$urlArray['tid'] = $tid;
		}

		$subTabs['pp_view_thread'] = array(
			'title' => $lang->pp_view_thread,
			'link' => $html->url($urlArray),
			'description' => $lang->sprintf($lang->pp_view_thread_desc, $threadTitle),
		);
		break;
	case 'pp_process_images':
		$urlArray = array('action' => 'process_images');

		$subTabs['pp_process_images'] = array(
			'title' => $lang->pp_process_images,
			'link' => $html->url($urlArray),
			'description' => $lang->pp_process_images_desc,
		);
		break;
	case 'pp_edit_set':
		$urlArray = array('action' => 'edit_set');

		$subTabs['pp_edit_set'] = array(
			'title' => $lang->pp_admin_edit_set,
			'link' => $html->url($urlArray),
			'description' => $lang->pp_admin_edit_set_desc,
		);
		break;
	}

	$page->output_nav_tabs($subTabs, $current);
}

?>
