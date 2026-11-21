<?php
/**
 * Admin settings page template.
 *
 * @package YDGL/Admin/Views
 */

defined( 'ABSPATH' ) || exit;

$ydgl_oauth        = new YDGL_OAuth();
$ydgl_redirect_uri = $ydgl_oauth->get_redirect_uri();

$ydgl_admin_helper = YDGL_Admin::get_instance();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only log file filter validated against discovered log file keys.
$ydgl_selected_log = isset( $_GET['log_file'] ) ? sanitize_text_field( wp_unslash( $_GET['log_file'] ) ) : '';
$ydgl_logs_data    = $ydgl_admin_helper->get_audit_logs( $ydgl_selected_log );

$ydgl_stored_secret   = (string) get_option( 'ydgl_client_secret', '' );
$ydgl_has_secret      = '' !== $ydgl_stored_secret;
$ydgl_secret_valid    = $ydgl_has_secret && YDGL_Security::has_valid_secret( $ydgl_stored_secret );
$ydgl_assignable_roles = $ydgl_admin_helper->get_assignable_roles();
$ydgl_role_mapping    = (string) get_option( 'ydgl_role_mapping', '[]' );
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p><?php esc_html_e( 'Configure Google OAuth 2.0 integration for secure WooCommerce customer login & registration.', 'yukdigitalz-login' ); ?></p>

	<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<?php
		$ydgl_tabs     = $ydgl_admin_helper->get_settings_tabs();
		$ydgl_is_first = true;
		foreach ( $ydgl_tabs as $ydgl_tab_id => $ydgl_tab_title ) {
			$ydgl_class = 'nav-tab ydgl-nav-tab' . ( $ydgl_is_first ? ' nav-tab-active' : '' );
			echo '<a href="#' . esc_attr( $ydgl_tab_id ) . '" class="' . esc_attr( $ydgl_class ) . '" id="ydgl-tab-nav-' . esc_attr( $ydgl_tab_id ) . '">' . esc_html( $ydgl_tab_title ) . '</a>';
			$ydgl_is_first = false;
		}
		?>
	</h2>

	<!-- TAB: SETTINGS -->
	<div id="ydgl-tab-settings" class="ydgl-tab-content">
		<div class="card ydgl-guide-card">
			<h2><?php esc_html_e( 'Google API Console Guide', 'yukdigitalz-login' ); ?></h2>
			<ol>
				<li><?php echo wp_kses_post( __( 'Open the <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console</a> and create a new project.', 'yukdigitalz-login' ) ); ?></li>
				<li><?php esc_html_e( 'Navigate to APIs & Services > Credentials.', 'yukdigitalz-login' ); ?></li>
				<li><?php esc_html_e( 'Create a new OAuth client ID with Application Type: "Web application".', 'yukdigitalz-login' ); ?></li>
				<li><?php esc_html_e( 'Add your site domain under Authorized JavaScript origins (required if using Google One Tap).', 'yukdigitalz-login' ); ?></li>
				<li>
					<?php esc_html_e( 'Add the following callback URL to the Authorized redirect URIs field:', 'yukdigitalz-login' ); ?>
					<br />
					<code class="ydgl-redirect-uri-code"><?php echo esc_html( $ydgl_redirect_uri ); ?></code>
				</li>
				<li><?php esc_html_e( 'Copy the Client ID and Client Secret, then enter them in the form below.', 'yukdigitalz-login' ); ?></li>
			</ol>
		</div>

		<form method="post" action="options.php" class="ydgl-settings-form">
			<?php
			settings_fields( 'ydgl-settings-group' );
			do_settings_sections( 'ydgl-settings-group' );
			?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Integration Status', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_enabled">
							<input type="checkbox" id="ydgl_enabled" name="ydgl_enabled" value="yes" <?php checked( get_option( 'ydgl_enabled', 'no' ), 'yes' ); ?> />
							<?php esc_html_e( 'Enable Google Login & Registration', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_client_id"><?php esc_html_e( 'Google Client ID', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_client_id" name="ydgl_client_id" value="<?php echo esc_attr( get_option( 'ydgl_client_id', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your Google OAuth Client ID', 'yukdigitalz-login' ); ?>" />
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_client_secret"><?php esc_html_e( 'Google Client Secret', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="password" id="ydgl_client_secret" name="ydgl_client_secret" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $ydgl_has_secret ? esc_attr__( '•••••••••••••••• (saved — leave blank to keep)', 'yukdigitalz-login' ) : ''; ?>" />
						<?php if ( $ydgl_has_secret && $ydgl_secret_valid ) : ?>
							<span class="ydgl-secret-status ydgl-secret-status--saved"><?php esc_html_e( 'Encrypted & Saved', 'yukdigitalz-login' ); ?></span>
						<?php elseif ( $ydgl_has_secret && ! $ydgl_secret_valid ) : ?>
							<span class="ydgl-secret-status ydgl-secret-status--invalid"><?php esc_html_e( 'Re-enter Required (WP salts changed)', 'yukdigitalz-login' ); ?></span>
						<?php endif; ?>
						<p class="description">
							<?php esc_html_e( 'Encrypted with AES-256-GCM before saving. For security, the saved secret is never displayed back in HTML.', 'yukdigitalz-login' ); ?>
						</p>
						<?php if ( $ydgl_has_secret ) : ?>
							<label for="ydgl_clear_client_secret" style="display:inline-block; margin-top:6px;">
								<input type="checkbox" id="ydgl_clear_client_secret" name="ydgl_clear_client_secret" value="yes" />
								<?php esc_html_e( 'Clear saved Client Secret', 'yukdigitalz-login' ); ?>
							</label>
						<?php endif; ?>
					</td>
				</tr>

				<!-- Security & Access Policies -->
				<tr class="ydgl-section-divider">
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
					<th scope="row"><?php esc_html_e( 'Secure Account Linking', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_verify_account_link">
							<input type="checkbox" id="ydgl_verify_account_link" name="ydgl_verify_account_link" value="yes" <?php checked( get_option( 'ydgl_verify_account_link', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Require email verification before linking Google login to an existing manual account', 'yukdigitalz-login' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Recommended. Prevents unauthorized account linking when an email already exists.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Protect Administrator Accounts', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_block_admin_login">
							<input type="checkbox" id="ydgl_block_admin_login" name="ydgl_block_admin_login" value="yes" <?php checked( get_option( 'ydgl_block_admin_login', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Block Google Sign-In for administrator accounts (users with manage_options)', 'yukdigitalz-login' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Strongly recommended so administrators always sign in through WordPress password & 2FA.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_domain_restriction"><?php esc_html_e( 'Domain Restriction', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<textarea id="ydgl_domain_restriction" name="ydgl_domain_restriction" rows="3" class="large-text" placeholder="company.com, office.co.id"><?php echo esc_textarea( get_option( 'ydgl_domain_restriction', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Leave empty to allow all email domains. Enter comma-separated domains to restrict login/registration to specific corporate emails only.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Dynamic Domain Role Mapping', 'yukdigitalz-login' ); ?></th>
					<td>
						<input type="hidden" id="ydgl_role_mapping" name="ydgl_role_mapping" value="<?php echo esc_attr( $ydgl_role_mapping ); ?>" />
						<table class="widefat striped ydgl-role-table" id="ydgl_role_mapping_table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Email Domain', 'yukdigitalz-login' ); ?></th>
									<th><?php esc_html_e( 'Assigned Role', 'yukdigitalz-login' ); ?></th>
									<th style="width: 90px;"><?php esc_html_e( 'Action', 'yukdigitalz-login' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
						<div class="ydgl-role-add-bar">
							<input type="text" id="ydgl_role_domain_input" class="regular-text" placeholder="partner.com" />
							<select id="ydgl_role_select_input">
								<?php foreach ( $ydgl_assignable_roles as $ydgl_role_slug => $ydgl_role_label ) : ?>
									<option value="<?php echo esc_attr( $ydgl_role_slug ); ?>"><?php echo esc_html( $ydgl_role_label ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button" id="ydgl_role_add_btn"><?php esc_html_e( 'Add Rule', 'yukdigitalz-login' ); ?></button>
						</div>
						<p class="description">
							<?php esc_html_e( 'Automatically assign a specific non-privileged role (e.g. Wholesale Customer) to new users registering from a corporate/partner domain. Administrative roles are excluded for security.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Google Avatar Sync', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_avatar_sync">
							<input type="checkbox" id="ydgl_avatar_sync" name="ydgl_avatar_sync" value="yes" <?php checked( get_option( 'ydgl_avatar_sync', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Use the customer\'s Google profile photo as their WordPress/WooCommerce avatar', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<!-- Google One Tap -->
				<tr class="ydgl-section-divider">
					<th scope="row"><?php esc_html_e( 'Google One Tap Sign-In', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_enable_one_tap">
							<input type="checkbox" id="ydgl_enable_one_tap" name="ydgl_enable_one_tap" value="yes" <?php checked( get_option( 'ydgl_enable_one_tap', 'no' ), 'yes' ); ?> />
							<?php esc_html_e( 'Enable Google One Tap prompt for logged-out visitors on WooCommerce Account & Checkout pages', 'yukdigitalz-login' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allows one-click sign-in without leaving the Checkout page. Make sure your site origin is added under "Authorized JavaScript origins" in Google Cloud Console.', 'yukdigitalz-login' ); ?>
						</p>
					</td>
				</tr>

				<tr class="ydgl-one-tap-sub-row">
					<th scope="row"><?php esc_html_e( 'One Tap Silent Auto-Login', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_one_tap_auto_select">
							<input type="checkbox" id="ydgl_one_tap_auto_select" name="ydgl_one_tap_auto_select" value="yes" <?php checked( get_option( 'ydgl_one_tap_auto_select', 'no' ), 'yes' ); ?> />
							<?php esc_html_e( 'Automatically sign in returning customers who have already authorized Google on this site', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<!-- Button & Checkout Promo Customizations -->
				<tr class="ydgl-section-divider">
					<th scope="row"><label for="ydgl_button_theme"><?php esc_html_e( 'Google Button Theme', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<select id="ydgl_button_theme" name="ydgl_button_theme">
							<option value="light" <?php selected( get_option( 'ydgl_button_theme', 'light' ), 'light' ); ?>><?php esc_html_e( 'Light (Google Official Default)', 'yukdigitalz-login' ); ?></option>
							<option value="dark" <?php selected( get_option( 'ydgl_button_theme', 'light' ), 'dark' ); ?>><?php esc_html_e( 'Dark (Google Official Dark)', 'yukdigitalz-login' ); ?></option>
							<option value="neutral" <?php selected( get_option( 'ydgl_button_theme', 'light' ), 'neutral' ); ?>><?php esc_html_e( 'Neutral (Soft Gray)', 'yukdigitalz-login' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Complies with official Google Sign-In branding guidelines.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ydgl_button_text"><?php esc_html_e( 'Google Button Text', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_button_text" name="ydgl_button_text" value="<?php echo esc_attr( get_option( 'ydgl_button_text', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Sign in with Google', 'yukdigitalz-login' ); ?>" />
						<p class="description"><?php esc_html_e( 'Custom text for the login/registration buttons.', 'yukdigitalz-login' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Checkout Express Banner', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_checkout_promo_enabled">
							<input type="checkbox" id="ydgl_checkout_promo_enabled" name="ydgl_checkout_promo_enabled" value="yes" <?php checked( get_option( 'ydgl_checkout_promo_enabled', 'yes' ), 'yes' ); ?> />
							<?php esc_html_e( 'Show Express Google Login banner on WooCommerce Checkout (Classic & Block Checkout)', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<tr class="ydgl-checkout-promo-row">
					<th scope="row"><?php esc_html_e( 'Digital/Virtual Carts Only', 'yukdigitalz-login' ); ?></th>
					<td>
						<label for="ydgl_checkout_promo_digital_only">
							<input type="checkbox" id="ydgl_checkout_promo_digital_only" name="ydgl_checkout_promo_digital_only" value="yes" <?php checked( get_option( 'ydgl_checkout_promo_digital_only', 'no' ), 'yes' ); ?> />
							<?php esc_html_e( 'Only display the Checkout Express Banner when the cart contains virtual/digital products only', 'yukdigitalz-login' ); ?>
						</label>
					</td>
				</tr>

				<tr class="ydgl-checkout-promo-row">
					<th scope="row"><label for="ydgl_checkout_promo_title"><?php esc_html_e( 'Checkout Banner Title', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_checkout_promo_title" name="ydgl_checkout_promo_title" value="<?php echo esc_attr( get_option( 'ydgl_checkout_promo_title', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Express Checkout with Google', 'yukdigitalz-login' ); ?>" />
					</td>
				</tr>

				<tr class="ydgl-checkout-promo-row">
					<th scope="row"><label for="ydgl_checkout_promo_desc"><?php esc_html_e( 'Checkout Banner Description', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_checkout_promo_desc" name="ydgl_checkout_promo_desc" value="<?php echo esc_attr( get_option( 'ydgl_checkout_promo_desc', '' ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Sign in with one click to automatically fill in your name and email.', 'yukdigitalz-login' ); ?>" />
					</td>
				</tr>

				<tr class="ydgl-checkout-promo-row">
					<th scope="row"><label for="ydgl_checkout_button_text"><?php esc_html_e( 'Checkout Banner Button Text', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<input type="text" id="ydgl_checkout_button_text" name="ydgl_checkout_button_text" value="<?php echo esc_attr( get_option( 'ydgl_checkout_button_text', '' ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Continue with Google', 'yukdigitalz-login' ); ?>" />
					</td>
				</tr>

				<!-- Redirect Options -->
				<tr class="ydgl-section-divider">
					<th scope="row"><label for="ydgl_login_redirect_type"><?php esc_html_e( 'Post-Login Redirect', 'yukdigitalz-login' ); ?></label></th>
					<td>
						<select id="ydgl_login_redirect_type" name="ydgl_login_redirect_type">
							<option value="default" <?php selected( get_option( 'ydgl_login_redirect_type', 'default' ), 'default' ); ?>><?php esc_html_e( 'Default (Return to Same Page / My Account)', 'yukdigitalz-login' ); ?></option>
							<option value="custom" <?php selected( get_option( 'ydgl_login_redirect_type', 'default' ), 'custom' ); ?>><?php esc_html_e( 'Custom URL Destination', 'yukdigitalz-login' ); ?></option>
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
		<div class="ydgl-logs-container">
			<div class="tablenav top ydgl-logs-toolbar">
				<div>
					<label for="ydgl_log_file_select"><?php esc_html_e( 'Select Log File:', 'yukdigitalz-login' ); ?></label>
					<?php if ( ! empty( $ydgl_logs_data['files'] ) ) : ?>
						<select id="ydgl_log_file_select">
							<?php foreach ( $ydgl_logs_data['files'] as $ydgl_file ) : ?>
								<option value="<?php echo esc_attr( $ydgl_file ); ?>" <?php selected( $ydgl_logs_data['current_file'], $ydgl_file ); ?>>
									<?php echo esc_html( $ydgl_file ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<select disabled><option><?php esc_html_e( 'No log files found', 'yukdigitalz-login' ); ?></option></select>
					<?php endif; ?>
				</div>
				<div>
					<button type="button" class="button" id="ydgl_refresh_logs_btn"><?php esc_html_e( 'Refresh Logs', 'yukdigitalz-login' ); ?></button>
				</div>
			</div>

			<div class="ydgl-log-console">
				<?php if ( ! empty( $ydgl_logs_data['entries'] ) ) : ?>
					<?php foreach ( $ydgl_logs_data['entries'] as $ydgl_entry ) : ?>
						<div class="ydgl-log-line">
							<?php if ( ! empty( $ydgl_entry['timestamp'] ) ) : ?>
								<span class="ydgl-log-time">[<?php echo esc_html( $ydgl_entry['timestamp'] ); ?>]</span>
							<?php endif; ?>
							<span class="ydgl-log-level ydgl-log-level-<?php echo esc_attr( $ydgl_entry['level'] ); ?>">
								<?php echo esc_html( $ydgl_entry['level'] ); ?>
							</span>
							<span class="ydgl-log-msg"><?php echo esc_html( $ydgl_entry['message'] ); ?></span>
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

	<!-- TAB: CUSTOM TABS REGISTERED VIA EXTENSIONS -->
	<?php
	foreach ( $ydgl_tabs as $ydgl_tab_id => $ydgl_tab_title ) {
		if ( in_array( $ydgl_tab_id, array( 'settings', 'logs' ), true ) ) {
			continue;
		}
		$ydgl_safe_tab_id = sanitize_key( $ydgl_tab_id );
		?>
		<div id="ydgl-tab-<?php echo esc_attr( $ydgl_safe_tab_id ); ?>" class="ydgl-tab-content" style="display: none;">
			<?php do_action( 'ydgl_settings_tab_content_' . $ydgl_safe_tab_id ); ?>
		</div>
		<?php
	}
	?>
</div>
