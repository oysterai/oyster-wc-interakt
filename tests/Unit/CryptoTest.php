<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Unit;

use Oyster\WcInterakt\Support\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase {

	public function test_it_round_trips_a_key(): void {
		$this->assertSame( 'sk_live_abc123', Crypto::decrypt( Crypto::encrypt( 'sk_live_abc123' ) ) );
	}

	public function test_it_does_not_store_the_key_in_the_clear(): void {
		$stored = Crypto::encrypt( 'sk_live_abc123' );

		$this->assertIsString( $stored );
		$this->assertStringNotContainsString( 'sk_live_abc123', $stored );
	}

	public function test_it_produces_a_different_ciphertext_each_time(): void {
		// A fresh IV per encrypt, so two rows holding the same key do not look alike.
		$this->assertNotSame( Crypto::encrypt( 'same' ), Crypto::encrypt( 'same' ) );
	}

	public function test_it_reads_a_tampered_or_missing_value_as_not_configured(): void {
		$this->assertNull( Crypto::decrypt( null ) );
		$this->assertNull( Crypto::decrypt( '' ) );
		$this->assertNull( Crypto::decrypt( 'v1:not-base64-at-all!!' ) );
		$this->assertNull( Crypto::decrypt( 'plaintext-with-no-version-tag' ) );
	}

	public function test_it_returns_null_for_an_empty_key_so_callers_can_clear_one(): void {
		$this->assertNull( Crypto::encrypt( '' ) );
	}
}
