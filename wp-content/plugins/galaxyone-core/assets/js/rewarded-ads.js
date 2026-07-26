(() => {
	'use strict';

	const config = window.galaxyoneRewardedAds;

	if (
		!config ||
		typeof config !== 'object' ||
		typeof config.ajaxUrl !== 'string' ||
		'' === config.ajaxUrl.trim() ||
		typeof config.nonce !== 'string' ||
		'' === config.nonce.trim()
	) {
		return;
	}

	const rewardTokens = new WeakMap();
	const inFlightContainers = new WeakSet();

	const getSuccessData = (result) => {
		if (
			!result ||
			typeof result !== 'object' ||
			true !== result.success ||
			!result.data ||
			typeof result.data !== 'object'
		) {
			return null;
		}

		return result.data;
	};

	const request = async (action, payload) => {
		const body = new URLSearchParams({
			action,
			nonce: config.nonce,
			...payload,
		});

		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			});

			if (!response.ok) {
				return null;
			}

			const result = await response.json();

			return getSuccessData(result);
		} catch {
			return null;
		}
	};

	const setStatus = (status, message) => {
		if (status) {
			status.textContent = message;
		}
	};

	document.addEventListener('click', async (event) => {
		const startButton = event.target.closest('.galaxyone-rewarded-offer__start');
		const completeButton = event.target.closest('.galaxyone-rewarded-offer__complete');

		if (!startButton && !completeButton) {
			return;
		}

		const button = startButton || completeButton;
		const container = button.closest('.galaxyone-rewarded-offer');
		const status = container
			? container.querySelector('.galaxyone-rewarded-offer__status')
			: null;

		if (!container || !status || inFlightContainers.has(container)) {
			return;
		}

		if (startButton) {
			const productId = container.dataset.productId;

			if (!productId) {
				return;
			}

			inFlightContainers.add(container);
			startButton.disabled = true;
			setStatus(status, 'Starting optional reward…');

			const result = await request('galaxyone_start_reward', {
				product_id: productId,
			});

			inFlightContainers.delete(container);

			if (
				!result ||
				typeof result.event_token !== 'string' ||
				'' === result.event_token.trim()
			) {
				setStatus(status, 'The optional reward could not be started. Please continue at the current price.');
				startButton.disabled = false;
				return;
			}

			const complete = document.createElement('button');
			complete.type = 'button';
			complete.className = 'galaxyone-button galaxyone-rewarded-offer__complete';
			complete.textContent = 'Complete staging reward';

			rewardTokens.set(complete, result.event_token);
			setStatus(status, 'The optional reward is ready for completion.');
			startButton.replaceWith(complete);

			return;
		}

		const eventToken = rewardTokens.get(completeButton);

		if (typeof eventToken !== 'string' || '' === eventToken.trim()) {
			return;
		}

		inFlightContainers.add(container);
		completeButton.disabled = true;
		setStatus(status, 'Verifying reward completion…');

		const result = await request('galaxyone_complete_reward', {
			event_token: eventToken,
		});

		inFlightContainers.delete(container);

		if (!result) {
			setStatus(status, 'The optional reward could not be verified. Please continue at the current price.');
			completeButton.disabled = false;
			return;
		}

		setStatus(status, 'Your optional rewarded offer is unlocked.');
		rewardTokens.delete(completeButton);
		completeButton.remove();

		window.dispatchEvent(new Event('update_checkout'));
	});
})();
