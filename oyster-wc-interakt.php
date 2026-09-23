<?php
/**
 * Plugin Name:       Oyster WhatsApp for WooCommerce
 * Plugin URI:        https://oysterskin.com/woocommerce
 * Description:       Send Oyster skin scan results and product recommendations to shoppers on WhatsApp, through your own Interakt account. Requires Oyster for WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Oyster Skin
 * Author URI:        https://oysterskin.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oyster-wc-interakt
 * Domain Path:       /languages
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt;

defined( 'ABSPATH' ) || exit;

define( 'OYSTER_WC_INTERAKT_VERSION', '0.1.0' );
define( 'OYSTER_WC_INTERAKT_FILE', __FILE__ );
define( 'OYSTER_WC_INTERAKT_PATH', plugin_dir_path( __FILE__ ) );
define( 'OYSTER_WC_INTERAKT_MIN_PHP', '8.1' );

/*
 * PSR-4-ish autoloader. Dependency-free (no Composer) so the release zip is
 * self-contained and passes WordPress.org review without a vendor/ tree.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = OYSTER_WC_INTERAKT_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/**
 * Returning false prevents bootstrap, so an unmet requirement never fatals on
 * activation.
 */
function meets_requirements(): bool {
	if ( version_compare( PHP_VERSION, OYSTER_WC_INTERAKT_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: required PHP version, 2: current PHP version */
							__( 'Oyster WhatsApp for WooCommerce requires PHP %1$s or newer. You are running %2$s.', 'oyster-wc-interakt' ),
							OYSTER_WC_INTERAKT_MIN_PHP,
							PHP_VERSION
						)
					)
				);
			}
		);

		return false;
	}

	return true;
}

/*
 * WooCommerce loads on `plugins_loaded` priority 10, so booting at 20 is what makes
 * the `WooCommerce` class check below reliable.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! meets_requirements() ) {
			return;
		}

		$missing = Plugin::missing_dependencies();

		if ( array() !== $missing ) {
			add_action( 'admin_notices', static fn () => Plugin::dependency_notice( $missing ) );

			return;
		}

		Plugin::instance()->boot();
	},
	20
);
