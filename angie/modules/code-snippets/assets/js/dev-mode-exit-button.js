(function() {
	var exitButton = document.getElementById('angie-dev-mode-exit-button');
	var tooltip = document.getElementById('angie-dev-mode-tooltip');
	var infoIcon = exitButton ? exitButton.querySelector('.angie-info-icon') : null;
	var exitIcon = exitButton ? exitButton.querySelector('.angie-exit-icon') : null;

	if (!exitButton) return;

	// Show tooltip on hover over the info icon
	if (infoIcon && tooltip) {
		infoIcon.addEventListener('mouseenter', function() {
			tooltip.classList.remove('angie-tooltip-hidden');
		});

		infoIcon.addEventListener('mouseleave', function() {
			tooltip.classList.add('angie-tooltip-hidden');
		});
	}

	// Exit dev mode when clicking the exit icon
	if (exitIcon) {
		exitIcon.addEventListener('click', function(e) {
			e.stopPropagation();
			exitDevMode();
		});
	}

	function exitDevMode() {
		exitButton.disabled = true;
		exitButton.querySelector('span:nth-child(2)').textContent = window.angieDevModeExit.exitingText;

		var xhr = new XMLHttpRequest();
		xhr.open('POST', window.angieDevModeExit.restUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('X-WP-Nonce', window.angieDevModeExit.nonce);

		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					var response = JSON.parse(xhr.responseText);
					if (response.success) {
						location.reload();
					} else {
						alert(response.message || window.angieDevModeExit.errorText);
						exitButton.disabled = false;
						exitButton.querySelector('span:nth-child(2)').textContent = window.angieDevModeExit.buttonText;
					}
				} else {
					alert(window.angieDevModeExit.requestFailedText);
					exitButton.disabled = false;
					exitButton.querySelector('span:nth-child(2)').textContent = window.angieDevModeExit.buttonText;
				}
			}
		};

		xhr.send(JSON.stringify({ enabled: false }));
	}
})();
