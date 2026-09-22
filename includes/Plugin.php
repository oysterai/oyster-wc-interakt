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
	 * What this plugin needs and cannot find, named as a merchant would recognise them.
	 *
	 * Checked at runtime rather than declared in a `Requires Plugins` header. That header
	 * resolves on the installed folder name, so a dependency installed from a zip (a
	 * GitHub download names its folder `-main`) reads as absent however active it is, and
	 * WordPress then refuses activation permanently with no way for the merchant to
	 * satisfy it. Failing here instead is recoverable and can say what is actually wrong.
	 *
	 * @return list<string>
	 */
	public static function missing_dependencies(): array {
		$missing = array();

		if ( ! defined( 'OYSTER_WOO_VERSION' ) ) {
			$missing[] = 'Oyster for WooCommerce';
		}

		// Named separately from the plugin above, which does not carry it: that plugin
		// still loads when WooCommerce is absent, defining its version constant while
		// never booting. Action Scheduler, which every queued send goes through, ships
		// inside WooCommerce.
		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing[] = 'WooCommerce';
		}

		return $missing;
	}

	/**
	 * @param list<string> $missing Dependencies that could not be found.
	 */
	public static function dependency_notice( array $missing ): void {
		if ( array() === $missing || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of plugin names */
					__( 'Oyster WhatsApp for WooCommerce needs these active, and cannot find them: %s. Nothing will be sent until they are.', 'oyster-wc-interakt' ),
					implode( ', ', $missing )
				)
			)
		);
	}
}
