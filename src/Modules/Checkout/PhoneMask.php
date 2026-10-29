<?php
/**
 * Phone number patterns.
 *
 * @package Munafa
 */

namespace Munafa\Modules\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Checks phone numbers against the seller's patterns, where `#` is any digit
 * (e.g. `01#########` or `+8801#########`).
 *
 * Free of WordPress calls so it can be unit tested.
 */
final class PhoneMask {

	/**
	 * Unicode code points of "0" in the digit sets South Asian customers may type with
	 * (Arabic-Indic, Extended Arabic-Indic, Devanagari, Bengali, Gurmukhi, Gujarati, Oriya,
	 * Tamil, Telugu, Kannada, Malayalam, Sinhala, Myanmar). The other nine digits follow each.
	 */
	const DIGIT_ZEROS = array( 0x0660, 0x06F0, 0x0966, 0x09E6, 0x0A66, 0x0AE6, 0x0B66, 0x0BE6, 0x0C66, 0x0CE6, 0x0D66, 0x0DE6, 0x1040 );

	/**
	 * Native digits mapped to 0–9, once built.
	 *
	 * @var array<string, string>|null
	 */
	private static $digits = null;

	/**
	 * A phone number with native digits turned into 0–9 and spaces, dashes, dots and brackets removed.
	 *
	 * @param string $phone Phone number as typed.
	 */
	public static function normalize( string $phone ): string {
		if ( null === self::$digits ) {
			self::$digits = array();
			foreach ( self::DIGIT_ZEROS as $zero ) {
				for ( $digit = 0; $digit <= 9; $digit++ ) {
					self::$digits[ html_entity_decode( '&#' . ( $zero + $digit ) . ';', ENT_QUOTES, 'UTF-8' ) ] = (string) $digit;
				}
			}
		}

		return (string) preg_replace( '/[\s\-.()]+/u', '', strtr( trim( $phone ), self::$digits ) );
	}

	/**
	 * Whether a pattern is usable: digits and `#`, optionally starting with `+`.
	 *
	 * @param string $pattern Pattern.
	 */
	public static function is_valid_pattern( string $pattern ): bool {
		return 1 === preg_match( '/^\+?[0-9#]{3,20}$/', $pattern );
	}

	/**
	 * Whether a phone number fits any of the patterns.
	 *
	 * @param string   $phone    Phone number, as typed or normalized.
	 * @param string[] $patterns Patterns.
	 */
	public static function matches( string $phone, array $patterns ): bool {
		$phone = self::normalize( $phone );

		foreach ( $patterns as $pattern ) {
			$regex = '';
			foreach ( str_split( (string) $pattern ) as $char ) {
				$regex .= '#' === $char ? '[0-9]' : preg_quote( $char, '/' );
			}
			if ( 1 === preg_match( '/^' . $regex . '$/', $phone ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A pattern as customers read it, with X for any digit (e.g. `01XXXXXXXXX`).
	 *
	 * @param string $pattern Pattern.
	 */
	public static function example( string $pattern ): string {
		return str_replace( '#', 'X', $pattern );
	}
}
