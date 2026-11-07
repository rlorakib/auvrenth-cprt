<?php
/**
 * "Returned" order status.
 *
 * @package Cprt
 */

namespace Cprt\Orders;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Returned status for parcels the courier brought back undelivered.
 */
final class ReturnStatus {

	/**
	 * Status slug without the `wc-` prefix, as WC_Order::get_status() returns it.
	 */
	const STATUS = 'returned';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_status' ) );
		add_filter( 'wc_order_statuses', array( self::class, 'add_to_list' ) );

		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( self::class, 'add_bulk_action' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( self::class, 'add_bulk_action' ) );

		add_action( 'woocommerce_order_status_' . self::STATUS, array( self::class, 'restock' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'badge_style' ) );
	}

	/**
	 * Register the post status (used by legacy order storage and status counts).
	 */
	public static function register_status(): void {
		register_post_status(
			'wc-' . self::STATUS,
			array(
				'label'                     => _x( 'Returned', 'Order status', 'auvrenth-cprt' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Returned <span class="count">(%s)</span>', 'Returned <span class="count">(%s)</span>', 'auvrenth-cprt' ),
			)
		);
	}

	/**
	 * Add Returned to WooCommerce's status list, right after Processing.
	 *
	 * @param array<string, string> $statuses Order statuses.
	 * @return array<string, string>
	 */
	public static function add_to_list( $statuses ) {
		$with_returned = array();
		foreach ( $statuses as $key => $label ) {
			$with_returned[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				$with_returned[ 'wc-' . self::STATUS ] = _x( 'Returned', 'Order status', 'auvrenth-cprt' );
			}
		}

		if ( ! isset( $with_returned[ 'wc-' . self::STATUS ] ) ) {
			$with_returned[ 'wc-' . self::STATUS ] = _x( 'Returned', 'Order status', 'auvrenth-cprt' );
		}

		return $with_returned;
	}

	/**
	 * Add "Change status to returned" to the orders list bulk actions.
	 * WooCommerce handles any `mark_<status>` action itself.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public static function add_bulk_action( $actions ) {
		$actions[ 'mark_' . self::STATUS ] = __( 'Change status to returned', 'auvrenth-cprt' );
		return $actions;
	}

	/**
	 * Put returned products back in stock. Does nothing if stock was never reduced.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 */
	public static function restock( $order_id, $order = null ): void {
		wc_maybe_increase_stock_levels( $order instanceof WC_Order ? $order : $order_id );
	}

	/**
	 * Colour the Returned badge in the orders list.
	 */
	public static function badge_style(): void {
		wp_register_style( 'auvrenth-cprt-badge', false, array(), CPRT_VERSION );
		wp_enqueue_style( 'auvrenth-cprt-badge' );
		wp_add_inline_style( 'auvrenth-cprt-badge', '.order-status.status-' . sanitize_html_class( self::STATUS ) . '{background:#f8d7da;color:#842029}' );
	}
}
