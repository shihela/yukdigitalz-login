<?php
/**
 * Security Encryption Helper.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Security {

	/**
	 * Encrypt data using WP secure salts.
	 *
	 * @param string $value Raw string.
	 * @return string Encrypted string.
	 */
	public static function encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $value; // Fallback if OpenSSL is not enabled.
		}

		$key = self::get_encryption_key();
		if ( empty( $key ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$iv_length = openssl_cipher_iv_length( $method );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $value, $method, $key, 0, $iv );
		if ( false === $encrypted ) {
			return $value;
		}

		return base64_encode( $iv . '::' . $encrypted );
	}

	/**
	 * Decrypt data using WP secure salts.
	 *
	 * @param string $value Encrypted string.
	 * @return string Decrypted string.
	 */
	public static function decrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}

		$decoded = base64_decode( $value );
		if ( ! $decoded || strpos( $decoded, '::' ) === false ) {
			return $value; // Return as-is if not encrypted in expected format.
		}

		$parts = explode( '::', $decoded, 2 );
		if ( count( $parts ) !== 2 ) {
			return $value;
		}

		$iv = $parts[0];
		$encrypted = $parts[1];

		$key = self::get_encryption_key();
		if ( empty( $key ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );

		return ( false !== $decrypted ) ? $decrypted : $value;
	}

	/**
	 * Get encryption key from wp-config salts.
	 *
	 * @return string
	 */
	private static function get_encryption_key() {
		if ( defined( 'SECURE_AUTH_KEY' ) && ! empty( SECURE_AUTH_KEY ) ) {
			return SECURE_AUTH_KEY;
		}
		if ( defined( 'LOGGED_IN_KEY' ) && ! empty( LOGGED_IN_KEY ) ) {
			return LOGGED_IN_KEY;
		}
		return '';
	}
}
