<?php
/**
 * Encryption helper for API key storage.
 *
 * @package AIKnowledgeChatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIKB_Encryption {

	/**
	 * Encrypt plaintext.
	 *
	 * @param string $plaintext Input.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key = self::key();
		$iv  = random_bytes( 16 );
		if ( function_exists( 'openssl_encrypt' ) ) {
			$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $ciphertext ) {
				return 'v1:' . base64_encode( $iv . $ciphertext );
			}
		}

		return 'plain:' . base64_encode( $plaintext );
	}

	/**
	 * Decrypt value.
	 *
	 * @param string $value Stored value.
	 */
	public static function decrypt( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( str_starts_with( $value, 'plain:' ) ) {
			return (string) base64_decode( substr( $value, 6 ), true );
		}

		if ( ! str_starts_with( $value, 'v1:' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$raw = base64_decode( substr( $value, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv         = substr( $raw, 0, 16 );
		$ciphertext = substr( $raw, 16 );
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Build encryption key.
	 */
	private static function key(): string {
		$source = '';
		if ( defined( 'AUTH_KEY' ) ) {
			$source .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_SALT' ) ) {
			$source .= SECURE_AUTH_SALT;
		}
		if ( '' === $source ) {
			$source = wp_salt( 'auth' );
		}
		return hash( 'sha256', $source, true );
	}
}
