<?php
/**
 * Order profit storage.
 *
 * @package Munafa
 */

namespace Munafa\Profit;

use Munafa\Db\Installer;
use Munafa\Settings\Options;
use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates an order's profit and stores it in the Munafa table.
 */
final class OrderProfit {

	/**
	 * Recalculate and save the profit of one order.
	 *
	 * @param int|WC_Order $order Order ID or object.
	 */
	public static function recalculate( $order ): void {
		global $wpdb;

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order instanceof WC_Order || $order instanceof WC_Order_Refund ) {
			return;
		}

		$calculator = new Calculator( Options::all() );
		$breakdown  = $calculator->calculate( self::input( $order ) );
		$created    = $order->get_date_created();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP API for it.
		$wpdb->replace(
			Installer::table(),
			array(
				'order_id'       => $order->get_id(),
				'order_date_gmt' => $created ? gmdate( 'Y-m-d H:i:s', $created->getTimestamp() ) : current_time( 'mysql', true ),
				'order_status'   => $order->get_status(),
				'payment_method' => $order->get_payment_method(),
				'delivery_state' => self::delivery_state( $order ),
				'revenue'        => $breakdown['revenue'],
				'product_cost'   => $breakdown['product_cost'],
				'courier_cost'   => $breakdown['courier_cost'],
				'cod_fee'        => $breakdown['cod_fee'],
				'gateway_fee'    => $breakdown['gateway_fee'],
				'return_cost'    => $breakdown['return_cost'],
				'net_profit'     => $breakdown['net_profit'],
				'missing_cost'   => $breakdown['missing_cost'] ? 1 : 0,
				'calculated_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%d', '%s' )
		);
	}

	/**
	 * Stored profit row for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, string>|null
	 */
	public static function get( int $order_id ): ?array {
		global $wpdb;

		$table = Installer::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name is not user input.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d", $order_id ), ARRAY_A );

		return $row ? $row : null;
	}

	/**
	 * Remove the stored profit of an order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function delete( int $order_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete( Installer::table(), array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Build the calculator input from an order.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function input( WC_Order $order ): array {
		$item_costs = array();
		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$item_costs[] = CostResolver::item_cost( $item, $order );
			}
		}

		$country = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();

		return array(
			'total'        => (float) $order->get_total(),
			'tax'          => (float) $order->get_total_tax(),
			'refunded'     => (float) $order->get_total_refunded(),
			'item_costs'   => $item_costs,
			'is_cod'       => 'cod' === $order->get_payment_method(),
			'inside_dhaka' => 'BD' === $country && Options::DHAKA_STATE === self::delivery_state( $order ),
			'return_cost'  => 0,
		);
	}

	/**
	 * State (district) the parcel goes to: shipping address, or billing when there is none.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function delivery_state( WC_Order $order ): string {
		return $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
	}
}
