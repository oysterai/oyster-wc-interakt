<?php
/**
 * @package Oyster\WcInterakt
 */

declare( strict_types=1 );

namespace Oyster\WcInterakt\Tests\Unit;

use Oyster\WcInterakt\Interakt\Trait_Sanitizer;
use PHPUnit\Framework\TestCase;

final class TraitSanitizerTest extends TestCase {

	public function test_it_collapses_the_whitespace_interakt_rejects(): void {
		$cleaned = Trait_Sanitizer::clean(
			array(
				'headline' => "Hey Ada,\nyour skin\tbarrier is   intact.",
			)
		);

		$this->assertSame( 'Hey Ada, your skin barrier is intact.', $cleaned['headline'] );
	}

	public function test_it_strips_markup_out_of_a_value(): void {
		$cleaned = Trait_Sanitizer::clean( array( 'headline' => '<strong>Hydrating</strong> body wash' ) );

		$this->assertSame( 'Hydrating body wash', $cleaned['headline'] );
	}

	public function test_it_drops_empty_values_rather_than_sending_them(): void {
		// A blank sent to Interakt overwrites whatever was recorded before.
		$cleaned = Trait_Sanitizer::clean(
			array(
				'name'  => 'Ada',
				'email' => null,
				'phone' => '',
				'tags'  => array(),
			)
		);

		$this->assertSame( array( 'name' => 'Ada' ), $cleaned );
	}

	public function test_it_keeps_booleans_and_numbers_as_they_are(): void {
		$cleaned = Trait_Sanitizer::clean(
			array(
				'marketing_whatsapp' => false,
				'product_count'      => 0,
				'score'              => 72.5,
			)
		);

		// False and zero are answers, not absences, so they must survive.
		$this->assertSame( false, $cleaned['marketing_whatsapp'] );
		$this->assertSame( 0, $cleaned['product_count'] );
		$this->assertSame( 72.5, $cleaned['score'] );
	}

	public function test_it_cleans_every_item_in_a_list(): void {
		$cleaned = Trait_Sanitizer::clean(
			array(
				'products' => array( "Calming\nCleanser", '', 'Niacinamide   Wash' ),
			)
		);

		$this->assertSame( array( 'Calming Cleanser', 'Niacinamide Wash' ), $cleaned['products'] );
	}

	public function test_it_truncates_a_value_that_would_bloat_the_request(): void {
		$cleaned = Trait_Sanitizer::clean( array( 'headline' => str_repeat( 'a', 5000 ) ) );

		$this->assertSame( 1000, mb_strlen( $cleaned['headline'] ) );
		$this->assertStringEndsWith( '…', $cleaned['headline'] );
	}

	public function test_it_refuses_a_value_it_cannot_represent(): void {
		$cleaned = Trait_Sanitizer::clean( array( 'handle' => new \stdClass() ) );

		$this->assertSame( array(), $cleaned );
	}
}
