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
require_once MYBB_ROOT.'inc/plugins/pp/install.php';

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
	$html = new HTMLGenerator010000(PICTURE_PERFECT_URL, array('ajax', 'fid'));

	$modules = ppGetAllModules();

	// if there is an existing function for the action
	$page_function = 'pp_admin_'.$mybb->input['action'];
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

	$lang->load('forum_management');

	if (!isset($mybb->input['fid'])) {
		$mybb->input['fid'] = 0;
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if ($fid) {
		$forum = get_forum($fid);
	}

	$page->add_breadcrumb_item($lang->pp);

	// set up the page header
	$page->extra_header .= <<<EOF

EOF;

	$page->output_header("{$lang->pp} &mdash; View Forums");
	pp_output_tabs('pp');

	$table = new Table;
	$table->construct_header('Forum');
	$table->construct_header('Description');
	$table->construct_header('Image Count');
	$table->construct_header('Control');

	ppBuildForumList($table, $fid);

	$table->output('Forums');

	$page->output_footer();
}

/**
 * view threads with images
 *
 * @return void
 */
function pp_admin_view_threads()
{
	global $mybb, $db, $page, $lang, $html, $min;

	if (!isset($mybb->input['fid'])) {
		$mybb->input['fid'] = 0;
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if ($fid) {
		$forum = get_forum($fid);
	}

	$forumName = htmlspecialchars_uni($forum['name']);

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("View Image Threads in {$forumName}");

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

	$page->output_header("{$lang->pp} - {$lang->pp_admin_main} - {$forum['name']}");
	pp_output_tabs('pp_view_threads');

	$query = $db->simple_select('pp_image_threads', 'COUNT(id) as resultCount', "fid='{$fid}'");
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
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
		<input type="hidden" name="page" value="{$mybb->input['page']}" />
	</span>
</div>
<br />
EOF;

	$table = new Table;
	$table->construct_header($lang->pp_thread, array('width' => '50%'));
	$table->construct_header('Thread Link', array('width' => '20%'));
	$table->construct_header($lang->pp_image_count, array('width' => '15%'));
	$table->construct_header('Controls', array('width' => '10%'));
	$table->construct_header($form->generate_check_box('', '', '', array('id' => 'pp_select_all')), array('style' => 'width: 1%'));

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_threads', 'fid' => $fid)));
		echo($pagination.'<br />');
	}

	$limitSql = "LIMIT {$perPage}";
	if ($start > 0) {
		$limitSql = "LIMIT {$start}, {$perPage}";
	}

	$queryString = <<<EOF
		SELECT i.id, i.tid, i.image_count, t.subject
		FROM {$db->table_prefix}pp_image_threads i
		LEFT JOIN {$db->table_prefix}threads t ON(t.tid=i.tid)
		WHERE i.fid='{$fid}'
		ORDER BY tid ASC
		{$limitSql}
EOF;

	$query = $db->write_query($queryString);

	if ($db->num_rows($query) > 0) {
		while ($thread = $db->fetch_array($query)) {
			$table->construct_cell($html->link($html->url(array('action' => 'view_thread', 'tid' => $thread['tid'])), $thread['subject']));

			$threadUrl = '../'.get_thread_link($thread['tid']);
			$threadLink = $html->link($threadUrl, "#{$thread['tid']}", array('target' => '_blank'));
			$table->construct_cell($threadLink);

			$table->construct_cell($thread['image_count']);

			$popup = new PopupMenu("control_{$thread['tid']}", 'Options');
			$popup->add_item('Scan', $html->url(array('action' => 'scan_center', 'mode' => 'inline', 'tid' => $thread['tid'])));
			$table->construct_cell($popup->fetch());

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
 * view image threads
 *
 * @return void
 */
function pp_admin_view_thread()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$selected = $mybb->input['selected_ids'];
	$tid = (int) $mybb->input['tid'];
	$titleQuery = $db->simple_select('threads', 'subject', "tid={$tid}");
	$threadTitle = $db->fetch_field($titleQuery, 'subject');

	$shortTitle = $threadTitle;
	if (my_strlen($shortTitle) > 35) {
		$shortTitle = my_substr($threadTitle, 0, 32).'...';
	}

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("{$lang->pp_view_thread} #{$tid} ({$shortTitle})");

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

	// get a total count on the items
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', "tid={$tid} AND setid=0");
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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

	$form = new Form($html->url(array('action' => 'process_images', 'mode' => 'configure', 'page' => $mybb->input['page'])), 'post');

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
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} ({$selectedCount})"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;
	}

	$taskOptions = '';
	$query = $db->simple_select('pp_image_task_lists', '*', 'active=1');
	if ($db->num_rows($query)) {
		while ($taskList = $db->fetch_array($query)) {
			$taskOptions .= <<<EOF

			<option value="{$taskList['id']}">{$taskList['title']}</option>
EOF;
		}

		echo <<<EOF
<div>
	<span>
		<strong>Add to task list:</strong>&nbsp;
		<select name="tasklist">{$taskOptions}
		</select>
		<input type="submit" class="pp_inline_submit button" name="pp_task_submit" value="{$lang->go} ({$selectedCount})"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;
	}

	$table = new Table;

	// more than one page?
	$start = ($mybb->input['page'] - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_thread', 'tid' => $tid)));
		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_images', '*', "tid={$tid} AND setid=0", array('limit_start' => $start, 'limit' => $perPage));

	$count = 0;
	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	$baseDomain = ppGetBaseDomain($mybb->settings['bburl']);
	if (!$baseDomain) {
		$baseDomain = $mybb->settings['bburl'];
	}

	foreach ($images as $id => $image) {
		$imageClass = '';
		if (strpos($image['url'], $baseDomain) !== false) {
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

	$table->output($lang->pp_images.$checkbox);

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
		} else {
			// boo, we suck
			flash_message($lang->sprintf($lang->pp_message_fail, $lang->pp_image_sets, $lang->pp_deleted), 'error');
		}
		admin_redirect($html->url(array('action' => 'sets')));
	}

	$page->output_header("{$lang->pp} - {$lang->pp_admin_sets}");
	pp_output_tabs('pp_sets');

	// get a total count on the items
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
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
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

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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
		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_image_sets', '*', '', array('order_by' => 'title ASC', 'limit_start' => $start, 'limit' => $perPage));
	while ($imageSet = $db->fetch_array($query)) {
		$imageSets[$imageSet['id']] = $imageSet;
	}

	if (!empty($imageSets)) {
		foreach ($imageSets as $id => $imageSet) {
			$query = $db->simple_select('pp_images', 'COUNT(id) as image_count', "setid={$id}");
			$imageCount = $db->fetch_field($query, 'image_count');

			$editUrl = $html->url(array('action' => 'edit_set', 'id' => $id, 'my_post_key' => $mybb->post_code));

			$deleteUrl = $html->url(array('action' => 'sets', 'mode' => 'delete', 'id' => $id, 'my_post_key' => $mybb->post_code));

			$table->construct_cell($html->link($html->url(array('action' => 'view_set', 'id' => $id)), $imageSet['title']));
			$table->construct_cell($imageSet['description']);
			$table->construct_cell($imageCount);

			$popup = new PopupMenu("control_{$id}", 'Options');
			$popup->add_item('Edit', $editUrl);
			$popup->add_item($lang->pp_delete, $deleteUrl);
			$table->construct_cell($popup->fetch());

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
 * edit image set details
 *
 * @return void
 */
function pp_admin_edit_set()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$data = array();
	$id = (int) $mybb->input['id'];
	$imageSet = new PicturePerfectImageSet($id);
	if ($imageSet->isValid()) {
		$data = $imageSet->get('data');
	}

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("{$lang->pp_admin_edit_set} #{$id} ({$imageSet->get('title')})");

	$page->output_header("{$lang->pp} - {$lang->pp_admin_edit_set}");
	pp_output_tabs('pp_edit_set');

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

	$id = (int) $mybb->input['id'];
	$imageSet = new PicturePerfectImageSet($id);

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("{$lang->pp_admin_view_set} #{$id} ({$imageSet->get('title')})");

	$page->output_header("{$lang->pp} - {$lang->pp_admin_view_set}");
	pp_output_tabs('pp_view_set');

	// get a total count on the items
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
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
EOF;

	$table = new Table;

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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
		echo($pagination.'<br />');
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

	$table->output($lang->pp_image_set." &mdash; {$imageSet->get('title')}".$checkbox);
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
 * view and manage image tasks
 *
 * @return void
 */
function pp_admin_image_tasks()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$page->add_breadcrumb_item('Image Tasks');

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
	</style>

EOF;

	$page->output_header("{$lang->pp} - Image Tasks");
	pp_output_tabs('pp_image_tasks');

	// get a total count on the items
	$query = $db->simple_select('pp_image_tasks', 'COUNT(id) AS resultCount', "lid=0");
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array('action' => 'view_tasks', 'mode' => 'inline')), 'post');

	echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="inline_action">
			<option value="delete">Delete</option>
		</select>
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
<a href="index.php?module=config-pp&amp;action=" title="add new image task"></a>
<br />
EOF;

	$newTaskUrl = $html->url(array('action' => 'edit_image_task'));
	$newTaskLink = $html->link($newTaskUrl, 'Add a new image task', array('style' => 'font-weight: bold;', 'title' => 'Add a new image task', 'icon' => "styles/{$cp_style}/images/asb/add.png"), array('alt' => '+', 'style' => 'margin-bottom: -3px;', 'title' => 'Add a new image task'));
	echo($newTaskLink.'<br /><br />');

	$table = new Table;
	$table->construct_header('Title', array('width' => '35%'));
	$table->construct_header('Description', array('width' => '35%'));
	$table->construct_header('Module', array('width' => '15%'));
	$table->construct_header('Order', array('width' => '10%'));
	$table->construct_header($form->generate_check_box('', '', '', array('id' => 'pp_select_all')), array('style' => 'width: 1%'));

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_tasks', 'id' => $id)));
		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_image_tasks', '*', "lid=0", array('limit_start' => $start, 'limit' => $perPage));
	$count = 0;
	$tasks = array();
	while ($task = $db->fetch_array($query)) {
		$tasks[$task['id']] = $task;
	}

	foreach ($tasks as $id => $task) {
		$addonName = $modules[$task['addon']]->get('title');

		$editUrl = $html->url(array('action' => 'edit_image_task', 'id' => $id));
		$editLink = $html->link($editUrl, $task['title']);

		$table->construct_cell($editLink);
		$table->construct_cell($task['description']);
		$table->construct_cell($addonName);
		$table->construct_cell($task['task_order']);
		$table->construct_cell($form->generate_check_box("selected_ids[{$id}]", '', $imageElement, array('class' => 'pp_check')));

		$table->construct_row();
	}

	if (empty($tasks)) {
		$table->construct_cell('nothing yet', array('colspan' => 5));
		$table->construct_row();
	}

	$checkbox = $form->generate_check_box('', '', '', array('id' => 'pp_select_all', 'class' => 'pp_select_all'));

	$table->output("View Tasks &mdash; {$task['title']}".$checkbox);
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
 * edit image task details
 *
 * @return void
 */
function pp_admin_edit_image_task()
{
	global $mybb, $db, $page, $lang, $html, $min, $modules;

	$data = array();
	$id = (int) $mybb->input['id'];
	$task = new PicturePerfectImageTask($id);
	if ($task->isValid()) {
		$data = $task->get('data');
	}

	if ($mybb->request_method == 'post') {
		$module = $modules[$mybb->input['addon']];
		if ($mybb->input['mode'] == 'configure') {
			$page->add_breadcrumb_item('Configure Image Task');

			$page->output_header("{$lang->pp} - Configure Image Task");
			pp_output_tabs('pp_configure_image_task');

			$form = new Form($html->url(array('action' => 'edit_image_task')), 'post');
			$formContainer = new FormContainer($module->get('title').' Settings');

			$module->outputSettings($formContainer);

			echo($form->generate_hidden_field('id', $id).$form->generate_hidden_field('addon', $mybb->input['addon']).$form->generate_hidden_field('title', $mybb->input['title']).$form->generate_hidden_field('description', $mybb->input['description']).$form->generate_hidden_field('task_order', $mybb->input['task_order']));

			$formContainer->end();
			$buttons[] = $form->generate_submit_button('Save Task', array('name' => 'process_submit'));
			$form->output_submit_wrapper($buttons);
			$form->end();

			$page->output_footer();
		}

		$task->set($mybb->input);

		$settings = array();
		foreach ($module->get('settings') as $name => $setting) {
			$settings[$name] = $setting['value'];
			if (isset($mybb->input[$name])) {
				$settings[$name] = $mybb->input[$name];
			}
		}

		$task->set('settings', $settings);

		if (!$task->save()) {
			flash_message('fail', 'error');
			admin_redirect($html->url(array('action' => 'edit_image_task', 'mode' => 'configure')));
		}

		flash_message('success', 'success');
		admin_redirect($html->url(array('action' => 'image_tasks', 'id' => $id)));
	}

	if (is_array($modules) &&
		!empty($modules)) {
		$options = array();
		foreach ($modules as $key => $module) {
			$options[$key] = $module->get('actionPhrase');
		}
	}

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("Edit Image Task #{$id} ({$task->get('title')})");

	$page->output_header("{$lang->pp} - Edit Image Task");
	pp_output_tabs('pp_edit_image_task');

	$form = new Form($html->url(array('action' => 'edit_image_task', 'mode' => 'configure')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Title', '', $form->generate_text_box('title', $data['title']));
	$formContainer->output_row('Description', '', $form->generate_text_box('description', $data['description']));
	$formContainer->output_row('Module', '', $form->generate_select_box('addon', $options));
	$formContainer->output_row('Order', '', $form->generate_text_box('task_order', $data['task_order']).$form->generate_hidden_field('id', $id).$form->generate_hidden_field('pid', 0));

	$formContainer->end();
	$buttons[] = $form->generate_submit_button('Configure Module and Save', array('name' => 'edit_image_task_submit'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * view and manage image task lists
 *
 * @return void
 */
function pp_admin_image_task_lists()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$page->add_breadcrumb_item('Image Task Lists');

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
	</style>

EOF;

	$page->output_header("{$lang->pp} - Image Task Lists");
	pp_output_tabs('pp_image_task_lists');

	// get a total count on the items
	$query = $db->simple_select('pp_image_task_lists', 'COUNT(id) AS resultCount');
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array('action' => 'view_task_lists', 'mode' => 'inline')), 'post');

	echo <<<EOF
<div>
	<span>
		<strong>{$lang->pp_inline_title}:</strong>&nbsp;
		<select name="inline_action">
			<option value="delete">Delete</option>
		</select>
		<input type="submit" class="pp_inline_submit button" name="pp_inline_submit" value="{$lang->go} (0)"/>
		<input type="button" class="pp_inline_clear button" name="pp_inline_clear" value="{$lang->clear}"/>
	</span>
</div>
<br />
<a href="index.php?module=config-pp&amp;action=" title="add new image task"></a>
<br />
EOF;

	$newTaskUrl = $html->url(array('action' => 'edit_image_task_list'));
	$newTaskLink = $html->link($newTaskUrl, 'Add a new image task list', array('style' => 'font-weight: bold;', 'title' => 'Add a new image task list', 'icon' => "styles/{$cp_style}/images/asb/add.png"), array('alt' => '+', 'style' => 'margin-bottom: -3px;', 'title' => 'Add a new image task list'));
	echo($newTaskLink.'<br /><br />');

	$table = new Table;
	$table->construct_header('Title', array('width' => '25%'));
	$table->construct_header('Description', array('width' => '25%'));
	$table->construct_header('Images', array('width' => '5%'));
	$table->construct_header('Image Set', array('width' => '15%'));
	$table->construct_header('Location', array('width' => '15%'));
	$table->construct_header('Status', array('width' => '10%'));
	$table->construct_header($form->generate_check_box('', '', '', array('id' => 'pp_select_all')), array('style' => 'width: 1%'));

	// adjust the page number if the user has entered manually or is returning to a page that no longer exists (deleted last item on page)
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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array('action' => 'view_task_lists', 'id' => $id)));
		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_image_sets', '*');
	$imageSets = array();
	while ($imageSet = $db->fetch_array($query)) {
		$imageSets[$imageSet['id']] = $imageSet['title'];
	}

	$query = $db->simple_select('pp_image_task_lists', '*', '', array('limit_start' => $start, 'limit' => $perPage));
	$count = 0;
	$taskLists = array();
	while ($taskList = $db->fetch_array($query)) {
		$taskLists[$taskList['id']] = $taskList;
	}

	foreach ($taskLists as $id => $taskList) {
		if (!empty($taskList['images'])) {
			$imageArray = explode(',', $taskList['images']);
			$imageCount = count($imageArray);
		} else {
			$taskList['images'] = array();
			$imageCount = 0;
		}

		$imageSetTitle = $imageSets[$taskList['setid']];
		$taskListStatus = $taskList['active'] ? 'Active' : 'Inactive';

		$editUrl = $html->url(array('action' => 'edit_image_task_list', 'id' => $id));
		$editLink = $html->link($editUrl, $taskList['title']);

		$table->construct_cell($editLink);
		$table->construct_cell($taskList['description']);
		$table->construct_cell($imageCount);
		$table->construct_cell($imageSetTitle);
		$table->construct_cell($taskList['destination']);
		$table->construct_cell($taskListStatus);
		$table->construct_cell($form->generate_check_box('', '', '', array('id' => 'pp_select_all', 'class' => 'pp_select_all')));

		$table->construct_row();
	}

	if (empty($taskLists)) {
		$table->construct_cell('nothing yet', array('colspan' => 7));
		$table->construct_row();
	}

	$table->output("View Task Lists");
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
 * edit image task list details
 *
 * @return void
 */
function pp_admin_edit_image_task_list()
{
	global $mybb, $db, $page, $lang, $html, $min, $modules;

	$data = array();
	$id = (int) $mybb->input['id'];
	$taskList = new PicturePerfectImageTaskList($id);
	if ($taskList->isValid()) {
		$data = $taskList->get('data');

		$data['active'] = $data['active'] ? '1' : '0';
	} else {
		$data['active'] = '0';
	}

	if ($mybb->request_method == 'post') {
		if ($mybb->input['setid'] == 'new') {
			$imageSet = new PicturePerfectImageSet(array(
				'title' => "{$mybb->input['title']} (set)",
				'description' => "image set for {$mybb->input['title']}",
			));

			$mybb->input['setid'] = $imageSet->save();
			if (!$mybb->input['setid']) {
				flash_message('could not save image set', 'error');
				admin_redirect($html->url(array('action' => 'image_task_lists')));
			}
		} else {
			$imageSet = new PicturePerfectImageSet($mybb->input['setid']);

			if (!$imageSet->isValid()) {
				flash_message('Invalid image set', 'error');
				admin_redirect($html->url(array('action' => 'image_task_lists')));
			}
		}

		if (empty($mybb->input['tasks'])) {
			flash_message('No tasks selected', 'error');
			admin_redirect($html->url(array('action' => 'image_task_lists')));
		}

		$mybb->input['tasks'] = implode(',', $mybb->input['tasks']);

		$taskList->set($mybb->input);

		if (!$id) {
			$newTasks = explode(',', $mybb->input['tasks']);
			$id = $taskList->save();

			foreach ((array) $newTasks as $taskId) {
				$thisTask = new PicturePerfectImageTask($taskId);
				$thisTask->set('id', 0);
				$thisTask->set('lid', $id);
				$thisTask->set('pid', $taskId);
				$thisTask->save();
			}
		} else {
			$taskList->save();
		}

		if (!$id) {
			flash_message('fail', 'error');
			admin_redirect($html->url(array('action' => 'edit_image_task_list')));
		}

		flash_message('success', 'success');
		admin_redirect($html->url(array('action' => 'edit_image_task_list', 'id' => $id)));
	}

	$selected = array();
	if ($id) {
		$query = $db->simple_select('pp_image_tasks', '*', "lid='{$id}' AND pid !=0");
		if ($db->num_rows($query)) {
			while ($task = $db->fetch_array($query)) {
				$selected[] = $task['pid'];
			}
		}
	}

	$options = array();
	$query = $db->simple_select('pp_image_tasks', '*', "lid=0 AND pid=0");
	if ($db->num_rows($query)) {
		while ($task = $db->fetch_array($query)) {
			$options[$task['id']] = $task['title'];
		}
	}

	$sets = array('new' => 'new set');
	$query = $db->simple_select('pp_image_sets', '*');
	if ($db->num_rows($query)) {
		while ($set = $db->fetch_array($query)) {
			$sets[$set['id']] = $set['title'];
		}
	}

	$page->add_breadcrumb_item($lang->pp);
	$page->add_breadcrumb_item("Edit Image Task List #{$id} ({$taskList->get('title')})");

	$page->output_header("{$lang->pp} - Edit Image Task List");
	pp_output_tabs('pp_edit_image_task_list');

	$form = new Form($html->url(array('action' => 'edit_image_task_list')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Title', '', $form->generate_text_box('title', $data['title']));
	$formContainer->output_row('Description', '', $form->generate_text_box('description', $data['description']));
	$formContainer->output_row('Task(s)', '', $form->generate_select_box('tasks[]', $options, $selected, array('multiple' => true)));
	$formContainer->output_row('Image Set', '', $form->generate_select_box('setid', $sets, $data['setid']));
	$formContainer->output_row('Destination Path', '', $form->generate_text_box('destination', $data['destination']).$form->generate_hidden_field('id', $id));
	$formContainer->output_row('Active?', '', $form->generate_yes_no_radio('active', $data['active']));

	$formContainer->end();
	$buttons[] = $form->generate_submit_button('Save Image Task List', array('name' => 'edit_image_task_list_submit'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * scan images selectively
 *
 * @return void
 */
function pp_admin_scan_center()
{
	global $mybb, $db, $page, $lang, $html, $min, $modules;

	$tid = (int) $mybb->input['tid'];
	$fid = (int) $mybb->input['fid'];

	if ($mybb->request_method == 'post' ||
		$mybb->input['mode'] == 'inline') {
		if ($tid) {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id', "tid='{$tid}'");

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this thread!', 'error');
					admin_redirect($html->url(array('action' => 'scan_center', 'mode' => 'confirm_overwrite', 'tid' => $tid, 'fid' => $fid)));
				}
			}

			$message = "Scan Thread #{$mybb->input['tid']}";
		} elseif ($fid) {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id', "fid='{$fid}'");

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this forum!', 'error');
					admin_redirect($html->url(array('action' => 'scan_center', 'mode' => 'confirm_overwrite', 'tid' => $tid, 'fid' => $fid)));
				}
			}

			$message = "Scan Forum #{$mybb->input['fid']}";
		} else {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id');

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this site!', 'error');
					admin_redirect($html->url(array('action' => 'scan_center', 'mode' => 'confirm_overwrite')));
				}
			}

			$message = "Scan Entire Forum";
		}

		$newOnly = (isset($mybb->input['start_new_scan']));
		$deleteFirst = (isset($mybb->input['start_delete_and_scan']));

		ppInitiateImageScan($message, $mybb->input['fid'], $mybb->input['tid'], $newOnly, $deleteFirst);
	}

	if ($mybb->input['mode'] == 'confirm_overwrite') {
		$page->add_breadcrumb_item('Confirm Image Overwrite');

		$page->output_header("{$lang->pp} - Image Scan Center");
		pp_output_tabs('pp_confirm_overwrite');

		echo <<<EOF
<div style="width: 60%; border-radius: 6px; border: 1px solid gray; background: pink; text-align: center; padding: 20px; margin: 20px auto;">
	<span style="font-size: 1.4em">The thread or folder you have chosen to be scanned already has existing images. Scanning again may produce duplicate records unless you choose to only look for new images.</span>
</div>
EOF;

		$form = new Form($html->url(array('action' => 'scan_center', 'mode' => 'override')), 'post');

		echo($form->generate_hidden_field('tid', $tid));
		echo($form->generate_hidden_field('fid', $fid));

		$buttons[] = $form->generate_submit_button('Scan All Images (not recommended)', array('name' => 'start_full_scan'));
		$buttons[] = $form->generate_submit_button('Scan Only New Images', array('name' => 'start_new_scan'));
		$buttons[] = $form->generate_submit_button('Delete Existing Images And Start Over', array('name' => 'start_delete_and_scan'));
		$form->output_submit_wrapper($buttons);
		$form->end();

		$page->output_footer();
		exit;
	}

	$page->add_breadcrumb_item('Image Scan Center');

	$page->output_header("{$lang->pp} - Image Scan Center");
	pp_output_tabs('pp_scan_center');

	$form = new Form($html->url(array('action' => 'scan_center')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Thread ID', '', $form->generate_text_box('tid'));
	$formContainer->output_row('Forum ID', '', $form->generate_text_box('fid'));

	$formContainer->end();

	$buttons[] = $form->generate_submit_button('Start Scan', array('name' => 'start_image_scan'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * merge new images into an existing task list
 *
 * @return void
 */
function ppAddImagesToTaskList()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$selected = $mybb->input['selected_ids'];
	$selectedCount = count($selected);

	$tid = (int) $mybb->input['tid'];
	$redirectUrl = $html->url(array('action' => 'view_thread', 'tid' => $tid, 'page' => $mybb->input['page']));

	if (!is_array($selected) ||
		empty($selected)) {
		flash_message('no images', 'error');
		admin_redirect($redirectUrl);
	}

	if (!$mybb->input['tasklist']) {
		flash_message('bad task list', 'error');
		admin_redirect($redirectUrl);
	}

	$taskList = new PicturePerfectImageTaskList($mybb->input['tasklist']);

	if (!$taskList->isValid()) {
		flash_message('invalid image task list', 'error');
		admin_redirect($redirectUrl);
	}

	$currentImages = $taskList->get('images');
	if (empty($currentImages)) {
		$currentArray = array();
	} else {
		$currentArray = explode(',', $currentImages);
	}

	$mergedArray = array_merge($currentArray, array_keys($selected));
	$mergedImages = implode(',', $mergedArray);
	$taskList->set('images', $mergedImages);

	if (!$taskList->save()) {
		flash_message('could not save task list', 'error');
		admin_redirect($redirectUrl);
	}

	flash_message('saved task list okay', 'success');
	admin_redirect($redirectUrl);
}

/**
 * perform image tasks
 *
 * @return void
 */
function pp_admin_process_images()
{
	global $mybb, $db, $page, $lang, $html, $min;

	if (isset($mybb->input['pp_task_submit'])) {
		ppAddImagesToTaskList();
	}

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

		foreach ((array) $info['messages'] as $m) {
			flash_message($m['message'], $m['status']);
		}

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
	$formContainer = new FormContainer($module->get('title').' Settings');

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
	echo $form->generate_hidden_field('page', $mybb->input['page']);

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

	ppInitiateImageScan($lang->pp_finalizing_installation);
}

function ppInitiateImageScan($message, $fid=0, $tid=0, $newOnly=false, $deleteFirst=false)
{
	global $db;

	$fid = (int) $fid;
	$tid = (int) $tid;

	$where = $queryExtra = '';
	if ($tid) {
		$queryExtra = "&tid={$tid}";
		$where = "tid='{$tid}'";
	} elseif($fid) {
		$queryExtra = "&fid={$fid}";
		$where = "fid='{$fid}'";
	}

	if ($deleteFirst) {
		$db->delete_query('pp_images', $where);
		$db->delete_query('pp_image_threads', $where);
	} elseif ($newOnly) {
		$query = $db->simple_select('pp_images', 'pid', $where, array('order_by' => 'pid', 'order_dir' => 'DESC', 'limit' => 1));

		$lastPid = (int) $db->fetch_field($query, 'pid');
		$where .= " AND pid > {$lastPid}";

		$queryExtra .= "&lastpid={$lastPid}";
	}

	$query = $db->simple_select('posts', 'COUNT(pid) as resultCount', $where);
	$count = (int) $db->fetch_field($query, 'resultCount');

	flash_message($message, 'success');
	admin_redirect("index.php?module=config-pp&action=scan&count={$count}{$queryExtra}");
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

	if (isset($mybb->input['do_not_analyze_posts'])) {
		$warning = '<br /><br />Skipping the image scan means that you will not see any images in the ACP interface. You will need to scan images from your forum\'s post before you can begin to use the plugin image processing modules.';
		flash_message($lang->pp_installation_finished.$warning, 'success');
		admin_redirect('index.php?module=config-plugins');
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

	if (!$totalCount) {
		flash_message('No images to scan.', 'error');
		admin_redirect('index.php?module=config-pp&action=scan_center');
	}

	$fid = (int) $mybb->input['fid'];
	$tid = (int) $mybb->input['tid'];
	$lastPid = (int) $mybb->input['lastpid'];


	if ($mybb->request_method == 'post') {
		$threadCache = $cache->read('pp_thread_cache');

		if (!is_array($threadCache) ||
			empty($threadCache)) {
			$threadCache = array();
		}

		$done = false;

		$operator = $where = '';
		if ($tid) {
			$where = "tid='{$tid}'";
			$operator = ' AND ';
		} elseif ($fid) {
			$where = "fid='{$fid}'";
			$operator = ' AND ';
		}

		if ($lastPid) {
			$where .= "{$operator}pid > {$lastPid}";
		}

		$query = $db->simple_select('posts', 'pid, tid, fid, message', $where, array('limit' => $ppp, 'limit_start' => $start, 'order_by' => 'dateline', 'order_dir', 'ASC'));

		$count = $db->num_rows($query);
		if ($count == 0 ||
			$count < $ppp) {
			$done = true;
		}

		$insert_arrays = array();
		while ($post = $db->fetch_array($query)) {
			foreach ((array) ppGetPostImages($post['message']) as $source) {
				$threadCache["{$post['fid']}-{$post['tid']}"]++;

				$insert_arrays[] = array(
					'setid' => 0,
					'pid' => (int) $post['pid'],
					'tid' => (int) $post['tid'],
					'fid' => (int) $post['fid'],
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

			foreach ($threadCache as $key => $count) {
				$keyPieces = explode('-', $key);
				list($forumId, $threadId) = $keyPieces;

				$insert_arrays[] = array(
					'tid' => (int) $threadId,
					'fid' => (int) $forumId,
					'image_count' => (int) $count,
					'dateline' => TIME_NOW,
				);
			}

			if (!empty($insert_arrays)) {
				$db->insert_query_multiple('pp_image_threads', $insert_arrays);
			}

			$cache->update('pp_thread_cache', null);

			$message = $lang->pp_installation_finished;
			$redirect = 'index.php?module=config-plugins';
			if ($tid) {
				$message = 'Thread scanned successfully.';
				$redirect = "index.php?module=config-pp&action=view_thread&tid={$tid}";
			} elseif ($fid) {
				$message = 'Forum scanned successfully.';
				$redirect = 'index.php?module=config-pp';
			}

			flash_message($message, 'success');
			admin_redirect($redirect);
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

	$message = $lang->sprintf($lang->pp_installation_progress, $start, $totalCount);
	$info = <<<EOF
<div style="width: 100%; height: 40px; background: #f2f2f2; text-align: center; font-weight: bold;">
<h1>{$message}</h1>
<div>
EOF;
	echo($info);

	$form = new Form('index.php?module=config-pp', 'post');

	$form_container = new FormContainer($lang->pp_analyze_posted_images);
	$form_container->output_row_header('Task', array('width' => '60%'));
	$form_container->output_row_header($lang->pp_posts_per_page, array('width' => '20%'));
	$form_container->output_row_header('&nbsp;', array('width' => '5%'));
	$form_container->output_row_header('&nbsp;', array('width' => '10%'));

	$form_container->output_cell("<label>{$lang->pp_analyze_posted_images}</label><div class=\"description\">{$lang->pp_analyze_posted_images_description}</div>");
	$form_container->output_cell($form->generate_numeric_field('posts_per_page', $ppp, array('style' => 'width: 150px;', 'min' => 0)));
	$form_container->output_cell($form->generate_submit_button($lang->go, array('name' => 'analyze_posts', 'class' => 'button_yes', 'id' => 'analyze_submit')));
	$form_container->output_cell($form->generate_submit_button('Skip for now...', array('name' => 'do_not_analyze_posts', 'class' => 'button_no', 'id' => 'skip_submit')).$form->generate_hidden_field('start', $start).$form->generate_hidden_field('count', $totalCount).$form->generate_hidden_field('in_progress', 1).$form->generate_hidden_field('action', 'scan').$form->generate_hidden_field('fid', $fid).$form->generate_hidden_field('tid', $tid).$form->generate_hidden_field('lastpid', $lastPid));
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
		'title' => 'Forums',
		'link' => $html->url(),
		'description' => 'a forum list for navigation',
	);

	switch ($current) {
	case 'pp_view_set':
		$subTabs['pp_view_set'] = array(
			'title' => $lang->pp_admin_view_set,
			'link' => $html->url(array('action' => 'view_set')),
			'description' => $lang->pp_admin_view_set_desc,
		);
		break;
	case 'pp_view_threads':
		$subTabs['pp_view_threads'] = array(
			'title' => $lang->pp_image_threads,
			'link' => $html->url(array('action' => 'view_threads')),
			'description' => 'view all threads in the forum that contain images',
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
	case 'pp_edit_image_task':
		$urlArray = array('action' => 'edit_image_task');

		$subTabs['pp_edit_image_task'] = array(
			'title' => 'Edit Image Task',
			'link' => $html->url($urlArray),
			'description' => 'edit image task details',
		);
		break;
	case 'pp_edit_image_task_list':
		$urlArray = array('action' => 'edit_image_task_list');

		$subTabs['pp_edit_image_task_list'] = array(
			'title' => 'Edit Image Task List',
			'link' => $html->url($urlArray),
			'description' => 'edit image task list details',
		);
		break;
	case 'pp_configure_image_task':
		$urlArray = array('action' => 'edit_image_task', 'mode' => 'configure');

		$subTabs['pp_configure_image_task'] = array(
			'title' => 'Configure Image Task',
			'link' => $html->url($urlArray),
			'description' => 'configure image task details',
		);
		break;
	case 'pp_confirm_overwrite':
		$urlArray = array('action' => 'scan_center', 'mode' => 'confirm_overwrite');

		$subTabs['pp_confirm_overwrite'] = array(
			'title' => 'Confirm Image Overwrite',
			'link' => $html->url($urlArray),
			'description' => 'The thread or forum being scanned already has existing images. You are being asked to confirm whether to delete the existing images and rescan, or to only look for new images.',
		);
		break;
	}

	$subTabs['pp_sets'] = array(
		'title' => $lang->pp_admin_sets,
		'link' => $html->url(array('action' => 'sets')),
		'description' => $lang->pp_admin_sets_desc,
	);

	$subTabs['pp_image_tasks'] = array(
		'title' => 'Image Tasks',
		'link' => $html->url(array('action' => 'image_tasks')),
		'description' => 'view and manage image tasks',
	);

	$subTabs['pp_image_task_lists'] = array(
		'title' => 'Image Task Lists',
		'link' => $html->url(array('action' => 'image_task_lists')),
		'description' => 'view and manage image tasks',
	);

	$subTabs['pp_scan_center'] = array(
		'title' => 'Scan Center',
		'link' => $html->url(array('action' => 'scan_center')),
		'description' => 'scan the forum for images',
	);

	$page->output_nav_tabs($subTabs, $current);
}

/**
 * simplified rewrite of build_admincp_forums_list to use tables
 *
 * @param  DefaultTable
 * @param  int
 * @param  int
 * @return void
 */
function ppBuildForumList(&$table, $pid=0, $depth=1)
{
	global $mybb, $lang, $db, $sub_forums, $html;
	static $allForums;

	// load the forum cache if necessary
	if (!is_array($allForums)) {
		$forumCache = cache_forums();

		foreach ($forumCache as $forum) {
			$allForums[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if (!is_array($allForums[$pid])) {
		return;
	}

	foreach ($allForums[$pid] as $children) {
		foreach ($children as $forum) {
			$forum['name'] = preg_replace("#&(?!\#[0-9]+;)#si", '&amp;', $forum['name']);

			if ($forum['active'] == 0) {
				$forum['name'] = "<em>{$forum['name']}</em>";
			}

			// category
			if ($forum['type'] == 'c') {
				$table->construct_cell("<div style=\"padding-left: ".(10*($depth-1))."px;\"><strong>{$forum['name']}</strong></div>");
				$table->construct_cell("<span style=\"font-style: italic;\">{$forum['description']}</span>");
				$table->construct_cell('');
				$table->construct_cell('');
				$table->construct_row();

				// Does this category have any sub forums?
				if ($allForums[$forum['fid']]) {
					ppBuildForumList($table, $forum['fid'], $depth+1);
				}
			// forum
			} elseif ($forum['type'] == 'f') {
				$query = $db->simple_select('pp_images', 'COUNT(id) as images', "fid='{$forum['fid']}'");
				$imageCount = (int) $db->fetch_field($query, 'images');

				$imageCountStyle = 'font-weight: bold; color: blue;';
				$forumNameStyle = '';
				$forumLink = "<a href=\"index.php?module=config-pp&amp;action=view_threads&amp;fid={$forum['fid']}\" style=\"font-weight: bold;\">{$forum['name']}</a>";
				if (!$imageCount) {
					$forumNameStyle = 'color: gray; font-style: italic; ';
					$forumLink = $forum['name'];
					$imageCountStyle = 'color: gray;';
				}

				$table->construct_cell("<div style=\"{$forumNameStyle}padding-left: ".(10*($depth-1))."px;\">{$forumLink}</div>");
				$table->construct_cell("<span style=\"font-style: italic;\">{$forum['description']}</span>");
				$table->construct_cell("<span style=\"{$imageCountStyle}\">{$imageCount}</span>");

				$popup = new PopupMenu("control_{$forum['fid']}", 'Options');
				$popup->add_item('Scan', $html->url(array('action' => 'scan_center', 'mode' => 'inline', 'fid' => $forum['fid'])));
				$table->construct_cell($popup->fetch());

				$table->construct_row();

				if (isset($allForums[$forum['fid']])) {
					ppBuildForumList($table, $forum['fid'], $depth+1);
				}
			}
		}
	}
}

/**
 * extract just the domain from a URL
 *
 * @param  string
 * @return bool|string
 */
function ppGetBaseDomain($url)
{
	$pieces = explode('.', $url);

	if (count($pieces) < 2) {
		return false;
	}

	$basePieces = array_slice($pieces, -2);
	$base = implode('.', $basePieces);

	return $base;
}

?>
