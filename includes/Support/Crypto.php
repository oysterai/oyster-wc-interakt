<?php
/**
 * At-rest encryption for the API keys this plugin stores.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Support;

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-CBC using a key derived from the site's `auth` salt.
 *
 * Threat model: this protects the stored keys against a leaked database dump
 * (backups, SQL exports, shared hosting snapshots). It does NOT defend against
 * an attacker who already holds both the database and wp-config.php, which is
 * the accepted ceiling for a secret stored by a WordPress plugin. Plaintext
 * would be strictly worse.
 *
 * Ciphertext is versioned so the scheme can rotate without a migration.
 */
final class Crypto {

	private const VERSION = 'v1';

	private const CIPHER = 'aes-256-cbc';

	/** Null on empty input, so callers can clear a stored key with an empty string. */
	public static function encrypt( string $plaintext ): ?string {
		if ( '' === $plaintext ) {
			return null;
		}

		if ( ! self::openssl_available() ) {
			// Tagged distinctly so decrypt() knows not to attempt an openssl pass.
			return 'b64:' . base64_encode( $plaintext );
		}

		$iv     = random_bytes( openssl_cipher_iv_length( self::CIPHER ) );
		$cipher = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return null;
		}

		return self::VERSION . ':' . base64_encode( $iv . $cipher );
	}

	/** Null when the payload is missing or tampered with, so callers read that as "not configured". */
	public static function decrypt( ?string $stored ): ?string {
		if ( null === $stored || '' === $stored ) {
			return null;
		}

		if ( 0 === strpos( $stored, 'b64:' ) ) {
			$decoded = base64_decode( substr( $stored, 4 ), true );

			return false === $decoded ? null : $decoded;
		}

		if ( 0 !== strpos( $stored, self::VERSION . ':' ) || ! self::openssl_available() ) {
			return null;
		}

		$raw = base64_decode( substr( $stored, strlen( self::VERSION ) + 1 ), true );
		if ( false === $raw ) {
			return null;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( strlen( $raw ) <= $iv_length ) {
			return null;
		}

		$plaintext = openssl_decrypt(
			substr( $raw, $iv_length ),
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			substr( $raw, 0, $iv_length )
		);

		return false === $plaintext ? null : $plaintext;
	}

	private static function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private static function openssl_available(): bool {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}
}
