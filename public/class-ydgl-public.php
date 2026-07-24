<?php
/**
 * Public Front-End Controller.
 *
 * @package YDGL/Public
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Public {

	/**
	 * Singleton instance.
	 *
	 * @var YDGL_Public|null
	 */
	private static $instance = null;

	/**
	 * Get active instance.
	 *
	 * @return YDGL_Public
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		
		// Add Google Sign-in buttons to WooCommerce forms.
		add_action( 'woocommerce_login_form_end', array( $this, 'render_google_login_button' ) );
		add_action( 'woocommerce_register_form_end', array( $this, 'render_google_login_button' ) );
		add_action( 'woocommerce_checkout_login_form_end', array( $this, 'render_google_login_button' ) );
		
		// Express Google login promo above checkout form.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_google_checkout_button' ), 5 );
		
		// Listen for the login trigger and account verification.
		add_action( 'template_redirect', array( $this, 'handle_login_redirect_trigger' ) );
		add_action( 'template_redirect', array( $this, 'handle_account_link_verification' ) );
		
		// Display OAuth errors as WooCommerce notices.
		add_action( 'wp', array( $this, 'display_login_errors' ) );

		// Register shortcode.
		add_shortcode( 'ydgl_google_login', array( $this, 'render_google_login_shortcode' ) );
	}

	/**
	 * Enqueue stylesheet for Google Sign-in button.
	 */
	public function enqueue_styles() {
		if ( ! get_option( 'ydgl_enabled' ) || 'yes' !== get_option( 'ydgl_enabled' ) ) {
			return;
		}

		wp_register_style(
			'ydgl-public-styles',
			YDGL_PLUGIN_URL . 'public/css/ydgl-public.css',
			array(),
			YDGL_VERSION
		);

		if ( is_account_page() || is_checkout() ) {
			wp_enqueue_style( 'ydgl-public-styles' );
		}
	}

	/**
	 * Render the Google button with branding compliance.
	 */
	public function render_google_login_button() {
		if ( ! get_option( 'ydgl_enabled' ) || 'yes' !== get_option( 'ydgl_enabled' ) ) {
			return;
		}

		$client_id = get_option( 'ydgl_client_id', '' );
		if ( empty( $client_id ) ) {
			return;
		}

		// Resolve redirect target.
		$redirect_type = get_option( 'ydgl_login_redirect_type', 'default' );
		$custom_url    = get_option( 'ydgl_login_redirect_url', '' );

		if ( 'custom' === $redirect_type && ! empty( $custom_url ) ) {
			if ( 0 === strpos( $custom_url, '/' ) ) {
				$redirect_url = home_url( $custom_url );
			} else {
				$redirect_url = esc_url_raw( $custom_url );
			}
		} else {
			// Set redirect to current page URL.
			$redirect_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
		}

		// Extensibility filter for redirect URL.
		$redirect_url = apply_filters( 'ydgl_login_redirect_url', $redirect_url, $redirect_type );

		$login_url = add_query_arg(
			array(
				'ydgl_login' => 'google',
				'redirect'   => rawurlencode( $redirect_url ),
			),
			home_url( '/' )
		);

		$button_text = get_option( 'ydgl_button_text', '' );
		if ( empty( $button_text ) ) {
			$button_text = __( 'Sign in with Google', 'yukdigitalz-login' );
		}

		ob_start();
		?>
		<div class="ydgl-google-login-container">
			<div class="ydgl-separator">
				<span><?php esc_html_e( 'or', 'yukdigitalz-login' ); ?></span>
			</div>
			<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn">
				<span class="ydgl-google-icon-wrapper">
					<svg class="ydgl-google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
						<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
						<path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.42-4.69H24v8.87h12.66c-.55 2.92-2.19 5.39-4.67 7.05l7.26 5.63C43.5 36.21 46.5 30.69 46.5 24z"/>
						<path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24s.92 7.54 2.56 10.78l7.98-6.19z"/>
						<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.26-5.63c-2.03 1.37-4.63 2.19-8.63 2.19-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
					</svg>
				</span>
				<span class="ydgl-google-btn-text"><?php echo esc_html( $button_text ); ?></span>
			</a>
		</div>
		<?php
		$html = ob_get_clean();

		do_action( 'ydgl_before_google_login_button' );
		echo apply_filters( 'ydgl_google_login_button_html', $html, $login_url, $button_text, 'ydgl-google-login-container' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is buffered and filtered safely.
		do_action( 'ydgl_after_google_login_button' );
	}

	/**
	 * Render Google Login promo block above checkout form.
	 */
	public function render_google_checkout_button() {
		if ( is_user_logged_in() ) {
			return;
		}

		if ( ! get_option( 'ydgl_enabled' ) || 'yes' !== get_option( 'ydgl_enabled' ) ) {
			return;
		}

		$client_id = get_option( 'ydgl_client_id', '' );
		if ( empty( $client_id ) ) {
			return;
		}

		// Set redirect back to checkout page.
		$current_url = wc_get_checkout_url();
		$login_url   = add_query_arg(
			array(
				'ydgl_login' => 'google',
				'redirect'   => rawurlencode( $current_url ),
			),
			home_url( '/' )
		);

		$checkout_text = get_option( 'ydgl_checkout_button_text', '' );
		if ( empty( $checkout_text ) ) {
			$checkout_text = __( 'Buy via Google', 'yukdigitalz-login' );
		}

		ob_start();
		?>
		<div class="ydgl-checkout-promo">
			<div class="ydgl-checkout-promo-content">
				<span class="ydgl-checkout-promo-icon">⚡</span>
				<div class="ydgl-checkout-promo-text">
					<strong><?php esc_html_e( 'Instant Digital Product Purchase', 'yukdigitalz-login' ); ?></strong>
					<p><?php esc_html_e( 'Click the button to automatically fill in your name and email using Google.', 'yukdigitalz-login' ); ?></p>
				</div>
			</div>
			<div class="ydgl-checkout-promo-btn-wrapper">
				<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn ydgl-checkout-btn">
					<span class="ydgl-google-icon-wrapper">
						<svg class="ydgl-google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
							<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
							<path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.42-4.69H24v8.87h12.66c-.55 2.92-2.19 5.39-4.67 7.05l7.26 5.63C43.5 36.21 46.5 30.69 46.5 24z"/>
							<path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24s.92 7.54 2.56 10.78l7.98-6.19z"/>
							<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.26-5.63c-2.03 1.37-4.63 2.19-8.63 2.19-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
						</svg>
					</span>
					<span class="ydgl-google-btn-text"><?php echo esc_html( $checkout_text ); ?></span>
				</a>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		echo apply_filters( 'ydgl_checkout_promo_html', $html, $login_url, $checkout_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is buffered and filtered safely.
	}

	/**
	 * Shortcode callback for [ydgl_google_login].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML button markup.
	 */
	public function render_google_login_shortcode( $atts ) {
		if ( ! get_option( 'ydgl_enabled' ) || 'yes' !== get_option( 'ydgl_enabled' ) ) {
			return '';
		}

		$client_id = get_option( 'ydgl_client_id', '' );
		if ( empty( $client_id ) ) {
			return '';
		}

		$args = shortcode_atts(
			array(
				'class' => '',
				'text'  => '',
			),
			$atts,
			'ydgl_google_login'
		);

		// Enqueue public stylesheet.
		wp_enqueue_style( 'ydgl-public-styles' );

		// Resolve redirect target.
		$redirect_type = get_option( 'ydgl_login_redirect_type', 'default' );
		$custom_url    = get_option( 'ydgl_login_redirect_url', '' );

		if ( 'custom' === $redirect_type && ! empty( $custom_url ) ) {
			if ( 0 === strpos( $custom_url, '/' ) ) {
				$redirect_url = home_url( $custom_url );
			} else {
				$redirect_url = esc_url_raw( $custom_url );
			}
		} else {
			// Fallback to current page URL.
			$redirect_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
		}

		// Extensibility filter for redirect URL.
		$redirect_url = apply_filters( 'ydgl_login_redirect_url', $redirect_url, $redirect_type );

		$login_url = add_query_arg(
			array(
				'ydgl_login' => 'google',
				'redirect'   => rawurlencode( $redirect_url ),
			),
			home_url( '/' )
		);

		$button_text = ! empty( $args['text'] ) ? sanitize_text_field( $args['text'] ) : get_option( 'ydgl_button_text', '' );
		if ( empty( $button_text ) ) {
			$button_text = __( 'Sign in with Google', 'yukdigitalz-login' );
		}

		$container_class = 'ydgl-google-login-container';
		if ( ! empty( $args['class'] ) ) {
			$container_class .= ' ' . sanitize_html_class( $args['class'] );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $container_class ); ?>">
			<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn">
				<span class="ydgl-google-icon-wrapper">
					<svg class="ydgl-google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
						<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
						<path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.42-4.69H24v8.87h12.66c-.55 2.92-2.19 5.39-4.67 7.05l7.26 5.63C43.5 36.21 46.5 30.69 46.5 24z"/>
						<path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24s.92 7.54 2.56 10.78l7.98-6.19z"/>
						<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.26-5.63c-2.03 1.37-4.63 2.19-8.63 2.19-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
					</svg>
				</span>
				<span class="ydgl-google-btn-text"><?php echo esc_html( $button_text ); ?></span>
			</a>
		</div>
		<?php
		$html = ob_get_clean();
		return apply_filters( 'ydgl_google_login_button_html', $html, $login_url, $button_text, $container_class );
	}

	/**
	 * Catch the login trigger, generate anti-CSRF state token, and redirect to Google.
	 */
	public function handle_login_redirect_trigger() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- External login trigger link, no nonce required.
		if ( ! isset( $_GET['ydgl_login'] ) || 'google' !== $_GET['ydgl_login'] ) {
			return;
		}

		if ( ! get_option( 'ydgl_enabled' ) || 'yes' !== get_option( 'ydgl_enabled' ) ) {
			return;
		}

		$oauth = new YDGL_OAuth();
		
		// Capture original redirect url to prevent open redirect vulnerabilities.
		$redirect_to = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : wc_get_page_permalink( 'myaccount' );
		$redirect_to = wp_validate_redirect( $redirect_to, wc_get_page_permalink( 'myaccount' ) );

		// Generate secure state parameters.
		$state    = $oauth->generate_state( $redirect_to );
		$auth_url = $oauth->get_auth_url( $state );

		if ( empty( $auth_url ) ) {
			YDGL_Logger::log( 'Authorization URL generation failed: Client ID is empty.', 'error' );
			wp_safe_redirect( add_query_arg( 'ydgl_error', 'config_missing', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External redirect to Google OAuth is required and safe.
		wp_redirect( esc_url_raw( $auth_url ) );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Intercept and process account linking verification from email link.
	 */
	public function handle_account_link_verification() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verification link from email inbox, no nonce required.
		if ( ! isset( $_GET['ydgl_verify_link'] ) ) {
			return;
		}

		$token = sanitize_key( $_GET['ydgl_verify_link'] );
		if ( empty( $token ) ) {
			return;
		}

		// Search user by pending link token.
		$users = get_users(
			array(
				'meta_key'   => '_ydgl_pending_link_token',
				'meta_value' => $token,
				'number'     => 1,
				'count_total' => false,
			)
		);

		if ( empty( $users ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Invalid or expired verification link.', 'yukdigitalz-login' ), 'error' );
			}
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		$user = $users[0];
		$expiry = (int) get_user_meta( $user->ID, '_ydgl_pending_link_expiry', true );

		if ( time() > $expiry ) {
			// Clean up expired meta.
			delete_user_meta( $user->ID, '_ydgl_pending_link_token' );
			delete_user_meta( $user->ID, '_ydgl_pending_google_id' );
			delete_user_meta( $user->ID, '_ydgl_pending_link_expiry' );

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'The verification link has expired. Please try signing in with Google again.', 'yukdigitalz-login' ), 'error' );
			}
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}

		// Link account.
		$google_id = get_user_meta( $user->ID, '_ydgl_pending_google_id', true );
		update_user_meta( $user->ID, '_ydgl_google_id', $google_id );

		// Clean up.
		delete_user_meta( $user->ID, '_ydgl_pending_link_token' );
		delete_user_meta( $user->ID, '_ydgl_pending_google_id' );
		delete_user_meta( $user->ID, '_ydgl_pending_link_expiry' );

		YDGL_Logger::log( 'User ID ' . $user->ID . ' successfully verified email and linked Google ID: ' . $google_id, 'info' );

		// Programmatically log the user in.
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing core WordPress hook for compatibility.
		do_action( 'wp_login', $user->user_login, $user );

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Your Google account has been successfully linked. You are now logged in.', 'yukdigitalz-login' ), 'success' );
		}

		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Display login errors as standard WooCommerce notices.
	 */
	public function display_login_errors() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading errors from URL redirects, no nonce verification needed.
		if ( ! isset( $_GET['ydgl_error'] ) ) {
			return;
		}

		$error_code = sanitize_key( $_GET['ydgl_error'] );
		$message    = __( 'An error occurred while signing in with Google. Please try again.', 'yukdigitalz-login' );
		$notice_type = 'error';

		switch ( $error_code ) {
			case 'google_error':
				$message = __( 'Google API returned an error. Please try again.', 'yukdigitalz-login' );
				break;
			case 'invalid_state':
				$message = __( 'Security session validation failed (CSRF detected). Please try again.', 'yukdigitalz-login' );
				break;
			case 'missing_params':
				$message = __( 'Google authentication parameters are incomplete.', 'yukdigitalz-login' );
				break;
			case 'token_exchange_failed':
				$message = __( 'Failed to exchange authorization code with Google.', 'yukdigitalz-login' );
				break;
			case 'profile_fetch_failed':
				$message = __( 'Failed to retrieve your Google profile data.', 'yukdigitalz-login' );
				break;
			case 'email_not_verified':
				$message = __( 'Your Google email address is not verified. Login rejected.', 'yukdigitalz-login' );
				break;
			case 'domain_restricted':
				$message = __( 'Login restricted. Your email domain is not allowed on this site.', 'yukdigitalz-login' );
				break;
			case 'registration_disabled':
				$message = __( 'New account registration is disabled by the administrator.', 'yukdigitalz-login' );
				break;
			case 'config_missing':
				$message = __( 'Google Login configuration has not been completed by the administrator.', 'yukdigitalz-login' );
				break;
			case 'pending_verification':
				$message = __( 'An account already exists with this email. A verification link has been sent to your email to link your Google account.', 'yukdigitalz-login' );
				$notice_type = 'notice'; // Blue informational notice banner
				break;
			case 'email_failed':
				$message = __( 'Failed to send verification email. Please try again.', 'yukdigitalz-login' );
				break;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $notice_type );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
