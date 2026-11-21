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
	 * @param array  $user_profile Google user profile data.
	 * @param string $redirect_to  Intended post-login redirect URL.
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	public function process_user_sign_in( $user_profile, $redirect_to = '' ) {
		$google_id = isset( $user_profile['sub'] ) ? sanitize_text_field( $user_profile['sub'] ) : '';
		$email     = isset( $user_profile['email'] ) ? sanitize_email( $user_profile['email'] ) : '';

		if ( empty( $google_id ) || empty( $email ) ) {
			return new WP_Error( 'invalid_profile', __( 'Google profile is incomplete.', 'yukdigitalz-login' ) );
		}

		// 1. Verify Domain Whitelist.
		$domain_check = $this->verify_domain_restriction( $email );
		if ( is_wp_error( $domain_check ) ) {
			return $domain_check;
		}

		// 2. Find user by Google ID (stored in user meta).
		$user = $this->find_user_by_google_id( $google_id );

		if ( $user ) {
			$admin_check = $this->verify_admin_login_policy( $user );
			if ( is_wp_error( $admin_check ) ) {
				return $admin_check;
			}
			$this->sync_google_avatar( $user->ID, $user_profile );
			return $this->login_user( $user );
		}

		// 3. Find user by Email.
		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$admin_check = $this->verify_admin_login_policy( $user );
			if ( is_wp_error( $admin_check ) ) {
				return $admin_check;
			}

			// Check if account linking verification is enabled.
			$verify_link = 'yes' === get_option( 'ydgl_verify_account_link', 'yes' );

			if ( $verify_link ) {
				$result = $this->send_account_linking_verification( $user, $google_id, $redirect_to );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				return new WP_Error( 'pending_verification', __( 'An account already exists with this email. A verification link has been sent to your email to link your Google account.', 'yukdigitalz-login' ) );
			}

			// Link account directly when verification is disabled.
			update_user_meta( $user->ID, '_ydgl_google_id', $google_id );
			$this->sync_google_avatar( $user->ID, $user_profile );
			YDGL_Logger::log( 'Linked existing WP user ID ' . $user->ID . ' with Google account.', 'info' );
			return $this->login_user( $user );
		}

		// 4. Create new customer if registration is allowed.
		return $this->register_new_customer( $user_profile );
	}

	/**
	 * Block privileged administrator accounts from social login (prevents 2FA bypass) if enabled.
	 *
	 * @param WP_User $user Target user.
	 * @return true|WP_Error
	 */
	public function verify_admin_login_policy( $user ) {
		$block_admins = 'yes' === get_option( 'ydgl_block_admin_login', 'yes' );
		if ( $block_admins && user_can( $user, 'manage_options' ) ) {
			YDGL_Logger::log( 'Blocked Google Sign-In attempt for privileged administrator account (User ID: ' . $user->ID . ').', 'warning' );
			return new WP_Error( 'admin_login_blocked', __( 'Google Sign-In is disabled for administrator accounts for security reasons. Please sign in with your password.', 'yukdigitalz-login' ) );
		}
		return true;
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

		$allowed_domains = array_filter( array_map( 'trim', explode( ',', strtolower( $domain_whitelist ) ) ) );
		$email_parts     = explode( '@', strtolower( $email ) );
		$user_domain     = end( $email_parts );

		if ( ! in_array( $user_domain, $allowed_domains, true ) ) {
			return new WP_Error( 'domain_restricted', __( 'Registration/login with this email domain is restricted by the administrator.', 'yukdigitalz-login' ) );
		}

		return true;
	}

	/**
	 * Find user by Google ID meta field (with object cache).
	 *
	 * @param string $google_id Google unique sub ID.
	 * @return WP_User|false
	 */
	private function find_user_by_google_id( $google_id ) {
		$cache_key      = 'ydgl_uid_' . hash( 'sha256', (string) $google_id );
		$cached_user_id = wp_cache_get( $cache_key, 'ydgl' );

		if ( false !== $cached_user_id ) {
			return $cached_user_id ? get_user_by( 'id', (int) $cached_user_id ) : false;
		}

		$users = get_users(
			array(
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded 1-row OAuth lookup cached in object cache.
				'meta_key'    => '_ydgl_google_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded 1-row OAuth lookup cached in object cache.
				'meta_value'  => $google_id,
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'ID',
			)
		);

		$user_id = ! empty( $users ) ? (int) $users[0] : 0;
		if ( $user_id > 0 ) {
			wp_cache_set( $cache_key, $user_id, 'ydgl', 15 * MINUTE_IN_SECONDS );
			return get_user_by( 'id', $user_id );
		}

		return false;
	}

	/**
	 * Generate a guaranteed unique WordPress username from an email address.
	 *
	 * @param string $email User email.
	 * @return string
	 */
	private function generate_unique_username( $email ) {
		$email_parts = explode( '@', $email );
		$base        = sanitize_user( $email_parts[0], true );

		if ( empty( $base ) ) {
			$base = 'customer';
		}

		$username = $base;
		$attempts = 0;

		while ( username_exists( $username ) && $attempts < 20 ) {
			$username = $base . '_' . wp_rand( 100, 9999 );
			++$attempts;
		}

		if ( username_exists( $username ) ) {
			$username = $base . '_' . substr( YDGL_Security::generate_token( 8 ), 0, 8 );
		}

		return $username;
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
		$wp_reg_enabled = (bool) get_option( 'users_can_register' );
		$wc_reg_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );

		if ( 'yes' !== $google_reg_enabled || ( ! $wp_reg_enabled && ! $wc_reg_enabled ) ) {
			return new WP_Error( 'registration_disabled', __( 'New user registration is currently disabled.', 'yukdigitalz-login' ) );
		}

		$email      = sanitize_email( $user_profile['email'] );
		$first_name = isset( $user_profile['given_name'] ) ? sanitize_text_field( $user_profile['given_name'] ) : '';
		$last_name  = isset( $user_profile['family_name'] ) ? sanitize_text_field( $user_profile['family_name'] ) : '';
		$full_name  = isset( $user_profile['name'] ) ? sanitize_text_field( $user_profile['name'] ) : '';

		$username = $this->generate_unique_username( $email );
		$password = wp_generate_password( 24, true, true );

		$used_wc_creator = false;
		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id         = wc_create_new_customer(
				$email,
				$username,
				$password,
				array(
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'display_name' => ! empty( $full_name ) ? $full_name : $username,
				)
			);
			$used_wc_creator = true;
		} else {
			$user_id = wp_create_user( $username, $password, $email );
		}

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Save Google ID meta & sync Google avatar.
		update_user_meta( $user_id, '_ydgl_google_id', sanitize_text_field( $user_profile['sub'] ) );
		$this->sync_google_avatar( $user_id, $user_profile );

		// Apply dynamic domain-based role mapping (strictly non-privileged roles only).
		$this->apply_domain_role_mapping( $user_id, $email );

		// Ensure profile fields are set when using fallback wp_create_user().
		if ( ! $used_wc_creator ) {
			$user_data = array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => ! empty( $full_name ) ? $full_name : $username,
			);
			wp_update_user( $user_data );

			// Only fire woocommerce_created_customer when wc_create_new_customer() was NOT used (avoids duplicate emails).
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing WooCommerce hook for compatibility.
			do_action( 'woocommerce_created_customer', $user_id, $user_data, false );
		}

		// Populate WooCommerce billing and shipping details for instant checkout.
		update_user_meta( $user_id, 'billing_email', $email );

		if ( ! empty( $first_name ) ) {
			update_user_meta( $user_id, 'billing_first_name', $first_name );
			update_user_meta( $user_id, 'shipping_first_name', $first_name );
		}
		if ( ! empty( $last_name ) ) {
			update_user_meta( $user_id, 'billing_last_name', $last_name );
			update_user_meta( $user_id, 'shipping_last_name', $last_name );
		}

		// Fire extensibility action for new registration via Google.
		do_action( 'ydgl_new_user_registered_via_google', $user_id, $user_profile );

		YDGL_Logger::log( 'New WooCommerce customer registered: ID ' . $user_id . ' (' . YDGL_Security::mask_email( $email ) . ')', 'info' );

		return $this->login_user( get_user_by( 'id', $user_id ) );
	}

	/**
	 * Programmatically log in a WordPress user securely.
	 *
	 * @param WP_User $user The WP_User object to authenticate.
	 * @return int User ID.
	 */
	public function login_user( $user ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		// Fire core WordPress login hook for compatibility.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Firing core WordPress hook for compatibility.
		do_action( 'wp_login', $user->user_login, $user );

		// Fire extensibility action for login via Google.
		do_action( 'ydgl_user_logged_in_via_google', $user->ID, $user );

		return $user->ID;
	}

	/**
	 * Generate a hashed link token and send account-linking verification email.
	 *
	 * @param WP_User $user        WP_User object.
	 * @param string  $google_id   Google ID (sub).
	 * @param string  $redirect_to Intended redirect destination after verification.
	 * @return true|WP_Error
	 */
	private function send_account_linking_verification( $user, $google_id, $redirect_to = '' ) {
		$token      = YDGL_Security::generate_token( 32 );
		$token_hash = hash( 'sha256', $token );

		// Store only the SHA-256 hash of the token in user meta.
		update_user_meta( $user->ID, '_ydgl_pending_link_token', $token_hash );
		update_user_meta( $user->ID, '_ydgl_pending_google_id', $google_id );
		update_user_meta( $user->ID, '_ydgl_pending_link_expiry', time() + HOUR_IN_SECONDS );

		if ( ! empty( $redirect_to ) ) {
			update_user_meta( $user->ID, '_ydgl_pending_link_redirect', esc_url_raw( $redirect_to ) );
		} else {
			delete_user_meta( $user->ID, '_ydgl_pending_link_redirect' );
		}

		// Include uid for O(1) lookup + constant-time hash comparison.
		$link = add_query_arg(
			array(
				'ydgl_verify_link' => $token,
				'ydgl_uid'         => $user->ID,
			),
			wc_get_page_permalink( 'myaccount' )
		);

		/* translators: %s: Site name. */
		$subject = sprintf( __( '[%s] Confirm Linking Your Google Account', 'yukdigitalz-login' ), get_bloginfo( 'name' ) );

		/* translators: %s: User display name. */
		$body  = sprintf( __( 'Hello %s,', 'yukdigitalz-login' ), $user->display_name ) . "\r\n\r\n";
		$body .= __( 'We detected a request to sign in to your account using Google. To confirm this request and link your Google account to your existing profile, please click the link below:', 'yukdigitalz-login' ) . "\r\n";
		$body .= esc_url_raw( $link ) . "\r\n\r\n";
		$body .= __( 'This link will expire in 1 hour. If you did not request this, you can safely ignore this email.', 'yukdigitalz-login' ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'yukdigitalz-login' ) . "\r\n";
		$body .= get_bloginfo( 'name' );

		$sent = wp_mail( $user->user_email, $subject, $body );
		if ( ! $sent ) {
			YDGL_Logger::log( 'Failed to send account linking email to ' . YDGL_Security::mask_email( $user->user_email ), 'error' );
			return new WP_Error( 'email_failed', __( 'Failed to send verification email. Please try again.', 'yukdigitalz-login' ) );
		}

		YDGL_Logger::log( 'Sent account linking verification email to ' . YDGL_Security::mask_email( $user->user_email ), 'info' );
		return true;
	}

	/**
	 * Sync the user's Google profile photo URL when Avatar Sync is enabled.
	 *
	 * @param int   $user_id      WordPress User ID.
	 * @param array $user_profile Google profile data.
	 */
	private function sync_google_avatar( $user_id, $user_profile ) {
		if ( 'yes' !== get_option( 'ydgl_avatar_sync', 'yes' ) ) {
			return;
		}

		if ( empty( $user_profile['picture'] ) ) {
			return;
		}

		$picture_url = esc_url_raw( $user_profile['picture'], array( 'https' ) );
		if ( empty( $picture_url ) ) {
			return;
		}

		$host = wp_parse_url( $picture_url, PHP_URL_HOST );
		if ( empty( $host ) || ! preg_match( '/(^|\.)googleusercontent\.com$/i', $host ) ) {
			return;
		}

		update_user_meta( $user_id, '_ydgl_google_avatar', $picture_url );
	}

	/**
	 * Assign a mapped WordPress role to a newly registered user based on email domain.
	 * Strictly blocks privileged/administrative roles to prevent privilege escalation.
	 *
	 * @param int    $user_id WordPress User ID.
	 * @param string $email   User email address.
	 */
	private function apply_domain_role_mapping( $user_id, $email ) {
		$mapping_json = (string) get_option( 'ydgl_role_mapping', '[]' );
		$rules        = json_decode( $mapping_json, true );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return;
		}

		$email_parts = explode( '@', strtolower( $email ) );
		$domain      = end( $email_parts );

		foreach ( $rules as $rule ) {
			if ( empty( $rule['domain'] ) || empty( $rule['role'] ) ) {
				continue;
			}

			if ( strtolower( trim( $rule['domain'] ) ) !== $domain ) {
				continue;
			}

			$role_slug = sanitize_key( $rule['role'] );
			if ( ! self::is_safe_assignable_role( $role_slug ) ) {
				YDGL_Logger::log( 'Role Mapping skipped privileged/invalid role "' . $role_slug . '" for User ID ' . $user_id, 'warning' );
				break;
			}

			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$user->set_role( $role_slug );
				YDGL_Logger::log( 'Role Mapping: Assigned role "' . $role_slug . '" to User ID ' . $user_id . ' for domain @' . $domain, 'info' );
			}
			break;
		}
	}

	/**
	 * Check whether a WordPress role is safe to auto-assign via domain mapping.
	 * Disallows any role with administrative or user/plugin management capabilities.
	 *
	 * @param string $role_slug WordPress role slug.
	 * @return bool
	 */
	public static function is_safe_assignable_role( $role_slug ) {
		$role_obj = get_role( $role_slug );
		if ( ! $role_obj ) {
			return false;
		}

		$blocked_caps = array(
			'manage_options',
			'edit_users',
			'delete_users',
			'create_users',
			'promote_users',
			'install_plugins',
			'activate_plugins',
			'delete_plugins',
			'edit_plugins',
			'manage_network',
		);

		foreach ( $blocked_caps as $cap ) {
			if ( $role_obj->has_cap( $cap ) ) {
				return false;
			}
		}

		return true;
	}
}
