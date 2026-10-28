<?php
/**
 * Courier zone matching.
 *
 * @package Munafa
 */

namespace Munafa\Profit;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the courier zone for a delivery address.
 *
 * Has no WordPress dependencies so it can be unit tested directly.
 */
final class ZoneMatcher {

	/**
	 * First zone that covers the address, in the seller's order.
	 *
	 * A zone covers an address when one of its areas is the address's state
	 * (`BD:BD-13`) or its whole country (`BD`).
	 *
	 * @param array  $zones   Zones, each with an `areas` list.
	 * @param string $country Two-letter country code.
	 * @param string $state   State code, or empty.
	 * @return array|null The zone, or null when none covers the address.
	 */
	public static function match( array $zones, string $country, string $state ): ?array {
		if ( '' === $country ) {
			return null;
		}

		$targets = array( $country );
		if ( '' !== $state ) {
			$targets[] = "{$country}:{$state}";
		}

		foreach ( $zones as $zone ) {
			if ( array_intersect( $targets, (array) ( $zone['areas'] ?? array() ) ) ) {
				return $zone;
			}
		}

		return null;
	}
}
