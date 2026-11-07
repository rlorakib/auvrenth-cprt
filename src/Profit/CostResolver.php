<?php
/**
 * Product cost lookup.
 *
 * @package Cprt
 */

namespace Cprt\Profit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the cost of an order line item.
 *
 * Uses WooCommerce's native Cost of Goods Sold when that feature is on,
 * otherwise Auvrenth CPRT's own `_cprt_cost` product meta.
 */
final class CostResolver {

	/**
	 * Product meta key for the fallback cost field.
	 */
	const META_KEY = '_cprt_cost';

	/**
	 * Whether WooCommerce's native Cost of Goods Sold feature is enabled.
	 */
	public static function native_cogs_enabled(): bool {
		return class_exists( FeaturesUtil::class ) && FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
	}

	/**
	 * Cost of a line item for the quantity that was not refunded.
	 *
	 * @param WC_Order_Item_Product $item  Line item.
	 * @param WC_Order              $order Order the item belongs to.
	 * @return float|null Null when the product has no cost set.
	 */
	public static function item_cost( WC_Order_Item_Product $item, WC_Order $order ): ?float {
		$product = $item->get_product();
		if ( ! $product ) {
			return null;
		}

		$unit_cost = self::unit_cost( $product );
		if ( null === $unit_cost ) {
			return null;
		}

		// get_qty_refunded_for_item() returns a negative number.
		$quantity = max( 0, $item->get_quantity() + $order->get_qty_refunded_for_item( $item->get_id() ) );

		return $unit_cost * $quantity;
	}

	/**
	 * Cost of one unit of a product.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return float|null Null when no cost is set.
	 */
	public static function unit_cost( WC_Product $product ): ?float {
		if ( self::native_cogs_enabled() ) {
			// Variations may inherit (or add to) the parent's cost, which get_cogs_total_value() resolves.
			$total = $product->get_cogs_total_value();
			return ( 0.0 === $total && null === $product->get_cogs_value() ) ? null : $total;
		}

		$cost = $product->get_meta( self::META_KEY );

		if ( '' === $cost && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$cost   = $parent ? $parent->get_meta( self::META_KEY ) : '';
		}

		return '' === $cost ? null : (float) $cost;
	}
}
