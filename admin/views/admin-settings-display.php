<?php
/**
 * Admin settings page template.
 *
 * @package YDGL/Admin/Views
 */

defined( 'ABSPATH' ) || exit;

$ydgl_oauth = new YDGL_OAuth();
$ydgl_redirect_uri = $ydgl_oauth->get_redirect_uri();

// Retrieve logs if tab is Log.
$admin_helper = YDGL_Admin::get_instance();
$selected_log_file = isset( $_GET['log_file'] ) ? sanitize_key( $_GET['log_file'] ) : '';
$logs_data = $admin_helper->get_audit_logs( $selected_log_file );
?>

<style>
.ydgl-nav-tab {
	cursor: pointer;
}
.ydgl-log-console {
	background-color: #1e1e1e;
	color: #d4d4d4;
	font-family: 'Consolas', 'Courier New', monospace;
	padding: 20px;
	border-radius: 6px;
	height: 480px;
	overflow-y: auto;
	box-shadow: inset 0 2px 8px rgba(0,0,0,0.8);
	line-height: 1.6;
	font-size: 13px;
	margin-top: 15px;
}
.ydgl-log-line {
	margin-bottom: 8px;
	border-bottom: 1px solid #2a2a2a;
	padding-bottom: 6px;
}
.ydgl-log-line:last-child {
	margin-bottom: 0;
	border-bottom: none;
	padding-bottom: 0;
}
.ydgl-log-time {
	color: #858585;
	margin-right: 10px;
}
.ydgl-log-level {
	font-weight: bold;
	text-transform: uppercase;
	margin-right: 10px;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
	display: inline-block;
	min-width: 60px;
	text-align: center;
}
.ydgl-log-level-info {
	background-color: rgba(56, 178, 172, 0.2);
	color: #4fd1c5;
}
.ydgl-log-level-warning {
	background-color: rgba(221, 107, 32, 0.2);
	color: #f6ad55;
}
.ydgl-log-level-error, .ydgl-log-level-critical {
	background-color: rgba(229, 62, 62, 0.2);
	color: #fc8181;
}
.ydgl-log-msg {
	word-break: break-all;
}
.ydgl-log-empty {
	color: #8c8f94;
	text-align: center;
	padding: 80px 0;
	font-size: 15px;
}
</style>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p><?php esc_html_e( 'Configure Google OAuth 2.0 integration for secure WooCommerce customer login & registration.', 'yukdigitalz-login' ); ?></p>
	
	<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<?php
		$tabs = $admin_helper->get_settings_tabs();
		$is_first = true;
		foreach ( $tabs as $tab_id => $tab_title ) {
			$class = 'nav-tab ydgl-nav-tab' . ( $is_first ? ' nav-tab-active' : '' );
			echo '<a href="#' . esc_attr( $tab_id ) . '" class="' . esc_attr( $class ) . '" id="ydgl-tab-nav-' . esc_attr( $tab_id ) . '">' . esc_html( $tab_title ) . '</a>';
			$is_first = false;
		}
		?>
	</h2>

	<!-- TAB: SETTINGS -->
	<div id="ydgl-tab-settings" class="ydgl-tab-content">
		<div class="card" style="max-width: 800px; margin-top: 10px; padding: 20px; border-left: 4px solid #4285F4; box-shadow: 0 1px 3px rgba(0,0,0,0.13);">
			<h2 style="margin-top:0; color:#4285F4;"><?php esc_html_e( 'Google API Console Guide', 'yukdigitalz-login' ); ?></h2>
			<ol style="margin-bottom:0; line-height: 1.5;">
				<li><?php echo wp_kses_post( __( 'Open the <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a> and create a new project.', 'yukdigitalz-login' ) ); ?></li>
				<li><?php esc_html_e( 'Navigate to APIs & Services > Credentials.', 'yukdigitalz-login' ); ?></li>
				<li><?php esc_html_e( 'Create a new OAuth client ID with Application Type: "Web application".', 'yukdigitalz-login' ); ?></li>
				<li><?php esc_html_e( 'Add the following callback URL to the Authorized redirect URIs field:', 'yukdigitalz-login' ); ?>
					<br />
					<code style="display:inline-block; margin: 8px 0; padding: 6px 12px; background:#f0f0f1; border:1px solid #dcdcde; border-radius:4px; font-weight:bold; font-size:12px; user-select:all;"><?php echo esc_html( $ydgl_redirect_uri ); ?></code>
				</li>
				<li><?php esc_html_e( 'Copy the Client ID and Client Secret, then enter them in the form below.', 'yukdigitalz-login' ); ?></li>
			</ol>
		</div>

		<form method="post" action="options.php" style="max-width: 800px; margin-top: 20px;">
			<?php
			settings_fields( 'ydgl-settings-group' );
			do_settings_sections( 'ydgl-settings-group' );
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Integration Status', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_enabled">
							<input type="checkbox" id="ydgl_enabled" name="ydgl_enabled" value="yes" <?php checked( get_option( 'ydgl_enabled' ), 'yes' ); ?> />
							<?php esc_html_e( 'Enable Google Login & Registration', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_client_id"><?php esc_html_e( 'Google Client ID', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_client_id" name="ydgl_client_id" value="<?php echo esc_attr( get_option( 'ydgl_client_id' ) ); ?>" class="regular-text" placeholder="xxxxxx.apps.googleusercontent.com" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_client_secret"><?php esc_html_e( 'Google Client Secret', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="password" id="ydgl_client_secret" name="ydgl_client_secret" value="<?php echo esc_attr( YDGL_Security::decrypt( get_option( 'ydgl_client_secret' ) ) ); ?>" class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Secure Google API client secret key.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Allow New Registrations', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_enable_registration">
							<input type="checkbox" id="ydgl_enable_registration" name="ydgl_enable_registration" value="yes" <?php checked( get_option( 'ydgl_enable_registration', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Automatically create a new WooCommerce account if the email is not registered', 'yukdigitalz-login' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Note: Registration must also be enabled in WordPress or WooCommerce general settings.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Secure Account Linking (Anti-Hijacking)', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_verify_account_link">
							<input type="checkbox" id="ydgl_verify_account_link" name="ydgl_verify_account_link" value="yes" <?php checked( get_option( 'ydgl_verify_account_link', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Require email verification before linking Google login to an existing manual account', 'yukdigitalz-login' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Highly recommended. Prevents unauthorized account takeover if someone attempts to login via Google with an existing account email address.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_domain_restriction"><?php esc_html_e( 'Domain Restriction (Enterprise)', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<textarea id="ydgl_domain_restriction" name="ydgl_domain_restriction" rows="3" class="large-text" placeholder="company.com, office.co.id"><?php echo esc_textarea( get_option( 'ydgl_domain_restriction' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Leave empty to allow all email domains (like gmail.com). Enter comma-separated domains if you want to restrict login/registration to specific corporate emails only.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<!-- Button Text Customizations -->
				<tr style="border-top: 1px solid #dcdcde;">
					<th scope="row"><label for="ydgl_button_text"><?php esc_html_e( 'Google Button Text', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_button_text" name="ydgl_button_text" value="<?php echo esc_attr( get_option( 'ydgl_button_text', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Sign in with Google', 'yukdigitalz-login' ); ?>" />
						<p class="description"><?php esc_html_e( 'Custom text for the login/registration buttons.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_checkout_button_text"><?php esc_html_e( 'Checkout Promo Button Text', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_checkout_button_text" name="ydgl_checkout_button_text" value="<?php echo esc_attr( get_option( 'ydgl_checkout_button_text', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Buy via Google', 'yukdigitalz-login' ); ?>" />
						<p class="description"><?php esc_html_e( 'Custom text for the Google Login button inside the express checkout banner.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<!-- Redirect Options -->
				<tr style="border-top: 1px solid #dcdcde;">
					<th scope="row"><label for="ydgl_login_redirect_type"><?php esc_html_e( 'Post-Login Redirect', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<select id="ydgl_login_redirect_type" name="ydgl_login_redirect_type">
							<option value="default" <?php selected( get_option( 'ydgl_login_redirect_type', 'default' ), 'default' ); ?>><?php esc_html_e( 'Default (Return to Same Page / My Account)', 'yukdigitalz-login' ); ?></option>
							<option value="custom" <?php selected( get_option( 'ydgl_login_redirect_type' ), 'custom' ); ?>><?php esc_html_e( 'Custom URL Destination', 'yukdigitalz-login' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Choose where users are redirected after successful Google authentication.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<tr id="ydgl_redirect_url_row">
					<th scope="row"><label for="ydgl_login_redirect_url"><?php esc_html_e( 'Custom Redirect URL', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_login_redirect_url" name="ydgl_login_redirect_url" value="<?php echo esc_attr( get_option( 'ydgl_login_redirect_url', '' ) ); ?>" class="regular-text" placeholder="/my-custom-page" />
						<p class="description"><?php esc_html_e( 'Enter a relative path (e.g. /shop) or full URL (e.g. https://yoursite.com/welcome) to redirect users to.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>

	<!-- TAB: AUDIT LOGS -->
	<div id="ydgl-tab-logs" class="ydgl-tab-content" style="display: none;">
		<div style="max-width: 900px; margin-top: 10px;">
			<div class="tablenav top" style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<label for="ydgl_log_file_select" style="font-weight: bold; margin-right: 10px;"><?php esc_html_e( 'Select Log File:', 'yukdigitalz-login' ); ?></label>
					<?php if ( ! empty( $logs_data['files'] ) ) : ?>
						<select id="ydgl_log_file_select" style="min-width: 320px;">
							<?php foreach ( $logs_data['files'] as $file ) : ?>
								<option value="<?php echo esc_attr( $file ); ?>" <?php selected( $logs_data['current_file'], $file ); ?>>
									<?php echo esc_html( $file ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<select disabled><option><?php esc_html_e( 'No log files found', 'yukdigitalz-login' ); ?></option></select>
					<?php endif; ?>
				</div>
				<div>
					<button type="button" class="button" onclick="window.location.reload();"><?php esc_html_e( 'Refresh Logs', 'yukdigitalz-login' ); ?></button>
				</div>
			</div>

			<div class="ydgl-log-console">
				<?php if ( ! empty( $logs_data['entries'] ) ) : ?>
					<?php foreach ( $logs_data['entries'] as $entry ) : ?>
						<div class="ydgl-log-line">
							<?php if ( ! empty( $entry['timestamp'] ) ) : ?>
								<span class="ydgl-log-time">[<?php echo esc_html( $entry['timestamp'] ); ?>]</span>
							<?php endif; ?>
							<span class="ydgl-log-level ydgl-log-level-<?php echo esc_attr( $entry['level'] ); ?>">
								<?php echo esc_html( $entry['level'] ); ?>
							</span>
							<span class="ydgl-log-msg"><?php echo esc_html( $entry['message'] ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="ydgl-log-empty">
						<?php esc_html_e( 'No log entries found in this log file.', 'yukdigitalz-login' ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- TAB: CUSTOM TABS REGISTERED VIA PRO -->
	<?php
	foreach ( $tabs as $tab_id => $tab_title ) {
		if ( in_array( $tab_id, array( 'settings', 'logs' ), true ) ) {
			continue;
		}
		?>
		<div id="ydgl-tab-<?php echo esc_attr( $tab_id ); ?>" class="ydgl-tab-content" style="display: none;">
			<?php do_action( 'ydgl_settings_tab_content_' . $tab_id ); ?>
		</div>
		<?php
	}
	?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const tabs = document.querySelectorAll('.ydgl-nav-tab');
	const contents = document.querySelectorAll('.ydgl-tab-content');

	// Function to switch tab
	function switchTab(targetId) {
		tabs.forEach(t => {
			if (t.getAttribute('href') === '#' + targetId) {
				t.classList.add('nav-tab-active');
			} else {
				t.classList.remove('nav-tab-active');
			}
		});

		contents.forEach(c => {
			if (c.id === 'ydgl-tab-' + targetId) {
				c.style.display = 'block';
			} else {
				c.style.display = 'none';
			}
		});

		// Update hash in URL without jumping
		if (history.pushState) {
			history.pushState(null, null, '#' + targetId);
		} else {
			window.location.hash = targetId;
		}
	}

	// Bind click events
	tabs.forEach(tab => {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			const targetId = this.getAttribute('href').substring(1);
			switchTab(targetId);
		});
	});

	// Check url hash or parameter
	let defaultTab = 'settings';
	const hash = window.location.hash.substring(1);
	const urlParams = new URLSearchParams(window.location.search);
	const tabParam = urlParams.get('tab');
	const allowedTabs = <?php echo json_encode( array_keys( $tabs ) ); ?>;

	if (hash && allowedTabs.includes(hash)) {
		defaultTab = hash;
	} else if (tabParam && allowedTabs.includes(tabParam)) {
		defaultTab = tabParam;
	}

	switchTab(defaultTab);

	// Toggle custom redirect URL visibility
	const redirectType = document.getElementById('ydgl_login_redirect_type');
	const redirectUrlRow = document.getElementById('ydgl_redirect_url_row');

	function toggleRedirectUrl() {
		if (redirectType.value === 'custom') {
			redirectUrlRow.style.display = '';
		} else {
			redirectUrlRow.style.display = 'none';
		}
	}

	if (redirectType && redirectUrlRow) {
		redirectType.addEventListener('change', toggleRedirectUrl);
		toggleRedirectUrl(); // Run on init
	}

	// Log file dropdown navigation
	const logFileSelect = document.getElementById('ydgl_log_file_select');
	if (logFileSelect) {
		logFileSelect.addEventListener('change', function() {
			const file = this.value;
			const currentUrl = new URL(window.location.href);
			currentUrl.searchParams.set('tab', 'logs');
			currentUrl.searchParams.set('log_file', file);
			window.location.href = currentUrl.toString();
		});
	}
});
</script>
