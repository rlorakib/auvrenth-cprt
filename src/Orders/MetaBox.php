<?php
/**
 * Profit box on the order edit screen.
 *
 * @package Munafa
 */

namespace Munafa\Orders;

use Munafa\Profit\OrderProfit;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the order's profit breakdown in the sidebar of the order edit screen.
 */
final class MetaBox {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
	}

	/**
	 * Add the meta box. Works with both HPOS and legacy post storage.
	 */
	public static function add(): void {
		add_meta_box(
			'munafa-order-profit',
			__( 'Munafa – Profit', 'munafa' ),
			array( self::class, 'render' ),
			wc_get_page_screen_id( 'shop-order' ),
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post (legacy storage) or order (HPOS).
	 */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$row = OrderProfit::get( $order->get_id() );
		if ( ! $row ) {
			echo '<p>' . esc_html__( 'Profit is calculated after the order is saved.', 'munafa' ) . '</p>';
			return;
		}

		$currency = array( 'currency' => $order->get_currency() );
		$lines    = array(
			'revenue'      => __( 'Revenue', 'munafa' ),
			'product_cost' => __( 'Product cost', 'munafa' ),
			'courier_cost' => __( 'Courier charge', 'munafa' ),
			'cod_fee'      => __( 'COD charge', 'munafa' ),
			'gateway_fee'  => __( 'Gateway fee', 'munafa' ),
			'return_cost'  => __( 'Return loss', 'munafa' ),
		);

		echo '<table class="widefat striped" style="border:0"><tbody>';
		foreach ( $lines as $key => $label ) {
			$amount = (float) $row[ $key ];
			if ( 'revenue' !== $key && 0.0 === $amount ) {
				continue;
			}
			printf(
				'<tr><td>%1$s</td><td style="text-align:right">%2$s%3$s</td></tr>',
				esc_html( $label ),
				'revenue' === $key ? '' : '&minus; ',
				wp_kses_post( wc_price( $amount, $currency ) )
			);
		}

		$profit = (float) $row['net_profit'];
		printf(
			'<tr><td><strong>%1$s</strong></td><td style="text-align:right;color:%2$s"><strong>%3$s</strong></td></tr>',
			esc_html__( 'Net profit', 'munafa' ),
			$profit < 0 ? '#b32d2e' : '#007017',
			wp_kses_post( wc_price( $profit, $currency ) )
		);
		echo '</tbody></table>';

		if ( (int) $row['missing_cost'] ) {
			echo '<p class="description" style="color:#996800">';
			echo esc_html__( 'Some products in this order have no cost price, so profit is shown higher than it really is.', 'munafa' );
			echo '</p>';
		}
	}
}
