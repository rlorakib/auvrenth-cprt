<?php
/**
 * Background recalculation of all orders.
 *
 * @package Munafa
 */

namespace Munafa\Tools;

use Munafa\Db\Installer;
use Munafa\Profit\OrderProfit;

defined( 'ABSPATH' ) || exit;

/**
 * Recalculates every order in batches with Action Scheduler.
 */
final class Recalculator {

	/**
	 * Action Scheduler hook that processes one batch.
	 */
	const ACTION = 'munafa_recalculate_batch';

	/**
	 * Action Scheduler group.
	 */
	const GROUP = 'munafa';

	/**
	 * Orders per batch.
	 */
	const BATCH_SIZE = 50;

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( self::ACTION, array( self::class, 'run_batch' ) );
		add_action( 'action_scheduler_init', array( self::class, 'maybe_start_after_install' ) );
	}

	/**
	 * Start from the first batch, replacing any run already in progress.
	 */
	public static function start(): void {
		as_unschedule_all_actions( self::ACTION, array(), self::GROUP );
		as_enqueue_async_action( self::ACTION, array( 1 ), self::GROUP );
	}

	/**
	 * Whether a recalculation is queued or running.
	 */
	public static function is_running(): bool {
		return as_has_scheduled_action( self::ACTION, null, self::GROUP );
	}

	/**
	 * Recalculate one page of orders and queue the next page.
	 *
	 * @param int $page Page number, starting at 1.
	 */
	public static function run_batch( $page ): void {
		$page      = max( 1, absint( $page ) );
		$order_ids = wc_get_orders(
			array(
				'type'    => 'shop_order',
				'status'  => array_keys( wc_get_order_statuses() ),
				'limit'   => self::BATCH_SIZE,
				'paged'   => $page,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		foreach ( $order_ids as $order_id ) {
			OrderProfit::recalculate( $order_id );
		}

		if ( count( $order_ids ) === self::BATCH_SIZE ) {
			as_enqueue_async_action( self::ACTION, array( $page + 1 ), self::GROUP );
		}
	}

	/**
	 * Start the first full recalculation right after the plugin is installed.
	 */
	public static function maybe_start_after_install(): void {
		if ( 'yes' !== get_option( Installer::RECALC_OPTION ) ) {
			return;
		}

		delete_option( Installer::RECALC_OPTION );
		self::start();
	}
}
