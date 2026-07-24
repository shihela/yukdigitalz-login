<?php
/**
 * Main plugin bootstrap class.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Core {

	/**
	 * Singleton instance.
	 *
	 * @var YDGL_Core|null
	 */
	private static $instance = null;

	/**
	 * Get active instance.
	 *
	 * @return YDGL_Core
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
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load core dependencies.
	 */
	private function load_dependencies() {
		require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-logger.php';
		require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-security.php';
		require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-oauth.php';
		require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-processor.php';

		if ( is_admin() ) {
			require_once YDGL_PLUGIN_DIR . 'admin/class-ydgl-admin.php';
		}

		require_once YDGL_PLUGIN_DIR . 'public/class-ydgl-public.php';
	}

	/**
	 * Register actions and filters.
	 */
	private function init_hooks() {
		// Initialize Admin Settings.
		if ( is_admin() ) {
			YDGL_Admin::get_instance();
		}

		// Initialize Public Frontend Hooks.
		YDGL_Public::get_instance();

		// Hook into WooCommerce API callback.
		add_action( 'woocommerce_api_ydgl_google_login', array( $this, 'handle_google_oauth_callback' ) );
	}

	/**
	 * Handles the Google OAuth callback redirect via WooCommerce API.
	 */
	public function handle_google_oauth_callback() {
		if ( class_exists( 'YDGL_OAuth' ) ) {
			$oauth = new YDGL_OAuth();
			$oauth->handle_callback();
		}
		exit;
	}
}
