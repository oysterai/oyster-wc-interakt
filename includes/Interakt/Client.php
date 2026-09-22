<?php
/**
 * Talks to Interakt.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Interakt;

use Oyster\WcInterakt\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Client {

	private const TIMEOUT = 15;

	/** Interakt caps a request at 32KB. */
	private const MAX_BODY_BYTES = 32768;

	/**
	 * Records the customer so the event has a profile to land on, and so the merchant
	 * can segment on these in their dashboard.
	 *
	 * @param array<string, mixed> $traits Already sanitised.
	 * @return true|WP_Error
	 */
	public function track_user( string $country_code, string $phone_number, array $traits ) {
		return $this->post(
			'/v1/public/track/users/',
			array(
				'countryCode' => $country_code,
				'phoneNumber' => $phone_number,
				'traits'      => (object) $traits,
			)
		);
	}

	/**
	 * @param array<string, mixed> $traits Already sanitised.
	 * @return true|WP_Error
	 */
	public function track_event( string $country_code, string $phone_number, string $event, array $traits ) {
		return $this->post(
			'/v1/public/track/events/',
			array(
				'countryCode' => $country_code,
				'phoneNumber' => $phone_number,
				'event'       => $event,
				'traits'      => (object) $traits,
			)
		);
	}

	/**
	 * Sends a named template directly. The only path that can carry a document header,
	 * which is how the report PDF reaches the customer without a hosted page.
	 *
	 * The checkout link travels as a body variable rather than a dynamic URL button:
	 * WhatsApp button variables are a suffix onto a prefix fixed at template approval,
	 * which an arbitrary storefront URL with a query string does not fit.
	 *
	 * @param list<string> $body_values Fills {{1}}, {{2}}, ... in order.
	 * @return true|WP_Error
	 */
	public function send_template(
		string $country_code,
		string $phone_number,
		string $template_name,
		string $language_code,
		array $body_values,
		?string $header_media_url = null,
		?string $header_file_name = null
	) {
		$template = array(
			'name'         => $template_name,
			'languageCode' => $language_code,
			'bodyValues'   => array_values( $body_values ),
		);

		if ( null !== $header_media_url && '' !== $header_media_url ) {
			$template['headerValues'] = array( $header_media_url );

			if ( null !== $header_file_name && '' !== $header_file_name ) {
				$template['fileName'] = $header_file_name;
			}
		}

		return $this->post(
			'/v1/public/message/',
			array(
				'countryCode' => $country_code,
				'phoneNumber' => $phone_number,
				'type'        => 'Template',
				'template'    => $template,
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload Request body.
	 * @return true|WP_Error
	 */
	private function post( string $path, array $payload ) {
		$key = Settings::interakt_api_key();

		if ( null === $key ) {
			return new WP_Error( 'oyster_wc_interakt_no_key', __( 'No Interakt API key is configured.', 'oyster-wc-interakt' ) );
		}

		$body = wp_json_encode( $payload );

		if ( false === $body ) {
			return new WP_Error( 'oyster_wc_interakt_unencodable', __( 'Could not encode the Interakt request.', 'oyster-wc-interakt' ) );
		}

		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			return new WP_Error( 'oyster_wc_interakt_too_large', __( 'The Interakt request is larger than Interakt accepts.', 'oyster-wc-interakt' ) );
		}

		$response = wp_remote_post(
			Settings::interakt_base_url() . $path,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $key,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code > 299 ) {
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			return new WP_Error(
				'oyster_wc_interakt_http_' . $code,
				sprintf(
					/* translators: 1: HTTP status code, 2: message returned by Interakt */
					__( 'Interakt returned %1$d: %2$s', 'oyster-wc-interakt' ),
					$code,
					is_array( $decoded ) ? (string) ( $decoded['message'] ?? '' ) : ''
				),
				// Carried so the caller can tell a rate limit, which is worth retrying,
				// from a rejected payload, which is not.
				array( 'status' => $code )
			);
		}

		return true;
	}
}
