<?php
/**
 * Turns the Oyster plugin's action hooks into queued delivery work.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Delivery;

use Oyster\WcInterakt\Settings;

defined( 'ABSPATH' ) || exit;

final class Listener {

	public const ACTION = 'oyster_wc_interakt_deliver';

	public const GROUP = 'oyster-wc-interakt';

	private const HOOKS = array(
		'oyster_woocommerce_scan_completed'        => Settings::EVENT_SCAN,
		'oyster_woocommerce_recommendations_ready' => Settings::EVENT_RECOMMENDATION,
	);

	public function register(): void {
		foreach ( self::HOOKS as $hook => $event ) {
			add_action(
				$hook,
				function ( $batch_id ) use ( $event ): void {
					$this->enqueue( (string) $batch_id, $event );
				},
				10,
				1
			);
		}

		add_action( self::ACTION, array( $this, 'run' ), 10, 2 );
	}

	/**
	 * Queues the work and returns.
	 *
	 * Nothing here may call Interakt. These hooks fire inside the request Oyster makes
	 * to deliver the webhook, which has a short timeout and disables the endpoint after
	 * a run of failures. An Interakt outage reached from here would take the store's
	 * whole Oyster webhook integration down with it, not just WhatsApp.
	 */
	private function enqueue( string $batch_id, string $event ): void {
		if ( '' === $batch_id || ! Settings::event_enabled( $event ) ) {
			return;
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		as_enqueue_async_action( self::ACTION, array( $batch_id, $event ), self::GROUP );
	}

	public function run( $batch_id = '', $event = '' ): void {
		( new Dispatcher() )->deliver( (string) $batch_id, (string) $event );
	}
}
