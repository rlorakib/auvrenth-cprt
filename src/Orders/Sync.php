<?php
/**
 * Keeps stored profit in step with order changes.
 *
 * @package Munafa
 */

namespace Munafa\Orders;

use Munafa\Profit\OrderProfit;

defined( 'ABSPATH' ) || exit;

/**
 * Recalculates profit whenever an order is created, saved or refunded.
 *
 * Orders are saved several times in one request (items, totals, status), so
 * changed IDs are collected and recalculated once at shutdown, from their final state.
 */
final class Sync {

	/**
	 * Order IDs waiting to be recalculated.
	 *
	 * @var array<int, true>
	 */
	private static $queue = array();

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_new_order', array( self::class, 'queue' ) );
		add_action( 'woocommerce_update_order', array( self::class, 'queue' ) );
		add_action( 'woocommerce_order_refunded', array( self::class, 'queue' ) );
		add_action( 'woocommerce_untrash_order', array( self::class, 'queue' ) );

		add_action( 'woocommerce_trash_order', array( self::class, 'remove' ) );
		add_action( 'woocommerce_delete_order', array( self::class, 'remove' ) );

		add_action( 'shutdown', array( self::class, 'flush' ) );
	}

	/**
	 * Mark an order for recalculation.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function queue( $order_id ): void {
		self::$queue[ absint( $order_id ) ] = true;
	}

	/**
	 * Forget a trashed or deleted order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function remove( $order_id ): void {
		$order_id = absint( $order_id );
		unset( self::$queue[ $order_id ] );
		OrderProfit::delete( $order_id );
	}

	/**
	 * Recalculate every queued order.
	 */
	public static function flush(): void {
		$order_ids   = array_keys( self::$queue );
		self::$queue = array();

		foreach ( $order_ids as $order_id ) {
			OrderProfit::recalculate( $order_id );
		}
	}
}
