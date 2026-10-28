<?php
/**
 * WooCommerce → Munafa dashboard page.
 *
 * @package Munafa
 */

namespace Munafa\Admin;

use Munafa\Settings\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page that hosts the React dashboard.
 */
final class DashboardPage {

	/**
	 * Admin page slug.
	 */
	const SLUG = 'munafa';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private static $hook_suffix = '';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		// After WooCommerce adds its menu (priority 9) and its own pages.
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Add the page under the WooCommerce menu.
	 */
	public static function add_menu(): void {
		self::$hook_suffix = (string) add_submenu_page(
			'woocommerce',
			__( 'Munafa – Profit dashboard', 'munafa' ),
			__( 'Munafa', 'munafa' ),
			'manage_woocommerce',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * Page markup; React mounts into the div.
	 */
	public static function render(): void {
		echo '<div class="wrap"><div id="munafa-dashboard"></div></div>';
	}

	/**
	 * Load the dashboard bundle on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( '' === self::$hook_suffix || $hook_suffix !== self::$hook_suffix ) {
			return;
		}

		$asset_file = MUNAFA_PATH . 'assets/build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Munafa: the dashboard is not built yet. Run "npm install" and "npm run build" in the plugin folder.', 'munafa' );
					echo '</p></div>';
				}
			);
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script( 'munafa-dashboard', MUNAFA_URL . 'assets/build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'munafa-dashboard', 'munafa', MUNAFA_PATH . 'languages' );

		wp_enqueue_style( 'wp-components' );
		if ( file_exists( MUNAFA_PATH . 'assets/build/style-index.css' ) ) {
			wp_enqueue_style( 'munafa-dashboard', MUNAFA_URL . 'assets/build/style-index.css', array( 'wp-components' ), $asset['version'] );
		}

		wp_add_inline_script(
			'munafa-dashboard',
			'window.munafaSettings = ' . wp_json_encode(
				array(
					// WooCommerce stores symbols as HTML entities ("&#2547;"); React prints text as is.
					'currency'    => html_entity_decode( Options::currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'decimals'    => wc_get_price_decimals(),
					'productsUrl' => admin_url( 'edit.php?post_type=product' ),
					'settingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=munafa' ),
					'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
					'today'       => wp_date( 'Y-m-d' ),
					'locale'      => str_replace( '_', '-', get_user_locale() ),
				)
			) . ';',
			'before'
		);
	}
}
