<?php
/**
 * Tests for Capabilities.
 *
 * @package Cherrylift
 */

namespace Cherrylift\Tests\Unit\Support;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Cherrylift\Support\Capabilities;
use Cherrylift\Tests\Unit\TestCase;

/**
 * @covers \Cherrylift\Support\Capabilities
 */
class CapabilitiesTest extends TestCase {

	public function test_default_capability_is_manage_options(): void {
		$this->assertSame( 'manage_options', ( new Capabilities() )->get_capability() );
	}

	public function test_capability_can_be_filtered(): void {
		Filters\expectApplied( 'cherrylift_capability' )
			->once()
			->with( 'manage_options' )
			->andReturn( 'edit_others_posts' );

		$this->assertSame( 'edit_others_posts', ( new Capabilities() )->get_capability() );
	}

	/**
	 * @dataProvider invalid_capabilities
	 *
	 * @param mixed $filtered Invalid value returned by the filter.
	 */
	public function test_invalid_filtered_capability_falls_back_to_default( $filtered ): void {
		Filters\expectApplied( 'cherrylift_capability' )->andReturn( $filtered );

		$this->assertSame( 'manage_options', ( new Capabilities() )->get_capability() );
	}

	/**
	 * @return array<string, array{mixed}>
	 */
	public function invalid_capabilities(): array {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
			'array'        => array( array( 'manage_options' ) ),
		);
	}

	public function test_current_user_can_manage_checks_the_filtered_capability(): void {
		Filters\expectApplied( 'cherrylift_capability' )->andReturn( 'edit_pages' );
		Functions\expect( 'current_user_can' )->once()->with( 'edit_pages' )->andReturn( true );

		$this->assertTrue( ( new Capabilities() )->current_user_can_manage() );
	}
}
