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

define('PP_SEARCH_YES', 1);
define('PP_SEARCH_NO', 2);
define('PP_SEARCH_EITHER', 3);

define('PP_SEARCH_URL_STARTS_WITH', 1);
define('PP_SEARCH_URL_CONTAINS', 2);
define('PP_SEARCH_URL_ENDS_WITH', 3);

require_once MYBB_ROOT.'inc/plugins/pp/install.php';
require_once MYBB_ROOT.'inc/plugins/pp/functions_acp.php';

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

	global $mybb, $lang, $html, $min, $modules, $hosts;
	if (!$lang->pp) {
		$lang->load('pp');
	}

	if ($mybb->settings['pp_minify_js']) {
		$min = '.min';
	}

	// URL, link and image markup generator
	$html = new HTMLGenerator010001(PICTURE_PERFECT_URL, array('ajax', 'fid', 'pid', 'addon', 'pp_inline_ids', 'host', 'task', 'from', 'fromid'));

	$modules = ppGetAllModules();
	$hosts = ppGetAllHosts();

	// if there is an existing function for the action
	$pageFunction = 'pp_admin_'.$mybb->input['action'];
	if (function_exists($pageFunction)) {
		// run it
		$pageFunction();
	} else {
		// default to the main page
		pp_admin_forums();
	}

	// get out
	exit();
}

	/** page functions **/

/**
 * main page
 *
 * @return void
 */
function pp_admin_forums()
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

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('View Forums');

	// set up the page header
	$page->extra_header .= <<<EOF
<style>
div.pp-forum-jump-form {
	display: inline;
	float: right;
	padding-right: 16px;
}

div.pp-forum-jump-form input[type=text] {
	width: 200px;
}
</style>
EOF;

	$page->output_header("{$lang->pp} &mdash; View Forums");
	pp_output_tabs('pp');

	$table = new Table;
	$table->construct_header('Forum');
	$table->construct_header('Description');
	$table->construct_header('Image Count');
	$table->construct_header('Control');

	ppBuildForumList($table, $fid);

	$jumpForm = <<<EOF
<div class="pp-forum-jump-form">
	<form id="forum-jump">
		<input type="hidden" name="module" value="config-pp" />
		<input type="hidden" name="action" value="parse_url" />
		<input type="hidden" name="from[action]" value="forums" />
		<input type="text" name="url" value="" placeholder="Enter TID or link here..." />&nbsp;
		<input type="submit" value="Go" />
	</form>
</div>
EOF;

	$table->output('Forums'.$jumpForm);

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

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	<style>
	div.pp-forum-jump-form {
		display: inline;
		float: right;
		padding-right: 16px;
	}

	div.pp-forum-jump-form input[type=text] {
		width: 200px;
	}
	</style>
EOF;

	$page->output_header("{$lang->pp} &mdash; {$forum['name']}");
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
		SELECT i.id, i.tid, i.image_count, t.subject, t.fid
		FROM {$db->table_prefix}pp_image_threads i
		LEFT JOIN {$db->table_prefix}threads t ON(t.tid=i.tid)
		WHERE i.fid='{$fid}'
		ORDER BY tid ASC
		{$limitSql}
