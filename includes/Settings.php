<?php
/**
 * Stored configuration: the two API keys and what to send.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt;

use Oyster\WcInterakt\Support\Crypto;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'oyster_wc_interakt_settings';

	public const EVENT_SCAN           = 'scan_completed';
	public const EVENT_RECOMMENDATION = 'recommendation_ready';

	/**
	 * Read every time rather than cached on the instance. A cached copy served a
	 * stale value after a save in a sibling plugin, and `get_option` is already
	 * backed by the object cache.
	 *
	 * @return array<string, mixed>
	 */
	private static function all(): array {
		$stored = get_option( self::OPTION );

		return is_array( $stored ) ? $stored : array();
	}

	public static function oyster_api_key(): ?string {
		return Crypto::decrypt( self::all()['oyster_key_enc'] ?? null );
	}

	public static function interakt_api_key(): ?string {
		return Crypto::decrypt( self::all()['interakt_key_enc'] ?? null );
	}

	public static function api_base_url(): string {
		$configured = defined( 'OYSTER_WC_INTERAKT_API_BASE_URL' )
			? (string) constant( 'OYSTER_WC_INTERAKT_API_BASE_URL' )
			: '';

		$base = '' !== $configured ? $configured : 'https://api.oysterskin.com';

		return rtrim( $base, '/' );
	}

	public static function interakt_base_url(): string {
		$configured = defined( 'OYSTER_WC_INTERAKT_BASE_URL' )
			? (string) constant( 'OYSTER_WC_INTERAKT_BASE_URL' )
			: '';

		$base = '' !== $configured ? $configured : 'https://api.interakt.ai';

		return rtrim( $base, '/' );
	}

	public static function event_enabled( string $event ): bool {
		return (bool) ( self::all()[ 'enable_' . $event ] ?? false );
	}

	/**
	 * The Interakt event name this plugin records. Configurable because the merchant
	 * builds their automation against whatever they call it.
	 */
	public static function event_name( string $event ): string {
		$stored = (string) ( self::all()[ 'event_name_' . $event ] ?? '' );

		if ( '' !== $stored ) {
			return $stored;
		}

		return self::EVENT_SCAN === $event ? 'Skin Scan Completed' : 'Skin Recommendations Ready';
	}

	/**
	 * Empty means "event only". A template name switches on the direct send, which is
	 * the only path that can attach the report.
	 */
	public static function template_name( string $event ): string {
		return (string) ( self::all()[ 'template_' . $event ] ?? '' );
	}

	public static function template_language( string $event ): string {
		$stored = (string) ( self::all()[ 'template_language_' . $event ] ?? '' );

		return '' !== $stored ? $stored : 'en';
	}

	public static function is_configured(): bool {
		return null !== self::oyster_api_key() && null !== self::interakt_api_key();
	}

	/**
	 * @param array<string, mixed> $input Raw, unsanitised form input.
	 */
	public static function save( array $input ): void {
		$current = self::all();

		$settings = array(
			'oyster_key_enc'   => self::key_value( $input, 'oyster_api_key', $current['oyster_key_enc'] ?? null ),
			'interakt_key_enc' => self::key_value( $input, 'interakt_api_key', $current['interakt_key_enc'] ?? null ),
		);

		foreach ( array( self::EVENT_SCAN, self::EVENT_RECOMMENDATION ) as $event ) {
			$settings[ 'enable_' . $event ]            = ! empty( $input[ 'enable_' . $event ] );
			$settings[ 'event_name_' . $event ]        = sanitize_text_field( (string) ( $input[ 'event_name_' . $event ] ?? '' ) );
			$settings[ 'template_' . $event ]          = sanitize_text_field( (string) ( $input[ 'template_' . $event ] ?? '' ) );
			$settings[ 'template_language_' . $event ] = sanitize_text_field( (string) ( $input[ 'template_language_' . $event ] ?? '' ) );
		}

		update_option( self::OPTION, $settings, false );
	}

	/**
	 * A blank submitted key leaves the stored one alone. The form never renders a key
	 * back, so a blank field means "unchanged", not "clear it".
	 *
	 * @param array<string, mixed> $input Raw form input.
	 */
	private static function key_value( array $input, string $field, ?string $existing ): ?string {
		$submitted = trim( (string) ( $input[ $field ] ?? '' ) );

		if ( '' === $submitted ) {
			return $existing;
		}

		return Crypto::encrypt( $submitted );
	}
}
