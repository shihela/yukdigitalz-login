<?php
/**
 * Admin Panel Configuration.
 *
 * @package YDGL/Admin
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var YDGL_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get active instance.
	 *
	 * @return YDGL_Admin
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . YDGL_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add Settings page link inside WooCommerce menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Google Login Settings', 'yukdigitalz-login' ),
			__( 'Google Login', 'yukdigitalz-login' ),
			'manage_options',
			'ydgl-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Add settings action link to the plugin listings page.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$settings_link = '<a href="admin.php?page=ydgl-settings">' . __( 'Settings', 'yukdigitalz-login' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register settings fields via WordPress Settings API.
	 */
	public function register_settings() {
		register_setting( 'ydgl-settings-group', 'ydgl_enabled', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_client_id', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_client_secret', array( $this, 'encrypt_client_secret' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_enable_registration', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_verify_account_link', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_domain_restriction', array( $this, 'sanitize_domain_restriction' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_button_text', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_button_text', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_login_redirect_type', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_login_redirect_url', array( $this, 'sanitize_redirect_url' ) );
	}

	/**
	 * Sanitize redirect URL.
	 *
	 * @param string $input Raw input.
	 * @return string Sanitized input.
	 */
	public function sanitize_redirect_url( $input ) {
		return esc_url_raw( trim( $input ) );
	}

	/**
	 * Retrieve and format the latest audit logs from WooCommerce log files.
	 *
	 * @param string $file_name Specific log file to read, optional.
	 * @return array Logs array.
	 */
	public function get_audit_logs( $file_name = '' ) {
		$logs = array(
			'files'        => array(),
			'current_file' => '',
			'entries'      => array(),
		);
		if ( ! class_exists( 'WC_Log_Handler_File' ) ) {
			return $logs;
		}

		$handler = new WC_Log_Handler_File();
		$files   = $handler->get_log_files();

		// Find files starting with 'yukdigitalz-login'.
		$ydgl_files = array();
		foreach ( $files as $key => $path ) {
			if ( 0 === strpos( $key, 'yukdigitalz-login' ) ) {
				$ydgl_files[ $key ] = $path;
			}
		}

		// Sort by key descending to get the latest first.
		krsort( $ydgl_files );

		if ( empty( $ydgl_files ) ) {
			return $logs;
		}

		// Use requested file, or fallback to the latest one.
		$current_file = ! empty( $file_name ) && isset( $ydgl_files[ $file_name ] ) ? $file_name : key( $ydgl_files );
		$file_path    = $ydgl_files[ $current_file ];

		$entries = array();
		if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
			// Read the file line-by-line to prevent memory limits exhaustion.
			$handle = fopen( $file_path, 'r' );
			if ( $handle ) {
				$buffer = array();
				while ( ( $line = fgets( $handle ) ) !== false ) {
					$buffer[] = trim( $line );
				}
				fclose( $handle );

				// Limit to the last 150 lines.
				$buffer = array_slice( $buffer, -150 );

				// Parse each line. Format: 2026-07-10T04:44:35+00:00 INFO Message
				foreach ( $buffer as $line ) {
					if ( empty( $line ) ) {
						continue;
					}

					$parts = explode( ' ', $line, 3 );
					if ( count( $parts ) >= 3 ) {
						$timestamp = trim( $parts[0] );
						$level     = strtolower( trim( $parts[1] ) );
						$message   = trim( $parts[2] );
						
						if ( ! in_array( $level, array( 'info', 'warning', 'error', 'critical' ), true ) ) {
							$level = 'info';
						}

						$entries[] = array(
							'timestamp' => $timestamp,
							'level'     => $level,
							'message'   => $message,
						);
					} else {
						$entries[] = array(
							'timestamp' => '',
							'level'     => 'info',
							'message'   => $line,
						);
					}
				}
				
				// Reverse to show the newest at the top.
				$entries = array_reverse( $entries );
			}
		}

		return array(
			'files'        => array_keys( $ydgl_files ),
			'current_file' => $current_file,
			'entries'      => $entries,
		);
	}

	/**
	 * Encrypt Client Secret upon saving to database.
	 *
	 * @param string $input Raw secret key.
	 * @return string Encrypted secret key.
	 */
	public function encrypt_client_secret( $input ) {
		return YDGL_Security::encrypt( sanitize_text_field( $input ) );
	}

	/**
	 * Custom sanitizer for domain whitelist.
	 *
	 * @param string $input Textarea input.
	 * @return string
	 */
	public function sanitize_domain_restriction( $input ) {
		if ( empty( $input ) ) {
			return '';
		}

		// Split by comma, sanitize each, recombine.
		$domains = explode( ',', $input );
		$sanitized = array();
		foreach ( $domains as $domain ) {
			$clean = trim( sanitize_text_field( $domain ) );
			// Simple validation check: remove leading '@' if user added it.
			$clean = ltrim( $clean, '@' );
			if ( ! empty( $clean ) ) {
				$sanitized[] = strtolower( $clean );
			}
		}

		return implode( ', ', $sanitized );
	}

	/**
	 * Retrieve settings tabs, allowing registration via filters.
	 *
	 * @return array
	 */
	public function get_settings_tabs() {
		$tabs = array(
			'settings' => __( 'General Settings', 'yukdigitalz-login' ),
			'logs'     => __( 'Audit Logs', 'yukdigitalz-login' ),
		);

		return apply_filters( 'ydgl_settings_tabs', $tabs );
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yukdigitalz-login' ) );
		}

		// Load settings view.
		include_once YDGL_PLUGIN_DIR . 'admin/views/admin-settings-display.php';
	}
}
