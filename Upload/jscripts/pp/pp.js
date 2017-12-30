/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2017 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * image scan redirect
 */

var pp = (function($, i) {
	var buttonText = "Automatically Redirecting...";

	function init() {
		$("#analyze_submit").val(buttonText).attr("disabled", true);
		$("form")[0].submit();
	}

	function setup(language) {
		if (language) {
			buttonText = language;
		}
	}

	$(init);

	i.setup = setup;
	return i;
})(jQuery, pp || {});
