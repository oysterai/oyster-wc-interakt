<?php
/**
 * Reads what is needed to deliver one scan's result.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Oyster;

use Oyster\WcInterakt\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Client {

	private const TIMEOUT = 15;

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function delivery_payload( string $batch_id ) {
		$key = Settings::oyster_api_key();

		if ( null === $key ) {
			return new WP_Error( 'oyster_wc_interakt_no_key', __( 'No Oyster API key is configured.', 'oyster-wc-interakt' ) );
		}

		$response = wp_remote_get(
			Settings::api_base_url() . '/api/v1/skin/delivery/' . rawurlencode( $batch_id ),
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			return new WP_Error(
				'oyster_wc_interakt_http_' . $code,
				sprintf(
					/* translators: 1: HTTP status code, 2: message returned by the API */
					__( 'Oyster returned %1$d: %2$s', 'oyster-wc-interakt' ),
					$code,
					is_array( $body ) ? (string) ( $body['message'] ?? '' ) : ''
				),
				array( 'status' => $code )
			);
		}

		$data = is_array( $body ) ? ( $body['data'] ?? null ) : null;

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'oyster_wc_interakt_bad_payload', __( 'Oyster returned an unreadable payload.', 'oyster-wc-interakt' ) );
		}

		return $data;
	}
}
