/*
 * Plugin Name: Picture Perfect for MyBB 1.8.x
 * Copyright 2017 WildcardSearch
 * http://www.rantcentralforums.com
 *
 * image scan redirect
 */

var PP = (function($, p) {
	var options = {
		checkedSearch: false,
	},

	uncheckedImages = {},

	imageCount = 0;

	function init() {
		var $uncheckedImages = $("div.pp-image-unchecked");

		if ($uncheckedImages.length <= 0) {
			return;
		}

		$.each($uncheckedImages,
		function()
		{
			var url = this.dataset.url,
				id = this.dataset.imageid,
				el;

			imageCount++;

			el = $("<img/>")[0];
			el.dataset.imageid = id;
			$(el).on("load", loaded).on("error", failed).attr("src", url);
		});
	}

	function loaded()
	{
		var $this = $(this),
			secure = this.src.substr(0, 5) === "https" ? true : false,
			w, h, iTime;

		$this.css("left", "-1000px").appendTo($("body")[0]);

		w = $this.width();
		h = $this.height();

		$this.remove();

		uncheckedImages[this.dataset.imageid] = {
			width: w,
			height: h,
			secureimage: secure,
		};

		imageCount--;

		doCheck();
	}

	function failed()
	{
		uncheckedImages[this.dataset.imageid] = {
			width: 0,
			height: 0,
			deadimage: true,
		};

		imageCount--;

		doCheck();
	}

	function doCheck()
	{
		if (imageCount !== 0) {
			return;
		}

		sendData();
	}

	function sendData()
	{
		$.ajax({
			type: "post",
			url: "index.php",
			data: {
				module: "config-pp",
				action: "set_image_data",
				imagedata: uncheckedImages,
			},
			success: updateInfo,
		});
	}

	function updateInfo(success)
	{
		if (!success ||
			imageCount < 0 ||
			uncheckedImages.length === 0) {
			return;
		}

		$.each(uncheckedImages,
		function(id, v)
		{
			var $infoDiv = $("#image-standard-info-"+id),
				$advInfoDiv = $("#image-advanced-info-"+id),
				$status = $advInfoDiv.children("span.pp-image-status"),
				$security = $infoDiv.children("span.pp-image-security"),
				i = uncheckedImages[id],
				w = i.width ? i.width : "?",
				h = i.height ? i.height : "?";

			if ($advInfoDiv.length === 0) {
				return;
			}

			$advInfoDiv.children("span.pp-image-dimensions").html(w+"x"+h);

			if (i.deadimage) {
				$status.removeClass("pp-good-image");
				$status.addClass("pp-dead-image");
				$status.html("dead");
			} else {
				$status.addClass("pp-good-image");
				$status.removeClass("pp-dead-image");
				$status.html("good");
			}

			if (i.secureimage) {
				$security.removeClass("pp-image-http");
				$security.addClass("pp-image-https");
				$security.html("https");
			} else {
				$security.removeClass("pp-image-https");
				$security.addClass("pp-image-http");
				$security.html("http");
			}
		});

		if (options.checkedSearch) {
			window.location.reload(true);
		}
	}

	function setup(o)
	{
		options = $.extend(options, o || {});
	}
	
	$(init);

	p.setup = setup;

	return p;
})(jQuery, PP || {});
