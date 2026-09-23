<?php
/**
 * Bootstrap for the integration suite.
 *
 * Loads a real WordPress and a real WooCommerce, which is where Action Scheduler
 * comes from. Nothing here is stubbed: hooks fire, options round-trip through the
 * database, and queued actions are really queued.
 *
 * Runs inside wp-env, which provides WordPress' own PHPUnit test library and
 * points WP_TESTS_DIR at it.
 *
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Could not find WordPress' test library at {$_tests_dir}.\n" .
		"This suite runs inside wp-env, try: npm run test:integration\n"
	);
	exit( 1 );
}

/**
 * WordPress' test suite is built on Yoast's PHPUnit Polyfills and refuses to boot
 * without them. Pointed at explicitly rather than relying on the Composer autoloader
 * having been pulled in first, which is the difference between a clear failure and a
 * confusing one.
 */
$_polyfills = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && is_dir( $_polyfills ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_polyfills );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once $_tests_dir . '/includes/functions.php';

/**
 * Where WooCommerce ended up. Not hardcoded: how a plugin directory is named depends
 * on how it was installed, and a wrong guess fails deep inside WordPress' boot with a
 * stack trace that says nothing about the cause.
 */
$_woocommerce = static function (): string {
	$expected = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

	if ( file_exists( $expected ) ) {
		return $expected;
	}

	foreach ( (array) glob( WP_PLUGIN_DIR . '/*/woocommerce.php' ) as $candidate ) {
		if ( is_string( $candidate ) && file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	$present = array_map( 'basename', (array) glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) );

	fwrite(
		STDERR,
		"WooCommerce is not installed in this environment.\n" .
		'Looked in ' . WP_PLUGIN_DIR . '; found: ' . ( $present ? implode( ', ', $present ) : '(nothing)' ) . "\n"
	);
	exit( 1 );
};

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $_woocommerce ): void {
		require_once $_woocommerce();

		/*
		 * Stands in for Oyster for WooCommerce, which lives in its own repository and is
		 * not installed here. The whole contract with it is this constant and the two
		 * actions it fires, and the tests fire those themselves, so there is nothing
		 * further of it to stub. Without this the plugin under test correctly refuses to
		 * boot and every delivery test would be asserting against a dead site.
		 */
		defined( 'OYSTER_WOO_VERSION' ) || define( 'OYSTER_WOO_VERSION', '0.20.0' );

		require_once dirname( __DIR__, 2 ) . '/oyster-wc-interakt.php';
	}
);

tests_add_filter(
	'setup_theme',
	static function (): void {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
		}
	}
);

require $_tests_dir . '/includes/bootstrap.php';
