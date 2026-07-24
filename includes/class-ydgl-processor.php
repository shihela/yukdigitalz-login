<?php
/**
 * Processes WooCommerce Login & Registration.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Processor {

	/**
	 * Main entry point for authenticating/registering user via Google profile.
	 *
	 * @param array $user_profile Google user profile data.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	public function process_user_sign_in( $user_profile ) {
		$google_id = sanitize_text_field( $user_profile['sub'] );
		$email     = sanitize_email( $user_profile['email'] );

		if ( empty( $google_id ) || empty( $email ) ) {
			return new WP_Error( 'invalid_profile', __( 'Google profile is incomplete.', 'yukdigitalz-login' ) );
		}

		// 1. Verify Enterprise Domain Whitelist
		$domain_check = $this->verify_domain_restriction( $email );
		if ( is_wp_error( $domain_check ) ) {
			return $domain_check;
		}

		// 2. Find user by Google ID (stored in user meta)
		$user = $this->find_user_by_google_id( $google_id );

		if ( $user ) {
			return $this->login_user( $user );
		}

		// 3. Find user by Email
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			// Check if account linking verification is enabled.
			$verify_link = get_option( 'ydgl_verify_account_link', 'yes' ) === 'yes';

			if ( $verify_link ) {
				$result = $this->send_account_linking_verification( $user, $google_id );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return new WP_Error( 'pending_verification', __( 'An account already exists with this email. A verification link has been sent to your email to link your Google account.', 'yukdigitalz-login' ) );
			} else {
				// Link account securely directly.
				update_user_meta( $user->ID, '_ydgl_google_id', $google_id );
				YDGL_Logger::log( 'Linked existing WP user ID ' . $user->ID . ' with Google ID: ' . $google_id, 'info' );
				return $this->login_user( $user );
			}
		}

		// 4. Create new customer if registration is allowed
		return $this->register_new_customer( $user_profile );
	}

	/**
	 * Verify if email domain matches the allowed domain restriction list.
	 *
	 * @param string $email User email.
	 * @return true|WP_Error
	 */
	private function verify_domain_restriction( $email ) {
		$domain_whitelist = get_option( 'ydgl_domain_restriction', '' );
		if ( empty( $domain_whitelist ) ) {
			return true;
		}

		$allowed_domains = array_map( 'trim', explode( ',', strtolower( $domain_whitelist ) ) );
		$email_parts     = explode( '@', strtolower( $email ) );
		$user_domain     = end( $email_parts );

		if ( ! in_array( $user_domain, $allowed_domains, true ) ) {
			return new WP_Error( 'domain_restricted', __( 'Registration/login with this email domain is restricted by the administrator.', 'yukdigitalz-login' ) );
		}

		return true;
	}

	/**
	 * Find user by google ID meta field.
	 *
	 * @param string $google_id Google unique sub ID.
	 * @return WP_User|false
	 */
	private function find_user_by_google_id( $google_id ) {
		$users = get_users(
			array(
				'meta_key'   => '_ydgl_google_id',
				'meta_value' => $google_id,
				'number'     => 1,
				'count_total' => false,
			)
		);

		return ! empty( $users ) ? $users[0] : false;
	}

	/**
	 * Register a new WooCommerce customer.
	 *
	 * @param array $user_profile Google profile.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	private function register_new_customer( $user_profile ) {
		// Verify if Google Registration is enabled.
		$google_reg_enabled = get_option( 'ydgl_enable_registration', 'yes' );
		
		// Also verify general WordPress / WooCommerce registration settings.
		$wp_reg_enabled = get_option( 'users_can_register' );
		$wc_reg_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );

		if ( 'yes' !== $google_reg_enabled || ( ! $wp_reg_enabled && ! $wc_reg_enabled ) ) {
			return new WP_Error( 'registration_disabled', __( 'New user registration is currently disabled.', 'yukdigitalz-login' ) );
		}

		$email      = sanitize_email( $user_profile['email'] );
		$first_name = isset( $user_profile['given_name'] ) ? sanitize_text_field( $user_profile['given_name'] ) : '';
		$last_name  = isset( $user_profile['family_name'] ) ? sanitize_text_field( $user_profile['family_name'] ) : '';
		$full_name  = isset( $user_profile['name'] ) ? sanitize_text_field( $user_profile['name'] ) : '';

		// Generate unique username based on email.
		$username = sanitize_user( current( explode( '@', $email ) ), true );
		if ( username_exists( $username ) ) {
			$username = $username . '_' . wp_rand( 100, 999 );
		}

		// Fail-safe username check.
		$username = wp_unique_id( $username );

		// Generate cryptographically secure random password.
		$password = wp_generate_password( 24, true, true );

		// WooCommerce function to create new customer.
		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email, $username, $password );
		} else {
			// Fallback to WP core insert if WC is not loaded for some reason.
			$user_id = wp_create_user( $username, $password, $email );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Update user meta and profile information.
		update_user_meta( $user_id, '_ydgl_google_id', sanitize_text_field( $user_profile['sub'] ) );

		$user_data = array(
			'ID'         => $user_id,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'display_name' => ! empty( $full_name ) ? $full_name : $username,
		);
		wp_update_user( $user_data );

		// Update billing name details for WooCommerce.
		if ( ! empty( $first_name ) ) {
			update_user_meta( $user_id, 'billing_first_name', $first_name );
			update_user_meta( $user_id, 'shipping_first_name', $first_name );
		}
		if ( ! empty( $last_name ) ) {
			update_user_meta( $user_id, 'billing_last_name', $last_name );
			update_user_meta( $user_id, 'shipping_last_name', $last_name );
		}

		// Trigger standard WooCommerce customer registration actions.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WooCommerce hook for compatibility.
		do_action( 'woocommerce_created_customer', $user_id, $user_data, false );

		// Fire extensibility action for new registration via Google.
		do_action( 'ydgl_new_user_registered_via_google', $user_id, $user_profile );

		YDGL_Logger::log( 'New WooCommerce customer registered: ID ' . $user_id . ' (' . $email . ')', 'info' );

		return $this->login_user( get_user_by( 'id', $user_id ) );
	}

	/**
	 * Programmatically login a WordPress user securely.
	 *
	 * @param WP_User $user The WP_User object to authenticate.
	 * @return int User ID.
	 */
	private function login_user( $user ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true ); // Remember user.

		// Fire normal login hooks for plugins (like security loggers, Wordfence, etc.).
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing core WordPress hook for compatibility.
		do_action( 'wp_login', $user->user_login, $user );

		// Fire extensibility action for login via Google.
		do_action( 'ydgl_user_logged_in_via_google', $user->ID, $user );

		return $user->ID;
	}

	/**
	 * Generate link token and send verification email.
	 *
	 * @param WP_User $user WP_User object.
	 * @param string  $google_id Google ID (sub).
	 * @return true|WP_Error
	 */
	private function send_account_linking_verification( $user, $google_id ) {
		$token = bin2hex( wp_generate_password( 24, false ) );
		
		// Set token details in user meta.
		update_user_meta( $user->ID, '_ydgl_pending_link_token', $token );
		update_user_meta( $user->ID, '_ydgl_pending_google_id', $google_id );
		update_user_meta( $user->ID, '_ydgl_pending_link_expiry', time() + HOUR_IN_SECONDS );

		// Generate verification link.
		$link = add_query_arg(
			array(
				'ydgl_verify_link' => $token,
			),
			wc_get_page_permalink( 'myaccount' )
		);

		// Send email.
		// translators: %s: Site name.
		$subject = sprintf( __( '[%s] Confirm Linking Your Google Account', 'yukdigitalz-login' ), get_bloginfo( 'name' ) );
		
		// translators: %s: User display name.
		$body  = sprintf( __( 'Hello %s,', 'yukdigitalz-login' ), $user->display_name ) . "\r\n\r\n";
		$body .= __( 'We detected a request to sign in to your account using Google. To confirm this request and link your Google account to your existing profile, please click the link below:', 'yukdigitalz-login' ) . "\r\n";
		$body .= esc_url_raw( $link ) . "\r\n\r\n";
		$body .= __( 'This link will expire in 1 hour. If you did not request this, you can safely ignore this email.', 'yukdigitalz-login' ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'yukdigitalz-login' ) . "\r\n";
		$body .= get_bloginfo( 'name' );

		$sent = wp_mail( $user->user_email, $subject, $body );
		if ( ! $sent ) {
			YDGL_Logger::log( 'Failed to send account linking email to ' . $user->user_email, 'error' );
			return new WP_Error( 'email_failed', __( 'Failed to send verification email. Please try again.', 'yukdigitalz-login' ) );
		}

		YDGL_Logger::log( 'Sent account linking verification email to ' . $user->user_email, 'info' );
		return true;
	}
}
