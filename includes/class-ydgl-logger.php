<?php
/**
 * Enterprise Audit Logger for YDGL.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Logger {

	/**
	 * Log a message for auditing.
	 *
	 * @param string $message The message to log.
	 * @param string $level   Log level: info, warning, error, critical.
	 */
	public static function log( $message, $level = 'info' ) {
		// Only log if WooCommerce logger is available, otherwise fallback to php error_log.
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger  = wc_get_logger();
			$context = array( 'source' => 'yukdigitalz-login' );
			
			switch ( $level ) {
				case 'error':
					$logger->error( $message, $context );
					break;
				case 'warning':
					$logger->warning( $message, $context );
					break;
				case 'critical':
					$logger->critical( $message, $context );
					break;
				case 'info':
				default:
					$logger->info( $message, $context );
					break;
			}
		} else {
			// Fail-safe default php error log.
			// phpcs:ignore
			error_log( sprintf( '[YDGL - %s] %s', strtoupper( $level ), $message ) );
		}
	}
}
