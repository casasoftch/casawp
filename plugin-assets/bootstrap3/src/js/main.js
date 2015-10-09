(function () {
	"use strict";

	var $ = jQuery;
	$(document).ready(function(){	

		//simple alternative lightbox for casasync (turn off Feather Light within the backend to avoid conflicts)
		//$('.property-image-gallery').fancybox();

		//fancybox implementation example for all images (you may turn this off and activate the above statement instead if only the plugin should be targeted)
		jQuery.fn.getTitle = function() { // Copy the title of every IMG tag and add it to its parent A so that fancybox can show titles
			var arr = jQuery("a.fancybox");
			jQuery.each(arr, function() {
				var title = jQuery(this).children("img").attr("title");
				jQuery(this).attr('title',title);
			});
		};

		var thumbnails = jQuery("a:has(img)").not(".nolightbox").filter( function() { return /\.(jpe?g|png|gif|bmp)$/i.test(jQuery(this).attr('href')) });
		thumbnails.addClass("fancybox").attr("rel","fancybox").getTitle();
		jQuery("a.fancybox").fancybox();

		//mobile header nav click toggles also
		$('.navbar-header').click(function(event) {
	    	if ($(event.target).hasClass('navbar-header') || $(event.target).hasClass('navbar-brand')) {
	    		$(this).find('.navbar-toggle').click();
	    	}
	    });


	});
 
})(jQuery);