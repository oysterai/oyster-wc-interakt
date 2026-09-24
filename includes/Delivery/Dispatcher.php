<?php
/**
 * Fetches a scan's delivery payload and hands it to Interakt.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Delivery;

use Oyster\WcInterakt\Interakt\Client as InteraktClient;
use Oyster\WcInterakt\Interakt\Trait_Sanitizer;
use Oyster\WcInterakt\Oyster\Client as OysterClient;
use Oyster\WcInterakt\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Dispatcher {

	private const MAX_ATTEMPTS = 4;

	/** Option prefix for the "already sent" markers. Swept on uninstall. */
	private const SENT_PREFIX = 'oyster_wc_interakt_sent_';

	private OysterClient $oyster;

	private InteraktClient $interakt;

	public function __construct( ?OysterClient $oyster = null, ?InteraktClient $interakt = null ) {
		$this->oyster   = $oyster ?? new OysterClient();
		$this->interakt = $interakt ?? new InteraktClient();
	}

	public function deliver( string $batch_id, string $event, int $attempt = 1 ): void {
		if ( '' === $batch_id || ! Settings::event_enabled( $event ) || ! Settings::is_configured() ) {
			return;
		}

		// Claimed before the send, not after, so two workers cannot both message the
		// same customer. Released again below if the failure is worth retrying.
		if ( ! $this->claim( $batch_id, $event ) ) {
			return;
		}

		$payload = $this->oyster->delivery_payload( $batch_id );

		if ( is_wp_error( $payload ) ) {
			$this->handle_failure( $payload, $batch_id, $event, $attempt );

			return;
		}

		$phone = $payload['customer']['phone'] ?? null;

		// No number on record. Nothing to retry: another attempt reads the same scan.
		if ( ! is_array( $phone ) || empty( $phone['country_code'] ) || empty( $phone['national_number'] ) ) {
			return;
		}

		$country_code = (string) $phone['country_code'];
		$number       = (string) $phone['national_number'];

		$result = $this->interakt->track_user( $country_code, $number, $this->user_traits( $payload ) );

		if ( is_wp_error( $result ) ) {
			$this->handle_failure( $result, $batch_id, $event, $attempt );

			return;
		}

		$result = $this->interakt->track_event(
			$country_code,
			$number,
			Settings::event_name( $event ),
			$this->event_traits( $payload )
		);

		if ( is_wp_error( $result ) ) {
			$this->handle_failure( $result, $batch_id, $event, $attempt );
		}
	}

	/**
	 * Consent flags ride along so the merchant can segment their own promotional sends
	 * on them; they do not gate this one. A scan result is what the shopper asked for
	 * by scanning.
	 *
	 * @param array<string, mixed> $payload Delivery payload.
	 * @return array<string, mixed>
	 */
	private function user_traits( array $payload ): array {
		$customer  = is_array( $payload['customer'] ?? null ) ? $payload['customer'] : array();
		$marketing = is_array( $customer['marketing'] ?? null ) ? $customer['marketing'] : array();

		return Trait_Sanitizer::clean(
			array(
				'name'                => $customer['name'] ?? null,
				'email'               => $customer['email'] ?? null,
				'marketing_email'     => $marketing['email'] ?? null,
				'marketing_sms'       => $marketing['sms'] ?? null,
				'marketing_whatsapp'  => $marketing['whatsapp'] ?? null,
			)
		);
	}

	/**
	 * Names and links only: the analysis itself stays out of a marketing tool's
	 * timeline.
	 *
	 * @param array<string, mixed> $payload Delivery payload.
	 * @return array<string, mixed>
	 */
	private function event_traits( array $payload ): array {
		$products = $this->product_names( $payload );

		return Trait_Sanitizer::clean(
			array(
				'batch_id'      => $payload['batch_id'] ?? null,
				'scanned_at'    => $payload['scanned_at'] ?? null,
				'channel'       => $payload['channel'] ?? null,
				'headline'      => $payload['headline'] ?? null,
				'product_count'  => count( $products ),
				'products'       => $products,
				'product_images' => $this->product_images( $payload ),
				'report_url'    => $payload['report_url'] ?? null,
				'checkout_url'  => $payload['checkout_url'] ?? null,
			)
		);
	}


	/**
	 * @param array<string, mixed> $payload Delivery payload.
	 * @return list<string>
	 */
	private function product_images( array $payload ): array {
		return $this->product_field( $payload, 'image_url' );
	}

	/**
	 * @param array<string, mixed> $payload Delivery payload.
	 * @return list<string>
	 */
	private function product_names( array $payload ): array {
		return $this->product_field( $payload, 'name' );
	}

	/**
	 * @param array<string, mixed> $payload Delivery payload.
	 * @return list<string>
	 */
	private function product_field( array $payload, string $field ): array {
		$products = is_array( $payload['products'] ?? null ) ? $payload['products'] : array();

		return array_values(
			array_filter(
				array_map(
					static fn ( $product ): string => is_array( $product ) ? (string) ( $product[ $field ] ?? '' ) : '',
					$products
				),
				static fn ( string $value ): bool => '' !== $value
			)
		);
	}


	/**
	 * Action Scheduler does not retry a failed action on its own, so a retryable
	 * failure schedules its own next attempt and releases the claim so it can run.
	 */
	private function handle_failure( WP_Error $error, string $batch_id, string $event, int $attempt ): void {
		$status = (int) ( $error->get_error_data()['status'] ?? 0 );

		if ( ! $this->is_retryable( $status ) || $attempt >= self::MAX_ATTEMPTS ) {
			return;
		}

		$this->release( $batch_id, $event );

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		as_schedule_single_action(
			time() + ( 60 * ( 2 ** ( $attempt - 1 ) ) ),
			Listener::ACTION,
			array( $batch_id, $event, $attempt + 1 ),
			Listener::GROUP
		);
	}

	/**
	 * A rate limit or a server-side fault is worth another attempt. A rejected payload
	 * or a refused key is not: the same request would be rejected again.
	 */
	private function is_retryable( int $status ): bool {
		return 0 === $status || 429 === $status || $status >= 500;
	}

	/** Atomic: add_option returns false when the row already exists. */
	private function claim( string $batch_id, string $event ): bool {
		return add_option( $this->marker( $batch_id, $event ), time(), '', 'no' );
	}

	private function release( string $batch_id, string $event ): void {
		delete_option( $this->marker( $batch_id, $event ) );
	}

	private function marker( string $batch_id, string $event ): string {
		return self::SENT_PREFIX . md5( $batch_id . '|' . $event );
	}
}
