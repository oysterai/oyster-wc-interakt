<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Integration;

use Oyster\WcInterakt\Plugin;
use WP_UnitTestCase;

/**
 * Dependencies are checked at runtime, not declared in a `Requires Plugins` header.
 * These cover what the merchant is told when one is missing.
 */
final class CompanionDependencyTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_it_does_not_declare_a_plugin_dependency_header(): void {
		// Deliberate. That header resolves on the installed folder name, so a dependency
		// installed from a zip reads as absent however active it is, and WordPress then
		// refuses activation with nothing the merchant can do to satisfy it.
		$data = get_plugin_data( dirname( __DIR__, 2 ) . '/oyster-wc-interakt.php', false, false );

		$this->assertSame( '', (string) ( $data['RequiresPlugins'] ?? '' ) );
	}

	public function test_it_finds_nothing_missing_on_a_correctly_set_up_site(): void {
		$this->assertSame( array(), Plugin::missing_dependencies() );
	}

	public function test_it_checks_woocommerce_in_its_own_right(): void {
		// The companion cannot stand in for this: it still loads when WooCommerce is
		// absent, defining its version constant while never booting. Action Scheduler
		// lives in WooCommerce and every queued send goes through it.
		$this->assertTrue( class_exists( 'WooCommerce' ) );
		$this->assertTrue( defined( 'OYSTER_WOO_VERSION' ) );
	}

	public function test_the_notice_says_what_is_actually_missing(): void {
		ob_start();
		Plugin::dependency_notice( array( 'Oyster for WooCommerce', 'WooCommerce' ) );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Oyster for WooCommerce, WooCommerce', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	public function test_it_stays_quiet_when_nothing_is_missing(): void {
		ob_start();
		Plugin::dependency_notice( array() );

		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_it_does_not_nag_someone_who_could_not_fix_it_anyway(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		Plugin::dependency_notice( array( 'Oyster for WooCommerce' ) );

		$this->assertSame( '', (string) ob_get_clean() );
	}
}
