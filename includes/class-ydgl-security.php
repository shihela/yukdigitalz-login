<?php
/**
 * Security & Cryptography Helper.
 *
 * Provides authenticated encryption (AES-256-GCM with HMAC-CBC fallback),
 * cryptographically secure token generation, and PII masking for logs.
 *
 * @package YDGL/Includes
 */

defined( 'ABSPATH' ) || exit;

class YDGL_Security {

	/**
	 * Version prefix for AES-256-GCM ciphertexts.
	 */
	const PREFIX_GCM = 'v2:gcm:';

	/**
	 * Version prefix for AES-256-CBC + HMAC-SHA256 ciphertexts.
	 */
	const PREFIX_CBC = 'v2:cbc:';

	/**
	 * Encrypt data using authenticated encryption and WP secret keys.
	 *
	 * @param string $value Raw plaintext string.
	 * @return string Encrypted string, or original value if OpenSSL/keys are unavailable.
	 */
	public static function encrypt( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return $value;
		}

		$key = self::get_derived_key();
		if ( empty( $key ) ) {
			return $value;
		}

		// Prefer AES-256-GCM (AEAD).
		if ( in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			try {
				$iv = random_bytes( 12 );
			} catch ( Exception $e ) {
				return $value;
			}

			$tag        = '';
			$ciphertext = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

			if ( false !== $ciphertext && 16 === strlen( $tag ) ) {
				return self::PREFIX_GCM . base64_encode( $iv . $tag . $ciphertext );
			}
		}

		// Fallback: AES-256-CBC + Encrypt-then-MAC (HMAC-SHA256).
		$method    = 'aes-256-cbc';
		$iv_length = openssl_cipher_iv_length( $method );
		if ( ! $iv_length ) {
			return $value;
		}

		try {
			$iv = random_bytes( $iv_length );
		} catch ( Exception $e ) {
			return $value;
		}

