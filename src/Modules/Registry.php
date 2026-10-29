<?php
/**
 * Module list and on/off state.
 *
 * @package Munafa
 */

namespace Munafa\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Knows every module, which ones are on, and starts those.
 */
final class Registry {

	/**
	 * Option with the on/off state of each module, keyed by module ID.
	 */
	const OPTION = 'munafa_modules';

	/**
	 * Modules keyed by ID, once collected.
	 *
	 * @var array<string, Module>|null
	 */
	private static $modules = null;

	/**
	 * Every module, built-in and from add-ons, keyed by ID.
	 *
	 * @return array<string, Module>
	 */
	public static function all(): array {
		if ( null !== self::$modules ) {
			return self::$modules;
		}

		/**
		 * Filters Munafa's modules. Add-ons append an instance of their Module subclass.
		 *
		 * @param Module[] $modules Modules.
		 */
		$modules = apply_filters( 'munafa_modules', array( new Checkout\CheckoutModule() ) );

		self::$modules = array();
		foreach ( (array) $modules as $module ) {
			if ( ! $module instanceof Module ) {
				continue;
			}
			$id = sanitize_key( $module->id() );
			// The first module with an ID wins, so an add-on can't replace a built-in one by accident.
			if ( '' !== $id && ! isset( self::$modules[ $id ] ) ) {
				self::$modules[ $id ] = $module;
			}
		}

		return self::$modules;
	}

	/**
	 * Whether a module is on.
	 *
	 * @param Module $module Module.
	 */
	public static function is_enabled( Module $module ): bool {
		$enabled = get_option( self::OPTION, array() );
		$id      = $module->id();

		return is_array( $enabled ) && isset( $enabled[ $id ] ) ? (bool) $enabled[ $id ] : $module->default_enabled();
	}

	/**
	 * Start every module that is on. Hooked to `plugins_loaded` through Plugin::boot().
	 */
	public static function boot(): void {
		foreach ( self::all() as $module ) {
			if ( self::is_enabled( $module ) ) {
				$module->register();
			}
		}
	}
}
