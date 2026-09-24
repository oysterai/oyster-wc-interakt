<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Integration;

use Oyster\WcInterakt\Delivery\Dispatcher;
use Oyster\WcInterakt\Delivery\Listener;
use Oyster\WcInterakt\Settings;
use WP_UnitTestCase;

final class DeliveryTest extends WP_UnitTestCase {

	/** @var list<array{url: string, body: array<string, mixed>}> */
	private array $requests = array();

	/** @var array<string, array{code: int, body: array<string, mixed>}> */
	private array $responses = array();

	/** @var array<string, mixed>|null */
	private ?array $payload = null;

	private const BATCH = '01a08bcd-71cf-7023-9cb9-c6c177845c4e';

	public function set_up(): void {
		parent::set_up();

		$this->requests  = array();
		$this->responses = array();
		$this->payload   = null;

		Settings::save(
			array(
				'interakt_api_key'                         => 'interakt-test-key',
				'enable_' . Settings::EVENT_SCAN           => '1',
				'enable_' . Settings::EVENT_RECOMMENDATION => '1',
			)
		);

		add_filter( 'pre_http_request', array( $this, 'intercept' ), 10, 3 );
		add_filter( 'oyster_woocommerce_api_get', array( $this, 'oyster_api' ), 10, 2 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'intercept' ), 10 );
		remove_filter( 'oyster_woocommerce_api_get', array( $this, 'oyster_api' ), 10 );
		parent::tear_down();
	}

	/**
	 * Stands in for Oyster for WooCommerce, which owns the store credential and lives
	 * in its own repository.
	 *
	 * @param mixed  $response Unused.
	 * @param string $path     API path.
	 * @return array<string, mixed>
	 */
	public function oyster_api( $response, $path = '' ) {
		$this->requests[] = array( 'url' => 'oyster:' . $path, 'body' => array() );

		return array( 'data' => $this->payload );
	}

	/**
	 * @param mixed                $preempt Short-circuit value.
	 * @param array<string, mixed> $args    Request args.
	 * @param string               $url     Request URL.
	 * @return array<string, mixed>
	 */
	public function intercept( $preempt, $args, $url ) {
		$this->requests[] = array(
			'url'  => $url,
			'body' => isset( $args['body'] ) ? (array) json_decode( (string) $args['body'], true ) : array(),
		);

		foreach ( $this->responses as $fragment => $response ) {
			if ( false !== strpos( $url, (string) $fragment ) ) {
				return array(
					'response' => array( 'code' => $response['code'] ),
					'body'     => (string) wp_json_encode( $response['body'] ),
				);
			}
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode( array( 'result' => true ) ),
		);
	}

	/** @param array<string, mixed> $overrides Payload overrides. */
	private function oyster_returns( array $overrides = array() ): void {
		$this->payload = array_merge(
					array(
						'batch_id'     => self::BATCH,
						'scanned_at'   => '2026-09-22T10:00:00+00:00',
						'channel'      => 'woocommerce',
						'headline'     => "Hey Ada,\nyour barrier is intact.",
						'report_url'   => 'https://api.example.test/api/v1/skin/reports/' . self::BATCH . '?expires=1&signature=abc',
						'checkout_url' => 'https://store.test/?oyster_checkout=1',
						'products'     => array(
							array(
								'name'      => 'Calming Cleanser',
								'brand'     => 'Acme',
								'step'      => 'Step 1: Cleanser',
								'image_url' => 'https://cdn.example.test/cleanser.png',
							),
						),
						'customer'     => array(
							'name'      => 'Ada Obi',
							'email'     => 'ada@example.com',
							'phone'     => array(
								'e164'            => '+2348012345678',
								'country_code'    => '+234',
								'national_number' => '8012345678',
							),
							'marketing' => array( 'email' => true, 'sms' => null, 'whatsapp' => null ),
						),
					),
			$overrides
		);
	}

	private function urls_called(): array {
		return array_map( static fn ( array $request ): string => $request['url'], $this->requests );
	}

	private function called( string $fragment ): bool {
		foreach ( $this->urls_called() as $url ) {
			if ( false !== strpos( $url, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	public function test_the_hook_queues_the_work_instead_of_calling_interakt(): void {
		// The hook fires inside the request Oyster makes to deliver the webhook. Calling
		// Interakt from there puts its latency inside that request's budget, and a run of
		// failures disables the store's whole webhook integration.
		do_action( 'oyster_woocommerce_scan_completed', self::BATCH, array() );

		$this->assertSame( array(), $this->requests );
		$this->assertNotEmpty(
			as_get_scheduled_actions( array( 'hook' => Listener::ACTION, 'group' => Listener::GROUP ) )
		);
	}

	public function test_it_reads_the_scan_through_the_companion_plugin(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		// No key of its own: the store credential stays with the plugin that owns it.
		$this->assertTrue( $this->called( 'oyster:/api/v1/skin/delivery/' . self::BATCH ) );
	}

	public function test_it_records_the_customer_then_the_event(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertTrue( $this->called( '/v1/public/track/users/' ) );
		$this->assertTrue( $this->called( '/v1/public/track/events/' ) );

		$event = null;
		foreach ( $this->requests as $request ) {
			if ( false !== strpos( $request['url'], '/track/events/' ) ) {
				$event = $request['body'];
			}
		}

		$this->assertSame( '+234', $event['countryCode'] );
		$this->assertSame( '8012345678', $event['phoneNumber'] );
		// Sanitised on the way out: Interakt rejects newlines in a trait value.
		$this->assertSame( 'Hey Ada, your barrier is intact.', $event['traits']['headline'] );
	}

	public function test_it_pushes_product_images_so_the_vendor_can_build_their_own_card(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$event = null;
		foreach ( $this->requests as $request ) {
			if ( false !== strpos( $request['url'], '/track/events/' ) ) {
				$event = $request['body'];
			}
		}

		$this->assertSame( array( 'Calming Cleanser' ), $event['traits']['products'] );
		$this->assertSame( array( 'https://cdn.example.test/cleanser.png' ), $event['traits']['product_images'] );
	}

	public function test_it_never_sends_a_message_itself(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		// Data goes to Interakt; the vendor decides what becomes a message.
		$this->assertFalse( $this->called( '/v1/public/message/' ) );
	}

	public function test_it_sends_the_result_even_when_whatsapp_marketing_is_declined(): void {
		// A scan result is what the shopper asked for by scanning. Consent flags inform
		// the merchant's own promotional sends; they do not withhold this one.
		$this->oyster_returns(
			array(
				'customer' => array(
					'name'      => 'Ada Obi',
					'email'     => 'ada@example.com',
					'phone'     => array( 'e164' => '+2348012345678', 'country_code' => '+234', 'national_number' => '8012345678' ),
					'marketing' => array( 'email' => false, 'sms' => false, 'whatsapp' => false ),
				),
			)
		);

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertTrue( $this->called( '/v1/public/track/events/' ) );
	}

	public function test_it_does_not_send_the_same_scan_twice(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );
		$first = count( $this->requests );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertCount( $first, $this->requests );
	}

	public function test_the_two_events_are_deduped_separately(): void {
		$this->oyster_returns();

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );
		$after_scan = count( $this->requests );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_RECOMMENDATION );

		$this->assertGreaterThan( $after_scan, count( $this->requests ) );
	}

	public function test_it_stops_when_there_is_no_number_to_reach(): void {
		$this->oyster_returns(
			array(
				'customer' => array( 'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => null, 'marketing' => array() ),
			)
		);

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertFalse( $this->called( 'interakt' ) );
		// Nothing to retry: another attempt reads the same scan and finds the same gap.
		$this->assertSame( array(), as_get_scheduled_actions( array( 'hook' => Listener::ACTION, 'group' => Listener::GROUP ) ) );
	}

	public function test_it_retries_a_rate_limit(): void {
		$this->oyster_returns();
		$this->responses['interakt'] = array( 'code' => 429, 'body' => array( 'message' => 'Too many requests' ) );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertNotEmpty( as_get_scheduled_actions( array( 'hook' => Listener::ACTION, 'group' => Listener::GROUP ) ) );
	}

	public function test_a_retry_is_free_to_run_again(): void {
		// The claim is taken before the send so two workers cannot both message the
		// customer. A retryable failure has to release it, or the retry no-ops.
		$this->oyster_returns();
		$this->responses['interakt'] = array( 'code' => 429, 'body' => array() );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		unset( $this->responses['interakt'] );
		$before = count( $this->requests );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN, 2 );

		$this->assertGreaterThan( $before, count( $this->requests ) );
	}

	public function test_it_does_not_retry_a_payload_interakt_refused(): void {
		// The same request would be refused again.
		$this->oyster_returns();
		$this->responses['interakt'] = array( 'code' => 400, 'body' => array( 'message' => 'Invalid payload' ) );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertSame( array(), as_get_scheduled_actions( array( 'hook' => Listener::ACTION, 'group' => Listener::GROUP ) ) );
	}



	public function test_it_does_nothing_until_the_interakt_key_is_set(): void {
		delete_option( Settings::OPTION );
		Settings::save( array( 'enable_' . Settings::EVENT_SCAN => '1' ) );

		( new Dispatcher() )->deliver( self::BATCH, Settings::EVENT_SCAN );

		$this->assertSame( array(), $this->requests );
	}
}