		$enc_key    = hash_hmac( 'sha256', 'ydgl-enc', $key, true );
		$mac_key    = hash_hmac( 'sha256', 'ydgl-mac', $key, true );
		$ciphertext = openssl_encrypt( $value, $method, $enc_key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return $value;
		}

		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true );

		return self::PREFIX_CBC . base64_encode( $iv . $mac . $ciphertext );
	}

	/**
	 * Decrypt data encrypted with encrypt() (supports v2 GCM, v2 CBC+HMAC, and legacy v1 CTR).
	 *
	 * @param string $value Encrypted string.
	 * @return string Decrypted plaintext string, or empty string on authentication failure.
	 */
	public static function decrypt( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}

		// Handle v2 AES-256-GCM.
		if ( 0 === strpos( $value, self::PREFIX_GCM ) ) {
			$key = self::get_derived_key();
			if ( empty( $key ) ) {
				return '';
			}

			$raw = base64_decode( substr( $value, strlen( self::PREFIX_GCM ) ), true );
			if ( false === $raw || strlen( $raw ) <= 28 ) {
				return '';
			}

			$iv         = substr( $raw, 0, 12 );
			$tag        = substr( $raw, 12, 16 );
			$ciphertext = substr( $raw, 28 );

			$decrypted = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return ( false !== $decrypted ) ? $decrypted : '';
		}

		// Handle v2 AES-256-CBC + HMAC-SHA256.
		if ( 0 === strpos( $value, self::PREFIX_CBC ) ) {
			$key = self::get_derived_key();
			if ( empty( $key ) ) {
				return '';
			}

			$method    = 'aes-256-cbc';
			$iv_length = openssl_cipher_iv_length( $method );
			$raw       = base64_decode( substr( $value, strlen( self::PREFIX_CBC ) ), true );

			if ( false === $raw || strlen( $raw ) <= ( $iv_length + 32 ) ) {
				return '';
			}

			$iv         = substr( $raw, 0, $iv_length );
			$mac        = substr( $raw, $iv_length, 32 );
			$ciphertext = substr( $raw, $iv_length + 32 );

			$enc_key      = hash_hmac( 'sha256', 'ydgl-enc', $key, true );
			$mac_key      = hash_hmac( 'sha256', 'ydgl-mac', $key, true );
			$expected_mac = hash_hmac( 'sha256', $iv . $ciphertext, $mac_key, true );

			if ( ! hash_equals( $expected_mac, $mac ) ) {
				return '';
			}

			$decrypted = openssl_decrypt( $ciphertext, $method, $enc_key, OPENSSL_RAW_DATA, $iv );
			return ( false !== $decrypted ) ? $decrypted : '';
		}

		// Backward compatibility: Legacy v1 AES-256-CTR (base64(iv . '::' . base64_cipher)).
		return self::decrypt_legacy_v1( $value );
	}

	/**
	 * Decrypt legacy v1 (aes-256-ctr) encrypted values for seamless upgrades.
	 *
	 * @param string $value Legacy encrypted string.
	 * @return string
	 */
	private static function decrypt_legacy_v1( $value ) {
		$decoded = base64_decode( $value, true );
		if ( false === $decoded ) {
			return $value;
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-ctr' );
		if ( strlen( $decoded ) <= ( $iv_length + 2 ) || '::' !== substr( $decoded, $iv_length, 2 ) ) {
			return $value;
		}

		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length + 2 );
		$raw_key   = self::get_raw_key();

		if ( empty( $raw_key ) ) {
			return $value;
		}

		$decrypted = openssl_decrypt( $encrypted, 'aes-256-ctr', $raw_key, 0, $iv );
		return ( false !== $decrypted ) ? $decrypted : '';
	}

	/**
	 * Check whether a stored encrypted value can be decrypted (i.e. keys haven't rotated).
	 *
	 * @param string $stored_value Value from wp_options.
	 * @return bool
	 */
	public static function has_valid_secret( $stored_value ) {
		if ( empty( $stored_value ) ) {
			return false;
		}
		return '' !== self::decrypt( $stored_value );
	}

	/**
	 * Generate a cryptographically secure random hex token.
	 *
	 * @param int $bytes Number of random bytes (default 32 -> 64 hex chars).
	 * @return string
	 */
	public static function generate_token( $bytes = 32 ) {
		try {
			return bin2hex( random_bytes( max( 16, (int) $bytes ) ) );
		} catch ( Exception $e ) {
			return hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) . wp_rand() );
		}
	}

	/**
	 * Base64URL encode (RFC 4648 Section 5) for PKCE.
	 *
	 * @param string $data Binary or string data.
	 * @return string
	 */
	public static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Mask an email address for GDPR-friendly audit logs (e.g. jo***@gmail.com).
	 *
	 * @param string $email Raw email address.
	 * @return string Masked email address.
	 */
	public static function mask_email( $email ) {
		$email = sanitize_email( $email );
		if ( empty( $email ) || false === strpos( $email, '@' ) ) {
			return '***';
		}

		list( $local, $domain ) = explode( '@', $email, 2 );
		$len                    = strlen( $local );

		if ( $len <= 2 ) {
			$masked_local = substr( $local, 0, 1 ) . '***';
		} else {
			$masked_local = substr( $local, 0, 2 ) . '***';
		}

		return $masked_local . '@' . $domain;
	}

	/**
	 * Derive a fixed 32-byte (256-bit) binary key from WP salts.
	 *
	 * @return string
	 */
	private static function get_derived_key() {
		$raw = self::get_raw_key();
		if ( empty( $raw ) ) {
			return '';
		}
		return hash( 'sha256', $raw, true );
	}

	/**
	 * Get raw encryption key from wp-config salts.
	 *
	 * @return string
	 */
	private static function get_raw_key() {
		if ( defined( 'SECURE_AUTH_KEY' ) && ! empty( SECURE_AUTH_KEY ) ) {
			return SECURE_AUTH_KEY;
		}
		if ( defined( 'LOGGED_IN_KEY' ) && ! empty( LOGGED_IN_KEY ) ) {
			return LOGGED_IN_KEY;
		}
		return '';
	}
}
