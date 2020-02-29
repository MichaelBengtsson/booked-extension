jQuery(function($) {
	const bookedAddon = {

		saveNote: function( e ) {
			let postId = $(e.target).data( 'post-id' );
			if ( postId ) {
				$('span.appt-block[data-appt-id="' + postId + '"]')
					.block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
				let values = {};
				let formId = '#booked-discrepancy-' + postId;
				$.each($(formId).serializeArray(), function(i, field) {
					values[field.name] = field.value;
				});
				$.ajax(
					bookedParams.ajaxurl,
					{
						type: 'POST',
						dataType: 'json',
						data: {
							action  : 'booked_save_discrepency',
							id : postId,
							values : values
						},
						complete: function(response) {
							$('span.appt-block[data-appt-id="' + postId + '"]').unblock();
						}
					}
				);
			}
		},

		init: function() {
			$('body').on('click', '.booked-discrepancy-save', bookedAddon.saveNote);
		},
	}
	bookedAddon.init();
});