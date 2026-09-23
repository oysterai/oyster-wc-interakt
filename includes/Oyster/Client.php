<?php
/**
 * Reads what is needed to deliver one scan's result.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Oyster;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Goes through Oyster for WooCommerce rather than holding a key of its own, so the
 * store keeps one credential and it dies with the connection.
 */
final class Client {

	private const FILTER = 'oyster_woocommerce_api_get';

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function delivery_payload( string $batch_id ) {
		if ( ! has_filter( self::FILTER ) ) {
			return new WP_Error(
				'oyster_wc_interakt_no_companion',
				__( 'Oyster for WooCommerce is not available to read this scan.', 'oyster-wc-interakt' )
			);
		}

		$response = apply_filters( self::FILTER, null, '/skin/delivery/' . rawurlencode( $batch_id ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = is_array( $response ) ? ( $response['data'] ?? null ) : null;

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'oyster_wc_interakt_bad_payload', __( 'Oyster returned an unreadable payload.', 'oyster-wc-interakt' ) );
		}

		return $data;
	}
}
