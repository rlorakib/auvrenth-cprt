<?php
/**
 * Order profit storage.
 *
 * @package Cprt
 */

namespace Cprt\Profit;

use Cprt\Db\Installer;
use Cprt\Orders\ReturnStatus;
use Cprt\Settings\Settings;
use WC_Order;
use WC_Order_Refund;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates an order's profit and stores it in the Auvrenth CPRT table.
 */
final class OrderProfit {

	/**
	 * Order meta key for a courier charge entered on the order, replacing the default.
	 */
	const COURIER_META = '_cprt_courier_cost';

	/**
	 * Order meta key for why a parcel was returned (a key of Settings::return_reasons()).
	 */
	const REASON_META = '_cprt_return_reason';

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

		$items                   = self::items( $order );
		$is_returned             = ReturnStatus::STATUS === $order->get_status();
		$breakdown               = ( new Calculator() )->calculate( self::input( $order, $items, $is_returned ) );
		$created                 = $order->get_date_created();
		list( $country, $state ) = self::delivery_address( $order );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP API for it.
		$wpdb->replace(
			Installer::table(),
			array(
				'order_id'         => $order->get_id(),
				'order_date_gmt'   => $created ? gmdate( 'Y-m-d H:i:s', $created->getTimestamp() ) : current_time( 'mysql', true ),
				'order_status'     => $order->get_status(),
				'payment_method'   => $order->get_payment_method(),
				'delivery_country' => $country,
				'delivery_state'   => $state,
				'revenue'          => $breakdown['revenue'],
				'product_cost'     => $breakdown['product_cost'],
				'courier_cost'     => $breakdown['courier_cost'],
				'cod_fee'          => $breakdown['cod_fee'],
				'gateway_fee'      => $breakdown['gateway_fee'],
				'return_cost'      => $breakdown['return_cost'],
				'packaging_cost'   => $breakdown['packaging_cost'],
				'net_profit'       => $breakdown['net_profit'],
				'return_reason'    => $is_returned ? (string) $order->get_meta( self::REASON_META ) : '',
				'missing_cost'     => $breakdown['missing_cost'] ? 1 : 0,
				'calculated_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%d', '%s' )
		);

		self::save_items( $order->get_id(), $items, $is_returned );
	}

	/**
	 * Replace the stored line items of an order.
	 *
	 * Returned orders keep their quantities (for return counts per product) but
	 * earn nothing and cost nothing, since the products went back to stock.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $items       Line items from items().
	 * @param bool  $is_returned Whether the order was returned.
	 */
	private static function save_items( int $order_id, array $items, bool $is_returned ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete( Installer::items_table(), array( 'order_id' => $order_id ), array( '%d' ) );

		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->insert(
				Installer::items_table(),
				array(
					'order_id'     => $order_id,
					'product_id'   => $item['product_id'],
					'variation_id' => $item['variation_id'],
					'qty'          => $item['qty'],
					'revenue'      => $is_returned ? 0 : round( $item['revenue'], 2 ),
					'cost'         => $is_returned || null === $item['cost'] ? 0 : round( $item['cost'], 2 ),
					'missing_cost' => ! $is_returned && null === $item['cost'] ? 1 : 0,
				),
				array( '%d', '%d', '%d', '%d', '%f', '%f', '%d' )
			);
		}
	}

	/**
	 * Product line items of an order, net of refunds.
	 *
	 * @param WC_Order $order Order.
	 * @return array<int, array{product_id: int, variation_id: int, qty: int, revenue: float, cost: float|null}>
	 */
	private static function items( WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$items[] = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				// get_qty_refunded_for_item() and get_total_refunded_for_item() return the refunded part (qty is negative).
				'qty'          => max( 0, $item->get_quantity() + $order->get_qty_refunded_for_item( $item->get_id() ) ),
				'revenue'      => (float) $item->get_total() - (float) $order->get_total_refunded_for_item( $item->get_id() ),
				'cost'         => CostResolver::item_cost( $item, $order ),
			);
		}
		return $items;
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is not user input.
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->delete( Installer::items_table(), array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Build the calculator input from an order.
	 *
	 * @param WC_Order $order       Order.
	 * @param array    $items       Line items from items().
	 * @param bool     $is_returned Whether the order was returned.
	 */
	private static function input( WC_Order $order, array $items, bool $is_returned ): array {
		$zone     = self::zone( $order );
		$method   = Settings::payment_method( $order->get_payment_method() );
		$override = self::courier_override( $order );

		return array(
			'total'          => (float) $order->get_total(),
			'tax'            => (float) $order->get_total_tax(),
			'refunded'       => (float) $order->get_total_refunded(),
			'item_costs'     => array_column( $items, 'cost' ),
			'is_cod'         => $method['is_cod'],
			'fee_percent'    => $method['fee_percent'],
			'is_returned'    => $is_returned,
			'courier_charge' => null === $override ? $zone['delivery_charge'] : $override,
			'return_percent' => $zone['return_percent'],
			'packaging_cost' => Settings::packaging_cost(),
		);
	}

	/**
	 * Courier zone the order's delivery address falls in, or "Everywhere else".
	 *
	 * @param WC_Order $order Order.
	 * @return array{id: string, name: string, areas: string[], delivery_charge: float, return_percent: float}
	 */
	public static function zone( WC_Order $order ): array {
		list( $country, $state ) = self::delivery_address( $order );

		$zone = ZoneMatcher::match( Settings::zones(), $country, $state );
		return $zone ? $zone : Settings::default_zone();
	}

	/**
	 * Courier charge entered on the order, or null to use the default.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function courier_override( WC_Order $order ): ?float {
		$value = $order->get_meta( self::COURIER_META );
		return '' === $value ? null : (float) $value;
	}

	/**
	 * Country and state the parcel goes to: the shipping address, or billing when there is none.
	 *
	 * @param WC_Order $order Order.
	 * @return array{0: string, 1: string}
	 */
	private static function delivery_address( WC_Order $order ): array {
		if ( $order->get_shipping_country() ) {
			return array( $order->get_shipping_country(), (string) $order->get_shipping_state() );
		}
		return array( (string) $order->get_billing_country(), (string) $order->get_billing_state() );
	}
}
