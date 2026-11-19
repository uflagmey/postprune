/**
 * Post Prune: "Calculate dry run now" in ACP > Forums > Edit forum.
 * The link is ajaxified by phpBB (data-ajax); the result goes into the block,
 * the forum form with any unsaved changes stays as it is.
 */
(function($) {
	'use strict';

	phpbb.addAjaxCallback('uflagmey_pp_preview', function(res) {
		if (res && res.UFLAGMEY_PP_PREVIEW) {
			$('#uflagmey_pp_preview_result').html(res.UFLAGMEY_PP_PREVIEW).prop('hidden', false);
		}
	});
})(jQuery);
