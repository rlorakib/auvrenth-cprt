<?php
/**
 * WooCommerce feature compatibility declarations.
 *
 * @package Cprt
 */

namespace Cprt;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Tells WooCommerce which of its features Auvrenth CPRT supports.
 */
final class Compatibility {

	/**
	 * Declare HPOS and Cart/Checkout Blocks compatibility.
	 *
	 * Hooked to `before_woocommerce_init`.
	 */
	public static function declare(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', CPRT_FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CPRT_FILE, true );
	}
}
