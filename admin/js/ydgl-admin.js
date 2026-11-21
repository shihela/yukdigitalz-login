/**
 * Admin Settings Page Controller for YukDigitalz Login.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var tabs = document.querySelectorAll('.ydgl-nav-tab');
		var contents = document.querySelectorAll('.ydgl-tab-content');
		var allowedTabs = (window.ydglAdmin && Array.isArray(window.ydglAdmin.tabs)) ? window.ydglAdmin.tabs : ['settings', 'logs'];

		function switchTab(targetId) {
			tabs.forEach(function (t) {
				if (t.getAttribute('href') === '#' + targetId) {
					t.classList.add('nav-tab-active');
				} else {
					t.classList.remove('nav-tab-active');
				}
			});

			contents.forEach(function (c) {
				c.style.display = (c.id === 'ydgl-tab-' + targetId) ? 'block' : 'none';
			});

			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', '#' + targetId);
			}
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function (e) {
				e.preventDefault();
				var targetId = this.getAttribute('href').substring(1);
				switchTab(targetId);
			});
		});

		var defaultTab = 'settings';
		var hash = window.location.hash.substring(1);
		var urlParams = new URLSearchParams(window.location.search);
		var tabParam = urlParams.get('tab');

		if (hash && allowedTabs.indexOf(hash) !== -1) {
			defaultTab = hash;
		} else if (tabParam && allowedTabs.indexOf(tabParam) !== -1) {
			defaultTab = tabParam;
		}

		switchTab(defaultTab);

		// Toggle custom redirect URL row visibility.
		var redirectType = document.getElementById('ydgl_login_redirect_type');
		var redirectUrlRow = document.getElementById('ydgl_redirect_url_row');

		function toggleRedirectUrl() {
			if (!redirectType || !redirectUrlRow) {
				return;
			}
			redirectUrlRow.style.display = (redirectType.value === 'custom') ? '' : 'none';
		}

		if (redirectType && redirectUrlRow) {
			redirectType.addEventListener('change', toggleRedirectUrl);
			toggleRedirectUrl();
		}

		// Toggle checkout promo sub-options visibility.
		var promoEnabled = document.getElementById('ydgl_checkout_promo_enabled');
		var promoSubRows = document.querySelectorAll('.ydgl-checkout-promo-row');

		function togglePromoRows() {
			if (!promoEnabled) {
				return;
			}
			var show = promoEnabled.checked;
			promoSubRows.forEach(function (row) {
				row.style.display = show ? '' : 'none';
			});
		}

		if (promoEnabled && promoSubRows.length) {
			promoEnabled.addEventListener('change', togglePromoRows);
			togglePromoRows();
		}

		// Toggle One Tap sub-options visibility.
		var oneTapEnabled = document.getElementById('ydgl_enable_one_tap');
		var oneTapSubRows = document.querySelectorAll('.ydgl-one-tap-sub-row');

		function toggleOneTapRows() {
			if (!oneTapEnabled) {
				return;
			}
			var show = oneTapEnabled.checked;
			oneTapSubRows.forEach(function (row) {
				row.style.display = show ? '' : 'none';
			});
		}

		if (oneTapEnabled && oneTapSubRows.length) {
			oneTapEnabled.addEventListener('change', toggleOneTapRows);
			toggleOneTapRows();
		}

		// Dynamic Domain Role Mapping table builder (vanilla JS).
		var roleInput = document.getElementById('ydgl_role_mapping');
		var roleTableBody = document.querySelector('#ydgl_role_mapping_table tbody');
		var domainInput = document.getElementById('ydgl_role_domain_input');
		var roleSelect = document.getElementById('ydgl_role_select_input');
		var addRuleBtn = document.getElementById('ydgl_role_add_btn');

		if (roleInput && roleTableBody && domainInput && roleSelect && addRuleBtn) {
			var rules = [];
			try {
				var parsed = JSON.parse(roleInput.value || '[]');
				if (Array.isArray(parsed)) {
					rules = parsed;
				}
			} catch (err) {
				rules = [];
			}

			function getRoleLabel(slug) {
				var opt = roleSelect.querySelector('option[value="' + slug.replace(/"/g, '') + '"]');
				return opt ? opt.textContent : slug;
			}

			function renderRules() {
				roleTableBody.innerHTML = '';
				roleInput.value = JSON.stringify(rules);

				if (!rules.length) {
					var emptyRow = document.createElement('tr');
					var emptyCell = document.createElement('td');
					emptyCell.colSpan = 3;
					emptyCell.className = 'ydgl-role-empty';
					emptyCell.textContent = 'No domain role mapping rules configured.';
					emptyRow.appendChild(emptyCell);
					roleTableBody.appendChild(emptyRow);
					return;
				}

				rules.forEach(function (rule, idx) {
					var tr = document.createElement('tr');

					var tdDomain = document.createElement('td');
					tdDomain.textContent = '@' + rule.domain;

					var tdRole = document.createElement('td');
					tdRole.textContent = getRoleLabel(rule.role);

					var tdAct = document.createElement('td');
					var rmBtn = document.createElement('button');
					rmBtn.type = 'button';
					rmBtn.className = 'button button-small';
					rmBtn.textContent = 'Remove';
					rmBtn.addEventListener('click', function () {
						rules.splice(idx, 1);
						renderRules();
					});
					tdAct.appendChild(rmBtn);

					tr.appendChild(tdDomain);
					tr.appendChild(tdRole);
					tr.appendChild(tdAct);
					roleTableBody.appendChild(tr);
				});
			}

			addRuleBtn.addEventListener('click', function () {
				var domain = domainInput.value.trim().toLowerCase().replace(/^@+/, '');
				var role = roleSelect.value;
				if (!domain || !role) {
					return;
				}
				rules = rules.filter(function (r) {
					return r.domain !== domain;
				});
				rules.push({ domain: domain, role: role });
				domainInput.value = '';
				renderRules();
			});

			renderRules();
		}

		// Log file dropdown navigation.
		var logFileSelect = document.getElementById('ydgl_log_file_select');
		if (logFileSelect) {
			logFileSelect.addEventListener('change', function () {
				var currentUrl = new URL(window.location.href);
				currentUrl.searchParams.set('tab', 'logs');
				currentUrl.searchParams.set('log_file', this.value);
				window.location.href = currentUrl.toString();
			});
		}

		// Refresh logs button.
		var refreshBtn = document.getElementById('ydgl_refresh_logs_btn');
		if (refreshBtn) {
			refreshBtn.addEventListener('click', function () {
				window.location.reload();
			});
		}
	});
})();
