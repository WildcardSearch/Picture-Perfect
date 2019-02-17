<?php
/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2018 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * ACP language file
 */

$l['pp'] = 'Picture Perfect';

$l['pp_description'] = 'manage your forum\'s posted images';

/* settings */

$l['pp_settingsgroup_description'] = 'placeholder';
$l['pp_plugin_settings'] = 'Plugin Settings';
$l['pp_manage_images'] = 'Manage Images';

$l['pp_minify_js_title'] = 'Minify JavaScript?';
$l['pp_minify_js_desc'] = 'YES (default) to serve client-side scripts minified to increase performance, NO to serve beautiful, commented code ;)';

$l['pp_inline_title'] = 'Inline Edits';

/* install */

$l['pp_installation'] = 'Picture Perfect Installation';
$l['pp_finalizing_installation'] = 'Component installation completed.';
$l['pp_finalize_installation'] = 'Finalize Installation';
$l['pp_analyze_posted_images'] = 'Analyze Posted Images';
$l['pp_analyze_posted_images_description'] = 'Collect and store information about the posted images on your forum.';
$l['pp_posts_per_page'] = 'Posts Per Page';
$l['pp_installation_finished'] = 'Picture Perfect has been successfully installed on your forum.';
$l['pp_installation_progress'] = 'Analyzing posts...<br />({1} of {2})';
$l['pp_redirect_button_text'] = 'Automatically Redirecting...';

// acp
$l['pp_admin_permissions_desc'] = 'Can use Picture Perfect?';
$l['pp_admin_main_desc'] = 'view image threads';

$l['pp_view_thread'] = 'View Thread';
$l['pp_view_thread_desc'] = 'view images in {1}';

$l['pp_thread'] = 'Thread';
$l['pp_image_threads'] = 'Image Threads';
$l['pp_image_count'] = 'Images';
$l['pp_no_image_threads'] = 'nothing yet';
$l['pp_images'] = 'Images';

$l['pp_process_images'] = 'Process Images';
$l['pp_process_images_desc'] = 'perform image tasks';

$l['pp_process_submit'] = 'Process Images';
$l['pp_admin_view_thumbnails'] = 'View Thumbnails';

$l['pp_process_images_fail_invalid_module'] = 'invalid module';
$l['pp_process_images_fail_no_images'] = 'no images';
$l['pp_process_images_finalize_fail_could_not_create_set'] = 'The image set could not be created successfully.';
$l['pp_process_images_finalize_success'] = 'Images processed successfully.{1}';
$l['pp_process_images_finalize_success_extra'] = ' You may now customize the new image set.';
$l['pp_process_images_fail_exceed_module_limit'] = 'You have selected more images than this module can process at once. The module limit is {1}';

$l['pp_admin_sets'] = 'View Images Sets';
$l['pp_admin_sets_desc'] = 'view and manage image sets';
$l['pp_no_image_sets'] = 'no image sets yet';
$l['pp_image_set'] = 'Image Set';
$l['pp_image_set_desc'] = 'add these images to an existing set, or create a new one';
$l['pp_image_sets'] = 'Image Sets';
$l['pp_image_sets_title'] = 'Title';
$l['pp_image_sets_title_description'] = 'a unique identifier';
$l['pp_image_sets_description'] = 'Description';
$l['pp_image_sets_description_desc'] = 'explain what type of images belong in this set';

$l['pp_admin_view_set'] = 'View Image Set';
$l['pp_admin_view_set_desc'] = 'view and manage image sets';

$l['pp_admin_edit_set'] = 'Edit Image Set';
$l['pp_admin_edit_set_desc'] = 'edit this image set\'s details';

$l['pp_edit_set_submit'] = 'Update Image Set';
$l['pp_edit_set_fail_message'] = 'The image set could not be updated.';
$l['pp_edit_set_success_message'] = 'The image set was successfully updated.';

// messages
$l['pp_message_success'] = '{1} {2} successfully';
$l['pp_message_fail'] = "{1} couldn't be {2} successfully";

$l['pp_inline_selection_error'] = 'You did not select anything.';
$l['pp_delete'] = 'Delete';
$l['pp_deleted'] = 'deleted';
$l['pp_inline_success'] = '{1} {2} successfully {3}';

// plugin requirements
$l['pp_folders_requirement_warning'] = 'One or more folders are not writable. These folders need to be writable during installation and upgrades for themeable items to be upgraded on a per-theme basis.<br /><strong>Folder(s):</strong><br />';
$l['pp_subfolders_unwritable'] = 'One or more subfolders in <span style="font-family: Courier New; font-weight: bolder; font-size: small; color: black;">{1}</span>';
$l['pp_cannot_be_installed'] = 'Picture Perfect cannot be installed!';
$l['pp_select_all'] = 'Select All';
$l['pp_rehost'] = 'Rehost';
$l['pp_create_thumbnails'] = 'Create Thumbnails';
$l['pp_rehosted'] = 'Hosted locally';

?>
