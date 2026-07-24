<?php
/**
 * Google OAuth 2.0 Handler.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_OAuth {

	/**
	 * Get Google Client ID from settings.
	 *
	 * @return string
	 */
	private function get_client_id() {
		return get_option( 'ydgl_client_id', '' );
	}

	/**
	 * Get Google Client Secret from settings.
	 *
	 * @return string
	 */
	private function get_client_secret() {
		return YDGL_Security::decrypt( get_option( 'ydgl_client_secret', '' ) );
	}

	/**
	 * Get WooCommerce API Redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		$url = esc_url_raw( WC()->api_request_url( 'ydgl_google_login' ) );

		// Force HTTPS if the request is SSL or behind an HTTPS reverse proxy (Ngrok, Cloudflare Tunnel, etc.).
		$x_proto  = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';
		$is_https = is_ssl() || 'https' === strtolower( $x_proto );
		
		if ( $is_https ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		return $url;
	}

	/**
	 * Generate OAuth authorization URL.
	 *
	 * @param string $state Secure state string.
	 * @return string
	 */
	public function get_auth_url( $state ) {
		$client_id = $this->get_client_id();
		if ( empty( $client_id ) ) {
			return '';
		}

		$params = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $this->get_redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'openid email profile',
			'state'         => $state,
			'access_type'   => 'online',
			'prompt'        => 'select_account',
		);

		return add_query_arg( $params, 'https://accounts.google.com/o/oauth2/v2/auth' );
	}

	/**
	 * Generate and store a secure state token to prevent CSRF.
	 * Stores client details like IP, User Agent, and return URL in WP transient.
	 *
	 * @param string $redirect_to URL to redirect after successful login.
	 * @return string
	 */
	public function generate_state( $redirect_to = '' ) {
		$state = bin2hex( wp_generate_password( 16, false ) );

		// Secure fingerprint components.
		$fingerprint = array(
			'ip'          => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'ua'          => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'redirect_to' => esc_url_raw( $redirect_to ),
		);

		// Store in transient for 10 minutes (600 seconds).
		set_transient( 'ydgl_state_' . $state, $fingerprint, 10 * MINUTE_IN_SECONDS );

		return $state;
	}

	/**
	 * Validate return state token against transient & fingerprint.
	 *
	 * @param string $state State token received from Google callback.
	 * @return array|false Returns fingerprint array if valid, false if invalid.
	 */
	public function validate_state( $state ) {
		if ( empty( $state ) ) {
			YDGL_Logger::log( 'validate_state: State parameter is empty.', 'warning' );
			return false;
		}

		$transient_name = 'ydgl_state_' . sanitize_key( $state );
		$fingerprint    = get_transient( $transient_name );

		if ( ! $fingerprint ) {
			YDGL_Logger::log( 'validate_state: Transient not found or expired for key ' . $transient_name, 'warning' );
			return false;
		}

		// Validate User Agent and IP.
		$current_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$current_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( $fingerprint['ua'] !== $current_ua ) {
			YDGL_Logger::log( sprintf( 'validate_state: User Agent mismatch. Stored: "%s", Current: "%s"', $fingerprint['ua'], $current_ua ), 'warning' );
			return false;
		}

		// Delete state immediately to prevent replay attacks.
		delete_transient( $transient_name );

		return $fingerprint;
	}

	/**
	 * Handle Google callback routing, token exchange, and validation.
	 */
	public function handle_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- External Google OAuth callback request, CSRF is validated via state.
		
		// 1. Check for errors from Google.
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			YDGL_Logger::log( 'Google OAuth Callback returned error: ' . $error, 'error' );
			$this->redirect_to_myaccount_with_error( 'google_error' );
		}

		// 2. Extract code and state parameters.
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( empty( $code ) || empty( $state ) ) {
			YDGL_Logger::log( 'Google OAuth Callback missing code or state parameter.', 'error' );
			$this->redirect_to_myaccount_with_error( 'missing_params' );
		}

		// 3. Validate state.
		$fingerprint = $this->validate_state( $state );
		if ( ! $fingerprint ) {
			YDGL_Logger::log( 'OAuth state token validation failed (CSRF attempt blocked).', 'critical' );
			$this->redirect_to_myaccount_with_error( 'invalid_state' );
		}

		// 4. Exchange authorization code for access token.
		$token_data = $this->exchange_code_for_token( $code );
		if ( ! $token_data || empty( $token_data['access_token'] ) ) {
			YDGL_Logger::log( 'Failed to exchange authorization code for access token.', 'error' );
			$this->redirect_to_myaccount_with_error( 'token_exchange_failed' );
		}

		// 5. Retrieve Google user profile info.
		$user_profile = $this->get_google_user_profile( $token_data['access_token'] );
		if ( ! $user_profile || empty( $user_profile['email'] ) ) {
			YDGL_Logger::log( 'Failed to retrieve Google user profile data.', 'error' );
			$this->redirect_to_myaccount_with_error( 'profile_fetch_failed' );
		}

		// 6. Enterprise security verification: Google must have verified the email.
		$email_verified = isset( $user_profile['email_verified'] ) ? (bool) $user_profile['email_verified'] : false;
		if ( ! $email_verified ) {
			YDGL_Logger::log( 'Security breach block: Google email is not verified for user ' . $user_profile['email'], 'warning' );
			$this->redirect_to_myaccount_with_error( 'email_not_verified' );
		}

		// 7. Hand over to YDGL_Processor to log in or register the user.
		$processor = new YDGL_Processor();
		$user_id   = $processor->process_user_sign_in( $user_profile );

		if ( is_wp_error( $user_id ) ) {
			$error_code = $user_id->get_error_code();
			YDGL_Logger::log( 'Registration/Login failed for user ' . $user_profile['email'] . ': ' . $user_id->get_error_message(), 'error' );
			$this->redirect_to_myaccount_with_error( $error_code );
		}

		// 8. Redirect back to original target URL or standard WooCommerce My Account.
		$redirect_to = ! empty( $fingerprint['redirect_to'] ) ? esc_url_raw( $fingerprint['redirect_to'] ) : wc_get_page_permalink( 'myaccount' );
		
		// Clean up redirect to avoid redirect loops or open redirect vulnerabilities.
		$redirect_to = wp_validate_redirect( $redirect_to, wc_get_page_permalink( 'myaccount' ) );

		YDGL_Logger::log( 'User ' . $user_profile['email'] . ' (ID: ' . $user_id . ') successfully authenticated via Google.', 'info' );

		wp_safe_redirect( $redirect_to );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Send POST request to Google Token endpoint.
	 *
	 * @param string $code Auth code.
	 * @return array|false Returns token array or false.
	 */
	private function exchange_code_for_token( $code ) {
		$client_id     = $this->get_client_id();
		$client_secret = $this->get_client_secret();

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $this->get_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			YDGL_Logger::log( 'HTTP request error during token exchange: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			YDGL_Logger::log( 'Google token exchange endpoint returned status ' . $response_code . ' with body: ' . $body, 'error' );
			return false;
		}

		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : false;
	}

	/**
	 * Retrieve Google user profile information using access token.
	 *
	 * @param string $access_token Google access token.
	 * @return array|false
	 */
	private function get_google_user_profile( $access_token ) {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v3/userinfo',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			YDGL_Logger::log( 'HTTP request error during profile fetch: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			YDGL_Logger::log( 'Google userinfo endpoint returned status ' . $response_code . ' with body: ' . $body, 'error' );
			return false;
		}

		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : false;
	}

	/**
	 * Redirect to WooCommerce My Account with error parameter.
	 *
	 * @param string $error_code
	 */
	private function redirect_to_myaccount_with_error( $error_code ) {
		$url = add_query_arg( 'ydgl_error', sanitize_key( $error_code ), wc_get_page_permalink( 'myaccount' ) );
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}
}
