<?php
/**
 * Plugin Name: YukDigitalz Login
 * Plugin URI:  https://yukdigitalz.com/yukdigitalz-login
 * Description: Bridge login/register for WooCommerce customers using a Google (Gmail) account. Enterprise-grade security.
 * Version:     1.0.0
 * Author:      Shihela
 * Author URI:  https://yukdigitalz.com
 * Text Domain: yukdigitalz-login
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 *
 * @package YDGL
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'YDGL_VERSION', '1.0.0' );
define( 'YDGL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YDGL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YDGL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include core class.
require_once YDGL_PLUGIN_DIR . 'includes/class-ydgl-core.php';

/**
 * Runs the plugin bootstrap.
 */
function ydgl_run_plugin() {
	if ( class_exists( 'YDGL_Core' ) ) {
		YDGL_Core::get_instance();
	}
}
add_action( 'plugins_loaded', 'ydgl_run_plugin' );
