<?php
/**
 * Shapes trait values into what Interakt accepts.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Interakt;

defined( 'ABSPATH' ) || exit;

/**
 * Interakt rejects trait values containing newlines, tabs, or runs of three or more
 * spaces, and caps a request at 32KB. Product names and scan headlines are written for
 * an email, so they arrive with all three.
 */
final class Trait_Sanitizer {

	private const MAX_STRING_LENGTH = 1000;

	/**
	 * @param array<string, mixed> $traits Raw values.
	 * @return array<string, mixed> Values Interakt will accept. Empties are dropped
	 *                              rather than sent, so a blank does not overwrite a
	 *                              trait recorded earlier.
	 */
	public static function clean( array $traits ): array {
		$cleaned = array();

		foreach ( $traits as $key => $value ) {
			$value = self::value( $value );

			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}

			$cleaned[ $key ] = $value;
		}

		return $cleaned;
	}

	/**
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function value( $value ) {
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_values(
				array_filter(
					array_map( array( self::class, 'value' ), $value ),
					static fn ( $item ): bool => null !== $item && '' !== $item
				)
			);
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		return self::text( $value );
	}

	private static function text( string $value ): string {
		$value = wp_strip_all_tags( $value );

		// Every run of whitespace becomes one space, which covers newlines, tabs and
		// the three-space rule in a single pass.
		$value = (string) preg_replace( '/\s+/u', ' ', $value );
		$value = trim( $value );

		if ( mb_strlen( $value ) > self::MAX_STRING_LENGTH ) {
			$value = mb_substr( $value, 0, self::MAX_STRING_LENGTH - 1 ) . '…';
		}

		return $value;
	}
}
