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

		Orders\Sync::register();
		Tools\Recalculator::register();

		if ( is_admin() ) {
			Orders\MetaBox::register();
			Products\CostField::register();
			Settings\SettingsPage::register();
		}
	}
}
