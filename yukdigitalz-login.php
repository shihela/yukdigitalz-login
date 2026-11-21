<?php
/**
 * Plugin Name: YukDigitalz Login
 * Plugin URI:  https://yukdigitalz.com/yukdigitalz-login
 * Description: Lightweight, secure Google OAuth 2.0 login & registration for WooCommerce customers. Supports Classic & Block Checkout, HPOS, PKCE, and Google One Tap.
 * Version:     1.2.0
 * Author:      Shihela
 * Author URI:  https://yukdigitalz.com
 * Text Domain: yukdigitalz-login
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.8
 *
 * @package YDGL
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'YDGL_VERSION', '1.2.0' );
define( 'YDGL_PLUGIN_FILE', __FILE__ );
define( 'YDGL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YDGL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YDGL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS and Cart/Checkout Blocks).
 */
function ydgl_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', YDGL_PLUGIN_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', YDGL_PLUGIN_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'ydgl_declare_wc_compatibility' );

/**
 * Display an admin notice when WooCommerce is not active.
 */
function ydgl_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: Plugin name. */
					__( '<strong>%s</strong> requires WooCommerce to be installed and active.', 'yukdigitalz-login' ),
					'YukDigitalz Login'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Runs the plugin bootstrap once all plugins are loaded.
 */
function ydgl_run_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ydgl_woocommerce_missing_notice' );
		return;
	}

	require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-core.php';

	if ( class_exists( 'YDGL_Core' ) ) {
		YDGL_Core::get_instance();
	}
}
add_action( 'plugins_loaded', 'ydgl_run_plugin', 20 );
