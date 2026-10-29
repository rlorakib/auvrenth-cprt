<?php
/**
 * WooCommerce → Munafa and Munafa Settings pages.
 *
 * @package Munafa
 */

namespace Munafa\Admin;

use Munafa\Reports\Repository;
use Munafa\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Admin pages that host the React app: the dashboard and the settings screen.
 */
final class DashboardPage {

	/**
	 * Dashboard page slug.
	 */
	const SLUG = 'munafa';

	/**
	 * Settings page slug.
	 */
	const SETTINGS_SLUG = 'munafa-settings';

	/**
	 * Hook suffixes returned by add_submenu_page(), mapped to the view they show.
	 *
	 * @var array<string, string>
	 */
	private static $views = array();

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		// After WooCommerce adds its menu (priority 9) and its own pages.
		add_action( 'admin_menu', array( self::class, 'add_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * URL of the settings page.
	 */
	public static function settings_url(): string {
		return admin_url( 'admin.php?page=' . self::SETTINGS_SLUG );
	}

	/**
	 * Add both pages under the WooCommerce menu.
	 */
	public static function add_menu(): void {
		$dashboard = add_submenu_page(
			'woocommerce',
			__( 'Munafa – Profit dashboard', 'munafa' ),
			__( 'Munafa', 'munafa' ),
			'manage_woocommerce',
			self::SLUG,
			array( self::class, 'render_dashboard' )
		);
		$settings  = add_submenu_page(
			'woocommerce',
			__( 'Munafa settings', 'munafa' ),
			__( 'Munafa Settings', 'munafa' ),
			'manage_woocommerce',
			self::SETTINGS_SLUG,
			array( self::class, 'render_settings' )
		);

		self::$views = array_filter(
			array(
				(string) $dashboard => 'dashboard',
				(string) $settings  => 'settings',
			)
		);
	}

	/**
	 * Dashboard markup; React mounts into the div.
	 */
	public static function render_dashboard(): void {
		echo '<div class="wrap"><div id="munafa-dashboard" data-view="dashboard"></div></div>';
	}

	/**
	 * Settings markup; React mounts into the div.
	 */
	public static function render_settings(): void {
		echo '<div class="wrap"><div id="munafa-dashboard" data-view="settings"></div></div>';
	}

	/**
	 * Load the app bundle on Munafa's pages only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ): void {
		if ( ! isset( self::$views[ $hook_suffix ] ) ) {
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
			// The build writes style-index-rtl.css for right-to-left languages such as Urdu.
			wp_style_add_data( 'munafa-dashboard', 'rtl', 'replace' );
		}

		wp_add_inline_script(
			'munafa-dashboard',
			'window.munafaSettings = ' . wp_json_encode(
				array(
					// WooCommerce stores symbols as HTML entities ("&#2547;"); React prints text as is.
					'currency'      => html_entity_decode( Settings::currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'decimals'      => wc_get_price_decimals(),
					'productsUrl'   => admin_url( 'edit.php?post_type=product' ),
					'settingsUrl'   => self::settings_url(),
					'setupDone'     => Settings::is_setup_done(),
					'startOfWeek'   => (int) get_option( 'start_of_week', 1 ),
					'maxHourlyDays' => Repository::MAX_HOURLY_DAYS,
					'today'         => wp_date( 'Y-m-d' ),
					'locale'        => str_replace( '_', '-', get_user_locale() ),
				)
			) . ';',
			'before'
		);
	}
}
