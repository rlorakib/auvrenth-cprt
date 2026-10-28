<?php
/**
 * WooCommerce → Settings → Munafa tab.
 *
 * @package Munafa
 */

namespace Munafa\Settings;

use Munafa\Tools\Recalculator;
use WC_Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab for courier charges and fees.
 *
 * Only instantiated from the `woocommerce_get_settings_pages` filter (see Plugin::add_settings_page()),
 * when WC_Settings_Page is loaded. Never reference this class earlier.
 */
final class SettingsPage extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'munafa';
		$this->label = __( 'Munafa', 'munafa' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_munafa_recalculate', array( $this, 'render_recalculate_field' ) );
	}

	/**
	 * Settings for the default section.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		$currency = Options::currency_symbol();

		return array(
			array(
				'id'    => 'munafa_costs',
				'type'  => 'title',
				'title' => __( 'Costs per order', 'munafa' ),
				'desc'  => __( 'Munafa subtracts these from every order to show its real profit. After changing them, recalculate all orders below.', 'munafa' ),
			),
			array(
				'id'                => Options::option_name( 'courier_inside_dhaka' ),
				/* translators: %s: currency symbol */
				'title'             => sprintf( __( 'Courier charge inside Dhaka (%s)', 'munafa' ), $currency ),
				'type'              => 'number',
				'default'           => Options::DEFAULTS['courier_inside_dhaka'],
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'id'                => Options::option_name( 'courier_outside_dhaka' ),
				/* translators: %s: currency symbol */
				'title'             => sprintf( __( 'Courier charge outside Dhaka (%s)', 'munafa' ), $currency ),
				'type'              => 'number',
				'default'           => Options::DEFAULTS['courier_outside_dhaka'],
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'id'                => Options::option_name( 'cod_fee_percent' ),
				'title'             => __( 'COD charge (%)', 'munafa' ),
				'desc_tip'          => __( 'What the courier keeps from cash on delivery orders, as a percentage of the order total.', 'munafa' ),
				'type'              => 'number',
				'default'           => Options::DEFAULTS['cod_fee_percent'],
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '0.01',
				),
			),
			array(
				'id'                => Options::option_name( 'gateway_fee_percent' ),
				'title'             => __( 'Online payment fee (%)', 'munafa' ),
				'desc_tip'          => __( 'What bKash, Nagad, SSLCommerz or your card gateway charges per order.', 'munafa' ),
				'type'              => 'number',
				'default'           => Options::DEFAULTS['gateway_fee_percent'],
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '0.01',
				),
			),
			array(
				'id'   => 'munafa_recalculate',
				'type' => 'munafa_recalculate',
			),
			array(
				'id'   => 'munafa_costs',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Render the "Recalculate all orders" row.
	 */
	public function render_recalculate_field(): void {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=munafa_recalculate' ), 'munafa_recalculate' );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Recalculate', 'munafa' ); ?></th>
			<td class="forminp">
				<?php if ( Recalculator::is_running() ) : ?>
					<p><?php esc_html_e( 'Recalculating all orders in the background. This can take a few minutes on big stores.', 'munafa' ); ?></p>
				<?php else : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="button"><?php esc_html_e( 'Recalculate all orders', 'munafa' ); ?></a>
					<p class="description"><?php esc_html_e( 'Save your changes first. Uses the current product costs and the settings above.', 'munafa' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
}
