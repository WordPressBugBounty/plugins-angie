(function($) {
	$(document).ready(function() {
		$('#angie-enable-dev-mode').on('click', function() {
			var button = $(this);
			button.prop('disabled', true).text('Enabling...');

			$.ajax({
				url: window.angieDevMode.restUrl,
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.angieDevMode.nonce);
				},
				data: JSON.stringify({ enabled: true }),
				contentType: 'application/json',
				dataType: 'json'
			}).done(function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.message || 'An error occurred.');
					button.prop('disabled', false).text('Enable Dev Mode');
				}
			}).fail(function() {
				alert('Request failed.');
				button.prop('disabled', false).text('Enable Dev Mode');
			});
		});

		$('#angie-disable-dev-mode').on('click', function() {
			var button = $(this);
			button.prop('disabled', true).text('Disabling...');

			$.ajax({
				url: window.angieDevMode.restUrl,
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', window.angieDevMode.nonce);
				},
				data: JSON.stringify({ enabled: false }),
				contentType: 'application/json',
				dataType: 'json'
			}).done(function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.message || 'An error occurred.');
					button.prop('disabled', false).text('Disable Dev Mode');
				}
			}).fail(function() {
				alert('Request failed.');
				button.prop('disabled', false).text('Disable Dev Mode');
			});
		});
	});
})(jQuery);