EOF;

	$query = $db->write_query($queryString);

	if ($db->num_rows($query) > 0) {
		while ($thread = $db->fetch_array($query)) {
			$table->construct_cell($html->link($html->url(array(
				'action' => 'view_thread',
				'tid' => $thread['tid'],
				'fid' => $thread['fid'],
			)), $thread['subject']));

			$threadUrl = '../'.get_thread_link($thread['tid']);
			$threadLink = $html->link($threadUrl, "#{$thread['tid']}", array('target' => '_blank'));
			$table->construct_cell($threadLink);

			$table->construct_cell($thread['image_count']);

			$popup = new PopupMenu("control_{$thread['tid']}", 'Options');
			$popup->add_item(
				'Scan',
				$html->url(array('action' => 'scan_center', 'mode' => 'inline', 'tid' => $thread['tid']))
			);

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

	$jumpForm = <<<EOF
<div class="pp-forum-jump-form">
	<form id="forum-jump">
		<input type="hidden" name="module" value="config-pp" />
		<input type="hidden" name="action" value="parse_url" />
		<input type="hidden" name="from[action]" value="view_threads" />
		<input type="hidden" name="from[fid]" value="{$fid}" />
		<input type="hidden" name="from[page]" value="{$mybb->input['page']}" />
		<input type="text" name="url" value="" placeholder="Enter TID or link here..." />&nbsp;
		<input type="submit" value="Go" />
	</form>
</div>
EOF;

	$table->output($lang->pp_image_threads.$jumpForm);
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
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules, $hosts;

	$perPage = 12;
	$selected = $mybb->input['pp_inline_ids'];

	$tid = (int) $mybb->input['tid'];

	if (!$tid) {
		flash_message('invalid thread', 'error');
		admin_redirect($html->url());
	}

	$pid = (int) $mybb->input['pid'];

	if ($pid) {
		if (!$tid) {
			$post = get_post($pid);
			$tid = (int) $post['tid'];
		}

		$query = $db->simple_select('pp_images', 'count(id) as count', "tid={$tid} AND setid=0 AND pid < '{$pid}'", array(
			'order_by' => 'pid',
			'order_dir' => 'ASC, id ASC',
		));

		$count = $db->fetch_field($query, 'count');

		$mybb->input['page'] = (int)($count / $perPage) + 1;
	}

	$titleQuery = $db->simple_select('threads', 'subject', "tid={$tid}");
	$threadTitle = $db->fetch_field($titleQuery, 'subject');

	$shortTitle = $threadTitle;
	if (my_strlen($shortTitle) > 35) {
		$shortTitle = my_substr($threadTitle, 0, 32).'...';
	}

	if (!isset($mybb->input['fid'])) {
		$mybb->input['fid'] = 0;
	}

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	if ($fid) {
		$forum = get_forum($fid);
		$forumName = htmlspecialchars_uni($forum['name']);
		$page->add_breadcrumb_item("Threads in {$forumName}", $html->url(array('action' => 'view_threads', 'fid' => $fid)));
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item("{$lang->pp_view_thread} #{$tid} ({$shortTitle})");

	// set up the page header
	$page->extra_header .= <<<EOF
	<link rel="stylesheet" href="styles/{$cp_style}/pp/view_images.css" />

	<script type="text/javascript" src="jscripts/pp/pp.js"></script>
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

	$page->output_header("{$lang->pp} - {$lang->pp_admin_view_thread}");
	pp_output_tabs('pp_view_thread', $threadTitle, $tid);

	// get a total count on the items
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', "tid={$tid} AND setid=0");
	$resultCount = $db->fetch_field($query, 'resultCount');

	if (!$resultCount) {
		flash_message('no images found', 'error');
		admin_redirect($html->url(array('action' => 'image_search')));
	}

	$totalPages = ceil($resultCount / $perPage);
	$currentPage = min($totalPages, max(1, (int) $mybb->input['page']));

	echo(ppBuildJumpMenu($tid, $currentPage));

	$form = new Form($html->url(array(
		'action' => 'process_images',
		'mode' => 'configure',
		'from' => 'view_thread',
		'fromid' => $tid,
	)), 'post');

	$taskSelect = ppBuildTaskSelector();
	$taskListSelect = ppBuildTaskListSelector();
	$selectAllCheck = <<<EOF
<span><label id="select-all-label" for="pp_select_all">Select all images: <input type="checkbox" name="" value="" class="checkbox_input" id="pp_select_all" /></label></span>
EOF;

	$ipr = 4;
	if ($mybb->settings['pp_images_per_row']) {
		$ipr = (int) $mybb->settings['pp_images_per_row'];
	}

	$table = new Table;
	$table->construct_header($taskSelect.$taskListSelect.$selectAllCheck, array('width' => '20%', 'colspan' => $ipr));

	// more than one page?
	$start = ($currentPage - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($currentPage, $perPage, $resultCount, $html->url(array(
			'action' => 'view_thread',
			'tid' => $tid,
		)));

		echo($pagination);
	} else {
		echo('<br />');
	}

	echo('<br />');

	$query = $db->simple_select('pp_images', '*', "tid={$tid} AND setid=0", array(
		'limit_start' => $start,
		'limit' => $perPage,
		'order_by' => 'pid',
		'order_dir' => 'ASC, id ASC'
	));

	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	$postNumbers = ppGetPostNumbersForThread($tid);

	$urlInfo = parse_url($mybb->settings['bburl']);
	$baseDomain = str_replace('www.', '', $urlInfo['host']);
	if (!$baseDomain) {
		$baseDomain = $mybb->settings['bburl'];
	}

	$iCount = 0;
	foreach ($images as $id => $image) {
		$imageElement = ppBuildImageCard($image, $baseDomain, $currentPage, $tid, 'view_thread', $postNumbers[$pid]);

		$table->construct_cell($imageElement);

		if ($iCount > ($ipr - 2)) {
			$table->construct_row();
			$iCount = 0;
		} else {
			$iCount++;
		}
	}

	$blankElement = <<<EOF
<div class="blankElement"></div>
EOF;

	$extra = count($images) % $ipr;
	if ($extra > 0) {
		for ($x=0; $x < ($ipr - $extra); $x++) {
			$table->construct_cell($blankElement);
		}

		$table->construct_row();
	}

	$table->output(htmlspecialchars_uni("Images in \"{$threadTitle}\""));

	foreach ((array) $selected as $id => $throwAway) {
		echo $form->generate_hidden_field("pp_inline_ids[{$id}]", 1);
	}

	echo(
		$form->generate_hidden_field('tid', $tid).
		$form->generate_hidden_field('fid', $fid).
		$form->generate_hidden_field('page', $currentPage)
	);

	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
		echo($pagination);
	}

	$page->output_footer();
}

/**
 * view image threads
 *
 * @return void
 */
function pp_admin_image_search()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules, $hosts;

	if ($mybb->request_method == 'post') {
		$data = $mybb->input;

		if (empty($data) ||
			!is_array($data)) {
			flash_message('no data sent', 'error');
			admin_redirect($html->url(array('action' => 'image_search')));
		}

		$is = new PicturePerfectImageSearch($data);
		if (!$is->isValid()) {
			flash_message('invalid data sent', 'error');
			admin_redirect($html->url(array('action' => 'image_search')));
		}

		$id = $is->Save();
		if (!$id) {
			flash_message('could not store search criteria', 'error');
			admin_redirect($html->url(array('action' => 'image_search')));
		}

		$is->set('title', 'Image Search #'.$id);
		$is->buildDescription();
		$is->save();

		$where = $is->buildSqlWhere();

		// get a total count on the items
		$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', $where);
		$resultCount = $db->fetch_field($query, 'resultCount');

		if (!$resultCount) {
			flash_message('no images found', 'error');
			admin_redirect($html->url(array('action' => 'image_search')));
		}

		flash_message("Found {$resultCount} image(s)", 'success');
		admin_redirect($html->url(array('action' => 'search_results', 'id' => $id)));
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item($lang->pp_image_search);

	// set up the page header
	$page->extra_header .= <<<EOF

<style>
label[class^=label_] {
	display: inline-block;

	width: 160px;

	padding: 9px 7px;
	border: 2px solid;
	border-radius: 3px;

	color: black;
}

label.label_yes {
	background-color: #4bcb4b;
	border-color: #169d16;
}

label.label_no {
	background-color: #efa5b4;
	border-color: #a58585;
}

label.label_either {
	background-color: lightgrey;
	border-color: #b0b0b0;
}
</style>

EOF;

	$page->output_header("{$lang->pp} - {$lang->pp_admin_image_search}");
	pp_output_tabs('pp_image_search');

	$form = new Form($html->url(array('action' => 'image_search')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row(
		'URL Comparison',
		'find images by their URL',
		$form->generate_radio_button('url_comparison_method', '1', 'URL Starts With...', array('checked' => 0, 'class' => 'either'))
		.'&nbsp;'
		.$form->generate_radio_button('url_comparison_method', '2', 'URL Contains...', array('checked' => 1, 'class' => 'either'))
		.'&nbsp;'
		.$form->generate_radio_button('url_comparison_method', '3', 'URL Ends With...', array('checked' => 0, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_url_comparison_method')
	);

	$formContainer->output_row('Image URL:', 'enter a URL to find all matches', $form->generate_text_box('url'));

	$formContainer->output_row(
		'Status',
		'find images by their status',
		$form->generate_radio_button('status', '1', 'Good Images', array('checked' => 0, 'class' => 'yes'))
		.'&nbsp;'
		.$form->generate_radio_button('status', '2', 'Dead Images', array('checked' => 0, 'class' => 'no'))
		.'&nbsp;'
		.$form->generate_radio_button('status', '3', 'Either', array('checked' => 1, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_status')
	);

	$formContainer->output_row(
		'Security Status',
		'find images by their security status',
		$form->generate_radio_button('security_status', '1', 'HTTPS', array('checked' => 0, 'class' => 'yes'))
		.'&nbsp;'
		.$form->generate_radio_button('security_status', '2', 'HTTP', array('checked' => 0, 'class' => 'no'))
		.'&nbsp;'
		.$form->generate_radio_button('security_status', '3', 'Either', array('checked' => 1, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_security_status')
	);

	$formContainer->output_row(
		'Color Average Status',
		'find images by their color average information',
		$form->generate_radio_button('color_average_status', '1', 'Averaged', array('checked' => 0, 'class' => 'yes'))
		.'&nbsp;'
		.$form->generate_radio_button('color_average_status', '2', 'Not Averaged', array('checked' => 0, 'class' => 'no'))
		.'&nbsp;'
		.$form->generate_radio_button('color_average_status', '3', 'Either', array('checked' => 1, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_color_average_status')
	);

	$formContainer->output_row(
		'Check Status',
		'find images by their whether they have been checked or not',
		$form->generate_radio_button('check_status', '1', 'Checked', array('checked' => 0, 'class' => 'yes'))
		.'&nbsp;'
		.$form->generate_radio_button('check_status', '2', 'Unchecked', array('checked' => 0, 'class' => 'no'))
		.'&nbsp;'
		.$form->generate_radio_button('check_status', '3', 'Either', array('checked' => 1, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_check_status')
	);

	$formContainer->output_row(
		'Caption Status',
		'find images by their whether they have a caption or not',
		$form->generate_radio_button('caption_status', '1', 'Captioned', array('checked' => 0, 'class' => 'yes'))
		.'&nbsp;'
		.$form->generate_radio_button('caption_status', '2', 'No Caption', array('checked' => 0, 'class' => 'no'))
		.'&nbsp;'
		.$form->generate_radio_button('caption_status', '3', 'Either', array('checked' => 1, 'class' => 'either')),
		'',
		array(),
		array('id' => 'row_caption_status')
	);

	$formContainer->end();

	$buttons[] = $form->generate_submit_button('Search', array('name' => 'start_search'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$query = $db->simple_select('pp_image_searches', '*', '', array(
		'order_by' => 'dateline',
		'order_dir' => 'DESC',
		'limit' => 5,
	));

	$searchCount = $db->num_rows($query);
	if ($searchCount) {
		echo('<br />');

		$table = new Table;
		$table->construct_header('Title', array('width' => '30%'));
		$table->construct_header('Description', array('width' => '40%'));
		$table->construct_header('Time', array('width' => '20%'));
		$table->construct_header('Controls', array('width' => '10%'));

		while ($search = $db->fetch_array($query)) {
			$table->construct_cell($html->link($html->url(array(
				'action' => 'search_results',
				'id' => $search['id'],
			)), $search['title']));

			$table->construct_cell($search['description']);

			$table->construct_cell(my_date('relative', $search['dateline']));

			$popup = new PopupMenu("control_{$search['id']}", 'Options');
			$popup->add_item(
				'Save',
				$html->url(array('action' => 'save_search', 'id' => $search['id']))
			);

			$table->construct_cell($popup->fetch());

			$table->construct_row();
		}

		$table->output('Recent Searches');
	}

	$page->output_footer();
}

/**
 * view image threads
 *
 * @return void
 */
function pp_admin_search_results()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules, $hosts;

	$id = $mybb->input['id'];

	if (!$id) {
		flash_message('invalid search', 'error');
		admin_redirect($html->url(array('action' => 'image_search')));
	}

	$is = new PicturePerfectImageSearch($id);
	if (!$is->isValid()) {
		flash_message('invalid search', 'error');
		admin_redirect($html->url(array('action' => 'image_search')));
	}

	$checkedSearch = $is->get('check_status') == PP_SEARCH_NO ? 'true' : 'false';

	$perPage = 12;
	$selected = $mybb->input['pp_inline_ids'];

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item($lang->pp_search_results);

	// set up the page header
	$page->extra_header .= <<<EOF
	<link rel="stylesheet" href="styles/{$cp_style}/pp/view_images.css" />

	<script type="text/javascript" src="jscripts/pp/pp.js"></script>
	<script type="text/javascript">
	<!--
	PP.setup({
		checkedSearch: {$checkedSearch},
	});
	// -->
	</script>
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

	$page->output_header("{$lang->pp} - {$lang->pp_admin_search_results}");
	pp_output_tabs('pp_search_results');

	$data = $is->get('data');
	$where = $is->buildSqlWhere($data);

	// get a total count on the items
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', $where);
	$resultCount = $db->fetch_field($query, 'resultCount');

	if (!$resultCount) {
		flash_message('no images found', 'error');
		admin_redirect($html->url(array('action' => 'image_search')));
	}

	$totalPages = ceil($resultCount / $perPage);
	$currentPage = min($totalPages, max(1, (int) $mybb->input['page']));

	echo(ppBuildJumpMenu($tid, $currentPage));

	$form = new Form($html->url(array(
		'action' => 'process_images',
		'mode' => 'configure',
		'page' => $currentPage,
		'from' => 'search_results',
		'fromid' => $id,
	)), 'post');

	$taskSelect = ppBuildTaskSelector();
	$taskListSelect = ppBuildTaskListSelector();
	$selectAllCheck = <<<EOF
<span><label id="select-all-label" for="pp_select_all">Select all images: <input type="checkbox" name="" value="" class="checkbox_input" id="pp_select_all" /></label></span>
EOF;

	$ipr = 4;
	if ($mybb->settings['pp_images_per_row']) {
		$ipr = (int) $mybb->settings['pp_images_per_row'];
	}

	$table = new Table;
	$table->construct_header($selectAllCheck.$taskSelect.$taskListSelect, array('width' => '20%', 'colspan' => $ipr));

	// more than one page?
	$start = ($currentPage - 1) * $perPage;
	if ($resultCount > $perPage) {
		// save the pagination for below and show it here as well
		$pagination = draw_admin_pagination($currentPage, $perPage, $resultCount, $html->url(array(
			'action' => 'search_results',
			'id' => $id,
		)));

		echo($pagination);
	} else {
		echo('<br />');
	}

	echo('<br />');

	$query = $db->simple_select('pp_images', '*', $where, array(
		'limit_start' => $start,
		'limit' => $perPage,
		'order_by' => 'pid',
		'order_dir' => 'ASC, id ASC'
	));

	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	$urlInfo = parse_url($mybb->settings['bburl']);
	$baseDomain = str_replace('www.', '', $urlInfo['host']);
	if (!$baseDomain) {
		$baseDomain = $mybb->settings['bburl'];
	}

	$iCount = 0;
	foreach ($images as $iid => $image) {
		$imageElement = ppBuildImageCard($image, $baseDomain, $currentPage, $id, 'search_results');

		$table->construct_cell($imageElement);

		if ($iCount > ($ipr - 2)) {
			$table->construct_row();
			$iCount = 0;
		} else {
			$iCount++;
		}
	}

	$blankElement = <<<EOF
<div class="blankElement"></div>
EOF;

	$extra = count($images) % $ipr;
	if ($extra > 0) {
		for ($x=0; $x < ($ipr - $extra); $x++) {
			$table->construct_cell($blankElement);
		}

		$table->construct_row();
	}

	$addAllToTaskListUrl = $html->url(array('action' => 'add_results_to_task_list', 'id' => $id));
	$addAllToTaskListLink = $html->link($addAllToTaskListUrl, 'Add All To Task List');
	$table->output($addAllToTaskListLink);

	foreach ((array) $selected as $id => $throwAway) {
		echo $form->generate_hidden_field("pp_inline_ids[{$id}]", 1);
	}

	echo(
		$form->generate_hidden_field('tid', $tid).
		$form->generate_hidden_field('fid', $fid).
		$form->generate_hidden_field('page', $currentPage)
	);

	$form->end();
	echo('<br />');

	// more than one page?
	if ($resultCount > $perPage) {
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

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	$redirectUrl = $html->url(array(
		'action' => 'sets',
		'page' => $mybb->input['page'],
	));

	if ($mybb->request_method == 'post') {
		if ($mybb->input['mode'] == 'inline') {
			// verify incoming POST request
			if (!verify_post_check($mybb->input['my_post_key'])) {
				flash_message($lang->invalid_post_verify_key2, 'error');
				admin_redirect($redirectUrl);
			}

			if (!is_array($mybb->input['pp_inline_ids']) ||
				empty($mybb->input['pp_inline_ids'])) {
				flash_message($lang->pp_inline_selection_error, 'error');
				admin_redirect($redirectUrl);
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
			admin_redirect($redirectUrl);
		}
	}

	if ($mybb->input['mode'] == 'delete') {
		// verify incoming POST request
		if (!verify_post_check($mybb->input['my_post_key'])) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect($redirectUrl);
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
		admin_redirect($redirectUrl);
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

	$query = $db->simple_select('pp_image_sets', '*', '', array(
		'order_by' => 'title ASC',
		'limit_start' => $start,
		'limit' => $perPage,
	));

	while ($imageSet = $db->fetch_array($query)) {
		$imageSets[$imageSet['id']] = $imageSet;
	}

	if (!empty($imageSets)) {
		foreach ($imageSets as $id => $imageSet) {
			$query = $db->simple_select('pp_images', 'COUNT(id) as image_count', "setid={$id}");
			$imageCount = $db->fetch_field($query, 'image_count');

			$editUrl = $html->url(array(
				'action' => 'edit_set',
				'id' => $id,
				'my_post_key' => $mybb->post_code,
			));

			$deleteUrl = $html->url(array(
				'action' => 'sets',
				'mode' => 'delete',
				'id' => $id,
				'my_post_key' => $mybb->post_code,
			));

			$table->construct_cell($html->link($html->url(array(
				'action' => 'view_set',
				'id' => $id,
			)), $imageSet['title']));

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

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item($lang->pp_admin_sets, $html->url(array('action' => 'sets')));
	$page->add_breadcrumb_item("{$lang->pp_admin_edit_set} #{$id} ({$imageSet->get('title')})");

	$page->output_header("{$lang->pp} - {$lang->pp_admin_edit_set}");
	pp_output_tabs('pp_edit_set');

	if ($mybb->request_method == 'post') {
		$imageSet->set($mybb->input);
		if (!$imageSet->save()) {
			flash_message($lang->pp_edit_set_fail_message, 'error');
			admin_redirect($html->url(array(
				'action' => 'edit_set',
				'id' => $id,
			)));
		}

		flash_message($lang->pp_edit_set_success_message, 'success');
		admin_redirect($html->url(array('action' => 'sets')));
	}

	$form = new Form($html->url(array(
		'action' => 'edit_set',
		'id' => $id,
	)), 'post');

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
	$currentPage = (int) $mybb->input['page'];

	if ($id <= 0) {
		flash_message('Invalid image set', 'error');
		admin_redirect($html->url(array('action' => 'sets')));
	}

	$imageSet = new PicturePerfectImageSet($id);
	if (!$imageSet->isValid()) {
		flash_message('Invalid image set', 'error');
		admin_redirect($html->url(array('action' => 'sets')));
	}

	$redirectUrl = $html->url(
	array(
		'action' => 'view_set',
		'id' => $id,
		'page' => $currentPage,
	));

	if ($mybb->request_method == 'post') {
		if ($mybb->input['inline_action'] == 'delete') {
			if (!isset($mybb->input['pp_inline_ids']) ||
				empty($mybb->input['pp_inline_ids'])) {
				flash_message('No images selected', 'error');
				admin_redirect($redirectUrl);
			}

			$ids = array_filter($mybb->input['pp_inline_ids']);
			$ids = array_map('intval', $ids);

			if (empty($ids)) {
				flash_message('No valid images selected', 'error');
				admin_redirect($redirectUrl);
			}

			$imageList = implode(',', $ids);

			$result = $db->delete_query('pp_images', "setid='{$id}' AND id IN({$imageList})");

			if (!$result) {
				flash_message('Image(s) could not be deleted successfully', 'error');
				admin_redirect($redirectUrl);
			}

			flash_message('Image(s) deleted successfully', 'success');
			admin_redirect($redirectUrl);
		}
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item($lang->pp_admin_sets, $html->url(array('action' => 'sets')));
	$page->add_breadcrumb_item("{$lang->pp_admin_view_set} #{$id} ({$imageSet->get('title')})");

	$page->output_header("{$lang->pp} - {$lang->pp_admin_view_set}");
	pp_output_tabs('pp_view_set');

	// get a total count on the items
	$query = $db->simple_select('pp_images', 'COUNT(id) AS resultCount', "setid={$id}");
	$resultCount = $db->fetch_field($query, 'resultCount');

	$perPage = 12;
	$totalPages = ceil($resultCount / $perPage);

	$form = new Form($html->url(array(
		'action' => 'view_set',
		'mode' => 'inline',
	)), 'post');

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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array(
			'action' => 'view_set',
			'id' => $id,
		)));

		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_images', '*', "setid={$id}", array(
		'limit_start' => $start,
		'limit' => $perPage,
	));

	$count = 0;
	$images = array();
	while ($image = $db->fetch_array($query)) {
		$images[$image['id']] = $image;
	}

	foreach ($images as $iid => $image) {
		$imageClass = '';
		if (strpos($image['url'], $mybb->settings['bburl']) !== false) {
			$imageClass = ' localImage';
		}

		$cacheBuster = "?dateline={$image['dateline']}";
		$imageElement = $html->img($image['url'].$cacheBuster, array('class' => "thumbnail{$imageClass}"));

		$table->construct_cell($form->generate_check_box("pp_inline_ids[]", $iid, $imageElement, array('class' => 'pp_check')), array('class' => 'ppImage'), array('class' => 'ppImage'));

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

	$checkbox = $form->generate_check_box('', '', '', array(
		'id' => 'pp_select_all',
		'class' => 'pp_select_all',
	));

	echo $form->generate_hidden_field('id', $id);

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

	$redirectUrl = $html->url(array(
		'action' => 'image_tasks',
		'page' => $mybb->input['page'],
	));

	if ($mybb->request_method == 'post') {
		// verify incoming POST request
		if (!verify_post_check($mybb->input['my_post_key'])) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect($redirectUrl);
		}

		if ($mybb->input['mode'] == 'inline') {
			if (!is_array($mybb->input['pp_inline_ids']) ||
				empty($mybb->input['pp_inline_ids'])) {
				flash_message($lang->pp_inline_selection_error, 'error');
				admin_redirect($redirectUrl);
			}

			$job_count = 0;
			foreach ($mybb->input['pp_inline_ids'] as $id => $throw_away) {
				$task = new PicturePerfectImageTask($id);
				if (!$task->isValid()) {
					continue;
				}

				switch ($mybb->input['inline_action']) {
				case 'delete':
					$action = $lang->pp_deleted;
					if (!$task->remove()) {
						continue 2;
					}
					break;
				}
				++$job_count;
			}

			flash_message($lang->sprintf($lang->pp_inline_success, $job_count, $lang->image_tasks, $action), 'success');
			admin_redirect($redirectUrl);
		}
	}

	if ($mybb->input['mode'] == 'delete') {
		// good info?
		if (isset($mybb->input['id']) &&
			(int) $mybb->input['id']) {
			// then attempt deletion
			$task = new PicturePerfectImageTask($mybb->input['id']);
			if ($task->isValid()) {
				$success = $task->remove();
			}
		}

		if ($success) {
			flash_message($lang->sprintf($lang->pp_message_success, $lang->image_tasks, $lang->pp_deleted), 'success');
		} else {
			flash_message($lang->sprintf($lang->pp_message_fail, $lang->image_tasks, $lang->pp_deleted), 'error');
		}
		admin_redirect($redirectUrl);
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	$form = new Form($html->url(array(
		'action' => 'image_tasks',
		'mode' => 'inline',
	)), 'post');

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

	$newTaskUrl = $html->url(array(
		'action' => 'edit_image_task',
	));

	$newTaskLink = $html->link($newTaskUrl, 'Add a new image task', array(
		'style' => 'font-weight: bold;',
		'title' => 'Add a new image task',
		'icon' => "styles/{$cp_style}/images/pp/add.png",
	), array(
		'alt' => '+',
		'style' => 'margin-bottom: -3px;',
		'title' => 'Add a new image task',
	));

	echo($newTaskLink.'<br /><br />');

	$table = new Table;
	$table->construct_header('Title', array('width' => '35%'));
	$table->construct_header('Description', array('width' => '35%'));
	$table->construct_header('Module', array('width' => '15%'));
	$table->construct_header('Order', array('width' => '10%'));
	$table->construct_header('', array('style' => 'width: 1%'));

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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array(
			'action' => 'view_tasks',
			'id' => $id,
		)));

		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_image_tasks', '*', "lid=0", array(
		'limit_start' => $start,
		'limit' => $perPage,
		'order_by' => 'task_order',
		'order_dir' => 'ASC, id ASC',
	));

	$count = 0;
	$tasks = array();
	while ($task = $db->fetch_array($query)) {
		$tasks[$task['id']] = $task;
	}

	foreach ($tasks as $id => $task) {
		$addonName = $modules[$task['addon']]->get('title');

		$editUrl = $html->url(array(
			'action' => 'edit_image_task',
			'id' => $id,
		));

		$editLink = $html->link($editUrl, $task['title']);

		$table->construct_cell($editLink);
		$table->construct_cell($task['description']);
		$table->construct_cell($addonName);
		$table->construct_cell($task['task_order']);
		$table->construct_cell($form->generate_check_box("pp_inline_ids[{$id}]", '', '', array('class' => 'pp_check')));

		$table->construct_row();
	}

	if (empty($tasks)) {
		$table->construct_cell('nothing yet', array('colspan' => 5));
		$table->construct_row();
	}

	$checkbox = $form->generate_check_box('', '', '', array(
		'id' => 'pp_select_all',
		'class' => 'pp_select_all',
	));

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
	global $mybb, $db, $page, $lang, $html, $min, $modules, $hosts;

	$data = array();
	$id = (int) $mybb->input['id'];
	$task = new PicturePerfectImageTask($id);
	if ($task->isValid()) {
		$data = $task->get('data');
	}

	if ($mybb->request_method == 'post') {
		$module = $modules[$mybb->input['addon']];
		$redirectUrl = $html->url(array('action' => 'image_task_lists', 'page' => $mybb->input['page']));

		$rehost = $hostConfig = false;
		$baseName = $module->get('baseName');
		if ($baseName === 'rehost') {
			if (empty($hosts)) {
				flash_message('no image hosts installed', 'error');
				admin_redirect($redirectUrl);
			}

			if ($mybb->input['mode'] == 'host-config') {
				if (!isset($hosts[$mybb->input['host']])) {
					flash_message('no image hosts installed', 'error');
					admin_redirect($redirectUrl);
				}

				$host = $hosts[$mybb->input['host']];
				$module->set('settings', $host->get('settings'));
				$module->hasSettings = true;

				$mybb->input['mode'] = 'configure';
				$hostConfig = true;
			} elseif ($mybb->input['mode'] == 'configure') {
				$hostList = '';
				foreach ((array) $hosts as $name => $host) {
					$hostList .= "{$name}={$host->get('actionPhrase')}\n";
				}

				$setting = array(
					'title' => $module->get('actionPhrase'),
					'description' => 'select an image host',
					'optionscode' => "select\n{$hostList}",
					'value' => $name,
				);

				$module->set('settings', array('host' => $setting));
				$module->hasSettings = true;
			} else {
				if (!isset($hosts[$mybb->input['host']])) {
					flash_message('no image hosts installed', 'error');
					admin_redirect($redirectUrl);
				}

				$host = $hosts[$mybb->input['host']];

				$allSettings = $host->get('settings');
				$allSettings['host'] = array(
					'title' => $module->get('actionPhrase'),
					'description' => 'select an image host',
					'optionscode' => "text",
					'value' => $mybb->input['host'],
				);

				$module->set('settings', $allSettings);
				$module->hasSettings = true;
			}

			$rehost = true;
		}

		if ($mybb->input['mode'] == 'configure') {
			$page->add_breadcrumb_item('Configure Image Task');

			$page->output_header("{$lang->pp} - Configure Image Task");
			pp_output_tabs('pp_configure_image_task');

			if ($mybb->input['setid'] == 'new') {
				$imageSet = new PicturePerfectImageSet(array(
					'title' => "{$mybb->input['title']} (set)",
					'description' => "image set for {$mybb->input['title']}",
				));

				$mybb->input['setid'] = $imageSet->save();
				if (!$mybb->input['setid']) {
					flash_message('could not save image set', 'error');
					admin_redirect($redirectUrl);
				}
			} else {
				$imageSet = new PicturePerfectImageSet($mybb->input['setid']);

				if (!$imageSet->isValid()) {
					flash_message('Invalid image set', 'error');
					admin_redirect($redirectUrl);
				}
			}

			$form = new Form($html->url(array('action' => 'edit_image_task')), 'post');
			$formContainer = new FormContainer($module->get('title').' Settings');

			$module->outputSettings($formContainer);

			$modeInput = '';
			if ($rehost) {
				if ($hostConfig) {
					$modeInput = $form->generate_hidden_field('host', $mybb->input['host']);
				} else {
					$modeInput = $form->generate_hidden_field('mode', 'host-config');
				}
			}

			echo(
				$form->generate_hidden_field('id', $id).
				$form->generate_hidden_field('addon', $mybb->input['addon']).
				$form->generate_hidden_field('title', $mybb->input['title']).
				$form->generate_hidden_field('description', $mybb->input['description']).
				$form->generate_hidden_field('task_order', $mybb->input['task_order']).
				$form->generate_hidden_field('setid', $mybb->input['setid']).$modeInput
			);

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
			admin_redirect($html->url(array(
				'action' => 'edit_image_task',
				'mode' => 'configure',
			)));
		}

		flash_message('success', 'success');
		admin_redirect($html->url(array(
			'action' => 'image_tasks',
			'id' => $id,
		)));
	}

	if (is_array($modules) &&
		!empty($modules)) {
		$options = array();
		foreach ($modules as $key => $module) {
			$options[$key] = $module->get('actionPhrase');
		}
	}

	$sets = array('new' => 'new set');
	$query = $db->simple_select('pp_image_sets', '*');
	if ($db->num_rows($query)) {
		while ($set = $db->fetch_array($query)) {
			$sets[$set['id']] = $set['title'];
		}
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('Image Tasks', $html->url(array('action' => 'image_tasks')));
	$page->add_breadcrumb_item("Edit Image Task #{$id} ({$task->get('title')})");

	$page->output_header("{$lang->pp} - Edit Image Task");
	pp_output_tabs('pp_edit_image_task');

	$form = new Form($html->url(array(
		'action' => 'edit_image_task',
		'mode' => 'configure',
	)), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Title', '', $form->generate_text_box('title', $data['title']));
	$formContainer->output_row('Description', '', $form->generate_text_box('description', $data['description']));
	$formContainer->output_row('Module', '', $form->generate_select_box('addon', $options, $data['addon']));
	$formContainer->output_row('Image Set', '', $form->generate_select_box('setid', $sets, $data['setid']));
	$formContainer->output_row('Order', '',
		$form->generate_text_box('task_order', $data['task_order']).
		$form->generate_hidden_field('id', $id).
		$form->generate_hidden_field('pid', 0)
	);

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

	$redirectUrl = $html->url(array(
		'action' => 'image_task_lists',
		'page' => $mybb->input['page'],
	));

	if ($mybb->request_method == 'post') {
		// verify incoming POST request
		if (!verify_post_check($mybb->input['my_post_key'])) {
			flash_message($lang->invalid_post_verify_key2, 'error');
			admin_redirect($redirectUrl);
		}

		if ($mybb->input['mode'] == 'inline') {
			if (!is_array($mybb->input['pp_inline_ids']) ||
				empty($mybb->input['pp_inline_ids'])) {
				flash_message($lang->pp_inline_selection_error, 'error');
				admin_redirect($redirectUrl);
			}

			$job_count = 0;
			foreach ($mybb->input['pp_inline_ids'] as $id => $throw_away) {
				$taskList = new PicturePerfectImageTaskList($id);
				if (!$taskList->isValid()) {
					continue;
				}

				switch ($mybb->input['inline_action']) {
				case 'delete':
					$action = $lang->pp_deleted;
					if (!$taskList->remove()) {
						continue 2;
					}
					break;
				}
				++$job_count;
			}

			flash_message($lang->sprintf($lang->pp_inline_success, $job_count, $lang->image_task_lists, $action), 'success');
			admin_redirect($redirectUrl);
		}
	}

	if ($mybb->input['mode'] == 'delete') {
		// good info?
		if (isset($mybb->input['id']) &&
			(int) $mybb->input['id']) {
			// then attempt deletion
			$taskList = new PicturePerfectImageTaskList($mybb->input['id']);
			if ($taskList->isValid()) {
				$success = $taskList->remove();
			}
		}

		if ($success) {
			flash_message($lang->sprintf($lang->pp_message_success, $lang->image_task_lists, $lang->pp_deleted), 'success');
		} else {
			flash_message($lang->sprintf($lang->pp_message_fail, $lang->image_task_lists, $lang->pp_deleted), 'error');
		}

		admin_redirect($redirectUrl);
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	$form = new Form($html->url(array(
		'action' => 'image_task_lists',
		'mode' => 'inline',
	)), 'post');

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
<br />
EOF;

	$newTaskUrl = $html->url(array('action' => 'edit_image_task_list'));
	$newTaskLink = $html->link($newTaskUrl, 'Add a new image task list', array(
		'style' => 'font-weight: bold;',
		'title' => 'Add a new image task list',
		'icon' => "styles/{$cp_style}/images/pp/add.png"
	), array(
		'alt' => '+',
		'style' => 'margin-bottom: -3px;',
		'title' => 'Add a new image task list',
	));
	echo($newTaskLink.'<br /><br />');

	$table = new Table;
	$table->construct_header('Title', array('width' => '25%'));
	$table->construct_header('Description', array('width' => '25%'));
	$table->construct_header('Images', array('width' => '5%'));
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
		$pagination = draw_admin_pagination($mybb->input['page'], $perPage, $resultCount, $html->url(array(
			'action' => 'view_task_lists',
			'id' => $id,
		)));

		echo($pagination.'<br />');
	}

	$query = $db->simple_select('pp_image_sets', '*');
	$imageSets = array();
	while ($imageSet = $db->fetch_array($query)) {
		$imageSets[$imageSet['id']] = $imageSet['title'];
	}

	$query = $db->simple_select('pp_image_task_lists', '*', '', array(
		'limit_start' => $start,
		'limit' => $perPage,
	));

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

		$taskListStatus = $taskList['active'] ? 'Active' : 'Inactive';

		$editUrl = $html->url(array(
			'action' => 'edit_image_task_list',
			'id' => $id,
		));

		$editLink = $html->link($editUrl, $taskList['title']);

		$table->construct_cell($editLink);
		$table->construct_cell($taskList['description']);
		$table->construct_cell($imageCount);
		$table->construct_cell($taskListStatus);
		$table->construct_cell($form->generate_check_box("pp_inline_ids[{$id}]", '', '', array('class' => 'pp_check')));

		$table->construct_row();
	}

	if (empty($taskLists)) {
		$table->construct_cell('nothing yet', array('colspan' => 6));
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
		$redirectUrl = $html->url(array('action' => 'image_task_lists'));

		if (empty($mybb->input['tasks'])) {
			flash_message('No tasks selected', 'error');
			admin_redirect($redirectUrl);
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
			admin_redirect($redirectUrl);
		}

		flash_message('success', 'success');
		admin_redirect($html->url(array(
			'action' => 'image_task_lists',
			'id' => $id,
		)));
	}

	$selected = array();
	if ($id) {
		$query = $db->simple_select('pp_image_tasks', '*', "lid='{$id}' AND pid !=0", array(
			'order_by' => 'task_order',
			'order_dir' => 'ASC',
		));

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

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('Image Task Lists', $html->url(array('action' => 'image_task_lists')));
	$page->add_breadcrumb_item("Edit Image Task List #{$id} ({$taskList->get('title')})");

	$page->output_header("{$lang->pp} - Edit Image Task List");
	pp_output_tabs('pp_edit_image_task_list');

	$form = new Form($html->url(array('action' => 'edit_image_task_list')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Title', '', $form->generate_text_box('title', $data['title']));
	$formContainer->output_row('Description', '', $form->generate_text_box('description', $data['description']));
	$formContainer->output_row('Task(s)', '', $form->generate_select_box('tasks[]', $options, $selected, array('multiple' => true)));
	$formContainer->output_row('Active?', '',
		$form->generate_yes_no_radio('active', $data['active']).
		$form->generate_hidden_field('id', $id)
	);

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

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('Image Scan Center', $html->url(array('action' => 'scan_center')));

	if ($mybb->request_method == 'post' ||
		$mybb->input['mode'] == 'inline') {
		$redirectUrl = $html->url(array(
			'action' => 'scan_center',
			'mode' => 'confirm_overwrite',
			'tid' => $tid,
			'fid' => $fid,
		));

		if ($tid) {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id', "tid='{$tid}'");

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this thread!', 'error');
					admin_redirect($redirectUrl);
				}
			}

			$message = "Scan Thread #{$mybb->input['tid']}";
		} elseif ($fid) {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id', "fid='{$fid}'");

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this forum!', 'error');
					admin_redirect($redirectUrl);
				}
			}

			$message = "Scan Forum #{$mybb->input['fid']}";
		} else {
			if ($mybb->input['mode'] != 'override') {
				$query = $db->simple_select('pp_images', 'id');

				if ($db->num_rows($query)) {
					flash_message('Existing images found for this site!', 'error');
					admin_redirect($redirectUrl);
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

		$form = new Form($html->url(array(
			'action' => 'scan_center',
			'mode' => 'override',
		)), 'post');

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

	$page->output_header("{$lang->pp} - Image Scan Center");
	pp_output_tabs('pp_scan_center');

	$form = new Form($html->url(array('action' => 'scan_center')), 'post');

	$formContainer = new FormContainer();

	$formContainer->output_row('Thread ID', 'enter a TID to search a single thread', $form->generate_text_box('tid'));
	$formContainer->output_row('Forum ID', 'enter an FID to search a single forum', $form->generate_text_box('fid'));

	$formContainer->end();

	$buttons[] = $form->generate_submit_button('Start Scan', array('name' => 'start_image_scan'));
	$form->output_submit_wrapper($buttons);
	$form->end();

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

	$tid = (int) $mybb->input['tid'];
	$from = trim($mybb->input['from']);
	$fromId = (int) $mybb->input['fromid'];

	$redirectArray = ppBuildRedirectUrlArray($fromId, $from);
	$redirectUrl = $html->url($redirectArray);

	if (isset($mybb->input['pp_task_submit'])) {
		ppAddImagesToTaskList();
	} elseif ($mybb->input['task'] == 'caption') {
		if (empty($mybb->input['pp_inline_ids'])) {
			flash_message('No selected images', 'error');
			admin_redirect($redirectUrl);
		}

		$success = $fail = 0;
		foreach ($mybb->input['pp_inline_ids'] as $id => $throwAway) {
			$i = new PicturePerfectImage($id);
			if (!$i->isValid()) {
				$fail++;
			} else {
				$i->set('caption', $mybb->input['image_captions'][$id]);
				if (!$i->save()) {
					$fail++;
				} else {
					$success++;
				}
			}
		}

		$status = 'success';
		$message = "{$success} caption(s) updated";
		if ($fail) {
			if ($success) {
				$message .= "; {$fail} caption(s) failed.";
				$status = '';
			} else {
				$message = "{$fail} caption(s) failed.";
				$status = 'error';
			}
		}

		flash_message($message, $status);
		admin_redirect($redirectUrl);
	} elseif ($mybb->input['task'] == 'reset_images') {
		if (empty($mybb->input['pp_inline_ids'])) {
			flash_message('No selected images', 'error');
			admin_redirect($redirectUrl);
		}

		$success = $fail = 0;
		foreach ($mybb->input['pp_inline_ids'] as $id => $throwAway) {
			$i = new PicturePerfectImage($id);
			if (!$i->isValid()) {
				$fail++;
			} else {
				$i->set('imagechecked', false);
				if (!$i->save()) {
					$fail++;
				} else {
					$success++;
				}
			}
		}

		$status = 'success';
		$message = "fetching info for {$success} image(s)";
		if ($fail) {
			if ($success) {
				$message .= "; {$fail} image(s) failed.";
				$status = '';
			} else {
				$message = "{$fail} image(s) failed.";
				$status = 'error';
			}
		}

		flash_message($message, $status);
		admin_redirect($redirectUrl);
	}

	$doTask = false;
	if (isset($mybb->input['pp_inline_task'])) {
		$doTask = true;

		$task = new PicturePerfectImageTask($mybb->input['task']);
		$taskId = (int) $task->get('id');

		if (!$taskId) {
			flash_message('Invalid task', 'error');
			admin_redirect($redirectUrl);
		}

		$module = new PicturePerfectModule($task->get('addon'));
	} else {
		$module = new PicturePerfectModule($mybb->input['addon']);
	}

	if (!$module->isValid()) {
		flash_message($lang->pp_process_images_fail_invalid_module, 'error');
		admin_redirect($redirectUrl);
	}

	$isHost = false;
	$contentRequired = $module->get('contentRequired');
	$storeImage = $module->get('storeImage');
	$hostSettings = array();
	if ($module->get('baseName') === 'rehost') {
		$imageHost = $mybb->input['host'];
		if (!$imageHost) {
			$settings = $task->get('settings');
			$imageHost = $settings['host'];
		}

		if ($imageHost) {
			$host = new PicturePerfectImageHost($imageHost);

			if ($host->isValid()) {
				$isHost = true;
				$contentRequired = $host->get('contentRequired');
			}
		}
	}

	$selected = $mybb->input['pp_inline_ids'];
	$selectedCount = count($selected);

	if (!is_array($selected) ||
		empty($selected)) {
		flash_message('no images', 'error');
		admin_redirect($redirectUrl);
	}

	if ($selectedCount > $module->get('imageLimit')) {
		flash_message($lang->sprintf($lang->pp_process_images_fail_exceed_module_limit, $module->get('imageLimit')), 'error');
		admin_redirect($redirectUrl);
	}

	if ($mybb->input['mode'] == 'finalize' ||
		$doTask || $module->hasSettings !== true) {
		if ($doTask) {
			$mybb->input['setid'] = $task->get('setid');
		} else {
			$extra = '';
			if ($mybb->input['setid'] == 'new') {
				$mybb->input['setid'] = 0;
				$extra = $lang->pp_process_images_finalize_success_extra;
			}
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
		if ($doTask) {
			$settings = $task->get('settings');
			$module->set('settings', $settings);
		} elseif ($isHost) {
			foreach ($host->get('settings') as $name => $setting) {
				$settings[$name] = $setting['value'];
				if (isset($mybb->input[$name])) {
					$settings[$name] = $mybb->input[$name];
				}
			}

			$settings['host'] = $host->get('baseName');
		} else {
			foreach ($module->get('settings') as $name => $setting) {
				$settings[$name] = $setting['value'];
				if (isset($mybb->input[$name])) {
					$settings[$name] = $mybb->input[$name];
				}
			}
		}

		if ($contentRequired) {
			$images = ppFetchRemoteFiles($images, $storeImage);
		}

		$info = $module->processImages($images, $settings);

		$sep = '';
		$status = 'success';
		foreach ((array) $info['messages'] as $m) {
			if ($m['status'] == 'error') {
				$status = 'error';
			}

			$message .= $sep.$m['message'];
			$sep = '<br />';
		}

		if (!empty($info['redirect'])) {
			$redirectArray = $info['redirect'];
		}

		flash_message($message, $status);
		admin_redirect($html->url($redirectArray));
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	$form = new Form($html->url(array(
		'action' => 'process_images',
		'mode' => 'finalize',
	)), 'post');

	$formContainer = new FormContainer($module->get('title').' Settings');

	if ($module->get('createsSet')) {
		$setArray = array('new' => 'new set');

		$query = $db->simple_select('pp_image_sets', 'id, title');
		while ($set = $db->fetch_array($query)) {
			$setArray[$set['id']] = $set['title'];
		}

		$formContainer->output_row($lang->pp_image_set, $lang->pp_image_set_desc, $form->generate_select_box('setid', $setArray, 'new', array('id' => 'setting_setid')), 'setid', array('id' => 'setting_setid'));
	}

	$module->outputSettings($formContainer);

	if ($isHost &&
		$host->hasSettings) {
		$formContainer->output_row('Host Settings', 'settings for the chosen image host', '');

		$host->outputSettings($formContainer);

		echo $form->generate_hidden_field('host', $host->get('baseName'));
	}

	foreach ((array) $selected as $id => $throwAway) {
		echo $form->generate_hidden_field("pp_inline_ids[{$id}]", 1);
	}

	echo(
		$form->generate_hidden_field('addon', $mybb->input['addon'])
		.$form->generate_hidden_field('page', $mybb->input['page'])
		.$form->generate_hidden_field('from', $from)
		.$form->generate_hidden_field('fromid', $fromId)
	);

	$formContainer->end();
	$buttons[] = $form->generate_submit_button($lang->pp_process_submit, array('name' => 'process_submit'));
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * scan forum posts for images and store info
 *
 * @return void
 */
function pp_admin_scan()
{
	global $mybb, $page, $db, $lang, $cache, $min, $html;

	if (!$lang->pp) {
		$lang->load('pp');
	}

	if ($page->active_action != 'pp') {
		return false;
	}

	if (isset($mybb->input['do_not_analyze_posts'])) {
		$warning = '<br /><br />Skipping the image scan means that you will not see any images in the ACP interface. You will need to scan images from your forum\'s post before you can begin to use the plugin image processing modules.';
		flash_message($lang->pp_installation_finished.$warning, 'success');
		admin_redirect('index.php?module=config-plugins');
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

		$query = $db->simple_select('posts', 'pid, tid, fid, message', $where, array(
			'limit' => $ppp,
			'limit_start' => $start,
			'order_by' => 'dateline',
			'order_dir', 'ASC',
		));

		$count = $db->num_rows($query);
		if ($count == 0 ||
			$count < $ppp) {
			$done = true;
		}

		$insert_arrays = array();
		while ($post = $db->fetch_array($query)) {
			foreach ((array) ppGetPostImages($post['message']) as $source) {
				$threadCache["{$post['fid']}-{$post['tid']}"]++;

				$secure = false;
				if (substr($source, 0, 5) === 'https') {
					$secure = true;
				}

				$insert_arrays[] = array(
					'setid' => 0,
					'pid' => (int) $post['pid'],
					'tid' => (int) $post['tid'],
					'fid' => (int) $post['fid'],
					'url' => $source,
					'secureimage' => $secure,
					'dateline' => TIME_NOW,
				);
			}
		}

		if (!empty($insert_arrays)) {
			$db->insert_query_multiple('pp_images', $insert_arrays);
		}

		if ($done) {
			$forumCounts = $insert_arrays = array();

			foreach ($threadCache as $key => $count) {
				$keyPieces = explode('-', $key);
				list($forumId, $threadId) = $keyPieces;

				$insert_arrays[] = array(
					'tid' => (int) $threadId,
					'fid' => (int) $forumId,
					'image_count' => (int) $count,
					'dateline' => TIME_NOW,
				);

				$forumCounts[$forumId] += $count;
			}

			if (!empty($insert_arrays)) {
				$db->insert_query_multiple('pp_image_threads', $insert_arrays);
			}

			$cache->update('pp_thread_cache', null);

			$insert_arrays = array();

			foreach ((array) $forumCounts as $forumId => $count) {
				$insert_arrays[] = array(
					'fid' => (int) $forumId,
					'image_count' => (int) $count,
					'dateline' => TIME_NOW,
				);
			}

			if (!empty($insert_arrays)) {
				$db->insert_query_multiple('pp_image_forums', $insert_arrays);
			}

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

	$page->add_breadcrumb_item($lang->pp, $html->url());
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

	$form_container->output_cell($form->generate_numeric_field('posts_per_page', $ppp, array(
		'style' => 'width: 150px;',
		'min' => 0,
	)));

	$form_container->output_cell($form->generate_submit_button($lang->go, array(
		'name' => 'analyze_posts',
		'class' => 'button_yes',
		'id' => 'analyze_submit',
	)));

	$form_container->output_cell($form->generate_submit_button('Skip for now...', array(
		'name' => 'do_not_analyze_posts',
		'class' => 'button_no',
		'id' => 'skip_submit',
	)).
		$form->generate_hidden_field('start', $start).
		$form->generate_hidden_field('count', $totalCount).
		$form->generate_hidden_field('in_progress', 1).
		$form->generate_hidden_field('action', 'scan').
		$form->generate_hidden_field('fid', $fid).
		$form->generate_hidden_field('tid', $tid).
		$form->generate_hidden_field('lastpid', $lastPid)
	);

	$form_container->construct_row();
	$form_container->end();
	$form->end();

	$page->output_footer();
	exit;
}

/**
 * retrieve redirect info from URL
 *
 * @return void
 */
function pp_admin_parse_url()
{
	global $mybb, $db, $html;

	$errorRedirect = $html->url($mybb->input['from']);

	$url = trim($mybb->input['url']);
	if ((int) $url <= 0 &&
		my_strpos($url, $mybb->settings['bburl']) === false) {
		flash_message('Invalid URL', 'error');
		admin_redirect($errorRedirect);
	}

	$url = str_replace($mybb->settings['bburl'], '', $url);

	while (my_strpos($url, '/') === 0) {
		$url = my_substr($url, 1);
	}

	$tidPattern = "#^([\d]+)$#";
	$standardPattern = "#showthread.php\?(pid|tid)=([\d]+)(?:.*?)(?:&pid=([\d]+))?#is";
	$seoPattern = "#([thread|post])-([\d]+)(?:-page-[\d]+)?(?:-post-([\d]+))?(?:-[a-zA-Z]+)?#is";

	$tid = $pid = 0;
	if (preg_match($tidPattern, $url, $match)) {
		$tid = (int) $match[1];
	} elseif (preg_match($standardPattern, $url, $match)) {
		$type = 'Standard';

		if ($match[1] == 'tid') {
			$tid = (int) $match[2];
			if ((int) $match[3]) {
				$pid = (int) $match[3];
			}
		} else {
			$pid = (int) $match[2];

			$post = get_post($pid);
			$tid = $post['tid'];
			$fid = $post['fid'];
		}
	} elseif (preg_match($seoPattern, $url, $match)) {
		$type = 'Default SEO';

		if ($match[1] == 'thread') {
			$tid = (int) $match[2];
		} else {
			$pid = (int) $match[2];

			$post = get_post($pid);
			$tid = $post['tid'];
			$fid = $post['fid'];
		}
	} elseif ($mybb->settings['google_seo_url_threads'] &&
		$db->table_exists('google_seo')) {
		$type = 'Google SEO';

		$seoPrefix = str_replace('{url}', '', $mybb->settings['google_seo_url_threads']);

		if (strpos($url, $seoPrefix) !== 0) {
			flash_message('Invalid URL', 'error');
			admin_redirect($errorRedirect);
		}

		$url = substr($url, strlen($seoPrefix));

		$urlPieces = explode('?', $url);
		if (count($urlPieces) > 1) {
			list($url, $queryString) = $urlPieces;

			if (my_strpos($queryString, '#') !== false) {
				$queryStringPieces = explode('#', $urlPieces[1]);
				$queryString = $queryStringPieces[0];
			}

			$queryStringPieces = explode('&', $queryString);
			if (count($queryStringPieces) > 0) {
				foreach ($queryStringPieces as $piece) {
					$queryPieces = explode('=', $piece);
					if (count($queryPieces) < 2) {
						continue;
					}

					switch ($queryPieces[0]) {
					case 'tid':
						$tid = (int) $queryPieces[1];
						break;
					case 'pid':
						$pid = (int) $queryPieces[1];
						break;
					}

					if ($tid && $pid) {
						break;
					}
				}

				if ($pid && !$tid) {
					$post = get_post($pid);
					$tid = $post['tid'];
				}
			}
		}

		if ($url &&
			!$tid) {
			$url = $db->escape_string($url);

			$query = $db->simple_select('google_seo', 'id', "idtype='4' AND url='{$url}'");

			if ($db->num_rows($query) <= 0) {
				flash_message('Invalid URL', 'error');
				admin_redirect($errorRedirect);
			}

			$tid = $db->fetch_field($query, 'id');
		}
	}

	if (!$tid) {
		flash_message('Invalid URL', 'error');
		admin_redirect($errorRedirect);
	}

	if (!$fid) {
		$thread = get_thread($tid);
		$fid = $thread['fid'];
	}

	$redirectArray = array(
		'action' => 'view_thread',
		'fid' => $fid,
		'tid' => $tid,
	);

	$queryWhere = "tid='{$tid}' AND setid='0'";

	$message = 'Redirected to thread...';
	if ($pid) {
		$message = 'Redirected to post...';
		$redirectArray['pid'] = $pid;
	}

	$query = $db->simple_select('pp_images', 'count(id) as count', $queryWhere);
	$count = $db->fetch_field($query, 'count');

	if ($count <= 0) {
		$message = "No images in thread #{$tid}";
		if ($pid) {
			$message .= ", Post #{$pid}";
		}

		flash_message($message, 'error');
		admin_redirect($errorRedirect);
	}

	$redirectUrl = $html->url($redirectArray);

	flash_message($message, 'success');
	admin_redirect($redirectUrl);
}

/**
 * update a single images caption
 *
 * @return void
 */
function pp_admin_update_caption()
{
	global $mybb, $db, $page, $lang, $html, $min, $cp_style, $modules;

	$id = (int) $mybb->input['id'];

	if (!$id) {
		flash_message('No Image ID', 'error');
		admin_redirect($html->url($redirectUrl));
	}

	$image = new PicturePerfectImage($id);
	if (!$image->isValid()) {
		flash_message('Invalid Image ID', 'error');
		admin_redirect($html->url());
	}

	$data = $image->get('data');
	$tid = (int) $data['tid'];
	$fid = (int) $data['fid'];
	$mybb->input['page'] = (int) $mybb->input['page'] ? (int) $mybb->input['page'] : 1;

	$redirectArray = array(
		'action' => 'view_thread',
		'tid' => $tid,
		'fid' => $fid,
		'page' => $mybb->input['page'],
	);

	if ($mybb->request_method == 'post') {
		$caption = trim($mybb->input['caption']);

		if (!$caption) {
			$redirectArray['action'] = 'update_caption';

			flash_message('No caption provided.', 'error');
			admin_redirect($html->url($redirectArray));
		}

		$image->set('caption', $caption);
		if (!$image->save()) {
			flash_message('Caption could not be updated.', 'error');
			admin_redirect($html->url($redirectArray));
		}

		flash_message('Caption updated successfully.', 'success');
		admin_redirect($html->url($redirectArray));
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('Update Image Caption', $html->url(array('action' => 'update_caption')));
	$page->add_breadcrumb_item("Update Image Caption (#{$id})");

	$page->output_header("{$lang->pp} - Update Image Caption");
	pp_output_tabs('pp_update_caption');

	$form = new Form($html->url(array('action' => 'update_caption')), 'post');

	$formContainer = new FormContainer('Update Image Caption');

	$formContainer->output_row('Caption', 'enter a caption here',
		$form->generate_text_box('caption', $data['caption']).
		$form->generate_hidden_field('action', 'update_caption').
		$form->generate_hidden_field('id', $id).$form->generate_hidden_field('tid', $tid).
		$form->generate_hidden_field('fid', $fid).
		$form->generate_hidden_field('page', $mybb->input['page'])
	);

	$formContainer->end();
	$buttons[] = $form->generate_submit_button('Submit');
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

function pp_admin_set_image_data()
{
	global $mybb, $db;

	$ret = false;

	$affectedImages = 0;
	$done = array();
	if (!empty($mybb->input['imagedata'])) {
		foreach ($mybb->input['imagedata'] as $id => $data) {
			if (isset($done[$data['url']])) {
				continue;
			}
	
			$image = new PicturePerfectImage($id);

			if (!$image->isValid()) {
				continue;
			}

			$url = $image->get('url');
			$eUrl = $db->escape_string($url);
			$done[$url] = 1;

			$updateArray = array(
				'width' => (int) $data['width'],
				'height' => (int) $data['height'],
				'deadimage' => $data['deadimage'] === "true" || $data['deadimage'] === true ? 1 : 0,
				'secureimage' => $data['secureimage'] === "true" || $data['secureimage'] === true ? 1 : 0,
				'imagechecked' => 1,
			);

			$count = $db->update_query('pp_images', $updateArray, "setid=0 AND url='{$eUrl}'");

			if ($count > 0) {
				$affectedImages += $count;
			}
		}

		$ret = ($affectedImages > 0);
	}

	ppOutputJson($ret);
}

function pp_admin_add_results_to_task_list()
{
	global $mybb, $db, $page, $lang, $html;

	$id = (int) $mybb->input['id'];
	$redirectUrl = $html->url(array('action' => 'search_results', 'id' => $id));

	if ($mybb->input['mode'] == 'finalize') {
		$taskListId = (int) $mybb->input['tasklist'];
		
		$search = new PicturePerfectImageSearch($id);

		if (!$search->isValid()) {
			flash_message('invalid image search', 'error');
			admin_redirect($redirectUrl);
		}

		$where = $search->buildSqlWhere();
		$query = $db->simple_select('pp_images', 'id', $where);
		if ($db->num_rows($query) <= 0) {
			flash_message('no images to add', 'error');
			admin_redirect($redirectUrl);
		}

		$selected = array();
		while ($iid = $db->fetch_field($query, 'id')) {
			$selected[] = $iid;
		}

		$taskList = new PicturePerfectImageTaskList($taskListId);

		if (!$taskList->isValid()) {
			flash_message('invalid task list', 'error');
			admin_redirect($redirectUrl);
		}

		$count = $taskList->addImages($selected);

		if ($count < 1) {
			flash_message($lang->sprintf('Add {1} image(s) to task list. ({2} image(s) were already associated with this task list)', $count, max(0, count($selected) - $count)), 'error');
			admin_redirect($redirectUrl);
		}

		if (!$taskList->save()) {
			flash_message('could not save task list', 'error');
			admin_redirect($redirectUrl);
		}

		flash_message($lang->sprintf('Add {1} image(s) to task list. ({2} image(s) were already associated with this task list)', $count, max(0, count($selected) - $count)), 'success');
		admin_redirect($redirectUrl);
	}

	$page->add_breadcrumb_item($lang->pp, $html->url());
	$page->add_breadcrumb_item('Add Search Results To Task List', $html->url(array('action' => 'add_results_to_task_list')));

	$page->output_header("{$lang->pp} - Add Search Results To Task List");
	pp_output_tabs('pp_add_results_to_task_list');

	$form = new Form('', 'post');

	$formContainer = new FormContainer('Add Search Results To Task List');

	$formContainer->output_row('Task List', 'choose a task list',
		$form->generate_select_box('tasklist', getAllTaskLists()).
		$form->generate_hidden_field('action', 'add_results_to_task_list').
		$form->generate_hidden_field('mode', 'finalize').
		$form->generate_hidden_field('id', $id).$form->generate_hidden_field('tid', $tid)
	);

	$formContainer->end();
	$buttons[] = $form->generate_submit_button('Add Images To Task List');
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

	/** hook functions **/

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

	/** misc. functions **/

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
	}

	$subTabs['pp_image_search'] = array(
		'title' => $lang->pp_image_search,
		'link' => $html->url(array('action' => 'image_search')),
		'description' => $lang->sprintf($lang->pp_image_search_desc),
	);

	switch ($current) {
	case 'pp_search_results':
		$subTabs['pp_search_results'] = array(
			'title' => $lang->pp_search_results,
			'link' => $html->url(array('action' => 'search_results')),
			'description' => $lang->sprintf($lang->pp_search_results_desc),
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
	case 'pp_confirm_overwrite':
		$urlArray = array('action' => 'scan_center', 'mode' => 'confirm_overwrite');

		$subTabs['pp_confirm_overwrite'] = array(
			'title' => 'Confirm Image Overwrite',
			'link' => $html->url($urlArray),
			'description' => 'The thread or forum being scanned already has existing images. You are being asked to confirm whether to delete the existing images and rescan, or to only look for new images.',
		);
		break;
	case 'pp_update_caption':
		$urlArray = array('action' => 'update_caption', 'id' => $mybb->input['id']);

		$subTabs['pp_update_caption'] = array(
			'title' => 'Update Image Caption',
			'link' => $html->url($urlArray),
			'description' => 'edit caption for posted images',
		);
		break;
	}

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
	case 'pp_edit_set':
		$urlArray = array('action' => 'edit_set');

		$subTabs['pp_edit_set'] = array(
			'title' => $lang->pp_admin_edit_set,
			'link' => $html->url($urlArray),
			'description' => $lang->pp_admin_edit_set_desc,
		);
		break;
	}

	$subTabs['pp_image_tasks'] = array(
		'title' => 'Image Tasks',
		'link' => $html->url(array('action' => 'image_tasks')),
		'description' => 'view and manage image tasks',
	);

	switch ($current) {
	case 'pp_edit_image_task':
		$urlArray = array('action' => 'edit_image_task');

		$subTabs['pp_edit_image_task'] = array(
			'title' => 'Edit Image Task',
			'link' => $html->url($urlArray),
			'description' => 'edit image task details',
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
	}

	$subTabs['pp_image_task_lists'] = array(
		'title' => 'Image Task Lists',
		'link' => $html->url(array('action' => 'image_task_lists')),
		'description' => 'view and manage image tasks',
	);

	switch ($current) {
	case 'pp_edit_image_task_list':
		$urlArray = array('action' => 'edit_image_task_list');

		$subTabs['pp_edit_image_task_list'] = array(
			'title' => 'Edit Image Task List',
			'link' => $html->url($urlArray),
			'description' => 'edit image task list details',
		);
		break;
	}

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
	static $imageCounts;

	// load the forum cache if necessary
	if (!is_array($allForums)) {
		$imageCounts = $fids = array();

		$forumCache = cache_forums();

		foreach ($forumCache as $forum) {
			$allForums[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}

		$query = $db->simple_select('pp_image_forums', 'fid, image_count');

		while ($forum = $db->fetch_array($query)) {
			$imageCounts[$forum['fid']] = $forum['image_count'];
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
				$imageCount = (int) $imageCounts[$forum['fid']];

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
				$popup->add_item('Scan', $html->url(array(
					'action' => 'scan_center',
					'mode' => 'inline',
					'fid' => $forum['fid'],
				)));

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
 * perform image scan according to parameters
 *
 * @param  string
 * @param  int
 * @param  int
 * @param  bool
 * @param  bool
 * @return void
 */
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
		$query = $db->simple_select('pp_images', 'pid', $where, array(
			'order_by' => 'pid',
			'order_dir' => 'DESC',
			'limit' => 1,
		));

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
 * merge new images into an existing task list
 *
 * @return void
 */
function ppAddImagesToTaskList()
{
	global $mybb, $db, $page, $lang, $html, $min;

	$selected = $mybb->input['pp_inline_ids'];
	$selectedCount = count($selected);

	$tid = (int) $mybb->input['tid'];
	$redirectUrl = $html->url(array(
		'action' => 'view_thread',
		'tid' => $tid,
		'page' => $mybb->input['page'],
	));

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

	$count = $taskList->addImages(array_keys($selected));

	if ($count < 1) {
		flash_message($lang->sprintf('Add {1} image(s) to task list. ({2} image(s) were already associated with this task list)', $count, max(0, count($selected) - $count)), 'error');
		admin_redirect($redirectUrl);
	}

	if (!$taskList->save()) {
		flash_message('could not save task list', 'error');
		admin_redirect($redirectUrl);
	}

	flash_message($lang->sprintf('Add {1} image(s) to task list. ({2} image(s) were already associated with this task list)', $count, max(0, count($selected) - $count)), 'success');
	admin_redirect($redirectUrl);
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
