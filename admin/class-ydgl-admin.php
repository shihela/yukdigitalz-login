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
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook = '';

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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . YDGL_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add Settings page link inside WooCommerce menu.
	 */
	public function add_settings_page() {
		$this->page_hook = add_submenu_page(
			'woocommerce',
			__( 'Google Login Settings', 'yukdigitalz-login' ),
			__( 'Google Login', 'yukdigitalz-login' ),
			'manage_options',
			'ydgl-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( empty( $this->page_hook ) || $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'ydgl-admin-styles',
			YDGL_PLUGIN_URL . 'admin/css/ydgl-admin.css',
			array(),
			YDGL_VERSION
		);

		wp_enqueue_script(
			'ydgl-admin-script',
			YDGL_PLUGIN_URL . 'admin/js/ydgl-admin.js',
			array(),
			YDGL_VERSION,
			true
		);

		wp_localize_script(
			'ydgl-admin-script',
			'ydglAdmin',
			array(
				'tabs' => array_keys( $this->get_settings_tabs() ),
			)
		);
	}

	/**
	 * Add settings action link to the plugin listings page.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$settings_url  = esc_url( admin_url( 'admin.php?page=ydgl-settings' ) );
		$settings_link = '<a href="' . $settings_url . '">' . esc_html__( 'Settings', 'yukdigitalz-login' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Register settings fields via WordPress Settings API.
	 */
	public function register_settings() {
		register_setting( 'ydgl-settings-group', 'ydgl_enabled', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_client_id', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_client_secret', array( $this, 'encrypt_client_secret' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_enable_registration', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_verify_account_link', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_block_admin_login', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_domain_restriction', array( $this, 'sanitize_domain_restriction' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_role_mapping', array( $this, 'sanitize_role_mapping' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_avatar_sync', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_enable_one_tap', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_one_tap_auto_select', array( $this, 'sanitize_checkbox' ) );

		// Button & Checkout Promo settings.
		register_setting( 'ydgl-settings-group', 'ydgl_button_theme', array( $this, 'sanitize_button_theme' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_button_text', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_promo_enabled', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_promo_digital_only', array( $this, 'sanitize_checkbox' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_promo_title', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_promo_desc', 'sanitize_text_field' );
		register_setting( 'ydgl-settings-group', 'ydgl_checkout_button_text', 'sanitize_text_field' );

		// Redirect settings.
		register_setting( 'ydgl-settings-group', 'ydgl_login_redirect_type', array( $this, 'sanitize_redirect_type' ) );
		register_setting( 'ydgl-settings-group', 'ydgl_login_redirect_url', array( $this, 'sanitize_redirect_url' ) );
	}

	/**
	 * Sanitize checkbox options to strict 'yes' or 'no'.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_checkbox( $input ) {
		return ( 'yes' === $input ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize redirect type option.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_redirect_type( $input ) {
		return ( 'custom' === $input ) ? 'custom' : 'default';
	}

	/**
	 * Sanitize Google button theme preset.
	 *
	 * @param mixed $input Raw input.
	 * @return string
	 */
	public function sanitize_button_theme( $input ) {
		return in_array( $input, array( 'light', 'dark', 'neutral' ), true ) ? $input : 'light';
	}

	/**
	 * Sanitize domain-to-role mapping JSON string.
	 * Strictly rejects privileged/administrative roles.
	 *
	 * @param string $input Raw JSON string.
	 * @return string Sanitized JSON string.
	 */
	public function sanitize_role_mapping( $input ) {
		if ( empty( $input ) ) {
			return '[]';
		}

		$data = json_decode( wp_unslash( (string) $input ), true );
		if ( ! is_array( $data ) ) {
			return '[]';
		}

		$sanitized    = array();
		$seen_domains = array();

		foreach ( $data as $rule ) {
			if ( ! isset( $rule['domain'], $rule['role'] ) ) {
				continue;
			}

			$domain = strtolower( ltrim( trim( sanitize_text_field( $rule['domain'] ) ), '@' ) );
			$role   = sanitize_key( $rule['role'] );

			if ( empty( $domain ) || empty( $role ) || isset( $seen_domains[ $domain ] ) ) {
				continue;
			}

			if ( class_exists( 'YDGL_Processor' ) && ! YDGL_Processor::is_safe_assignable_role( $role ) ) {
				continue;
			}

			$seen_domains[ $domain ] = true;
			$sanitized[]             = array(
				'domain' => $domain,
				'role'   => $role,
			);
		}

		return wp_json_encode( $sanitized );
	}

	/**
	 * Return non-privileged WordPress roles safe for domain role mapping.
	 *
	 * @return array<string, string> Map of role_slug => translated role label.
	 */
	public function get_assignable_roles() {
		$safe_roles = array();
		$all_roles  = wp_roles()->roles;

		foreach ( $all_roles as $role_slug => $details ) {
			if ( class_exists( 'YDGL_Processor' ) && YDGL_Processor::is_safe_assignable_role( $role_slug ) ) {
				$safe_roles[ $role_slug ] = translate_user_role( $details['name'] );
			}
		}

		return $safe_roles;
	}

	/**
	 * Sanitize redirect URL (supports relative paths like /shop or full URLs).
	 *
	 * @param string $input Raw input.
	 * @return string Sanitized input.
	 */
	public function sanitize_redirect_url( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return '';
		}

		// Allow relative paths starting with '/'.
		if ( 0 === strpos( $input, '/' ) ) {
			return '/' . ltrim( sanitize_text_field( $input ), '/' );
		}

		return esc_url_raw( $input );
	}

	/**
	 * Encrypt Client Secret upon saving to database.
	 * Preserves existing encrypted secret when the password input is left blank.
	 *
	 * @param string $input Raw secret key.
	 * @return string Encrypted secret key.
	 */
	public function encrypt_client_secret( $input ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is already verified by WordPress options.php.
		if ( isset( $_POST['ydgl_clear_client_secret'] ) && 'yes' === $_POST['ydgl_clear_client_secret'] ) {
			return '';
		}

		$clean = trim( sanitize_text_field( (string) $input ) );

		// Keep existing saved secret when admin saves other settings without re-entering the secret.
		if ( '' === $clean ) {
			return (string) get_option( 'ydgl_client_secret', '' );
		}

		return YDGL_Security::encrypt( $clean );
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

		$domains   = explode( ',', (string) $input );
		$sanitized = array();
		foreach ( $domains as $domain ) {
			$clean = trim( sanitize_text_field( $domain ) );
			$clean = ltrim( $clean, '@' );
			if ( ! empty( $clean ) ) {
				$sanitized[] = strtolower( $clean );
			}
		}

		return implode( ', ', array_unique( $sanitized ) );
	}

	/**
	 * Retrieve and format the latest audit logs from WooCommerce log files.
	 * Supports WooCommerce 8.6+ FileController and legacy WC_Log_Handler_File.
	 *
	 * @param string $file_name Specific log file key to read, optional.
	 * @return array Logs array.
	 */
	public function get_audit_logs( $file_name = '' ) {
		$logs = array(
			'files'        => array(),
			'current_file' => '',
			'entries'      => array(),
		);

		$ydgl_files = $this->discover_log_files();
		if ( empty( $ydgl_files ) ) {
			return $logs;
		}

		krsort( $ydgl_files );

		$current_file = ( ! empty( $file_name ) && isset( $ydgl_files[ $file_name ] ) ) ? $file_name : key( $ydgl_files );
		$file_path    = $ydgl_files[ $current_file ];

		$entries = array();
		if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
			$lines = $this->read_tail_lines( $file_path, 150 );

			foreach ( $lines as $line ) {
				if ( '' === $line ) {
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

			$entries = array_reverse( $entries );
		}

		return array(
			'files'        => array_keys( $ydgl_files ),
			'current_file' => $current_file,
			'entries'      => $entries,
		);
	}

	/**
	 * Discover WooCommerce log files belonging to yukdigitalz-login.
	 *
	 * @return array<string, string> Map of log key => absolute file path.
	 */
	private function discover_log_files() {
		$ydgl_files = array();

		// WooCommerce 8.6+ FileV2 controller.
		if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController' ) && function_exists( 'wc_get_container' ) ) {
			try {
				$controller = wc_get_container()->get( \Automattic\WooCommerce\Internal\Admin\Logging\FileV2\FileController::class );
				$files      = $controller->get_files(
					array(
						'source'   => 'yukdigitalz-login',
						'per_page' => 20,
						'orderby'  => 'modified',
						'order'    => 'desc',
					)
				);

				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						if ( is_object( $file ) && method_exists( $file, 'get_file_id' ) && method_exists( $file, 'get_path' ) ) {
							$ydgl_files[ $file->get_file_id() ] = $file->get_path();
						}
					}
					return $ydgl_files;
				}
			} catch ( Exception $e ) {
				// Fall through to legacy handler.
			}
		}

		// Legacy WooCommerce log handler fallback.
		if ( class_exists( 'WC_Log_Handler_File' ) ) {
			$handler = new WC_Log_Handler_File();
			$files   = $handler->get_log_files();

			foreach ( $files as $key => $path ) {
				if ( 0 === strpos( $key, 'yukdigitalz-login' ) ) {
					$ydgl_files[ $key ] = $path;
				}
			}
		}

		return $ydgl_files;
	}

	/**
	 * Read the last N lines of a file without loading the entire file into memory.
	 *
	 * @param string $file_path Absolute path to log file.
	 * @param int    $max_lines Maximum number of trailing lines to return.
	 * @return array<string>
	 */
	private function read_tail_lines( $file_path, $max_lines = 150 ) {
		try {
			$file      = new SplFileObject( $file_path, 'r' );
			$file_size = $file->getSize();
			$max_bytes = 65536; // 64 KB bounded tail read.

			if ( $file_size > $max_bytes ) {
				$file->fseek( $file_size - $max_bytes );
				// Discard partial first line after seeking.
				$file->fgets();
			}

			$buffer = array();
			while ( ! $file->eof() ) {
				$line = trim( (string) $file->fgets() );
				if ( '' !== $line ) {
					$buffer[] = $line;
				}
			}

			return array_slice( $buffer, -$max_lines );
		} catch ( Exception $e ) {
			return array();
		}
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

		include_once YDGL_PLUGIN_DIR . 'admin/views/admin-settings-display.php';
	}
}
