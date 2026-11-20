/**
 * Post Prune: "Calculate dry run now" in ACP > Forums > Edit forum.
 * With JavaScript the button sends the values currently in the block's fields
 * (POST, AJAX); the server checks them with the same rules as saving and counts
 * with them. The forum form with its unsaved changes stays as it is.
 * Without JavaScript the link reloads the page and the saved settings are used.
 */
(function($) {
	'use strict';

	// Field names only; every rule is checked on the server.
	var fields = ['uflagmey_pp_scope', 'uflagmey_pp_rule', 'uflagmey_pp_days', 'uflagmey_pp_keep_count', 'uflagmey_pp_keep_first', 'uflagmey_pp_skip_sticky'];

	function show(html) {
		$('#uflagmey_pp_preview_result').html(html).prop('hidden', false).css('display', 'block');
	}

	$(function() {
		$('#uflagmey_pp_preview_button').on('click', function(e) {
			var $form = $(this).closest('form'),
				data = {uflagmey_pp_preview: 1, uflagmey_pp_form: 1};

			e.preventDefault();
			$.each(fields, function(i, name) {
				var $field = $form.find('[name="' + name + '"]');
				if ($field.is(':radio')) {
					$field = $field.filter(':checked');
				}
				if ($field.length) {
					data[name] = $field.val();
				}
			});

			var $indicator = phpbb.loadingIndicator();
			$.ajax({url: this.href, type: 'POST', data: data, dataType: 'json'})
				.done(function(res) {
					show(res && res.success ? res.UFLAGMEY_PP_PREVIEW : (res && res.MESSAGE_TEXT) || '');
				})
				.fail(function(xhr) {
					var res = xhr.responseJSON;
					show((res && res.MESSAGE_TEXT) || (xhr.status + ' ' + xhr.statusText));
				})
				.always(function() {
					if ($indicator) {
						$indicator.fadeOut(phpbb.alertTime);
					}
				});
		});
	});
})(jQuery);
