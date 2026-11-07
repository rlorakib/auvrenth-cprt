<?php
/**
 * Profit box on the order edit screen.
 *
 * @package Cprt
 */

namespace Cprt\Orders;

use Cprt\Admin\DashboardPage;
use Cprt\Profit\OrderProfit;
use Cprt\Settings\Settings;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the order's profit breakdown in the sidebar of the order edit screen,
 * with fields for this order's courier charge and return reason.
 */
final class MetaBox {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		// After WooCommerce's own order meta boxes have saved (they use priorities up to 50).
		add_action( 'woocommerce_process_shop_order_meta', array( self::class, 'save' ), 60, 2 );
	}

	/**
	 * Add the meta box. Works with both HPOS and legacy post storage.
	 */
	public static function add(): void {
		// Same rule as the reports: only shop managers and admins see profit.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_meta_box(
			'cprt-order-profit',
			__( 'Auvrenth CPRT – Profit', 'auvrenth-cprt' ),
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

		self::render_breakdown( $order );
		self::render_fields( $order );
	}

	/**
	 * Render the stored profit breakdown.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function render_breakdown( WC_Order $order ): void {
		$row = OrderProfit::get( $order->get_id() );
		if ( ! $row ) {
			echo '<p>' . esc_html__( 'Profit is calculated after the order is saved.', 'auvrenth-cprt' ) . '</p>';
			return;
		}

		$currency = array( 'currency' => $order->get_currency() );
		$lines    = array(
			'revenue'        => __( 'Revenue', 'auvrenth-cprt' ),
			'product_cost'   => __( 'Product cost', 'auvrenth-cprt' ),
			'courier_cost'   => __( 'Courier charge', 'auvrenth-cprt' ),
			'cod_fee'        => __( 'COD charge', 'auvrenth-cprt' ),
			'gateway_fee'    => __( 'Gateway fee', 'auvrenth-cprt' ),
			'return_cost'    => __( 'Return charge', 'auvrenth-cprt' ),
			'packaging_cost' => __( 'Packaging', 'auvrenth-cprt' ),
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
			esc_html__( 'Net profit', 'auvrenth-cprt' ),
			$profit < 0 ? '#b32d2e' : '#007017',
			wp_kses_post( wc_price( $profit, $currency ) )
		);
		echo '</tbody></table>';

		if ( (int) $row['missing_cost'] ) {
			echo '<p class="description" style="color:#996800">';
			echo esc_html__( 'Some products in this order have no cost price, so profit is shown higher than it really is.', 'auvrenth-cprt' );
			echo '</p>';
		}
	}

	/**
	 * Render the courier charge and return reason fields.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function render_fields( WC_Order $order ): void {
		$zone     = OrderProfit::zone( $order );
		$override = OrderProfit::courier_override( $order );
		$reason   = (string) $order->get_meta( OrderProfit::REASON_META );

		if ( ! Settings::is_setup_done() ) {
			printf(
				'<p class="description" style="color:#996800">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Auvrenth CPRT is not set up yet, so courier charges and fees may be wrong.', 'auvrenth-cprt' ),
				esc_url( DashboardPage::settings_url() ),
				esc_html__( 'Set up Auvrenth CPRT', 'auvrenth-cprt' )
			);
		}
		?>
		<p>
			<label for="cprt_courier_cost">
				<?php
				/* translators: %s: currency symbol */
				echo esc_html( sprintf( __( 'Courier charge for this order (%s)', 'auvrenth-cprt' ), Settings::currency_symbol() ) );
				?>
			</label>
			<input type="number" min="0" step="0.01" class="widefat" id="cprt_courier_cost" name="cprt_courier_cost"
				value="<?php echo esc_attr( null === $override ? '' : wc_format_decimal( $override ) ); ?>"
				placeholder="<?php echo esc_attr( sprintf( /* translators: 1: courier zone name, 2: its delivery charge */ __( '%1$s: %2$s', 'auvrenth-cprt' ), $zone['name'], wc_format_decimal( $zone['delivery_charge'] ) ) ); ?>">
		</p>
		<p>
			<label for="cprt_return_reason"><?php esc_html_e( 'Return reason (for returned orders)', 'auvrenth-cprt' ); ?></label>
			<select class="widefat" id="cprt_return_reason" name="cprt_return_reason">
				<option value=""><?php esc_html_e( '— None —', 'auvrenth-cprt' ); ?></option>
				<?php foreach ( Settings::return_reasons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $reason, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Save the fields. WooCommerce has already checked the nonce and that the user can edit the order.
	 *
	 * @param int                   $order_id Order ID.
	 * @param WC_Order|WP_Post|null $order    Order being edited (HPOS) or its post (legacy storage).
	 */
	public static function save( $order_id, $order = null ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['cprt_courier_cost'] ) ) {
			// The box was not on the form, so there is nothing to save (and nothing to clear).
			return;
		}

		// Reuse WooCommerce's order object under HPOS so a later save of it can't overwrite our meta.
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
		$courier = wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['cprt_courier_cost'] ) ) );
		$reason  = isset( $_POST['cprt_return_reason'] ) ? sanitize_key( wp_unslash( $_POST['cprt_return_reason'] ) ) : '';
		// phpcs:enable

		if ( '' === $courier ) {
			$order->delete_meta_data( OrderProfit::COURIER_META );
		} elseif ( is_numeric( $courier ) && (float) $courier >= 0 ) {
			$order->update_meta_data( OrderProfit::COURIER_META, $courier );
		}
		// Anything else (a negative charge) is a typo; the saved charge stays as it was.

		if ( isset( Settings::return_reasons()[ $reason ] ) ) {
			$order->update_meta_data( OrderProfit::REASON_META, $reason );
		} else {
			$order->delete_meta_data( OrderProfit::REASON_META );
		}

		// Saving queues the order in Orders\Sync, which recalculates its profit.
		$order->save();
	}
}
