(function() {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {
		const toggles = document.querySelectorAll('.angie-snippet-toggle-input');

		toggles.forEach(function(toggle) {
			toggle.addEventListener('change', function() {
				const postId = toggle.getAttribute('data-post-id');
				const isChecked = toggle.checked;

				toggle.disabled = true;

				const formData = new FormData();
				formData.append('action', 'angie_toggle_snippet_status');
				formData.append('nonce', angieListTableToggle.nonce);
				formData.append('post_id', postId);

				fetch(angieListTableToggle.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					return response.json();
				})
					.then(function(data) {
						if (data.success) {
							console.log('Status updated:', data.data.status);
						} else {
							alert(data.data.message || angieListTableToggle.i18n.failedToUpdateStatus);
							toggle.checked = !isChecked;
						}
					})
					.catch(function() {
						alert(angieListTableToggle.i18n.errorOccurred);
						toggle.checked = !isChecked;
					})
				.finally(function() {
					toggle.disabled = false;
				});
			});
		});

		const pushButtons = document.querySelectorAll('.angie-push-to-production');

		pushButtons.forEach(function(button) {
			button.addEventListener('click', function() {
				const postId = button.getAttribute('data-post-id');
				const buttonText = button.textContent.trim();
				const confirmMessage = buttonText === angieListTableToggle.i18n.publishToDevButton
					? angieListTableToggle.i18n.confirmPublishToDev
					: angieListTableToggle.i18n.confirmPushToProduction;

				if ( !confirm( confirmMessage ) ) {
					return;
				}

				button.disabled = true;
				const originalText = button.textContent;
				button.textContent = angieListTableToggle.i18n.pushing;

				const formData = new FormData();
				formData.append('action', 'angie_push_to_production');
				formData.append('nonce', angieListTableToggle.pushToProductionNonce);
				formData.append('post_id', postId);

				fetch(angieListTableToggle.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					return response.json();
				})
					.then( function( data ) {
						if ( data.success ) {
							alert( data.data.message || angieListTableToggle.i18n.successPushedToProduction );
							location.reload();
						} else {
							alert( data.data.message || angieListTableToggle.i18n.failedToPushToProduction );
						}
					} )
					.catch( function() {
						alert( angieListTableToggle.i18n.errorOccurred );
					} )
				.finally(function() {
					button.disabled = false;
					button.textContent = originalText;
				});
			});
		});
	});
})();
