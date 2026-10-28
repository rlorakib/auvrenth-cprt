<?php
/**
 * Tests for courier zone matching.
 *
 * @package Munafa
 */

namespace Munafa\Tests\Unit;

use Munafa\Profit\ZoneMatcher;
use PHPUnit\Framework\TestCase;

/**
 * ZoneMatcher tests.
 */
final class ZoneMatcherTest extends TestCase {

	/**
	 * Zones a Bangladeshi seller might set up, plus one for India.
	 */
	private function zones(): array {
		return array(
			array(
				'id'    => 'dhaka',
				'areas' => array( 'BD:BD-13' ),
			),
			array(
				'id'    => 'sub-dhaka',
				'areas' => array( 'BD:BD-18', 'BD:BD-40' ),
			),
			array(
				'id'    => 'bangladesh',
				'areas' => array( 'BD' ),
			),
			array(
				'id'    => 'kolkata',
				'areas' => array( 'IN:WB' ),
			),
		);
	}

	/**
	 * A state listed in a zone matches that zone.
	 */
	public function test_state_match(): void {
		$this->assertSame( 'dhaka', ZoneMatcher::match( $this->zones(), 'BD', 'BD-13' )['id'] );
		$this->assertSame( 'sub-dhaka', ZoneMatcher::match( $this->zones(), 'BD', 'BD-40' )['id'] );
		$this->assertSame( 'kolkata', ZoneMatcher::match( $this->zones(), 'IN', 'WB' )['id'] );
	}

	/**
	 * A whole-country area catches every other state of that country.
	 */
	public function test_whole_country_match(): void {
		$this->assertSame( 'bangladesh', ZoneMatcher::match( $this->zones(), 'BD', 'BD-10' )['id'] );
		$this->assertSame( 'bangladesh', ZoneMatcher::match( $this->zones(), 'BD', '' )['id'] );
	}

	/**
	 * Zones are checked in order, so the first one that covers the address wins.
	 */
	public function test_first_zone_wins(): void {
		$zones = array_reverse( $this->zones() );
		$this->assertSame( 'bangladesh', ZoneMatcher::match( $zones, 'BD', 'BD-13' )['id'] );
	}

	/**
	 * Addresses no zone covers return null, so the caller uses "Everywhere else".
	 */
	public function test_no_match(): void {
		$this->assertNull( ZoneMatcher::match( $this->zones(), 'IN', 'MH' ) );
		$this->assertNull( ZoneMatcher::match( $this->zones(), 'PK', '' ) );
		$this->assertNull( ZoneMatcher::match( $this->zones(), '', 'BD-13' ) );
		$this->assertNull( ZoneMatcher::match( array(), 'BD', 'BD-13' ) );
	}
}
