<?php
/**
 * Plugin settings.
 *
 * @package Munafa
 */

namespace Munafa\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads Munafa settings with their defaults.
 */
final class Options {

	/**
	 * Default value for each setting, keyed by setting name (without the `munafa_` prefix).
	 */
	const DEFAULTS = array(
		'courier_inside_dhaka'  => '60',
		'courier_outside_dhaka' => '120',
		'cod_fee_percent'       => '1',
		'gateway_fee_percent'   => '2',
		'return_charge_percent' => '50',
		'packaging_cost'        => '0',
	);

	/**
	 * WooCommerce state code for Dhaka district.
	 */
	const DHAKA_STATE = 'BD-13';

	/**
	 * Option name for a setting.
	 *
	 * @param string $key Setting name.
	 */
	public static function option_name( string $key ): string {
		return 'munafa_' . $key;
	}

	/**
	 * Store currency symbol for labels, without the trailing space WooCommerce adds to some (e.g. "৳&nbsp;").
	 */
	public static function currency_symbol(): string {
		return trim( str_replace( '&nbsp;', '', get_woocommerce_currency_symbol() ) );
	}

	/**
	 * All settings as floats, ready for the calculator.
	 *
	 * @return array<string, float>
	 */
	public static function all(): array {
		$settings = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$settings[ $key ] = (float) get_option( self::option_name( $key ), $default );
		}
		return $settings;
	}
}
