<?php
/**
 * Main plugin bootstrap.
 *
 * @package Munafa
 */

namespace Munafa;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up Munafa once all plugins are loaded.
 */
final class Plugin {

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Start the plugin. Hooked to `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// "Requires Plugins" blocks activation without WooCommerce, but it can still be deactivated later.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		Db\Installer::maybe_upgrade();

		Orders\ReturnStatus::register();
		Orders\Sync::register();
		Tools\Recalculator::register();
		Rest\ReportsController::register();

		if ( is_admin() ) {
			Admin\DashboardPage::register();
			Orders\MetaBox::register();
			Products\CostField::register();
			// SettingsPage extends WC_Settings_Page, which only exists inside this filter, so don't touch the class before it.
			add_filter( 'woocommerce_get_settings_pages', array( self::class, 'add_settings_page' ) );
		}
	}

	/**
	 * Add the Munafa tab to WooCommerce → Settings.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public static function add_settings_page( $pages ) {
		$pages[] = new Settings\SettingsPage();
		return $pages;
	}
}
