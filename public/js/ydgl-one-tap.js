/**
 * Google One Tap Sign-In integration for WooCommerce.
 */
(function () {
	'use strict';

	if (!window.ydglOneTap || !window.ydglOneTap.clientId) {
		return;
	}

	function setSkipCookie() {
		var date = new Date();
		date.setTime(date.getTime() + (24 * 60 * 60 * 1000));
		document.cookie = 'ydgl_one_tap_skip=1; expires=' + date.toUTCString() + '; path=/; SameSite=Lax';
	}

	function hasSkipCookie() {
		return document.cookie.indexOf('ydgl_one_tap_skip=1') !== -1;
	}

	function handleCredentialResponse(response) {
		if (!response || !response.credential) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'ydgl_one_tap_login');
		formData.append('nonce', window.ydglOneTap.nonce);
		formData.append('credential', response.credential);
		formData.append('redirect', window.ydglOneTap.redirectUrl || '');

		fetch(window.ydglOneTap.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (result) {
				if (result && result.success && result.data && result.data.redirect) {
					window.location.href = result.data.redirect;
					return;
				}
				setSkipCookie();
				if (result && result.data && result.data.code && window.ydglOneTap.myAccount) {
					var errUrl = new URL(window.ydglOneTap.myAccount);
					errUrl.searchParams.set('ydgl_error', result.data.code);
					window.location.href = errUrl.toString();
				}
			})
			.catch(function () {
				// Silently ignore network errors so the user can still use the manual button.
			});
	}

	function initOneTap() {
		if (hasSkipCookie()) {
			return;
		}

		if (!window.google || !window.google.accounts || !window.google.accounts.id) {
			return;
		}

		window.google.accounts.id.initialize({
			client_id: window.ydglOneTap.clientId,
			callback: handleCredentialResponse,
			auto_select: Boolean(window.ydglOneTap.autoSelect),
			cancel_on_tap_outside: true
		});

		window.google.accounts.id.prompt();
	}

	if (document.readyState === 'complete') {
		initOneTap();
	} else {
		window.addEventListener('load', initOneTap);
	}
})();
