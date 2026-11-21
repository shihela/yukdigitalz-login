<?php
/**
 * Google OAuth 2.0 + PKCE Handler.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_OAuth {

	/**
	 * Cookie name used to bind OAuth state to the initiating browser (anti Login-CSRF).
	 */
	const STATE_COOKIE = 'ydgl_oauth_state';

	/**
	 * Get Google Client ID from settings.
	 *
	 * @return string
	 */
	public function get_client_id() {
		return trim( (string) get_option( 'ydgl_client_id', '' ) );
	}

	/**
	 * Get Google Client Secret from settings.
	 *
	 * @return string
	 */
	private function get_client_secret() {
		return YDGL_Security::decrypt( (string) get_option( 'ydgl_client_secret', '' ) );
	}

	/**
	 * Get WooCommerce API Redirect URI.
	 *
	 * @return string
	 */
	public function get_redirect_uri() {
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return esc_url_raw( add_query_arg( 'wc-api', 'ydgl_google_login', home_url( '/' ) ) );
		}

		$url = esc_url_raw( WC()->api_request_url( 'ydgl_google_login' ) );

		// Force HTTPS if the request is SSL or behind an HTTPS reverse proxy.
		$x_proto  = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';
		$is_https = is_ssl() || 'https' === strtolower( $x_proto );

		if ( $is_https ) {
			$url = preg_replace( '#^http://#i', 'https://', $url );
		}

		return $url;
	}

	/**
	 * Generate OAuth authorization URL with PKCE (S256).
	 *
	 * @param string $state          Secure state string.
	 * @param string $code_challenge PKCE S256 code challenge.
	 * @return string
	 */
	public function get_auth_url( $state, $code_challenge = '' ) {
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

		if ( ! empty( $code_challenge ) ) {
			$params['code_challenge']        = $code_challenge;
			$params['code_challenge_method'] = 'S256';
		}

		return add_query_arg( $params, 'https://accounts.google.com/o/oauth2/v2/auth' );
	}

	/**
	 * Generate and store a secure state token + PKCE verifier bound to the browser cookie.
	 *
	 * @param string $redirect_to URL to redirect after successful login.
	 * @return array{state: string, code_challenge: string}
	 */
	public function create_oauth_session( $redirect_to = '' ) {
		$state         = YDGL_Security::generate_token( 24 );
		$cookie_secret = YDGL_Security::generate_token( 24 );
		$code_verifier = YDGL_Security::generate_token( 32 );
		$challenge     = YDGL_Security::base64url_encode( hash( 'sha256', $code_verifier, true ) );

		$payload = array(
			'cookie_hash'   => hash( 'sha256', $cookie_secret ),
			'ua'            => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'code_verifier' => $code_verifier,
			'redirect_to'   => esc_url_raw( $redirect_to ),
		);

		// Store in transient for 10 minutes.
		set_transient( 'ydgl_state_' . $state, $payload, 10 * MINUTE_IN_SECONDS );

		// Bind state to the initiating browser with HttpOnly + SameSite=Lax cookie.
		$this->set_state_cookie( $cookie_secret, time() + ( 10 * MINUTE_IN_SECONDS ) );

		return array(
			'state'          => $state,
			'code_challenge' => $challenge,
		);
	}

	/**
	 * Validate return state token against transient, browser cookie binding, and UA.
	 *
	 * @param string $state State token received from Google callback.
	 * @return array|false Returns session payload array if valid, false if invalid.
	 */
	public function validate_state( $state ) {
		if ( empty( $state ) ) {
			YDGL_Logger::log( 'validate_state: State parameter is empty.', 'warning' );
			return false;
		}

		$transient_name = 'ydgl_state_' . sanitize_key( $state );
		$payload        = get_transient( $transient_name );

		if ( ! is_array( $payload ) ) {
			YDGL_Logger::log( 'validate_state: State transient not found or expired.', 'warning' );
			return false;
		}

		// Delete state immediately (one-time use, prevents replay attacks).
		delete_transient( $transient_name );

		// Verify HttpOnly browser cookie binding (defeats Login CSRF).
		$cookie_val = isset( $_COOKIE[ self::STATE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::STATE_COOKIE ] ) ) : '';
		$this->clear_state_cookie();

		if ( empty( $cookie_val ) || empty( $payload['cookie_hash'] ) || ! hash_equals( $payload['cookie_hash'], hash( 'sha256', $cookie_val ) ) ) {
			YDGL_Logger::log( 'validate_state: Browser cookie binding mismatch (possible Login CSRF).', 'warning' );
			return false;
		}

		// Secondary check: User-Agent consistency.
		$current_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( isset( $payload['ua'] ) && $payload['ua'] !== $current_ua ) {
			YDGL_Logger::log( 'validate_state: User-Agent mismatch.', 'warning' );
			return false;
		}

		return $payload;
	}

	/**
	 * Handle Google callback routing, token exchange, and validation.
	 */
	public function handle_callback() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- External Google OAuth callback; CSRF is validated via cookie-bound state token.

		// 1. Check for errors from Google.
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			$this->clear_state_cookie();
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

		// 3. Validate cookie-bound state.
		$session = $this->validate_state( $state );
		if ( ! $session ) {
			YDGL_Logger::log( 'OAuth state token validation failed (CSRF attempt blocked).', 'critical' );
			$this->redirect_to_myaccount_with_error( 'invalid_state' );
		}

		// 4. Exchange authorization code for access token (with PKCE verifier).
		$code_verifier = isset( $session['code_verifier'] ) ? $session['code_verifier'] : '';
		$token_data    = $this->exchange_code_for_token( $code, $code_verifier );
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

		// 6. Verify Google email_verified flag.
		$email_verified = isset( $user_profile['email_verified'] ) ? (bool) $user_profile['email_verified'] : false;
		if ( ! $email_verified ) {
			YDGL_Logger::log( 'Login rejected: Google email is not verified for ' . YDGL_Security::mask_email( $user_profile['email'] ), 'warning' );
			$this->redirect_to_myaccount_with_error( 'email_not_verified' );
		}

		// Resolve safe redirect destination.
		$default_redirect = wc_get_page_permalink( 'myaccount' );
		$redirect_to      = ! empty( $session['redirect_to'] ) ? esc_url_raw( $session['redirect_to'] ) : $default_redirect;
		$redirect_to      = wp_validate_redirect( $redirect_to, $default_redirect );

		// 7. Hand over to YDGL_Processor to log in or register the user.
		$processor = new YDGL_Processor();
		$user_id   = $processor->process_user_sign_in( $user_profile, $redirect_to );

		if ( is_wp_error( $user_id ) ) {
			$error_code = $user_id->get_error_code();
			YDGL_Logger::log( 'Sign-in failed for ' . YDGL_Security::mask_email( $user_profile['email'] ) . ': ' . $user_id->get_error_message(), 'error' );
			$this->redirect_to_myaccount_with_error( $error_code );
		}

		YDGL_Logger::log( 'User ' . YDGL_Security::mask_email( $user_profile['email'] ) . ' (ID: ' . $user_id . ') authenticated via Google OAuth.', 'info' );

		wp_safe_redirect( $redirect_to );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Verify a Google One Tap ID token via Google's tokeninfo endpoint.
	 *
	 * @param string $id_token JWT credential returned by Google Identity Services.
	 * @return array|WP_Error Normalized user profile array or WP_Error.
	 */
	public function verify_id_token( $id_token ) {
		$client_id = $this->get_client_id();
		if ( empty( $client_id ) ) {
			return new WP_Error( 'config_missing', __( 'Google Login configuration is incomplete.', 'yukdigitalz-login' ) );
		}

		$response = wp_remote_get(
			add_query_arg( 'id_token', rawurlencode( $id_token ), 'https://oauth2.googleapis.com/tokeninfo' ),
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			YDGL_Logger::log( 'One Tap token verification HTTP error: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'token_exchange_failed', __( 'Failed to verify Google One Tap token.', 'yukdigitalz-login' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			YDGL_Logger::log( 'One Tap tokeninfo rejected token with HTTP status ' . $code, 'warning' );
			return new WP_Error( 'token_exchange_failed', __( 'Invalid Google One Tap token.', 'yukdigitalz-login' ) );
		}

		// Validate audience matches our configured Client ID.
		if ( empty( $body['aud'] ) || ! hash_equals( $client_id, (string) $body['aud'] ) ) {
			YDGL_Logger::log( 'One Tap token audience mismatch.', 'critical' );
			return new WP_Error( 'invalid_state', __( 'Google token audience mismatch.', 'yukdigitalz-login' ) );
		}

		// Validate issuer.
		$valid_issuers = array( 'accounts.google.com', 'https://accounts.google.com' );
		if ( empty( $body['iss'] ) || ! in_array( $body['iss'], $valid_issuers, true ) ) {
			YDGL_Logger::log( 'One Tap token issuer mismatch.', 'critical' );
			return new WP_Error( 'invalid_state', __( 'Invalid Google token issuer.', 'yukdigitalz-login' ) );
		}

		// Validate expiration.
		if ( empty( $body['exp'] ) || (int) $body['exp'] < time() ) {
			return new WP_Error( 'token_exchange_failed', __( 'Google One Tap token has expired.', 'yukdigitalz-login' ) );
		}

		// Validate verified email.
		$email_verified = isset( $body['email_verified'] ) && ( true === $body['email_verified'] || 'true' === $body['email_verified'] );
		if ( ! $email_verified || empty( $body['email'] ) || empty( $body['sub'] ) ) {
			return new WP_Error( 'email_not_verified', __( 'Your Google email address is not verified.', 'yukdigitalz-login' ) );
		}

		return array(
			'sub'            => sanitize_text_field( $body['sub'] ),
			'email'          => sanitize_email( $body['email'] ),
			'email_verified' => true,
			'given_name'     => isset( $body['given_name'] ) ? sanitize_text_field( $body['given_name'] ) : '',
			'family_name'    => isset( $body['family_name'] ) ? sanitize_text_field( $body['family_name'] ) : '',
			'name'           => isset( $body['name'] ) ? sanitize_text_field( $body['name'] ) : '',
			'picture'        => isset( $body['picture'] ) ? esc_url_raw( $body['picture'] ) : '',
		);
	}

	/**
	 * Send POST request to Google Token endpoint.
	 *
	 * @param string $code          Auth code.
	 * @param string $code_verifier PKCE code verifier.
	 * @return array|false Returns token array or false.
	 */
	private function exchange_code_for_token( $code, $code_verifier = '' ) {
		$client_id     = $this->get_client_id();
		$client_secret = $this->get_client_secret();

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			YDGL_Logger::log( 'Token exchange aborted: Client ID or Client Secret is missing or could not be decrypted.', 'error' );
			return false;
		}

		$body_params = array(
			'code'          => $code,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri'  => $this->get_redirect_uri(),
			'grant_type'    => 'authorization_code',
		);

		if ( ! empty( $code_verifier ) ) {
			$body_params['code_verifier'] = $code_verifier;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => $body_params,
			)
		);

		if ( is_wp_error( $response ) ) {
			YDGL_Logger::log( 'HTTP request error during token exchange: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			YDGL_Logger::log( 'Google token exchange endpoint returned HTTP ' . $response_code, 'error' );
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
			YDGL_Logger::log( 'Google userinfo endpoint returned HTTP ' . $response_code, 'error' );
			return false;
		}

		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : false;
	}

	/**
	 * Set the HttpOnly SameSite=Lax state binding cookie.
	 *
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiration Unix timestamp.
	 */
	private function set_state_cookie( $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}

		$x_proto  = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';
		$is_https = is_ssl() || 'https' === strtolower( $x_proto );

		setcookie(
			self::STATE_COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => $is_https,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Clear the state binding cookie.
	 */
	private function clear_state_cookie() {
		$this->set_state_cookie( '', time() - HOUR_IN_SECONDS );
		unset( $_COOKIE[ self::STATE_COOKIE ] );
	}

	/**
	 * Redirect to WooCommerce My Account with error parameter.
	 *
	 * @param string $error_code Error identifier.
	 */
	private function redirect_to_myaccount_with_error( $error_code ) {
		$url = add_query_arg( 'ydgl_error', sanitize_key( $error_code ), wc_get_page_permalink( 'myaccount' ) );
		wp_safe_redirect( esc_url_raw( $url ) );
		exit;
	}
}
