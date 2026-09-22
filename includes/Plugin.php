<?php
/**
 * Bootstrap.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt;

use Oyster\WcInterakt\Admin\Settings_Screen;
use Oyster\WcInterakt\Delivery\Listener;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		( new Listener() )->register();

		if ( is_admin() ) {
			( new Settings_Screen() )->register();
		}
	}

	/**
	 * Whether the plugin this one listens to is active. Its action hooks are the only
	 * source of work here, so without it nothing ever fires.
	 */
	public static function companion_active(): bool {
		return defined( 'OYSTER_WOO_VERSION' );
	}
}
