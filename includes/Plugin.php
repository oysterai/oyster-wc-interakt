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
			add_action( 'admin_notices', array( self::class, 'companion_notice' ) );
		}
	}

	/**
	 * Whether the plugin this one listens to is active. Its action hooks are the only
	 * source of work here, so without it nothing ever fires.
	 */
	public static function companion_active(): bool {
		return defined( 'OYSTER_WOO_VERSION' );
	}

	/**
	 * The `Requires Plugins` header is what actually enforces this, and WordPress
	 * blocks activation on it. This covers the gap it leaves: a site that had both and
	 * then deactivated the other one, where this plugin would otherwise go quiet with
	 * nothing on screen to say why.
	 *
	 * Shown on the plugins list and on this plugin's own screen, not site-wide.
	 */
	public static function companion_notice(): void {
		if ( self::companion_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen || ! in_array( $screen->id, array( 'plugins', 'settings_page_oyster-wc-interakt' ), true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Oyster WhatsApp for WooCommerce needs Oyster for WooCommerce, which is not active. Nothing will be sent until it is.', 'oyster-wc-interakt' )
		);
	}
}
