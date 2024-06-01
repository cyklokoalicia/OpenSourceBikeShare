/*!
* viewportDetect Plugin
* v1.0 [jj 13Dec18]
*
* returns the current viewport ("xs", "sm", "md", or "lg")
* $.viewportDetect()
*
* create a callback function that is called whenever the viewport changes
* $.viewportDetect(function(currentViewport, previousViewport) { });
*/

$(document).ready(function () {
	// create some unobtrusive dom that bootstrap will show and hide depending on its media queries
	$("body").append("<div id=\"viewportDetect\"><div class=\"visible-xs\" data-viewport=\"xs\"></div><div class=\"visible-sm\" data-viewport=\"sm\"></div><div class=\"visible-md\" data-viewport=\"md\"></div><div class=\"visible-lg\" data-viewport=\"lg\"></div></div>");

	/* examples
	// simply log the current viewport size every two seconds
	setInterval(function () {
		console.log("interval: " + $.viewportDetect());	},
	2000);

	// a callback fn that gets called whenever the viewport changes
	$.viewportDetect(function (vp, prevVp) {
		console.log("onChange: " + prevVp + " -> " + vp);
	});
	*/
});

(function ($) {
	var _currentViewport = "";

	$.viewportDetect = function(onChange) {
		if (arguments.length == 0) {
			return $("#viewportDetect div:visible").data("viewport");
		}

		// onChange is a function we want to call whenever the viewport changes
		$(window).resize(function () {
			var viewport = $("#viewportDetect div:visible").data("viewport");
			if (_currentViewport == "") _currentViewport = viewport; // the first change is not really a change at all

			if (_currentViewport != viewport) {
				var prevViewport = _currentViewport;
				_currentViewport = viewport;
				onChange(_currentViewport, prevViewport);
			}
		});
	};

})(jQuery);
