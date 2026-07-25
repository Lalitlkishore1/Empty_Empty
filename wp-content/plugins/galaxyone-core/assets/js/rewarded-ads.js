(() => {
	'use strict';

	const config = window.galaxyoneRewardedAds;

	if (!config) {
		return;
	}

	const request = async (action, payload) => {
		const body = new URLSearchParams({
			action,
			nonce: config.nonce,
			...payload,
		});

		const response = await fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		});

		return response.json();
	};

	document.addEventListener('click', async (event) => {
		const startButton = event.target.closest('.galaxyone-rewarded-offer__start');
		const completeButton = event.target.closest('.galaxyone-rewarded-offer__complete');

		if (!startButton && !completeButton) {
			return;
		}

		const container = event.target.closest('.galaxyone-rewarded-offer');

		if (!container) {
			return;
		}

		const status = container.querySelector('.galaxyone-rewarded-offer__status');

		try {
			if (startButton) {
				startButton.disabled = true;
				status.textContent = 'Starting optional reward…';

				const result = await request('galaxyone_start_reward', {
					product_id: container.dataset.productId,
				});

				if (!result.success) {
					throw new Error(result.data.message);
				}

				status.textContent = result.data.message;

				const complete = document.createElement('button');
				complete.type = 'button';
				complete.className = 'galaxyone-button galaxyone-rewarded-offer__complete';
				complete.dataset.eventToken = result.data.event_token;
				complete.textContent = 'Complete staging reward';

				startButton.replaceWith(complete);
				return;
			}

			completeButton.disabled = true;
			status.textContent = 'Verifying reward completion…';

			const result = await request('galaxyone_complete_reward', {
				event_token: completeButton.dataset.eventToken,
			});

			if (!result.success) {
				throw new Error(result.data.message);
			}

			status.textContent = result.data.message;
			completeButton.remove();

			window.dispatchEvent(new Event('update_checkout'));
		} catch (error) {
			status.textContent = error.message || 'The optional reward could not be completed.';
			if (startButton) {
				startButton.disabled = false;
			}

			if (completeButton) {
				completeButton.disabled = false;
			}
		}
	});
})();
