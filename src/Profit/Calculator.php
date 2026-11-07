<?php
/**
 * Net profit calculator.
 *
 * @package Cprt
 */

namespace Cprt\Profit;

defined( 'ABSPATH' ) || exit;

/**
 * Turns plain order numbers into a profit breakdown.
 *
 * Has no WordPress or WooCommerce dependencies so it can be unit tested directly.
 * The caller resolves the order's courier zone and payment method first, so the
 * calculator knows nothing about countries or gateways.
 * Every amount is rounded to 2 decimals before it is added up, so totals match
 * what the seller sees on screen.
 */
final class Calculator {

	/**
	 * Calculate the profit breakdown for one order.
	 *
	 * A returned order earns nothing and its products go back to stock, so it only
	 * loses the courier charge both ways, packaging, and any online payment fee.
	 *
	 * @param array $order {
	 *     Order numbers.
	 *
	 *     @type float              $total          Order total paid by the customer, including shipping and tax.
	 *     @type float              $tax            Total tax in the order.
	 *     @type float              $refunded       Amount refunded so far.
	 *     @type array<float|null>  $item_costs     Cost of each line item (unit cost × net quantity). Null when unknown.
	 *     @type bool               $is_cod         Whether the payment method is cash on delivery.
	 *     @type float              $fee_percent    Payment method fee (COD charge or gateway fee) in percent of the total.
	 *     @type bool               $is_returned    Whether the parcel came back undelivered.
	 *     @type float              $courier_charge Delivery charge for the order's zone, or the order's own charge.
	 *     @type float              $return_percent Return charge in percent of the courier charge.
	 *     @type float              $packaging_cost Packaging cost of the parcel.
	 * }
	 * @return array{revenue: float, product_cost: float, courier_cost: float, cod_fee: float, gateway_fee: float, return_cost: float, packaging_cost: float, net_profit: float, missing_cost: bool}
	 */
	public function calculate( array $order ): array {
		$total       = (float) ( $order['total'] ?? 0 );
		$tax         = (float) ( $order['tax'] ?? 0 );
		$refunded    = (float) ( $order['refunded'] ?? 0 );
		$is_cod      = ! empty( $order['is_cod'] );
		$is_returned = ! empty( $order['is_returned'] );

		$product_cost = 0.0;
		$missing_cost = false;
		foreach ( $order['item_costs'] ?? array() as $cost ) {
			if ( null === $cost ) {
				$missing_cost = true;
				continue;
			}
			$product_cost += (float) $cost;
		}

		$courier_cost = self::round( (float) ( $order['courier_charge'] ?? 0 ) );
		$fee          = $total > 0 ? self::round( $total * (float) ( $order['fee_percent'] ?? 0 ) / 100 ) : 0.0;

		$breakdown = array(
			'revenue'        => $is_returned ? 0.0 : self::round( $total - $tax - $refunded ),
			'product_cost'   => $is_returned ? 0.0 : self::round( $product_cost ),
			'courier_cost'   => $courier_cost,
			// The courier keeps its COD charge from cash it collects, so a returned COD parcel has none.
			'cod_fee'        => $is_cod && ! $is_returned ? $fee : 0.0,
			'gateway_fee'    => $is_cod ? 0.0 : $fee,
			'return_cost'    => $is_returned ? self::round( $courier_cost * (float) ( $order['return_percent'] ?? 0 ) / 100 ) : 0.0,
			'packaging_cost' => self::round( (float) ( $order['packaging_cost'] ?? 0 ) ),
		);

		$breakdown['net_profit'] = self::round(
			$breakdown['revenue']
			- $breakdown['product_cost']
			- $breakdown['courier_cost']
			- $breakdown['cod_fee']
			- $breakdown['gateway_fee']
			- $breakdown['return_cost']
			- $breakdown['packaging_cost']
		);

		// Unknown costs don't matter when nothing was sold.
		$breakdown['missing_cost'] = $missing_cost && ! $is_returned;

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
