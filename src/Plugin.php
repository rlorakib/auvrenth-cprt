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
		// Late on init, so future migration steps can use WooCommerce's payment gateways.
		add_action( 'init', array( Settings\Settings::class, 'maybe_migrate' ), 20 );

		Orders\ReturnStatus::register();
		Orders\Sync::register();
		Tools\Recalculator::register();
		Rest\ReportsController::register();
		Rest\SettingsController::register();

		if ( is_admin() ) {
			Admin\DashboardPage::register();
			Orders\MetaBox::register();
			Products\CostField::register();
		}
	}
}
