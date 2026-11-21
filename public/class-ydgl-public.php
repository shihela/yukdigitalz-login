<?php
/**
 * Public Front-End Controller.
 *
 * Handles WooCommerce Classic & Block Checkout integration, My Account forms,
 * shortcode, OAuth triggers, account-linking verification, Google One Tap,
 * and Google account disconnection.
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
	 * Track whether checkout promo was already rendered on this request.
	 *
	 * @var bool
	 */
	private $checkout_promo_rendered = false;

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Add Google Sign-In buttons to WooCommerce Classic forms.
		add_action( 'woocommerce_login_form_end', array( $this, 'render_google_login_button' ) );
		add_action( 'woocommerce_register_form_end', array( $this, 'render_google_login_button' ) );
		add_action( 'woocommerce_checkout_login_form_end', array( $this, 'render_google_login_button' ) );

		// Express Google Login banner on Classic Checkout.
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_google_checkout_button' ), 5 );

		// Express Google Login banner on WooCommerce Block Checkout.
		add_filter( 'render_block_woocommerce/checkout-contact-information-block', array( $this, 'render_block_checkout_promo' ), 10, 2 );

		// Connected Google Account management on My Account > Account Details.
		add_action( 'woocommerce_after_edit_account_form', array( $this, 'render_connected_account_section' ) );
		add_action( 'template_redirect', array( $this, 'handle_google_disconnect' ) );

		// Listen for OAuth login trigger and email verification link.
		add_action( 'template_redirect', array( $this, 'handle_login_redirect_trigger' ) );
		add_action( 'template_redirect', array( $this, 'handle_account_link_verification' ) );

		// Display OAuth errors as WooCommerce notices.
		add_action( 'wp', array( $this, 'display_login_errors' ) );

		// Google One Tap AJAX handler (logged-out visitors).
		add_action( 'wp_ajax_nopriv_ydgl_one_tap_login', array( $this, 'handle_one_tap_ajax' ) );

		// Google Avatar Sync filter.
		add_filter( 'get_avatar_url', array( $this, 'filter_get_avatar_url' ), 10, 3 );

		// Register shortcode.
		add_shortcode( 'ydgl_google_login', array( $this, 'render_google_login_shortcode' ) );
	}

	/**
	 * Check if the plugin is enabled and properly configured.
	 *
	 * @return bool
	 */
	private function is_plugin_ready() {
		if ( 'yes' !== get_option( 'ydgl_enabled', 'no' ) ) {
			return false;
		}
		return '' !== trim( (string) get_option( 'ydgl_client_id', '' ) );
	}

	/**
	 * Check if current page is a WooCommerce Account or Checkout page (Classic or Block).
	 *
	 * @return bool
	 */
	private function is_wc_auth_context() {
		$is_account  = function_exists( 'is_account_page' ) && is_account_page();
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout();
		$has_block   = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' );

		return $is_account || $is_checkout || $has_block;
	}

	/**
	 * Enqueue frontend stylesheet and optional Google One Tap scripts.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_plugin_ready() ) {
			return;
		}

		wp_register_style(
			'ydgl-public-styles',
			YDGL_PLUGIN_URL . 'public/css/ydgl-public.css',
			array(),
			YDGL_VERSION
		);

		if ( $this->is_wc_auth_context() ) {
			wp_enqueue_style( 'ydgl-public-styles' );
		}

		// Google One Tap Sign-In (opt-in, logged-out visitors on WC Account/Checkout pages).
		if ( ! is_user_logged_in() && 'yes' === get_option( 'ydgl_enable_one_tap', 'no' ) && $this->is_wc_auth_context() ) {
			wp_enqueue_script(
				'ydgl-google-gsi',
				'https://accounts.google.com/gsi/client',
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External Google Identity Services script.
				true
			);

			wp_enqueue_script(
				'ydgl-one-tap',
				YDGL_PLUGIN_URL . 'public/js/ydgl-one-tap.js',
				array( 'ydgl-google-gsi' ),
				YDGL_VERSION,
				true
			);

			wp_localize_script(
				'ydgl-one-tap',
				'ydglOneTap',
				array(
					'clientId'    => trim( (string) get_option( 'ydgl_client_id', '' ) ),
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'ydgl_one_tap_nonce' ),
					'autoSelect'  => 'yes' === get_option( 'ydgl_one_tap_auto_select', 'no' ),
					'redirectUrl' => $this->resolve_redirect_url(),
					'myAccount'   => wc_get_page_permalink( 'myaccount' ),
				)
			);
		}
	}

	/**
	 * Return official Google "G" SVG icon markup.
	 *
	 * @return string
	 */
	private function get_google_icon_svg() {
		return '<span class="ydgl-google-icon-wrapper" aria-hidden="true">'
			. '<svg class="ydgl-google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">'
			. '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>'
			. '<path fill="#4285F4" d="M46.5 24c0-1.61-.15-3.16-.42-4.69H24v8.87h12.66c-.55 2.92-2.19 5.39-4.67 7.05l7.26 5.63C43.5 36.21 46.5 30.69 46.5 24z"/>'
			. '<path fill="#FBBC05" d="M10.54 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24s.92 7.54 2.56 10.78l7.98-6.19z"/>'
			. '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.26-5.63c-2.03 1.37-4.63 2.19-8.63 2.19-6.26 0-11.57-4.22-13.46-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>'
			. '</svg>'
			. '</span>';
	}

	/**
	 * Resolve post-login redirect URL while preserving query parameters.
	 *
	 * @return string
	 */
	private function resolve_redirect_url() {
		$redirect_type = get_option( 'ydgl_login_redirect_type', 'default' );
		$custom_url    = get_option( 'ydgl_login_redirect_url', '' );

		if ( 'custom' === $redirect_type && ! empty( $custom_url ) ) {
			if ( 0 === strpos( $custom_url, '/' ) ) {
				$redirect_url = home_url( $custom_url );
			} else {
				$redirect_url = esc_url_raw( $custom_url );
			}
		} else {
			$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$redirect_url = ! empty( $request_uri ) ? home_url( $request_uri ) : wc_get_page_permalink( 'myaccount' );
			// Strip ydgl_error from return URL to avoid re-displaying old errors after login.
			$redirect_url = remove_query_arg( array( 'ydgl_error', 'ydgl_verify_link', 'ydgl_uid' ), $redirect_url );
		}

		return apply_filters( 'ydgl_login_redirect_url', $redirect_url, $redirect_type );
	}

	/**
	 * Build the plugin OAuth trigger URL.
	 *
	 * @param string $redirect_url Post-login redirect target.
	 * @return string
	 */
	private function build_login_url( $redirect_url ) {
		return add_query_arg(
			array(
				'ydgl_login' => 'google',
				'redirect'   => rawurlencode( $redirect_url ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Determine whether the Express Checkout promo banner should be displayed.
	 *
	 * @return bool
	 */
	private function should_show_checkout_promo() {
		if ( is_user_logged_in() || ! $this->is_plugin_ready() ) {
			return false;
		}

		if ( 'yes' !== get_option( 'ydgl_checkout_promo_enabled', 'yes' ) ) {
			return false;
		}

		// Optional: restrict banner to carts containing only virtual/digital items.
		if ( 'yes' === get_option( 'ydgl_checkout_promo_digital_only', 'no' ) ) {
			if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
				return false;
			}
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
				if ( $product && ! $product->is_virtual() && ! $product->is_downloadable() ) {
					return false;
				}
			}
		}

		return (bool) apply_filters( 'ydgl_should_show_checkout_promo', true );
	}

	/**
	 * Return the configured Google Sign-In button theme CSS class.
	 *
	 * @return string
	 */
	private function get_button_theme_class() {
		$theme = get_option( 'ydgl_button_theme', 'light' );
		if ( ! in_array( $theme, array( 'light', 'dark', 'neutral' ), true ) ) {
			$theme = 'light';
		}
		return 'ydgl-theme-' . $theme;
	}

	/**
	 * Filter WordPress avatar URL to return synced Google profile picture when enabled.
	 *
	 * @param string $url         Default avatar URL.
	 * @param mixed  $id_or_email User ID, email, WP_User, WP_Post, or WP_Comment.
	 * @param array  $args        Avatar arguments.
	 * @return string
	 */
	public function filter_get_avatar_url( $url, $id_or_email, $args ) {
		if ( 'yes' !== get_option( 'ydgl_avatar_sync', 'yes' ) ) {
			return $url;
		}

		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = absint( $id_or_email );
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = absint( $id_or_email->post_author );
		} elseif ( $id_or_email instanceof WP_Comment && ! empty( $id_or_email->user_id ) ) {
			$user_id = absint( $id_or_email->user_id );
		}

		if ( $user_id > 0 ) {
			$google_avatar = (string) get_user_meta( $user_id, '_ydgl_google_avatar', true );
			if ( ! empty( $google_avatar ) ) {
				return esc_url_raw( $google_avatar );
			}
		}

		return $url;
	}

	/**
	 * Build the Checkout Express Promo banner HTML.
	 *
	 * @return string
	 */
	private function get_checkout_promo_html() {
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$login_url    = $this->build_login_url( $checkout_url );

		$title = get_option( 'ydgl_checkout_promo_title', '' );
		if ( '' === trim( $title ) ) {
			$title = __( 'Express Checkout with Google', 'yukdigitalz-login' );
		}

		$desc = get_option( 'ydgl_checkout_promo_desc', '' );
		if ( '' === trim( $desc ) ) {
			$desc = __( 'Sign in with one click to automatically fill in your name and email.', 'yukdigitalz-login' );
		}

		$checkout_text = get_option( 'ydgl_checkout_button_text', '' );
		if ( '' === trim( $checkout_text ) ) {
			$checkout_text = __( 'Continue with Google', 'yukdigitalz-login' );
		}

		ob_start();
		?>
		<div class="ydgl-checkout-promo">
			<div class="ydgl-checkout-promo-content">
				<span class="ydgl-checkout-promo-icon" aria-hidden="true">&#9889;</span>
				<div class="ydgl-checkout-promo-text">
					<strong><?php echo esc_html( $title ); ?></strong>
					<p><?php echo esc_html( $desc ); ?></p>
				</div>
			</div>
			<div class="ydgl-checkout-promo-btn-wrapper">
				<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn ydgl-checkout-btn <?php echo esc_attr( $this->get_button_theme_class() ); ?>">
					<?php echo $this->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup. ?>
					<span class="ydgl-google-btn-text"><?php echo esc_html( $checkout_text ); ?></span>
				</a>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		return apply_filters( 'ydgl_checkout_promo_html', $html, $login_url, $checkout_text );
	}

	/**
	 * Render the Google Sign-In button on WooCommerce login/register forms.
	 */
	public function render_google_login_button() {
		if ( is_user_logged_in() || ! $this->is_plugin_ready() ) {
			return;
		}

		$redirect_url = $this->resolve_redirect_url();
		$login_url    = $this->build_login_url( $redirect_url );

		$button_text = get_option( 'ydgl_button_text', '' );
		if ( '' === trim( $button_text ) ) {
			$button_text = __( 'Sign in with Google', 'yukdigitalz-login' );
		}

		ob_start();
		?>
		<div class="ydgl-google-login-container">
			<div class="ydgl-separator">
				<span><?php esc_html_e( 'or', 'yukdigitalz-login' ); ?></span>
			</div>
			<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn <?php echo esc_attr( $this->get_button_theme_class() ); ?>">
				<?php echo $this->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup. ?>
				<span class="ydgl-google-btn-text"><?php echo esc_html( $button_text ); ?></span>
			</a>
		</div>
		<?php
		$html = ob_get_clean();

		do_action( 'ydgl_before_google_login_button' );
		echo apply_filters( 'ydgl_google_login_button_html', $html, $login_url, $button_text, 'ydgl-google-login-container' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped internally before filter.
		do_action( 'ydgl_after_google_login_button' );
	}

	/**
	 * Render Express Google Login banner above Classic Checkout form.
	 */
	public function render_google_checkout_button() {
		if ( $this->checkout_promo_rendered || ! $this->should_show_checkout_promo() ) {
			return;
		}

		$this->checkout_promo_rendered = true;
		echo $this->get_checkout_promo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside get_checkout_promo_html().
	}

	/**
	 * Inject Express Google Login banner into WooCommerce Block Checkout.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block metadata.
	 * @return string
	 */
	public function render_block_checkout_promo( $block_content, $block ) {
		if ( $this->checkout_promo_rendered || ! $this->should_show_checkout_promo() ) {
			return $block_content;
		}

		wp_enqueue_style( 'ydgl-public-styles' );
		$this->checkout_promo_rendered = true;

		return $this->get_checkout_promo_html() . $block_content;
	}

	/**
	 * Shortcode callback for [ydgl_google_login].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML button markup.
	 */
	public function render_google_login_shortcode( $atts ) {
		if ( is_user_logged_in() || ! $this->is_plugin_ready() ) {
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

		wp_enqueue_style( 'ydgl-public-styles' );

		$redirect_url = $this->resolve_redirect_url();
		$login_url    = $this->build_login_url( $redirect_url );

		$button_text = ! empty( $args['text'] ) ? sanitize_text_field( $args['text'] ) : get_option( 'ydgl_button_text', '' );
		if ( '' === trim( $button_text ) ) {
			$button_text = __( 'Sign in with Google', 'yukdigitalz-login' );
		}

		$container_class = 'ydgl-google-login-container';
		if ( ! empty( $args['class'] ) ) {
			$container_class .= ' ' . sanitize_html_class( $args['class'] );
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $container_class ); ?>">
			<a href="<?php echo esc_url( $login_url ); ?>" class="ydgl-google-btn <?php echo esc_attr( $this->get_button_theme_class() ); ?>">
				<?php echo $this->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup. ?>
				<span class="ydgl-google-btn-text"><?php echo esc_html( $button_text ); ?></span>
			</a>
		</div>
		<?php
		$html = ob_get_clean();

		return apply_filters( 'ydgl_google_login_button_html', $html, $login_url, $button_text, $container_class );
	}

	/**
	 * Render Connected Google Account section on My Account > Account Details.
	 */
	public function render_connected_account_section() {
		if ( ! is_user_logged_in() || ! $this->is_plugin_ready() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$google_id = get_user_meta( $user_id, '_ydgl_google_id', true );
		if ( empty( $google_id ) ) {
			return;
		}

		$disconnect_url = wp_nonce_url(
			add_query_arg( 'ydgl_action', 'disconnect_google', wc_get_account_endpoint_url( 'edit-account' ) ),
			'ydgl_disconnect_google_' . $user_id
		);
		?>
		<div class="ydgl-account-connection">
			<div class="ydgl-account-connection-info">
				<?php echo $this->get_google_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup. ?>
				<div>
					<strong><?php esc_html_e( 'Google Account Connected', 'yukdigitalz-login' ); ?></strong>
					<p><?php esc_html_e( 'Your store account is currently linked to Google Sign-In. If you disconnect, you can sign in using your email and password (or reset your password).', 'yukdigitalz-login' ); ?></p>
				</div>
			</div>
			<div class="ydgl-account-connection-action">
				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button ydgl-disconnect-btn">
					<?php esc_html_e( 'Disconnect Google', 'yukdigitalz-login' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle nonce-verified request to disconnect a user's linked Google account.
	 */
	public function handle_google_disconnect() {
		if ( ! isset( $_GET['ydgl_action'] ) || 'disconnect_google' !== $_GET['ydgl_action'] ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'ydgl_disconnect_google_' . $user_id ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Security check failed. Please try again.', 'yukdigitalz-login' ), 'error' );
			}
			wp_safe_redirect( wc_get_account_endpoint_url( 'edit-account' ) );
			exit;
		}

		$google_id = (string) get_user_meta( $user_id, '_ydgl_google_id', true );
		delete_user_meta( $user_id, '_ydgl_google_id' );
		delete_user_meta( $user_id, '_ydgl_google_avatar' );
		if ( ! empty( $google_id ) ) {
			wp_cache_delete( 'ydgl_uid_' . hash( 'sha256', $google_id ), 'ydgl' );
		}
		YDGL_Logger::log( 'User ID ' . $user_id . ' disconnected their Google account.', 'info' );

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Your Google account has been disconnected.', 'yukdigitalz-login' ), 'success' );
		}

		wp_safe_redirect( wc_get_account_endpoint_url( 'edit-account' ) );
		exit;
	}

	/**
	 * Catch the login trigger, create cookie-bound state + PKCE session, and redirect to Google.
	 */
	public function handle_login_redirect_trigger() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public OAuth initiation link; CSRF is protected via cookie-bound state + PKCE.
		if ( ! isset( $_GET['ydgl_login'] ) || 'google' !== $_GET['ydgl_login'] ) {
			return;
		}

		if ( ! $this->is_plugin_ready() ) {
			return;
		}

		$oauth = new YDGL_OAuth();

		$default_redirect = wc_get_page_permalink( 'myaccount' );
		$redirect_to      = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : $default_redirect;
		$redirect_to      = wp_validate_redirect( $redirect_to, $default_redirect );

		$session  = $oauth->create_oauth_session( $redirect_to );
		$auth_url = $oauth->get_auth_url( $session['state'], $session['code_challenge'] );

		if ( empty( $auth_url ) ) {
			YDGL_Logger::log( 'Authorization URL generation failed: Client ID is empty.', 'error' );
			wp_safe_redirect( add_query_arg( 'ydgl_error', 'config_missing', $default_redirect ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External redirect to Google OAuth endpoint is required.
		wp_redirect( esc_url_raw( $auth_url ) );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Intercept and process account-linking verification from email link.
	 */
	public function handle_account_link_verification() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verification link from email inbox; validated with SHA-256 token hash.
		if ( ! isset( $_GET['ydgl_verify_link'] ) ) {
			return;
		}

		$raw_token = sanitize_text_field( wp_unslash( $_GET['ydgl_verify_link'] ) );
		$uid       = isset( $_GET['ydgl_uid'] ) ? absint( $_GET['ydgl_uid'] ) : 0;

		if ( empty( $raw_token ) ) {
			return;
		}

		$user       = false;
		$token_hash = hash( 'sha256', $raw_token );

		if ( $uid > 0 ) {
			$candidate   = get_user_by( 'id', $uid );
			$stored_hash = $candidate ? (string) get_user_meta( $candidate->ID, '_ydgl_pending_link_token', true ) : '';

			if ( $candidate && ! empty( $stored_hash ) && hash_equals( $stored_hash, $token_hash ) ) {
				$user = $candidate;
			}
		}

		$default_redirect = wc_get_page_permalink( 'myaccount' );

		if ( ! $user ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Invalid or expired verification link.', 'yukdigitalz-login' ), 'error' );
			}
			wp_safe_redirect( $default_redirect );
			exit;
		}

		$expiry = (int) get_user_meta( $user->ID, '_ydgl_pending_link_expiry', true );
		if ( time() > $expiry ) {
			$this->cleanup_pending_link_meta( $user->ID );
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'The verification link has expired. Please try signing in with Google again.', 'yukdigitalz-login' ), 'error' );
			}
			wp_safe_redirect( $default_redirect );
			exit;
		}

		$processor   = new YDGL_Processor();
		$admin_check = $processor->verify_admin_login_policy( $user );
		if ( is_wp_error( $admin_check ) ) {
			$this->cleanup_pending_link_meta( $user->ID );
			wp_safe_redirect( add_query_arg( 'ydgl_error', 'admin_login_blocked', $default_redirect ) );
			exit;
		}

		// Link account and restore intended redirect URL (e.g. Checkout).
		$google_id   = (string) get_user_meta( $user->ID, '_ydgl_pending_google_id', true );
		$redirect_to = (string) get_user_meta( $user->ID, '_ydgl_pending_link_redirect', true );
		$redirect_to = ! empty( $redirect_to ) ? wp_validate_redirect( esc_url_raw( $redirect_to ), $default_redirect ) : $default_redirect;

		update_user_meta( $user->ID, '_ydgl_google_id', $google_id );
		$this->cleanup_pending_link_meta( $user->ID );

		YDGL_Logger::log( 'User ID ' . $user->ID . ' verified email and linked Google account.', 'info' );

		$processor->login_user( $user );

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Your Google account has been successfully linked. You are now logged in.', 'yukdigitalz-login' ), 'success' );
		}

		wp_safe_redirect( $redirect_to );
		exit;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Handle Google One Tap AJAX sign-in request.
	 */
	public function handle_one_tap_ajax() {
		if ( ! $this->is_plugin_ready() || 'yes' !== get_option( 'ydgl_enable_one_tap', 'no' ) ) {
			wp_send_json_error( array( 'message' => __( 'Google One Tap is not enabled.', 'yukdigitalz-login' ) ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ydgl_one_tap_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'yukdigitalz-login' ) ), 403 );
		}

		$credential = isset( $_POST['credential'] ) ? sanitize_text_field( wp_unslash( $_POST['credential'] ) ) : '';
		if ( empty( $credential ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing Google credential.', 'yukdigitalz-login' ) ), 400 );
		}

		$default_redirect = wc_get_page_permalink( 'myaccount' );
		$redirect_to      = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : $default_redirect;
		$redirect_to      = wp_validate_redirect( $redirect_to, $default_redirect );

		$oauth        = new YDGL_OAuth();
		$user_profile = $oauth->verify_id_token( $credential );

		if ( is_wp_error( $user_profile ) ) {
			wp_send_json_error(
				array(
					'code'    => $user_profile->get_error_code(),
					'message' => $user_profile->get_error_message(),
				),
				400
			);
		}

		$processor = new YDGL_Processor();
		$user_id   = $processor->process_user_sign_in( $user_profile, $redirect_to );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error(
				array(
					'code'    => $user_id->get_error_code(),
					'message' => $user_id->get_error_message(),
				),
				400
			);
		}

		YDGL_Logger::log( 'User ' . YDGL_Security::mask_email( $user_profile['email'] ) . ' (ID: ' . $user_id . ') authenticated via Google One Tap.', 'info' );

		wp_send_json_success(
			array(
				'redirect' => $redirect_to,
			)
		);
	}

	/**
	 * Clean up temporary pending account-linking user meta.
	 *
	 * @param int $user_id User ID.
	 */
	private function cleanup_pending_link_meta( $user_id ) {
		delete_user_meta( $user_id, '_ydgl_pending_link_token' );
		delete_user_meta( $user_id, '_ydgl_pending_google_id' );
		delete_user_meta( $user_id, '_ydgl_pending_link_expiry' );
		delete_user_meta( $user_id, '_ydgl_pending_link_redirect' );
	}

	/**
	 * Display login errors as standard WooCommerce notices.
	 */
	public function display_login_errors() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only error notice code mapped via strict allowlist.
		if ( ! isset( $_GET['ydgl_error'] ) ) {
			return;
		}

		$error_code  = sanitize_key( wp_unslash( $_GET['ydgl_error'] ) );
		$message     = __( 'An error occurred while signing in with Google. Please try again.', 'yukdigitalz-login' );
		$notice_type = 'error';

		switch ( $error_code ) {
			case 'google_error':
				$message = __( 'Google API returned an error. Please try again.', 'yukdigitalz-login' );
				break;
			case 'invalid_state':
				$message = __( 'Security session validation failed. Please try again.', 'yukdigitalz-login' );
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
			case 'admin_login_blocked':
				$message = __( 'Google Sign-In is disabled for administrator accounts for security reasons. Please sign in with your password.', 'yukdigitalz-login' );
				break;
			case 'registration_disabled':
				$message = __( 'New account registration is disabled by the administrator.', 'yukdigitalz-login' );
				break;
			case 'config_missing':
				$message = __( 'Google Login configuration has not been completed by the administrator.', 'yukdigitalz-login' );
				break;
			case 'pending_verification':
				$message     = __( 'An account already exists with this email. A verification link has been sent to your email to link your Google account.', 'yukdigitalz-login' );
				$notice_type = 'notice';
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
