/**
 * Rating System Pro – Admin JS
 * Initialises the WordPress colour pickers on the settings page.
 */
(function ($) {
	'use strict';

	$(function () {

		// Colour pickers
		$('.rsp-color-picker').wpColorPicker({
			change: function () {
				// Live preview could be wired here
			}
		});

		// Auto-compute the meta box preview when star inputs change
		$('.rsp-star-input').on('input change', function () {
			var total    = 0;
			var weighted = 0;

			$('.rsp-star-input').each(function () {
				var $el  = $(this);
				var star  = parseInt($el.attr('id').replace('rsp_stars_', ''), 10);
				var count = parseInt($el.val(), 10) || 0;
				total    += count;
				weighted += star * count;
			});

			var average = total > 0 ? (weighted / total).toFixed(1) : '0.0';

			$('.rsp-meta-average').text(average);
			$('.rsp-meta-total').text(total.toLocaleString());
		});

	});

}(jQuery));
