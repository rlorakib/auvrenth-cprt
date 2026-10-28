<?php
/**
 * Cost price field on the product edit screen.
 *
 * @package Munafa
 */

namespace Munafa\Products;

use Munafa\Profit\CostResolver;
use Munafa\Settings\Options;
use WC_Product;
use WC_Product_Variation;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Cost price" field to simple products and variations.
 *
 * Only used when WooCommerce's native Cost of Goods Sold feature is off;
 * otherwise WooCommerce shows its own cost field.
 */
final class CostField {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		if ( CostResolver::native_cogs_enabled() ) {
			return;
		}

		add_action( 'woocommerce_product_options_pricing', array( self::class, 'render_simple' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_simple' ) );

		add_action( 'woocommerce_variation_options_pricing', array( self::class, 'render_variation' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( self::class, 'save_variation' ), 10, 2 );
	}

	/**
	 * Field label with the store currency symbol.
	 */
	private static function label(): string {
		/* translators: %s: currency symbol */
		return sprintf( __( 'Cost price (%s)', 'munafa' ), Options::currency_symbol() );
	}

	/**
	 * Render the field in the General tab of a simple product.
	 */
	public static function render_simple(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => CostResolver::META_KEY,
				'label'       => self::label(),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'What one unit costs you. Munafa uses it to calculate order profit.', 'munafa' ),
			)
		);
	}

	/**
	 * Save the simple product field. WooCommerce has already checked the nonce and capability.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save_simple( WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
		if ( isset( $_POST[ CostResolver::META_KEY ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
			self::save( $product, sanitize_text_field( wp_unslash( $_POST[ CostResolver::META_KEY ] ) ) );
		}
	}

	/**
	 * Render the field for one variation.
	 *
	 * @param int     $loop           Variation index.
	 * @param array   $variation_data Variation data (unused).
	 * @param WP_Post $variation      Variation post.
	 */
	public static function render_variation( $loop, $variation_data, $variation ): void {
		$product = wc_get_product( $variation->ID );

		woocommerce_wp_text_input(
			array(
				'id'            => CostResolver::META_KEY . "_{$loop}",
				'name'          => CostResolver::META_KEY . "[{$loop}]",
				'value'         => $product ? wc_format_localized_price( $product->get_meta( CostResolver::META_KEY ) ) : '',
				'label'         => self::label(),
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
				'placeholder'   => __( 'Same as parent product', 'munafa' ),
			)
		);
	}

	/**
	 * Save one variation's field.
	 *
	 * @param WC_Product_Variation $variation Variation being saved.
	 * @param int                  $index     Variation index in the submitted form.
	 */
	public static function save_variation( WC_Product_Variation $variation, $index ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
		if ( isset( $_POST[ CostResolver::META_KEY ][ $index ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WooCommerce before this hook.
			self::save( $variation, sanitize_text_field( wp_unslash( $_POST[ CostResolver::META_KEY ][ $index ] ) ) );
		}
	}

	/**
	 * Store a cost value, or remove it when empty.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $value   Submitted value.
	 */
	private static function save( WC_Product $product, string $value ): void {
		$value = wc_format_decimal( $value );

		if ( '' === $value ) {
			$product->delete_meta_data( CostResolver::META_KEY );
			return;
		}

		$product->update_meta_data( CostResolver::META_KEY, $value );
	}
}
