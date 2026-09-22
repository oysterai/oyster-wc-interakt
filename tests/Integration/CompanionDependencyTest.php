<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Integration;

use Oyster\WcInterakt\Plugin;
use WP_UnitTestCase;

/**
 * The `Requires Plugins` header blocks activation, but WordPress still allows the
 * dependency to be deactivated afterwards while this plugin stays active. These cover
 * what the merchant sees in that window.
 */
final class CompanionDependencyTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$GLOBALS['current_screen'] = null;
		parent::tear_down();
	}

	public function test_it_declares_the_dependency_to_wordpress(): void {
		// The header is the actual enforcement; the notice below is only the fallback.
		$data = get_plugin_data( dirname( __DIR__, 2 ) . '/oyster-wc-interakt.php', false, false );

		$required = array_map( 'trim', explode( ',', (string) $data['RequiresPlugins'] ) );

		$this->assertContains( 'oyster-woocommerce', $required );

		// Named directly rather than leaned on transitively: Action Scheduler ships
		// inside WooCommerce, and the Oyster plugin still loads (defining its version
		// constant) when WooCommerce is absent, so it never boots but looks present.
		$this->assertContains( 'woocommerce', $required );
	}

	public function test_it_says_so_on_the_plugins_screen_when_the_companion_is_gone(): void {
		set_current_screen( 'plugins' );

		ob_start();
		Plugin::companion_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Oyster for WooCommerce', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	public function test_it_keeps_quiet_on_unrelated_screens(): void {
		set_current_screen( 'edit-post' );

		ob_start();
		Plugin::companion_notice();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_it_does_not_nag_someone_who_could_not_fix_it_anyway(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		set_current_screen( 'plugins' );

		ob_start();
		Plugin::companion_notice();

		$this->assertSame( '', (string) ob_get_clean() );
	}

	public function test_the_companion_is_absent_in_this_environment(): void {
		// Guards the three above: if something started defining the constant, they would
		// pass by never reaching their assertions.
		$this->assertFalse( Plugin::companion_active() );
	}
}
