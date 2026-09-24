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

	private function render(): string {
		ob_start();
		( new Settings_Screen() )->render();

		return (string) ob_get_clean();
	}

	public function test_a_saved_key_looks_saved(): void {
		Settings::save( array( 'interakt_api_key' => 'sk_live_abcdef123456' ) );

		$html = $this->render();

		$this->assertStringContainsString( 'A key is saved', $html );
		$this->assertStringContainsString( str_repeat( '•', 28 ), $html );
	}

	public function test_an_empty_field_says_where_to_get_one(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( 'A key is saved', $html );
		$this->assertStringContainsString( 'Developer Settings', $html );
	}

	public function test_the_key_is_never_written_into_the_field(): void {
		// A value would post back untouched on the next save and overwrite the real key,
		// since only a blank field means "keep what you have".
		Settings::save( array( 'interakt_api_key' => 'sk_live_abcdef123456' ) );

		$this->assertStringNotContainsString( 'sk_live_abcdef123456', $this->render() );
	}

	public function test_saving_with_the_field_left_blank_keeps_the_key(): void {
		Settings::save( array( 'interakt_api_key' => 'sk_live_abcdef123456' ) );
		Settings::save( array( 'interakt_api_key' => '', 'enable_scan_completed' => '1' ) );

		$this->assertSame( 'sk_live_abcdef123456', Settings::interakt_api_key() );
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
