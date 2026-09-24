<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Integration;

use Exception;
use Oyster\WcInterakt\Admin\Settings_Screen;
use Oyster\WcInterakt\Settings;
use WP_UnitTestCase;

final class SettingsScreenTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// handle_save() exits after redirecting, which would end the run. Throwing from
		// the filter is how WordPress' own suite reaches the target instead.
		add_filter( 'wp_redirect', array( $this, 'intercept' ), 10, 1 );
	}

	public function tear_down(): void {
		remove_filter( 'wp_redirect', array( $this, 'intercept' ), 10 );
		delete_option( Settings::OPTION );
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * @param string $location Redirect target.
	 * @throws Exception Always, carrying the target.
	 */
	public function intercept( $location ): void {
		throw new Exception( (string) $location );
	}

	public function test_saving_sends_the_admin_back_to_the_settings_page(): void {
		// admin-post.php fires admin_init but never loads the admin menu, so building
		// this with menu_page_url() yields an empty target and a blank page.
		$_POST = array(
			'action'                => 'oyster_wc_interakt_save',
			'_wpnonce'              => wp_create_nonce( 'oyster_wc_interakt_save' ),
			'interakt_api_key'      => 'a-test-key',
			'enable_scan_completed' => '1',
		);

		$_REQUEST = $_POST;

		$target = null;

		try {
			( new Settings_Screen() )->handle_save();
		} catch ( Exception $e ) {
			$target = $e->getMessage();
		}

		$this->assertNotNull( $target, 'expected a redirect' );
		$this->assertStringContainsString( 'options-general.php', (string) $target );
		$this->assertStringContainsString( 'page=oyster-wc-interakt', (string) $target );
		$this->assertSame( 'a-test-key', Settings::interakt_api_key() );
	}
}
