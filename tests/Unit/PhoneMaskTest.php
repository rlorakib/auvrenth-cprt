<?php
/**
 * Tests for phone number patterns.
 *
 * @package Munafa
 */

namespace Munafa\Tests\Unit;

use Munafa\Modules\Checkout\PhoneMask;
use PHPUnit\Framework\TestCase;

/**
 * PhoneMask tests.
 */
final class PhoneMaskTest extends TestCase {

	/**
	 * Patterns a Bangladeshi seller might use: local and international.
	 */
	const BD = array( '01#########', '+8801#########' );

	/**
	 * Numbers in either format pass.
	 */
	public function test_matches_local_and_international_numbers(): void {
		$this->assertTrue( PhoneMask::matches( '01712345678', self::BD ) );
		$this->assertTrue( PhoneMask::matches( '+8801712345678', self::BD ) );
	}

	/**
	 * Too short, too long, or starting wrong fails.
	 */
	public function test_rejects_wrong_length_or_prefix(): void {
		$this->assertFalse( PhoneMask::matches( '0171234567', self::BD ) );
		$this->assertFalse( PhoneMask::matches( '017123456789', self::BD ) );
		$this->assertFalse( PhoneMask::matches( '02712345678', self::BD ) );
		$this->assertFalse( PhoneMask::matches( '8801712345678', self::BD ) );
		$this->assertFalse( PhoneMask::matches( '', self::BD ) );
	}

	/**
	 * Spacing customers add does not matter.
	 */
	public function test_ignores_spaces_dashes_and_brackets(): void {
		$this->assertTrue( PhoneMask::matches( ' 017-1234 5678 ', self::BD ) );
		$this->assertTrue( PhoneMask::matches( '+880 (17) 1234.5678', self::BD ) );
	}

	/**
	 * Digits typed on a Bangla or Hindi keyboard count as 0–9.
	 */
	public function test_accepts_native_digits(): void {
		$this->assertSame( '01712345678', PhoneMask::normalize( '০১৭১২৩৪৫৬৭৮' ) );
		$this->assertTrue( PhoneMask::matches( '०९८७६५४३२१', array( '##########' ) ) );
	}

	/**
	 * A leading + in a pattern must be typed.
	 */
	public function test_plus_is_literal(): void {
		$this->assertFalse( PhoneMask::matches( '8801712345678', array( '+8801#########' ) ) );
		$this->assertTrue( PhoneMask::matches( '+919876543210', array( '+91##########' ) ) );
	}

	/**
	 * Patterns may only hold digits, # and a leading +.
	 */
	public function test_validates_patterns(): void {
		$this->assertTrue( PhoneMask::is_valid_pattern( '01#########' ) );
		$this->assertTrue( PhoneMask::is_valid_pattern( '+92##########' ) );
		$this->assertFalse( PhoneMask::is_valid_pattern( '01XXXXXXXXX' ) );
		$this->assertFalse( PhoneMask::is_valid_pattern( '0+1###' ) );
		$this->assertFalse( PhoneMask::is_valid_pattern( '##' ) );
	}

	/**
	 * Error messages show X where any digit goes.
	 */
	public function test_example_shows_x_for_digits(): void {
		$this->assertSame( '01XXXXXXXXX', PhoneMask::example( '01#########' ) );
	}
}
