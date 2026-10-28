<?php
/**
 * Net profit calculator.
 *
 * @package Munafa
 */

namespace Munafa\Profit;

defined( 'ABSPATH' ) || exit;

/**
 * Turns plain order numbers into a profit breakdown.
 *
 * Has no WordPress or WooCommerce dependencies so it can be unit tested directly.
 * Every amount is rounded to 2 decimals before it is added up, so totals match
 * what the seller sees on screen.
 */
final class Calculator {

	/**
	 * Courier charge for deliveries inside Dhaka.
	 *
	 * @var float
	 */
	private $courier_inside_dhaka;

	/**
	 * Courier charge for deliveries outside Dhaka.
	 *
	 * @var float
	 */
	private $courier_outside_dhaka;

	/**
	 * COD charge as a percentage of the order total.
	 *
	 * @var float
	 */
	private $cod_fee_percent;

	/**
	 * Online payment gateway fee as a percentage of the order total.
	 *
	 * @var float
	 */
	private $gateway_fee_percent;

	/**
	 * Constructor.
	 *
	 * @param array $settings {
	 *     Courier charges and fees.
	 *
	 *     @type float $courier_inside_dhaka  Courier charge inside Dhaka.
	 *     @type float $courier_outside_dhaka Courier charge outside Dhaka.
	 *     @type float $cod_fee_percent       COD charge in percent.
	 *     @type float $gateway_fee_percent   Gateway fee in percent.
	 * }
	 */
	public function __construct( array $settings ) {
		$this->courier_inside_dhaka  = (float) ( $settings['courier_inside_dhaka'] ?? 0 );
		$this->courier_outside_dhaka = (float) ( $settings['courier_outside_dhaka'] ?? 0 );
		$this->cod_fee_percent       = (float) ( $settings['cod_fee_percent'] ?? 0 );
		$this->gateway_fee_percent   = (float) ( $settings['gateway_fee_percent'] ?? 0 );
	}

	/**
	 * Calculate the profit breakdown for one order.
	 *
	 * @param array $order {
	 *     Order numbers.
	 *
	 *     @type float              $total        Order total paid by the customer, including shipping and tax.
	 *     @type float              $tax          Total tax in the order.
	 *     @type float              $refunded     Amount refunded so far.
	 *     @type array<float|null>  $item_costs   Cost of each line item (unit cost × net quantity). Null when unknown.
	 *     @type bool               $is_cod       Whether the order is paid cash on delivery.
	 *     @type bool               $inside_dhaka Whether the delivery address is inside Dhaka.
	 *     @type float              $return_cost  Extra loss from a returned parcel.
	 * }
	 * @return array{revenue: float, product_cost: float, courier_cost: float, cod_fee: float, gateway_fee: float, return_cost: float, net_profit: float, missing_cost: bool}
	 */
	public function calculate( array $order ): array {
		$total    = (float) ( $order['total'] ?? 0 );
		$tax      = (float) ( $order['tax'] ?? 0 );
		$refunded = (float) ( $order['refunded'] ?? 0 );

		$product_cost = 0.0;
		$missing_cost = false;
		foreach ( $order['item_costs'] ?? array() as $cost ) {
			if ( null === $cost ) {
				$missing_cost = true;
				continue;
			}
			$product_cost += (float) $cost;
		}

		$is_cod      = ! empty( $order['is_cod'] );
		$fee_percent = $is_cod ? $this->cod_fee_percent : $this->gateway_fee_percent;
		$fee         = $total > 0 ? self::round( $total * $fee_percent / 100 ) : 0.0;

		$breakdown = array(
			'revenue'      => self::round( $total - $tax - $refunded ),
			'product_cost' => self::round( $product_cost ),
			'courier_cost' => self::round( ! empty( $order['inside_dhaka'] ) ? $this->courier_inside_dhaka : $this->courier_outside_dhaka ),
			'cod_fee'      => $is_cod ? $fee : 0.0,
			'gateway_fee'  => $is_cod ? 0.0 : $fee,
			'return_cost'  => self::round( (float) ( $order['return_cost'] ?? 0 ) ),
		);

		$breakdown['net_profit'] = self::round(
			$breakdown['revenue']
			- $breakdown['product_cost']
			- $breakdown['courier_cost']
			- $breakdown['cod_fee']
			- $breakdown['gateway_fee']
			- $breakdown['return_cost']
		);

		$breakdown['missing_cost'] = $missing_cost;

		return $breakdown;
	}

	/**
	 * Round a money amount to 2 decimals.
	 *
	 * @param float $amount Amount.
	 */
	private static function round( float $amount ): float {
		// Adding 0.0 turns -0.0 into 0.0.
		return round( $amount, 2 ) + 0.0;
	}
}
